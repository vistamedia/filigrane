# Filigrane — mise en ligne et exploitation

Écrit pour : toi, ou quiconque reprendrait l'administration de l'application.

---

## L'essentiel

| | |
|---|---|
| Hébergement | mutualisé cPanel |
| Application | hors de la racine web, dans `$FILIGRANE_APP_DIR` |
| Racine web | `$FILIGRANE_WEB_DIR` — n'y vivent que `index.php` et les fichiers statiques |
| Base | MariaDB sur `localhost` |
| Sauvegardes | `~/sauvegardes` sur le serveur |

> **Les coordonnées du serveur ne sont pas dans ce dépôt.** Compte SSH, nom
> d'hôte, chemins et adresse publique vivent dans un fichier local,
> `.deploiement.env`, que Git ignore. Les trois scripts le lisent
> automatiquement.
>
> ```bash
> cp .deploiement.env.example .deploiement.env   # puis renseigner
> source .deploiement.env                        # pour les commandes ci-dessous
> ```
>
> Un identifiant SSH et l'arborescence d'une machine sont la moitié d'une
> porte d'entrée : ils n'ont rien à faire dans un dépôt public.

---

## Déployer

```bash
make deploy
```

Le script `scripts/deployer.sh` enchaîne :

1. compilation locale des feuilles de style et des scripts *(le serveur n'a que
   Node 18 : on construit ici, avec la version du projet)* ;
2. envoi du code par `rsync`, en excluant `.env`, `storage/app`, les journaux et
   `vendor` ;
3. `composer install --no-dev --optimize-autoloader` sur le serveur ;
4. migrations, puis mise en cache de la configuration, des routes et des vues ;
5. publication des fichiers statiques dans la racine web, avec réécriture du
   contrôleur d'entrée et du lien vers les photographies ;
6. contrôle que `/login` répond 200.

Le script **n'écrase jamais** `.env` ni `storage/app` : la configuration et les
photographies survivent à tous les déploiements.

### Contrôler après coup

```bash
./scripts/verifier-production.sh
```

Vérifie ce qui doit répondre, ce qui doit être protégé, ce qui ne doit pas
exister (`.env`, `.git`, code source), le contenu des pages, la redirection
HTTPS et les en-têtes de sécurité.

> **Laissez passer une minute après le déploiement.** L'opcache de PHP ne
> revalide les fichiers qu'au bout de quelques dizaines de secondes : un
> contrôle lancé dans la foulée peut encore voir l'ancien code et signaler un
> échec qui n'en est pas un. En cas de doute, relancez.

### Recette complète, dans un vrai navigateur

```bash
source .deploiement.env
FILIGRANE_COMPTE=ton@adresse.fr \
FILIGRANE_MOTDEPASSE='…' \
npx playwright test --project=en-ligne
```

Ce test photographie un dessin, vérifie que la vignette est bien fabriquée et
servie, décrit la fiche, la retrouve dans le diaporama, **télécharge les deux
exports**, puis **efface la fiche d'essai** pour rendre son numéro
d'inventaire. C'est le seul moyen de s'assurer qu'Imagick, dompdf, le lien de
stockage et les droits d'écriture fonctionnent sur le serveur réel.

---

## La première installation (déjà faite)

Pour mémoire, si tout devait être refait ailleurs.

1. **Créer le sous-domaine** dans cPanel. La racine web doit être
   `/home/<user>/<domaine>`.
2. **Créer la base et son utilisateur** dans cPanel.
3. **Déposer `.env`** dans `~/apps/filigrane/` :

   ```bash
   docker compose exec app php artisan key:generate --show   # en local
   ```

   Puis sur le serveur, les valeurs qui changent par rapport à `.env.example` :

   ```
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://filigrane.exemple.fr
   APP_KEY=base64:…
   DB_HOST=localhost
   DB_DATABASE=<base>
   DB_USERNAME=<utilisateur>
   DB_PASSWORD=…
   SESSION_SECURE_COOKIE=true
   LOG_LEVEL=warning
   MAIL_MAILER=log
   ```

   ```bash
   chmod 600 ~/apps/filigrane/.env
   ```

4. **Vérifier le mot de passe de la base.** Il doit être long et sans
   rapport avec le nom d'utilisateur. La base n'écoute que sur `localhost`,
   mais un mot de passe devinable n'en reste pas moins un mot de passe
   devinable.
5. **Déployer** : `make deploy`.
6. **Créer les comptes** :

   ```bash
   ssh "$FILIGRANE_SSH_USER@$FILIGRANE_SSH_HOST"
   cd ~/apps/filigrane
   php artisan filigrane:compte lise@exemple.fr --nom='Lise' --role=curator
   php artisan filigrane:compte toi@exemple.fr --nom='Emmanuel' --role=admin
   ```

7. **Installer la tâche planifiée** (voir plus bas).

---

## Tâches planifiées

Une seule ligne de cron déclenche tout :

```cron
* * * * * cd $HOME/apps/filigrane && /usr/local/bin/php artisan schedule:run >/dev/null 2>&1
```

Elle est **déjà installée**. Le crontab du compte contenait déjà une tâche pour
un autre projet (Prevaterra) : elle a été conservée intacte, et une copie de
l'ancien crontab a été laissée dans `~/.crontab-avant-filigrane-*`.

Ce que la planification déclenche (`routes/console.php`) :

| Heure | Tâche | Effet |
|---|---|---|
| 04 h 10 | `filigrane:sauvegarder` | Copie de la base dans `~/sauvegardes`, quinze jours conservés. |
| 04 h 40 | `filigrane:menage` | Supprime les envois interrompus, les exports préparés mais jamais récupérés, et les images sans fiche. |
| dimanche | `auth:clear-resets` | Jetons périmés. |

Vérifier :

```bash
ssh "$FILIGRANE_SSH_USER@$FILIGRANE_SSH_HOST" 'cd ~/apps/filigrane && php artisan schedule:list'
```

---

## Sauvegardes

### Sur le serveur, chaque nuit

Base seulement, compressée, quinze copies glissantes. Environ 10 Ko au départ,
quelques mégaoctets une fois la collection décrite.

### Sur ton Mac, à la demande

```bash
make sauvegarde          # vers ~/Sauvegardes/filigrane
```

Rapatrie la dernière copie de la base **et** toutes les photographies
originales, en incrémental : seules les nouvelles passent sur le réseau.

> **C'est la seule sauvegarde qui protège vraiment.** Celles du serveur
> disparaîtraient avec lui. Lance-la une première fois dès maintenant, pour
> vérifier qu'elle fonctionne — une sauvegarde jamais essayée n'est pas une
> sauvegarde.

### Restaurer

```bash
gunzip -c ~/Sauvegardes/filigrane/base/filigrane-AAAA-MM-JJ-HHMMSS.sql.gz \
  | ssh "$FILIGRANE_SSH_USER@$FILIGRANE_SSH_HOST" 'mysql -u <utilisateur> -p… <base>'

rsync -az ~/Sauvegardes/filigrane/photographies/ \
  "$FILIGRANE_SSH_USER@$FILIGRANE_SSH_HOST":~/apps/filigrane/storage/app/private/photos/
```

Puis régénérer les dérivés si nécessaire — les vignettes se refabriquent à
partir des originaux.

---

## Administration courante

### Remettre un mot de passe

Par l'interface : **Comptes** → la ligne → **Modifier** → nouveau mot de passe.

En ligne de commande :

```bash
ssh "$FILIGRANE_SSH_USER@$FILIGRANE_SSH_HOST"
cd ~/apps/filigrane && php artisan filigrane:compte lise@exemple.fr
```

### Voir les journaux

```bash
ssh "$FILIGRANE_SSH_USER@$FILIGRANE_SSH_HOST" 'tail -50 $(ls -t ~/apps/filigrane/storage/logs/*.log | head -1)'
```

`LOG_LEVEL=warning` en production : seuls les avertissements et les erreurs y
figurent.

### Faire le ménage à la main

```bash
cd ~/apps/filigrane
php artisan filigrane:menage --simuler   # annonce sans rien effacer
php artisan filigrane:menage
```

### Après un changement de configuration

```bash
cd ~/apps/filigrane && php artisan optimize
```

La configuration est mise en cache : modifier `.env` sans relancer cette
commande n'a aucun effet.

---

## Si quelque chose ne va pas

| Symptôme | Cause probable | Remède |
|---|---|---|
| L'export PDF ne se termine pas | Trop de fiches d'un coup sur un mutualisé | Filtrer la liste avant d'exporter, ou lancer l'export par onglet |
| « Lien expiré » au téléchargement d'un export | Plus de dix minutes entre la préparation et le clic | Relancer l'export |
| Erreur 500 partout | Cache de configuration périmé, ou `.env` absent | `php artisan optimize:clear` puis `php artisan optimize` |
| Erreur 500 sur une page seulement | Voir les journaux | `tail` sur le dernier fichier de `storage/logs` |
| Page blanche | Droits sur `storage` | `chmod -R 755 ~/apps/filigrane/storage` |
| Les vignettes ne s'affichent pas | Lien de stockage cassé | Relancer `make deploy`, qui le refait |
| Les styles ont disparu | `build/` non publié | `npm run build` puis `make deploy` |
| « Page expirée » à la connexion | Session perdue | Vérifier `SESSION_SECURE_COOKIE=true` **et** que HTTPS est forcé |
| Un envoi de photo échoue | Fichier trop lourd | Limite à 100 Mo dans `config/livewire.php` |

### Revenir en arrière

Il n'y a pas de versions successives sur le serveur : le déploiement remplace.
La base, elle, se restaure depuis `~/sauvegardes`.

> **Le projet n'est pas encore sous gestion de version.** C'est le point faible
> du dispositif : sans historique, revenir à l'état d'avant un déploiement
> demande de reconstituer les fichiers à la main. Un `git init` suivi d'un
> premier enregistrement y remédierait en deux minutes — le `.gitignore` est
> déjà écrit pour cela, et exclut `conf.txt`, `.env` et les photographies.
>
> Si les déploiements devenaient fréquents, l'étape suivante serait des
> dossiers horodatés sur le serveur avec un lien symbolique `current` : une
> demi-journée de travail, et un retour en arrière instantané.

---

## Ce que l'hébergement fournit

Relevé le 28 octobre 2026, utile le jour où quelque chose change.

| | |
|---|---|
| PHP | 8.4.27, en ligne de commande comme côté web (LiteSpeed) |
| Imagick | présent, **avec les délégués HEIC, HEIF et AVIF** |
| Extensions | `exif`, `gd`, `intl`, `zip`, `fileinfo`, `mbstring`, `pdo_mysql`, `openssl` |
| MariaDB | 11.4.13 |
| Envoi de fichiers | 512 Mo, **20 fichiers au plus par requête** |
| Temps d'exécution | 600 s côté web, illimité en ligne de commande |
| Mémoire | 512 Mo |
| Node | 18.20.8 — trop ancien pour ce projet, d'où la compilation en local |
| Composer, Git, rsync | présents |

La limite de **20 fichiers par requête** est sans effet ici : Livewire envoie
un fichier par requête. Elle interdirait en revanche tout formulaire d'envoi
multiple classique.
