-- ============================================================
--  WERWOLF — Datenbankschema (nicht-destruktive Variante)
--  Datenbankverbindung: config/config.php
-- ============================================================
--  Für die Kommandozeile gedacht (mysql ... < db/init.sql).
--  Nutzt CREATE TABLE IF NOT EXISTS / INSERT IGNORE — bestehende
--  Daten bleiben erhalten, ideal für eine echte Erstinstallation.
--
--  Für einen GARANTIERT sauberen Reset stattdessen db/schema.sql
--  verwenden, oder über public/setup.php (empfohlen).
-- ============================================================

CREATE DATABASE IF NOT EXISTS werwolf CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE werwolf;

CREATE TABLE IF NOT EXISTS players (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(50)  NOT NULL UNIQUE,
  display_name  VARCHAR(50)  NOT NULL UNIQUE DEFAULT '',
  password_hash VARCHAR(255) NOT NULL,
  is_admin      TINYINT(1)   DEFAULT 0,
  created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS roles (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(50)  NOT NULL UNIQUE,
  cooldown    INT          NOT NULL DEFAULT 0,
  description TEXT         NULL,
  rules       TEXT         NULL,
  active      TINYINT(1)   NOT NULL DEFAULT 1,
  fill        TINYINT(1)   NOT NULL DEFAULT 0,
  amount      INT          NOT NULL DEFAULT 1,
  icon_path   VARCHAR(255) NULL,
  sichtbar    TINYINT(1)   NOT NULL DEFAULT 0,
  sort_order  INT          NOT NULL DEFAULT 0,
  created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS games (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  status     ENUM('lobby','running','finished') DEFAULT 'lobby',
  phase      ENUM('day','night')                DEFAULT 'day',
  round      INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS game_players (
  id                 INT AUTO_INCREMENT PRIMARY KEY,
  game_id            INT NOT NULL,
  player_id          INT NOT NULL,
  role_id            INT NULL,
  is_alive           TINYINT(1) DEFAULT 1,
  last_ability_round INT NULL,
  joined_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (game_id)   REFERENCES games(id)   ON DELETE CASCADE,
  FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
  FOREIGN KEY (role_id)   REFERENCES roles(id)   ON DELETE SET NULL,
  UNIQUE KEY uq_gp (game_id, player_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS deaths (
  id        INT AUTO_INCREMENT PRIMARY KEY,
  game_id   INT NOT NULL,
  player_id INT NOT NULL,
  role_id   INT NULL,
  cause     ENUM('wolves','vote','hunter','witch','killer','other') DEFAULT 'vote',
  round     INT NOT NULL,
  phase     ENUM('day','night'),
  died_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (game_id)   REFERENCES games(id)   ON DELETE CASCADE,
  FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
  FOREIGN KEY (role_id)   REFERENCES roles(id)   ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS night_actions (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  game_id          INT NOT NULL,
  round            INT NOT NULL,
  actor_player_id  INT NOT NULL,
  target_player_id INT NULL,
  action_type      ENUM('wolf_kill','seer_check','witch_kill','witch_save','hunter_shoot') NOT NULL,
  created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS votes (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  game_id    INT NOT NULL,
  round      INT NOT NULL,
  voter_id   INT NOT NULL,
  target_id  INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE,
  UNIQUE KEY uq_vote (game_id, round, voter_id)
) ENGINE=InnoDB;

-- Migration: fill-Spalte nachrüsten falls bereits vorhanden ohne sie
ALTER TABLE roles ADD COLUMN IF NOT EXISTS fill TINYINT(1) NOT NULL DEFAULT 0 AFTER active;

-- Rollen (fill=1 = Auffüll-Rolle, fill=0 = Sonderrolle)
INSERT IGNORE INTO roles (id, name, cooldown, description, rules, active, fill, amount, icon_path, sichtbar, sort_order) VALUES
(1,  'Bürger',      0,  'Einfacher Bürger ohne besondere Fähigkeiten.', 'Finde die Mörder durch Beobachten und Abstimmen. Berufe Versammlungen ein.', 1, 1, 0, 'assets/icons/roles/buerger.png', 0, 10),
(2,  'Mörder',      30, 'Kann andere Spieler mit der Mordwaffe töten (30 Min. Abklingzeit).', 'Zeige einem Spieler die Mordwaffe — er ist sofort tot. 30 Min. Abklingzeit. Arbeite mit dem anderen Mörder zusammen. Öffne beim Start die Augen wenn Mörder aufgerufen.', 1, 0, 2, 'assets/icons/roles/moerder.png', 1, 20),
(3,  'Nekromant',   0,  'Kann tote Spieler befragen.', 'Zeige einem toten Spieler deine Karte — er trägt Todeszeitpunkt und -ort in die Liste ein.', 1, 0, 1, 'assets/icons/roles/nekromant.png', 0, 30),
(4,  'Hellseher',   30, 'Kann alle 30 Min. einen Spieler zwingen, seine Rolle aufzudecken.', 'Zeige einem Spieler deine Karte — er muss dir seine Rolle zeigen. Abklingzeit: 30 Min.', 1, 0, 1, 'assets/icons/roles/hellseherin.png', 0, 40),
(5,  'Detektiv',    0,  'Kann Spieler durchsuchen — trägt der Mörder die Waffe, ist er entlarvt.', 'Durchsuche einen Spieler. Trägt er die Mordwaffe, muss er sie abgeben.', 1, 0, 1, 'assets/icons/roles/detektiv.png', 0, 50),
(6,  'Das Paar',    0,  '2 Spieler bilden ein Paar und kennen sich von Beginn an.', 'Öffne beim Start die Augen wenn das Paar aufgerufen wird — ihr kennt euren Partner. Stirbt dein Partner, nimmst du dir das Leben sobald du es bemerkst.', 1, 0, 2, 'assets/icons/roles/das-paar.png', 1, 60),
(7,  'Dodo',        0,  'Gewinnt das Spiel, indem er von der Gruppe erhängt wird.', 'Du gewinnst wenn die Versammlung dich erhängt. Wirst du von einem Mörder getötet, hat deine Rolle keine Auswirkung. Trage KEINE Mordwaffe.', 1, 0, 1, 'assets/icons/roles/dodo.png', 0, 70),
(8,  'Celebrity',   0,  'Sein Tod fällt sofort auf.', 'Du bist bekannt. Stirbst du, trägst du sofort Zeit und Ort in die Todesliste ein.', 0, 0, 1, 'assets/icons/roles/celebrity.png', 0, 80),
(9,  'Gunslinger',  0,  'Trägt eine Waffe und kann einmalig pro Spiel einen Spieler erschießen.', 'Du hast eine Waffe und kannst einmalig schießen. Seite der Bürger. Wirst du ermordet, darfst du nicht mehr schießen.', 0, 0, 1, 'assets/icons/roles/gunslinger.png', 0, 90),
(10, 'Sheriff',     0,  'Kann unbegrenzt schießen — tötet er einen Unschuldigen, stirbt er selbst.', 'Schieß auf Mörder oder Dodo. Triffst du einen Bürger oder die Hellseherin, stirbst du selbst.', 0, 0, 1, 'assets/icons/roles/sheriff.png', 0, 100);

-- idempotente Migration: Icon-Pfade auf tatsächliche PNG-Dateien aktualisieren
UPDATE roles SET icon_path='assets/icons/roles/buerger.png'    WHERE name='Bürger'      AND (icon_path LIKE '%.svg' OR icon_path IS NULL);
UPDATE roles SET icon_path='assets/icons/roles/moerder.png'    WHERE name='Mörder'      AND (icon_path LIKE '%.svg' OR icon_path IS NULL);
UPDATE roles SET icon_path='assets/icons/roles/nekromant.png'  WHERE name='Nekromant'   AND (icon_path LIKE '%.svg' OR icon_path IS NULL);
UPDATE roles SET icon_path='assets/icons/roles/hellseherin.png'WHERE name='Hellseherin' AND (icon_path LIKE '%.svg' OR icon_path IS NULL);
UPDATE roles SET name='Hellseher' WHERE name='Hellseherin';
UPDATE roles SET icon_path='assets/icons/roles/detektiv.png'   WHERE name='Detektiv'    AND (icon_path LIKE '%.svg' OR icon_path IS NULL);
UPDATE roles SET icon_path='assets/icons/roles/das-paar.png'   WHERE name='Das Paar'    AND (icon_path LIKE '%.svg' OR icon_path IS NULL);
UPDATE roles SET icon_path='assets/icons/roles/dodo.png'       WHERE name='Dodo'        AND (icon_path LIKE '%.svg' OR icon_path IS NULL);
UPDATE roles SET icon_path='assets/icons/roles/celebrity.png'  WHERE name='Celebrity'   AND (icon_path LIKE '%.svg' OR icon_path IS NULL);
UPDATE roles SET icon_path='assets/icons/roles/gunslinger.png' WHERE name='Gunslinger'  AND (icon_path LIKE '%.svg' OR icon_path IS NULL);
UPDATE roles SET icon_path='assets/icons/roles/sheriff.png'    WHERE name='Sheriff'     AND (icon_path LIKE '%.svg' OR icon_path IS NULL);

INSERT IGNORE INTO games (id, status) VALUES (1, 'lobby');

-- Settings-Tabelle (wird auch von migration_settings.sql abgedeckt)
CREATE TABLE IF NOT EXISTS settings (
  `key`       VARCHAR(80)   NOT NULL,
  value       TEXT          NOT NULL,
  type        ENUM('string','int','bool') NOT NULL DEFAULT 'string',
  label       VARCHAR(100)  NOT NULL,
  description VARCHAR(255)  NOT NULL DEFAULT '',
  sort_order  INT           NOT NULL DEFAULT 0,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB;

INSERT IGNORE INTO settings (`key`, value, type, label, description, sort_order) VALUES
('app_name',           'Werwolf',                          'string', 'Spielname',              'Anzeigename der App — überall sichtbar.',                         10),
('app_version',        '1.0.0',                           'string', 'Versionsnummer',          'Anzeigeversion z. B. in Fußzeile oder About-Seite.',             15),
('app_debug',          '1',                               'bool',   'Debug-Modus',             'PHP-Fehler anzeigen. Im Produktivbetrieb auf 0 setzen.',          20),
('default_theme',      'gothic',                          'string', 'Standard-Theme',          'Theme für neue Nutzer ohne gespeichertes Theme.',                30),
('login_title',        'Willkommen zurück',                         'string', 'Login-Kartentitel',       'Überschrift der Anmeldekarte.',                        34),
('login_subtitle',     'Das Dorf schläft … doch die Wölfe nicht.', 'string', 'Login-Untertitel',        'Slogan unter dem Logo auf der Anmeldeseite.',          35),
('register_subtitle',  'Tritt dem Dorf bei',                        'string', 'Registrierungs-Untertitel','Text unter dem Logo auf der Registrierungsseite.',   36),
('min_players',        '4',                               'int',    'Mindest-Spielerzahl',     'Mindestanzahl Spieler für den Spielstart.',                       40),
('max_players',        '30',                              'int',    'Maximal-Spielerzahl',     'Maximale Anzahl Spieler pro Spiel.',                              50),
('background_music',   'background.mp3',                  'string', 'Hintergrundmusik',        'Dateiname in assets/audio/. Leer = kein Player.',                60),
('default_role_icon',  'assets/icons/roles/_default.png', 'string', 'Standard-Rollen-Icon',   'Fallback-Icon-Pfad wenn eine Rolle kein eigenes Icon hat.',       70),
('session_lifetime',   '604800',                          'int',    'Session-Dauer (Sek.)',    'Anmeldedauer in Sekunden (604800 = 7 Tage, 86400 = 1 Tag).',     80),
('deaths_empty_title', 'Noch niemand gestorben',          'string', 'Todesliste: Leertitel',   'Überschrift wenn noch niemand gestorben ist.',                    90),
('deaths_empty_sub',   'Das Dorf ist in Frieden … noch.','string', 'Todesliste: Leer-Subtext','Untertitel wenn noch niemand gestorben ist.',                     91),
('deaths_peace_text',  'Mögen sie in Frieden ruhen',      'string', 'Todesliste: Friedenstext','Text unter dem Friedhof-Bereich wenn Tote vorhanden sind.',      92),
('login_logo',         'assets/icons/logo/login_logo.png','string', 'Login-Logo',              'Pfad zum PNG-Bild auf der Anmeldeseite (leer = Wolf-Emoji 🐺).', 5),
('mini_logo',          'assets/icons/logo/mini_logo.png', 'string', 'Browser-Icon (Favicon)',  'PNG-Datei für die Browser-Adressleiste / Tab-Icon.',              6),
('asset_version',      '1',                               'int',    'Asset-Version',           'Wird bei jedem Bild-Upload erhöht (Cache-Busting).',             999);

-- Migration: display_name nachrüsten (bestehende Installationen)
ALTER TABLE players ADD COLUMN IF NOT EXISTS display_name VARCHAR(50) NOT NULL DEFAULT '' AFTER username;
UPDATE players SET display_name = username WHERE display_name = '';
SET @dn_idx = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='players' AND INDEX_NAME='display_name');
SET @dn_sql = IF(@dn_idx=0,'ALTER TABLE players ADD UNIQUE KEY display_name (display_name)','SELECT 1');
PREPARE dn_stmt FROM @dn_sql; EXECUTE dn_stmt; DEALLOCATE PREPARE dn_stmt;

-- idempotente Migration: team-Spalte entfernen (falls noch vorhanden aus älterer Installation)
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='roles' AND COLUMN_NAME='team');
SET @sql = IF(@col_exists=1,'ALTER TABLE roles DROP COLUMN team','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- idempotente Migration für sichtbar (falls ältere Installation ohne sichtbar-Spalte)
SET @col2_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='roles' AND COLUMN_NAME='sichtbar');
SET @sql2 = IF(@col2_exists=0,'ALTER TABLE roles ADD COLUMN sichtbar TINYINT(1) NOT NULL DEFAULT 0 AFTER amount','SELECT 1');
PREPARE stmt2 FROM @sql2; EXECUTE stmt2; DEALLOCATE PREPARE stmt2;
UPDATE roles SET sichtbar=1 WHERE name='Mörder' AND sichtbar=0;
UPDATE roles SET sichtbar=1 WHERE name='Das Paar' AND sichtbar=0;

-- idempotente Migration: befragen / auto_eintrag / is_killer Spalten in roles
ALTER TABLE roles ADD COLUMN IF NOT EXISTS befragen    TINYINT(1) NOT NULL DEFAULT 0 AFTER sichtbar;
ALTER TABLE roles ADD COLUMN IF NOT EXISTS auto_eintrag TINYINT(1) NOT NULL DEFAULT 0 AFTER befragen;
ALTER TABLE roles ADD COLUMN IF NOT EXISTS is_killer    TINYINT(1) NOT NULL DEFAULT 0 AFTER auto_eintrag;
UPDATE roles SET befragen=1    WHERE name='Nekromant' AND befragen=0;

-- idempotente Migration: Push-Einstellungen (push_cooldown + push_last_sent)
INSERT IGNORE INTO settings (`key`, value, type, label, description, sort_order) VALUES
('push_cooldown',  '30', 'int', 'Push-Cooldown (Min.)',          'Mindestwartezeit zwischen zwei Auto-Push-Benachrichtigungen.',    26),
('push_last_sent', '0',  'int', 'Push: letzter Versand (intern)', 'Unix-Timestamp des letzten gesendeten Pushes (intern).',         999);

-- idempotente Migration: deaths-Spalten nachrüsten
ALTER TABLE deaths ADD COLUMN IF NOT EXISTS ort             VARCHAR(255) NULL DEFAULT NULL AFTER phase;
ALTER TABLE deaths ADD COLUMN IF NOT EXISTS zeit            VARCHAR(50)  NULL DEFAULT NULL AFTER ort;
ALTER TABLE deaths ADD COLUMN IF NOT EXISTS is_gehenkt      TINYINT(1) NOT NULL DEFAULT 0 AFTER phase;
ALTER TABLE deaths ADD COLUMN IF NOT EXISTS rolle_aufgedeckt TINYINT(1) NOT NULL DEFAULT 0 AFTER zeit;
-- cause-Spalte entfernen (aus älterer Schema-Version)
SET @col_cause = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='deaths' AND COLUMN_NAME='cause');
SET @sql_cause = IF(@col_cause=1,'ALTER TABLE deaths DROP COLUMN cause','SELECT 1');
PREPARE stmt_cause FROM @sql_cause; EXECUTE stmt_cause; DEALLOCATE PREPARE stmt_cause;
