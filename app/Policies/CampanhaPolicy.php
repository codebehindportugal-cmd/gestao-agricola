<?php

namespace App\Policies;

use App\Models\Campanha;
use App\Models\User;

class CampanhaPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Campanha $campanha): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['admin', 'gestor_agricola']);
    }

    public function update(User $user, Campanha $campanha): bool
    {
        return $user->hasRole(['admin', 'gestor_agricola']);
    }

    public function delete(User $user, Campanha $campanha): bool
    {
        return $user->hasRole('admin');
    }
}
