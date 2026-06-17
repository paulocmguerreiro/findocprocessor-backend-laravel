#!/usr/bin/env bash
# Detecta e repara corrupção do vendor/ (directórios duplicados com espaço,
# ex: "phpunit 2", causados pelo extractor ZIP do macOS).
# Uso: bash bin/repair-vendor.sh [--force] [--update]
#   --force   força reinstalação mesmo sem corrupção detectada
#   --update  actualiza para versões mais recentes (composer update + composer bump)
#             "composer bump" actualiza os limites inferiores das constraints no
#             composer.json para corresponderem ao que foi instalado (requer Composer >= 2.6)
set -euo pipefail

VENDOR_DIR="vendor"
DO_UPDATE=false

for arg in "$@"; do
    [ "$arg" = "--update" ] && DO_UPDATE=true
done

_instalar() {
    composer update --no-scripts --no-interaction
    php artisan package:discover --ansi 2>/dev/null || true
    composer dump-autoload --no-interaction
}

_bump() {
    echo "🔄  A actualizar constraints no composer.json (composer bump)..."
    composer bump
    echo "✅  composer.json actualizado."
}

if [ ! -d "$VENDOR_DIR" ]; then
    echo "⚠️  Pasta vendor/ inexistente. A instalar..."
    _instalar
    [ "$DO_UPDATE" = true ] && _bump
    echo "✅  Vendor instalado com sucesso."
    exit 0
fi

CORRUPTED=$(find "$VENDOR_DIR" -maxdepth 2 -name "* *" -type d 2>/dev/null | wc -l | tr -d ' ')

if [ "$CORRUPTED" -gt 0 ] || [[ " $* " == *" --force "* ]]; then
    echo "⚠️  Vendor corrompido ($CORRUPTED directórios duplicados). A reparar..."
    rm -rf "$VENDOR_DIR"
    _instalar
    [ "$DO_UPDATE" = true ] && _bump
    echo "✅  Vendor reparado com sucesso."
else
    if [ "$DO_UPDATE" = true ]; then
        echo "✅  Vendor OK. A actualizar pacotes..."
        _instalar
        _bump
        echo "✅  Pacotes actualizados com sucesso."
    else
        echo "✅  Vendor OK."
    fi
fi
