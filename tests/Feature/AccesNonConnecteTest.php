<?php

namespace Tests\Feature;

use App\Enums\PhotoKind;
use App\Models\Drawing;
use App\Services\PhotoImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ce qu'un visiteur sans compte obtient.
 *
 * Le panneau Filament nomme sa route de connexion
 * « filament.admin.auth.login », pas « login ». Le middleware « auth » de
 * Laravel, lui, cherche une route nommée « login » pour y renvoyer les
 * visiteurs : sans redirection explicitement configurée
 * (`bootstrap/app.php`), les routes hors panneau ne redirigeaient pas, elles
 * tombaient en **erreur 500**. Ce test garde la porte fermée.
 */
class AccesNonConnecteTest extends TestCase
{
    use RefreshDatabase;

    public function test_une_photographie_d_origine_renvoie_vers_la_connexion(): void
    {
        $dessin = Drawing::create([]);

        $photo = app(PhotoImporter::class)->import(
            $dessin,
            base_path('tests/fixtures/dessin-recto.jpg'),
            'dessin-recto.jpg',
            ['kind' => PhotoKind::Recto],
        );

        $this->get(route('photos.original', $photo))->assertRedirect('/login');
        $this->get(route('photos.download', $photo))->assertRedirect('/login');
    }

    public function test_la_remise_d_un_export_renvoie_vers_la_connexion(): void
    {
        $this->get('/exports/0123456789abcdef.pdf')->assertRedirect('/login');
    }

    public function test_le_panneau_renvoie_vers_la_connexion(): void
    {
        $this->get('/dessins')->assertRedirect('/login');
        $this->get('/diaporama')->assertRedirect('/login');
    }

    public function test_la_presentation_publique_reste_ouverte(): void
    {
        $this->get('/a-propos')
            ->assertOk()
            ->assertSee('Filigrane')
            ->assertSee('Conception et développement');
    }
}
