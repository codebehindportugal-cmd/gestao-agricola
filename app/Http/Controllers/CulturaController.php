<?php

namespace App\Http\Controllers;

use App\Models\Cultura;
use App\Http\Requests\StoreCulturaRequest;
use App\Http\Requests\UpdateCulturaRequest;
use Illuminate\Http\JsonResponse;

class CulturaController extends Controller
{
    /**
     * Listar culturas com filtros
     */
    public function index(): JsonResponse
    {
        $culturas = Cultura::with('parcela', 'campanhas')
            ->when(request('parcela_id'), function ($query) {
                $query->where('parcela_id', request('parcela_id'));
            })
            ->when(request('tipo'), function ($query) {
                $query->where('tipo', request('tipo'));
            })
            ->when(request('estado'), function ($query) {
                $query->where('estado', request('estado'));
            })
            ->paginate(15);

        return response()->json($culturas);
    }

    /**
     * Armazenar nova cultura
     */
    public function store(StoreCulturaRequest $request): JsonResponse
    {
        $cultura = Cultura::create($request->validated());

        return response()->json([
            'message' => 'Cultura criada com sucesso',
            'data' => $cultura->load('parcela'),
        ], 201);
    }

    /**
     * Mostrar cultura específica
     */
    public function show(Cultura $cultura): JsonResponse
    {
        $cultura->load([
            'parcela.terreno',
            'campanhas',
            'operacoes',
            'colheitas.lotes',
            'custos',
        ]);

        return response()->json($cultura);
    }

    /**
     * Atualizar cultura
     */
    public function update(UpdateCulturaRequest $request, Cultura $cultura): JsonResponse
    {
        $cultura->update($request->validated());

        return response()->json([
            'message' => 'Cultura atualizada com sucesso',
            'data' => $cultura,
        ]);
    }

    /**
     * Eliminar cultura
     */
    public function destroy(Cultura $cultura): JsonResponse
    {
        $cultura->delete();

        return response()->json([
            'message' => 'Cultura eliminada com sucesso',
        ]);
    }
}
