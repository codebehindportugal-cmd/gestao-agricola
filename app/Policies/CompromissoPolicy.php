<?php

namespace App\Policies;

use App\Models\Compromisso;
use App\Models\User;

class CompromissoPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Compromisso $compromisso): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['admin', 'gestor_agricola', 'operador']);
    }

    public function update(User $user, ?Compromisso $compromisso = null): bool
    {
        return $user->hasRole(['admin', 'gestor_agricola', 'operador']);
    }

    public function delete(User $user, ?Compromisso $compromisso = null): bool
    {
        return $user->hasRole(['admin', 'gestor_agricola']);
    }
}
