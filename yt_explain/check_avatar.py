"""Diagnostic: which avatar files / config this install uses, plus a preview image (check_avatar_preview.png)."""
import json, os, sys
HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, HERE)
import avatar
from PIL import Image

print("avatar.py :", avatar.__file__)
print("assets    :", avatar.ASSETS)
for n in ("teacher_f", "teacher_m"):
    p = os.path.join(avatar.ASSETS, n, "manifest.json")
    print(f"{n:10}: manifest={'OK' if os.path.exists(p) else 'MISSING'}", end="")
    if os.path.exists(p):
        m = json.load(open(p, encoding="utf-8"))
        print(f"  flip={m.get('flip')}  frames={sum(len(v) for v in m['groups'].values())}", end="")
    print()
cp = os.path.join(HERE, "config.json")
cfg = json.load(open(cp, encoding="utf-8")) if os.path.exists(cp) else {}
print("config.json avatar section:", json.dumps(cfg.get("avatar"), ensure_ascii=False))
import inspect
print("flip-aware avatar.py:", "flip" in inspect.getsource(avatar.Sheet.__init__))
for n in ("teacher_f", "teacher_m"):
    c = {"avatar": dict(cfg.get("avatar") or {}, enabled=True, name=n)}
    print(f"{n}: sprite resolved ->", avatar._sprite_name(c), "class", type(avatar._teacher(c)).__name__)
slide = Image.new("RGB", (1920, 1080), (245, 240, 225))
out = avatar.compose({"avatar": {"enabled": True, "name": "teacher_f"}}, slide, "point", 0.3, mouth=0.4)
dst = os.path.join(HERE, "check_avatar_preview.png")
out.save(dst)
print("preview saved:", dst)
