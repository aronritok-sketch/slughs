/* ==========================================================================
   SLUGSHOP — termék- és tartalomadatok (prototípus)
   A termékek és árak a jelenlegi slugshop.hu nyitóoldaláról származnak.
   A "sample: true" jelölésű elemek minta tartalmak, élesítés előtt cserélendők.
   WordPress/WooCommerce alatt ezt az adatbázis váltja ki.
   ========================================================================== */

window.SLUG = window.SLUG || {};

SLUG.CATS = [
  { key: "zan",      name: "ZAN slugok",              short: "ZAN slugok",      icon: "slug",     desc: "ZAN Projectiles hollow point slugok .22-től .357-ig. Magyarországon kizárólag a Slugshopban." },
  { key: "jsb",      name: "JSB diabolók",            short: "JSB diabolók",    icon: "pellet",   desc: "JSB Match minőségű diabolók 4,5 és 5,5 mm-ben." },
  { key: "fx",       name: "FX légfegyverek",         short: "FX fegyverek",    icon: "rifle",    desc: "FX Airguns PCP légfegyverek. Az ár a konfigurációtól függ, kérj ajánlatot." },
  { key: "fx-kieg",  name: "FX kiegészítők",          short: "FX kiegészítők",  icon: "scope",    desc: "Tárak, céltávcsövek, kronográfok, csövek, nyomásmérők és töltés." },
  { key: "fx-alk",   name: "FX alkatrészek",          short: "FX alkatrészek",  icon: "spring",   desc: "Gyári FX alkatrészek cikkszám szerint: rugók, csavarok, tömítések." },
  { key: "snowpeak", name: "Snowpeak",                short: "Snowpeak",        icon: "nozzle",   desc: "Snowpeak (Artemis) légfegyverek alkatrészei és kiegészítői." },
  { key: "karb",     name: "Karbantartás, céltáblák", short: "Karbantartás",    icon: "target",   desc: "Tisztítás, kenés, céltáblák és lőtéri felszerelés." }
];

/* Menü-csoportok: egy menüpont több kategóriát is kijelölhet. */
SLUG.GROUPS = {
  "zan": ["zan", "jsb"],
  "fx": ["fx", "fx-kieg"],
  "fx-alk": ["fx-alk"],
  "snowpeak": ["snowpeak"],
  "karb": ["karb"]
};

SLUG.CALIBERS = [
  { key: "177", label: ".177", mm: "4,5 mm" },
  { key: "22",  label: ".22",  mm: "5,5 mm" },
  { key: "25",  label: ".25",  mm: "6,35 mm" },
  { key: "30",  label: ".30",  mm: "7,62 mm" },
  { key: "35",  label: ".35",  mm: "9 mm" }
];

SLUG.PRODUCTS = [
  { id: "zan-218-25-5gr", name: "ZAN Projectiles HP slug .218 / 5,53 mm – 25,5 grain – 200 db", cat: "zan", sub: "ZAN .22 / 5,5 mm slugok", price: 6200, stock: true, cal: ["22"], icon: "slug", isNew: true,
    sku: "160087", brand: "ZAN Projectiles",
    specs: { "Kaliber": ".218 / 5,53 mm", "Tömeg": "25,5 grain", "Kivitel": "Hollow point (HP)", "Kiszerelés": "200 db / doboz" } },
  { id: "zan-250-33gr", name: "ZAN Projectiles HP slug – .250 / 6,35 mm, 33 grain, 200 db", cat: "zan", sub: ".25 / 6,35 mm slugok", price: 6400, stock: true, cal: ["25"], icon: "slug", isNew: true,
    brand: "ZAN Projectiles",
    specs: { "Kaliber": ".250 / 6,35 mm", "Tömeg": "33 grain", "Kivitel": "Hollow point (HP)", "Kiszerelés": "200 db / doboz" } },
  { id: "zan-300-59gr", name: "ZAN Projectiles HP slug – .300 / 7,62 mm, 59 grain, 128 db", cat: "zan", sub: ".30 / 7,62 mm slugok", price: 7100, stock: true, cal: ["30"], icon: "slug", isNew: true,
    brand: "ZAN Projectiles",
    specs: { "Kaliber": ".300 / 7,62 mm", "Tömeg": "59 grain", "Kivitel": "Hollow point (HP)", "Kiszerelés": "128 db / doboz" } },
  { id: "zan-357-100gr", name: "ZAN Projectiles HP slug – .357 / 9,07 mm, 100 grain, 100 db", cat: "zan", sub: ".35 / 9 mm slugok", price: 7150, stock: true, cal: ["35"], icon: "slug", isNew: true,
    brand: "ZAN Projectiles",
    specs: { "Kaliber": ".357 / 9,07 mm", "Tömeg": "100 grain", "Kivitel": "Hollow point (HP)", "Kiszerelés": "100 db / doboz" } },

  { id: "jsb-hades-45-1034", name: "JSB Hades 4,5 mm 0,67 g / 10,34 grain – 500 db", cat: "jsb", sub: "JSB diabolók", price: 5944, stock: true, cal: ["177"], icon: "pellet", isNew: true,
    sku: "000845", brand: "JSB",
    specs: { "Kaliber": "4,5 mm (.177)", "Tömeg": "0,67 g / 10,34 grain", "Forma": "Hades (hollow point)", "Kiszerelés": "500 db" } },
  { id: "jsb-exact-45-844", name: "JSB Exact 4,5 mm 0,547 g / 8,44 grain", cat: "jsb", sub: "JSB diabolók", price: 5950, stock: true, cal: ["177"], icon: "pellet", isNew: true,
    sku: "007134", brand: "JSB",
    specs: { "Kaliber": "4,5 mm (.177)", "Tömeg": "0,547 g / 8,44 grain", "Forma": "Exact diabolo" } },
  { id: "jsb-hades-55-1589", name: "JSB Hades 5,5 mm 15,89 grain", cat: "jsb", sub: "JSB diabolók", price: 3750, stock: false, cal: ["22"], icon: "pellet", isNew: true,
    brand: "JSB",
    specs: { "Kaliber": "5,5 mm (.22)", "Tömeg": "15,89 grain", "Forma": "Hades (hollow point)" } },

  { id: "fx-impact-m3", name: "FX Impact M3", cat: "fx", sub: "FX légfegyverek", price: null, stock: "ask", cal: [], icon: "rifle", sample: true,
    brand: "FX Airguns", specs: { "Rendszer": "PCP, bullpup", "Kaliber": "Több változatban", "Engedély": "7,5 J felett engedélyköteles" } },
  { id: "fx-dreamline", name: "FX Dreamline", cat: "fx", sub: "FX légfegyverek", price: null, stock: "ask", cal: [], icon: "rifle", sample: true,
    brand: "FX Airguns", specs: { "Rendszer": "PCP", "Kaliber": "Több változatban", "Engedély": "7,5 J felett engedélyköteles" } },
  { id: "fx-panthera", name: "FX Panthera", cat: "fx", sub: "FX légfegyverek", price: null, stock: "ask", cal: [], icon: "rifle", sample: true,
    brand: "FX Airguns", specs: { "Rendszer": "PCP", "Kaliber": "Több változatban", "Engedély": "7,5 J felett engedélyköteles" } },
  { id: "fx-leopard", name: "FX Leopard", cat: "fx", sub: "FX légfegyverek", price: null, stock: "ask", cal: [], icon: "rifle", sample: true,
    brand: "FX Airguns", specs: { "Rendszer": "PCP, bullpup", "Kaliber": "Több változatban", "Engedély": "7,5 J felett engedélyköteles" } },

  { id: "huma-mini-nyomasmero", name: "Huma-Air digitális mini nyomásmérő – FX Impact, 28 mm, 300 bar", cat: "fx-kieg", sub: "Nyomásmérők", price: 24000, stock: true, cal: [], icon: "gauge", isNew: true,
    brand: "Huma-Air", specs: { "Kijelzés": "Digitális", "Méréshatár": "300 bar", "Átmérő": "28 mm", "Kompatibilitás": "FX Impact" } },
  { id: "carm-tar-22", name: "CARM slug tár FX Crown DRS / Dreamline / Maverick / WC MK3-hoz, .22 kaliber", cat: "fx-kieg", sub: "FX tárak", price: 12000, stock: true, cal: ["22"], icon: "magazine", isNew: true,
    brand: "CARM", specs: { "Kaliber": ".22 / 5,5 mm", "Lövedék": "Slug", "Kompatibilitás": "FX Crown DRS, Dreamline, Maverick, WC MK3" } },
  { id: "nexus-gen2-4-25x50", name: "Nexus Gen II 4-25x50 céltávcső", cat: "fx-kieg", sub: "Céltávcsövek, red dot", price: 840000, stock: true, cal: [], icon: "scope", isNew: true,
    brand: "Element Optics", specs: { "Nagyítás": "4–25×", "Objektív": "50 mm", "Típus": "Céltávcső" } },
  { id: "fx-no-limit-34", name: "FX No Limit állítható távcsőszerelék – 34 mm, Weaver/Picatinny (30005)", cat: "fx-kieg", sub: "Távcsőrögzítők", price: 31200, stock: true, cal: [], icon: "mount", isNew: true,
    sku: "30005", brand: "FX Airguns", specs: { "Gyűrűátmérő": "34 mm", "Sín": "Weaver / Picatinny", "Kivitel": "Állítható dőlésszög" } },
  { id: "fx-volfram-kalapacs", name: "FX volfrám kalapács – Impact MKI/MKII/M3/M4 (FX20710)", cat: "fx-kieg", sub: "FX tartozékok", price: 18000, stock: true, cal: [], icon: "bolt", isNew: true,
    sku: "FX20710", brand: "FX Airguns", specs: { "Anyag": "Volfrám", "Kompatibilitás": "FX Impact MKI, MKII, M3, M4" } },
  { id: "fx-chrono-display", name: "FX Chronograph Display külső kijelző – FX20590", cat: "fx-kieg", sub: "FX tartozékok", price: 73000, stock: true, cal: [], icon: "chrono", isNew: true,
    sku: "FX20590", brand: "FX Airguns", specs: { "Funkció": "Külső kijelző FX Pocket Chrono-hoz" } },
  { id: "fx-solid-stx-22-700", name: "FX Solid csőkészlet STX Superior Heavy – 5,5 mm (.22), 700 mm", cat: "fx-kieg", sub: "Csövek, csőbetétek", price: 109900, stock: true, cal: ["22"], icon: "barrel", isNew: true,
    brand: "FX Airguns", specs: { "Kaliber": "5,5 mm (.22)", "Hossz": "700 mm", "Cső": "STX Superior Heavy (slug)" } },
  { id: "fx-true-ballistic-chrono", name: "FX True Ballistic Chronograph CE radaros kronográf (FX30104)", cat: "fx-kieg", sub: "Kronográfok", price: 350000, stock: false, cal: [], icon: "chrono", isNew: true,
    sku: "FX30104", brand: "FX Airguns", specs: { "Mérés": "Radaros", "Tanúsítás": "CE" } },
  { id: "fx-impact-m3-power-block", name: "FX Impact M3 Power Block Kit (FX20845)", cat: "fx-kieg", sub: "FX tartozékok", price: 47000, stock: false, cal: [], icon: "bolt", isNew: true,
    sku: "FX20845", brand: "FX Airguns", specs: { "Kompatibilitás": "FX Impact M3" } },
  { id: "huma-toltocso-1500", name: "Huma-Air mikrofuratú töltőcső – 1500 mm, 1/8 BSP belső menet", cat: "fx-kieg", sub: "Levegőtöltés és szerelvény", price: 20500, stock: true, cal: [], icon: "hose", isNew: true,
    sku: "006175", brand: "Huma-Air", specs: { "Hossz": "1500 mm", "Csatlakozás": "1/8\" BSP belső menet", "Kivitel": "Mikrofuratú" } },

  { id: "fx11504-2-rugo", name: "FX11504-2 rugó 0,4×2,5×15 – gyári FX alkatrész", cat: "fx-alk", sub: "FX alkatrészek", price: 1450, stock: true, cal: [], icon: "spring", isNew: true,
    sku: "FX11504-2", brand: "FX Airguns", specs: { "Méret": "0,4 × 2,5 × 15 mm", "Eredet": "Gyári FX" } },
  { id: "fx2504-csavar", name: "FX2504 süllyesztett fejű csavar M3×6 – gyári FX alkatrész", cat: "fx-alk", sub: "FX alkatrészek", price: 350, stock: true, cal: [], icon: "bolt", isNew: true,
    sku: "FX2504", brand: "FX Airguns", specs: { "Menet": "M3 × 6", "Fej": "Süllyesztett", "Eredet": "Gyári FX" } },

  { id: "snowpeak-p35-toltoszonda", name: "P35 töltőszonda", cat: "snowpeak", sub: "Snowpeak", price: 4800, stock: true, cal: [], icon: "nozzle", isNew: true,
    sku: "007004", brand: "Snowpeak", specs: { "Kompatibilitás": "Snowpeak P35" } }
];

/* Új termékek sorrendje a nyitóoldalon (a jelenlegi oldal sorrendje) */
SLUG.NEW_ORDER = [
  "huma-mini-nyomasmero", "zan-357-100gr", "zan-300-59gr", "carm-tar-22", "nexus-gen2-4-25x50",
  "fx11504-2-rugo", "fx-no-limit-34", "zan-218-25-5gr", "fx-volfram-kalapacs", "fx-chrono-display",
  "zan-250-33gr", "fx-solid-stx-22-700", "fx2504-csavar", "snowpeak-p35-toltoszonda",
  "fx-true-ballistic-chrono", "jsb-hades-45-1034", "fx-impact-m3-power-block", "jsb-hades-55-1589",
  "huma-toltocso-1500", "jsb-exact-45-844"
];

SLUG.POSTS = [
  { id: "fx-leopard", title: "FX Leopard – a jövő bullpup légpuskája", tag: "Újdonság", read: 5, icon: "rifle",
    excerpt: "A légfegyveres világban néha születnek olyan újdonságok, amelyek egy egész kategóriát mozdítanak előre. Az FX Leopard ilyen." },
  { id: "slug-vagy-diabolo", title: "Slug vagy diabolo? Mikor melyiket válaszd", tag: "Tudástár", read: 7, icon: "slug", sample: true,
    excerpt: "A slug nagyobb távon stabilabb lehet, de nem minden cső szereti. Összefoglaltuk, mitől függ a választás." },
  { id: "pcp-karbantartas", title: "O-gyűrű, regulátor, tár: a PCP karbantartás alapjai", tag: "Szerviz", read: 6, icon: "gauge", sample: true,
    excerpt: "Mikor elég egy tömítéscsere, és mikor kell a fegyvert szervizbe hozni? A leggyakoribb jelek és teendők." }
];

SLUG.SLIDES = [
  { eyebrow: "Kizárólag a Slugshopban", title: "ELR ZAN<em>slugok</em>", lead: "Magyarországon <strong>csakis kizárólag a Slugshopban.</strong> Már Viva Wallet bankkártyás fizetés is elérhető.", cta: { label: "ELR slugok", href: "kategoria.html#kat-zan" }, tab: "ELR ZAN" },
  { eyebrow: "PCP új generáció", title: "Légfegyverek<em>forradalma</em>", lead: "Fedezd fel a PCP légfegyverek új generációját. Precizitás, megbízhatóság és forradalmi design egy helyen.", cta: { label: "FX fegyverek", href: "kategoria.html#kat-fx" }, tab: "FX PCP" },
  { eyebrow: "Kizárólagos forgalmazó", title: "ZAN lövedékek<em>szakértője</em>", lead: "A ZAN lövedékek <strong>magyarországi kizárólagos forgalmazója</strong> a Slugshop Kft.", cta: { label: "Termékek", href: "kategoria.html#kat-zan" }, tab: "ZAN" },
  { eyebrow: "Hivatalos partner", title: "Scandinavian<em>Arms</em>", lead: "A Slugshop a Scandinavian Arms termékeinek magyarországi forgalmazója.", cta: { label: "Termékek", href: "kategoria.html" }, tab: "Scand. Arms" },
  { eyebrow: "Videótár", title: "FX légfegyverek<em>videón</em>", lead: "Gyere és fedezd fel az FX légfegyverek világát: bemutatók, beállítás, lőtéri tesztek.", cta: { label: "Videók", href: "videok.html" }, tab: "Videók" }
];

/* Hero műszaki rajz — a ZAN slugok valós adatai */
SLUG.BLUEPRINT = [
  { key: "22", label: ".22", dia: "5,53", gr: "25,5", pcs: 200, product: "zan-218-25-5gr", scale: 0.86 },
  { key: "25", label: ".25", dia: "6,35", gr: "33",   pcs: 200, product: "zan-250-33gr",   scale: 0.94 },
  { key: "30", label: ".30", dia: "7,62", gr: "59",   pcs: 128, product: "zan-300-59gr",   scale: 1.04 },
  { key: "35", label: ".357", dia: "9,07", gr: "100", pcs: 100, product: "zan-357-100gr",  scale: 1.14 }
];

/* Pénzügyi beállítások — MINTA értékek, élesben a WooCommerce adja */
SLUG.SETTINGS = {
  eurRate: 395,               // HUF / EUR, minta árfolyam
  shipping: [
    { id: "futar", name: "Házhozszállítás futárral", note: "1–3 munkanap", price: 2490, sample: true },
    { id: "szemelyes", name: "Személyes átvétel – Jászberény", note: "Előzetes egyeztetés alapján", price: 0 }
  ],
  payment: [
    { id: "viva", name: "Bankkártya – Viva Wallet", note: "Visa, Mastercard" },
    { id: "barion", name: "Barion", note: "Bankkártya vagy Barion egyenleg" },
    { id: "utalas", name: "Előre utalás", note: "A csomagot a jóváírás után adjuk fel" }
  ]
};
