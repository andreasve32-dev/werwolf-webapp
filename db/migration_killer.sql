-- Migration: is_killer-Flag für Rollen
-- Markiert Rollen die zum Killer-Team gehören (Gewinn-Bedingung)

ALTER TABLE roles ADD COLUMN IF NOT EXISTS is_killer TINYINT(1) NOT NULL DEFAULT 0
    COMMENT '1 = Killerteam (gewinnen wenn >= Überlebende Nicht-Killer)';

-- Mörder ist standardmäßig Killer-Rolle
UPDATE roles SET is_killer = 1 WHERE name = 'Mörder';
