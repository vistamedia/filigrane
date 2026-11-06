@php
    /** @var \App\Models\Drawing|null $dessin */
    $photos = ($dessin?->photos ?? collect())->values();
    $principale = $dessin?->displayPhoto();

    // L'illustration choisie n'est pas forcément la première photographie.
    $rangPrincipal = $principale ? (int) $photos->search(fn ($p) => $p->is($principale)) : 0;

    $donnees = $photos->map(fn ($p) => [
        'id' => $p->id,
        'web' => $p->webUrl(),
        'original' => $p->originalUrl(),
        'retouchee' => $p->isRetouched(),
        'legende' => trim(($p->kind?->getLabel() ?? '').' '.($p->caption ?? '')),
    ]);
@endphp

{{-- Le composant Alpine est recréé dès que la liste des photographies
     change : sans quoi il garderait celle du premier affichage, et une
     photographie ajoutée depuis ne s'ouvrirait pas en grand. --}}
<div>
<div
    wire:key="visionneuse-{{ md5($donnees->toJson()) }}"
    x-data="{
        plein: false,
        index: 0,
        original: false,
        agrandie: false,
        etats: {},
        photos: @js($donnees),

        get photo() { return this.photos[this.index]; },

        ouvrir(index, original = false) {
            this.index = index;
            this.original = original;
            this.agrandie = false;
            this.plein = true;
        },

        voir({ id, original }) {
            const index = this.photos.findIndex((photo) => photo.id === id);
            if (index !== -1) this.ouvrir(index, original);
        },

        aller(sens) {
            this.index = Math.min(Math.max(this.index + sens, 0), this.photos.length - 1);
            this.agrandie = false;
        },

        basculerOriginal() {
            this.original = ! this.original;
            this.agrandie = false;
        },

        // L'original pèse lourd : on garde la version web à l'écran tant
        // qu'il n'est pas arrivé.
        get source() {
            const photo = this.photo;
            if (! photo) return null;

            return this.original && this.etats[photo.original] === 'pret' ? photo.original : photo.web;
        },

        precharger() {
            const url = this.plein && this.original ? this.photo?.original : null;
            if (! url || this.etats[url]) return;

            this.etats[url] = 'chargement';
            const image = new Image();
            image.onload = () => { this.etats[url] = 'pret'; };
            image.onerror = () => { this.etats[url] = 'echec'; };
            image.src = url;
        },

        etatOriginal() {
            switch (this.etats[this.photo?.original]) {
                case 'pret': return this.photo.retouchee ? 'Original en pleine résolution, sans retouche' : 'Original en pleine résolution';
                case 'echec': return 'L\'original n\'a pas pu être affiché : vous voyez la version courante.';
                default: return 'Chargement de l\'original en pleine résolution…';
            }
        },

        // Un clic, ou une touche du doigt, montre l'image à sa taille réelle,
        // centrée sur l'endroit visé ; un second la remet à la taille de l'écran.
        agrandir(event) {
            const image = event.currentTarget;
            const zone = image.parentElement;
            const cadre = image.getBoundingClientRect();
            const [fx, fy] = [(event.clientX - cadre.left) / cadre.width, (event.clientY - cadre.top) / cadre.height];

            this.agrandie = ! this.agrandie;
            if (! this.agrandie) return;

            this.$nextTick(() => {
                zone.scrollLeft = fx * image.offsetWidth - zone.clientWidth / 2;
                zone.scrollTop = fy * image.offsetHeight - zone.clientHeight / 2;
            });
        },
    }"
    x-on:voir-photo.window="voir($event.detail)"
    class="fi-section rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 lg:sticky lg:top-24 dark:bg-gray-900 dark:ring-white/10"
>
    @if ($principale)
        <button
            type="button"
            x-on:click="ouvrir({{ $rangPrincipal }})"
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
                        x-on:click="ouvrir({{ $rang }})"
                        class="fi-filigrane-vignette overflow-hidden rounded-md p-1"
                        title="{{ $photo->kind?->getLabel() }}"
                    >
                        <img src="{{ $photo->thumbUrl() }}" alt="" class="aspect-square w-full object-cover">
                    </button>
                @endforeach
            </div>
        @endif

        {{-- Les clichés signalés à refaire : on les voit là où l'on regarde
             l'œuvre, pas seulement dans la liste des photographies. --}}
        @foreach ($photos->filter->needsReshoot() as $aRefaire)
            <p class="mt-3 flex items-start gap-2 rounded-lg bg-warning-50 px-3 py-2 text-sm text-warning-800 ring-1 ring-warning-600/20 dark:bg-warning-400/10 dark:text-warning-300">
                <x-filament::icon icon="heroicon-o-flag" class="mt-0.5 h-4 w-4 shrink-0" />
                <span>
                    <span class="font-medium">{{ $photos->count() > 1 ? $aRefaire->kind?->getLabel().' à refaire' : 'Cliché à refaire' }}</span>
                    : {{ mb_lcfirst($aRefaire->reshootLabel()) }}
                </span>
            </p>
        @endforeach

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
                <dt class="text-gray-500 dark:text-gray-400">Photographie</dt>
                <dd class="text-right">
                    <a
                        href="{{ \App\Filament\Pages\RetoucherPhoto::getUrl(['photo' => $principale->id]) }}"
                        class="font-medium text-primary-600 hover:underline dark:text-primary-400"
                    >
                        {{ $principale->isRetouched() ? 'Reprendre la retouche' : 'Redresser, recadrer' }}
                    </a>
                </dd>
            </div>

            <div class="flex justify-between gap-3">
                <dt class="text-gray-500 dark:text-gray-400">Original</dt>
                <dd class="text-right">
                    {{-- Dans l'application, pas dans un nouvel onglet : sur la
                         tablette, l'application installée n'a pas d'onglets,
                         et l'on ne pouvait plus revenir à la fiche. --}}
                    <button
                        type="button"
                        x-on:click="ouvrir({{ $rangPrincipal }}, true)"
                        class="font-medium text-primary-600 hover:underline dark:text-primary-400"
                    >
                        {{ $principale->isRetouched() ? 'Pleine résolution, sans retouche' : 'Pleine résolution' }}
                    </button>
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

    {{-- Affichage plein écran, avec zoom au pincement sur tablette et sur
         téléphone. L'original s'y affiche aussi : rien ne s'ouvre hors de
         l'application. --}}
    <template x-teleport="body">
        <div
            x-show="plein"
            x-cloak
            x-effect="precharger()"
            x-on:keydown.escape.window="plein = false"
            x-on:keydown.arrow-right.window="if (plein) aller(1)"
            x-on:keydown.arrow-left.window="if (plein) aller(-1)"
            role="dialog"
            aria-modal="true"
            aria-label="Photographie en grand"
            class="fixed inset-0 z-50 flex flex-col bg-black/95"
            x-transition.opacity
        >
            <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 text-white">
                <div class="min-w-0">
                    <p class="text-sm" x-text="photo?.legende"></p>
                    <p x-show="original" class="text-sm text-white/70" x-text="etatOriginal()"></p>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        x-on:click="basculerOriginal()"
                        x-bind:aria-pressed="original"
                        x-bind:class="original ? 'bg-white text-gray-950' : 'ring-1 ring-white/30 hover:bg-white/10'"
                        class="rounded-lg px-4 py-2 text-base font-medium"
                    >
                        Pleine résolution
                    </button>
                    <button
                        type="button"
                        x-on:click="plein = false"
                        class="rounded-lg px-4 py-2 text-base font-medium ring-1 ring-white/30 hover:bg-white/10"
                    >
                        Fermer
                    </button>
                </div>
            </div>

            <div class="min-h-0 flex-1 overflow-auto p-2" style="touch-action: manipulation;">
                <img
                    x-bind:src="source"
                    alt=""
                    x-on:click="agrandir($event)"
                    x-bind:title="agrandie ? 'Revenir à la taille de l\'écran' : 'Agrandir'"
                    x-bind:class="agrandie ? 'max-w-none cursor-zoom-out' : 'max-h-full max-w-full cursor-zoom-in'"
                    class="mx-auto w-auto object-contain"
                >
            </div>

            <div class="flex items-center justify-center gap-6 py-3 text-white" x-show="photos.length > 1">
                <button type="button" x-on:click="aller(-1)" class="rounded-lg px-5 py-2 ring-1 ring-white/30 hover:bg-white/10">Précédente</button>
                <span class="text-sm" x-text="(index + 1) + ' / ' + photos.length"></span>
                <button type="button" x-on:click="aller(1)" class="rounded-lg px-5 py-2 ring-1 ring-white/30 hover:bg-white/10">Suivante</button>
            </div>
        </div>
    </template>
</div>
</div>
