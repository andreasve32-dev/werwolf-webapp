<?php
// Copyright (c) 2026 Andreas Vetter
// Nachrichten-API: Spieler senden Fragen an den Spielleiter, Admin antwortet.
// Veröffentlichte Antworten landen im öffentlichen FAQ (app/faq.php).
require_once dirname(__DIR__) . '/core/bootstrap.php';
Auth::requireLogin();
requireSameOrigin();

// Session-Lock freigeben: kein Endpunkt hier schreibt $_SESSION, aber ohne
// write_close serialisiert PHP alle parallelen Poll-Requests desselben Nutzers.
session_write_close();

$body   = jsonBody();
$action = $body['action'] ?? $_POST['action'] ?? $_GET['action'] ?? '';
$player = Auth::player();

/** Lädt eine Nachricht inkl. Spielername für render_message_row(). */
function fetchMessageRow(int $id): array {
    return Database::queryOne(
        "SELECT m.id, m.type, m.status, m.message, m.faq_question, m.reply, m.created_at, m.replied_at,
                m.read_by_player, m.published, p.display_name, p.username
         FROM messages m JOIN players p ON p.id = m.player_id
         WHERE m.id = ?",
        [$id]
    );
}

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

    case 'send_feedback':
        // Feedback-Eintrag (Bug / Wunsch / Feedback) von app/feedback.php —
        // landet in derselben messages-Tabelle, nur mit anderem Typ + Status.
        $type = $body['type'] ?? '';
        if (!in_array($type, ['bug', 'wish', 'feedback'], true)) jsonError('Ungültiger Typ.');
        $msg = trim($body['message'] ?? '');
        if (mb_strlen($msg) < 3)    jsonError('Beschreibung zu kurz (min. 3 Zeichen).');
        if (mb_strlen($msg) > 1000) jsonError('Beschreibung zu lang (max. 1000 Zeichen).');
        $game = currentGame();
        $id = Database::execute(
            "INSERT INTO messages (game_id, player_id, type, status, message) VALUES (?, ?, ?, 'open', ?)",
            [$game['id'] ?? null, $player['id'], $type, $msg]
        );
        jsonOk('Danke — dein Eintrag wurde gespeichert!', ['id' => $id]);

    case 'get_my_feedback':
        // Eigene Feedback-Einträge für app/feedback.php (inkl. Status + Admin-Antwort).
        $rows = Database::query(
            "SELECT id, type, status, message, reply, created_at, replied_at, read_by_admin
             FROM messages WHERE player_id = ? AND type != 'question'
             ORDER BY created_at DESC",
            [$player['id']]
        );
        // Beantwortete Feedback-Einträge gelten mit dem Ansehen als gelesen
        Database::execute(
            "UPDATE messages SET read_by_player = 1
             WHERE player_id = ? AND type != 'question' AND reply IS NOT NULL AND read_by_player = 0",
            [$player['id']]
        );
        jsonResponse(['entries' => $rows]);

    case 'set_status':
        // Admin setzt den Bearbeitungsstatus eines Feedback-Eintrags.
        if (!$player['is_admin']) jsonError('Kein Zugriff.', 403);
        $mid    = (int)($body['id'] ?? 0);
        $status = $body['status'] ?? '';
        if (!$mid) jsonError('Ungültige ID.');
        if (!isset(feedbackStatusMeta()[$status])) jsonError('Ungültiger Status.');
        $row = Database::queryOne("SELECT id, type FROM messages WHERE id = ?", [$mid]);
        if (!$row)                      jsonError('Eintrag nicht gefunden.', 404);
        if ($row['type'] === 'question') jsonError('Status gibt es nur für Feedback-Einträge.');
        Database::execute("UPDATE messages SET status = ? WHERE id = ?", [$status, $mid]);
        require_once TEMPLATE_PATH . '/messages_blocks.php';
        jsonOk('Status: ' . feedbackStatusMeta()[$status]['label'],
            ['status' => $status, 'html' => render_message_row(fetchMessageRow($mid))]);

    case 'feedback_token_generate':
        // Admin (neu) generiert das Token für die externe Feedback-API (api/feedback.php).
        if (!$player['is_admin']) jsonError('Kein Zugriff.', 403);
        $token = bin2hex(random_bytes(32));
        Database::execute(
            "INSERT INTO settings (`key`, value, type, label, description, sort_order)
             VALUES ('feedback_api_token', ?, 'string', 'Feedback-API-Token',
                     'Zugriffs-Token für die externe Feedback-API (leer = API deaktiviert).', 998)
             ON DUPLICATE KEY UPDATE value = VALUES(value)",
            [$token]
        );
        logEvent('NOTICE', 'Feedback-API-Token neu generiert (Admin: ' . $player['username'] . ').');
        jsonOk('Token generiert — jetzt kopieren, er wird nur einmal angezeigt.', ['token' => $token]);

    case 'feedback_token_clear':
        // Admin deaktiviert die externe Feedback-API (Token leeren).
        if (!$player['is_admin']) jsonError('Kein Zugriff.', 403);
        Database::execute("UPDATE settings SET value = '' WHERE `key` = 'feedback_api_token'");
        logEvent('NOTICE', 'Feedback-API-Token entfernt — externe API deaktiviert (Admin: ' . $player['username'] . ').');
        jsonOk('Token entfernt — die Feedback-API ist deaktiviert.');

    case 'get_my':
        $msgs = Database::query(
            "SELECT id, type, message, reply, created_at, replied_at, read_by_player
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
        require_once TEMPLATE_PATH . '/messages_blocks.php';
        jsonOk('Antwort gespeichert.', ['html' => render_message_row(fetchMessageRow($mid))]);

    case 'toggle_publish':
        if (!$player['is_admin']) jsonError('Kein Zugriff.', 403);
        $mid = (int)($body['id'] ?? 0);
        if (!$mid) jsonError('Ungültige ID.');
        $row = Database::queryOne("SELECT id, type, published, reply FROM messages WHERE id = ?", [$mid]);
        if (!$row)          jsonError('Nachricht nicht gefunden.', 404);
        if ($row['type'] !== 'question') jsonError('Nur Spielerfragen können im FAQ veröffentlicht werden.');
        if (!$row['reply']) jsonError('Nur beantwortete Nachrichten können veröffentlicht werden.');
        $newState = $row['published'] ? 0 : 1;
        Database::execute("UPDATE messages SET published = ? WHERE id = ?", [$newState, $mid]);
        require_once TEMPLATE_PATH . '/messages_blocks.php';
        jsonOk($newState ? 'Im FAQ veröffentlicht.' : 'Aus FAQ entfernt.',
            ['published' => $newState, 'html' => render_message_row(fetchMessageRow($mid))]);

    case 'set_faq_question':
        if (!$player['is_admin']) jsonError('Kein Zugriff.', 403);
        $mid  = (int)($body['id'] ?? 0);
        $text = trim($body['question'] ?? '');
        if (!$mid)                  jsonError('Ungültige ID.');
        if (mb_strlen($text) < 1)   jsonError('FAQ-Text darf nicht leer sein.');
        if (mb_strlen($text) > 500) jsonError('FAQ-Text zu lang (max. 500 Zeichen).');
        $row = Database::queryOne("SELECT id FROM messages WHERE id = ?", [$mid]);
        if (!$row) jsonError('Nachricht nicht gefunden.', 404);
        Database::execute("UPDATE messages SET faq_question = ? WHERE id = ?", [$text, $mid]);
        require_once TEMPLATE_PATH . '/messages_blocks.php';
        jsonOk('FAQ-Text gespeichert.', ['html' => render_message_row(fetchMessageRow($mid))]);

    case 'get_new_messages':
        if (!$player['is_admin']) jsonError('Kein Zugriff.', 403);
        require_once TEMPLATE_PATH . '/messages_blocks.php';
        $afterId = (int)($body['after_id'] ?? 0);
        $newMsgs = Database::query(
            "SELECT m.id, m.type, m.status, m.message, m.faq_question, m.reply, m.created_at, m.replied_at, m.read_by_player, m.published,
                    p.display_name, p.username
             FROM messages m JOIN players p ON p.id = m.player_id
             WHERE m.id > ? ORDER BY m.created_at ASC",
            [$afterId]
        );
        // Live nachgeladene Einträge gelten als vom Admin gesehen (er hat die Seite offen)
        if ($newMsgs) {
            $ids = array_map(fn($m) => (int)$m['id'], $newMsgs);
            Database::execute(
                "UPDATE messages SET read_by_admin = 1 WHERE read_by_admin = 0 AND id IN (" . implode(',', $ids) . ")"
            );
        }
        // "Unbeantwortet" zählt nur echte Spielerfragen — Feedback-Einträge laufen
        // über ihren eigenen Status (feedback_open) und brauchen keine Antwortpflicht.
        $pendingRow  = Database::queryOne("SELECT COUNT(*) AS cnt FROM messages WHERE reply IS NULL AND type = 'question'");
        $feedbackRow = Database::queryOne("SELECT COUNT(*) AS cnt FROM messages WHERE type != 'question' AND status = 'open'");
        jsonResponse([
            'rows'          => array_map(fn($m) => ['id' => (int)$m['id'], 'html' => render_message_row($m)], $newMsgs),
            'pending'       => (int)($pendingRow['cnt'] ?? 0),
            'feedback_open' => (int)($feedbackRow['cnt'] ?? 0),
        ]);

    case 'pending_count':
        if (!$player['is_admin']) jsonError('Kein Zugriff.', 403);
        $row = Database::queryOne("SELECT COUNT(*) AS cnt FROM messages WHERE reply IS NULL AND type = 'question'");
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
