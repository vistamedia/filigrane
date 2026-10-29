<?php

namespace App\Filament\Resources\Drawings;

use App\Filament\Resources\Drawings\Pages\CreateDrawing;
use App\Filament\Resources\Drawings\Pages\EditDrawing;
use App\Filament\Resources\Drawings\Pages\ListDrawings;
use App\Filament\Resources\Drawings\RelationManagers\PhotosRelationManager;
use App\Filament\Resources\Drawings\Schemas\DrawingForm;
use App\Filament\Resources\Drawings\Tables\DrawingsTable;
use App\Models\Drawing;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class DrawingResource extends Resource
{
    protected static ?string $model = Drawing::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Collection';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'inventory_number';

    /** Les adresses sont en français : /dessins, /dessins/12/decrire. */
    protected static ?string $slug = 'dessins';

    public static function getNavigationLabel(): string
    {
        return 'Dessins';
    }

    public static function getModelLabel(): string
    {
        return 'dessin';
    }

    public static function getPluralModelLabel(): string
    {
        return 'dessins';
    }

    /**
     * Le nombre de fiches en attente de description, affiché dans le menu :
     * c'est la file de travail du jour.
     */
    public static function getNavigationBadge(): ?string
    {
        $count = Drawing::query()->toDescribe()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Dessins encore à décrire';
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return DrawingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DrawingsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            PhotosRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDrawings::route('/'),
            'create' => CreateDrawing::route('/nouveau'),
            'edit' => EditDrawing::route('/{record}/decrire'),
        ];
    }

    /**
     * Les vignettes et les compteurs sont demandés à chaque affichage de la
     * planche : on les charge en une fois.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['coverPhoto', 'photos']);
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    /**
     * @return array<int, string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['inventory_number', 'artist', 'period'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return $record->title();
    }
}
