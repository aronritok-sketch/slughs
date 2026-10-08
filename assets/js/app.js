/* ==========================================================================
   Slugshop – kattintható előnézet
   Egyetlen oldal, a # utáni rész a régi slugshop.hu útvonala (pl. #/zan-slugok/…),
   így a WordPressben ugyanazok az URL-ek maradhatnak. Az adatok a data.js-ből
   (tools/extract.py) jönnek. A kosár, a pénztár és az űrlapok csak bemutatók:
   semmit nem küldenek el.
   ========================================================================== */
(function () {
  'use strict';

  const S = window.SLUG;
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
  const esc = (s) => String(s == null ? '' : s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const norm = (s) => String(s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
  const ft = (n) => (n == null ? '' : String(Math.round(n)).replace(/\B(?=(\d{3})+(?!\d))/g, '.') + ' Ft');
  const store = {
    get(k, d) { try { const v = localStorage.getItem(k); return v == null ? d : JSON.parse(v); } catch (e) { return d; } },
    set(k, v) { try { localStorage.setItem(k, JSON.stringify(v)); } catch (e) { /* nincs tárhely */ } }
  };
  const IMG = S.site.img;

  const ICON = {
    search: '<svg viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="2"><circle cx="13" cy="13" r="10"/><path d="m21 21 9 9"/></svg>',
    cart: '<svg viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"><path d="M1 3h5l3.5 17h17l3-12H8"/><circle cx="12" cy="27" r="2"/><circle cx="24" cy="27" r="2"/></svg>',
    menu: '<svg viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 8h24M4 16h24M4 24h24"/></svg>',
    down: '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2"><path d="m3 6 5 5 5-5"/></svg>',
    left: '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2"><path d="m10 3-5 5 5 5"/></svg>',
    right: '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 3 5 5-5 5"/></svg>',
    mail: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M2 5h20v14H2z" opacity=".15"/><path d="M2 5h20v14H2V5zm2 2v.5l8 5.5 8-5.5V7H4zm16 2.9-8 5.5-8-5.5V17h16V9.9z"/></svg>',
    a11y: '<svg viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="4" r="2"/><path d="M4 8.5 12 10l8-1.5.4 1.8-6 1.7V15l2.4 6.5-1.8.7L12 16.6l-3 5.6-1.8-.7L9.6 15v-3l-6-1.7z"/></svg>',
    box: '<svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2"><path d="M24 4 42 13v22L24 44 6 35V13z"/><path d="m6 13 18 9 18-9M24 22v22"/></svg>'
  };

  // ------------------------------------------------------------- indexek
  const P = S.products;
  const C = S.cats;
  const byPath = {};
  Object.keys(P).forEach((id) => { byPath[P[id].path] = id; });
  const catChain = (path) => { const out = []; let c = C[path]; while (c) { out.unshift(c); c = C[c.parent]; } return out; };
  const allProductsIn = (path, acc = new Set()) => {
    const c = C[path]; if (!c) return acc;
    c.products.forEach((id) => acc.add(id));
    c.children.forEach((ch) => allProductsIn(ch, acc));
    return acc;
  };

  // ------------------------------------------------------------- kosár
  let cart = store.get('slugshop_cart', []).filter((l) => P[l.id] && P[l.id].stock === 'in');
  const cartCount = () => cart.reduce((a, l) => a + l.qty, 0);
  const cartTotal = () => cart.reduce((a, l) => a + (P[l.id].price || 0) * l.qty, 0);
  function saveCart() {
    store.set('slugshop_cart', cart);
    $$('[data-cart-count]').forEach((el) => { el.textContent = cartCount(); el.dataset.n = cartCount(); });
  }
  function addToCart(id, qty) {
    const p = P[id]; if (!p || p.stock !== 'in') return;
    const l = cart.find((x) => x.id === id);
    if (l) l.qty = Math.min(99, l.qty + qty); else cart.push({ id, qty });
    saveCart();
    toast(`<span><b>${esc(p.name)}</b> a kosárba került.</span><a href="#/kosar">Kosár</a>`);
  }

  // ------------------------------------------------------------- közös elemek
  let toastTimer = null;
  function toast(html) {
    let t = $('.toast'); if (t) t.remove();
    t = document.createElement('div'); t.className = 'toast'; t.setAttribute('role', 'status'); t.innerHTML = html;
    document.body.appendChild(t);
    clearTimeout(toastTimer); toastTimer = setTimeout(() => t.remove(), 3800);
  }

  function productCard(id) {
    const p = P[id];
    const inStock = p.stock === 'in';
    const action = inStock
      ? `<button type="button" class="button add_to_cart_button" data-add="${id}">${ICON.cart.replace('<svg', '<svg class="icon"')}Kosárba</button>`
      : `<button type="button" class="button" data-notify="${id}">Értesítést kérek!</button>`;
    return `<li class="product ${inStock ? 'instock' : 'outofstock'}">
      ${inStock ? '' : '<span class="badge-out">Elfogyott</span>'}
      <a href="#${esc(p.path)}" class="woocommerce-LoopProduct-link">
        <span class="img">${p.img ? `<img src="${p.img}" alt="${esc(p.name)}" loading="lazy" width="480" height="480">` : ICON.box}</span>
        <h2 class="woocommerce-loop-product__title">${esc(p.name)}</h2>
        <span class="price">${ft(p.price)}</span>
      </a>
      <div class="actions">${action}</div>
    </li>`;
  }

  function header() {
    const m = S.site.menu;
    const sub = (path) => {
      const c = C[path]; if (!c || !c.children.length) return '';
      return `<ul class="sub">${c.children.map((ch) => `<li><a href="#${ch}">${esc(C[ch].menu)}</a></li>`).join('')}</ul>`;
    };
    const nav = m.map(([label, path]) => `<li><a href="#${path}" data-nav="${path}">${esc(label)}</a>${sub(path)}</li>`).join('');
    return `
    <a class="skip-link" href="#main" data-skip>Ugrás a tartalomra</a>
    <div class="topbar"><div class="wrap">
      <div class="topbar-group">
        <a href="${S.site.phoneHref}"><img src="${IMG.phone}" alt="">${esc(S.site.phone)}</a>
        <a class="t-hide" href="mailto:${S.site.email}"><img src="${IMG.email}" alt="">${esc(S.site.email)}</a>
      </div>
      <div class="topbar-group">
        <span class="social t-hide">
          <a href="${S.site.social.facebook}" target="_blank" rel="noopener" aria-label="Facebook"><img src="${IMG.fb}" alt=""></a>
          <a href="${S.site.social.instagram}" target="_blank" rel="noopener" aria-label="Instagram"><img src="${IMG.insta}" alt=""></a>
          <a href="${S.site.social.youtube}" target="_blank" rel="noopener" aria-label="YouTube"><img src="${IMG.yt}" alt=""></a>
        </span>
        <a href="#/bejelentkezes"><img src="${IMG.login}" alt="">Belépés</a>
        <a class="t-hide" href="#/regisztracio"><img src="${IMG.register}" alt="">Regisztráció</a>
        <label class="cur"><span class="sr-only">Pénznem</span><select id="cur"><option>HUF</option><option>EUR</option></select></label>
      </div>
    </div></div>
    <header class="site-header"><div class="wrap">
      <button class="close-btn burger" type="button" data-open-drawer aria-label="Menü megnyitása">${ICON.menu.replace('<svg', '<svg width="30" height="30"')}</button>
      <a class="logo" href="#/" aria-label="Slugshop – kezdőlap"><img src="${IMG.logo}" alt="Slugshop logó" width="84" height="84"></a>
      <nav class="main-nav" aria-label="Fő menü"><ul>${nav}</ul></nav>
      <div class="header-icons">
        <button type="button" data-open-search aria-label="Kereső">${ICON.search}</button>
        <a href="#/kosar" aria-label="Kosár">${ICON.cart}<span class="cart-count" data-cart-count data-n="0">0</span></a>
      </div>
    </div></header>
    <div class="drawer" id="drawer" aria-label="Menü" aria-hidden="true">
      <div class="drawer-head"><img src="${IMG.logo}" alt="Slugshop"><button class="close-btn" type="button" data-close aria-label="Menü bezárása">×</button></div>
      <nav>${m.map(([label, path]) => `<a href="#${path}" data-nav="${path}">${esc(label)}</a>` + (C[path] ? C[path].children.map((ch) => `<a class="sub" href="#${ch}" data-nav="${ch}">${esc(C[ch].menu)}</a>`).join('') : '')).join('')}
      ${S.site.footerMenu.map(([l, p]) => `<a href="#${p}" data-nav="${p}">${esc(l)}</a>`).join('')}</nav>
      <div class="drawer-foot">
        <a href="${S.site.phoneHref}"><img src="${IMG.phone}" alt="">${esc(S.site.phone)}</a>
        <a href="mailto:${S.site.email}"><img src="${IMG.email}" alt="">${esc(S.site.email)}</a>
        <a href="#/bejelentkezes"><img src="${IMG.login}" alt="">Belépés</a>
        <a href="#/regisztracio"><img src="${IMG.register}" alt="">Regisztráció</a>
      </div>
    </div>
    <div class="scrim" data-close></div>
    <div class="search-panel" id="search" role="dialog" aria-label="Kereső" aria-hidden="true"><div class="wrap">
      <form class="search-row" id="search-form" role="search">
        <label class="sr-only" for="q">Keresés a termékek között</label>
        <input id="q" type="search" placeholder="Keresés…" autocomplete="off">
        <button class="btn" type="submit">Keresés</button>
        <button class="close-btn" type="button" data-close aria-label="Kereső bezárása">×</button>
      </form>
      <p class="search-hint" id="search-hint">Írd be a termék nevét, a kalibert vagy a cikkszámot.</p>
      <div class="search-results" id="search-results"></div>
    </div></div>`;
  }

  function footer() {
    const age = S.site.age || {};
    return `<footer class="site-footer"><div class="wrap">
      <div class="footer-grid">
        <a class="footer-logo" href="#/" aria-label="Slugshop – kezdőlap"><img src="${IMG.logoFooter}" alt="Slugshop logó" loading="lazy"></a>
        <div class="footer-contact">
          <a href="${S.site.phoneHref}"><img src="${IMG.phone}" alt="">${esc(S.site.phone)}</a>
          <a href="mailto:${S.site.email}"><img src="${IMG.email}" alt="">${esc(S.site.email)}</a>
        </div>
        <ul class="footer-menu">${S.site.menu.map(([l, p]) => `<li><a href="#${p}" data-nav="${p}">${esc(l)}</a></li>`).join('')}</ul>
        <ul class="footer-menu">${S.site.footerMenu.map(([l, p]) => `<li><a href="#${p}" data-nav="${p}">${esc(l)}</a></li>`).join('')}</ul>
      </div>
      ${age.noteTitle ? `<div class="footer-notice"><strong>${esc(age.noteTitle)}</strong> ${age.note.map(esc).join(' ')}</div>` : ''}
      <div class="footer-bottom">
        <span>© ${new Date().getFullYear()} Slugshop Kft. · <a href="https://fogyasztobarat.hu/tanusitvany-ellenorzes/?csi=MB0C642A" target="_blank" rel="noopener">Fogyasztó Barát tanúsítvány</a></span>
        <span>Készítette: HelloProVision</span>
      </div>
    </div></footer>
    <button class="a11y-btn" type="button" data-a11y aria-label="Kisegítő eszközök" aria-expanded="false">${ICON.a11y}</button>
    <a class="preview-pill" href="#/elonezet">Előnézet – mi változott?</a>`;
  }

  // ------------------------------------------------------------- űrlapok
  function contactForm(dark) {
    return `<form class="form ${dark ? 'form-dark' : ''}" data-form="contact" novalidate>
      <div class="form-grid">
        <div class="field"><label for="cf-nev">Név *</label><input id="cf-nev" name="nev" required autocomplete="name"></div>
        <div class="field"><label for="cf-tel">Telefon *</label><input id="cf-tel" name="tel" type="tel" required autocomplete="tel" placeholder="+36 ( __ ) ___ - ____" data-phone></div>
        <div class="field"><label for="cf-email">Email</label><input id="cf-email" name="email" type="email" autocomplete="email"></div>
        <div class="field"><span class="label" style="font-size:var(--fs-small);font-weight:600">Kép feltöltése</span>
          <div class="dropzone" tabindex="0" role="button" aria-label="Kép feltöltése">Húzd és dobáld be a fájlokat ide<input type="file" hidden multiple accept=".jpg,.jpeg,.png,.pdf,.doc,.heic,.webp,.svg"></div>
          <div class="files"></div></div>
      </div>
      <div class="field"><label for="cf-uzenet">Üzenet</label><textarea id="cf-uzenet" name="uzenet"></textarea></div>
      <label class="check"><input type="checkbox" required> <span>Elfogadom az <a href="#/adatvedelmi-nyilatkozat">adatvédelmi nyilatkozatot</a></span></label>
      <div><button class="btn" type="submit">Küldés</button></div>
    </form>`;
  }

  function validate(form) {
    let ok = true;
    $$('.field-error', form).forEach((e) => e.remove());
    $$('.has-error', form).forEach((e) => e.classList.remove('has-error'));
    $$('input, textarea, select', form).forEach((el) => {
      if (el.closest('[hidden]') || el.type === 'file') return;
      const v = (el.value || '').trim();
      let msg = '';
      if (el.type === 'checkbox') { if (el.required && !el.checked) { el.closest('.check').classList.add('has-error'); ok = false; } return; }
      if (el.required && !v) msg = 'Kötelező mező.';
      else if (v && el.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)) msg = 'Érvényes e-mail címet adj meg.';
      else if (v && el.hasAttribute('data-phone') && v.replace(/\D/g, '').length < 10) msg = 'Teljes telefonszámot adj meg.';
      else if (el.dataset.match && v !== $('#' + el.dataset.match, form).value) msg = 'A két jelszó nem egyezik.';
      if (msg) { ok = false; const f = el.closest('.field'); if (f) { f.classList.add('has-error'); f.insertAdjacentHTML('beforeend', `<span class="field-error">${msg}</span>`); } }
    });
    if (!ok) { const first = $('.has-error input, .has-error textarea, .check.has-error input', form); if (first) first.focus(); }
    return ok;
  }

  function bindForms(root) {
    $$('form[data-form]', root).forEach((form) => {
      form.addEventListener('submit', (e) => {
        e.preventDefault();
        if (!validate(form)) return;
        const kind = form.dataset.form;
        if (kind === 'checkout') return placeOrder(form);
        const msgs = {
          contact: 'Köszönjük, az üzenetedet megkaptuk. (Előnézet: az űrlap nem küldött semmit.)',
          login: 'Előnézet: a belépés a WordPress-változatban működik majd.',
          register: 'Előnézet: a regisztráció a WordPress-változatban működik majd.',
          notify: 'Rendben, szólunk, ha a termék újra raktárra kerül. (Előnézet: nem küldtünk semmit.)',
          ask: 'Köszönjük a kérdést, hamarosan válaszolunk. (Előnézet: nem küldtünk semmit.)'
        };
        form.outerHTML = `<div class="form-ok" role="status">${msgs[kind] || 'Kész.'}</div>`;
      });
    });
    $$('.dropzone', root).forEach((dz) => {
      const input = $('input[type=file]', dz); const list = dz.parentElement.querySelector('.files');
      const show = (files) => { list.innerHTML = Array.from(files).map((f) => `<span>${esc(f.name)}</span>`).join(''); };
      dz.addEventListener('click', (e) => { if (e.target !== input) input.click(); });
      dz.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); input.click(); } });
      input.addEventListener('change', () => show(input.files));
      ['dragenter', 'dragover'].forEach((ev) => dz.addEventListener(ev, (e) => { e.preventDefault(); dz.classList.add('is-over'); }));
      ['dragleave', 'drop'].forEach((ev) => dz.addEventListener(ev, (e) => { e.preventDefault(); dz.classList.remove('is-over'); }));
      dz.addEventListener('drop', (e) => { if (e.dataTransfer) show(e.dataTransfer.files); });
    });
  }

  function modal(html, cls = '') {
    closeModal();
    const m = document.createElement('div');
    m.className = 'modal ' + cls; m.setAttribute('role', 'dialog'); m.setAttribute('aria-modal', 'true');
    m.innerHTML = `<div class="modal-box">${html}</div>`;
    document.body.appendChild(m);
    m.addEventListener('click', (e) => { if (e.target === m && !cls.includes('age')) closeModal(); });
    bindForms(m);
    const f = $('input, button', m); if (f) f.focus();
    return m;
  }
  function closeModal() { $$('.modal').forEach((m) => m.remove()); }

  // ------------------------------------------------------------- oldalak
  const crumbs = (items) => `<ol class="crumbs" aria-label="Morzsamenü"><li><a href="#/">Kezdőlap</a></li>${items.map(([l, h]) => h ? `<li><a href="#${h}">${esc(l)}</a></li>` : `<li aria-current="page">${esc(l)}</li>`).join('')}</ol>`;

  function pageHome() {
    const s = S.site;
    const slides = s.slides.map((sl, i) => `<div class="hero-slide${i === 0 ? ' is-active' : ''}" aria-hidden="${i !== 0}">
        <img src="${sl.img}" alt="" ${i === 0 ? 'fetchpriority="high"' : 'loading="lazy"'}>
        <div class="hero-caption"><div class="wrap"><div>
          <img class="hero-stars" src="${IMG.stars}" alt="">
          ${i === 0 ? '<h1' : '<h2'} class="hero-title">${esc(sl.title)}${i === 0 ? '</h1>' : '</h2>'}
          <span class="bar"></span>
          <p class="hero-text">${esc(sl.text)}</p>
          <a class="btn" href="${sl.href}">${esc(sl.button)}</a>
        </div></div></div>
      </div>`).join('');
    const tiles = s.tiles.map((t) => `<a class="tile" href="${t.href}">${t.img ? `<img src="${t.img}" alt="" loading="lazy">` : ''}<span>${esc(t.title)}</span></a>`).join('');
    const [b1, b2] = s.blocks;
    const latest = S.articles.filter((a) => a.lang === 'hu').slice(0, 3);
    return `
    <section class="hero" aria-roledescription="körhinta" aria-label="Kiemelt ajánlatok">
      <div class="hero-track">${slides}</div>
      <button class="hero-arrow prev" type="button" aria-label="Előző dia">${ICON.left}</button>
      <button class="hero-arrow next" type="button" aria-label="Következő dia">${ICON.right}</button>
      <div class="hero-dots">${s.slides.map((sl, i) => `<button type="button" aria-label="${i + 1}. dia: ${esc(sl.title)}" aria-current="${i === 0}"></button>`).join('')}</div>
    </section>

    <section class="intro iu-section"><div class="iu-row iu-row-vertical-center">
      <div class="iu-column iu-column-1-2"><div class="tiles">${tiles}</div></div>
      <div class="iu-column iu-column-1-2 intro-copy sec-dark" style="background:none">
        <img class="word" src="${IMG.logoWord}" alt="Slugshop">
        <h2>${esc(s.intro.title)}</h2><span class="bar"></span>
        <p>${esc(s.intro.text)}</p>
      </div>
    </div></section>

    <section class="feature feature--light iu-section"><div class="iu-row iu-row-vertical-center">
      <div class="iu-column iu-column-1-2"><h2>${esc(b1.title)}</h2><p>${esc(b1.text)}</p><a class="btn" href="${b1.href}">${esc(b1.button)}</a></div>
      <div class="iu-column iu-column-1-2 feature-img">${b2.img ? `<img src="${b2.img}" alt="FX légfegyverek" loading="lazy">` : ''}</div>
    </div></section>
    <section class="feature feature--dark sec-dark iu-section"><div class="iu-row">
      <div class="iu-column iu-column-1-2"><h2>${esc(b2.title)}</h2><p>${esc(b2.text)}</p><a class="btn" href="${b2.href}">${esc(b2.button)}</a></div>
    </div></section>

    <section class="sec-dark iu-section home" style="padding-top:var(--space-8)"><div class="iu-row"><div class="iu-column iu-column-1-1">
      <h2 class="sec-title center">Új termékek</h2>
      <div class="carousel">
        <button class="carousel-btn prev" type="button" aria-label="Előző termékek">${ICON.left}</button>
        <ul class="products carousel-track">${s.newProducts.filter((id) => P[id]).map(productCard).join('')}</ul>
        <button class="carousel-btn next" type="button" aria-label="Következő termékek">${ICON.right}</button>
      </div>
    </div></div></section>

    <section class="contact-band" id="kapcsolat-urlap"><div class="wrap" style="max-width:860px">
      <h2>Vegye fel velünk a kapcsolatot!</h2>${contactForm(true)}
    </div></section>

    <section class="articles-band sec-dark"><div class="iu-row iu-row-vertical-center" style="margin-bottom:var(--space-6)">
      <div class="iu-column iu-column-1-2"><h2 class="sec-title">Cikkeink</h2><p class="lead">Érdekességek, újdonságok, innováció egy helyen</p></div>
      <div class="iu-column iu-column-1-2 iu-column-align-right"><a class="btn" href="#/cikkeink">Cikkeink</a></div>
    </div><div class="iu-row"><div class="iu-column iu-column-1-1"><div class="cards">${latest.map(articleCard).join('')}</div></div></div></section>`;
  }

  function articleCard(a) {
    return `<a class="card" href="#${a.path}">
      <span class="card-img">${a.img ? `<img src="${a.img}" alt="" loading="lazy">` : ''}</span>
      <span class="card-body"><span class="card-meta">${a.lang === 'hu' ? 'Cikkek' : 'Articles'}</span><h3>${esc(a.title)}</h3><p>${esc(a.excerpt)}</p></span>
    </a>`;
  }

  function sidebarTree(current) {
    const open = new Set(catChain(current).map((c) => c.path));
    const node = (path) => {
      const c = C[path]; if (!c) return '';
      const kids = c.children.length ? `<ul ${open.has(path) ? '' : 'hidden'}>${c.children.map(node).join('')}</ul>` : '';
      return `<li><div class="row"><a href="#${path}" ${path === current ? 'aria-current="page"' : ''}>${esc(c.menu)}</a>${c.children.length ? `<button class="tog" type="button" aria-expanded="${open.has(path)}" aria-label="${esc(c.menu)} alkategóriái">${ICON.down}</button>` : ''}</div>${kids}</li>`;
    };
    return `<ul class="cat-tree">${S.tree.map(node).join('')}</ul>`;
  }

  let listState = { sort: 'default', stock: false, limit: 24 };
  function pageCategory(path) {
    const c = C[path];
    listState = { sort: 'default', stock: false, limit: 24 };
    const chain = catChain(path);
    const subs = c.children.map((ch) => `<a class="subcat" href="#${ch}"><span class="img">${C[ch].img ? `<img src="${C[ch].img}" alt="" loading="lazy">` : `<span class="noimg">${ICON.box}</span>`}</span><h3>${esc(C[ch].menu)}</h3></a>`).join('');
    const own = c.products.length;
    return `<div class="wrap page">
      ${crumbs(chain.map((x, i) => [x.menu, i < chain.length - 1 ? x.path : null]))}
      <div class="shop-layout">
        <aside class="sidebar" id="sidebar" aria-label="Kategóriák"><h2>Kategóriák</h2>${sidebarTree(path)}</aside>
        <div>
          <h1 class="cat-title">${esc(c.name)}</h1>
          ${c.desc ? `<div class="cat-desc prose">${c.desc}</div>` : ''}
          <button class="btn btn--sm filter-btn" type="button" data-toggle-sidebar style="margin-bottom:var(--space-4)">Kategóriák</button>
          ${subs ? `<div class="subcats">${subs}</div>` : ''}
          ${own ? `<div class="toolbar">
              <span class="count" id="count"></span>
              <label class="check"><input type="checkbox" id="only-stock"> Csak raktáron lévő</label>
              <label><span class="sr-only">Rendezés</span><select id="sort">
                <option value="default">Alapértelmezett sorrend</option><option value="asc">Ár szerint növekvő</option>
                <option value="desc">Ár szerint csökkenő</option><option value="name">Név szerint</option></select></label>
            </div>
            <ul class="products" id="grid"></ul><div class="more-wrap"><button class="btn" type="button" id="more" hidden>Továbbiak betöltése</button></div>`
          : (subs ? '' : '<p class="empty">Ebben a kategóriában jelenleg nincs termék.</p>')}
        </div>
      </div>
    </div>`;
  }
  function renderList(path) {
    const grid = $('#grid'); if (!grid) return;
    let ids = C[path].products.slice();
    if (listState.stock) ids = ids.filter((id) => P[id].stock === 'in');
    const pr = (id) => (P[id].price == null ? Infinity : P[id].price);
    if (listState.sort === 'asc') ids.sort((a, b) => pr(a) - pr(b));
    if (listState.sort === 'desc') ids.sort((a, b) => pr(b) - pr(a));
    if (listState.sort === 'name') ids.sort((a, b) => P[a].name.localeCompare(P[b].name, 'hu'));
    grid.innerHTML = ids.slice(0, listState.limit).map(productCard).join('') || '<li class="empty" style="grid-column:1/-1">Nincs raktáron lévő termék ebben a kategóriában.</li>';
    $('#count').textContent = `Találatok: ${Math.min(ids.length, listState.limit)} / ${ids.length}`;
    $('#more').hidden = ids.length <= listState.limit;
  }

  function pageProduct(id) {
    const p = P[id];
    const cat = C[p.cats[0]];
    const chain = cat ? catChain(cat.path) : [];
    const imgs = [p.img].concat(p.gallery).filter(Boolean);
    const inStock = p.stock === 'in';
    const related = cat ? cat.products.filter((x) => x !== id).slice(0, 4) : [];
    return `<div class="wrap page">
      ${crumbs(chain.map((x) => [x.menu, x.path]).concat([[p.name, null]]))}
      <div class="product-single product">
        <div class="woocommerce-product-gallery">
          <div class="woocommerce-product-gallery__image">${imgs[0] ? `<img id="main-img" src="${imgs[0]}" alt="${esc(p.name)}">` : ICON.box}</div>
          ${imgs.length > 1 ? `<div class="thumbs">${imgs.map((src, i) => `<button type="button" data-thumb="${src}" aria-current="${i === 0}" aria-label="${i + 1}. kép"><img src="${src}" alt="" loading="lazy"></button>`).join('')}</div>` : ''}
        </div>
        <div class="summary">
          <h1 class="product_title">${esc(p.name)}</h1>
          <span class="price">${ft(p.price)}</span>
          <div class="stock-row"><span class="stock ${inStock ? 'in-stock' : 'out-of-stock'}">${esc(p.stockText)}</span>${p.sku ? `<span>Cikkszám: ${esc(p.sku)}</span>` : ''}</div>
          ${p.short ? `<div class="woocommerce-product-details__short-description prose">${p.short}</div>` : ''}
          ${inStock ? `<form class="cart-form" data-cart-form="${id}">
              <div class="quantity"><button type="button" data-q="-1" aria-label="Kevesebb">−</button><input id="qty" type="number" min="1" max="99" value="1" aria-label="Mennyiség"><button type="button" data-q="1" aria-label="Több">+</button></div>
              <button class="button single_add_to_cart_button" type="submit">${ICON.cart.replace('<svg', '<svg class="icon"')}Kosárba</button>
              <button class="ask-btn" type="button" data-ask="${id}" aria-label="Tegye fel kérdését a termékről">${ICON.mail}</button>
            </form>`
          : `<div class="cart-form"><button class="button" type="button" data-notify="${id}">Értesítést kérek!</button><button class="ask-btn" type="button" data-ask="${id}" aria-label="Tegye fel kérdését a termékről">${ICON.mail}</button></div>`}
          ${cat ? `<a class="back-link" href="#${cat.path}"><b>Vissza:</b> ${esc(cat.menu)}</a>` : ''}
        </div>
      </div>
      <div class="wc-tabs"><span class="tab">Leírás</span><div class="panel"><div class="prose">${p.desc || p.short || '<p>A termékhez nincs részletes leírás.</p>'}</div></div></div>
      ${related.length ? `<section class="related"><h2>Ebben a kategóriában még</h2><ul class="products">${related.map(productCard).join('')}</ul></section>` : ''}
    </div>`;
  }

  function pageCart() {
    if (!cart.length) {
      return `<div class="wrap page">${crumbs([['Kosár', null]])}<h1 class="page-title">Kosár</h1><div class="empty"><p>A kosara még üres.</p><a class="btn" href="#/zan-slugok">Vissza a boltba</a></div></div>`;
    }
    return `<div class="wrap page">${crumbs([['Kosár', null]])}<h1 class="page-title">Kosár</h1>
      <div class="cart-layout">
        <table class="shop_table cart"><thead><tr><th></th><th>Termék</th><th class="product-price">Ár</th><th>Mennyiség</th><th>Részösszeg</th><th></th></tr></thead><tbody>
        ${cart.map((l) => { const p = P[l.id]; return `<tr>
          <td class="product-thumbnail">${p.img ? `<img src="${p.img}" alt="">` : ''}</td>
          <td class="product-name"><a href="#${p.path}">${esc(p.name)}</a></td>
          <td class="product-price">${ft(p.price)}</td>
          <td class="product-quantity"><div class="quantity"><button type="button" data-cq="${l.id}" data-d="-1" aria-label="Kevesebb">−</button><input type="number" min="1" max="99" value="${l.qty}" data-cqi="${l.id}" aria-label="Mennyiség"><button type="button" data-cq="${l.id}" data-d="1" aria-label="Több">+</button></div></td>
          <td class="product-subtotal"><b>${ft(p.price * l.qty)}</b></td>
          <td class="product-remove"><button class="remove" type="button" data-remove="${l.id}" aria-label="Törlés: ${esc(p.name)}">×</button></td></tr>`; }).join('')}
        </tbody></table>
        <div class="cart_totals"><h2>Kosár összesen</h2>
          <div class="totals-row"><span>Részösszeg</span><b>${ft(cartTotal())}</b></div>
          <div class="totals-row"><span>Szállítás<small>A pénztárban választható</small></span><span></span></div>
          <div class="totals-row total"><span>Összesen</span><span>${ft(cartTotal())}</span></div>
          <a class="btn btn--primary" href="#/penztar">Tovább a pénztárhoz</a>
        </div>
      </div></div>`;
  }

  function pageCheckout() {
    if (!cart.length) return pageCart();
    const k = S.pages.kapcsolat;
    return `<div class="wrap page">${crumbs([['Kosár', '/kosar'], ['Pénztár', null]])}<h1 class="page-title">Pénztár</h1>
      <form class="checkout-layout" data-form="checkout" novalidate>
        <div>
          <h3>Számlázási adatok</h3>
          <div class="form-grid">
            <div class="field"><label for="b-last">Vezetéknév *</label><input id="b-last" required autocomplete="family-name"></div>
            <div class="field"><label for="b-first">Keresztnév *</label><input id="b-first" required autocomplete="given-name"></div>
            <div class="field"><label for="b-email">E-mail cím *</label><input id="b-email" type="email" required autocomplete="email"></div>
            <div class="field"><label for="b-tel">Telefon *</label><input id="b-tel" type="tel" required data-phone autocomplete="tel"></div>
            <div class="field"><label for="b-company">Cégnév (nem kötelező)</label><input id="b-company" autocomplete="organization"></div>
            <div class="field"><label for="b-tax">Adószám (cégnek)</label><input id="b-tax"></div>
            <div class="field"><label for="b-zip">Irányítószám *</label><input id="b-zip" required inputmode="numeric" maxlength="4" autocomplete="postal-code"></div>
            <div class="field"><label for="b-city">Település *</label><input id="b-city" required autocomplete="address-level2"></div>
          </div>
          <div class="field" style="margin-top:var(--space-4)"><label for="b-street">Utca, házszám *</label><input id="b-street" required autocomplete="street-address"></div>
          <h3>Szállítási mód</h3>
          <ul class="wc-methods">
            <li><label><input type="radio" name="ship" value="futar" checked><span><b>Házhozszállítás futárszolgálattal</b><small>A szállítási díjat a mostani webshop díjtáblája szerint állítjuk be.</small></span></label></li>
            <li><label><input type="radio" name="ship" value="szemelyes"><span><b>Személyes átvétel</b><small>${esc(k.address)} · ${k.hours.map((h) => esc(h.join(': '))).join(' · ')}</small></span></label></li>
          </ul>
          <h3>Fizetési mód</h3>
          <ul class="wc-methods">
            <li><label><input type="radio" name="pay" value="viva" checked><span><b>Bankkártyás fizetés (Viva Wallet)</b><small>A fizetés a Viva Wallet biztonságos oldalán történik.</small></span></label></li>
            <li><label><input type="radio" name="pay" value="utalas"><span><b>Előre utalás</b><small>A csomagot a jóváírás után adjuk fel.</small></span></label></li>
          </ul>
          <div class="field" style="margin-top:var(--space-5)"><label for="b-note">Megjegyzés a rendeléshez</label><textarea id="b-note" style="min-height:90px"></textarea></div>
        </div>
        <aside class="order-review"><h3>Rendelése</h3>
          ${cart.map((l) => `<div class="item"><span>${esc(P[l.id].name)} × ${l.qty}</span><b>${ft(P[l.id].price * l.qty)}</b></div>`).join('')}
          <div class="totals-row"><span>Részösszeg</span><b>${ft(cartTotal())}</b></div>
          <div class="totals-row"><span>Szállítás<small>a választott mód szerint</small></span><span></span></div>
          <div class="totals-row total"><span>Összesen</span><span>${ft(cartTotal())}</span></div>
          <label class="check" style="margin:var(--space-4) 0"><input type="checkbox" required> <span>Elolvastam és elfogadom az <a href="#/aszf">ÁSZF</a>-et és az <a href="#/adatvedelmi-nyilatkozat">adatvédelmi nyilatkozatot</a>.</span></label>
          <button class="btn btn--primary" type="submit" style="width:100%">Megrendelés elküldése</button>
          <p style="font-size:var(--fs-xs);color:var(--c-muted);margin:var(--space-3) 0 0">A megrendelés fizetési kötelezettséggel jár.</p>
        </aside>
      </form></div>`;
  }
  function placeOrder() {
    const no = 'SL-' + String(Date.now()).slice(-6);
    cart = []; saveCart();
    location.hash = '#/koszonjuk/' + no;
  }
  function pageThanks(no) {
    return `<div class="wrap page order-done"><h1 class="page-title">Köszönjük a rendelését!</h1>
      <p>A rendelés száma:</p><span class="no">${esc(no)}</span>
      <p>A visszaigazolást e-mailben küldjük. (Előnézet: valódi rendelés nem jött létre.)</p>
      <a class="btn" href="#/">Vissza a kezdőlapra</a></div>`;
  }

  function pageContent(name) {
    const pg = S.pages[name];
    return `<div class="wrap page">${crumbs([[pg.title, null]])}<h1 class="page-title">${esc(pg.title)}</h1><div class="prose">${pg.html}</div></div>`;
  }

  function pageContact() {
    const k = S.pages.kapcsolat;
    return `<div class="wrap page">${crumbs([['Kapcsolat', null]])}<h1 class="page-title">Kapcsolat</h1>
      <div class="iu-row" style="width:100%">
        <div class="iu-column iu-column-1-3">
          <div class="contact-info">
            <a href="${esc(k.map)}" target="_blank" rel="noopener">${esc(k.address)}</a>
            <a href="${S.site.phoneHref}">${esc(k.phone)}</a>
            <a href="mailto:${S.site.email}">${esc(S.site.email)}</a>
          </div>
          <table class="hours"><caption class="sr-only">Nyitvatartás</caption><tbody>${k.hours.map((h) => `<tr><td>${esc(h[0])}</td><td><b>${esc(h[1])}</b></td></tr>`).join('')}</tbody></table>
          <a class="btn" href="${esc(k.map)}" target="_blank" rel="noopener">Útvonaltervezés</a>
        </div>
        <div class="iu-column iu-column-2-3">${contactForm(false)}</div>
      </div></div>`;
  }

  function pageLegal(name) {
    const titles = { aszf: 'Általános szerződési feltételek', impresszum: 'Impresszum', 'adatvedelmi-nyilatkozat': 'Adatvédelmi nyilatkozat' };
    const co = S.site.company;
    let body = '';
    if (name === 'impresszum') {
      body = `<table><tbody>
        <tr><th>Cégnév</th><td>${esc(co.name)}</td></tr><tr><th>Székhely</th><td>${esc(co.seat)}</td></tr>
        <tr><th>Üzlet, személyes átvétel</th><td>${esc(S.pages.kapcsolat.address)}</td></tr>
        <tr><th>Adószám</th><td>${esc(co.tax)}</td></tr><tr><th>Cégjegyzékszám</th><td>${esc(co.reg)}</td></tr>
        <tr><th>Képviselők</th><td>${esc(co.reps)}</td></tr>
        <tr><th>Telefon</th><td>${esc(S.site.phone)}</td></tr><tr><th>E-mail</th><td>${esc(S.site.email)}</td></tr>
        <tr><th>Tárhelyszolgáltató</th><td>(élesítés előtt kitöltendő)</td></tr></tbody></table>
        <p class="note"><strong>Javítás:</strong> a régi oldalon ez az oldal üres volt. Az adatok a Fogyasztó Barát tanúsítványán szereplő cégadatok; a tárhelyszolgáltató adatait élesítés előtt pótoljuk.</p>`;
    } else {
      body = `<p class="note"><strong>${name === 'aszf' ? 'Az ÁSZF-et' : 'Az adatkezelési tájékoztatót'} a Fogyasztó Barát rendszere tölti be,</strong> mindig a hatályos szöveggel (a régi oldalon is így volt${name === 'aszf' ? '' : ', de ez az oldal üres maradt – az új oldalon ide is bekötjük'}). Az előnézetben ez nem töltődik be.</p>
        <p><a class="btn" href="https://fogyasztobarat.hu/tanusitvany-ellenorzes/?csi=MB0C642A" target="_blank" rel="noopener">Tanúsítvány megnyitása</a></p>`;
    }
    return `<div class="wrap page">${crumbs([[titles[name], null]])}<h1 class="page-title">${titles[name]}</h1><div class="prose">${body}</div></div>`;
  }

  function pageVideos() {
    return `<div class="wrap page">${crumbs([['Videók', null]])}<h1 class="page-title">Videók</h1>
      <div class="cards">${S.videos.map((v) => `<a class="card" href="#${v.path}"><span class="card-img">${v.img ? `<img src="${v.img}" alt="" loading="lazy">` : ''}</span><span class="card-body"><h3>${esc(v.title)}</h3><p>${esc(v.title)} videók</p><span class="card-meta">Bővebben …</span></span></a>`).join('')}</div></div>`;
  }
  function pageVideo(v) {
    return `<div class="wrap page">${crumbs([['Videók', '/videok'], [v.title, null]])}<h1 class="page-title">${esc(v.title)}</h1>
      <div class="video-grid">${v.youtube.map((id) => `<button class="yt" type="button" data-yt="${id}" aria-label="Videó lejátszása"><img src="https://i.ytimg.com/vi/${id}/hqdefault.jpg" alt="" loading="lazy" onerror="this.remove()"><span></span></button>`).join('')}</div>
      <div class="prose">${v.html}</div></div>`;
  }

  function pageBlog(which) {
    const groups = which === '/cikkeink' ? [['/cikkeink/cikkek', 'hu', 'Cikkek'], ['/cikkeink/articles', 'en', 'Articles']]
      : [[which, which.endsWith('articles') ? 'en' : 'hu', which.endsWith('articles') ? 'Articles' : 'Cikkek']];
    const title = which === '/cikkeink' ? 'Cikkeink' : groups[0][2];
    const intro = S.site.blogIntro[which.replace(/^\//, '')] || '';
    return `<div class="wrap page">${crumbs(which === '/cikkeink' ? [['Cikkeink', null]] : [['Cikkeink', '/cikkeink'], [title, null]])}
      <h1 class="page-title">${esc(title)}</h1>${intro ? `<div class="prose" style="margin-bottom:var(--space-6)">${intro}</div>` : ''}
      ${groups.map(([path, lang, label]) => `${which === '/cikkeink' ? `<h2 class="sec-title" style="margin-top:var(--space-6)"><a href="#${path}" style="color:inherit">${label}</a></h2>` : ''}
        <div class="cards">${S.articles.filter((a) => a.lang === lang).map(articleCard).join('')}</div>`).join('')}
    </div>`;
  }
  function pageArticle(a) {
    const blog = a.lang === 'hu' ? ['Cikkek', '/cikkeink/cikkek'] : ['Articles', '/cikkeink/articles'];
    return `<div class="wrap page">${crumbs([['Cikkeink', '/cikkeink'], blog, [a.title, null]])}
      <article class="prose" style="margin:0 auto"><h1 class="page-title">${esc(a.title)}</h1>
      ${a.img && !a.html.includes(a.img) ? `<img src="${a.img}" alt="" style="width:100%;max-height:520px;object-fit:cover">` : ''}${a.html}</article></div>`;
  }

  function pageAuth(kind) {
    if (kind === 'login') {
      return `<div class="wrap page" style="max-width:520px">${crumbs([['Belépés', null]])}<h1 class="page-title">Bejelentkezés</h1>
        <form class="form" data-form="login" novalidate>
          <div class="field"><label for="l-user">Felhasználónév</label><input id="l-user" required autocomplete="username"></div>
          <div class="field"><label for="l-pass">Jelszó</label><input id="l-pass" type="password" required autocomplete="current-password"></div>
          <label class="check"><input type="checkbox"> Emlékezzen rám</label>
          <div><button class="btn" type="submit">Bejelentkezés</button></div>
          <p style="font-size:var(--fs-small)"><a href="#/bejelentkezes">Elfelejtette a jelszavát?</a> · <a href="#/regisztracio">Nincs fiókja?</a></p>
        </form></div>`;
    }
    return `<div class="wrap page" style="max-width:640px">${crumbs([['Regisztráció', null]])}<h1 class="page-title">Regisztráció</h1>
      <form class="form" data-form="register" novalidate>
        <div class="field"><label for="r-name">Név *</label><input id="r-name" required autocomplete="name"></div>
        <div class="form-grid">
          <div class="field"><label for="r-user">Felhasználónév *</label><input id="r-user" required autocomplete="username"></div>
          <div class="field"><label for="r-email">E-mail cím *</label><input id="r-email" type="email" required autocomplete="email"></div>
          <div class="field"><label for="r-pass">Jelszó *</label><input id="r-pass" type="password" required autocomplete="new-password"></div>
          <div class="field"><label for="r-pass2">Jelszó megerősítése *</label><input id="r-pass2" type="password" required data-match="r-pass" autocomplete="new-password"></div>
        </div>
        <label class="check"><input type="checkbox" required> <span>Elfogadom az <a href="#/adatvedelmi-nyilatkozat">adatvédelmi nyilatkozatot</a></span></label>
        <div><button class="btn" type="submit">Regisztráció</button></div>
      </form></div>`;
  }

  function searchHits(q) {
    const t = norm(q).split(/\s+/).filter(Boolean);
    if (!t.length) return [];
    return Object.keys(P).filter((id) => { const h = norm(P[id].name + ' ' + P[id].sku); return t.every((w) => h.includes(w)); });
  }
  function highlight(text, q) {
    const words = norm(q).split(/\s+/).filter(Boolean);
    const n = norm(text); let out = ''; let i = 0;
    while (i < text.length) {
      const w = words.find((x) => n.startsWith(x, i));
      if (w) { out += `<mark>${esc(text.slice(i, i + w.length))}</mark>`; i += w.length; } else { out += esc(text[i]); i += 1; }
    }
    return out;
  }
  function pageSearch(q) {
    const ids = searchHits(q);
    const arts = S.articles.filter((a) => norm(a.title + ' ' + a.excerpt).includes(norm(q)));
    return `<div class="wrap page">${crumbs([['Keresés', null]])}<h1 class="page-title">Keresés: „${esc(q)}”</h1>
      <p>${ids.length} termék${arts.length ? ` és ${arts.length} cikk` : ''}.</p>
      ${ids.length ? `<ul class="products" style="grid-template-columns:repeat(auto-fill,minmax(220px,1fr))">${ids.slice(0, 60).map(productCard).join('')}</ul>` : '<div class="empty">Nincs találat. Próbáld kevesebb szóval vagy cikkszámmal.</div>'}
      ${arts.length ? `<h2 class="sec-title" style="margin-top:var(--space-7)">Cikkek</h2><div class="cards">${arts.map(articleCard).join('')}</div>` : ''}</div>`;
  }

  function pageGuide() {
    const fixes = S.site.fixes.map(([a, b]) => `<tr><td>${esc(a)}</td><td>${esc(b)}</td></tr>`).join('');
    return `<div class="wrap page">${crumbs([['Előnézet', null]])}<h1 class="page-title">A megújuló slugshop.hu előnézete</h1>
      <div class="prose" style="max-width:none">
        <p style="font-size:var(--fs-lead)">Ez a slugshop.hu új, WordPress-alapú változatának kattintható előnézete. <strong>Nem kapott új designt:</strong> ugyanaz a kinézet, ugyanazok a szövegek, képek, kategóriák, termékek és árak, mint a mostani oldalon – csak egy ráncfelvarráson esett át, és alatta az új rendszer fut.</p>
        <h2>Mi változott?</h2>
        <ul class="guide-list">
          <li><b>Egysoros menü, ragadós fejléc</b>A menü nem törik két sorba, görgetéskor a fejléc fent marad, mobilon oldalsó menü.</li>
          <li><b>Mobilnézet</b>A csempék, a nyitókép szövege és a terméklista telefonon is rendben van.</li>
          <li><b>Termékkártyák</b>Egyforma magasak, látszik, mi fogyott el; elfogyottnál „Értesítést kérek!”.</li>
          <li><b>Cikkek a nyitóoldalon</b>A „Cikkeink” blokk eddig üres volt, most a legfrissebb cikkek látszanak.</li>
          <li><b>Impresszum, adatvédelem</b>Ezek az oldalak eddig üresek voltak; az új oldalon kitöltjük őket.</li>
          <li><b>Gyorsabb betöltés</b>WebP képek, kevesebb szkript (nincs jQuery és körhinta-bővítmény).</li>
          <li><b>Akadálymentesség</b>Billentyűzettel kezelhető, jobb kontraszt, alt-szövegek, „Ugrás a tartalomra”.</li>
          <li><b>Kosár és pénztár</b>WooCommerce-alapú, áttekinthető pénztár Viva Wallet bankkártyás fizetéssel.</li>
        </ul>
        <h2>Ami a háttérben jön</h2>
        <ul>
          <li><strong>WordPress + WooCommerce</strong> a Joomla és a VirtueMart helyett, a mi rendszerünkön (Infinite Unity alap téma).</li>
          <li><strong>JUTA-Soft kapcsolat</strong>, mint a Mandalánál: a termékek, árak és a készlet a JUTA-ból frissülnek, a webshop rendelései a JUTA-ba mennek.</li>
          <li><strong>Az URL-ek maradnak</strong> (pl. /zan-slugok/…), így a Google-helyezések nem vesznek el; ami változik, azt átirányítjuk.</li>
          <li><strong>Fogyasztó Barát</strong>: az ÁSZF és az adatkezelési tájékoztató továbbra is onnan töltődik be, mindig hatályos szöveggel.</li>
        </ul>
        <h2>Javított elírások</h2>
        <p>A szövegeket nem írtuk át, csak ezeket a pontos javításokat végeztük el:</p>
        <div class="table-scroll"><table class="fix-table"><thead><tr><th>Régi</th><th>Új</th></tr></thead><tbody>${fixes}</tbody></table></div>
        <h2>Amit az előnézet nem tud</h2>
        <p>Az űrlapok, a belépés és a rendelés nem küld el semmit, fizetés nincs. A YouTube-videókhoz internet kell. Az árak és a készlet a letöltés pillanatában érvényes állapotot mutatják.</p>
        <p><strong>Ha valamit másképp szeretnél, írd meg</strong> – oldalanként, akár képernyőképpel.</p>
      </div></div>`;
  }

  function page404() {
    return `<div class="wrap page"><h1 class="page-title">Az oldal nem található</h1><p>Lehet, hogy a cím elírás, vagy az oldal megszűnt.</p><a class="btn" href="#/">Vissza a kezdőlapra</a></div>`;
  }

  // ------------------------------------------------------------- útvonalválasztó
  function resolve(path) {
    if (path === '/' || path === '') return { html: pageHome(), title: 'Légfegyverek és ZAN lövedékek', home: true };
    if (C[path]) return { html: pageCategory(path), title: C[path].name, cat: path };
    if (byPath[path]) return { html: pageProduct(byPath[path]), title: P[byPath[path]].name, product: byPath[path] };
    const m = path.match(/^\/kereses\/(.+)$/); if (m) return { html: pageSearch(decodeURIComponent(m[1])), title: 'Keresés' };
    const t = path.match(/^\/koszonjuk\/(.+)$/); if (t) return { html: pageThanks(t[1]), title: 'Köszönjük' };
    const v = S.videos.find((x) => x.path === path); if (v) return { html: pageVideo(v), title: v.title };
    const a = S.articles.find((x) => x.path === path); if (a) return { html: pageArticle(a), title: a.title };
    const simple = {
      '/kosar': [pageCart, 'Kosár'], '/penztar': [pageCheckout, 'Pénztár'], '/kapcsolat': [pageContact, 'Kapcsolat'],
      '/videok': [pageVideos, 'Videók'], '/cikkeink': [() => pageBlog('/cikkeink'), 'Cikkeink'],
      '/cikkeink/cikkek': [() => pageBlog('/cikkeink/cikkek'), 'Cikkek'], '/cikkeink/articles': [() => pageBlog('/cikkeink/articles'), 'Articles'],
      '/bejelentkezes': [() => pageAuth('login'), 'Bejelentkezés'], '/regisztracio': [() => pageAuth('register'), 'Regisztráció'],
      '/elonezet': [pageGuide, 'Előnézet'], '/szerviz': [() => pageContent('szerviz'), 'Szerviz'],
      '/rolunk': [() => pageContent('rolunk'), 'Rólunk'], '/letoltesek': [() => pageContent('letoltesek'), 'Letöltések'],
      '/aszf': [() => pageLegal('aszf'), 'ÁSZF'], '/impresszum': [() => pageLegal('impresszum'), 'Impresszum'],
      '/adatvedelmi-nyilatkozat': [() => pageLegal('adatvedelmi-nyilatkozat'), 'Adatvédelmi nyilatkozat']
    };
    if (simple[path]) return { html: simple[path][0](), title: simple[path][1] };
    return { html: page404(), title: 'Nem található' };
  }

  let heroTimer = null;
  function render() {
    const raw = location.hash.replace(/^#/, '');
    if (raw === 'main') return; // „Ugrás a tartalomra”
    const path = decodeURIComponent(raw.split('?')[0]) || '/';
    const r = resolve(path);
    clearInterval(heroTimer);
    const main = $('#main');
    main.innerHTML = r.html;
    document.title = r.title + ' – Slugshop';
    closeAll();
    const top = path.split('/').slice(0, 2).join('/');
    $$('[data-nav]').forEach((a) => {
      const on = a.dataset.nav === path || (r.cat && catChain(r.cat).some((c) => c.path === a.dataset.nav)) ||
        (r.product && P[r.product].cats.some((cp) => catChain(cp).some((c) => c.path === a.dataset.nav))) || (a.dataset.nav === top && top !== '/');
      if (on) a.setAttribute('aria-current', 'page'); else a.removeAttribute('aria-current');
    });
    window.scrollTo(0, 0);
    bindForms(main);
    if (r.home) initHome();
    if (r.cat && $('#grid')) initList(r.cat);
    if (r.product) initProduct(r.product);
    initPhone(main);
  }

  function initHome() {
    const slides = $$('.hero-slide'); const dots = $$('.hero-dots button');
    let cur = 0;
    const go = (n) => {
      cur = (n + slides.length) % slides.length;
      slides.forEach((s, i) => { s.classList.toggle('is-active', i === cur); s.setAttribute('aria-hidden', String(i !== cur)); });
      dots.forEach((d, i) => d.setAttribute('aria-current', String(i === cur)));
    };
    const play = () => { clearInterval(heroTimer); if (!matchMedia('(prefers-reduced-motion: reduce)').matches) heroTimer = setInterval(() => go(cur + 1), 6500); };
    dots.forEach((d, i) => d.addEventListener('click', () => { go(i); play(); }));
    $('.hero-arrow.prev').addEventListener('click', () => { go(cur - 1); play(); });
    $('.hero-arrow.next').addEventListener('click', () => { go(cur + 1); play(); });
    $('.hero').addEventListener('mouseenter', () => clearInterval(heroTimer));
    $('.hero').addEventListener('mouseleave', play);
    play();
    const track = $('.carousel-track');
    $('.carousel-btn.prev').addEventListener('click', () => track.scrollBy({ left: -track.clientWidth, behavior: 'smooth' }));
    $('.carousel-btn.next').addEventListener('click', () => track.scrollBy({ left: track.clientWidth, behavior: 'smooth' }));
  }

  function initList(path) {
    renderList(path);
    $('#sort').addEventListener('change', (e) => { listState.sort = e.target.value; renderList(path); });
    $('#only-stock').addEventListener('change', (e) => { listState.stock = e.target.checked; listState.limit = 24; renderList(path); });
    $('#more').addEventListener('click', () => { listState.limit += 24; renderList(path); });
  }

  function initProduct(id) {
    const f = $('[data-cart-form]');
    if (f) {
      const q = $('#qty');
      $$('[data-q]', f).forEach((b) => b.addEventListener('click', () => { q.value = Math.max(1, Math.min(99, (+q.value || 1) + +b.dataset.q)); }));
      f.addEventListener('submit', (e) => { e.preventDefault(); addToCart(id, Math.max(1, +q.value || 1)); });
    }
    $$('[data-thumb]').forEach((b) => b.addEventListener('click', () => {
      $('#main-img').src = b.dataset.thumb;
      $$('[data-thumb]').forEach((x) => x.setAttribute('aria-current', String(x === b)));
    }));
  }

  function initPhone(root) {
    $$('[data-phone]', root).forEach((el) => {
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

  // ------------------------------------------------------------- rétegek
  function closeAll() {
    ['#drawer', '#search'].forEach((s) => { const el = $(s); el.classList.remove('is-open'); el.setAttribute('aria-hidden', 'true'); });
    $('.scrim').classList.remove('is-open');
    const ap = $('.a11y-panel'); if (ap) ap.remove();
    document.documentElement.style.overflow = '';
  }
  function openLayer(sel) {
    closeAll();
    const el = $(sel); el.classList.add('is-open'); el.setAttribute('aria-hidden', 'false');
    $('.scrim').classList.add('is-open');
    document.documentElement.style.overflow = 'hidden';
    const f = $('input, a, button', el); if (f) setTimeout(() => f.focus(), 50);
  }

  function renderSearch(q) {
    const box = $('#search-results'); const hint = $('#search-hint');
    if (!q.trim()) { box.innerHTML = ''; hint.textContent = 'Írd be a termék nevét, a kalibert vagy a cikkszámot.'; return; }
    const ids = searchHits(q);
    hint.textContent = ids.length ? `${ids.length} találat – Enter: az összes megjelenítése` : 'Nincs találat.';
    box.innerHTML = ids.slice(0, 12).map((id) => { const p = P[id]; return `<a class="search-hit" href="#${p.path}">${p.img ? `<img src="${p.img}" alt="">` : '<span></span>'}<span><b>${highlight(p.name, q)}</b><small>${esc(p.stockText)}</small></span><span class="p">${ft(p.price)}</span></a>`; }).join('');
  }

  function ageGate() {
    if (store.get('slugshop_age', false)) return;
    const a = S.site.age || {};
    const m = modal(`<h2>${esc(a.title || 'Elmúltál 18 éves?')}</h2><p class="sub">${esc(a.sub || '')}</p>
      <div class="age-actions"><button class="btn btn--primary" type="button" data-age="yes">Igen</button><button class="btn" type="button" data-age="no">Nem</button></div>
      ${a.noteTitle ? `<div class="age-note"><strong>${esc(a.noteTitle)}</strong>${a.note.map((n) => `<p>${esc(n)}</p>`).join('')}</div>` : ''}`, 'age');
    m.addEventListener('click', (e) => {
      const b = e.target.closest('[data-age]'); if (!b) return;
      if (b.dataset.age === 'yes') { store.set('slugshop_age', true); closeModal(); cookieBar(); }
      else $('.modal-box', m).innerHTML = '<h2>Sajnáljuk</h2><p class="sub">Weboldalunkat csak 18 éven felüliek látogathatják.</p>';
    });
  }
  function cookieBar() {
    if (store.get('slugshop_cookie', null) || $('.cookie')) return;
    const c = S.site.cookie;
    const el = document.createElement('div');
    el.className = 'cookie'; el.setAttribute('role', 'dialog'); el.setAttribute('aria-label', 'Sütik');
    el.innerHTML = `<p><b>${esc(c.title)}</b></p><p>${esc(c.text)} <a href="#/adatvedelmi-nyilatkozat">Adatvédelmi irányelvek</a></p>
      <div class="cookie-opts" hidden>
        <label class="check"><input type="checkbox" checked disabled> Kötelező</label>
        <label class="check"><input type="checkbox" checked data-ck="pref"> Ajánlott</label>
        <label class="check"><input type="checkbox" data-ck="stat"> Teljesítmény mérése</label>
        <label class="check"><input type="checkbox" data-ck="mkt"> Saját marketinges tevékenység</label>
      </div>
      <div class="cookie-actions"><button class="btn" type="button" data-ck-act="deny">Elutasítom</button><button class="btn" type="button" data-ck-act="settings">Beállítások</button><button class="btn btn--primary" type="button" data-ck-act="all">Összes elfogadása</button></div>`;
    document.body.appendChild(el);
    el.addEventListener('click', (e) => {
      const b = e.target.closest('[data-ck-act]'); if (!b) return;
      const act = b.dataset.ckAct;
      if (act === 'settings') { const o = $('.cookie-opts', el); if (o.hidden) { o.hidden = false; b.textContent = 'Engedélyezem'; return; } }
      const pick = act === 'all' ? { pref: true, stat: true, mkt: true } : act === 'deny' ? {} : Object.fromEntries($$('[data-ck]', el).map((i) => [i.dataset.ck, i.checked]));
      store.set('slugshop_cookie', pick); el.remove();
    });
  }

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
    $('#site-header').outerHTML = header();
    $('#site-footer').outerHTML = footer();
    store.get('slugshop_a11y', []).forEach((c) => document.documentElement.classList.add(c));
    saveCart();

    document.addEventListener('click', (e) => {
      const t = e.target;
      const add = t.closest('[data-add]'); if (add) { addToCart(add.dataset.add, 1); return; }
      const nt = t.closest('[data-notify]');
      if (nt) { const p = P[nt.dataset.notify]; modal(`<button class="close-btn" type="button" data-modal-close aria-label="Bezárás" style="float:right">×</button><h2 style="font-size:1.5rem">Értesítést kérek!</h2><p class="sub">${esc(p.name)}</p><form class="form form-dark" data-form="notify" novalidate style="text-align:left"><div class="field"><label for="n-email">E-mail cím *</label><input id="n-email" type="email" required autocomplete="email"></div><div><button class="btn btn--primary" type="submit">Értesítést kérek!</button></div></form>`); return; }
      const ask = t.closest('[data-ask]');
      if (ask) { const p = P[ask.dataset.ask]; modal(`<button class="close-btn" type="button" data-modal-close aria-label="Bezárás" style="float:right">×</button><h2 style="font-size:1.5rem">Tegye fel kérdését</h2><p class="sub">${esc(p.name)}</p><form class="form form-dark" data-form="ask" novalidate style="text-align:left"><div class="field"><label for="a-name">Név *</label><input id="a-name" required></div><div class="field"><label for="a-email">E-mail cím *</label><input id="a-email" type="email" required></div><div class="field"><label for="a-q">Kérdés *</label><textarea id="a-q" required></textarea></div><div><button class="btn btn--primary" type="submit">Küldés</button></div></form>`); return; }
      if (t.closest('[data-modal-close]')) { closeModal(); return; }
      if (t.closest('[data-open-drawer]')) { openLayer('#drawer'); return; }
      if (t.closest('[data-open-search]')) { openLayer('#search'); return; }
      if (t.closest('[data-close]')) { closeAll(); return; }
      if (t.closest('[data-toggle-sidebar]')) { $('#sidebar').classList.toggle('is-open'); return; }
      const tog = t.closest('.cat-tree .tog');
      if (tog) { const ul = tog.closest('li').querySelector('ul'); const open = ul.hidden; ul.hidden = !open; tog.setAttribute('aria-expanded', String(open)); return; }
      const rm = t.closest('[data-remove]'); if (rm) { cart = cart.filter((l) => l.id !== rm.dataset.remove); saveCart(); render(); return; }
      const cq = t.closest('[data-cq]'); if (cq) { const l = cart.find((x) => x.id === cq.dataset.cq); l.qty = Math.max(1, Math.min(99, l.qty + +cq.dataset.d)); saveCart(); render(); return; }
      const yt = t.closest('[data-yt]'); if (yt) { yt.innerHTML = `<iframe src="https://www.youtube-nocookie.com/embed/${yt.dataset.yt}?autoplay=1" title="YouTube-videó" allow="autoplay; encrypted-media; picture-in-picture" allowfullscreen></iframe>`; return; }
      const ab = t.closest('[data-a11y]'); if (ab) { a11yPanel(ab); return; }
      if (t.closest('[data-skip]')) { e.preventDefault(); const m = $('#main'); m.setAttribute('tabindex', '-1'); m.focus(); return; }
      const link = t.closest('a[href^="#/"]');
      if (link && link.getAttribute('href') === location.hash) { e.preventDefault(); render(); }
    });
    document.addEventListener('change', (e) => {
      const i = e.target.closest('[data-cqi]'); if (i) { const l = cart.find((x) => x.id === i.dataset.cqi); l.qty = Math.max(1, Math.min(99, +i.value || 1)); saveCart(); render(); }
      if (e.target.id === 'cur' && e.target.value === 'EUR') { toast('Az euróárakat az új webshop a WooCommerce árfolyamából számolja; az előnézet forintban mutat.'); e.target.value = 'HUF'; }
    });
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') { closeAll(); if (!$('.modal.age')) closeModal(); }
      if (e.key === '/' && !/input|textarea|select/i.test(document.activeElement.tagName)) { e.preventDefault(); openLayer('#search'); }
    });
    $('#q').addEventListener('input', (e) => renderSearch(e.target.value));
    $('#search-form').addEventListener('submit', (e) => { e.preventDefault(); const q = $('#q').value.trim(); if (q) location.hash = '#/kereses/' + encodeURIComponent(q); });
    const hdr = $('.site-header');
    window.addEventListener('scroll', () => hdr.classList.toggle('is-scrolled', window.scrollY > 40), { passive: true });
    window.addEventListener('hashchange', render);
    render();
    ageGate();
    if (store.get('slugshop_age', false)) cookieBar();
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot); else boot();
})();
