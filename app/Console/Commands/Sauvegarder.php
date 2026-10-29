<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

/**
 * Copie de la base dans ~/sauvegardes, avec rotation.
 *
 * Ce n'est pas une sauvegarde au sens strict : le fichier reste sur le même
 * serveur, donc il ne protège ni d'une panne de disque ni de la disparition de
 * l'hébergement. Il protège de ce qui arrive bien plus souvent : une fausse
 * manœuvre. La vraie copie hors site se fait depuis le Mac, avec
 * `make sauvegarde` (voir scripts/sauvegarder.sh).
 */
class Sauvegarder extends Command
{
    protected $signature = 'filigrane:sauvegarder
                            {--conserver=14 : Nombre de copies à garder}';

    protected $description = 'Enregistre une copie de la base de données';

    public function handle(): int
    {
        $dossier = dirname(base_path()).'/sauvegardes';

        if (! is_dir($dossier) && ! mkdir($dossier, 0o700, true)) {
            $this->components->error("Impossible de créer {$dossier}");

            return self::FAILURE;
        }

        $fichier = sprintf('%s/filigrane-%s.sql.gz', $dossier, now()->format('Y-m-d-His'));

        $connexion = config('database.default');
        $base = config("database.connections.{$connexion}");

        $resultat = Process::timeout(600)->run(sprintf(
            'mysqldump --host=%s --port=%s --user=%s --password=%s --single-transaction --quick --default-character-set=utf8mb4 %s | gzip -9 > %s',
            escapeshellarg($base['host']),
            escapeshellarg((string) $base['port']),
            escapeshellarg($base['username']),
            escapeshellarg($base['password']),
            escapeshellarg($base['database']),
            escapeshellarg($fichier),
        ));

        if (! $resultat->successful() || ! file_exists($fichier) || filesize($fichier) < 1024) {
            @unlink($fichier);
            $this->components->error('La sauvegarde a échoué : '.trim($resultat->errorOutput()));

            return self::FAILURE;
        }

        $this->components->info(sprintf(
            'Sauvegarde écrite : %s (%s)',
            basename($fichier),
            $this->poids(filesize($fichier)),
        ));

        $this->rotation($dossier, (int) $this->option('conserver'));

        // Repère utile : une sauvegarde doit être proportionnée au fonds.
        $this->components->info(sprintf(
            '%d fiche(s), %d photographie(s), %s de photographies.',
            \App\Models\Drawing::withTrashed()->count(),
            \App\Models\Photo::count(),
            $this->poids($this->poidsDesPhotos()),
        ));

        return self::SUCCESS;
    }

    private function rotation(string $dossier, int $conserver): void
    {
        $copies = glob($dossier.'/filigrane-*.sql.gz') ?: [];

        if (count($copies) <= $conserver) {
            return;
        }

        rsort($copies);

        foreach (array_slice($copies, $conserver) as $ancienne) {
            unlink($ancienne);
            $this->line('  Supprimée : '.basename($ancienne));
        }
    }

    private function poidsDesPhotos(): int
    {
        $total = 0;

        foreach (['local', 'public'] as $disque) {
            $stockage = Storage::disk($disque);

            foreach ($stockage->allFiles('photos') as $chemin) {
                $total += $stockage->size($chemin);
            }
        }

        return $total;
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
