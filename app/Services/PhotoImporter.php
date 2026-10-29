<?php

namespace App\Services;

use App\Enums\PhotoKind;
use App\Jobs\ProcessPhoto;
use App\Models\Drawing;
use App\Models\Photo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Point d'entrée unique pour faire entrer une image dans le catalogue, qu'elle
 * vienne d'un fichier déposé, de l'import en masse ou du studio photo.
 */
class PhotoImporter
{
    /**
     * @param  array{kind?: PhotoKind|string, caption?: string|null}  $attributes
     */
    public function import(
        Drawing $drawing,
        string $sourcePath,
        ?string $originalName = null,
        array $attributes = [],
    ): Photo {
        if (! is_readable($sourcePath)) {
            throw new RuntimeException("Fichier illisible : {$sourcePath}");
        }

        $mime = $this->detectMime($sourcePath);

        if (! in_array($mime, config('filigrane.accepted_mimes'), true)) {
            throw new RuntimeException("Format d'image non pris en charge : {$mime}");
        }

        $originalPath = $this->storeOriginal($sourcePath, $mime);

        $photo = $drawing->photos()->create([
            'kind' => $attributes['kind'] ?? $this->defaultKind($drawing),
            'caption' => $attributes['caption'] ?? null,
            'original_path' => $originalPath,
            'original_name' => $originalName ? Str::limit(basename($originalName), 250, '') : null,
            'mime' => $mime,
            'size_bytes' => filesize($sourcePath) ?: null,
            'sort_order' => ($drawing->photos()->max('sort_order') ?? -1) + 1,
        ]);

        if (config('filigrane.image_processing') === 'queue') {
            ProcessPhoto::dispatch($photo);
        } else {
            ProcessPhoto::dispatchSync($photo);
        }

        return $photo->refresh();
    }

    /**
     * L'original est recopié octet pour octet : c'est la pièce d'archive.
     */
    private function storeOriginal(string $sourcePath, string $mime): string
    {
        $extension = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/heic' => 'heic',
            'image/heif' => 'heif',
            'image/tiff' => 'tif',
            'image/avif' => 'avif',
            default => 'bin',
        };

        $path = sprintf(
            '%s/%s/%s.%s',
            trim(config('filigrane.originals_path'), '/'),
            now()->format('Y/m'),
            Str::lower((string) Str::ulid()),
            $extension,
        );

        $stream = fopen($sourcePath, 'rb');

        try {
            Storage::disk(config('filigrane.originals_disk'))->put($path, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        return $path;
    }

    /**
     * Première photo d'une fiche : c'est le recto. Les suivantes sont des
     * détails, à requalifier si besoin.
     */
    private function defaultKind(Drawing $drawing): PhotoKind
    {
        return $drawing->photos()->exists() ? PhotoKind::Detail : PhotoKind::Recto;
    }

    private function detectMime(string $path): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        try {
            $mime = finfo_file($finfo, $path) ?: 'application/octet-stream';
        } finally {
            finfo_close($finfo);
        }

        // Certains systèmes renvoient encore un type générique pour le HEIC.
        if (in_array($mime, ['application/octet-stream', 'image/x-heic'], true)
            && str_contains(strtolower(file_get_contents($path, false, null, 0, 32) ?: ''), 'ftypheic')) {
            return 'image/heic';
        }

        return $mime;
    }
}
