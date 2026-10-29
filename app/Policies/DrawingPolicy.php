<?php

namespace App\Policies;

use App\Models\Drawing;
use App\Models\User;

/**
 * Les deux comptes travaillent sur le même fonds : tout le monde consulte,
 * crée et décrit. Seule la suppression définitive est réservée, parce qu'elle
 * est la seule action irréversible de l'application.
 */
class DrawingPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Drawing $drawing): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Drawing $drawing): bool
    {
        return true;
    }

    /** Mise à la corbeille : réversible, donc ouverte à tous. */
    public function delete(User $user, Drawing $drawing): bool
    {
        return true;
    }

    public function restore(User $user, Drawing $drawing): bool
    {
        return true;
    }

    /** Suppression définitive : seul l'administrateur, et sans filet. */
    public function forceDelete(User $user, Drawing $drawing): bool
    {
        return $user->isAdmin();
    }
}
