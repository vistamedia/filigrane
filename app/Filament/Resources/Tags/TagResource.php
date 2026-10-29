<?php

namespace App\Filament\Resources\Tags;

use App\Filament\Resources\Tags\Pages\ManageTags;
use App\Models\Tag;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class TagResource extends Resource
{
    protected static ?string $model = Tag::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|UnitEnum|null $navigationGroup = 'Collection';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'mots-cles';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationLabel(): string
    {
        return 'Mots-clés';
    }

    public static function getModelLabel(): string
    {
        return 'mot-clé';
    }

    public static function getPluralModelLabel(): string
    {
        return 'mots-clés';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Mot-clé')
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true),

            TextInput::make('family')
                ->label('Famille')
                ->placeholder('sujet, école, emplacement, provenance…')
                ->helperText('Facultatif : sert à regrouper les mots-clés quand ils se multiplient.')
                ->maxLength(50)
                ->datalist(fn () => Tag::query()
                    ->whereNotNull('family')
                    ->distinct()
                    ->orderBy('family')
                    ->pluck('family')
                    ->all()),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Mot-clé')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('family')
                    ->label('Famille')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('drawings_count')
                    ->label('Dessins')
                    ->counts('drawings')
                    ->sortable(),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('Aucun mot-clé')
            ->emptyStateDescription('Les mots-clés se créent aussi directement depuis une fiche de dessin.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageTags::route('/'),
        ];
    }
}
