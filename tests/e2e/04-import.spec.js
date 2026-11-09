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
        await page.getByLabel('Emplacement (appliqué à toutes les fiches)').selectOption({ label: 'Carton 2' });
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

        // Les trois fiches sont rangées au même endroit.
        await expect(page.locator('.fi-ta-record', { hasText: 'Carton 2' })).toHaveCount(3);

        expect(erreurs).toEqual([]);
    });

    test('un DNG (ProRAW) est accepté et développé en JPEG', async ({ page }) => {
        const erreurs = surveillerLesErreurs(page);

        await page.goto('/import');
        await page.locator('input[type="file"]').setInputFiles([fixture('prise-raw.dng')]);

        await expect(page.locator('[data-filepond-item-state="processing-complete"]'))
            .toHaveCount(1, { timeout: 90_000 });

        await page.getByRole('button', { name: 'Importer', exact: true }).click();
        await expect(page.getByText('1 photographie importée')).toBeVisible({ timeout: 120_000 });

        // La vignette est fabriquée : le DNG a bien été lu.
        const vignette = page.locator('.fi-filigrane-vignette img').first();
        await expect(vignette).toHaveJSProperty('complete', true);
        expect(await vignette.evaluate((img) => img.naturalWidth)).toBeGreaterThan(0);

        expect(erreurs).toEqual([]);
    });

    // Le 9 novembre, l'hébergeur a refusé 4 envois de ProRAW sur 104, aux
    // minutes les plus chargées : Laravel recevait alors un GET et répondait
    // 405. On imite ce refus en interceptant l'envoi.
    const refus = { status: 405, contentType: 'application/json', body: '{"message":"The GET method is not supported."}' };

    test('un envoi refusé en passant est relancé sans qu\'on le voie', async ({ page }) => {
        const erreurs = surveillerLesErreurs(page);
        let envois = 0;

        await page.route('**/upload-file**', (route) => (envois++ === 0 ? route.fulfill(refus) : route.continue()));

        await page.goto('/import');
        await page.locator('input[type="file"]').setInputFiles([fixture('dessin-recto.jpg')]);

        await expect(page.locator('[data-filepond-item-state="processing-complete"]'))
            .toHaveCount(1, { timeout: 60_000 });
        expect(envois).toBe(2);

        // Ni liste d'échecs, ni message générique resté affiché.
        await expect(page.locator('[data-envois-en-echec]')).toBeHidden();
        await expect(page.getByText(/n'a pas pu être envoyé|failed to upload/)).toHaveCount(0);

        // Les erreurs réseau simulées sont attendues ici.
        expect(erreurs.filter((e) => ! /405|Failed to load resource/.test(e))).toEqual([]);
    });

    test('un fichier qui échoue encore est nommé, et seul le reste est importé', async ({ page }) => {
        await page.route('**/upload-file**', (route) => (
            route.request().postDataBuffer()?.includes('dessin-verso.jpg') ? route.fulfill(refus) : route.continue()
        ));

        await page.goto('/import');
        await page.locator('input[type="file"]').setInputFiles([
            fixture('dessin-recto.jpg'),
            fixture('dessin-verso.jpg'),
        ]);

        // Deux relances, à 2 puis 4 secondes, avant de renoncer.
        const liste = page.locator('[data-envois-en-echec]');
        await expect(liste).toBeVisible({ timeout: 60_000 });
        await expect(liste).toContainText('1 fichier n\'a pas pu être envoyé');
        await expect(liste).toContainText('dessin-verso.jpg');
        await expect(liste).not.toContainText('dessin-recto.jpg');
        await expect(page.getByText(/failed to upload/)).toHaveCount(0);

        await page.getByRole('button', { name: 'Importer', exact: true }).click();
        await expect(page.getByText('1 photographie importée')).toBeVisible({ timeout: 120_000 });
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
