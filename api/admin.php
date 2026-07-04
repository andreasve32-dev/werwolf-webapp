<?php
// Copyright (c) 2026 Andreas Vetter
require_once dirname(__DIR__) . '/core/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
Auth::requireAdmin();

// Admin noch in der Datenbank vorhanden? (z.B. nach Löschung durch anderen Admin)
$_adminId=Auth::player()['id'];
if(!Database::queryOne("SELECT id FROM players WHERE id=? AND is_admin=1",[$_adminId])){
    http_response_code(401);
    echo json_encode(['error'=>'Nicht eingeloggt.']);
    exit;
}

// Session-Lock freigeben: kein Endpunkt hier schreibt $_SESSION, aber ohne
// write_close serialisiert PHP alle parallelen Poll-Requests desselben Nutzers.
session_write_close();

$input=jsonBody();
$action=$input['action']??'';
$gameId=(int)($input['game_id']??0);

require_once CORE_PATH . '/WebPush.php';

/**
 * Prüft Siegbedingungen; beendet das Spiel automatisch und sendet Push wenn nötig.
 * Gibt den Sieger-Schlüssel zurück ('killer'|'citizen'|'dodo') oder null.
 */
function checkAndEndGame(int $gameId): ?string {
    $g = Database::queryOne("SELECT status FROM games WHERE id=?", [$gameId]);
    if (!$g || $g['status'] !== 'running') return null;

    // 1. Dodo per Abstimmung gehenkt?
    $dodoHanged = Database::queryOne(
        "SELECT d.id FROM deaths d JOIN roles r ON r.id=d.role_id
         WHERE d.game_id=? AND d.is_gehenkt=1 AND r.name='Dodo' LIMIT 1",
        [$gameId]
    );
    if ($dodoHanged) {
        Database::execute("UPDATE games SET status='finished', winner='dodo' WHERE id=?", [$gameId]);
        WebPush::sendToGame($gameId, true, '🐦 Dodo hat gewonnen!', 'Er wurde vom Dorf erhängt — sein Plan ist aufgegangen. Spiel beendet.');
        return 'dodo';
    }

    $aliveKillers = (int)(Database::queryOne(
        "SELECT COUNT(*) AS cnt FROM game_players gp JOIN roles r ON r.id=gp.role_id
         WHERE gp.game_id=? AND gp.is_alive=1 AND r.is_killer=1", [$gameId]
    )['cnt'] ?? 0);
    $aliveNonKillers = (int)(Database::queryOne(
        "SELECT COUNT(*) AS cnt FROM game_players gp LEFT JOIN roles r ON r.id=gp.role_id
         WHERE gp.game_id=? AND gp.is_alive=1 AND (r.is_killer=0 OR r.id IS NULL)", [$gameId]
    )['cnt'] ?? 0);
    $totalKillers = (int)(Database::queryOne(
        "SELECT COUNT(*) AS cnt FROM game_players gp JOIN roles r ON r.id=gp.role_id
         WHERE gp.game_id=? AND r.is_killer=1", [$gameId]
    )['cnt'] ?? 0);

    // 2. Bürger-Sieg: alle Killer tot
    if ($totalKillers > 0 && $aliveKillers === 0) {
        Database::execute("UPDATE games SET status='finished', winner='citizen' WHERE id=?", [$gameId]);
        WebPush::sendToGame($gameId, true, '🏘️ Bürger haben gewonnen!', 'Alle Mörder sind tot — das Dorf ist gerettet! Spiel beendet.');
        return 'citizen';
    }

    // 3. Mörder-Sieg: lebende Spieler ≤ 2× lebende Mörder
    if ($aliveKillers > 0 && $aliveKillers >= $aliveNonKillers) {
        Database::execute("UPDATE games SET status='finished', winner='killer' WHERE id=?", [$gameId]);
        WebPush::sendToGame($gameId, true, '🔪 Mörder haben gewonnen!', "{$aliveKillers} Mörder, {$aliveNonKillers} Überlebende — das Dorf ist verloren. Spiel beendet.");
        return 'killer';
    }

    return null;
}

// Dünne lokale Aliase um die zentralen Helper aus core/helpers.php —
// vermeidet doppelte json_encode-Logik, behält aber die kurze
// Schreibweise ok()/err() bei, die in dieser Datei an 25+ Stellen
// verwendet wird.
function ok($msg='OK',$extra=[]){ jsonOk($msg,$extra); }
function err($msg,$code=400){ jsonError($msg,$code); }

switch($action){
  case 'start_game':
    $g=Database::queryOne("SELECT * FROM games WHERE id=? AND status='lobby'",[$gameId]);
    if(!$g) err('Nicht in Lobby');

    // Spieler holen
    $ids = array_column(
      Database::query("SELECT player_id FROM game_players WHERE game_id=?",[$gameId]),
      'player_id'
    );
    $playerCount = count($ids);
    if($playerCount < MIN_PLAYERS) err('Mindestens '.MIN_PLAYERS.' Spieler erforderlich');

    // Aktive Sonderrollen laden (amount > 0, Dorfbewohner = amount 0 oder hat fill=1)
    // "Auffüll-Rolle" = die Rolle mit dem Flag fill=1 (Dorfbewohner).
    // Alle anderen aktiven Rollen mit amount > 0 sind Sonderrollen.
    $fillRole   = Database::queryOne("SELECT id,name FROM roles WHERE active=1 AND fill=1 LIMIT 1");
    $fillRoleId = $fillRole ? (int)$fillRole['id'] : null;

    $specialRoles = Database::query(
      "SELECT id, name, amount FROM roles WHERE active=1 AND fill=0 AND amount>0 ORDER BY sort_order"
    );

    // Sonderrollen-Pool aufbauen
    $pool = [];
    foreach($specialRoles as $r){
      for($i=0;$i<(int)$r['amount'];$i++) $pool[]=(int)$r['id'];
    }
    $specialCount = count($pool);

    // FEHLER: Sonderrollen übersteigen Spieleranzahl
    if($specialCount > $playerCount){
      $names = implode(', ', array_map(fn($r)=>$r['name'].'('.$r['amount'].'x)', $specialRoles));
      err("Sonderrollen ({$specialCount}) übersteigen Spieleranzahl ({$playerCount}).\n".
          "Aktive Sonderrollen: {$names}.\n".
          'Bitte unter "Rollen verwalten" die Anzahlen anpassen.');
    }

    // Restliche Spieler bekommen die Auffüll-Rolle
    $remaining = $playerCount - $specialCount;
    for($i=0;$i<$remaining;$i++) $pool[]=$fillRoleId; // null wenn keine Auffüll-Rolle definiert

    // Zufällig mischen und zuweisen
    shuffle($ids);
    shuffle($pool);
    foreach($ids as $i=>$pid){
      Database::execute(
        "UPDATE game_players SET role_id=? WHERE game_id=? AND player_id=?",
        [$pool[$i]??null,$gameId,$pid]
      );
    }

    // Spiel starten
    Database::execute("UPDATE games SET status='running',phase='day',round=1 WHERE id=?",[$gameId]);

    WebPush::sendToGame($gameId, true, '▶️ Spiel gestartet!', 'Das Spiel läuft — viel Erfolg!');

    $msg = "▶ Spiel gestartet! {$specialCount} Sonderrollen + {$remaining} ";
    $msg .= $fillRole ? $fillRole['name'] : 'ohne Rolle';
    $msg .= " vergeben.";
    ok($msg);break;

  case 'switch_phase':
    $g=Database::queryOne("SELECT * FROM games WHERE id=? AND status='running'",[$gameId]);
    if(!$g)err('Spiel läuft nicht');
    $np=$g['phase']==='day'?'night':'day';
    $nr=$g['round']+($np==='day'?1:0);
    Database::execute("UPDATE games SET phase=?,round=? WHERE id=?",[$np,$nr,$gameId]);
    if($np==='day'){
        WebPush::sendToGame($gameId,true,'☀️ Bürgerversammlung!','Tag '.$nr.' beginnt — kommt zusammen und stimmt ab.');
    } else {
        WebPush::sendToGame($gameId,true,'🌕 Die Nacht bricht herein','Runde '.$g['round'].' — haltet die Augen offen.');
    }
    ok($np==='night'?"🌕 Nacht {$g['round']} beginnt":"☀️ Tag {$nr} beginnt");break;

  case 'execute_vote':
    $g=Database::queryOne("SELECT * FROM games WHERE id=? AND status='running'",[$gameId]);
    if(!$g)err('Spiel läuft nicht');
    $pid=(int)($input['player_id']??0);
    if(!$pid){
      $top=Database::queryOne("SELECT target_id,COUNT(*) as cnt FROM votes WHERE game_id=? AND round=? GROUP BY target_id ORDER BY cnt DESC LIMIT 1",[$gameId,$g['round']]);
      if(!$top)err('Keine Stimmen');
      $pid=$top['target_id'];
    }
    recordDeath($gameId,$pid,$g['round'],'day',null,true);
    Database::execute("DELETE FROM votes WHERE game_id=? AND round=?",[$gameId,$g['round']]);
    $n=Database::queryOne("SELECT display_name FROM players WHERE id=?",[$pid]);
    $winner = checkAndEndGame($gameId);
    if (!$winner) WebPush::sendToGame($gameId,true,'⚖️ Hinrichtung!',($n['display_name']??'Jemand').' wurde vom Dorf gehenkt.');
    $winMsg = ['dodo'=>' 🐦 Dodo-Sieg!','citizen'=>' 🏘️ Bürger-Sieg!','killer'=>' 🔪 Mörder-Sieg!'];
    ok("⚖️ {$n['display_name']} wurde gehenkt.".($winner ? $winMsg[$winner] : ''), ['game_ended' => (bool)$winner, 'winner' => $winner]);break;

  case 'free_accused':
    $g=Database::queryOne("SELECT * FROM games WHERE id=? AND status='running'",[$gameId]);
    if(!$g)err('Spiel läuft nicht');
    Database::execute("DELETE FROM votes WHERE game_id=? AND round=?",[$gameId,$g['round']]);
    ok("✓ Angeklagter freigesprochen — Stimmen gelöscht.");break;

  case 'execute_night':
    $g=Database::queryOne("SELECT * FROM games WHERE id=? AND status='running'",[$gameId]);
    if(!$g)err('Spiel läuft nicht');
    $top=Database::queryOne("SELECT target_player_id,COUNT(*) as cnt FROM night_actions WHERE game_id=? AND round=? AND action_type='wolf_kill' GROUP BY target_player_id ORDER BY cnt DESC LIMIT 1",[$gameId,$g['round']]);
    if(!$top){ok('Wölfe haben niemanden gewählt.');break;}
    recordDeath($gameId,$top['target_player_id'],$g['round'],'night');
    $n=Database::queryOne("SELECT display_name FROM players WHERE id=?",[$top['target_player_id']]);
    $winner = checkAndEndGame($gameId);
    if (!$winner) WebPush::sendToGame($gameId,true,'💀 Ein Spieler ist tot',($n['display_name']??'Jemand').' wurde in der Nacht zerrissen.');
    $winMsg = ['dodo'=>' 🐦 Dodo-Sieg!','citizen'=>' 🏘️ Bürger-Sieg!','killer'=>' 🔪 Mörder-Sieg!'];
    ok("🐺 {$n['display_name']} wurde zerrissen!".($winner ? $winMsg[$winner] : ''), ['game_ended' => (bool)$winner, 'winner' => $winner]);break;

  case 'kill_player':
    $g=Database::queryOne("SELECT * FROM games WHERE id=? AND status='running'",[$gameId]);
    if(!$g)err('Spiel läuft nicht');
    $pid=(int)($input['player_id']??0);
    $isGehenkt = ($input['cause'] ?? '') === 'vote';
    recordDeath($gameId,$pid,$g['round'],$g['phase'],null,$isGehenkt);
    $kn=Database::queryOne("SELECT display_name FROM players WHERE id=?",[$pid]);
    $winner = checkAndEndGame($gameId);
    if (!$winner) WebPush::sendToGame($gameId,true,'💀 Ein Spieler ist tot',($kn['display_name']??'Jemand').' ist aus dem Spiel ausgeschieden.');
    $winMsg = ['dodo'=>' 🐦 Dodo-Sieg!','citizen'=>' 🏘️ Bürger-Sieg!','killer'=>' 🔪 Mörder-Sieg!'];
    ok('Spieler gestorben'.($winner ? $winMsg[$winner] : ''), ['game_ended' => (bool)$winner, 'winner' => $winner]);break;

  case 'revive_player':
    if (!APP_DEBUG) err('Nur im Debug-Modus verfügbar.', 403);
    $g=Database::queryOne("SELECT * FROM games WHERE id=? AND status='running'",[$gameId]);
    if(!$g)err('Spiel läuft nicht');
    $pid=(int)($input['player_id']??0);
    $gp=Database::queryOne("SELECT is_alive FROM game_players WHERE game_id=? AND player_id=?",[$gameId,$pid]);
    if(!$gp)err('Spieler nicht im Spiel');
    if($gp['is_alive'])err('Spieler ist bereits am Leben');
    Database::execute("UPDATE game_players SET is_alive=1 WHERE game_id=? AND player_id=?",[$gameId,$pid]);
    Database::execute("DELETE FROM deaths WHERE game_id=? AND player_id=?",[$gameId,$pid]);
    $rn=Database::queryOne("SELECT display_name FROM players WHERE id=?",[$pid]);
    WebPush::sendToGame($gameId,true,'🔮 Ein Toter lebt!',($rn['display_name']??'Jemand').' wurde vom Spielleiter wiederbelebt.');
    ok(($rn['display_name']??'Spieler').' wiederbelebt.');break;

  case 'set_own_role':
    if (!APP_DEBUG) err('Nur im Debug-Modus verfügbar.', 403);
    $g = Database::queryOne("SELECT * FROM games WHERE id=? AND status='running'", [$gameId]);
    if (!$g) err('Spiel läuft nicht');
    $roleId = (int)($input['role_id'] ?? 0);
    if (!$roleId) err('Keine Rolle angegeben');
    $role = Database::queryOne("SELECT id, name FROM roles WHERE id=? AND active=1", [$roleId]);
    if (!$role) err('Rolle nicht gefunden oder inaktiv');
    $me = Database::queryOne("SELECT id FROM game_players WHERE game_id=? AND player_id=?", [$gameId, $_adminId]);
    if (!$me) err('Du bist nicht als Spieler im Spiel');
    Database::execute("UPDATE game_players SET role_id=? WHERE game_id=? AND player_id=?", [$roleId, $gameId, $_adminId]);
    ok('🎭 Rolle gesetzt: ' . $role['name'], ['role_name' => $role['name']]);break;

  case 'add_player':
    $pid=(int)($input['player_id']??0);
    Database::execute("INSERT IGNORE INTO game_players (game_id,player_id) VALUES (?,?)",[$gameId,$pid]);
    ok('Hinzugefügt');break;

  case 'add_all_players':
    $g=Database::queryOne("SELECT status FROM games WHERE id=?",[$gameId]);
    if(!$g||$g['status']!=='lobby') err('Nur in der Lobby möglich');
    Database::execute(
      "INSERT IGNORE INTO game_players (game_id,player_id)
       SELECT ?,id FROM players WHERE id NOT IN (SELECT player_id FROM game_players WHERE game_id=?)",
      [$gameId,$gameId]
    );
    ok('Alle Spieler hinzugefügt');break;

  case 'remove_player':
    $pid=(int)($input['player_id']??0);
    Database::execute("DELETE FROM game_players WHERE game_id=? AND player_id=?",[$gameId,$pid]);
    ok('Entfernt');break;

  case 'add_slogan':
    $text  = trim($input['text'] ?? '');
    $phase = in_array($input['phase'] ?? '', ['day','night']) ? $input['phase'] : 'day';
    if ($text === '') err('Text darf nicht leer sein');
    if (mb_strlen($text) > 255) err('Text zu lang (max. 255 Zeichen)');
    $count = (int)(Database::queryOne("SELECT COUNT(*) AS c FROM slogans WHERE phase=?", [$phase])['c'] ?? 0);
    if ($count >= 20) err('Maximal 20 Sprüche pro Phase erlaubt');
    try {
        Database::execute("INSERT INTO slogans (text, phase) VALUES (?,?)", [$text, $phase]);
        $newSloganId = Database::lastId();
        require_once TEMPLATE_PATH . '/slogan_row.php';
        ok('Spruch gespeichert', ['html' => sloganRow(['id'=>$newSloganId,'text'=>$text,'active'=>1])]);
    } catch (\Throwable $e) { err('Dieser Spruch existiert bereits'); }
    break;

  case 'delete_slogan':
    $sid = (int)($input['slogan_id'] ?? 0);
    if (!$sid) err('Ungültige ID');
    Database::execute("DELETE FROM slogans WHERE id=?", [$sid]);
    ok('Gelöscht');break;

  case 'toggle_slogan':
    $sid = (int)($input['slogan_id'] ?? 0);
    if (!$sid) err('Ungültige ID');
    Database::execute("UPDATE slogans SET active = 1 - active WHERE id=?", [$sid]);
    ok('Gespeichert');break;

  case 'end_game':
    Database::execute("UPDATE games SET status='finished' WHERE id=?",[$gameId]);
    WebPush::sendToGame($gameId, true, '🏁 Spiel beendet!', 'Das Spiel ist vorbei — danke für eure Teilnahme!');
    ok('🏁 Spiel beendet');break;

  case 'reset_game':
    foreach(['game_players','deaths','votes','night_actions'] as $t)
      Database::execute("DELETE FROM {$t} WHERE game_id=?",[$gameId]);
    Database::execute("UPDATE games SET status='lobby',phase='day',round=0,winner=NULL WHERE id=?",[$gameId]);
    ok('🔄 Spiel zurückgesetzt');break;

  case 'new_game':
    Database::execute("INSERT INTO games (status) VALUES ('lobby')");
    $id=Database::lastId();
    ok("➕ Neues Spiel #{$id}",['game_id'=>$id]);break;

  // ── Rollen-Verwaltung (CRUD) ──────────────────────────────────
  case 'role_create':
    $name = trim($input['name'] ?? '');
    if(!$name) err('Name erforderlich');
    $exists = Database::queryOne("SELECT id FROM roles WHERE name=?",[$name]);
    if($exists) err('Eine Rolle mit diesem Namen existiert bereits');
    $cooldown = (int)($input['cooldown'] ?? 0);
    if ($cooldown < 0 || $cooldown > 10080) err('Cooldown: 0–10080 Minuten (max. 7 Tage).');
    Database::execute(
      "INSERT INTO roles (name,cooldown,description,rules,active,amount,fill,icon_path,sichtbar,killer_sichtbar,befragen,auto_eintrag,is_killer,sort_order,linked_death) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
      [
        $name,
        $cooldown,
        trim($input['description'] ?? ''),
        trim($input['rules'] ?? ''),
        !empty($input['active']) ? 1 : 0,
        max(0,(int)($input['amount'] ?? 1)),
        !empty($input['fill']) ? 1 : 0,
        trim($input['icon_path'] ?? '') ?: null,
        !empty($input['sichtbar']) ? 1 : 0,
        !empty($input['killer_sichtbar']) ? 1 : 0,
        !empty($input['befragen']) ? 1 : 0,
        !empty($input['auto_eintrag']) ? 1 : 0,
        !empty($input['is_killer']) ? 1 : 0,
        (int)($input['sort_order'] ?? 0),
        !empty($input['linked_death']) ? 1 : 0,
      ]
    );
    $newRoleId = Database::lastId();
    require_once TEMPLATE_PATH . '/role_card.php';
    $newRole = Database::queryOne("SELECT * FROM roles WHERE id=?", [$newRoleId]);
    ok('🎭 Rolle erstellt', ['role_id' => $newRoleId, 'html' => render_role_card($newRole)]);break;

  case 'role_update':
    $roleId = (int)($input['role_id'] ?? 0);
    $existing = Database::queryOne("SELECT id FROM roles WHERE id=?",[$roleId]);
    if(!$existing) err('Rolle nicht gefunden');
    $name = trim($input['name'] ?? '');
    if(!$name) err('Name erforderlich');
    $dup = Database::queryOne("SELECT id FROM roles WHERE name=? AND id!=?",[$name,$roleId]);
    if($dup) err('Eine andere Rolle hat bereits diesen Namen');
    $cooldown = (int)($input['cooldown'] ?? 0);
    if ($cooldown < 0 || $cooldown > 10080) err('Cooldown: 0–10080 Minuten (max. 7 Tage).');
    Database::execute(
      "UPDATE roles SET name=?,cooldown=?,description=?,rules=?,active=?,amount=?,fill=?,icon_path=?,sichtbar=?,killer_sichtbar=?,befragen=?,auto_eintrag=?,is_killer=?,sort_order=?,linked_death=? WHERE id=?",
      [
        $name,
        $cooldown,
        trim($input['description'] ?? ''),
        trim($input['rules'] ?? ''),
        !empty($input['active']) ? 1 : 0,
        max(0,(int)($input['amount'] ?? 1)),
        !empty($input['fill']) ? 1 : 0,
        trim($input['icon_path'] ?? '') ?: null,
        !empty($input['sichtbar']) ? 1 : 0,
        !empty($input['killer_sichtbar']) ? 1 : 0,
        !empty($input['befragen']) ? 1 : 0,
        !empty($input['auto_eintrag']) ? 1 : 0,
        !empty($input['is_killer']) ? 1 : 0,
        (int)($input['sort_order'] ?? 0),
        !empty($input['linked_death']) ? 1 : 0,
        $roleId,
      ]
    );
    require_once TEMPLATE_PATH . '/role_card.php';
    $updatedRole = Database::queryOne("SELECT * FROM roles WHERE id=?", [$roleId]);
    ok('🎭 Rolle aktualisiert', ['html' => render_role_card($updatedRole)]);break;

  case 'role_delete':
    $roleId = (int)($input['role_id'] ?? 0);
    // FK ON DELETE SET NULL kümmert sich um game_players/deaths-Referenzen
    Database::execute("DELETE FROM roles WHERE id=?",[$roleId]);
    ok('🗑️ Rolle gelöscht');break;

  case 'role_toggle_active':
    $roleId = (int)($input['role_id'] ?? 0);
    $r = Database::queryOne("SELECT active FROM roles WHERE id=?",[$roleId]);
    if(!$r) err('Rolle nicht gefunden');
    $newActive = $r['active'] ? 0 : 1;
    Database::execute("UPDATE roles SET active=? WHERE id=?",[$newActive,$roleId]);
    ok('Status geändert', ['active' => $newActive]);break;

  case 'get_dashboard':
    require_once TEMPLATE_PATH . '/admin_dashboard_blocks.php';
    $g = Database::queryOne("SELECT id FROM games WHERE id=?", [$gameId]);
    if (!$g) err('Spiel nicht gefunden');
    $dashState = admin_compute_state($gameId);
    blocksResponse([
        'win-banner'        => admin_render_win_banner($dashState),
        'assembly-banner'   => admin_render_assembly_banner($dashState),
        'game-controls'     => admin_render_game_controls($dashState),
        'player-list-body'  => admin_render_player_list($dashState),
        'add-players-card'  => admin_render_add_players($dashState),
        'role-preview-card' => admin_render_role_preview($dashState),
        'voting-card'       => admin_render_voting($dashState),
        'kill-quick-card'   => admin_render_kill_quick($dashState),
    ], $input['blocks_hash'] ?? null);break;

  default:
    err('Unbekannte Aktion');
}
