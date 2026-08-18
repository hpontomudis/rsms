#!/bin/sh
set -e

# Runtime environment variables (DB_*, APP_KEY, etc.) come from Coolify and
# only exist once the container starts -- config:cache/view:cache must run
# here, never during the image BUILD, or they would bake in empty values.
# Deliberately excludes migrate/seed/bootstrap-admin: those are destructive
# or one-time administrative actions and belong to an explicit deploy /
# pre-deploy step, not something every container restart re-runs.
php artisan config:cache
php artisan view:cache

exec "$@"
