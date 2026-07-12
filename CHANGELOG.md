# Changelog — Werwolf Web-App

Jedes Backup erhält eine fortlaufende Versionsnummer (v0.x — bis v0.0.25
lautete das Schema v0.0.x, ab v0.26 verkürzt auf Wunsch des Betreibers).

---

## [v0.40] — 2026-07-12

### Geändert
- **💔 „Das Paar" stirbt nicht mehr automatisch mit:** Das Rollen-Flag
  `linked_death` löst bei Tod eines Rollenpartners keinen Automatik-Tod
  („Vor Kummer gestorben") mehr aus. Stattdessen bekommen die verbleibenden
  Rollenpartner eine **neutrale Push-Benachrichtigung** (`🔔 Neuigkeit im
  Spiel — Öffne das Spielfenster für Details.` — bewusst ohne Namen/Rollen-
  bezug, da auch auf einem gesperrten Bildschirm sichtbar) und im Spielfenster
  einen Hinweis-Banner über dem „☠️ Meinen Tod melden"-Button. Sie können
  sich danach wie jeder andere Spieler **selbst** und **jederzeit** als tot
  melden (bestehende `self_report_death`-Funktion) — kein erzwungener
  Zeitpunkt, keine erzwungene Todesursache mehr.
  Betroffen: `core/helpers.php` (`recordDeath()`), `templates/game_blocks.php`
  (`render_my_status_actions()`), Rollentext „Das Paar", Admin-Formular-Text
  und Doku (`templates/role_form_fields.php`, `templates/role_card.php`,
  `docs/admin.php`, `docs/spieler.php`, `README.md`). Das Flag selbst bleibt
  unverändert in der DB (kein Rename, keine Schema-Änderung) — nur seine
  Bedeutung und Beschriftung („Partner-Benachrichtigung" statt „Gemeinsamer
  Tod") ändern sich. Design-Doku: `docs/superpowers/specs/2026-07-12-paar-partner-benachrichtigung-design.md`.

### DB-Änderungen
- Keine Schema-Änderung. `roles.rules` für „Das Paar" inhaltlich angepasst,
  `app_version` → `0.40` (beide Settings/Daten, kein `ALTER TABLE`).

---

## [v0.39] — 2026-07-12

### Geändert
- **Rollen-Seed in `db/schema.sql`/`db/init.sql` auf den aktuellen Live-Stand
  aktualisiert** (Snapshot der `roles`-Tabelle vom Testserver): Texte von
  Mörder, Detektiv, Das Paar, Dodo und Celebrity wurden über
  `admin/roles.php` angepasst — diese Änderungen stecken jetzt auch in den
  Seed-Dateien für Neuinstallationen. Spalten-Liste des Seed-INSERTs um
  `killer_sichtbar`, `linked_death`, `rollensicht`, `kill_hinweis` ergänzt
  (vorher teils über separate `UPDATE`-Nachträge gesetzt). **Sheriff ist im
  Seed jetzt standardmäßig deaktiviert** (`active=0`), weil das auf dem
  Testserver aktuell so eingestellt ist.
- `db/neue_rollen_vorlagen.sql` bereits deckungsgleich, keine Änderung nötig.

### DB-Änderungen
- Keine Schema-Änderung. Nur `app_version` → `0.39` (Setting).

---

## [v0.38] — 2026-07-12

### Entfernt
- **🎙️ Sprachnachrichten-System komplett entfernt** (auf Wunsch des Betreibers):
  Aufnahme/Wiedergabe von Sprachnachrichten bei Spielerfragen und Feedback,
  Sprachantworten des Spielleiters, automatische Transkription über die
  OpenAI-API samt API-Key-Verwaltung, das Aufräumen verwaister Aufnahmen.
  Betroffen: `app/game.php` (Frage-Modal + Posteingang), `app/feedback.php`,
  `admin/messages.php`, `templates/messages_blocks.php`, `api/messages.php`,
  `api/feedback.php`, `api/admin.php` (Spielstart-Aufräumlogik),
  `admin/settings.php` (Einstellungsbereich „Sprachnachrichten" entfernt),
  `core/bootstrap.php`, `core/helpers.php`, `docs/spieler.php`, `docs/admin.php`.
  Die externe Feedback-API liefert kein `has_voice`/`transcript`-Feld mehr aus.

### DB-Änderungen
- Spalten `messages.voice_path` und `messages.reply_voice_path` entfernt.
- Settings-Zeilen `voice_messages_enabled`, `voice_transcription_enabled`,
  `openai_api_key` entfernt.
- `app_version` → `0.38`.

---

## [v0.37] — 2026-07-09

### Hinzugefügt
- **📣 Promo-Seite „Was ist {App-Name}?"** (`app/werbung/index.php`, öffentlich, kein
  Login nötig): eigenständige, animierte Kino-Trailer-Seite — 5 Szenen laufen
  automatisch durch (Wortmarke, Rollen, Live-Updates, Themes, Mitmach-CTA), mit
  Play/Pause, Kapitel-Punkten und Fortschrittsbalken wie ein Videoplayer. Bewusst
  ohne `templates/base.php` (eigene volle-Viewporthöhe-Optik, keine normale
  Nav/Tab-Bar). Nutzt echte In-App-Effekte nach: Button-Ripple, Phasen-Überblendung
  („Die Nacht bricht herein …" / „Der Morgen graut …"), Mondlicht/Nebel/Glühwürmchen.
  Wortmarke und Seitentitel sind dynamisch über `APP_NAME` (nicht hartcodiert
  „Werwolf" — funktioniert also auch nach einer Umbenennung im Admin-Bereich).
  Der Abschluss-Button verlinkt direkt zur Login-Seite.
- **Login-Seite** (`index.php`): neuer dezenter Link „Was ist {App-Name}?" unter dem
  Logo, führt zur neuen Promo-Seite.

### DB-Änderungen
- Nur `app_version` → `0.37` (Setting). Keine Schema-Änderungen.

---

## [v0.36] — 2026-07-09

### Hinzugefügt
- **🎫 Feedback als Mini-Ticketsystem:** Die Feedback-Seite (`app/feedback.php`) zeigt bei
  jedem eigenen Eintrag jetzt zusätzlich, **ob der Spielleiter ihn schon gesehen hat**
  (👁 Gelesen / 🕐 Noch ungelesen — nur solange der Status „Offen" ist) und einen
  erweiterten Bearbeitungsstatus: 🔴 Offen → **👍 Angenommen** → 🟡 In Arbeit →
  🟢 Erledigt, alternativ **🚫 Abgelehnt**. Öffnet der Admin die Nachrichten-Verwaltung
  (`admin/messages.php`), gelten alle Einträge automatisch als gelesen — auch beim
  Live-Nachladen neuer Einträge während die Seite offen ist.
- **🎙️ Feedback per Sprachnachricht:** Wie bei Spielerfragen kann jetzt auch ein
  Feedback-Eintrag eingesprochen werden (Umschalter Text/Sprache, max. 1 Minute).
  Neue API-Aktion `send_feedback_voice`. In der Admin-Verwaltung steht bei
  Sprach-Feedback ebenfalls der Button „🎙️→📝 Transkribieren" zur Verfügung; das
  Transkript erscheint direkt unter dem Audio-Player und wird über die externe
  Feedback-API als Feld `transcript` mit ausgeliefert (macht Sprach-Feedback für
  externe Auswertung nutzbar, ohne die Audiodatei selbst preiszugeben).

### DB-Änderungen
- Neue Spalte **`messages.read_by_admin`**:
  ```sql
  ALTER TABLE messages ADD COLUMN IF NOT EXISTS read_by_admin TINYINT(1) NOT NULL DEFAULT 0 AFTER read_by_player;
  ```
- `app_version` → `0.36`.

---

## [v0.35] — 2026-07-09

### Geändert
- **🏳️ Rollen-Flags als waagerechte Tabs** (`templates/role_form_fields.php`): Die bisher
  10 Checkboxen im Raster + ein langer Sammel-Erklärtext darunter sind jetzt **Tabs** —
  ein Tab pro Flag. Tab antippen öffnet ein Panel mit Schalter und der **Erklärung direkt
  beim jeweiligen Flag** (nochmal antippen klappt zu, pro Formular ist max. ein Panel offen).
  Gesetzte Flags zeigen dauerhaft ein **✓ im Tab**, der Zustand bleibt also ohne
  Durchklicken sichtbar. Neue Flags brauchen nur noch einen Eintrag im `$roleFlags`-Array
  (Tab, Panel und ✓ entstehen automatisch). Feld-IDs unverändert — `collectFormData()`
  und die Rollen-Flag-Checkliste (CLAUDE.md) gelten weiter.
- **📣 Feedback-Link in der Fußzeile**: neben Impressum/Datenschutz/Nutzungsbedingungen
  (nur für eingeloggte Nutzer sichtbar) — die Feedback-Seite ist damit von jeder Seite
  aus erreichbar, nicht nur über das Optionen-Sheet.

### DB-Änderungen
- Nur `app_version` → `0.35` (Setting). Keine Schema-Änderungen.

---

## [v0.34] — 2026-07-09

### Hinzugefügt
- **📣 Feedback-System:** Spieler können Bugs melden, Wünsche äußern und Feedback geben —
  neue Seite `app/feedback.php` (erreichbar über ⚙️ Optionen → 📣 Mithelfen sowie einen
  Link im „Frage stellen"-Fenster). Aufsatz auf das bestehende Nachrichtensystem
  (messages-Tabelle, neue Spalten `type` + `status`), kein separates Modul.
  - **Typ-Auswahl** beim Eintragen: 🐛 Bug / 💡 Wunsch / 💬 Feedback (max. 1000 Zeichen).
  - **Bearbeitungsstatus** pro Eintrag: 🔴 Offen → 🟡 In Arbeit → 🟢 Erledigt — der Admin
    stellt ihn in der Nachrichten-Verwaltung um, der Spieler sieht ihn live auf der
    Feedback-Seite („Deine Einträge", Live-Update über `liveBlocks()`).
  - **Admin:** `admin/messages.php` heißt jetzt „Spielerfragen & Feedback" — mit
    Typ-Filter-Buttons (Alle / Fragen / Bugs / Wünsche / Feedback), Status-Dropdown je
    Eintrag und eigenem Badge „X offenes Feedback". Antworten (Text/Sprache) funktionieren
    wie bei Fragen; Feedback ist von der FAQ-Veröffentlichung ausgeschlossen.
  - „Unbeantwortet"-Zähler (Admin-Hinweis im Spielfenster) zählt jetzt nur noch echte
    Spielerfragen — offenes Feedback läuft über den eigenen Status-Badge.
- **🔌 Feedback-API** (`api/feedback.php`): token-gesicherte HTTPS-Schnittstelle für
  externe Clients (z.B. KI-Assistent beim Entwickeln — auch von einem anderen Server aus):
  - `list` (Einträge als JSON, Filter: type/status/since_id/limit) und `set_status`.
  - Auth per `Authorization: Bearer <Token>`; Token-Verwaltung im neuen Panel
    „Feedback-API" unten in der Nachrichten-Verwaltung (generieren/entfernen, Anzeige
    nur einmalig direkt nach dem Generieren). Leeres Token = API komplett deaktiviert.
  - Sicherheit: `hash_equals`-Vergleich, Rate-Limit pro IP (60/min, Fehlversuche 10/min),
    Logging ungültiger Zugriffe, **nie** Spielerfragen oder Audiodateien in der Ausgabe.

### DB-Änderungen
- Neue Spalten **`messages.type`** (question/bug/wish/feedback) und **`messages.status`**
  (open/in_progress/done):
  ```sql
  ALTER TABLE messages ADD COLUMN IF NOT EXISTS type   VARCHAR(16) NOT NULL DEFAULT 'question' AFTER player_id;
  ALTER TABLE messages ADD COLUMN IF NOT EXISTS status VARCHAR(16) NOT NULL DEFAULT 'open' AFTER type;
  ```
- Neuer Settings-Eintrag **`feedback_api_token`** (leer = API deaktiviert):
  ```sql
  INSERT IGNORE INTO settings (`key`, value, type, label, description, sort_order) VALUES
  ('feedback_api_token', '', 'string', 'Feedback-API-Token', 'Zugriffs-Token für die externe Feedback-API (leer = API deaktiviert). Verwaltung über Admin → Spielerfragen & Feedback.', 998);
  ```
- `app_version` → `0.34`.

---

## [v0.33] — 2026-07-08

### Hinzugefügt
- **🎙️ Sprachantworten des Spielleiters:** Der Admin kann Spielerfragen jetzt auch
  **per Sprachnachricht** beantworten — Aufnahme direkt im Antwort-Bereich der
  Nachrichten-Verwaltung (MediaRecorder, max. 1 Min., Vorschau vor dem Senden). Ist die
  **Transkription aktiviert**, wird die Sprachantwort automatisch in Text umgewandelt und
  als Antworttext gespeichert (per OpenAI, wie bei den Spielerfragen). Der Spieler hört
  die Antwort im Posteingang (Audio-Player) inkl. Transkript-Text.
  - Neue API-Aktion `reply_voice` (Admin-only, Upload + optional Transkription + Push).
  - `voice_file` um `which=reply` erweitert — auth-geschützte Auslieferung (Admin oder der
    Empfänger-Spieler), der `uploads/`-Ordner bleibt HTTP-gesperrt.
  - Die Aufräumfunktion (`cleanupOrphanedVoiceFiles`) berücksichtigt jetzt auch
    `reply_voice_path`, damit Sprachantworten nie fälschlich als verwaist gelöscht werden.

### DB-Änderungen
- Neue Spalte **`messages.reply_voice_path`** (Pfad der Admin-Sprachantwort):
  ```sql
  ALTER TABLE messages ADD COLUMN IF NOT EXISTS reply_voice_path VARCHAR(255) NULL AFTER reply;
  ```
- `app_version` → `0.33`.

---

## [v0.32] — 2026-07-08

### Sicherheit
- **HTTPS verpflichtend** — HTTP ist für die ganze App tot:
  - **HSTS** (`.htaccess`): Browser sprechen die Domain 1 Jahr nur noch über HTTPS an.
  - **App-Ebene** (`bootstrap.php`): Nicht-HTTPS-Zugriffe (außer CLI + localhost) werden mit
    einer eigenständigen „🔒 HTTPS erforderlich"-Hinweisseite blockiert — greift auch, wenn
    die App auf einem fremden Nur-HTTP-Server läuft. Reverse-Proxy-tauglich (`X-Forwarded-Proto`).
  - (Der Port-80→443-Redirect bestand bereits.)

### Fehler-Logging (im Admin-Bereich abrufbar)
- **Globaler Exception-Handler**: jede nicht abgefangene Ausnahme (z.B. DB-Fehler in
  irgendeiner Funktion) landet automatisch als `[ERROR]` im System-Log und der Nutzer bekommt
  eine saubere Meldung — ohne dass jede Funktion ein eigenes try/catch braucht.
- **DB-Verbindungsfehler** werden ebenfalls ins Log geschrieben.
- Zusammen mit dem bestehenden Fatal-Handler landen so praktisch alle unerwarteten Fehler im
  **Admin → Debug → System-Log**.

### Wartung
- **Aufräumfunktion für verwaiste Sprachaufnahmen** (`cleanupOrphanedVoiceFiles()`): löscht
  `.webm`-Dateien in `uploads/voice/`, die zu keiner Nachricht mehr gehören. Läuft automatisch
  beim Löschen einer Nachricht **und eines Spielers** (Kaskaden-Orphans) und ist als
  manueller Button „🧹 Verwaiste Aufnahmen aufräumen" in der Nachrichten-Verwaltung verfügbar.
  So können dauerhaft keine verwaisten Aufnahmen zurückbleiben.

### Infrastruktur (nur Server, nicht im Repo)
- **DNS-Fix** in `docker-compose.yml` (`dns: [1.1.1.1, 8.8.8.8]` beim Web-Service): nach dem
  Subnetz-Umzug zeigte der DNS noch auf den toten `192.168.178.1` — der Container erreichte
  kein Internet. Dadurch liefen **OpenAI-Transkription und Web-Push** ins Leere; jetzt behoben
  und die Sprachnachricht-Transkription end-to-end verifiziert.

### DB-Änderungen
- Nur `app_version` → `0.32` (Setting). Keine Schema-Änderungen.

---

## [v0.31] — 2026-07-08

### Hinzugefügt
- **⬆️ Update-Center** (`admin/update.php`, Admin-only): signierte Update-Pakete
  (`.wwupd`) per Web-Upload einspielen — **0 manueller Server-Zugriff nötig**. Vor dem
  Anwenden zeigt es Zielversion, Release-Notes und DB-/Neuaufsetzen-Hinweise; auf
  Bestätigung werden Dateien automatisch ersetzt (mit Backup) und additive DB-Migrationen
  angewendet, danach `app_version` gesetzt.
- **Sicherheit des Update-Systems** (`core/Updater.php`):
  - **Ed25519-Signatur** (libsodium): nur mit dem privaten Entwickler-Schlüssel signierte
    Pakete werden akzeptiert (Public Key in `config/update_pubkey.php`, Private Key nur lokal).
  - **Manipulationserkennung**: SHA-256 jeder Datei gegen das signierte Manifest — verändertes
    Paket, untergeschobene Datei oder gefälschte Signatur werden erkannt und abgelehnt.
  - **Versionsprüfung**: nur passende/neuere Pakete (kein Einspielen über Kreuz) — dadurch
    greift das System praktisch erst ab v1.0.
  - **Pfad-sicheres Kopieren** (kein Traversal, nur unter Webroot), **Backup vor Überschreiben**
    mit Rollback bei Fehler, destruktive Migrationen (DROP/TRUNCATE) verboten.
  - `updates/`-Ordner per `.htaccess` HTTP-gesperrt.
  - 10/10 Sicherheitstests bestanden (gültig / manipuliert / gefälscht / Traversal / Version / Anwenden).
- **Paket-Werkzeuge** (lokal, nicht auf dem Server): `tools/build_update.php` (baut & signiert
  `.wwupd`-Pakete), `tools/gen_update_keys.php` (Schlüsselpaar erzeugen).

### DB-Änderungen
- Nur `app_version` → `0.31` (Setting). Keine Schema-Änderungen.

---

## [v0.30] — 2026-07-08

### Geändert
- **🐛 Debug-Menü als Accordion** (wie die Server-Einstellungen aufgebaut): jeder
  Block klappt einzeln auf, immer nur einer offen. **📜 System-Log steht jetzt ganz
  oben und startet aufgeklappt** (mit Fehler-Badge ⛔/❌ im Kopf), alle übrigen Blöcke
  — inklusive **Testspieler** — sind standardmäßig eingeklappt. Die von Render-
  Funktionen erzeugten inneren Karten werden per CSS abgeflacht (keine Änderung an
  den geteilten Templates nötig).

### Sicherheit
- **`admin/setup.php` gehärtet** (der destruktive Setup-Assistent ist login-los erreichbar):
  - Leeres `SETUP_PASSWORD` = **gesperrt** (bisher: offen für alle).
  - Timing-sicherer Passwortvergleich (`hash_equals`).
  - **Brute-Force-Bremse** (dateibasiert, funktioniert auch ohne DB): nach 5 Fehlversuchen 5 Min. Sperre.
  - **Serverseitige Bestätigungsphrase** (`action=confirm`) vor dem destruktiven Lauf —
    `?action=run` war bisher per direktem GET ohne Bestätigung auslösbar.
  - Session-Regenerierung nach erfolgreichem Login (gegen Session-Fixation).

### Geplant (nur dokumentiert, nicht umgesetzt)
- **Update-System** (ZIP-basiert, Web-Upload, digitale Signatur zur Manipulations-
  erkennung, Versionsprüfung, automatisches Datei-Kopieren + DB-Migration): Konzept in
  `info.txt` festgehalten — Umsetzung **erst nach Release 1.0**, nicht in der Beta.

### DB-Änderungen
- Nur `app_version` → `0.30` (Setting). Keine Schema-Änderungen.

---

## [v0.29] — 2026-07-08

### Hinzugefügt
- **📜 System-Log im Debug-Menü** (`admin/logs.php`, nur bei `app_debug`): zeigt
  aufgezeichnete Server-Fehler & Ereignisse, klassifiziert nach Schweregrad
  (⛔ Kritisch, ❌ Fehler, ⚠️ Warnung, ℹ️ Hinweis, 💬 Info) — mit Filter-Chips
  je Stufe, Sortierung „Neueste zuerst"/„Nach Schweregrad" und Leeren-Funktion
  (protokolliert sich selbst). Einstieg über eine neue Karte im Debug-Menü mit
  Kurzübersicht der kritischen/Fehler-Einträge.
- **Log-Infrastruktur:** Alle `error_log()`-Aufrufe laufen jetzt in `logs/app.log`
  (vorher: verschwanden in Docker-`stderr`), gesetzt per `ini_set` in
  `bootstrap.php`; bestehende Aufrufe auf klassifizierte `logEvent(LEVEL, …)`
  umgestellt. **Fatale Fehler** werden per `register_shutdown_function`
  automatisch als CRITICAL erfasst. Der `logs/`-Ordner ist per `.htaccess` gegen
  HTTP-Zugriff gesperrt (Root-Regel + eigene `logs/.htaccess`).

### Sicherheit (Code-Review, Punkte 4–8)
- **DB-Fehler-Leak behoben** (`api/game.php`, `update_death_info`): die rohe
  PDO-Fehlermeldung ging bisher unabhängig von `APP_DEBUG` an den Client — jetzt
  nur im Debug-Modus, sonst generische Meldung; Detail landet via `logEvent` im Log.
- **CSRF-Härtung** (Defense-in-Depth zusätzlich zu SameSite=Lax): neue Funktion
  `requireSameOrigin()` in `core/helpers.php`, eingebunden in alle
  zustandsändernden Endpunkte (`api/game.php`, `api/admin.php`, `api/push.php`,
  `api/messages.php`, die drei Upload-Endpunkte, `admin/players.php`,
  `admin/testplayers.php`, `admin/settings.php`). Greift nur bei Nicht-GET und
  blockt nur nachweislich fremde Herkunft (403), lässt Anfragen ohne
  Origin/Referer durch.
- **Logik-Härtung:** `add_player` (`api/admin.php`) prüft jetzt Lobby-Status +
  Spielerexistenz (analog `add_all_players`); `vote` (`api/game.php`) validiert,
  dass das Ziel ein lebender Mitspieler dieses Spiels ist.
- **Auth-Fail-open sichtbar gemacht:** `Auth::validateInDb()` bleibt bewusst
  fail-open (kurzer DB-Aussetzer sperrt niemanden aus), loggt den Fall aber jetzt
  als WARNING statt still.
- Hinweis: Das schwache `SETUP_PASSWORD` (#1) bleibt in der Beta absichtlich,
  wird vor dem Livegang gehärtet.

### DB-Änderungen
- Nur `app_version` → `0.29` (Setting; siehe unten). Keine Schema-Änderungen.

---

## [v0.28] — 2026-07-07

### Geändert
- **🐛 Debug-Menü konsolidiert:** Drei bisher verstreute Werkzeuge sind jetzt an
  einem Ort (`admin/debug.php`, nur bei `app_debug` sichtbar):
  - **„Spieler als tot melden"** aus dem Admin-Dashboard hierher verschoben
    (Dropdown-Auswahl + Todesursache) — der per-Zeile „☠"-Schnellknopf in der
    normalen Spielerliste bleibt unverändert für den echten Spielbetrieb erhalten.
  - **Todesursache-Dropdown** auf zwei Optionen reduziert: 🔪 Mordwaffe und
    ⚖️ Erhängt — „💀 Sonstiges" entfernt (war serverseitig ohnehin identisch zu
    „Mordwaffe" behandelt, reine UI-Vereinfachung auf Wunsch).
  - **Testspieler-Verwaltung** aus der eigenständigen Seite `admin/testplayers.php`
    hierher verschoben (neue gemeinsame Render-Funktion `admin_render_testplayers()`
    in `templates/testplayers_blocks.php`); die alte Seite ist jetzt ein reiner
    AJAX-Endpunkt (create/delete/delete_all) und leitet bei direktem Aufruf ohne
    Aktion auf das Debug-Menü um. Bewusst **nicht** an die Spielstatus-Prüfung
    gekoppelt — Testspieler müssen oft schon in der Lobby anlegbar sein.
  - **Neu:** Button „⏱️ Cooldown zurücksetzen" — setzt den Cooldown der eigenen
    aktuellen Rolle sofort zurück (`game_players.cooldown_started_at = NULL`),
    admin-only, neue API-Aktion `debug_reset_cooldown`.
  - Admin-Dashboard-Kachel „Debug-Menü" entsprechend im Untertitel aktualisiert;
    eigene Testspieler-Kachel entfernt.
  - Beim Live-Test eine vergessene Altlast gefunden: die `liveBlocks()`-Zielliste
    im Admin-Dashboard referenzierte noch `kill-quick-card` nach dessen Entfernung
    — behoben.
- **📖 Anleitung-Kachel im Admin-Dashboard ergänzt:** Direkter Link zum
  Admin-Handbuch (`docs/admin.php`) fehlte bisher komplett im Verwaltungsbereich.

### DB-Änderungen
- Keine (nur Code-Änderungen, `cooldown_started_at`-Spalte existiert bereits seit
  der ursprünglichen Cooldown-Funktion).

---

## [v0.27] — 2026-07-07

### Hinzugefügt
- **🎙️ Sprachnachrichten an den Spielleiter:** Im „Frage stellen"-Fenster können
  Spieler auf Sprachnachricht umschalten und ihre Frage einsprechen — max.
  1 Minute (Auto-Stopp mit Countdown), Vorschau vor dem Senden, Neu-Aufnehmen
  möglich. Aufnahme per MediaRecorder (Chrome/Firefox: WebM/Opus, Safari/iPhone:
  MP4/AAC — Format wird automatisch gewählt); ohne MediaRecorder-Unterstützung
  bleibt nur der Text-Tab sichtbar. In der Admin-Nachrichtenverwaltung erscheint
  ein Audio-Player statt des Fragetexts (mit „🎙️"-Badge), geantwortet wird wie
  gewohnt per Text; der Spieler kann seine eigene Aufnahme im Posteingang anhören.
- **Datenschutz:** Aufnahmen liegen unter `uploads/voice/` (per .htaccess
  gesperrt, gitignored) und werden ausschließlich über
  `api/messages.php?action=voice_file` mit Auth ausgeliefert — nur Admin und
  der Absender selbst. Die Audiodatei selbst wird nie veröffentlicht — der
  Spielleiter kann aber wie bei Text-Fragen über „✏️ FAQ-Text" eine
  anonymisierte Textfassung hinterlegen und diese in die öffentliche FAQ
  übernehmen (`toggle_publish` verlangt bei Sprachnachrichten zwingend eine
  gesetzte `faq_question`, sonst würde nur der Platzhaltertext veröffentlicht).
  Hinweistexte im Frage-Modal (Text + Sprache) weisen entsprechend einheitlich
  darauf hin, keine identitätsverratenden Angaben zu machen. Beim Löschen
  einer Nachricht wird die Audio-Datei mit entfernt.
- **Robustheit:** Server prüft den echten Dateiinhalt (finfo-MIME-Allowlist,
  max. 3 MB); fehlende oder beschädigte Aufnahmen stürzen nichts ab — Player
  zeigt stattdessen einen Hinweis (Existenz-Check + onerror-Fallback).
- **Neue Einstellung „Sprachnachrichten"** (eigener Bereich in Admin →
  Einstellungen, Standard: an): schaltet den Sprach-Tab für alle Spieler
  ein/aus. Neuer Settings-Key `voice_messages_enabled`, Konstante
  `VOICE_MESSAGES` (bootstrap).
- **🎙️→📝 Automatische Transkription (OpenAI):** eigener Schalter „Sprachnachrichten-
  Transkription" (Standard: aus, Settings-Key `voice_transcription_enabled`,
  Konstante `VOICE_TRANSCRIPTION`) plus maskiertes API-Key-Feld (Settings-Key
  `openai_api_key`, nie im Klartext angezeigt, leer lassen beim Speichern = Key
  unverändert, eigener „🗑 Entfernen"-Button). Ist beides gesetzt, erscheint in der
  Nachrichten-Verwaltung bei jeder Sprachnachricht der Button „🎙️→📝
  Transkribieren" — schickt die Aufnahme an `gpt-4o-mini-transcribe`
  (api/messages.php → `transcribe_voice`, admin-only) und trägt das Ergebnis
  direkt ins FAQ-Textfeld ein (öffnet sich automatisch zum Gegenlesen/
  Anonymisieren, bevor der Spielleiter veröffentlicht — die Aufnahme selbst wird
  dabei nie an Dritte als Audio weitergegeben, nur der transkribierte Text geht
  an OpenAI).
- **Nachrichten bei Spielstart löschen** (Einstellungen → Spiel, Standard: aus):
  Ist der Schalter an, räumt `start_game` (api/admin.php) beim Start eines neuen
  Spiels automatisch auf — alle Sprachnachrichten werden unbedingt gelöscht
  (Zeile + Datei, unabhängig vom FAQ-Status, da danach keine Aufnahme mehr nötig
  ist), alle Text-Fragen ohne FAQ-Veröffentlichung ebenfalls; bereits
  veröffentlichte Text-FAQ-Einträge bleiben stehen. Betrifft alle bisherigen
  Spiele, nicht nur das gerade beendete. Neuer Settings-Key
  `clear_messages_on_start`, Konstante `CLEAR_MESSAGES_ON_START`. Die Aufräum-Logik
  läuft bewusst erst NACH allen Validierungen (Spieleranzahl, Sonderrollen-Pool,
  Preset) und unmittelbar vor dem eigentlichen Statuswechsel auf „running" — ein
  Fund beim Live-Test: in einer früheren Fassung lief sie direkt nach dem
  Lobby-Check, wodurch ein wegen falscher Sonderrollen-Anzahl abgebrochener
  Spielstart trotzdem schon alle Nachrichten gelöscht hätte.
- **🐛 Debug-Menü: Spielkarte eines Spielers ansehen** (nur bei aktivem `app_debug`,
  nur während eines laufenden Spiels): neue Karte in `admin/debug.php` — Spieler aus
  Dropdown wählen, komplette Rollenkarte (Icon, Name, Beschreibung, Regeln, Cooldown,
  „sichtbar"-Badge) erscheint direkt darunter. Ignoriert bewusst alle normalen
  Sichtbarkeitsregeln (auch bei toten Spielern, deren Rolle sonst verborgen bleibt) —
  ausschließlich als Debug-Werkzeug für die Spielleitung gedacht. Neue admin-only
  API-Aktion `debug_peek_role` (api/admin.php), live getestet mit 12 Testspielern
  über alle vergebenen Rollen hinweg (inkl. Cooldown- und Sichtbarkeits-Anzeige).

### DB-Änderungen (live bereits ausgeführt + getestet)
- `ALTER TABLE messages ADD COLUMN voice_path VARCHAR(255) NULL AFTER faq_question`
- Settings-Zeilen `voice_messages_enabled` (bool, Default 1), `voice_transcription_enabled`
  (bool, Default 0), `openai_api_key` (string, Default '') und `clear_messages_on_start`
  (bool, Default 0) — vollständig live nachgezogen und per Testspiel end-to-end verifiziert
  (Text-Frage + FAQ-Veröffentlichung + Sprachnachricht angelegt, Spielstart ausgelöst:
  FAQ-Eintrag blieb erhalten, unveröffentlichte Text-Frage + Sprachnachricht samt Datei
  wurden korrekt gelöscht). Referenz-SQL für andere Umgebungen:
  ```sql
  INSERT IGNORE INTO settings (`key`, value, type, label, description, sort_order) VALUES
  ('voice_transcription_enabled', '0', 'bool', 'Sprachnachrichten-Transkription', 'Erlaubt dem Spielleiter, Sprachnachrichten per OpenAI-API automatisch in Text umzuwandeln (Grundlage für die FAQ-Übernahme).', 28),
  ('openai_api_key', '', 'string', 'OpenAI API-Key', 'Wird nur für die Sprachnachrichten-Transkription verwendet. Wert wird in der Oberfläche nie im Klartext angezeigt.', 29),
  ('clear_messages_on_start', '0', 'bool', 'Nachrichten bei Spielstart löschen', 'Beim Start eines neuen Spiels: alle Sprachnachrichten (immer) sowie alle Text-Fragen ohne FAQ-Veröffentlichung werden gelöscht.', 22);
  ```

---

## [v0.26] — 2026-07-07

Versionsschema von 0.0.xx auf 0.xx umgestellt (0.0.25 → 0.26).
Abarbeitung der drei Rest-Beobachtungen aus dem Code-Review v0.0.18/19.

### Behoben
- **Manuelles „⚖️ Erhängt" (kill_player, cause=vote) respektiert jetzt die
  Versammlungsregeln:** gleicher GET_LOCK wie `execute_vote` (keine parallele
  Doppel-Hinrichtung) und die Sperre „max. 1 Hinrichtung pro Versammlung".
  Die Mindeststimmen-Prüfung entfällt auf diesem Pfad bewusst — er ist für
  Hinrichtungen gedacht, die außerhalb der App-Abstimmung entschieden wurden.
- **Rollen-Icons im Spielfeld cachen jetzt korrekt nach Datei-Ersatz:**
  `get_players` liefert die fertige Icon-URL mit filemtime-Cache-Buster
  (`role_icon_url` via `assetUrl()`); das JS setzt keine URLs mehr aus dem
  nur bei Uploads gebumpten `ASSET_VER`-Setting zusammen.

### Entfernt
- **Einheiten-Altlast beim Cooldown:** Die nie aufgerufenen Helfer
  `canUseAbility()`/`markAbilityUsed()` (rechneten in Runden, während
  `roles.cooldown` überall sonst Minuten bedeutet) sind gelöscht, ebenso die
  ungenutzte Spalte `game_players.last_ability_round` (Schema + init-Migration
  + Live-DB). Der echte Timer läuft unverändert über `cooldown_started_at`.

### DB-Änderungen (in frischem Schema enthalten, live bereits ausgeführt)
- `ALTER TABLE game_players DROP COLUMN last_ability_round`

---

## [v0.0.25] — 2026-07-07

### Hinzugefügt
- **Detektiv überarbeitet — passive Kill-Hinweise:** Neues Rollen-Flag
  `kill_hinweis` (per Checkbox für jede Rolle aktivierbar, Detektiv =
  Standard). Vollautomatisch, kein Button: Immer wenn im Spiel so viele
  Morde geschehen sind, wie es Killer gibt (Hinrichtungen zählen nicht),
  erfährt jeder lebende Spieler einer solchen Rolle einen zufälligen
  garantierten Nicht-Killer — als „✅ Kein Killer"-Badge in seiner
  Spielerliste (bewusst NICHT die volle Rolle), mit Toast im offenen
  Spielfenster und neutraler Push-Benachrichtigung (ohne Inhalt auf dem
  Sperrbildschirm). Zentrale Logik in `grantKillHints()` (core/helpers.php),
  aufgerufen aus `recordDeath()` — idempotent über Soll/Ist-Vergleich,
  funktioniert daher auch bei linked_death-Kaskaden und mehreren
  Detektiven. Nutzt die `role_insights`-Tabelle (source=`kill_hinweis`),
  zählt damit automatisch in der Statistik mit.
- `WebPush::sendToPlayer()` kann jetzt Titel/Text mitgeben (bisher nur
  generischer Fallback).
- Untersucht eine Rolle mit Rollensicht jemanden, den sie schon als
  „kein Killer" kennt, wird die Erkenntnis zur vollen Rollensicht
  aufgewertet (ON DUPLICATE KEY UPDATE), nie umgekehrt.
- Detektiv-Rollentexte auf die neue passive Mechanik umgestellt (Seeds +
  Live-DB); der alte Durchsuchen-Text ist in der lokalen
  Rollen-Referenz-PDF dokumentiert.

### DB-Änderungen (in frischem Schema enthalten, live bereits ausgeführt)
- `ALTER TABLE roles ADD COLUMN kill_hinweis TINYINT(1) NOT NULL DEFAULT 0`
- `UPDATE roles SET kill_hinweis=1 WHERE name='Detektiv'` + neue Texte

---

## [v0.0.24] — 2026-07-06

### Geändert
- **Rollensicht: Ziel-Auswahl wie beim Anklagen.** Statt eines eigenen
  Auswahl-Popups wählt die Hellseherin den untersuchten Spieler jetzt direkt
  im Dorfbewohner-Block aus (Spielerkarte antippen — dieselbe Bedienung wie
  bei der Anklage) und drückt dann „🔮 Spieler untersuchen". Ohne Auswahl
  erscheint ein Hinweis; vor dem Eintragen kommt eine Bestätigung mit dem
  Spielernamen. Hinweistext unter dem Button entsprechend angepasst.

---

## [v0.0.23] — 2026-07-06

### Hinzugefügt
- **Rollensicht — Hellseherin sieht untersuchte Rollen dauerhaft:** Neues
  Rollen-Flag `rollensicht` (per Checkbox in der Rollen-Verwaltung für jede
  Rolle aktivierbar). Der Fähigkeit-Button fragt bei diesen Rollen zuerst
  „Wen hast du untersucht?" (Auswahl der lebenden Mitspieler), speichert die
  Erkenntnis dauerhaft und zeigt die Rolle des Ziels sofort als Toast — ab
  dann bleibt sie für den Untersucher in der Spielerliste sichtbar
  (serverseitig entschieden, Regel e der Sichtbarkeitslogik). Danach startet
  der normale Cooldown. Hellseherin hat das Flag als Standard.
- **Neue Tabelle `role_insights`** — „Spieler A kennt die Rolle von Spieler B"
  (game_id, viewer, target, source): eine Zeile pro Erkenntnis, `source`
  nennt die Mechanik (vorbereitet für Detektiv/Sheriff), FK ON DELETE CASCADE,
  wird bei `reset_game` mit abgeräumt. Flag-Sichtbarkeiten (Mörder sehen sich)
  bleiben bewusst live berechnet — keine doppelte Wahrheit.
- **Statistik:** Neue Kennzahl „🔮 Untersuchungen" (gesamt) und im
  Spieler-Profil „🔮 Untersuchungen" / „👁️ Untersucht worden"; die
  Versions-Probe von `get_stats` berücksichtigt `role_insights`.
- Rollen-Flag-Checkliste komplett umgesetzt: Formular-Checkbox + Legende,
  `collectFormData`, `role_create`/`role_update`, Admin-Karten-Tag
  „🔮 Rollensicht", `dump_roles_temp.php`-Spaltenliste, README.

### DB-Änderungen (in frischem Schema enthalten, live bereits ausgeführt)
- `ALTER TABLE roles ADD COLUMN rollensicht TINYINT(1) NOT NULL DEFAULT 0`
- `CREATE TABLE role_insights` (siehe `db/init.sql`)
- `UPDATE roles SET rollensicht=1 WHERE name='Hellseherin'` + Texte mit
  Hinweis auf dauerhafte Sichtbarkeit

---

## [v0.0.22] — 2026-07-06

### Hinzugefügt
- **Auto-Timeout zur Rollenkarte:** Neue Spieler-Einstellung (Optionen →
  🔒 Privatsphäre, Standard: aus): Nach 1/2/5/10 Minuten ohne Eingabe (Touch,
  Klick, Taste, Scrollen) öffnet das Spielfenster automatisch die eigene
  Rollenkarte — Sichtschutz, wenn das Handy offen herumliegt. Jede Eingabe
  setzt den Timer zurück; Änderung der Einstellung greift sofort ohne Reload.
  Reiner Anzeige-Timer ohne Serverkontakt. Neuer Settings-Key
  `ww_rolecard_timeout` (Allowlist in `api/game.php`, geräteübergreifend
  gespeichert wie alle Spieler-Einstellungen). In der Spieler-Anleitung
  dokumentiert.

---

## [v0.0.21] — 2026-07-06

### Hinzugefügt
- **Cooldown-Platzhalter `{cooldown}` in Rollentexten:** In Beschreibung und
  Regeln einer Rolle kann `{cooldown}` geschrieben werden — bei der Anzeige
  (Rollenkarte im Spiel, Rollen-Galerie, FAQ-Rollenregeln, Admin-Rollenkarte,
  Spieler-Anleitung) wird er automatisch durch den aktuellen Cooldown-Wert der
  Rolle ersetzt. Ändert der Admin den Cooldown, bleiben die Texte ohne
  Handarbeit synchron. Zentrale Ersetzung in `roleText()` (`core/helpers.php`),
  Hinweis im Rollen-Formular ergänzt.
- Die Seed-Texte von Mörder und Hellseherin (fest „30 Minuten") nutzen jetzt
  den Platzhalter — in `db/schema.sql`, `db/init.sql` und auf der Live-DB.

---

## [v0.0.20] — 2026-07-06

### Hinzugefügt
- **Rollen-Presets** (`admin/roles.php`): Der aktuelle Zustand aller Rollen
  (aktiv/inaktiv, Anzahl, Auffüll-Rolle) lässt sich als benanntes Set speichern
  (z. B. „7 Spieler") und später mit einem Klick wieder anwenden — schneller
  Wechsel zwischen Rollenkonfigurationen ohne einzelnes Umhaken. Max. 20 Presets.
  Beim Speichern wählbar: neues Preset anlegen oder ein vorhandenes explizit
  überschreiben (Name bleibt dabei erhalten; Neuanlage mit vergebenem Namen wird
  abgelehnt). Beim Laden werden Rollen, die nach dem Speichern des Presets neu
  angelegt wurden, deaktiviert — das Preset beschreibt immer die komplette
  Konfiguration. Die Rollenliste aktualisiert sich nach dem Laden ohne Seitenreload.
- **Spielstart mit Preset** (Admin-Dashboard): Neben „▶ Spiel starten" gibt es
  in der Lobby eine Preset-Auswahl mit eigenem „▶ Mit Preset starten"-Button
  (nur sichtbar, wenn Presets existieren) — das gewählte Set wird unmittelbar
  vor der Rollenverteilung angewendet, die Erfolgsmeldung nennt das Preset.
- Neue Tabellen `role_presets` + `role_preset_items` (Snapshot von
  `active`/`amount`/`fill` pro Rolle, FK mit ON DELETE CASCADE — gelöschte
  Rollen verschwinden automatisch aus allen Presets).
- Neue API-Aktionen in `api/admin.php`: `preset_save`, `preset_apply`,
  `preset_delete`; `start_game` akzeptiert optional `preset_id`. Die
  Anwenden-Logik liegt zentral in `applyRolePresetOrFail()`.

### DB-Änderungen (in frischem Schema enthalten)
- `CREATE TABLE role_presets` + `CREATE TABLE role_preset_items`
  (siehe `db/init.sql` für bestehende Installationen)

---

## [v0.0.19] — 2026-07-06

Nachbesserung: Re-Review der v0.0.18-Fixes (sequenzieller 8-Winkel-Durchlauf,
13 Kandidaten, 13 Verifier) fand 7 bestätigte/plausible Punkte — alle behoben.

### Behoben
- **Versammlungs-Sperre war aushebelbar.** `hangedThisAssembly()` nahm als
  Prüffenster die zuletzt *terminierte* Versammlung — auch wenn sie vor ihrem
  Beginn wieder beendet wurde (Termin in der Zukunft). Per „Einberufen + sofort
  Beenden" ließ sich das Fenster nach vorn schieben und mit Rest-Stimmen ein
  zweites Mal henken. Jetzt zählen nur tatsächlich **gestartete** Versammlungen
  (`scheduled_at <= jetzt`) als Fenster-Referenz.
- **Spiel-Reset während laufender Versammlung sperrte das neue Spiel.**
  `reset_game` ließ `assembly_requests` stehen: die Alt-Versammlung lebte im
  neuen Spiel weiter (keine neue einberufbar) und ihr altes `scheduled_at`
  hätte nach der ersten Hinrichtung alle weiteren dauerhaft blockiert.
  `assembly_requests` wird beim Reset jetzt mit abgeräumt.
- **Icon-Caching erlosch nach der ersten Revalidierung.** Die
  `expr==200`-Bedingung ließ auf 304-Not-Modified-Antworten die No-Cache-Header
  der Root-.htaccess durch; Browser ersetzen bei 304 die gespeicherten Header
  des Cache-Eintrags (RFC 9111 §3.2) — nach dem ersten max-age-Ablauf wäre das
  Icon dauerhaft revalidiert worden (live per curl nachgewiesen). Die
  Cache-Header gelten jetzt für Status 200 **und** 304.
- **Cooldown: negativer Elapsed-Wert konnte den Timer überdehnen.** Bei einem
  Rücksprung der DB-Server-Uhr (NTP-Stepping) wäre `remaining_secs` größer als
  der Rollen-Cooldown geworden. Die Formel liegt jetzt zentral in
  `cooldownRemainingSecs()` (core/helpers.php) und clampt auf `[0, total]`.

### Geändert
- **`gamePlayer()` liefert `cooldown_elapsed` mit** (TIMESTAMPDIFF in der
  bestehenden Query) — die separate Zusatz-Query pro Spielfeld-Seitenaufbau
  entfällt, und Seite + API nutzen dieselbe Formel über den neuen Helper.
- **`hangedThisAssembly()` braucht nur noch eine Query** (Subquery statt zwei
  Round-Trips) — relevant, weil die Prüfung im Dashboard-Poll läuft.
- **Auto-Rollenkarte:** Der Funken-Bootstrap ruft jetzt `openRoleCard()` auf,
  statt dessen zwei Start-Zeilen zu duplizieren (`classList.add` auf offenem
  Overlay ist ein No-op).

---

## [v0.0.18] — 2026-07-06

Sammel-Release: alle 13 bestätigten Findings aus dem Code-Review v0.0.17 behoben.

### Behoben
- **Cooldown: Zeitzonen-Regression aus v0.0.15.** `start_cooldown` etikettierte den
  DB-`NOW()`-String mit `GAME_TIMEZONE`, obwohl der MariaDB-Container auf UTC läuft —
  der Timer startete dadurch 2 h in der Vergangenheit und war sofort abgelaufen.
  Grundsätzlicher Umbau: Server überträgt nur noch **verbleibende Sekunden**
  (`remaining_secs`, berechnet per `TIMESTAMPDIFF` komplett auf der DB-Uhr), der
  Client zählt lokal herunter — keine Zeitstempel-Vergleiche zwischen PHP-, DB- und
  Browser-Uhr mehr (`api/game.php`, `app/game.php`). Auch der Reload-Pfad injiziert
  keinen rohen MySQL-Zeitstempel mehr ins JS, und der Server-Guard rechnet nicht
  mehr PHP-`time()` gegen den DB-String. Läuft der Cooldown serverseitig noch
  (z. B. auf zweitem Gerät gestartet), synchronisiert der Client die Anzeige statt
  einen Fehler zu zeigen. Nebenbei: Null-Deref beim Readback entfernt und die
  Antwort läuft jetzt über `jsonOk()`/`jsonResponse()` statt hand-`echo`tem JSON.
- **„Max. 1 Hinrichtung pro Bürgerversammlung" sperrte den ganzen Tag.** Die Regel
  war an `(game_id, round)` gekoppelt, `round` steigt aber nur bei Nacht→Tag —
  eine zweite Versammlung am selben Tag konnte nie henken. Neuer Helper
  `hangedThisAssembly()` (`core/helpers.php`): geprüft wird jetzt, ob seit Beginn
  der zuletzt zustande gekommenen Versammlung bereits jemand gehenkt wurde
  (Zeitvergleich komplett auf der DB-Uhr). Genutzt von `execute_vote` UND dem
  Dashboard-Voting-Block — das vorher wortgleich duplizierte SQL ist damit weg;
  die Prüfung läuft im Dashboard zudem nur noch, wenn überhaupt Stimmen vorliegen.
- **Phantom-Hinrichtung bei bereits totem Ziel.** `execute_vote` löschte Stimmen,
  sendete Push und meldete Erfolg, obwohl `recordDeath()` bei Toten nichts tut.
  Jetzt wird vorab geprüft, dass der Angeklagte lebend im Spiel ist.
- **`execute_vote`: expliziter `player_id` umging die Stimmenmehrheit.** Ein
  mitgeschickter `player_id` wird jetzt gegen den Top-Angeklagten geprüft; bei
  Stimmengleichstand entscheidet deterministisch die kleinere Spieler-ID —
  Dashboard-Anzeige und API nutzen dieselbe Sortierung und können nicht mehr
  divergieren.
- **Race Condition bei parallelen Hinrichtungen.** Guards + `recordDeath` laufen
  jetzt unter MySQL `GET_LOCK` — zwei gleichzeitige `execute_vote`-Requests können
  nicht mehr beide henken.
- **Auto-Rollenkarte: Funken-Bootstrap + Interval-Leak.** Der Funken-Start prüfte
  den localStorage-Spiegel statt des tatsächlichen (DB-gerenderten) Overlay-Zustands;
  jetzt zählt die Overlay-Klasse `open`. `openRoleCard()` räumt außerdem einen
  laufenden Funken-Timer auf, statt Intervalle zu stapeln.
- **Rollen-Icon-Cache: Fehlantworten wurden 7 Tage gecacht.** `Header always set`
  stempelte den Cache-Header auch auf 403/404 — jetzt nur noch bei Status 200
  (Fehler behalten die globalen No-Cache-Header); `immutable` entfernt, da Icons
  per scp in-place ersetzt werden können. Fehlende `?v=`-Cache-Buster ergänzt:
  Icon-Vorschau im Rollen-Formular und Rollen-Galerie der Spieler-Anleitung nutzen
  jetzt `assetUrl()`/`roleIconUrl()` (filemtime-basiert).

### Geändert
- **`playerSettings()` hat jetzt einen Request-Cache** (wie `role()`) — `base.php`
  und die Seite selbst lösten pro Seitenaufbau doppelte SELECTs aus.
- **Voting-Block im Admin-Dashboard:** Der Sperr-Grund („bereits gehenkt" /
  „zu wenig Stimmen") ist nur noch einmal formuliert und dient als Hinweistext
  und Button-Tooltip zugleich (vorher vierfach dupliziert).

---

## [v0.0.17] — 2026-07-05

### Hinzugefügt
- **Startseite = Rollenkarte (Privatsphäre-Einstellung).** Neuer Toggle „Rollenkarte
  beim Öffnen zeigen" unter Optionen → 🔒 Privatsphäre (Standard: aus). Bei aktivierter
  Einstellung rendert `app/game.php` das Rollenkarten-Overlay serverseitig direkt im
  offenen Zustand, damit beim erneuten Einloggen/Neuladen nie kurz das Spielfenster
  aufblitzt, bevor die Karte erscheint — relevant, wenn andere Spieler zusehen könnten.
  Neuer Settings-Key `ww_auto_rolecard` (Allowlist `api/game.php`), dokumentiert in
  `docs/spieler.php`.
- **Rollen-Icons cachebar.** `assets/icons/roles/.htaccess` durchbricht die globale
  No-Cache-Regel (root `.htaccess`) gezielt für diesen Ordner: `Cache-Control: public,
  max-age=604800, immutable`, `Pragma`/`Expires` entfernt. Rollen-Icons ändern sich
  praktisch nie, wurden bisher aber bei jedem Aufruf neu geladen — spürbar langsamer
  gerade beim automatischen Öffnen der Rollenkarte.

---

## [v0.0.16] — 2026-07-04

### Hinzugefügt
- **Voting-System: Mindeststimmen für Hinrichtung.** Feste Spielregel
  `MIN_VOTES_TO_HANG` (= 2, `core/bootstrap.php`, kein Admin-UI-Feld) — der
  „Hängen"-Button im Admin-Panel bleibt deaktiviert, bis der Angeklagte mit
  den meisten Stimmen mindestens diese Anzahl erreicht hat. Zusätzlich
  serverseitig in `api/admin.php` (`execute_vote`) geprüft, damit die Regel
  nicht über die API umgangen werden kann.
- **Max. 1 Hinrichtung pro Bürgerversammlung.** Wurde in der aktuellen Runde
  bereits jemand gehenkt, bleibt der „Hängen"-Button für alle weiteren
  Angeklagten dieser Versammlung gesperrt — geprüft über `(game_id, round)`
  in der `deaths`-Tabelle, sowohl im Admin-Panel als auch serverseitig.

---

## [v0.0.15] — 2026-07-04

### Behoben
- **Rollen-Cooldown startete bei falschem Wert** (Timer zeigte nach dem Start
  sofort ~20 Sekunden Rückstand statt bei 0 zu beginnen). Ursache: `api/game.php`
  (`start_cooldown`) schrieb den Startzeitpunkt per MySQL `NOW()` in die DB, gab
  dem Client aber einen separat per PHP `date('c')` berechneten Zeitstempel
  zurück — zwei unterschiedliche Uhren (Web-Server-Prozess vs. DB-Server), die
  schon bei geringer Drift einen sofortigen Rückstand verursachten. Jetzt wird
  der tatsächlich gespeicherte Wert nach dem `UPDATE` zurückgelesen und als
  ISO-Zeitstempel an den Client geschickt.

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
