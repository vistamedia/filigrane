@php
    /*
     * Page publique de présentation. Elle ne montre rien de la collection :
     * ni fiche, ni photographie, ni nom de personne. On peut donc la donner
     * à lire sans rien exposer.
     *
     * Les coordonnées tiennent dans ce seul tableau.
     */
    $auteur = [
        'nom' => 'Emmanuel Danan',
        'site' => 'https://dananlab.fr',
        'siteAffiche' => 'dananlab.fr',
    ];

    $apercus = collect([
        ['fichier' => 'apercus/dessins.jpg', 'legende' => 'La planche de vignettes : la collection d\'un coup d\'œil.'],
        ['fichier' => 'apercus/fiche.jpg', 'legende' => 'La fiche : la photographie à gauche, la description à droite.'],
        ['fichier' => 'apercus/diaporama.jpg', 'legende' => 'Le diaporama : un dessin en grand, son commentaire dessous.'],
    ])->filter(fn (array $apercu): bool => file_exists(public_path($apercu['fichier'])));

    $pile = [
        'Laravel 13' => 'Socle applicatif',
        'Filament 5' => 'Interface de catalogage',
        'MariaDB 11.4' => 'Base de données',
        'Imagick' => 'Traitement des images, HEIC des iPhone compris',
        'PWA' => 'Installable sur Mac et sur iPhone',
        'Playwright' => '70 tests de bout en bout',
        'Docker' => 'Environnement de développement identique à la production',
    ];
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Filigrane — catalogue d'une collection de dessins anciens</title>
    <meta name="description" content="Application de catalogage d'une collection privée de dessins anciens, conçue pour une utilisatrice senior : photographier, décrire et mesurer deux cent cinquante feuilles.">

    <link rel="icon" type="image/svg+xml" href="/icones/favicon.svg">
    <link rel="apple-touch-icon" href="/icones/icone-180x180.png">
    <meta name="theme-color" content="#FBF8F2">

    <meta property="og:type" content="website">
    <meta property="og:title" content="Filigrane">
    <meta property="og:description" content="Catalogue d'une collection privée de dessins anciens, conçu pour sa propriétaire.">
    <meta property="og:image" content="{{ url('/icones/icone-512x512.png') }}">

    @vite('resources/css/app.css')
</head>
<body class="antialiased">
    <div class="mx-auto max-w-3xl px-6 py-16 sm:py-24">

        {{-- ----------------------------------------------------------- Titre --}}
        <header class="text-center">
            <img
                src="/icones/icone-192x192.png"
                alt=""
                width="88"
                height="88"
                class="mx-auto h-22 w-22 rounded-[22%] shadow-sm"
            >

            <h1 class="mt-8 text-5xl font-semibold tracking-wide sm:text-6xl">Filigrane</h1>

            <p class="mt-4 text-xl text-gris dark:text-gris-clair">
                Catalogue d'une collection privée de dessins anciens.
            </p>

            <div class="mx-auto mt-10 w-24 border-t border-sanguine/40"></div>
        </header>

        {{-- ------------------------------------------------------- Le point de départ --}}
        <section class="mt-14 text-lg leading-relaxed">
            <p>
                Deux cent cinquante dessins anciens dormaient dans des cartons
                et sur les murs. Leur propriétaire, <strong>Lise</strong>,
                ancienne chargée de mission au musée du Louvre, a plus de
                quatre-vingt-dix ans et l'œil intact. Elle sait ce qu'elle
                regarde ; il lui manquait un endroit où l'écrire.
            </p>

            <p class="mt-5">
                Filigrane est cet endroit. On photographie les feuilles, elles
                arrivent dans une file d'attente, et il ne reste plus qu'à les
                décrire, une par une, depuis un Mac ou un iPhone.
            </p>
        </section>

        {{-- ---------------------------------------------------------- Aperçus --}}
        @if ($apercus->isNotEmpty())
            <section class="mt-16 space-y-10">
                @foreach ($apercus as $apercu)
                    <figure>
                        <img
                            src="/{{ $apercu['fichier'] }}"
                            alt=""
                            loading="lazy"
                            class="w-full rounded-xl border border-encre/10 shadow-sm dark:border-papier/10"
                        >
                        <figcaption class="mt-3 text-center text-base text-gris dark:text-gris-clair">
                            {{ $apercu['legende'] }}
                        </figcaption>
                    </figure>
                @endforeach

                <p class="text-center text-base text-gris dark:text-gris-clair">
                    Ces aperçus sont pris sur un jeu de démonstration : aucun
                    dessin de la collection n'y figure.
                </p>
            </section>
        @endif

        {{-- ------------------------------------------------------ Trois problèmes --}}
        <section class="mt-20 space-y-14">
            <article>
                <h2 class="text-2xl font-semibold">Mesurer un dessin sans se mettre à genoux</h2>

                <p class="mt-4 text-lg leading-relaxed">
                    La demande était nette : les dimensions devaient se calculer
                    depuis la photographie. Il n'était pas question de demander
                    à Lise de s'agenouiller avec un mètre.
                </p>

                <p class="mt-4 text-lg leading-relaxed">
                    Une photographie seule ne suffit pourtant jamais : il y
                    manque toujours soit la distance au sujet, soit une longueur
                    connue dans le cadre. Filigrane règle la question là où elle
                    coûte le moins. Une règle est posée à côté du
                    <em>premier</em> dessin d'une séance ; on trace le segment à
                    l'écran, une fois ; l'échelle obtenue — en pixels par
                    millimètre — devient un profil, et s'applique seule à toutes
                    les photographies prises ensuite dans les mêmes conditions.
                    Le contour de la feuille, lui, est détecté automatiquement
                    sur le fond uni.
                </p>

                <p class="mt-4 text-lg leading-relaxed">
                    Mesure de contrôle sur une feuille de 300 × 400 mm :
                    <strong>301 × 401 mm</strong>. Trois millièmes d'erreur, et
                    pas un geste demandé à la personne qui décrit.
                </p>
            </article>

            <article>
                <h2 class="text-2xl font-semibold">Deux cents photographies sur un hébergement mutualisé</h2>

                <p class="mt-4 text-lg leading-relaxed">
                    Pas de file d'attente, pas de processus résident : un
                    mutualisé n'en offre pas. L'import en masse envoie donc
                    <em>une requête HTTP par image</em>, enchaînées depuis le
                    navigateur. Chaque requête reste courte, aucune ne dépasse
                    la limite de temps du serveur, et la barre de progression
                    avance pour une raison réelle — une image traitée — plutôt
                    que sur une estimation.
                </p>
            </article>

            <article>
                <h2 class="text-2xl font-semibold">Conçu pour celle qui s'en sert</h2>

                <p class="mt-4 text-lg leading-relaxed">
                    Corps de texte à 17 pixels plutôt que 16. Cibles tactiles de
                    44 pixels au minimum. Contraste mesuré à 6,8 : 1 sur le
                    bouton principal, là où la norme en demande 4,5. Et des
                    libellés qui disent exactement ce qu'ils font :
                    « Mettre à la corbeille » quand la fiche est récupérable,
                    « Supprimer définitivement » quand elle ne l'est plus.
                </p>

                <p class="mt-4 text-lg leading-relaxed">
                    Ces valeurs ne sont pas des intentions : un test automatisé
                    échoue si le contraste passe sous le seuil, un autre si un
                    bouton devient trop petit pour le doigt.
                </p>
            </article>

            <article>
                <h2 class="text-2xl font-semibold">Et pour finir, regarder</h2>

                <p class="mt-4 text-lg leading-relaxed">
                    Un catalogue ne sert à rien si on ne peut pas le parcourir.
                    Filigrane propose un diaporama — un dessin en grand sur fond
                    sombre, son commentaire dessous — et deux exports : un
                    tableur pour trier et compter, un catalogue PDF d'une page
                    par dessin, reproduction comprise, pour imprimer ou
                    transmettre.
                </p>
            </article>
        </section>

        {{-- ----------------------------------------------------------- La pile --}}
        <section class="mt-20">
            <h2 class="text-2xl font-semibold">Sous le capot</h2>

            <dl class="mt-6 divide-y divide-encre/10 dark:divide-papier/10">
                @foreach ($pile as $outil => $role)
                    <div class="flex flex-wrap items-baseline gap-x-4 py-3">
                        <dt class="w-40 shrink-0 font-semibold">{{ $outil }}</dt>
                        <dd class="flex-1 text-gris dark:text-gris-clair">{{ $role }}</dd>
                    </div>
                @endforeach
            </dl>

            <p class="mt-6 text-base leading-relaxed text-gris dark:text-gris-clair">
                Les photographies d'origine ne sortent jamais du dossier privé :
                elles transitent par une route authentifiée, et seules les
                versions réduites sont servies en statique. Le code applicatif
                vit hors de la racine web.
            </p>
        </section>

        {{-- ------------------------------------------------------------ Signature --}}
        <footer class="mt-20 border-t border-encre/10 pt-10 text-center dark:border-papier/10">
            <p class="text-lg">
                Conception et développement&nbsp;:
                <a
                    href="{{ $auteur['site'] }}"
                    class="font-semibold text-sanguine underline decoration-sanguine/40 underline-offset-4 hover:decoration-sanguine"
                >{{ $auteur['nom'] }}</a>
            </p>

            <p class="mt-2 text-base text-gris dark:text-gris-clair">
                {{ $auteur['siteAffiche'] }}
            </p>

            <p class="mt-8 text-base">
                <a
                    href="{{ url('/') }}"
                    class="text-gris underline decoration-gris/30 underline-offset-4 hover:text-sanguine dark:text-gris-clair"
                >Accéder à l'application</a>
            </p>
        </footer>
    </div>
</body>
</html>
