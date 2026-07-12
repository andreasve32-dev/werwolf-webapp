<?php
// Copyright (c) 2026 Andreas Vetter
// Debug-Werkzeuge für die Spielleitung — nur sichtbar/nutzbar wenn APP_DEBUG an ist.
// Eigene Seite statt inline im Dashboard, damit die Spielleitung übersichtlich bleibt.
// Aufbau als Akkordeon wie die Server-Einstellungen: jeder Block klappt einzeln
// auf, immer nur einer offen. System-Log steht oben und startet aufgeklappt.
require_once dirname(__DIR__) . '/core/bootstrap.php';
require_once TEMPLATE_PATH . '/admin_dashboard_blocks.php';
require_once TEMPLATE_PATH . '/testplayers_blocks.php';
Auth::requireAdmin();

$game   = currentGame();
$gameId = $game['id'] ?? null;
$state  = $gameId ? admin_compute_state($gameId) : [];
extract($state);

// Klappbarer Sektions-Header (wie settingsAccHead in admin/settings.php).
// $badge ist bereits fertiges, sicheres HTML (nur ints/feste Strings).
function debugAccHead(string $icon, string $title, string $badge = '', bool $open = false): void {
    ?>
    <button type="button" class="debug-acc__head" aria-expanded="<?= $open ? 'true' : 'false' ?>" onclick="toggleDebugAcc(this)">
      <span class="debug-acc__title"><?= $icon ?> <?= e($title) ?><?= $badge ?></span>
      <span class="debug-acc__chevron">▾</span>
    </button>
    <?php
}

$page = ['title' => 'Debug-Menü'];
require TEMPLATE_PATH . '/base.php';
?>

<style>
.debug-acc__head {
  display: flex; align-items: center; justify-content: space-between; gap: .75rem;
  width: 100%; padding: 0; margin: 0;
  background: none; border: none; cursor: pointer;
  text-align: left; font-family: inherit;
}
.debug-acc__title {
  font-family: var(--font-display, inherit);
  font-size: 1.05rem; font-weight: 600; color: var(--text-bright);
  display: flex; align-items: center; gap: .5rem; flex-wrap: wrap;
}
.debug-acc__head[aria-expanded="true"] .debug-acc__title { color: var(--accent); }
.debug-acc__chevron {
  flex-shrink: 0; font-size: .75rem; color: var(--text-dim);
  transition: transform .22s ease;
}
.debug-acc__head[aria-expanded="true"] .debug-acc__chevron { transform: rotate(180deg); }
.debug-acc__body { margin-top: 1rem; }
/* Von Render-Funktionen (Testspieler, Tot melden) erzeugte innere .card in der
   Accordion-Body optisch abflachen — ihr eigener Titel entfällt (der Kopf zeigt ihn). */
.debug-acc__body > .card {
  border: none !important; background: none !important; box-shadow: none !important;
  padding: 0 !important; margin: 0 !important; animation: none !important;
}
.debug-acc__body > .card > .section-title:first-child { display: none; }
.debug-badge { font-size: .72rem; font-weight: 700; }
</style>

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
  <?php else: ?>

  <!-- ── System-Log (immer verfügbar, startet aufgeklappt) ────────── -->
  <?php
    $__logEntries = logParse(LOG_PATH);
    $__logMeta    = logLevelMeta();
    $__logCrit    = 0; $__logErr = 0;
    foreach ($__logEntries as $__le) {
        if ($__le['level'] === 'CRITICAL') $__logCrit++;
        elseif ($__le['level'] === 'ERROR') $__logErr++;
    }
    $__logTotal = count($__logEntries);
    // Badge im Kopf: Anzahl kritischer/Fehler-Einträge auch bei zugeklapptem Block sichtbar
    $__logBadge = '';
    if ($__logCrit > 0 || $__logErr > 0) {
        $__parts = [];
        if ($__logCrit > 0) $__parts[] = '<span class="debug-badge" style="color:' . $__logMeta['CRITICAL']['color'] . '">⛔ ' . $__logCrit . '</span>';
        if ($__logErr  > 0) $__parts[] = '<span class="debug-badge" style="color:' . $__logMeta['ERROR']['color'] . '">❌ ' . $__logErr . '</span>';
        $__logBadge = ' ' . implode(' ', $__parts);
    }
  ?>
  <div class="card card--glow animate-in mb-2" style="border-color:rgba(148,163,184,.35)">
    <?php debugAccHead('📜', 'System-Log', $__logBadge, true); ?>
    <div class="debug-acc__body">
      <p class="text-dim text-xs mb-2">
        Aufgezeichnete Fehler &amp; Ereignisse durchsuchen — nach Schweregrad geordnet
        (kritisch → Info). Aktuell <strong><?= $__logTotal ?></strong> Einträge<?php
          if ($__logCrit > 0 || $__logErr > 0): ?>, davon
          <?php if ($__logCrit > 0): ?><span style="color:<?= $__logMeta['CRITICAL']['color'] ?>">⛔ <?= $__logCrit ?> kritisch</span><?php endif; ?>
          <?php if ($__logCrit > 0 && $__logErr > 0): ?> · <?php endif; ?>
          <?php if ($__logErr > 0): ?><span style="color:<?= $__logMeta['ERROR']['color'] ?>">❌ <?= $__logErr ?> Fehler</span><?php endif; ?>
        <?php endif; ?>.
      </p>
      <a href="<?= APP_URL ?>/admin/logs.php" class="btn btn--primary btn--sm">📜 Log öffnen</a>
      <?php if ($__logCrit > 0): ?>
      <a href="<?= APP_URL ?>/admin/logs.php?level=CRITICAL&sort=severity" class="btn btn--ghost btn--sm"
         style="color:<?= $__logMeta['CRITICAL']['color'] ?>">⛔ Nur kritische</a>
      <?php endif; ?>
    </div>
  </div>

  <?php if (!$game || $game['status'] !== 'running'): ?>
  <div class="alert alert--warn mb-2">
    Kein laufendes Spiel — die spielbezogenen Debug-Werkzeuge (Rolle, Rollen ansehen,
    Cooldown, Tod/Wiederbeleben) sind nur während eines laufenden Spiels verfügbar.
    Die Testspieler-Verwaltung unten geht unabhängig davon jederzeit.
  </div>
  <?php else: ?>

  <!-- ── Eigene Rolle wählen ──────────────────────────────────────── -->
  <div class="card card--glow animate-in mb-2" style="border-color:rgba(251,191,36,.35);background:rgba(251,191,36,.04)">
    <?php debugAccHead('🎭', 'Eigene Rolle wählen'); ?>
    <div class="debug-acc__body" hidden>
      <p class="text-dim text-xs mb-2">
        Setzt deine eigene Rolle im laufenden Spiel sofort.
        Aktuell: <span id="debug-current-role-label"><?php if ($adminGameEntry['role_name'] ?? null): ?><strong style="color:var(--text-bright)"><?= e($adminGameEntry['role_name']) ?></strong><?php else: ?><em>keine Rolle</em><?php endif; ?></span>
      </p>
      <div class="flex gap-sm mb-2">
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
      <div id="debug-role-result" class="mb-1"></div>
      <button class="btn btn--ghost btn--sm" style="border-color:rgba(251,191,36,.4);color:#fbbf24"
              onclick="debugResetCooldown()">⏱️ Cooldown zurücksetzen</button>
      <div id="debug-cooldown-result" class="mt-1"></div>
    </div>
  </div>

  <!-- ── Spieler als tot melden ───────────────────────────────────── -->
  <div class="card card--glow animate-in mb-2" style="border-color:rgba(251,191,36,.35);background:rgba(251,191,36,.04)">
    <?php debugAccHead('☠', 'Spieler als tot melden'); ?>
    <div class="debug-acc__body" hidden>
      <?= admin_render_kill_quick($state) ?>
    </div>
  </div>

  <!-- ── Alle Rollen ansehen ──────────────────────────────────────── -->
  <?php
    // Wie der Dorfbewohner-Block im Spielfenster (.player-grid/.player-card),
    // nur dass hier bewusst ALLE echten Rollen direkt sichtbar sind — ignoriert
    // alle normalen Sichtbarkeitsregeln, nur für dich als Debug-Werkzeug.
    $__rolesById = array_column(allRoles(), null, 'id');
  ?>
  <div class="card card--glow animate-in mb-2" style="border-color:rgba(251,191,36,.35);background:rgba(251,191,36,.04)">
    <?php debugAccHead('🃏', 'Alle Rollen ansehen'); ?>
    <div class="debug-acc__body" hidden>
      <p class="text-dim text-xs mb-2">
        Zeigt die echten Rollen aller Spieler auf einen Blick — ignoriert bewusst alle
        normalen Sichtbarkeitsregeln, nur für dich als Debug-Werkzeug sichtbar.
      </p>
      <div class="player-grid">
        <?php foreach ($gamePlayers as $gp):
          $r = $gp['role_id'] ? ($__rolesById[(int)$gp['role_id']] ?? null) : null;
          $r = $r ?: roleFallback();
          $dead = !$gp['is_alive'];
        ?>
        <div class="player-card<?= $dead ? ' player-card--dead' : '' ?>" style="cursor:default">
          <?php if ($dead): ?><span class="player-card__skull">💀</span><?php endif; ?>
          <?= roleIconHtml($r, 'lg') ?>
          <div class="player-card__name"><?= e($gp['display_name']) ?></div>
          <div class="player-card__role"><?= e($r['name']) ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- ── Tote wiederbeleben ───────────────────────────────────────── -->
  <div class="card animate-in mb-2" style="border-color:rgba(251,191,36,.35);background:rgba(251,191,36,.04)">
    <?php debugAccHead('🔮', 'Tote wiederbeleben'); ?>
    <div class="debug-acc__body" hidden>
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
                  onclick="revivePlayer(<?= (int)$gp['player_id'] ?>,'<?= e($gp['display_name']) ?>')">🔮 Wiederbeleben</button>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <?php endif; ?>

  <!-- ── Testspieler (immer verfügbar, standardmäßig eingeklappt) ──── -->
  <div class="card card--glow animate-in mb-2" style="border-color:rgba(251,191,36,.35);background:rgba(251,191,36,.04)">
    <?php debugAccHead('🤖', 'Testspieler'); ?>
    <div class="debug-acc__body" hidden>
      <?= admin_render_testplayers() ?>
    </div>
  </div>

  <?php endif; ?>

</div>

<?php
$page['inline_js'] = sprintf(
    'const API_BASE = %s; const GAME_ID = %s; const TP_API = %s;',
    json_encode(API_URL), json_encode($gameId), json_encode(APP_URL . '/admin/testplayers.php')
);
$page['inline_js'] .= <<<'JS'

// Akkordeon wie in den Server-Einstellungen: immer nur EIN Block offen.
function toggleDebugAcc(btn) {
  const body = btn.nextElementSibling;
  const open = btn.getAttribute('aria-expanded') === 'true';
  if (!open) {
    document.querySelectorAll('.debug-acc__head[aria-expanded="true"]').forEach(other => {
      if (other !== btn) {
        other.setAttribute('aria-expanded', 'false');
        other.nextElementSibling.hidden = true;
      }
    });
  }
  btn.setAttribute('aria-expanded', String(!open));
  body.hidden = open;
}

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

async function debugResetCooldown() {
  const res = document.getElementById('debug-cooldown-result');
  const r = await apiFetch(API_BASE+'/admin.php', {action:'debug_reset_cooldown', game_id:GAME_ID});
  if (r.error === 'session_expired') return;
  if (r.ok) res.innerHTML = `<div class="alert alert--success">${escHtml(r.message||'Cooldown zurückgesetzt')}</div>`;
  else res.innerHTML = `<div class="alert alert--error">${escHtml(r.error||'Fehler')}</div>`;
}

async function manualKill(){
  const pid=document.getElementById('kill-pid').value;
  const cause=document.getElementById('kill-cause').value;
  if(!pid){showToast('Spieler wählen!','error');return;}
  const r=await apiFetch(API_BASE+'/admin.php',{action:'kill_player',game_id:GAME_ID,player_id:parseInt(pid),cause});
  if(r.error==='session_expired')return;
  if(r.ok){showToast(r.message||'Als tot markiert','success');location.reload();}
  else showToast(r.error||'Fehler','error');
}

// ── Testspieler ────────────────────────────────────────────────
function tpUpdateCounts(delta) {
  const cur = document.getElementById('tp-cur-count');
  const lc  = document.getElementById('tp-list-count');
  if (cur) cur.textContent = Math.max(0, parseInt(cur.textContent) + delta);
  if (lc)  lc.textContent  = Math.max(0, parseInt(lc.textContent)  + delta);
  const count = parseInt((cur || lc).textContent);
  const dab = document.getElementById('tp-delete-all-btn');
  if (dab) dab.style.display = count > 0 ? '' : 'none';
  const hint = document.getElementById('tp-empty-hint');
  if (hint) hint.style.display = count === 0 ? '' : 'none';
}

function tpRowHtml(p) {
  const row = document.createElement('div');
  row.className = 'panel';
  row.id = 'tp-' + p.id;
  row.style.cssText = 'padding:.5rem .8rem;display:flex;justify-content:space-between;align-items:center;gap:.5rem;flex-wrap:wrap';
  row.innerHTML =
    '<div class="flex gap-sm" style="align-items:center">' +
      '<span style="font-family:var(--font-display);font-size:.88rem;color:var(--text-bright)">' + escHtml(p.display_name) + '</span>' +
      '<span class="text-dim text-xs">Login: <code style="font-family:monospace">' + escHtml(p.username) + '</code></span>' +
    '</div>' +
    '<button type="button" class="btn btn--ghost btn--sm" title="Löschen">🗑</button>';
  row.querySelector('button').addEventListener('click', () => tpDeleteOne(p.id, p.display_name));
  return row;
}

async function tpCreatePlayers() {
  const count  = parseInt(document.getElementById('tp-create-count').value) || 1;
  const result = document.getElementById('tp-create-result');
  const fd = new FormData();
  fd.append('count', count);
  try {
    const data = await (await fetch(TP_API + '?action=create', {method: 'POST', body: fd})).json();
    result.style.display = '';
    if (data.ok) {
      const msg = data.created > 0
        ? '✓ ' + data.created + ' Testspieler angelegt' + (data.skipped > 0 ? ' (' + data.skipped + ' vorhandene übersprungen).' : '.')
        : 'Kein Platz mehr — das Maximum an Testspielern ist bereits angelegt.';
      result.innerHTML = `<div class="alert alert--success" style="padding:.35rem .8rem;font-size:.84rem">${escHtml(msg)}</div>`;
      if (data.created > 0) {
        const list = document.getElementById('tp-list');
        (data.rows || []).forEach(p => list.appendChild(tpRowHtml(p)));
        tpUpdateCounts(data.created);
      }
    } else {
      result.innerHTML = `<div class="alert alert--error" style="padding:.35rem .8rem;font-size:.84rem">${escHtml(data.error || 'Fehler')}</div>`;
    }
  } catch(e) {
    result.style.display = '';
    result.innerHTML = '<div class="alert alert--error" style="padding:.35rem .8rem;font-size:.84rem">Netzwerkfehler.</div>';
  }
}

async function tpDeleteOne(pid, name) {
  const fd = new FormData();
  fd.append('player_id', pid);
  try {
    const data = await (await fetch(TP_API + '?action=delete', {method: 'POST', body: fd})).json();
    if (data.ok) {
      const row = document.getElementById('tp-' + pid);
      if (row) { row.style.opacity = '0'; row.style.transition = 'opacity .25s'; setTimeout(() => row.remove(), 260); }
      tpUpdateCounts(-1);
      showToast('„' + name + '" gelöscht.', 'success');
    } else {
      showToast(data.error || 'Fehler', 'error');
    }
  } catch(e) { showToast('Netzwerkfehler.', 'error'); }
}

async function tpDeleteAll() {
  const count = parseInt(document.getElementById('tp-list-count').textContent);
  if (!count || !confirm(count + ' Testspieler wirklich alle löschen?')) return;
  try {
    const data = await (await fetch(TP_API + '?action=delete_all', {method: 'POST'})).json();
    if (data.ok) {
      document.querySelectorAll('#tp-list [id^="tp-"]').forEach(el => el.remove());
      tpUpdateCounts(-count);
      showToast('Alle Testspieler gelöscht.', 'success');
    } else {
      showToast(data.error || 'Fehler', 'error');
    }
  } catch(e) { showToast('Netzwerkfehler.', 'error'); }
}
JS;
require TEMPLATE_PATH . '/base_end.php';
