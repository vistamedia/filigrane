# Filigrane

Catalogue d'une collection de dessins anciens : photographier, décrire,
classer. Application installable sur iPhone et sur Mac.

**En ligne :** <https://lise.dananlab.fr>
**Présentation :** <https://lise.dananlab.fr/a-propos> (publique)
**Documentation :** [`DOCS/00-sommaire.md`](DOCS/00-sommaire.md)

---

## Démarrer

```bash
make install     # image Docker, base, dépendances, icônes, images de test
make up          # http://localhost:8080
```

Comptes de développement — `lise@filigrane.test` et `emmanuel@filigrane.test`,
mot de passe `filigrane`.

## Commandes

```bash
make             # liste tout
make test        # 32 tests PHP + 64 tests de bout en bout
make deploy      # met en ligne
make sauvegarde  # rapatrie base et photographies
make apercus     # régénère les captures de la page « À propos »
```

## Pile

Laravel 13 · Filament 5 · MariaDB 11.4 · PHP 8.4 · Imagick (HEIC) ·
Playwright · Docker en développement, o2switch en ligne.

L'environnement local reproduit exactement les versions de l'hébergement.

## Organisation

```
app/            code de l'application
DOCS/           documentation (commencer par 00-sommaire.md)
docker/         image de développement
scripts/        déploiement, contrôle, sauvegarde, génération d'images
tests/          Feature (PHPUnit) et e2e (Playwright)
```

## Particularités à connaître

- **Imagick est indispensable** : GD ne sait pas lire le HEIC des iPhone.
- Les **originaux** sont hors de la racine web et passent par une route
  authentifiée ; seuls les dérivés sont servis en statique.
- Les **dimensions** se calculent depuis les photographies, via un profil
  d'échelle établi une fois par séance de prise de vue.
- Les **numéros d'inventaire** ne sont jamais recyclés.
- Les **exports** ne transitent pas par Livewire, qui les encoderait en base64
  dans sa réponse : ils passent par une route signée qui les diffuse en flux.

Le détail et les raisons : [`DOCS/04-decisions-et-alternatives.md`](DOCS/04-decisions-et-alternatives.md).
