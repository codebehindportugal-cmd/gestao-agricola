<?php

namespace App\Policies;

use App\Models\Operacao;
use App\Models\User;

class OperacaoPolicy
{
    /**
     * Determine whether the user can view any operacoes.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the operacao.
     */
    public function view(User $user, Operacao $operacao): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create operacoes.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(['admin', 'gestor_agricola', 'operador']);
    }

    /**
     * Determine whether the user can update the operacao.
     */
    public function update(User $user, Operacao $operacao): bool
    {
        // Operador só pode atualizar suas operações
        if ($user->hasRole('operador')) {
            return $operacao->operador_id === $user->id;
        }

        return $user->hasRole(['admin', 'gestor_agricola']);
    }

    /**
     * Determine whether the user can delete the operacao.
     */
    public function delete(User $user, Operacao $operacao): bool
    {
        return $user->hasRole(['admin', 'gestor_agricola']);
    }

    /**
     * Determine whether the user can restore the operacao.
     */
    public function restore(User $user, Operacao $operacao): bool
    {
        return $user->hasRole('admin');
    }
}
