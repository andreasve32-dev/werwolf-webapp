# Design: „Das Paar" — Benachrichtigung statt Automatik-Tod

**Datum:** 2026-07-12
**Status:** genehmigt

## Ausgangslage

Rollen mit dem Flag `linked_death=1` (aktuell nur „Das Paar") lösen aktuell in
`core/helpers.php` → `recordDeath()` einen Automatismus aus: Stirbt ein
Spieler dieser Rolle, sterben alle anderen lebenden Spieler derselben Rolle
sofort automatisch mit (Todesursache fest „Vor Kummer gestorben").

## Ziel

Der überlebende Rollenpartner stirbt **nicht mehr automatisch**. Stattdessen
bekommt er/sie eine Benachrichtigung, dass der Partner gestorben ist, und
kann sich danach — genau wie jeder andere Spieler auch — jederzeit über den
bereits vorhandenen „☠️ Meinen Tod melden"-Button (`self_report_death`)
selbst als tot eintragen. Kein erzwungener Zeitpunkt, keine automatische
Todesursache mehr.

Das Flag `linked_death` bleibt in der DB bestehen (kein Rename, keine
Schema-Änderung) — es wechselt nur seine Bedeutung von „automatischer
Mit-Tod" zu „Partner-Benachrichtigung bei Tod". Die Umsetzung bleibt
generisch (kein Hardcoding auf „Das Paar" als Rollenname), damit sie für
jede künftige Rolle mit diesem Flag funktioniert.

## Komponenten

### 1. Server: `core/helpers.php` → `recordDeath()`

Der bestehende Block (Zeilen ~403–415), der rekursiv `recordDeath()` für
alle lebenden Rollenpartner aufruft, wird ersetzt durch: für jeden noch
lebenden Partner derselben Rolle eine gezielte Push-Nachricht senden
(`WebPush::sendToPlayer()`, analog zum bestehenden Detektiv-Hinweis in
`grantKillHints()`).

- Kein rekursiver `recordDeath()`-Aufruf mehr, kein `is_alive=0`-Update,
  kein `deaths`-Eintrag für den Partner.
- Push-Text bewusst **neutral** (kein Name, kein Rollenbezug) — sichtbar auch
  auf einem gesperrten Bildschirm, wo Dritte mitlesen könnten:
  - Titel: `🔔 Neuigkeit im Spiel`
  - Text: `Öffne das Spielfenster für Details.`
- `require_once CORE_PATH . '/WebPush.php';` wie an den anderen Stellen in
  `helpers.php`.
- Kein neuer DB-State nötig: `recordDeath()` bricht bei bereits totem
  Spieler sofort ab (`if (!$gp || !$gp['is_alive']) return;`), die
  Push-Nachricht kann also pro Todesfall nicht doppelt ausgelöst werden.

### 2. Hinweis im Spielfenster: `templates/game_blocks.php` →
   `render_my_status_actions()`

Diese Funktion wird bereits sowohl beim initialen Seitenaufbau
(`app/game.php`) als auch bei jedem Poll (`api/game.php` → `get_players` →
`my_status_html`) neu gerendert und im Client per `innerHTML`-Swap
ausgetauscht — kein zusätzlicher Client-Code nötig.

Ergänzung: Lebt der Spieler noch und hat er eine Rolle mit `linked_death=1`,
wird geprüft, ob es einen toten Mitspieler mit derselben `role_id` gibt. Falls
ja, erscheint direkt über dem „☠️ Meinen Tod melden"-Button ein Alert-Banner:

> 💔 **{Name}** ist gestorben. Du kannst dich jetzt jederzeit selbst als tot
> melden, wenn du bereit dazu bist.

- Die Abfrage läuft direkt in `render_my_status_actions()` über
  `$myGP['role_id']` und `$myGP['game_id']` (beide bereits vorhanden, da
  `$myGP` aus `SELECT *` auf `game_players` stammt) — kein Signatur-Wechsel
  der Funktion, kein zusätzlicher Query an den Aufrufstellen.
- Bei mehreren toten Partnern (bei „Das Paar" nie der Fall, aber generisch
  möglich) wird der zuletzt verstorbene angezeigt.
- Der Banner verschwindet automatisch, sobald sich der Spieler selbst
  meldet — dann greift die bereits vorhandene „☠ Du bist tot"-Anzeige
  weiter oben in derselben Funktion. Kein Dismiss-Mechanismus, kein
  zusätzlicher „gesehen"-State nötig.
- Wird der tote Partner über den Admin-Debug wiederbelebt, verschwindet der
  Banner ebenfalls automatisch (Bedingung „toter Partner vorhanden" wird
  wieder falsch).

### 3. Texte anpassen

**Rollentext „Das Paar"** — in der Live-DB (per SQL) **und** in
`db/schema.sql` / `db/init.sql` (Seed-Daten):

- `description`: unverändert („2 Spieler bilden ein Paar und kennen sich von
  Beginn an.")
- `rules`: alt → „Ihr kennt euren Partner. Stirbt dein Partner, nimmst du
  dir das Leben sobald du seinen Tod bemerkst."
  neu → „Ihr kennt euren Partner. Stirbt dein Partner, wirst du
  benachrichtigt (Push + Hinweis im Spielfenster) — du kannst dich danach
  jederzeit selbst als tot melden, wenn du bereit dazu bist."

**Admin-Rollenformular** (`templates/role_form_fields.php`, Flag-Erklärtext
`linked_death`):
- alt: „Stirbt ein Spieler dieser Rolle, sterben automatisch alle anderen
  lebenden Spieler derselben Rolle mit ('Vor Kummer gestorben') — z.B. Das
  Paar. Der Ort bleibt bis zur Befragung verborgen wie bei jedem anderen
  Tod."
- neu: „Stirbt ein Spieler dieser Rolle, werden alle anderen lebenden
  Spieler derselben Rolle per Push benachrichtigt (z.B. Das Paar) — sie
  sterben nicht automatisch mit, sondern können sich jederzeit selbst als
  tot melden."
- Label „Gemeinsamer Tod" → „Partner-Benachrichtigung" (Icon 💔 bleibt).

**`templates/role_card.php`** (Tag-Anzeige in der Admin-Karte): Tag-Text
„💔 Gemeinsamer Tod" → „💔 Partner-Benachrichtigung".

**`README.md`**: Zeilen 524 und 538 (Rollen-Flag-Tabelle bzw.
Standard-Rollen-Tabelle) entsprechend umschreiben.

### 4. Sonstiges

- Keine Schema-Änderung (Flag existiert bereits).
- CHANGELOG-Eintrag + `app_version`-Bump wie im Projekt üblich.
- Bestehendes generisches Game-Death-Push (`WebPush::sendToGame` in
  `api/admin.php` bei Hinrichtung/Nacht-Kill/manuellem Kill) bleibt
  unverändert — informiert weiterhin alle Spieler, dass irgendjemand
  gestorben ist. Die neue, gezielte Partner-Push ist zusätzlich und separat.
- Der Fall „Partner meldet sich selbst über `self_report_death`" sendet
  aktuell **keinen** Game-weiten Push (bestehendes Verhalten, nicht Teil
  dieser Änderung).

## Testplan

- Manueller Testlauf mit 2 Test-Accounts in einer „Das Paar"-Runde auf dem
  Testserver: Partner A stirbt (Admin-Kill) → Partner B bekommt Push +
  sieht Banner im Spielfenster, bleibt selbst am Leben. B meldet sich
  später selbst über den bestehenden Button → Banner verschwindet, B gilt
  als tot.
- Kontrolle: Rolle ohne `linked_death` (z.B. Bürger) zeigt keinen Banner,
  wenn irgendwer stirbt.
- Kontrolle: PHP-Lint aller geänderten Dateien im Container
  (`docker exec mein_webserver php -l …`).
