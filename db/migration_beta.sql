-- Migration: Beta-Modus-Einstellung hinzufügen
-- Ausführen wenn die App bereits installiert ist (schema.sql wurde schon eingespielt).

INSERT INTO settings (`key`, value, type, label, description, sort_order)
VALUES ('beta_mode', '1', 'bool', 'Beta-Modus', 'Zeigt einen Beta-Hinweis im Spielfenster an.', 16)
ON DUPLICATE KEY UPDATE type='bool', label='Beta-Modus', sort_order=16;
