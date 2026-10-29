import { defineConfig, devices } from '@playwright/test';
import { ETATS } from './tests/e2e/aide.js';

/**
 * Tests de bout en bout, joués contre l'environnement Docker local.
 *
 *   make up && npx playwright test
 *
 * Deux profils, avec des rôles distincts :
 *
 *   « mac »    joue l'ensemble des parcours fonctionnels ;
 *   « mobile » ne rejoue pas ces parcours à 390 px — Chromium étroit n'est pas
 *              Safari sur iPhone, et la duplication coûterait du temps sans
 *              rien révéler — mais vérifie ce qui change vraiment sur un
 *              téléphone : mise en page, atteignabilité, prise de vue.
 */
export default defineConfig({
    testDir: './tests/e2e',
    globalSetup: './tests/e2e/preparation.js',
    fullyParallel: false,
    workers: 1,
    retries: 0,
    timeout: 60_000,
    expect: { timeout: 15_000 },
    reporter: [['list'], ['html', { outputFolder: 'tests/e2e/.rapport', open: 'never' }]],
    use: {
        baseURL: process.env.FILIGRANE_URL ?? 'http://localhost:8080',
        locale: 'fr-FR',
        timezoneId: 'Europe/Paris',
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        // La caméra du studio photo est simulée : Chromium fournit une mire.
        launchOptions: {
            args: [
                '--use-fake-ui-for-media-stream',
                '--use-fake-device-for-media-stream',
            ],
        },
        permissions: ['camera'],
    },
    projects: [
        {
            name: 'setup',
            testMatch: /auth\.setup\.js/,
        },
        {
            name: 'mac',
            use: {
                ...devices['Desktop Chrome'],
                viewport: { width: 1440, height: 900 },
                storageState: ETATS.admin,
            },
            dependencies: ['setup'],
            testIgnore: [/auth\.setup\.js/, /09-mobile\.spec\.js/, /10-recette-en-ligne\.spec\.js/],
        },
        {
            name: 'mobile',
            use: {
                ...devices['Desktop Chrome'],
                viewport: { width: 390, height: 844 },
                storageState: ETATS.admin,
            },
            dependencies: ['setup'],
            testMatch: [/09-mobile\.spec\.js/, /08-confort\.spec\.js/, /captures\.spec\.js/],
        },
        {
            // Recette du serveur en ligne : aucune session préenregistrée,
            // aucune remise à zéro de base. Voir 10-recette-en-ligne.spec.js.
            name: 'en-ligne',
            use: {
                ...devices['Desktop Chrome'],
                viewport: { width: 1440, height: 900 },
            },
            testMatch: /10-recette-en-ligne\.spec\.js/,
        },
    ],
});
