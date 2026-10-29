<?php

namespace App\Jobs;

use App\Models\Photo;
use App\Services\DerivativeMaker;
use App\Services\ScaleResolver;
use App\Support\ExifReader;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Prépare une photo après son dépôt : métadonnées, dérivés JPEG, échelle.
 *
 * Exécuté de façon synchrone par défaut (chaque fichier arrive dans sa propre
 * requête, donc rien ne bloque), ou en file d'attente si la configuration le
 * demande.
 */
class ProcessPhoto implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public Photo $photo) {}

    public function handle(DerivativeMaker $derivatives, ScaleResolver $scale): void
    {
        $photo = $this->photo->fresh();

        if (! $photo) {
            return;
        }

        $originals = Storage::disk(config('filigrane.originals_disk'));
        $absolutePath = $originals->path($photo->original_path);

        try {
            $made = $derivatives->make($absolutePath);

            $base = pathinfo($photo->original_path, PATHINFO_FILENAME);
            $folder = now()->format('Y/m');

            $webPath = trim(config('filigrane.web_path'), '/')."/{$folder}/{$base}.jpg";
            $thumbPath = trim(config('filigrane.thumb_path'), '/')."/{$folder}/{$base}.jpg";

            $public = Storage::disk(config('filigrane.derivatives_disk'));
            $public->put($webPath, $made['web']);
            $public->put($thumbPath, $made['thumb']);

            $exif = ExifReader::read($absolutePath);

            $photo->forceFill([
                'width_px' => $made['width'],
                'height_px' => $made['height'],
                'web_path' => $webPath,
                'thumb_path' => $thumbPath,
                'exif' => $exif,
                'taken_at' => $exif['taken_at'] ?? null,
                'processed_at' => now(),
                'processing_error' => null,
            ])->save();

            $scale->resolve($photo, $absolutePath);

            $this->ensureCover($photo);
        } catch (Throwable $exception) {
            $photo->forceFill([
                'processing_error' => mb_substr($exception->getMessage(), 0, 1000),
            ])->save();

            throw $exception;
        }
    }

    /**
     * La première photo exploitable d'une fiche en devient l'illustration.
     */
    private function ensureCover(Photo $photo): void
    {
        $drawing = $photo->drawing;

        if ($drawing && ! $drawing->cover_photo_id) {
            $drawing->forceFill(['cover_photo_id' => $photo->id])->saveQuietly();
        }
    }
}
