<?php

namespace App\Console\Commands;

use App\Enums\DrawingStatus;
use App\Enums\PhotoKind;
use App\Models\Drawing;
use App\Models\Tag;
use App\Services\PhotoImporter;
use Illuminate\Console\Command;

/**
 * Remplit la base de fiches décrites, pour regarder le diaporama, les exports
 * et les captures d'écran sans attendre que la collection soit saisie.
 *
 * Refuse de s'exécuter en production : ces fiches n'ont rien à y faire.
 */
class JeuDEssai extends Command
{
    protected $signature = 'filigrane:jeu-d-essai
        {--nombre=8 : Nombre de fiches à créer}
        {--force : Créer les fiches même s\'il en existe déjà de décrites}';

    protected $description = 'Crée des fiches décrites avec photographie, pour le développement';

    /**
     * Des textes **entièrement inventés**, de longueurs variées : ils ne
     * décrivent aucune œuvre réelle et ne doivent jamais être pris pour le
     * contenu de la collection. Ce qu'on leur demande, c'est d'éprouver les
     * mises en page — un titre court, une description longue, une note vide.
     */
    private const PLANCHES = [
        [
            'artist' => 'Attribué à Hubert Robert',
            'period' => 'Vers 1780',
            'description' => '<p>Vue de ruines antiques animée de quelques figures, à la <strong>sanguine</strong> sur papier vergé. La main est rapide, presque sténographique dans les lointains, et se resserre sur les deux personnages assis au premier plan.</p><p>Le trait de construction reste visible sous le lavis, ce qui plaide pour une étude prise sur le motif plutôt que pour une feuille d\'atelier.</p>',
            'note' => '<p>Comparer avec les feuilles du carton 3, même papier, même filigrane au raisin.</p>',
            'tags' => ['Paysage', 'École française'],
        ],
        [
            'artist' => 'Anonyme',
            'period' => 'XVIIe siècle',
            'description' => '<p>Étude de draperie à la <em>pierre noire</em>, rehauts de craie blanche sur papier bleuté passé. Le drapé tombe en plis cassés, d\'une écriture large qui cherche le volume plus que le détail.</p><p>Angle inférieur droit restauré anciennement.</p>',
            'note' => '',
            'tags' => ['Étude de draperie'],
        ],
        [
            'artist' => 'École italienne',
            'period' => 'Fin du XVIe siècle',
            'description' => '<p>Tête d\'homme barbu tournée vers la gauche, à la plume et encre brune. Les hachures croisées, serrées sous la pommette, sont caractéristiques des ateliers bolonais.</p><ul><li>Filigrane : croissant dans un cercle</li><li>Collé en plein sur un montage ancien</li></ul>',
            'note' => '<p>Montage à conserver : il porte une inscription à la plume, en partie effacée.</p>',
            'tags' => ['Portrait', 'École italienne'],
        ],
        [
            'artist' => 'Anonyme',
            'period' => 'Vers 1820',
            'description' => '<p>Paysage boisé traversé d\'un chemin creux, mine de plomb sur papier vélin. Feuille d\'une grande douceur, entièrement construite par valeurs, sans aucun trait de contour.</p>',
            'note' => '',
            'tags' => ['Paysage'],
        ],
        [
            'artist' => 'Entourage de Charles Le Brun',
            'period' => 'Vers 1660',
            'description' => '<p>Étude de bras levé, pierre noire et craie blanche sur papier gris-bleu. La mise au carreau, encore lisible, indique une feuille destinée au report sur une composition de grand format.</p><p>Deux reprises du poignet, l\'une abandonnée, témoignent d\'une recherche en cours.</p>',
            'note' => '<p>Vérifier auprès du département des Arts graphiques s\'il existe un morceau correspondant.</p>',
            'tags' => ['École française'],
        ],
        [
            'artist' => 'Anonyme',
            'period' => 'XVIIIe siècle',
            'description' => '<p>Académie d\'homme assis, sanguine. Exercice d\'école, d\'une exécution appliquée, où le modelé des épaules est mieux venu que celui des jambes.</p>',
            'note' => '',
            'tags' => ['École française'],
        ],
        [
            'artist' => 'Anonyme',
            'period' => 'Vers 1750',
            'description' => '<p>Projet de dessus-de-porte à la plume et lavis gris : une cartouche rocaille encadrant un motif de trophée. Le tracé à la règle et au compas est encore apparent sous le lavis.</p>',
            'note' => '<p>Encadré, salon. Vitre à dépoussiérer.</p>',
            'tags' => ['Encadré — salon'],
            // Une vue au mur, prise d'en bas : de quoi montrer la retouche.
            'vue_au_mur' => true,
        ],
        [
            'artist' => 'Jean-Baptiste Oudry',
            'period' => '1740',
            'description' => '<p>Étude de chien couché, pierre noire rehaussée de blanc sur papier bleu. Signée en bas à droite, d\'une écriture fine et penchée.</p><p>La feuille a été pliée en deux dans le sens de la hauteur ; le pli, visible en lumière rasante, ne traverse pas le sujet.</p>',
            'note' => '<p>Pièce la plus importante du fonds, à mon sens. À faire voir.</p>',
            'tags' => ['École française', 'Carton 1'],
        ],
    ];

    public function handle(PhotoImporter $importeur): int
    {
        if (app()->isProduction()) {
            $this->error('Cette commande ne doit pas être lancée en production.');

            return self::FAILURE;
        }

        // Sans ce garde-fou, relancer la commande empilerait huit fiches de
        // plus à chaque fois.
        if (! $this->option('force') && Drawing::query()->commented()->exists()) {
            $this->components->info('Des fiches décrites existent déjà — rien à faire (--force pour forcer).');

            return self::SUCCESS;
        }

        $source = base_path('tests/fixtures/dessin-recto.jpg');
        $cadre = base_path('tests/fixtures/cadre-en-perspective.jpg');

        foreach ([$source, $cadre] as $image) {
            if (! is_readable($image)) {
                $this->error("Image de référence introuvable : {$image}");
                $this->line('Lancez d\'abord : php scripts/generer-images-test.php');

                return self::FAILURE;
            }
        }

        $nombre = min((int) $this->option('nombre'), count(self::PLANCHES));

        foreach (array_slice(self::PLANCHES, 0, $nombre) as $planche) {
            $dessin = Drawing::create([
                'artist' => $planche['artist'],
                'period' => $planche['period'],
                'description' => $planche['description'],
                'note' => $planche['note'],
                'status' => DrawingStatus::Described,
            ]);

            $importeur->import($dessin, $source, 'dessin-recto.jpg', ['kind' => PhotoKind::Recto]);

            if ($planche['vue_au_mur'] ?? false) {
                $importeur->import($dessin, $cadre, 'cadre-en-perspective.jpg', ['kind' => PhotoKind::Frame]);
            }

            $dessin->tags()->sync(
                collect($planche['tags'])
                    ->map(fn (string $nom): int => Tag::firstOrCreate(['name' => $nom])->id)
                    ->all(),
            );

            $this->line("✓ {$dessin->inventory_number} — {$planche['artist']}");
        }

        $this->info("{$nombre} fiches décrites créées.");

        return self::SUCCESS;
    }
}
