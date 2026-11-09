<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Drawings\DrawingResource;
use App\Models\Photo;
use App\Services\SeparationPhoto;
use App\Support\DetecteurDeDessins;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Throwable;

/**
 * Séparer une photographie de plusieurs dessins : un cadre par dessin,
 * proposé par la détection et ajusté à la main, puis une fiche par dessin
 * (D-45).
 */
class SeparerPhoto extends Page
{
    protected string $view = 'filament.pages.separer-photo';

    protected static ?string $slug = 'separer';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Séparer les dessins d\'une photographie';

    public Photo $photo;

    /** @var list<array{x: float, y: float, w: float, h: float}> */
    public array $detectes = [];

    public function mount(): void
    {
        $this->photo = Photo::with('drawing')->findOrFail(request()->query('photo'));

        // La détection travaille sur la version web d'avant retouche : elle
        // montre toute la photographie, dans le même sens que l'original.
        $chemin = $this->photo->raw_web_path ?? $this->photo->web_path;

        try {
            $this->detectes = $chemin
                ? DetecteurDeDessins::detecter(Storage::disk(config('filigrane.derivatives_disk'))->path($chemin))
                : [];
        } catch (Throwable $exception) {
            Log::warning('Détection des dessins impossible', ['photo' => $this->photo->id, 'erreur' => $exception->getMessage()]);
            $this->detectes = [];
        }
    }

    public function getHeading(): string
    {
        return 'Séparer les dessins de '.$this->photo->drawing->inventory_number;
    }

    public function getSubheading(): ?string
    {
        return 'Un cadre par dessin : chacun deviendra une fiche, avec sa propre photographie.';
    }

    public function separer(array $cadres): void
    {
        $validateur = validator(['cadres' => $cadres], [
            'cadres' => ['required', 'array', 'min:2', 'max:60'],
            'cadres.*.x' => ['required', 'numeric', 'between:0,1'],
            'cadres.*.y' => ['required', 'numeric', 'between:0,1'],
            'cadres.*.w' => ['required', 'numeric', 'between:0.005,1'],
            'cadres.*.h' => ['required', 'numeric', 'between:0.005,1'],
        ], [
            'cadres.min' => 'Il faut au moins deux dessins pour séparer une photographie.',
        ]);

        if ($validateur->fails()) {
            Notification::make()
                ->title('Séparation impossible')
                ->body($validateur->errors()->first())
                ->danger()
                ->send();

            return;
        }

        $fiche = $this->photo->drawing;

        try {
            $decoupes = app(SeparationPhoto::class)->separer($this->photo, $validateur->validated()['cadres']);
        } catch (InvalidArgumentException $exception) {
            Notification::make()->title('Séparation impossible')->body($exception->getMessage())->danger()->send();

            return;
        } catch (Throwable $exception) {
            Log::error('Séparation impossible', ['photo' => $this->photo->id, 'erreur' => $exception->getMessage()]);

            Notification::make()
                ->title('La séparation n\'a pas pu aller au bout')
                ->body('La photographie d\'ensemble est intacte. Les fiches déjà créées sont dans la liste des dessins.')
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        $numeros = collect($decoupes)->map(fn (Photo $p): string => $p->drawing->inventory_number);
        $suivants = $numeros->count() === 2
            ? "le second est {$numeros->last()}"
            : "les suivants vont de {$numeros->get(1)} à {$numeros->last()}";

        Notification::make()
            ->title(count($decoupes).' dessins, une fiche chacun')
            ->body("{$fiche->inventory_number} garde le premier ; {$suivants}.")
            ->actions([
                Action::make('redresser')
                    ->label('Les redresser à la chaîne')
                    ->url(RetoucherPhoto::getUrl(['photo' => $decoupes[0]->id])),
            ])
            ->success()
            ->persistent()
            ->send();

        $this->redirect(DrawingResource::getUrl('index').'?tab=tous');
    }
}
