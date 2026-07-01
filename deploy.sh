#!/usr/bin/env bash
# Server-side deploy: run inside the project directory on the VPS.
# Pulls the merged main, rebuilds the stack and applies migrations.
set -euo pipefail

# .env is gitignored: create it from the example on the first deploy.
[ -f .env ] || cp .env.example .env

echo "==> Updating main"
git checkout main
git pull --ff-only origin main

echo "==> Building and starting containers"
docker compose up -d --build

echo "==> Applying migrations"
docker compose exec -T app1 php bin/console doctrine:migrations:migrate --no-interaction

echo "==> Restarting worker to pick up new code"
docker compose restart worker

echo "==> Deployed on main"
