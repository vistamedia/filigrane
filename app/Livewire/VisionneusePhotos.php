<?php

namespace App\Livewire;

use App\Models\Drawing;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Le panneau d'images de la fiche.
 *
 * C'est un composant à part entière, et non un simple morceau de la vue du
 * formulaire, pour une raison précise : quand on ajoute ou supprime une
 * photographie, il doit se redessiner seul. Rafraîchir le formulaire entier
 * effacerait la description en cours de frappe.
 */
class VisionneusePhotos extends Component
{
    public ?Drawing $dessin = null;

    public function mount(?Drawing $dessin = null): void
    {
        $this->dessin = $dessin;
    }

    #[On('photographies-modifiees')]
    public function rafraichir(): void
    {
        $this->dessin?->refresh();
    }

    public function render()
    {
        return view('filament.dessins.visionneuse', [
            'dessin' => $this->dessin,
        ]);
    }
}
