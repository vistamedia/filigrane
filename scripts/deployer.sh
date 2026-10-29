#!/usr/bin/env bash
#
# Filigrane — mise en ligne sur o2switch.
#
#   ./scripts/deployer.sh
#
# Principe : le code de l'application vit HORS du dossier web
# (~/apps/filigrane), et le dossier web ne contient que le contrôleur d'entrée
# et les fichiers statiques. Même mal configuré, le serveur ne peut donc pas
# servir le fichier .env ni le code source.
#
#   ~/apps/filigrane/           app, config, vendor, storage, .env
#   ~/lise.dananlab.fr/         index.php, .htaccess, build/, icônes, storage→
#
# Les photographies vivent dans ~/apps/filigrane/storage et ne sont jamais
# touchées par un déploiement.

set -euo pipefail

RACINE="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

# Les coordonnées du serveur ne sont pas dans le dépôt : un identifiant SSH et
# l'arborescence d'une machine sont la moitié d'une porte d'entrée.
# Voir .deploiement.env.example.
# shellcheck source=/dev/null
[ -f "$RACINE/.deploiement.env" ] && { set -a; . "$RACINE/.deploiement.env"; set +a; }

SSH_UTILISATEUR="${FILIGRANE_SSH_USER:?Renseignez .deploiement.env (voir .deploiement.env.example)}"
SSH_HOTE="${FILIGRANE_SSH_HOST:?Renseignez .deploiement.env}"
SSH_CLE="${FILIGRANE_SSH_KEY:-$HOME/.ssh/id_ed25519}"

DOSSIER_APP="${FILIGRANE_APP_DIR:?Renseignez .deploiement.env}"
DOSSIER_WEB="${FILIGRANE_WEB_DIR:?Renseignez .deploiement.env}"
URL="${FILIGRANE_URL:?Renseignez .deploiement.env}"
SSH=(ssh -o BatchMode=yes -i "$SSH_CLE" "$SSH_UTILISATEUR@$SSH_HOTE")

bleu()  { printf '\033[1;34m→ %s\033[0m\n' "$1"; }
vert()  { printf '\033[1;32m✓ %s\033[0m\n' "$1"; }
rouge() { printf '\033[1;31m✗ %s\033[0m\n' "$1" >&2; }

cd "$RACINE"

# ---------------------------------------------------------------------------
bleu "Vérification de l'accès"
"${SSH[@]}" 'echo ok' >/dev/null || { rouge "Connexion SSH impossible"; exit 1; }

# ---------------------------------------------------------------------------
bleu "Compilation des feuilles de style et des scripts (en local)"
# Le serveur n'a que Node 18 : on construit ici, avec la version du projet,
# et on envoie le résultat.
npm run build >/dev/null
vert "public/build à jour"

# ---------------------------------------------------------------------------
bleu "Envoi du code de l'application"
"${SSH[@]}" "mkdir -p '$DOSSIER_APP'"
rsync -az --delete \
    -e "ssh -o BatchMode=yes -i $SSH_CLE" \
    --exclude '.git' \
    --exclude '.github' \
    --exclude 'node_modules' \
    --exclude '.env' \
    --exclude '.env.example' \
    --exclude 'docker' \
    --exclude 'docker-compose.yml' \
    --exclude 'Makefile' \
    --exclude 'conf.txt' \
    --exclude 'DOCS' \
    --exclude 'tests' \
    --exclude 'test-results' \
    --exclude 'playwright-report' \
    --exclude '.verif' \
    --exclude 'playwright.config.js' \
    --exclude 'public/storage' \
    --exclude 'public/hot' \
    --exclude 'storage/app/**' \
    --exclude 'storage/logs/**' \
    --exclude 'storage/framework/cache/**' \
    --exclude 'storage/framework/sessions/**' \
    --exclude 'storage/framework/views/**' \
    `# La barre oblique ancre le motif à la racine du transfert. Sans elle,` \
    `# rsync exclut TOUT dossier nommé « vendor », y compris lang/vendor,` \
    `# où vivent les 168 traductions françaises écrites à la main.` \
    --exclude '/vendor' \
    ./ "$SSH_UTILISATEUR@$SSH_HOTE:$DOSSIER_APP/"
vert "Code envoyé"

# ---------------------------------------------------------------------------
bleu "Installation des dépendances PHP sur le serveur"
"${SSH[@]}" "cd '$DOSSIER_APP' && composer install --no-dev --optimize-autoloader --no-interaction --quiet"
vert "vendor à jour"

# ---------------------------------------------------------------------------
bleu "Préparation du dossier de données"
"${SSH[@]}" bash -s <<SSHEOF
set -e
cd '$DOSSIER_APP'
mkdir -p storage/app/public storage/app/private storage/framework/{cache/data,sessions,views} storage/logs bootstrap/cache
chmod -R 755 storage bootstrap/cache

if [ ! -f .env ]; then
    echo "Aucun fichier .env sur le serveur — voir DOCS/06-deploiement.md" >&2
    exit 1
fi
SSHEOF
vert "Dossiers en place"

# ---------------------------------------------------------------------------
bleu "Migrations et caches"
"${SSH[@]}" bash -s <<SSHEOF
set -e
cd '$DOSSIER_APP'
php artisan migrate --force --no-interaction
php artisan filament:assets --no-interaction >/dev/null
# Vider avant de reconstruire : « optimize » seul laisse en place des vues
# compilées que l'opcache sert encore, et une page modifiée peut rester
# affichée dans son ancienne version.
php artisan optimize:clear >/dev/null
php artisan optimize >/dev/null
php artisan filament:cache-components >/dev/null 2>&1 || true
SSHEOF
vert "Base et caches à jour"

# ---------------------------------------------------------------------------
bleu "Publication des fichiers statiques dans le dossier web"
"${SSH[@]}" bash -s <<SSHEOF
set -e

# Tout le contenu de public/ est recopié dans la racine web, SAUF le lien
# symbolique des photographies, qu'on refait ensuite avec un chemin absolu.
rsync -a --delete \
    --exclude '.well-known' \
    --exclude 'cgi-bin' \
    --exclude 'storage' \
    '$DOSSIER_APP/public/' '$DOSSIER_WEB/'

# Le contrôleur d'entrée doit pointer vers l'application, qui n'est plus son
# dossier parent.
cat > '$DOSSIER_WEB/index.php' <<'PHPEOF'
<?php

/*
 * Filigrane — contrôleur d'entrée.
 *
 * L'application elle-même se trouve en dehors de la racine web : seuls ce
 * fichier et les fichiers statiques sont accessibles par le serveur.
 * Ce fichier est réécrit à chaque déploiement (scripts/deployer.sh).
 */

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

\$application = '$DOSSIER_APP';

if (file_exists(\$maintenance = \$application.'/storage/framework/maintenance.php')) {
    require \$maintenance;
}

require \$application.'/vendor/autoload.php';

/** @var Application \$app */
\$app = require_once \$application.'/bootstrap/app.php';

\$app->handleRequest(Request::capture());
PHPEOF

# Les photographies sont servies en statique depuis le disque public.
rm -rf '$DOSSIER_WEB/storage'
ln -s '$DOSSIER_APP/storage/app/public' '$DOSSIER_WEB/storage'

chmod 644 '$DOSSIER_WEB/index.php'
SSHEOF
vert "Dossier web publié"

# ---------------------------------------------------------------------------
bleu "Vérification"
CODE=$(curl -s -o /dev/null -w '%{http_code}' "$URL/login" || true)

if [ "$CODE" = "200" ]; then
    vert "$URL/login répond 200"
else
    rouge "$URL/login répond $CODE — voir DOCS/06-deploiement.md"
    exit 1
fi

vert "Déploiement terminé"
