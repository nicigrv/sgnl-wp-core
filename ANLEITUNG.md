# SG Neptun – Umstellung auf das neue Theme (Version 2)

Ergebnis: eigenes Theme ohne Mesmerize, alle Inhalte als normale WordPress-Blöcke,
15 Fremd-Plugins weniger. Lokal mit einer Kopie der Live-Datenbank getestet:
132 Seiten/Beiträge, alle Inhalte im Editor ohne Fehler.

## Was bleibt
| Plugin | Wofür |
|---|---|
| SG Neptun Funktionen (neu) | Tabellen-Verwaltung, Cookie-Banner, Hinweise/Popups, Weiterleitungen, iFrame-Block, Kommentare aus, Umstellung |
| Contact Form 7 | die 4 Formulare |
| ManageWP Worker | Verwaltung über ManageWP Orion |
| Simple History, Media Cleaner, FileBird | Verwaltung |
| greylo-login, greylo-chatwoot, sgnl-microsoft-login | eigene Plugins |

## Reihenfolge (ca. 15 Minuten)

1. **Backup** von Dateien und Datenbank (z. B. über ManageWP).
2. **Plugin** hochladen: Plugins → Installieren → Plugin hochladen → `sgnl-core.zip` (aus dem neuesten Release) → Aktivieren.
3. **Theme** hochladen: Design → Themes → Theme hinzufügen → Theme hochladen → `sgnl-neptun.zip` (aus dem neuesten Theme-Release)
   → „Aktuelle Version durch hochgeladene ersetzen“. Danach eine beliebige Seite einmal aufrufen
   (das Theme trennt sich dabei selbst von Mesmerize).
   *Ab hier bis Schritt 4 sieht die Startseite unformatiert aus – also direkt weitermachen.*
4. **Werkzeuge → SG Neptun Umstellung**
   - Schritt 1 muss „passt“ zeigen.
   - Schritt 2: Liste prüfen, Backup-Haken setzen, **Jetzt umstellen**.
     (Originalinhalte werden gesichert; „Umstellung rückgängig machen“ stellt sie wieder her.)
   - Schritt 3: alte Plugins **deaktivieren**.
5. **Seite durchklicken**: Startseite, Terminplan, Vorstand, Trainerteam, Mannschaften,
   Links & Downloads, Galerien, Rekorde, Mitglied werden, ein Beitrag – auch am Handy.
6. Unter **Plugins** die deaktivierten Plugins löschen, unter **Design → Themes**
   Mesmerize, EmpowerWP und SiteOrigin Corp löschen.
7. Optional **Schritt 4 „Aufräumen“**: löscht Daten alter Plugins (Datenbank ca. 68 MB → 26 MB).
   Gespeicherte Flamingo-Kontaktanfragen sind standardmäßig *nicht* angehakt.
   Nicht rückgängig zu machen – nur nach Prüfung.

## Danach per FTP löschen (Altlasten, ca. 2,1 GB)
- `wp-content/ai1wm-backups/` (alte All-in-One-Backups von Jan. 2025, ~2 GB)
- `wp-content/uploads/omgf/`, `wp-content/uploads/siteorigin-widgets/`
- `wp-content/advanced-cache.php`, `wp-content/wphb-cache/`, `wp-content/jetpack-waf/`,
  `wp-content/shield/`, `wp-content/updraft/`, `wp-content/wflogs/`, `wp-content/temp-write-test-*`
- Wordfence-Reste: Block „Wordfence WAF“ in `.htaccess` und `.user.ini` (zeigt noch auf einen
  alten STRATO-Pfad) und danach `wordfence-waf.php` – **erst die Einträge entfernen, dann die Datei.**

## Was sich inhaltlich geändert hat
- Startseite: gleiche Abschnitte, jetzt normale Blöcke. News zeigen automatisch die 4 neuesten Beiträge.
- Alle 8 TablePress-Tabellen (auch die ungenutzte „Trainingszeiten“) liegen danach unter **Tabellen**;
  auf den Seiten steht der Block „Tabelle“. Ausgeblendete Zeilen/Spalten bleiben ausgeblendet.
- Korrigiert: Button „Jetzt Vorschwimmen anfragen“ (war `matilo:` statt `mailto:`),
  Link „Terminplan“ auf der Startseite (zeigte auf `/sgnl.de/terminplan`).
- Entfernt, weil schon vorher nichts angezeigt wurde: `[wpspeedo-team]` (Ansprechpartner, Kontakt,
  Schwimmschule) und `[wpforms]` (Online-Ausschreibungen) – die Plugins waren bereits gelöscht.
- **Bitte ergänzen:** Button „Meldeergebnis Herunterladen“ auf *Aktuelle Meldeergebnisse* hatte nie einen Link.
- Externe Einbettungen (Rekorde/webclub, Spond, Kalender) laden erst nach Zustimmung oder Klick.
  Eigene Subdomains (*.sgnl.de, z. B. die Umfrage) laden direkt.
- Der Chat (Chatwoot) startet erst nach Zustimmung im Cookie-Banner.
- Die WPMU-DEV-Besucherstatistik (Matomo) entfällt mit dem WPMU-DEV-Plugin.
- **Datenschutzerklärung prüfen:** CookieYes und ggf. WPMU-DEV/Matomo herausnehmen,
  eigenen Cookie-Banner (nur notwendiges Cookie `sgnl_consent`) und Chatwoot beschreiben.

## Tabellen (z. B. Terminplan)
- Dashboard → **Tabellen** → Tabelle anklicken. Zellen direkt bearbeiten, mit Tab zur nächsten Zelle.
- Zeilennummer bzw. Spaltenbuchstabe anklicken: einfügen, verschieben, duplizieren, ausblenden, löschen.
- Aus Excel mehrere Zellen kopieren und in eine Zelle einfügen – wird automatisch verteilt.
- Zeile, in der nur die erste Zelle gefüllt ist (z. B. „Oktober“), erscheint als Zwischenüberschrift.
- „Aktualisieren“ speichert; jede Version bleibt unter „Revisionen“ wiederherstellbar.
- Rechts: Kopf-/Fußzeile, Name über der Tabelle, Hinweis darunter, **CSV importieren / herunterladen**
  (Excel: „CSV (Trennzeichen-getrennt)“).
- Auf einer Seite einbinden: Block **„Tabelle“** einfügen und Tabelle auswählen (oder `[sgnl_tabelle id="…"]`).
- Eingeloggt sieht man auf der Website unter jeder Tabelle den Link „Tabelle bearbeiten“.

## Updates
Theme und Plugin aktualisieren sich danach über **Dashboard → Aktualisierungen** direkt aus GitHub
(nicigrv/sgnl-wp-theme und nicigrv/sgnl-wp-core). Kein ZIP-Hochladen mehr nötig.

## Bearbeiten im Alltag
- **Design → Anpassen → SG Neptun**: Startseiten-Überschrift, Buttons, Slideshow-Bilder,
  Kopfbilder der Unterseiten, Button „Mitglied werden“, Footer.
- **Hinweise (Popup)** im Menü: Hinweis anlegen, „Hinweis anzeigen“ anhaken, optional Enddatum.
- **Werkzeuge → Weiterleitungen**: eine Zeile pro Weiterleitung.
- **Einstellungen → Cookie-Banner**: Texte und Links.
- Im Editor unter Muster → **SG Neptun**: Team-Raster, Info-Box mit Button, Abschnitt Text + Bild.
- Block-Stile (rechts unter „Stile“): Überschrift „Mit Bahnleine“, Gruppe „Karte“/„Info-Box“,
  Bild „Porträt (rund)“/„Foto mit Schatten“, Button „Mit Mail-/Download-/Link-Symbol“,
  Absatz „Kennzahl (zählt hoch)“, Tabelle „Vereinstabelle“.
- Galerien: normaler Galerie-Block, Link auf „Vergrößern beim Klicken“ – mit Blättern.
- Tabellen (Terminplan): normaler Tabellen-Block, direkt in der Seite bearbeiten.
