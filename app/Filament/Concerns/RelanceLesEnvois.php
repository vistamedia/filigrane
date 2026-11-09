<?php

namespace App\Filament\Concerns;

/**
 * Pour les composants qui reçoivent des photographies.
 *
 * Quand un envoi échoue sans motif (refus passager de l'hébergeur, coupure
 * réseau), Livewire affiche sous le champ un message générique qui ne nomme
 * aucun fichier, et qui resterait affiché après une relance réussie. La page
 * relance elle-même l'envoi et nomme les fichiers qui échouent encore
 * (resources/views/filament/envois.blade.php) : ce message-là n'a plus lieu
 * d'être.
 *
 * Un refus motivé, lui, garde son message : un fichier trop lourd, par
 * exemple, le serait encore à la relance.
 */
trait RelanceLesEnvois
{
    public function _uploadErrored($name, $errorsInJson, $isMultiple)
    {
        if ($errorsInJson === null) {
            $this->dispatch('upload:errored', name: $name)->self();

            return;
        }

        parent::_uploadErrored($name, $errorsInJson, $isMultiple);
    }
}
