<?php

namespace App\Filament\Resources\Calibrations;

use App\Filament\Resources\Calibrations\Pages\ManageCalibrations;
use App\Models\Calibration;
use App\Services\SeriesScaler;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Les profils d'échelle : « sur les photos de cette série, 1 mm réel occupe
 * N pixels ». Établis par l'outil de mesure, ils servent ensuite à mesurer
 * automatiquement toutes les photographies de la même séance.
 */
class CalibrationResource extends Resource
{
    protected static ?string $model = Calibration::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedVariable;

    protected static string|UnitEnum|null $navigationGroup = 'Prise de vue';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'echelles';

    protected static ?string $recordTitleAttribute = 'label';

    public static function canAccess(): bool
    {
        return Auth::user()?->isAdmin() ?? false;
    }

    public static function getNavigationLabel(): string
    {
        return 'Échelles';
    }

    public static function getModelLabel(): string
    {
        return 'profil d\'échelle';
    }

    public static function getPluralModelLabel(): string
    {
        return 'profils d\'échelle';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('label')
                ->label('Nom du profil')
                ->required()
                ->maxLength(255),

            Toggle::make('is_active')
                ->label('Actif')
                ->helperText('Un profil actif s\'applique automatiquement aux nouvelles photographies de la série.')
                ->default(true),

            Textarea::make('notes')
                ->label('Notes')
                ->placeholder('Trépied à 1,20 m, lumière rasante, carton gris…')
                ->rows(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('label')
                    ->label('Profil')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Calibration $record): ?string => $record->notes),

                IconColumn::make('is_active')
                    ->label('Actif')
                    ->boolean(),

                TextColumn::make('px_per_mm')
                    ->label('Échelle')
                    ->formatStateUsing(fn (float $state): string => number_format($state, 2, ',', ' ').' px/mm'),

                TextColumn::make('camera')
                    ->label('Appareil')
                    ->placeholder('indifférent')
                    ->wrap(),

                TextColumn::make('definition')
                    ->label('Cadrage')
                    ->state(fn (Calibration $record): string => $record->image_width_px
                        ? sprintf('%d × %d px', $record->image_width_px, $record->image_height_px)
                        : '—'),

                TextColumn::make('reference_label')
                    ->label('Référence')
                    ->description(fn (Calibration $record): ?string => $record->reference_mm
                        ? number_format($record->reference_mm, 1, ',', ' ').' mm'
                        : null)
                    ->placeholder('—'),

                TextColumn::make('photos_count')
                    ->label('Photos mesurées')
                    ->counts('photos'),

                TextColumn::make('created_at')
                    ->label('Créé le')
                    ->date('j F Y')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('appliquer')
                    ->label('Mesurer la série')
                    ->icon('heroicon-o-play')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading('Mesurer les photographies de cette série')
                    ->modalDescription(fn (Calibration $record): string => sprintf(
                        '%d photographie(s) de la même série attendent une échelle. Elles seront traitées par lots de %d.',
                        app(SeriesScaler::class)->candidates($record)->count(),
                        SeriesScaler::LOT,
                    ))
                    ->modalSubmitActionLabel('Lancer')
                    ->action(function (Calibration $record): void {
                        $bilan = app(SeriesScaler::class)->apply($record);

                        Notification::make()
                            ->title("{$bilan['traitees']} photographie(s) mesurée(s)")
                            ->body($bilan['restantes'] > 0
                                ? "Il reste {$bilan['restantes']} photographie(s) : relancez pour continuer."
                                : 'La série est complète.')
                            ->success()
                            ->send();
                    }),

                EditAction::make(),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('Aucun profil d\'échelle')
            ->emptyStateDescription('Un profil se crée depuis l\'outil « Mesurer » d\'une photographie.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCalibrations::route('/'),
        ];
    }
}
