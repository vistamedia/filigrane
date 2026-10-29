<x-filament-panels::page>
    @if ($planches === [])
        <x-filament::section>
            <div class="py-10 text-center">
                <x-filament::icon
                    icon="heroicon-o-play-circle"
                    class="mx-auto mb-4 h-12 w-12 text-gray-400"
                />
                <p class="text-lg font-medium">Aucun dessin n'est encore commenté</p>
                <p class="mt-2 text-base text-gray-600 dark:text-gray-400">
                    Le diaporama reprend les fiches qui portent une description
                    et au moins une photographie. Décrivez-en une, elle
                    apparaîtra ici.
                </p>
                <div class="mt-6">
                    <x-filament::button
                        tag="a"
                        href="{{ \App\Filament\Resources\Drawings\DrawingResource::getUrl('index') }}"
                        icon="heroicon-o-rectangle-stack"
                    >
                        Aller aux dessins
                    </x-filament::button>
                </div>
            </div>
        </x-filament::section>
    @else
        <script>
            /**
             * Le feuilletage se fait entièrement dans le navigateur : les
             * planches sont déjà là, seules les images se chargent à la
             * demande — la suivante et la précédente d'abord, pour que le
             * passage d'un dessin à l'autre soit immédiat.
             */
            window.filigraneDiaporama = function (planches, depart) {
                return {
                    planches: planches,
                    index: depart,
                    lecture: false,
                    minuterie: null,
                    delai: 8000,
                    plein: false,
                    departToucher: null,

                    get planche() {
                        return this.planches[this.index] || null;
                    },

                    get total() {
                        return this.planches.length;
                    },

                    init() {
                        this.precharger();

                        document.addEventListener('fullscreenchange', () => {
                            this.plein = document.fullscreenElement !== null;
                        });
                    },

                    aller(rang) {
                        if (this.total === 0) {
                            return;
                        }

                        this.index = (rang + this.total) % this.total;
                        this.precharger();
                    },

                    suivant() {
                        this.aller(this.index + 1);
                    },

                    precedent() {
                        this.aller(this.index - 1);
                    },

                    precharger() {
                        [this.index + 1, this.index - 1].forEach((rang) => {
                            const voisine = this.planches[(rang + this.total) % this.total];

                            if (voisine && voisine.image) {
                                const image = new Image();
                                image.src = voisine.image;
                            }
                        });
                    },

                    basculerLecture() {
                        this.lecture = ! this.lecture;
                        clearInterval(this.minuterie);

                        if (this.lecture) {
                            this.minuterie = setInterval(() => this.suivant(), this.delai);
                        }
                    },

                    basculerPlein() {
                        if (document.fullscreenElement) {
                            document.exitFullscreen();
                        } else if (this.$root.requestFullscreen) {
                            this.$root.requestFullscreen();
                        }
                    },

                    touche(evenement) {
                        const gestes = {
                            ArrowRight: 'suivant',
                            ArrowLeft: 'precedent',
                            PageDown: 'suivant',
                            PageUp: 'precedent',
                        };

                        if (gestes[evenement.key]) {
                            evenement.preventDefault();
                            this[gestes[evenement.key]]();
                        } else if (evenement.key === ' ') {
                            evenement.preventDefault();
                            this.basculerLecture();
                        }
                    },

                    // Balayage du doigt : c'est le geste attendu sur un iPhone.
                    toucherDebut(evenement) {
                        this.departToucher = evenement.changedTouches[0].clientX;
                    },

                    toucherFin(evenement) {
                        if (this.departToucher === null) {
                            return;
                        }

                        const course = evenement.changedTouches[0].clientX - this.departToucher;
                        this.departToucher = null;

                        if (Math.abs(course) > 50) {
                            course < 0 ? this.suivant() : this.precedent();
                        }
                    },
                };
            };
        </script>

        <div
            x-data="window.filigraneDiaporama(@js($planches), {{ $depart }})"
            x-on:keydown.window="touche($event)"
            class="fi-filigrane-diaporama space-y-4"
        >
            {{-- La scène : fond sombre, parce qu'une reproduction en noir et
                 blanc se lit mieux détachée du papier de l'interface. --}}
            <div
                class="fi-filigrane-scene relative flex items-center justify-center overflow-hidden rounded-xl bg-stone-900"
                x-on:touchstart="toucherDebut($event)"
                x-on:touchend="toucherFin($event)"
            >
                <template x-if="planche">
                    <img
                        :src="planche.image"
                        :alt="'Dessin ' + planche.cote"
                        class="max-h-full max-w-full object-contain"
                    >
                </template>

                <button
                    type="button"
                    x-on:click="precedent()"
                    class="fi-filigrane-fleche start-0"
                    aria-label="Dessin précédent"
                >
                    <x-filament::icon icon="heroicon-o-chevron-left" class="h-7 w-7" />
                </button>

                <button
                    type="button"
                    x-on:click="suivant()"
                    class="fi-filigrane-fleche end-0"
                    aria-label="Dessin suivant"
                >
                    <x-filament::icon icon="heroicon-o-chevron-right" class="h-7 w-7" />
                </button>
            </div>

            {{-- La barre de conduite, entre l'image et son commentaire. --}}
            <div class="flex flex-wrap items-center justify-center gap-2 sm:justify-between">
                <div class="flex items-center gap-2">
                    <x-filament::icon-button
                        icon="heroicon-o-chevron-left"
                        label="Dessin précédent"
                        color="gray"
                        size="lg"
                        x-on:click="precedent()"
                    />

                    <span class="fi-filigrane-compteur px-2 tabular-nums">
                        <span x-text="index + 1"></span> / <span x-text="total"></span>
                    </span>

                    <x-filament::icon-button
                        icon="heroicon-o-chevron-right"
                        label="Dessin suivant"
                        color="gray"
                        size="lg"
                        x-on:click="suivant()"
                    />
                </div>

                <div class="flex items-center gap-2">
                    {{-- Deux boutons plutôt qu'un seul dont l'icône change :
                         l'icône est rendue côté serveur, elle ne peut pas
                         basculer dans le navigateur. --}}
                    <x-filament::button
                        color="gray"
                        size="lg"
                        icon="heroicon-o-play"
                        x-show="! lecture"
                        x-on:click="basculerLecture()"
                    >
                        Faire défiler
                    </x-filament::button>

                    <x-filament::button
                        color="gray"
                        size="lg"
                        icon="heroicon-o-pause"
                        x-show="lecture"
                        x-cloak
                        x-on:click="basculerLecture()"
                    >
                        Arrêter le défilement
                    </x-filament::button>

                    <x-filament::button
                        color="gray"
                        size="lg"
                        icon="heroicon-o-arrows-pointing-out"
                        x-on:click="basculerPlein()"
                    >
                        <span x-text="plein ? 'Quitter le plein écran' : 'Plein écran'"></span>
                    </x-filament::button>

                    <x-filament::button
                        tag="a"
                        color="gray"
                        size="lg"
                        icon="heroicon-o-pencil-square"
                        x-bind:href="planche ? planche.lien : '#'"
                    >
                        Ouvrir la fiche
                    </x-filament::button>
                </div>
            </div>

            {{-- Le commentaire, dessous, dans la largeur d'une colonne de
                 lecture : au-delà d'une soixantaine de signes par ligne, l'œil
                 perd la ligne suivante. --}}
            <template x-if="planche">
                <div class="fi-filigrane-cartouche">
                    <p class="fi-filigrane-cote text-sm text-gray-500 dark:text-gray-400" x-text="planche.cote"></p>

                    <h2 class="fi-filigrane-titre" x-text="planche.artiste || 'Artiste non identifié'"></h2>

                    <p class="mt-1 text-base text-gray-600 dark:text-gray-400">
                        <span x-text="planche.periode"></span>
                        <template x-if="planche.periode && planche.dimensions"><span> · </span></template>
                        <span x-text="planche.dimensions"></span>
                    </p>

                    <div class="fi-filigrane-prose mt-5" x-html="planche.description"></div>

                    <template x-if="planche.note">
                        <div class="fi-filigrane-note mt-6">
                            <p class="fi-filigrane-intertitre">Note</p>
                            <div class="fi-filigrane-prose" x-html="planche.note"></div>
                        </div>
                    </template>

                    <template x-if="planche.motsCles.length">
                        <ul class="mt-6 flex flex-wrap gap-2">
                            <template x-for="mot in planche.motsCles" :key="mot">
                                <li class="fi-filigrane-mot" x-text="mot"></li>
                            </template>
                        </ul>
                    </template>
                </div>
            </template>
        </div>
    @endif
</x-filament-panels::page>
