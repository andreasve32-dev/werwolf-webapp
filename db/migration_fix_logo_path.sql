-- ============================================================
--  Migration: Logo-Pfade auf assets/icons/logo/ korrigieren
--  Für Installationen die noch den alten assets/images/-Pfad
--  in der settings-Tabelle gespeichert haben.
--  Kann mehrfach ausgeführt werden (idempotent).
-- ============================================================

INSERT INTO settings (`key`, value, type, label, description, sort_order)
VALUES ('login_logo', 'assets/icons/logo/login_logo.png', 'string', 'Login-Logo', 'Pfad zum PNG-Bild auf der Anmeldeseite (leer = Wolf-Emoji 🐺).', 5)
ON DUPLICATE KEY UPDATE
  value = IF(value NOT LIKE 'assets/icons/logo/%', 'assets/icons/logo/login_logo.png', value);

INSERT INTO settings (`key`, value, type, label, description, sort_order)
VALUES ('mini_logo', 'assets/icons/logo/mini_logo.png', 'string', 'Browser-Icon (Favicon)', 'PNG-Datei für die Browser-Adressleiste / Tab-Icon.', 6)
ON DUPLICATE KEY UPDATE
  value = IF(value NOT LIKE 'assets/icons/logo/%', 'assets/icons/logo/mini_logo.png', value);
