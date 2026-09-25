# Slugshop – új frontend (HTML prototípus)

A slugshop.hu (Joomla) teljes frontendjének újratervezése statikus HTML/CSS/JS formában. Ha ez a verzió jóvá van hagyva, ebből készül a WordPress (WooCommerce) sablon.

**Irány:** modern, sötét olíva alapszín, coyote-tan jelölések, a brand zöldje az akciógombokon. Katonai/taktikai hangulat, de visszafogottan: célkereszt-sarkok, műszaki rajz, stencil címsorok, mono „adatlap” feliratok.

## Megnyitás

Nincs build lépés. Nyisd meg az `index.html`-t böngészőben (dupla kattintás), vagy indíts egy helyi szervert:

```bash
python3 -m http.server 8000
# → http://localhost:8000
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

## Következő lépés: WordPress

Jóváhagyás után:

1. Egyedi WooCommerce sablon ebből a designból (`header.php`, `footer.php`, `front-page.php`, `archive-product.php`, `single-product.php`, kosár és pénztár sablonok).
2. A `data.js` helyére a WooCommerce termékadatbázis kerül; a kaliber tulajdonság (`pa_kaliber`) adja a kaliberszűrőt.
3. Űrlapok: Contact Form 7 vagy Fluent Forms; fizetés: Viva Wallet és Barion WooCommerce bővítmény.
4. A Joomla termékek, képek és cikkek átköltöztetése, átirányítások a régi URL-ekről.
