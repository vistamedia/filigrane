<?php

namespace App\Observers;

use App\Models\Photo;

class PhotoObserver
{
    /**
     * Une fiche ne doit jamais pointer vers une illustration disparue.
     */
    public function deleting(Photo $photo): void
    {
        $drawing = $photo->drawing;

        if ($drawing && $drawing->cover_photo_id === $photo->id) {
            $replacement = $drawing->photos()->whereKeyNot($photo->getKey())->first();

            $drawing->forceFill(['cover_photo_id' => $replacement?->id])->saveQuietly();
        }
    }

    public function deleted(Photo $photo): void
    {
        $photo->deleteFiles();
    }
}
