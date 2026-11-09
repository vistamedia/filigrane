{{--
    Envois de photographies : relance, puis liste des fichiers en échec.

    Lors d'un import de 70 ProRAW, l'hébergeur a refusé 4 envois sur 104,
    aux minutes les plus chargées (jusqu'à 1 Go par minute). Ces refus sont
    passagers : renvoyé aussitôt, le même fichier passe. On relance donc
    chaque envoi refusé, deux fois au plus, avec un petit délai ; et si un
    fichier échoue encore, on le nomme sous le champ, au lieu du message
    générique de Livewire, qui ne disait pas lequel.

    Branché sur chaque champ d'envoi par extraAlpineAttributes (voir
    PhotoImporter::RELANCE_DES_ENVOIS), une fois FilePond prêt.
--}}
<script>
    window.filigraneEnvois = (pond, racine) => {
        if (pond.filigraneSuivi) return;
        pond.filigraneSuivi = true;

        const RELANCES = 2;
        const tentatives = new Map();
        const echecs = new Map();

        const message = document.createElement('p');
        message.className = 'mt-2 text-sm font-medium text-danger-600 dark:text-danger-400';
        message.setAttribute('role', 'alert');
        message.dataset.envoisEnEchec = '';
        message.hidden = true;

        // À l'intérieur du champ, que Livewire laisse en paix (wire:ignore) :
        // posée à côté, la liste disparaissait à la requête suivante.
        racine.append(message);

        const afficher = () => {
            const noms = [...echecs.values()];
            message.hidden = noms.length === 0;

            if (noms.length === 0) return;

            message.textContent = (noms.length === 1
                ? `1 fichier n'a pas pu être envoyé, même après ${RELANCES} nouvelles tentatives : `
                : `${noms.length} fichiers n'ont pas pu être envoyés, même après ${RELANCES} nouvelles tentatives : `)
                + noms.join(', ')
                + '. Touchez ↻ sur chacun pour réessayer : tant qu\'un fichier n\'est pas envoyé, il ne sera pas importé.';
        };

        pond.on('processfile', (erreur, fichier) => {
            if (! erreur) {
                tentatives.delete(fichier.id);
                echecs.delete(fichier.id);
                afficher();

                return;
            }

            const deja = tentatives.get(fichier.id) ?? 0;

            if (deja < RELANCES) {
                tentatives.set(fichier.id, deja + 1);
                setTimeout(() => pond.processFile(fichier.id), 2000 * (deja + 1));

                return;
            }

            echecs.set(fichier.id, fichier.filename);
            afficher();
        });

        pond.on('removefile', (erreur, fichier) => {
            tentatives.delete(fichier.id);
            echecs.delete(fichier.id);
            afficher();
        });
    };
</script>
