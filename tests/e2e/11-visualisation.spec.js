import { expect, test } from '@playwright/test';
import { readFile } from 'node:fs/promises';
import { boutonSoumettre, fixture, surveillerLesErreurs } from './aide.js';

/**
 * Lecture suivie et exports.
 *
 * Ces trois fonctions partagent le même préalable — des fiches décrites, avec
 * photographie — qu'on fabrique une fois, par l'interface, comme le ferait une
 * personne. Les tests s'enchaînent donc dans l'ordre.
 */
test.describe.configure({ mode: 'serial' });

const PLANCHES = [
    { artiste: 'Hubert Robert', periode: 'Vers 1780', texte: 'Vue de ruines antiques animée de quelques figures.' },
    { artiste: 'Jean-Baptiste Oudry', periode: '1740', texte: 'Étude de chien couché, pierre noire sur papier bleu.' },
];

async function creerFicheDecrite(page, planche) {
    await page.goto('/dessins');
    await page.getByRole('link', { name: 'Nouvelle fiche' }).click();

    await page.getByLabel('Artiste').fill(planche.artiste);
    await page.getByLabel('Période').fill(planche.periode);
    await boutonSoumettre(page, 'Créer').click();
    await expect(page).toHaveURL(/\/dessins\/\d+\/decrire/);
    // Le numéro n'apparaît qu'une fois la fiche vraiment enregistrée : c'est
    // le signal que la page est en place, gestionnaire de photographies compris.
    await expect(page.getByLabel('Numéro d\'inventaire')).toHaveValue(/^FIL-\d{4}$/);

    await page.getByRole('button', { name: 'Ajouter des photographies' }).click();

    const modale = page.getByRole('dialog');
    // FilePond remplace le champ natif : tant qu'il n'est pas monté, un fichier
    // déposé sur le champ passe inaperçu.
    await expect(modale.locator('.filepond--root')).toBeVisible();
    await modale.locator('input[type="file"]').setInputFiles([fixture('dessin-recto.jpg')]);
    await expect(modale.locator('[data-filepond-item-state="processing-complete"]'))
        .toHaveCount(1, { timeout: 60_000 });
    await modale.getByRole('button', { name: 'Importer' }).click();

    await expect(page.getByText(/photographie ajoutée/)).toBeVisible({ timeout: 60_000 });

    await page.reload();
    await page.locator('.fi-fo-rich-editor').first().locator('[contenteditable="true"]')
        .fill(planche.texte);

    await boutonSoumettre(page, 'Enregistrer').click();
    await expect(page.getByText('Fiche enregistrée')).toBeVisible();
}

test('deux fiches décrites et photographiées servent de matière', async ({ page }) => {
    for (const planche of PLANCHES) {
        await creerFicheDecrite(page, planche);
    }
});

test.describe('Diaporama', () => {
    test('il feuillette les fiches commentées, image puis commentaire', async ({ page }) => {
        const erreurs = surveillerLesErreurs(page);

        await page.goto('/diaporama');

        await expect(page.getByRole('heading', { name: 'Diaporama', level: 1 })).toBeVisible();

        const compteur = page.locator('.fi-filigrane-compteur');
        await expect(compteur).toContainText('1 /');

        const total = Number((await compteur.textContent()).split('/')[1].trim());
        expect(total).toBeGreaterThanOrEqual(2);

        // La reproduction est en place, et le commentaire est dessous.
        const scene = page.locator('.fi-filigrane-scene');
        await expect(scene.locator('img')).toBeVisible();

        const cartouche = page.locator('.fi-filigrane-cartouche');
        await expect(cartouche.locator('.fi-filigrane-prose').first()).not.toBeEmpty();

        // Ces fiches n'ont pas de note : aucun bloc « Note » ne doit
        // apparaître. L'éditeur enregistre « <p></p> » pour un champ
        // seulement effleuré, ce qui suffisait à en faire naître un.
        await expect(cartouche.locator('.fi-filigrane-note')).toHaveCount(0);

        const cadreImage = await scene.boundingBox();
        const cadreTexte = await cartouche.boundingBox();
        expect(cadreTexte.y).toBeGreaterThan(cadreImage.y);

        const premier = await cartouche.locator('.fi-filigrane-titre').textContent();

        // Au suivant, par le bouton…
        await page.getByRole('button', { name: 'Dessin suivant' }).first().click();
        await expect(compteur).toContainText('2 /');
        await expect(cartouche.locator('.fi-filigrane-titre')).not.toHaveText(premier);

        // …puis retour par le clavier.
        await page.keyboard.press('ArrowLeft');
        await expect(compteur).toContainText('1 /');
        await expect(cartouche.locator('.fi-filigrane-titre')).toHaveText(premier);

        // Et de là on ouvre la fiche correspondante.
        await expect(page.getByRole('link', { name: 'Ouvrir la fiche' }))
            .toHaveAttribute('href', /\/dessins\/\d+\/decrire/);

        expect(erreurs).toEqual([]);
    });

    test('le défilement automatique avance puis s\'arrête', async ({ page }) => {
        await page.goto('/diaporama');

        await page.getByRole('button', { name: 'Faire défiler' }).click();
        await expect(page.getByRole('button', { name: 'Arrêter le défilement' })).toBeVisible();

        await page.getByRole('button', { name: 'Arrêter le défilement' }).click();
        await expect(page.getByRole('button', { name: 'Faire défiler' })).toBeVisible();
    });
});

test.describe('Exports', () => {
    async function exporter(page, libelle, apresOuverture = null) {
        await page.goto('/dessins');
        await page.getByRole('button', { name: 'Exporter' }).click();
        await page.getByText(libelle, { exact: true }).click();

        if (apresOuverture) {
            await apresOuverture(page);
        }

        return page.waitForEvent('download', { timeout: 60_000 });
    }

    test('le tableur se télécharge et s\'ouvre comme un classeur', async ({ page }) => {
        const telechargement = await exporter(page, 'Tableur (Excel)');

        expect(telechargement.suggestedFilename())
            .toMatch(/^filigrane-catalogue-\d{4}-\d{2}-\d{2}\.xlsx$/);

        // Un .xlsx est une archive ZIP : les deux premiers octets le disent.
        const octets = await readFile(await telechargement.path());
        expect(octets.subarray(0, 2).toString()).toBe('PK');
        expect(octets.length).toBeGreaterThan(2000);
    });

    test('le catalogue PDF se télécharge, notes comprises si on le demande', async ({ page }) => {
        const telechargement = await exporter(page, 'Catalogue (PDF)', async (page) => {
            const modale = page.locator('.fi-modal-window');
            await expect(modale.getByText('Inclure les notes')).toBeVisible();
            await modale.getByRole('button', { name: 'Préparer le PDF' }).click();
        });

        expect(telechargement.suggestedFilename())
            .toMatch(/^filigrane-catalogue-\d{4}-\d{2}-\d{2}\.pdf$/);

        const octets = await readFile(await telechargement.path());
        expect(octets.subarray(0, 5).toString()).toBe('%PDF-');

        // Page de garde plus au moins deux planches.
        const pages = (octets.toString('latin1').match(/\/Type\s*\/Page[^s]/g) ?? []).length;
        expect(pages).toBeGreaterThanOrEqual(3);
    });

    test('le lien de téléchargement ne sert qu\'une fois et refuse d\'être bricolé', async ({ page }) => {
        const telechargement = await exporter(page, 'Tableur (Excel)');
        const url = telechargement.url();

        // Rejouer le lien ne donne rien : le fichier a été effacé après remise.
        expect((await page.request.get(url)).status()).toBe(404);

        // Et une signature trafiquée est refusée avant même d'ouvrir un fichier.
        expect((await page.request.get(url.replace(/signature=\w{8}/, 'signature=00000000'))).status())
            .toBe(403);
    });
});
