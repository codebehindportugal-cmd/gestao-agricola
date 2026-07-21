<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\RespondeJson;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreCustoApiRequest;
use App\Http\Resources\Api\V1\CustoResource;
use App\Models\Custo;
use App\Services\ResolvedorReferencias;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustoController extends Controller
{
    use RespondeJson;

    public function __construct(private readonly ResolvedorReferencias $resolvedor)
    {
    }

    public function store(StoreCustoApiRequest $request): JsonResponse
    {
        $itens = $this->normalizarItens($request->validated());
        $avisos = [];

        try {
            $resultado = DB::transaction(function () use ($itens, &$avisos): array {
                $criados = [];
                $ignorados = [];
                $custos = [];

                foreach ($itens as $indice => $item) {
                    if (! empty($item['referencia_externa'])) {
                        $existente = Custo::query()
                            ->where('referencia_externa', $item['referencia_externa'])
                            ->first();

                        if ($existente) {
                            $ignorados[] = [
                                'indice' => $indice,
                                'id' => $existente->id,
                                'referencia_externa' => $item['referencia_externa'],
                            ];
                            $avisos[] = "custo ja registado ({$item['referencia_externa']})";
                            $custos[] = $existente->load($this->relacoes());

                            continue;
                        }
                    }

                    $custo = Custo::query()->create($this->payloadCusto($item));
                    $criados[] = $custo->id;
                    $custos[] = $custo->load($this->relacoes());
                }

                return [
                    'criados' => $criados,
                    'ignorados' => $ignorados,
                    'custos' => CustoResource::collection(collect($custos))->resolve(),
                ];
            });
        } catch (ValidationException $exception) {
            return $this->erro422($exception->errors());
        }

        return $this->criado($resultado, $avisos);
    }

    private function normalizarItens(array $data): array
    {
        return array_values($data['custos'] ?? [$data]);
    }

    private function payloadCusto(array $item): array
    {
        return [
            'descricao' => $item['descricao'],
            'tipo' => $item['tipo'],
            'valor' => $item['valor'],
            'data_custo' => $item['data'],
            'referencia_externa' => $item['referencia_externa'] ?? null,
            'observacoes' => $item['observacoes'] ?? null,
            'campanha_id' => $this->resolverIdOpcional('campanha', $item),
            'operacao_id' => $this->resolverIdOpcional('operacao', $item),
            'cultura_id' => $this->resolverIdOpcional('cultura', $item),
            'parcela_id' => $this->resolverIdOpcional('parcela', $item),
            'maquina_id' => $this->resolverIdOpcional('maquina', $item),
            'funcionario_id' => $this->resolverIdOpcional('funcionario', $item),
        ];
    }

    private function resolverIdOpcional(string $campo, array $item): ?int
    {
        if (! array_key_exists($campo, $item) || $item[$campo] === null || $item[$campo] === '') {
            return null;
        }

        $valor = $this->valorReferencia($item[$campo]);

        return match ($campo) {
            'campanha' => $this->resolvedor->resolverCampanha($valor)->id,
            'operacao' => $this->resolvedor->resolverOperacao($valor)->id,
            'cultura' => $this->resolvedor->resolverCultura($valor)->id,
            'parcela' => $this->resolvedor->resolverParcela($valor)->id,
            'maquina' => $this->resolvedor->resolverMaquina($valor)->id,
            'funcionario' => $this->resolvedor->resolverFuncionario($valor)->id,
        };
    }

    private function valorReferencia(mixed $referencia): int|string|null
    {
        if (! is_array($referencia)) {
            return $referencia;
        }

        foreach (['id', 'nome', 'codigo', 'numero_autorizacao_dgav'] as $chave) {
            if (array_key_exists($chave, $referencia) && $referencia[$chave] !== null && $referencia[$chave] !== '') {
                return $referencia[$chave];
            }
        }

        return null;
    }

    private function relacoes(): array
    {
        return ['campanha', 'operacao', 'cultura', 'parcela', 'maquina', 'funcionario'];
    }
}
