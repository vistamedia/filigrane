<?php

namespace App\Console\Commands;

use App\Models\Photo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Entretien quotidien : on enlève ce qui n'a plus de raison d'être.
 */
class Menage extends Command
{
    protected $signature = 'filigrane:menage {--simuler : N\'efface rien, se contente d\'annoncer}';

    protected $description = 'Supprime les fichiers temporaires et les images orphelines';

    public function handle(): int
    {
        $simulation = (bool) $this->option('simuler');

        $this->tempsDUpload($simulation);
        $this->exportsAbandonnes($simulation);
        $this->imagesOrphelines($simulation);

        return self::SUCCESS;
    }

    /**
     * Un export préparé puis jamais récupéré — fenêtre fermée, lien expiré —
     * reste sur le disque. Un catalogue PDF pèse plusieurs dizaines de
     * mégaoctets : on ne les laisse pas s'empiler.
     */
    private function exportsAbandonnes(bool $simulation): void
    {
        $disque = Storage::disk('local');
        $limite = now()->subHour()->getTimestamp();
        $efface = 0;
        $octets = 0;

        foreach ($disque->files('exports') as $fichier) {
            if ($disque->lastModified($fichier) >= $limite) {
                continue;
            }

            $octets += $disque->size($fichier);
            $simulation || $disque->delete($fichier);
            $efface++;
        }

        $this->components->info(sprintf(
            '%d export(s) abandonné(s) %s (%s)',
            $efface,
            $simulation ? 'à supprimer' : 'supprimé(s)',
            $this->poids($octets),
        ));
    }

    /**
     * Les envois interrompus laissent des fichiers dans livewire-tmp.
     */
    private function tempsDUpload(bool $simulation): void
    {
        $disque = Storage::disk('local');
        $limite = now()->subDay()->getTimestamp();
        $efface = 0;

        foreach ($disque->files('livewire-tmp') as $fichier) {
            if ($disque->lastModified($fichier) < $limite) {
                $simulation || $disque->delete($fichier);
                $efface++;
            }
        }

        $this->components->info(
            $simulation
                ? "{$efface} fichier(s) d'envoi à supprimer"
                : "{$efface} fichier(s) d'envoi supprimé(s)",
        );
    }

    /**
     * Images présentes sur le disque sans ligne correspondante en base.
     *
     * Cela ne devrait plus arriver — les observateurs s'en chargent — mais une
     * interruption au mauvais moment peut en laisser, et personne ne va
     * inspecter un dossier de stockage à la main.
     */
    private function imagesOrphelines(bool $simulation): void
    {
        $connus = [
            'local' => Photo::pluck('original_path')->filter()->flip(),
            'public' => Photo::pluck('web_path')->merge(Photo::pluck('thumb_path'))->filter()->flip(),
        ];

        $efface = 0;
        $octets = 0;

        foreach ($connus as $nomDuDisque => $chemins) {
            $disque = Storage::disk($nomDuDisque);

            foreach ($disque->allFiles('photos') as $fichier) {
                if ($chemins->has($fichier)) {
                    continue;
                }

                $octets += $disque->size($fichier);
                $simulation || $disque->delete($fichier);
                $efface++;
            }
        }

        $this->components->info(sprintf(
            '%d image(s) orpheline(s) %s (%s)',
            $efface,
            $simulation ? 'à supprimer' : 'supprimée(s)',
            $this->poids($octets),
        ));
    }

    private function poids(int $octets): string
    {
        foreach (['o', 'Ko', 'Mo', 'Go'] as $unite) {
            if ($octets < 1024) {
                return round($octets, 1).' '.$unite;
            }

            $octets /= 1024;
        }

        return round($octets, 1).' To';
    }
}
