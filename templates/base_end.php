<?php // Copyright (c) 2026 Andreas Vetter ?>
<!-- ── JS ──────────────────────────────────────────────────── -->
<script src="<?= ASSETS_URL ?>/js/app.js?v=<?= filemtime(ROOT_PATH . '/assets/js/app.js') ?>"></script>

<?php if (!empty($page['extra_js'])): ?>
  <?php foreach ((array)$page['extra_js'] as $js): ?>
    <script src="<?= e($js) ?>"></script>
  <?php endforeach; ?>
<?php endif; ?>

<script src="<?= ASSETS_URL ?>/js/effects.js?v=<?= filemtime(ROOT_PATH . '/assets/js/effects.js') ?>"></script>

<script>const ASSET_VER = <?= json_encode(ASSET_VERSION) ?>;</script>
<?php if (!empty($page['inline_js'])): ?>
<script><?= $page['inline_js'] ?></script>
<?php endif; ?>

<footer class="legal-footer">
  <a href="<?= APP_URL ?>/impressum.php">Impressum</a>
  <span class="legal-footer__sep">·</span>
  <a href="<?= APP_URL ?>/datenschutz.php">Datenschutz</a>
  <span class="legal-footer__sep">·</span>
  <a href="<?= APP_URL ?>/nutzungsbedingungen.php">Nutzungsbedingungen</a>
  <span class="legal-footer__sep">·</span>
  <span class="legal-footer__copy">KI-generierte Charakterbilder, inspiriert von FINAL FANTASY XIV © SQUARE ENIX</span>
</footer>

<?php $__bepPlayer = Auth::check() ? Auth::player() : null; ?>
<?php if ($__bepPlayer && !empty($__bepPlayer['is_admin'])): ?>
<script>
(function() {
  const _msgApi     = <?= json_encode(API_URL . '/messages.php') ?>;
  let   _lastPending = 0;
  let   _firstPoll   = true; // kein Toast beim Seitenstart

  async function _pollAdminMsgs() {
    try {
      const r = await apiFetch(_msgApi, {action:'pending_count'});
      if (!r || r.error || r.pending === undefined) return;
      // nav-msg-badge (versteckt, nur Zustandsträger) + admin-msg-badge (sichtbar auf index.php)
      [document.getElementById('nav-msg-badge'), document.getElementById('admin-msg-badge')]
        .forEach(badge => {
          if (!badge) return;
          if (r.pending > 0) { badge.textContent = r.pending; badge.style.display = 'inline-block'; }
          else               { badge.style.display = 'none'; }
        });
      if (!_firstPoll && r.pending > _lastPending) {
        showToast('✉️ Neue Spielerfrage eingegangen!', 'info', 5000);
      }
      _firstPoll   = false;
      _lastPending = r.pending;
    } catch(e) {}
  }

  setInterval(_pollAdminMsgs, 30000);
})();
</script>
<?php endif; ?>

<?php if (defined('BACKGROUND_MUSIC') && BACKGROUND_MUSIC): ?>
<script>
(function() {
  window._aud = new Audio('<?= e(APP_URL) ?>/audio/<?= e(BACKGROUND_MUSIC) ?>');
  _aud.loop   = true;
  _aud.volume = Math.max(0, Math.min(1, parseFloat(localStorage.getItem('ww_fx_vol') || '0.25')));

  const _t = parseFloat(localStorage.getItem('ww_music_t') || '0');
  if (_t > 0) _aud.currentTime = _t;

  window.addEventListener('beforeunload', () => {
    localStorage.setItem('ww_music_t', String(_aud.currentTime));
  });

  function _setBtns(playing) {
    const bp = document.getElementById('music-btn-play');
    const bs = document.getElementById('music-btn-stop');
    if (bp) bp.disabled =  playing;
    if (bs) bs.disabled = !playing;
  }

  if (localStorage.getItem('ww_music') === '1') {
    _aud.play().then(() => _setBtns(true)).catch(() => {});
  }

  window.musicPlay = function() {
    _aud.play().then(() => {
      localStorage.setItem('ww_music', '1');
      _setBtns(true);
    }).catch(() => {});
  };

  window.musicStop = function() {
    _aud.pause();
    _aud.currentTime = 0;
    localStorage.removeItem('ww_music');
    localStorage.removeItem('ww_music_t');
    _setBtns(false);
  };
})();
</script>
<?php endif; ?>
<?php if (empty($page['skip_consent'])): ?>
<script>
window.acceptConsent = function () {
  localStorage.setItem('ww_consent', '1');
  var o = document.getElementById('consent-overlay');
  if (o) o.style.display = 'none';
};

window.declineConsent = function () {
  var o = document.getElementById('consent-overlay');
  if (!o) return;
  o.innerHTML =
    '<div style="background:var(--card-bg);border:1px solid var(--border);border-radius:var(--radius);' +
    'max-width:420px;width:100%;padding:2rem 1.75rem;text-align:center">' +
    '<div style="font-size:2rem;margin-bottom:.8rem">🔒</div>' +
    '<h2 style="font-family:var(--font-display);color:var(--text-bright);margin-bottom:.75rem">Zugang nicht möglich</h2>' +
    '<p style="font-size:.85rem;color:var(--text-dim);line-height:1.65;margin-bottom:1.25rem">' +
    'Ohne Zustimmung zu den Nutzungsbedingungen und der Datenschutzerklärung kann ' +
    '<?= e(APP_NAME) ?> nicht genutzt werden, da technisch notwendige Cookies ' +
    'für den Login erforderlich sind.</p>' +
    '<a href="<?= e(APP_URL) ?>/impressum.php" ' +
    'style="font-size:.78rem;color:var(--text-dim);text-decoration:underline">Impressum</a>' +
    '</div>';
};
</script>
<?php endif; ?>

<?php if (Auth::check()): ?>
<div id="push-banner" style="display:none;position:fixed;bottom:4.5rem;left:50%;transform:translateX(-50%);
     z-index:900;background:var(--card-bg);border:1px solid var(--accent-border);border-radius:var(--radius);
     padding:.65rem 1rem;box-shadow:0 4px 20px rgba(0,0,0,.4);align-items:center;gap:.75rem;
     font-size:.85rem;white-space:nowrap">
  <span>🔔 Benachrichtigungen aktivieren?</span>
  <button class="btn btn--primary btn--sm" onclick="subscribePush()">Ja, aktivieren</button>
  <button class="btn btn--ghost btn--sm"   onclick="dismissPushBanner()">Nicht jetzt</button>
</div>

<script>
(function () {
  if (!('serviceWorker' in navigator) || !('PushManager' in window)) return;
  if (!window.isSecureContext) return; // kein HTTPS/localhost → kein Service Worker
  if (localStorage.getItem('ww_push_dismissed')) return;

  navigator.serviceWorker.register('/sw.js').then(async function (reg) {
    const sub = await reg.pushManager.getSubscription();
    if (sub) return; // bereits abonniert
    if (Notification.permission === 'denied') return;
    const banner = document.getElementById('push-banner');
    if (banner) banner.style.display = 'flex';
  }).catch(function () {});
})();

function dismissPushBanner() {
  localStorage.setItem('ww_push_dismissed', '1');
  const b = document.getElementById('push-banner');
  if (b) b.style.display = 'none';
}

window.subscribePush = async function () {
  try {
    const reg = await navigator.serviceWorker.ready;
    const r   = await apiFetch(<?= json_encode(API_URL . '/push.php') ?>, {action: 'public_key'});
    if (!r || !r.key) { showToast('Push-Schlüssel fehlt', 'error'); return; }

    const sub = await reg.pushManager.subscribe({
      userVisibleOnly     : true,
      applicationServerKey: _b64uToUint8(r.key),
    });

    const j = sub.toJSON();
    await apiFetch(<?= json_encode(API_URL . '/push.php') ?>, {
      action  : 'subscribe',
      endpoint: j.endpoint,
      p256dh  : (j.keys || {}).p256dh || '',
      auth    : (j.keys || {}).auth    || '',
    });

    const b = document.getElementById('push-banner');
    if (b) b.style.display = 'none';
    localStorage.setItem('ww_push_dismissed', '1');
    showToast('🔔 Benachrichtigungen aktiviert!', 'success');
  } catch (e) {
    showToast('Benachrichtigungen konnten nicht aktiviert werden.', 'error');
  }
};

function _b64uToUint8(b64u) {
  const pad = '='.repeat((4 - b64u.length % 4) % 4);
  const b64 = (b64u + pad).replace(/-/g, '+').replace(/_/g, '/');
  const raw = atob(b64);
  return Uint8Array.from(raw, c => c.charCodeAt(0));
}
</script>
<?php endif; ?>
</body>
</html>
