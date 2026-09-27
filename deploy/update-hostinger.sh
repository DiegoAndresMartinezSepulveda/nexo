#!/usr/bin/env bash
set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)"
PHP_BIN="${PHP_BIN:-/opt/alt/php82/usr/bin/php}"
PUBLIC_ROOT="$(cd "$PROJECT_ROOT/../public_html" && pwd -P)"

if [[ ! -f "$PROJECT_ROOT/artisan" || "$(dirname "$PUBLIC_ROOT")" != "$(dirname "$PROJECT_ROOT")" ]]; then
  echo "Comprueba que flujo y public_html sean carpetas hermanas." >&2
  exit 1
fi
if [[ "$(basename "$PROJECT_ROOT")" != "flujo" ]]; then
  echo "Esta instalación usa la carpeta privada flujo. Ajusta el adaptador antes de cambiarla." >&2
  exit 1
fi
if [[ ! -f "$PROJECT_ROOT/.env" || ! -f "$PROJECT_ROOT/composer.lock" || ! -f "$PROJECT_ROOT/public/app/index.html" || ! -f "$PROJECT_ROOT/deploy/hostinger-index.php" ]]; then
  echo "Falta .env, composer.lock, la compilación de Angular o el adaptador de Hostinger." >&2
  exit 1
fi
export PATH="$(dirname "$PHP_BIN"):$PATH"
COMPOSER_BIN="${COMPOSER_BIN:-$(command -v composer2 || command -v composer || true)}"
if [[ -z "$COMPOSER_BIN" ]]; then
  echo "No se encontró Composer 2. Configura COMPOSER_BIN con su ruta." >&2
  exit 1
fi
cd "$PROJECT_ROOT"
"$PHP_BIN" -r 'exit(PHP_VERSION_ID >= 80200 ? 0 : 1);'
"$COMPOSER_BIN" --version
"$PHP_BIN" artisan down --retry=60
trap 'echo "Actualización interrumpida. Revisa el error y vuelve a ejecutar este script. El sitio sigue en mantenimiento." >&2' ERR
"$COMPOSER_BIN" install --no-dev --prefer-dist --optimize-autoloader --no-interaction
"$PHP_BIN" artisan optimize:clear
if [[ ! -f "$PROJECT_ROOT/vendor/autoload.php" ]]; then
  echo "Composer terminó sin crear vendor/autoload.php." >&2
  exit 1
fi
"$PHP_BIN" artisan --version
"$PHP_BIN" artisan migrate --force
# Only public assets are copied; .env, uploads and database are never overwritten.
cp -R "$PROJECT_ROOT/public/." "$PUBLIC_ROOT/"
cp "$PROJECT_ROOT/deploy/hostinger-index.php" "$PUBLIC_ROOT/index.php"
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan up
echo "Nexo actualizado. Abre el sitio y recarga la página."
