# FinDocProcessor — imagem PHP 8.5 (FPM) para desenvolvimento/demonstração.
# Self-contained: o vendor/ é instalado dentro da imagem, pelo que
# `docker compose up` arranca a partir de um clone limpo sem passos manuais.
FROM php:8.5-fpm-alpine

# Extensões PHP via install-php-extensions (resolve deps de sistema e evita
# as condições de corrida do docker-php-ext-install na compilação do intl).
# - pdo_mysql  → MySQL (prod/demo via Docker)
# - pdo_sqlite → testes (sqlite :memory:)
# - redis      → cache/queue (cliente phpredis opcional; o projecto usa predis)
RUN apk add --no-cache git unzip \
    && curl -sSLf \
       https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions \
       -o /usr/local/bin/install-php-extensions \
    && chmod +x /usr/local/bin/install-php-extensions \
    && install-php-extensions pdo_mysql pdo_sqlite bcmath intl zip opcache pcntl redis pcov

# memory_limit elevado: a análise estática (PHPStan / type-coverage) excede os
# 128M por omissão. Aplica-se a CLI e FPM (conf.d partilhada nesta imagem).
RUN printf "memory_limit=512M\n" > "$PHP_INI_DIR/conf.d/zz-findoc.ini"

# Composer (binário oficial).
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Instalar dependências primeiro (camada cacheável enquanto composer.* não muda).
COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-progress --no-scripts --no-autoloader

# Copiar o resto do código e finalizar o autoloader.
COPY . .
RUN composer dump-autoload --optimize \
    && chown -R www-data:www-data storage bootstrap/cache

# Entrypoint: prepara .env/APP_KEY, corre migrations + seed e arranca o FPM.
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 9000
ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
