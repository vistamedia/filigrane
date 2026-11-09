import { expect, test } from '@playwright/test';
import { fixture, surveillerLesErreurs } from './aide.js';

/**
 * Une planche de mini-dessins photographiée d'un coup devient une fiche par
 * dessin (D-45). La planche de test montre cinq feuilles sur une table
 * sombre (voir scripts/generer-planche-test.php).
 */
test('une planche de plusieurs dessins devient une fiche par dessin', async ({ page }) => {
    const erreurs = surveillerLesErreurs(page);

    await page.goto('/import');
    await page.locator('input[type="file"]').setInputFiles([fixture('planche-de-dessins.jpg')]);
    await expect(page.locator('[data-filepond-item-state="processing-complete"]'))
        .toHaveCount(1, { timeout: 90_000 });
    await page.getByRole('button', { name: 'Importer', exact: true }).click();
    await expect(page.getByText('1 photographie importée')).toBeVisible({ timeout: 120_000 });

    await page.locator('.fi-filigrane-vignette').first().click();
    await expect(page).toHaveURL(/\/decrire/);

    await page.getByRole('link', { name: 'Une fiche par dessin' }).click();
    await expect(page).toHaveURL(/\/separer\?photo=\d+/);

    // Les cinq feuilles sont repérées d'elles-mêmes.
    await expect(page.getByText('5 dessins, 5 fiches.')).toBeVisible();
    await expect(page.locator('[data-cadre]')).toHaveCount(5);

    // Un cadre se retire…
    await page.getByRole('button', { name: 'Retirer le dessin 5' }).click();
    await expect(page.getByText('4 dessins, 4 fiches.')).toBeVisible();

    // …et un coin se fait glisser.
    const cadre = page.locator('[data-cadre]').first();
    const avant = await cadre.boundingBox();
    const coin = await page.getByRole('button', { name: 'Coin inférieur droit du dessin 1' }).boundingBox();
    await page.mouse.move(coin.x + coin.width / 2, coin.y + coin.height / 2);
    await page.mouse.down();
    await page.mouse.move(coin.x + coin.width / 2 + 30, coin.y + coin.height / 2 + 20, { steps: 6 });
    await page.mouse.up();
    expect((await cadre.boundingBox()).width).toBeGreaterThan(avant.width + 20);

    page.once('dialog', (dialogue) => dialogue.accept());
    await page.getByRole('button', { name: 'Créer 4 fiches' }).click();

    await expect(page).toHaveURL(/\/dessins\?tab=tous/, { timeout: 60_000 });
    await expect(page.getByText('4 dessins, une fiche chacun')).toBeVisible();
    await expect(page.getByRole('link', { name: 'Les redresser à la chaîne' })).toBeVisible();

    expect(erreurs).toEqual([]);
});
