-- ============================================================
--  WERWOLF — Vorlagen für geplante neue Rollen (NICHT ausführen!)
--  Rekonstruiert am 07.07.2026 aus den Ideen in info.txt, nachdem
--  die alte Backup-Datei auf dem Server überschrieben wurde.
--  Zweck: fertige Texte/Flags zum späteren Einbauen — die Rollen
--  werden hier bewusst mit active=0 angelegt.
--  Diese Datei liegt im Git-Repo und wird von keinem Deploy und
--  keinem Setup-Lauf angefasst.
-- ============================================================

-- Spaltenreferenz (Stand v0.0.25):
-- (name, cooldown, description, rules, active, fill, amount, icon_path,
--  sichtbar, killer_sichtbar, befragen, auto_eintrag, is_killer,
--  sort_order, linked_death, rollensicht, kill_hinweis)

-- ── 1. Leichenfresser ─────────────────────────────────────────
-- Killer-Variante: seine Opfer hinterlassen keine Spuren und können
-- nicht vom Nekromanten befragt werden.
-- ⚠️ Braucht neues Flag (z.B. keine_spuren): Tod durch diese Rolle
--    setzt deaths so, dass update_death_info/Befragen gesperrt ist
--    und ort/zeit leer bleiben.
INSERT INTO roles (name, cooldown, description, rules, active, fill, amount, icon_path, sichtbar, killer_sichtbar, befragen, auto_eintrag, is_killer, sort_order, linked_death, rollensicht, kill_hinweis) VALUES
('Leichenfresser', 30,
 'Killer, dessen Opfer spurlos verschwinden — sie können nicht vom Nekromanten befragt werden.',
 'Zeige einem anderen Spieler die Mordwaffe — dieser ist sofort tot. Deine Opfer hinterlassen keine Orts- und Zeitspuren und können NICHT vom Nekromanten befragt werden. Abklingzeit: {cooldown} Minuten.',
 0, 0, 1, NULL, 0, 0, 0, 0, 1, 110, 0, 0, 0);

-- ── 2. Auftragskiller ─────────────────────────────────────────
-- Killer mit zugewiesenem Ziel: erst wenn das Ziel tot ist, gibt es
-- ein neues (zufällig). Kurzer Cooldown von 5 Minuten nach jedem Kill.
-- ⚠️ Braucht neue Mechanik: Zielzuweisung (z.B. Tabelle/Spalte
--    assigned_target in game_players oder role_insights-artige Tabelle),
--    Anzeige des Ziels nur für den Auftragskiller auf der Statuskarte.
INSERT INTO roles (name, cooldown, description, rules, active, fill, amount, icon_path, sichtbar, killer_sichtbar, befragen, auto_eintrag, is_killer, sort_order, linked_death, rollensicht, kill_hinweis) VALUES
('Auftragskiller', 5,
 'Killer mit Auftrag: Er bekommt ein zufälliges Ziel — erst nach dessen Tod das nächste.',
 'Die App zeigt dir ein zufälliges Ziel. Nur dieses Ziel darfst du töten (Mordwaffe zeigen). Nach dem Kill bekommst du nach {cooldown} Minuten Abklingzeit ein neues Ziel zugewiesen.',
 0, 0, 1, NULL, 0, 0, 0, 0, 1, 120, 0, 0, 0);

-- ── 3. Schläfer / Verräter ────────────────────────────────────
-- Startet im Killer-Team, wechselt nach X Stunden automatisch die
-- Seite. Alle anderen Killer werden beim Spielstart vorgewarnt:
-- "Einer von euch ist ein Verräter" (ohne Namen).
-- ⚠️ Braucht neue Mechanik: zeitgesteuerter Seitenwechsel (Timestamp
--    beim Spielstart + Prüfung im Poll), Warnhinweis auf den
--    Statuskarten der Killer, Umbuchung is_killer-Wertung bei der
--    Siegprüfung nach dem Wechsel.
INSERT INTO roles (name, cooldown, description, rules, active, fill, amount, icon_path, sichtbar, killer_sichtbar, befragen, auto_eintrag, is_killer, sort_order, linked_death, rollensicht, kill_hinweis) VALUES
('Schläfer', 0,
 'Beginnt als Killer — wechselt aber nach einiger Zeit heimlich die Seite und spielt dann für das Dorf.',
 'Du startest im Killer-Team und kennst die anderen Killer. Nach einer zufälligen Zeit wechselst du automatisch die Seite: Ab dann gewinnst du mit den Bürgern. Die anderen Killer wissen von Anfang an nur: "Einer von euch ist ein Verräter."',
 0, 0, 1, NULL, 1, 0, 0, 0, 1, 130, 0, 0, 0);

-- ── 4. Sheriff als bekannte Rolle (Überarbeitung des Sheriffs) ─
-- Permanent ALLEN Spielern sichtbar; erhält nach JEDEM Mord einen
-- garantierten Nicht-Killer angezeigt (statt je Anzahl-Killer-Morde
-- wie beim Detektiv).
-- ⚠️ Braucht: neues Flag "öffentlich sichtbar" (alle sehen die Rolle
--    in der Spielerliste) + kill_hinweis-Variante mit Frequenz
--    "jeder Mord" (z.B. Spalte kill_hinweis als Frequenz-Zahl statt
--    Bool: 0=aus, 1=je #Killer, 2=jeder Mord — oder eigenes Flag).
-- Vorlage als UPDATE des bestehenden Sheriffs:
-- UPDATE roles SET
--   description = 'Allen bekannt: Jeder weiß, wer der Sheriff ist. Nach jedem Mord erfährt er automatisch einen Spieler, der sicher kein Killer ist.',
--   rules = 'Deine Rolle ist öffentlich — alle Spieler sehen dich als Sheriff. Du kannst unbegrenzt Spieler erschießen, aber tötest du einen Unschuldigen, stirbst du selbst. Nach jedem Mord im Dorf zeigt dir die App automatisch einen zufälligen Spieler mit "✅ Kein Killer" an.'
-- WHERE name = 'Sheriff';

-- ── 5. Detektiv: Leichen befragen (Erweiterung) ───────────────
-- Zusätzlich zur passiven Ermittlung: Der Detektiv kann eine Leiche
-- befragen und bekommt von ihr einen Spieler genannt, der definitiv
-- nicht der Killer war.
-- ⚠️ Braucht: Interaktion auf der Todesliste (ähnlich Nekromant-
--    Befragen), Ergebnis als role_insights-Eintrag source='leiche'
--    ("✅ Kein Killer"-Badge wie bei kill_hinweis). Pro Leiche nur
--    einmal befragbar.

-- ── 6. Zombie-Spielmodus (alternativer Modus, keine Einzelrolle) ─
-- Alle starten als Söldner; nach 1–5 Stunden zufälliger Zombie-Start:
-- ein Spieler wird Zombie, Infizierte bekehren weitere Spieler.
-- ⚠️ Großprojekt: eigener Spielmodus (games-Spalte mode), Infektions-
--    Mechanik (Kill = Bekehrung statt Tod), eigene Siegbedingungen
--    (Zombies gewinnen wenn alle infiziert / Söldner wenn alle
--    Zombies tot). Rollen-Vorlagen:
INSERT INTO roles (name, cooldown, description, rules, active, fill, amount, icon_path, sichtbar, killer_sichtbar, befragen, auto_eintrag, is_killer, sort_order, linked_death, rollensicht, kill_hinweis) VALUES
('Söldner', 0,
 'Startrolle im Zombie-Modus: Überlebe die Zombie-Plage.',
 'Alle Spieler starten als Söldner. Irgendwann in den ersten Stunden verwandelt sich einer von euch in den ersten Zombie. Findet und eliminiert die Zombies, bevor sie euch alle bekehren.',
 0, 1, 0, NULL, 0, 0, 0, 0, 0, 140, 0, 0, 0),
('Zombie', 0,
 'Bekehrt Söldner statt sie zu töten — die Plage wächst.',
 'Zeige einem Söldner deine Karte — er ist ab sofort ebenfalls Zombie und spielt für euch weiter. Ihr gewinnt, wenn alle Spieler infiziert sind. Zombies erkennen sich gegenseitig.',
 0, 0, 1, NULL, 1, 0, 0, 0, 1, 150, 0, 0, 0);

-- ── 7. Gunslinger: Weiter schießen nach Killer-Treffer ────────
-- Bereits weitgehend im aktuellen Regeltext enthalten ("lebst du
-- weiter und kannst erneut schießen") — bei Gelegenheit den Text
-- schärfen, dass NUR ein Killer-Treffer zum Weiterschießen
-- berechtigt:
-- UPDATE roles SET
--   rules = 'Du hast eine Waffe ohne Schussbegrenzung. Schießt du auf einen Killer (z. B. Mörder), lebst du weiter und darfst sofort erneut schießen. Triffst du einen Unschuldigen, stirbst du selbst. Du stehst auf Seite der Bürger.'
-- WHERE name = 'Gunslinger';
