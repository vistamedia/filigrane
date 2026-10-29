import { expect, test } from '@playwright/test';
import { fixture, surveillerLesErreurs } from './aide.js';

/**
 * Ce qui change vraiment sur un téléphone : le menu se replie, la planche
 * passe sur une colonne, la fiche s'empile, et c'est là que la prise de vue
 * a lieu.
 *
 * Ces tests tournent dans Chromium à la largeur d'un iPhone. Safari sur iOS
 * reste à vérifier à la main : voir DOCS/02-architecture-technique.md.
 */
test.describe('Usage sur téléphone', () => {
    test('le menu se déplie et donne accès à tout', async ({ page }) => {
        const erreurs = surveillerLesErreurs(page);

        await page.goto('/');

        const menu = page.locator('.fi-sidebar');
        await expect(menu.getByText('Dessins')).toBeHidden();

        await page.getByRole('button', { name: 'Agrandir la barre latérale' }).click();

        await expect(menu.getByText('Dessins')).toBeVisible();
        await expect(menu.getByText('Studio photo')).toBeVisible();
        await expect(menu.getByText('Import en masse')).toBeVisible();

        expect(erreurs).toEqual([]);
    });

    test('la planche de dessins se lit sur une colonne', async ({ page }) => {
        await page.goto('/studio-photo');
        await page.locator('input[capture]').setInputFiles(fixture('dessin-recto.jpg'));
        await expect(page.getByText(/Fiche FIL-\d{4} créée/)).toBeVisible({ timeout: 60_000 });

        await page.goto('/dessins');

        const vignette = page.locator('.fi-ta-record').first();
        await expect(vignette).toBeVisible();

        const cadre = await vignette.boundingBox();
        const fenetre = page.viewportSize();

        // Une carte par ligne : elle occupe l'essentiel de la largeur.
        expect(cadre.width).toBeGreaterThan(fenetre.width * 0.75);
    });

    test('la fiche empile la photographie puis les champs', async ({ page }) => {
        await page.goto('/studio-photo');
        await page.locator('input[capture]').setInputFiles(fixture('dessin-recto.jpg'));
        await expect(page.getByText(/Fiche FIL-\d{4} créée/)).toBeVisible({ timeout: 60_000 });

        await page.locator('.fi-filigrane-vignette').first().click();
        await expect(page).toHaveURL(/\/decrire/);

        const photo = await page.locator('.fi-filigrane-vignette').first().boundingBox();
        const artiste = await page.getByLabel('Artiste').boundingBox();

        // La photographie est au-dessus du formulaire, pas à côté.
        expect(photo.y + photo.height).toBeLessThanOrEqual(artiste.y + 5);
        expect(photo.width).toBeGreaterThan(page.viewportSize().width * 0.7);
    });

    test('le bouton de prise de vue est large et à portée de pouce', async ({ page }) => {
        await page.goto('/studio-photo');

        const bouton = page.getByText('Prendre une photo');
        await expect(bouton).toBeVisible();

        const cadre = await bouton.boundingBox();

        expect(cadre.height).toBeGreaterThanOrEqual(48);
        expect(cadre.y).toBeLessThan(page.viewportSize().height);
    });

    test('l\'éditeur de description reste utilisable au doigt', async ({ page }) => {
        await page.goto('/dessins');
        await page.getByRole('link', { name: 'Nouvelle fiche' }).click();
        await page.locator('button[type="submit"]:has-text("Créer")').click();
        await expect(page).toHaveURL(/\/decrire/);

        const editeur = page.locator('.fi-fo-rich-editor').first();
        await expect(editeur).toBeVisible();

        // Les boutons de la barre d'outils ne doivent pas être minuscules.
        const gras = editeur.getByRole('button').first();
        const cadre = await gras.boundingBox();

        expect(cadre.width).toBeGreaterThanOrEqual(32);
        expect(cadre.height).toBeGreaterThanOrEqual(32);

        // Et la saisie fonctionne.
        await editeur.locator('[contenteditable="true"]').fill('Essai de saisie au doigt.');
        await expect(editeur.locator('[contenteditable="true"]')).toContainText('Essai de saisie');
    });
});
