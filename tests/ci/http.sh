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
curl -s "$BASE/" > /tmp/front.html
if grep -q 'fill:var(--wp--preset--color--water)' /tmp/front.html; then ok "Welle hat die Farbe des ersten Abschnitts"; else fail "Welle nicht eingefärbt"; grep -o '<svg class="hero-wave"[^>]*>' /tmp/front.html; grep -o '<body[^>]*>' /tmp/front.html; fi
has() { curl -s "$BASE$1" > /tmp/check.html; grep -qE "$2" /tmp/check.html; }
has / 'sgnl-next__item' && ok "Startseite zeigt die nächsten Termine" || fail "Startseite ohne nächste Termine"
has /terminplan/ 'sgnl-events__filter' && ok "Terminplan mit Kategorie-Filter" || fail "Terminplan ohne Filter"
Y=$(sed -n 's#^/galerie/\([0-9]\{4\}\)/$#\1#p' "$DIR/urls.txt" | head -1)
has "/galerie/$Y/" 'sgnl-albums' && ok "/galerie/$Y/ zeigt die Alben" || fail "/galerie/$Y/ zeigt keine Alben"
has "/galerie/$Y/dlp/" 'wp-lightbox-container|data-wp-interactive' && ok "Album: Bilder vergrößerbar" || fail "Album: keine Lightbox"
read -r OLD NEW < "$DIR/redirect.txt"
out=$(curl -s -o /dev/null -w '%{http_code} %{redirect_url}' "$BASE$OLD")
case "$out" in 301*"$NEW") ok "Alte Galerie-Adresse leitet weiter ($out)";; *) fail "Weiterleitung $OLD: $out";; esac
for pair in "/ /datenbank/" "/index.html /datenbank/" "/trainer.html /datenbank/trainer/" "/admin/ post_type=sgnl_event"; do
  set -- $pair
  out=$(curl -s -o /dev/null -H 'Host: datenbank.example.org' -w '%{http_code} %{redirect_url}' "$BASE$1")
  case "$out" in 301*"$2") ok "datenbank.…$1 → $2";; *) fail "Alte Subdomain $1: $out";; esac
done
curl -s "$BASE/wp-json/sgnl/v1/live" > /tmp/live.json && python3 -c 'import json; d=json.load(open("/tmp/live.json")); assert "events" in d' && ok "Live-JSON gültig" || fail "Live-JSON ungültig"
ct=$(curl -s -o /tmp/terminplan.pdf -w '%{content_type}' "$BASE/?sgnl-terminplan=pdf")
[ "$ct" = "application/pdf" ] && ok "PDF wird ausgeliefert" || fail "PDF Content-Type: $ct"
qpdf --check /tmp/terminplan.pdf > /dev/null && ok "PDF (Website) ist gültig" || fail "PDF (Website) ungültig"
qpdf --check "$DIR/test.pdf" > /dev/null && ok "PDF (70 Termine, mehrseitig) ist gültig" || fail "PDF (mehrseitig) ungültig"

echo "== Vereinsdaten (webclub über die eigene Website)"
J=/tmp/wc-cookies.txt; rm -f $J
code=$(curl -s -c $J -b $J -o /tmp/wc.html -w '%{http_code}' "$BASE/vereinsdaten/oeffentlich-ergebnisse-ex.php")
if [ "$code" = "502" ]; then
  echo "  WARN webclub gerade nicht erreichbar – Prüfung übersprungen"
else
  [ "$code" = "200" ] && ok "Ergebnisse-Seite → 200" || fail "Ergebnisse-Seite → $code"
  grep -q 'Ergebnisse' /tmp/wc.html && ok "Inhalt der Ergebnisse-Seite" || fail "Ergebnisse-Seite ohne Inhalt"
  grep -qi 'webclub\.app' /tmp/wc.html && fail "webclub-Adresse in der Seite" || ok "keine webclub-Adresse in der Seite"
  ajax=$(curl -s -c $J -b $J -o /tmp/wc.json -w '%{http_code}' --data-urlencode 'func=oeff/verlist' --data-urlencode 'data={"start":0,"openid":"0","sitetyp":"-ex","listSORT":"0","listCOUNT":"10"}' "$BASE/vereinsdaten/ajax.php")
  [ "$ajax" = "200" ] && grep -q '"list"' /tmp/wc.json && ok "Datenabfrage (Liste der Veranstaltungen)" || { fail "Datenabfrage → $ajax"; head -c 300 /tmp/wc.json; echo; }
  deny=$(curl -s -o /dev/null -w '%{http_code}' --data-urlencode 'func=std/login' "$BASE/vereinsdaten/ajax.php")
  [ "$deny" = "403" ] && ok "Nicht öffentliche Funktionen gesperrt" || fail "std/login → $deny"
  js=$(curl -s -o /dev/null -w '%{http_code} %{content_type}' "$BASE/vereinsdaten/cdn/v1.79/js/jquery.min.js")
  case "$js" in 200*javascript*) ok "Skripte über die eigene Website ($js)";; *) fail "Skript: $js";; esac
  sw=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/oeffentlich-schwimmer-ex.php")
  [ "$sw" = "200" ] && ok "Sprung zur Schwimmerseite" || fail "Schwimmerseite → $sw"
fi

echo "== PHP-Fehlerprotokoll"
if grep -E 'PHP (Fatal|Parse|Warning|Notice)' /tmp/wp-debug.log; then fail "PHP-Fehler/Warnungen im Protokoll"; else ok "keine PHP-Fehler oder Warnungen"; fi

exit $FAIL
