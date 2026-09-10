<?php

namespace App\Http\Controllers;

use App\Models\Campanha;
use App\Models\Colheita;
use App\Models\Custo;
use App\Models\Despesa;
use App\Models\Lote;
use App\Models\FaturaItem;
use App\Models\Produto;
use App\Models\Receita;
use App\Services\MovimentoStockService;
use App\Services\PaperInvoice\ProdutosDaFatura;
use App\Services\RateioCustosService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DespesaManagementController extends Controller
{
    public const CATEGORIAS = [
        'combustivel',
        'sementes',
        'fertilizantes',
        'fitofarmaceuticos',
        'equipamento',
        'pecas',
        'mao_obra',
        'outro',
    ];

    public const TAXAS_IVA = [0, 6, 13, 23];

    /** Tipos de Custo aceites nos custos partilhados da exploracao. */
    public const TIPOS_CUSTO_PARTILHADO = [
        'energia',
        'material',
        'mao_obra',
        'maquinaria',
        'manutencao',
        'outro',
    ];

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Despesa::class);

        $filters = $request->only(['search', 'categoria', 'mes', 'ano']);
        $mes = (int) ($filters['mes'] ?? now()->month);
        $ano = (int) ($filters['ano'] ?? now()->year);
        $campanhaIds = $this->activeCampaignIds($request);

        $despesas = Despesa::query()
            ->with(['items:id,despesa_id,descricao,quantidade,preco_unitario,desconto_percentagem,iva_percentagem,produto_id,notas'])
            ->when($campanhaIds, fn ($q) => $q->whereIn('campanha_id', $campanhaIds))
            ->when($filters['search'] ?? null, fn ($q, $s) => $q->where(function ($sub) use ($s) {
                $sub->where('titulo', 'like', "%{$s}%")
                    ->orWhere('fornecedor', 'like', "%{$s}%")
                    ->orWhere('numero_fatura', 'like', "%{$s}%")
                    ->orWhereHas('items', fn ($qi) => $qi->where('descricao', 'like', "%{$s}%"));
            }))
            ->when($filters['categoria'] ?? null, fn ($q, $cat) => $q->where('categoria', $cat))
            ->whereYear('data', $ano)
            ->whereMonth('data', $mes)
            ->orderByDesc('data')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Despesa $d) => $this->formatDespesa($d));

        $mesAnt = $mes === 1 ? 12 : $mes - 1;
        $anoAnt = $mes === 1 ? $ano - 1 : $ano;

        return Inertia::render('Despesas/Index', [
            'despesas'          => $despesas,
            'vendas'            => $this->vendasMes($mes, $ano, $campanhaIds),
            'filters'           => array_merge($filters, ['mes' => $mes, 'ano' => $ano]),
            'categorias'        => self::CATEGORIAS,
            'taxasIva'          => self::TAXAS_IVA,
            'tiposVenda'        => ['venda_colheita', 'subsidio', 'servico', 'outro'],
            'resumoMes'         => $this->buildResumoMes($mes, $ano, $campanhaIds),
            'resumoMesAnterior' => $this->buildResumoMes($mesAnt, $anoAnt, $campanhaIds),
            'resumoVendas'      => $this->buildResumoVendas($mes, $ano, $campanhaIds),
            'analytics'         => $this->buildAnalytics($mes, $ano, $campanhaIds),
            'produtos'          => Produto::query()->orderBy('nome')->get(['id', 'nome', 'tipo', 'unidade_medida', 'conteudo', 'custo_unitario']),
            'lotes'             => $this->lotesDisponiveis($campanhaIds),
            'partilhados'       => $this->custosPartilhadosMes($mes, $ano),
            'resumoPartilhados' => $this->buildResumoPartilhados($mes, $ano),
            'tiposCustoPartilhado' => self::TIPOS_CUSTO_PARTILHADO,
            'basesRateio'       => Custo::BASES_RATEIO,
            'can' => [
                'create' => $request->user()->can('create', Despesa::class),
                'update' => $request->user()->can('update', new Despesa()),
                'delete' => $request->user()->can('delete', new Despesa()),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Despesa::class);

        $validated = $this->validateDespesa($request, true);
        $validated['campanha_id'] = $this->activeCampaignIdForWrite($request);
        $items = $validated['items'] ?? [];
        unset($validated['ficheiro'], $validated['items']);

        if ($request->hasFile('ficheiro')) {
            $validated['ficheiro_path'] = $request->file('ficheiro')->store('despesas', 'public');
        }

        $valorCalculado = $this->calcularTotalItems($items);
        if ($valorCalculado > 0) {
            $validated['valor'] = $valorCalculado;
        }

        $despesa = DB::transaction(function () use ($validated, $items) {
            $despesa = Despesa::create($validated);
            foreach ($items as $item) {
                $despesa->items()->create($item);
            }

            return $despesa;
        });

        $despesa->load(['items.produto']);
        $catalogo = app(ProdutosDaFatura::class)->garantir($despesa);
        $movimentos = $this->processarMovimentosStock($despesa);

        $msg = 'Despesa registada com sucesso.'.$this->resumoDoStock($catalogo, $movimentos);

        return redirect()
            ->route('app.despesas.index', $request->only(['mes', 'ano']))
            ->with('success', $msg);
    }

    public function storeReceita(Request $request): RedirectResponse
    {
        $this->authorize('create', Despesa::class);

        $data = $request->validate([
            'descricao' => ['required', 'string', 'max:255'],
            'tipo' => ['required', 'string', 'in:venda_colheita,subsidio,servico,outro'],
            'valor' => ['nullable', 'numeric', 'gt:0'],
            'quantidade' => ['nullable', 'numeric', 'gt:0'],
            'unidade' => ['nullable', 'string', 'max:20'],
            'preco_unitario' => ['nullable', 'numeric', 'gt:0'],
            'data' => ['required', 'date'],
            'comprador_nome' => ['nullable', 'string', 'max:255'],
            'documento' => ['nullable', 'string', 'max:255'],
            'lote_id' => ['nullable', 'integer', 'exists:lotes,id'],
            'observacoes' => ['nullable', 'string'],
        ]);

        // Quilos vezes preco chega para saber o valor: quem vende fruta sabe o
        // preco a que a vendeu, nao o total da guia.
        $quantidade = (float) ($data['quantidade'] ?? 0);
        $preco = (float) ($data['preco_unitario'] ?? 0);

        if (empty($data['valor']) && $quantidade > 0 && $preco > 0) {
            $data['valor'] = round($quantidade * $preco, 2);
        }

        if (empty($data['valor'])) {
            throw ValidationException::withMessages([
                'valor' => 'Indique o valor da venda, ou a quantidade e o preco por unidade.',
            ]);
        }

        if (empty($data['preco_unitario']) && $quantidade > 0) {
            $data['preco_unitario'] = round((float) $data['valor'] / $quantidade, 4);
        }

        if ($quantidade > 0 && empty($data['unidade'])) {
            $data['unidade'] = 'kg';
        }

        $data['campanha_id'] = $this->activeCampaignIdForWrite($request);

        // A venda de um lote pertence a campanha desse lote, nao a campanha activa.
        if (! empty($data['lote_id'])) {
            $lote = Lote::query()->with('colheita:id,campanha_id,parcela_id,cultura_id')->find($data['lote_id']);

            if ($lote?->colheita) {
                $data['colheita_id'] = $lote->colheita->id;
                $data['campanha_id'] = $lote->colheita->campanha_id ?: $data['campanha_id'];
                $data['parcela_id'] = $lote->colheita->parcela_id;
                $data['cultura_id'] = $lote->colheita->cultura_id;
            }
        }

        Receita::query()->create($data);

        return redirect()
            ->route('app.despesas.index', $request->only(['mes', 'ano']))
            ->with('success', 'Venda registada com sucesso.');
    }

    public function destroyReceita(Request $request, Receita $receita): RedirectResponse
    {
        $this->authorize('delete', new Despesa());

        $receita->delete();

        return redirect()
            ->route('app.despesas.index', $request->only(['mes', 'ano']))
            ->with('success', 'Venda eliminada com sucesso.');
    }

    public function update(Request $request, Despesa $despesa): RedirectResponse
    {
        $this->authorize('update', $despesa);

        $validated = $this->validateDespesa($request, false);
        $items = $validated['items'] ?? [];
        unset($validated['ficheiro'], $validated['items']);

        if ($request->hasFile('ficheiro')) {
            if ($despesa->ficheiro_path && Storage::disk('public')->exists($despesa->ficheiro_path)) {
                Storage::disk('public')->delete($despesa->ficheiro_path);
            }
            $validated['ficheiro_path'] = $request->file('ficheiro')->store('despesas', 'public');
        }

        $valorCalculado = $this->calcularTotalItems($items);
        if ($valorCalculado > 0) {
            $validated['valor'] = $valorCalculado;
        }

        // Reverter movimentos anteriores antes de aplicar novos
        $this->reverterMovimentosAnteriores($despesa);

        DB::transaction(function () use ($despesa, $validated, $items) {
            $despesa->update($validated);
            $despesa->items()->delete();
            foreach ($items as $item) {
                $despesa->items()->create($item);
            }
        });

        $despesa->load(['items.produto']);
        $catalogo = app(ProdutosDaFatura::class)->garantir($despesa);
        $movimentos = $this->processarMovimentosStock($despesa);

        $msg = 'Despesa atualizada com sucesso.'.$this->resumoDoStock($catalogo, $movimentos);

        return redirect()
            ->route('app.despesas.index', $request->only(['mes', 'ano']))
            ->with('success', $msg);
    }

    public function destroy(Despesa $despesa): RedirectResponse
    {
        $this->authorize('delete', $despesa);

        $this->reverterMovimentosAnteriores($despesa);

        if ($despesa->ficheiro_path && Storage::disk('public')->exists($despesa->ficheiro_path)) {
            Storage::disk('public')->delete($despesa->ficheiro_path);
        }

        $despesa->delete();

        return back()->with('success', 'Despesa eliminada e movimentos de stock revertidos.');
    }

    public function exportarResumoMensal(Request $request)
    {
        $this->authorize('viewAny', Despesa::class);

        $mes    = (int) ($request->query('mes', now()->month));
        $ano    = (int) ($request->query('ano', now()->year));
        $campanhaIds = $this->activeCampaignIds($request);
        $resumo    = $this->buildResumoMes($mes, $ano, $campanhaIds);
        $analytics = $this->buildAnalytics($mes, $ano, $campanhaIds);
        $resumoVendas = $this->buildResumoVendas($mes, $ano, $campanhaIds);

        $despesas = Despesa::query()
            ->with('items')
            ->when($campanhaIds, fn ($q) => $q->whereIn('campanha_id', $campanhaIds))
            ->whereYear('data', $ano)
            ->whereMonth('data', $mes)
            ->orderBy('data')
            ->get()
            ->map(fn (Despesa $d) => [
                'titulo'        => $d->titulo,
                'fornecedor'    => $d->fornecedor ?? '-',
                'numero_fatura' => $d->numero_fatura ?? '-',
                'categoria'     => $d->categoria,
                'valor'         => $d->total_fatura,
                'subtotal'      => $d->subtotal_calculado,
                'iva'           => $d->iva_calculado,
                'data'          => $d->data?->format('d/m/Y'),
                'items'         => $d->items->map(fn (FaturaItem $i) => [
                    'descricao'       => $i->descricao,
                    'quantidade'      => (float) $i->quantidade,
                    'preco_unitario'  => (float) $i->preco_unitario,
                    'desconto_percentagem' => (float) $i->desconto_percentagem,
                    'iva_percentagem' => (float) $i->iva_percentagem,
                    'total_sem_iva'   => $i->total_sem_iva,
                    'total_iva_valor' => $i->total_iva_valor,
                    'total_com_iva'   => $i->total_com_iva,
                ])->all(),
            ]);

        $nomeMes = \Carbon\Carbon::create($ano, $mes, 1)->translatedFormat('F Y');

        return view('despesas.resumo_mensal', compact('resumo', 'analytics', 'resumoVendas', 'despesas', 'nomeMes', 'mes', 'ano'));
    }

    public function exportarCsv(Request $request)
    {
        $this->authorize('viewAny', Despesa::class);

        $mes = (int) ($request->query('mes', now()->month));
        $ano = (int) ($request->query('ano', now()->year));
        $campanhaIds = $this->activeCampaignIds($request);

        $despesas = Despesa::query()
            ->with('items')
            ->when($campanhaIds, fn ($q) => $q->whereIn('campanha_id', $campanhaIds))
            ->whereYear('data', $ano)
            ->whereMonth('data', $mes)
            ->orderBy('data')
            ->get();
        $vendas = $this->vendasMes($mes, $ano, $campanhaIds);

        $nomeMes  = \Carbon\Carbon::create($ano, $mes, 1)->format('Y-m');
        $filename = "despesas-{$nomeMes}.csv";

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($despesas, $vendas) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($handle, ['Data', 'Título', 'Fornecedor', 'Nº Fatura', 'Categoria', 'Subtotal s/ IVA', 'IVA', 'Total c/ IVA', 'Notas'], ';');

            foreach ($despesas as $d) {
                fputcsv($handle, [
                    $d->data?->format('d/m/Y'),
                    $d->titulo,
                    $d->fornecedor ?? '',
                    $d->numero_fatura ?? '',
                    $d->categoria,
                    number_format($d->subtotal_calculado, 2, ',', '.'),
                    number_format($d->iva_calculado, 2, ',', '.'),
                    number_format($d->total_fatura, 2, ',', '.'),
                    $d->notas ?? '',
                ], ';');

                if ($d->items->isNotEmpty()) {
                    fputcsv($handle, ['', '  ↳ Descrição', '', 'Qtd', 'Preço Unit.', 'Desc. %', 'IVA %', 'Total s/ IVA', 'IVA', 'Total c/ IVA', ''], ';');
                    foreach ($d->items as $i) {
                        fputcsv($handle, [
                            '',
                            '  ' . $i->descricao,
                            '',
                            number_format((float) $i->quantidade, 3, ',', '.'),
                            number_format((float) $i->preco_unitario, 4, ',', '.'),
                            number_format((float) $i->desconto_percentagem, 2, ',', '.'),
                            number_format((float) $i->iva_percentagem, 0),
                            number_format($i->total_sem_iva, 2, ',', '.'),
                            number_format($i->total_iva_valor, 2, ',', '.'),
                            number_format($i->total_com_iva, 2, ',', '.'),
                            '',
                        ], ';');
                    }
                }
            }

            if (! empty($vendas)) {
                fputcsv($handle, [], ';');
                fputcsv($handle, ['Vendas'], ';');
                fputcsv($handle, ['Data', 'Descricao', 'Tipo', 'Comprador', 'Documento', 'Valor'], ';');

                foreach ($vendas as $venda) {
                    fputcsv($handle, [
                        $venda['data'] ? \Carbon\Carbon::parse($venda['data'])->format('d/m/Y') : '',
                        $venda['descricao'],
                        $venda['tipo'],
                        $venda['comprador_nome'] ?? '',
                        $venda['documento'] ?? '',
                        number_format($venda['valor'], 2, ',', '.'),
                    ], ';');
                }
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    // ── Stock integration ────────────────────────────────────────────────────

    private function processarMovimentosStock(Despesa $despesa): array
    {
        return app(MovimentoStockService::class)->processarEntradas($despesa);
    }

    /**
     * Diz em texto o que entrou em stock, com as quantidades ja convertidas
     * (dois bidoes de 5 L sao 10 L), e o que ficou de fora e porque.
     *
     * @param  array{ligados: int, criados: array, avisos: array}  $catalogo
     */
    private function resumoDoStock(array $catalogo, array $movimentos): string
    {
        $partes = [];

        if ($catalogo['criados'] !== []) {
            $partes[] = 'Produtos criados: '.implode(', ', $catalogo['criados']).'.';
        }

        if ($movimentos !== []) {
            $entradas = collect($movimentos)
                ->map(fn (array $m) => "{$m['produto']} +".rtrim(rtrim(number_format($m['quantidade'], 2, ',', ''), '0'), ',')." {$m['unidade']}")
                ->implode('; ');

            $partes[] = "Stock: {$entradas}.";
        }

        foreach ($catalogo['avisos'] as $aviso) {
            $partes[] = $aviso;
        }

        return $partes === [] ? '' : ' '.implode(' ', $partes);
    }

    private function reverterMovimentosAnteriores(Despesa $despesa): void
    {
        app(MovimentoStockService::class)->reverterEntradas($despesa);
    }

    // ── Validation & formatting ──────────────────────────────────────────────

    private function validateDespesa(Request $request, bool $isStore): array
    {
        return $request->validate([
            'titulo'        => ['required', 'string', 'max:255'],
            'numero_fatura' => ['nullable', 'string', 'max:100'],
            'fornecedor'    => ['nullable', 'string', 'max:255'],
            'valor'         => [
                'nullable',
                'numeric',
                'min:0',
                Rule::requiredIf(fn () => empty($request->input('items'))),
            ],
            'data'      => ['required', 'date'],
            'categoria' => ['required', 'string', 'in:' . implode(',', self::CATEGORIAS)],
            'notas'     => ['nullable', 'string'],
            'ficheiro'  => ['nullable', 'file', 'mimes:jpeg,jpg,png,webp,pdf', 'max:20480'],
            'items'     => ['nullable', 'array'],
            'items.*.descricao'       => ['required', 'string', 'max:255'],
            'items.*.quantidade'      => ['required', 'numeric', 'min:0.001'],
            'items.*.preco_unitario'  => ['required', 'numeric', 'min:0'],
            'items.*.desconto_percentagem' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.iva_percentagem' => ['required', 'numeric', 'in:0,6,13,23'],
            'items.*.produto_id'      => ['nullable', 'integer', 'exists:produtos,id'],
            'items.*.notas'           => ['nullable', 'string', 'max:500'],
        ]);
    }

    private function formatDespesa(Despesa $d): array
    {
        return [
            'id'             => $d->id,
            'titulo'         => $d->titulo,
            'numero_fatura'  => $d->numero_fatura,
            'fornecedor'     => $d->fornecedor,
            'valor'          => (float) $d->valor,
            'data'           => $d->data?->format('Y-m-d'),
            'categoria'      => $d->categoria,
            'ficheiro_path'  => $d->ficheiro_path,
            'ficheiro_url'   => $d->ficheiro_path ? Storage::disk('public')->url($d->ficheiro_path) : null,
            'notas'          => $d->notas,
            'items'          => $d->items->map(fn (FaturaItem $i) => [
                'id'              => $i->id,
                'descricao'       => $i->descricao,
                'quantidade'      => (float) $i->quantidade,
                'preco_unitario'  => (float) $i->preco_unitario,
                'desconto_percentagem' => (float) $i->desconto_percentagem,
                'preco_liquido'   => $i->preco_liquido,
                'iva_percentagem' => (float) $i->iva_percentagem,
                'produto_id'      => $i->produto_id,
                'notas'           => $i->notas ?? '',
                'total_sem_iva'   => $i->total_sem_iva,
                'total_iva_valor' => $i->total_iva_valor,
                'total_com_iva'   => $i->total_com_iva,
            ])->values()->all(),
            'tem_items'          => $d->items->isNotEmpty(),
            'subtotal_calculado' => $d->subtotal_calculado,
            'iva_calculado'      => $d->iva_calculado,
            'total_fatura'       => $d->total_fatura,
        ];
    }

    private function calcularTotalItems(array $items): float
    {
        return array_reduce($items, function (float $carry, array $item) {
            $bruto = (float) ($item['quantidade'] ?? 0) * (float) ($item['preco_unitario'] ?? 0);
            // O desconto entra antes do IVA, como na fatura.
            $base = $bruto * (1 - (float) ($item['desconto_percentagem'] ?? 0) / 100);
            $iva  = $base * (float) ($item['iva_percentagem'] ?? 0) / 100;

            return $carry + round($base + $iva, 2);
        }, 0.0);
    }

    // ── Analytics ────────────────────────────────────────────────────────────

    private function buildResumoMes(int $mes, int $ano, array $campanhaIds = []): array
    {
        $despesas = Despesa::query()
            ->when($campanhaIds, fn ($q) => $q->whereIn('campanha_id', $campanhaIds))
            ->whereYear('data', $ano)
            ->whereMonth('data', $mes)
            ->get(['valor', 'categoria']);

        $total = (float) $despesas->sum('valor');

        $porCategoria = collect(self::CATEGORIAS)->mapWithKeys(fn ($cat) => [
            $cat => (float) $despesas->where('categoria', $cat)->sum('valor'),
        ])->all();

        return [
            'mes'           => $mes,
            'ano'           => $ano,
            'total'         => $total,
            'total_grupo'   => $total,
            'por_categoria' => $porCategoria,
            'count'         => $despesas->count(),
        ];
    }

    private function buildAnalytics(int $mes, int $ano, array $campanhaIds = []): array
    {
        $empty = [
            'tem_items'      => false,
            'iva_total'      => 0,
            'subtotal'       => 0,
            'por_fornecedor' => [],
            'top_descricoes' => [],
        ];

        if (! Schema::hasTable('fatura_items')) {
            return $empty;
        }

        $despesaIds = Despesa::query()
            ->when($campanhaIds, fn ($q) => $q->whereIn('campanha_id', $campanhaIds))
            ->whereYear('data', $ano)
            ->whereMonth('data', $mes)
            ->pluck('id');

        if ($despesaIds->isEmpty()) {
            return $empty;
        }

        $items = FaturaItem::query()
            ->whereIn('despesa_id', $despesaIds)
            ->with('despesa:id,fornecedor')
            ->get();

        if ($items->isEmpty()) {
            return $empty;
        }

        $ivaTotal = round($items->sum(fn ($i) => $i->total_iva_valor), 2);
        $subtotal  = round($items->sum(fn ($i) => $i->total_sem_iva), 2);

        $porFornecedor = $items
            ->groupBy(fn ($i) => $i->despesa?->fornecedor ?? 'Desconhecido')
            ->map(fn ($group, $fornecedor) => [
                'fornecedor' => $fornecedor,
                'total'      => round((float) $group->sum(fn ($i) => $i->total_com_iva), 2),
                'count'      => $group->count(),
            ])
            ->sortByDesc('total')
            ->values()
            ->take(8)
            ->all();

        $topDescricoes = $items
            ->groupBy('descricao')
            ->map(fn ($group) => [
                'descricao' => $group->first()->descricao,
                'count'     => $group->count(),
                'total'     => round((float) $group->sum(fn ($i) => $i->total_com_iva), 2),
            ])
            ->sortByDesc('count')
            ->values()
            ->take(10)
            ->all();

        return [
            'tem_items'      => true,
            'iva_total'      => $ivaTotal,
            'subtotal'       => $subtotal,
            'por_fornecedor' => $porFornecedor,
            'top_descricoes' => $topDescricoes,
        ];
    }

    private function buildResumoVendas(int $mes, int $ano, array $campanhaIds = []): array
    {
        $vendas = Receita::query()
            ->when($campanhaIds, fn ($q) => $q->whereIn('campanha_id', $campanhaIds))
            ->whereYear('data', $ano)
            ->whereMonth('data', $mes)
            ->get(['valor', 'tipo', 'quantidade']);

        $kg = (float) $vendas->whereNotNull('quantidade')->sum('quantidade');
        $valorComKg = (float) $vendas->whereNotNull('quantidade')->sum('valor');

        return [
            'total' => round((float) $vendas->sum('valor'), 2),
            'count' => $vendas->count(),
            'quantidade' => round($kg, 2),
            'preco_medio' => $kg > 0 ? round($valorComKg / $kg, 4) : 0,
            'por_tipo' => collect(['venda_colheita', 'subsidio', 'servico', 'outro'])
                ->mapWithKeys(fn ($tipo) => [$tipo => round((float) $vendas->where('tipo', $tipo)->sum('valor'), 2)])
                ->all(),
        ];
    }

    private function vendasMes(int $mes, int $ano, array $campanhaIds = []): array
    {
        return Receita::query()
            ->when($campanhaIds, fn ($q) => $q->whereIn('campanha_id', $campanhaIds))
            ->whereYear('data', $ano)
            ->whereMonth('data', $mes)
            ->orderByDesc('data')
            ->orderByDesc('id')
            ->with('lote:id,numero_lote')
            ->get()
            ->map(fn (Receita $receita) => [
                'id' => $receita->id,
                'descricao' => $receita->descricao,
                'tipo' => $receita->tipo,
                'valor' => (float) $receita->valor,
                'quantidade' => $receita->quantidade !== null ? (float) $receita->quantidade : null,
                'unidade' => $receita->unidade,
                'preco_unitario' => $receita->preco_efetivo,
                'lote' => $receita->lote?->numero_lote,
                'data' => $receita->data?->format('Y-m-d'),
                'comprador_nome' => $receita->comprador_nome,
                'documento' => $receita->documento,
                'observacoes' => $receita->observacoes,
            ])
            ->all();
    }

    /** Lotes que ainda podem ser vendidos, para ligar a venda a colheita. */
    private function lotesDisponiveis(array $campanhaIds = []): array
    {
        return Lote::query()
            ->with('colheita:id,campanha_id')
            ->when($campanhaIds, fn ($q) => $q->whereHas(
                'colheita',
                fn ($sub) => $sub->whereIn('campanha_id', $campanhaIds)
            ))
            ->orderByDesc('data_colheita')
            ->orderByDesc('id')
            ->limit(200)
            ->get(['id', 'numero_lote', 'quantidade', 'unidade_medida', 'colheita_id', 'data_colheita', 'status'])
            ->map(fn (Lote $lote) => [
                'id' => $lote->id,
                'numero_lote' => $lote->numero_lote,
                'quantidade' => (float) $lote->quantidade,
                'unidade' => $lote->unidade_medida,
                'status' => $lote->status,
            ])
            ->all();
    }

    // -- Custos partilhados (luz das regas, frio, IMI, seguros) ---------------

    /**
     * Regista um gasto da exploracao que serve varias campanhas ao mesmo tempo.
     *
     * Fica sem campanha_id e marcado como rateavel: e o RateioCustosService
     * que decide, no momento de mostrar as contas, quanto dele pertence a cada
     * campanha - por omissao na proporcao dos quilos colhidos.
     */
    public function storePartilhado(Request $request): RedirectResponse
    {
        $this->authorize('create', Despesa::class);

        $data = $request->validate([
            'descricao' => ['required', 'string', 'max:255'],
            'tipo' => ['required', 'string', Rule::in(self::TIPOS_CUSTO_PARTILHADO)],
            'valor' => ['required', 'numeric', 'gt:0'],
            'data_custo' => ['required', 'date'],
            'base_rateio' => ['required', 'string', Rule::in(Custo::BASES_RATEIO)],
            'observacoes' => ['nullable', 'string'],
        ]);

        Custo::query()->create($data + [
            'rateavel' => true,
            'campanha_id' => null,
        ]);

        app(RateioCustosService::class)->esquecer();

        return redirect()
            ->route('app.despesas.index', $request->only(['mes', 'ano']))
            ->with('success', 'Custo partilhado registado. Vai ser repartido pelas campanhas do periodo.');
    }

    public function destroyPartilhado(Request $request, Custo $custo): RedirectResponse
    {
        $this->authorize('delete', new Despesa());

        abort_unless($custo->rateavel, 404);

        $custo->delete();

        app(RateioCustosService::class)->esquecer();

        return redirect()
            ->route('app.despesas.index', $request->only(['mes', 'ano']))
            ->with('success', 'Custo partilhado eliminado.');
    }

    /** @return array<int, array<string, mixed>> */
    private function custosPartilhadosMes(int $mes, int $ano): array
    {
        $rateio = app(RateioCustosService::class);

        return Custo::query()
            ->partilhados()
            ->whereYear('data_custo', $ano)
            ->whereMonth('data_custo', $mes)
            ->orderByDesc('data_custo')
            ->get()
            ->map(fn (Custo $custo) => [
                'id' => $custo->id,
                'descricao' => $custo->descricao,
                'tipo' => $custo->tipo,
                'valor' => (float) $custo->valor,
                'base_rateio' => $custo->base_rateio ?: 'kg',
                'data' => $custo->data_custo?->format('Y-m-d'),
                'observacoes' => $custo->observacoes,
                'campanhas' => $rateio->distribuicaoDe($custo),
            ])
            ->all();
    }

    private function buildResumoPartilhados(int $mes, int $ano): array
    {
        $custos = Custo::query()
            ->partilhados()
            ->whereYear('data_custo', $ano)
            ->whereMonth('data_custo', $mes)
            ->get(['valor', 'tipo']);

        return [
            'total' => round((float) $custos->sum('valor'), 2),
            'count' => $custos->count(),
        ];
    }

    private function activeCampaignIdForWrite(Request $request): ?int
    {
        $id = $request->session()->get('campanha_ativa_id');

        if ($id && Campanha::query()->whereKey($id)->exists()) {
            return (int) $id;
        }

        $activeYear = $this->activeCampaignYear($request);

        $defaultId = Campanha::query()
            ->when($activeYear, fn ($query) => $query->where('ano', $activeYear))
            ->orderByRaw("CASE WHEN status = 'em_curso' THEN 0 ELSE 1 END")
            ->orderByDesc('ano')
            ->orderByDesc('id')
            ->value('id');

        return $defaultId ? (int) $defaultId : null;
    }

    private function activeCampaignIds(Request $request): array
    {
        $activeYear = $this->activeCampaignYear($request);

        if (! $activeYear) {
            return [];
        }

        return Campanha::query()
            ->where('ano', $activeYear)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function activeCampaignYear(Request $request): ?int
    {
        $year = $request->session()->get('campanha_ativa_ano');

        if ($year && Campanha::query()->where('ano', $year)->exists()) {
            return (int) $year;
        }

        $id = $request->session()->get('campanha_ativa_id');
        $legacyYear = $id
            ? Campanha::query()->whereKey($id)->value('ano')
            : null;

        if ($legacyYear) {
            return (int) $legacyYear;
        }

        $defaultYear = Campanha::query()
            ->orderByRaw("CASE WHEN status = 'em_curso' THEN 0 ELSE 1 END")
            ->orderByDesc('ano')
            ->orderByDesc('id')
            ->value('ano');

        return $defaultYear ? (int) $defaultYear : null;
    }
}
