<?php
// Copyright (c) 2026 Andreas Vetter
/**
 * ============================================================
 *  WERWOLF — templates/base.php
 * ============================================================
 *  Haupt-HTML-Wrapper. Jede Seite ruft renderStart() und
 *  renderEnd() auf, oder nutzt startPage() / endPage().
 *
 *  Verwendung:
 *    $page = ['title' => 'Login', 'body_class' => 'auth-page'];
 *    require TEMPLATE_PATH . '/base.php';
 *    // ... HTML-Inhalt ...
 *    require TEMPLATE_PATH . '/base_end.php';
 * ============================================================
 */

header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Theme ermitteln
$activeTheme     = getActiveTheme();
$themeData       = THEMES[$activeTheme];
$themeBodyClass  = $themeData['body_class'];

// Seitentitel
$pageTitle = ($page['title'] ?? 'Spiel') . ' — ' . APP_NAME;

// Soll die Navigation angezeigt werden?
$showNav = $page['nav'] ?? true;
?>
<!DOCTYPE html>
<html lang="de" data-theme="<?= e($activeTheme) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, viewport-fit=cover">
  <meta name="description" content="Werwolf Spiel — Webbasiert">
  <meta name="theme-color" content="<?= e($themeData['preview']) ?>">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <title><?= e($pageTitle) ?></title>

  <?php if (MINI_LOGO !== '' && file_exists(ROOT_PATH . '/' . MINI_LOGO)): ?>
  <link rel="icon" type="image/png" href="<?= e(assetUrl(MINI_LOGO)) ?>">
  <?php endif; ?>

  <!-- Tag/Nacht-Modus sofort setzen (vor CSS-Render, verhindert Flash) -->
  <script>
  (function(){
    var dn = localStorage.getItem('ww_daynight');
    if (dn) document.documentElement.setAttribute('data-daynight', dn);
  })();
  </script>

  <!-- Theme-CSS zuerst (definiert CSS-Variablen) -->
  <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/themes/<?= e($themeData['css_file']) ?>?v=<?= filemtime(ROOT_PATH . '/assets/css/themes/' . $themeData['css_file']) ?>">
  <!-- Dann Basis-CSS (nutzt die Variablen) -->
  <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/app.css?v=<?= filemtime(ROOT_PATH . '/assets/css/app.css') ?>">

  <?php if (!empty($page['extra_css'])): ?>
    <?php foreach ((array)$page['extra_css'] as $css): ?>
      <link rel="stylesheet" href="<?= e($css) ?>">
    <?php endforeach; ?>
  <?php endif; ?>
</head>
<body class="<?= e($themeBodyClass) ?> <?= e($page['body_class'] ?? '') ?>">

<div id="toast-container"></div>

<?php if (empty($page['skip_consent'])): ?>
<div id="consent-overlay" style="
  display:none; position:fixed; inset:0; z-index:9999;
  background:rgba(0,0,0,.82); backdrop-filter:blur(4px);
  align-items:center; justify-content:center; padding:1.25rem">
  <div style="
    background:var(--card-bg); border:1px solid var(--accent-border);
    border-radius:calc(var(--radius)*1.5); box-shadow:0 8px 40px rgba(0,0,0,.6);
    max-width:420px; width:100%; padding:2rem 1.75rem; text-align:center">

    <div style="font-size:2.6rem;line-height:1;margin-bottom:.6rem">🐺</div>
    <h2 style="font-family:var(--font-display);font-size:1.25rem;color:var(--text-bright);margin-bottom:.4rem">
      Bevor du weitermachst
    </h2>
    <p style="font-size:.82rem;color:var(--text-dim);line-height:1.65;margin-bottom:1.4rem">
      <?= e(APP_NAME) ?> verwendet technisch notwendige Cookies und lokalen Browserspeicher,
      um deinen Login und deine Einstellungen zu speichern. Es werden keine Tracking-
      oder Werbe-Cookies eingesetzt und keine Daten an Dritte verkauft.
    </p>

    <div style="display:flex;gap:.5rem;justify-content:center;flex-wrap:wrap;margin-bottom:1.1rem">
      <a href="<?= APP_URL ?>/datenschutz.php"
         style="font-size:.78rem;color:var(--accent);text-decoration:underline">
        Datenschutzerklärung
      </a>
      <span style="color:var(--text-dim);font-size:.78rem">·</span>
      <a href="<?= APP_URL ?>/nutzungsbedingungen.php"
         style="font-size:.78rem;color:var(--accent);text-decoration:underline">
        Nutzungsbedingungen
      </a>
    </div>

    <button class="btn btn--primary" style="width:100%;margin-bottom:.6rem;font-size:.95rem"
            onclick="acceptConsent()">
      Akzeptieren &amp; fortfahren
    </button>
    <button onclick="declineConsent()"
            style="background:none;border:none;color:var(--text-dim);font-size:.78rem;
                   cursor:pointer;text-decoration:underline;padding:.2rem">
      Ablehnen
    </button>
  </div>
</div>
<script>
(function () {
  if (localStorage.getItem('ww_consent') !== '1') {
    var o = document.getElementById('consent-overlay');
    if (o) o.style.display = 'flex';
  }
})();
</script>
<?php endif; ?>

<?php if ($showNav && Auth::check()): ?>
  <?php require TEMPLATE_PATH . '/nav.php'; ?>
<?php endif; ?>
