<?php
// Copyright (c) 2026 Andreas Vetter
// Wiederverwendbare Render-Funktion für roles.php — genutzt sowohl beim
// initialen Seitenaufbau als auch von api/game.php (get_roles) fürs Polling.

if (!function_exists('render_roles_gallery')) {
function render_roles_gallery(array $roles): string {
    ob_start();
    ?>
    <?php if (empty($roles)): ?>
      <div class="card text-center text-dim" style="padding:2.5rem">
        Noch keine aktiven Rollen konfiguriert.
      </div>
    <?php else: ?>
    <div class="roles-gallery">
      <?php foreach ($roles as $r): ?>
      <div class="rg-card animate-in" onclick="openRoleCard(<?= (int)$r['id'] ?>)" style="cursor:pointer" title="<?= e($r['name']) ?> — Karte anzeigen">
        <div class="rg-card__icon"
             style="background-image:url('<?= e(roleIconUrl($r)) ?>')"
             role="img" aria-label="<?= e($r['name']) ?>"></div>
        <div class="rg-card__name"><?= e($r['name']) ?></div>
        <?php if ($r['description']): ?>
          <div class="rg-card__desc"><?= e($r['description']) ?></div>
        <?php endif; ?>
        <div class="rg-card__meta">
          <?php if (!empty($r['sichtbar'])): ?>
            <span class="tag tag--day" style="font-size:.68rem">👁️ Sichtbar untereinander</span>
          <?php endif; ?>
          <?php if (!empty($r['killer_sichtbar'])): ?>
            <span class="tag tag--dead" style="font-size:.68rem">🔪👁 Sichtbar mit Mördern</span>
          <?php endif; ?>
          <?php if ($r['cooldown'] > 0): ?>
            <span class="tag tag--lobby" style="font-size:.68rem">⏳ <?= (int)$r['cooldown'] ?> Min.</span>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php
    return trim(ob_get_clean());
}
}

if (!function_exists('roles_data_json')) {
function roles_data_json(array $roles): array {
    return array_map(fn($r) => [
        'id'          => (int)$r['id'],
        'name'        => $r['name'],
        'icon_url'    => roleIconUrl($r),
        'sichtbar'    => (bool)$r['sichtbar'],
        'description' => $r['description'] ?? '',
        'rules'       => $r['rules'] ?? '',
        'cooldown'    => (int)$r['cooldown'],
    ], $roles);
}
}
