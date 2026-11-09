<?php

namespace Tests\Feature;

use App\Enums\DrawingStatus;
use App\Enums\Location;
use App\Enums\UserRole;
use App\Filament\Pages\SeparerPhoto;
use App\Filament\Resources\Drawings\DrawingResource;
use App\Models\Drawing;
use App\Models\Photo;
use App\Models\Tag;
use App\Models\User;
use App\Services\PhotoImporter;
use App\Services\SeparationPhoto;
use App\Support\DetecteurDeDessins;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Une photographie de plusieurs mini-dessins devient une fiche par dessin
 * (D-45). La planche de test (scripts/generer-planche-test.php) montre cinq
 * feuilles sur une table sombre, dont deux reliées par un reflet, et une
 * miette claire.
 */
class SeparationTest extends TestCase
{
    use RefreshDatabase;

    private const PLANCHE = 'tests/fixtures/planche-de-dessins.jpg';

    /** @var list<array{x: float, y: float, w: float, h: float}> */
    private array $feuilles;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');

        $this->actingAs(User::factory()->create(['role' => UserRole::Curator]));
        $this->feuilles = json_decode(file_get_contents(base_path('tests/fixtures/planche-de-dessins.json')), true);
    }

    private function planche(): Photo
    {
        $fiche = Drawing::create([
            'artist' => 'Anonyme, école française',
            'period' => 'XIXe siècle',
            'location' => Location::Box3,
        ]);
        $fiche->tags()->attach(Tag::create(['name' => 'Études'])->id);

        return app(PhotoImporter::class)->import($fiche, base_path(self::PLANCHE), 'planche-carton3.jpg');
    }

    public function test_chaque_feuille_est_reperee_dans_l_ordre_de_la_lecture(): void
    {
        $cadres = DetecteurDeDessins::detecter(base_path(self::PLANCHE));

        // Cinq feuilles : le reflet ne les soude pas, la miette n'en est pas une.
        $this->assertCount(5, $cadres);

        foreach ($this->feuilles as $rang => $feuille) {
            $cadre = $cadres[$rang];

            // Le cadre contient la feuille, avec une marge, sans déborder
            // franchement sur la table.
            $this->assertLessThanOrEqual($feuille['x'] + 0.005, $cadre['x']);
            $this->assertLessThanOrEqual($feuille['y'] + 0.005, $cadre['y']);
            $this->assertGreaterThanOrEqual($feuille['x'] + $feuille['w'] - 0.005, $cadre['x'] + $cadre['w']);
            $this->assertGreaterThanOrEqual($feuille['y'] + $feuille['h'] - 0.005, $cadre['y'] + $cadre['h']);
            $this->assertLessThan(1.4, ($cadre['w'] * $cadre['h']) / ($feuille['w'] * $feuille['h']));
        }
    }

    public function test_une_photographie_de_plusieurs_dessins_donne_une_fiche_par_dessin(): void
    {
        $photo = $this->planche();
        $source = $photo->drawing;
        $original = $photo->original_path;

        $decoupes = app(SeparationPhoto::class)->separer($photo, DetecteurDeDessins::detecter(base_path(self::PLANCHE)));

        $this->assertCount(5, $decoupes);
        $this->assertSame(5, Drawing::count());

        // La fiche d'origine garde le premier dessin, et son numéro ; les
        // suivants prennent les numéros d'après, dans l'ordre de la lecture.
        $fiches = collect($decoupes)->map->drawing;
        $this->assertTrue($fiches->first()->is($source));
        $this->assertSame(['LBS-0001', 'LBS-0002', 'LBS-0003', 'LBS-0004', 'LBS-0005'], $fiches->pluck('inventory_number')->all());
        $this->assertSame($decoupes[0]->id, $source->refresh()->cover_photo_id);

        // Ce que l'import avait donné au lot vaut pour chaque dessin.
        foreach ($fiches->slice(1) as $fiche) {
            $this->assertSame('Anonyme, école française', $fiche->artist);
            $this->assertSame('XIXe siècle', $fiche->period);
            $this->assertSame(Location::Box3, $fiche->location);
            $this->assertSame(['Études'], $fiche->tags->pluck('name')->all());
            $this->assertSame(DrawingStatus::ToDescribe, $fiche->status);
            $this->assertNotNull($fiche->cover_photo_id);
        }

        // La photographie d'ensemble a disparu ; chaque dessin a la sienne,
        // découpée à pleine définition, et sait où elle était.
        $this->assertNull($photo->fresh());
        Storage::disk('local')->assertMissing($original);

        foreach ($decoupes as $rang => $decoupe) {
            $decoupe->refresh();
            $feuille = $this->feuilles[$rang];

            Storage::disk('local')->assertExists($decoupe->original_path);
            $this->assertSame("\xFF\xD8", substr(Storage::disk('local')->get($decoupe->original_path), 0, 2));
            Storage::disk('public')->assertExists($decoupe->web_path);

            $this->assertSame([2400, 1800], [$decoupe->fullFrame()['largeur_px'], $decoupe->fullFrame()['hauteur_px']]);
            $this->assertEqualsWithDelta($feuille['x'] * 2400, $decoupe->original_trim['x'], 45);
            $this->assertEqualsWithDelta($feuille['w'] * 2400, $decoupe->width_px, 90);
            $this->assertSame('planche-carton3.jpg', $decoupe->original_name);
        }
    }

    public function test_il_faut_au_moins_deux_dessins(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(SeparationPhoto::class)->separer($this->planche(), [['x' => 0.1, 'y' => 0.1, 'w' => 0.3, 'h' => 0.3]]);
    }

    public function test_la_page_propose_les_cadres_puis_separe(): void
    {
        $photo = $this->planche();

        $page = Livewire::withQueryParams(['photo' => $photo->id])->test(SeparerPhoto::class);
        $cadres = $page->get('detectes');
        $this->assertCount(5, $cadres);

        $page->call('separer', array_slice($cadres, 0, 4))
            ->assertNotified('4 dessins, une fiche chacun')
            ->assertRedirect(DrawingResource::getUrl('index').'?tab=tous');

        $this->assertSame(4, Drawing::count());
    }
}
