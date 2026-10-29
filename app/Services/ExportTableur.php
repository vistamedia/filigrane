<?php

namespace App\Services;

use App\Models\Drawing;
use App\Support\TexteRiche;
use Illuminate\Database\Eloquent\Builder;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Le catalogue en tableur, sans images.
 *
 * C'est le format de travail : on y trie, on y filtre, on en tire des listes.
 * Les dimensions sortent donc en nombres — en millimètres, l'unité de la base
 * — et non en « 40,1 × 30,1 cm », qu'aucun tableur ne saurait additionner.
 *
 * L'écriture se fait en flux, ligne à ligne : la mémoire ne dépend pas du
 * nombre de fiches.
 */
class ExportTableur
{
    private const COLONNES = [
        'Inventaire' => 14,
        'Artiste' => 28,
        'Période' => 16,
        'Hauteur (mm)' => 13,
        'Largeur (mm)' => 13,
        'Origine des dimensions' => 20,
        'Mots-clés' => 26,
        'Avancement' => 14,
        'Description' => 70,
        'Note' => 40,
        'Photographies' => 14,
        'Décrit le' => 14,
    ];

    /**
     * @param  Builder<Drawing>  $requete
     */
    public function ecrire(Builder $requete, string $chemin): int
    {
        $options = new Options();

        // Les colonnes sont numérotées à partir de 1 : A vaut 1, pas 0. Un
        // zéro produit un fichier qu'Excel refuse d'ouvrir.
        $colonne = 1;
        foreach (self::COLONNES as $largeur) {
            $options->setColumnWidth((float) $largeur, $colonne++);
        }

        $corps = (new Style())
            ->setShouldWrapText()
            ->setCellVerticalAlignment(CellVerticalAlignment::TOP);

        $options->DEFAULT_ROW_STYLE = $corps;

        $writer = new Writer($options);
        $writer->openToFile($chemin);
        $writer->getCurrentSheet()->setName('Catalogue');

        $writer->addRow(Row::fromValues(
            array_keys(self::COLONNES),
            (new Style())->setFontBold()->setBackgroundColor('EFE7DC'),
        ));

        $nombre = 0;

        $requete->with(['tags', 'photos'])
            ->orderBy('inventory_number')
            ->chunk(200, function ($dessins) use ($writer, &$nombre): void {
                foreach ($dessins as $dessin) {
                    $writer->addRow(Row::fromValues($this->ligne($dessin)));
                    $nombre++;
                }
            });

        $writer->close();

        return $nombre;
    }

    private function ligne(Drawing $dessin): array
    {
        return [
            $dessin->inventory_number,
            $dessin->artist ?? '',
            $dessin->period ?? '',
            $dessin->height_mm ?? '',
            $dessin->width_mm ?? '',
            $dessin->dimensions_source?->getLabel() ?? '',
            $dessin->tags->pluck('name')->implode(', '),
            $dessin->status->getLabel(),
            TexteRiche::enTexte($dessin->description),
            TexteRiche::enTexte($dessin->note),
            $dessin->photos->count(),
            $dessin->described_at?->format('d/m/Y') ?? '',
        ];
    }
}
