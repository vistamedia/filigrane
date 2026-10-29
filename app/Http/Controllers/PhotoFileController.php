<?php

namespace App\Http\Controllers;

use App\Models\Photo;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PhotoFileController extends Controller
{
    /**
     * Affiche l'original en pleine résolution dans le navigateur.
     */
    public function show(Photo $photo): StreamedResponse
    {
        abort_unless($photo->originalExists(), 404);

        return Storage::disk(config('filigrane.originals_disk'))->response(
            $photo->original_path,
            $this->filename($photo),
            ['Cache-Control' => 'private, max-age=86400'],
        );
    }

    /**
     * Télécharge l'original, nommé d'après le numéro d'inventaire.
     */
    public function download(Photo $photo): StreamedResponse
    {
        abort_unless($photo->originalExists(), 404);

        return Storage::disk(config('filigrane.originals_disk'))
            ->download($photo->original_path, $this->filename($photo));
    }

    /**
     * « FIL-0012-recto.jpg » plutôt qu'un identifiant opaque : les fichiers
     * exportés restent compréhensibles hors de l'application.
     */
    private function filename(Photo $photo): string
    {
        $extension = pathinfo($photo->original_path, PATHINFO_EXTENSION);

        return sprintf(
            '%s-%s-%d.%s',
            $photo->drawing->inventory_number,
            $photo->kind->value,
            $photo->id,
            $extension,
        );
    }
}
