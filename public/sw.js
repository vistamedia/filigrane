/*
 * Filigrane — service worker.
 *
 * Choix assumé : l'application exige une connexion. Ce service worker ne sert
 * donc qu'à deux choses :
 *   1. rendre l'application installable sur iPhone et sur Mac ;
 *   2. afficher une page lisible plutôt qu'une erreur quand le réseau manque.
 *
 * Rien de ce que l'utilisatrice saisit n'est mis en file d'attente localement,
 * et aucune photo n'est mise en cache : une collection de 250 dessins
 * dépasserait vite le quota accordé à Safari sur iOS.
 *
 * VERSION est l'empreinte des fichiers de SHELL. Le navigateur ne renouvelle
 * ce cache que si sw.js change : une icône remplacée sans changer VERSION
 * resterait l'ancienne, pour toujours, chez qui avait déjà ouvert le site.
 * C'est arrivé le 29 octobre 2026 (DOCS/02, défaut n° 15). Le test
 * ServiceWorkerTest calcule l'empreinte et refuse l'oubli.
 */

const VERSION = 'filigrane-f40f5b81';
const SHELL = [
  '/hors-ligne',
  '/icones/icone-192x192.png',
  '/icones/favicon.svg',
  '/manifest.webmanifest',
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches
      .open(VERSION)
      // « reload » : sinon le cache HTTP du navigateur, qui garde longtemps
      // les icônes, resservirait l'ancienne au nouveau cache.
      .then((cache) => cache.addAll(SHELL.map((url) => new Request(url, { cache: 'reload' }))))
      .then(() => self.skipWaiting()),
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches
      .keys()
      .then((keys) => Promise.all(keys.filter((key) => key !== VERSION).map((key) => caches.delete(key))))
      .then(() => self.clients.claim()),
  );
});

self.addEventListener('fetch', (event) => {
  const { request } = event;

  // Tout ce qui modifie des données passe directement au réseau : on ne
  // veut surtout pas rejouer une requête d'enregistrement.
  if (request.method !== 'GET') {
    return;
  }

  const url = new URL(request.url);

  if (url.origin !== self.location.origin) {
    return;
  }

  // Les icônes et le manifeste : cache d'abord, c'est immuable.
  if (SHELL.includes(url.pathname)) {
    event.respondWith(caches.match(request).then((hit) => hit || fetch(request)));
    return;
  }

  // Navigation : réseau, et page d'attente lisible en cas d'échec.
  if (request.mode === 'navigate') {
    event.respondWith(fetch(request).catch(() => caches.match('/hors-ligne')));
  }
});
