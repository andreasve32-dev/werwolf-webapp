<?php
// Copyright (c) 2026 Andreas Vetter
require_once dirname(__DIR__) . '/core/bootstrap.php';
Auth::requireAdmin();

// ── AJAX: Einstellung(en) speichern ──────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'save') {
    header('Content-Type: application/json');

    $allowed = [
        'app_name', 'app_version', 'app_debug', 'beta_mode', 'default_theme', 'login_title', 'login_subtitle',
        'min_players', 'max_players',
        'background_music', 'default_role_icon', 'session_lifetime',
        'deaths_empty_title', 'deaths_empty_sub', 'deaths_peace_text',
        'login_logo', 'mini_logo', 'register_subtitle',
        'game_timezone',
        'push_cooldown',
    ];

    $body   = json_decode(file_get_contents('php://input'), true) ?? [];
    $errors = [];
    $values = [];

    foreach ($allowed as $key) {
        if (!array_key_exists($key, $body)) continue;
        $v = $body[$key];

        switch ($key) {
            case 'app_name':
                $v = trim($v);
                if ($v === '' || mb_strlen($v) > 80) {
                    $errors[$key] = 'Spielname: 1–80 Zeichen.'; continue 2;
                }
                break;
            case 'min_players':
                $v = (int)$v;
                if ($v < 2 || $v > 50) {
                    $errors[$key] = 'Min. Spieler: 2–50.'; continue 2;
                }
                $v = (string)$v;
                break;
            case 'max_players':
                $v = (int)$v;
                if ($v < 2 || $v > 200) {
                    $errors[$key] = 'Max. Spieler: 2–200.'; continue 2;
                }
                $v = (string)$v;
                break;
            case 'app_debug':
            case 'beta_mode':
                $v = filter_var($v, FILTER_VALIDATE_BOOLEAN) ? '1' : '0';
                break;
            case 'default_theme':
                if (!isset(THEMES[$v])) {
                    $errors[$key] = 'Ungültiges Theme.'; continue 2;
                }
                break;
            case 'session_lifetime':
                $v = (int)$v;
                if ($v < 300 || $v > 86400 * 365) {
                    $errors[$key] = 'Session-Dauer: 300 – 31.536.000 Sekunden.'; continue 2;
                }
                $v = (string)$v;
                break;
            case 'app_version':
                $v = trim($v);
                if ($v === '' || mb_strlen($v) > 30) {
                    $errors[$key] = 'Versionsnummer: 1–30 Zeichen.'; continue 2;
                }
                break;
            case 'login_title':
            case 'login_subtitle':
            case 'register_subtitle':
                $v = trim($v);
                if (mb_strlen($v) > 200) {
                    $errors[$key] = 'Text: max. 200 Zeichen.'; continue 2;
                }
                break;
            case 'deaths_empty_title':
            case 'deaths_empty_sub':
            case 'deaths_peace_text':
            case 'background_music':
            case 'default_role_icon':
            case 'login_logo':
            case 'mini_logo':
                $v = trim($v);
                if (mb_strlen($v) > 255) {
                    $errors[$key] = 'Wert zu lang (max. 255 Zeichen).'; continue 2;
                }
                break;
            case 'game_timezone':
                $v = trim($v);
                if ($v !== '' && !in_array($v, timezone_identifiers_list(), true)) {
                    $errors[$key] = 'Ungültige Zeitzone (z.B. Europe/Berlin).'; continue 2;
                }
                break;
            case 'push_cooldown':
                $v = (int)$v;
                if ($v < 0 || $v > 1440) {
                    $errors[$key] = 'Push-Cooldown: 0–1440 Minuten.'; continue 2;
                }
                $v = (string)$v;
                break;
        }
        $values[$key] = $v;
    }

    // Kreuz-Validierung min/max
    $minV = isset($values['min_players']) ? (int)$values['min_players']
          : (int)(Database::queryOne('SELECT value FROM settings WHERE `key`=?', ['min_players'])['value'] ?? 4);
    $maxV = isset($values['max_players']) ? (int)$values['max_players']
          : (int)(Database::queryOne('SELECT value FROM settings WHERE `key`=?', ['max_players'])['value'] ?? 30);
    if ($minV > $maxV) {
        $errors['min_players'] = 'Mindest-Spieler darf nicht größer als Maximal-Spieler sein.';
    }

    if (!empty($errors)) {
        echo json_encode(['ok' => false, 'errors' => $errors]); exit;
    }

    try {
        foreach ($values as $k => $v) {
            Database::execute(
                'INSERT INTO settings (`key`, value) VALUES (?,?) AS new_row
                 ON DUPLICATE KEY UPDATE value = new_row.value',
                [$k, $v]
            );
        }
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'errors' => ['db' => 'Datenbankfehler: ' . $e->getMessage()]]); exit;
    }
    echo json_encode(['ok' => true, 'message' => count($values) . ' Einstellung(en) gespeichert.']);
    exit;
}

// ── AJAX: VAPID-Schlüssel generieren ────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'vapid_generate') {
    header('Content-Type: application/json');
    $body  = json_decode(file_get_contents('php://input'), true) ?? [];
    $force = !empty($body['force']);
    if ($force) {
        Database::execute("DELETE FROM settings WHERE `key` IN ('vapid_public_key','vapid_private_key')");
    }
    require_once dirname(__DIR__) . '/core/WebPush.php';
    $ok = WebPush::ensureKeys();
    if ($ok) {
        $row = Database::queryOne("SELECT value FROM settings WHERE `key`='vapid_public_key'");
        echo json_encode(['ok' => true, 'key' => $row['value'] ?? '', 'message' => 'VAPID-Schlüssel erfolgreich generiert.']);
    } else {
        echo json_encode(['ok' => false, 'error' => 'Schlüsselgenerierung fehlgeschlagen — ist OpenSSL verfügbar?']);
    }
    exit;
}

// ── AJAX: Alle Push-Abonnements löschen ─────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'clear_subs') {
    header('Content-Type: application/json');
    Database::execute("DELETE FROM push_subscriptions");
    echo json_encode(['ok' => true, 'message' => 'Alle Geräte-Abonnements gelöscht.']);
    exit;
}

// ── AJAX: Einzelnes Abonnement löschen ──────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'delete_sub') {
    header('Content-Type: application/json');
    $id = (int)(json_decode(file_get_contents('php://input'), true)['id'] ?? 0);
    if ($id) Database::execute("DELETE FROM push_subscriptions WHERE id=?", [$id]);
    echo json_encode(['ok' => true]);
    exit;
}

// ── Aktuelle Werte aus DB laden ───────────────────────────────
$cfg  = [];
$migrationNeeded = false;
try {
    foreach (Database::query('SELECT `key`, value, type, label, description FROM settings ORDER BY sort_order') as $r) {
        $cfg[$r['key']] = $r;
    }
} catch (Throwable $e) {
    $migrationNeeded = true;
}

// Fallbacks wenn Tabelle leer / fehlende Zeilen
$defaults = [
    'app_name'           => APP_NAME,
    'app_version'        => APP_VERSION,
    'app_debug'          => APP_DEBUG ? '1' : '0',
    'beta_mode'          => BETA_MODE  ? '1' : '0',
    'default_theme'      => DEFAULT_THEME,
    'login_title'        => LOGIN_TITLE,
    'login_subtitle'     => LOGIN_SUBTITLE,
    'register_subtitle'  => REGISTER_SUBTITLE,
    'min_players'        => (string)MIN_PLAYERS,
    'max_players'        => (string)MAX_PLAYERS,
    'background_music'   => BACKGROUND_MUSIC,
    'default_role_icon'  => DEFAULT_ROLE_ICON,
    'session_lifetime'   => (string)SESSION_LIFETIME,
    'deaths_empty_title' => DEATHS_EMPTY_TITLE,
    'deaths_empty_sub'   => DEATHS_EMPTY_SUB,
    'deaths_peace_text'  => DEATHS_PEACE_TEXT,
    'login_logo'         => LOGIN_LOGO,
    'mini_logo'          => MINI_LOGO,
    'game_timezone'      => GAME_TIMEZONE,
    'push_cooldown'      => '5',
];
foreach ($defaults as $k => $def) {
    if (!isset($cfg[$k])) $cfg[$k] = ['key' => $k, 'value' => $def, 'type' => 'string', 'label' => $k, 'description' => ''];
}

// Push-Abonnements laden
$pushSubs = [];
try {
    $pushSubs = Database::query(
        "SELECT ps.id, p.display_name, ps.created_at
         FROM push_subscriptions ps
         JOIN players p ON p.id = ps.player_id
         ORDER BY ps.created_at DESC"
    );
} catch (Throwable $e) {}

$page = ['title' => 'Server-Einstellungen'];
require TEMPLATE_PATH . '/base.php';
?>

<div class="container page-wrap">

  <div class="page-header">
    <span class="page-header__icon">🔧</span>
    <h1>Server-Einstellungen</h1>
    <p class="page-header__sub">Werte werden in der Datenbank gespeichert und beim nächsten Seitenaufruf aktiv.</p>
  </div>

  <?php if ($migrationNeeded): ?>
  <div class="alert alert--error mb-2">
    <strong>⚠️ settings-Tabelle fehlt!</strong><br>
    Diese Installation wurde vor Einführung der DB-Einstellungen aufgesetzt.
    Führe bitte die Migration aus, dann ist diese Seite nutzbar:<br><br>
    <code>db/migration_settings.sql</code> über das Setup-Tool oder direkt in MySQL ausführen.<br><br>
    <em>Alternativ: Setup-Assistent erneut ausführen (löscht alle Daten!).</em>
  </div>
  <?php endif; ?>

  <div id="save-result" style="display:none;margin-bottom:1rem"></div>

  <form id="settings-form" onsubmit="saveSettings(event)"
        <?= $migrationNeeded ? 'style="opacity:.4;pointer-events:none"' : '' ?> >

    <!-- ── Allgemein ──────────────────────────────────────── -->
    <div class="card card--glow animate-in mb-2">
      <div class="section-title">🌐 Allgemein</div>

      <div class="settings-row" style="padding:.6rem 0">
        <div>
          <span class="settings-row__name"><?= e($cfg['app_name']['label'] ?? 'Spielname') ?></span>
          <div class="text-dim text-xs mt-1"><?= e($cfg['app_name']['description'] ?? '') ?></div>
        </div>
        <div style="max-width:260px;width:100%">
          <input class="form-input" type="text" name="app_name"
                 value="<?= e($cfg['app_name']['value']) ?>"
                 maxlength="80" required>
        </div>
      </div>

      <div class="settings-row" style="padding:.6rem 0">
        <div>
          <span class="settings-row__name"><?= e($cfg['app_version']['label'] ?? 'Versionsnummer') ?></span>
          <div class="text-dim text-xs mt-1"><?= e($cfg['app_version']['description'] ?? '') ?></div>
        </div>
        <div style="max-width:160px;width:100%">
          <input class="form-input" type="text" name="app_version"
                 value="<?= e($cfg['app_version']['value']) ?>"
                 maxlength="30" placeholder="z. B. 1.2.0">
        </div>
      </div>

      <div class="settings-row" style="padding:.6rem 0">
        <div>
          <span class="settings-row__name">Login-Kartentitel</span>
          <div class="text-dim text-xs mt-1">Überschrift der Anmeldekarte (z. B. „Willkommen zurück").</div>
        </div>
        <div style="max-width:320px;width:100%">
          <input class="form-input" type="text" name="login_title"
                 value="<?= e($cfg['login_title']['value'] ?? LOGIN_TITLE) ?>"
                 maxlength="200">
        </div>
      </div>

      <div class="settings-row" style="padding:.6rem 0">
        <div>
          <span class="settings-row__name">Login-Untertitel</span>
          <div class="text-dim text-xs mt-1">Slogan unter dem Logo auf der Anmeldeseite.</div>
        </div>
        <div style="max-width:320px;width:100%">
          <input class="form-input" type="text" name="login_subtitle"
                 value="<?= e($cfg['login_subtitle']['value']) ?>"
                 maxlength="200">
        </div>
      </div>

      <div class="settings-row" style="padding:.6rem 0">
        <div>
          <span class="settings-row__name">Registrierungs-Untertitel</span>
          <div class="text-dim text-xs mt-1">Text unter dem Logo auf der Registrierungsseite.</div>
        </div>
        <div style="max-width:320px;width:100%">
          <input class="form-input" type="text" name="register_subtitle"
                 value="<?= e($cfg['register_subtitle']['value'] ?? REGISTER_SUBTITLE) ?>"
                 maxlength="200">
        </div>
      </div>

      <div class="settings-row" style="padding:.6rem 0">
        <div>
          <span class="settings-row__name"><?= e($cfg['app_debug']['label'] ?? 'Debug-Modus') ?></span>
          <div class="text-dim text-xs mt-1"><?= e($cfg['app_debug']['description'] ?? '') ?></div>
        </div>
        <label class="toggle-switch">
          <input type="checkbox" name="app_debug"
                 <?= $cfg['app_debug']['value'] === '1' ? 'checked' : '' ?>>
          <span class="toggle-switch__track"></span>
        </label>
      </div>

      <div class="settings-row" style="padding:.6rem 0">
        <div>
          <span class="settings-row__name">Beta-Modus</span>
          <div class="text-dim text-xs mt-1">Zeigt im Spielfenster einen „Beta"-Hinweis an.</div>
        </div>
        <label class="toggle-switch">
          <input type="checkbox" name="beta_mode"
                 <?= ($cfg['beta_mode']['value'] ?? '1') === '1' ? 'checked' : '' ?>>
          <span class="toggle-switch__track"></span>
        </label>
      </div>

      <div class="settings-row" style="padding:.6rem 0">
        <div>
          <span class="settings-row__name">Zeitzone</span>
          <div class="text-dim text-xs mt-1">PHP-Zeitzone des Servers — wichtig wenn der Server auf UTC/London läuft, das Spiel aber Ortszeit anzeigen soll. Beispiel: <code>Europe/Berlin</code></div>
        </div>
        <div style="max-width:220px;width:100%">
          <input class="form-input" type="text" name="game_timezone"
                 value="<?= e($cfg['game_timezone']['value'] ?? GAME_TIMEZONE) ?>"
                 maxlength="60" placeholder="Europe/Berlin">
        </div>
      </div>
    </div>

    <!-- ── Spiel ──────────────────────────────────────────── -->
    <div class="card animate-in mb-2" style="animation-delay:.05s">
      <div class="section-title">🐺 Spiel</div>

      <div class="settings-row" style="padding:.6rem 0">
        <div>
          <span class="settings-row__name"><?= e($cfg['min_players']['label'] ?? 'Min. Spieler') ?></span>
          <div class="text-dim text-xs mt-1"><?= e($cfg['min_players']['description'] ?? '') ?></div>
        </div>
        <input class="form-input" type="number" name="min_players"
               value="<?= (int)$cfg['min_players']['value'] ?>"
               min="2" max="50" style="width:90px">
      </div>

      <div class="settings-row" style="padding:.6rem 0">
        <div>
          <span class="settings-row__name"><?= e($cfg['max_players']['label'] ?? 'Max. Spieler') ?></span>
          <div class="text-dim text-xs mt-1"><?= e($cfg['max_players']['description'] ?? '') ?></div>
        </div>
        <input class="form-input" type="number" name="max_players"
               value="<?= (int)$cfg['max_players']['value'] ?>"
               min="2" max="200" style="width:90px">
      </div>

      <div class="settings-row" style="padding:.6rem 0">
        <div>
          <span class="settings-row__name">Dorf-Sprüche</span>
          <div class="text-dim text-xs mt-1">Tag- und Nacht-Sprüche werden in der Sprüche-Verwaltung gepflegt.</div>
        </div>
        <a href="<?= APP_URL ?>/admin/slogans.php" class="btn btn--ghost btn--sm">Sprüche verwalten →</a>
      </div>
    </div>

    <!-- ── Push-Benachrichtigungen ─────────────────────────── -->
    <div class="card animate-in mb-2" style="animation-delay:.07s">
      <div class="section-title">🔔 Push-Benachrichtigungen</div>

      <div class="settings-row" style="padding:.6rem 0">
        <div>
          <span class="settings-row__name">Cooldown (Minuten)</span>
          <div class="text-dim text-xs mt-1">
            Mindestwartezeit zwischen zwei automatischen Push-Nachrichten (Kill, Phasenwechsel).
            Spielstart und Spielende ignorieren diesen Wert immer.
            <strong>0 = kein Cooldown.</strong>
          </div>
        </div>
        <div style="display:flex;align-items:center;gap:.5rem">
          <input class="form-input" type="number" name="push_cooldown"
                 value="<?= (int)($cfg['push_cooldown']['value'] ?? 5) ?>"
                 min="0" max="1440" style="width:90px">
          <span class="text-dim text-sm">Min.</span>
        </div>
      </div>

      <!-- VAPID-Schlüssel -->
      <?php $vapidKey = $cfg['vapid_public_key']['value'] ?? ''; ?>
      <div class="settings-row" style="padding:.6rem 0;border-top:1px solid var(--border);margin-top:.5rem">
        <div>
          <span class="settings-row__name">VAPID Public Key</span>
          <div class="text-dim text-xs mt-1">
            Wird im Browser der Spieler hinterlegt. Einmal generieren — danach nicht mehr ändern, sonst müssen alle Spieler Push neu aktivieren.
          </div>
        </div>
        <div style="max-width:340px;width:100%">
          <?php if ($vapidKey): ?>
          <div class="panel text-xs" style="word-break:break-all;padding:.5rem .75rem;line-height:1.5;margin-bottom:.5rem">
            <span id="vapid-key-display"><?= e($vapidKey) ?></span>
          </div>
          <div class="flex gap-xs">
            <button type="button" class="btn btn--ghost btn--sm" onclick="vapidGenerate(false)">🔄 Neu generieren</button>
          </div>
          <?php else: ?>
          <div class="alert alert--error mb-1" style="font-size:.82rem">
            ⚠️ Noch nicht eingerichtet — Push-Benachrichtigungen funktionieren nicht.
          </div>
          <button type="button" class="btn btn--primary btn--sm" onclick="vapidGenerate(false)">✨ Schlüssel generieren</button>
          <?php endif; ?>
          <div id="vapid-result" class="mt-1"></div>
        </div>
      </div>

      <!-- Registrierte Geräte -->
      <div style="border-top:1px solid var(--border);margin-top:.75rem;padding-top:.75rem">
        <div class="flex gap-sm" style="align-items:center;justify-content:space-between;margin-bottom:.5rem">
          <span class="settings-row__name">
            Registrierte Geräte
            <span class="badge" style="margin-left:.4rem" id="subs-count"><?= count($pushSubs) ?></span>
          </span>
          <?php if (!empty($pushSubs)): ?>
          <button type="button" class="btn btn--ghost btn--sm"
                  style="color:var(--danger)" onclick="clearAllSubs()">🗑 Alle löschen</button>
          <?php endif; ?>
        </div>
        <div id="subs-list">
        <?php if (empty($pushSubs)): ?>
          <div class="text-dim text-sm">Noch keine Geräte registriert.</div>
        <?php else: ?>
          <table style="width:100%;font-size:.83rem;border-collapse:collapse" id="subs-table">
            <thead>
              <tr style="color:var(--text-dim);text-align:left">
                <th style="padding:.3rem .5rem">Spieler</th>
                <th style="padding:.3rem .5rem">Registriert am</th>
                <th style="padding:.3rem .5rem;width:32px"></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($pushSubs as $sub): ?>
              <tr id="sub-row-<?= (int)$sub['id'] ?>" style="border-top:1px solid var(--border)">
                <td style="padding:.3rem .5rem"><?= e($sub['display_name']) ?></td>
                <td style="padding:.3rem .5rem;color:var(--text-dim)"><?= e(substr($sub['created_at'] ?? '', 0, 16)) ?></td>
                <td style="padding:.3rem .5rem">
                  <button class="btn btn--ghost btn--sm" style="padding:.15rem .4rem;color:var(--danger)"
                          onclick="deleteSub(<?= (int)$sub['id'] ?>)">✕</button>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- ── Design ─────────────────────────────────────────── -->
    <div class="card animate-in mb-2" style="animation-delay:.08s">
      <div class="section-title">🎨 Design</div>

      <div class="settings-row" style="padding:.6rem 0">
        <div>
          <span class="settings-row__name"><?= e($cfg['default_theme']['label'] ?? 'Standard-Theme') ?></span>
          <div class="text-dim text-xs mt-1"><?= e($cfg['default_theme']['description'] ?? '') ?></div>
        </div>
        <div class="flex gap-xs" style="flex-wrap:wrap">
          <?php foreach (THEMES as $key => $theme): ?>
          <label style="cursor:pointer;display:flex;flex-direction:column;align-items:center;gap:.25rem">
            <input type="radio" name="default_theme" value="<?= e($key) ?>"
                   <?= $cfg['default_theme']['value'] === $key ? 'checked' : '' ?>
                   style="display:none" onchange="updateThemePreview()">
            <span class="theme-row__btn <?= $cfg['default_theme']['value'] === $key ? 'active' : '' ?>"
                  id="theme-btn-<?= e($key) ?>"
                  style="background:<?= e($theme['preview']) ?>;display:flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:50%;cursor:pointer"
                  onclick="selectTheme('<?= e($key) ?>')">
              <?= $theme['icon'] ?>
            </span>
            <span class="text-dim" style="font-size:.65rem"><?= e($theme['label']) ?></span>
          </label>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- ── Audio ──────────────────────────────────────────── -->
    <div class="card animate-in mb-2" style="animation-delay:.1s">
      <div class="section-title">🎵 Audio</div>

      <div class="settings-row" style="padding:.6rem 0">
        <div>
          <span class="settings-row__name"><?= e($cfg['background_music']['label'] ?? 'Hintergrundmusik') ?></span>
          <div class="text-dim text-xs mt-1"><?= e($cfg['background_music']['description'] ?? '') ?></div>
        </div>
        <div style="max-width:260px;width:100%">
          <input class="form-input" type="text" name="background_music"
                 value="<?= e($cfg['background_music']['value']) ?>"
                 placeholder="z. B. background.mp3 — leer = kein Player"
                 maxlength="255">
        </div>
      </div>
    </div>

    <!-- ── Bilder ────────────────────────────────────────── -->
    <div class="card animate-in mb-2" style="animation-delay:.09s">
      <div class="section-title">🖼️ Bilder</div>

      <!-- Login-Logo -->
      <div class="settings-row" style="padding:.6rem 0">
        <div>
          <span class="settings-row__name">Login-Logo</span>
          <div class="text-dim text-xs mt-1">Bild auf der Anmeldeseite anstelle des 🐺-Emojis. Nur PNG (Transparenz wird genutzt) — max. 2 MB.</div>
        </div>
        <div>
          <?php $logoVal = $cfg['login_logo']['value'] ?? ''; ?>
          <?php if ($logoVal !== '' && file_exists(ROOT_PATH . '/' . $logoVal)): ?>
          <div class="flex gap-sm mb-2" style="align-items:center">
            <img src="<?= e(assetUrl($logoVal)) ?>" alt="Aktuelles Logo"
                 style="max-height:64px;max-width:120px;object-fit:contain;background:var(--surface);border-radius:6px;padding:4px">
            <button type="button" class="btn btn--ghost btn--sm" onclick="removeLogo()">✕ Entfernen</button>
          </div>
          <?php else: ?>
          <div class="text-dim text-sm mb-2">Kein Logo hochgeladen — Emoji 🐺 wird angezeigt.</div>
          <?php endif; ?>
          <div class="flex gap-xs" style="align-items:center;flex-wrap:wrap">
            <input type="file" id="logo-file" accept="image/png"
                   class="form-input" style="flex:1;min-width:0">
            <button type="button" id="logo-upload-btn" class="btn btn--primary btn--sm"
                    onclick="uploadLogo()">Hochladen</button>
          </div>
          <div id="logo-upload-result" class="mt-1"></div>
        </div>
      </div>

      <!-- Favicon / Browser-Icon -->
      <div class="settings-row" style="padding:.6rem 0;border-top:1px solid var(--border);margin-top:.5rem">
        <div>
          <span class="settings-row__name">Browser-Icon (Favicon)</span>
          <div class="text-dim text-xs mt-1">Kleines Bild in der Browser-Adressleiste / Tab. Nur PNG (Transparenz wird genutzt) — max. 512 KB. Empfehlung: 64×64 px oder 512×512 px.</div>
        </div>
        <div>
          <?php $faviconVal = $cfg['mini_logo']['value'] ?? ''; ?>
          <?php if ($faviconVal !== '' && file_exists(ROOT_PATH . '/' . $faviconVal)): ?>
          <div class="flex gap-sm mb-2" style="align-items:center">
            <img src="<?= e(assetUrl($faviconVal)) ?>" alt="Aktuelles Favicon"
                 style="max-height:48px;max-width:48px;object-fit:contain;background:var(--surface);border-radius:6px;padding:4px">
            <span class="text-dim text-xs">Aktuelles Favicon</span>
          </div>
          <?php else: ?>
          <div class="text-dim text-sm mb-2">Kein Favicon hochgeladen — Browser-Standard wird genutzt.</div>
          <?php endif; ?>
          <div class="flex gap-xs" style="align-items:center;flex-wrap:wrap">
            <input type="file" id="favicon-file" accept="image/png"
                   class="form-input" style="flex:1;min-width:0">
            <button type="button" id="favicon-upload-btn" class="btn btn--primary btn--sm"
                    onclick="uploadFavicon()">Hochladen</button>
          </div>
          <div id="favicon-upload-result" class="mt-1"></div>
        </div>
      </div>
    </div>

    <!-- ── System ─────────────────────────────────────────── -->
    <div class="card animate-in mb-2" style="animation-delay:.12s">
      <div class="section-title">⚙️ System</div>

      <div class="settings-row" style="padding:.6rem 0">
        <div>
          <span class="settings-row__name"><?= e($cfg['session_lifetime']['label'] ?? 'Session-Dauer') ?></span>
          <div class="text-dim text-xs mt-1"><?= e($cfg['session_lifetime']['description'] ?? '') ?></div>
        </div>
        <div>
          <input class="form-input" type="number" name="session_lifetime"
                 id="sess-input"
                 value="<?= (int)$cfg['session_lifetime']['value'] ?>"
                 min="300" max="31536000" style="width:130px"
                 oninput="updateSessLabel()">
          <div class="text-dim text-xs mt-1" id="sess-label"></div>
        </div>
      </div>

      <div class="settings-row" style="padding:.6rem 0">
        <div>
          <span class="settings-row__name"><?= e($cfg['default_role_icon']['label'] ?? 'Standard-Rollen-Icon') ?></span>
          <div class="text-dim text-xs mt-1"><?= e($cfg['default_role_icon']['description'] ?? '') ?></div>
        </div>
        <div style="max-width:300px;width:100%">
          <input class="form-input" type="text" name="default_role_icon"
                 value="<?= e($cfg['default_role_icon']['value']) ?>"
                 maxlength="255">
        </div>
      </div>
    </div>

    <!-- ── Texte ─────────────────────────────────────────── -->
    <div class="card animate-in mb-2" style="animation-delay:.14s">
      <div class="section-title">📝 Texte</div>

      <div class="settings-row" style="padding:.6rem 0">
        <div>
          <span class="settings-row__name"><?= e($cfg['deaths_empty_title']['label'] ?? 'Todesliste: Leertitel') ?></span>
          <div class="text-dim text-xs mt-1"><?= e($cfg['deaths_empty_title']['description'] ?? '') ?></div>
        </div>
        <div style="max-width:300px;width:100%">
          <input class="form-input" type="text" name="deaths_empty_title"
                 value="<?= e($cfg['deaths_empty_title']['value']) ?>"
                 maxlength="255">
        </div>
      </div>

      <div class="settings-row" style="padding:.6rem 0">
        <div>
          <span class="settings-row__name"><?= e($cfg['deaths_empty_sub']['label'] ?? 'Todesliste: Leer-Subtext') ?></span>
          <div class="text-dim text-xs mt-1"><?= e($cfg['deaths_empty_sub']['description'] ?? '') ?></div>
        </div>
        <div style="max-width:300px;width:100%">
          <input class="form-input" type="text" name="deaths_empty_sub"
                 value="<?= e($cfg['deaths_empty_sub']['value']) ?>"
                 maxlength="255">
        </div>
      </div>

      <div class="settings-row" style="padding:.6rem 0">
        <div>
          <span class="settings-row__name"><?= e($cfg['deaths_peace_text']['label'] ?? 'Todesliste: Friedenstext') ?></span>
          <div class="text-dim text-xs mt-1"><?= e($cfg['deaths_peace_text']['description'] ?? '') ?></div>
        </div>
        <div style="max-width:300px;width:100%">
          <input class="form-input" type="text" name="deaths_peace_text"
                 value="<?= e($cfg['deaths_peace_text']['value']) ?>"
                 maxlength="255">
        </div>
      </div>
    </div>

    <!-- ── Speichern ──────────────────────────────────────── -->
    <div class="flex gap-sm" style="justify-content:flex-end;margin-bottom:2rem">
      <button type="submit" class="btn btn--primary btn--lg" id="save-btn">
        💾 Einstellungen speichern
      </button>
    </div>

  </form>

  <!-- Hinweis: Was in config.php bleibt -->
  <div class="card" style="opacity:.7;margin-bottom:2rem">
    <div class="section-title text-sm">📁 Nur in config/config.php änderbar</div>
    <div style="display:flex;flex-direction:column;gap:.35rem;font-size:.84rem">
      <?php
      $fileOnly = [
          'DB_HOST / DB_PORT / DB_NAME / DB_USER / DB_PASS' => 'Datenbankzugangsdaten',
          'SETUP_PASSWORD'                                   => 'Passwort für den Setup-Assistenten',
          'APP_URL'                                          => 'Basis-URL (leer bei Root-Installation)',
          'SESSION_NAME'                                     => 'Name des Session-Cookies',
      ];
      foreach ($fileOnly as $key => $desc): ?>
      <div class="panel" style="padding:.45rem .9rem">
        <code class="text-accent" style="display:block;font-size:.8rem;margin-bottom:.1rem"><?= e($key) ?></code>
        <span class="text-dim" style="font-size:.82rem"><?= e($desc) ?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

</div>

<?php
$page['inline_js'] = sprintf(
    'const SETTINGS_API = %s; const LOGO_UPLOAD_API = %s; const FAVICON_UPLOAD_API = %s;',
    json_encode(APP_URL . '/admin/settings.php'),
    json_encode(APP_URL . '/api/upload_logo.php'),
    json_encode(APP_URL . '/api/upload_favicon.php')
);
$page['inline_js'] .= <<<'JS'

// Session-Dauer lesbar darstellen
function updateSessLabel() {
  const secs = parseInt(document.getElementById('sess-input').value) || 0;
  const d = Math.floor(secs / 86400);
  const h = Math.floor((secs % 86400) / 3600);
  const m = Math.floor((secs % 3600) / 60);
  const parts = [];
  if (d) parts.push(d + ' Tag' + (d !== 1 ? 'e' : ''));
  if (h) parts.push(h + ' Std.');
  if (m) parts.push(m + ' Min.');
  document.getElementById('sess-label').textContent = parts.length ? '= ' + parts.join(' ') : '';
}
updateSessLabel();

// Theme-Radio-Buttons über Icon-Klick bedienen
function selectTheme(key) {
  document.querySelectorAll('input[name="default_theme"]').forEach(r => r.checked = r.value === key);
  updateThemePreview();
}
function updateThemePreview() {
  const val = document.querySelector('input[name="default_theme"]:checked')?.value;
  document.querySelectorAll('[id^="theme-btn-"]').forEach(b => b.classList.remove('active'));
  if (val) document.getElementById('theme-btn-' + val)?.classList.add('active');
}

// Einstellungen speichern
async function saveSettings(e) {
  e.preventDefault();
  const btn = document.getElementById('save-btn');
  const res = document.getElementById('save-result');
  btn.disabled = true;
  btn.textContent = 'Speichern…';
  res.style.display = 'none';

  const form = document.getElementById('settings-form');
  const data = {};

  form.querySelectorAll('input[name], select[name], textarea[name]').forEach(el => {
    if (el.type === 'checkbox')  data[el.name] = el.checked;
    else if (el.type === 'radio') { if (el.checked) data[el.name] = el.value; }
    else data[el.name] = el.value;
  });

  try {
    const r = await fetch(SETTINGS_API + '?action=save', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify(data),
    });
    const d = await r.json();
    if (d.ok) {
      res.innerHTML = `<div class="alert alert--success">✓ ${escHtml(d.message || 'Gespeichert.')}</div>`;
    } else {
      const errList = d.errors
        ? Object.values(d.errors).map(e => `<li>${escHtml(e)}</li>`).join('')
        : 'Unbekannter Fehler.';
      res.innerHTML = `<div class="alert alert--error"><ul style="margin:0;padding-left:1.2rem">${errList}</ul></div>`;
    }
    res.style.display = '';
    res.scrollIntoView({behavior:'smooth', block:'nearest'});
  } catch(err) {
    res.innerHTML = `<div class="alert alert--error">Netzwerkfehler: ${escHtml(err.message)}</div>`;
    res.style.display = '';
  }

  btn.disabled = false;
  btn.textContent = '💾 Einstellungen speichern';
}

async function uploadLogo() {
  const input = document.getElementById('logo-file');
  const btn   = document.getElementById('logo-upload-btn');
  const res   = document.getElementById('logo-upload-result');
  if (!input.files.length) { showToast('Keine Datei ausgewählt.', 'error'); return; }
  const fd = new FormData();
  fd.append('logo', input.files[0]);
  btn.disabled = true;
  btn.textContent = 'Lädt…';
  res.innerHTML = '';
  try {
    const r = await fetch(LOGO_UPLOAD_API, {method: 'POST', body: fd});
    const d = await r.json();
    if (d.ok) {
      res.innerHTML = `<div class="alert alert--success">✓ ${escHtml(d.message || 'Logo hochgeladen.')}</div>`;
      setTimeout(() => location.reload(), 1000);
    } else {
      res.innerHTML = `<div class="alert alert--error">${escHtml(d.error || 'Fehler beim Upload.')}</div>`;
    }
  } catch (err) {
    res.innerHTML = `<div class="alert alert--error">Netzwerkfehler: ${escHtml(err.message)}</div>`;
  }
  btn.disabled = false;
  btn.textContent = 'Hochladen';
}

async function uploadFavicon() {
  const input = document.getElementById('favicon-file');
  const btn   = document.getElementById('favicon-upload-btn');
  const res   = document.getElementById('favicon-upload-result');
  if (!input.files.length) { showToast('Keine Datei ausgewählt.', 'error'); return; }
  const fd = new FormData();
  fd.append('favicon', input.files[0]);
  btn.disabled = true;
  btn.textContent = 'Lädt…';
  res.innerHTML = '';
  try {
    const r = await fetch(FAVICON_UPLOAD_API, {method: 'POST', body: fd});
    const d = await r.json();
    if (d.ok) {
      res.innerHTML = `<div class="alert alert--success">✓ ${escHtml(d.message || 'Favicon hochgeladen.')}</div>`;
      setTimeout(() => location.reload(), 1000);
    } else {
      res.innerHTML = `<div class="alert alert--error">${escHtml(d.error || 'Fehler beim Upload.')}</div>`;
    }
  } catch (err) {
    res.innerHTML = `<div class="alert alert--error">Netzwerkfehler: ${escHtml(err.message)}</div>`;
  }
  btn.disabled = false;
  btn.textContent = 'Hochladen';
}

// ── Push / VAPID ──────────────────────────────────────────────
async function vapidGenerate(force) {
  if (force && !confirm('VAPID-Schlüssel wirklich neu generieren?\nAlle Spieler müssen Push-Benachrichtigungen danach neu aktivieren!')) return;
  const res = document.getElementById('vapid-result');
  res.innerHTML = '<span class="text-dim text-xs">Generiere…</span>';
  try {
    const r = await fetch(SETTINGS_API + '?action=vapid_generate', {
      method: 'POST', headers: {'Content-Type':'application/json'},
      body: JSON.stringify({force: !!force}),
    });
    const d = await r.json();
    if (d.ok) {
      res.innerHTML = `<div class="alert alert--success text-xs">✓ ${escHtml(d.message)}</div>`;
      const disp = document.getElementById('vapid-key-display');
      if (disp) disp.textContent = d.key;
      else setTimeout(() => location.reload(), 1000);
    } else {
      res.innerHTML = `<div class="alert alert--error text-xs">${escHtml(d.error || 'Fehler')}</div>`;
    }
  } catch(e) {
    res.innerHTML = `<div class="alert alert--error text-xs">Netzwerkfehler</div>`;
  }
}

async function deleteSub(id) {
  const row = document.getElementById('sub-row-' + id);
  try {
    const r = await fetch(SETTINGS_API + '?action=delete_sub', {
      method: 'POST', headers: {'Content-Type':'application/json'},
      body: JSON.stringify({id}),
    });
    const d = await r.json();
    if (d.ok) {
      row?.remove();
      const cnt = document.getElementById('subs-count');
      if (cnt) cnt.textContent = parseInt(cnt.textContent || '1') - 1;
    }
  } catch(e) {}
}

async function clearAllSubs() {
  if (!confirm('Alle Geräte-Abonnements löschen?')) return;
  try {
    const r = await fetch(SETTINGS_API + '?action=clear_subs', {
      method: 'POST', headers: {'Content-Type':'application/json'}, body: '{}',
    });
    const d = await r.json();
    if (d.ok) {
      showToast(d.message, 'success');
      setTimeout(() => location.reload(), 700);
    }
  } catch(e) {}
}

async function removeLogo() {
  if (!confirm('Logo entfernen?')) return;
  try {
    const r = await fetch(SETTINGS_API + '?action=save', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({login_logo: ''}),
    });
    const d = await r.json();
    if (d.ok) { showToast('Logo entfernt.', 'success'); setTimeout(() => location.reload(), 700); }
    else showToast(d.error || 'Fehler', 'error');
  } catch (err) {
    showToast('Netzwerkfehler', 'error');
  }
}
JS;
require TEMPLATE_PATH . '/base_end.php';
?>
