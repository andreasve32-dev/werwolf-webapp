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
-- Sprachnachrichten-System entfernt (v0.38) — Spalten bei bestehenden Installs mit abräumen.
ALTER TABLE messages DROP COLUMN IF EXISTS voice_path;
ALTER TABLE messages DROP COLUMN IF EXISTS reply_voice_path;
-- Feedback-System (v0.34): question = Spielerfrage, bug/wish/feedback = Feedback-Einträge.
-- status gilt nur für Feedback-Typen (open/accepted/in_progress/done/rejected).
ALTER TABLE messages ADD COLUMN IF NOT EXISTS type   VARCHAR(16) NOT NULL DEFAULT 'question' AFTER player_id;
ALTER TABLE messages ADD COLUMN IF NOT EXISTS status VARCHAR(16) NOT NULL DEFAULT 'open' AFTER type;
-- v0.36: Gelesen-Marker — wird gesetzt, sobald der Admin die Nachrichten-Verwaltung öffnet
ALTER TABLE messages ADD COLUMN IF NOT EXISTS read_by_admin TINYINT(1) NOT NULL DEFAULT 0 AFTER read_by_player;

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
  ADD COLUMN IF NOT EXISTS linked_death    TINYINT(1) NOT NULL DEFAULT 0  AFTER sort_order,
  ADD COLUMN IF NOT EXISTS rollensicht     TINYINT(1) NOT NULL DEFAULT 0  AFTER linked_death,
  ADD COLUMN IF NOT EXISTS kill_hinweis    TINYINT(1) NOT NULL DEFAULT 0  AFTER rollensicht;

-- Rollen-Erkenntnisse: "Spieler A kennt die Rolle von Spieler B" (z.B. Hellseherin)
CREATE TABLE IF NOT EXISTS role_insights (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  game_id          INT NOT NULL,
  viewer_player_id INT NOT NULL,
  target_player_id INT NOT NULL,
  source           VARCHAR(30) NOT NULL DEFAULT 'rollensicht',
  created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (game_id)          REFERENCES games(id)   ON DELETE CASCADE,
  FOREIGN KEY (viewer_player_id) REFERENCES players(id) ON DELETE CASCADE,
  FOREIGN KEY (target_player_id) REFERENCES players(id) ON DELETE CASCADE,
  UNIQUE KEY uq_insight (game_id, viewer_player_id, target_player_id)
) ENGINE=InnoDB;

ALTER TABLE games
  ADD COLUMN IF NOT EXISTS winner ENUM('killer','citizen','dodo') NULL DEFAULT NULL AFTER status;

ALTER TABLE game_players
  ADD COLUMN IF NOT EXISTS cooldown_started_at TIMESTAMP NULL DEFAULT NULL AFTER is_alive;

-- last_ability_round entfernen falls noch vorhanden (Altlast: rundenbasierte
-- Cooldown-Idee, nie genutzt — Timer läuft über cooldown_started_at in Minuten)
SET @_has_lar = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='game_players' AND COLUMN_NAME='last_ability_round');
SET @_sql_lar = IF(@_has_lar=1,'ALTER TABLE game_players DROP COLUMN last_ability_round','SELECT 1');
PREPARE _stmt_lar FROM @_sql_lar; EXECUTE _stmt_lar; DEALLOCATE PREPARE _stmt_lar;

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

-- Stand: 1:1-Abbild der Live-Rollen (Snapshot 12.07.2026, siehe CHANGELOG v0.39).
-- Greift nur bei einer wirklich leeren roles-Tabelle (INSERT IGNORE) — bei
-- bestehenden Installs bleiben individuell angepasste Rollen unangetastet,
-- nur strukturelle Flag-Migrationen laufen unten per UPDATE nach.
INSERT IGNORE INTO roles
  (id, name, cooldown, description, rules, active, fill, amount, icon_path, sichtbar, killer_sichtbar, befragen, auto_eintrag, is_killer, linked_death, rollensicht, kill_hinweis, sort_order)
VALUES
(1,  'Bürger',    0,
  'Einfacher Bürger ohne besondere Fähigkeiten.',
  'Finde die Mörder durch Beobachten und Abstimmen. Berufe Versammlungen ein.',
  1, 1, 0, 'assets/icons/roles/buerger.png', 0, 0, 0, 0, 0, 0, 0, 0, 10),

(2,  'Mörder',    30,
  'Kann andere Spieler mit der Mörderkarte töten. Abklingzeit: {cooldown} Minuten.',
  'Zeige einem anderen Spieler deine Mörderkarte — dieser ist sofort tot und trägt sich in die Todesliste ein. Auf Morde hast du {cooldown} Minuten Abklingzeit. Arbeite mit dem anderen Mörder zusammen.',
  1, 0, 2, 'assets/icons/roles/moerder.png', 1, 0, 0, 0, 1, 0, 0, 0, 20),

(3,  'Nekromant', 0,
  'Kann tote Spieler befragen, indem er ihnen seine Karte zeigt.',
  'Zeige einem toten Spieler deine Karte. Dieser trägt Todeszeitpunkt und -ort in die Todesliste ein und gibt so mehr Informationen preis.',
  1, 0, 1, 'assets/icons/roles/nekromant.png', 0, 0, 1, 0, 0, 0, 0, 0, 30),

(4,  'Hellseherin', 30,
  'Kann alle {cooldown} Minuten einen Spieler zwingen, seine Rolle aufzudecken. Untersuchte Rollen bleiben dauerhaft sichtbar.',
  'Zeige einem Spieler deine Karte — er muss dir seine Rolle zeigen. Trage die Untersuchung danach in der App ein: die Rolle bleibt für dich dauerhaft in der Spielerliste sichtbar. Abklingzeit: {cooldown} Minuten.',
  1, 0, 1, 'assets/icons/roles/hellseherin.png', 0, 0, 0, 0, 0, 1, 0, 0, 40),

(5,  'Detektiv',  0,
  'Ermittelt passiv: Nach jedem Mord eines Killers erfährt er automatisch einen Spieler, der sicher kein Killer ist.',
  'Deine Fähigkeit ist passiv — du musst nichts tun. Immer wenn ein Spieler ermordet wird, zeigt dir die App automatisch einen zufälligen Spieler mit "✅ Kein Killer" in der Spielerliste an. Du bekommst dann eine Benachrichtigung.',
  1, 0, 1, 'assets/icons/roles/detektiv.png', 0, 0, 0, 0, 0, 0, 0, 1, 50),

(6,  'Das Paar',  0,
  '2 Spieler bilden ein Paar und kennen sich von Beginn an.',
  'Ihr kennt euren Partner. Stirbt dein Partner, wirst du benachrichtigt (Push + Hinweis im Spielfenster) — du kannst dich danach jederzeit selbst als tot melden, wenn du bereit dazu bist.',
  1, 0, 2, 'assets/icons/roles/das-paar.png', 1, 0, 0, 0, 0, 1, 0, 0, 60),

(7,  'Dodo',      0,
  'Gewinnt das Spiel, indem er von der Gruppe erhängt wird.',
  'Du gewinnst, wenn die Versammlung dich erhängt. Wirst du von einem Mörder getötet, hat deine Rolle keine Auswirkung.',
  1, 0, 1, 'assets/icons/roles/dodo.png', 0, 0, 0, 0, 0, 0, 0, 0, 70),

(8,  'Celebrity', 0,
  'Sein Tod fällt sofort auf — nachdem du getötet wurdest erscheinen deine Daten auf der Todesliste.',
  'Du bist allgemein bekannt — nachdem du getötet wurdest erscheinen deine Daten auf der Todesliste.',
  1, 0, 1, 'assets/icons/roles/celebrity.png', 0, 0, 0, 1, 0, 0, 0, 0, 80),

(9,  'Gunslinger', 0,
  'Kann beliebig oft schießen. Trifft er einen Killer, überlebt er. Trifft er einen Unschuldigen, stirbt er selbst.',
  'Du hast eine Waffe ohne Schussbegrenzung. Schießt du auf einen Killer (z. B. Mörder), lebst du weiter und kannst erneut schießen. Triffst du einen Unschuldigen, stirbst du selbst. Du stehst auf Seite der Bürger.',
  1, 0, 1, 'assets/icons/roles/gunslinger.png', 0, 0, 0, 0, 0, 0, 0, 0, 90),

-- Sheriff: derzeit deaktiviert (active=0) — Stand aus der Live-DB übernommen
(10, 'Sheriff',   0,
  'Kann unbegrenzt Spieler erschießen — tötet er jedoch einen Unschuldigen, stirbt er selbst.',
  'Du kannst so viele Spieler erschießen wie du willst. Tötest du jedoch nicht den Dodo oder einen Mörder, stirbst du selbst.',
  0, 0, 1, 'assets/icons/roles/sheriff.png', 0, 0, 0, 0, 0, 0, 0, 0, 100),

(11, 'Leichenfresser', 30,
  'Killer, dessen Opfer spurlos verschwinden — sie können nicht vom Nekromanten befragt werden.',
  'Zeige einem anderen Spieler die Mordwaffe — dieser ist sofort tot. Deine Opfer hinterlassen keine Orts- und Zeitspuren und können NICHT vom Nekromanten befragt werden. Abklingzeit: {cooldown} Minuten.',
  0, 0, 1, NULL, 0, 0, 0, 0, 1, 0, 0, 0, 110),

(12, 'Auftragskiller', 5,
  'Killer mit Auftrag: Er bekommt ein zufälliges Ziel — erst nach dessen Tod das nächste.',
  'Die App zeigt dir ein zufälliges Ziel. Nur dieses Ziel darfst du töten (Mordwaffe zeigen). Nach dem Kill bekommst du nach {cooldown} Minuten Abklingzeit ein neues Ziel zugewiesen.',
  0, 0, 1, NULL, 0, 0, 0, 0, 1, 0, 0, 0, 120),

(13, 'Schläfer', 0,
  'Beginnt als Killer — wechselt aber nach einiger Zeit heimlich die Seite und spielt dann für das Dorf.',
  'Du startest im Killer-Team und kennst die anderen Killer. Nach einer zufälligen Zeit wechselst du automatisch die Seite: Ab dann gewinnst du mit den Bürgern. Die anderen Killer wissen von Anfang an nur: "Einer von euch ist ein Verräter."',
  0, 0, 1, NULL, 1, 0, 0, 0, 1, 0, 0, 0, 130),

(14, 'Söldner', 0,
  'Startrolle im Zombie-Modus: Überlebe die Zombie-Plage.',
  'Alle Spieler starten als Söldner. Irgendwann in den ersten Stunden verwandelt sich einer von euch in den ersten Zombie. Findet und eliminiert die Zombies, bevor sie euch alle bekehren.',
  0, 1, 0, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 140),

(15, 'Zombie', 0,
  'Bekehrt Söldner statt sie zu töten — die Plage wächst.',
  'Zeige einem Söldner deine Karte — er ist ab sofort ebenfalls Zombie und spielt für euch weiter. Ihr gewinnt, wenn alle Spieler infiziert sind. Zombies erkennen sich gegenseitig.',
  0, 0, 1, NULL, 1, 0, 0, 0, 1, 0, 0, 0, 150);

-- Bekannte Rollen-Flags sicherstellen (falls INSERT IGNORE übersprungen)
UPDATE roles SET befragen=1  WHERE name='Nekromant'  AND befragen=0;
UPDATE roles SET auto_eintrag=1 WHERE name='Celebrity' AND auto_eintrag=0;
UPDATE roles SET is_killer=1 WHERE name='Mörder'     AND is_killer=0;
UPDATE roles SET sichtbar=1  WHERE name IN ('Mörder','Das Paar') AND sichtbar=0;
UPDATE roles SET linked_death=1 WHERE name='Das Paar' AND linked_death=0;
UPDATE roles SET rollensicht=1 WHERE name='Hellseherin' AND rollensicht=0;
UPDATE roles SET kill_hinweis=1 WHERE name='Detektiv' AND kill_hinweis=0;

INSERT IGNORE INTO games (id, status) VALUES (1, 'lobby');

INSERT IGNORE INTO settings (`key`, value, type, label, description, sort_order) VALUES
('app_name',           'Werwolf',                                       'string', 'Spielname',                     'Anzeigename der App — überall sichtbar.',                              10),
('app_version',        '0.28',                                          'string', 'Versionsnummer',                'Anzeigeversion z. B. in Fußzeile oder About-Seite.',                   15),
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
('clear_messages_on_start', '0',                                         'bool',   'Nachrichten bei Spielstart löschen', 'Beim Start eines neuen Spiels: alle Text-Fragen ohne FAQ-Veröffentlichung werden gelöscht.', 22),
('push_last_sent',     '0',                                             'int',    'Push: letzter Versand (intern)','Unix-Timestamp des letzten gesendeten Pushes (intern).',              999),
('feedback_api_token', '',                                              'string', 'Feedback-API-Token',            'Zugriffs-Token für die externe Feedback-API (leer = API deaktiviert). Verwaltung über Admin → Spielerfragen & Feedback.', 998);

-- Sprachnachrichten-System entfernt (v0.38) — verwaiste Settings-Zeilen bei bestehenden Installs entfernen.
DELETE FROM settings WHERE `key` IN ('voice_messages_enabled', 'voice_transcription_enabled', 'openai_api_key');
