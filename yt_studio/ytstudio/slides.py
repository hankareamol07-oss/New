"""Render 1920x1080 (and 1080x1920 for Shorts) PNG slides with Pillow. Devanagari via Noto + raqm,
Latin runs fall back to Noto Sans (Noto Sans Devanagari has no Latin letters)."""
import os
import re

from PIL import Image, ImageDraw, ImageFont, features

from .config import ROOT
from . import themes

FONT_DIR = os.path.join(ROOT, "assets", "fonts")
W, H = 1920, 1080
INK = (25, 30, 45)
GREEN = (0, 110, 50)
_RUN = re.compile(r"[A-Za-z][A-Za-z0-9'’.,;:!?%/()\-]*|[^A-Za-z]+")


def _hex(c):
    c = c.lstrip("#")
    return tuple(int(c[i:i + 2], 16) for i in (0, 2, 4))


class Text:
    """Font-aware text measuring/drawing with per-run Latin/Devanagari fallback."""

    def __init__(self, lang):
        self.deva = lang in ("mr", "hi")
        if self.deva and not features.check("raqm"):
            raise RuntimeError(
                "Pillow text shaping (raqm/fribidi) not available - Marathi/Hindi would render wrongly. "
                "Run: python -m pip install --upgrade Pillow ; make sure assets/dll/fribidi-0.dll exists next to config.json."
            )
        self.layout = ImageFont.Layout.RAQM if features.check("raqm") else ImageFont.Layout.BASIC
        self._cache = {}

    def _font(self, deva, size, bold):
        name = ("NotoSansDevanagari" if deva else "NotoSans") + ("-Bold" if bold else "-Regular") + ".ttf"
        key = (name, size)
        if key not in self._cache:
            self._cache[key] = ImageFont.truetype(os.path.join(FONT_DIR, name), size, layout_engine=self.layout)
        return self._cache[key]

    def runs(self, s, size, bold):
        out = []
        for m in _RUN.finditer(str(s)):
            t = m.group(0)
            latin = t[0].isascii() and t[0].isalpha()
            has_deva = any("\u0900" <= ch <= "\u097F" for ch in t)
            deva = has_deva or (self.deva and not latin)
            out.append((t, self._font(deva, size, bold)))
        return out

    def length(self, d, s, size, bold=False):
        return sum(d.textlength(t, font=f) for t, f in self.runs(s, size, bold))

    def draw(self, d, xy, s, size, bold=False, fill=INK, anchor="la", stroke=0, stroke_fill=None):
        x, y = xy
        total = self.length(d, s, size, bold)
        if anchor[0] == "m":
            x -= total / 2
        elif anchor[0] == "r":
            x -= total
        va = "m" if anchor[1] == "m" else "a"
        for t, f in self.runs(s, size, bold):
            d.text((x, y), t, font=f, fill=fill, anchor="l" + va, stroke_width=stroke, stroke_fill=stroke_fill)
            x += d.textlength(t, font=f)

    def wrap(self, d, text, size, bold, max_w):
        lines = []
        for para in str(text).split("\n"):
            cur = ""
            for w in para.split():
                t = (cur + " " + w).strip()
                if self.length(d, t, size, bold) <= max_w:
                    cur = t
                else:
                    if cur:
                        lines.append(cur)
                    cur = w
            lines.append(cur)
        return lines

    def fit(self, d, text, max_w, max_h, start=64, min_size=30, bold=False, spacing=1.3):
        size = start
        while size >= min_size:
            lines = self.wrap(d, text, size, bold, max_w)
            lh = int(size * spacing)
            if len(lines) * lh <= max_h:
                return size, lines, lh
            size -= 4
        lines = self.wrap(d, text, min_size, bold, max_w)
        lh = int(min_size * spacing)
        return min_size, lines[: max(1, max_h // lh)], lh

    def block(self, d, xy, text, max_w, max_h, start=64, min_size=30, bold=False, fill=INK, spacing=1.3, anchor="la"):
        size, lines, lh = self.fit(d, text, max_w, max_h, start, min_size, bold, spacing)
        x, y = xy
        for l in lines:
            self.draw(d, (x, y), l, size, bold, fill, anchor)
            y += lh
        return y


class Renderer:
    def __init__(self, cfg, book, chapter):
        self.cfg = cfg
        self.book, self.chapter = book, chapter
        self.en = book["lang"] == "en"
        self.t = Text(book["lang"])
        self.theme = themes.pick(cfg, book.get("subject"))
        self.primary = _hex(self.theme["primary"])
        self.accent = _hex(self.theme["accent"])
        self.bg = _hex(self.theme.get("bg", "#f4f7ff"))
        self.logo = None
        if cfg.get("logo") and os.path.exists(cfg["logo"]):
            self.logo = Image.open(cfg["logo"]).convert("RGBA")
            self.logo.thumbnail((160, 160))

    def _std(self):
        return f"Std {self.book['std']}" if self.en else f"इ. {self.book['std']} वी"

    # ---- chrome -------------------------------------------------------------------------------
    def _base(self, w=W, h=H, badge=None):
        im = Image.new("RGB", (w, h), self.bg)
        hh = 110 if w > h else 150
        themes.draw_pattern(im, self.theme, self.primary, self.accent, self.bg, hh + 8, h - 70)
        d = ImageDraw.Draw(im)
        if self.theme.get("icon"):
            self.t.draw(d, (w - 60, hh + 40), self.theme["icon"], 150 if w > h else 110, True,
                        tuple(int(self.bg[i] * 0.86 + self.primary[i] * 0.14) for i in range(3)), anchor="ra")
        d.rectangle([0, 0, w, hh], fill=self.primary)
        d.rectangle([0, hh, w, hh + 8], fill=self.accent)
        d.rectangle([0, h - 70, w, h], fill=self.accent)
        head = f"{self._std()}  |  {self.book['subject']}  |  {self.chapter['title']}"
        size = 44 if w > h else 40
        head = self.t.wrap(d, head, size, True, w - 300)[0]
        self.t.draw(d, (40, hh // 2), head, size, True, "white", anchor="lm")
        if self.logo:
            im.paste(self.logo, (w - self.logo.width - 30, 10), self.logo)
        else:
            self.t.draw(d, (w - 40, hh // 2), self.cfg["channel_name"], 38, True, self.accent, anchor="rm")
        self.t.draw(d, (w // 2, h - 35), self.cfg["channel_tagline"], 30, True, self.primary, anchor="mm")
        if badge:
            by = hh + 30
            tw = self.t.length(d, badge, 34, True) + 50
            d.rounded_rectangle([40, by, 40 + tw, by + 56], radius=14, fill=self.accent)
            self.t.draw(d, (65, by + 28), badge, 34, True, self.primary, anchor="lm")
        return im, d

    def _card(self, d, box, fill="white", outline=(210, 218, 240)):
        x0, y0, x1, y1 = box
        d.rounded_rectangle([x0 + 6, y0 + 8, x1 + 6, y1 + 8], radius=26, fill=(215, 222, 240))
        d.rounded_rectangle(box, radius=26, fill=fill, outline=outline, width=3)

    def _bullets(self, d, points, x, y, max_w, max_h, start=56, active=None):
        """active=None: all points normal. active=i: points > i hidden (segmenting), point i highlighted (signaling)."""
        size = start
        blocks = []
        while size >= 30:
            lh = int(size * 1.35)
            blocks = [self.t.wrap(d, str(p), size, False, max_w - 60) for p in points]
            if sum(len(b) * lh + 16 for b in blocks) <= max_h:
                break
            size -= 4
        lh = int(size * 1.35)
        for i, ls in enumerate(blocks):
            if active is not None and i > active:
                break
            hl = active is not None and i == active
            if hl:
                d.rounded_rectangle([x - 20, y - 6, x + max_w - 20, y + len(ls) * lh + 6], radius=14, fill=(255, 246, 214))
            d.ellipse([x, y + lh // 2 - 12, x + 24, y + lh // 2 + 12], fill=self.accent if hl or active is None else (190, 196, 215))
            for l in ls:
                self.t.draw(d, (x + 50, y), l, size, hl, self.primary if hl else (INK if active is None else (110, 115, 135)))
                y += lh
            y += 16

    # ---- slides --------------------------------------------------------------------------------
    def title(self, script):
        im, d = self._base()
        self._card(d, (160, 200, 1760, 940))
        y = self.t.block(d, (W // 2, 260), self.chapter["title"], 1500, 260, start=120, min_size=60, bold=True, fill=self.primary, anchor="ma")
        d.line([560, y + 20, 1360, y + 20], fill=self.accent, width=6)
        sub = script.get("thumbnail", {}).get("sub") or ("Exercise | Answers" if self.en else "स्वाध्याय | उत्तरे")
        self.t.draw(d, (W // 2, y + 60), sub, 60, True, (60, 60, 60), anchor="ma")
        self.t.draw(d, (W // 2, y + 170), f"{self.book['subject']}  |  {self._std()}  |  Maharashtra Board", 44, False, (90, 90, 90), anchor="ma")
        return im

    def points(self, heading, pts, badge=None, reserve_right=0, active=None):
        im, d = self._base(badge=badge)
        right = 1840 - reserve_right
        self.t.draw(d, (80, 215), heading, 64, True, self.primary)
        d.line([80, 305, right, 305], fill=self.accent, width=5)
        self._card(d, (80, 340, right, 980))
        self._bullets(d, pts, 130, 380, right - 180, 570, active=active)
        return im

    def points_fig(self, heading, pts, fig_path, badge=None, reserve_right=0, active=None):
        """Bullets on the left, the textbook figure in a card on the right (the avatar strip stays free)."""
        im, d = self._base(badge=badge)
        right = 1840 - reserve_right
        self.t.draw(d, (80, 215), heading, 64, True, self.primary)
        d.line([80, 305, right, 305], fill=self.accent, width=5)
        fw = min(760, max(420, (right - 80) * 0.42))
        fx0 = int(right - fw)
        self._card(d, (80, 340, fx0 - 30, 980))
        self._bullets(d, pts, 130, 380, fx0 - 30 - 130, 570, active=active)
        self._card(d, (fx0, 340, right, 980), fill=(252, 252, 255))
        try:
            fig = Image.open(fig_path).convert("RGB")
            box_w, box_h = int(right - fx0) - 40, 600
            k = min(box_w / fig.width, box_h / fig.height)
            fig = fig.resize((max(1, int(fig.width * k)), max(1, int(fig.height * k))), Image.LANCZOS)
            im.paste(fig, (fx0 + 20 + (box_w - fig.width) // 2, 360 + (box_h - fig.height) // 2))
            self.t.draw(d, (fx0 + (right - fx0) // 2, 975), "पाठ्यपुस्तकातील चित्र" if not self.en else "Textbook figure", 24, False, (120, 125, 145), anchor="mm")
        except Exception:
            pass
        return im

    def question(self, seg, show_answer=False, badge=None):
        im, d = self._base(badge=badge or seg.get("instruction") or "")
        n = seg.get("no", "")
        opts = seg.get("options") or []
        pairs = seg.get("pairs") or []
        is_mcq = bool(opts) and seg.get("qtype") in ("mcq", "odd_one")
        is_match = bool(pairs) and seg.get("qtype") == "match"
        ans_h = 330 if show_answer else 0
        q_bottom = H - 100 - ans_h - (20 if show_answer else 0)
        self._card(d, (80, 215, 1840, q_bottom))
        d.ellipse([120, 250, 240, 370], fill=self.primary)
        self.t.draw(d, (180, 310), str(n), 70, True, "white", anchor="mm")
        extra = (((len(opts) + 1) // 2) * 66 + 30 if is_mcq else 0) + (min(len(pairs), 6) * 58 + 20 if is_match else 0)
        avail = q_bottom - 250 - 40 - extra
        y = self.t.block(d, (290, 250), seg.get("question", ""), 1500, max(90, avail), start=60, min_size=34)
        if is_mcq:
            labels = ["a", "b", "c", "d", "e", "f"] if self.en else ["अ", "ब", "क", "ड", "इ", "फ"]
            y += 20
            ans = str(seg.get("answer", "")).strip()
            for i, o in enumerate(opts[:6]):
                x = 290 + (i % 2) * 760
                if i and i % 2 == 0:
                    y += 66
                hit = show_answer and (ans == str(o).strip() or ans.lower() == labels[i] or ans.startswith(f"({labels[i]})"))
                if hit:
                    d.rounded_rectangle([x - 16, y - 6, x + 720, y + 58], radius=12, fill=(220, 250, 228))
                self.t.draw(d, (x, y), f"({labels[i]})  {o}", 46, hit, GREEN if hit else (40, 40, 40))
            y += 70
        if is_match:
            right = [p[1] for p in pairs]
            if not show_answer:
                right = sorted(right, key=lambda s: str(s)[::-1])
            y += 10
            hdr_a, hdr_b = ("Group A", "Group B") if self.en else ("अ गट", "ब गट")
            self.t.draw(d, (290, y), hdr_a, 40, True, self.primary)
            self.t.draw(d, (1060, y), hdr_b, 40, True, self.primary)
            y += 56
            for i, p in enumerate(pairs[:6]):
                self.t.draw(d, (290, y), f"{i + 1}. {p[0]}", 42, False, (40, 40, 40))
                self.t.draw(d, (1060, y), ("→ " if show_answer else "") + str(right[i]), 42, show_answer, GREEN if show_answer else (40, 40, 40))
                y += 58
        if show_answer:
            top = H - 90 - ans_h
            d.rounded_rectangle([80, top, 1840, H - 90], radius=26, fill=(230, 252, 236), outline=(0, 150, 70), width=4)
            label = "Ans." if self.en else "उत्तर :"
            self.t.draw(d, (120, top + 22), label, 44, True, GREEN)
            steps = seg.get("steps") or []
            lx = 120 + self.t.length(d, label, 44, True) + 30
            if steps:
                y2 = self.t.block(d, (lx, top + 22), "\n".join(str(s) for s in steps), 1840 - lx - 60, ans_h - 40, start=46, min_size=28, bold=False, fill=GREEN, spacing=1.25)
            else:
                y2 = self.t.block(d, (lx, top + 22), str(seg.get("answer", "")), 1840 - lx - 60, 150, start=54, min_size=34, bold=True, fill=GREEN)
                expl = str(seg.get("say_a", "")).strip()
                if expl and ans_h - (y2 - top) > 70:
                    self.t.block(d, (lx, y2 + 10), expl, 1840 - lx - 60, top + ans_h - y2 - 30, start=36, min_size=26, fill=(40, 80, 55), spacing=1.25)
        return im

    def short(self, seg, hook, show_answer=False):
        w, h = 1080, 1920
        im, d = self._base(w, h)
        self.t.block(d, (w // 2, 220), hook, 980, 180, start=66, min_size=44, bold=True, fill=self.primary, anchor="ma")
        self._card(d, (40, 430, 1040, 1300))
        y = self.t.block(d, (80, 480), seg.get("question", ""), 920, 520, start=62, min_size=36)
        opts = seg.get("options") or []
        if opts:
            labels = ["a", "b", "c", "d"] if self.en else ["अ", "ब", "क", "ड"]
            for i, o in enumerate(opts[:4]):
                y += 66
                self.t.draw(d, (100, y), f"({labels[i]})  {o}", 46, False, (40, 40, 40))
        if show_answer:
            d.rounded_rectangle([40, 1350, 1040, 1800], radius=30, fill=(230, 252, 236), outline=(0, 150, 70), width=5)
            body = "\n".join(str(s) for s in (seg.get("steps") or [])) or str(seg.get("answer", ""))
            self.t.block(d, (80, 1385), ("Ans. " if self.en else "उत्तर : ") + body, 920, 390, start=56, min_size=32, bold=True, fill=GREEN)
        else:
            self.t.draw(d, (w // 2, 1560), "Answer next ..." if self.en else "उत्तर पुढे ...", 54, True, self.primary, anchor="mm")
        return im

    def thumbnail(self, thumb):
        im = Image.new("RGB", (1280, 720), (20, 44, 110))
        d = ImageDraw.Draw(im)
        d.polygon([(0, 720), (0, 430), (1280, 620), (1280, 720)], fill=self.accent)
        badge = thumb.get("badge") or self._std()
        tw = self.t.length(d, badge, 60, True) + 60
        d.rounded_rectangle([50, 50, 50 + tw, 145], radius=20, fill=self.accent)
        self.t.draw(d, (80, 97), badge, 60, True, (20, 44, 110), anchor="lm")
        self.t.block(d, (50, 180), thumb.get("headline") or self.chapter["title"], 1180, 280, start=118, min_size=60, bold=True, fill="white", spacing=1.15)
        self.t.draw(d, (50, 610), thumb.get("sub", ""), 56, True, (20, 44, 110))
        self.t.draw(d, (1240, 675), self.cfg["channel_name"], 40, True, (20, 44, 110), anchor="rm")
        if self.logo:
            lg = self.logo.copy()
            lg.thumbnail((120, 120))
            im.paste(lg, (1280 - lg.width - 40, 40), lg)
        return im
