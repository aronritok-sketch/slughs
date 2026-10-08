#!/usr/bin/env python3
"""A letöltött régi oldalból (cache/) az előnézet adatai: assets/js/data.js és assets/img/**.

Szabály (Áron, 2026-10-08): a design és a szöveg a régi oldalé, csak ráncfelvarrás. A szöveget nem írjuk át,
csak a lenti FIXES pontos cseréi futnak (elírás, írásjel, angol rendszerüzenet). A HTML-ből a Joomla inline
stílusai és osztályai kikerülnek, a szerkezet (bekezdés, lista, táblázat, kép, link) marad.

Futtatás:  python3 tools/fetch.py && python3 tools/extract.py
"""
import hashlib
import io
import json
import os
import re
import subprocess
from urllib.parse import unquote, urljoin, urlparse

from bs4 import BeautifulSoup, Comment, NavigableString
from PIL import Image

BASE = 'https://slugshop.hu'
UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126 Safari/537.36'
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
CACHE = os.path.join(ROOT, 'cache')
IMG = os.path.join(ROOT, 'assets', 'img')

# Pontos szövegcserék a régi oldal egyértelmű hibáira (a szöveg egyébként betűre marad).
FIXES = [
    ('Bővebbem', 'Bővebben'),
    ('elismerves', 'elismerve'),
    ('Drimline', 'Dreamline'),
    ('bankártyás', 'bankkártyás'),
    ('Elmultál', 'Elmúltál'),
    ('Csak is kizárólag', 'Csakis kizárólag'),
    ('Szervíz', 'Szerviz'),
    ('felömlők,betolótüskék', 'felömlők, betolótüskék'),
    ('energiáját.Ezért', 'energiáját. Ezért'),
    ('Magyarországi kizárólagos', 'magyarországi kizárólagos'),
    ('Csövek,csőbetétek', 'Csövek, csőbetétek'),
    ('Fx légfegyverek - videótár', 'FX légfegyverek – videótár'),
    ('Only 10 in stock', 'Raktáron (10 db)'),
    ('Kft!', 'Kft.!'),
]

ALLOWED = {'p', 'br', 'strong', 'b', 'em', 'i', 'u', 'ul', 'ol', 'li', 'h2', 'h3', 'h4', 'h5', 'h6', 'a', 'table',
           'thead', 'tbody', 'tr', 'th', 'td', 'img', 'blockquote', 'hr', 'figure', 'figcaption'}
UNWRAP = {'span', 'div', 'font', 'section', 'article', 'center', 'small', 'sup', 'sub'}


def fix(s):
    for a, b in FIXES:
        s = s.replace(a, b)
    return s


def txt(e):
    return fix(re.sub(r'\s+', ' ', e.get_text(' ', strip=True))) if e else ''


def soup(html):
    s = BeautifulSoup(html, 'html.parser')
    for t in s(['script', 'style', 'noscript', 'form', 'iframe', 'select', 'input', 'button', 'meta']):
        t.decompose()
    for c in s.find_all(string=lambda x: isinstance(x, Comment)):
        c.extract()
    return s


def page(name):
    return BeautifulSoup(open(os.path.join(CACHE, 'pages', name + '.html'), encoding='utf-8', errors='ignore').read(), 'html.parser')


# ------------------------------------------------------------------ képek
_img_cache = {}


def fetch_bin(url):
    url = urljoin(BASE, url.split('#')[0])
    fn = os.path.join(CACHE, 'img', hashlib.md5(url.encode()).hexdigest())
    os.makedirs(os.path.dirname(fn), exist_ok=True)
    if not os.path.exists(fn) or os.path.getsize(fn) == 0:
        quoted = url.replace(' ', '%20').replace('(', '%28').replace(')', '%29')
        subprocess.run(['curl', '-sS', '-m', '40', '-A', UA, '-L', quoted, '-o', fn], check=False)
    return fn if os.path.exists(fn) and os.path.getsize(fn) > 0 else None


def image(url, kind, name, width, quality=72):
    """Letölti és WebP-be menti (SVG marad SVG). Visszaadja a relatív útvonalat, vagy None-t."""
    if not url:
        return None
    key = (url, kind, width)
    if key in _img_cache:
        return _img_cache[key]
    src = fetch_bin(url)
    out = None
    if src:
        os.makedirs(os.path.join(IMG, kind), exist_ok=True)
        if urlparse(url).path.lower().endswith('.svg'):
            rel = f'assets/img/{kind}/{name}.svg'
            open(os.path.join(ROOT, rel), 'wb').write(open(src, 'rb').read())
            out = rel
        else:
            try:
                im = Image.open(src)
                im.load()
                if im.mode in ('P', 'LA', 'RGBA') or 'transparency' in im.info:
                    im = im.convert('RGBA')
                else:
                    im = im.convert('RGB')
                if im.width > width:
                    im = im.resize((width, round(im.height * width / im.width)), Image.LANCZOS)
                rel = f'assets/img/{kind}/{name}.webp'
                im.save(os.path.join(ROOT, rel), 'WEBP', quality=quality, method=6)
                out = rel
            except Exception as e:  # sérült vagy nem kép (pl. 404-es HTML)
                print('kép kihagyva:', url, e)
    _img_cache[key] = out
    return out


def slug(s):
    s = unquote(s).lower()
    tr = str.maketrans('áéíóöőúüű', 'aeiooouuu')
    return re.sub(r'[^a-z0-9]+', '-', s.translate(tr)).strip('-')[:80] or 'x'


def silhouette(url, name, width):
    """A kép sötét sziluettje (ugyanaz az alfa) – a 3D-s forgatásnál a test „vastagsága”."""
    src = fetch_bin(url)
    if not src:
        return None
    im = Image.open(src).convert('RGBA')
    if im.width > width:
        im = im.resize((width, round(im.height * width / im.width)), Image.LANCZOS)
    alpha = im.getchannel('A')
    core = Image.new('RGBA', im.size, (24, 24, 28, 255))
    core.putalpha(alpha)
    rel = f'assets/img/site/{name}.webp'
    core.save(os.path.join(ROOT, rel), 'WEBP', quality=70, method=6)
    return rel


def youtube_item(vid):
    """A YouTube-videó címe (oEmbed) és bélyegképe, helyben tárolva (az előnézet net nélkül is mutatja)."""
    d = os.path.join(CACHE, 'yt')
    os.makedirs(d, exist_ok=True)
    meta, jpg = os.path.join(d, vid + '.json'), os.path.join(d, vid + '.jpg')
    if not os.path.exists(meta):
        subprocess.run(['curl', '-sS', '-m', '20', f'https://www.youtube.com/oembed?url=https://www.youtube.com/watch?v={vid}&format=json', '-o', meta])
    if not os.path.exists(jpg):
        subprocess.run(['curl', '-sS', '-m', '20', f'https://i.ytimg.com/vi/{vid}/maxresdefault.jpg', '-o', jpg])
    try:
        title = json.load(open(meta))['title']
    except Exception:
        title = ''
    thumb = None
    try:
        im = Image.open(jpg).convert('RGB')
        im = im.resize((640, round(im.height * 640 / im.width)), Image.LANCZOS)
        thumb = f'assets/img/site/yt-{vid}.webp'
        im.save(os.path.join(ROOT, thumb), 'WEBP', quality=72, method=6)
    except Exception:
        pass
    return {'id': vid, 'title': title, 'thumb': thumb}


# ------------------------------------------------------------------ HTML tisztítás
def clean(html, kind='content'):
    s = BeautifulSoup(html or '', 'html.parser')
    for t in s(['script', 'style', 'noscript', 'form', 'iframe', 'select', 'input', 'button', 'meta', 'link']):
        t.decompose()
    for c in s.find_all(string=lambda x: isinstance(x, Comment)):
        c.extract()
    for el in list(s.find_all(True)):
        if el.name in UNWRAP:
            el.unwrap()
            continue
        if el.name not in ALLOWED:
            el.unwrap()
            continue
        attrs = {}
        if el.name == 'a' and el.get('href'):
            href = el['href']
            if href.startswith('/') and not href.startswith('//'):
                href = '#' + href.split('?')[0]  # belső link → előnézeti útvonal
            attrs['href'] = href
            if href.startswith('http'):
                attrs['target'] = '_blank'
                attrs['rel'] = 'noopener'
        if el.name == 'img':
            src = el.get('src') or el.get('data-src')
            rel = image(src, kind, slug(os.path.splitext(os.path.basename(urlparse(src or '').path))[0]), 900) if src else None
            if not rel:
                el.decompose()
                continue
            attrs = {'src': rel, 'alt': fix(el.get('alt', '')), 'loading': 'lazy'}
        if el.name in ('td', 'th') and el.get('colspan'):
            attrs['colspan'] = el['colspan']
        el.attrs = attrs
    out = str(s)
    out = re.sub(r'<p>(\s|&nbsp;|\xa0|<br/?>)*</p>', '', out)
    out = re.sub(r'<h6>(\s|&nbsp;|\xa0)*</h6>', '', out)
    out = re.sub(r'\n\s*\n+', '\n', out).strip()
    return fix(out)


def price_num(s):
    m = re.sub(r'[^\d]', '', s or '')
    return int(m) if m else None


# ------------------------------------------------------------------ fő
def main():
    crawl = json.load(open(os.path.join(CACHE, 'crawl.json')))
    cats, tree, prods = crawl['cats'], crawl['tree'], crawl['products']

    # --- termékek (több kategóriában szereplő terméket egyszer, név + cikkszám szerint)
    products, by_key, path_to_id, used_ids = {}, {}, {}, set()
    listing = {}
    for c in cats.values():
        for it in c['products']:
            listing.setdefault(it['path'], it)
    for path, p in prods.items():
        key = (p['name'], p['sku'])
        if key in by_key:
            path_to_id[path] = by_key[key]
            continue
        pid = slug(path.rsplit('/', 1)[1])
        n = 2
        while pid in used_ids:
            pid = f'{slug(path.rsplit("/", 1)[1])}-{n}'
            n += 1
        used_ids.add(pid)
        by_key[key] = pid
        path_to_id[path] = pid
        li = listing.get(path, {})
        first = (li.get('img') or [None])[0] or (p['gallery_small'] or [None])[0]
        img = image(first, 'p', pid, 480, 70)
        gallery = []
        for i, g in enumerate(p['gallery_small'][1:4], start=2):
            r = image(g, 'p', f'{pid}-{i}', 480, 62)
            if r:
                gallery.append(r)
        stock_in = p['availability'] == 'InStock'
        products[pid] = {
            'path': path, 'name': fix(p['name']), 'price': price_num(p['price']), 'priceText': p['price'],
            'stock': 'in' if stock_in else 'out', 'stockText': fix(p['stock']) if stock_in else 'Elfogyott',
            'sku': p['sku'], 'short': clean(p['short'], 'desc'), 'desc': clean(p['desc'], 'desc'),
            'img': img, 'gallery': gallery, 'back': fix(p['back']), 'cats': [], 'meta': fix(p['meta']),
        }
    print('termék (összevonva):', len(products))

    # --- kategóriák
    catlist = {}

    def add_cat(node, parent):
        path = node['path']
        c = cats.get(path, {'title': node['name'], 'desc': '', 'subs': [], 'products': []})
        ids = []
        for it in c['products']:
            pid = path_to_id.get(it['path'])
            if pid and pid not in ids:
                ids.append(pid)
                if path not in products[pid]['cats']:
                    products[pid]['cats'].append(path)
        sub_imgs = {s['path']: s['img'] for s in c['subs']}
        catlist[path] = {'path': path, 'name': fix(c['title'] or node['name']), 'menu': fix(node['name']), 'parent': parent,
                         'desc': clean(c['desc'], 'cat'), 'children': [ch['path'] for ch in node['children']],
                         'products': ids, 'img': None, '_subimgs': sub_imgs}
        for ch in node['children']:
            add_cat(ch, path)

    for node in tree:
        add_cat(node, None)
    for c in catlist.values():
        for chp, im in c.pop('_subimgs').items():
            if chp in catlist and im:
                catlist[chp]['img'] = image(im, 'c', slug(chp), 480, 70)
    print('kategória:', len(catlist))

    # --- nyitóoldal
    home = page('index')
    hs = soup(str(home))
    slides = []
    for cap in hs.select('.vpfrs-caption-inner'):
        item = cap.find_parent(class_=re.compile('vpfrs-item|item|slide')) or cap.parent.parent
        img = None
        for im in (item.find_all('img') if item else []):
            if 'csillagok' not in (im.get('src') or ''):
                img = im.get('src')
                break
        title = txt(cap.find(['h2', 'h1', 'h3']))
        paras = [txt(x) for x in cap.find_all('p')]
        a = cap.find('a')
        if any(x['title'] == title and x['text'] == ' '.join(x2 for x2 in paras if x2 and x2 != txt(a)) for x in slides):
            continue  # a körhinta klónjai
        slides.append({'title': title, 'text': ' '.join(x for x in paras if x and x != txt(a)),
                       'button': txt(a), 'href': '#' + urlparse(a['href']).path if a else '#/',
                       'img': image(img, 'site', 'slide-' + slug(os.path.basename(urlparse(img or 'x').path)), 1800, 70)})
    print('diák:', len(slides))
    elso = hs.find(class_='elso')
    tiles = []
    for box in elso.select('.keretes'):
        a = box.find('a')
        im = [i.get('src') for i in box.find_all('img') if 'pluszok' not in (i.get('src') or '')]
        tiles.append({'title': txt(box.find(['h3', 'h4'])) or txt(a), 'href': '#' + urlparse(a['href']).path,
                      'img': image(im[0] if im else None, 'site', 'tile-' + slug(os.path.basename(urlparse(im[0]).path)) if im else 'x', 400, 80)})
    intro_title = txt(elso.find('h3', string=re.compile('innováció')) or elso.find_all('h3')[-1])
    intro_text = txt(elso.find_all('p')[-1])
    blocks = []
    for cls in ['masodik', 'harmadik']:
        e = hs.find(class_=cls)
        heads = e.find_all(['h2', 'h3', 'h4', 'h5', 'h6'])
        head = next((h for h in heads if txt(h) and txt(h) != 'Bővebben'), None)
        a = e.find('a')
        paras = [txt(p) for p in e.find_all('p') if txt(p) and txt(p) != txt(a)]
        im = e.find('img')
        blocks.append({'title': txt(head), 'text': ' '.join(paras), 'button': txt(a), 'href': '#' + urlparse(a['href']).path,
                       'img': image(im.get('src'), 'site', 'blokk-' + cls, 1600, 80) if im else None,
                       'imgCore': silhouette(im.get('src'), 'blokk-' + cls + '-mag', 1600) if im else None})
    new_ids = []
    for a in hs.find(class_='negyedik').select('.product-name a, h3 a'):
        last = urlparse(a['href']).path.rsplit('/', 1)[1]
        pid = next((v for k, v in path_to_id.items() if k.rsplit('/', 1)[1] == last), None)
        if pid and pid not in new_ids:
            new_ids.append(pid)
    print('új termékek:', len(new_ids))
    age = hs.select_one('.eb-inst, .eb-dialog')
    age_all = txt(age).replace('×', '').strip() if age else ''
    m = re.match(r'(.*?\?)\s*(.*?)\s*Igen\s*Nem\s*(Fontos tájékoztatás!)\s*(.*?)\s*(Felhívjuk figyelmüket, hogy az esetleges.*)$', age_all)
    age_paras = {'title': m.group(1), 'sub': m.group(2), 'noteTitle': m.group(3), 'note': [m.group(4), m.group(5)]} if m else {'raw': age_all}

    # --- statikus képek (fejléc, lábléc, ikonok, hátterek)
    site_img = {}
    for name, url, w in [
        ('logo', '/images/rendszer/logouj.png', 320), ('logoFooter', '/images/rendszer/sluglogo-transformed.png', 400),
        ('logoWord', '/images/rendszer/logo.svg', 600), ('stars', '/images/rendszer/csillagok.svg', 200),
        ('plus', '/images/rendszer/pluszok.svg', 100), ('phone', '/images/rendszer/telefon.svg', 40),
        ('email', '/images/rendszer/email.svg', 40), ('fb', '/images/rendszer/iface.svg', 40),
        ('insta', '/images/rendszer/i-insta.svg', 40), ('yt', '/images/rendszer/i-youtube.svg', 40),
        ('login', '/images/rendszer/belepes.svg', 40), ('register', '/images/rendszer/regisztracio.svg', 40),
        ('cart', '/images/rendszer/kosar.svg', 40), ('arrow', '/images/rendszer/cikkesnyil.svg', 40),
        ('headerBg', '/images/rendszer/manuhatter.png', 1600), ('introBg', '/images/image21.webp', 1800),
        ('contactBg', '/images/image17.webp', 1800), ('footerBg', '/images/image16.png', 1800),
        ('aboutLogo', '/images/rendszer/sluglogo.jpg', 600),
    ]:
        site_img[name] = image(url, 'site', name, w, 75)

    # --- tartalmi oldalak
    def article_body(name, kind='page'):
        s = page(name)
        body = s.select_one('[itemprop=articleBody]') or s.select_one('.item-page')
        h1 = s.select_one('.item-page h1, .page-header h1')
        return txt(h1), clean(body.decode_contents() if body else '', kind)

    pages = {}
    for name in ['szerviz', 'letoltesek', 'rolunk']:
        t, h = article_body(name)
        pages[name] = {'title': t, 'html': h}
    # kapcsolat: cím, telefon, nyitvatartás (az űrlapot a téma adja)
    ks = page('kapcsolat').select_one('[itemprop=articleBody]')
    hours = [[txt(td) for td in tr.find_all('td')] for tr in ks.select('table tr')]
    links = [(txt(a), a.get('href')) for a in ks.find_all('a')]
    pages['kapcsolat'] = {'title': 'Kapcsolat', 'address': links[0][0], 'map': links[0][1], 'phone': links[1][0], 'hours': hours}
    for name, title in [('aszf', 'Általános szerződési feltételek'), ('impresszum', 'Impresszum'),
                        ('adatvedelmi-nyilatkozat', 'Adatvédelmi nyilatkozat')]:
        pages[name] = {'title': title, 'html': ''}

    # videók
    videos = []
    vs = page('videok')
    for item in vs.select('.blog .item'):
        a = item.find('a', href=True)
        if not a:
            continue
        path = urlparse(a['href']).path
        if path in [v['path'] for v in videos]:
            continue
        im = item.find('img')
        sub = page(path.strip('/').replace('/', '__'))
        raw = str(sub)
        ids = []
        for m in re.findall(r'youtube\.com/watch\?v=([\w-]{11})', raw) + re.findall(r'youtube(?:-nocookie)?\.com/embed/([\w-]{11})', raw):
            if m not in ids:
                ids.append(m)
        body = sub.select_one('[itemprop=articleBody]')
        videos.append({'path': path, 'title': txt(sub.select_one('.item-page h1, .page-header h1, h2')) or txt(a),
                       'intro': txt(item.find('p')),
                       'img': image(im.get('src'), 'site', 'video-' + slug(path), 900, 72) if im else None, 'youtube': ids,
                       'items': [youtube_item(i) for i in ids],
                       'html': clean(re.sub(r'https?://(www\.)?youtube\.com/watch\?v=[\w-]{11}', '', body.decode_contents()) if body else '', 'page')})
    print('videóoldal:', len(videos))

    # cikkek
    articles = []
    for blog, lang in [('cikkeink__cikkek', 'hu'), ('cikkeink__articles', 'en')]:
        bs = page(blog)
        for item in bs.select('.blog .item'):
            a = item.select_one('p.readmore a, .page-header a, a[href]')
            if not a:
                continue
            path = urlparse(urljoin(BASE, a['href'])).path
            if not path.startswith('/' + blog.replace('__', '/') + '/') or path in [x['path'] for x in articles]:
                continue
            fn = os.path.join(CACHE, 'art', path.strip('/').replace('/', '__') + '.html')
            if not os.path.exists(fn):
                continue
            s = BeautifulSoup(open(fn, encoding='utf-8', errors='ignore').read(), 'html.parser')
            body = s.select_one('[itemprop=articleBody]')
            heads = [h for h in item.find_all(['h1', 'h2', 'h3', 'h4']) if txt(h)]
            if not heads and body:
                heads = [h for h in body.find_all(['h1', 'h2', 'h3', 'h4']) if txt(h)]
            tt = s.find('title')
            title = fix(tt.get_text(strip=True)) if tt and tt.get_text(strip=True) else (txt(heads[0]) if heads else txt(a))
            if body:
                first = next((h for h in body.find_all(['h1', 'h2', 'h3', 'h4']) if txt(h)), None)
                if first and txt(first) == title:
                    first.decompose()
            im = item.select_one('.item-image img') or (body.find('img') if body else None)
            img_src = im.get('src') if im else None
            html = clean(body.decode_contents() if body else '', 'art')
            html = re.sub(r'^(<h2>\s*</h2>\s*)+', '', html)
            plain = BeautifulSoup(html, 'html.parser').get_text(' ', strip=True)
            articles.append({'path': path, 'lang': lang, 'title': title,
                             'img': image(img_src, 'art', slug(path.rsplit('/', 1)[1]), 1000, 70) if img_src else None,
                             'excerpt': (plain[:220].rsplit(' ', 1)[0] + '…') if len(plain) > 220 else plain, 'html': html})
    print('cikk:', len(articles))
    blog_intro = {}
    for blog in ['cikkeink', 'cikkeink__cikkek', 'cikkeink__articles']:
        s = page(blog)
        desc = s.select_one('.category-desc')
        blog_intro[blog.replace('__', '/')] = clean(desc.decode_contents(), 'page') if desc else ''

    site = {
        'phone': '+36 30 677 7836', 'phoneHref': 'tel:+36306777836', 'email': 'info@slugshop.hu',
        'social': {'facebook': 'https://www.facebook.com/profile.php?id=61554078063288',
                   'instagram': 'https://www.instagram.com/slugshop.hungary/', 'youtube': 'https://www.youtube.com/@laszlomora4678'},
        'company': {'name': 'Slugshop Korlátolt Felelősségű Társaság', 'seat': '5100 Jászberény, Érhát utca 7.',
                    'tax': '32247147-2-16', 'reg': '16-09-022741',
                    'reps': 'Móra László ügyvezető, Tibori Ádám ügyvezető'},
        'menu': [['Zan slugok', '/zan-slugok'], ['Fx fegyverek és kiegészítők', '/fx-termekek'], ['FX Alkatrészek', '/fx-alkatreszek'],
                 ['Snowpeak', '/snowpeak'], ['Letöltések', '/letoltesek'], ['Szerviz', '/szerviz'], ['Videók', '/videok'],
                 ['Cikkeink', '/cikkeink'], ['Kapcsolat', '/kapcsolat']],
        'footerMenu': [['Rólunk', '/rolunk'], ['ÁSZF', '/aszf'], ['Impresszum', '/impresszum'], ['Adatvédelmi nyilatkozat', '/adatvedelmi-nyilatkozat']],
        'img': site_img, 'slides': slides, 'tiles': tiles, 'intro': {'title': intro_title, 'text': intro_text}, 'blocks': blocks,
        'newProducts': new_ids, 'age': age_paras, 'blogIntro': blog_intro,
        'cookie': {'title': 'Hozzájárulást kérünk az Ön adatainak felhasználásához, annak érdekében, hogy minőségibb élményt nyújtsunk.',
                   'text': 'A weboldalon sütiket használunk annak érdekében hogy a legjobb élményben legyen része.'},
        'fixes': [[a, b] for a, b in FIXES],
    }
    data = {'site': site, 'tree': [n['path'] for n in tree], 'cats': catlist, 'products': products,
            'pages': pages, 'videos': videos, 'articles': articles}
    os.makedirs(os.path.join(ROOT, 'assets', 'js'), exist_ok=True)
    with open(os.path.join(ROOT, 'assets', 'js', 'data.js'), 'w', encoding='utf-8') as f:
        f.write('/* GENERÁLT FÁJL – tools/extract.py készíti a régi slugshop.hu tartalmából. Kézzel ne szerkeszd. */\n')
        f.write('window.SLUG = ' + json.dumps(data, ensure_ascii=False, separators=(',', ':')).replace('</', '<\\/') + ';\n')
    size = sum(os.path.getsize(os.path.join(dp, f)) for dp, _, fs in os.walk(IMG) for f in fs)
    print(f'kész: assets/js/data.js ({os.path.getsize(os.path.join(ROOT, "assets/js/data.js")) // 1024} KB), képek: {size // 1024} KB')


if __name__ == '__main__':
    main()
