<?php
// Copyright (c) 2026 Andreas Vetter
require_once dirname(__DIR__) . '/core/bootstrap.php';
require_once TEMPLATE_PATH . '/messages_blocks.php';
Auth::requireAdmin();

$messages = Database::query(
    "SELECT m.id, m.message, m.faq_question, m.voice_path, m.reply, m.created_at, m.replied_at, m.read_by_player, m.published,
            p.display_name, p.username
     FROM messages m
     JOIN players p ON p.id = m.player_id
     ORDER BY (m.reply IS NULL) DESC, m.created_at DESC"
);
$pending  = count(array_filter($messages, fn($m) => $m['reply'] === null));
$maxMsgId = $messages ? max(array_column($messages, 'id')) : 0;

$page = ['title' => 'Spielerfragen'];
require TEMPLATE_PATH . '/base.php';
?>

<div class="container page-wrap">

  <div class="page-header">
    <span class="page-header__icon">✉️</span>
    <h1>Spielerfragen</h1>
    <p class="page-header__sub">
      <span id="pending-badge"><?php if ($pending > 0): ?><span class="tag tag--running"><?= $pending ?> unbeantwortet</span> &middot; <?php endif; ?></span>
      <a href="<?= APP_URL ?>/admin/">← zurück zur Spielleitung</a>
    </p>
  </div>

  <div class="flex" style="justify-content:flex-end;margin-bottom:.6rem">
    <button class="btn btn--ghost btn--sm" onclick="cleanupVoices(this)"
            title="Sprachaufnahmen löschen, die zu keiner Nachricht mehr gehören">
      🧹 Verwaiste Aufnahmen aufräumen
    </button>
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

async function transcribeVoice(id, btn) {
  const original = btn.textContent;
  btn.disabled = true;
  btn.textContent = '⏳ Transkribiere …';
  const r = await apiFetch(MSG_API, {action:'transcribe_voice', id});
  if (r.error === 'session_expired') return;
  if (r.ok) {
    showToast(r.message || 'Transkribiert.', 'success');
    if (r.html) replaceMsgRow(id, r.html);
    toggleFaqEdit(id); // Textfassung direkt zum Gegenlesen/Anpassen aufklappen
  } else {
    showToast(r.error || 'Fehler', 'error');
    btn.disabled = false;
    btn.textContent = original;
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

async function cleanupVoices(btn) {
  const original = btn.textContent;
  btn.disabled = true; btn.textContent = '⏳ Räume auf …';
  const r = await apiFetch(MSG_API, {action:'cleanup_voice'});
  if (r.error === 'session_expired') return;
  showToast(r.ok ? (r.message || 'Aufgeräumt.') : (r.error || 'Fehler'), r.ok ? 'success' : 'error');
  btn.disabled = false; btn.textContent = original;
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
    }
    if (typeof r.pending === 'number') {
      const badge = document.getElementById('pending-badge');
      if (badge) badge.innerHTML = r.pending > 0
        ? '<span class="tag tag--running">' + r.pending + ' unbeantwortet</span> &middot; '
        : '';
    }
  },
});
msgPoll.start();
JS;
require TEMPLATE_PATH . '/base_end.php';
?>
