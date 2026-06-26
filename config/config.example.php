<?php
// Copyright (c) 2026 Andreas Vetter
/**
 * ============================================================
 *  WERWOLF — Haupt-Konfiguration (BEISPIELDATEI)
 * ============================================================
 *  Kopiere diese Datei als config.php und trage deine Werte ein.
 *  config.php ist in .gitignore — kommt NICHT ins Repository.
 * ============================================================
 */

// ── Datenbank ────────────────────────────────────────────────
define('DB_HOST',    'DB');           // ← Hostname / Docker-Service-Name
define('DB_PORT',    '3306');
define('DB_NAME',    'werwolf');
define('DB_USER',    'root');
define('DB_PASS',    'DEIN_PASSWORT_HIER');
define('DB_CHARSET', 'utf8mb4');

// ── Setup-Schutz ─────────────────────────────────────────────
// Passwort für /admin/setup.php — die Seite ist OHNE Login
// erreichbar (Datenbank existiert noch nicht), daher UNBEDINGT
// ein sicheres Passwort setzen! Leer ('') = kein Schutz.
define('SETUP_PASSWORD', 'SICHERES_SETUP_PASSWORT');

// ── App (feste Werte — nicht per DB überschreibbar) ─────────
define('APP_URL',     '');            // Basis-Pfad (ohne trailing slash)

// ── Session ──────────────────────────────────────────────────
define('SESSION_NAME', 'werwolf_session');

// ── Pfade ────────────────────────────────────────────────────
define('ROOT_PATH',      dirname(__DIR__));
define('CONFIG_PATH',    ROOT_PATH . '/config');
define('CORE_PATH',      ROOT_PATH . '/core');
define('TEMPLATE_PATH',  ROOT_PATH . '/templates');
define('ASSETS_URL',     APP_URL   . '/assets');
define('API_URL',        APP_URL   . '/api');
