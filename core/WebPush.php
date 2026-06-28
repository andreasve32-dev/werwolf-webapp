<?php
// Copyright (c) 2026 Andreas Vetter
/**
 * Natives Web Push ohne externe Bibliothek.
 * Sendet leere Pushes (kein Payload) — der Service Worker
 * zeigt dann eine generische Benachrichtigung.
 */

class WebPush {

    // ── Schlüsselverwaltung ───────────────────────────────────

    /** VAPID-Schlüssel erzeugen (einmalig) und in settings speichern. */
    public static function ensureKeys(): bool {
        $exists = Database::queryOne(
            "SELECT value FROM settings WHERE `key` = 'vapid_public_key'"
        );
        if ($exists && $exists['value']) return true;

        // openssl.cnf fehlt oft im PHP-Docker-Container
        if (!getenv('OPENSSL_CONF')) {
            foreach (['/etc/ssl/openssl.cnf', '/usr/lib/ssl/openssl.cnf', '/usr/local/ssl/openssl.cnf'] as $cnf) {
                if (file_exists($cnf)) { putenv('OPENSSL_CONF=' . $cnf); break; }
            }
        }

        $key = @openssl_pkey_new([
            'curve_name'       => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ]);
        if (!$key) return false;

        openssl_pkey_export($key, $privPem);
        $det = openssl_pkey_get_details($key);
        $x   = str_pad($det['ec']['x'], 32, "\x00", STR_PAD_LEFT);
        $y   = str_pad($det['ec']['y'], 32, "\x00", STR_PAD_LEFT);
        $pub = self::b64u("\x04" . $x . $y);

        foreach (['vapid_public_key' => $pub, 'vapid_private_key' => $privPem] as $k => $v) {
            Database::execute(
                "INSERT INTO settings (`key`, value, type, label, description, sort_order)
                 VALUES (?, ?, 'string', ?, '', 999)
                 ON DUPLICATE KEY UPDATE value = VALUES(value)",
                [$k, $v, $k === 'vapid_public_key' ? 'VAPID Public Key' : 'VAPID Private Key']
            );
        }
        return true;
    }

    public static function getPublicKey(): string {
        $row = Database::queryOne("SELECT value FROM settings WHERE `key` = 'vapid_public_key'");
        return $row['value'] ?? '';
    }

    // ── Versand ───────────────────────────────────────────────

    /** Push an einen einzelnen Spieler senden. */
    public static function sendToPlayer(int $playerId): void {
        try {
            [$pub, $priv] = self::loadKeys();
            if (!$pub) return;
            $subs = Database::query(
                "SELECT endpoint FROM push_subscriptions WHERE player_id = ?",
                [$playerId]
            );
            foreach ($subs as $s) {
                self::dispatch($s['endpoint'], $pub, $priv);
            }
        } catch (Throwable $e) {
            error_log('[WebPush] sendToPlayer error: ' . $e->getMessage());
        }
    }

    /**
     * Push an alle Spieler in einem Spiel senden.
     * $force = true: Cooldown wird übersprungen (z.B. Spielstart/-ende).
     * $title / $body: optionaler Nachrichtentext (leer = generischer Fallback im SW).
     */
    public static function sendToGame(int $gameId, bool $force = false, string $title = '', string $body = ''): void {
        try {
            if (!$force && !self::checkCooldown()) return;
            self::updateLastSent();
            [$pub, $priv] = self::loadKeys();
            if (!$pub) return;
            $subs = Database::query(
                "SELECT DISTINCT ps.endpoint
                 FROM push_subscriptions ps
                 JOIN game_players gp ON gp.player_id = ps.player_id
                 WHERE gp.game_id = ?",
                [$gameId]
            );
            $appName = defined('APP_NAME') ? APP_NAME : 'Spiel';
        $payload = ($title !== '') ? json_encode(['title' => $title, 'body' => $body, 'tag' => 'werwolf-event', 'app' => $appName]) : json_encode(['app' => $appName]);
            foreach ($subs as $s) {
                self::dispatch($s['endpoint'], $pub, $priv, $payload);
            }
        } catch (Throwable $e) {
            error_log('[WebPush] sendToGame error: ' . $e->getMessage());
        }
    }

    // ── Intern ───────────────────────────────────────────────

    /** Gibt true zurück wenn seit dem letzten Push genug Zeit vergangen ist. */
    private static function checkCooldown(): bool {
        $mins = (int)(Database::queryOne(
            "SELECT value FROM settings WHERE `key`='push_cooldown'"
        )['value'] ?? 30);
        if ($mins <= 0) return true;
        $last = (int)(Database::queryOne(
            "SELECT value FROM settings WHERE `key`='push_last_sent'"
        )['value'] ?? 0);
        return (time() - $last) >= ($mins * 60);
    }

    /** Zeitstempel des letzten Versands in der DB aktualisieren. */
    private static function updateLastSent(): void {
        Database::execute(
            "INSERT INTO settings (`key`,value,type,label,description,sort_order)
             VALUES('push_last_sent',?,'int','Push: letzter Versand (intern)','',999)
             ON DUPLICATE KEY UPDATE value=VALUES(value)",
            [(string)time()]
        );
    }

    private static function loadKeys(): array {
        $rows = Database::query(
            "SELECT `key`, value FROM settings WHERE `key` IN ('vapid_public_key','vapid_private_key')"
        );
        $map = array_column($rows, 'value', 'key');
        return [$map['vapid_public_key'] ?? '', $map['vapid_private_key'] ?? ''];
    }

    private static function dispatch(string $endpoint, string $pubKey, string $privPem, string $payload = ''): void {
        try {
            $p        = parse_url($endpoint);
            if (empty($p['scheme']) || empty($p['host'])) return;
            $audience = $p['scheme'] . '://' . $p['host'];
            $jwt      = self::createJwt($audience, $privPem);

            $headers = [
                'Authorization: vapid t=' . $jwt . ',k=' . $pubKey,
                'TTL: 86400',
            ];
            if ($payload !== '') {
                $headers[] = 'Content-Type: application/json';
                $headers[] = 'Content-Length: ' . strlen($payload);
            } else {
                $headers[] = 'Content-Length: 0';
            }

            $ch = curl_init($endpoint);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 5,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($code === 410 || $code === 404) {
                Database::execute(
                    "DELETE FROM push_subscriptions WHERE endpoint = ?",
                    [$endpoint]
                );
            }
        } catch (Throwable $e) {
            error_log('[WebPush] dispatch error: ' . $e->getMessage());
        }
    }

    private static function createJwt(string $audience, string $privPem): string {
        if (!$privPem) throw new RuntimeException('VAPID Private Key nicht konfiguriert');
        $header  = self::b64u('{"typ":"JWT","alg":"ES256"}');
        $payload = self::b64u(json_encode([
            'aud' => $audience,
            'exp' => time() + 43200,
            'sub' => 'mailto:noreply@werwolf.local',
        ]));
        $data = "$header.$payload";
        if (!@openssl_sign($data, $der, $privPem, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('openssl_sign fehlgeschlagen — VAPID Key ungültig?');
        }
        return "$data." . self::b64u(self::derToRS($der));
    }

    /** DER-kodierte ECDSA-Signatur → rohe 64-Byte-Form (r||s) */
    private static function derToRS(string $der): string {
        $i = 2;          // SEQUENCE tag (0x30) + 1-Byte-Länge überspringen
        $i++;            // INTEGER-Tag für r
        $rLen = ord($der[$i++]);
        $r    = substr($der, $i, $rLen);
        $i   += $rLen;
        $i++;            // INTEGER-Tag für s
        $sLen = ord($der[$i++]);
        $s    = substr($der, $i, $sLen);

        $r = str_pad(ltrim($r, "\x00"), 32, "\x00", STR_PAD_LEFT);
        $s = str_pad(ltrim($s, "\x00"), 32, "\x00", STR_PAD_LEFT);
        return substr($r, -32) . substr($s, -32);
    }

    private static function b64u(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
