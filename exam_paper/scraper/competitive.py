"""Scrape topic-wise objective (MCQ) questions - Scholarship / NMMS / Navodaya etc. - from downloadpapers.com.

Usage: DP_USER=... DP_PASS=... python3 competitive.py  (run inside exam_paper/data)
Reads hierarchy.json, writes competitive/questions.json + competitive/images/.
"""
import glob
import json
import os
import re
import sys
import time
from concurrent.futures import ThreadPoolExecutor

import openpyxl
import requests

BASE = "https://downloadpapers.com"
OUT = "competitive"
DIFFICULTIES = ["Simple", "Medium", "Hard"]

phones = [os.environ["DP_USER"]]
for f in glob.glob(os.environ.get("DP_INSTITUTES_XLSX", "institutes/*.xlsx")):
    for ws in openpyxl.load_workbook(f, read_only=True):
        for row in ws.iter_rows(min_row=2, values_only=True):
            if row[2] and str(row[2]).isdigit() and str(row[2]) not in phones:
                phones.append(str(row[2]))

sessions = {}


def session(phone):
    if phone not in sessions:
        r = requests.post(f"{BASE}/login", params={"username": phone, "password": os.environ["DP_PASS"]}).json()
        s = requests.Session()
        s.headers["TOKEN"] = r.get("token", "")
        sessions[phone] = s if r.get("status") == "success" else None
    return sessions[phone]


def get(s, path):
    for _ in range(4):
        try:
            r = s.get(BASE + path, timeout=90)
            r.raise_for_status()
            return r.json() if r.text.strip() else None
        except Exception as e:
            print("retry", path, e, file=sys.stderr)
            time.sleep(3)
    return None


def safe(name):
    return re.sub(r"[^\w.-]", "_", name)


def parts(js):
    """questionjson -> (text, [image filenames])."""
    text, imgs = [], []
    for el in json.loads(js or "[]") or []:
        if el.get("type") == "image":
            imgs.append(el.get("content"))
        elif el.get("content"):
            text.append(el["content"])
    return " ".join(text).strip(), [i for i in imgs if i]


def flatten(q, meta, parent=None):
    text, imgs = parts(q.get("questionjson"))
    opts = []
    for o in json.loads(q.get("optionsjson") or "[]") or []:
        t, im = parts(json.dumps(o, ensure_ascii=False))
        opts.append({"text": t, "images": im})
    sol, sol_imgs = parts(q.get("solutionjson"))
    rec = dict(meta, question_id=q["objectivequestionid"], parent_id=parent, difficulty=q.get("difficulty"),
               marks=q.get("marks"), text=text, images=imgs, options=opts,
               correct_option=q.get("correctoptionno"),
               correct_option_label=("ABCD"[q["correctoptionno"]] if isinstance(q.get("correctoptionno"), int) and 0 <= q["correctoptionno"] < 4 else None),
               solution=sol, solution_images=sol_imgs,
               is_group=bool(q.get("objectivequestionList")))
    yield rec
    for c in q.get("objectivequestionList") or []:
        yield from flatten(c, meta, q["objectivequestionid"])


h = json.load(open("hierarchy.json"))
jobs = []
for st in h:
    for sub in st["subjects"]:
        for ch in sub["chapters"]:
            for t in ch.get("topicList") or []:
                jobs.append((st, sub, ch, t))
print(len(jobs), "topics", file=sys.stderr)


def scrape_topic(job):
    st, sub, ch, t = job
    meta = dict(standard_id=st["standardid"], standard=st["name"].strip(), medium=st.get("medium"),
                subject_id=sub["subjectid"], subject=sub["name"].strip(),
                chapter_id=ch["chapterid"], chapter=ch["name"].strip(),
                topic_id=t["topicid"], topic=t["name"].strip(), is_pyq=t.get("ispyq"))
    order = [sub.get("via", phones[0])] + [p for p in phones if p != sub.get("via", phones[0])]
    out = []
    for phone in order:
        s = session(phone)
        if not s:
            continue
        for d in DIFFICULTIES:
            page = 0
            while True:
                r = get(s, f"/objectivequestions?topicid={t['topicid']}&difficulty={d}&pageno={page}&pagesize=100")
                if not r or not r.get("content"):
                    break
                for q in r["content"]:
                    out.extend(flatten(q, meta))
                if r.get("last", True):
                    break
                page += 1
        if out:
            break
    if out:
        print(st["name"], sub["name"], t["name"], len(out), file=sys.stderr)
    return out


os.makedirs(f"{OUT}/images", exist_ok=True)
questions = []
with ThreadPoolExecutor(6) as ex:
    for qs in ex.map(scrape_topic, jobs):
        questions.extend(qs)

seen = set()
dedup = []
for q in questions:
    if q["question_id"] in seen:
        continue
    seen.add(q["question_id"])
    dedup.append(q)
questions = dedup

images = set()
for q in questions:
    images.update(q["images"])
    images.update(q["solution_images"])
    for o in q["options"]:
        images.update(o["images"])
    for k in ("images", "solution_images"):
        q[k] = [f"images/{safe(i)}" for i in q[k]]
    for o in q["options"]:
        o["images"] = [f"images/{safe(i)}" for i in o["images"]]


def fetch_image(name):
    dst = f"{OUT}/images/{safe(name)}"
    if os.path.exists(dst) and os.path.getsize(dst) > 0:
        return
    s = session(phones[0])
    for _ in range(3):
        try:
            r = s.get(f"{BASE}/notesimage/{name}", timeout=60)
            if r.ok and r.content:
                open(dst, "wb").write(r.content)
                return
        except Exception as e:
            print("img retry", name, e, file=sys.stderr)
            time.sleep(2)


with ThreadPoolExecutor(8) as ex:
    list(ex.map(fetch_image, sorted(images)))

json.dump({"source": BASE, "count": len(questions), "image_base": f"{BASE}/notesimage/", "questions": questions},
          open(f"{OUT}/questions.json", "w"), ensure_ascii=False, indent=1)
print("questions", len(questions), "images", len(images), file=sys.stderr)
