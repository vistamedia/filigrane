<?php

namespace App\Services;

use App\Models\Drawing;
use App\Support\TexteRiche;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Database\Eloquent\Builder;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;

/**
 * Le catalogue en PDF, avec les images : une page par dessin, la reproduction
 * en haut, le commentaire dessous. C'est le document qu'on imprime, qu'on
 * envoie, qu'on garde.
 *
 * Les images ne sont pas reprises telles quelles : le dérivé « web » fait
 * 2 400 pixels de large, soit bien plus que ce qu'une page A4 peut rendre, et
 * deux cents fiches de ce calibre donneraient un fichier de plusieurs centaines
 * de mégaoctets. Chaque image est donc ramenée à la définition utile — environ
 * 190 points par pouce à la taille où elle sera imprimée — dans un dossier
 * temporaire effacé à la fin.
 */
class ExportCatalogue
{
    /** Définition de travail des images insérées dans le PDF. */
    private const LARGEUR_IMAGE_PX = 1200;

    /** Boîte réservée à la reproduction, en millimètres. */
    private const BOITE_MM = ['largeur' => 160.0, 'hauteur' => 112.0];

    private ImageManager $images;

    public function __construct(?ImageManager $images = null)
    {
        $this->images = $images ?? new ImageManager(new ImagickDriver());
    }

    /**
     * @param  Builder<Drawing>  $requete
     * @return int Nombre de fiches écrites
     */
    public function ecrire(Builder $requete, string $chemin, bool $avecNotes = false): int
    {
        $atelier = $this->atelier();

        try {
            $planches = $this->preparer($requete, $atelier, $avecNotes);

            if ($planches === []) {
                return 0;
            }

            $html = view('exports.catalogue', [
                'planches' => $planches,
                'date' => now(),
            ])->render();

            $options = new Options();
            $options->set('isRemoteEnabled', false);
            $options->set('isHtml5ParserEnabled', true);
            // dompdf refuse les fichiers hors de ce périmètre : seules les
            // images que nous venons de fabriquer sont lisibles.
            $options->set('chroot', [$atelier, public_path()]);
            $options->set('defaultFont', 'DejaVu Serif');
            $options->set('defaultPaperSize', 'a4');

            $dompdf = new Dompdf($options);
            $dompdf->setPaper('a4', 'portrait');
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->render();

            $this->numeroter($dompdf);

            file_put_contents($chemin, $dompdf->output());

            return count($planches);
        } finally {
            $this->ranger($atelier);
        }
    }

    /**
     * Les planches, images réduites comprises. Le parcours se fait par paquets
     * pour ne pas charger deux cents fiches et leurs relations d'un bloc.
     *
     * @param  Builder<Drawing>  $requete
     */
    private function preparer(Builder $requete, string $atelier, bool $avecNotes): array
    {
        $planches = [];

        $requete->with(['coverPhoto', 'photos', 'tags'])
            ->orderBy('inventory_number')
            ->chunk(50, function ($dessins) use (&$planches, $atelier, $avecNotes): void {
                foreach ($dessins as $dessin) {
                    $planches[] = [
                        'cote' => $dessin->inventory_number,
                        'artiste' => $dessin->artist,
                        'periode' => $dessin->period,
                        'dimensions' => $dessin->dimensionsLabel(),
                        'description' => TexteRiche::nettoyer($dessin->description),
                        'note' => $avecNotes ? TexteRiche::nettoyer($dessin->note) : '',
                        'motsCles' => $dessin->tags->pluck('name')->implode(' · '),
                        'image' => $this->reduire($dessin, $atelier),
                    ];
                }
            });

        return $planches;
    }

    /**
     * Ramène la reproduction à la définition utile et retourne de quoi la
     * poser dans la page : chemin, largeur et hauteur en millimètres.
     *
     * dompdf ne sait pas appliquer « max-height » à une image ; la mise à
     * l'échelle est donc calculée ici et écrite en dur dans le HTML.
     *
     * @return array{chemin: string, largeur: float, hauteur: float}|null
     */
    private function reduire(Drawing $dessin, string $atelier): ?array
    {
        $photo = $dessin->displayPhoto();

        if (! $photo?->web_path) {
            return null;
        }

        $source = storage_path('app/public/'.$photo->web_path);

        if (! is_readable($source)) {
            return null;
        }

        $cible = $atelier.'/'.$dessin->inventory_number.'.jpg';

        $image = $this->images->decodePath($source)->scaleDown(width: self::LARGEUR_IMAGE_PX);
        $image->save($cible, quality: 78);

        $rapport = $image->height() / max($image->width(), 1);

        $largeur = self::BOITE_MM['largeur'];
        $hauteur = $largeur * $rapport;

        if ($hauteur > self::BOITE_MM['hauteur']) {
            $hauteur = self::BOITE_MM['hauteur'];
            $largeur = $hauteur / max($rapport, 0.0001);
        }

        return [
            'chemin' => $cible,
            'largeur' => round($largeur, 1),
            'hauteur' => round($hauteur, 1),
        ];
    }

    /**
     * « 3 / 87 » en pied de page, centré, une fois la pagination connue.
     * La page de garde n'est pas foliotée : une couverture ne l'est jamais.
     */
    private function numeroter(Dompdf $dompdf): void
    {
        $police = $dompdf->getFontMetrics()->getFont('DejaVu Serif');

        $dompdf->getCanvas()->page_script(
            function (int $page, int $total, $toile, $metriques) use ($police): void {
                if ($page === 1) {
                    return;
                }

                $texte = "{$page} / {$total}";
                $taille = 9;
                $largeur = $metriques->getTextWidth($texte, $police, $taille);

                // 595,28 points : la largeur d'une A4.
                $toile->text((595.28 - $largeur) / 2, 800, $texte, $police, $taille, [0.45, 0.43, 0.41]);
            },
        );
    }

    private function atelier(): string
    {
        $chemin = storage_path('app/private/exports/'.bin2hex(random_bytes(6)));

        if (! is_dir($chemin) && ! mkdir($chemin, 0o700, true) && ! is_dir($chemin)) {
            throw new \RuntimeException("Dossier de travail impossible à créer : {$chemin}");
        }

        return $chemin;
    }

    private function ranger(string $atelier): void
    {
        foreach (glob($atelier.'/*') ?: [] as $fichier) {
            @unlink($fichier);
        }

        @rmdir($atelier);
    }
}
