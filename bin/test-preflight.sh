#!/bin/sh
set -eu

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
REDIS_HOST="${REDIS_HOST:-127.0.0.1}"
REDIS_PORT="${REDIS_PORT:-6379}"

check_port() {
    host="$1" port="$2" label="$3"
    if ! nc -z -w1 "${host}" "${port}" 2>/dev/null; then
        echo "❌  ${label} não está a responder em ${host}:${port}"
        echo "    Arranca o Docker: docker compose up -d"
        exit 1
    fi
}

check_port "$DB_HOST"    "$DB_PORT"    "MySQL"
check_port "$REDIS_HOST" "$REDIS_PORT" "Redis"
