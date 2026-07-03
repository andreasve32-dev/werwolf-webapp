<?php
// Copyright (c) 2026 Andreas Vetter
// Wiederverwendbare Render-Funktionen für faq.php — genutzt sowohl beim
// initialen Seitenaufbau als auch von api/game.php (get_faq) fürs Polling.

if (!function_exists('render_faq_list')) {
function render_faq_list(array $faqEntries): string {
    ob_start();
    ?>
    <?php if (empty($faqEntries)): ?>
    <div class="card text-center" style="padding:3rem 1.5rem">
      <div style="font-size:2rem;margin-bottom:.75rem">🔕</div>
      <p class="text-dim">Noch keine FAQ-Einträge vorhanden.</p>
      <p class="text-dim text-sm">
        Du kannst dem Spielleiter über die Schaltfläche<br>
        „✉️ Frage stellen" auf der Spielseite eine Frage senden.
      </p>
    </div>
    <?php else: ?>
    <div id="faq-list" style="display:flex;flex-direction:column;gap:.75rem">
      <?php foreach ($faqEntries as $i => $entry): ?>
      <div class="faq-item card animate-in"
           style="animation-delay:<?= $i * 0.04 ?>s;padding:0;overflow:hidden"
           data-search="<?= e(strtolower($entry['message'] . ' ' . $entry['reply'])) ?>">

        <!-- Frage (Akkordeon-Header) -->
        <button class="faq-item__q" onclick="toggleFaq(this)" aria-expanded="false">
          <span class="faq-item__q-icon">Q</span>
          <span class="faq-item__q-text"><?= e($entry['message']) ?></span>
          <span class="faq-item__chevron">▼</span>
        </button>

        <!-- Antwort (eingeklappt) -->
        <div class="faq-item__a" hidden>
          <span class="faq-item__a-icon">A</span>
          <div class="faq-item__a-text"><?= nl2br(e($entry['reply'])) ?></div>
        </div>

      </div>
      <?php endforeach; ?>
    </div>
    <p id="faq-empty" class="text-dim text-center" style="display:none;padding:2rem">
      Keine Einträge für diese Suchanfrage gefunden.
    </p>
    <?php endif; ?>
    <?php
    return trim(ob_get_clean());
}
}

if (!function_exists('render_roles_rules_list')) {
function render_roles_rules_list(array $roles): string {
    ob_start();
    ?>
    <?php if (empty($roles)): ?>
    <div class="card text-center" style="padding:3rem 1.5rem">
      <p class="text-dim">Keine aktiven Rollen konfiguriert.</p>
    </div>
    <?php else: ?>
    <div id="roles-list" style="display:flex;flex-direction:column;gap:.6rem">
      <?php foreach ($roles as $i => $r): ?>
      <div class="role-rule-item animate-in"
           style="animation-delay:<?= $i * 0.04 ?>s"
           data-search="<?= e(strtolower($r['name'] . ' ' . $r['description'] . ' ' . $r['rules'])) ?>">

        <!-- Kopfzeile (klickbar) -->
        <button class="role-rule-item__head" onclick="toggleRole(this)" aria-expanded="false">
          <span class="role-rule-item__icon"
                style="background-image:url('<?= e(roleIconUrl($r)) ?>')"></span>
          <span class="role-rule-item__name"><?= e($r['name']) ?></span>
          <div class="role-rule-item__tags">
            <?php if (!empty($r['sichtbar'])): ?>
              <span class="tag tag--day" style="font-size:.66rem">👁️ Sichtbar</span>
            <?php endif; ?>
            <?php if ($r['cooldown'] > 0): ?>
              <span class="tag tag--lobby" style="font-size:.66rem">⏳ <?= (int)$r['cooldown'] ?> Min.</span>
            <?php endif; ?>
          </div>
          <span class="role-rule-item__chevron">▼</span>
        </button>

        <!-- Regeltext (eingeklappt) -->
        <div class="role-rule-item__body" hidden>
          <?php if ($r['description']): ?>
          <p class="role-rule-item__desc"><?= e($r['description']) ?></p>
          <?php endif; ?>
          <?php if ($r['rules']): ?>
          <div class="role-rule-item__rules">
            <span class="role-rule-item__rules-label">📜 Regeln</span>
            <?= e($r['rules']) ?>
          </div>
          <?php endif; ?>
        </div>

      </div>
      <?php endforeach; ?>
    </div>
    <p id="roles-empty" class="text-dim text-center" style="display:none;padding:2rem">
      Keine Rollen für diese Suchanfrage gefunden.
    </p>
    <?php endif; ?>
    <?php
    return trim(ob_get_clean());
}
}
