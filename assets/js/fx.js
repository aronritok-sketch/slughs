/* ==========================================================================
   Slugshop – 3D videókarusszel (coverflow). A valódi 3D (WebGL) a fx3d.js-ben.
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
    coverflow,
    destroy() { cleanups.forEach((f) => f()); cleanups = []; }
  };
})();
