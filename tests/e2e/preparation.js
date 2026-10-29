import { execFile } from 'node:child_process';
import { rm } from 'node:fs/promises';
import path from 'node:path';
import { promisify } from 'node:util';

const executer = promisify(execFile);

/**
 * Préparation de la campagne de tests.
 *
 * On repart d'une base vierge : plusieurs tests comptent des fiches ou des
 * photographies, et des données laissées par une campagne précédente les
 * feraient échouer sans qu'aucun défaut réel soit en cause.
 *
 * Remarque : cette remise à zéro doit précéder l'ouverture des sessions
 * (projet « setup »), car les sessions sont stockées en base.
 *
 * Ne s'exécute que sur l'environnement local.
 */
export default async function preparer(config) {
    const base = config.projects[0]?.use?.baseURL ?? '';

    if (! base.includes('localhost')) {
        return;
    }

    const racine = path.resolve(config.rootDir, '../..');

    try {
        await executer('docker', [
            'compose', 'exec', '-T', 'app',
            'php', 'artisan', 'migrate:fresh', '--seed', '--force',
        ], { cwd: racine });

        // Les dérivés et les originaux des campagnes précédentes n'ont plus de
        // ligne en base : on les efface pour ne pas accumuler des gigaoctets.
        await Promise.all([
            rm(path.join(racine, 'storage/app/private/photos'), { recursive: true, force: true }),
            rm(path.join(racine, 'storage/app/public/photos'), { recursive: true, force: true }),
        ]);
    } catch (erreur) {
        console.warn('Base de test non réinitialisée :', erreur.message);
    }
}
