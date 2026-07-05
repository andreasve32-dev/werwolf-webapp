# Werwolf Web-App — Regeln für KI-Assistenten

Browserbasiertes Werwolf-Spiel: PHP (kein Framework) + MySQL/MariaDB + Apache,
Vanilla-JS, mobile-first. Struktur siehe README.md.

- **Nur `index.php` liegt im Root.** Alle anderen Seiten (Spiel, Todesliste,
  Rollen, Statistik, FAQ, Logout, Registrierung, Datenschutz, Impressum,
  Nutzungsbedingungen, Rollen-Backup) liegen unter `app/` (Umzug 2026-07-04,
  Root war zu unübersichtlich geworden). Neue Root-Unterseiten immer dort
  ablegen, nie direkt im Root. Bootstrap-Require: wie bei `admin/*.php` —
  `require_once dirname(__DIR__) . '/core/bootstrap.php'`. Alte Root-URLs
  leiten per 301 in `.htaccess` auf `/app/…` um — bei neuen Seiten in `app/`
  KEINE Root-Redirect-Regel nötig (nur für die verschobenen Alt-URLs).

## Lokale Entwicklung & Deployment

- **Lokale Arbeitskopie und Live-Server sind getrennt** (seit 2026-07-05).
  Vorher lag das Projekt auf einem Netzlaufwerk, das direkt auf den
  Server-Webordner gemountet war — jede lokale Änderung war sofort live.
  Das ist jetzt nicht mehr so: der Server hat einen eigenen, unabhängigen
  Dateibestand (Docker-Compose-Setup, Webroot als Volume in einen
  `php:8.2-apache`-Container gemountet, siehe README.md → Docker-Compose-Abschnitt).
- **Jede Codeänderung muss deshalb aktiv auf den Server übertragen werden**,
  sonst kann sie dort nicht live getestet werden — kein automatischer Sync
  mehr. Übertragung nur per Datei-Kopie (z. B. `scp`) auf den Server, kein
  `rsync` lokal verfügbar. Ein Neustart der Container ist dafür normalerweise
  NICHT nötig (PHP liest Dateien pro Request neu ein) — **außer** bei
  Schema-Änderungen: die laufen weiterhin nur manuell per SQL auf dem
  DB-Container (siehe „DB-Updates immer explizit nennen" unten).
- **Zugangsdaten (SSH-Host, Nutzer, Passwort/Key-Pfad, Server-Verzeichnisstruktur)
  stehen absichtlich NICHT in diesem Repo**, sondern nur im lokalen
  Claude-Code-Memory des Betreiber-Rechners (nicht Teil von Git/GitHub).
  Bei Bedarf dort nachschlagen, nicht hier eintragen.

## Grundregeln

- **Sprache:** Deutsch für alle UI-Texte, Kommentare und Commit-Messages.
- **DB-Updates immer explizit nennen:** Jede Änderung, die neue Tabellen,
  Spalten oder Settings-Werte braucht, am Ende der Antwort klar auflisten
  (fertiges `ALTER TABLE`-Statement mitgeben).
- **`admin/setup.php` ist DESTRUKTIV** (führt `db/schema.sql` mit
  `DROP TABLE` aus). Niemals für Migrationen empfehlen — nur für die
  Ersteinrichtung einer leeren Datenbank. Migrationen: manuelles SQL
  oder selbstheilende Checks (siehe `ensurePlayerSettingsColumn()`).
- **MariaDB-Kompatibilität:** kein `VALUES(col)`-Alias-Syntax-Mix,
  `JSON_SET` ist ok (MariaDB 10.2+). Immer PDO-Prepared-Statements.

## Live-Updates (kein Seitenreload)

- **Kein `location.reload()`** für Datenaktualisierung. Einzige erlaubte
  Ausnahmen: `admin/settings.php` (Logo/Favicon-Cache-Busting) und der
  einmalige Reload in `game.php` bei Spiel-**Status**-Wechsel
  (Lobby→Läuft etc.), weil Rollenkarte/Konstanten nur initial gerendert werden.
- **Polling immer über `liveBlocks()`** (`assets/js/app.js`) — nie eigene
  `setInterval`-Loops. Der Helper liefert: Overlap-Guard, Pause bei
  verstecktem Tab, Countdown-Anzeige (`countdownId: 'poll-countdown'`,
  Element liegt zentral in `templates/nav.php`; pro Seite steuert nur der
  Haupt-Poller den Countdown), Intervall aus der Spieler-Einstellung
  `ww_poll_interval` und Neustart bei Änderung.
  Für JSON-Payloads ohne HTML-Blocks den `onData`-only-Modus nutzen.
- **Keine festen Poll-Intervalle.** Alle Server-Polls folgen der
  Spieler-Einstellung — `config.interval` bei `liveBlocks()` nicht setzen.
  Reine Anzeige-Ticker ohne Serverkontakt (1s-Countdowns, Slogan-Rotation)
  sind davon ausgenommen.
- **Blöcke nur bei Änderung übertragen:** Block-Endpunkte antworten über
  `blocksResponse($blocks, $input['blocks_hash'] ?? null)`
  (`core/helpers.php`) und der Client-Fetcher reicht den Hash durch:
  `fetcher: (hash) => apiFetch(url, {action:'…', blocks_hash: hash})`.
  Bei unverändertem Inhalt geht nur `{hash}` zurück — kein DOM-Umbau,
  kein Flackern, kein unnötiger Transfer. Client-seitig gerenderte Listen
  (z.B. Spielerliste in `game.php`) vergleichen das erzeugte HTML mit dem
  zuletzt geschriebenen, bevor sie `innerHTML` anfassen.
- **Server-Muster:** Seiteninhalt in Render-Funktionen unter
  `templates/*_blocks.php` extrahieren (`ob_start()`/`ob_get_clean()`),
  die sowohl die Seite als auch der API-Endpunkt nutzen. API liefert
  `{blocks: {key: html}}`; leerer String = Block wird client-seitig versteckt.
- **Teure Endpunkte** (viele Aggregat-Queries wie `get_stats`): vor der
  Berechnung eine billige Versions-Probe (COUNT/MAX) gegen die vom Client
  mitgesendete `version` prüfen und bei Gleichheit nur `{version}` antworten.
- **API-Endpunkte:** direkt nach dem Auth-Check `session_write_close()`
  aufrufen (sonst serialisieren parallele Polls desselben Nutzers).
  Danach nichts mehr in `$_SESSION` schreiben.
- Nach jedem `innerHTML`-Swap geht UI-Zustand verloren — offene
  Akkordeons/Auswahlen im `onData`-Callback wiederherstellen
  (Beispiele: `stats.php`, `faq.php` `_restoreOpenItems()`).

## Rollen-System

- **`dump_roles_temp.php` ist eine dauerhafte Backup-Seite** (Rollen-Tabelle
  als INSERT-Dump, Admin-geschützt) — trotz des Namens NICHT löschen und
  nicht als temporär einstufen. Bei neuen Rollen-Flags die Spaltenliste
  dort mitpflegen.

- **Rollen sind komplett datenbankgesteuert** (`roles`-Tabelle, Pflege über
  `admin/roles.php`). Später kommen weitere Flags dazu — **niemals
  Rollennamen hart codieren** (Altlast: Dodo-Siegprüfung matcht noch auf
  `r.name = 'Dodo'`; bei Gelegenheit durch Flag ersetzen).
- **Checkliste für ein neues Rollen-Flag** (alle Stellen anfassen):
  1. `db/schema.sql` + `db/init.sql` (Spalte in `roles`)
  2. `templates/role_form_fields.php` (Formularfeld, Präfix-Konvention `rf-`/`ef-{id}-` beachten)
  3. `admin/roles.php` → `collectFormData()` (JS liest die Feld-IDs)
  4. `api/admin.php` → `role_create` **und** `role_update` (Spaltenlisten)
  5. `templates/role_card.php` (Tag-Anzeige in der Admin-Karte)
  6. ggf. Spiellogik (`api/game.php` Sichtbarkeit, `api/admin.php` Siegbedingungen)
  7. DB-Update-Hinweis an den Betreiber (`ALTER TABLE roles ADD COLUMN …`)

## Sicherheit

- **Ausgabe immer escapen:** `e()` in PHP-Templates (auch in per JSON
  gelieferten HTML-Fragmenten!), `escHtml()` in JS-Template-Strings.
- **Auth-Muster:** Seiten/Endpunkte schützen sich selbst mit
  `Auth::requireLogin()` / `Auth::requireAdmin()` — `bootstrap.php` startet
  nur die Session. Jede neue Datei im Web-Root braucht einen Auth-Aufruf.
- **JSON-Antworten** über die Helper `jsonOk()` / `jsonError()` /
  `jsonResponse()` (`core/helpers.php`), nicht hand-`echo`t.
- Nutzereingaben in APIs: Allowlists für Schlüssel/Aktionen, Längen-Limits,
  `(int)`-Casts für IDs.

## Spieler-Einstellungen

- Persistenz in `players.settings` (JSON als TEXT), geräteübergreifend.
  Schreiben nur über die `save_setting`-Action (`api/game.php`) mit
  Key-Allowlist und atomarem `JSON_SET`. `localStorage` ist nur schneller
  Spiegel — `templates/base.php` synct DB → localStorage beim Seitenaufbau.
  Neue Einstellungs-Keys: in die Allowlist in `api/game.php` eintragen.
