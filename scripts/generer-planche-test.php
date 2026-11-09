#!/usr/bin/env php
<?php

/**
 * Fabrique tests/fixtures/planche-de-dessins.jpg : cinq petites feuilles sur
 * une table sombre, comme une planche de mini-dessins photographiée d'un
 * coup (D-45).
 *
 *   docker compose exec app php scripts/generer-planche-test.php
 *
 * Trois feuilles en haut, deux en bas, de tailles différentes, chacune avec
 * quelques traits sombres. Les deux premières sont reliées par un reflet clair
 * sur la table, trop épais pour disparaître à l'ouverture morphologique : la
 * détection doit tout de même les séparer. Une miette claire traîne sur la
 * table : elle ne doit pas devenir un dessin.
 *
 * Les rectangles des feuilles sont écrits à côté, dans planche-de-dessins.json,
 * en coordonnées normalisées.
 */

const LARGEUR = 2400;
const HAUTEUR = 1800;

$feuilles = [
    [180, 160, 640, 760],
    [760, 220, 1240, 700],
    [1500, 140, 2200, 620],
    [300, 1000, 900, 1650],
    [1250, 1050, 2050, 1600],
];

$image = new Imagick();
$image->newImage(LARGEUR, HAUTEUR, new ImagickPixel('#2e1c14'));
$image->addNoiseImage(Imagick::NOISE_GAUSSIAN);

$dessin = new ImagickDraw();

// Le reflet qui relie les deux premières feuilles.
$dessin->setFillColor('#e6dfd6');
$dessin->rectangle(600, 420, 800, 455);

// La miette.
$dessin->rectangle(1350, 850, 1362, 860);

foreach ($feuilles as [$x0, $y0, $x1, $y1]) {
    $dessin->setFillColor('#ece4d2');
    $dessin->rectangle($x0, $y0, $x1, $y1);

    $dessin->setStrokeColor('#3b3530');
    $dessin->setStrokeWidth(5);
    for ($k = 1; $k <= 4; $k++) {
        $y = $y0 + $k * ($y1 - $y0) / 5;
        $dessin->line($x0 + 40, $y, $x1 - 40, $y + 20);
    }
    $dessin->setStrokeWidth(0);
    $dessin->setStrokeOpacity(0);
}

$image->drawImage($dessin);
$image->setImageFormat('jpeg');
$image->setImageCompressionQuality(85);

$dossier = dirname(__DIR__).'/tests/fixtures';
$image->writeImage($dossier.'/planche-de-dessins.jpg');

file_put_contents($dossier.'/planche-de-dessins.json', json_encode(array_map(fn (array $f): array => [
    'x' => $f[0] / LARGEUR,
    'y' => $f[1] / HAUTEUR,
    'w' => ($f[2] - $f[0]) / LARGEUR,
    'h' => ($f[3] - $f[1]) / HAUTEUR,
], $feuilles), JSON_PRETTY_PRINT)."\n");

echo "planche-de-dessins.jpg et .json écrits dans {$dossier}\n";
