#!/bin/sh
# Start van de container van het technisch register.
#
# Alles wat het register schrijft, staat in $base, de blijvende opslag. De code komt uit het
# image: bij elke start zet dit script hem in $base/production, over de vorige code heen.
# Bestanden die een eerdere versie van het image daar zette en die er nu niet meer in zitten,
# worden weggehaald. Gepubliceerde artefacten blijven staan: die zitten niet in het image.
set -eu

base=/var/www/geostandaarden/v2
code=/opt/register/src
manifest="$base/.register-code-files"

if ! touch "$base/.schrijftest" 2>/dev/null; then
    echo "register: $base is niet beschrijfbaar voor uid $(id -u). Koppel daar blijvende opslag die deze gebruiker mag beschrijven." >&2
    exit 1
fi
rm -f "$base/.schrijftest"

mkdir -p "$base/production" "$base/staging" "$base/backup" "$base/log/deliveries" "$base/tmp"

new="$(mktemp)"
(cd "$code" && find . -type f | LC_ALL=C sort) > "$new"
if [ -f "$manifest" ]; then
    LC_ALL=C comm -23 "$manifest" "$new" | while IFS= read -r f; do
        rm -f "$base/production/$f"
    done
fi
cp -R "$code/." "$base/production/"
mv "$new" "$manifest"
mkdir -p "$base/production/autodeploy/status"

# De publieke naam voor Apache: schema en host uit REGISTER_BASE_URL, zonder pad.
url="${REGISTER_BASE_URL:-https://register.geostandaarden.nl/}"
REGISTER_SERVER_NAME="$(printf '%s' "$url" | sed -E 's#^([a-z]+://[^/]+).*#\1#')"
export REGISTER_SERVER_NAME

if [ -z "${REGISTER_WEBHOOK_SECRET:-}" ] && [ ! -s "${REGISTER_WEBHOOK_SECRET_FILE:-$base/webhook-secret}" ]; then
    echo "register: geen webhookgeheim (REGISTER_WEBHOOK_SECRET); de webhook weigert releases tot het is ingesteld." >&2
fi

exec "$@"
