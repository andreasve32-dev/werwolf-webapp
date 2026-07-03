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

// Session-Lock freigeben: kein Endpunkt hier schreibt $_SESSION, aber ohne
// write_close serialisiert PHP alle parallelen Poll-Requests desselben Nutzers.
session_write_close();

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
    // Rollennamen und -Icons werden nur in vier Fällen herausgegeben:
    // eigene Karte, toter Spieler (Rolle aufgedeckt), gleiche sichtbare Rolle
    // oder gegenseitige Killer-Sichtbarkeit (killer_sichtbar, z.B. Dodo↔Mörder).
    $players=Database::query(
      "SELECT gp.player_id, gp.is_alive, gp.role_id, p.display_name,
              r.name AS role_name, r.icon_path AS role_icon_path, r.sichtbar AS role_sichtbar,
              r.is_killer AS role_is_killer, r.killer_sichtbar AS role_killer_sichtbar
       FROM game_players gp
       JOIN players p ON p.id=gp.player_id
       LEFT JOIN roles r ON r.id=gp.role_id
       WHERE gp.game_id=?
       ORDER BY gp.is_alive DESC, p.display_name",
      [$gameId]
    );
    // Eigene Rolle + Sichtbarkeits-Flags laden (für den Vergleich)
    $me=Database::queryOne(
      "SELECT gp.role_id, r.sichtbar AS role_sichtbar,
              r.is_killer AS role_is_killer, r.killer_sichtbar AS role_killer_sichtbar
       FROM game_players gp LEFT JOIN roles r ON r.id=gp.role_id
       WHERE gp.game_id=? AND gp.player_id=?",
      [$gameId,$playerId]
    );
    $myRoleId         = ($me && $me['role_id'] !== null) ? (int)$me['role_id'] : null;
    $myVisible        = $me ? (bool)((int)($me['role_sichtbar'] ?? 0)) : false;
    $myIsKiller       = $me ? (bool)((int)($me['role_is_killer'] ?? 0)) : false;
    $myKillerSichtbar = $me ? (bool)((int)($me['role_killer_sichtbar'] ?? 0)) : false;

    foreach($players as &$p){
      $pRoleId         = $p['role_id'] !== null ? (int)$p['role_id'] : null;
      $pVisible        = (bool)((int)($p['role_sichtbar'] ?? 0));
      $pIsKiller       = (bool)((int)($p['role_is_killer'] ?? 0));
      $pKillerSichtbar = (bool)((int)($p['role_killer_sichtbar'] ?? 0));
      $isMe            = (int)$p['player_id'] === (int)$playerId;
      $isDead          = !((bool)$p['is_alive']);

      // Echte Rolle sichtbar, wenn:
      //  a) Mein eigener Eintrag
      //  b) Spieler ist tot (Rolle wird aufgedeckt)
      //  c) Gleiche Rolle + beide sichtbar=1 (z.B. Mörder sehen Mörder)
      //  d) Killer-Kreuz-Sichtbarkeit: meine Rolle hat killer_sichtbar und
      //     der andere ist Killer — oder ich bin Killer und seine Rolle
      //     hat killer_sichtbar (Dodo sieht Mörder, Mörder sehen Dodo)
      $sameVisibleRole = $myRoleId !== null
        && $pRoleId !== null
        && $pRoleId === $myRoleId
        && $myVisible
        && $pVisible;
      $crossKillerVisible = ($myKillerSichtbar && $pIsKiller)
                         || ($myIsKiller && $pKillerSichtbar);

      if(!$isMe && !$isDead && !$sameVisibleRole && !$crossKillerVisible){
        // Rolle komplett ausblenden — kein Name, kein Icon
        $p['role_name']      = null;
        $p['role_icon_path'] = null;
        $p['role_sichtbar']  = 0;
      }
      // Interne Flags nie an den Client geben — role_is_killer würde
      // sonst die Mörder an ALLE verraten
      unset($p['role_is_killer'], $p['role_killer_sichtbar']);
    }
    unset($p);

    $g = Database::queryOne("SELECT status, phase, round, winner FROM games WHERE id=?", [$gameId]);
    $myGPRow = Database::queryOne("SELECT * FROM game_players WHERE game_id=? AND player_id=?", [$gameId, $playerId]);
    require_once TEMPLATE_PATH . '/game_blocks.php';
    echo json_encode([
      'players'        => $players,
      'game'           => $g ? ['status'=>$g['status'],'phase'=>$g['phase'],'round'=>(int)$g['round'],'winner'=>$g['winner'] ?? null] : null,
      'me'             => ['in_game'=>(bool)$myGPRow, 'is_alive'=>$myGPRow ? (bool)$myGPRow['is_alive'] : false],
      'my_status_html' => render_my_status_actions($g, $myGPRow),
    ]);break;

  case 'vote':
    $tid=(int)($input['target_id']??0);
    $g=Database::queryOne("SELECT * FROM games WHERE id=? AND status='running'",[$gameId]);
    if(!$g){http_response_code(400);echo json_encode(['error'=>'Spiel läuft nicht']);exit;}
    $me=Database::queryOne("SELECT * FROM game_players WHERE game_id=? AND player_id=? AND is_alive=1",[$gameId,$playerId]);
    if(!$me){http_response_code(400);echo json_encode(['error'=>'Nicht berechtigt']);exit;}
    // Anklagen sind nur während einer LAUFENDEN Bürgerversammlung erlaubt
    // (einberufen, Termin erreicht, noch nicht beendet)
    $assemblyRunning=Database::queryOne(
      "SELECT id FROM assembly_requests WHERE game_id=? AND ended_at IS NULL AND scheduled_at<=?",
      [$gameId, time()]
    );
    if(!$assemblyRunning){http_response_code(400);echo json_encode(['error'=>'Anklagen sind nur während einer laufenden Versammlung möglich.']);exit;}
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
    // Todesort, -zeit und Rolle nachtragen. Erlaubt für: Admin oder der Betroffene selbst.
    $deathId = (int)($input['death_id'] ?? 0);
    $ort     = trim($input['ort']  ?? '');
    $zeit    = trim($input['zeit'] ?? '');
    if (mb_strlen($ort) > 200) { http_response_code(400); echo json_encode(['error'=>'Ort zu lang']); exit; }
    if (mb_strlen($zeit) > 20)  { http_response_code(400); echo json_encode(['error'=>'Zeit zu lang']);  exit; }
    $isAdmin = (bool)Auth::player()['is_admin'];
    $death = Database::queryOne("SELECT * FROM deaths WHERE id=?", [$deathId]);
    if (!$death) { http_response_code(404); echo json_encode(['error'=>'Nicht gefunden']); exit; }
    if (!$isAdmin && (int)$death['player_id'] !== (int)$playerId) {
        http_response_code(403); echo json_encode(['error'=>'Keine Berechtigung']); exit;
    }
    try {
        Database::execute(
            "UPDATE deaths SET ort=?, zeit=?, rolle_aufgedeckt=1 WHERE id=?",
            [$ort ?: null, $zeit ?: null, $deathId]
        );
    } catch (\Throwable $ex) {
        http_response_code(500);
        echo json_encode(['error' => 'DB-Fehler: ' . $ex->getMessage() . ' — Migration db/migration_zeit.sql ausführen?']);
        exit;
    }
    echo json_encode(['ok'=>true]); break;

  case 'call_assembly':
    $g = Database::queryOne("SELECT * FROM games WHERE id=? AND status='running'", [$gameId]);
    if (!$g) { http_response_code(400); echo json_encode(['error'=>'Spiel läuft nicht']); exit; }
    $me = Database::queryOne("SELECT * FROM game_players WHERE game_id=? AND player_id=? AND is_alive=1", [$gameId, $playerId]);
    if (!$me) { http_response_code(400); echo json_encode(['error'=>'Du bist nicht im Spiel oder bereits tot']); exit; }
    $caller = Database::queryOne("SELECT display_name FROM players WHERE id=?", [$playerId]);
    $callerName = $caller['display_name'] ?? 'Ein Spieler';
    require_once dirname(__DIR__) . '/core/WebPush.php';

    // Bereits eine aktive Anfrage (Antrag, Countdown oder laufend)?
    $existing = Database::queryOne(
        "SELECT * FROM assembly_requests WHERE game_id=? AND ended_at IS NULL", [$gameId]
    );

    if (!$existing) {
        // Phase 1: erster Einberufer stellt den Antrag — Versammlung kommt
        // erst zustande, wenn ein ZWEITER Spieler sie ebenfalls einberuft
        Database::execute(
            "INSERT INTO assembly_requests (game_id, player_id, scheduled_at) VALUES (?,?,NULL)",
            [$gameId, $playerId]
        );
        WebPush::sendToGame($gameId, true, '🏛️ Versammlung beantragt',
            $callerName . ' möchte eine Bürgerversammlung einberufen — ein zweiter Spieler muss unterstützen!');
        echo json_encode(['ok'=>true,'pending'=>true,'caller'=>$callerName,'caller_id'=>(int)$playerId,
                          'message'=>'Antrag gestellt — ein zweiter Spieler muss die Versammlung unterstützen.']);
        break;
    }

    if ($existing['scheduled_at'] !== null) {
        echo json_encode(['ok'=>false,'error'=>'Es ist bereits eine Versammlung aktiv','scheduled_at'=>(int)$existing['scheduled_at']]);
        exit;
    }

    if ((int)$existing['player_id'] === (int)$playerId) {
        echo json_encode(['ok'=>false,'error'=>'Du hast die Versammlung bereits beantragt — ein anderer Spieler muss sie unterstützen.']);
        exit;
    }

    // Phase 2: zweiter Einberufer unterstützt → Termin nächste volle Stunde
    $nextHour = (int)((floor(time() / 3600) + 1) * 3600);
    $timeStr  = date('H:i', $nextHour);
    Database::execute(
        "UPDATE assembly_requests SET supporter_id=?, scheduled_at=? WHERE id=?",
        [$playerId, $nextHour, $existing['id']]
    );
    $firstCaller = Database::queryOne("SELECT display_name FROM players WHERE id=?", [(int)$existing['player_id']]);
    WebPush::sendToGame($gameId, true, '🏛️ Versammlung einberufen!',
        ($firstCaller['display_name'] ?? 'Ein Spieler') . ' und ' . $callerName .
        ' rufen zur Bürgerversammlung — Treffen um ' . $timeStr . ' Uhr.');
    echo json_encode(['ok'=>true,'scheduled_at'=>$nextHour,'caller'=>$firstCaller['display_name'] ?? 'Ein Spieler',
                      'caller_id'=>(int)$existing['player_id'],'supporter_id'=>(int)$playerId,
                      'message'=>'Versammlung um '.$timeStr.' Uhr einberufen.']);
    break;

  case 'get_assembly':
    $g = Database::queryOne("SELECT status FROM games WHERE id=?", [$gameId]);
    if (!$g || $g['status'] !== 'running') { echo json_encode(['assembly'=>null]); break; }
    $now = time();
    // Fällige Erinnerung versenden (lazy check)
    $due = Database::queryOne(
        "SELECT ar.id FROM assembly_requests ar
         WHERE ar.game_id=? AND ar.notified=0 AND ar.scheduled_at<=?
         ORDER BY ar.scheduled_at LIMIT 1",
        [$gameId, $now]
    );
    if ($due) {
        require_once dirname(__DIR__) . '/core/WebPush.php';
        WebPush::sendToGame($gameId, true, '🏛️ Jetzt: Bürgerversammlung!',
            'Die Versammlung beginnt — kommt zusammen und beratet!');
        Database::execute("UPDATE assembly_requests SET notified=1 WHERE id=?", [$due['id']]);
    }
    $assembly = Database::queryOne(
        "SELECT ar.scheduled_at, ar.notified, ar.player_id AS caller_id, ar.supporter_id,
                p.display_name AS caller
         FROM assembly_requests ar JOIN players p ON p.id=ar.player_id
         WHERE ar.game_id=? AND ar.ended_at IS NULL
         ORDER BY ar.id DESC LIMIT 1",
        [$gameId]
    );
    echo json_encode(['assembly'=> $assembly ? [
        'scheduled_at' => $assembly['scheduled_at'] !== null ? (int)$assembly['scheduled_at'] : null,
        'pending'      => $assembly['scheduled_at'] === null,
        'notified'     => (bool)$assembly['notified'],
        'caller'       => $assembly['caller'],
        'caller_id'    => (int)$assembly['caller_id'],
        'supporter_id' => $assembly['supporter_id'] !== null ? (int)$assembly['supporter_id'] : null,
    ] : null]);
    break;

  case 'end_assembly':
    $assembly = Database::queryOne(
        "SELECT * FROM assembly_requests WHERE game_id=? AND ended_at IS NULL ORDER BY scheduled_at DESC LIMIT 1",
        [$gameId]
    );
    if (!$assembly) { http_response_code(400); echo json_encode(['error'=>'Keine aktive Versammlung']); exit; }
    $isAdmin     = (bool)Auth::player()['is_admin'];
    $isCaller    = (int)$assembly['player_id'] === (int)$playerId;
    $isSupporter = $assembly['supporter_id'] !== null && (int)$assembly['supporter_id'] === (int)$playerId;
    if (!$isAdmin && !$isCaller && !$isSupporter) {
        http_response_code(403);
        echo json_encode(['error'=>'Nur die beiden Einberufer (oder die Spielleitung) können die Versammlung beenden.']);
        exit;
    }
    Database::execute("UPDATE assembly_requests SET ended_at=NOW() WHERE id=?", [$assembly['id']]);
    echo json_encode(['ok'=>true]);
    break;

  case 'get_faq':
    require_once TEMPLATE_PATH . '/faq_blocks.php';
    $faqEntries = Database::query(
        "SELECT message, reply FROM messages
         WHERE published = 1 AND reply IS NOT NULL
         ORDER BY replied_at DESC"
    );
    $faqRoles = activeRoles();
    blocksResponse([
        'faq-content'          => render_faq_list($faqEntries),
        'roles-rules-content'  => render_roles_rules_list($faqRoles),
    ], $input['blocks_hash'] ?? null);

  case 'get_roles':
    require_once TEMPLATE_PATH . '/roles_blocks.php';
    $galleryRoles = activeRoles();
    blocksResponse(
        ['roles-gallery-block' => render_roles_gallery($galleryRoles)],
        $input['blocks_hash'] ?? null,
        ['roles' => roles_data_json($galleryRoles)]
    );

  case 'get_stats':
    // Billige Versions-Probe VOR der teuren Statistik-Berechnung (~11 Aggregat-
    // Queries): ändert sich an deaths/votes/games nichts, genügt eine Antwort
    // mit unveränderter Version und der Client behält seine Anzeige.
    $statsVer = Database::queryOne(
        "SELECT CONCAT(
            (SELECT COUNT(*) FROM deaths), '-', (SELECT COALESCE(MAX(id),0) FROM deaths), '-',
            (SELECT COUNT(*) FROM votes),  '-', (SELECT COALESCE(MAX(id),0) FROM votes),  '-',
            (SELECT COUNT(*) FROM games),  '-', (SELECT COUNT(*) FROM games WHERE status='finished')
         ) AS v"
    )['v'] ?? '';
    if (($input['version'] ?? null) === $statsVer) {
        echo json_encode(['version' => $statsVer]);
        break;
    }
    require_once TEMPLATE_PATH . '/stats_blocks.php';
    $sState = stats_compute_state();
    echo json_encode([
        'version' => $statsVer,
        'blocks'  => ['stats-content' => render_stats_content($sState)],
        'stats'   => [
            'cause'   => $sState['causePieData'],
            'phase'   => $sState['phasePieData'],
            'rounds'  => $sState['roundBarData'],
            'accused' => $sState['accuseBarData'],
        ],
        'players' => $sState['playerDetails'],
    ]);
    break;

  case 'get_deaths':
    require_once TEMPLATE_PATH . '/deaths_blocks.php';
    $dGame = currentGame() ?: Database::queryOne("SELECT * FROM games ORDER BY id DESC LIMIT 1");
    $dState = deaths_compute_state($dGame['id'] ?? null, Auth::player());
    blocksResponse(
        ['deaths-content' => render_deaths_content($dState)],
        $input['blocks_hash'] ?? null
    );

  case 'save_setting':
    $key = (string)($input['key'] ?? '');
    $val = (string)($input['value'] ?? '');
    $allowedKeys = [
        'ww_atmosphere', 'ww_poll_interval',
        'ww_fx_particles', 'ww_fx_ripple', 'ww_fx_phase', 'ww_fx_skulls',
        'ww_fx_anims', 'ww_fx_fog', 'ww_fx_rolecard', 'ww_fx_rolename',
    ];
    if (!in_array($key, $allowedKeys, true)) jsonError('Ungültiger Schlüssel');
    if (mb_strlen($val) > 20) jsonError('Wert zu lang');
    // JSON_SET statt Read-Modify-Write: zwei schnell aufeinanderfolgende Toggles
    // (parallele Requests) überschreiben sich sonst gegenseitig.
    try {
        Database::execute(
            "UPDATE players SET settings = JSON_SET(COALESCE(settings, '{}'), CONCAT('$.', ?), ?) WHERE id=?",
            [$key, $val, $playerId]
        );
    } catch (\Throwable $e) {
        // Spalte fehlt vermutlich noch (ältere Installation) — nachlegen und einmal wiederholen
        ensurePlayerSettingsColumn();
        try {
            Database::execute(
                "UPDATE players SET settings = JSON_SET(COALESCE(settings, '{}'), CONCAT('$.', ?), ?) WHERE id=?",
                [$key, $val, $playerId]
            );
        } catch (\Throwable $e2) {
            error_log('save_setting fehlgeschlagen: ' . $e2->getMessage());
            jsonError('Einstellung konnte nicht gespeichert werden (DB-Update nötig?)', 500);
        }
    }
    echo json_encode(['ok'=>true]);break;

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
