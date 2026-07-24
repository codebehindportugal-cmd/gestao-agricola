<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\RespondeJson;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreColheitaApiRequest;
use App\Http\Resources\Api\V1\ColheitaResource;
use App\Models\Colheita;
use App\Models\Lote;
use App\Services\ResolvedorReferencias;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ColheitaController extends Controller
{
    use RespondeJson;

    public function __construct(private readonly ResolvedorReferencias $resolvedor)
    {
    }

    public function store(StoreColheitaApiRequest $request): JsonResponse
    {
        $data = $request->validated();
        $avisos = [];

        if (! empty($data['referencia_externa'])) {
            $existente = Colheita::query()
                ->where('referencia_externa', $data['referencia_externa'])
                ->first();

            if ($existente) {
                $avisos[] = "colheita ja registada ({$data['referencia_externa']})";

                return $this->criado([
                    'colheita' => ColheitaResource::make($existente->load($this->relacoes()))->resolve(),
                ], $avisos);
            }
        }

        try {
            $colheita = DB::transaction(function () use ($data): Colheita {
                $campanha = $this->resolvedor->resolverCampanha($this->valorReferencia($data['campanha']));
                $cultura = $this->resolvedor->resolverCultura($this->valorReferencia($data['cultura']));
                $parcela = $this->resolverParcela($data['parcela'] ?? null, (int) $cultura->parcela_id);

                $colheita = Colheita::query()->create([
                    'campanha_id' => $campanha->id,
                    'cultura_id' => $cultura->id,
                    'parcela_id' => $parcela->id,
                    'data_colheita' => $data['data'],
                    'quantidade_total' => $data['quantidade_total'],
                    'unidade_medida' => $data['unidade_medida'] ?? $data['unidade'] ?? 'kg',
                    'qualidade' => $data['qualidade'] ?? 'comercial',
                    'referencia_externa' => $data['referencia_externa'] ?? null,
                    'observacoes' => $data['observacoes'] ?? null,
                ]);

                foreach ($data['lotes'] as $linha) {
                    $terreno = $this->resolvedor->resolverTerreno($this->valorReferencia($linha['terreno']));
                    $dataColheita = $linha['data_colheita'] ?? $data['data'];

                    Lote::query()->create([
                        'colheita_id' => $colheita->id,
                        'terreno_id' => $terreno->id,
                        'numero_lote' => $this->codigoLote($linha['codigo'] ?? null, $campanha->ano),
                        'quantidade' => $linha['quantidade'],
                        'unidade_medida' => $linha['unidade'],
                        'data_colheita' => $dataColheita,
                        'data_entrada' => $dataColheita,
                        'localizacao_armazem' => $linha['localizacao_armazem'] ?? null,
                        'observacoes' => $linha['observacoes'] ?? null,
                    ]);
                }

                return $colheita->load($this->relacoes());
            });
        } catch (ValidationException $exception) {
            return $this->erro422($exception->errors());
        }

        return $this->criado([
            'colheita' => ColheitaResource::make($colheita)->resolve(),
        ]);
    }

    private function resolverParcela(mixed $referencia, int $parcelaIdDaCultura): mixed
    {
        if ($referencia !== null && $referencia !== '') {
            return $this->resolvedor->resolverParcela($this->valorReferencia($referencia));
        }

        return $this->resolvedor->resolverParcela($parcelaIdDaCultura);
    }

    private function codigoLote(?string $codigo, int|string|null $ano): string
    {
        if ($codigo !== null && trim($codigo) !== '') {
            return $codigo;
        }

        return $this->gerarCodigoLote($ano);
    }

    private function gerarCodigoLote(int|string|null $ano): string
    {
        $base = 'LOTE-'.($ano ?: now()->year).'-';
        $sequencia = Lote::query()
            ->where('numero_lote', 'like', $base.'%')
            ->count() + 1;

        return $base.str_pad((string) $sequencia, 3, '0', STR_PAD_LEFT);
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
        return ['campanha', 'cultura', 'parcela', 'lotes.terreno'];
    }
}
