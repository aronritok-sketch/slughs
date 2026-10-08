#!/usr/bin/env python3
"""A régi slugshop.hu (Joomla + VirtueMart) letöltése a cache/ mappába.

Mit tölt le: a fő oldalakat, a teljes kategóriafát a terméklistákkal, minden termék adatlapját, a cikkeket és a
videóoldalakat. A képeket az extract.py tölti le, csak azokat, amelyek kellenek.

Ami már a cache-ben van, azt nem kéri le újra (újratöltés: töröld a cache/ megfelelő mappáját).
A tűzfal a curl alapértelmezett azonosítóját blokkolhatja, ezért böngésző-azonosítóval kérünk, kérésenként kis szünettel.

Futtatás a repó gyökeréből:  python3 tools/fetch.py
"""
import json
import os
import re
import subprocess
import time
from urllib.parse import urljoin, urlparse

from bs4 import BeautifulSoup

BASE = 'https://slugshop.hu'
UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126 Safari/537.36'
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
CACHE = os.path.join(ROOT, 'cache')

PAGES = ['', 'szerviz', 'letoltesek', 'videok', 'cikkeink', 'kapcsolat', 'rolunk', 'aszf', 'impresszum',
         'adatvedelmi-nyilatkozat', 'bejelentkezes', 'regisztracio', 'videok/fx-drs', 'videok/fx-impact-m3',
         'videok/fx-king', 'cikkeink/articles', 'cikkeink/cikkek']
TOP_CATS = ['/zan-slugok', '/fx-termekek', '/fx-alkatreszek', '/snowpeak', '/karbantartas-celtablak',
            '/minden-termek/zan-pellet', '/minden-termek/jsb-pellet']


def cache_name(kind, path):
    return os.path.join(CACHE, kind, (path.strip('/').replace('/', '__') or 'index') + '.html')


def get(kind, path):
    fn = cache_name(kind, path)
    os.makedirs(os.path.dirname(fn), exist_ok=True)
    if not os.path.exists(fn) or os.path.getsize(fn) < 1000:
        subprocess.run(['curl', '-sS', '-m', '30', '-A', UA, '-L', BASE + '/' + path.lstrip('/'), '-o', fn], check=False)
        time.sleep(0.3)
    return open(fn, encoding='utf-8', errors='ignore').read()


def txt(e):
    return re.sub(r'\s+', ' ', e.get_text(' ', strip=True)) if e else ''


def soup(html):
    s = BeautifulSoup(html, 'html.parser')
    for t in s(['script', 'style', 'noscript']):
        t.decompose()
    return s


def crawl_categories():
    cats, seen, queue = {}, set(), list(TOP_CATS)
    while queue:
        path = queue.pop(0)
        if path in seen:
            continue
        seen.add(path)
        s = soup(get('cat', path))
        subs = []
        for it in s.select('.category-list .category-item'):
            a = it.select_one('.category-name a')
            img = it.select_one('.category-image-cont img')
            subs.append({'path': urlparse(urljoin(BASE, a['href'])).path, 'name': txt(a), 'img': img.get('src') if img else None})
        prods = []
        for it in s.select('.browse-view .product-item'):
            a = it.select_one('.product-name a')
            prods.append({'path': urlparse(urljoin(BASE, a['href'])).path, 'name': txt(a),
                          'img': [i.get('src') for i in it.select('.product-image-cont img')],
                          'price': txt(it.select_one('span.PricesalesPrice')), 'cat': txt(it.select_one('.category-name')),
                          'notify': bool(it.select_one('.btn-notify'))})
        desc = s.select_one('.category-desc-cont')
        cats[path] = {'title': txt(s.select_one('h1.category-page-title, .category-view h1, h1')),
                      'desc': desc.decode_contents().strip() if desc else '', 'subs': subs, 'products': prods}
        queue += [x['path'] for x in subs]
    # oldalsáv kategóriafa (sorrend és szülők)
    s = soup(get('cat', '/zan-slugok'))
    side = s.find('span', class_='mod-header-title', string=lambda x: x and 'Kategóriák' in x)
    box = side
    for _ in range(6):
        box = box.parent
        if box.find('ul'):
            break

    def walk(ul):
        out = []
        for li in ul.find_all('li', recursive=False):
            a = li.find('a')
            sub = li.find('ul')
            out.append({'path': urlparse(urljoin(BASE, a['href'])).path, 'name': txt(a), 'children': walk(sub) if sub else []})
        return out

    return cats, walk(box.find('ul'))


def crawl_products(cats):
    paths = []
    for c in cats.values():
        for p in c['products']:
            if p['path'] not in paths:
                paths.append(p['path'])
    out = {}
    for path in paths:
        s = soup(get('prod', path))
        pd = s.select_one('.productdetails-view')
        if not pd:
            print('nincs adatlap:', path)
            continue
        big, small = [], []
        for a in pd.select('#product-image-gallery, .vpf-zoom-gallery'):
            h = a.get('href')
            if h and h not in big:
                big.append(h)
                img = a.find('img')
                small.append(img.get('src') if img else h)
        desc = pd.select_one('#tab-product-desc .description')
        short = pd.select_one('.product-short-desc-cont')
        av = pd.select_one('[itemprop=availability]')
        meta = s.find('meta', attrs={'name': 'description'})
        out[path] = {'name': txt(pd.select_one('h1.product-title')), 'price': txt(pd.select_one('span.PricesalesPrice')),
                     'stock': txt(pd.select_one('.product-stock-cont')),
                     'availability': av.get('content', '').split('/')[-1] if av else '',
                     'sku': txt(pd.select_one('[itemprop=sku]')),
                     'short': short.decode_contents().strip() if short else '',
                     'desc': desc.decode_contents().strip() if desc else '',
                     'gallery': big, 'gallery_small': small,
                     'back': txt(pd.select_one('.btn-backtocat span')),
                     'notify': bool(pd.select_one('.btn-notify')),
                     'meta': meta.get('content', '') if meta else ''}
    return out


def main():
    os.makedirs(CACHE, exist_ok=True)
    for p in PAGES:
        get('pages', p)
    cats, tree = crawl_categories()
    print('kategóriák:', len(cats))
    products = crawl_products(cats)
    print('termékek:', len(products))
    # cikkek
    for blog in ['cikkeink/articles', 'cikkeink/cikkek']:
        s = soup(get('pages', blog))
        for a in s.select('.blog a[href]'):
            href = urlparse(urljoin(BASE, a['href'])).path
            if href.startswith('/' + blog + '/'):
                get('art', href)
    json.dump({'cats': cats, 'tree': tree, 'products': products},
              open(os.path.join(CACHE, 'crawl.json'), 'w'), ensure_ascii=False, indent=1)
    print('kész: cache/crawl.json')


if __name__ == '__main__':
    main()
