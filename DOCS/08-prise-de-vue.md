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

## À tester une fois : la voie sans aucun geste

Si ton iPhone inscrit la distance de mise au point dans l'EXIF, l'application
calcule l'échelle toute seule, sans référence et sans trait à tracer.

**Le test prend trente secondes :** photographie un cadre, importe la photo, et
regarde la fiche.

| Ce que tu vois | Ce que ça veut dire |
|---|---|
| Des dimensions apparaissent, mention *estimée* | L'EXIF suffit. Rien à faire pour les œuvres au mur. |
| Les dimensions restent vides | L'appareil ne l'enregistre pas. Reviens à la carte. |

**Réserve.** Cette distance vient de l'autofocus : elle est approximative. Cinq
pour cent d'erreur sur la distance font cinq pour cent sur la taille, soit deux
centimètres sur une feuille de quarante. Pour un catalogue de travail, c'est
acceptable ; pour une assurance ou un constat d'état, non. La carte reste
nettement plus sûre.

---

## Les trois pièges qui coûtent de la précision

**La référence doit être dans le même plan que ce qu'on mesure.** Contre la
vitre, pas sur le mur à côté du cadre, pas tenue en avant. Un cadre fait trois
ou quatre centimètres d'épaisseur : une carte tenue devant lui fausse l'échelle
de 2 à 3 %.

**Photographie bien en face**, depuis le centre de l'œuvre. De biais, l'échelle
n'est plus la même d'un bord à l'autre de l'image, et aucune calibration ne
rattrape cela. C'est l'erreur la plus coûteuse, et la seule qu'on ne voit pas
sur la photo.

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
| Prise de vue de biais | 3 % et au-delà, sans moyen de le savoir |
| Estimation par l'EXIF seul | 5 % environ |

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
