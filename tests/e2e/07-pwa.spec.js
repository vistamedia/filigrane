import { expect, test } from '@playwright/test';

test.describe('Application installable', () => {
    test('le manifeste décrit une application autonome en français', async ({ request }) => {
        const reponse = await request.get('/manifest.webmanifest');
        expect(reponse.status()).toBe(200);

        const manifeste = await reponse.json();

        expect(manifeste.name).toContain('Filigrane');
        expect(manifeste.short_name).toBe('Filigrane');
        expect(manifeste.lang).toBe('fr');
        expect(manifeste.display).toBe('standalone');
        expect(manifeste.start_url).toBe('/');
        expect(manifeste.scope).toBe('/');

        // Chrome exige une icône 192 et une 512 pour proposer l'installation ;
        // iOS se sert de l'icône « maskable » pour l'écran d'accueil.
        const tailles = manifeste.icons.map((i) => `${i.sizes}:${i.purpose}`);
        expect(tailles).toContain('192x192:any');
        expect(tailles).toContain('512x512:any');
        expect(tailles).toContain('512x512:maskable');
    });

    test('toutes les icônes déclarées existent vraiment', async ({ request }) => {
        const manifeste = await (await request.get('/manifest.webmanifest')).json();

        for (const icone of manifeste.icons) {
            const reponse = await request.get(icone.src);
            expect(reponse.status(), `${icone.src} doit exister`).toBe(200);
            expect(reponse.headers()['content-type']).toContain('image');
        }

        // Et l'icône que Safari pose sur l'écran d'accueil.
        expect((await request.get('/icones/icone-180x180.png')).status()).toBe(200);
    });

    test('les balises iOS sont présentes dans la page', async ({ page }) => {
        await page.goto('/');

        await expect(page.locator('link[rel="manifest"]')).toHaveAttribute('href', '/manifest.webmanifest');
        await expect(page.locator('link[rel="apple-touch-icon"]')).toHaveAttribute('href', '/icones/icone-180x180.png');
        await expect(page.locator('meta[name="apple-mobile-web-app-capable"]')).toHaveAttribute('content', 'yes');
        await expect(page.locator('meta[name="apple-mobile-web-app-title"]')).toHaveAttribute('content', 'Filigrane');
        await expect(page.locator('meta[name="theme-color"]')).toHaveAttribute('content', '#FBF8F2');
    });

    test('le service worker s\'enregistre et prend la main', async ({ page }) => {
        await page.goto('/');

        const portee = await page.evaluate(async () => (await navigator.serviceWorker.ready).scope);
        expect(portee).toMatch(/\/$/);

        // L'activation prend quelques dizaines de millisecondes après
        // l'inscription : on laisse le temps au passage « activating » →
        // « activated ».
        await expect
            .poll(() => page.evaluate(async () =>
                (await navigator.serviceWorker.ready).active?.state ?? null))
            .toBe('activated');
    });

    test('sans réseau, une page lisible remplace l\'erreur du navigateur', async ({ page, context }) => {
        // On amorce le service worker, qui met la page d'attente en cache.
        await page.goto('/');
        await page.evaluate(() => navigator.serviceWorker.ready);

        await context.setOffline(true);
        await page.goto('/dessins').catch(() => {});

        await expect(page.getByRole('heading', { name: 'Pas de connexion' })).toBeVisible();
        await expect(page.getByText(/Filigrane a besoin d'Internet/)).toBeVisible();
        await expect(page.getByRole('button', { name: 'Réessayer' })).toBeVisible();

        await context.setOffline(false);
    });

    test('le service worker ne se met jamais en travers d\'un enregistrement', async ({ page }) => {
        await page.goto('/');
        await page.evaluate(() => navigator.serviceWorker.ready);

        // Les requêtes POST doivent partir au réseau sans passer par le cache :
        // rejouer un enregistrement depuis le cache corromprait une fiche.
        const interception = await page.evaluate(async () => {
            const reponse = await fetch('/livewire/update', { method: 'POST', body: '{}' });

            return { statut: reponse.status, depuisCache: reponse.headers.get('x-sw-cache') };
        });

        expect(interception.depuisCache).toBeNull();
        expect(interception.statut).toBeGreaterThanOrEqual(400);
    });
});
