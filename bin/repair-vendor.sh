#!/usr/bin/env bash
# Detecta e repara corrupção do vendor/ (directórios duplicados com espaço,
# ex: "phpunit 2", causados pelo extractor ZIP do macOS).
# Uso: bash bin/repair-vendor.sh [--force]
set -euo pipefail

VENDOR_DIR="vendor"

if [ ! -d "$VENDOR_DIR" ]; then
    echo "⚠️  Pasta vendor/ inexistente. A instalar..."
    composer install --no-scripts --no-interaction
    php artisan package:discover --ansi 2>/dev/null || true
    composer dump-autoload --no-interaction
    echo "✅  Vendor instalado com sucesso."
    exit 0
fi

CORRUPTED=$(find "$VENDOR_DIR" -maxdepth 2 -name "* *" -type d 2>/dev/null | wc -l | tr -d ' ')

if [ "$CORRUPTED" -gt 0 ] || [ "${1:-}" = "--force" ]; then
    echo "⚠️  Vendor corrompido ($CORRUPTED directórios duplicados). A reparar..."
    rm -rf "$VENDOR_DIR"
    composer install --no-scripts --no-interaction
    php artisan package:discover --ansi 2>/dev/null || true
    composer dump-autoload --no-interaction
    echo "✅  Vendor reparado com sucesso."
else
    echo "✅  Vendor OK."
fi
