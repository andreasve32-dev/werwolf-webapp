-- ============================================================
--  WERWOLF — Migration: Superstar-Rolle hinzufügen
--  Ausführen auf bestehenden Installationen, die bereits
--  über schema.sql eingerichtet wurden.
-- ============================================================

INSERT IGNORE INTO roles
  (id, name, cooldown, description, rules, active, fill, amount, icon_path, sichtbar, sort_order)
VALUES
  (11, 'Superstar', 0,
   'Wenn der Superstar stirbt, erfahren alle sofort, wer gestorben ist und wann.',
   'Stirbst du, wird dein Tod unmittelbar öffentlich bekannt — alle Spieler sehen deinen Namen und den genauen Zeitpunkt deines Todes. Der Spielleiter muss deinen Tod sofort ankündigen.',
   1, 0, 1, 'assets/icons/roles/_default.png', 0, 85);
