<?php
// Copyright (c) 2026 Andreas Vetter
// DEMO: Live-Updates ohne Seitenreload (Polling via fetch)
require_once __DIR__ . '/core/bootstrap.php';
Auth::requireLogin();

// ── API-Handler (wird von fetch() aufgerufen) ─────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');

    $block = $_GET['block'] ?? '';

    switch ($block) {

        case 'zeit':
            // Block 1: Aktuelle Serverzeit (date() liefert nur englische Namen)
            $tage   = ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'];
            $monate = [1 => 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli',
                       'August', 'September', 'Oktober', 'November', 'Dezember'];
            $datum = $tage[(int)date('w')] . ', ' . date('d.') . ' ' . $monate[(int)date('n')] . ' ' . date('Y');
            echo json_encode([
                'html' => '<span style="font-size:2rem;font-family:var(--font-display)">'
                        . date('H:i:s')
                        . '</span><div class="text-dim text-xs mt-1">' . $datum . '</div>',
            ]);
            break;

        case 'spiel':
            // Block 2: Aktuelles Spiel-Status
            $game = Database::queryOne(
                "SELECT status, phase, round,
                        (SELECT COUNT(*) FROM game_players WHERE game_id=games.id) AS total,
                        (SELECT COUNT(*) FROM game_players WHERE game_id=games.id AND is_alive=1) AS alive
                 FROM games ORDER BY id DESC LIMIT 1"
            );
            if (!$game) {
                echo json_encode(['html' => '<span class="text-dim">Kein Spiel vorhanden</span>']);
                break;
            }
            $statusLabel = ['lobby' => '🏠 Lobby', 'running' => '▶️ Läuft', 'finished' => '🏁 Beendet'][$game['status']] ?? $game['status'];
            $phaseLabel  = $game['phase'] === 'night' ? '🌕 Nacht' : '☀️ Tag';
            $html  = "<div class='flex gap-sm' style='flex-wrap:wrap'>";
            $html .= "<span class='badge badge--accent'>{$statusLabel}</span>";
            if ($game['status'] === 'running') {
                $html .= "<span class='badge'>Runde {$game['round']}</span>";
                $html .= "<span class='badge'>{$phaseLabel}</span>";
                $html .= "<span class='badge'>👥 {$game['alive']}/{$game['total']} am Leben</span>";
            }
            $html .= "</div>";
            echo json_encode(['html' => $html]);
            break;

        case 'spieler':
            // Block 3: Spieler im laufenden Spiel
            $game = Database::queryOne("SELECT id FROM games WHERE status='running' ORDER BY id DESC LIMIT 1");
            if (!$game) {
                echo json_encode(['html' => '<span class="text-dim">Kein laufendes Spiel</span>']);
                break;
            }
            $players = Database::query(
                "SELECT p.display_name, gp.is_alive
                 FROM game_players gp JOIN players p ON p.id=gp.player_id
                 WHERE gp.game_id=? ORDER BY gp.is_alive DESC, p.display_name",
                [$game['id']]
            );
            if (!$players) {
                echo json_encode(['html' => '<span class="text-dim">Keine Spieler</span>']);
                break;
            }
            $html = '<div style="display:flex;flex-wrap:wrap;gap:.4rem">';
            foreach ($players as $p) {
                $icon  = $p['is_alive'] ? '🟢' : '💀';
                $style = $p['is_alive'] ? '' : 'opacity:.45;text-decoration:line-through';
                $html .= "<span class='badge' style='{$style}'>{$icon} " . e($p['display_name']) . '</span>';
            }
            $html .= '</div>';
            echo json_encode(['html' => $html]);
            break;

        default:
            echo json_encode(['error' => 'Unbekannter Block']);
    }
    exit;
}

$page = ['title' => '🔴 Live-Demo'];
require TEMPLATE_PATH . '/base.php';
?>

<div class="container page-wrap">

  <div class="page-header">
    <span class="page-header__icon">🔴</span>
    <h1>Live-Update Demo</h1>
    <p class="page-header__sub">
      Diese drei Blöcke aktualisieren sich automatisch — <strong>ohne Seitenreload</strong>.
    </p>
  </div>

  <!-- Steuerung -->
  <div class="card animate-in mb-2" style="display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap">
    <div>
      <span class="settings-row__name">Intervall</span>
      <div class="text-dim text-xs mt-1">Wie oft werden die Blöcke aktualisiert?</div>
    </div>
    <div class="flex gap-sm" style="align-items:center">
      <select id="interval-select" class="form-input" style="width:auto" onchange="changeInterval()">
        <option value="1000">1 Sekunde</option>
        <option value="3000" selected>3 Sekunden</option>
        <option value="5000">5 Sekunden</option>
        <option value="10000">10 Sekunden</option>
      </select>
      <button class="btn btn--ghost btn--sm" id="pause-btn" onclick="togglePause()">⏸ Pause</button>
    </div>
  </div>

  <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:1rem;margin-bottom:2rem">

    <!-- Block 1: Uhr -->
    <div class="card animate-in" style="animation-delay:.05s">
      <div class="flex gap-sm" style="align-items:center;justify-content:space-between;margin-bottom:.75rem">
        <div class="section-title" style="margin:0">🕐 Serverzeit</div>
        <span id="ind-zeit" class="live-dot"></span>
      </div>
      <div id="block-zeit" class="live-block">Lädt…</div>
      <div class="text-dim text-xs mt-2">Aktualisiert: <span id="ts-zeit">—</span></div>
    </div>

    <!-- Block 2: Spielstatus -->
    <div class="card animate-in" style="animation-delay:.1s">
      <div class="flex gap-sm" style="align-items:center;justify-content:space-between;margin-bottom:.75rem">
        <div class="section-title" style="margin:0">🎮 Spielstatus</div>
        <span id="ind-spiel" class="live-dot"></span>
      </div>
      <div id="block-spiel" class="live-block">Lädt…</div>
      <div class="text-dim text-xs mt-2">Aktualisiert: <span id="ts-spiel">—</span></div>
    </div>

    <!-- Block 3: Spielerliste -->
    <div class="card animate-in" style="animation-delay:.15s">
      <div class="flex gap-sm" style="align-items:center;justify-content:space-between;margin-bottom:.75rem">
        <div class="section-title" style="margin:0">👥 Spieler (laufend)</div>
        <span id="ind-spieler" class="live-dot"></span>
      </div>
      <div id="block-spieler" class="live-block">Lädt…</div>
      <div class="text-dim text-xs mt-2">Aktualisiert: <span id="ts-spieler">—</span></div>
    </div>

  </div>

  <!-- Log -->
  <div class="card animate-in" style="animation-delay:.2s">
    <div class="flex gap-sm" style="align-items:center;justify-content:space-between;margin-bottom:.75rem">
      <div class="section-title" style="margin:0">📋 Update-Log</div>
      <button class="btn btn--ghost btn--sm" onclick="clearLog()">Leeren</button>
    </div>
    <div id="log" style="font-family:monospace;font-size:.8rem;max-height:180px;overflow-y:auto;
         background:var(--bg-deep);border-radius:var(--radius);padding:.75rem;color:var(--text-dim)">
      Warte auf Updates…
    </div>
  </div>

</div>

<style>
.live-dot {
  width: 10px; height: 10px; border-radius: 50%;
  background: var(--accent); display: inline-block;
  animation: pulse-dot 2s ease-in-out infinite;
}
.live-dot.error { background: var(--danger); animation: none; }

@keyframes pulse-dot {
  0%, 100% { opacity: 1; transform: scale(1); }
  50%       { opacity: .4; transform: scale(.7); }
}

.live-block { transition: opacity .15s; min-height: 2rem; }
.live-block.updating { opacity: .4; }

</style>

<?php
$page['inline_js'] = sprintf('const DEMO_API = %s;', json_encode(APP_URL . '/demo_live.php'));
$page['inline_js'] .= <<<'JS'

const BLOCKS   = ['zeit', 'spiel', 'spieler'];
let   interval = 3000;
let   timer    = null;
let   paused   = false;
let   logLines = [];

function ts() {
  return new Date().toLocaleTimeString('de-DE');
}

function addLog(msg, ok = true) {
  logLines.unshift(`[${ts()}] ${ok ? '✓' : '✗'} ${msg}`);
  if (logLines.length > 50) logLines.pop();
  document.getElementById('log').textContent = logLines.join('\n');
}

function clearLog() {
  logLines = [];
  document.getElementById('log').textContent = 'Log geleert.';
}

let sessionLost = false;

function stopOnSessionLoss() {
  if (sessionLost) return;
  sessionLost = true;
  clearInterval(timer);
  timer = null;
  paused = true;
  document.getElementById('pause-btn').textContent = '▶ Fortsetzen';
  addLog('Session abgelaufen — bitte neu anmelden', false);
}

async function updateBlock(name) {
  const el  = document.getElementById('block-' + name);
  const ind = document.getElementById('ind-' + name);
  const tsEl = document.getElementById('ts-' + name);

  el.classList.add('updating');
  try {
    const r = await fetch(DEMO_API + '?block=' + name, {
      method: 'POST',
      headers: { 'X-Requested-With': 'fetch' },
    });
    if (r.status === 401) {
      ind.classList.add('error');
      stopOnSessionLoss();
      return;
    }
    const d = await r.json();
    if (d.html !== undefined) {
      el.innerHTML = d.html;
      ind.classList.remove('error');
      tsEl.textContent = ts();
      addLog('Block "' + name + '" aktualisiert');
    } else {
      addLog('Block "' + name + '": ' + (d.error || 'Fehler'), false);
      ind.classList.add('error');
    }
  } catch (e) {
    ind.classList.add('error');
    addLog('Block "' + name + '": Netzwerkfehler', false);
  } finally {
    el.classList.remove('updating');
  }
}

let updateRunning = false;

async function updateAll() {
  if (updateRunning) return; // Ticks nicht stapeln, wenn der Server langsamer als das Intervall ist
  updateRunning = true;
  try {
    await Promise.all(BLOCKS.map(updateBlock));
  } finally {
    updateRunning = false;
  }
}

function startTimer() {
  if (timer) clearInterval(timer);
  timer = setInterval(updateAll, interval);
}

function changeInterval() {
  interval = parseInt(document.getElementById('interval-select').value);
  if (!paused) startTimer();
  addLog('Intervall geändert: ' + (interval/1000) + 's');
}

function togglePause() {
  paused = !paused;
  const btn = document.getElementById('pause-btn');
  if (paused) {
    clearInterval(timer);
    btn.textContent = '▶ Fortsetzen';
    addLog('Pausiert');
  } else {
    sessionLost = false;
    startTimer();
    btn.textContent = '⏸ Pause';
    addLog('Fortgesetzt');
  }
}

// Unsichtbare Tabs nicht weiter pollen — beim Zurückkehren sofort aktualisieren
document.addEventListener('visibilitychange', () => {
  if (document.hidden) {
    clearInterval(timer);
    timer = null;
  } else if (!paused) {
    updateAll();
    startTimer();
  }
});

// Direkt beim Laden alle Blöcke holen, dann Intervall starten
updateAll();
startTimer();
JS;
require TEMPLATE_PATH . '/base_end.php';
?>
