<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\RespondeJson;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreReceitaApiRequest;
use App\Http\Resources\Api\V1\ReceitaResource;
use App\Models\Receita;
use App\Services\ResolvedorReferencias;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReceitaController extends Controller
{
    use RespondeJson;

    public function __construct(private readonly ResolvedorReferencias $resolvedor)
    {
    }

    public function store(StoreReceitaApiRequest $request): JsonResponse
    {
        $itens = $this->normalizarItens($request->validated());
        $avisos = [];

        try {
            $resultado = DB::transaction(function () use ($itens, &$avisos): array {
                $criados = [];
                $ignorados = [];
                $receitas = [];

                foreach ($itens as $indice => $item) {
                    if (! empty($item['referencia_externa'])) {
                        $existente = Receita::query()
                            ->where('referencia_externa', $item['referencia_externa'])
                            ->first();

                        if ($existente) {
                            $ignorados[] = [
                                'indice' => $indice,
                                'id' => $existente->id,
                                'referencia_externa' => $item['referencia_externa'],
                            ];
                            $avisos[] = "receita ja registada ({$item['referencia_externa']})";
                            $receitas[] = $existente->load($this->relacoes());

                            continue;
                        }
                    }

                    $receita = Receita::query()->create($this->payloadReceita($item));
                    $criados[] = $receita->id;
                    $receitas[] = $receita->load($this->relacoes());
                }

                return [
                    'criados' => $criados,
                    'ignorados' => $ignorados,
                    'receitas' => ReceitaResource::collection(collect($receitas))->resolve(),
                ];
            });
        } catch (ValidationException $exception) {
            return $this->erro422($exception->errors());
        }

        return $this->criado($resultado, $avisos);
    }

    private function normalizarItens(array $data): array
    {
        return array_values($data['receitas'] ?? [$data]);
    }

    private function payloadReceita(array $item): array
    {
        return [
            'descricao' => $item['descricao'],
            'tipo' => $item['tipo'],
            'valor' => $item['valor'],
            'data' => $item['data'],
            'referencia_externa' => $item['referencia_externa'] ?? null,
            'comprador_nome' => $item['comprador_nome'] ?? null,
            'documento' => $item['documento'] ?? null,
            'observacoes' => $item['observacoes'] ?? null,
            'campanha_id' => $this->resolverIdOpcional('campanha', $item),
            'cultura_id' => $this->resolverIdOpcional('cultura', $item),
            'parcela_id' => $this->resolverIdOpcional('parcela', $item),
            'colheita_id' => $this->resolverIdOpcional('colheita', $item),
            'lote_id' => $this->resolverIdOpcional('lote', $item),
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
            'cultura' => $this->resolvedor->resolverCultura($valor)->id,
            'parcela' => $this->resolvedor->resolverParcela($valor)->id,
            'colheita' => $this->resolvedor->resolverColheita($valor)->id,
            'lote' => $this->resolvedor->resolverLote($valor)->id,
        };
    }

    private function valorReferencia(mixed $referencia): int|string|null
    {
        if (! is_array($referencia)) {
            return $referencia;
        }

        foreach (['id', 'referencia_externa', 'codigo', 'numero_lote', 'nome'] as $chave) {
            if (array_key_exists($chave, $referencia) && $referencia[$chave] !== null && $referencia[$chave] !== '') {
                return $referencia[$chave];
            }
        }

        return null;
    }

    private function relacoes(): array
    {
        return ['campanha', 'cultura', 'parcela', 'colheita', 'lote'];
    }
}
