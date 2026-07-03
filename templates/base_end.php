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
  <div class="legal-footer__links">
    <a href="<?= APP_URL ?>/app/impressum.php">Impressum</a>
    <span class="legal-footer__sep">·</span>
    <a href="<?= APP_URL ?>/app/datenschutz.php">Datenschutz</a>
    <span class="legal-footer__sep">·</span>
    <a href="<?= APP_URL ?>/app/nutzungsbedingungen.php">Nutzungsbedingungen</a>
  </div>
  <div class="legal-footer__copy">KI-generierte Charakterbilder, inspiriert von FINAL FANTASY XIV © SQUARE ENIX</div>
</footer>

<?php $__bepPlayer = Auth::check() ? Auth::player() : null; ?>
<?php if ($__bepPlayer && !empty($__bepPlayer['is_admin'])): ?>
<script>
(function() {
  const _msgApi     = <?= json_encode(API_URL . '/messages.php') ?>;
  let   _lastPending = 0;
  let   _firstPoll   = true; // kein Toast beim Seitenstart

  // Badge-Poll im eingestellten Ladeintervall (liveBlocks: Tab-Pause + Overlap-Guard)
  liveBlocks({
    fetcher: () => apiFetch(_msgApi, {action:'pending_count'}),
    onData: (r) => {
      if (r.pending === undefined) return;
      // nav-msg-badge (versteckt, nur Zustandsträger) + admin-msg-badge (sichtbar auf index.php)
      [document.getElementById('nav-msg-badge'), document.getElementById('admin-msg-badge')]
        .forEach(badge => {
          if (!badge) return;
          if (r.pending > 0) { badge.textContent = r.pending; badge.style.display = 'inline-block'; }
          else               { badge.style.display = 'none'; }
        });
      // Hinweis-Link auf game.php (existiert nur dort)
      const hint  = document.getElementById('admin-pending-hint');
      const count = document.getElementById('admin-pending-count');
      if (hint)  hint.style.display = r.pending > 0 ? 'flex' : 'none';
      if (count) count.textContent  = r.pending;
      if (!_firstPoll && r.pending > _lastPending) {
        showToast('✉️ Neue Spielerfrage eingegangen!', 'info', 5000);
      }
      _firstPoll   = false;
      _lastPending = r.pending;
    },
  }).start();
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
    '<a href="<?= e(APP_URL) ?>/app/impressum.php" ' +
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

  navigator.serviceWorker.register('<?= ASSETS_URL ?>/js/sw.js', {scope: '/'}).then(async function (reg) {
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
<!-- ── Portrait-Sperre (Handy im Querformat) ─────────────────── -->
<div id="portrait-overlay">
  <div>
    <div style="font-size:3rem;margin-bottom:.75rem">📱</div>
    <div style="font-family:var(--font-display);font-size:1.1rem;color:var(--text-bright);margin-bottom:.5rem">
      Bitte Handy drehen
    </div>
    <p style="color:var(--text-dim);font-size:.85rem;margin:0">
      Diese App ist für den Hochformat-Modus optimiert.
    </p>
  </div>
</div>
<style>
#portrait-overlay {
  display: none;
  position: fixed; inset: 0; z-index: 99999;
  background: var(--bg, #0d0d14);
  align-items: center; justify-content: center; text-align: center;
  padding: 2rem;
}
@media screen and (orientation: landscape) and (max-height: 500px) and (hover: none) and (pointer: coarse) {
  #portrait-overlay { display: flex; }
}
</style>
</body>
</html>
