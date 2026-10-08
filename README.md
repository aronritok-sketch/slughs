# Slugshop – slugshop.hu átköltöztetése WordPressre (ráncfelvarrás)

Slugshop Kft., Jászberény: FX légfegyverek, ZAN slugok (kizárólagos magyarországi forgalmazó), JSB, alkatrészek,
szerviz. A mostani oldal Joomla 4 + VirtueMart (vp_smart sablon).

## A döntés (Áron, 2026-10-08)

- **Nincs új design, csak ráncfelvarrás.** Az ügyfél kérése: a kinézet, a szövegek, a képek, a kategóriák és a termékek
  maradjanak, minden hasonlítson a mostani oldalra. Ugyanaz a módszer, mint a Joomla-költöztetéseknél
  (Bébiszitter Akadémia, littleminds.hu): **a design és a szöveg a régi oldalé, minden más legyen profi.**
- **Az új oldal a mi rendszerünkön készül:** WordPress + WooCommerce, `iu_theme` + `slugshop` child téma (mint a Mandala),
  űrlap- és page builder plugin nélkül.
- **JUTA-Soft kapcsolat, mint a Mandalánál:** termékek, árak, készlet a JUTA-ból; a rendelések a JUTA-ba. Terv: [docs/JUTA.md](docs/JUTA.md).
- **Első lépés: egyoldalas, kattintható HTML-előnézet** jóváhagyásra. Ez az, ami most ebben a repóban van.
- A korábbi, sötét „taktikai” újratervezés (2026-09-25) érvényét vesztette: [`archiv/taktikai-terv/`](archiv/taktikai-terv/README.md).

## Az előnézet

**Egy fájlban:** [`dist/slugshop-elonezet.html`](dist/slugshop-elonezet.html) (~12 MB). Dupla kattintással megnyílik,
internet nélkül is működik (a betűk és a képek benne vannak; a YouTube-videókhoz kell net). Ügyfélnek a CRM
ügyfélportálon át megy (Tartalom → anyag → Weboldal-előnézet), nem külső linkként.

Fejlesztéshez az `index.html` is megnyitható közvetlenül, vagy: `python3 -m http.server 8000`.

Benne van a teljes mostani oldal:

| Régi oldal | Előnézet (útvonal = a régi URL) |
|---|---|
| Kezdőlap: csúszka (5 dia), 4 csempe, bemutatkozás, két szöveges blokk, Új termékek, kapcsolati űrlap, Cikkeink | `#/` – ugyanez, a Cikkeink blokkban a legfrissebb cikkekkel |
| 30 kategória a fával, leírással, alkategória-csempékkel | `#/zan-slugok`, `#/fx-termekek/…` – oldalsáv, rendezés, „csak raktáron” |
| 516 termék (530 adatlap, a több kategóriában szereplők összevonva), ár, készlet, cikkszám, leírás, galéria | `#/zan-slugok/22-5-5mm-slugs/…` – adatlap, kosárba / értesítést kérek, kérdés |
| Szerviz, Rólunk, Letöltések (robbantott ábrák PDF-ben), Kapcsolat (cím, nyitvatartás, űrlap) | `#/szerviz`, `#/rolunk`, `#/letoltesek`, `#/kapcsolat` |
| Videók (FX Impact M3, King, DRS – 7 YouTube-videó) | `#/videok`, `#/videok/fx-king` … |
| Cikkeink: 11 magyar + 4 angol cikk | `#/cikkeink`, `#/cikkeink/cikkek/…`, `#/cikkeink/articles/…` |
| Kosár, belépés, regisztráció, ÁSZF / Impresszum / Adatvédelem | `#/kosar` → `#/penztar` → köszönő oldal; `#/bejelentkezes`, `#/regisztracio`, jogi oldalak |
| 18+ ablak a fontos tájékoztatással, sütiablak, akadálymentességi gomb | ugyanezek, a régi szöveggel |

**Látványelemek (Áron kérése, 2026-10-08):**
- **Forgó FX fegyverek** a nyitóoldalon: nagyobban, átnyúlnak a fehér és a sötét blokkon, 3D-ben forognak
  (14 rétegből kapnak vastagságot, élükre fordulva sem tűnnek el); egérrel/ujjal megforgathatók.
- **Szétszedett Panthera** (új blokk): görgetésre az FX Panthera összerakott oldalnézete szétesik, és 77 alkatrész 3D-ben a
  gyári robbantott ábra szerinti helyére repül. Forrás: a Letöltések oldal `FX_Panthera_Exploded_PartsList.pdf`-je,
  generátor: `tools/panthera.py` (alkatrészekre bontás, textúraatlasz).
- **3D videókarusszel** a Videók oldal 7 YouTube-videójával (cím és bélyegkép helyben, lejátszás youtube-nocookie-val).
- **Fix parallax** a fegyveres háttérképeken (asztali gépen; érintőképernyőn kikapcsolva).
- Teljesítmény: csak transform/opacity animáció (GPU), csak látható blokk számol, „csökkentett mozgás” beállításnál áll.

Az ügyfélnek szóló magyarázat az előnézetben: **„Előnézet – mi változott?”** gomb (`#/elonezet`).
Az űrlapok, a belépés és a rendelés semmit nem küldenek el.

### Mi változott (ráncfelvarrás)

- A menü egy sorban (a régin a „Kapcsolat” második sorba tört), ragadós fejléc, mobilon oldalsó menü.
- Mobilnézet: csempék 2×2-ben, a nyitókép szövege olvasható sötétítéssel, nincs vízszintes görgetés.
- Egyforma termékkártyák, „Elfogyott” jelölés, „Értesítést kérek!”; termékoldalon „Ebben a kategóriában még”.
- A nyitóoldali „Cikkeink” blokk eddig üres volt → a legfrissebb cikkek.
- 16 px alap betűméret (a régin 14–15 px), AA kontraszt, billentyűzetes fókusz, „Ugrás a tartalomra”, alt-szövegek.
- WebP képek, jQuery és körhinta-bővítmény nélkül.
- Csak felsorolt szövegjavítások (`tools/extract.py` → `FIXES`): Bővebbem → Bővebben, elismerves → elismerve,
  Drimline → Dreamline, bankártyás → bankkártyás, Elmultál → Elmúltál, Csak is → Csakis, Szervíz → Szerviz,
  hiányzó szóközök, „Only 10 in stock” → „Raktáron (10 db)”. A szöveg egyébként betűre a régi
  (a régi oldal vegyesen magáz és tegez – ez is marad, lásd Nyitott kérdések).

### A régi oldal hibái, amiket találtunk

| Hiba | Az új oldalon |
|---|---|
| Az **Impresszum** és az **Adatvédelmi nyilatkozat** oldal üres (csak a cím) | Impresszum a cégadatokkal; adatkezelési tájékoztató a Fogyasztó Barátból, mint az ÁSZF |
| A nyitóoldali „Cikkeink” körhinta üres | a legfrissebb cikkek |
| Az „ELR Zan slug” kategória üres, pedig az 1. dia gombja oda visz (az ELR termékek a „ZAN slugok” főkategóriában vannak) | élesítés előtt: a termékeket a kategóriába tenni, vagy a gombot a főkategóriára |
| „Teszt csomag” kategória a nyilvános menüben | rákérdezni: valódi termékcsomag vagy teszt? |
| `/tarak/fx-impact-kaliber-specifikus-egylovesu-rakodo-o-gyuruvel` a listában van, de az adatlapja nem nyílik meg | a termék adatait ellenőrizni |
| Egy termékkép sérült (`elsosegely_csomag_vadasz…`), 44 terméknek nincs leírása | képcsere / leírás pótlása (nem kötelező) |
| Angol rendszerüzenet a készletnél („Only 10 in stock”) | magyarul |

## Felépítés

```
index.html               az előnézet váza (a tartalmat az app.js rajzolja a data.js-ből)
theme/theme.json         paletta és betű (a child téma theme.json-ja)
assets/css/vars.css      tokenek, --iu-* felülírások (child téma vars.css)
assets/css/iu.css        az iu szerkezeti alap – CSAK a prototípushoz (WordPressben az iu_theme adja)
assets/css/site.css      fejléc, csúszka, sávok, lábléc, űrlapok, felugrók (child téma style.css)
assets/css/shop.css      WooCommerce klasszikus markup: lista, adatlap, kosár, pénztár (child téma shop.css)
assets/css/fonts.css     Barlow (helyi másolat) + assets/fonts/
assets/js/data.js        GENERÁLT: kategóriák, termékek, oldalak, cikkek, videók (tools/extract.py)
assets/js/app.js         útvonalak, oldalak, kosár, kereső, 18+ / süti / akadálymentesség
assets/img/              GENERÁLT: WebP képek (p = termék, c = kategória, site, art, page)
tools/fetch.py           a régi oldal letöltése a cache/ mappába (nincs a gitben)
tools/extract.py         cache → data.js + képek, szövegjavítások (FIXES)
tools/panthera.py        a szétszedett Panthera adatai (atlasz, alkatrészek) a gyári PDF-ből
assets/js/fx.js          látványelemek: forgó fegyverek, Panthera, 3D videókarusszel
tools/build-single.py    → dist/slugshop-elonezet.html
tests/smoke.mjs          minden útvonal asztalon és mobilon: JS-hiba, túlcsordulás, egy H1
tests/flow.mjs           18+, süti, kereső, kosár, pénztár, rendelés
docs/JUTA.md             JUTA-Soft terv
archiv/taktikai-terv/    a korábbi, elvetett újratervezés
```

## Újragenerálás

```bash
pip install beautifulsoup4 pillow
python3 tools/fetch.py          # a régi oldal letöltése (ami a cache/-ben van, azt nem kéri újra)
python3 tools/extract.py        # data.js + képek (~10 perc első futásra, a képletöltés miatt)
pip install pymupdf numpy scipy && python3 tools/panthera.py   # a Panthera-blokk adatai
python3 tools/build-single.py   # dist/slugshop-elonezet.html
node tests/smoke.mjs && node tests/flow.mjs
```

Ha a régi oldalon változik valami (új termék, ár), elég a `cache/` megfelelő mappáját törölni és a három lépést újrafuttatni.

## WordPress-terv (jóváhagyás után)

`iu_theme` + `slugshop` child téma, a Mandala felépítésével (`wp-theme/slugshop`, telepítő varázsló, WP-CLI):

| Előnézet | WordPress |
|---|---|
| fejléc, felső sáv, menü, lábléc | `templates/global_header.html`, `global_footer.html` (iu/menu, iu-woocommerce/search, mini-cart) |
| kezdőlap sávjai | `front_page_content.html` iu/section › iu/row › iu/column blokkokból; csúszka: `iu/image-slider`; „Új termékek”: saját blokk |
| kategóriaoldal | `tax_product_cat_content.html`: oldalsáv kategóriafa (saját blokk), `[products … class="mainquery"]` |
| termékkártya | „Loop Product” `iu_pattern` |
| adatlap | `single_product_content.html` (images, price, stock, add-to-cart, short-description, iu/content) |
| kosár, pénztár | WooCommerce klasszikus (shortcode-os) pénztár, `local_pickup` a személyes átvételhez |
| kapcsolati, szerviz-, kérdés-űrlap | `iu/form` (nincs CF7) |
| 18+ ablak, sütiablak, akadálymentesség | a child téma saját funkciói (mint a Bébiszitter Akadémiánál) |
| ÁSZF, adatkezelés | Fogyasztó Barát beágyazás (`MB0C642A`), ahogy most |
| JUTA | [docs/JUTA.md](docs/JUTA.md) |

Migráció: a VirtueMart termékei, kategóriái, képei és a cikkek importja; **az URL-ek maradnak** (termék-permalink a
kategóriautakkal), ami mégis változik, arra 301. Fizetés: Viva Wallet WooCommerce bővítmény.

## Nyitott kérdések (az ügyfélnek, a portálon kérdőívként)

1. **Szállítási díjak és módok** (futár, személyes átvétel; utánvét van-e?), Barion kell-e a Viva Wallet mellett.
2. **Megszólítás:** a régi szöveg vegyesen magáz („Vegye fel velünk a kapcsolatot”) és tegez (kategórialeírások).
   A költöztetési szabály szerint a szöveg marad – vagy egységesítsük tegezőre?
3. EUR-ár kell-e (a régi oldalon van HUF/EUR váltó); ha igen, milyen árfolyammal.
4. „Teszt csomag” kategória, üres „ELR Zan slug” kategória (fent).
5. JUTA: a kérdések a [docs/JUTA.md](docs/JUTA.md) végén.
6. Bekössük-e az űrlapokat a HelloProVision CRM-be (az Alpha Movers-nél Áron nem kérte; általános szabály még nincs).
