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

    /** Clé de session : la liste des dessins telle qu'on l'a quittée. */
    private const LISTE = 'filigrane.liste-des-dessins';

    public function mount(int | string $record): void
    {
        parent::mount($record);

        $this->retenirLaListe();
    }

    /**
     * Onglet, filtres, recherche et page de la liste vivent dans son adresse.
     * On la retient quand on en vient, pour que « Retour à la liste » y
     * ramène, et non sur l'onglet par défaut. Les fiches enchaînées par
     * « Décrit — passer au suivant » ou une retouche ne l'effacent pas.
     */
    private function retenirLaListe(): void
    {
        $precedente = (string) request()->headers->get('referer');

        if (rtrim(strtok($precedente, '?#') ?: '', '/') === rtrim(DrawingResource::getUrl('index'), '/')) {
            session([self::LISTE => $precedente]);
        }
    }

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
            // Sur la tablette tenue en paysage, le menu latéral est replié :
            // sans ce bouton, il fallait passer par le menu pour retrouver
            // la liste. On enregistre d'abord : Safari sur iPad ne prévient
            // pas qu'une saisie va être perdue quand on quitte la page.
            Action::make('liste')
                ->label('Retour à la liste')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->action(function (): void {
                    $this->save(shouldRedirect: false);

                    $this->redirect(session(self::LISTE, DrawingResource::getUrl('index')));
                }),

            // Le geste le plus fréquent : « j'ai fini celui-là, au suivant ».
            // Sur une fiche où rien n'est écrit, on demande confirmation : un
            // second appui, donné trop vite, tombait sur la fiche suivante et
            // la déclarait décrite sans qu'on l'ait vue.
            Action::make('terminer')
                ->label('Décrit — passer au suivant')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (Drawing $record): bool => $record->status !== DrawingStatus::Described)
                ->requiresConfirmation()
                // Un titre de fenêtre suffit à Filament pour l'ouvrir : c'est
                // donc la fenêtre elle-même qu'on masque quand la fiche est
                // commencée.
                ->modalHidden(fn (): bool => ! $this->rienNEstEcrit())
                ->modalHeading(fn (Drawing $record): string => "{$record->inventory_number} n'a ni artiste ni description")
                ->modalDescription('La marquer comme décrite quand même ? Pour quitter la fiche sans la marquer, utilisez « Retour à la liste ».')
                ->modalSubmitActionLabel('Marquer décrite')
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

    /**
     * Ni artiste ni description dans le formulaire, tel qu'il est à l'écran.
     * Pendant la saisie, l'éditeur tient la description sous forme de
     * document (un tableau de nœuds), et non de HTML : on en lit le texte.
     */
    private function rienNEstEcrit(): bool
    {
        $description = $this->data['description'] ?? null;

        if (is_array($description)) {
            $texte = '';
            array_walk_recursive($description, function (mixed $valeur, string | int $cle) use (&$texte): void {
                $texte .= $cle === 'text' ? (string) $valeur : '';
            });
            $descriptionVide = trim($texte) === '';
        } else {
            $descriptionVide = TexteRiche::estVide($description);
        }

        return $descriptionVide && blank(trim((string) ($this->data['artist'] ?? '')));
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
