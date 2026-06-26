-- ============================================================
--  Migration: Push-Einstellungen (push_cooldown + push_last_sent)
--  Für bestehende Installationen ausführen, die schema.sql nicht
--  neu aufgesetzt haben.
-- ============================================================

INSERT IGNORE INTO settings (`key`, value, type, label, description, sort_order) VALUES
('push_cooldown',  '30', 'int', 'Push-Cooldown (Min.)',           'Mindestwartezeit zwischen zwei Auto-Push-Benachrichtigungen.',   26),
('push_last_sent', '0',  'int', 'Push: letzter Versand (intern)', 'Unix-Timestamp des letzten gesendeten Pushes (intern).',        999);
