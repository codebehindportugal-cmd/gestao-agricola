<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOperacaoRequest;
use App\Http\Requests\UpdateOperacaoRequest;
use App\Models\Cultura;
use App\Models\Maquina;
use App\Models\Operacao;
use App\Models\Parcela;
use App\Support\OperacaoDuration;
use App\Support\StockConsumption;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class OperacaoController extends Controller
{
    public function index(): JsonResponse
    {
        $operacoes = Operacao::with('parcela', 'cultura', 'maquina', 'alfaia', 'operador', 'produtos')
            ->when(request('parcela_id'), fn ($query) => $query->where('parcela_id', request('parcela_id')))
            ->when(request('cultura_id'), function ($query, $culturaId) {
                $cultura = Cultura::query()->find($culturaId, ['id', 'nome']);

                $query->where(function ($subQuery) use ($culturaId, $cultura) {
                    $subQuery
                        ->where('cultura_id', $culturaId)
                        ->orWhereHas('parcela.culturas', fn ($culturaQuery) => $culturaQuery->where('id', $culturaId));

                    if ($cultura?->nome) {
                        $subQuery
                            ->orWhereHas('cultura', fn ($culturaQuery) => $culturaQuery->where('nome', $cultura->nome))
                            ->orWhereHas('parcela.culturas', fn ($culturaQuery) => $culturaQuery->where('nome', $cultura->nome));
                    }
                });
            })
            ->when(request('tipo'), fn ($query) => $query->where('tipo', request('tipo')))
            ->when(request('estado'), fn ($query) => $query->where('estado', request('estado')))
            ->when(request('data_inicio'), fn ($query) => $query->whereDate('data_hora_inicio', '>=', request('data_inicio')))
            ->when(request('data_fim'), fn ($query) => $query->whereDate('data_hora_inicio', '<=', request('data_fim')))
            ->orderBy('data_hora_inicio', 'desc')
            ->paginate(15);

        return response()->json($operacoes);
    }

    public function store(StoreOperacaoRequest $request): JsonResponse
    {
        $data = $this->normalizePayload($request->validated());
        $produtos = $data['produtos'] ?? [];
        $parcelaIds = $this->selectedParcelaIds($data);
        $isBulkOperation = count($parcelaIds) > 1;
        $distributionWeights = $isBulkOperation ? $this->parcelDistributionWeights($parcelaIds) : [];
        unset($data['produtos'], $data['parcela_ids']);

        $operacoes = DB::transaction(function () use ($data, $produtos, $parcelaIds, $isBulkOperation, $distributionWeights) {
            return collect($parcelaIds)->map(function (int $parcelaId) use ($data, $produtos, $parcelaIds, $isBulkOperation, $distributionWeights) {
                $operationData = $isBulkOperation
                    ? $this->distributedOperationPayload($data, $parcelaId, $distributionWeights)
                    : $data;

                $operacao = Operacao::create([
                    ...$operationData,
                    'parcela_id' => $parcelaId,
                    'cultura_id' => $this->culturaIdForOperationParcela($parcelaId, $data['cultura_id'] ?? null),
                    'campanha_id' => count($parcelaIds) > 1 ? null : ($data['campanha_id'] ?? null),
                ]);

                $formattedProdutos = $this->formatProdutos($produtos);

                if (count($formattedProdutos)) {
                    $operacao->produtos()->sync($formattedProdutos);
                }

                StockConsumption::syncOperation($operacao->fresh(), [], $formattedProdutos);

                return $operacao->load('parcela', 'cultura', 'maquina', 'produtos');
            });
        });

        return response()->json([
            'message' => 'Operação criada com sucesso',
            'data' => $operacoes->count() === 1 ? $operacoes->first() : $operacoes,
        ], 201);
    }

    public function show(Operacao $operacao): JsonResponse
    {
        $operacao->load([
            'parcela',
            'cultura',
            'maquina',
            'alfaia',
            'operador',
            'produtos',
            'jornadas',
            'custos',
        ]);

        return response()->json($operacao);
    }

    public function update(UpdateOperacaoRequest $request, Operacao $operacao): JsonResponse
    {
        $data = $this->normalizePayload($request->validated());
        $produtos = $data['produtos'] ?? [];
        unset($data['produtos'], $data['parcela_ids']);

        DB::transaction(function () use ($operacao, $data, $produtos) {
            $previousProducts = StockConsumption::productsFromOperation($operacao);
            $previousFuel = $operacao->combustivel_gasto_l === null ? null : (float) $operacao->combustivel_gasto_l;
            $formattedProdutos = $this->formatProdutos($produtos);

            $operacao->update($data);
            $operacao->produtos()->sync($formattedProdutos);
            StockConsumption::syncOperation($operacao->fresh(), $previousProducts, $formattedProdutos, $previousFuel);
        });

        return response()->json([
            'message' => 'Operação atualizada com sucesso',
            'data' => $operacao->load('parcela', 'cultura', 'maquina', 'produtos'),
        ]);
    }

    public function destroy(Operacao $operacao): JsonResponse
    {
        DB::transaction(function () use ($operacao) {
            StockConsumption::restoreOperation($operacao);
            $operacao->delete();
        });

        return response()->json([
            'message' => 'Operação eliminada com sucesso',
        ]);
    }

    public function tipos(): JsonResponse
    {
        return response()->json([
            'data' => [
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
            ],
        ]);
    }

    private function formatProdutos(array $produtos): array
    {
        return collect($produtos)
            ->filter(fn (array $produto) => !empty($produto['produto_id']))
            ->mapWithKeys(function (array $produto) {
                $quantidade = (float) ($produto['quantidade'] ?? 0);
                $custoUnitario = ($produto['custo_unitario'] ?? null) === '' || ($produto['custo_unitario'] ?? null) === null
                    ? null
                    : (float) $produto['custo_unitario'];

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
                        'custo_unitario' => $custoUnitario,
                        'custo_total' => $custoUnitario === null ? null : round($quantidade * $custoUnitario, 2),
                        'observacoes' => $produto['observacoes'] ?? null,
                    ],
                ];
            })
            ->all();
    }

    private function nullableFloat(mixed $value): ?float
    {
        if ($value === '' || $value === null) {
            return null;
        }

        return (float) str_replace(',', '.', (string) $value);
    }

    private function normalizePayload(array $data): array
    {
        $data['duracao_horas'] = $this->nullableFloat($data['duracao_horas'] ?? null)
            ?? OperacaoDuration::calculateFromStrings(
                $data['data_hora_inicio'] ?? null,
                $data['data_hora_fim'] ?? null,
            );
        $data['combustivel_gasto_l'] = $this->calculateFuelUsage($data);

        return $data;
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

    private function selectedParcelaIds(array $data): array
    {
        $parcelaIds = collect($data['parcela_ids'] ?? [])
            ->filter()
            ->map(fn ($id) => (int) $id);

        if ($parcelaIds->isEmpty() && ! empty($data['parcela_id'])) {
            $parcelaIds->push((int) $data['parcela_id']);
        }

        return $parcelaIds->unique()->values()->all();
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

    private function distributedOperationPayload(array $data, int $parcelaId, array $weights): array
    {
        $weight = (float) ($weights[$parcelaId] ?? 0);

        foreach (['duracao_horas', 'combustivel_gasto_l', 'distancia_km'] as $field) {
            if (($data[$field] ?? null) !== null) {
                $data[$field] = round((float) $data[$field] * $weight, 2);
            }
        }

        return $data;
    }

    private function culturaIdForOperationParcela(int $parcelaId, mixed $requestedCulturaId = null): ?int
    {
        $requestedCultura = empty($requestedCulturaId)
            ? null
            : Cultura::query()->find($requestedCulturaId, ['id', 'nome', 'parcela_id']);

        if ($requestedCultura && (int) $requestedCultura->parcela_id === $parcelaId) {
            return (int) $requestedCultura->id;
        }

        if ($requestedCultura?->nome) {
            $matchingCultureId = Cultura::query()
                ->where('parcela_id', $parcelaId)
                ->where('nome', $requestedCultura->nome)
                ->orderBy('id')
                ->value('id');

            return $matchingCultureId ? (int) $matchingCultureId : null;
        }

        $cultureId = Cultura::query()
            ->where('parcela_id', $parcelaId)
            ->orderBy('id')
            ->value('id');

        if ($cultureId) {
            return (int) $cultureId;
        }

        $parcela = Parcela::query()->find($parcelaId, ['id', 'nome', 'tipo_ocupacao']);

        if (! $parcela) {
            return null;
        }

        return (int) Cultura::query()->create([
            'parcela_id' => $parcela->id,
            'nome' => $parcela->nome,
            'tipo' => $parcela->tipo_ocupacao ?: 'outro',
            'data_plantacao' => now()->toDateString(),
            'estado' => 'em_crescimento',
        ])->id;
    }
}
