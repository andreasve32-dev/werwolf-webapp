<?php
// Copyright (c) 2026 Andreas Vetter
// Wiederverwendbare Render-Funktionen für deaths.php — genutzt sowohl beim
// initialen Seitenaufbau als auch von api/game.php (get_deaths) fürs Polling.

if (!function_exists('deaths_compute_state')) {
function deaths_compute_state(?int $gameId, array $curPlayer): array {
    $deaths = $gameId ? Database::query(
        "SELECT d.*, p.display_name AS username FROM deaths d JOIN players p ON p.id=d.player_id WHERE d.game_id=? ORDER BY d.died_at",
        [$gameId]
    ) : [];

    $canBefragen = (bool)$curPlayer['is_admin'];
    if (!$canBefragen && $gameId) {
        $myGPRole = Database::queryOne(
            "SELECT r.befragen FROM game_players gp LEFT JOIN roles r ON r.id=gp.role_id WHERE gp.game_id=? AND gp.player_id=?",
            [$gameId, $curPlayer['id']]
        );
        $canBefragen = !empty($myGPRole['befragen']);
    }

    // Bin ich tot in diesem Spiel?
    $myDeath = null;
    foreach ($deaths as $d) {
        if ((int)$d['player_id'] === (int)$curPlayer['id']) { $myDeath = $d; break; }
    }
    $amDead = $myDeath !== null;

    // Gibt es noch lebende Spieler mit befragen=1 in diesem Spiel?
    $hasBefragenRole = false;
    if ($gameId && $amDead) {
        $bq = Database::queryOne(
            "SELECT 1 FROM game_players gp JOIN roles r ON r.id=gp.role_id
             WHERE gp.game_id=? AND r.befragen=1 AND gp.is_alive=1 LIMIT 1",
            [$gameId]
        );
        $hasBefragenRole = !empty($bq);
    }
    // Spalten-Wrapper: solange tot + Befragen-Rolle lebt (Spalte bleibt für Colspan-Konsistenz)
    $showEintragenCol = $amDead && $hasBefragenRole;

    return compact('deaths', 'canBefragen', 'showEintragenCol');
}
}

if (!function_exists('render_deaths_content')) {
function render_deaths_content(array $ctx): string {
    extract($ctx);
    $curPlayer = Auth::player();
    ob_start();
    ?>
    <?php if (empty($deaths)): ?>
    <div class="card card--glow text-center animate-in" style="padding:3rem">
      <span style="font-size:3rem;display:block;margin-bottom:1rem">🕊️</span>
      <h3><?= e(DEATHS_EMPTY_TITLE) ?></h3>
      <p class="text-dim mt-1 italic"><?= e(DEATHS_EMPTY_SUB) ?></p>
    </div>

    <?php else: ?>

    <!-- Stats -->
    <?php $byVote = count(array_filter($deaths, fn($d) => !empty($d['is_gehenkt']))); ?>
    <div class="grid-2 mb-2">
      <div class="card text-center animate-in">
        <div style="font-size:2rem">☠️</div>
        <div style="font-family:var(--font-display);font-size:1.8rem;color:var(--accent)"><?= count($deaths) ?></div>
        <div class="text-dim text-sm">Tote gesamt</div>
      </div>
      <div class="card text-center animate-in" style="animation-delay:.05s">
        <div style="font-size:2rem">⚖️</div>
        <div style="font-family:var(--font-display);font-size:1.8rem;color:var(--accent)"><?= $byVote ?></div>
        <div class="text-dim text-sm">Gehenkt</div>
      </div>
    </div>

    <!-- Tabelle -->
    <div class="card animate-in" style="animation-delay:.12s">
      <div class="section-title">Chronik der Gefallenen</div>
      <div style="overflow-x:auto">
        <table class="table">
          <thead>
            <tr>
              <th>Spieler</th>
              <?php if ($showEintragenCol): ?><th></th><?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($deaths as $d):
              $r         = role($d['role_id']);
              $isMyDeath = (int)$d['player_id'] === (int)$curPlayer['id'];
              $dOrt      = $d['ort']  ?? null;
              $dZeit     = $d['zeit'] ?? null;
              // Fallback für den Modal-Vorbefüller: gespeicherte Zeit oder died_at aus DB
              $defaultZeit = $dZeit ?: ($d['died_at'] ? date('H:i', strtotime($d['died_at'])) : '');
            ?>
            <tr class="animate-in">
              <td>
                <div class="flex gap-sm" style="align-items:center">
                  <span style="font-size:1.3rem">💀</span>
                  <span style="font-family:var(--font-display)"><?= e($d['username']) ?></span>
                </div>
              </td>
              <?php if ($showEintragenCol): ?>
              <td style="text-align:right;white-space:nowrap">
                <?php if ($isMyDeath && empty($d['rolle_aufgedeckt'])): ?>
                <button class="btn btn-sm btn-ghost"
                        data-death-id="<?= (int)$d['id'] ?>"
                        data-ort="<?= e($dOrt ?? '') ?>"
                        data-zeit="<?= e($defaultZeit) ?>"
                        data-rolle-name="<?= e($r['name']) ?>"
                        onclick="openEintragen(this)"
                        style="font-size:.8rem">
                  📋 Eintragen
                </button>
                <?php endif; ?>
              </td>
              <?php endif; ?>
            </tr>
            <?php
            // Sub-Zeile: für alle sichtbar wenn aufgedeckt; nur für Befragen/Admin wenn noch nicht
            $showSubrow = !empty($d['rolle_aufgedeckt']) || $canBefragen;
            $subColspan = $showEintragenCol ? 2 : 1;
            ?>
            <?php if ($showSubrow): ?>
            <tr style="background:rgba(0,0,0,.12)">
              <td colspan="<?= $subColspan ?>"
                  style="padding:.5rem 1rem .8rem 2.8rem;border-top:none">
                <div style="display:flex;flex-wrap:wrap;gap:1.4rem;font-size:.85rem;align-items:center">
                  <?php if (!empty($d['rolle_aufgedeckt'])): ?>
                  <div>
                    <span class="text-dim" style="font-size:.75rem;text-transform:uppercase;letter-spacing:.05em">Rolle</span><br>
                    <span class="role-badge" style="margin-top:.25rem">
                      <?= roleIconHtml($r, 'sm') ?> <?= e($r['name']) ?>
                    </span>
                  </div>
                  <div>
                    <span class="text-dim" style="font-size:.75rem;text-transform:uppercase;letter-spacing:.05em">Ort</span><br>
                    <span style="margin-top:.25rem;display:block">
                      <?= $dOrt ? e($dOrt) : '<span style="opacity:.35">–</span>' ?>
                    </span>
                  </div>
                  <div style="margin-left:auto;text-align:right">
                    <span class="text-dim" style="font-size:.75rem;text-transform:uppercase;letter-spacing:.05em">Zeit</span><br>
                    <span style="margin-top:.25rem;display:block;font-family:var(--font-display);font-size:1rem">
                      <?= $dZeit ? e($dZeit) : '<span style="opacity:.35">–</span>' ?>
                    </span>
                  </div>
                  <?php else: ?>
                  <span class="text-dim" style="font-size:.8rem;font-style:italic;opacity:.5">Noch nicht befragt</span>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
            <?php endif; ?>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Friedhof -->
    <div class="card mt-2 text-center animate-in" style="animation-delay:.18s;padding:2rem">
      <div style="font-size:1.6rem;letter-spacing:.35rem;color:var(--text-dim)">
        <?php foreach ($deaths as $d): ?><span title="<?= e($d['username']) ?>">🪦</span><?php endforeach; ?>
      </div>
      <p class="text-dim text-sm mt-1 italic"><?= e(DEATHS_PEACE_TEXT) ?></p>
    </div>

    <?php endif; ?>
    <?php
    return trim(ob_get_clean());
}
}
