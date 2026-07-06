<?php
// Copyright (c) 2026 Andreas Vetter
// Öffentliche Rollengalerie: zeigt alle aktiven Rollen als Kacheln.
// Kein is_killer, kein fill — nur das, was Spieler wissen dürfen.
require_once dirname(__DIR__) . '/core/bootstrap.php';
require_once TEMPLATE_PATH . '/roles_blocks.php';
Auth::requireLogin();

$roles = activeRoles();

$page = ['title' => 'Rollen'];
require TEMPLATE_PATH . '/base.php';
?>

<div class="container page-wrap">

  <div class="page-header">
    <span class="page-header__icon">🎭</span>
    <h1>Rollen</h1>
    <p class="page-header__sub">Alle aktiven Rollen in diesem Spiel.</p>
  </div>

  <div id="roles-gallery-block"><?= render_roles_gallery($roles) ?></div>

</div>

<!-- Rollen-Karten-Modal -->
<div id="role-card-overlay" onclick="closeRoleCard()" role="dialog" aria-modal="true">
  <div class="role-card-modal" id="role-card-modal">
    <div class="role-fx role-fx--modal" id="role-fx-modal-roles">
      <div class="role-card-modal__icon" id="rcd-icon"></div>
    </div>
    <div class="role-card-modal__title role-badge--glow" id="rcd-title"></div>
    <div id="rcd-badge" class="role-card-modal__badge" style="display:none">👁️ Ihr erkennt euch untereinander</div>
    <p class="role-card-modal__desc" id="rcd-desc"></p>
    <div class="role-card-modal__rules" id="rcd-rules" style="display:none"></div>
    <div class="role-card-modal__cooldown" id="rcd-cooldown" style="display:none"></div>
    <div class="role-card-modal__hint">Tippen zum Schließen</div>
  </div>
</div>

<style>
.roles-gallery {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(148px, 1fr));
  gap: 1rem;
  padding-bottom: 1rem;
}
.rg-card {
  background: var(--card-bg);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 1.4rem .85rem 1rem;
  text-align: center;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: .45rem;
  transition: border-color .18s, transform .18s;
}
.rg-card:hover { border-color: var(--accent-border); transform: translateY(-2px); }
.rg-card__icon {
  width: 72px; height: 72px;
  background-size: contain;
  background-repeat: no-repeat;
  background-position: center;
  background-color: var(--panel-bg);
  border-radius: 12px;
  flex-shrink: 0;
}
.rg-card__name {
  font-family: var(--font-display);
  font-size: 1rem;
  color: var(--accent);
  font-weight: 600;
  margin-top: .15rem;
}
.rg-card__desc {
  font-size: .76rem;
  color: var(--text-dim);
  line-height: 1.45;
}
.rg-card__meta {
  display: flex;
  flex-wrap: wrap;
  gap: .3rem;
  justify-content: center;
  margin-top: .15rem;
}

/* ── Rollen-Karte Modal ─────────────────────────────────── */
#role-card-overlay {
  position: fixed; inset: 0; z-index: 500;
  display: flex; align-items: center; justify-content: center;
  padding: 1.25rem;
  background: rgba(0,0,0,0);
  backdrop-filter: blur(0px);
  -webkit-backdrop-filter: blur(0px);
  opacity: 0; pointer-events: none;
  transition: opacity .22s ease, backdrop-filter .22s ease, background .22s ease;
}
#role-card-overlay.open {
  opacity: 1; pointer-events: auto;
  /* Voll deckend: die Seite darf hinter der großen Rollenkarte
     nicht durchscheinen (bis zum Bildschirmrand abgedeckt) */
  background: var(--bg, #0d0d14);
}
.role-card-modal {
  background: var(--card-bg);
  border: 1px solid var(--accent-border);
  border-radius: 20px;
  padding: 2rem 1.75rem 1.5rem;
  max-width: 360px; width: 100%;
  text-align: center;
  box-shadow: 0 12px 56px rgba(0,0,0,.65), 0 0 0 1px var(--accent-border);
  cursor: pointer;
  transform: scale(.84); opacity: 0;
  transition: transform .28s cubic-bezier(.34,1.4,.64,1), opacity .22s ease;
  user-select: none;
}
#role-card-overlay.open .role-card-modal {
  transform: scale(1); opacity: 1;
}
.role-card-modal__icon {
  width: 100%; height: 300px;
  margin: 0 0 1.1rem;
  background-size: contain; background-repeat: no-repeat; background-position: center;
  border-radius: 16px;
  background-color: var(--panel-bg);
  padding: 10px; box-sizing: border-box;
}
.role-card-modal__title {
  font-family: var(--font-display);
  font-size: 1.7rem;
  color: var(--accent);
  letter-spacing: .06em;
  margin-bottom: .5rem;
}
.role-card-modal__badge {
  display: inline-block;
  font-size: .78rem;
  color: var(--accent);
  border: 1px solid var(--accent-border);
  border-radius: 99px;
  padding: .2rem .75rem;
  margin-bottom: .75rem;
}
.role-card-modal__desc {
  color: var(--text-bright);
  font-size: .92rem;
  line-height: 1.55;
  margin-bottom: .65rem;
}
.role-card-modal__rules {
  color: var(--text-dim);
  font-size: .82rem;
  font-style: italic;
  line-height: 1.5;
  border-top: 1px solid var(--border);
  padding-top: .65rem;
  margin-top: .5rem;
}
.role-card-modal__cooldown {
  color: var(--text-dim);
  font-size: .8rem;
  margin-top: .5rem;
}
.role-card-modal__hint {
  color: var(--text-dim);
  font-size: .72rem;
  margin-top: 1.1rem;
  opacity: .6;
  letter-spacing: .04em;
}
</style>

<?php
$rolesJson = json_encode(array_map(fn($r) => [
    'id'          => (int)$r['id'],
    'name'        => $r['name'],
    'icon_url'    => roleIconUrl($r),
    'sichtbar'    => (bool)$r['sichtbar'],
    'description' => roleText($r['description'] ?? '', $r),
    'rules'       => roleText($r['rules'] ?? '', $r),
    'cooldown'    => (int)$r['cooldown'],
], $roles), JSON_UNESCAPED_UNICODE);

$page['inline_js'] = "let ROLES_DATA = {$rolesJson};" . sprintf('const API_BASE=%s;', json_encode(API_URL)) . <<<'JS'

// Body-Klassen aus Einstellungen
if (localStorage.getItem('ww_fx_rolecard') === 'false') document.body.classList.add('fx-rolecard-off');
if (localStorage.getItem('ww_fx_rolename') === 'false') document.body.classList.add('fx-rolename-off');

// Funken-Spawner
function _spawnSpark(wrap) {
  if (document.body.classList.contains('fx-rolecard-off')) return;
  const w = wrap.offsetWidth, h = wrap.offsetHeight;
  const s = document.createElement('span');
  s.className = 'role-spark';
  const sx  = Math.random() * (w + 20) - 10;
  const sy  = h - Math.random() * 10;
  const tx  = (Math.random() - .5) * 40;
  const ty  = -(30 + Math.random() * 40);
  const dur = (.8 + Math.random() * .9).toFixed(2) + 's';
  const sz  = (3 + Math.random() * 4).toFixed(1) + 'px';
  s.style.cssText = `--x:${sx.toFixed(1)}px;--y:${sy.toFixed(1)}px;--tx:${tx.toFixed(1)}px;--ty:${ty.toFixed(1)}px;--dur:${dur};--delay:0s;width:${sz};height:${sz}`;
  wrap.appendChild(s);
  setTimeout(() => s.remove(), parseFloat(dur) * 1000 + 50);
}
let _rolesModalSparkTimer = null;

function openRoleCard(roleId) {
  const r = ROLES_DATA.find(x => x.id === roleId);
  if (!r) return;
  document.getElementById('rcd-icon').style.backgroundImage = `url('${r.icon_url}')`;
  document.getElementById('rcd-title').textContent = r.name;
  const badge = document.getElementById('rcd-badge');
  badge.style.display = r.sichtbar ? '' : 'none';
  document.getElementById('rcd-desc').textContent = r.description;
  const rules = document.getElementById('rcd-rules');
  if (r.rules) { rules.textContent = '📜 ' + r.rules; rules.style.display = ''; }
  else rules.style.display = 'none';
  const cd = document.getElementById('rcd-cooldown');
  if (r.cooldown > 0) { cd.textContent = '⏳ Cooldown: alle ' + (r.cooldown + 1) + ' Nächte'; cd.style.display = ''; }
  else cd.style.display = 'none';
  document.getElementById('role-card-overlay').classList.add('open');
  const wrap = document.getElementById('role-fx-modal-roles');
  if (wrap) _rolesModalSparkTimer = setInterval(() => _spawnSpark(wrap), 180);
}
function closeRoleCard() {
  document.getElementById('role-card-overlay').classList.remove('open');
  clearInterval(_rolesModalSparkTimer);
  _rolesModalSparkTimer = null;
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeRoleCard(); });

const rolesPoll = liveBlocks({
  fetcher: (hash) => apiFetch(API_BASE+'/game.php', {action:'get_roles', blocks_hash: hash}),
  targets: {'roles-gallery-block':'roles-gallery-block'},
  countdownId: 'poll-countdown',
  onData: (data) => { if (Array.isArray(data.roles)) ROLES_DATA = data.roles; },
});
rolesPoll.start();
JS;
require TEMPLATE_PATH . '/base_end.php';
?>
