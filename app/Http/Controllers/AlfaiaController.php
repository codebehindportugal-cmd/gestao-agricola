<?php

namespace App\Http\Controllers;

use App\Models\Alfaia;
use App\Http\Requests\StoreAlfaiaRequest;
use App\Http\Requests\UpdateAlfaiaRequest;
use Illuminate\Http\JsonResponse;

class AlfaiaController extends Controller
{
    /**
     * Listar alfaias
     */
    public function index(): JsonResponse
    {
        $alfaias = Alfaia::with('maquina')
            ->when(request('tipo'), function ($query) {
                $query->where('tipo', request('tipo'));
            })
            ->when(request('maquina_id'), function ($query) {
                $query->where('maquina_id', request('maquina_id'));
            })
            ->when(request('estado'), function ($query) {
                $query->where('estado', request('estado'));
            })
            ->paginate(15);

        return response()->json($alfaias);
    }

    /**
     * Armazenar nova alfaia
     */
    public function store(StoreAlfaiaRequest $request): JsonResponse
    {
        $alfaia = Alfaia::create($request->validated());

        return response()->json([
            'message' => 'Alfaia criada com sucesso',
            'data' => $alfaia->load('maquina'),
        ], 201);
    }

    /**
     * Mostrar alfaia específica
     */
    public function show(Alfaia $alfaia): JsonResponse
    {
        $alfaia->load([
            'maquina',
            'operacoes.parcela',
        ]);

        return response()->json($alfaia);
    }

    /**
     * Atualizar alfaia
     */
    public function update(UpdateAlfaiaRequest $request, Alfaia $alfaia): JsonResponse
    {
        $alfaia->update($request->validated());

        return response()->json([
            'message' => 'Alfaia atualizada com sucesso',
            'data' => $alfaia,
        ]);
    }

    /**
     * Eliminar alfaia
     */
    public function destroy(Alfaia $alfaia): JsonResponse
    {
        $alfaia->delete();

        return response()->json([
            'message' => 'Alfaia eliminada com sucesso',
        ]);
    }

    /**
     * Listar tipos de alfaias disponíveis
     */
    public function tipos(): JsonResponse
    {
        $tipos = [
            'charrua',
            'grade de discos',
            'semeadora',
            'distribuidor de adubo',
            'pulverizador',
            'carregador',
            'destroçador',
            'niveladora',
            'grade de dentes',
            'cultivador',
        ];

        return response()->json([
            'data' => $tipos,
        ]);
    }
}
