<?php

namespace App\Policies;

use App\Models\Photo;
use App\Models\User;

/**
 * Les photographies suivent le sort de leur fiche : qui peut décrire un
 * dessin peut en gérer les clichés. La suppression d'une photo efface aussi
 * l'original, elle n'est donc pas réversible.
 */
class PhotoPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Photo $photo): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Photo $photo): bool
    {
        return true;
    }

    public function delete(User $user, Photo $photo): bool
    {
        return true;
    }
}
