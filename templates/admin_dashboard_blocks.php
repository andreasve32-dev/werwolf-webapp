<?php
// Copyright (c) 2026 Andreas Vetter
// Wiederverwendbare Render-Funktionen für das Admin-Dashboard — genutzt
// sowohl beim initialen Seitenaufbau (admin/index.php) als auch von
// api/admin.php (get_dashboard) fürs laufende Polling.

if (!function_exists('admin_compute_state')) {
function admin_compute_state(int $gameId): array {
    $game        = Database::queryOne("SELECT * FROM games WHERE id=?", [$gameId]);
    $gamePlayers = gamePlayers($gameId);
    $playerCount = count($gamePlayers);

    // Spieler, die noch nicht im Spiel sind
    $inGameIds    = array_column($gamePlayers, 'player_id');
    $placeholders = $inGameIds ? implode(',', array_fill(0, count($inGameIds), '?')) : '0';
    $available    = $inGameIds
        ? Database::query("SELECT id,username FROM players WHERE id NOT IN ($placeholders) ORDER BY username", $inGameIds)
        : Database::query("SELECT id,username FROM players ORDER BY username");

    // Abstimmungsergebnisse (nur während Tag)
    $votes = [];
    $playerNames = array_column($gamePlayers, 'display_name', 'player_id');
    if ($game['status'] === 'running' && $game['phase'] === 'day') {
        $votes = Database::query(
            "SELECT target_id, COUNT(*) as cnt FROM votes WHERE game_id=? AND round=? GROUP BY target_id ORDER BY cnt DESC",
            [$gameId, $game['round']]
        );
    }

    // Rollen-Vorschau für die Lobby (zeigt NUR Anzahlen, nie wer was bekommt)
    $fillRole     = Database::queryOne("SELECT id,name FROM roles WHERE active=1 AND fill=1 LIMIT 1");
    $specialRoles = Database::query(
        "SELECT id, name, amount FROM roles WHERE active=1 AND fill=0 AND amount>0 ORDER BY sort_order"
    );
    $specialCount = array_sum(array_column($specialRoles, 'amount'));
    $fillCount    = max(0, $playerCount - $specialCount);

    // Gewinn-Bedingung: bei beendetem Spiel aus winner-Spalte, bei laufendem Spiel live prüfen
    $killerWin       = false;
    $citizenWin      = false;
    $dodoWin         = false;
    $aliveKillers    = 0;
    $aliveNonKillers = 0;
    if ($game['status'] === 'finished' && !empty($game['winner'])) {
        $dodoWin    = $game['winner'] === 'dodo';
        $citizenWin = $game['winner'] === 'citizen';
        $killerWin  = $game['winner'] === 'killer';
    } elseif ($game['status'] === 'running') {
        // Eine zusammengefasste Abfrage statt drei einzelner COUNT()s —
        // läuft bei aktivem Polling alle paar Sekunden.
        $killCounts = Database::queryOne(
            "SELECT
               SUM(CASE WHEN gp.is_alive=1 AND r.is_killer=1 THEN 1 ELSE 0 END) AS alive_killers,
               SUM(CASE WHEN gp.is_alive=1 AND (r.is_killer=0 OR r.id IS NULL) THEN 1 ELSE 0 END) AS alive_non_killers,
               SUM(CASE WHEN r.is_killer=1 THEN 1 ELSE 0 END) AS total_killers
             FROM game_players gp LEFT JOIN roles r ON r.id = gp.role_id
             WHERE gp.game_id = ?",
            [$gameId]
        );
        $aliveKillers    = (int)($killCounts['alive_killers'] ?? 0);
        $aliveNonKillers = (int)($killCounts['alive_non_killers'] ?? 0);
        $totalKillers    = (int)($killCounts['total_killers'] ?? 0);

        // Dodo-Sieg: wurde der Dodo per Abstimmung gehenkt?
        $dodoHanged = Database::queryOne(
            "SELECT d.id FROM deaths d
             JOIN roles r ON r.id = d.role_id
             WHERE d.game_id = ? AND d.is_gehenkt = 1 AND r.name = 'Dodo'
             LIMIT 1",
            [$gameId]
        );
        if ($dodoHanged) {
            $dodoWin = true;
        } elseif ($totalKillers > 0 && $aliveKillers === 0) {
            // Bürger-Sieg: alle Killer tot
            $citizenWin = true;
        } elseif ($aliveKillers > 0 && $aliveKillers >= $aliveNonKillers) {
            // Mörder-Sieg: Lebende Spieler ≤ doppelte Anzahl lebender Mörder
            $killerWin = true;
        }
    }

    // Debug: Rollen für Admin-Rollenwahl (nur wenn Debug-Modus an und Spiel läuft)
    $debugRoles     = [];
    $adminGameEntry = null;
    if (APP_DEBUG && $game['status'] === 'running') {
        $debugRoles     = Database::query("SELECT id, name FROM roles WHERE active=1 ORDER BY sort_order, name");
        $adminGameEntry = Database::queryOne(
            "SELECT gp.role_id, r.name AS role_name
             FROM game_players gp LEFT JOIN roles r ON r.id=gp.role_id
             WHERE gp.game_id=? AND gp.player_id=?",
            [$gameId, Auth::player()['id']]
        );
    }

    // Aktuelle Versammlungsanfrage
    $pendingAssembly = null;
    if ($game['status'] === 'running') {
        try {
            $pendingAssembly = Database::queryOne(
                "SELECT ar.scheduled_at, ar.notified, p.display_name AS caller
                 FROM assembly_requests ar JOIN players p ON p.id=ar.player_id
                 WHERE ar.game_id=? AND ar.ended_at IS NULL ORDER BY ar.scheduled_at DESC LIMIT 1",
                [$gameId]
            );
        } catch (Throwable $e) {}
    }

    return compact(
        'game', 'gamePlayers', 'playerCount', 'available', 'votes', 'playerNames',
        'fillRole', 'specialRoles', 'specialCount', 'fillCount',
        'killerWin', 'citizenWin', 'dodoWin', 'aliveKillers', 'aliveNonKillers',
        'debugRoles', 'adminGameEntry', 'pendingAssembly'
    );
}
}

if (!function_exists('admin_render_win_banner')) {
function admin_render_win_banner(array $s): string {
    extract($s);
    ob_start();
    ?>
    <?php if ($dodoWin): ?>
    <div class="alert animate-in" style="font-size:1.05rem;font-family:var(--font-display);text-align:center;padding:1.2rem;background:rgba(168,85,247,.18);border-color:rgba(168,85,247,.4);color:#d8b4fe">
      🐦 <strong>Der Dodo hat gewonnen!</strong><br>
      <span style="font-size:.85rem;font-weight:400">Der Dodo wurde vom Dorf erhängt — sein Plan ist aufgegangen.</span>
    </div>
    <?php elseif ($killerWin): ?>
    <div class="alert alert--error animate-in" style="font-size:1.05rem;font-family:var(--font-display);text-align:center;padding:1.2rem">
      🔪 <strong>Die Mörder haben gewonnen!</strong><br>
      <span style="font-size:.85rem;font-weight:400">
        <?= $aliveKillers ?> Mörder, <?= $aliveNonKillers ?> Überlebende —
        das Dorf kann die Mörder nicht mehr überführen.
      </span>
    </div>
    <?php elseif ($citizenWin): ?>
    <div class="alert alert--success animate-in" style="font-size:1.05rem;font-family:var(--font-display);text-align:center;padding:1.2rem">
      🏘️ <strong>Die Bürger haben gewonnen!</strong><br>
      <span style="font-size:.85rem;font-weight:400">Alle Mörder sind tot — das Dorf ist gerettet.</span>
    </div>
    <?php endif; ?>
    <?php
    return trim(ob_get_clean());
}
}

if (!function_exists('admin_render_assembly_banner')) {
function admin_render_assembly_banner(array $s): string {
    extract($s);
    ob_start();
    ?>
    <?php if ($pendingAssembly): ?>
    <?php $aTime = (int)$pendingAssembly['scheduled_at']; $aLabel = date('H:i', $aTime); ?>
    <div class="alert animate-in" style="display:flex;align-items:center;gap:.8rem;padding:.9rem 1rem;
         background:rgba(99,102,241,.14);border-color:rgba(99,102,241,.4);color:#c7d2fe;font-size:.9rem">
      <span style="font-size:1.5rem">🏛️</span>
      <div style="flex:1">
        <strong><?= e($pendingAssembly['caller']) ?></strong> hat eine Versammlung einberufen
        <?php if ($aTime > time()): ?>
          — Termin: <strong><?= $aLabel ?> Uhr</strong>
          <span class="text-xs" style="opacity:.7;margin-left:.4rem" id="admin-assembly-countdown" data-ts="<?= $aTime ?>"></span>
        <?php else: ?>
          — <strong>Versammlung läuft jetzt!</strong>
        <?php endif; ?>
      </div>
      <button class="btn btn--danger btn--sm" onclick="endAssemblyAdmin()" style="white-space:nowrap">
        ✖ Beenden
      </button>
    </div>
    <?php endif; ?>
    <?php
    return trim(ob_get_clean());
}
}

if (!function_exists('admin_render_game_controls')) {
function admin_render_game_controls(array $s): string {
    extract($s);
    ob_start();
    ?>
    <div class="card card--glow animate-in mb-2">
      <div class="section-title">Spielsteuerung</div>
      <div class="flex flex-wrap gap-sm">
        <?php if ($game['status']==='lobby'): ?>
          <button class="btn btn--primary" onclick="adminAction('start_game')">▶ Spiel starten</button>
          <button class="btn btn--ghost"   onclick="adminAction('reset_game')">🔄 Zurücksetzen</button>
        <?php elseif ($game['status']==='running'): ?>
          <button class="btn btn--ghost" onclick="adminAction('end_game')">🏁 Spiel beenden</button>
        <?php else: ?>
          <button class="btn btn--primary" onclick="adminAction('new_game')">➕ Neues Spiel</button>
        <?php endif; ?>
      </div>
      <div id="action-result" class="mt-2"></div>
    </div>
    <?php
    return trim(ob_get_clean());
}
}

if (!function_exists('admin_render_player_list')) {
function admin_render_player_list(array $s): string {
    extract($s);
    ob_start();
    ?>
    <?php if (empty($gamePlayers)): ?>
      <p class="text-dim text-sm">Noch keine Spieler im Spiel.</p>
    <?php else: ?>
      <div style="display:flex;flex-direction:column;gap:.4rem">
        <?php foreach ($gamePlayers as $gp): ?>
        <div class="panel flex-between" style="padding:.55rem .9rem">
          <div class="flex gap-sm">
            <?php if (!$gp['is_alive']): ?>
              <?php // Rolle bewusst NICHT anzeigen — der Admin spielt mit und
                    // soll nicht über das Dashboard gespoilert werden ?>
              <span style="width:1.1rem;height:1.1rem;display:inline-block"></span>
              <span style="font-family:var(--font-display);font-size:.88rem;color:var(--text-dim)">
                <?= e($gp['display_name']) ?>
              </span>
              <span class="tag tag--dead text-xs">Tot</span>
            <?php else: ?>
              <span style="width:1.1rem;height:1.1rem;display:inline-block"></span>
              <span style="font-family:var(--font-display);font-size:.88rem;color:var(--text-bright)">
                <?= e($gp['display_name']) ?>
              </span>
            <?php endif; ?>
          </div>
          <div class="flex gap-xs">
            <?php if ($gp['is_alive'] && $game['status']==='running'): ?>
              <button class="btn btn--danger btn--sm" title="Als tot markieren"
                onclick="killPlayer(<?= (int)$gp['player_id'] ?>,'<?= e($gp['username']) ?>')">☠</button>
            <?php endif; ?>
            <?php if ($game['status']==='lobby'): ?>
              <button class="btn btn--ghost btn--sm" title="Entfernen"
                onclick="removePlayer(<?= (int)$gp['player_id'] ?>)">✕</button>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <?php
    return trim(ob_get_clean());
}
}

if (!function_exists('admin_render_add_players')) {
function admin_render_add_players(array $s): string {
    extract($s);
    ob_start();
    ?>
    <?php if ($game['status']==='lobby' && !empty($available)): ?>
    <div class="card animate-in mt-2" style="animation-delay:.1s">
      <div class="section-title" style="display:flex;justify-content:space-between;align-items:center;gap:.5rem;flex-wrap:wrap">
        <span>Spieler hinzufügen</span>
        <button class="btn btn--primary btn--sm" onclick="addAllPlayers()">
          ➕ Alle hinzufügen (<?= count($available) ?>)
        </button>
      </div>
      <div class="flex flex-wrap gap-xs" style="margin-top:.5rem">
        <?php foreach ($available as $p): ?>
        <button class="btn btn--ghost btn--sm"
          onclick="addPlayer(<?= (int)$p['id'] ?>,'<?= e($p['username']) ?>')">
          + <?= e($p['username']) ?>
        </button>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
    <?php
    return trim(ob_get_clean());
}
}

if (!function_exists('admin_render_role_preview')) {
function admin_render_role_preview(array $s): string {
    extract($s);
    ob_start();
    ?>
    <?php if ($game['status']==='lobby'): ?>
    <div class="card animate-in" style="animation-delay:.08s">
      <div class="section-title">Rollenverteilung beim Start</div>
      <p class="text-dim text-sm mb-2">
        Die Rollen werden beim Spielstart automatisch und zufällig vergeben.
        Der Admin sieht nicht, wer welche Rolle bekommt.
      </p>

      <?php if (empty($specialRoles)): ?>
        <div class="alert alert--warn">
          Keine aktiven Sonderrollen konfiguriert.
          <a href="<?= APP_URL ?>/admin/roles.php">Rollen verwalten →</a>
        </div>
      <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:.3rem;margin-bottom:.85rem">
          <?php foreach ($specialRoles as $r): ?>
          <div class="flex gap-sm" style="font-size:.9rem">
            <span class="text-accent" style="font-family:var(--font-display);min-width:1.5rem"><?= (int)$r['amount'] ?>×</span>
            <span class="text-bright"><?= e($r['name']) ?></span>
          </div>
          <?php endforeach; ?>
          <?php if ($fillRole): ?>
          <div class="flex gap-sm" style="font-size:.9rem">
            <span class="text-dim" style="font-family:var(--font-display);min-width:1.5rem">
              <?= $playerCount > 0 ? max(0,$fillCount) : '?' ?>×
            </span>
            <span class="text-dim"><?= e($fillRole['name']) ?> (Auffüllung)</span>
          </div>
          <?php endif; ?>
        </div>

        <?php if ($specialCount > $playerCount && $playerCount > 0): ?>
          <div class="alert alert--error">
            ⚠️ Sonderrollen (<?= $specialCount ?>) übersteigen Spieleranzahl (<?= $playerCount ?>).<br>
            Bitte unter „Rollen verwalten" die Anzahlen anpassen oder mehr Spieler hinzufügen.
          </div>
        <?php elseif ($playerCount < MIN_PLAYERS): ?>
          <div class="alert alert--warn">
            Mindestens <?= MIN_PLAYERS ?> Spieler erforderlich (aktuell: <?= $playerCount ?>).
          </div>
        <?php else: ?>
          <div class="alert alert--success">
            ✓ <?= $playerCount ?> Spieler bereit &mdash; <?= $specialCount ?> Sonderrollen + <?= $fillCount ?> <?= $fillRole ? e($fillRole['name']) : 'ohne Rolle' ?>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php
    return trim(ob_get_clean());
}
}

if (!function_exists('admin_render_voting')) {
function admin_render_voting(array $s): string {
    extract($s);
    ob_start();
    ?>
    <?php if ($game['status']==='running'): ?>
    <div class="card animate-in" style="animation-delay:.1s">
      <div class="section-title">🏛️ Bürgerversammlung</div>
      <?php if (empty($votes)): ?>
        <p class="text-dim text-sm">Noch keine Anklagen eingereicht.</p>
      <?php else:
        $total   = array_sum(array_column($votes,'cnt'));
        $topVote = $votes[0];
        $accusedId   = $topVote['target_id'];
        $accusedName = $playerNames[$accusedId] ?? '?';
      ?>
        <!-- Angeklagter -->
        <div class="panel mb-2" style="border:1px solid var(--danger-text,#f87171);padding:1rem;text-align:center">
          <div class="text-dim text-xs mb-1" style="letter-spacing:.08em;text-transform:uppercase">Angeklagter</div>
          <div style="font-family:var(--font-display);font-size:1.35rem;color:var(--danger-text,#f87171)">
            <?= e($accusedName) ?>
          </div>
          <div class="text-dim text-xs mt-1"><?= $topVote['cnt'] ?> von <?= $total ?> Stimmen</div>
        </div>

        <!-- Stimmenverteilung -->
        <?php if (count($votes) > 1): ?>
        <div style="margin-bottom:.85rem">
          <?php foreach ($votes as $v): ?>
          <div style="margin-bottom:.3rem">
            <div class="flex-between" style="font-size:.82rem">
              <span style="font-family:var(--font-display)"><?= e($playerNames[$v['target_id']]??'?') ?></span>
              <span class="text-dim"><?= $v['cnt'] ?>/<?= $total ?></span>
            </div>
            <div style="height:3px;background:var(--border);border-radius:2px;margin-top:.2rem;overflow:hidden">
              <div style="height:100%;width:<?= round($v['cnt']/$total*100) ?>%;background:var(--danger-text,#f87171);border-radius:2px"></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Urteil -->
        <div class="flex gap-sm" style="margin-top:.5rem">
          <button class="btn btn--danger" style="flex:1"
                  onclick="hangAccused(<?= $accusedId ?>, '<?= e(addslashes($accusedName)) ?>')">
            ⚖️ Hängen
          </button>
          <button class="btn btn--ghost" style="flex:1"
                  onclick="freeAccused('<?= e(addslashes($accusedName)) ?>')">
            ✓ Frei lassen
          </button>
        </div>
      <?php endif; ?>
      <div id="action-result-assembly" class="mt-2"></div>
    </div>
    <?php endif; ?>
    <?php
    return trim(ob_get_clean());
}
}

if (!function_exists('admin_render_kill_quick')) {
function admin_render_kill_quick(array $s): string {
    extract($s);
    ob_start();
    ?>
    <?php if ($game['status']==='running'): ?>
    <div class="card animate-in mt-2" style="animation-delay:.14s">
      <div class="section-title">Spieler als tot melden</div>
      <div class="flex gap-sm kill-controls">
        <select id="kill-pid" class="form-input" style="flex:1">
          <option value="">Spieler wählen…</option>
          <?php foreach (array_filter($gamePlayers, fn($p)=>$p['is_alive']) as $gp): ?>
          <option value="<?= (int)$gp['player_id'] ?>"><?= e($gp['username']) ?></option>
          <?php endforeach; ?>
        </select>
        <select id="kill-cause" class="form-input" style="width:auto">
          <option value="killer">🔪 Mordwaffe</option>
          <option value="vote">⚖️ Erhängt</option>
          <option value="other">💀 Sonstiges</option>
        </select>
        <button class="btn btn--danger" onclick="manualKill()">☠</button>
      </div>
    </div>
    <?php endif; ?>
    <?php
    return trim(ob_get_clean());
}
}
