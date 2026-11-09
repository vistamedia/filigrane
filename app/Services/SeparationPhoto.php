<?php

namespace App\Services;

use App\Enums\PhotoKind;
use App\Jobs\ProcessPhoto;
use App\Models\Drawing;
use App\Models\Photo;
use App\Support\DetecteurDeDessins;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Imagick;
use InvalidArgumentException;

/**
 * Sépare une photographie qui montre plusieurs dessins côte à côte : une
 * fiche par dessin, chacune avec sa photographie, découpée à pleine
 * définition dans l'original (D-45).
 *
 * Le premier dessin, dans l'ordre de la lecture, reste sur la fiche de la
 * photographie : il garde son numéro. Les suivants reçoivent une fiche neuve,
 * qui reprend l'artiste, la période, l'emplacement et les mots-clés de la
 * première. La photographie d'ensemble est ensuite supprimée : chaque dessin
 * en garde sa part, avec une marge.
 *
 * Chaque découpe est un original allégé (D-44) : on retient sa place dans la
 * photographie entière, pour que la retouche calcule juste les proportions.
 */
class SeparationPhoto
{
    public function __construct(private DerivativeMaker $derivatives) {}

    /**
     * @param  list<array{x: float, y: float, w: float, h: float}>  $cadres  normalisés sur l'original orienté
     * @return list<Photo> les photographies découpées, dans l'ordre de la lecture
     */
    public function separer(Photo $photo, array $cadres): array
    {
        if (count($cadres) < 2) {
            throw new InvalidArgumentException('Il faut au moins deux dessins pour séparer une photographie.');
        }

        $source = $photo->drawing;
        $original = Storage::disk(config('filigrane.originals_disk'))->path($photo->original_path);

        /** @var Imagick $imagick */
        $imagick = $this->derivatives->decodeOriented($original)->core()->native();
        [$largeur, $hauteur] = [$imagick->getImageWidth(), $imagick->getImageHeight()];

        $entiere = $photo->isTrimmed()
            ? $photo->fullFrame()
            : ['x' => 0, 'y' => 0, 'largeur_px' => $largeur, 'hauteur_px' => $hauteur];

        $decoupes = [];

        foreach (DetecteurDeDessins::ordreDeLecture($cadres) as $rang => $cadre) {
            $x = (int) floor(max(0, $cadre['x']) * $largeur);
            $y = (int) floor(max(0, $cadre['y']) * $hauteur);
            $l = (int) ceil(min(1, $cadre['x'] + $cadre['w']) * $largeur) - $x;
            $h = (int) ceil(min(1, $cadre['y'] + $cadre['h']) * $hauteur) - $y;

            if ($l < 16 || $h < 16) {
                throw new InvalidArgumentException('Un cadre est trop petit pour contenir un dessin.');
            }

            $fiche = $rang === 0 ? $source : $this->ficheSoeur($source);
            $chemin = $this->ecrireOriginal($imagick->getImageRegion($l, $h, $x, $y), $octets);

            $decoupe = $fiche->photos()->create([
                'kind' => $rang === 0 ? $photo->kind : PhotoKind::Recto,
                'caption' => $rang === 0 ? $photo->caption : null,
                'original_path' => $chemin,
                'original_name' => $photo->original_name,
                'mime' => 'image/jpeg',
                'size_bytes' => $octets,
                // Le JPEG découpé n'a pas d'EXIF : la focale et la date de
                // la photographie d'ensemble valent pour chaque dessin.
                'exif' => $photo->exif,
                'original_trim' => [
                    'x' => $entiere['x'] + $x,
                    'y' => $entiere['y'] + $y,
                    'largeur_px' => $entiere['largeur_px'],
                    'hauteur_px' => $entiere['hauteur_px'],
                ],
                'sort_order' => $rang === 0 ? $photo->sort_order : 0,
            ]);

            config('filigrane.image_processing') === 'queue'
                ? ProcessPhoto::dispatch($decoupe)
                : ProcessPhoto::dispatchSync($decoupe);

            $decoupes[] = $decoupe;
        }

        $imagick->clear();

        // La fiche d'origine prend pour illustration son dessin, et non une
        // autre photographie qu'elle aurait eue avant.
        if ($source->cover_photo_id === $photo->id) {
            $source->forceFill(['cover_photo_id' => $decoupes[0]->id])->saveQuietly();
        }

        $photo->delete();

        return $decoupes;
    }

    /**
     * Une fiche neuve pour un dessin du même lot : ce que l'import avait
     * donné à toute la série vaut pour lui aussi.
     */
    private function ficheSoeur(Drawing $source): Drawing
    {
        $fiche = Drawing::create(array_filter([
            'artist' => $source->artist,
            'period' => $source->period,
            'location' => $source->location,
            'location_other' => $source->location_other,
        ]));

        $fiche->tags()->sync($source->tags()->pluck('tags.id'));

        return $fiche;
    }

    /**
     * Écrit une découpe comme original, en JPEG pleine définition, et en
     * donne le chemin ; son poids sort par $octets.
     */
    private function ecrireOriginal(Imagick $morceau, ?int &$octets): string
    {
        $morceau->setImagePage(0, 0, 0, 0);

        if ($morceau->getImageProfiles('exif', false) !== []) {
            $morceau->removeImageProfile('exif');
        }

        $morceau->setImageOrientation(Imagick::ORIENTATION_TOPLEFT);
        $morceau->setImageFormat('jpeg');
        $morceau->setImageCompressionQuality((int) config('filigrane.original_jpeg_quality', 92));

        $donnees = $morceau->getImageBlob();
        $morceau->clear();
        $octets = strlen($donnees);

        $chemin = sprintf(
            '%s/%s/%s.jpg',
            trim(config('filigrane.originals_path'), '/'),
            now()->format('Y/m'),
            Str::lower((string) Str::ulid()),
        );

        Storage::disk(config('filigrane.originals_disk'))->put($chemin, $donnees);

        return $chemin;
    }
}
