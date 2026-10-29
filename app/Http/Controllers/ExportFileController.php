<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Remise d'un export préparé.
 *
 * Le fichier n'est pas renvoyé depuis l'action Livewire qui l'a fabriqué :
 * Livewire encode en base64, dans sa réponse JSON, tout fichier qu'on lui
 * retourne. Pour un catalogue de deux cents planches — plusieurs dizaines de
 * mégaoctets — cela demanderait au serveur comme au navigateur un tiers de
 * mémoire en plus, pour rien. L'action prépare donc le fichier, puis renvoie
 * vers cette route, qui le diffuse en flux et l'efface derrière elle.
 */
class ExportFileController extends Controller
{
    public function telecharger(Request $requete, string $fichier): BinaryFileResponse
    {
        // Le nom est fabriqué par l'application : rien d'autre n'est accepté,
        // et surtout aucun chemin.
        abort_unless((bool) preg_match('/^[0-9a-f]{16}\.(xlsx|pdf)$/', $fichier), 404);

        $chemin = storage_path('app/private/exports/'.$fichier);

        abort_unless(is_file($chemin), 404);

        $extension = pathinfo($fichier, PATHINFO_EXTENSION);

        return response()
            ->download($chemin, 'filigrane-catalogue-'.now()->format('Y-m-d').'.'.$extension)
            ->deleteFileAfterSend();
    }
}
