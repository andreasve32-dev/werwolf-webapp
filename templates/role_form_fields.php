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
  <small class="text-dim text-xs">Tipp: <code>{cooldown}</code> in Beschreibung/Regeln wird
    bei der Anzeige automatisch durch den aktuellen Cooldown-Wert ersetzt —
    z.&nbsp;B. „alle {cooldown} Minuten“.</small>
</div>

<div class="icon-sort-row">

  <div class="form-group icon-sort-row__icon">
    <label class="form-label">Rollen-Icon <small class="text-dim text-xs">(256 × 256 px · max. 2 MB · PNG/JPG)</small></label>
    <div class="icon-uploader" id="<?= $prefix ?>uploader">
      <div class="icon-uploader__preview" id="<?= $prefix ?>icon-preview">
        <?php if ($v('icon_path')): ?>
          <img src="<?= e(assetUrl($v('icon_path'))) ?>" alt="" onerror="this.style.display='none'">
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

<?php
// ── Rollen-Flags als waagerechte Tabs ─────────────────────────
// Ein Tab pro Flag: Klick öffnet den Tab-Inhalt mit Checkbox + ausführlicher
// Erklärung direkt beim Flag (nochmal klicken klappt wieder zu). Gesetzte
// Flags zeigen ein ✓ im Tab, so bleibt der Zustand ohne Durchklicken sichtbar.
// NEUE FLAGS: hier einen Array-Eintrag ergänzen (Key = Spaltenname, siehe
// Checkliste in CLAUDE.md) — Tab, Panel und ✓-Anzeige entstehen automatisch.
// collectFormData() in admin/roles.php liest weiterhin {prefix}{key}.
$roleFlags = [
    'active' => [
        'icon' => '✅', 'label' => 'Aktiv', 'accent' => 'var(--accent)', 'default' => 1,
        'desc' => 'Nur aktive Rollen werden bei der Rollenverteilung berücksichtigt. Deaktivierte Rollen bleiben gespeichert, kommen aber in kein Spiel.',
    ],
    'fill' => [
        'icon' => '👥', 'label' => 'Auffüll-Rolle', 'accent' => 'var(--accent)', 'default' => 0,
        'desc' => 'Spieler ohne Sonderrolle bekommen diese Rolle (z.B. Bürger). Das Feld „Anzahl im Spiel" wird dabei ignoriert — aufgefüllt wird, so viele Spieler übrig sind.',
        'onchange' => "onFillToggle('{$prefix}', this.checked);",
    ],
    'sichtbar' => [
        'icon' => '🤝', 'label' => 'Sichtbar untereinander', 'accent' => 'var(--accent)', 'default' => 0,
        'desc' => 'Spieler mit dieser Rolle erkennen sich beim Spielstart gegenseitig in der Spielerliste (z.B. Mörder).',
    ],
    'killer_sichtbar' => [
        'icon' => '🔪👁', 'label' => 'Mit Killern sichtbar', 'accent' => '#f87171', 'default' => 0,
        'desc' => 'Gegenseitig sichtbar mit allen Killer-Rollen (z.B. Dodo): Diese Rolle sieht alle Killer — und die Killer sehen sie.',
    ],
    'befragen' => [
        'icon' => '⚰️', 'label' => 'Darf Tote befragen', 'accent' => 'var(--accent)', 'default' => 0,
        'desc' => 'Diese Rolle sieht Ort &amp; Zeit der Todesfälle in der Todesliste (z.B. Nekromant).',
    ],
    'auto_eintrag' => [
        'icon' => '⭐', 'label' => 'Star', 'accent' => 'var(--accent)', 'default' => 0,
        'desc' => 'Ort &amp; Zeit werden beim Sterben sofort automatisch in die Todesliste eingetragen — der Tod dieses Spielers ist für alle sichtbar dokumentiert.',
    ],
    'is_killer' => [
        'icon' => '🔪', 'label' => 'Killer-Team', 'accent' => '#f87171', 'default' => 0,
        'desc' => 'Zählt zur Killer-Seite. Die Killer gewinnen, wenn sie die Bürger zahlenmäßig erreichen — Grundlage für Siegprüfung und Kill-Hinweise.',
    ],
    'linked_death' => [
        'icon' => '💔', 'label' => 'Partner-Benachrichtigung', 'accent' => 'var(--accent)', 'default' => 0,
        'desc' => 'Stirbt ein Spieler dieser Rolle, werden alle anderen lebenden Spieler derselben Rolle per Push benachrichtigt (z.B. Das Paar) — sie sterben nicht automatisch mit, sondern können sich jederzeit selbst als tot melden.',
    ],
    'rollensicht' => [
        'icon' => '🔮', 'label' => 'Rollensicht', 'accent' => 'var(--accent)', 'default' => 0,
        'desc' => 'Der Fähigkeit-Button fragt nach einem Untersuchungs-Ziel; dessen Rolle bleibt für diesen Spieler dauerhaft in der Spielerliste sichtbar (z.B. Hellseherin). Braucht einen Cooldown-Wert &gt; 0 — der Button erscheint nur mit Cooldown.',
    ],
    'kill_hinweis' => [
        'icon' => '🕵️', 'label' => 'Kill-Hinweise', 'accent' => 'var(--accent)', 'default' => 0,
        'desc' => 'Vollautomatisch: Immer wenn so viele Morde geschehen sind, wie es Killer gibt, erfährt der Spieler einen zufälligen Nicht-Killer („✅ Kein Killer" in der Spielerliste, mit Push) — z.B. Detektiv.',
    ],
];
?>
<div class="form-group">
  <label class="form-label">Rollen-Flags <small class="text-dim text-xs">(Tab antippen für Erklärung &amp; Schalter — ✓ = gesetzt)</small></label>
  <div class="flex gap-xs" style="flex-wrap:wrap" id="<?= $prefix ?>flag-tabs">
    <?php foreach ($roleFlags as $key => $f): $checked = (bool)$v($key, $f['default']); ?>
    <button type="button" class="btn btn--ghost btn--sm" id="<?= $prefix ?>flagtab-<?= $key ?>"
            onclick="flagTabToggle('<?= $prefix ?>', '<?= $key ?>')">
      <?= $f['icon'] ?> <?= e($f['label']) ?><span id="<?= $prefix ?>flagmark-<?= $key ?>"
            style="color:#4ade80;font-weight:700;<?= $checked ? '' : 'display:none' ?>"> ✓</span>
    </button>
    <?php endforeach; ?>
  </div>
  <?php foreach ($roleFlags as $key => $f): $checked = (bool)$v($key, $f['default']); ?>
  <div id="<?= $prefix ?>flagpanel-<?= $key ?>" data-flag-panel
       style="display:none;margin-top:.5rem;background:var(--panel-bg);border:1px solid var(--border);
              border-radius:8px;padding:.7rem .85rem">
    <div class="flex gap-sm" style="align-items:center">
      <input type="checkbox" id="<?= $prefix ?><?= $key ?>" <?= $checked ? 'checked' : '' ?>
             style="width:18px;height:18px;flex-shrink:0;accent-color:<?= $f['accent'] ?>"
             onchange="<?= $f['onchange'] ?? '' ?>flagMarkUpdate('<?= $prefix ?>', '<?= $key ?>', this.checked)">
      <label class="form-label" for="<?= $prefix ?><?= $key ?>" style="margin:0;cursor:pointer">
        <?= $f['icon'] ?> <?= e($f['label']) ?>
      </label>
    </div>
    <p class="text-dim text-xs" style="margin:.45rem 0 0;line-height:1.55"><?= $f['desc'] ?></p>
  </div>
  <?php endforeach; ?>
</div>
