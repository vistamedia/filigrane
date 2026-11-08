<?php

namespace App\Services;

use App\Models\Photo;
use App\Support\Perspective;
use Illuminate\Support\Arr;
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
     * Avec « alleger », l'original lui-même est rogné autour des coins, avec
     * une marge : le reste de la photographie est perdu (D-44).
     *
     * @param  array{coins: array<int, array{0: float, 1: float}>, quart: int, focale: float, luminosite: float, contraste: float, alleger?: bool}  $reglages
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
        // photographie telle qu'elle a été prise. Il n'y a alors rien à
        // alléger non plus : les coins sont aux bords.
        if ($this->sontNeutres($coinsNormalises, $quart, $luminosite, $contraste)) {
            $this->annuler($photo);

            return;
        }

        Perspective::verifier($coinsNormalises);

        $original = Storage::disk(config('filigrane.originals_disk'))->path($photo->original_path);
        $image = $this->derivatives->decodeOriented($original);

        /** @var Imagick $imagick */
        $imagick = $image->core()->native();
        $largeur = $imagick->getImageWidth();
        $hauteur = $imagick->getImageHeight();

        $cadre = $photo->isTrimmed()
            ? $photo->fullFrame()
            : ['x' => 0, 'y' => 0, 'largeur_px' => $largeur, 'hauteur_px' => $hauteur];

        $allegement = ($reglages['alleger'] ?? false)
            ? $this->alleger($photo, $imagick, $coinsNormalises, $cadre)
            : null;

        if ($allegement) {
            // L'image en mémoire n'est plus que la partie gardée : les coins
            // s'y rapportent désormais.
            $coinsNormalises = $allegement['coins'];
            $cadre = $allegement['cadre'];
            $largeur = $imagick->getImageWidth();
            $hauteur = $imagick->getImageHeight();
        }

        $coins = array_map(fn (array $coin): array => [$coin[0] * $largeur, $coin[1] * $hauteur], $coinsNormalises);

        // Les vraies proportions se calculent dans la photographie entière :
        // son centre est le centre optique, et la focale se rapporte à sa
        // diagonale. Une focale lue dans l'EXIF l'emporte sur celle déclarée.
        $focale = (float) ($photo->exif['focal_35mm'] ?? $reglages['focale']);
        $rapport = Perspective::proportions(
            array_map(fn (array $coin): array => [$coin[0] + $cadre['x'], $coin[1] + $cadre['y']], $coins),
            $cadre['largeur_px'],
            $cadre['hauteur_px'],
            $focale,
        );

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

        $champs = [
            'raw_web_path' => $photo->raw_web_path ?? $photo->web_path,
            'web_path' => $webPath,
            'thumb_path' => $thumbPath,
            'retouch' => $retouche,
            ...$this->mesureAEffacer($photo, $retouche),
        ];

        // Allégé, l'original change, et sa version web d'origine avec lui :
        // l'ancienne montrerait encore la table.
        if ($allegement) {
            $perimes[] = $photo->raw_web_path ?? $photo->web_path;

            $champs = [
                ...$champs,
                ...Arr::except($allegement['photo'], ['brute']),
                'raw_web_path' => $this->ecrireBrute($photo, $allegement['photo']['brute']),
            ];
        }

        $photo->forceFill($champs)->save();

        Storage::disk(config('filigrane.derivatives_disk'))->delete(array_filter(array_unique($perimes)));

        // L'ancien original ne part qu'une fois la photographie enregistrée :
        // une erreur plus tôt le laisse en place, intact.
        if ($allegement) {
            Storage::disk(config('filigrane.originals_disk'))->delete($allegement['ancien']);
        }
    }

    /**
     * Rogne l'image en mémoire autour des coins, avec une marge, et écrit
     * cette partie comme nouvel original, en JPEG pleine définition. N'efface
     * rien : c'est l'appelant qui le fait, une fois tout enregistré.
     *
     * Renvoie null quand il n'y a presque rien à gagner : moins d'un dixième
     * de la photographie à retirer.
     *
     * @param  array{x: int, y: int, largeur_px: int, hauteur_px: int}  $cadre  la photographie entière
     * @return array{coins: array, cadre: array, ancien: string, photo: array<string, mixed>}|null
     */
    private function alleger(Photo $photo, Imagick $imagick, array $coins, array $cadre): ?array
    {
        $largeur = $imagick->getImageWidth();
        $hauteur = $imagick->getImageHeight();

        // La marge se mesure sur la photographie entière : rapportée à
        // l'original déjà allégé, elle rétrécirait à chaque passage, et la
        // même retouche, enregistrée deux fois, rognerait encore.
        $marge = (float) config('filigrane.marge_allegement', 0.04) * max($cadre['largeur_px'], $cadre['hauteur_px']);

        $xs = array_map(fn (array $coin): float => $coin[0] * $largeur, $coins);
        $ys = array_map(fn (array $coin): float => $coin[1] * $hauteur, $coins);

        $x0 = (int) max(0, floor(min($xs) - $marge));
        $y0 = (int) max(0, floor(min($ys) - $marge));
        $l = (int) min($largeur, ceil(max($xs) + $marge)) - $x0;
        $h = (int) min($hauteur, ceil(max($ys) + $marge)) - $y0;

        if ($l * $h > 0.9 * $largeur * $hauteur) {
            return null;
        }

        $imagick->cropImage($l, $h, $x0, $y0);
        $imagick->setImagePage(0, 0, 0, 0);

        // L'image est déjà à l'endroit : une orientation laissée dans l'EXIF
        // la ferait tourner une seconde fois. Le profil de couleur, lui, reste.
        $jpeg = clone $imagick;
        if ($jpeg->getImageProfiles('exif', false) !== []) {
            $jpeg->removeImageProfile('exif');
        }
        $jpeg->setImageOrientation(Imagick::ORIENTATION_TOPLEFT);
        $jpeg->setImageFormat('jpeg');
        $jpeg->setImageCompressionQuality((int) config('filigrane.original_jpeg_quality', 92));
        $octets = $jpeg->getImageBlob();
        $jpeg->clear();

        $originaux = Storage::disk(config('filigrane.originals_disk'));
        $chemin = sprintf(
            '%s/%s/%s.jpg',
            trim(config('filigrane.originals_path'), '/'),
            now()->format('Y/m'),
            Str::lower((string) Str::ulid()),
        );
        $originaux->put($chemin, $octets);

        return [
            'coins' => array_map(fn (array $coin): array => [
                round(($coin[0] * $largeur - $x0) / $l, 5),
                round(($coin[1] * $hauteur - $y0) / $h, 5),
            ], $coins),
            'cadre' => [
                'x' => $cadre['x'] + $x0,
                'y' => $cadre['y'] + $y0,
                'largeur_px' => $cadre['largeur_px'],
                'hauteur_px' => $cadre['hauteur_px'],
            ],
            'ancien' => $photo->original_path,
            'photo' => [
                'original_path' => $chemin,
                'mime' => 'image/jpeg',
                'size_bytes' => strlen($octets),
                'width_px' => $l,
                'height_px' => $h,
                'original_trim' => [
                    'x' => $cadre['x'] + $x0,
                    'y' => $cadre['y'] + $y0,
                    'largeur_px' => $cadre['largeur_px'],
                    'hauteur_px' => $cadre['hauteur_px'],
                ],
                'brute' => $this->derivatives->web($originaux->path($chemin)),
            ],
        ];
    }

    /** Écrit la version web d'origine d'un original allégé, sous un nom neuf. */
    private function ecrireBrute(Photo $photo, string $octets): string
    {
        [$chemin] = $this->cheminsNeufs($photo);
        Storage::disk(config('filigrane.derivatives_disk'))->put($chemin, $octets);

        return $chemin;
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
     * La version web d'origine, mise de côté à la première retouche, est le
     * fond sur lequel l'outil replace les coins. Si elle manque, on la
     * refabrique depuis l'original, qui n'est jamais touché.
     *
     * Elle a manqué pour de bon : jusqu'au 6 novembre 2026, le ménage de nuit
     * la prenait pour une orpheline (voir DOCS/02, défaut n° 13).
     *
     * @return bool vrai si elle a dû être refabriquée
     */
    public function retablirLaVersionDOrigine(Photo $photo): bool
    {
        $public = Storage::disk(config('filigrane.derivatives_disk'));

        if (! $photo->isRetouched()
            || ($photo->raw_web_path && $public->exists($photo->raw_web_path))
            || ! $photo->originalExists()) {
            return false;
        }

        $original = Storage::disk(config('filigrane.originals_disk'))->path($photo->original_path);
        [$chemin] = $this->cheminsNeufs($photo);

        $public->put($chemin, $this->derivatives->web($original));
        $photo->forceFill(['raw_web_path' => $chemin])->save();

        return true;
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
        [$webPath, $thumbPath] = $this->cheminsNeufs($photo);

        $public = Storage::disk(config('filigrane.derivatives_disk'));
        $public->put($webPath, $web);
        $public->put($thumbPath, $thumb ?? $this->derivatives->thumbnail($web));

        return [$webPath, $thumbPath];
    }

    /**
     * Un nom jamais servi, pour la version web et pour la miniature.
     *
     * @return array{0: string, 1: string}
     */
    private function cheminsNeufs(Photo $photo): array
    {
        $base = pathinfo($photo->original_path, PATHINFO_FILENAME).'-'.Str::lower(Str::random(8));
        $dossier = now()->format('Y/m');

        return [
            trim(config('filigrane.web_path'), '/')."/{$dossier}/{$base}.jpg",
            trim(config('filigrane.thumb_path'), '/')."/{$dossier}/{$base}.jpg",
        ];
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
