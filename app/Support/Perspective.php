<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Géométrie d'un rectangle photographié de biais.
 *
 * Un cadre accroché en hauteur et photographié d'en bas apparaît en trapèze.
 * Placer ses quatre coins suffit à le redresser, mais pas à connaître ses
 * vraies proportions : un trapèze donné peut venir d'un rectangle allongé vu
 * de face comme d'un rectangle plus haut vu très en biais. Ce qui tranche,
 * c'est la focale de l'appareil.
 *
 * La méthode est celle de Zhang et He (« Whiteboard scanning and image
 * enhancement », 2007) : les deux côtés du rectangle réel sont
 * perpendiculaires, et cette seule contrainte, jointe à la focale, fixe le
 * rapport largeur / hauteur.
 *
 * Les coins sont toujours donnés dans le même ordre, celui de la lecture :
 * haut-gauche, haut-droite, bas-droite, bas-gauche.
 */
final class Perspective
{
    /**
     * Focale équivalente 24 × 36 retenue quand l'EXIF n'en dit rien : celle de
     * l'objectif principal des iPhone, qui sert à l'immense majorité des
     * clichés. Les photographies prises depuis Filigrane arrivent sans EXIF.
     */
    public const FOCALE_PAR_DEFAUT = 26.0;

    /** Diagonale d'un négatif 24 × 36, en millimètres. */
    private const DIAGONALE_24X36 = 43.2666;

    /**
     * Rapport largeur / hauteur du rectangle réel.
     *
     * @param  array<int, array{0: float, 1: float}>  $coins  en pixels
     */
    public static function proportions(
        array $coins,
        int $largeurImage,
        int $hauteurImage,
        ?float $focale35 = null,
    ): float {
        self::verifier($coins);

        // Le centre optique est supposé au centre de l'image : c'est le cas
        // de tout appareil qui n'a pas été recadré.
        $cx = $largeurImage / 2;
        $cy = $hauteurImage / 2;

        [$hg, $hd, $bd, $bg] = array_map(
            fn (array $p): array => [$p[0] - $cx, $p[1] - $cy, 1.0],
            array_values($coins),
        );

        // Notation de Zhang et He : m1 haut-gauche, m2 haut-droite,
        // m3 bas-gauche, m4 bas-droite.
        [$m1, $m2, $m3, $m4] = [$hg, $hd, $bg, $bd];

        $k2 = self::mixte($m1, $m4, $m3) / self::mixte($m2, $m4, $m3);
        $k3 = self::mixte($m1, $m4, $m2) / self::mixte($m3, $m4, $m2);

        // Directions des deux côtés du rectangle réel, à la focale près.
        $n2 = [$k2 * $m2[0] - $m1[0], $k2 * $m2[1] - $m1[1], $k2 - 1];
        $n3 = [$k3 * $m3[0] - $m1[0], $k3 * $m3[1] - $m1[1], $k3 - 1];

        $f = self::focalePixels($largeurImage, $hauteurImage, $focale35);

        $largeur = ($n2[0] ** 2 + $n2[1] ** 2) / $f ** 2 + $n2[2] ** 2;
        $hauteur = ($n3[0] ** 2 + $n3[1] ** 2) / $f ** 2 + $n3[2] ** 2;

        $rapport = sqrt($largeur / $hauteur);

        // Coins presque alignés ou placés derrière l'horizon : le calcul n'a
        // plus de sens, on se rabat sur la moyenne des côtés opposés.
        if (! is_finite($rapport) || $rapport <= 0 || $k2 <= 0 || $k3 <= 0) {
            return self::proportionsApparentes($coins);
        }

        return $rapport;
    }

    /**
     * Rapport des côtés tels qu'ils apparaissent sur la photographie, sans
     * correction de perspective. Exact pour une prise de vue de face.
     *
     * @param  array<int, array{0: float, 1: float}>  $coins
     */
    public static function proportionsApparentes(array $coins): float
    {
        [$hg, $hd, $bd, $bg] = array_values($coins);

        $largeur = (self::distance($hg, $hd) + self::distance($bg, $bd)) / 2;
        $hauteur = (self::distance($hg, $bg) + self::distance($hd, $bd)) / 2;

        return $largeur / $hauteur;
    }

    /**
     * Longueur des quatre côtés, en pixels : haut, droite, bas, gauche.
     *
     * @param  array<int, array{0: float, 1: float}>  $coins
     * @return array{0: float, 1: float, 2: float, 3: float}
     */
    public static function cotes(array $coins): array
    {
        [$hg, $hd, $bd, $bg] = array_values($coins);

        return [
            self::distance($hg, $hd),
            self::distance($hd, $bd),
            self::distance($bd, $bg),
            self::distance($bg, $hg),
        ];
    }

    /**
     * Les quatre coins doivent former un quadrilatère convexe, parcouru dans
     * le sens de la lecture. Des coins croisés donneraient une image
     * retournée en miroir : on refuse plutôt que de la produire.
     *
     * @param  array<int, array{0: float, 1: float}>  $coins
     */
    public static function verifier(array $coins): void
    {
        $coins = array_values($coins);

        if (count($coins) !== 4) {
            throw new InvalidArgumentException('Il faut exactement quatre coins.');
        }

        for ($i = 0; $i < 4; $i++) {
            $a = $coins[$i];
            $b = $coins[($i + 1) % 4];
            $c = $coins[($i + 2) % 4];

            // L'axe des ordonnées descend : un parcours dans le sens des
            // aiguilles d'une montre donne un produit vectoriel positif.
            $tour = ($b[0] - $a[0]) * ($c[1] - $b[1]) - ($b[1] - $a[1]) * ($c[0] - $b[0]);

            if ($tour <= 0) {
                throw new InvalidArgumentException('Les quatre coins se croisent ou sont alignés.');
            }
        }
    }

    /**
     * Focale en pixels. La focale « équivalente 24 × 36 » se rapporte à la
     * diagonale de l'image, quel que soit son format.
     */
    private static function focalePixels(int $largeur, int $hauteur, ?float $focale35): float
    {
        $focale35 = $focale35 && $focale35 > 0 ? $focale35 : self::FOCALE_PAR_DEFAUT;

        return $focale35 * hypot($largeur, $hauteur) / self::DIAGONALE_24X36;
    }

    /** Produit mixte (a × b) · c. */
    private static function mixte(array $a, array $b, array $c): float
    {
        return ($a[1] * $b[2] - $a[2] * $b[1]) * $c[0]
            + ($a[2] * $b[0] - $a[0] * $b[2]) * $c[1]
            + ($a[0] * $b[1] - $a[1] * $b[0]) * $c[2];
    }

    private static function distance(array $a, array $b): float
    {
        return hypot($b[0] - $a[0], $b[1] - $a[1]);
    }
}
