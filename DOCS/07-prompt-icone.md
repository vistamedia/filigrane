# Icône — texte à donner à Claude Design

Écrit pour : toi, au moment d'ouvrir Claude Design.

La charte générale fait l'objet d'un autre document,
[05 — Charte graphique](05-prompt-charte-graphique.md). Celui-ci ne traite que
de **l'icône de l'application** : c'est un travail à part, avec ses propres
contraintes techniques, et le mélanger au reste donne des réponses tièdes sur
les deux sujets.

---

## Comment s'en servir

1. Copier le bloc **« Le texte à copier »** dans Claude Design.
2. Récupérer sa réponse — elle doit contenir deux fichiers SVG complets et un
   tableau de valeurs.
3. Suivre la section **« Quand tu auras reçu la réponse »**, en bas : trois
   commandes, pas davantage.

> **L'essentiel du prompt, c'est la section « Contraintes techniques ».**
> Le moteur qui transforme le SVG en PNG est celui d'ImageMagick, pas un
> navigateur : il ignore silencieusement la moitié de SVG. Un dessin superbe
> qui s'appuie sur un `stroke` ou un `clipPath` sortira **vide**, sans message
> d'erreur. Ce n'est pas une préférence de style, c'est ce qui fonctionne ou
> non.

---

## Le texte à copier

> J'ai besoin de l'**icône** d'une application web installable. Je te demande
> un travail d'icône seul : ni charte complète, ni maquette d'écran.
>
> ### L'application
>
> **Filigrane** est un catalogue privé de dessins anciens. Une collection
> d'environ deux cent cinquante feuilles, photographiées puis décrites une à
> une. Deux utilisateurs : sa propriétaire, qui décrit, et moi, qui
> photographie et entretiens l'application. Elle s'en sert sur un Mac et sur un
> iPhone, sur lesquels l'application est installée comme une application
> native.
>
> **Le nom.** Un *filigrane* est la marque laissée dans la pâte du papier au
> moment de sa fabrication, visible par transparence. Les spécialistes du
> dessin ancien s'en servent pour dater et attribuer une feuille. Les motifs
> historiques sont simples et beaux : croissant de lune, raisin, licorne, pot,
> main, couronne, cloche, aigle. Le papier vergé lui-même porte une trame
> régulière — des **vergeures** serrées et des **pontuseaux** espacés — qui
> apparaît elle aussi à contre-jour.
>
> **Le registre.** Sobriété de cabinet d'arts graphiques. Papier, encre,
> lumière rasante. Pas de dégradé, pas d'ombre portée, pas de couleur vive.
> L'application montre des reproductions en noir et blanc : son icône n'a pas à
> crier.
>
> ### Où l'icône apparaît
>
> | Contexte | Taille de rendu | Remarque |
> |---|---|---|
> | Écran d'accueil iOS | 180 × 180 px | iOS applique **son propre** masque à coins arrondis |
> | Installation Android / Chrome | 192 et 512 px | variante « maskable » obligatoire, rognée en cercle |
> | Onglet de navigateur | 32 × 32 px et SVG | c'est la taille critique |
> | Logo dans l'application | 36 px de haut, à gauche du mot « Filigrane » en romain | sur fond crème `#FBF8F2` |
> | Écran de démarrage | couleur de fond unie | tu me donnes laquelle |
>
> ### Contraintes techniques — à lire avant de dessiner
>
> Les PNG sont fabriqués à partir du SVG par **le moteur interne
> d'ImageMagick** (MSVG), et non par un navigateur. Ce moteur ne sait pas tout
> faire, et surtout : **il échoue en silence**. Ce qui n'est pas supporté
> disparaît du PNG sans message d'erreur.
>
> **Interdit — vérifié, ne rend pas :**
>
> - `stroke` : tout tracé au trait. Un `<path fill="none" stroke="…">` donne
>   une image vide. Les contours doivent être convertis en aplats fermés.
> - `clipPath`, `mask`, `filter`, `pattern`, `use`.
> - Les dégradés, les opacités de groupe imbriquées, le CSS (`<style>`,
>   attribut `class`).
>
> **Autorisé — ce avec quoi il faut composer :**
>
> - `rect`, `circle`, `ellipse`, `polygon`, `path` — **avec `fill` uniquement**.
> - `line` avec `stroke` : c'est la seule exception qui fonctionne, utile pour
>   une trame.
> - `opacity` et `fill-opacity` sur un élément simple.
> - `transform="translate(…) scale(…) rotate(…)"` sur un `<g>`.
>
> **Astuce qui marche bien :** pour évider une forme, superposer une forme de
> la **couleur exacte du fond** plutôt que d'utiliser un masque. C'est ainsi
> que l'icône actuelle obtient son croissant : un disque de couleur papier
> mordant sur un disque de couleur accent.
>
> **Format :** un seul `<svg viewBox="0 0 64 64">`, dessiné **plein bord**,
> sans coins arrondis — iOS, Android et les navigateurs appliquent leur propre
> masque, et un arrondi dessiné à la main produit un double arrondi.
>
> ### Ce que je te demande de produire
>
> 1. **`icone.svg`** — le SVG complet, prêt à coller dans un fichier. Plein
>    bord, 64 × 64.
>
> 2. **`icone-maskable.svg`** — même dessin, **motif réduit à 64 % et centré**,
>    fond plein bord. C'est la variante que les systèmes rognent en cercle :
>    rien d'important ne doit dépasser du disque central.
>
> 3. **Le tableau des couleurs**, en hexadécimal, avec un nom pour chacune :
>    fond de l'icône, couleur du motif, couleur de la trame s'il y en a une.
>
> 4. **Le contraste mesuré** entre le motif et le fond de l'icône, exprimé en
>    rapport (par exemple 5,2 : 1). Je veux au moins **4,5 : 1** pour que le
>    motif tienne à 32 pixels.
>
> 5. **Deux valeurs pour le manifeste** de l'application installable :
>    `theme_color` et `background_color`. Dis-moi laquelle va où et pourquoi.
>
> 6. **Un avis sur le favicon.** À 32 pixels, faut-il le même dessin ou une
>    version simplifiée ? Si tu proposes une simplification, donne-la en SVG
>    complet, sous le nom `favicon.svg`.
>
> 7. **Un avis sur le logo dans l'application.** Aujourd'hui l'icône est posée
>    telle quelle à gauche du mot « Filigrane ». Si ton dessin appelle autre
>    chose — un monogramme, un cadrage différent —, dis-le et donne le SVG.
>
> 8. **Une phrase de justification par choix important** : le motif, la
>    couleur, le cadrage. Pas un paragraphe.
>
> ### Ce qui existe aujourd'hui
>
> J'ai dessiné une icône de travail, que tu peux reprendre, améliorer ou
> écarter — dis-moi simplement laquelle des trois tu fais.
>
> - Fond crème `#F4EEE2`, plein bord.
> - Un **croissant** `#8E462F` (une craie rouge, dite sanguine), obtenu par
>   deux disques : un disque plein de couleur accent, échancré par un disque de
>   couleur papier.
> - Par-dessus l'ensemble, la trame du papier vergé : des `line` horizontales
>   très serrées à 4,5 % d'opacité, et quatre verticales à 7,5 %.
>
> L'idée que je tiens à garder, si tu changes de motif : **la trame passe
> par-dessus le motif**. C'est ce détail qui en fait un filigrane, pris dans
> l'épaisseur de la feuille, et non un dessin posé à sa surface.
>
> Ce que je n'aime pas dans l'état actuel : le croissant seul se lit parfois
> comme une lune, et la trame horizontale peut évoquer du papier réglé plutôt
> que du papier vergé.
>
> ### Ce qu'il faut éviter
>
> - Une tuile sombre : sur un écran d'accueil, elle fait un trou.
> - Un motif qui ne survit pas à 32 pixels.
> - Une couleur saturée : l'application montre des dessins, c'est à eux de
>   porter la couleur.
> - Un dessin qui ne fonctionne qu'en couleur : il doit rester lisible en
>   monochrome.
>
> ### Format de ta réponse
>
> Le code SVG complet entre balises, pas une description. Les valeurs
> hexadécimales dans un tableau. Et, en une ligne chacune, les justifications.

---

## Quand tu auras reçu la réponse

### 1. Les deux fichiers

Remplace le contenu de :

- `resources/icones/icone.svg`
- `resources/icones/icone-maskable.svg`

Puis :

```bash
make icones     # régénère les PNG 32, 180, 192, 512 et le favicon.ico
make assets     # recompile les feuilles de style
```

`make icones` recopie aussi `icone.svg` en `public/icones/favicon.svg`. Si
Claude Design a fourni un `favicon.svg` distinct, dépose-le **après** dans
`public/icones/`, et note-le dans `scripts/generer-icones.php` pour qu'il
survive à la prochaine régénération.

### 2. Les couleurs du manifeste

Dans `public/manifest.webmanifest` :

```json
"background_color": "#…",
"theme_color": "#…",
```

Et la même valeur de `theme_color` dans
`resources/views/pwa/head.blade.php` :

```html
<meta name="theme-color" content="#…">
```

### 3. Le logo dans l'application

`resources/views/components/filigrane-logo.blade.php` pose l'icône à gauche du
mot « Filigrane ». Si la réponse propose autre chose, c'est le seul fichier à
reprendre.

### 4. Vérifier

```bash
make test       # la suite complète
make captures   # régénère les captures de la documentation
make apercus    # régénère les aperçus de la page publique
```

Puis **regarde le PNG**, pas seulement le SVG :

```bash
open public/icones/icone-180x180.png public/icones/icone-32x32.png
```

> **C'est l'étape qui attrape les échecs silencieux.** Si le motif a disparu ou
> s'est transformé, c'est qu'il s'appuyait sur une fonctionnalité que le moteur
> ne gère pas — relis les contraintes techniques ci-dessus et demande une
> version en aplats.

### Si le dessin retenu exige vraiment un tracé au trait

Il y a une sortie de secours, et elle est moins coûteuse qu'il n'y paraît :
**les icônes sont fabriquées en local**, dans le conteneur Docker, et seuls les
PNG produits partent sur le serveur. L'hébergement ne régénère jamais rien.

Il suffit donc d'améliorer le moteur SVG de l'image de développement, sans rien
changer en ligne : ajouter `librsvg2-bin` aux paquets installés dans
`docker/app/Dockerfile`, puis `make build && make icones`. ImageMagick délègue
alors le rendu à librsvg, qui gère `stroke`, `clipPath` et les dégradés.

Pour savoir lequel des deux moteurs est en place :

```bash
docker compose exec app convert -list format | grep -i '^ *SVG'
```

`Scalable Vector Graphics (RSVG 2.x)` signifie que librsvg a pris la main.
`(XML 2.9.x)` signifie que c'est le moteur interne, celui qui impose les
contraintes ci-dessus.

> Une réserve, tout de même : un dessin en aplats rend **identiquement
> partout**. Un dessin qui dépend de librsvg ne rendra plus si l'image Docker
> est reconstruite ailleurs sans ce paquet. À 64 × 64, la contrainte des aplats
> coûte peu et achète cette tranquillité.

### 5. Mettre en ligne

```bash
make deploy
./scripts/verifier-production.sh
```

Le contrôle vérifie que toutes les icônes déclarées dans le manifeste existent
réellement et sont servies comme des images.

---

## Les raisons du dessin actuel

Elles sont dans [D-34](04-decisions-et-alternatives.md#d-34), avec les deux
pistes écartées et pourquoi.
