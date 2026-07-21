<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;

trait RespondeJson
{
    protected function ok(array $dados = [], array $avisos = [], int $status = 200): JsonResponse
    {
        return response()->json([
            'sucesso' => true,
            'dados' => $dados,
            'avisos' => $avisos,
            'erros' => [],
        ], $status);
    }

    protected function criado(array $dados = [], array $avisos = []): JsonResponse
    {
        return $this->ok($dados, $avisos, 201);
    }

    protected function erro422(array $erros, array $avisos = []): JsonResponse
    {
        return response()->json([
            'sucesso' => false,
            'dados' => null,
            'avisos' => $avisos,
            'erros' => $erros,
        ], 422);
    }
}
