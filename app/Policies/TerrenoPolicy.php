<?php

namespace App\Policies;

use App\Models\Terreno;
use App\Models\User;

class TerrenoPolicy
{
    /**
     * Determine whether the user can view any terrenos.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the terreno.
     */
    public function view(User $user, Terreno $terreno): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create terrenos.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('terrenos.create') || $user->hasRole(['admin', 'gestor_agricola']);
    }

    /**
     * Determine whether the user can update the terreno.
     */
    public function update(User $user, Terreno $terreno): bool
    {
        return $user->hasPermission('terrenos.edit') || $user->hasRole(['admin', 'gestor_agricola']);
    }

    /**
     * Determine whether the user can delete the terreno.
     */
    public function delete(User $user, Terreno $terreno): bool
    {
        return $user->hasPermission('terrenos.delete') || $user->hasRole('admin');
    }

    /**
     * Determine whether the user can restore the terreno.
     */
    public function restore(User $user, Terreno $terreno): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can permanently delete the terreno.
     */
    public function forceDelete(User $user, Terreno $terreno): bool
    {
        return $user->hasRole('admin');
    }
}
