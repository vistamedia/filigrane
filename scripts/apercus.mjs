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
    { nom: 'dessins', chemin: '/dessins', hauteur: 880 },
    { nom: 'fiche', chemin: null, hauteur: 880 },
    { nom: 'diaporama', chemin: '/diaporama', hauteur: 980 },
];

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

    if (vue.chemin) {
        await page.goto(`${BASE}${vue.chemin}`);
    } else {
        // La fiche d'un dessin décrit : on y arrive par la planche.
        await page.goto(`${BASE}/dessins`);
        await page.locator('.fi-tabs-item', { hasText: 'Décrits' }).click();
        await page.getByRole('link', { name: 'Décrire' }).first().click();
        await page.waitForURL(/\/decrire/);
    }

    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(600);
    await page.screenshot({ path: `${SORTIE}/${vue.nom}.jpg`, ...FORMAT });

    console.log(`✓ ${SORTIE}/${vue.nom}.jpg`);
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
