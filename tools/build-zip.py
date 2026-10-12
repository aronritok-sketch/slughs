#!/usr/bin/env python3
"""Előnézet zipben a CRM ügyfélportál jóváhagyásához: dist/slugshop-elonezet.zip

A portál „Weboldal-előnézet (zip)” anyaga a zip gyökerében lévő index.html-t nyitja meg, homokozóban
(CSP sandbox, allow-same-origin nélkül), asztali / tablet / mobil nézetben. A zipben a statikus oldal van
(index.html + assets/), a fejlesztői források (assets/js/src, tools, tests) nélkül.

Futtatás a repó gyökeréből (előtte a 3D-csomag: cd tools/3d && npm run build):  python3 tools/build-zip.py
"""
import os
import zipfile

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
OUT = os.path.join(ROOT, 'dist', 'slugshop-elonezet.zip')
SKIP = ('assets/js/src/',)
TYPES = {'.html', '.css', '.js', '.webp', '.svg', '.png', '.jpg', '.woff2'}   # mind szerepel a portál engedélyezett típusai között


def main():
    files = ['index.html']
    for base, _, names in os.walk(os.path.join(ROOT, 'assets')):
        for n in names:
            rel = os.path.relpath(os.path.join(base, n), ROOT).replace(os.sep, '/')
            if not rel.startswith(SKIP) and os.path.splitext(n)[1].lower() in TYPES:
                files.append(rel)
    os.makedirs(os.path.dirname(OUT), exist_ok=True)
    with zipfile.ZipFile(OUT, 'w', zipfile.ZIP_DEFLATED, compresslevel=9) as z:
        for rel in sorted(files):
            # a képek és betűk már tömörítettek, azokat csak tároljuk
            method = zipfile.ZIP_DEFLATED if rel.endswith(('.html', '.css', '.js', '.svg')) else zipfile.ZIP_STORED
            z.write(os.path.join(ROOT, rel), rel, compress_type=method)
    print(f'{os.path.relpath(OUT, ROOT)}  {len(files)} fájl, {os.path.getsize(OUT) / 1e6:.1f} MB')


if __name__ == '__main__':
    main()
