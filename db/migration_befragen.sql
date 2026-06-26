-- ============================================================
--  Migration: befragen-Rolle + ort-Feld für Tode
--  Führe dies einmalig aus wenn die DB bereits existiert.
--  setup.php (schema.sql) enthält diese Spalten bereits.
-- ============================================================

ALTER TABLE deaths
  ADD COLUMN ort VARCHAR(255) NULL DEFAULT NULL AFTER phase;

ALTER TABLE roles
  ADD COLUMN befragen TINYINT(1) NOT NULL DEFAULT 0 AFTER sichtbar;
