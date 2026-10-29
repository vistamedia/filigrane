<?php

namespace App\Policies;

use App\Models\Calibration;
use App\Models\User;

/**
 * Les profils d'échelle relèvent de la prise de vue, pas du catalogage :
 * ils restent entre les mains de l'administrateur.
 */
class CalibrationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Calibration $calibration): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Calibration $calibration): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Calibration $calibration): bool
    {
        return $user->isAdmin();
    }
}
