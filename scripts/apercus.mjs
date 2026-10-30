/**
 * Fabrique les aperçus de la page publique « À propos ».
 *
 *   make apercus
 *
 * Ces images-là ne sont pas les captures de la documentation : elles sont
 * publiées sur Internet. Elles sont donc prises sur le jeu d'essai — des
 * fiches plausibles, aucune donnée réelle de la collection.
 */
import { chromium } from '@playwright/test';
import { mkdir, readFile } from 'node:fs/promises';

const BASE = process.env.FILIGRANE_URL ?? 'http://localhost:8080';
const COMPTE = process.env.FILIGRANE_COMPTE ?? 'emmanuel@filigrane.test';
const MOTDEPASSE = process.env.FILIGRANE_MOTDEPASSE ?? 'filigrane';
const SORTIE = 'public/apercus';

const VUES = [
    { nom: 'dessins', hauteur: 880, ouvrir: (page) => page.goto(`${BASE}/dessins`) },
    { nom: 'fiche', hauteur: 880, ouvrir: ouvrirUneFiche },
    { nom: 'diaporama', hauteur: 980, ouvrir: (page) => page.goto(`${BASE}/diaporama`) },
    { nom: 'retouche', hauteur: 960, ouvrir: redresserUnCadre },
];

/* Les coins exacts du cadre de l'image fabriquée (scripts/generer-images-test.php). */
const CADRE = JSON.parse(await readFile('tests/fixtures/cadre-en-perspective.json', 'utf8'));

/*
 * JPEG et non PNG : ces images partent sur Internet, et une capture
 * d'interface en PNG à deux fois la définition pèse trois mégaoctets.
 */
const FORMAT = { type: 'jpeg', quality: 78 };

await mkdir(SORTIE, { recursive: true });

const navigateur = await chromium.launch();
const contexte = await navigateur.newContext({
    viewport: { width: 1440, height: 900 },
    deviceScaleFactor: 2,
    locale: 'fr-FR',
});
const page = await contexte.newPage();

await page.goto(`${BASE}/login`);
await page.getByLabel('Adresse e-mail').fill(COMPTE);
await page.locator('input[type="password"]').fill(MOTDEPASSE);
await page.locator('button[type="submit"]').click();
await page.waitForURL(/\/$|\/dessins/);

for (const vue of VUES) {
    page.setViewportSize({ width: 1440, height: vue.hauteur });

    await vue.ouvrir(page);

    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(600);
    await page.screenshot({ path: `${SORTIE}/${vue.nom}.jpg`, ...FORMAT });

    console.log(`✓ ${SORTIE}/${vue.nom}.jpg`);
}

/** La fiche d'un dessin décrit : on y arrive par la planche. */
async function ouvrirUneFiche(page) {
    await page.goto(`${BASE}/dessins`);
    await page.locator('.fi-tabs-item', { hasText: 'Décrits' }).click();
    await page.getByRole('link', { name: 'Décrire' }).first().click();
    await page.waitForURL(/\/decrire/);
}

/**
 * La retouche d'un cadre photographié d'en bas : les quatre pastilles sur ses
 * coins, l'aperçu redressé à côté, la lumière réglée par « Automatique ».
 */
async function redresserUnCadre(page) {
    await page.goto(`${BASE}/dessins`);
    await page.locator('.fi-tabs-item', { hasText: 'Décrits' }).click();
    await page.locator('.fi-ta-record', { hasText: 'Vers 1750' }).getByRole('link', { name: 'Décrire' }).click();
    await page.waitForURL(/\/decrire/);

    await page.locator('.fi-ta-row', { hasText: 'Encadrement' }).getByRole('link', { name: 'Retoucher' }).click();
    await page.getByRole('heading', { name: /Retoucher FIL-/ }).waitFor();

    const scene = await page.locator('[x-ref="scene"]').boundingBox();
    const coins = ['supérieur gauche', 'supérieur droit', 'inférieur droit', 'inférieur gauche'];

    for (const [i, coin] of coins.entries()) {
        const pastille = await page.getByRole('button', { name: `Coin ${coin} de l'œuvre` }).boundingBox();
        const [fx, fy] = CADRE.coins[i];

        await page.mouse.move(pastille.x + pastille.width / 2, pastille.y + pastille.height / 2);
        await page.mouse.down();
        await page.mouse.move(scene.x + scene.width * fx, scene.y + scene.height * fy, { steps: 8 });
        await page.mouse.up();
    }

    // Le clic fait défiler la page jusqu'au bouton : on remonte ensuite, pour
    // que l'image et son aperçu figurent dans la capture.
    await page.getByRole('button', { name: 'Automatique' }).click();
    await page.mouse.move(0, 0);
    await page.evaluate(() => window.scrollTo(0, 0));
}

/*
 * La famille d'icônes, côte à côte.
 *
 * En PNG et sur fond transparent, contrairement aux captures d'interface : la
 * planche est montrée sur la page publique, qui a un thème clair et un thème
 * sombre, et sur fond opaque elle ferait une tache dans l'un des deux.
 */
await plancheDIcones(page);

await navigateur.close();

async function plancheDIcones(page) {
    const lire = async (nom, hauteur) => (await readFile(`resources/icones/${nom}.svg`, 'utf8'))
        .replace(/<svg\b[^>]*>/, (balise) => balise
            .replace(/\s(width|height)="[^"]*"/g, '')
            .replace('<svg', `<svg height="${hauteur}"`));

    const tuile = async (nom, hauteur, legende, arrondi = true) => `
        <figure style="margin:0;text-align:center">
          <div style="display:inline-block;font-size:0${arrondi ? `;border-radius:${Math.round(hauteur * 0.22)}px;overflow:hidden` : ''}">
            ${await lire(nom, hauteur)}
          </div>
          <figcaption style="margin-top:12px;font:13px/1.4 ui-serif,Georgia,serif;color:#8A8178">${legende}</figcaption>
        </figure>`;

    await page.setViewportSize({ width: 1440, height: 420 });
    await page.setContent(`<body style="margin:0;padding:28px;display:inline-block">
        <div id="planche" style="display:flex;gap:40px;align-items:flex-end">
          ${await tuile('icone', 160, 'L’icône — 180 px')}
          ${await tuile('icone-maskable', 120, 'La variante rognée en cercle')}
          ${await tuile('favicon', 64, 'Le favicon — 32 px')}
          ${await tuile('logo', 64, 'Le logo, dans l’interface', false)}
        </div></body>`);

    for (const cible of [`${SORTIE}/icones.png`, 'DOCS/captures/icone.png']) {
        await page.locator('#planche').screenshot({ path: cible, omitBackground: true });
        console.log(`✓ ${cible}`);
    }
}
