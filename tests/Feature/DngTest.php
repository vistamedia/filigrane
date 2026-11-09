<?php

namespace Tests\Feature;

use App\Models\Drawing;
use App\Services\DeveloppementRaw;
use App\Services\PhotoImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Imagick;
use Tests\TestCase;

/**
 * Un DNG (ProRAW d'iPhone) est développé en JPEG pleine définition à
 * l'import, et c'est ce JPEG qui devient l'original (D-43).
 *
 * Le fichier de test est un petit DNG fabriqué par
 * scripts/generer-dng-test.php : un carré sombre en haut à gauche d'un fond
 * clair, enregistré tête en bas avec l'orientation 3, une focale de 24 mm.
 */
class DngTest extends TestCase
{
    use RefreshDatabase;

    private const DNG = 'tests/fixtures/prise-raw.dng';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
    }

    public function test_un_dng_devient_un_original_jpeg_a_l_endroit_qui_garde_ses_metadonnees(): void
    {
        $photo = app(PhotoImporter::class)->import(Drawing::create([]), base_path(self::DNG), 'IMG_2456.DNG');

        $this->assertNull($photo->processing_error);
        $this->assertSame('image/jpeg', $photo->mime);
        $this->assertStringEndsWith('.jpg', $photo->original_path);
        $this->assertSame('IMG_2456.DNG', $photo->original_name);

        // C'est bien un JPEG qui est rangé, pleine définition.
        $original = Storage::disk('local')->get($photo->original_path);
        $this->assertSame("\xFF\xD8", substr($original, 0, 2));
        $this->assertSame([240, 180], [$photo->width_px, $photo->height_px]);
        $this->assertSame(strlen($original), $photo->size_bytes);

        // Le JPEG développé n'a plus d'EXIF : celles du DNG ont été gardées.
        $this->assertSame(24, $photo->exif['focal_35mm']);
        $this->assertSame('2026-11-08 15:56:06', $photo->taken_at->format('Y-m-d H:i:s'));
        $this->assertArrayNotHasKey('orientation', $photo->exif);

        // Remis à l'endroit : le carré sombre est en haut à gauche, sur
        // l'original comme sur la version web.
        foreach ([$original, Storage::disk('public')->get($photo->web_path)] as $octets) {
            $image = new Imagick();
            $image->readImageBlob($octets);
            [$l, $h] = [$image->getImageWidth(), $image->getImageHeight()];

            $this->assertLessThan(0.5, $this->luminance($image, 0.25 * $l, 0.28 * $h));
            $this->assertGreaterThan(0.5, $this->luminance($image, 0.75 * $l, 0.72 * $h));
        }
    }

    public function test_un_dng_se_reconnait_meme_sans_son_extension(): void
    {
        $chemin = base_path(self::DNG);

        // libmagic y voit un TIFF, qu'il est par sa structure.
        $this->assertTrue(DeveloppementRaw::estUnDng($chemin, 'sans-extension', 'image/tiff'));
        $this->assertTrue(DeveloppementRaw::estUnDng($chemin, null, 'image/x-adobe-dng'));

        // Un vrai TIFF n'en est pas un.
        $tiff = tempnam(sys_get_temp_dir(), 'tiff');
        $image = new Imagick();
        $image->newImage(20, 20, 'white');
        $image->writeImage('tiff:'.$tiff);

        $this->assertFalse(DeveloppementRaw::estUnDng($tiff, 'scan.tif', 'image/tiff'));
        unlink($tiff);
    }

    public function test_un_jpeg_renomme_en_dng_reste_un_jpeg(): void
    {
        $photo = app(PhotoImporter::class)->import(Drawing::create([]), base_path('tests/fixtures/dessin-recto.jpg'), 'IMG_0001.DNG');

        $this->assertNull($photo->processing_error);
        $this->assertSame('image/jpeg', $photo->mime);
        $this->assertSame([3000, 2250], [$photo->width_px, $photo->height_px]);
    }

    private function luminance(Imagick $image, float $x, float $y): float
    {
        return $image->getImagePixelColor((int) $x, (int) $y)->getColorValue(Imagick::COLOR_RED);
    }
}
