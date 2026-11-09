<?php

namespace App\Support;

use Imagick;

/**
 * Repère les feuilles posées côte à côte sur une même photo : des papiers
 * clairs sur une table sombre (D-45).
 *
 * Principe : sur une version réduite de l'image, un seuil sépare le clair du
 * sombre (méthode d'Otsu) ; chaque région claire d'un seul tenant est une
 * feuille candidate. Les traits du dessin, sombres, creusent la région sans la
 * couper : seul compte son rectangle englobant. Les reflets sur la table, les
 * taches et les miettes sont écartés par leur taille et leur forme.
 *
 * Le résultat n'est qu'une proposition : la page de séparation la montre, et
 * l'on ajuste, retire ou ajoute un cadre avant de valider.
 */
class DetecteurDeDessins
{
    /** Côté le plus long de l'image analysée, en pixels. */
    private const ANALYSE = 500;

    /**
     * Rayon de l'ouverture morphologique, en pixels de l'image analysée : de
     * quoi effacer le grain clair du bois. Plus large, elle rongeait le papier
     * autour des traits et coupait une feuille en morceaux.
     */
    private const RAYON_OUVERTURE = 1;

    /** En deçà, une ligne de la région passe entre deux feuilles. */
    private const REMPLISSAGE_ECART = 0.5;

    /** Part minimale de l'image qu'occupe une feuille. */
    private const AIRE_MINIMALE = 0.002;

    /** Part minimale de son rectangle qu'une feuille remplit : un reflet en diagonale en remplit peu. */
    private const REMPLISSAGE_MINIMAL = 0.45;

    /**
     * Les cadres repérés, en coordonnées normalisées (0 à 1), dans l'ordre de
     * la lecture : rangée par rangée, de gauche à droite.
     *
     * @return list<array{x: float, y: float, w: float, h: float}>
     */
    public static function detecter(string $chemin, float $marge = 0.015): array
    {
        $image = new Imagick($chemin);
        $image->autoOrient();
        $image->thumbnailImage(self::ANALYSE, self::ANALYSE, true);
        [$l, $h] = [$image->getImageWidth(), $image->getImageHeight()];

        $image->transformImageColorspace(Imagick::COLORSPACE_GRAY);
        $seuil = self::otsu($image->exportImagePixels(0, 0, $l, $h, 'I', Imagick::PIXEL_CHAR));

        // Le clair d'un côté, le sombre de l'autre ; puis une légère
        // « ouverture » (érosion, puis dilatation), qui efface les grains
        // clairs isolés. Les reflets qui soudent deux feuilles sont traités
        // plus loin, par la découpe en bandes.
        $image->thresholdImage($seuil / 255 * $image->getQuantumRange()['quantumRangeLong']);
        $image->morphology(Imagick::MORPHOLOGY_OPEN, 1, \ImagickKernel::fromBuiltIn(Imagick::KERNEL_SQUARE, (string) self::RAYON_OUVERTURE));

        $clair = array_map(
            fn (int $v): bool => $v > 127,
            $image->exportImagePixels(0, 0, $l, $h, 'I', Imagick::PIXEL_CHAR),
        );
        $image->clear();

        $cadres = [];
        [$etiquettes, $regions] = self::regions($clair, $l, $h);

        foreach ($regions as $etiquette => $region) {
            // Deux feuilles voisines, soudées par un reflet, ne font qu'une
            // région : on la découpe là où elle s'amincit.
            foreach (self::decouper($etiquettes, $l, $etiquette, $region) as [$x0, $y0, $x1, $y1, $aire]) {
                [$lr, $hr] = [$x1 - $x0 + 1, $y1 - $y0 + 1];

                if ($aire < self::AIRE_MINIMALE * $l * $h
                    || $aire < self::REMPLISSAGE_MINIMAL * $lr * $hr
                    || min($lr, $hr) < 0.03 * min($l, $h)) {
                    continue;
                }

                $cadres[] = [$x0, $y0, $x1 + 1, $y1 + 1];
            }
        }

        $cadres = self::fusionner($cadres);

        // Une marge autour de chaque feuille : de quoi reprendre les coins à
        // la retouche sans buter sur le bord.
        $m = $marge * max($l, $h);
        $normalises = array_map(fn (array $c): array => [
            'x' => round(max(0, $c[0] - $m) / $l, 5),
            'y' => round(max(0, $c[1] - $m) / $h, 5),
            'w' => round((min($l, $c[2] + $m) - max(0, $c[0] - $m)) / $l, 5),
            'h' => round((min($h, $c[3] + $m) - max(0, $c[1] - $m)) / $h, 5),
        ], $cadres);

        return self::ordreDeLecture($normalises);
    }

    /**
     * Seuil d'Otsu : celui qui sépare le mieux les deux populations de
     * l'histogramme, ici le papier et la table.
     *
     * @param  list<int>  $intensites  de 0 à 255
     */
    private static function otsu(array $intensites): int
    {
        $histogramme = array_fill(0, 256, 0);
        foreach ($intensites as $v) {
            $histogramme[$v]++;
        }

        $total = count($intensites);
        $somme = 0;
        foreach ($histogramme as $v => $n) {
            $somme += $v * $n;
        }

        [$sommeFond, $poidsFond, $meilleur, $seuil] = [0, 0, -1.0, 127];

        for ($t = 0; $t < 256; $t++) {
            $poidsFond += $histogramme[$t];
            $poidsClair = $total - $poidsFond;

            if ($poidsFond === 0 || $poidsClair === 0) {
                continue;
            }

            $sommeFond += $t * $histogramme[$t];
            $ecart = $poidsFond * $poidsClair * (($sommeFond / $poidsFond) - (($somme - $sommeFond) / $poidsClair)) ** 2;

            if ($ecart > $meilleur) {
                [$meilleur, $seuil] = [$ecart, $t];
            }
        }

        return $seuil;
    }

    /**
     * Régions claires d'un seul tenant (voisinage à quatre) : l'étiquette de
     * chaque pixel (0 pour le sombre), et chaque région par son rectangle
     * englobant et son nombre de pixels.
     *
     * @param  list<bool>  $clair
     * @return array{0: list<int>, 1: array<int, array{0: int, 1: int, 2: int, 3: int, 4: int}>}
     */
    private static function regions(array $clair, int $l, int $h): array
    {
        $vu = array_fill(0, $l * $h, 0);
        $regions = [];

        foreach ($clair as $depart => $estClair) {
            if (! $estClair || $vu[$depart]) {
                continue;
            }

            $etiquette = count($regions) + 1;
            $pile = [$depart];
            $vu[$depart] = $etiquette;
            [$x0, $y0, $x1, $y1, $aire] = [$l, $h, 0, 0, 0];

            while ($pile !== []) {
                $i = array_pop($pile);
                [$x, $y] = [$i % $l, intdiv($i, $l)];
                $aire++;
                [$x0, $y0, $x1, $y1] = [min($x0, $x), min($y0, $y), max($x1, $x), max($y1, $y)];

                foreach ([
                    $x > 0 ? $i - 1 : null,
                    $x < $l - 1 ? $i + 1 : null,
                    $y > 0 ? $i - $l : null,
                    $y < $h - 1 ? $i + $l : null,
                ] as $voisin) {
                    if ($voisin !== null && $clair[$voisin] && ! $vu[$voisin]) {
                        $vu[$voisin] = $etiquette;
                        $pile[] = $voisin;
                    }
                }
            }

            $regions[$etiquette] = [$x0, $y0, $x1, $y1, $aire];
        }

        return [$vu, $regions];
    }

    /**
     * Découpe une région là où une bande de lignes, ou de colonnes, n'est
     * presque plus remplie : c'est l'espace entre deux feuilles. Les bandes
     * qui touchent le bord de la région ne comptent pas : une feuille posée
     * de biais remplit mal ses premières et dernières lignes.
     *
     * @param  list<int>  $etiquettes
     * @param  array{0: int, 1: int, 2: int, 3: int, 4: int}  $region
     * @return list<array{0: int, 1: int, 2: int, 3: int, 4: int}>
     */
    private static function decouper(array $etiquettes, int $l, int $etiquette, array $region): array
    {
        [$x0, $y0, $x1, $y1] = $region;

        foreach (['lignes', 'colonnes'] as $sens) {
            $parLigne = $sens === 'lignes';
            [$debut, $fin] = $parLigne ? [$y0, $y1] : [$x0, $x1];
            [$travers0, $travers1] = $parLigne ? [$x0, $x1] : [$y0, $y1];

            $remplissage = [];
            for ($k = $debut; $k <= $fin; $k++) {
                $n = 0;
                for ($t = $travers0; $t <= $travers1; $t++) {
                    $n += $etiquettes[$parLigne ? $k * $l + $t : $t * $l + $k] === $etiquette ? 1 : 0;
                }
                $remplissage[$k] = $n / ($travers1 - $travers0 + 1);
            }

            // La première bande creuse, loin des bords, où couper.
            $coupe = null;
            for ($k = $debut + 2; $k <= $fin - 2; $k++) {
                if ($remplissage[$k] < self::REMPLISSAGE_ECART
                    && max($remplissage[$k - 1], $remplissage[$k - 2]) >= self::REMPLISSAGE_ECART) {
                    $coupe = $k;
                }

                if ($coupe !== null && $remplissage[$k] >= self::REMPLISSAGE_ECART) {
                    break;
                }
            }

            if ($coupe === null || $remplissage[$fin - 1] < self::REMPLISSAGE_ECART) {
                continue;
            }

            // L'espace entre les deux feuilles ne va à aucune : la seconde
            // moitié commence à la première ligne pleine après la coupe.
            $reprise = $coupe;
            while ($reprise < $fin && $remplissage[$reprise] < self::REMPLISSAGE_ECART) {
                $reprise++;
            }

            // Chaque moitié, réduite à ses pixels, peut encore se découper.
            $moities = [];
            foreach ([[$debut, $coupe - 1], [$reprise, $fin]] as [$a, $b]) {
                $moitie = self::englober($etiquettes, $l, $etiquette, $parLigne ? [$x0, $a, $x1, $b] : [$a, $y0, $b, $y1]);

                if ($moitie !== null) {
                    $moities = [...$moities, ...self::decouper($etiquettes, $l, $etiquette, $moitie)];
                }
            }

            return $moities;
        }

        return [$region];
    }

    /**
     * Le rectangle des pixels d'une région dans une zone, et leur nombre.
     *
     * @param  list<int>  $etiquettes
     * @param  array{0: int, 1: int, 2: int, 3: int}  $zone
     * @return array{0: int, 1: int, 2: int, 3: int, 4: int}|null
     */
    private static function englober(array $etiquettes, int $l, int $etiquette, array $zone): ?array
    {
        [$x0, $y0, $x1, $y1, $aire] = [PHP_INT_MAX, PHP_INT_MAX, -1, -1, 0];

        for ($y = $zone[1]; $y <= $zone[3]; $y++) {
            for ($x = $zone[0]; $x <= $zone[2]; $x++) {
                if ($etiquettes[$y * $l + $x] === $etiquette) {
                    [$x0, $y0, $x1, $y1] = [min($x0, $x), min($y0, $y), max($x1, $x), max($y1, $y)];
                    $aire++;
                }
            }
        }

        return $aire > 0 ? [$x0, $y0, $x1, $y1, $aire] : null;
    }

    /**
     * Une feuille qu'un trait sombre coupe de part en part donne deux régions
     * qui se chevauchent : on les réunit.
     *
     * @param  list<array{0: int, 1: int, 2: int, 3: int}>  $cadres
     * @return list<array{0: int, 1: int, 2: int, 3: int}>
     */
    private static function fusionner(array $cadres): array
    {
        do {
            $fusion = false;

            foreach ($cadres as $i => $a) {
                foreach ($cadres as $j => $b) {
                    if ($j <= $i) {
                        continue;
                    }

                    $recouvrement = max(0, min($a[2], $b[2]) - max($a[0], $b[0]))
                        * max(0, min($a[3], $b[3]) - max($a[1], $b[1]));
                    $plusPetite = min(($a[2] - $a[0]) * ($a[3] - $a[1]), ($b[2] - $b[0]) * ($b[3] - $b[1]));

                    if ($recouvrement > 0.2 * $plusPetite) {
                        $cadres[$i] = [min($a[0], $b[0]), min($a[1], $b[1]), max($a[2], $b[2]), max($a[3], $b[3])];
                        unset($cadres[$j]);
                        $fusion = true;

                        break 2;
                    }
                }
            }
        } while ($fusion);

        return array_values($cadres);
    }

    /**
     * Rangée par rangée, de gauche à droite. Une feuille appartient à la
     * rangée en cours si son centre tombe dans la hauteur de la première
     * feuille de cette rangée.
     *
     * @param  list<array{x: float, y: float, w: float, h: float}>  $cadres
     * @return list<array{x: float, y: float, w: float, h: float}>
     */
    public static function ordreDeLecture(array $cadres): array
    {
        usort($cadres, fn (array $a, array $b): int => $a['y'] <=> $b['y']);

        $rangees = [];
        foreach ($cadres as $cadre) {
            $centre = $cadre['y'] + $cadre['h'] / 2;
            $derniere = array_key_last($rangees);

            if ($derniere !== null
                && $centre >= $rangees[$derniere][0]['y']
                && $centre <= $rangees[$derniere][0]['y'] + $rangees[$derniere][0]['h']) {
                $rangees[$derniere][] = $cadre;
            } else {
                $rangees[] = [$cadre];
            }
        }

        return array_merge(...array_map(function (array $rangee): array {
            usort($rangee, fn (array $a, array $b): int => $a['x'] <=> $b['x']);

            return $rangee;
        }, $rangees ?: [[]]));
    }
}
