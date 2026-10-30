<?php

namespace Tests\Feature;

use App\Enums\DrawingStatus;
use App\Enums\UserRole;
use App\Filament\Resources\Drawings\Pages\EditDrawing;
use App\Filament\Resources\Drawings\Pages\ListDrawings;
use App\Models\Drawing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Le passage automatique de « à décrire » à « en cours » à l'enregistrement
 * de la fiche, et surtout ce qui ne doit pas le déclencher ; puis le
 * changement d'avancement par lots, depuis la liste des dessins.
 */
class AvancementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['role' => UserRole::Curator]));
    }

    private function enregistrer(Drawing $dessin, array $champs): DrawingStatus
    {
        Livewire::test(EditDrawing::class, ['record' => $dessin->getRouteKey()])
            ->fillForm($champs)
            ->call('save')
            ->assertHasNoFormErrors();

        return $dessin->refresh()->status;
    }

    public function test_ecrire_une_description_fait_passer_la_fiche_en_cours(): void
    {
        $dessin = Drawing::create(['status' => DrawingStatus::ToDescribe]);

        $statut = $this->enregistrer($dessin, ['description' => '<p>Paysage à la sanguine.</p>']);

        $this->assertSame(DrawingStatus::InProgress, $statut);
    }

    public function test_ecrire_un_artiste_fait_passer_la_fiche_en_cours(): void
    {
        $dessin = Drawing::create(['status' => DrawingStatus::ToDescribe]);

        $this->assertSame(DrawingStatus::InProgress, $this->enregistrer($dessin, ['artist' => 'Anonyme']));
    }

    public function test_un_clic_dans_la_description_ne_compte_pas_comme_un_debut(): void
    {
        // L'éditeur enregistre « <p></p> » dès qu'on a posé le curseur dans
        // le champ : c'est ainsi que FIL-0012 était passé en cours.
        $dessin = Drawing::create(['status' => DrawingStatus::ToDescribe]);

        $this->assertSame(DrawingStatus::ToDescribe, $this->enregistrer($dessin, ['description' => '<p></p>']));
    }

    public function test_remettre_a_decrire_a_la_main_est_respecte(): void
    {
        $dessin = Drawing::create([
            'status' => DrawingStatus::InProgress,
            'artist' => 'Anonyme',
            'description' => '<p>Paysage à la sanguine.</p>',
        ]);

        $this->assertSame(DrawingStatus::ToDescribe, $this->enregistrer($dessin, ['status' => DrawingStatus::ToDescribe]));
    }

    public function test_une_fiche_remise_a_decrire_ne_repasse_pas_en_cours_au_prochain_enregistrement(): void
    {
        $dessin = Drawing::create([
            'status' => DrawingStatus::ToDescribe,
            'artist' => 'Anonyme',
            'description' => '<p>Paysage à la sanguine.</p>',
        ]);

        $this->assertSame(DrawingStatus::ToDescribe, $this->enregistrer($dessin, ['period' => 'XVIIIᵉ siècle']));

        // Compléter la description, en revanche, c'est reprendre le travail.
        $statut = $this->enregistrer($dessin, ['description' => '<p>Paysage à la sanguine, rehauts de craie.</p>']);
        $this->assertSame(DrawingStatus::InProgress, $statut);
    }

    private function changerParLots(array $dessins, DrawingStatus $statut): void
    {
        Livewire::test(ListDrawings::class)
            ->set('activeTab', 'tous')
            ->callTableBulkAction('avancement', $dessins, data: ['status' => $statut->value])
            ->assertHasNoFormErrors()
            ->assertNotified();
    }

    public function test_plusieurs_fiches_passent_a_decrit_d_un_coup_et_chacune_est_horodatee(): void
    {
        $premier = Drawing::create(['status' => DrawingStatus::ToDescribe]);
        $second = Drawing::create(['status' => DrawingStatus::InProgress]);
        $laissee = Drawing::create(['status' => DrawingStatus::ToDescribe]);

        $this->changerParLots([$premier, $second], DrawingStatus::Described);

        foreach ([$premier, $second] as $dessin) {
            $dessin->refresh();
            $this->assertSame(DrawingStatus::Described, $dessin->status);
            $this->assertNotNull($dessin->described_at);
        }

        $this->assertSame(DrawingStatus::ToDescribe, $laissee->refresh()->status);
    }

    public function test_une_fiche_deja_decrite_garde_sa_date(): void
    {
        $ancienne = Drawing::create(['status' => DrawingStatus::ToDescribe]);
        $ancienne->update(['status' => DrawingStatus::Described]);
        $ancienne->forceFill(['described_at' => now()->subMonth()])->saveQuietly();
        $date = $ancienne->refresh()->described_at;

        $this->changerParLots([$ancienne, Drawing::create([])], DrawingStatus::Described);

        $this->assertEquals($date, $ancienne->refresh()->described_at);
    }

    public function test_remettre_des_fiches_a_decrire_efface_leur_date(): void
    {
        $dessin = Drawing::create(['status' => DrawingStatus::ToDescribe]);
        $dessin->update(['status' => DrawingStatus::Described]);

        $this->changerParLots([$dessin], DrawingStatus::ToDescribe);

        $dessin->refresh();
        $this->assertSame(DrawingStatus::ToDescribe, $dessin->status);
        $this->assertNull($dessin->described_at);
    }
}
