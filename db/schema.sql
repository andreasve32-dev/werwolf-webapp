-- ============================================================
--  WERWOLF — Vollständiges Datenbankschema (sauberer Neuaufbau)
-- ============================================================
--  Diese Datei wird vom grafischen Setup-Skript
--  (public/setup.php) ausgeführt. Sie LÖSCHT zuerst alle
--  bestehenden Tabellen dieses Projekts und baut sie dann neu
--  auf — dadurch werden alte/inkonsistente Zwischenstände
--  (z.B. von früheren Migrationen) vollständig bereinigt.
--
--  Reihenfolge wichtig: Tabellen mit Foreign Keys zuerst droppen,
--  dann die Tabellen, auf die sie verweisen.
-- ============================================================

-- ── 1. Alte Tabellen entfernen (Reihenfolge wegen FKs) ────────
DROP TABLE IF EXISTS assembly_requests;
DROP TABLE IF EXISTS push_subscriptions;
DROP TABLE IF EXISTS messages;
DROP TABLE IF EXISTS votes;
DROP TABLE IF EXISTS night_actions;
DROP TABLE IF EXISTS deaths;
DROP TABLE IF EXISTS game_players;
DROP TABLE IF EXISTS games;
DROP TABLE IF EXISTS roles;
DROP TABLE IF EXISTS players;
DROP TABLE IF EXISTS settings;

-- ── 2. Spieler-Konten ───────────────────────────────────────────
CREATE TABLE players (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(50)  NOT NULL UNIQUE,
  display_name  VARCHAR(50)  NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  is_admin      TINYINT(1)   NOT NULL DEFAULT 0,
  created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── 3. Rollen — komplett datenbankgesteuert ────────────────────
-- Kein Rollen-Eintrag im Code. Verwaltung über /public/roles.php
CREATE TABLE roles (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(50)  NOT NULL UNIQUE,       -- z.B. "Mörder"
  cooldown    INT          NOT NULL DEFAULT 0,    -- Minuten bis Fähigkeit wieder nutzbar (0 = unbegrenzt)
  description TEXT         NULL,                  -- Rollenbeschreibung für den Spieler
  rules       TEXT         NULL,                  -- Regeln für diese Rolle
  active      TINYINT(1)   NOT NULL DEFAULT 1,    -- 1 = im Spiel verfügbar
  fill        TINYINT(1)   NOT NULL DEFAULT 0,    -- 1 = Auffüll-Rolle (z.B. Bürger), füllt übrige Plätze
  amount      INT          NOT NULL DEFAULT 1,    -- wie oft diese Rolle pro Spiel vorkommen soll (bei fill=1 ignoriert)
  icon_path   VARCHAR(255) NULL,                  -- lokaler Pfad zum Icon (PNG/JPG), z.B. assets/icons/roles/moerder.png
  sichtbar    TINYINT(1)   NOT NULL DEFAULT 0,    -- 1 = Spieler mit dieser Rolle erkennen sich gegenseitig
  befragen    TINYINT(1)   NOT NULL DEFAULT 0,    -- 1 = Rolle darf Tote befragen (sieht ort/zeit in Todesliste)
  auto_eintrag TINYINT(1)  NOT NULL DEFAULT 0,    -- 1 = Ort+Zeit werden beim Sterben sofort automatisch eingetragen (Star)
  is_killer   TINYINT(1)   NOT NULL DEFAULT 0,    -- 1 = Killerteam (gewinnen wenn >= Überlebende Nicht-Killer)
  sort_order  INT          NOT NULL DEFAULT 0,
  created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── 4. Spiele ───────────────────────────────────────────────────
CREATE TABLE games (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  status     ENUM('lobby','running','finished') NOT NULL DEFAULT 'lobby',
  winner     ENUM('killer','citizen','dodo')    NULL DEFAULT NULL,
  phase      ENUM('day','night')                NOT NULL DEFAULT 'day',
  round      INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── 5. Teilnehmer eines Spiels ──────────────────────────────────
CREATE TABLE game_players (
  id                 INT AUTO_INCREMENT PRIMARY KEY,
  game_id            INT NOT NULL,
  player_id          INT NOT NULL,
  role_id            INT NULL,                  -- FK auf roles.id (NULL = noch keine Rolle zugewiesen)
  is_alive           TINYINT(1) NOT NULL DEFAULT 1,
  last_ability_round  INT NULL,                  -- letzte Runde, in der die Fähigkeit genutzt wurde (Cooldown)
  cooldown_started_at TIMESTAMP NULL DEFAULT NULL, -- Zeitpunkt, zu dem der Spieler seinen Cooldown manuell gestartet hat
  joined_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (game_id)   REFERENCES games(id)   ON DELETE CASCADE,
  FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
  FOREIGN KEY (role_id)   REFERENCES roles(id)   ON DELETE SET NULL,
  UNIQUE KEY uq_gp (game_id, player_id)
) ENGINE=InnoDB;

-- ── 6. Todesfälle ────────────────────────────────────────────────
CREATE TABLE deaths (
  id        INT AUTO_INCREMENT PRIMARY KEY,
  game_id   INT NOT NULL,
  player_id INT NOT NULL,
  role_id   INT NULL,                        -- FK auf roles.id, Rolle zum Zeitpunkt des Todes
  round            INT NOT NULL,
  phase            ENUM('day','night') NOT NULL,
  is_gehenkt       TINYINT(1) NOT NULL DEFAULT 0,  -- 1 = vom Dorf abgestimmt und gehenkt
  ort              VARCHAR(255) NULL DEFAULT NULL,
  zeit             VARCHAR(50)  NULL DEFAULT NULL,
  rolle_aufgedeckt TINYINT(1)  NOT NULL DEFAULT 0,  -- 1 = Rolle durch befragen enthüllt
  died_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (game_id)   REFERENCES games(id)   ON DELETE CASCADE,
  FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
  FOREIGN KEY (role_id)   REFERENCES roles(id)   ON DELETE SET NULL
) ENGINE=InnoDB;

-- ── 7. Nachtaktionen ─────────────────────────────────────────────
CREATE TABLE night_actions (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  game_id          INT NOT NULL,
  round            INT NOT NULL,
  actor_player_id  INT NOT NULL,
  target_player_id INT NULL,
  action_type      ENUM('wolf_kill','seer_check','witch_kill','witch_save','hunter_shoot') NOT NULL,
  created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── 8. Tagesabstimmungen ─────────────────────────────────────────
CREATE TABLE votes (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  game_id    INT NOT NULL,
  round      INT NOT NULL,
  voter_id   INT NOT NULL,
  target_id  INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE,
  UNIQUE KEY uq_vote (game_id, round, voter_id)
) ENGINE=InnoDB;

-- ── 9. Spieler-Nachrichten an den Spielleiter ────────────────
CREATE TABLE messages (
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

CREATE TABLE push_subscriptions (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  player_id  INT NOT NULL,
  endpoint   TEXT NOT NULL,
  p256dh     VARCHAR(512) DEFAULT NULL,
  auth       VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_player_endpoint (player_id, endpoint(191)),
  FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── 9b. Versammlungsanfragen ─────────────────────────────────────
CREATE TABLE assembly_requests (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  game_id      INT NOT NULL,
  player_id    INT NOT NULL,
  scheduled_at INT NOT NULL,
  notified     TINYINT(1) NOT NULL DEFAULT 0,
  called_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  ended_at     TIMESTAMP NULL DEFAULT NULL, -- NULL = aktiv, gesetzt = vom Admin beendet
  FOREIGN KEY (game_id)   REFERENCES games(id)   ON DELETE CASCADE,
  FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── 10. Rollen (Seed-Daten basierend auf dem echten Regelwerk) ──
-- fill=1: Bürger ist die Auffüll-Rolle — Spieler, die keine Sonderrolle
--         bekommen, werden automatisch Bürger. amount wird bei fill=1 ignoriert.
-- fill=0: Sonderrolle — amount bestimmt, wie viele es pro Spiel gibt.
-- cooldown: Abklingzeit in Minuten (0 = keine)
-- sichtbar=1: Spieler mit dieser Rolle erkennen sich beim Spielstart gegenseitig
--
INSERT INTO roles (id, name, cooldown, description, rules, active, fill, amount, icon_path, sichtbar, befragen, auto_eintrag, is_killer, sort_order) VALUES
(1,  'Bürger',      0,
  'Einfacher Bürger ohne besondere Fähigkeiten.',
  'Finde die Mörder durch Beobachten und Abstimmen. Berufe Versammlungen ein.',
  1, 1, 0, 'assets/icons/roles/buerger.png', 0, 0, 0, 0, 10),

(2,  'Mörder',      30,
  'Kann andere Spieler mit der Mordwaffe töten. Abklingzeit: 30 Minuten.',
  'Zeige einem anderen Spieler die Mordwaffe — dieser ist sofort tot und trägt sich in die Todesliste ein. Die Mordwaffe hat 30 Minuten Abklingzeit. Arbeite mit dem anderen Mörder zusammen.',
  1, 0, 2, 'assets/icons/roles/moerder.png', 1, 0, 0, 1, 20),

(3,  'Nekromant',   0,
  'Kann tote Spieler befragen, indem er ihnen seine Karte zeigt.',
  'Zeige einem toten Spieler deine Karte. Dieser trägt Todeszeitpunkt und -ort in die Todesliste ein und gibt so mehr Informationen preis.',
  1, 0, 1, 'assets/icons/roles/nekromant.png', 0, 1, 0, 0, 30),

(4,  'Hellseherin', 30,
  'Kann alle 30 Minuten einen Spieler zwingen, seine Rolle aufzudecken.',
  'Zeige einem Spieler deine Karte — er muss dir seine Rolle zeigen. Abklingzeit: 30 Minuten.',
  1, 0, 1, 'assets/icons/roles/hellseherin.png', 0, 0, 0, 0, 40),

(5,  'Detektiv',    0,
  'Kann Spieler durchsuchen. Trägt der Mörder die Waffe, ist er entlarvt.',
  'Durchsuche einen Spieler. Trägt er die Mordwaffe, muss er sie abgeben. Stirbt der Detektiv danach, kann die Waffe vom zweiten Mörder zurückgeholt werden. Alternativ wird die Waffe als Beweis öffentlich abgelegt.',
  1, 0, 1, 'assets/icons/roles/detektiv.png', 0, 0, 0, 0, 50),

(6,  'Das Paar',    0,
  '2 Spieler bilden ein Paar und kennen sich von Beginn an.',
  'Öffne beim Spielstart die Augen wenn das Paar aufgerufen wird — ihr kennt euren Partner. Stirbt dein Partner, nimmst du dir das Leben sobald du seinen Tod bemerkst.',
  1, 0, 2, 'assets/icons/roles/das-paar.png', 1, 0, 0, 0, 60),

(7,  'Dodo',        0,
  'Gewinnt das Spiel, indem er von der Gruppe erhängt wird.',
  'Du gewinnst, wenn die Versammlung dich erhängt. Wirst du von einem Mörder getötet, hat deine Rolle keine Auswirkung. Du darfst KEINE Mordwaffe bei dir tragen.',
  1, 0, 1, 'assets/icons/roles/dodo.png', 0, 0, 0, 0, 70),

-- Optionale Zusatzrollen (standardmäßig deaktiviert)
(8,  'Celebrity',   0,
  'Sein Tod fällt sofort auf — er trägt direkt Todeszeitpunkt und Ort ein.',
  'Du bist allgemein bekannt. Stirbst du, trägst du sofort Zeitpunkt und Ort in die Todesliste ein.',
  1, 0, 1, 'assets/icons/roles/celebrity.png', 0, 0, 1, 0, 80),

(9,  'Gunslinger',  0,
  'Kann beliebig oft schießen. Trifft er einen Killer, überlebt er. Trifft er einen Unschuldigen, stirbt er selbst.',
  'Du hast eine Waffe ohne Schussbegrenzung. Schießt du auf einen Killer (z. B. Mörder), lebst du weiter und kannst erneut schießen. Triffst du einen Unschuldigen, stirbst du selbst. Du stehst auf Seite der Bürger.',
  1, 0, 1, 'assets/icons/roles/gunslinger.png', 0, 0, 0, 0, 90),

(10, 'Sheriff',     0,
  'Kann unbegrenzt Spieler erschießen — tötet er jedoch einen Unschuldigen, stirbt er selbst.',
  'Du kannst so viele Spieler erschießen wie du willst. Tötest du jedoch nicht den Dodo oder einen Mörder, stirbst du selbst.',
  1, 0, 1, 'assets/icons/roles/sheriff.png', 0, 0, 0, 0, 100);

-- ── 11. App-Einstellungen (DB-konfigurierbar) ─────────────────────
CREATE TABLE settings (
  `key`       VARCHAR(80)   NOT NULL,
  value       TEXT          NOT NULL,
  type        ENUM('string','int','bool') NOT NULL DEFAULT 'string',
  label       VARCHAR(100)  NOT NULL,
  description VARCHAR(255)  NOT NULL DEFAULT '',
  sort_order  INT           NOT NULL DEFAULT 0,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB;

INSERT INTO settings (`key`, value, type, label, description, sort_order) VALUES
('app_name',           'Werwolf',                          'string', 'Spielname',              'Anzeigename der App — überall sichtbar.',                         10),
('app_version',        '0.0.7',                           'string', 'Versionsnummer',          'Anzeigeversion z. B. in Fußzeile oder About-Seite.',             15),
('beta_mode',          '1',                               'bool',   'Beta-Modus',              'Zeigt einen Beta-Hinweis im Spielfenster an.',                    16),
('app_debug',          '1',                               'bool',   'Debug-Modus',             'PHP-Fehler anzeigen. Im Produktivbetrieb auf 0 setzen.',          20),
('default_theme',      'gothic',                          'string', 'Standard-Theme',          'Theme für neue Nutzer ohne gespeichertes Theme.',                30),
('login_subtitle',     'Das Dorf schläft … doch die Wölfe nicht.', 'string', 'Login-Untertitel', 'Slogan unter dem Spielnamen auf der Anmeldeseite.',           35),
('min_players',        '4',                               'int',    'Mindest-Spielerzahl',     'Mindestanzahl Spieler für den Spielstart.',                       40),
('max_players',        '30',                              'int',    'Maximal-Spielerzahl',     'Maximale Anzahl Spieler pro Spiel.',                              50),
('background_music',   'background.mp3',                  'string', 'Hintergrundmusik',        'Dateiname in assets/audio/. Leer = kein Player.',                60),
('default_role_icon',  'assets/icons/roles/_default.png', 'string', 'Standard-Rollen-Icon',   'Fallback-Icon-Pfad wenn eine Rolle kein eigenes Icon hat.',       70),
('session_lifetime',   '604800',                          'int',    'Session-Dauer (Sek.)',    'Anmeldedauer in Sekunden (604800 = 7 Tage, 86400 = 1 Tag).',     80),
('deaths_empty_title', 'Noch niemand gestorben',          'string', 'Todesliste: Leertitel',   'Überschrift wenn noch niemand gestorben ist.',                    90),
('deaths_empty_sub',   'Das Dorf ist in Frieden … noch.','string', 'Todesliste: Leer-Subtext','Untertitel wenn noch niemand gestorben ist.',                     91),
('deaths_peace_text',  'Mögen sie in Frieden ruhen',      'string', 'Todesliste: Friedenstext','Text unter dem Friedhof-Bereich wenn Tote vorhanden sind.',      92),
('login_logo',         '',                                'string', 'Login-Logo',              'Pfad zum Bild auf der Anmeldeseite (leer = Wolf-Emoji 🐺).',       5),
('game_timezone',      'Europe/Berlin',                   'string', 'Zeitzone',                'PHP-Zeitzone des Servers (z.B. Europe/Berlin, UTC).',             20),
('day_slogans',        '30 Grad im Schatten im Dorf',    'string', 'Tages-Slogans',           'Zufallssprüche im Tages-Banner (eine Zeile = ein Slogan).',       25),
('push_cooldown',      '30',                              'int',    'Push-Cooldown (Min.)',    'Mindestwartezeit zwischen zwei Auto-Push-Benachrichtigungen.',    26),
('push_last_sent',     '0',                               'int',    'Push: letzter Versand (intern)', 'Unix-Timestamp des letzten gesendeten Pushes (intern).', 999);

-- ── 12. Erstes Spiel anlegen ──────────────────────────────────────
INSERT INTO games (id, status) VALUES (1, 'lobby');
