<?php
// Copyright (c) 2026 Andreas Vetter
// Nachrichten-API: Spieler senden Fragen an den Spielleiter, Admin antwortet.
// Veröffentlichte Antworten landen im öffentlichen FAQ (app/faq.php).
require_once dirname(__DIR__) . '/core/bootstrap.php';
Auth::requireLogin();

// Session-Lock freigeben: kein Endpunkt hier schreibt $_SESSION, aber ohne
// write_close serialisiert PHP alle parallelen Poll-Requests desselben Nutzers.
session_write_close();

$body   = jsonBody();
$action = $body['action'] ?? '';
$player = Auth::player();

switch ($action) {

    case 'send':
        $msg = trim($body['message'] ?? '');
        if (mb_strlen($msg) < 3)   jsonError('Nachricht zu kurz (min. 3 Zeichen).');
        if (mb_strlen($msg) > 500) jsonError('Nachricht zu lang (max. 500 Zeichen).');
        $game = currentGame();
        $id = Database::execute(
            "INSERT INTO messages (game_id, player_id, message) VALUES (?, ?, ?)",
            [$game['id'] ?? null, $player['id'], $msg]
        );
        jsonOk('Nachricht gesendet.', ['id' => $id]);

    case 'get_my':
        $msgs = Database::query(
            "SELECT id, message, reply, created_at, replied_at, read_by_player
             FROM messages WHERE player_id = ? ORDER BY created_at DESC",
            [$player['id']]
        );
        // Ungelesene Antworten als gelesen markieren
        Database::execute(
            "UPDATE messages SET read_by_player = 1
             WHERE player_id = ? AND reply IS NOT NULL AND read_by_player = 0",
            [$player['id']]
        );
        jsonResponse(['messages' => $msgs]);

    case 'unread_count':
        $row = Database::queryOne(
            "SELECT COUNT(*) AS cnt FROM messages
             WHERE player_id = ? AND reply IS NOT NULL AND read_by_player = 0",
            [$player['id']]
        );
        jsonResponse(['unread' => (int)($row['cnt'] ?? 0)]);

    case 'reply':
        if (!$player['is_admin']) jsonError('Kein Zugriff.', 403);
        $mid   = (int)($body['id'] ?? 0);
        $reply = trim($body['reply'] ?? '');
        if (!$mid)                    jsonError('Ungültige ID.');
        if (mb_strlen($reply) < 1)    jsonError('Antwort darf nicht leer sein.');
        if (mb_strlen($reply) > 1000) jsonError('Antwort zu lang (max. 1000 Zeichen).');
        $msgRow = Database::queryOne("SELECT id, player_id FROM messages WHERE id = ?", [$mid]);
        if (!$msgRow) jsonError('Nachricht nicht gefunden.', 404);
        Database::execute(
            "UPDATE messages SET reply = ?, replied_at = NOW(), read_by_player = 0 WHERE id = ?",
            [$reply, $mid]
        );
        require_once CORE_PATH . '/WebPush.php';
        WebPush::sendToPlayer((int)$msgRow['player_id']);
        jsonOk('Antwort gespeichert.');

    case 'toggle_publish':
        if (!$player['is_admin']) jsonError('Kein Zugriff.', 403);
        $mid = (int)($body['id'] ?? 0);
        if (!$mid) jsonError('Ungültige ID.');
        $row = Database::queryOne("SELECT id, published, reply FROM messages WHERE id = ?", [$mid]);
        if (!$row)          jsonError('Nachricht nicht gefunden.', 404);
        if (!$row['reply']) jsonError('Nur beantwortete Nachrichten können veröffentlicht werden.');
        $newState = $row['published'] ? 0 : 1;
        Database::execute("UPDATE messages SET published = ? WHERE id = ?", [$newState, $mid]);
        jsonOk($newState ? 'Im FAQ veröffentlicht.' : 'Aus FAQ entfernt.', ['published' => $newState]);

    case 'get_new_messages':
        if (!$player['is_admin']) jsonError('Kein Zugriff.', 403);
        require_once TEMPLATE_PATH . '/messages_blocks.php';
        $afterId = (int)($body['after_id'] ?? 0);
        $newMsgs = Database::query(
            "SELECT m.id, m.message, m.reply, m.created_at, m.replied_at, m.read_by_player, m.published,
                    p.display_name, p.username
             FROM messages m JOIN players p ON p.id = m.player_id
             WHERE m.id > ? ORDER BY m.created_at ASC",
            [$afterId]
        );
        $pendingRow = Database::queryOne("SELECT COUNT(*) AS cnt FROM messages WHERE reply IS NULL");
        jsonResponse([
            'rows'    => array_map(fn($m) => ['id' => (int)$m['id'], 'html' => render_message_row($m)], $newMsgs),
            'pending' => (int)($pendingRow['cnt'] ?? 0),
        ]);

    case 'pending_count':
        if (!$player['is_admin']) jsonError('Kein Zugriff.', 403);
        $row = Database::queryOne("SELECT COUNT(*) AS cnt FROM messages WHERE reply IS NULL");
        jsonResponse(['pending' => (int)($row['cnt'] ?? 0)]);

    case 'delete':
        if (!$player['is_admin']) jsonError('Kein Zugriff.', 403);
        $mid = (int)($body['id'] ?? 0);
        if (!$mid) jsonError('Ungültige ID.');
        Database::execute("DELETE FROM messages WHERE id = ?", [$mid]);
        jsonOk('Gelöscht.');

    default:
        jsonError('Unbekannte Aktion.', 400);
}
