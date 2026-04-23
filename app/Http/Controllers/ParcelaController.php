<?php

namespace App\Http\Controllers;

use App\Models\Parcela;
use App\Http\Requests\StoreParcelaRequest;
use App\Http\Requests\UpdateParcelaRequest;
use Illuminate\Http\JsonResponse;

class ParcelaController extends Controller
{
    /**
     * Listar parcelas com filtros
     */
    public function index(): JsonResponse
    {
        $parcelas = Parcela::with('terreno', 'culturas')
            ->when(request('terreno_id'), function ($query) {
                $query->where('terreno_id', request('terreno_id'));
            })
            ->when(request('estado'), function ($query) {
                $query->where('estado', request('estado'));
            })
            ->paginate(15);

        return response()->json($parcelas);
    }

    /**
     * Armazenar nova parcela
     */
    public function store(StoreParcelaRequest $request): JsonResponse
    {
        $parcela = Parcela::create($request->validated());

        return response()->json([
            'message' => 'Parcela criada com sucesso',
            'data' => $parcela->load('terreno'),
        ], 201);
    }

    /**
     * Mostrar parcela específica
     */
    public function show(Parcela $parcela): JsonResponse
    {
        $parcela->load([
            'terreno',
            'culturas.campanhas',
            'operacoes',
            'colheitas',
            'custos',
        ]);

        return response()->json($parcela);
    }

    /**
     * Atualizar parcela
     */
    public function update(UpdateParcelaRequest $request, Parcela $parcela): JsonResponse
    {
        $parcela->update($request->validated());

        return response()->json([
            'message' => 'Parcela atualizada com sucesso',
            'data' => $parcela,
        ]);
    }

    /**
     * Eliminar parcela
     */
    public function destroy(Parcela $parcela): JsonResponse
    {
        $parcela->delete();

        return response()->json([
            'message' => 'Parcela eliminada com sucesso',
        ]);
    }
}
