<?php
// Copyright (c) 2026 Andreas Vetter
// Wiederverwendbare Testspieler-Logik — genutzt von admin/debug.php (Anzeige)
// und admin/testplayers.php (die drei AJAX-Aktionen).

if (!defined('TEST_PREFIX')) {
    define('TEST_PREFIX', 'test_');
    define('TEST_DISPLAY_PX', 'Testspieler ');
    define('TEST_PASSWORD', 'test1234');
    define('TEST_MAX', 20);
}

if (!function_exists('isTestPlayer')) {
    /** Ist diese Player-Row ein Testspieler? */
    function isTestPlayer(array $p): bool {
        return (bool)preg_match('/^test_\d{2}$/', $p['username']);
    }
}

if (!function_exists('admin_render_testplayers')) {
function admin_render_testplayers(): string {
    $allTestPlayers = array_filter(
        Database::query("SELECT id, username, display_name FROM players WHERE username REGEXP '^test_[0-9]{2}$' ORDER BY username"),
        'isTestPlayer'
    );
    $testCount = count($allTestPlayers);
    ob_start();
    ?>
    <!-- Testspieler -->
    <div class="card card--glow animate-in mb-2" style="animation-delay:.12s;border-color:rgba(251,191,36,.35);background:rgba(251,191,36,.04)">
      <div class="section-title" style="color:#fbbf24">🤖 Testspieler</div>
      <p class="text-dim text-xs mb-2">
        Bis zu <?= TEST_MAX ?> Test-Konten anlegen/löschen — Login <code>test_01</code> …
        <code>test_<?= str_pad(TEST_MAX, 2, '0', STR_PAD_LEFT) ?></code>, Passwort
        <code><?= TEST_PASSWORD ?></code> (für alle gleich).
        Aktuell: <strong id="tp-cur-count"><?= $testCount ?></strong> / <?= TEST_MAX ?>.
      </p>
      <div class="flex gap-md" style="align-items:center;flex-wrap:wrap;margin-bottom:.75rem">
        <div style="flex:1;min-width:180px">
          <label class="form-label" for="tp-create-count">Anzahl anlegen</label>
          <div class="flex gap-sm" style="align-items:center">
            <input type="range" id="tp-create-slider" min="1" max="<?= TEST_MAX ?>"
                   value="<?= min(5, max(1, TEST_MAX - $testCount)) ?>"
                   style="flex:1;accent-color:#fbbf24"
                   oninput="document.getElementById('tp-create-count').value=this.value">
            <input type="number" id="tp-create-count" min="1" max="<?= TEST_MAX ?>"
                   value="<?= min(5, max(1, TEST_MAX - $testCount)) ?>"
                   class="form-input"
                   style="width:4.5rem;text-align:center;padding:.35rem .5rem;font-size:.9rem"
                   oninput="document.getElementById('tp-create-slider').value=this.value">
          </div>
        </div>
        <button class="btn btn--ghost" style="border-color:rgba(251,191,36,.4);color:#fbbf24"
                onclick="tpCreatePlayers()">➕ Anlegen</button>
      </div>
      <div id="tp-create-result" style="display:none;margin-bottom:.5rem"></div>

      <div class="section-title" style="font-size:.85rem;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.5rem">
        <span>Vorhanden (<span id="tp-list-count"><?= $testCount ?></span>)</span>
        <button class="btn btn--danger btn--sm" id="tp-delete-all-btn"
                style="<?= $testCount > 0 ? '' : 'display:none' ?>" onclick="tpDeleteAll()">🗑 Alle löschen</button>
      </div>
      <div id="tp-list" style="display:flex;flex-direction:column;gap:.4rem;margin-top:.5rem">
        <p class="text-dim text-center text-sm" id="tp-empty-hint" style="padding:.75rem 0;<?= $testCount > 0 ? 'display:none' : '' ?>">
          Noch keine Testspieler angelegt.
        </p>
        <?php foreach ($allTestPlayers as $p): ?>
        <div class="panel" id="tp-<?= (int)$p['id'] ?>"
             style="padding:.5rem .8rem;display:flex;justify-content:space-between;align-items:center;gap:.5rem;flex-wrap:wrap">
          <div class="flex gap-sm" style="align-items:center">
            <span style="font-family:var(--font-display);font-size:.88rem;color:var(--text-bright)"><?= e($p['display_name']) ?></span>
            <span class="text-dim text-xs">Login: <code style="font-family:monospace"><?= e($p['username']) ?></code></span>
          </div>
          <button class="btn btn--ghost btn--sm" title="Löschen"
                  onclick="tpDeleteOne(<?= (int)$p['id'] ?>, '<?= e(addslashes($p['display_name'])) ?>')">🗑</button>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php
    return trim(ob_get_clean());
}
}
