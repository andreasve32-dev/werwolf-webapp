<?php
// Copyright (c) 2026 Andreas Vetter
/**
 * templates/role_form_fields.php
 * Wiederverwendbares Formular für Rollen-Erstellung & -Bearbeitung.
 *
 * Verwendung:
 *   - Neu anlegen:    Variable $editRole NICHT setzen → leere Felder, Präfix "rf-"
 *   - Bearbeiten:     $editRole = [...Rollendaten...] setzen VOR include
 *                      → Präfix wird automatisch "ef-{id}-"
 *
 * Die JS-Funktion collectFormData(prefix) in roles.php liest exakt
 * diese IDs aus, daher müssen Feld-IDs und -Suffixe hier konsistent bleiben.
 */
$isEdit  = isset($editRole);
$prefix  = $isEdit ? 'ef-' . (int)$editRole['id'] . '-' : 'rf-';
$v       = fn(string $key, $default = '') => $isEdit ? ($editRole[$key] ?? $default) : $default;
?>
<div class="form-group">
  <label class="form-label" for="<?= $prefix ?>name">Name *</label>
  <input class="form-input" type="text" id="<?= $prefix ?>name"
         placeholder="z.B. Mörder" maxlength="50" value="<?= e($v('name')) ?>" required>
</div>

<div class="grid-2">
  <div class="form-group">
    <label class="form-label" for="<?= $prefix ?>cooldown">Cooldown (Minuten)</label>
    <input class="form-input" type="number" min="0" max="10080" id="<?= $prefix ?>cooldown"
           placeholder="0 = kein Cooldown" value="<?= e($v('cooldown', 0)) ?>">
  </div>
  <div class="form-group">
    <label class="form-label" for="<?= $prefix ?>amount">Anzahl im Spiel</label>
    <input class="form-input" type="number" min="0" id="<?= $prefix ?>amount"
           placeholder="z.B. 2" value="<?= e($v('amount', 1)) ?>">
    <small class="text-dim text-xs">Wird bei „Auffüll-Rolle" ignoriert.</small>
  </div>
</div>

<div class="form-group">
  <label class="form-label" for="<?= $prefix ?>description">Beschreibung</label>
  <textarea class="form-input" id="<?= $prefix ?>description" rows="2"
            placeholder="Was macht diese Rolle?"><?= e($v('description')) ?></textarea>
</div>

<div class="form-group">
  <label class="form-label" for="<?= $prefix ?>rules">Regeln</label>
  <textarea class="form-input" id="<?= $prefix ?>rules" rows="2"
            placeholder="Spielregeln für diese Rolle"><?= e($v('rules')) ?></textarea>
</div>

<div class="icon-sort-row">

  <div class="form-group icon-sort-row__icon">
    <label class="form-label">Rollen-Icon <small class="text-dim text-xs">(256 × 256 px · max. 2 MB · PNG/JPG)</small></label>
    <div class="icon-uploader" id="<?= $prefix ?>uploader">
      <div class="icon-uploader__preview" id="<?= $prefix ?>icon-preview">
        <?php if ($v('icon_path')): ?>
          <img src="<?= APP_URL ?>/<?= e($v('icon_path')) ?>" alt="" onerror="this.style.display='none'">
        <?php else: ?>
          <span class="icon-uploader__placeholder">🎭</span>
        <?php endif; ?>
      </div>
      <div class="icon-uploader__controls">
        <input type="file" id="<?= $prefix ?>icon_file" accept=".png,.jpg,.jpeg,image/png,image/jpeg" style="display:none"
               onchange="onIconFileSelected('<?= $prefix ?>')">
        <button type="button" class="btn btn--secondary btn--sm" onclick="document.getElementById('<?= $prefix ?>icon_file').click()">
          📁 Bild wählen (PNG oder JPG)
        </button>
        <div class="text-xs text-dim mt-1">Empfohlene Größe: 256 × 256 px &middot; max. 2 MB</div>
        <button type="button" class="btn btn--primary btn--sm mt-1" id="<?= $prefix ?>upload-btn" style="display:none" onclick="uploadIconFile('<?= $prefix ?>')">
          ⬆ Hochladen
        </button>
        <div id="<?= $prefix ?>upload-status" class="text-xs mt-1"></div>
      </div>
    </div>
    <input type="hidden" id="<?= $prefix ?>icon_path" value="<?= e($v('icon_path')) ?>">

  </div>

  <div class="form-group icon-sort-row__sort">
    <label class="form-label" for="<?= $prefix ?>sort_order">Sortierung</label>
    <input class="form-input" type="number" id="<?= $prefix ?>sort_order"
           placeholder="0" value="<?= e($v('sort_order', 0)) ?>">
    <div><small class="text-dim text-xs">Anzeigereihenfolge in Listen.</small></div>
  </div>

</div>

<div class="grid-2" style="margin-bottom:.5rem">
  <div class="form-group flex gap-sm" style="align-items:center">
    <input type="checkbox" id="<?= $prefix ?>active" <?= $v('active', 1) ? 'checked' : '' ?>
           style="width:18px;height:18px;accent-color:var(--accent)">
    <label class="form-label" for="<?= $prefix ?>active" style="margin:0">Aktiv</label>
  </div>
  <div class="form-group flex gap-sm" style="align-items:center">
    <input type="checkbox" id="<?= $prefix ?>fill" <?= $v('fill', 0) ? 'checked' : '' ?>
           style="width:18px;height:18px;accent-color:var(--accent)"
           onchange="onFillToggle('<?= $prefix ?>', this.checked)">
    <label class="form-label" for="<?= $prefix ?>fill" style="margin:0">Auffüll-Rolle</label>
  </div>
  <div class="form-group flex gap-sm" style="align-items:center">
    <input type="checkbox" id="<?= $prefix ?>sichtbar" <?= $v('sichtbar', 0) ? 'checked' : '' ?>
           style="width:18px;height:18px;accent-color:var(--accent)">
    <label class="form-label" for="<?= $prefix ?>sichtbar" style="margin:0">Sichtbar untereinander</label>
  </div>
  <div class="form-group flex gap-sm" style="align-items:center">
    <input type="checkbox" id="<?= $prefix ?>befragen" <?= $v('befragen', 0) ? 'checked' : '' ?>
           style="width:18px;height:18px;accent-color:var(--accent)">
    <label class="form-label" for="<?= $prefix ?>befragen" style="margin:0">Darf Tote befragen</label>
  </div>
  <div class="form-group flex gap-sm" style="align-items:center">
    <input type="checkbox" id="<?= $prefix ?>auto_eintrag" <?= $v('auto_eintrag', 0) ? 'checked' : '' ?>
           style="width:18px;height:18px;accent-color:var(--accent)">
    <label class="form-label" for="<?= $prefix ?>auto_eintrag" style="margin:0">⭐ Star — Ort &amp; Zeit automatisch eintragen</label>
  </div>
  <div class="form-group flex gap-sm" style="align-items:center">
    <input type="checkbox" id="<?= $prefix ?>is_killer" <?= $v('is_killer', 0) ? 'checked' : '' ?>
           style="width:18px;height:18px;accent-color:#f87171">
    <label class="form-label" for="<?= $prefix ?>is_killer" style="margin:0">🔪 Killer-Team</label>
  </div>
</div>
<p class="text-dim text-xs mt-1" style="margin-top:-.5rem">
  <strong>Auffüll-Rolle</strong>: Spieler ohne Sonderrolle bekommen diese Rolle (z.B. Bürger). &nbsp;|&nbsp;
  <strong>Sichtbar untereinander</strong>: Spieler mit dieser Rolle erkennen sich beim Start gegenseitig (z.B. Mörder). &nbsp;|&nbsp;
  <strong>Darf Tote befragen</strong>: Diese Rolle sieht Ort &amp; Zeit in der Todesliste. &nbsp;|&nbsp;
  <strong>Star</strong>: Ort &amp; Zeit werden beim Sterben sofort automatisch eingetragen. &nbsp;|&nbsp;
  <strong>Killer-Team</strong>: Zählt zur Killer-Seite — Killer gewinnen wenn sie die Bürger zahlenmäßig erreichen.
</p>
