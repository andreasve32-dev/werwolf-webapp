# Design: Seitenwechsel-Flag für den Schläfer

**Datum:** 2026-07-12
**Status:** genehmigt

## Ziel

Neues, generisches Rollen-Flag (nicht auf „Schläfer" hartcodiert): Ein
Spieler mit dieser Rolle startet normal im Killer-Team (kann wie gewohnt
mit seiner Killer-Fähigkeit töten), wechselt aber zu einem **zufälligen
Zeitpunkt innerhalb einer konfigurierbaren Minuten-Spanne ab Spielstart**
automatisch und vollständig zur Bürger-Seite. Ab diesem Moment ist er nur
noch die normale Auffüll-Rolle (Bürger) — keine Killer-Fähigkeit mehr,
zählt bei der Siegprüfung nicht mehr als Killer.

Für den Schläfer konkret: Spanne **30–90 Minuten**, Rolle bleibt zunächst
**deaktiviert** (`active=0`) — nur die Technik wird gebaut, der Betreiber
schaltet sie selbst frei, wenn er bereit ist.

## Umsetzung: „Rollenwechsel simulieren"

Statt eines neuen Zustands pro Spieler (z. B. `game_players.switched_side`)
wird `game_players.role_id` beim Auslösen direkt auf die aktive
Auffüll-Rolle (Bürger, `roles.fill=1 AND active=1`) umgestellt. Vorteil:
Sieg-Logik (`checkAndEndGame()`, zählt über `roles.is_killer`) und
Killer-Sichtbarkeit funktionieren danach automatisch korrekt, ohne dass
diese Stellen angefasst werden müssen. Akzeptierter Kompromiss (vom
Betreiber bestätigt): Die ursprüngliche Schläfer-Identität ist danach
technisch weg — bei einem späteren Tod erscheint der Spieler als „Bürger",
nicht als „Schläfer".

## Neue DB-Spalten

**`roles`** (generisch für jede künftige Rolle mit diesem Verhalten):
- `side_switch` TINYINT(1) DEFAULT 0 — aktiviert den Mechanismus
- `side_switch_min` INT DEFAULT 0 — frühester Zeitpunkt (Minuten ab Spielstart)
- `side_switch_max` INT DEFAULT 0 — spätester Zeitpunkt (Minuten ab Spielstart)

**`games`**:
- `started_at` TIMESTAMP NULL DEFAULT NULL — Zeitpunkt des tatsächlichen
  Spielstarts (Lobby→Running). Bisher gibt es nur `created_at`
  (Lobby-Anlage) und `updated_at` (ändert sich bei JEDEM Update, z. B.
  Phasenwechsel — als stabiler Startzeitpunkt ungeeignet). Wird in
  `api/admin.php` beim `start_game`-Übergang gesetzt.

## Auslöse-Mechanik (vollautomatisch, wie `grantKillHints()`)

Neue Funktion `applySideSwitches(int $gameId)` in `core/helpers.php`,
aufgerufen am Anfang von `get_players` (`api/game.php`) — läuft also bei
jedem Poll eines beliebigen Spielers mit, genau wie die bestehenden
vollautomatischen Mechaniken (z. B. Kill-Hinweise für den Detektiv).

```php
function applySideSwitches(int $gameId): void {
    $g = Database::queryOne("SELECT started_at FROM games WHERE id=? AND status='running'", [$gameId]);
    if (!$g || !$g['started_at']) return;
    $candidates = Database::query(
        "SELECT gp.player_id, r.side_switch_min, r.side_switch_max,
                TIMESTAMPDIFF(SECOND, ?, NOW()) AS elapsed
         FROM game_players gp JOIN roles r ON r.id=gp.role_id
         WHERE gp.game_id=? AND gp.is_alive=1 AND r.side_switch=1",
        [$g['started_at'], $gameId]
    );
    if (!$candidates) return;
    $fillRole = Database::queryOne("SELECT id FROM roles WHERE active=1 AND fill=1 LIMIT 1");
    if (!$fillRole) return;
    require_once CORE_PATH . '/WebPush.php';
    foreach ($candidates as $c) {
        $min = max(0, (int)$c['side_switch_min']);
        $max = max($min, (int)$c['side_switch_max']);
        // Deterministisch aus Spiel+Spieler-ID abgeleitet (kein Extra-Zustand
        // nötig, kein erneuter Zufallswurf bei jedem Check).
        $seed   = crc32($gameId . ':' . $c['player_id'] . ':side_switch');
        $range  = ($max - $min) * 60 + 60;
        $target = $min * 60 + ($seed % $range);
        if ((int)$c['elapsed'] >= $target) {
            Database::execute("UPDATE game_players SET role_id=? WHERE game_id=? AND player_id=?",
                [$fillRole['id'], $gameId, $c['player_id']]);
            // Einmaliger Hinweis im Spielfenster (siehe unten) + neutrale Push
            Database::execute(
                "INSERT IGNORE INTO role_insights (game_id, viewer_player_id, target_player_id, source) VALUES (?,?,?,'side_switch')",
                [$gameId, $c['player_id'], $c['player_id']]
            );
            WebPush::sendToPlayer((int)$c['player_id'], '🔔 Neuigkeit im Spiel', 'Öffne das Spielfenster für Details.');
        }
    }
}
```

Sobald `role_id` umgestellt ist, taucht der Spieler in der obigen
`WHERE r.side_switch=1`-Abfrage nicht mehr auf — kein Doppel-Wechsel möglich.

## Hinweis im Spielfenster

`render_my_status_actions()` (`templates/game_blocks.php`) bekommt eine
weitere Prüfung (analog zur Paar-Partner-Benachrichtigung): existiert für
den aktuellen Spieler ein `role_insights`-Eintrag mit
`source='side_switch'`? Falls ja, bleibender Hinweis:

> 💤 Deine Zeit als Schläfer ist vorbei — du gehörst jetzt zu den Bürgern.

Kein Dismiss-Mechanismus nötig (bleibt für den Rest der Partie stehen,
gleiche bewusste Vereinfachung wie beim Paar-Feature).

## Admin-Formular & Anzeige

- `templates/role_form_fields.php`: neues Flag „Seitenwechsel" (Icon 💤)
  + zwei Zahlenfelder „Wechsel ab (Min.)" / „Wechsel bis (Min.)".
- `admin/roles.php` → `collectFormData()`: liest die drei neuen Feld-IDs.
- `api/admin.php` → `role_create` / `role_update`: Spaltenlisten erweitert.
- `templates/role_card.php`: Tag „💤 Seitenwechsel" wenn Flag gesetzt.

## Für die Schläfer-Rolle konkret (Live-DB + Seeds)

- `side_switch=1`, `side_switch_min=30`, `side_switch_max=90`
- `active` bleibt `0` (unverändert) — Betreiber aktiviert später selbst.

## Nicht Teil dieses Designs (bewusst weggelassen)

- `killer_sichtbar` für den Schläfer (damit er andere Killer erkennt) —
  nicht angefragt, wird nicht mitgebaut.
- Dynamische „Vorwarnung an andere Killer"-Textanzeige — bleibt reiner
  Flavor-Text in der Rollenbeschreibung, kein eigener Mechanismus.

## Testplan

- Isoliertes Testspiel: Spieler auf eine Test-Rolle mit `side_switch=1`,
  `side_switch_min=0`, `side_switch_max=0` setzen (sofortiger Wechsel) →
  `get_players`-Poll auslösen → `role_id` muss auf die Bürger-Rolle
  umgestellt sein, `role_insights`-Eintrag vorhanden, `my_status_html`
  zeigt den Hinweis.
- Kontrolle: Spieler ohne `side_switch`-Rolle bleibt unangetastet.
- PHP-Lint aller geänderten Dateien im Container.
