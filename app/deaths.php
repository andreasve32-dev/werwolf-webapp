<?php
// Copyright (c) 2026 Andreas Vetter
require_once dirname(__DIR__) . '/core/bootstrap.php';
require_once TEMPLATE_PATH . '/deaths_blocks.php';
Auth::requireLogin();

$game      = currentGame() ?: Database::queryOne("SELECT * FROM games ORDER BY id DESC LIMIT 1");
$gameId    = $game['id'] ?? null;
$curPlayer = Auth::player();

$state = deaths_compute_state($gameId, $curPlayer);

$page = ['title' => 'Gräber'];
require TEMPLATE_PATH . '/base.php';
?>

<div class="container page-wrap">

  <div class="page-header">
    <span class="page-header__icon">⚰️</span>
    <h1>Gräber des Dorfes</h1>
    <p class="page-header__sub">
      <?php if ($game): ?>
        Spiel #<?= (int)$game['id'] ?> &middot;
        <span class="tag tag--<?= $game['status']==='running'?'running':($game['status']==='lobby'?'lobby':'dead') ?>">
          <?= ['lobby'=>'Lobby','running'=>'Läuft','finished'=>'Beendet'][$game['status']] ?? $game['status'] ?>
        </span>
      <?php else: ?>
        Kein Spiel gefunden
      <?php endif; ?>
    </p>
  </div>

  <div id="deaths-content"><?= render_deaths_content($state) ?></div>
</div>

<!-- Modal: Todesdaten eintragen (immer rendern — der Live-Poll kann den
     Eintragen-Button auch nachliefern, wenn der Spieler erst während
     geöffneter Seite stirbt) -->
<div id="eintragen-modal"
     style="display:none;position:fixed;inset:0;z-index:9000;
            background:rgba(0,0,0,.75);backdrop-filter:blur(10px);
            align-items:center;justify-content:center;padding:1rem">
  <div style="background:var(--card-bg);border:1px solid var(--border);border-radius:20px;
              width:min(92vw,400px);padding:2rem 1.8rem;
              box-shadow:0 32px 64px rgba(0,0,0,.6)">

    <div style="display:flex;align-items:center;gap:.8rem;margin-bottom:1.6rem">
      <span style="font-size:1.8rem">📋</span>
      <div>
        <div style="font-family:var(--font-display);font-size:1.15rem">Todesdaten eintragen</div>
        <div class="text-dim text-xs">Nur du siehst dieses Formular</div>
      </div>
    </div>

    <input type="hidden" id="ei-death-id">

    <div style="margin-bottom:1.1rem">
      <label style="display:block;font-size:.72rem;font-weight:700;letter-spacing:.08em;
                    text-transform:uppercase;color:var(--text-dim);margin-bottom:.45rem">
        Rolle
      </label>
      <div id="ei-rolle-anzeige"
           style="width:100%;box-sizing:border-box;
                  background:rgba(255,255,255,.03);border:1px solid var(--border);
                  border-radius:10px;padding:.7rem 1rem;font-size:.95rem;
                  color:var(--text-dim);font-family:inherit;opacity:.75;
                  user-select:none">—</div>
    </div>

    <div style="margin-bottom:1.1rem">
      <label style="display:block;font-size:.72rem;font-weight:700;letter-spacing:.08em;
                    text-transform:uppercase;color:var(--text-dim);margin-bottom:.45rem">
        Todesort
      </label>
      <input type="text" id="ei-ort"
             style="width:100%;box-sizing:border-box;
                    background:rgba(255,255,255,.06);border:1px solid var(--border);
                    border-radius:10px;padding:.7rem 1rem;font-size:.95rem;
                    color:var(--text);font-family:inherit;outline:none;
                    transition:border-color .15s"
             onfocus="this.style.borderColor='var(--accent)'"
             onblur="this.style.borderColor='var(--border)'"
             placeholder="z.B. Marktplatz, Kirche …" maxlength="200">
    </div>

    <div style="margin-bottom:1.6rem">
      <label style="display:block;font-size:.72rem;font-weight:700;letter-spacing:.08em;
                    text-transform:uppercase;color:var(--text-dim);margin-bottom:.45rem">
        Todeszeit
      </label>
      <input type="time" id="ei-zeit"
             style="width:100%;box-sizing:border-box;
                    background:rgba(255,255,255,.06);border:1px solid var(--border);
                    border-radius:10px;padding:.7rem 1rem;font-size:1.1rem;
                    color:var(--text);font-family:var(--font-display);outline:none;
                    transition:border-color .15s"
             onfocus="this.style.borderColor='var(--accent)'"
             onblur="this.style.borderColor='var(--border)'">
    </div>

    <div style="display:flex;gap:.75rem">
      <button class="btn btn-ghost" onclick="closeEintragen()" style="flex:1">Abbrechen</button>
      <button class="btn" id="ei-save" onclick="saveEintragen()" style="flex:2">Speichern</button>
    </div>

    <div id="ei-error"
         style="display:none;margin-top:.85rem;padding:.55rem .85rem;
                border-radius:10px;background:rgba(248,113,113,.12);
                border:1px solid rgba(248,113,113,.3);
                color:#f87171;font-size:.82rem"></div>
  </div>
</div>

<?php
$gameIdJs = (int)($gameId ?? 0);
$page['inline_js']  = sprintf('const API_BASE=%s;', json_encode(API_URL));
$page['inline_js'] .= <<<'JS'
const deathsPoll = liveBlocks({
  fetcher: (hash) => apiFetch(API_BASE+'/game.php', {action:'get_deaths', blocks_hash: hash}),
  targets: {'deaths-content':'deaths-content'},
  countdownId: 'poll-countdown',
});
deathsPoll.start();
JS;
$page['inline_js'] .= <<<JS

function openEintragen(btn) {
  document.getElementById('ei-death-id').value  = btn.dataset.deathId;
  document.getElementById('ei-ort').value        = btn.dataset.ort   || '';
  document.getElementById('ei-zeit').value       = btn.dataset.zeit  || '';
  document.getElementById('ei-rolle-anzeige').textContent = btn.dataset.rolleName || '—';
  document.getElementById('ei-error').style.display = 'none';
  document.getElementById('eintragen-modal').style.display = 'flex';
  setTimeout(() => document.getElementById('ei-ort').focus(), 80);
}
function closeEintragen() {
  document.getElementById('eintragen-modal').style.display = 'none';
}
async function saveEintragen() {
  const deathId = document.getElementById('ei-death-id').value;
  const ort     = document.getElementById('ei-ort').value.trim();
  const zeit    = document.getElementById('ei-zeit').value.trim();
  const savBtn  = document.getElementById('ei-save');
  const err     = document.getElementById('ei-error');
  savBtn.disabled = true;
  err.style.display = 'none';
  try {
    const res  = await fetch('/api/game.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({action:'update_death_info', game_id:{$gameIdJs}, death_id:parseInt(deathId), ort, zeit})
    });
    const text = await res.text();
    let data;
    try { data = JSON.parse(text); } catch(_) { throw new Error('Server-Fehler (keine JSON-Antwort)'); }
    if (!res.ok || data.error) throw new Error(data.error || 'Unbekannter Fehler');
    closeEintragen();
    await deathsPoll.refreshNow();
    savBtn.disabled = false;
  } catch(e) {
    err.textContent = e.message;
    err.style.display = 'block';
    savBtn.disabled = false;
  }
}
document.getElementById('eintragen-modal')?.addEventListener('click', e => {
  if (e.target === e.currentTarget) closeEintragen();
});
JS;
require TEMPLATE_PATH . '/base_end.php';
?>
