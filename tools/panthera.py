#!/usr/bin/env python3
"""A „szétszedett Panthera” blokk adatai az FX gyári robbantott ábrájából.

Forrás: https://slugshop.hu/media/acfupload/FX_Panthera_Exploded_PartsList.pdf (a Letöltések oldalról), 1. oldal:
- az összerakott, árnyékolt oldalnézet (beágyazott PNG átlátszó maszkkal) → assets/img/panthera/render.webp
  (ez a 3D-modell oldallapjainak textúrája, és WebGL nélkül ez a tartalék kép)
- a render átlátszóságából alkatrészenként körvonal + lyukak (lapos részek), és a forgástestek (hangtompító, palack,
  tár, kerék) méretei → assets/js/panthera-data.js; ebből építi fel a three.js-modellt az assets/js/src/fx3d.js

Futtatás:  pip install pymupdf numpy pillow opencv-python-headless && python3 tools/panthera.py
"""
import json
import os
import subprocess

import cv2
import numpy as np
import pymupdf as fitz
from PIL import Image

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
CACHE = os.path.join(ROOT, 'cache', 'pdf')
OUT_IMG = os.path.join(ROOT, 'assets', 'img', 'panthera')
URL = 'https://slugshop.hu/media/acfupload/FX_Panthera_Exploded_PartsList.pdf'
PDF = os.path.join(CACHE, 'FX_Panthera_Exploded_PartsList.pdf')
RENDER_XREF = 20                      # az alsó, összerakott oldalnézet képe


# A gyári oldalnézet (1313×235 px, 1 px ≈ 1 mm) részekre bontva a 3D-modellhez.
# Lapos részek: régió (x0, x1, y0, y1, kizárt ellipszis) → körvonal lyukakkal, ezt húzza ki a 3D-motor.
FLAT = {
    'forend':  (372, 748, 0, 235, None),
    'action':  (745, 1000, 0, 138, None),
    'grip':    (900, 1000, 138, 235, None),
    'stock':   (1000, 1252, 0, 235, (1112, 131, 78, 36)),   # a palack ellipszise kivonva
    'buttpad': (1250, 1313, 0, 235, None),
}
# Hengeres részek (px): tengely y, sugár, x-tartomány
ROUND = {
    'shroud': {'y': 58, 'r': 18.5, 'x0': 8, 'x1': 378},
    'bottle': {'y': 131, 'r': 33.5, 'x0': 1036, 'x1': 1188},
    'bottleNeck': {'y': 131, 'r': 17, 'x0': 1188, 'x1': 1246},
    'magazine': {'y': 76, 'r': 19, 'x': 817},
    'wheel': {'y': 111, 'r': 17, 'x': 817},
}


def flat_shapes(alpha):
    """Régiónként a külső körvonal(ak) és a lyukak, egyszerűsítve, px-koordinátában."""
    out = {}
    H, W = alpha.shape
    for name, (x0, x1, y0, y1, ell) in FLAT.items():
        m = np.zeros_like(alpha, dtype=np.uint8)
        m[y0:y1, x0:x1] = (alpha[y0:y1, x0:x1] > 110).astype(np.uint8) * 255
        if ell:
            cx, cy, rx, ry = ell
            cv2.ellipse(m, (cx, cy), (rx, ry), 0, 0, 360, 0, -1)
        m = cv2.morphologyEx(m, cv2.MORPH_OPEN, np.ones((2, 2), np.uint8))
        cs, hier = cv2.findContours(m, cv2.RETR_CCOMP, cv2.CHAIN_APPROX_NONE)
        shapes = []
        if hier is None:
            continue
        hier = hier[0]
        for i, c in enumerate(cs):
            if hier[i][3] != -1 or cv2.contourArea(c) < 60:
                continue
            outer = cv2.approxPolyDP(c, 0.7, True)[:, 0, :].tolist()
            holes = []
            j = hier[i][2]
            while j != -1:
                if cv2.contourArea(cs[j]) > 12:
                    holes.append(cv2.approxPolyDP(cs[j], 0.6, True)[:, 0, :].tolist())
                j = hier[j][0]
            shapes.append({'outer': outer, 'holes': holes})
        out[name] = shapes
    return out


def main():
    os.makedirs(CACHE, exist_ok=True)
    os.makedirs(OUT_IMG, exist_ok=True)
    if not os.path.exists(PDF):
        subprocess.run(['curl', '-sS', '-A', 'Mozilla/5.0 Chrome/126', URL, '-o', PDF], check=True)
    doc = fitz.open(PDF)
    page = doc[0]

    # összerakott oldalnézet átlátszó háttérrel
    smask = next(im[1] for im in page.get_images(full=True) if im[0] == RENDER_XREF)
    pix = fitz.Pixmap(doc, RENDER_XREF)
    rgb = Image.frombytes('RGBA' if pix.alpha else 'RGB', (pix.width, pix.height), pix.samples).convert('RGB')
    mask = fitz.Pixmap(doc, smask)
    render = rgb.copy()
    render.putalpha(Image.frombytes('L', (mask.width, mask.height), mask.samples))
    render.save(os.path.join(OUT_IMG, 'render.webp'), 'WEBP', quality=88, method=6)

    data = {
        'model': {'w': render.width, 'h': render.height, 'flat': flat_shapes(np.array(render.getchannel('A'))), 'round': ROUND},
        'render': 'assets/img/panthera/render.webp',
        'pdf': URL,
    }
    with open(os.path.join(ROOT, 'assets', 'js', 'panthera-data.js'), 'w') as f:
        f.write('/* GENERÁLT – tools/panthera.py (FX Panthera robbantott ábra). */\nwindow.PANTHERA = '
                + json.dumps(data, separators=(',', ':')) + ';\n')
    print(f'{len(data["model"]["flat"])} lapos + {len(ROUND)} forgástestes alkatrész, render {render.width}×{render.height}')


if __name__ == '__main__':
    main()
