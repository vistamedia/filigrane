import { test } from '@playwright/test';
import { boutonSoumettre, fixture, onglet } from './aide.js';

/**
 * Captures d'écran de référence, utilisées par la documentation et pour
 * contrôler l'allure générale après un changement de charte graphique.
 *
 * La session est déjà ouverte (storageState) : ces tests ne repassent pas par
 * le formulaire de connexion.
 *
 *   npx playwright test captures --project=mac
 */
test.describe('Captures', () => {
    test('connexion', async ({ page, context }, infos) => {
        // Seule capture qui exige d'être déconnecté.
        await context.clearCookies();
        await page.goto('/login');
        await page.screenshot({ path: `DOCS/captures/${infos.project.name}-connexion.png`, fullPage: true });
    });

    test('tableau de bord', async ({ page }, infos) => {
        await page.goto('/');
        await page.waitForLoadState('networkidle');
        await page.screenshot({ path: `DOCS/captures/${infos.project.name}-tableau-de-bord.png`, fullPage: true });
    });

    test('planche de dessins, fiche de description et diaporama', async ({ page }, infos) => {
        // Une fiche illustrée et décrite, pour que les captures montrent
        // quelque chose — le diaporama ne retient que les fiches commentées.
        await page.goto('/studio-photo');
        await page.locator('input[capture]').setInputFiles(fixture('dessin-avec-regle.jpg'));
        await page.getByText(/Fiche FIL-\d{4} créée/).waitFor({ timeout: 60_000 });

        await page.goto('/dessins');
        await onglet(page, 'Tous').click();
        await page.waitForLoadState('networkidle');
        await page.screenshot({ path: `DOCS/captures/${infos.project.name}-dessins.png`, fullPage: true });

        await page.getByRole('link', { name: 'Décrire' }).first().click();
        await page.waitForLoadState('networkidle');

        if (! await page.locator('.fi-fo-rich-editor [contenteditable="true"]').first().textContent()) {
            await page.getByLabel('Artiste').fill('Attribué à Hubert Robert');
            await page.getByLabel('Période').fill('Vers 1780');
            await page.locator('.fi-fo-rich-editor').first().locator('[contenteditable="true"]')
                .fill('Vue de ruines antiques animée de quelques figures, à la sanguine sur papier vergé.');
            await boutonSoumettre(page, 'Enregistrer').click();
            await page.getByText('Fiche enregistrée').waitFor();
        }

        await page.screenshot({ path: `DOCS/captures/${infos.project.name}-fiche.png`, fullPage: true });

        await page.goto('/diaporama');
        await page.waitForLoadState('networkidle');
        await page.screenshot({ path: `DOCS/captures/${infos.project.name}-diaporama.png`, fullPage: true });
    });

    test('présentation publique', async ({ page, context }, infos) => {
        await context.clearCookies();
        await page.goto('/a-propos');
        await page.waitForLoadState('networkidle');
        await page.screenshot({ path: `DOCS/captures/${infos.project.name}-a-propos.png`, fullPage: true });
    });

    test('studio photo', async ({ page }, infos) => {
        await page.goto('/studio-photo');
        await page.waitForLoadState('networkidle');
        await page.screenshot({ path: `DOCS/captures/${infos.project.name}-studio.png`, fullPage: true });
    });
});
