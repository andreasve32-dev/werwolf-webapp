-- ============================================================
--  WERWOLF — Datenbankschema (nicht-destruktive Variante)
-- ============================================================
--  Für die Kommandozeile: mysql -u root -p werwolf < db/init.sql
--  Nutzt CREATE TABLE IF NOT EXISTS / INSERT IGNORE — bestehende
--  Daten bleiben erhalten.
--
--  Für einen sauberen Neuaufbau: db/schema.sql verwenden
--  oder den Setup-Assistenten (admin/setup.php) nutzen.
-- ============================================================

CREATE DATABASE IF NOT EXISTS werwolf CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE werwolf;

-- ── Tabellen ────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS players (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(50)  NOT NULL UNIQUE,
  display_name  VARCHAR(50)  NOT NULL UNIQUE DEFAULT '',
  password_hash VARCHAR(255) NOT NULL,
  is_admin      TINYINT(1)   NOT NULL DEFAULT 0,
  settings      TEXT         NULL,
  created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS roles (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  name         VARCHAR(50)  NOT NULL UNIQUE,
  cooldown     INT          NOT NULL DEFAULT 0,
  description  TEXT         NULL,
  rules        TEXT         NULL,
  active       TINYINT(1)   NOT NULL DEFAULT 1,
  fill         TINYINT(1)   NOT NULL DEFAULT 0,
  amount       INT          NOT NULL DEFAULT 1,
  icon_path    VARCHAR(255) NULL,
  sichtbar     TINYINT(1)   NOT NULL DEFAULT 0,
  killer_sichtbar TINYINT(1) NOT NULL DEFAULT 0,
  befragen     TINYINT(1)   NOT NULL DEFAULT 0,
  auto_eintrag TINYINT(1)   NOT NULL DEFAULT 0,
  is_killer    TINYINT(1)   NOT NULL DEFAULT 0,
  sort_order   INT          NOT NULL DEFAULT 0,
  linked_death TINYINT(1)   NOT NULL DEFAULT 0,
  created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Rollen-Presets: gespeicherte Rollensets (Snapshot von active/amount/fill)
CREATE TABLE IF NOT EXISTS role_presets (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(50) NOT NULL UNIQUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS role_preset_items (
  id        INT AUTO_INCREMENT PRIMARY KEY,
  preset_id INT NOT NULL,
  role_id   INT NOT NULL,
  active    TINYINT(1) NOT NULL DEFAULT 1,
  amount    INT        NOT NULL DEFAULT 1,
  fill      TINYINT(1) NOT NULL DEFAULT 0,
  FOREIGN KEY (preset_id) REFERENCES role_presets(id) ON DELETE CASCADE,
  FOREIGN KEY (role_id)   REFERENCES roles(id)        ON DELETE CASCADE,
  UNIQUE KEY uq_preset_role (preset_id, role_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS games (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  status     ENUM('lobby','running','finished') NOT NULL DEFAULT 'lobby',
  winner     ENUM('killer','citizen','dodo')    NULL DEFAULT NULL,
  phase      ENUM('day','night')                NOT NULL DEFAULT 'day',
  round      INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS game_players (
  id                  INT AUTO_INCREMENT PRIMARY KEY,
  game_id             INT NOT NULL,
  player_id           INT NOT NULL,
  role_id             INT NULL,
  is_alive            TINYINT(1)  NOT NULL DEFAULT 1,
  last_ability_round  INT         NULL,
  cooldown_started_at TIMESTAMP   NULL DEFAULT NULL,
  joined_at           TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (game_id)   REFERENCES games(id)   ON DELETE CASCADE,
  FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
  FOREIGN KEY (role_id)   REFERENCES roles(id)   ON DELETE SET NULL,
  UNIQUE KEY uq_gp (game_id, player_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS deaths (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  game_id          INT NOT NULL,
  player_id        INT NOT NULL,
  role_id          INT NULL,
  round            INT NOT NULL,
  phase            ENUM('day','night') NOT NULL,
  is_gehenkt       TINYINT(1)   NOT NULL DEFAULT 0,
  ort              VARCHAR(255) NULL DEFAULT NULL,
  zeit             VARCHAR(50)  NULL DEFAULT NULL,
  rolle_aufgedeckt TINYINT(1)   NOT NULL DEFAULT 0,
  died_at          TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
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

CREATE TABLE IF NOT EXISTS messages (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  game_id        INT NULL,
  player_id      INT NOT NULL,
  message        TEXT NOT NULL,
  reply          TEXT NULL,
  created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  replied_at     TIMESTAMP NULL,
  read_by_player TINYINT(1) NOT NULL DEFAULT 0,
  published      TINYINT(1) NOT NULL DEFAULT 0,
  FOREIGN KEY (game_id)   REFERENCES games(id)   ON DELETE SET NULL,
  FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
) ENGINE=InnoDB;
ALTER TABLE messages ADD COLUMN IF NOT EXISTS faq_question TEXT NULL AFTER message;

CREATE TABLE IF NOT EXISTS slogans (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  text       VARCHAR(255) NOT NULL UNIQUE,
  phase      ENUM('day','night') NOT NULL DEFAULT 'day',
  active     TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT IGNORE INTO slogans (text, phase) VALUES
('Die Kuh von Bauer Franz schaut mich heute komisch an','day'),
('30 Grad im Schatten und der Dorfbrunnen riecht wieder komisch','day'),
('Irgendjemand hat die Scheunentür schon wieder offengelassen','day'),
('Der Schmied hat sich heute schon dreimal auf den Daumen gehauen','day'),
('Die alte Marie hat mal wieder komisch geschaut','day'),
('Das Bier im Wirtshaus ist schon alle — um zehn Uhr morgens','day'),
('Jemand hat die letzte Wurst vom Markt geklaut','day'),
('Die Gänse vom Müller sind nervöser als üblich','day'),
('Pfarrer Klemens hat die Predigt heute auf drei Stunden ausgedehnt','day'),
('Die Milch vom Huber-Hof ist heute besonders sauer','day'),
('Seltsame Fußspuren im Morast hinter der Mühle','day'),
('Die Katze des Pfarrers ist seit Dienstag weg','day'),
('Das Kalb vom Bauern Huber muht nur noch rückwärts','day'),
('Drei Krähen kreisen seit dem Morgengrauen über dem Kirchturm','day'),
('Die Hühner legen seit einer Woche keine Eier mehr','day'),
('Im Dorf herrscht eine trügerische Stille','day'),
('Der Bürgermeister schläft schon wieder im Rathaus','day'),
('Heute gibt es Rübensuppe beim Wirt — schon das vierte Mal diese Woche','day'),
('Die Wetterfahne zeigt seit gestern in keine Richtung mehr','day'),
('Der Hund vom Förster bellt seit gestern Nacht am Stück','day'),
('Die Nacht hat tausend Augen — und zwei davon gehören dem Mörder','night'),
('Schließt die Türen und löscht die Lichter','night'),
('Irgendwo bellt ein Hund — schon seit einer Stunde','night'),
('Der Wind trägt heute Nacht seltsame Geräusche mit sich','night'),
('Niemand geht nachts alleine zum Brunnen','night'),
('Das Flackern der Kerzen macht alle nervös','night'),
('Hinter jedem Schatten könnte einer lauern','night'),
('Die Eulen schreien heute besonders laut','night'),
('Der Vollmond lügt nicht über verdächtige Gesichter','night'),
('Schlaf gut — falls du kannst','night'),
('Jemand schleicht um die Scheune herum','night'),
('Die Nacht macht alle gleich — gleich verdächtig','night'),
('Kein Licht im Dorf brennt mehr — außer einem','night'),
('Selbst die Ratten sind heute stiller als sonst','night'),
('Der Nebel kriecht vom Wald ins Dorf herein','night'),
('Heute Nacht öffnet sich die Scheunentür von selbst','night'),
('Wer jetzt noch draußen ist, dem ist nicht zu helfen','night'),
('Die Uhr am Kirchturm schlägt dreizehn','night'),
('Im Wald raschelt es — hoffentlich nur der Wind','night'),
('Drei Stimmen im Dunkeln, aber nur zwei Schatten','night');

-- day_slogans aus settings entfernen (wird durch slogans-Tabelle ersetzt)
DELETE FROM settings WHERE `key` = 'day_slogans';

CREATE TABLE IF NOT EXISTS assembly_requests (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  game_id      INT NOT NULL,
  player_id    INT NOT NULL,
  supporter_id INT NULL,
  scheduled_at INT NULL,
  notified     TINYINT(1) NOT NULL DEFAULT 0,
  called_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  ended_at     TIMESTAMP NULL DEFAULT NULL,
  FOREIGN KEY (game_id)      REFERENCES games(id)   ON DELETE CASCADE,
  FOREIGN KEY (player_id)    REFERENCES players(id) ON DELETE CASCADE,
  FOREIGN KEY (supporter_id) REFERENCES players(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Nachrüsten falls Tabelle bereits existiert
ALTER TABLE assembly_requests ADD COLUMN IF NOT EXISTS ended_at TIMESTAMP NULL DEFAULT NULL AFTER called_at;
ALTER TABLE assembly_requests ADD COLUMN IF NOT EXISTS supporter_id INT NULL AFTER player_id;
ALTER TABLE assembly_requests MODIFY scheduled_at INT NULL;

CREATE TABLE IF NOT EXISTS push_subscriptions (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  player_id  INT NOT NULL,
  endpoint   TEXT NOT NULL,
  p256dh     VARCHAR(512) DEFAULT NULL,
  auth       VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_player_endpoint (player_id, endpoint(191)),
  FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS settings (
  `key`       VARCHAR(80)  NOT NULL,
  value       TEXT         NOT NULL,
  type        ENUM('string','int','bool') NOT NULL DEFAULT 'string',
  label       VARCHAR(100) NOT NULL DEFAULT '',
  description VARCHAR(255) NOT NULL DEFAULT '',
  sort_order  INT          NOT NULL DEFAULT 0,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB;

-- ── Spalten nachrüsten (für bestehende Installationen) ──────
-- Läuft ohne Fehler wenn Spalten bereits existieren (IF NOT EXISTS / IGNORE).

ALTER TABLE players
  ADD COLUMN IF NOT EXISTS display_name VARCHAR(50) NOT NULL DEFAULT '' AFTER username;
UPDATE players SET display_name = username WHERE display_name = '';

ALTER TABLE roles
  ADD COLUMN IF NOT EXISTS fill            TINYINT(1) NOT NULL DEFAULT 0 AFTER active,
  ADD COLUMN IF NOT EXISTS sichtbar        TINYINT(1) NOT NULL DEFAULT 0 AFTER icon_path,
  ADD COLUMN IF NOT EXISTS killer_sichtbar TINYINT(1) NOT NULL DEFAULT 0 AFTER sichtbar,
  ADD COLUMN IF NOT EXISTS befragen        TINYINT(1) NOT NULL DEFAULT 0 AFTER killer_sichtbar,
  ADD COLUMN IF NOT EXISTS auto_eintrag    TINYINT(1) NOT NULL DEFAULT 0 AFTER befragen,
  ADD COLUMN IF NOT EXISTS is_killer       TINYINT(1) NOT NULL DEFAULT 0 AFTER auto_eintrag,
  ADD COLUMN IF NOT EXISTS sort_order      INT        NOT NULL DEFAULT 0  AFTER is_killer,
  ADD COLUMN IF NOT EXISTS linked_death    TINYINT(1) NOT NULL DEFAULT 0  AFTER sort_order;

ALTER TABLE games
  ADD COLUMN IF NOT EXISTS winner ENUM('killer','citizen','dodo') NULL DEFAULT NULL AFTER status;

ALTER TABLE game_players
  ADD COLUMN IF NOT EXISTS cooldown_started_at TIMESTAMP NULL DEFAULT NULL AFTER last_ability_round;

ALTER TABLE deaths
  ADD COLUMN IF NOT EXISTS is_gehenkt       TINYINT(1)   NOT NULL DEFAULT 0   AFTER phase,
  ADD COLUMN IF NOT EXISTS ort              VARCHAR(255) NULL DEFAULT NULL     AFTER is_gehenkt,
  ADD COLUMN IF NOT EXISTS zeit             VARCHAR(50)  NULL DEFAULT NULL     AFTER ort,
  ADD COLUMN IF NOT EXISTS rolle_aufgedeckt TINYINT(1)   NOT NULL DEFAULT 0   AFTER zeit;

-- cause-Spalte entfernen falls noch vorhanden (alte Schema-Version)
SET @_has_cause = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='deaths' AND COLUMN_NAME='cause');
SET @_sql = IF(@_has_cause=1,'ALTER TABLE deaths DROP COLUMN cause','SELECT 1');
PREPARE _stmt FROM @_sql; EXECUTE _stmt; DEALLOCATE PREPARE _stmt;

-- team-Spalte in roles entfernen falls noch vorhanden
SET @_has_team = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='roles' AND COLUMN_NAME='team');
SET @_sql2 = IF(@_has_team=1,'ALTER TABLE roles DROP COLUMN team','SELECT 1');
PREPARE _stmt2 FROM @_sql2; EXECUTE _stmt2; DEALLOCATE PREPARE _stmt2;

-- display_name Unique-Index nachrüsten
SET @_dn_idx = (SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='players' AND INDEX_NAME='display_name');
SET @_sql3 = IF(@_dn_idx=0,'ALTER TABLE players ADD UNIQUE KEY display_name (display_name)','SELECT 1');
PREPARE _stmt3 FROM @_sql3; EXECUTE _stmt3; DEALLOCATE PREPARE _stmt3;

-- ── Seed-Daten ───────────────────────────────────────────────

INSERT IGNORE INTO roles
  (id, name, cooldown, description, rules, active, fill, amount, icon_path, sichtbar, befragen, auto_eintrag, is_killer, sort_order)
VALUES
(1,  'Bürger',    0,
  'Einfacher Bürger ohne besondere Fähigkeiten.',
  'Finde die Mörder durch Beobachten und Abstimmen. Berufe Versammlungen ein.',
  1, 1, 0, 'assets/icons/roles/buerger.png', 0, 0, 0, 0, 10),

(2,  'Mörder',    30,
  'Kann andere Spieler mit der Mordwaffe töten. Abklingzeit: 30 Minuten.',
  'Zeige einem anderen Spieler die Mordwaffe — dieser ist sofort tot und trägt sich in die Todesliste ein. Die Mordwaffe hat 30 Minuten Abklingzeit. Arbeite mit dem anderen Mörder zusammen.',
  1, 0, 2, 'assets/icons/roles/moerder.png', 1, 0, 0, 1, 20),

(3,  'Nekromant', 0,
  'Kann tote Spieler befragen, indem er ihnen seine Karte zeigt.',
  'Zeige einem toten Spieler deine Karte. Dieser trägt Todeszeitpunkt und -ort in die Todesliste ein und gibt so mehr Informationen preis.',
  1, 0, 1, 'assets/icons/roles/nekromant.png', 0, 1, 0, 0, 30),

(4,  'Hellseherin', 30,
  'Kann alle 30 Minuten einen Spieler zwingen, seine Rolle aufzudecken.',
  'Zeige einem Spieler deine Karte — er muss dir seine Rolle zeigen. Abklingzeit: 30 Minuten.',
  1, 0, 1, 'assets/icons/roles/hellseherin.png', 0, 0, 0, 0, 40),

(5,  'Detektiv',  0,
  'Kann Spieler durchsuchen. Trägt der Mörder die Waffe, ist er entlarvt.',
  'Durchsuche einen Spieler. Trägt er die Mordwaffe, muss er sie abgeben. Stirbt der Detektiv danach, kann die Waffe vom zweiten Mörder zurückgeholt werden.',
  1, 0, 1, 'assets/icons/roles/detektiv.png', 0, 0, 0, 0, 50),

(6,  'Das Paar',  0,
  '2 Spieler bilden ein Paar und kennen sich von Beginn an.',
  'Öffne beim Spielstart die Augen wenn das Paar aufgerufen wird — ihr kennt euren Partner. Stirbt dein Partner, nimmst du dir das Leben sobald du seinen Tod bemerkst.',
  1, 0, 2, 'assets/icons/roles/das-paar.png', 1, 0, 0, 0, 60),

(7,  'Dodo',      0,
  'Gewinnt das Spiel, indem er von der Gruppe erhängt wird.',
  'Du gewinnst, wenn die Versammlung dich erhängt. Wirst du von einem Mörder getötet, hat deine Rolle keine Auswirkung. Du darfst KEINE Mordwaffe bei dir tragen.',
  1, 0, 1, 'assets/icons/roles/dodo.png', 0, 0, 0, 0, 70),

(8,  'Celebrity', 0,
  'Sein Tod fällt sofort auf — er trägt direkt Todeszeitpunkt und Ort ein.',
  'Du bist allgemein bekannt. Stirbst du, trägst du sofort Zeitpunkt und Ort in die Todesliste ein.',
  1, 0, 1, 'assets/icons/roles/celebrity.png', 0, 0, 1, 0, 80),

(9,  'Gunslinger', 0,
  'Kann beliebig oft schießen. Trifft er einen Killer, überlebt er. Trifft er einen Unschuldigen, stirbt er selbst.',
  'Du hast eine Waffe ohne Schussbegrenzung. Schießt du auf einen Killer (z. B. Mörder), lebst du weiter und kannst erneut schießen. Triffst du einen Unschuldigen, stirbst du selbst. Du stehst auf Seite der Bürger.',
  1, 0, 1, 'assets/icons/roles/gunslinger.png', 0, 0, 0, 0, 90),

(10, 'Sheriff',   0,
  'Kann unbegrenzt Spieler erschießen — tötet er jedoch einen Unschuldigen, stirbt er selbst.',
  'Du kannst so viele Spieler erschießen wie du willst. Tötest du jedoch nicht den Dodo oder einen Mörder, stirbst du selbst.',
  1, 0, 1, 'assets/icons/roles/sheriff.png', 0, 0, 0, 0, 100);

-- Bekannte Rollen-Flags sicherstellen (falls INSERT IGNORE übersprungen)
UPDATE roles SET befragen=1  WHERE name='Nekromant'  AND befragen=0;
UPDATE roles SET auto_eintrag=1 WHERE name='Celebrity' AND auto_eintrag=0;
UPDATE roles SET is_killer=1 WHERE name='Mörder'     AND is_killer=0;
UPDATE roles SET sichtbar=1  WHERE name IN ('Mörder','Das Paar') AND sichtbar=0;
UPDATE roles SET linked_death=1 WHERE name='Das Paar' AND linked_death=0;

INSERT IGNORE INTO games (id, status) VALUES (1, 'lobby');

INSERT IGNORE INTO settings (`key`, value, type, label, description, sort_order) VALUES
('app_name',           'Werwolf',                                       'string', 'Spielname',                     'Anzeigename der App — überall sichtbar.',                              10),
('app_version',        '0.0.20',                                        'string', 'Versionsnummer',                'Anzeigeversion z. B. in Fußzeile oder About-Seite.',                   15),
('beta_mode',          '1',                                             'bool',   'Beta-Modus',                    'Zeigt einen Beta-Hinweis im Spielfenster an.',                         16),
('app_debug',          '1',                                             'bool',   'Debug-Modus',                   'PHP-Fehler anzeigen. Im Produktivbetrieb auf 0 setzen.',               20),
('default_theme',      'gothic',                                        'string', 'Standard-Theme',                'Theme für neue Nutzer ohne gespeichertes Theme.',                      30),
('login_title',        'Willkommen zurück',                             'string', 'Login-Kartentitel',             'Überschrift der Anmeldekarte.',                                        34),
('login_subtitle',     'Das Dorf schläft … doch die Wölfe nicht.',     'string', 'Login-Untertitel',              'Slogan unter dem Logo auf der Anmeldeseite.',                          35),
('register_subtitle',  'Tritt dem Dorf bei',                            'string', 'Registrierungs-Untertitel',     'Text unter dem Logo auf der Registrierungsseite.',                     36),
('min_players',        '4',                                             'int',    'Mindest-Spielerzahl',           'Mindestanzahl Spieler für den Spielstart.',                            40),
('max_players',        '30',                                            'int',    'Maximal-Spielerzahl',           'Maximale Anzahl Spieler pro Spiel.',                                   50),
('default_role_icon',  'assets/icons/roles/_default.png',               'string', 'Standard-Rollen-Icon',          'Fallback-Icon-Pfad wenn eine Rolle kein eigenes Icon hat.',            70),
('session_lifetime',   '604800',                                        'int',    'Session-Dauer (Sek.)',          'Anmeldedauer in Sekunden (604800 = 7 Tage, 86400 = 1 Tag).',          80),
('deaths_empty_title', 'Noch niemand gestorben',                        'string', 'Todesliste: Leertitel',         'Überschrift wenn noch niemand gestorben ist.',                         90),
('deaths_empty_sub',   'Das Dorf ist in Frieden … noch.',              'string', 'Todesliste: Leer-Subtext',      'Untertitel wenn noch niemand gestorben ist.',                          91),
('deaths_peace_text',  'Mögen sie in Frieden ruhen',                    'string', 'Todesliste: Friedenstext',      'Text unter dem Friedhof-Bereich wenn Tote vorhanden sind.',           92),
('login_logo',         '',                                              'string', 'Login-Logo',                    'Pfad zum PNG-Bild auf der Anmeldeseite (leer = Wolf-Emoji 🐺).',       5),
('mini_logo',          'assets/icons/logo/mini_logo.png',               'string', 'Browser-Icon (Favicon)',        'PNG-Datei für die Browser-Adressleiste / Tab-Icon.',                    6),
('game_timezone',      'Europe/Berlin',                                 'string', 'Zeitzone',                      'PHP-Zeitzone des Servers (z.B. Europe/Berlin, UTC).',                  20),
('push_cooldown',      '5',                                             'int',    'Push-Cooldown (Min.)',          'Mindestwartezeit zwischen zwei Auto-Push-Benachrichtigungen.',         26),
('push_last_sent',     '0',                                             'int',    'Push: letzter Versand (intern)','Unix-Timestamp des letzten gesendeten Pushes (intern).',              999);
