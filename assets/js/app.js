// Copyright (c) 2026 Andreas Vetter
/**
 * ============================================================
 *  WERWOLF — app.js
 *  Globale JS-Utilities: API, Toast, LocalStorage, Theme
 * ============================================================
 *  Hinweis: Rollen-Namen/Icons werden NICHT mehr hier hartcodiert.
 *  Sie kommen live aus der Datenbank (Tabelle `roles`) über die
 *  API-Felder role_name / role_icon_path / role_sichtbar.
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
      setTimeout(() => { window.location.href = '/logout.php'; }, 2000);
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
