import { expect, test } from '@playwright/test';
import { boutonSoumettre, fixture, onglet, surveillerLesErreurs } from './aide.js';

test.describe('Catalogage d\'un dessin', () => {
    test('créer une fiche, y joindre des photographies, la décrire', async ({ page }) => {
        const erreurs = surveillerLesErreurs(page);

        // 1. Création de la fiche.
        await page.goto('/dessins');
        await page.getByRole('link', { name: 'Nouvelle fiche' }).click();

        // Le numéro est attribué à l'enregistrement, pas avant.
        await expect(page.getByLabel('Numéro d\'inventaire')).toBeDisabled();

        await page.getByLabel('Artiste').fill('Attribué à Hubert Robert');
        await page.getByLabel('Période').fill('Vers 1780');
        await boutonSoumettre(page, 'Créer').click();

        await expect(page).toHaveURL(/\/dessins\/\d+\/decrire/);
        await expect(page.getByLabel('Numéro d\'inventaire')).toHaveValue(/^FIL-\d{4}$/);
        const inventaire = await page.getByLabel('Numéro d\'inventaire').inputValue();

        // 2. Ajout de deux photographies par dépôt de fichiers.
        await page.getByRole('button', { name: 'Ajouter des photographies' }).click();

        const modale = page.getByRole('dialog');
        await modale.locator('input[type="file"]').setInputFiles([
            fixture('dessin-recto.jpg'),
            fixture('dessin-verso.jpg'),
        ]);

        // FilePond téléverse chaque fichier dans sa propre requête : on attend
        // que les deux soient effectivement arrivés avant de valider.
        await expect(modale.locator('[data-filepond-item-state="processing-complete"]'))
            .toHaveCount(2, { timeout: 60_000 });

        await modale.getByRole('button', { name: 'Importer' }).click();

        await expect(page.getByText('2 photographies ajoutées')).toBeVisible({ timeout: 60_000 });

        // Les vignettes sont fabriquées et la définition relevée.
        const tableauPhotos = page.locator('.fi-ta').last();
        await expect(tableauPhotos.getByText('Recto')).toBeVisible();
        await expect(tableauPhotos.getByText('Détail')).toBeVisible();
        await expect(tableauPhotos.getByText('3000 × 2250 px').first()).toBeVisible();

        // 3. Description.
        await page.reload();
        await page.locator('.fi-fo-rich-editor').first().locator('[contenteditable="true"]')
            .fill('Vue d\'un portique en ruine, plume et lavis brun sur papier vergé.');

        await boutonSoumettre(page, 'Enregistrer').click();
        await expect(page.getByText('Fiche enregistrée')).toBeVisible();

        // Une fiche commencée n'est plus « à décrire ».
        await page.reload();
        await expect(page.getByRole('radio', { name: 'En cours' })).toBeChecked();

        // 4. La fiche apparaît dans la planche avec son artiste.
        await page.goto('/dessins');
        await onglet(page, 'Tous').click();
        await expect(page.getByText(inventaire)).toBeVisible();
        await expect(page.getByText('Attribué à Hubert Robert')).toBeVisible();

        expect(erreurs).toEqual([]);
    });

    test('« Décrit — passer au suivant » enchaîne sur la fiche suivante', async ({ page }) => {
        // Deux fiches en attente.
        for (const artiste of ['Premier essai', 'Deuxième essai']) {
            await page.goto('/dessins');
            await page.getByRole('link', { name: 'Nouvelle fiche' }).click();
            await page.getByLabel('Artiste').fill(artiste);
            await boutonSoumettre(page, 'Créer').click();
            await expect(page).toHaveURL(/\/decrire/);
        }

        await page.goto('/dessins');
        await onglet(page, 'À décrire').click();
        await page.getByRole('link', { name: 'Décrire' }).first().click();

        const premier = await page.getByLabel('Numéro d\'inventaire').inputValue();

        await page.getByRole('button', { name: 'Décrit — passer au suivant' }).click();

        // On arrive directement sur une autre fiche.
        await expect(page.getByText(/au tour de FIL-/)).toBeVisible();
        await expect(page.getByLabel('Numéro d\'inventaire')).not.toHaveValue(premier);
    });

    test('l\'avancement de plusieurs fiches se change d\'un coup depuis la liste', async ({ page }) => {
        const erreurs = surveillerLesErreurs(page);

        for (const artiste of ['Lot, premier', 'Lot, second']) {
            await page.goto('/dessins');
            await page.getByRole('link', { name: 'Nouvelle fiche' }).click();
            await page.getByLabel('Artiste').fill(artiste);
            await boutonSoumettre(page, 'Créer').click();
            await expect(page).toHaveURL(/\/decrire/);
        }

        await page.goto('/dessins');
        await onglet(page, 'Tous').click();

        // Le bouton n'apparaît qu'une fois des fiches cochées.
        const bouton = page.getByRole('button', { name: 'Changer l\'avancement' });
        await expect(bouton).toBeHidden();

        for (const artiste of ['Lot, premier', 'Lot, second']) {
            await page.locator('.fi-ta-record', { hasText: artiste }).getByRole('checkbox').check();
        }

        // Cochée, la case doit quitter le fond blanc qui la détache de
        // l'image : la coche est blanche, elle y devenait invisible.
        await expect(page.locator('.fi-ta-record', { hasText: 'Lot, premier' }).getByRole('checkbox'))
            .not.toHaveCSS('background-color', 'rgba(255, 255, 255, 0.9)');

        await bouton.click();
        const modale = page.getByRole('dialog');
        await modale.getByText('Décrit', { exact: true }).click();
        await modale.getByRole('button', { name: 'Appliquer' }).click();

        await expect(page.getByText('2 fiches passées à « Décrit »')).toBeVisible();

        for (const artiste of ['Lot, premier', 'Lot, second']) {
            await expect(page.locator('.fi-ta-record', { hasText: artiste })).toContainText('Décrit');
        }

        expect(erreurs).toEqual([]);
    });

    test('un mot-clé se crée depuis la fiche et se retrouve dans la liste', async ({ page }) => {
        await page.goto('/dessins');
        await page.getByRole('link', { name: 'Nouvelle fiche' }).click();
        await boutonSoumettre(page, 'Créer').click();
        await expect(page).toHaveURL(/\/decrire/);

        await page.getByRole('button', { name: 'Créer', exact: true }).first().click();

        const modale = page.getByRole('dialog');
        await modale.getByLabel('Mot-clé').fill('Ruines');
        await modale.getByLabel('Famille (facultatif)').fill('sujet');
        await boutonSoumettre(modale, 'Créer').click();

        await expect(modale).toBeHidden();

        await boutonSoumettre(page, 'Enregistrer').click();
        await expect(page.getByText('Fiche enregistrée')).toBeVisible();

        await page.goto('/mots-cles');

        // Le mot-clé figure bien dans la liste, rangé dans sa famille.
        const ligne = page.locator('tr', { hasText: 'Ruines' });
        await expect(ligne).toHaveCount(1);
        await expect(ligne.getByText('sujet')).toBeVisible();
    });
});
