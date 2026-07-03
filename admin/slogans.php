<?php
// Copyright (c) 2026 Andreas Vetter
require_once dirname(__DIR__) . '/core/bootstrap.php';
require_once TEMPLATE_PATH . '/slogan_row.php';
Auth::requireAdmin();

$daySlogans   = Database::query("SELECT id, text, active FROM slogans WHERE phase='day'   ORDER BY created_at");
$nightSlogans = Database::query("SELECT id, text, active FROM slogans WHERE phase='night' ORDER BY created_at");

$page = ['title' => 'Dorf-Sprüche'];
require TEMPLATE_PATH . '/base.php';
?>

<div class="container page-wrap">

  <div class="page-header">
    <a href="<?= APP_URL ?>/admin/" class="btn btn--ghost btn--sm" style="margin-right:.5rem">← Zurück</a>
    <span class="page-header__icon">💬</span>
    <h1>Dorf-Sprüche</h1>
    <p class="page-header__sub">Bis zu 20 Sprüche pro Phase — werden alle 2 Minuten zufällig gewechselt.</p>
  </div>

  <!-- ── Neuen Spruch hinzufügen ──────────────────────────── -->
  <div class="card animate-in mb-2">
    <div class="section-title">➕ Spruch hinzufügen</div>
    <div class="flex gap-sm" style="align-items:flex-end;flex-wrap:wrap">
      <div style="flex:1;min-width:200px">
        <label class="form-label">Text</label>
        <input class="form-input" type="text" id="new-text" maxlength="255"
               placeholder="z.B. Die Kuh von Bauer Franz schaut komisch …">
      </div>
      <div>
        <label class="form-label">Phase</label>
        <select class="form-input" id="new-phase" style="width:120px">
          <option value="day">☀️ Tag</option>
          <option value="night">🌕 Nacht</option>
        </select>
      </div>
      <button class="btn btn--primary" onclick="addSlogan()">Hinzufügen</button>
    </div>
    <div id="add-result" class="mt-1"></div>
  </div>

  <div class="grid-2" style="gap:1.25rem;align-items:start">

    <!-- ── Tag-Sprüche ──────────────────────────────────────── -->
    <div class="card animate-in" style="animation-delay:.04s">
      <div class="section-title" style="display:flex;justify-content:space-between;align-items:center">
        <span>☀️ Tag-Sprüche</span>
        <span class="tag" id="day-count"><?= count($daySlogans) ?>/20</span>
      </div>
      <div id="day-list">
        <?php foreach ($daySlogans as $s): ?>
        <?= sloganRow($s) ?>
        <?php endforeach; ?>
        <?php if (!$daySlogans): ?>
        <p class="text-dim text-sm" id="day-empty">Noch keine Tag-Sprüche.</p>
        <?php endif; ?>
      </div>
    </div>

    <!-- ── Nacht-Sprüche ────────────────────────────────────── -->
    <div class="card animate-in" style="animation-delay:.08s">
      <div class="section-title" style="display:flex;justify-content:space-between;align-items:center">
        <span>🌕 Nacht-Sprüche</span>
        <span class="tag" id="night-count"><?= count($nightSlogans) ?>/20</span>
      </div>
      <div id="night-list">
        <?php foreach ($nightSlogans as $s): ?>
        <?= sloganRow($s) ?>
        <?php endforeach; ?>
        <?php if (!$nightSlogans): ?>
        <p class="text-dim text-sm" id="night-empty">Noch keine Nacht-Sprüche.</p>
        <?php endif; ?>
      </div>
    </div>

  </div>
</div>

<?php
$page['inline_js'] = sprintf('const API_BASE=%s;', json_encode(API_URL));
$page['inline_js'] .= <<<'JS'

async function addSlogan() {
  const text  = document.getElementById('new-text').value.trim();
  const phase = document.getElementById('new-phase').value;
  if (!text) { showToast('Bitte Text eingeben', 'error'); return; }
  const r = await apiFetch(API_BASE+'/admin.php', {action:'add_slogan', game_id:0, text, phase});
  const el = document.getElementById('add-result');
  if (r.ok) {
    el.innerHTML = '<div class="alert alert--success mt-1">Gespeichert!</div>';
    if (r.html) {
      document.getElementById(phase+'-empty')?.remove();
      document.getElementById(phase+'-list').insertAdjacentHTML('beforeend', r.html);
      updateCounts();
    }
    document.getElementById('new-text').value = '';
  } else {
    el.innerHTML = `<div class="alert alert--error mt-1">${r.error||'Fehler'}</div>`;
  }
}

async function deleteSlogan(id) {
  if (!confirm('Spruch wirklich löschen?')) return;
  const r = await apiFetch(API_BASE+'/admin.php', {action:'delete_slogan', game_id:0, slogan_id:id});
  if (r.ok) {
    const row = document.getElementById('slogan-row-'+id);
    if (row) row.remove();
    updateCounts();
  } else showToast(r.error||'Fehler','error');
}

async function toggleSlogan(id) {
  const r = await apiFetch(API_BASE+'/admin.php', {action:'toggle_slogan', game_id:0, slogan_id:id});
  if (r.ok) {
    const row  = document.getElementById('slogan-row-'+id);
    const icon = document.getElementById('slogan-icon-'+id);
    if (!row || !icon) return;
    const isActive = icon.textContent.trim() === '✓';
    icon.textContent = isActive ? '○' : '✓';
    row.style.opacity = isActive ? '0.45' : '1';
  } else showToast(r.error||'Fehler','error');
}

function updateCounts() {
  ['day','night'].forEach(p => {
    const list = document.getElementById(p+'-list');
    const cnt  = document.getElementById(p+'-count');
    if (!list || !cnt) return;
    cnt.textContent = list.querySelectorAll('[id^="slogan-row-"]').length + '/20';
  });
}
JS;
require TEMPLATE_PATH . '/base_end.php';
?>
