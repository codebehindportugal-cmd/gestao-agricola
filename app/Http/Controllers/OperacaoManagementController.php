<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOperacaoRequest;
use App\Http\Requests\UpdateOperacaoRequest;
use App\Models\Alfaia;
use App\Models\Campanha;
use App\Models\Cultura;
use App\Models\Funcionario;
use App\Models\Equipa;
use App\Models\Maquina;
use App\Models\Operacao;
use App\Models\Parcela;
use App\Models\Produto;
use App\Models\User;
use App\Models\Custo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
                'maquina:id,nome',
                'alfaia:id,nome',
                'operador:id,name',
                'funcionario:id,nome,cargo,status',
                'equipa:id,nome,status',
                'produtos:id,nome,tipo,unidade_medida,custo_unitario,numero_autorizacao_dgav',
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
                'custo_estimado' => $operacao->custo_estimado,
                'custo_real' => $operacao->custo_real,
                'produtos' => $operacao->produtos->map(fn (Produto $produto) => [
                    'produto_id' => $produto->id,
                    'nome' => $produto->nome,
                    'tipo' => $produto->tipo,
                    'numero_autorizacao_dgav' => $produto->numero_autorizacao_dgav,
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
                ->get(['id', 'terreno_id', 'nome'])
                ->map(fn (Parcela $parcela) => [
                    'id' => $parcela->id,
                    'nome' => $parcela->nome,
                    'terreno_nome' => $parcela->terreno?->nome,
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
                ->get(['id', 'nome']),
            'alfaias' => Alfaia::query()
                ->orderBy('nome')
                ->get(['id', 'nome']),
            'operadores' => User::query()
                ->orderBy('name')
                ->get(['id', 'name']),
            'funcionarios' => Funcionario::query()
                ->where('status', 'ativo')
                ->orderBy('nome')
                ->get(['id', 'nome', 'cargo'])
                ->map(fn (Funcionario $funcionario) => [
                    'id' => $funcionario->id,
                    'nome' => $funcionario->nome,
                    'cargo' => $funcionario->cargo,
                ]),
            'produtos' => Produto::query()
                ->orderBy('nome')
                ->get(['id', 'nome', 'tipo', 'unidade_medida', 'custo_unitario', 'numero_autorizacao_dgav'])
                ->map(fn (Produto $produto) => [
                    'id' => $produto->id,
                    'nome' => $produto->nome,
                    'tipo' => $produto->tipo,
                    'unidade_medida' => $produto->unidade_medida,
                    'custo_unitario' => $produto->custo_unitario,
                    'numero_autorizacao_dgav' => $produto->numero_autorizacao_dgav,
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
            'cadernoCampo' => $this->cadernoCampoResumo(),
        ]);
    }

    public function store(StoreOperacaoRequest $request): RedirectResponse
    {
        $this->authorize('create', Operacao::class);

        try {
            DB::transaction(function () use ($request) {
                $data = $this->normalizePayload($request);
                $produtos = $this->extractProdutosPayload($request);
                $operacao = Operacao::query()->create($data);

                $operacao->produtos()->sync($produtos);
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

                $operacao->update($data);
                $operacao->produtos()->sync($produtos);
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
            $operacao->delete();
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

        try {
            Produto::query()->create($data);
        } catch (\Throwable $exception) {
            return $this->backWithError('Não foi possível criar o produto. Verifique os dados e tente novamente.', $exception);
        }

        return redirect()
            ->route('app.operacoes.index', $this->redirectFilters($request))
            ->with('success', 'Produto criado com sucesso.');
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
            'custo_estimado',
            'custo_real',
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

        return $data;
    }

    private function extractProdutosPayload(Request $request): array
    {
        $produtosSelecionados = collect($request->input('produtos', []))
            ->filter(fn (array $produto) => !empty($produto['produto_id']))
            ->values();

        $custosUnitarios = Produto::query()
            ->whereIn('id', $produtosSelecionados->pluck('produto_id')->filter()->all())
            ->pluck('custo_unitario', 'id');

        return $produtosSelecionados
            ->mapWithKeys(function (array $produto) use ($custosUnitarios) {
                $quantidade = (float) ($produto['quantidade'] ?? 0);
                $custoUnitario = ($custosUnitarios[$produto['produto_id']] ?? null) === null
                    ? null
                    : (float) $custosUnitarios[$produto['produto_id']];

                return [
                    $produto['produto_id'] => [
                        'quantidade' => $quantidade,
                        'unidade_medida' => $produto['unidade_medida'],
                        'dose' => $this->nullableFloat($produto['dose'] ?? null),
                        'dose_unidade' => $produto['dose_unidade'] ?? null,
                        'area_tratada' => $this->nullableFloat($produto['area_tratada'] ?? null),
                        'volume_calda' => $this->nullableFloat($produto['volume_calda'] ?? null),
                        'finalidade' => $produto['finalidade'] ?? null,
                        'intervalo_seguranca_dias' => ($produto['intervalo_seguranca_dias'] ?? null) === '' || ($produto['intervalo_seguranca_dias'] ?? null) === null
                            ? null
                            : (int) $produto['intervalo_seguranca_dias'],
                        'estabelecimento_venda_nome' => $produto['estabelecimento_venda_nome'] ?? null,
                        'estabelecimento_venda_autorizacao' => $produto['estabelecimento_venda_autorizacao'] ?? null,
                        'custo_unitario' => $custoUnitario,
                        'custo_total' => $custoUnitario === null ? null : round($quantidade * $custoUnitario, 2),
                        'observacoes' => $produto['observacoes'] ?? null,
                    ],
                ];
            })
            ->all();
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

        return (float) $value;
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

                $custoProdutos = (float) $operacoes
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
                $producaoReal = (float) ($campanha->producao_real ?? 0);

                return [
                    'id' => $campanha->id,
                    'nome' => "{$campanha->cultura?->nome} - {$campanha->ano}",
                    'tratamentos' => $operacoes->count(),
                    'producao_real' => $campanha->producao_real,
                    'custo_operacoes' => $custoOperacoes,
                    'custo_produtos' => $custoProdutos,
                    'custo_outros' => $custoOutros,
                    'custo_total' => $custoTotal,
                    'custo_por_unidade' => $producaoReal > 0 ? round($custoTotal / $producaoReal, 4) : null,
                ];
            });
    }
}
