<x-filament-panels::page>
    <div
        x-data="studioPhoto()"
        x-on:beforeunload.window="arreter()"
        class="space-y-6"
    >
        {{-- Réglage du rythme de travail. --}}
        <x-filament::section heading="Mode de prise de vue">
            <div class="space-y-4">
                <label class="flex items-start gap-3 text-base">
                    <input type="radio" wire:model.live="nouvelleFiche" value="1" class="mt-1 h-5 w-5 shrink-0">
                    <span>
                        Une fiche par cliché
                        <span class="block text-sm text-gray-500 dark:text-gray-400">
                            Pour photographier le fonds à la chaîne : un dessin, une photo, une fiche.
                        </span>
                    </span>
                </label>

                <label class="flex items-start gap-3 text-base">
                    <input type="radio" wire:model.live="nouvelleFiche" value="0" class="mt-1 h-5 w-5 shrink-0">
                    <span>
                        Plusieurs clichés sur la même fiche
                        <span class="block text-sm text-gray-500 dark:text-gray-400">
                            Pour le recto, le verso, un détail ou le filigrane d'un même dessin.
                        </span>
                    </span>
                </label>

                @if (! $nouvelleFiche && $ficheCourante)
                    <div class="flex flex-wrap items-center gap-3 rounded-lg bg-primary-50 p-3 text-base dark:bg-primary-500/10">
                        <span>Fiche en cours : <strong>{{ \App\Models\Drawing::find($ficheCourante)?->inventory_number }}</strong></span>
                        <x-filament::button size="sm" color="gray" wire:click="ficheSuivante">
                            Passer au dessin suivant
                        </x-filament::button>
                    </div>
                @endif
            </div>
        </x-filament::section>

        {{-- Chemin 1 : l'appareil du téléphone, pleine définition. --}}
        <x-filament::section heading="Appareil photo du téléphone">
            <p class="mb-4 text-base text-gray-600 dark:text-gray-400">
                Ouvre l'appareil photo de l'iPhone et conserve toute sa définition.
                C'est le choix à privilégier pour les clichés qui feront référence.
            </p>

            <label class="fi-btn fi-btn-size-lg inline-flex cursor-pointer items-center gap-2 rounded-lg bg-primary-600 px-6 py-3 text-base font-semibold text-white hover:bg-primary-700">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0Z" />
                </svg>
                Prendre une photo
                <input
                    type="file"
                    accept="image/*"
                    capture="environment"
                    class="sr-only"
                    x-ref="appareil"
                    x-on:change="envoyerFichier($event.target.files[0]); $event.target.value = ''"
                >
            </label>
        </x-filament::section>

        {{-- Chemin 2 : le viseur intégré, pour enchaîner. --}}
        <x-filament::section heading="Viseur intégré" collapsible collapsed>
            <p class="mb-4 text-base text-gray-600 dark:text-gray-400">
                Affiche l'image en direct et déclenche sans quitter l'application.
                Plus rapide pour une série, mais la définition dépend de ce que
                le navigateur accepte de fournir.
            </p>

            <div x-show="erreur" class="mb-4 rounded-lg bg-danger-50 p-4 text-base text-danger-700 dark:bg-danger-500/10 dark:text-danger-400">
                <span x-text="erreur"></span>
            </div>

            <div x-show="actif" x-cloak class="space-y-4">
                <div class="relative overflow-hidden rounded-xl bg-black">
                    <video x-ref="video" autoplay playsinline muted class="mx-auto block max-h-[60vh] w-full object-contain"></video>
                    <span
                        x-show="definition"
                        class="absolute right-3 top-3 rounded-md bg-black/60 px-2 py-1 text-xs text-white"
                        x-text="definition"
                    ></span>
                </div>

                <div class="flex flex-wrap items-center justify-center gap-3">
                    <x-filament::button size="lg" x-on:click="declencher()" icon="heroicon-o-camera">
                        Déclencher
                    </x-filament::button>

                    <x-filament::button size="lg" color="gray" x-on:click="changerCamera()" x-show="plusieursCameras">
                        Changer d'objectif
                    </x-filament::button>

                    <x-filament::button size="lg" color="gray" x-on:click="arreter()">
                        Fermer le viseur
                    </x-filament::button>
                </div>
            </div>

            <div x-show="!actif">
                <x-filament::button size="lg" color="gray" x-on:click="demarrer()" icon="heroicon-o-video-camera">
                    Ouvrir le viseur
                </x-filament::button>
            </div>
        </x-filament::section>

        {{-- Journal de la séance. --}}
        @if ($derniers !== [])
            <x-filament::section heading="Clichés de cette séance">
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-4 lg:grid-cols-6">
                    @foreach ($derniers as $cliche)
                        <a href="{{ $cliche['lien'] }}" class="fi-filigrane-vignette block rounded-lg p-2 transition hover:opacity-80">
                            @if ($cliche['vignette'])
                                <img src="{{ $cliche['vignette'] }}" alt="" class="aspect-square w-full rounded object-cover">
                            @else
                                <div class="aspect-square w-full rounded bg-gray-100 dark:bg-gray-800"></div>
                            @endif
                            <span class="mt-2 block text-center text-sm font-medium">{{ $cliche['inventaire'] }}</span>
                        </a>
                    @endforeach
                </div>
            </x-filament::section>
        @endif

        {{-- Progression du téléversement. --}}
        <div
            x-show="envoi"
            x-cloak
            class="fixed inset-x-0 bottom-0 z-40 border-t border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900"
        >
            <div class="mx-auto flex max-w-xl items-center gap-4">
                <span class="text-base">Enregistrement…</span>
                <div class="h-2 flex-1 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                    <div class="h-full bg-primary-600 transition-all" :style="`width:${progression}%`"></div>
                </div>
                <span class="w-12 text-right text-sm" x-text="progression + ' %'"></span>
            </div>
        </div>
    </div>

    @script
    <script>
        Alpine.data('studioPhoto', () => ({
            flux: null,
            actif: false,
            envoi: false,
            progression: 0,
            erreur: '',
            definition: '',
            cameraArriere: true,
            plusieursCameras: false,

            async demarrer() {
                this.erreur = '';

                if (!navigator.mediaDevices?.getUserMedia) {
                    this.erreur = "Ce navigateur ne donne pas accès à la caméra. Utilisez le bouton « Prendre une photo » ci-dessus.";
                    return;
                }

                if (!window.isSecureContext) {
                    this.erreur = "La caméra n'est accessible qu'en HTTPS. Sur l'adresse en ligne, elle fonctionnera.";
                    return;
                }

                try {
                    this.flux = await navigator.mediaDevices.getUserMedia({
                        video: {
                            facingMode: this.cameraArriere ? { ideal: 'environment' } : 'user',
                            width: { ideal: 4032 },
                            height: { ideal: 3024 },
                        },
                        audio: false,
                    });

                    this.$refs.video.srcObject = this.flux;
                    this.actif = true;

                    const piste = this.flux.getVideoTracks()[0];
                    const reglages = piste.getSettings();
                    this.definition = `${reglages.width} × ${reglages.height} px`;

                    const appareils = await navigator.mediaDevices.enumerateDevices();
                    this.plusieursCameras = appareils.filter((a) => a.kind === 'videoinput').length > 1;
                } catch (e) {
                    this.erreur = "Accès à la caméra refusé. Autorisez-le dans les réglages du navigateur, ou utilisez le bouton « Prendre une photo ».";
                }
            },

            arreter() {
                this.flux?.getTracks().forEach((piste) => piste.stop());
                this.flux = null;
                this.actif = false;
            },

            async changerCamera() {
                this.cameraArriere = !this.cameraArriere;
                this.arreter();
                await this.demarrer();
            },

            declencher() {
                const video = this.$refs.video;

                if (!video?.videoWidth) return;

                const toile = document.createElement('canvas');
                toile.width = video.videoWidth;
                toile.height = video.videoHeight;
                toile.getContext('2d').drawImage(video, 0, 0);

                toile.toBlob((blob) => {
                    const horodatage = new Date().toISOString().replace(/[:.]/g, '-');
                    this.envoyerFichier(new File([blob], `viseur-${horodatage}.jpg`, { type: 'image/jpeg' }));
                }, 'image/jpeg', 0.95);
            },

            envoyerFichier(fichier) {
                if (!fichier) return;

                this.envoi = true;
                this.progression = 0;

                this.$wire.upload(
                    'cliche',
                    fichier,
                    () => {
                        this.envoi = false;
                        this.$wire.enregistrerCliche();
                    },
                    () => {
                        this.envoi = false;
                        this.erreur = "Le téléversement a échoué. Vérifiez la connexion et réessayez.";
                    },
                    (evenement) => {
                        this.progression = evenement.detail.progress;
                    },
                );
            },
        }));
    </script>
    @endscript
</x-filament-panels::page>
