<?php

use Illuminate\Support\Facades\Schedule;

/*
| Tâches périodiques.
|
| Une seule ligne de cron suffit à les déclencher toutes, chez l'hébergeur :
|
|   * * * * * cd ~/apps/filigrane && /usr/local/bin/php artisan schedule:run >/dev/null 2>&1
|
| Voir DOCS/06-deploiement.md.
*/

// Copie de la base, conservée quinze jours sur le serveur.
Schedule::command('filigrane:sauvegarder')
    ->dailyAt('04:10')
    ->withoutOverlapping()
    ->onFailure(fn () => logger()->error('Filigrane : la sauvegarde quotidienne a échoué.'));

// Fichiers d'envoi interrompus et images sans fiche.
Schedule::command('filigrane:menage')
    ->dailyAt('04:40')
    ->withoutOverlapping();

// Sessions expirées et jetons de réinitialisation périmés.
Schedule::command('auth:clear-resets')->weekly();
