<?php
// Copyright (c) 2026 Andreas Vetter
// Oeffentliche Promo-Seite ("Was ist <APP_NAME>?") -- bewusst OHNE templates/base.php:
// eigenstaendige Kino-Trailer-Optik (volle Viewporthoehe, eigenes Nacht-Theme),
// soll nicht von der normalen Seiten-Chrome (Nav/Tab-Bar) durchbrochen werden.
// Oeffentlich erreichbar, kein Login noetig (wie app/datenschutz.php, app/impressum.php).
require_once dirname(__DIR__, 2) . '/core/bootstrap.php';
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="dark">
<title><?= e(APP_NAME) ?> — Was ist das?</title>
<style>
  @import url('https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600;700&family=Crimson+Text:ital,wght@0,400;0,600;1,400&display=swap');

  :root {
    --void:        #07070b;
    --ink:         #cfc9c0;
    --ink-bright:  #f6efdd;
    --ink-dim:     #9a93a6;
    --gold:        #c8a96e;
    --gold-bright: #f0d090;
    --violet:      #8b3fd6;
    --violet-bright:#c084fc;
  }

  * { box-sizing: border-box; }
  html, body {
    height: 100%; margin: 0; padding: 0;
    background: var(--void); color: var(--ink);
    font-family: 'Crimson Text', Georgia, serif;
    overflow: hidden;
  }
  h1, h2, .headline { font-family: 'Cinzel', Georgia, serif; }

  .visually-hidden {
    position: absolute; width: 1px; height: 1px; margin: -1px;
    overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0;
  }

  .stage {
    position: relative; width: 100%; height: 100dvh;
    overflow: hidden; isolation: isolate;
  }

  canvas#fx { position: absolute; inset: 0; width: 100%; height: 100%; display: block; z-index: 1; }

  .moon {
    position: absolute; top: 9%; left: 50%; width: 130px; height: 130px;
    border-radius: 50%; pointer-events: none; z-index: 2;
    background: radial-gradient(circle at 35% 30%, var(--gold-bright), var(--gold) 55%, transparent 78%);
    box-shadow: 0 0 90px 30px rgba(200,169,110,.35);
    transform: translate(-50%, 0) translateX(var(--moon-x, -170px)) translate(calc(var(--px, 0) * 16px), calc(var(--py, 0) * 10px));
    transition: transform 1.6s ease, box-shadow 1.6s ease, background 1.6s ease;
  }
  .stage[data-scene="0"] .moon { --moon-x: -170px; }
  .stage[data-scene="1"] .moon { --moon-x: -85px; }
  .stage[data-scene="2"] .moon {
    --moon-x: 0px;
    background: radial-gradient(circle at 35% 30%, var(--violet-bright), var(--violet) 55%, transparent 78%);
    box-shadow: 0 0 90px 34px rgba(139,63,214,.42);
  }
  .stage[data-scene="3"] .moon {
    --moon-x: 85px;
    background: radial-gradient(circle at 35% 30%, var(--violet-bright), var(--violet) 55%, transparent 78%);
    box-shadow: 0 0 100px 38px rgba(139,63,214,.48);
  }
  .stage[data-scene="4"] .moon {
    --moon-x: 170px;
    background: radial-gradient(circle at 35% 30%, #fff, var(--gold-bright) 45%, var(--gold) 72%, transparent 86%);
    box-shadow: 0 0 130px 48px rgba(240,208,144,.55);
  }

  .vignette {
    position: absolute; inset: 0; pointer-events: none; z-index: 5;
    background:
      radial-gradient(ellipse at center, transparent 34%, rgba(3,3,6,.55) 78%, rgba(3,3,6,.94) 100%);
  }

  /* Kulisse: Huegel-Silhouette + Wolf, nur in der Eroeffnungsszene sichtbar */
  .hill {
    position: absolute; left: 0; right: 0; bottom: 0; height: 16vh; z-index: 4;
    pointer-events: none; opacity: 0; transition: opacity 1.4s ease;
    clip-path: polygon(0% 100%, 0% 58%, 12% 64%, 24% 50%, 38% 62%, 50% 44%, 64% 60%, 78% 48%, 90% 62%, 100% 52%, 100% 100%);
    background: linear-gradient(180deg, #0c0a12, #050308);
  }
  .stage[data-scene="0"] .hill { opacity: 1; }
  .hill__wolf {
    position: absolute; left: 50%; bottom: 62%; transform: translateX(-50%);
    font-size: 2.5rem; line-height: 1;
    filter: brightness(0) drop-shadow(0 0 20px rgba(240,208,144,.6));
  }
  .howl-ring {
    position: absolute; left: 50%; bottom: 10.2vh; z-index: 4;
    width: 10px; height: 10px; border-radius: 50%;
    transform: translate(-50%, 50%);
    border: 2px solid rgba(240,208,144,.55);
    opacity: 0; pointer-events: none;
  }
  .stage.intro .howl-ring { animation: howlOut 1.7s ease-out .35s 1; }
  @keyframes howlOut {
    0%   { opacity: .9; width: 10px; height: 10px; }
    100% { opacity: 0;  width: 320px; height: 320px; }
  }

  /* Wortmarke: einmaliger "Krallenriss"-Reveal beim Laden */
  .headline--hero { clip-path: inset(0 0 0 0); }
  @keyframes wordReveal {
    0%   { clip-path: inset(0 100% 0 0); opacity: .25; }
    100% { clip-path: inset(0 0 0 0);   opacity: 1; }
  }
  .stage.intro .headline--hero { animation: wordReveal 1.15s cubic-bezier(.2,.9,.25,1) .15s both; }

  /* Szenenwechsel: kurzer Krallenriss statt reinem Crossfade */
  .claws { position: absolute; inset: 0; z-index: 15; pointer-events: none; opacity: 0; }
  .claws span {
    position: absolute; top: -12%; height: 130%; width: 5%;
    background: linear-gradient(180deg, transparent, rgba(255,244,214,.85) 35%, rgba(200,169,110,.95) 55%, transparent 90%);
    transform: rotate(-18deg) scaleY(0); transform-origin: center;
  }
  .claws span:nth-child(1) { left: 27%; }
  .claws span:nth-child(2) { left: 47%; }
  .claws span:nth-child(3) { left: 67%; }
  .claws.slash { opacity: 1; animation: clawFade .6s ease-out forwards; }
  .claws.slash span:nth-child(1) { animation: clawSweep .6s cubic-bezier(.2,.8,.3,1) 0s forwards; }
  .claws.slash span:nth-child(2) { animation: clawSweep .6s cubic-bezier(.2,.8,.3,1) .04s forwards; }
  .claws.slash span:nth-child(3) { animation: clawSweep .6s cubic-bezier(.2,.8,.3,1) .08s forwards; }
  @keyframes clawSweep {
    0%   { transform: rotate(-18deg) scaleY(0); opacity: 1; }
    35%  { transform: rotate(-18deg) scaleY(1); opacity: 1; }
    100% { transform: rotate(-18deg) scaleY(1); opacity: 0; }
  }
  @keyframes clawFade { 0%, 55% { opacity: 1; } 100% { opacity: 0; } }

  .scenes { position: absolute; inset: 0; z-index: 10; }
  .scene {
    position: absolute; inset: 0;
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    text-align: center; padding: 0 6vw 8vh;
    opacity: 0; transform: translateY(16px) scale(.985);
    transition: opacity 900ms ease, transform 900ms ease;
    pointer-events: none;
  }
  .scene.active { opacity: 1; transform: translateY(0) scale(1); pointer-events: auto; }

  .eyebrow {
    font-family: 'Crimson Text', Georgia, serif; font-style: italic;
    font-size: .8rem; letter-spacing: .26em; text-transform: uppercase;
    color: var(--gold-bright); opacity: .9; margin: 0 0 1rem;
  }
  .eyebrow--violet { color: var(--violet-bright); }

  .headline {
    font-weight: 700; color: var(--ink-bright); text-wrap: balance;
    line-height: 1.06; letter-spacing: .015em; margin: 0 0 1.1rem;
    text-shadow: 0 0 46px rgba(200,169,110,.32);
  }
  .headline--hero { font-size: clamp(3.2rem, 11vw, 7.8rem); letter-spacing: .05em; }
  .headline--scene { font-size: clamp(1.7rem, 4.6vw, 3rem); }

  .sub {
    font-size: clamp(1rem, 2.1vw, 1.3rem); line-height: 1.65;
    color: var(--ink); max-width: 36rem; margin: 0 auto;
  }
  .sub--tagline { font-style: italic; font-size: clamp(1.1rem, 2.6vw, 1.55rem); color: var(--ink-bright); }

  /* Szene 1: Rollenkarte */
  .role-card {
    position: relative; overflow: hidden;
    width: 8.6rem; aspect-ratio: 3/4; margin: 1.8rem auto 0; border-radius: 14px;
    background: linear-gradient(160deg, #17171f, #0b0b10);
    border: 1px solid rgba(200,169,110,.35);
    display: flex; flex-direction: column; align-items: center; justify-content: center; gap: .5rem;
    box-shadow: 0 18px 40px rgba(0,0,0,.55), 0 0 30px rgba(200,169,110,.22);
    animation: cardGlow 3.6s ease-in-out infinite;
  }
  .role-card__icon { font-size: 2.1rem; filter: drop-shadow(0 0 10px rgba(200,169,110,.6)); }
  .role-card__name {
    font-family: 'Cinzel', serif; font-size: .78rem; letter-spacing: .06em; color: var(--gold-bright);
    animation: roleGlow 2.2s ease-in-out infinite alternate;
  }
  @keyframes roleGlow {
    from { text-shadow: 0 0 6px rgba(240,208,144,.35); }
    to   { text-shadow: 0 0 18px rgba(240,208,144,.9); }
  }
  @keyframes cardGlow {
    0%, 100% { box-shadow: 0 18px 40px rgba(0,0,0,.55), 0 0 26px rgba(200,169,110,.2); }
    50%      { box-shadow: 0 18px 44px rgba(0,0,0,.6), 0 0 46px rgba(200,169,110,.4); }
  }
  .role-card::after {
    content: ''; position: absolute; inset: -60%;
    background: linear-gradient(75deg, transparent 42%, rgba(255,255,255,.22) 50%, transparent 58%);
    transform: translateX(-130%);
    animation: sheen 4.4s ease-in-out infinite;
  }
  @keyframes sheen {
    0%, 68% { transform: translateX(-130%); }
    100%    { transform: translateX(130%); }
  }

  /* Szene 2: Live-Telefon */
  .phone {
    width: 7.2rem; height: 14rem; margin: 1.8rem auto 0; border-radius: 1.3rem;
    border: 2px solid rgba(139,63,214,.45); position: relative;
    background: linear-gradient(180deg, #100c1c, #06050b);
    box-shadow: 0 0 44px rgba(139,63,214,.3);
  }
  .phone__dot {
    position: absolute; top: .65rem; right: .8rem; width: .5rem; height: .5rem;
    border-radius: 50%; background: #4ade80; box-shadow: 0 0 8px #4ade80;
    animation: pulseDot 1.7s ease-in-out infinite;
  }
  .phone__bars { position: absolute; left: .8rem; right: .8rem; bottom: 1rem; display: flex; flex-direction: column; gap: .4rem; }
  .phone__bar { height: .4rem; border-radius: 3px; background: rgba(192,132,252,.18); overflow: hidden; }
  .phone__bar span { display: block; height: 100%; background: linear-gradient(90deg, var(--violet), var(--violet-bright)); animation: barFill 2.4s ease-in-out infinite; }
  .phone__bar:nth-child(2) span { animation-delay: .4s; }
  @keyframes pulseDot { 0%,100% { opacity: 1; } 50% { opacity: .35; } }
  @keyframes barFill { 0% { width: 12%; } 50% { width: 82%; } 100% { width: 12%; } }

  /* Szene 3: Theme-Chips */
  .themes { display: flex; gap: 1rem; margin-top: 1.9rem; flex-wrap: wrap; justify-content: center; }
  .theme-chip {
    width: 3.6rem; height: 3.6rem; border-radius: 50%;
    display: flex; align-items: center; justify-content: center; font-size: 1.35rem;
    box-shadow: 0 0 0 1px rgba(255,255,255,.12) inset, 0 10px 24px rgba(0,0,0,.5);
    animation: chipFloat 3.2s ease-in-out infinite;
  }
  .theme-chip:nth-child(1) { background: radial-gradient(circle at 35% 30%, #8b3fd6, #3a1160); animation-delay: 0s; }
  .theme-chip:nth-child(2) { background: radial-gradient(circle at 35% 30%, #5b8def, #17356b); animation-delay: .3s; }
  .theme-chip:nth-child(3) { background: radial-gradient(circle at 35% 30%, #b3812f, #4a2a08); animation-delay: .6s; }
  .theme-chip:nth-child(4) { background: radial-gradient(circle at 35% 30%, #4b4b55, #101014); animation-delay: .9s; }
  .theme-chip:nth-child(5) { background: radial-gradient(circle at 35% 30%, #6fe6de, #0f5f5a); animation-delay: 1.2s; }
  @keyframes chipFloat { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-8px); } }

  /* Szene 4: CTA */
  .cta {
    margin-top: 1.9rem; display: inline-flex; align-items: center; gap: .6rem;
    padding: .95rem 2rem; border-radius: 999px; text-decoration: none; cursor: pointer;
    background: linear-gradient(135deg, var(--gold-bright), var(--gold) 60%, #a9843f);
    color: #1a1206; font-family: 'Cinzel', serif; font-weight: 700; letter-spacing: .05em;
    font-size: 1rem; box-shadow: 0 12px 32px rgba(200,169,110,.4);
    animation: ctaPulse 2.6s ease-in-out infinite;
  }
  @keyframes ctaPulse {
    0%, 100% { box-shadow: 0 12px 32px rgba(200,169,110,.4); }
    50%      { box-shadow: 0 14px 44px rgba(200,169,110,.65); }
  }
  .cta:focus-visible { outline: 2px solid var(--ink-bright); outline-offset: 3px; }

  /* HUD / Steuerung */
  .hud {
    position: absolute; left: 0; right: 0; bottom: 0; z-index: 20;
    display: flex; flex-direction: column; gap: .65rem;
    padding: 1rem 1.3rem calc(1.2rem + env(safe-area-inset-bottom));
    background: linear-gradient(0deg, rgba(3,3,6,.8), transparent);
  }
  .progress-track { width: 100%; height: 3px; border-radius: 2px; background: rgba(255,255,255,.14); overflow: hidden; }
  .progress-bar { height: 100%; width: 0%; border-radius: 2px; background: linear-gradient(90deg, var(--gold), var(--violet-bright)); }
  .hud-row { display: flex; align-items: center; justify-content: center; gap: 1.1rem; }
  .hud-btn {
    background: transparent; border: none; color: var(--ink-dim); cursor: pointer;
    font-size: 1.15rem; line-height: 1; padding: .4rem; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    transition: color .25s, background .25s;
  }
  .hud-btn:hover { color: var(--gold-bright); background: rgba(200,169,110,.1); }
  .hud-btn:focus-visible { outline: 2px solid var(--gold-bright); outline-offset: 2px; color: var(--gold-bright); }
  .dots { display: flex; gap: .55rem; }
  .dot {
    width: .55rem; height: .55rem; border-radius: 50%; padding: 0; border: none; cursor: pointer;
    background: rgba(255,255,255,.25); transition: background .3s, transform .3s, box-shadow .3s;
  }
  .dot.active { background: var(--gold-bright); transform: scale(1.35); box-shadow: 0 0 8px var(--gold-bright); }
  .dot:focus-visible { outline: 2px solid var(--gold-bright); outline-offset: 3px; }

  /* Echter In-App-Effekt: Button-Ripple (assets/js/effects.js) */
  .hud-btn, .dot { position: relative; overflow: hidden; }
  .fx-ripple {
    position: absolute; border-radius: 50%; pointer-events: none;
    width: 8px; height: 8px; margin: -4px;
    background: rgba(240,208,144,.55); transform: scale(0);
    animation: fxRipple .55s ease-out forwards;
  }
  @keyframes fxRipple { to { transform: scale(45); opacity: 0; } }

  /* Echter In-App-Effekt: Phasen-Ueberblendung (Tag/Nacht-Wechsel) */
  .fx-phase {
    position: absolute; inset: 0; z-index: 50; pointer-events: none;
    display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 1rem;
    opacity: 0; animation: fxPhaseIn .5s ease forwards, fxPhaseOut .9s ease 1.9s forwards;
  }
  .fx-phase--night { background: radial-gradient(ellipse at 50% 38%, rgba(12,4,45,.88), rgba(0,0,8,.94)); }
  .fx-phase--day   { background: radial-gradient(ellipse at 50% 30%, rgba(255,200,45,.55), rgba(255,115,0,.38)); }
  .fx-phase__icon  { font-size: 3.6rem; animation: fxPhasePulse 1.1s ease-in-out infinite alternate; }
  .fx-phase__label {
    font-family: 'Cinzel', serif; font-size: .95rem; letter-spacing: .13em; text-transform: uppercase;
    color: #fff; text-shadow: 0 2px 20px rgba(0,0,0,.9);
  }
  @keyframes fxPhaseIn    { from { opacity: 0; } to { opacity: 1; } }
  @keyframes fxPhaseOut   { from { opacity: 1; } to { opacity: 0; } }
  @keyframes fxPhasePulse { from { transform: scale(1); } to { transform: scale(1.08); } }

  @media (prefers-reduced-motion: reduce) {
    .scene, .moon, .hill { transition: none; }
    .role-card, .phone__dot, .phone__bar span, .theme-chip, .cta, .role-card::after, .role-card__name { animation: none; }
    .stage.intro .headline--hero, .stage.intro .howl-ring { animation: none; }
    canvas#fx { display: none; }
  }
</style>
</head>
<body>
<div class="stage" data-scene="0">
  <canvas id="fx" aria-hidden="true"></canvas>
  <div class="moon" aria-hidden="true"></div>
  <div class="hill" aria-hidden="true"><span class="hill__wolf">🐺</span></div>
  <div class="howl-ring" aria-hidden="true"></div>
  <div class="vignette" aria-hidden="true"></div>
  <div class="claws" id="claws" aria-hidden="true"><span></span><span></span><span></span></div>

  <p class="visually-hidden">Automatische Diashow. Pfeiltasten wechseln die Szene, Leertaste pausiert.</p>

  <div class="scenes" role="group" aria-roledescription="Diashow" aria-label="<?= e(APP_NAME) ?> — Vorschau">

    <section class="scene active" data-idx="0">
      <p class="eyebrow">Ein Dorf. Viele Geheimnisse.</p>
      <h1 class="headline headline--hero"><?= e(mb_strtoupper(APP_NAME)) ?></h1>
      <p class="sub sub--tagline">Das Dorf schläft … doch die Wölfe nicht.</p>
    </section>

    <section class="scene" data-idx="1">
      <p class="eyebrow">Jede Nacht eine neue Rolle</p>
      <h2 class="headline headline--scene">Wer bist du heute?</h2>
      <p class="sub">Über ein Dutzend Rollen mit eigenem Charakter, eigenem Icon, eigener Macht —
        Hellseherin, Detektiv, Mörder, Dodo und mehr. Der Spielleiter mischt, du entdeckst.</p>
      <div class="role-card" aria-hidden="true">
        <span class="role-card__icon">🔮</span>
        <span class="role-card__name">Hellseherin</span>
      </div>
    </section>

    <section class="scene" data-idx="2">
      <p class="eyebrow eyebrow--violet">Kein Reload. Nur das Dorf.</p>
      <h2 class="headline headline--scene">Alles live, direkt im Browser</h2>
      <p class="sub">Abstimmungen, Todesfälle, Bürgerversammlungen — alles aktualisiert sich von
        selbst. Kein Download, keine Installation, läuft auf jedem Handy.</p>
      <div class="phone" aria-hidden="true">
        <div class="phone__dot"></div>
        <div class="phone__bars">
          <div class="phone__bar"><span></span></div>
          <div class="phone__bar"><span></span></div>
        </div>
      </div>
    </section>

    <section class="scene" data-idx="3">
      <p class="eyebrow eyebrow--violet">Fünf Welten, ein Dorf</p>
      <h2 class="headline headline--scene">Spiel im Look, der zu euch passt</h2>
      <p class="sub">Gothic, Vista, Mittelalter, Minimal, Crystal — jede Gruppe wählt ihre eigene Atmosphäre.</p>
      <div class="themes" aria-hidden="true">
        <span class="theme-chip">🌑</span>
        <span class="theme-chip">💠</span>
        <span class="theme-chip">🏰</span>
        <span class="theme-chip">◻</span>
        <span class="theme-chip">💎</span>
      </div>
    </section>

    <section class="scene" data-idx="4">
      <p class="eyebrow">Bereit fürs Dorf?</p>
      <h2 class="headline headline--scene">Tritt dem Dorf bei</h2>
      <p class="sub">Frag deinen Spielleiter nach dem Zugang — und hoffe, dass niemand
        neben dir ein Wolf ist.</p>
      <a class="cta" href="<?= e(APP_URL) ?>/">🐺 Los geht's</a>
    </section>

  </div>

  <div class="hud">
    <div class="progress-track"><div class="progress-bar" id="bar"></div></div>
    <div class="hud-row">
      <button class="hud-btn" id="prevBtn" aria-label="Vorherige Szene">‹</button>
      <button class="hud-btn" id="playBtn" aria-label="Pause">⏸</button>
      <div class="dots" id="dots"></div>
      <button class="hud-btn" id="nextBtn" aria-label="Nächste Szene">›</button>
    </div>
  </div>
</div>

<script>
(function () {
  var reduced = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
  var stage   = document.querySelector('.stage');
  var scenes  = Array.prototype.slice.call(document.querySelectorAll('.scene'));
  var dotsBox = document.getElementById('dots');
  var bar     = document.getElementById('bar');
  var playBtn = document.getElementById('playBtn');
  var DUR     = 5200;
  var idx = 0, playing = !reduced, timer = null;
  var sceneLabels = ['Willkommen', 'Rollen', 'Live', 'Themes', 'Mitmachen'];

  scenes.forEach(function (s, n) {
    var d = document.createElement('button');
    d.className = 'dot' + (n === 0 ? ' active' : '');
    d.setAttribute('aria-label', 'Szene ' + (n + 1) + ': ' + sceneLabels[n]);
    d.addEventListener('click', function () { goTo(n, true); });
    dotsBox.appendChild(d);
  });
  var dots = Array.prototype.slice.call(dotsBox.children);

  var firstRender = true;
  function render() {
    scenes.forEach(function (s, n) {
      var active = n === idx;
      s.classList.toggle('active', active);
      s.setAttribute('aria-hidden', active ? 'false' : 'true');
    });
    dots.forEach(function (d, n) { d.classList.toggle('active', n === idx); });
    stage.setAttribute('data-scene', String(idx));
    if (!firstRender) slashCut();
    firstRender = false;
    if (idx === 2) phaseFlash('night');
    else if (idx === 4) phaseFlash('day');
    resetBar();
    if (playing) growBar();
  }
  function slashCut() {
    if (reduced) return;
    var el = document.getElementById('claws');
    el.classList.remove('slash');
    void el.offsetWidth;
    el.classList.add('slash');
  }

  // ── Echter In-App-Effekt: Phasen-Ueberblendung bei Tag-/Nachtwechsel ──
  function phaseFlash(kind) {
    if (reduced) return;
    var old = document.getElementById('fx-phase');
    if (old) old.remove();
    var el = document.createElement('div');
    el.id = 'fx-phase';
    el.className = 'fx-phase fx-phase--' + kind;
    el.innerHTML = kind === 'night'
      ? '<div class="fx-phase__icon">🌕</div><div class="fx-phase__label">Die Nacht bricht herein …</div>'
      : '<div class="fx-phase__icon">☀️</div><div class="fx-phase__label">Der Morgen graut …</div>';
    stage.appendChild(el);
    setTimeout(function () { el.remove(); }, 2900);
  }

  // ── Echter In-App-Effekt: Button-Ripple beim Klick auf die Steuerung ──
  document.addEventListener('click', function (e) {
    if (reduced) return;
    var btn = e.target.closest('.hud-btn, .dot');
    if (!btn) return;
    var rc = btn.getBoundingClientRect();
    var rip = document.createElement('span');
    rip.className = 'fx-ripple';
    rip.style.left = (e.clientX - rc.left) + 'px';
    rip.style.top = (e.clientY - rc.top) + 'px';
    btn.appendChild(rip);
    rip.addEventListener('animationend', function () { rip.remove(); }, { once: true });
  });

  function resetBar() {
    bar.style.transition = 'none';
    bar.style.width = '0%';
    void bar.offsetWidth;
  }
  function growBar() {
    bar.style.transition = 'width ' + DUR + 'ms linear';
    bar.style.width = '100%';
  }
  function freezeBar() {
    var w = getComputedStyle(bar).width;
    bar.style.transition = 'none';
    bar.style.width = w;
  }

  function goTo(n, userInitiated) {
    idx = (n + scenes.length) % scenes.length;
    render();
    if (userInitiated) { pause(); } else if (playing) { armTimer(); }
  }
  function armTimer() {
    clearTimeout(timer);
    timer = setTimeout(function () { goTo(idx + 1); }, DUR);
  }
  function play() {
    if (reduced) return;
    playing = true;
    playBtn.textContent = '⏸';
    playBtn.setAttribute('aria-label', 'Pause');
    growBar();
    armTimer();
  }
  function pause() {
    playing = false;
    playBtn.textContent = '▶';
    playBtn.setAttribute('aria-label', 'Abspielen');
    clearTimeout(timer);
    freezeBar();
  }

  document.getElementById('prevBtn').addEventListener('click', function () { goTo(idx - 1, true); });
  document.getElementById('nextBtn').addEventListener('click', function () { goTo(idx + 1, true); });
  playBtn.addEventListener('click', function () { playing ? pause() : play(); });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'ArrowRight') { goTo(idx + 1, true); }
    else if (e.key === 'ArrowLeft') { goTo(idx - 1, true); }
    else if (e.code === 'Space' && e.target === document.body) { e.preventDefault(); playing ? pause() : play(); }
  });

  render();
  if (playing) { growBar(); armTimer(); } else { playBtn.textContent = '▶'; playBtn.setAttribute('aria-label', 'Abspielen'); }

  if (!reduced) {
    requestAnimationFrame(function () { stage.classList.add('intro'); });
  }

  // ── Dezente Mond-Parallaxe fuer Zeigegeraete mit Maus ────────────
  if (!reduced && window.matchMedia && matchMedia('(pointer: fine)').matches) {
    document.addEventListener('mousemove', function (e) {
      var px = (e.clientX / innerWidth) * 2 - 1;
      var py = (e.clientY / innerHeight) * 2 - 1;
      stage.style.setProperty('--px', px.toFixed(3));
      stage.style.setProperty('--py', py.toFixed(3));
    });
  }

  // ── Ambient-Ebene: Sterne, Nebel, Glühwürmchen (Canvas) ──────────
  var canvas = document.getElementById('fx');
  var ctx = canvas.getContext('2d');
  var dpr = Math.min(window.devicePixelRatio || 1, 2);

  function resize() {
    canvas.width = innerWidth * dpr;
    canvas.height = innerHeight * dpr;
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
  }
  resize();
  window.addEventListener('resize', resize);

  function rnd(a, b) { return a + Math.random() * (b - a); }
  var stars = Array.from({ length: 90 }, function () {
    return { x: Math.random(), y: Math.random() * 0.62, r: rnd(.3, 1.5), ph: rnd(0, 7), sp: rnd(.4, 1.1) };
  });
  var flies = Array.from({ length: 11 }, function () {
    return { x: Math.random(), y: rnd(.42, .92), r: rnd(1.4, 2.8), ph: rnd(0, 7), spx: rnd(.12, .3), spy: rnd(.1, .22) };
  });
  var fog = [
    { x: .2, y: .78, r: .5, sp: .015 },
    { x: .72, y: .86, r: .58, sp: -.012 },
    { x: .46, y: .7, r: .4, sp: .02 }
  ];

  var t0 = performance.now();
  function frame(now) {
    var t = (now - t0) / 1000;
    var w = innerWidth, h = innerHeight;
    ctx.clearRect(0, 0, w, h);

    fog.forEach(function (b) {
      var x = (b.x + Math.sin(t * b.sp) * .05) * w;
      var y = b.y * h;
      var r = b.r * Math.max(w, h) * .55;
      var g = ctx.createRadialGradient(x, y, 0, x, y, r);
      g.addColorStop(0, 'rgba(22,15,36,.35)');
      g.addColorStop(1, 'rgba(22,15,36,0)');
      ctx.fillStyle = g;
      ctx.beginPath(); ctx.arc(x, y, r, 0, Math.PI * 2); ctx.fill();
    });

    stars.forEach(function (s) {
      var a = .3 + .55 * Math.abs(Math.sin(t * s.sp + s.ph));
      ctx.fillStyle = 'rgba(230,225,255,' + a.toFixed(3) + ')';
      ctx.beginPath(); ctx.arc(s.x * w, s.y * h, s.r, 0, Math.PI * 2); ctx.fill();
    });

    flies.forEach(function (f) {
      var x = f.x * w + Math.sin(t * f.spx + f.ph) * 42;
      var y = f.y * h + Math.cos(t * f.spy + f.ph) * 32;
      var glow = .5 + .45 * Math.sin(t * 1.3 + f.ph);
      var grad = ctx.createRadialGradient(x, y, 0, x, y, f.r * 6);
      grad.addColorStop(0, 'rgba(240,208,144,' + (.85 * glow).toFixed(3) + ')');
      grad.addColorStop(1, 'rgba(240,208,144,0)');
      ctx.fillStyle = grad;
      ctx.beginPath(); ctx.arc(x, y, f.r * 6, 0, Math.PI * 2); ctx.fill();
      ctx.fillStyle = 'rgba(255,240,210,' + glow.toFixed(3) + ')';
      ctx.beginPath(); ctx.arc(x, y, f.r, 0, Math.PI * 2); ctx.fill();
    });

    // Filmkorn — dezentes Flackern fuer den Kino-Look
    ctx.fillStyle = 'rgba(255,255,255,.05)';
    for (var i = 0; i < 60; i++) {
      ctx.fillRect(Math.random() * w, Math.random() * h, 1, 1);
    }

    if (!reduced) requestAnimationFrame(frame);
  }
  if (reduced) { frame(performance.now()); } else { requestAnimationFrame(frame); }
})();
</script>

</body>
</html>
