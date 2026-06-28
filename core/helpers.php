<?php
// Copyright (c) 2026 Andreas Vetter
/**
 * ============================================================
 *  WERWOLF — helpers.php
 * ============================================================
 *  Globale Hilfsfunktionen, die überall verfügbar sind.
 * ============================================================
 */

/** HTML-sicher ausgeben */
function e(mixed $val): string {
    return htmlspecialchars((string)$val, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** JSON-Antwort senden und beenden */
function jsonResponse(mixed $data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/** JSON-Fehler senden */
function jsonError(string $msg, int $code = 400): never {
    jsonResponse(['error' => $msg], $code);
}

/** JSON-Erfolg senden */
function jsonOk(string $msg = 'OK', array $extra = []): never {
    jsonResponse(array_merge(['ok' => true, 'message' => $msg], $extra));
}

/** Redirect */
function redirect(string $path): never {
    header('Location: ' . APP_URL . $path);
    exit;
}

/** Ist es ein POST-Request? */
function isPost(): bool {
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

/** POST-Wert sicher holen */
function post(string $key, mixed $default = ''): mixed {
    return $_POST[$key] ?? $default;
}

/** GET-Wert sicher holen */
function get(string $key, mixed $default = ''): mixed {
    return $_GET[$key] ?? $default;
}

/** JSON-Body aus php://input lesen (für API-Endpoints) */
function jsonBody(): array {
    static $body = null;
    if ($body === null) {
        $raw  = file_get_contents('php://input');
        $body = json_decode($raw, true) ?? [];
    }
    return $body;
}

/** Passwort hashen */
function hashPassword(string $pw): string {
    return password_hash($pw, PASSWORD_BCRYPT, ['cost' => 10]);
}

/** Passwort prüfen */
function verifyPassword(string $pw, string $hash): bool {
    return password_verify($pw, $hash);
}


// ============================================================
//  Rollen — komplett datenbankgesteuert (Tabelle: roles)
//  Kein Rollen-Eintrag mehr im Code. Verwaltung über
//  /roles.php
// ============================================================

/** Alle Rollen laden (auch deaktivierte), sortiert nach sort_order. */
function allRoles(): array {
    return Database::query("SELECT * FROM roles ORDER BY sort_order, name");
}

/** Nur aktive Rollen laden (active = 1) — für Rollenvergabe im Spiel. */
function activeRoles(): array {
    return Database::query("SELECT * FROM roles WHERE active = 1 ORDER BY sort_order, name");
}

/** Eine einzelne Rolle per ID laden. Gibt ein Fallback-Array zurück, falls nicht gefunden. */
function role(?int $roleId): array {
    if ($roleId === null) {
        return roleFallback();
    }
    static $cache = [];
    if (!isset($cache[$roleId])) {
        $r = Database::queryOne("SELECT * FROM roles WHERE id = ?", [$roleId]);
        $cache[$roleId] = $r ?: roleFallback();
    }
    return $cache[$roleId];
}

/** Fallback, falls eine Rolle gelöscht wurde oder noch keine zugewiesen ist. */
function roleFallback(): array {
    return [
        'id' => null, 'name' => 'Unbekannt', 'cooldown' => 0,
        'description' => '', 'rules' => '', 'active' => 0, 'fill' => 0, 'amount' => 0,
        'icon_path' => DEFAULT_ROLE_ICON, 'sichtbar' => 0, 'befragen' => 0,
        'auto_eintrag' => 0, 'is_killer' => 0, 'sort_order' => 999,
    ];
}

/**
 * Asset-URL mit automatischem Cache-Busting (filemtime-basiert).
 * Nutze diese Funktion überall wo Bilder/Assets ggf. neu hochgeladen werden.
 */
function assetUrl(string $path): string {
    $rel = ltrim($path, '/');
    $abs = ROOT_PATH . '/' . $rel;
    $v   = file_exists($abs) ? filemtime($abs) : ASSET_VERSION;
    return APP_URL . '/' . $rel . '?v=' . $v;
}

/** Icon-URL für eine Rolle (für <img src="…">, mask-image oder background-image) */
function roleIconUrl(array $roleRow): string {
    $path = $roleRow['icon_path'] ?? '';
    if (!$path) $path = DEFAULT_ROLE_ICON;
    return assetUrl($path);
}

/** Rendert ein Rollen-Icon als <span>. $size: 'sm' | 'md' | 'lg' | 'xl' */
function roleIconHtml(?array $roleRow, string $size = 'md'): string {
    $url   = $roleRow ? roleIconUrl($roleRow) : APP_URL . '/' . DEFAULT_ROLE_ICON;
    $label = $roleRow['name'] ?? 'Rolle';
    return sprintf(
        '<span class="role-icon role-icon--%s role-icon--photo" style="background-image:url(\'%s\')" role="img" aria-label="%s"></span>',
        e($size), e($url), e($label)
    );
}

/** Aktuelles Game laden (läuft oder Lobby) */
function currentGame(): array|false {
    return Database::queryOne(
        "SELECT * FROM games WHERE status IN ('lobby','running') ORDER BY id DESC LIMIT 1"
    );
}

/** Spieler im Game laden (inkl. Rollendaten per JOIN) */
function gamePlayers(int $gameId): array {
    return Database::query(
        "SELECT gp.*, p.username, p.display_name,
                r.name AS role_name, r.icon_path AS role_icon_path,
                r.cooldown AS role_cooldown, r.sichtbar AS role_sichtbar
         FROM game_players gp
         JOIN players p ON p.id = gp.player_id
         LEFT JOIN roles r ON r.id = gp.role_id
         WHERE gp.game_id = ?
         ORDER BY gp.is_alive DESC, p.display_name",
        [$gameId]
    );
}

/** Spieler-Eintrag im Game holen (inkl. Rollendaten per JOIN) */
function gamePlayer(int $gameId, int $playerId): array|false {
    return Database::queryOne(
        "SELECT gp.*, p.username, p.display_name,
                r.name AS role_name, r.icon_path AS role_icon_path,
                r.cooldown AS role_cooldown, r.sichtbar AS role_sichtbar
         FROM game_players gp
         JOIN players p ON p.id = gp.player_id
         LEFT JOIN roles r ON r.id = gp.role_id
         WHERE gp.game_id = ? AND gp.player_id = ?",
        [$gameId, $playerId]
    );
}

/** Tod aufzeichnen */
function recordDeath(int $gameId, int $playerId, int $round, string $phase, ?string $ort = null, bool $isGehenkt = false): void {
    $gp = Database::queryOne(
        "SELECT gp.role_id, gp.is_alive, r.auto_eintrag
         FROM game_players gp
         LEFT JOIN roles r ON r.id = gp.role_id
         WHERE gp.game_id = ? AND gp.player_id = ?",
        [$gameId, $playerId]
    );
    if (!$gp || !$gp['is_alive']) return;
    $autoEintrag = !empty($gp['auto_eintrag']) ? 1 : 0;
    Database::execute(
        "UPDATE game_players SET is_alive = 0 WHERE game_id = ? AND player_id = ?",
        [$gameId, $playerId]
    );
    $zeit = $autoEintrag ? date('H:i') : null;
    Database::execute(
        "INSERT INTO deaths (game_id, player_id, role_id, round, phase, is_gehenkt, ort, rolle_aufgedeckt, zeit) VALUES (?,?,?,?,?,?,?,?,?)",
        [$gameId, $playerId, $gp['role_id'] ?? null, $round, $phase, $isGehenkt ? 1 : 0, $ort ?: null, $autoEintrag, $zeit]
    );
    // Stirbt ein Spieler, wird seine laufende Anklage sofort ungültig
    Database::execute(
        "DELETE FROM votes WHERE game_id = ? AND voter_id = ?",
        [$gameId, $playerId]
    );
}

/**
 * Prüft, ob ein Spieler seine Rollenfähigkeit in der aktuellen Runde
 * einsetzen darf (Cooldown-Logik). $currentRound = aktuelle Spielrunde.
 *
 * HINWEIS: Diese Funktion ist vorbereitete Infrastruktur für eine
 * zukünftige Nachtphasen-Mechanik (gezielte Fähigkeiten wie Seher-Blick,
 * Hexen-Tränke, Heiler-Schutz mit echter Cooldown-Durchsetzung). Aktuell
 * wird der Cooldown-Wert einer Rolle nur informativ angezeigt
 * (siehe public/game.php), aber noch nicht aktiv durchgesetzt — es gibt
 * noch keine UI für rollenspezifische Nachtaktionen. Kein toter Code,
 * sondern bewusst vorbereiteter Baustein für den nächsten Ausbauschritt.
 */
function canUseAbility(array $gamePlayerRow, int $currentRound): bool {
    $cooldown = (int)($gamePlayerRow['role_cooldown'] ?? 0);
    if ($cooldown <= 0) return true; // kein Cooldown definiert
    $last = $gamePlayerRow['last_ability_round'] ?? null;
    if ($last === null) return true; // noch nie genutzt
    return ($currentRound - (int)$last) >= ($cooldown + 1);
}

/** Asset-Version in DB erhöhen — nach jedem Bild-Upload aufrufen. */
function bumpAssetVersion(): void {
    $current = (int)(Database::queryOne('SELECT value FROM settings WHERE `key` = ?', ['asset_version'])['value'] ?? 0);
    Database::execute(
        'INSERT INTO settings (`key`, value, type, label, description, sort_order) VALUES (?,?,?,?,?,?) AS new_row
         ON DUPLICATE KEY UPDATE value = new_row.value',
        ['asset_version', (string)($current + 1), 'int', 'Asset-Version', 'Wird bei jedem Bild-Upload erhöht (Cache-Busting).', 999]
    );
}

/** Markiert, dass die Fähigkeit in dieser Runde genutzt wurde. */
function markAbilityUsed(int $gameId, int $playerId, int $round): void {
    Database::execute(
        "UPDATE game_players SET last_ability_round = ? WHERE game_id = ? AND player_id = ?",
        [$round, $gameId, $playerId]
    );
}
