-- ============================================================
--  WERWOLF — Migration: Rollen von Code (ENUM) in DB-Tabelle
--  Nur ausführen, wenn bereits eine ÄLTERE Installation existiert,
--  die game_players.role / deaths.role als ENUM hatte.
--  Bei einer Neuinstallation einfach init.sql verwenden — diese
--  Datei wird dann nicht gebraucht.
-- ============================================================
USE werwolf;

-- 1. roles-Tabelle anlegen (falls noch nicht vorhanden)
CREATE TABLE IF NOT EXISTS roles (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(50)  NOT NULL UNIQUE,
  cooldown    INT          NOT NULL DEFAULT 0,
  description TEXT         NULL,
  rules       TEXT         NULL,
  active      TINYINT(1)   NOT NULL DEFAULT 1,
  amount      INT          NOT NULL DEFAULT 1,
  icon_path   VARCHAR(255) NULL,
  team        ENUM('village','wolves','solo') NOT NULL DEFAULT 'village',
  sichtbar    TINYINT(1)   NOT NULL DEFAULT 0,
  sort_order  INT          NOT NULL DEFAULT 0,
  created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 1b. Falls `roles` bereits aus einer Zwischen-Version ohne `sichtbar` existiert: nachrüsten.
ALTER TABLE roles ADD COLUMN IF NOT EXISTS sichtbar TINYINT(1) NOT NULL DEFAULT 0 AFTER team;

-- 2. Alte Standard-Rollen einfügen (gleiche IDs wie in init.sql, für konsistente Migration)
INSERT IGNORE INTO roles (id, name, cooldown, description, rules, active, amount, icon_path, team, sichtbar, sort_order) VALUES
(1, 'Dorfbewohner', 0, 'Ein einfacher Bewohner des Dorfes ohne Sonderfähigkeit.',     'Stimmt tagsüber für die Hinrichtung eines Verdächtigen ab.',                            1, 0, 'assets/icons/roles/villager.svg', 'village', 0, 10),
(2, 'Werwolf',      0, 'Verwandelt sich nachts und jagt die Dorfbewohner.',            'Wählt jede Nacht gemeinsam mit den anderen Wölfen ein Opfer.',                          1, 1, 'assets/icons/roles/werewolf.svg', 'wolves',  1, 20),
(3, 'Seher',        0, 'Kann nachts in die wahre Rolle eines Mitspielers blicken.',    'Wählt jede Nacht einen Spieler, dessen Rolle aufgedeckt wird.',                         1, 1, 'assets/icons/roles/seer.svg',     'village', 0, 30),
(4, 'Hexe',         0, 'Besitzt einen Heil- und einen Gifttrank, je einmal pro Spiel.', 'Kann das Opfer der Wölfe retten oder einen Spieler vergiften.',                        1, 1, 'assets/icons/roles/witch.svg',    'village', 0, 40),
(5, 'Jäger',        0, 'Reißt beim eigenen Tod einen weiteren Spieler mit.',           'Schießt sofort nach dem eigenen Tod auf einen Spieler seiner Wahl.',                    1, 1, 'assets/icons/roles/hunter.svg',   'village', 0, 50),
(6, 'Amor',         0, 'Verbindet zwei Spieler als Liebespaar.',                       'Stirbt einer der Verliebten, stirbt der andere ebenfalls.',                             1, 1, 'assets/icons/roles/amor.svg',     'village', 0, 60),
(7, 'Heiler',       1, 'Kann jede Nacht einen Spieler beschützen.',                    'Darf denselben Spieler nicht zwei Nächte in Folge schützen.',                           1, 1, 'assets/icons/roles/healer.svg',   'village', 0, 70),
(8, 'Killer',       2, 'Ein einsamer Mörder, der heimlich jeden tötet.',               'Darf alle 2 Nächte töten.',                                                             1, 1, 'assets/icons/roles/killer.svg',   'solo',    0, 80);

-- 3. role_id-Spalten ergänzen
ALTER TABLE game_players ADD COLUMN IF NOT EXISTS role_id INT NULL AFTER player_id;
ALTER TABLE game_players ADD COLUMN IF NOT EXISTS last_ability_round INT NULL AFTER is_alive;
ALTER TABLE deaths       ADD COLUMN IF NOT EXISTS role_id INT NULL AFTER player_id;

-- 4. Alte ENUM-Werte in role_id übersetzen
UPDATE game_players gp JOIN roles r ON r.name = CASE gp.role
  WHEN 'villager' THEN 'Dorfbewohner' WHEN 'werewolf' THEN 'Werwolf' WHEN 'seer' THEN 'Seher'
  WHEN 'witch' THEN 'Hexe' WHEN 'hunter' THEN 'Jäger' WHEN 'amor' THEN 'Amor' WHEN 'healer' THEN 'Heiler'
END SET gp.role_id = r.id;

UPDATE deaths d JOIN roles r ON r.name = CASE d.role
  WHEN 'villager' THEN 'Dorfbewohner' WHEN 'werewolf' THEN 'Werwolf' WHEN 'seer' THEN 'Seher'
  WHEN 'witch' THEN 'Hexe' WHEN 'hunter' THEN 'Jäger' WHEN 'amor' THEN 'Amor' WHEN 'healer' THEN 'Heiler'
END SET d.role_id = r.id;

-- 5. Foreign Keys ergänzen
ALTER TABLE game_players ADD CONSTRAINT fk_gp_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE SET NULL;
ALTER TABLE deaths       ADD CONSTRAINT fk_d_role  FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE SET NULL;

-- 6. Alte ENUM-Spalten entfernen (NUR ausführen, wenn Schritt 4 erfolgreich war!)
-- ALTER TABLE game_players DROP COLUMN role;
-- ALTER TABLE deaths       DROP COLUMN role;

-- Hinweis: deaths.cause hat ein neues ENUM-Mitglied 'killer'/'other' bekommen:
ALTER TABLE deaths MODIFY COLUMN cause ENUM('wolves','vote','hunter','witch','killer','other') DEFAULT 'vote';
