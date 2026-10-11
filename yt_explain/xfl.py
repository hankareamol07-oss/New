"""Minimal XFL (.fla) renderer with skia: symbols, timelines, shapes (fills/strokes/gradients), bitmaps, masks, tweens."""
import os, re, math, html
from lxml import etree
import skia

NS = "{http://ns.adobe.com/xfl/2008/}"
TW = 20.0  # twips per px
TOK = re.compile(r'([!|/\[\]])|S\d+|(#?-?[0-9A-Fa-f]+(?:\.[0-9A-Fa-f]+)?)')


def _num(s):
    if s.startswith('#'):
        neg = s[1] == '-'
        s = s[2:] if neg else s[1:]
        ip, _, fp = s.partition('.')
        iv = int(ip or '0', 16)
        if iv >= 0x800000:
            iv -= 0x1000000
        v = iv + (int(fp.ljust(2, '0')[:2], 16) / 256.0 if fp else 0)
        return (-v if neg else v) / TW
    return float(s) / TW


def parse_edges(s):
    """-> list of segments [(x0,y0),(x1,y1)] or [(x0,y0),(cx,cy),(x1,y1)]"""
    segs = []
    cur = None
    toks = TOK.findall(s)
    i = 0
    nums = []
    op = None
    out = []
    for sym, num in toks:
        if sym:
            op = sym
            nums = []
        elif num:
            nums.append(_num(num))
            if op == '!' and len(nums) == 2:
                cur = (nums[0], nums[1]); nums = []
            elif op in '|/' and len(nums) == 2:
                p = (nums[0], nums[1])
                if cur is not None: segs.append([cur, p])
                cur = p; nums = []
            elif op in '[]' and len(nums) == 4:
                c = (nums[0], nums[1]); p = (nums[2], nums[3])
                if cur is not None: segs.append([cur, c, p])
                cur = p; nums = []
    return segs


def _mat(el):
    if el is None:
        return skia.Matrix()
    m = el.find(NS + 'Matrix')
    if m is None:
        return skia.Matrix()
    g = lambda k, d: float(m.get(k, d))
    return skia.Matrix.MakeAll(g('a', 1), g('c', 0), g('tx', 0), g('b', 0), g('d', 1), g('ty', 0), 0, 0, 1)


def _color(hexs, alpha=1.0):
    hexs = hexs.lstrip('#')
    r, g, b = int(hexs[0:2], 16), int(hexs[2:4], 16), int(hexs[4:6], 16)
    return skia.Color4f(r / 255, g / 255, b / 255, float(alpha))


def _key(p):
    return (round(p[0] * 100), round(p[1] * 100))


def _rev(seg):
    return seg[::-1]


def chain_loops(segs):
    """Join directed segments into closed loops -> list of lists of segs."""
    from collections import defaultdict
    by_start = defaultdict(list)
    for s in segs:
        by_start[_key(s[0])].append(s)
    used = set()
    loops = []
    for s in segs:
        if id(s) in used:
            continue
        loop = [s]; used.add(id(s))
        cur = s
        start = _key(s[0])
        for _ in range(len(segs)):
            k = _key(cur[-1])
            if k == start:
                break
            cands = [c for c in by_start.get(k, []) if id(c) not in used]
            if not cands:
                break
            cur = cands[0]; used.add(id(cur)); loop.append(cur)
        if math.hypot(cur[-1][0] - s[0][0], cur[-1][1] - s[0][1]) < 3.0:
            loops.append(loop)
    return loops


def loops_to_path(loops):
    path = skia.Path()
    for loop in loops:
        path.moveTo(*loop[0][0])
        for seg in loop:
            if len(seg) == 2:
                path.lineTo(*seg[1])
            else:
                path.quadTo(seg[1][0], seg[1][1], seg[2][0], seg[2][1])
        path.close()
    path.setFillType(skia.PathFillType.kWinding)
    return path


def segs_to_open_path(segs):
    path = skia.Path()
    last = None
    for seg in segs:
        if last is None or _key(last) != _key(seg[0]):
            path.moveTo(*seg[0])
        if len(seg) == 2:
            path.lineTo(*seg[1])
        else:
            path.quadTo(seg[1][0], seg[1][1], seg[2][0], seg[2][1])
        last = seg[-1]
    return path


class Doc:
    def __init__(self, root):
        self.root = root
        dd = os.path.join(root, 'DOMDocument.xml')
        self.dom = etree.parse(dd).getroot() if os.path.exists(dd) else None
        self.symbols = {}
        self.bitmaps = {}
        self.images = {}
        if self.dom is not None:
            for inc in self.dom.iter(NS + 'Include'):
                self.symbols[inc.get('href')[:-4]] = None
            for bm in self.dom.iter(NS + 'DOMBitmapItem'):
                self.bitmaps[bm.get('name')] = bm
        self.fps = float(self.dom.get('frameRate', 24)) if self.dom is not None else 24.0
        # animation hooks (set per rendered frame by the puppet rig)
        self.layer_xform = {}     # layer name -> skia.Matrix applied in the parent's coordinate space
        self.frame_override = {}  # symbol name -> forced timeline frame
        self.layer_frame = {}     # layer name -> forced frame on that layer
        self.layer_scope = None   # restrict hooks to layers of this symbol timeline (name) when set

    def symbol(self, name):
        name = html.unescape(name)
        if self.symbols.get(name) is None:
            p = os.path.join(self.root, 'LIBRARY', name + '.xml')
            if not os.path.exists(p):
                # names are stored with &#032 style escapes in file names
                alt = os.path.join(self.root, 'LIBRARY', re.sub(r'[ ]', '&#032', name) + '.xml')
                p = alt if os.path.exists(alt) else p
            self.symbols[name] = etree.parse(p).getroot()
        return self.symbols[name]

    def image(self, name):
        name = html.unescape(name)
        if name not in self.images:
            bm = self.bitmaps.get(name)
            p = os.path.join(self.root, 'LIBRARY', name)
            img = None
            if os.path.exists(p):
                img = skia.Image.open(p)
            elif bm is not None:
                p2 = os.path.join(self.root, 'bin', bm.get('bitmapDataHRef', ''))
                if os.path.exists(p2):
                    try: img = skia.Image.open(p2)
                    except Exception: img = None
            self.images[name] = img
        return self.images[name]

    # ---------- timeline helpers ----------
    @staticmethod
    def timeline(sym):
        return sym.find(NS + 'timeline/' + NS + 'DOMTimeline')

    @staticmethod
    def layers(tl):
        return list(tl.find(NS + 'layers'))

    @staticmethod
    def length(tl):
        n = 0
        for L in tl.find(NS + 'layers'):
            fr = L.find(NS + 'frames')
            if fr is None: continue
            for f in fr:
                n = max(n, int(f.get('index')) + int(f.get('duration', 1)))
        return max(n, 1)

    @staticmethod
    def frame_at(layer, f):
        fr = layer.find(NS + 'frames')
        if fr is None: return None, None
        frames = list(fr)
        for i, df in enumerate(frames):
            idx = int(df.get('index')); dur = int(df.get('duration', 1))
            if idx <= f < idx + dur:
                return df, (frames[i + 1] if i + 1 < len(frames) else None)
        return None, None

    # ---------- drawing ----------
    def draw_symbol(self, canvas, name, frame=0, only_layers=None, skip_layers=None):
        sym = self.symbol(name)
        tl = self.timeline(sym)
        self.draw_timeline(canvas, tl, frame, only_layers, skip_layers)

    def draw_timeline(self, canvas, tl, frame, only_layers=None, skip_layers=None):
        layers = self.layers(tl)
        n = len(layers)
        # masks: mask layer at i, children have parentLayerId=i
        drawn = set()
        order = list(range(n - 1, -1, -1))  # bottom first
        for i in order:
            if i in drawn: continue
            L = layers[i]
            lt = L.get('layerType', 'normal')
            if lt in ('guide', 'folder', 'camera'):
                drawn.add(i); continue
            if L.get('visible', 'true') == 'false' and lt != 'mask':
                drawn.add(i); continue
            if lt == 'mask':
                kids = [j for j in range(n) if layers[j].get('parentLayerId') == str(i) and layers[j].get('layerType', 'normal') != 'guide']
                canvas.saveLayer()
                for j in sorted(kids, reverse=True):
                    if only_layers is None or layers[j].get('name') in only_layers:
                        if not (skip_layers and layers[j].get('name') in skip_layers):
                            self.draw_layer(canvas, layers[j], frame)
                    drawn.add(j)
                canvas.saveLayer(None, skia.Paint(BlendMode=skia.BlendMode.kDstIn))
                self.draw_layer(canvas, L, frame, mask=True)
                canvas.restore(); canvas.restore()
                drawn.add(i); continue
            if L.get('parentLayerId') is not None:
                pl = layers[int(L.get('parentLayerId'))]
                if pl.get('layerType') == 'mask':
                    continue  # handled with mask
            if only_layers is not None and L.get('name') not in only_layers:
                drawn.add(i); continue
            if skip_layers and L.get('name') in skip_layers:
                drawn.add(i); continue
            self.draw_layer(canvas, L, frame)
            drawn.add(i)

    def draw_layer(self, canvas, L, frame, mask=False):
        name = L.get('name')
        frame = self.layer_frame.get(name, frame)
        xf = self.layer_xform.get(name)
        if xf is not None:
            canvas.save(); canvas.concat(xf)
            try:
                self._draw_layer(canvas, L, frame, mask)
            finally:
                canvas.restore()
            return
        self._draw_layer(canvas, L, frame, mask)

    def _draw_layer(self, canvas, L, frame, mask=False):
        df, nxt = self.frame_at(L, frame)
        if df is None: return
        els = df.find(NS + 'elements')
        if els is None: return
        tween = df.get('tweenType') == 'motion' and nxt is not None
        t = 0.0
        if tween:
            idx = int(df.get('index')); dur = int(df.get('duration', 1))
            t = (frame - idx) / float(dur)
            acc = float(df.get('acceleration', 0)) / 100.0
            if acc:  # ease
                t = t ** (1 - acc) if acc > 0 else 1 - (1 - t) ** (1 + acc)
        for el in els:
            self.draw_element(canvas, el, frame, df, nxt if tween else None, t, mask)

    def _interp_matrix(self, el, nxt_el, t):
        m0 = _mat(el.find(NS + 'matrix'))
        if nxt_el is None: return m0
        m1 = _mat(nxt_el.find(NS + 'matrix'))
        def dec(m):
            a, c, tx, b, d, ty = m.get(0), m.get(1), m.get(2), m.get(3), m.get(4), m.get(5)
            sx = math.hypot(a, b); sy = math.hypot(c, d)
            rot = math.atan2(b, a); rot2 = math.atan2(-c, d)
            return sx, sy, rot, rot2, tx, ty
        s0 = dec(m0); s1 = dec(m1)
        def lerp(a, b): return a + (b - a) * t
        def lerpa(a, b):
            d = (b - a + math.pi) % (2 * math.pi) - math.pi
            return a + d * t
        sx = lerp(s0[0], s1[0]); sy = lerp(s0[1], s1[1])
        r = lerpa(s0[2], s1[2]); r2 = lerpa(s0[3], s1[3])
        tx = lerp(s0[4], s1[4]); ty = lerp(s0[5], s1[5])
        return skia.Matrix.MakeAll(sx * math.cos(r), -sy * math.sin(r2), tx, sx * math.sin(r), sy * math.cos(r2), ty, 0, 0, 1)

    def draw_element(self, canvas, el, frame, df, nxt, t, mask=False):
        tag = etree.QName(el).localname
        if tag == 'DOMGroup':
            m = el.find(NS + 'members')
            if m is not None:
                for e in m:
                    self.draw_element(canvas, e, frame, df, None, 0, mask)
            return
        nxt_el = None
        if nxt is not None:
            nels = nxt.find(NS + 'elements')
            if nels is not None:
                for e in nels:
                    if etree.QName(e).localname == tag and e.get('libraryItemName') == el.get('libraryItemName'):
                        nxt_el = e; break
        canvas.save()
        canvas.concat(self._interp_matrix(el, nxt_el, t) if nxt_el is not None else _mat(el.find(NS + 'matrix')))
        if tag == 'DOMShape':
            self.draw_shape(canvas, el, mask)
        elif tag == 'DOMBitmapInstance':
            img = self.image(el.get('libraryItemName'))
            if img is not None:
                canvas.drawImage(img, 0, 0, skia.SamplingOptions(skia.FilterMode.kLinear, skia.MipmapMode.kLinear))
        elif tag == 'DOMSymbolInstance':
            name = el.get('libraryItemName')
            try:
                sym = self.symbol(name)
            except Exception:
                canvas.restore(); return
            tl = self.timeline(sym)
            ln = self.length(tl)
            idx = int(df.get('index'))
            first = int(el.get('firstFrame', 0))
            loop = el.get('loop', 'loop')
            if el.get('symbolType', 'graphic') != 'graphic':
                cf = 0
            elif loop == 'single frame':
                cf = first
            elif loop == 'play once':
                cf = min(ln - 1, first + frame - idx)
            else:
                cf = (first + frame - idx) % ln
            if html.unescape(name) in self.frame_override:
                cf = self.frame_override[html.unescape(name)] % ln
            alpha = 1.0
            col = el.find(NS + 'color/' + NS + 'Color')
            cf_paint = None
            if col is not None:
                alpha = float(col.get('alphaMultiplier', 1))
                br = col.get('brightness')
                tint = col.get('tintColor')
                if tint is not None:
                    tm = float(col.get('tintMultiplier', 0))
                    c = _color(tint)
                    cf_paint = skia.Paint(ColorFilter=skia.ColorFilters.Blend(skia.Color4f(c.fR, c.fG, c.fB, tm).toColor(), skia.BlendMode.kSrcATop))
                elif br is not None:
                    b = float(br)
                    c = skia.Color4f(1, 1, 1, b) if b > 0 else skia.Color4f(0, 0, 0, -b)
                    cf_paint = skia.Paint(ColorFilter=skia.ColorFilters.Blend(c.toColor(), skia.BlendMode.kSrcATop))
            layered = alpha < 1 or cf_paint is not None
            if layered:
                p = cf_paint or skia.Paint()
                p.setAlphaf(alpha)
                canvas.saveLayer(None, p)
            self.draw_timeline(canvas, tl, cf)
            if layered: canvas.restore()
        canvas.restore()

    def _fill_paint(self, fs, mask):
        p = skia.Paint(AntiAlias=True)
        if mask:
            p.setColor4f(skia.Color4f(0, 0, 0, 1)); return p
        for ch in fs:
            n = etree.QName(ch).localname
            if n == 'SolidColor':
                p.setColor4f(_color(ch.get('color', '#000000'), ch.get('alpha', 1)))
            elif n in ('LinearGradient', 'RadialGradient'):
                m = _mat(ch.find(NS + 'matrix'))
                ents = ch.findall(NS + 'GradientEntry')
                cols = [_color(e.get('color', '#000000'), e.get('alpha', 1)) for e in ents]
                pos = [float(e.get('ratio', 0)) for e in ents]
                if len(cols) == 1: cols = cols * 2; pos = [0, 1]
                mode = skia.TileMode.kClamp
                sm = ch.get('spreadMethod', 'extend')
                if sm == 'reflect': mode = skia.TileMode.kMirror
                elif sm == 'repeat': mode = skia.TileMode.kRepeat
                R = 16384 / TW
                if n == 'LinearGradient':
                    sh = skia.GradientShader.MakeLinear([skia.Point(-R, 0), skia.Point(R, 0)], cols, pos, mode, localMatrix=m)
                else:
                    fr = float(ch.get('focalPointRatio', 0))
                    if abs(fr) > 1e-3:
                        sh = skia.GradientShader.MakeTwoPointConical(skia.Point(fr * R, 0), 0, skia.Point(0, 0), R, cols, pos, mode, localMatrix=m)
                    else:
                        sh = skia.GradientShader.MakeRadial(skia.Point(0, 0), R, cols, pos, mode, localMatrix=m)
                p.setShader(sh)
            elif n == 'BitmapFill':
                img = self.image(ch.get('bitmapPath', ''))
                if img is not None:
                    m = _mat(ch.find(NS + 'matrix'))
                    m.preScale(1 / TW, 1 / TW)
                    tm = skia.TileMode.kRepeat if ch.get('bitmapIsClipped', 'false') == 'false' else skia.TileMode.kClamp
                    p.setShader(img.makeShader(tm, tm, skia.SamplingOptions(skia.FilterMode.kLinear), m))
                else:
                    p.setColor4f(skia.Color4f(0.5, 0.5, 0.5, 1))
        return p

    def _stroke_paint(self, ss, mask):
        p = skia.Paint(AntiAlias=True)
        p.setStyle(skia.Paint.kStroke_Style)
        s = ss[0] if len(ss) else None
        if s is None: return None
        w = float(s.get('weight', 1))
        p.setStrokeWidth(max(w, 0.5))
        caps = s.get('caps', 'round'); joints = s.get('joints', 'round')
        p.setStrokeCap({'none': skia.Paint.kButt_Cap, 'square': skia.Paint.kSquare_Cap}.get(caps, skia.Paint.kRound_Cap))
        p.setStrokeJoin({'miter': skia.Paint.kMiter_Join, 'bevel': skia.Paint.kBevel_Join}.get(joints, skia.Paint.kRound_Join))
        fill = s.find(NS + 'fill')
        if mask:
            p.setColor4f(skia.Color4f(0, 0, 0, 1)); return p
        if fill is not None and len(fill):
            fp = self._fill_paint(fill, False)
            if fp.getShader(): p.setShader(fp.getShader())
            else: p.setColor4f(fp.getColor4f())
        return p

    def draw_shape(self, canvas, sh, mask=False):
        fills = {int(f.get('index')): f for f in (sh.find(NS + 'fills') if sh.find(NS + 'fills') is not None else [])}
        strokes = {int(s.get('index')): s for s in (sh.find(NS + 'strokes') if sh.find(NS + 'strokes') is not None else [])}
        edges_el = sh.find(NS + 'edges')
        if edges_el is None: return
        fsegs = {}
        ssegs = {}
        for e in edges_el:
            es = e.get('edges')
            if not es: continue
            segs = parse_edges(es)
            f0 = e.get('fillStyle0'); f1 = e.get('fillStyle1'); st = e.get('strokeStyle')
            if f0: fsegs.setdefault(int(f0), []).extend(segs)
            if f1: fsegs.setdefault(int(f1), []).extend(_rev(s) for s in segs)
            if st: ssegs.setdefault(int(st), []).extend(segs)
        for idx, segs in fsegs.items():
            fs = fills.get(idx)
            if fs is None: continue
            path = loops_to_path(chain_loops(segs))
            canvas.drawPath(path, self._fill_paint(fs, mask))
        for idx, segs in ssegs.items():
            ss = strokes.get(idx)
            if ss is None: continue
            p = self._stroke_paint(ss, mask)
            if p is None: continue
            canvas.drawPath(segs_to_open_path(segs), p)


def render(doc, name, frame=0, scale=1.0, size=None, bounds=None, only_layers=None, skip_layers=None, pad=4):
    """Render a symbol (or 'stage') to a skia Image. If bounds None: auto-fit via a probe render."""
    def draw(canvas):
        if name == 'stage':
            doc.draw_timeline(canvas, doc.dom.find(NS + 'timelines/' + NS + 'DOMTimeline'), frame, only_layers, skip_layers)
        else:
            doc.draw_symbol(canvas, name, frame, only_layers, skip_layers)
    if bounds is None:
        # probe at low res over a wide area
        W = 4096; s = 0.25
        surf = skia.Surface(W, W)
        c = surf.getCanvas(); c.clear(skia.Color4f(0, 0, 0, 0))
        c.translate(W / 2, W / 2); c.scale(s, s)
        draw(c)
        import numpy as np
        a = surf.makeImageSnapshot().toarray()[:, :, 3]
        ys, xs = np.nonzero(a)
        if len(xs) == 0:
            return None, None
        bounds = ((xs.min() - W / 2) / s - pad, (ys.min() - W / 2) / s - pad, (xs.max() + 1 - W / 2) / s + pad, (ys.max() + 1 - W / 2) / s + pad)
    x0, y0, x1, y1 = bounds
    if size is not None:
        scale = min(size[0] / (x1 - x0), size[1] / (y1 - y0))
    w = max(1, int(math.ceil((x1 - x0) * scale))); h = max(1, int(math.ceil((y1 - y0) * scale)))
    surf = skia.Surface(w, h)
    c = surf.getCanvas(); c.clear(skia.Color4f(0, 0, 0, 0))
    c.scale(scale, scale); c.translate(-x0, -y0)
    draw(c)
    return surf.makeImageSnapshot(), bounds


def to_pil(img):
    from PIL import Image
    import numpy as np
    a = img.toarray()  # BGRA premul? skia toarray gives RGBA8888 unpremul by default for N32? ensure
    return Image.fromarray(a[:, :, [2, 1, 0, 3]] if img.colorType() == skia.kBGRA_8888_ColorType else a, 'RGBA')
