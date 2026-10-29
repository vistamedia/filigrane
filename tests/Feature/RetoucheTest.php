<?php

namespace Tests\Feature;

use App\Models\Calibration;
use App\Models\Drawing;
use App\Models\Photo;
use App\Services\PhotoImporter;
use App\Services\RetouchePhoto;
use App\Services\SeriesScaler;
use App\Support\Perspective;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Imagick;
use InvalidArgumentException;
use Tests\TestCase;

class RetoucheTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Un cadre de 300 × 400 mm photographié d'en bas et de côté, dont on
     * connaît les coins exacts (voir scripts/generer-images-test.php).
     *
     * @var array{coins: array<int, array{0: float, 1: float}>, proportions: float, focale: int}
     */
    private array $cadre;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');

        $this->cadre = json_decode(file_get_contents(base_path('tests/fixtures/cadre-en-perspective.json')), true);
    }

    private function importer(string $fixture = 'cadre-en-perspective.jpg'): Photo
    {
        return app(PhotoImporter::class)->import(Drawing::create([]), base_path('tests/fixtures/'.$fixture), $fixture);
    }

    /**
     * @return array{coins: array, quart: int, focale: float, luminosite: float, contraste: float}
     */
    private function reglages(array $autres = []): array
    {
        return [
            'coins' => $this->cadre['coins'],
            'quart' => 0,
            'focale' => 26.0,
            'luminosite' => 1.0,
            'contraste' => 1.0,
            ...$autres,
        ];
    }

    private function versionWeb(Photo $photo): Imagick
    {
        $image = new Imagick();
        $image->readImageBlob(Storage::disk('public')->get($photo->web_path));

        return $image;
    }

    /**
     * Composante bleue moyenne autour d'un point de l'image, de 0 à 255.
     * C'est elle qui distingue le mieux la baguette dorée (60), le mur (142),
     * le dessin (192) et le passe-partout (224).
     */
    private function bleuEn(Imagick $image, float $fx, float $fy): int
    {
        $zone = clone $image;
        $zone->cropImage(15, 15, (int) ($fx * $image->getImageWidth()) - 7, (int) ($fy * $image->getImageHeight()) - 7);
        $zone->scaleImage(1, 1);

        return $zone->getImagePixelColor(0, 0)->getColor()['b'];
    }

    /** Luminosité moyenne de l'image, de 0 à 1 : l'image réduite à un pixel. */
    private function luminositeMoyenne(Imagick $image): float
    {
        $copie = clone $image;
        $copie->scaleImage(1, 1);
        $couleur = $copie->getImagePixelColor(0, 0)->getColor();

        return ($couleur['r'] + $couleur['g'] + $couleur['b']) / 3 / 255;
    }

    public function test_les_vraies_proportions_d_un_cadre_photographie_de_biais_sont_retrouvees(): void
    {
        $coins = array_map(fn (array $c): array => [$c[0] * 3024, $c[1] * 4032], $this->cadre['coins']);

        $this->assertEqualsWithDelta(0.75, Perspective::proportions($coins, 3024, 4032, 26), 0.003);

        // Sans correction, le trapèze trompe de plusieurs pour cent : c'est
        // toute la raison d'être du calcul.
        $this->assertGreaterThan(0.03, abs(Perspective::proportionsApparentes($coins) - 0.75));
    }

    public function test_une_prise_de_vue_de_face_garde_les_proportions_de_l_image(): void
    {
        $coins = [[300, 400], [1500, 400], [1500, 2000], [300, 2000]];

        $this->assertEqualsWithDelta(0.75, Perspective::proportions($coins, 3024, 4032), 0.0001);
    }

    public function test_des_coins_croises_sont_refuses(): void
    {
        $this->expectException(InvalidArgumentException::class);

        // Haut-droite et bas-droite intervertis : l'image sortirait en miroir.
        Perspective::verifier([[0, 0], [100, 100], [100, 0], [0, 100]]);
    }

    public function test_la_retouche_redresse_le_cadre_sans_toucher_a_l_original(): void
    {
        $photo = $this->importer();
        $original = Storage::disk('local')->get($photo->original_path);
        [$webAvant, $vignetteAvant] = [$photo->web_path, $photo->thumb_path];

        app(RetouchePhoto::class)->appliquer($photo, $this->reglages());
        $photo->refresh();

        // L'original n'a pas bougé d'un octet.
        $this->assertSame($original, Storage::disk('local')->get($photo->original_path));

        // La version web d'origine est mise de côté, la miniature remplacée.
        $this->assertSame($webAvant, $photo->raw_web_path);
        Storage::disk('public')->assertExists($webAvant);
        Storage::disk('public')->assertMissing($vignetteAvant);

        // Les nouveaux dérivés portent un nom neuf, que le cache du
        // navigateur ne connaît pas encore.
        $this->assertNotSame($webAvant, $photo->web_path);
        $this->assertNotSame($vignetteAvant, $photo->thumb_path);
        Storage::disk('public')->assertExists($photo->web_path);
        Storage::disk('public')->assertExists($photo->thumb_path);

        // Le cadre redressé a retrouvé ses proportions de 300 × 400 mm.
        $web = $this->versionWeb($photo);
        $this->assertEqualsWithDelta(0.75, $web->getImageWidth() / $web->getImageHeight(), 0.005);

        $taille = $photo->workingSize();
        $this->assertEqualsWithDelta(0.75, $taille['width'] / $taille['height'], 0.005);
    }

    public function test_le_cadre_redresse_occupe_toute_l_image_sans_deborder_sur_le_mur(): void
    {
        $photo = $this->importer();

        app(RetouchePhoto::class)->appliquer($photo, $this->reglages());
        $web = $this->versionWeb($photo->refresh());

        // Les quatre coins de l'image tombent sur la baguette dorée, et non
        // sur le mur : le recadrage suit exactement le cadre.
        foreach ([[0.02, 0.02], [0.98, 0.02], [0.98, 0.98], [0.02, 0.98]] as [$x, $y]) {
            $this->assertEqualsWithDelta(60, $this->bleuEn($web, $x, $y), 25, "Coin ({$x}, {$y})");
        }

        // La baguette mesure 25 mm sur 300 : le passe-partout commence à
        // 8,3 % de la largeur, le dessin à 23 %. Une perspective mal corrigée
        // décalerait ces limites.
        $this->assertEqualsWithDelta(60, $this->bleuEn($web, 0.04, 0.5), 25, 'Baguette à gauche');
        $this->assertEqualsWithDelta(224, $this->bleuEn($web, 0.15, 0.5), 20, 'Passe-partout');
        $this->assertEqualsWithDelta(192, $this->bleuEn($web, 0.30, 0.45), 20, 'Dessin');
        $this->assertEqualsWithDelta(224, $this->bleuEn($web, 0.85, 0.5), 20, 'Passe-partout à droite');
        $this->assertEqualsWithDelta(60, $this->bleuEn($web, 0.96, 0.5), 25, 'Baguette à droite');
    }

    public function test_un_quart_de_tour_echange_largeur_et_hauteur(): void
    {
        $photo = $this->importer();

        app(RetouchePhoto::class)->appliquer($photo, $this->reglages(['quart' => 1]));
        $photo->refresh();

        $web = $this->versionWeb($photo);
        $this->assertEqualsWithDelta(4 / 3, $web->getImageWidth() / $web->getImageHeight(), 0.01);

        $taille = $photo->workingSize();
        $this->assertGreaterThan($taille['height'], $taille['width']);
    }

    public function test_eclaircir_seulement_conserve_la_mesure_de_la_photo(): void
    {
        $photo = $this->importer('dessin-recto.jpg');
        $photo->forceFill(['px_per_mm' => 3.0, 'sheet_box' => ['x' => 0.35, 'y' => 0.23, 'w' => 0.3, 'h' => 0.53]])->save();
        $avant = $this->luminositeMoyenne($this->versionWeb($photo));

        app(RetouchePhoto::class)->appliquer($photo, $this->reglages([
            'coins' => RetouchePhoto::COINS_PLEINS,
            'luminosite' => 1.3,
        ]));
        $photo->refresh();

        $this->assertGreaterThan($avant + 0.05, $this->luminositeMoyenne($this->versionWeb($photo)));

        // La géométrie n'a pas changé : la mesure reste valable.
        $this->assertSame(3.0, $photo->px_per_mm);
        $this->assertNotNull($photo->sheet_box);
        $this->assertSame(['width' => 3000, 'height' => 2250], $photo->workingSize());
    }

    public function test_redresser_efface_la_mesure_de_la_photo_mais_pas_les_dimensions_de_la_fiche(): void
    {
        $photo = $this->importer();
        $photo->forceFill(['px_per_mm' => 3.0, 'sheet_box' => ['x' => 0.3, 'y' => 0.3, 'w' => 0.4, 'h' => 0.4]])->save();
        $photo->drawing->forceFill(['width_mm' => 300, 'height_mm' => 400])->saveQuietly();

        app(RetouchePhoto::class)->appliquer($photo, $this->reglages());
        $photo->refresh();

        $this->assertNull($photo->px_per_mm);
        $this->assertNull($photo->sheet_box);
        $this->assertSame(300, $photo->drawing->refresh()->width_mm);
    }

    public function test_une_fois_redresse_le_cadre_se_mesure_d_une_seule_echelle(): void
    {
        $photo = $this->importer();
        app(RetouchePhoto::class)->appliquer($photo, $this->reglages());
        $photo->refresh();

        // Sur la photographie brute, l'échelle varie d'un bout à l'autre du
        // cadre. Redressé, une seule échelle vaut partout : une référence de
        // 300 mm posée sur la largeur donne aussi la hauteur.
        $taille = $photo->workingSize();
        $photo->forceFill([
            'px_per_mm' => $taille['width'] / 300,
            'sheet_box' => ['x' => 0, 'y' => 0, 'w' => 1, 'h' => 1],
        ])->save();

        $mesure = $photo->measuredSheetSize();

        $this->assertSame(300, $mesure['width_mm']);
        $this->assertEqualsWithDelta(400, $mesure['height_mm'], 3);
    }

    public function test_annuler_revient_a_la_photographie_telle_qu_elle_a_ete_prise(): void
    {
        $photo = $this->importer();
        $service = app(RetouchePhoto::class);

        $service->appliquer($photo, $this->reglages());
        $photo->refresh();
        $perimes = [$photo->web_path, $photo->raw_web_path, $photo->thumb_path];

        $service->annuler($photo);
        $photo->refresh();

        $this->assertFalse($photo->isRetouched());
        $this->assertNull($photo->raw_web_path);
        $this->assertSame(['width' => 3024, 'height' => 4032], $photo->workingSize());

        foreach ($perimes as $chemin) {
            Storage::disk('public')->assertMissing($chemin);
        }

        $web = $this->versionWeb($photo);
        $this->assertSame(2400, $web->getImageWidth());
        $this->assertSame(3200, $web->getImageHeight());
    }

    public function test_des_reglages_neutres_ne_sont_pas_une_retouche(): void
    {
        $photo = $this->importer();
        $service = app(RetouchePhoto::class);

        $service->appliquer($photo, $this->reglages());
        $service->appliquer($photo->refresh(), $this->reglages(['coins' => RetouchePhoto::COINS_PLEINS]));

        $this->assertFalse($photo->refresh()->isRetouched());
    }

    public function test_supprimer_une_photographie_retouchee_efface_aussi_la_version_mise_de_cote(): void
    {
        $photo = $this->importer();
        app(RetouchePhoto::class)->appliquer($photo, $this->reglages());
        $photo->refresh();

        $chemins = [$photo->web_path, $photo->raw_web_path, $photo->thumb_path];

        $photo->delete();

        foreach ($chemins as $chemin) {
            Storage::disk('public')->assertMissing($chemin);
        }
    }

    public function test_un_profil_d_echelle_ne_s_applique_pas_a_une_photographie_redressee(): void
    {
        $photo = $this->importer();
        app(RetouchePhoto::class)->appliquer($photo, $this->reglages());

        // Le profil a été établi sur des photographies brutes de même
        // définition ; l'image redressée n'a plus la même échelle.
        $profil = Calibration::create([
            'label' => 'Séance de test',
            'is_active' => true,
            'image_width_px' => 3024,
            'image_height_px' => 4032,
            'px_per_mm' => 4.0,
        ]);

        $this->assertSame(0, app(SeriesScaler::class)->candidates($profil)->count());
    }
}
