#!/usr/bin/env bash
set -euo pipefail

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
REDIS_HOST="${REDIS_HOST:-127.0.0.1}"
REDIS_PORT="${REDIS_PORT:-6379}"

check_port() {
    local host="$1" port="$2" label="$3"
    if ! bash -c "echo > /dev/tcp/${host}/${port}" 2>/dev/null; then
        echo "❌  ${label} não está a responder em ${host}:${port}"
        echo "    Arranca o Docker: docker compose up -d"
        exit 1
    fi
}

check_port "$DB_HOST"    "$DB_PORT"    "MySQL"
check_port "$REDIS_HOST" "$REDIS_PORT" "Redis"
