<?php
// Copyright (c) 2026 Andreas Vetter
/**
 * Setup wizard — DB init with live SSE progress.
 * Uses its own session (ww_setup), separate from the app session.
 * Protected by SETUP_PASSWORD in config/config.php.
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/themes.php';

// Vor dem Start der Setup-Session prüfen ob eine App-Session existiert.
// Diese Info wird später im JS genutzt um nach erfolgreichem Setup
// sauber auszuloggen (oder eben nicht, wenn niemand eingeloggt war).
$_hadAppSession = !empty($_COOKIE[SESSION_NAME]);

function splitSqlStatements(string $sql): array {
    $lines = array_filter(explode("\n", $sql), fn($l) => !preg_match('/^\s*--/', $l));
    return array_values(array_filter(array_map('trim', explode(';', implode("\n", $lines))), fn($s) => $s !== ''));
}

function describeSql(string $sql): string {
    if (preg_match('/^DROP TABLE IF EXISTS\s+`?(\w+)`?/i', $sql, $m))
        return 'Alte Tabelle "' . $m[1] . '" entfernt.';
    if (preg_match('/^CREATE TABLE\s+`?(\w+)`?/i', $sql, $m))
        return 'Tabelle "' . $m[1] . '" erstellt.';
    if (preg_match('/^INSERT INTO\s+`?(\w+)`?/i', $sql, $m)) {
        $rows = max(1, preg_match_all('/\)\s*,\s*\(/s', substr($sql, (int)stripos($sql, 'VALUES'))) + 1);
        return 'Daten in "' . $m[1] . '" eingefügt' . ($rows > 1 ? " ({$rows} Einträge)." : '.');
    }
    return 'SQL-Anweisung ausgefuehrt.';
}

// Isolated setup session — must not share the app session cookie
session_name('ww_setup');
session_set_cookie_params(['lifetime' => 1800, 'httponly' => true, 'samesite' => 'Strict']);
session_start();

$action = $_GET['action'] ?? '';

// POST ?action=auth
if ($action === 'auth') {
    header('Content-Type: application/json');
    $pw = $_POST['pw'] ?? '';
    if (SETUP_PASSWORD === '' || $pw === SETUP_PASSWORD) {
        $_SESSION['setup_auth'] = true;
        $_SESSION['setup_ts']   = time();
        echo json_encode(['ok' => true]);
    } else {
        echo json_encode(['ok' => false, 'error' => 'Passwort falsch.']);
    }
    exit;
}

// POST ?action=test_db
if ($action === 'test_db') {
    header('Content-Type: application/json');
    if (empty($_SESSION['setup_auth'])) {
        echo json_encode(['ok' => false, 'error' => 'Nicht autorisiert.']); exit;
    }
    try {
        $dsn = sprintf('mysql:host=%s;port=%s;charset=%s', DB_HOST, DB_PORT, DB_CHARSET);
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $ver = $pdo->query('SELECT VERSION()')->fetchColumn();
        $stmtDb = $pdo->prepare("SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=?");
        $stmtDb->execute([DB_NAME]);
        $dbExists = (bool)$stmtDb->fetchColumn();
        $schemaOk = is_file(dirname(__DIR__) . '/db/schema.sql');
        echo json_encode([
            'ok'        => true,
            'version'   => $ver,
            'host'      => DB_HOST . ':' . DB_PORT,
            'db'        => DB_NAME,
            'db_exists' => $dbExists,
            'user'      => DB_USER,
            'schema_ok' => $schemaOk,
        ]);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// POST ?action=save_admin
if ($action === 'save_admin') {
    header('Content-Type: application/json');
    if (empty($_SESSION['setup_auth'])) {
        echo json_encode(['ok' => false, 'error' => 'Nicht autorisiert.']); exit;
    }
    $username     = trim($_POST['username']     ?? '');
    $display_name = trim($_POST['display_name'] ?? '');
    $password     = $_POST['password'] ?? '';
    $confirm      = $_POST['confirm']  ?? '';
    if (mb_strlen($username) < 3 || mb_strlen($username) > 30) {
        echo json_encode(['ok' => false, 'error' => 'Login-Name: 3–30 Zeichen erforderlich.']); exit;
    }
    if (!preg_match('/^[a-zA-Z0-9_\-äöüÄÖÜß ]+$/u', $username)) {
        echo json_encode(['ok' => false, 'error' => 'Login-Name: ungültige Zeichen.']); exit;
    }
    if (mb_strlen($display_name) < 2 || mb_strlen($display_name) > 30) {
        echo json_encode(['ok' => false, 'error' => 'Spieler-Name: 2–30 Zeichen erforderlich.']); exit;
    }
    if (!preg_match('/^[a-zA-Z0-9_\-äöüÄÖÜß ]+$/u', $display_name)) {
        echo json_encode(['ok' => false, 'error' => 'Spieler-Name: ungültige Zeichen.']); exit;
    }
    if (mb_strlen($password) < 6) {
        echo json_encode(['ok' => false, 'error' => 'Passwort: mindestens 6 Zeichen.']); exit;
    }
    if ($password !== $confirm) {
        echo json_encode(['ok' => false, 'error' => 'Passwörter stimmen nicht überein.']); exit;
    }
    $_SESSION['admin_username']     = $username;
    $_SESSION['admin_display_name'] = $display_name;
    $_SESSION['admin_password']     = $password;
    echo json_encode(['ok' => true]); exit;
}

// GET ?action=run  →  SSE stream for real-time progress
if ($action === 'run') {
    if (empty($_SESSION['setup_auth']) || (time() - ($_SESSION['setup_ts'] ?? 0)) > 1800) {
        http_response_code(403); exit;
    }
    session_write_close();

    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');
    header('X-Accel-Buffering: no');
    @set_time_limit(180);
    if (ob_get_level()) ob_end_clean();

    function sse(string $type, string $text, int $pct): void {
        echo 'data: ' . json_encode(['type' => $type, 'text' => $text, 'pct' => $pct]) . "\n\n";
        if (ob_get_level()) ob_flush();
        flush();
    }

    usleep(300000);

    // 1. connect
    try {
        $dsn = sprintf('mysql:host=%s;port=%s;charset=%s', DB_HOST, DB_PORT, DB_CHARSET);
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        sse('ok', 'MySQL-Verbindung zu ' . DB_HOST . ':' . DB_PORT . ' hergestellt.', 8);
        usleep(500000);
    } catch (Throwable $e) {
        sse('error', 'Verbindung fehlgeschlagen: ' . $e->getMessage(), 5);
        sse('done', 'error', 5); exit;
    }

    // 2. create DB (if not exists)
    try {
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `" . DB_NAME . "`");
        sse('ok', 'Datenbank "' . DB_NAME . '" bereit.', 14);
        usleep(500000);
    } catch (Throwable $e) {
        sse('error', 'Datenbank-Fehler: ' . $e->getMessage(), 10);
        sse('done', 'error', 10); exit;
    }

    // 3. load schema
    $schemaPath = dirname(__DIR__) . '/db/schema.sql';
    if (!is_file($schemaPath)) {
        sse('error', 'db/schema.sql nicht gefunden!', 14);
        sse('done', 'error', 14); exit;
    }
    $stmts = splitSqlStatements(file_get_contents($schemaPath));
    $total  = count($stmts);
    sse('ok', "Schema geladen — {$total} SQL-Anweisungen werden ausgeführt.", 20);
    usleep(600000);

    // 4. execute statements
    try {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($stmts as $i => $stmt) {
            $pdo->exec($stmt);
            $pct = 20 + (int)(($i + 1) / $total * 75);
            sse('ok', describeSql($stmt), $pct);
            usleep(200000); // artificial delay keeps progress bar visible
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        usleep(400000);

        // create the admin account with the credentials chosen in step 3
        $adminUser    = $_SESSION['admin_username']     ?? 'admin';
        $adminDisplay = $_SESSION['admin_display_name'] ?? $adminUser;
        $adminPass    = $_SESSION['admin_password']     ?? 'password';
        $adminHash    = password_hash($adminPass, PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO players (username, display_name, password_hash, is_admin) VALUES (?, ?, ?, 1)")
            ->execute([$adminUser, $adminDisplay, $adminHash]);
        unset($_SESSION['admin_username'], $_SESSION['admin_display_name'], $_SESSION['admin_password']);
        sse('ok', 'Admin-Konto "' . $adminUser . '" (Spieler-Name: "' . $adminDisplay . '") eingerichtet.', 98);
        usleep(500000);

        sse('success', 'Datenbank vollständig eingerichtet! Login: ' . $adminUser, 100);
        usleep(300000);
        sse('done', 'success|' . $adminUser, 100);
    } catch (Throwable $e) {
        sse('error', 'SQL-Fehler: ' . $e->getMessage(), -1);
        sse('done', 'error', -1);
    }
    exit;
}

$activeTheme = getActiveTheme();
$themeData   = THEMES[$activeTheme];
$noPw        = SETUP_PASSWORD === '';
?>
<!DOCTYPE html>
<html lang="de" data-theme="<?= htmlspecialchars($activeTheme, ENT_QUOTES) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>Werwolf — Setup-Assistent</title>
  <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/themes/<?= htmlspecialchars($themeData['css_file'], ENT_QUOTES) ?>">
  <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/app.css">
  <style>
    /* ── Wizard ─────────────────────────────────────── */
    .wizard-wrap{width:100%;max-width:580px;margin:0 auto}
    .wizard-steps{display:flex;gap:0;margin-bottom:2rem;position:relative}
    .wizard-steps::before{content:'';position:absolute;top:14px;left:14px;right:14px;height:2px;background:var(--border);z-index:0}
    .wizard-step{flex:1;display:flex;flex-direction:column;align-items:center;gap:.35rem;z-index:1;cursor:default}
    .wizard-step__dot{width:28px;height:28px;border-radius:50%;background:var(--panel-bg);border:2px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:.8rem;font-weight:700;transition:all .4s;color:var(--text-dim)}
    .wizard-step--active .wizard-step__dot{background:var(--accent);border-color:var(--accent);color:#fff}
    .wizard-step--done .wizard-step__dot{background:var(--alert-success-bg,#14532d);border-color:var(--alert-success-border,#166534);color:var(--alert-success-text,#86efac)}
    .wizard-step--error .wizard-step__dot{background:var(--alert-error-bg);border-color:var(--alert-error-border);color:var(--alert-error-text)}
    .wizard-step__label{font-size:.7rem;color:var(--text-dim);text-align:center;transition:color .3s}
    .wizard-step--active .wizard-step__label{color:var(--accent);font-weight:600}
    .wizard-step--done .wizard-step__label{color:var(--alert-success-text,#86efac)}
    /* ── Panels ─────────────────────────────────────── */
    .setup-panel{display:none;animation:fadeIn .3s ease}
    .setup-panel--active{display:block}
    @keyframes fadeIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}
    /* ── Progress ───────────────────────────────────── */
    .progress-wrap{margin:1.2rem 0}
    .progress-bar-track{height:10px;background:var(--panel-bg);border-radius:99px;overflow:hidden;border:1px solid var(--border)}
    .progress-bar-fill{height:100%;width:0%;background:var(--accent);border-radius:99px;transition:width .6s cubic-bezier(.4,0,.2,1)}
    .progress-pct{text-align:right;font-size:.8rem;color:var(--text-dim);margin-top:.3rem;font-family:var(--font-display)}
    /* ── Log ────────────────────────────────────────── */
    .setup-log{display:flex;flex-direction:column;gap:.35rem;max-height:300px;overflow-y:auto;margin:1rem 0}
    .setup-log__row{display:flex;gap:.6rem;padding:.45rem .7rem;border-radius:var(--radius);background:var(--panel-bg);border:1px solid var(--border);font-size:.84rem;animation:fadeIn .25s ease}
    .setup-log__icon{flex-shrink:0;font-weight:700}
    .setup-log__row--ok   .setup-log__icon{color:var(--alert-success-text,#86efac)}
    .setup-log__row--error{background:var(--alert-error-bg);border-color:var(--alert-error-border);color:var(--alert-error-text)}
    .setup-log__row--success{background:var(--alert-success-bg);border-color:var(--alert-success-border);color:var(--alert-success-text);font-weight:600}
    /* ── Info-Grid ──────────────────────────────────── */
    .info-grid{display:flex;flex-direction:column;gap:.5rem;margin:1rem 0}
    .info-row{display:flex;gap:.75rem;align-items:center;padding:.5rem .75rem;border-radius:var(--radius);background:var(--panel-bg);border:1px solid var(--border);font-size:.88rem}
    .info-row__icon{font-size:1rem;flex-shrink:0}
    .info-row__label{color:var(--text-dim);min-width:120px;font-size:.8rem}
    .info-row__val{color:var(--text-bright);font-family:monospace}
    .info-row--ok{border-color:var(--alert-success-border,#166534)}
    .info-row--error{border-color:var(--alert-error-border);background:var(--alert-error-bg)}
  </style>
</head>
<body class="<?= htmlspecialchars($themeData['body_class'], ENT_QUOTES) ?> no-tabbar">
<main class="auth-page">

  <div class="auth-logo animate-in">
    <span class="auth-logo__icon">🛠️</span>
    <div class="auth-logo__title">SETUP-ASSISTENT</div>
    <div class="auth-logo__sub">Datenbank einrichten</div>
  </div>

  <div class="wizard-wrap animate-in" style="animation-delay:.1s">

    <div class="wizard-steps" id="wizard-steps">
      <div class="wizard-step wizard-step--active" data-step="1">
        <div class="wizard-step__dot">1</div>
        <div class="wizard-step__label">Zugang</div>
      </div>
      <div class="wizard-step" data-step="2">
        <div class="wizard-step__dot">2</div>
        <div class="wizard-step__label">Verbindung</div>
      </div>
      <div class="wizard-step" data-step="3">
        <div class="wizard-step__dot">3</div>
        <div class="wizard-step__label">Admin</div>
      </div>
      <div class="wizard-step" data-step="4">
        <div class="wizard-step__dot">4</div>
        <div class="wizard-step__label">Bestätigung</div>
      </div>
      <div class="wizard-step" data-step="5">
        <div class="wizard-step__dot">5</div>
        <div class="wizard-step__label">Einrichtung</div>
      </div>
    </div>

    <!-- step 1: password -->
    <div class="card card--glow setup-panel setup-panel--active" id="panel-1">
      <div class="section-title">🔐 Setup-Zugang</div>
      <?php if ($noPw): ?>
      <div class="alert alert--warn mb-2">
        ⚠️ Kein Setup-Passwort gesetzt (<code>SETUP_PASSWORD</code> in <code>config/config.php</code> ist leer).<br>
        Die Setup-Seite ist ohne Passwort öffentlich zugänglich — bitte unbedingt ein Passwort setzen!
      </div>
      <?php else: ?>
      <p class="text-dim text-sm mb-2">
        Gib das Setup-Passwort ein, das in <code>config/config.php</code> unter <code>SETUP_PASSWORD</code> hinterlegt ist.
      </p>
      <?php endif; ?>
      <div id="pw-error" class="alert alert--error mb-2" style="display:none"></div>
      <div class="form-group">
        <label class="form-label" for="setup-pw">
          <?= $noPw ? 'Kein Passwort erforderlich — direkt weiter' : 'Setup-Passwort' ?>
        </label>
        <input class="form-input" type="password" id="setup-pw"
               placeholder="<?= $noPw ? '(kein Passwort gesetzt)' : 'Setup-Passwort eingeben…' ?>"
               <?= $noPw ? 'disabled' : '' ?> autocomplete="off">
      </div>
      <button class="btn btn--primary btn--full btn--lg mt-1" onclick="step1Next()">
        Weiter →
      </button>
    </div>

    <!-- step 2: DB connection -->
    <div class="card setup-panel" id="panel-2">
      <div class="section-title">🔌 Datenbankverbindung prüfen</div>
      <div id="db-loading" class="flex-center" style="padding:2rem;gap:.75rem">
        <div class="spinner"></div>
        <span class="text-dim">Verbinde mit <?= htmlspecialchars(DB_HOST, ENT_QUOTES) ?>…</span>
      </div>
      <div id="db-result" style="display:none">
        <div class="info-grid" id="db-info"></div>
        <div id="db-error" class="alert alert--error" style="display:none"></div>
      </div>
      <div class="flex gap-sm mt-2">
        <button class="btn btn--ghost" onclick="goStep(1)">← Zurück</button>
        <button class="btn btn--primary" id="btn-step2-next" onclick="step2Next()" disabled style="margin-left:auto">
          Weiter →
        </button>
      </div>
    </div>

    <!-- step 3: admin account -->
    <div class="card setup-panel" id="panel-3">
      <div class="section-title">👤 Admin-Konto einrichten</div>
      <p class="text-dim text-sm mb-2">
        Wähle Login-Namen, Spieler-Namen und Passwort für den Administrator-Account.
      </p>
      <div id="admin-error" class="alert alert--error mb-2" style="display:none"></div>
      <div class="form-group">
        <label class="form-label" for="admin-name">Login-Name</label>
        <div class="text-dim text-xs mb-1">Wird nur zum Einloggen verwendet.</div>
        <input class="form-input" type="text" id="admin-name"
               placeholder="3–30 Zeichen" maxlength="30" autocomplete="username"
               oninput="onAdminInput()">
      </div>
      <div class="form-group">
        <label class="form-label" for="admin-display-name">Spieler-Name</label>
        <div class="text-dim text-xs mb-1">Wird im Spiel angezeigt.</div>
        <input class="form-input" type="text" id="admin-display-name"
               placeholder="2–30 Zeichen" maxlength="30" autocomplete="off"
               oninput="onAdminInput()">
      </div>
      <div class="form-group">
        <label class="form-label" for="admin-pw">Passwort</label>
        <input class="form-input" type="password" id="admin-pw"
               placeholder="Mindestens 6 Zeichen" autocomplete="new-password"
               oninput="onAdminInput()">
        <div style="height:4px;background:var(--border);border-radius:99px;margin-top:.4rem;overflow:hidden">
          <div id="admin-pw-bar" style="height:100%;width:0%;border-radius:99px;transition:width .4s,background .4s"></div>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label" for="admin-pw2">Passwort bestätigen</label>
        <input class="form-input" type="password" id="admin-pw2"
               placeholder="Wiederholen" autocomplete="new-password"
               oninput="onAdminInput()">
      </div>
      <div class="flex gap-sm mt-2">
        <button class="btn btn--ghost" onclick="goStep(2)">← Zurück</button>
        <button class="btn btn--primary" id="btn-admin-next" onclick="step3Next()" disabled style="margin-left:auto">
          Weiter →
        </button>
      </div>
    </div>

    <!-- step 4: confirm -->
    <div class="card setup-panel" id="panel-4">
      <div class="section-title">⚠️ Bestätigung erforderlich</div>
      <div class="alert alert--warn mb-2">
        <strong>Achtung:</strong> Alle vorhandenen Tabellen in der Datenbank
        <strong><?= htmlspecialchars(DB_NAME, ENT_QUOTES) ?></strong> werden gelöscht und neu angelegt.
        <br><br>
        <strong>Spieler, Spielstände, Rollen, Abstimmungen — alles geht verloren!</strong>
        <br><br>
        Admin-Konto: <strong id="confirm-admin-name"></strong> (Spieler-Name: <strong id="confirm-admin-display"></strong>)
      </div>
      <div class="form-group">
        <label class="form-label" for="confirm-text">
          Zur Bestätigung <strong>LÖSCHEN</strong> eingeben:
        </label>
        <input class="form-input" type="text" id="confirm-text"
               placeholder="LÖSCHEN" autocomplete="off"
               oninput="document.getElementById('btn-confirm').disabled = this.value !== 'LÖSCHEN'">
      </div>
      <div class="flex gap-sm mt-2">
        <button class="btn btn--ghost" onclick="goStep(3)">← Zurück</button>
        <button class="btn btn--danger" id="btn-confirm" onclick="step4Next()" disabled style="margin-left:auto">
          🗑️ Setup starten
        </button>
      </div>
    </div>

    <!-- step 5: progress -->
    <div class="card setup-panel" id="panel-5">
      <div class="section-title">⚙️ Datenbank wird eingerichtet…</div>
      <div class="progress-wrap">
        <div class="progress-bar-track">
          <div class="progress-bar-fill" id="progress-fill"></div>
        </div>
        <div class="progress-pct" id="progress-pct">0 %</div>
      </div>
      <div class="setup-log" id="setup-log"></div>
      <div id="setup-done" style="display:none">
        <div id="done-msg"></div>
        <div class="flex gap-sm mt-2">
          <a href="<?= APP_URL ?>/admin/setup.php" class="btn btn--ghost" id="btn-retry" style="display:none">
            ↻ Erneut versuchen
          </a>
          <a href="<?= APP_URL ?>/<?= $_hadAppSession ? 'logout.php' : 'index.php' ?>" class="btn btn--primary" id="btn-login" style="display:none">
            Zur Anmeldung →
          </a>
        </div>
      </div>
    </div>

  </div><!-- /.wizard-wrap -->
</main>

<script>
const API = '<?= APP_URL ?>/admin/setup.php';
let currentStep = 1;

function goStep(n) {
  document.querySelectorAll('.setup-panel').forEach(p => p.classList.remove('setup-panel--active'));
  document.getElementById('panel-' + n)?.classList.add('setup-panel--active');
  document.querySelectorAll('.wizard-step').forEach(s => {
    const sn = parseInt(s.dataset.step);
    s.classList.remove('wizard-step--active','wizard-step--done','wizard-step--error');
    if (sn < n)       s.classList.add('wizard-step--done');
    else if (sn === n) s.classList.add('wizard-step--active');
  });
  currentStep = n;
}

// step 1 – password
async function step1Next() {
  const pw = document.getElementById('setup-pw').value;
  const errEl = document.getElementById('pw-error');
  errEl.style.display = 'none';

  const btn = event.target;
  btn.disabled = true;
  btn.textContent = 'Prüfe…';

  try {
    const fd = new FormData();
    fd.append('pw', pw);
    const res  = await fetch(API + '?action=auth', {method:'POST', body: fd});
    const data = await res.json();
    if (data.ok) {
      goStep(2);
      testConnection();
    } else {
      errEl.textContent = data.error || 'Fehler';
      errEl.style.display = '';
    }
  } catch(e) {
    errEl.textContent = 'Netzwerkfehler: ' + e.message;
    errEl.style.display = '';
  }
  btn.disabled = false;
  btn.textContent = 'Weiter →';
}

// allow Enter to submit
document.getElementById('setup-pw')?.addEventListener('keydown', e => {
  if (e.key === 'Enter') step1Next();
});

// step 2 – DB connection test
async function testConnection() {
  document.getElementById('db-loading').style.display = '';
  document.getElementById('db-result').style.display  = 'none';
  document.getElementById('btn-step2-next').disabled  = true;

  try {
    const res  = await fetch(API + '?action=test_db', {method:'POST'});
    const data = await res.json();

    document.getElementById('db-loading').style.display = 'none';
    document.getElementById('db-result').style.display  = '';

    if (data.ok) {
      document.getElementById('db-info').innerHTML = `
        ${infoRow('✓','Server',    data.host,     true)}
        ${infoRow('✓','Benutzer',  data.user,     true)}
        ${infoRow('✓','MySQL',     data.version,  true)}
        ${infoRow(data.db_exists ? '✓' : '＋', 'Datenbank', data.db + (data.db_exists ? ' (vorhanden)' : ' (wird angelegt)'), true)}
        ${infoRow(data.schema_ok ? '✓' : '✕', 'db/schema.sql', data.schema_ok ? 'gefunden' : 'FEHLT!', data.schema_ok)}
      `;
      document.getElementById('db-error').style.display = 'none';
      document.getElementById('btn-step2-next').disabled = !data.schema_ok;
    } else {
      document.getElementById('db-error').textContent = data.error;
      document.getElementById('db-error').style.display = '';
      document.getElementById('db-info').innerHTML = '';
    }
  } catch(e) {
    document.getElementById('db-loading').style.display = 'none';
    document.getElementById('db-result').style.display  = '';
    document.getElementById('db-error').textContent = 'Netzwerkfehler: ' + e.message;
    document.getElementById('db-error').style.display = '';
  }
}

function infoRow(icon, label, val, ok) {
  return `<div class="info-row ${ok ? 'info-row--ok' : 'info-row--error'}">
    <span class="info-row__icon">${icon}</span>
    <span class="info-row__label">${label}</span>
    <span class="info-row__val">${escHtml(String(val))}</span>
  </div>`;
}

function step2Next() { goStep(3); setTimeout(() => document.getElementById('admin-name')?.focus(), 100); }

// step 3 – admin account
function pwStrength(pw) {
  let s = 0;
  if (pw.length >= 6)  s++;
  if (pw.length >= 10) s++;
  if (/[A-Z]/.test(pw)) s++;
  if (/[0-9]/.test(pw)) s++;
  if (/[^a-zA-Z0-9]/.test(pw)) s++;
  return s;
}

function onAdminInput() {
  const name    = document.getElementById('admin-name').value.trim();
  const display = document.getElementById('admin-display-name').value.trim();
  const pw      = document.getElementById('admin-pw').value;
  const pw2     = document.getElementById('admin-pw2').value;
  const bar     = document.getElementById('admin-pw-bar');
  const colors  = ['','#ef4444','#f97316','#eab308','#22c55e','#16a34a'];
  const score   = pwStrength(pw);
  bar.style.width      = Math.min(100, score * 22) + '%';
  bar.style.background = colors[score] || '#ef4444';
  document.getElementById('btn-admin-next').disabled =
    !(name.length >= 3 && display.length >= 2 && pw.length >= 6 && pw === pw2);
}

async function step3Next() {
  const btn  = document.getElementById('btn-admin-next');
  const errEl = document.getElementById('admin-error');
  errEl.style.display = 'none';
  btn.disabled = true;
  btn.textContent = 'Prüfe…';

  const fd = new FormData();
  fd.append('username',     document.getElementById('admin-name').value.trim());
  fd.append('display_name', document.getElementById('admin-display-name').value.trim());
  fd.append('password', document.getElementById('admin-pw').value);
  fd.append('confirm',  document.getElementById('admin-pw2').value);

  try {
    const res  = await fetch(API + '?action=save_admin', {method:'POST', body: fd});
    const data = await res.json();
    if (data.ok) {
      document.getElementById('confirm-admin-name').textContent =
        document.getElementById('admin-name').value.trim();
      document.getElementById('confirm-admin-display').textContent =
        document.getElementById('admin-display-name').value.trim();
      goStep(4);
      document.getElementById('confirm-text').value = '';
      document.getElementById('btn-confirm').disabled = true;
    } else {
      errEl.textContent = data.error || 'Fehler';
      errEl.style.display = '';
      btn.disabled = false;
    }
  } catch(e) {
    errEl.textContent = 'Netzwerkfehler: ' + e.message;
    errEl.style.display = '';
    btn.disabled = false;
  }
  btn.textContent = 'Weiter →';
}

// step 4 – confirm
function step4Next() {
  document.getElementById('btn-confirm').disabled = true;
  document.getElementById('confirm-text').disabled = true;
  goStep(5);
  runSetup();
}

// step 5 – SSE progress
function runSetup() {
  const log      = document.getElementById('setup-log');
  const fill     = document.getElementById('progress-fill');
  const pctEl    = document.getElementById('progress-pct');
  const doneWrap = document.getElementById('setup-done');
  const doneMsg  = document.getElementById('done-msg');

  function setProgress(pct) {
    fill.style.width = Math.max(0, Math.min(100, pct)) + '%';
    pctEl.textContent = Math.max(0, Math.min(100, pct)) + ' %';
  }

  function addLog(type, text) {
    const icon = type === 'ok' ? '✓' : type === 'error' ? '✕' : '🌕';
    const row  = document.createElement('div');
    row.className = 'setup-log__row setup-log__row--' + type;
    row.innerHTML = `<span class="setup-log__icon">${icon}</span><span>${escHtml(text)}</span>`;
    log.appendChild(row);
    log.scrollTop = log.scrollHeight;
  }

  document.querySelector('#wizard-steps [data-step="5"] .wizard-step__dot').textContent = '⟳';

  const es = new EventSource(API + '?action=run');

  es.onmessage = function(e) {
    const d = JSON.parse(e.data);
    if (d.pct >= 0) setProgress(d.pct);

    if (d.type === 'done') {
      es.close();
      doneWrap.style.display = '';
      const step5 = document.querySelector('#wizard-steps [data-step="5"]');
      if (d.text.startsWith('success')) {
        const adminName = d.text.split('|')[1] || 'admin';
        step5.querySelector('.wizard-step__dot').textContent = '✓';
        step5.classList.remove('wizard-step--active');
        step5.classList.add('wizard-step--done');
        doneMsg.innerHTML = '<div class="alert alert--success">🐺 Fertig! Login: <strong>' + escHtml(adminName) + '</strong> mit deinem gewählten Passwort.</div>';
        const _btnLogin = document.getElementById('btn-login');
        _btnLogin.style.display = '';
        let _cd = 5;
        _btnLogin.textContent = 'Zur Anmeldung → (' + _cd + ')';
        const _cdTimer = setInterval(() => {
          _cd--;
          _btnLogin.textContent = 'Zur Anmeldung → (' + _cd + ')';
          if (_cd <= 0) { clearInterval(_cdTimer); window.location.href = _btnLogin.href; }
        }, 1000);
      } else {
        step5.querySelector('.wizard-step__dot').textContent = '✕';
        step5.classList.remove('wizard-step--active');
        step5.classList.add('wizard-step--error');
        doneMsg.innerHTML = '<div class="alert alert--error">Setup fehlgeschlagen. Prüfe die DB-Zugangsdaten in <code>config/config.php</code> und versuche es erneut.</div>';
        document.getElementById('btn-retry').style.display = '';
      }
      return;
    }

    addLog(d.type, d.text);
  };

  es.onerror = function() {
    es.close();
    addLog('error', 'Verbindung zum Server unterbrochen.');
    doneWrap.style.display = '';
    document.getElementById('btn-retry').style.display = '';
  };
}

function escHtml(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// skip step 1 when SETUP_PASSWORD is empty
<?php if ($noPw): ?>
document.addEventListener('DOMContentLoaded', () => step1Next());
<?php endif; ?>
</script>

</body>
</html>
