<?php

namespace Tests\Feature;

use App\Enums\PhotoKind;
use App\Models\Drawing;
use App\Models\Tag;
use App\Services\ExportCatalogue;
use App\Services\ExportTableur;
use App\Services\PhotoImporter;
use App\Support\TexteRiche;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

/**
 * Les deux exports du catalogue.
 *
 * Le tableur est relu avec le lecteur de la même bibliothèque : c'est le seul
 * moyen d'attraper un fichier qu'Excel refuserait d'ouvrir — un .xlsx mal
 * formé reste un fichier de taille plausible.
 */
class ExportTest extends TestCase
{
    use RefreshDatabase;

    private function dessinDecrit(array $attributs = []): Drawing
    {
        return Drawing::create(array_merge([
            'artist' => 'Attribué à Hubert Robert',
            'period' => 'Vers 1780',
            'description' => '<p>Vue de ruines à la <strong>sanguine</strong>.</p><p>Second paragraphe.</p>',
            'note' => '<p>À rapprocher du carton 3.</p>',
            'width_mm' => 300,
            'height_mm' => 405,
        ], $attributs));
    }

    public function test_le_tableur_est_relisible_et_porte_les_bonnes_colonnes(): void
    {
        $dessin = $this->dessinDecrit();
        $dessin->tags()->attach(Tag::create(['name' => 'Paysage'])->id);

        $chemin = storage_path('app/private/essai-tableur.xlsx');

        $nombre = app(ExportTableur::class)->ecrire(Drawing::query(), $chemin);

        $this->assertSame(1, $nombre);

        $lignes = [];
        $lecteur = new Reader();
        $lecteur->open($chemin);

        foreach ($lecteur->getSheetIterator() as $feuille) {
            $this->assertSame('Catalogue', $feuille->getName());

            foreach ($feuille->getRowIterator() as $ligne) {
                $lignes[] = $ligne->toArray();
            }
        }

        $lecteur->close();
        @unlink($chemin);

        $this->assertCount(2, $lignes);
        $this->assertSame('Inventaire', $lignes[0][0]);

        [$cote, $artiste, $periode, $hauteur, $largeur] = $lignes[1];

        $this->assertSame('FIL-0001', $cote);
        $this->assertSame('Attribué à Hubert Robert', $artiste);
        $this->assertSame('Vers 1780', $periode);

        // Les dimensions sortent en nombres, en millimètres : un tableur doit
        // pouvoir les trier et les additionner.
        $this->assertSame(405, $hauteur);
        $this->assertSame(300, $largeur);

        // Et la description perd son balisage sans perdre ses paragraphes.
        $this->assertStringContainsString('Vue de ruines à la sanguine.', $lignes[1][8]);
        $this->assertStringContainsString("\n", $lignes[1][8]);
        $this->assertStringNotContainsString('<strong>', $lignes[1][8]);
        $this->assertSame('Paysage', $lignes[1][6]);
    }

    public function test_le_tableur_suit_le_filtre_qu_on_lui_donne(): void
    {
        $this->dessinDecrit();
        $this->dessinDecrit(['description' => null, 'artist' => 'Anonyme']);

        $chemin = storage_path('app/private/essai-filtre.xlsx');

        $nombre = app(ExportTableur::class)->ecrire(Drawing::query()->commented(), $chemin);

        @unlink($chemin);

        $this->assertSame(1, $nombre);
    }

    public function test_le_catalogue_pdf_contient_une_page_par_dessin(): void
    {
        $dessin = $this->dessinDecrit();

        app(PhotoImporter::class)->import(
            $dessin,
            base_path('tests/fixtures/dessin-recto.jpg'),
            'dessin-recto.jpg',
            ['kind' => PhotoKind::Recto],
        );

        $chemin = storage_path('app/private/essai-catalogue.pdf');

        $nombre = app(ExportCatalogue::class)->ecrire(Drawing::query()->commented(), $chemin, avecNotes: true);

        $this->assertSame(1, $nombre);
        $this->assertFileExists($chemin);

        $contenu = file_get_contents($chemin);

        $this->assertStringStartsWith('%PDF-', $contenu);

        // Une page de garde plus une planche.
        $this->assertSame(2, preg_match_all('#/Type\s*/Page[^s]#', $contenu));

        @unlink($chemin);
    }

    public function test_le_catalogue_laisse_les_notes_de_cote_quand_on_le_demande(): void
    {
        $dessin = $this->dessinDecrit(['note' => '<p>Remarque strictement personnelle.</p>']);

        app(PhotoImporter::class)->import(
            $dessin,
            base_path('tests/fixtures/dessin-recto.jpg'),
            'dessin-recto.jpg',
            ['kind' => PhotoKind::Recto],
        );

        $chemin = storage_path('app/private/essai-sans-notes.pdf');

        app(ExportCatalogue::class)->ecrire(Drawing::query(), $chemin, avecNotes: false);

        // Le texte d'un PDF produit par dompdf est compressé : on ne peut pas
        // le chercher tel quel. On compare donc les tailles — la planche avec
        // note est nécessairement plus lourde.
        $avecNotes = storage_path('app/private/essai-avec-notes.pdf');
        app(ExportCatalogue::class)->ecrire(Drawing::query(), $avecNotes, avecNotes: true);

        $this->assertLessThan(filesize($avecNotes), filesize($chemin));

        @unlink($chemin);
        @unlink($avecNotes);
    }

    public function test_les_dossiers_de_travail_ne_survivent_pas_a_l_export(): void
    {
        $dessin = $this->dessinDecrit();

        app(PhotoImporter::class)->import(
            $dessin,
            base_path('tests/fixtures/dessin-recto.jpg'),
            'dessin-recto.jpg',
            ['kind' => PhotoKind::Recto],
        );

        $chemin = storage_path('app/private/essai-menage.pdf');

        app(ExportCatalogue::class)->ecrire(Drawing::query(), $chemin);

        // Les images réduites sont fabriquées dans un dossier temporaire qui
        // doit disparaître : deux cents exports laisseraient sinon des
        // gigaoctets derrière eux.
        $restes = glob(storage_path('app/private/exports/*'));

        @unlink($chemin);

        $this->assertSame([], $restes ?: []);
    }

    public function test_le_texte_riche_est_ramene_a_ce_que_l_editeur_produit(): void
    {
        $sale = '<p onclick="voler()">Bonjour <script>alert(1)</script><strong>vous</strong></p>'
            .'<a href="javascript:voler()">piège</a><a href="https://example.org">lien</a>';

        $propre = TexteRiche::nettoyer($sale);

        $this->assertStringNotContainsString('<script>', $propre);
        $this->assertStringNotContainsString('javascript:', $propre);
        $this->assertStringContainsString('<strong>vous</strong>', $propre);
        $this->assertStringContainsString('href="https://example.org"', $propre);
    }

    public function test_un_champ_riche_sans_texte_est_tenu_pour_vide(): void
    {
        $this->assertTrue(TexteRiche::estVide('<p></p>'));
        $this->assertTrue(TexteRiche::estVide('<p><br></p>'));
        $this->assertTrue(TexteRiche::estVide(null));
        $this->assertFalse(TexteRiche::estVide('<p>Un mot</p>'));

        // Et il ne ressort pas en « <p></p> » : un champ seulement effleuré
        // ferait sinon apparaître un bloc « Note » vide dans le diaporama
        // comme dans le catalogue.
        $this->assertSame('', TexteRiche::nettoyer('<p></p>'));
        $this->assertSame('', TexteRiche::nettoyer('<p><br></p>'));
    }
}
