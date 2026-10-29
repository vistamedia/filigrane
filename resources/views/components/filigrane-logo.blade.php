@props(['class' => 'h-9'])

<span {{ $attributes->class(['flex items-center gap-2.5']) }}>
    {{-- logo.svg, et non icone.svg : le motif seul, sans tuile ni fond. Pas
         d'arrondi, donc : il n'y a pas de carré à arrondir.

         Deux fichiers, et non un seul avec du CSS : le dessin est servi par
         une balise <img>, qui n'hérite de rien de la page. Ce qui est « du
         papier » dans le logo doit reprendre la couleur de la surface, et
         cette couleur change avec le thème. La variante sombre est dérivée de
         la claire par scripts/generer-icones.mjs. --}}
    <img
        src="{{ asset('icones/logo.svg') }}"
        alt=""
        class="{{ $class }} w-auto dark:hidden"
    />
    <img
        src="{{ asset('icones/logo-sombre.svg') }}"
        alt=""
        class="{{ $class }} hidden w-auto dark:block"
    />

    <span class="fi-filigrane-wordmark text-xl font-semibold tracking-wide text-gray-950 dark:text-white">
        Filigrane
    </span>
</span>
