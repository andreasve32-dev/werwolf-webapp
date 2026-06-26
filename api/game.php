<?php
// Copyright (c) 2026 Andreas Vetter
require_once dirname(__DIR__) . '/core/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
Auth::requireLogin();

$playerId=Auth::player()['id'];

// Spieler noch in der Datenbank vorhanden? (z.B. nach Admin-Löschung)
if(!Database::queryOne("SELECT id FROM players WHERE id=?",[$playerId])){
    http_response_code(401);
    echo json_encode(['error'=>'Nicht eingeloggt.']);
    exit;
}

$input=jsonBody();
$action=$input['action']??'';
$gameId=(int)($input['game_id']??0);

switch($action){
  case 'join':
    $g=Database::queryOne("SELECT id FROM games WHERE id=? AND status='lobby'",[$gameId]);
    if(!$g){http_response_code(400);echo json_encode(['error'=>'Spiel nicht gefunden']);exit;}
    Database::execute("INSERT IGNORE INTO game_players (game_id,player_id) VALUES (?,?)",[$gameId,$playerId]);
    echo json_encode(['ok'=>true,'message'=>'Beigetreten']);break;

  case 'get_players':
    $players=Database::query(
      "SELECT gp.player_id, gp.is_alive, gp.role_id, p.display_name,
              r.name AS role_name, r.icon_path AS role_icon_path, r.sichtbar AS role_sichtbar
       FROM game_players gp
       JOIN players p ON p.id=gp.player_id
       LEFT JOIN roles r ON r.id=gp.role_id
       WHERE gp.game_id=?
       ORDER BY gp.is_alive DESC, p.display_name",
      [$gameId]
    );
    // Eigene Rolle + sichtbar-Status laden (für Sichtbarkeits-Vergleich)
    $me=Database::queryOne(
      "SELECT gp.role_id, r.sichtbar AS role_sichtbar
       FROM game_players gp LEFT JOIN roles r ON r.id=gp.role_id
       WHERE gp.game_id=? AND gp.player_id=?",
      [$gameId,$playerId]
    );
    $myRoleId  = ($me && $me['role_id'] !== null) ? (int)$me['role_id'] : null;
    $myVisible = $me ? (bool)((int)($me['role_sichtbar'] ?? 0)) : false;

    foreach($players as &$p){
      $pRoleId  = $p['role_id'] !== null ? (int)$p['role_id'] : null;
      $pVisible = (bool)((int)($p['role_sichtbar'] ?? 0));
      $isMe     = (int)$p['player_id'] === (int)$playerId;
      $isDead   = !((bool)$p['is_alive']);

      // Echte Rolle sichtbar, wenn:
      //  a) Mein eigener Eintrag
      //  b) Spieler ist tot (Rolle wird aufgedeckt)
      //  c) Gleiche Rolle + beide sichtbar=1 (z.B. Mörder sehen Mörder)
      $sameVisibleRole = $myRoleId !== null
        && $pRoleId !== null
        && $pRoleId === $myRoleId
        && $myVisible
        && $pVisible;

      if(!$isMe && !$isDead && !$sameVisibleRole){
        // Rolle komplett ausblenden — kein Name, kein Icon
        $p['role_name']      = null;
        $p['role_icon_path'] = null;
        $p['role_sichtbar']  = 0;
      }
    }
    echo json_encode(['players'=>$players]);break;

  case 'vote':
    $tid=(int)($input['target_id']??0);
    $g=Database::queryOne("SELECT * FROM games WHERE id=? AND status='running' AND phase='day'",[$gameId]);
    if(!$g){http_response_code(400);echo json_encode(['error'=>'Nicht möglich']);exit;}
    $me=Database::queryOne("SELECT * FROM game_players WHERE game_id=? AND player_id=? AND is_alive=1",[$gameId,$playerId]);
    if(!$me){http_response_code(400);echo json_encode(['error'=>'Nicht berechtigt']);exit;}
    Database::execute("INSERT INTO votes (game_id,round,voter_id,target_id) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE target_id=VALUES(target_id)",[$gameId,$g['round'],$playerId,$tid]);
    echo json_encode(['ok'=>true]);break;

  case 'self_report_death':
    $g=$gameId ? Database::queryOne("SELECT * FROM games WHERE id=? AND status='running'",[$gameId]) : null;
    if(!$g){http_response_code(400);echo json_encode(['error'=>'Kein laufendes Spiel gefunden']);exit;}
    $me=Database::queryOne("SELECT * FROM game_players WHERE game_id=? AND player_id=? AND is_alive=1",[$gameId,$playerId]);
    if(!$me){http_response_code(400);echo json_encode(['error'=>'Du bist bereits tot oder nicht im Spiel']);exit;}
    $ort=trim($input['ort']??'');
    if(!$ort){http_response_code(400);echo json_encode(['error'=>'Bitte den Todesort angeben.']);exit;}
    if(mb_strlen($ort)>200){http_response_code(400);echo json_encode(['error'=>'Ort zu lang (max. 200 Zeichen)']);exit;}
    recordDeath($gameId,$playerId,(int)$g['round'],$g['phase'],$ort);
    echo json_encode(['ok'=>true,'message'=>'Du wurdest als tot gemeldet.']);break;

  case 'start_cooldown':
    $g=Database::queryOne("SELECT * FROM games WHERE id=? AND status='running'",[$gameId]);
    if(!$g){http_response_code(400);echo json_encode(['error'=>'Kein laufendes Spiel']);exit;}
    $me=Database::queryOne(
      "SELECT gp.*, r.cooldown FROM game_players gp
       LEFT JOIN roles r ON r.id=gp.role_id
       WHERE gp.game_id=? AND gp.player_id=? AND gp.is_alive=1",
      [$gameId,$playerId]
    );
    if(!$me){http_response_code(400);echo json_encode(['error'=>'Nicht berechtigt']);exit;}
    if((int)($me['cooldown']??0)<=0){http_response_code(400);echo json_encode(['error'=>'Deine Rolle hat keinen Cooldown']);exit;}
    if(!empty($me['cooldown_started_at'])){
      $elapsed=time()-strtotime($me['cooldown_started_at']);
      $total=(int)$me['cooldown']*60;
      if($elapsed<$total){http_response_code(400);echo json_encode(['error'=>'Cooldown läuft noch','remaining_secs'=>$total-$elapsed]);exit;}
    }
    Database::execute("UPDATE game_players SET cooldown_started_at=NOW() WHERE game_id=? AND player_id=?",[$gameId,$playerId]);
    $now=date('c');
    echo json_encode(['ok'=>true,'started_at'=>$now]);break;

  case 'update_death_info':
    $deathId = (int)($input['death_id'] ?? 0);
    $ort  = trim($input['ort']  ?? '');
    $zeit = trim($input['zeit'] ?? '');
    if (mb_strlen($ort) > 200) { http_response_code(400); echo json_encode(['error'=>'Ort zu lang']); exit; }
    if (mb_strlen($zeit) > 20)  { http_response_code(400); echo json_encode(['error'=>'Zeit zu lang']);  exit; }
    $isAdmin = (bool)Auth::player()['is_admin'];
    $death = Database::queryOne("SELECT * FROM deaths WHERE id=?", [$deathId]);
    if (!$death) { http_response_code(404); echo json_encode(['error'=>'Nicht gefunden']); exit; }
    if (!$isAdmin && (int)$death['player_id'] !== (int)$playerId) {
        http_response_code(403); echo json_encode(['error'=>'Keine Berechtigung']); exit;
    }
    try {
        Database::execute("UPDATE deaths SET ort=?, zeit=?, rolle_aufgedeckt=1 WHERE id=?", [$ort ?: null, $zeit ?: null, $deathId]);
    } catch (\Throwable $ex) {
        http_response_code(500);
        echo json_encode(['error' => 'DB-Fehler: ' . $ex->getMessage() . ' — Migration db/migration_zeit.sql ausführen?']);
        exit;
    }
    echo json_encode(['ok'=>true]); break;

  case 'get_log':
    $deaths=Database::query(
      "SELECT d.died_at, d.round, d.phase, p.display_name AS username
       FROM deaths d
       JOIN players p ON p.id=d.player_id
       WHERE d.game_id=? ORDER BY d.died_at",
      [$gameId]
    );
    $log=[];
    foreach($deaths as $d){
      $ph=$d['phase']==='night'?'🌕':'☀️';
      $name = htmlspecialchars($d['username'], ENT_QUOTES, 'UTF-8');
      $log[]="{$ph} R{$d['round']}: <strong>{$name}</strong>";
    }
    echo json_encode(['log'=>$log]);break;

  default:
    http_response_code(400);echo json_encode(['error'=>'Unbekannt']);
}
