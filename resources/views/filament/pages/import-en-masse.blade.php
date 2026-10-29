<x-filament-panels::page>
    <div
        x-data="{
            encours: false,
            async derouler() {
                this.encours = true;

                // Une requête par photographie : chacune reste courte, et la
                // barre de progression avance réellement.
                while (await $wire.suivant()) {
                    // La boucle s'arrête quand la file est vide.
                }

                this.encours = false;
            },
        }"
        x-on:import-demarre.window="derouler()"
        class="space-y-6"
    >
        {{ $this->form }}

        <div class="flex flex-wrap items-center gap-3">
            <x-filament::button
                size="lg"
                icon="heroicon-o-arrow-up-tray"
                wire:click="demarrer"
                x-bind:disabled="encours"
                wire:loading.attr="disabled"
            >
                Importer
            </x-filament::button>

            <span x-show="encours" class="text-base text-gray-600 dark:text-gray-400">
                Ne fermez pas cette page pendant l'import.
            </span>
        </div>

        @if ($total > 0)
            <x-filament::section heading="Progression">
                <div class="space-y-4">
                    <div class="flex items-center gap-4">
                        <div class="h-3 flex-1 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                            <div
                                class="h-full bg-primary-600 transition-all duration-300"
                                style="width: {{ $total > 0 ? round($traitees / $total * 100) : 0 }}%"
                            ></div>
                        </div>
                        <span class="whitespace-nowrap text-base font-medium">
                            {{ $traitees }} / {{ $total }}
                        </span>
                    </div>

                    @if ($echecs !== [])
                        <div class="rounded-lg bg-danger-50 p-4 text-sm text-danger-700 dark:bg-danger-500/10 dark:text-danger-400">
                            <p class="mb-2 font-medium">Fichiers non importés :</p>
                            <ul class="list-inside list-disc space-y-1">
                                @foreach ($echecs as $echec)
                                    <li>{{ $echec }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            </x-filament::section>
        @endif

        @if ($resultats !== [])
            <x-filament::section heading="Fiches créées">
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-4 lg:grid-cols-6">
                    @foreach (array_reverse($resultats) as $resultat)
                        <a href="{{ $resultat['lien'] }}" class="fi-filigrane-vignette block rounded-lg p-2 transition hover:opacity-80">
                            @if ($resultat['vignette'])
                                <img src="{{ $resultat['vignette'] }}" alt="" class="aspect-square w-full rounded object-cover">
                            @else
                                <div class="aspect-square w-full rounded bg-gray-100 dark:bg-gray-800"></div>
                            @endif
                            <span class="mt-2 block text-center text-sm font-medium">{{ $resultat['inventaire'] }}</span>
                        </a>
                    @endforeach
                </div>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
