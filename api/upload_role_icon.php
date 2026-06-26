<?php
// Copyright (c) 2026 Andreas Vetter
/**
 * ============================================================
 *  WERWOLF — api/upload_role_icon.php
 * ============================================================
 *  Nimmt einen PNG- oder JPG-Upload für ein Rollen-Icon
 *  entgegen und speichert ihn unter assets/icons/roles/.
 *
 *  Antwort: { ok: true, icon_path: "assets/icons/roles/xyz.png", message: "..." }
 *           { error: "..." } bei Fehlern
 * ============================================================
 */
require_once dirname(__DIR__) . '/core/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
Auth::requireAdmin();

const MAX_UPLOAD_BYTES = 2 * 1024 * 1024; // 2 MB
const ICON_DIR_REL = 'assets/icons/roles';

$iconDirAbs = ROOT_PATH . '/' . ICON_DIR_REL;
if (!is_dir($iconDirAbs)) {
    @mkdir($iconDirAbs, 0755, true);
}

if (empty($_FILES['icon']) || $_FILES['icon']['error'] !== UPLOAD_ERR_OK) {
    $errCode = $_FILES['icon']['error'] ?? UPLOAD_ERR_NO_FILE;
    $msg = match ($errCode) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Datei ist zu groß.',
        UPLOAD_ERR_NO_FILE => 'Keine Datei ausgewählt.',
        default => 'Upload fehlgeschlagen (Fehlercode ' . $errCode . ').',
    };
    jsonError($msg);
}

$file = $_FILES['icon'];

if ($file['size'] > MAX_UPLOAD_BYTES) {
    jsonError('Datei ist zu groß (max. 2 MB).');
}

// MIME-Typ über den tatsächlichen Dateiinhalt prüfen (nicht nur die
// vom Browser gemeldete Endung — die kann gefälscht sein).
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

$allowedMimes = [
    'image/png'  => 'png',
    'image/jpeg' => 'jpg',
];

if (!isset($allowedMimes[$mime])) {
    jsonError('Nur PNG- oder JPG-Dateien sind erlaubt (erkannt: ' . e($mime) . ').');
}

// Dateinamen generieren: rollenname-artiger Slug + kurzer Zufallswert,
// damit gleichzeitige Uploads sich nie überschreiben.
$baseNameInput = pathinfo($file['name'], PATHINFO_FILENAME);
$slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $baseNameInput), '-'));
if ($slug === '') $slug = 'icon';
$slug = substr($slug, 0, 40);
$uniqueSuffix = substr(bin2hex(random_bytes(4)), 0, 8);

$ext      = $allowedMimes[$mime];
$filename = "{$slug}-{$uniqueSuffix}.{$ext}";
$destAbs  = $iconDirAbs . '/' . $filename;

if (!move_uploaded_file($file['tmp_name'], $destAbs)) {
    jsonError('Datei konnte nicht gespeichert werden (Schreibrechte prüfen).');
}

bumpAssetVersion();

jsonOk('Icon gespeichert.', ['icon_path' => ICON_DIR_REL . '/' . $filename]);
