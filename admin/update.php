<?php
// Copyright (c) 2026 Andreas Vetter
// Update-Center: signierte Update-Pakete (.wwupd) hochladen/auswählen, verifizieren
// (Signatur + Datei-Hashes + Versionsprüfung) und anwenden. Admin-only.
// Für Neuaufsetzung/Reset ist weiterhin admin/setup.php zuständig (destruktiv).
require_once dirname(__DIR__) . '/core/bootstrap.php';
require_once CORE_PATH . '/Updater.php';
Auth::requireAdmin();

const UPDATES_DIR = ROOT_PATH . '/updates';
if (!is_dir(UPDATES_DIR)) @mkdir(UPDATES_DIR, 0775, true);

/** Sicheren Paket-Dateinamen (nur Basename, .wwupd) oder null. */
function updSafeName(string $n): ?string {
    $n = basename($n);
    return preg_match('#^[A-Za-z0-9._-]+\.wwupd$#', $n) ? $n : null;
}

// ── POST: Paket anwenden (JSON) ───────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'apply') {
    header('Content-Type: application/json');
    requireSameOrigin();
    $name = updSafeName((string)(jsonBody()['file'] ?? ''));
    if (!$name || !is_file(UPDATES_DIR . '/' . $name)) { echo json_encode(['ok' => false, 'error' => 'Paket nicht gefunden.']); exit; }
    $raw = (string)file_get_contents(UPDATES_DIR . '/' . $name);
    $v = Updater::verify($raw);
    if (!$v['ok']) { echo json_encode(['ok' => false, 'error' => $v['error']]); exit; }
    $vc = Updater::checkVersion($v['manifest'], APP_VERSION);
    if (!$vc['ok']) { echo json_encode(['ok' => false, 'error' => $vc['error']]); exit; }
    $res = Updater::apply($v['pkg'], $v['manifest']);
    echo json_encode($res);
    exit;
}

// ── POST: Paket hochladen (Multipart) ─────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'upload') {
    requireSameOrigin();
    if (empty($_FILES['pkg']) || $_FILES['pkg']['error'] !== UPLOAD_ERR_OK) {
        redirect('/admin/update.php?err=' . urlencode('Upload fehlgeschlagen.'));
    }
    if ($_FILES['pkg']['size'] > 25 * 1024 * 1024) {
        redirect('/admin/update.php?err=' . urlencode('Paket zu groß (max. 25 MB).'));
    }
    $name = updSafeName($_FILES['pkg']['name']) ?? ('upload_' . date('Ymd_His') . '.wwupd');
    $name = updSafeName($name) ?? ('upload_' . date('Ymd_His') . '.wwupd');
    if (!move_uploaded_file($_FILES['pkg']['tmp_name'], UPDATES_DIR . '/' . $name)) {
        redirect('/admin/update.php?err=' . urlencode('Speichern fehlgeschlagen.'));
    }
    redirect('/admin/update.php?file=' . urlencode($name));
}

// ── Seite rendern ─────────────────────────────────────────────
$selected = updSafeName((string)($_GET['file'] ?? ''));
$preview  = null;
if ($selected && is_file(UPDATES_DIR . '/' . $selected)) {
    $raw = (string)file_get_contents(UPDATES_DIR . '/' . $selected);
    $v = Updater::verify($raw);
    if ($v['ok']) {
        $vc = Updater::checkVersion($v['manifest'], APP_VERSION);
        $preview = ['manifest' => $v['manifest'], 'version_ok' => $vc['ok'], 'version_error' => $vc['error']];
    } else {
        $preview = ['error' => $v['error']];
    }
}
$existing = array_map('basename', glob(UPDATES_DIR . '/*.wwupd') ?: []);

$page = ['title' => 'Update-Center'];
require TEMPLATE_PATH . '/base.php';
?>

<div class="container page-wrap">

  <div class="page-header">
    <span class="page-header__icon">⬆️</span>
    <h1>Update-Center</h1>
    <p class="page-header__sub">
      <a href="<?= APP_URL ?>/admin/">← zurück zur Spielleitung</a>
    </p>
  </div>

  <?php if (!empty($_GET['err'])): ?>
  <div class="alert alert--error mb-2"><?= e((string)$_GET['err']) ?></div>
  <?php endif; ?>

  <div class="card animate-in mb-2">
    <div class="section-title">ℹ️ So funktionieren Updates</div>
    <p class="text-dim text-sm" style="line-height:1.6">
      Ein Update kommt als <strong>signiertes Paket</strong> (<code>.wwupd</code>). Lade es unten
      hoch — die App prüft die <strong>Signatur</strong> (nur vom Entwickler signierte Pakete
      werden akzeptiert) und die <strong>Prüfsummen aller Dateien</strong> (erkennt jede
      Manipulation) sowie die <strong>Version</strong>. Danach siehst du die Änderungen und kannst
      mit einem Klick anwenden — Dateien werden automatisch ersetzt (mit Backup) und DB-Änderungen
      angewendet. Installierte App-Version: <strong><?= e(APP_VERSION) ?></strong>.
    </p>
  </div>

  <!-- Upload -->
  <div class="card card--glow animate-in mb-2">
    <div class="section-title">📦 Paket hochladen</div>
    <form method="POST" action="<?= APP_URL ?>/admin/update.php?action=upload" enctype="multipart/form-data">
      <div class="flex gap-sm" style="flex-wrap:wrap;align-items:center">
        <input type="file" name="pkg" accept=".wwupd" required class="form-input" style="flex:1;min-width:200px">
        <button type="submit" class="btn btn--primary">⬆️ Hochladen &amp; prüfen</button>
      </div>
    </form>
    <?php if ($existing): ?>
    <div class="mt-2">
      <div class="text-dim text-xs mb-1">Bereits hochgeladene Pakete:</div>
      <div style="display:flex;flex-direction:column;gap:.3rem">
        <?php foreach ($existing as $f): ?>
        <a href="<?= APP_URL ?>/admin/update.php?file=<?= e(urlencode($f)) ?>"
           class="panel" style="padding:.4rem .7rem;text-decoration:none;color:inherit;display:flex;align-items:center;gap:.5rem">
          📄 <span style="font-family:monospace;font-size:.82rem"><?= e($f) ?></span>
          <span class="text-dim text-xs" style="margin-left:auto">prüfen →</span>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Vorschau des gewählten Pakets -->
  <?php if ($preview !== null): ?>
    <?php if (isset($preview['error'])): ?>
    <div class="card animate-in mb-2" style="border-color:var(--danger-border,#7f1d1d)">
      <div class="section-title" style="color:var(--danger-text,#f87171)">🚫 Paket abgelehnt</div>
      <p class="text-sm"><?= e($preview['error']) ?></p>
      <p class="text-dim text-xs mt-1">Datei: <?= e($selected) ?></p>
    </div>
    <?php else: $m = $preview['manifest']; ?>
    <div class="card card--glow animate-in mb-2">
      <div class="section-title">🔎 Vorschau: <?= e($selected) ?></div>
      <div class="alert alert--success mb-2" style="padding:.4rem .8rem">✓ Signatur gültig — Paket ist echt und unverändert.</div>
      <table style="width:100%;border-collapse:collapse;font-size:.9rem">
        <tr><td class="text-dim" style="padding:.3rem .5rem;width:40%">Zielversion</td><td style="padding:.3rem .5rem"><strong><?= e($m['target_version'] ?? '?') ?></strong></td></tr>
        <tr><td class="text-dim" style="padding:.3rem .5rem">Mindestversion</td><td style="padding:.3rem .5rem"><?= e($m['min_version'] ?: '—') ?></td></tr>
        <tr><td class="text-dim" style="padding:.3rem .5rem">Enthält Dateien</td><td style="padding:.3rem .5rem"><?= count($m['files'] ?? []) ?></td></tr>
        <tr><td class="text-dim" style="padding:.3rem .5rem">DB-Änderungen</td><td style="padding:.3rem .5rem"><?= !empty($m['has_db_changes']) ? '⚙️ ja (additive Migration)' : 'nein' ?></td></tr>
      </table>

      <?php if (!empty($m['notes'])): ?>
      <div class="mt-2">
        <div class="text-dim text-xs mb-1">Änderungen in dieser Version:</div>
        <div class="panel" style="padding:.6rem .85rem;font-size:.88rem;line-height:1.5;white-space:pre-wrap"><?= e($m['notes']) ?></div>
      </div>
      <?php endif; ?>

      <?php if (!empty($m['requires_resetup'])): ?>
      <div class="alert alert--warn mt-2">
        ⚠️ Dieses Update erfordert ein komplettes <strong>Neu-Aufsetzen</strong> der Datenbank und kann
        nicht automatisch angewendet werden. Bitte die
        <a href="<?= APP_URL ?>/admin/setup.php">Ersteinrichtung</a> verwenden (Achtung: löscht Daten!).
      </div>
      <?php elseif (!$preview['version_ok']): ?>
      <div class="alert alert--warn mt-2">⚠️ <?= e($preview['version_error']) ?></div>
      <?php else: ?>
      <div id="apply-result" class="mt-2" style="display:none"></div>
      <button class="btn btn--primary btn--lg mt-2" id="btn-apply"
              onclick="applyUpdate(<?= htmlspecialchars(json_encode($selected), ENT_QUOTES) ?>)">
        ⚙️ Update auf Version <?= e($m['target_version'] ?? '') ?> installieren
      </button>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  <?php endif; ?>

</div>

<?php $__applyUrl = json_encode(APP_URL . '/admin/update.php?action=apply'); ?>
<script>
async function applyUpdate(file){
  const btn = document.getElementById('btn-apply');
  const res = document.getElementById('apply-result');
  if(!confirm('Update jetzt installieren? Dateien werden ersetzt (mit Backup) und DB-Änderungen angewendet.')) return;
  btn.disabled = true; btn.textContent = '⏳ Installiere…';
  res.style.display = ''; res.innerHTML = '<div class="text-dim text-sm">Wird angewendet…</div>';
  try{
    const data = await (await fetch(<?= $__applyUrl ?>, {
      method:'POST',
      headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},
      body: JSON.stringify({file})
    })).json();
    if(data.ok){
      res.innerHTML = `<div class="alert alert--success">✓ Update installiert (${(data.written||[]).length} Datei(en)). Backup: ${escHtml(data.backup||'—')}</div>`;
      btn.style.display = 'none';
      setTimeout(() => { location.href = <?= json_encode(APP_URL . '/admin/update.php') ?>; }, 2200);
    } else {
      res.innerHTML = `<div class="alert alert--error">${escHtml(data.error||'Fehler')}</div>`;
      btn.disabled = false; btn.textContent = '⚙️ Erneut versuchen';
    }
  }catch(e){
    res.innerHTML = '<div class="alert alert--error">Netzwerkfehler beim Update.</div>';
    btn.disabled = false; btn.textContent = '⚙️ Erneut versuchen';
  }
}
</script>
<?php
require TEMPLATE_PATH . '/base_end.php';
