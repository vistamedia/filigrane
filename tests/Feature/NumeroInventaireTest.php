<?php

namespace Tests\Feature;

use App\Models\Drawing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NumeroInventaireTest extends TestCase
{
    use RefreshDatabase;

    public function test_les_numeros_sont_attribues_dans_l_ordre(): void
    {
        $premier = Drawing::create([]);
        $deuxieme = Drawing::create([]);
        $troisieme = Drawing::create([]);

        $this->assertSame('FIL-0001', $premier->inventory_number);
        $this->assertSame('FIL-0002', $deuxieme->inventory_number);
        $this->assertSame('FIL-0003', $troisieme->inventory_number);
    }

    public function test_un_numero_mis_a_la_corbeille_n_est_pas_reattribue(): void
    {
        Drawing::create([]);
        $deuxieme = Drawing::create([]);

        $deuxieme->delete();

        // Le numéro FIL-0002 figure peut-être déjà sur une étiquette ou dans
        // des notes papier : il ne doit jamais désigner un autre dessin.
        $suivant = Drawing::create([]);

        $this->assertSame('FIL-0003', $suivant->inventory_number);
        $this->assertSame(1, Drawing::onlyTrashed()->count());
    }

    public function test_un_numero_fourni_est_respecte(): void
    {
        $dessin = Drawing::create(['inventory_number' => 'LOUVRE-RF-1234']);

        $this->assertSame('LOUVRE-RF-1234', $dessin->inventory_number);

        // Et la numérotation automatique reprend sa propre série.
        $this->assertSame('FIL-0001', Drawing::create([])->inventory_number);
    }

    public function test_le_titre_reste_lisible_quand_rien_n_est_renseigne(): void
    {
        $vide = Drawing::create([]);
        $renseigne = Drawing::create(['artist' => 'Anonyme', 'period' => 'XVIIIᵉ siècle']);

        $this->assertSame('FIL-0001', $vide->title());
        $this->assertSame('FIL-0002 — Anonyme, XVIIIᵉ siècle', $renseigne->title());
    }

    public function test_les_dimensions_s_affichent_en_centimetres(): void
    {
        $dessin = Drawing::create([
            'width_mm' => 300,
            'height_mm' => 405,
            'dimensions_source' => \App\Enums\DimensionsSource::Estimated,
        ]);

        $this->assertSame('40,5 × 30 cm (estimée)', $dessin->dimensionsLabel());
    }

    public function test_sans_dimensions_aucune_mention_n_est_fabriquee(): void
    {
        $this->assertNull(Drawing::create([])->dimensionsLabel());
    }
}
