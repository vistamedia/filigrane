<?php

namespace Tests\Feature;

use App\Models\Drawing;
use App\Models\Photo;
use App\Services\PhotoImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Une fiche à la corbeille doit rester entièrement consultable : c'est de là
 * qu'on décide de la restaurer ou de l'effacer pour de bon.
 */
class CorbeilleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
    }

    private function ficheAvecPhoto(): Photo
    {
        $dessin = Drawing::create(['artist' => 'Anonyme']);

        return app(PhotoImporter::class)->import(
            $dessin,
            base_path('tests/fixtures/dessin-recto.jpg'),
            'dessin-recto.jpg',
        );
    }

    public function test_une_photographie_retrouve_sa_fiche_meme_en_corbeille(): void
    {
        $photo = $this->ficheAvecPhoto();

        $photo->drawing->delete();

        // Sans `withTrashed()` sur la relation, cette ligne renverrait null et
        // toutes les pages affichant la photographie tomberaient en erreur.
        $photo = Photo::findOrFail($photo->id);

        $this->assertNotNull($photo->drawing);
        $this->assertSame('Anonyme', $photo->drawing->artist);
        $this->assertTrue($photo->drawing->trashed());
    }

    public function test_le_nom_de_fichier_telecharge_reste_calculable(): void
    {
        $photo = $this->ficheAvecPhoto();
        $numero = $photo->drawing->inventory_number;

        $photo->drawing->delete();

        $reponse = $this->actingAs($this->admin())->get(route('photos.download', $photo->fresh()));

        $reponse->assertOk();
        $reponse->assertDownload($numero.'-recto-'.$photo->id.'.jpg');
    }

    public function test_la_suppression_definitive_efface_aussi_les_fichiers(): void
    {
        $photo = $this->ficheAvecPhoto();
        $chemins = [$photo->original_path, $photo->web_path, $photo->thumb_path];
        $dessin = $photo->drawing;

        $dessin->delete();

        // Mise à la corbeille : les fichiers restent, la fiche est récupérable.
        Storage::disk('local')->assertExists($chemins[0]);

        $dessin->forceDelete();

        $this->assertDatabaseMissing('photos', ['id' => $photo->id]);
        Storage::disk('local')->assertMissing($chemins[0]);
        Storage::disk('public')->assertMissing($chemins[1]);
        Storage::disk('public')->assertMissing($chemins[2]);
    }

    public function test_un_numero_reste_pris_tant_que_la_fiche_est_en_corbeille(): void
    {
        $premier = Drawing::create([]);
        $premier->delete();

        $this->assertSame('FIL-0002', Drawing::create([])->inventory_number);

        // Effacée pour de bon, elle rend son numéro.
        $premier->forceDelete();
        Drawing::query()->delete();
        Drawing::withTrashed()->get()->each->forceDelete();

        $this->assertSame('FIL-0001', Drawing::create([])->inventory_number);
    }

    private function admin(): \App\Models\User
    {
        return \App\Models\User::create([
            'name' => 'Essai',
            'email' => 'essai@filigrane.test',
            'password' => 'motdepasse',
            'role' => \App\Enums\UserRole::Admin,
        ]);
    }
}
