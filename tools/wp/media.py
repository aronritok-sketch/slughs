#!/usr/bin/env python3
"""A webshop médiacsomagja: a régi oldal TELJES MÉRETŰ képei WebP-ben (legfeljebb 1600 px), a WordPress telepítőjéhez.

Az előnézet (assets/img) 480 px-es képeket használ; a webshopban a termékoldal nagyítható galériája ennél nagyobbat kér.
Forrás: a cache/crawl.json termékenkénti galériája (a VirtueMart eredeti képei), ami nincs meg, ott az előnézet képe.
Kimenet: build/media/<mappa>/<név>.webp + build/media/manifest.json (fájl → forrás URL, méret).

Futtatás a repó gyökeréből:  python3 tools/wp/media.py   (újrafuttatva csak a hiányzókat tölti le)
"""
import ast
import hashlib
import json
import os
import subprocess
import sys
from concurrent.futures import ThreadPoolExecutor
from urllib.parse import quote, urljoin

from PIL import Image

ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
sys.path.insert(0, os.path.join(ROOT, 'tools', 'wp'))
from data import load_preview  # noqa: E402

BASE = 'https://slugshop.hu'
UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36'
CACHE = os.path.join(ROOT, 'cache', 'img-full')
OUT = os.path.join(ROOT, 'build', 'media')
MAX = 1600


def fetch(url):
    url = urljoin(BASE, url)
    fn = os.path.join(CACHE, hashlib.md5(url.encode()).hexdigest())
    if not os.path.exists(fn) or os.path.getsize(fn) == 0:
        os.makedirs(CACHE, exist_ok=True)
        q = quote(url, safe=':/?=&%')
        subprocess.run(['curl', '-sS', '-m', '60', '-A', UA, '-L', q, '-o', fn], check=False)
    return fn if os.path.exists(fn) and os.path.getsize(fn) > 0 else None


def to_webp(src, dest, quality=80):
    if os.path.exists(dest):
        return True
    try:
        im = Image.open(src)
        im.load()
        im = im.convert('RGBA') if im.mode in ('P', 'LA', 'RGBA') or 'transparency' in im.info else im.convert('RGB')
        if max(im.size) > MAX:
            im.thumbnail((MAX, MAX), Image.LANCZOS)
        os.makedirs(os.path.dirname(dest), exist_ok=True)
        im.save(dest, 'WEBP', quality=quality, method=6)
        return True
    except Exception as e:  # sérült kép vagy 404-es HTML
        print('kihagyva:', src, e)
        return False


def main():
    D = load_preview()
    crawl = json.load(open(os.path.join(ROOT, 'cache', 'crawl.json')))['products']
    jobs = []  # (forrás URL vagy helyi fájl, cél relatív útvonal)
    for key, p in D['products'].items():
        slug = p['path'].rsplit('/', 1)[1]
        c = crawl.get(p['path'], {})
        gal = c.get('gallery') or '[]'
        gal = ast.literal_eval(gal) if isinstance(gal, str) else gal
        gal = [g for g in gal if g and not g.lower().endswith(('.svg', 'noimage.gif'))]
        if gal:
            for i, g in enumerate(gal[:8]):
                jobs.append((g, f'products/{slug}{"" if i == 0 else f"-{i + 1}"}.webp'))
        elif p.get('img'):
            jobs.append((os.path.join(ROOT, p['img']), f'products/{slug}.webp'))
    manifest = {}

    def run(job):
        src, rel = job
        local = src if src.startswith(ROOT) else fetch(src)
        if local and to_webp(local, os.path.join(OUT, rel)):
            return rel, src
        return None

    with ThreadPoolExecutor(8) as ex:
        for r in ex.map(run, jobs):
            if r:
                manifest[r[0]] = r[1]
    json.dump(manifest, open(os.path.join(OUT, 'manifest.json'), 'w'), ensure_ascii=False, indent=0)
    size = sum(os.path.getsize(os.path.join(OUT, k)) for k in manifest)
    print(f'{len(manifest)} / {len(jobs)} termékkép, {size / 1e6:.1f} MB')


if __name__ == '__main__':
    main()
