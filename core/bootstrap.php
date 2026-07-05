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

// 6. Session starten
Auth::start();
