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
Trente-cinq décisions, chacune avec ce qui a été fait, pourquoi, l'autre option
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
L'icône est une demande à part, avec ses propres contraintes : le moteur qui
fabrique les PNG ignore la moitié de SVG **sans prévenir**. Ce document donne
le texte à copier, la liste de ce qu'il faut exiger en retour, et l'étape de
vérification qui attrape les échecs silencieux.

---

## Aide-mémoire

```bash
make install    # première installation complète
make up         # démarre l'environnement local → http://localhost:8080
make test       # 32 tests PHP + 62 tests de bout en bout
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
- Deux comptes créés, catalogue vide, prêt à recevoir la première fiche.
- Sauvegarde quotidienne de la base installée sur le serveur.
- Ajoutés le 29 octobre : **diaporama**, **export tableur et catalogue PDF**,
  **page publique de présentation**, nouvelle **icône**. Le réglage de taille
  du texte a été **retiré** (voir [D-18](04-decisions-et-alternatives.md#d-18)).
- Charte graphique provisoire, en attente de celle de Claude Design — l'icône,
  elle, est arrêtée.
- Reste à faire à la main : essayer l'application sur un vrai iPhone, et
  déposer une photo HEIC dans `tests/fixtures/` pour activer le test de
  conversion.
