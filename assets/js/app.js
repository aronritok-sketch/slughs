/* ==========================================================================
   SLUGSHOP — frontend prototípus logika
   Közös fejléc/lábléc, kosár, kereső, pénznem, űrlapok és oldalspecifikus
   modulok (data-page attribútum alapján a <body>-n).
   ========================================================================== */
(function () {
  "use strict";

  const S = window.SLUG;
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
  const esc = (s) => String(s == null ? "" : s).replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
  const norm = (s) => Array.from(String(s)).map((c) => c.normalize("NFD").replace(/[̀-ͯ]/g, "").toLowerCase().charAt(0) || c).join("");
  const slugify = (s) => norm(s).replace(/[^a-z0-9]+/g, "-").replace(/^-|-$/g, "");
  const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  /* Egyfájlos (letölthető) változatban minden oldal egy HTML-ben van,
     az oldalt a hash választja ki: #kategoria/kat-zan, #termek/p-zan-300-59gr … */
  const BUNDLE = !!window.SLUG_BUNDLE;
  const toBundle = (url) => { const m = String(url).match(/^([\w-]+)\.html(?:#(.*))?$/); return m ? (m[1] === "index" && !m[2] ? "index" : m[1] + (m[2] ? "/" + m[2] : "")) : null; };
  function subHash() {
    if (!BUNDLE) return location.hash;
    const h = location.hash.slice(1), i = h.indexOf("/");
    return i < 0 ? "" : "#" + h.slice(i + 1);
  }
  function go(url) { if (BUNDLE) location.hash = toBundle(url) || url; else location.href = url; }
  let hashHandler = null;
  const cleanups = [];
  function onDoc(ev, fn) { document.addEventListener(ev, fn); cleanups.push(() => document.removeEventListener(ev, fn)); }

  const store = {
    get(k, d) { try { const v = localStorage.getItem(k); return v == null ? d : JSON.parse(v); } catch (e) { return d; } },
    set(k, v) { try { localStorage.setItem(k, JSON.stringify(v)); } catch (e) { /* tárhely nem elérhető */ } }
  };

  /* ------------------------------------------------------------ ikonok */
  const ICONS = {
    search: '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
    cart: '<circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M2 3h3l2.7 12.4a2 2 0 0 0 2 1.6h8.2a2 2 0 0 0 2-1.5L22 8H6"/>',
    user: '<circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6"/>',
    menu: '<path d="M3 6h18M3 12h18M3 18h12"/>',
    close: '<path d="M6 6l12 12M18 6 6 18"/>',
    phone: '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8 9.8a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.5c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.7 2z"/>',
    mail: '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 6-10 7L2 6"/>',
    arrow: '<path d="M5 12h14M13 6l6 6-6 6"/>',
    chevron: '<path d="m6 9 6 6 6-6"/>',
    check: '<path d="M20 6 9 17l-5-5"/>',
    truck: '<path d="M1 4h14v12H1zM15 8h4l4 4v4h-8z"/><circle cx="5.5" cy="18.5" r="2"/><circle cx="18.5" cy="18.5" r="2"/>',
    shield: '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/>',
    wrench: '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.8-3.8a6 6 0 0 1-7.9 7.9l-6.9 6.9a2.1 2.1 0 0 1-3-3l6.9-6.9a6 6 0 0 1 7.9-7.9z"/>',
    card: '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20M6 15h4"/>',
    plus: '<path d="M12 5v14M5 12h14"/>',
    minus: '<path d="M5 12h14"/>',
    trash: '<path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/>',
    upload: '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12"/>',
    download: '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/>',
    play: '<path d="M6 4l14 8-14 8z"/>',
    target: '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="4"/><path d="M12 1v5M12 18v5M1 12h5M18 12h5"/>',
    fb: '<path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/>',
    ig: '<rect x="2" y="2" width="20" height="20" rx="5"/><circle cx="12" cy="12" r="4"/><path d="M17.5 6.5h.01"/>',
    yt: '<path d="M22.5 6.4a2.8 2.8 0 0 0-2-2C18.8 4 12 4 12 4s-6.8 0-8.5.4a2.8 2.8 0 0 0-2 2A29 29 0 0 0 1 12a29 29 0 0 0 .5 5.6 2.8 2.8 0 0 0 2 2c1.7.4 8.5.4 8.5.4s6.8 0 8.5-.4a2.8 2.8 0 0 0 2-2A29 29 0 0 0 23 12a29 29 0 0 0-.5-5.6z"/><path d="m9.8 15 5.7-3-5.7-3z"/>',
    pin: '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>',
    grid: '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/>',
    list: '<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>',
    filter: '<path d="M22 3H2l8 9.5V19l4 2v-8.5z"/>',
    bell: '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 0 1-3.4 0"/>',
    file: '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h5"/>',
    lock: '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
    up: '<path d="M12 19V5M5 12l7-7 7 7"/>',
    box: '<path d="M21 8 12 3 3 8v8l9 5 9-5z"/><path d="M3 8l9 5 9-5M12 13v8"/>',
    award: '<circle cx="12" cy="8" r="6"/><path d="M8.2 13.2 7 22l5-3 5 3-1.2-8.8"/>',
    gauge: '<path d="M12 14l4-4"/><path d="M3.3 17a10 10 0 1 1 17.4 0"/>',
    building: '<path d="M3 21h18M5 21V5l7-3 7 3v16M9 9h1M14 9h1M9 13h1M14 13h1M10 21v-4h4v4"/>',
    clock: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>'
  };
  const icon = (n, cls) => `<svg${cls ? ` class="${cls}"` : ""} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${ICONS[n] || ""}</svg>`;

  /* Termék-placeholder rajzok (120×120) — valódi fotó hiányában */
  const ART = {
    slug: '<path d="M8 60h104" stroke-dasharray="10 3 2 3" opacity=".4"/><path d="M22 46h46c14 0 24 6 30 11v6c-6 5-16 11-30 11H22l-4-4V50z"/><path d="M98 57l-9 1.5v3l9 1.5" stroke-dasharray="3 3"/><path d="M34 46v28M40 46v28" opacity=".45"/>',
    pellet: '<path d="M8 60h104" stroke-dasharray="10 3 2 3" opacity=".4"/><path d="M18 42l32 12h6c5-6 13-8 21-8 14 0 23 6 23 14s-9 14-23 14c-8 0-16-2-21-8h-6L18 78z"/><path d="M50 54v12M56 54v12" opacity=".45"/>',
    gauge: '<circle cx="60" cy="52" r="34"/><circle cx="60" cy="52" r="26" opacity=".45"/><path d="M60 52l17-13"/><circle cx="60" cy="52" r="3"/><path d="M37 68l4-3M34 52h5M37 36l4 3M60 26v5M83 36l-4 3M86 52h-5M83 68l-4-3"/><path d="M52 86h16v8H52zM56 94h8v14h-8z"/>',
    scope: '<path d="M26 54h58v12H26z"/><path d="M84 52l22-9v34l-22-9"/><path d="M26 56l-12-4v16l12-4"/><path d="M50 54V44h16v10M54 44v-5h8v5"/><path d="M40 66v8h8v-8M66 66v8h8v-8M32 74h52"/>',
    magazine: '<circle cx="60" cy="60" r="32"/><circle cx="60" cy="60" r="6"/><circle cx="79" cy="60" r="5"/><circle cx="73.4" cy="73.4" r="5"/><circle cx="60" cy="79" r="5"/><circle cx="46.6" cy="73.4" r="5"/><circle cx="41" cy="60" r="5"/><circle cx="46.6" cy="46.6" r="5"/><circle cx="60" cy="41" r="5"/><circle cx="73.4" cy="46.6" r="5"/><path d="M60 28v-8M52 20h16"/>',
    spring: '<path d="M6 60h8"/><path d="M14 60c4-18 8-18 12 0c4 18 8 18 12 0c4-18 8-18 12 0c4 18 8 18 12 0c4-18 8-18 12 0c4 18 8 18 12 0c4-18 8-18 12 0c4 18 8 18 12 0"/><path d="M110 60h6"/><path d="M14 90h92M14 86v8M106 86v8" opacity=".45"/>',
    bolt: '<path d="M18 44l12-4v40l-12-4z"/><path d="M30 50h66l8 10-8 10H30"/><path d="M42 50l5 20M52 50l5 20M62 50l5 20M72 50l5 20M82 50l5 20" opacity=".6"/>',
    mount: '<path d="M14 84h92v8H14z"/><path d="M22 92v6M38 92v6M54 92v6M70 92v6M86 92v6M100 92v6" opacity=".55"/><circle cx="38" cy="52" r="17"/><circle cx="82" cy="52" r="17"/><path d="M30 68l-4 16M46 68l4 16M74 68l-4 16M90 68l4 16"/><path d="M14 52h92" opacity=".3"/>',
    chrono: '<rect x="18" y="38" width="54" height="46" rx="3"/><rect x="26" y="46" width="38" height="16"/><path d="M31 54h6M41 54h6M51 54h6" /><path d="M28 72h10M44 72h10"/><path d="M82 50c6 6 6 18 0 24M90 44c10 10 10 26 0 36M98 38c14 14 14 34 0 48"/><path d="M40 84v12h10V84"/>',
    barrel: '<path d="M8 54h92v12H8z"/><path d="M100 51h10v18h-10"/><path d="M22 54v12M34 54v12M46 54v12M58 54v12" opacity=".45"/><path d="M4 60h112" stroke-dasharray="4 4" opacity=".35"/>',
    hose: '<path d="M16 34h16v12H16zM88 74h16v12H88z"/><path d="M32 40c34 0 40 8 28 20s-6 20 28 20"/><path d="M10 37h6M10 43h6M104 77h6M104 83h6"/>',
    nozzle: '<path d="M14 50h42v20H14z"/><path d="M56 54h30v12H56z"/><path d="M86 57h20v6H86z"/><path d="M22 50v20M30 50v20M38 50v20" opacity=".45"/><path d="M4 60h112" stroke-dasharray="4 4" opacity=".3"/>',
    rifle: '<path d="M4 56h54v7H4z"/><path d="M58 51h30v15H58z"/><path d="M88 49l28-4v26l-11 2-17-7"/><path d="M71 66l-4 18h9l4-18"/><rect x="16" y="63" width="40" height="11" rx="5.5"/><rect x="48" y="36" width="38" height="8" rx="4"/><path d="M55 44v7M79 44v7"/>',
    target: '<circle cx="60" cy="60" r="40"/><circle cx="60" cy="60" r="28"/><circle cx="60" cy="60" r="16"/><circle cx="60" cy="60" r="4"/><path d="M60 10v14M60 96v14M10 60h14M96 60h14"/>'
  };
  const art = (n) => `<svg viewBox="0 0 120 120" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${ART[n] || ART.target}</svg>`;

  const LOGO = `<svg class="logo-mark" viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="20" cy="20" r="14.5"/><circle cx="20" cy="20" r="2.6" fill="currentColor" stroke="none"/><path d="M20 1.5v11M20 27.5v11M1.5 20h11M27.5 20h11"/></svg><span class="logo-word"><span class="logo-name">Slug<b>shop</b></span><span class="logo-sub">Airgun · ZAN · FX</span></span>`;

  /* Hiányzó képek → placeholder rajz marad látható */
  document.addEventListener("error", (e) => { if (e.target && e.target.tagName === "IMG") e.target.classList.add("is-missing"); }, true);
  document.addEventListener("load", (e) => { if (e.target && e.target.tagName === "IMG") e.target.classList.add("is-loaded"); }, true);

  /* ------------------------------------------------------------ adatsegédek */
  const byId = (id) => S.PRODUCTS.find((p) => p.id === id);
  const catOf = (key) => S.CATS.find((c) => c.key === key);
  const calLabel = (k) => { const c = S.CALIBERS.find((x) => x.key === k); return c ? c.label : k; };
  const countCat = (keys) => S.PRODUCTS.filter((p) => keys.includes(p.cat)).length;

  /* ------------------------------------------------------------ pénznem */
  let currency = store.get("slugshop_cur", "HUF");
  function money(n) {
    if (n == null) return ["", ""];
    if (currency === "EUR") {
      const v = n / S.SETTINGS.eurRate;
      return [v.toLocaleString("hu-HU", { minimumFractionDigits: 2, maximumFractionDigits: 2 }), "€"];
    }
    return [String(Math.round(n)).replace(/\B(?=(\d{3})+(?!\d))/g, "."), "Ft"];
  }
  const moneyText = (n) => money(n).join(" ");
  const priceInner = (n) => { const [v, u] = money(n); return `${v}<small>${u}</small>`; };
  const priceHTML = (p, cls = "") => p.price == null
    ? `<span class="price price--ask ${cls}">Ár egyeztetés alapján</span>`
    : `<span class="price ${cls}" data-price="${p.price}">${priceInner(p.price)}</span>`;
  function refreshPrices() {
    $$("[data-price]").forEach((el) => { el.innerHTML = priceInner(+el.dataset.price); });
    $$("[data-money]").forEach((el) => { el.textContent = moneyText(+el.dataset.money); });
    $$(".cur-switch button").forEach((b) => b.setAttribute("aria-pressed", String(b.dataset.cur === currency)));
    updateCartUI();
  }

  /* ------------------------------------------------------------ kártya */
  const mediaHTML = (p) => `<span class="media"><span class="ph">${art(p.icon)}</span><img src="assets/img/termekek/${p.id}.jpg" alt="${esc(p.name)}" loading="lazy"></span>`;
  function actionBtn(p) {
    if (p.stock === true) return `<button class="add-btn" type="button" data-add="${p.id}" aria-label="Kosárba: ${esc(p.name)}">${icon("cart")}</button>`;
    if (p.stock === false) return `<button class="add-btn add-btn--notify" type="button" data-notify="${p.id}" aria-label="Értesítést kérek: ${esc(p.name)}">${icon("bell")}</button>`;
    return `<a class="add-btn add-btn--ask" href="kapcsolat.html#ajanlat-${p.id}" aria-label="Ajánlatot kérek: ${esc(p.name)}">${icon("mail")}</a>`;
  }
  function badgeHTML(p) {
    if (p.stock === false) return '<span class="badge badge--out">Elfogyott</span>';
    if (p.stock === "ask") return '<span class="badge badge--ask">Ajánlatra</span>';
    if (p.isNew) return '<span class="badge badge--new">Új</span>';
    return "";
  }
  function cardHTML(p) {
    const url = `termek.html#p-${p.id}`;
    const chips = (p.cal || []).map((k) => `<span class="chip chip--tan">${calLabel(k)}</span>`).join("");
    return `<article class="card">
      <a class="card-media" href="${url}" tabindex="-1" aria-hidden="true">${mediaHTML(p)}<span class="reticle-corners"></span><span class="card-badges">${badgeHTML(p)}</span></a>
      <div class="card-body">
        <div class="card-info">
          <span class="card-cat">${esc(p.sub)}</span>
          <h3><a href="${url}">${esc(p.name)}</a></h3>
          ${chips ? `<div class="card-chips">${chips}</div>` : ""}
        </div>
        <div class="card-foot">${priceHTML(p)}${actionBtn(p)}</div>
      </div>
    </article>`;
  }

  /* ------------------------------------------------------------ fejléc */
  const NAV = [
    { label: "ZAN slugok", href: "kategoria.html#grp-zan", drop: () => {
      const cals = S.BLUEPRINT.map((b) => `<a href="kategoria.html#kat-zan~kal-${b.key}">${b.label} ZAN slug <span>${b.dia} mm</span></a>`).join("");
      return `<div class="dropdown-title">Kaliber szerint</div>${cals}<div class="dropdown-title">Diabolók</div><a href="kategoria.html#kat-jsb">JSB diabolók <span>${countCat(["jsb"])}</span></a>`;
    } },
    { label: "FX fegyverek és kiegészítők", href: "kategoria.html#grp-fx", drop: () => {
      const subs = [...new Set(S.PRODUCTS.filter((p) => p.cat === "fx-kieg").map((p) => p.sub))];
      return `<a href="kategoria.html#kat-fx">FX légfegyverek <span>${countCat(["fx"])}</span></a><div class="dropdown-title">Kiegészítők</div>` +
        subs.map((s) => `<a href="kategoria.html#kat-fx-kieg~sub-${slugify(s)}">${esc(s)} <span>${S.PRODUCTS.filter((p) => p.sub === s).length}</span></a>`).join("");
    } },
    { label: "FX alkatrészek", href: "kategoria.html#kat-fx-alk" },
    { label: "Snowpeak", href: "kategoria.html#kat-snowpeak" },
    { label: "Szerviz", href: "szerviz.html", page: "service" },
    { label: "Videók", href: "videok.html", page: "videos" },
    { label: "Letöltések", href: "letoltesek.html", page: "downloads" },
    { label: "Cikkeink", href: "cikkek.html", page: "articles" },
    { label: "Kapcsolat", href: "kapcsolat.html", page: "contact" }
  ];
  const TICKER = ["Kizárólagos magyarországi ZAN forgalmazó", "Viva Wallet bankkártyás fizetés", "Gyári FX alkatrészek cikkszám szerint", "Saját szerviz – Jászberény", "Scandinavian Arms hivatalos forgalmazó", "Kérdése van? +36 30 677 7836"];
  const SOCIAL = `<a href="https://www.facebook.com/profile.php?id=61554078063288" target="_blank" rel="noopener" aria-label="Facebook">${icon("fb")}</a><a href="https://www.instagram.com/slugshop.hungary/" target="_blank" rel="noopener" aria-label="Instagram">${icon("ig")}</a><a href="https://www.youtube.com/@laszlomora4678" target="_blank" rel="noopener" aria-label="YouTube">${icon("yt")}</a>`;

  function headerHTML(page) {
    const items = NAV.map((n) => `<li class="nav-item">
        <a class="nav-link" href="${n.href}"${n.page ? ` data-nav-page="${n.page}"` : ""}${n.page === page ? ' aria-current="page"' : ""}>${n.label}${n.drop ? icon("chevron") : ""}</a>
        ${n.drop ? `<div class="dropdown">${n.drop()}</div>` : ""}
      </li>`).join("");
    const tick = TICKER.map((t) => `<span>${t}</span>`).join("");
    return `<a class="skip-link" href="#main">Ugrás a tartalomra</a>
    <div class="topbar"><div class="wrap topbar-in">
      <div class="tb-group">
        <a class="tb-link" href="tel:+36306777836">${icon("phone")}+36 30 677 7836</a>
        <a class="tb-link tb-hide-sm" href="mailto:info@slugshop.hu">${icon("mail")}info@slugshop.hu</a>
      </div>
      <div class="tb-group">
        <div class="cur-switch" role="group" aria-label="Pénznem"><button type="button" data-cur="HUF">HUF</button><button type="button" data-cur="EUR">EUR</button></div>
        <span class="tb-sep tb-hide-md"></span>
        <div class="tb-social tb-hide-md">${SOCIAL}</div>
        <span class="tb-sep tb-hide-sm"></span>
        <button class="tb-link tb-hide-sm" type="button" data-open="login">${icon("user")}Belépés</button>
        <button class="tb-link tb-hide-sm" type="button" data-open="login" data-tab="register">Regisztráció</button>
      </div>
    </div></div>
    <div class="ticker"><div class="ticker-track">${tick}${tick}</div></div>
    <header class="site-header">
      <div class="wrap hdr-in">
        <button class="icon-btn burger" type="button" data-open="mnav" aria-label="Menü megnyitása">${icon("menu")}</button>
        <a class="logo" href="index.html" aria-label="Slugshop kezdőlap">${LOGO}</a>
        <button class="hdr-search" type="button" data-open="search">${icon("search")}<span>Keresés: ZAN .22, FX Impact, tár…</span><kbd>/</kbd></button>
        <div class="hdr-actions">
          <button class="icon-btn hdr-search-ico" type="button" data-open="search" aria-label="Keresés">${icon("search")}</button>
          <button class="icon-btn tb-hide-sm" type="button" data-open="login" aria-label="Fiókom">${icon("user")}</button>
          <button class="cart-btn" type="button" data-open="cart" aria-label="Kosár megnyitása">
            <span class="cart-ico">${icon("cart")}<span class="cart-count" data-cart-count data-n="0">0</span></span>
            <span class="cart-total" data-cart-total>0 Ft</span>
          </button>
        </div>
      </div>
      <nav class="main-nav" aria-label="Fő menü"><div class="wrap"><ul class="nav-list">${items}</ul></div></nav>
    </header>`;
  }

  function footerHTML() {
    const cats = S.CATS.map((c) => `<li><a href="kategoria.html#kat-${c.key}">${c.name}</a></li>`).join("");
    return `<footer class="site-footer">
      <div class="wrap">
        <div class="ft-top">
          <div class="ft-col ft-about">
            <a class="logo" href="index.html" aria-label="Slugshop kezdőlap">${LOGO}</a>
            <p>Prémium PCP légfegyverek, ZAN slugok, gyári FX alkatrészek és saját szerviz. A ZAN Projectiles kizárólagos magyarországi forgalmazója.</p>
            <div class="ft-contact">
              <a class="tb-link" href="tel:+36306777836">${icon("phone")}+36 30 677 7836</a>
              <a class="tb-link" href="mailto:info@slugshop.hu">${icon("mail")}info@slugshop.hu</a>
              <div class="tb-social">${SOCIAL}</div>
            </div>
            <a class="ft-badge" href="https://fogyasztobarat.hu/tanusitvany-ellenorzes/?csi=MB0C642A" target="_blank" rel="noopener">${icon("shield")}<span><b>Fogyasztó Barát tanúsítvány</b>Jogszabálykövető webáruház 2023 óta</span></a>
          </div>
          <div class="ft-col"><h4>Webshop</h4><ul>${cats}</ul></div>
          <div class="ft-col"><h4>Információ</h4><ul>
            <li><a href="rolunk.html">Rólunk</a></li><li><a href="szerviz.html">Szerviz</a></li><li><a href="letoltesek.html">Letöltések</a></li>
            <li><a href="videok.html">Videók</a></li><li><a href="cikkek.html">Cikkeink</a></li><li><a href="kapcsolat.html">Kapcsolat</a></li>
            <li><a href="#aszf">ÁSZF</a></li><li><a href="#impresszum">Impresszum</a></li><li><a href="#adatvedelem">Adatvédelmi nyilatkozat</a></li><li><a href="#" data-open-cookie>Süti beállítások</a></li>
          </ul></div>
          <div class="ft-col"><h4>Cégadatok</h4>
            <dl class="ft-company">
              <dt>Cégnév</dt><dd>Slugshop Kft.</dd>
              <dt>Székhely</dt><dd>5100 Jászberény, Érhát utca 7.</dd>
              <dt>Adószám</dt><dd>32247147-2-16</dd>
              <dt>Cégjegyzékszám</dt><dd>16-09-022741</dd>
            </dl>
          </div>
        </div>
        <div class="ft-legal-note">${icon("shield")}<p><b>Engedélyköteles termékek.</b> A 7,5 joule feletti csőtorkolati energiájú légfegyverek megvásárlása és tartása Magyarországon engedélyhez kötött. Vásárlás előtt kérjen tanácsot tőlünk.</p></div>
      </div>
      <div class="ft-bottom"><div class="wrap">
        <span>© ${new Date().getFullYear()} Slugshop Kft. Minden jog fenntartva. · <a href="utmutato.html" style="color:var(--tan)">Prototípus útmutató</a></span>
        <div class="ft-pay"><span>Viva Wallet</span><span>Barion</span><span>Visa</span><span>Mastercard</span><span>Átutalás</span></div>
      </div></div>
    </footer>
    <button class="to-top" type="button" aria-label="Vissza a tetejére">${icon("up")}</button>`;
  }

  function layersHTML() {
    const mnav = NAV.map((n) => {
      let sub = "";
      if (n.drop) sub = `<div class="mnav-sub">${n.drop().replace(/<div class="dropdown-title">[^<]*<\/div>/g, "")}</div>`;
      return `<a href="${n.href}">${n.label}${icon("arrow")}</a>${sub}`;
    }).join("");
    return `
    <div class="scrim" data-close></div>

    <aside class="drawer drawer--left" id="mnav" data-layer aria-hidden="true" aria-label="Menü">
      <div class="drawer-head"><a class="logo" href="index.html">${LOGO}</a><button class="icon-btn" type="button" data-close aria-label="Bezárás">${icon("close")}</button></div>
      <div class="drawer-body"><nav class="mnav">${mnav}</nav>
        <div class="mnav-meta">
          <button class="btn btn--ghost btn--sm" type="button" data-open="login">${icon("user")}Belépés / Regisztráció</button>
          <a class="tb-link" href="tel:+36306777836">${icon("phone")}+36 30 677 7836</a>
          <a class="tb-link" href="mailto:info@slugshop.hu">${icon("mail")}info@slugshop.hu</a>
          <div class="tb-social">${SOCIAL}</div>
        </div>
      </div>
    </aside>

    <aside class="drawer drawer--right" id="cart" data-layer aria-hidden="true" aria-label="Kosár">
      <div class="drawer-head"><h2>Kosár <span class="mono num" data-cart-count-text style="font-size:1rem;color:var(--tan)"></span></h2><button class="icon-btn" type="button" data-close aria-label="Kosár bezárása">${icon("close")}</button></div>
      <div class="drawer-body" data-cart-lines></div>
      <div class="drawer-foot" data-cart-foot>
        <div class="row-between row-between--total"><span class="lbl">Részösszeg</span><span class="val" data-cart-sub>0 Ft</span></div>
        <p class="hint">A szállítási díjat a pénztárban számoljuk.</p>
        <a class="btn btn--block" href="penztar.html">Tovább a pénztárhoz ${icon("arrow")}</a>
        <a class="btn btn--ghost btn--block" href="kosar.html">Kosár megtekintése</a>
      </div>
    </aside>

    <div class="search-overlay" id="search" data-layer aria-hidden="true" role="dialog" aria-label="Keresés">
      <div class="so-head"><div class="wrap">${icon("search", "lead")}
        <label class="sr-only" for="so-input">Keresés a termékek között</label>
        <input class="so-input" id="so-input" type="search" placeholder="Mit keresel?" autocomplete="off">
        <button class="icon-btn" type="button" data-close aria-label="Keresés bezárása">${icon("close")}</button>
      </div></div>
      <div class="so-body"><div class="wrap">
        <p class="so-hint" data-so-hint>Népszerű keresések</p>
        <div class="so-tags">${["ZAN .22", "ZAN .30", "FX Impact", "JSB Hades", "tár", "kronográf", "céltávcső", "rugó"].map((t) => `<button type="button" data-so-tag="${t}">${t}</button>`).join("")}</div>
        <div class="so-results" data-so-results></div>
      </div></div>
    </div>

    <div class="modal" id="login" data-layer aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="login-title">
      <div class="modal-card">
        <button class="icon-btn" type="button" data-close aria-label="Bezárás">${icon("close")}</button>
        <div data-login-panel="login">
          <span class="eyebrow">Fiók</span>
          <h2 id="login-title">Bejelentkezés</h2>
          <p>Lépjen be a rendelései és címei eléréséhez.</p>
          <form class="form" data-form data-success-title="Prototípus" data-success-text="A belépés a WordPress verzióban lesz működőképes.">
            <div class="field"><label for="lg-user">Felhasználónév vagy e-mail <span class="req">*</span></label><input class="input" id="lg-user" required autocomplete="username"></div>
            <div class="field"><label for="lg-pass">Jelszó <span class="req">*</span></label><input class="input" id="lg-pass" type="password" required autocomplete="current-password"></div>
            <div class="row-between"><label class="check"><input type="checkbox" id="lg-remember"> Emlékezzen rám</label><a class="hint" href="#jelszo">Elfelejtett jelszó?</a></div>
            <button class="btn btn--block" type="submit">${icon("lock")}Belépés</button>
            <p class="hint">Nincs fiókja? <button class="link-arrow" type="button" data-login-tab="register" style="font-size:inherit">Regisztráció</button></p>
          </form>
        </div>
        <div data-login-panel="register" hidden>
          <span class="eyebrow">Új fiók</span>
          <h2>Regisztráció</h2>
          <p>Gyorsabb rendelés, mentett címek, rendeléskövetés.</p>
          <form class="form" data-form data-success-title="Prototípus" data-success-text="A regisztráció a WordPress verzióban lesz működőképes.">
            <div class="field"><label for="rg-name">Név <span class="req">*</span></label><input class="input" id="rg-name" required autocomplete="name"></div>
            <div class="field"><label for="rg-email">E-mail <span class="req">*</span></label><input class="input" id="rg-email" type="email" required autocomplete="email"></div>
            <div class="field"><label for="rg-pass">Jelszó <span class="req">*</span></label><input class="input" id="rg-pass" type="password" required minlength="8" autocomplete="new-password"><span class="hint">Legalább 8 karakter.</span></div>
            <label class="check"><input type="checkbox" id="rg-accept" required> <span>Elfogadom az <a href="#aszf">ÁSZF</a>-et és az <a href="#adatvedelem">adatvédelmi nyilatkozatot</a>.</span></label>
            <button class="btn btn--block" type="submit">Fiók létrehozása</button>
            <p class="hint">Van már fiókja? <button class="link-arrow" type="button" data-login-tab="login" style="font-size:inherit">Belépés</button></p>
          </form>
        </div>
      </div>
    </div>

    <div class="modal" id="notify" data-layer aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="notify-title">
      <div class="modal-card">
        <button class="icon-btn" type="button" data-close aria-label="Bezárás">${icon("close")}</button>
        <span class="eyebrow">Készletfigyelő</span>
        <h2 id="notify-title">Értesítést kérek</h2>
        <p data-notify-name></p>
        <form class="form" data-form data-success-title="Feliratkozott" data-success-text="E-mailt küldünk, amint a termék újra raktárra kerül.">
          <div class="field"><label for="nt-email">E-mail cím <span class="req">*</span></label><input class="input" id="nt-email" type="email" required autocomplete="email" placeholder="nev@pelda.hu"></div>
          <label class="check"><input type="checkbox" id="nt-accept" required> <span>Elfogadom az <a href="#adatvedelem">adatvédelmi nyilatkozatot</a>.</span></label>
          <button class="btn btn--block" type="submit">${icon("bell")}Értesítést kérek</button>
        </form>
      </div>
    </div>

    <div class="toasts" aria-live="polite"></div>`;
  }

  function cookieHTML() {
    return `<div class="cookie" role="dialog" aria-label="Süti beállítások">
      <span class="eyebrow">Sütik</span>
      <p>A weboldalon sütiket használunk, hogy a legjobb élményt nyújtsuk. Részletek a <a href="#suti">süti szabályzatban</a> és az <a href="#adatvedelem">adatvédelmi irányelvekben</a>.</p>
      <div class="cookie-opts" hidden>
        <label class="toggle">Kötelező (mindig aktív)<input type="checkbox" checked disabled></label>
        <label class="toggle">Ajánlott<input type="checkbox" id="ck-pref" checked></label>
        <label class="toggle">Teljesítmény mérése<input type="checkbox" id="ck-stat"></label>
        <label class="toggle">Saját marketing<input type="checkbox" id="ck-mkt"></label>
      </div>
      <div class="cookie-actions">
        <button class="btn btn--sm" type="button" data-cookie="all">Összes elfogadása</button>
        <button class="btn btn--ghost btn--sm" type="button" data-cookie="deny">Elutasítom</button>
        <button class="btn btn--ghost btn--sm" type="button" data-cookie="settings">Beállítások</button>
      </div>
    </div>`;
  }

  /* ------------------------------------------------------------ rétegek */
  let lastFocus = null;
  function openLayer(id) {
    const el = document.getElementById(id);
    if (!el) return;
    closeLayers(true);
    lastFocus = document.activeElement;
    el.classList.add("is-open");
    if (!el.hasAttribute("data-keep-aria")) el.setAttribute("aria-hidden", "false");
    if (!el.classList.contains("search-overlay") && !el.classList.contains("modal")) $(".scrim").classList.add("is-open");
    if (el.classList.contains("modal")) $(".scrim").classList.add("is-open");
    document.documentElement.style.overflow = "hidden";
    const f = el.querySelector("input:not([type=hidden]):not([disabled]), .icon-btn");
    if (f) setTimeout(() => f.focus(), 60);
  }
  function closeLayers(silent) {
    $$("[data-layer].is-open").forEach((l) => { l.classList.remove("is-open"); if (!l.hasAttribute("data-keep-aria")) l.setAttribute("aria-hidden", "true"); });
    const sc = $(".scrim"); if (sc) sc.classList.remove("is-open");
    document.documentElement.style.overflow = "";
    if (!silent && lastFocus && lastFocus.focus) lastFocus.focus();
  }

  /* ------------------------------------------------------------ toast */
  function toast(msg, link) {
    const box = $(".toasts");
    const t = document.createElement("div");
    t.className = "toast";
    t.innerHTML = `${icon("check")}<span>${msg}</span>${link ? `<a href="${link.href}" ${link.open ? `data-open="${link.open}"` : ""}>${link.label}</a>` : ""}`;
    box.appendChild(t);
    setTimeout(() => { t.classList.add("out"); setTimeout(() => t.remove(), 260); }, 3200);
  }

  /* ------------------------------------------------------------ kosár */
  let cart = store.get("slugshop_cart", []).filter((l) => byId(l.id) && byId(l.id).price != null);
  const cartCount = () => cart.reduce((a, l) => a + l.qty, 0);
  const cartSub = () => cart.reduce((a, l) => a + byId(l.id).price * l.qty, 0);
  function saveCart() { store.set("slugshop_cart", cart); updateCartUI(); document.dispatchEvent(new CustomEvent("cart:change")); }
  function addToCart(id, qty = 1) {
    const p = byId(id); if (!p || p.stock !== true) return;
    const line = cart.find((l) => l.id === id);
    if (line) line.qty = Math.min(99, line.qty + qty); else cart.push({ id, qty });
    saveCart();
    const c = $("[data-cart-count]"); if (c) { c.classList.remove("bump"); void c.offsetWidth; c.classList.add("bump"); }
    toast(`<b>${esc(p.name.length > 48 ? p.name.slice(0, 46) + "…" : p.name)}</b> a kosárban`, { href: "#", open: "cart", label: "Kosár" });
  }
  function setQty(id, qty) {
    const line = cart.find((l) => l.id === id); if (!line) return;
    line.qty = Math.max(0, Math.min(99, qty | 0));
    if (!line.qty) cart = cart.filter((l) => l.id !== id);
    saveCart();
  }
  const qtyHTML = (id, qty, lg) => `<div class="qty${lg ? " qty--lg" : ""}"><button type="button" data-qty-dec="${id}" aria-label="Csökkentés">${icon("minus")}</button><input type="number" min="1" max="99" value="${qty}" data-qty-input="${id}" aria-label="Mennyiség"><button type="button" data-qty-inc="${id}" aria-label="Növelés">${icon("plus")}</button></div>`;

  function updateCartUI() {
    const n = cartCount();
    $$("[data-cart-count]").forEach((el) => { el.textContent = n; el.dataset.n = n; });
    $$("[data-cart-count-text]").forEach((el) => { el.textContent = n ? `(${n})` : ""; });
    $$("[data-cart-total]").forEach((el) => { el.textContent = moneyText(cartSub()); });
    $$("[data-cart-sub]").forEach((el) => { el.textContent = moneyText(cartSub()); });
    const lines = $("[data-cart-lines]");
    if (!lines) return;
    const foot = $("[data-cart-foot]");
    if (!cart.length) {
      lines.innerHTML = `<div class="empty">${icon("cart")}<h3>Üres a kosár</h3><p>Nézz körül a ZAN slugok vagy az FX kiegészítők között.</p><a class="btn btn--sm" href="kategoria.html">Irány a webshop</a></div>`;
      foot.hidden = true;
      return;
    }
    foot.hidden = false;
    lines.innerHTML = cart.map((l) => {
      const p = byId(l.id);
      return `<div class="mini-item">${mediaHTML(p)}
        <div><h3><a href="termek.html#p-${p.id}">${esc(p.name)}</a></h3><div class="mini-meta">${qtyHTML(p.id, l.qty)}</div></div>
        <div style="display:grid;justify-items:end;gap:10px"><span class="mini-price">${moneyText(p.price * l.qty)}</span><button class="remove-btn" type="button" data-remove="${p.id}" aria-label="Eltávolítás">${icon("trash")}</button></div>
      </div>`;
    }).join("");
  }

  /* ------------------------------------------------------------ kereső */
  function searchProducts(q) {
    const nq = norm(q.trim());
    if (!nq) return [];
    const terms = nq.split(/\s+/);
    return S.PRODUCTS.filter((p) => {
      const hay = norm(`${p.name} ${p.sub} ${p.brand || ""} ${p.sku || ""} ${(p.cal || []).map(calLabel).join(" ")}`);
      return terms.every((t) => hay.includes(t));
    });
  }
  function highlight(text, q) {
    const nq = norm(q.trim()); if (!nq) return esc(text);
    const nt = norm(text);
    let out = "", i = 0;
    const terms = nq.split(/\s+/).filter(Boolean);
    while (i < text.length) {
      let hit = null;
      for (const t of terms) if (nt.startsWith(t, i)) { hit = t; break; }
      if (hit) { out += `<mark>${esc(text.slice(i, i + hit.length))}</mark>`; i += hit.length; }
      else { out += esc(text[i]); i++; }
    }
    return out;
  }
  function renderSearch(q) {
    const res = searchProducts(q);
    const box = $("[data-so-results]");
    const hint = $("[data-so-hint]");
    if (!q.trim()) { box.innerHTML = ""; hint.textContent = "Népszerű keresések"; return; }
    hint.textContent = res.length ? `${res.length} találat — Enter: összes megnyitása` : "Nincs találat. Próbáld kaliberrel vagy cikkszámmal (pl. .22, FX20710).";
    box.innerHTML = res.slice(0, 12).map((p) => `<a class="so-result" href="termek.html#p-${p.id}">${mediaHTML(p)}<div><h3>${highlight(p.name, q)}</h3><small>${esc(p.sub)}</small></div>${p.price == null ? '<span class="chip">Ajánlat</span>' : `<span class="mini-price" data-money="${p.price}">${moneyText(p.price)}</span>`}</a>`).join("");
  }

  /* ------------------------------------------------------------ űrlapok */
  function validateForm(form) {
    let ok = true;
    $$(".field", form).forEach((f) => { f.classList.remove("has-error"); const e = $(".field-error", f); if (e) e.remove(); });
    $$(".check", form).forEach((c) => c.classList.remove("has-error"));
    $$("input, textarea, select", form).forEach((el) => {
      if (el.disabled || el.closest("[hidden]")) return;
      let msg = "";
      const v = (el.value || "").trim();
      if (el.type === "checkbox") {
        if (el.required && !el.checked) { el.closest(".check").classList.add("has-error"); ok = false; }
        return;
      }
      if (el.required && !v) msg = "Kötelező mező.";
      else if (v && el.type === "email" && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)) msg = "Adjon meg érvényes e-mail címet.";
      else if (v && el.dataset.phone != null && v.replace(/\D/g, "").length < 11) msg = "Adjon meg teljes telefonszámot.";
      else if (v && el.minLength > 0 && v.length < el.minLength) msg = `Legalább ${el.minLength} karakter szükséges.`;
      if (msg) {
        ok = false;
        const f = el.closest(".field");
        if (f) { f.classList.add("has-error"); f.insertAdjacentHTML("beforeend", `<span class="field-error">${msg}</span>`); }
      }
    });
    if (!ok) { const first = $(".has-error input, .has-error textarea, .has-error select", form); if (first) first.focus(); }
    return ok;
  }
  function successBox(title, text) {
    return `<div class="form-success" role="status">${icon("check")}<h3>${esc(title)}</h3><p>${esc(text)}</p></div>`;
  }
  function phoneMask(el) {
    el.addEventListener("input", () => {
      let d = el.value.replace(/\D/g, "");
      if (d.startsWith("06")) d = "36" + d.slice(2);
      if (!d.startsWith("36")) d = "36" + d;
      d = d.slice(0, 11);
      const p = [d.slice(2, 4), d.slice(4, 7), d.slice(7, 11)].filter(Boolean);
      el.value = "+36" + (p.length ? " " + p.join(" ") : "");
    });
    el.addEventListener("focus", () => { if (!el.value) el.value = "+36 "; });
    el.addEventListener("blur", () => { if (el.value.trim() === "+36") el.value = ""; });
  }
  function initForms(root = document) {
    $$("form[data-form]", root).forEach((form) => {
      if (form.dataset.bound) return;
      form.dataset.bound = "1";
      form.setAttribute("novalidate", "");
      $$("[data-phone]", form).forEach(phoneMask);
      form.addEventListener("submit", (e) => {
        e.preventDefault();
        if (!validateForm(form)) return;
        form.outerHTML = successBox(form.dataset.successTitle || "Köszönjük!", form.dataset.successText || "Üzenetét megkaptuk, hamarosan válaszolunk.");
      });
    });
    $$(".dropzone", root).forEach((dz) => {
      if (dz.dataset.bound) return;
      dz.dataset.bound = "1";
      const input = $("input[type=file]", dz);
      const list = dz.parentElement.querySelector(".file-list");
      const show = (files) => { list.innerHTML = Array.from(files).map((f) => `<span class="chip">${icon("file")}${esc(f.name)}</span>`).join(""); };
      dz.addEventListener("click", (e) => { if (e.target !== input) input.click(); });
      dz.addEventListener("keydown", (e) => { if (e.key === "Enter" || e.key === " ") { e.preventDefault(); input.click(); } });
      input.addEventListener("change", () => show(input.files));
      ["dragenter", "dragover"].forEach((ev) => dz.addEventListener(ev, (e) => { e.preventDefault(); dz.classList.add("is-over"); }));
      ["dragleave", "drop"].forEach((ev) => dz.addEventListener(ev, (e) => { e.preventDefault(); dz.classList.remove("is-over"); }));
      dz.addEventListener("drop", (e) => { if (e.dataTransfer && e.dataTransfer.files.length) show(e.dataTransfer.files); });
    });
  }

  /* ------------------------------------------------------------ globális események */
  function bindGlobal() {
    document.addEventListener("click", (e) => {
      const t = e.target.closest("[data-open],[data-close],[data-add],[data-notify],[data-remove],[data-qty-inc],[data-qty-dec],[data-cur],[data-so-tag],[data-login-tab],[data-cookie],[data-open-cookie]");
      if (!t) return;
      if (t.hasAttribute("data-open")) {
        e.preventDefault();
        const id = t.dataset.open;
        openLayer(id);
        if (id === "login") switchLogin(t.dataset.tab || "login");
        if (id === "search") renderSearch($("#so-input").value);
      } else if (t.hasAttribute("data-close")) {
        closeLayers();
      } else if (t.dataset.add) {
        const qEl = $("[data-pdp-qty]");
        addToCart(t.dataset.add, t.hasAttribute("data-pdp-add") && qEl ? Math.max(1, +qEl.value || 1) : 1);
        if (t.classList.contains("add-btn")) { t.classList.add("is-done"); t.innerHTML = icon("check"); setTimeout(() => { t.classList.remove("is-done"); t.innerHTML = icon("cart"); }, 1400); }
      } else if (t.dataset.notify) {
        const p = byId(t.dataset.notify);
        openLayer("notify");
        $("[data-notify-name]").textContent = `Szólunk, amint a(z) ${p.name} újra raktárra kerül.`;
      } else if (t.dataset.remove) {
        setQty(t.dataset.remove, 0);
      } else if (t.dataset.qtyInc) {
        const l = cart.find((x) => x.id === t.dataset.qtyInc); if (l) setQty(l.id, l.qty + 1);
      } else if (t.dataset.qtyDec) {
        const l = cart.find((x) => x.id === t.dataset.qtyDec); if (l) setQty(l.id, l.qty - 1);
      } else if (t.dataset.cur) {
        currency = t.dataset.cur; store.set("slugshop_cur", currency); refreshPrices();
        document.dispatchEvent(new CustomEvent("currency:change"));
      } else if (t.dataset.soTag) {
        const i = $("#so-input"); i.value = t.dataset.soTag; renderSearch(i.value); i.focus();
      } else if (t.dataset.loginTab) {
        switchLogin(t.dataset.loginTab);
      } else if (t.dataset.cookie) {
        const c = $(".cookie");
        if (t.dataset.cookie === "settings") {
          const o = $(".cookie-opts", c);
          if (o.hidden) { o.hidden = false; t.textContent = "Mentés"; return; }
          store.set("slugshop_cookie", { pref: $("#ck-pref").checked, stat: $("#ck-stat").checked, mkt: $("#ck-mkt").checked });
        } else store.set("slugshop_cookie", t.dataset.cookie === "all" ? { pref: true, stat: true, mkt: true } : { pref: false, stat: false, mkt: false });
        c.remove();
      } else if (t.hasAttribute("data-open-cookie")) {
        e.preventDefault();
        if (!$(".cookie")) document.body.insertAdjacentHTML("beforeend", cookieHTML());
      }
    });
    document.addEventListener("change", (e) => {
      const i = e.target.closest("[data-qty-input]");
      if (i) setQty(i.dataset.qtyInput, Math.max(1, +i.value || 1));
    });
    $$(".modal").forEach((m) => m.addEventListener("click", (e) => { if (e.target === m) closeLayers(); }));
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape") closeLayers();
      if (e.key === "/" && !/input|textarea|select/i.test(document.activeElement.tagName)) { e.preventDefault(); openLayer("search"); }
    });
    const si = $("#so-input");
    si.addEventListener("input", () => renderSearch(si.value));
    si.addEventListener("keydown", (e) => {
      if (e.key === "Enter" && si.value.trim()) { go(`kategoria.html#q-${encodeURIComponent(si.value.trim())}`); closeLayers(true); }
    });
    const tt = $(".to-top");
    const onScroll = () => tt.classList.toggle("is-visible", window.scrollY > 900);
    window.addEventListener("scroll", onScroll, { passive: true }); onScroll();
    tt.addEventListener("click", () => window.scrollTo({ top: 0, behavior: reduceMotion ? "auto" : "smooth" }));
  }
  function switchLogin(tab) {
    $$("[data-login-panel]").forEach((p) => { p.hidden = p.dataset.loginPanel !== tab; });
  }

  /* ==================================================================
     OLDALAK
     ================================================================== */

  /* ---------------------------------------------------------- nyitóoldal */
  function initHome() {
    // hero slider
    const slidesBox = $("#hero-slides"), tabsBox = $("#hero-tabs"), hero = $(".hero");
    if (slidesBox) {
      slidesBox.innerHTML = S.SLIDES.map((s, i) => `<div class="hero-slide${i === 0 ? " is-active" : ""}" id="slide-${i}" role="tabpanel" aria-roledescription="dia" aria-label="${i + 1} / ${S.SLIDES.length}">
          <span class="eyebrow">${s.eyebrow}</span>
          ${i === 0 ? "<h1" : "<h2"} class="hero-title">${s.title}${i === 0 ? "</h1>" : "</h2>"}
          <p class="hero-lead">${s.lead}</p>
          <div class="hero-cta"><a class="btn" href="${s.cta.href}">${s.cta.label}${icon("arrow")}</a><a class="btn btn--ghost" href="kategoria.html">Teljes kínálat</a></div>
        </div>`).join("");
      tabsBox.innerHTML = S.SLIDES.map((s, i) => `<button class="hero-tab" type="button" role="tab" aria-controls="slide-${i}" aria-selected="${i === 0}"><i></i><span>${s.tab}</span></button>`).join("");
      const DUR = 6500;
      hero.style.setProperty("--dur", DUR + "ms");
      let cur = 0, timer = null;
      const go = (n) => {
        cur = (n + S.SLIDES.length) % S.SLIDES.length;
        $$(".hero-slide", slidesBox).forEach((el, i) => el.classList.toggle("is-active", i === cur));
        $$(".hero-tab", tabsBox).forEach((el, i) => el.setAttribute("aria-selected", String(i === cur)));
      };
      const play = () => { if (reduceMotion) return; clearInterval(timer); timer = setInterval(() => go(cur + 1), DUR); hero.classList.remove("is-paused"); };
      const pause = () => { clearInterval(timer); hero.classList.add("is-paused"); };
      $$(".hero-tab", tabsBox).forEach((b, i) => b.addEventListener("click", () => { go(i); play(); }));
      const left = $(".hero-copy");
      left.addEventListener("mouseenter", pause); left.addEventListener("mouseleave", play);
      left.addEventListener("focusin", pause); left.addEventListener("focusout", play);
      if (reduceMotion) hero.classList.add("is-paused");
      cleanups.push(() => clearInterval(timer));
      // az első ciklus progress-sávja
      requestAnimationFrame(() => { const t = $(".hero-tab[aria-selected=true]", tabsBox); if (t) { t.setAttribute("aria-selected", "false"); void t.offsetWidth; t.setAttribute("aria-selected", "true"); } });
      play();
    }

    // műszaki rajz kaliber-váltó
    const sw = $("#cal-switch");
    if (sw) {
      sw.innerHTML = S.BLUEPRINT.map((b, i) => `<button type="button" data-bp="${b.key}" aria-pressed="${i === 0}">${b.label}</button>`).join("");
      const setBp = (key) => {
        const b = S.BLUEPRINT.find((x) => x.key === key); const p = byId(b.product);
        $$("button", sw).forEach((x) => x.setAttribute("aria-pressed", String(x.dataset.bp === key)));
        $("#bp-slug").style.transform = `scale(${b.scale})`;
        $("#bp-dia").textContent = `Ø ${b.dia}`;
        $("#bp-title").textContent = `ZAN HP ${b.label}`;
        $("#bp-cal").textContent = b.label;
        $("#rd-dia").textContent = `${b.dia} mm`;
        $("#rd-gr").textContent = `${b.gr} gr`;
        $("#rd-pcs").textContent = `${b.pcs} db`;
        const rp = $("#rd-price"); rp.dataset.money = p.price; rp.textContent = moneyText(p.price);
        $("#bp-link").href = `termek.html#p-${p.id}`;
      };
      sw.addEventListener("click", (e) => { const b = e.target.closest("[data-bp]"); if (b) setBp(b.dataset.bp); });
      setBp("22");
    }

    // kategória csempék
    const cats = $("#cats");
    if (cats) {
      const tiles = [
        { lg: true, href: "kategoria.html#grp-zan", title: "ZAN slugok", icon: "slug", count: countCat(["zan", "jsb"]), text: "Hollow point slugok .22-től .357-ig és JSB diabolók. A ZAN Projectiles kizárólagos magyarországi forgalmazója vagyunk." },
        { lg: true, href: "kategoria.html#grp-fx", title: "FX fegyverek és kiegészítők", icon: "scope", count: countCat(["fx", "fx-kieg"]), text: "FX Impact, Dreamline, Panthera és Leopard. Tárak, csövek, céltávcsövek, kronográfok." },
        { href: "kategoria.html#kat-fx-alk", title: "FX alkatrészek", icon: "spring", count: countCat(["fx-alk"]) },
        { href: "kategoria.html#kat-jsb", title: "JSB diabolók", icon: "pellet", count: countCat(["jsb"]) },
        { href: "kategoria.html#kat-karb", title: "Karbantartás, céltáblák", icon: "target", count: countCat(["karb"]) },
        { href: "szerviz.html", title: "Szerviz", icon: "gauge", countText: "Javítás · beállítás" }
      ];
      cats.innerHTML = tiles.map((t) => `<a class="cat-tile${t.lg ? " cat-tile--lg" : ""}" href="${t.href}">
          <span class="cat-art">${art(t.icon)}</span>
          <span class="cat-count">${t.countText || (t.count ? `${t.count} termék` : "Hamarosan")}</span>
          <div><h3>${t.title}</h3>${t.text ? `<p>${t.text}</p>` : ""}</div>
          <span class="link-arrow">Megnézem${icon("arrow")}</span>
        </a>`).join("");
    }

    // új termékek
    const grid = $("#new-products"), tabs = $("#new-tabs"), more = $("#new-more");
    if (grid) {
      const filters = { all: () => true, ammo: (p) => ["zan", "jsb"].includes(p.cat), kieg: (p) => p.cat === "fx-kieg", alk: (p) => ["fx-alk", "snowpeak"].includes(p.cat) };
      let f = "all", expanded = false;
      const render = () => {
        const list = S.NEW_ORDER.map(byId).filter(filters[f]);
        const shown = expanded ? list : list.slice(0, 8);
        grid.innerHTML = shown.map(cardHTML).join("");
        more.hidden = list.length <= 8 || expanded;
        more.querySelector("span").textContent = `Mind a ${list.length} termék`;
      };
      tabs.addEventListener("click", (e) => {
        const b = e.target.closest("[data-f]"); if (!b) return;
        f = b.dataset.f; expanded = false;
        $$("[data-f]", tabs).forEach((x) => x.setAttribute("aria-selected", String(x === b)));
        render();
      });
      more.addEventListener("click", () => { expanded = true; render(); });
      render();
    }

    // ZAN tábla
    const zt = $("#zan-table");
    if (zt) {
      const zans = S.BLUEPRINT.map((b) => ({ b, p: byId(b.product) }));
      zt.innerHTML = zans.map(({ b, p }) => `<tr>
          <td><span class="cal">${b.label}</span></td>
          <td class="mm">${b.dia} mm</td>
          <td class="num">${b.gr} gr<span class="bar" aria-hidden="true"><i style="width:${Math.round(parseFloat(b.gr.replace(",", ".")) / 100 * 100)}%"></i></span></td>
          <td class="num">${b.pcs} db</td>
          <td class="r mono num" data-money="${Math.round(p.price / b.pcs * 10) / 10}">${moneyText(Math.round(p.price / b.pcs * 10) / 10)}</td>
          <td class="r">${priceHTML(p)}</td>
          <td class="r"><button class="add-btn" type="button" data-add="${p.id}" aria-label="Kosárba: ${esc(p.name)}">${icon("cart")}</button></td>
        </tr>`).join("");
    }

    renderPosts($("#posts"), S.POSTS);
  }

  function renderPosts(box, posts) {
    if (!box) return;
    box.innerHTML = posts.map((a) => `<article class="post">
        <div class="media"><span class="ph">${art(a.icon)}</span><img src="assets/img/cikkek/${a.id}.jpg" alt="" loading="lazy"></div>
        <div class="post-body">
          <div class="post-meta"><b>${a.tag}</b><span>${a.read} perc olvasás</span></div>
          <h3><a href="cikk.html#${a.id}">${esc(a.title)}</a></h3>
          <p>${esc(a.excerpt)}</p>
        </div>
        <span class="link-arrow">Tovább olvasom${icon("arrow")}</span>
      </article>`).join("");
  }

  /* ---------------------------------------------------------- kategória */
  function initCategory() {
    const st = { cats: new Set(), cals: new Set(), sub: null, stock: false, price: "all", sort: "default", q: "", limit: 12 };
    let view = store.get("slugshop_view", "grid");
    const PRICES = [
      { key: "all", label: "Minden ár", test: () => true },
      { key: "p1", label: "10.000 Ft alatt", test: (p) => p.price != null && p.price < 10000 },
      { key: "p2", label: "10.000 – 50.000 Ft", test: (p) => p.price != null && p.price >= 10000 && p.price < 50000 },
      { key: "p3", label: "50.000 – 200.000 Ft", test: (p) => p.price != null && p.price >= 50000 && p.price < 200000 },
      { key: "p4", label: "200.000 Ft felett", test: (p) => p.price != null && p.price >= 200000 }
    ];
    const grid = $("#grid"), filters = $("#filters");

    function readHash() {
      st.cats.clear(); st.cals.clear(); st.sub = null; st.q = ""; st.limit = 12;
      decodeURIComponent(subHash().slice(1)).split("~").forEach((tok) => {
        if (tok.startsWith("grp-")) (S.GROUPS[tok.slice(4)] || []).forEach((c) => st.cats.add(c));
        else if (tok.startsWith("kat-") && catOf(tok.slice(4))) st.cats.add(tok.slice(4));
        else if (tok.startsWith("kal-")) st.cals.add(tok.slice(4));
        else if (tok.startsWith("sub-")) st.sub = tok.slice(4);
        else if (tok.startsWith("q-")) st.q = tok.slice(2);
      });
    }

    const base = () => S.PRODUCTS.filter((p) =>
      (!st.q || searchProducts(st.q).includes(p)) &&
      (!st.sub || slugify(p.sub) === st.sub));
    function results() {
      let list = base().filter((p) =>
        (!st.cats.size || st.cats.has(p.cat)) &&
        (!st.cals.size || (p.cal || []).some((c) => st.cals.has(c))) &&
        (!st.stock || p.stock === true) &&
        PRICES.find((x) => x.key === st.price).test(p));
      const pr = (p) => p.price == null ? Infinity : p.price;
      if (st.sort === "asc") list = list.slice().sort((a, b) => pr(a) - pr(b));
      if (st.sort === "desc") list = list.slice().sort((a, b) => (b.price == null ? -1 : b.price) - (a.price == null ? -1 : a.price));
      if (st.sort === "name") list = list.slice().sort((a, b) => a.name.localeCompare(b.name, "hu"));
      return list;
    }

    function renderFilters() {
      const b = base();
      const catRows = S.CATS.map((c) => `<label class="check"><span class="lbl"><input type="checkbox" data-fcat="${c.key}"${st.cats.has(c.key) ? " checked" : ""}> ${c.name}</span><span class="cnt">${b.filter((p) => p.cat === c.key).length}</span></label>`).join("");
      const calBtns = S.CALIBERS.map((c) => `<button type="button" data-fcal="${c.key}" aria-pressed="${st.cals.has(c.key)}" title="${c.mm}">${c.label}</button>`).join("");
      const priceRows = PRICES.map((p) => `<label class="check"><span class="lbl"><input type="radio" name="fprice" data-fprice="${p.key}"${st.price === p.key ? " checked" : ""}> ${p.label}</span></label>`).join("");
      filters.innerHTML = `
        <div class="filters-head"><h2>Szűrők</h2><button class="icon-btn" type="button" data-close aria-label="Szűrők bezárása">${icon("close")}</button></div>
        <div class="filter-group"><h3>Kategória</h3>${catRows}</div>
        <div class="filter-group"><h3>Kaliber</h3><div class="cal-grid">${calBtns}</div></div>
        <div class="filter-group"><h3>Ár</h3>${priceRows}</div>
        <div class="filter-group"><label class="toggle">Csak raktáron lévő<input type="checkbox" data-fstock${st.stock ? " checked" : ""}></label></div>
        <button class="btn btn--block filter-apply" type="button" data-close style="margin-top:12px">Találatok mutatása</button>`;
    }

    function renderHead(count) {
      let title = "Webshop", desc = "PCP légfegyverek, ZAN slugok, JSB diabolók, gyári FX alkatrészek és kiegészítők.";
      const keys = [...st.cats];
      const grpKey = Object.keys(S.GROUPS).find((g) => S.GROUPS[g].length === keys.length && S.GROUPS[g].every((k) => st.cats.has(k)));
      if (st.q) { title = `Keresés`; desc = `Találatok erre: „${st.q}”`; }
      else if (keys.length === 1) { const c = catOf(keys[0]); title = c.name; desc = c.desc; }
      else if (grpKey === "fx") { title = "FX fegyverek és kiegészítők"; desc = "FX Airguns PCP légfegyverek, tárak, csövek, céltávcsövek, kronográfok és töltés."; }
      else if (grpKey === "zan") { title = "ZAN slugok és diabolók"; desc = catOf("zan").desc; }
      if (st.sub) { const s = S.PRODUCTS.find((p) => slugify(p.sub) === st.sub); if (s) title = s.sub; }
      if (st.cals.size === 1 && !st.q) { const c = S.CALIBERS.find((x) => st.cals.has(x.key)); title = `${title} · ${c.label}`; }
      $("#cat-title").textContent = title;
      $("#cat-desc").textContent = desc;
      $("#cat-crumb").textContent = title;
      document.title = `${title} – Slugshop`;
    }

    function renderChips() {
      const chips = [];
      st.cats.forEach((k) => chips.push({ label: catOf(k).name, rm: () => st.cats.delete(k) }));
      st.cals.forEach((k) => chips.push({ label: `Kaliber ${calLabel(k)}`, rm: () => st.cals.delete(k) }));
      if (st.sub) { const s = S.PRODUCTS.find((p) => slugify(p.sub) === st.sub); chips.push({ label: s ? s.sub : st.sub, rm: () => { st.sub = null; } }); }
      if (st.q) chips.push({ label: `„${st.q}”`, rm: () => { st.q = ""; } });
      if (st.price !== "all") chips.push({ label: PRICES.find((p) => p.key === st.price).label, rm: () => { st.price = "all"; } });
      if (st.stock) chips.push({ label: "Raktáron", rm: () => { st.stock = false; } });
      const box = $("#active-filters");
      box.innerHTML = chips.map((c, i) => `<button class="af-chip" type="button" data-chip="${i}">${esc(c.label)}${icon("close")}</button>`).join("") + (chips.length > 1 ? '<button class="af-clear" type="button" data-chip-clear>Összes törlése</button>' : "");
      box.onclick = (e) => {
        const c = e.target.closest("[data-chip]");
        if (c) { chips[+c.dataset.chip].rm(); update(); }
        if (e.target.closest("[data-chip-clear]")) { st.cats.clear(); st.cals.clear(); st.sub = null; st.q = ""; st.price = "all"; st.stock = false; update(); }
      };
    }

    function update() {
      const list = results();
      renderHead(list.length);
      renderFilters();
      renderChips();
      $("#count").innerHTML = `<b>${list.length}</b> termék`;
      grid.classList.toggle("is-list", view === "list");
      $$("[data-view]").forEach((b) => b.setAttribute("aria-pressed", String(b.dataset.view === view)));
      if (!list.length) {
        grid.innerHTML = `<div class="empty" style="grid-column:1/-1">${icon("target")}<h3>Nincs találat</h3><p>Ezekkel a szűrőkkel nem találtunk terméket. Lazíts a szűrésen, vagy kérdezz minket: +36 30 677 7836.</p><button class="btn btn--sm" type="button" data-reset>Szűrők törlése</button></div>`;
        $("#more").hidden = true;
        return;
      }
      grid.innerHTML = list.slice(0, st.limit).map(cardHTML).join("");
      const m = $("#more");
      m.hidden = list.length <= st.limit;
      m.querySelector("span").textContent = `További ${list.length - st.limit} termék`;
    }

    filters.addEventListener("change", (e) => {
      const t = e.target;
      if (t.dataset.fcat) { t.checked ? st.cats.add(t.dataset.fcat) : st.cats.delete(t.dataset.fcat); }
      if (t.dataset.fprice) st.price = t.dataset.fprice;
      if (t.hasAttribute("data-fstock")) st.stock = t.checked;
      st.limit = 12; update();
    });
    filters.addEventListener("click", (e) => {
      const b = e.target.closest("[data-fcal]"); if (!b) return;
      const k = b.dataset.fcal; st.cals.has(k) ? st.cals.delete(k) : st.cals.add(k);
      st.limit = 12; update();
    });
    $("#sort").addEventListener("change", (e) => { st.sort = e.target.value; update(); });
    $$("[data-view]").forEach((b) => b.addEventListener("click", () => { view = b.dataset.view; store.set("slugshop_view", view); update(); }));
    $("#more").addEventListener("click", () => { st.limit += 12; update(); });
    $("#filter-open").addEventListener("click", () => openLayer("filters"));
    grid.addEventListener("click", (e) => { if (e.target.closest("[data-reset]")) { st.cats.clear(); st.cals.clear(); st.sub = null; st.q = ""; st.price = "all"; st.stock = false; update(); } });
    hashHandler = () => { readHash(); update(); window.scrollTo({ top: 0 }); };
    readHash(); update();
  }

  /* ---------------------------------------------------------- termékoldal */
  function describe(p) {
    const spec = p.specs || {};
    switch (p.cat) {
      case "zan": return `<p>A <strong>ZAN Projectiles</strong> hollow point slugjai PCP légfegyverekhez készülnek, nagy távolságú, precíz lövészetre. A ${esc(spec["Kaliber"] || "")} kaliberű, ${esc(spec["Tömeg"] || "")} tömegű lövedék egyenletes súlya és pontos átmérője a csoportosításnál számít igazán.</p>
        <p>A Slugshop a ZAN lövedékek <strong>kizárólagos magyarországi forgalmazója</strong>, így közvetlenül a gyártótól érkező, friss sorozatokat kapsz.</p>
        <h3>Mire figyelj választáskor?</h3>
        <ul><li>A slugot a cső furatához és csavarodásához kell választani. Slug-optimalizált cső (pl. FX STX Superior Heavy) ajánlott.</li><li>Nehezebb lövedékhez általában nagyobb nyomás és erősebb beállítás kell.</li><li>Érdemes két-három súlyt kipróbálni, és kronográffal mérni a sebesség szórását.</li></ul>
        <p>Nem vagy biztos benne, melyik illik a fegyveredhez? Hívj minket: <a href="tel:+36306777836">+36 30 677 7836</a>.</p>`;
      case "jsb": return `<p>A <strong>JSB</strong> diabolók a sportlövészet és a vadászati célú légfegyverezés egyik legelterjedtebb választása. Az egyenletes gyártási minőség miatt stabilan csoportosítanak.</p><p>Diabolót akkor érdemes választani slug helyett, ha a fegyver csöve diabolóra optimalizált, vagy kisebb távolságon lősz.</p>`;
      case "fx": return `<p>Az <strong>FX Airguns</strong> svéd gyártó PCP légfegyverei a kategória élvonalába tartoznak. Az ár a kalibertől, csőhossztól és a felszereltségtől függ, ezért egyedi ajánlatot adunk.</p><p>Az FX modelleket a légfegyverek Mercédeszeként szokás emlegetni: precíz regulátor, kiváló cső és hosszú távon is megbízható működés.</p><p><strong>Figyelem:</strong> a 7,5 joule feletti légfegyverek megvásárlása és tartása Magyarországon engedélyhez kötött. Kérdés esetén segítünk az ügyintézésben.</p>`;
      case "fx-alk": return `<p><strong>Gyári FX alkatrész</strong> cikkszám szerint. Ha nem vagy biztos benne, hogy a te fegyveredhez ez az alkatrész kell, küldd el a fegyver típusát és gyártási számát, és ellenőrizzük.</p><p>A beszerelést szervizünkben is vállaljuk.</p>`;
      default: return `<p>${esc(p.name)} – eredeti ${esc(p.brand || "")} termék a Slugshop kínálatából.</p><p>Kompatibilitással kapcsolatos kérdés esetén keress minket telefonon vagy e-mailben, és segítünk kiválasztani a megfelelő változatot.</p>`;
    }
  }
  function initProduct() {
    const box = $("#pdp");
    const render = () => {
      const id = subHash().replace(/^#p-/, "");
      const p = byId(id) || byId("zan-218-25-5gr");
      const c = catOf(p.cat);
      document.title = `${p.name} – Slugshop`;
      $("#pdp-crumbs").innerHTML = `<li><a href="index.html">Kezdőlap</a></li><li><a href="kategoria.html#kat-${c.key}">${c.name}</a></li><li aria-current="page">${esc(p.sub)}</li>`;
      const stock = p.stock === true ? '<span class="stock stock--in">Raktáron – azonnal szállítható</span>' : p.stock === false ? '<span class="stock stock--out">Jelenleg nincs készleten</span>' : '<span class="stock stock--ask">Rendelésre, egyedi ajánlat alapján</span>';
      const specs = Object.entries(p.specs || {});
      const quick = specs.slice(0, 3).map(([k, v]) => `<div><dt>${esc(k)}</dt><dd>${esc(v.length > 22 ? v.split(",")[0] : v)}</dd></div>`).join("");
      let buy;
      if (p.stock === true) buy = `${qtyHTML("pdp", 1, true).replace(/data-qty-(inc|dec|input)="pdp"/g, 'data-pdp-$1')}<button class="btn" type="button" data-add="${p.id}" data-pdp-add>${icon("cart")}Kosárba</button>`;
      else if (p.stock === false) buy = `<button class="btn btn--tan" type="button" data-notify="${p.id}">${icon("bell")}Értesítést kérek</button>`;
      else buy = `<a class="btn btn--tan" href="kapcsolat.html#ajanlat-${p.id}">${icon("mail")}Ajánlatot kérek</a><a class="btn btn--ghost" href="tel:+36306777836">${icon("phone")}Hívás</a>`;
      const perks = [
        ["shield", p.cat === "fx-alk" ? "Gyári FX alkatrész, eredeti csomagolásban" : "Eredeti, hivatalos forgalmazói termék"],
        ["truck", "Futárral házhoz vagy személyes átvétel Jászberényben"],
        ["card", "Biztonságos fizetés: Viva Wallet, Barion, átutalás"],
        ["phone", "Szakmai tanácsadás: +36 30 677 7836"]
      ];
      const kv = [["Cikkszám", p.sku || "—"], ["Márka", p.brand || "—"], ["Kategória", p.sub]].concat(specs);
      const related = S.PRODUCTS.filter((x) => x.id !== p.id && x.cat === p.cat).concat(S.NEW_ORDER.map(byId).filter((x) => x.id !== p.id && x.cat !== p.cat)).slice(0, 4);

      box.innerHTML = `<div class="pdp">
        <div class="pdp-gallery">
          <div class="pdp-main">${mediaHTML(p).replace('loading="lazy"', "")}<span class="reticle-corners"></span><span class="card-badges">${badgeHTML(p)}</span><span class="scale"><span>0</span><span>|</span><span>|</span><span>|</span><span>|</span><span>${p.cal && p.cal[0] ? calLabel(p.cal[0]) : "SLG"}</span></span></div>
        </div>
        <div class="pdp-info">
          <div style="display:grid;gap:14px">
            <span class="eyebrow">${esc(p.sub)}</span>
            <h1>${esc(p.name)}</h1>
            <div class="pdp-sku">${p.sku ? `<span>Cikkszám <b>${esc(p.sku)}</b></span>` : ""}${p.brand ? `<span>Márka <b>${esc(p.brand)}</b></span>` : ""}${(p.cal || []).map((k) => `<span class="chip chip--tan">${calLabel(k)}</span>`).join("")}</div>
          </div>
          <div class="pdp-price">${priceHTML(p)}${p.price != null ? '<span class="vat">Bruttó ár, ÁFA-val</span>' : ""}</div>
          ${stock}
          ${quick ? `<dl class="pdp-quick" style="margin:0">${quick}</dl>` : ""}
          <div class="pdp-buy">${buy}</div>
          <ul class="pdp-perks">${perks.map(([i, t]) => `<li>${icon(i)}${t}</li>`).join("")}</ul>
        </div>
      </div>
      <div class="pdp-tabs">
        <div role="tablist" aria-label="Termékinformáció">
          <button role="tab" type="button" id="tab-d" aria-controls="panel-d" aria-selected="true">Leírás</button>
          <button role="tab" type="button" id="tab-s" aria-controls="panel-s" aria-selected="false" tabindex="-1">Specifikáció</button>
          <button role="tab" type="button" id="tab-sz" aria-controls="panel-sz" aria-selected="false" tabindex="-1">Szállítás és fizetés</button>
        </div>
        <div role="tabpanel" id="panel-d" aria-labelledby="tab-d" class="prose">${describe(p)}</div>
        <div role="tabpanel" id="panel-s" aria-labelledby="tab-s" hidden><table class="kv"><tbody>${kv.map(([k, v]) => `<tr><th scope="row">${esc(k)}</th><td>${esc(v)}</td></tr>`).join("")}</tbody></table></div>
        <div role="tabpanel" id="panel-sz" aria-labelledby="tab-sz" class="prose" hidden>
          <h3>Szállítás</h3><ul>${S.SETTINGS.shipping.map((s) => `<li><strong>${s.name}</strong> – ${s.note}${s.price ? `, <span data-money="${s.price}">${moneyText(s.price)}</span>` : ", díjmentes"}</li>`).join("")}</ul>
          <h3>Fizetés</h3><ul>${S.SETTINGS.payment.map((s) => `<li><strong>${s.name}</strong> – ${s.note}</li>`).join("")}</ul>
          <p>Engedélyköteles légfegyver csak a szükséges engedély bemutatása után adható át.</p>
        </div>
      </div>
      <section class="section" style="padding-top:24px">
        <div class="section-head"><div><span class="eyebrow">Ajánljuk még</span><h2>Kapcsolódó termékek</h2></div><a class="link-arrow" href="kategoria.html#kat-${c.key}">${c.name}${icon("arrow")}</a></div>
        <div class="products">${related.map(cardHTML).join("")}</div>
      </section>`;

      // tabok
      const tabs = $$("[role=tab]", box);
      const sel = (t) => { tabs.forEach((x) => { const on = x === t; x.setAttribute("aria-selected", String(on)); x.tabIndex = on ? 0 : -1; $("#" + x.getAttribute("aria-controls")).hidden = !on; }); };
      tabs.forEach((t, i) => {
        t.addEventListener("click", () => sel(t));
        t.addEventListener("keydown", (e) => { if (e.key === "ArrowRight" || e.key === "ArrowLeft") { const n = tabs[(i + (e.key === "ArrowRight" ? 1 : -1) + tabs.length) % tabs.length]; sel(n); n.focus(); } });
      });
      // pdp mennyiség
      const qi = $("[data-pdp-input]", box);
      if (qi) {
        qi.setAttribute("data-pdp-qty", "");
        $("[data-pdp-inc]", box).addEventListener("click", () => { qi.value = Math.min(99, (+qi.value || 1) + 1); });
        $("[data-pdp-dec]", box).addEventListener("click", () => { qi.value = Math.max(1, (+qi.value || 1) - 1); });
      }
    };
    hashHandler = () => { render(); window.scrollTo({ top: 0 }); };
    render();
  }

  /* ---------------------------------------------------------- kosár oldal */
  function initCart() {
    const box = $("#cart-page");
    const render = () => {
      if (!cart.length) {
        box.innerHTML = `<div class="empty" style="padding-block:80px 120px">${icon("cart")}<h3>A kosarad üres</h3><p>Válogass a ZAN slugok, FX kiegészítők és alkatrészek között.</p><a class="btn" href="kategoria.html">Irány a webshop${icon("arrow")}</a></div>`;
        return;
      }
      box.innerHTML = `<div class="cart-layout">
        <div>
          <div class="cart-lines">${cart.map((l) => { const p = byId(l.id); return `<div class="cart-line">${mediaHTML(p)}
            <div><h3><a href="termek.html#p-${p.id}">${esc(p.name)}</a></h3><p class="unit">${esc(p.sub)} · egységár <span data-money="${p.price}">${moneyText(p.price)}</span></p></div>
            ${qtyHTML(p.id, l.qty)}
            <span class="line-total">${moneyText(p.price * l.qty)}</span>
            <button class="remove-btn" type="button" data-remove="${p.id}" aria-label="Eltávolítás">${icon("trash")}</button></div>`; }).join("")}</div>
          <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-top:20px"><a class="link-arrow" href="kategoria.html" style="flex-direction:row-reverse">Vásárlás folytatása${icon("arrow")}</a></div>
        </div>
        <aside class="panel summary">
          <h2>Összesítő</h2>
          <div class="row-between"><span class="lbl">Részösszeg (${cartCount()} db)</span><span class="val">${moneyText(cartSub())}</span></div>
          <div class="row-between"><span class="lbl">Szállítás</span><span class="val" style="font-weight:400;color:var(--muted);font-size:var(--t-sm)">a pénztárban</span></div>
          <hr>
          <form class="coupon" id="coupon"><label class="sr-only" for="coupon-code">Kuponkód</label><input class="input" id="coupon-code" placeholder="Kuponkód"><button class="btn btn--ghost btn--sm" type="submit">Beváltás</button></form>
          <p class="hint" id="coupon-msg" hidden></p>
          <hr>
          <div class="row-between row-between--total"><span class="lbl">Összesen</span><span class="val">${moneyText(cartSub())}</span></div>
          <a class="btn btn--block" href="penztar.html">Tovább a pénztárhoz${icon("arrow")}</a>
          <p class="summary-note">${icon("lock").replace("<svg", '<svg style="width:14px;height:14px;display:inline;vertical-align:-2px;margin-right:6px"')}Biztonságos fizetés Viva Wallet vagy Barion rendszerén keresztül.</p>
        </aside>
      </div>`;
      $("#coupon").addEventListener("submit", (e) => {
        e.preventDefault();
        const m = $("#coupon-msg"); m.hidden = false;
        m.style.color = "var(--red)";
        m.textContent = $("#coupon-code").value.trim() ? "Ez a kuponkód nem érvényes. Ellenőrizd az elírást, vagy kérdezz minket." : "Írd be a kuponkódot.";
      });
    };
    onDoc("cart:change", render);
    onDoc("currency:change", render);
    render();
  }

  /* ---------------------------------------------------------- pénztár */
  function initCheckout() {
    const box = $("#checkout");
    if (!cart.length) {
      box.innerHTML = `<div class="empty" style="padding-block:80px 120px">${icon("cart")}<h3>Nincs mit kifizetni</h3><p>A kosarad üres, előbb tegyél bele néhány terméket.</p><a class="btn" href="kategoria.html">Irány a webshop${icon("arrow")}</a></div>`;
      return;
    }
    const ship = S.SETTINGS.shipping, pay = S.SETTINGS.payment;
    box.innerHTML = `<form class="co-layout" id="co-form" novalidate>
      <div>
        <div class="co-block"><h2><span>1</span>Kapcsolattartó</h2>
          <div class="form-row"><div class="field"><label for="co-name">Teljes név <span class="req">*</span></label><input class="input" id="co-name" required autocomplete="name"></div>
          <div class="field"><label for="co-email">E-mail <span class="req">*</span></label><input class="input" id="co-email" type="email" required autocomplete="email"></div></div>
          <div class="form-row"><div class="field"><label for="co-phone">Telefon <span class="req">*</span></label><input class="input" id="co-phone" required data-phone inputmode="tel" autocomplete="tel" placeholder="+36 30 123 4567"></div><div></div></div>
        </div>
        <div class="co-block"><h2><span>2</span>Szállítás</h2>
          <div class="opt-list">${ship.map((s, i) => `<label class="opt"><input type="radio" name="ship" value="${s.id}"${i === 0 ? " checked" : ""}><span><b>${s.name}</b><small>${s.note}</small></span><span class="opt-price">${s.price ? `<span data-money="${s.price}">${moneyText(s.price)}</span>` : "Díjmentes"}</span></label>`).join("")}</div>
          <div id="addr" style="display:grid;gap:16px">
            <div class="form-row"><div class="field"><label for="co-zip">Irányítószám <span class="req">*</span></label><input class="input" id="co-zip" required inputmode="numeric" maxlength="4" autocomplete="postal-code"></div>
            <div class="field"><label for="co-city">Település <span class="req">*</span></label><input class="input" id="co-city" required autocomplete="address-level2"></div></div>
            <div class="field"><label for="co-street">Utca, házszám <span class="req">*</span></label><input class="input" id="co-street" required autocomplete="street-address"></div>
          </div>
          <label class="check"><input type="checkbox" id="co-company"> Cégként vásárolok (számla cégnévre)</label>
          <div class="form-row" id="company" hidden><div class="field"><label for="co-cname">Cégnév <span class="req">*</span></label><input class="input" id="co-cname" required></div><div class="field"><label for="co-tax">Adószám <span class="req">*</span></label><input class="input" id="co-tax" required placeholder="12345678-1-12"></div></div>
        </div>
        <div class="co-block"><h2><span>3</span>Fizetés</h2>
          <div class="opt-list">${pay.map((s, i) => `<label class="opt"><input type="radio" name="pay" value="${s.id}"${i === 0 ? " checked" : ""}><span><b>${s.name}</b><small>${s.note}</small></span><span class="opt-price">${icon(s.id === "utalas" ? "building" : "card").replace("<svg", '<svg style="width:20px;height:20px;color:var(--tan)"')}</span></label>`).join("")}</div>
        </div>
        <div class="co-block"><h2><span>4</span>Megjegyzés</h2>
          <div class="field"><label for="co-note">Megjegyzés a rendeléshez</label><textarea class="textarea" id="co-note" placeholder="Pl. fegyvertípus a kompatibilitás ellenőrzéséhez, átvételi időpont…" style="min-height:100px"></textarea></div>
        </div>
      </div>
      <aside class="panel summary">
        <h2>Rendelésed</h2>
        <div>${cart.map((l) => { const p = byId(l.id); return `<div class="mini-item" style="grid-template-columns:56px 1fr auto">${mediaHTML(p).replace('class="media"', 'class="media" style="width:56px;height:56px"')}<div><h3>${esc(p.name)}</h3><p class="hint mono">${l.qty} db</p></div><span class="mini-price">${moneyText(p.price * l.qty)}</span></div>`; }).join("")}</div>
        <div class="row-between"><span class="lbl">Részösszeg</span><span class="val">${moneyText(cartSub())}</span></div>
        <div class="row-between"><span class="lbl">Szállítás</span><span class="val" id="co-ship"></span></div>
        <hr>
        <div class="row-between row-between--total"><span class="lbl">Fizetendő</span><span class="val" id="co-total"></span></div>
        <label class="check"><input type="checkbox" id="co-aszf" required> <span>Elolvastam és elfogadom az <a href="#aszf">ÁSZF</a>-et és az <a href="#adatvedelem">adatvédelmi nyilatkozatot</a>. <span class="req" style="color:var(--tan)">*</span></span></label>
        <label class="check"><input type="checkbox" id="co-news"> <span>Kérek értesítést új ZAN szállítmányokról.</span></label>
        <button class="btn btn--block" type="submit">${icon("lock")}Megrendelés elküldése</button>
        <p class="summary-note">A megrendelés fizetési kötelezettséggel jár. Engedélyköteles terméket csak az engedély bemutatása után adunk át.</p>
      </aside>
    </form>`;
    const form = $("#co-form");
    $$("[data-phone]", form).forEach(phoneMask);
    const sync = () => {
      const s = ship.find((x) => x.id === form.ship.value);
      $("#addr").hidden = s.id === "szemelyes";
      $("#co-ship").textContent = s.price ? moneyText(s.price) : "Díjmentes";
      $("#co-total").textContent = moneyText(cartSub() + s.price);
    };
    form.addEventListener("change", (e) => {
      if (e.target.name === "ship") sync();
      if (e.target.id === "co-company") $("#company").hidden = !e.target.checked;
    });
    sync();
    onDoc("currency:change", sync);
    form.addEventListener("submit", (e) => {
      e.preventDefault();
      if (!validateForm(form)) return;
      const no = "SLG-" + new Date().toISOString().slice(2, 10).replace(/-/g, "") + "-" + String(Math.floor(Math.random() * 9000) + 1000);
      cart = []; saveCart();
      $$(".checkout-steps li").forEach((li) => { li.className = "is-done"; });
      box.innerHTML = `<div class="order-done">${icon("check")}<span class="eyebrow">Rendelés rögzítve</span><h1>Köszönjük!</h1>
        <p>A rendelésedet megkaptuk. A visszaigazolást e-mailben küldjük, és a feladásról is értesítünk.</p>
        <span class="order-no">${no}</span>
        <div class="hero-cta" style="justify-content:center"><a class="btn" href="index.html">Vissza a főoldalra</a><a class="btn btn--ghost" href="kategoria.html">Tovább vásárolok</a></div></div>`;
      window.scrollTo({ top: 0 });
    });
  }

  /* ---------------------------------------------------------- kapcsolat */
  function initContact() {
    const m = subHash().match(/^#ajanlat-(.+)$/);
    if (!m) return;
    const p = byId(m[1]);
    const subj = $("#ct-subject"), msg = $("#ct-msg");
    if (subj) subj.value = "ajanlat";
    if (p && msg) msg.value = `Ajánlatot szeretnék kérni a következő termékre: ${p.name}.\nKaliber / kiépítés: `;
    const f = $("#contact-form"); if (f) setTimeout(() => f.scrollIntoView({ behavior: reduceMotion ? "auto" : "smooth", block: "start" }), 100);
  }

  /* ---------------------------------------------------------- cikkek */
  function initArticles() { renderPosts($("#posts"), S.POSTS); }
  function initArticle() {
    const show = () => {
      const id = subHash().slice(1) || "fx-leopard";
      const arts = $$("[data-article]");
      const hit = arts.some((a) => a.dataset.article === id);
      arts.forEach((a, i) => { a.hidden = hit ? a.dataset.article !== id : i !== 0; });
      const h1 = $("[data-article]:not([hidden]) h1");
      $("#art-crumb").textContent = h1.textContent;
      document.title = h1.textContent + " – Slugshop";
    };
    hashHandler = () => { show(); window.scrollTo({ top: 0 }); };
    show();
  }

  /* ==================================================================
     INDÍTÁS
     ================================================================== */
  const PAGES = { home: initHome, category: initCategory, product: initProduct, cart: initCart, checkout: initCheckout, contact: initContact, articles: initArticles, article: initArticle };
  function enterPage(page) {
    cleanups.splice(0).forEach((fn) => fn());
    hashHandler = null;
    document.body.dataset.page = page;
    $$(".nav-link").forEach((a) => { if (a.dataset.navPage === page) a.setAttribute("aria-current", "page"); else a.removeAttribute("aria-current"); });
    if (PAGES[page]) PAGES[page]();
    initForms();
    refreshPrices();
  }

  /* egyfájlos mód: oldalváltás sablonból */
  let curFile = null;
  function bundleFile() { const h = location.hash.slice(1); const f = h.split("/")[0]; return $(`template[data-file="${f}"]`) ? f : (curFile ? null : "index"); }
  function showFile(file) {
    const t = $(`template[data-file="${file}"]`); if (!t) return false;
    curFile = file;
    $("#main").innerHTML = t.innerHTML;
    document.title = t.dataset.title;
    closeLayers(true);
    enterPage(t.dataset.page);
    window.scrollTo({ top: 0 });
    return true;
  }
  function bindBundle() {
    document.addEventListener("click", (e) => {
      const a = e.target.closest("a[href]");
      if (!a || e.defaultPrevented || a.target === "_blank" || e.metaKey || e.ctrlKey) return;
      const href = a.getAttribute("href");
      const b = toBundle(href);
      if (b) { e.preventDefault(); if (location.hash.slice(1) === b) { const f = b.split("/")[0]; if (f !== curFile) showFile(f); else window.scrollTo({ top: 0 }); } else location.hash = b; return; }
      if (/^#[\w-]+$/.test(href) && !$(`template[data-file="${href.slice(1)}"]`)) {
        e.preventDefault();
        const el = document.getElementById(href.slice(1));
        if (el) el.scrollIntoView({ behavior: reduceMotion ? "auto" : "smooth" });
      }
    });
  }

  function boot() {
    const page = document.body.dataset.page || "";
    const h = $("#site-header"); if (h) h.outerHTML = headerHTML(page);
    const f = $("#site-footer"); if (f) f.outerHTML = footerHTML();
    document.body.insertAdjacentHTML("beforeend", layersHTML());
    if (!store.get("slugshop_cookie", null)) document.body.insertAdjacentHTML("beforeend", cookieHTML());
    bindGlobal();
    window.addEventListener("hashchange", () => {
      if (BUNDLE) { const f = bundleFile(); if (f && f !== curFile) { showFile(f); return; } }
      if (hashHandler) hashHandler();
    });
    if (BUNDLE) { bindBundle(); showFile(bundleFile() || "index"); }
    else enterPage(page);
  }
  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", boot); else boot();

  // más szkriptek számára
  S.ui = { icon, art, cardHTML, openLayer, toast };
})();
