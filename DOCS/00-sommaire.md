# Filigrane — documentation

Application de catalogage d'une collection de dessins anciens.
**<https://lise.dananlab.fr>**

---

## Par où commencer

| Vous êtes | Lisez |
|---|---|
| L'utilisatrice de l'application | **[Guide d'utilisation](03-guide-utilisateur.md)** |
| Celui qui l'administre | [Mise en ligne et exploitation](06-deploiement.md) |
| Celui qui veut comprendre les choix | [Décisions et alternatives](04-decisions-et-alternatives.md) |
| Un développeur qui reprend le projet | [Architecture technique](02-architecture-technique.md), puis [Schéma relationnel](01-schema-base-de-donnees.md) |
| Prêt à travailler la charte graphique | [Texte à donner à Claude Design](05-prompt-charte-graphique.md) |
| Prêt à travailler l'icône | [Texte à donner à Claude Design — icône](07-prompt-icone.md) |
| Sur le point de photographier | **[Guide de prise de vue](08-prise-de-vue.md)** |

---

## Les documents

### [01 — Schéma relationnel de la base](01-schema-base-de-donnees.md)
Les six tables, colonne par colonne, et les raisons de chaque choix : pourquoi
l'artiste est un texte libre, pourquoi les dimensions sont en millimètres
entiers, pourquoi une table de profils d'échelle. Diagramme Mermaid inclus.

### [02 — Architecture technique](02-architecture-technique.md)
La pile logicielle, le trajet d'une photographie depuis l'envoi jusqu'à la
vignette, le fonctionnement de la mesure par calibration, l'organisation du
code, la couverture des tests et ce qu'ils ne couvrent pas.

### [03 — Guide d'utilisation](03-guide-utilisateur.md)
Écrit pour l'utilisatrice, sans vocabulaire technique : installer l'application
sur l'iPhone et sur le Mac, décrire un dessin, regarder une photographie de
près, poser des mots-clés, parcourir la collection en diaporama, l'exporter en
tableur ou en catalogue PDF, récupérer une fiche effacée.

### [04 — Décisions et alternatives](04-decisions-et-alternatives.md)
Trente-neuf décisions, chacune avec ce qui a été fait, pourquoi, l'autre option
et ce qu'il en coûterait de changer d'avis — y compris une décision **revenue**
(D-18, le réglage de taille du texte). **C'est le document à ouvrir quand
quelque chose te paraît discutable.**

### [05 — Charte graphique](05-prompt-charte-graphique.md)
Le texte à copier dans Claude Design pour la palette, la typographie et les
tailles, et la marche à suivre pour intégrer ce qu'il produira. Deux fichiers à
modifier, pas davantage.

### [06 — Mise en ligne et exploitation](06-deploiement.md)
Déployer, contrôler, sauvegarder, restaurer, dépanner. Et le relevé de ce que
l'hébergement fournit.

### [07 — Icône](07-prompt-icone.md)
L'icône est une demande à part : quatre fichiers, pas un. Ce document montre
le dessin actuel, donne le texte à copier dans Claude Design, la liste de ce
qu'il faut exiger en retour, et la marche à suivre pour l'intégrer — métadonnées
à retirer et cadrage à vérifier compris.

### [08 — Guide de prise de vue](08-prise-de-vue.md)
Ce qu'il faut faire appareil en main : les feuilles des cartons en séance, avec
un profil d'échelle ; les œuvres encadrées au mur, avec une référence dans
chaque photographie et aucune contrainte de distance ; le redressement après
la séance, et pourquoi le zoom compte. La règle qui prime sur toutes les
autres — **la photographie est irréversible, la mesure ne l'est pas** — et les
quatre pièges qui coûtent de la précision.

---

## Aide-mémoire

```bash
make install    # première installation complète
make up         # démarre l'environnement local → http://localhost:8080
make test       # 57 tests PHP + 71 tests de bout en bout
make deploy     # met en ligne
make sauvegarde # rapatrie base et photographies sur ce Mac
make apercus    # régénère les captures de la page publique « À propos »
make            # liste toutes les commandes
```

| Dossier | Contenu |
|---|---|
| `DOCS/` | Cette documentation |
| `DOCS/captures/` | Captures d'écran, régénérées par `make captures` |
| `DOCS/diagrammes/` | Diagrammes en SVG, régénérés par `node scripts/rendre-diagrammes.mjs` |
| `scripts/` | Déploiement, contrôle, sauvegarde, génération des icônes et des images de test |
| `tests/e2e/` | Tests Playwright |
| `tests/Feature/` | Tests PHP |

---

## État au 29 octobre 2026

- Application en ligne et vérifiée sur le serveur réel.
- Deux comptes créés. **Première séance de prise de vue faite le 29 octobre** :
  32 œuvres encadrées photographiées au mur.
- Essayée le même jour sur la tablette, le téléphone et le Mac de
  l'utilisatrice : tout fonctionne.
- Sauvegarde quotidienne de la base installée sur le serveur.
- Ajoutés le 29 octobre : **diaporama**, **export tableur et catalogue PDF**,
  **page publique de présentation**, nouvelle **icône**. Le réglage de taille
  du texte a été **retiré** (voir [D-18](04-decisions-et-alternatives.md#d-18)).
- Charte graphique provisoire, en attente de celle de Claude Design.
- **Icône, favicon et logo** livrés par Claude Design et intégrés
  (voir [D-34](04-decisions-et-alternatives.md#d-34)).
- **Test de l'EXIF fait** : les photos prises depuis Filigrane n'en portent
  que l'orientation. Pour les œuvres au mur, la carte bancaire dans chaque
  photo reste la seule méthode de mesure.
- Ajoutée à la suite de cette séance : la **retouche** — redresser un cadre
  photographié de biais par ses quatre coins, recadrer, tourner, éclaircir,
  sans jamais toucher à l'original (voir
  [D-36](04-decisions-et-alternatives.md#d-36) et
  [D-37](04-decisions-et-alternatives.md#d-37)) ; et le **signalement des
  clichés à refaire**, réunis dans l'onglet « À rephotographier » de la liste
  des dessins (voir [D-38](04-decisions-et-alternatives.md#d-38)).
- Ajouté le 30 octobre : le **changement d'avancement par lots** depuis la
  liste des dessins (voir [D-39](04-decisions-et-alternatives.md#d-39)). Au
  passage, deux défauts corrigés : une fiche ne pouvait plus revenir « à
  décrire », et la coche des vignettes sélectionnées était invisible.
- Reste à faire à la main : déposer une photo HEIC dans `tests/fixtures/` pour
  activer le test de conversion.
