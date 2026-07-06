# Changelog — Werwolf Web-App

Jedes Backup erhält eine fortlaufende Versionsnummer (v0.0.x).

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
