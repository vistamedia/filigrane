#!/usr/bin/env php
<?php

/**
 * Fabrique les images de test utilisées par la suite Playwright.
 *
 *   docker compose exec app php scripts/generer-images-test.php
 *
 * Elles imitent ce que donne une vraie séance : une feuille claire posée sur
 * une table sombre, photographiée de face, avec ou sans règle de référence.
 *
 * Échelle choisie : 3 pixels par millimètre. La feuille mesure donc
 * 900 × 1200 px, soit 300 × 400 mm, et la règle 600 px, soit 200 mm.
 */

$destination = dirname(__DIR__).'/tests/fixtures';

if (! is_dir($destination) && ! mkdir($destination, 0o755, true)) {
    fwrite(STDERR, "Impossible de créer {$destination}\n");
    exit(1);
}

const LARGEUR = 3000;
const HAUTEUR = 2250;
const PX_PAR_MM = 3;

function feuille(bool $avecRegle, int $graine): Imagick
{
    mt_srand($graine);

    $image = new Imagick();
    $image->newImage(LARGEUR, HAUTEUR, new ImagickPixel('#242220'));
    $image->setImageFormat('jpeg');

    $largeurFeuille = 300 * PX_PAR_MM;
    $hauteurFeuille = 400 * PX_PAR_MM;
    $gauche = (int) ((LARGEUR - $largeurFeuille) / 2);
    $haut = (int) ((HAUTEUR - $hauteurFeuille) / 2);

    // La feuille : un papier crème, légèrement irrégulier sur les bords.
    $dessin = new ImagickDraw();
    $dessin->setFillColor(new ImagickPixel('#EFE7D6'));
    $dessin->rectangle($gauche, $haut, $gauche + $largeurFeuille, $haut + $hauteurFeuille);

    // Un sujet au trait, pour que la vignette ne soit pas qu'un rectangle vide.
    $dessin->setFillColor(new ImagickPixel('transparent'));
    $dessin->setStrokeColor(new ImagickPixel('#3A3630'));
    $dessin->setStrokeWidth(4);

    $centreX = $gauche + $largeurFeuille / 2;
    $centreY = $haut + $hauteurFeuille / 2;

    $dessin->ellipse($centreX, $centreY - 180, 150, 190, 0, 360);

    for ($i = 0; $i < 14; $i++) {
        $dessin->line(
            $centreX - 220 + mt_rand(0, 60),
            $centreY + 120 + $i * 24,
            $centreX + 220 - mt_rand(0, 60),
            $centreY + 150 + $i * 24,
        );
    }

    $image->drawImage($dessin);

    if ($avecRegle) {
        // Une règle de 200 mm posée sous la feuille, graduée tous les 10 mm.
        $regle = new ImagickDraw();
        $longueur = 200 * PX_PAR_MM;
        $x = (int) (($image->getImageWidth() - $longueur) / 2);
        $y = HAUTEUR - 220;

        $regle->setFillColor(new ImagickPixel('#F6F3EC'));
        $regle->rectangle($x, $y, $x + $longueur, $y + 70);

        $regle->setStrokeColor(new ImagickPixel('#2A2724'));
        $regle->setStrokeWidth(3);

        for ($mm = 0; $mm <= 200; $mm += 10) {
            $tick = $x + $mm * PX_PAR_MM;
            $regle->line($tick, $y, $tick, $y + ($mm % 50 === 0 ? 40 : 22));
        }

        $image->drawImage($regle);
    }

    // Un léger grain : sur un fond parfaitement lisse, le détourage réussirait
    // trop facilement et le test ne prouverait rien.
    $image->setOption('attenuate', '0.12');
    $image->addNoiseImage(Imagick::NOISE_GAUSSIAN, Imagick::CHANNEL_DEFAULT);
    $image->setImageCompressionQuality(88);

    return $image;
}

$fichiers = [
    'dessin-avec-regle.jpg' => feuille(true, 1),
    'dessin-recto.jpg' => feuille(false, 2),
    'dessin-verso.jpg' => feuille(false, 3),
];

foreach ($fichiers as $nom => $image) {
    $image->writeImage($destination.'/'.$nom);
    printf("✓ %s (%d × %d)\n", $nom, $image->getImageWidth(), $image->getImageHeight());
    $image->clear();
}

// Un fichier qui n'est pas une image, pour éprouver le refus.
file_put_contents($destination.'/pas-une-image.txt', "Ceci n'est pas un dessin.\n");
echo "✓ pas-une-image.txt\n";

/*
 * Pas de HEIC fabriqué ici : ImageMagick sait lire ce format mais pas l'écrire
 * (délégué « r-- »). Pour éprouver la conversion des photos d'iPhone, déposez
 * un vrai fichier dans tests/fixtures/dessin-iphone.heic — le test dédié se
 * déclenche automatiquement, et se met en attente s'il est absent.
 */
if (! file_exists($destination.'/dessin-iphone.heic')) {
    echo "· dessin-iphone.heic absent (déposez une photo d'iPhone pour activer le test HEIC)\n";
}
