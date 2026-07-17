# FinDocProcessor — imagem PHP 8.5 (FPM) para desenvolvimento/demonstração.
# Self-contained: o vendor/ é instalado dentro da imagem, pelo que
# `docker compose up` arranca a partir de um clone limpo sem passos manuais.
FROM php:8.5-fpm-alpine

# Extensões PHP via install-php-extensions (resolve deps de sistema e evita
# as condições de corrida do docker-php-ext-install na compilação do intl).
# - pdo_mysql → MySQL (dev/prod via Docker; testes correm também contra MySQL)
# - redis     → cache/queue (cliente phpredis opcional; o projecto usa predis)
# - gd        → geração de imagens de teste (UploadedFile::fake()->image(), Http\Testing\FileFactory)
# - imagick   → rasterização de PDF/PS para OCR (pipeline de extração, #95/#96); o
#               policy.xml do apk imagemagick já permite ler PDF por omissão (verificado
#               manualmente), ao contrário do policy restritivo típico em bases Debian/Ubuntu.
# libwebp/tiff → delegates do imagick para ler os formatos de upload alargados (#111,
#               RNF-05): WEBP e TIFF (BMP é nativo). Sem eles, o upload aceita mas o OCR
#               falha ao rasterizar. Instalados antes do install-php-extensions imagick.
RUN apk add --no-cache git unzip tesseract-ocr tesseract-ocr-data-por tesseract-ocr-data-eng ghostscript libwebp tiff \
    && curl -sSLf \
       https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions \
       -o /usr/local/bin/install-php-extensions \
    && chmod +x /usr/local/bin/install-php-extensions \
    && install-php-extensions pdo_mysql bcmath intl zip opcache pcntl redis pcov gd imagick

# zz-findoc.ini (CLI + FPM, conf.d partilhada):
# - memory_limit elevado: a análise estática (PHPStan / type-coverage) excede os 128M por omissão.
# - upload_max_filesize/post_max_size: limite de upload de 50 MB (#111, RF-15); post ligeiramente
#   acima do upload para acomodar o overhead do multipart. nginx (client_max_body_size) já em 50M.
RUN printf "memory_limit=512M\nupload_max_filesize=50M\npost_max_size=52M\n" > "$PHP_INI_DIR/conf.d/zz-findoc.ini"

# Composer (binário oficial).
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Instalar dependências primeiro (camada cacheável enquanto composer.* não muda).
COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-progress --no-scripts --no-autoloader

# Copiar o resto do código e finalizar o autoloader.
COPY . .
RUN mkdir -p bootstrap/cache storage/framework/{cache,sessions,views} storage/logs \
    && composer dump-autoload --optimize \
    && chown -R www-data:www-data storage bootstrap/cache

# Entrypoint: prepara .env/APP_KEY, corre migrations + seed e arranca o FPM.
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 9000
ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
