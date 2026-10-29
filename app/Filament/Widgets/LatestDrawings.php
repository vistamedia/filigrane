<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Drawings\DrawingResource;
use App\Models\Drawing;
use Filament\Actions\Action;
use Filament\Support\Enums\TextSize;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * La file d'attente du jour : les prochains dessins à décrire, avec leur
 * vignette, pour reprendre le travail sans passer par la recherche.
 */
class LatestDrawings extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function getTableHeading(): string
    {
        return 'À décrire';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Drawing::query()
                ->toDescribe()
                ->with(['coverPhoto', 'photos'])
                ->orderBy('inventory_number'))
            ->columns([
                ImageColumn::make('vignette')
                    ->label('')
                    ->state(fn (Drawing $record): ?string => $record->displayPhoto()?->thumbUrl())
                    ->defaultImageUrl(asset('icones/vignette-absente.svg'))
                    ->height(64)
                    ->extraImgAttributes(['class' => 'rounded-md object-cover']),

                TextColumn::make('inventory_number')
                    ->label('Inventaire')
                    ->size(TextSize::Large),

                TextColumn::make('artist')
                    ->label('Artiste')
                    ->placeholder('—'),

                TextColumn::make('created_at')
                    ->label('Entré le')
                    ->date('j F Y')
                    ->toggleable(),
            ])
            ->recordActions([
                Action::make('decrire')
                    ->label('Décrire')
                    ->icon('heroicon-o-pencil-square')
                    ->button()
                    ->url(fn (Drawing $record): string => DrawingResource::getUrl('edit', ['record' => $record])),
            ])
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading('Tout est décrit')
            ->emptyStateDescription('Il n\'y a plus de fiche en attente.')
            ->emptyStateIcon('heroicon-o-check-circle');
    }
}
