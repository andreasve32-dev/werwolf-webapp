<?php
// Copyright (c) 2026 Andreas Vetter
// Wiederverwendbare Render-Funktionen für game.php — genutzt sowohl beim
// initialen Seitenaufbau als auch von api/game.php (get_players) fürs Polling.

if (!function_exists('render_my_status_actions')) {
function render_my_status_actions(array|false|null $game, array|false|null $myGP): string {
    $status = $game['status'] ?? null;
    ob_start();
    ?>
    <?php if ($status === 'lobby'): ?>
      <?php if (!$myGP): ?>
        <button class="btn btn--primary btn--full" onclick="joinGame()">
          🚪 Dem Spiel beitreten
        </button>
      <?php else: ?>
        <div class="alert alert--info">Du bist beigetreten. Warte auf Spielstart.</div>
      <?php endif; ?>

    <?php elseif ($status === 'running' && !$myGP): ?>
      <div class="alert alert--warn">⚠️ Das Spiel läuft bereits — du kannst nicht mehr beitreten.</div>

    <?php elseif ($status === 'running' && $myGP && !$myGP['is_alive']): ?>
      <div class="alert alert--error">☠ Du bist tot. Beobachte das Geschehen.</div>

    <?php endif; ?>

    <!-- Tot-Melden-Button (nur wenn Spiel läuft und Spieler noch lebt) -->
    <?php if ($status === 'running' && $myGP && $myGP['is_alive']): ?>
    <button class="btn btn--danger btn--full mt-2" onclick="openDeathModal()"
            style="border-style:dashed;opacity:.85">
      ☠️ Meinen Tod melden
    </button>
    <?php endif; ?>
    <?php
    return ob_get_clean();
}
}
