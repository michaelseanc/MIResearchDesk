#!/usr/bin/env bash
#
# Deploy hook for Monument Independent Research Desk (Cloudways).
# Run from the application root on every deploy (Cloudways Git "Deployment via SSH" hook, or manually
# over SSH after pulling). Assumes .env is already configured for production (MySQL, APP_URL, etc.).
#
# See docs/deploy/cloudways-checklist.md for the full runbook.
set -euo pipefail

echo "→ Installing PHP dependencies (production)…"
composer install --no-dev --optimize-autoloader --no-interaction

echo "→ Running migrations…"
php artisan migrate --force

echo "→ Rebuilding caches…"
php artisan optimize:clear
php artisan optimize
php artisan filament:assets
php artisan storage:link || true   # no-op if the symlink already exists

echo "→ Restarting queue workers so they pick up new code…"
php artisan queue:restart

echo "✓ Deploy complete."
