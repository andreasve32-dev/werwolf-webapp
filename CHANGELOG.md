# Changelog — Werwolf Web-App

Jedes Backup erhält eine fortlaufende Versionsnummer (v0.0.x).

---

## [v0.0.8] — 2026-07-03

### Hinzugefügt
- **Live-Updates ohne Seitenreload auf allen Seiten** (`liveBlocks()`-Helper):
  Spielfeld, Admin-Dashboard, Totenliste, Rollen, Statistik, FAQ, Spielerfragen —
  Übertragung nur bei Änderungen (Hash/Versions-Probe), Countdown in der Kopfleiste,
  Ladeintervall in den Spieler-Einstellungen wählbar (3–20 s)
- **Spieler-Einstellungen am Konto** (`players.settings`, JSON) — gelten auf allen Geräten
- **Versammlung zu zweit**: erster Spieler beantragt (sichtbar + Push), zweiter unterstützt,
  erst dann steht der Termin; beenden nur durch die beiden Einberufer oder Admin
- **Anklagen nur während laufender Versammlung** (Server-geprüft)
- **Rollen-Flag `killer_sichtbar`**: Rolle und Mörder sehen sich gegenseitig (z. B. Dodo)
- **Effekt-Paket**: Tiefen-Partikel, dichterer Nachtnebel, Sterne/Sonnenstrahlen in der
  Phasen-Überblendung, Todes-Puls, Versammlungs-Glocke, Dodo-Sieges-Auftritt mit Federregen
- CLAUDE.md mit Projekt-Regeln für KI-Assistenten

### Behoben
- Admin-Einstellungen: speichern jetzt automatisch; settings-INSERT scheiterte im
  MariaDB-Strict-Modus; Formular konnte bei JS-Fehler Werte per GET in die URL stellen
- Rolle erschien nach Spielstart nicht ohne Reload; Totenlisten-Popup schloss sich beim
  Auto-Refresh; FAQ-Suche springt jetzt zum Treffer und wechselt den Tab
- Rollen der Toten im Admin verborgen (kein Spoiler); Dorfbewohner-Raster in allen
  Themes gleich; Cooldown-Eingabe begrenzt (0–10080); Testspieler kumulieren
- Wichtige Pushes (Phasenwechsel, Tode, Hinrichtung) umgehen den Push-Cooldown
- `session_write_close()` in den APIs — parallele Polls blockieren sich nicht mehr
- Impressum/Datenschutz: TMG→DDG, TTDSG→TDDDG, Abschnittsnummern korrigiert

### Entfernt
- Hintergrundmusik-Feature samt `audio/`-Ordner; `demo_live.php` (Zweck erfüllt)

### DB-Änderungen (in frischem Schema enthalten)
- `players.settings TEXT NULL`
- `assembly_requests.supporter_id INT NULL`; `scheduled_at` jetzt NULL-bar
- `roles.killer_sichtbar TINYINT(1) DEFAULT 0`
- `settings.label` mit DEFAULT '' (Strict-Mode)

---

## [v0.0.7] — 2026-06-26

### Behoben
- `deaths.php`: Eintragen-Button erscheint jetzt nur noch wenn ein Befragen-Spieler **noch lebt** (`is_alive=1` im Query fehlte)
- `deaths.php`: Button verschwindet nach dem Eintragen (`rolle_aufgedeckt=1`) — kein erneutes Überschreiben möglich
- `deaths.php`: Rolle/Ort/Zeit nach dem Eintragen für **alle** Spieler sichtbar (vorher nur Befragen/Admin)
- `deaths.php`: Button nur beim eigenen Todeseintrag sichtbar (war schon so, jetzt korrekt dokumentiert)
- `deaths.php`: Colspan in der Sub-Zeile korrekt für alle Spieler-Typen

---

## [v0.0.6] — 2026-06-26

### Hinzugefügt
- `db/schema.sql` + `db/migration_cooldown.sql`: Neues Feld `cooldown_started_at` in `game_players` — speichert den Zeitstempel, wann ein Spieler seinen Cooldown manuell gestartet hat
- `api/game.php`: Neuer Endpunkt `start_cooldown` — setzt `cooldown_started_at = NOW()`, prüft ob Cooldown noch läuft
- `game.php`: Cooldown-Button „⏱ Fähigkeit aktivieren" erscheint im Mein-Status-Block für alle Rollen mit Cooldown > 0
- `game.php`: Live-Countdown (MM:SS) während Cooldown läuft, Button gesperrt bis Ablauf

### Verhalten
- Cooldown startet NICHT automatisch beim Töten — der Spieler drückt den Button manuell, wenn er seine Fähigkeit einsetzt
- Gilt für alle Rollen mit Cooldown (Mörder, Hellseherin, …) — kein rollenspezifischer Code nötig
- Countdown läuft im Browser weiter ohne Seite neu zu laden

---

## [v0.0.5] — 2026-06-26

### Hinzugefügt
- `assets/css/app.css`: Rollen-Flammeneffekt-CSS global ausgelagert (`.role-fx`, `.role-spark`, Keyframes)
- `roles.php`: Flammen-Aura + Funken-Partikel im Karten-Modal (Rollenliste)
- `roles.php`: Rollenname im Modal leuchtet (`role-badge--glow`)
- `templates/nav.php`: Neuer Toggle „Rollenname-Leuchten" (`ww_fx_rolename`) im Einstellungs-Sheet
- Zwei separate Einstellungen: **Karten-Flammeneffekt** (Aura + Funken) und **Rollenname-Leuchten** (Badge-Glow) getrennt steuerbar

### Geändert
- `game.php`: Button-Text „Ich bin tot melden" → „Meinen Tod melden"
- CSS-Trennung: `fx-rolecard-off` schaltet nur Flammen/Funken ab; neues `fx-rolename-off` schaltet nur den Glow am Rollennamen ab

---

## [v0.0.4] — 2026-06-26

### Hinzugefügt
- `game.php`: Flammen-Aura + Funken-Partikel um das Rollen-Icon im Status-Block
- `game.php`: Selbe Effekte in der Karten-Vorschau (Modal), Funken laufen nur solange das Modal offen ist
- `game.php`: Rollen-Badge pulsiert mit orangem Glow
- `templates/nav.php`: Toggle „Karten-Flammeneffekt" im Einstellungs-Sheet (localStorage `ww_fx_rolecard`)

### Geändert
- `game.php`: Button „Ich bin tot melden" → „Meinen Tod melden" (korrekteres Deutsch)
- `deaths.php`: Todesursache-Spalte entfernt; Rolle/Ort/Zeit nur nach Nekromant-Besuch sichtbar (`rolle_aufgedeckt`-Flag)
- `deaths.php`: Auto-Refresh bleibt auf der Todesliste (kein Rauswurf durch URL-Maskierung)
- `game.php`: Tote Spieler zeigen im Dorfbewohner-Block keine Rolle mehr
- `core/helpers.php`: `cause`-Parameter aus `recordDeath()` entfernt; `is_gehenkt`-Flag hinzugefügt
- `db/schema.sql`: `cause`-Spalte entfernt; `is_gehenkt` + `rolle_aufgedeckt` hinzugefügt
- `admin/setup.php`: Musik-LocalStorage beim Setup-Abschluss gelöscht
- `api/game.php`: Todeslog zeigt nur noch Name, Phase und Runde — keine Rolle, keine Ursache

### Neu (Migrationen)
- `db/migration_remove_cause.sql`: `cause` droppen, `is_gehenkt` + `rolle_aufgedeckt` hinzufügen

---

## [v0.0.3] — 2026-06-26

### Hinzugefügt
- `db/schema.sql`: Beta-Modus-Einstellung (`beta_mode = 1`) beim Setup
- `core/bootstrap.php`: `BETA_MODE`-Konstante (Fallback: aktiviert)
- `admin/settings.php`: Toggle-Schalter „Beta-Modus" im Allgemein-Block
- `game.php`: Info-Banner „🧪 Beta" (nur wenn Beta-Modus aktiv)
- `db/migration_beta.sql`: Migration für bestehende Installationen
- `CHANGELOG.md`: Versionshistorie für alle Backups

### Geändert
- Versionsnummern-Schema von `1.0.x` auf `0.0.x` umgestellt (Beta-Phase)
- `README.md`: Vollständig aktualisiert — neue Dateien, korrigierte Rollenliste, neue Abschnitte

---

## [v0.0.2] — 2026-06-26

### Entfernt
- Rolle **Superstar** (id 11) aus `db/schema.sql` gelöscht — wird beim Setup nicht mehr angelegt
- Rolle **Star** (id 12) aus `db/schema.sql` gelöscht — wird beim Setup nicht mehr angelegt

### Geändert
- `db/schema.sql`: Rollen **Celebrity**, **Gunslinger**, **Sheriff** von `active=0` auf `active=1` gesetzt — alle Rollen beim Setup direkt aktiv
- `game.php`: Im Dorfbewohner-Block werden bei toten Spielern keine Rollen-Icons und keine Rollennamen mehr angezeigt — nur der Name und das Tod-Symbol bleiben sichtbar
- `core/helpers.php`: In `recordDeath()` wird die laufende Anklage eines sterbenden Spielers sofort aus der `votes`-Tabelle gelöscht — Anklagen von toten Spielern zählen automatisch als ungültig

---

## [v0.0.1] — vor 2026-06-26

*(Änderungen gegenüber v0.0.0 nicht dokumentiert)*

---

## [v0.0.0] — Erstveröffentlichung

- Initiale Version der Werwolf Web-App
