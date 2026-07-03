<?php
// Copyright (c) 2026 Andreas Vetter
require_once dirname(__DIR__) . '/core/bootstrap.php';
Auth::requireAdmin();

const TEST_PREFIX     = 'test_';
const TEST_DISPLAY_PX = 'Testspieler ';
const TEST_PASSWORD   = 'test1234';
const TEST_MAX        = 20;

// Hilfsfunktion: Ist diese Player-Row ein Testspieler?
function isTestPlayer(array $p): bool {
    return (bool)preg_match('/^test_\d{2}$/', $p['username']);
}

// ── AJAX: Testspieler anlegen ─────────────────────────────────
if (($_GET['action'] ?? '') === 'create') {
    header('Content-Type: application/json');
    if (!APP_DEBUG) { echo json_encode(['ok' => false, 'error' => 'Nur im Debug-Modus verfügbar.']); exit; }
    $count = max(1, min(TEST_MAX, (int)($_POST['count'] ?? 1)));
    $hash  = hashPassword(TEST_PASSWORD);
    $created = 0;
    $skipped = 0;
    $rows    = [];
    // Kumulierend: vorhandene Nummern überspringen und die nächsten freien
    // belegen, bis $count NEUE Spieler angelegt sind (oder TEST_MAX erreicht ist).
    for ($i = 1; $i <= TEST_MAX && $created < $count; $i++) {
        $username     = TEST_PREFIX . str_pad($i, 2, '0', STR_PAD_LEFT);
        $display_name = TEST_DISPLAY_PX . str_pad($i, 2, '0', STR_PAD_LEFT);
        $exists = Database::queryOne(
            'SELECT id FROM players WHERE username = ? OR display_name = ?',
            [$username, $display_name]
        );
        if ($exists) { $skipped++; continue; }
        Database::execute(
            'INSERT INTO players (username, display_name, password_hash, is_admin) VALUES (?,?,?,0)',
            [$username, $display_name, $hash]
        );
        $rows[] = ['id' => Database::lastId(), 'username' => $username, 'display_name' => $display_name];
        $created++;
    }
    echo json_encode(['ok' => true, 'created' => $created, 'skipped' => $skipped, 'rows' => $rows]);
    exit;
}

// ── AJAX: Einzelnen Testspieler löschen ──────────────────────
if (($_GET['action'] ?? '') === 'delete') {
    header('Content-Type: application/json');
    if (!APP_DEBUG) { echo json_encode(['ok' => false, 'error' => 'Nur im Debug-Modus verfügbar.']); exit; }
    $pid = (int)($_POST['player_id'] ?? 0);
    if (!$pid) { echo json_encode(['ok' => false, 'error' => 'Ungültige ID.']); exit; }
    $p = Database::queryOne('SELECT id, username FROM players WHERE id = ?', [$pid]);
    if (!$p || !isTestPlayer($p)) {
        echo json_encode(['ok' => false, 'error' => 'Kein Testspieler.']); exit;
    }
    Database::execute('DELETE FROM players WHERE id = ?', [$pid]);
    echo json_encode(['ok' => true]);
    exit;
}

// ── AJAX: Alle Testspieler löschen ────────────────────────────
if (($_GET['action'] ?? '') === 'delete_all') {
    header('Content-Type: application/json');
    if (!APP_DEBUG) { echo json_encode(['ok' => false, 'error' => 'Nur im Debug-Modus verfügbar.']); exit; }
    Database::execute("DELETE FROM players WHERE username REGEXP '^test_[0-9]{2}$'");
    echo json_encode(['ok' => true]);
    exit;
}

// ── Seite laden ───────────────────────────────────────────────
$allTestPlayers = array_filter(
    Database::query("SELECT id, username, display_name FROM players WHERE username REGEXP '^test_[0-9]{2}$' ORDER BY username"),
    'isTestPlayer'
);
$testCount = count($allTestPlayers);

$page = ['title' => 'Testspieler'];
require TEMPLATE_PATH . '/base.php';
?>

<div class="container page-wrap">

  <div class="page-header">
    <span class="page-header__icon">🤖</span>
    <h1>Testspieler</h1>
    <p class="page-header__sub">
      <a href="<?= APP_URL ?>/admin/">← zurück zur Spielleitung</a>
    </p>
  </div>

  <?php if (!APP_DEBUG): ?>
  <div class="alert alert--warn">
    ⚠️ Debug-Modus ist deaktiviert — aktiviere <code>app_debug</code> unter
    <a href="<?= APP_URL ?>/admin/settings.php">Server-Einstellungen</a>, um Testspieler zu verwalten.
  </div>
  <?php else: ?>

  <!-- Info-Box -->
  <div class="card animate-in mb-2" style="animation-delay:.02s">
    <div class="section-title">Zugangsdaten Testspieler</div>
    <div class="panel" style="padding:.8rem 1rem;display:flex;flex-wrap:wrap;gap:1.2rem;align-items:center">
      <div>
        <div class="text-dim text-xs mb-1">Login-Name</div>
        <code style="font-family:monospace;font-size:.92rem;color:var(--text-bright)">
          test_01 … test_<?= str_pad(TEST_MAX, 2, '0', STR_PAD_LEFT) ?>
        </code>
      </div>
      <div>
        <div class="text-dim text-xs mb-1">Passwort (alle)</div>
        <code style="font-family:monospace;font-size:.92rem;color:var(--text-bright)"><?= TEST_PASSWORD ?></code>
      </div>
      <div>
        <div class="text-dim text-xs mb-1">Anzeigename</div>
        <code style="font-family:monospace;font-size:.92rem;color:var(--text-bright)">
          Testspieler 01 … Testspieler <?= str_pad(TEST_MAX, 2, '0', STR_PAD_LEFT) ?>
        </code>
      </div>
    </div>
  </div>

  <!-- Erstellen -->
  <div class="card card--glow animate-in mb-2" style="animation-delay:.04s">
    <div class="section-title">Testspieler anlegen</div>
    <p class="text-dim text-sm mb-2">
      Bereits vorhandene Testspieler werden übersprungen.
      Aktuell: <strong id="cur-count"><?= $testCount ?></strong> / <?= TEST_MAX ?>
    </p>

    <div class="flex gap-md" style="align-items:center;flex-wrap:wrap">
      <div style="flex:1;min-width:200px">
        <label class="form-label" for="create-count">Anzahl anlegen</label>
        <div class="flex gap-sm" style="align-items:center">
          <input type="range" id="create-slider" min="1" max="<?= TEST_MAX ?>"
                 value="<?= min(5, TEST_MAX - $testCount) ?>"
                 style="flex:1;accent-color:var(--accent)"
                 oninput="document.getElementById('create-count').value=this.value">
          <input type="number" id="create-count" min="1" max="<?= TEST_MAX ?>"
                 value="<?= min(5, TEST_MAX - $testCount) ?>"
                 class="form-input"
                 style="width:4.5rem;text-align:center;padding:.35rem .5rem;font-size:.9rem"
                 oninput="document.getElementById('create-slider').value=this.value">
        </div>
      </div>
      <div style="padding-top:1.4rem">
        <button class="btn btn--primary" onclick="createPlayers()">
          ➕ Anlegen
        </button>
      </div>
    </div>
    <div id="create-result" style="display:none;margin-top:.75rem"></div>
  </div>

  <!-- Liste -->
  <div class="card animate-in" style="animation-delay:.06s">
    <div class="section-title" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.5rem">
      <span>Vorhandene Testspieler (<span id="list-count"><?= $testCount ?></span>)</span>
      <?php if ($testCount > 0): ?>
      <button class="btn btn--danger btn--sm" onclick="deleteAll()">🗑 Alle löschen</button>
      <?php else: ?>
      <button class="btn btn--danger btn--sm" id="delete-all-btn" style="display:none" onclick="deleteAll()">🗑 Alle löschen</button>
      <?php endif; ?>
    </div>

    <div id="test-list" style="display:flex;flex-direction:column;gap:.4rem;margin-top:.5rem">
      <?php if (empty($allTestPlayers)): ?>
      <p class="text-dim text-center text-sm" id="empty-hint" style="padding:1.5rem 0">
        Noch keine Testspieler angelegt.
      </p>
      <?php else: ?>
      <p id="empty-hint" style="display:none;padding:1.5rem 0" class="text-dim text-center text-sm">
        Noch keine Testspieler angelegt.
      </p>
      <?php foreach ($allTestPlayers as $p): ?>
      <div class="panel" id="tp-<?= (int)$p['id'] ?>"
           style="padding:.55rem 1rem;display:flex;justify-content:space-between;align-items:center;gap:.5rem;flex-wrap:wrap">
        <div class="flex gap-sm" style="align-items:center">
          <span style="font-family:var(--font-display);font-size:.9rem;color:var(--text-bright)">
            <?= e($p['display_name']) ?>
          </span>
          <span class="text-dim text-xs">
            Login: <code style="font-family:monospace"><?= e($p['username']) ?></code>
          </span>
        </div>
        <button class="btn btn--ghost btn--sm" title="Löschen"
                onclick="deleteOne(<?= (int)$p['id'] ?>, '<?= e(addslashes($p['display_name'])) ?>')">
          🗑
        </button>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <?php endif; ?>

</div>

<?php
$page['inline_js'] = sprintf('const TP_API = %s;', json_encode(APP_URL . '/admin/testplayers.php'));
$page['inline_js'] .= <<<'JS'

function showResult(el, ok, msg) {
  el.style.display = '';
  el.innerHTML = '<div class="alert alert--' + (ok ? 'success' : 'error') + '" style="padding:.35rem .8rem;font-size:.84rem">' + msg + '</div>';
}

function updateCounts(delta) {
  const cur  = document.getElementById('cur-count');
  const lc   = document.getElementById('list-count');
  const dab  = document.querySelector('[onclick="deleteAll()"]') || document.getElementById('delete-all-btn');
  if (cur) cur.textContent = Math.max(0, parseInt(cur.textContent) + delta);
  if (lc)  lc.textContent  = Math.max(0, parseInt(lc.textContent)  + delta);
  const count = parseInt((cur || lc).textContent);
  if (dab) dab.style.display = count > 0 ? '' : 'none';
  const hint = document.getElementById('empty-hint');
  if (hint) hint.style.display = count === 0 ? '' : 'none';
}

function _tpRowHtml(p) {
  const row = document.createElement('div');
  row.className = 'panel';
  row.id = 'tp-' + p.id;
  row.style.cssText = 'padding:.55rem 1rem;display:flex;justify-content:space-between;align-items:center;gap:.5rem;flex-wrap:wrap';
  row.innerHTML =
    '<div class="flex gap-sm" style="align-items:center">' +
      '<span style="font-family:var(--font-display);font-size:.9rem;color:var(--text-bright)">' + escHtml(p.display_name) + '</span>' +
      '<span class="text-dim text-xs">Login: <code style="font-family:monospace">' + escHtml(p.username) + '</code></span>' +
    '</div>' +
    '<button type="button" class="btn btn--ghost btn--sm" title="Löschen">🗑</button>';
  row.querySelector('button').addEventListener('click', () => deleteOne(p.id, p.display_name));
  return row;
}

async function createPlayers() {
  const count  = parseInt(document.getElementById('create-count').value) || 1;
  const result = document.getElementById('create-result');
  const fd = new FormData();
  fd.append('count', count);
  try {
    const data = await (await fetch(TP_API + '?action=create', {method: 'POST', body: fd})).json();
    if (data.ok) {
      const msg = data.created > 0
        ? '✓ ' + data.created + ' Testspieler angelegt' + (data.skipped > 0 ? ' (' + data.skipped + ' vorhandene übersprungen).' : '.')
        : 'Kein Platz mehr — das Maximum an Testspielern ist bereits angelegt.';
      showResult(result, true, msg);
      if (data.created > 0) {
        const list = document.getElementById('test-list');
        (data.rows || []).forEach(p => list.appendChild(_tpRowHtml(p)));
        updateCounts(data.created);
      }
    } else {
      showResult(result, false, data.error || 'Fehler');
    }
  } catch(e) { showResult(result, false, 'Netzwerkfehler.'); }
}

async function deleteOne(pid, name) {
  const fd = new FormData();
  fd.append('player_id', pid);
  try {
    const data = await (await fetch(TP_API + '?action=delete', {method: 'POST', body: fd})).json();
    if (data.ok) {
      const row = document.getElementById('tp-' + pid);
      if (row) { row.style.opacity = '0'; row.style.transition = 'opacity .25s'; setTimeout(() => row.remove(), 260); }
      updateCounts(-1);
      showToast('„' + name + '" gelöscht.', 'success');
    } else {
      showToast(data.error || 'Fehler', 'error');
    }
  } catch(e) { showToast('Netzwerkfehler.', 'error'); }
}

async function deleteAll() {
  const count = parseInt(document.getElementById('list-count').textContent);
  if (!count || !confirm(count + ' Testspieler wirklich alle löschen?')) return;
  try {
    const data = await (await fetch(TP_API + '?action=delete_all', {method: 'POST'})).json();
    if (data.ok) {
      document.querySelectorAll('#test-list [id^="tp-"]').forEach(el => el.remove());
      const delta = -count;
      updateCounts(delta);
      showToast('Alle Testspieler gelöscht.', 'success');
    } else {
      showToast(data.error || 'Fehler', 'error');
    }
  } catch(e) { showToast('Netzwerkfehler.', 'error'); }
}
JS;
require TEMPLATE_PATH . '/base_end.php';
?>
