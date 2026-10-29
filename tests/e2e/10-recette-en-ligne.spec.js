import { expect, test } from '@playwright/test';
import { fixture } from './aide.js';

/**
 * Recette de la version en ligne.
 *
 * Les tests précédents tournent contre Docker. Celui-ci s'adresse au serveur
 * réel : c'est le seul moyen de vérifier que la conversion des images, le lien
 * vers les photographies et les droits d'écriture fonctionnent là où ils
 * comptent vraiment.
 *
 * Il fabrique une fiche d'essai puis la supprime définitivement, de sorte que
 * le numéro d'inventaire qu'elle a consommé redevienne disponible.
 *
 *   FILIGRANE_URL=https://lise.dananlab.fr \
 *   FILIGRANE_COMPTE=... FILIGRANE_MOTDEPASSE=... \
 *   npx playwright test --project=en-ligne
 */

const COMPTE = process.env.FILIGRANE_COMPTE;
const MOT_DE_PASSE = process.env.FILIGRANE_MOTDEPASSE;

test.describe('Recette en ligne', () => {
    test.skip(
        ! COMPTE || ! MOT_DE_PASSE,
        'Renseignez FILIGRANE_COMPTE et FILIGRANE_MOTDEPASSE pour jouer la recette en ligne.',
    );

    test('photographier, consulter puis effacer, sur le serveur réel', async ({ page, request }) => {
        test.setTimeout(180_000);

        // 1. Connexion.
        await page.goto('/login');
        await page.getByLabel('Adresse e-mail').fill(COMPTE);
        await page.locator('input[type="password"]').fill(MOT_DE_PASSE);
        await page.getByRole('button', { name: /connexion/i }).click();
        await expect(page.getByText('Dessins au catalogue')).toBeVisible({ timeout: 30_000 });

        // Les 168 traductions écrites à la main vivent dans lang/vendor/. Un
        // motif rsync mal ancré les avait un jour laissées sur place, et
        // l'interface en ligne affichait des identifiants bruts.
        expect(await page.content(), 'aucune clé de traduction brute')
            .not.toMatch(/filament-[a-z]+::/);

        // 2. Une photographie entre dans le catalogue.
        await page.goto('/studio-photo');
        await page.locator('input[capture]').setInputFiles(fixture('dessin-avec-regle.jpg'));
        await expect(page.getByText(/Fiche FIL-\d{4} créée/)).toBeVisible({ timeout: 120_000 });

        const inventaire = (await page.locator('.fi-filigrane-vignette span').first().innerText()).trim();

        // 3. La vignette est bien fabriquée ET servie par le serveur web :
        //    c'est ce qui valide imagick et le lien vers le dossier de stockage.
        const vignette = await page.locator('.fi-filigrane-vignette img').first().getAttribute('src');
        expect(vignette).toContain('/storage/photos/miniatures/');

        const image = await request.get(vignette);
        expect(image.status()).toBe(200);
        expect(image.headers()['content-type']).toContain('image/jpeg');
        expect((await image.body()).length).toBeGreaterThan(5_000);

        // 4. L'original, lui, n'est pas accessible en statique.
        const original = await request.get('/storage/photos/originaux/');
        expect(original.status()).toBeGreaterThanOrEqual(400);

        // 5. La fiche s'ouvre et affiche sa photographie.
        await page.locator('.fi-filigrane-vignette').first().click();
        await expect(page).toHaveURL(/\/decrire/);
        await expect(page.getByLabel('Numéro d\'inventaire')).toHaveValue(inventaire);

        const adresseFiche = page.url();

        // 6. Décrite, la fiche doit apparaître dans le diaporama.
        await page.getByLabel('Artiste').fill('Essai de recette');
        await page.locator('.fi-fo-rich-editor').first().locator('[contenteditable="true"]')
            .fill('Fiche d\'essai, créée et effacée par la recette automatique.');
        await page.locator('button[type="submit"]:has-text("Enregistrer")').click();
        await expect(page.getByText('Fiche enregistrée')).toBeVisible({ timeout: 30_000 });

        await page.goto('/diaporama');
        await expect(page.locator('.fi-filigrane-cartouche')).toContainText('Essai de recette');
        await expect(page.locator('.fi-filigrane-scene img')).toBeVisible();

        // 7. Les deux exports, sur le serveur réel : c'est là que se vérifient
        //    openspout, dompdf, la réduction des images par Imagick, les droits
        //    d'écriture dans storage et la route de remise signée.
        await page.goto('/dessins');
        await page.getByRole('button', { name: 'Exporter' }).click();
        await page.getByText('Tableur (Excel)', { exact: true }).click();

        const tableur = await page.waitForEvent('download', { timeout: 120_000 });
        expect(tableur.suggestedFilename()).toMatch(/\.xlsx$/);

        await page.goto('/dessins');
        await page.getByRole('button', { name: 'Exporter' }).click();
        await page.getByText('Catalogue (PDF)', { exact: true }).click();
        await page.locator('.fi-modal-window').getByRole('button', { name: 'Préparer le PDF' }).click();

        const catalogue = await page.waitForEvent('download', { timeout: 120_000 });
        expect(catalogue.suggestedFilename()).toMatch(/\.pdf$/);

        // 8. Ménage : corbeille, puis suppression définitive, pour rendre le
        //    numéro d'inventaire au catalogue.
        await page.goto(adresseFiche, { waitUntil: 'domcontentloaded' });
        await page.getByRole('button', { name: 'Mettre à la corbeille' }).click();

        // Les fenêtres de confirmation de Filament ne portent pas de rôle ARIA
        // « dialog » : on les vise par leur classe.
        const corbeille = page.locator('.fi-modal-window');
        await expect(corbeille).toContainText(`Mettre ${inventaire} à la corbeille`);
        await expect(corbeille).toContainText('reste récupérable');
        await corbeille.getByRole('button', { name: 'Mettre à la corbeille' }).click();

        await expect(page).toHaveURL(/\/dessins/, { timeout: 30_000 });

        // 9. Suppression définitive : la fiche reste accessible par son adresse
        //    tant qu'elle est à la corbeille, ce qui évite de bricoler le
        //    filtre de la liste.
        // On laisse la redirection précédente se terminer : une navigation
        // lancée pendant une autre est abandonnée par le navigateur.
        await page.waitForLoadState('networkidle');
        await page.goto(adresseFiche, { waitUntil: 'domcontentloaded' });

        await page.getByRole('button', { name: 'Supprimer définitivement' }).click();

        const definitive = page.locator('.fi-modal-window');
        await expect(definitive).toContainText('sans retour possible');
        await definitive.getByRole('button', { name: 'Supprimer définitivement' }).click();

        await expect(page).toHaveURL(/\/dessins/, { timeout: 30_000 });
        await expect(page.getByText(inventaire)).toHaveCount(0);

        // 10. Et la photographie n'est plus servie : les fichiers ont suivi.
        await expect.poll(async () => (await request.get(vignette)).status(), { timeout: 30_000 })
            .toBeGreaterThanOrEqual(400);
    });
});
