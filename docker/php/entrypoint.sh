#!/bin/sh
set -e

# Wait for PostgreSQL.
until php -r 'exit(@fsockopen(getenv("DB_HOST") ?: "postgres", 5432) ? 0 : 1);' 2>/dev/null; do
    echo "waiting for postgres..."
    sleep 1
done

# Publish public/ to the Nginx-shared volume.
[ -n "${SYNC_PUBLIC_TO}" ] && mkdir -p "${SYNC_PUBLIC_TO}" && cp -a /var/www/public/. "${SYNC_PUBLIC_TO}/"

# Only the primary node migrates (avoids races between instances).
[ "${RUN_MIGRATIONS}" = "1" ] && php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

exec "$@"