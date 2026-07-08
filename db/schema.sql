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
DROP TABLE IF EXISTS role_insights;
DROP TABLE IF EXISTS role_preset_items;
DROP TABLE IF EXISTS role_presets;
DROP TABLE IF EXISTS slogans;
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
  settings      TEXT         NULL,                 -- JSON: persönliche UI-Einstellungen (geräteübergreifend)
  created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── 3. Rollen — komplett datenbankgesteuert ────────────────────
-- Kein Rollen-Eintrag im Code. Verwaltung über /admin/roles.php
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
  killer_sichtbar TINYINT(1) NOT NULL DEFAULT 0,  -- 1 = Rolle und Killer-Team sehen sich gegenseitig (z.B. Dodo)
  befragen    TINYINT(1)   NOT NULL DEFAULT 0,    -- 1 = Rolle darf Tote befragen (sieht ort/zeit in Todesliste)
  auto_eintrag TINYINT(1)  NOT NULL DEFAULT 0,    -- 1 = Ort+Zeit werden beim Sterben sofort automatisch eingetragen (Star)
  is_killer   TINYINT(1)   NOT NULL DEFAULT 0,    -- 1 = Killerteam (gewinnen wenn >= Überlebende Nicht-Killer)
  linked_death TINYINT(1)  NOT NULL DEFAULT 0,    -- 1 = stirbt ein Spieler dieser Rolle, sterben alle anderen lebenden Spieler derselben Rolle automatisch mit (Das Paar)
  rollensicht TINYINT(1)   NOT NULL DEFAULT 0,    -- 1 = darf Spieler untersuchen und sieht deren Rolle dauerhaft (Hellseherin) — Erkenntnisse in role_insights
  kill_hinweis TINYINT(1)  NOT NULL DEFAULT 0,    -- 1 = erfährt automatisch je (Anzahl Killer) Morde einen zufälligen Nicht-Killer (Detektiv) — Erkenntnisse in role_insights
  sort_order  INT          NOT NULL DEFAULT 0,
  created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── 3b. Rollen-Presets ───────────────────────────────────────────
-- Gespeicherte Rollensets (z.B. "7 Spieler"): Snapshot von active/amount/fill
-- pro Rolle. Beim Laden wird der Snapshot auf die roles-Tabelle angewendet;
-- Rollen, die im Preset fehlen (nach dem Speichern neu angelegt), werden
-- deaktiviert, damit das Preset die komplette Konfiguration beschreibt.
CREATE TABLE role_presets (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(50) NOT NULL UNIQUE,        -- z.B. "7 Spieler"
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE role_preset_items (
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
  faq_question   TEXT NULL,        -- anonymisierte/bearbeitete Frage für die FAQ (NULL = message wird 1:1 verwendet)
  voice_path     VARCHAR(255) NULL, -- Sprachnachricht-Datei unter uploads/voice/ (NULL = Textnachricht). Auslieferung nur über api/messages.php (Auth), nie direkt
  reply          TEXT NULL,
  reply_voice_path VARCHAR(255) NULL, -- Sprach-ANTWORT des Admins (uploads/voice/), Auslieferung nur über api/messages.php?action=voice_file&which=reply
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

-- ── 9b. Dorf-Sprüche ────────────────────────────────────────────
CREATE TABLE slogans (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  text       VARCHAR(255) NOT NULL UNIQUE,
  phase      ENUM('day','night') NOT NULL DEFAULT 'day',
  active     TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO slogans (text, phase) VALUES
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

-- ── 9c. Versammlungsanfragen ─────────────────────────────────────
CREATE TABLE assembly_requests (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  game_id      INT NOT NULL,
  player_id    INT NOT NULL,
  supporter_id INT NULL,                     -- zweiter Einberufer (NULL = wartet noch auf Unterstützung)
  scheduled_at INT NULL,                     -- Termin: wird erst beim zweiten Einberufer gesetzt
  notified     TINYINT(1) NOT NULL DEFAULT 0,
  called_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  ended_at     TIMESTAMP NULL DEFAULT NULL, -- NULL = aktiv, gesetzt = vom Admin beendet
  FOREIGN KEY (game_id)      REFERENCES games(id)   ON DELETE CASCADE,
  FOREIGN KEY (player_id)    REFERENCES players(id) ON DELETE CASCADE,
  FOREIGN KEY (supporter_id) REFERENCES players(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ── 9d. Rollen-Erkenntnisse ──────────────────────────────────────
-- Erworbenes Wissen "Spieler A kennt die Rolle von Spieler B" — z.B. durch
-- die Hellseherin (rollensicht-Flag). Eine Zeile pro Erkenntnis; source
-- nennt die Mechanik (für die Statistik auswertbar). Flag-basierte
-- Sichtbarkeiten (sichtbar/killer_sichtbar) stehen NICHT hier — die werden
-- weiterhin live aus den Rollen-Flags berechnet (eine Quelle der Wahrheit).
CREATE TABLE role_insights (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  game_id          INT NOT NULL,
  viewer_player_id INT NOT NULL,                 -- wer das Wissen hat
  target_player_id INT NOT NULL,                 -- über wen
  source           VARCHAR(30) NOT NULL DEFAULT 'rollensicht',
  created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (game_id)          REFERENCES games(id)   ON DELETE CASCADE,
  FOREIGN KEY (viewer_player_id) REFERENCES players(id) ON DELETE CASCADE,
  FOREIGN KEY (target_player_id) REFERENCES players(id) ON DELETE CASCADE,
  UNIQUE KEY uq_insight (game_id, viewer_player_id, target_player_id)
) ENGINE=InnoDB;

-- ── 10. Rollen (Seed-Daten basierend auf dem echten Regelwerk) ──
-- fill=1: Bürger ist die Auffüll-Rolle — Spieler, die keine Sonderrolle
--         bekommen, werden automatisch Bürger. amount wird bei fill=1 ignoriert.
-- fill=0: Sonderrolle — amount bestimmt, wie viele es pro Spiel gibt.
-- cooldown: Abklingzeit in Minuten (0 = keine)
-- sichtbar=1: Spieler mit dieser Rolle erkennen sich beim Spielstart gegenseitig
--
INSERT INTO roles (id, name, cooldown, description, rules, active, fill, amount, icon_path, sichtbar, befragen, auto_eintrag, is_killer, sort_order, linked_death) VALUES
(1,  'Bürger',      0,
  'Einfacher Bürger ohne besondere Fähigkeiten.',
  'Finde die Mörder durch Beobachten und Abstimmen. Berufe Versammlungen ein.',
  1, 1, 0, 'assets/icons/roles/buerger.png', 0, 0, 0, 0, 10, 0),

(2,  'Mörder',      30,
  'Kann andere Spieler mit der Mordwaffe töten. Abklingzeit: {cooldown} Minuten.',
  'Zeige einem anderen Spieler die Mordwaffe — dieser ist sofort tot und trägt sich in die Todesliste ein. Die Mordwaffe hat {cooldown} Minuten Abklingzeit. Arbeite mit dem anderen Mörder zusammen.',
  1, 0, 2, 'assets/icons/roles/moerder.png', 1, 0, 0, 1, 20, 0),

(3,  'Nekromant',   0,
  'Kann tote Spieler befragen, indem er ihnen seine Karte zeigt.',
  'Zeige einem toten Spieler deine Karte. Dieser trägt Todeszeitpunkt und -ort in die Todesliste ein und gibt so mehr Informationen preis.',
  1, 0, 1, 'assets/icons/roles/nekromant.png', 0, 1, 0, 0, 30, 0),

(4,  'Hellseherin', 30,
  'Kann alle {cooldown} Minuten einen Spieler zwingen, seine Rolle aufzudecken. Untersuchte Rollen bleiben dauerhaft sichtbar.',
  'Zeige einem Spieler deine Karte — er muss dir seine Rolle zeigen. Trage die Untersuchung danach in der App ein: die Rolle bleibt für dich dauerhaft in der Spielerliste sichtbar. Abklingzeit: {cooldown} Minuten.',
  1, 0, 1, 'assets/icons/roles/hellseherin.png', 0, 0, 0, 0, 40, 0),

(5,  'Detektiv',    0,
  'Ermittelt passiv: Nach jeder Mordserie erfährt er automatisch einen Spieler, der sicher kein Killer ist.',
  'Deine Fähigkeit ist passiv — du musst nichts tun. Immer wenn so viele Morde geschehen sind, wie es Killer im Spiel gibt, zeigt dir die App automatisch einen zufälligen Spieler mit "✅ Kein Killer" in der Spielerliste an. Du bekommst dann eine Benachrichtigung.',
  1, 0, 1, 'assets/icons/roles/detektiv.png', 0, 0, 0, 0, 50, 0),

(6,  'Das Paar',    0,
  '2 Spieler bilden ein Paar und kennen sich von Beginn an.',
  'Öffne beim Spielstart die Augen wenn das Paar aufgerufen wird — ihr kennt euren Partner. Stirbt dein Partner, nimmst du dir das Leben sobald du seinen Tod bemerkst.',
  1, 0, 2, 'assets/icons/roles/das-paar.png', 1, 0, 0, 0, 60, 1),

(7,  'Dodo',        0,
  'Gewinnt das Spiel, indem er von der Gruppe erhängt wird.',
  'Du gewinnst, wenn die Versammlung dich erhängt. Wirst du von einem Mörder getötet, hat deine Rolle keine Auswirkung. Du darfst KEINE Mordwaffe bei dir tragen.',
  1, 0, 1, 'assets/icons/roles/dodo.png', 0, 0, 0, 0, 70, 0),

-- Optionale Zusatzrollen (standardmäßig deaktiviert)
(8,  'Celebrity',   0,
  'Sein Tod fällt sofort auf — er trägt direkt Todeszeitpunkt und Ort ein.',
  'Du bist allgemein bekannt. Stirbst du, trägst du sofort Zeitpunkt und Ort in die Todesliste ein.',
  1, 0, 1, 'assets/icons/roles/celebrity.png', 0, 0, 1, 0, 80, 0),

(9,  'Gunslinger',  0,
  'Kann beliebig oft schießen. Trifft er einen Killer, überlebt er. Trifft er einen Unschuldigen, stirbt er selbst.',
  'Du hast eine Waffe ohne Schussbegrenzung. Schießt du auf einen Killer (z. B. Mörder), lebst du weiter und kannst erneut schießen. Triffst du einen Unschuldigen, stirbst du selbst. Du stehst auf Seite der Bürger.',
  1, 0, 1, 'assets/icons/roles/gunslinger.png', 0, 0, 0, 0, 90, 0),

(10, 'Sheriff',     0,
  'Kann unbegrenzt Spieler erschießen — tötet er jedoch einen Unschuldigen, stirbt er selbst.',
  'Du kannst so viele Spieler erschießen wie du willst. Tötest du jedoch nicht den Dodo oder einen Mörder, stirbst du selbst.',
  1, 0, 1, 'assets/icons/roles/sheriff.png', 0, 0, 0, 0, 100, 0),

-- Geplante Rollen (active=0) — Mechaniken noch nicht gebaut, Details
-- und benötigte Flags siehe db/neue_rollen_vorlagen.sql
(11, 'Leichenfresser', 30,
  'Killer, dessen Opfer spurlos verschwinden — sie können nicht vom Nekromanten befragt werden.',
  'Zeige einem anderen Spieler die Mordwaffe — dieser ist sofort tot. Deine Opfer hinterlassen keine Orts- und Zeitspuren und können NICHT vom Nekromanten befragt werden. Abklingzeit: {cooldown} Minuten.',
  0, 0, 1, NULL, 0, 0, 0, 1, 110, 0),

(12, 'Auftragskiller', 5,
  'Killer mit Auftrag: Er bekommt ein zufälliges Ziel — erst nach dessen Tod das nächste.',
  'Die App zeigt dir ein zufälliges Ziel. Nur dieses Ziel darfst du töten (Mordwaffe zeigen). Nach dem Kill bekommst du nach {cooldown} Minuten Abklingzeit ein neues Ziel zugewiesen.',
  0, 0, 1, NULL, 0, 0, 0, 1, 120, 0),

(13, 'Schläfer', 0,
  'Beginnt als Killer — wechselt aber nach einiger Zeit heimlich die Seite und spielt dann für das Dorf.',
  'Du startest im Killer-Team und kennst die anderen Killer. Nach einer zufälligen Zeit wechselst du automatisch die Seite: Ab dann gewinnst du mit den Bürgern. Die anderen Killer wissen von Anfang an nur: "Einer von euch ist ein Verräter."',
  0, 0, 1, NULL, 1, 0, 0, 1, 130, 0),

(14, 'Söldner', 0,
  'Startrolle im Zombie-Modus: Überlebe die Zombie-Plage.',
  'Alle Spieler starten als Söldner. Irgendwann in den ersten Stunden verwandelt sich einer von euch in den ersten Zombie. Findet und eliminiert die Zombies, bevor sie euch alle bekehren.',
  0, 1, 0, NULL, 0, 0, 0, 0, 140, 0),

(15, 'Zombie', 0,
  'Bekehrt Söldner statt sie zu töten — die Plage wächst.',
  'Zeige einem Söldner deine Karte — er ist ab sofort ebenfalls Zombie und spielt für euch weiter. Ihr gewinnt, wenn alle Spieler infiziert sind. Zombies erkennen sich gegenseitig.',
  0, 0, 1, NULL, 1, 0, 0, 1, 150, 0);

-- Flags, die nicht in der Spaltenliste des Seed-INSERTs stehen
UPDATE roles SET rollensicht=1 WHERE name='Hellseherin';
UPDATE roles SET kill_hinweis=1 WHERE name='Detektiv';

-- ── 11. App-Einstellungen (DB-konfigurierbar) ─────────────────────
CREATE TABLE settings (
  `key`       VARCHAR(80)   NOT NULL,
  value       TEXT          NOT NULL,
  type        ENUM('string','int','bool') NOT NULL DEFAULT 'string',
  label       VARCHAR(100)  NOT NULL DEFAULT '',
  description VARCHAR(255)  NOT NULL DEFAULT '',
  sort_order  INT           NOT NULL DEFAULT 0,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB;

INSERT INTO settings (`key`, value, type, label, description, sort_order) VALUES
('app_name',           'Werwolf',                          'string', 'Spielname',              'Anzeigename der App — überall sichtbar.',                         10),
('app_version',        '0.28',                            'string', 'Versionsnummer',          'Anzeigeversion z. B. in Fußzeile oder About-Seite.',             15),
('beta_mode',          '1',                               'bool',   'Beta-Modus',              'Zeigt einen Beta-Hinweis im Spielfenster an.',                    16),
('app_debug',          '1',                               'bool',   'Debug-Modus',             'PHP-Fehler anzeigen. Im Produktivbetrieb auf 0 setzen.',          20),
('default_theme',      'gothic',                          'string', 'Standard-Theme',          'Theme für neue Nutzer ohne gespeichertes Theme.',                30),
('login_subtitle',     'Das Dorf schläft … doch die Wölfe nicht.', 'string', 'Login-Untertitel', 'Slogan unter dem Spielnamen auf der Anmeldeseite.',           35),
('min_players',        '4',                               'int',    'Mindest-Spielerzahl',     'Mindestanzahl Spieler für den Spielstart.',                       40),
('max_players',        '30',                              'int',    'Maximal-Spielerzahl',     'Maximale Anzahl Spieler pro Spiel.',                              50),
('default_role_icon',  'assets/icons/roles/_default.png', 'string', 'Standard-Rollen-Icon',   'Fallback-Icon-Pfad wenn eine Rolle kein eigenes Icon hat.',       70),
('session_lifetime',   '604800',                          'int',    'Session-Dauer (Sek.)',    'Anmeldedauer in Sekunden (604800 = 7 Tage, 86400 = 1 Tag).',     80),
('deaths_empty_title', 'Noch niemand gestorben',          'string', 'Todesliste: Leertitel',   'Überschrift wenn noch niemand gestorben ist.',                    90),
('deaths_empty_sub',   'Das Dorf ist in Frieden … noch.','string', 'Todesliste: Leer-Subtext','Untertitel wenn noch niemand gestorben ist.',                     91),
('deaths_peace_text',  'Mögen sie in Frieden ruhen',      'string', 'Todesliste: Friedenstext','Text unter dem Friedhof-Bereich wenn Tote vorhanden sind.',      92),
('login_logo',         '',                                'string', 'Login-Logo',              'Pfad zum Bild auf der Anmeldeseite (leer = Wolf-Emoji 🐺).',       5),
('game_timezone',      'Europe/Berlin',                   'string', 'Zeitzone',                'PHP-Zeitzone des Servers (z.B. Europe/Berlin, UTC).',             20),
('push_cooldown',      '5',                               'int',    'Push-Cooldown (Min.)',    'Mindestwartezeit zwischen zwei Auto-Push-Benachrichtigungen.',    26),
('voice_messages_enabled', '1',                           'bool',   'Sprachnachrichten',       'Spieler dürfen Fragen als Sprachnachricht (max. 1 Min.) aufnehmen.', 27),
('voice_transcription_enabled', '0',                       'bool',   'Sprachnachrichten-Transkription', 'Erlaubt dem Spielleiter, Sprachnachrichten per OpenAI-API automatisch in Text umzuwandeln (Grundlage für die FAQ-Übernahme).', 28),
('openai_api_key',      '',                                'string', 'OpenAI API-Key',          'Wird nur für die Sprachnachrichten-Transkription verwendet. Wert wird in der Oberfläche nie im Klartext angezeigt.', 29),
('clear_messages_on_start', '0',                           'bool',   'Nachrichten bei Spielstart löschen', 'Beim Start eines neuen Spiels: alle Sprachnachrichten (immer) sowie alle Text-Fragen ohne FAQ-Veröffentlichung werden gelöscht.', 22),
('push_last_sent',     '0',                               'int',    'Push: letzter Versand (intern)', 'Unix-Timestamp des letzten gesendeten Pushes (intern).', 999);

-- ── 12. Erstes Spiel anlegen ──────────────────────────────────────
INSERT INTO games (id, status) VALUES (1, 'lobby');
