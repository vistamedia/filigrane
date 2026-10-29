<?php

namespace App\Services;

use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;

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
        $image = $this->decodeOriented($sourcePath);

        $width = $image->width();
        $height = $image->height();

        $web = $this->encodeWeb($image->scaleDown(width: (int) config('filigrane.web_width', 2400)));

        return [
            'web' => $web,
            'thumb' => $this->thumbnail($web),
            'width' => $width,
            'height' => $height,
        ];
    }

    /**
     * La miniature part de la version web, déjà réduite : c'est bien plus
     * rapide que de repartir de l'original.
     */
    public function thumbnail(string $web): string
    {
        $thumbSize = (int) config('filigrane.thumb_size', 600);

        return (string) $this->manager
            ->decodeBinary($web)
            ->cover($thumbSize, $thumbSize)
            ->encode(new JpegEncoder(quality: (int) config('filigrane.jpeg_quality', 82)));
    }

    /**
     * L'original décodé et remis à l'endroit, pour qui doit le transformer
     * avant d'en tirer une version web (la retouche).
     */
    public function decodeOriented(string $sourcePath): ImageInterface
    {
        return $this->manager->decodePath($sourcePath)->orient();
    }

    public function encodeWeb(ImageInterface $image): string
    {
        return (string) $image->encode(new JpegEncoder(
            quality: (int) config('filigrane.jpeg_quality', 82),
            progressive: true,
        ));
    }
}
