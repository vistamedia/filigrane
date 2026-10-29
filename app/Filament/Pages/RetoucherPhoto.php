<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Drawings\DrawingResource;
use App\Models\Drawing;
use App\Models\Photo;
use App\Services\RetouchePhoto;
use App\Support\Perspective;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * Retouche d'une photographie : on place quatre pastilles aux coins de
 * l'œuvre, et l'image est redressée et recadrée ; on peut aussi la tourner
 * d'un quart de tour et en corriger la lumière.
 *
 * Pensé pour les œuvres encadrées, photographiées au mur, souvent d'en bas
 * parce qu'elles sont accrochées haut. L'original n'est jamais modifié : voir
 * App\Services\RetouchePhoto.
 */
class RetoucherPhoto extends Page
{
    protected string $view = 'filament.pages.retoucher-photo';

    protected static ?string $slug = 'retoucher';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Retoucher une photographie';

    public Photo $photo;

    public function mount(): void
    {
        $this->photo = Photo::with('drawing')->findOrFail(request()->query('photo'));
    }

    public function getHeading(): string
    {
        return 'Retoucher '.$this->photo->drawing->inventory_number;
    }

    public function getSubheading(): ?string
    {
        return 'Placez les quatre pastilles aux coins de l\'œuvre : l\'image sera redressée et recadrée.';
    }

    /**
     * Réglages de départ : ceux de la retouche précédente, sinon un cadre
     * posé sur la feuille déjà repérée, sinon en léger retrait des bords.
     *
     * @return array{coins: array, quart: int, focale: float, luminosite: float, contraste: float}
     */
    public function reglagesInitiaux(): array
    {
        $retouche = $this->photo->retouch ?? [];
        $boite = $this->photo->sheet_box;

        $coins = $retouche['coins'] ?? ($boite
            ? [
                [$boite['x'], $boite['y']],
                [$boite['x'] + $boite['w'], $boite['y']],
                [$boite['x'] + $boite['w'], $boite['y'] + $boite['h']],
                [$boite['x'], $boite['y'] + $boite['h']],
            ]
            : [[0.12, 0.12], [0.88, 0.12], [0.88, 0.88], [0.12, 0.88]]);

        return [
            'coins' => $coins,
            'quart' => (int) ($retouche['quart'] ?? 0),
            'focale' => (float) ($retouche['focale'] ?? Perspective::FOCALE_PAR_DEFAUT),
            'luminosite' => (float) ($retouche['luminosite'] ?? 1),
            'contraste' => (float) ($retouche['contraste'] ?? 1),
        ];
    }

    /**
     * @return array<int, array{label: string, focale: int}>
     */
    public function zooms(): array
    {
        return config('filigrane.zooms');
    }

    /**
     * Focale lue dans la photographie. Quand elle est connue, inutile de
     * demander quel zoom affichait l'appareil.
     */
    public function focaleExif(): ?float
    {
        $focale = $this->photo->exif['focal_35mm'] ?? null;

        return $focale ? (float) $focale : null;
    }

    /**
     * La prochaine photographie qui n'a pas encore été retouchée, pour
     * enchaîner une série sans repasser par chaque fiche.
     */
    public function suivante(): ?Photo
    {
        return Photo::query()
            ->whereNull('retouch')
            ->whereNotNull('web_path')
            ->where('id', '>', $this->photo->id)
            // Les fiches à la corbeille sont écartées par la portée par défaut.
            ->whereIn('drawing_id', Drawing::query()->select('id'))
            ->orderBy('id')
            ->first();
    }

    public function enregistrer(array $reglages, bool $suivante = false): void
    {
        $validateur = validator($reglages, [
            'coins' => ['required', 'array', 'size:4'],
            'coins.*' => ['required', 'array', 'size:2'],
            'coins.*.*' => ['required', 'numeric', 'between:0,1'],
            'quart' => ['required', 'integer', 'between:0,3'],
            'focale' => ['required', 'numeric', 'between:10,300'],
            'luminosite' => ['required', 'numeric', 'between:0.5,2'],
            'contraste' => ['required', 'numeric', 'between:0.5,2'],
        ]);

        if ($validateur->fails()) {
            Notification::make()
                ->title('Réglages incomplets')
                ->body($validateur->errors()->first())
                ->danger()
                ->send();

            return;
        }

        $prochaine = $suivante ? $this->suivante() : null;

        try {
            app(RetouchePhoto::class)->appliquer($this->photo, $validateur->validated());
        } catch (InvalidArgumentException $exception) {
            Notification::make()
                ->title('Les pastilles se croisent')
                ->body('Replacez chacune à son coin : haut-gauche, haut-droite, bas-droite, bas-gauche.')
                ->danger()
                ->send();

            return;
        } catch (Throwable $exception) {
            Log::error('Retouche impossible', ['photo' => $this->photo->id, 'erreur' => $exception->getMessage()]);

            Notification::make()
                ->title('La retouche n\'a pas pu être enregistrée')
                ->body('La photographie d\'origine est intacte. Réessayez dans un instant.')
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title('Photographie retouchée')
            ->body('L\'original est conservé tel quel.')
            ->success()
            ->send();

        $this->redirect($prochaine
            ? self::getUrl(['photo' => $prochaine->id])
            : DrawingResource::getUrl('edit', ['record' => $this->photo->drawing]));
    }

    public function annulerRetouche(): void
    {
        app(RetouchePhoto::class)->annuler($this->photo);

        Notification::make()
            ->title('Photographie rétablie telle qu\'elle a été prise')
            ->success()
            ->send();

        $this->redirect(DrawingResource::getUrl('edit', ['record' => $this->photo->drawing]));
    }
}
