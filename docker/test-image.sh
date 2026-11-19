#!/bin/sh
# Controleert een image van het technisch register: start het met een lege opslag en test
# de rootless start, de bestanden, de omleidingen achter een proxy, de artefactmappen en de
# webhook. Gebruik: docker/test-image.sh <image>
# De pagina's die GitHub raadplegen (/ en de lijsten per standaard) test het niet, zodat de
# test ook zonder internettoegang van de container werkt.
set -eu

image="${1:?gebruik: docker/test-image.sh <image>}"
name="register-test-$$"
volume="register-test-$$"
port="${TEST_PORT:-18080}"
secret="testgeheim"
base="https://register.example.test"
failures=0

cleanup() { docker rm -f "$name" >/dev/null 2>&1 || true; docker volume rm "$volume" >/dev/null 2>&1 || true; }
trap cleanup EXIT

check() { # check <omschrijving> <verwacht> <werkelijk>
    if [ "$2" = "$3" ]; then echo "ok    $1"; else echo "FOUT  $1: verwacht '$2', kreeg '$3'"; failures=$((failures + 1)); fi
}

start() {
    docker run -d --name "$name" -p "127.0.0.1:$port:8080" -v "$volume:/var/www/geostandaarden/v2" \
        -e REGISTER_BASE_URL="$base/" -e REGISTER_WEBHOOK_SECRET="$secret" "$image" >/dev/null
    i=0
    until curl -fs -o /dev/null "http://127.0.0.1:$port/resources/css/style.css"; do
        i=$((i + 1))
        if [ "$i" -gt 30 ]; then echo "FOUT  de container start niet:"; docker logs "$name"; exit 1; fi
        sleep 1
    done
}
status() { curl -s -o /dev/null -w '%{http_code}' "$@"; }

start

check "draait als uid 33 (www-data)" "33" "$(docker exec "$name" id -u)"
check "geen Apache-proces als root" "0" "$(docker exec "$name" sh -c 'for p in /proc/[0-9]*; do [ "$(cat $p/comm)" = apache2 ] && awk "/^Uid:/{print \$2}" $p/status; done | grep -cx 0 || true')"
check "PHP-extensie zip" "zip" "$(docker exec "$name" php -m | grep -x zip)"
check "code in de opslag gezet" "200" "$(status "http://127.0.0.1:$port/resources/css/style.css")"
check "geen PHP-versie in de headers" "" "$(curl -sI "http://127.0.0.1:$port/resources/css/style.css" | grep -i '^x-powered-by' || true)"
check "omleiding naar het publieke https-adres" "$base/?url=imgeo" \
    "$(curl -s -o /dev/null -w '%{redirect_url}' "http://127.0.0.1:$port/imgeo")"

# een gepubliceerd artefact, en een PHP-bestand dat daar niet uitgevoerd mag worden
docker exec "$name" sh -c 'd=/var/www/geostandaarden/v2/production/xmlschema/test/1.0; mkdir -p $d; echo "<schema/>" > $d/test.xsd; echo "<?php echo 1;" > $d/x.php'
check "artefact te downloaden" "200" "$(status "http://127.0.0.1:$port/xmlschema/test/1.0/test.xsd")"
check "mappenlijst van een artefactmap" "1" "$(curl -s "http://127.0.0.1:$port/xmlschema/test/1.0/" | grep -c 'test.xsd' || true)"
check "PHP in een artefactmap geweigerd" "403" "$(status "http://127.0.0.1:$port/xmlschema/test/1.0/x.php")"

# webhook
check "webhook: GET geweigerd" "405" "$(status "http://127.0.0.1:$port/autodeploy/releasecreated.php")"
check "webhook: zonder handtekening geweigerd" "401" \
    "$(status -X POST -H 'X-GitHub-Event: ping' -d '{}' "http://127.0.0.1:$port/autodeploy/releasecreated.php")"
body='{"zen":"test"}'
sig="sha256=$(printf '%s' "$body" | openssl dgst -sha256 -hmac "$secret" | awk '{print $NF}')"
check "webhook: ping met geheim uit de omgeving" "200" \
    "$(status -X POST -H 'X-GitHub-Event: ping' -H "X-Hub-Signature-256: $sig" -H 'Content-Type: application/json' -d "$body" "http://127.0.0.1:$port/autodeploy/releasecreated.php")"

# herstart met een nieuwere versie van de code: artefacten blijven, verdwenen code gaat weg
docker exec "$name" sh -c 'f=/var/www/geostandaarden/v2; echo oud > $f/production/oud.js; echo ./oud.js >> $f/.register-code-files; LC_ALL=C sort -o $f/.register-code-files $f/.register-code-files'
docker rm -f "$name" >/dev/null
start
check "na herstart: oude code weg" "404" "$(status "http://127.0.0.1:$port/oud.js")"
check "na herstart: artefact behouden" "200" "$(status "http://127.0.0.1:$port/xmlschema/test/1.0/test.xsd")"

if [ "$failures" -gt 0 ]; then
    echo "$failures controle(s) mislukt"; docker logs "$name" 2>&1 | tail -20; exit 1
fi
echo "alle controles geslaagd"
