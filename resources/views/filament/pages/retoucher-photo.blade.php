@php
    $reglages = $this->reglagesInitiaux();
    $focaleExif = $this->focaleExif();
    $suivante = $this->suivante();
    $champ = 'fi-input block w-full rounded-lg border-none bg-white px-3 py-2 text-base shadow-sm ring-1 ring-gray-950/10 dark:bg-white/5 dark:text-white dark:ring-white/20';
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
            x-data="outilRetouche({
                url: @js($this->photo->rawWebUrl()),
                largeurPx: {{ (int) $this->photo->width_px }},
                hauteurPx: {{ (int) $this->photo->height_px }},
                reglages: @js($reglages),
                focaleExif: @js($focaleExif),
            })"
            x-on:resize.window="mesurerApercu()"
            class="grid gap-6 lg:grid-cols-3"
        >
            {{-- La photographie telle qu'elle a été prise, et les quatre coins. --}}
            <div class="lg:col-span-2">
                <div class="fi-filigrane-vignette rounded-xl">
                    {{-- La scène épouse exactement l'image : les coordonnées des
                         pastilles se rapportent à la photographie, pas à son
                         encadrement. Sa hauteur reste dans l'écran, pour qu'une
                         photographie en hauteur ne cache pas ses coins du bas. --}}
                    <div
                        x-ref="scene"
                        class="relative mx-auto select-none"
                        style="touch-action: none; max-width: min(100%, calc(75vh * {{ $this->photo->width_px / max(1, $this->photo->height_px) }}));"
                        x-on:pointermove="deplacer($event)"
                        x-on:pointerup.window="prise = null"
                        x-on:pointercancel.window="prise = null"
                    >
                        <img x-ref="source" src="{{ $this->photo->rawWebUrl() }}" alt="" class="block w-full" draggable="false">

                        <svg class="pointer-events-none absolute inset-0 h-full w-full" viewBox="0 0 1000 1000" preserveAspectRatio="none" aria-hidden="true">
                            {{-- Ce qui sera coupé est assombri. --}}
                            <path :d="voile" fill="rgba(0, 0, 0, 0.5)" fill-rule="evenodd" />
                            <polygon
                                :points="contour"
                                fill="none"
                                :stroke="valide ? '#2563EB' : '#DC2626'"
                                stroke-width="2"
                                vector-effect="non-scaling-stroke"
                            />
                        </svg>

                        {{-- Poignées : grandes, pour être attrapables au doigt. La
                             flèche dit à quel coin chacune appartient. --}}
                        <template x-for="(coin, i) in coins" :key="i">
                            <button
                                type="button"
                                x-on:pointerdown="saisir($event, i)"
                                class="absolute flex h-11 w-11 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full border-2 border-white bg-blue-600 text-lg font-semibold leading-none text-white shadow-lg"
                                :style="`left:${coin[0] * 100}%; top:${coin[1] * 100}%`"
                                :aria-label="noms[i]"
                                :title="noms[i]"
                                x-text="fleches[i]"
                            ></button>
                        </template>

                        {{-- Loupe : le doigt cache le coin qu'il déplace. --}}
                        <div
                            x-show="prise !== null"
                            x-cloak
                            class="pointer-events-none absolute overflow-hidden rounded-full border-4 border-white bg-no-repeat shadow-xl"
                            :style="styleLoupe()"
                        >
                            <span class="absolute left-1/2 top-0 h-full w-px -translate-x-1/2 bg-blue-600"></span>
                            <span class="absolute left-0 top-1/2 h-px w-full -translate-y-1/2 bg-blue-600"></span>
                        </div>
                    </div>
                </div>

                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">
                    Faites glisser chaque pastille sur le coin qu'indique sa flèche :
                    coins du cadre, du passe-partout ou de la feuille, selon ce que vous
                    voulez garder. Pendant le déplacement, une loupe montre le coin de près.
                </p>

                <p x-show="!valide" x-cloak class="mt-2 text-base font-medium text-danger-600 dark:text-danger-400">
                    Les pastilles se croisent : replacez chacune au coin qu'indique sa flèche.
                </p>
            </div>

            {{-- Résultat et réglages. --}}
            <div class="space-y-5">
                <x-filament::section heading="Aperçu">
                    <div x-ref="zoneApercu" class="w-full">
                        <div
                            data-apercu
                            class="relative mx-auto overflow-hidden rounded-md bg-gray-100 shadow-sm dark:bg-gray-800"
                            :style="styleApercu()"
                        >
                            <img
                                src="{{ $this->photo->rawWebUrl() }}"
                                alt=""
                                draggable="false"
                                class="absolute left-0 top-0 max-w-none origin-top-left"
                                :style="styleImageApercu()"
                            >
                        </div>
                    </div>

                    <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">
                        Proportions de l'œuvre redressée :
                        <span class="font-medium text-gray-950 dark:text-white" x-text="libelleProportions()"></span>
                    </p>
                </x-filament::section>

                <x-filament::section heading="Prise de vue">
                    @if ($focaleExif)
                        <p class="text-base">
                            Focale lue dans la photographie :
                            {{ number_format($focaleExif, 0, ',', ' ') }} mm (équivalent 24 × 36).
                        </p>
                    @else
                        <label for="zoom" class="mb-1 block text-sm font-medium">Zoom affiché par l'appareil</label>
                        <select id="zoom" x-model.number="focale" class="{{ $champ }}">
                            @foreach ($this->zooms() as $zoom)
                                <option value="{{ $zoom['focale'] }}">{{ $zoom['label'] }}</option>
                            @endforeach
                        </select>
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                            Il fixe les vraies proportions d'une œuvre photographiée de biais.
                            Dans le doute, laissez 1×.
                        </p>
                    @endif
                </x-filament::section>

                <x-filament::section heading="Orientation et lumière">
                    <div class="space-y-5">
                        <div class="grid grid-cols-2 gap-3">
                            <x-filament::button color="gray" icon="heroicon-o-arrow-uturn-left" x-on:click="tourner(-1)">
                                Tourner à gauche
                            </x-filament::button>
                            <x-filament::button color="gray" icon="heroicon-o-arrow-uturn-right" x-on:click="tourner(1)">
                                Tourner à droite
                            </x-filament::button>
                        </div>

                        <div>
                            <div class="mb-1 flex items-baseline justify-between gap-3">
                                <label for="luminosite" class="text-sm font-medium">Luminosité</label>
                                <span class="text-sm text-gray-500 dark:text-gray-400" x-text="pourcentage(luminosite)"></span>
                            </div>
                            <input id="luminosite" type="range" min="0.5" max="2" step="0.01" x-model.number="luminosite" class="h-11 w-full accent-primary-600">
                        </div>

                        <div>
                            <div class="mb-1 flex items-baseline justify-between gap-3">
                                <label for="contraste" class="text-sm font-medium">Contraste</label>
                                <span class="text-sm text-gray-500 dark:text-gray-400" x-text="pourcentage(contraste)"></span>
                            </div>
                            <input id="contraste" type="range" min="0.5" max="2" step="0.01" x-model.number="contraste" class="h-11 w-full accent-primary-600">
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <x-filament::button color="gray" icon="heroicon-o-sparkles" x-on:click="automatique()">
                                Automatique
                            </x-filament::button>
                            <x-filament::button color="gray" x-on:click="luminosite = 1; contraste = 1">
                                Lumière d'origine
                            </x-filament::button>
                        </div>

                        <x-filament::button color="gray" class="w-full" icon="heroicon-o-arrows-pointing-out" x-on:click="toutLImage()">
                            Garder toute l'image
                        </x-filament::button>
                    </div>
                </x-filament::section>

                <div class="flex flex-col gap-3">
                    <x-filament::button
                        size="lg"
                        icon="heroicon-o-check"
                        x-bind:disabled="!valide || enCours"
                        x-on:click="enregistrer(false)"
                    >
                        <span x-show="!enCours">Enregistrer</span>
                        <span x-show="enCours" x-cloak>Préparation de l'image…</span>
                    </x-filament::button>

                    @if ($suivante)
                        <x-filament::button
                            size="lg"
                            color="gray"
                            icon="heroicon-o-forward"
                            x-bind:disabled="!valide || enCours"
                            x-on:click="enregistrer(true)"
                        >
                            Enregistrer, puis la suivante
                        </x-filament::button>
                    @endif

                    <x-filament::button
                        size="lg"
                        color="gray"
                        tag="a"
                        href="{{ \App\Filament\Resources\Drawings\DrawingResource::getUrl('edit', ['record' => $this->photo->drawing]) }}"
                    >
                        Annuler
                    </x-filament::button>

                    @if ($this->photo->isRetouched())
                        <x-filament::button
                            size="lg"
                            color="danger"
                            outlined
                            icon="heroicon-o-arrow-path"
                            wire:click="annulerRetouche"
                            wire:confirm="La photographie reviendra telle qu'elle a été prise, et ces réglages seront oubliés. Continuer ?"
                        >
                            Revenir à la photographie d'origine
                        </x-filament::button>
                    @endif
                </div>
            </div>
        </div>

        @script
        <script>
            Alpine.data('outilRetouche', (config) => ({
                url: config.url,
                largeurPx: config.largeurPx,
                hauteurPx: config.hauteurPx,
                focaleExif: config.focaleExif,

                // Coordonnées normalisées (0 à 1) sur la photographie, dans
                // l'ordre de la lecture : haut-gauche, haut-droite,
                // bas-droite, bas-gauche.
                coins: config.reglages.coins.map((coin) => [Number(coin[0]), Number(coin[1])]),
                quart: config.reglages.quart,
                focale: config.reglages.focale,
                luminosite: config.reglages.luminosite,
                contraste: config.reglages.contraste,

                noms: [
                    'Coin supérieur gauche de l\'œuvre',
                    'Coin supérieur droit de l\'œuvre',
                    'Coin inférieur droit de l\'œuvre',
                    'Coin inférieur gauche de l\'œuvre',
                ],
                fleches: ['↖', '↗', '↘', '↙'],

                prise: null,
                taille: { l: 0, h: 0 },
                largeurDispo: 300,
                enCours: false,

                init() {
                    this.$nextTick(() => this.mesurerApercu());
                },

                saisir(event, i) {
                    event.preventDefault();
                    const rect = this.$refs.scene.getBoundingClientRect();
                    this.taille = { l: rect.width, h: rect.height };
                    this.prise = i;
                    event.target.setPointerCapture?.(event.pointerId);
                },

                deplacer(event) {
                    if (this.prise === null) return;

                    const rect = this.$refs.scene.getBoundingClientRect();
                    const x = Math.min(1, Math.max(0, (event.clientX - rect.left) / rect.width));
                    const y = Math.min(1, Math.max(0, (event.clientY - rect.top) / rect.height));

                    this.coins[this.prise] = [x, y];
                },

                tourner(sens) {
                    this.quart = (this.quart + sens + 4) % 4;
                },

                toutLImage() {
                    this.coins = [[0, 0], [1, 0], [1, 1], [0, 1]];
                },

                get contour() {
                    return this.coins.map(([x, y]) => `${x * 1000},${y * 1000}`).join(' ');
                },

                get voile() {
                    const [a, ...autres] = this.coins.map(([x, y]) => `${x * 1000},${y * 1000}`);

                    return `M0,0 H1000 V1000 H0 Z M${a} L${autres.join(' L')} Z`;
                },

                /**
                 * Les coins doivent tourner dans le sens des aiguilles d'une
                 * montre, sans se croiser (voir App\Support\Perspective).
                 */
                get valide() {
                    const p = this.coins.map(([x, y]) => [x * this.largeurPx, y * this.hauteurPx]);

                    for (let i = 0; i < 4; i++) {
                        const [a, b, c] = [p[i], p[(i + 1) % 4], p[(i + 2) % 4]];

                        if ((b[0] - a[0]) * (c[1] - b[1]) - (b[1] - a[1]) * (c[0] - b[0]) <= 0) {
                            return false;
                        }
                    }

                    return true;
                },

                /**
                 * Rapport largeur / hauteur de l'œuvre réelle : même calcul
                 * que App\Support\Perspective::proportions(), pour que
                 * l'aperçu montre ce que le serveur produira.
                 */
                get proportions() {
                    const [l, h] = [this.largeurPx, this.hauteurPx];
                    const [hg, hd, bd, bg] = this.coins.map(([x, y]) => [x * l - l / 2, y * h - h / 2, 1]);
                    const [m1, m2, m3, m4] = [hg, hd, bg, bd];

                    const mixte = (a, b, c) => (a[1] * b[2] - a[2] * b[1]) * c[0]
                        + (a[2] * b[0] - a[0] * b[2]) * c[1]
                        + (a[0] * b[1] - a[1] * b[0]) * c[2];

                    const k2 = mixte(m1, m4, m3) / mixte(m2, m4, m3);
                    const k3 = mixte(m1, m4, m2) / mixte(m3, m4, m2);
                    const n2 = [k2 * m2[0] - m1[0], k2 * m2[1] - m1[1], k2 - 1];
                    const n3 = [k3 * m3[0] - m1[0], k3 * m3[1] - m1[1], k3 - 1];

                    const f = (this.focaleExif || this.focale) * Math.hypot(l, h) / 43.2666;
                    const rapport = Math.sqrt(
                        ((n2[0] ** 2 + n2[1] ** 2) / f ** 2 + n2[2] ** 2)
                        / ((n3[0] ** 2 + n3[1] ** 2) / f ** 2 + n3[2] ** 2),
                    );

                    if (Number.isFinite(rapport) && rapport > 0 && k2 > 0 && k3 > 0) {
                        return rapport;
                    }

                    const d = (a, b) => Math.hypot(b[0] - a[0], b[1] - a[1]);

                    return (d(hg, hd) + d(bg, bd)) / (d(hg, bg) + d(hd, bd));
                },

                /** Proportions de l'image produite, quart de tour compris. */
                get proportionsFinales() {
                    return this.quart % 2 === 1 ? 1 / this.proportions : this.proportions;
                },

                libelleProportions() {
                    if (!this.valide) return '—';

                    const r = this.proportionsFinales;
                    const sens = r >= 1 ? 'en largeur' : 'en hauteur';

                    return `${r.toLocaleString('fr-FR', { maximumFractionDigits: 2 })} (${sens})`;
                },

                pourcentage(valeur) {
                    const ecart = Math.round((valeur - 1) * 100);

                    if (ecart === 0) return 'd\'origine';

                    return (ecart > 0 ? '+' : '−') + Math.abs(ecart) + ' %';
                },

                mesurerApercu() {
                    this.largeurDispo = this.$refs.zoneApercu?.clientWidth || 300;
                },

                /** Le cadre de l'aperçu prend les proportions de l'image produite. */
                get tailleApercu() {
                    const r = this.valide ? this.proportionsFinales : 1;
                    const hauteurMax = 380;
                    const l = Math.min(this.largeurDispo, hauteurMax * r);

                    return { l, h: l / r };
                },

                styleApercu() {
                    const { l, h } = this.tailleApercu;

                    return `width:${l}px; height:${h}px; filter: brightness(${this.luminosite}) contrast(${this.contraste});`;
                },

                /**
                 * L'image entière est projetée de sorte que le quadrilatère
                 * des coins remplisse exactement le cadre de l'aperçu : une
                 * homographie, que matrix3d() sait appliquer.
                 */
                styleImageApercu() {
                    if (!this.valide) return 'display:none';

                    // Taille de référence de l'image dans l'aperçu ; le
                    // navigateur la réduit lui-même par la transformation.
                    const base = 2000;
                    const [l, h] = [base, base * this.hauteurPx / this.largeurPx];
                    const { l: L, h: H } = this.tailleApercu;

                    const source = this.coins.map(([x, y]) => [x * l, y * h]);
                    const rectangle = [[0, 0], [L, 0], [L, H], [0, H]];

                    // Un quart de tour vers la droite envoie le coin
                    // haut-gauche en haut à droite, et ainsi de suite.
                    const cible = source.map((_, i) => rectangle[(i + this.quart) % 4]);
                    const m = [...this.homographie(source, cible), 1];

                    // Une homographie est définie au signe près. Le navigateur
                    // n'affiche que ce qui est « devant » lui : il faut que
                    // l'intérieur des coins ait une profondeur positive, sans
                    // quoi l'aperçu disparaît dès que la perspective est forte.
                    const [cx, cy] = source.reduce(([sx, sy], [x, y]) => [sx + x / 4, sy + y / 4], [0, 0]);
                    const s = m[6] * cx + m[7] * cy + 1 < 0 ? -1 : 1;
                    const [a, b, c, d, e, f, g, k, w] = m.map((v) => v * s);

                    return `width:${l}px; height:${h}px; transform: matrix3d(${a},${d},0,${g},${b},${e},0,${k},0,0,1,0,${c},${f},0,${w});`;
                },

                /**
                 * Résout les huit inconnues de l'homographie qui envoie
                 * chaque point source sur sa cible (élimination de Gauss).
                 */
                homographie(source, cible) {
                    const A = [];
                    const b = [];

                    source.forEach(([x, y], i) => {
                        const [u, v] = cible[i];
                        A.push([x, y, 1, 0, 0, 0, -u * x, -u * y]); b.push(u);
                        A.push([0, 0, 0, x, y, 1, -v * x, -v * y]); b.push(v);
                    });

                    for (let c = 0; c < 8; c++) {
                        let pivot = c;
                        for (let r = c + 1; r < 8; r++) {
                            if (Math.abs(A[r][c]) > Math.abs(A[pivot][c])) pivot = r;
                        }
                        [A[c], A[pivot]] = [A[pivot], A[c]];
                        [b[c], b[pivot]] = [b[pivot], b[c]];

                        for (let r = c + 1; r < 8; r++) {
                            const facteur = A[r][c] / A[c][c];
                            for (let k = c; k < 8; k++) A[r][k] -= facteur * A[c][k];
                            b[r] -= facteur * b[c];
                        }
                    }

                    const h = new Array(8);
                    for (let r = 7; r >= 0; r--) {
                        let somme = b[r];
                        for (let k = r + 1; k < 8; k++) somme -= A[r][k] * h[k];
                        h[r] = somme / A[r][r];
                    }

                    return h;
                },

                styleLoupe() {
                    if (this.prise === null) return 'display:none';

                    const [x, y] = this.coins[this.prise];
                    const { l, h } = this.taille;
                    const [diametre, grossissement] = [128, 3];

                    // Au-dessus du doigt, ou dessous si l'on est trop près du haut.
                    let haut = y * h - diametre - 36;
                    if (haut < 0) haut = y * h + 36;
                    const gauche = Math.min(Math.max(0, x * l - diametre / 2), l - diametre);

                    return [
                        `left:${gauche}px`, `top:${haut}px`,
                        `width:${diametre}px`, `height:${diametre}px`,
                        `background-image:url('${this.url}')`,
                        `background-size:${l * grossissement}px ${h * grossissement}px`,
                        `background-position:${diametre / 2 - x * l * grossissement}px ${diametre / 2 - y * h * grossissement}px`,
                    ].join(';');
                },

                /**
                 * Corrige l'exposition d'après l'œuvre seule — ce qui est à
                 * l'intérieur des coins : le plus clair (le papier, le
                 * passe-partout) est ramené vers 95 %, et le contraste n'est
                 * relevé qu'avec mesure. Étirer jusqu'au noir et au blanc
                 * purs dénaturerait un papier ancien et une dorure.
                 */
                automatique() {
                    const image = this.$refs.source;
                    const l = 300;
                    const h = Math.round(l * image.naturalHeight / image.naturalWidth);

                    let pixels;
                    try {
                        const toile = document.createElement('canvas');
                        toile.width = l;
                        toile.height = h;
                        const ctx = toile.getContext('2d', { willReadFrequently: true });
                        ctx.drawImage(image, 0, 0, l, h);
                        pixels = ctx.getImageData(0, 0, l, h).data;
                    } catch {
                        return;
                    }

                    const histogramme = new Array(256).fill(0);
                    let total = 0;

                    for (let py = 0; py < h; py++) {
                        for (let px = 0; px < l; px++) {
                            if (!this.dedans((px + 0.5) / l, (py + 0.5) / h)) continue;

                            const i = (py * l + px) * 4;
                            histogramme[Math.round(0.2126 * pixels[i] + 0.7152 * pixels[i + 1] + 0.0722 * pixels[i + 2])]++;
                            total++;
                        }
                    }

                    if (total < 100) return;

                    const centile = (part) => {
                        let cumul = 0;
                        for (let v = 0; v < 256; v++) {
                            cumul += histogramme[v];
                            if (cumul >= part * total) return v / 255;
                        }
                        return 1;
                    };

                    const [sombre, clair] = [centile(0.01), centile(0.99)];
                    if (clair - sombre < 0.05) return;

                    // Le contraste rapproche l'étendue des tons de 5 à 95 %,
                    // sans jamais l'augmenter de plus de 35 % ni la réduire.
                    const contraste = Math.min(1.35, Math.max(1, 0.9 / (clair - sombre)));

                    // Les filtres brightness() puis contrast() font
                    // y = C·(L·x − 0,5) + 0,5 : on choisit L pour que le plus
                    // clair arrive à 95 %.
                    const luminosite = (0.5 + 0.45 / contraste) / clair;
                    const arrondir = (v) => Math.round(Math.min(2, Math.max(0.5, v)) * 100) / 100;

                    this.contraste = arrondir(contraste);
                    this.luminosite = arrondir(luminosite);
                },

                dedans(x, y) {
                    for (let i = 0; i < 4; i++) {
                        const [a, b] = [this.coins[i], this.coins[(i + 1) % 4]];
                        if ((b[0] - a[0]) * (y - a[1]) - (b[1] - a[1]) * (x - a[0]) < 0) return false;
                    }

                    return true;
                },

                async enregistrer(suivante) {
                    if (!this.valide || this.enCours) return;

                    this.enCours = true;

                    try {
                        await this.$wire.enregistrer({
                            coins: this.coins.map(([x, y]) => [Number(x.toFixed(5)), Number(y.toFixed(5))]),
                            quart: this.quart,
                            focale: this.focaleExif || this.focale,
                            luminosite: this.luminosite,
                            contraste: this.contraste,
                        }, suivante);
                    } finally {
                        this.enCours = false;
                    }
                },
            }));
        </script>
        @endscript
    @endif
</x-filament-panels::page>
