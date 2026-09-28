"""Project C: original Marathi motivation Short (9:16, <= 60 s) from trending themes.

trend titles (research) -> LLM writes a NEW script (never quotes/copies a source) -> typographic slides
(display Devanagari fonts, one line per slide, key word highlighted, gradient background, progress bar)
-> TTS narration -> ffmpeg -> short.mp4 + title / caption / hashtags (Marathi first).

Script JSON:
{ "theme": "...", "title": "<= 60 chars, Marathi, may end with #Shorts",
  "hook":  {"text": "on-screen line <= 40 chars", "say": "1 spoken sentence"},
  "lines": [ {"text": "<= 45 chars", "key": "one word from text to highlight", "say": "1-2 spoken sentences"} ] (4-6),
  "close": {"text": "<= 40 chars call to action", "say": "..."},
  "caption": "3-5 line Marathi caption for YouTube/Instagram (no hashtags inside)",
  "hashtags": ["#मराठीसुविचार", ... 10-15, Marathi first then Hindi/English],
  "tags": ["marathi motivation", ...] }
"""
import hashlib
import json
import os
import re

from PIL import Image, ImageDraw, ImageFilter, ImageFont, features

from ytstudio.llm import chat_json
from ytstudio.tts import duration, speak
from ytstudio.video import _concat

HERE = os.path.dirname(os.path.abspath(__file__))
FONT_DIR = os.path.join(HERE, "assets", "fonts")
W, H = 1080, 1920

SYSTEM = """You are a Marathi motivational speaker and copywriter who makes viral, wholesome YouTube Shorts / Instagram Reels
for Maharashtra viewers (students, young workers, parents). Output STRICT JSON only.
Rules:
- Language: शुद्ध, natural spoken Marathi (मराठी) FIRST. No Hindi words, no Hinglish (write अपयश not असफलता, यश not सफलता,
  स्वप्न not सपना). Simple, punchy, positive.
- Write something NEW and ORIGINAL on the given theme. Do NOT copy, translate or paraphrase any existing quote, speech,
  song, film dialogue or the titles you are shown; do not name any person, creator or channel.
- Structure (total spoken 35-50 s, ~90-130 words): hook that stops the scroll (question / bold claim), 4-6 short
  lines each with ONE idea (one strong key word to highlight), close with a warm call to action
  (e.g. save/share with a friend, comment your goal). No emojis on-screen text; digits allowed.
- title: catchy Marathi, <= 60 chars. caption: 3-5 short Marathi lines, may end with one English line.
- hashtags: 10-15, Marathi first (#मराठीसुविचार #प्रेरणा #मराठीमोटिवेशन ...), then Hindi/English (#motivation #shorts #reels ...).
- tags: 10-15 search tags (marathi motivation, मराठी सुविचार, ...).
Return EXACTLY this JSON shape (keys in English, values in Marathi):
{"theme": "...", "title": "...",
 "hook": {"text": "on-screen line <= 40 chars", "say": "one spoken sentence"},
 "lines": [{"text": "<= 45 chars", "key": "ONE word copied from text", "say": "1-2 spoken sentences"}, ... 4-6 items],
 "close": {"text": "<= 40 chars call to action", "say": "spoken sentence"},
 "caption": "...", "hashtags": ["#...", ...], "tags": ["...", ...]}"""

PALETTES = [  # (top, bottom, accent, text, style)
    ((16, 24, 48), (46, 20, 80), (255, 196, 0), (255, 255, 255), "bold"),
    ((5, 40, 45), (10, 90, 80), (255, 140, 60), (255, 255, 255), "bold"),
    ((60, 12, 20), (140, 30, 40), (255, 210, 90), (255, 250, 240), "classic"),
    ((248, 243, 230), (232, 220, 195), (180, 40, 30), (30, 30, 40), "classic"),
    ((12, 12, 14), (40, 40, 48), (0, 220, 160), (255, 255, 255), "bold"),
    ((20, 40, 90), (10, 20, 50), (255, 120, 150), (255, 255, 255), "playful"),
    ((255, 140, 40), (200, 60, 30), (255, 255, 255), (40, 20, 10), "playful"),
]
FONTS = {"bold": ("Mukta-ExtraBold.ttf", "Mukta-Medium.ttf"), "classic": ("TiroDevanagariMarathi-Regular.ttf", "Mukta-Medium.ttf"),
         "playful": ("Baloo2[wght].ttf", "Mukta-Medium.ttf")}


class Typo:
    def __init__(self, style):
        if not features.check("raqm"):
            raise RuntimeError("Pillow raqm not available - Marathi text would render wrongly. python -m pip install --upgrade Pillow; keep assets/dll/fribidi-0.dll (yt_studio) on PATH")
        self.head, self.body = FONTS[style]
        self._c = {}

    def font(self, name, size):
        k = (name, size)
        if k not in self._c:
            f = ImageFont.truetype(os.path.join(FONT_DIR, name), size, layout_engine=ImageFont.Layout.RAQM)
            if "[wght]" in name:
                try:
                    f.set_variation_by_axes([800])
                except Exception:
                    pass
            self._c[k] = f
        return self._c[k]

    def wrap(self, d, text, font, max_w):
        lines, cur = [], ""
        for w in str(text).split():
            t = (cur + " " + w).strip()
            if d.textlength(t, font=font) <= max_w or not cur:
                cur = t
            else:
                lines.append(cur)
                cur = w
        if cur:
            lines.append(cur)
        return lines

    def fit(self, d, text, name, max_w, max_h, start, min_size=44, spacing=1.25):
        size = start
        while size > min_size:
            f = self.font(name, size)
            lines = self.wrap(d, text, f, max_w)
            if len(lines) * int(size * spacing) <= max_h and all(d.textlength(l, font=f) <= max_w for l in lines):
                return f, lines, int(size * spacing)
            size -= 4
        f = self.font(name, min_size)
        return f, self.wrap(d, text, f, max_w), int(min_size * spacing)


def _gradient(top, bottom):
    img = Image.new("RGB", (W, H), top)
    px = img.load()
    for y in range(H):
        t = y / (H - 1)
        c = tuple(int(top[i] + (bottom[i] - top[i]) * t) for i in range(3))
        for x in range(W):
            px[x, y] = c
    # soft glow blobs
    glow = Image.new("RGB", (W, H), (0, 0, 0))
    g = ImageDraw.Draw(glow)
    g.ellipse((-200, -100, 500, 600), fill=tuple(min(255, c + 40) for c in top))
    g.ellipse((600, 1300, 1400, 2100), fill=tuple(min(255, c + 30) for c in bottom))
    glow = glow.filter(ImageFilter.GaussianBlur(160))
    return Image.blend(img, Image.composite(glow, img, glow.convert("L").point(lambda v: min(255, v * 2))), 0.35)


def _mix(a, b, t):
    return tuple(int(a[i] + (b[i] - a[i]) * t) for i in range(3))


class Slides:
    def __init__(self, cfg, seed):
        idx = int(hashlib.md5(seed.encode("utf-8")).hexdigest(), 16) % len(PALETTES)
        self.top, self.bottom, self.accent, self.ink, self.style = PALETTES[idx]
        self.bg = _gradient(self.top, self.bottom)
        self.t = Typo(self.style)
        self.brand = cfg.get("channel_name", "स्वाध्याय")
        self.tag = cfg.get("channel_tagline", "")
        self.muted = _mix(self.ink, self.top, 0.45)

    def _base(self, progress):
        img = self.bg.copy()
        d = ImageDraw.Draw(img)
        f = self.t.font(self.t.body, 40)
        d.text((W // 2, 110), self.brand, font=f, fill=self.accent, anchor="mm")
        d.rounded_rectangle((W // 2 - 28, 150, W // 2 + 28, 156), 3, fill=self.accent)
        # progress bar
        d.rounded_rectangle((90, H - 150, W - 90, H - 138), 6, fill=_mix(self.ink, self.top, 0.75))
        d.rounded_rectangle((90, H - 150, 90 + int((W - 180) * max(0.02, progress)), H - 138), 6, fill=self.accent)
        if self.tag:
            d.text((W // 2, H - 90), self.tag, font=self.t.font(self.t.body, 30), fill=self.muted, anchor="mm")
        return img, d

    def _headline(self, d, text, key, y_center, start=112, max_h=1000):
        f, lines, lh = self.t.fit(d, text, self.t.head, W - 180, max_h, start)
        y = y_center - len(lines) * lh // 2
        key = (key or "").strip()
        for line in lines:
            if key and key in line:
                pre, post = line.split(key, 1)
                total = d.textlength(line, font=f)
                x = W // 2 - total / 2
                d.text((x, y), pre, font=f, fill=self.ink, anchor="la")
                x += d.textlength(pre, font=f)
                kw = d.textlength(key, font=f)
                d.rounded_rectangle((x - 10, y + lh * 0.82, x + kw + 10, y + lh * 0.9), 4, fill=self.accent)
                d.text((x, y), key, font=f, fill=self.accent, anchor="la")
                d.text((x + kw, y), post, font=f, fill=self.ink, anchor="la")
            else:
                d.text((W // 2, y), line, font=f, fill=self.ink, anchor="ma")
            y += lh
        return y

    def hook(self, text, progress):
        img, d = self._base(progress)
        d.text((W // 2, 560), "\u201C", font=self.t.font(self.t.head, 260), fill=self.accent, anchor="mm")
        self._headline(d, text, None, H // 2 + 40, start=124)
        return img

    def line(self, text, key, no, total, progress):
        img, d = self._base(progress)
        pill = self.t.font(self.t.body, 44)
        d.rounded_rectangle((W // 2 - 90, 470, W // 2 + 90, 540), 35, outline=self.accent, width=4)
        d.text((W // 2, 505), f"{no} / {total}", font=pill, fill=self.accent, anchor="mm")
        self._headline(d, text, key, H // 2 + 20)
        return img

    def close(self, text, progress):
        img, d = self._base(progress)
        self._headline(d, text, None, H // 2 - 60, start=104, max_h=700)
        f = self.t.font(self.t.body, 46)
        d.rounded_rectangle((W // 2 - 300, H // 2 + 330, W // 2 + 300, H // 2 + 420), 45, fill=self.accent)
        d.text((W // 2, H // 2 + 375), "Like • Share • Subscribe", font=f, fill=self.top, anchor="mm")
        return img

    def cover(self, title):
        img, d = self._base(1.0)
        self._headline(d, title, None, H // 2, start=128)
        return img


def _slug(s):
    s = re.sub(r"[^\w\u0900-\u097F]+", "_", str(s), flags=re.U).strip("_")
    return s[:60] or "short"


def generate(cfg, theme, trend_titles, kw, log=print):
    ex = "\n".join(f"- {t}" for t in trend_titles[:25])
    user = (f"THEME for this Short: {theme}\n\nTrending Marathi motivation Shorts right now (titles only, for tone/topic research - "
            f"do NOT copy or reuse wording):\n{ex}\n\nFrequent words in trending titles: {', '.join(kw[:20])}\n\n"
            "Write the JSON script now.")
    script, model = chat_json(cfg, SYSTEM, user, max_tokens=3000, log=log)
    script["model"] = model
    if not script.get("lines") or not script.get("hook"):
        raise ValueError("LLM returned no lines/hook")
    script["lines"] = [l for l in script["lines"] if l.get("text")][:6]
    script.setdefault("close", {"text": "आजपासून सुरुवात करा!", "say": "आजपासून सुरुवात करा."})
    script.setdefault("hashtags", [])
    script.setdefault("tags", [])
    script.setdefault("caption", "")
    return script


def build(cfg, script, out_dir, log=print):
    sl, au = os.path.join(out_dir, "slides"), os.path.join(out_dir, "audio")
    os.makedirs(sl, exist_ok=True)
    os.makedirs(au, exist_ok=True)
    S = Slides(cfg, script.get("theme", "") + script.get("title", ""))
    steps = [("00_hook", script["hook"]["text"], None, script["hook"].get("say"), "hook")]
    n = len(script["lines"])
    for i, l in enumerate(script["lines"], 1):
        steps.append((f"{i:02d}_line", l["text"], l.get("key"), l.get("say"), ("line", i, n)))
    steps.append(("99_close", script["close"]["text"], None, script["close"].get("say"), "close"))
    items, total = [], 0.0
    for i, (name, text, key, say, kind) in enumerate(steps):
        prog = i / (len(steps) - 1)
        img = S.hook(text, prog) if kind == "hook" else S.close(text, prog) if kind == "close" else S.line(text, key, kind[1], kind[2], prog)
        png = os.path.join(sl, name + ".png")
        img.save(png)
        aud, secs = None, 0.7
        if say:
            aud = os.path.join(au, name + ".mp3")
            if not os.path.exists(aud):
                speak(cfg, say, aud, "mr", log=log)
            secs += duration(cfg, aud)
        items.append((png, aud, secs))
        total += secs
    log(f"[short] {len(items)} slides, {total:.0f} s")
    S.cover(script["title"]).save(os.path.join(out_dir, "cover.png"))
    mp4 = os.path.join(out_dir, "short.mp4")
    _concat(cfg, items, os.path.join(out_dir, "narration.wav"), mp4, (W, H))
    return mp4, total


def run(cfg, unit, trend_titles, kw, out_dir, upload=None, log=print):
    os.makedirs(out_dir, exist_ok=True)
    sp = os.path.join(out_dir, "script.json")
    if os.path.exists(sp):
        script = json.load(open(sp, encoding="utf-8"))
        log("[script] reusing script.json (delete it to regenerate)")
    else:
        log(f"[script] writing original Marathi script on theme: {unit['theme']}")
        script = generate(cfg, unit["theme"], trend_titles, kw, log)
        json.dump(script, open(sp, "w", encoding="utf-8"), ensure_ascii=False, indent=1)
    mp4, secs = build(cfg, script, out_dir, log)
    tags = " ".join(script["hashtags"])
    desc = script["caption"].strip() + "\n\n" + tags
    open(os.path.join(out_dir, "caption.txt"), "w", encoding="utf-8").write(f"{script['title']}\n\n{desc}\n")
    meta = {"title": script["title"], "short_title": script["title"][:100], "description": script["caption"], "hashtags": script["hashtags"],
            "tags": script["tags"], "lang": "mr", "video": mp4, "short": mp4, "seconds": secs, "out_dir": out_dir, "theme": unit["theme"]}
    if upload is None:
        upload = cfg["youtube"]["enabled"]
    if upload:
        from ytstudio import youtube
        meta["short_url"] = youtube.upload(cfg, mp4, meta, None, is_short=True, log=log)
        meta["youtube_url"] = meta["short_url"]
    json.dump(meta, open(os.path.join(out_dir, "manifest.json"), "w", encoding="utf-8"), ensure_ascii=False, indent=1)
    return meta
