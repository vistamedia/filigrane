import { expect, test } from '@playwright/test';
import { ETATS, surveillerLesErreurs } from './aide.js';

const PAGES_ADMIN = [
    { url: '/', titre: 'Tableau de bord' },
    { url: '/dessins', titre: 'Dessins' },
    { url: '/diaporama', titre: 'Diaporama' },
    { url: '/mots-cles', titre: 'Mots-clés' },
    { url: '/comptes', titre: 'Comptes' },
    { url: '/echelles', titre: 'Échelles' },
    { url: '/studio-photo', titre: 'Studio photo' },
    { url: '/import', titre: 'Import en masse' },
];

test.describe('Navigation', () => {
    for (const cible of PAGES_ADMIN) {
        test(`l'administrateur ouvre ${cible.titre} sans erreur`, async ({ page }) => {
            const erreurs = surveillerLesErreurs(page);

            await page.goto(cible.url);

            await expect(page.getByRole('heading', { name: cible.titre, level: 1 })).toBeVisible();
            expect(erreurs).toEqual([]);
        });
    }

    test('la présentation publique s\'ouvre sans compte', async ({ browser }) => {
        // Contexte neuf, sans session : c'est tout l'intérêt de cette page.
        const contexte = await browser.newContext({ storageState: undefined });
        const page = await contexte.newPage();

        const reponse = await page.goto('/a-propos');

        expect(reponse.status()).toBe(200);
        await expect(page.getByRole('heading', { name: 'Filigrane', level: 1 })).toBeVisible();
        await expect(page.getByRole('link', { name: /Emmanuel Danan/ })).toBeVisible();

        // Et elle ne laisse rien filtrer de la collection.
        await expect(page.locator('body')).not.toContainText('FIL-');

        await contexte.close();
    });
});

test.describe('Cloisonnement des rôles', () => {
    test.use({ storageState: ETATS.conservateur });

    test('le conservateur ne voit ni les comptes ni les échelles', async ({ page }) => {
        await page.goto('/');

        const menu = page.locator('.fi-sidebar');
        await expect(menu.getByText('Dessins')).toBeVisible();
        await expect(menu.getByText('Comptes')).toHaveCount(0);
        await expect(menu.getByText('Échelles')).toHaveCount(0);
    });

    test('l\'accès direct aux comptes est refusé au conservateur', async ({ page }) => {
        const reponse = await page.goto('/comptes');

        expect(reponse.status()).toBe(403);
    });
});
