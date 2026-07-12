<?php
// Copyright (c) 2026 Andreas Vetter
require_once dirname(__DIR__) . '/core/bootstrap.php';
require_once TEMPLATE_PATH . '/messages_blocks.php';
Auth::requireAdmin();

$messages = Database::query(
    "SELECT m.id, m.type, m.status, m.message, m.faq_question, m.reply, m.created_at, m.replied_at, m.read_by_player, m.published,
            p.display_name, p.username
     FROM messages m
     JOIN players p ON p.id = m.player_id
     ORDER BY (CASE WHEN m.type = 'question' THEN (m.reply IS NULL) ELSE (m.status = 'open') END) DESC,
              m.created_at DESC"
);

// Gelesen-Marker: Mit dem Öffnen dieser Seite hat der Admin alle Einträge gesehen —
// der Spieler sieht dadurch auf seiner Feedback-Seite „👁 Gelesen".
Database::execute("UPDATE messages SET read_by_admin = 1 WHERE read_by_admin = 0");
$pending      = count(array_filter($messages, fn($m) => ($m['type'] ?? 'question') === 'question' && $m['reply'] === null));
$feedbackOpen = count(array_filter($messages, fn($m) => ($m['type'] ?? 'question') !== 'question' && $m['status'] === 'open'));
$maxMsgId = $messages ? max(array_column($messages, 'id')) : 0;

// Feedback-API-Token: nur ob eines gesetzt ist — der Wert selbst wird nach dem
// Generieren einmalig angezeigt, danach nie wieder ausgelesen.
$hasApiToken = trim(Database::queryOne("SELECT value FROM settings WHERE `key`='feedback_api_token'")['value'] ?? '') !== '';

$page = ['title' => 'Spielerfragen & Feedback'];
require TEMPLATE_PATH . '/base.php';
?>

<div class="container page-wrap">

  <div class="page-header">
    <span class="page-header__icon">✉️</span>
    <h1>Spielerfragen &amp; Feedback</h1>
    <p class="page-header__sub">
      <span id="pending-badge"><?php if ($pending > 0): ?><span class="tag tag--running"><?= $pending ?> unbeantwortet</span> &middot; <?php endif; ?></span>
      <span id="feedback-badge"><?php if ($feedbackOpen > 0): ?><span class="tag tag--night"><?= $feedbackOpen ?> offenes Feedback</span> &middot; <?php endif; ?></span>
      <a href="<?= APP_URL ?>/admin/">← zurück zur Spielleitung</a>
    </p>
  </div>

  <div class="flex-between" style="flex-wrap:wrap;gap:.5rem;margin-bottom:.6rem">
    <!-- Typ-Filter (rein client-seitig über data-type) -->
    <div class="flex gap-xs" style="flex-wrap:wrap" id="type-filter">
      <button class="btn btn--primary btn--sm" data-filter="all"      onclick="filterType('all', this)">Alle</button>
      <button class="btn btn--ghost btn--sm"   data-filter="question" onclick="filterType('question', this)">✉️ Fragen</button>
      <button class="btn btn--ghost btn--sm"   data-filter="bug"      onclick="filterType('bug', this)">🐛 Bugs</button>
      <button class="btn btn--ghost btn--sm"   data-filter="wish"     onclick="filterType('wish', this)">💡 Wünsche</button>
      <button class="btn btn--ghost btn--sm"   data-filter="feedback" onclick="filterType('feedback', this)">💬 Feedback</button>
    </div>
  </div>

  <div class="card card--glow animate-in">
    <div id="msg-list" style="display:flex;flex-direction:column;gap:.75rem">
      <?php if (empty($messages)): ?>
      <p class="text-dim text-center" id="msg-list-empty" style="padding:2rem">
        Noch keine Nachrichten von Spielern.
      </p>
      <?php else: ?>
      <?php foreach ($messages as $msg): ?>
      <?= render_message_row($msg) ?>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- ── Feedback-API (externer Lesezugriff, z. B. für KI-Assistenten) ── -->
  <div class="card animate-in" style="margin-top:1rem">
    <details>
      <summary style="cursor:pointer;font-family:var(--font-display);color:var(--text-bright)">
        🔌 Feedback-API (externer Zugriff)
      </summary>
      <div style="margin-top:.75rem;font-size:.88rem;line-height:1.6">
        <p class="text-dim" style="margin:0 0 .6rem">
          Token-gesicherte Schnittstelle, über die Feedback-Einträge (Bugs, Wünsche, Feedback —
          <strong>nie Spielerfragen</strong>) von außen ausgelesen und deren Status gesetzt werden kann,
          z.&nbsp;B. durch einen KI-Assistenten. Ohne Token ist die API komplett deaktiviert.
        </p>
        <p style="margin:0 0 .6rem">
          Status: <span id="api-token-state" class="tag <?= $hasApiToken ? 'tag--alive' : '' ?>"
                        <?= $hasApiToken ? '' : 'style="background:var(--panel-bg);color:var(--text-dim)"' ?>>
            <?= $hasApiToken ? '🟢 Aktiv (Token gesetzt)' : '⚪ Deaktiviert (kein Token)' ?>
          </span>
        </p>
        <div class="flex gap-xs" style="flex-wrap:wrap">
          <button class="btn btn--primary btn--sm" onclick="generateApiToken(this)">
            <?= $hasApiToken ? '🔄 Token neu generieren' : '🔑 Token generieren' ?>
          </button>
          <?php if ($hasApiToken): ?>
          <button class="btn btn--ghost btn--sm" id="api-token-clear" onclick="clearApiToken(this)">🗑 Token entfernen (API deaktivieren)</button>
          <?php endif; ?>
        </div>
        <div id="api-token-box" style="display:none;margin-top:.6rem">
          <div class="alert alert--success" style="font-size:.82rem">
            Neues Token — <strong>jetzt kopieren</strong>, es wird nur dieses eine Mal angezeigt:
          </div>
          <code id="api-token-value" style="display:block;word-break:break-all;background:var(--panel-bg);
                border:1px solid var(--border);border-radius:8px;padding:.5rem .7rem;margin-top:.4rem;font-size:.78rem"></code>
          <button class="btn btn--ghost btn--sm mt-1" onclick="copyApiToken(this)">📋 Kopieren</button>
        </div>
        <div class="text-dim text-xs" style="margin-top:.6rem;line-height:1.6">
          Nutzung (HTTPS Pflicht, Token als Bearer-Header):<br>
          <code>GET <?= e(API_URL) ?>/feedback.php?action=list</code> — Einträge als JSON<br>
          <code>POST <?= e(API_URL) ?>/feedback.php</code> mit <code>{"action":"set_status","id":…,"status":"open|in_progress|done"}</code><br>
          Header: <code>Authorization: Bearer &lt;Token&gt;</code>
        </div>
      </div>
    </details>
  </div>

</div>

<?php
$page['inline_js'] = sprintf('const MSG_API = %s; let _maxMsgId = %d;', json_encode(API_URL . '/messages.php'), $maxMsgId);
$page['inline_js'] .= <<<'JS'

function toggleCollapse(id) {
  const body    = document.getElementById('body-'    + id);
  const chevron = document.getElementById('chevron-' + id);
  const panel   = document.getElementById('msg-'     + id);
  const preview = document.getElementById('preview-' + id);
  if (!body) return;
  const open = body.style.display !== 'none';
  body.style.display    = open ? 'none' : '';
  if (chevron) chevron.style.transform = open ? 'rotate(0deg)' : 'rotate(180deg)';
  if (preview) preview.style.display   = open ? ''     : 'none';
  if (panel)   panel.style.opacity     = open ? '.75'  : '1';
}

// Ersetzt eine komplette Zeile durch das frisch vom Server gerenderte HTML
// (nach Antworten/Veröffentlichen/FAQ-Text speichern) — dieselbe render_message_row()-
// Funktion wie beim initialen Seitenaufbau, damit z. B. der FAQ-Button sofort nach
// dem Antworten erscheint (vorher: manuelles DOM-Patchen, das ihn vergaß und erst
// nach einem Seiten-Reload zeigte). Klappt die Zeile danach automatisch auf.
function replaceMsgRow(id, html) {
  const old = document.getElementById('msg-' + id);
  if (!old) return;
  const tmp = document.createElement('div');
  tmp.innerHTML = html;
  const fresh = tmp.firstElementChild;
  if (!fresh) return;
  old.replaceWith(fresh);
  const body = document.getElementById('body-' + id);
  if (body) body.style.display = '';
  const chevron = document.getElementById('chevron-' + id);
  if (chevron) chevron.style.transform = 'rotate(180deg)';
  const preview = document.getElementById('preview-' + id);
  if (preview) preview.style.display = 'none';
}

async function sendReply(id) {
  const ta  = document.getElementById('reply-text-' + id);
  const rd  = document.getElementById('reply-result-' + id);
  const txt = ta ? ta.value.trim() : '';
  if (!txt) { showToast('Antwort darf nicht leer sein.', 'error'); return; }

  const r = await apiFetch(MSG_API, {action:'reply', id, reply:txt});
  if (r.error === 'session_expired') return;
  if (r.ok) {
    showToast('Antwort gesendet.', 'success');
    if (r.html) replaceMsgRow(id, r.html);
  } else {
    if (rd) { rd.style.display=''; rd.innerHTML='<div class="alert alert--error" style="padding:.3rem .7rem;font-size:.82rem">'+escHtml(r.error||'Fehler')+'</div>'; }
  }
}

function openEditReply(id, currentText) {
  const body    = document.getElementById('body-'          + id);
  const display = document.getElementById('reply-display-' + id);
  const form    = document.getElementById('reply-form-'    + id);
  const ta      = document.getElementById('reply-text-'    + id);
  if (body && body.style.display === 'none') toggleCollapse(id);
  if (display) display.style.display = 'none';
  if (form)    form.style.display    = '';
  if (ta)      ta.value = currentText;
  if (ta)      ta.focus();
}

function cancelEdit(id) {
  const display = document.getElementById('reply-display-' + id);
  const form    = document.getElementById('reply-form-'    + id);
  if (display) display.style.display = '';
  if (form)    form.style.display    = 'none';
}

async function togglePublish(id) {
  const r = await apiFetch(MSG_API, {action:'toggle_publish', id});
  if (r.error === 'session_expired') return;
  if (r.ok) {
    showToast(r.message || 'OK', 'success');
    if (r.html) replaceMsgRow(id, r.html);
  } else {
    showToast(r.error || 'Fehler', 'error');
  }
}

// FAQ-Text-Editor (anonymisierte Version der Frage) auf-/zuklappen
function toggleFaqEdit(id) {
  const body = document.getElementById('body-' + id);
  if (body && body.style.display === 'none') toggleCollapse(id);
  const box = document.getElementById('faq-edit-' + id);
  if (box) box.style.display = box.style.display === 'none' ? '' : 'none';
}

async function saveFaqQuestion(id) {
  const ta  = document.getElementById('faq-text-' + id);
  const rd  = document.getElementById('faq-edit-result-' + id);
  const txt = ta ? ta.value.trim() : '';
  if (!txt) { showToast('FAQ-Text darf nicht leer sein.', 'error'); return; }

  const r = await apiFetch(MSG_API, {action:'set_faq_question', id, question:txt});
  if (r.error === 'session_expired') return;
  if (r.ok) {
    showToast('FAQ-Text gespeichert.', 'success');
    if (r.html) replaceMsgRow(id, r.html);
  } else {
    if (rd) { rd.style.display=''; rd.innerHTML='<div class="alert alert--error" style="padding:.3rem .7rem;font-size:.82rem">'+escHtml(r.error||'Fehler')+'</div>'; }
  }
}

async function deleteMsg(id) {
  if (!confirm('Nachricht wirklich löschen?')) return;
  const r = await apiFetch(MSG_API, {action:'delete', id});
  if (r.error === 'session_expired') return;
  if (r.ok) {
    const el = document.getElementById('msg-' + id);
    if (el) { el.style.opacity='0'; el.style.transition='opacity .3s'; setTimeout(()=>el.remove(),320); }
    showToast('Gelöscht.', 'success');
  } else {
    showToast(r.error || 'Fehler', 'error');
  }
}

// ── Feedback: Bearbeitungsstatus setzen ──────────────────────
async function setStatus(id, status) {
  const r = await apiFetch(MSG_API, {action:'set_status', id, status});
  if (r.error === 'session_expired') return;
  if (r.ok) {
    showToast(r.message || 'Status gespeichert.', 'success');
    if (r.html) { replaceMsgRow(id, r.html); _applyTypeFilter(); }
  } else {
    showToast(r.error || 'Fehler', 'error');
  }
}

// ── Typ-Filter (client-seitig über data-type) ────────────────
let _typeFilter = 'all';
function filterType(type, btn) {
  _typeFilter = type;
  document.querySelectorAll('#type-filter button').forEach(b => {
    b.classList.toggle('btn--primary', b === btn);
    b.classList.toggle('btn--ghost',   b !== btn);
  });
  _applyTypeFilter();
}
function _applyTypeFilter() {
  document.querySelectorAll('#msg-list .msg-panel').forEach(p => {
    p.style.display = (_typeFilter === 'all' || p.dataset.type === _typeFilter) ? '' : 'none';
  });
}

// ── Feedback-API-Token verwalten ─────────────────────────────
async function generateApiToken(btn) {
  if (!confirm('Token generieren? Ein eventuell vorhandenes altes Token wird dadurch ungültig.')) return;
  btn.disabled = true;
  const r = await apiFetch(MSG_API, {action:'feedback_token_generate'});
  btn.disabled = false;
  if (r.error === 'session_expired') return;
  if (!r.ok) { showToast(r.error || 'Fehler', 'error'); return; }
  const box = document.getElementById('api-token-box');
  const val = document.getElementById('api-token-value');
  if (box && val) { val.textContent = r.token; box.style.display = ''; }
  const state = document.getElementById('api-token-state');
  if (state) { state.textContent = '🟢 Aktiv (Token gesetzt)'; state.className = 'tag tag--alive'; state.removeAttribute('style'); }
  btn.textContent = '🔄 Token neu generieren';
  showToast(r.message || 'Token generiert.', 'success');
}

async function clearApiToken(btn) {
  if (!confirm('Token wirklich entfernen? Die Feedback-API ist danach deaktiviert.')) return;
  const r = await apiFetch(MSG_API, {action:'feedback_token_clear'});
  if (r.error === 'session_expired') return;
  if (!r.ok) { showToast(r.error || 'Fehler', 'error'); return; }
  const state = document.getElementById('api-token-state');
  if (state) { state.textContent = '⚪ Deaktiviert (kein Token)'; state.className = 'tag'; state.style.background='var(--panel-bg)'; state.style.color='var(--text-dim)'; }
  const box = document.getElementById('api-token-box');
  if (box) box.style.display = 'none';
  btn.remove();
  showToast(r.message || 'Token entfernt.', 'success');
}

function copyApiToken(btn) {
  const val = document.getElementById('api-token-value');
  if (!val) return;
  navigator.clipboard?.writeText(val.textContent).then(
    () => showToast('Token kopiert.', 'success'),
    () => showToast('Kopieren fehlgeschlagen — bitte manuell markieren.', 'error')
  );
}

// ── Live-Update: neue Spielerfragen ohne Reload nachladen ─────
// Läuft über den gemeinsamen liveBlocks()-Helper (onData-only-Modus):
// Overlap-Guard, Tab-Pause und Intervall-Neustart kommen automatisch mit.
const msgPoll = liveBlocks({
  fetcher: () => apiFetch(MSG_API, {action:'get_new_messages', after_id:_maxMsgId}),
  countdownId: 'poll-countdown',
  onData: (r) => {
    if (!Array.isArray(r.rows)) return;
    if (r.rows.length) {
      document.getElementById('msg-list-empty')?.remove();
      const list = document.getElementById('msg-list');
      r.rows.forEach(row => {
        list.insertAdjacentHTML('afterbegin', row.html);
        if (row.id > _maxMsgId) _maxMsgId = row.id;
      });
      _applyTypeFilter(); // aktiver Filter gilt auch für frisch nachgeladene Zeilen
    }
    if (typeof r.pending === 'number') {
      const badge = document.getElementById('pending-badge');
      if (badge) badge.innerHTML = r.pending > 0
        ? '<span class="tag tag--running">' + r.pending + ' unbeantwortet</span> &middot; '
        : '';
    }
    if (typeof r.feedback_open === 'number') {
      const fb = document.getElementById('feedback-badge');
      if (fb) fb.innerHTML = r.feedback_open > 0
        ? '<span class="tag tag--night">' + r.feedback_open + ' offenes Feedback</span> &middot; '
        : '';
    }
  },
});
msgPoll.start();
JS;
require TEMPLATE_PATH . '/base_end.php';
?>
