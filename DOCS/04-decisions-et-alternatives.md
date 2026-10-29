# Filigrane — décisions prises, et comment en changer

Chaque décision est présentée ainsi : **ce qui a été fait**, **pourquoi**,
**l'autre option**, et **ce qu'il en coûterait de revenir dessus**.

Les décisions que tu as prises toi-même (nom, champs, comptes, hors-ligne) sont
marquées *validée*.

---

## Sommaire

**Produit et usage**
[D-01](#d-01) Nom · [D-02](#d-02) Pas de hors-ligne · [D-03](#d-03) Traitement synchrone ·
[D-04](#d-04) Application à la racine · [D-25](#d-25) Deux chemins de prise de vue ·
[D-27](#d-27) Charte provisoire · [D-30](#d-30) Diaporama côté navigateur ·
[D-31](#d-31) Deux exports · [D-35](#d-35) Page publique

**Données**
[D-07](#d-07) Artiste en texte libre · [D-08](#d-08) Mots-clés libres ·
[D-09](#d-09) Emplacement par mots-clés · [D-10](#d-10) Pas d'historique ·
[D-11](#d-11) Pas d'index plein texte · [D-19](#d-19) Numéros non recyclés ·
[D-20](#d-20) Corbeille · [D-28](#d-28) Millimètres entiers

**Images et mesure**
[D-05](#d-05) Originaux privés · [D-06](#d-06) Mesure par référence ·
[D-24](#d-24) HEIC converti côté serveur · [D-36](#d-36) Retouche non destructive ·
[D-37](#d-37) Proportions par la focale

**Sécurité et comptes**
[D-12](#d-12) Deux rôles · [D-13](#d-13) Pas de courriel ·
[D-14](#d-14) Code hors du web

**Technique**
[D-15](#d-15) Docker en dev seulement · [D-16](#d-16) Police système ·
[D-17](#d-17) Palette écrite à la main · [D-18](#d-18) Taille du texte *(revenue)* ·
[D-21](#d-21) Tests sur MariaDB · [D-22](#d-22) Mobile testé à part ·
[D-23](#d-23) Pas de mode SPA · [D-26](#d-26) Sauvegardes · [D-29](#d-29) Traductions ·
[D-32](#d-32) Exports hors Livewire · [D-33](#d-33) Images du PDF ·
[D-34](#d-34) Icône

---

<a id="d-01"></a>
## D-01 — Le nom « Filigrane » *validée*

**Choix.** L'application s'appelle Filigrane. Le domaine reste
`lise.dananlab.fr`.

**Pourquoi.** Le filigrane est la marque dans la pâte du papier que les
spécialistes du dessin ancien traquent pour dater et attribuer une feuille.
Le mot parle immédiatement à une ancienne du Louvre, il est court, il
s'écrit sans hésitation, et il ne promet rien de technique.

**Alternatives écartées.** *Cabinet* (clin d'œil au Cabinet des dessins, plus
institutionnel), *Lavis* (la technique monochrome), *Lise* (le prénom, chaleureux
mais muet sur le contenu).

**Coût d'un changement.** Faible, mais réparti : `APP_NAME` dans `.env`, le
manifeste, le composant du logo, les icônes, les titres de la documentation.
Une demi-heure. Le préfixe des numéros d'inventaire (`FIL-`) se change dans
`.env`, mais **les numéros déjà attribués ne doivent pas bouger** — ils
figurent peut-être déjà sur des étiquettes.

---

<a id="d-02"></a>
## D-02 — Installable, mais sans fonctionnement hors ligne *validée*

**Choix.** L'application s'installe sur l'écran d'accueil et dans le Dock, mais
exige une connexion. Sans réseau, une page claire l'explique.

**Pourquoi.** C'est ton choix, et il est bon. Une vraie saisie hors ligne
suppose une file d'attente locale, une synchronisation, et une politique de
résolution des conflits. C'est la partie la plus coûteuse et la plus fragile
d'une application de ce genre, pour un bénéfice nul dans un appartement
couvert par le Wi-Fi.

**Alternatives.**
- *Consultation hors ligne* : mettre en cache les fiches déjà vues. Environ
  une journée de travail, mais attention au quota accordé à Safari sur iOS —
  une collection de 250 dessins le dépasse vite.
- *Saisie hors ligne complète* : plusieurs jours, et un risque permanent de
  perte de données silencieuse.

**Coût d'un changement.** Le service worker (`public/sw.js`) est écrit pour
rester lisible : une mise en cache des fiches consultées s'y ajouterait sans
toucher au reste. La saisie hors ligne, en revanche, est une autre application.

---

<a id="d-03"></a>
## D-03 — Les images sont traitées pendant la requête, pas en file d'attente

**Choix.** Vignettes et versions web sont fabriquées au moment de l'envoi.

**Pourquoi.** Livewire envoie un fichier par requête HTTP : chaque requête ne
traite donc qu'une image, une à deux secondes, loin des 600 secondes
autorisées. Une file d'attente sur un hébergement mutualisé suppose un
*worker* déclenché par cron, donc jusqu'à une minute d'attente avant qu'une
vignette apparaisse — inacceptable quand on photographie dessin après dessin.

**Alternative.** `FILIGRANE_IMAGE_PROCESSING=queue` dans `.env`, plus une ligne
de cron pour `queue:work --stop-when-empty`. Tout le code est déjà écrit :
`ProcessPhoto` est un *job* à part entière.

**Coût d'un changement.** Une ligne de configuration et une ligne de cron.
Cela ne vaut la peine que si les images deviennent beaucoup plus lourdes
(numérisation à 600 ppp, par exemple).

---

<a id="d-04"></a>
## D-04 — L'application occupe la racine du domaine

**Choix.** `lise.dananlab.fr/` ouvre directement sur la collection, pas sur
`/admin`.

**Pourquoi.** Il n'y a pas de site public à côté. Une adresse à expliquer
plutôt que deux, et l'application installée s'ouvre sur le tableau de bord.

**Alternative.** `->path('admin')` dans `AdminPanelProvider`, si un jour une
galerie publique doit occuper la racine.

**Coût d'un changement.** Une ligne, plus `start_url` dans le manifeste. Mais
les raccourcis déjà posés sur les écrans d'accueil devraient être refaits.

---

<a id="d-05"></a>
## D-05 — Les originaux ne sont pas servis en statique

**Choix.** Les vignettes et les versions web sont servies directement par
Apache. Les photographies d'origine passent par une adresse qui vérifie que
vous êtes connecté.

**Pourquoi.** Une collection privée. Les dérivés portent des noms
impossibles à deviner, ce qui suffit pour des vignettes ; les originaux en
pleine définition — la seule copie de référence de ces œuvres — méritent une
vraie porte.

**Alternative.** Tout servir en statique : plus rapide, plus simple, mais la
pleine définition devient accessible à qui connaît l'adresse.
À l'inverse, tout passer par une route authentifiée coûterait 250 requêtes PHP
pour afficher une planche de vignettes — bien trop lent sur un mutualisé.

**Coût d'un changement.** `config/filigrane.php` : passer `originals_disk` à
`public` et déplacer les fichiers existants.

---

<a id="d-06"></a>
## D-06 — Les dimensions se calculent par calibration de référence *validée*

**Choix.** On photographie **un** dessin avec une règle dans le champ, on trace
le segment correspondant, on enregistre l'échelle comme profil. Toutes les
photographies de la même séance sont ensuite mesurées sans intervention.

**Pourquoi.** Tu voulais des dimensions calculées depuis la photo, sans que ta
belle-mère ait à manier un mètre. Il faut savoir qu'**une photographie seule ne
suffit pas** : sans une longueur connue dans le champ ou une distance de prise
de vue fiable, aucun calcul n'est possible. Les iPhone n'enregistrent pas la
distance de mise au point de façon exploitable.

La calibration par série résout le problème au bon endroit : l'effort est payé
une fois par séance, par toi, et non à chaque dessin.

**Précision obtenue.** 301 × 401 mm pour une feuille de 300 × 400 mm, soit
0,3 % d'écart. Suffisant pour un catalogue, insuffisant pour un constat d'état.

**Alternatives.**
- *EXIF seul* : implémenté comme voie secondaire, mais rarement exploitable.
  Vérifié le 29 octobre : les photos prises depuis Filigrane n'ont dans leur
  EXIF que l'orientation — ni appareil, ni focale, ni distance.
- *Saisie manuelle* : toujours possible, et jamais écrasée par un calcul.
- *LiDAR / ARKit* (iPhone Pro) : mesure réelle au centimètre près, mais il
  faudrait une application native. Hors de proportion ici.
- *Mire imprimée* (damier de calibration) : plus précis, plus encombrant à
  photographier.

**Coût d'un changement.** Les dimensions sont trois colonnes indépendantes
(`width_mm`, `height_mm`, `dimensions_source`). N'importe quelle autre méthode
peut les alimenter sans toucher au reste.

---

<a id="d-07"></a>
## D-07 — L'artiste est un texte libre, pas une fiche d'auteur

**Choix.** `artist` est une chaîne de caractères, indexée, avec autocomplétion
sur les valeurs déjà saisies.

**Pourquoi.** Une collection de dessins anciens est pleine d'attributions
incertaines : « entourage de », « d'après », « anonyme, école française ».
Une table d'auteurs imposerait une identité là où il n'y a qu'une hypothèse,
et obligerait à créer une fiche d'artiste pour écrire « anonyme ».

**Alternative.** Une table `artists` avec dates, école, notices. Pertinent le
jour où tu voudrais une page par artiste, ou un lien vers des bases externes
(Getty ULAN, Wikidata).

**Coût d'un changement.** Modéré et sans perte : une migration qui crée la
table, la peuple à partir des valeurs distinctes existantes (environ 80 lignes
de code), puis remplace le champ par une liste déroulante avec création à la
volée. Une demi-journée. Le champ texte actuel sert de brouillon à cette
normalisation.

---

<a id="d-08"></a>
## D-08 — Les mots-clés remplacent les champs qu'on ne connaît pas encore *validée*

**Choix.** Une table `tags` avec un champ `family` facultatif.

**Pourquoi.** Tes critères définitifs — technique, école, sujet, provenance —
ne sont pas arrêtés. Les mots-clés se créent sans migration, et `family`
permet de les ranger quand ils se multiplient.

**Alternative.** Des colonnes dédiées (`technique`, `support`, `ecole`) le jour
où un critère s'impose. Un mot-clé très employé est précisément le signal qu'il
mérite sa colonne.

**Coût d'un changement.** Faible : une migration qui ajoute la colonne, un
script qui la remplit depuis les mots-clés d'une famille, un champ de plus dans
le formulaire. Une à deux heures par critère.

---

<a id="d-09"></a>
## D-09 — L'emplacement physique passe par les mots-clés

**Choix.** Pas de table d'emplacements. « Carton 3 », « Encadré — salon » sont
des mots-clés de la famille *emplacement*.

**Pourquoi.** Tu n'as pas retenu l'emplacement parmi les champs à prévoir. Les
mots-clés le permettent quand même, sans rien imposer.

**Alternative.** Une table `locations` avec hiérarchie (pièce → meuble →
carton → chemise), utile pour un récolement ou un déménagement.

**Coût d'un changement.** Une demi-journée, reprise des mots-clés existants
comprise.

---

<a id="d-10"></a>
## D-10 — Pas d'historique des modifications

**Choix.** On garde qui a créé la fiche et qui l'a modifiée en dernier, pas la
suite des versions.

**Pourquoi.** Deux utilisateurs, pas d'édition simultanée attendue, et une
table d'historique sur deux champs HTML grossit vite pour un bénéfice
théorique.

**Alternative.** Un paquet comme `owen-it/laravel-auditing` ajoute l'historique
complet en une heure, avec une table par entité.

**Coût d'un changement.** Faible, mais l'historique ne vaudra qu'à partir du
jour où il est activé : il ne reconstruira pas le passé.

---

<a id="d-11"></a>
## D-11 — Pas d'index plein texte

**Choix.** La recherche utilise `LIKE` sur le numéro d'inventaire, l'artiste et
la période.

**Pourquoi.** 250 fiches. Un `LIKE` répond en quelques millisecondes. Un index
plein texte sur des champs HTML renverrait en plus des mots parasites issus du
balisage.

**Alternative.** `fullText()` sur `description` et `note`, ou Laravel Scout, si
la collection dépassait quelques milliers de fiches ou si tu voulais chercher
*dans* les descriptions.

**Coût d'un changement.** Une migration et quelques lignes dans la table.
Une heure. À envisager si tu veux retrouver « filigrane au raisin » dans les
descriptions.

---

<a id="d-12"></a>
## D-12 — Deux rôles, appliqués par des politiques *validée*

**Choix.** `admin` (toi) et `curator` (ta belle-mère). Les droits sont écrits
dans `app/Policies/`, pas dans l'interface.

**Pourquoi.** Un bouton masqué n'est pas une protection : une adresse tapée à
la main doit être refusée de la même façon. Les politiques s'appliquent
partout, y compris en ligne de commande.

Ce qui est réservé à l'administrateur : les comptes, les profils d'échelle, et
la **suppression définitive** — la seule action irréversible.

**Alternative.** Un rôle unique partagé (plus simple à expliquer, aucune
traçabilité), ou des permissions fines avec `spatie/laravel-permission`
(surdimensionné à deux personnes).

**Coût d'un changement.** Une colonne `role` et cinq fichiers de politique.
Ajouter un rôle : deux heures.

---

<a id="d-13"></a>
## D-13 — Pas de réinitialisation de mot de passe par courriel

**Choix.** Les mots de passe se remettent depuis l'écran **Comptes**, ou en
ligne de commande : `php artisan filigrane:compte adresse@exemple.fr`.

**Pourquoi.** Un envoi de courriel ajoute une dépendance SMTP, un risque de
message classé en indésirable, et une porte de plus. À deux utilisateurs dont
l'un est administrateur, cela n'apporte rien.

**Alternative.** `->passwordReset()` dans le panneau et des identifiants SMTP
dans `.env`. L'hébergeur en fournit.

**Coût d'un changement.** Une ligne de code, cinq de configuration. Un quart
d'heure.

---

<a id="d-14"></a>
## D-14 — Le code vit en dehors de la racine web

**Choix.** L'application est dans `~/apps/filigrane`, la racine web ne contient
qu'un contrôleur d'entrée et les fichiers statiques.

**Pourquoi.** Beaucoup de déploiements Laravel sur mutualisé placent le projet
entier sous la racine web et comptent sur `.htaccess` pour empêcher d'atteindre
`.env`. Une règle qui saute lors d'une migration d'hébergeur, et les
identifiants de la base deviennent publics. Ici, il n'y a rien à protéger : les
fichiers sensibles ne sont pas atteignables, quelle que soit la configuration.

**Alternative.** Le tout-en-un, plus simple à déployer à la main.

**Coût d'un changement.** Le déploiement est scripté
(`scripts/deployer.sh`) : revenir en arrière demanderait de le réécrire, sans
aucun gain.

---

<a id="d-15"></a>
## D-15 — Docker en développement, pas en ligne *validée*

**Choix.** L'environnement local reproduit l'hébergement : Apache, PHP 8.4.27,
MariaDB 11.4, Imagick avec HEIC. En ligne, c'est l'hébergement mutualisé tel
quel.

**Pourquoi.** o2switch ne propose pas Docker. Et c'est très bien ainsi :
reproduire *exactement* les versions de l'hébergeur en local vaut mieux que
d'avoir deux environnements différents. `composer.json` fige même la résolution
des dépendances sur PHP 8.4, pour qu'un paquet incompatible ne puisse pas
s'installer en local.

**Alternative.** Un VPS avec Docker en production : contrôle total,
mais une machine à administrer, à mettre à jour et à sauvegarder. Pour une
application familiale, c'est un fardeau.

**Coût d'un changement.** Le `Dockerfile` existe déjà et sert de base à une
image de production. Compter une journée, puis de l'entretien à vie.

---

<a id="d-16"></a>
## D-16 — Police système, pas de police téléchargée

**Choix.** Les titres utilisent la pile `ui-serif, New York, Georgia`.

**Pourquoi.** Sur Mac et sur iPhone, cela donne *New York*, un romain de grande
qualité. Aucun téléchargement, aucun délai d'affichage, aucune requête vers un
tiers, et rien à charger quand le réseau est faible.

**Alternative.** Une police servie depuis nos propres fichiers (Spectral,
Source Serif) si la charte graphique en impose une. Compter 100 à 200 Ko.

**Coût d'un changement.** Une ligne dans `theme.css` et les fichiers de police
à déposer. Une heure.

---

<a id="d-17"></a>
## D-17 — La gamme sanguine est écrite à la main

**Choix.** Les onze nuances de la couleur d'accent sont définies une par une
dans `AdminPanelProvider`.

**Pourquoi.** Filament 5 fabrique une gamme à partir d'une seule teinte, mais
il n'en retient que l'angle de couleur et impose sa propre saturation. Partant
de la sanguine `#A3553E` — une craie, donc une couleur rabattue — il produisait
un saumon vif, très loin de l'esprit du fonds.

La nuance 600, celle des boutons, atteint **6,5:1** de contraste sur blanc,
au-delà du seuil AA de 4,5:1 : une marge utile passé quatre-vingt-dix ans.
C'est vérifié
automatiquement par un test.

**Alternative.** `Color::hex('#A3553E')` : une ligne au lieu de onze, mais une
couleur qui n'est plus la bonne.

**Coût d'un changement.** Trivial, et c'est précisément ce que la charte
graphique à venir viendra remplacer (voir [D-27](#d-27)).

---

<a id="d-18"></a>
## D-18 — Le réglage de taille du texte a été retiré *revenue*

**Ce qui avait été fait.** Deux boutons **A−** et **A+** dans la barre
supérieure réglaient la taille de toute l'interface, de 15 à 23 px, le choix
étant conservé par navigateur — un iPhone tenu à 30 cm et un grand écran à
70 cm n'appelant pas le même réglage.

**Pourquoi c'est revenu.** L'hypothèse était fausse. Quatre-vingt-quinze ans
ne veut pas dire mal voir : l'utilisatrice voit très bien. Deux boutons
inutiles dans la barre supérieure, c'est deux boutons de trop — et ils
alourdissaient l'écran.

**Ce qui reste.** La base à **17 px** plutôt que 16, les cibles tactiles de
44 px et le contraste renforcé : ces trois-là ne coûtent rien à personne et
profitent à tout le monde. C'est le bon niveau d'attention.

**Pour y revenir.** Une trentaine de lignes : un script dans
`resources/views/pwa/head.blade.php` appliquant une variable CSS avant le
premier rendu, un gabarit de boutons, un point d'accroche `TOPBAR_END` dans
`AdminPanelProvider`. Une heure. La leçon vaut mieux que le code : demander
avant de compenser.

---

<a id="d-19"></a>
## D-19 — Les numéros d'inventaire ne sont jamais recyclés *validée*

**Choix.** `FIL-0001`, `FIL-0002`… attribués à l'enregistrement. Une fiche mise
à la corbeille garde son numéro, qui reste réservé.

**Pourquoi.** Un numéro figure peut-être déjà sur une étiquette, dans un
carnet ou dans un courrier. S'il désignait un jour un autre dessin, la
référence deviendrait fausse sans que personne s'en aperçoive.

Le numéro est attribué **au moment de l'enregistrement** et non à l'ouverture
du formulaire : deux fiches ouvertes en même temps ne peuvent pas réclamer le
même.

**Alternative.** Le préfixe se change dans `.env`
(`FILIGRANE_INVENTORY_PREFIX`). Le champ reste modifiable à la main pour
reprendre un inventaire existant (« LOUVRE-RF-1234 »).

**Coût d'un changement.** Nul pour le préfixe des **futurs** numéros. Renuméroter
l'existant serait une mauvaise idée, pour la raison ci-dessus.

---

<a id="d-20"></a>
## D-20 — Corbeille sur les fiches, suppression immédiate sur les photographies

**Choix.** Supprimer une fiche la met à la corbeille, d'où elle se restaure.
Supprimer une photographie efface aussitôt ses fichiers.

**Pourquoi.** Une fiche, c'est du texte : la garder ne coûte rien. Une
photographie, c'est plusieurs mégaoctets : une corbeille d'images ferait
grossir le stockage sans fin.

**Attention.** La suppression définitive d'une fiche efface aussi ses
photographies, originaux compris. L'application le dit explicitement avant.

**Alternative.** Une corbeille sur les photographies aussi : une colonne
`deleted_at` et un ménage périodique. Deux heures.

**Coût d'un changement.** Faible.

---

<a id="d-21"></a>
## D-21 — Les tests tournent sur MariaDB, pas sur SQLite

**Choix.** Une base `filigrane_test` dans le même conteneur MariaDB.

**Pourquoi.** Le schéma interroge des colonnes JSON par chemin
(`exif->camera`), et la traduction SQL diffère d'un moteur à l'autre. Un test
vert sur SQLite ne prouverait rien pour la production. C'est plus lent de
quelques secondes, et c'est honnête.

**Alternative.** SQLite en mémoire : des tests deux fois plus rapides, et des
angles morts exactement là où ça compte.

**Coût d'un changement.** Cinq lignes dans `phpunit.xml`. À ne pas faire.

---

<a id="d-22"></a>
## D-22 — Les parcours fonctionnels ne sont pas rejoués en 390 px

**Choix.** La suite complète tourne au format Mac. Une suite distincte vérifie
ce qui change sur un téléphone : menu replié, planche sur une colonne, fiche
empilée, bouton de prise de vue.

**Pourquoi.** Chromium étroit n'est pas Safari sur iOS. Rejouer les quarante
parcours à 390 px doublerait le temps sans rien révéler de plus, tout en
multipliant les sélecteurs fragiles.

**Alternative.** Un vrai Safari piloté par WebKit (Playwright le propose) ou un
service d'appareils réels. Plus proche du vrai, nettement plus lent.

**Coût d'un changement.** `testIgnore` à retirer dans
`playwright.config.js`. Mais **le test sur un vrai iPhone reste indispensable
et manuel** : accès caméra en mode installé, HEIC, clavier qui recouvre un
champ.

---

<a id="d-23"></a>
## D-23 — Pas de navigation en mode SPA

**Choix.** `->spa()` n'est pas activé dans Filament.

**Pourquoi.** Ce mode accélère la navigation mais remplace les chargements de
page par du JavaScript, ce qui casse facilement les composants qui gardent un
état : le viseur de la caméra, le canevas de mesure. Le gain de vitesse ne vaut
pas ce risque.

**Alternative.** `->spa()` avec exclusion des pages sensibles.

**Coût d'un changement.** Une ligne, mais il faudrait éprouver sérieusement le
studio photo et l'outil de mesure.

---

<a id="d-24"></a>
## D-24 — Le HEIC est converti sur le serveur

**Choix.** Les photos d'iPhone sont conservées en HEIC et converties en JPEG
pour l'affichage, par Imagick.

**Pourquoi.** L'hébergeur dispose d'Imagick avec le délégué HEIC — ce qui est
loin d'être acquis partout, et c'est la raison pour laquelle l'environnement
Docker l'installe explicitement. L'original n'est jamais recompressé.

**Alternative.** Convertir dans le navigateur avant l'envoi (`heic2any`) :
allège le serveur, mais fait perdre la qualité et les métadonnées d'origine.

**Coût d'un changement.** Déconseillé. L'original est la pièce d'archive.

**À vérifier.** Aucune image HEIC n'a pu être fabriquée pour les tests —
ImageMagick lit ce format mais ne sait pas l'écrire. Dépose une vraie photo
d'iPhone dans `tests/fixtures/dessin-iphone.heic` et le test dédié s'activera.

---

<a id="d-25"></a>
## D-25 — Deux façons de photographier

**Choix.** Le studio photo propose l'appareil natif de l'iPhone **et** un
viseur intégré.

**Pourquoi.** Ils ne servent pas la même chose. L'appareil natif donne la
pleine définition, la stabilisation et la mise au point d'Apple : c'est lui
qu'il faut pour des clichés d'archive. Le viseur intégré est plus rapide pour
enchaîner une série, mais limité à ce que le navigateur veut bien fournir — la
page affiche d'ailleurs la définition réellement obtenue, pour qu'on puisse en
juger.

**Alternative.** N'en garder qu'un. Le viseur seul dégraderait la qualité
d'archive ; l'appareil natif seul ralentirait les séries.

**Coût d'un changement.** Supprimer une section de la page. Un quart d'heure.

---

<a id="d-26"></a>
## D-26 — Sauvegarde quotidienne sur le serveur, rapatriement à la demande

**Choix.** Une copie de la base chaque nuit à 4 h 10, quinze jours conservés
sur le serveur. Le rapatriement vers ton Mac — base **et** photographies — se
lance avec `make sauvegarde`.

**Pourquoi.** Une copie qui reste sur le serveur protège de la fausse manœuvre,
pas de la panne. Une copie chez toi protège des deux. Les photographies ne sont
pas copiées chaque nuit : elles pèsent des gigaoctets et ne changent pas.

**Alternative.** Sauvegarde automatique vers un stockage distant (S3, Backblaze)
avec `spatie/laravel-backup`. Compter deux heures et quelques euros par mois.

**Coût d'un changement.** Faible. À envisager sérieusement dès que la
collection sera entièrement photographiée : ces images seront irremplaçables.

> **À faire dès maintenant :** lance `make sauvegarde` une première fois, pour
> vérifier que le rapatriement fonctionne. Une sauvegarde jamais restaurée n'est
> pas une sauvegarde.

---

<a id="d-27"></a>
## D-27 — La charte graphique actuelle est provisoire

**Choix.** Papier crème, encre, un seul accent sanguine, titres en romain.
L'icône et le logo, eux, viennent de Claude Design : voir [D-34](#d-34).

**Pourquoi.** Il fallait quelque chose de cohérent pour travailler, et qui
s'efface devant des reproductions en noir et blanc. C'est volontairement
sobre.

**La suite.** `DOCS/05-prompt-charte-graphique.md` contient le texte à donner à
Claude Design. Ce qu'il produira remplacera : les onze nuances de la couleur
d'accent, la pile typographique, et les deux fichiers SVG du logo.

**Coût d'un changement.** Une à deux heures, concentrées sur trois fichiers :
`AdminPanelProvider` (couleurs), `theme.css` (typographie), `resources/icones/`
(logo, puis `make icones` pour régénérer les PNG).

---

<a id="d-28"></a>
## D-28 — Les dimensions sont stockées en millimètres entiers

**Choix.** `width_mm` et `height_mm` sont des entiers. L'affichage en
centimètres se fait à la lecture.

**Pourquoi.** L'arithmétique sur des entiers ne dérive pas. Une dimension
calculée depuis une photographie tombe naturellement sur un nombre de
millimètres, et la précision réelle de la méthode est de l'ordre du millimètre :
afficher des dixièmes de millimètre serait mentir.

**Alternative.** Un décimal en centimètres. Aucun gain, et des arrondis à
surveiller partout.

**Coût d'un changement.** Une migration. Déconseillé.

---

<a id="d-29"></a>
## D-29 — 168 traductions manquantes ont été écrites à la main

**Choix.** Les clés absentes de la traduction française livrée avec Filament
5.11 ont été comblées dans `lang/vendor/`.

**Pourquoi.** Sans cela, l'interface affichait des identifiants bruts du
genre `filament-panels::global-search.field.instructions` — lus tels quels par
un lecteur d'écran, et visibles par endroits. Dans une application destinée à
une personne âgée francophone, c'est inacceptable.

Laravel fusionne ces fichiers par-dessus ceux du paquet : seules les clés
manquantes y figurent, et une mise à jour de Filament n'écrasera rien.

**À refaire après chaque mise à jour de Filament :**

```bash
docker compose exec app php artisan filament:check-translations fr
```

**Coût d'entretien.** Quelques minutes par mise à jour majeure.

---

<a id="d-30"></a>
## D-30 — Le diaporama est joué entièrement dans le navigateur

**Choix.** La page `/diaporama` envoie toutes les planches décrites d'un bloc
— texte, mots-clés, adresses des images — et Alpine les feuillette sans
jamais revenir au serveur. Seules les images sont chargées à la demande, la
suivante et la précédente d'abord.

**Pourquoi.** À deux ou trois cents fiches, l'ensemble du texte pèse quelques
centaines de kilo-octets : moins qu'une seule photographie. En échange, passer
d'un dessin à l'autre est instantané, au clavier comme au doigt. Une
pagination côté serveur aurait coûté un aller-retour à chaque flèche — un
diaporama qui attend n'est plus un diaporama.

**Alternative.** Un composant Livewire paginé. Correct à dix mille fiches,
inutilement lent à deux cents.

**Le point de bascule.** Vers deux mille fiches, le temps de chargement de la
page deviendrait sensible. Il faudrait alors envoyer les planches par paquets
de cinquante, en avançant la fenêtre.

**Coût d'un changement.** Une demi-journée.

---

<a id="d-31"></a>
## D-31 — Deux exports, parce qu'il y a deux usages

**Choix.** Un tableur **sans images** (XLSX, via *openspout*) et un catalogue
**avec images** (PDF, via *dompdf*). Tous deux suivent exactement ce que la
liste affiche : onglet ouvert, filtres posés, recherche en cours.

**Pourquoi.** Ce ne sont pas deux formats du même document. Le tableur sert à
trier, compter, recouper — ses dimensions sortent donc en **nombres**, en
millimètres, et non en « 40,1 × 30,1 cm », qu'aucun tableur ne saurait
additionner. Le PDF sert à montrer et à imprimer : une page par dessin,
reproduction comprise.

Exporter « ce qu'on voit » évite un second jeu de critères à comprendre.

**Le choix des bibliothèques.** *openspout* écrit en flux : la mémoire ne
dépend pas du nombre de lignes. *dompdf* est en PHP pur — un moteur à base de
navigateur sans tête (Browsershot, Puppeteer) serait plus fidèle, mais
n'existe pas sur un hébergement mutualisé.

**Alternative.** `filament/actions` fournit un `ExportAction` tout fait, mais
il s'appuie sur des traitements en file d'attente, que l'hébergement ne permet
pas (voir [D-03](#d-03)).

**Coût d'un changement.** Les deux services sont indépendants :
`app/Services/ExportTableur.php` et `app/Services/ExportCatalogue.php`,
environ 150 lignes chacun.

---

<a id="d-32"></a>
## D-32 — Les exports ne repassent pas par Livewire

**Choix.** L'action prépare le fichier dans `storage/app/private/exports/`,
puis renvoie le navigateur vers une **route signée**, valable dix minutes, qui
diffuse le fichier en flux et l'efface après remise.

**Pourquoi.** Tout fichier retourné depuis une action Livewire est encodé en
base64 **dans la réponse JSON**. Pour un catalogue de deux cents planches —
plusieurs dizaines de mégaoctets — cela veut dire un tiers de mémoire en plus
côté serveur, autant côté navigateur, et une réponse que rien ne peut
diffuser progressivement. Sur un mutualisé, c'est la limite de mémoire
assurée.

**Les restes.** Un export préparé puis jamais récupéré reste sur le disque :
`filigrane:menage` efface chaque nuit ceux qui ont plus d'une heure.

**Alternative.** `response()->download()` directement depuis l'action : trois
lignes de moins, et un plafond de mémoire à quelques dizaines de fiches.

---

<a id="d-33"></a>
## D-33 — Les images du catalogue PDF sont refabriquées à 1 200 px

**Choix.** Chaque reproduction est réduite à 1 200 px de large, en JPEG de
qualité 78, dans un dossier temporaire effacé à la fin de l'export.

**Pourquoi.** Le dérivé « web » fait 2 400 px : bien plus que ce qu'une page
A4 peut rendre. À 160 mm de large, 1 200 px donnent environ 190 points par
pouce — au-delà, on alourdit le fichier sans que l'œil y gagne. La miniature,
elle, ne convient pas : elle est **recadrée au carré**, ce qui couperait les
dessins.

Ordre de grandeur : 250 planches donnent un PDF d'environ 30 Mo.

**Alternative.** Insérer le dérivé web tel quel : un fichier de 200 Mo et un
risque sérieux de dépassement mémoire.

**Coût d'un changement.** Une constante dans `ExportCatalogue`.

---

<a id="d-34"></a>
## D-34 — L'icône vient de Claude Design, rendue par un navigateur

**Choix.** Une feuille de papier au coin replié, un crayon sanguine `#8E462F`
posé en diagonale, un filigrane en volute dans la feuille, le tout sur un fond
de papier vergé `#F4EEE2`. Dessinée plein bord, sans coins arrondis. Contraste
motif sur fond : **5,93 : 1**.

Quatre fichiers plutôt qu'un : `icone.svg`, `icone-maskable.svg`,
`favicon.svg` simplifié pour 32 pixels, et `logo.svg` — le motif seul, sans
fond, repris dans le crème de l'interface. Un cinquième, `logo-sombre.svg`,
est **dérivé** du précédent à la génération : le logo n'ayant pas de tuile,
son papier doit prendre la couleur de la surface, et celle-ci change avec le
thème. Une seule couleur diffère, il n'y a donc pas deux dessins à tenir à
jour.

**Pourquoi quatre.** Ce qui tient à 512 pixels ne tient pas à 32 : le favicon
perd ses vergeures et appuie ses pontuseaux. Et le logo posé à côté du
mot-symbole n'a pas de tuile : lui donner le fond de l'icône ferait une
vignette dans la barre.

Fond clair, parce qu'une tuile sombre de plus fait un trou sur un écran
d'accueil, et parce qu'un filigrane se regarde à contre-jour. Pas de coins
arrondis : iOS, Android et les navigateurs appliquent leur propre masque.

**Le rendu est passé d'ImageMagick à Chromium.** C'est le vrai enseignement de
cette étape. Le moteur SVG interne d'ImageMagick compose mal les
transformations imbriquées : il plaçait le crayon, tourné de 60°, complètement
hors du cadre — **sans message ni code d'erreur**. Les PNG étaient produits,
de la bonne taille, et faux.

`scripts/generer-icones.mjs` rend donc chaque SVG dans le Chromium déjà
installé pour les tests, à la taille exacte voulue, et capture l'image.
`scripts/generer-favicon-ico.php` en tire le `.ico` par simple conversion de
pixels — le seul travail où ce moteur ne peut pas se tromper, puisqu'il
n'intervient pas.

Deux bénéfices : le rendu est **exactement** celui que verront les visiteurs,
et le dessin n'a plus à se restreindre aux aplats — `stroke`, `clipPath` et
dégradés sont désormais permis.

**Ce qui reste à surveiller.** Le cadrage. Une forme tournée qui sort du
`viewBox` est rognée en silence : c'est arrivé au `logo.svg` livré, dont la
pointe du crayon atteignait x ≈ 59,6 pour un cadre s'arrêtant à 58. Corrigé en
élargissant le `viewBox` de deux unités.

**Les métadonnées sont retirées.** Les fichiers livrés portaient un manifeste
C2PA de 7,7 Ko — plus lourd que le dessin — qu'il aurait fallu servir à chaque
visiteur, favicon compris. Supprimé à l'intégration.

**Alternative.** Garder ImageMagick en lui adjoignant librsvg
(`librsvg2-bin` dans l'image Docker). Cela aurait marché — les icônes sont
fabriquées en local, le serveur n'en régénère jamais — mais ajoutait une
dépendance système pour un résultat moins fidèle qu'un navigateur, que le
projet installe déjà.

**Coût d'un changement.** `resources/icones/*.svg` puis `make icones`. La
marche à suivre complète est dans [07 — Icône](07-prompt-icone.md).

---

<a id="d-35"></a>
## D-35 — Une page de présentation, publique et sans données

**Choix.** `/a-propos` est accessible sans compte. Elle présente le projet,
montre trois captures d'écran prises sur un **jeu d'essai**, et crédite
l'auteur.

**Pourquoi.** L'application a vocation à figurer dans un portfolio : une page
qui demande un mot de passe n'y sert à rien. Elle ne montre en revanche aucune
donnée réelle — ni fiche, ni photographie de la collection, ni nom de
personne — et un test vérifie qu'aucun numéro d'inventaire n'y apparaît.

Les aperçus se régénèrent par `make apercus`, qui crée d'abord des fiches
d'essai plausibles (`filigrane:jeu-d-essai`).

**Alternative.** La garder derrière l'authentification : plus prudent, et
inutile puisqu'elle ne contient rien.

**Coût d'un changement.** Une ligne dans `routes/web.php` pour la refermer.

---

<a id="d-36"></a>
## D-36 — La retouche enregistre des réglages, jamais une image

**Choix.** Redresser, recadrer, tourner et éclaircir une photographie, c'est
enregistrer quatre coins, un quart de tour, une luminosité et un contraste
dans la colonne `retouch`. La version web et la miniature sont refabriquées à
partir de l'original et de ces réglages ; l'original n'est pas touché. La
version web d'avant retouche est mise de côté (`raw_web_path`) : c'est sur
elle qu'on replace les coins pour reprendre la retouche.

**Pourquoi.** La première séance — 32 œuvres encadrées, sur quatre étages —
a donné des trapèzes, des cadres voisins dans le champ et des étages hauts
plus sombres. Il fallait les reprendre, mais l'original reste la pièce
d'archive ([D-24](#d-24)) : une retouche ratée ne doit rien coûter. Et comme
tout ce qui s'affiche, se projette ou s'exporte passe par la version web,
la retouche se propage partout sans que rien d'autre ne change.

Le redressement n'est pas qu'esthétique. Sur un cadre pris de biais,
l'échelle varie d'un bord à l'autre de l'image ; une fois redressé, une seule
échelle vaut partout, et la carte bancaire contre la vitre donne les deux
dimensions.

**Trois conséquences, voulues.**
- Chaque retouche écrit ses fichiers **sous un nom neuf**. Le navigateur garde
  les images un an en cache (`.htaccess`) : une image changée sous le même nom
  resterait invisible à qui l'a déjà vue — c'est arrivé à l'icône.
- Une retouche qui change la géométrie **efface la mesure faite sur la
  photographie** (échelle et cadre bleu désigneraient d'autres pixels), mais
  **pas les dimensions de la fiche**. Une retouche de la seule lumière ne
  touche à rien.
- Une photographie redressée **n'entre plus dans les profils d'échelle** : elle
  n'a plus l'échelle des clichés bruts de sa série.

**L'aperçu est calculé par le navigateur.** L'homographie qui envoie les
quatre coins sur un rectangle est appliquée à l'image par `matrix3d()`, et la
lumière par les filtres CSS `brightness()` et `contrast()`. Le serveur applique
exactement la même formule (`y = C·(L·x − 0,5) + 0,5`) : ce qu'on voit est ce
qu'on obtient, sans aller-retour pendant qu'on déplace un coin.

**Ce que la retouche ne fait pas.** Les reflets dans la vitre : rien ne les
retire proprement, cela se règle à la prise de vue. La dominante jaune d'un mur
éclairé de près : un réglage de balance des blancs s'ajouterait au même
endroit, si le besoin se confirme.

**Alternatives.**
- *Retoucher dans l'app Photos de l'iPhone avant l'envoi* : outil familier,
  mais l'original reçu serait déjà modifié, et le redressement de Photos ne
  connaît pas les vraies proportions de l'œuvre (voir [D-37](#d-37)).
- *Écraser les dérivés sans garder de réglages* : plus simple, mais impossible
  de reprendre une retouche, seulement de la refaire depuis le début.
- *Détection automatique des coins* : le détourage échoue sur un mur tendu de
  toile et des cadres voisins ([D-06](#d-06)) ; poser quatre coins prend dix
  secondes.

**Coût d'un changement.** `App\Services\RetouchePhoto` et la page
`RetoucherPhoto`. Ajouter un réglage (balance des blancs, netteté) : une clé de
plus dans `retouch`, une ligne d'Imagick, un curseur. Une heure.

---

<a id="d-37"></a>
## D-37 — Les vraies proportions viennent de la focale, et on demande le zoom

**Choix.** Le rectangle redressé ne prend pas les proportions apparentes du
trapèze, mais celles que calcule la méthode de Zhang et He (*Whiteboard
scanning and image enhancement*, 2007) : les côtés du cadre réel sont
perpendiculaires, et cette contrainte, avec la focale, fixe le rapport largeur
/ hauteur. La focale vient de l'EXIF quand il y en a une ; sinon, l'outil
demande **quel zoom affichait l'appareil** (0,5×, 1×, 2×, 3×, 5×), 1× par
défaut.

**Pourquoi.** Quatre coins disent comment redresser, pas quelles proportions
donner au résultat : un trapèze donné peut venir d'un cadre allongé vu de
face comme d'un cadre plus haut vu très en biais. Sur un cadre de 300 × 400 mm
levé de 25 à 35°, le rapport apparent se trompe de 10 à 22 % ; le calcul par
la focale retrouve 0,75 exactement. Un catalogue de dessins qui allonge les
figures de 10 % n'est pas acceptable.

Mais les photos prises depuis Filigrane arrivent sans EXIF ([D-06](#d-06)). D'où la
question du zoom : c'est le chiffre que l'iPhone affiche à l'écran, et le seul
que la personne qui photographie connaisse. Le prendre faux coûte cher — un
cadre levé de 25° photographié à 2× et déclaré à 1× sort 7 % trop large —,
d'où la consigne de garder un même zoom pour une séance
([08](08-prise-de-vue.md)).

**Le calcul est écrit deux fois**, en PHP (`App\Support\Perspective`) et en
JavaScript (l'aperçu), pour que l'aperçu montre ce que le serveur produira. Le
test `RetoucheTest` vérifie la version PHP sur un cadre photographié par un
appareil simulé dont on connaît tout.

**Alternatives.**
- *Proportions apparentes* (moyenne des côtés opposés) : exactes de face,
  fausses de 10 % et plus dès que l'appareil se lève. C'est le repli quand le
  calcul n'a plus de sens (coins presque alignés).
- *Estimer la focale à partir des coins* : possible quand le cadre est vu à la
  fois d'en bas et de côté, impossible quand il n'est vu que d'en bas — le cas
  le plus courant ici. Et des coins posés au doigt la rendraient instable.
- *Saisir les dimensions connues de l'œuvre* : exact, mais on retouche le plus
  souvent avant d'avoir mesuré.

**Coût d'un changement.** Les focales sont dans `config/filigrane.php`
(`zooms`). Si l'on envoyait un jour les photos avec leur EXIF intact, la
question disparaîtrait d'elle-même : l'outil ne la pose pas quand l'EXIF
répond.

---

## Ce qui n'a pas été fait, et qu'on pourrait vouloir

| Idée | Intérêt | Effort estimé |
|---|---|---|
| Galerie publique partageable par lien | Montrer à la famille, à un chercheur | 1 à 2 jours |
| Rapprochement avec des bases externes (Getty, Wikidata) | Vérifier une attribution | 2 jours |
| Lecture des filigranes par transparence | Photographier le filigrane lui-même | prise de vue, pas de code |
| Dictée vocale des descriptions | Écrire devient fatigant | la dictée d'iOS fonctionne déjà dans les champs |
| Impression d'étiquettes avec code | Retrouver un dessin dans un carton | 1 jour |

Aucune n'est nécessaire pour commencer à cataloguer. Elles se décideront quand
les premières dizaines de fiches auront montré ce qui manque vraiment.
