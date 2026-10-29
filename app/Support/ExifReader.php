<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Imagick;
use Throwable;

/**
 * Lecture des métadonnées de prise de vue.
 *
 * exif_read_data() ne gère pas le HEIC : pour ces fichiers on interroge
 * ImageMagick, qui expose les mêmes étiquettes sous la forme « exif:Nom ».
 */
class ExifReader
{
    /**
     * @return array<string, mixed>
     */
    public static function read(string $path): array
    {
        $raw = self::rawTags($path);

        if ($raw === []) {
            return [];
        }

        $make = self::tag($raw, 'Make');
        $model = self::tag($raw, 'Model');

        return array_filter([
            'camera' => trim(implode(' ', array_filter([$make, $model]))) ?: null,
            'lens' => self::tag($raw, 'LensModel') ?: self::tag($raw, 'UndefinedTag:0xA434'),
            'focal_mm' => self::rational($raw, 'FocalLength'),
            'focal_35mm' => self::integer($raw, 'FocalLengthIn35mmFilm') ?? self::integer($raw, 'FocalLenIn35mmFilm'),
            'subject_distance_mm' => self::subjectDistanceMm($raw),
            'aperture' => self::rational($raw, 'FNumber'),
            'iso' => self::integer($raw, 'ISOSpeedRatings') ?? self::integer($raw, 'PhotographicSensitivity'),
            'orientation' => self::integer($raw, 'Orientation'),
            'taken_at' => self::takenAt($raw)?->toIso8601String(),
        ], fn ($value) => $value !== null && $value !== '');
    }

    public static function takenAt(array $raw): ?Carbon
    {
        foreach (['DateTimeOriginal', 'DateTimeDigitized', 'DateTime'] as $key) {
            $value = self::tag($raw, $key);

            if (! $value) {
                continue;
            }

            try {
                // Format EXIF : « 2026:10:28 14:32:05 »
                return Carbon::createFromFormat('Y:m:d H:i:s', trim($value));
            } catch (Throwable) {
                continue;
            }
        }

        return null;
    }

    /**
     * Distance de mise au point, en millimètres, quand l'appareil l'enregistre.
     * Les iPhone ne la fournissent pas systématiquement : l'absence est normale.
     */
    private static function subjectDistanceMm(array $raw): ?float
    {
        $metres = self::rational($raw, 'SubjectDistance');

        if ($metres === null || $metres <= 0 || $metres > 100) {
            return null;
        }

        return round($metres * 1000, 1);
    }

    /**
     * @return array<string, string>
     */
    private static function rawTags(string $path): array
    {
        $tags = [];

        if (function_exists('exif_read_data')) {
            try {
                $data = @exif_read_data($path, null, true);

                if (is_array($data)) {
                    foreach ($data as $section) {
                        if (is_array($section)) {
                            foreach ($section as $key => $value) {
                                if (is_scalar($value)) {
                                    $tags[$key] ??= (string) $value;
                                }
                            }
                        }
                    }
                }
            } catch (Throwable) {
                // Fichier sans EXIF exploitable : on tente ImageMagick.
            }
        }

        if ($tags !== [] || ! class_exists(Imagick::class)) {
            return $tags;
        }

        try {
            $imagick = new Imagick($path);

            foreach ($imagick->getImageProperties('exif:*') as $key => $value) {
                $tags[substr($key, 5)] = (string) $value;
            }

            $imagick->clear();
        } catch (Throwable) {
            // Pas de métadonnées : on renvoie ce qu'on a.
        }

        return $tags;
    }

    private static function tag(array $raw, string $key): ?string
    {
        $value = $raw[$key] ?? null;

        return is_scalar($value) ? trim((string) $value) : null;
    }

    private static function integer(array $raw, string $key): ?int
    {
        $value = self::tag($raw, $key);

        return $value === null || $value === '' ? null : (int) $value;
    }

    /**
     * Les valeurs EXIF numériques arrivent souvent en fraction « 7/2 ».
     */
    private static function rational(array $raw, string $key): ?float
    {
        $value = self::tag($raw, $key);

        if ($value === null || $value === '') {
            return null;
        }

        if (str_contains($value, '/')) {
            [$numerator, $denominator] = array_pad(explode('/', $value, 2), 2, '1');

            if ((float) $denominator === 0.0) {
                return null;
            }

            return round((float) $numerator / (float) $denominator, 4);
        }

        return is_numeric($value) ? (float) $value : null;
    }
}
