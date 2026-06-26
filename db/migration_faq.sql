-- ============================================================
--  WERWOLF — Migration: FAQ-Freigabe-Flag
-- ============================================================
--  Fügt der messages-Tabelle eine published-Spalte hinzu.
--  Idempotent durch IF NOT EXISTS (MySQL 8).
-- ============================================================

ALTER TABLE messages
  ADD COLUMN IF NOT EXISTS published TINYINT(1) NOT NULL DEFAULT 0;
