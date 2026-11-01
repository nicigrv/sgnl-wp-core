#!/usr/bin/env bash
# Prüft die Seiten über HTTP (PHP-Server) – nach tests/run.php.
set -uo pipefail
WP=${WP_DIR:-/tmp/wp}
DIR=$(cd "$(dirname "$0")" && pwd)
BASE=http://127.0.0.1:8080
FAIL=0
fail() { echo "  FAIL $*"; FAIL=1; }
ok() { echo "  ok   $*"; }

: > /tmp/wp-debug.log

echo "== Seiten"
while read -r u; do
  [ -z "$u" ] && continue
  code=$(curl -s -o /tmp/page.html -w '%{http_code}' "$BASE$u")
  if [ "$code" = "200" ]; then ok "$u → 200"; else fail "$u → $code"; fi
  if grep -qiE 'Fatal error|kritischer Fehler|critical error' /tmp/page.html; then fail "$u enthält eine Fehlermeldung"; fi
done < "$DIR/urls.txt"

echo "== Inhalte"
curl -s "$BASE/" | grep -q 'fill:var(--wp--preset--color--water)' && ok "Welle hat die Farbe des ersten Abschnitts" || fail "Welle nicht eingefärbt"
curl -s "$BASE/terminplan/" | grep -q 'sgnl-events__filter' && ok "Terminplan mit Kategorie-Filter" || fail "Terminplan ohne Filter"
Y=$(sed -n 's#^/galerie/\([0-9]\{4\}\)/$#\1#p' "$DIR/urls.txt" | head -1)
curl -s "$BASE/galerie/$Y/" | grep -q 'sgnl-albums' && ok "/galerie/$Y/ zeigt die Alben" || fail "/galerie/$Y/ zeigt keine Alben"
curl -s "$BASE/galerie/$Y/dlp/" | grep -qE 'wp-lightbox-container|data-wp-interactive' && ok "Album: Bilder vergrößerbar" || fail "Album: keine Lightbox"
read -r OLD NEW < "$DIR/redirect.txt"
out=$(curl -s -o /dev/null -w '%{http_code} %{redirect_url}' "$BASE$OLD")
case "$out" in 301*"$NEW") ok "Alte Galerie-Adresse leitet weiter ($out)";; *) fail "Weiterleitung $OLD: $out";; esac
curl -s "$BASE/wp-json/sgnl/v1/live" | python3 -c 'import json,sys; d=json.load(sys.stdin); assert "events" in d' && ok "Live-JSON gültig" || fail "Live-JSON ungültig"
ct=$(curl -s -o /tmp/terminplan.pdf -w '%{content_type}' "$BASE/?sgnl-terminplan=pdf")
[ "$ct" = "application/pdf" ] && ok "PDF wird ausgeliefert" || fail "PDF Content-Type: $ct"
qpdf --check /tmp/terminplan.pdf > /dev/null && ok "PDF (Website) ist gültig" || fail "PDF (Website) ungültig"
qpdf --check "$DIR/test.pdf" > /dev/null && ok "PDF (70 Termine, mehrseitig) ist gültig" || fail "PDF (mehrseitig) ungültig"

echo "== PHP-Fehlerprotokoll"
if grep -E 'PHP (Fatal|Parse|Warning|Notice)' /tmp/wp-debug.log; then fail "PHP-Fehler/Warnungen im Protokoll"; else ok "keine PHP-Fehler oder Warnungen"; fi

exit $FAIL
