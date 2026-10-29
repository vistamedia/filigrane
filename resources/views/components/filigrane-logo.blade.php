@props(['class' => 'h-9'])

<span {{ $attributes->class(['flex items-center gap-2.5']) }}>
    <img src="{{ asset('icones/icone.svg') }}" alt="" class="{{ $class }} w-auto rounded-lg" />
    <span class="fi-filigrane-wordmark text-xl font-semibold tracking-wide text-gray-950 dark:text-white">
        Filigrane
    </span>
</span>
