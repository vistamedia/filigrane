<?php

namespace App\Filament\Resources\Drawings\Tables;

use App\Enums\DrawingStatus;
use App\Filament\Resources\Drawings\DrawingResource;
use App\Models\Drawing;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DrawingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Une collection de dessins se parcourt des yeux : la vue par
            // défaut est une planche de vignettes, pas un tableau de lignes.
            ->columns([
                Stack::make([
                    ImageColumn::make('cover')
                        ->label('')
                        ->state(fn (Drawing $record): ?string => $record->displayPhoto()?->thumbUrl())
                        ->defaultImageUrl(asset('icones/vignette-absente.svg'))
                        ->height(230)
                        ->extraImgAttributes([
                            // Le liseré et le fond blanc évoquent le montage
                            // sous passe-partout ; le dessin ne touche jamais
                            // le bord de sa vignette.
                            'class' => 'fi-filigrane-vignette w-full object-contain',
                            'loading' => 'lazy',
                        ]),

                    // Le numéro sert de repère, pas de titre : il reste discret,
                    // au-dessus de ce qui change d'une fiche à l'autre.
                    TextColumn::make('inventory_number')
                        ->label('Inventaire')
                        ->size(TextSize::Small)
                        ->color('gray')
                        ->extraAttributes(['class' => 'fi-filigrane-cote'])
                        ->searchable(),

                    TextColumn::make('artist')
                        ->label('Artiste')
                        ->weight(FontWeight::SemiBold)
                        ->placeholder('—')
                        ->searchable(),

                    TextColumn::make('period')
                        ->label('Période')
                        ->size(TextSize::Small)
                        ->color('gray')
                        ->searchable(),

                    TextColumn::make('status')
                        ->label('Avancement')
                        ->badge(),
                ])->space(2),
            ])
            ->contentGrid(['default' => 1, 'sm' => 2, 'lg' => 3, 'xl' => 4, '2xl' => 5])
            // Toute la vignette est cliquable : c'est le geste naturel devant
            // une planche de dessins.
            ->recordUrl(fn (Drawing $record): string => DrawingResource::getUrl('edit', ['record' => $record]))
            ->paginationPageOptions([24, 48, 96])
            ->defaultPaginationPageOption(24)
            ->defaultSort('inventory_number', 'asc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Avancement')
                    ->options(DrawingStatus::class)
                    ->multiple(),

                SelectFilter::make('tags')
                    ->label('Mots-clés')
                    ->relationship('tags', 'name')
                    ->multiple()
                    ->preload(),

                TernaryFilter::make('sans_photo')
                    ->label('Photographie')
                    ->placeholder('Toutes les fiches')
                    ->trueLabel('Avec photographie')
                    ->falseLabel('Sans photographie')
                    ->queries(
                        true: fn (Builder $query) => $query->has('photos'),
                        false: fn (Builder $query) => $query->doesntHave('photos'),
                    ),

                TernaryFilter::make('sans_dimensions')
                    ->label('Dimensions')
                    ->placeholder('Toutes les fiches')
                    ->trueLabel('Dimensions connues')
                    ->falseLabel('Dimensions manquantes')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('width_mm'),
                        false: fn (Builder $query) => $query->whereNull('width_mm'),
                    ),

                TrashedFilter::make()->label('Corbeille'),
            ])
            ->searchPlaceholder('Rechercher un artiste, une période, un numéro…')
            // Le bouton reste, parce qu'une vignette cliquable ne s'annonce
            // pas ; mais en second plan, pour que la planche ne soit pas un
            // mur de boutons rouges.
            ->recordActions([
                EditAction::make()
                    ->label('Décrire')
                    ->icon('heroicon-o-pencil-square')
                    ->button()
                    ->outlined()
                    ->color('gray'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Mettre à la corbeille')
                        ->modalHeading('Mettre les fiches sélectionnées à la corbeille')
                        ->modalDescription('Elles quittent la liste mais restent récupérables.')
                        ->modalSubmitActionLabel('Mettre à la corbeille'),

                    RestoreBulkAction::make()
                        ->label('Restaurer')
                        ->modalHeading('Sortir les fiches sélectionnées de la corbeille')
                        ->modalSubmitActionLabel('Restaurer'),

                    ForceDeleteBulkAction::make()
                        ->label('Supprimer définitivement')
                        ->modalHeading('Supprimer définitivement les fiches sélectionnées')
                        ->modalDescription('Les fiches et leurs photographies, originaux compris, seront effacées sans retour possible.')
                        ->modalSubmitActionLabel('Supprimer définitivement'),
                ]),
            ])
            ->emptyStateHeading('Aucun dessin')
            ->emptyStateDescription('Commencez par photographier un dessin ou importer des fichiers.')
            ->emptyStateIcon('heroicon-o-photo');
    }
}
