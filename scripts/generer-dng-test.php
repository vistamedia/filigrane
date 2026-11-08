#!/usr/bin/env php
<?php

/**
 * Fabrique tests/fixtures/prise-raw.dng, un petit DNG pour les tests.
 *
 *   docker compose exec app php scripts/generer-dng-test.php
 *
 * Les vrais DNG (ProRAW d'iPhone) pèsent 66 Mo et montrent des dessins de la
 * collection : ils n'ont rien à faire dans un dépôt public. Aucun outil
 * courant n'écrit de DNG ; celui-ci est donc assemblé à la main. Un DNG est un
 * TIFF muni de quelques balises propres (DNGVersion, ColorMatrix1…). Ici, en
 * « linear raw » : trois composantes de 16 bits par pixel, déjà dématricées,
 * que libraw sait lire comme un ProRAW.
 *
 * La scène, à l'endroit : un fond clair, et un carré sombre en haut à gauche.
 * Les pixels sont enregistrés tête en bas, avec l'orientation 3 (rotation de
 * 180°), comme un iPhone tenu à l'envers : l'import doit la remettre droite.
 * Les données EXIF portent une date de prise de vue et une focale de 24 mm.
 */

const LARGEUR = 240;
const HAUTEUR = 180;

const BYTE = 1;
const ASCII = 2;
const SHORT = 3;
const LONG = 4;
const RATIONAL = 5;
const SRATIONAL = 10;

/**
 * Une entrée de répertoire TIFF : son type, son nombre de valeurs et ses
 * octets, petit-boutistes.
 *
 * @return array{0: int, 1: int, 2: string}
 */
function entree(int $type, array|string $valeurs): array
{
    return match ($type) {
        BYTE => [$type, count($valeurs), pack('C*', ...$valeurs)],
        ASCII => [$type, strlen($valeurs) + 1, $valeurs."\0"],
        SHORT => [$type, count($valeurs), pack('v*', ...$valeurs)],
        LONG => [$type, count($valeurs), pack('V*', ...$valeurs)],
        RATIONAL => [$type, count($valeurs), implode('', array_map(fn (array $r) => pack('VV', ...$r), $valeurs))],
        SRATIONAL => [$type, count($valeurs), implode('', array_map(
            fn (array $r) => pack('VV', $r[0] & 0xFFFFFFFF, $r[1]),
            $valeurs,
        ))],
    };
}

/**
 * Un répertoire placé à $debut, suivi des valeurs trop longues pour tenir
 * dans leur entrée (plus de 4 octets).
 */
function repertoire(array $entrees, int $debut): string
{
    ksort($entrees);

    $taille = 2 + count($entrees) * 12 + 4;
    $tete = pack('v', count($entrees));
    $annexe = '';

    foreach ($entrees as $balise => [$type, $nombre, $octets]) {
        if (strlen($octets) <= 4) {
            $valeur = str_pad($octets, 4, "\0");
        } else {
            $valeur = pack('V', $debut + $taille + strlen($annexe));
            $annexe .= $octets.(strlen($octets) % 2 ? "\0" : '');
        }

        $tete .= pack('vvV', $balise, $type, $nombre).$valeur;
    }

    return $tete.pack('V', 0).$annexe;
}

// La scène à l'endroit, puis retournée pour l'enregistrement.
$clair = (int) (0.55 * 65535);
$sombre = (int) (0.04 * 65535);
$lignes = [];

for ($y = 0; $y < HAUTEUR; $y++) {
    $ligne = '';
    for ($x = 0; $x < LARGEUR; $x++) {
        $dansLeCarre = $x >= 20 && $x < 100 && $y >= 20 && $y < 80;
        $v = $dansLeCarre ? $sombre : $clair;
        $ligne .= pack('vvv', $v, $v, $v);
    }
    $lignes[] = $ligne;
}

// Rotation de 180° : lignes et pixels dans l'ordre inverse.
$pixels = implode('', array_map(
    fn (string $ligne) => implode('', array_reverse(str_split($ligne, 6))),
    array_reverse($lignes),
));

$exif = [
    36867 => entree(ASCII, '2026:11:08 15:56:06'),   // DateTimeOriginal
    41989 => entree(SHORT, [24]),                    // FocalLengthIn35mmFilm
];

// XYZ → sRGB linéaire (D65) : la matrice que libraw attend pour en déduire
// les couleurs. Une matrice identité donnerait des couleurs fausses.
$versSrgb = [3.2406, -1.5372, -0.4986, -0.9689, 1.8758, 0.0415, 0.0557, -0.2040, 1.0570];

$principal = fn (int $exifIfd, int $donnees) => [
    254 => entree(LONG, [0]),                          // NewSubfileType
    256 => entree(LONG, [LARGEUR]),
    257 => entree(LONG, [HAUTEUR]),
    258 => entree(SHORT, [16, 16, 16]),                // BitsPerSample
    259 => entree(SHORT, [1]),                         // sans compression
    262 => entree(SHORT, [34892]),                     // LinearRaw
    271 => entree(ASCII, 'Filigrane'),
    272 => entree(ASCII, 'Essai'),
    273 => entree(LONG, [$donnees]),                   // StripOffsets
    274 => entree(SHORT, [3]),                         // Orientation : 180°
    277 => entree(SHORT, [3]),                         // SamplesPerPixel
    278 => entree(LONG, [HAUTEUR]),                    // RowsPerStrip
    279 => entree(LONG, [strlen($pixels)]),            // StripByteCounts
    284 => entree(SHORT, [1]),                         // PlanarConfiguration
    34665 => entree(LONG, [$exifIfd]),                 // ExifIFD
    50706 => entree(BYTE, [1, 4, 0, 0]),               // DNGVersion
    50707 => entree(BYTE, [1, 1, 0, 0]),               // DNGBackwardVersion
    50708 => entree(ASCII, 'Filigrane Essai'),         // UniqueCameraModel
    50721 => entree(SRATIONAL, array_map(fn (float $v) => [(int) round($v * 10000), 10000], $versSrgb)),
    50728 => entree(RATIONAL, [[1, 1], [1, 1], [1, 1]]), // AsShotNeutral
    50778 => entree(SHORT, [21]),                      // CalibrationIlluminant1 : D65
];

// Les tailles ne dépendent pas des adresses : un premier passage les donne.
$taillePrincipal = strlen(repertoire($principal(0, 0), 8));
$debutExif = 8 + $taillePrincipal;
$debutDonnees = $debutExif + strlen(repertoire($exif, $debutExif));

$fichier = 'II'.pack('vV', 42, 8)
    .repertoire($principal($debutExif, $debutDonnees), 8)
    .repertoire($exif, $debutExif)
    .$pixels;

$destination = dirname(__DIR__).'/tests/fixtures/prise-raw.dng';
file_put_contents($destination, $fichier);

echo "{$destination} : ".strlen($fichier)." octets\n";
