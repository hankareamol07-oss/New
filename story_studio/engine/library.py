"""Library: import .fla files, find candidate characters/props, export a symbol with all its dependencies
into library/characters/<name>/ (LIBRARY xml + manifest.json)."""
import html
import json
import os
import re
import shutil

from lxml import etree

from . import fla_import, paths, xfl

NS = xfl.NS


def sym_file(root, name):
    name = html.unescape(name)
    p = os.path.join(root, "LIBRARY", name + ".xml")
    if os.path.exists(p):
        return p
    alt = os.path.join(root, "LIBRARY", re.sub(r"[ ]", "&#032", name) + ".xml")
    return alt if os.path.exists(alt) else None


def safe_name(s):
    s = re.sub(r"[^\w\- ]+", "_", html.unescape(s.split("/")[-1])).strip()
    return re.sub(r"\s+", "_", s) or "item"


def import_fla(fla, log=print):
    """Extract one .fla into library/_fla/<name>/ (skips if already extracted). Returns the XFL folder."""
    out = os.path.join(paths.IMPORTS, safe_name(os.path.splitext(os.path.basename(fla))[0]))
    if not os.path.exists(os.path.join(out, "DOMDocument.xml")):
        log(f"extracting {os.path.basename(fla)} ...")
        n = fla_import.extract(fla, out, log)
        log(f"  {n} files")
    return out


def deps(root, name, seen=None):
    """All symbol names (and bitmap names) used by `name`, recursively."""
    seen = set() if seen is None else seen
    bitmaps = set()
    stack = [name]
    while stack:
        n = html.unescape(stack.pop())
        if n in seen:
            continue
        seen.add(n)
        p = sym_file(root, n)
        if not p:
            continue
        tree = etree.parse(p).getroot()
        for el in tree.iter(NS + "DOMSymbolInstance"):
            stack.append(el.get("libraryItemName"))
        for el in tree.iter(NS + "DOMBitmapInstance"):
            bitmaps.add(html.unescape(el.get("libraryItemName")))
        for el in tree.iter(NS + "BitmapFill"):
            if el.get("bitmapPath"):
                bitmaps.add(html.unescape(el.get("bitmapPath")))
    return seen, bitmaps


def export_symbol(root, name, out_dir, manifest):
    syms, bitmaps = deps(root, name)
    lib = os.path.join(root, "LIBRARY")
    for s in syms:
        p = sym_file(root, s)
        if p:
            dst = os.path.join(out_dir, os.path.relpath(p, root))
            os.makedirs(os.path.dirname(dst), exist_ok=True)
            shutil.copyfile(p, dst)
    # bitmaps: keep a tiny DOMDocument.xml with the bitmap items so xfl.Doc can find bin/*.dat or LIBRARY files
    items = []
    dd = os.path.join(root, "DOMDocument.xml")
    if bitmaps and os.path.exists(dd):
        dom = etree.parse(dd).getroot()
        for bm in dom.iter(NS + "DOMBitmapItem"):
            if html.unescape(bm.get("name", "")) in bitmaps:
                items.append(bm)
                for src in (os.path.join(lib, bm.get("name")), os.path.join(root, "bin", bm.get("bitmapDataHRef", ""))):
                    if os.path.isfile(src):
                        dst = os.path.join(out_dir, os.path.relpath(src, root))
                        os.makedirs(os.path.dirname(dst), exist_ok=True)
                        shutil.copyfile(src, dst)
    if items:
        doc = etree.Element(NS + "DOMDocument", nsmap={None: NS[1:-1]})
        media = etree.SubElement(doc, NS + "media")
        for bm in items:
            media.append(bm)
        etree.SubElement(doc, NS + "timelines")
        etree.ElementTree(doc).write(os.path.join(out_dir, "DOMDocument.xml"), encoding="utf-8", xml_declaration=True)
    manifest = dict(manifest, root=name)
    with open(os.path.join(out_dir, "manifest.json"), "w", encoding="utf8") as f:
        json.dump(manifest, f, ensure_ascii=False, indent=1)
    return out_dir


def stage_items(root):
    """Symbols placed on the stage (any frame of every scene) - in character packs these are the characters."""
    d = xfl.Doc(root)
    out = []
    if d.dom is None:
        return out
    for tl in d.dom.findall(NS + "timelines/" + NS + "DOMTimeline"):
        for L in d.layers(tl):                      # every keyframe: some packs put one character per frame
            for df in L.iter(NS + "DOMFrame"):
                els = df.find(NS + "elements")
                for e in (els if els is not None else []):
                    n = e.get("libraryItemName")
                    if n and etree.QName(e).localname == "DOMSymbolInstance" and n not in out and sym_file(root, n):
                        out.append(n)
    return out


def characters():
    if not os.path.isdir(paths.CHARS):
        return []
    return sorted(n for n in os.listdir(paths.CHARS) if os.path.exists(os.path.join(paths.CHARS, n, "manifest.json")))


def thumb(name, make=True):
    """PIL thumbnail of a library character (created from the rig the first time)."""
    from PIL import Image
    f = os.path.join(paths.CHARS, name, "thumb.png")
    if os.path.exists(f):
        return Image.open(f).convert("RGBA")
    if not make:
        return None
    try:
        from . import rig
        r = rig.Rig(os.path.join(paths.CHARS, name))
        img, _ = r.frame(0.0, scale=360.0 / max(1.0, r.height * 1.12))
        bb = img.getbbox()
        img = img.crop(bb) if bb else img
        img.thumbnail((240, 360))
        img.save(f)
        return img
    except Exception:
        return None


def load_manifest(name):
    with open(os.path.join(paths.CHARS, name, "manifest.json"), encoding="utf8") as f:
        return json.load(f)


def save_manifest(name, m):
    with open(os.path.join(paths.CHARS, name, "manifest.json"), "w", encoding="utf8") as f:
        json.dump(m, f, ensure_ascii=False, indent=1)
