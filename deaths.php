<?php
// Copyright (c) 2026 Andreas Vetter
require_once __DIR__ . '/core/bootstrap.php';
Auth::requireLogin();

$game   = currentGame() ?: Database::queryOne("SELECT * FROM games ORDER BY id DESC LIMIT 1");
$gameId = $game['id'] ?? null;
$deaths = $gameId ? Database::query(
    "SELECT d.*, p.display_name AS username FROM deaths d JOIN players p ON p.id=d.player_id WHERE d.game_id=? ORDER BY d.died_at",
    [$gameId]
) : [];

$curPlayer   = Auth::player();
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
// Button selbst: nur wenn der eigene Eintrag noch nicht aufgedeckt ist
$canEintragen     = $showEintragenCol && !empty($myDeath) && empty($myDeath['rolle_aufgedeckt']);

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
</div>

<!-- Modal: Todesdaten eintragen -->
<?php if ($showEintragenCol): ?>
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
<?php endif; ?>

<?php
$gameIdJs  = (int)($gameId ?? 0);
$deathsUrl = APP_URL . '/deaths.php';
$page['inline_js'] = '';
if ($showEintragenCol): $page['inline_js'] = <<<JS
function openEintragen(btn) {
  document.getElementById('ei-death-id').value = btn.dataset.deathId;
  document.getElementById('ei-ort').value       = btn.dataset.ort  || '';
  document.getElementById('ei-zeit').value      = btn.dataset.zeit || '';
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
    window.location.href = '{$deathsUrl}';
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
endif;
$page['inline_js'] .= "setInterval(() => { window.location.href = '{$deathsUrl}'; }, 12000);";
require TEMPLATE_PATH . '/base_end.php';
?>
