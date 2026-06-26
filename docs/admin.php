<?php
// Copyright (c) 2026 Andreas Vetter
require_once dirname(__DIR__) . '/core/bootstrap.php';
Auth::requireAdmin();

$page = ['title' => 'Admin-Handbuch'];
require TEMPLATE_PATH . '/base.php';
?>

<style>
/* ── Shared Docs Styles ──────────────────────────────── */
.docs-hero{background:linear-gradient(135deg,rgba(168,85,247,.15),rgba(139,92,246,.08));
  border:1px solid rgba(168,85,247,.3);border-radius:var(--radius-lg);
  padding:2.5rem 2rem;text-align:center;margin-bottom:2rem}
.docs-hero h1{font-family:var(--font-display);font-size:2rem;color:var(--text-bright);margin:.5rem 0 .25rem}
.docs-hero .sub{color:var(--text-dim);font-size:1rem}

.step-card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius-md);
  padding:1.5rem;margin-bottom:1.25rem;position:relative}
.step-num{position:absolute;top:-14px;left:1.25rem;background:rgba(168,85,247,1);color:#fff;
  font-weight:700;font-size:.85rem;border-radius:999px;padding:.2rem .7rem;
  font-family:var(--font-display)}
.step-card h3{margin:0 0 .6rem;font-size:1.05rem;color:var(--text-bright)}
.step-card p{margin:0;color:var(--text-dim);font-size:.92rem;line-height:1.6}

.section-sep{display:flex;align-items:center;gap:.75rem;margin:2rem 0}
.section-sep span{white-space:nowrap;font-family:var(--font-display);font-size:.8rem;
  color:var(--text-dim);text-transform:uppercase;letter-spacing:.08em}
.section-sep::before,.section-sep::after{content:'';flex:1;height:1px;background:var(--border)}

.ui-mock{background:var(--bg);border:1px solid var(--border);border-radius:var(--radius-md);
  padding:1rem 1.25rem;margin:.75rem 0;font-size:.84rem;font-family:monospace;color:var(--text-dim);
  line-height:1.8}
.ui-mock .label{color:#a78bfa;font-style:normal;font-family:var(--font-sans);font-size:.8rem;
  display:block;margin-bottom:.25rem}
.ui-mock .action{color:#34d399;cursor:default}
.ui-mock .danger{color:#f87171;cursor:default}
.ui-mock .info{color:#60a5fa;cursor:default}
.ui-mock .dim{color:var(--text-dim);opacity:.6;cursor:default}

.tip-box{background:rgba(16,185,129,.08);border:1px solid rgba(16,185,129,.25);
  border-radius:var(--radius-md);padding:1rem 1.25rem;margin:1rem 0;
  font-size:.9rem;color:var(--text-dim)}
.tip-box strong{color:var(--text)}

.warn-box{background:rgba(251,191,36,.08);border:1px solid rgba(251,191,36,.3);
  border-radius:var(--radius-md);padding:1rem 1.25rem;margin:1rem 0;
  font-size:.9rem;color:var(--text-dim)}
.warn-box strong{color:#fbbf24}

.checklist{list-style:none;padding:0;margin:.5rem 0}
.checklist li{padding:.35rem 0;border-bottom:1px solid var(--border);font-size:.9rem;
  color:var(--text-dim);display:flex;gap:.6rem;align-items:flex-start}
.checklist li::before{content:'☐';color:#a78bfa;flex-shrink:0}

.phase-box{border-radius:var(--radius-md);padding:1.25rem 1.5rem;margin-bottom:1rem}
.phase-box--day{background:rgba(251,191,36,.08);border:1px solid rgba(251,191,36,.25)}
.phase-box--night{background:rgba(139,92,246,.08);border:1px solid rgba(139,92,246,.25)}
.phase-box h3{margin:0 0 .5rem;font-size:1rem}
.phase-box ol{margin:0;padding-left:1.3rem;color:var(--text-dim);font-size:.9rem;line-height:1.8}

.nav-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:.6rem;margin:1rem 0}
.nav-card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius-md);
  padding:.75rem .9rem;font-size:.82rem;text-align:center;color:var(--text)}
.nav-card .nav-icon{font-size:1.5rem;display:block;margin-bottom:.25rem}
</style>

<div class="container page-wrap" style="max-width:820px">

  <!-- Zurück -->
  <div style="margin-bottom:1rem">
    <a href="<?= APP_URL ?>/docs/" class="btn btn--ghost btn--sm">← Übersicht</a>
    <a href="<?= APP_URL ?>/admin/" class="btn btn--ghost btn--sm" style="margin-left:.5rem">⚙️ Admin-Panel</a>
  </div>

  <!-- Hero -->
  <div class="docs-hero animate-in">
    <div style="font-size:3rem">⚙️</div>
    <h1>Admin-Handbuch</h1>
    <p class="sub">Spiel einrichten, Runden leiten und das Dorf unter Kontrolle halten</p>
  </div>


  <!-- ═══════════════════════════════════════════
       Navigation
  ═══════════════════════════════════════════ -->
  <div class="card animate-in" style="margin-bottom:1.5rem;animation-delay:.04s">
    <div class="section-title">🗺️ Admin-Panel — Übersicht</div>
    <div class="nav-grid">
      <div class="nav-card"><span class="nav-icon">🎮</span>Spiele</div>
      <div class="nav-card"><span class="nav-icon">👥</span>Spieler</div>
      <div class="nav-card"><span class="nav-icon">🎭</span>Rollen</div>
      <div class="nav-card"><span class="nav-icon">💬</span>Sprüche</div>
      <div class="nav-card"><span class="nav-icon">⚙️</span>Einstellungen</div>
      <div class="nav-card"><span class="nav-icon">🔔</span>Push</div>
    </div>
    <p class="text-dim text-sm" style="margin-top:.75rem">
      Das Admin-Panel erreichst du über das Navigationsmenü oder
      <a href="<?= APP_URL ?>/admin/" style="color:var(--accent)">direkt hier</a>.
      Alle Aktionen gelten immer für das <strong>aktuell laufende Spiel</strong>.
    </p>
  </div>


  <!-- ═══════════════════════════════════════════
       1. Vorbereitung
  ═══════════════════════════════════════════ -->
  <div class="section-sep"><span>Vorbereitung</span></div>

  <div class="step-card animate-in" style="animation-delay:.05s">
    <div class="step-num">1</div>
    <h3>Rollen konfigurieren</h3>
    <p>
      Unter <strong>Admin → Rollen</strong> stellst du ein, welche Rollen ins Spiel kommen
      und wie viele Exemplare davon. Die Anzahl der aktiven Rollen muss zur Spieleranzahl passen.
      Der <em>Bürger</em> ist die Auffüll-Rolle — er wird automatisch vergeben, bis alle Plätze
      besetzt sind.
    </p>
    <div class="ui-mock" style="margin-top:.75rem">
      <span class="label">Rollen-Verwaltung (Beispiel für 8 Spieler)</span>
      <span class="action">✓</span> Mörder × 2 &nbsp;&nbsp;
      <span class="action">✓</span> Hellseherin × 1<br>
      <span class="action">✓</span> Detektiv × 1 &nbsp;&nbsp;
      <span class="dim">○</span> Nekromant × 1 (deaktiviert)<br>
      <span class="action">✓</span> Bürger (Auffüll, verbleibende 4 Plätze)
    </div>
  </div>

  <div class="step-card animate-in" style="animation-delay:.07s">
    <div class="step-num">2</div>
    <h3>Spiel erstellen &amp; Spieler einladen</h3>
    <p>
      Unter <strong>Admin → Spiele</strong> legst du ein neues Spiel an. Gib ihm einen Namen und
      stelle es auf <em>„Offen"</em>. Spieler können dann auf der Startseite beitreten.
      Alternativ kannst du unter <strong>Admin → Spieler</strong> Konten direkt anlegen und
      einem Spiel zuweisen.
    </p>
  </div>

  <ul class="checklist animate-in" style="animation-delay:.08s">
    <li>Rollen aktiviert und Anzahl gesetzt?</li>
    <li>Alle Spieler sind dem Spiel beigetreten?</li>
    <li>Spieleranzahl stimmt mit Rollenanzahl überein?</li>
    <li>Push-Benachrichtigungen von allen genehmigt?</li>
  </ul>

  <div class="step-card animate-in" style="animation-delay:.09s">
    <div class="step-num">3</div>
    <h3>Spiel starten</h3>
    <p>
      Wenn alle bereit sind: im Admin-Panel auf <strong>„Spiel starten"</strong> klicken.
      Die App verteilt automatisch die Rollen und benachrichtigt alle Spieler per Push.
      Jeder Spieler sieht nun seine Rollenkarte.
    </p>
    <div class="warn-box" style="margin-top:.75rem">
      <strong>⚠️ Einmal gestartet</strong> können keine neuen Spieler mehr beitreten.
      Starte das Spiel also erst, wenn alle da sind.
    </div>
  </div>


  <!-- ═══════════════════════════════════════════
       4. Tag-Phase leiten
  ═══════════════════════════════════════════ -->
  <div class="section-sep"><span>Tag-Phase</span></div>

  <div class="phase-box phase-box--day animate-in" style="animation-delay:.1s">
    <h3>☀️ Tag-Phase — Ablauf für den Admin</h3>
    <ol>
      <li>Gib bekannt, wer in der Nacht gestorben ist (steht im Admin-Panel).</li>
      <li>Trage den/die Toten unter <strong>„Kill eintragen"</strong> → <em>Tote Nacht</em> ein —
          oder der Spieler trägt sich selbst via „Ich bin tot" aus.</li>
      <li>Lass das Dorf diskutieren.</li>
      <li>Wenn eine Bürgerversammlung einberufen wurde: die Anklagemeldung erscheint oben
          im Admin-Panel — du siehst Einberufenden und Uhrzeit.</li>
      <li>Leite Abstimmung, verkünde Ergebnis.</li>
      <li>Falls hingerichtet: <strong>„Spieler hinrichten"</strong> → Spieler wählen →
          <em>Abstimmung</em>. Die App prüft automatisch, ob das Spiel endet.</li>
      <li>Phase wechseln: <strong>„Zur Nacht wechseln"</strong>.</li>
    </ol>
  </div>

  <div class="ui-mock animate-in" style="animation-delay:.11s">
    <span class="label">Admin-Panel — Kill eintragen</span>
    Spieler auswählen: <span class="info">[Dropdown]</span><br>
    Todesart: <span class="action">Abstimmung</span> &nbsp;|&nbsp;
              <span class="danger">Mord (Nacht)</span> &nbsp;|&nbsp;
              <span class="dim">Schuss</span><br>
    <span class="action">[Eintragen]</span> → Spieler wird als tot markiert,<br>
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
    Siegbedingungen werden geprüft.
  </div>

  <div class="tip-box animate-in" style="animation-delay:.12s">
    <strong>💡 Hinrichtung durch Abstimmung:</strong> Nutze den Schnell-Button direkt beim
    Spielernamen (<em>⚔ Hinrichten</em>) im Admin-Panel — spart Zeit bei der Versammlung.
  </div>


  <!-- ═══════════════════════════════════════════
       5. Nacht-Phase leiten
  ═══════════════════════════════════════════ -->
  <div class="section-sep"><span>Nacht-Phase</span></div>

  <div class="phase-box phase-box--night animate-in" style="animation-delay:.13s">
    <h3>🌕 Nacht-Phase — Ablauf für den Admin</h3>
    <ol>
      <li>Alle schließen die Augen. Rufst du die Rollen auf, klopfe oder zeige kurz auf
          den Betroffenen.</li>
      <li><strong>Mörder</strong>: öffnen die Augen, zeigen auf ein Opfer, schließen
          die Augen wieder. Merke dir das Opfer.</li>
      <li><strong>Hellseherin</strong>: öffnet die Augen, zeigt auf einen Spieler.
          Du zeigst Daumen hoch (Killer) oder runter (unschuldig).</li>
      <li><strong>Detektiv</strong>: öffnet die Augen, zeigt auf einen Spieler.
          In der App unter <em>„Detektiv-Suche"</em> prüfen — bei Treffer Daumen hoch.</li>
      <li><strong>Nekromant</strong>: zeigt auf einen Toten — du kannst ihm kurz den
          Rollen-Typen flüstern (Killer/unschuldig).</li>
      <li><strong>Gunslinger / Sheriff</strong>: handeln auf eigene Initiative — der Tod
          wird sofort als <em>„Schuss"</em> eingetragen, wenn sie feuern.</li>
      <li>Alle öffnen die Augen — neue Tag-Phase beginnt.</li>
    </ol>
  </div>

  <div class="warn-box animate-in" style="animation-delay:.14s">
    <strong>⚠️ Sonderrollen-Reihenfolge:</strong> Immer Mörder zuerst, dann Hellseherin,
    dann Detektiv. Gunslinger und Sheriff handeln außer der Reihe, sobald sie schießen
    wollen — trage den Kill sofort ein.
  </div>


  <!-- ═══════════════════════════════════════════
       6. Bürgerversammlung
  ═══════════════════════════════════════════ -->
  <div class="section-sep"><span>Bürgerversammlung</span></div>

  <div class="step-card animate-in" style="animation-delay:.15s">
    <div class="step-num">6</div>
    <h3>Versammlung im Admin-Panel</h3>
    <p>
      Wenn ein Spieler eine Versammlung einberuft, erscheint oben im Admin-Panel ein Banner
      mit Name des Einberufenden und Startzeit. Du kannst die Versammlung als Admin jederzeit
      über <strong>„✖ Versammlung beenden"</strong> im Banner schließen — zum Beispiel wenn
      das Dorf fertig abgestimmt hat.
    </p>
    <div class="ui-mock" style="margin-top:.75rem">
      <span class="label">Bürgerversammlung-Banner (Admin-Panel)</span>
      <span class="info">🔔 Bürgerversammlung aktiv</span><br>
      Einberufen von: <span class="action">Spielername</span> · gestartet 15:00 Uhr<br>
      <span class="danger">[✖ Versammlung beenden]</span>
    </div>
  </div>

  <div class="step-card animate-in" style="animation-delay:.16s">
    <div class="step-num">7</div>
    <h3>Anklage &amp; Urteil eintragen</h3>
    <p>
      Nach der Abstimmung trägst du das Ergebnis ein. Wurde jemand hingerichtet: Spieler
      auswählen, Todesart <em>„Abstimmung"</em> wählen, speichern. Die App prüft direkt,
      ob damit das Spiel endet (Mörder eliminiert → Bürger gewinnen, Dodo erhängt → Dodo gewinnt).
    </p>
  </div>


  <!-- ═══════════════════════════════════════════
       7. Spielende
  ═══════════════════════════════════════════ -->
  <div class="section-sep"><span>Spielende</span></div>

  <div class="step-card animate-in" style="animation-delay:.17s">
    <div class="step-num">8</div>
    <h3>Automatisches Spielende</h3>
    <p>
      Die App beendet das Spiel automatisch, sobald eine Siegbedingung erfüllt ist —
      nach jedem Kill-Eintrag. Alle Spieler erhalten eine Push-Benachrichtigung mit dem
      Ergebnis. Wenn du das Spiel manuell beenden willst: Admin-Panel →
      <strong>„Spiel manuell beenden"</strong>.
    </p>
    <div class="ui-mock" style="margin-top:.75rem">
      <span class="label">Automatische Prüfung nach jedem Kill</span>
      Alle Mörder tot? → <span class="action">Bürger gewinnen 🟢</span><br>
      Mörder ≥ Bürger? → <span class="danger">Mörder gewinnen 🔴</span><br>
      Dodo erhängt?   → <span class="info">Dodo gewinnt 🟡</span>
    </div>
  </div>

  <div class="step-card animate-in" style="animation-delay:.18s">
    <div class="step-num">9</div>
    <h3>Nächste Runde &amp; Reset</h3>
    <p>
      Nach dem Spiel kannst du unter <strong>Admin → Spiele</strong> ein neues Spiel anlegen
      oder das alte zurücksetzen. Beim Reset werden alle Tode, Abstimmungen und Rollenzuweisungen
      gelöscht — die Spielerkonten bleiben erhalten.
    </p>
  </div>


  <!-- ═══════════════════════════════════════════
       8. Einstellungen & Sonstiges
  ═══════════════════════════════════════════ -->
  <div class="section-sep"><span>Einstellungen</span></div>

  <div class="card animate-in" style="animation-delay:.19s">
    <div class="section-title">⚙️ Wichtige Einstellungen</div>
    <table style="width:100%;border-collapse:collapse;font-size:.87rem;margin-top:.5rem">
      <tr style="border-bottom:1px solid var(--border)">
        <td style="padding:.5rem .25rem;color:var(--text);font-weight:600">Push-Cooldown</td>
        <td style="padding:.5rem .5rem;color:var(--text-dim)">
          Mindestpause zwischen automatischen Push-Benachrichtigungen (Standard: 30 Min).
          Verhindert Spam.
        </td>
      </tr>
      <tr style="border-bottom:1px solid var(--border)">
        <td style="padding:.5rem .25rem;color:var(--text);font-weight:600">Spielname</td>
        <td style="padding:.5rem .5rem;color:var(--text-dim)">
          Anzeigename der App — erscheint in allen Push-Nachrichten und im Browser-Tab.
        </td>
      </tr>
      <tr style="border-bottom:1px solid var(--border)">
        <td style="padding:.5rem .25rem;color:var(--text);font-weight:600">Zeitzone</td>
        <td style="padding:.5rem .5rem;color:var(--text-dim)">
          PHP-Zeitzone des Servers (z.B. Europe/Berlin). Beeinflusst Bürgerversammlungs-Uhrzeit.
        </td>
      </tr>
      <tr>
        <td style="padding:.5rem .25rem;color:var(--text);font-weight:600">Dorf-Sprüche</td>
        <td style="padding:.5rem .5rem;color:var(--text-dim)">
          Über <a href="<?= APP_URL ?>/admin/slogans.php" style="color:var(--accent)">Sprüche verwalten</a>
          — bis zu 20 Tag- und 20 Nacht-Sprüche, rotieren alle 2 Minuten im Spieler-Banner.
        </td>
      </tr>
    </table>
  </div>

  <div class="tip-box animate-in" style="animation-delay:.2s">
    <strong>💡 Als Admin bist du auch Spieler.</strong> Deine eigene Rolle ist im Spieler-Fenster
    sichtbar, aber du siehst bewusst <em>nicht</em> die Rollen der anderen lebenden Spieler —
    damit du nicht betrügst. Tote Spieler kannst du im Admin-Panel einsehen.
  </div>

  <div style="text-align:center;margin-top:2rem;padding-bottom:1rem;display:flex;gap:.75rem;justify-content:center;flex-wrap:wrap" class="no-print">
    <a href="<?= APP_URL ?>/docs/" class="btn btn--ghost">← Übersicht</a>
    <button onclick="window.print()" class="btn btn--ghost">🖨️ Als PDF speichern</button>
    <a href="<?= APP_URL ?>/admin/" class="btn btn--primary">⚙️ Zum Admin-Panel</a>
  </div>

</div>

<style media="print">
  .nav-wrapper,.legal-footer,#push-banner,#consent-overlay,.no-print,
  #toast-container{display:none!important}
  body{background:#fff!important;color:#111!important}
  .card{border:1px solid #ddd!important;background:#fff!important;
        break-inside:avoid;page-break-inside:avoid}
  .docs-hero{background:#f5f0ff!important;border:1px solid #ccc!important}
  .phase-box--day{background:#fffbe6!important;border-color:#f5c518!important}
  .phase-box--night{background:#f0ecff!important;border-color:#8b5cf6!important}
  .tip-box{background:#e6fff5!important;border-color:#10b981!important}
  .warn-box{background:#fffbe6!important;border-color:#f5c518!important}
  .ui-mock{background:#f8f8f8!important;color:#444!important}
  .step-card{break-inside:avoid;page-break-inside:avoid}
  .checklist li{break-inside:avoid}
  h1,h2,h3,.section-title{color:#111!important}
  .text-dim{color:#444!important}
  a{color:#111!important;text-decoration:none}
  table{border-collapse:collapse}
  td,th{border:1px solid #ddd!important;padding:.4rem!important}
</style>

<?php require TEMPLATE_PATH . '/base_end.php'; ?>
