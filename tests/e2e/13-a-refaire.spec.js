import { expect, test } from '@playwright/test';
import { fixture, onglet, surveillerLesErreurs } from './aide.js';

/**
 * Un cliché raté — reflets dans la vitre, flou, étage trop sombre — reste en
 * place pour qu'on puisse décrire l'œuvre, mais on le signale à refaire. Les
 * fiches concernées forment la liste de travail de la séance suivante.
 */
test.describe.configure({ mode: 'serial' });

let fiche;

test('signaler un cliché à refaire le met dans la liste de la prochaine séance', async ({ page }) => {
    const erreurs = surveillerLesErreurs(page);

    await page.goto('/studio-photo');
    await page.locator('input[capture]').setInputFiles(fixture('cadre-en-perspective.jpg'));
    await expect(page.getByText(/Fiche FIL-\d{4} créée/)).toBeVisible({ timeout: 60_000 });

    await page.locator('.fi-filigrane-vignette').first().click();
    await expect(page).toHaveURL(/\/decrire/);
    fiche = page.url();
    const inventaire = await page.getByLabel('Numéro d\'inventaire').inputValue();

    await page.getByRole('button', { name: 'À refaire' }).click();

    const modale = page.getByRole('dialog');
    await modale.getByLabel('Pourquoi').selectOption({ label: 'Reflets dans la vitre' });
    await modale.getByLabel('Précision').fill('fenêtre en haut à gauche');
    await modale.getByRole('button', { name: 'Signaler' }).click();

    await expect(page.getByText('Cliché signalé à refaire')).toBeVisible();

    // Sous la photographie, là où l'on regarde l'œuvre.
    await expect(page.getByText('reflets dans la vitre — fenêtre en haut à gauche')).toBeVisible();

    // Dans la liste des dessins : un onglet qui n'existait pas, et la vignette
    // marquée.
    await page.goto('/dessins');
    await onglet(page, 'À rephotographier').click();
    await expect(onglet(page, 'À rephotographier')).toContainText('1');

    const carte = page.locator('.fi-ta-record', { hasText: inventaire });
    await expect(carte).toBeVisible();
    await expect(carte).toContainText('Cliché à refaire');
    await expect(page.locator('.fi-ta-record')).toHaveCount(1);

    expect(erreurs).toEqual([]);
});

test('une fois le cliché refait, la fiche quitte la liste', async ({ page }) => {
    await page.goto(fiche);

    await page.getByRole('button', { name: 'Ne plus signaler' }).click();
    // Une demande de confirmation s'annonce comme « alertdialog ».
    await page.getByRole('alertdialog').getByRole('button', { name: 'Ne plus signaler' }).click();

    await expect(page.getByText(/reflets dans la vitre/)).toHaveCount(0);

    // L'onglet disparaît quand il n'a plus rien à montrer.
    await page.goto('/dessins');
    await expect(onglet(page, 'Tous')).toBeVisible();
    await expect(onglet(page, 'À rephotographier')).toHaveCount(0);
});
