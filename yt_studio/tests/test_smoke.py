"""Offline smoke tests (no API keys needed):  python -m pytest tests/  or  python tests/test_smoke.py"""
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from PIL import Image  # noqa: E402

from ytstudio import config  # noqa: E402
from ytstudio.data import Bank, chapter_from_file  # noqa: E402
from ytstudio.script import _fix_chapter_no, pick_questions  # noqa: E402
from ytstudio.slides import Renderer, Text  # noqa: E402

CFG = config.load()


def test_bank_lists_books_and_chapters():
    bank = Bank(CFG["data_dir"])
    assert 6 in bank.standards()
    books = bank.book_list(6)
    assert books
    b = books[0]
    chs = bank.chapters(b["book_id"])
    assert chs and chs[0]["no"] >= 1


def test_source_filter():
    bank = Bank(CFG["data_dir"])
    b = next(x for x in bank.book_list(6) if x["lang"] == "mr")
    ch = bank.chapters(b["book_id"])[0]["no"]
    both = bank.chapter_questions(b["book_id"], ch, "both")
    book = bank.chapter_questions(b["book_id"], ch, "book")
    typed = bank.chapter_questions(b["book_id"], ch, "typed")
    assert len(both) == len(book) + len(typed)
    assert all(q["source"] == "book" for q in book) and all(q["source"] == "typed" for q in typed)


def test_pick_questions_balanced():
    qs = [{"qtype": t, "text": str(i), "answer": "x"} for i, t in enumerate(["mcq"] * 10 + ["fill_blank"] * 10)]
    p = pick_questions(qs, 6, seed=1)
    assert len(p) == 6 and {q["qtype"] for q in p} == {"mcq", "fill_blank"}


def test_chapter_number_forced():
    s = {"title": "इयत्ता 6 वी | विज्ञान | पाठ 12. मापन", "description": "Chapter 12", "hashtags": ["#पाठ12"],
         "thumbnail": {"badge": "पाठ 12"}}
    _fix_chapter_no(s, {"no": 1}, lambda *_: None)
    assert "पाठ 1." in s["title"] and s["description"] == "Chapter 1"
    assert s["hashtags"] == ["#पाठ1"] and s["thumbnail"]["badge"] == "पाठ 1"


def test_mixed_script_rendering_has_no_tofu():
    im = Image.new("RGB", (800, 200), "white")
    from PIL import ImageDraw
    d = ImageDraw.Draw(im)
    t = Text("mr")
    t.draw(d, (10, 10), "मापन Science 37 अंश Maharashtra Board", 48)
    # tofu boxes render as solid rectangles -> very high dark ratio in a glyph cell; check some ink but not a block
    px = im.crop((10, 10, 790, 80)).convert("L")
    hist = px.histogram()
    dark = sum(hist[:128]) / (px.width * px.height)
    assert 0.02 < dark < 0.35


def test_renderer_thumbnail_and_slide(tmp_path=None):
    r = Renderer(CFG, {"std": 6, "subject": "Science", "lang": "mr"}, {"no": 1, "title": "मापन"})
    th = r.thumbnail({"headline": "मापन", "sub": "संपूर्ण स्वाध्याय", "badge": "इ. 6 वी"})
    assert th.size == (1280, 720)
    q = r.question({"no": 1, "qtype": "fill_blank", "instruction": "रिकाम्या जागा भरा", "question": "पाणी ____ अंशाला उकळते.",
                    "answer": "100", "explain": "प्रमाण वातावरणीय दाबावर."}, show_answer=True)
    assert q.size == (1920, 1080)


def test_chapter_from_text_file(tmp_path=None):
    p = os.path.join(os.path.dirname(__file__), "_ch.txt")
    with open(p, "w", encoding="utf-8") as f:
        f.write("पाठ 2. सजीव सृष्टी\nअनुकूलन म्हणजे ...")
    try:
        ch = chapter_from_file(p, 6, "Science", "mr", "सजीव सृष्टी", no=2)
        assert ch["chapter"] == {"no": 2, "title": "सजीव सृष्टी"} and "अनुकूलन" in ch["text"]
    finally:
        os.remove(p)


if __name__ == "__main__":
    for name, fn in list(globals().items()):
        if name.startswith("test_"):
            fn()
            print("ok", name)
