/* ==========================================================================
   Slugshop – a weboldal szkriptje (az előnézet app.js-éből, a WordPress / WooCommerce végpontjaira kötve).
   Fejléc, mobilmenü, élő kereső, nyitókép, terméklapozó, kategórialista („Továbbiak betöltése”, rendezés),
   kosárba tétel AJAX-szal, „Értesítést kérek” / kérdés / kapcsolat űrlap, 18+ kapu, süti-hozzájárulás
   (a mérőkódok csak ezután töltődnek), kisegítő eszközök, 3D és videó-lapozó indítása.
   JS nélkül is minden működik (linkek, űrlapok), ez csak kényelem.
   ========================================================================== */
(function () {
  'use strict';

  const C = window.SLUGSHOP || {};
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
  const esc = (s) => String(s == null ? '' : s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const reduce = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  // ------------------------------------------------------------- tárolás (süti; ha nincs, a böngésző tárhelye)
  const store = {
    get(k, d) {
      try { const m = document.cookie.match(new RegExp('(?:^|; )' + k + '=([^;]*)')); if (m) return JSON.parse(decodeURIComponent(m[1])); } catch (e) { /* tiltott süti */ }
      try { const v = localStorage.getItem(k); return v == null ? d : JSON.parse(v); } catch (e) { return d; }
    },
    set(k, v, days = 365) {
      const val = encodeURIComponent(JSON.stringify(v));
      try { document.cookie = `${k}=${val}; max-age=${days * 86400}; path=/; SameSite=Lax${location.protocol === 'https:' ? '; Secure' : ''}`; } catch (e) { /* tiltott süti */ }
      try { localStorage.setItem(k, JSON.stringify(v)); } catch (e) { /* nincs tárhely */ }
    }
  };

  // ------------------------------------------------------------- közös
  let toastTimer = null;
  function toast(html) {
    let t = $('.toast'); if (t) t.remove();
    t = document.createElement('div'); t.className = 'toast'; t.setAttribute('role', 'status'); t.innerHTML = html;
    document.body.appendChild(t);
    clearTimeout(toastTimer); toastTimer = setTimeout(() => t.remove(), 4200);
  }
  function setCount(n) {
    $$('[data-cart-count]').forEach((el) => { el.textContent = n; el.dataset.n = n; });
  }
  function modal(html, cls = '') {
    closeModal();
    const m = document.createElement('div');
    m.className = 'modal ' + cls; m.setAttribute('role', 'dialog'); m.setAttribute('aria-modal', 'true');
    m.innerHTML = `<div class="modal-box">${html}</div>`;
    document.body.appendChild(m);
    m.addEventListener('click', (e) => { if (e.target === m && !cls.includes('age')) closeModal(); });
    bindForms(m); initPhone(m);
    const f = $('input:not([type=hidden]):not([tabindex="-1"]), button', m); if (f) f.focus();
    return m;
  }
  function closeModal() { $$('.modal').forEach((m) => m.remove()); }

  // ------------------------------------------------------------- rétegek: mobilmenü, kereső
  function closeAll() {
    ['#drawer', '#search'].forEach((s) => { const el = $(s); if (el) { el.classList.remove('is-open'); el.setAttribute('aria-hidden', 'true'); } });
    const scrim = $('.scrim'); if (scrim) scrim.classList.remove('is-open');
    const b = $('[data-open-drawer]'); if (b) b.setAttribute('aria-expanded', 'false');
    const ap = $('.a11y-panel'); if (ap) ap.remove();
    document.documentElement.style.overflow = '';
  }
  function openLayer(sel) {
    closeAll();
    const el = $(sel); if (!el) return;
    el.classList.add('is-open'); el.setAttribute('aria-hidden', 'false');
    $('.scrim').classList.add('is-open');
    document.documentElement.style.overflow = 'hidden';
    if (sel === '#drawer') $('[data-open-drawer]').setAttribute('aria-expanded', 'true');
    const f = $('input:not([type=hidden]), a, button', el); if (f) setTimeout(() => f.focus(), 50);
  }

  // ------------------------------------------------------------- élő kereső
  let searchTimer = null; let searchSeq = 0;
  function liveSearch(q) {
    const box = $('#search-results'); const hint = $('#search-hint');
    clearTimeout(searchTimer);
    if (q.trim().length < 2) { box.innerHTML = ''; hint.textContent = 'Írd be a termék nevét, a kalibert vagy a cikkszámot.'; return; }
    searchTimer = setTimeout(async () => {
      const seq = ++searchSeq;
      try {
        const r = await fetch(C.rest + 'search?q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' } });
        const d = await r.json(); if (seq !== searchSeq) return;
        hint.textContent = d.total ? `${d.total} találat – Enter: az összes megjelenítése` : 'Nincs találat.';
        box.innerHTML = d.items.map((p) => `<a class="search-hit" href="${esc(p.url)}">${p.img ? `<img src="${esc(p.img)}" alt="">` : '<span></span>'}<span><b>${esc(p.name)}</b><small>${esc(p.stock)}</small></span><span class="p">${esc(p.price)}</span></a>`).join('');
      } catch (e) { hint.textContent = 'A kereső most nem érhető el – Enter: keresés.'; }
    }, 180);
  }

  // ------------------------------------------------------------- nyitókép, lapozók
  function initHero() {
    const hero = $('.hero'); if (!hero) return;
    const slides = $$('.hero-slide', hero); const dots = $$('.hero-dots button', hero);
    let cur = 0; let timer = null;
    const go = (n) => {
      cur = (n + slides.length) % slides.length;
      slides.forEach((s, i) => { s.classList.toggle('is-active', i === cur); s.setAttribute('aria-hidden', String(i !== cur)); $$('a', s).forEach((a) => { a.tabIndex = i === cur ? 0 : -1; }); });
      dots.forEach((d, i) => d.setAttribute('aria-current', String(i === cur)));
    };
    const play = () => { clearInterval(timer); if (!reduce() && slides.length > 1) timer = setInterval(() => go(cur + 1), 6500); };
    dots.forEach((d, i) => d.addEventListener('click', () => { go(i); play(); }));
    $('.hero-arrow.prev', hero).addEventListener('click', () => { go(cur - 1); play(); });
    $('.hero-arrow.next', hero).addEventListener('click', () => { go(cur + 1); play(); });
    hero.addEventListener('mouseenter', () => clearInterval(timer));
    hero.addEventListener('mouseleave', play);
    hero.addEventListener('focusin', () => clearInterval(timer));
    play();
  }
  function initCarousels() {
    $$('.carousel').forEach((c) => {
      const track = $('.carousel-track', c);
      $('.carousel-btn.prev', c).addEventListener('click', () => track.scrollBy({ left: -track.clientWidth, behavior: reduce() ? 'auto' : 'smooth' }));
      $('.carousel-btn.next', c).addEventListener('click', () => track.scrollBy({ left: track.clientWidth, behavior: reduce() ? 'auto' : 'smooth' }));
    });
  }

  // ------------------------------------------------------------- kategórialista
  function initList() {
    const bar = $('form[data-autosubmit]');
    if (bar) $$('select, input[type=checkbox]', bar).forEach((el) => el.addEventListener('change', () => bar.submit()));
    document.addEventListener('click', async (e) => {
      const more = e.target.closest('[data-more]'); if (!more) return;
      e.preventDefault(); more.setAttribute('aria-busy', 'true'); more.textContent = 'Betöltés…';
      const loadedUrl = more.href;
      try {
        const html = await (await fetch(more.href, { credentials: 'same-origin' })).text();
        const doc = new DOMParser().parseFromString(html, 'text/html');
        const grid = $('#grid'); const first = grid.children.length;
        $$('#grid > li.product', doc).forEach((li) => grid.appendChild(document.importNode(li, true)));
        const cnt = $('#count', doc); if (cnt) $('#count').textContent = cnt.textContent;
        const next = $('[data-more]', doc);
        if (next) { more.href = next.href; more.textContent = 'Továbbiak betöltése'; more.removeAttribute('aria-busy'); } else more.remove();
        const f = grid.children[first] && $('a', grid.children[first]); if (f) f.focus({ preventScroll: true });
        history.replaceState(null, '', loadedUrl);
      } catch (err) { location.href = more.href; }
    });
  }

  // ------------------------------------------------------------- termékoldal
  function initProduct() {
    $$('[data-thumb]').forEach((b) => b.addEventListener('click', () => {
      const img = $('#main-img'); if (!img) return;
      img.src = b.dataset.thumb; img.removeAttribute('srcset');
      $$('[data-thumb]').forEach((x) => x.setAttribute('aria-current', String(x === b)));
    }));
    $$('.quantity').forEach((q) => {
      const input = $('input', q); if (!input) return;
      $$('[data-q]', q).forEach((b) => b.addEventListener('click', () => {
        const max = Number(input.max) || 99;
        input.value = Math.max(1, Math.min(max, (Number(input.value) || 1) + Number(b.dataset.q)));
        input.dispatchEvent(new Event('change', { bubbles: true }));
      }));
    });
  }

  async function addToCart(id, qty, fallbackUrl) {
    if (!C.ajax) { location.href = fallbackUrl; return; }
    try {
      const body = new URLSearchParams({ product_id: id, quantity: qty });
      const r = await fetch(C.ajax.replace('%%endpoint%%', 'add_to_cart'), { method: 'POST', body, credentials: 'same-origin' });
      const d = await r.json();
      if (!d || d.error) { location.href = (d && d.product_url) || fallbackUrl; return; }
      const frag = d.fragments && d.fragments['span.cart-count'];
      if (frag) { const n = (frag.match(/data-n="(\d+)"/) || [0, 0])[1]; setCount(n); }
      toast(`<span>A termék a kosárba került.</span><a href="${esc(C.cart)}">Kosár</a>`);
    } catch (e) { location.href = fallbackUrl; }
  }

  // ------------------------------------------------------------- űrlapok
  function validate(form) {
    let ok = true;
    $$('.field-error', form).forEach((e) => e.remove());
    $$('.has-error', form).forEach((e) => e.classList.remove('has-error'));
    $$('input, textarea, select', form).forEach((el) => {
      if (el.closest('[hidden]') || el.closest('.hp') || el.type === 'file' || el.type === 'hidden') return;
      const v = (el.value || '').trim();
      let msg = '';
      if (el.type === 'checkbox') { if (el.required && !el.checked) { el.closest('.check').classList.add('has-error'); ok = false; } return; }
      if (el.required && !v) msg = 'Kötelező mező.';
      else if (v && el.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)) msg = 'Érvényes e-mail címet adj meg.';
      else if (v && el.hasAttribute('data-phone') && v.replace(/\D/g, '').length < 10) msg = 'Teljes telefonszámot adj meg.';
      if (msg) { ok = false; showError(el, msg); }
    });
    if (!ok) { const first = $('.has-error input, .has-error textarea, .check.has-error input', form); if (first) first.focus(); }
    return ok;
  }
  function showError(el, msg) {
    const f = el.closest('.field'); if (!f) return;
    f.classList.add('has-error'); f.insertAdjacentHTML('beforeend', `<span class="field-error">${esc(msg)}</span>`);
  }
  function bindForms(root) {
    $$('form[data-form]', root).forEach((form) => {
      if (form.dataset.bound) return; form.dataset.bound = '1';
      form.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!validate(form)) return;
        const btn = $('button[type=submit]', form); btn.disabled = true; btn.setAttribute('aria-busy', 'true');
        const fd = new FormData(form);
        if (form._files) { fd.delete('files[]'); form._files.forEach((f) => fd.append('files[]', f)); }
        try {
          const r = await fetch(C.rest + 'form/' + form.dataset.form, { method: 'POST', body: fd, credentials: 'same-origin' });
          const d = await r.json();
          if (d.ok) { form.outerHTML = `<div class="form-ok" role="status">${esc(d.message)}</div>`; return; }
          Object.entries(d.errors || {}).forEach(([name, msg]) => {
            const el = form.querySelector(`[name="${name}"]`);
            if (el && el.type === 'checkbox') el.closest('.check').classList.add('has-error'); else if (el) showError(el, msg); else toast(esc(msg));
          });
        } catch (err) { toast('A küldés nem sikerült. Próbáld újra, vagy hívj minket.'); }
        btn.disabled = false; btn.removeAttribute('aria-busy');
      });
    });
    $$('.dropzone', root).forEach((dz) => {
      const input = $('input[type=file]', dz); const list = dz.parentElement.querySelector('.files'); const form = dz.closest('form');
      const show = (files) => { form._files = Array.from(files).slice(0, 5); list.innerHTML = form._files.map((f) => `<span>${esc(f.name)}</span>`).join(''); };
      dz.addEventListener('click', (e) => { if (e.target !== input) input.click(); });
      dz.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); input.click(); } });
      input.addEventListener('change', () => show(input.files));
      ['dragenter', 'dragover'].forEach((ev) => dz.addEventListener(ev, (e) => { e.preventDefault(); dz.classList.add('is-over'); }));
      ['dragleave', 'drop'].forEach((ev) => dz.addEventListener(ev, (e) => { e.preventDefault(); dz.classList.remove('is-over'); }));
      dz.addEventListener('drop', (e) => { if (e.dataTransfer) show(e.dataTransfer.files); });
    });
  }
  const guard = () => `<div class="hp" aria-hidden="true"><label>Weboldal<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div><input type="hidden" name="ts" value="${Math.floor(Date.now() / 1000)}">`;
  function initPhone(root) {
    $$('[data-phone]', root).forEach((el) => {
      if (el.dataset.mask) return; el.dataset.mask = '1';
      el.addEventListener('input', () => {
        let d = el.value.replace(/\D/g, '');
        if (d.startsWith('06')) d = '36' + d.slice(2);
        if (!d.startsWith('36')) d = '36' + d;
        d = d.slice(0, 11);
        const p = [d.slice(2, 4), d.slice(4, 7), d.slice(7, 11)].filter(Boolean);
        el.value = '+36' + (p.length ? ' ' + p.join(' ') : '');
      });
    });
  }

  // ------------------------------------------------------------- 18+ kapu, sütik, mérőkódok
  function ageGate() {
    if (store.get('slugshop_age', false)) { cookieBar(); return; }
    const a = C.age || {};
    const m = modal(`<h2>${esc(a.title || 'Elmúltál 18 éves?')}</h2><p class="sub">${esc(a.sub || '')}</p>
      <div class="age-actions"><button class="btn btn--primary" type="button" data-age="yes">Igen</button><button class="btn" type="button" data-age="no">Nem</button></div>
      ${a.noteTitle ? `<div class="age-note"><strong>${esc(a.noteTitle)}</strong>${(a.note || []).map((n) => `<p>${esc(n)}</p>`).join('')}</div>` : ''}`, 'age');
    m.addEventListener('click', (e) => {
      const b = e.target.closest('[data-age]'); if (!b) return;
      if (b.dataset.age === 'yes') { store.set('slugshop_age', true); closeModal(); cookieBar(); }
      else $('.modal-box', m).innerHTML = '<h2>Sajnáljuk</h2><p class="sub">Weboldalunkat csak 18 éven felüliek látogathatják.</p>';
    });
  }
  function cookieBar(force) {
    const saved = store.get('slugshop_consent', null);
    if (saved && !force) { applyConsent(saved); return; }
    if ($('.cookie')) return;
    const c = C.cookie || {};
    const el = document.createElement('div');
    el.className = 'cookie'; el.setAttribute('role', 'dialog'); el.setAttribute('aria-label', 'Sütik');
    el.innerHTML = `<p><b>${esc(c.title || 'Sütik')}</b></p><p>${esc(c.text || '')} <a href="${esc(C.privacy)}">Adatvédelmi irányelvek</a></p>
      <div class="cookie-opts" ${force ? '' : 'hidden'}>
        <label class="check"><input type="checkbox" checked disabled> Kötelező</label>
        <label class="check"><input type="checkbox" data-ck="pref" ${!saved || saved.pref ? 'checked' : ''}> Ajánlott</label>
        <label class="check"><input type="checkbox" data-ck="stat" ${saved && saved.stat ? 'checked' : ''}> Teljesítmény mérése</label>
        <label class="check"><input type="checkbox" data-ck="mkt" ${saved && saved.mkt ? 'checked' : ''}> Saját marketinges tevékenység</label>
      </div>
      <div class="cookie-actions"><button class="btn" type="button" data-ck-act="deny">Elutasítom</button><button class="btn" type="button" data-ck-act="settings">${force ? 'Engedélyezem' : 'Beállítások'}</button><button class="btn btn--primary" type="button" data-ck-act="all">Összes elfogadása</button></div>`;
    document.body.appendChild(el);
    el.addEventListener('click', (e) => {
      const b = e.target.closest('[data-ck-act]'); if (!b) return;
      const act = b.dataset.ckAct;
      if (act === 'settings') { const o = $('.cookie-opts', el); if (o.hidden) { o.hidden = false; b.textContent = 'Engedélyezem'; return; } }
      const pick = act === 'all' ? { pref: true, stat: true, mkt: true } : act === 'deny' ? { pref: false, stat: false, mkt: false } : Object.fromEntries($$('[data-ck]', el).map((i) => [i.dataset.ck, i.checked]));
      store.set('slugshop_consent', pick); el.remove(); applyConsent(pick);
    });
  }
  let loaded = {};
  function addScript(src, attrs = {}) { const s = document.createElement('script'); s.async = true; s.src = src; Object.assign(s, attrs); document.head.appendChild(s); }
  function applyConsent(c) {
    const t = C.trackers || {};
    if (window.gtag) {
      window.gtag('consent', 'update', { analytics_storage: c.stat ? 'granted' : 'denied', ad_storage: c.mkt ? 'granted' : 'denied', ad_user_data: c.mkt ? 'granted' : 'denied', ad_personalization: c.mkt ? 'granted' : 'denied' });
    }
    if (t.gtm && (c.stat || c.mkt) && !loaded.gtm) {
      loaded.gtm = true; window.dataLayer = window.dataLayer || []; window.dataLayer.push({ 'gtm.start': Date.now(), event: 'gtm.js' });
      addScript('https://www.googletagmanager.com/gtm.js?id=' + encodeURIComponent(t.gtm));
    }
    if (t.fbPixel && c.mkt && !loaded.fb) {
      loaded.fb = true;
      /* eslint-disable */
      !function (f, b, e, v, n, t2, s) { if (f.fbq) return; n = f.fbq = function () { n.callMethod ? n.callMethod.apply(n, arguments) : n.queue.push(arguments); }; if (!f._fbq) f._fbq = n; n.push = n; n.loaded = !0; n.version = '2.0'; n.queue = []; t2 = b.createElement(e); t2.async = !0; t2.src = v; s = b.getElementsByTagName(e)[0]; s.parentNode.insertBefore(t2, s); }(window, document, 'script', 'https://connect.facebook.net/en_US/fbevents.js');
      /* eslint-enable */
      window.fbq('init', t.fbPixel); window.fbq('track', 'PageView');
    }
    if (t.barionPixel && c.mkt && !loaded.barion) {
      loaded.barion = true; window.barion_pixel_id = t.barionPixel;
      window.bp = window.bp || function () { (window.bp.q = window.bp.q || []).push(arguments); };
      addScript('https://pixel.barion.com/bp.js'); window.bp('init', 'addBarionPixelId', t.barionPixel);
    }
  }

  // ------------------------------------------------------------- kisegítő eszközök
  function a11yPanel(btn) {
    const ex = $('.a11y-panel'); if (ex) { ex.remove(); btn.setAttribute('aria-expanded', 'false'); return; }
    const opts = [['a11y-big', 'Nagyobb betű'], ['a11y-contrast', 'Erősebb kontraszt'], ['a11y-links', 'Hivatkozások kiemelése']];
    const p = document.createElement('div'); p.className = 'a11y-panel'; p.setAttribute('role', 'group'); p.setAttribute('aria-label', 'Kisegítő eszközök');
    p.innerHTML = '<b>Kisegítő eszközök</b>' + opts.map(([c, l]) => `<button type="button" data-a11y-opt="${c}" aria-pressed="${document.documentElement.classList.contains(c)}">${l}</button>`).join('') + '<button type="button" data-a11y-opt="reset">Alaphelyzet</button>';
    document.body.appendChild(p); btn.setAttribute('aria-expanded', 'true');
    p.addEventListener('click', (e) => {
      const b = e.target.closest('[data-a11y-opt]'); if (!b) return;
      const c = b.dataset.a11yOpt; const h = document.documentElement;
      if (c === 'reset') { opts.forEach(([x]) => h.classList.remove(x)); $$('[data-a11y-opt]', p).forEach((x) => x.setAttribute('aria-pressed', 'false')); }
      else { h.classList.toggle(c); b.setAttribute('aria-pressed', String(h.classList.contains(c))); }
      store.set('slugshop_a11y', opts.map(([x]) => x).filter((x) => h.classList.contains(x)));
    });
  }

  // ------------------------------------------------------------- indítás
  function boot() {
    (store.get('slugshop_a11y', []) || []).forEach((c) => document.documentElement.classList.add(c));
    const hdr = $('.site-header');
    if (hdr) window.addEventListener('scroll', () => hdr.classList.toggle('is-scrolled', window.scrollY > 40), { passive: true });

    document.addEventListener('click', (e) => {
      const t = e.target;
      const add = t.closest('[data-add]');
      if (add) { e.preventDefault(); addToCart(add.dataset.add, 1, add.href); return; }
      const nt = t.closest('[data-notify]');
      if (nt) {
        modal(`<button class="close-btn" type="button" data-modal-close aria-label="Bezárás" style="float:right">×</button><h2 style="font-size:1.5rem">Értesítést kérek!</h2><p class="sub">${esc(nt.dataset.name)}</p>
          <form class="form form-dark" data-form="notify" novalidate style="text-align:left">${guard()}<input type="hidden" name="product" value="${esc(nt.dataset.notify)}">
          <div class="field"><label for="n-email">E-mail cím *</label><input id="n-email" name="your-email" type="email" required autocomplete="email"></div>
          <div><button class="btn btn--primary" type="submit">Értesítést kérek!</button></div></form>`);
        return;
      }
      const ask = t.closest('[data-ask]');
      if (ask) {
        modal(`<button class="close-btn" type="button" data-modal-close aria-label="Bezárás" style="float:right">×</button><h2 style="font-size:1.5rem">Kérdezz a termékről</h2><p class="sub">${esc(ask.dataset.name)}</p>
          <form class="form form-dark" data-form="ask" novalidate style="text-align:left">${guard()}<input type="hidden" name="product" value="${esc(ask.dataset.ask)}">
          <div class="field"><label for="a-name">Név *</label><input id="a-name" name="your-name" required autocomplete="name"></div>
          <div class="field"><label for="a-email">E-mail cím *</label><input id="a-email" name="your-email" type="email" required autocomplete="email"></div>
          <div class="field"><label for="a-q">Kérdés *</label><textarea id="a-q" name="your-message" required></textarea></div>
          <div><button class="btn btn--primary" type="submit">Küldés</button></div></form>`);
        return;
      }
      if (t.closest('[data-modal-close]')) { closeModal(); return; }
      if (t.closest('[data-open-drawer]')) { openLayer('#drawer'); return; }
      if (t.closest('[data-open-search]')) { openLayer('#search'); return; }
      if (t.closest('[data-close]')) { closeAll(); return; }
      if (t.closest('[data-toggle-sidebar]')) { const s = $('#sidebar'); s.classList.toggle('is-open'); t.closest('[data-toggle-sidebar]').setAttribute('aria-expanded', String(s.classList.contains('is-open'))); return; }
      const dt = t.closest('.drawer-tog');
      if (dt) { const sub = $('#' + dt.getAttribute('aria-controls')); const open = sub.hidden; sub.hidden = !open; dt.setAttribute('aria-expanded', String(open)); return; }
      const tog = t.closest('.cat-tree .tog');
      if (tog) { const ul = tog.closest('li').querySelector('ul'); const open = ul.hidden; ul.hidden = !open; tog.setAttribute('aria-expanded', String(open)); return; }
      const yt = t.closest('.yt[data-yt]'); if (yt) { yt.innerHTML = `<iframe src="https://www.youtube-nocookie.com/embed/${encodeURIComponent(yt.dataset.yt)}?autoplay=1" title="YouTube-videó" allow="autoplay; encrypted-media; picture-in-picture" allowfullscreen></iframe>`; return; }
      const ab = t.closest('[data-a11y]'); if (ab) { a11yPanel(ab); return; }
      if (t.closest('[data-cookie-settings]')) { e.preventDefault(); cookieBar(true); return; }
      if (t.closest('.skip-link')) { const m = $('#main'); if (m) { e.preventDefault(); m.focus(); m.scrollIntoView(); } }
    });
    const cf = $('form[data-cart-form]');
    if (cf) cf.addEventListener('submit', (e) => {
      const btn = e.submitter || $('[name=add-to-cart]', cf); if (!btn || !C.ajax) return;
      e.preventDefault(); addToCart(btn.value, Math.max(1, Number($('[name=quantity]', cf).value) || 1), cf.action + (cf.action.includes('?') ? '&' : '?') + 'add-to-cart=' + btn.value);
    });
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') { closeAll(); if (!$('.modal.age')) closeModal(); }
      if (e.key === '/' && !/input|textarea|select/i.test(document.activeElement.tagName) && !$('.modal.age')) { e.preventDefault(); openLayer('#search'); }
    });
    const q = $('#q'); if (q) q.addEventListener('input', (e) => liveSearch(e.target.value));

    bindForms(document); initPhone(document);
    initHero(); initCarousels(); initList(); initProduct();
    if (window.SlugFX) $$('.coverflow').forEach(window.SlugFX.coverflow);
    if (window.SlugFX3D) { $$('.rifles').forEach(window.SlugFX3D.rifles); $$('.panthera').forEach(window.SlugFX3D.panthera); }
    if (document.body.classList.contains('show-register')) { const r = $('#reg_email'); if (r) r.focus(); }
    ageGate();
  }

  // A szkriptek késleltetve (defer) töltődnek: a DOMContentLoaded előtt mind lefut, így a 3D- és a videó-modul is
  // kész, mire indulunk. Tartalék: ha valamiért már későn töltődnénk, a load eseményre.
  let booted = false;
  const start = () => { if (!booted) { booted = true; boot(); } };
  if (document.readyState === 'complete') start();
  else { document.addEventListener('DOMContentLoaded', start); window.addEventListener('load', start); }
})();
