# Slugshop – új frontend (HTML prototípus)

A slugshop.hu (Joomla) teljes frontendjének újratervezése statikus HTML/CSS/JS formában. Ha ez a verzió jóvá van hagyva, ebből készül a WordPress (WooCommerce) sablon.

**Irány:** modern, sötét olíva alapszín, coyote-tan jelölések, a brand zöldje az akciógombokon. Katonai/taktikai hangulat, de visszafogottan: célkereszt-sarkok, műszaki rajz, stencil címsorok, mono „adatlap” feliratok.

## Megnyitás

Nincs build lépés. Nyisd meg az `index.html`-t böngészőben (dupla kattintás), vagy indíts egy helyi szervert:

```bash
python3 -m http.server 8000
# → http://localhost:8000
```

## Letölthető, egyfájlos változat

`dist/slugshop-prototipus.html` – az egész prototípus egyetlen HTML fájlban (CSS, JS, adatok beágyazva). Letöltés után dupla kattintással megnyitható, e-mailben is továbbküldhető. Az oldalak között ugyanúgy lehet kattintgatni; a cím végén a `#kategoria/…`, `#termek/…` rész választja ki az oldalt. A bal alsó „Útmutató” gomb elmagyarázza, mi van benne. (A betűtípusokhoz internet kell, nélküle tartalék betűvel jelenik meg.)

Újragenerálás a forrásfájlokból:

```bash
python3 tools/build-single.py
```

## Oldalak

| Fájl | Tartalom |
|---|---|
| `index.html` | Nyitóoldal: hero dia + ZAN műszaki rajz kaliberváltóval, bizalmi sáv, kategóriák, új termékek (szűrhető), ZAN összehasonlító tábla, rólunk, videó, cikkek, kapcsolat űrlap |
| `kategoria.html` | Webshop: kategória-, kaliber-, ár- és készletszűrő, rendezés, rács/lista nézet, aktív szűrő chipek. Mobilon a szűrő oldalsó fiókban nyílik |
| `termek.html` | Termék adatlap: ár, készlet, gyors specifikáció, mennyiség + kosárba, leírás / specifikáció / szállítás fülek, kapcsolódó termékek |
| `kosar.html` | Kosár: mennyiség, törlés, kuponmező, összesítő |
| `penztar.html` | Pénztár: kapcsolattartó, szállítás (személyes átvételnél elrejti a címet), céges számla, fizetési mód, ÁSZF, visszaigazolás |
| `kapcsolat.html` | Elérhetőségek, cégadatok, üzenetküldés fájlcsatolással |
| `szerviz.html` | Szolgáltatások, a szerviz menete, szervizbejelentő űrlap |
| `videok.html`, `letoltesek.html`, `cikkek.html`, `cikk.html`, `rolunk.html` | Tartalmi oldalak |
| `utmutato.html` | Prototípus útmutató: mi hol van, mit érdemes kipróbálni, mi minta tartalom |

Közös elemek (JS-ből kerülnek be minden oldalra): felső sáv (telefon, e-mail, HUF/EUR, közösségi linkek, belépés), futó hírsáv, ragadós fejléc kereséssel és kosárral, legördülő menük, mobil menü, kosár fiók, kereső (`/` billentyű), belépés/regisztráció ablak, „értesítést kérek” ablak, süti sáv, lábléc a cégadatokkal és az engedélyköteles termékekre vonatkozó megjegyzéssel.

A kosár, a pénznem és a süti-döntés a böngésző `localStorage`-ában marad meg, így oldalak között is működik.

## Fájlszerkezet

```
assets/
  css/style.css      design tokenek + minden komponens
  js/data.js         termékek, kategóriák, diák, beállítások
  js/app.js          fejléc/lábléc, kosár, kereső, űrlapok, oldalmodulok
  img/termekek/      termékfotók (lásd lent)
  img/cikkek/        cikkek borítóképei
```

## Képek

A feltöltött mentésből a képek nem jöttek át, ezért minden termék helyén egy vonalas rajz áll (slug, diabolo, céltávcső, tár stb.). Ha a képfájl létezik, automatikusan lecseréli a rajzot.

- Termékfotó: `assets/img/termekek/<termék-id>.jpg` – az id a `data.js`-ben van, pl. `zan-218-25-5gr.jpg`, `nexus-gen2-4-25x50.jpg`.
- Cikk borító: `assets/img/cikkek/<cikk-id>.jpg`, pl. `fx-leopard.jpg`.
- Logó: jelenleg SVG célkereszt + „SLUGSHOP” felirat az `app.js` `LOGO` konstansában; a valódi logóval egy sorban cserélhető.

## Mi valós és mi minta?

**Valós** (a jelenlegi oldalról): a 20 új termék neve, ára, kategóriája és készlete; a menüpontok; a hero szövegek; a rólunk szövegek; telefon, e-mail, közösségi linkek; cégadatok; a Fogyasztó Barát tanúsítvány; a Viva Wallet és Barion fizetés.

**Minta, élesítés előtt ellenőrizendő** (`sample: true` a `data.js`-ben, vagy megjelölve az oldalon):

- FX Impact M3 / Dreamline / Panthera / Leopard fegyverek „Ár egyeztetés alapján” jelöléssel (ár nélkül, ajánlatkérő gombbal)
- Szállítási díj (futár 2.490 Ft) és az EUR árfolyam (395) – ezt élesben a WooCommerce adja
- A cikkek szövege (a Leopard cikknek csak a bevezetője valós), a letöltések listája, a videókártyák
- A szerviz szolgáltatások és a menet leírása

## Hangnem

A vásárlóknak szóló minden szöveg **tegező** (HelloProVision-szabály, 2026-09-28): „Válaszd”, „kérj ajánlatot”, „Írj nekünk”. Új szövegnél, hibaüzenetnél, e-mail sablonnál is így maradjon.

## Következő lépés: WordPress (Infinite Unity + child téma)

Jóváhagyás után a HelloProVision szabványa szerint készül, kivétel nélkül:

1. **Téma:** `iu_theme` (Infinite Unity) + `slugshop` child téma (`style.css`: `Template: iu_theme`). A design a child téma `vars.css`-ében (`:root` változók) és a `theme.json` palettában: sötét olíva háttér, coyote-tan, brand-zöld; Big Shoulders + Archivo + JetBrains Mono. A keretrendszerhez (`themes/iu_theme`, `mu-plugins/iu_*`) nem nyúlunk, minden projektkód a child témába kerül. Nincs page builder, slider-, wishlist- és űrlap-plugin.
2. **Sablonok** a child téma `templates/` mappájában: `global_header.html` / `global_footer.html` (felső sáv, hírsáv, menü, `iu-woocommerce/search`, `mini-cart`, `account-menu`; lábléc cégadatokkal és az engedélyköteles termékek megjegyzésével), `front_page_content.html`, `single_page_content.html` (saját H1-hez), `single_product_content.html` (`iu-woocommerce` blokkok: `images`, `price`, `add-to-cart`, `stock`, `product-brand`, `product-badges`, `product-attributes`, `short-description`, `iu/title`, `iu/breadcrumbs`, `iu/content`; leírás / specifikáció / szállítás fülek `iu/tabs`-szal), `tax_product_cat_content.html` és a bolt oldal (`[products … class="mainquery"]` + `iu-woocommerce/filter`), `blog_page_content.html`, `single_post_content.html`, `search_content.html`, `404_content.html`. A termékkártya a „Loop Product” `iu_pattern`.
3. **Termékadatok:** a `data.js` helyére a WooCommerce adatbázis kerül. Kaliber = `pa_kaliber` attribútum (ebből a kaliberszűrő és a menü kaliberlistája), márka = `product_brand`. Az „Ár egyeztetés alapján” FX fegyverek ár nélküli termékek, a kosár gomb helyett ajánlatkérő gombbal (child téma szűrő). Bruttó árak, 27% ÁFA (`woocommerce_prices_include_tax = yes`).
4. **Egyedi blokkok** (`iucb_add_block`, `inc/blocks/…`): hero diavetítés + ZAN műszaki rajz kaliberváltóval, ZAN összehasonlító tábla (kosárba gombbal), bizalmi sáv, futó hírsáv, válogatott termékkarusszel (a `product-carousel` csak a legújabb 12-t mutatja), „értesítést kérek” készletfigyelő, HUF/EUR kijelzés (ha marad).
5. **Űrlapok:** kapcsolat, ajánlatkérés, szervizbejelentés és készletfigyelő az `iu/form` blokkal, űrlap-plugin nélkül (nincs CF7, Fluent Forms). A keretrendszer-hiba miatt az `email` attribútum üres marad, a küldést a child téma `iu_form_submit_{formId}` szűrője végzi (a mezők címkével, válaszcím a kitöltő e-mailje, az `iu/form-accept` ellenőrzése is ott). Ugyanez a szűrő adja át az érdeklődőt a **HelloProVision CRM-nek** a leads hídon (`hpv_leads_map()` → `hpv_leads_send()`, a `helloprovision-leads.php` mu-pluginból; a beállító varázsló telepíti). A CRM csak e-mail címmel vesz fel érdeklődőt, ezért a WordPress-változatban az e-mail mező legyen kötelező. Fájlcsatolás (fotó a fegyverről) a szűrőben, `wp_handle_upload`-dal, méret- és típusellenőrzéssel.
6. **Kosár, pénztár, fiók:** WooCommerce klasszikus (shortcode-os) kosár és pénztár. Szállítás: futár (díj a WooCommerce-ben) és `local_pickup` (Jászberény) – a blokkos „Pickup location” a klasszikus pénztárral nem működik. Fizetés: **Viva Wallet** és **Barion** WooCommerce fizetési bővítmény (fizetési kaput szabad pluginnal), plusz előre utalás (BACS). ÁSZF oldal a pénztárhoz, céges számla mezők.
7. **SEO:** JSON-LD a child témában (`inc/schema.php`): `Product` (név, SKU, márka, `Offer` HUF-ban, készlet; az ár nélküli FX fegyvereknél `Offer` nélkül) és `LocalBusiness` / `Store` (Slugshop Kft., Jászberény, Érhát utca 7., telefon, nyitvatartás ha az ügyfél megadja, `sameAs` Facebook, Instagram, YouTube). Oldalanként egy H1, canonical a szűrt listákon.
8. **Telepítés kódként** (`inc/setup.php`, verziózott, egyszer futó lépések): oldalak, menü, jogi oldalak (ÁSZF, adatkezelés, impresszum, elállás), WooCommerce beállítások (ÁFA, szállítási zónák, pénznem), „Loop Product” minta.
9. **Joomla-migráció:** termékek, képek és cikkek átköltöztetése, **301-es átirányítások** a régi Joomla URL-ekről az új WooCommerce URL-ekre (termék, kategória, cikk, letöltések; leképezési táblából, a child témában `template_redirect`-en vagy szerverszinten).
10. **Ellenőrzés élesítés előtt:** teljes rendelés (kosár → pénztár → köszönő oldal → e-mailek), minden űrlap beküldése (e-mail + CRM), mobil nézet vízszintes görgetés nélkül, 404, keresés.
