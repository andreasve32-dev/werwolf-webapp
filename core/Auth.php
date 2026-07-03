<?php
// Copyright (c) 2026 Andreas Vetter
/**
 * ============================================================
 *  WERWOLF — Auth.php
 * ============================================================
 *  Session-Verwaltung, Login, Logout, Schutz von Seiten.
 * ============================================================
 */

class Auth {

    /**
     * Session starten (einmalig pro Request).
     * Setzt gc_maxlifetime vor session_start(), damit der PHP-eigene
     * Garbage Collector Sessions nicht bereits nach 24 Minuten
     * (Standard) löscht, sondern erst nach SESSION_LIFETIME.
     * Erneuert außerdem bei jedem Besuch das Ablaufdatum des
     * Session-Cookies (Rolling-Window), damit die 7 Tage ab dem
     * letzten Besuch — nicht ab dem Login — zählen.
     */
    public static function start(): void {
        if (session_status() !== PHP_SESSION_NONE) return;

        ini_set('session.gc_maxlifetime', SESSION_LIFETIME);

        session_name(SESSION_NAME);
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (($_SERVER['SERVER_PORT'] ?? 80) == 443);
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path'     => '/',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();

        // Rolling 7-Tage-Fenster: Cookie-Ablauf bei jedem Request vorwärts schieben.
        if (!empty($_SESSION['player_id'])) {
            setcookie(SESSION_NAME, session_id(), [
                'expires'  => time() + SESSION_LIFETIME,
                'path'     => '/',
                'secure'   => $isHttps,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
    }

    /**
     * Gibt true zurück, wenn der Nutzer eingeloggt ist UND
     * noch in der Datenbank existiert.
     * Die DB-Prüfung wird auf maximal einmal alle 5 Minuten
     * gedrosselt, um unnötige Abfragen zu vermeiden.
     */
    public static function check(): bool {
        self::start();
        if (empty($_SESSION['player_id'])) return false;
        return self::validateInDb();
    }

    /** Gibt die Session-Daten des aktuellen Spielers zurück. */
    public static function player(): array {
        self::start();
        return [
            'id'           => $_SESSION['player_id']    ?? null,
            'username'     => $_SESSION['username']     ?? null,
            'display_name' => $_SESSION['display_name'] ?? null,
            'is_admin'     => $_SESSION['is_admin']     ?? false,
        ];
    }

    /** Seite nur für eingeloggte Spieler zugänglich. */
    public static function requireLogin(): void {
        if (!self::check()) {
            if (self::isApiRequest()) {
                self::jsonError('Nicht eingeloggt.', 401);
            }
            header('Location: ' . APP_URL . '/index.php');
            exit;
        }
    }

    /** Seite nur für Admins zugänglich. */
    public static function requireAdmin(): void {
        self::requireLogin();
        if (empty($_SESSION['is_admin'])) {
            if (self::isApiRequest()) {
                self::jsonError('Kein Zugriff.', 403);
            }
            header('Location: ' . APP_URL . '/app/game.php');
            exit;
        }
    }

    /** Spieler einloggen und Session setzen. */
    public static function login(array $player): void {
        self::start();
        session_regenerate_id(true);
        $_SESSION['player_id']    = $player['id'];
        $_SESSION['username']     = $player['username'];
        $_SESSION['display_name'] = $player['display_name'];
        $_SESSION['is_admin']     = (bool) $player['is_admin'];
        $_SESSION['_db_ok_at']    = time(); // frisch eingeloggt, kein sofortiger DB-Check nötig

        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (($_SERVER['SERVER_PORT'] ?? 80) == 443);

        // Cookie sofort auf volle SESSION_LIFETIME setzen
        setcookie(SESSION_NAME, session_id(), [
            'expires'  => time() + SESSION_LIFETIME,
            'path'     => '/',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        // Spielerdaten im js-lesbaren Cookie für localStorage-Sync
        setcookie('ww_player', json_encode([
            'id'           => $player['id'],
            'username'     => $player['username'],
            'display_name' => $player['display_name'],
            'is_admin'     => (bool) $player['is_admin'],
        ]), [
            'expires'  => time() + SESSION_LIFETIME,
            'path'     => '/',
            'secure'   => $isHttps,
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
    }

    /** Spieler ausloggen und alle Cookies löschen. */
    public static function logout(): void {
        self::start();
        session_unset();
        session_destroy();
        setcookie('ww_player',  '', time() - 3600, '/');
        setcookie(SESSION_NAME, '', time() - 3600, '/');
    }

    // ── Interne Hilfsmethoden ────────────────────────────────

    /**
     * Stellt sicher, dass der Spieler noch in der DB vorhanden ist.
     * Ergebnis wird 5 Minuten in der Session gecacht, um die DB
     * nicht bei jedem einzelnen Seitenaufruf zu befragen.
     * Bei einem DB-Fehler (z. B. kurzem Ausfall) bleibt der Nutzer
     * eingeloggt (Fail-open), um unbeabsichtigte Sperren zu vermeiden.
     */
    private static function validateInDb(): bool {
        $now = time();
        if (isset($_SESSION['_db_ok_at']) && ($now - $_SESSION['_db_ok_at']) < 300) {
            return true;
        }
        try {
            $row = Database::queryOne(
                'SELECT id FROM players WHERE id = ?',
                [$_SESSION['player_id']]
            );
            if (!$row) {
                self::logout();
                return false;
            }
            $_SESSION['_db_ok_at'] = $now;
            return true;
        } catch (Throwable $e) {
            // DB vorübergehend nicht erreichbar — Session vertrauen statt aussperren
            return true;
        }
    }

    private static function isApiRequest(): bool {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) ||
               str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/');
    }

    private static function jsonError(string $msg, int $code = 400): never {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode(['error' => $msg]);
        exit;
    }
}
