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
2. **Plugin** hochladen: Plugins → Installieren → Plugin hochladen → `sgnl-core-1.1.0.zip` → Aktivieren.
3. **Theme** hochladen: Design → Themes → Theme hinzufügen → Theme hochladen → `sgnl-neptun-2.0.0.zip`
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

## Termine (Terminplan + Archiv) – ab Version 1.3.0
- **Einmalig nach dem Update:** Werkzeuge → SG Neptun Umstellung → Schritt 5 „Termine übernehmen“.
  Die Tabellen „Terminplan“ und „Archiv 2024–2026“ werden zu einzelnen Terminen, die Seiten
  bekommen den Block „Termine“. Die alten Tabellen bleiben als Entwurf erhalten, rückgängig machbar.
- Dashboard → **Termine** → „Neuer Termin“: Name, Datum von/bis, Ort, Bahn, Mannschaft,
  Ausschreibung (PDF aus der Mediathek oder Link).
- Nach dem letzten Tag verschwindet der Termin automatisch aus dem Terminplan und steht im
  **Archiv seines Jahres**. Dort beim Termin **Ergebnisse** und **Protokoll** ergänzen.
- Gibt es im Archiv ein neues Jahr (z. B. 2027), wird die Seite „Archiv 2027“ automatisch
  angelegt und im Menü unter „Archiv“ eingefügt.
- Der Button **„Terminplan herunterladen“** erzeugt das PDF (A4) jedes Mal frisch aus den Terminen.
- Besucher können zwischen **Liste** und **Kacheln** wechseln; die Standardansicht ist im Block einstellbar.
- In der Terminliste: Filter „Terminplan“ / „Archiv JJJJ“, A/E/P zeigt Ausschreibung/Ergebnisse/Protokoll,
  „Duplizieren“ für wiederkehrende Veranstaltungen.

## Datenbank + Live – ab Version 1.4.0
- **Einmalig:** Werkzeuge → SG Neptun Umstellung → Schritt 7.
  a) phpMyAdmin der alten Datenbank (datenbank.sgnl.de) → Exportieren → SQL, Datei hochladen →
     Vorschau → „Wettkämpfe übernehmen“. Passende Termine werden ergänzt (Uhrzeiten, Meldeübersicht,
     Meldeergebnis, Live-Timing, Protokoll, Nachrichten), fehlende angelegt.
  b) „Datenbank einrichten“: Seite /datenbank/ mit den Reitern Live, Termine, Ergebnisse, Bestenliste,
     Rekorde. Optional Trainerbereich /datenbank/trainer/ (Schwimmer, Meldungen) mit Passwort.
     Menüpunkte und Links auf datenbank.sgnl.de zeigen danach auf die neue Seite (#live usw. bleibt).
- **Termine sind die einzige Quelle**: Der Terminplan von webclub wird nicht mehr eingebunden.
  Alles wird unter Dashboard → Termine eingetragen.
- **Am Wettkampftag** beim Termin eintragen: Uhrzeit, Meldeübersicht, Meldeergebnis, Live-Timing,
  später Protokoll/Ergebnisse. Im Kasten „Nachrichten (Live)“ kurze Infos veröffentlichen
  (z. B. geänderte Startzeit) – erscheinen sofort groß auf der Live-Karte.
- Im Reiter **Live** steht ein Wettkampf, solange er läuft, und vorher bzw. bis 48 Stunden danach,
  sobald ein Link oder eine Nachricht eingetragen ist. Läuft gerade einer, pulsiert ein roter Punkt am Reiter.
- **Rolle „Terminverwaltung“** (Benutzer → Neu): darf nur Termine pflegen und Dateien hochladen –
  z. B. für Trainer, die bisher im Datenbank-Admin (Microsoft-Login) eingetragen haben.
- Ergebnisse, Bestenliste, Rekorde, Schwimmer, Meldungen kommen weiter aus sgnl.webclub.app und laden
  erst nach Zustimmung zu externen Inhalten.
- Block „Datenbank“ (Reiter frei wählbar) und Block „Live-Wettkämpfe“ lassen sich auf jeder Seite einsetzen.

## Galerien – ab Version 1.3.0
- **Einmalig:** Werkzeuge → SG Neptun Umstellung → Schritt 6 „Galerien einrichten“.
  Aus „Galerie 2024/2025/2026“ wird: /galerie/ (Jahre) → /galerie/2026/ (Alben) → /galerie/2026/dlp/ (Fotos).
  Alte Adressen leiten weiter, Menüpunkte zeigen auf die neuen Seiten. Rückgängig machbar.
- **Neues Album:** Seiten → Neu, Titel z. B. „Vereinsmeisterschaften“, rechts „Übergeordnete Seite“ =
  „Galerie 2026“, Galerie-Block einfügen, **Beitragsbild** = Titelbild der Kachel. Fertig – die Kachel
  erscheint automatisch.
- **Neues Jahr:** Seite „Galerie 2027“ mit Übergeordneter Seite „Galerie“, als Adresse (Permalink) „2027“ eintragen,
  Block **„Galerie-Übersicht“** einfügen.
- Alle Galerien sind automatisch per Klick vergrößerbar (mit Blättern) – auch alte Galerien in Beiträgen.

## Tabellen
- Dashboard → **Tabellen** → Tabelle anklicken. Zellen direkt bearbeiten, mit Tab zur nächsten Zelle.
- Zeilennummer bzw. Spaltenbuchstabe anklicken: einfügen, verschieben, duplizieren, ausblenden, löschen.
- Aus Excel mehrere Zellen kopieren und in eine Zelle einfügen – wird automatisch verteilt.
- Zeile, in der nur die erste Zelle gefüllt ist (z. B. „Oktober“), erscheint als Zwischenüberschrift.
- „Aktualisieren“ speichert; jede Version bleibt unter „Revisionen“ wiederherstellbar.
- Rechts: Kopf-/Fußzeile, Name über der Tabelle, Hinweis darunter, **CSV importieren / herunterladen**
  (Excel: „CSV (Trennzeichen-getrennt)“).
- Auf einer Seite einbinden: Block **„Tabelle“** einfügen und Tabelle auswählen (oder `[sgnl_tabelle id="…"]`).
- Eingeloggt sieht man auf der Website unter jeder Tabelle den Link „Tabelle bearbeiten“.

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
- Galerien: normaler Galerie-Block – Bilder vergrößern sich automatisch beim Klicken.
