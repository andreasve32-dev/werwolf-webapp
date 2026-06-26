<?php
// Copyright (c) 2026 Andreas Vetter
require_once dirname(__DIR__) . '/core/bootstrap.php';
Auth::requireLogin();
$page = ['title' => 'Hilfe & Anleitung'];
require TEMPLATE_PATH . '/base.php';
?>

<div class="container page-wrap">

  <div class="page-header" style="text-align:center">
    <div style="font-size:3rem;margin-bottom:.5rem">📖</div>
    <h1 style="font-family:var(--font-display);font-size:2rem">Hilfe & Anleitung</h1>
    <p class="page-header__sub">Alles was du über <?= e(APP_NAME) ?> wissen musst</p>
  </div>

  <div class="grid-2" style="gap:1.5rem;max-width:700px;margin:0 auto">

    <a href="<?= APP_URL ?>/docs/spieler.php" style="text-decoration:none">
      <div class="card card--glow animate-in" style="text-align:center;padding:2rem 1.5rem;transition:transform .18s;cursor:pointer"
           onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform=''">
        <div style="font-size:3.5rem;margin-bottom:.75rem">🧑‍🤝‍🧑</div>
        <div style="font-family:var(--font-display);font-size:1.3rem;color:var(--text-bright);margin-bottom:.5rem">
          Spieler-Anleitung
        </div>
        <p class="text-dim text-sm">
          Regeln, Rollen, Spielablauf, Versammlung —<br>alles für neue Spieler erklärt.
        </p>
        <div class="btn btn--primary" style="margin-top:1.25rem;display:inline-block">
          Anleitung lesen →
        </div>
      </div>
    </a>

    <?php if ($player['is_admin'] ?? false): ?>
    <a href="<?= APP_URL ?>/docs/admin.php" style="text-decoration:none">
      <div class="card animate-in" style="text-align:center;padding:2rem 1.5rem;transition:transform .18s;cursor:pointer;
           border-color:rgba(168,85,247,.3);animation-delay:.06s"
           onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform=''">
        <div style="font-size:3.5rem;margin-bottom:.75rem">⚙️</div>
        <div style="font-family:var(--font-display);font-size:1.3rem;color:var(--text-bright);margin-bottom:.5rem">
          Admin-Handbuch
        </div>
        <p class="text-dim text-sm">
          Spiel einrichten, Runden leiten, Phasen wechseln —<br>alles für den Spielleiter.
        </p>
        <div class="btn btn--ghost" style="margin-top:1.25rem;display:inline-block">
          Handbuch öffnen →
        </div>
      </div>
    </a>
    <?php endif; ?>

  </div>

</div>

<?php require TEMPLATE_PATH . '/base_end.php'; ?>
