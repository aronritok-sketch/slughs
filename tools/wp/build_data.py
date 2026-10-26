#!/usr/bin/env python3
"""A WordPress-téma telepítőjének adatai és blokk-tartalma a jóváhagyott előnézetből.

Bemenet:  assets/js/data.js + panthera-data.js (tools/extract.py, tools/panthera.py), cache/crawl.json (a régi oldal),
          build/media/ (tools/wp/media.py: teljes méretű termékképek, letölthető PDF-ek)
Kimenet:  wp-theme/slugshop/setup/data/{site,categories,products,pages,posts,downloads,panthera}.json
          wp-theme/slugshop/setup/content/{pages,posts}/*.html   (blokk-markup; a tools/wp/canon.mjs teszi kanonikussá)
          wp-theme/slugshop/templates/*.html                    (iu_theme sablonok)
          build/media/{cats,articles,pages}/…                    (a médiacsomaghoz a termékképek és PDF-ek mellé)

Futtatás a repó gyökeréből:  python3 tools/wp/build_data.py
"""
import json
import os
import re
import shutil
import sys

from bs4 import BeautifulSoup, Comment, NavigableString

HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, HERE)
from data import ROOT, load_panthera, load_preview  # noqa: E402
import iu  # noqa: E402

THEME = os.path.join(ROOT, 'wp-theme', 'slugshop')
DATA = os.path.join(THEME, 'setup', 'data')
CONTENT = os.path.join(THEME, 'setup', 'content')
TEMPLATES = os.path.join(THEME, 'templates')
MEDIA = os.path.join(ROOT, 'build', 'media')


def write(path, text):
    os.makedirs(os.path.dirname(path), exist_ok=True)
    with open(path, 'w', encoding='utf-8') as f:
        f.write(text.rstrip() + '\n')


def dump(name, data):
    write(os.path.join(DATA, name + '.json'), json.dumps(data, ensure_ascii=False, indent=1))


def site_img(rel):
    """assets/img/site/x.webp → x.webp (a téma assets/img mappájában)."""
    return os.path.basename(rel) if rel else ''


def href(h):
    return (h or '').replace('#/', '/') or '/'


# ---------------------------------------------------------------- HTML → blokkok

INLINE = {'a', 'b', 'strong', 'em', 'i', 'u', 'br', 'span', 'sup', 'sub', 'code', 'mark', 'small', 's'}


def inner(el):
    return ''.join(str(c) for c in el.contents).strip()


def clean_attrs(soup):
    for c in soup.find_all(string=lambda t: isinstance(t, Comment)):
        c.extract()
    for el in soup.find_all(True):
        keep = {'href', 'src', 'alt', 'target', 'rel', 'colspan', 'rowspan', 'title'}
        el.attrs = {k: v for k, v in el.attrs.items() if k in keep}
        if el.name == 'a' and el.get('href', '').startswith('#/'):
            el['href'] = '[iu_site_url]' + el['href'][2:]
        if el.name == 'a' and not el.get('href'):
            # a régi oldal elrejtett e-mail címe (<a>info@…</a>): legyen valódi link
            text = el.get_text(strip=True)
            if re.fullmatch(r'[\w.+-]+@[\w-]+\.[\w.]+', text):
                el['href'] = 'mailto:' + text
            else:
                el.unwrap()
    return soup


def media_src(src):
    """assets/img/art/x.webp → {{media:articles/x.webp}} (a telepítő cseréli a médiatár URL-jére)."""
    m = re.match(r'assets/img/(art|page)/(.+)$', src or '')
    if not m:
        return src
    folder = 'articles' if m.group(1) == 'art' else 'pages'
    os.makedirs(os.path.join(MEDIA, folder), exist_ok=True)
    shutil.copy2(os.path.join(ROOT, src), os.path.join(MEDIA, folder, m.group(2)))
    return '{{media:%s/%s}}' % (folder, m.group(2))


def html_to_blocks(html, demote_h1=True):
    """A régi oldal tisztított HTML-je → core blokkok (bekezdés, címsor, lista); ami más (kép, táblázat, vonal, idézet),
    az core/html-be kerül (a WordPress 7.1-ben a core/html tartalma nem attribútum, a szerkesztő nem írja át)."""
    soup = clean_attrs(BeautifulSoup(html or '', 'html.parser'))
    for img in soup.find_all('img'):
        img['src'] = media_src(img.get('src'))
        img['loading'] = 'lazy'
        img.attrs.setdefault('alt', '')
    out = []
    buf = []

    def flush():
        text = ''.join(buf).strip()
        buf.clear()
        if text and re.sub(r'<br\s*/?>', '', text).strip():
            out.append(iu.comment('paragraph', None, f'<p class="iu-text">{text}</p>'))

    for el in list(soup.children):
        if isinstance(el, NavigableString):
            buf.append(str(el))
            continue
        name = el.name
        if name in INLINE:
            buf.append(str(el))
            continue
        flush()
        if name == 'p':
            if el.find('img') and not el.get_text(strip=True):
                out.append(iu.comment('html', None, f'<figure class="wp-figure">{inner(el)}</figure>'))
            elif inner(el):
                out.append(iu.comment('paragraph', None, f'<p class="iu-text">{inner(el)}</p>'))
        elif name in ('h1', 'h2', 'h3', 'h4', 'h5', 'h6'):
            level = int(name[1])
            if demote_h1 and level == 1:
                level = 2
            text = inner(el)
            if el.find(['b', 'strong']) and len(el.find_all(True)) == 1 and el.get_text(strip=True) == el.find(['b', 'strong']).get_text(strip=True):
                text = inner(el.find(['b', 'strong']))  # a régi szerkesztő <h2><b>…</b></h2>-t írt
            attrs = {'level': level} if level != 2 else None
            out.append(iu.comment('heading', attrs, f'<h{level} class="wp-block-heading iu-heading">{text}</h{level}>'))
        elif name in ('ul', 'ol') and not el.find(['ul', 'ol', 'table', 'img']):
            items = []
            for li in el.find_all('li', recursive=False):
                t = inner(li)
                t = re.sub(r'^<p>(.*)</p>$', r'\1', t, flags=re.S).strip()
                if t:
                    items.append(iu.comment('list-item', None, f'<li class="iu-list-item">{t}</li>'))
            if items:
                attrs = {'ordered': True} if name == 'ol' else None
                tag = name
                out.append(iu.comment('list', attrs, f'<{tag} class="wp-block-list iu-list">' + ''.join(items) + f'</{tag}>'))
        else:
            out.append(iu.comment('html', None, str(el)))
    flush()
    return '\n\n'.join(out)


# ---------------------------------------------------------------- sablonok és oldalak

def sec(*blocks, className='', row=True):
    """iu/section → iu/row → iu/column → blokkok (egyoszlopos)."""
    if not row:
        return iu.section(*blocks, className=className)
    return iu.section(iu.row(iu.col(*blocks)), className=className)


def dyn(name, **attrs):
    return iu.comment('slugshop/' + name, attrs or None)


def full(name, className, **attrs):
    """Teljes szélességű saját blokk közvetlenül a szekcióban (a blokk maga rajzolja a belső elrendezést)."""
    return iu.section(dyn(name, **attrs), className='slug-full ' + className)


def templates():
    t = {
        'global_header': full('header', 'slug-chrome'),
        'global_footer': full('footer', 'slug-chrome'),
        'front_page_content': '<!-- wp:iu/content /-->',
        'single_page_content': full('page-head', 'slug-page-head') + '\n\n<!-- wp:iu/content /-->',
        'tax_product_cat_content': full('category', 'slug-page'),
        'archive_product_content': full('category', 'slug-page'),
        'single_product_content': full('product', 'slug-page'),
        'search_content': full('search', 'slug-page'),
        '404_content': full('not-found', 'slug-page'),
        'single_post_content': full('article', 'slug-page'),
        'tax_category_content': full('blog', 'slug-page'),
        'blog_page_content': full('blog', 'slug-page'),
    }
    for name, body in t.items():
        write(os.path.join(TEMPLATES, name + '.html'), body)


def page_body(*blocks, className='prose'):
    """Oldal-tartalom: egy szekció, egy oszlop, benne a blokkok (a cím és a morzsamenü a sablonból jön)."""
    return iu.section(iu.row(iu.col(*blocks, className=className)), className='slug-page-body')


def main():
    D = load_preview()
    P = load_panthera()
    site = D['site']
    os.makedirs(MEDIA, exist_ok=True)

    # ------------------------------------------------ site.json
    keys = {}
    for key, p in D['products'].items():
        keys[key] = p['path'].rsplit('/', 1)[1]
    k = D['pages']['kapcsolat']
    videos = []
    for v in D['videos']:
        videos.append({'path': v['path'], 'title': v['title'], 'img': site_img(v.get('img')),
                       'items': [{'id': it['id'], 'title': it['title'], 'thumb': site_img(it.get('thumb'))} for it in v.get('items', [])]})
    index = open(os.path.join(ROOT, 'cache', 'pages', 'index.html'), encoding='utf-8', errors='ignore').read()
    m = re.search(r'<meta name="description" content="([^"]*)"', index)
    home_desc = BeautifulSoup(m.group(1), 'html.parser').get_text() if m else ''
    out_site = {
        'phone': site['phone'], 'email': site['email'], 'social': site['social'],
        # a cégjegyzékszám 16-os előtagja: Jász-Nagykun-Szolnok megye → Szolnoki Törvényszék Cégbírósága
        'company': {**site['company'], 'court': 'Szolnoki Törvényszék Cégbírósága' if site['company']['reg'].startswith('16-') else '', 'host': ''},
        'contact': {'address': k['address'], 'street': 'Megyeház u. 3.', 'map': k['map'], 'hours': k['hours']},
        'menu': [[t, href(p)] for t, p in site['menu']],
        'footerMenu': [[t, href(p)] for t, p in site['footerMenu']],
        'slides': [{**s, 'img': site_img(s['img']), 'href': href(s['href'])} for s in site['slides']],
        'tiles': [{**t, 'img': site_img(t.get('img')), 'href': href(t['href'])} for t in site['tiles']],
        'intro': site['intro'],
        'blocks': [{**b, 'img': site_img(b.get('img')), 'imgCore': site_img(b.get('imgCore')), 'href': href(b['href'])} for b in site['blocks']],
        'newProducts': [keys[i] for i in site['newProducts'] if i in keys],
        'age': site['age'], 'cookie': site['cookie'],
        'blogIntro': {k2: v2 for k2, v2 in site['blogIntro'].items()},
        'videos': videos,
        'trackers': {'gtm': 'GTM-K2DT9KZ8', 'fbPixel': '1273288477167785', 'barionPixel': 'BP-hkJPSBB2DS-0D', 'fogyasztobarat': 'MB0C642A', 'fogyasztobaratBadge': True},
        'seo': {'homeTitle': 'Légfegyverek és ZAN lövedékek', 'homeDescription': home_desc},
        'legacyMap': {'/bejelentkezes': '/fiokom', '/regisztracio': '/fiokom?regisztracio=1', '/kosar/penztar': '/penztar'},
        'currencySwitcher': False,
    }
    dump('site', out_site)

    # ------------------------------------------------ kategóriák (a régi fa sorrendjében)
    cats = []

    def walk(path, parent):
        c = D['cats'][path]
        img = ''
        if c.get('img'):
            os.makedirs(os.path.join(MEDIA, 'cats'), exist_ok=True)
            img = 'cats/' + os.path.basename(c['img'])
            shutil.copy2(os.path.join(ROOT, c['img']), os.path.join(MEDIA, img))
        cats.append({'path': path, 'slug': path.rsplit('/', 1)[1], 'name': c['name'], 'menu': c.get('menu') or c['name'],
                     'parent': parent, 'desc': c.get('desc') or '', 'img': img})
        for ch in c.get('children', []):
            walk(ch, path)

    for top in D['tree']:
        walk(top, None)
    # a régi fában szülő nélkül szereplő kategóriák (pl. /minden-termek/zan-pellet)
    for path in D['cats']:
        if path not in {c['path'] for c in cats}:
            walk(path, None)
    dump('categories', cats)

    # ------------------------------------------------ termékek
    manifest = json.load(open(os.path.join(MEDIA, 'manifest.json'))) if os.path.exists(os.path.join(MEDIA, 'manifest.json')) else {}
    products = []
    for key, p in D['products'].items():
        slug = p['path'].rsplit('/', 1)[1]
        prefix = p['path'].rsplit('/', 1)[0]
        primary = prefix if prefix in p['cats'] else p['cats'][0]
        images = []
        for n in range(1, 9):
            rel = f'products/{slug}{"" if n == 1 else f"-{n}"}.webp'
            if rel in manifest:
                src = manifest[rel]
                images.append([rel, src if src.startswith('/') and not src.startswith(ROOT) else ''])
        products.append({
            'key': key, 'slug': slug, 'path': p['path'], 'primary': primary, 'cats': p['cats'],
            'name': p['name'], 'price': p['price'], 'stock': p['stock'], 'stockText': p['stockText'], 'sku': p['sku'] or '',
            'short': p['short'] or '', 'desc': p['desc'] or '', 'meta': '', 'images': images,
        })
    dump('products', products)
    # termékképek nélkül maradt (a régi oldalon sem volt kép, vagy sérült)
    print('kép nélkül:', sum(1 for p in products if not p['images']))

    # ------------------------------------------------ letöltések, Panthera
    downloads = json.load(open(os.path.join(MEDIA, 'downloads.json'))) if os.path.exists(os.path.join(MEDIA, 'downloads.json')) else []
    dump('downloads', [{'title': d['title'], 'file': d['file'], 'src': d['src']} for d in downloads if d.get('ok')])
    dump('panthera', {'model': P['model'], 'pdf': P.get('pdf', '')})
    shutil.copy2(os.path.join(ROOT, 'assets', 'img', 'panthera', 'render.webp'), os.path.join(THEME, 'assets', 'img', 'panthera-render.webp'))

    # ------------------------------------------------ oldalak
    pages = []

    def page(slug, title, content, parent=None, order=0, **extra):
        pages.append({'slug': slug, 'title': title, 'parent': parent, 'order': order, **extra})
        write(os.path.join(CONTENT, 'pages', slug + '.html'), content)

    home = '\n\n'.join([
        full('hero', 'slug-hero'), full('intro', 'slug-intro'), full('features', 'slug-features'),
        full('panthera', 'slug-panthera'), full('new-products', 'slug-new'), full('videos', 'slug-videos'),
        full('contact-band', 'slug-contact'), full('articles', 'slug-articles'),
    ])
    page('kezdolap', 'Kezdőlap', home)
    page('minden-termek', 'Minden termék', page_body(iu.para('Termékeink kategóriák szerint.')))
    page('kosar', 'Kosár', page_body(iu.comment('shortcode', None, '[woocommerce_cart]'), className='wc-page'))
    page('penztar', 'Pénztár', page_body(iu.comment('shortcode', None, '[woocommerce_checkout]'), className='wc-page'))
    page('fiokom', 'Fiókom', page_body(iu.comment('shortcode', None, '[woocommerce_my_account]'), className='wc-page'))
    for slug in ('szerviz', 'rolunk'):
        page(slug, D['pages'][slug]['title'], page_body(html_to_blocks(D['pages'][slug]['html'])))
    page('letoltesek', D['pages']['letoltesek']['title'], page_body(html_to_blocks(D['pages']['letoltesek']['html']), dyn('downloads')))
    page('kapcsolat', 'Kapcsolat', page_body(dyn('contact'), className='contact-col'))
    page('aszf', 'Általános szerződési feltételek', page_body(dyn('legal', type='aszf')))
    page('adatvedelmi-nyilatkozat', 'Adatvédelmi nyilatkozat', page_body(dyn('legal', type='adatkezeles')))
    page('impresszum', 'Impresszum', page_body(dyn('company')))
    page('videok', 'Videók', page_body(dyn('video-list'), className=''))
    for i, v in enumerate(D['videos']):
        slug = v['path'].rsplit('/', 1)[1]
        body = html_to_blocks(re.sub(r'<ul>.*</ul>', '', v['html'], flags=re.S))  # a régi videólista a blokkba kerül
        page(slug, v['title'], page_body(dyn('video-grid', ids='\n'.join(v['youtube'])), body), parent='videok', order=i)
    page('cikkeink', 'Cikkeink', iu.section(dyn('blog'), className='slug-full slug-page'))
    dump('pages', pages)

    # ------------------------------------------------ cikkek
    posts = []
    for a in D['articles']:
        slug = a['path'].rsplit('/', 1)[1]
        cat = 'articles' if '/articles/' in a['path'] else 'cikkek'
        img = ''
        if a.get('img'):
            img = media_src(a['img']).strip('{}').split(':', 1)[1]
        body = a['html']
        if a.get('img'):
            # a kiemelt kép a sablonból jön; ha a szöveg elején is ott van, ne legyen kétszer
            body = re.sub(r'^\s*<(p|figure)[^>]*>\s*<img[^>]*' + re.escape(a['img']) + r'[^>]*>\s*</\1>', '', body)
        posts.append({'slug': slug, 'cat': cat, 'title': a['title'], 'excerpt': a.get('excerpt', ''), 'img': img})
        write(os.path.join(CONTENT, 'posts', slug + '.html'), html_to_blocks(body))
    dump('posts', posts)

    templates()
    import fixes  # az ügyfél javításkérései (2026-10-26) – a régi oldal adatai fölött
    fixes.main()
    print(f'{len(cats)} kategória, {len(products)} termék, {len(pages)} oldal, {len(posts)} cikk, {len(downloads)} PDF')


if __name__ == '__main__':
    main()
