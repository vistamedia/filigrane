<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, User $cible): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, User $cible): bool
    {
        return $user->isAdmin();
    }

    /** On ne supprime pas son propre compte : le panneau se refermerait. */
    public function delete(User $user, User $cible): bool
    {
        return $user->isAdmin() && $user->id !== $cible->id;
    }
}
