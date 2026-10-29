import { expect, test } from '@playwright/test';

/**
 * Confort d'usage.
 *
 * L'application est utilisée par une personne âgée, sur un iPhone comme
 * sur un Mac. Ces vérifications portent sur ce qui conditionne la lisibilité
 * et la précision du geste, et non sur l'apparence.
 */

/**
 * Convertit une couleur CSS en canaux rouge, vert, bleu.
 *
 * Filament exprime ses couleurs en oklch : plutôt que de réimplémenter la
 * conversion, on laisse le navigateur peindre la couleur et on relit le pixel.
 */
async function enRvb(page, couleur) {
    return page.evaluate((valeur) => {
        const toile = document.createElement('canvas');
        toile.width = toile.height = 1;

        const contexte = toile.getContext('2d');
        contexte.fillStyle = valeur;
        contexte.fillRect(0, 0, 1, 1);

        return Array.from(contexte.getImageData(0, 0, 1, 1).data).slice(0, 3);
    }, couleur);
}

/** Luminance relative, au sens des règles WCAG. */
function luminance([r, v, b]) {
    const canaux = [r, v, b].map((n) => {
        const c = n / 255;

        return c <= 0.04045 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
    });

    return 0.2126 * canaux[0] + 0.7152 * canaux[1] + 0.0722 * canaux[2];
}

function contraste(premier, second) {
    const [clair, sombre] = [luminance(premier), luminance(second)].sort((a, b) => b - a);

    return (clair + 0.05) / (sombre + 0.05);
}

test.describe('Confort de lecture et de manipulation', () => {
    test('le texte part à 17 pixels, au-dessus du réglage par défaut', async ({ page }) => {
        await page.goto('/');

        const taille = await page.evaluate(() =>
            parseFloat(getComputedStyle(document.documentElement).fontSize));

        expect(taille).toBeGreaterThanOrEqual(17);
    });

    test('le bouton principal reste lisible sur fond clair', async ({ page }) => {
        await page.goto('/dessins');

        const bouton = page.getByRole('link', { name: 'Nouvelle fiche' });
        await expect(bouton).toBeVisible();

        const couleurs = await bouton.evaluate((element) => {
            const style = getComputedStyle(element);

            return { texte: style.color, fond: style.backgroundColor };
        });

        const rapport = contraste(
            await enRvb(page, couleurs.texte),
            await enRvb(page, couleurs.fond),
        );

        // WCAG AA demande 4,5:1 pour du texte courant ; la nuance sanguine 600
        // a été choisie pour dépasser 6:1, marge utile passé 90 ans.
        expect(rapport, `contraste mesuré : ${rapport.toFixed(2)}:1`).toBeGreaterThan(6);
    });

    test('les boutons d\'action offrent une cible confortable au doigt', async ({ page }) => {
        await page.goto('/dessins');

        const boutons = page.locator('.fi-btn');
        const nombre = await boutons.count();

        expect(nombre).toBeGreaterThan(0);

        for (let i = 0; i < nombre; i++) {
            const bouton = boutons.nth(i);

            if (! await bouton.isVisible()) {
                continue;
            }

            const cadre = await bouton.boundingBox();

            // 44 px est le minimum recommandé par Apple pour une cible tactile.
            expect(cadre.height, `bouton n°${i + 1}`).toBeGreaterThanOrEqual(44);
        }
    });

    test('aucun débordement horizontal à la largeur d\'un iPhone', async ({ page }) => {
        await page.setViewportSize({ width: 390, height: 844 });

        for (const url of ['/', '/dessins', '/studio-photo', '/import']) {
            await page.goto(url);

            const deborde = await page.evaluate(() =>
                document.documentElement.scrollWidth > document.documentElement.clientWidth + 1);

            expect(deborde, `${url} ne doit pas déborder`).toBe(false);
        }
    });
});
