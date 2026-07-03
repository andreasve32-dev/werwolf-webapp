<?php
// Copyright (c) 2026 Andreas Vetter
require_once dirname(__DIR__) . '/core/bootstrap.php';
if (Auth::check()) redirect('/app/game.php');

// ── AJAX: Spieler-Name (display_name) prüfen ─────────────────
if (($_GET['action'] ?? '') === 'check_display_name') {
    header('Content-Type: application/json');
    $name = trim(post('display_name'));
    if (mb_strlen($name) < 2 || mb_strlen($name) > 30) {
        echo json_encode(['ok' => false, 'error' => '2–30 Zeichen erforderlich.']); exit;
    }
    if (!preg_match('/^[a-zA-Z0-9_\-äöüÄÖÜß ]+$/u', $name)) {
        echo json_encode(['ok' => false, 'error' => 'Ungültige Zeichen (erlaubt: Buchstaben, Zahlen, _ - Leerzeichen).']); exit;
    }
    $exists = Database::queryOne('SELECT id FROM players WHERE display_name=?', [$name]);
    if ($exists) {
        echo json_encode(['ok' => false, 'error' => 'Dieser Spieler-Name ist bereits vergeben.']); exit;
    }
    echo json_encode(['ok' => true]); exit;
}

// ── AJAX: Login-Name prüfen ───────────────────────────────────
if (($_GET['action'] ?? '') === 'check_username') {
    header('Content-Type: application/json');
    $name = trim(post('username'));
    if (mb_strlen($name) < 3 || mb_strlen($name) > 30) {
        echo json_encode(['ok' => false, 'error' => '3–30 Zeichen erforderlich.']); exit;
    }
    if (!preg_match('/^[a-zA-Z0-9_\-äöüÄÖÜß ]+$/u', $name)) {
        echo json_encode(['ok' => false, 'error' => 'Ungültige Zeichen (erlaubt: Buchstaben, Zahlen, _ - Leerzeichen).']); exit;
    }
    $exists = Database::queryOne('SELECT id FROM players WHERE username=?', [$name]);
    if ($exists) {
        echo json_encode(['ok' => false, 'error' => 'Dieser Name ist bereits vergeben.']); exit;
    }
    echo json_encode(['ok' => true]); exit;
}

// ── AJAX: Registrierung durchführen ──────────────────────────
if (($_GET['action'] ?? '') === 'register') {
    header('Content-Type: application/json');
    $username     = trim(post('username'));
    $display_name = trim(post('display_name'));
    $password     = post('password');
    $confirm      = post('confirm');

    if (!$username || !$display_name || !$password || !$confirm) {
        echo json_encode(['ok' => false, 'error' => 'Bitte alle Felder ausfüllen.']); exit;
    }
    if (mb_strlen($username) < 3 || mb_strlen($username) > 30) {
        echo json_encode(['ok' => false, 'error' => 'Login-Name: 3–30 Zeichen.']); exit;
    }
    if (!preg_match('/^[a-zA-Z0-9_\-äöüÄÖÜß ]+$/u', $username)) {
        echo json_encode(['ok' => false, 'error' => 'Login-Name enthält ungültige Zeichen.']); exit;
    }
    if (mb_strlen($display_name) < 2 || mb_strlen($display_name) > 30) {
        echo json_encode(['ok' => false, 'error' => 'Spieler-Name: 2–30 Zeichen.']); exit;
    }
    if (!preg_match('/^[a-zA-Z0-9_\-äöüÄÖÜß ]+$/u', $display_name)) {
        echo json_encode(['ok' => false, 'error' => 'Spieler-Name enthält ungültige Zeichen.']); exit;
    }
    if (mb_strlen($password) < 6) {
        echo json_encode(['ok' => false, 'error' => 'Passwort: mindestens 6 Zeichen.']); exit;
    }
    if ($password !== $confirm) {
        echo json_encode(['ok' => false, 'error' => 'Passwörter stimmen nicht überein.']); exit;
    }
    try {
        Database::execute(
            'INSERT INTO players (username, display_name, password_hash) VALUES (?,?,?)',
            [$username, $display_name, hashPassword($password)]
        );
        echo json_encode(['ok' => true]); exit;
    } catch (PDOException $e) {
        $msg = $e->getCode() === '23000'
            ? 'Dieser Spielername ist bereits vergeben.'
            : 'Registrierung fehlgeschlagen. Bitte erneut versuchen.';
        echo json_encode(['ok' => false, 'error' => $msg]); exit;
    }
}

// ── Seite rendern ─────────────────────────────────────────────
$page = ['title' => 'Registrieren', 'nav' => false, 'body_class' => 'no-tabbar'];
require TEMPLATE_PATH . '/base.php';
?>

<!-- ── Theme-Sheet für Registrierung ───────────────────── -->
<div class="sheet-backdrop" id="theme-backdrop" onclick="closeThemeSheet()"></div>
<div class="theme-sheet" id="theme-sheet">
  <div class="theme-sheet__handle"></div>
  <div class="theme-sheet__grid">
    <?php foreach (THEMES as $key => $theme): ?>
    <a href="?theme=<?= e($key) ?>"
       class="theme-option <?= $activeTheme === $key ? 'active' : '' ?>"
       onclick="switchTheme(event,'<?= e($key) ?>')">
      <span class="theme-option__swatch" style="background:<?= e($theme['preview']) ?>"><?= $theme['icon'] ?></span>
      <span class="theme-option__label"><?= e($theme['label']) ?></span>
      <span class="theme-option__desc"><?= e($theme['desc']) ?></span>
    </a>
    <?php endforeach; ?>
  </div>
</div>

<!-- Globe-Button (oben rechts) -->
<button class="theme-trigger" onclick="openThemeSheet()"
        style="position:fixed;top:.75rem;right:.75rem;z-index:350"
        aria-label="Design wählen">
  <?= THEMES[$activeTheme]['icon'] ?>
</button>

<main class="auth-page">
  <div class="auth-logo animate-in">
    <?php
    $__logoPath = '';
    if (LOGIN_LOGO !== '' && file_exists(ROOT_PATH . '/' . LOGIN_LOGO)) {
        $__logoPath = LOGIN_LOGO;
    } elseif (file_exists(ROOT_PATH . '/assets/icons/logo/login_logo.png')) {
        $__logoPath = 'assets/icons/logo/login_logo.png';
    }
    ?>
    <?php if ($__logoPath !== ''): ?>
      <img src="<?= e(assetUrl($__logoPath)) ?>" alt="<?= e(APP_NAME) ?>" class="auth-logo__img">
    <?php else: ?>
      <span class="auth-logo__icon">🌕</span>
    <?php endif; ?>
    <div class="auth-logo__title"><?= e(APP_NAME) ?></div>
    <div class="auth-logo__sub"><?= e(REGISTER_SUBTITLE) ?></div>
  </div>

  <div class="auth-card animate-in" style="animation-delay:.1s">
    <div class="card card--glow" style="width:100%;max-width:420px">

      <!-- Schrittanzeige -->
      <div class="reg-steps" id="reg-steps">
        <div class="reg-step reg-step--active" data-step="1">
          <div class="reg-step__dot">1</div>
          <div class="reg-step__label">Name</div>
        </div>
        <div class="reg-step" data-step="2">
          <div class="reg-step__dot">2</div>
          <div class="reg-step__label">Passwort</div>
        </div>
        <div class="reg-step" data-step="3">
          <div class="reg-step__dot">3</div>
          <div class="reg-step__label">Fertig</div>
        </div>
      </div>

      <!-- Schritt 1: Account-Daten -->
      <div class="reg-panel reg-panel--active" id="rpanel-1">
        <h2 class="mb-2">Account einrichten</h2>
        <div class="form-group">
          <label class="form-label" for="r-username">Login-Name</label>
          <div class="text-dim text-xs mb-1">Wird nur zum Einloggen verwendet.</div>
          <div style="position:relative">
            <input class="form-input" type="text" id="r-username"
                   placeholder="3–30 Zeichen…"
                   maxlength="30" autocomplete="username" autofocus
                   oninput="onNameInput(this.value)">
            <span id="name-icon" style="position:absolute;right:.75rem;top:50%;transform:translateY(-50%);font-size:1rem;transition:opacity .2s;opacity:0"></span>
          </div>
          <div id="name-hint" class="text-xs mt-1" style="min-height:1.2em"></div>
        </div>
        <div class="form-group">
          <label class="form-label" for="r-display-name">Spieler-Name</label>
          <div class="text-dim text-xs mb-1">Wird im Spiel angezeigt.</div>
          <div style="position:relative">
            <input class="form-input" type="text" id="r-display-name"
                   placeholder="2–30 Zeichen…"
                   maxlength="30" autocomplete="off"
                   oninput="onDisplayNameInput(this.value)">
            <span id="dname-icon" style="position:absolute;right:.75rem;top:50%;transform:translateY(-50%);font-size:1rem;transition:opacity .2s;opacity:0"></span>
          </div>
          <div id="dname-hint" class="text-xs mt-1" style="min-height:1.2em"></div>
        </div>
        <button class="btn btn--primary btn--full btn--lg mt-1" id="btn-name-next"
                onclick="step1Next()" disabled>
          Weiter →
        </button>
        <hr class="divider">
        <p class="text-center text-dim text-sm">
          Schon ein Konto? <a href="<?= APP_URL ?>/index.php">Anmelden</a>
        </p>
      </div>

      <!-- Schritt 2: Passwort -->
      <div class="reg-panel" id="rpanel-2">
        <h2 class="mb-1">Passwort festlegen</h2>
        <p class="text-dim text-sm mb-2">Mindestens 6 Zeichen.</p>
        <div class="form-group">
          <label class="form-label" for="r-password">Passwort</label>
          <input class="form-input" type="password" id="r-password"
                 placeholder="Mindestens 6 Zeichen"
                 autocomplete="new-password"
                 oninput="onPwInput()">
          <!-- Stärke-Balken -->
          <div style="height:4px;background:var(--border);border-radius:99px;margin-top:.4rem;overflow:hidden">
            <div id="pw-strength-bar" style="height:100%;width:0%;border-radius:99px;transition:width .4s,background .4s"></div>
          </div>
          <div id="pw-strength-label" class="text-xs mt-1" style="min-height:1.2em;color:var(--text-dim)"></div>
        </div>
        <div class="form-group">
          <label class="form-label" for="r-confirm">Passwort bestätigen</label>
          <div style="position:relative">
            <input class="form-input" type="password" id="r-confirm"
                   placeholder="Wiederholen"
                   autocomplete="new-password"
                   oninput="onPwInput()">
            <span id="confirm-icon" style="position:absolute;right:.75rem;top:50%;transform:translateY(-50%);font-size:1rem;opacity:0;transition:opacity .2s"></span>
          </div>
        </div>
        <div class="flex gap-sm mt-2">
          <button class="btn btn--ghost" onclick="goRStep(1)">← Zurück</button>
          <button class="btn btn--primary" id="btn-pw-next" onclick="step2Next()" disabled style="margin-left:auto">
            Weiter →
          </button>
        </div>
      </div>

      <!-- Schritt 3: Zusammenfassung + Registrierung -->
      <div class="reg-panel" id="rpanel-3">
        <h2 class="mb-2">Konto erstellen</h2>

        <!-- Zusammenfassung -->
        <div class="panel mb-2" id="reg-summary" style="display:flex;align-items:center;gap:1rem;padding:1rem">
          <span style="font-size:2rem">👤</span>
          <div>
            <div class="text-bright bold" id="summary-display-name" style="font-size:1.1rem"></div>
            <div class="text-dim text-xs mt-1">Login: <span id="summary-name" style="font-family:monospace"></span></div>
            <div class="text-dim text-sm mt-1">Passwort: ••••••</div>
          </div>
        </div>

        <!-- Fortschrittsbalken (erscheint beim Start) -->
        <div id="reg-progress" style="display:none;margin-bottom:1rem">
          <div style="height:10px;background:var(--panel-bg);border-radius:99px;overflow:hidden;border:1px solid var(--border)">
            <div id="reg-progress-fill" style="height:100%;width:0%;background:var(--accent);border-radius:99px;transition:width .6s cubic-bezier(.4,0,.2,1)"></div>
          </div>
          <div id="reg-progress-text" class="text-dim text-xs mt-1 text-right">Vorbereiten…</div>
        </div>

        <!-- Fehler / Erfolg -->
        <div id="reg-error"   class="alert alert--error mb-2"   style="display:none"></div>
        <div id="reg-success" class="alert alert--success mb-2" style="display:none"></div>

        <div class="flex gap-sm" id="reg-buttons">
          <button class="btn btn--ghost" id="btn-reg-back" onclick="goRStep(2)">← Zurück</button>
          <button class="btn btn--primary" id="btn-register" onclick="doRegister()" style="margin-left:auto">
            ✨ Konto erstellen
          </button>
        </div>
        <div id="btn-login-wrap" style="display:none;margin-top:.75rem">
          <a href="<?= APP_URL ?>/index.php" class="btn btn--primary btn--full btn--lg">
            Jetzt anmelden →
          </a>
        </div>
      </div>

    </div>
  </div>
</main>

<style>
.reg-steps{display:flex;gap:0;margin-bottom:1.75rem;position:relative}
.reg-steps::before{content:'';position:absolute;top:14px;left:14px;right:14px;height:2px;background:var(--border);z-index:0}
.reg-step{flex:1;display:flex;flex-direction:column;align-items:center;gap:.3rem;z-index:1}
.reg-step__dot{width:28px;height:28px;border-radius:50%;background:var(--panel-bg);border:2px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:.8rem;font-weight:700;transition:all .4s;color:var(--text-dim)}
.reg-step--active .reg-step__dot{background:var(--accent);border-color:var(--accent);color:#fff}
.reg-step--done   .reg-step__dot{background:var(--alert-success-bg,#14532d);border-color:var(--alert-success-border,#166534);color:var(--alert-success-text,#86efac)}
.reg-step__label{font-size:.7rem;color:var(--text-dim);transition:color .3s}
.reg-step--active .reg-step__label{color:var(--accent);font-weight:600}
.reg-step--done   .reg-step__label{color:var(--alert-success-text,#86efac)}
.reg-panel{display:none;animation:regFadeIn .3s ease}
.reg-panel--active{display:block}
@keyframes regFadeIn{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:none}}
</style>

<?php
$page['inline_js'] = sprintf('const REG_API=%s;', json_encode(APP_URL . '/app/register.php'));
$page['inline_js'] .= <<<'JS'
function openThemeSheet(){
  document.getElementById('theme-sheet').classList.add('open');
  document.getElementById('theme-backdrop').classList.add('open');
}
function closeThemeSheet(){
  document.getElementById('theme-sheet').classList.remove('open');
  document.getElementById('theme-backdrop').classList.remove('open');
}
JS;
$page['inline_js'] .= <<<'JS'

let checkedName        = '';
let checkedDisplayName = '';
let nameOk        = false;
let displayNameOk = false;
let checkTimer        = null;
let displayCheckTimer = null;

function updateStep1Btn() {
  document.getElementById('btn-name-next').disabled = !(nameOk && displayNameOk);
}

function goRStep(n) {
  document.querySelectorAll('.reg-panel').forEach(p => p.classList.remove('reg-panel--active'));
  document.getElementById('rpanel-' + n)?.classList.add('reg-panel--active');
  document.querySelectorAll('.reg-step').forEach(s => {
    const sn = parseInt(s.dataset.step);
    s.classList.remove('reg-step--active','reg-step--done');
    if (sn < n)        s.classList.add('reg-step--done');
    else if (sn === n) s.classList.add('reg-step--active');
  });
  // Fokus
  const inputs = {'1':'r-username','2':'r-password','3':null};
  if (inputs[n]) setTimeout(() => document.getElementById(inputs[n])?.focus(), 100);
}

// ── Schritt 1: Login-Name ────────────────────────────────────
function onNameInput(val) {
  clearTimeout(checkTimer);
  const icon = document.getElementById('name-icon');
  const hint = document.getElementById('name-hint');
  checkedName = '';
  nameOk = false;
  updateStep1Btn();
  icon.textContent = '';
  icon.style.opacity = '0';

  if (!val) { hint.textContent = ''; return; }
  if (val.length < 3) {
    hint.style.color = 'var(--danger-text,#f87171)';
    hint.textContent = 'Mindestens 3 Zeichen.';
    return;
  }

  hint.style.color = 'var(--text-dim)';
  hint.textContent = 'Prüfe Verfügbarkeit…';

  checkTimer = setTimeout(async () => {
    const fd = new FormData();
    fd.append('username', val);
    try {
      const res  = await fetch(REG_API + '?action=check_username', {method:'POST', body: fd});
      const data = await res.json();
      if (data.ok) {
        icon.textContent = '✓';
        icon.style.color  = 'var(--alert-success-text,#86efac)';
        icon.style.opacity = '1';
        hint.style.color  = 'var(--alert-success-text,#86efac)';
        hint.textContent  = 'Name verfügbar!';
        checkedName = val;
        nameOk = true;
      } else {
        icon.textContent   = '✕';
        icon.style.color   = 'var(--danger-text,#f87171)';
        icon.style.opacity = '1';
        hint.style.color   = 'var(--danger-text,#f87171)';
        hint.textContent   = data.error || 'Nicht verfügbar.';
        nameOk = false;
      }
    } catch(e) {
      hint.style.color = 'var(--danger-text,#f87171)';
      hint.textContent = 'Prüfung fehlgeschlagen.';
      nameOk = false;
    }
    updateStep1Btn();
  }, 500);
}

// ── Schritt 1: Spieler-Name ──────────────────────────────────
function onDisplayNameInput(val) {
  clearTimeout(displayCheckTimer);
  const icon = document.getElementById('dname-icon');
  const hint = document.getElementById('dname-hint');
  checkedDisplayName = '';
  displayNameOk = false;
  updateStep1Btn();
  icon.textContent = '';
  icon.style.opacity = '0';

  if (!val) { hint.textContent = ''; return; }
  if (val.length < 2) {
    hint.style.color = 'var(--danger-text,#f87171)';
    hint.textContent = 'Mindestens 2 Zeichen.';
    return;
  }

  hint.style.color = 'var(--text-dim)';
  hint.textContent = 'Prüfe Verfügbarkeit…';

  displayCheckTimer = setTimeout(async () => {
    const fd = new FormData();
    fd.append('display_name', val);
    try {
      const res  = await fetch(REG_API + '?action=check_display_name', {method:'POST', body: fd});
      const data = await res.json();
      if (data.ok) {
        icon.textContent = '✓';
        icon.style.color  = 'var(--alert-success-text,#86efac)';
        icon.style.opacity = '1';
        hint.style.color  = 'var(--alert-success-text,#86efac)';
        hint.textContent  = 'Name verfügbar!';
        checkedDisplayName = val;
        displayNameOk = true;
      } else {
        icon.textContent   = '✕';
        icon.style.color   = 'var(--danger-text,#f87171)';
        icon.style.opacity = '1';
        hint.style.color   = 'var(--danger-text,#f87171)';
        hint.textContent   = data.error || 'Nicht verfügbar.';
        displayNameOk = false;
      }
    } catch(e) {
      hint.style.color = 'var(--danger-text,#f87171)';
      hint.textContent = 'Prüfung fehlgeschlagen.';
      displayNameOk = false;
    }
    updateStep1Btn();
  }, 500);
}

function step1Next() {
  if (!checkedName || !checkedDisplayName) return;
  document.getElementById('summary-display-name').textContent = checkedDisplayName;
  document.getElementById('summary-name').textContent = checkedName;
  goRStep(2);
}

document.getElementById('r-username')?.addEventListener('keydown', e => {
  if (e.key === 'Enter' && !document.getElementById('btn-name-next').disabled) step1Next();
});

// ── Schritt 2: Passwort ──────────────────────────────────────
function pwStrength(pw) {
  let score = 0;
  if (pw.length >= 6)  score++;
  if (pw.length >= 10) score++;
  if (/[A-Z]/.test(pw)) score++;
  if (/[0-9]/.test(pw)) score++;
  if (/[^a-zA-Z0-9]/.test(pw)) score++;
  return score;
}

function onPwInput() {
  const pw      = document.getElementById('r-password').value;
  const confirm = document.getElementById('r-confirm').value;
  const bar     = document.getElementById('pw-strength-bar');
  const lbl     = document.getElementById('pw-strength-label');
  const icon    = document.getElementById('confirm-icon');
  const btn     = document.getElementById('btn-pw-next');

  // Stärke-Balken
  const score = pwStrength(pw);
  const pct   = Math.min(100, score * 22);
  const colors = ['','#ef4444','#f97316','#eab308','#22c55e','#16a34a'];
  const labels = ['','Sehr schwach','Schwach','Mittel','Stark','Sehr stark'];
  bar.style.width      = pct + '%';
  bar.style.background = colors[score] || '#ef4444';
  lbl.textContent      = pw ? (labels[score] || '') : '';
  lbl.style.color      = colors[score] || 'var(--text-dim)';

  // Bestätigung
  if (confirm && pw) {
    const match = pw === confirm;
    icon.textContent   = match ? '✓' : '✕';
    icon.style.color   = match ? 'var(--alert-success-text,#86efac)' : 'var(--danger-text,#f87171)';
    icon.style.opacity = '1';
  } else {
    icon.style.opacity = '0';
  }

  btn.disabled = !(pw.length >= 6 && pw === confirm);
}

function step2Next() {
  goRStep(3);
}

document.getElementById('r-confirm')?.addEventListener('keydown', e => {
  if (e.key === 'Enter' && !document.getElementById('btn-pw-next').disabled) step2Next();
});

// ── Schritt 3: Registrieren ──────────────────────────────────
async function doRegister() {
  const btn      = document.getElementById('btn-register');
  const backBtn  = document.getElementById('btn-reg-back');
  const errEl    = document.getElementById('reg-error');
  const succEl   = document.getElementById('reg-success');
  const progress = document.getElementById('reg-progress');
  const fill     = document.getElementById('reg-progress-fill');
  const progText = document.getElementById('reg-progress-text');

  btn.disabled     = true;
  backBtn.disabled = true;
  errEl.style.display  = 'none';
  succEl.style.display = 'none';

  // Fortschrittsbalken einblenden
  progress.style.display = '';

  // Animiert auf 40% während der Request läuft
  setTimeout(() => { fill.style.width = '40%'; progText.textContent = 'Konto wird erstellt…'; }, 50);

  const fd = new FormData();
  fd.append('username',     checkedName);
  fd.append('display_name', checkedDisplayName);
  fd.append('password', document.getElementById('r-password').value);
  fd.append('confirm',  document.getElementById('r-confirm').value);

  try {
    // Mindest-Laufzeit damit der Balken sichtbar ist
    const [res] = await Promise.all([
      fetch(REG_API + '?action=register', {method:'POST', body: fd}),
      new Promise(r => setTimeout(r, 900))
    ]);
    const data = await res.json();

    if (data.ok) {
      // 40% → 100% mit Animation
      fill.style.width    = '100%';
      progText.textContent = 'Fertig!';

      // Schrittanzeige auf "done"
      document.querySelectorAll('.reg-step').forEach(s => s.classList.add('reg-step--done'));
      document.querySelectorAll('.reg-step').forEach(s => s.classList.remove('reg-step--active'));

      await new Promise(r => setTimeout(r, 700));

      succEl.innerHTML    = `🎉 Willkommen, <strong>${escHtml(checkedDisplayName)}</strong>! Dein Konto wurde erstellt.`;
      succEl.style.display = '';
      document.getElementById('btn-register').style.display = 'none';
      backBtn.style.display = 'none';
      document.getElementById('btn-login-wrap').style.display = '';
    } else {
      fill.style.width    = '0%';
      progress.style.display = 'none';
      errEl.textContent   = data.error || 'Registrierung fehlgeschlagen.';
      errEl.style.display = '';
      btn.disabled     = false;
      backBtn.disabled = false;
    }
  } catch(e) {
    fill.style.width    = '0%';
    progress.style.display = 'none';
    errEl.textContent   = 'Netzwerkfehler. Bitte erneut versuchen.';
    errEl.style.display = '';
    btn.disabled     = false;
    backBtn.disabled = false;
  }
}

JS;
require TEMPLATE_PATH . '/base_end.php';
?>
