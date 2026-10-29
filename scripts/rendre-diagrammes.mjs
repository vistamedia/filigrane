#!/usr/bin/env node
/**
 * Transforme les blocs Mermaid des documents en images SVG.
 *
 *   node scripts/rendre-diagrammes.mjs
 *
 * Les documents gardent le diagramme sous forme de texte — c'est lui qui fait
 * foi et qui se relit dans une revue — et l'image produite ici sert aux lecteurs
 * dont l'outil ne sait pas interpréter Mermaid.
 *
 * On passe par le Chromium déjà installé pour les tests plutôt que par
 * mermaid-cli, qui téléchargerait un second navigateur.
 */

import { chromium } from '@playwright/test';
import { mkdir, readFile, readdir, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const RACINE = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const DOCS = path.join(RACINE, 'DOCS');
const SORTIE = path.join(DOCS, 'diagrammes');

/** Nom de fichier déduit du document : 01-schema-base-de-donnees.md → schema-base-de-donnees */
function nomDuDiagramme(document, rang) {
    const base = document.replace(/^\d+-/, '').replace(/\.md$/, '');

    return rang === 0 ? base : `${base}-${rang + 1}`;
}

async function extraireBlocs(fichier) {
    const contenu = await readFile(fichier, 'utf8');
    const blocs = [...contenu.matchAll(/```mermaid\n([\s\S]*?)```/g)];

    return blocs.map((bloc) => bloc[1]);
}

const page = await (await chromium.launch()).newPage();

await page.setContent(`<!doctype html>
<html><head><meta charset="utf-8">
<style>
  body { margin: 0; background: #FBF8F2; font-family: ui-sans-serif, system-ui, sans-serif; }
  #cible { padding: 24px; display: inline-block; }
</style>
</head><body><div id="cible"></div></body></html>`);

await page.addScriptTag({ url: 'https://cdn.jsdelivr.net/npm/mermaid@11.12.0/dist/mermaid.min.js' });

await page.evaluate(() => {
    window.mermaid.initialize({
        startOnLoad: false,
        theme: 'base',
        themeVariables: {
            background: '#FBF8F2',
            primaryColor: '#FFFFFF',
            primaryTextColor: '#1C1A17',
            primaryBorderColor: '#A3553E',
            lineColor: '#8A8070',
            fontFamily: 'ui-sans-serif, system-ui, sans-serif',
            fontSize: '15px',
        },
    });
});

await mkdir(SORTIE, { recursive: true });

const documents = (await readdir(DOCS)).filter((f) => f.endsWith('.md'));
let total = 0;

for (const document of documents) {
    const blocs = await extraireBlocs(path.join(DOCS, document));

    for (const [rang, source] of blocs.entries()) {
        const nom = nomDuDiagramme(document, rang);

        const svg = await page.evaluate(async ([code, identifiant]) => {
            const { svg } = await window.mermaid.render(identifiant, code);

            return svg;
        }, [source, `diagramme-${total}`]);

        await writeFile(path.join(SORTIE, `${nom}.svg`), svg, 'utf8');
        console.log(`✓ DOCS/diagrammes/${nom}.svg`);
        total++;
    }
}

if (total === 0) {
    console.log('Aucun bloc mermaid trouvé dans DOCS/.');
}

await page.context().browser().close();
