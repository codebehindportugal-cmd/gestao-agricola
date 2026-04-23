<?php

namespace App\Policies;

use App\Models\Maquina;
use App\Models\User;

class MaquinaPolicy
{
    /**
     * Determine whether the user can view any maquinas.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the maquina.
     */
    public function view(User $user, Maquina $maquina): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create maquinas.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(['admin', 'gestor_agricola']);
    }

    /**
     * Determine whether the user can update the maquina.
     */
    public function update(User $user, Maquina $maquina): bool
    {
        return $user->hasRole(['admin', 'gestor_agricola']);
    }

    /**
     * Determine whether the user can delete the maquina.
     */
    public function delete(User $user, Maquina $maquina): bool
    {
        return $user->hasRole('admin');
    }
}
