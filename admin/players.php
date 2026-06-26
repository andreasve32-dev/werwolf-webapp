<?php
// Copyright (c) 2026 Andreas Vetter
require_once dirname(__DIR__) . '/core/bootstrap.php';
Auth::requireAdmin();

$myId = Auth::player()['id'];

// ── AJAX: Passwort setzen ─────────────────────────────────
if (($_GET['action'] ?? '') === 'set_password') {
    header('Content-Type: application/json');
    $pid      = (int)($_POST['player_id'] ?? 0);
    $password = $_POST['password'] ?? '';
    if (!$pid || mb_strlen($password) < 6) {
        echo json_encode(['ok' => false, 'error' => 'Passwort muss mindestens 6 Zeichen haben.']); exit;
    }
    if (!Database::queryOne('SELECT id FROM players WHERE id = ?', [$pid])) {
        echo json_encode(['ok' => false, 'error' => 'Spieler nicht gefunden.']); exit;
    }
    Database::execute('UPDATE players SET password_hash = ? WHERE id = ?', [hashPassword($password), $pid]);
    echo json_encode(['ok' => true]); exit;
}

// ── AJAX: Spielername umbenennen ──────────────────────────
if (($_GET['action'] ?? '') === 'rename') {
    header('Content-Type: application/json');
    $pid          = (int)($_POST['player_id'] ?? 0);
    $display_name = trim($_POST['display_name'] ?? '');
    if (!$pid || mb_strlen($display_name) < 2 || mb_strlen($display_name) > 30) {
        echo json_encode(['ok' => false, 'error' => 'Name muss 2–30 Zeichen haben.']); exit;
    }
    if (!preg_match('/^[a-zA-Z0-9_\-äöüÄÖÜß ]+$/u', $display_name)) {
        echo json_encode(['ok' => false, 'error' => 'Ungültige Zeichen im Namen.']); exit;
    }
    $conflict = Database::queryOne('SELECT id FROM players WHERE display_name = ? AND id != ?', [$display_name, $pid]);
    if ($conflict) {
        echo json_encode(['ok' => false, 'error' => 'Dieser Spielername ist bereits vergeben.']); exit;
    }
    Database::execute('UPDATE players SET display_name = ? WHERE id = ?', [$display_name, $pid]);
    echo json_encode(['ok' => true]); exit;
}

// ── AJAX: Spieler löschen ─────────────────────────────────
if (($_GET['action'] ?? '') === 'delete') {
    header('Content-Type: application/json');
    $pid = (int)($_POST['player_id'] ?? 0);
    if (!$pid) {
        echo json_encode(['ok' => false, 'error' => 'Ungültige ID.']); exit;
    }
    if ($pid === $myId) {
        echo json_encode(['ok' => false, 'error' => 'Du kannst dich nicht selbst löschen.']); exit;
    }
    Database::execute('DELETE FROM players WHERE id = ?', [$pid]);
    echo json_encode(['ok' => true]); exit;
}

$players = Database::query('SELECT id, username, display_name, is_admin FROM players ORDER BY display_name');

$page = ['title' => 'Spieler verwalten'];
require TEMPLATE_PATH . '/base.php';
?>

<div class="container page-wrap">

  <div class="page-header">
    <span class="page-header__icon">👥</span>
    <h1>Spieler verwalten</h1>
    <p class="page-header__sub">
      <a href="<?= APP_URL ?>/admin/">← zurück zur Spielleitung</a>
    </p>
  </div>

  <div class="card card--glow animate-in">
    <div class="section-title">Alle Spieler (<?= count($players) ?>)</div>

    <div style="display:flex;flex-direction:column;gap:.5rem" id="player-list">
      <?php foreach ($players as $p): ?>
      <div class="panel" id="row-<?= (int)$p['id'] ?>" style="padding:.7rem 1rem">

        <!-- Anzeige-Zeile -->
        <div class="flex-between flex-wrap gap-sm" id="view-<?= (int)$p['id'] ?>">
          <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap">
            <span style="font-family:var(--font-display);font-size:.95rem;color:var(--text-bright)">
              <?= e($p['display_name']) ?>
            </span>
            <span class="text-dim text-xs">
              Login: <code style="font-family:monospace"><?= e($p['username']) ?></code>
            </span>
            <?php if ($p['is_admin']): ?>
              <span class="tag tag--running" style="font-size:.65rem">Admin</span>
            <?php endif; ?>
          </div>
          <div class="flex gap-xs">
            <button class="btn btn--ghost btn--sm" title="Namen ändern"
                    onclick="openRename(<?= (int)$p['id'] ?>, '<?= e(addslashes($p['display_name'])) ?>')">
              ✏️
            </button>
            <?php if ((int)$p['id'] !== $myId): ?>
            <button class="btn btn--danger btn--sm" title="Spieler löschen"
                    onclick="deletePlayer(<?= (int)$p['id'] ?>, '<?= e(addslashes($p['display_name'])) ?>')">
              🗑
            </button>
            <?php endif; ?>
          </div>
        </div>

        <!-- Umbenennen-Formular (versteckt) -->
        <div id="rename-<?= (int)$p['id'] ?>" style="display:none;margin-top:.5rem">
          <div class="flex gap-xs" style="align-items:center;flex-wrap:wrap">
            <input class="form-input" type="text"
                   id="rename-input-<?= (int)$p['id'] ?>"
                   value="<?= e($p['display_name']) ?>"
                   maxlength="30"
                   style="width:200px;font-size:.85rem;padding:.35rem .7rem"
                   onkeydown="if(event.key==='Enter')saveRename(<?= (int)$p['id'] ?>);if(event.key==='Escape')closeRename(<?= (int)$p['id'] ?>)">
            <button class="btn btn--primary btn--sm" onclick="saveRename(<?= (int)$p['id'] ?>)">✓ Speichern</button>
            <button class="btn btn--ghost btn--sm"   onclick="closeRename(<?= (int)$p['id'] ?>)">Abbrechen</button>
          </div>
        </div>

        <!-- Passwort-Zeile -->
        <div style="margin-top:.5rem;display:flex;gap:.5rem;align-items:center;flex-wrap:wrap">
          <input class="form-input" type="password"
                 id="pw-<?= (int)$p['id'] ?>"
                 placeholder="Neues Passwort (min. 6 Zeichen)"
                 style="width:220px;font-size:.85rem;padding:.35rem .7rem"
                 autocomplete="new-password"
                 onkeydown="if(event.key==='Enter')setPassword(<?= (int)$p['id'] ?>, '<?= e(addslashes($p['display_name'])) ?>')">
          <button class="btn btn--secondary btn--sm"
                  onclick="setPassword(<?= (int)$p['id'] ?>, '<?= e(addslashes($p['display_name'])) ?>')">
            🔑 Passwort setzen
          </button>
        </div>

        <div id="pw-result-<?= (int)$p['id'] ?>" style="margin-top:.35rem;display:none"></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

</div>

<?php
$page['inline_js'] = sprintf('const PW_API = %s;', json_encode(APP_URL . '/admin/players.php'));
$page['inline_js'] .= <<<'JS'

// ── Passwort setzen ──────────────────────────────────────
async function setPassword(pid, name) {
  const input  = document.getElementById('pw-' + pid);
  const result = document.getElementById('pw-result-' + pid);
  const pw     = input.value.trim();
  if (pw.length < 6) {
    showResult(result, false, 'Mindestens 6 Zeichen erforderlich.'); return;
  }
  const fd = new FormData();
  fd.append('player_id', pid);
  fd.append('password',  pw);
  try {
    const data = await (await fetch(PW_API + '?action=set_password', {method:'POST',body:fd})).json();
    showResult(result, data.ok, data.ok ? ('✓ Passwort für <strong>' + escHtml(name) + '</strong> gesetzt.') : (data.error || 'Fehler'));
    if (data.ok) { input.value = ''; setTimeout(() => { result.style.display='none'; }, 3000); }
  } catch(e) { showResult(result, false, 'Netzwerkfehler.'); }
}

// ── Umbenennen ───────────────────────────────────────────
function openRename(pid, currentName) {
  document.getElementById('rename-' + pid).style.display = '';
  const inp = document.getElementById('rename-input-' + pid);
  inp.value = currentName;
  setTimeout(() => { inp.focus(); inp.select(); }, 50);
}
function closeRename(pid) {
  document.getElementById('rename-' + pid).style.display = 'none';
}
async function saveRename(pid) {
  const inp    = document.getElementById('rename-input-' + pid);
  const result = document.getElementById('pw-result-' + pid);
  const name   = inp.value.trim();
  if (name.length < 2) { showResult(result, false, 'Name zu kurz.'); return; }
  const fd = new FormData();
  fd.append('player_id',    pid);
  fd.append('display_name', name);
  try {
    const data = await (await fetch(PW_API + '?action=rename', {method:'POST',body:fd})).json();
    if (data.ok) {
      // Name in der Anzeige sofort aktualisieren
      const viewEl = document.querySelector('#view-' + pid + ' span[style*="font-display"]');
      if (viewEl) viewEl.textContent = name;
      closeRename(pid);
      showResult(result, true, '✓ Name geändert.');
      setTimeout(() => { result.style.display='none'; }, 2500);
    } else {
      showResult(result, false, data.error || 'Fehler');
    }
  } catch(e) { showResult(result, false, 'Netzwerkfehler.'); }
}

// ── Löschen ──────────────────────────────────────────────
async function deletePlayer(pid, name) {
  if (!confirm('Spieler „' + name + '" wirklich löschen? Diese Aktion kann nicht rückgängig gemacht werden.')) return;
  const fd = new FormData();
  fd.append('player_id', pid);
  try {
    const data = await (await fetch(PW_API + '?action=delete', {method:'POST',body:fd})).json();
    if (data.ok) {
      const row = document.getElementById('row-' + pid);
      if (row) row.remove();
      showToast('Spieler „' + name + '" gelöscht.', 'success');
    } else {
      showToast(data.error || 'Fehler', 'error');
    }
  } catch(e) { showToast('Netzwerkfehler.', 'error'); }
}

// ── Hilfs-Funktion ───────────────────────────────────────
function showResult(el, ok, msg) {
  el.style.display = '';
  el.innerHTML = '<div class="alert alert--' + (ok?'success':'error') + '" style="padding:.3rem .7rem;font-size:.82rem">' + msg + '</div>';
}
JS;
require TEMPLATE_PATH . '/base_end.php';
?>
