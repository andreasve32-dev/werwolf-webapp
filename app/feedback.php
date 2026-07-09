<?php
// Copyright (c) 2026 Andreas Vetter
// Feedback-Seite: Spieler melden Bugs, äußern Wünsche oder geben allgemeines
// Feedback. Aufsatz auf das Nachrichtensystem (messages-Tabelle, type/status) —
// der Spielleiter verwaltet die Einträge in admin/messages.php.
require_once dirname(__DIR__) . '/core/bootstrap.php';
Auth::requireLogin();

$page = ['title' => 'Feedback & Wünsche'];
require TEMPLATE_PATH . '/base.php';
?>

<div class="container page-wrap">

  <div class="page-header">
    <span class="page-header__icon">📣</span>
    <h1>Feedback &amp; Wünsche</h1>
    <p class="page-header__sub">
      Bug gefunden? Idee für ein neues Feature? Oder einfach Lob &amp; Kritik?
      Hier landet alles direkt beim Spielleiter.
    </p>
  </div>

  <!-- ── Neuer Eintrag ─────────────────────────────────────── -->
  <div class="card card--glow animate-in">
    <div class="flex gap-xs" style="flex-wrap:wrap;margin-bottom:.75rem" id="fb-type-row">
      <button class="btn btn--primary btn--sm" data-type="bug"      onclick="fbPickType('bug', this)">🐛 Bug melden</button>
      <button class="btn btn--ghost btn--sm"   data-type="wish"     onclick="fbPickType('wish', this)">💡 Wunsch äußern</button>
      <button class="btn btn--ghost btn--sm"   data-type="feedback" onclick="fbPickType('feedback', this)">💬 Feedback geben</button>
    </div>
    <textarea class="form-input" id="fb-text" rows="4" maxlength="1000"
              placeholder="🐛 Was funktioniert nicht? Beschreibe möglichst genau, was du gemacht hast und was passiert ist …"
              style="width:100%;resize:vertical;font-size:16px"></textarea>
    <div class="flex-between" style="flex-wrap:wrap;gap:.5rem;margin-top:.6rem">
      <span class="text-dim text-xs"><span id="fb-count">0</span>/1000 Zeichen</span>
      <button class="btn btn--primary" id="fb-send" onclick="fbSend()">📨 Absenden</button>
    </div>
    <div id="fb-result" style="display:none;margin-top:.6rem"></div>
  </div>

  <!-- ── Eigene Einträge ───────────────────────────────────── -->
  <div class="card animate-in" style="margin-top:1.25rem">
    <div class="section-title" style="font-family:var(--font-display);color:var(--text-bright);margin-bottom:.75rem">
      📋 Deine Einträge
    </div>
    <div id="fb-list">
      <div class="flex-center" style="padding:1.5rem"><div class="spinner"></div></div>
    </div>
  </div>

</div>

<?php
$page['inline_js'] = sprintf('const MSG_API = %s;', json_encode(API_URL . '/messages.php'));
$page['inline_js'] .= <<<'JS'

// ── Typ-Auswahl ──────────────────────────────────────────────
const FB_TYPES = {
  bug:      {icon:'🐛', label:'Bug',      ph:'🐛 Was funktioniert nicht? Beschreibe möglichst genau, was du gemacht hast und was passiert ist …'},
  wish:     {icon:'💡', label:'Wunsch',   ph:'💡 Was wünschst du dir? Beschreibe deine Idee — je konkreter, desto besser …'},
  feedback: {icon:'💬', label:'Feedback', ph:'💬 Was gefällt dir, was nicht? Ehrliche Meinung erwünscht …'},
};
const FB_STATUS = {
  open:        {icon:'🔴', label:'Offen'},
  in_progress: {icon:'🟡', label:'In Arbeit'},
  done:        {icon:'🟢', label:'Erledigt'},
};
let _fbType = 'bug';

function fbPickType(type, btn) {
  _fbType = type;
  document.querySelectorAll('#fb-type-row button').forEach(b => {
    b.classList.toggle('btn--primary', b === btn);
    b.classList.toggle('btn--ghost',   b !== btn);
  });
  const ta = document.getElementById('fb-text');
  if (ta) ta.placeholder = FB_TYPES[type].ph;
}

// Zeichenzähler
document.getElementById('fb-text').addEventListener('input', function () {
  document.getElementById('fb-count').textContent = this.value.length;
});

// ── Absenden ─────────────────────────────────────────────────
async function fbSend() {
  const ta  = document.getElementById('fb-text');
  const btn = document.getElementById('fb-send');
  const res = document.getElementById('fb-result');
  const txt = ta.value.trim();
  if (txt.length < 3) { showToast('Bitte etwas mehr schreiben (min. 3 Zeichen).', 'error'); return; }
  btn.disabled = true;
  const r = await apiFetch(MSG_API, {action:'send_feedback', type:_fbType, message:txt});
  btn.disabled = false;
  if (r.error === 'session_expired') return;
  if (r.ok) {
    ta.value = '';
    document.getElementById('fb-count').textContent = '0';
    res.style.display = '';
    res.innerHTML = '<div class="alert alert--success">✓ ' + escHtml(r.message || 'Gespeichert!') + '</div>';
    setTimeout(() => { res.style.display = 'none'; }, 4000);
    fbPoll.refreshNow(); // Liste sofort aktualisieren
  } else {
    res.style.display = '';
    res.innerHTML = '<div class="alert alert--error">' + escHtml(r.error || 'Fehler beim Speichern.') + '</div>';
  }
}

// ── Eigene Einträge (Live-Update über liveBlocks) ────────────
let _fbLastHtml = null;

function fbRender(entries) {
  if (!entries.length) {
    return '<p class="text-dim text-center" style="padding:1.5rem;font-size:.9rem">Noch keine Einträge — dein erster Eintrag erscheint hier.</p>';
  }
  return entries.map(e => {
    const t = FB_TYPES[e.type]    || FB_TYPES.feedback;
    const s = FB_STATUS[e.status] || FB_STATUS.open;
    const date = new Date(e.created_at).toLocaleString('de-DE', {day:'2-digit', month:'2-digit', year:'numeric', hour:'2-digit', minute:'2-digit'});
    const replyVoiceHtml = e.reply_voice_path
      ? `<audio controls preload="none" style="width:100%;max-width:300px;margin-top:.4rem;display:block"
                src="${MSG_API}?action=voice_file&which=reply&id=${parseInt(e.id,10)}"
                onerror="this.style.display='none'"></audio>`
      : '';
    const replyHtml = (e.reply || e.reply_voice_path)
      ? `<div style="border-left:2px solid var(--accent-border);padding:.5rem .75rem;margin-top:.5rem;
                     font-size:.85rem;line-height:1.5;background:var(--panel-bg);border-radius:0 6px 6px 0">
           <div class="text-dim text-xs mb-1">Antwort des Spielleiters${e.reply_voice_path ? ' · 🎙️ Sprachantwort' : ''}</div>
           ${e.reply ? `<span style="color:var(--text-bright)">${escHtml(e.reply)}</span>` : ''}
           ${replyVoiceHtml}
         </div>`
      : '';
    return `<div style="padding:.75rem 0;border-bottom:1px solid var(--border)">
      <div class="flex gap-sm" style="align-items:center;flex-wrap:wrap;margin-bottom:.35rem">
        <span class="tag tag--night" style="font-size:.65rem">${t.icon} ${t.label}</span>
        <span class="tag" style="font-size:.65rem;background:var(--panel-bg);color:var(--text-dim)">${s.icon} ${s.label}</span>
        <span class="text-dim text-xs">${escHtml(date)}</span>
      </div>
      <div style="font-size:.9rem;line-height:1.5">${escHtml(e.message)}</div>
      ${replyHtml}
    </div>`;
  }).join('');
}

const fbPoll = liveBlocks({
  fetcher: () => apiFetch(MSG_API, {action:'get_my_feedback'}),
  countdownId: 'poll-countdown',
  onData: (r) => {
    if (!Array.isArray(r.entries)) return;
    const html = fbRender(r.entries);
    if (html === _fbLastHtml) return; // kein DOM-Umbau ohne Änderung
    _fbLastHtml = html;
    document.getElementById('fb-list').innerHTML = html;
  },
});
fbPoll.start();
JS;
require TEMPLATE_PATH . '/base_end.php';
?>
