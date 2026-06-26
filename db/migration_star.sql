-- ============================================================
--  Migration: Star-Rolle + auto_eintrag-Spalte
--  Führe dies einmalig aus wenn die DB bereits existiert.
-- ============================================================

ALTER TABLE roles
  ADD COLUMN auto_eintrag TINYINT(1) NOT NULL DEFAULT 0
    COMMENT '1 = Ort+Zeit beim Sterben sofort automatisch eintragen (Star)'
  AFTER befragen;

INSERT IGNORE INTO roles (name, cooldown, description, rules, active, fill, amount, icon_path, sichtbar, befragen, auto_eintrag, sort_order)
VALUES (
  'Star', 0,
  'Wenn gestorben wird, wird direkt Ort und Zeit eingetragen in der Todesliste.',
  'Du bist eine bekannte Persönlichkeit. Stirbst du, werden Todesort und Todeszeit sofort automatisch in der Todesliste eingetragen — ohne dass ein Nekromant dich besuchen muss.',
  1, 0, 1, 'assets/icons/roles/celebrity.png', 0, 0, 1, 86
);
