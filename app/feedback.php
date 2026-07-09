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

    <?php if (VOICE_MESSAGES): ?>
    <!-- Umschalter Text / Sprachnachricht (Sprach-Tab blendet sich per JS aus,
         falls der Browser kein MediaRecorder kann — wie im Frage-Modal) -->
    <div class="flex gap-xs" id="fb-mode-tabs" style="margin-bottom:.75rem">
      <button class="btn btn--sm btn--primary" style="flex:1" id="fb-tab-text"  onclick="fbMode('text')">📝 Text</button>
      <button class="btn btn--sm btn--ghost"   style="flex:1" id="fb-tab-voice" onclick="fbMode('voice')">🎙️ Sprachnachricht</button>
    </div>
    <?php endif; ?>

    <div id="fb-area-text">
      <textarea class="form-input" id="fb-text" rows="4" maxlength="1000"
                placeholder="🐛 Was funktioniert nicht? Beschreibe möglichst genau, was du gemacht hast und was passiert ist …"
                style="width:100%;resize:vertical;font-size:16px"></textarea>
      <div class="flex-between" style="flex-wrap:wrap;gap:.5rem;margin-top:.6rem">
        <span class="text-dim text-xs"><span id="fb-count">0</span>/1000 Zeichen</span>
        <button class="btn btn--primary" id="fb-send" onclick="fbSend()">📨 Absenden</button>
      </div>
    </div>

    <?php if (VOICE_MESSAGES): ?>
    <div id="fb-area-voice" style="display:none">
      <p class="text-dim text-sm" style="margin-bottom:.75rem;line-height:1.5">
        Max. <strong>1 Minute</strong>. Deine Aufnahme geht direkt an den Spielleiter —
        sprich einfach ein, was du melden oder dir wünschen möchtest.
      </p>
      <div class="flex-center" style="flex-direction:column;gap:.6rem;margin-bottom:.75rem">
        <button class="btn btn--primary btn--full" id="fb-rec-btn" onclick="fbToggleRecording()">🔴 Aufnahme starten</button>
        <div id="fb-timer" class="text-dim" style="display:none;font-family:var(--font-display);font-size:1.1rem">0:00 / 1:00</div>
        <audio id="fb-preview" controls style="display:none;width:100%"></audio>
      </div>
      <div class="flex" style="justify-content:flex-end">
        <button class="btn btn--primary" id="fb-voice-send" onclick="fbSendVoice()" disabled>📨 Sprachnachricht absenden</button>
      </div>
    </div>
    <?php endif; ?>

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
  accepted:    {icon:'👍', label:'Angenommen'},
  in_progress: {icon:'🟡', label:'In Arbeit'},
  done:        {icon:'🟢', label:'Erledigt'},
  rejected:    {icon:'🚫', label:'Abgelehnt'},
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

// ── Sprachnachricht (MediaRecorder, max. 1 Min.) ─────────────
const FB_REC_MAX_SECS = 60;
let _fbRec = {rec:null, chunks:[], blob:null, timer:null, start:0, stream:null};

// Sprach-Tab ausblenden, wenn der Browser keine Aufnahme kann
if (!window.MediaRecorder || !navigator.mediaDevices?.getUserMedia) {
  const tabs = document.getElementById('fb-mode-tabs');
  if (tabs) tabs.style.display = 'none';
}

function fbMode(mode) {
  const isVoice = mode === 'voice';
  document.getElementById('fb-area-text').style.display  = isVoice ? 'none' : '';
  const va = document.getElementById('fb-area-voice');
  if (va) va.style.display = isVoice ? '' : 'none';
  const tt = document.getElementById('fb-tab-text'), tv = document.getElementById('fb-tab-voice');
  if (tt) { tt.classList.toggle('btn--primary', !isVoice); tt.classList.toggle('btn--ghost', isVoice); }
  if (tv) { tv.classList.toggle('btn--primary', isVoice);  tv.classList.toggle('btn--ghost', !isVoice); }
  if (!isVoice) fbRecReset();
}

function fbPickMime() {
  for (const m of ['audio/webm;codecs=opus','audio/webm','audio/mp4']) {
    if (MediaRecorder.isTypeSupported?.(m)) return m;
  }
  return '';
}

async function fbToggleRecording() {
  if (_fbRec.rec && _fbRec.rec.state === 'recording') { fbStopRecording(); return; }
  try { _fbRec.stream = await navigator.mediaDevices.getUserMedia({audio:true}); }
  catch(e) { showToast('Mikrofon-Zugriff verweigert.', 'error'); return; }
  _fbRec.chunks = []; _fbRec.blob = null;
  const mime = fbPickMime();
  try { _fbRec.rec = new MediaRecorder(_fbRec.stream, mime ? {mimeType:mime} : undefined); }
  catch(e) { _fbRec.rec = new MediaRecorder(_fbRec.stream); }
  _fbRec.rec.ondataavailable = e => { if (e.data && e.data.size > 0) _fbRec.chunks.push(e.data); };
  _fbRec.rec.onstop = () => {
    _fbRec.stream?.getTracks().forEach(t => t.stop()); _fbRec.stream = null;
    if (!_fbRec.chunks.length) return;
    _fbRec.blob = new Blob(_fbRec.chunks, {type:_fbRec.rec.mimeType || 'audio/webm'});
    const prev = document.getElementById('fb-preview');
    if (prev) { prev.src = URL.createObjectURL(_fbRec.blob); prev.style.display = ''; }
    document.getElementById('fb-voice-send').disabled = false;
    document.getElementById('fb-rec-btn').textContent = '🔄 Neu aufnehmen';
  };
  _fbRec.rec.start(); _fbRec.start = Date.now();
  document.getElementById('fb-rec-btn').textContent = '⏹ Aufnahme beenden';
  document.getElementById('fb-voice-send').disabled = true;
  const prev = document.getElementById('fb-preview'); if (prev) prev.style.display = 'none';
  const t = document.getElementById('fb-timer'); t.style.display = '';
  clearInterval(_fbRec.timer);
  _fbRec.timer = setInterval(() => {
    const s = Math.floor((Date.now() - _fbRec.start) / 1000);
    t.textContent = `${Math.floor(s/60)}:${String(s%60).padStart(2,'0')} / 1:00`;
    if (s >= FB_REC_MAX_SECS) fbStopRecording();
  }, 250);
}

function fbStopRecording() {
  clearInterval(_fbRec.timer); _fbRec.timer = null;
  if (_fbRec.rec && _fbRec.rec.state === 'recording') { try { _fbRec.rec.stop(); } catch(e){} }
}

function fbRecReset() {
  fbStopRecording();
  _fbRec.stream?.getTracks().forEach(t => t.stop());
  _fbRec = {rec:null, chunks:[], blob:null, timer:null, start:0, stream:null};
  const rec = document.getElementById('fb-rec-btn');   if (rec) rec.textContent = '🔴 Aufnahme starten';
  const t   = document.getElementById('fb-timer');     if (t) { t.style.display = 'none'; t.textContent = '0:00 / 1:00'; }
  const prev= document.getElementById('fb-preview');   if (prev) { prev.style.display = 'none'; prev.removeAttribute('src'); }
  const send= document.getElementById('fb-voice-send'); if (send) send.disabled = true;
}

async function fbSendVoice() {
  if (!_fbRec.blob) { showToast('Bitte zuerst eine Aufnahme machen.', 'error'); return; }
  const btn = document.getElementById('fb-voice-send');
  const res = document.getElementById('fb-result');
  btn.disabled = true;
  const fd = new FormData();
  fd.append('action', 'send_feedback_voice');
  fd.append('type', _fbType);
  const ext = _fbRec.blob.type.includes('mp4') ? 'm4a' : (_fbRec.blob.type.includes('ogg') ? 'ogg' : 'webm');
  fd.append('voice', _fbRec.blob, 'feedback.' + ext);
  try {
    const r = await (await fetch(MSG_API, {method:'POST', body:fd})).json();
    if (r.ok) {
      fbRecReset();
      res.style.display = '';
      res.innerHTML = '<div class="alert alert--success">✓ ' + escHtml(r.message || 'Gespeichert!') + '</div>';
      setTimeout(() => { res.style.display = 'none'; }, 4000);
      fbPoll.refreshNow();
    } else {
      showToast(r.error || 'Fehler beim Senden.', 'error');
      btn.disabled = false;
    }
  } catch(e) {
    showToast('Netzwerkfehler beim Senden.', 'error');
    btn.disabled = false;
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
    // Gelesen-Bestätigung (Ticket-Prinzip): sobald der Spielleiter die Verwaltung
    // geöffnet hat, gilt der Eintrag als gelesen. Ein geänderter Status impliziert
    // das Lesen bereits — der Chip erscheint deshalb nur solange der Status "Offen" ist.
    const readChip = (e.status === 'open')
      ? (parseInt(e.read_by_admin, 10)
          ? '<span class="tag" style="font-size:.65rem;background:var(--panel-bg);color:var(--text-dim)">👁 Gelesen</span>'
          : '<span class="tag" style="font-size:.65rem;background:var(--panel-bg);color:var(--text-dim)">🕐 Noch ungelesen</span>')
      : '';
    // Eigene Sprachnachricht abspielbar (onerror fängt fehlende/defekte Datei ab)
    const bodyHtml = e.voice_path
      ? `<audio controls preload="none" style="width:100%;max-width:320px"
                src="${MSG_API}?action=voice_file&id=${parseInt(e.id,10)}"
                onerror="this.style.display='none';this.nextElementSibling.style.display=''"></audio>
         <span class="text-dim text-xs" style="display:none">⚠️ Aufnahme nicht mehr abspielbar.</span>`
      : `<div style="font-size:.9rem;line-height:1.5">${escHtml(e.message)}</div>`;
    return `<div style="padding:.75rem 0;border-bottom:1px solid var(--border)">
      <div class="flex gap-sm" style="align-items:center;flex-wrap:wrap;margin-bottom:.35rem">
        <span class="tag tag--night" style="font-size:.65rem">${t.icon} ${t.label}</span>
        <span class="tag" style="font-size:.65rem;background:var(--panel-bg);color:var(--text-dim)">${s.icon} ${s.label}</span>
        ${readChip}
        <span class="text-dim text-xs">${escHtml(date)}${e.voice_path ? ' · 🎙️ Sprachnachricht' : ''}</span>
      </div>
      ${bodyHtml}
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
