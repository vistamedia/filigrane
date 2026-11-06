<?php

namespace Tests\Feature;

use App\Enums\DrawingStatus;
use App\Enums\UserRole;
use App\Filament\Resources\Drawings\DrawingResource;
use App\Filament\Resources\Drawings\Pages\EditDrawing;
use App\Models\Drawing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Quitter une fiche : revenir à la liste telle qu'on l'avait laissée, ou
 * passer à la suivante sans déclarer décrite, par mégarde, une fiche vide.
 */
class NavigationFicheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['role' => UserRole::Curator]));
    }

    private function fiche(Drawing $dessin, ?string $depuis = null)
    {
        return Livewire::withHeaders($depuis ? ['referer' => $depuis] : [])
            ->test(EditDrawing::class, ['record' => $dessin->getRouteKey()]);
    }

    public function test_le_retour_ramene_a_la_liste_telle_qu_on_l_a_quittee_et_enregistre_la_fiche(): void
    {
        $dessin = Drawing::create([]);
        $liste = DrawingResource::getUrl('index').'?tab=tous&page=2';

        $this->fiche($dessin, $liste)
            ->fillForm(['artist' => 'Anonyme, école française'])
            ->callAction('liste')
            ->assertRedirect($liste);

        // La saisie n'est pas perdue : Safari sur iPad ne prévient pas.
        $this->assertSame('Anonyme, école française', $dessin->refresh()->artist);
    }

    public function test_la_liste_retenue_survit_aux_fiches_enchainees(): void
    {
        $premier = Drawing::create([]);
        $second = Drawing::create([]);
        $liste = DrawingResource::getUrl('index').'?tab=a-decrire';

        $this->fiche($premier, $liste);

        // On arrive sur la seconde depuis la première, pas depuis la liste.
        $this->fiche($second, DrawingResource::getUrl('edit', ['record' => $premier]))
            ->callAction('liste')
            ->assertRedirect($liste);
    }

    public function test_sans_liste_retenue_le_retour_mene_a_la_liste_par_defaut(): void
    {
        $this->fiche(Drawing::create([]), 'https://ailleurs.example/dessins?tab=tous')
            ->callAction('liste')
            ->assertRedirect(DrawingResource::getUrl('index'));
    }

    public function test_declarer_decrite_une_fiche_vide_demande_confirmation(): void
    {
        $dessin = Drawing::create([]);

        $page = $this->fiche($dessin)
            ->mountAction('terminer')
            ->assertActionMounted('terminer');

        $this->assertSame(DrawingStatus::ToDescribe, $dessin->refresh()->status);

        $page->callMountedAction();

        $this->assertSame(DrawingStatus::Described, $dessin->refresh()->status);
    }

    public function test_une_fiche_ou_quelque_chose_est_ecrit_passe_decrite_sans_question(): void
    {
        foreach ([['artist' => 'Hubert Robert'], ['description' => '<p>Ruines animées.</p>']] as $champs) {
            $dessin = Drawing::create([]);

            // Sans confirmation demandée, ouvrir l'action suffit à l'exécuter.
            $this->fiche($dessin)
                ->fillForm($champs)
                ->mountAction('terminer');

            $this->assertSame(DrawingStatus::Described, $dessin->refresh()->status);
        }
    }
}
