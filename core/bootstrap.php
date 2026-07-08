<?php
// Copyright (c) 2026 Andreas Vetter
/**
 * ============================================================
 *  WERWOLF — bootstrap.php
 * ============================================================
 *  Wird auf JEDER Seite als erstes eingebunden.
 *  Lädt Konfiguration, Klassen, Hilfsfunktionen.
 *
 *  Verwendung:  require_once dirname(__DIR__) . '/core/bootstrap.php';
 * ============================================================
 */

// Fehler standardmäßig ausblenden (Produktion) — wird nach DB-Laden per APP_DEBUG gesteuert
error_reporting(0);
ini_set('display_errors', '0');
// PHP-Version nicht preisgeben
header_remove('X-Powered-By');

// 1. Konfiguration (DB-Zugangsdaten, Pfade, SESSION_NAME)
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/themes.php';

// 1a. HTTPS erzwingen (App-Ebene, Defense-in-Depth zusätzlich zu Apache-Redirect/HSTS).
//     Grund: die App ist ohne HTTPS unsicher UND mehrere Kernfunktionen brauchen einen
//     sicheren Kontext (Push, Sprachaufnahme via MediaRecorder, Service Worker). Wird die
//     App auf einem fremden Server nur über HTTP betrieben, blockiert sie hier bewusst.
//     Ausnahme: CLI (Skripte/Tests) und localhost (lokale Entwicklung).
if (PHP_SAPI !== 'cli') {
    $__fwd  = strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''); // hinter Reverse-Proxy
    $__https = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? '') == 443)
            || $__fwd === 'https';
    $__host = strtolower(preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? ''));
    $__local = in_array($__host, ['localhost', '127.0.0.1', '::1', '[::1]'], true);
    if (!$__https && !$__local) {
        http_response_code(403);
        header('Content-Type: text/html; charset=utf-8');
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        $__h = htmlspecialchars($_SERVER['HTTP_HOST'] ?? '', ENT_QUOTES, 'UTF-8');
        $__u = htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/', ENT_QUOTES, 'UTF-8');
        echo '<!doctype html><html lang="de"><meta charset="utf-8">'
           . '<meta name="viewport" content="width=device-width,initial-scale=1">'
           . '<title>HTTPS erforderlich</title>'
           . '<div style="font-family:system-ui,sans-serif;max-width:34rem;margin:12vh auto;padding:0 1.2rem;text-align:center">'
           . '<div style="font-size:3rem">🔒</div>'
           . '<h1 style="font-size:1.3rem;margin:.4rem 0">HTTPS erforderlich</h1>'
           . '<p style="color:#555;line-height:1.6">Diese App kann aus Sicherheitsgründen nur über eine '
           . 'verschlüsselte <strong>HTTPS</strong>-Verbindung genutzt werden. Login, Sprachaufnahme und '
           . 'Push-Benachrichtigungen funktionieren über HTTP nicht.</p>'
           . ($__h !== '' ? '<p><a href="https://' . $__h . $__u . '" style="color:#2563eb">→ Zur sicheren HTTPS-Version</a></p>' : '')
           . '</div></html>';
        exit;
    }
}

// 1b. Anwendungs-Log: alle error_log()-Aufrufe in eine Datei unter logs/
//     umleiten, damit sie in der Admin-Log-Ansicht (admin/logs.php)
//     durchsuchbar sind statt im Docker-stderr zu verschwinden. Der Ordner ist
//     per .htaccess gegen HTTP-Zugriff gesperrt (nur PHP liest/schreibt ihn).
define('LOG_PATH', ROOT_PATH . '/logs/app.log');
if (!is_dir(dirname(LOG_PATH))) @mkdir(dirname(LOG_PATH), 0775, true);
ini_set('error_log', LOG_PATH);
ini_set('log_errors', '1');

// Fatale Fehler (die die Ausführung abbrechen) als CRITICAL festhalten — sie
// werden sonst je nach error_reporting nicht automatisch geloggt. error_log()
// funktioniert unabhängig von display_errors/error_reporting.
register_shutdown_function(static function (): void {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        error_log('[CRITICAL] ' . $e['message'] . ' in ' . $e['file'] . ':' . $e['line']);
    }
});

// 2. Database-Klasse laden
require_once __DIR__ . '/Database.php';

// 3. Einstellungen aus DB laden und Konstanten definieren.
//    trySettings() gibt [] zurück wenn DB oder settings-Tabelle fehlen
//    (z. B. vor dem ersten Setup) → Fallback-Werte greifen.
$_cfg = Database::trySettings();
define('APP_NAME',           $_cfg['app_name']          ?? 'Werwolf');
define('APP_VERSION',        $_cfg['app_version']       ?? '0.0.1');
define('BETA_MODE',          filter_var($_cfg['beta_mode'] ?? '1', FILTER_VALIDATE_BOOLEAN));
define('APP_DEBUG',          filter_var($_cfg['app_debug'] ?? '1', FILTER_VALIDATE_BOOLEAN));
define('DEFAULT_THEME',      $_cfg['default_theme']     ?? 'gothic');
define('SESSION_LIFETIME',   (int)($_cfg['session_lifetime'] ?? 604800));
define('MIN_PLAYERS',        (int)($_cfg['min_players']      ?? 4));
define('MAX_PLAYERS',        (int)($_cfg['max_players']      ?? 30));
define('DEFAULT_ROLE_ICON',  $_cfg['default_role_icon'] ?? 'assets/icons/roles/_default.png');
define('LOGIN_TITLE',        $_cfg['login_title']       ?? 'Willkommen zurück');
define('LOGIN_SUBTITLE',     $_cfg['login_subtitle']    ?? 'Das Dorf schläft … doch die Wölfe nicht.');
define('REGISTER_SUBTITLE',  $_cfg['register_subtitle'] ?? 'Tritt dem Dorf bei');
define('DEATHS_EMPTY_TITLE', $_cfg['deaths_empty_title'] ?? 'Noch niemand gestorben');
define('DEATHS_EMPTY_SUB',   $_cfg['deaths_empty_sub']   ?? 'Das Dorf ist in Frieden … noch.');
define('DEATHS_PEACE_TEXT',  $_cfg['deaths_peace_text']  ?? 'Mögen sie in Frieden ruhen');
define('LOGIN_LOGO',         $_cfg['login_logo']         ?? '');
define('MINI_LOGO',          $_cfg['mini_logo']          ?? '');
define('ASSET_VERSION',      $_cfg['asset_version']      ?? '1');
define('GAME_TIMEZONE',      $_cfg['game_timezone']      ?? 'Europe/Berlin');
define('VOICE_MESSAGES',     filter_var($_cfg['voice_messages_enabled'] ?? '1', FILTER_VALIDATE_BOOLEAN));
define('VOICE_TRANSCRIPTION', filter_var($_cfg['voice_transcription_enabled'] ?? '0', FILTER_VALIDATE_BOOLEAN));
define('CLEAR_MESSAGES_ON_START', filter_var($_cfg['clear_messages_on_start'] ?? '0', FILTER_VALIDATE_BOOLEAN));
date_default_timezone_set(GAME_TIMEZONE);
unset($_cfg);

// Feste Spielregel, bewusst keine DB-Einstellung (siehe CLAUDE.md)
define('MIN_VOTES_TO_HANG', 2);

// 4. Fehlerreporting gemäß APP_DEBUG
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

// 5. Weitere Kern-Klassen und Hilfsfunktionen
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/helpers.php';

// 5b. Globaler Exception-Handler: JEDE nicht abgefangene Ausnahme (z.B. DB-Fehler
//     in irgendeiner Funktion) landet automatisch als ERROR im System-Log
//     (Admin → Debug → System-Log) und der Nutzer bekommt eine saubere Antwort —
//     ohne dass jede Funktion einzeln ein try/catch braucht.
set_exception_handler(function (\Throwable $e): void {
    logEvent('ERROR', 'Unbehandelte Ausnahme: ' . get_class($e) . ': ' . $e->getMessage()
        . ' in ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) http_response_code(500);
    $isApi = str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/')
          || (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest');
    if ($isApi) {
        if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => APP_DEBUG ? $e->getMessage() : 'Ein interner Fehler ist aufgetreten (protokolliert).']);
    } else {
        echo '<!doctype html><meta charset="utf-8"><div style="font-family:system-ui,sans-serif;max-width:34rem;margin:12vh auto;padding:0 1.2rem;text-align:center">'
           . '<div style="font-size:2.5rem">⚠️</div><h1 style="font-size:1.2rem">Ein Fehler ist aufgetreten</h1>'
           . '<p style="color:#555">Der Fehler wurde protokolliert und kann im Admin-Bereich eingesehen werden.</p>'
           . (APP_DEBUG ? '<pre style="text-align:left;white-space:pre-wrap;color:#b00">' . htmlspecialchars($e->getMessage()) . '</pre>' : '')
           . '</div>';
    }
    exit;
});

// 6. Session starten
Auth::start();
