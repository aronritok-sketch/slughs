#!/usr/bin/env python3
"""Az ügyfél javításkérései (portál, 2026-10-26) a telepítő adataiban – a build_data.py után fut, magában is futtatható
(idempotens). Ami a régi webshop adata volt, azt csak igazolt forrás alapján írjuk át (gyári robbantott ábra).

Futtatás a repó gyökeréből:  python3 tools/wp/fixes.py
"""
import json
import os
import re
import sys

HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, HERE)
from fxparts import mpn  # noqa: E402

DATA = os.path.join(os.path.dirname(os.path.dirname(HERE)), 'wp-theme', 'slugshop', 'setup', 'data')

# 1. Az ELR kategória a régi oldalon ugyanazon a címen volt, mint az „ELR Zan Slug” termék (a régi bolt a terméket
#    mutatta, az új az üres kategóriát). A kategória új címet kap, és bekerülnek az ELR lövedékek.
ELR_OLD, ELR_NEW = '/zan-slugok/elr-zan-slug', '/zan-slugok/elr-slugok'

# 3. FX16092: a gyári ábrák szerint „Grub screw M3x4” (hernyócsavar) – a kép jó volt, a név és a leírás nem.
FX16092 = {
    'name': 'FX16092 hernyócsavar M3×4 – gyári FX alkatrész',
    'short': '<p>Gyári FX16092 hernyócsavar (gyári megnevezés: Grub screw M3x4): fej nélküli, imbuszkulcsos M3×4 csavar.</p>',
    'desc': ('<p>Az <strong>FX16092</strong> gyári FX hernyócsavar (gyári megnevezés: <em>Grub screw M3x4</em>): fej nélküli, '
             'imbuszkulcsos M3×4 csavar. Hiányzó, sérült vagy elveszett csavar pótlására szerviz vagy karbantartás során.</p>\n'
             '<p><strong>Gyors specifikáció</strong></p>\n<ul>\n<li>Gyári cikkszám: <strong>FX16092</strong></li>\n'
             '<li>Megnevezés: hernyócsavar (grub screw) M3×4</li>\n<li>Kiszerelés: 1 db</li>\n<li>Gyártó: <strong>FX Airguns</strong></li>\n</ul>\n'
             '<p>Hogy mely modellekben szerepel, azt a gyári robbantott ábrák alapján a termékoldal mutatja.</p>'),
}

# 2., 7. Nyitóoldal: a diák linkje és megszólítása, rövidebb, tényszerű bemutatkozás (tegezve; a szakmai részt László hagyja jóvá).
SLIDES = [
    {'text': 'Magyarországon kizárólag a Slugshopnál. Bankkártyával (Viva) is fizethetsz.', 'href': ELR_NEW},
    {'text': 'Fedezd fel az FX Airguns PCP légfegyvereit: pontosság és megbízhatóság, szervizzel és alkatrészekkel együtt.',
     'button': 'Légfegyverek', 'href': '/fx-termekek/fx-fegyverek'},
    {},
    {},
    {'text': 'Nézd meg az FX légfegyvereket működés közben.'},
]
INTRO = {
    'title': 'Légfegyverek, ZAN lövedékek, FX alkatrészek',
    'text': ('A Slugshop jászberényi légfegyver-szaküzlet és szerviz. FX Airguns PCP légfegyvereket, gyári FX alkatrészeket '
             'és kiegészítőket forgalmazunk, a ZAN lövedékek magyarországi kizárólagos forgalmazójaként slugokat és pelleteket. '
             'A fegyvereket szervizeljük, és a választott lövedékhez hangoljuk.'),
}
BLOCKS = [
    {'title': 'FX légfegyverek és gyári alkatrészek',
     'text': ('FX Airguns PCP légfegyverek, gyári alkatrészek, csövek, tárak és kiegészítők. Az alkatrészeket a gyári '
              'cikkszám vagy a fegyver modellje alapján is megtalálod, a gyári robbantott ábrák a Letöltések között vannak.'),
     'button': 'FX termékek', 'href': '/fx-termekek'},
    {'title': 'Szerviz és hangolás',
     'text': ('Az FX légfegyverek szervizét és a lövedékhez illesztett rendszerhangolást előzetes egyeztetéssel vállaljuk '
              'Jászberényben: ingadozó sebességnél, romló szórásképnél, levegővesztésnél, lövedékváltáskor vagy használt '
              'fegyver vásárlása után.'),
     'button': 'Szerviz', 'href': '/szerviz'},
]

# 9. Termékoldal: szállítás (a kérdéssor díjai, 2026-10-26). A várható feladási időt László adja meg (Adatok fül).
SHIPPING = {
    'lines': ['GLS házhozszállítás: 3 650 Ft', 'GLS CsomagPont vagy automata: 2 000 Ft', 'Személyes átvétel Jászberényben: ingyenes'],
    'dispatch': '',
}


def load(name):
    with open(os.path.join(DATA, name + '.json'), encoding='utf-8') as f:
        return json.load(f)


def dump(name, data):
    with open(os.path.join(DATA, name + '.json'), 'w', encoding='utf-8') as f:
        f.write(json.dumps(data, ensure_ascii=False, indent=1) + '\n')


def main():
    cats = load('categories')
    for c in cats:
        if c['path'] == ELR_OLD:
            c.update({'path': ELR_NEW, 'slug': ELR_NEW.rsplit('/', 1)[1], 'oldPath': ELR_OLD, 'name': 'ELR ZAN slugok', 'menu': 'ELR ZAN slugok'})
    dump('categories', cats)

    products = load('products')
    first = {}
    for p in products:
        if re.search(r'\bELR\b', p['name'], re.I) and ELR_NEW not in p['cats']:
            p['cats'].append(ELR_NEW)
        if p['key'].startswith('fx16092-'):
            p.update(FX16092)
        p.pop('mpn', None)
        if mpn(p['name']):
            p['mpn'] = mpn(p['name'])
        # 4. Ugyanaz a tétel kétszer (azonos JUTA-vonalkód, ár és gyári cikkszám): a második rejtett, a címe az elsőre visz.
        if p['sku']:
            if p['sku'] in first and first[p['sku']].get('mpn') == p.get('mpn'):
                p['dupOf'] = first[p['sku']]['key']
            first.setdefault(p['sku'], p)
    dump('products', products)

    # 4. A „copy 1” PDF-ek bájtra azonosak az eredetivel: a listában nem jelennek meg, a fájl és a régi címe megmarad.
    downloads = load('downloads')
    files = {d['file'] for d in downloads}
    for d in downloads:
        orig = d['file'].replace('_copy_1.pdf', '.pdf')
        if orig != d['file'] and orig in files:
            d['dupOf'] = orig
    dump('downloads', downloads)

    site = load('site')
    for sl, fix in zip(site['slides'], SLIDES):
        sl.update(fix)
    site['intro'] = INTRO
    for b, fix in zip(site['blocks'], BLOCKS):
        b.update(fix)
    site['shipping'] = SHIPPING
    # A ZAN slugok és pelletek közös belépője (az ügyfél kategóriarendezése, 2026-10-28)
    for t in site['tiles']:
        if t['href'] == '/zan-slugok':
            t.update({'title': 'ZAN lövedékek', 'href': '/zan-lovedekek'})
    dump('site', site)


if __name__ == '__main__':
    main()
