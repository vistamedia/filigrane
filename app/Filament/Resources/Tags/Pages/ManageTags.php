<?php

namespace App\Filament\Resources\Tags\Pages;

use App\Filament\Resources\Tags\TagResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

/**
 * Les mots-clés sont assez simples pour être créés et modifiés sans quitter
 * la liste : une fenêtre suffit, inutile d'ouvrir une page par mot.
 */
class ManageTags extends ManageRecords
{
    protected static string $resource = TagResource::class;

    protected static ?string $title = 'Mots-clés';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nouveau mot-clé'),
        ];
    }
}
