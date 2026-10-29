<?php

use App\Http\Controllers\ExportFileController;
use App\Http\Controllers\PhotoFileController;
use Illuminate\Support\Facades\Route;

/*
| L'interface de catalogage est servie par le panneau Filament, monté à la
| racine du domaine (voir App\Providers\Filament\AdminPanelProvider).
| Ce fichier ne contient que ce qui vit en dehors du panneau.
*/

Route::middleware('auth')->group(function (): void {
    // Les photos d'origine, en pleine résolution, ne sont jamais exposées en
    // statique : elles transitent par une route authentifiée.
    Route::get('/photos/{photo}/original', [PhotoFileController::class, 'show'])
        ->name('photos.original');

    Route::get('/photos/{photo}/telecharger', [PhotoFileController::class, 'download'])
        ->name('photos.download');

    // Remise d'un export préparé (voir ExportFileController).
    Route::get('/exports/{fichier}', [ExportFileController::class, 'telecharger'])
        ->middleware('signed')
        ->name('exports.telecharger');
});

// Page affichée par le service worker quand le réseau manque.
Route::view('/hors-ligne', 'offline')->name('offline');

// Présentation de l'application, ouverte à tous. Elle ne montre rien de la
// collection : ni fiche, ni photographie, ni nom de personne.
Route::view('/a-propos', 'a-propos')->name('a-propos');
