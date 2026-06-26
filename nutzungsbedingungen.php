<?php
// Copyright (c) 2026 Andreas Vetter
require_once __DIR__ . '/core/bootstrap.php';

$page = ['title' => 'Nutzungsbedingungen', 'skip_consent' => true];
require TEMPLATE_PATH . '/base.php';
?>

<main class="container page-wrap" style="max-width:720px;padding-top:2rem">

  <div class="card card--glow animate-in">
    <h1 style="margin-bottom:1.5rem">Nutzungsbedingungen</h1>

    <p class="text-sm text-dim mb-3" style="line-height:1.7">
      Stand: Juni 2026 &mdash; Mit der Registrierung oder der aktiven Nutzung des Dienstes
      erkennst du diese Bedingungen an.
    </p>

    <section class="mb-3">
      <h2 class="section-title">1. Geltungsbereich</h2>
      <p class="text-sm text-dim" style="line-height:1.7">
        Diese Nutzungsbedingungen gelten für alle Nutzer der <?= e(APP_NAME) ?>-Webanwendung
        (nachfolgend „Dienst") des Betreibers (nachfolgend „Betreiber").
        Der Dienst richtet sich ausschließlich an einen geschlossenen, vorab eingeladenen
        Teilnehmerkreis.
      </p>
    </section>

    <section class="mb-3">
      <h2 class="section-title">2. Beschreibung des Dienstes</h2>
      <p class="text-sm text-dim" style="line-height:1.7">
        <?= e(APP_NAME) ?> ist eine webbasierte Unterstützungsanwendung für das
        Gesellschaftsspiel „Werwolf von Düsterwald". Der Dienst ermöglicht die digitale
        Verwaltung von Spielrunden (Spielerliste, Rollenzuweisung, Abstimmungen, Todesfälle,
        Kommunikation zwischen Spielern und Spielleitung) im privaten, nicht-kommerziellen
        Umfeld. Der Dienst ersetzt nicht das eigentliche Kartenspiel und erhebt keinen Anspruch
        auf Vollständigkeit der Spielregeln.
      </p>
    </section>

    <section class="mb-3">
      <h2 class="section-title">3. Zustimmung und Datenschutz</h2>
      <p class="text-sm text-dim" style="line-height:1.7">
        Durch die Nutzung des Dienstes stimmst du zu, dass zur technischen Bereitstellung
        des Dienstes notwendige Daten verarbeitet werden. Dazu zählen Sitzungs-Cookies,
        Kontodaten und Spieldaten. Die genaue Beschreibung findest du in der
        <a href="<?= APP_URL ?>/datenschutz.php">Datenschutzerklärung</a>.<br>
        Push-Benachrichtigungen sind freiwillig und erfordern deine gesonderte Einwilligung
        direkt im Browser.
      </p>
    </section>

    <section class="mb-3">
      <h2 class="section-title">4. Registrierung und Konto</h2>
      <p class="text-sm text-dim" style="line-height:1.7">
        Zur Nutzung des Dienstes ist ein Konto erforderlich, das ausschließlich vom Betreiber
        eingerichtet wird. Du bist verpflichtet, einen angemessenen Anzeigenamen zu verwenden.
        Du bist verantwortlich für die Sicherheit deines Passworts und alle Aktivitäten
        unter deinem Konto. Ein Konto darf nur von einer natürlichen Person genutzt werden.
        Die Weitergabe von Zugangsdaten ist nicht gestattet.
      </p>
    </section>

    <section class="mb-3">
      <h2 class="section-title">5. Erlaubte Nutzung</h2>
      <p class="text-sm text-dim" style="line-height:1.7">
        Der Dienst darf ausschließlich für private Spielrunden genutzt werden.
        Es ist insbesondere untersagt:
      </p>
      <ul class="text-sm text-dim" style="line-height:1.9;padding-left:1.4rem">
        <li>Den Dienst für kommerzielle Zwecke zu nutzen</li>
        <li>Den Dienst zu missbrauchen, zu manipulieren oder zu beschädigen</li>
        <li>Automatisierte Anfragen (Bots, Scraper) zu stellen</li>
        <li>Beleidigende, diskriminierende oder rechtswidrige Inhalte als Spielernamen zu verwenden</li>
        <li>Den Dienst zu nutzen, um Dritte zu schädigen oder in ihre Rechte einzugreifen</li>
        <li>Sicherheitsmechanismen des Dienstes zu umgehen oder zu testen</li>
        <li>Admin-Funktionen missbräuchlich oder außerhalb des Spielkontexts zu nutzen</li>
      </ul>
    </section>

    <section class="mb-3">
      <h2 class="section-title">6. Spielregeln und Fairness</h2>
      <p class="text-sm text-dim" style="line-height:1.7">
        Die Anwendung unterstützt das Spiel technisch. Das faire Einhalten der Spielregeln
        liegt in der Verantwortung der Spieler. Manipulation von Spielergebnissen oder
        missbräuchliche Nutzung von Spielfunktionen ist untersagt. Nachrichten an die
        Spielleitung sind sachlich und respektvoll zu halten.
      </p>
    </section>

    <section class="mb-3">
      <h2 class="section-title">7. Push-Benachrichtigungen</h2>
      <p class="text-sm text-dim" style="line-height:1.7">
        Der Dienst kann optionale Browser-Benachrichtigungen (Web Push) senden,
        um über Spielereignisse zu informieren. Diese Funktion ist freiwillig.
        Du kannst die Erlaubnis jederzeit in den Einstellungen deines Browsers widerrufen.
        Der Betreiber wird Push-Benachrichtigungen ausschließlich für spielrelevante
        Ereignisse (Spielstart, Nachrichtenantworten) verwenden.
      </p>
    </section>

    <section class="mb-3">
      <h2 class="section-title">8. Verfügbarkeit</h2>
      <p class="text-sm text-dim" style="line-height:1.7">
        Der Betreiber übernimmt keine Garantie für eine ununterbrochene Verfügbarkeit
        des Dienstes. Wartungsarbeiten, technische Störungen oder andere Unterbrechungen
        können ohne Vorankündigung auftreten. Ein Anspruch auf Verfügbarkeit besteht nicht.
      </p>
    </section>

    <section class="mb-3">
      <h2 class="section-title">9. Haftungsausschluss</h2>
      <p class="text-sm text-dim" style="line-height:1.7">
        Der Betreiber haftet nicht für:
      </p>
      <ul class="text-sm text-dim" style="line-height:1.9;padding-left:1.4rem">
        <li>Datenverluste durch technische Störungen oder fehlerhafte Bedienung</li>
        <li>Schäden durch unbefugten Zugriff Dritter auf dein Konto</li>
        <li>Entgangene Spielergebnisse oder Unterbrechungen des Spielbetriebs</li>
        <li>Nicht zugestellte oder verspätete Push-Benachrichtigungen</li>
      </ul>
      <p class="text-sm text-dim mt-1" style="line-height:1.7">
        Der Dienst wird ohne jegliche Gewährleistung bereitgestellt.
        Der Betreiber ist eine Privatperson, kein Unternehmen.
      </p>
    </section>

    <section class="mb-3">
      <h2 class="section-title">10. Kontosperrung und -löschung</h2>
      <p class="text-sm text-dim" style="line-height:1.7">
        Der Betreiber behält sich das Recht vor, Konten bei Verstoß gegen diese
        Nutzungsbedingungen ohne Vorankündigung zu sperren oder zu löschen.
        Du kannst die Löschung deines Kontos jederzeit per E-Mail an
        <a href="mailto:ihre@email.de">ihre@email.de</a> beantragen.
      </p>
    </section>

    <section class="mb-3">
      <h2 class="section-title">11. Änderungen der Nutzungsbedingungen</h2>
      <p class="text-sm text-dim" style="line-height:1.7">
        Der Betreiber behält sich vor, diese Nutzungsbedingungen jederzeit zu ändern.
        Wesentliche Änderungen werden den Nutzern beim nächsten Login mitgeteilt.
        Die fortgesetzte Nutzung nach einer Änderung gilt als Zustimmung.
      </p>
    </section>

    <section>
      <h2 class="section-title">12. Anwendbares Recht und Gerichtsstand</h2>
      <p class="text-sm text-dim" style="line-height:1.7">
        Es gilt das Recht der Bundesrepublik Deutschland.
        Gerichtsstand ist, soweit gesetzlich zulässig, der Wohnort des Betreibers.
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
