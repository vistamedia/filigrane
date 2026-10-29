<?php

namespace App\Filament\Resources\Drawings\RelationManagers;

use App\Enums\PhotoKind;
use App\Filament\Pages\MesurerPhoto;
use App\Filament\Pages\RetoucherPhoto;
use App\Models\Photo;
use App\Services\PhotoImporter;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Log;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

class PhotosRelationManager extends RelationManager
{
    protected static string $relationship = 'photos';

    protected static ?string $title = 'Photographies';

    /**
     * Les photographies sont le cœur de la fiche : elles s'affichent avec le
     * reste de la page, et non après coup au moment où l'on fait défiler.
     */
    protected static ?bool $isLazyLoaded = false;

    public static function getModelLabel(): string
    {
        return 'photographie';
    }

    public static function getPluralModelLabel(): string
    {
        return 'photographies';
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('kind')
                ->label('Nature')
                ->options(PhotoKind::class)
                ->native(false)
                ->required(),

            TextInput::make('caption')
                ->label('Légende')
                ->placeholder('Filigrane visible en bas à gauche…')
                ->maxLength(255),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('caption')
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                ImageColumn::make('thumb_path')
                    ->label('')
                    ->state(fn (Photo $record): ?string => $record->thumbUrl())
                    ->defaultImageUrl(asset('icones/vignette-absente.svg'))
                    ->height(72)
                    ->extraImgAttributes(['class' => 'rounded-md object-cover']),

                TextColumn::make('kind')
                    ->label('Nature')
                    ->badge(),

                TextColumn::make('caption')
                    ->label('Légende')
                    ->placeholder('—')
                    ->wrap(),

                TextColumn::make('definition')
                    ->label('Définition')
                    ->state(fn (Photo $record): string => $record->width_px
                        ? sprintf('%d × %d px', $record->width_px, $record->height_px)
                        : 'en cours de traitement')
                    ->description(fn (Photo $record): ?string => $record->isRetouched() ? 'retouchée' : null),

                TextColumn::make('echelle')
                    ->label('Échelle')
                    ->state(fn (Photo $record): string => $record->px_per_mm
                        ? number_format($record->px_per_mm, 2, ',', ' ').' px/mm'
                        : 'non mesurée')
                    ->badge()
                    ->color(fn (Photo $record): string => $record->px_per_mm ? 'success' : 'gray'),

                TextColumn::make('processing_error')
                    ->label('Erreur')
                    ->placeholder('—')
                    ->color('danger')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->wrap(),
            ])
            ->headerActions([
                $this->ajouterAction(),
            ])
            ->recordActions([
                Action::make('illustration')
                    ->label('Illustration')
                    ->icon('heroicon-o-star')
                    ->tooltip('Utiliser cette photo comme vignette de la fiche')
                    ->iconButton()
                    // On interroge la fiche parente déjà chargée plutôt que de
                    // la recharger pour chaque ligne du tableau.
                    ->visible(fn (Photo $record): bool => $this->getOwnerRecord()->cover_photo_id !== $record->id)
                    ->action(function (Photo $record): void {
                        $this->getOwnerRecord()->forceFill(['cover_photo_id' => $record->id])->saveQuietly();

                        $this->dispatch('photographies-modifiees');

                        Notification::make()
                            ->title('Illustration de la fiche mise à jour')
                            ->success()
                            ->send();
                    }),

                Action::make('retoucher')
                    ->label('Retoucher')
                    ->icon('heroicon-o-scissors')
                    ->tooltip('Redresser, recadrer, tourner ou éclaircir cette photo')
                    ->iconButton()
                    ->url(fn (Photo $record): string => RetoucherPhoto::getUrl(['photo' => $record->id])),

                Action::make('mesurer')
                    ->label('Mesurer')
                    ->icon('heroicon-o-variable')
                    ->tooltip('Calculer les dimensions depuis cette photo')
                    ->iconButton()
                    ->url(fn (Photo $record): string => MesurerPhoto::getUrl(['photo' => $record->id])),

                Action::make('original')
                    ->label('Original')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->tooltip('Télécharger la photo en pleine résolution')
                    ->iconButton()
                    ->url(fn (Photo $record): string => route('photos.download', $record))
                    ->openUrlInNewTab(),

                EditAction::make()->iconButton(),

                DeleteAction::make()
                    ->iconButton()
                    ->after(fn () => $this->dispatch('photographies-modifiees')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Aucune photographie')
            ->emptyStateDescription('Déposez des fichiers ou utilisez le studio photo.');
    }

    /**
     * Dépôt de fichiers : chaque image arrive dans sa propre requête (Livewire),
     * ce qui contourne la limite de 20 fichiers par envoi du serveur.
     */
    private function ajouterAction(): Action
    {
        return Action::make('ajouter')
            ->label('Ajouter des photographies')
            ->icon('heroicon-o-plus')
            ->modalHeading('Ajouter des photographies')
            ->modalSubmitActionLabel('Importer')
            ->schema([
                FileUpload::make('fichiers')
                    ->label('Fichiers')
                    ->multiple()
                    ->image()
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif', 'image/tiff', 'image/avif'])
                    ->maxSize(100 * 1024)
                    ->maxFiles(40)
                    ->storeFiles(false)
                    ->required()
                    ->helperText('JPEG, PNG, WebP, TIFF ou HEIC (iPhone). L\'original est conservé tel quel.'),

                Select::make('kind')
                    ->label('Nature de ces photographies')
                    // Laissé vide, chaque image est qualifiée selon son rang :
                    // la première d'une fiche est le recto, les suivantes des
                    // détails. Forcer une valeur ici les étiquetterait toutes
                    // pareil, ce qui n'a de sens que pour une série homogène.
                    ->placeholder('Automatique (recto, puis détails)')
                    ->options(PhotoKind::class)
                    ->native(false),
            ])
            ->action(function (array $data, PhotoImporter $importer): void {
                $drawing = $this->getOwnerRecord();
                $importees = 0;
                $echecs = [];

                foreach ($data['fichiers'] as $fichier) {
                    /** @var TemporaryUploadedFile $fichier */
                    try {
                        $importer->import(
                            $drawing,
                            $fichier->getRealPath(),
                            $fichier->getClientOriginalName(),
                            ['kind' => $data['kind'] ?: null],
                        );

                        $importees++;
                    } catch (Throwable $exception) {
                        Log::error('Import de photographie impossible', [
                            'fichier' => $fichier->getClientOriginalName(),
                            'erreur' => $exception->getMessage(),
                        ]);

                        $echecs[] = $fichier->getClientOriginalName();
                    }
                }

                if ($importees > 0) {
                    // Le panneau d'images de la fiche se redessine seul.
                    $this->dispatch('photographies-modifiees');

                    Notification::make()
                        ->title($importees > 1 ? "{$importees} photographies ajoutées" : '1 photographie ajoutée')
                        ->success()
                        ->send();
                }

                if ($echecs !== []) {
                    Notification::make()
                        ->title('Certains fichiers n\'ont pas pu être importés')
                        ->body(implode(', ', $echecs))
                        ->danger()
                        ->persistent()
                        ->send();
                }
            });
    }
}
