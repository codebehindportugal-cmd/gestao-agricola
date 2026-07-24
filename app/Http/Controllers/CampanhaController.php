<?php

namespace App\Http\Controllers;

use App\Models\Campanha;
use App\Models\Custo;
use App\Models\Cultura;
use App\Models\Operacao;
use App\Models\Produto;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
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
                'producao_real' => (float) $campanha->colheitas->sum('quantidade_total') ?: $campanha->producao_real,
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
                'custo_total' => (float) Campanha::query()->whereNotNull('custo_real')->sum('custo_real'),
            ],
            'can' => [
                'create' => $request->user()->can('create', Campanha::class),
            ],
            'statusOptions' => ['planejada', 'em_curso', 'concluida', 'cancelada'],
            'anos' => Campanha::query()->distinct()->pluck('ano')->sort()->values(),
            'culturas' => Cultura::query()->orderBy('nome')->get(['id', 'nome']),
        ]);
    }

    public function show(Request $request, Campanha $campanha): Response
    {
        $this->authorize('view', $campanha);

        $campanha->load([
            'cultura.parcela.terreno',
            'colheitas',
            'custos' => fn ($q) => $q->orderByRaw('data_custo IS NULL, data_custo ASC'),
            'operacoes' => fn ($q) => $q
                ->with([
                    'parcela:id,nome',
                    'maquina:id,nome',
                    'alfaia:id,nome',
                    'funcionario:id,nome',
                    'operador:id,name',
                    'produtos',
                ])
                ->orderBy('data_hora_inicio'),
        ]);

        $operacoes = $campanha->operacoes;
        $custoOperacoes = round((float) $operacoes->sum('custo_real'), 2);
        $custoProdutos = round((float) $operacoes
            ->flatMap(fn ($op) => $op->produtos)
            ->sum(fn ($produto) => $this->produtoPivotCost($produto)), 2);
        $custoDiretos = round((float) $campanha->custos->sum('valor'), 2);
        $custoTotal = round($custoOperacoes + $custoProdutos + $custoDiretos, 2);

        $producaoReal = (float) $campanha->colheitas->sum('quantidade_total')
            ?: (float) ($campanha->producao_real ?? 0);
        $area = (float) ($campanha->cultura?->parcela?->area_util
            ?: $campanha->cultura?->parcela?->area_total
            ?: 0);

        return Inertia::render('Campanhas/Show', [
            'campanha' => [
                'id' => $campanha->id,
                'ano' => $campanha->ano,
                'status' => $campanha->status,
                'data_inicio' => optional($campanha->data_inicio)?->format('Y-m-d'),
                'data_fim' => optional($campanha->data_fim)?->format('Y-m-d'),
                'producao_esperada' => $campanha->producao_esperada,
                'custo_estimado' => $campanha->custo_estimado,
                'observacoes' => $campanha->observacoes,
                'cultura_nome' => $campanha->cultura?->nome,
                'parcela_nome' => $campanha->cultura?->parcela?->nome,
                'terreno_nome' => $campanha->cultura?->parcela?->terreno?->nome,
                'area_ha' => $area,
            ],
            'operacoes' => $operacoes->map(fn ($op) => [
                'id' => $op->id,
                'tipo' => $op->tipo,
                'estado' => $op->estado,
                'data' => optional($op->data_hora_inicio)?->format('d/m/Y'),
                'parcela_nome' => $op->parcela?->nome,
                'maquina_nome' => $op->maquina?->nome,
                'responsavel' => $op->funcionario?->nome ?? $op->operador?->name,
                'custo_real' => (float) ($op->custo_real ?? 0),
                'custo_produtos' => round((float) $op->produtos->sum(fn ($p) => $this->produtoPivotCost($p)), 2),
                'produtos_nomes' => $op->produtos->pluck('nome')->join(', '),
            ])->values(),
            'colheitas' => $campanha->colheitas->map(fn ($c) => [
                'id' => $c->id,
                'data_colheita' => optional($c->data_colheita)?->format('d/m/Y'),
                'quantidade_total' => (float) ($c->quantidade_total ?? 0),
                'quantidade_perdas' => (float) ($c->quantidade_perdas ?? 0),
                'qualidade' => $c->qualidade,
                'observacoes' => $c->observacoes,
            ])->values(),
            'custos' => $campanha->custos->map(fn ($c) => [
                'id' => $c->id,
                'tipo' => $c->tipo,
                'descricao' => $c->descricao,
                'valor' => (float) ($c->valor ?? 0),
                'data_custo' => optional($c->data_custo)?->format('Y-m-d'),
                'observacoes' => $c->observacoes,
            ])->values(),
            'resumo' => [
                'custo_operacoes' => $custoOperacoes,
                'custo_produtos' => $custoProdutos,
                'custo_diretos' => $custoDiretos,
                'custo_total' => $custoTotal,
                'producao_real' => $producaoReal,
                'custo_por_kg' => $producaoReal > 0 ? round($custoTotal / $producaoReal, 4) : 0,
                'area_ha' => $area,
                'custo_por_ha' => $area > 0 ? round($custoTotal / $area, 2) : 0,
            ],
            'can' => [
                'update' => $request->user()->can('update', $campanha),
                'manage_custos' => $request->user()->can('update', $campanha),
            ],
            'tiposCusto' => ['material', 'mao_obra', 'maquinaria', 'energia', 'manutencao', 'outro'],
        ]);
    }

    public function storeCusto(Request $request, Campanha $campanha): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('update', $campanha);

        $data = $request->validate([
            'tipo' => ['required', 'string', 'in:material,mao_obra,maquinaria,energia,manutencao,outro'],
            'descricao' => ['required', 'string', 'max:255'],
            'valor' => ['required', 'numeric', 'min:0.01'],
            'data_custo' => ['nullable', 'date'],
            'observacoes' => ['nullable', 'string'],
        ], [
            'tipo.required' => 'O tipo de custo é obrigatório.',
            'tipo.in' => 'Selecione um tipo de custo válido.',
            'descricao.required' => 'A descrição é obrigatória.',
            'valor.required' => 'O valor é obrigatório.',
            'valor.min' => 'O valor deve ser superior a zero.',
        ]);

        $campanha->custos()->create($data);

        return redirect()
            ->route('app.campanhas.show', $campanha)
            ->with('success', 'Custo adicionado com sucesso.');
    }

    public function updateCusto(Request $request, Campanha $campanha, Custo $custo): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('update', $campanha);

        if ($custo->campanha_id !== $campanha->id) {
            abort(403);
        }

        $data = $request->validate([
            'tipo' => ['required', 'string', 'in:material,mao_obra,maquinaria,energia,manutencao,outro'],
            'descricao' => ['required', 'string', 'max:255'],
            'valor' => ['required', 'numeric', 'min:0.01'],
            'data_custo' => ['nullable', 'date'],
            'observacoes' => ['nullable', 'string'],
        ], [
            'tipo.required' => 'O tipo de custo é obrigatório.',
            'tipo.in' => 'Selecione um tipo de custo válido.',
            'descricao.required' => 'A descrição é obrigatória.',
            'valor.required' => 'O valor é obrigatório.',
            'valor.min' => 'O valor deve ser superior a zero.',
        ]);

        $custo->update($data);

        return redirect()
            ->route('app.campanhas.show', $campanha)
            ->with('success', 'Custo atualizado com sucesso.');
    }

    public function destroyCusto(Campanha $campanha, Custo $custo): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('update', $campanha);

        if ($custo->campanha_id !== $campanha->id) {
            abort(403);
        }

        $custo->delete();

        return redirect()
            ->route('app.campanhas.show', $campanha)
            ->with('success', 'Custo removido com sucesso.');
    }

    public function exportarCadernoCampo(Campanha $campanha)
    {
        $this->authorize('view', $campanha);

        $campanha->load([
            'cultura.parcela.terreno',
            'colheitas',
            'custos',
        ]);
        $campanha->setRelation('operacoes', $this->reportOperationsForCampaign($campanha, ['parcela.terreno', 'cultura', 'produtos', 'custos']));

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
            ->sum(fn (Produto $produto) => $this->produtoPivotCost($produto));
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

        $registosFitofarmaceuticos = $campanha->operacoes
            ->filter(fn ($operacao) => $this->normaliseText($operacao->tipo) === 'tratamento fitossanitario')
            ->flatMap(function ($operacao) {
                return $operacao->produtos
                    ->filter(fn (Produto $produto) => in_array($this->normaliseText($produto->tipo), [
                        'fitofarmaco',
                        'fitofarmaceutico',
                        'produto fitofarmaceutico',
                    ], true))
                    ->map(function (Produto $produto) use ($operacao) {
                        $areaTratada = (float) ($produto->pivot->area_tratada ?? 0);
                        $volumeCalda = (float) ($produto->pivot->volume_calda ?? 0);

                        return [
                            'parcela' => trim(($operacao->parcela?->terreno?->nome ? "{$operacao->parcela->terreno->nome} - " : '').($operacao->parcela?->nome ?? '')),
                            'cultura' => $operacao->cultura?->nome ?? $operacao->campanha?->cultura?->nome ?? '',
                            'area_tratada' => $areaTratada > 0 ? number_format($areaTratada, 2, ',', ' ') : '',
                            'inimigo_efeito' => $produto->pivot->finalidade ?? '',
                            'produto' => $produto->nome,
                            'numero_autorizacao' => $produto->numero_autorizacao_dgav ?? '',
                            'estabelecimento_nome' => $produto->pivot->estabelecimento_venda_nome
                                ?? $produto->estabelecimento_venda_nome
                                ?? '',
                            'estabelecimento_autorizacao' => $produto->pivot->estabelecimento_venda_autorizacao
                                ?? $produto->estabelecimento_venda_autorizacao
                                ?? '',
                            'dose' => trim(($produto->pivot->dose ?? '').' '.($produto->pivot->dose_unidade ?? '')),
                            'volume_calda' => $volumeCalda > 0 ? number_format($volumeCalda, 2, ',', ' ') : '',
                            'data_aplicacao' => optional($operacao->data_hora_inicio)?->format('d/m/Y') ?? '',
                            'produtor_nome' => $operacao->produtor_nome,
                            'aplicador_nome' => $operacao->aplicador_nome,
                            'aplicador_numero_autorizacao' => $operacao->aplicador_numero_autorizacao,
                            'concelho' => $operacao->exploracao_concelho,
                            'freguesia' => $operacao->exploracao_freguesia,
                        ];
                    });
            })
            ->values();

        $identificacao = [
            'produtor' => $registosFitofarmaceuticos->pluck('produtor_nome')->filter()->first() ?? '',
            'aplicador' => $registosFitofarmaceuticos->pluck('aplicador_nome')->filter()->first() ?? '',
            'aplicador_numero' => $registosFitofarmaceuticos->pluck('aplicador_numero_autorizacao')->filter()->first() ?? '',
            'concelho' => $registosFitofarmaceuticos->pluck('concelho')->filter()->first() ?? '',
            'freguesia' => $registosFitofarmaceuticos->pluck('freguesia')->filter()->first() ?? '',
        ];

        $linhasVazias = max(0, 8 - $registosFitofarmaceuticos->count());

        $produtosFitofarmaceuticos = $registosFitofarmaceuticos->map(function ($registo) {
            return [
                'nome' => $registo['produto'],
                'numero_autorizacao' => $registo['numero_autorizacao'] ?: 'N/A',
                'dose' => $registo['dose'],
                'area_tratada' => $registo['area_tratada'],
                'data_aplicacao' => $registo['data_aplicacao'] ?: 'N/A',
            ];
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
            'registosFitofarmaceuticos',
            'identificacao',
            'linhasVazias',
            'dataGeracao'
        ));
    }

    public function exportarCustosPdf(Campanha $campanha)
    {
        $this->authorize('view', $campanha);

        $campanha->load([
            'cultura.parcela.terreno',
            'custos',
            'colheitas',
        ]);
        $campanha->setRelation('operacoes', $this->reportOperationsForCampaign($campanha, ['parcela.terreno', 'maquina', 'alfaia', 'operador', 'funcionario', 'equipa', 'produtos']));

        $operacoes = $campanha->operacoes->map(function ($operacao) {
            $custoProdutos = (float) $operacao->produtos
                ->sum(fn (Produto $produto) => $this->produtoPivotCost($produto));

            return [
                'data' => optional($operacao->data_hora_inicio)?->format('d/m/Y') ?? 'N/A',
                'tipo' => $operacao->tipo ?? 'N/A',
                'parcela' => trim(($operacao->parcela?->terreno?->nome ? "{$operacao->parcela->terreno->nome} - " : '').($operacao->parcela?->nome ?? '')),
                'maquina' => $operacao->maquina?->nome,
                'alfaia' => $operacao->alfaia?->nome,
                'responsavel' => $operacao->funcionario?->nome ?? $operacao->operador?->name,
                'equipa' => $operacao->equipa?->nome,
                'duracao_horas' => (float) ($operacao->duracao_horas ?? 0),
                'combustivel_gasto_l' => (float) ($operacao->combustivel_gasto_l ?? 0),
                'custo_real' => (float) ($operacao->custo_real ?? 0),
                'custo_produtos' => $custoProdutos,
                'custo_total' => (float) ($operacao->custo_real ?? 0) + $custoProdutos,
                'produtos' => $operacao->produtos->map(fn (Produto $produto) => [
                    'nome' => $produto->nome,
                    'tipo' => $produto->tipo,
                    'quantidade' => (float) ($produto->pivot->quantidade ?? 0),
                    'unidade_medida' => $produto->pivot->unidade_medida ?? $produto->unidade_medida,
                    'custo_unitario' => $produto->pivot->custo_unitario === null
                        ? null
                        : (float) $produto->pivot->custo_unitario,
                    'custo_total' => $this->produtoPivotCost($produto),
                ])->values(),
            ];
        });

        $produtosAplicados = $operacoes
            ->flatMap(fn (array $operacao) => $operacao['produtos']->map(fn (array $produto) => [
                ...$produto,
                'data' => $operacao['data'],
                'operacao' => $operacao['tipo'],
                'parcela' => $operacao['parcela'],
            ]))
            ->values();

        $custosAvulsos = $campanha->custos->map(fn ($custo) => [
            'data' => optional($custo->data_custo ?? null)?->format('d/m/Y') ?? 'N/A',
            'tipo' => $custo->tipo ?? 'Outro',
            'descricao' => $custo->descricao ?? $custo->observacoes ?? '',
            'valor' => (float) ($custo->valor ?? 0),
        ]);

        $totalOperacoes = (float) $operacoes->sum('custo_real');
        $totalProdutos = (float) $operacoes->sum('custo_produtos');
        $totalCustosAvulsos = (float) $custosAvulsos->sum('valor');
        $custoTotal = $totalOperacoes + $totalProdutos + $totalCustosAvulsos;
        $producaoReal = (float) $campanha->colheitas->sum('quantidade_total') ?: (float) ($campanha->producao_real ?? 0);
        $custoPorKg = $producaoReal > 0 ? round($custoTotal / $producaoReal, 2) : 0;
        $areaTotal = (float) ($campanha->cultura?->parcela?->area_util ?? $campanha->cultura?->parcela?->area_total ?? 0);
        $custoPorHa = $areaTotal > 0 ? round($custoTotal / $areaTotal, 2) : 0;

        $resumo = [
            'total_operacoes' => $totalOperacoes,
            'total_produtos' => $totalProdutos,
            'total_custos_avulsos' => $totalCustosAvulsos,
            'custo_total' => $custoTotal,
            'producao_real' => $producaoReal,
            'custo_por_kg' => $custoPorKg,
            'area_total' => $areaTotal,
            'custo_por_ha' => $custoPorHa,
        ];

        $dataGeracao = now()->format('d/m/Y H:i');

        return view('campanhas.custos_pdf', compact(
            'campanha',
            'operacoes',
            'produtosAplicados',
            'custosAvulsos',
            'resumo',
            'dataGeracao'
        ));
    }

    private function produtoPivotCost(Produto $produto): float
    {
        if ($produto->pivot?->custo_total !== null) {
            return (float) $produto->pivot->custo_total;
        }

        if ($produto->pivot?->custo_unitario === null) {
            return 0.0;
        }

        return round((float) ($produto->pivot->quantidade ?? 0) * (float) $produto->pivot->custo_unitario, 2);
    }

    private function reportOperationsForCampaign(Campanha $campanha, array $with = []): Collection
    {
        $cultura = $campanha->cultura;
        $parcelaId = $cultura?->parcela_id;
        $culturaNome = $cultura?->nome;

        return Operacao::query()
            ->with($with)
            ->where(function ($query) use ($campanha, $parcelaId, $culturaNome) {
                $query->where('campanha_id', $campanha->id);

                if (! $parcelaId) {
                    return;
                }

                $query->orWhere(function ($fallbackQuery) use ($campanha, $parcelaId, $culturaNome) {
                    $fallbackQuery
                        ->where('parcela_id', $parcelaId)
                        ->whereYear('data_hora_inicio', (int) $campanha->ano)
                        ->where(function ($scopeQuery) use ($campanha, $culturaNome) {
                            $scopeQuery
                                ->where('cultura_id', $campanha->cultura_id)
                                ->orWhereNull('cultura_id');

                            if ($culturaNome) {
                                $scopeQuery->orWhereHas('cultura', fn ($culturaQuery) => $culturaQuery->where('nome', $culturaNome));
                            }
                        });
                });
            })
            ->orderBy('data_hora_inicio')
            ->get()
            ->unique('id')
            ->values();
    }

    private function normaliseText(?string $value): string
    {
        return Str::of($value ?? '')
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->squish()
            ->toString();
    }
}
