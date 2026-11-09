@php
    $ratio = $this->photo->width_px / max(1, $this->photo->height_px);
@endphp

<x-filament-panels::page>
    @if (! $this->photo->rawWebUrl())
        <x-filament::section>
            <p class="text-base">
                Cette photographie n'a pas encore été préparée. Patientez quelques
                instants puis rechargez la page.
            </p>
        </x-filament::section>
    @else
        <div
            x-data="outilSeparation({
                detectes: @js($this->detectes),
                numero: @js($this->photo->drawing->inventory_number),
            })"
            class="grid gap-6 lg:grid-cols-3"
        >
            <div class="lg:col-span-2">
                <div class="fi-filigrane-vignette rounded-xl">
                    {{-- Comme pour la retouche, la scène épouse l'image : les
                         cadres se rapportent à la photographie. --}}
                    <div
                        x-ref="scene"
                        class="relative mx-auto select-none"
                        style="touch-action: none; aspect-ratio: {{ max(1, (int) $this->photo->width_px) }} / {{ max(1, (int) $this->photo->height_px) }}; max-width: min(100%, calc(75vh * {{ $ratio }}));"
                        x-on:pointermove="deplacer($event)"
                        x-on:pointerup.window="prise = null"
                        x-on:pointercancel.window="prise = null"
                    >
                        <img src="{{ $this->photo->rawWebUrl() }}" alt="" class="block w-full" draggable="false">

                        <template x-for="(cadre, i) in cadres" :key="i">
                            <div
                                data-cadre
                                class="absolute cursor-move border-2 border-blue-600 bg-blue-600/10"
                                :style="`left:${cadre.x * 100}%; top:${cadre.y * 100}%; width:${cadre.w * 100}%; height:${cadre.h * 100}%`"
                                x-on:pointerdown="saisir($event, i, 'deplacer')"
                            >
                                {{-- Le numéro au milieu, à l'écart des poignées : c'est
                                     l'ordre des futures fiches. --}}
                                <span
                                    class="pointer-events-none absolute left-1/2 top-1/2 flex h-8 min-w-8 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full bg-blue-600/90 px-2 text-base font-semibold text-white shadow"
                                    x-text="rang(i)"
                                ></span>

                                <button
                                    type="button"
                                    class="absolute right-3 top-3 flex h-7 w-7 items-center justify-center rounded-full bg-white text-base font-semibold text-gray-900 shadow"
                                    :aria-label="`Retirer le dessin ${rang(i)}`"
                                    :title="`Retirer le dessin ${rang(i)}`"
                                    x-on:pointerdown.stop
                                    x-on:click="retirer(i)"
                                >×</button>

                                {{-- Les quatre coins. Plus petits que ceux de la
                                     retouche : une planche en compte des dizaines. --}}
                                <template x-for="coin in ['hg', 'hd', 'bd', 'bg']" :key="coin">
                                    <span
                                        role="button"
                                        :aria-label="`${nomsDesCoins[coin]} du dessin ${rang(i)}`"
                                        class="absolute h-5 w-5 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white bg-blue-600 shadow"
                                        :class="coin === 'hg' || coin === 'bd' ? 'cursor-nwse-resize' : 'cursor-nesw-resize'"
                                        :style="`left:${coin.endsWith('d') ? 100 : 0}%; top:${coin.startsWith('b') ? 100 : 0}%`"
                                        x-on:pointerdown.stop="saisir($event, i, coin)"
                                    ></span>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>

                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">
                    Faites glisser un coin pour ajuster un cadre, ou le cadre entier pour
                    le déplacer. Gardez un peu de marge autour de chaque dessin : vous le
                    redresserez ensuite, avec la retouche.
                </p>
            </div>

            <div class="space-y-5">
                <x-filament::section heading="Dessins">
                    <p class="text-base" x-text="resume()"></p>

                    <div class="mt-4 grid grid-cols-2 gap-3">
                        <x-filament::button color="gray" icon="heroicon-o-plus" x-on:click="ajouter()">
                            Ajouter un dessin
                        </x-filament::button>
                        <x-filament::button color="gray" icon="heroicon-o-arrow-path" x-on:click="reperer()">
                            Repérer à nouveau
                        </x-filament::button>
                    </div>
                </x-filament::section>

                <x-filament::section heading="Ce qui va se passer">
                    <ul class="list-disc space-y-1 pl-5 text-sm text-gray-600 dark:text-gray-300">
                        <li>Chaque cadre devient une fiche « à décrire », avec sa photographie à pleine définition.</li>
                        <li>{{ $this->photo->drawing->inventory_number }} garde le dessin n° 1 ; les autres prennent les numéros suivants.</li>
                        <li>L'artiste, la période, l'emplacement et les mots-clés de {{ $this->photo->drawing->inventory_number }} sont recopiés.</li>
                        <li>La photographie d'ensemble est ensuite supprimée.</li>
                    </ul>
                </x-filament::section>

                <div class="flex flex-col gap-3">
                    <x-filament::button
                        size="lg"
                        icon="heroicon-o-squares-2x2"
                        x-bind:disabled="cadres.length < 2 || enCours"
                        x-on:click="creer()"
                    >
                        <span x-show="!enCours" x-text="`Créer ${cadres.length} fiches`"></span>
                        <span x-show="enCours" x-cloak>Découpe en cours…</span>
                    </x-filament::button>

                    <x-filament::button
                        size="lg"
                        color="gray"
                        tag="a"
                        href="{{ \App\Filament\Resources\Drawings\DrawingResource::getUrl('edit', ['record' => $this->photo->drawing]) }}"
                    >
                        Annuler
                    </x-filament::button>
                </div>
            </div>
        </div>

        @script
        <script>
            Alpine.data('outilSeparation', (config) => ({
                detectes: config.detectes,
                numero: config.numero,
                cadres: config.detectes.map((c) => ({ ...c })),
                prise: null,
                enCours: false,

                nomsDesCoins: {
                    hg: 'Coin supérieur gauche',
                    hd: 'Coin supérieur droit',
                    bd: 'Coin inférieur droit',
                    bg: 'Coin inférieur gauche',
                },

                resume() {
                    const n = this.cadres.length;

                    if (n === 0) return 'Aucun dessin repéré : ajoutez un cadre par dessin.';
                    if (n === 1) return 'Un seul dessin : ajoutez au moins un autre cadre.';

                    return `${n} dessins, ${n} fiches.`;
                },

                /**
                 * Le numéro d'un cadre dans l'ordre de la lecture : rangée par
                 * rangée, de gauche à droite. Même règle que
                 * DetecteurDeDessins::ordreDeLecture(), qui décide côté serveur.
                 */
                rang(i) {
                    const ordre = this.cadres
                        .map((c, j) => ({ ...c, j }))
                        .sort((a, b) => a.y - b.y);

                    const rangees = [];
                    for (const c of ordre) {
                        const derniere = rangees[rangees.length - 1];
                        const centre = c.y + c.h / 2;

                        if (derniere && centre >= derniere[0].y && centre <= derniere[0].y + derniere[0].h) {
                            derniere.push(c);
                        } else {
                            rangees.push([c]);
                        }
                    }

                    const lecture = rangees.flatMap((r) => r.sort((a, b) => a.x - b.x));

                    return lecture.findIndex((c) => c.j === i) + 1;
                },

                saisir(event, i, mode) {
                    event.preventDefault();
                    const rect = this.$refs.scene.getBoundingClientRect();

                    this.prise = {
                        i,
                        mode,
                        x: (event.clientX - rect.left) / rect.width,
                        y: (event.clientY - rect.top) / rect.height,
                        cadre: { ...this.cadres[i] },
                    };
                    event.target.setPointerCapture?.(event.pointerId);
                },

                deplacer(event) {
                    if (this.prise === null) return;

                    const rect = this.$refs.scene.getBoundingClientRect();
                    const borne = (v, min = 0, max = 1) => Math.min(max, Math.max(min, v));
                    const px = borne((event.clientX - rect.left) / rect.width);
                    const py = borne((event.clientY - rect.top) / rect.height);

                    const { i, mode, x, y, cadre: c } = this.prise;
                    const minimum = 0.02;
                    let [gauche, haut, droite, bas] = [c.x, c.y, c.x + c.w, c.y + c.h];

                    if (mode === 'deplacer') {
                        gauche = borne(c.x + px - x, 0, 1 - c.w);
                        haut = borne(c.y + py - y, 0, 1 - c.h);
                        [droite, bas] = [gauche + c.w, haut + c.h];
                    } else {
                        if (mode.endsWith('g')) gauche = Math.min(px, droite - minimum);
                        if (mode.endsWith('d')) droite = Math.max(px, gauche + minimum);
                        if (mode.startsWith('h')) haut = Math.min(py, bas - minimum);
                        if (mode.startsWith('b')) bas = Math.max(py, haut + minimum);
                    }

                    this.cadres[i] = { x: gauche, y: haut, w: droite - gauche, h: bas - haut };
                },

                ajouter() {
                    this.cadres.push({ x: 0.4, y: 0.4, w: 0.2, h: 0.2 });
                },

                retirer(i) {
                    this.cadres.splice(i, 1);
                },

                reperer() {
                    this.cadres = this.detectes.map((c) => ({ ...c }));
                },

                async creer() {
                    const n = this.cadres.length;
                    if (n < 2 || this.enCours) return;

                    const question = `${n} fiches vont être créées : ${this.numero} garde le dessin n° 1, `
                        + 'les autres prendront les numéros suivants. La photographie d\'ensemble sera ensuite supprimée. Continuer ?';

                    if (! window.confirm(question)) return;

                    this.enCours = true;

                    try {
                        const arrondi = (v) => Number(v.toFixed(5));
                        await this.$wire.separer(this.cadres.map((c) => ({
                            x: arrondi(c.x), y: arrondi(c.y), w: arrondi(c.w), h: arrondi(c.h),
                        })));
                    } finally {
                        this.enCours = false;
                    }
                },
            }));
        </script>
        @endscript
    @endif
</x-filament-panels::page>
