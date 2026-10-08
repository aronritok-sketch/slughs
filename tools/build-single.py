#!/usr/bin/env python3
"""Egyfájlos, kattintható előnézet: dist/slugshop-elonezet.html

Az index.html-be beágyazza a CSS-t (a betűkkel és háttérképekkel), a JS-t és az összes képet (data URI),
így a fájl dupla kattintással, internet nélkül is megnyílik, e-mailben küldhető, és a CRM ügyfélportál
„Weboldal-előnézet” anyagába is feltölthető. Internet csak a YouTube-videókhoz kell.

Futtatás a repó gyökeréből:  python3 tools/build-single.py
"""
import base64
import mimetypes
import os
import re

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
MIME = {'.webp': 'image/webp', '.svg': 'image/svg+xml', '.png': 'image/png', '.jpg': 'image/jpeg', '.woff2': 'font/woff2'}


def data_uri(path):
    ext = os.path.splitext(path)[1].lower()
    mime = MIME.get(ext) or mimetypes.guess_type(path)[0] or 'application/octet-stream'
    return f'data:{mime};base64,' + base64.b64encode(open(path, 'rb').read()).decode()


def inline_css(rel):
    css_path = os.path.join(ROOT, rel)
    css = open(css_path, encoding='utf-8').read()

    def repl(m):
        url = m.group(2)
        if url.startswith(('data:', 'http', '#')):
            return m.group(0)
        target = os.path.normpath(os.path.join(os.path.dirname(css_path), url))
        return f'url("{data_uri(target)}")' if os.path.exists(target) else m.group(0)

    return re.sub(r'url\((["\']?)([^)"\']+)\1\)', repl, css)


def inline_js(rel):
    js = open(os.path.join(ROOT, rel), encoding='utf-8').read()
    if rel.endswith('data.js'):  # data.js és panthera-data.js
        js = re.sub(r'assets/img/[\w./-]+\.(?:webp|svg|png|jpg)',
                    lambda m: data_uri(os.path.join(ROOT, m.group(0))) if os.path.exists(os.path.join(ROOT, m.group(0))) else m.group(0), js)
    return js.replace('</script', '<\\/script')


def main():
    html = open(os.path.join(ROOT, 'index.html'), encoding='utf-8').read()
    html = re.sub(r'<link rel="stylesheet" href="([^"]+)">', lambda m: f'<style>\n{inline_css(m.group(1))}\n</style>', html)
    html = re.sub(r'<script src="([^"]+)"></script>', lambda m: f'<script>\n{inline_js(m.group(1))}\n</script>', html)
    os.makedirs(os.path.join(ROOT, 'dist'), exist_ok=True)
    out = os.path.join(ROOT, 'dist', 'slugshop-elonezet.html')
    open(out, 'w', encoding='utf-8').write(html)
    print(f'dist/slugshop-elonezet.html  {os.path.getsize(out) / 1024 / 1024:.1f} MB')


if __name__ == '__main__':
    main()
