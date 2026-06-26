-- ============================================================
--  Migration: winner-Spalte in games (speichert Spielsieger)
-- ============================================================
ALTER TABLE games
  ADD COLUMN winner ENUM('killer','citizen','dodo') NULL DEFAULT NULL
  AFTER status;
