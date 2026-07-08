<?php
// Copyright (c) 2026 Andreas Vetter
// Favicon-Upload: tauscht das Browser-Tab-Icon (mini_logo.png) aus.
// Nur PNG erlaubt — Transparenz ist für dunkle und helle Browser-Themes nötig.
require_once dirname(__DIR__) . '/core/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
Auth::requireAdmin();
requireSameOrigin();

const MAX_FAVICON_BYTES = 512 * 1024;
const FAVICON_DIR_REL   = 'assets/icons/logo';
const FAVICON_FILENAME  = 'mini_logo.png';

$faviconDirAbs = ROOT_PATH . '/' . FAVICON_DIR_REL;
if (!is_dir($faviconDirAbs)) {
    @mkdir($faviconDirAbs, 0755, true);
}

if (empty($_FILES['favicon']) || $_FILES['favicon']['error'] !== UPLOAD_ERR_OK) {
    $errCode = $_FILES['favicon']['error'] ?? UPLOAD_ERR_NO_FILE;
    $msg = match ($errCode) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Datei ist zu groß.',
        UPLOAD_ERR_NO_FILE => 'Keine Datei ausgewählt.',
        default => 'Upload fehlgeschlagen (Fehlercode ' . $errCode . ').',
    };
    jsonError($msg);
}

$file = $_FILES['favicon'];

if ($file['size'] > MAX_FAVICON_BYTES) {
    jsonError('Datei ist zu groß (max. 512 KB).');
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if ($mime !== 'image/png') {
    jsonError('Nur PNG-Dateien erlaubt (erkannt: ' . e($mime) . '). PNG wird benötigt da Transparenz genutzt wird.');
}

$destAbs = $faviconDirAbs . '/' . FAVICON_FILENAME;

if (!move_uploaded_file($file['tmp_name'], $destAbs)) {
    jsonError('Datei konnte nicht gespeichert werden (Schreibrechte prüfen).');
}

$faviconPath = FAVICON_DIR_REL . '/' . FAVICON_FILENAME;

Database::execute(
    'INSERT INTO settings (`key`, value, type, label, description, sort_order) VALUES (?,?,?,?,?,?)
     ON DUPLICATE KEY UPDATE value = VALUES(value)',
    ['mini_logo', $faviconPath, 'string', 'Browser-Icon (Favicon)', 'PNG-Datei für die Browser-Adressleiste / Tab-Icon.', 6]
);

bumpAssetVersion();

jsonOk('Favicon hochgeladen.', ['favicon_path' => $faviconPath]);
