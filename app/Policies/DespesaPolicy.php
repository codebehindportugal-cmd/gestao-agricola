<?php

namespace App\Policies;

use App\Models\Despesa;
use App\Models\User;

class DespesaPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Despesa $despesa): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['admin', 'gestor_agricola', 'operador', 'armazem']);
    }

    public function update(User $user, Despesa $despesa): bool
    {
        return $user->hasRole(['admin', 'gestor_agricola']);
    }

    public function delete(User $user, Despesa $despesa): bool
    {
        return $user->hasRole(['admin', 'gestor_agricola']);
    }
}
