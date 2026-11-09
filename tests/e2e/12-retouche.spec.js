import { expect, test } from '@playwright/test';
import { execFile } from 'node:child_process';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import { promisify } from 'node:util';
import { fixture, surveillerLesErreurs } from './aide.js';

const executer = promisify(execFile);

/**
 * Retouche d'une photographie d'œuvre encadrée.
 *
 * L'image de test figure un cadre de 300 × 400 mm photographié d'en bas et
 * de côté : sur la photographie, c'est un trapèze. Ses coins exacts sont
 * fournis à côté de l'image (voir scripts/generer-images-test.php).
 */
const CADRE = JSON.parse(readFileSync(fixture('cadre-en-perspective.json'), 'utf8'));

const POIGNEES = [
    'Coin supérieur gauche de l\'œuvre',
    'Coin supérieur droit de l\'œuvre',
    'Coin inférieur droit de l\'œuvre',
    'Coin inférieur gauche de l\'œuvre',
];

test.describe.configure({ mode: 'serial' });

/** Fait glisser une poignée jusqu'à un point donné de la photographie. */
async function deplacer(page, etiquette, fx, fy) {
    const scene = page.locator('[x-ref="scene"]');
    const cadre = await scene.boundingBox();
    const poignee = page.getByRole('button', { name: etiquette });
    const depart = await poignee.boundingBox();

    await page.mouse.move(depart.x + depart.width / 2, depart.y + depart.height / 2);
    await page.mouse.down();
    await page.mouse.move(cadre.x + cadre.width * fx, cadre.y + cadre.height * fy, { steps: 8 });
    await page.mouse.up();
}

/**
 * Ce que montre le coin supérieur gauche de l'image principale de la fiche :
 * le mur beige (bleu ≈ 142) sur la photographie brute, la baguette dorée
 * (bleu ≈ 60) une fois le cadre redressé et recadré.
 */
async function coinDeLaPhoto(page) {
    const image = page.locator('img[alt^="LBS-"]').first();
    await expect(image).toHaveJSProperty('complete', true);

    const bleu = await image.evaluate((img) => {
        const toile = document.createElement('canvas');
        [toile.width, toile.height] = [img.naturalWidth, img.naturalHeight];
        const contexte = toile.getContext('2d');
        contexte.drawImage(img, 0, 0);

        const cote = Math.round(img.naturalWidth * 0.02);
        const { data } = contexte.getImageData(cote, cote, cote, cote);
        let somme = 0;
        for (let i = 2; i < data.length; i += 4) somme += data[i];

        return somme / (data.length / 4);
    });

    return bleu < 100 ? 'baguette' : 'mur';
}

let fiche;

test('quatre coins posés sur le cadre suffisent à le redresser', async ({ page }, infos) => {
    const erreurs = surveillerLesErreurs(page);

    await page.goto('/studio-photo');
    await page.locator('input[capture]').setInputFiles(fixture('cadre-en-perspective.jpg'));
    await expect(page.getByText(/Fiche LBS-\d{4} créée/)).toBeVisible({ timeout: 60_000 });

    await page.locator('.fi-filigrane-vignette').first().click();
    await expect(page).toHaveURL(/\/decrire/);
    fiche = page.url();

    // Avant retouche : la photographie entière, le mur jusque dans les coins.
    expect(await coinDeLaPhoto(page)).toBe('mur');
    const avant = await page.locator('img[alt^="LBS-"]').first().getAttribute('src');

    await page.getByRole('link', { name: 'Redresser, recadrer' }).click();
    await expect(page.getByRole('heading', { name: /Retoucher LBS-/ })).toBeVisible();

    for (const [i, etiquette] of POIGNEES.entries()) {
        await deplacer(page, etiquette, ...CADRE.coins[i]);
    }

    // Le trapèze redevient un rectangle de 300 × 400 mm.
    await expect(page.getByText('0,75 (en hauteur)')).toBeVisible();

    // Un quart de tour couche l'image, un autre la redresse.
    await page.getByRole('button', { name: 'Tourner à droite' }).click();
    await expect(page.getByText('1,33 (en largeur)')).toBeVisible();
    await page.getByRole('button', { name: 'Tourner à gauche' }).click();
    await expect(page.getByText('0,75 (en hauteur)')).toBeVisible();

    // Le réglage automatique étire les niveaux de l'œuvre.
    await page.getByRole('button', { name: 'Automatique' }).click();
    await expect(page.locator('#contraste')).not.toHaveValue('1');

    // Remonté en haut de page : sinon la barre supérieure, qui reste
    // accrochée au défilement, se retrouve au milieu de la capture.
    await page.evaluate(() => window.scrollTo(0, 0));
    await page.screenshot({ path: `DOCS/captures/${infos.project.name}-retouche.png`, fullPage: true });

    await page.getByRole('button', { name: 'Enregistrer', exact: true }).click();
    await expect(page).toHaveURL(/\/decrire/, { timeout: 30_000 });
    await expect(page.getByText('Photographie retouchée')).toBeVisible();

    // La fiche montre désormais le cadre seul, redressé, sous une adresse
    // neuve que le cache du navigateur ne connaît pas.
    const image = page.locator('img[alt^="LBS-"]').first();
    expect(await image.getAttribute('src')).not.toBe(avant);
    expect(await coinDeLaPhoto(page)).toBe('baguette');
    expect(await image.evaluate((img) => img.naturalWidth / img.naturalHeight)).toBeCloseTo(0.75, 2);

    await expect(page.getByRole('link', { name: 'Reprendre la retouche' })).toBeVisible();
    await expect(page.getByText('retouchée', { exact: true })).toBeVisible();

    expect(erreurs).toEqual([]);
});

test('la retouche se reprend là où on l\'avait laissée, même après le ménage de nuit', async ({ page }) => {
    // Le ménage effaçait la photographie d'origine mise de côté : le
    // lendemain, l'outil n'affichait plus qu'une bande vide.
    await executer('docker', ['compose', 'exec', '-T', 'app', 'php', 'artisan', 'filigrane:menage'], {
        cwd: path.resolve(import.meta.dirname, '../..'),
    });

    await page.goto(fiche);
    await page.getByRole('link', { name: 'Reprendre la retouche' }).click();

    const source = page.locator('[x-ref="source"]');
    await expect(source).toHaveJSProperty('complete', true);
    expect(await source.evaluate((img) => img.naturalWidth)).toBeGreaterThan(0);

    // Chaque poignée est revenue sur son coin du cadre.
    const scene = await page.locator('[x-ref="scene"]').boundingBox();
    expect(scene.height).toBeGreaterThan(200);

    for (const [i, etiquette] of POIGNEES.entries()) {
        const poignee = await page.getByRole('button', { name: etiquette }).boundingBox();
        const [fx, fy] = CADRE.coins[i];

        expect(Math.abs(poignee.x + poignee.width / 2 - (scene.x + scene.width * fx))).toBeLessThan(3);
        expect(Math.abs(poignee.y + poignee.height / 2 - (scene.y + scene.height * fy))).toBeLessThan(3);
    }
});

test('l\'original s\'affiche dans l\'application, jamais dans un nouvel onglet', async ({ page, context }) => {
    const erreurs = surveillerLesErreurs(page);
    await page.goto(fiche);

    const visionneuse = page.getByRole('dialog', { name: 'Photographie en grand' });
    const image = visionneuse.locator('img');

    // Depuis le panneau d'images de la fiche…
    await page.getByRole('button', { name: 'Pleine résolution, sans retouche' }).click();
    await expect(visionneuse).toBeVisible();
    await expect(visionneuse.getByText('Original en pleine résolution, sans retouche')).toBeVisible();
    await expect(image).toHaveAttribute('src', /\/photos\/\d+\/original$/);

    // …c'est bien l'original, pas la version web réduite.
    await expect(image).toHaveJSProperty('complete', true);
    expect(await image.evaluate((img) => img.naturalWidth)).toBe(3024);

    // Un clic l'agrandit à sa taille réelle, un autre la remet à l'écran.
    const ecran = page.viewportSize();
    await image.click();
    expect((await image.boundingBox()).width).toBeGreaterThan(ecran.width);
    await image.click();
    expect((await image.boundingBox()).height).toBeLessThan(ecran.height);

    await visionneuse.getByRole('button', { name: 'Fermer' }).click();
    await expect(visionneuse).toBeHidden();

    // …et depuis la liste des photographies.
    await page.getByRole('button', { name: 'Original', exact: true }).first().click();
    await expect(visionneuse).toBeVisible();
    await expect(image).toHaveAttribute('src', /\/photos\/\d+\/original$/);

    // On est resté sur la fiche, dans le même onglet.
    expect(context.pages()).toHaveLength(1);
    expect(page.url()).toBe(fiche);

    await page.keyboard.press('Escape');
    await expect(visionneuse).toBeHidden();

    expect(erreurs).toEqual([]);
});

test('on revient à la photographie d\'origine en un geste', async ({ page }) => {
    await page.goto(fiche);
    await page.getByRole('link', { name: 'Reprendre la retouche' }).click();

    page.once('dialog', (dialogue) => dialogue.accept());
    await page.getByRole('button', { name: 'Revenir à la photographie d\'origine' }).click();

    await expect(page).toHaveURL(/\/decrire/, { timeout: 30_000 });
    await expect(page.getByRole('link', { name: 'Redresser, recadrer' })).toBeVisible();

    // L'image entière, telle qu'elle a été prise.
    expect(await coinDeLaPhoto(page)).toBe('mur');
    await expect(page.getByText('retouchée', { exact: true })).toHaveCount(0);
});

test('alléger l\'original ne garde que le cadre et sa marge, pour de bon', async ({ page }) => {
    const erreurs = surveillerLesErreurs(page);

    await page.goto(fiche);
    await page.getByRole('link', { name: 'Redresser, recadrer' }).click();

    for (const [i, etiquette] of POIGNEES.entries()) {
        await deplacer(page, etiquette, ...CADRE.coins[i]);
    }

    // Irréversible : la case n'est jamais cochée d'office, et dit ce qui part.
    const alleger = page.getByRole('checkbox', { name: /Alléger l'original/ });
    await expect(alleger).not.toBeChecked();
    await alleger.check();
    await expect(page.getByText(/Environ \d+\s%\sde la photographie seront supprimés/)).toBeVisible();

    await page.getByRole('button', { name: 'Enregistrer', exact: true }).click();
    await expect(page).toHaveURL(/\/decrire/, { timeout: 30_000 });
    await expect(page.getByText('L\'original est allégé')).toBeVisible();
    await expect(page.getByText('retouchée · original allégé')).toBeVisible();

    // L'original ne fait plus les 3024 × 4032 pixels de la photographie.
    const definition = await page.locator('.fi-ta').last().getByText(/\d+ × \d+ px/).first().innerText();
    const [largeur, hauteur] = definition.match(/\d+/g).map(Number);
    expect(largeur * hauteur).toBeLessThan(3024 * 4032 * 0.6);

    // La case reste cochée d'une photographie à l'autre, pour enchaîner.
    await page.getByRole('link', { name: 'Reprendre la retouche' }).click();
    await expect(page.getByRole('checkbox', { name: /Alléger l'original/ })).toBeChecked();
    await expect(page.getByText('Original allégé : on ne voit plus')).toBeVisible();

    expect(erreurs).toEqual([]);
});
