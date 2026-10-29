<?php

namespace App\Services;

use App\Models\Photo;
use App\Support\Perspective;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Imagick;
use ImagickPixel;

/**
 * Retouche d'une photographie : redresser, recadrer, tourner, éclaircir.
 *
 * Rien n'est jamais écrit sur l'original. On enregistre les réglages, et l'on
 * refabrique à partir de l'original la version web et la miniature — les
 * seules images que l'application affiche, projette et exporte. Annuler la
 * retouche revient à les refabriquer sans réglage.
 *
 * Chaque retouche produit des fichiers sous un nom neuf : le navigateur garde
 * les images un an en cache, et une image changée sous le même nom resterait
 * invisible à qui l'a déjà regardée.
 */
class RetouchePhoto
{
    /** Les quatre coins de l'image entière : aucun recadrage. */
    public const COINS_PLEINS = [[0.0, 0.0], [1.0, 0.0], [1.0, 1.0], [0.0, 1.0]];

    public function __construct(private DerivativeMaker $derivatives) {}

    /**
     * @param  array{coins: array<int, array{0: float, 1: float}>, quart: int, focale: float, luminosite: float, contraste: float}  $reglages
     */
    public function appliquer(Photo $photo, array $reglages): void
    {
        $coinsNormalises = array_map(
            fn (array $coin): array => [round((float) $coin[0], 5), round((float) $coin[1], 5)],
            array_values($reglages['coins']),
        );
        $quart = ((int) $reglages['quart'] % 4 + 4) % 4;
        $luminosite = round((float) $reglages['luminosite'], 3);
        $contraste = round((float) $reglages['contraste'], 3);

        // Des réglages neutres ne sont pas une retouche : on revient à la
        // photographie telle qu'elle a été prise.
        if ($this->sontNeutres($coinsNormalises, $quart, $luminosite, $contraste)) {
            $this->annuler($photo);

            return;
        }

        $original = Storage::disk(config('filigrane.originals_disk'))->path($photo->original_path);
        $image = $this->derivatives->decodeOriented($original);

        /** @var Imagick $imagick */
        $imagick = $image->core()->native();
        $largeur = $imagick->getImageWidth();
        $hauteur = $imagick->getImageHeight();

        $coins = array_map(fn (array $coin): array => [$coin[0] * $largeur, $coin[1] * $hauteur], $coinsNormalises);

        // Une focale lue dans l'EXIF l'emporte sur celle qu'on a déclarée.
        $focale = (float) ($photo->exif['focal_35mm'] ?? $reglages['focale']);
        $rapport = Perspective::proportions($coins, $largeur, $hauteur, $focale);

        // Le rectangle redressé, en pleine définition : aucun côté n'est
        // raccourci par rapport à ce que montre la photographie.
        [$haut, $droite, $bas, $gauche] = Perspective::cotes($coins);
        $largeurPleine = max($haut, $bas, $rapport * max($gauche, $droite));
        $hauteurPleine = $largeurPleine / $rapport;

        // Un quart de tour échange largeur et hauteur. La version web reste
        // bornée en largeur, comme à l'import.
        $couchee = $quart % 2 === 1;
        $echelle = min(1, config('filigrane.web_width', 2400) / ($couchee ? $hauteurPleine : $largeurPleine));
        $l = max(1, (int) round($largeurPleine * $echelle));
        $h = max(1, (int) round($hauteurPleine * $echelle));

        $imagick->setImageVirtualPixelMethod(Imagick::VIRTUALPIXELMETHOD_EDGE);
        $imagick->setImageArtifact('distort:viewport', "{$l}x{$h}+0+0");
        $imagick->distortImage(Imagick::DISTORTION_PERSPECTIVE, [
            $coins[0][0], $coins[0][1], 0, 0,
            $coins[1][0], $coins[1][1], $l, 0,
            $coins[2][0], $coins[2][1], $l, $h,
            $coins[3][0], $coins[3][1], 0, $h,
        ], false);
        $imagick->setImagePage($l, $h, 0, 0);

        if ($quart !== 0) {
            $imagick->rotateImage(new ImagickPixel('white'), 90 * $quart);
            $imagick->setImagePage(0, 0, 0, 0);
        }

        $this->eclairer($imagick, $luminosite, $contraste);

        $web = $this->derivatives->encodeWeb($image);
        [$webPath, $thumbPath] = $this->ecrire($photo, $web);

        $retouche = [
            'coins' => $coinsNormalises,
            'quart' => $quart,
            'focale' => $focale,
            'luminosite' => $luminosite,
            'contraste' => $contraste,
            'largeur_px' => (int) round($couchee ? $hauteurPleine : $largeurPleine),
            'hauteur_px' => (int) round($couchee ? $largeurPleine : $hauteurPleine),
        ];

        // La première retouche met de côté la version web d'origine ; les
        // suivantes remplacent la précédente.
        $perimes = $photo->isRetouched()
            ? [$photo->web_path, $photo->thumb_path]
            : [$photo->thumb_path];

        $photo->forceFill([
            'raw_web_path' => $photo->raw_web_path ?? $photo->web_path,
            'web_path' => $webPath,
            'thumb_path' => $thumbPath,
            'retouch' => $retouche,
            ...$this->mesureAEffacer($photo, $retouche),
        ])->save();

        Storage::disk(config('filigrane.derivatives_disk'))->delete(array_filter($perimes));
    }

    /**
     * Revient à la photographie telle qu'elle a été prise.
     */
    public function annuler(Photo $photo): void
    {
        if (! $photo->isRetouched()) {
            return;
        }

        $original = Storage::disk(config('filigrane.originals_disk'))->path($photo->original_path);
        $made = $this->derivatives->make($original);

        [$webPath, $thumbPath] = $this->ecrire($photo, $made['web'], $made['thumb']);

        $perimes = [$photo->web_path, $photo->raw_web_path, $photo->thumb_path];

        $photo->forceFill([
            'web_path' => $webPath,
            'raw_web_path' => null,
            'thumb_path' => $thumbPath,
            'retouch' => null,
            ...$this->mesureAEffacer($photo, null),
        ])->save();

        Storage::disk(config('filigrane.derivatives_disk'))->delete(array_filter($perimes));
    }

    /**
     * Même formule que les filtres CSS brightness() puis contrast() de
     * l'aperçu : ce qu'on voit à l'écran est ce qu'on obtient.
     */
    private function eclairer(Imagick $imagick, float $luminosite, float $contraste): void
    {
        if (abs($luminosite - 1) < 0.001 && abs($contraste - 1) < 0.001) {
            return;
        }

        $imagick->functionImage(Imagick::FUNCTION_POLYNOMIAL, [
            $contraste * $luminosite,
            0.5 * (1 - $contraste),
        ]);
    }

    /**
     * Écrit la version web et la miniature sous un nom neuf.
     *
     * @return array{0: string, 1: string}
     */
    private function ecrire(Photo $photo, string $web, ?string $thumb = null): array
    {
        $base = pathinfo($photo->original_path, PATHINFO_FILENAME).'-'.Str::lower(Str::random(8));
        $dossier = now()->format('Y/m');

        $webPath = trim(config('filigrane.web_path'), '/')."/{$dossier}/{$base}.jpg";
        $thumbPath = trim(config('filigrane.thumb_path'), '/')."/{$dossier}/{$base}.jpg";

        $public = Storage::disk(config('filigrane.derivatives_disk'));
        $public->put($webPath, $web);
        $public->put($thumbPath, $thumb ?? $this->derivatives->thumbnail($web));

        return [$webPath, $thumbPath];
    }

    /**
     * Une mesure faite sur la photographie ne vaut plus si la géométrie
     * change : le cadre bleu et l'échelle désigneraient d'autres pixels. Les
     * dimensions déjà reportées sur la fiche, elles, restent.
     *
     * Une retouche de la seule lumière ne touche à rien de tout cela.
     *
     * @return array<string, null>
     */
    private function mesureAEffacer(Photo $photo, ?array $nouvelle): array
    {
        $avant = $this->geometrie($photo->retouch, $photo);
        $apres = $this->geometrie($nouvelle, $photo);

        // Comparaison souple : relus depuis le JSON, 0.0 et 1.0 deviennent des
        // entiers.
        return $avant == $apres
            ? []
            : ['px_per_mm' => null, 'sheet_box' => null, 'calibration_id' => null];
    }

    private function geometrie(?array $retouche, Photo $photo): array
    {
        return [
            'coins' => $retouche['coins'] ?? self::COINS_PLEINS,
            'quart' => $retouche['quart'] ?? 0,
            'largeur_px' => $retouche['largeur_px'] ?? $photo->width_px,
            'hauteur_px' => $retouche['hauteur_px'] ?? $photo->height_px,
        ];
    }

    private function sontNeutres(array $coins, int $quart, float $luminosite, float $contraste): bool
    {
        return $coins == self::COINS_PLEINS
            && $quart === 0
            && abs($luminosite - 1) < 0.001
            && abs($contraste - 1) < 0.001;
    }
}
