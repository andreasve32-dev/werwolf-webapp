-- ============================================================
--  Migration: settings-Tabelle hinzufügen
--  Für bestehende Installationen ausführen, die schema.sql
--  bereits abgearbeitet haben.
--  Kann mehrfach ausgeführt werden (IF NOT EXISTS / INSERT IGNORE).
-- ============================================================

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
