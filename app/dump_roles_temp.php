<?php
// Rollen-Backup-Seite (dauerhaft, NICHT löschen):
// gibt die komplette roles-Tabelle als fertiges INSERT-Statement aus,
// um den aktuellen Rollen-Stand zu sichern / woanders einzuspielen.
require_once dirname(__DIR__) . '/core/bootstrap.php';
Auth::requireAdmin();

header('Content-Type: text/plain; charset=utf-8');

$roles = Database::query("SELECT * FROM roles ORDER BY sort_order");

echo "-- Rollen-Dump " . date('Y-m-d H:i:s') . "\n";
echo "-- Anzahl: " . count($roles) . "\n\n";

if (!$roles) {
    echo "-- Keine Rollen vorhanden, kein INSERT erzeugt.\n";
    exit;
}

$pdo = Database::get();
$esc = fn($v) => $v === null ? 'NULL' : $pdo->quote($v);

echo "INSERT INTO roles (id, name, cooldown, description, rules, active, fill, amount, icon_path, sichtbar, killer_sichtbar, befragen, auto_eintrag, is_killer, sort_order) VALUES\n";

$lines = [];
foreach ($roles as $r) {
    $lines[] = sprintf(
        "(%d, %s, %d, %s, %s, %d, %d, %d, %s, %d, %d, %d, %d, %d, %d)",
        $r['id'], $esc($r['name']), (int)$r['cooldown'],
        $esc($r['description']), $esc($r['rules']),
        (int)$r['active'], (int)$r['fill'], (int)$r['amount'],
        $esc($r['icon_path']),
        (int)$r['sichtbar'], (int)($r['killer_sichtbar'] ?? 0), (int)$r['befragen'],
        (int)$r['auto_eintrag'], (int)$r['is_killer'],
        (int)$r['sort_order']
    );
}
echo implode(",\n", $lines) . ";\n";
