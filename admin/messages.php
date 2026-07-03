<?php
// Copyright (c) 2026 Andreas Vetter
require_once dirname(__DIR__) . '/core/bootstrap.php';
require_once TEMPLATE_PATH . '/messages_blocks.php';
Auth::requireAdmin();

$messages = Database::query(
    "SELECT m.id, m.message, m.reply, m.created_at, m.replied_at, m.read_by_player, m.published,
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

function _replyDisplayFragment(id, text) {
  const now = new Date().toLocaleString('de-DE', {day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'});
  const wrap = document.createElement('div');
  wrap.style.cssText = 'background:var(--input-bg,var(--card-bg));border-left:3px solid var(--accent-border);' +
    'border-radius:0 8px 8px 0;padding:.6rem .85rem;font-size:.88rem;line-height:1.5';
  wrap.innerHTML =
    '<div class="text-dim text-xs mb-1">Deine Antwort &middot; ' + escHtml(now) + '</div>' +
    '<p style="margin:0;color:var(--text-bright)" id="reply-text-display-' + id + '">' + escHtml(text) + '</p>' +
    '<button type="button" class="btn btn--ghost btn--sm mt-1" id="edit-reply-btn-' + id + '">✏️ Bearbeiten</button>';
  const editBtn = wrap.querySelector('#edit-reply-btn-' + id);
  if (editBtn) editBtn.addEventListener('click', () => openEditReply(id, text));
  return wrap;
}

function _applyReplyToDom(id, text) {
  const panel   = document.getElementById('msg-' + id);
  const display = document.getElementById('reply-display-' + id);
  const form    = document.getElementById('reply-form-' + id);

  if (display) {
    display.innerHTML = '';
    display.appendChild(_replyDisplayFragment(id, text));
    display.style.display = '';
  }
  if (form) form.style.display = 'none';
  if (panel) { panel.style.borderLeft = 'none'; panel.style.opacity = '.75'; }

  const header = panel ? panel.querySelector('.flex-between') : null;
  const newTag = header ? header.querySelector('.tag--running') : null;
  if (newTag) {
    newTag.className = 'tag';
    newTag.style.background = 'var(--panel-bg)';
    newTag.style.color = 'var(--text-dim)';
    newTag.style.fontSize = '.65rem';
    newTag.textContent = '✓ Beantwortet';
  }
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
    _applyReplyToDom(id, txt);
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

async function togglePublish(id, isPublished) {
  const r = await apiFetch(MSG_API, {action:'toggle_publish', id});
  if (r.error === 'session_expired') return;
  if (r.ok) {
    const btn = document.getElementById('pub-btn-' + id);
    const tag = document.getElementById('pub-tag-' + id);
    const nowPublished = r.published === 1;
    if (btn) { btn.textContent = nowPublished ? '📢 Veröffentlicht' : '📢 FAQ freigeben'; }
    if (tag) { tag.style.display = nowPublished ? '' : 'none'; }
    showToast(r.message || 'OK', 'success');
  } else {
    showToast(r.error || 'Fehler', 'error');
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
