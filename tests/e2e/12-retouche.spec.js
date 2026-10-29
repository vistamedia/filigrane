import { expect, test } from '@playwright/test';
import { readFileSync } from 'node:fs';
import { fixture, surveillerLesErreurs } from './aide.js';

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
    const image = page.locator('img[alt^="FIL-"]').first();
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
    await expect(page.getByText(/Fiche FIL-\d{4} créée/)).toBeVisible({ timeout: 60_000 });

    await page.locator('.fi-filigrane-vignette').first().click();
    await expect(page).toHaveURL(/\/decrire/);
    fiche = page.url();

    // Avant retouche : la photographie entière, le mur jusque dans les coins.
    expect(await coinDeLaPhoto(page)).toBe('mur');
    const avant = await page.locator('img[alt^="FIL-"]').first().getAttribute('src');

    await page.getByRole('link', { name: 'Redresser, recadrer' }).click();
    await expect(page.getByRole('heading', { name: /Retoucher FIL-/ })).toBeVisible();

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
    const image = page.locator('img[alt^="FIL-"]').first();
    expect(await image.getAttribute('src')).not.toBe(avant);
    expect(await coinDeLaPhoto(page)).toBe('baguette');
    expect(await image.evaluate((img) => img.naturalWidth / img.naturalHeight)).toBeCloseTo(0.75, 2);

    await expect(page.getByRole('link', { name: 'Reprendre la retouche' })).toBeVisible();
    await expect(page.getByText('retouchée', { exact: true })).toBeVisible();

    expect(erreurs).toEqual([]);
});

test('la retouche se reprend là où on l\'avait laissée', async ({ page }) => {
    await page.goto(fiche);
    await page.getByRole('link', { name: 'Reprendre la retouche' }).click();

    // Chaque poignée est revenue sur son coin du cadre.
    const scene = await page.locator('[x-ref="scene"]').boundingBox();

    for (const [i, etiquette] of POIGNEES.entries()) {
        const poignee = await page.getByRole('button', { name: etiquette }).boundingBox();
        const [fx, fy] = CADRE.coins[i];

        expect(Math.abs(poignee.x + poignee.width / 2 - (scene.x + scene.width * fx))).toBeLessThan(3);
        expect(Math.abs(poignee.y + poignee.height / 2 - (scene.y + scene.height * fy))).toBeLessThan(3);
    }
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
