-- ============================================================
--  WERWOLF — Migration: Icon-Dateinamen an Rollennamen anpassen
--  Ausführen auf bestehenden Installationen.
--  Vorher: Dateien im Ordner assets/icons/roles/ umbenennen
--          (siehe README oder Commit-Nachricht).
-- ============================================================

UPDATE roles SET icon_path = 'assets/icons/roles/buerger.png'    WHERE id = 1;
UPDATE roles SET icon_path = 'assets/icons/roles/moerder.png'    WHERE id = 2;
UPDATE roles SET icon_path = 'assets/icons/roles/nekromant.png'  WHERE id = 3;
UPDATE roles SET icon_path = 'assets/icons/roles/hellseherin.png' WHERE id = 4;
UPDATE roles SET icon_path = 'assets/icons/roles/detektiv.png'   WHERE id = 5;
UPDATE roles SET icon_path = 'assets/icons/roles/das-paar.png'   WHERE id = 6;
UPDATE roles SET icon_path = 'assets/icons/roles/dodo.png'       WHERE id = 7;
UPDATE roles SET icon_path = 'assets/icons/roles/celebrity.png'  WHERE id = 8;
UPDATE roles SET icon_path = 'assets/icons/roles/gunslinger.png' WHERE id = 9;
UPDATE roles SET icon_path = 'assets/icons/roles/sheriff.png'    WHERE id = 10;
-- id 11 (Superstar) bleibt auf _default.png bis ein eigenes Bild hochgeladen wird
