<?php

namespace App\Services;

use App\Models\Calibration;
use App\Models\Photo;
use App\Support\SheetDetector;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Application d'un profil d'échelle à toutes les photographies d'une même
 * série : c'est ce qui rend la mesure « automatique » sur 200 dessins. On
 * calibre une fois, avec une règle dans le champ, et les suivantes se mesurent
 * seules tant que l'appareil n'a pas bougé.
 *
 * Le traitement avance par lots : détourer une photo prend quelques centaines
 * de millisecondes, et une requête web ne doit pas durer des minutes.
 */
class SeriesScaler
{
    public const LOT = 40;

    public function __construct(private ScaleResolver $resolver) {}

    /**
     * @return array{traitees: int, restantes: int, echecs: int}
     */
    public function apply(Calibration $calibration, int $limite = self::LOT): array
    {
        $requete = $this->candidates($calibration);

        $restantes = (clone $requete)->count();
        $traitees = 0;
        $echecs = 0;

        foreach ($requete->limit($limite)->get() as $photo) {
            try {
                $this->applyTo($photo, $calibration);
                $traitees++;
            } catch (Throwable) {
                // Une photo sur fond non uni ne se détoure pas : on passe à la
                // suivante, elle sera mesurée à la main.
                $echecs++;
            }
        }

        return [
            'traitees' => $traitees,
            'restantes' => max(0, $restantes - $traitees),
            'echecs' => $echecs,
        ];
    }

    /**
     * Photographies de la même série encore dépourvues d'échelle.
     *
     * La série se reconnaît à trois critères : même appareil, même focale,
     * même définition. Si l'un des trois change, la distance de prise de vue
     * a pu changer aussi et l'échelle ne serait plus valable.
     *
     * Une photographie redressée est exclue : recadrée et remise d'aplomb,
     * elle n'a plus l'échelle des clichés bruts de la série.
     *
     * @return Builder<Photo>
     */
    public function candidates(Calibration $calibration): Builder
    {
        return Photo::query()
            ->whereNull('px_per_mm')
            ->whereNull('retouch')
            ->whereNotNull('processed_at')
            ->where('width_px', $calibration->image_width_px)
            ->where('height_px', $calibration->image_height_px)
            ->when(
                $calibration->camera,
                fn (Builder $query) => $query->where('exif->camera', $calibration->camera),
            )
            ->when(
                $calibration->focal_35mm,
                fn (Builder $query) => $query->where('exif->focal_35mm', $calibration->focal_35mm),
            )
            ->orderBy('id');
    }

    private function applyTo(Photo $photo, Calibration $calibration): void
    {
        $chemin = Storage::disk(config('filigrane.originals_disk'))->path($photo->original_path);

        $photo->forceFill([
            'calibration_id' => $calibration->id,
            'px_per_mm' => $calibration->px_per_mm,
            'sheet_box' => $photo->sheet_box ?? SheetDetector::detect($chemin),
        ])->save();

        $this->resolver->pushToDrawing($photo);
    }
}
