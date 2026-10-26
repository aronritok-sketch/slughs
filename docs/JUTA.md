# JUTA-Soft kapcsolat – a Mandala mintájára

Áron döntése (2026-10-08): a Slugshop webshopja ugyanúgy kapcsolódik a JUTA-Softhoz, mint a mandala.hu.
A Mandala leírása: `aronritok-sketch/mdn` → `docs/JUTA.md`, kód: `wp-theme/mandala/inc/features/juta.php`.

## Ahol most tartunk (2026-10-15)

- **A régi (Joomla/VirtueMart) webshopon a JUTA már fut** (CRM: slugshop projekt, „Juta fix” feladat, 2026-10-06):
  a tárhelyen (MHosting) a szerver cronja 30 percenként hívja a készlet-szinkront és a rendelés-generálást. Az eddigi
  ütemező (Cronicle) leállt, ezért került a szerver cronjába; az egyeztetés Zolival ment.
- Ezek a szkriptek a **VirtueMart táblákba** írnak és onnan olvasnak. Az új bolt WooCommerce: a szkriptek
  **WooCommerce-es változata** kell – ugyanaz, ami a Mandalánál fut (`/juta/raw_sync.php`, `/juta/elad.php`), az új
  adatbázisra és táblaelőtagra beállítva.
- A `slugshop` téma a Mandala figyelőjét kapta (`wp-theme/slugshop/inc/juta.php`): a szkripteken nem kell változtatni.

## Hogyan működik (WooCommerce)

- A kapcsolat **nem a téma része**: a web gyökerében egy `/juta/` mappa két szkripttel, a tárhely cronja hívja őket.
  - `raw_sync.php`: a JUTA készlet-exportja alapján **cikkszám (SKU) szerint** frissíti az árat, a készletet és a
    készletállapotot (`_regular_price`, `_price`, `_stock`, `_stock_status`, `_manage_stock`, `wc_product_meta_lookup`);
    ismeretlen cikkszámból piszkozat termék lesz.
  - `elad.php`: a teljesített (`wc-completed`) rendeléseket fájlba írja a JUTA-nak (`elad/eladNNNN.txt`), és
    `_elad_exported_file` jelölőt tesz rájuk. A HPOS-táblákat olvassa (a Mandalánál `md_wc_orders` … – az előtag itt más).
- A szkriptek WordPress nélkül, közvetlenül az adatbázisba írnak. A téma 10 percenként összeveti a termékek ár- és
  készletadatainak lenyomatát, és a megváltozottaknál:
  - törli az objektum-gyorsítótárat és a WooCommerce átmeneti adatait (különben a régi ár / készlet látszhat),
  - ha egy termék **újra raktárra került**, kiküldi az „Értesítést kérek” leveleket (a régi oldal funkciója),
  - feljegyzi a szinkron idejét: az admin (és a Megjelenés → Slugshop → Élesítési ellenőrzés) szól, ha 26 óránál
    régebben volt változás,
  - az új (a JUTA-ból jött, piszkozat) termékeket az adminban jelzi (kategória, kép, leírás kell, aztán közzététel).
- Nem kötelező jelzések, ha a szkriptek valaha WordPress-betöltéssel futnak: `do_action('slugshop_juta_sync_done')`,
  `slugshop_juta_orders_to_send()` / `do_action('slugshop_juta_orders_sent', $ids)`.

## Átállás a régi boltról

1. **Tesztoldalon (staging) a `raw_sync.php` mehet** (csak a staging adatbázisát írja), az **`elad.php` NEM**: a
   tesztrendelések ne kerüljenek a JUTA-ba. A staging cronjában csak a szinkron legyen.
2. A cikkszámok egyeztetése: a JUTA `barcode` mezője = a WooCommerce SKU. A régi boltból átjött cikkszámok vegyesek
   (EAN, pl. `3830079160087`; gyári FX-cikkszám, pl. `FX20710`; belső kód). Az első staging-szinkron után nézd meg:
   hány új piszkozat keletkezett (= a JUTA ismer, a bolt nem) és hány termék nem változott (= a bolt ismer, a JUTA
   nem). A régi boltban egy cikkszám kétszer szerepelt (`9990000007233`, FX2504): a második tétel rejtett, a címe az
   elsőre visz (`slugshop_dup_of`), a cikkszámot `slugshop_old_sku` metában őrzi.
3. **Fizetési módok leképezése** az `elad.php`-ben: a Viva Wallet bővítmény azonosítóját fel kell venni a kártyás
   listába (a Mandalánál a Teya `borgun` azonosítója hiányzott, ezért utalásként ment át). Az előre utalás (`bacs`),
   az utánvét (`cod`) és a személyes átvételes fizetés leképezését a JUTA-t kezelő kollégával egyeztetni.
4. **Élesítés napján:** a régi (VirtueMart-os) cron leállítása, az új szkriptek cronja be (szinkron + rendelés). Az
   éles rendelésszámok a WooCommerce saját számai; a régi rendelések nem jönnek át (ha mégis, az átvevő eszköz a
   `_slugshop_imported` metát írja, a téma pedig `regi-bolt` jelölőt tesz rájuk, hogy az `elad.php` ne küldje újra).
5. A `/juta/*.php` fájlokat **ne nyisd meg böngészőből** tesztként: minden megnyitás lefuttatja őket.

## Az ügyfél válaszai (kérdéssor, 2026-10-26)

1. A JUTA-t Móra László kezeli; Zolit nem ismeri – a JUTA technikai kapcsolattartóját Dohar Istvánnal pontosítják.
2. A **teljesített / kiküldött** rendelések mennek a JUTA-ba (az utánvétesek is; az utánvét pénzügyét külön követik).
3. Az árat **és az akciós árat** a JUTA-ban kezelik, a bolt onnan vegye át – a szinkron ármezőit élesítés előtt
   ellenőrizni (a Mandalánál a JUTA „Akciós ár” a viszonteladói ár volt!).
4. Számlázás: Számlázz.hu. A JUTA és a webshop cikkszámainak egyezése nincs ellenőrizve: élesítés előtt a két export
   tételes összevetése (a 2025-12-19-i vonalkód-kitöltés alapján).
