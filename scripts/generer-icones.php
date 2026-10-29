#!/usr/bin/env php
<?php

/**
 * Fabrique les icônes PNG de l'application à partir des deux sources SVG.
 *
 *   docker compose exec app php scripts/generer-icones.php
 *
 * À relancer après tout changement de la charte graphique.
 */

$root = dirname(__DIR__);
$sources = [
    'icone' => $root.'/resources/icones/icone.svg',
    'icone-maskable' => $root.'/resources/icones/icone-maskable.svg',
];
$destination = $root.'/public/icones';

if (! is_dir($destination) && ! mkdir($destination, 0o755, true)) {
    fwrite(STDERR, "Impossible de créer {$destination}\n");
    exit(1);
}

/** Tailles attendues par les navigateurs et par iOS. */
$sizes = [
    'icone' => [32, 180, 192, 512],
    'icone-maskable' => [192, 512],
];

foreach ($sources as $name => $path) {
    if (! is_readable($path)) {
        fwrite(STDERR, "Source introuvable : {$path}\n");
        exit(1);
    }

    // On copie aussi le SVG tel quel : c'est lui qui sert de favicon.
    copy($path, $destination.'/'.$name.'.svg');

    foreach ($sizes[$name] as $size) {
        $imagick = new Imagick();
        $imagick->setBackgroundColor(new ImagickPixel('transparent'));
        $imagick->setResolution($size * 2, $size * 2);
        $imagick->readImageBlob(file_get_contents($path));
        $imagick->setImageFormat('png32');
        $imagick->resizeImage($size, $size, Imagick::FILTER_LANCZOS, 1);

        $target = sprintf('%s/%s-%dx%d.png', $destination, $name, $size, $size);
        $imagick->writeImage($target);
        $imagick->clear();

        printf("✓ %s\n", basename($target));
    }
}

// Le favicon.ico reste utile pour les vieux navigateurs et les favoris macOS.
$ico = new Imagick();
$ico->setBackgroundColor(new ImagickPixel('transparent'));
$ico->setResolution(128, 128);
$ico->readImageBlob(file_get_contents($sources['icone']));
$ico->setImageFormat('png32');
$ico->resizeImage(32, 32, Imagick::FILTER_LANCZOS, 1);
$ico->setImageFormat('ico');
$ico->writeImage($destination.'/favicon.ico');
$ico->clear();

copy($sources['icone'], $destination.'/favicon.svg');

echo "✓ favicon.ico\n✓ favicon.svg\n";
