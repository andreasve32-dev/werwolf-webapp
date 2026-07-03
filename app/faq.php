<?php
// Copyright (c) 2026 Andreas Vetter
// FAQ & Rollenregeln: nur vom Admin veröffentlichte Antworten erscheinen hier.
// Spieler stellen Fragen über das Nachrichtenformular auf game.php.
require_once dirname(__DIR__) . '/core/bootstrap.php';
require_once TEMPLATE_PATH . '/faq_blocks.php';
Auth::requireLogin();

$faqEntries = Database::query(
    "SELECT COALESCE(faq_question, message) AS message, reply FROM messages
     WHERE published = 1 AND reply IS NOT NULL
     ORDER BY replied_at DESC"
);

$roles = activeRoles();

$page = ['title' => 'FAQ & Regeln'];
require TEMPLATE_PATH . '/base.php';
?>

<div class="container page-wrap">

  <div class="page-header">
    <span class="page-header__icon">❓</span>
    <h1>FAQ & Regeln</h1>
    <p class="page-header__sub">Häufige Fragen und Rollenregeln auf einen Blick.</p>
  </div>

  <!-- ── Suchleiste ───────────────────────────────────────────── -->
  <div style="position:relative;margin-bottom:1.5rem">
    <span style="position:absolute;left:.85rem;top:50%;transform:translateY(-50%);
                 font-size:1rem;pointer-events:none;opacity:.5">🔍</span>
    <input type="search" id="faq-search" class="form-input"
           placeholder="Suchen in FAQ und Rollenregeln …"
           style="padding-left:2.4rem;font-size:.95rem"
           autocomplete="off">
    <span id="search-count" class="text-dim text-xs"
          style="position:absolute;right:.9rem;top:50%;transform:translateY(-50%);display:none"></span>
  </div>

  <!-- ── Tabs ────────────────────────────────────────────────── -->
  <div class="faq-tabs" role="tablist">
    <button id="tab-btn-faq"   class="faq-tab faq-tab--active" role="tab"
            onclick="switchTab('faq')">
      ❓ FAQ
      <span class="faq-tab__count" id="tab-count-faq"><?= count($faqEntries) ?></span>
    </button>
    <button id="tab-btn-roles" class="faq-tab" role="tab"
            onclick="switchTab('roles')">
      🎭 Rollenregeln
      <span class="faq-tab__count" id="tab-count-roles"><?= count($roles) ?></span>
    </button>
  </div>

  <!-- ── FAQ-Panel ────────────────────────────────────────────── -->
  <div id="panel-faq" role="tabpanel">
    <div id="faq-content"><?= render_faq_list($faqEntries) ?></div>
  </div>

  <!-- ── Rollen-Panel ─────────────────────────────────────────── -->
  <div id="panel-roles" role="tabpanel" style="display:none">
    <div id="roles-rules-content"><?= render_roles_rules_list($roles) ?></div>
  </div>

</div>

<style>
/* ── Tabs ──────────────────────────────────────────────────── */
.faq-tabs {
  display: flex;
  gap: .5rem;
  margin-bottom: 1.25rem;
}
.faq-tab {
  display: flex; align-items: center; gap: .45rem;
  padding: .55rem 1.1rem;
  border-radius: 99px;
  border: 1px solid var(--border);
  background: var(--card-bg);
  color: var(--text-dim);
  font-size: .88rem;
  cursor: pointer;
  transition: border-color .18s, color .18s, background .18s;
  font-family: inherit;
}
.faq-tab:hover { border-color: var(--accent-border); color: var(--text-bright); }
.faq-tab--active {
  border-color: var(--accent-border);
  background: var(--panel-bg);
  color: var(--accent);
}
.faq-tab__count {
  display: inline-flex; align-items: center; justify-content: center;
  min-width: 1.35rem; height: 1.35rem; padding: 0 .3rem;
  background: var(--panel-bg);
  border-radius: 99px;
  font-size: .7rem;
  font-weight: 700;
  color: var(--text-dim);
}
.faq-tab--active .faq-tab__count { background: var(--accent-border); color: var(--accent); }

/* ── FAQ-Einträge ──────────────────────────────────────────── */
.faq-item__q {
  display: flex; align-items: flex-start; gap: .75rem;
  width: 100%; padding: 1rem 1rem;
  background: none; border: none; cursor: pointer;
  text-align: left; color: var(--text-bright);
  font-size: .92rem; font-family: inherit;
  line-height: 1.5;
}
.faq-item__q:hover { background: var(--hover-bg, rgba(255,255,255,.03)); }
.faq-item__q[aria-expanded="true"] { color: var(--accent); }
.faq-item__q-icon {
  flex-shrink: 0; width: 1.5rem; height: 1.5rem;
  display: flex; align-items: center; justify-content: center;
  border-radius: 6px;
  background: var(--accent-border);
  color: var(--accent);
  font-size: .72rem; font-weight: 800; letter-spacing: .02em;
  margin-top: .05rem;
}
.faq-item__q-text { flex: 1; }
.faq-item__chevron {
  flex-shrink: 0; margin-top: .1rem;
  font-size: .7rem; color: var(--text-dim);
  transition: transform .22s ease;
}
.faq-item__q[aria-expanded="true"] .faq-item__chevron { transform: rotate(180deg); }

.faq-item__a {
  display: flex; gap: .75rem;
  padding: .75rem 1rem 1rem;
  border-top: 1px solid var(--border);
  background: var(--panel-bg);
}
.faq-item__a-icon {
  flex-shrink: 0; width: 1.5rem; height: 1.5rem;
  display: flex; align-items: center; justify-content: center;
  border-radius: 6px;
  background: var(--success-bg, rgba(74,222,128,.12));
  color: var(--success-text, #4ade80);
  font-size: .72rem; font-weight: 800;
  margin-top: .05rem;
}
.faq-item__a-text {
  flex: 1;
  font-size: .9rem; line-height: 1.6;
  color: var(--text-bright);
}

/* ── Rollenregeln ──────────────────────────────────────────── */
.role-rule-item {
  background: var(--card-bg);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  overflow: hidden;
  transition: border-color .18s;
}
.role-rule-item:has(.role-rule-item__head[aria-expanded="true"]) {
  border-color: var(--accent-border);
}
.role-rule-item__head {
  display: flex; align-items: center; gap: .75rem; flex-wrap: wrap;
  width: 100%; padding: .85rem 1rem;
  background: none; border: none; cursor: pointer;
  text-align: left; font-family: inherit;
}
.role-rule-item__head:hover { background: var(--hover-bg, rgba(255,255,255,.03)); }
.role-rule-item__icon {
  flex-shrink: 0; width: 2.5rem; height: 2.5rem;
  background-size: contain; background-repeat: no-repeat; background-position: center;
  background-color: var(--panel-bg);
  border-radius: 8px;
}
.role-rule-item__name {
  font-family: var(--font-display);
  font-size: 1.05rem; color: var(--accent); font-weight: 600;
  flex: 1; min-width: 80px;
}
.role-rule-item__tags { display: flex; gap: .3rem; flex-wrap: wrap; }
.role-rule-item__chevron {
  flex-shrink: 0; font-size: .7rem; color: var(--text-dim);
  transition: transform .22s ease; margin-left: auto;
}
.role-rule-item__head[aria-expanded="true"] .role-rule-item__chevron { transform: rotate(180deg); }

.role-rule-item__body {
  padding: 0 1rem 1rem;
  border-top: 1px solid var(--border);
  background: var(--panel-bg);
}
.role-rule-item__desc {
  margin: .85rem 0 .5rem;
  font-size: .9rem; line-height: 1.6;
  color: var(--text-bright);
}
.role-rule-item__rules {
  font-size: .85rem; line-height: 1.6;
  color: var(--text-dim);
  font-style: italic;
  border-top: 1px solid var(--border);
  padding-top: .65rem;
  margin-top: .5rem;
}
.role-rule-item__rules-label {
  display: block; font-style: normal;
  font-size: .72rem; font-weight: 700;
  color: var(--text-dim);
  text-transform: uppercase; letter-spacing: .07em;
  margin-bottom: .3rem;
}

/* ── Highlight für Suchtreffer ─────────────────────────────── */
.faq-highlight { background: var(--accent-border); border-radius: 3px; padding: 0 2px; color: var(--accent); }
</style>

<?php
$page['inline_js'] = sprintf('const API_BASE=%s;', json_encode(API_URL));
$page['inline_js'] .= <<<'JS'
let _activeTab = 'faq';

// ── Tab wechseln ─────────────────────────────────────────────
function switchTab(tab) {
  _activeTab = tab;
  document.getElementById('panel-faq').style.display   = tab === 'faq'   ? '' : 'none';
  document.getElementById('panel-roles').style.display = tab === 'roles' ? '' : 'none';
  document.getElementById('tab-btn-faq').classList.toggle('faq-tab--active',   tab === 'faq');
  document.getElementById('tab-btn-roles').classList.toggle('faq-tab--active', tab === 'roles');
  // Suche auf neuen Tab anwenden
  applySearch(document.getElementById('faq-search').value);
}

// ── Akkordeon FAQ ─────────────────────────────────────────────
// Offene Einträge merken (per Fragetext/Rollenname), damit ein Live-Update
// des Blocks die Leseposition nicht zerstört.
const _openFaqItems  = new Set();
const _openRoleItems = new Set();

function toggleFaq(btn) {
  const body = btn.nextElementSibling;
  const open  = btn.getAttribute('aria-expanded') === 'true';
  btn.setAttribute('aria-expanded', String(!open));
  body.hidden = open;
  const key = btn.querySelector('.faq-item__q-text')?.textContent.trim();
  if (key) { if (open) _openFaqItems.delete(key); else _openFaqItems.add(key); }
}

// ── Akkordeon Rollen ──────────────────────────────────────────
function toggleRole(btn) {
  const body = btn.nextElementSibling;
  const open  = btn.getAttribute('aria-expanded') === 'true';
  btn.setAttribute('aria-expanded', String(!open));
  body.hidden = open;
  const key = btn.querySelector('.role-rule-item__name')?.textContent.trim();
  if (key) { if (open) _openRoleItems.delete(key); else _openRoleItems.add(key); }
}

function _restoreOpenItems() {
  document.querySelectorAll('#faq-list .faq-item__q').forEach(btn => {
    const key = btn.querySelector('.faq-item__q-text')?.textContent.trim();
    if (key && _openFaqItems.has(key)) {
      btn.setAttribute('aria-expanded', 'true');
      if (btn.nextElementSibling) btn.nextElementSibling.hidden = false;
    }
  });
  document.querySelectorAll('#roles-list .role-rule-item__head').forEach(btn => {
    const key = btn.querySelector('.role-rule-item__name')?.textContent.trim();
    if (key && _openRoleItems.has(key)) {
      btn.setAttribute('aria-expanded', 'true');
      if (btn.nextElementSibling) btn.nextElementSibling.hidden = false;
    }
  });
}

// ── Volltext-Suche ────────────────────────────────────────────
function matches(text, words) {
  const t = text.toLowerCase();
  return words.every(w => t.includes(w));
}

function applySearch(raw) {
  const query = raw.trim().toLowerCase();
  const words = query.split(/\s+/).filter(Boolean);

  // FAQ-Einträge filtern
  const faqItems = document.querySelectorAll('#faq-list .faq-item');
  let faqVis = 0;
  faqItems.forEach(el => {
    const show = !words.length || matches(el.dataset.search || '', words);
    el.style.display = show ? '' : 'none';
    if (show) faqVis++;
    // Bei Suchtreffer automatisch aufklappen
    if (show && words.length) {
      const btn = el.querySelector('.faq-item__q');
      const body = el.querySelector('.faq-item__a');
      if (btn && body) { btn.setAttribute('aria-expanded','true'); body.hidden = false; }
    }
  });
  const faqEmpty = document.getElementById('faq-empty');
  if (faqEmpty) faqEmpty.style.display = (faqItems.length > 0 && faqVis === 0) ? '' : 'none';

  // Rollen filtern
  const roleItems = document.querySelectorAll('#roles-list .role-rule-item');
  let rolesVis = 0;
  roleItems.forEach(el => {
    const show = !words.length || matches(el.dataset.search || '', words);
    el.style.display = show ? '' : 'none';
    if (show) rolesVis++;
    // Bei Suchtreffer automatisch aufklappen
    if (show && words.length) {
      const btn = el.querySelector('.role-rule-item__head');
      const body = el.querySelector('.role-rule-item__body');
      if (btn && body) { btn.setAttribute('aria-expanded','true'); body.hidden = false; }
    }
  });
  const rolesEmpty = document.getElementById('roles-empty');
  if (rolesEmpty) rolesEmpty.style.display = (roleItems.length > 0 && rolesVis === 0) ? '' : 'none';

  // Suche geleert: von der Suche aufgeklappte Einträge wieder schließen —
  // nur manuell geöffnete (in den _open*-Sets gemerkte) bleiben offen
  if (!words.length) {
    document.querySelectorAll('#faq-list .faq-item__q[aria-expanded="true"], #roles-list .role-rule-item__head[aria-expanded="true"]')
      .forEach(btn => {
        btn.setAttribute('aria-expanded', 'false');
        if (btn.nextElementSibling) btn.nextElementSibling.hidden = true;
      });
    _restoreOpenItems();
  }

  // Hat nur der jeweils andere Bereich Treffer → automatisch dorthin wechseln
  // (switchTab ruft applySearch erneut auf; die Bedingung greift dann nicht mehr)
  if (words.length) {
    if (_activeTab === 'faq'   && faqVis === 0   && rolesVis > 0) { switchTab('roles'); return; }
    if (_activeTab === 'roles' && rolesVis === 0 && faqVis > 0)   { switchTab('faq');   return; }
  }

  // Zum ersten sichtbaren Treffer im aktiven Bereich springen
  if (words.length) {
    const sel   = _activeTab === 'faq' ? '#faq-list .faq-item' : '#roles-list .role-rule-item';
    const first = Array.from(document.querySelectorAll(sel)).find(el => el.style.display !== 'none');
    if (first) first.scrollIntoView({behavior: 'smooth', block: 'nearest'});
  }

  // Tab-Zähler aktualisieren
  const faqCount   = document.getElementById('tab-count-faq');
  const rolesCount = document.getElementById('tab-count-roles');
  if (faqCount)   faqCount.textContent   = words.length ? faqVis   : faqItems.length;
  if (rolesCount) rolesCount.textContent = words.length ? rolesVis : roleItems.length;

  // Trefferanzahl anzeigen
  const sc = document.getElementById('search-count');
  if (sc) {
    if (words.length) {
      const total = faqVis + rolesVis;
      sc.textContent = total + ' Treffer';
      sc.style.display = '';
    } else {
      sc.style.display = 'none';
    }
  }
}

document.getElementById('faq-search').addEventListener('input', function() {
  applySearch(this.value);
});

// ── Tastatur ─────────────────────────────────────────────────
document.addEventListener('keydown', e => {
  if (e.key === '/' && document.activeElement.tagName !== 'INPUT') {
    e.preventDefault();
    document.getElementById('faq-search').focus();
  }
  if (e.key === 'Escape') {
    const s = document.getElementById('faq-search');
    s.value = ''; applySearch(''); s.blur();
  }
});

// ── Live-Update: FAQ & Rollenregeln ────────────────────────────
const faqPoll = liveBlocks({
  fetcher: (hash) => apiFetch(API_BASE+'/game.php', {action:'get_faq', blocks_hash: hash}),
  targets: {'faq-content':'faq-content', 'roles-rules-content':'roles-rules-content'},
  countdownId: 'poll-countdown',
  onData: () => {
    _restoreOpenItems(); // vom Nutzer geöffnete Einträge wieder aufklappen
    applySearch(document.getElementById('faq-search').value);
  },
});
faqPoll.start();
JS;
require TEMPLATE_PATH . '/base_end.php';
?>
