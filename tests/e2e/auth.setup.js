import { test as setup } from '@playwright/test';
import { COMPTES, ETATS, seConnecter } from './aide.js';

/**
 * Ouvre une session par rôle et enregistre les cookies.
 *
 * Les tests réutilisent ces sessions au lieu de repasser par le formulaire :
 * Filament limite les tentatives de connexion à cinq par minute, et une suite
 * d'une vingtaine de tests se ferait bloquer au milieu.
 */
setup('session administrateur', async ({ page }) => {
    await seConnecter(page, COMPTES.admin);
    await page.context().storageState({ path: ETATS.admin });
});

setup('session conservateur', async ({ page }) => {
    await seConnecter(page, COMPTES.conservateur);
    await page.context().storageState({ path: ETATS.conservateur });
});
