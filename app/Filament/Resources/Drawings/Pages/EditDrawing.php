<?php

namespace App\Filament\Resources\Drawings\Pages;

use App\Enums\DrawingStatus;
use App\Filament\Pages\Diaporama;
use App\Filament\Resources\Drawings\DrawingResource;
use App\Models\Drawing;
use App\Support\TexteRiche;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditDrawing extends EditRecord
{
    protected static string $resource = DrawingResource::class;

    public function getTitle(): string
    {
        return $this->getRecord()->inventory_number;
    }

    public function getSubheading(): ?string
    {
        $dessin = $this->getRecord();

        return collect([$dessin->artist, $dessin->period, $dessin->dimensionsLabel()])
            ->filter()
            ->implode(' · ') ?: null;
    }

    protected function getHeaderActions(): array
    {
        return [
            // Le geste le plus fréquent : « j'ai fini celui-là, au suivant ».
            Action::make('terminer')
                ->label('Décrit — passer au suivant')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (Drawing $record): bool => $record->status !== DrawingStatus::Described)
                ->action(function (Drawing $record): void {
                    $this->save(shouldRedirect: false, shouldSendSavedNotification: false);

                    $record->update(['status' => DrawingStatus::Described]);

                    $suivant = Drawing::query()
                        ->where('status', DrawingStatus::ToDescribe)
                        ->orderBy('inventory_number')
                        ->first();

                    if (! $suivant) {
                        Notification::make()
                            ->title('Fiche enregistrée — plus rien en attente')
                            ->success()
                            ->send();

                        $this->redirect(DrawingResource::getUrl('index'));

                        return;
                    }

                    Notification::make()
                        ->title("Fiche enregistrée — au tour de {$suivant->inventory_number}")
                        ->success()
                        ->send();

                    $this->redirect(DrawingResource::getUrl('edit', ['record' => $suivant]));
                }),

            // Une fois la fiche décrite, on peut vouloir la revoir en grand.
            Action::make('diaporama')
                ->label('Voir en diaporama')
                ->icon('heroicon-o-play-circle')
                ->color('gray')
                ->url(fn (Drawing $record): string => Diaporama::getUrl().'?dessin='.$record->getKey())
                ->visible(fn (Drawing $record): bool => ! TexteRiche::estVide($record->description)
                    && $record->displayPhoto()?->webUrl() !== null),

            // Les trois actions de suppression disent exactement ce qu'elles
            // font. Une fenêtre intitulée « Supprimer » pour une mise à la
            // corbeille inquiète pour rien ; l'inverse serait pire.
            DeleteAction::make()
                ->label('Mettre à la corbeille')
                ->modalHeading(fn (Drawing $record): string => "Mettre {$record->inventory_number} à la corbeille")
                ->modalDescription('La fiche quitte la liste mais reste récupérable, avec ses photographies.')
                ->modalSubmitActionLabel('Mettre à la corbeille'),

            RestoreAction::make()
                ->label('Restaurer')
                ->modalHeading(fn (Drawing $record): string => "Sortir {$record->inventory_number} de la corbeille")
                ->modalDescription('La fiche réapparaîtra dans la collection.')
                ->modalSubmitActionLabel('Restaurer'),

            ForceDeleteAction::make()
                ->label('Supprimer définitivement')
                ->modalHeading(fn (Drawing $record): string => "Supprimer définitivement {$record->inventory_number}")
                ->modalDescription('La fiche et ses photographies, originaux compris, seront effacées sans retour possible.')
                ->modalSubmitActionLabel('Supprimer définitivement'),
        ];
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Fiche enregistrée';
    }

    /**
     * Écrire un artiste ou une description dans une fiche laissée « à
     * décrire » signifie qu'on a commencé : elle passe en cours, sans que
     * personne ait à y penser.
     *
     * Trois cas n'en sont pas :
     * - l'avancement vient d'être remis à « à décrire » à la main : le choix
     *   est délibéré, on le respecte ;
     * - la description ne contient que « <p></p> », ce que l'éditeur enregistre
     *   dès qu'on a cliqué dans le champ ;
     * - le texte était déjà là avant : la fiche a été remise à décrire, et un
     *   enregistrement sans rapport ne doit pas la refaire passer en cours.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $avant = $this->getRecord();
        $statut = $data['status'] ?? null;
        $statut = $statut instanceof DrawingStatus ? $statut->value : $statut;

        if ($statut !== DrawingStatus::ToDescribe->value || $avant->status !== DrawingStatus::ToDescribe) {
            return $data;
        }

        $description = TexteRiche::enTexte($data['description'] ?? null);
        $artiste = trim((string) ($data['artist'] ?? ''));

        $commence = ($description !== '' && $description !== TexteRiche::enTexte($avant->description))
            || ($artiste !== '' && $artiste !== trim((string) $avant->artist));

        if ($commence) {
            $data['status'] = DrawingStatus::InProgress->value;
        }

        return $data;
    }
}
