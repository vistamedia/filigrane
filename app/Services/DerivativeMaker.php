<?php

namespace App\Services;

use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;

/**
 * Fabrication des deux dérivés JPEG servis par l'application :
 * une version « web » pour la consultation plein écran et une miniature
 * carrée pour les galeries. L'original, lui, n'est jamais modifié.
 */
class DerivativeMaker
{
    private ImageManager $manager;

    public function __construct(?ImageManager $manager = null)
    {
        // Le pilote Imagick est indispensable : c'est lui qui sait lire les
        // HEIC produits par les iPhone (GD en est incapable).
        $this->manager = $manager ?? new ImageManager(new ImagickDriver());
    }

    /**
     * @return array{web: string, thumb: string, width: int, height: int}
     */
    public function make(string $sourcePath): array
    {
        $quality = (int) config('filigrane.jpeg_quality', 82);

        $image = $this->manager->decodePath($sourcePath)->orient();

        $width = $image->width();
        $height = $image->height();

        $web = (string) $image
            ->scaleDown(width: (int) config('filigrane.web_width', 2400))
            ->encode(new JpegEncoder(quality: $quality, progressive: true));

        // La miniature part de la version web, déjà réduite : c'est bien plus
        // rapide que de repartir de l'original.
        $thumbSize = (int) config('filigrane.thumb_size', 600);

        $thumb = (string) $this->manager
            ->decodeBinary($web)
            ->cover($thumbSize, $thumbSize)
            ->encode(new JpegEncoder(quality: $quality));

        return [
            'web' => $web,
            'thumb' => $thumb,
            'width' => $width,
            'height' => $height,
        ];
    }
}
