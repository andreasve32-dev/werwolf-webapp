<?php
// Copyright (c) 2026 Andreas Vetter
// Wiederverwendbare Karten-Markup für eine einzelne Rolle — genutzt sowohl
// von admin/roles.php (initiale Liste) als auch von api/admin.php
// (role_create/role_update) für den DOM-Patch ohne Seitenreload.

if (!function_exists('render_role_card')) {
function render_role_card(array $r): string {
    ob_start();
    ?>
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
              <?php if (!empty($r['killer_sichtbar'])): ?><span class="tag tag--dead">🔪👁 Sichtbar mit Killern</span><?php endif; ?>
              <?php if (!empty($r['befragen'])): ?><span class="tag tag--night">🔍 Darf Tote befragen</span><?php endif; ?>
              <?php if (!empty($r['auto_eintrag'])): ?><span class="tag tag--running">⭐ Star</span><?php endif; ?>
              <?php if (!empty($r['is_killer'])): ?><span class="tag tag--dead">🔪 Killer</span><?php endif; ?>
              <?php if (!empty($r['linked_death'])): ?><span class="tag tag--night">💔 Gemeinsamer Tod</span><?php endif; ?>
            </div>
            <div class="text-dim text-sm mt-1"><?= e($r['description'] ? roleText($r['description'], $r) : 'Keine Beschreibung') ?></div>
            <div class="text-xs text-dim mt-1">
              Anzahl: <strong class="text-accent"><?= (int)$r['amount'] ?></strong> &middot;
              Cooldown: <strong class="text-accent"><?= (int)$r['cooldown'] ?></strong> Min.
            </div>
            <?php if ($r['rules']): ?>
            <div class="text-xs text-dim mt-1 italic">📜 <?= e(roleText($r['rules'], $r)) ?></div>
            <?php endif; ?>
          </div>
        </div>
        <div class="flex gap-xs" style="flex-shrink:0">
          <button class="btn btn--ghost btn--sm" onclick="toggleEdit(<?= (int)$r['id'] ?>)">✎</button>
          <label class="toggle-switch" title="Rolle aktivieren/deaktivieren">
            <input type="checkbox" data-toggle-input <?= $r['active'] ? 'checked' : '' ?>
                   onchange="toggleActive(<?= (int)$r['id'] ?>)">
            <span class="toggle-switch__track"></span>
          </label>
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
    <?php
    return trim(ob_get_clean());
}
}
