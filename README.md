# SG Neptun Funktionen – WordPress-Plugin

Vereinsfunktionen für [sgnl.de](https://sgnl.de), gehört zum Theme
[sgnl-wp-theme](https://github.com/nicigrv/sgnl-wp-theme):

- **Tabellen** im Dashboard (Editor, CSV-Import/-Export, Block „Tabelle“, `[sgnl_tabelle id="…"]`)
- **Cookie-Banner** (externe iFrames und Chatwoot erst nach Zustimmung)
- **Hinweise (Popup)** mit Zeitraum und Ort
- **Weiterleitungen** (Werkzeuge → Weiterleitungen)
- Block **„Eingebettete Seite (iFrame)“**
- Kommentare überall aus
- **Umstellung** von TablePress, Modula, Team Members, SiteOrigin, Popup Maker, Advanced iFrame,
  Mesmerize Companion (Werkzeuge → SG Neptun Umstellung), siehe [ANLEITUNG.md](ANLEITUNG.md)

## Updates auf der Website
Das Plugin fragt das neueste [Release](../../releases) ab. Neue Versionen erscheinen unter
**Dashboard → Aktualisierungen** bzw. **Plugins** („Details anzeigen“ zeigt die Änderungen).

## Neue Version veröffentlichen
1. Änderungen machen und die Versionsnummer erhöhen in `sgnl-core.php` – im Kopf (`Version:`) **und** bei `SGNL_CORE_VERSION`, z. B. auf `1.2.1`.
2. Commit auf `main` pushen. Die Commit-Nachrichten seit der letzten Version erscheinen in WordPress als Änderungsliste.
3. Die GitHub-Action merkt die neue Versionsnummer, baut die ZIP und legt das Release `v1.2.1` an.
   Ohne neue Versionsnummer wird kein Release erstellt.
