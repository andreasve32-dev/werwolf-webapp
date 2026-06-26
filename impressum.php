<?php
// Copyright (c) 2026 Andreas Vetter
require_once __DIR__ . '/core/bootstrap.php';

$page = ['title' => 'Impressum', 'skip_consent' => true];
require TEMPLATE_PATH . '/base.php';
?>

<main class="container page-wrap" style="max-width:720px;padding-top:2rem">

  <div class="card card--glow animate-in">
    <h1 style="margin-bottom:1.5rem">Impressum</h1>

    <section class="mb-3">
      <h2 class="section-title">Angaben gemäß § 18 Abs. 2 MStV</h2>
      <p class="text-sm" style="line-height:1.8">
        <strong>Ihr Name</strong><br>
        Ihre Straße und Hausnummer<br>
        Ihre PLZ und Ort<br>
        Deutschland
      </p>
    </section>

    <section class="mb-3">
      <h2 class="section-title">Kontakt</h2>
      <p class="text-sm" style="line-height:1.8">
        E-Mail: <a href="mailto:ihre@email.de">ihre@email.de</a>
      </p>
    </section>

    <section class="mb-3">
      <h2 class="section-title">Art des Angebots</h2>
      <p class="text-sm text-dim" style="line-height:1.7">
        <?= e(APP_NAME) ?> ist ein privates, nicht-kommerzielles Freizeitprojekt.
        Es dient ausschließlich der Unterstützung von Werwolf-Spielrunden im privaten Umfeld
        eines geschlossenen Teilnehmerkreises. Es werden keine Waren oder Dienstleistungen
        angeboten, kein Umsatz erzielt und keine Entgelte erhoben. Der Zugang ist ausschließlich
        auf eingeladene Teilnehmer beschränkt.
      </p>
    </section>

    <section class="mb-3">
      <h2 class="section-title">Haftung für Inhalte</h2>
      <p class="text-sm text-dim" style="line-height:1.7">
        Als Diensteanbieter sind wir gemäß § 7 Abs. 1 TMG für eigene Inhalte auf diesen Seiten
        nach den allgemeinen Gesetzen verantwortlich. Nach §§ 8 bis 10 TMG sind wir als
        Diensteanbieter jedoch nicht verpflichtet, übermittelte oder gespeicherte fremde Informationen
        zu überwachen oder nach Umständen zu forschen, die auf eine rechtswidrige Tätigkeit hinweisen.
        Verpflichtungen zur Entfernung oder Sperrung der Nutzung von Informationen nach den allgemeinen
        Gesetzen bleiben hiervon unberührt. Eine diesbezügliche Haftung ist jedoch erst ab dem
        Zeitpunkt der Kenntnis einer konkreten Rechtsverletzung möglich.
      </p>
    </section>

    <section class="mb-3">
      <h2 class="section-title">Haftung für Links</h2>
      <p class="text-sm text-dim" style="line-height:1.7">
        Unser Angebot enthält Links zu externen Webseiten Dritter, auf deren Inhalte wir keinen
        Einfluss haben. Deshalb können wir für diese fremden Inhalte auch keine Gewähr übernehmen.
        Für die Inhalte der verlinkten Seiten ist stets der jeweilige Anbieter oder Betreiber
        verantwortlich. Rechtswidrige Inhalte waren zum Zeitpunkt der Verlinkung nicht erkennbar.
      </p>
    </section>

    <section>
      <h2 class="section-title">Urheberrecht</h2>
      <p class="text-sm text-dim" style="line-height:1.7">
        Die durch den Betreiber erstellten Inhalte und Werke auf diesen Seiten unterliegen dem
        deutschen Urheberrecht. Die Vervielfältigung, Bearbeitung, Verbreitung und jede Art der
        Verwertung außerhalb der Grenzen des Urheberrechts bedürfen der schriftlichen Zustimmung
        des jeweiligen Autors bzw. Erstellers.
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
