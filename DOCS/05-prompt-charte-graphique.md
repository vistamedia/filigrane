# Charte graphique — texte à donner à Claude Design

Écrit pour : toi, au moment d'ouvrir Claude Design.

Ce document contient deux choses :

1. **Le texte à copier-coller** dans Claude Design (section ci-dessous).
2. **Ce qu'il faudra en faire** une fois la charte reçue.

L'icône est traitée à part : [07 — Icône](07-prompt-icone.md).

---

## Avant de copier : trois choses à savoir

**L'habillage actuel est provisoire et assumé comme tel.** Papier crème, encre,
un accent sanguine, titres en romain. Il tient debout, mais il n'a pas été
conçu : il a été posé pour pouvoir travailler. Ce que Claude Design produira le
remplacera.

**L'icône fait l'objet d'un document à part :**
[07 — Icône](07-prompt-icone.md). Ce n'est pas la même demande, et les
contraintes techniques n'ont rien à voir — les mélanger donne des réponses
tièdes sur les deux sujets.

**Deux fichiers seulement sont concernés** — c'est voulu :

| Fichier | Ce qu'il contient |
|---|---|
| `app/Providers/Filament/AdminPanelProvider.php` | Les onze nuances de la couleur d'accent. |
| `resources/css/filament/admin/theme.css` | Typographie, tailles, espacements. |

**Ne demande pas une maquette d'écran.** Filament impose sa structure. Ce qu'il
faut, ce sont des *valeurs* : des couleurs, des tailles, une typographie. Le
texte ci-dessous est écrit pour obtenir cela.

---

## Le texte à copier

> Je développe **Filigrane**, une application web privée qui sert à cataloguer
> une collection de dessins anciens. J'ai besoin d'une charte graphique :
> palette, typographie, échelle de tailles. L'icône fait l'objet d'une demande
> séparée, ne t'en occupe pas ici.
>
> **Qui l'utilise.** Deux personnes. Principalement ma belle-mère, une
> conservatrice retraitée de plus de quatre-vingt-dix ans. Elle s'en sert sur
> un Mac et sur un iPhone, à la lumière du jour comme le soir. Elle a l'œil
> d'une spécialiste et la vue de son âge : ces deux faits comptent autant l'un
> que l'autre. Secondairement moi, qui photographie les dessins et fais la
> maintenance.
>
> **Ce qu'elle y fait.** Elle regarde la photographie d'un dessin et elle
> écrit : l'artiste, la période, une description, des notes. Une fiche après
> l'autre, pendant des séances d'une heure ou deux. Environ 250 dessins.
>
> **Ce que l'application montre.** Des reproductions de dessins anciens,
> **presque toutes en noir et blanc**, sur des papiers dont les blancs ont
> tourné. Ce sont ces images qui doivent porter la couleur et l'attention.
> **L'interface doit s'effacer devant elles.**
>
> **Ce que je te demande de produire :**
>
> 1. **Une palette complète**, exprimée en valeurs hexadécimales :
>    - une couleur d'accent, déclinée en **onze nuances numérotées 50, 100,
>      200, 300, 400, 500, 600, 700, 800, 900, 950** (c'est le format qu'attend
>      le cadre d'interface que j'utilise) ;
>    - une échelle de gris, chaude de préférence, pour les fonds, les textes et
>      les bordures ;
>    - les couleurs d'état : succès, avertissement, erreur, information.
>    - **Contrainte ferme :** la nuance 600 de l'accent sert de fond aux
>      boutons, avec du texte très clair dessus. Elle doit atteindre **au moins
>      6:1 de contraste** sur blanc — au-delà du seuil WCAG AA de 4,5:1, parce
>      que l'utilisatrice est âgée. Donne-moi le rapport de contraste calculé.
>    - Prévois les mêmes valeurs pour un **thème sombre**.
>
> 2. **Une typographie :**
>    - une pile pour les titres et une pour le texte courant ;
>    - privilégie les polices système d'Apple (`ui-serif` donne *New York*,
>      `ui-sans-serif` donne *SF Pro*) ; si tu proposes une police à
>      télécharger, justifie-le et reste sous 200 Ko ;
>    - une échelle de tailles partant de **17 px** pour le texte courant, et
>      non 16 ;
>    - interlignage et longueur de ligne confortables pour de la lecture
>      prolongée.
>
> 3. **Quelques règles d'usage**, en une phrase chacune : quand employer
>    l'accent et quand s'en abstenir, quelle épaisseur de bordure, quelle ombre
>    (ou pas d'ombre), quel rayon d'arrondi.
>
> **Le registre visuel.** Sobriété de cabinet d'arts graphiques : papier,
> encre, lumière rasante. Pas de dégradés, pas d'ombres portées marquées, pas
> de couleurs vives. Une seule couleur d'accent suffit. Je pense à la
> signalétique d'un département d'arts graphiques, ou à la maquette d'un
> catalogue raisonné — pas à une application grand public.
>
> **Ce qu'il faut éviter :**
> - le gris bleuté « logiciel » : le fonds est sur papier, les gris doivent
>   être chauds ;
> - les fonds sombres par défaut : la lecture prolongée se fait mieux sur clair,
>   même si un thème sombre doit exister ;
> - les couleurs saturées : elles entreraient en concurrence avec les dessins ;
> - les typographies « techniques » ou condensées.
>
> **Format de ta réponse.** Un document structuré, avec les valeurs
> hexadécimales dans des tableaux, prêtes à être recopiées dans du code.
> Explique en une ou deux phrases chaque choix important.
>
> À titre indicatif, voici l'habillage provisoire que j'ai posé en attendant,
> et que tu peux reprendre ou écarter : encre `#1C1A17`, papier `#FBF8F2`,
> accent sanguine `#A3553E` (une craie rouge), gris chauds, titres en
> `ui-serif`. Je n'y suis pas attaché.
>
> Une contrainte de cohérence : l'icône de l'application, traitée séparément,
> est posée sur un fond crème `#F4EEE2` avec un motif `#8E462F`. La charte doit
> pouvoir vivre à côté.

---

## Quand tu auras reçu la charte

### 1. La palette

Dans `app/Providers/Filament/AdminPanelProvider.php`, remplace le tableau
`'primary' => [...]` par les onze nuances fournies. Garde la forme :

```php
'primary' => [
    50 => '#…', 100 => '#…', 200 => '#…', 300 => '#…', 400 => '#…',
    500 => '#…', 600 => '#…', 700 => '#…', 800 => '#…', 900 => '#…',
    950 => '#…',
],
```

> **Ne remplace pas ce tableau par `Color::hex('#…')`.** Filament ne retiendrait
> que l'angle de teinte et imposerait sa propre saturation — c'est ce qui
> transformait la sanguine en saumon vif. Voir la décision D-17.

Mets aussi à jour `'gray'` si la charte propose son propre gris chaud.

### 2. La typographie et les tailles

Dans `resources/css/filament/admin/theme.css` :

- `--font-serif` dans le bloc `@theme` ;
- `html { font-size: 17px }` si la taille de départ change ;
- les règles de titres, de champs et de tableaux plus bas dans le fichier.

Si la charte impose une police à télécharger, dépose les fichiers dans
`public/polices/` et déclare-les par `@font-face` dans ce même fichier. Pas de
CDN : l'application doit s'afficher sans dépendre d'un tiers.

### 3. Recompiler

```bash
make assets        # recompile les feuilles de style
```

L'icône, elle, ne bouge pas ici : voir [07 — Icône](07-prompt-icone.md).

### 4. Vérifier

```bash
make test          # le contraste du bouton principal est contrôlé automatiquement
make captures      # régénère les captures de la documentation
make apercus       # régénère les aperçus de la page publique
```

Le test *« le bouton principal reste lisible sur fond clair »* échoue si la
nuance 600 descend sous 6:1 de contraste. S'il échoue, c'est la charte qu'il
faut corriger, pas le test.

Regarde ensuite `DOCS/captures/` : les écrans doivent avoir changé d'allure
sans avoir changé de structure.

### 5. Mettre en ligne

```bash
make deploy
./scripts/verifier-production.sh
```
