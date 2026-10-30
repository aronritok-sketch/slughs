# JUTA-kapcsolat (WooCommerce)

A Mandala `/juta` szkriptjei alapján, a Slugshophoz. A web gyökerébe, `/juta/` mappába kell másolni.

1. `config.sample.php` → `config.php`, kitöltve (adatbázis, `prefix`, `web_key`). A `config.php` a repóba nem kerül.
2. Próba: `php raw_sync.php --dry` és `php elad.php --dry` (vagy böngészőből `…/juta/raw_sync.php?key=…&dry=1`).
   A `raw_sync` minden futáskor elkészíti a párosító táblát (alapból a web gyökerén kívül: `../juta-parositas.csv`): egyezik / csak a JUTA-ban / csak a webshopban / többszörös.
3. **Akciós ár:** a `sale_column` alapból üres. Az első próbánál nézd meg a `keszlet.csv`-ben, mi az `egysegar2`: ha a
   JUTA akciós ára, állítsd `'egysegar2'`-re. Ha nagyker ár (mint a Mandalánál), maradjon üresen.
4. Cron, 30 percenként: stagingen CSAK a `raw_sync.php`, élesen a `raw_sync.php` és az `elad.php` is.
   CLI: `php /…/juta/raw_sync.php`, vagy URL: `wget -qO- "https://…/juta/raw_sync.php?key=…"`.
5. A fizetési módok leképezése (`payment_map`, `payment_pickup_cod`) – egyeztetni a JUTA-s kollégával.

Túleladás: a `raw_sync` a nyitott (függő, feldolgozás alatti) és a teljesített, de még be nem küldött rendelések
mennyiségét levonja a JUTA készletéből. A beküldés után a JUTA-é a levonás – amíg a JUTA fel nem dolgozza az
eladás-fájlt, rövid ideig a régi készlet látszhat.

**Adatvédelem:** a tárhely nginx-e a `.csv` és `.txt` fájlokat a `.htaccess` ellenére is kiszolgálja. A `keszlet.csv`
(árak, készlet) és az `elad/*.txt` (vásárlók neve, címe!) így böngészőből letölthető lenne: az élesítés előtt a
tárhelyen (nginx-szabály / tárhelypanel) tiltani kell a `/juta/` mappát a webről – a JUTA FTP-n éri el, annak nem kell.
