/**
 * Fabrique les icônes PNG de l'application à partir des sources SVG.
 *
 *   make icones
 *
 * À relancer après tout changement du dessin. Voir DOCS/07-prompt-icone.md.
 *
 * Quatre sources, qui ne servent pas la même chose :
 *
 *   icone.svg           le dessin complet, plein bord — écran d'accueil, PWA
 *   icone-maskable.svg  le même, motif réduit, pour les systèmes qui rognent
 *   favicon.svg         une version simplifiée, qui tient dans 32 pixels
 *   logo.svg            le motif seul, sans fond, posé dans l'interface
 *
 * Le rendu passe par le Chromium déjà installé pour les tests, et non par
 * ImageMagick. Ce n'est pas un détail : le moteur SVG interne d'ImageMagick
 * compose mal les transformations imbriquées — il plaçait le crayon hors du
 * cadre — et il échoue **en silence**, sans message ni code d'erreur. Un
 * navigateur, lui, rend exactement ce que verront les visiteurs, et le dessin
 * n'a plus à se restreindre aux aplats.
 */
import { chromium } from '@playwright/test';
import { copyFile, mkdir, readFile, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const RACINE = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const SOURCES = path.join(RACINE, 'resources/icones');
const DESTINATION = path.join(RACINE, 'public/icones');

/** Ce que chaque source doit produire : fichier de sortie => côté en pixels. */
const RENDUS = {
    'icone': {
        'icone-180x180.png': 180,   // écran d'accueil iOS
        'icone-192x192.png': 192,   // installation Android et Chrome
        'icone-512x512.png': 512,
    },
    'icone-maskable': {
        'icone-maskable-192x192.png': 192,
        'icone-maskable-512x512.png': 512,
    },
    'favicon': {
        'icone-32x32.png': 32,      // onglet du navigateur
    },
};

/** Les SVG recopiés tels quels, servis directement par le serveur. */
const COPIES = ['icone', 'icone-maskable', 'favicon', 'logo'];

/** Impose au SVG une taille de rendu exacte, sans toucher à son viewBox. */
function dimensionner(svg, taille) {
    return svg.replace(
        /<svg\b[^>]*?>/,
        (balise) => balise
            .replace(/\s(width|height)="[^"]*"/g, '')
            .replace('<svg', `<svg width="${taille}" height="${taille}"`),
    );
}

await mkdir(DESTINATION, { recursive: true });

for (const nom of COPIES) {
    await copyFile(path.join(SOURCES, `${nom}.svg`), path.join(DESTINATION, `${nom}.svg`));
    console.log(`✓ ${nom}.svg`);
}

/*
 * Variante sombre du logo.
 *
 * Le logo n'a pas de tuile : ce qui est « du papier » y reprend la couleur de
 * la surface sur laquelle il est posé. En thème sombre, ce crème faisait une
 * vignette blanche collée sur la barre. Plutôt qu'un second dessin à tenir à
 * jour, on dérive la variante du fichier source — une seule couleur change.
 */
const logo = await readFile(path.join(SOURCES, 'logo.svg'), 'utf8');

await writeFile(
    path.join(DESTINATION, 'logo-sombre.svg'),
    logo.replaceAll('#FBF8F2', '#1C1A17'),
);

console.log('✓ logo-sombre.svg');

const navigateur = await chromium.launch();
const page = await (await navigateur.newContext({ deviceScaleFactor: 1 })).newPage();

for (const [nom, fichiers] of Object.entries(RENDUS)) {
    const svg = await readFile(path.join(SOURCES, `${nom}.svg`), 'utf8');

    for (const [fichier, taille] of Object.entries(fichiers)) {
        await page.setContent(
            `<body style="margin:0"><div id="icone" style="width:${taille}px;height:${taille}px;font-size:0">`
            + dimensionner(svg, taille)
            + '</div></body>',
        );

        // omitBackground laisse transparent ce que le dessin ne peint pas :
        // les icônes ont leur propre fond, le logo n'en a pas.
        await page.locator('#icone').screenshot({
            path: path.join(DESTINATION, fichier),
            omitBackground: true,
        });

        console.log(`✓ ${fichier}`);
    }
}

await navigateur.close();
