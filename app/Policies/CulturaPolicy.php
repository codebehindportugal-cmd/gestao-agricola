<?php

namespace App\Policies;

use App\Models\Cultura;
use App\Models\User;

class CulturaPolicy
{
    /**
     * Determine whether the user can view any culturas.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the cultura.
     */
    public function view(User $user, Cultura $cultura): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create culturas.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(['admin', 'gestor_agricola']);
    }

    /**
     * Determine whether the user can update the cultura.
     */
    public function update(User $user, Cultura $cultura): bool
    {
        return $user->hasRole(['admin', 'gestor_agricola']);
    }

    /**
     * Determine whether the user can delete the cultura.
     */
    public function delete(User $user, Cultura $cultura): bool
    {
        return $user->hasRole('admin');
    }
}
