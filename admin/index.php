<?php
// Copyright (c) 2026 Andreas Vetter
require_once dirname(__DIR__) . '/core/bootstrap.php';
require_once TEMPLATE_PATH . '/admin_dashboard_blocks.php';
Auth::requireAdmin();

$game = currentGame();
if (!$game) {
    Database::execute("INSERT INTO games (status) VALUES ('lobby')");
    $game = Database::queryOne("SELECT * FROM games ORDER BY id DESC LIMIT 1");
}
$gameId = $game['id'];

$state = admin_compute_state($gameId);
extract($state);

$_navMsgPending = 0;
try {
    $row = Database::queryOne("SELECT COUNT(*) AS cnt FROM messages WHERE reply IS NULL");
    $_navMsgPending = (int)($row['cnt'] ?? 0);
} catch (Throwable $e) {}

$page = ['title' => 'Admin'];
require TEMPLATE_PATH . '/base.php';
?>

<div class="container page-wrap">

  <div class="page-header">
    <span class="page-header__icon">⚙️</span>
    <h1>Spielleitung</h1>
    <p class="page-header__sub">
      Spiel #<?= $gameId ?> &middot;
      <span class="tag tag--<?= $game['status']==='running'?'running':($game['status']==='lobby'?'lobby':'dead') ?>">
        <?= ['lobby'=>'Lobby','running'=>'Läuft','finished'=>'Beendet'][$game['status']] ?? $game['status'] ?>
      </span>
      <?php if ($game['status']==='running'): ?>
      &middot; <span class="tag tag--day">Runde <?= (int)$game['round'] ?></span>
      <?php endif; ?>
    </p>
  </div>

  <div id="win-banner"><?= admin_render_win_banner($state) ?></div>

  <div id="assembly-banner"><?= admin_render_assembly_banner($state) ?></div>

  <!-- ── Spielsteuerung ──────────────────────────────────────── -->
  <div id="game-controls"><?= admin_render_game_controls($state) ?></div>

  <!-- ── Verwaltungsbereich ─────────────────────────────────── -->
  <div class="card animate-in mb-2" style="animation-delay:.04s">
    <div class="section-title">Verwaltung</div>
    <div class="admin-links">

      <!-- Reihenfolge nach Gebrauchshäufigkeit im laufenden Spiel: erst was während
           des Spiels oft gebraucht wird, danach seltener genutzte Verwaltung/Technik. -->

      <a href="<?= APP_URL ?>/admin/messages.php" class="admin-link-card">
        <span class="admin-link-card__icon">✉️</span>
        <div class="admin-link-card__text">
          <div class="admin-link-card__title">
            Nachrichten
            <?php if ($_navMsgPending > 0): ?>
            <span id="admin-msg-badge"
                  style="background:var(--accent);color:var(--bg,#000);border-radius:99px;
                         font-size:.6rem;font-weight:700;padding:1px 6px;
                         margin-left:.3rem;vertical-align:middle">
              <?= $_navMsgPending ?>
            </span>
            <?php else: ?>
            <span id="admin-msg-badge"
                  style="display:none;background:var(--accent);color:var(--bg,#000);border-radius:99px;
                         font-size:.6rem;font-weight:700;padding:1px 6px;margin-left:.3rem;vertical-align:middle"></span>
            <?php endif; ?>
          </div>
          <div class="admin-link-card__sub">Spielerfragen beantworten &amp; FAQ pflegen</div>
        </div>
        <span class="admin-link-card__arrow">→</span>
      </a>

      <a href="<?= APP_URL ?>/admin/roles.php" class="admin-link-card">
        <span class="admin-link-card__icon">🎭</span>
        <div class="admin-link-card__text">
          <div class="admin-link-card__title">Rollen</div>
          <div class="admin-link-card__sub">Rollen konfigurieren &amp; aktivieren</div>
        </div>
        <span class="admin-link-card__arrow">→</span>
      </a>

      <a href="<?= APP_URL ?>/admin/players.php" class="admin-link-card">
        <span class="admin-link-card__icon">👥</span>
        <div class="admin-link-card__text">
          <div class="admin-link-card__title">Spieler</div>
          <div class="admin-link-card__sub">Konten verwalten &amp; Passwörter setzen</div>
        </div>
        <span class="admin-link-card__arrow">→</span>
      </a>

      <a href="<?= APP_URL ?>/admin/slogans.php" class="admin-link-card">
        <span class="admin-link-card__icon">💬</span>
        <div class="admin-link-card__text">
          <div class="admin-link-card__title">Dorf-Sprüche</div>
          <div class="admin-link-card__sub">Tag- &amp; Nacht-Sprüche verwalten</div>
        </div>
        <span class="admin-link-card__arrow">→</span>
      </a>

      <a href="<?= APP_URL ?>/admin/settings.php" class="admin-link-card">
        <span class="admin-link-card__icon">⚙️</span>
        <div class="admin-link-card__text">
          <div class="admin-link-card__title">Einstellungen</div>
          <div class="admin-link-card__sub">App-Name, Theme, Texte &amp; mehr</div>
        </div>
        <span class="admin-link-card__arrow">→</span>
      </a>

      <a href="<?= APP_URL ?>/admin/diagnostics.php" class="admin-link-card">
        <span class="admin-link-card__icon">🔍</span>
        <div class="admin-link-card__text">
          <div class="admin-link-card__title">Systemcheck</div>
          <div class="admin-link-card__sub">Datenbankverbindung &amp; Konfiguration prüfen</div>
        </div>
        <span class="admin-link-card__arrow">→</span>
      </a>

      <a href="<?= APP_URL ?>/admin/setup.php" class="admin-link-card">
        <span class="admin-link-card__icon">🔧</span>
        <div class="admin-link-card__text">
          <div class="admin-link-card__title">Ersteinrichtung</div>
          <div class="admin-link-card__sub">Datenbank &amp; Admin-Konto neu aufbauen</div>
        </div>
        <span class="admin-link-card__arrow">→</span>
      </a>

      <?php if (APP_DEBUG): ?>
      <a href="<?= APP_URL ?>/admin/debug.php" class="admin-link-card" style="border-color:rgba(251,191,36,.35)">
        <span class="admin-link-card__icon">🐛</span>
        <div class="admin-link-card__text">
          <div class="admin-link-card__title" style="color:#fbbf24">Debug-Menü</div>
          <div class="admin-link-card__sub">Eigene Rolle wählen &amp; Tote wiederbeleben</div>
        </div>
        <span class="admin-link-card__arrow">→</span>
      </a>

      <a href="<?= APP_URL ?>/admin/testplayers.php" class="admin-link-card" style="border-color:rgba(251,191,36,.35)">
        <span class="admin-link-card__icon">🤖</span>
        <div class="admin-link-card__text">
          <div class="admin-link-card__title" style="color:#fbbf24">Testspieler</div>
          <div class="admin-link-card__sub">Bis zu 20 Test-Konten anlegen &amp; löschen</div>
        </div>
        <span class="admin-link-card__arrow">→</span>
      </a>
      <?php endif; ?>

    </div>
  </div>

  <div class="grid-2" style="gap:1.5rem;align-items:start">

    <!-- ── Linke Spalte: Spielerliste ──────────────────────────── -->
    <div>
      <div class="card animate-in" style="animation-delay:.06s">
        <div class="section-title" style="display:flex;align-items:center;justify-content:space-between;cursor:pointer" onclick="togglePlayers()">
          <span>Spieler (<?= $playerCount ?>)</span>
          <span id="players-chevron" style="font-size:.75rem;color:var(--text-dim);transition:transform .22s ease">▼</span>
        </div>

        <div id="player-list-body"><?= admin_render_player_list($state) ?></div>
      </div>

      <!-- Spieler hinzufügen (nur in Lobby) -->
      <div id="add-players-card"><?= admin_render_add_players($state) ?></div>
    </div>

    <!-- ── Rechte Spalte ───────────────────────────────────────── -->
    <div>

      <!-- Rollen-Vorschau (nur Anzahlen, nicht wer was bekommt) -->
      <div id="role-preview-card"><?= admin_render_role_preview($state) ?></div>

      <!-- Bürgerversammlung -->
      <div id="voting-card"><?= admin_render_voting($state) ?></div>

      <!-- Als tot markieren (Schnellzugriff) -->
      <div id="kill-quick-card"><?= admin_render_kill_quick($state) ?></div>

    </div>
  </div>
</div>

<style>
.admin-links {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: .6rem;
}
@media (max-width: 640px) {
  .admin-links { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 380px) {
  .admin-links { grid-template-columns: 1fr; }
}
.admin-link-card {
  display: flex; align-items: center; gap: .75rem;
  padding: .85rem .9rem;
  background: var(--panel-bg);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  text-decoration: none;
  color: inherit;
  transition: border-color .18s, background .18s, transform .18s;
  min-width: 0;
}
.admin-link-card:hover {
  border-color: var(--accent-border);
  background: var(--card-bg);
  transform: translateY(-1px);
}
.admin-link-card__icon {
  font-size: 1.4rem;
  flex-shrink: 0;
  line-height: 1;
}
.admin-link-card__text { flex: 1; min-width: 0; }
.admin-link-card__title {
  font-family: var(--font-display);
  font-size: .88rem;
  color: var(--text-bright);
  font-weight: 600;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.admin-link-card__sub {
  font-size: .72rem;
  color: var(--text-dim);
  margin-top: .1rem;
  line-height: 1.35;
}
.admin-link-card__arrow {
  flex-shrink: 0;
  color: var(--text-dim);
  font-size: .8rem;
  margin-left: auto;
  transition: transform .18s;
}
.admin-link-card:hover .admin-link-card__arrow { transform: translateX(3px); }
</style>

<?php
$page['inline_js'] = sprintf('const GAME_ID=%s,API_BASE=%s;', json_encode($gameId), json_encode(API_URL));
$page['inline_js'] .= <<<'JS'
const dash = liveBlocks({
  fetcher: (hash) => apiFetch(API_BASE+'/admin.php', {action:'get_dashboard', game_id:GAME_ID, blocks_hash: hash}),
  targets: {
    'win-banner':'win-banner', 'assembly-banner':'assembly-banner',
    'game-controls':'game-controls', 'player-list-body':'player-list-body',
    'add-players-card':'add-players-card', 'role-preview-card':'role-preview-card',
    'voting-card':'voting-card', 'kill-quick-card':'kill-quick-card',
  },
  countdownId: 'poll-countdown',
});
dash.start();

async function adminAction(action){
  const r=await apiFetch(API_BASE+'/admin.php',{action,game_id:GAME_ID});
  if(r.error==='session_expired')return;
  const el=document.getElementById('action-result');
  if(el) el.innerHTML=r.ok
    ?`<div class="alert alert--success">${r.message||'OK'}</div>`
    :`<div class="alert alert--error">${r.error||'Fehler'}</div>`;
  if(r.ok) dash.refreshNow();
}
function _handleWinResponse(r, resultElId) {
  const el = document.getElementById(resultElId);
  if (!el) return;
  if (!r.ok) { el.innerHTML=`<div class="alert alert--error">${r.error||'Fehler'}</div>`; return; }
  el.innerHTML = `<div class="alert alert--success">${r.message||'OK'}</div>`;
  dash.refreshNow();
}
async function addPlayer(pid,name){
  const r=await apiFetch(API_BASE+'/admin.php',{action:'add_player',game_id:GAME_ID,player_id:pid});
  if(r.error==='session_expired')return;
  if(r.ok){showToast(name+' hinzugefügt','success');dash.refreshNow();}
  else showToast(r.error||'Fehler','error');
}
async function addAllPlayers(){
  const r=await apiFetch(API_BASE+'/admin.php',{action:'add_all_players',game_id:GAME_ID});
  if(r.error==='session_expired')return;
  if(r.ok){showToast('Alle Spieler hinzugefügt','success');dash.refreshNow();}
  else showToast(r.error||'Fehler','error');
}
async function removePlayer(pid){
  const r=await apiFetch(API_BASE+'/admin.php',{action:'remove_player',game_id:GAME_ID,player_id:pid});
  if(r.error==='session_expired')return;
  if(r.ok){showToast('Entfernt','success');dash.refreshNow();}
  else showToast(r.error||'Fehler','error');
}
async function hangAccused(pid, name) {
  if (!confirm('⚖️ ' + name + ' wirklich hängen?')) return;
  const r = await apiFetch(API_BASE+'/admin.php', {action:'execute_vote', game_id:GAME_ID, player_id:pid});
  if (r.error === 'session_expired') return;
  _handleWinResponse(r, 'action-result-assembly');
}
async function freeAccused(name) {
  if (!confirm('✓ ' + name + ' freisprechen? Alle Stimmen werden gelöscht.')) return;
  const r = await apiFetch(API_BASE+'/admin.php', {action:'free_accused', game_id:GAME_ID});
  if (r.error === 'session_expired') return;
  const el = document.getElementById('action-result-assembly');
  if (el) el.innerHTML = r.ok
    ? `<div class="alert alert--success">${r.message||'OK'}</div>`
    : `<div class="alert alert--error">${r.error||'Fehler'}</div>`;
  if (r.ok) dash.refreshNow();
}
async function killPlayer(pid,name){
  if(!confirm(name+' als tot markieren?'))return;
  const cause=document.getElementById('kill-cause')?.value||'other';
  const r=await apiFetch(API_BASE+'/admin.php',{action:'kill_player',game_id:GAME_ID,player_id:pid,cause});
  if(r.error==='session_expired')return;
  if(r.ok){showToast(r.message||(name+' gestorben'),'success');dash.refreshNow();}
  else showToast(r.error||'Fehler','error');
}
async function manualKill(){
  const pid=document.getElementById('kill-pid').value;
  const cause=document.getElementById('kill-cause').value;
  if(!pid){showToast('Spieler wählen!','error');return;}
  const r=await apiFetch(API_BASE+'/admin.php',{action:'kill_player',game_id:GAME_ID,player_id:parseInt(pid),cause});
  if(r.error==='session_expired')return;
  if(r.ok){showToast(r.message||'Als tot markiert','success');dash.refreshNow();}
  else showToast(r.error||'Fehler','error');
}
JS;

$page['inline_js'] .= <<<'JS'

// ── Versammlung beenden (Admin) ──────────────────────────────
async function endAssemblyAdmin() {
  const r = await apiFetch(API_BASE+'/game.php', {action:'end_assembly', game_id:GAME_ID});
  if (r.error === 'session_expired') return;
  if (r.ok) dash.refreshNow();
  else showToast(r.error||'Fehler','error');
}

// ── Versammlungs-Countdown im Admin-Banner ───────────────────
// Element wird alle 5s neu gerendert (Dashboard-Polling) — Referenz und
// Zeitstempel deshalb bei jedem Tick frisch aus data-ts auslesen statt
// einmalig einzufangen.
setInterval(() => {
  const el = document.getElementById('admin-assembly-countdown');
  if (!el) return;
  const ts = parseInt(el.dataset.ts || '0', 10);
  if (!ts) { el.textContent = ''; return; }
  const diff = ts - Math.floor(Date.now()/1000);
  if (diff <= 0) { el.textContent = ''; return; }
  const m = Math.floor(diff/60), s = diff%60;
  el.textContent = '(noch ' + String(m).padStart(2,'0') + ':' + String(s).padStart(2,'0') + ')';
}, 1000);

// ── Spielerliste ein-/ausklappen ─────────────────────────────
function togglePlayers() {
  const body    = document.getElementById('player-list-body');
  const chevron = document.getElementById('players-chevron');
  const isOpen  = body.style.display !== 'none';
  body.style.display    = isOpen ? 'none' : '';
  chevron.style.transform = isOpen ? 'rotate(-90deg)' : '';
  localStorage.setItem('ww_admin_players_open', String(!isOpen));
}

// Zustand beim Laden wiederherstellen
(function() {
  if (localStorage.getItem('ww_admin_players_open') === 'false') {
    const body    = document.getElementById('player-list-body');
    const chevron = document.getElementById('players-chevron');
    if (body)    body.style.display     = 'none';
    if (chevron) chevron.style.transform = 'rotate(-90deg)';
  }
})();
JS;

require TEMPLATE_PATH . '/base_end.php';
?>
