// Copyright (c) 2026 Andreas Vetter
/**
 * ============================================================
 *  WERWOLF — app.js
 *  Globale JS-Utilities: API, Toast, LocalStorage, Theme
 * ============================================================
 *  Hinweis: Rollen-Namen/Icons werden NICHT mehr hier hartcodiert.
 *  Sie kommen live aus der Datenbank (Tabelle `roles`) über die
 *  API-Felder role_name / role_icon_url / role_sichtbar.
 * ============================================================
 */

// ── URL-Masking (zeigt nur die Domain, versteckt den Pfad) ───
// Echte URL zuerst sichern — wird für Theme-Wechsel benötigt.
const _pageUrl = window.location.href;
if (window.history && window.history.replaceState && !_pageUrl.includes('/admin')) {
  history.replaceState(null, document.title, '/');
}

// ── LocalStorage ─────────────────────────────────────────────
const LS = {
  get(k)  { try{return JSON.parse(localStorage.getItem(k))}catch{return null} },
  set(k,v){ try{localStorage.setItem(k,JSON.stringify(v))}catch{} },
  del(k)  { try{localStorage.removeItem(k)}catch{} },
  /** Spieler-Objekt aus Cookie in localStorage synchronisieren */
  syncPlayer() {
    const c=document.cookie.split(';').find(s=>s.trim().startsWith('ww_player='));
    if(c){
      try{
        const val=decodeURIComponent(c.split('=').slice(1).join('='));
        localStorage.setItem('ww_player',val);
      }catch{}
    }
  },
  getPlayer() { return this.get('ww_player'); },
  getTheme()  { return this.get('ww_theme') || 'gothic'; },
  setTheme(t) { this.set('ww_theme',t); },
};

// ── API ──────────────────────────────────────────────────────
async function apiFetch(url, body={}) {
  try {
    const res = await fetch(url, {
      method:'POST',
      headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},
      body:JSON.stringify(body)
    });
    if (res.status === 401) {
      showToast('Sitzung abgelaufen — bitte neu anmelden.', 'error', 3000);
      // /logout.php clears the session before redirecting to login,
      // preventing an infinite loop when the session cookie is still
      // valid but the player no longer exists in the database.
      setTimeout(() => { window.location.href = '/app/logout.php'; }, 2000);
      return {error:'session_expired'};
    }
    return await res.json();
  } catch(e) {
    console.error('[API]', e);
    return {error:'Netzwerkfehler'};
  }
}

// ── Toast ────────────────────────────────────────────────────
function showToast(msg, type='info', ms=3500) {
  let box = document.getElementById('toast-container');
  if(!box){ box=document.createElement('div'); box.id='toast-container'; document.body.appendChild(box); }
  const t = document.createElement('div');
  t.className=`toast toast--${type}`;
  t.textContent=msg;
  box.appendChild(t);
  setTimeout(()=>{ t.style.opacity='0'; t.style.transition='opacity .3s'; setTimeout(()=>t.remove(),350); },ms);
}

// ── HTML-Escape ──────────────────────────────────────────────
function escHtml(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
                  .replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}

// ── Countdown-Anzeige "nächste Aktualisierung in Xs" ──────────
// Wiederverwendbar von liveBlocks() und von handgerollten Poll-Loops
// (z.B. game.php's loadPlayers()-Intervall).
function pollCountdown(elementId) {
  const el = document.getElementById(elementId);
  let remaining = 0, timer = null;

  function render() {
    // Kurzformat, damit die Anzeige auch in der schmalen Handy-Navigation Platz hat
    if (el) el.textContent = remaining > 0 ? ('🔄 ' + remaining + 's') : '🔄 …';
  }
  function tick() {
    if (remaining > 0) remaining--;
    render();
  }
  function start() {
    if (timer) clearInterval(timer);
    timer = setInterval(tick, 1000);
  }
  function stop() {
    if (timer) clearInterval(timer);
    timer = null;
  }
  function reset(intervalMs) {
    remaining = Math.ceil(intervalMs / 1000);
    render();
    if (!timer) start();
  }

  return { reset, start, stop };
}

// ── Live-Block Polling (fetch-basiert, kein Seitenreload) ─────
// Ohne config.interval wird das global in den Spieler-Einstellungen gewählte
// Ladeintervall (ww_poll_interval) verwendet — und bei Änderung der Einstellung
// live neu gestartet (siehe restartAllLiveBlocks() in nav.php).
function liveBlocks(config) {
  let timer = null, inFlight = false, running = false;
  let serverHash = null;        // Hash der zuletzt empfangenen Blocks (Server sendet
                                // bei unverändertem Inhalt nur {hash} ohne blocks)
  const hiddenByUs = new Set(); // Blöcke, deren display:none WIR gesetzt haben
  const lastHtml   = new Map(); // Block-Key → zuletzt angewendeter HTML-String
  const countdown = config.countdownId ? pollCountdown(config.countdownId) : null;

  function currentInterval() {
    return config.interval || parseInt(localStorage.getItem('ww_poll_interval') || '6000', 10) || 6000;
  }

  async function tick() {
    if (inFlight) return; // Overlap-Guard: Tick überspringen, falls voriger Request noch läuft
    inFlight = true;
    if (countdown) countdown.reset(currentInterval());
    try {
      const data = await config.fetcher(serverHash);
      if (!data || data.error) return;
      if (data.hash !== undefined) serverHash = data.hash;
      // "Änderung" = neue Blocks angekommen, ODER Endpunkt ohne Hash/Blocks
      // (reine onData-Nutzung wie Badge-Polls). Hash-Antwort ohne Blocks
      // bedeutet: Inhalt unverändert → weder DOM noch onData anfassen.
      let changed = !data.blocks && data.hash === undefined;
      if (data.blocks) {
        for (const [key, id] of Object.entries(config.targets || {})) {
          if (data.blocks[key] === undefined) continue;
          if (lastHtml.has(key) && lastHtml.get(key) === data.blocks[key]) continue; // unverändert — DOM/Zustand unangetastet lassen
          lastHtml.set(key, data.blocks[key]);
          changed = true;
          const el = document.getElementById(id);
          if (!el) continue;
          el.innerHTML = data.blocks[key];
          if (data.blocks[key] === '') {
            el.style.display = 'none';
            hiddenByUs.add(key);
          } else if (hiddenByUs.has(key)) {
            // Nur wieder einblenden, wenn WIR es vorher versteckt hatten —
            // andernfalls überschreibt jeder Poll-Tick z.B. einen manuell
            // eingeklappten Zustand (siehe togglePlayers()).
            el.style.display = '';
            hiddenByUs.delete(key);
          }
        }
      }
      if (changed && typeof config.onData === 'function') config.onData(data);
    } finally {
      inFlight = false;
    }
  }

  function start() {
    running = true;
    tick();
    if (timer) clearInterval(timer);
    timer = setInterval(tick, currentInterval());
  }
  function stop() {
    running = false;
    if (timer) clearInterval(timer);
    timer = null;
    if (countdown) countdown.stop();
  }
  function restart() { if (running) start(); } // Intervall neu einlesen + Timer neu aufsetzen

  document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
      if (timer) { clearInterval(timer); timer = null; }
      if (countdown) countdown.stop();
    } else if (running) {
      start(); // sofort refreshen + Intervall neu starten
    }
  });

  // Nur registrieren, wenn kein fest verdrahtetes Intervall vorgegeben wurde —
  // sonst würde eine Einstellungs-Änderung einen bewusst fixen Poller stören.
  if (!config.interval) {
    if (!window._liveBlockInstances) window._liveBlockInstances = [];
    window._liveBlockInstances.push({ restart });
  }

  return { start, stop, restart, refreshNow: tick };
}

/** Alle laufenden liveBlocks()-Instanzen mit dem aktuellen ww_poll_interval neu starten. */
function restartAllLiveBlocks() {
  (window._liveBlockInstances || []).forEach(inst => inst.restart());
}

// ── Theme-Switch ─────────────────────────────────────────────
function switchTheme(e, theme) {
  e.preventDefault();
  document.cookie=`ww_theme=${encodeURIComponent(theme)};path=/;max-age=${365*86400}`;
  LS.setTheme(theme);
  // _pageUrl (echte URL vor replaceState) verwenden, damit der
  // Theme-Parameter an die richtige Seite weitergegeben wird.
  const url = new URL(_pageUrl);
  url.searchParams.set('theme', theme);
  window.location.href = url.toString();
}

// ── Init ─────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded',()=>{
  LS.syncPlayer();
});
