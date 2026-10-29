<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Drawings\DrawingResource;
use App\Models\Drawing;
use App\Support\TexteRiche;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Lecture suivie de la collection décrite.
 *
 * Ce n'est pas un outil de travail : c'est le moment où l'on regarde ce qui a
 * été fait. Un dessin à la fois, en grand, avec son commentaire dessous —
 * l'inverse exact de la fiche de saisie, où l'image sert de référence au texte.
 *
 * Toutes les planches sont envoyées au navigateur en une fois, puis feuilletées
 * sans aller-retour avec le serveur : à deux ou trois cents fiches, la charge
 * est négligeable et le feuilletage devient instantané. Seules les images sont
 * chargées à la demande, voisines d'abord.
 */
class Diaporama extends Page
{
    protected string $view = 'filament.pages.diaporama';

    protected static ?string $slug = 'diaporama';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPlayCircle;

    protected static string|UnitEnum|null $navigationGroup = 'Collection';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Diaporama';

    /** Planches prêtes à l'affichage. */
    public array $planches = [];

    /** Rang de la planche ouverte au chargement. */
    public int $depart = 0;

    public static function getNavigationLabel(): string
    {
        return 'Diaporama';
    }

    public function getSubheading(): ?string
    {
        $nombre = count($this->planches);

        return match (true) {
            $nombre === 0 => null,
            $nombre === 1 => 'Un dessin décrit.',
            default => "{$nombre} dessins décrits, dans l'ordre des numéros d'inventaire.",
        };
    }

    public function mount(): void
    {
        $this->planches = $this->rassembler();

        $demande = request()->integer('dessin');

        if ($demande) {
            $rang = array_search($demande, array_column($this->planches, 'id'), strict: true);
            $this->depart = $rang === false ? 0 : $rang;
        }
    }

    /**
     * Les fiches commentées qui ont une image à montrer. Une fiche décrite
     * sans photographie n'a pas sa place dans un diaporama.
     */
    private function rassembler(): array
    {
        return Drawing::query()
            ->commented()
            ->with(['coverPhoto', 'photos', 'tags'])
            ->orderBy('inventory_number')
            ->get()
            ->map(function (Drawing $dessin): ?array {
                $photo = $dessin->displayPhoto();

                if (! $photo?->webUrl() || TexteRiche::estVide($dessin->description)) {
                    return null;
                }

                return [
                    'id' => $dessin->id,
                    'cote' => $dessin->inventory_number,
                    'artiste' => $dessin->artist,
                    'periode' => $dessin->period,
                    'dimensions' => $dessin->dimensionsLabel(),
                    'motsCles' => $dessin->tags->pluck('name')->all(),
                    'description' => TexteRiche::nettoyer($dessin->description),
                    'note' => TexteRiche::nettoyer($dessin->note),
                    'image' => $photo->webUrl(),
                    'vignette' => $photo->thumbUrl(),
                    'lien' => DrawingResource::getUrl('edit', ['record' => $dessin]),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }
}
