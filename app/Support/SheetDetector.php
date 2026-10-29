<?php

namespace App\Support;

use Imagick;
use Throwable;

/**
 * Repérage du rectangle de la feuille dans une photo.
 *
 * Principe : ImageMagick détoure l'image en partant de la couleur des coins.
 * Cela fonctionne dès lors que le dessin est posé sur un fond uni nettement
 * contrasté (table sombre, carton neutre). Sinon la détection échoue
 * proprement et l'utilisateur ajuste le cadre à la main.
 */
class SheetDetector
{
    /**
     * Rectangle détecté en coordonnées normalisées (0 à 1), ou null.
     *
     * @return array{x: float, y: float, w: float, h: float}|null
     */
    public static function detect(string $path, ?int $fuzzPercent = null): ?array
    {
        if (! class_exists(Imagick::class)) {
            return null;
        }

        $fuzzPercent ??= (int) config('filigrane.sheet_detection_fuzz', 22);

        try {
            $imagick = new Imagick();
            $imagick->setOption('heic:preserve-orientation', 'true');
            $imagick->readImage($path);
            $imagick->autoOrient();

            // On travaille sur une version réduite : la précision au pixel près
            // n'apporte rien et le détourage est bien plus rapide.
            $imagick->thumbnailImage(1400, 1400, true);

            $fullWidth = $imagick->getImageWidth();
            $fullHeight = $imagick->getImageHeight();

            if ($fullWidth < 50 || $fullHeight < 50) {
                $imagick->clear();

                return null;
            }

            $quantum = $imagick->getQuantumRange()['quantumRangeLong'];
            $imagick->trimImage($fuzzPercent / 100 * $quantum);

            // Après détourage, getImagePage() ne donne que le décalage : la
            // largeur et la hauteur qu'il renvoie restent celles du canevas
            // d'origine. Les dimensions réelles de la zone conservée se lisent
            // sur l'image elle-même.
            $page = $imagick->getImagePage();
            $trimWidth = $imagick->getImageWidth();
            $trimHeight = $imagick->getImageHeight();

            $imagick->clear();

            $box = [
                'x' => $page['x'] / $fullWidth,
                'y' => $page['y'] / $fullHeight,
                'w' => $trimWidth / $fullWidth,
                'h' => $trimHeight / $fullHeight,
            ];
        } catch (Throwable) {
            return null;
        }

        return self::isPlausible($box) ? self::round($box) : null;
    }

    /**
     * Un détourage qui ne retire presque rien (fond non uni) ou qui ne laisse
     * qu'une vignette (ombre, reflet) n'est pas exploitable.
     */
    private static function isPlausible(array $box): bool
    {
        $area = $box['w'] * $box['h'];

        return $area > 0.04 && $area < 0.97 && $box['w'] > 0.1 && $box['h'] > 0.1;
    }

    private static function round(array $box): array
    {
        return array_map(fn (float $value): float => round($value, 5), $box);
    }
}
