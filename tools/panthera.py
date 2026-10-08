#!/usr/bin/env python3
"""A „szétszedett Panthera” blokk adatai az FX gyári robbantott ábrájából.

Forrás: https://slugshop.hu/media/acfupload/FX_Panthera_Exploded_PartsList.pdf (a Letöltések oldalról), 1. oldal:
- az összerakott, árnyékolt oldalnézet (beágyazott PNG átlátszó maszkkal) → assets/img/panthera/render.webp
- a vektoros robbantott ábra → 300 dpi-n kirenderelve, összefüggő vonalcsoportokra (= alkatrészekre) bontva,
  világos vonalakkal egy textúraatlaszba csomagolva → assets/img/panthera/atlas.webp
- alkatrészenként: hely az ábrán, hely az atlaszban, és a fegyver tengelye menti helyzet (0 = csőtorkolat, 1 = tus),
  ebből számolja az app.js, honnan „repül ki” az alkatrész → assets/js/panthera-data.js

Futtatás:  pip install pymupdf numpy scipy pillow && python3 tools/panthera.py
"""
import json
import os
import subprocess

import numpy as np
import pymupdf as fitz
from PIL import Image
from scipy import ndimage as ndi

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
CACHE = os.path.join(ROOT, 'cache', 'pdf')
OUT_IMG = os.path.join(ROOT, 'assets', 'img', 'panthera')
URL = 'https://slugshop.hu/media/acfupload/FX_Panthera_Exploded_PartsList.pdf'
PDF = os.path.join(CACHE, 'FX_Panthera_Exploded_PartsList.pdf')
CLIP = fitz.Rect(40, 410, 550, 745)   # a robbantott ábra az 1. oldalon (pt)
RENDER_XREF = 20                      # az alsó, összerakott oldalnézet képe
LINE = (236, 236, 240)                # vonalszín a sötét háttérhez


def main():
    os.makedirs(CACHE, exist_ok=True)
    os.makedirs(OUT_IMG, exist_ok=True)
    if not os.path.exists(PDF):
        subprocess.run(['curl', '-sS', '-A', 'Mozilla/5.0 Chrome/126', URL, '-o', PDF], check=True)
    doc = fitz.open(PDF)
    page = doc[0]

    # 1) összerakott oldalnézet átlátszó háttérrel
    smask = next(im[1] for im in page.get_images(full=True) if im[0] == RENDER_XREF)
    pix = fitz.Pixmap(doc, RENDER_XREF)
    rgb = Image.frombytes('RGBA' if pix.alpha else 'RGB', (pix.width, pix.height), pix.samples).convert('RGB')
    mask = fitz.Pixmap(doc, smask)
    render = rgb.copy()
    render.putalpha(Image.frombytes('L', (mask.width, mask.height), mask.samples))
    render.save(os.path.join(OUT_IMG, 'render.webp'), 'WEBP', quality=88, method=6)

    # 2) robbantott ábra → alkatrészek
    pix = page.get_pixmap(clip=CLIP, dpi=300)
    gray = np.array(Image.frombytes('RGB', (pix.width, pix.height), pix.samples).convert('L')).astype(np.int16)
    H, W = gray.shape
    ink = gray < 200
    alpha_full = np.clip((235 - gray) * 255 // 150, 0, 255).astype(np.uint8)   # élsimított vonal → átlátszóság
    alpha_full = ndi.grey_dilation(alpha_full, size=(3, 3))                     # vastagabb vonal: kicsinyítve is látszik
    lab, n = ndi.label(ndi.binary_dilation(ink, iterations=1), structure=np.ones((3, 3)))
    objs = ndi.find_objects(lab)
    parts = []
    for i, sl in enumerate(objs, start=1):
        mask = (lab[sl] == i)
        area = int((ink[sl] & mask).sum())
        if area < 8:
            continue
        a = np.where(mask, alpha_full[sl], 0).astype(np.uint8)
        ys, xs = np.nonzero(a)
        if not len(xs):
            continue
        y0, y1, x0, x1 = ys.min(), ys.max() + 1, xs.min(), xs.max() + 1
        crop = a[y0:y1, x0:x1]
        parts.append({'x': int(sl[1].start + x0), 'y': int(sl[0].start + y0), 'w': int(x1 - x0), 'h': int(y1 - y0),
                      'area': area, 'alpha': crop})

    # 3) tengely: a részek súlypontjainak fő iránya (csőtorkolat bal fent → tus jobb lent)
    c = np.array([[p['x'] + p['w'] / 2, p['y'] + p['h'] / 2] for p in parts])
    wts = np.array([p['area'] for p in parts], dtype=float)
    m = (c * wts[:, None]).sum(0) / wts.sum()
    cov = np.cov((c - m).T, aweights=wts)
    u = np.linalg.eigh(cov)[1][:, -1]
    if u[0] < 0:
        u = -u
    v = np.array([-u[1], u[0]])
    along = (c - m) @ u
    perp = (c - m) @ v
    amin, amax = along.min(), along.max()

    # 4) atlasz: polcos csomagolás 2 px réssel
    order = sorted(range(len(parts)), key=lambda k: -parts[k]['h'])
    AW, pad = 2048, 2
    x = y = shelf = 0
    for k in order:
        p = parts[k]
        if x + p['w'] + pad > AW:
            x, y, shelf = 0, y + shelf + pad, 0
        p['ax'], p['ay'] = x, y
        x += p['w'] + pad
        shelf = max(shelf, p['h'])
    AH = y + shelf
    atlas = np.zeros((AH, AW, 4), dtype=np.uint8)
    atlas[..., 0], atlas[..., 1], atlas[..., 2] = LINE
    for p in parts:
        atlas[p['ay']:p['ay'] + p['h'], p['ax']:p['ax'] + p['w'], 3] = p['alpha']
    Image.fromarray(atlas, 'RGBA').save(os.path.join(OUT_IMG, 'atlas.webp'), 'WEBP', quality=82, method=6)

    data = {
        'w': W, 'h': H, 'angle': float(np.degrees(np.arctan2(u[1], u[0]))),
        'render': 'assets/img/panthera/render.webp', 'renderW': render.width, 'renderH': render.height,
        'atlas': 'assets/img/panthera/atlas.webp', 'atlasW': AW, 'atlasH': AH,
        'parts': [{'x': p['x'], 'y': p['y'], 'w': p['w'], 'h': p['h'], 'ax': p['ax'], 'ay': p['ay'],
                   'a': round(float((along[k] - amin) / (amax - amin)), 4), 'b': round(float(perp[k]), 1), 'area': p['area']}
                  for k, p in enumerate(parts)],
        'pdf': URL,
    }
    with open(os.path.join(ROOT, 'assets', 'js', 'panthera-data.js'), 'w') as f:
        f.write('/* GENERÁLT – tools/panthera.py (FX Panthera robbantott ábra). */\nwindow.PANTHERA = '
                + json.dumps(data, separators=(',', ':')) + ';\n')
    print(f'{len(parts)} alkatrész, atlasz {AW}×{AH}, tengely {data["angle"]:.1f}°')


if __name__ == '__main__':
    main()
