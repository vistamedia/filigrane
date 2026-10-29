#!/usr/bin/env bash
#
# Filigrane — copie complète depuis le serveur vers ce Mac.
#
#   ./scripts/sauvegarder.sh [dossier]
#
# Récupère la base de données ET les photographies originales. C'est la seule
# copie qui protège vraiment : les sauvegardes faites sur le serveur
# disparaîtraient avec lui.
#
# Les photographies sont transférées en incrémental : seules les nouvelles
# passent sur le réseau.

set -euo pipefail

RACINE="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

# Voir .deploiement.env.example : les coordonnées du serveur ne sont pas dans
# le dépôt.
# shellcheck source=/dev/null
[ -f "$RACINE/.deploiement.env" ] && { set -a; . "$RACINE/.deploiement.env"; set +a; }

SSH_UTILISATEUR="${FILIGRANE_SSH_USER:?Renseignez .deploiement.env (voir .deploiement.env.example)}"
SSH_HOTE="${FILIGRANE_SSH_HOST:?Renseignez .deploiement.env}"
SSH_CLE="${FILIGRANE_SSH_KEY:-$HOME/.ssh/id_ed25519}"
DOSSIER_APP="${FILIGRANE_APP_DIR:?Renseignez .deploiement.env}"

DESTINATION="${1:-$HOME/Sauvegardes/filigrane}"
HORODATAGE="$(date +%Y-%m-%d-%H%M%S)"

SSH=(ssh -o BatchMode=yes -i "$SSH_CLE" "$SSH_UTILISATEUR@$SSH_HOTE")

bleu() { printf '\033[1;34m→ %s\033[0m\n' "$1"; }
vert() { printf '\033[1;32m✓ %s\033[0m\n' "$1"; }

mkdir -p "$DESTINATION/base" "$DESTINATION/photographies"

bleu "Copie de la base sur le serveur"
"${SSH[@]}" "cd '$DOSSIER_APP' && php artisan filigrane:sauvegarder --no-interaction" | sed 's/^/   /'

bleu "Rapatriement de la dernière copie de la base"
DERNIERE=$("${SSH[@]}" "ls -t \$(dirname '$DOSSIER_APP')/sauvegardes/filigrane-*.sql.gz 2>/dev/null | head -1")

if [ -z "$DERNIERE" ]; then
    echo "Aucune copie trouvée sur le serveur." >&2
    exit 1
fi

scp -q -i "$SSH_CLE" "$SSH_UTILISATEUR@$SSH_HOTE:$DERNIERE" "$DESTINATION/base/"
vert "$(basename "$DERNIERE")"

bleu "Rapatriement des photographies (incrémental)"
rsync -az --info=stats2 \
    -e "ssh -o BatchMode=yes -i $SSH_CLE" \
    "$SSH_UTILISATEUR@$SSH_HOTE:$DOSSIER_APP/storage/app/private/photos/" \
    "$DESTINATION/photographies/" | tail -6

# Un repère daté, pour savoir d'un coup d'œil quand la dernière copie a eu lieu.
printf 'Dernière sauvegarde : %s\n' "$HORODATAGE" > "$DESTINATION/DERNIERE-SAUVEGARDE.txt"

echo
vert "Sauvegarde terminée dans $DESTINATION"
du -sh "$DESTINATION"
