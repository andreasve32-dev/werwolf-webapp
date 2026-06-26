<?php
// Copyright (c) 2026 Andreas Vetter
require_once dirname(__DIR__) . '/core/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
Auth::requireAdmin();

const MAX_LOGO_BYTES = 2 * 1024 * 1024;
const LOGO_DIR_REL   = 'assets/icons/logo';
const LOGO_FILENAME  = 'login_logo.png';

$logoDirAbs = ROOT_PATH . '/' . LOGO_DIR_REL;
if (!is_dir($logoDirAbs)) {
    @mkdir($logoDirAbs, 0755, true);
}

if (empty($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
    $errCode = $_FILES['logo']['error'] ?? UPLOAD_ERR_NO_FILE;
    $msg = match ($errCode) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Datei ist zu groß.',
        UPLOAD_ERR_NO_FILE => 'Keine Datei ausgewählt.',
        default => 'Upload fehlgeschlagen (Fehlercode ' . $errCode . ').',
    };
    jsonError($msg);
}

$file = $_FILES['logo'];

if ($file['size'] > MAX_LOGO_BYTES) {
    jsonError('Datei ist zu groß (max. 2 MB).');
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if ($mime !== 'image/png') {
    jsonError('Nur PNG-Dateien erlaubt (erkannt: ' . e($mime) . '). PNG wird benötigt da Transparenz genutzt wird.');
}

$destAbs = $logoDirAbs . '/' . LOGO_FILENAME;

if (!move_uploaded_file($file['tmp_name'], $destAbs)) {
    jsonError('Datei konnte nicht gespeichert werden (Schreibrechte prüfen).');
}

$logoPath = LOGO_DIR_REL . '/' . LOGO_FILENAME;

Database::execute(
    'INSERT INTO settings (`key`, value, type, label, description, sort_order) VALUES (?,?,?,?,?,?)
     ON DUPLICATE KEY UPDATE value = VALUES(value)',
    ['login_logo', $logoPath, 'string', 'Login-Logo', 'Pfad zum Bild auf der Anmeldeseite (leer = Wolf-Emoji 🐺).', 5]
);

bumpAssetVersion();

jsonOk('Logo hochgeladen.', ['logo_path' => $logoPath]);
