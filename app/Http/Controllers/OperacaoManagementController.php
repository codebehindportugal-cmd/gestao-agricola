<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOperacaoRequest;
use App\Http\Requests\UpdateOperacaoRequest;
use App\Models\Alfaia;
use App\Models\Campanha;
use App\Models\Colheita;
use App\Models\Cultura;
use App\Models\Funcionario;
use App\Models\Equipa;
use App\Models\ExploracaoSetting;
use App\Models\Maquina;
use App\Models\Operacao;
use App\Models\Parcela;
use App\Models\Produto;
use App\Models\User;
use App\Models\Custo;
use App\Support\OperacaoDuration;
use App\Support\StockConsumption;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class OperacaoManagementController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Operacao::class);

        $filters = $request->only(['search', 'estado', 'parcela_id', 'tipo']);
        $user = $request->user();

        $operacoes = Operacao::query()
            ->with([
                'parcela.terreno:id,nome',
                'cultura:id,nome',
                'campanha:id,cultura_id,ano,data_inicio,data_fim,status',
                'colheita:id,operacao_id,quantidade_total,quantidade_perdas,qualidade',
                'maquina:id,nome,tipo,consumo_combustivel',
                'alfaia:id,nome',
                'operador:id,name',
                'funcionario:id,nome,cargo,status,aplicador_numero_autorizacao',
                'equipa:id,nome,status',
                'produtos:id,nome,tipo,unidade_medida,custo_unitario,numero_autorizacao_dgav,estabelecimento_venda_nome,estabelecimento_venda_autorizacao',
            ])
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery
                        ->where('tipo', 'like', "%{$search}%")
                        ->orWhere('estado', 'like', "%{$search}%")
                        ->orWhere('observacoes', 'like', "%{$search}%")
                        ->orWhereHas('produtos', fn ($productQuery) => $productQuery->where('nome', 'like', "%{$search}%"));
                });
            })
            ->when($filters['estado'] ?? null, fn ($query, $estado) => $query->where('estado', $estado))
            ->when($filters['parcela_id'] ?? null, fn ($query, $parcelaId) => $query->where('parcela_id', $parcelaId))
            ->when($filters['tipo'] ?? null, fn ($query, $tipo) => $query->where('tipo', $tipo))
            ->orderByDesc('data_hora_inicio')
            ->paginate(9)
            ->withQueryString()
            ->through(fn (Operacao $operacao) => [
                'id' => $operacao->id,
                'parcela_id' => $operacao->parcela_id,
                'cultura_id' => $operacao->cultura_id,
                'campanha_id' => $operacao->campanha_id,
                'maquina_id' => $operacao->maquina_id,
                'alfaia_id' => $operacao->alfaia_id,
                'operador_id' => $operacao->operador_id,
                'funcionario_id' => $operacao->funcionario_id,
                'equipa_id' => $operacao->equipa_id,
                'produtor_nome' => $operacao->produtor_nome,
                'aplicador_nome' => $operacao->aplicador_nome,
                'aplicador_numero_autorizacao' => $operacao->aplicador_numero_autorizacao,
                'exploracao_concelho' => $operacao->exploracao_concelho,
                'exploracao_freguesia' => $operacao->exploracao_freguesia,
                'tipo' => $operacao->tipo,
                'estado' => $operacao->estado,
                'parcela_nome' => $operacao->parcela?->nome,
                'terreno_nome' => $operacao->parcela?->terreno?->nome,
                'cultura_nome' => $operacao->cultura?->nome,
                'campanha_nome' => $operacao->campanha ? "Campanha {$operacao->campanha->ano}" : null,
                'maquina_nome' => $operacao->maquina?->nome,
                'alfaia_nome' => $operacao->alfaia?->nome,
                'operador_nome' => $operacao->funcionario?->nome ?? $operacao->operador?->name,
                'equipa_nome' => $operacao->equipa?->nome,
                'data_hora_inicio' => optional($operacao->data_hora_inicio)?->format('Y-m-d\TH:i'),
                'data_hora_fim' => optional($operacao->data_hora_fim)?->format('Y-m-d\TH:i'),
                'duracao_horas' => $operacao->duracao_horas,
                'distancia_km' => $operacao->distancia_km,
                'combustivel_gasto_l' => $operacao->combustivel_gasto_l,
                'maquina_tipo' => $operacao->maquina?->tipo,
                'maquina_consumo_combustivel' => $operacao->maquina?->consumo_combustivel,
                'custo_estimado' => $operacao->custo_estimado,
                'custo_real' => $operacao->custo_real,
                'colheita_quantidade_total' => $operacao->colheita?->quantidade_total,
                'colheita_quantidade_perdas' => $operacao->colheita?->quantidade_perdas,
                'colheita_qualidade' => $operacao->colheita?->qualidade,
                'produtos' => $operacao->produtos->map(fn (Produto $produto) => [
                    'produto_id' => $produto->id,
                    'nome' => $produto->nome,
                    'tipo' => $produto->tipo,
                    'numero_autorizacao_dgav' => $produto->numero_autorizacao_dgav,
                    'estabelecimento_venda_nome_produto' => $produto->estabelecimento_venda_nome,
                    'estabelecimento_venda_autorizacao_produto' => $produto->estabelecimento_venda_autorizacao,
                    'quantidade' => $produto->pivot->quantidade,
                    'unidade_medida' => $produto->pivot->unidade_medida,
                    'dose' => $produto->pivot->dose,
                    'dose_unidade' => $produto->pivot->dose_unidade,
                    'area_tratada' => $produto->pivot->area_tratada,
                    'volume_calda' => $produto->pivot->volume_calda,
                    'finalidade' => $produto->pivot->finalidade,
                    'intervalo_seguranca_dias' => $produto->pivot->intervalo_seguranca_dias,
                    'estabelecimento_venda_nome' => $produto->pivot->estabelecimento_venda_nome,
                    'estabelecimento_venda_autorizacao' => $produto->pivot->estabelecimento_venda_autorizacao,
                    'custo_unitario' => $produto->pivot->custo_unitario,
                    'custo_total' => $produto->pivot->custo_total,
                    'observacoes' => $produto->pivot->observacoes,
                ])->values(),
                'observacoes' => $operacao->observacoes,
                'can_update' => $user->can('update', $operacao),
                'can_delete' => $user->can('delete', $operacao),
                'updated_at' => optional($operacao->updated_at)?->format('d/m/Y H:i'),
            ]);

        return Inertia::render('Operacoes/Index', [
            'operacoes' => $operacoes,
            'filters' => $filters,
            'summary' => [
                'total' => Operacao::query()->count(),
                'planeadas' => Operacao::query()->where('estado', 'planejada')->count(),
                'em_curso' => Operacao::query()->where('estado', 'em_curso')->count(),
                'concluidas' => Operacao::query()->where('estado', 'concluida')->count(),
            ],
            'can' => [
                'create' => $user->can('create', Operacao::class),
            ],
            'estadoOptions' => ['planejada', 'em_curso', 'concluida', 'cancelada'],
            'tipoOptions' => $this->tipoOptions(),
            'parcelas' => Parcela::query()
                ->with('terreno:id,nome')
                ->orderBy('nome')
                ->get(['id', 'terreno_id', 'nome', 'area_total', 'area_util'])
                ->map(fn (Parcela $parcela) => [
                    'id' => $parcela->id,
                    'nome' => $parcela->nome,
                    'terreno_nome' => $parcela->terreno?->nome,
                    'area_total' => $parcela->area_total,
                    'area_util' => $parcela->area_util,
                ]),
            'culturas' => Cultura::query()
                ->orderBy('nome')
                ->get(['id', 'parcela_id', 'nome'])
                ->map(fn (Cultura $cultura) => [
                    'id' => $cultura->id,
                    'parcela_id' => $cultura->parcela_id,
                    'nome' => $cultura->nome,
                ]),
            'maquinas' => Maquina::query()
                ->orderBy('nome')
                ->get(['id', 'nome', 'tipo', 'consumo_combustivel']),
            'alfaias' => Alfaia::query()
                ->orderBy('nome')
                ->get(['id', 'nome']),
            'operadores' => User::query()
                ->orderBy('name')
                ->get(['id', 'name']),
            'funcionarios' => Funcionario::query()
                ->where('status', 'ativo')
                ->orderBy('nome')
                ->get(['id', 'nome', 'cargo', 'aplicador_numero_autorizacao'])
                ->map(fn (Funcionario $funcionario) => [
                    'id' => $funcionario->id,
                    'nome' => $funcionario->nome,
                    'cargo' => $funcionario->cargo,
                    'aplicador_numero_autorizacao' => $funcionario->aplicador_numero_autorizacao,
                ]),
            'produtos' => Produto::query()
                ->orderBy('nome')
                ->get(['id', 'nome', 'tipo', 'unidade_medida', 'custo_unitario', 'numero_autorizacao_dgav', 'estabelecimento_venda_nome', 'estabelecimento_venda_autorizacao'])
                ->map(fn (Produto $produto) => [
                    'id' => $produto->id,
                    'nome' => $produto->nome,
                    'tipo' => $produto->tipo,
                    'unidade_medida' => $produto->unidade_medida,
                    'custo_unitario' => $produto->custo_unitario,
                    'numero_autorizacao_dgav' => $produto->numero_autorizacao_dgav,
                    'estabelecimento_venda_nome' => $produto->estabelecimento_venda_nome,
                    'estabelecimento_venda_autorizacao' => $produto->estabelecimento_venda_autorizacao,
                ]),
            'equipas' => Equipa::query()
                ->where('status', 'ativa')
                ->orderBy('nome')
                ->get(['id', 'nome']),
            'campanhas' => Campanha::query()
                ->with('cultura:id,nome')
                ->orderByDesc('ano')
                ->get(['id', 'cultura_id', 'ano', 'data_inicio', 'data_fim', 'status'])
                ->map(fn (Campanha $campanha) => [
                    'id' => $campanha->id,
                    'cultura_id' => $campanha->cultura_id,
                    'nome' => "{$campanha->cultura?->nome} - {$campanha->ano}",
                    'ano' => $campanha->ano,
                    'status' => $campanha->status,
                ]),
            'stockResumo' => Produto::query()
                ->withSum('stocks as stock_atual', 'quantidade')
                ->orderBy('nome')
                ->get(['id', 'nome', 'tipo', 'unidade_medida', 'custo_unitario', 'stock_minimo'])
                ->map(fn (Produto $produto) => [
                    'id' => $produto->id,
                    'nome' => $produto->nome,
                    'tipo' => $produto->tipo,
                    'unidade_medida' => $produto->unidade_medida,
                    'custo_unitario' => $produto->custo_unitario,
                    'stock_minimo' => (float) ($produto->stock_minimo ?? 0),
                    'stock_atual' => (float) ($produto->stock_atual ?? 0),
                    'abaixo_minimo' => (float) ($produto->stock_atual ?? 0) <= (float) ($produto->stock_minimo ?? 0),
                    'valor_stock' => $produto->custo_unitario === null
                        ? null
                        : round((float) ($produto->stock_atual ?? 0) * (float) $produto->custo_unitario, 2),
                ]),
            'cadernoCampo' => $this->cadernoCampoResumoNormalizado(),
            'exploracaoDados' => $this->exploracaoDados(),
        ]);
    }

    public function store(StoreOperacaoRequest $request): RedirectResponse
    {
        $this->authorize('create', Operacao::class);

        try {
            DB::transaction(function () use ($request) {
                $data = $this->normalizePayload($request);
                $produtos = $this->extractProdutosPayload($request);
                $parcelaIds = $this->selectedParcelaIds($request);
                $isBulkOperation = count($parcelaIds) > 1;
                $distributionWeights = $isBulkOperation ? $this->parcelDistributionWeights($parcelaIds) : [];

                foreach ($parcelaIds as $parcelaId) {
                    $produtosParcela = $isBulkOperation
                        ? $this->distributedProdutosPayload($produtos, $parcelaId, $distributionWeights)
                        : $produtos;
                    $operationData = [
                        ...$data,
                        'parcela_id' => $parcelaId,
                        'cultura_id' => $isBulkOperation ? null : ($data['cultura_id'] ?? null),
                    ];
                    $operationData = $isBulkOperation
                        ? $this->distributedOperationPayload($operationData, $parcelaId, $distributionWeights)
                        : $operationData;
                    $operationData['campanha_id'] = $isBulkOperation ? null : $this->resolveCampanhaId($operationData);

                    $operacao = Operacao::query()->create([
                        ...$operationData,
                    ]);

                    $operacao->produtos()->sync($produtosParcela);
                    $this->syncColheita($operacao->fresh(), $request);
                    StockConsumption::syncOperation($operacao->fresh(), [], $produtosParcela);
                }
            });
        } catch (\Throwable $exception) {
            return $this->backWithError('Não foi possível criar a operação. Verifique os dados e tente novamente.', $exception);
        }

        return redirect()
            ->route('app.operacoes.index', $this->redirectFilters($request))
            ->with('success', 'Operação criada com sucesso.');
    }

    public function update(UpdateOperacaoRequest $request, Operacao $operacao): RedirectResponse
    {
        $this->authorize('update', $operacao);

        try {
            DB::transaction(function () use ($request, $operacao) {
                $data = $this->normalizePayload($request);
                $produtos = $this->extractProdutosPayload($request);
                $previousProducts = StockConsumption::productsFromOperation($operacao);
                $previousFuel = $operacao->combustivel_gasto_l === null ? null : (float) $operacao->combustivel_gasto_l;

                $data['campanha_id'] = $this->resolveCampanhaId($data);

                $operacao->update($data);
                $operacao->produtos()->sync($produtos);
                $this->syncColheita($operacao->fresh(), $request);
                StockConsumption::syncOperation($operacao->fresh(), $previousProducts, $produtos, $previousFuel);
            });
        } catch (\Throwable $exception) {
            return $this->backWithError('Não foi possível atualizar a operação. Verifique os dados e tente novamente.', $exception);
        }

        return redirect()
            ->route('app.operacoes.index', $this->redirectFilters($request))
            ->with('success', 'Operação atualizada com sucesso.');
    }

    public function destroy(Request $request, Operacao $operacao): RedirectResponse
    {
        $this->authorize('delete', $operacao);

        try {
            DB::transaction(function () use ($operacao) {
                StockConsumption::restoreOperation($operacao);
                $operacao->colheita()->delete();
                $operacao->delete();
            });
        } catch (\Throwable $exception) {
            return $this->backWithError('Não foi possível remover a operação. Confirme se não existem custos ou produtos associados.', $exception);
        }

        return redirect()
            ->route('app.operacoes.index', $this->redirectFilters($request))
            ->with('success', 'Operação removida com sucesso.');
    }

    public function storeProduto(Request $request): RedirectResponse
    {
        $this->authorize('create', Operacao::class);

        $data = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'tipo' => ['required', 'string', 'max:255'],
            'unidade_medida' => ['required', 'string', 'max:50'],
            'custo_unitario' => ['nullable', 'numeric', 'min:0'],
            'codigo_interno' => ['nullable', 'string', 'max:255', 'unique:produtos,codigo_interno'],
            'numero_autorizacao_dgav' => ['nullable', 'string', 'max:255'],
            'estabelecimento_venda_nome' => ['nullable', 'string', 'max:255'],
            'estabelecimento_venda_autorizacao' => ['nullable', 'string', 'max:255'],
            'descricao' => ['nullable', 'string'],
        ], [
            'nome.required' => 'O nome do produto é obrigatório.',
            'tipo.required' => 'O tipo de produto é obrigatório.',
            'unidade_medida.required' => 'A unidade de medida é obrigatória.',
            'custo_unitario.numeric' => 'O custo unitário deve ser numérico.',
            'custo_unitario.min' => 'O custo unitário não pode ser negativo.',
            'codigo_interno.unique' => 'Este código interno já está a ser usado.',
        ]);

        if (($data['custo_unitario'] ?? '') === '') {
            $data['custo_unitario'] = null;
        }

        $data['tipo'] = $this->normalizeProdutoTipo($data['tipo']);

        try {
            Produto::query()->create($data);
        } catch (\Throwable $exception) {
            return $this->backWithError('Não foi possível criar o produto. Verifique os dados e tente novamente.', $exception);
        }

        return redirect()
            ->route('app.operacoes.index', $this->redirectFilters($request))
            ->with('success', 'Produto criado com sucesso.');
    }

    public function updateExploracaoDados(Request $request): RedirectResponse
    {
        $this->authorize('create', Operacao::class);

        $data = $request->validate([
            'produtor_nome' => ['nullable', 'string', 'max:255'],
            'concelho' => ['nullable', 'string', 'max:255'],
            'freguesia' => ['nullable', 'string', 'max:255'],
        ]);

        ExploracaoSetting::current()->update($data);

        return redirect()
            ->route('app.operacoes.index', $this->redirectFilters($request))
            ->with('success', 'Dados da exploração atualizados.');
    }

    private function syncColheita(Operacao $operacao, Request $request): void
    {
        if ($operacao->tipo !== 'colheita') {
            $operacao->colheita()->delete();
            return;
        }

        $colheita = Colheita::withTrashed()->updateOrCreate(
            ['operacao_id' => $operacao->id],
            [
                'cultura_id' => $operacao->cultura_id,
                'campanha_id' => $operacao->campanha_id,
                'parcela_id' => $operacao->parcela_id,
                'data_colheita' => $operacao->data_hora_inicio?->toDateString(),
                'quantidade_total' => $request->input('colheita_quantidade_total'),
                'unidade_medida' => 'kg',
                'qualidade' => $request->input('colheita_qualidade') ?: 'comercial',
                'quantidade_perdas' => $request->input('colheita_quantidade_perdas') ?: null,
                'operador_id' => $operacao->operador_id,
                'observacoes' => $operacao->observacoes,
            ]
        );

        if ($colheita->trashed()) {
            $colheita->restore();
        }
    }

    private function resolveCampanhaId(array $data): ?int
    {
        if (! empty($data['campanha_id'])) {
            return (int) $data['campanha_id'];
        }

        if (empty($data['cultura_id'])) {
            return null;
        }

        $date = \Illuminate\Support\Carbon::parse($data['data_hora_inicio'] ?? now());
        $cultura = Cultura::query()->find($data['cultura_id'], ['id', 'quantidade_esperada']);

        $campanha = Campanha::query()->firstOrCreate(
            [
                'cultura_id' => (int) $data['cultura_id'],
                'ano' => (int) $date->year,
            ],
            [
                'data_inicio' => $date->copy()->startOfYear()->toDateString(),
                'status' => 'em_curso',
                'producao_esperada' => $cultura?->quantidade_esperada,
            ]
        );

        return $campanha->id;
    }

    private function redirectFilters(Request $request): array
    {
        return array_filter($request->only(['search', 'estado', 'parcela_id', 'tipo']));
    }

    private function normalizePayload(Request $request): array
    {
        $data = $request->validated();

        foreach ([
            'cultura_id',
            'campanha_id',
            'maquina_id',
            'alfaia_id',
            'operador_id',
            'funcionario_id',
            'equipa_id',
            'duracao_horas',
            'distancia_km',
            'combustivel_gasto_l',
            'custo_estimado',
            'custo_real',
            'colheita_quantidade_total',
            'colheita_quantidade_perdas',
            'colheita_qualidade',
            'data_hora_fim',
            'produtor_nome',
            'aplicador_nome',
            'aplicador_numero_autorizacao',
            'exploracao_concelho',
            'exploracao_freguesia',
        ] as $field) {
            if (array_key_exists($field, $data) && $data[$field] === '') {
                $data[$field] = null;
            }
        }

        unset($data['produtos']);
        unset($data['parcela_ids']);
        unset($data['colheita_quantidade_total']);
        unset($data['colheita_quantidade_perdas']);
        unset($data['colheita_qualidade']);

        $data['duracao_horas'] = OperacaoDuration::calculateFromStrings(
            $data['data_hora_inicio'] ?? null,
            $data['data_hora_fim'] ?? null,
        );
        $data['combustivel_gasto_l'] = $this->calculateFuelUsage($data);
        $data = $this->fillDgavDefaults($data);

        return $data;
    }

    private function fillDgavDefaults(array $data): array
    {
        $exploracao = ExploracaoSetting::current();

        $data['produtor_nome'] = $data['produtor_nome'] ?: $exploracao->produtor_nome;
        $data['exploracao_concelho'] = $data['exploracao_concelho'] ?: $exploracao->concelho;
        $data['exploracao_freguesia'] = $data['exploracao_freguesia'] ?: $exploracao->freguesia;

        if (! empty($data['funcionario_id'])) {
            $funcionario = Funcionario::query()->find($data['funcionario_id'], ['id', 'nome', 'aplicador_numero_autorizacao']);
            $data['aplicador_nome'] = $data['aplicador_nome'] ?: $funcionario?->nome;
            $data['aplicador_numero_autorizacao'] = $data['aplicador_numero_autorizacao'] ?: $funcionario?->aplicador_numero_autorizacao;
        }

        return $data;
    }

    private function exploracaoDados(): array
    {
        $exploracao = ExploracaoSetting::current();

        return [
            'produtor_nome' => $exploracao->produtor_nome,
            'concelho' => $exploracao->concelho,
            'freguesia' => $exploracao->freguesia,
        ];
    }

    private function calculateFuelUsage(array $data): ?float
    {
        if (empty($data['maquina_id'])) {
            return null;
        }

        $maquina = Maquina::query()->find($data['maquina_id'], ['id', 'tipo', 'consumo_combustivel']);

        if (! $maquina || $maquina->consumo_combustivel === null) {
            return null;
        }

        $consumo = (float) $maquina->consumo_combustivel;
        $tipo = strtolower((string) $maquina->tipo);

        if (in_array($tipo, ['carro', 'carrinha', 'camião', 'camiao', 'moto_4'], true)) {
            $distancia = (float) ($data['distancia_km'] ?? 0);

            return $distancia > 0 ? round(($distancia * $consumo) / 100, 2) : null;
        }

        $horas = (float) ($data['duracao_horas'] ?? 0);

        return $horas > 0 ? round($horas * $consumo, 2) : null;
    }

    private function selectedParcelaIds(Request $request): array
    {
        $parcelaIds = collect($request->input('parcela_ids', []))
            ->filter()
            ->map(fn ($id) => (int) $id);

        if ($parcelaIds->isEmpty() && $request->filled('parcela_id')) {
            $parcelaIds->push((int) $request->input('parcela_id'));
        }

        return $parcelaIds->unique()->values()->all();
    }

    private function extractProdutosPayload(Request $request): array
    {
        $produtosSelecionados = collect($request->input('produtos', []))
            ->filter(fn (array $produto) => !empty($produto['produto_id']))
            ->values();

        $produtosCatalogo = Produto::query()
            ->whereIn('id', $produtosSelecionados->pluck('produto_id')->filter()->all())
            ->get(['id', 'custo_unitario', 'estabelecimento_venda_nome', 'estabelecimento_venda_autorizacao'])
            ->keyBy('id');

        return $produtosSelecionados
            ->mapWithKeys(function (array $produto) use ($produtosCatalogo) {
                $dose = $this->nullableFloat($produto['dose'] ?? null);
                $areaTratada = $this->nullableFloat($produto['area_tratada'] ?? null);
                $quantidade = $this->calculatedProductQuantity(
                    $this->nullableFloat($produto['quantidade'] ?? null),
                    $dose,
                    $areaTratada,
                    $produto['dose_unidade'] ?? null,
                );
                $produtoCatalogo = $produtosCatalogo->get($produto['produto_id']);
                $custoUnitario = ($produtoCatalogo?->custo_unitario) === null
                    ? null
                    : (float) $produtoCatalogo->custo_unitario;

                return [
                    $produto['produto_id'] => [
                        'quantidade' => $quantidade,
                        'unidade_medida' => $produto['unidade_medida'],
                        'dose' => $dose,
                        'dose_unidade' => $produto['dose_unidade'] ?? null,
                        'area_tratada' => $areaTratada,
                        'volume_calda' => $this->nullableFloat($produto['volume_calda'] ?? null),
                        'finalidade' => $produto['finalidade'] ?? null,
                        'intervalo_seguranca_dias' => ($produto['intervalo_seguranca_dias'] ?? null) === '' || ($produto['intervalo_seguranca_dias'] ?? null) === null
                            ? null
                            : (int) $produto['intervalo_seguranca_dias'],
                        'estabelecimento_venda_nome' => $produtoCatalogo?->estabelecimento_venda_nome,
                        'estabelecimento_venda_autorizacao' => $produtoCatalogo?->estabelecimento_venda_autorizacao,
                        'custo_unitario' => $custoUnitario,
                        'custo_total' => $custoUnitario === null ? null : round($quantidade * $custoUnitario, 2),
                        'observacoes' => $produto['observacoes'] ?? null,
                    ],
                ];
            })
            ->all();
    }

    private function parcelDistributionWeights(array $parcelaIds): array
    {
        $parcelas = Parcela::query()
            ->whereIn('id', $parcelaIds)
            ->get(['id', 'area_util', 'area_total']);

        $areas = collect($parcelaIds)->mapWithKeys(function (int $parcelaId) use ($parcelas) {
            $parcela = $parcelas->firstWhere('id', $parcelaId);

            return [$parcelaId => (float) ($parcela?->area_util ?: $parcela?->area_total ?: 0)];
        });

        $totalArea = (float) $areas->sum();

        if ($totalArea <= 0) {
            $equalWeight = count($parcelaIds) > 0 ? 1 / count($parcelaIds) : 0;

            return collect($parcelaIds)
                ->mapWithKeys(fn (int $parcelaId) => [$parcelaId => $equalWeight])
                ->all();
        }

        return $areas
            ->map(fn (float $area) => $area > 0 ? $area / $totalArea : 0)
            ->all();
    }

    private function distributedProdutosPayload(array $produtos, int $parcelaId, array $weights): array
    {
        $weight = (float) ($weights[$parcelaId] ?? 0);

        return collect($produtos)
            ->map(function (array $payload) use ($weight) {
                foreach (['quantidade', 'area_tratada'] as $field) {
                    if (($payload[$field] ?? null) !== null) {
                        $payload[$field] = round((float) $payload[$field] * $weight, 3);
                    }
                }

                if (($payload['custo_unitario'] ?? null) !== null) {
                    $payload['custo_total'] = round((float) $payload['quantidade'] * (float) $payload['custo_unitario'], 2);
                }

                return $payload;
            })
            ->all();
    }

    private function distributedOperationPayload(array $data, int $parcelaId, array $weights): array
    {
        $weight = (float) ($weights[$parcelaId] ?? 0);

        foreach (['combustivel_gasto_l', 'distancia_km'] as $field) {
            if (($data[$field] ?? null) !== null) {
                $data[$field] = round((float) $data[$field] * $weight, 2);
            }
        }

        return $data;
    }

    private function tipoOptions(): array
    {
        return [
            'mobilização do solo',
            'sementeira',
            'plantação',
            'rega',
            'fertilização',
            'tratamento fitossanitário',
            'poda',
            'limpeza',
            'manutenção',
            'colheita',
            'transporte',
        ];
    }

    private function nullableFloat(mixed $value): ?float
    {
        if ($value === '' || $value === null) {
            return null;
        }

        return (float) str_replace(',', '.', (string) $value);
    }

    private function calculatedProductQuantity(?float $submittedQuantity, ?float $dose, ?float $areaTratada, ?string $doseUnit): float
    {
        if ($dose !== null && $areaTratada !== null && $this->doseUnitIsPerHectare($doseUnit)) {
            return round($dose * $areaTratada, 3);
        }

        return (float) ($submittedQuantity ?? 0);
    }

    private function doseUnitIsPerHectare(?string $doseUnit): bool
    {
        $unit = strtolower((string) $doseUnit);
        $unit = str_replace([' ', 'hactare', 'hectare'], ['', 'ha', 'ha'], $unit);

        return str_contains($unit, '/ha');
    }

    private function normalizeProdutoTipo(?string $tipo): string
    {
        $normalized = strtolower(trim((string) $tipo));
        $normalized = strtr($normalized, [
            'á' => 'a',
            'à' => 'a',
            'â' => 'a',
            'ã' => 'a',
            'ç' => 'c',
            'é' => 'e',
            'ê' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ô' => 'o',
            'õ' => 'o',
            'ú' => 'u',
        ]);

        return match ($normalized) {
            'combustivel', 'combustiveis', 'gasoleo', 'diesel' => 'combustivel',
            'fitofarmaceutico', 'fitofarmaco', 'produto fitofarmaceutico' => 'fitofarmaco',
            'fertilizacao', 'fertilizante', 'adubo' => 'fertilizante',
            'sementes', 'semente' => 'semente',
            'plantas', 'planta' => 'planta',
            default => $normalized ?: 'outro',
        };
    }

    private function cadernoCampoResumoNormalizado()
    {
        return Campanha::query()
            ->with(['cultura:id,nome'])
            ->orderByDesc('ano')
            ->limit(8)
            ->get(['id', 'cultura_id', 'ano', 'status', 'producao_real', 'custo_real'])
            ->map(function (Campanha $campanha) {
                $operacoes = Operacao::query()
                    ->with(['produtos:id,nome,tipo'])
                    ->where('campanha_id', $campanha->id)
                    ->get();
                $tratamentos = $operacoes
                    ->filter(fn (Operacao $operacao) => $this->normaliseText($operacao->tipo) === 'tratamento fitossanitario');

                $custoProdutosTratamentos = (float) $tratamentos
                    ->flatMap(fn (Operacao $operacao) => $operacao->produtos)
                    ->sum(fn (Produto $produto) => (float) ($produto->pivot->custo_total ?? 0));
                $custoProdutos = (float) $operacoes
                    ->flatMap(fn (Operacao $operacao) => $operacao->produtos)
                    ->sum(fn (Produto $produto) => (float) ($produto->pivot->custo_total ?? 0));
                $custoOperacoes = (float) $operacoes->sum('custo_real');
                $custoOutros = (float) Custo::query()
                    ->where('campanha_id', $campanha->id)
                    ->sum('valor');
                $custoTotal = ($campanha->custo_real ?? null) !== null
                    ? (float) $campanha->custo_real
                    : $custoOperacoes + $custoProdutos + $custoOutros;
                $producaoReal = (float) Colheita::query()
                    ->where('campanha_id', $campanha->id)
                    ->sum('quantidade_total') ?: (float) ($campanha->producao_real ?? 0);

                return [
                    'id' => $campanha->id,
                    'nome' => "{$campanha->cultura?->nome} - {$campanha->ano}",
                    'tratamentos' => $tratamentos->count(),
                    'producao_real' => $producaoReal,
                    'custo_operacoes' => $custoOperacoes,
                    'custo_produtos' => $custoProdutosTratamentos,
                    'custo_outros' => $custoOutros,
                    'custo_total' => $custoTotal,
                    'custo_por_unidade' => $producaoReal > 0 ? round($custoTotal / $producaoReal, 4) : null,
                ];
            });
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

    private function cadernoCampoResumo()
    {
        return Campanha::query()
            ->with(['cultura:id,nome'])
            ->orderByDesc('ano')
            ->limit(8)
            ->get(['id', 'cultura_id', 'ano', 'status', 'producao_real', 'custo_real'])
            ->map(function (Campanha $campanha) {
                $operacoes = Operacao::query()
                    ->with(['produtos:id,nome,tipo'])
                    ->where('campanha_id', $campanha->id)
                    ->where('tipo', 'tratamento fitossanitário')
                    ->get();

                $custoProdutosTratamentos = (float) $operacoes
                    ->flatMap(fn (Operacao $operacao) => $operacao->produtos)
                    ->sum(fn (Produto $produto) => (float) ($produto->pivot->custo_total ?? 0));
                $custoProdutos = (float) Operacao::query()
                    ->with(['produtos:id,nome,tipo'])
                    ->where('campanha_id', $campanha->id)
                    ->get()
                    ->flatMap(fn (Operacao $operacao) => $operacao->produtos)
                    ->sum(fn (Produto $produto) => (float) ($produto->pivot->custo_total ?? 0));
                $custoOperacoes = (float) Operacao::query()
                    ->where('campanha_id', $campanha->id)
                    ->sum('custo_real');
                $custoOutros = (float) Custo::query()
                    ->where('campanha_id', $campanha->id)
                    ->sum('valor');
                $custoTotal = ($campanha->custo_real ?? null) !== null
                    ? (float) $campanha->custo_real
                    : $custoOperacoes + $custoProdutos + $custoOutros;
                $producaoReal = (float) Colheita::query()
                    ->where('campanha_id', $campanha->id)
                    ->sum('quantidade_total') ?: (float) ($campanha->producao_real ?? 0);

                return [
                    'id' => $campanha->id,
                    'nome' => "{$campanha->cultura?->nome} - {$campanha->ano}",
                    'tratamentos' => $operacoes->count(),
                    'producao_real' => $producaoReal,
                    'custo_operacoes' => $custoOperacoes,
                    'custo_produtos' => $custoProdutosTratamentos,
                    'custo_outros' => $custoOutros,
                    'custo_total' => $custoTotal,
                    'custo_por_unidade' => $producaoReal > 0 ? round($custoTotal / $producaoReal, 4) : null,
                ];
            });
    }
}
