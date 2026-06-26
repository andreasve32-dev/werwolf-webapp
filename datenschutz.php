<?php
// Copyright (c) 2026 Andreas Vetter
require_once __DIR__ . '/core/bootstrap.php';

$page = ['title' => 'Datenschutzerklärung', 'skip_consent' => true];
require TEMPLATE_PATH . '/base.php';
?>

<main class="container page-wrap" style="max-width:720px;padding-top:2rem">

  <div class="card card--glow animate-in">
    <h1 style="margin-bottom:1.5rem">Datenschutzerklärung</h1>

    <p class="text-sm text-dim mb-3" style="line-height:1.7">
      Stand: Juni 2026 &mdash; Diese Erklärung gilt für die Webanwendung <?= e(APP_NAME) ?>,
      betrieben als privates, nicht-kommerzielles Projekt.
    </p>

    <!-- 1 -->
    <section class="mb-3">
      <h2 class="section-title">1. Verantwortlicher (Art. 13 Abs. 1 lit. a DSGVO)</h2>
      <p class="text-sm" style="line-height:1.8">
        <strong>Ihr Name</strong><br>
        Ihre Straße und Hausnummer<br>
        Ihre PLZ und Ort, Deutschland<br>
        E-Mail: <a href="mailto:ihre@email.de">ihre@email.de</a>
      </p>
    </section>

    <!-- 2 -->
    <section class="mb-3">
      <h2 class="section-title">2. Verarbeitete Daten &amp; Zweck</h2>

      <h3 class="text-sm mb-1" style="font-weight:600;margin-top:1rem">2.1 Registrierung und Konto</h3>
      <p class="text-sm text-dim" style="line-height:1.7">
        Bei der Registrierung werden <strong>Login-Name, Anzeigename und Passwort</strong>
        (ausschließlich als kryptografischer bcrypt-Hash) gespeichert.
        Das Klartextpasswort wird nie gespeichert.<br>
        <em>Rechtsgrundlage:</em> Art. 6 Abs. 1 lit. b DSGVO (Vertragserfüllung).
      </p>

      <h3 class="text-sm mb-1" style="font-weight:600;margin-top:1rem">2.2 Spieldaten</h3>
      <p class="text-sm text-dim" style="line-height:1.7">
        Im Spielverlauf werden Spielergebnisse, Rollenzuweisungen, Abstimmungen, Todesfälle
        und Nachrichten zwischen Spielern und Spielleitung gespeichert. Diese Daten sind für
        die Spielfunktion notwendig.<br>
        <em>Rechtsgrundlage:</em> Art. 6 Abs. 1 lit. b DSGVO.
      </p>

      <h3 class="text-sm mb-1" style="font-weight:600;margin-top:1rem">2.3 Technische Sitzungsdaten (Cookies)</h3>
      <p class="text-sm text-dim" style="line-height:1.7">
        Es werden ausschließlich technisch notwendige Cookies gesetzt:
      </p>
      <div style="overflow-x:auto;margin:.5rem 0">
        <table class="table text-sm">
          <thead><tr><th>Cookie</th><th>Zweck</th><th>Dauer</th></tr></thead>
          <tbody>
            <tr>
              <td><code><?= e(SESSION_NAME) ?></code></td>
              <td>Authentifizierung (PHP-Session)</td>
              <td>7 Tage</td>
            </tr>
            <tr>
              <td><code>ww_player</code></td>
              <td>Spielerdaten für JavaScript</td>
              <td>Sitzungslaufzeit</td>
            </tr>
          </tbody>
        </table>
      </div>
      <p class="text-sm text-dim" style="line-height:1.7">
        Es werden <strong>keine Tracking- oder Werbe-Cookies</strong> eingesetzt.<br>
        <em>Rechtsgrundlage:</em> § 25 Abs. 2 TTDSG (technisch notwendige Cookies).
      </p>

      <h3 class="text-sm mb-1" style="font-weight:600;margin-top:1rem">2.4 Lokaler Browserspeicher (localStorage)</h3>
      <p class="text-sm text-dim" style="line-height:1.7">
        Im lokalen Speicher des Browsers werden folgende Präferenzen gespeichert,
        die das Gerät nicht verlassen:
      </p>
      <div style="overflow-x:auto;margin:.5rem 0">
        <table class="table text-sm">
          <thead><tr><th>Schlüssel</th><th>Inhalt</th></tr></thead>
          <tbody>
            <tr><td><code>ww_theme</code></td><td>Design-Einstellung</td></tr>
            <tr><td><code>ww_music</code>, <code>ww_music_t</code></td><td>Musik-Status und -position</td></tr>
            <tr><td><code>ww_fx_vol</code></td><td>Effekt-Lautstärke</td></tr>
            <tr><td><code>ww_consent</code></td><td>Zustimmung zu Nutzungsbedingungen und Datenschutz</td></tr>
            <tr><td><code>ww_push_dismissed</code></td><td>Entscheidung zum Push-Hinweis</td></tr>
          </tbody>
        </table>
      </div>

      <h3 class="text-sm mb-1" style="font-weight:600;margin-top:1rem">2.5 Push-Benachrichtigungen (optional)</h3>
      <p class="text-sm text-dim" style="line-height:1.7">
        Mit ausdrücklicher Einwilligung können Browser-Benachrichtigungen (Web Push) aktiviert werden.
        Dabei wird ein <strong>Push-Abonnement</strong> (bestehend aus einer vom Browser erzeugten
        Endpunkt-URL und kryptografischen Schlüsseln) auf dem Server gespeichert, um Hinweise
        bei Spielereignissen (Spielstart, Nachrichtenantwort) zustellen zu können.
        Das Abonnement enthält <em>keine personenbezogenen Inhalte</em>; es wird ausschließlich
        serverseitig für den Versand verwendet.<br>
        Die Benachrichtigungsberechtigung kann jederzeit in den Browsereinstellungen widerrufen werden.
        Das gespeicherte Abonnement wird nach Widerruf automatisch gelöscht.<br>
        <em>Rechtsgrundlage:</em> Art. 6 Abs. 1 lit. a DSGVO (Einwilligung).
      </p>

      <h3 class="text-sm mb-1" style="font-weight:600;margin-top:1rem">2.6 Server-Protokolldaten</h3>
      <p class="text-sm text-dim" style="line-height:1.7">
        Der Webserver protokolliert automatisch Zugriffe mit IP-Adresse, Zeitpunkt,
        aufgerufener URL und HTTP-Statuscode. Diese Daten werden für maximal 14 Tage
        gespeichert und ausschließlich zur Fehlerdiagnose und Sicherheitsüberwachung genutzt.<br>
        <em>Rechtsgrundlage:</em> Art. 6 Abs. 1 lit. f DSGVO (berechtigtes Interesse an
        Betrieb und Sicherheit des Dienstes).
      </p>
    </section>

    <!-- 3 -->
    <section class="mb-3">
      <h2 class="section-title">3. Weitergabe an Dritte</h2>
      <p class="text-sm text-dim" style="line-height:1.7">
        Es werden <strong>keine personenbezogenen Daten an Dritte</strong> weitergegeben,
        verkauft oder vermietet. Es werden keine Dienste von Drittanbietern eingebunden
        (kein Google Analytics, keine CDNs, keine sozialen Netzwerke).
        Alle Daten verbleiben auf dem Server des Betreibers.<br>
        Einzige Ausnahme: Push-Benachrichtigungen werden über den
        <strong>Browser-Push-Dienst</strong> des jeweiligen Geräteherstellers übermittelt
        (z.&nbsp;B. Google FCM für Android-Chrome, Mozilla für Firefox). Dabei werden
        nur die vom Browser bereitgestellten Endpunkt-URLs verwendet — keine
        inhaltlichen Nutzerdaten.
      </p>
    </section>

    <!-- 4 -->
    <section class="mb-3">
      <h2 class="section-title">4. Speicherdauer</h2>
      <p class="text-sm text-dim" style="line-height:1.7">
        Konto- und Spieldaten werden gespeichert, solange das Konto aktiv ist.
        Nach Löschung eines Kontos werden die zugehörigen Daten unverzüglich entfernt.
        Push-Abonnements werden bei Widerruf oder bei Ungültigkeit (HTTP 410 vom Push-Dienst)
        automatisch gelöscht. Server-Logs werden nach spätestens 14 Tagen gelöscht.
      </p>
    </section>

    <!-- 5 -->
    <section class="mb-3">
      <h2 class="section-title">5. Betroffenenrechte (Art. 15–22 DSGVO)</h2>
      <p class="text-sm text-dim" style="line-height:1.7">Du hast das Recht auf:</p>
      <ul class="text-sm text-dim" style="line-height:1.9;padding-left:1.4rem">
        <li><strong>Auskunft</strong> (Art. 15) — welche Daten über dich gespeichert sind</li>
        <li><strong>Berichtigung</strong> (Art. 16) — Korrektur unrichtiger Daten</li>
        <li><strong>Löschung</strong> (Art. 17) — Entfernung deiner Daten</li>
        <li><strong>Einschränkung der Verarbeitung</strong> (Art. 18)</li>
        <li><strong>Widerspruch</strong> (Art. 21) — gegen Verarbeitungen auf Basis berechtigter Interessen</li>
        <li><strong>Widerruf einer Einwilligung</strong> (Art. 7 Abs. 3) — jederzeit für Push-Benachrichtigungen</li>
        <li><strong>Datenübertragbarkeit</strong> (Art. 20) — soweit technisch umsetzbar</li>
      </ul>
      <p class="text-sm text-dim mt-1" style="line-height:1.7">
        Zur Ausübung dieser Rechte wende dich an:
        <a href="mailto:ihre@email.de">ihre@email.de</a>
      </p>
    </section>

    <!-- 6 -->
    <section class="mb-3">
      <h2 class="section-title">6. Beschwerderecht bei der Aufsichtsbehörde</h2>
      <p class="text-sm text-dim" style="line-height:1.7">
        Du hast das Recht, dich bei einer Datenschutz-Aufsichtsbehörde zu beschweren.
        Die zuständige Behörde für Baden-Württemberg ist der
        <strong>Landesbeauftragte für den Datenschutz und die Informationsfreiheit
        Baden-Württemberg (LfDI BW)</strong>, Königstraße 10a, 70173 Stuttgart.
      </p>
    </section>

    <!-- 7 -->
    <section>
      <h2 class="section-title">7. Datensicherheit</h2>
      <p class="text-sm text-dim" style="line-height:1.7">
        Passwörter werden ausschließlich als bcrypt-Hash gespeichert — das Klartextpasswort
        ist technisch nicht wiederherstellbar. Datenbankzugriffe erfolgen ausschließlich
        über Prepared Statements (PDO) zum Schutz vor SQL-Injection. Sessions sind mit
        <code>HttpOnly</code> und <code>SameSite=Lax</code> gesichert. VAPID-Schlüssel
        für Push-Benachrichtigungen werden serverseitig gespeichert und nie an den Client
        übertragen (nur der öffentliche Schlüssel wird benötigt).
      </p>
    </section>

  </div>

  <div class="text-center mt-2 mb-3">
    <?php if (Auth::check()): ?>
      <a href="<?= APP_URL ?>/game.php" class="btn btn--ghost">← Zurück zum Spiel</a>
    <?php else: ?>
      <a href="<?= APP_URL ?>/index.php" class="btn btn--ghost">← Zur Anmeldung</a>
    <?php endif; ?>
  </div>

</main>

<?php require TEMPLATE_PATH . '/base_end.php'; ?>
