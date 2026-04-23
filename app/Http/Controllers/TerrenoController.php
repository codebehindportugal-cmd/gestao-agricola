<?php

namespace App\Http\Controllers;

use App\Models\Terreno;
use App\Http\Requests\StoreTerrenoRequest;
use App\Http\Requests\UpdateTerrenoRequest;
use Illuminate\Http\JsonResponse;

class TerrenoController extends Controller
{
    /**
     * Listar todos os terrenos
     */
    public function index(): JsonResponse
    {
        $terrenos = Terreno::with('parcelas')
            ->paginate(15);

        return response()->json($terrenos);
    }

    /**
     * Armazenar novo terreno
     */
    public function store(StoreTerrenoRequest $request): JsonResponse
    {
        $terreno = Terreno::create($request->validated());

        return response()->json([
            'message' => 'Terreno criado com sucesso',
            'data' => $terreno,
        ], 201);
    }

    /**
     * Mostrar terreno específico
     */
    public function show(Terreno $terreno): JsonResponse
    {
        $terreno->load(['parcelas.culturas', 'custos']);

        return response()->json($terreno);
    }

    /**
     * Atualizar terreno
     */
    public function update(UpdateTerrenoRequest $request, Terreno $terreno): JsonResponse
    {
        $terreno->update($request->validated());

        return response()->json([
            'message' => 'Terreno atualizado com sucesso',
            'data' => $terreno,
        ]);
    }

    /**
     * Eliminar terreno
     */
    public function destroy(Terreno $terreno): JsonResponse
    {
        $terreno->delete();

        return response()->json([
            'message' => 'Terreno eliminado com sucesso',
        ]);
    }

    /**
     * Restaurar terreno eliminado
     */
    public function restore(int $id): JsonResponse
    {
        $terreno = Terreno::withTrashed()->findOrFail($id);
        $terreno->restore();

        return response()->json([
            'message' => 'Terreno restaurado com sucesso',
            'data' => $terreno,
        ]);
    }
}
