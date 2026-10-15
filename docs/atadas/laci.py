"""
Laci teendőlistája a slugshop.hu új webshopjának élesítéséhez: ebből készül a laci.html (build.py) és a PDF.
(cím, hogyan) párok, fázisonként. „Z01” = az ügyfél kérdéssorának 1. fejezete (kerdesek.py, a portálon tölti ki).
A fázisok a CRM „Élesítés 1/4–4/4” feladataihoz igazodnak (slugshop projekt); ez a lista a Slugshop-specifikus „hogyan”.
"""

TITLE = "Slugshop élesítés – teendők"
INTRO = [
    "Laci, ez a teljes lista a slugshop.hu új webshopjának élesítéséig. A téma kész és tesztelt: a régi oldal minden "
    "oldala, 30 kategória, 516 termék teljes méretű képekkel, 45 letölthető PDF, 15 cikk, a menük és a WooCommerce "
    "alapbeállításai kódból települnek, egy gombnyomásra. Ide az került, amihez tárhely, admin, bővítmény, döntés vagy "
    "az ügyfél adata kell. Sorrendben haladj, a pipák mellé írd a dátumot.",
    "A Z01–Z06 jelölés az ügyfél kérdéssorára utal (a portálon tölti ki): onnan jön az adat. Ami még nem jött meg, azt "
    "hagyd nyitva, és szólj Istvánnak (projektvezető). A CRM-ben a slugshop projekt „Élesítés 1/4–4/4” feladatai a "
    "közös élesítési lista – ez a PDF mondja meg, hogyan kell őket a Slugshopnál végigvinni.",
]
DOCS = [
    ("dist/slugshop-tema.zip", "a child téma (Megjelenés → Témák → Téma feltöltése; frissítéskor „Csere a feltöltöttre”)"),
    ("dist/slugshop-media.zip", "35 MB: termék-, kategória- és cikkképek, 45 PDF (Megjelenés → Slugshop → Médiacsomag, vagy FTP)"),
    ("iu_theme.zip", "az alap téma a hlprv repó GitHub-kiadásából (iu_theme-v0.2.x)"),
    ("docs/wp/TELEPITES.md", "a telepítés lépései parancsokkal, hibaelhárítás"),
    ("docs/wp/DEVELOPER.md", "belső működés: URL-ek, blokkok, telepítő, JUTA-figyelő, tesztek"),
    ("docs/JUTA.md", "a JUTA átállás a régi VirtueMart-os szkriptekről a WooCommerce-es (Mandala) változatra"),
    ("Slugshop_kerdessor.pdf", "az ügyfél kérdéssora (Z01–Z06), a válaszok a portálon"),
    ("Megjelenés → Slugshop", "telepítés, cégadatok, mérőkódok, Élesítési ellenőrzés (magától pipál)"),
]

SECTIONS = [
    {
        "num": "00", "title": "Mielőtt hozzákezdesz", "kind": "",
        "items": [
            ("Olvasd át", "docs/wp/TELEPITES.md, docs/wp/DEVELOPER.md, docs/JUTA.md (aronritok-sketch/slughs repó, claude/wizardly-bell-40450y ág). Az előnézet, amit az ügyfél jóváhagyott: dist/slugshop-elonezet.html."),
            ("Teljes mentés a régi oldalról", "Joomla + VirtueMart (MHosting): adatbázis + a teljes webmappa, benne az /images, /media és a /juta (vagy ahol a JUTA-szkriptek vannak). Élesítés után még 3 hónapig tartsd meg."),
            ("Hozzáférések (Z05)", "Tárhely, domain/DNS, postafiók – az ügyfél a portálon, jelszókezelőn át küldi (nem e-mailben)."),
            ("JUTA-kapcsolattartó (Z03)", "Ki kezeli a JUTA-t (Zoli?). A régi boltnál most az MHosting szerver cronja hívja 30 percenként a szinkront és a rendelés-generálást (CRM: „Juta fix”)."),
        ],
    },
    {
        "num": "01", "title": "Tárhely és tesztoldal", "kind": "",
        "note": "CRM: Élesítés 2/4 – Domain és tárhely előkészítése.",
        "items": [
            ("Követelmények", "PHP 8.1+, MySQL/MariaDB (utf8mb4), HTTPS. memory_limit ≥ 256M, max_execution_time ≥ 120 (a telepítő 25-ös kötegekben dolgozik), upload_max_filesize és post_max_size ≥ 64M (a médiacsomag 35 MB; ha kevesebb, FTP-n töltsd fel). PHP: gd vagy imagick WebP-vel, zip, intl, mbstring."),
            ("Tesztoldal (staging)", "Pl. uj.slugshop.hu: friss WordPress magyarul (hu_HU). Beállítások → Olvasás: „Keresőmotorok … ne indexeljék” BEJELÖLVE, amíg nem élesítünk."),
            ("Tűzfal-próba", "A webshop REST-et és a WooCommerce AJAX-át használja (élő kereső, űrlapok, kosárba tétel): magyar és külföldi IP-ről is menjen át egy kapcsolati űrlap és egy kosárba tétel. Az iu_theme REST-előtagja egyedi (nem /wp-json/). Ha 403, a tárhelynek engedélyeznie kell."),
            ("E-mail", "Postafiók és SMTP-adat (Z05): a webshop levelei (rendelés, űrlap, „Újra raktáron”) innen mennek. SPF, DKIM, DMARC a domainen."),
        ],
    },
    {
        "num": "02", "title": "Téma és bővítmények", "kind": "",
        "items": [
            ("Infinite Unity (alap téma)", "iu_theme.zip 0.2.0+ a hlprv GitHub-kiadásából → Megjelenés → Témák → Téma feltöltése (bekapcsolni nem kell). Az iu_* mu-pluginek nélkül is működik (a saját blokkok egyszerűsített szerkesztőnézettel); ha a többi ügyféloldalon fent vannak, ide is tedd fel őket."),
            ("Automatikus frissítés", "wp-config.php: define('HPV_GITHUB_TOKEN', '…'); – a tokent Áron adja. Ezzel az alap téma magától frissül."),
            ("WooCommerce", "Telepítés és bekapcsolás; a beállító varázslót ugord át (a téma állítja be: Ft, bruttó ár, 27% ÁFA, Magyarország)."),
            ("Slugshop téma", "Megjelenés → Témák → Téma feltöltése: dist/slugshop-tema.zip → Bekapcsolás (szülő: iu_theme)."),
            ("Levélküldés", "WP Mail SMTP a bolt postafiókjával (Z05); próbalevél a saját címedre és egy Gmailre."),
            ("Viva Wallet (Z02)", "„Viva.com Smart Checkout” (a Viva hivatalos WooCommerce-bővítménye): Merchant ID, API-kulcs, forrás (source code) – először demo/teszt módban."),
            ("GLS (Z01)", "A GLS WooCommerce-bővítménye, ugyanaz, mint a Mandalánál: futár + csomagpont/automata pontválasztóval, címke és csomagkövetés. Az ügyfél GLS-ügyfélszáma kell."),
            ("Számlázó (Z02)", "Ha Számlázz.hu: „Integration for Szamlazz.hu & WooCommerce”. Az adószámot a téma a bővítmény kulcsába is beírja – a bővítmény saját adószám mezőjét NE kapcsold be (Mandala-tanulság). Billingo / JUTA-számla esetén szólj."),
            ("Semmi más", "Nincs page builder, űrlap-, slider- vagy cache-bővítmény egyeztetés nélkül. Ha cache kell: a kosár, a pénztár, a fiók és a slugshop REST-végpontjai ne jöjjenek cache-ből."),
        ],
    },
    {
        "num": "03", "title": "Telepítés", "kind": "",
        "note": "CRM: Élesítés 3/4 – Staging (FELTÖLTÉS, IMPORTÁLÁS).",
        "items": [
            ("Médiacsomag", "Megjelenés → Slugshop → Telepítés → „Médiacsomag feltöltése” (dist/slugshop-media.zip). Ha a feltöltési limit kicsi: FTP-n kicsomagolva ide: wp-content/uploads/slugshop-media/ – a lap kiírja, hány fájl van ott (1122)."),
            ("Telepítés", "Ugyanott: „Telepítés indítása”. ~6 perc (a képek miatt); a napló minden lépést kiír. Ha megszakad: indítsd újra, onnan folytatja. WP-CLI-vel: wp slugshop setup --media=slugshop-media.zip."),
            ("Eredmény", "30 kategória, 516 termék (képekkel, a régi sorrendben), 17 oldal (kosár, pénztár, fiók, jogi oldalak, videók …), 15 cikk, 45 PDF, fő- és láblécmenü, a WordPress mintatartalma lomtárban. Az egyetlen figyelmeztetés: egy ismétlődő cikkszám a régi boltból (9990000007233) – a JUTA-egyeztetésnél javítandó."),
            ("Élesítési ellenőrzés", "Megjelenés → Slugshop → Élesítési ellenőrzés: minden sor mellett a teendő. A lista a következő fázisokkal fogy el."),
            ("Szerkesztő-próba", "Nyiss meg pár oldalt és cikket a blokkszerkesztőben (Kezdőlap, Szerviz, egy cikk): nem lehet „váratlan vagy érvénytelen tartalom”. Ha mégis, NE mentsd – küldd el az oldal nevét."),
        ],
    },
    {
        "num": "04", "title": "Szállítás, fizetés, számlázás", "kind": "",
        "note": "CRM: Élesítés 3/4 – SZÁLLÍTÁS, FIZETÉS, SZÁMLÁZÁS. A díjak és a módok az ügyfél kérdéssorából (Z01, Z02) jönnek.",
        "items": [
            ("Szállítási zóna", "WooCommerce → Beállítások → Szállítás → Magyarország: a GLS-módok (futár, csomagpont) a díjtábla szerint; az ingyenes szállítás határa (Z01). A telepítő személyes átvételt (Jászberény, ingyenes, be) és egy kikapcsolt „futár” fix díjas módot hoz létre – ha a GLS-bővítmény adja a futárt, a fix díjasat töröld."),
            ("Viva Wallet", "Teszt módban egy kártyás próbarendelés (a Viva tesztkártyájával), aztán éles kulcsok. A visszatérési (siker / hiba) oldal a WooCommerce köszönő oldala (/penztar/rendeles-fogadva)."),
            ("Előre utalás / utánvét (Z02)", "WooCommerce → Fizetések: a telepítő kikapcsolva hozta létre őket. Bankszámlaszám a BACS beállításba; utánvét díja, ha van."),
            ("Számla", "Egy magánszemélyes és egy céges (adószámos) próbarendelés: a számlán ott az adószám."),
            ("Levelek", "WooCommerce → Beállítások → E-mailek: feladó „Slugshop”, cím a bolt postafiókja (a telepítő beállítja); a vásárlói levelek szövegét az ügyféllel átnézni."),
            ("ÁFA", "WooCommerce → Adó: 27% normál kulcs Magyarországra (a telepítő létrehozza); az árak bruttóak."),
        ],
    },
    {
        "num": "05", "title": "JUTA-Soft (a Mandala mintájára)", "kind": "warn",
        "note": "A régi boltnál a JUTA-szkriptek a VirtueMart táblákba írnak. Az új bolthoz a WooCommerce-es változat kell – ugyanaz, ami a Mandalánál fut (/juta/raw_sync.php, /juta/elad.php). Részletek: docs/JUTA.md.",
        "items": [
            ("Szkriptek", "A /juta/ mappa WooCommerce-es változata (a Mandaláé alapján) a staging web gyökerébe; a beállításai (adatbázis, táblaelőtag, elérési utak) a staging WordPressre mutassanak."),
            ("Staging cron: CSAK a szinkron", "A tárhely cronja a raw_sync.php-t hívja (pl. 30 percenként). Az elad.php-t a stagingen NE futtasd: a tesztrendelések nem kerülhetnek a JUTA-ba."),
            ("Első szinkron ellenőrzése", "Utána: Megjelenés → Slugshop → Élesítési ellenőrzés „JUTA-szinkron él” zöld; Termékek → Piszkozatok: ennyi termék van a JUTA-ban, ami a boltban nem (kategória, kép, közzététel kell – az ügyféllel). Szúrópróba: 5 termék ára és készlete egyezik a JUTA-val."),
            ("Cikkszám-egyeztetés (Z03)", "A párosítás SKU = JUTA barcode. Ami a boltban van, de a szinkron sosem frissíti, annak a cikkszáma nem egyezik – listát az ügyféllel átnézni."),
            ("Fizetési módok leképezése", "Az elad.php kártyás listájába a Viva bővítmény azonosítója is kerüljön (a Mandalánál a Teya hiányzott, utalásként ment át); az utalás, utánvét, helyszíni fizetés leképezését a JUTA-s kollégával egyeztetni."),
            ("„Értesítést kérek”", "Próba: egy elfogyott terméken feliratkozás → a JUTA-ban készletre tenni → a következő szinkron után (a téma 10 percenként néz) megjön az „Újra raktáron” levél."),
        ],
    },
    {
        "num": "06", "title": "Adatok és jogi oldalak", "kind": "warn",
        "note": "Élesítés csak ezekkel.",
        "items": [
            ("Megjelenés → Slugshop → Adatok", "Tárhelyszolgáltató (név, cím, e-mail – Z05) az impresszumhoz; minden más a régi oldalról már kitöltve (telefon, e-mail, cégadatok, nyilvántartó bíróság: Szolnoki Törvényszék Cégbírósága, Fogyasztó Barát: MB0C642A, mérőkódok)."),
            ("Fogyasztó Barát", "Az ÁSZF és az adatkezelési tájékoztató onnan töltődik be (mint a régi oldalon). Ellenőrizd, hogy a stagingen és élesen is megjelenik (domainhez kötött lehet). Az ügyfél frissítse ott: új szállítási és fizetési módok, új adatkezelések (kapcsolati űrlap képfeltöltéssel, „Értesítést kérek”, sütik és mérőkódok – Z05)."),
            ("Impresszum", "/impresszum: a cégadatok táblázata a tárhellyel együtt (a régi oldalon üres volt)."),
            ("Beállítások → Adatvédelem", "Az „Adatvédelmi nyilatkozat” oldal legyen kijelölve (a telepítő beállítja; a pénztár és az űrlapok ezt linkelik)."),
        ],
    },
    {
        "num": "07", "title": "Próbák a stagingen", "kind": "",
        "note": "CRM: Élesítés 3/4 – TESZTELÉS. Ugyanezek automatikusan is: tests/wp/e2e.mjs (helyi teszt-WordPressen hibátlan).",
        "items": [
            ("Rendelés végig", "Minden szállítási és fizetési móddal: kosárba (listából és adatlapról) → kosár → pénztár → köszönő oldal; levél a vásárlónak és a boltnak; a rendelés a WooCommerce-ben."),
            ("Űrlapok", "Kapcsolat (képpel) → levél a boltnak, a beküldés az adminban (Beküldések). Kérdés a termékről. „Értesítést kérek”."),
            ("Keresés", "A fejléc nagyítója (vagy a / billentyű): élő találatok név és cikkszám szerint; Enter → találati oldal."),
            ("18+ és sütik", "Inkognitóban: 18+ ablak → sütiablak; elutasításnál nincs GTM / Meta / Barion kérés (böngésző Hálózat fül); elfogadás után van. A láblécben „Sütibeállítások”."),
            ("3D és videók", "Nyitóoldal asztalon és telefonon: forgó fegyverek, görgetésre szétszedett Panthera; régi gépen / WebGL nélkül a kép látszik. Videó-lapozó, „Megnézem a YouTube-on”."),
            ("Régi címek", "Mintákra: /zan-slugok, /zan-slugok/22-5-5mm-slugs, egy termék, /tarak/pp750-nagfykapacitasu-tar (→ 301 a /snowpeak alá), /cikkeink/cikkek/…, /bejelentkezes (→ /fiokom), /media/acfupload/FX_Crown_Exploded_PartsList.pdf (→ a médiatárbeli PDF), /index.php/szerviz (→ /szerviz)."),
            ("Mobil", "Telefonon: menü, kategóriafa („Kategóriák” gomb), kosár, pénztár; vízszintes görgetés sehol."),
            ("Ügyfél-jóváhagyás", "A staging címét István küldi az ügyfélnek a portálon (jóváhagyásra); a válaszokat a portálon kapjuk."),
        ],
    },
    {
        "num": "08", "title": "Élesítés", "kind": "warn",
        "note": "CRM: Élesítés 4/4. Előtte: 00–07 kész, az ügyfél a portálon jóváhagyta. Egyeztesd az időpontot a JUTA-s kollégával (cron-csere).",
        "items": [
            ("Régi bolt leállítása", "Az utolsó régi rendelések feldolgozva; a régi (VirtueMart-os) JUTA-cron KI."),
            ("Átköltöztetés", "A staging a slugshop.hu-ra (mentés-visszatöltés vagy migráló bővítmény). Better Search Replace a staging címére ÉS a JSON-escape-elt alakra is (https:\\/\\/uj.slugshop.hu)."),
            ("JUTA élesen", "A /juta beállításai az éles adatbázisra; cron: raw_sync.php ÉS elad.php. Az első éles rendelés után nézd meg, hogy bekerült a JUTA-ba."),
            ("Fizetés élesen", "Viva éles kulcsok; egy valódi kis összegű kártyás vásárlás, majd visszatérítés."),
            ("Közvetlen hivatkozások", "Beállítások → Közvetlen hivatkozások → Mentés (a téma /%postname% szerkezetet állít, perjel nélkül, mint a régi oldal)."),
            ("Keresők", "Az ügyfél jóváhagyása után: Beállítások → Olvasás: a „Keresőmotorok …” jelölés KI."),
            ("Felhasználók", "Nincs „admin” nevű felhasználó; az ügyfélnek saját fiók (Bolt-kezelő szerep, ha nem kell teljes admin)."),
        ],
    },
    {
        "num": "09", "title": "Élesítés után", "kind": "",
        "items": [
            ("Search Console", "Domain-tulajdon; oldaltérkép: /wp-sitemap.xml. Egy hét múlva a „Nem található” lista: ha régi cím maradt ki, a DEVELOPER.md szerint vegyük fel (a téma site.json legacyMap vagy szűrő)."),
            ("Mérés", "GTM: a tag-ek a hozzájárulás szerint (Consent Mode v2); a Meta és a Barion Pixel csak marketing-hozzájárulással (Z06)."),
            ("JUTA figyelése", "Az első héten naponta: az Élesítési ellenőrzés „JUTA-szinkron” sora zöld, a piszkozat új termékeket az ügyfél gondozza."),
            ("Régi képcímek", "A régi /images/… termékképek és /media/acfupload/… PDF-ek 301-gyel mennek (a téma); szúrópróba a régi tárhely leállítása után."),
            ("Mentés", "Napi adatbázis- és heti teljes mentés a tárhelyen; a régi oldal mentése 3 hónapig."),
        ],
    },
]
