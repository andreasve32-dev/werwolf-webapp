-- ============================================================
--  Migration: zeit-Feld für Tode
--  Führe dies einmalig aus wenn die DB bereits existiert.
--  setup.php (schema.sql) enthält diese Spalte bereits.
-- ============================================================

ALTER TABLE deaths
  ADD COLUMN zeit VARCHAR(50) NULL DEFAULT NULL AFTER ort;
