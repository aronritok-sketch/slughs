"""iu_theme blokk-markup generátor (az Alpha Movers generátorából, a Bébiszitter Akadémia blokkjaival bővítve).

A kép, csúszka és űrlap blokkok mentett HTML-je itt csak közelítő: a tools/canon.mjs a valódi blokkszerkesztőben
kanonikus alakra hozza (createBlock + serialize), így a szerkesztőben nincs „váratlan tartalom”.

A statikus iu blokkok mentett HTML-je pontosan a valódi keretrendszer block.js save() kimenete (a csapat dev szerverén futó
iu_theme 0.1.0 alapján, 2026-09), a core blokkoké a keretrendszer block-utils.js szűrőivel együtt:
- core/heading: <h2 class="wp-block-heading iu-heading">, core/paragraph: <p class="iu-text">,
- core/list: <ul class="wp-block-list iu-list">, core/list-item: <li class="iu-list-item">,
- iu/section / row / column / group / button-group / button: a block.js szerinti osztályokkal.
Az alapértelmezett attribútumok (pl. columns "1-1", width "1-1", heading level 2, button type "default") nem kerülnek
a komment-JSON-ba, ahogy a szerkesztő sem írja ki őket. A komment-JSON-ban a <, >, &, " és -- escape-elve (\\u003c alak).
"""

import json
import re


def attrs_json(attrs: dict) -> str:
    if not attrs:
        return ""
    s = json.dumps(attrs, ensure_ascii=False, separators=(",", ":"))
    s = s.replace("<", "\\u003c").replace(">", "\\u003e").replace("&", "\\u0026").replace("--", "\\u002d\\u002d")
    # a stringen belüli \" → "
    s = s.replace('\\"', "\\u0022")
    return " " + s


def comment(name: str, attrs: dict | None = None, inner: str | None = None) -> str:
    a = attrs_json(attrs or {})
    if inner is None:
        return f"<!-- wp:{name}{a} /-->"
    return f"<!-- wp:{name}{a} -->\n{inner}\n<!-- /wp:{name} -->"


def cls(*names: str) -> str:
    return " ".join(n for n in names if n)


# ---------------------------------------------------------------- Szerkezet

def section(*rows: str, className: str = "", fullWidth: bool = False, lightText: bool = False) -> str:
    attrs = {}
    if lightText:
        attrs["lightText"] = True
    if fullWidth:
        attrs["fullWidth"] = True
    if className:
        attrs["className"] = className
    html_cls = cls("iu-section", "iu-section-light" if lightText else "", "iu-section-fullwidth" if fullWidth else "", className)
    return comment("iu/section", attrs, f'<section class="{html_cls}">' + "\n".join(rows) + "</section>")


def row(*cols: str, columns: str = "1-1", vertical: str = "", className: str = "") -> str:
    attrs = {}
    if columns != "1-1":
        attrs["columns"] = columns
    if vertical:
        attrs["vertical"] = vertical
    if className:
        attrs["className"] = className
    html_cls = cls("iu-row", f"iu-row-vertical-{vertical}" if vertical else "", className)
    return comment("iu/row", attrs, f'<div class="{html_cls}">' + "\n".join(cols) + "</div>")


def col(*blocks: str, width: str = "1-1", className: str = "", align: str = "") -> str:
    attrs = {}
    if width != "1-1":
        attrs["width"] = width
    if align:
        attrs["align"] = align
    if className:
        attrs["className"] = className
    html_cls = cls("iu-column", f"iu-column-{width}", f"iu-column-align-{align}" if align else "", className)
    return comment("iu/column", attrs, f'<div class="{html_cls}">' + "\n".join(blocks) + "</div>")


def group(*blocks: str, direction: str = "", horizontal: str = "", vertical: str = "", className: str = "") -> str:
    attrs = {}
    for k, v in (("direction", direction), ("horizontal", horizontal), ("vertical", vertical)):
        if v:
            attrs[k] = v
    if className:
        attrs["className"] = className
    html_cls = cls("iu-group", "iu-group-direction-column" if direction == "column" else "",
                   f"iu-group-horizontal-{horizontal}" if horizontal else "", f"iu-group-vertical-{vertical}" if vertical else "", className)
    return comment("iu/group", attrs, f'<div class="{html_cls}">' + "\n".join(blocks) + "</div>")


# ---------------------------------------------------------------- Tartalom

def inline(text: str) -> str:
    """**félkövér** és [link](/url/) → HTML; a belső linkek [iu_site_url]-lel (alkönyvtáras telepítésnél is jók)."""
    text = re.sub(r"\*\*(.+?)\*\*", r"<strong>\1</strong>", text)
    return re.sub(r"\[([^\]]+)\]\((/[^)]*|https?://[^)]*)\)",
                  lambda m: f'<a href="{site_url(m.group(2))}">{m.group(1)}</a>', text)


def site_url(url: str) -> str:
    return "[iu_site_url]" + url.lstrip("/") if url.startswith("/") else url


def heading(text: str, level: int = 2, className: str = "", anchor: str = "") -> str:
    attrs = {}
    if level != 2:
        attrs["level"] = level
    if anchor:
        attrs["anchor"] = anchor
    if className:
        attrs["className"] = className
    idattr = f' id="{anchor}"' if anchor else ""
    return comment("heading", attrs, f'<h{level} class="{cls("wp-block-heading iu-heading", className)}"{idattr}>{inline(text)}</h{level}>')


def para(text: str, className: str = "") -> str:
    attrs = {"className": className} if className else {}
    return comment("paragraph", attrs, f'<p class="{cls("iu-text", className)}">{inline(text)}</p>')


def paras(text: str, className: str = "") -> str:
    return "\n".join(para(p.strip(), className) for p in text.strip().split("\n\n"))


def eyebrow(text: str) -> str:
    return para(text, "eyebrow")


def lst(items: list[str], className: str = "", ordered: bool = False) -> str:
    attrs = {}
    if ordered:
        attrs["ordered"] = True
    if className:
        attrs["className"] = className
    tag = "ol" if ordered else "ul"
    lis = "".join(comment("list-item", None, f'<li class="iu-list-item">{inline(i)}</li>') for i in items)
    return comment("list", attrs, f'<{tag} class="{cls("wp-block-list iu-list", className)}">{lis}</{tag}>')


def buttons(*items: tuple, align: str = "", className: str = "") -> str:
    """items: (felirat, url[, típus: default|outline|link])"""
    out = []
    for item in items:
        text, url = item[0], item[1]
        typ = item[2] if len(item) > 2 else "default"
        attrs = {"text": text}
        if typ != "default":
            attrs["type"] = typ
        attrs["linkUrl"] = site_url(url)
        out.append(comment("iu/button", attrs, f'<a class="iu-button iu-button-{typ}" href="{site_url(url)}"><span class="iu-button-text"><span>{text}</span></span></a>'))
    attrs = {}
    if align:
        attrs["align"] = align
    if className:
        attrs["className"] = className
    html_cls = cls("iu-button-group", f"iu-button-group-justify-{align}" if align else "", className)
    return comment("iu/button-group", attrs, f'<div class="{html_cls}">' + "".join(out) + "</div>")


def block(name: str, **attrs) -> str:
    """Saját (dinamikus, önzáró) blokk: bba/… vagy iu/… (pl. iu/content, iu/query, iu/terms)."""
    full = name if "/" in name else "bba/" + name
    return comment(full, {k: v for k, v in attrs.items() if v is not None})


def html(markup: str) -> str:
    return comment("html", None, markup)


# ---------------------------------------------------------------- Nyers HTML-es változatok (átköltöztetett tartalom)

def para_html(inner: str, className: str = "") -> str:
    attrs = {"className": className} if className else {}
    return comment("paragraph", attrs, f'<p class="{cls("iu-text", className)}">{inner}</p>')


def heading_html(inner: str, level: int = 2, className: str = "") -> str:
    attrs = {}
    if level != 2:
        attrs["level"] = level
    if className:
        attrs["className"] = className
    return comment("heading", attrs, f'<h{level} class="{cls("wp-block-heading iu-heading", className)}">{inner}</h{level}>')


def list_html(items: list, className: str = "", ordered: bool = False) -> str:
    attrs = {}
    if ordered:
        attrs["ordered"] = True
    if className:
        attrs["className"] = className
    tag = "ol" if ordered else "ul"
    lis = "".join(comment("list-item", None, f'<li class="iu-list-item">{i}</li>') for i in items)
    return comment("list", attrs, f'<{tag} class="{cls("wp-block-list iu-list", className)}">{lis}</{tag}>')


def image(url: str, className: str = "", align: str = "", link: str = "") -> str:
    attrs = {"imageURL": url}
    if align:
        attrs["align"] = align
    if link:
        attrs["linkUrl"] = link
    if className:
        attrs["className"] = className
    wrap = f'<a class="iu-image-wrap" href="{link}">' if link else '<div class="iu-image-wrap">'
    end = "</a>" if link else "</div>"
    return comment("iu/image", attrs, f'<div class="{cls("iu-image", "iu-image-justify-" + align if align else "", className)}">{wrap}<img src="{url}"/>{end}</div>')


def slider(*slides: str, params: str = '"autoplay": true,\n"autoplaySpeed": 3000', className: str = "") -> str:
    attrs = {"params": params}
    if className:
        attrs["className"] = className
    data = "{" + params + "}"
    return comment("iu/slider", attrs, f'<div class="{cls("iu-slider", className)}"><div data-slick="{data.replace(chr(34), "&quot;")}" class="iu-slider-slider">' + "\n".join(slides) + "</div></div>")


def slide(*blocks: str, bgImage: str = "") -> str:
    attrs = {"bgImage": bgImage} if bgImage else {}
    style = f' style="background-image:url({bgImage});background-size:cover;background-position:center"' if bgImage else ""
    return comment("iu/slide", attrs, f'<div class="iu-slide"><div class="iu-slide-bg"{style}></div><div class="iu-slide-inner">' + "\n".join(blocks) + "</div></div>")


def form(form_id: str, *fields: str, button: str = "Küldés", email: str = "", subject: str = "", success: str = "", className: str = "") -> str:
    attrs = {"formId": form_id, "buttonLabel": button}
    if email:
        attrs["email"] = email
    if subject:
        attrs["subject"] = subject
    if success:
        attrs["success"] = success
    if className:
        attrs["className"] = className
    return comment("iu/form", attrs, f'<form class="{cls("iu-form", className)}"><input type="hidden" name="iu_form_id" value="{form_id}"/>' + "\n".join(fields)
                   + f'<div class="iu-form-foot iu-button-group" style="justify-content:flex-start"><a class="iu-form-submit iu-button iu-button-default" href="javascript:void(0)"><span class="iu-button-text"><span>{button}</span></span></a></div><span class="iu-form-error-message"></span><span class="iu-form-success-message">{success}</span></form>')


def form_text(name: str, placeholder: str = "", label: str = "", validate: str = "") -> str:
    attrs = {"name": name}
    if label:
        attrs["label"] = label
    if placeholder:
        attrs["placeholder"] = placeholder
    if validate:
        attrs["validate"] = validate
    return comment("iu/form-text", attrs, f'<div class="iu-form-field iu-form-text"><input type="text" name="{name}" placeholder="{placeholder}"/></div>')


def form_textarea(name: str, placeholder: str = "", label: str = "", validate: str = "") -> str:
    attrs = {"name": name}
    if label:
        attrs["label"] = label
    if placeholder:
        attrs["placeholder"] = placeholder
    if validate:
        attrs["validate"] = validate
    return comment("iu/form-textarea", attrs, f'<div class="iu-form-field iu-form-textarea"><textarea name="{name}" placeholder="{placeholder}" rows="5"></textarea></div>')


def form_accept(name: str, label: str, error: str = "") -> str:
    attrs = {"name": name, "label": label}
    if error:
        attrs["error"] = error
    return comment("iu/form-accept", attrs, f'<div class="iu-form-field iu-form-accept iu-form-field-required"><div class="iu-form-checkbox-wrap"><span class="iu-form-checkbox-input"><input name="{name}" type="checkbox"/></span><span class="iu-form-checkbox-label">{label}</span></div></div>')
