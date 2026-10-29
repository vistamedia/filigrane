<?php

namespace Tests\Feature;

use App\Enums\DimensionsSource;
use App\Enums\PhotoKind;
use App\Models\Calibration;
use App\Models\Drawing;
use App\Services\PhotoImporter;
use App\Services\ScaleResolver;
use App\Services\SeriesScaler;
use App\Support\SheetDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class ImportPhotoTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Les images de test représentent une feuille de 300 × 400 mm posée sur
     * une table sombre, photographiée à 3 pixels par millimètre.
     */
    private const PX_PAR_MM = 3.0;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
    }

    private function fixture(string $nom): string
    {
        return base_path('tests/fixtures/'.$nom);
    }

    private function importer(Drawing $dessin, string $fixture): \App\Models\Photo
    {
        return app(PhotoImporter::class)->import($dessin, $this->fixture($fixture), $fixture);
    }

    public function test_une_photographie_produit_un_original_prive_et_deux_derives_publics(): void
    {
        $dessin = Drawing::create([]);

        $photo = $this->importer($dessin, 'dessin-recto.jpg');

        $this->assertSame('image/jpeg', $photo->mime);
        $this->assertSame(3000, $photo->width_px);
        $this->assertSame(2250, $photo->height_px);
        $this->assertNotNull($photo->processed_at);
        $this->assertNull($photo->processing_error);

        // L'original est conservé intact, hors du dossier servi par Apache.
        Storage::disk('local')->assertExists($photo->original_path);
        $this->assertSame(
            filesize($this->fixture('dessin-recto.jpg')),
            Storage::disk('local')->size($photo->original_path),
        );

        // Les dérivés, eux, sont publics et nettement plus légers.
        Storage::disk('public')->assertExists($photo->web_path);
        Storage::disk('public')->assertExists($photo->thumb_path);
        $this->assertLessThan(
            Storage::disk('local')->size($photo->original_path),
            Storage::disk('public')->size($photo->web_path),
        );
    }

    public function test_la_version_web_est_ramenee_a_la_largeur_configuree(): void
    {
        config(['filigrane.web_width' => 1200, 'filigrane.thumb_size' => 300]);

        $photo = $this->importer(Drawing::create([]), 'dessin-recto.jpg');

        $web = imagecreatefromstring(Storage::disk('public')->get($photo->web_path));
        $vignette = imagecreatefromstring(Storage::disk('public')->get($photo->thumb_path));

        $this->assertSame(1200, imagesx($web));
        $this->assertSame(300, imagesx($vignette));
        $this->assertSame(300, imagesy($vignette));

        // Et la définition enregistrée reste celle de l'original.
        $this->assertSame(3000, $photo->width_px);
    }

    public function test_la_premiere_photographie_devient_l_illustration_de_la_fiche(): void
    {
        $dessin = Drawing::create([]);

        $premiere = $this->importer($dessin, 'dessin-recto.jpg');
        $seconde = $this->importer($dessin, 'dessin-verso.jpg');

        $this->assertSame($premiere->id, $dessin->refresh()->cover_photo_id);
        $this->assertSame(PhotoKind::Recto, $premiere->kind);
        $this->assertSame(PhotoKind::Detail, $seconde->kind);
        $this->assertSame(0, $premiere->sort_order);
        $this->assertSame(1, $seconde->sort_order);
    }

    public function test_supprimer_l_illustration_en_designe_une_autre_et_efface_les_fichiers(): void
    {
        $dessin = Drawing::create([]);
        $premiere = $this->importer($dessin, 'dessin-recto.jpg');
        $seconde = $this->importer($dessin, 'dessin-verso.jpg');

        $chemins = [$premiere->original_path, $premiere->web_path, $premiere->thumb_path];

        $premiere->delete();

        $this->assertSame($seconde->id, $dessin->refresh()->cover_photo_id);
        Storage::disk('local')->assertMissing($chemins[0]);
        Storage::disk('public')->assertMissing($chemins[1]);
        Storage::disk('public')->assertMissing($chemins[2]);
    }

    public function test_un_fichier_qui_n_est_pas_une_image_est_refuse(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Format d\'image non pris en charge');

        $this->importer(Drawing::create([]), 'pas-une-image.txt');
    }

    public function test_le_cadre_de_la_feuille_est_detecte_sur_fond_uni(): void
    {
        $cadre = SheetDetector::detect($this->fixture('dessin-recto.jpg'));

        $this->assertNotNull($cadre);

        // 300 × 400 mm attendus, à 1 % près : le détourage travaille sur une
        // version réduite de l'image.
        $largeurMm = $cadre['w'] * 3000 / self::PX_PAR_MM;
        $hauteurMm = $cadre['h'] * 2250 / self::PX_PAR_MM;

        $this->assertEqualsWithDelta(300, $largeurMm, 3);
        $this->assertEqualsWithDelta(400, $hauteurMm, 4);
    }

    public function test_une_echelle_connue_renseigne_les_dimensions_de_la_fiche(): void
    {
        $dessin = Drawing::create([]);
        $photo = $this->importer($dessin, 'dessin-recto.jpg');

        $photo->forceFill([
            'px_per_mm' => self::PX_PAR_MM,
            'sheet_box' => SheetDetector::detect($this->fixture('dessin-recto.jpg')),
        ])->save();

        $this->assertTrue(app(ScaleResolver::class)->pushToDrawing($photo));

        $dessin->refresh();

        $this->assertEqualsWithDelta(300, $dessin->width_mm, 3);
        $this->assertEqualsWithDelta(400, $dessin->height_mm, 4);
        $this->assertSame(DimensionsSource::Estimated, $dessin->dimensions_source);
    }

    public function test_une_mesure_faite_a_la_main_n_est_jamais_ecrasee(): void
    {
        $dessin = Drawing::create([
            'width_mm' => 311,
            'height_mm' => 404,
            'dimensions_source' => DimensionsSource::Measured,
        ]);

        $photo = $this->importer($dessin, 'dessin-recto.jpg');
        $photo->forceFill([
            'px_per_mm' => self::PX_PAR_MM,
            'sheet_box' => SheetDetector::detect($this->fixture('dessin-recto.jpg')),
        ])->save();

        $this->assertFalse(app(ScaleResolver::class)->pushToDrawing($photo));

        $dessin->refresh();

        $this->assertSame(311, $dessin->width_mm);
        $this->assertSame(DimensionsSource::Measured, $dessin->dimensions_source);
    }

    public function test_un_profil_d_echelle_mesure_les_autres_photos_de_la_meme_serie(): void
    {
        $serie = [];

        foreach (['dessin-recto.jpg', 'dessin-verso.jpg'] as $fixture) {
            $serie[] = $this->importer(Drawing::create([]), $fixture);
        }

        $profil = Calibration::create([
            'label' => 'Séance de test',
            'is_active' => true,
            'camera' => null,
            'focal_35mm' => null,
            'image_width_px' => 3000,
            'image_height_px' => 2250,
            'px_per_mm' => self::PX_PAR_MM,
        ]);

        $bilan = app(SeriesScaler::class)->apply($profil);

        $this->assertSame(2, $bilan['traitees']);
        $this->assertSame(0, $bilan['restantes']);
        $this->assertSame(0, $bilan['echecs']);

        foreach ($serie as $photo) {
            $dessin = $photo->drawing->refresh();

            $this->assertEqualsWithDelta(300, $dessin->width_mm, 3);
            $this->assertEqualsWithDelta(400, $dessin->height_mm, 4);
        }
    }

    public function test_un_profil_ne_touche_pas_aux_photos_d_un_autre_cadrage(): void
    {
        $photo = $this->importer(Drawing::create([]), 'dessin-recto.jpg');

        $profil = Calibration::create([
            'label' => 'Autre cadrage',
            'is_active' => true,
            // Définition différente : la distance de prise de vue a pu changer,
            // l'échelle ne serait plus valable.
            'image_width_px' => 4032,
            'image_height_px' => 3024,
            'px_per_mm' => 7.5,
        ]);

        $bilan = app(SeriesScaler::class)->apply($profil);

        $this->assertSame(0, $bilan['traitees']);
        $this->assertNull($photo->refresh()->px_per_mm);
        $this->assertNull($photo->drawing->refresh()->width_mm);
    }

    public function test_une_photographie_d_iphone_au_format_heic_est_convertie(): void
    {
        $heic = $this->fixture('dessin-iphone.heic');

        if (! file_exists($heic)) {
            $this->markTestSkipped(
                'Déposez une photo d\'iPhone dans tests/fixtures/dessin-iphone.heic '
                .'pour éprouver la conversion HEIC (ImageMagick lit ce format mais ne sait pas l\'écrire).',
            );
        }

        $photo = app(PhotoImporter::class)->import(Drawing::create([]), $heic, 'dessin-iphone.heic');

        $this->assertContains($photo->mime, ['image/heic', 'image/heif']);
        $this->assertNull($photo->processing_error);

        // L'original reste en HEIC, les dérivés sont des JPEG lisibles partout.
        $this->assertStringEndsWith('.heic', $photo->original_path);
        $this->assertStringEndsWith('.jpg', $photo->web_path);
        $this->assertNotFalse(imagecreatefromstring(Storage::disk('public')->get($photo->web_path)));
    }
}
