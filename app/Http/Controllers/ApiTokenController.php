<?php

namespace App\Http\Controllers;

use App\Support\TokenApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Botao "Gerar chave da API" do perfil (29/09/2026).
 *
 * Responde em JSON e nao por redirect: a chave so existe em claro neste
 * pedido, e assim vai directa para o ecra sem passar pela sessao.
 */
class ApiTokenController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        if (! TokenApi::podeTer($request->user())) {
            return response()->json([
                'message' => 'A tua conta nao tem permissao para escrever na API.',
            ], 403);
        }

        ['token' => $token, 'revogados' => $revogados] = TokenApi::emitir($request->user());

        return response()->json([
            'token' => $token,
            'revogados' => $revogados,
            'estado' => TokenApi::estado($request->user()),
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        TokenApi::revogar($request->user());

        return response()->json(['estado' => TokenApi::estado($request->user())]);
    }
}
