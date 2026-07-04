# Changelog — Werwolf Web-App

Jedes Backup erhält eine fortlaufende Versionsnummer (v0.0.x).

---

## [v0.0.14] — 2026-07-04

### Behoben
- **Rollen-Liste (Admin): Aktiv/Inaktiv-Schalter war vertauscht** — aktive Rolle
  zeigte ⏸ (Pause), inaktive ▶ (Play). Statt nur die Icons zu tauschen, auf den
  bestehenden Toggle-Switch (wie in den Admin-Einstellungen) umgebaut — ein
  eindeutiger An/Aus-Schalter ohne Symbol-Mehrdeutigkeit.

---

## [v0.0.13] — 2026-07-04

### Behoben
- **Paar: zweiter Spieler stirbt jetzt automatisch mit.** Neues Rollen-Flag
  `linked_death` — stirbt ein Spieler dieser Rolle, sterben alle anderen lebenden
  Spieler derselben Rolle im selben Spiel automatisch mit. Der Partner bekommt als
  Todesort fest „Vor Kummer gestorben", bleibt aber wie jeder andere Tod zunächst
  unaufgedeckt (nur Nekromant/Selbstauskunft macht ihn sichtbar) — keine
  Sonderbehandlung gegenüber normalen Toden. „Das Paar" ist ab sofort entsprechend
  konfiguriert.

### DB-Änderungen
- `roles.linked_death TINYINT(1) NOT NULL DEFAULT 0` (in `db/schema.sql` +
  idempotent in `db/init.sql`; bereits live per `ALTER TABLE` nachgezogen)

---

## [v0.0.12] — 2026-07-04

### Hinzugefügt
- **Neues Debug-Menü** (`admin/debug.php`, nur im Debug-Modus nutzbar): 🔮 Tote
  wiederbeleben (Spieler ohne neue Runde zurückholen, Todeslisten-Eintrag wird
  gelöscht) + die bisherige „Eigene Rolle wählen"-Funktion, aus dem Haupt-
  Dashboard hierher verschoben. Neue API-Aktion `revive_player` (`api/admin.php`),
  gegen `APP_DEBUG` abgesichert wie `set_own_role`.
- Testspieler-Verwaltung (`admin/testplayers.php`) ist jetzt ebenfalls an den
  Debug-Modus gebunden (Seite + alle AJAX-Aktionen) und nur noch in der
  Kachel-Liste sichtbar, wenn Debug an ist.

### Geändert
- Kachel-Reihenfolge im Verwaltungsbereich (`admin/index.php`) neu sortiert nach
  Gebrauchshäufigkeit im laufenden Spiel: Nachrichten, Rollen, Spieler,
  Dorf-Sprüche, Einstellungen, Systemcheck, Setup, Debug-Menü, Testspieler.

---

## [v0.0.11] — 2026-07-04

### Behoben
- **Spielerfragen: "FAQ freigeben"-Button fehlte nach dem Antworten** bis zum
  nächsten Reload — `reply`/`toggle_publish`/`set_faq_question` geben jetzt die
  fertig gerenderte Zeile zurück, der Client ersetzt sie komplett statt einzelne
  DOM-Stellen manuell zu patchen.

### Hinzugefügt
- **FAQ-Übernahme anonymisierbar**: neuer Button „✏️ FAQ-Text" bei beantworteten
  Fragen — Admin kann vor der Veröffentlichung einen eigenen, anonymisierten
  Text hinterlegen (`messages.faq_question`), ohne die Original-Nachricht des
  Spielers zu verändern.
- Auffälliger Datenschutz-Hinweis im Fragenformular + in der Spieler-Anleitung:
  keine Namen/persönlichen Angaben in Fragen, da sie anonym in die FAQ
  übernommen werden können.

### DB-Änderungen
- `messages.faq_question TEXT NULL` (in `db/schema.sql` + idempotent in `db/init.sql`)

---

## [v0.0.10] — 2026-07-04

### Geändert
- **Root-Verzeichnis aufgeräumt**: Nur noch `index.php` liegt im Wurzelverzeichnis.
  Alle anderen Seiten (`game.php`, `deaths.php`, `roles.php`, `stats.php`, `faq.php`,
  `logout.php`, `register.php`, `datenschutz.php`, `impressum.php`,
  `nutzungsbedingungen.php`, `dump_roles_temp.php`) liegen jetzt unter `app/`.
  `sw.js` liegt jetzt unter `assets/js/sw.js` (Scope explizit auf `/` gesetzt +
  `Service-Worker-Allowed`-Header in `.htaccess`, sonst wäre Push site-weit
  ausgefallen). Alte Root-URLs leiten per 301 automatisch auf `/app/…` um —
  bestehende Bookmarks/PWA-Icons/Links bleiben nutzbar.

---

## [v0.0.9] — 2026-07-04

### Behoben
- **Kritischer Bug: Admin-Einstellungen reagierten nicht auf Klicks** — Debug-/
  Beta-Schalter speicherten nicht, Geräte ließen sich nicht löschen, Test-Push
  reagierte nicht. Ursache: `admin/settings.php` deklarierte `const SETTINGS_API`,
  derselbe Name wie in `templates/nav.php` (dort global für die Spieler-
  Einstellungen, auf jeder Seite geladen). Die doppelte `const`-Deklaration löste
  im Browser einen SyntaxError aus, der das komplette zweite Skript stumm
  deaktivierte — jeder Klick lief ins Leere, ohne dass der Server je etwas davon
  mitbekam. Konstante in `admin/settings.php` zu `ADMIN_SETTINGS_API` umbenannt.
- Auto-Save der Admin-Einstellungen stürzte bei jeder automatischen Speicherung
  still ab (`e.preventDefault()` auf `null` aufgerufen) — behoben, außerdem zeigt
  eine fehlgeschlagene Speicherung jetzt die echte Server-Antwort statt nur
  „Netzwerkfehler"

### Hinzugefügt
- **Admin-Einstellungen komplett neu gebaut**: jeder Bereich (Allgemein/Spiel/
  Push/Design/System/Texte) ist jetzt ein eigenes, unabhängiges Formular mit
  eigenem Speichern-Button — ein ungültiger Wert in einem Bereich blockiert
  nicht mehr das Speichern der anderen; Bereiche als Akkordeon (starten
  zugeklappt, sparen Platz v. a. am Handy); Sprungmarken-Leiste oben öffnet
  den Zielbereich automatisch mit; „↑ Nach oben"-Link am Ende jedes Bereichs
- **Test-Benachrichtigung** in den Push-Einstellungen: sofortiger Test-Push an
  das eigene Gerät oder an alle registrierten Geräte, ohne Cooldown

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
