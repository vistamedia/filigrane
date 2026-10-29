<?php

namespace App\Filament\Resources\Drawings\Schemas;

use App\Enums\DimensionsSource;
use App\Enums\DrawingStatus;
use App\Livewire\VisionneusePhotos;
use App\Models\Drawing;
use App\Models\Tag;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class DrawingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Cinq colonnes : deux pour l'image, trois pour les champs.
                // Un tiers de la largeur ne suffisait pas pour examiner un
                // dessin tout en écrivant à côté.
                Grid::make()
                    ->columns(['default' => 1, 'lg' => 5])
                    // Sans cela, la grille n'occuperait qu'une colonne de la
                    // grille parente et la fiche resterait tassée à gauche.
                    ->columnSpanFull()
                    ->schema([
                        // La photo reste sous les yeux pendant qu'on décrit :
                        // c'est tout l'objet de l'application.
                        Livewire::make(VisionneusePhotos::class, fn (?Drawing $record): array => [
                            'dessin' => $record,
                        ])
                            ->key('visionneuse')
                            ->columnSpan(['default' => 1, 'lg' => 2]),

                        Grid::make(1)
                            ->columnSpan(['default' => 1, 'lg' => 3])
                            ->schema([
                                self::identification(),
                                self::description(),
                                self::note(),
                                self::classement(),
                            ]),
                    ]),
            ]);
    }

    private static function identification(): Section
    {
        return Section::make('Identification')
            ->icon('heroicon-o-identification')
            ->columns(['default' => 1, 'sm' => 2])
            ->schema([
                TextInput::make('artist')
                    ->label('Artiste')
                    ->placeholder('Anonyme, école française…')
                    ->maxLength(255)
                    // Les noms déjà saisis sont proposés : on évite « Boucher »,
                    // « F. Boucher » et « Boucher (François) » dans la même base.
                    ->datalist(fn () => self::existingValues('artist')),

                TextInput::make('period')
                    ->label('Période')
                    ->placeholder('XVIIIᵉ siècle, vers 1750…')
                    ->maxLength(255)
                    ->datalist(fn () => self::existingValues('period')),

                // À la création, le numéro n'existe pas encore : il est attribué
                // à l'enregistrement, pour que deux fiches ouvertes en même
                // temps ne puissent pas réclamer le même. Une fois la fiche
                // créée, il reste modifiable (reprise d'un inventaire existant).
                TextInput::make('inventory_number')
                    ->label('Numéro d\'inventaire')
                    ->helperText(fn (string $operation): string => $operation === 'create'
                        ? 'Attribué automatiquement à l\'enregistrement.'
                        : 'Modifiable, par exemple pour reprendre un numéro d\'inventaire existant.')
                    ->placeholder(fn (string $operation): ?string => $operation === 'create'
                        ? 'FIL-…'
                        : null)
                    ->disabled(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (string $operation): bool => $operation !== 'create')
                    ->required(fn (string $operation): bool => $operation !== 'create')
                    ->maxLength(32)
                    ->unique(ignoreRecord: true)
                    ->columnSpanFull(),
            ]);
    }

    private static function description(): Section
    {
        return Section::make('Description')
            ->icon('heroicon-o-document-text')
            ->description('Ce que l\'on voit : sujet, technique, état, inscriptions.')
            ->schema([
                RichEditor::make('description')
                    ->label('')
                    ->toolbarButtons([
                        ['bold', 'italic', 'underline', 'link'],
                        ['bulletList', 'orderedList'],
                        ['undo', 'redo'],
                    ])
                    ->fileAttachmentsDisk('public')
                    ->fileAttachmentsDirectory('pieces-jointes')
                    ->columnSpanFull(),
            ]);
    }

    private static function note(): Section
    {
        return Section::make('Note')
            ->icon('heroicon-o-pencil-square')
            ->description('Hypothèses, rapprochements, provenance, questions en suspens.')
            ->collapsible()
            ->schema([
                RichEditor::make('note')
                    ->label('')
                    ->toolbarButtons([
                        ['bold', 'italic', 'underline', 'link'],
                        ['bulletList', 'orderedList'],
                        ['undo', 'redo'],
                    ])
                    ->fileAttachmentsDisk('public')
                    ->fileAttachmentsDirectory('pieces-jointes')
                    ->columnSpanFull(),
            ]);
    }

    private static function classement(): Section
    {
        return Section::make('Classement')
            ->icon('heroicon-o-tag')
            ->columns(['default' => 1, 'sm' => 2])
            ->schema([
                ToggleButtons::make('status')
                    ->label('Avancement')
                    ->options(DrawingStatus::class)
                    ->default(DrawingStatus::ToDescribe)
                    ->inline()
                    ->required()
                    ->columnSpanFull(),

                Select::make('tags')
                    ->label('Mots-clés')
                    ->relationship('tags', 'name')
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->createOptionForm([
                        TextInput::make('name')
                            ->label('Mot-clé')
                            ->required()
                            ->maxLength(255)
                            ->unique(Tag::class, 'name'),
                        TextInput::make('family')
                            ->label('Famille (facultatif)')
                            ->placeholder('sujet, école, emplacement…')
                            ->maxLength(50),
                    ])
                    ->helperText('Créez-les au fil de l\'eau : sujet, école, emplacement, provenance…')
                    ->columnSpanFull(),

                TextInput::make('height_mm')
                    ->label('Hauteur')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(5000)
                    ->suffix('mm')
                    ->helperText('Laissez vide : la mesure se calcule depuis la photo.'),

                TextInput::make('width_mm')
                    ->label('Largeur')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(5000)
                    ->suffix('mm'),

                Select::make('dimensions_source')
                    ->label('Origine des dimensions')
                    ->options(DimensionsSource::class)
                    ->native(false)
                    ->placeholder('Non renseignée')
                    ->columnSpanFull(),
            ]);
    }

    /** @var array<string, array<int, string>> */
    private static array $valueCache = [];

    /**
     * Valeurs déjà présentes dans la base, pour l'autocomplétion.
     *
     * @return array<int, string>
     */
    private static function existingValues(string $column): array
    {
        return self::$valueCache[$column] ??= Drawing::query()
            ->whereNotNull($column)
            ->distinct()
            ->orderBy($column)
            ->limit(200)
            ->pluck($column)
            ->map(fn (string $value) => Str::squish($value))
            ->filter()
            ->values()
            ->all();
    }
}
