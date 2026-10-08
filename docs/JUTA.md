# JUTA-Soft kapcsolat – terv (a Mandala mintájára)

Áron döntése (2026-10-08): a Slugshop webshopja ugyanúgy kapcsolódik a JUTA-Softhoz, mint a mandala.hu.
A Mandala leírása: `aronritok-sketch/mdn` → `docs/JUTA.md`, kód: `wp-theme/mandala/inc/features/juta.php`.

## Hogyan működik a Mandalánál

- A kapcsolat **nem a téma része**: a tárhelyen, a web gyökerében egy `/juta/` mappa két szkripttel, amelyeket a
  tárhely **cronja** hív.
  - `raw_sync.php`: a JUTA készlet-exportja (`keszlet.csv`) alapján **cikkszám szerint** frissíti a WooCommerce
    termékek árát, akciós árát, készletét és készletállapotát; ismeretlen cikkszámból piszkozat termék lesz.
  - `elad.php`: a teljesített webshop-rendeléseket fájlba írja a JUTA-nak (`elad/eladNNNN.txt`), és megjelöli őket,
    hogy ne menjenek újra.
- A szkriptek a sebesség miatt WordPress nélkül, közvetlenül az adatbázisba írnak. A **téma** ezért 10 percenként
  összeveti a termékek ár- és készletadatait, és a megváltozottaknál törli a gyorsítótárat (Redis), frissíti a kereső-
  és szűrőindexet, az új termékeket jóváhagyási sorba teszi (piszkozat, ellenőrzőlistával), és naplózza a szinkront
  (az őrszem szól, ha 26 óránál régebben volt változás).
- A rendelések a WooCommerce HPOS-tábláiból mennek; a régi boltból átköltöztetett rendeléseket a téma megjelöli
  (`_elad_exported_file = regi-bolt`), hogy ne küldje be újra őket.

## Slugshopnál ugyanígy

- A `slugshop` child téma kapja a Mandala `juta.php` megfelelőjét (figyelő, jóváhagyási sor az új termékekhez,
  az átköltöztetett rendelések jelölése), a Mandala kódjából átvéve.
- A termékek egyeztetésének kulcsa a **cikkszám**. A mostani VirtueMart-cikkszámok vegyesek: van EAN
  (pl. `3830079160087` – ZAN .218 25,5 grain), gyári FX-cikkszám (pl. `FX20710`) és belső kód. Importáláskor ezt kell
  a JUTA cikkszámával (`barcode`) összevetni.
- A fizetési módok leképezése az `elad.php`-ben: a Viva Wallet azonosítóját fel kell venni a kártyás listába (a Mandalánál
  a Teya `borgun` azonosítója hiányzott, ezért utalásként ment át).

## Kérdések az ügyfélnek / a JUTA-t kezelő kollégának

1. Fut-e már JUTA-Soft a Slugshopnál, és ki kezeli (a Mandalánál a beállítást Laci végzi, útmutató alapján)?
2. Van-e készlet-export (`keszlet.csv` vagy más formátum), és milyen gyakran frissül?
3. A JUTA cikkszáma megegyezik-e a webshop mostani cikkszámaival (EAN / FX-cikkszám)?
4. Milyen rendelési állapotnál menjen a rendelés a JUTA-ba (fizetve / teljesítve)?
5. Az akciós ár a JUTA-ból jön-e (a Mandalánál a JUTA „Akciós ár” a viszonteladói ár – itt valószínűleg nem kell)?
