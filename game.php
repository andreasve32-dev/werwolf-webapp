<?php
// Copyright (c) 2026 Andreas Vetter
// Spielfeld (Spieler-Ansicht): Rolle anzeigen, Tod melden, abstimmen, Nachrichten.
require_once __DIR__ . '/core/bootstrap.php';
require_once TEMPLATE_PATH . '/game_blocks.php';
Auth::requireLogin();

$player  = Auth::player();
$game    = currentGame();
// Kein Spiel vorhanden → automatisch eine neue Lobby anlegen
if (!$game) {
    Database::execute("INSERT INTO games (status) VALUES ('lobby')");
    $game = currentGame();
}
$gameId  = $game['id'] ?? null;
$myGP    = $gameId ? gamePlayer($gameId, $player['id']) : null;

// Rolle des Spielers — null wenn noch keine vergeben oder Spiel in Lobby
$myRole  = $myGP && $myGP['role_id'] ? role((int)$myGP['role_id']) : null;

// Sprüche aus DB laden (Tag + Nacht, zufällige Reihenfolge, max. 20)
try {
    $daySlogans   = array_column(Database::query("SELECT text FROM slogans WHERE phase='day'   AND active=1 ORDER BY RAND() LIMIT 20"), 'text');
    $nightSlogans = array_column(Database::query("SELECT text FROM slogans WHERE phase='night' AND active=1 ORDER BY RAND() LIMIT 20"), 'text');
} catch (\Throwable $e) {
    $daySlogans   = [];
    $nightSlogans = [];
}

// Aktuelle Versammlungsanfrage laden
$currentAssembly = null;
if ($gameId && ($game['status'] ?? '') === 'running') {
    try {
        $row = Database::queryOne(
            "SELECT ar.scheduled_at, ar.notified, ar.player_id AS caller_id, p.display_name AS caller
             FROM assembly_requests ar JOIN players p ON p.id=ar.player_id
             WHERE ar.game_id=? AND ar.ended_at IS NULL ORDER BY ar.scheduled_at DESC LIMIT 1",
            [$gameId]
        );
        if ($row) {
            $currentAssembly = ['scheduled_at'=>(int)$row['scheduled_at'],
                                'notified'=>(bool)(int)$row['notified'],
                                'caller'=>$row['caller'],
                                'caller_id'=>(int)$row['caller_id']];
        }
    } catch (\Throwable $e) { /* Tabelle noch nicht migriert */ }
}

$page = [
    'title'    => 'Spielfeld',
    'inline_js' => sprintf(
        'const GAME_ID=%s,PLAYER_ID=%s,API_BASE=%s,DAY_SLOGANS=%s,NIGHT_SLOGANS=%s,MY_COOLDOWN_MINS=%s,MY_COOLDOWN_STARTED=%s,ASSEMBLY_DATA=%s,MY_IS_ADMIN=%s;'
        . 'let MY_ALIVE=%s,GAME_STATUS=%s,GAME_PHASE=%s;',
        json_encode($gameId),
        json_encode($player['id']),
        json_encode(API_URL),
        json_encode($daySlogans),
        json_encode($nightSlogans),
        json_encode($myRole ? (int)$myRole['cooldown'] : 0),
        json_encode($myGP['cooldown_started_at'] ?? null),
        json_encode($currentAssembly),
        json_encode((bool)$player['is_admin']),
        json_encode($myGP ? (bool)$myGP['is_alive'] : false),
        json_encode($game['status'] ?? null),
        json_encode($game['phase']  ?? null)
    ),
];
require TEMPLATE_PATH . '/base.php';
?>

<div class="container page-wrap">

  <!-- Beta-Hinweis -->
  <?php if (BETA_MODE): ?>
  <div class="alert alert--info" style="margin-bottom:.75rem;font-size:.85rem;text-align:center">
    🧪 <strong>Beta</strong> — Diese Version ist noch in der Entwicklung. Fehler können auftreten.
  </div>
  <?php endif; ?>

  <!-- Phase Banner -->
  <?php
  $phase = $game['phase'] ?? 'lobby';
  $status = $game['status'] ?? null;
  ?>
  <div class="phase-banner phase-banner--<?= $status === 'running' ? $phase : 'lobby' ?>" id="phase-banner">
    <span id="phase-banner-text">
    <?php if ($status === 'lobby'): ?>
      🏰 Warte auf Spielstart
    <?php elseif ($phase === 'day'): ?>
      ☀️ Tag — Das Dorf berät
    <?php else: ?>
      🌕 Nacht — Die Wölfe erwachen
    <?php endif; ?>
    </span>
  </div>

  <div class="grid-2 game-layout" style="gap:1.5rem;align-items:start">
    <div>
      <div class="card card--glow animate-in">
        <div class="section-title">Mein Status</div>
        <div class="flex gap-md mb-2">
          <?php if ($myRole): ?>
            <div class="role-fx" id="role-fx-wrap">
              <?= roleIconHtml($myRole, 'xl') ?>
            </div>
          <?php else: ?>
            <span style="font-size:2.4rem">👤</span>
          <?php endif; ?>
          <div>
            <div class="bold text-bright"><?= e($player['display_name']) ?></div>
            <?php if ($myGP && $myRole): ?>
              <div class="role-badge role-badge--glow mt-1">
                <?= e($myRole['name']) ?>
              </div>
              <div class="mt-1">
                <?php if ($myGP['is_alive']): ?>
                  <span class="tag tag--alive">✓ Am Leben</span>
                <?php else: ?>
                  <span class="tag tag--dead">☠ Gestorben</span>
                <?php endif; ?>
              </div>
            <?php elseif ($myGP): ?>
              <span class="tag tag--lobby mt-1">Noch keine Rolle zugewiesen</span>
            <?php else: ?>
              <span class="tag tag--lobby mt-1">Nicht im Spiel</span>
            <?php endif; ?>
          </div>
        </div>

        <?php if ($myGP): ?>
        <button class="btn btn--ghost btn--full btn--sm" style="margin-bottom:.6rem" onclick="openRoleCard()">
          🃏 Meine Karte anzeigen
        </button>
        <?php endif; ?>

        <div class="flex gap-xs" style="margin-bottom:.6rem">
          <button class="btn btn--ghost btn--sm" style="flex:1" onclick="openAskModal()">
            ✉️ Frage stellen
          </button>
          <button class="btn btn--ghost btn--sm" style="flex:1;position:relative" id="inbox-btn" onclick="openInboxModal()">
            📬 Posteingang
            <span id="msg-badge"
                  style="display:none;position:absolute;top:-6px;right:-6px;
                         background:var(--accent);color:var(--bg,#000);
                         border-radius:99px;font-size:.6rem;font-weight:700;
                         padding:1px 5px;min-width:16px;text-align:center;line-height:1.5">
            </span>
          </button>
        </div>

        <?php if ($player['is_admin']): ?>
        <a href="<?= APP_URL ?>/admin/messages.php" id="admin-pending-hint"
           style="display:none;align-items:center;gap:.6rem;margin-bottom:.5rem;
                  padding:.55rem .8rem;border-radius:8px;
                  background:var(--alert-info-bg,rgba(96,165,250,.12));
                  border:1px solid var(--alert-info-border,rgba(96,165,250,.35));
                  color:var(--alert-info-text,#93c5fd);
                  font-size:.82rem;text-decoration:none;
                  transition:border-color .18s">
          <span>✉️</span>
          <span><strong id="admin-pending-count">0</strong> unbeantwortete Spielerfragen</span>
          <span style="margin-left:auto;opacity:.6">→</span>
        </a>
        <?php endif; ?>

        <div id="my-status-actions"><?= render_my_status_actions($game, $myGP) ?></div>

        <!-- Rollenbeschreibung & Regeln -->
        <?php if ($myRole && $myGP && $myGP['is_alive'] && $status === 'running'): ?>
          <?php if (!empty($myRole['sichtbar'])): ?>
          <div class="alert alert--info mt-2">
            👁️ Spieler mit derselben Rolle (<?= e($myRole['name']) ?>) erkennst du in der Spielerliste.
          </div>
          <?php endif; ?>
          <?php if ($myRole['description']): ?>
          <div class="panel mt-2 text-sm">
            <strong class="text-accent">Deine Rolle:</strong> <?= e($myRole['description']) ?>
            <?php if ($myRole['rules']): ?>
              <div class="text-dim mt-1 italic">📜 <?= e($myRole['rules']) ?></div>
            <?php endif; ?>
            <?php if ($myRole['cooldown'] > 0): ?>
              <div class="mt-2" id="cooldown-block">
                <button class="btn btn--primary btn--full" id="cd-btn" onclick="startCooldown()">
                  ⏱ Fähigkeit aktivieren (Cooldown starten)
                </button>
                <div id="cd-status" class="text-center text-dim text-sm mt-1" style="display:none">
                  ⏳ Cooldown läuft — noch <strong id="cd-remaining">--:--</strong>
                  <div class="text-xs mt-1" style="opacity:.65">Dauer: <?= (int)$myRole['cooldown'] ?> Minuten</div>
                </div>
                <div class="text-xs text-dim text-center mt-1" style="opacity:.55">
                  Drücke den Button sobald du deine Fähigkeit eingesetzt hast.
                </div>
              </div>
            <?php endif; ?>
          </div>
          <?php endif; ?>
        <?php endif; ?>

        <div class="mt-2 pt-1" style="border-top:1px solid var(--border);text-align:center">
          <a href="<?= APP_URL ?>/docs/spieler.php" class="btn btn--ghost btn--sm btn--full">
            📖 Spieler-Anleitung
          </a>
        </div>
      </div>

      <!-- ── Versammlungs-Karte (immer sichtbar wenn Spiel läuft) ── -->
      <div class="card animate-in mt-2" id="assembly-schedule-card"
           style="animation-delay:.1s<?= $status === 'running' ? '' : ';display:none' ?>">
        <div class="section-title">🏛️ Bürgerversammlung</div>

        <!-- Einberufen-Button: nur für lebende Spieler, nur wenn keine läuft -->
        <?php if ($myGP && $myGP['is_alive']): ?>
        <div id="assembly-call-section">
          <p class="text-dim text-sm mb-2">
            Berufe eine Versammlung ein — sie findet zur nächsten vollen Stunde statt.
            Alle Spieler werden per Push benachrichtigt.
          </p>
          <button class="btn btn--primary btn--full" id="assembly-call-btn" onclick="callAssembly()">
            📣 Versammlung einberufen
          </button>
          <div id="assembly-call-result" class="mt-1"></div>
        </div>
        <?php endif; ?>

        <!-- Countdown: via JS befüllt, anfangs versteckt -->
        <div id="assembly-countdown-section" style="display:none;text-align:center;padding:.25rem 0">
          <div style="font-size:1.8rem;margin-bottom:.3rem">🏛️</div>
          <div style="font-family:var(--font-display);font-size:1.15rem;color:var(--text-bright)" id="assembly-time-label"></div>
          <div class="text-dim text-xs mt-1" id="assembly-caller-label"></div>
          <div style="margin-top:.9rem;font-size:2rem;font-weight:700;color:var(--accent);
                      font-variant-numeric:tabular-nums;letter-spacing:.04em" id="assembly-countdown">--:--</div>
          <div class="text-xs text-dim mt-1">verbleibend</div>
          <button class="btn btn--ghost btn--sm mt-2" id="assembly-end-btn-countdown" onclick="endAssembly()" style="display:none">
            ✖ Versammlung abbrechen
          </button>
        </div>

        <!-- Versammlung läuft! -->
        <div id="assembly-running-section" style="display:none">
          <div class="alert alert--success" style="text-align:center;margin-bottom:.75rem">
            🏛️ <strong>Die Versammlung läuft!</strong><br>
            <span class="text-sm" id="assembly-running-caller"></span>
          </div>
          <button class="btn btn--danger btn--full" id="assembly-end-btn-running" onclick="endAssembly()" style="display:none">
            🏛️ Versammlung beenden
          </button>
        </div>
      </div>

      <!-- Anklagen (nur tagsüber, lebendig) -->
      <?php $showAccuse = $status === 'running' && $phase === 'day' && $myGP && $myGP['is_alive']; ?>
      <div class="card animate-in mt-2" id="assembly-card"
           style="animation-delay:.12s<?= $showAccuse ? '' : ';display:none' ?>">
        <div class="section-title">⚖️ Anklagen</div>
        <p class="text-dim text-sm mb-2">
          Wähle einen Spieler aus der Liste aus und klag ihn vor der Versammlung an.
          Der Admin entscheidet über das Urteil.
        </p>
        <div id="accuse-target-display"
             style="display:flex;align-items:center;gap:.6rem;min-height:2.4rem;padding:.5rem .75rem;
                    border-radius:8px;border:1px solid var(--border);background:var(--panel-bg);margin-bottom:.75rem">
          <span id="accuse-icon" style="font-size:1.2rem">👤</span>
          <span id="accuse-name" class="text-dim text-sm italic">Spieler aus der Liste auswählen …</span>
        </div>
        <button class="btn btn--danger btn--full" id="vote-btn" disabled onclick="castVote()">
          ⚖️ Anklagen
        </button>
        <div id="vote-result" class="mt-1"></div>
      </div>
    </div>

    <!-- ── Rechte Spalte: Spielerliste ── -->
    <div class="card animate-in" style="animation-delay:.12s">
      <div class="section-title">Dorfbewohner</div>
      <div id="player-list" class="player-grid">
        <div class="flex-center" style="padding:2rem;grid-column:1/-1">
          <div class="spinner"></div>
        </div>
      </div>
    </div>
  </div>


</div>

<!-- ── Tod-Melden Modal ──────────────────────────────────────── -->
<div id="death-overlay" style="display:none;position:fixed;inset:0;z-index:600;
     align-items:center;justify-content:center;padding:1.25rem;
     background:rgba(0,0,0,.65);backdrop-filter:blur(12px)">
  <div id="death-modal" style="background:var(--card-bg);border:1px solid var(--danger-border,#7f1d1d);
       border-radius:20px;padding:2rem 1.75rem 1.5rem;max-width:380px;width:100%;
       box-shadow:0 12px 56px rgba(0,0,0,.7);transform:scale(.88);opacity:0;
       transition:transform .26s cubic-bezier(.34,1.4,.64,1),opacity .2s ease">
    <div style="text-align:center;margin-bottom:1.25rem">
      <div style="font-size:3rem;line-height:1;margin-bottom:.6rem">☠️</div>
      <div style="font-family:var(--font-display);font-size:1.5rem;color:var(--text-bright)">Ich bin tot</div>
      <p class="text-dim text-sm mt-1">
        Dein Tod wird in der Chronik vermerkt.<br>
        Diese Aktion kann nicht rückgängig gemacht werden.
      </p>
    </div>

    <div class="form-group" id="death-ort-group">
      <label class="form-label" for="death-ort">
        📍 Wo bist du gestorben? *
      </label>
      <input class="form-input" type="text" id="death-ort" maxlength="200" required
             placeholder="z.B. Hinter der Scheune, Am Brunnen …">
      <small class="text-dim text-xs">
        Pflichtfeld — nur Rollen mit Befragungsrecht sehen diesen Ort.
      </small>
    </div>

    <div id="death-modal-result" style="margin-bottom:.75rem"></div>

    <div class="flex gap-sm">
      <button class="btn btn--danger" style="flex:1" onclick="confirmDeath()" id="death-confirm-btn">
        ☠️ Bestätigen
      </button>
      <button class="btn btn--ghost" style="flex:1" onclick="closeDeathModal()">
        Abbrechen
      </button>
    </div>
  </div>
</div>

<style>
.player-card__icon-mask { display:inline-block; width:1.5rem; height:1.5rem; margin:0 auto .35rem; background-color: var(--accent); -webkit-mask-size:contain; -webkit-mask-repeat:no-repeat; -webkit-mask-position:center; mask-size:contain; mask-repeat:no-repeat; mask-position:center; }
.player-card__icon-photo { display:inline-block; width:1.5rem; height:1.5rem; margin:0 auto .35rem; background-size:contain; background-repeat:no-repeat; background-position:center; border-radius:3px; }

/* Rollen-Effekte → app.css */

/* ── Rollen-Karte Modal ─────────────────────────────────── */
#role-card-overlay {
  position: fixed; inset: 0; z-index: 500;
  display: flex; align-items: center; justify-content: center;
  padding: 1.25rem;
  background: rgba(0,0,0,0);
  backdrop-filter: blur(0px);
  -webkit-backdrop-filter: blur(0px);
  opacity: 0; pointer-events: none;
  transition: opacity .22s ease, backdrop-filter .22s ease, background .22s ease;
}
#role-card-overlay.open {
  opacity: 1; pointer-events: auto;
  /* Voll deckend: die Spielerseite darf hinter der großen Rollenkarte
     nicht durchscheinen (bis zum Bildschirmrand abgedeckt) */
  background: var(--bg, #0d0d14);
}
.role-card-modal {
  background: var(--card-bg);
  border: 1px solid var(--accent-border);
  border-radius: 20px;
  padding: 2rem 1.75rem 1.5rem;
  max-width: 360px; width: 100%;
  text-align: center;
  box-shadow: 0 12px 56px rgba(0,0,0,.65), 0 0 0 1px var(--accent-border);
  cursor: pointer;
  transform: scale(.84); opacity: 0;
  transition: transform .28s cubic-bezier(.34,1.4,.64,1), opacity .22s ease;
  user-select: none;
}
#role-card-overlay.open .role-card-modal {
  transform: scale(1); opacity: 1;
}
.role-card-modal__icon {
  width: 100%; height: 300px;
  margin: 0 0 1.1rem;
  background-size: contain; background-repeat: no-repeat; background-position: center;
  border-radius: 16px;
  background-color: var(--panel-bg);
  padding: 10px; box-sizing: border-box;
}
.role-card-modal__title {
  font-family: var(--font-display);
  font-size: 1.7rem;
  color: var(--accent);
  letter-spacing: .06em;
  margin-bottom: .5rem;
}
.role-card-modal__badge {
  display: inline-block;
  font-size: .78rem;
  color: var(--accent);
  border: 1px solid var(--accent-border);
  border-radius: 99px;
  padding: .2rem .75rem;
  margin-bottom: .75rem;
}
.role-card-modal__desc {
  color: var(--text-bright);
  font-size: .92rem;
  line-height: 1.55;
  margin-bottom: .65rem;
}
.role-card-modal__rules {
  color: var(--text-dim);
  font-size: .82rem;
  font-style: italic;
  line-height: 1.5;
  border-top: 1px solid var(--border);
  padding-top: .65rem;
  margin-top: .5rem;
}
.role-card-modal__cooldown {
  color: var(--text-dim);
  font-size: .8rem;
  margin-top: .5rem;
}
.role-card-modal__hint {
  color: var(--text-dim);
  font-size: .72rem;
  margin-top: 1.1rem;
  opacity: .6;
  letter-spacing: .04em;
}
</style>

<!-- ── Frage stellen Modal ───────────────────────────────── -->
<div id="ask-overlay" onclick="closeAskModal()"
     style="position:fixed;inset:0;z-index:500;display:flex;align-items:center;justify-content:center;
            padding:1.25rem;background:rgba(0,0,0,0);backdrop-filter:blur(0px);
            opacity:0;pointer-events:none;transition:opacity .22s ease,backdrop-filter .22s ease,background .22s ease">
  <div class="role-card-modal" onclick="event.stopPropagation()" style="max-width:380px;text-align:left;cursor:default">
    <div class="role-card-modal__title" style="font-size:1.25rem;margin-bottom:1rem">✉️ Frage an den Spielleiter</div>
    <textarea id="ask-text" class="form-input"
              placeholder="Deine Frage …" rows="4" maxlength="500"
              style="width:100%;font-size:.9rem;resize:vertical;margin-bottom:.75rem;box-sizing:border-box"></textarea>
    <div class="flex gap-sm">
      <button class="btn btn--primary" style="flex:1" onclick="sendAsk()">Senden</button>
      <button class="btn btn--ghost"   style="flex:1" onclick="closeAskModal()">Abbrechen</button>
    </div>
    <div id="ask-result" style="margin-top:.6rem"></div>
  </div>
</div>

<!-- ── Posteingang Modal ─────────────────────────────────── -->
<div id="inbox-overlay" onclick="closeInboxModal()"
     style="position:fixed;inset:0;z-index:500;display:flex;align-items:center;justify-content:center;
            padding:1.25rem;background:rgba(0,0,0,0);backdrop-filter:blur(0px);
            opacity:0;pointer-events:none;transition:opacity .22s ease,backdrop-filter .22s ease,background .22s ease">
  <div class="role-card-modal" onclick="event.stopPropagation()"
       style="max-width:440px;text-align:left;cursor:default;max-height:80vh;overflow-y:auto">
    <div class="role-card-modal__title" style="font-size:1.25rem;margin-bottom:1rem">📬 Posteingang</div>
    <div id="inbox-list">
      <div class="flex-center" style="padding:1.5rem"><div class="spinner"></div></div>
    </div>
    <button class="btn btn--ghost btn--full btn--sm mt-2" onclick="closeInboxModal()">Schließen</button>
  </div>
</div>

<?php if ($myGP): ?>
<?php $cardRole = $myRole ?? ['name' => 'Noch keine Rolle', 'icon_path' => DEFAULT_ROLE_ICON, 'sichtbar' => 0, 'description' => 'Dir wurde noch keine Rolle zugewiesen.', 'rules' => '', 'cooldown' => 0]; ?>
<div id="role-card-overlay" onclick="closeRoleCard()" role="dialog" aria-modal="true">
  <div class="role-card-modal">
    <div class="role-fx role-fx--modal" id="role-fx-modal">
      <div class="role-card-modal__icon"
           style="background-image:url('<?= e(roleIconUrl($cardRole)) ?>')"></div>
    </div>
    <div class="role-card-modal__title role-badge--glow"><?= e($cardRole['name']) ?></div>
    <?php if (!empty($cardRole['sichtbar'])): ?>
      <div class="role-card-modal__badge">👁️ Ihr erkennt euch untereinander</div>
    <?php endif; ?>
    <?php if ($cardRole['description']): ?>
      <p class="role-card-modal__desc"><?= e($cardRole['description']) ?></p>
    <?php endif; ?>
    <?php if ($cardRole['rules']): ?>
      <div class="role-card-modal__rules">📜 <?= e($cardRole['rules']) ?></div>
    <?php endif; ?>
    <?php if ((int)($cardRole['cooldown'] ?? 0) > 0): ?>
      <div class="role-card-modal__cooldown">⏳ Cooldown: alle <?= (int)$cardRole['cooldown'] + 1 ?> Nächte</div>
    <?php endif; ?>
    <div class="role-card-modal__hint">Tippen zum Schließen</div>
  </div>
</div>
<?php endif; ?>

<?php
$page['inline_js'] .= <<<'JS'
let selectedTarget    = null;
let _lastStatusHtml   = null; // zuletzt gerendertes my-status-actions-Fragment
let _lastPlayersHtml  = null; // zuletzt gerenderte Spielerliste

async function joinGame() {
  if (!GAME_ID) { showToast('Kein aktives Spiel vorhanden.', 'error'); return; }
  const r = await apiFetch(API_BASE+'/game.php',{action:'join',game_id:GAME_ID});
  if(r.error==='session_expired')return;
  if(r.ok){showToast('Beigetreten!','success');await gamePoll.refreshNow();}
  else showToast(r.error||'Fehler','error');
}

let _lastBannerKey = GAME_STATUS + ':' + GAME_PHASE;
function _updatePhaseBanner(status, phase) {
  // Textinhalt bleibt der Sprüche-Rotation (_startSloganRotation/_setBannerText)
  // überlassen — hier nur die Hintergrundfarbe/Klasse synchron halten und,
  // falls das Spiel bei offenem Tab von Lobby auf Running wechselt, die
  // Rotation nachträglich anstoßen (beim ersten Laden war sie noch aus).
  const key = status + ':' + phase;
  if (key === _lastBannerKey) return; // unverändert — kein Flackern bei jedem Poll
  _lastBannerKey = key;
  const banner = document.getElementById('phase-banner');
  if (!banner) return;
  banner.className = 'phase-banner phase-banner--' + (status === 'running' ? phase : 'lobby');
  if (status !== 'running') {
    _setBannerText('🏰 Warte auf Spielstart');
  } else if (!_sloganTimer && !_bannerBeraet) {
    _startSloganRotation();
  }
}

function renderRoleIcon(iconPath){
  const base = API_BASE.replace('/api','');
  const fullUrl = base + '/' + iconPath + '?v=' + ASSET_VER;
  return `<span class="player-card__icon-photo" style="background-image:url('${fullUrl}')"></span>`;
}

function renderGameState(r) {
  if(!r.players){
    document.getElementById('player-list').innerHTML='<p class="text-dim" style="grid-column:1/-1;padding:1rem">Spieler konnten nicht geladen werden.</p>';
    return;
  }

  if (r.game) {
    // Status-Wechsel (Lobby→Läuft, Läuft→Beendet, Reset): einmalig komplett neu
    // laden — Rollen-Karte, Cooldown-Konstanten und Karten-Modal werden nur beim
    // Seitenaufbau gerendert und wären sonst veraltet (Spieler sähe seine Rolle nicht).
    if (GAME_STATUS !== r.game.status) { location.reload(); return; }
    GAME_STATUS = r.game.status;
    GAME_PHASE  = r.game.phase;
    _updatePhaseBanner(GAME_STATUS, GAME_PHASE);
  }
  if (r.me) {
    MY_ALIVE = r.me.is_alive;
    const actionsEl = document.getElementById('my-status-actions');
    if (actionsEl && r.my_status_html !== undefined && r.my_status_html !== _lastStatusHtml) {
      actionsEl.innerHTML = r.my_status_html;
      _lastStatusHtml = r.my_status_html;
    }
    const assemblyCard = document.getElementById('assembly-schedule-card');
    if (assemblyCard) assemblyCard.style.display = GAME_STATUS === 'running' ? '' : 'none';
    const accuseCard = document.getElementById('assembly-card');
    if (accuseCard) accuseCard.style.display =
      (GAME_STATUS === 'running' && GAME_PHASE === 'day' && r.me.in_game && MY_ALIVE) ? '' : 'none';
  }

  if(r.players.length===0){
    document.getElementById('player-list').innerHTML='<p class="text-dim" style="grid-column:1/-1;padding:1rem">Noch keine Spieler im Spiel.</p>';
    return;
  }
  const list=document.getElementById('player-list');
  const html=r.players.map(p=>{
    const dead=!p.is_alive;
    const canSel=!dead&&p.player_id!=PLAYER_ID&&MY_ALIVE&&GAME_STATUS==='running';
    const pName=p.display_name;
    const roleHtml = !dead && p.role_name
      ? `<div class="player-card__role">${escHtml(p.role_name)}</div>` : '';
    const iconHtml = !dead && p.role_icon_path
      ? renderRoleIcon(p.role_icon_path)
      : `<span class="player-card__icon">${dead?'🕯️':'👤'}</span>`;
    return `<div class="player-card${dead?' player-card--dead':''}" id="pc-${p.player_id}"
      onclick="${canSel?`selectTarget(${p.player_id},'${escHtml(pName)}')`:''}" >
      ${dead?'<span class="player-card__skull">💀</span>':''}
      ${iconHtml}
      <div class="player-card__name">${escHtml(p.display_name)}</div>
      ${roleHtml}
    </div>`;
  }).join('');
  // Nur bei tatsächlicher Änderung schreiben — verhindert Reflow/Flackern
  // und den Verlust der aktuellen Auswahl-Markierung bei jedem Poll-Tick
  if (html !== _lastPlayersHtml) {
    list.innerHTML = html;
    _lastPlayersHtml = html;
    // Auswahl-Markierung nach Neuaufbau wiederherstellen
    if (selectedTarget !== null) {
      document.getElementById('pc-' + selectedTarget)?.classList.add('selected');
    }
  }
}

// ── Banner-Slogan-Rotation ───────────────────────────────────
let _bannerBeraet = false;
let _sloganTimer  = null;

function _setBannerText(txt) {
  const el = document.getElementById('phase-banner-text');
  if (!el) return;
  el.style.transition = 'opacity .35s';
  el.style.opacity = '0';
  setTimeout(() => { el.textContent = txt; el.style.opacity = '1'; }, 350);
}

function _nextSlogan() {
  if (_bannerBeraet) return;
  const arr  = GAME_PHASE === 'night' ? NIGHT_SLOGANS : DAY_SLOGANS;
  if (!arr.length) return;
  const icon = GAME_PHASE === 'night' ? '🌕' : '☀️';
  _setBannerText(icon + ' ' + arr[Math.floor(Math.random() * arr.length)]);
}

function _startSloganRotation() {
  if (GAME_STATUS !== 'running') return;
  _nextSlogan();
  _sloganTimer = setInterval(_nextSlogan, 120000); // alle 2 Minuten
}

function _bannerSetBeraet() {
  _bannerBeraet = true;
  clearInterval(_sloganTimer);
  _setBannerText('☀️ Tag — Das Dorf berät');
}

if (GAME_STATUS === 'running') {
  document.addEventListener('DOMContentLoaded', _startSloganRotation);
}

function selectTarget(id, name) {
  selectedTarget = id;
  document.querySelectorAll('.player-card').forEach(c => c.classList.remove('selected'));
  const c = document.getElementById('pc-' + id);
  if (c) c.classList.add('selected');

  const nameEl = document.getElementById('accuse-name');
  const iconEl = document.getElementById('accuse-icon');
  if (nameEl) { nameEl.textContent = name; nameEl.style.color = 'var(--text-bright)'; nameEl.style.fontStyle = 'normal'; }
  if (iconEl) iconEl.textContent = '🎯';

  const btn = document.getElementById('vote-btn');
  if (btn) btn.disabled = false;

  // Banner auf "Das Dorf berät" umschalten
  _bannerSetBeraet();
}

async function castVote() {
  if (!selectedTarget) return;
  const r = await apiFetch(API_BASE+'/game.php', {action:'vote', game_id:GAME_ID, target_id:selectedTarget});
  if (r.error === 'session_expired') return;
  const el = document.getElementById('vote-result');
  if (r.ok) {
    el.innerHTML = '<div class="alert alert--success">⚖️ Anklage eingereicht!</div>';
    document.getElementById('vote-btn').disabled = true;
    document.getElementById('vote-btn').textContent = '✓ Anklage eingereicht';
  } else {
    el.innerHTML = `<div class="alert alert--error">${escHtml(r.error||'Fehler')}</div>`;
  }
}

// ── Bürgerversammlung einberufen ────────────────────────────────
let _assemblyData = ASSEMBLY_DATA; // aus PHP: {scheduled_at, notified, caller} | null
let _assemblyCountdownTimer = null;

function _assemblyRender() {
  const callSection      = document.getElementById('assembly-call-section');
  const countdownSection = document.getElementById('assembly-countdown-section');
  const runningSection   = document.getElementById('assembly-running-section');
  if (!countdownSection) return;

  const canEnd = _assemblyData && (PLAYER_ID === _assemblyData.caller_id || MY_IS_ADMIN);

  if (!_assemblyData || !_assemblyData.scheduled_at) {
    if (callSection) callSection.style.display = '';
    countdownSection.style.display = 'none';
    runningSection.style.display   = 'none';
    return;
  }

  const now  = Math.floor(Date.now() / 1000);
  const diff = _assemblyData.scheduled_at - now;
  const timeLabel = new Date(_assemblyData.scheduled_at * 1000)
    .toLocaleTimeString('de-DE', {hour:'2-digit', minute:'2-digit'});

  if (diff > 0) {
    if (callSection) callSection.style.display = 'none';
    countdownSection.style.display = '';
    runningSection.style.display   = 'none';
    document.getElementById('assembly-time-label').textContent = 'Versammlung um ' + timeLabel + ' Uhr';
    document.getElementById('assembly-caller-label').textContent = 'Einberufen von ' + (_assemblyData.caller || '');
    const m = Math.floor(diff / 60), s = diff % 60;
    document.getElementById('assembly-countdown').textContent =
      String(m).padStart(2,'0') + ':' + String(s).padStart(2,'0');
    const endBtn = document.getElementById('assembly-end-btn-countdown');
    if (endBtn) endBtn.style.display = canEnd ? '' : 'none';
  } else {
    if (callSection) callSection.style.display = 'none';
    countdownSection.style.display = 'none';
    runningSection.style.display   = '';
    const callerEl = document.getElementById('assembly-running-caller');
    if (callerEl) callerEl.textContent = 'Einberufen von ' + (_assemblyData.caller || '');
    const endBtn = document.getElementById('assembly-end-btn-running');
    if (endBtn) endBtn.style.display = canEnd ? '' : 'none';
  }
}

async function endAssembly() {
  const r = await apiFetch(API_BASE+'/game.php', {action:'end_assembly', game_id:GAME_ID});
  if (r.error === 'session_expired') return;
  if (r.ok) {
    _assemblyData = null;
    _assemblyRender();
  } else {
    showToast(r.error || 'Fehler', 'error');
  }
}

async function callAssembly() {
  const btn = document.getElementById('assembly-call-btn');
  if (btn) btn.disabled = true;
  const r = await apiFetch(API_BASE+'/game.php', {action:'call_assembly', game_id:GAME_ID});
  if (r.error === 'session_expired') return;
  const resultEl = document.getElementById('assembly-call-result');
  if (r.ok) {
    _assemblyData = {scheduled_at: r.scheduled_at, notified: false, caller: r.caller};
    _assemblyRender();
    if (resultEl) resultEl.innerHTML = '';
  } else {
    if (btn) btn.disabled = false;
    if (resultEl) resultEl.innerHTML = `<div class="alert alert--error mt-1">${escHtml(r.error||'Fehler')}</div>`;
  }
}

// Versammlungs-Poll: läuft über liveBlocks() im eingestellten Ladeintervall
// (kein countdownId — den Nav-Countdown steuert der Haupt-Poller der Seite).
// Läuft immer, damit ein Lobby→Running-Wechsel bei offenem Tab auch ohne
// Reload den Versammlungs-Countdown startet — Sichtbarkeit der Karte selbst
// wird separat in renderGameState() über #assembly-schedule-card gesteuert.
const assemblyPoll = liveBlocks({
  fetcher: () => apiFetch(API_BASE+'/game.php', {action:'get_assembly', game_id:GAME_ID}),
  onData: (r) => {
    if ('assembly' in r) {
      // auch null übernehmen — sonst bleibt der Countdown stehen,
      // wenn ein anderer Spieler/Admin die Versammlung beendet
      _assemblyData = r.assembly;
      _assemblyRender();
    }
  },
});
_assemblyRender();
setInterval(_assemblyRender, 1000); // reiner Anzeige-Ticker (kein Serverkontakt)
assemblyPoll.start();



// phase-change effect + Effekte auf aktuelle Phase setzen
(function () {
  const stored = localStorage.getItem('ww_last_phase');
  if (stored && stored !== GAME_PHASE && GAME_STATUS === 'running') {
    triggerPhaseTransition(GAME_PHASE);
  }
  if (GAME_STATUS === 'running') localStorage.setItem('ww_last_phase', GAME_PHASE);
  else localStorage.removeItem('ww_last_phase');
  // Nebel + Partikelfarbe sofort auf aktuelle Phase setzen
  if (window.FX && GAME_STATUS === 'running') FX.updateForPhase(GAME_PHASE);
})();

// ── Karten-Effekte: Body-Klassen nach Settings setzen ───────
if (localStorage.getItem('ww_fx_rolecard') === 'false') {
  document.body.classList.add('fx-rolecard-off');
}
if (localStorage.getItem('ww_fx_rolename') === 'false') {
  document.body.classList.add('fx-rolename-off');
}

// ── Rollen-Funken Helper ─────────────────────────────────────
function _spawnSpark(wrap) {
  if (document.body.classList.contains('fx-rolecard-off')) return;
  const w = wrap.offsetWidth, h = wrap.offsetHeight;
  const s = document.createElement('span');
  s.className = 'role-spark';
  const sx  = Math.random() * (w + 20) - 10;
  const sy  = h - Math.random() * 10;
  const tx  = (Math.random() - .5) * 40;
  const ty  = -(30 + Math.random() * 40);
  const dur = (.8 + Math.random() * .9).toFixed(2) + 's';
  const sz  = (3 + Math.random() * 4).toFixed(1) + 'px';
  s.style.cssText = `--x:${sx.toFixed(1)}px;--y:${sy.toFixed(1)}px;--tx:${tx.toFixed(1)}px;--ty:${ty.toFixed(1)}px;--dur:${dur};--delay:0s;width:${sz};height:${sz}`;
  wrap.appendChild(s);
  setTimeout(() => s.remove(), parseFloat(dur) * 1000 + 50);
}

// Status-Karte: dauerhaft Funken
(function() {
  const wrap = document.getElementById('role-fx-wrap');
  if (!wrap) return;
  setInterval(() => _spawnSpark(wrap), 220);
})();

// Modal: Funken nur solange offen
let _modalSparkTimer = null;

// Zentrale Poll-Schleife über den gemeinsamen liveBlocks()-Helper:
// Overlap-Guard, Pause bei verstecktem Tab und Intervall-Neustart bei
// geänderter Einstellung kommen damit automatisch mit.
const gamePoll = liveBlocks({
  fetcher: () => {
    if (!GAME_ID) {
      document.getElementById('player-list').innerHTML='<p class="text-dim" style="grid-column:1/-1;padding:1rem">Kein Spiel aktiv.</p>';
      return Promise.resolve(null);
    }
    return apiFetch(API_BASE+'/game.php', {action:'get_players', game_id:GAME_ID});
  },
  countdownId: 'poll-countdown',
  onData: renderGameState,
});
gamePoll.start();

function openRoleCard() {
  const el = document.getElementById('role-card-overlay');
  if (el) el.classList.add('open');
  const wrap = document.getElementById('role-fx-modal');
  if (wrap) _modalSparkTimer = setInterval(() => _spawnSpark(wrap), 180);
}
function closeRoleCard() {
  const el = document.getElementById('role-card-overlay');
  if (el) el.classList.remove('open');
  clearInterval(_modalSparkTimer);
  _modalSparkTimer = null;
}
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') { closeRoleCard(); closeAskModal(); closeInboxModal(); }
});
JS;

$page['inline_js'] .= sprintf("\nconst MSG_API = %s;\n", json_encode(API_URL . '/messages.php'));
$page['inline_js'] .= <<<'MSGJS'

// ── Nachrichten ──────────────────────────────────────────────
function _openOverlay(id) {
  const el = document.getElementById(id);
  if (!el) return;
  el.style.opacity = '0';
  el.style.pointerEvents = 'auto';
  el.style.background = 'rgba(0,0,0,.58)';
  el.style.backdropFilter = 'blur(14px)';
  el.style.webkitBackdropFilter = 'blur(14px)';
  requestAnimationFrame(() => { el.style.transition='opacity .22s ease,backdrop-filter .22s ease,background .22s ease'; el.style.opacity='1'; });
  const card = el.querySelector('.role-card-modal');
  if (card) { card.style.transform='scale(.84)'; card.style.opacity='0'; requestAnimationFrame(()=>{ card.style.transition='transform .28s cubic-bezier(.34,1.4,.64,1),opacity .22s ease'; card.style.transform='scale(1)'; card.style.opacity='1'; }); }
}
function _closeOverlay(id) {
  const el = document.getElementById(id);
  if (!el) return;
  el.style.opacity = '0';
  el.style.background = 'rgba(0,0,0,0)';
  el.style.backdropFilter = 'blur(0px)';
  el.style.webkitBackdropFilter = 'blur(0px)';
  setTimeout(() => { el.style.pointerEvents = 'none'; }, 250);
}

// ── Frage stellen ────────────────────────────────────────────
function openAskModal() {
  document.getElementById('ask-result').innerHTML = '';
  document.getElementById('ask-text').value = '';
  _openOverlay('ask-overlay');
  setTimeout(() => document.getElementById('ask-text').focus(), 260);
}
function closeAskModal() { _closeOverlay('ask-overlay'); }

async function sendAsk() {
  const ta  = document.getElementById('ask-text');
  const rd  = document.getElementById('ask-result');
  const msg = ta.value.trim();
  if (msg.length < 3) { rd.innerHTML='<div class="alert alert--error">Bitte mindestens 3 Zeichen eingeben.</div>'; return; }
  const r = await apiFetch(MSG_API, {action:'send', message:msg});
  if (r.error === 'session_expired') return;
  if (r.ok) {
    rd.innerHTML = '<div class="alert alert--success">✓ Frage gesendet — der Spielleiter antwortet dir bald.</div>';
    ta.value = '';
    setTimeout(closeAskModal, 1800);
  } else {
    rd.innerHTML = '<div class="alert alert--error">'+escHtml(r.error||'Fehler')+'</div>';
  }
}

// ── Posteingang ──────────────────────────────────────────────
async function openInboxModal() {
  _openOverlay('inbox-overlay');
  await loadInbox();
}
function closeInboxModal() { _closeOverlay('inbox-overlay'); }

async function loadInbox() {
  const list = document.getElementById('inbox-list');
  if (!list) return;
  list.innerHTML = '<div class="flex-center" style="padding:1.5rem"><div class="spinner"></div></div>';
  const r = await apiFetch(MSG_API, {action:'get_my'});
  if (r.error === 'session_expired') return;
  if (!r.messages) { list.innerHTML='<p class="text-dim text-center" style="padding:1rem">Fehler beim Laden.</p>'; return; }

  // Badge zurücksetzen (wurden beim get_my als gelesen markiert)
  const badge = document.getElementById('msg-badge');
  if (badge) badge.style.display = 'none';

  if (r.messages.length === 0) {
    list.innerHTML = '<p class="text-dim text-center" style="padding:1rem;font-size:.9rem">Noch keine Nachrichten gesendet.</p>';
    return;
  }
  list.innerHTML = r.messages.map(m => {
    const date = new Date(m.created_at).toLocaleString('de-DE',{day:'2-digit',month:'2-digit',hour:'2-digit',minute:'2-digit'});
    const replyHtml = m.reply
      ? `<div style="border-left:2px solid var(--accent-border);padding:.5rem .75rem;margin-top:.5rem;
                     font-size:.85rem;line-height:1.5;background:var(--panel-bg);border-radius:0 6px 6px 0">
           <div class="text-dim text-xs mb-1">Antwort des Spielleiters</div>
           <span style="color:var(--text-bright)">${escHtml(m.reply)}</span>
         </div>`
      : `<div class="text-dim text-xs mt-1" style="font-style:italic">⏳ Warte auf Antwort …</div>`;
    return `<div style="padding:.7rem 0;border-bottom:1px solid var(--border)">
      <div class="text-dim text-xs mb-1">${escHtml(date)}</div>
      <div style="font-size:.9rem;line-height:1.5">${escHtml(m.message)}</div>
      ${replyHtml}
    </div>`;
  }).join('');
}

// ── Ungelesene Badge + Toast beim Laden / Polling ────────────
let _lastUnread = -1; // -1 = erster Aufruf → kein Toast beim Seitenstart

// Badge-Poll im eingestellten Ladeintervall (liveBlocks: Tab-Pause + Overlap-Guard)
liveBlocks({
  fetcher: () => apiFetch(MSG_API, {action:'unread_count'}),
  onData: (r) => { if (r.unread !== undefined) _applyUnread(r.unread); },
}).start();

function _applyUnread(unread) {
  const badge = document.getElementById('msg-badge');
  if (badge) {
    if (unread > 0) { badge.textContent = unread; badge.style.display = 'inline-block'; }
    else            { badge.style.display = 'none'; }
  }
  if (_lastUnread >= 0 && unread > _lastUnread) {
    showToast('📬 Der Spielleiter hat dir geantwortet!', 'info', 5000);
  }
  _lastUnread = unread;
}
MSGJS;

// (Admin-Hinweis „unbeantwortete Spielerfragen" wird vom globalen
//  pending_count-Poll in templates/base_end.php mitbedient.)

// ── Tod-Melden ───────────────────────────────────────────────
$page['inline_js'] .= <<<'DEATHJS'

function openDeathModal() {
  const overlay = document.getElementById('death-overlay');
  const modal   = document.getElementById('death-modal');
  overlay.style.display = 'flex';
  document.getElementById('death-ort').value = '';
  document.getElementById('death-modal-result').innerHTML = '';
  document.getElementById('death-confirm-btn').disabled = false;
  requestAnimationFrame(() => requestAnimationFrame(() => {
    modal.style.transform = 'scale(1)';
    modal.style.opacity   = '1';
  }));
}

function closeDeathModal() {
  const overlay = document.getElementById('death-overlay');
  const modal   = document.getElementById('death-modal');
  modal.style.transform = 'scale(.88)';
  modal.style.opacity   = '0';
  setTimeout(() => { overlay.style.display = 'none'; }, 220);
}

async function confirmDeath() {
  const btn = document.getElementById('death-confirm-btn');
  const res = document.getElementById('death-modal-result');
  const ort = document.getElementById('death-ort').value.trim();
  btn.disabled = true;
  res.innerHTML = '<span class="text-dim text-sm">Wird gemeldet…</span>';
  const r = await apiFetch(API_BASE + '/game.php', {
    action: 'self_report_death',
    game_id: GAME_ID,
    ort: ort,
  });
  if (r.ok) {
    res.innerHTML = '<div class="alert alert--success">☠️ Du wurdest als tot eingetragen.</div>';
    setTimeout(closeDeathModal, 1200);
    await gamePoll.refreshNow();
  } else {
    res.innerHTML = `<div class="alert alert--error">${escHtml(r.error || 'Fehler')}</div>`;
    btn.disabled = false;
  }
}

document.getElementById('death-overlay').addEventListener('click', function(e) {
  if (e.target === this) closeDeathModal();
});
DEATHJS;

$page['inline_js'] .= <<<'CDJS'

// ── Cooldown ─────────────────────────────────────────────────
(function initCooldown() {
  if (!MY_COOLDOWN_MINS || MY_COOLDOWN_MINS <= 0) return;
  const btn       = document.getElementById('cd-btn');
  const statusEl  = document.getElementById('cd-status');
  const remaining = document.getElementById('cd-remaining');
  if (!btn) return;

  const totalSecs = MY_COOLDOWN_MINS * 60;
  let startedAt   = MY_COOLDOWN_STARTED ? new Date(MY_COOLDOWN_STARTED).getTime() : null;

  function tick() {
    if (!startedAt) {
      btn.disabled           = false;
      btn.textContent        = '⏱ Fähigkeit aktivieren (Cooldown starten)';
      statusEl.style.display = 'none';
      return;
    }
    const elapsed = (Date.now() - startedAt) / 1000;
    const left    = totalSecs - elapsed;
    if (left <= 0) {
      startedAt              = null;
      btn.disabled           = false;
      btn.textContent        = '⏱ Fähigkeit aktivieren (Cooldown starten)';
      statusEl.style.display = 'none';
      return;
    }
    btn.disabled           = true;
    btn.textContent        = '⏱ Cooldown läuft …';
    statusEl.style.display = '';
    const m = Math.floor(left / 60);
    const s = Math.floor(left % 60);
    remaining.textContent  = m + ':' + String(s).padStart(2, '0');
  }

  tick();
  setInterval(tick, 1000);

  window._setCooldownStarted = function(iso) {
    startedAt = new Date(iso).getTime();
    tick();
  };
})();

function startCooldown() {
  const btn = document.getElementById('cd-btn');
  if (!btn || btn.disabled) return;
  btn.disabled    = true;
  btn.textContent = '…';
  fetch(API_BASE + '/game.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({action:'start_cooldown', game_id: GAME_ID})
  })
  .then(r => r.json())
  .then(d => {
    if (d.ok) {
      if (window._setCooldownStarted) window._setCooldownStarted(d.started_at);
    } else {
      alert(d.error || 'Fehler beim Starten des Cooldowns');
      btn.disabled    = false;
      btn.textContent = '⏱ Fähigkeit aktivieren (Cooldown starten)';
    }
  })
  .catch(() => {
    btn.disabled    = false;
    btn.textContent = '⏱ Fähigkeit aktivieren (Cooldown starten)';
  });
}
CDJS;

require TEMPLATE_PATH . '/base_end.php';
?>
