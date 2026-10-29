@props(['class' => 'h-9'])

<span {{ $attributes->class(['flex items-center gap-2.5']) }}>
    {{-- logo.svg, et non icone.svg : le motif seul, sans tuile ni fond, et
         repris dans le crème de l'interface. Pas d'arrondi, donc : il n'y a
         pas de carré à arrondir. --}}
    <img src="{{ asset('icones/logo.svg') }}" alt="" class="{{ $class }} w-auto" />
    <span class="fi-filigrane-wordmark text-xl font-semibold tracking-wide text-gray-950 dark:text-white">
        Filigrane
    </span>
</span>
