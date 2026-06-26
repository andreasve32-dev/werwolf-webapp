-- ============================================================
--  Migration: Gunslinger-Beschreibung korrigiert
--  Keine Schussbegrenzung; Tod nur bei Treffer auf Unschuldigen
-- ============================================================

UPDATE roles SET
  description = 'Kann beliebig oft schießen. Trifft er einen Killer, überlebt er. Trifft er einen Unschuldigen, stirbt er selbst.',
  rules       = 'Du hast eine Waffe ohne Schussbegrenzung. Schießt du auf einen Killer (z. B. Mörder), lebst du weiter und kannst erneut schießen. Triffst du einen Unschuldigen, stirbst du selbst. Du stehst auf Seite der Bürger.'
WHERE name = 'Gunslinger';
