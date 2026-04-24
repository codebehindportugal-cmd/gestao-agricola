<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOperacaoRequest;
use App\Http\Requests\UpdateOperacaoRequest;
use App\Models\Operacao;
use App\Support\OperacaoDuration;
use Illuminate\Http\JsonResponse;

class OperacaoController extends Controller
{
    public function index(): JsonResponse
    {
        $operacoes = Operacao::with('parcela', 'cultura', 'maquina', 'alfaia', 'operador', 'produtos')
            ->when(request('parcela_id'), fn ($query) => $query->where('parcela_id', request('parcela_id')))
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
        unset($data['produtos'], $data['parcela_ids']);

        $operacoes = collect($parcelaIds)->map(function (int $parcelaId) use ($data, $produtos, $parcelaIds) {
            $operacao = Operacao::create([
                ...$data,
                'parcela_id' => $parcelaId,
                'cultura_id' => count($parcelaIds) > 1 ? null : ($data['cultura_id'] ?? null),
                'campanha_id' => count($parcelaIds) > 1 ? null : ($data['campanha_id'] ?? null),
            ]);

            if (count($produtos)) {
                $operacao->produtos()->sync($this->formatProdutos($produtos));
            }

            return $operacao->load('parcela', 'cultura', 'maquina', 'produtos');
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

        $operacao->update($data);
        $operacao->produtos()->sync($this->formatProdutos($produtos));

        return response()->json([
            'message' => 'Operação atualizada com sucesso',
            'data' => $operacao->load('parcela', 'cultura', 'maquina', 'produtos'),
        ]);
    }

    public function destroy(Operacao $operacao): JsonResponse
    {
        $operacao->delete();

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

        return (float) $value;
    }

    private function normalizePayload(array $data): array
    {
        $data['duracao_horas'] = OperacaoDuration::calculateFromStrings(
            $data['data_hora_inicio'] ?? null,
            $data['data_hora_fim'] ?? null,
        );

        return $data;
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
}
