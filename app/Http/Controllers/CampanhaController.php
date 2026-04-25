<?php

namespace App\Http\Controllers;

use App\Models\Campanha;
use App\Models\Cultura;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CampanhaController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Campanha::class);

        $filters = $request->only(['search', 'status', 'ano', 'cultura_id']);

        $campanhas = Campanha::query()
            ->with([
                'cultura:id,nome',
                'colheitas:id,campanha_id,quantidade_total',
                'custos:id,campanha_id,valor',
                'operacoes' => fn ($query) => $query
                    ->select('id', 'campanha_id', 'custo_real')
                    ->with('produtos:id,nome'),
            ])
            ->withCount(['operacoes', 'colheitas'])
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery
                        ->where('ano', 'like', "%{$search}%")
                        ->orWhereHas('cultura', fn ($culturaQuery) => $culturaQuery->where('nome', 'like', "%{$search}%"));
                });
            })
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['ano'] ?? null, fn ($query, $ano) => $query->where('ano', $ano))
            ->when($filters['cultura_id'] ?? null, fn ($query, $culturaId) => $query->where('cultura_id', $culturaId))
            ->orderByDesc('ano')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Campanha $campanha) => [
                'id' => $campanha->id,
                'cultura_nome' => $campanha->cultura?->nome,
                'ano' => $campanha->ano,
                'data_inicio' => optional($campanha->data_inicio)?->format('Y-m-d'),
                'data_fim' => optional($campanha->data_fim)?->format('Y-m-d'),
                'status' => $campanha->status,
                'producao_esperada' => $campanha->producao_esperada,
                'producao_real' => $campanha->producao_real,
                'custo_estimado' => $campanha->custo_estimado,
                'custo_real' => $campanha->custo_real,
                'custo_total' => $campanha->custo_total_calculado,
                'custo_por_kg' => $campanha->custo_por_kg,
                'operacoes_count' => $campanha->operacoes_count,
                'colheitas_count' => $campanha->colheitas_count,
                'updated_at' => optional($campanha->updated_at)?->format('d/m/Y H:i'),
            ]);

        return Inertia::render('Campanhas/Index', [
            'campanhas' => $campanhas,
            'filters' => $filters,
            'summary' => [
                'total' => Campanha::query()->count(),
                'concluidas' => Campanha::query()->where('status', 'concluida')->count(),
                'custo_total' => Campanha::query()
                    ->with(['custos:id,campanha_id,valor', 'operacoes.produtos:id,nome'])
                    ->get()
                    ->sum(fn (Campanha $campanha) => $campanha->custo_total_calculado),
            ],
            'can' => [
                'create' => $request->user()->can('create', Campanha::class),
            ],
            'statusOptions' => ['planejada', 'em_curso', 'concluida', 'cancelada'],
            'anos' => Campanha::query()->distinct()->pluck('ano')->sort()->values(),
            'culturas' => Cultura::query()->orderBy('nome')->get(['id', 'nome']),
        ]);
    }

    public function exportar(Campanha $campanha)
    {
        $this->authorize('view', $campanha);

        $campanha->load([
            'cultura.parcela.terreno',
            'operacoes' => function ($query) {
                $query->with(['produtos', 'custos']);
            },
            'colheitas',
            'custos',
        ]);

        $exploracao = $campanha->cultura?->parcela?->terreno?->nome ?? 'N/A';
        $cultura = $campanha->cultura?->nome ?? 'N/A';
        $periodo = (optional($campanha->data_inicio)->format('d/m/Y') ?? 'N/A') . ' - ' . (optional($campanha->data_fim)->format('d/m/Y') ?? 'N/A');

        $producao = [
            'quantidade_colhida' => $campanha->colheitas->sum('quantidade_total'),
            'perdas' => $campanha->colheitas->sum('quantidade_perdas'),
            'qualidade' => $campanha->colheitas->pluck('qualidade')->filter()->first() ?? 'N/A',
        ];

        $custosPorCategoria = $campanha->custos
            ->groupBy('tipo')
            ->map(fn ($custos) => $custos->sum('valor'));

        $custoProdutos = $campanha->operacoes
            ->flatMap(fn ($operacao) => $operacao->produtos)
            ->sum(fn ($produto) => (float) ($produto->pivot->custo_total ?? 0));
        $custoTotal = (float) $campanha->custos->sum('valor') + (float) $campanha->operacoes->sum('custo_real') + $custoProdutos;
        $custoPorKg = $campanha->custo_por_kg;
        $areaTotal = $campanha->cultura?->parcela?->area_util ?? 0;
        $custoPorHa = $areaTotal > 0 ? round($custoTotal / $areaTotal, 2) : 0;

        $financeiro = [
            'custo_total' => $custoTotal,
            'custos_por_categoria' => $custosPorCategoria,
            'custo_por_kg' => $custoPorKg,
            'custo_por_ha' => $custoPorHa,
        ];

        $operacoes = $campanha->operacoes->map(function ($operacao) {
            return [
                'data' => optional($operacao->data_hora_inicio)?->format('d/m/Y') ?? 'N/A',
                'tipo' => $operacao->tipo ?? 'N/A',
                'custo' => $operacao->custo_real,
            ];
        });

        $produtosFitofarmaceuticos = $campanha->operacoes->flatMap(function ($operacao) {
            return $operacao->produtos
                ->filter(function ($produto) {
                    return in_array($produto->tipo, ['fitofarmaco', 'fitofarmaceutico', 'produto fitofarmaceutico'], true);
                })
                ->map(function ($produto) use ($operacao) {
                    return [
                        'nome' => $produto->nome,
                        'numero_autorizacao' => $produto->numero_autorizacao_dgav ?? 'N/A',
                        'dose' => trim(($produto->pivot->dose ?? '') . ' ' . ($produto->pivot->dose_unidade ?? '')),
                        'area_tratada' => $produto->pivot->area_tratada,
                        'data_aplicacao' => optional($operacao->data_hora_inicio)?->format('d/m/Y') ?? 'N/A',
                    ];
                });
        });

        $dataGeracao = now()->format('d/m/Y H:i');

        return view('campanhas.relatorio', compact(
            'campanha',
            'exploracao',
            'cultura',
            'periodo',
            'producao',
            'financeiro',
            'operacoes',
            'produtosFitofarmaceuticos',
            'dataGeracao'
        ));
    }
}
