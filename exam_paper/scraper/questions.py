import json, os, re, sys, time, requests, openpyxl, glob
from concurrent.futures import ThreadPoolExecutor

BASE = "https://downloadpapers.com"
OUT = "."
TYPES = ["mcq", "descriptive", "fillinblanks"]
QNOS = [f"{n}{s}" for n in range(1, 9) for s in ["", "a", "b", "c"]]

phones = [os.environ["DP_USER"]]
for f in glob.glob(os.environ.get("DP_INSTITUTES_XLSX", "institutes/*.xlsx")):
    for ws in openpyxl.load_workbook(f, read_only=True):
        for row in ws.iter_rows(min_row=2, values_only=True):
            if row[2] and str(row[2]).isdigit() and row[2] not in phones:
                phones.append(str(row[2]))

sessions = {}
def session(phone):
    if phone not in sessions:
        r = requests.post(f"{BASE}/login", params={"username": phone, "password": os.environ["DP_PASS"]}).json()
        s = requests.Session()
        s.headers["TOKEN"] = r.get("token", "")
        sessions[phone] = s if r.get("status") == "success" else None
    return sessions[phone]

def req(s, method, path, **kw):
    for i in range(4):
        try:
            r = s.request(method, BASE + path, timeout=90, **kw)
            r.raise_for_status()
            return r.json() if r.text.strip() else []
        except Exception as e:
            print("retry", path, e, file=sys.stderr); time.sleep(3)
    return None

h = json.load(open(f"{OUT}/hierarchy.json"))

# Fill chapters for subjects that were locked for the main institute using other institutes' logins
for st in h:
    for sub in st["subjects"]:
        if sub["chapters"]:
            sub["via"] = phones[0]; continue
        for p in phones[1:]:
            s = session(p)
            if not s: continue
            ch = req(s, "GET", f"/chapters/{sub['subjectid']}")
            if ch:
                for c in ch:
                    c.pop("subject", None)
                    if c.get("hastopics"):
                        c["topicList"] = req(s, "GET", f"/topicsbychapterid/{c['chapterid']}") or []
                sub["chapters"] = ch
                sub["templates"] = sub["templates"] or req(s, "GET", f"/gettemplatebysubjectid/{sub['subjectid']}") or []
                for t in sub["templates"]: t.pop("subject", None)
                sub["via"] = p
                print("unlocked", st["name"], sub["name"], "via", p, len(ch), file=sys.stderr)
                break
json.dump(h, open(f"{OUT}/hierarchy.json", "w"), ensure_ascii=False, indent=1)

def scrape_subject(args):
    st, sub = args
    s = session(sub.get("via", phones[0]))
    chapter_ids = [c["chapterid"] for c in sub["chapters"]]
    if not chapter_ids or not s:
        return sub["subjectid"], []
    seen = {}
    for t in TYPES:
        for q in QNOS:
            d = req(s, "POST", "/chooserandomqquestions", json={"subjectid": sub["subjectid"], "chapterids": chapter_ids,
                    "questionnumber": q, "questiontype": t, "noofquestions": 100000})
            for x in d or []:
                x["chapterid"] = x["chapter"]["chapterid"]; x.pop("chapter", None)
                for sq in x.get("questionList") or []:
                    sq.pop("chapter", None)
                seen[x["questionid"]] = x
    print(st["name"], st["medium"], sub["name"], len(seen), "questions", file=sys.stderr)
    return sub["subjectid"], list(seen.values())

jobs = [(st, sub) for st in h for sub in st["subjects"] if sub["chapters"]]
os.makedirs(f"{OUT}/questions", exist_ok=True)
with ThreadPoolExecutor(6) as ex:
    for sid, qs in ex.map(scrape_subject, jobs):
        json.dump(qs, open(f"{OUT}/questions/{sid}.json", "w"), ensure_ascii=False)
