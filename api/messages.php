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
// Aktion kann auch per Multipart-POST (send_voice) oder GET (voice_file) kommen —
// beide transportieren kein JSON.
$action = $body['action'] ?? $_POST['action'] ?? $_GET['action'] ?? '';
$player = Auth::player();

/** Lädt eine Nachricht inkl. Spielername für render_message_row(). */
function fetchMessageRow(int $id): array {
    return Database::queryOne(
        "SELECT m.id, m.message, m.faq_question, m.voice_path, m.reply, m.created_at, m.replied_at,
                m.read_by_player, m.published, p.display_name, p.username
         FROM messages m JOIN players p ON p.id = m.player_id
         WHERE m.id = ?",
        [$id]
    );
}

// Sprachnachrichten: einzige Quelle der Wahrheit ist die Ziel-Endung (VOICE_EXT_MIME,
// auch für die Auslieferung in voice_file genutzt). finfo liefert je nach Browser einen
// abweichenden MIME-Typ für denselben Container (Chrome: WebM-Audio als video/webm,
// Safari: MP4-Audio als video/mp4) — solche Aliase landen nur in VOICE_MIME_ALIASES und
// werden beim Erkennen auf dieselbe Endung wie oben abgebildet, nie separat gepflegt.
const VOICE_MAX_BYTES = 3 * 1024 * 1024; // 3 MB (~1 Min. reicht locker)
const VOICE_EXT_MIME  = [
    'webm' => 'audio/webm', 'm4a' => 'audio/mp4', 'ogg' => 'audio/ogg',
    'mp3'  => 'audio/mpeg', 'wav' => 'audio/wav',
];
const VOICE_MIME_ALIASES = [
    'video/webm'      => 'webm',
    'video/mp4'       => 'm4a',
    'audio/x-m4a'     => 'm4a',
    'application/ogg' => 'ogg',
    'audio/x-wav'     => 'wav',
];
// Erkennungs-Map (mime → ext) aus VOICE_EXT_MIME abgeleitet statt separat gepflegt.
$voiceMimeToExt = array_flip(VOICE_EXT_MIME) + VOICE_MIME_ALIASES;

/**
 * Schickt eine Sprachnachricht an die OpenAI-Transkriptions-API und liefert den
 * erkannten Text zurück (oder false bei Netzwerk-/HTTP-/Key-Fehler — Details landen
 * im Error-Log, nie in der Client-Antwort, um den API-Key nicht versehentlich über
 * Fehlermeldungen preiszugeben). Modell gpt-4o-mini-transcribe: bei kurzen
 * Spielerfragen (15–30s) nur Bruchteile eines Cents pro Nachricht.
 */
function transcribeVoiceFile(string $absPath, string $ext, string $apiKey): string|false {
    $mime = VOICE_EXT_MIME[$ext] ?? 'application/octet-stream';
    $ch = curl_init('https://api.openai.com/v1/audio/transcriptions');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => [
            'file'  => new CURLFile($absPath, $mime, 'aufnahme.' . $ext),
            'model' => 'gpt-4o-mini-transcribe',
        ],
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $apiKey],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($err !== '' || $code !== 200) {
        logEvent('ERROR', '[OpenAI Transcribe] HTTP ' . $code . ' ' . $err . ' — ' . substr((string)$res, 0, 300));
        return false;
    }
    $text = trim(json_decode($res, true)['text'] ?? '');
    return $text !== '' ? $text : false;
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

    case 'send_voice':
        // Multipart-POST: action + Audio-Blob im Feld "voice"
        if (!VOICE_MESSAGES) jsonError('Sprachnachrichten sind deaktiviert.');
        if (empty($_FILES['voice']) || $_FILES['voice']['error'] !== UPLOAD_ERR_OK) {
            jsonError('Keine Aufnahme empfangen — bitte erneut versuchen.');
        }
        $file = $_FILES['voice'];
        if ($file['size'] > VOICE_MAX_BYTES) jsonError('Aufnahme zu groß (max. 3 MB).');
        if ($file['size'] < 200)             jsonError('Aufnahme ist leer.');
        // MIME über den Dateiinhalt prüfen — nie der Browser-Angabe vertrauen
        $mime = detectMimeType($file['tmp_name']);
        if (!isset($voiceMimeToExt[$mime])) jsonError('Ungültiges Audioformat (' . $mime . ').');
        $ext = $voiceMimeToExt[$mime];
        $dirAbs = ensureUploadDir('uploads/voice');
        if (!$dirAbs) jsonError('Upload-Verzeichnis konnte nicht angelegt werden.');
        $name = 'msg_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $dirAbs . '/' . $name)) {
            jsonError('Speichern der Aufnahme fehlgeschlagen.');
        }
        $game = currentGame();
        $id = Database::execute(
            "INSERT INTO messages (game_id, player_id, message, voice_path) VALUES (?, ?, ?, ?)",
            [$game['id'] ?? null, $player['id'], '🎙️ Sprachnachricht', 'uploads/voice/' . $name]
        );
        jsonOk('Sprachnachricht gesendet.', ['id' => $id]);

    case 'voice_file':
        // Audio-Auslieferung mit Auth: nur Admin oder der Absender selbst.
        // Der uploads/-Ordner ist per .htaccess gesperrt — dies ist der einzige Weg.
        $mid = (int)($_GET['id'] ?? 0);
        $row = $mid ? Database::queryOne("SELECT player_id, voice_path FROM messages WHERE id = ?", [$mid]) : null;
        if (!$row || !$row['voice_path']) { http_response_code(404); exit('Nicht gefunden'); }
        if (!$player['is_admin'] && (int)$row['player_id'] !== (int)$player['id']) {
            http_response_code(403); exit('Kein Zugriff');
        }
        $abs = ROOT_PATH . '/' . $row['voice_path'];
        $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
        if (!is_file($abs) || !isset(VOICE_EXT_MIME[$ext])) { http_response_code(404); exit('Datei fehlt'); }
        header('Content-Type: ' . VOICE_EXT_MIME[$ext]);
        header('Content-Length: ' . filesize($abs));
        header('Cache-Control: private, max-age=3600');
        readfile($abs);
        exit;

    case 'transcribe_voice':
        if (!$player['is_admin']) jsonError('Kein Zugriff.', 403);
        if (!VOICE_TRANSCRIPTION) jsonError('Transkription ist deaktiviert (Einstellungen → Sprachnachrichten).');
        $mid = (int)($body['id'] ?? 0);
        if (!$mid) jsonError('Ungültige ID.');
        $row = Database::queryOne("SELECT id, voice_path FROM messages WHERE id = ?", [$mid]);
        if (!$row || !$row['voice_path']) jsonError('Keine Sprachnachricht gefunden.', 404);
        $abs = ROOT_PATH . '/' . $row['voice_path'];
        $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
        if (!is_file($abs)) jsonError('Audio-Datei fehlt auf dem Server.', 404);
        $apiKey = trim(Database::queryOne("SELECT value FROM settings WHERE `key`='openai_api_key'")['value'] ?? '');
        if ($apiKey === '') jsonError('Kein OpenAI-API-Key hinterlegt (Einstellungen → Sprachnachrichten).');
        $text = transcribeVoiceFile($abs, $ext, $apiKey);
        if ($text === false) jsonError('Transkription fehlgeschlagen — bitte später erneut versuchen oder API-Key prüfen.');
        Database::execute("UPDATE messages SET faq_question = ? WHERE id = ?", [$text, $mid]);
        require_once TEMPLATE_PATH . '/messages_blocks.php';
        jsonOk('Transkribiert — bitte gegenlesen, anonymisieren und dann veröffentlichen.',
            ['html' => render_message_row(fetchMessageRow($mid))]);

    case 'get_my':
        $msgs = Database::query(
            "SELECT id, message, voice_path, reply, created_at, replied_at, read_by_player
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
        $row = Database::queryOne("SELECT id, published, reply, voice_path, faq_question FROM messages WHERE id = ?", [$mid]);
        if (!$row)          jsonError('Nachricht nicht gefunden.', 404);
        if (!$row['reply']) jsonError('Nur beantwortete Nachrichten können veröffentlicht werden.');
        // Die Audiodatei selbst wird nie öffentlich — bei Sprachnachrichten muss vorher
        // über "FAQ-Text" eine vom Spielleiter geschriebene, anonymisierte Textfassung
        // hinterlegt sein (sonst würde nur der Platzhaltertext veröffentlicht).
        if ($row['voice_path'] && !$row['faq_question']) {
            jsonError('Bitte zuerst über „✏️ FAQ-Text" eine anonymisierte Textfassung hinterlegen.');
        }
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
            "SELECT m.id, m.message, m.faq_question, m.voice_path, m.reply, m.created_at, m.replied_at, m.read_by_player, m.published,
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
        // Zugehörige Sprachaufnahme mit entfernen (Datei liegt außerhalb der DB)
        $vrow = Database::queryOne("SELECT voice_path FROM messages WHERE id = ?", [$mid]);
        if ($vrow && $vrow['voice_path'] && str_starts_with($vrow['voice_path'], 'uploads/voice/')) {
            @unlink(ROOT_PATH . '/' . $vrow['voice_path']);
        }
        Database::execute("DELETE FROM messages WHERE id = ?", [$mid]);
        // Sicherheitsnetz: falls durch parallele Vorgänge etwas verwaist ist, mit aufräumen
        cleanupOrphanedVoiceFiles();
        jsonOk('Gelöscht.');

    case 'cleanup_voice':
        // Manuelle Aufräumfunktion: alle verwaisten Sprachaufnahmen entfernen.
        if (!$player['is_admin']) jsonError('Kein Zugriff.', 403);
        $n = cleanupOrphanedVoiceFiles();
        jsonOk($n > 0 ? "🧹 {$n} verwaiste Aufnahme(n) gelöscht." : 'Keine verwaisten Aufnahmen gefunden.', ['deleted' => $n]);

    default:
        jsonError('Unbekannte Aktion.', 400);
}
