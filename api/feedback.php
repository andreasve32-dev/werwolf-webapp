<?php
// Copyright (c) 2026 Andreas Vetter
// Feedback-API: token-gesicherte Schnittstelle für EXTERNE Clients (z.B. einen
// KI-Assistenten), um Feedback-Einträge (Bug/Wunsch/Feedback) auszulesen und
// deren Bearbeitungsstatus zu setzen. BEWUSST ohne Login — die Absicherung:
//   1. HTTPS ist app-weit Pflicht (bootstrap.php blockiert HTTP hart)
//   2. Zugriff nur mit dem Token aus settings.feedback_api_token
//      (leer = API komplett deaktiviert, Verwaltung: Admin → Spielerfragen & Feedback)
//   3. Vergleich über hash_equals (kein Timing-Leak) + Rate-Limit pro IP
//   4. Es werden NUR Feedback-Typen ausgeliefert — nie Spielerfragen
//      (die können Rollen-/Spielgeheimnisse enthalten).
require_once dirname(__DIR__) . '/core/bootstrap.php';

// Kein Session-Zugriff nötig — Lock sofort freigeben.
session_write_close();

/**
 * Simples Fixed-Window-Rate-Limit pro IP (Datei unter logs/, per .htaccess
 * HTTP-gesperrt). Bewusst ohne DB: schützt auch, wenn jemand die API mit
 * ungültigen Tokens bombardiert, ohne dafür Queries zu verbrennen.
 */
function feedbackApiRateLimit(string $ip, int $max, int $window = 60): bool {
    $file = ROOT_PATH . '/logs/feedback_api_rl.json';
    $now  = time();
    $data = is_file($file) ? (json_decode((string)@file_get_contents($file), true) ?: []) : [];
    // Abgelaufene Zeitstempel aller IPs ausmisten, damit die Datei klein bleibt
    foreach ($data as $k => $ts) {
        $data[$k] = array_values(array_filter((array)$ts, fn($t) => $t > $now - $window));
        if (!$data[$k]) unset($data[$k]);
    }
    $hits = $data[$ip] ?? [];
    if (count($hits) >= $max) {
        @file_put_contents($file, json_encode($data), LOCK_EX);
        return false;
    }
    $hits[] = $now;
    $data[$ip] = $hits;
    @file_put_contents($file, json_encode($data), LOCK_EX);
    return true;
}

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

// ── Token prüfen ─────────────────────────────────────────────
$stored = trim(Database::queryOne("SELECT value FROM settings WHERE `key`='feedback_api_token'")['value'] ?? '');
if ($stored === '') {
    jsonError('Feedback-API ist deaktiviert (kein Token gesetzt).', 403);
}

// Token aus Authorization: Bearer …, alternativ ?token= / JSON-Body.
// mod_php reicht den Authorization-Header nicht immer in $_SERVER durch —
// deshalb zusätzlich REDIRECT_-Variante und apache_request_headers() prüfen.
$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
if ($auth === '' && function_exists('apache_request_headers')) {
    foreach (apache_request_headers() as $hk => $hv) {
        if (strcasecmp($hk, 'Authorization') === 0) { $auth = $hv; break; }
    }
}
$token = preg_match('/^Bearer\s+(\S+)$/i', $auth, $m) ? $m[1] : '';
if ($token === '') $token = (string)($_GET['token'] ?? '');
$body = jsonBody();
if ($token === '') $token = (string)($body['token'] ?? '');

if (!hash_equals($stored, $token)) {
    // Fehlversuche deutlich strenger limitieren als gültige Zugriffe
    if (!feedbackApiRateLimit('fail:' . $ip, 10)) {
        jsonError('Zu viele Fehlversuche — bitte später erneut.', 429);
    }
    logEvent('WARNING', "[Feedback-API] Ungültiges Token von {$ip}.");
    jsonError('Ungültiges Token.', 403);
}
if (!feedbackApiRateLimit($ip, 60)) {
    jsonError('Rate-Limit erreicht (max. 60 Anfragen/Minute).', 429);
}

$action = $body['action'] ?? $_GET['action'] ?? '';

switch ($action) {

    case 'list':
        // Feedback-Einträge als JSON. Filter (optional): type, status, since_id, limit.
        $where  = ["m.type != 'question'"];
        $params = [];
        $type = $body['type'] ?? $_GET['type'] ?? '';
        if ($type !== '') {
            if (!in_array($type, ['bug', 'wish', 'feedback'], true)) jsonError('Ungültiger Typ-Filter.');
            $where[]  = 'm.type = ?';
            $params[] = $type;
        }
        $status = $body['status'] ?? $_GET['status'] ?? '';
        if ($status !== '') {
            if (!isset(feedbackStatusMeta()[$status])) jsonError('Ungültiger Status-Filter.');
            $where[]  = 'm.status = ?';
            $params[] = $status;
        }
        $sinceId = (int)($body['since_id'] ?? $_GET['since_id'] ?? 0);
        if ($sinceId > 0) {
            $where[]  = 'm.id > ?';
            $params[] = $sinceId;
        }
        $limit = (int)($body['limit'] ?? $_GET['limit'] ?? 100);
        $limit = max(1, min(200, $limit));

        $rows = Database::query(
            "SELECT m.id, m.type, m.status, m.message, m.reply, m.created_at, m.replied_at,
                    p.display_name AS player
             FROM messages m JOIN players p ON p.id = m.player_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY m.id DESC
             LIMIT {$limit}",
            $params
        );
        $entries = array_map(fn($r) => [
            'id'         => (int)$r['id'],
            'type'       => $r['type'],
            'status'     => $r['status'],
            'message'    => $r['message'],
            'reply'      => $r['reply'],
            'player'     => $r['player'],
            'created_at' => $r['created_at'],
            'replied_at' => $r['replied_at'],
        ], $rows);
        jsonResponse([
            'ok'          => true,
            'app_version' => APP_VERSION,
            'count'       => count($entries),
            'entries'     => $entries,
        ]);

    case 'set_status':
        // Bearbeitungsstatus setzen (z.B. nach dem Fixen eines Bugs auf 'done').
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('set_status nur per POST.', 405);
        $id     = (int)($body['id'] ?? 0);
        $status = $body['status'] ?? '';
        if (!$id) jsonError('Ungültige ID.');
        if (!isset(feedbackStatusMeta()[$status])) jsonError('Ungültiger Status (open|in_progress|done).');
        $row = Database::queryOne("SELECT id, type, status FROM messages WHERE id = ?", [$id]);
        if (!$row || $row['type'] === 'question') jsonError('Feedback-Eintrag nicht gefunden.', 404);
        Database::execute("UPDATE messages SET status = ? WHERE id = ?", [$status, $id]);
        logEvent('INFO', "[Feedback-API] Status von Eintrag #{$id}: {$row['status']} → {$status} ({$ip}).");
        jsonOk('Status aktualisiert.', ['id' => $id, 'status' => $status]);

    default:
        jsonError('Unbekannte Aktion (list | set_status).', 400);
}
