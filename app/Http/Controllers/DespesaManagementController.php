<?php

namespace App\Http\Controllers;

use App\Models\Campanha;
use App\Models\Despesa;
use App\Models\FaturaItem;
use App\Models\MovimentoStock;
use App\Models\Produto;
use App\Models\Receita;
use App\Models\Stock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
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
        'mao_obra',
        'outro',
    ];

    public const TAXAS_IVA = [0, 6, 13, 23];

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Despesa::class);

        $filters = $request->only(['search', 'categoria', 'mes', 'ano']);
        $mes = (int) ($filters['mes'] ?? now()->month);
        $ano = (int) ($filters['ano'] ?? now()->year);
        $campanhaId = $this->activeCampaignId($request);

        $despesas = Despesa::query()
            ->with(['items:id,despesa_id,descricao,quantidade,preco_unitario,iva_percentagem,produto_id,notas'])
            ->when($campanhaId, fn ($q) => $q->where('campanha_id', $campanhaId))
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
            'vendas'            => $this->vendasMes($mes, $ano, $campanhaId),
            'filters'           => array_merge($filters, ['mes' => $mes, 'ano' => $ano]),
            'categorias'        => self::CATEGORIAS,
            'taxasIva'          => self::TAXAS_IVA,
            'tiposVenda'        => ['venda_colheita', 'subsidio', 'servico', 'outro'],
            'resumoMes'         => $this->buildResumoMes($mes, $ano, $campanhaId),
            'resumoMesAnterior' => $this->buildResumoMes($mesAnt, $anoAnt, $campanhaId),
            'resumoVendas'      => $this->buildResumoVendas($mes, $ano, $campanhaId),
            'analytics'         => $this->buildAnalytics($mes, $ano, $campanhaId),
            'produtos'          => Produto::query()->orderBy('nome')->get(['id', 'nome', 'tipo', 'unidade_medida', 'custo_unitario']),
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
        $validated['campanha_id'] = $this->activeCampaignId($request);
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
        $movimentos = $this->processarMovimentosStock($despesa);

        $msg = 'Despesa registada com sucesso.';
        if (count($movimentos) > 0) {
            $msg .= ' ' . count($movimentos) . ' produto(s) adicionado(s) ao stock automaticamente.';
        }

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
            'valor' => ['required', 'numeric', 'gt:0'],
            'data' => ['required', 'date'],
            'comprador_nome' => ['nullable', 'string', 'max:255'],
            'documento' => ['nullable', 'string', 'max:255'],
            'observacoes' => ['nullable', 'string'],
        ]);

        $data['campanha_id'] = $this->activeCampaignId($request);

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
        $movimentos = $this->processarMovimentosStock($despesa);

        $msg = 'Despesa atualizada com sucesso.';
        if (count($movimentos) > 0) {
            $msg .= ' Stock actualizado para ' . count($movimentos) . ' produto(s).';
        }

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
        $campanhaId = $this->activeCampaignId($request);
        $resumo    = $this->buildResumoMes($mes, $ano, $campanhaId);
        $analytics = $this->buildAnalytics($mes, $ano, $campanhaId);
        $resumoVendas = $this->buildResumoVendas($mes, $ano, $campanhaId);

        $despesas = Despesa::query()
            ->with('items')
            ->when($campanhaId, fn ($q) => $q->where('campanha_id', $campanhaId))
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
        $campanhaId = $this->activeCampaignId($request);

        $despesas = Despesa::query()
            ->with('items')
            ->when($campanhaId, fn ($q) => $q->where('campanha_id', $campanhaId))
            ->whereYear('data', $ano)
            ->whereMonth('data', $mes)
            ->orderBy('data')
            ->get();
        $vendas = $this->vendasMes($mes, $ano, $campanhaId);

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
                    fputcsv($handle, ['', '  ↳ Descrição', '', 'Qtd', 'Preço Unit.', 'IVA %', 'Total s/ IVA', 'IVA', 'Total c/ IVA', ''], ';');
                    foreach ($d->items as $i) {
                        fputcsv($handle, [
                            '',
                            '  ' . $i->descricao,
                            '',
                            number_format((float) $i->quantidade, 3, ',', '.'),
                            number_format((float) $i->preco_unitario, 4, ',', '.'),
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
        if (! Schema::hasTable('movimento_stocks') || ! Schema::hasTable('stocks')) {
            return [];
        }

        $itemsComProduto = $despesa->items->filter(fn ($i) => $i->produto_id !== null);

        if ($itemsComProduto->isEmpty()) {
            return [];
        }

        $movimentos = [];
        $referencia = $this->buildReferencia($despesa);

        foreach ($itemsComProduto as $item) {
            $produto = $item->produto ?? Produto::find($item->produto_id);
            if (! $produto) {
                continue;
            }

            // Upsert stock record (armazem_id = null = stock geral)
            $stock = Stock::firstOrCreate(
                ['produto_id' => $item->produto_id, 'armazem_id' => null],
                [
                    'quantidade'      => 0,
                    'unidade_medida'  => $produto->unidade_medida ?? 'un',
                    'data_atualizado' => now()->toDateString(),
                ]
            );

            $stock->update([
                'quantidade'      => max(0, (float) $stock->quantidade + (float) $item->quantidade),
                'data_atualizado' => now()->toDateString(),
            ]);

            MovimentoStock::create([
                'produto_id'      => $item->produto_id,
                'tipo'            => 'entrada',
                'quantidade'      => (float) $item->quantidade,
                'unidade_medida'  => $produto->unidade_medida ?? 'un',
                'custo_unitario'  => (float) $item->preco_unitario,
                'referencia'      => $referencia,
                'despesa_id'      => $despesa->id,
                'fatura_item_id'  => $item->id,
                'notas'           => "Entrada automática via fatura: {$referencia}",
            ]);

            $movimentos[] = [
                'produto'    => $produto->nome,
                'quantidade' => (float) $item->quantidade,
                'unidade'    => $produto->unidade_medida ?? 'un',
            ];
        }

        return $movimentos;
    }

    private function reverterMovimentosAnteriores(Despesa $despesa): void
    {
        if (! Schema::hasTable('movimento_stocks')) {
            return;
        }

        $movimentos = MovimentoStock::where('despesa_id', $despesa->id)
            ->where('tipo', 'entrada')
            ->get();

        foreach ($movimentos as $mov) {
            $stock = Stock::where('produto_id', $mov->produto_id)
                ->whereNull('armazem_id')
                ->first();

            if ($stock) {
                $stock->update([
                    'quantidade'      => max(0, (float) $stock->quantidade - (float) $mov->quantidade),
                    'data_atualizado' => now()->toDateString(),
                ]);
            }

            $mov->delete();
        }
    }

    private function buildReferencia(Despesa $despesa): string
    {
        $parts = [];
        if ($despesa->numero_fatura) {
            $parts[] = "Fatura {$despesa->numero_fatura}";
        }
        if ($despesa->fornecedor) {
            $parts[] = "de {$despesa->fornecedor}";
        }

        return implode(' ', $parts) ?: "Fatura #{$despesa->id}";
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
            $base = (float) ($item['quantidade'] ?? 0) * (float) ($item['preco_unitario'] ?? 0);
            $iva  = $base * (float) ($item['iva_percentagem'] ?? 0) / 100;

            return $carry + round($base + $iva, 2);
        }, 0.0);
    }

    // ── Analytics ────────────────────────────────────────────────────────────

    private function buildResumoMes(int $mes, int $ano, ?int $campanhaId = null): array
    {
        $despesas = Despesa::query()
            ->when($campanhaId, fn ($q) => $q->where('campanha_id', $campanhaId))
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

    private function buildAnalytics(int $mes, int $ano, ?int $campanhaId = null): array
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
            ->when($campanhaId, fn ($q) => $q->where('campanha_id', $campanhaId))
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

    private function buildResumoVendas(int $mes, int $ano, ?int $campanhaId = null): array
    {
        $vendas = Receita::query()
            ->when($campanhaId, fn ($q) => $q->where('campanha_id', $campanhaId))
            ->whereYear('data', $ano)
            ->whereMonth('data', $mes)
            ->get(['valor', 'tipo']);

        return [
            'total' => round((float) $vendas->sum('valor'), 2),
            'count' => $vendas->count(),
            'por_tipo' => collect(['venda_colheita', 'subsidio', 'servico', 'outro'])
                ->mapWithKeys(fn ($tipo) => [$tipo => round((float) $vendas->where('tipo', $tipo)->sum('valor'), 2)])
                ->all(),
        ];
    }

    private function vendasMes(int $mes, int $ano, ?int $campanhaId = null): array
    {
        return Receita::query()
            ->when($campanhaId, fn ($q) => $q->where('campanha_id', $campanhaId))
            ->whereYear('data', $ano)
            ->whereMonth('data', $mes)
            ->orderByDesc('data')
            ->orderByDesc('id')
            ->get(['id', 'descricao', 'tipo', 'valor', 'data', 'comprador_nome', 'documento', 'observacoes'])
            ->map(fn (Receita $receita) => [
                'id' => $receita->id,
                'descricao' => $receita->descricao,
                'tipo' => $receita->tipo,
                'valor' => (float) $receita->valor,
                'data' => $receita->data?->format('Y-m-d'),
                'comprador_nome' => $receita->comprador_nome,
                'documento' => $receita->documento,
                'observacoes' => $receita->observacoes,
            ])
            ->all();
    }

    private function activeCampaignId(Request $request): ?int
    {
        $id = $request->session()->get('campanha_ativa_id');

        if ($id && Campanha::query()->whereKey($id)->exists()) {
            return (int) $id;
        }

        $defaultId = Campanha::query()
            ->orderByRaw("CASE WHEN status = 'em_curso' THEN 0 ELSE 1 END")
            ->orderByDesc('ano')
            ->orderByDesc('id')
            ->value('id');

        return $defaultId ? (int) $defaultId : null;
    }
}
