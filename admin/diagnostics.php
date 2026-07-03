<?php
// Copyright (c) 2026 Andreas Vetter
require_once dirname(__DIR__) . '/core/bootstrap.php';
require_once CORE_PATH . '/WebPush.php';
Auth::requireAdmin();

// ── Push-Schlüssel generieren (POST) ────────────────────────
$keysGenResult = null;
if (isPost() && post('action') === 'generate_keys') {
    $ok = WebPush::ensureKeys();
    $keysGenResult = $ok
        ? ['ok' => true,  'msg' => 'VAPID-Schlüssel erfolgreich generiert und gespeichert.']
        : ['ok' => false, 'msg' => 'Schlüssel-Generierung fehlgeschlagen — OpenSSL EC nicht verfügbar?'];
}

// ── Push-Test (POST) ─────────────────────────────────────────
$pushTestResult = null;
if (isPost() && post('action') === 'test_push') {
    try {
        $subs = Database::query("SELECT DISTINCT player_id FROM push_subscriptions");
        if (empty($subs)) {
            $pushTestResult = ['ok' => false, 'msg' => 'Keine abonnierten Geräte in der Datenbank.'];
        } else {
            foreach ($subs as $s) {
                WebPush::sendToPlayer((int)$s['player_id']);
            }
            $count = count($subs);
            $pushTestResult = ['ok' => true, 'msg' => "Push gesendet an {$count} Spieler — Benachrichtigung sollte gleich erscheinen."];
        }
    } catch (Throwable $e) {
        $pushTestResult = ['ok' => false, 'msg' => 'Fehler: ' . $e->getMessage()];
    }
}

// ── URL-Test (POST) ──────────────────────────────────────────
$urlTestResult = null;
if (isPost() && ($testUrl = trim(post('test_url')))) {
    if (!preg_match('#^https?://#i', $testUrl)) {
        $urlTestResult = ['ok' => false, 'msg' => 'URL muss mit http:// oder https:// beginnen.', 'code' => null];
    } else {
        $ctx = stream_context_create(['http' => [
            'method'          => 'GET',
            'timeout'         => 8,
            'ignore_errors'   => true,
            'follow_location' => true,
            'max_redirects'   => 3,
            'user_agent'      => 'Werwolf-Diagnostics/1.0',
        ]]);
        $start = microtime(true);
        $body  = @file_get_contents($testUrl, false, $ctx);
        $ms    = round((microtime(true) - $start) * 1000);
        $line  = $http_response_header[0] ?? 'Keine Antwort';
        preg_match('#HTTP/\S+\s+(\d+)#', $line, $m);
        $code  = isset($m[1]) ? (int)$m[1] : null;
        $ok    = $code && $code < 400;
        $urlTestResult = ['ok' => $ok, 'msg' => $line, 'code' => $code, 'ms' => $ms, 'bytes' => strlen($body ?: '')];
    }
}

// ── Hilfsfunktionen ──────────────────────────────────────────
function chk(bool $ok, string $label, string $detail = ''): array {
    return ['ok' => $ok, 'label' => $label, 'detail' => $detail];
}

// ── PHP-Prüfungen ────────────────────────────────────────────
$phpChecks = [];

$phpVersion = PHP_VERSION;
$phpOk = version_compare(PHP_VERSION, '8.0.0', '>=');
$phpChecks[] = chk($phpOk, 'PHP-Version: ' . $phpVersion, $phpOk ? 'Mindestens 8.0 erforderlich' : 'Mindestens PHP 8.0 erforderlich!');

$requiredExts = [
    'pdo'       => 'PDO (Datenbankschicht)',
    'pdo_mysql' => 'PDO MySQL (MySQL-Treiber)',
    'json'      => 'JSON (API-Kommunikation)',
    'mbstring'  => 'Multibyte-String (Spielernamen mit Umlauten)',
    'fileinfo'  => 'FileInfo (Upload-Typ-Erkennung)',
    'session'   => 'Sessions (Login)',
];
foreach ($requiredExts as $ext => $desc) {
    $loaded = extension_loaded($ext);
    $phpChecks[] = ['ok' => $loaded, 'label' => "ext/{$ext}", 'detail' => $desc . ($loaded ? '' : ' — FEHLT!'), 'ext' => $ext];
}

$optionalExts = [
    'gd'      => 'GD (Bildbearbeitung für Icon-Upload)',
    'curl'    => 'cURL (HTTP-Anfragen)',
    'imagick' => 'ImageMagick (Erweiterte Bildverarbeitung)',
];
foreach ($optionalExts as $ext => $desc) {
    $loaded = extension_loaded($ext);
    $phpChecks[] = ['ok' => $loaded, 'label' => "ext/{$ext} (optional)", 'detail' => $desc, 'optional' => true, 'ext' => $ext];
}

$iniChecks = [
    ['upload_max_filesize', '2M', 'Mindestens 2M für Icon-Upload'],
    ['post_max_size',       '8M', 'Mindestens 8M empfohlen'],
    ['max_execution_time',  '30', 'Mindestens 30s'],
];
foreach ($iniChecks as [$key, $min, $desc]) {
    $val = ini_get($key);
    $phpChecks[] = chk(true, "php.ini: {$key} = {$val}", $desc);
}

// ── Datenbank-Prüfungen ──────────────────────────────────────
$dbChecks = [];

try {
    $pdo = Database::get();
    $dbChecks[] = chk(true, 'Datenbankverbindung', DB_HOST . ':' . DB_PORT . '/' . DB_NAME);

    $ver = $pdo->query('SELECT VERSION()')->fetchColumn();
    $dbChecks[] = chk(true, 'MySQL-Version: ' . $ver, '');

    $requiredTables = ['players','games','game_players','votes','deaths','roles'];
    foreach ($requiredTables as $tbl) {
        $exists = $pdo->query("SHOW TABLES LIKE '{$tbl}'")->rowCount() > 0;
        $count  = $exists ? $pdo->query("SELECT COUNT(*) FROM `{$tbl}`")->fetchColumn() : 0;
        $dbChecks[] = chk($exists, "Tabelle: {$tbl}", $exists ? "{$count} Einträge" : 'TABELLE FEHLT — setup.php ausführen!');
    }

    $adminCount = (int)$pdo->query("SELECT COUNT(*) FROM players WHERE is_admin=1")->fetchColumn();
    $dbChecks[] = chk($adminCount > 0, 'Admin-Konto', $adminCount > 0 ? "{$adminCount} Admin(s) vorhanden" : 'Kein Admin — setup.php ausführen!');

} catch (Throwable $e) {
    $dbChecks[] = chk(false, 'Datenbankverbindung', $e->getMessage());
}

// ── Dateisystem-Prüfungen ────────────────────────────────────
$fsChecks = [];

$root = ROOT_PATH;
$criticalFiles = [
    'config/config.php'          => 'Hauptkonfiguration',
    'config/themes.php'          => 'Theme-Definitionen',
    'core/bootstrap.php'         => 'Bootstrap',
    'core/Database.php'          => 'Datenbankklasse',
    'core/Auth.php'              => 'Auth-Klasse',
    'core/helpers.php'           => 'Hilfsfunktionen',
    'db/schema.sql'              => 'Datenbankschema',
    'assets/css/app.css'         => 'Haupt-CSS',
    'assets/js/app.js'           => 'Haupt-JS',
    'templates/base.php'         => 'Basis-Template',
    'templates/base_end.php'     => 'Basis-Template Ende',
    'templates/nav.php'          => 'Navigation',
    'assets/js/sw.js'            => 'Service Worker',
    'index.php'                  => 'Login-Seite',
    'app/game.php'               => 'Spielfeld',
    'app/deaths.php'             => 'Todesliste',
    'app/register.php'           => 'Registrierung',
    'app/logout.php'             => 'Logout',
    'admin/index.php'            => 'Admin-Spielleitung',
    'admin/roles.php'            => 'Admin-Rollen',
    'admin/setup.php'            => 'Admin-Setup',
    'admin/diagnostics.php'      => 'Admin-Diagnose',
    'api/game.php'               => 'API Spiel',
    'api/admin.php'              => 'API Admin',
    'api/upload_role_icon.php'   => 'API Icon-Upload',
];
foreach ($criticalFiles as $rel => $desc) {
    $abs = $root . '/' . $rel;
    $exists = file_exists($abs);
    $fsChecks[] = chk($exists, $rel, $desc . ($exists ? ' (' . number_format(filesize($abs)) . ' Bytes)' : ' — DATEI FEHLT!'));
}

$iconDir = $root . '/assets/icons/roles';
$writable = is_dir($iconDir) && is_writable($iconDir);
$fsChecks[] = chk($writable, 'assets/icons/roles/ (schreibbar)', $writable ? 'Icon-Upload möglich' : 'Nicht schreibbar — chmod 775 setzen!');

// ── Server-Infos ─────────────────────────────────────────────
$serverInfo = [
    'Server-Software'  => $_SERVER['SERVER_SOFTWARE'] ?? 'unbekannt',
    'Document Root'    => $_SERVER['DOCUMENT_ROOT']   ?? 'unbekannt',
    'Script-Pfad'      => $_SERVER['SCRIPT_FILENAME'] ?? 'unbekannt',
    'APP_URL'          => APP_URL ?: '(leer = Domain-Root)',
    'ROOT_PATH'        => ROOT_PATH,
    'ASSETS_URL'       => ASSETS_URL,
    'API_URL'          => API_URL,
    'APP_DEBUG'        => APP_DEBUG ? 'true' : 'false',
    'Session-Name'     => SESSION_NAME,
    'PHP SAPI'         => PHP_SAPI,
    'OS'               => PHP_OS,
    'mod_rewrite'      => function_exists('apache_get_modules') ? (in_array('mod_rewrite', apache_get_modules()) ? 'aktiv' : 'INAKTIV') : 'nicht prüfbar (kein Apache-SAPI)',
    'mod_headers'      => function_exists('apache_get_modules') ? (in_array('mod_headers', apache_get_modules()) ? 'aktiv' : 'INAKTIV') : 'nicht prüfbar (kein Apache-SAPI)',
];

// ── Gesamtstatus ─────────────────────────────────────────────
$allChecks = array_merge($phpChecks, $dbChecks, $fsChecks);
$errors   = count(array_filter($allChecks, fn($c) => !$c['ok'] && empty($c['optional'])));
$warnings = count(array_filter($allChecks, fn($c) => !$c['ok'] && !empty($c['optional'])));

$page = ['title' => 'Diagnose'];
require TEMPLATE_PATH . '/base.php';
?>

<div class="container page-wrap">

  <div class="page-header">
    <span class="page-header__icon">🔍</span>
    <h1>System-Diagnose</h1>
    <p class="page-header__sub">
      <a href="<?= APP_URL ?>/admin/">← zurück zur Spielleitung</a>
    </p>
  </div>

  <!-- Gesamtstatus -->
  <div class="card card--glow animate-in mb-2" style="animation-delay:.0s">
    <?php if ($errors === 0 && $warnings === 0): ?>
      <div class="alert alert--success" style="font-size:1.05rem">
        ✅ Alles in Ordnung — keine Fehler oder Warnungen gefunden.
      </div>
    <?php elseif ($errors === 0): ?>
      <div class="alert alert--warn" style="font-size:1.05rem">
        ⚠️ <?= $warnings ?> optionale Komponente(n) fehlen — keine kritischen Fehler.
      </div>
    <?php else: ?>
      <div class="alert alert--error" style="font-size:1.05rem">
        ❌ <?= $errors ?> kritische(r) Fehler gefunden<?= $warnings ? ", {$warnings} Warnung(en)" : '' ?> — Details unten.
      </div>
    <?php endif; ?>
    <div class="text-dim text-xs mt-1">Geprüft am <?= date('d.m.Y H:i:s') ?></div>
  </div>

  <!-- URL-Tester -->
  <div class="card animate-in mb-2" style="animation-delay:.04s">
    <div class="section-title">🌐 URL-Erreichbarkeitstest</div>
    <p class="text-dim text-sm mb-2">
      Teste ob eine URL vom Server aus erreichbar ist und welchen HTTP-Statuscode sie zurückgibt.
    </p>
    <form method="POST" style="display:flex;gap:.5rem;flex-wrap:wrap">
      <input class="form-input" type="url" name="test_url"
             placeholder="https://deine-domain.de/app/game.php"
             value="<?= e(post('test_url')) ?>"
             style="flex:1;min-width:220px" required>
      <button class="btn btn--primary" type="submit">Testen</button>
    </form>

    <?php if ($urlTestResult !== null): ?>
    <div class="mt-2 panel" style="font-family:monospace;font-size:.88rem">
      <?php $r = $urlTestResult; ?>
      <?php if ($r['ok'] === false && $r['code'] === null): ?>
        <span style="color:var(--danger-text)">✕ <?= e($r['msg']) ?></span>
      <?php else: ?>
        <span style="color:<?= $r['ok'] ? 'var(--alert-success-text)' : 'var(--danger-text)' ?>">
          <?= $r['ok'] ? '✓' : '✕' ?>
          HTTP <?= $r['code'] ?> — <?= e($r['msg']) ?>
        </span>
        <span class="text-dim"> | <?= $r['ms'] ?>ms | <?= number_format($r['bytes']) ?> Bytes</span>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Schnelltest-Links -->
    <div class="mt-2">
      <div class="text-dim text-xs mb-1">Schnelltests (klicken zum Ausfüllen):</div>
      <div style="display:flex;flex-wrap:wrap;gap:.3rem">
        <?php
        $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
                 . '://' . $_SERVER['HTTP_HOST'];
        $quickUrls = [
            $baseUrl . '/'                       => 'Login',
            $baseUrl . '/app/game.php'           => 'Spielfeld',
            $baseUrl . '/admin/'                 => 'Admin',
            $baseUrl . '/api/game.php'           => 'API game',
            $baseUrl . '/api/admin.php'          => 'API admin',
            $baseUrl . '/assets/css/app.css'     => 'CSS',
            $baseUrl . '/assets/js/app.js'       => 'JS',
            $baseUrl . '/config/config.php'      => 'config (403?)',
            $baseUrl . '/core/bootstrap.php'     => 'core (403?)',
            $baseUrl . '/db/schema.sql'          => 'schema.sql (403?)',
        ];
        foreach ($quickUrls as $url => $label):
        ?>
        <button type="button" class="btn btn--ghost btn--sm"
                onclick="document.querySelector('[name=test_url]').value='<?= e($url) ?>'">
          <?= e($label) ?>
        </button>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Push-Test -->
  <div class="card animate-in mb-2" style="animation-delay:.06s">
    <div class="section-title">🔔 Web-Push-Test</div>
    <?php
    $vapidKey  = WebPush::getPublicKey();
    $pushTable = false;
    $mySubCount = 0;
    try {
        $pushTable  = Database::get()->query("SHOW TABLES LIKE 'push_subscriptions'")->rowCount() > 0;
        if ($pushTable) {
            $r = Database::queryOne(
                "SELECT COUNT(*) AS n FROM push_subscriptions WHERE player_id = ?",
                [Auth::player()['id']]
            );
            $mySubCount = $r ? (int)$r['n'] : 0;
        }
    } catch (Throwable $e) {}
    ?>
    <?php if ($keysGenResult): ?>
    <div class="alert <?= $keysGenResult['ok'] ? 'alert--success' : 'alert--error' ?> mb-2">
      <?= e($keysGenResult['msg']) ?>
    </div>
    <?php endif; ?>
    <div class="flex gap-sm mb-2" style="padding:.3rem 0;border-bottom:1px solid var(--border);font-size:.88rem;flex-wrap:wrap;align-items:center">
      <span style="flex:0 0 1.2rem;color:<?= $vapidKey ? 'var(--alert-success-text)' : 'var(--danger-text)' ?>">
        <?= $vapidKey ? '✓' : '✕' ?>
      </span>
      <span style="font-family:monospace;flex:1;color:var(--text-bright)">VAPID-Schlüssel</span>
      <?php if ($vapidKey): ?>
        <span class="text-dim" style="flex:0 0 100%;padding-left:1.7rem">Vorhanden (<?= substr($vapidKey, 0, 20) ?>…)</span>
      <?php else: ?>
        <span class="text-dim" style="padding-left:1.7rem">Fehlt —</span>
        <form method="POST" style="display:inline;margin-left:.4rem">
          <input type="hidden" name="action" value="generate_keys">
          <button class="btn btn--primary btn--sm" type="submit">Jetzt generieren</button>
        </form>
      <?php endif; ?>
    </div>
    <div class="flex gap-sm mb-2" style="padding:.3rem 0;border-bottom:1px solid var(--border);font-size:.88rem;flex-wrap:wrap">
      <span style="flex:0 0 1.2rem;color:<?= $pushTable ? 'var(--alert-success-text)' : 'var(--danger-text)' ?>">
        <?= $pushTable ? '✓' : '✕' ?>
      </span>
      <span style="font-family:monospace;flex:1;color:var(--text-bright)">Tabelle: push_subscriptions</span>
      <span class="text-dim" style="flex:0 0 100%;padding-left:1.7rem">
        <?= $pushTable ? 'Vorhanden' : 'FEHLT — migration_push.sql ausführen!' ?>
      </span>
    </div>
    <?php
    $totalSubs = 0;
    try {
        $r2 = Database::queryOne("SELECT COUNT(*) AS n FROM push_subscriptions");
        $totalSubs = $r2 ? (int)$r2['n'] : 0;
    } catch (Throwable $e) {}
    ?>
    <div class="flex gap-sm mb-2" style="padding:.3rem 0;border-bottom:1px solid var(--border);font-size:.88rem;flex-wrap:wrap">
      <span style="flex:0 0 1.2rem;color:<?= $totalSubs > 0 ? 'var(--alert-success-text)' : 'var(--alert-warn-text,#f59e0b)' ?>">
        <?= $totalSubs > 0 ? '✓' : '⚠' ?>
      </span>
      <span style="font-family:monospace;flex:1;color:var(--text-bright)">Abonnierte Geräte (gesamt)</span>
      <span class="text-dim" style="flex:0 0 100%;padding-left:1.7rem">
        <?= $totalSubs > 0 ? "{$totalSubs} Gerät(e) abonniert" : 'Noch kein Gerät abonniert' ?>
      </span>
    </div>

    <?php if ($pushTestResult): ?>
    <div class="alert <?= $pushTestResult['ok'] ? 'alert--success' : 'alert--error' ?> mb-2">
      <?= e($pushTestResult['msg']) ?>
    </div>
    <?php endif; ?>

    <form method="POST">
      <input type="hidden" name="action" value="test_push">
      <button class="btn btn--primary" type="submit" <?= (!$pushTable || !$vapidKey || $totalSubs === 0) ? 'disabled' : '' ?>>
        🔔 Test-Push an alle senden
      </button>
      <span class="text-dim text-sm" style="margin-left:.75rem">
        Schickt eine Benachrichtigung an alle abonnierten Geräte.
      </span>
    </form>
  </div>

  <!-- PHP-Checks -->
  <div class="card animate-in mb-2" style="animation-delay:.08s">
    <div class="section-title">🐘 PHP & Extensions</div>

    <?php foreach ($phpChecks as $c): ?>
    <div class="flex gap-sm" style="padding:.3rem 0;border-bottom:1px solid var(--border);font-size:.88rem;flex-wrap:wrap">
      <span style="flex:0 0 1.2rem;color:<?= $c['ok'] ? 'var(--alert-success-text)' : (empty($c['optional']) ? 'var(--danger-text)' : 'var(--alert-warn-text,#f59e0b)') ?>">
        <?= $c['ok'] ? '✓' : (empty($c['optional']) ? '✕' : '⚠') ?>
      </span>
      <span style="font-family:monospace;flex:1;min-width:0;color:var(--text-bright);word-break:break-all"><?= e($c['label']) ?></span>
      <span class="text-dim" style="flex:0 0 100%;padding-left:1.7rem"><?= e($c['detail']) ?></span>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Datenbank-Checks -->
  <div class="card animate-in mb-2" style="animation-delay:.12s">
    <div class="section-title">🗄️ Datenbank</div>
    <?php foreach ($dbChecks as $c): ?>
    <div class="flex gap-sm" style="padding:.3rem 0;border-bottom:1px solid var(--border);font-size:.88rem;flex-wrap:wrap">
      <span style="flex:0 0 1.2rem;color:<?= $c['ok'] ? 'var(--alert-success-text)' : 'var(--danger-text)' ?>">
        <?= $c['ok'] ? '✓' : '✕' ?>
      </span>
      <span style="font-family:monospace;flex:1;min-width:0;color:var(--text-bright);word-break:break-all"><?= e($c['label']) ?></span>
      <span class="text-dim" style="flex:0 0 100%;padding-left:1.7rem"><?= e($c['detail']) ?></span>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Dateisystem-Checks -->
  <div class="card animate-in mb-2" style="animation-delay:.16s">
    <div class="section-title">📁 Dateisystem</div>
    <?php foreach ($fsChecks as $c): ?>
    <div class="flex gap-sm" style="padding:.3rem 0;border-bottom:1px solid var(--border);font-size:.88rem;flex-wrap:wrap">
      <span style="flex:0 0 1.2rem;color:<?= $c['ok'] ? 'var(--alert-success-text)' : 'var(--danger-text)' ?>">
        <?= $c['ok'] ? '✓' : '✕' ?>
      </span>
      <span style="font-family:monospace;flex:1;min-width:0;color:var(--text-bright);word-break:break-all"><?= e($c['label']) ?></span>
      <span class="text-dim" style="flex:0 0 100%;padding-left:1.7rem"><?= e($c['detail']) ?></span>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Server-Info -->
  <div class="card animate-in mb-2" style="animation-delay:.20s">
    <div class="section-title">🖥️ Server & Konfiguration</div>
    <?php foreach ($serverInfo as $key => $val): ?>
    <div class="flex gap-sm" style="padding:.3rem 0;border-bottom:1px solid var(--border);font-size:.88rem;flex-wrap:wrap">
      <span style="font-family:monospace;flex:0 0 100%;color:var(--text-dim)"><?= e($key) ?></span>
      <span style="font-family:monospace;color:var(--text-bright);word-break:break-all;padding-left:.5rem"><?= e($val) ?></span>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- KI-Schnellbericht -->
  <div class="card animate-in" style="animation-delay:.24s">
    <div class="section-title">🤖 KI-Fehlerbericht (kopieren & einfügen)</div>
    <p class="text-dim text-sm mb-2">
      Diesen Text beim Melden eines Fehlers an eine KI weitergeben:
    </p>
    <textarea class="form-input" rows="12" readonly onclick="this.select()"
              style="font-family:monospace;font-size:.78rem;white-space:pre;resize:vertical"
><?= e(implode("\n", array_map(function($c) {
    $status = $c['ok'] ? 'OK' : (empty($c['optional']) ? 'FEHLER' : 'WARNUNG');
    return "[{$status}] {$c['label']}" . ($c['detail'] ? " — {$c['detail']}" : '');
}, $allChecks)) . "\n\n" . implode("\n", array_map(
    fn($k,$v) => "{$k}: {$v}",
    array_keys($serverInfo),
    array_values($serverInfo)
))) ?></textarea>
    <button class="btn btn--ghost btn--sm mt-1"
            onclick="navigator.clipboard.writeText(document.querySelector('textarea').value).then(()=>showToast('Kopiert!','success'))">
      📋 In Zwischenablage kopieren
    </button>
  </div>

</div>

<?php require TEMPLATE_PATH . '/base_end.php'; ?>
