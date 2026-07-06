<?php
// Copyright (c) 2026 Andreas Vetter
require_once dirname(__DIR__) . '/core/bootstrap.php';
Auth::requireLogin();

$allRoles = Database::query(
    "SELECT name, description, icon_path, active, cooldown FROM roles ORDER BY sort_order, name"
);

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

.role-card--inactive{opacity:.45;filter:grayscale(.7);position:relative}
.role-card--inactive img{opacity:.6}
.role-inactive-badge{position:absolute;top:.45rem;right:.45rem;background:rgba(239,68,68,.18);
  color:#f87171;font-size:.65rem;font-weight:700;border-radius:999px;
  padding:.1rem .45rem;letter-spacing:.04em;border:1px solid rgba(239,68,68,.35)}

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
    <div class="flow-node flow-node--day">💬 Reden &amp; Anklagen<br><small>jederzeit</small></div>
    <div class="flow-arrow">→</div>
    <div class="flow-node flow-node--day" style="border-color:rgba(251,191,36,.9)">🗳️ Bürgerversammlung<br><small>Verdächtigen hinrichten?</small></div>
    <div class="flow-arrow">→</div>
    <div class="flow-node flow-node--night">🔪 Mörder schlagen zu<br><small>jederzeit möglich</small></div>
    <div class="flow-arrow">→</div>
    <div class="flow-node flow-node--end">🏁 Siegbedingung<br><small>erfüllt?</small></div>
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

    <div class="tip-box" style="margin-bottom:1rem">
      <strong>ℹ️ Aktivierte &amp; deaktivierte Rollen:</strong>
      Der Admin kann Rollen für jedes Spiel einzeln aktivieren oder deaktivieren.
      Auf der <strong>Rollen</strong>-Seite siehst du nur die aktuell <strong>aktivierten</strong> Rollen —
      deaktivierte Rollen werden dort nicht angezeigt und können im laufenden Spiel nicht vergeben werden.
    </div>

    <?php if (empty($allRoles)): ?>
      <p class="text-dim text-sm" style="text-align:center;padding:1rem 0">
        Noch keine Rollen in der Datenbank konfiguriert.
      </p>
    <?php else: ?>
    <div class="role-grid">
      <?php foreach ($allRoles as $r):
        $active  = (bool)$r['active'];
        $iconUrl = roleIconUrl($r); // inkl. ?v=-Cache-Busting (filemtime)
      ?>
      <div class="role-card<?= $active ? '' : ' role-card--inactive' ?>">
        <?php if (!$active): ?>
          <div class="role-inactive-badge">Deaktiviert</div>
        <?php endif; ?>
        <img src="<?= e($iconUrl) ?>" alt="<?= e($r['name']) ?>">
        <div class="role-name"><?= e($r['name']) ?></div>
        <?php if ($r['description']): ?>
          <div class="role-desc"><?= e(roleText($r['description'], $r)) ?></div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="tip-box" style="margin-top:1rem">
      <strong>⏱ Fähigkeiten mit Abklingzeit:</strong>
      Hat deine Rolle einen Cooldown (z.&nbsp;B. Mörder, Hellseherin), findest du auf deiner
      Statuskarte im Spiel-Fenster einen Fähigkeit-Button. Drücke ihn, sobald du deine
      Fähigkeit in der echten Welt eingesetzt hast — die App zählt dann die Abklingzeit
      herunter, bis du sie wieder benutzen darfst.
    </div>

    <div class="tip-box" style="margin-top:.75rem">
      <strong>🔮 Untersuchen mit dauerhafter Rollensicht (z.&nbsp;B. Hellseherin):</strong>
      Bei Rollen mit Rollensicht läuft es so: Untersuche einen Spieler in der echten Welt
      (zeige ihm deine Karte — er muss dir seine Rolle zeigen). Tippe danach im Spiel-Fenster
      den untersuchten Spieler im <strong>Dorfbewohner-Block</strong> an (wie beim Anklagen)
      und drücke <strong>„🔮 Spieler untersuchen"</strong>. Nach der Bestätigung merkt sich
      die App die Rolle: Sie bleibt für dich <strong>dauerhaft</strong> in der Spielerliste
      sichtbar — auch nach Neuladen oder auf einem anderen Gerät. Erst danach startet dein
      Cooldown. Nur du siehst diese Information, kein anderer Spieler.
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
      <li>Wer getötet wurde, wird bekannt gegeben und trägt sich in der App selbst aus.</li>
      <li>Zwei lebende Spieler zusammen können eine <strong>Bürgerversammlung einberufen</strong>
          (einer beantragt, ein zweiter unterstützt), um Verdächtige anzuklagen und per
          Handzeichen hinrichten zu lassen.</li>
      <li>Der Admin verwaltet die Abstimmung und trägt das Ergebnis in die App ein.</li>
    </ul>
  </div>

  <div class="phase-box phase-box--night animate-in" style="animation-delay:.12s">
    <h3>🌕 Nacht-Phase — Das Dorf schläft</h3>
    <ul>
      <li>Der Tag-Nacht-Wechsel ist <strong>nur optisch</strong> — er ändert den Hintergrund und die Atmosphäre im Spielfenster.</li>
      <li>Die Mörder können <strong>jederzeit</strong> (Tag oder Nacht) die Mordwaffe einsetzen — es gibt keine Phasensperre.</li>
      <li>Sonderrollen (Hellseherin, Detektiv, Nekromant …) handeln ebenfalls unabhängig von der Phase, koordiniert durch den Admin.</li>
      <li>Ein getöteter Spieler trägt sich in der App selbst als tot ein.</li>
    </ul>
  </div>


  <!-- ═══════════════════════════════════════════
       5. Bürgerversammlung
  ═══════════════════════════════════════════ -->
  <div class="section-sep"><span>Bürgerversammlung</span></div>

  <div class="step-card animate-in" style="animation-delay:.13s">
    <div class="step-num">4</div>
    <h3>Bürgerversammlung einberufen — zu zweit!</h3>
    <p>
      Als lebender Spieler kannst du im Spiel-Fenster auf <strong>„Bürgerversammlung
      einberufen"</strong> tippen — damit stellst du einen <strong>Antrag</strong>, den alle
      sehen. Erst wenn ein <strong>zweiter Spieler</strong> den Antrag unterstützt, steht die
      Versammlung fest: Sie startet zur nächsten vollen Stunde, alle Spieler erhalten eine
      Push-Benachrichtigung. Du kannst deinen Antrag jederzeit zurückziehen. Nur eine
      Versammlung gleichzeitig ist möglich.
    </p>
  </div>

  <div class="step-card animate-in" style="animation-delay:.15s">
    <div class="step-num">5</div>
    <h3>Anklagen, Abstimmen & Beenden</h3>
    <p>
      <strong>Anklagen sind nur möglich, während die Versammlung läuft</strong> — dann
      erscheint die Anklage-Karte im Spiel-Fenster und du wählst einen Spieler aus der
      Liste. Die Versammlung läuft, bis einer der <strong>beiden Einberufer oder die
      Spielleitung</strong> sie beendet. Wer hingerichtet wird, entscheidet das Dorf per
      Handzeichen — der Admin trägt das Ergebnis in der App ein. Wird niemand hingerichtet,
      endet die Versammlung einfach.
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
          Stirbt einer der beiden, stirbt der andere automatisch mit ("vor Kummer gestorben").
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
    <p class="text-dim text-sm">
      ⚠️ Beantwortete Fragen kann der Admin anonym in die öffentliche FAQ übernehmen —
      dein Name erscheint dabei nie, aber der Text deiner Frage wird unverändert
      übernommen. Schreib deshalb keine Namen oder andere persönlichen Angaben hinein.
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
      Sie werden an deinem Konto gespeichert und gelten damit auf allen deinen Geräten —
      keine Auswirkung auf andere Spieler.
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
        <div class="text-sm" style="font-weight:600;margin-bottom:.2rem">🔒 Rollenkarte beim Öffnen zeigen</div>
        <div class="text-dim text-xs">Standardmäßig aus. Wenn aktiviert, zeigt die App beim Aufrufen/Neuladen sofort deine Rollenkarte, bevor das Spielfenster sichtbar wird — nützlich, wenn du dich neu einloggst und jemand mitsehen könnte, der deine Rolle nicht kennen soll.</div>
      </div>
      <div style="padding:.6rem .75rem;background:var(--bg);border:1px solid var(--border);border-radius:var(--radius-md)">
        <div class="text-sm" style="font-weight:600;margin-bottom:.2rem">🔒 Auto-Timeout zur Rollenkarte</div>
        <div class="text-dim text-xs">Standardmäßig aus. Wenn aktiviert (1–10 Minuten), öffnet das Spielfenster automatisch deine Rollenkarte, sobald du so lange nichts eingegeben hast — Sichtschutz, falls dein Handy offen herumliegt. Jede Berührung, Taste oder jedes Scrollen setzt den Timer zurück.</div>
      </div>
      <div style="padding:.6rem .75rem;background:var(--bg);border:1px solid var(--border);border-radius:var(--radius-md)">
        <div class="text-sm" style="font-weight:600;margin-bottom:.2rem">✨ Visuelle Effekte</div>
        <div class="text-dim text-xs">Partikel, Nebel, Phasen-Überblendung, Karten-Flammeneffekt, Schädelregen — alle einzeln ein-/ausschaltbar.</div>
      </div>
      <div style="padding:.6rem .75rem;background:var(--bg);border:1px solid var(--border);border-radius:var(--radius-md)">
        <div class="text-sm" style="font-weight:600;margin-bottom:.2rem">🔄 Ladeintervall</div>
        <div class="text-dim text-xs">Wie oft sich Spielstatus, Listen und Statistiken automatisch aktualisieren (3–20 Sekunden). Der Countdown oben in der Mitte zeigt die Zeit bis zur nächsten Aktualisierung.</div>
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
