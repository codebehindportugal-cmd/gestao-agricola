<?php

namespace App\Policies;

use App\Models\Parcela;
use App\Models\User;

class ParcelaPolicy
{
    /**
     * Determine whether the user can view any parcelas.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the parcela.
     */
    public function view(User $user, Parcela $parcela): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create parcelas.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(['admin', 'gestor_agricola']);
    }

    /**
     * Determine whether the user can update the parcela.
     */
    public function update(User $user, Parcela $parcela): bool
    {
        return $user->hasRole(['admin', 'gestor_agricola']);
    }

    /**
     * Determine whether the user can delete the parcela.
     */
    public function delete(User $user, Parcela $parcela): bool
    {
        return $user->hasRole('admin');
    }
}
