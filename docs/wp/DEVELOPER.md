# Slugshop – fejlesztői leírás (WordPress-téma)

`wp-theme/slugshop` – az `iu_theme` child témája. A kinézet és a szöveg a jóváhagyott előnézeté
(`dist/slugshop-elonezet.html`, `assets/`), a működés WordPress + WooCommerce (klasszikus kosár / pénztár).
Telepítés: [`TELEPITES.md`](TELEPITES.md), JUTA: [`../JUTA.md`](../JUTA.md).

## Felépítés

```
wp-theme/slugshop/
├── functions.php            betöltő (inc/ alatt NINCS „blocks” mappa: azt az iu_theme block.json-os blokknak nézné)
├── inc/
│   ├── helpers.php          slugshop_site() (site.json + Adatok fül), ár, ikonok, morzsamenü
│   ├── routes.php           a régi URL-ek: request-szűrő, term_link / post_type_link / post_link
│   ├── theme.php            CSS/JS, a 3D csak ahol kell, iu alap-CSS csere (Mandala-minta), cím, keresés
│   ├── blocks.php           saját blokkok regisztrálása (iucb_add_block, ha nincs: register_block_type + editor.js)
│   ├── parts/chrome.php     fejléc, mobilmenü, kereső panel, lábléc
│   ├── parts/home.php       nyitóoldal szekciói (nyitókép, csempék, 3D fegyverek, Panthera, új termékek, videók …)
│   ├── parts/catalog.php    termékkártya, kategóriaoldal, termékoldal, keresés, élő kereső REST
│   ├── parts/pages.php      oldalcím, kapcsolat, jogi (Fogyasztó Barát), impresszum, letöltések, videók, cikkek, 404
│   ├── shop.php             WooCommerce: CSS csak kosár/pénztár/fiók, fragments, pénztár mezők, adószám, sablon
│   ├── forms.php            kapcsolat / értesítés / kérdés: REST + admin-post, csapda, korlát, Beküldések CPT
│   ├── consent.php          Consent Mode v2 alap, mérőkód-azonosítók, Fogyasztó Barát jelvény
│   ├── seo.php              meta, OG, Store / Product / BreadcrumbList JSON-LD, noindex
│   ├── legacy.php           régi Joomla / VirtueMart címek 301-gyel (csak 404 helyett)
│   ├── juta.php             JUTA-figyelő (Mandala), „Újra raktáron” levél, beküldendő rendelések
│   ├── setup.php            telepítő lépései (idempotens)
│   ├── admin.php            Megjelenés → Slugshop (telepítés, adatok, élesítési ellenőrzés)
│   └── cli.php              wp slugshop setup | check | juta-watch
├── assets/css               fonts → vars → iu-compat → site → shop → wp (az előnézet CSS-e + WP-illesztés)
├── assets/js                site.js (minden interakció), fx.js (videó-lapozó), fx3d.js (three.js, generált), editor.js
├── setup/data/*.json        site, categories, products, pages, posts, downloads, panthera (generált)
├── setup/content/**.html    oldalak és cikkek blokk-markupja (generált, kanonikus)
└── templates/*.html         iu_theme sablonok (fejléc, lábléc, oldal, kategória, termék, keresés, cikk, 404)
```

## URL-ek (routes.php)

A régi Joomla SEF-címek változatlanok, perjel nélkül (`permalink_structure = /%postname%`):

| Cím | Mi | Hogyan |
|---|---|---|
| `/zan-slugok`, `/fx-termekek/fx-kiegeszitok/bipodok` | termékkategória | a `slugshop_path` term-meta (a telepítő írja; új kategóriánál szülő útvonala + slug); térkép: `slugshop_cat_paths` opció, kategória-változáskor újraépül |
| `/zan-slugok/<termék>` | termék | a fő kategória (`slugshop_primary_cat` meta, különben az első) útvonala + `post_name`; más kategória alól 301 a saját címére |
| `/cikkeink/cikkek/<cikk>`, `/cikkeink/articles` | bejegyzés / kategória | `cikkek`, `articles` kategóriák |
| `/szerviz`, `/videok/fx-king` | oldal | a WordPress oldal-hierarchiája |

A `request` szűrő a WordPress saját szabályai után fut: ha az útvonal kategória, termék vagy cikk, azt adja vissza
(ha egy termék címe pontosan egy kategóriáé is, a termék nyer – a régi boltban így volt az ELR).
Ismétlődő tétel (`slugshop_dup_of` meta, rejtett): 301 a megmaradóra.
A `redirect_canonical` ezeknél ki van kapcsolva. Régi alakok (legacy.php, csak 404 esetén): `/index.php/…`, `/hu/…`,
`/component/virtuemart/…`, `-detail` / `.html` végződés, termék vagy kategória az utolsó része szerint,
`/images/virtuemart/product/<kép>` → a termék (a kép eredeti útja a melléklet `slugshop_source` metájában),
`/media/acfupload/<pdf>` → a médiatárbeli PDF; egyedi térkép: `site.json` → `legacyMap` vagy a `slugshop_legacy_map` szűrő.

## Sablonok és blokkok

- Minden sablon egy teljes szélességű `iu/section` (`slug-full`), benne egy saját, szerveroldali, önzáró blokk
  (`slugshop/header`, `…/category`, `…/product` …) – a HTML az előnézet `app.js`-ének szerkezete, PHP-ben.
- A WooCommerce a termék- és kategóriaoldalhoz a saját sablonját töltené: `template_include` → az iu_theme `index.php`-ja
  (shop.php), így az iu sablonmotor fut.
- A nyitóoldal tartalma a „Kezdőlap” oldal: szekciónként egy saját blokk, a szövegek attribútumok (a blokk oldalsávjában
  szerkeszthetők), a listák (diák, csempék, új termékek) a `site.json`-ból.
- Oldalak (Szerviz, Rólunk, cikkek …): core bekezdés / címsor / lista blokkok egy `iu/section > row > column.prose`-ban;
  ami nem fér bele (kép, táblázat, vonal), `core/html`. A kanonikus alakot a valódi szerkesztő adja (`tools/wp/canon.mjs`).
- **CSS:** az iu_theme `style.css`-e (block-styles: `p` és `h1:not(…)` szín) helyett csak a `variables.css` töltődik, és
  a sor/oszlop/csoport blokk-CSS-e ki van véve – a szerkezetet az `iu-compat.css` adja (az előnézeté). Ugyanez a
  Mandalánál. Kikapcsolás: `add_filter('slugshop_iu_base_styles', '__return_false')`.
- **3D:** `assets/js/fx3d.js` = a repó `assets/js/src/fx3d.js`-e three.js-szel (tools/3d, esbuild); a modell adatai
  `setup/data/panthera.json`, csak a nyitóoldalon töltődik (`slugshop_need_script`). WebGL nélkül a kép marad.

## WooCommerce

- Kártya és termékoldal saját HTML-lel (WooCommerce-osztályokkal), kosárba tétel: `?wc-ajax=add_to_cart` (JS), JS nélkül
  `?add-to-cart=` / POST. A fejléc számlálója a `woocommerce_add_to_cart_fragments`-ben.
- A WooCommerce CSS-e csak a kosár / pénztár / fiók oldalon (`woocommerce_enqueue_styles`), igazítás a `wp.css`-ben.
- Pénztár: vezetéknév elöl, kötelező telefon (a WC 11 `woocommerce_checkout_phone_field`-je felülírva), megye rejtve,
  „Adószám” (`_billing_tax_number` + `_billing_wc_szamlazz_adoszam` – DONTESEK 2026-10-08, Mandala).
- Kategórialista: 24 termék / oldal, `?rendezes=asc|desc|name`, `?raktaron=1`, `?oldal=N` („Továbbiak betöltése” JS-sel
  hozzáfűzi), csak a kategória saját termékei (`include_children = false`), sorrend: `menu_order` (= a régi).
- Készlet: a telepítő `manage_stock = no` + készletállapotot ír; élesítés után a JUTA (raw_sync.php) írja.

## Űrlapok (forms.php)

`POST slugshop/v1/form/{contact|notify|ask}` (REST, `rest_url()` – az iu_theme egyedi REST-előtagja miatt), JS nélkül a
kapcsolat `admin-post.php?action=slugshop_contact`. Csapda: rejtett `website` mező + legalább 3 mp a `ts` óta (a robotnak
„sikert” mond, de nem ment); IP-nkénti korlát 10 / óra (`slugshop_form_rate_limit`). Fájlok: max. 5 × 10 MB, kép / PDF /
Word, `uploads/slugshop-uzenetek/` (véletlen előtag), letöltés csak adminnak. Minden beküldés `slugshop_message` CPT
(Beküldések menü). „Értesítést kérek”: `notify_status = waiting`; amikor a termék újra raktáron van (kézi mentés:
`woocommerce_product_set_stock_status`, JUTA: a figyelő), levél és `sent`.

## Hozzájárulás, mérés

18+ kapu és sütiablak a látogató böngészőjében (`slugshop_age`, `slugshop_consent` süti). A `wp_head` elején Consent
Mode v2 alapállapot (minden `denied`); a GTM csak „Teljesítmény” vagy „Marketing” elfogadása után töltődik, a Meta és a
Barion Pixel csak „Marketing” után. A lábléc „Sütibeállítások” linkje újra megnyitja.

## Gyári FX-cikkszám, modellszűrő

A termék nevéből a gyári szám (`FX16092`, `… (FX20267C)`) a `slugshop_mpn` metába kerül. A `setup/data/fxparts.json`
a gyári robbantott ábrákból (Letöltések PDF-jei) mondja meg, mely modellek ábráján szerepel – csak pontos egyezés,
tehát igazolt adat. Ebből: a termékoldal „Gyári / Belső cikkszám” sora és „ezekben a modellekben szerepel” doboza, a
kategória modellválasztója (`?modell=fx-panthera`, ha a kategóriában van ilyen termék) és a cikkszámkereső (`?q=`).

## Telepítő (setup.php)

`slugshop_setup_steps()`: settings → categories → products (25-ös köteg) → pages → posts → menus → finish.
- Lenyomatok: oldal / cikk `slugshop_hash` (blokkszintű normalizálás, mert a szerkesztő mentése átírja a formát),
  kategória `slugshop_hash`, termék `slugshop_text_hash` (csak név + leírások). Ha a mostani tartalom ≠ a tárolt
  lenyomat, „szerkesztették” → kihagyja (`--force` felülírja).
- A meglévő termék árához, készletéhez, cikkszámához csak `--force` nyúl (JUTA).
- Képek: `slugshop_attach()` a médiacsomagból egyszer (`slugshop_media` meta = relatív út); a tartalomban a
  `{{media:articles/x.webp}}` jelölő a médiatár URL-jére cserélődik.
- Frissítéskor a hiányzó kategóriákat hozzáadja (sosem vesz el), a gyári cikkszámot (`slugshop_mpn`) és az ismétlődő
  tételt (`dupOf` → rejtve) beírja; a kategória `oldPath`-ja szerint a régi címen tárolt kategóriát viszi tovább.
- Kategória keresése útvonal szerint közvetlen SQL-lel: a WooCommerce a termékkategóriák `get_terms()`-ébe saját
  `order`-rendezést tesz, ami a `meta_key` szűrővel üres eredményt ad (duplikált kategóriák lettek volna).

## Fejlesztés

```
tools/wp/media.py              teljes méretű termékképek és PDF-ek a régi oldalról → build/media (egyszer)
tools/wp/dev-wp.sh /tmp/wp     helyi teszt-WordPress (MariaDB, WP-CLI, iu_theme a hlprv repóból, telepítővel)
(cd /tmp/wp && PHP_CLI_SERVER_WORKERS=6 php -S localhost:8890 -t /tmp/wp /tmp/slug-router.php)
tools/wp/rebuild.sh            build_data.py → canon.mjs ×2 (adatok + kanonikus blokk-markup)
python3 tools/wp/fixes.py      az ügyfél javításai az adatokon (a build_data.py is lefuttatja; idempotens)
python3 tools/wp/fxparts.py build/media/files   gyári cikkszám → modellek a gyári robbantott ábrákból (fxparts.json)
python3 tools/wp/build_theme.py   dist/slugshop-tema.zip + dist/slugshop-media.zip
BASE=http://localhost:8890 WPDIR=/tmp/wp node tests/wp/e2e.mjs
BASE=http://localhost:8890 WP="wp --allow-root --path=/tmp/wp" WPUSER=admin WPPASS=admin node tests/wp/editor-check.mjs
```

Az előnézet változásakor: `tools/extract.py` → `tools/wp/rebuild.sh` → `build_theme.py`; a CSS / JS az `assets/`-ből
kézzel kerül a témába (`assets/css/{fonts,vars,site,shop}.css`, `fx.js`, `fx3d.js`; az `iu.css` → `iu-compat.css`).

## Ellenőrizve (2026-10-15, WordPress 7.1.3, WooCommerce 11.2.1, iu_theme 0.2.2, PHP 8.4, MariaDB 10.11)

- e2e: 34 ellenőrzés hibátlan – 27 régi cím 1440 és 390 px-en (200, egy H1, nincs vízszintes görgetés, nincs JS-hiba),
  átirányítások, 404, 18+ és süti (mérőkód csak hozzájárulás után), élő kereső, rendezés, „Továbbiak betöltése”,
  kosárba tétel listából és adatlapról, rendelés végig személyes átvétellel és előre utalással a két levéllel,
  kapcsolati űrlap levéllel, „Értesítést kérek” + JUTA-szinkron utáni levél, 3D (WebGL), Fogyasztó Barát.
- Szerkesztő: 33 oldal és cikk, 44 sablon- és tartalomfájl érvényes a valódi blokkszerkesztőben.
- Telepítő: üres WordPressre zipből (téma + médiacsomag) is; a második futás semmit nem ír át.
