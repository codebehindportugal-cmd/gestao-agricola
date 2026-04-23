<?php

namespace App\Policies;

use App\Models\Funcionario;
use App\Models\User;

class FuncionarioPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Funcionario $funcionario): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['admin', 'gestor_agricola']);
    }

    public function update(User $user, Funcionario $funcionario): bool
    {
        return $user->hasRole(['admin', 'gestor_agricola']);
    }

    public function delete(User $user, Funcionario $funcionario): bool
    {
        return $user->hasRole('admin');
    }
}
