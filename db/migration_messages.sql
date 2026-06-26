-- ============================================================
--  WERWOLF — Migration: Nachrichten-System
-- ============================================================
--  Spieler können Fragen an den Spielleiter senden.
--  Idempotent: CREATE TABLE IF NOT EXISTS.
-- ============================================================

CREATE TABLE IF NOT EXISTS messages (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  game_id        INT NULL,
  player_id      INT NOT NULL,
  message        TEXT NOT NULL,
  reply          TEXT NULL,
  created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  replied_at     TIMESTAMP NULL,
  read_by_player TINYINT(1) NOT NULL DEFAULT 0,
  FOREIGN KEY (game_id)   REFERENCES games(id)   ON DELETE SET NULL,
  FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
) ENGINE=InnoDB;
