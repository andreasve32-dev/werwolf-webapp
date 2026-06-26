-- Migration: Todesursache entfernen, is_gehenkt + rolle_aufgedeckt hinzufügen
-- Ausführen wenn die App bereits installiert ist.

ALTER TABLE deaths
  DROP COLUMN cause,
  ADD COLUMN is_gehenkt       TINYINT(1) NOT NULL DEFAULT 0
    COMMENT '1 = vom Dorf abgestimmt und gehenkt'
    AFTER phase,
  ADD COLUMN rolle_aufgedeckt TINYINT(1) NOT NULL DEFAULT 0
    COMMENT '1 = Rolle wurde durch befragen enthüllt'
    AFTER zeit;
