#!/bin/sh
# Entrypoint do container `app` (PHP-FPM) e `queue`.
# Idempotente: pode correr em cada arranque sem efeitos colaterais.
set -e

cd /var/www/html

# Garantir directórios necessários ao Laravel (ignorados pelo git).
mkdir -p bootstrap/cache \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs

# Garantir .env (a partir do exemplo) na primeira execução.
if [ ! -f .env ]; then
    cp .env.example .env
fi

# Gerar APP_KEY se ainda não existir.
if ! grep -q '^APP_KEY=base64:' .env; then
    php artisan key:generate --force --no-interaction
fi

# Só o container `app` prepara a base de dados — o `queue` apenas espera.
# Idempotente: migrations correm sempre (são versionadas); o seed só corre
# quando a base de dados está vazia, para suportar reinícios do container.
if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    echo "A correr migrations..."
    php artisan migrate --force --no-interaction

    if [ "$(php artisan tinker --execute='echo \App\Models\User::query()->exists() ? 1 : 0;' 2>/dev/null | tail -n1)" = "1" ]; then
        echo "Base de dados já populada — seed ignorado."
    else
        echo "Base de dados vazia — a correr seeders..."
        php artisan db:seed --force --no-interaction
    fi
fi

exec "$@"
