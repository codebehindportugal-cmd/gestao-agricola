<?php

namespace App\Policies;

use App\Models\Alfaia;
use App\Models\User;

class AlfaiaPolicy
{
    /**
     * Determine whether the user can view any alfaias.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the alfaia.
     */
    public function view(User $user, Alfaia $alfaia): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create alfaias.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(['admin', 'gestor_agricola']);
    }

    /**
     * Determine whether the user can update the alfaia.
     */
    public function update(User $user, Alfaia $alfaia): bool
    {
        return $user->hasRole(['admin', 'gestor_agricola']);
    }

    /**
     * Determine whether the user can delete the alfaia.
     */
    public function delete(User $user, Alfaia $alfaia): bool
    {
        return $user->hasRole('admin');
    }
}
