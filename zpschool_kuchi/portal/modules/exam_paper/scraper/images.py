"""Mirror question images from downloadpapers.com into images/<id>[_2|_a].png and
record which questions have images in images_index.json."""
import json, glob, os, requests, concurrent.futures as cf, sys

BASE = "https://downloadpapers.com"
OUT = "images"
os.makedirs(OUT, exist_ok=True)
tok = requests.post(f"{BASE}/login", params={"username": os.environ.get("DP_USER", ""), "password": os.environ.get("DP_PASS", "")}).json()["token"]
S = requests.Session()
S.headers["TOKEN"] = tok

ids = set()
for f in glob.glob("questions/*.json"):
    for q in json.load(open(f)):
        ids.add(q["questionid"])
        for sq in q.get("questionList") or []:
            if sq.get("questionid"):
                ids.add(sq["questionid"])
ids = sorted(ids)
print(len(ids), "question ids", flush=True)

ENDPOINTS = [("questionimage", ""), ("questionimage2", "_2"), ("answerimage", "_a")]


def fetch(qid):
    found = {}
    for ep, suffix in ENDPOINTS:
        path = f"{OUT}/{qid}{suffix}.png"
        if os.path.exists(path):
            found[suffix] = True
            continue
        for _ in range(3):
            try:
                r = S.get(f"{BASE}/{ep}/{qid}", timeout=30)
                break
            except requests.RequestException:
                r = None
        if r is not None and r.status_code == 200 and r.headers.get("content-type", "").startswith("image") and len(r.content) > 50:
            with open(path, "wb") as fh:
                fh.write(r.content)
            found[suffix] = True
    return qid, found


index = {}
with cf.ThreadPoolExecutor(16) as ex:
    for i, (qid, found) in enumerate(ex.map(fetch, ids)):
        if found:
            index[qid] = {"image": "" in found, "image2": "_2" in found, "answer": "_a" in found}
        if i % 1000 == 0:
            print(i, "done,", len(index), "with images", flush=True)

json.dump(index, open("images_index.json", "w"))
print("finished:", len(index), "questions with images")
