<?php

namespace App\Support;

use App\Models\User;
use InvalidArgumentException;

/**
 * A chave da API de ingestao (/api/v1) que cada pessoa gera no seu perfil.
 *
 * 29/09/2026: ate aqui so havia o `agri:emitir-token`, a correr no servidor.
 * O botao do perfil usa esta classe e fica com as mesmas regras do comando:
 * so roles com escrita, e as abilities de escrita todas menos `casa:write` —
 * essa e so do Home Assistant, que tem token proprio com outro nome e por
 * isso nao e tocado aqui.
 *
 * Uma chave por pessoa: gerar outra revoga as anteriores com o mesmo nome,
 * para nao se ir acumulando chaves esquecidas com acesso aos dados.
 */
class TokenApi
{
    public const NOME = 'api-ingestao';

    public const ROLES_ESCRITA = ['admin', 'gestor_agricola', 'operador'];

    public const ABILITIES = [
        'custos:write',
        'aplicacoes:write',
        'colheitas:write',
        'receitas:write',
        'faturas:write',
        'trabalhos:write',
        'compromissos:write',
        'manutencoes:write',
        'campanhas:write',
        'stock:write',
        'cadastro:write',
    ];

    public static function podeTer(User $utilizador): bool
    {
        return $utilizador->hasRole(self::ROLES_ESCRITA);
    }

    /** @return array{token: string, revogados: int} */
    public static function emitir(User $utilizador): array
    {
        if (! self::podeTer($utilizador)) {
            throw new InvalidArgumentException('Utilizador sem role autorizado para escrever na API.');
        }

        $revogados = $utilizador->tokens()->where('name', self::NOME)->delete();

        return [
            'token' => $utilizador->createToken(self::NOME, self::ABILITIES)->plainTextToken,
            'revogados' => (int) $revogados,
        ];
    }

    public static function revogar(User $utilizador): int
    {
        return (int) $utilizador->tokens()->where('name', self::NOME)->delete();
    }

    /** @return array{existe: bool, criada_em: ?string, ultimo_uso: ?string} */
    public static function estado(User $utilizador): array
    {
        $token = $utilizador->tokens()->where('name', self::NOME)->latest('id')->first();

        return [
            'existe' => (bool) $token,
            'criada_em' => $token?->created_at?->format('d/m/Y H:i'),
            'ultimo_uso' => $token?->last_used_at?->format('d/m/Y H:i'),
        ];
    }
}
