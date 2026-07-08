<?php
// Copyright (c) 2026 Andreas Vetter
/**
 * ============================================================
 *  WERWOLF — Database.php
 * ============================================================
 *  PDO-Singleton. Nur eine Verbindung pro Request.
 *  Alle DB-Zugangsdaten kommen aus config/config.php.
 * ============================================================
 */

class Database {

    private static ?PDO $instance = null;

    /** Gibt die PDO-Instanz zurück (lazy init). */
    public static function get(): PDO {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
            );
            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
            } catch (PDOException $e) {
                // Ins System-Log (error_log statt logEvent, da helpers.php ggf. noch
                // nicht geladen ist; der [ERROR]-Tag wird vom Log-Parser klassifiziert).
                error_log('[ERROR] DB-Verbindung fehlgeschlagen: ' . $e->getMessage());
                if (defined('APP_DEBUG') && APP_DEBUG) {
                    http_response_code(500);
                    die('<pre style="color:red">DB-Fehler: ' . htmlspecialchars($e->getMessage()) . '</pre>');
                }
                http_response_code(500);
                die(json_encode(['error' => 'Datenbankverbindung fehlgeschlagen.']));
            }
        }
        return self::$instance;
    }

    /** Shortcut: Prepared Statement ausführen und alle Zeilen zurückgeben. */
    public static function query(string $sql, array $params = []): array {
        $stmt = self::get()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Shortcut: Prepared Statement ausführen, erste Zeile zurückgeben. */
    public static function queryOne(string $sql, array $params = []): array|false {
        $stmt = self::get()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    }

    /** Shortcut: Statement ausführen (INSERT/UPDATE/DELETE). */
    public static function execute(string $sql, array $params = []): int {
        $stmt = self::get()->prepare($sql);
        $stmt->execute($params);
        return (int) self::get()->lastInsertId();
    }

    /** Gibt die ID des letzten INSERTs zurück. */
    public static function lastId(): int {
        return (int) self::get()->lastInsertId();
    }

    /**
     * Liest die settings-Tabelle und gibt alle Zeilen als key→value-Array zurück.
     * Gibt [] zurück wenn die DB oder die Tabelle noch nicht existiert (z. B. vor
     * dem ersten Setup). Der Singleton wird dabei bereits befüllt, damit kein zweiter
     * Connect ausgelöst wird.
     */
    public static function trySettings(): array {
        try {
            $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s',
                           DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            if (self::$instance === null) {
                self::$instance = $pdo;
            }
            $map = [];
            foreach ($pdo->query('SELECT `key`, value FROM `settings`') as $row) {
                $map[$row['key']] = $row['value'];
            }
            return $map;
        } catch (Throwable $e) {
            return [];
        }
    }
}
