"""A kérdéssor kitölthető Word-változata (az Alpha Movers generátorából). Futtatás ebben a mappában: python3 build_docx.py
A portál-JSON-t és a PDF-et a build.py készíti; mindhárom forrása a kerdesek.py."""

import json
import sys
from pathlib import Path

from docx import Document
from docx.oxml import OxmlElement
from docx.oxml.ns import qn
from docx.shared import Mm, Pt, RGBColor

sys.path.insert(0, str(Path(__file__).resolve().parent))
import kerdesek as Q  # noqa: E402

OUT = Path(__file__).resolve().parent.parent / "kerdoiv"
INK, MUTED, BLUE = RGBColor(0x25, 0x2C, 0x33), RGBColor(0x5A, 0x66, 0x72), RGBColor(0xB8, 0x5F, 0x00)
INTRO = "Sziasztok! " + Q.INTRO


def shade(cell, fill):
    pr = cell._tc.get_or_add_tcPr()
    shd = OxmlElement("w:shd")
    shd.set(qn("w:val"), "clear")
    shd.set(qn("w:color"), "auto")
    shd.set(qn("w:fill"), fill)
    pr.append(shd)


def borders(table, color="DBE5EE"):
    b = OxmlElement("w:tblBorders")
    for edge in ("top", "left", "bottom", "right", "insideH", "insideV"):
        el = OxmlElement("w:" + edge)
        el.set(qn("w:val"), "single")
        el.set(qn("w:sz"), "6")
        el.set(qn("w:color"), color)
        b.append(el)
    table._tbl.tblPr.append(b)


def min_height(row, twips):
    pr = row._tr.get_or_add_trPr()
    h = OxmlElement("w:trHeight")
    h.set(qn("w:val"), str(twips))
    h.set(qn("w:hRule"), "atLeast")
    pr.append(h)


def docx() -> None:
    doc = Document()
    s = doc.sections[0]
    s.page_width, s.page_height = Mm(210), Mm(297)
    s.left_margin = s.right_margin = Mm(18)
    s.top_margin = s.bottom_margin = Mm(16)
    normal = doc.styles["Normal"]
    normal.font.name = "Arial"
    normal.font.size = Pt(10)
    normal.font.color.rgb = INK
    normal.element.rPr.rFonts.set(qn("w:eastAsia"), "Arial")
    p = doc.add_paragraph()
    r = p.add_run("SLUGSHOP · ÚJ WEBSHOP")
    r.bold, r.font.size, r.font.color.rgb = True, Pt(9), BLUE
    p = doc.add_paragraph()
    r = p.add_run("Kérdéssor az élesítéshez")
    r.bold, r.font.size = True, Pt(20)
    doc.add_paragraph(INTRO).runs[0].font.color.rgb = MUTED
    n = 0
    for _skey, title, intro, qs in Q.SECTIONS:
        p = doc.add_paragraph()
        p.paragraph_format.space_before = Pt(14)
        r = p.add_run(title)
        r.bold, r.font.size, r.font.color.rgb = True, Pt(13), BLUE
        if intro:
            doc.add_paragraph(intro).runs[0].font.color.rgb = MUTED
        t = doc.add_table(rows=0, cols=2)
        borders(t)
        for key, q, typ, req, hint in qs:
            n += 1
            row = t.add_row()
            min_height(row, 900 if typ == "textarea" else 600)
            c0, c1 = row.cells
            c0.width, c1.width = Mm(88), Mm(86)
            shade(c0, "FDF1E3")
            para = c0.paragraphs[0]
            rr = para.add_run(f"{n}. {q}{' *' if req else ''}")
            rr.bold = True
            if hint:
                h = c0.add_paragraph().add_run(hint)
                h.font.size, h.font.color.rgb = Pt(8.5), MUTED
    doc.add_paragraph()
    doc.add_paragraph("Köszönjük! A kitöltött dokumentumot töltsétek fel a portál Fájlok menüjébe. Jelszót, kulcsot ide ne írjatok: azokat a portálon külön kérjük.").runs[0].font.color.rgb = MUTED
    doc.save(OUT / "Slugshop – kérdéssor.docx")


def main() -> None:
    OUT.mkdir(parents=True, exist_ok=True)
    docx()
    n = sum(len(qs) for *_x, qs in Q.SECTIONS)
    print(f"Slugshop – kérdéssor.docx: {n} kérdés")


if __name__ == "__main__":
    main()
