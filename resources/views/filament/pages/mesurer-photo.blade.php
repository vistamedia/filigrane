@php
    $definition = $this->definition();
    $references = $this->references();
@endphp

<x-filament-panels::page>
    @if (! $this->urlPhoto())
        <x-filament::section>
            <p class="text-base">
                Cette photographie n'a pas encore été préparée. Patientez quelques
                instants puis rechargez la page.
            </p>
        </x-filament::section>
    @else
        <div
            x-data="outilMesure({
                largeurPx: {{ $definition['width'] }},
                hauteurPx: {{ $definition['height'] }},
                cadre: @js($this->cadreInitial),
                references: @js($references),
                echelleExistante: {{ $this->photo->px_per_mm ?? 'null' }},
            })"
            class="grid gap-6 lg:grid-cols-3"
        >
            {{-- Scène : la photo, le segment de référence et le cadre de la feuille. --}}
            <div class="lg:col-span-2">
                <div
                    x-ref="scene"
                    class="fi-filigrane-vignette relative select-none overflow-hidden rounded-xl"
                    style="touch-action: none;"
                    x-on:pointermove="deplacer($event)"
                    x-on:pointerup.window="prise = null"
                    x-on:pointercancel.window="prise = null"
                >
                    <img
                        src="{{ $this->urlPhoto() }}"
                        alt=""
                        class="block w-full"
                        draggable="false"
                    >

                    <svg class="pointer-events-none absolute inset-0 h-full w-full" aria-hidden="true">
                        {{-- Cadre de la feuille --}}
                        <rect
                            :x="pc(Math.min(boite.x1, boite.x2))"
                            :y="pc(Math.min(boite.y1, boite.y2))"
                            :width="pc(Math.abs(boite.x2 - boite.x1))"
                            :height="pc(Math.abs(boite.y2 - boite.y1))"
                            fill="rgba(37, 99, 235, 0.08)"
                            stroke="#2563EB"
                            stroke-width="2"
                            stroke-dasharray="9 7"
                        />
                        {{-- Segment de référence --}}
                        <line
                            :x1="pc(segment.x1)" :y1="pc(segment.y1)"
                            :x2="pc(segment.x2)" :y2="pc(segment.y2)"
                            stroke="#A3553E" stroke-width="3" stroke-linecap="round"
                        />
                    </svg>

                    {{-- Poignées : grandes, pour être attrapables au doigt. --}}
                    <template x-for="poignee in poignees" :key="poignee.cle">
                        <button
                            type="button"
                            x-on:pointerdown="saisir($event, poignee.cle)"
                            class="absolute flex h-11 w-11 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full border-2 border-white shadow-lg"
                            :class="poignee.couleur"
                            :style="`left:${position(poignee.cle).x * 100}%; top:${position(poignee.cle).y * 100}%`"
                            :aria-label="poignee.titre"
                            :title="poignee.titre"
                        >
                            <span class="h-2.5 w-2.5 rounded-full bg-white"></span>
                        </button>
                    </template>
                </div>

                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">
                    Faites glisser les deux pastilles <span class="font-medium text-[#A3553E]">rouges</span>
                    aux extrémités de votre objet de référence, et les deux pastilles
                    <span class="font-medium text-blue-600">bleues</span> aux coins opposés de la feuille.
                </p>
            </div>

            {{-- Réglages et résultat. --}}
            <div class="space-y-5">
                <x-filament::section heading="Référence de longueur">
                    <div class="space-y-4">
                        <div>
                            <label for="reference" class="fi-fo-field-wrp-label mb-1 block text-sm font-medium">Objet photographié à côté du dessin</label>
                            <select
                                id="reference"
                                x-model="referenceIndex"
                                class="fi-input block w-full rounded-lg border-none bg-white px-3 py-2 text-base shadow-sm ring-1 ring-gray-950/10 dark:bg-white/5 dark:text-white dark:ring-white/20"
                            >
                                <template x-for="(ref, i) in references" :key="i">
                                    <option :value="i" x-text="ref.label + ' — ' + ref.mm.toLocaleString('fr-FR') + ' mm'"></option>
                                </template>
                                <option value="libre">Autre longueur…</option>
                            </select>
                        </div>

                        <div x-show="referenceIndex === 'libre'">
                            <label for="longueur" class="fi-fo-field-wrp-label mb-1 block text-sm font-medium">Longueur réelle du trait rouge</label>
                            <div class="flex items-center gap-2">
                                <input
                                    id="longueur"
                                    type="number"
                                    min="1"
                                    max="5000"
                                    step="0.1"
                                    x-model.number="longueurLibre"
                                    class="fi-input block w-full rounded-lg border-none bg-white px-3 py-2 text-base shadow-sm ring-1 ring-gray-950/10 dark:bg-white/5 dark:text-white dark:ring-white/20"
                                >
                                <span class="text-base text-gray-500">mm</span>
                            </div>
                        </div>
                    </div>
                </x-filament::section>

                <x-filament::section heading="Résultat">
                    <dl class="space-y-3 text-base">
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="text-gray-500 dark:text-gray-400">Échelle</dt>
                            <dd class="font-medium" x-text="echelle ? echelle.toLocaleString('fr-FR', {maximumFractionDigits: 2}) + ' px/mm' : '—'"></dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="text-gray-500 dark:text-gray-400">Hauteur</dt>
                            <dd class="text-lg font-semibold" x-text="dimensions ? format(dimensions.hauteur) : '—'"></dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="text-gray-500 dark:text-gray-400">Largeur</dt>
                            <dd class="text-lg font-semibold" x-text="dimensions ? format(dimensions.largeur) : '—'"></dd>
                        </div>
                    </dl>

                    <p x-show="!echelle" class="mt-3 text-sm text-amber-700 dark:text-amber-500">
                        Écartez les deux pastilles rouges pour définir l'échelle.
                    </p>
                </x-filament::section>

                <x-filament::section heading="Appliquer à toute la série" collapsible>
                    <div class="space-y-4">
                        <label class="flex items-start gap-3 text-base">
                            <input type="checkbox" x-model="profil" class="fi-checkbox-input mt-1 h-5 w-5 shrink-0 rounded">
                            <span>
                                Enregistrer cette échelle comme profil
                                <span class="block text-sm text-gray-500 dark:text-gray-400">
                                    Les photographies prises avec le même appareil, à la même focale
                                    et dans le même cadrage réutiliseront automatiquement cette échelle.
                                </span>
                            </span>
                        </label>

                        <div x-show="profil" class="space-y-4 pl-8">
                            <input
                                type="text"
                                x-model="profilLabel"
                                placeholder="Nom du profil (ex. : séance du 28 octobre)"
                                class="fi-input block w-full rounded-lg border-none bg-white px-3 py-2 text-base shadow-sm ring-1 ring-gray-950/10 dark:bg-white/5 dark:text-white dark:ring-white/20"
                            >

                            <label class="flex items-start gap-3 text-base">
                                <input type="checkbox" x-model="appliquerSerie" class="fi-checkbox-input mt-1 h-5 w-5 shrink-0 rounded">
                                <span>
                                    Mesurer tout de suite les autres photographies de la série
                                    <span class="block text-sm text-gray-500 dark:text-gray-400">
                                        Par lots de {{ \App\Services\SeriesScaler::LOT }} pour ne pas faire attendre le serveur.
                                    </span>
                                </span>
                            </label>
                        </div>
                    </div>
                </x-filament::section>

                <div class="flex flex-col gap-3">
                    <x-filament::button
                        size="lg"
                        x-bind:disabled="!echelle"
                        x-on:click="enregistrer()"
                        icon="heroicon-o-check"
                    >
                        Enregistrer les dimensions
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
            Alpine.data('outilMesure', (config) => ({
                largeurPx: config.largeurPx,
                hauteurPx: config.hauteurPx,
                references: config.references,

                // Coordonnées normalisées (0 à 1) sur la photographie.
                segment: { x1: 0.3, y1: 0.9, x2: 0.7, y2: 0.9 },
                boite: {
                    x1: config.cadre.x,
                    y1: config.cadre.y,
                    x2: config.cadre.x + config.cadre.w,
                    y2: config.cadre.y + config.cadre.h,
                },

                referenceIndex: 0,
                longueurLibre: 100,
                profil: false,
                profilLabel: '',
                appliquerSerie: false,
                prise: null,

                poignees: [
                    { cle: 'r1', titre: 'Début de la référence', couleur: 'bg-[#A3553E]' },
                    { cle: 'r2', titre: 'Fin de la référence', couleur: 'bg-[#A3553E]' },
                    { cle: 'b1', titre: 'Coin supérieur gauche de la feuille', couleur: 'bg-blue-600' },
                    { cle: 'b2', titre: 'Coin inférieur droit de la feuille', couleur: 'bg-blue-600' },
                ],

                pc(valeur) {
                    return (valeur * 100) + '%';
                },

                position(cle) {
                    switch (cle) {
                        case 'r1': return { x: this.segment.x1, y: this.segment.y1 };
                        case 'r2': return { x: this.segment.x2, y: this.segment.y2 };
                        case 'b1': return { x: this.boite.x1, y: this.boite.y1 };
                        default: return { x: this.boite.x2, y: this.boite.y2 };
                    }
                },

                saisir(event, cle) {
                    event.preventDefault();
                    this.prise = cle;
                    event.target.setPointerCapture?.(event.pointerId);
                },

                deplacer(event) {
                    if (!this.prise) return;

                    const rect = this.$refs.scene.getBoundingClientRect();
                    const x = Math.min(1, Math.max(0, (event.clientX - rect.left) / rect.width));
                    const y = Math.min(1, Math.max(0, (event.clientY - rect.top) / rect.height));

                    switch (this.prise) {
                        case 'r1': this.segment.x1 = x; this.segment.y1 = y; break;
                        case 'r2': this.segment.x2 = x; this.segment.y2 = y; break;
                        case 'b1': this.boite.x1 = x; this.boite.y1 = y; break;
                        case 'b2': this.boite.x2 = x; this.boite.y2 = y; break;
                    }
                },

                get longueurReference() {
                    if (this.referenceIndex === 'libre') {
                        return Number(this.longueurLibre) || 0;
                    }

                    return this.references[this.referenceIndex]?.mm ?? 0;
                },

                get echelle() {
                    const dx = (this.segment.x2 - this.segment.x1) * this.largeurPx;
                    const dy = (this.segment.y2 - this.segment.y1) * this.hauteurPx;
                    const longueurPx = Math.hypot(dx, dy);

                    if (longueurPx < 10 || this.longueurReference <= 0) return null;

                    return longueurPx / this.longueurReference;
                },

                get dimensions() {
                    if (!this.echelle) return null;

                    const largeur = Math.abs(this.boite.x2 - this.boite.x1) * this.largeurPx / this.echelle;
                    const hauteur = Math.abs(this.boite.y2 - this.boite.y1) * this.hauteurPx / this.echelle;

                    if (largeur < 1 || hauteur < 1) return null;

                    return { largeur: Math.round(largeur), hauteur: Math.round(hauteur) };
                },

                format(mm) {
                    return (mm / 10).toLocaleString('fr-FR', { maximumFractionDigits: 1 }) + ' cm';
                },

                enregistrer() {
                    if (!this.echelle || !this.dimensions) return;

                    const libelle = this.referenceIndex === 'libre'
                        ? 'Longueur saisie'
                        : this.references[this.referenceIndex].label;

                    this.$wire.enregistrer({
                        px_per_mm: Number(this.echelle.toFixed(6)),
                        reference_mm: this.longueurReference,
                        reference_label: libelle,
                        segment: { ...this.segment },
                        box: {
                            x: Math.min(this.boite.x1, this.boite.x2),
                            y: Math.min(this.boite.y1, this.boite.y2),
                            w: Math.abs(this.boite.x2 - this.boite.x1),
                            h: Math.abs(this.boite.y2 - this.boite.y1),
                        },
                        profil: this.profil,
                        profil_label: this.profilLabel,
                        appliquer_serie: this.profil && this.appliquerSerie,
                    });
                },
            }));
        </script>
        @endscript
    @endif
</x-filament-panels::page>
