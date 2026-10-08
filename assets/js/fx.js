/* ==========================================================================
   Slugshop – látványelemek (3D)
   - SlugFX.rifles(el):    a két egymásnak támasztott FX fegyver 3D-ben forog (rétegekből
                           adott vastagsággal), egérrel/ujjal megforgatható
   - SlugFX.panthera(el):  görgetésre szétszedi az FX Pantherát: az összerakott oldalnézetből
                           az alkatrészek 3D-ben a gyári robbantott ábra helyére repülnek
   - SlugFX.coverflow(el): 3D videókarusszel
   Mindegyik csak akkor számol, ha látszik (IntersectionObserver), csak transformot
   animál (GPU), és „csökkentett mozgás” beállításnál áll.
   ========================================================================== */
(function () {
  'use strict';

  const reduce = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const clamp = (v, a = 0, b = 1) => Math.min(b, Math.max(a, v));
  const lerp = (a, b, t) => a + (b - a) * t;
  const ease = (t) => (t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2);
  const hash = (i, k) => { const x = Math.sin(i * 127.1 + k * 311.7) * 43758.5453; return x - Math.floor(x); };
  let cleanups = [];
  const on = (t, ev, fn, o) => { t.addEventListener(ev, fn, o); cleanups.push(() => t.removeEventListener(ev, fn, o)); };
  function visible(el, cb) {
    const io = new IntersectionObserver((es) => es.forEach((e) => cb(e.isIntersecting)), { rootMargin: '120px' });
    io.observe(el); cleanups.push(() => io.disconnect());
  }

  /* ------------------------------------------------------------- forgó fegyverek */
  function rifles(root) {
    const spin = root.querySelector('.rifles-spin');
    const shadow = root.querySelector('.rifles-shadow');
    if (!spin) return;
    let angle = -18, vel = 22, last = 0, raf = 0, shown = false, drag = null;
    const BASE = 22; // fok / mp
    const frame = (now) => {
      const dt = last ? Math.min(0.05, (now - last) / 1000) : 0; last = now;
      if (!drag) { vel += (BASE - vel) * Math.min(1, dt * 1.5); angle += vel * dt; }
      spin.style.transform = `rotateX(-4deg) rotateY(${angle}deg)`;
      const c = Math.abs(Math.cos(angle * Math.PI / 180));
      if (shadow) shadow.style.transform = `translateX(-50%) scaleX(${0.35 + 0.65 * c})`;
      raf = shown ? requestAnimationFrame(frame) : 0;
    };
    const start = () => { if (!raf && shown) { last = 0; raf = requestAnimationFrame(frame); } };
    if (reduce()) { spin.style.transform = 'rotateX(-4deg) rotateY(-24deg)'; return; }
    visible(root, (v) => { shown = v; start(); });
    const grab = root.querySelector('.rifles-stage');
    on(grab, 'pointerdown', (e) => { drag = { x: e.clientX, a: angle, t: performance.now() }; grab.setPointerCapture(e.pointerId); grab.classList.add('is-drag'); });
    on(grab, 'pointermove', (e) => {
      if (!drag) return;
      const now = performance.now(); const na = drag.a + (e.clientX - drag.x) * 0.45;
      vel = (na - angle) / Math.max(0.016, (now - drag.t) / 1000); drag.t = now; angle = na;
    });
    const end = () => { if (drag) { drag = null; grab.classList.remove('is-drag'); vel = clamp(vel, -720, 720); } };
    on(grab, 'pointerup', end); on(grab, 'pointercancel', end);
  }

  /* ------------------------------------------------------------- Panthera robbantva */
  function panthera(root) {
    const D = window.PANTHERA; if (!D) return;
    const stage = root.querySelector('.panthera-stage');
    const scene = root.querySelector('.panthera-scene');
    const render = root.querySelector('.panthera-render');
    const counter = root.querySelector('[data-pcount]');
    const bar = root.querySelector('.panthera-progress i');
    const parts = D.parts.map((p, i) => {
      const el = document.createElement('div');
      el.className = 'pp';
      scene.appendChild(el);
      return { p, el, i, h1: hash(i, 1), h2: hash(i, 2), h3: hash(i, 3) };
    });
    let s = 1, W = 0, H = 0, raf = 0, shown = false, mx = 0, my = 0, tmx = 0, tmy = 0;

    function layout() {
      const r = stage.getBoundingClientRect();
      s = Math.min(r.width * 1.0 / D.w, r.height * 1.0 / D.h);
      W = D.w * s; H = D.h * s;
      scene.style.width = W + 'px'; scene.style.height = H + 'px';
      const rw = W * 0.96; const rh = rw * D.renderH / D.renderW;
      Object.assign(render.style, { width: rw + 'px', height: rh + 'px', left: (W - rw) / 2 + 'px', top: (H - rh) / 2 + 'px' });
      parts.forEach((o) => {
        const { p } = o;
        Object.assign(o.el.style, {
          width: p.w * s + 'px', height: p.h * s + 'px',
          backgroundSize: `${D.atlasW * s}px ${D.atlasH * s}px`, backgroundPosition: `${-p.ax * s}px ${-p.ay * s}px`
        });
        o.ex = (p.x + p.w / 2) * s; o.ey = (p.y + p.h / 2) * s;      // végső (robbantott) középpont
        o.sx = (W - rw) / 2 + rw * (0.015 + 0.97 * p.a);             // kiinduló: a fegyver tengelyén
        o.sy = H / 2 + p.b * s * 0.1;
      });
      draw();
    }
    function progress() {
      const r = root.getBoundingClientRect();
      const total = r.height - window.innerHeight;
      return total > 0 ? clamp(-r.top / total) : 1;
    }
    function draw() {
      const p = reduce() ? 1 : progress();
      mx += (tmx - mx) * 0.08; my += (tmy - my) * 0.08;
      scene.style.transform = `translate(-50%, -50%) rotateX(${lerp(16, 3, ease(p)) + my * 6}deg) rotateY(${lerp(-16, 5, p) + mx * 10}deg)`;
      // az összerakott fegyver: forog, majd átadja a helyét az alkatrészeknek
      const ro = 1 - clamp((p - 0.16) / 0.14);
      render.style.opacity = ro;
      render.style.transform = `translateZ(${lerp(0, 120, clamp(p / 0.3))}px) rotateY(${lerp(0, -14, clamp(p / 0.3))}deg)`;
      let landed = 0;
      for (const o of parts) {
        const t = ease(clamp((p - 0.2 - o.p.a * 0.24) / 0.42));
        const arc = Math.sin(Math.PI * t);
        const x = lerp(o.sx, o.ex, t), y = lerp(o.sy, o.ey, t) - arc * 30 * o.h3;
        const z = arc * (140 + 260 * o.h1);
        const rz = lerp(-D.angle, 0, t);
        const ry = arc * (o.h2 * 80 - 40), rx = arc * (o.h3 * 60 - 30);
        const sc = lerp(0.6, 1, t);
        o.el.style.opacity = clamp((p - 0.18) / 0.1);
        o.el.style.transform = `translate3d(${x - o.p.w * s / 2}px, ${y - o.p.h * s / 2}px, ${z}px) rotateZ(${rz}deg) rotateY(${ry}deg) rotateX(${rx}deg) scale(${sc})`;
        if (t > 0.985) landed++;
      }
      if (counter) counter.textContent = landed;
      if (bar) bar.style.transform = `scaleX(${p})`;
      root.classList.toggle('is-done', p > 0.96);
    }
    const loop = () => { draw(); raf = shown ? requestAnimationFrame(loop) : 0; };
    visible(root, (v) => { shown = v; if (v && !raf) raf = requestAnimationFrame(loop); });
    on(window, 'resize', layout);
    on(stage, 'pointermove', (e) => { const r = stage.getBoundingClientRect(); tmx = (e.clientX - r.left) / r.width - 0.5; tmy = (e.clientY - r.top) / r.height - 0.5; });
    on(stage, 'pointerleave', () => { tmx = 0; tmy = 0; });
    if (render.complete) layout(); else render.addEventListener('load', layout, { once: true });
    layout();
  }

  /* ------------------------------------------------------------- 3D videókarusszel */
  function coverflow(root) {
    const items = Array.from(root.querySelectorAll('.cf-item'));
    const title = root.querySelector('[data-cf-title]');
    const label = root.querySelector('[data-cf-label]');
    if (!items.length) return;
    let cur = 0;
    const set = (n) => {
      const prev = items[cur];
      cur = (n + items.length) % items.length;
      if (prev !== items[cur]) { const f = prev.querySelector('iframe'); if (f) prev.querySelector('.cf-media').innerHTML = prev.dataset.thumbHtml; }
      items.forEach((el, i) => {
        let d = i - cur;
        if (d > items.length / 2) d -= items.length;
        if (d < -items.length / 2) d += items.length;
        const ad = Math.abs(d);
        el.style.transform = `translateX(${d * 58}%) translateZ(${-ad * 220}px) rotateY(${clamp(-d, -1, 1) * 42}deg) scale(${ad ? 0.86 : 1})`;
        el.style.zIndex = 10 - ad;
        el.style.opacity = ad > 2 ? 0 : 1 - ad * 0.22;
        el.setAttribute('aria-hidden', String(ad !== 0));
        el.classList.toggle('is-current', ad === 0);
        el.querySelector('button').tabIndex = ad === 0 ? 0 : -1;
      });
      if (title) title.textContent = items[cur].dataset.title;
      if (label) label.textContent = `${cur + 1} / ${items.length} · ${items[cur].dataset.group}`;
    };
    items.forEach((el, i) => {
      el.dataset.thumbHtml = el.querySelector('.cf-media').innerHTML;
      on(el, 'click', (e) => {
        if (i !== cur) { e.preventDefault(); set(i); return; }
        const m = el.querySelector('.cf-media');
        if (!m.querySelector('iframe')) m.innerHTML = `<iframe src="https://www.youtube-nocookie.com/embed/${el.dataset.yt}?autoplay=1&rel=0" title="${el.dataset.title.replace(/"/g, '&quot;')}" allow="autoplay; encrypted-media; picture-in-picture" allowfullscreen></iframe>`;
      });
    });
    on(root.querySelector('.cf-prev'), 'click', () => set(cur - 1));
    on(root.querySelector('.cf-next'), 'click', () => set(cur + 1));
    on(root, 'keydown', (e) => { if (e.key === 'ArrowLeft') set(cur - 1); if (e.key === 'ArrowRight') set(cur + 1); });
    let sx = null;
    on(root, 'pointerdown', (e) => { sx = e.clientX; });
    on(root, 'pointerup', (e) => { if (sx != null && Math.abs(e.clientX - sx) > 50) set(cur + (e.clientX < sx ? 1 : -1)); sx = null; });
    set(0);
  }

  window.SlugFX = {
    rifles, panthera, coverflow,
    destroy() { cleanups.forEach((f) => f()); cleanups = []; }
  };
})();
