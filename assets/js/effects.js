// Copyright (c) 2026 Andreas Vetter
(function () {
  'use strict';

  // ── Einstellungen aus localStorage ──────────────────────────
  function fxGet(key, def) {
    const v = localStorage.getItem('ww_fx_' + key);
    if (v === null)   return def;
    if (v === 'true') return true;
    if (v === 'false')return false;
    const n = parseFloat(v);
    return isNaN(n) ? def : n;
  }

  // ── Aktuelle Phase ────────────────────────────────────────────
  // Wird von game.php via FX.updateForPhase() gesetzt.
  // Für alle anderen Seiten: letzte bekannte Phase aus localStorage.
  let currentPhase = localStorage.getItem('ww_last_phase') || 'day';

  // ── CSS ───────────────────────────────────────────────────────
  const style = document.createElement('style');
  style.textContent = `
    /* Button-Ripple */
    .btn { overflow: hidden; }
    .fx-ripple {
      position: absolute; border-radius: 50%; pointer-events: none;
      width: 8px; height: 8px; margin: -4px;
      background: rgba(255,255,255,.3); transform: scale(0);
      animation: fx-ripple .55s ease-out forwards;
    }
    @keyframes fx-ripple { to { transform: scale(45); opacity: 0; } }

    /* Phasen-Überblendung */
    #fx-phase {
      position: fixed; inset: 0; z-index: 9999; pointer-events: none;
      display: flex; flex-direction: column;
      align-items: center; justify-content: center; gap: 1.2rem;
      animation: fx-phase-in .55s ease;
    }
    #fx-phase.fx-out { animation: fx-phase-out .9s ease forwards; }
    .fx-phase-night { background: radial-gradient(ellipse at 50% 38%, rgba(12,4,45,.9), rgba(0,0,8,.96)); }
    .fx-phase-day   { background: radial-gradient(ellipse at 50% 30%, rgba(255,200,45,.62), rgba(255,115,0,.42)); -webkit-backdrop-filter: blur(2px); backdrop-filter: blur(2px); }
    .fx-phase-icon  { font-size: 5.5rem; animation: fx-phase-pulse 1.1s ease-in-out infinite alternate; }
    .fx-phase-label { font-size: 1.25rem; letter-spacing: .13em; color: #fff;
                      text-shadow: 0 2px 24px rgba(0,0,0,.9); font-family: var(--font-display, serif); }
    @keyframes fx-phase-in    { from { opacity:0 } to { opacity:1 } }
    @keyframes fx-phase-out   { from { opacity:1 } to { opacity:0 } }
    @keyframes fx-phase-pulse { from { transform:scale(1) } to { transform:scale(1.1) } }

    /* Schädelregen */
    @keyframes fx-skull-fall {
      0%   { transform: translateY(-60px) rotate(0deg); opacity: 0; }
      8%   { opacity: .6; }
      90%  { opacity: .4; }
      100% { transform: translateY(108vh) rotate(540deg); opacity: 0; }
    }
    .fx-skull {
      position: fixed; top: 0; pointer-events: none; z-index: 2;
      font-size: 1.4rem; filter: opacity(.5); animation: fx-skull-fall linear forwards;
    }

    /* Zeilen-Einblendung */
    @keyframes fx-row-in {
      from { opacity: 0; transform: translateX(-16px); }
      to   { opacity: 1; transform: none; }
    }
    tbody tr { animation: fx-row-in .42s ease both; }

    /* Animations-Kill */
    body.no-fx-anims .animate-in { animation: none !important; }

    /* ── Nebel ─────────────────────────────────────────────────── */
    #fx-fog {
      position: fixed; inset: 0; pointer-events: none; z-index: 1;
      opacity: 0; transition: opacity 6s ease;
      overflow: hidden;
    }
    #fx-fog.active { opacity: 1; }

    .fx-fog-blob {
      position: absolute; border-radius: 50%;
      will-change: transform;
    }

    @keyframes fog-a { 0%,100%{transform:translate(0,0) scale(1.00)} 30%{transform:translate(4vw,3vh) scale(1.06)} 65%{transform:translate(-3vw,5vh) scale(.95)} }
    @keyframes fog-b { 0%,100%{transform:translate(0,0) scale(1.00)} 40%{transform:translate(-5vw,-2vh) scale(1.08)} 70%{transform:translate(3vw,-4vh) scale(.93)} }
    @keyframes fog-c { 0%,100%{transform:translate(0,0) scale(1.00)} 50%{transform:translate(3vw,4vh) scale(1.05)} }
    @keyframes fog-d { 0%,100%{transform:translate(0,0) scale(1.00)} 35%{transform:translate(-2vw,2vh) scale(.96)} 70%{transform:translate(4vw,-3vh) scale(1.04)} }
  `;
  document.head.appendChild(style);

  // ── Nebel-Overlay ─────────────────────────────────────────────
  let fogOn = fxGet('fog', true);

  const fogEl = document.createElement('div');
  fogEl.id = 'fx-fog';

  [
    { l:'-15vw', t:'5vh',   w:'85vw', h:'60vh', a:'fog-a', d:'34s', o:.055 },
    { l:'38vw',  t:'38vh',  w:'72vw', h:'58vh', a:'fog-b', d:'43s', o:.045 },
    { l:'12vw',  t:'-18vh', w:'64vw', h:'72vh', a:'fog-c', d:'27s', o:.050 },
    { l:'-8vw',  t:'58vh',  w:'90vw', h:'48vh', a:'fog-d', d:'38s', o:.040 },
  ].forEach(b => {
    const blob = document.createElement('div');
    blob.className = 'fx-fog-blob';
    Object.assign(blob.style, {
      left: b.l, top: b.t, width: b.w, height: b.h,
      background: `radial-gradient(ellipse at center, rgba(165,195,245,${b.o}) 0%, rgba(145,175,230,${(b.o*.38).toFixed(3)}) 45%, transparent 70%)`,
      animation:  `${b.a} ${b.d} ease-in-out infinite`,
    });
    fogEl.appendChild(blob);
  });

  document.body.appendChild(fogEl);

  function applyFog() {
    if (fogOn && currentPhase === 'night') {
      fogEl.classList.add('active');
    } else {
      fogEl.classList.remove('active');
    }
  }
  applyFog();

  // ── Partikel-Canvas (Glühwürmchen / Mondlicht-Funken) ─────────
  let particlesOn = fxGet('particles', true);

  const canvas = document.createElement('canvas');
  canvas.id = 'fx-canvas';
  Object.assign(canvas.style, {
    position: 'fixed', inset: '0',
    pointerEvents: 'none', zIndex: '9998',
    mixBlendMode: 'screen',
  });
  document.body.appendChild(canvas);

  const ctx = canvas.getContext('2d');
  let W, H;

  function resize() {
    W = canvas.width  = window.innerWidth;
    H = canvas.height = window.innerHeight;
  }
  resize();
  window.addEventListener('resize', resize);

  function mkP(randomY) {
    return {
      x:  Math.random() * W,
      y:  randomY ? Math.random() * H : H + 6,
      r:  Math.random() * 1.4 + 0.45,
      vx: (Math.random() - .5) * .38,
      vy: -(Math.random() * .38 + .12),
      a:  Math.random() * .45 + .15,
      da: (Math.random() * .007 + .002) * (Math.random() > .5 ? 1 : -1),
      // Farbvariation 0..1 (für Nacht-Blauton-Mix)
      hue: Math.random(),
    };
  }

  const pts = Array.from({ length: 28 }, () => mkP(true));

  function drawFrame() {
    ctx.clearRect(0, 0, W, H);

    if (particlesOn) {
      const night = currentPhase === 'night';
      // Nachts langsamer treiben
      const spd = night ? 0.6 : 1;

      for (const p of pts) {
        p.x  += p.vx * spd;
        p.y  += p.vy * spd;
        p.a  += p.da;
        if (p.a > .82) p.da = -Math.abs(p.da);
        if (p.a < .08) p.da =  Math.abs(p.da);
        if (p.y < -10) Object.assign(p, mkP(false));

        if (night) {
          // Blaue/violette Mondlicht-Funken
          const rb = Math.round(115 + p.hue * 65); // 115–180
          const gb = Math.round(148 + p.hue * 12); // 148–160
          const bb = Math.round(238 + p.hue * 17); // 238–255
          const col = `${rb},${gb},${bb}`;

          const grd = ctx.createRadialGradient(p.x, p.y, 0, p.x, p.y, p.r * 8);
          grd.addColorStop(0, `rgba(${col},${(p.a * .28).toFixed(2)})`);
          grd.addColorStop(1, `rgba(${col},0)`);
          ctx.beginPath();
          ctx.arc(p.x, p.y, p.r * 8, 0, Math.PI * 2);
          ctx.fillStyle = grd;
          ctx.fill();

          ctx.beginPath();
          ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
          ctx.fillStyle = `rgba(${col},${(p.a * .8).toFixed(2)})`;
          ctx.fill();

        } else {
          // Goldene Glühwürmchen (Tag / Default)
          const g = ctx.createRadialGradient(p.x, p.y, 0, p.x, p.y, p.r * 7);
          g.addColorStop(0, `rgba(255,215,80,${(p.a * .44).toFixed(2)})`);
          g.addColorStop(1, 'rgba(255,140,0,0)');
          ctx.beginPath();
          ctx.arc(p.x, p.y, p.r * 7, 0, Math.PI * 2);
          ctx.fillStyle = g;
          ctx.fill();

          ctx.beginPath();
          ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
          ctx.fillStyle = `rgba(255,245,160,${p.a.toFixed(2)})`;
          ctx.fill();
        }
      }
    }

    requestAnimationFrame(drawFrame);
  }
  drawFrame();

  // ── Button-Ripple ─────────────────────────────────────────────
  let rippleOn = fxGet('ripple', true);

  document.addEventListener('click', e => {
    if (!rippleOn) return;
    const btn = e.target.closest('.btn');
    if (!btn) return;
    const rc = btn.getBoundingClientRect();
    const el = document.createElement('span');
    el.className = 'fx-ripple';
    el.style.left = (e.clientX - rc.left) + 'px';
    el.style.top  = (e.clientY - rc.top)  + 'px';
    btn.appendChild(el);
    el.addEventListener('animationend', () => el.remove(), { once: true });
  });

  // ── Phasen-Überblendung ───────────────────────────────────────
  let phaseOn = fxGet('phase', true);

  window.triggerPhaseTransition = function (phase) {
    // Phase intern merken + Nebel/Partikel sofort anpassen
    currentPhase = phase;
    applyFog();

    if (!phaseOn) return;
    const old = document.getElementById('fx-phase');
    if (old) old.remove();
    const el = document.createElement('div');
    el.id        = 'fx-phase';
    el.className = phase === 'night' ? 'fx-phase-night' : 'fx-phase-day';
    el.innerHTML =
      `<div class="fx-phase-icon">${phase === 'night' ? '🌕' : '☀️'}</div>` +
      `<div class="fx-phase-label">${phase === 'night' ? 'Die Nacht bricht herein …' : 'Der Morgen graut …'}</div>`;
    document.body.appendChild(el);
    setTimeout(() => {
      el.classList.add('fx-out');
      el.addEventListener('animationend', () => el.remove(), { once: true });
    }, 2000);
  };

  // ── Schädelregen (deaths.php) ─────────────────────────────────
  let skullsOn = fxGet('skulls', true);
  let _skullTimer = null;
  const _skullIcons = ['💀', '🦇', '🕯️', '🪦', '🌑'];

  function spawnSkull() {
    const el = document.createElement('span');
    el.className  = 'fx-skull';
    el.textContent = _skullIcons[Math.floor(Math.random() * _skullIcons.length)];
    el.style.left  = (Math.random() * 96) + 'vw';
    const dur      = 5500 + Math.random() * 5500;
    el.style.animationDuration = dur + 'ms';
    document.body.appendChild(el);
    setTimeout(() => el.remove(), dur + 300);
  }

  function startSkulls() {
    if (_skullTimer) return;
    for (let i = 0; i < 5; i++) setTimeout(spawnSkull, i * 650);
    _skullTimer = setInterval(spawnSkull, 2800);
  }
  function stopSkulls() {
    if (_skullTimer) { clearInterval(_skullTimer); _skullTimer = null; }
    document.querySelectorAll('.fx-skull').forEach(el => el.remove());
  }

  if (location.pathname.endsWith('deaths.php')) {
    if (skullsOn) startSkulls();
    document.querySelectorAll('tbody tr').forEach((tr, i) => {
      tr.style.animationDelay = (i * 0.07) + 's';
    });
  }

  // ── Öffentliche API ───────────────────────────────────────────
  window.FX = {
    setParticles(on) { particlesOn = on; if (!on) ctx.clearRect(0, 0, W, H); },
    setRipple(on)    { rippleOn = on; },
    setPhase(on)     { phaseOn  = on; },
    setFog(on)       { fogOn    = on; applyFog(); },
    setSkulls(on) {
      skullsOn = on;
      if (location.pathname.endsWith('deaths.php')) {
        on ? startSkulls() : stopSkulls();
      }
    },
    // Wird von game.php aufgerufen, um Phase sofort zu setzen
    updateForPhase(phase) {
      currentPhase = phase;
      applyFog();
    },
  };

})();
