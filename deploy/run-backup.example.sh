#!/bin/sh
set -eu

APP_ROOT=${APP_ROOT:-/srv/www/hawpiwcloud}
ENV_FILE=${BACKUP_ENV_FILE:-/etc/hawpiwcloud/backup.env}
PHP_BIN=${PHP_BIN:-/usr/bin/php}

if [ ! -r "$ENV_FILE" ]; then
    echo "Backup environment file tidak dapat dibaca: $ENV_FILE" >&2
    exit 1
fi

set -a
. "$ENV_FILE"
set +a

case "${1:-backup}" in
    backup)
        BACKUP_TYPE=incremental
        if [ "$(date +%u)" = "7" ] || [ "$(date +%d)" = "01" ]; then
            BACKUP_TYPE=full
        fi
        exec "$PHP_BIN" "$APP_ROOT/bin/backup.php" --reason=scheduled --type="$BACKUP_TYPE"
        ;;
    purge-trash)
        exec "$PHP_BIN" "$APP_ROOT/bin/purge-trash.php" --older-than-days="${TRASH_RETENTION_DAYS:-30}"
        ;;
    retention)
        exec "$PHP_BIN" "$APP_ROOT/bin/retention.php"
        ;;
    *)
        echo "Gunakan: $0 [backup|purge-trash|retention]" >&2
        exit 2
        ;;
esac
