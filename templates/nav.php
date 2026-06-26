<?php
// Copyright (c) 2026 Andreas Vetter
/**
 * templates/nav.php — Mobile-first Navigation
 *
 * Handy:   Schmale Top-Bar (Logo + Theme-Globe + Logout)
 *          + fixe Bottom-Tab-Bar (Spiel | Tote | [Admin] | Optionen)
 * Desktop: Top-Bar mit Theme-Icon-Reihe + Subnav-Pille
 *
 * Alle Seiten nutzen dieses Template.
 */
$currentFile = basename($_SERVER['PHP_SELF']);
$inAdmin     = strpos($_SERVER['REQUEST_URI'], '/admin') !== false;
$player      = Auth::player();
$hasMusic    = defined('BACKGROUND_MUSIC') && BACKGROUND_MUSIC;
?>

<!-- ── Theme-Backdrop & -Sheet ────────────────────────────── -->
<div class="sheet-backdrop" id="theme-backdrop" onclick="closeThemeSheet()"></div>
<div class="theme-sheet" id="theme-sheet">
  <div class="theme-sheet__handle"></div>
  <div class="theme-sheet__grid">
    <?php foreach (THEMES as $key => $theme): ?>
    <a href="?theme=<?= e($key) ?>"
       class="theme-option <?= $activeTheme === $key ? 'active' : '' ?>"
       onclick="switchTheme(event,'<?= e($key) ?>')">
      <span class="theme-option__swatch" style="background:<?= e($theme['preview']) ?>">
        <?= $theme['icon'] ?>
      </span>
      <span class="theme-option__label"><?= e($theme['label']) ?></span>
      <span class="theme-option__desc"><?= e($theme['desc']) ?></span>
    </a>
    <?php endforeach; ?>
  </div>
</div>

<!-- ── Einstellungen-Backdrop & -Sheet ───────────────────── -->
<div class="sheet-backdrop" id="settings-backdrop" onclick="closeSettingsSheet()"></div>
<div class="theme-sheet" id="settings-sheet">
  <div class="theme-sheet__handle"></div>
  <div class="settings-sheet-header">
    <p class="settings-sheet-title">⚙️ Einstellungen</p>
    <button class="settings-close-btn" onclick="closeSettingsSheet()" aria-label="Schließen">✕</button>
  </div>

  <?php if ($hasMusic): ?>
  <!-- Musik -->
  <div class="settings-section">
    <div class="settings-label">🎵 Musik</div>
    <div class="settings-row">
      <span class="settings-row__name">Lautstärke</span>
      <div class="settings-row__ctrl">
        <input type="range" id="set-vol" min="0" max="100" step="5"
               class="vol-slider"
               oninput="settingVol(this.value/100)">
        <span id="set-vol-label" class="text-dim text-xs" style="min-width:2.5rem;text-align:right">25%</span>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Benachrichtigungen -->
  <?php if (Auth::check()): ?>
  <div class="settings-section" id="push-settings-section"
       style="<?= (!isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] ?? '') !== 'on') ? 'display:none' : '' ?>">
    <div class="settings-label">🔔 Benachrichtigungen</div>
    <div class="settings-row">
      <span class="settings-row__name">Push-Benachrichtigungen</span>
      <label class="toggle-switch">
        <input type="checkbox" id="set-push" onchange="pushToggle(this.checked)">
        <span class="toggle-switch__track"></span>
      </label>
    </div>
    <div id="push-hint" class="text-dim text-xs" style="display:none;padding:.4rem 0 0;line-height:1.5"></div>
  </div>
  <?php endif; ?>

  <!-- Atmosphäre -->
  <div class="settings-section">
    <div class="settings-label">🌤️ Atmosphäre</div>
    <div class="settings-row">
      <div>
        <span class="settings-row__name" id="daynight-label">🌙 Nachtmodus</span>
        <div class="text-dim text-xs" style="margin-top:.15rem">Nur Optik — kein Spieleffekt</div>
      </div>
      <label class="toggle-switch">
        <input type="checkbox" id="set-daynight" onchange="toggleDayNight(this.checked)">
        <span class="toggle-switch__track"></span>
      </label>
    </div>
  </div>

  <!-- Effekte -->
  <div class="settings-section">
    <div class="settings-label">✨ Effekte</div>

    <div class="settings-row">
      <span class="settings-row__name">Glühwürmchen</span>
      <label class="toggle-switch">
        <input type="checkbox" id="set-particles"
               onchange="settingToggle('particles',this.checked)">
        <span class="toggle-switch__track"></span>
      </label>
    </div>

    <div class="settings-row">
      <span class="settings-row__name">Button-Ripple</span>
      <label class="toggle-switch">
        <input type="checkbox" id="set-ripple"
               onchange="settingToggle('ripple',this.checked)">
        <span class="toggle-switch__track"></span>
      </label>
    </div>

    <div class="settings-row">
      <span class="settings-row__name">Phasen-Überblendung</span>
      <label class="toggle-switch">
        <input type="checkbox" id="set-phase"
               onchange="settingToggle('phase',this.checked)">
        <span class="toggle-switch__track"></span>
      </label>
    </div>

    <div class="settings-row">
      <span class="settings-row__name">Schädelregen</span>
      <label class="toggle-switch">
        <input type="checkbox" id="set-skulls"
               onchange="settingToggle('skulls',this.checked)">
        <span class="toggle-switch__track"></span>
      </label>
    </div>

    <div class="settings-row">
      <span class="settings-row__name">Einblend-Animationen</span>
      <label class="toggle-switch">
        <input type="checkbox" id="set-anims"
               onchange="settingToggle('anims',this.checked)">
        <span class="toggle-switch__track"></span>
      </label>
    </div>

    <div class="settings-row">
      <span class="settings-row__name">Nächtlicher Nebel</span>
      <label class="toggle-switch">
        <input type="checkbox" id="set-fog"
               onchange="settingToggle('fog',this.checked)">
        <span class="toggle-switch__track"></span>
      </label>
    </div>

    <div class="settings-row">
      <span class="settings-row__name">Karten-Flammeneffekt</span>
      <label class="toggle-switch">
        <input type="checkbox" id="set-rolecard"
               onchange="settingToggle('rolecard',this.checked)">
        <span class="toggle-switch__track"></span>
      </label>
    </div>

    <div class="settings-row">
      <span class="settings-row__name">Rollenname-Leuchten</span>
      <label class="toggle-switch">
        <input type="checkbox" id="set-rolename"
               onchange="settingToggle('rolename',this.checked)">
        <span class="toggle-switch__track"></span>
      </label>
    </div>
  </div>
</div>

<!-- ── Top-Bar ─────────────────────────────────────────────── -->
<nav class="nav nav--top">
  <div class="container">
    <div class="nav__inner">
      <a href="<?= APP_URL ?>/game.php" class="nav__logo">
        <?php
        $__navLogo = '';
        if (MINI_LOGO !== '' && file_exists(ROOT_PATH . '/' . MINI_LOGO)) {
            $__navLogo = MINI_LOGO;
        } elseif (file_exists(ROOT_PATH . '/assets/icons/logo/mini_logo.png')) {
            $__navLogo = 'assets/icons/logo/mini_logo.png';
        }
        ?>
        <?php if ($__navLogo !== ''): ?>
          <img src="<?= e(assetUrl($__navLogo)) ?>" alt="<?= e(APP_NAME) ?>" class="nav__logo-img">
        <?php else: ?>
          🐺
        <?php endif; ?>
        <span><?= APP_NAME ?></span>
      </a>
      <div class="flex gap-xs" style="align-items:center">

        <!-- Handy: Globe öffnet Theme-Sheet -->
        <button class="theme-trigger nav--mobile-only"
                onclick="openThemeSheet()"
                aria-label="Theme wählen">
          <?= THEMES[$activeTheme]['icon'] ?>
        </button>

        <!-- Desktop: direkte Icon-Reihe -->
        <div class="theme-row nav--desktop-only">
          <?php foreach (THEMES as $key => $theme): ?>
          <a href="?theme=<?= e($key) ?>"
             class="theme-row__btn <?= $activeTheme === $key ? 'active' : '' ?>"
             style="background:<?= e($theme['preview']) ?>"
             title="<?= e($theme['label']) ?> — <?= e($theme['desc']) ?>"
             onclick="switchTheme(event,'<?= e($key) ?>')">
            <?= $theme['icon'] ?>
          </a>
          <?php endforeach; ?>
        </div>

        <a href="<?= APP_URL ?>/logout.php"
           class="nav__link nav__link--danger nav__link--icon"
           aria-label="Abmelden">↩</a>
      </div>
    </div>
  </div>
</nav>

<!-- ── Tab-Bar (Handy + Desktop) ──────────────────────────── -->
<nav class="tabbar" role="navigation" aria-label="Navigation">
<?php if ($inAdmin): ?>
  <a href="<?= APP_URL ?>/game.php" class="tabbar__item">
    <span class="tabbar__icon">
      <?php if ($__navLogo !== ''): ?>
        <img src="<?= e(assetUrl($__navLogo)) ?>" alt="" style="width:1.4em;height:1.4em;object-fit:contain;vertical-align:middle">
      <?php else: ?>🐺<?php endif; ?>
    </span>
    <span class="tabbar__label">Spiel</span>
  </a>
  <a href="<?= APP_URL ?>/admin/" class="tabbar__item active">
    <span class="tabbar__icon">👑</span>
    <span class="tabbar__label">Admin</span>
  </a>
<?php else: ?>
  <a href="<?= APP_URL ?>/game.php"
     class="tabbar__item <?= $currentFile === 'game.php' ? 'active' : '' ?>">
    <span class="tabbar__icon">
      <?php if ($__navLogo !== ''): ?>
        <img src="<?= e(assetUrl($__navLogo)) ?>" alt="" style="width:1.4em;height:1.4em;object-fit:contain;vertical-align:middle">
      <?php else: ?>🐺<?php endif; ?>
    </span>
    <span class="tabbar__label">Spiel</span>
  </a>
  <a href="<?= APP_URL ?>/deaths.php"
     class="tabbar__item <?= $currentFile === 'deaths.php' ? 'active' : '' ?>">
    <span class="tabbar__icon">⚰️</span>
    <span class="tabbar__label">Tote</span>
  </a>
  <a href="<?= APP_URL ?>/roles.php"
     class="tabbar__item <?= $currentFile === 'roles.php' ? 'active' : '' ?>">
    <span class="tabbar__icon">🎭</span>
    <span class="tabbar__label">Rollen</span>
  </a>
  <a href="<?= APP_URL ?>/stats.php"
     class="tabbar__item <?= $currentFile === 'stats.php' ? 'active' : '' ?>">
    <span class="tabbar__icon">📊</span>
    <span class="tabbar__label">Statistik</span>
  </a>
  <a href="<?= APP_URL ?>/faq.php"
     class="tabbar__item <?= $currentFile === 'faq.php' ? 'active' : '' ?>">
    <span class="tabbar__icon">❓</span>
    <span class="tabbar__label">FAQ</span>
  </a>
  <?php if ($player['is_admin']): ?>
  <a href="<?= APP_URL ?>/admin/"
     class="tabbar__item">
    <span class="tabbar__icon">👑</span>
    <span class="tabbar__label">Admin</span>
  </a>
  <?php endif; ?>
  <button class="tabbar__item" onclick="openSettingsSheet()" aria-label="Einstellungen">
    <span class="tabbar__icon">⚙️</span>
    <span class="tabbar__label">Optionen</span>
  </button>
<?php endif; ?>
</nav>

<script>
// ── Push-Toggle ──────────────────────────────────────────────
async function initPushToggle() {
  const el   = document.getElementById('set-push');
  const hint = document.getElementById('push-hint');
  if (!el || !('serviceWorker' in navigator) || !('PushManager' in window)) {
    const sec = document.getElementById('push-settings-section');
    if (sec) sec.style.display = 'none';
    return;
  }
  if (Notification.permission === 'denied') {
    el.checked  = false;
    el.disabled = true;
    if (hint) { hint.textContent = 'Im Browser blockiert — Schloss-Symbol in der Adressleiste → Benachrichtigungen → Zulassen.'; hint.style.display = 'block'; }
    return;
  }
  el.disabled = false;
  if (hint) hint.style.display = 'none';
  try {
    const reg = await navigator.serviceWorker.register('/sw.js');
    const sub = await reg.pushManager.getSubscription();
    el.checked = !!sub;
  } catch(e) {}
}

window.pushToggle = async function(enable) {
  const el = document.getElementById('set-push');
  if (Notification.permission === 'denied') {
    showToast('Im Browser blockiert — Schloss-Symbol in der Adressleiste → Benachrichtigungen → Zulassen.', 'error', 6000);
    if (el) el.checked = false;
    return;
  }
  try {
    const reg = await navigator.serviceWorker.ready;
    if (enable) {
      const r = await apiFetch(<?= json_encode(API_URL . '/push.php') ?>, {action: 'public_key'});
      if (!r || !r.key) { showToast('Push-Schlüssel fehlt', 'error'); if(el) el.checked = false; return; }
      const sub = await reg.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: _b64uToUint8(r.key),
      });
      const j = sub.toJSON();
      await apiFetch(<?= json_encode(API_URL . '/push.php') ?>, {
        action: 'subscribe', endpoint: j.endpoint,
        p256dh: (j.keys||{}).p256dh||'', auth: (j.keys||{}).auth||'',
      });
      localStorage.removeItem('ww_push_dismissed');
      showToast('🔔 Benachrichtigungen aktiviert!', 'success');
    } else {
      const sub = await reg.pushManager.getSubscription();
      if (sub) {
        await apiFetch(<?= json_encode(API_URL . '/push.php') ?>, {
          action: 'unsubscribe', endpoint: sub.endpoint,
        });
        await sub.unsubscribe();
      }
      localStorage.setItem('ww_push_dismissed', '1');
      showToast('🔕 Benachrichtigungen deaktiviert.', 'info');
    }
  } catch(e) {
    showToast('Fehler beim Ändern der Benachrichtigungen.', 'error');
    if (el) el.checked = !enable;
  }
};

// ── Einblend-Animationen sofort deaktivieren (vor Render) ────
if (localStorage.getItem('ww_fx_anims') === 'false') {
  document.body.classList.add('no-fx-anims');
}

// ── Theme-Sheet ──────────────────────────────────────────────
function openThemeSheet() {
  closeSettingsSheet();
  document.getElementById('theme-sheet').classList.add('open');
  document.getElementById('theme-backdrop').classList.add('open');
}
function closeThemeSheet() {
  document.getElementById('theme-sheet').classList.remove('open');
  document.getElementById('theme-backdrop').classList.remove('open');
}

// ── Einstellungen-Sheet ──────────────────────────────────────
function openSettingsSheet() {
  closeThemeSheet();

  // Lautstärke
  const vol = parseFloat(localStorage.getItem('ww_fx_vol') || '0.25');
  const slider = document.getElementById('set-vol');
  if (slider) {
    slider.value = Math.round(vol * 100);
    document.getElementById('set-vol-label').textContent = Math.round(vol * 100) + '%';
  }

  // Effekt-Toggles (Standard: an)
  ['particles','ripple','phase','skulls','anims','fog','rolecard','rolename'].forEach(k => {
    const el = document.getElementById('set-' + k);
    if (el) el.checked = localStorage.getItem('ww_fx_' + k) !== 'false';
  });

  // Atmosphäre-Toggle
  const dnEl = document.getElementById('set-daynight');
  const isDay = document.documentElement.getAttribute('data-daynight') === 'day';
  if (dnEl) { dnEl.checked = isDay; _updateDaynightLabel(isDay); }


  document.getElementById('settings-sheet').classList.add('open');
  document.getElementById('settings-backdrop').classList.add('open');
  initPushToggle();
}
function closeSettingsSheet() {
  document.getElementById('settings-sheet').classList.remove('open');
  document.getElementById('settings-backdrop').classList.remove('open');
}

// ── Effekt ein-/ausschalten & speichern ──────────────────────
function settingToggle(key, val) {
  localStorage.setItem('ww_fx_' + key, String(val));
  if (key === 'anims') {
    document.body.classList.toggle('no-fx-anims', !val);
    return;
  }
  if (key === 'rolecard') {
    document.body.classList.toggle('fx-rolecard-off', !val);
    return;
  }
  if (key === 'rolename') {
    document.body.classList.toggle('fx-rolename-off', !val);
    return;
  }
  if (window.FX) {
    const fn = 'set' + key.charAt(0).toUpperCase() + key.slice(1);
    if (typeof window.FX[fn] === 'function') window.FX[fn](val);
  }
}

// ── Atmosphäre-Toggle (rein visuell) ─────────────────────────
function _updateDaynightLabel(isDay) {
  const el = document.getElementById('daynight-label');
  if (el) el.textContent = isDay ? '☀️ Tagmodus' : '🌙 Nachtmodus';
}
function toggleDayNight(isDay) {
  localStorage.setItem('ww_daynight', isDay ? 'day' : 'night');
  document.documentElement.setAttribute('data-daynight', isDay ? 'day' : 'night');
  _updateDaynightLabel(isDay);
}

// ── Lautstärke ändern & speichern ───────────────────────────
function settingVol(val) {
  val = Math.max(0, Math.min(1, parseFloat(val)));
  localStorage.setItem('ww_fx_vol', val);
  const label = document.getElementById('set-vol-label');
  if (label) label.textContent = Math.round(val * 100) + '%';
  if (window._aud) window._aud.volume = val;
}
</script>
