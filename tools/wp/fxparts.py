#!/usr/bin/env python3
"""Gyári FX-cikkszámok és modellek a gyári robbantott ábrákból (a Letöltések PDF-jei) – a termékoldal „Gyári cikkszám” és
„Melyik modellekhez?” sora, valamint az FX alkatrészek modellválasztója ebből dolgozik. Csak igazolt adat: egy alkatrész
akkor tartozik egy modellhez, ha a gyári cikkszáma pontosan szerepel annak a modellnek a gyári alkatrészlistájában.

Bemenet:  a PDF-ek mappája (a médiacsomag files/ mappája), setup/data/{downloads,products}.json
Kimenet:  wp-theme/slugshop/setup/data/fxparts.json   {"models": {slug: név}, "parts": {cikkszám: {"name", "models"}}}
Futtatás: python3 tools/wp/fxparts.py <pdf-mappa>          (pdftotext kell: poppler-utils)
"""
import json
import os
import re
import subprocess
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
DATA = os.path.join(ROOT, 'wp-theme', 'slugshop', 'setup', 'data')

# PDF címe (Letöltések) → modell. A rész-ábrák (cső, palack, adapter) a modellhez tartoznak.
MODEL_OF = [
    (r'^FX Bobcat MkI$', 'FX Bobcat MkI'), (r'^FX Bobcat MkII$', 'FX Bobcat MkII'), (r'^FX Crown', 'FX Crown'),
    (r'^FX Dreamline Basic', 'FX Dreamline Basic'), (r'^FX Dreamline Bullpup', 'FX Dreamline Bullpup'),
    (r'^FX Dreamline Classic', 'FX Dreamline Classic'), (r'^FX Dreamline Lite', 'FX Dreamline Lite'),
    (r'^FX Dreamline Tactical', 'FX Dreamline Tactical'), (r'^FX Dreamline', 'FX Dreamline'),
    (r'^FX DRS Tactical', 'FX DRS Tactical'), (r'^FX DRS', 'FX DRS'), (r'^FX Dynamic', 'FX Dynamic'),
    (r'^FX Impact M3', 'FX Impact M3'), (r'^FX Impact M4', 'FX Impact M4'), (r'^FX Impact MkII', 'FX Impact MkII'),
    (r'^FX Impact BarrelKit', ''), (r'^FX Impact MkI', 'FX Impact MkI'), (r'^FX King', 'FX King'), (r'^FX Leopard', 'FX Leopard'),
    (r'^FX Maverick', 'FX Maverick'), (r'^FX Panthera Hunter', 'FX Panthera Hunter'), (r'^FX Panthera', 'FX Panthera'),
    (r'^FX Royale', 'FX Royale'), (r'^FX Streamline', 'FX Streamline'), (r'^FX (T12|Typhoon)', 'FX Typhoon T12'),
    (r'^FX Wildcat MkIII', 'FX Wildcat MkIII'), (r'^FX Wildcat MkII', 'FX Wildcat MkII'), (r'^FX Wildcat MkI', 'FX Wildcat MkI'),
]

# A termék nevéből a gyári cikkszám: „FX16092 …”, „… (FX20267C)”, „FX 9121 – …”. Ami „…-ból/-ből” (pl. „FX2572 alátétből”),
# az nem a termék cikkszáma.
MPN = re.compile(r'\bFX\s?(\d{4,6}(?:-\d+)?[A-Z]?)\b(?!\s*\S*(ból|ből)\b)')


def slug(s):
    return re.sub(r'[^a-z0-9]+', '-', s.lower()).strip('-')


def mpn(name):
    m = MPN.search(name)
    return m.group(1) if m else ''


def model_of(title):
    """None: ismeretlen cím; '': szándékosan kihagyva (az Impact csőkészlete MkI és MkII alá is tartozik)."""
    for pat, model in MODEL_OF:
        if re.search(pat, title):
            return model
    return None


def main(pdf_dir):
    downloads = json.load(open(os.path.join(DATA, 'downloads.json'), encoding='utf-8'))
    products = json.load(open(os.path.join(DATA, 'products.json'), encoding='utf-8'))
    wanted = sorted({mpn(p['name']) for p in products} - {''})
    texts = {}
    for d in downloads:
        if d.get('dupOf'):
            continue
        model = model_of(d['title'])
        if model is None:
            print('nincs modell:', d['title'])
        if not model:
            continue
        out = subprocess.run(['pdftotext', '-layout', os.path.join(pdf_dir, d['file']), '-'], capture_output=True, text=True).stdout
        texts.setdefault(model, []).append(out)
    parts = {}
    for num in wanted:
        tok = re.compile(r'(?<![\w.,-])' + re.escape(num) + r'(?![\w.,-])')
        models, names = [], {}
        for model, pages in texts.items():
            for text in pages:
                for line in text.splitlines():
                    for m in tok.finditer(line):
                        # a gyári megnevezés a szám után (a King ábráján előtte) áll
                        after = re.match(r'\s+([A-Za-z][A-Za-z0-9 ,./x×()\'-]*?)(?=\s{2,}|$)', line[m.end():])
                        before = re.search(r'([A-Za-z][A-Za-z0-9 ,./x×()\'-]*?)\s{2,}$', line[:m.start()])
                        cands = [g.group(1).strip() for g in (after, before) if g]
                        name = next((c for c in cands if not re.match(r'^[A-Z]{1,2}\d', c)), '')
                        if model not in models:
                            models.append(model)
                        if name:
                            names[name] = names.get(name, 0) + 1
        if models:
            name = max(names, key=names.get).rstrip(' ,') if names else ''
            parts[num] = {'name': name, 'models': sorted(slug(m) for m in models)}
    all_models = {slug(m): m for _p, m in MODEL_OF if m}
    used = sorted({m for p in parts.values() for m in p['models']})
    out = {'models': {s: all_models[s] for s in used}, 'parts': parts}
    with open(os.path.join(DATA, 'fxparts.json'), 'w', encoding='utf-8') as f:
        f.write(json.dumps(out, ensure_ascii=False, indent=1) + '\n')
    print(f'{len(parts)} / {len(wanted)} gyári cikkszám a robbantott ábrákon; nincs rajta:', ', '.join(n for n in wanted if n not in parts))


if __name__ == '__main__':
    main(sys.argv[1] if len(sys.argv) > 1 else os.path.join(ROOT, 'build', 'media', 'files'))
