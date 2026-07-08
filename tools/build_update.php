<?php
// Copyright (c) 2026 Andreas Vetter
/**
 * Baut ein signiertes Update-Paket (.wwupd) aus einem Verzeichnis geänderter
 * Dateien + Metadaten. Läuft LOKAL/beim Entwickeln (braucht den privaten
 * Signaturschlüssel), NICHT auf dem Live-Server.
 *
 * Verwendung:
 *   php tools/build_update.php \
 *       --target 1.1 [--min 1.0] \
 *       --src <ordner-mit-webroot-relativen-dateien> \
 *       --out updates/update-1.1.wwupd \
 *       [--notes "Text"|@notes.txt] [--migration db/migrations/0002_x.sql] \
 *       [--resetup] [--key config/update_privkey.secret]
 *
 * --src: Ordner, dessen Inhalt die Ziel-Pfade spiegelt (z.B. src/admin/foo.php
 *        -> "admin/foo.php"). Wird rekursiv eingelesen.
 */

$opt = getopt('', ['target:', 'min::', 'src:', 'out:', 'notes::', 'migration::', 'resetup', 'key::']);
foreach (['target', 'src', 'out'] as $req) {
    if (empty($opt[$req])) { fwrite(STDERR, "Fehlt: --$req\n"); exit(1); }
}
if (!function_exists('sodium_crypto_sign_detached')) { fwrite(STDERR, "libsodium fehlt.\n"); exit(1); }

$keyFile = $opt['key'] ?? (__DIR__ . '/../config/update_privkey.secret');
if (!is_file($keyFile)) { fwrite(STDERR, "Secret-Key nicht gefunden: $keyFile\n"); exit(1); }
$secret = base64_decode(trim((string)file_get_contents($keyFile)), true);
if ($secret === false || strlen($secret) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
    fwrite(STDERR, "Secret-Key ungültig.\n"); exit(1);
}

$srcDir = rtrim($opt['src'], '/\\');
if (!is_dir($srcDir)) { fwrite(STDERR, "src-Ordner fehlt: $srcDir\n"); exit(1); }

// Dateien rekursiv einsammeln (relativ zu src)
$filesMap = [];        // rel => base64 content
$manifestFiles = [];   // [{path, sha256}]
$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($srcDir, FilesystemIterator::SKIP_DOTS)
);
foreach ($it as $file) {
    if (!$file->isFile()) continue;
    $rel = str_replace('\\', '/', substr($file->getPathname(), strlen($srcDir) + 1));
    if (!preg_match('#^[A-Za-z0-9][A-Za-z0-9._/-]*$#', $rel) || str_contains($rel, '..')) {
        fwrite(STDERR, "Unsicherer/ungültiger Pfad übersprungen: $rel\n"); continue;
    }
    $content = (string)file_get_contents($file->getPathname());
    $filesMap[$rel]  = base64_encode($content);
    $manifestFiles[] = ['path' => $rel, 'sha256' => hash('sha256', $content)];
}
if (!$manifestFiles) { fwrite(STDERR, "Keine Dateien in --src gefunden.\n"); exit(1); }

// Notes (Text oder @datei)
$notes = (string)($opt['notes'] ?? '');
if ($notes !== '' && $notes[0] === '@') {
    $nf = substr($notes, 1);
    $notes = is_file($nf) ? (string)file_get_contents($nf) : '';
}

// Optionale Migration
$migrationB64 = null; $migrationSha = null;
if (!empty($opt['migration'])) {
    if (!is_file($opt['migration'])) { fwrite(STDERR, "Migration-Datei fehlt: {$opt['migration']}\n"); exit(1); }
    $sql = (string)file_get_contents($opt['migration']);
    $migrationB64 = base64_encode($sql);
    $migrationSha = hash('sha256', $sql);
}

$manifest = [
    'target_version'   => (string)$opt['target'],
    'min_version'      => (string)($opt['min'] ?? ''),
    'created_at'       => date('c'),
    'notes'            => $notes,
    'requires_resetup' => isset($opt['resetup']),
    'has_db_changes'   => $migrationB64 !== null,
    'files'            => $manifestFiles,
    'migration_sha256' => $migrationSha,
];
$manifestJson = json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$signature    = base64_encode(sodium_crypto_sign_detached($manifestJson, $secret));

$pkg = [
    'format'        => 1,
    'manifest_json' => $manifestJson,
    'signature'     => $signature,
    'files'         => $filesMap,
    'migration'     => $migrationB64,
];
$out = $opt['out'];
@mkdir(dirname($out), 0775, true);
file_put_contents($out, json_encode($pkg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

printf("✓ Paket geschrieben: %s\n  Zielversion: %s (min: %s)\n  Dateien: %d%s\n",
    $out, $manifest['target_version'], $manifest['min_version'] ?: '—',
    count($manifestFiles), $migrationB64 !== null ? ", + DB-Migration" : "");
