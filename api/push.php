<?php
// Copyright (c) 2026 Andreas Vetter
// Web-Push-API: VAPID-Schlüssel abrufen, Push-Abonnements speichern und entfernen.
// Wird vom Browser nach der Bestätigung der Push-Benachrichtigungen aufgerufen.
require_once dirname(__DIR__) . '/core/bootstrap.php';
require_once CORE_PATH . '/WebPush.php';
Auth::requireLogin();

$body   = jsonBody();
$action = $body['action'] ?? '';
$player = Auth::player();

switch ($action) {

    case 'public_key':
        WebPush::ensureKeys();
        jsonResponse(['key' => WebPush::getPublicKey()]);

    case 'subscribe':
        $endpoint = trim($body['endpoint'] ?? '');
        $p256dh   = trim($body['p256dh']   ?? '');
        $auth     = trim($body['auth']      ?? '');
        if (!$endpoint) jsonError('Endpoint fehlt.');
        Database::execute(
            "INSERT INTO push_subscriptions (player_id, endpoint, p256dh, auth)
             VALUES (?, ?, ?, ?) AS new_row
             ON DUPLICATE KEY UPDATE p256dh = new_row.p256dh, auth = new_row.auth",
            [$player['id'], $endpoint, $p256dh ?: null, $auth ?: null]
        );
        jsonOk('Abonniert.');

    case 'unsubscribe':
        $endpoint = trim($body['endpoint'] ?? '');
        if ($endpoint) {
            Database::execute(
                "DELETE FROM push_subscriptions WHERE player_id = ? AND endpoint = ?",
                [$player['id'], $endpoint]
            );
        }
        jsonOk('Abgemeldet.');

    default:
        jsonError('Unbekannte Aktion.', 400);
}
