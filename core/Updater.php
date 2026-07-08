<?php
// Copyright (c) 2026 Andreas Vetter
/**
 * ============================================================
 *  WERWOLF — Updater.php
 * ============================================================
 *  Verifiziert und wendet signierte Update-Pakete (.wwupd) an.
 *
 *  Paketformat (JSON):
 *    {
 *      "format": 1,
 *      "manifest_json": "<exakter JSON-String>",   // wird signiert
 *      "signature": "<base64 Ed25519 detached sig ueber manifest_json-Bytes>",
 *      "files": { "rel/pfad.php": "<base64 Inhalt>", ... },
 *      "migration": "<base64 SQL>" | null
 *    }
 *  manifest_json (dekodiert):
 *    {
 *      "target_version": "1.1", "min_version": "1.0",
 *      "notes": "…", "requires_resetup": false, "has_db_changes": true,
 *      "files": [ { "path": "rel/pfad.php", "sha256": "hex" }, … ],
 *      "migration_sha256": "hex" | null
 *    }
 *
 *  Sicherheitsprinzip: Nur mit dem privaten Entwickler-Schluessel signierte
 *  Pakete werden akzeptiert (Public Key in config/update_pubkey.php). Jede
 *  nachtraegliche Manipulation (veraendertes Manifest, ausgetauschte Datei,
 *  gefaelschtes Paket) faellt bei der Signatur- oder Hash-Pruefung auf.
 * ============================================================
 */

class Updater {

    /** Base64-Public-Key laden. */
    public static function publicKey(): string {
        $b64 = require CONFIG_PATH . '/update_pubkey.php';
        return base64_decode((string)$b64, true) ?: '';
    }

    /**
     * Paket parsen + vollstaendig verifizieren (Signatur, Datei-Hashes, Pfade).
     * Rueckgabe: ['ok'=>bool, 'error'=>?string, 'manifest'=>?array, 'pkg'=>?array]
     */
    public static function verify(string $raw): array {
        $pkg = json_decode($raw, true);
        if (!is_array($pkg) || ($pkg['format'] ?? null) !== 1) {
            return ['ok' => false, 'error' => 'Kein gültiges Update-Paket (Format).'];
        }
        $manifestJson = (string)($pkg['manifest_json'] ?? '');
        $sig          = base64_decode((string)($pkg['signature'] ?? ''), true);
        if ($manifestJson === '' || $sig === false) {
            return ['ok' => false, 'error' => 'Paket unvollständig (Manifest/Signatur fehlt).'];
        }

        // 1) Signatur über die exakten Manifest-Bytes prüfen
        $pub = self::publicKey();
        try {
            $sigOk = strlen($pub) === SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES
                  && sodium_crypto_sign_verify_detached($sig, $manifestJson, $pub);
        } catch (\Throwable $e) {
            $sigOk = false;
        }
        if (!$sigOk) {
            return ['ok' => false, 'error' => '❌ Signatur ungültig — das Paket ist nicht vom Entwickler signiert oder wurde manipuliert.'];
        }

        $manifest = json_decode($manifestJson, true);
        if (!is_array($manifest)) {
            return ['ok' => false, 'error' => 'Manifest nicht lesbar.'];
        }

        // 2) Jede Datei: vorhanden, Pfad sicher, Hash stimmt
        $files = $pkg['files'] ?? [];
        foreach ($manifest['files'] ?? [] as $f) {
            $path = (string)($f['path'] ?? '');
            if (self::safeRelPath($path) === null) {
                return ['ok' => false, 'error' => 'Unsicherer Dateipfad im Paket: ' . $path];
            }
            if (!isset($files[$path])) {
                return ['ok' => false, 'error' => 'Datei fehlt im Paket: ' . $path];
            }
            $content = base64_decode((string)$files[$path], true);
            if ($content === false || hash('sha256', $content) !== ($f['sha256'] ?? '')) {
                return ['ok' => false, 'error' => '❌ Prüfsumme weicht ab (manipulierte Datei): ' . $path];
            }
        }

        // 3) Migration (optional): Hash prüfen
        if (!empty($manifest['migration_sha256'])) {
            $mig = base64_decode((string)($pkg['migration'] ?? ''), true);
            if ($mig === false || hash('sha256', $mig) !== $manifest['migration_sha256']) {
                return ['ok' => false, 'error' => '❌ Prüfsumme der DB-Migration weicht ab.'];
            }
        }

        return ['ok' => true, 'error' => null, 'manifest' => $manifest, 'pkg' => $pkg];
    }

    /**
     * Versionsprüfung: target muss neuer als installiert sein, und die installierte
     * Version muss die geforderte Mindestversion erfüllen (kein Einspielen über Kreuz).
     */
    public static function checkVersion(array $manifest, string $current): array {
        $target = (string)($manifest['target_version'] ?? '');
        $min    = (string)($manifest['min_version'] ?? '');
        if ($target === '') return ['ok' => false, 'error' => 'Keine Zielversion im Manifest.'];
        if (!version_compare($target, $current, '>')) {
            return ['ok' => false, 'error' => "Dieses Update (Version {$target}) ist nicht neuer als die installierte Version {$current}."];
        }
        if ($min !== '' && !version_compare($current, $min, '>=')) {
            return ['ok' => false, 'error' => "Dieses Update setzt mindestens Version {$min} voraus — installiert ist {$current}. Bitte zuerst die Zwischenversion(en) einspielen."];
        }
        return ['ok' => true, 'error' => null];
    }

    /**
     * Sicheren relativen Pfad prüfen: keine Traversal, kein absoluter/Windows-Pfad.
     * Rueckgabe: normalisierter relativer Pfad oder null bei Unsicherheit.
     */
    public static function safeRelPath(string $rel): ?string {
        $rel = str_replace('\\', '/', trim($rel));
        if ($rel === '' || $rel[0] === '/' || str_contains($rel, "\0")) return null;
        if (preg_match('#(^|/)\.\.(/|$)#', $rel)) return null;           // ..
        if (preg_match('#(^|/)\.(/|$)#', $rel)) return null;             // .
        if (!preg_match('#^[A-Za-z0-9][A-Za-z0-9._/-]*$#', $rel)) return null;
        if (preg_match('#[A-Za-z]:#', $rel)) return null;                // Laufwerksbuchstabe
        return $rel;
    }

    /**
     * Paket anwenden: Backup der zu überschreibenden Dateien, neue Dateien schreiben,
     * optionale additive Migration, app_version setzen. Bei Fehler wird aus dem Backup
     * wiederhergestellt. Setzt voraus, dass verify()+checkVersion() ok waren.
     * Rueckgabe: ['ok'=>bool, 'error'=>?string, 'written'=>[], 'backup'=>?string]
     */
    public static function apply(array $pkg, array $manifest): array {
        if (!empty($manifest['requires_resetup'])) {
            return ['ok' => false, 'error' => 'Dieses Update erfordert ein komplettes Neu-Aufsetzen (Ersteinrichtung) und kann nicht automatisch angewendet werden.'];
        }

        $ts       = date('Ymd_His');
        $backupRel = 'updates/backups/' . $ts;
        $backupAbs = ROOT_PATH . '/' . $backupRel;
        if (!is_dir($backupAbs) && !@mkdir($backupAbs, 0775, true)) {
            return ['ok' => false, 'error' => 'Backup-Verzeichnis konnte nicht angelegt werden.'];
        }

        $files   = $pkg['files'] ?? [];
        $written = [];
        try {
            foreach ($manifest['files'] ?? [] as $f) {
                $rel = self::safeRelPath((string)($f['path'] ?? ''));
                if ($rel === null) throw new \RuntimeException('Unsicherer Pfad: ' . ($f['path'] ?? ''));
                $abs = ROOT_PATH . '/' . $rel;

                // Backup der bestehenden Datei (falls vorhanden)
                if (is_file($abs)) {
                    $bAbs = $backupAbs . '/' . $rel;
                    if (!is_dir(dirname($bAbs))) @mkdir(dirname($bAbs), 0775, true);
                    if (!@copy($abs, $bAbs)) throw new \RuntimeException('Backup fehlgeschlagen: ' . $rel);
                }
                // Zielordner sicherstellen + schreiben
                if (!is_dir(dirname($abs)) && !@mkdir(dirname($abs), 0775, true)) {
                    throw new \RuntimeException('Zielordner nicht anlegbar: ' . $rel);
                }
                $content = base64_decode((string)($files[$rel] ?? ''), true);
                if ($content === false || @file_put_contents($abs, $content) === false) {
                    throw new \RuntimeException('Schreiben fehlgeschlagen: ' . $rel);
                }
                $written[] = $rel;
            }

            // Optionale additive Migration
            if (!empty($manifest['migration_sha256'])) {
                $sql = base64_decode((string)($pkg['migration'] ?? ''), true) ?: '';
                if (preg_match('/\bDROP\s+(TABLE|DATABASE|SCHEMA)\b|\bTRUNCATE\b/i', $sql)) {
                    throw new \RuntimeException('Migration enthält destruktive Anweisung (DROP/TRUNCATE).');
                }
                foreach (self::splitSql($sql) as $stmt) {
                    Database::execute($stmt);
                }
            }
        } catch (\Throwable $e) {
            self::restoreBackup($backupAbs, $written);
            logEvent('ERROR', 'Update fehlgeschlagen, Backup wiederhergestellt: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Update fehlgeschlagen: ' . $e->getMessage() . ' — Änderungen wurden zurückgerollt.', 'backup' => $backupRel];
        }

        // app_version setzen
        $target = (string)($manifest['target_version'] ?? '');
        Database::execute(
            "INSERT INTO settings (`key`, value, type, label, description, sort_order) VALUES ('app_version', ?, 'string', 'App-Version', 'Aktuelle Version der App.', 1)
             ON DUPLICATE KEY UPDATE value = VALUES(value)",
            [$target]
        );
        logEvent('INFO', "Update auf Version {$target} angewendet (" . count($written) . ' Datei(en), Backup ' . $backupRel . ').');
        return ['ok' => true, 'error' => null, 'written' => $written, 'backup' => $backupRel];
    }

    private static function restoreBackup(string $backupAbs, array $written): void {
        foreach ($written as $rel) {
            $bAbs = $backupAbs . '/' . $rel;
            $abs  = ROOT_PATH . '/' . $rel;
            if (is_file($bAbs)) @copy($bAbs, $abs); // war vorher vorhanden → zurück
            // War die Datei neu (kein Backup), bleibt sie stehen — additive Neuanlage ist unkritisch.
        }
    }

    private static function splitSql(string $sql): array {
        $lines = array_filter(explode("\n", $sql), fn($l) => !preg_match('/^\s*--/', $l));
        return array_values(array_filter(array_map('trim', explode(';', implode("\n", $lines))), fn($s) => $s !== ''));
    }
}
