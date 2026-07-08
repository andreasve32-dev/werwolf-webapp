<?php
// Copyright (c) 2026 Andreas Vetter
// Log-Ansicht für die Spielleitung — Teil des Debug-Menüs, nur im Debug-Modus
// nutzbar. Liest logs/app.log (siehe LOG_PATH/bootstrap.php), klassifiziert die
// Einträge nach Schweregrad und erlaubt Filtern/Sortieren + Leeren.
require_once dirname(__DIR__) . '/core/bootstrap.php';
Auth::requireAdmin();

// ── AJAX: Log leeren ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'clear') {
    header('Content-Type: application/json');
    requireSameOrigin();
    if (!APP_DEBUG) { echo json_encode(['ok' => false, 'error' => 'Nur im Debug-Modus verfügbar.']); exit; }
    @file_put_contents(LOG_PATH, '');
    logEvent('INFO', 'Log geleert durch Admin ' . (Auth::player()['username'] ?? '?'));
    echo json_encode(['ok' => true]); exit;
}

$page = ['title' => 'System-Log'];
require TEMPLATE_PATH . '/base.php';
?>

<div class="container page-wrap">

  <div class="page-header">
    <span class="page-header__icon">📜</span>
    <h1>System-Log</h1>
    <p class="page-header__sub">
      <a href="<?= APP_URL ?>/admin/debug.php">← zurück zum Debug-Menü</a>
    </p>
  </div>

  <?php if (!APP_DEBUG): ?>
  <div class="alert alert--warn">
    ⚠️ Die Log-Ansicht ist nur im Debug-Modus verfügbar — aktiviere <code>app_debug</code> unter
    <a href="<?= APP_URL ?>/admin/settings.php">Server-Einstellungen</a>.
  </div>
  <?php else: ?>

  <?php
  $meta      = logLevelMeta();
  $entries   = logParse(LOG_PATH);
  $total     = count($entries);

  // Zählung je Schweregrad (über ALLE Einträge, für die Filter-Chips)
  $counts = array_fill_keys(array_keys($meta), 0);
  foreach ($entries as $e) $counts[$e['level']]++;

  // Filter & Sortierung aus GET
  $allowedLevels = array_keys($meta);
  $filter = strtoupper((string)($_GET['level'] ?? 'ALL'));
  if ($filter !== 'ALL' && !in_array($filter, $allowedLevels, true)) $filter = 'ALL';
  $sort = ($_GET['sort'] ?? 'time') === 'severity' ? 'severity' : 'time';

  // Neueste zuerst
  $view = array_reverse($entries);
  if ($filter !== 'ALL') {
      $view = array_values(array_filter($view, fn($e) => $e['level'] === $filter));
  }
  if ($sort === 'severity') {
      // stabil nach Rang (kritischste zuerst); innerhalb gleicher Stufe bleibt
      // die schon gesetzte Neueste-zuerst-Reihenfolge erhalten
      usort($view, fn($a, $b) => $a['rank'] <=> $b['rank']);
  }
  $shown = count($view);

  // Dateigröße für die Anzeige
  $sizeKb = is_file(LOG_PATH) ? round(filesize(LOG_PATH) / 1024, 1) : 0;

  // Basis-URL für Filter-/Sort-Links (Filter wechseln, Sortierung behalten)
  $baseUrl = APP_URL . '/admin/logs.php';
  $linkFor = fn($lvl) => $baseUrl . '?level=' . urlencode($lvl) . '&sort=' . $sort;
  ?>

  <!-- Werkzeugleiste: Sortierung + Aktualisieren + Leeren -->
  <div class="card animate-in mb-2" style="display:flex;flex-wrap:wrap;gap:.6rem;align-items:center;justify-content:space-between">
    <div class="flex gap-sm" style="align-items:center;flex-wrap:wrap">
      <span class="text-dim text-sm">Sortierung:</span>
      <a class="btn btn--sm <?= $sort === 'time' ? 'btn--primary' : 'btn--ghost' ?>"
         href="<?= e($baseUrl . '?level=' . urlencode($filter) . '&sort=time') ?>">🕒 Neueste zuerst</a>
      <a class="btn btn--sm <?= $sort === 'severity' ? 'btn--primary' : 'btn--ghost' ?>"
         href="<?= e($baseUrl . '?level=' . urlencode($filter) . '&sort=severity') ?>">⛔ Nach Schweregrad</a>
    </div>
    <div class="flex gap-sm" style="align-items:center;flex-wrap:wrap">
      <span class="text-dim text-xs">Datei: <?= $sizeKb ?> KB</span>
      <a class="btn btn--ghost btn--sm" href="<?= e($linkFor($filter)) ?>">🔄 Aktualisieren</a>
      <button class="btn btn--ghost btn--sm" onclick="clearLog()"
              style="color:var(--danger-text,#f87171)">🗑 Leeren</button>
    </div>
  </div>

  <!-- Filter-Chips je Schweregrad -->
  <div class="flex gap-sm mb-2" style="flex-wrap:wrap">
    <a class="log-chip <?= $filter === 'ALL' ? 'log-chip--active' : '' ?>"
       href="<?= e($baseUrl . '?level=ALL&sort=' . $sort) ?>">
      Alle <span class="log-chip__count"><?= $total ?></span>
    </a>
    <?php foreach ($meta as $lvl => $m): ?>
    <a class="log-chip <?= $filter === $lvl ? 'log-chip--active' : '' ?>"
       href="<?= e($linkFor($lvl)) ?>"
       style="<?= $filter === $lvl ? 'border-color:' . $m['color'] . ';color:' . $m['color'] : '' ?>">
      <?= $m['emoji'] ?> <?= e($m['label']) ?>
      <span class="log-chip__count" style="<?= $counts[$lvl] > 0 ? 'background:' . $m['color'] . ';color:#fff' : '' ?>"><?= $counts[$lvl] ?></span>
    </a>
    <?php endforeach; ?>
  </div>

  <!-- Eintragsliste -->
  <?php if ($shown === 0): ?>
  <div class="card card--glow text-center animate-in" style="padding:3rem">
    <span style="font-size:3rem;display:block;margin-bottom:1rem">🍃</span>
    <h3><?= $total === 0 ? 'Log ist leer' : 'Keine Einträge für diesen Filter' ?></h3>
    <p class="text-dim mt-1 italic">
      <?= $total === 0 ? 'Noch keine Ereignisse aufgezeichnet — gute Nachrichten.' : 'Wähle oben einen anderen Schweregrad.' ?>
    </p>
  </div>
  <?php else: ?>
  <div class="card animate-in" style="padding:.5rem">
    <?php foreach ($view as $e): $m = $meta[$e['level']]; ?>
    <div class="log-entry" style="border-left:3px solid <?= $m['color'] ?>">
      <div class="log-entry__head">
        <span class="log-entry__badge" style="background:<?= $m['color'] ?>1a;color:<?= $m['color'] ?>">
          <?= $m['emoji'] ?> <?= e($m['label']) ?>
        </span>
        <span class="text-dim text-xs"><?= e($e['ts'] !== '' ? $e['ts'] : '—') ?></span>
      </div>
      <pre class="log-entry__msg"><?= e($e['msg']) ?></pre>
    </div>
    <?php endforeach; ?>
  </div>
  <p class="text-dim text-xs mt-1 text-center">
    <?= $shown ?> von <?= $total ?> Einträgen angezeigt (max. die jüngsten ~256&nbsp;KB der Datei).
  </p>
  <?php endif; ?>

  <?php endif; // APP_DEBUG ?>
</div>

<style>
.log-chip{
  display:inline-flex;align-items:center;gap:.4rem;
  padding:.35rem .7rem;border:1px solid var(--border);border-radius:99px;
  background:var(--panel-bg);color:var(--text-dim);text-decoration:none;
  font-size:.8rem;transition:border-color .15s,color .15s;
}
.log-chip:hover{border-color:var(--accent-border)}
.log-chip--active{border-color:var(--accent);color:var(--text-bright);background:var(--card-bg)}
.log-chip__count{
  background:var(--border);color:var(--text-dim);border-radius:99px;
  font-size:.68rem;font-weight:700;padding:1px 7px;line-height:1.4;
}
.log-entry{padding:.55rem .75rem;margin:.35rem .25rem;background:var(--panel-bg);border-radius:6px}
.log-entry__head{display:flex;align-items:center;gap:.6rem;flex-wrap:wrap;margin-bottom:.25rem}
.log-entry__badge{font-size:.68rem;font-weight:700;padding:2px 8px;border-radius:99px;letter-spacing:.02em}
.log-entry__msg{
  margin:0;font-family:var(--font-mono,monospace);font-size:.8rem;line-height:1.5;
  color:var(--text-bright);white-space:pre-wrap;word-break:break-word;overflow-x:auto;
}
</style>

<?php
$__clearUrl = json_encode(APP_URL . '/admin/logs.php?action=clear');
$__logsUrl  = json_encode(APP_URL . '/admin/logs.php');
?>
<script>
async function clearLog(){
  if(!confirm('Wirklich das komplette System-Log leeren? Das kann nicht rückgängig gemacht werden.')) return;
  try{
    const res = await fetch(<?= $__clearUrl ?>, {
      method:'POST',
      headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},
      body:'{}'
    });
    const data = await res.json();
    if(data.ok){ location.href = <?= $__logsUrl ?>; }
    else { showToast(data.error || 'Fehler beim Leeren', 'error'); }
  }catch(e){ showToast('Netzwerkfehler beim Leeren', 'error'); }
}
</script>
<?php
require TEMPLATE_PATH . '/base_end.php';
