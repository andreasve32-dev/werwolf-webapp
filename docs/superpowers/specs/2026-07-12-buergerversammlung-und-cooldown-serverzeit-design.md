# Design: Bürgerversammlung sofort starten + reine Server-Zeit für alle Cooldowns

**Datum:** 2026-07-12
**Status:** genehmigt

## Ausgangslage

1. Die Bürgerversammlung braucht aktuell zwei Einberufer (Antrag + Unterstützung),
   startet danach aber erst zur **nächsten vollen Stunde** — mit tickendem
   Countdown im Spielfenster und Admin-Panel.
2. Interne Tests haben gezeigt: Anzeigen, die sich auf die **lokale
   Client-Uhr** (`Date.now()`) stützen, lassen sich durch Verstellen der
   Geräte-Uhr beeinflussen bzw. sorgen für Verwirrung, wenn die Geräte-Uhr
   abweicht. Ziel: überall, wo eine Uhr für Cooldowns/Timing eine Rolle
   spielt, ist ausschließlich die **Server-/DB-Uhr** maßgeblich — der Client
   zeigt nur an, was der Server zuletzt gemeldet hat.

## Teil 1: Bürgerversammlung

### Sofort-Start statt volle Stunde
`api/game.php` → `call_assembly`, Phase 2 (zweiter Einberufer): `scheduled_at`
wird auf **jetzt** (`time()`) gesetzt statt auf die nächste volle Stunde.
`notified` wird sofort auf 1 gesetzt (die Ankündigung geht direkt als Push
raus: „🏛️ Versammlung beginnt jetzt! — X und Y rufen zusammen, kommt jetzt
zusammen!"). Der bisherige lazy „fällige Erinnerung"-Check in `get_assembly`
entfällt (wird nie mehr fällig, da `scheduled_at` nie in der Zukunft liegt).

### 15-Minuten-Sperre nach Ende
`call_assembly`, Phase 1 (erster Antrag): vor dem Anlegen des Antrags wird
serverseitig per SQL geprüft, ob es eine beendete Versammlung
(`ended_at IS NOT NULL`) in diesem Spiel gibt, deren Ende
`TIMESTAMPDIFF(SECOND, ended_at, NOW()) < 900` ist (DB-Uhr, kein PHP-`time()`
und kein Client-Zeitstempel). Falls ja: Fehlermeldung „Nach einer Versammlung
muss das Dorf noch ca. X Minuten warten." (X aus dem DB-Ergebnis berechnet).

### UI wird zustandsbasiert statt zeitbasiert
Da `scheduled_at` nie mehr in der Zukunft liegt, gibt es nur noch zwei
sichtbare Zustände statt drei:
- **Antrag wartet** (`pending`) — unverändert.
- **Versammlung läuft** — sofort nach der Unterstützung durch den zweiten
  Spieler.

Entfernt wird:
- Der komplette Countdown-Abschnitt im Spielfenster (`assembly-countdown-section`,
  tickender MM:SS-Timer) in `app/game.php`.
- `_assemblyIsRunning()` vergleicht aktuell `_assemblyData.scheduled_at` gegen
  `Date.now()` (Client-Uhr!) — wird ersetzt durch eine reine Zustandsprüfung
  ohne Zeitvergleich: `!!(_assemblyData && !_assemblyData.pending && _assemblyData.scheduled_at)`.
  Kein `Date.now()` mehr in der gesamten Versammlungs-Logik.
- Die „Termin: HH:MM Uhr" + Countdown-Variante im Admin-Banner
  (`templates/admin_dashboard_blocks.php` → `admin_render_assembly_banner()`)
  und der zugehörige `setInterval`-Countdown-Timer in `admin/index.php`
  (`#admin-assembly-countdown`) — beide zeigen künftig sofort „Versammlung
  läuft jetzt!".

Unverändert: 2-Einberufer-Antragslogik, `hangedThisAssembly()` (nutzt
weiterhin `scheduled_at` als DB-Wert, keine Client-Zeit beteiligt),
Beenden-Rechte, Anklagen nur während laufender Versammlung (Server-Check via
`scheduled_at<=?` mit PHP-`time()` bleibt wie es ist — das ist Server-, keine
Client-Zeit).

## Teil 2: Rollen-Fähigkeits-Cooldown — Anzeige auf reines Server-Polling umstellen

Aktuell (`app/game.php`, `initCooldown()`): Server liefert einmalig
verbleibende Sekunden, Client zählt mit `Date.now()` + `setInterval(tick, 1000)`
lokal sekundengenau herunter. Das ist zwar schon serverseitig abgesichert
(der eigentliche Fähigkeits-Einsatz wird bei jedem `start_cooldown`-Aufruf
erneut gegen die DB-Uhr geprüft und bei manipulierter Client-Uhr korrekt
abgelehnt) — aber auf ausdrücklichen Wunsch wird jetzt auch die **Anzeige**
komplett von der lokalen Uhr gelöst:

- `api/game.php` → `get_players`: `$myGPRow`-Query um `r.cooldown` und
  `TIMESTAMPDIFF(SECOND, gp.cooldown_started_at, NOW())` erweitert (JOIN
  roles), daraus über die bestehende `cooldownRemainingSecs()`-Funktion die
  aktuell verbleibenden Sekunden berechnet und im JSON unter
  `me.cooldown_remaining_secs` mitgeliefert (fließt bei jedem Poll mit,
  kein zusätzlicher Request).
- `app/game.php` → `initCooldown()`: `Date.now()`/`endsAt`/`setInterval(tick,1000)`
  entfällt komplett. `_setCooldownRemaining(secs)` rendert die Anzeige direkt
  aus dem übergebenen Wert (Button-Text, MM:SS, disabled-Status) ohne eigene
  Zwischenzählung.
- `renderGameState(r)` (Haupt-Poll-Handler): ruft bei jedem Poll
  `window._setCooldownRemaining(r.me.cooldown_remaining_secs)` auf, sobald
  sich der Wert geändert hat.

**Bewusster UX-Tradeoff** (vom Betreiber bestätigt): Die Anzeige aktualisiert
sich dadurch nur noch im Rhythmus des eingestellten Poll-Intervalls (springt
z.B. alle paar Sekunden), statt jede Sekunde sichtbar herunterzuzählen.

## Testplan
- `call_assembly` zweimal mit unterschiedlichen Testspielern aufrufen →
  Versammlung sofort aktiv (`scheduled_at <= now` direkt nach dem 2. Aufruf).
- `end_assembly`, danach sofort erneut `call_assembly` → Fehlermeldung mit
  Restzeit; nach (simuliertem) Ablauf der 15 Minuten geht ein neuer Antrag
  wieder durch.
- `start_cooldown` aufrufen, `get_players` pollen → `cooldown_remaining_secs`
  sinkt zwischen zwei Polls plausibel (DB-Uhr), erreicht 0 nach Ablauf.
- PHP-Lint aller geänderten Dateien im Container.
