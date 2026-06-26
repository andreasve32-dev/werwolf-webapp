-- Migration: Cooldown-Startzeitpunkt in game_players speichern
ALTER TABLE game_players
  ADD COLUMN cooldown_started_at TIMESTAMP NULL DEFAULT NULL
    AFTER last_ability_round;
