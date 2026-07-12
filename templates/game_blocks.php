<?php
// Copyright (c) 2026 Andreas Vetter
// Wiederverwendbare Render-Funktionen für game.php — genutzt sowohl beim
// initialen Seitenaufbau als auch von api/game.php (get_players) fürs Polling.

if (!function_exists('render_my_status_actions')) {
function render_my_status_actions(array|false|null $game, array|false|null $myGP): string {
    $status = $game['status'] ?? null;

    // Rollen mit linked_death=1 (z.B. Das Paar): kein Automatik-Tod mehr —
    // stattdessen sieht der überlebende Rollenpartner hier einen Hinweis,
    // sobald ein Mitspieler derselben Rolle gestorben ist (zusätzlich zur
    // neutralen Push-Nachricht aus recordDeath()). Verschwindet automatisch,
    // sobald er/sie sich selbst meldet (dann greift die "Du bist tot"-Anzeige).
    $partnerDeathName = null;
    if ($status === 'running' && $myGP && $myGP['is_alive'] && !empty($myGP['role_id'])) {
        $role = Database::queryOne("SELECT linked_death FROM roles WHERE id = ?", [$myGP['role_id']]);
        if ($role && $role['linked_death']) {
            $deadPartner = Database::queryOne(
                "SELECT p.display_name FROM game_players gp JOIN players p ON p.id = gp.player_id
                 WHERE gp.game_id = ? AND gp.role_id = ? AND gp.is_alive = 0 AND gp.player_id != ?
                 ORDER BY gp.id DESC LIMIT 1",
                [$myGP['game_id'], $myGP['role_id'], $myGP['player_id']]
            );
            if ($deadPartner) $partnerDeathName = $deadPartner['display_name'];
        }
    }

    // Rollen mit side_switch=1 (z.B. Schläfer): applySideSwitches() in
    // core/helpers.php stellt bei Ablauf der Zeitspanne role_id automatisch auf
    // die Auffüll-Rolle um und hinterlegt dabei einen role_insights-Eintrag
    // (source='side_switch') — bleibender Hinweis für den Rest der Partie.
    $sideSwitched = false;
    if ($status === 'running' && $myGP && $myGP['is_alive']) {
        $sideSwitched = (bool)Database::queryOne(
            "SELECT id FROM role_insights WHERE game_id = ? AND viewer_player_id = ? AND target_player_id = ? AND source = 'side_switch'",
            [$myGP['game_id'], $myGP['player_id'], $myGP['player_id']]
        );
    }

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

    <!-- Partner-Benachrichtigung (linked_death, z.B. Das Paar) -->
    <?php if ($partnerDeathName !== null): ?>
    <div class="alert alert--warn mt-2">
      💔 <strong><?= e($partnerDeathName) ?></strong> ist gestorben. Du kannst dich jetzt
      jederzeit selbst als tot melden, wenn du bereit dazu bist.
    </div>
    <?php endif; ?>

    <!-- Seitenwechsel-Hinweis (side_switch, z.B. Schläfer) -->
    <?php if ($sideSwitched): ?>
    <div class="alert alert--info mt-2">
      💤 Deine Zeit als verdeckter Killer ist vorbei — du gehörst jetzt zu den Bürgern.
    </div>
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
