#!/usr/bin/env php
<?php

/**
 * Fabrique public/icones/favicon.ico à partir du PNG 32 pixels.
 *
 *   docker compose exec app php scripts/generer-favicon-ico.php
 *
 * Lancé par `make icones`, après scripts/generer-icones.mjs.
 *
 * Le .ico ne sert plus qu'aux vieux navigateurs et aux favoris de macOS :
 * tout le reste préfère le SVG. On part du PNG déjà rendu, et non du SVG —
 * c'est une simple conversion de pixels, le seul travail dans lequel le moteur
 * SVG d'ImageMagick ne peut pas se tromper, puisqu'il n'intervient pas.
 */

$source = dirname(__DIR__).'/public/icones/icone-32x32.png';
$cible = dirname(__DIR__).'/public/icones/favicon.ico';

if (! is_readable($source)) {
    fwrite(STDERR, "Source introuvable : {$source}\nLancez d'abord : node scripts/generer-icones.mjs\n");
    exit(1);
}

$ico = new Imagick();
$ico->readImage($source);
$ico->setImageFormat('ico');
$ico->writeImage($cible);
$ico->clear();

echo "✓ favicon.ico\n";
