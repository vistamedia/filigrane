<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        /*
         * Où renvoyer un visiteur non connecté.
         *
         * Les routes hors panneau — les photographies d'origine, la remise
         * d'un export — sont protégées par le middleware « auth », qui
         * cherche par défaut une route nommée « login ». Filament nomme la
         * sienne « filament.admin.auth.login » : sans cette ligne, une
         * requête non authentifiée sur l'une de ces routes ne redirige pas,
         * elle tombe en erreur 500 (« Route [login] not defined »).
         *
         * Le panneau étant monté à la racine, son formulaire est en /login.
         */
        $middleware->redirectGuestsTo('/login');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
