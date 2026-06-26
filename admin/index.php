<?php
// Copyright (c) 2026 Andreas Vetter
require_once dirname(__DIR__) . '/core/bootstrap.php';
Auth::requireAdmin();

$game = currentGame();
if (!$game) {
    Database::execute("INSERT INTO games (status) VALUES ('lobby')");
    $game = Database::queryOne("SELECT * FROM games ORDER BY id DESC LIMIT 1");
}
$gameId      = $game['id'];
$gamePlayers = gamePlayers($gameId);
$playerCount = count($gamePlayers);

// Spieler, die noch nicht im Spiel sind
$inGameIds    = array_column($gamePlayers, 'player_id');
$placeholders = $inGameIds ? implode(',', array_fill(0, count($inGameIds), '?')) : '0';
$available    = $inGameIds
    ? Database::query("SELECT id,username FROM players WHERE id NOT IN ($placeholders) ORDER BY username", $inGameIds)
    : Database::query("SELECT id,username FROM players ORDER BY username");

// Abstimmungsergebnisse (nur während Tag)
$votes = [];
$playerNames = array_column($gamePlayers, 'display_name', 'player_id');
if ($game['status'] === 'running' && $game['phase'] === 'day') {
    $votes = Database::query(
        "SELECT target_id, COUNT(*) as cnt FROM votes WHERE game_id=? AND round=? GROUP BY target_id ORDER BY cnt DESC",
        [$gameId, $game['round']]
    );
}

// Rollen-Vorschau für die Lobby (zeigt NUR Anzahlen, nie wer was bekommt)
$fillRole     = Database::queryOne("SELECT id,name FROM roles WHERE active=1 AND fill=1 LIMIT 1");
$specialRoles = Database::query(
    "SELECT id, name, amount FROM roles WHERE active=1 AND fill=0 AND amount>0 ORDER BY sort_order"
);
$specialCount = array_sum(array_column($specialRoles, 'amount'));
$fillCount    = max(0, $playerCount - $specialCount);

// Gewinn-Bedingung: bei beendetem Spiel aus winner-Spalte, bei laufendem Spiel live prüfen
$killerWin  = false;
$citizenWin = false;
$dodoWin    = false;
if ($game['status'] === 'finished' && !empty($game['winner'])) {
    $dodoWin    = $game['winner'] === 'dodo';
    $citizenWin = $game['winner'] === 'citizen';
    $killerWin  = $game['winner'] === 'killer';
} elseif ($game['status'] === 'running') {
    $aliveKillers = (int)(Database::queryOne(
        "SELECT COUNT(*) AS cnt FROM game_players gp
         JOIN roles r ON r.id = gp.role_id
         WHERE gp.game_id = ? AND gp.is_alive = 1 AND r.is_killer = 1",
        [$gameId]
    )['cnt'] ?? 0);
    $aliveNonKillers = (int)(Database::queryOne(
        "SELECT COUNT(*) AS cnt FROM game_players gp
         LEFT JOIN roles r ON r.id = gp.role_id
         WHERE gp.game_id = ? AND gp.is_alive = 1 AND (r.is_killer = 0 OR r.id IS NULL)",
        [$gameId]
    )['cnt'] ?? 0);
    $totalKillers = (int)(Database::queryOne(
        "SELECT COUNT(*) AS cnt FROM game_players gp
         JOIN roles r ON r.id = gp.role_id
         WHERE gp.game_id = ? AND r.is_killer = 1",
        [$gameId]
    )['cnt'] ?? 0);

    // Dodo-Sieg: wurde der Dodo per Abstimmung gehenkt?
    $dodoHanged = Database::queryOne(
        "SELECT d.id FROM deaths d
         JOIN roles r ON r.id = d.role_id
         WHERE d.game_id = ? AND d.is_gehenkt = 1 AND r.name = 'Dodo'
         LIMIT 1",
        [$gameId]
    );
    if ($dodoHanged) {
        $dodoWin = true;
    } elseif ($totalKillers > 0 && $aliveKillers === 0) {
        // Bürger-Sieg: alle Killer tot
        $citizenWin = true;
    } elseif ($aliveKillers > 0 && $aliveKillers >= $aliveNonKillers) {
        // Mörder-Sieg: Lebende Spieler ≤ doppelte Anzahl lebender Mörder
        // (aliveKillers >= aliveNonKillers bedeutet: aliveTotal <= aliveKillers * 2)
        $killerWin = true;
    }
}

$_navMsgPending = 0;
try {
    $row = Database::queryOne("SELECT COUNT(*) AS cnt FROM messages WHERE reply IS NULL");
    $_navMsgPending = (int)($row['cnt'] ?? 0);
} catch (Throwable $e) {}

// Aktuelle Versammlungsanfrage
$pendingAssembly = null;
if ($game['status'] === 'running') {
    try {
        $pendingAssembly = Database::queryOne(
            "SELECT ar.scheduled_at, ar.notified, p.display_name AS caller
             FROM assembly_requests ar JOIN players p ON p.id=ar.player_id
             WHERE ar.game_id=? ORDER BY ar.scheduled_at DESC LIMIT 1",
            [$gameId]
        );
    } catch (Throwable $e) {}
}

$page = ['title' => 'Admin'];
require TEMPLATE_PATH . '/base.php';
?>

<div class="container page-wrap">

  <div class="page-header">
    <span class="page-header__icon">⚙️</span>
    <h1>Spielleitung</h1>
    <p class="page-header__sub">
      Spiel #<?= $gameId ?> &middot;
      <span class="tag tag--<?= $game['status']==='running'?'running':($game['status']==='lobby'?'lobby':'dead') ?>">
        <?= ['lobby'=>'Lobby','running'=>'Läuft','finished'=>'Beendet'][$game['status']] ?? $game['status'] ?>
      </span>
      <?php if ($game['status']==='running'): ?>
      &middot; <span class="tag tag--day">Runde <?= (int)$game['round'] ?></span>
      <?php endif; ?>
    </p>
  </div>

  <?php if ($dodoWin): ?>
  <div class="alert animate-in" style="font-size:1.05rem;font-family:var(--font-display);text-align:center;padding:1.2rem;background:rgba(168,85,247,.18);border-color:rgba(168,85,247,.4);color:#d8b4fe">
    🐦 <strong>Der Dodo hat gewonnen!</strong><br>
    <span style="font-size:.85rem;font-weight:400">Der Dodo wurde vom Dorf erhängt — sein Plan ist aufgegangen.</span>
  </div>
  <?php elseif ($killerWin): ?>
  <div class="alert alert--error animate-in" style="font-size:1.05rem;font-family:var(--font-display);text-align:center;padding:1.2rem">
    🔪 <strong>Die Mörder haben gewonnen!</strong><br>
    <span style="font-size:.85rem;font-weight:400">
      <?= $aliveKillers ?> Mörder, <?= $aliveNonKillers ?> Überlebende —
      das Dorf kann die Mörder nicht mehr überführen.
    </span>
  </div>
  <?php elseif ($citizenWin): ?>
  <div class="alert alert--success animate-in" style="font-size:1.05rem;font-family:var(--font-display);text-align:center;padding:1.2rem">
    🏘️ <strong>Die Bürger haben gewonnen!</strong><br>
    <span style="font-size:.85rem;font-weight:400">Alle Mörder sind tot — das Dorf ist gerettet.</span>
  </div>
  <?php endif; ?>

  <?php if ($pendingAssembly): ?>
  <?php $aTime = (int)$pendingAssembly['scheduled_at']; $aLabel = date('H:i', $aTime); ?>
  <div class="alert animate-in" style="display:flex;align-items:center;gap:.8rem;padding:.9rem 1rem;
       background:rgba(99,102,241,.14);border-color:rgba(99,102,241,.4);color:#c7d2fe;font-size:.9rem">
    <span style="font-size:1.5rem">🏛️</span>
    <div>
      <strong><?= e($pendingAssembly['caller']) ?></strong> hat eine Versammlung einberufen
      <?php if ($aTime > time()): ?>
        — Termin: <strong><?= $aLabel ?> Uhr</strong>
        <span class="text-xs" style="opacity:.7;margin-left:.4rem" id="admin-assembly-countdown"></span>
      <?php else: ?>
        — <strong>Versammlung läuft jetzt!</strong>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- ── Spielsteuerung ──────────────────────────────────────── -->
  <div class="card card--glow animate-in mb-2">
    <div class="section-title">Spielsteuerung</div>
    <div class="flex flex-wrap gap-sm">
      <?php if ($game['status']==='lobby'): ?>
        <button class="btn btn--primary" onclick="adminAction('start_game')">▶ Spiel starten</button>
        <button class="btn btn--ghost"   onclick="adminAction('reset_game')">🔄 Zurücksetzen</button>
      <?php elseif ($game['status']==='running'): ?>
        <button class="btn btn--ghost" onclick="adminAction('end_game')">🏁 Spiel beenden</button>
      <?php else: ?>
        <button class="btn btn--primary" onclick="adminAction('new_game')">➕ Neues Spiel</button>
      <?php endif; ?>
    </div>
    <div id="action-result" class="mt-2"></div>
  </div>

  <!-- ── Verwaltungsbereich ─────────────────────────────────── -->
  <div class="card animate-in mb-2" style="animation-delay:.04s">
    <div class="section-title">Verwaltung</div>
    <div class="admin-links">

      <a href="<?= APP_URL ?>/admin/roles.php" class="admin-link-card">
        <span class="admin-link-card__icon">🎭</span>
        <div class="admin-link-card__text">
          <div class="admin-link-card__title">Rollen</div>
          <div class="admin-link-card__sub">Rollen konfigurieren &amp; aktivieren</div>
        </div>
        <span class="admin-link-card__arrow">→</span>
      </a>

      <a href="<?= APP_URL ?>/admin/players.php" class="admin-link-card">
        <span class="admin-link-card__icon">👥</span>
        <div class="admin-link-card__text">
          <div class="admin-link-card__title">Spieler</div>
          <div class="admin-link-card__sub">Konten verwalten &amp; Passwörter setzen</div>
        </div>
        <span class="admin-link-card__arrow">→</span>
      </a>

      <a href="<?= APP_URL ?>/admin/testplayers.php" class="admin-link-card">
        <span class="admin-link-card__icon">🤖</span>
        <div class="admin-link-card__text">
          <div class="admin-link-card__title">Testspieler</div>
          <div class="admin-link-card__sub">Bis zu 20 Test-Konten anlegen &amp; löschen</div>
        </div>
        <span class="admin-link-card__arrow">→</span>
      </a>

      <a href="<?= APP_URL ?>/admin/messages.php" class="admin-link-card">
        <span class="admin-link-card__icon">✉️</span>
        <div class="admin-link-card__text">
          <div class="admin-link-card__title">
            Nachrichten
            <?php if ($_navMsgPending > 0): ?>
            <span id="admin-msg-badge"
                  style="background:var(--accent);color:var(--bg,#000);border-radius:99px;
                         font-size:.6rem;font-weight:700;padding:1px 6px;
                         margin-left:.3rem;vertical-align:middle">
              <?= $_navMsgPending ?>
            </span>
            <?php else: ?>
            <span id="admin-msg-badge"
                  style="display:none;background:var(--accent);color:var(--bg,#000);border-radius:99px;
                         font-size:.6rem;font-weight:700;padding:1px 6px;margin-left:.3rem;vertical-align:middle"></span>
            <?php endif; ?>
          </div>
          <div class="admin-link-card__sub">Spielerfragen beantworten &amp; FAQ pflegen</div>
        </div>
        <span class="admin-link-card__arrow">→</span>
      </a>

      <a href="<?= APP_URL ?>/admin/diagnostics.php" class="admin-link-card">
        <span class="admin-link-card__icon">🔍</span>
        <div class="admin-link-card__text">
          <div class="admin-link-card__title">Systemcheck</div>
          <div class="admin-link-card__sub">Datenbankverbindung &amp; Konfiguration prüfen</div>
        </div>
        <span class="admin-link-card__arrow">→</span>
      </a>

      <a href="<?= APP_URL ?>/admin/settings.php" class="admin-link-card">
        <span class="admin-link-card__icon">⚙️</span>
        <div class="admin-link-card__text">
          <div class="admin-link-card__title">Einstellungen</div>
          <div class="admin-link-card__sub">App-Name, Theme, Texte &amp; mehr</div>
        </div>
        <span class="admin-link-card__arrow">→</span>
      </a>

      <a href="<?= APP_URL ?>/admin/setup.php" class="admin-link-card">
        <span class="admin-link-card__icon">🔧</span>
        <div class="admin-link-card__text">
          <div class="admin-link-card__title">Setup</div>
          <div class="admin-link-card__sub">Datenbankschema neu aufbauen</div>
        </div>
        <span class="admin-link-card__arrow">→</span>
      </a>

    </div>
  </div>

  <div class="grid-2" style="gap:1.5rem;align-items:start">

    <!-- ── Linke Spalte: Spielerliste ──────────────────────────── -->
    <div>
      <div class="card animate-in" style="animation-delay:.06s">
        <div class="section-title" style="display:flex;align-items:center;justify-content:space-between;cursor:pointer" onclick="togglePlayers()">
          <span>Spieler (<?= $playerCount ?>)</span>
          <span id="players-chevron" style="font-size:.75rem;color:var(--text-dim);transition:transform .22s ease">▼</span>
        </div>

        <div id="player-list-body">
        <?php if (empty($gamePlayers)): ?>
          <p class="text-dim text-sm">Noch keine Spieler im Spiel.</p>
        <?php else: ?>
          <div style="display:flex;flex-direction:column;gap:.4rem">
            <?php foreach ($gamePlayers as $gp): ?>
            <div class="panel flex-between" style="padding:.55rem .9rem">
              <div class="flex gap-sm">
                <?php if (!$gp['is_alive'] && $gp['role_id']): ?>
                  <?= roleIconHtml(['icon_path'=>$gp['role_icon_path'],'name'=>$gp['role_name']], 'sm') ?>
                  <span style="font-family:var(--font-display);font-size:.88rem;color:var(--text-dim)">
                    <?= e($gp['display_name']) ?>
                  </span>
                  <span class="tag tag--dead text-xs">Tot</span>
                  <?php if ($gp['role_name']): ?>
                    <span class="text-dim text-xs">(<?= e($gp['role_name']) ?>)</span>
                  <?php endif; ?>
                <?php else: ?>
                  <span style="width:1.1rem;height:1.1rem;display:inline-block"></span>
                  <span style="font-family:var(--font-display);font-size:.88rem;color:var(--text-bright)">
                    <?= e($gp['display_name']) ?>
                  </span>
                <?php endif; ?>
              </div>
              <div class="flex gap-xs">
                <?php if ($gp['is_alive'] && $game['status']==='running'): ?>
                  <button class="btn btn--danger btn--sm" title="Als tot markieren"
                    onclick="killPlayer(<?= (int)$gp['player_id'] ?>,'<?= e($gp['username']) ?>')">☠</button>
                <?php endif; ?>
                <?php if ($game['status']==='lobby'): ?>
                  <button class="btn btn--ghost btn--sm" title="Entfernen"
                    onclick="removePlayer(<?= (int)$gp['player_id'] ?>)">✕</button>
                <?php endif; ?>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        </div><!-- /#player-list-body -->
      </div>

      <!-- Spieler hinzufügen (nur in Lobby) -->
      <?php if ($game['status']==='lobby' && !empty($available)): ?>
      <div class="card animate-in mt-2" style="animation-delay:.1s">
        <div class="section-title" style="display:flex;justify-content:space-between;align-items:center;gap:.5rem;flex-wrap:wrap">
          <span>Spieler hinzufügen</span>
          <button class="btn btn--primary btn--sm" onclick="addAllPlayers()">
            ➕ Alle hinzufügen (<?= count($available) ?>)
          </button>
        </div>
        <div class="flex flex-wrap gap-xs" style="margin-top:.5rem">
          <?php foreach ($available as $p): ?>
          <button class="btn btn--ghost btn--sm"
            onclick="addPlayer(<?= (int)$p['id'] ?>,'<?= e($p['username']) ?>')">
            + <?= e($p['username']) ?>
          </button>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <!-- ── Rechte Spalte ───────────────────────────────────────── -->
    <div>

      <!-- Rollen-Vorschau (nur Anzahlen, nicht wer was bekommt) -->
      <?php if ($game['status']==='lobby'): ?>
      <div class="card animate-in" style="animation-delay:.08s">
        <div class="section-title">Rollenverteilung beim Start</div>
        <p class="text-dim text-sm mb-2">
          Die Rollen werden beim Spielstart automatisch und zufällig vergeben.
          Der Admin sieht nicht, wer welche Rolle bekommt.
        </p>

        <?php if (empty($specialRoles)): ?>
          <div class="alert alert--warn">
            Keine aktiven Sonderrollen konfiguriert.
            <a href="<?= APP_URL ?>/admin/roles.php">Rollen verwalten →</a>
          </div>
        <?php else: ?>
          <div style="display:flex;flex-direction:column;gap:.3rem;margin-bottom:.85rem">
            <?php foreach ($specialRoles as $r): ?>
            <div class="flex gap-sm" style="font-size:.9rem">
              <span class="text-accent" style="font-family:var(--font-display);min-width:1.5rem"><?= (int)$r['amount'] ?>×</span>
              <span class="text-bright"><?= e($r['name']) ?></span>
            </div>
            <?php endforeach; ?>
            <?php if ($fillRole): ?>
            <div class="flex gap-sm" style="font-size:.9rem">
              <span class="text-dim" style="font-family:var(--font-display);min-width:1.5rem">
                <?= $playerCount > 0 ? max(0,$fillCount) : '?' ?>×
              </span>
              <span class="text-dim"><?= e($fillRole['name']) ?> (Auffüllung)</span>
            </div>
            <?php endif; ?>
          </div>

          <?php if ($specialCount > $playerCount && $playerCount > 0): ?>
            <div class="alert alert--error">
              ⚠️ Sonderrollen (<?= $specialCount ?>) übersteigen Spieleranzahl (<?= $playerCount ?>).<br>
              Bitte unter „Rollen verwalten" die Anzahlen anpassen oder mehr Spieler hinzufügen.
            </div>
          <?php elseif ($playerCount < MIN_PLAYERS): ?>
            <div class="alert alert--warn">
              Mindestens <?= MIN_PLAYERS ?> Spieler erforderlich (aktuell: <?= $playerCount ?>).
            </div>
          <?php else: ?>
            <div class="alert alert--success">
              ✓ <?= $playerCount ?> Spieler bereit &mdash; <?= $specialCount ?> Sonderrollen + <?= $fillCount ?> <?= $fillRole ? e($fillRole['name']) : 'ohne Rolle' ?>
            </div>
          <?php endif; ?>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <!-- Bürgerversammlung (Tag) -->
      <?php if ($game['status']==='running' && $game['phase']==='day'): ?>
      <div class="card animate-in" style="animation-delay:.1s">
        <div class="section-title">🏛️ Bürgerversammlung</div>
        <?php if (empty($votes)): ?>
          <p class="text-dim text-sm">Noch keine Anklagen eingereicht.</p>
        <?php else:
          $total   = array_sum(array_column($votes,'cnt'));
          $topVote = $votes[0];
          $accusedId   = $topVote['target_id'];
          $accusedName = $playerNames[$accusedId] ?? '?';
        ?>
          <!-- Angeklagter -->
          <div class="panel mb-2" style="border:1px solid var(--danger-text,#f87171);padding:1rem;text-align:center">
            <div class="text-dim text-xs mb-1" style="letter-spacing:.08em;text-transform:uppercase">Angeklagter</div>
            <div style="font-family:var(--font-display);font-size:1.35rem;color:var(--danger-text,#f87171)">
              <?= e($accusedName) ?>
            </div>
            <div class="text-dim text-xs mt-1"><?= $topVote['cnt'] ?> von <?= $total ?> Stimmen</div>
          </div>

          <!-- Stimmenverteilung -->
          <?php if (count($votes) > 1): ?>
          <div style="margin-bottom:.85rem">
            <?php foreach ($votes as $v): ?>
            <div style="margin-bottom:.3rem">
              <div class="flex-between" style="font-size:.82rem">
                <span style="font-family:var(--font-display)"><?= e($playerNames[$v['target_id']]??'?') ?></span>
                <span class="text-dim"><?= $v['cnt'] ?>/<?= $total ?></span>
              </div>
              <div style="height:3px;background:var(--border);border-radius:2px;margin-top:.2rem;overflow:hidden">
                <div style="height:100%;width:<?= round($v['cnt']/$total*100) ?>%;background:var(--danger-text,#f87171);border-radius:2px"></div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>

          <!-- Urteil -->
          <div class="flex gap-sm" style="margin-top:.5rem">
            <button class="btn btn--danger" style="flex:1"
                    onclick="hangAccused(<?= $accusedId ?>, '<?= e(addslashes($accusedName)) ?>')">
              ⚖️ Hängen
            </button>
            <button class="btn btn--ghost" style="flex:1"
                    onclick="freeAccused('<?= e(addslashes($accusedName)) ?>')">
              ✓ Frei lassen
            </button>
          </div>
        <?php endif; ?>
        <div id="action-result-assembly" class="mt-2"></div>
      </div>
      <?php endif; ?>

      <!-- Als tot markieren (Schnellzugriff) -->
      <?php if ($game['status']==='running'): ?>
      <div class="card animate-in mt-2" style="animation-delay:.14s">
        <div class="section-title">Spieler als tot melden</div>
        <div class="flex gap-sm kill-controls">
          <select id="kill-pid" class="form-input" style="flex:1">
            <option value="">Spieler wählen…</option>
            <?php foreach (array_filter($gamePlayers, fn($p)=>$p['is_alive']) as $gp): ?>
            <option value="<?= (int)$gp['player_id'] ?>"><?= e($gp['username']) ?></option>
            <?php endforeach; ?>
          </select>
          <select id="kill-cause" class="form-input" style="width:auto">
            <option value="killer">🔪 Mordwaffe</option>
            <option value="vote">⚖️ Erhängt</option>
            <option value="other">💀 Sonstiges</option>
          </select>
          <button class="btn btn--danger" onclick="manualKill()">☠</button>
        </div>
      </div>
      <?php endif; ?>

    </div>
  </div>
</div>

<style>
.admin-links {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: .6rem;
}
@media (max-width: 640px) {
  .admin-links { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 380px) {
  .admin-links { grid-template-columns: 1fr; }
}
.admin-link-card {
  display: flex; align-items: center; gap: .75rem;
  padding: .85rem .9rem;
  background: var(--panel-bg);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  text-decoration: none;
  color: inherit;
  transition: border-color .18s, background .18s, transform .18s;
  min-width: 0;
}
.admin-link-card:hover {
  border-color: var(--accent-border);
  background: var(--card-bg);
  transform: translateY(-1px);
}
.admin-link-card__icon {
  font-size: 1.4rem;
  flex-shrink: 0;
  line-height: 1;
}
.admin-link-card__text { flex: 1; min-width: 0; }
.admin-link-card__title {
  font-family: var(--font-display);
  font-size: .88rem;
  color: var(--text-bright);
  font-weight: 600;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.admin-link-card__sub {
  font-size: .72rem;
  color: var(--text-dim);
  margin-top: .1rem;
  line-height: 1.35;
}
.admin-link-card__arrow {
  flex-shrink: 0;
  color: var(--text-dim);
  font-size: .8rem;
  margin-left: auto;
  transition: transform .18s;
}
.admin-link-card:hover .admin-link-card__arrow { transform: translateX(3px); }
</style>

<?php
$page['inline_js'] = sprintf('const GAME_ID=%s,API_BASE=%s,ASSEMBLY_TS=%s;',
    json_encode($gameId), json_encode(API_URL),
    json_encode($pendingAssembly ? (int)$pendingAssembly['scheduled_at'] : 0)
);
$page['inline_js'] .= <<<'JS'
async function adminAction(action){
  const r=await apiFetch(API_BASE+'/admin.php',{action,game_id:GAME_ID});
  if(r.error==='session_expired')return;
  const el=document.getElementById('action-result');
  if(!el)return;
  el.innerHTML=r.ok
    ?`<div class="alert alert--success">${r.message||'OK'}</div>`
    :`<div class="alert alert--error">${r.error||'Fehler'}</div>`;
  if(r.ok) setTimeout(()=>location.reload(), r.game_ended ? 2500 : 1200);
}
function _handleWinResponse(r, resultElId) {
  const el = document.getElementById(resultElId);
  if (!el) return;
  if (!r.ok) { el.innerHTML=`<div class="alert alert--error">${r.error||'Fehler'}</div>`; return; }
  el.innerHTML = `<div class="alert alert--success">${r.message||'OK'}</div>`;
  setTimeout(() => location.reload(), r.game_ended ? 2500 : 1400);
}
async function addPlayer(pid,name){
  const r=await apiFetch(API_BASE+'/admin.php',{action:'add_player',game_id:GAME_ID,player_id:pid});
  if(r.error==='session_expired')return;
  if(r.ok){showToast(name+' hinzugefügt','success');setTimeout(()=>location.reload(),600);}
  else showToast(r.error||'Fehler','error');
}
async function addAllPlayers(){
  const r=await apiFetch(API_BASE+'/admin.php',{action:'add_all_players',game_id:GAME_ID});
  if(r.error==='session_expired')return;
  if(r.ok){showToast('Alle Spieler hinzugefügt','success');setTimeout(()=>location.reload(),600);}
  else showToast(r.error||'Fehler','error');
}
async function removePlayer(pid){
  const r=await apiFetch(API_BASE+'/admin.php',{action:'remove_player',game_id:GAME_ID,player_id:pid});
  if(r.error==='session_expired')return;
  if(r.ok){showToast('Entfernt','success');setTimeout(()=>location.reload(),600);}
  else showToast(r.error||'Fehler','error');
}
async function hangAccused(pid, name) {
  if (!confirm('⚖️ ' + name + ' wirklich hängen?')) return;
  const r = await apiFetch(API_BASE+'/admin.php', {action:'execute_vote', game_id:GAME_ID, player_id:pid});
  if (r.error === 'session_expired') return;
  _handleWinResponse(r, 'action-result-assembly');
}
async function freeAccused(name) {
  if (!confirm('✓ ' + name + ' freisprechen? Alle Stimmen werden gelöscht.')) return;
  const r = await apiFetch(API_BASE+'/admin.php', {action:'free_accused', game_id:GAME_ID});
  if (r.error === 'session_expired') return;
  const el = document.getElementById('action-result-assembly');
  if (el) el.innerHTML = r.ok
    ? `<div class="alert alert--success">${r.message||'OK'}</div>`
    : `<div class="alert alert--error">${r.error||'Fehler'}</div>`;
  if (r.ok) setTimeout(() => location.reload(), 1200);
}
async function killPlayer(pid,name){
  if(!confirm(name+' als tot markieren?'))return;
  const cause=document.getElementById('kill-cause')?.value||'other';
  const r=await apiFetch(API_BASE+'/admin.php',{action:'kill_player',game_id:GAME_ID,player_id:pid,cause});
  if(r.error==='session_expired')return;
  if(r.ok){showToast(r.message||(name+' gestorben'),'success');setTimeout(()=>location.reload(),r.game_ended?2500:700);}
  else showToast(r.error||'Fehler','error');
}
async function manualKill(){
  const pid=document.getElementById('kill-pid').value;
  const cause=document.getElementById('kill-cause').value;
  if(!pid){showToast('Spieler wählen!','error');return;}
  const r=await apiFetch(API_BASE+'/admin.php',{action:'kill_player',game_id:GAME_ID,player_id:parseInt(pid),cause});
  if(r.error==='session_expired')return;
  if(r.ok){showToast(r.message||'Als tot markiert','success');setTimeout(()=>location.reload(),r.game_ended?2500:700);}
  else showToast(r.error||'Fehler','error');
}
JS;

$page['inline_js'] .= <<<'JS'

// ── Versammlungs-Countdown im Admin-Banner ───────────────────
(function(){
  const el = document.getElementById('admin-assembly-countdown');
  if (!el || !ASSEMBLY_TS) return;
  function tick() {
    const diff = ASSEMBLY_TS - Math.floor(Date.now()/1000);
    if (diff <= 0) { el.textContent = ''; return; }
    const m = Math.floor(diff/60), s = diff%60;
    el.textContent = '(noch ' + String(m).padStart(2,'0') + ':' + String(s).padStart(2,'0') + ')';
  }
  tick();
  setInterval(tick, 1000);
})();

// ── Spielerliste ein-/ausklappen ─────────────────────────────
function togglePlayers() {
  const body    = document.getElementById('player-list-body');
  const chevron = document.getElementById('players-chevron');
  const isOpen  = body.style.display !== 'none';
  body.style.display    = isOpen ? 'none' : '';
  chevron.style.transform = isOpen ? 'rotate(-90deg)' : '';
  localStorage.setItem('ww_admin_players_open', String(!isOpen));
}

// Zustand beim Laden wiederherstellen
(function() {
  if (localStorage.getItem('ww_admin_players_open') === 'false') {
    const body    = document.getElementById('player-list-body');
    const chevron = document.getElementById('players-chevron');
    if (body)    body.style.display     = 'none';
    if (chevron) chevron.style.transform = 'rotate(-90deg)';
  }
})();
JS;

require TEMPLATE_PATH . '/base_end.php';
?>
