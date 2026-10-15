#!/usr/bin/env python3
"""A WordPress-telepítő csomagjai:
  dist/slugshop-tema.zip   a child téma (Megjelenés → Témák → Téma feltöltése; frissítéskor „Csere a feltöltöttre”)
  dist/slugshop-media.zip  médiacsomag: termék-, kategória- és cikkképek, letölthető PDF-ek
                           (Megjelenés → Slugshop → Médiacsomag, vagy kicsomagolva: wp-content/uploads/slugshop-media/)
Futtatás a repó gyökeréből (előtte: tools/wp/media.py, tools/wp/rebuild.sh):  python3 tools/wp/build_theme.py
"""
import os
import zipfile

ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
THEME = os.path.join(ROOT, 'wp-theme', 'slugshop')
MEDIA = os.path.join(ROOT, 'build', 'media')
DIST = os.path.join(ROOT, 'dist')
STORED = ('.webp', '.png', '.jpg', '.woff2', '.pdf', '.zip')


def pack(out, base, prefix, skip=lambda rel: False):
    n = 0
    with zipfile.ZipFile(out, 'w', zipfile.ZIP_DEFLATED, compresslevel=9) as z:
        for d, _, files in os.walk(base):
            for f in sorted(files):
                full = os.path.join(d, f)
                rel = os.path.relpath(full, base).replace(os.sep, '/')
                if skip(rel) or f.startswith('.'):
                    continue
                z.write(full, prefix + rel, compress_type=zipfile.ZIP_STORED if f.lower().endswith(STORED) else zipfile.ZIP_DEFLATED)
                n += 1
    print(f'{os.path.relpath(out, ROOT)}  {n} fájl, {os.path.getsize(out) / 1e6:.1f} MB')


os.makedirs(DIST, exist_ok=True)
pack(os.path.join(DIST, 'slugshop-tema.zip'), THEME, 'slugshop/')
pack(os.path.join(DIST, 'slugshop-media.zip'), MEDIA, '', skip=lambda rel: rel.endswith('.json'))
