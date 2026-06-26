-- ============================================================
--  Migration: day_slogans Einstellung
--  Führe dies einmalig aus wenn die DB bereits existiert.
-- ============================================================

INSERT IGNORE INTO settings (`key`, value, type, label, description, sort_order)
VALUES ('day_slogans',
'30 Grad im Schatten im Dorf
Die Hühner legen heute mehr als sonst
Der Bürgermeister schläft schon wieder
Jemand hat die letzte Wurst geklaut
Der Brunnen riecht heute komisch
Das Bier im Wirtshaus ist schon alle
Drei Krähen kreisen über dem Kirchturm
Der Schmied hat sich wieder auf den Daumen verhauen
Die Katze des Pfarrers ist seit gestern weg
Heute gibt es Rübensuppe beim Wirt
Die Wetterfahne dreht sich seltsam
Irgendjemand hat die Scheunentür offengelassen
Die alte Marie hat mal wieder komisch geschaut
Seltsame Fußspuren im Morast hinter der Mühle
Die Milch ist heute besonders sauer
Pfarrer Klemens hat die Predigt wieder verlängert
Im Dorf herrscht trügerische Stille
Das Kalb vom Bauern Huber hat dreimal gemuht
Der Mond sah heute Nacht besonders groß aus
Die Gänse sind nervöser als üblich',
'string', 'Tages-Slogans', 'Zufallssprüche im Tages-Banner (eine Zeile = ein Slogan).', 25);
