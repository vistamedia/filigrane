import { expect, test } from '@playwright/test';
import { fixture, onglet, surveillerLesErreurs } from './aide.js';

test.describe('Import en masse', () => {
    test('chaque fichier déposé devient une fiche à décrire', async ({ page }) => {
        const erreurs = surveillerLesErreurs(page);

        await page.goto('/import');

        await page.locator('input[type="file"]').setInputFiles([
            fixture('dessin-recto.jpg'),
            fixture('dessin-verso.jpg'),
            fixture('dessin-avec-regle.jpg'),
        ]);

        await expect(page.locator('[data-filepond-item-state="processing-complete"]'))
            .toHaveCount(3, { timeout: 90_000 });

        await page.getByLabel('Artiste (appliqué à toutes les fiches)').fill('Anonyme');
        await page.getByRole('button', { name: 'Importer', exact: true }).click();

        // Les images sont traitées une par une : la progression avance.
        await expect(page.getByText('3 / 3')).toBeVisible({ timeout: 120_000 });
        await expect(page.getByText('3 photographies importées')).toBeVisible();

        // Trois fiches, chacune avec sa vignette.
        const vignettes = page.locator('.fi-filigrane-vignette');
        await expect(vignettes).toHaveCount(3);

        await page.goto('/dessins');
        await onglet(page, 'À décrire').click();
        await expect(page.getByText('Anonyme').first()).toBeVisible();

        expect(erreurs).toEqual([]);
    });

    test('le mode « une seule fiche » regroupe les clichés', async ({ page }) => {
        await page.goto('/import');

        await page.locator('input[type="file"]').setInputFiles([
            fixture('dessin-recto.jpg'),
            fixture('dessin-verso.jpg'),
        ]);

        await expect(page.locator('[data-filepond-item-state="processing-complete"]'))
            .toHaveCount(2, { timeout: 90_000 });

        await page.getByText('Tous les fichiers sur une seule fiche').click();
        await page.getByRole('button', { name: 'Importer', exact: true }).click();

        await expect(page.getByText('2 / 2')).toBeVisible({ timeout: 120_000 });

        // Les deux vignettes portent le même numéro d'inventaire.
        const numeros = await page.locator('.fi-filigrane-vignette span').allInnerTexts();
        expect(new Set(numeros).size).toBe(1);
    });

    test('un fichier qui n\'est pas une image est refusé avec un motif lisible', async ({ page }, infos) => {
        await page.goto('/import');

        // Première barrière, côté navigateur : le fichier n'est même pas envoyé.
        await page.locator('input[type="file"]').setInputFiles([fixture('pas-une-image.txt')]);

        const refus = page.locator('.filepond--item[data-filepond-item-state^="load-invalid"]');
        await expect(refus).toHaveCount(1, { timeout: 30_000 });
        await expect(refus).toContainText(/image\/jpeg/);

        // Le motif doit rester lisible : nom et message ne se chevauchent pas.
        await page.screenshot({
            path: `DOCS/captures/${infos.project.name}-import-refus.png`,
            clip: await page.locator('.fi-fo-file-upload').first().boundingBox(),
        });

        const nom = await refus.locator('.filepond--file-info').boundingBox();
        const message = await refus.locator('.filepond--file-status').boundingBox();
        expect(message.y).toBeGreaterThanOrEqual(nom.y + nom.height);
    });
});
