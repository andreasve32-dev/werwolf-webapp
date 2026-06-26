<?php
// Copyright (c) 2026 Andreas Vetter
/**
 * ============================================================
 *  WERWOLF — Theme-System
 * ============================================================
 *  Jedes Theme definiert:
 *    - label:    Anzeigename
 *    - icon:     Emoji
 *    - css_file: CSS-Datei unter /assets/css/themes/
 *    - body_class: CSS-Klasse auf <body>
 *    - preview:  Farbe für den Theme-Switcher-Knopf
 * ============================================================
 */

define('THEMES', [

    'gothic' => [
        'label'      => 'Gothic',
        'icon'       => '🌑',
        'css_file'   => 'gothic.css',
        'body_class' => 'theme-gothic',
        'preview'    => '#6b21a8',
        'desc'       => 'Düster, mystisch, Mondlicht',
    ],

    'vista' => [
        'label'      => 'Vista',
        'icon'       => '💠',
        'css_file'   => 'vista.css',
        'body_class' => 'theme-vista',
        'preview'    => '#2563eb',
        'desc'       => 'Blau, Glasmorphism, Windows-Aero',
    ],

    'medieval' => [
        'label'      => 'Mittelalter',
        'icon'       => '🏰',
        'css_file'   => 'medieval.css',
        'body_class' => 'theme-medieval',
        'preview'    => '#78350f',
        'desc'       => 'Pergament, Holz, Dorf-Ästhetik',
    ],

    'minimal' => [
        'label'      => 'Minimal',
        'icon'       => '◻',
        'css_file'   => 'minimal.css',
        'body_class' => 'theme-minimal',
        'preview'    => '#18181b',
        'desc'       => 'Schwarz-Weiß, clean, funktional',
    ],

    'crystal' => [
        'label'      => 'Crystal',
        'icon'       => '💎',
        'css_file'   => 'crystal.css',
        'body_class' => 'theme-crystal',
        'preview'    => '#1ecec6',
        'desc'       => 'Final Fantasy VII / VIII / IX — JRPG',
    ],

]);

/**
 * Aktives Theme ermitteln:
 * Priorität: GET-Parameter > Cookie > DEFAULT_THEME
 */
function getActiveTheme(): string {
    $allowed = array_keys(THEMES);

    // 1. GET-Parameter (für direkten Link)
    if (!empty($_GET['theme']) && in_array($_GET['theme'], $allowed, true)) {
        $theme = $_GET['theme'];
        setcookie('ww_theme', $theme, time() + 86400 * 365, '/', '', false, false);
        return $theme;
    }

    // 2. Cookie
    if (!empty($_COOKIE['ww_theme']) && in_array($_COOKIE['ww_theme'], $allowed, true)) {
        return $_COOKIE['ww_theme'];
    }

    // 3. Standard aus config.php
    return defined('DEFAULT_THEME') ? DEFAULT_THEME : 'gothic';
}
