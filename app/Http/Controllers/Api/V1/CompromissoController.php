<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\RespondeJson;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreCompromissoApiRequest;
use App\Models\Compromisso;
use App\Services\CompromissoService;
use App\Services\GeradorCompromissos;
use App\Services\ResolvedorReferencias;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CompromissoController extends Controller
{
    use RespondeJson;

    public function __construct(
        private readonly ResolvedorReferencias $resolvedor,
        private readonly GeradorCompromissos $gerador,
        private readonly CompromissoService $servico
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->query(), [
            'de' => ['nullable', 'date'],
            'ate' => ['nullable', 'date', 'after_or_equal:de'],
            'categoria' => ['nullable', 'string'],
            'estado' => ['nullable', 'string'],
            'atrasados' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return $this->erro422($validator->errors()->toArray());
        }

        $filtros = $validator->validated();

        $compromissos = Compromisso::query()
            ->with(['campanha.cultura:id,nome', 'parcela:id,nome', 'maquina:id,nome', 'funcionario:id,nome'])
            ->when($filtros['de'] ?? null, fn ($q, $de) => $q->whereDate('data', '>=', $de))
            ->when($filtros['ate'] ?? null, fn ($q, $ate) => $q->whereDate('data', '<=', $ate))
            ->when($filtros['categoria'] ?? null, fn ($q, $c) => $q->where('categoria', $c))
            ->when($filtros['estado'] ?? null, fn ($q, $e) => $q->where('estado', $e))
            ->when($filtros['atrasados'] ?? false, fn ($q) => $q->atrasados())
            ->orderBy('data')
            ->limit(500)
            ->get();

        return $this->ok([
            'compromissos' => $compromissos->map(fn (Compromisso $c) => $this->formatar($c))->all(),
            'total' => $compromissos->count(),
        ]);
    }

    public function store(StoreCompromissoApiRequest $request): JsonResponse
    {
        $data = $request->validated();
        $itens = $data['compromissos'] ?? [$request->all()];

        $criados = [];
        $ignorados = [];
        $avisos = [];

        try {
            DB::transaction(function () use ($itens, &$criados, &$ignorados, &$avisos): void {
                foreach ($itens as $indice => $item) {
                    $item = Validator::make($item, StoreCompromissoApiRequest::regrasItem())
                        ->validate();

                    if (! empty($item['referencia_externa'])) {
                        $existente = Compromisso::query()
                            ->where('referencia_externa', $item['referencia_externa'])
                            ->first();

                        if ($existente) {
                            $avisos[] = "compromisso ja registado ({$item['referencia_externa']})";
                            $ignorados[] = $this->formatar($existente);

                            continue;
                        }
                    }

                    $compromisso = Compromisso::query()->create([
                        ...collect($item)->except([
                            'campanha', 'parcela', 'cultura', 'maquina', 'funcionario',
                        ])->all(),
                        'campanha_id' => $this->resolverId('resolverCampanha', $item['campanha'] ?? null),
                        'parcela_id' => $this->resolverId('resolverParcela', $item['parcela'] ?? null),
                        'cultura_id' => $this->resolverId('resolverCultura', $item['cultura'] ?? null),
                        'maquina_id' => $this->resolverId('resolverMaquina', $item['maquina'] ?? null),
                        'funcionario_id' => $this->resolverId('resolverFuncionario', $item['funcionario'] ?? null),
                    ]);

                    $gerados = $this->gerador->gerar($compromisso);

                    if ($gerados !== []) {
                        $avisos[] = sprintf(
                            '"%s": geradas %d ocorrencias ate %s.',
                            $compromisso->titulo,
                            count($gerados),
                            end($gerados)->data->toDateString()
                        );
                    }

                    $criados[] = $this->formatar($compromisso);
                }
            });
        } catch (ValidationException $exception) {
            return $this->erro422($exception->errors());
        }

        return $this->criado([
            'criados' => array_column($criados, 'id'),
            'compromissos' => $criados,
            'ignorados' => $ignorados,
        ], $avisos);
    }

    public function concluir(Request $request, Compromisso $compromisso): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'valor_pago' => ['nullable', 'numeric', 'min:0'],
            'data_conclusao' => ['nullable', 'date'],
            'criar_custo' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return $this->erro422($validator->errors()->toArray());
        }

        $dados = $validator->validated();

        $resultado = $this->servico->concluir(
            $compromisso,
            $dados['valor_pago'] ?? null,
            $dados['data_conclusao'] ?? null,
            $dados['criar_custo'] ?? true
        );

        return $this->ok([
            'compromisso' => $this->formatar($resultado['compromisso']),
            'custo' => $resultado['custo'] === null ? null : [
                'id' => $resultado['custo']->id,
                'tipo' => $resultado['custo']->tipo,
                'valor' => $resultado['custo']->valor,
            ],
            'proxima_ocorrencia' => $resultado['proxima'] === null
                ? null
                : $this->formatar($resultado['proxima']),
        ]);
    }

    private function resolverId(string $metodo, mixed $referencia): ?int
    {
        if ($referencia === null || $referencia === '') {
            return null;
        }

        if (is_array($referencia)) {
            $referencia = $referencia['id'] ?? $referencia['nome'] ?? null;
        }

        if ($referencia === null || $referencia === '') {
            return null;
        }

        return $this->resolvedor->{$metodo}($referencia)->id;
    }

    private function formatar(Compromisso $c): array
    {
        return [
            'id' => $c->id,
            'titulo' => $c->titulo,
            'categoria' => $c->categoria,
            'tipo' => $c->tipo,
            'entidade' => $c->entidade,
            'data' => $c->data?->toDateString(),
            'valor' => $c->valor,
            'estado' => $c->estado,
            'recorrencia' => $c->recorrencia,
            'atrasado' => $c->atrasado,
            'dias_para_prazo' => $c->dias_para_prazo,
            'referencia_externa' => $c->referencia_externa,
            'campanha' => $c->campanha ? [
                'id' => $c->campanha->id,
                'nome' => trim(($c->campanha->cultura?->nome ? $c->campanha->cultura->nome.' ' : '').$c->campanha->ano),
            ] : null,
            'parcela' => $c->parcela ? ['id' => $c->parcela->id, 'nome' => $c->parcela->nome] : null,
            'maquina' => $c->maquina ? ['id' => $c->maquina->id, 'nome' => $c->maquina->nome] : null,
        ];
    }
}
