<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Pages\ImportEnMasse;
use App\Filament\Resources\Drawings\Pages\EditDrawing;
use App\Models\Drawing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Les messages que Laravel adresse à l'utilisatrice, en français.
 *
 * La langue de secours de l'application est le français : une clé absente de
 * lang/fr ne retombe pas sur l'anglais, elle s'affiche telle quelle
 * (« validation.unique »). C'était le cas de toutes les erreurs de validation
 * jusqu'au 9 novembre 2026.
 */
class TraductionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_chaque_message_du_framework_existe_en_francais(): void
    {
        $origine = base_path('vendor/laravel/framework/src/Illuminate/Translation/lang/en');

        foreach (['validation', 'auth', 'passwords', 'pagination'] as $groupe) {
            $anglais = $this->cles(require "{$origine}/{$groupe}.php");
            $francais = $this->cles(require lang_path("fr/{$groupe}.php"));

            // L'exemple de message personnalisé n'est qu'un modèle.
            $manquent = array_diff($anglais, $francais, ['custom.attribute-name.rule-name']);

            $this->assertSame([], array_values($manquent), "Clés absentes de lang/fr/{$groupe}.php");
        }
    }

    public function test_un_numero_d_inventaire_deja_pris_se_dit_en_francais(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Curator]));

        Drawing::create(['inventory_number' => 'FIL-0042']);
        $dessin = Drawing::create([]);

        Livewire::test(EditDrawing::class, ['record' => $dessin->getRouteKey()])
            ->fillForm(['inventory_number' => 'FIL-0042'])
            ->call('save')
            ->assertHasFormErrors(['inventory_number' => 'unique'])
            ->assertSee('est déjà utilisée')
            ->assertDontSee('validation.unique');
    }

    public function test_un_envoi_refuse_sans_motif_n_affiche_pas_de_message_generique(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Curator]));

        // Refus passager (hébergeur, réseau) : la page relance et nomme
        // elle-même les fichiers qui échouent encore.
        Livewire::test(ImportEnMasse::class)
            ->call('_uploadErrored', 'data.fichiers', null, true)
            ->assertHasNoErrors();

        // Refus motivé : le motif reste affiché.
        Livewire::test(ImportEnMasse::class)
            ->call('_uploadErrored', 'data.fichiers', json_encode(['errors' => ['files.0' => ['Le fichier est trop lourd.']]]), false)
            ->assertHasErrors('data.fichiers');
    }

    /**
     * @return list<string>
     */
    private function cles(array $traductions, string $prefixe = ''): array
    {
        $cles = [];

        foreach ($traductions as $cle => $valeur) {
            $cles = is_array($valeur) && $valeur !== []
                ? [...$cles, ...$this->cles($valeur, "{$prefixe}{$cle}.")]
                : [...$cles, "{$prefixe}{$cle}"];
        }

        return $cles;
    }
}
