# Filigrane — guide de prise de vue

Écrit pour : toi, appareil en main, devant les cartons ou devant un mur.

Le fonctionnement interne de la mesure est décrit dans
[02 — Architecture technique](02-architecture-technique.md#la-mesure-des-dimensions).
Ce document-ci ne dit que ce qu'il faut faire, et dans quel ordre.

---

## La règle qui prime sur toutes les autres

**La photographie est irréversible, la mesure ne l'est pas.**

Tu ne vas pas redécrocher cinquante cadres ni ressortir deux cents feuilles
d'un carton. En revanche, l'original de chaque photographie est conservé, et le
bouton **Mesurer** reste disponible sur la fiche indéfiniment.

Donc : **mets une référence de longueur dans le champ, toujours.** Même quand
tu penses ne pas en avoir besoin. Cela ne coûte rien au moment du déclenchement
et t'ouvre toutes les options ensuite. Sans elle, une photographie est
définitivement non mesurable.

La carte bancaire est la meilleure référence de terrain : rigide, toujours sur
toi, et déjà dans la liste de l'application (85,6 mm sur le côté long).

| Référence proposée | Longueur |
|---|---|
| Carte bancaire, côté long | 85,6 mm |
| Carte bancaire, côté court | 53,98 mm |
| Règle | 100 ou 200 mm |
| Feuille A4 | 297 ou 210 mm |
| Pièce de 2 € | 25,75 mm |

Plus la référence est longue, plus la mesure est précise : un trait de 200 mm
tracé à deux pixels près est dix fois plus fiable qu'un trait de 20 mm.

---

## Les feuilles des cartons : une séance, un profil

C'est le cas confortable. Tu installes un poste et tu n'en bouges plus.

1. **Fond uni et contrasté.** Une feuille claire sur une table sombre. C'est ce
   qui permet le détourage automatique du cadre.
2. **À plat, bien en face**, l'appareil au-dessus du centre de la feuille.
3. **Photographie le premier dessin avec une règle posée à côté**, dans le même
   plan que la feuille.
4. Sur la fiche, **Mesurer** : trait rouge sur la règle, longueur déclarée,
   cadre bleu ajusté sur la feuille.
5. Coche **« Enregistrer cette échelle comme profil »**, nomme-le
   (« séance du 3 novembre »), et coche **« Mesurer tout de suite les autres
   photographies de la série »**.

À partir de là, toutes les photographies suivantes sont mesurées seules.

**Ce qui ne doit plus changer** — sinon le profil cesse de s'appliquer, et
c'est voulu :

- l'appareil ;
- la focale (ne zoome pas, ne passe pas de l'objectif principal à l'ultra
  grand-angle) ;
- la hauteur de l'appareil au-dessus de la table ;
- la définition des photographies.

Si l'un des quatre bouge, refais une photo avec la règle et enregistre un
nouveau profil. L'application ne mélangera pas les deux : elle n'applique un
profil que si l'appareil, la focale et la définition correspondent tous les
trois.

---

## Les œuvres encadrées au mur : une référence par photo

Là, la distance change à chaque pièce — et ce n'est pas un problème.

**Le profil n'est pas le mécanisme, c'est un raccourci.** Si la référence est
*dans* la photo, le rapport pixels/millimètres se lit dans l'image elle-même.
Tu peux être à 80 cm d'un petit cadre et à 2 m d'un grand.

1. **Tiens la carte bancaire à plat contre la vitre**, dans un coin, et
   déclenche de l'autre main.
2. Plus tard, sur la fiche : **Mesurer**, trait rouge sur la carte, cadre bleu
   sur l'œuvre, **Enregistrer**.
3. **Laisse la section « Appliquer à toute la série » repliée.** La case est
   décochée par défaut : tu n'as rien à faire.

Compte une quinzaine de secondes par pièce. Pour une cinquantaine de cadres,
une vingtaine de minutes, à faire tranquillement au bureau.

> **Le détourage automatique ne t'aidera pas ici.** Il fonctionne en rognant un
> fond uni ; un mur, une cimaise et un cadre ne lui conviennent pas. Le cadre
> bleu partira donc de sa position par défaut, à placer à la main.

### Le cas particulier qui va plus vite

Si tu peux reculer et photographier plusieurs cadres **sans te déplacer ni
zoomer**, l'échelle est la même pour tous : un seul profil les couvre. Ça
marche pour un mur homogène. C'est fragile — un pas en avant pour une petite
pièce et le profil ne vaut plus rien — donc à réserver aux cas évidents.

---

## La voie sans aucun geste : fermée

Si l'iPhone inscrivait la distance de mise au point dans l'EXIF, l'application
calculerait l'échelle toute seule. **Le test est fait** : les 32 clichés de la
première séance, le 29 octobre, pris depuis Filigrane, sont arrivés avec la
seule orientation dans l'EXIF — ni appareil, ni focale, ni distance.

Pour les œuvres au mur, c'est donc **la carte dans chaque photo**, comme décrit
plus haut.

---

## Après la séance : redresser

Un cadre accroché haut se photographie d'en bas, il sort en trapèze, avec du
mur et les cadres voisins autour. Sur la fiche, **Redresser, recadrer** :

1. Les quatre pastilles bleues sur les quatre coins de ce que tu veux garder —
   le cadre, le passe-partout ou la feuille.
2. Le **zoom** que l'appareil affichait au déclenchement : 1×, 2×, 3×…
3. Au besoin, **Automatique** pour la lumière.
4. **Enregistrer**, ou **Enregistrer, puis la suivante** pour enchaîner les
   photographies pas encore retouchées.

L'original n'est jamais touché : on reprend ou on annule quand on veut.

**Le zoom compte.** Quatre coins disent comment redresser, pas quelles sont
les vraies proportions de l'œuvre : un trapèze donné peut venir d'un cadre
allongé vu de face comme d'un cadre plus haut vu très en biais. C'est la focale
qui tranche, et l'EXIF des photos ne la donne pas. L'outil suppose donc 1×, l'objectif
principal. Un cadre levé de 25° et photographié à 2× sans le dire sortirait
**7 % trop large**. Le plus simple : garde le même zoom pour toute une séance,
et note-le. Si tu photographies à l'iPad, rien ne dit que sa focale soit celle
de l'iPhone : 30 mm pris pour 26 faussent les proportions d'environ 2 % sur un
cadre levé de 25°.

**Trier en même temps.** Un cliché voilé de reflets, flou ou trop sombre ne se
rattrape pas : signale-le à refaire — le drapeau dans la liste des
photographies de la fiche —, avec le motif. Il reste en place pour la
description. Avant la séance suivante, l'onglet **À rephotographier** de la
page Dessins donne la liste, et les motifs disent quoi préparer : un autre
angle pour les reflets, de la lumière pour les étages sombres.

Pour reprendre un cliché, sur la fiche : **Ajouter des photographies** (sur
l'iPhone, le bouton propose de photographier directement), en choisissant la
même **nature** que l'ancien — sinon le nouveau arrive comme « détail » —, puis
supprime l'ancien. Le signalement part avec lui. Si l'ancien servait
d'illustration, la première photographie restante de la fiche prend le relais
— le nouveau cliché, quand il était seul ; sinon, l'étoile désigne celle qu'on
veut.

**Redresser d'abord, mesurer ensuite.** Sur la photographie brute d'un cadre
pris de biais, l'échelle varie d'un bord à l'autre, et la carte ne vaut que
là où elle est. Une fois l'image redressée, une seule échelle vaut partout :
la carte contre la vitre donne alors la largeur *et* la hauteur. Redresser
efface la mesure faite sur la photographie (elle désignerait d'autres pixels),
pas les dimensions déjà reportées sur la fiche.

---

## Les quatre pièges qui coûtent de la précision

**La référence doit être dans le même plan que ce qu'on mesure.** Contre la
vitre, pas sur le mur à côté du cadre, pas tenue en avant. Un cadre fait trois
ou quatre centimètres d'épaisseur : une carte tenue devant lui fausse l'échelle
de 2 à 3 %.

**Photographie aussi en face que possible**, depuis le centre de l'œuvre. De
biais, l'échelle n'est plus la même d'un bord à l'autre de l'image. Le
redressement le rattrape — voir plus haut —, à deux conditions : la carte est
dans le plan de l'œuvre, et le zoom déclaré est le bon. Sans redressement, une
mesure prise sur un trapèze est fausse sans que rien ne le signale.

**Les reflets ne se corrigent pas après coup.** Une vitre renvoie la fenêtre ou
la lampe qui lui fait face. Puisque la perspective se redresse ensuite, tu peux
te décaler d'un pas pour sortir du reflet : un cadre un peu de biais vaut mieux
qu'une œuvre voilée.

**Décide ce que tu enregistres, et n'en change plus.** L'application retient
une hauteur et une largeur, pas deux jeux. Pour une œuvre encadrée, la feuille
est partiellement cachée sous le passe-partout : tu mesureras donc en réalité
la **fenêtre du passe-partout**. Écris-le une fois dans la note de la première
fiche et tiens la même convention partout. Un chiffre dont on ignore ce qu'il
mesure ne vaut rien.

---

## Ce qu'on peut attendre

| Situation | Écart attendu |
|---|---|
| Feuille à plat, règle de 200 mm, bien en face | moins de 0,5 % |
| Œuvre au mur, carte bancaire, bien en face | autour de 1 % |
| Œuvre de biais, redressée, zoom déclaré juste | comme de face, en théorie ; à vérifier sur une pièce de dimensions connues |
| Œuvre de biais, redressée, zoom déclaré faux (2× pris pour 1×) | 7 % et au-delà sur les proportions |
| Prise de vue de biais, mesurée sans redresser | 3 % et au-delà, sans moyen de le savoir |
| Estimation par l'EXIF seul | 5 % environ (sans objet : l'EXIF est vide) |

Pour mémoire, la mesure de contrôle de la chaîne complète, sur une feuille de
300 × 400 mm photographiée en conditions idéales, donne 301 × 401 mm.

---

## Si tu t'es trompé

Rien n'est perdu tant que la référence est dans l'image.

- **Mauvaise échelle enregistrée ?** Rouvre **Mesurer** sur la photographie et
  recommence : la nouvelle mesure remplace l'ancienne.
- **Dimensions fausses sur une fiche déjà décrite ?** Corrige les champs
  Hauteur et Largeur à la main. L'application marque alors la dimension comme
  *saisie*, et **ne l'écrasera plus jamais** par un calcul.
- **Un profil appliqué à tort à toute une série ?** Supprime-le depuis
  **Échelles**, puis remesure les photographies concernées.
