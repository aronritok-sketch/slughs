<?php
/**
 * Másold config.php néven, és töltsd ki (a config.php NEM kerül a repóba, a hozzáférés 1Passwordben: „Slugshop – JUTA”).
 */
return [
    'db_host' => 'localhost',
    'db_name' => '',
    'db_user' => '',
    'db_pass' => '',
    'prefix'  => 'slgshp_',              // a WordPress táblaelőtagja (wp-config.php: $table_prefix)

    // Böngészőből / wgettel csak ezzel a kulccsal fut: …/juta/raw_sync.php?key=… (legalább 24 karakter, véletlen)
    'web_key' => '',

    // true: semmit nem ír, csak naplóz (próbafuttatás). Ugyanez: ?dry=1 vagy php raw_sync.php --dry
    'dry' => false,

    // ----- raw_sync.php (JUTA → webshop: ár, készlet)
    'csv' => __DIR__ . '/keszlet.csv',
    'columns' => ['sku' => 'barcode', 'name' => 'megnev', 'name2' => 'megnev2', 'price' => 'egysegar', 'stock' => 'keszlet'],
    // Az akciós ár oszlopa a JUTA-exportban. ÜRESEN KI: amíg a Slugshop exportján nem ellenőriztük, hogy az
    // „egysegar2” tényleg az akciós ár (a Mandalánál a nagyker ár volt!). Ha be van állítva: ha 0-nál nagyobb és
    // kisebb a rendes árnál, az a WooCommerce akciós ára; ha üres vagy 0, a webshopban nincs akció.
    'sale_column' => '',
    // Ismeretlen vonalkódból piszkozat termék legyen-e (a Mandalánál igen). A Slugshopnál alapból nem: a párosító
    // táblában látszik (report), és László dönt róla.
    'create_drafts' => false,
    // A nyitott, a JUTA-ba még be nem küldött rendelések mennyiségét levonja a JUTA készletéből (túleladás ellen).
    'reserve_open_orders' => true,
    'reserve_statuses' => ['wc-processing', 'wc-on-hold'],

    // A párosító tábla helye: a web gyökerén KÍVÜL (a tárhely nginx-e a .csv-t a .htaccess ellenére is kiszolgálja).
    'report' => dirname(__DIR__, 2) . '/juta-parositas.csv',

    // ----- elad.php (webshop → JUTA: a teljesített rendelések)
    'export_dir' => __DIR__ . '/elad',
    'order_statuses' => ['wc-completed'],
    // fizetési mód azonosítója → JUTA fizetési mód. A „viva” kezdetű azonosítók kártyásak. Egyeztetni a JUTA-s kollégával!
    'payment_map' => ['bacs' => 'utalas', 'cod' => 'utanvet', 'cheque' => 'csekk'],
    'payment_map_card' => 'kartya',
    'payment_default' => 'utalas',
    // Személyes átvétel + utánvét (= fizetés az üzletben) – ha a JUTA-ban ez külön mód, itt add meg (pl. 'keszpenz').
    'payment_pickup_cod' => 'utanvet',
];
