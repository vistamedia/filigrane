<?php

namespace App\Services;

use App\Enums\DimensionsSource;
use App\Models\Calibration;
use App\Models\Photo;
use App\Support\SheetDetector;

/**
 * Passage des pixels aux millimètres.
 *
 * Deux sources d'échelle, dans cet ordre :
 *
 *  1. un profil de calibration établi en photographiant une référence de
 *     longueur connue ; il est réutilisé sur toutes les photos de la même
 *     série (même appareil, même focale, même cadrage) ;
 *  2. à défaut, une estimation issue de l'EXIF, qui suppose que l'appareil a
 *     enregistré la distance de mise au point — ce que les iPhone ne font pas
 *     toujours.
 *
 * Sans échelle, les dimensions restent vides : mieux vaut rien qu'un chiffre
 * faux dans un catalogue.
 */
class ScaleResolver
{
    /**
     * Détermine l'échelle, repère la feuille et en déduit les dimensions.
     */
    public function resolve(Photo $photo, string $absolutePath): void
    {
        $calibration = Calibration::query()->matching($photo)->first();

        $pxPerMm = $calibration?->px_per_mm ?? $this->estimateFromExif($photo);

        if (! $pxPerMm) {
            return;
        }

        $photo->calibration_id = $calibration?->id;
        $photo->px_per_mm = $pxPerMm;
        $photo->sheet_box ??= SheetDetector::detect($absolutePath);
        $photo->save();

        $this->pushToDrawing($photo);
    }

    /**
     * Reporte les dimensions sur la fiche, sans jamais écraser une mesure
     * physique saisie par un humain.
     */
    public function pushToDrawing(Photo $photo): bool
    {
        $size = $photo->measuredSheetSize();

        if (! $size) {
            return false;
        }

        $drawing = $photo->drawing;

        $alreadyMeasured = $drawing->dimensions_source === DimensionsSource::Measured
            || $drawing->dimensions_source === DimensionsSource::Manual;

        if ($alreadyMeasured) {
            return false;
        }

        $drawing->forceFill([
            'width_mm' => $size['width_mm'],
            'height_mm' => $size['height_mm'],
            'dimensions_source' => DimensionsSource::Estimated,
        ])->saveQuietly();

        return true;
    }

    /**
     * Le cadre d'un boîtier 24 × 36 mesure 36 mm sur son grand côté. À focale
     * équivalente 35 mm connue et distance de mise au point connue, la largeur
     * réelle du champ photographié vaut 36 × distance / focale.
     */
    private function estimateFromExif(Photo $photo): ?float
    {
        $focal35 = $photo->exif['focal_35mm'] ?? null;
        $distanceMm = $photo->exif['subject_distance_mm'] ?? null;

        if (! $focal35 || ! $distanceMm || ! $photo->width_px || ! $photo->height_px) {
            return null;
        }

        $frameLongSideMm = 36 * $distanceMm / $focal35;

        if ($frameLongSideMm <= 0) {
            return null;
        }

        return round(max($photo->width_px, $photo->height_px) / $frameLongSideMm, 6);
    }
}
