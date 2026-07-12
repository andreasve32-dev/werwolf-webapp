<?php
// Copyright (c) 2026 Andreas Vetter
require_once dirname(__DIR__) . '/core/bootstrap.php';
require_once TEMPLATE_PATH . '/role_card.php';
Auth::requireAdmin();

$roles   = allRoles();
$presets = Database::query("SELECT id, name FROM role_presets ORDER BY name");

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

  <!-- Rollen-Presets -->
  <div class="card animate-in mb-2">
    <div class="section-title">🎛️ Rollen-Presets</div>
    <p class="text-dim" style="font-size:.85rem;margin-bottom:1rem">
      Speichert den aktuellen Zustand aller Rollen (aktiv/inaktiv, Anzahl, Auffüll-Rolle)
      als benanntes Set — z.&nbsp;B. „7 Spieler". Beim Laden wird das Set komplett auf die
      Rollen angewendet; Rollen, die nach dem Speichern neu dazukamen, werden dabei deaktiviert.
    </p>

    <div class="form-group">
      <label class="form-label" for="preset-select">Preset laden</label>
      <div class="flex gap-sm">
        <select class="form-input" id="preset-select" style="flex:1">
          <option value="">— Preset wählen —</option>
          <?php foreach ($presets as $p): ?>
          <option value="<?= (int)$p['id'] ?>"><?= e($p['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <button type="button" class="btn btn--primary" onclick="applyPreset()">📥 Laden</button>
        <button type="button" class="btn" onclick="deletePreset()">🗑️</button>
      </div>
    </div>

    <div class="form-group" style="margin-bottom:0">
      <label class="form-label" for="preset-save-target">Aktuellen Zustand speichern</label>
      <div class="flex gap-sm flex-wrap">
        <select class="form-input" id="preset-save-target" style="flex:1;min-width:10rem"
                onchange="onSaveTargetChange()">
          <option value="">➕ Neues Preset anlegen…</option>
          <?php foreach ($presets as $p): ?>
          <option value="<?= (int)$p['id'] ?>">Überschreiben: <?= e($p['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <input class="form-input" type="text" id="preset-name" maxlength="50"
               placeholder='Name, z.B. "7 Spieler"' style="flex:1;min-width:10rem">
        <button type="button" class="btn btn--primary" onclick="savePreset()">💾 Speichern</button>
      </div>
    </div>
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
  <div class="section-title">Bestehende Rollen (<span id="roles-count"><?= count($roles) ?></span>)</div>

  <?php if (empty($roles)): ?>
  <div class="card text-center text-dim" id="roles-empty-hint" style="padding:2rem">Noch keine Rollen angelegt.</div>
  <?php endif; ?>

  <div id="roles-list" style="display:flex;flex-direction:column;gap:1rem">
    <?php foreach ($roles as $r): ?>
    <?= render_role_card($r) ?>
    <?php endforeach; ?>
  </div>

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
    killer_sichtbar: get('killer_sichtbar')?.checked ? 1 : 0,
    befragen: get('befragen')?.checked ? 1 : 0,
    auto_eintrag: get('auto_eintrag')?.checked ? 1 : 0,
    is_killer: get('is_killer')?.checked ? 1 : 0,
    linked_death: get('linked_death')?.checked ? 1 : 0,
    rollensicht: get('rollensicht')?.checked ? 1 : 0,
    kill_hinweis: get('kill_hinweis')?.checked ? 1 : 0,
    side_switch: get('side_switch')?.checked ? 1 : 0,
    side_switch_min: parseInt(get('side_switch_min')?.value || '0', 10),
    side_switch_max: parseInt(get('side_switch_max')?.value || '0', 10),
    amount: parseInt(get('amount')?.value || '1', 10),
    icon_path: get('icon_path')?.value.trim() || '',
    sort_order: parseInt(get('sort_order')?.value || '0', 10),
  };
}

function onFillToggle(prefix, checked) {
  const amountInput = document.getElementById(prefix+'amount');
  if(amountInput) { amountInput.disabled = checked; amountInput.style.opacity = checked ? '.4' : '1'; }
}

// ── Rollen-Flags als Tabs (templates/role_form_fields.php) ────
// Tab öffnet das Erklär-Panel des Flags (nochmal klicken = zuklappen),
// pro Formular ist maximal ein Panel offen (Tabs sind prefix-scoped).
function flagTabToggle(prefix, key) {
  const panel  = document.getElementById(prefix+'flagpanel-'+key);
  const btn    = document.getElementById(prefix+'flagtab-'+key);
  if (!panel || !btn) return;
  const isOpen = panel.style.display !== 'none';
  // Alle Panels + Tab-Hervorhebungen dieses Formulars zurücksetzen
  document.querySelectorAll('[id^="'+prefix+'flagpanel-"]').forEach(p => p.style.display = 'none');
  document.querySelectorAll('#'+CSS.escape(prefix+'flag-tabs')+' button').forEach(b => {
    b.classList.remove('btn--primary'); b.classList.add('btn--ghost');
  });
  if (!isOpen) {
    panel.style.display = '';
    btn.classList.remove('btn--ghost'); btn.classList.add('btn--primary');
  }
}

// ✓-Anzeige im Tab aktualisieren, wenn die Checkbox umgeschaltet wird
function flagMarkUpdate(prefix, key, checked) {
  const mark = document.getElementById(prefix+'flagmark-'+key);
  if (mark) mark.style.display = checked ? '' : 'none';
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

function _bumpRolesCount(delta) {
  const el = document.getElementById('roles-count');
  if (el) el.textContent = String(Math.max(0, parseInt(el.textContent, 10) + delta));
}

async function createRole(){
  const data = collectFormData('rf-');
  if(!data.name){ showToast('Name erforderlich','error'); return; }
  const r = await apiFetch(API_BASE+'/admin.php', Object.assign({action:'role_create'}, data));
  if(r.error==='session_expired')return;
  const el = document.getElementById('create-result');
  if(r.ok){
    el.innerHTML = '<div class="alert alert--success">Rolle erstellt!</div>';
    if (r.html) {
      document.getElementById('roles-empty-hint')?.remove();
      document.getElementById('roles-list').insertAdjacentHTML('beforeend', r.html);
      _bumpRolesCount(1);
    }
    document.getElementById('role-create-form')?.reset();
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
  if(r.ok){
    const card = document.getElementById('role-card-'+id);
    if (r.html && card) card.outerHTML = r.html;
    showToast('Gespeichert!','success');
  } else {
    const el = document.getElementById('edit-result-'+id);
    if (el) el.innerHTML = `<div class="alert alert--error">${r.error||'Fehler'}</div>`;
  }
}

async function toggleActive(id){
  const card = document.getElementById('role-card-'+id);
  const checkbox = card?.querySelector('[data-toggle-input]');

  const r = await apiFetch(API_BASE+'/admin.php', {action:'role_toggle_active', role_id:id});
  if(r.error==='session_expired')return;
  if(!r.ok){
    showToast(r.error||'Fehler','error');
    if(checkbox) checkbox.checked = !checkbox.checked; // Fehlschlag: Häkchen zurücksetzen
    return;
  }

  const isNowActive = r.active === 1;
  if(!card) return;
  if(checkbox) checkbox.checked = isNowActive;

  // Karte ein-/ausblenden
  card.classList.toggle('role-card--inactive', !isNowActive);

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
  if(r.ok){
    const card = document.getElementById('role-card-'+id);
    if (card) { card.style.transition='opacity .3s'; card.style.opacity='0'; setTimeout(()=>card.remove(),320); }
    _bumpRolesCount(-1);
    showToast('Gelöscht','success');
  }
  else showToast(r.error||'Fehler','error');
}

// ── Rollen-Presets ─────────────────────────────────────────────

function _rebuildPresetSelects(presets, selectId) {
  const sel = document.getElementById('preset-select');
  sel.innerHTML = '<option value="">— Preset wählen —</option>' +
    presets.map(p => `<option value="${p.id}">${escHtml(p.name)}</option>`).join('');
  if (selectId) sel.value = String(selectId);
  const saveSel = document.getElementById('preset-save-target');
  saveSel.innerHTML = '<option value="">➕ Neues Preset anlegen…</option>' +
    presets.map(p => `<option value="${p.id}">Überschreiben: ${escHtml(p.name)}</option>`).join('');
  onSaveTargetChange();
}

function onSaveTargetChange(){
  // Name-Feld nur bei Neuanlage — beim Überschreiben bleibt der Name erhalten
  const isNew = !document.getElementById('preset-save-target').value;
  const input = document.getElementById('preset-name');
  input.style.display = isNew ? '' : 'none';
  if (!isNew) input.value = '';
}

async function savePreset(){
  const targetId = parseInt(document.getElementById('preset-save-target').value||'0', 10);
  const input = document.getElementById('preset-name');
  const payload = {action:'preset_save'};
  if (targetId) {
    const sel = document.getElementById('preset-save-target');
    const label = sel.options[sel.selectedIndex].textContent.replace(/^Überschreiben: /,'');
    if(!confirm(`Preset "${label}" mit dem aktuellen Rollen-Zustand überschreiben?`)) return;
    payload.preset_id = targetId;
  } else {
    const name = input.value.trim();
    if(!name){ showToast('Bitte einen Namen eingeben','error'); return; }
    payload.name = name;
  }
  const r = await apiFetch(API_BASE+'/admin.php', payload);
  if(r.error==='session_expired')return;
  if(r.ok){
    _rebuildPresetSelects(r.presets, r.preset_id);
    input.value = '';
    showToast(r.message||'Preset gespeichert','success');
  } else showToast(r.error||'Fehler','error');
}

async function applyPreset(){
  const sel = document.getElementById('preset-select');
  const id = parseInt(sel.value, 10);
  if(!id){ showToast('Bitte ein Preset wählen','error'); return; }
  const name = sel.options[sel.selectedIndex].textContent;
  if(!confirm(`Preset "${name}" laden? Aktiv/Anzahl/Auffüll-Rolle aller Rollen werden überschrieben.`)) return;
  const r = await apiFetch(API_BASE+'/admin.php', {action:'preset_apply', preset_id:id});
  if(r.error==='session_expired')return;
  if(r.ok){
    if (r.html !== undefined) {
      const list = document.getElementById('roles-list');
      list.innerHTML = r.html;
      const count = list.querySelectorAll('.role-card').length;
      const countEl = document.getElementById('roles-count');
      if (countEl) countEl.textContent = String(count);
    }
    showToast(r.message||'Preset geladen','success');
  } else showToast(r.error||'Fehler','error');
}

async function deletePreset(){
  const sel = document.getElementById('preset-select');
  const id = parseInt(sel.value, 10);
  if(!id){ showToast('Bitte ein Preset wählen','error'); return; }
  const name = sel.options[sel.selectedIndex].textContent;
  if(!confirm(`Preset "${name}" wirklich löschen? Die Rollen selbst bleiben unverändert.`)) return;
  const r = await apiFetch(API_BASE+'/admin.php', {action:'preset_delete', preset_id:id});
  if(r.error==='session_expired')return;
  if(r.ok){
    _rebuildPresetSelects(r.presets);
    showToast('Preset gelöscht','success');
  } else showToast(r.error||'Fehler','error');
}
JS;
require TEMPLATE_PATH . '/base_end.php';
?>
