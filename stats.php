<?php
// Copyright (c) 2026 Andreas Vetter
require_once __DIR__ . '/core/bootstrap.php';
Auth::requireLogin();

// ── Globale Gesamt-Daten ──────────────────────────────────────

$totals = Database::queryOne("
    SELECT
        (SELECT COUNT(*) FROM games WHERE status='finished') AS spiele_beendet,
        (SELECT COUNT(*) FROM games WHERE status='running')  AS spiele_laufend,
        (SELECT COUNT(*) FROM games WHERE status='lobby')    AS spiele_lobby,
        (SELECT COUNT(*) FROM players WHERE is_admin=0)      AS spieler_gesamt,
        (SELECT COUNT(*) FROM deaths)                        AS tode_gesamt,
        (SELECT ROUND(AVG(round),1) FROM games WHERE status='finished' AND round > 0) AS avg_runden,
        (SELECT COUNT(*) FROM votes)                         AS stimmen_gesamt
");

$deathsByCause = Database::query(
    "SELECT is_gehenkt, COUNT(*) AS cnt FROM deaths GROUP BY is_gehenkt ORDER BY cnt DESC"
);
$deathsByPhase = Database::query(
    "SELECT phase, COUNT(*) AS cnt FROM deaths GROUP BY phase ORDER BY cnt DESC"
);
$deathsPerRound = Database::query(
    "SELECT round, COUNT(*) AS cnt FROM deaths GROUP BY round ORDER BY round ASC LIMIT 20"
);
$topAccused = Database::query("
    SELECT p.display_name, COUNT(*) AS stimmen
    FROM votes v JOIN players p ON p.id = v.target_id
    GROUP BY v.target_id ORDER BY stimmen DESC LIMIT 8
");

// ── Per-Spieler Daten ─────────────────────────────────────────

$allPlayers = Database::query("
    SELECT p.id, p.display_name,
           COUNT(DISTINCT gp.game_id)                                  AS spiele,
           SUM(CASE WHEN gp.is_alive=1 THEN 1 ELSE 0 END)             AS ueberlebt,
           SUM(CASE WHEN gp.is_alive=0 THEN 1 ELSE 0 END)             AS gestorben
    FROM players p
    JOIN game_players gp ON gp.player_id = p.id
    JOIN games g         ON g.id = gp.game_id AND g.status = 'finished'
    WHERE p.is_admin = 0
    GROUP BY p.id
    ORDER BY spiele DESC, p.display_name
");

$rolesByPlayer = Database::query("
    SELECT gp.player_id, r.name AS rolle, COUNT(*) AS cnt
    FROM game_players gp
    JOIN roles r  ON r.id  = gp.role_id
    JOIN games g  ON g.id  = gp.game_id AND g.status = 'finished'
    GROUP BY gp.player_id, gp.role_id
    ORDER BY gp.player_id, cnt DESC
");

$deathsByPlayerCause = Database::query("
    SELECT d.player_id, d.is_gehenkt, COUNT(*) AS cnt
    FROM deaths d
    JOIN games g ON g.id = d.game_id AND g.status = 'finished'
    GROUP BY d.player_id, d.is_gehenkt
");

$votesGivenMap    = [];
$votesReceivedMap = [];
foreach (Database::query("SELECT voter_id AS pid, COUNT(*) AS cnt FROM votes GROUP BY voter_id") as $r) {
    $votesGivenMap[(int)$r['pid']] = (int)$r['cnt'];
}
foreach (Database::query("SELECT target_id AS pid, COUNT(*) AS cnt FROM votes GROUP BY target_id") as $r) {
    $votesReceivedMap[(int)$r['pid']] = (int)$r['cnt'];
}
$hangedMap = [];
foreach (Database::query("SELECT player_id AS pid, COUNT(*) AS cnt FROM deaths WHERE is_gehenkt=1 GROUP BY player_id") as $r) {
    $hangedMap[(int)$r['pid']] = (int)$r['cnt'];
}

// Rollen-Farb-Palette (10 Farben, Rotation bei mehr Rollen)
$rolePalette = ['#818cf8','#a78bfa','#c084fc','#e879f9','#f472b6',
                '#fb7185','#fbbf24','#34d399','#22d3ee','#60a5fa'];

// Aggregiere alle per-Spieler Daten in ein JS-taugliches Array
$rolesGrouped  = [];
foreach ($rolesByPlayer as $r) {
    $rolesGrouped[(int)$r['player_id']][] = $r;
}
$deathsGrouped = [];
foreach ($deathsByPlayerCause as $r) {
    $deathsGrouped[(int)$r['player_id']][] = $r;
}

$causeLabels = [0 => 'Ermordet', 1 => 'Gehenkt'];
$causeColors = [0 => '#ef4444',  1 => '#f97316'];

$playerDetails = [];
foreach ($allPlayers as $p) {
    $pid = (int)$p['id'];

    $rollen = [];
    foreach ($rolesGrouped[$pid] ?? [] as $i => $r) {
        $rollen[] = [
            'label' => $r['rolle'],
            'value' => (int)$r['cnt'],
            'color' => $rolePalette[$i % count($rolePalette)],
        ];
    }

    $tod = [];
    foreach ($deathsGrouped[$pid] ?? [] as $r) {
        $key  = (int)$r['is_gehenkt'];
        $tod[] = [
            'label' => $causeLabels[$key] ?? 'Unbekannt',
            'value' => (int)$r['cnt'],
            'color' => $causeColors[$key] ?? '#6b7280',
        ];
    }

    $playerDetails[$pid] = [
        'name'             => $p['display_name'],
        'spiele'           => (int)$p['spiele'],
        'ueberlebt'        => (int)$p['ueberlebt'],
        'gestorben'        => (int)$p['gestorben'],
        'gehenkt'          => $hangedMap[$pid] ?? 0,
        'stimmen_gegeben'  => $votesGivenMap[$pid]    ?? 0,
        'anklagen'         => $votesReceivedMap[$pid]  ?? 0,
        'rollen'           => $rollen,
        'tod'              => $tod,
    ];
}

// ── Globale Chart-Daten ───────────────────────────────────────

$causePieData = [];
foreach ($deathsByCause as $row) {
    $key = (int)$row['is_gehenkt'];
    $causePieData[] = [
        'label' => $causeLabels[$key] ?? 'Unbekannt',
        'value' => (int)$row['cnt'],
        'color' => $causeColors[$key] ?? '#6b7280',
    ];
}
$phasePieData = [];
foreach ($deathsByPhase as $row) {
    $phasePieData[] = [
        'label' => ['day'=>'Tag','night'=>'Nacht'][$row['phase']] ?? $row['phase'],
        'value' => (int)$row['cnt'],
        'color' => ['day'=>'#fbbf24','night'=>'#818cf8'][$row['phase']] ?? '#6b7280',
    ];
}
$roundBarData = [];
foreach ($deathsPerRound as $row) {
    $roundBarData[] = ['label' => 'R'.(int)$row['round'], 'cnt' => (int)$row['cnt']];
}
$accuseBarData = [];
foreach ($topAccused as $row) {
    $accuseBarData[] = ['label' => $row['display_name'], 'stimmen' => (int)$row['stimmen']];
}

$hasData = ($totals['spiele_beendet'] ?? 0) > 0 || ($totals['tode_gesamt'] ?? 0) > 0;

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

<?php if (!$hasData): ?>
  <div class="card card--glow animate-in" style="text-align:center;padding:3rem 1.5rem">
    <div style="font-size:3rem;margin-bottom:.75rem">📭</div>
    <div style="font-family:var(--font-display);font-size:1.2rem;color:var(--text-bright)">
      Noch keine Spieldaten vorhanden
    </div>
    <p class="text-dim text-sm mt-2">Statistiken erscheinen sobald das erste Spiel beendet wurde.</p>
  </div>
<?php else: ?>

  <!-- ── Kennzahlen ─────────────────────────────────────────── -->
  <div class="stats-kpi-grid animate-in">
    <div class="stats-kpi">
      <div class="stats-kpi__val"><?= (int)($totals['spiele_beendet'] ?? 0) ?></div>
      <div class="stats-kpi__label">Spiele beendet</div>
    </div>
    <div class="stats-kpi">
      <div class="stats-kpi__val"><?= (int)($totals['spieler_gesamt'] ?? 0) ?></div>
      <div class="stats-kpi__label">Spieler</div>
    </div>
    <div class="stats-kpi">
      <div class="stats-kpi__val"><?= (int)($totals['tode_gesamt'] ?? 0) ?></div>
      <div class="stats-kpi__label">Tode gesamt</div>
    </div>
    <div class="stats-kpi">
      <div class="stats-kpi__val">
        <?= $totals['avg_runden'] ? number_format((float)$totals['avg_runden'], 1) : '–' ?>
      </div>
      <div class="stats-kpi__label">Ø Runden/Spiel</div>
    </div>
    <div class="stats-kpi">
      <div class="stats-kpi__val"><?= (int)($totals['stimmen_gesamt'] ?? 0) ?></div>
      <div class="stats-kpi__label">Abstimmungen</div>
    </div>
    <?php $laufend = (int)($totals['spiele_laufend'] ?? 0) + (int)($totals['spiele_lobby'] ?? 0); ?>
    <div class="stats-kpi">
      <div class="stats-kpi__val"><?= $laufend ?></div>
      <div class="stats-kpi__label">Aktive Spiele</div>
    </div>
  </div>

  <!-- ── Todesursachen + Tag/Nacht ─────────────────────────── -->
  <div class="stats-row-2 animate-in" style="animation-delay:.05s">
    <div class="card">
      <div class="section-title">Todesursachen</div>
      <?php if (empty($causePieData)): ?>
        <p class="text-dim text-sm">Keine Daten</p>
      <?php else: ?>
      <div style="display:flex;flex-direction:column;align-items:center;gap:1rem">
        <canvas id="chart-cause" width="220" height="220" style="max-width:220px"></canvas>
        <div class="stats-legend" id="legend-cause"></div>
      </div>
      <?php endif; ?>
    </div>
    <div class="card">
      <div class="section-title">Tag vs. Nacht</div>
      <?php if (empty($phasePieData)): ?>
        <p class="text-dim text-sm">Keine Daten</p>
      <?php else: ?>
      <div style="display:flex;flex-direction:column;align-items:center;gap:1rem">
        <canvas id="chart-phase" width="220" height="220" style="max-width:220px"></canvas>
        <div class="stats-legend" id="legend-phase"></div>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ── Tode pro Runde ─────────────────────────────────────── -->
  <?php if (!empty($roundBarData)): ?>
  <div class="card animate-in" style="animation-delay:.08s">
    <div class="section-title">Tode pro Runde (alle Spiele)</div>
    <div style="overflow-x:auto">
      <canvas id="chart-rounds" height="160"
              style="min-width:<?= max(400, count($roundBarData) * 42) ?>px;width:100%">
      </canvas>
    </div>
  </div>
  <?php endif; ?>

  <!-- ── Häufigste Anklagen ─────────────────────────────────── -->
  <?php if (!empty($accuseBarData)): ?>
  <div class="card animate-in" style="animation-delay:.1s">
    <div class="section-title">Häufigste Anklagen (Abstimmungen erhalten)</div>
    <div style="overflow-x:auto">
      <canvas id="chart-accused" height="180"
              style="min-width:<?= max(300, count($accuseBarData) * 68) ?>px;width:100%">
      </canvas>
    </div>
  </div>
  <?php endif; ?>

  <!-- ── Spielerliste ───────────────────────────────────────── -->
  <?php if (!empty($allPlayers)): ?>
  <div class="card animate-in" style="animation-delay:.12s">
    <div class="section-title">Spieler-Profile</div>
    <p class="text-dim text-xs mb-3">
      Spieler anklicken für Rollen-Verteilung, Todesursachen und persönliche Statistiken.
    </p>

    <!-- Spielerkarten-Raster -->
    <div class="player-profile-grid" id="player-grid">
      <?php foreach ($allPlayers as $p): ?>
      <?php $pid = (int)$p['id']; ?>
      <button class="player-profile-card" id="pcard-<?= $pid ?>"
              onclick="togglePlayer(<?= $pid ?>)" type="button">
        <div class="player-profile-card__avatar">
          <?= mb_strtoupper(mb_substr($p['display_name'], 0, 1)) ?>
        </div>
        <div class="player-profile-card__name"><?= e($p['display_name']) ?></div>
        <div class="player-profile-card__sub">
          <?= (int)$p['spiele'] ?> Sp &middot;
          <span style="color:var(--alert-success-text,#86efac)"><?= (int)$p['ueberlebt'] ?>✓</span>
          <span style="color:var(--danger-text,#f87171)"><?= (int)$p['gestorben'] ?>✗</span>
        </div>
      </button>
      <?php endforeach; ?>
    </div>

    <!-- Aufklapp-Detail-Panel -->
    <div id="player-detail" style="display:none;margin-top:1.25rem;
         border-top:1px solid var(--border);padding-top:1.25rem">

      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem">
        <div style="font-family:var(--font-display);font-size:1.15rem;color:var(--text-bright)"
             id="detail-name"></div>
        <button onclick="closePlayer()" class="btn btn--ghost btn--sm">✕</button>
      </div>

      <!-- Stat-Chips -->
      <div class="player-stat-chips" id="detail-chips"></div>

      <!-- 2 Kreisdiagramme nebeneinander -->
      <div class="stats-row-2" style="margin-top:1rem;margin-bottom:0">
        <div>
          <div class="text-xs text-dim" style="text-align:center;margin-bottom:.5rem;text-transform:uppercase;letter-spacing:.06em">
            Rollen gespielt
          </div>
          <div style="display:flex;flex-direction:column;align-items:center;gap:.75rem">
            <canvas id="detail-chart-rollen" width="170" height="170" style="max-width:170px"></canvas>
            <div class="stats-legend" id="detail-legend-rollen"></div>
          </div>
        </div>
        <div>
          <div class="text-xs text-dim" style="text-align:center;margin-bottom:.5rem;text-transform:uppercase;letter-spacing:.06em">
            Todesursachen
          </div>
          <div style="display:flex;flex-direction:column;align-items:center;gap:.75rem">
            <canvas id="detail-chart-tod" width="170" height="170" style="max-width:170px"></canvas>
            <div class="stats-legend" id="detail-legend-tod"></div>
          </div>
        </div>
      </div>

    </div>
  </div>
  <?php endif; ?>

<?php endif; // $hasData ?>

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
</style>

<?php
$page['inline_js'] = sprintf(
    'const STATS=%s,PLAYERS=%s;',
    json_encode([
        'cause'   => $causePieData,
        'phase'   => $phasePieData,
        'rounds'  => $roundBarData,
        'accused' => $accuseBarData,
    ], JSON_UNESCAPED_UNICODE),
    json_encode($playerDetails, JSON_UNESCAPED_UNICODE)
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

// ── Alle globalen Charts ──────────────────────────────────────
function drawAll() {
  drawDonut('chart-cause', 'legend-cause', STATS.cause);
  drawDonut('chart-phase', 'legend-phase', STATS.phase);
  if (STATS.rounds.length)  drawSimpleBar('chart-rounds',  STATS.rounds,  'cnt',     'rgba(129,140,248,0.85)');
  if (STATS.accused.length) drawSimpleBar('chart-accused', STATS.accused, 'stimmen', 'rgba(234,88,12,0.8)');
  if (_openPid !== null) renderPlayerDetail(_openPid);
}
window.addEventListener('load',   drawAll);
window.addEventListener('resize', () => { clearTimeout(window._rt); window._rt = setTimeout(drawAll, 120); });

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
    chip('🗳️ Stimmen gegeben', p.stimmen_gegeben),
    chip('🎯 Anklagen erhalten', p.anklagen),
    chip('📊 Überlebensrate', survive + '%'),
  ].join('');

  drawDonutEl(
    document.getElementById('detail-chart-rollen'),
    document.getElementById('detail-legend-rollen'),
    p.rollen, 170
  );
  drawDonutEl(
    document.getElementById('detail-chart-tod'),
    document.getElementById('detail-legend-tod'),
    p.tod, 170
  );
}

function chip(label, val) {
  return `<div class="stat-chip"><strong>${escHtml(String(val))}</strong> ${escHtml(label)}</div>`;
}
JS;

require TEMPLATE_PATH . '/base_end.php';
?>
