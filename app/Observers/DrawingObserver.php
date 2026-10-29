<?php

namespace App\Observers;

use App\Enums\DrawingStatus;
use App\Models\Drawing;
use App\Services\InventoryNumberGenerator;
use Illuminate\Support\Facades\Auth;

class DrawingObserver
{
    public function __construct(private InventoryNumberGenerator $numbers) {}

    public function creating(Drawing $drawing): void
    {
        $drawing->inventory_number ??= $this->numbers->next();
        $drawing->created_by_id ??= Auth::id();
        $drawing->updated_by_id ??= Auth::id();
    }

    /**
     * La clé étrangère des photographies est en cascade : sans cette méthode,
     * une suppression définitive effacerait les lignes sans passer par
     * Eloquent, et les fichiers image resteraient orphelins sur le disque.
     *
     * On vide d'abord l'illustration pour que l'observateur des photographies
     * n'aille pas chercher une remplaçante à chacune d'elles.
     */
    public function forceDeleting(Drawing $drawing): void
    {
        $drawing->forceFill(['cover_photo_id' => null])->saveQuietly();

        foreach ($drawing->photos()->get() as $photo) {
            $photo->delete();
        }
    }

    public function updating(Drawing $drawing): void
    {
        $drawing->updated_by_id = Auth::id() ?? $drawing->updated_by_id;

        // On horodate le moment où la fiche est déclarée décrite, et on efface
        // cette date si elle repasse en cours.
        if ($drawing->isDirty('status')) {
            $drawing->described_at = $drawing->status === DrawingStatus::Described ? now() : null;
        }
    }
}
