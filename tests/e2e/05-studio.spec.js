import { expect, test } from '@playwright/test';
import { fixture, surveillerLesErreurs } from './aide.js';

test.describe('Studio photo', () => {
    test('l\'appareil du téléphone crée une fiche par cliché', async ({ page }) => {
        const erreurs = surveillerLesErreurs(page);

        await page.goto('/studio-photo');

        // Le bouton « Prendre une photo » ouvre l'appareil natif : dans un test,
        // on lui fournit directement un fichier.
        await page.locator('input[capture]').setInputFiles(fixture('dessin-recto.jpg'));

        await expect(page.getByText(/Fiche FIL-\d{4} créée/)).toBeVisible({ timeout: 60_000 });
        await expect(page.getByRole('heading', { name: 'Clichés de cette séance' })).toBeVisible();

        const vignettes = page.locator('.fi-filigrane-vignette');
        await expect(vignettes).toHaveCount(1);

        // Un second cliché ouvre une deuxième fiche.
        await page.locator('input[capture]').setInputFiles(fixture('dessin-verso.jpg'));
        await expect(vignettes).toHaveCount(2, { timeout: 60_000 });

        const numeros = await vignettes.locator('span').allInnerTexts();
        expect(new Set(numeros).size).toBe(2);

        expect(erreurs).toEqual([]);
    });

    test('le mode « plusieurs clichés » regroupe sur une seule fiche', async ({ page }) => {
        await page.goto('/studio-photo');

        await page.getByText('Plusieurs clichés sur la même fiche').click();

        await page.locator('input[capture]').setInputFiles(fixture('dessin-recto.jpg'));
        await expect(page.getByText(/Fiche FIL-\d{4} créée/)).toBeVisible({ timeout: 60_000 });

        await page.locator('input[capture]').setInputFiles(fixture('dessin-verso.jpg'));
        await expect(page.getByText(/Photographie ajoutée à FIL-\d{4}/)).toBeVisible({ timeout: 60_000 });

        const numeros = await page.locator('.fi-filigrane-vignette span').allInnerTexts();
        expect(new Set(numeros).size).toBe(1);

        // Et « passer au dessin suivant » referme la fiche en cours.
        await page.getByRole('button', { name: 'Passer au dessin suivant' }).click();
        await expect(page.getByText(/Fiche close/)).toBeVisible();
    });

    test('le viseur intégré affiche l\'image en direct et déclenche', async ({ page }) => {
        // Chromium est lancé avec une caméra simulée (voir playwright.config.js).
        await page.goto('/studio-photo');

        await page.getByText('Viseur intégré').click();
        await page.getByRole('button', { name: 'Ouvrir le viseur' }).click();

        const video = page.locator('video');
        await expect(video).toBeVisible({ timeout: 30_000 });

        // La définition réellement obtenue est affichée : elle conditionne la
        // qualité du cliché, l'utilisateur doit pouvoir la constater.
        await expect(page.getByText(/\d+ × \d+ px/)).toBeVisible();

        await expect.poll(async () => video.evaluate((v) => v.videoWidth)).toBeGreaterThan(0);

        await page.getByRole('button', { name: 'Déclencher' }).click();
        await expect(page.getByText(/Fiche FIL-\d{4} créée/)).toBeVisible({ timeout: 60_000 });

        await page.getByRole('button', { name: 'Fermer le viseur' }).click();
        await expect(video).toBeHidden();
    });
});
