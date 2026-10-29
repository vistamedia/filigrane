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

/**
 * Un cadre de 300 × 400 mm accroché au mur, photographié d'en bas et un peu
 * de côté, comme on photographie une œuvre placée trop haut.
 *
 * La prise de vue est simulée par un appareil sténopé : focale équivalente
 * 26 mm (l'objectif principal d'un iPhone), image de 3024 × 4032, appareil
 * levé de 25° et tourné de 10°. Les coins du cadre sur la photographie sont
 * donc connus exactement, et ses vraies proportions aussi : 0,75.
 *
 * @return array{0: Imagick, 1: array<int, array{0: float, 1: float}>}
 */
function cadreEnPerspective(): array
{
    mt_srand(4);

    $largeurPhoto = 3024;
    $hauteurPhoto = 4032;

    // Le cadre à plat, à 3 pixels par millimètre : une baguette dorée, un
    // passe-partout, et le dessin.
    $plat = new Imagick();
    $plat->newImage(300 * PX_PAR_MM, 400 * PX_PAR_MM, new ImagickPixel('#9C7A3C'));

    $trace = new ImagickDraw();
    $trace->setFillColor(new ImagickPixel('#F1ECE0'));
    $trace->rectangle(25 * PX_PAR_MM, 25 * PX_PAR_MM, 275 * PX_PAR_MM, 375 * PX_PAR_MM);
    $trace->setFillColor(new ImagickPixel('#E6DAC0'));
    $trace->rectangle(70 * PX_PAR_MM, 80 * PX_PAR_MM, 230 * PX_PAR_MM, 320 * PX_PAR_MM);
    $trace->setFillColor(new ImagickPixel('transparent'));
    $trace->setStrokeColor(new ImagickPixel('#5A4632'));
    $trace->setStrokeWidth(4);
    $trace->ellipse(150 * PX_PAR_MM, 170 * PX_PAR_MM, 45 * PX_PAR_MM, 55 * PX_PAR_MM, 0, 360);

    for ($i = 0; $i < 12; $i++) {
        $trace->line(
            (85 + mt_rand(0, 15)) * PX_PAR_MM,
            (240 + $i * 6) * PX_PAR_MM,
            (215 - mt_rand(0, 15)) * PX_PAR_MM,
            (246 + $i * 6) * PX_PAR_MM,
        );
    }

    $plat->drawImage($trace);

    // L'appareil : rotation de 10° autour de la verticale, puis de 25° vers
    // le haut, à 80 cm du centre du cadre. L'axe des ordonnées descend : un
    // angle négatif éloigne le haut du cadre, qui paraît donc plus étroit.
    $focale = 26 * hypot($largeurPhoto, $hauteurPhoto) / 43.2666;
    [$lacet, $inclinaison, $distance] = [deg2rad(10), deg2rad(-25), 800];

    $projeter = function (float $x, float $y) use ($focale, $lacet, $inclinaison, $distance, $largeurPhoto, $hauteurPhoto): array {
        // Lacet autour de l'axe vertical…
        [$x, $z] = [$x * cos($lacet), -$x * sin($lacet)];
        // … puis inclinaison autour de l'axe horizontal.
        [$y, $z] = [$y * cos($inclinaison) - $z * sin($inclinaison), $y * sin($inclinaison) + $z * cos($inclinaison)];
        $z += $distance;

        return [$largeurPhoto / 2 + $focale * $x / $z, $hauteurPhoto / 2 + $focale * $y / $z];
    };

    $coins = [
        $projeter(-150, -200),
        $projeter(150, -200),
        $projeter(150, 200),
        $projeter(-150, 200),
    ];

    $photo = clone $plat;
    $photo->setImageBackgroundColor(new ImagickPixel('#C9B48E'));
    $photo->setImageVirtualPixelMethod(Imagick::VIRTUALPIXELMETHOD_BACKGROUND);
    $photo->setImageArtifact('distort:viewport', "{$largeurPhoto}x{$hauteurPhoto}+0+0");
    $photo->distortImage(Imagick::DISTORTION_PERSPECTIVE, [
        0, 0, ...$coins[0],
        300 * PX_PAR_MM, 0, ...$coins[1],
        300 * PX_PAR_MM, 400 * PX_PAR_MM, ...$coins[2],
        0, 400 * PX_PAR_MM, ...$coins[3],
    ], false);
    $photo->setImagePage($largeurPhoto, $hauteurPhoto, 0, 0);

    $photo->setImageFormat('jpeg');
    $photo->setOption('attenuate', '0.12');
    $photo->addNoiseImage(Imagick::NOISE_GAUSSIAN, Imagick::CHANNEL_DEFAULT);
    $photo->setImageCompressionQuality(88);
    $plat->clear();

    $normalises = array_map(
        fn (array $coin): array => [round($coin[0] / $largeurPhoto, 5), round($coin[1] / $hauteurPhoto, 5)],
        $coins,
    );

    return [$photo, $normalises];
}

[$cadre, $coinsDuCadre] = cadreEnPerspective();

// Les coins exacts accompagnent l'image : les tests y placent les poignées.
file_put_contents($destination.'/cadre-en-perspective.json', json_encode([
    'coins' => $coinsDuCadre,
    'proportions' => 0.75,
    'focale' => 26,
], JSON_PRETTY_PRINT)."\n");
echo "✓ cadre-en-perspective.json\n";

$fichiers = [
    'dessin-avec-regle.jpg' => feuille(true, 1),
    'dessin-recto.jpg' => feuille(false, 2),
    'dessin-verso.jpg' => feuille(false, 3),
    'cadre-en-perspective.jpg' => $cadre,
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
