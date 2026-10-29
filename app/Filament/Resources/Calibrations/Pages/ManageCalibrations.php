<?php

namespace App\Filament\Resources\Calibrations\Pages;

use App\Filament\Resources\Calibrations\CalibrationResource;
use Filament\Resources\Pages\ManageRecords;

/**
 * Pas de bouton « créer » : un profil d'échelle naît forcément d'une mesure
 * faite sur une photographie, jamais d'une saisie de chiffres à la main.
 */
class ManageCalibrations extends ManageRecords
{
    protected static string $resource = CalibrationResource::class;

    protected static ?string $title = 'Échelles';

    protected function getHeaderActions(): array
    {
        return [];
    }
}
