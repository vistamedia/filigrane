import { expect, test } from '@playwright/test';
import { fixture, surveillerLesErreurs } from './aide.js';

/**
 * L'image de test figure une feuille de 300 × 400 mm posée sur une table
 * sombre, avec une règle de 200 mm, le tout à 3 pixels par millimètre
 * (voir scripts/generer-images-test.php). On connaît donc exactement les
 * coordonnées attendues, en fraction de l'image :
 */
const REGLE = { x1: 0.35, x2: 0.55, y: 0.918 };
const FEUILLE = { x1: 0.35, y1: 0.2333, x2: 0.65, y2: 0.7667 };

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

test.describe('Mesure des dimensions', () => {
    test('une règle dans le champ suffit à calculer la feuille', async ({ page }, infos) => {
        const erreurs = surveillerLesErreurs(page);

        // Une fiche avec la photographie qui contient la règle.
        await page.goto('/studio-photo');
        await page.locator('input[capture]').setInputFiles(fixture('dessin-avec-regle.jpg'));
        await expect(page.getByText(/Fiche FIL-\d{4} créée/)).toBeVisible({ timeout: 60_000 });

        await page.locator('.fi-filigrane-vignette').first().click();
        await expect(page).toHaveURL(/\/decrire/);

        await page.getByRole('link', { name: 'Mesurer' }).click();
        await expect(page.getByRole('heading', { name: /Mesurer FIL-/ })).toBeVisible();

        // 1. Le trait rouge sur la règle, dont on déclare la longueur.
        await deplacer(page, 'Début de la référence', REGLE.x1, REGLE.y);
        await deplacer(page, 'Fin de la référence', REGLE.x2, REGLE.y);
        await page.getByLabel('Objet photographié à côté du dessin')
            .selectOption({ label: 'Règle — 20 cm — 200 mm' });

        // L'échelle attendue est de 3 pixels par millimètre.
        await expect(page.getByText(/^3(,\d+)? px\/mm$/)).toBeVisible();

        // 2. Le cadre bleu sur la feuille.
        await deplacer(page, 'Coin supérieur gauche de la feuille', FEUILLE.x1, FEUILLE.y1);
        await deplacer(page, 'Coin inférieur droit de la feuille', FEUILLE.x2, FEUILLE.y2);

        await page.screenshot({ path: `DOCS/captures/${infos.project.name}-mesure.png`, fullPage: true });

        // 3. Le résultat, à un millimètre près.
        const section = page.locator('section', { hasText: 'Résultat' }).first();
        await expect(section.getByText(/^4[01](,\d)? cm$/)).toBeVisible();
        await expect(section.getByText(/^3[01](,\d)? cm$/)).toBeVisible();

        // 4. Enregistrement : la fiche porte désormais ses dimensions.
        await page.getByRole('button', { name: 'Enregistrer les dimensions' }).click();

        await expect(page).toHaveURL(/\/decrire/, { timeout: 30_000 });
        await expect(page.getByLabel('Hauteur')).toHaveValue(/^(39[5-9]|40[0-5])$/);
        await expect(page.getByLabel('Largeur')).toHaveValue(/^(29[5-9]|30[0-5])$/);
        await expect(page.getByRole('combobox', { name: 'Origine des dimensions' }))
            .toContainText('Estimée depuis la photo');

        expect(erreurs).toEqual([]);
    });

    test('le profil d\'échelle mesure ensuite la série sans intervention', async ({ page }) => {
        // Deux dessins photographiés dans les mêmes conditions.
        await page.goto('/import');
        await page.locator('input[type="file"]').setInputFiles([
            fixture('dessin-recto.jpg'),
            fixture('dessin-verso.jpg'),
        ]);
        await expect(page.locator('[data-filepond-item-state="processing-complete"]'))
            .toHaveCount(2, { timeout: 90_000 });
        await page.getByRole('button', { name: 'Importer', exact: true }).click();
        await expect(page.getByText('2 / 2')).toBeVisible({ timeout: 120_000 });

        // On retient l'adresse d'une des deux fiches : c'est elle qu'on ira
        // vérifier à la fin, et non « la première de la liste », qui pourrait
        // venir d'un autre test.
        const ficheDeLaSerie = await page.locator('.fi-filigrane-vignette').first().getAttribute('href');

        // Un troisième, avec la règle, sert de calibration.
        await page.goto('/studio-photo');
        await page.locator('input[capture]').setInputFiles(fixture('dessin-avec-regle.jpg'));
        await expect(page.getByText(/Fiche FIL-\d{4} créée/)).toBeVisible({ timeout: 60_000 });

        await page.locator('.fi-filigrane-vignette').first().click();
        await page.getByRole('link', { name: 'Mesurer' }).click();

        await deplacer(page, 'Début de la référence', REGLE.x1, REGLE.y);
        await deplacer(page, 'Fin de la référence', REGLE.x2, REGLE.y);
        await page.getByLabel('Objet photographié à côté du dessin')
            .selectOption({ label: 'Règle — 20 cm — 200 mm' });

        await deplacer(page, 'Coin supérieur gauche de la feuille', FEUILLE.x1, FEUILLE.y1);
        await deplacer(page, 'Coin inférieur droit de la feuille', FEUILLE.x2, FEUILLE.y2);

        // On enregistre l'échelle comme profil et on l'applique à la série.
        await page.getByText('Enregistrer cette échelle comme profil').click();
        await page.getByText('Mesurer tout de suite les autres photographies').click();

        await page.getByRole('button', { name: 'Enregistrer les dimensions' }).click();
        // Le nombre dépend des photographies déjà présentes dans la base ;
        // ce qui compte, c'est que la série ait été traitée.
        await expect(page.getByText(/Série traitée : [1-9]\d* photographie/)).toBeVisible({ timeout: 60_000 });

        // Le profil est enregistré et compte les trois photographies de la
        // série, celle de calibration comprise.
        await page.goto('/echelles');
        const profil = page.locator('tr', { hasText: '3,00 px/mm' });
        await expect(profil).toHaveCount(1);
        await expect(profil).toContainText('3000 × 2250 px');

        // Et la fiche importée porte désormais ses dimensions, sans que
        // personne y ait touché.
        await page.goto(ficheDeLaSerie);
        await expect(page.getByLabel('Hauteur')).toHaveValue(/^(39[5-9]|40[0-5])$/);
        await expect(page.getByLabel('Largeur')).toHaveValue(/^(29[5-9]|30[0-5])$/);
    });
});
