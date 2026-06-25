<?php

namespace App\Http\Controllers;

use App\Models\Despesa;
use App\Models\FaturaItem;
use App\Models\Produto;
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

        $despesas = Despesa::query()
            ->with(['items:id,despesa_id,descricao,quantidade,preco_unitario,iva_percentagem,produto_id,notas'])
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
            'despesas' => $despesas,
            'filters' => array_merge($filters, ['mes' => $mes, 'ano' => $ano]),
            'categorias' => self::CATEGORIAS,
            'taxasIva' => self::TAXAS_IVA,
            'resumoMes' => $this->buildResumoMes($mes, $ano),
            'resumoMesAnterior' => $this->buildResumoMes($mesAnt, $anoAnt),
            'analytics' => $this->buildAnalytics($mes, $ano),
            'produtos' => Produto::query()->orderBy('nome')->get(['id', 'nome', 'tipo', 'unidade_medida']),
            'can' => [
                'create' => $request->user()->can('create', Despesa::class),
                'update' => $request->user()->can('update', Despesa::class, new Despesa()),
                'delete' => $request->user()->can('delete', Despesa::class, new Despesa()),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Despesa::class);

        $validated = $this->validateDespesa($request, true);
        $items = $validated['items'] ?? [];
        unset($validated['ficheiro'], $validated['items']);

        if ($request->hasFile('ficheiro')) {
            $validated['ficheiro_path'] = $request->file('ficheiro')->store('despesas', 'public');
        }

        $valorCalculado = $this->calcularTotalItems($items);
        if ($valorCalculado > 0) {
            $validated['valor'] = $valorCalculado;
        }

        DB::transaction(function () use ($validated, $items) {
            $despesa = Despesa::create($validated);
            foreach ($items as $item) {
                $despesa->items()->create($item);
            }
        });

        return redirect()
            ->route('app.despesas.index', $request->only(['mes', 'ano']))
            ->with('success', 'Despesa registada com sucesso.');
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

        DB::transaction(function () use ($despesa, $validated, $items) {
            $despesa->update($validated);
            $despesa->items()->delete();
            foreach ($items as $item) {
                $despesa->items()->create($item);
            }
        });

        return redirect()
            ->route('app.despesas.index', $request->only(['mes', 'ano']))
            ->with('success', 'Despesa atualizada com sucesso.');
    }

    public function destroy(Despesa $despesa): RedirectResponse
    {
        $this->authorize('delete', $despesa);

        if ($despesa->ficheiro_path && Storage::disk('public')->exists($despesa->ficheiro_path)) {
            Storage::disk('public')->delete($despesa->ficheiro_path);
        }

        $despesa->delete();

        return back()->with('success', 'Despesa eliminada com sucesso.');
    }

    public function exportarResumoMensal(Request $request)
    {
        $this->authorize('viewAny', Despesa::class);

        $mes = (int) ($request->query('mes', now()->month));
        $ano = (int) ($request->query('ano', now()->year));

        $resumo = $this->buildResumoMes($mes, $ano);
        $analytics = $this->buildAnalytics($mes, $ano);

        $despesas = Despesa::query()
            ->with('items')
            ->whereYear('data', $ano)
            ->whereMonth('data', $mes)
            ->orderBy('data')
            ->get()
            ->map(fn (Despesa $d) => [
                'titulo' => $d->titulo,
                'fornecedor' => $d->fornecedor ?? '-',
                'numero_fatura' => $d->numero_fatura ?? '-',
                'categoria' => $d->categoria,
                'valor' => $d->total_fatura,
                'subtotal' => $d->subtotal_calculado,
                'iva' => $d->iva_calculado,
                'data' => $d->data?->format('d/m/Y'),
                'items' => $d->items->map(fn (FaturaItem $i) => [
                    'descricao' => $i->descricao,
                    'quantidade' => (float) $i->quantidade,
                    'preco_unitario' => (float) $i->preco_unitario,
                    'iva_percentagem' => (float) $i->iva_percentagem,
                    'total_sem_iva' => $i->total_sem_iva,
                    'total_iva_valor' => $i->total_iva_valor,
                    'total_com_iva' => $i->total_com_iva,
                ])->all(),
            ]);

        $nomeMes = \Carbon\Carbon::create($ano, $mes, 1)->translatedFormat('F Y');

        return view('despesas.resumo_mensal', compact('resumo', 'analytics', 'despesas', 'nomeMes', 'mes', 'ano'));
    }

    public function exportarCsv(Request $request)
    {
        $this->authorize('viewAny', Despesa::class);

        $mes = (int) ($request->query('mes', now()->month));
        $ano = (int) ($request->query('ano', now()->year));

        $despesas = Despesa::query()
            ->with('items')
            ->whereYear('data', $ano)
            ->whereMonth('data', $mes)
            ->orderBy('data')
            ->get();

        $nomeMes = \Carbon\Carbon::create($ano, $mes, 1)->format('Y-m');
        $filename = "despesas-{$nomeMes}.csv";

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($despesas) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // Cabeçalho — faturas
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

                // Linhas da fatura
                if ($d->items->isNotEmpty()) {
                    fputcsv($handle, ['', '  ↳ Descrição', 'Qtd', 'Preço Unit.', 'IVA %', 'Total s/ IVA', 'IVA', 'Total c/ IVA', ''], ';');
                    foreach ($d->items as $i) {
                        fputcsv($handle, [
                            '',
                            '  ' . $i->descricao,
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

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function validateDespesa(Request $request, bool $isStore): array
    {
        return $request->validate([
            'titulo' => ['required', 'string', 'max:255'],
            'numero_fatura' => ['nullable', 'string', 'max:100'],
            'fornecedor' => ['nullable', 'string', 'max:255'],
            'valor' => [
                'nullable',
                'numeric',
                'min:0',
                Rule::requiredIf(fn () => empty($request->input('items'))),
            ],
            'data' => ['required', 'date'],
            'categoria' => ['required', 'string', 'in:' . implode(',', self::CATEGORIAS)],
            'notas' => ['nullable', 'string'],
            'ficheiro' => ['nullable', 'file', 'mimes:jpeg,jpg,png,webp,pdf', 'max:20480'],
            'items' => ['nullable', 'array'],
            'items.*.descricao' => ['required', 'string', 'max:255'],
            'items.*.quantidade' => ['required', 'numeric', 'min:0.001'],
            'items.*.preco_unitario' => ['required', 'numeric', 'min:0'],
            'items.*.iva_percentagem' => ['required', 'numeric', 'in:0,6,13,23'],
            'items.*.produto_id' => ['nullable', 'integer', 'exists:produtos,id'],
            'items.*.notas' => ['nullable', 'string', 'max:500'],
        ]);
    }

    private function formatDespesa(Despesa $d): array
    {
        return [
            'id' => $d->id,
            'titulo' => $d->titulo,
            'numero_fatura' => $d->numero_fatura,
            'fornecedor' => $d->fornecedor,
            'valor' => (float) $d->valor,
            'data' => $d->data?->format('Y-m-d'),
            'categoria' => $d->categoria,
            'ficheiro_path' => $d->ficheiro_path,
            'ficheiro_url' => $d->ficheiro_path ? Storage::disk('public')->url($d->ficheiro_path) : null,
            'notas' => $d->notas,
            'items' => $d->items->map(fn (FaturaItem $i) => [
                'id' => $i->id,
                'descricao' => $i->descricao,
                'quantidade' => (float) $i->quantidade,
                'preco_unitario' => (float) $i->preco_unitario,
                'iva_percentagem' => (float) $i->iva_percentagem,
                'produto_id' => $i->produto_id,
                'notas' => $i->notas ?? '',
                'total_sem_iva' => $i->total_sem_iva,
                'total_iva_valor' => $i->total_iva_valor,
                'total_com_iva' => $i->total_com_iva,
            ])->values()->all(),
            'tem_items' => $d->items->isNotEmpty(),
            'subtotal_calculado' => $d->subtotal_calculado,
            'iva_calculado' => $d->iva_calculado,
            'total_fatura' => $d->total_fatura,
        ];
    }

    private function calcularTotalItems(array $items): float
    {
        return array_reduce($items, function (float $carry, array $item) {
            $base = (float) ($item['quantidade'] ?? 0) * (float) ($item['preco_unitario'] ?? 0);
            $iva = $base * (float) ($item['iva_percentagem'] ?? 0) / 100;

            return $carry + round($base + $iva, 2);
        }, 0.0);
    }

    private function buildResumoMes(int $mes, int $ano): array
    {
        $despesas = Despesa::query()
            ->whereYear('data', $ano)
            ->whereMonth('data', $mes)
            ->get(['valor', 'categoria']);

        $total = (float) $despesas->sum('valor');

        $porCategoria = collect(self::CATEGORIAS)->mapWithKeys(fn ($cat) => [
            $cat => (float) $despesas->where('categoria', $cat)->sum('valor'),
        ])->all();

        return [
            'mes' => $mes,
            'ano' => $ano,
            'total' => $total,
            'por_categoria' => $porCategoria,
            'count' => $despesas->count(),
        ];
    }

    private function buildAnalytics(int $mes, int $ano): array
    {
        $empty = ['tem_items' => false, 'iva_total' => 0, 'subtotal' => 0, 'por_fornecedor' => [], 'top_descricoes' => []];

        if (! Schema::hasTable('fatura_items')) {
            return $empty;
        }

        $despesaIds = Despesa::query()
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
        $subtotal = round($items->sum(fn ($i) => $i->total_sem_iva), 2);

        $porFornecedor = $items
            ->groupBy(fn ($i) => $i->despesa?->fornecedor ?? 'Desconhecido')
            ->map(fn ($group, $fornecedor) => [
                'fornecedor' => $fornecedor,
                'total' => round((float) $group->sum(fn ($i) => $i->total_com_iva), 2),
                'count' => $group->count(),
            ])
            ->sortByDesc('total')
            ->values()
            ->take(8)
            ->all();

        $topDescricoes = $items
            ->groupBy('descricao')
            ->map(fn ($group) => [
                'descricao' => $group->first()->descricao,
                'count' => $group->count(),
                'total' => round((float) $group->sum(fn ($i) => $i->total_com_iva), 2),
            ])
            ->sortByDesc('count')
            ->values()
            ->take(10)
            ->all();

        return [
            'tem_items' => true,
            'iva_total' => $ivaTotal,
            'subtotal' => $subtotal,
            'por_fornecedor' => $porFornecedor,
            'top_descricoes' => $topDescricoes,
        ];
    }
}
