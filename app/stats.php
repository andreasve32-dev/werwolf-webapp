<?php
// Copyright (c) 2026 Andreas Vetter
require_once dirname(__DIR__) . '/core/bootstrap.php';
require_once TEMPLATE_PATH . '/stats_blocks.php';
Auth::requireLogin();

$state = stats_compute_state();
extract($state);

// ── Seite rendern ─────────────────────────────────────────────

$page = ['title' => 'Statistik'];
require TEMPLATE_PATH . '/base.php';
?>

<div class="container page-wrap">

  <div class="page-header">
    <span class="page-header__icon">📊</span>
    <h1>Spielstatistik</h1>
    <p class="page-header__sub">Auswertung aller abgeschlossenen Spiele</p>
  </div>

  <div id="stats-content"><?= render_stats_content($state) ?></div>

</div>

<style>
/* ── KPI ─────────────────────────────────────────────────── */
.stats-kpi-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: .75rem;
  margin-bottom: 1.5rem;
}
@media (max-width: 480px) { .stats-kpi-grid { grid-template-columns: repeat(2, 1fr); } }
.stats-kpi {
  background: var(--card-bg);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 1rem .75rem;
  text-align: center;
  transition: border-color .18s;
}
.stats-kpi:hover { border-color: var(--accent-border); }
.stats-kpi__val {
  font-family: var(--font-display);
  font-size: 2rem;
  color: var(--accent);
  line-height: 1;
  margin-bottom: .3rem;
}
.stats-kpi__label {
  font-size: .72rem;
  color: var(--text-dim);
  text-transform: uppercase;
  letter-spacing: .06em;
}

/* ── 2-Spalten Reihe ─────────────────────────────────────── */
.stats-row-2 {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1.5rem;
  margin-bottom: 1.5rem;
}
@media (max-width: 580px) { .stats-row-2 { grid-template-columns: 1fr; } }

/* ── Legende ─────────────────────────────────────────────── */
.stats-legend {
  display: flex;
  flex-wrap: wrap;
  gap: .4rem .75rem;
  justify-content: center;
}
.stats-legend__item {
  display: flex;
  align-items: center;
  gap: .35rem;
  font-size: .75rem;
  color: var(--text-dim);
}
.stats-legend__dot {
  width: 9px;
  height: 9px;
  border-radius: 50%;
  flex-shrink: 0;
}

/* ── Spieler-Profil-Raster ───────────────────────────────── */
.player-profile-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: .6rem;
}
@media (max-width: 600px) { .player-profile-grid { grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 380px) { .player-profile-grid { grid-template-columns: repeat(2, 1fr); } }

.player-profile-card {
  background: var(--panel-bg);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: .75rem .5rem;
  cursor: pointer;
  text-align: center;
  transition: border-color .18s, background .18s, transform .12s;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: .35rem;
}
.player-profile-card:hover {
  border-color: var(--accent-border);
  background: var(--card-bg);
  transform: translateY(-2px);
}
.player-profile-card.active {
  border-color: var(--accent);
  background: var(--card-bg);
  box-shadow: 0 0 0 2px var(--accent-border);
}

.player-profile-card__avatar {
  width: 2.4rem;
  height: 2.4rem;
  border-radius: 50%;
  background: var(--accent-border);
  color: var(--accent);
  font-family: var(--font-display);
  font-size: 1.1rem;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.player-profile-card__name {
  font-size: .8rem;
  font-weight: 600;
  color: var(--text-bright);
  word-break: break-word;
  line-height: 1.2;
}
.player-profile-card__sub {
  font-size: .68rem;
  color: var(--text-dim);
}

/* ── Stat-Chips ──────────────────────────────────────────── */
.player-stat-chips {
  display: flex;
  flex-wrap: wrap;
  gap: .45rem;
}
.stat-chip {
  background: var(--panel-bg);
  border: 1px solid var(--border);
  border-radius: 99px;
  padding: .25rem .75rem;
  font-size: .75rem;
  color: var(--text-dim);
  white-space: nowrap;
}
.stat-chip strong { color: var(--text-bright); }

/* ── Akkordeon ───────────────────────────────────────────── */
.stats-acc-hdr {
  display: flex; align-items: center; justify-content: space-between;
  width: 100%; background: none; border: none; cursor: pointer;
  padding: 0; color: inherit; text-align: left; gap: .5rem;
}
.stats-acc-hdr:hover .stats-acc-chevron { color: var(--text-bright); }
.stats-acc-chevron {
  font-size: 1.3rem; color: var(--text-dim);
  transition: transform .2s ease; flex-shrink: 0; line-height: 1;
}
.stats-acc.acc-open .stats-acc-chevron { transform: rotate(90deg); }
.stats-acc-body { display: none; }
.stats-acc.acc-open .stats-acc-body { display: block; }

/* ── Spieler-Detail Kreisdiagramme (max 2 nebeneinander) ── */
.detail-charts-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 1rem;
  margin-top: 1rem;
}
@media (min-width: 560px) {
  .detail-charts-grid { grid-template-columns: repeat(3, 1fr); }
}
</style>

<?php
$page['inline_js'] = sprintf(
    'let STATS=%s,PLAYERS=%s; const API_BASE=%s;',
    json_encode([
        'cause'   => $causePieData,
        'phase'   => $phasePieData,
        'rounds'  => $roundBarData,
        'accused' => $accuseBarData,
    ], JSON_UNESCAPED_UNICODE),
    json_encode($playerDetails, JSON_UNESCAPED_UNICODE),
    json_encode(API_URL)
);

$page['inline_js'] .= <<<'JS'

// ── Canvas-Hilfsfunktionen ────────────────────────────────────
function chartTextColor()   { return getComputedStyle(document.documentElement).getPropertyValue('--text-dim').trim()    || '#888'; }
function chartBrightColor() { return getComputedStyle(document.documentElement).getPropertyValue('--text-bright').trim() || '#eee'; }
function chartBgColor()     { return getComputedStyle(document.documentElement).getPropertyValue('--panel-bg').trim()    || '#1a1a2e'; }

// ── Donut-Diagramm ────────────────────────────────────────────
function drawDonutEl(canvas, legendEl, data, size) {
  if (!canvas || !data || !data.length) {
    if (canvas) { const ctx = canvas.getContext('2d'); canvas.width=size; canvas.height=size; ctx.fillStyle=chartTextColor(); ctx.font='11px sans-serif'; ctx.textAlign='center'; ctx.textBaseline='middle'; ctx.fillText('Keine Daten', size/2, size/2); }
    return;
  }
  const dpr = window.devicePixelRatio || 1;
  canvas.width  = size * dpr;
  canvas.height = size * dpr;
  canvas.style.width  = size + 'px';
  canvas.style.height = size + 'px';
  const ctx = canvas.getContext('2d');
  ctx.scale(dpr, dpr);

  const cx = size/2, cy = size/2, R = size/2 - 8, r = R * 0.54;
  const total = data.reduce((s, d) => s + d.value, 0);
  if (!total) return;
  let angle = -Math.PI / 2;

  data.forEach(d => {
    const slice = (d.value / total) * 2 * Math.PI;
    ctx.beginPath(); ctx.moveTo(cx, cy);
    ctx.arc(cx, cy, R, angle, angle + slice);
    ctx.closePath(); ctx.fillStyle = d.color; ctx.fill();
    ctx.beginPath(); ctx.moveTo(cx, cy);
    ctx.arc(cx, cy, R, angle, angle + slice);
    ctx.closePath(); ctx.strokeStyle = chartBgColor(); ctx.lineWidth = 1.5; ctx.stroke();
    angle += slice;
  });

  ctx.beginPath(); ctx.arc(cx, cy, r, 0, 2*Math.PI);
  ctx.fillStyle = chartBgColor(); ctx.fill();

  ctx.fillStyle = chartBrightColor(); ctx.font = `bold ${Math.round(size*0.13)}px sans-serif`;
  ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
  ctx.fillText(total, cx, cy - size*0.04);
  ctx.font = `${Math.round(size*0.07)}px sans-serif`;
  ctx.fillStyle = chartTextColor();
  ctx.fillText('gesamt', cx, cy + size*0.1);

  if (legendEl) {
    legendEl.innerHTML = data.map(d =>
      `<div class="stats-legend__item">
        <span class="stats-legend__dot" style="background:${d.color}"></span>
        <span>${escHtml(d.label)} (${d.value})</span>
      </div>`
    ).join('');
  }
}

function drawDonut(id, legId, data) {
  drawDonutEl(document.getElementById(id), document.getElementById(legId), data, 220);
}

// ── Einfaches Balkendiagramm ─────────────────────────────────
function drawSimpleBar(canvasId, data, valueKey, color) {
  const canvas = document.getElementById(canvasId);
  if (!canvas || !data.length) return;
  const dpr = window.devicePixelRatio || 1;
  const W   = canvas.offsetWidth || 500;
  const H   = parseInt(canvas.getAttribute('height')) || 180;
  canvas.width  = W * dpr; canvas.height = H * dpr;
  const ctx = canvas.getContext('2d');
  ctx.scale(dpr, dpr);
  const pad = {top:16,right:10,bottom:48,left:36};
  const cW = W-pad.left-pad.right, cH = H-pad.top-pad.bottom;
  const maxVal = Math.max(...data.map(d => d[valueKey]||0), 1);
  const barW = Math.min(50, (cW/data.length)-6);
  const gap  = (cW - barW*data.length) / (data.length+1);
  const dim = chartTextColor(), bright = chartBrightColor();
  ctx.strokeStyle='rgba(128,128,128,0.15)'; ctx.lineWidth=1;
  for (let i=0;i<=4;i++) {
    const y=pad.top+cH-(i/4)*cH;
    ctx.beginPath(); ctx.moveTo(pad.left,y); ctx.lineTo(pad.left+cW,y); ctx.stroke();
    if(i>0){ctx.fillStyle=dim;ctx.font='9px sans-serif';ctx.textAlign='right';ctx.fillText(Math.round(maxVal*i/4),pad.left-4,y+3);}
  }
  data.forEach((d,i)=>{
    const val=d[valueKey]||0, x=pad.left+gap+i*(barW+gap), h=(val/maxVal)*cH, y=pad.top+cH-h;
    ctx.fillStyle=color; roundRect(ctx,x,y,barW,h,4);
    if(val>0){ctx.fillStyle=bright;ctx.font='bold 9px sans-serif';ctx.textAlign='center';ctx.fillText(val,x+barW/2,y-4);}
    const label=d.label.length>9?d.label.slice(0,8)+'…':d.label;
    ctx.save();ctx.translate(x+barW/2,pad.top+cH+10);ctx.rotate(-Math.PI/5);
    ctx.fillStyle=dim;ctx.font='10px sans-serif';ctx.textAlign='right';ctx.fillText(label,0,0);ctx.restore();
  });
}

// ── Abgerundetes Rechteck ─────────────────────────────────────
function roundRect(ctx, x, y, w, h, r) {
  if (h<=0) return;
  if (typeof r==='number') r=[r,r,r,r];
  const [tl,tr,br,bl]=r;
  ctx.beginPath();
  ctx.moveTo(x+tl,y); ctx.lineTo(x+w-tr,y); ctx.quadraticCurveTo(x+w,y,x+w,y+tr);
  ctx.lineTo(x+w,y+h-br); ctx.quadraticCurveTo(x+w,y+h,x+w-br,y+h);
  ctx.lineTo(x+bl,y+h); ctx.quadraticCurveTo(x,y+h,x,y+h-bl);
  ctx.lineTo(x,y+tl); ctx.quadraticCurveTo(x,y,x+tl,y);
  ctx.closePath(); ctx.fill();
}

// ── Gruppiertes Balkendiagramm Tag/Nacht ─────────────────────
function drawGroupedBar(canvasId, data) {
  const canvas = document.getElementById(canvasId);
  if (!canvas || !data.length) return;
  const dpr = window.devicePixelRatio || 1;
  const W   = canvas.offsetWidth || 500;
  const H   = parseInt(canvas.getAttribute('height')) || 180;
  canvas.width  = W * dpr; canvas.height = H * dpr;
  const ctx = canvas.getContext('2d');
  ctx.scale(dpr, dpr);
  const pad = {top:20, right:10, bottom:52, left:36};
  const cW = W - pad.left - pad.right;
  const cH = H - pad.top - pad.bottom;
  const maxVal = Math.max(...data.map(d => Math.max(d.day||0, d.night||0)), 1);
  const groupW = cW / data.length;
  const barW   = Math.min(18, groupW / 2 - 3);
  const gap    = 3;
  const dim = chartTextColor(), bright = chartBrightColor();
  const dayColor   = 'rgba(251,191,36,.88)';  // Gelb/Amber
  const nightColor = 'rgba(129,140,248,.88)'; // Violett

  // Rasterlinien
  ctx.strokeStyle = 'rgba(128,128,128,0.15)'; ctx.lineWidth = 1;
  for (let i = 0; i <= 4; i++) {
    const y = pad.top + cH - (i/4)*cH;
    ctx.beginPath(); ctx.moveTo(pad.left, y); ctx.lineTo(pad.left+cW, y); ctx.stroke();
    if (i > 0) { ctx.fillStyle=dim; ctx.font='9px sans-serif'; ctx.textAlign='right'; ctx.fillText(Math.round(maxVal*i/4), pad.left-4, y+3); }
  }

  data.forEach((d, i) => {
    const groupX = pad.left + i * groupW + groupW / 2;
    const xDay   = groupX - barW - gap/2;
    const xNight = groupX + gap/2;

    // Tag-Balken (links)
    if (d.day > 0) {
      const h = (d.day/maxVal)*cH;
      const y = pad.top + cH - h;
      ctx.fillStyle = dayColor;
      roundRect(ctx, xDay, y, barW, h, [3,3,0,0]);
      ctx.fillStyle = bright; ctx.font = 'bold 8px sans-serif'; ctx.textAlign = 'center';
      ctx.fillText(d.day, xDay + barW/2, y - 3);
    }
    // Nacht-Balken (rechts)
    if (d.night > 0) {
      const h = (d.night/maxVal)*cH;
      const y = pad.top + cH - h;
      ctx.fillStyle = nightColor;
      roundRect(ctx, xNight, y, barW, h, [3,3,0,0]);
      ctx.fillStyle = bright; ctx.font = 'bold 8px sans-serif'; ctx.textAlign = 'center';
      ctx.fillText(d.night, xNight + barW/2, y - 3);
    }

    // Rundenbezeichnung
    ctx.save();
    ctx.translate(groupX, pad.top + cH + 10);
    ctx.rotate(-Math.PI / 5);
    ctx.fillStyle = dim; ctx.font = '10px sans-serif'; ctx.textAlign = 'right';
    ctx.fillText(d.label, 0, 0);
    ctx.restore();
  });

  // Legende
  const legendY = H - 14;
  const items = [['☀️ Tag', dayColor], ['🌕 Nacht', nightColor]];
  let lx = pad.left;
  items.forEach(([label, color]) => {
    ctx.fillStyle = color; ctx.fillRect(lx, legendY - 7, 10, 10);
    ctx.fillStyle = dim; ctx.font = '10px sans-serif'; ctx.textAlign = 'left';
    ctx.fillText(label, lx + 13, legendY + 1);
    lx += 75;
  });
}

// ── Mobile Chartgröße ────────────────────────────────────────
function mobileH(defaultH) {
  return window.innerWidth < 600 ? Math.round(defaultH * 0.65) : defaultH;
}

// ── Alle globalen Charts ──────────────────────────────────────
function drawAll() {
  drawDonut('chart-cause', 'legend-cause', STATS.cause);
  drawDonut('chart-phase', 'legend-phase', STATS.phase);
  const cRounds = document.getElementById('chart-rounds');
  if (cRounds) cRounds.setAttribute('height', mobileH(160));
  if (STATS.rounds.length && document.getElementById('acc-rounds')?.classList.contains('acc-open'))
    drawGroupedBar('chart-rounds', STATS.rounds);
  const cAcc = document.getElementById('chart-accused');
  if (cAcc) cAcc.setAttribute('height', mobileH(180));
  if (STATS.accused.length && document.getElementById('acc-accused')?.classList.contains('acc-open'))
    drawSimpleBar('chart-accused', STATS.accused, 'stimmen', 'rgba(234,88,12,0.8)');
  if (_openPid !== null && document.getElementById('acc-players')?.classList.contains('acc-open'))
    renderPlayerDetail(_openPid);
}
window.addEventListener('load',   () => { restoreAccState(); drawAll(); });
window.addEventListener('resize', () => { clearTimeout(window._rt); window._rt = setTimeout(drawAll, 120); });

// ── Akkordeon ─────────────────────────────────────────────────
const _ACC_KEY = 'ww_stats_acc';

function toggleAcc(id) {
  const card = document.getElementById(id);
  if (!card) return;
  const wasOpen = card.classList.contains('acc-open');
  card.classList.toggle('acc-open', !wasOpen);
  if (!wasOpen) {
    // Sektion gerade geöffnet → Charts neu zeichnen
    const cRounds = document.getElementById('chart-rounds');
    if (cRounds) cRounds.setAttribute('height', mobileH(160));
    const cAcc = document.getElementById('chart-accused');
    if (cAcc) cAcc.setAttribute('height', mobileH(180));
    if (id === 'acc-rounds'  && STATS.rounds.length)  drawGroupedBar('chart-rounds', STATS.rounds);
    if (id === 'acc-accused' && STATS.accused.length) drawSimpleBar('chart-accused', STATS.accused, 'stimmen', 'rgba(234,88,12,0.8)');
    if (id === 'acc-players' && _openPid !== null)    renderPlayerDetail(_openPid);
  }
  saveAccState();
}

function saveAccState() {
  const open = [];
  document.querySelectorAll('.stats-acc.acc-open').forEach(el => open.push(el.id));
  LS.set(_ACC_KEY, open);
}

function restoreAccState() {
  const open = LS.get(_ACC_KEY) || [];
  document.querySelectorAll('.stats-acc').forEach(el => {
    el.classList.toggle('acc-open', open.includes(el.id));
  });
}

// ── Spieler-Detail ────────────────────────────────────────────
let _openPid = null;

function togglePlayer(pid) {
  if (_openPid === pid) { closePlayer(); return; }
  _openPid = pid;
  document.querySelectorAll('.player-profile-card').forEach(c => c.classList.remove('active'));
  const card = document.getElementById('pcard-'+pid);
  if (card) {
    card.classList.add('active');
    card.scrollIntoView({behavior:'smooth', block:'nearest'});
  }
  const panel = document.getElementById('player-detail');
  panel.style.display = 'block';
  renderPlayerDetail(pid);

  // Scroll zu Panel
  setTimeout(() => panel.scrollIntoView({behavior:'smooth', block:'nearest'}), 80);
}

function closePlayer() {
  _openPid = null;
  document.getElementById('player-detail').style.display = 'none';
  document.querySelectorAll('.player-profile-card').forEach(c => c.classList.remove('active'));
}

function renderPlayerDetail(pid) {
  const p = PLAYERS[pid];
  if (!p) return;

  document.getElementById('detail-name').textContent = p.name;

  const survive = p.spiele > 0 ? Math.round(p.ueberlebt / p.spiele * 100) : 0;
  document.getElementById('detail-chips').innerHTML = [
    chip('🎮 Spiele', p.spiele),
    chip('✅ Überlebt', p.ueberlebt),
    chip('💀 Gestorben', p.gestorben),
    chip('⚖️ Gehenkt', p.gehenkt),
    chip('☀️ Tode am Tag', p.tod_tag),
    chip('🌕 Tode in der Nacht', p.tod_nacht),
    chip('🗳️ Stimmen gegeben', p.stimmen_gegeben),
    chip('🎯 Anklagen erhalten', p.anklagen),
    chip('🔮 Untersuchungen', p.untersucht),
    chip('👁️ Untersucht worden', p.untersucht_worden),
    chip('📊 Überlebensrate', survive + '%'),
  ].join('');

  drawDonutEl(
    document.getElementById('detail-chart-rollen'),
    document.getElementById('detail-legend-rollen'),
    p.rollen, 150
  );
  drawDonutEl(
    document.getElementById('detail-chart-tod'),
    document.getElementById('detail-legend-tod'),
    p.tod, 150
  );
  // Tag/Nacht-Donut
  const phaseData = [];
  if (p.tod_tag   > 0) phaseData.push({label:'☀️ Tag',   value:p.tod_tag,   color:'rgba(251,191,36,.9)'});
  if (p.tod_nacht > 0) phaseData.push({label:'🌕 Nacht', value:p.tod_nacht, color:'rgba(129,140,248,.9)'});
  drawDonutEl(
    document.getElementById('detail-chart-phase'),
    document.getElementById('detail-legend-phase'),
    phaseData, 150
  );
}

function chip(label, val) {
  return `<div class="stat-chip"><strong>${escHtml(String(val))}</strong> ${escHtml(label)}</div>`;
}

// ── Live-Update: Statistiken periodisch aktualisieren ─────────
let _statsVer = null; // Server rechnet nur bei geänderter Version neu
const statsPoll = liveBlocks({
  fetcher: () => apiFetch(API_BASE+'/game.php', {action:'get_stats', version:_statsVer}),
  targets: {'stats-content':'stats-content'},
  countdownId: 'poll-countdown',
  onData: (data) => {
    if (data.version !== undefined) _statsVer = data.version;
    if (!data.blocks) return; // Version unverändert — nichts zu tun
    if (data.stats)   STATS   = data.stats;
    if (data.players) PLAYERS = data.players;
    // Der Block-Swap hat Akkordeon-/Detail-Zustand zurückgesetzt — wiederherstellen:
    restoreAccState();
    if (_openPid !== null) {
      const card = document.getElementById('pcard-' + _openPid);
      if (card) card.classList.add('active');
      const panel = document.getElementById('player-detail');
      if (panel) panel.style.display = 'block';
    }
    drawAll();
  },
});
statsPoll.start();
JS;

require TEMPLATE_PATH . '/base_end.php';
?>
