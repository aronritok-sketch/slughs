"""
A Slugshop kérdéssora az élesítéshez: ami csak az ügyféltől (Móra László, Tibori Ádám) vagy a JUTA-t kezelő kollégától
jöhet. Egy adatforrás: ebből készül a portál egyedi kérdéssora (JSON), a PDF (build.py) és a Word (build_docx.py).
Formátum: SECTIONS = [(kulcs, cím, bevezető, [(kulcs, kérdés, típus, kötelező, súgó), …]), …] – „Z01” = 1. fejezet.
"""

TITLE = "Slugshop – kérdéssor az új webshop élesítéséhez"
INTRO = ("Az új slugshop.hu kinézetét jóváhagytátok, most élesítjük. A termékek, árak, szövegek és képek már átkerültek; "
         "pár dolgot viszont csak ti tudtok megmondani – főleg a szállításról, a fizetésről és a JUTA-ról. Ahol a régi "
         "oldalról már tudunk valamit, odaírtuk: ott elég megerősíteni vagy javítani. Jelszót, kulcsot ide ne írj – azt "
         "a portálon külön, jelszókezelőn át kérjük. Nagyjából 20 perc.")

SECTIONS = [
    ("szallitas", "Szállítás", "A régi webshopban GLS-futár és GLS csomagpont / automata volt, és személyes átvétel Jászberényben.", [
        ("szall_modok", "Milyen szállítási módok legyenek az új webshopban?", "textarea", True,
         "pl. GLS futár, GLS csomagpont / automata, személyes átvétel (Jászberény, Megyeház u. 3.) – ha más is kell, írd ide"),
        ("szall_dij", "Mennyi a szállítási díj (bruttó)? Ha súly vagy kosárérték szerint változik, a teljes díjtábla.", "textarea", True,
         "pl. GLS futár 2 490 Ft, csomagpont 1 990 Ft; 30 000 Ft felett ingyenes"),
        ("szall_ingyenes", "Van ingyenes szállítás? Ha igen, milyen kosárértéktől?", "text", False, ""),
        ("szall_gls", "Van GLS-szerződésetek (ügyfélszám), és a GLS-címkét eddig honnan nyomtattátok?", "text", True,
         "A GLS bővítmény ezzel köti be a csomagpont-választót és a címkenyomtatást."),
        ("szall_kulfold", "Küldtök külföldre is? Ha igen, hova és mennyiért?", "text", False, "A régi oldalon volt EUR-váltó."),
    ]),
    ("fizetes", "Fizetés és számlázás", "", [
        ("fiz_modok", "Milyen fizetési módok legyenek?", "textarea", True,
         "pl. Viva Wallet bankkártya (a régi oldalon is), előre utalás, utánvét, fizetés átvételkor az üzletben"),
        ("fiz_viva", "Megvan a Viva Wallet (Viva.com) üzleti fiókotok? Ki fér hozzá?", "text", True,
         "A bekötéshez a Merchant ID, az API-kulcs és a fizetési forrás (source code) kell – ezeket ne ide írd: a portálon kérjük."),
        ("fiz_bank", "Előre utaláshoz: bankszámlaszám és a kedvezményezett neve.", "text", False, "Csak ha lesz előre utalás."),
        ("fiz_utanvet", "Utánvétnél van-e külön díj (bruttó)?", "text", False, "Csak ha lesz utánvét."),
        ("fiz_barion", "A régi oldalon Barion Pixel futott. Fizettek még Barionnal, vagy kivehetjük?", "text", False, ""),
        ("fiz_eur", "Kell-e euróban is fizetni (a régi oldal fejlécében HUF/EUR váltó volt)?", "text", False,
         "Az euró külön bővítményt és árfolyamot jelent; ha nem kell, a forint marad."),
        ("fiz_szamla", "Mivel számláztok? (Számlázz.hu, Billingo, a JUTA állítja ki, kézzel …)", "text", True,
         "Ha Számlázz.hu vagy Billingo, a webshop magától kiállítja a számlát; a cégeknek az adószámot is ráírja."),
    ]),
    ("juta", "JUTA-Soft", "A JUTA most a régi webshoppal 30 percenként szinkronizál (a tárhely cronja). Az új webshopnál a WooCommerce-es változat fut, ahogy a Mandalánál.", [
        ("juta_kapcsolat", "Ki kezeli nálatok a JUTA-t, és kit kereshetünk a technikai részével (Zoli?)? Név, telefon, e-mail.", "text", True, ""),
        ("juta_rendeles", "Milyen állapotú rendelés menjen be a JUTA-ba: a kifizetett vagy a teljesített (kiküldött)?", "text", True,
         "A Mandalánál a teljesített rendelések mennek."),
        ("juta_akcio", "Az akciós árakat a JUTA-ban kezelitek, vagy a webshopban?", "text", False, ""),
        ("juta_cikkszam", "A JUTA cikkszáma (vonalkód) ugyanaz, mint a webshop cikkszáma (EAN / FX-cikkszám)?", "text", True,
         "A párosítás a cikkszám alapján megy; ahol eltér, ott kézzel kell egyeztetni."),
    ]),
    ("regi", "A régi webshop adatai", "", [
        ("regi_vasarlok", "Átvigyük a régi vásárlói fiókokat (név, cím, e-mail)? A jelszavak nem vihetők át: első belépéskor újat kérnek.", "text", True, ""),
        ("regi_rendelesek", "Kellenek a régi rendelések az új webshopban is (pl. reklamációhoz), vagy elég, ha a JUTA-ban megvannak?", "text", True, ""),
        ("regi_kuponok", "Vannak élő kuponok, kedvezmények vagy törzsvásárlói árak?", "text", False, ""),
    ]),
    ("ceg", "Cégadatok, jogi oldalak, tárhely", "", [
        ("ceg_tarhely", "Hol lesz az új webshop tárhelye? A régi az MHostingnál van – maradjon ott, vagy költözzön?", "text", True,
         "Az impresszumba a tárhelyszolgáltató neve, címe és elérhetősége kell."),
        ("ceg_domain", "Ki fér hozzá a slugshop.hu domainhez (DNS)?", "text", True, "A hozzáférést a portálon, jelszókezelőn át kérjük."),
        ("ceg_email", "Melyik postafiókból menjenek a webshop levelei (pl. info@slugshop.hu)? Hol van a postafiók?", "text", True,
         "A levélküldéshez az SMTP-adatok kellenek – azokat a portálon kérjük."),
        ("ceg_aszf", "Az ÁSZF és az adatkezelési tájékoztató a Fogyasztó Baráttól töltődik be. Ki frissíti ott az új szállítási és fizetési módokat?", "text", True,
         "Az új webshop új adatkezelést is hoz: kapcsolati űrlap képfeltöltéssel, „Értesítést kérek” feliratkozás."),
    ]),
    ("meres", "Mérés és hirdetések", "A régi oldalon Google Tag Manager (GTM-K2DT9KZ8), Facebook (Meta) Pixel és Barion Pixel futott. Az újon csak a süti-hozzájárulás után töltődnek be.", [
        ("meres_ki", "Ki kezeli a GTM-et és a hirdetéseket (ti, egy ügynökség)? Kérünk hozzá hozzáférést.", "text", False, ""),
        ("meres_kell", "Maradjon mind a három mérőkód?", "text", False, ""),
    ]),
]

# A portál egyedi kérdéssora (CRM → ügyfél adatlap → Onboarding kérdőív → Egyedi kérdéssor).
def portal_json():
    qs = []
    for _k, title, _intro, items in SECTIONS:
        for key, q, typ, req, hint in items:
            qs.append({"key": key, "hu": q, "en": q, "type": typ, "required": req, "section": title, "hint": hint})
    return {"title": TITLE, "questions": qs}
