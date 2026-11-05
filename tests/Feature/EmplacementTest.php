<?php

namespace Tests\Feature;

use App\Enums\Location;
use App\Enums\UserRole;
use App\Filament\Pages\ImportEnMasse;
use App\Filament\Resources\Drawings\Pages\EditDrawing;
use App\Filament\Resources\Drawings\Pages\ListDrawings;
use App\Models\Drawing;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * L'emplacement d'un dessin : un carton ou une pièce, et pour « Autre » une
 * précision libre. Il se donne sur la fiche, par lots depuis la liste, ou
 * d'un coup pour tout un import.
 */
class EmplacementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');

        $this->actingAs(User::factory()->create(['role' => UserRole::Curator]));
    }

    public function test_autre_s_affiche_par_sa_precision(): void
    {
        $dessin = Drawing::create(['location' => Location::Other, 'location_other' => '  grenier ']);

        $this->assertSame('grenier', $dessin->refresh()->locationLabel());

        $dessin->update(['location_other' => '']);
        $this->assertSame('Autre', $dessin->refresh()->locationLabel());

        $this->assertNull(Drawing::create([])->locationLabel());
    }

    public function test_choisir_une_piece_de_la_liste_efface_la_precision(): void
    {
        $dessin = Drawing::create(['location' => Location::Other, 'location_other' => 'grenier']);

        $dessin->update(['location' => Location::LivingRoom]);

        // Sans quoi « grenier » ressurgirait le jour où l'on repasserait à
        // « Autre ».
        $this->assertNull($dessin->refresh()->location_other);
        $this->assertSame('Grand salon', $dessin->locationLabel());
    }

    public function test_la_fiche_enregistre_l_emplacement_et_sa_precision(): void
    {
        $dessin = Drawing::create([]);

        Livewire::test(EditDrawing::class, ['record' => $dessin->getRouteKey()])
            ->fillForm(['location' => Location::Other->value])
            ->assertFormFieldIsVisible('location_other')
            ->fillForm(['location_other' => 'chez l\'encadreur'])
            ->call('save')
            ->assertHasNoFormErrors();

        $dessin->refresh();
        $this->assertSame(Location::Other, $dessin->location);
        $this->assertSame('chez l\'encadreur', $dessin->location_other);

        // La précision ne se propose que pour « Autre ».
        Livewire::test(EditDrawing::class, ['record' => $dessin->getRouteKey()])
            ->fillForm(['location' => Location::Box1->value])
            ->assertFormFieldIsHidden('location_other');
    }

    public function test_plusieurs_fiches_changent_d_emplacement_d_un_coup(): void
    {
        $premier = Drawing::create([]);
        $second = Drawing::create(['location' => Location::Other, 'location_other' => 'grenier']);
        $laisse = Drawing::create(['location' => Location::Box1]);

        Livewire::test(ListDrawings::class)
            ->set('activeTab', 'tous')
            ->callTableBulkAction('emplacement', [$premier, $second], data: ['location' => Location::Box2->value])
            ->assertHasNoFormErrors()
            ->assertNotified('2 fiches placées à l\'emplacement « Carton 2 »');

        foreach ([$premier, $second] as $dessin) {
            $dessin->refresh();
            $this->assertSame(Location::Box2, $dessin->location);
            $this->assertNull($dessin->location_other);
        }

        $this->assertSame(Location::Box1, $laisse->refresh()->location);
    }

    public function test_l_action_par_lots_accepte_autre_avec_sa_precision(): void
    {
        $dessin = Drawing::create([]);

        Livewire::test(ListDrawings::class)
            ->set('activeTab', 'tous')
            ->callTableBulkAction('emplacement', [$dessin], data: [
                'location' => Location::Other->value,
                'location_other' => 'grenier',
            ])
            ->assertNotified('1 fiche placée à l\'emplacement « grenier »');

        $this->assertSame('grenier', $dessin->refresh()->locationLabel());
    }

    public function test_l_import_en_masse_donne_son_emplacement_a_chaque_fiche(): void
    {
        $page = Livewire::test(ImportEnMasse::class)
            ->set('data.fichiers', [
                UploadedFile::fake()->image('premier.jpg', 600, 800),
                UploadedFile::fake()->image('second.jpg', 600, 800),
            ])
            ->set('data.location', Location::Box3->value)
            ->call('demarrer');

        $page->call('suivant')->call('suivant')->assertSet('echecs', []);

        // La fiche est créée avant que l'image soit lue : on vérifie aussi
        // que les deux photographies sont bien arrivées.
        $this->assertSame(2, Photo::count());
        $this->assertSame(2, Drawing::count());
        $this->assertSame(2, Drawing::where('location', Location::Box3)->count());
    }
}
