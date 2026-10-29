<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Drawings\DrawingResource;
use App\Models\Calibration;
use App\Models\Photo;
use App\Services\ScaleResolver;
use App\Services\SeriesScaler;
use App\Support\SheetDetector;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Calcul des dimensions d'un dessin à partir d'une photographie.
 *
 * L'utilisateur trace un segment sur une référence de longueur connue (règle,
 * carte bancaire) puis ajuste le cadre de la feuille, pré-positionné par
 * détection automatique. L'échelle obtenue peut être enregistrée comme profil
 * et appliquée à toutes les photos de la même série.
 */
class MesurerPhoto extends Page
{
    protected string $view = 'filament.pages.mesurer-photo';

    protected static ?string $slug = 'mesurer';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Mesurer un dessin';

    public Photo $photo;

    /** Cadre de la feuille pré-détecté, en coordonnées normalisées. */
    public array $cadreInitial = ['x' => 0.15, 'y' => 0.15, 'w' => 0.7, 'h' => 0.7];

    public function mount(): void
    {
        $this->photo = Photo::with('drawing')->findOrFail(request()->query('photo'));

        if ($this->photo->sheet_box) {
            $this->cadreInitial = $this->photo->sheet_box;

            return;
        }

        $chemin = Storage::disk(config('filigrane.originals_disk'))->path($this->photo->original_path);

        $this->cadreInitial = SheetDetector::detect($chemin) ?? $this->cadreInitial;
    }

    public function getHeading(): string
    {
        return 'Mesurer '.$this->photo->drawing->inventory_number;
    }

    public function getSubheading(): ?string
    {
        return 'Placez le trait rouge sur un objet de longueur connue, puis ajustez le cadre bleu sur la feuille.';
    }

    /**
     * Références proposées dans la liste déroulante.
     *
     * @return array<int, array{label: string, mm: float}>
     */
    public function references(): array
    {
        return config('filigrane.scale_references');
    }

    public function urlPhoto(): ?string
    {
        return $this->photo->webUrl();
    }

    /**
     * @return array{width: int, height: int}
     */
    public function definition(): array
    {
        return [
            'width' => $this->photo->width_px ?? 0,
            'height' => $this->photo->height_px ?? 0,
        ];
    }

    /**
     * Enregistre la mesure. Appelé depuis l'outil avec l'état courant.
     */
    public function enregistrer(array $mesure): void
    {
        $validateur = validator($mesure, [
            'px_per_mm' => ['required', 'numeric', 'min:0.01', 'max:10000'],
            'reference_mm' => ['required', 'numeric', 'min:1', 'max:5000'],
            'reference_label' => ['nullable', 'string', 'max:255'],
            'segment' => ['required', 'array'],
            'segment.x1' => ['required', 'numeric', 'between:0,1'],
            'segment.y1' => ['required', 'numeric', 'between:0,1'],
            'segment.x2' => ['required', 'numeric', 'between:0,1'],
            'segment.y2' => ['required', 'numeric', 'between:0,1'],
            'box' => ['required', 'array'],
            'box.x' => ['required', 'numeric', 'between:0,1'],
            'box.y' => ['required', 'numeric', 'between:0,1'],
            'box.w' => ['required', 'numeric', 'between:0.01,1'],
            'box.h' => ['required', 'numeric', 'between:0.01,1'],
            'profil' => ['boolean'],
            'profil_label' => ['nullable', 'string', 'max:255'],
            'appliquer_serie' => ['boolean'],
        ]);

        if ($validateur->fails()) {
            Notification::make()
                ->title('Mesure incomplète')
                ->body($validateur->errors()->first())
                ->danger()
                ->send();

            return;
        }

        $donnees = $validateur->validated();

        $calibration = null;

        if ($donnees['profil'] ?? false) {
            $calibration = Calibration::create([
                'label' => $donnees['profil_label'] ?: 'Série du '.now()->translatedFormat('j F Y'),
                'is_active' => true,
                'camera' => $this->photo->exif['camera'] ?? null,
                'focal_35mm' => $this->photo->exif['focal_35mm'] ?? null,
                'image_width_px' => $this->photo->width_px,
                'image_height_px' => $this->photo->height_px,
                'px_per_mm' => $donnees['px_per_mm'],
                'reference_label' => $donnees['reference_label'],
                'reference_mm' => $donnees['reference_mm'],
                'reference_segment' => $donnees['segment'],
                'created_by_id' => Auth::id(),
            ]);
        }

        $this->photo->forceFill([
            'px_per_mm' => $donnees['px_per_mm'],
            'sheet_box' => $donnees['box'],
            'calibration_id' => $calibration?->id ?? $this->photo->calibration_id,
        ])->save();

        if (! app(ScaleResolver::class)->pushToDrawing($this->photo)) {
            Notification::make()
                ->title('Échelle enregistrée')
                ->body('Les dimensions de la fiche n\'ont pas été modifiées : elles sont marquées comme mesurées à la main.')
                ->warning()
                ->send();
        } else {
            $dessin = $this->photo->drawing->refresh();

            Notification::make()
                ->title('Dimensions enregistrées')
                ->body($dessin->dimensionsLabel())
                ->success()
                ->send();
        }

        if ($calibration && ($donnees['appliquer_serie'] ?? false)) {
            $bilan = app(SeriesScaler::class)->apply($calibration);

            Notification::make()
                ->title("Série traitée : {$bilan['traitees']} photographie(s)")
                ->body($bilan['restantes'] > 0
                    ? "Il reste {$bilan['restantes']} photographie(s) à traiter. Relancez depuis l'écran « Échelles »."
                    : 'Toutes les photographies de la série ont une échelle.')
                ->success()
                ->send();
        }

        $this->redirect(DrawingResource::getUrl('edit', ['record' => $this->photo->drawing]));
    }
}
