<?php
// Copyright (c) 2026 Andreas Vetter
// Testspieler-AJAX-Endpunkt — die Verwaltungsoberfläche liegt seit v0.27 im
// Debug-Menü (admin/debug.php), diese Datei bedient nur noch die drei Aktionen.
require_once dirname(__DIR__) . '/core/bootstrap.php';
require_once TEMPLATE_PATH . '/testplayers_blocks.php';
Auth::requireAdmin();
requireSameOrigin();

// ── AJAX: Testspieler anlegen ─────────────────────────────────
if (($_GET['action'] ?? '') === 'create') {
    header('Content-Type: application/json');
    if (!APP_DEBUG) { echo json_encode(['ok' => false, 'error' => 'Nur im Debug-Modus verfügbar.']); exit; }
    $count = max(1, min(TEST_MAX, (int)($_POST['count'] ?? 1)));
    $hash  = hashPassword(TEST_PASSWORD);
    $created = 0;
    $skipped = 0;
    $rows    = [];
    // Kumulierend: vorhandene Nummern überspringen und die nächsten freien
    // belegen, bis $count NEUE Spieler angelegt sind (oder TEST_MAX erreicht ist).
    for ($i = 1; $i <= TEST_MAX && $created < $count; $i++) {
        $username     = TEST_PREFIX . str_pad($i, 2, '0', STR_PAD_LEFT);
        $display_name = TEST_DISPLAY_PX . str_pad($i, 2, '0', STR_PAD_LEFT);
        $exists = Database::queryOne(
            'SELECT id FROM players WHERE username = ? OR display_name = ?',
            [$username, $display_name]
        );
        if ($exists) { $skipped++; continue; }
        Database::execute(
            'INSERT INTO players (username, display_name, password_hash, is_admin) VALUES (?,?,?,0)',
            [$username, $display_name, $hash]
        );
        $rows[] = ['id' => Database::lastId(), 'username' => $username, 'display_name' => $display_name];
        $created++;
    }
    echo json_encode(['ok' => true, 'created' => $created, 'skipped' => $skipped, 'rows' => $rows]);
    exit;
}

// ── AJAX: Einzelnen Testspieler löschen ──────────────────────
if (($_GET['action'] ?? '') === 'delete') {
    header('Content-Type: application/json');
    if (!APP_DEBUG) { echo json_encode(['ok' => false, 'error' => 'Nur im Debug-Modus verfügbar.']); exit; }
    $pid = (int)($_POST['player_id'] ?? 0);
    if (!$pid) { echo json_encode(['ok' => false, 'error' => 'Ungültige ID.']); exit; }
    $p = Database::queryOne('SELECT id, username FROM players WHERE id = ?', [$pid]);
    if (!$p || !isTestPlayer($p)) {
        echo json_encode(['ok' => false, 'error' => 'Kein Testspieler.']); exit;
    }
    Database::execute('DELETE FROM players WHERE id = ?', [$pid]);
    echo json_encode(['ok' => true]);
    exit;
}

// ── AJAX: Alle Testspieler löschen ────────────────────────────
if (($_GET['action'] ?? '') === 'delete_all') {
    header('Content-Type: application/json');
    if (!APP_DEBUG) { echo json_encode(['ok' => false, 'error' => 'Nur im Debug-Modus verfügbar.']); exit; }
    Database::execute("DELETE FROM players WHERE username REGEXP '^test_[0-9]{2}$'");
    echo json_encode(['ok' => true]);
    exit;
}

// Direkter Seitenaufruf ohne Aktion (z.B. alter Bookmark) — Oberfläche liegt jetzt im Debug-Menü
header('Location: ' . APP_URL . '/admin/debug.php');
exit;
