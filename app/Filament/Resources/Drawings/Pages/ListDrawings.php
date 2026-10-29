<?php

namespace App\Filament\Resources\Drawings\Pages;

use App\Enums\DrawingStatus;
use App\Filament\Resources\Drawings\DrawingResource;
use App\Models\Drawing;
use App\Services\ExportCatalogue;
use App\Services\ExportTableur;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\URL;

class ListDrawings extends ListRecords
{
    protected static string $resource = DrawingResource::class;

    protected static ?string $title = 'Dessins';

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                Action::make('tableur')
                    ->label('Tableur (Excel)')
                    ->icon('heroicon-o-table-cells')
                    ->action(fn () => $this->exporterTableur()),

                Action::make('catalogue')
                    ->label('Catalogue (PDF)')
                    ->icon('heroicon-o-book-open')
                    ->modalHeading('Exporter le catalogue en PDF')
                    ->modalDescription('Une page par dessin : la reproduction en haut, le commentaire dessous.')
                    ->modalSubmitActionLabel('Préparer le PDF')
                    ->schema([
                        Toggle::make('notes')
                            ->label('Inclure les notes')
                            ->helperText('Les notes sont des remarques de travail. Un catalogue destiné à être montré s\'en passe ; un catalogue pour soi les garde.')
                            ->default(false),
                    ])
                    ->action(fn (array $data) => $this->exporterCatalogue((bool) ($data['notes'] ?? false))),
            ])
                ->label('Exporter')
                ->icon('heroicon-o-arrow-down-tray')
                ->button()
                ->color('gray'),

            CreateAction::make()->label('Nouvelle fiche'),
        ];
    }

    /**
     * Les exports reprennent exactement ce que la liste montre : onglet
     * ouvert, filtres posés, recherche en cours. Exporter « ce qu'on voit »
     * est plus simple à comprendre qu'un second jeu de critères.
     */
    private function requeteExportee(): Builder
    {
        return $this->getFilteredTableQuery() ?? Drawing::query();
    }

    private function exporterTableur(): void
    {
        $fichier = $this->fichierTemporaire('xlsx');

        $nombre = app(ExportTableur::class)->ecrire($this->requeteExportee(), $fichier);

        $this->livrer($fichier, $nombre);
    }

    private function exporterCatalogue(bool $avecNotes): void
    {
        // Deux cents reproductions à réduire puis à composer : c'est long, et
        // la limite de temps d'un serveur mutualisé est vite atteinte.
        set_time_limit(0);

        $fichier = $this->fichierTemporaire('pdf');

        $nombre = app(ExportCatalogue::class)->ecrire($this->requeteExportee(), $fichier, $avecNotes);

        $this->livrer($fichier, $nombre);
    }

    private function fichierTemporaire(string $extension): string
    {
        $dossier = storage_path('app/private/exports');

        if (! is_dir($dossier)) {
            mkdir($dossier, 0o700, true);
        }

        return $dossier.'/'.bin2hex(random_bytes(8)).'.'.$extension;
    }

    /**
     * Le fichier n'est pas renvoyé d'ici : on envoie le navigateur le
     * chercher sur une route signée, qui le diffuse en flux et l'efface.
     * Voir App\Http\Controllers\ExportFileController.
     */
    private function livrer(string $fichier, int $nombre): void
    {
        if ($nombre === 0) {
            @unlink($fichier);

            Notification::make()
                ->title('Rien à exporter')
                ->body('Aucune fiche ne correspond à ce que la liste affiche en ce moment.')
                ->warning()
                ->send();

            return;
        }

        $this->redirect(URL::temporarySignedRoute(
            'exports.telecharger',
            now()->addMinutes(10),
            ['fichier' => basename($fichier)],
        ));
    }

    /**
     * Les onglets reprennent la façon dont on travaille réellement : on
     * s'occupe d'abord de ce qui reste à décrire.
     */
    public function getTabs(): array
    {
        return [
            'a_decrire' => Tab::make('À décrire')
                ->icon('heroicon-o-inbox')
                ->badge(Drawing::where('status', DrawingStatus::ToDescribe)->count() ?: null)
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', DrawingStatus::ToDescribe)),

            'en_cours' => Tab::make('En cours')
                ->icon('heroicon-o-pencil')
                ->badge(Drawing::where('status', DrawingStatus::InProgress)->count() ?: null)
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', DrawingStatus::InProgress)),

            'decrits' => Tab::make('Décrits')
                ->icon('heroicon-o-check-circle')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', DrawingStatus::Described)),

            'tous' => Tab::make('Tous'),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        // S'il ne reste rien à décrire, on ouvre sur l'ensemble du fonds.
        return Drawing::where('status', DrawingStatus::ToDescribe)->exists() ? 'a_decrire' : 'tous';
    }
}
