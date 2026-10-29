#!/usr/bin/env bash
#
# Filigrane — contrôle de la mise en ligne.
#
#   ./scripts/verifier-production.sh
#
# Vérifie ce qui doit répondre, et surtout ce qui ne doit PAS répondre.
# À relancer après chaque déploiement.

set -uo pipefail

RACINE="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

# shellcheck source=/dev/null
[ -f "$RACINE/.deploiement.env" ] && { set -a; . "$RACINE/.deploiement.env"; set +a; }

URL="${FILIGRANE_URL:?Renseignez .deploiement.env (voir .deploiement.env.example)}"

vert()  { printf '  \033[1;32m✓\033[0m %s\n' "$1"; }
rouge() { printf '  \033[1;31m✗\033[0m %s\n' "$1"; }

echecs=0

# Le code attendu peut en désigner plusieurs, séparés par « | » : l'hébergeur
# répond parfois 403 là où l'on attendrait 404, ce qui protège tout autant.
attendu() {
    local description="$1" chemin="$2" acceptes="$3"
    local obtenu
    obtenu=$(curl -s -o /dev/null -w '%{http_code}' "$URL$chemin")

    if [[ "|$acceptes|" == *"|$obtenu|"* ]]; then
        vert "$description ($chemin → $obtenu)"
    else
        rouge "$description ($chemin → $obtenu, attendu $acceptes)"
        echecs=$((echecs + 1))
    fi
}

contient() {
    local description="$1" chemin="$2" motif="$3"
    local corps

    # On récupère le corps avant de chercher dedans : enchaîner curl et grep -q
    # ferait échouer curl sur un tube refermé, et « pipefail » le signalerait
    # comme une erreur alors que le motif a bien été trouvé.
    corps=$(curl -s "$URL$chemin")

    if printf '%s' "$corps" | grep -q "$motif"; then
        vert "$description"
    else
        rouge "$description"
        echecs=$((echecs + 1))
    fi
}

absent() {
    local description="$1" chemin="$2" motif="$3"
    local corps

    corps=$(curl -s "$URL$chemin")

    if printf '%s' "$corps" | grep -Eq "$motif"; then
        rouge "$description"
        echecs=$((echecs + 1))
    else
        vert "$description"
    fi
}

echo
echo "Filigrane — contrôle de $URL"
echo

echo "Ce qui doit répondre :"
attendu "page de connexion"        "/login"                   200
attendu "manifeste"                "/manifest.webmanifest"    200
attendu "service worker"           "/sw.js"                   200
attendu "icône 192"                "/icones/icone-192x192.png" 200
attendu "icône iOS 180"            "/icones/icone-180x180.png" 200
attendu "page hors-ligne"          "/hors-ligne"              200
attendu "présentation publique"    "/a-propos"                200
attendu "aperçu de la présentation" "/apercus/diaporama.jpg"  200
attendu "état de l'application"    "/up"                      200

echo
echo "Ce qui doit être protégé :"
attendu "accueil (redirige vers la connexion)" "/"             302
attendu "liste des dessins"        "/dessins"                 302
attendu "diaporama"                "/diaporama"               302
attendu "comptes"                  "/comptes"                 302
# Sans session, la route de remise d'un export doit renvoyer sur la connexion
# bien avant de regarder si le fichier existe.
attendu "remise d'un export"       "/exports/0123456789abcdef.pdf" 302

echo
echo "Ce qui ne doit pas exister :"
attendu "fichier de configuration" "/.env"                    "403|404"
attendu "dépôt git"                "/.git/config"             "403|404"
attendu "composer.json"            "/composer.json"           404
attendu "code source"              "/app/Models/Drawing.php"  404
attendu "journaux"                 "/storage/logs/laravel.log" 404
attendu "sonde de diagnostic"      "/_sonde.php"              404

echo
echo "Contenu :"
contient "la page de connexion est en français"  "/login"  "Mot de passe"

# Les 168 traductions manquantes vivent dans lang/vendor/. Un motif rsync mal
# ancré les avait un jour laissées sur place, et l'interface affichait des
# identifiants bruts sans que rien ne le signale.
absent "aucune clé de traduction brute n'apparaît" "/login" 'filament-[a-z]*::'
contient "la présentation ne laisse filtrer aucune fiche" "/a-propos" "Conception et développement"
contient "le manifeste nomme l'application"      "/manifest.webmanifest" "Filigrane"
contient "le service worker est servi en JavaScript" "/sw.js" "filigrane-v"

echo
echo "Transport :"
destination=$(curl -s -o /dev/null -w '%{redirect_url}' "http://${URL#https://}/login")

if [[ "$destination" == https://* ]]; then
    vert "HTTP redirige vers HTTPS ($destination)"
else
    rouge "HTTP ne redirige pas vers HTTPS — les cookies « secure » ne survivraient pas"
    echecs=$((echecs + 1))
fi

entetes=$(curl -s -I "$URL/login")

for entete in "Strict-Transport-Security" "X-Content-Type-Options" "X-Frame-Options" "Referrer-Policy"; do
    if printf '%s' "$entetes" | grep -qi "^$entete:"; then
        vert "en-tête $entete"
    else
        rouge "en-tête $entete absent"
        echecs=$((echecs + 1))
    fi
done

echo
if [ "$echecs" -eq 0 ]; then
    printf '\033[1;32mTout est en ordre.\033[0m\n\n'
else
    printf '\033[1;31m%d contrôle(s) en échec.\033[0m\n\n' "$echecs"
    exit 1
fi
