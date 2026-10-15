# Slugshop – telepítés (WordPress)

Lépéssor a fejlesztőnek: tárhely → téma → telepítő → beállítások → próba → élesítés. A teljes, pipálható lista:
[`docs/atadas/Slugshop_teendok_Laci.pdf`](../atadas/Slugshop_teendok_Laci.pdf) (CRM: slugshop projekt, „Élesítés 1/4–4/4”).
A belső működés: [`DEVELOPER.md`](DEVELOPER.md), a JUTA: [`../JUTA.md`](../JUTA.md).

## 1. Követelmények

- PHP 8.1+, MySQL / MariaDB (utf8mb4), HTTPS; `memory_limit` ≥ 256M, `max_execution_time` ≥ 120,
  `upload_max_filesize` és `post_max_size` ≥ 64M (a médiacsomag 35 MB – kisebb limitnél FTP).
- PHP-bővítmények: gd vagy imagick (WebP-vel), zip, intl, mbstring.
- WordPress magyarul (`hu_HU`), WooCommerce; az alap téma **`iu_theme` 0.2.0+** (hlprv GitHub-kiadás: `iu_theme-v…` →
  `iu_theme.zip`). Az `iu_*` mu-pluginek nem kötelezők (nélkülük a saját blokkok egyszerűsített szerkesztőnézettel
  jelennek meg), de ha a többi ügyféloldalon fent vannak, ide is.
- `wp-config.php`: `define('HPV_GITHUB_TOKEN', '…');` – az alap téma automatikus frissítéséhez (a tokent Áron adja).
- Levélküldés SMTP-n át (WP Mail SMTP), létező feladócímmel.

## 2. Csomagok

| Fájl | Mi ez | Hova |
|---|---|---|
| `dist/slugshop-tema.zip` | a child téma (2 MB) | Megjelenés → Témák → Téma feltöltése → Bekapcsolás |
| `dist/slugshop-media.zip` | 1042 termékkép (teljes méret, WebP), kategória- és cikkképek, 45 PDF (35 MB) | Megjelenés → Slugshop → Médiacsomag, vagy kicsomagolva `wp-content/uploads/slugshop-media/` |

Frissítéskor ugyanez: Téma feltöltése → „Csere a feltöltöttre”, utána Megjelenés → Slugshop → Telepítés (csak a
változást írja).

## 3. Telepítés

1. iu_theme feltöltése (nem kell bekapcsolni), WooCommerce bekapcsolása (a varázslót ugord át), Slugshop téma bekapcsolása.
2. **Megjelenés → Slugshop → Telepítés**: „Médiacsomag feltöltése”, majd „Telepítés indítása” (~6 perc a képek miatt;
   ha megszakad, újraindítva onnan folytatja). WP-CLI-vel:
   ```
   wp slugshop setup --media=slugshop-media.zip     # vagy --media=<kicsomagolt mappa>
   wp slugshop check                                # Élesítési ellenőrzés a parancssorban
   ```
3. Amit létrehoz: WordPress-alapok (magyar, Europe/Budapest, `/%postname%` permalink – perjel nélkül, mint a régi
   oldal), WooCommerce (Ft, ezres pont, bruttó ár, 27% ÁFA, Magyarország, vendégvásárlás, regisztráció, „Magyarország”
   szállítási zóna személyes átvétellel + kikapcsolt fix díjas futárral, előre utalás és utánvét kikapcsolva
   előkészítve), 30 kategória a régi útvonalakkal és képekkel, 516 termék (ár, készletállapot, cikkszám, rövid és hosszú
   leírás, kép + galéria, a régi sorrend), 45 PDF a médiatárban, 17 oldal (Kezdőlap, Minden termék, Kosár, Pénztár,
   Fiókom, Szerviz, Rólunk, Letöltések, Kapcsolat, ÁSZF, Adatvédelmi nyilatkozat, Impresszum, Videók + 3 videóoldal,
   Cikkeink), 15 cikk (Cikkek / Articles), fő- és láblécmenü; a WordPress és a WooCommerce mintatartalma a lomtárba.
4. **Ismételt futtatás:** ami nem változott, ahhoz nem nyúl; amit az adminban szerkesztettek, azt kihagyja (a naplóban
   „⚠ kihagyva”). Meglévő terméknél az árat, a készletet és a cikkszámot sosem írja át (azt élesítés után a JUTA
   kezeli) – csak a „Felülírás” pipával / `--force`-szal.

## 4. Beállítások a telepítő után

- **WooCommerce → Szállítás → Magyarország:** a GLS-bővítmény módjai (futár, csomagpont / automata), a díjak és az
  ingyenes határ a kérdéssorból (Z01). A telepítő fix díjas „futár” módja kikapcsolva jön (díj nélkül ingyenes lenne!) –
  ha a GLS adja a futárt, töröld.
- **Fizetések:** Viva.com Smart Checkout (Merchant ID, API-kulcs, forrás); előre utalás bankszámlaszámmal; utánvét, ha
  kell (Z02).
- **Számlázó:** ha Számlázz.hu, a bővítmény saját adószám mezőjét ne kapcsold be: a téma pénztári „Adószám” mezője a
  bővítmény kulcsába (`_billing_wc_szamlazz_adoszam`) is beírja.
- **Megjelenés → Slugshop → Adatok:** tárhelyszolgáltató (impresszum); ha kell: űrlapok címzettje, mérőkódok
  (GTM-K2DT9KZ8, Meta Pixel, Barion Pixel), Fogyasztó Barát azonosító (MB0C642A), nyitóoldali meta-leírás. Üresen a
  régi oldal adata marad.
- **JUTA:** [`../JUTA.md`](../JUTA.md) – stagingen csak a `raw_sync.php` cronja, élesen az `elad.php` is.

## 5. Ellenőrzés

- **Megjelenés → Slugshop → Élesítési ellenőrzés** (vagy `wp slugshop check`): ami a kódból ellenőrizhető.
- Automatikus próbák (helyi vagy staging teszt-WordPressen, a repó gyökeréből):
  ```
  BASE=https://uj.slugshop.hu WPDIR=/út/a/wordpresshez node tests/wp/e2e.mjs
  BASE=… WP="wp --path=…" WPUSER=… WPPASS=… node tests/wp/editor-check.mjs
  ```
  Az e2e rendelést ad le (előre utalással) és levélnaplót olvas – éles oldalon NE futtasd; helyi teszt-WordPresshez:
  `tools/wp/dev-wp.sh`.

## 6. Hibaelhárítás

| Jelenség | Ok, teendő |
|---|---|
| A kategória vagy a termék 404 | Beállítások → Közvetlen hivatkozások → Mentés. A kategória útvonala a `slugshop_path` term-metában van; új kategóriánál a szülő útvonala + slug. |
| A kategória- / termékoldalon nincs fejléc | Valami a WooCommerce sablonját tölti a témáé helyett (`template_include`) – másik WooCommerce-téma-bővítmény? |
| A telepítő elakad („HTTP 500 / 504”) | `max_execution_time`, `memory_limit` emelése; újraindítva onnan folytatja. WP-CLI-vel nincs időkorlát. |
| Termékkép hiányzik | A médiacsomag nincs a `wp-content/uploads/slugshop-media/` mappában; feltöltés után a telepítő újrafuttatva pótolja. Egy terméknek a régi oldalon sem volt képe. |
| Sötét szöveg a sötét sávban / szétcsúszott oszlopok | Az iu_theme alap-CSS-ét a téma szándékosan cseréli (`slugshop_iu_base_styles`); ha egy cache-bővítmény összevonja a CSS-t, az sorrendet ronthat. |
| A JUTA nem frissít | Élesítési ellenőrzés „JUTA-szinkron” sora; a tárhely cronja fut-e; a `/juta` beállításai az új adatbázisra mutatnak-e (JUTA.md). |
| Az ÁSZF / adatkezelés üres | A Fogyasztó Barát beágyazása domainhez kötött lehet; a tanúsítvány azonosítója (Adatok fül). |
| Nem jön levél | WP Mail SMTP próbalevél; a Beküldések menüben minden űrlap akkor is megvan. |
