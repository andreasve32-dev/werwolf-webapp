<?php
// Copyright (c) 2026 Andreas Vetter
// Wiederverwendbare Zeilen-Markup für einen Dorf-Spruch — genutzt sowohl
// von admin/slogans.php (initiale Liste) als auch von api/admin.php
// (add_slogan) für den DOM-Patch ohne Seitenreload.

if (!function_exists('sloganRow')) {
function sloganRow(array $s): string {
    $id     = (int)$s['id'];
    $text   = htmlspecialchars($s['text'], ENT_QUOTES, 'UTF-8');
    $active = (bool)(int)$s['active'];
    $opacity = $active ? '1' : '.45';
    return <<<HTML
    <div class="flex-between" id="slogan-row-{$id}"
         style="padding:.55rem .1rem;border-bottom:1px solid var(--border);gap:.5rem;opacity:{$opacity}">
      <span class="text-sm" style="flex:1">{$text}</span>
      <div class="flex gap-xs" style="flex-shrink:0">
        <button class="btn btn--ghost btn--sm" onclick="toggleSlogan({$id})" title="Aktiv / Inaktiv">
          <span id="slogan-icon-{$id}">
HTML . ($active ? '✓' : '○') . <<<HTML
          </span>
        </button>
        <button class="btn btn--danger btn--sm" onclick="deleteSlogan({$id})" title="Löschen">✕</button>
      </div>
    </div>
    HTML;
}
}
