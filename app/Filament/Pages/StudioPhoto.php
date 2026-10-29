<?php

namespace App\Filament\Pages;

use App\Enums\PhotoKind;
use App\Models\Drawing;
use App\Models\Photo;
use App\Services\PhotoImporter;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Log;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Throwable;
use UnitEnum;

/**
 * Prise de vue depuis l'application.
 *
 * Deux chemins, parce qu'ils ne servent pas la même chose :
 *
 *  - l'appareil photo du téléphone, qui donne la pleine définition et la
 *    stabilisation : c'est lui qu'il faut pour les clichés d'archive ;
 *  - le viseur intégré, plus rapide pour enchaîner les prises, mais limité à
 *    la définition que le navigateur veut bien fournir.
 */
class StudioPhoto extends Page
{
    use WithFileUploads;

    protected string $view = 'filament.pages.studio-photo';

    protected static ?string $slug = 'studio-photo';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCamera;

    protected static string|UnitEnum|null $navigationGroup = 'Prise de vue';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Studio photo';

    /** Fichier déposé par le navigateur, en attente d'import. */
    public $cliche;

    /** true : chaque cliché ouvre une fiche ; false : ils s'ajoutent à la fiche en cours. */
    public bool $nouvelleFiche = true;

    /** Fiche en cours, quand on enchaîne recto, verso et détails. */
    public ?int $ficheCourante = null;

    /** @var array<int, array{id: int, inventaire: string, vignette: ?string, lien: string}> */
    public array $derniers = [];

    public static function getNavigationLabel(): string
    {
        return 'Studio photo';
    }

    public function getSubheading(): ?string
    {
        return 'Chaque cliché crée une fiche « à décrire », prête à être renseignée.';
    }

    /**
     * Appelé par le navigateur dès que le téléversement du cliché est terminé.
     */
    public function enregistrerCliche(): void
    {
        if (! $this->cliche instanceof TemporaryUploadedFile) {
            return;
        }

        try {
            $dessin = $this->ficheCible();

            $photo = app(PhotoImporter::class)->import(
                $dessin,
                $this->cliche->getRealPath(),
                $this->cliche->getClientOriginalName(),
                ['kind' => $dessin->photos()->count() > 1 ? PhotoKind::Detail : PhotoKind::Recto],
            );

            $this->ficheCourante = $dessin->id;
            $this->empilerApercu($dessin, $photo);

            Notification::make()
                ->title($dessin->wasRecentlyCreated
                    ? "Fiche {$dessin->inventory_number} créée"
                    : "Photographie ajoutée à {$dessin->inventory_number}")
                ->success()
                ->send();
        } catch (Throwable $exception) {
            Log::error('Studio photo : import impossible', ['erreur' => $exception->getMessage()]);

            Notification::make()
                ->title('Cette photographie n\'a pas pu être enregistrée')
                ->body($exception->getMessage())
                ->danger()
                ->persistent()
                ->send();
        } finally {
            $this->reset('cliche');
        }
    }

    /**
     * Clôt la fiche en cours : le prochain cliché en ouvrira une nouvelle.
     */
    public function ficheSuivante(): void
    {
        $this->ficheCourante = null;

        Notification::make()
            ->title('Fiche close — le prochain cliché en ouvrira une nouvelle')
            ->success()
            ->send();
    }

    private function ficheCible(): Drawing
    {
        if (! $this->nouvelleFiche && $this->ficheCourante) {
            $existante = Drawing::find($this->ficheCourante);

            if ($existante) {
                return $existante;
            }
        }

        return Drawing::create([]);
    }

    private function empilerApercu(Drawing $dessin, Photo $photo): void
    {
        array_unshift($this->derniers, [
            'id' => $dessin->id,
            'inventaire' => $dessin->inventory_number,
            'vignette' => $photo->thumbUrl(),
            'lien' => \App\Filament\Resources\Drawings\DrawingResource::getUrl('edit', ['record' => $dessin]),
        ]);

        $this->derniers = array_slice($this->derniers, 0, 12);
    }
}
