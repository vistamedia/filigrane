import { expect } from '@playwright/test';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const ICI = path.dirname(fileURLToPath(import.meta.url));

/** Chemin absolu d'une image de test (voir scripts/generer-images-test.php). */
export function fixture(nom) {
    return path.resolve(ICI, '../fixtures', nom);
}

export const COMPTES = {
    admin: { email: 'emmanuel@filigrane.test', motDePasse: 'filigrane' },
    conservateur: { email: 'lise@filigrane.test', motDePasse: 'filigrane' },
};

/** Sessions enregistrées par auth.setup.js et réutilisées par les tests. */
export const ETATS = {
    admin: 'tests/e2e/.sessions/admin.json',
    conservateur: 'tests/e2e/.sessions/conservateur.json',
};

/**
 * Connexion par le formulaire, comme le ferait une personne.
 */
export async function seConnecter(page, compte = COMPTES.admin) {
    await page.goto('/login');
    await page.getByLabel('Adresse e-mail').fill(compte.email);
    // Filament ajoute deux boutons « afficher / masquer » étiquetés eux aussi
    // « mot de passe » : on vise le champ par son type.
    await page.locator('input[type="password"]').fill(compte.motDePasse);
    await page.getByRole('button', { name: /connexion/i }).click();
    await expect(page).toHaveURL(/\/$|\/dessins/);
}

/**
 * Le bouton de validation d'un formulaire.
 *
 * Filament place des boutons homonymes un peu partout (« Créer » sert aussi à
 * ajouter un mot-clé depuis une liste déroulante) : on vise donc celui qui
 * soumet réellement le formulaire.
 */
export function boutonSoumettre(portee, libelle) {
    return portee.locator(`button[type="submit"]:has-text("${libelle}")`);
}

/**
 * Un onglet de liste (« À décrire », « Décrits », « Tous »).
 *
 * Filament les rend en liens dans un <nav>, sans role ARIA « tab » : on les
 * vise donc par leur classe.
 */
export function onglet(page, libelle) {
    return page.locator('.fi-tabs-item', { hasText: libelle });
}

/**
 * Échoue si la page a produit une erreur serveur ou une exception JavaScript.
 */
export function surveillerLesErreurs(page) {
    const erreurs = [];

    page.on('pageerror', (erreur) => erreurs.push(`JS: ${erreur.message}`));
    page.on('response', (reponse) => {
        if (reponse.status() >= 500) {
            erreurs.push(`HTTP ${reponse.status()} sur ${reponse.url()}`);
        }
    });

    return erreurs;
}
