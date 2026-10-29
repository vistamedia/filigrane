<?php

namespace App\Filament\Pages;

use App\Enums\PhotoKind;
use App\Models\Drawing;
use App\Services\PhotoImporter;
use BackedEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Log;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;
use UnitEnum;

/**
 * Entrée du fonds déjà photographié : on dépose les fichiers, l'application
 * crée autant de fiches « à décrire ».
 *
 * Les images sont traitées une par une, à la demande du navigateur, et non
 * toutes dans la même requête : redimensionner 200 photographies prendrait
 * plusieurs minutes et dépasserait le temps imparti à une page web.
 */
class ImportEnMasse extends Page
{
    protected string $view = 'filament.pages.import-en-masse';

    protected static ?string $slug = 'import';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static string|UnitEnum|null $navigationGroup = 'Prise de vue';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Import en masse';

    public ?array $data = [];

    /** Noms des fichiers temporaires restant à traiter. */
    public array $attente = [];

    public int $total = 0;

    public int $traitees = 0;

    /** @var array<int, string> */
    public array $echecs = [];

    /** @var array<int, array{inventaire: string, lien: string, vignette: ?string}> */
    public array $resultats = [];

    public ?int $ficheGroupee = null;

    public static function getNavigationLabel(): string
    {
        return 'Import en masse';
    }

    public function getSubheading(): ?string
    {
        return 'Déposez les photographies déjà prises : chaque fichier devient une fiche à décrire.';
    }

    public function mount(): void
    {
        $this->form->fill([
            'repartition' => 'une_fiche_par_fichier',
            'kind' => PhotoKind::Recto->value,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Fichiers')
                    ->schema([
                        FileUpload::make('fichiers')
                            ->label('Photographies')
                            ->multiple()
                            ->image()
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif', 'image/tiff', 'image/avif'])
                            ->maxSize(100 * 1024)
                            ->maxFiles(300)
                            ->storeFiles(false)
                            ->panelLayout('grid')
                            ->required()
                            ->helperText('JPEG, PNG, WebP, TIFF ou HEIC. Les originaux sont conservés intacts ; seules les versions de consultation sont fabriquées.'),
                    ]),

                Section::make('Options')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        Radio::make('repartition')
                            ->label('Répartition')
                            ->options([
                                'une_fiche_par_fichier' => 'Une fiche par fichier',
                                'une_seule_fiche' => 'Tous les fichiers sur une seule fiche',
                            ])
                            ->descriptions([
                                'une_fiche_par_fichier' => 'Le cas courant : chaque photographie correspond à un dessin.',
                                'une_seule_fiche' => 'Pour le recto, le verso et les détails d\'un même dessin.',
                            ])
                            ->default('une_fiche_par_fichier')
                            ->required(),

                        TextInput::make('artist')
                            ->label('Artiste (appliqué à toutes les fiches)')
                            ->placeholder('Laisser vide si inconnu')
                            ->maxLength(255),

                        TextInput::make('period')
                            ->label('Période (appliquée à toutes les fiches)')
                            ->placeholder('Laisser vide si inconnue')
                            ->maxLength(255),
                    ]),
            ])
            ->statePath('data');
    }

    /**
     * Met les fichiers en file et laisse le navigateur dérouler.
     */
    public function demarrer(): void
    {
        $etat = $this->form->getState();

        $this->attente = collect($etat['fichiers'] ?? [])
            ->filter(fn ($fichier) => $fichier instanceof TemporaryUploadedFile)
            ->map(fn (TemporaryUploadedFile $fichier) => $fichier->getFilename())
            ->values()
            ->all();

        $this->total = count($this->attente);
        $this->traitees = 0;
        $this->echecs = [];
        $this->resultats = [];
        $this->ficheGroupee = null;

        if ($this->total === 0) {
            Notification::make()->title('Aucun fichier à importer')->warning()->send();

            return;
        }

        $this->dispatch('import-demarre');
    }

    /**
     * Traite une photographie et rend la main : une requête, une image.
     */
    public function suivant(): bool
    {
        $nom = array_shift($this->attente);

        if ($nom === null) {
            return false;
        }

        $etat = $this->data;

        try {
            $fichier = TemporaryUploadedFile::createFromLivewire($nom);

            $dessin = $this->ficheCible($etat);

            $photo = app(PhotoImporter::class)->import(
                $dessin,
                $fichier->getRealPath(),
                $fichier->getClientOriginalName(),
                ['kind' => $etat['kind'] ?? null],
            );

            $this->resultats[] = [
                'inventaire' => $dessin->inventory_number,
                'lien' => \App\Filament\Resources\Drawings\DrawingResource::getUrl('edit', ['record' => $dessin]),
                'vignette' => $photo->thumbUrl(),
            ];
        } catch (Throwable $exception) {
            Log::error('Import en masse : fichier ignoré', [
                'fichier' => $nom,
                'erreur' => $exception->getMessage(),
            ]);

            $this->echecs[] = $nom.' — '.$exception->getMessage();
        }

        $this->traitees++;

        if ($this->attente === []) {
            $this->terminer();
        }

        return true;
    }

    private function ficheCible(array $etat): Drawing
    {
        $groupee = ($etat['repartition'] ?? null) === 'une_seule_fiche';

        if ($groupee && $this->ficheGroupee) {
            return Drawing::findOrFail($this->ficheGroupee);
        }

        $dessin = Drawing::create(array_filter([
            'artist' => $etat['artist'] ?? null,
            'period' => $etat['period'] ?? null,
        ]));

        if ($groupee) {
            $this->ficheGroupee = $dessin->id;
        }

        return $dessin;
    }

    private function terminer(): void
    {
        $reussies = count($this->resultats);

        Notification::make()
            ->title($reussies > 1 ? "{$reussies} photographies importées" : "{$reussies} photographie importée")
            ->body($this->echecs === []
                ? 'Les fiches sont en attente de description.'
                : count($this->echecs).' fichier(s) n\'ont pas pu être lus.')
            ->success()
            ->send();

        // On vide le dépôt pour éviter un second import par inadvertance.
        $this->form->fill([
            'repartition' => $this->data['repartition'] ?? 'une_fiche_par_fichier',
            'kind' => $this->data['kind'] ?? PhotoKind::Recto->value,
            'artist' => $this->data['artist'] ?? null,
            'period' => $this->data['period'] ?? null,
        ]);
    }
}
