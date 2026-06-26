<?php
// Copyright (c) 2026 Andreas Vetter
require_once dirname(__DIR__) . '/core/bootstrap.php';
Auth::requireLogin();

$page = ['title' => 'Spieler-Anleitung'];
require TEMPLATE_PATH . '/base.php';
?>

<style>
/* ── Docs Layout ─────────────────────────────────────── */
.docs-hero{background:linear-gradient(135deg,rgba(251,191,36,.12),rgba(139,92,246,.12));
  border:1px solid rgba(251,191,36,.25);border-radius:var(--radius-lg);
  padding:2.5rem 2rem;text-align:center;margin-bottom:2rem}
.docs-hero h1{font-family:var(--font-display);font-size:2rem;color:var(--text-bright);margin:.5rem 0 .25rem}
.docs-hero .sub{color:var(--text-dim);font-size:1rem}

.step-card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius-md);
  padding:1.5rem;margin-bottom:1.25rem;position:relative}
.step-num{position:absolute;top:-14px;left:1.25rem;background:var(--accent);color:#000;
  font-weight:700;font-size:.85rem;border-radius:999px;padding:.2rem .7rem;
  font-family:var(--font-display)}
.step-card h3{margin:0 0 .6rem;font-size:1.05rem;color:var(--text-bright)}
.step-card p{margin:0;color:var(--text-dim);font-size:.92rem;line-height:1.6}

.phase-box{border-radius:var(--radius-md);padding:1.25rem 1.5rem;margin-bottom:1rem}
.phase-box--day{background:rgba(251,191,36,.1);border:1px solid rgba(251,191,36,.3)}
.phase-box--night{background:rgba(139,92,246,.1);border:1px solid rgba(139,92,246,.3)}
.phase-box h3{margin:0 0 .5rem;font-size:1rem}
.phase-box ul{margin:0;padding-left:1.3rem;color:var(--text-dim);font-size:.9rem;line-height:1.8}

/* Flow Diagramm */
.flow{display:flex;align-items:center;justify-content:center;flex-wrap:wrap;gap:.5rem;
  margin:1.5rem 0;padding:1.25rem;background:var(--card);border:1px solid var(--border);
  border-radius:var(--radius-md)}
.flow-node{background:var(--bg);border:2px solid var(--border);border-radius:var(--radius-md);
  padding:.6rem .9rem;text-align:center;font-size:.82rem;color:var(--text)}
.flow-node--day{border-color:rgba(251,191,36,.6);background:rgba(251,191,36,.08);color:#fbbf24}
.flow-node--night{border-color:rgba(139,92,246,.6);background:rgba(139,92,246,.08);color:#a78bfa}
.flow-node--start{border-color:var(--accent);background:rgba(16,185,129,.1);color:#10b981}
.flow-node--end{border-color:rgba(239,68,68,.5);background:rgba(239,68,68,.08);color:#f87171}
.flow-arrow{color:var(--text-dim);font-size:1.2rem}

/* Rollen-Grid */
.role-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:1rem;margin:1rem 0}
.role-card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius-md);
  padding:1rem;text-align:center;position:relative}
.role-card img{width:72px;height:72px;object-fit:cover;border-radius:50%;
  border:2px solid var(--border);margin-bottom:.6rem}
.role-card .role-name{font-weight:700;font-size:.9rem;color:var(--text-bright);margin-bottom:.3rem}
.role-card .role-team{font-size:.72rem;padding:.15rem .5rem;border-radius:999px;
  display:inline-block;margin-bottom:.4rem;font-weight:600}
.team--buerger{background:rgba(16,185,129,.15);color:#10b981}
.team--killer{background:rgba(239,68,68,.15);color:#f87171}
.team--solo{background:rgba(251,191,36,.15);color:#fbbf24}
.role-card .role-desc{font-size:.78rem;color:var(--text-dim);line-height:1.45}

.tip-box{background:rgba(16,185,129,.08);border:1px solid rgba(16,185,129,.25);
  border-radius:var(--radius-md);padding:1rem 1.25rem;margin:1rem 0;
  font-size:.9rem;color:var(--text-dim)}
.tip-box strong{color:var(--text)}

.warn-box{background:rgba(251,191,36,.08);border:1px solid rgba(251,191,36,.3);
  border-radius:var(--radius-md);padding:1rem 1.25rem;margin:1rem 0;
  font-size:.9rem;color:var(--text-dim)}
.warn-box strong{color:#fbbf24}

.section-sep{display:flex;align-items:center;gap:.75rem;margin:2rem 0}
.section-sep span{white-space:nowrap;font-family:var(--font-display);font-size:.8rem;
  color:var(--text-dim);text-transform:uppercase;letter-spacing:.08em}
.section-sep::before,.section-sep::after{content:'';flex:1;height:1px;background:var(--border)}
</style>

<div class="container page-wrap" style="max-width:800px">

  <!-- Zurück -->
  <div style="margin-bottom:1rem">
    <a href="<?= APP_URL ?>/docs/" class="btn btn--ghost btn--sm">← Übersicht</a>
  </div>

  <!-- Hero -->
  <div class="docs-hero animate-in">
    <div style="font-size:3rem">🧑‍🤝‍🧑</div>
    <h1>Spieler-Anleitung</h1>
    <p class="sub">Alles was du als neuer Spieler wissen musst — von Rollenkarte bis Siegbedingung</p>
  </div>


  <!-- ═══════════════════════════════════════════
       1. Was ist Werwolf?
  ═══════════════════════════════════════════ -->
  <div class="section-sep"><span>Das Spiel</span></div>

  <div class="step-card animate-in">
    <div class="step-num">1</div>
    <h3>Was ist <?= e(APP_NAME) ?>?</h3>
    <p>
      <?= e(APP_NAME) ?> ist ein Deduktions- und Bluff-Spiel für Gruppen.
      Zwei Fraktionen stehen sich gegenüber: <strong>Bürger</strong> und <strong>Mörder</strong>.
      Die Mörder wissen, wer sie sind und arbeiten zusammen. Die Bürger müssen herausfinden,
      wer unter ihnen die Mörder sind — und das in Echtzeit, als Präsenzspiel mit Telefonen als
      Hilfsmittel.
    </p>
  </div>

  <!-- Spielablauf-Diagramm -->
  <div class="flow animate-in" style="animation-delay:.04s">
    <div class="flow-node flow-node--start">🚀 Spiel startet</div>
    <div class="flow-arrow">→</div>
    <div class="flow-node flow-node--day">☀️ Tag-Phase<br><small>Reden, Anklagen</small></div>
    <div class="flow-arrow">→</div>
    <div class="flow-node flow-node--day" style="border-color:rgba(251,191,36,.9)">🗳️ Abstimmung<br><small>Hinrichten?</small></div>
    <div class="flow-arrow">→</div>
    <div class="flow-node flow-node--night">🌕 Nacht-Phase<br><small>Mörder töten</small></div>
    <div class="flow-arrow">→</div>
    <div class="flow-node flow-node--day">☀️ Neuer Tag …</div>
    <div class="flow-arrow">→</div>
    <div class="flow-node flow-node--end">🏁 Spielende</div>
  </div>


  <!-- ═══════════════════════════════════════════
       2. Ins Spiel kommen
  ═══════════════════════════════════════════ -->
  <div class="section-sep"><span>Einstieg</span></div>

  <div class="step-card animate-in" style="animation-delay:.05s">
    <div class="step-num">2</div>
    <h3>Einem Spiel beitreten</h3>
    <p>
      Rufe die App auf und melde dich an. Auf der Startseite siehst du offene Spiele —
      klicke auf <strong>„Beitreten"</strong>. Sobald der Admin das Spiel startet,
      erhältst du automatisch eine Benachrichtigung und deine Rollenkarte erscheint.
    </p>
  </div>

  <div class="step-card animate-in" style="animation-delay:.07s">
    <div class="step-num">3</div>
    <h3>Deine Rollenkarte</h3>
    <p>
      Im Spiel-Fenster findest du oben deine Rollenkarte mit Bild, Name und einer kurzen
      Regelbeschreibung. <strong>Halte deine Rolle geheim</strong> — zeige den Bildschirm
      niemand anderem. Nur wenn du als Promi eingestuft bist, kennen alle deine Rolle.
    </p>
  </div>


  <!-- ═══════════════════════════════════════════
       3. Rollen
  ═══════════════════════════════════════════ -->
  <div class="section-sep"><span>Die Rollen</span></div>

  <div class="card animate-in" style="animation-delay:.08s">
    <div class="section-title">🎭 Alle Rollen im Überblick</div>

    <div class="role-grid">

      <div class="role-card">
        <img src="<?= APP_URL ?>/assets/icons/roles/buerger.png" alt="Bürger">
        <div class="role-name">Bürger</div>
        <div class="role-team team--buerger">Bürger-Team</div>
        <div class="role-desc">Keine Sonderrolle. Rede, beobachte und kläre durch Abstimmen.</div>
      </div>

      <div class="role-card">
        <img src="<?= APP_URL ?>/assets/icons/roles/moerder.png" alt="Mörder">
        <div class="role-name">Mörder</div>
        <div class="role-team team--killer">Mörder-Team</div>
        <div class="role-desc">Zeige einem Spieler die Mordwaffe — er ist sofort tot. 30 Min. Abklingzeit.</div>
      </div>

      <div class="role-card">
        <img src="<?= APP_URL ?>/assets/icons/roles/hellseherin.png" alt="Hellseherin">
        <div class="role-name">Hellseherin</div>
        <div class="role-team team--buerger">Bürger-Team</div>
        <div class="role-desc">Kann einen Spieler befragen. Die App zeigt an: Killer oder unschuldig.</div>
      </div>

      <div class="role-card">
        <img src="<?= APP_URL ?>/assets/icons/roles/detektiv.png" alt="Detektiv">
        <div class="role-name">Detektiv</div>
        <div class="role-team team--buerger">Bürger-Team</div>
        <div class="role-desc">Durchsuche einen Spieler. Trägt er die Mordwaffe, muss er sie abgeben.</div>
      </div>

      <div class="role-card">
        <img src="<?= APP_URL ?>/assets/icons/roles/nekromant.png" alt="Nekromant">
        <div class="role-name">Nekromant</div>
        <div class="role-team team--buerger">Bürger-Team</div>
        <div class="role-desc">Kann tote Spieler befragen und erhält Hinweise auf den Mörder.</div>
      </div>

      <div class="role-card">
        <img src="<?= APP_URL ?>/assets/icons/roles/gunslinger.png" alt="Gunslinger">
        <div class="role-name">Gunslinger</div>
        <div class="role-team team--buerger">Bürger-Team</div>
        <div class="role-desc">Kann schießen. Triffst du einen Killer, lebst du. Triffst du einen Unschuldigen, stirbst du selbst.</div>
      </div>

      <div class="role-card">
        <img src="<?= APP_URL ?>/assets/icons/roles/sheriff.png" alt="Sheriff">
        <div class="role-name">Sheriff</div>
        <div class="role-team team--buerger">Bürger-Team</div>
        <div class="role-desc">Kann schießen — ohne Limit. Aber: Triffst du keinen Killer oder Dodo, stirbst du.</div>
      </div>

      <div class="role-card">
        <img src="<?= APP_URL ?>/assets/icons/roles/celebrity.png" alt="Promi">
        <div class="role-name">Promi</div>
        <div class="role-team team--buerger">Bürger-Team</div>
        <div class="role-desc">Deine Rolle ist allen bekannt. Du trittst automatisch ins Spiel ein.</div>
      </div>

      <div class="role-card">
        <img src="<?= APP_URL ?>/assets/icons/roles/das-paar.png" alt="Das Paar">
        <div class="role-name">Das Paar</div>
        <div class="role-team team--solo">Eigenes Team</div>
        <div class="role-desc">Zwei Spieler, die zusammen gewinnen. Stirbt einer, stirbt der andere mit.</div>
      </div>

      <div class="role-card">
        <img src="<?= APP_URL ?>/assets/icons/roles/dodo.png" alt="Dodo">
        <div class="role-name">Dodo</div>
        <div class="role-team team--solo">Eigenes Team</div>
        <div class="role-desc">Gewinnst, wenn die Versammlung dich erhängt. Du darfst KEINE Mordwaffe tragen!</div>
      </div>

    </div>
  </div>


  <!-- ═══════════════════════════════════════════
       4. Tag-Phase
  ═══════════════════════════════════════════ -->
  <div class="section-sep"><span>Spielphasen</span></div>

  <div class="phase-box phase-box--day animate-in" style="animation-delay:.1s">
    <h3>☀️ Tag-Phase — Das Dorf wacht auf</h3>
    <ul>
      <li>Alle Spieler reden, diskutieren und tauschen Verdächtigungen aus.</li>
      <li>Wer gestern Nacht gestorben ist, wird bekannt gegeben und trägt sich aus.</li>
      <li>Jeder lebende Spieler kann eine <strong>Bürgerversammlung einberufen</strong>, um
          einen Verdächtigen anzuklagen und per Handzeichen hinrichten zu lassen.</li>
      <li>Der Admin verwaltet die Abstimmung und trägt das Ergebnis in die App ein.</li>
    </ul>
  </div>

  <div class="phase-box phase-box--night animate-in" style="animation-delay:.12s">
    <h3>🌕 Nacht-Phase — Das Dorf schläft</h3>
    <ul>
      <li>Alle Spieler schließen die Augen (oder schauen weg).</li>
      <li>Die Mörder zeigen einem Spieler ihrer Wahl die Mordwaffe — dieser ist tot.</li>
      <li>Sonderrollen (Hellseherin, Detektiv, Nekromant …) werden vom Admin nacheinander
          aufgerufen und handeln im Verborgenen.</li>
      <li>Ein getöteter Spieler trägt sich nach der Nacht in der App selbst aus.</li>
    </ul>
  </div>


  <!-- ═══════════════════════════════════════════
       5. Bürgerversammlung
  ═══════════════════════════════════════════ -->
  <div class="section-sep"><span>Bürgerversammlung</span></div>

  <div class="step-card animate-in" style="animation-delay:.13s">
    <div class="step-num">4</div>
    <h3>Bürgerversammlung einberufen</h3>
    <p>
      Als lebender Spieler kannst du im Spiel-Fenster auf <strong>„Bürgerversammlung
      einberufen"</strong> tippen. Die Versammlung startet zur nächsten vollen Stunde —
      alle Spieler erhalten eine Push-Benachrichtigung. Nur eine Versammlung gleichzeitig
      ist möglich.
    </p>
  </div>

  <div class="step-card animate-in" style="animation-delay:.15s">
    <div class="step-num">5</div>
    <h3>Abstimmen & Beenden</h3>
    <p>
      Die Versammlung läuft, bis sie <strong>der Einberufende oder ein Admin</strong> beendet.
      Wer angeklagt wird, entscheidet das Dorf per Handzeichen — der Admin trägt das Ergebnis
      in der App ein. Wird niemand hingerichtet, endet die Versammlung einfach.
    </p>
  </div>

  <div class="tip-box animate-in" style="animation-delay:.16s">
    <strong>💡 Tipp:</strong> Gut reden und geschickt bluffen lohnt sich. Mörder müssen
    unverdächtig wirken — Bürger müssen Lügen erkennen. Beobachte auch, wie jemand
    <em>reagiert</em>, nicht nur was er sagt.
  </div>


  <!-- ═══════════════════════════════════════════
       6. Tod & Niederlage
  ═══════════════════════════════════════════ -->
  <div class="section-sep"><span>Tod & Sieg</span></div>

  <div class="step-card animate-in" style="animation-delay:.17s">
    <div class="step-num">6</div>
    <h3>Wenn du stirbst</h3>
    <p>
      Wurdest du in der Nacht getötet oder durch eine Abstimmung hingerichtet, wählst du
      im Spiel-Fenster <strong>„Ich bin tot"</strong>. Du wirst aus der Spielerliste
      ausgetragen. Tote Spieler schweigen — kein Hinweis, kein Bluff, keine Bürgerversammlung.
    </p>
  </div>

  <div class="card animate-in" style="animation-delay:.18s">
    <div class="section-title">🏆 Siegbedingungen</div>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:.75rem;margin-top:.75rem">

      <div style="background:rgba(16,185,129,.08);border:1px solid rgba(16,185,129,.25);
                  border-radius:var(--radius-md);padding:1rem">
        <div style="font-size:1.4rem;margin-bottom:.35rem">🟢 Bürger</div>
        <div class="text-sm" style="color:var(--text-dim)">
          Alle Mörder sind tot. Bürger, Hellseherin, Detektiv, Nekromant, Gunslinger,
          Sheriff und Promi gewinnen gemeinsam.
        </div>
      </div>

      <div style="background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.25);
                  border-radius:var(--radius-md);padding:1rem">
        <div style="font-size:1.4rem;margin-bottom:.35rem">🔴 Mörder</div>
        <div class="text-sm" style="color:var(--text-dim)">
          Die Mörder sind genauso viele (oder mehr) wie die übrigen lebenden Spieler.
        </div>
      </div>

      <div style="background:rgba(251,191,36,.08);border:1px solid rgba(251,191,36,.25);
                  border-radius:var(--radius-md);padding:1rem">
        <div style="font-size:1.4rem;margin-bottom:.35rem">🟡 Dodo</div>
        <div class="text-sm" style="color:var(--text-dim)">
          Der Dodo gewinnt alleine, wenn er durch eine Abstimmung hingerichtet wird.
        </div>
      </div>

      <div style="background:rgba(139,92,246,.08);border:1px solid rgba(139,92,246,.25);
                  border-radius:var(--radius-md);padding:1rem">
        <div style="font-size:1.4rem;margin-bottom:.35rem">💜 Das Paar</div>
        <div class="text-sm" style="color:var(--text-dim)">
          Beide überleben bis zum Ende, egal ob Bürger oder Mörder siegen.
        </div>
      </div>

    </div>
  </div>

  <div class="warn-box animate-in" style="animation-delay:.2s">
    <strong>⚠️ Wichtig:</strong> Spieler, die tot sind, dürfen keine Hinweise geben,
    nicht flüstern und keine Versammlung einberufen — auch nicht per Chat oder Geste.
    Das macht das Spiel fair.
  </div>


  <!-- ═══════════════════════════════════════════
       7. Nachrichten
  ═══════════════════════════════════════════ -->
  <div class="section-sep"><span>Nachrichten</span></div>

  <div class="step-card animate-in" style="animation-delay:.205s">
    <div class="step-num">7</div>
    <h3>Fragen an den Spielleiter</h3>
    <p>
      Im Spielfenster gibt es zwei Buttons: <strong>„Frage stellen"</strong> öffnet ein
      Eingabefeld — deine Frage geht direkt an den Admin, ohne dass andere Spieler sie sehen.
      Unter <strong>„Posteingang"</strong> siehst du alle deine bisherigen Fragen und die
      Antworten des Admins. Neue Antworten werden dir per Badge und Toast-Meldung signalisiert.
    </p>
  </div>

  <!-- ═══════════════════════════════════════════
       8. Optionen
  ═══════════════════════════════════════════ -->
  <div class="section-sep"><span>Optionen</span></div>

  <div class="card animate-in" style="animation-delay:.21s">
    <div class="section-title">⚙️ Deine Einstellungen</div>
    <p class="text-dim text-sm" style="margin-bottom:.75rem;line-height:1.6">
      Über den <strong>⚙️ Optionen</strong>-Tab erreichst du deine persönlichen Einstellungen.
      Alle Werte werden lokal auf deinem Gerät gespeichert — keine Auswirkung auf andere Spieler.
    </p>
    <div style="display:grid;gap:.5rem">
      <div style="padding:.6rem .75rem;background:var(--bg);border:1px solid var(--border);border-radius:var(--radius-md)">
        <div class="text-sm" style="font-weight:600;margin-bottom:.2rem">🎨 Theme</div>
        <div class="text-dim text-xs">5 Designs zur Auswahl: Gothic, Vista, Mittelalter, Minimal, Crystal.</div>
      </div>
      <div style="padding:.6rem .75rem;background:var(--bg);border:1px solid var(--border);border-radius:var(--radius-md)">
        <div class="text-sm" style="font-weight:600;margin-bottom:.2rem">🌤️ Tag/Nacht-Atmosphäre</div>
        <div class="text-dim text-xs">An/Aus — passt den Hintergrund automatisch der Tageszeit an. Nur optisch, kein Einfluss auf das Spiel.</div>
      </div>
      <div style="padding:.6rem .75rem;background:var(--bg);border:1px solid var(--border);border-radius:var(--radius-md)">
        <div class="text-sm" style="font-weight:600;margin-bottom:.2rem">✨ Visuelle Effekte</div>
        <div class="text-dim text-xs">Partikel, Nebel, Phasen-Überblendung, Karten-Flammeneffekt, Schädelregen — alle einzeln ein-/ausschaltbar.</div>
      </div>
      <div style="padding:.6rem .75rem;background:var(--bg);border:1px solid var(--border);border-radius:var(--radius-md)">
        <div class="text-sm" style="font-weight:600;margin-bottom:.2rem">🎵 Hintergrundmusik</div>
        <div class="text-dim text-xs">Falls vom Admin aktiviert: Play/Stop-Buttons erscheinen im Spielfenster unten links.</div>
      </div>
    </div>
  </div>

  <!-- ═══════════════════════════════════════════
       9. Benachrichtigungen
  ═══════════════════════════════════════════ -->
  <div class="section-sep"><span>Benachrichtigungen</span></div>

  <div class="card animate-in" style="animation-delay:.21s">
    <div class="section-title">🔔 Push-Benachrichtigungen</div>
    <p class="text-dim text-sm" style="line-height:1.7;margin-bottom:.75rem">
      <?= e(APP_NAME) ?> sendet Push-Benachrichtigungen direkt auf dein Gerät — zum Beispiel
      wenn das Spiel startet, eine Bürgerversammlung einberufen wird oder der Admin dir antwortet.
    </p>

    <div style="display:grid;gap:.75rem">

      <div style="background:rgba(16,185,129,.06);border:1px solid rgba(16,185,129,.2);
                  border-radius:var(--radius-md);padding:1rem">
        <div style="font-weight:600;margin-bottom:.4rem">✅ Aktivierung</div>
        <p class="text-dim text-sm" style="line-height:1.6;margin:0">
          Beim ersten Öffnen erscheint unten auf der Seite ein Banner
          <em>„Benachrichtigungen aktivieren?"</em>. Klicke <strong>Ja, aktivieren</strong>
          und bestätige die Browser-Abfrage mit <strong>Erlauben</strong>. Fertig.
        </p>
      </div>

      <div style="background:rgba(251,191,36,.06);border:1px solid rgba(251,191,36,.2);
                  border-radius:var(--radius-md);padding:1rem">
        <div style="font-weight:600;margin-bottom:.4rem">⚠️ Kein Banner sichtbar?</div>
        <p class="text-dim text-sm" style="line-height:1.6;margin:0">
          Benachrichtigungen funktionieren nur über <strong>HTTPS</strong> und nicht über eine
          reine IP-Adresse (z.B. <code>192.168.x.x</code>). Rufe die App stattdessen über die
          konfigurierte Domain auf. Falls du sie früher abgelehnt hast, setze die Berechtigung
          im Browser zurück: Adressleiste → Schloss-Symbol → Benachrichtigungen → Erlauben.
        </p>
      </div>

    </div>
  </div>

  <!-- iPhone-Sonderhinweis -->
  <div class="card animate-in" style="animation-delay:.22s;border-color:rgba(59,130,246,.3)">
    <div class="section-title" style="color:#60a5fa">📱 iPhone / iPad — Besonderer Hinweis</div>
    <p class="text-dim text-sm" style="line-height:1.7;margin-bottom:.75rem">
      Push-Benachrichtigungen auf Apple-Geräten erfordern <strong>iOS 16.4 oder neuer</strong>.
      Außerdem muss die App als Webapp auf dem Home-Bildschirm installiert sein — direkt aus
      Safari aufgerufen funktioniert Push <strong>nicht</strong>.
    </p>

    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:.75rem">

      <div style="text-align:center;padding:1rem;background:var(--bg);border:1px solid var(--border);border-radius:var(--radius-md)">
        <div style="font-size:2rem;margin-bottom:.4rem">1️⃣</div>
        <div class="text-sm" style="font-weight:600;margin-bottom:.25rem">Safari öffnen</div>
        <div class="text-dim text-xs">Rufe die App-URL in Safari auf — <em>nicht</em> Chrome oder Firefox auf iOS.</div>
      </div>

      <div style="text-align:center;padding:1rem;background:var(--bg);border:1px solid var(--border);border-radius:var(--radius-md)">
        <div style="font-size:2rem;margin-bottom:.4rem">2️⃣</div>
        <div class="text-sm" style="font-weight:600;margin-bottom:.25rem">Teilen-Symbol tippen</div>
        <div class="text-dim text-xs">Das Viereck mit Pfeil nach oben unten in der Safari-Leiste.</div>
      </div>

      <div style="text-align:center;padding:1rem;background:var(--bg);border:1px solid var(--border);border-radius:var(--radius-md)">
        <div style="font-size:2rem;margin-bottom:.4rem">3️⃣</div>
        <div class="text-sm" style="font-weight:600;margin-bottom:.25rem">„Zum Home-Bildschirm"</div>
        <div class="text-dim text-xs">Im Share-Menü nach unten scrollen → „Zum Home-Bildschirm" auswählen.</div>
      </div>

      <div style="text-align:center;padding:1rem;background:var(--bg);border:1px solid var(--border);border-radius:var(--radius-md)">
        <div style="font-size:2rem;margin-bottom:.4rem">4️⃣</div>
        <div class="text-sm" style="font-weight:600;margin-bottom:.25rem">App öffnen</div>
        <div class="text-dim text-xs">Öffne die App über das neue Icon auf dem Home-Bildschirm. Jetzt funktioniert Push.</div>
      </div>

    </div>

    <div class="warn-box" style="margin-top:.75rem">
      <strong>⚠️ iOS 16.3 oder älter:</strong> Push-Benachrichtigungen werden leider nicht
      unterstützt. Bitte iOS aktualisieren oder ein anderes Gerät verwenden.
    </div>
  </div>


  <!-- Fußzeile -->
  <div style="text-align:center;margin-top:2rem;padding-bottom:1rem;display:flex;gap:.75rem;justify-content:center;flex-wrap:wrap" class="no-print">
    <a href="<?= APP_URL ?>/docs/" class="btn btn--ghost">← Zurück zur Übersicht</a>
    <button onclick="window.print()" class="btn btn--ghost">🖨️ Als PDF speichern</button>
  </div>

</div>

<style media="print">
  .nav-wrapper,.legal-footer,#push-banner,#consent-overlay,.no-print,
  #toast-container{display:none!important}
  body{background:#fff!important;color:#111!important}
  .card{border:1px solid #ddd!important;background:#fff!important;
        break-inside:avoid;page-break-inside:avoid}
  .docs-hero{background:#f8f8f8!important;border:1px solid #ccc!important}
  .phase-box--day{background:#fffbe6!important;border-color:#f5c518!important}
  .phase-box--night{background:#f0ecff!important;border-color:#8b5cf6!important}
  .tip-box{background:#e6fff5!important;border-color:#10b981!important}
  .warn-box{background:#fffbe6!important;border-color:#f5c518!important}
  .role-card{break-inside:avoid;page-break-inside:avoid}
  .role-grid{grid-template-columns:repeat(5,1fr)!important}
  h1,h2,h3,.section-title{color:#111!important}
  .text-dim{color:#444!important}
  a{color:#111!important;text-decoration:none}
  .flow{background:#f8f8f8!important}
  .flow-node{background:#fff!important}
</style>

<?php require TEMPLATE_PATH . '/base_end.php'; ?>
