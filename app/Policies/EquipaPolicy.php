<?php

namespace App\Policies;

use App\Models\Equipa;
use App\Models\User;

class EquipaPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Equipa $equipa): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['admin', 'gestor_agricola']);
    }

    public function update(User $user, Equipa $equipa): bool
    {
        return $user->hasRole(['admin', 'gestor_agricola']);
    }

    public function delete(User $user, Equipa $equipa): bool
    {
        return $user->hasRole('admin');
    }
}
