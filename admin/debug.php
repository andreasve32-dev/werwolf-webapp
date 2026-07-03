<?php
// Copyright (c) 2026 Andreas Vetter
// Debug-Werkzeuge für die Spielleitung — nur sichtbar/nutzbar wenn APP_DEBUG an ist.
// Eigene Seite statt inline im Dashboard, damit die Spielleitung übersichtlich bleibt.
require_once dirname(__DIR__) . '/core/bootstrap.php';
require_once TEMPLATE_PATH . '/admin_dashboard_blocks.php';
Auth::requireAdmin();

$game   = currentGame();
$gameId = $game['id'] ?? null;
$state  = $gameId ? admin_compute_state($gameId) : [];
extract($state);

$page = ['title' => 'Debug-Menü'];
require TEMPLATE_PATH . '/base.php';
?>

<div class="container page-wrap">

  <div class="page-header">
    <span class="page-header__icon">🐛</span>
    <h1>Debug-Menü</h1>
    <p class="page-header__sub">
      <a href="<?= APP_URL ?>/admin/">← zurück zur Spielleitung</a>
    </p>
  </div>

  <?php if (!APP_DEBUG): ?>
  <div class="alert alert--warn">
    ⚠️ Debug-Modus ist deaktiviert — aktiviere <code>app_debug</code> unter
    <a href="<?= APP_URL ?>/admin/settings.php">Server-Einstellungen</a>, um diese Werkzeuge zu nutzen.
  </div>
  <?php elseif (!$game || $game['status'] !== 'running'): ?>
  <div class="alert alert--warn">
    Kein laufendes Spiel — Debug-Werkzeuge sind nur während eines laufenden Spiels verfügbar.
  </div>
  <?php else: ?>

  <!-- Eigene Rolle wählen -->
  <div class="card card--glow animate-in mb-2" style="border-color:rgba(251,191,36,.35);background:rgba(251,191,36,.04)">
    <div class="section-title" style="color:#fbbf24">🎭 Eigene Rolle wählen</div>
    <p class="text-dim text-xs mb-2">
      Setzt deine eigene Rolle im laufenden Spiel sofort.
      Aktuell: <span id="debug-current-role-label"><?php if ($adminGameEntry['role_name'] ?? null): ?><strong style="color:var(--text-bright)"><?= e($adminGameEntry['role_name']) ?></strong><?php else: ?><em>keine Rolle</em><?php endif; ?></span>
    </p>
    <div class="flex gap-sm">
      <select id="debug-role-select" class="form-input" style="flex:1">
        <option value="">Rolle wählen…</option>
        <?php foreach ($debugRoles as $r): ?>
        <option value="<?= (int)$r['id'] ?>"
          <?= (int)($adminGameEntry['role_id'] ?? 0) === (int)$r['id'] ? 'selected' : '' ?>>
          <?= e($r['name']) ?>
        </option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn--ghost" style="border-color:rgba(251,191,36,.4);color:#fbbf24"
              onclick="debugSetOwnRole()">Setzen</button>
    </div>
    <div id="debug-role-result" class="mt-1"></div>
  </div>

  <!-- Tote wiederbeleben -->
  <div class="card animate-in mb-2" style="animation-delay:.05s;border-color:rgba(251,191,36,.35);background:rgba(251,191,36,.04)">
    <div class="section-title" style="color:#fbbf24">🔮 Tote wiederbeleben</div>
    <p class="text-dim text-xs mb-2">
      Bringt einen Spieler ohne neue Runde zurück ins Spiel — löscht dabei auch seinen Todeslisten-Eintrag.
    </p>
    <?php $deadPlayers = array_filter($gamePlayers, fn($p) => !$p['is_alive']); ?>
    <?php if (empty($deadPlayers)): ?>
    <p class="text-dim text-sm" id="debug-dead-empty">Niemand ist tot.</p>
    <?php endif; ?>
    <div id="debug-dead-list" style="display:flex;flex-direction:column;gap:.4rem">
      <?php foreach ($deadPlayers as $gp): ?>
      <div class="panel flex-between" style="padding:.5rem .8rem" id="debug-dead-row-<?= (int)$gp['player_id'] ?>">
        <span style="font-family:var(--font-display);font-size:.88rem"><?= e($gp['display_name']) ?></span>
        <button class="btn btn--ghost btn--sm"
                onclick="revivePlayer(<?= (int)$gp['player_id'] ?>,'<?= e($gp['username']) ?>')">🔮 Wiederbeleben</button>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <?php endif; ?>

</div>

<?php
$page['inline_js'] = sprintf(
    'const API_BASE = %s; const GAME_ID = %s;',
    json_encode(API_URL), json_encode($gameId)
);
$page['inline_js'] .= <<<'JS'

async function debugSetOwnRole() {
  const sel   = document.getElementById('debug-role-select');
  const res   = document.getElementById('debug-role-result');
  const label = document.getElementById('debug-current-role-label');
  const roleId = parseInt(sel?.value);
  if (!roleId) { showToast('Keine Rolle gewählt', 'error'); return; }
  const r = await apiFetch(API_BASE+'/admin.php', {action:'set_own_role', game_id:GAME_ID, role_id:roleId});
  if (r.error === 'session_expired') return;
  if (r.ok) {
    res.innerHTML = `<div class="alert alert--success">${escHtml(r.message||'Rolle gesetzt')}</div>`;
    if (label && r.role_name) label.textContent = r.role_name;
  } else {
    res.innerHTML = `<div class="alert alert--error">${escHtml(r.error||'Fehler')}</div>`;
  }
}

async function revivePlayer(pid, name) {
  if (!confirm(name + ' wiederbeleben? Der Todeslisten-Eintrag wird dabei gelöscht.')) return;
  const r = await apiFetch(API_BASE+'/admin.php', {action:'revive_player', game_id:GAME_ID, player_id:pid});
  if (r.error === 'session_expired') return;
  if (r.ok) {
    showToast(r.message || (name + ' wiederbelebt'), 'success');
    const row = document.getElementById('debug-dead-row-' + pid);
    if (row) { row.style.opacity='0'; row.style.transition='opacity .3s'; setTimeout(() => row.remove(), 300); }
    const list = document.getElementById('debug-dead-list');
    if (list && !list.querySelector('.panel')) {
      const empty = document.getElementById('debug-dead-empty');
      if (empty) empty.style.display = '';
      else list.insertAdjacentHTML('beforebegin', '<p class="text-dim text-sm">Niemand ist tot.</p>');
    }
  } else {
    showToast(r.error || 'Fehler', 'error');
  }
}
JS;
require TEMPLATE_PATH . '/base_end.php';
?>
