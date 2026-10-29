#!/bin/sh
set -e

# En dev, le projet est monté depuis macOS : on s'assure simplement que Laravel
# peut écrire ses caches et ses fichiers de session.
if [ -d /var/www/html/storage ]; then
    chmod -R a+rwX /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true
fi

exec "$@"
