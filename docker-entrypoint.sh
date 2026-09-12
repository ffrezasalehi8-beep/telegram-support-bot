#!/bin/sh

set -e

if [ -z "$BOT_TOKEN" ]; then
    echo "BOT_TOKEN is missing."
    exit 1
fi

if [ -z "$ADMIN_ID" ]; then
    echo "ADMIN_ID is missing."
    exit 1
fi

if [ -z "$WEBHOOK_SECRET" ]; then
    echo "WEBHOOK_SECRET is missing."
    exit 1
fi

php /app/bin/set-webhook.php

echo "Starting PHP server on port ${PORT:-8080}..."

exec php \
    -d display_errors=0 \
    -d log_errors=1 \
    -d error_reporting=E_ALL \
    -d max_execution_time=30 \
    -S "0.0.0.0:${PORT:-8080}" \
    -t /app/public \
    /app/public/index.php
