@php
    /** @var \App\Models\Drawing|null $dessin */
    $photos = $dessin?->photos ?? collect();
    $principale = $dessin?->displayPhoto();
@endphp

<div
    x-data="{ plein: false, index: 0, photos: @js($photos->map(fn ($p) => [
        'web' => $p->webUrl(),
        'legende' => trim(($p->kind?->getLabel() ?? '').' '.($p->caption ?? '')),
    ])->values()) }"
    class="fi-section rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 lg:sticky lg:top-24 dark:bg-gray-900 dark:ring-white/10"
>
    @if ($principale)
        <button
            type="button"
            x-on:click="index = 0; plein = true"
            class="fi-filigrane-vignette block w-full overflow-hidden rounded-lg p-2"
            title="Agrandir la photographie"
        >
            <img
                src="{{ $principale->webUrl() }}"
                alt="{{ $dessin->inventory_number }}"
                class="mx-auto max-h-[68vh] w-auto object-contain"
            >
        </button>

        @if ($photos->count() > 1)
            <div class="mt-3 grid grid-cols-4 gap-2">
                @foreach ($photos as $rang => $photo)
                    <button
                        type="button"
                        x-on:click="index = {{ $rang }}; plein = true"
                        class="fi-filigrane-vignette overflow-hidden rounded-md p-1"
                        title="{{ $photo->kind?->getLabel() }}"
                    >
                        <img src="{{ $photo->thumbUrl() }}" alt="" class="aspect-square w-full object-cover">
                    </button>
                @endforeach
            </div>
        @endif

        <dl class="mt-4 space-y-1.5 text-sm">
            @if ($dessin->dimensionsLabel())
                <div class="flex justify-between gap-3">
                    <dt class="text-gray-500 dark:text-gray-400">Dimensions</dt>
                    <dd class="text-right font-medium text-gray-950 dark:text-white">{{ $dessin->dimensionsLabel() }}</dd>
                </div>
            @endif

            @if ($principale->taken_at)
                <div class="flex justify-between gap-3">
                    <dt class="text-gray-500 dark:text-gray-400">Prise de vue</dt>
                    <dd class="text-right text-gray-950 dark:text-white">{{ $principale->taken_at->translatedFormat('j F Y') }}</dd>
                </div>
            @endif

            <div class="flex justify-between gap-3">
                <dt class="text-gray-500 dark:text-gray-400">Original</dt>
                <dd class="text-right">
                    <a
                        href="{{ $principale->originalUrl() }}"
                        target="_blank"
                        rel="noopener"
                        class="font-medium text-primary-600 hover:underline dark:text-primary-400"
                    >
                        Pleine résolution
                    </a>
                </dd>
            </div>
        </dl>
    @else
        <div class="py-10 text-center">
            <img src="{{ asset('icones/vignette-absente.svg') }}" alt="" class="mx-auto h-28 w-auto opacity-70">
            <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">
                {{ $dessin
                    ? 'Aucune photographie. Ajoutez-en une depuis la section « Photographies », plus bas sur cette page.'
                    : 'Enregistrez la fiche, puis ajoutez les photographies.' }}
            </p>
        </div>
    @endif

    {{-- Affichage plein écran, avec zoom au pincement sur iPhone. --}}
    <template x-teleport="body">
        <div
            x-show="plein"
            x-cloak
            x-on:keydown.escape.window="plein = false"
            x-on:keydown.arrow-right.window="if (plein) index = Math.min(index + 1, photos.length - 1)"
            x-on:keydown.arrow-left.window="if (plein) index = Math.max(index - 1, 0)"
            class="fixed inset-0 z-50 flex flex-col bg-black/95"
            x-transition.opacity
        >
            <div class="flex items-center justify-between gap-4 px-4 py-3 text-white">
                <span class="text-sm" x-text="photos[index]?.legende"></span>
                <button
                    type="button"
                    x-on:click="plein = false"
                    class="rounded-lg px-4 py-2 text-base font-medium ring-1 ring-white/30 hover:bg-white/10"
                >
                    Fermer
                </button>
            </div>

            <div class="flex-1 overflow-auto p-2" style="touch-action: pinch-zoom;">
                <img :src="photos[index]?.web" alt="" class="mx-auto max-h-full w-auto object-contain">
            </div>

            <div class="flex items-center justify-center gap-6 py-3 text-white" x-show="photos.length > 1">
                <button type="button" x-on:click="index = Math.max(index - 1, 0)" class="rounded-lg px-5 py-2 ring-1 ring-white/30 hover:bg-white/10">Précédente</button>
                <span class="text-sm" x-text="(index + 1) + ' / ' + photos.length"></span>
                <button type="button" x-on:click="index = Math.min(index + 1, photos.length - 1)" class="rounded-lg px-5 py-2 ring-1 ring-white/30 hover:bg-white/10">Suivante</button>
            </div>
        </div>
    </template>
</div>
