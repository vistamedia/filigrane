import { expect, test } from '@playwright/test';
import { COMPTES, surveillerLesErreurs } from './aide.js';

// Ces tests partent d'un navigateur vierge : c'est la connexion elle-même
// qui est éprouvée.
test.use({ storageState: { cookies: [], origins: [] } });

test.describe('Connexion', () => {
    test('la page de connexion s\'affiche en français avec la marque', async ({ page }) => {
        const erreurs = surveillerLesErreurs(page);

        await page.goto('/login');

        await expect(page.getByText('Filigrane').first()).toBeVisible();
        await expect(page.getByLabel('Adresse e-mail')).toBeVisible();
        await expect(page.locator('input[type="password"]')).toBeVisible();

        expect(erreurs).toEqual([]);
    });

    test('un mot de passe erroné est refusé', async ({ page }) => {
        await page.goto('/login');
        await page.getByLabel('Adresse e-mail').fill(COMPTES.conservateur.email);
        await page.locator('input[type="password"]').fill('mauvais-mot-de-passe');
        await page.getByRole('button', { name: /connexion/i }).click();

        await expect(page.getByText(/ne correspondent pas|identifiants/i).first()).toBeVisible();
        await expect(page).toHaveURL(/\/login/);
    });

    test('une page protégée renvoie vers la connexion', async ({ page }) => {
        await page.goto('/dessins');
        await expect(page).toHaveURL(/\/login/);
    });
});
