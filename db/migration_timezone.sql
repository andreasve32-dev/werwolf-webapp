-- ============================================================
--  Migration: game_timezone Einstellung
--  Führe dies einmalig aus wenn die DB bereits existiert.
-- ============================================================

INSERT IGNORE INTO settings (`key`, value, type, label, description)
VALUES ('game_timezone', 'Europe/Berlin', 'string', 'Zeitzone', 'PHP-Zeitzone (z.B. Europe/Berlin, UTC). Wichtig wenn Server auf London-Zeit läuft.');
