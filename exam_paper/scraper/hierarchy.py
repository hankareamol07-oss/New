import os
import json, re, requests, sys, time

BASE = "https://downloadpapers.com"
tok = requests.post(f"{BASE}/login", params={"username": os.environ["DP_USER"], "password": os.environ["DP_PASS"]}).json()["token"]
S = requests.Session()
S.headers["TOKEN"] = tok

def get(path, **params):
    for i in range(3):
        try:
            r = S.get(BASE + path, params=params, timeout=60)
            r.raise_for_status()
            if not r.text.strip():
                return []
            return r.json()
        except Exception as e:
            print("retry", path, e, file=sys.stderr); time.sleep(2)
    return None

standards = get("/standards")
keep = [s for s in standards if re.match(r"^\s*[1-8]\b", s["name"]) or re.match(r"^\s*[1-8](st|nd|rd|th)\b", s["name"])]
print(len(keep), "standards kept", file=sys.stderr)
out = []
for s in keep:
    s["subjects"] = get(f"/subjects/{s['standardid']}") or []
    for sub in s["subjects"]:
        sub.pop("standard", None)
        sub["chapters"] = get(f"/chapters/{sub['subjectid']}") or []
        sub["templates"] = get(f"/gettemplatebysubjectid/{sub['subjectid']}") or []
        for t in sub["templates"]:
            t.pop("subject", None)
        for c in sub["chapters"]:
            c.pop("subject", None)
            if c.get("hastopics"):
                c["topicList"] = get(f"/topicsbychapterid/{c['chapterid']}") or []
        print(s["name"], s["medium"], sub["name"], len(sub["chapters"]), "ch", len(sub["templates"]), "tpl", file=sys.stderr)
    out.append(s)
json.dump(out, open("hierarchy.json", "w"), ensure_ascii=False, indent=1)
