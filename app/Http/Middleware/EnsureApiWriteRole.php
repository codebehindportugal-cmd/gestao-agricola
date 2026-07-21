<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiWriteRole
{
    private const ROLES_ESCRITA = [
        'admin',
        'gestor_agricola',
        'operador',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->hasRole(self::ROLES_ESCRITA)) {
            return response()->json([
                'sucesso' => false,
                'dados' => null,
                'avisos' => [],
                'erros' => [
                    'autorizacao' => ['Utilizador sem permissao para escrever na API de ingestao.'],
                ],
            ], 403);
        }

        return $next($request);
    }
}
