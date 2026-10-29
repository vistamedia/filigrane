<?php

namespace Tests\Feature;

use App\Enums\ReshootReason;
use App\Models\Drawing;
use App\Models\Photo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClicheARefaireTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
    }

    /** Une fiche et son cliché, sans fichier : seul l'état compte ici. */
    private function ficheAvecCliche(): Photo
    {
        return Drawing::create([])->photos()->create(['original_path' => 'photos/originaux/essai.jpg']);
    }

    public function test_un_cliche_signale_fait_entrer_sa_fiche_dans_la_liste_de_travail(): void
    {
        $rate = $this->ficheAvecCliche();
        $this->ficheAvecCliche();

        $this->assertSame(0, Drawing::query()->needsReshoot()->count());

        $rate->forceFill(['reshoot_reason' => ReshootReason::Reflections])->save();

        $this->assertSame([$rate->drawing_id], Drawing::query()->needsReshoot()->pluck('id')->all());
    }

    public function test_le_signalement_dit_pourquoi_et_precise_au_besoin(): void
    {
        $photo = $this->ficheAvecCliche();
        $this->assertNull($photo->reshootLabel());

        $photo->forceFill(['reshoot_reason' => ReshootReason::Reflections])->save();
        $this->assertSame('Reflets dans la vitre', $photo->refresh()->reshootLabel());

        $photo->forceFill(['reshoot_note' => 'fenêtre en haut à gauche'])->save();
        $this->assertSame('Reflets dans la vitre — fenêtre en haut à gauche', $photo->refresh()->reshootLabel());
    }

    public function test_supprimer_le_cliche_rate_fait_sortir_la_fiche_de_la_liste(): void
    {
        $rate = $this->ficheAvecCliche();
        $rate->forceFill(['reshoot_reason' => ReshootReason::Blur])->save();

        // Le nouveau cliché arrive, l'ancien s'en va.
        $rate->drawing->photos()->create(['original_path' => 'photos/originaux/nouveau.jpg']);
        $rate->delete();

        $this->assertSame(0, Drawing::query()->needsReshoot()->count());
    }

    public function test_une_fiche_mise_a_la_corbeille_quitte_la_liste(): void
    {
        $rate = $this->ficheAvecCliche();
        $rate->forceFill(['reshoot_reason' => ReshootReason::Dark])->save();

        $rate->drawing->delete();

        $this->assertSame(0, Drawing::query()->needsReshoot()->count());
    }
}
