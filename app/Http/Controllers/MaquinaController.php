<?php

namespace App\Http\Controllers;

use App\Models\Maquina;
use App\Http\Requests\StoreMaquinaRequest;
use App\Http\Requests\UpdateMaquinaRequest;
use Illuminate\Http\JsonResponse;

class MaquinaController extends Controller
{
    /**
     * Listar máquinas
     */
    public function index(): JsonResponse
    {
        $maquinas = Maquina::with('alfaias', 'manutencoes')
            ->when(request('tipo'), function ($query) {
                $query->where('tipo', request('tipo'));
            })
            ->when(request('estado'), function ($query) {
                $query->where('estado', request('estado'));
            })
            ->paginate(15);

        return response()->json($maquinas);
    }

    /**
     * Armazenar nova máquina
     */
    public function store(StoreMaquinaRequest $request): JsonResponse
    {
        $maquina = Maquina::create($request->validated());

        return response()->json([
            'message' => 'Máquina criada com sucesso',
            'data' => $maquina,
        ], 201);
    }

    /**
     * Mostrar máquina específica
     */
    public function show(Maquina $maquina): JsonResponse
    {
        $maquina->load([
            'alfaias',
            'operacoes.parcela',
            'manutencoes',
            'custos',
        ]);

        return response()->json($maquina);
    }

    /**
     * Atualizar máquina
     */
    public function update(UpdateMaquinaRequest $request, Maquina $maquina): JsonResponse
    {
        $maquina->update($request->validated());

        return response()->json([
            'message' => 'Máquina atualizada com sucesso',
            'data' => $maquina,
        ]);
    }

    /**
     * Eliminar máquina
     */
    public function destroy(Maquina $maquina): JsonResponse
    {
        $maquina->delete();

        return response()->json([
            'message' => 'Máquina eliminada com sucesso',
        ]);
    }

    /**
     * Listar tipos de máquinas disponíveis
     */
    public function tipos(): JsonResponse
    {
        $tipos = [
            'trator',
            'ceifeira',
            'distribuidor',
            'pulverizador',
            'carregador',
            'reboque',
            'charrua',
            'grade de discos',
            'semeadora',
            'distribuidor de adubo',
        ];

        return response()->json([
            'data' => $tipos,
        ]);
    }
}
