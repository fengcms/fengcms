#!/bin/sh
set -eu
if [ "${1:-}" = 'php-fpm' ] && [ "$(id -u)" = 0 ]; then
    worker_uid=$(id -u www-data)
    marker=/var/lib/fengcms/.volume-owner
    previous_uid=$(cat "$marker" 2>/dev/null || true)
    for directory in /var/lib/fengcms /var/www/html/app/cache /var/www/html/admin/app/cache /var/www/html/admin/app/dbbackup /var/www/html/upload; do
        mkdir -p "$directory"
        # Migrate named-volume ownership once when the worker UID changes.
        # Never traverse or chmod the source/template bind mounts or symlinks.
        if [ "$previous_uid" != "$worker_uid" ] || [ "$(stat -c %u "$directory")" != "$worker_uid" ]; then
            find "$directory" -xdev ! -type l -exec chown "$worker_uid":www-data {} +
        fi
    done
    printf '%s\n' "$worker_uid" > "$marker"
fi
exec docker-php-entrypoint "$@"
