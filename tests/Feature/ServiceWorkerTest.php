<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Le service worker sert les icônes et la page hors-ligne depuis son propre
 * cache, avant le réseau. Ce cache ne se renouvelle que si le fichier sw.js
 * change : d'où une version qui est l'empreinte de ces fichiers.
 *
 * Le 29 octobre 2026, les icônes ont été remplacées sans que la version
 * bouge : les navigateurs qui avaient déjà ouvert le site ont gardé
 * l'ancienne icône, sauf à forcer le rechargement.
 */
class ServiceWorkerTest extends TestCase
{
    public function test_la_version_du_service_worker_suit_les_fichiers_qu_il_met_en_cache(): void
    {
        $sw = file_get_contents(public_path('sw.js'));

        $this->assertSame(1, preg_match("/const VERSION = '([^']+)';/", $sw, $version));
        $this->assertSame(1, preg_match('/const SHELL = \[(.*?)\];/s', $sw, $liste));
        preg_match_all("/'(\/[^']*)'/", $liste[1], $adresses);

        $empreintes = array_map(function (string $adresse): string {
            // La page hors-ligne est une vue, pas un fichier de public/.
            $fichier = $adresse === '/hors-ligne'
                ? resource_path('views/offline.blade.php')
                : public_path(ltrim($adresse, '/'));

            $this->assertFileExists($fichier, "Le service worker met en cache {$adresse}, introuvable.");

            return md5_file($fichier);
        }, $adresses[1]);

        $attendue = 'filigrane-'.substr(md5(implode('', $empreintes)), 0, 8);

        $this->assertSame(
            $attendue,
            $version[1],
            "Un fichier mis en cache par public/sw.js a changé : remplacer sa VERSION par '{$attendue}'.",
        );
    }
}
