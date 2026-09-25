#!/usr/bin/env python3
"""Egyetlen, önálló HTML fájlt készít a prototípusból (dist/slugshop-prototipus.html).

Minden oldal <template>-be kerül, a CSS és a JS beágyazva. A fájl bárhol
megnyitható, nem kell hozzá szerver; oldalváltás a # utáni részből történik.
Futtatás a repó gyökeréből:  python3 tools/build-single.py
"""
import html
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
PAGES = ["index", "kategoria", "termek", "kosar", "penztar", "kapcsolat", "szerviz",
         "videok", "letoltesek", "cikkek", "cikk", "rolunk", "utmutato"]
FONTS = re.search(r'<link rel="stylesheet" href="(https://fonts\.googleapis\.com[^"]+)">',
                  (ROOT / "index.html").read_text(encoding="utf-8")).group(1)


def read(rel):
    return (ROOT / rel).read_text(encoding="utf-8")


def page_parts(name):
    src = read(f"{name}.html")
    title = re.search(r"<title>(.*?)</title>", src, re.S).group(1)
    page = re.search(r'<body data-page="([^"]*)"', src).group(1)
    main = re.search(r'<main id="main">(.*)</main>', src, re.S).group(1)
    return title, page, main


def inline_js(rel):
    js = read(rel)
    assert "</script" not in js.lower(), rel
    return js


templates = []
for name in PAGES:
    title, page, main = page_parts(name)
    templates.append(f'<template data-file="{name}" data-page="{page}" data-title="{html.escape(title, quote=True)}">{main}</template>')

out = f"""<!doctype html>
<html lang="hu">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Slugshop | Légfegyverek és ZAN lövedékek</title>
<meta name="description" content="A slugshop.hu új frontendjének kattintható prototípusa, egy fájlban.">
<meta name="theme-color" content="#10120d">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="{FONTS}">
<style>
{read("assets/css/style.css")}
.proto-pill {{ position: fixed; z-index: 55; left: 16px; bottom: calc(16px + env(safe-area-inset-bottom, 0px)); display: inline-flex; align-items: center; gap: 8px; padding: 9px 14px; background: var(--tan); color: var(--bg); font-family: var(--f-mono); font-size: .75rem; font-weight: 600; letter-spacing: .08em; text-transform: uppercase; box-shadow: var(--shadow); }}
.proto-pill:hover {{ background: var(--tan-2); }}
</style>
</head>
<body data-page="">
<div id="site-header"></div>
<main id="main"></main>
<div id="site-footer"></div>
<a class="proto-pill" href="utmutato.html">? Útmutató</a>
{"".join(templates)}
<script>window.SLUG_BUNDLE = true;</script>
<script>
{inline_js("assets/js/data.js")}
</script>
<script>
{inline_js("assets/js/app.js")}
</script>
</body>
</html>
"""

dist = ROOT / "dist"
dist.mkdir(exist_ok=True)
target = dist / "slugshop-prototipus.html"
target.write_text(out, encoding="utf-8")
print(f"{target.relative_to(ROOT)}  {len(out.encode()) // 1024} KB")
