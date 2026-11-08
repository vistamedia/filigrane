<?php

namespace App\Services;

use Imagick;

/**
 * Développement d'un DNG (ProRAW d'iPhone, ou tout autre DNG) en JPEG pleine
 * définition, au moment de l'import.
 *
 * Un ProRAW de 48 Mpx pèse 66 Mo ; développé en JPEG de qualité 92, il en
 * pèse 6, sans perte visible. C'est ce JPEG qui devient l'original : le DNG
 * n'est pas conservé sur le serveur (voir DOCS/04, D-43). ImageMagick le lit
 * par libraw, dont le rendu a été comparé à celui d'Apple sur un ProRAW réel :
 * à peine plus clair.
 *
 * libraw ne transmet pas les métadonnées EXIF au JPEG : l'import les lit
 * d'abord dans le DNG (voir PhotoImporter).
 */
class DeveloppementRaw
{
    /** Balise TIFF propre au DNG : DNGVersion (0xC612). */
    private const BALISE_DNG = 'UndefinedTag:0xC612';

    /**
     * Un DNG se reconnaît à son extension, au type que lui donne libmagic,
     * ou, quand il se présente comme un simple TIFF, à sa balise DNGVersion.
     */
    public static function estUnDng(string $chemin, ?string $nom, string $mime): bool
    {
        if (strtolower(pathinfo((string) $nom, PATHINFO_EXTENSION)) === 'dng'
            || in_array($mime, ['image/x-adobe-dng', 'image/dng'], true)) {
            return true;
        }

        if ($mime !== 'image/tiff') {
            return false;
        }

        $balises = @exif_read_data($chemin);

        return is_array($balises) && array_key_exists(self::BALISE_DNG, $balises);
    }

    /**
     * Développe le DNG dans un fichier JPEG temporaire, à l'endroit, et en
     * renvoie le chemin. À l'appelant de l'effacer.
     */
    public function developper(string $chemin): string
    {
        // Le préfixe force le décodeur DNG : sous un nom sans extension, le
        // fichier passerait pour un TIFF, dont ImageMagick ne lirait que
        // l'aperçu.
        $image = new Imagick('dng:'.$chemin);

        try {
            $image->autoOrient();
            $image->setImageOrientation(Imagick::ORIENTATION_TOPLEFT);
            $image->stripImage();
            $image->setImageFormat('jpeg');
            $image->setImageCompressionQuality((int) config('filigrane.original_jpeg_quality', 92));

            $destination = tempnam(sys_get_temp_dir(), 'filigrane-raw-');
            $image->writeImage('jpeg:'.$destination);

            return $destination;
        } finally {
            $image->clear();
        }
    }
}
