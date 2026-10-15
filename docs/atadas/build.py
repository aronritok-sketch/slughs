"""
A Slugshop élesítési anyagainak HTML-je és a portál-kérdéssor: kerdessor.html (ügyfél), laci.html (fejlesztő),
../kerdoiv/slugshop-kerdoiv.json (portál: CRM → ügyfél → Onboarding kérdőív → Egyedi kérdéssor), Word-változat.
Futtatás (ebben a mappában): python3 build.py && node pdf.js
A kérdéssor forrása a kerdesek.py, a teendőlistáé a laci.py. Minta és stílus: hlprv alphamovers/docs/atadas
(HelloProVision: Satoshi cím, Inter szöveg, lime, sötét borító).
"""
import html
import json
import os

import kerdesek as Q
import laci

HERE = os.path.dirname(os.path.abspath(__file__))
E = html.escape
DATE = "2026. október"


def head(title):
    return (
        '<!doctype html>\n<html lang="hu">\n<head>\n<meta charset="utf-8">\n'
        '<meta name="viewport" content="width=device-width, initial-scale=1">\n'
        f"<title>{E(title)}</title>\n<link rel=\"stylesheet\" href=\"hpv.css\">\n</head>\n<body>\n"
    )


def cover(title_html, lead, meta):
    cells = "".join(f"<div>{E(k)}<b>{E(v)}</b></div>" for k, v in meta)
    return (
        '<section class="page cover">\n'
        '<div class="brand"><img src="assets/mark.svg" alt=""><span>HelloProVision</span></div>\n'
        f"<h1>{title_html}</h1>\n<p class=\"lead\">{lead}</p>\n"
        '<div class="tag"><span>Design</span><span>Build</span><span>Grow</span></div>\n'
        f'<div class="meta">{cells}</div>\n</section>\n'
    )


def foot(left):
    return f'<div class="foot"><span><b>Slugshop</b> · {E(left)}</span><span></span></div>'


class Pager:
    """Blokkok folyó A4 oldalakra; a pdf.js a böngészőben méri, és ami nem fér ki, új oldalra teszi."""

    def __init__(self, label):
        self.label, self.blocks = label, []

    def add(self, block_html, keep_with_next=False):
        cls = ' class="flow-keep"' if keep_with_next else ""
        self.blocks.append(f"<div{cls}>{block_html}</div>")

    def render(self):
        return '<section class="page flow">\n' + "\n".join(self.blocks) + "\n" + foot(self.label) + "\n</section>\n"


def build_questionnaire():
    doc = head(Q.TITLE)
    doc += cover("Slugshop<br>új webshop <em>kérdéssor</em>", E(Q.INTRO),
                 [("Weboldal", "slugshop.hu"), ("Kinek", "Slugshop Kft."), ("Dátum", DATE)])
    pager = Pager("kérdéssor")
    pager.add('<span class="eyebrow">Mielőtt kitöltöd</span><h2>Sziasztok!</h2>'
              f'<p class="lead">{E(Q.INTRO)}</p>'
              '<div class="box tip"><span class="eyebrow">Kitöltés</span><p>A legegyszerűbb a HelloProVision ügyfélportálon: '
              'ott részenként kitölthetitek, közben menthetitek. Ha papíron jobb, írjatok a vonalakra; Word-változat is van '
              '(Slugshop – kérdéssor.docx). A *-gal jelölt kérdések a fontosak.</p></div>')
    for n, (_key, title, intro, qs) in enumerate(Q.SECTIONS, 1):
        head_html = f'<span class="num">Z{n:02d}</span><h2>{E(title)}</h2>' + (f'<p class="sec-intro">{E(intro)}</p>' if intro else "")
        pager.add(head_html, keep_with_next=True)
        for _k, q, typ, req, hint in qs:
            block = f'<div class="q"><div class="qt">{E(q)}{" *" if req else ""}</div>'
            if hint:
                block += f'<div class="qh">{E(hint)}</div>'
            block += '<div class="line"></div>' * (3 if typ == "textarea" else 1) + "</div>"
            pager.add(block)
    pager.add('<div class="box ok"><span class="eyebrow">Köszönjük</span><p>Jelszavakat, kulcsokat ne írjatok ide: azokat '
              'a portálon külön kérjük. Ha kérdésetek van, írjatok a portál Üzenetek menüjében.</p>'
              '<p><b>HelloProVision</b> · Tervezzük. Építjük. Növeljük.</p></div>')
    doc += pager.render() + "</body>\n</html>\n"
    with open(os.path.join(HERE, "kerdessor.html"), "w", encoding="utf-8") as f:
        f.write(doc)
    out = os.path.join(HERE, "..", "kerdoiv")
    os.makedirs(out, exist_ok=True)
    with open(os.path.join(out, "slugshop-kerdoiv.json"), "w", encoding="utf-8") as f:
        json.dump(Q.portal_json(), f, ensure_ascii=False, indent=1)


def build_tasks():
    total = sum(len(s["items"]) for s in laci.SECTIONS)
    doc = head(laci.TITLE)
    doc += cover("Slugshop<br><em>élesítés</em> – teendők", E(laci.INTRO[0]),
                 [("Kinek", "Laci (fejlesztő)"), ("Feladat", f"{total} lépés, {len(laci.SECTIONS)} fázis"), ("Dátum", DATE)])
    pager = Pager("élesítés – teendők")
    first = '<span class="eyebrow">Hogyan használd</span><h2>Laci, ez a teljes lista</h2>' + "".join(
        f'<p class="lead">{E(p)}</p>' for p in laci.INTRO)
    first += "<h3>Ami kell hozzá</h3><table><tr><th>Anyag</th><th>Mi van benne</th></tr>" + "".join(
        f"<tr><td>{E(a)}</td><td>{E(b)}</td></tr>" for a, b in laci.DOCS) + "</table>"
    first += '<h3>A fázisok</h3><ol class="toc">' + "".join(
        f'<li><span>{s["num"]}</span>{E(s["title"])} ({len(s["items"])})</li>' for s in laci.SECTIONS) + "</ol>"
    pager.add(first)
    for s in laci.SECTIONS:
        h = f'<span class="num">{s["num"]}</span><h2>{E(s["title"])}</h2>'
        if s.get("note"):
            h += f'<div class="box {s["kind"] or ""}"><p>{E(s["note"])}</p></div>'
        pager.add(h, keep_with_next=True)
        for title, how in s["items"]:
            pager.add(f'<ul class="check"><li><b>{E(title)}</b><span class="how">{E(how)}</span></li></ul>')
    pager.add('<div class="box ok"><span class="eyebrow">Ha elakadsz</span><p>Írd le a CRM-feladatba, hol tartasz és mi a hiba '
              '(képernyőképpel). A kódot érintő kérdésekhez: docs/wp/DEVELOPER.md; a forrás: aronritok-sketch/slughs, '
              'claude/wizardly-bell-40450y ág.</p><p><b>HelloProVision</b> · Tervezzük. Építjük. Növeljük.</p></div>')
    doc += pager.render() + "</body>\n</html>\n"
    with open(os.path.join(HERE, "laci.html"), "w", encoding="utf-8") as f:
        f.write(doc)


if __name__ == "__main__":
    build_questionnaire()
    build_tasks()
    print("kerdessor.html, laci.html, ../kerdoiv/slugshop-kerdoiv.json")
