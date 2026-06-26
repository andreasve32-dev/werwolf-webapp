<?php
// Copyright (c) 2026 Andreas Vetter
require_once dirname(__DIR__) . '/core/bootstrap.php';
Auth::requireAdmin();

$messages = Database::query(
    "SELECT m.id, m.message, m.reply, m.created_at, m.replied_at, m.read_by_player, m.published,
            p.display_name, p.username
     FROM messages m
     JOIN players p ON p.id = m.player_id
     ORDER BY (m.reply IS NULL) DESC, m.created_at DESC"
);
$pending = count(array_filter($messages, fn($m) => $m['reply'] === null));

$page = ['title' => 'Spielerfragen'];
require TEMPLATE_PATH . '/base.php';
?>

<div class="container page-wrap">

  <div class="page-header">
    <span class="page-header__icon">✉️</span>
    <h1>Spielerfragen</h1>
    <p class="page-header__sub">
      <?php if ($pending > 0): ?>
        <span class="tag tag--running"><?= $pending ?> unbeantwortet</span>
        &middot;
      <?php endif; ?>
      <a href="<?= APP_URL ?>/admin/">← zurück zur Spielleitung</a>
    </p>
  </div>

  <?php if (empty($messages)): ?>
  <div class="card card--glow animate-in">
    <p class="text-dim text-center" style="padding:2rem">
      Noch keine Nachrichten von Spielern.
    </p>
  </div>
  <?php else: ?>
  <div class="card card--glow animate-in">
    <div id="msg-list" style="display:flex;flex-direction:column;gap:.75rem">
      <?php foreach ($messages as $msg): ?>
      <?php $isNew = ($msg['reply'] === null); ?>
      <div class="panel msg-panel" id="msg-<?= (int)$msg['id'] ?>"
           style="padding:.9rem 1rem<?= $isNew ? ';border-left:3px solid var(--accent)' : ';opacity:.75' ?>">

        <!-- Kopfzeile: Name + Zeit + Badge + Aktionen + Chevron -->
        <div class="flex-between" style="flex-wrap:wrap;gap:.4rem;<?= !$isNew ? 'cursor:pointer' : '' ?>"
             <?= !$isNew ? 'onclick="toggleCollapse(' . (int)$msg['id'] . ')" title="Auf-/Zuklappen"' : '' ?>>
          <div class="flex gap-sm" style="align-items:center;flex-wrap:wrap">
            <span style="font-family:var(--font-display);font-size:.92rem;color:var(--text-bright)">
              <?= e($msg['display_name']) ?>
            </span>
            <span class="text-dim text-xs">
              <?= e(date('d.m.Y H:i', strtotime($msg['created_at']))) ?>
            </span>
            <?php if ($isNew): ?>
              <span class="tag tag--running" style="font-size:.65rem">Neu</span>
            <?php else: ?>
              <span class="tag" style="font-size:.65rem;background:var(--panel-bg);color:var(--text-dim)">✓ Beantwortet</span>
            <?php endif; ?>
            <?php if ($msg['published']): ?>
              <span class="tag tag--alive" style="font-size:.65rem" id="pub-tag-<?= (int)$msg['id'] ?>">📢 Im FAQ</span>
            <?php else: ?>
              <span id="pub-tag-<?= (int)$msg['id'] ?>" style="display:none" class="tag tag--alive" style="font-size:.65rem">📢 Im FAQ</span>
            <?php endif; ?>
            <!-- Vorschau der Frage wenn zugeklappt -->
            <?php if (!$isNew): ?>
            <span class="msg-preview text-dim" id="preview-<?= (int)$msg['id'] ?>"
                  style="font-size:.8rem;max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
              <?= e(mb_substr($msg['message'], 0, 80)) ?>
            </span>
            <?php endif; ?>
          </div>
          <div class="flex gap-xs" onclick="event.stopPropagation()">
            <?php if (!$isNew): ?>
            <button class="btn btn--ghost btn--sm" id="pub-btn-<?= (int)$msg['id'] ?>"
                    title="<?= $msg['published'] ? 'Aus FAQ entfernen' : 'Als FAQ veröffentlichen' ?>"
                    onclick="togglePublish(<?= (int)$msg['id'] ?>, <?= $msg['published'] ? 'true' : 'false' ?>)">
              <?= $msg['published'] ? '📢 Veröffentlicht' : '📢 FAQ freigeben' ?>
            </button>
            <?php endif; ?>
            <button class="btn btn--ghost btn--sm" title="Löschen"
                    onclick="deleteMsg(<?= (int)$msg['id'] ?>)">🗑</button>
            <?php if (!$isNew): ?>
            <span class="msg-chevron" id="chevron-<?= (int)$msg['id'] ?>"
                  style="font-size:.8rem;color:var(--text-dim);padding:.1rem .2rem;line-height:1;transition:transform .2s;display:inline-block;transform:rotate(0deg)">▼</span>
            <?php endif; ?>
          </div>
        </div>

        <!-- Aufklappbarer Inhalt -->
        <div id="body-<?= (int)$msg['id'] ?>"
             style="<?= !$isNew ? 'display:none;' : '' ?>margin-top:.75rem">

          <!-- Frage des Spielers -->
          <div style="background:var(--panel-bg);border:1px solid var(--border);border-radius:8px;
                      padding:.6rem .85rem;margin-bottom:.6rem;font-size:.9rem;line-height:1.5">
            <?= e($msg['message']) ?>
          </div>

          <!-- Antwort-Bereich -->
          <div id="reply-display-<?= (int)$msg['id'] ?>"
               style="<?= $isNew ? 'display:none' : '' ?>">
            <?php if ($msg['reply']): ?>
            <div style="background:var(--input-bg,var(--card-bg));border-left:3px solid var(--accent-border);
                        border-radius:0 8px 8px 0;padding:.6rem .85rem;font-size:.88rem;line-height:1.5">
              <div class="text-dim text-xs mb-1">
                Deine Antwort &middot; <?= e(date('d.m.Y H:i', strtotime($msg['replied_at']))) ?>
              </div>
              <p style="margin:0;color:var(--text-bright)" id="reply-text-display-<?= (int)$msg['id'] ?>">
                <?= e($msg['reply']) ?>
              </p>
              <button class="btn btn--ghost btn--sm mt-1"
                      onclick="openEditReply(<?= (int)$msg['id'] ?>, <?= htmlspecialchars(json_encode($msg['reply']), ENT_QUOTES) ?>)">
                ✏️ Bearbeiten
              </button>
            </div>
            <?php endif; ?>
          </div>

          <div id="reply-form-<?= (int)$msg['id'] ?>"
               style="<?= $isNew ? '' : 'display:none' ?>">
            <textarea class="form-input"
                      id="reply-text-<?= (int)$msg['id'] ?>"
                      placeholder="Antwort eingeben …" rows="2"
                      style="width:100%;font-size:.85rem;resize:vertical;margin-bottom:.4rem"
                      maxlength="1000"></textarea>
            <div class="flex gap-xs">
              <button class="btn btn--primary btn--sm"
                      onclick="sendReply(<?= (int)$msg['id'] ?>)">✓ Antworten</button>
              <?php if (!$isNew): ?>
              <button class="btn btn--ghost btn--sm"
                      onclick="cancelEdit(<?= (int)$msg['id'] ?>)">Abbrechen</button>
              <?php endif; ?>
            </div>
            <div id="reply-result-<?= (int)$msg['id'] ?>" style="display:none;margin-top:.4rem"></div>
          </div>

        </div><!-- /body -->

      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

</div>

<?php
$page['inline_js'] = sprintf('const MSG_API = %s;', json_encode(API_URL . '/messages.php'));
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

async function sendReply(id) {
  const ta  = document.getElementById('reply-text-' + id);
  const rd  = document.getElementById('reply-result-' + id);
  const txt = ta ? ta.value.trim() : '';
  if (!txt) { showToast('Antwort darf nicht leer sein.', 'error'); return; }

  const r = await apiFetch(MSG_API, {action:'reply', id, reply:txt});
  if (r.error === 'session_expired') return;
  if (r.ok) {
    showToast('Antwort gesendet.', 'success');
    setTimeout(() => location.reload(), 800);
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
JS;
require TEMPLATE_PATH . '/base_end.php';
?>
