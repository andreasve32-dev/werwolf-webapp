<?php
// Copyright (c) 2026 Andreas Vetter
require_once dirname(__DIR__) . '/core/bootstrap.php';
Auth::requireAdmin();

$roles = allRoles();

$page = ['title' => 'Rollen verwalten'];
require TEMPLATE_PATH . '/base.php';
?>

<div class="container page-wrap">

  <div class="page-header">
    <span class="page-header__icon">🎭</span>
    <h1>Rollen verwalten</h1>
    <p class="page-header__sub">
      <a href="<?= APP_URL ?>/admin/">← zurück zur Spielleitung</a>
    </p>
  </div>

  <!-- Neue Rolle erstellen -->
  <div class="card card--glow animate-in mb-2">
    <div class="section-title">Neue Rolle erstellen</div>
    <form id="role-create-form" onsubmit="return false;">
      <?php include TEMPLATE_PATH . '/role_form_fields.php'; ?>
      <button type="button" class="btn btn--primary btn--full mt-2" onclick="createRole()">+ Rolle erstellen</button>
    </form>
    <div id="create-result" class="mt-2"></div>
  </div>

  <!-- Bestehende Rollen -->
  <div class="section-title">Bestehende Rollen (<?= count($roles) ?>)</div>

  <?php if (empty($roles)): ?>
  <div class="card text-center text-dim" style="padding:2rem">Noch keine Rollen angelegt.</div>
  <?php else: ?>

  <div style="display:flex;flex-direction:column;gap:1rem">
    <?php foreach ($roles as $r): ?>
    <div class="card animate-in role-card <?= !$r['active']?'role-card--inactive':'' ?>" id="role-card-<?= (int)$r['id'] ?>">
      <div class="flex-between" style="align-items:flex-start">
        <div class="flex gap-md" style="align-items:flex-start">
          <span class="role-icon-frame"><?= roleIconHtml($r, 'lg') ?></span>
          <div>
            <div class="flex gap-sm" style="align-items:center">
              <span style="font-family:var(--font-display);font-size:1.1rem;color:var(--text-bright)"><?= e($r['name']) ?></span>
              <?php if (!$r['active']): ?><span class="tag tag--lobby" data-inactive-tag>Inaktiv</span><?php endif; ?>
              <?php if (!empty($r['fill'])): ?><span class="tag tag--lobby">⬚ Auffüll-Rolle</span><?php endif; ?>
              <?php if (!empty($r['sichtbar'])): ?><span class="tag tag--day">👁️ Sichtbar untereinander</span><?php endif; ?>
              <?php if (!empty($r['befragen'])): ?><span class="tag tag--night">🔍 Darf Tote befragen</span><?php endif; ?>
              <?php if (!empty($r['auto_eintrag'])): ?><span class="tag tag--running">⭐ Star</span><?php endif; ?>
            </div>
            <div class="text-dim text-sm mt-1"><?= e($r['description'] ?: 'Keine Beschreibung') ?></div>
            <div class="text-xs text-dim mt-1">
              Anzahl: <strong class="text-accent"><?= (int)$r['amount'] ?></strong> &middot;
              Cooldown: <strong class="text-accent"><?= (int)$r['cooldown'] ?></strong> Min.
            </div>
            <?php if ($r['rules']): ?>
            <div class="text-xs text-dim mt-1 italic">📜 <?= e($r['rules']) ?></div>
            <?php endif; ?>
          </div>
        </div>
        <div class="flex gap-xs" style="flex-shrink:0">
          <button class="btn btn--ghost btn--sm" onclick="toggleEdit(<?= (int)$r['id'] ?>)">✎</button>
          <button class="btn btn--ghost btn--sm" data-toggle-btn onclick="toggleActive(<?= (int)$r['id'] ?>)"><?= $r['active']?'⏸':'▶' ?></button>
          <button class="btn btn--danger btn--sm" onclick="deleteRole(<?= (int)$r['id'] ?>,'<?= e($r['name']) ?>')">✕</button>
        </div>
      </div>

      <!-- Edit-Formular (versteckt) -->
      <div id="edit-form-<?= (int)$r['id'] ?>" class="mt-2" style="display:none;border-top:1px solid var(--border);padding-top:1rem">
        <?php
          $editRole = $r;
          include TEMPLATE_PATH . '/role_form_fields.php';
        ?>
        <div class="flex gap-sm mt-2">
          <button type="button" class="btn btn--primary" onclick="updateRole(<?= (int)$r['id'] ?>)">Speichern</button>
          <button type="button" class="btn btn--ghost" onclick="toggleEdit(<?= (int)$r['id'] ?>)">Abbrechen</button>
        </div>
        <div id="edit-result-<?= (int)$r['id'] ?>" class="mt-1"></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

</div>

<style>
.role-card--inactive { opacity:.55; }
</style>

<?php
$page['inline_js'] = sprintf(
    'const API_BASE=%s,UPLOAD_URL=%s,APP_URL=%s;',
    json_encode(API_URL),
    json_encode(API_URL . '/upload_role_icon.php'),
    json_encode(APP_URL)
);
$page['inline_js'] .= <<<'JS'


function collectFormData(prefix) {
  const get = id => document.getElementById(prefix+id);
  return {
    name: get('name')?.value.trim() || '',
    cooldown: parseInt(get('cooldown')?.value || '0', 10),
    description: get('description')?.value.trim() || '',
    rules: get('rules')?.value.trim() || '',
    active: get('active')?.checked ? 1 : 0,
    fill: get('fill')?.checked ? 1 : 0,
    sichtbar: get('sichtbar')?.checked ? 1 : 0,
    befragen: get('befragen')?.checked ? 1 : 0,
    auto_eintrag: get('auto_eintrag')?.checked ? 1 : 0,
    amount: parseInt(get('amount')?.value || '1', 10),
    icon_path: get('icon_path')?.value.trim() || '',
    sort_order: parseInt(get('sort_order')?.value || '0', 10),
  };
}

function onFillToggle(prefix, checked) {
  const amountInput = document.getElementById(prefix+'amount');
  if(amountInput) { amountInput.disabled = checked; amountInput.style.opacity = checked ? '.4' : '1'; }
}

function onIconFileSelected(prefix){
  const input = document.getElementById(prefix+'icon_file');
  const file = input.files[0];
  if(!file) return;

  const preview = document.getElementById(prefix+'icon-preview');
  const reader = new FileReader();
  reader.onload = e => {
    preview.innerHTML = `<img src="${e.target.result}" alt="">`;
  };
  reader.readAsDataURL(file);

  document.getElementById(prefix+'upload-btn').style.display = 'inline-flex';
  document.getElementById(prefix+'upload-status').innerHTML =
    `<span class="text-dim">${file.type||'Bilddatei'} erkannt (${(file.size/1024).toFixed(0)} KB)</span>`;
}

async function uploadIconFile(prefix){
  const input = document.getElementById(prefix+'icon_file');
  const file = input.files[0];
  if(!file){ showToast('Bitte zuerst eine Datei wählen','error'); return; }

  const statusEl = document.getElementById(prefix+'upload-status');
  const uploadBtn = document.getElementById(prefix+'upload-btn');

  const formData = new FormData();
  formData.append('icon', file);

  uploadBtn.disabled = true;
  statusEl.innerHTML = '<span class="text-dim">Lädt hoch…</span>';

  try {
    const res = await fetch(UPLOAD_URL, { method:'POST', body: formData });
    const r = await res.json();
    if(r.ok){
      document.getElementById(prefix+'icon_path').value = r.icon_path;
      statusEl.innerHTML = `<span style="color:var(--alert-success-text)">✓ ${escHtml(r.message)}</span>`;
      uploadBtn.style.display = 'none';
      showToast('Icon hochgeladen!','success');
    } else {
      statusEl.innerHTML = `<span style="color:var(--alert-error-text)">${escHtml(r.error||'Fehler')}</span>`;
    }
  } catch(e){
    statusEl.innerHTML = '<span style="color:var(--alert-error-text)">Netzwerkfehler beim Hochladen</span>';
  } finally {
    uploadBtn.disabled = false;
  }
}

async function createRole(){
  const data = collectFormData('rf-');
  if(!data.name){ showToast('Name erforderlich','error'); return; }
  const r = await apiFetch(API_BASE+'/admin.php', Object.assign({action:'role_create'}, data));
  if(r.error==='session_expired')return;
  const el = document.getElementById('create-result');
  if(r.ok){
    el.innerHTML = '<div class="alert alert--success">Rolle erstellt!</div>';
    setTimeout(()=>location.reload(), 800);
  } else {
    el.innerHTML = `<div class="alert alert--error">${r.error||'Fehler'}</div>`;
  }
}

function toggleEdit(id){
  const el = document.getElementById('edit-form-'+id);
  if(el) el.style.display = el.style.display==='none' ? 'block' : 'none';
}

async function updateRole(id){
  const data = collectFormData('ef-'+id+'-');
  if(!data.name){ showToast('Name erforderlich','error'); return; }
  const r = await apiFetch(API_BASE+'/admin.php', Object.assign({action:'role_update', role_id:id}, data));
  if(r.error==='session_expired')return;
  const el = document.getElementById('edit-result-'+id);
  if(r.ok){
    el.innerHTML = '<div class="alert alert--success">Gespeichert!</div>';
    setTimeout(()=>location.reload(), 800);
  } else {
    el.innerHTML = `<div class="alert alert--error">${r.error||'Fehler'}</div>`;
  }
}

async function toggleActive(id){
  const r = await apiFetch(API_BASE+'/admin.php', {action:'role_toggle_active', role_id:id});
  if(r.error==='session_expired')return;
  if(!r.ok){ showToast(r.error||'Fehler','error'); return; }

  const isNowActive = r.active === 1;
  const card = document.getElementById('role-card-'+id);
  if(!card) return;

  // Karte ein-/ausblenden
  card.classList.toggle('role-card--inactive', !isNowActive);

  // Toggle-Button aktualisieren
  const btn = card.querySelector('[data-toggle-btn]');
  if(btn) btn.textContent = isNowActive ? '⏸' : '▶';

  // "Inaktiv"-Tag hinzufügen oder entfernen
  const nameRow = card.querySelector('.flex.gap-sm');
  const existingTag = nameRow?.querySelector('[data-inactive-tag]');
  if(!isNowActive) {
    if(!existingTag && nameRow) {
      const tag = document.createElement('span');
      tag.className = 'tag tag--lobby';
      tag.setAttribute('data-inactive-tag', '');
      tag.textContent = 'Inaktiv';
      nameRow.insertBefore(tag, nameRow.children[1] || null);
    }
  } else {
    existingTag?.remove();
  }

  showToast(isNowActive ? '▶ Aktiviert' : '⏸ Deaktiviert', 'success');
}

async function deleteRole(id,name){
  if(!confirm('Rolle "'+name+'" wirklich löschen? Spieler mit dieser Rolle verlieren sie (werden zu "keine Rolle").')) return;
  const r = await apiFetch(API_BASE+'/admin.php', {action:'role_delete', role_id:id});
  if(r.error==='session_expired')return;
  if(r.ok){ showToast('Gelöscht','success'); setTimeout(()=>location.reload(), 500); }
  else showToast(r.error||'Fehler','error');
}
JS;
require TEMPLATE_PATH . '/base_end.php';
?>
