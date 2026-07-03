<?php
// Copyright (c) 2026 Andreas Vetter
require_once __DIR__ . '/core/bootstrap.php';

// Bereits eingeloggt → weiterleiten
if (Auth::check()) redirect('/app/game.php');

$error = '';

if (isPost()) {
    $username = trim(post('username'));
    $password = post('password');

    if (!$username || !$password) {
        $error = 'Bitte alle Felder ausfüllen.';
    } else {
        $player = Database::queryOne(
            'SELECT id, username, display_name, password_hash, is_admin FROM players WHERE username = ?',
            [$username]
        );
        if ($player && verifyPassword($password, $player['password_hash'])) {
            Auth::login($player);
            redirect('/app/game.php');
        } else {
            $error = 'Ungültiger Spielername oder Passwort.';
        }
    }
}

$page = ['title' => 'Anmelden', 'nav' => false, 'body_class' => 'no-tabbar'];
require TEMPLATE_PATH . '/base.php';
?>

<main class="auth-page">

  <!-- Logo -->
  <div class="auth-logo animate-in">
    <?php
    // DB-Pfad prüfen, Fallback auf festen Speicherort
    $__logoPath = '';
    if (LOGIN_LOGO !== '' && file_exists(ROOT_PATH . '/' . LOGIN_LOGO)) {
        $__logoPath = LOGIN_LOGO;
    } elseif (file_exists(ROOT_PATH . '/assets/icons/logo/login_logo.png')) {
        $__logoPath = 'assets/icons/logo/login_logo.png';
    }
    ?>
    <?php if ($__logoPath !== ''): ?>
      <img src="<?= e(assetUrl($__logoPath)) ?>" alt="<?= e(APP_NAME) ?>"
           class="auth-logo__img">
    <?php else: ?>
      <span class="auth-logo__icon pulse">🐺</span>
    <?php endif; ?>
    <div class="auth-logo__title"><?= APP_NAME ?></div>
    <div class="auth-logo__sub"><?= e(LOGIN_SUBTITLE) ?></div>
  </div>

  <!-- Card -->
  <div class="auth-card animate-in" style="animation-delay:.1s">
    <div class="card card--glow">
      <h2 class="mb-2"><?= e(LOGIN_TITLE) ?></h2>

      <?php if ($error): ?>
      <div class="alert alert--error"><?= e($error) ?></div>
      <?php endif; ?>

      <form method="POST">
        <div class="form-group">
          <label class="form-label" for="username">Login-Name</label>
          <input class="form-input" type="text" id="username" name="username"
                 placeholder="Dein Login-Name"
                 value="<?= e(post('username')) ?>"
                 autocomplete="username" autofocus required>
        </div>
        <div class="form-group">
          <label class="form-label" for="password">Passwort</label>
          <input class="form-input" type="password" id="password" name="password"
                 placeholder="••••••••"
                 autocomplete="current-password" required>
        </div>

        <!-- ── Theme-Auswahl (Swatch-Leiste) ── -->
        <div class="auth-theme-row">
          <span class="auth-theme-row__label">Design</span>
          <div class="auth-theme-row__swatches">
            <?php foreach (THEMES as $key => $theme): ?>
            <a href="?theme=<?= e($key) ?>"
               class="auth-swatch <?= $activeTheme === $key ? 'auth-swatch--active' : '' ?>"
               style="background:<?= e($theme['preview']) ?>"
               title="<?= e($theme['label']) ?> — <?= e($theme['desc']) ?>"
               onclick="switchTheme(event,'<?= e($key) ?>')">
              <?= $theme['icon'] ?>
            </a>
            <?php endforeach; ?>
          </div>
        </div>

        <button class="btn btn--primary btn--full btn--lg mt-2" type="submit">
          🔑 Anmelden
        </button>
      </form>

      <hr class="divider">
      <p class="text-center text-dim text-sm">
        Noch kein Konto? <a href="<?= APP_URL ?>/app/register.php">Registrieren</a>
      </p>
    </div>
  </div>
</main>

<?php
$page['inline_js'] = <<<JS
(function(){
  const c = document.cookie.split(';').find(s=>s.trim().startsWith('ww_player='));
  if(c){try{localStorage.setItem('ww_player',decodeURIComponent(c.split('=').slice(1).join('=')));}catch(e){}}
})();
JS;
require TEMPLATE_PATH . '/base_end.php';
?>
