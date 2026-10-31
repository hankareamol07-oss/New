"""vidIQ-style Growth / Analytics for the channel: YouTube Analytics API stats, SEO score per video,
LLM 'what to do' plan, topic/keyword trend finder, share texts. Everything cached under <out_dir>/_growth/."""
import datetime as dt
import json
import os
import re
import sys

from ytstudio import youtube
from ytstudio.llm import chat_json

HERE = os.path.dirname(sys.executable) if getattr(sys, "frozen", False) else os.path.dirname(os.path.abspath(__file__))
ANALYTICS_SCOPE = "https://www.googleapis.com/auth/yt-analytics.readonly"
if ANALYTICS_SCOPE not in youtube.SCOPES:
    youtube.SCOPES.append(ANALYTICS_SCOPE)

SYSTEM = ("You are a YouTube growth coach for a Marathi-medium education channel (Maharashtra board std 1-8 chapter explanations, "
          "scholarship/NMMS practice). Answer in simple Marathi (Devanagari), concrete and short. Return JSON only.")


def gdir(cfg):
    d = os.path.join(cfg["out_dir"], "_growth")
    os.makedirs(d, exist_ok=True)
    return d


def _services(cfg):
    from googleapiclient.discovery import build
    yt = youtube.service(cfg)
    an = build("youtubeAnalytics", "v2", credentials=yt._http.credentials)
    return yt, an


def _rows(res, names=None):
    cols = [c["name"] for c in res.get("columnHeaders", [])]
    return [dict(zip(cols, r)) for r in res.get("rows", [])]


def fetch(cfg, days=28, log=print):
    """Channel + per-video stats for the last N days -> _growth/analytics.json."""
    yt, an = _services(cfg)
    ch = yt.channels().list(part="snippet,statistics,contentDetails", mine=True).execute()["items"][0]
    end = dt.date.today()
    start = end - dt.timedelta(days=days)
    q = dict(ids="channel==MINE", startDate=start.isoformat(), endDate=end.isoformat())
    tot = _rows(an.reports().query(metrics="views,estimatedMinutesWatched,averageViewDuration,averageViewPercentage,subscribersGained,subscribersLost,likes,comments,shares", **q).execute())
    daily = _rows(an.reports().query(dimensions="day", metrics="views,estimatedMinutesWatched,subscribersGained", sort="day", **q).execute())
    traffic = _rows(an.reports().query(dimensions="insightTrafficSourceType", metrics="views,estimatedMinutesWatched", sort="-views", **q).execute())
    top = _rows(an.reports().query(dimensions="video", metrics="views,estimatedMinutesWatched,averageViewDuration,averageViewPercentage,likes,comments,shares,subscribersGained", sort="-views", maxResults=25, **q).execute())
    try:
        ctr = {r["video"]: r for r in _rows(an.reports().query(dimensions="video", metrics="views,impressions,impressionsClickThroughRate", sort="-views", maxResults=25, **q).execute())}
    except Exception as e:   # impressions need the channel to be eligible; keep going without CTR
        log(f"[growth] CTR not available: {str(e)[:100]}")
        ctr = {}
    geo = _rows(an.reports().query(dimensions="country", metrics="views", sort="-views", maxResults=5, **q).execute())
    dev = _rows(an.reports().query(dimensions="deviceType", metrics="views", sort="-views", **q).execute())
    # titles / publish dates / tags of the top videos + recent uploads
    ids = [r["video"] for r in top]
    uploads = yt.playlistItems().list(part="contentDetails", playlistId=ch["contentDetails"]["relatedPlaylists"]["uploads"], maxResults=50).execute()
    recent = [i["contentDetails"]["videoId"] for i in uploads.get("items", [])]
    ids = list(dict.fromkeys(ids + recent))[:50]
    vids = {}
    for i in range(0, len(ids), 50):
        for v in yt.videos().list(part="snippet,statistics,contentDetails", id=",".join(ids[i:i + 50])).execute().get("items", []):
            sn, st = v["snippet"], v.get("statistics", {})
            vids[v["id"]] = {"id": v["id"], "title": sn["title"], "published": sn["publishedAt"], "tags": sn.get("tags", []),
                             "description": sn.get("description", ""), "thumbnail": sn["thumbnails"].get("high", {}).get("url"),
                             "views_total": int(st.get("viewCount", 0)), "likes_total": int(st.get("likeCount", 0)), "comments_total": int(st.get("commentCount", 0)),
                             "duration": v["contentDetails"]["duration"], "url": f"https://youtu.be/{v['id']}"}
    for r in top:
        r.update(vids.get(r["video"], {}))
        r.update({k: v for k, v in ctr.get(r["video"], {}).items() if k != "views"})
        r["seo"] = seo_score(r)
    data = {"fetched": dt.datetime.now().isoformat(timespec="minutes"), "days": days,
            "channel": {"title": ch["snippet"]["title"], "id": ch["id"], "subscribers": int(ch["statistics"].get("subscriberCount", 0)),
                        "videos": int(ch["statistics"].get("videoCount", 0)), "views_total": int(ch["statistics"].get("viewCount", 0))},
            "period": tot[0] if tot else {}, "daily": daily, "traffic": traffic, "top": top, "geo": geo, "devices": dev,
            "recent": [vids[i] | {"seo": seo_score(vids[i])} for i in recent if i in vids]}
    json.dump(data, open(os.path.join(gdir(cfg), "analytics.json"), "w", encoding="utf-8"), ensure_ascii=False, indent=1)
    return data


def load(cfg):
    p = os.path.join(gdir(cfg), "analytics.json")
    return json.load(open(p, encoding="utf-8")) if os.path.exists(p) else None


def seo_score(v):
    """0-100 like vidIQ's SEO score: title length/keywords, description length, timestamps, tags, hashtags."""
    t, d, tags = v.get("title", "") or "", v.get("description", "") or "", v.get("tags") or []
    s, why = 0, []
    s += 15 if 40 <= len(t) <= 95 else 5
    why.append("शीर्षक लांबी ठीक" if 40 <= len(t) <= 95 else "शीर्षक 40-95 अक्षरांचे करा")
    s += 10 if re.search(r"(इ\.|इयत्ता|std|class)\s*\d", t, re.I) else 0
    why.append("" if re.search(r"(इ\.|इयत्ता|std|class)\s*\d", t, re.I) else "शीर्षकात इयत्ता लिहा (इ. 6 वी)")
    s += 10 if re.search(r"maharashtra|balbharati|बालभारती|महाराष्ट्र", t + d, re.I) else 0
    s += 15 if len(d) >= 400 else (8 if len(d) >= 150 else 0)
    why.append("" if len(d) >= 400 else "वर्णन 400+ अक्षरांचे करा (सारांश + कीवर्ड)")
    s += 10 if re.search(r"\b\d{1,2}:\d{2}\b", d) else 0
    why.append("" if re.search(r"\b\d{1,2}:\d{2}\b", d) else "वर्णनात timestamps टाका")
    s += 15 if len(tags) >= 12 else (8 if len(tags) >= 5 else 0)
    why.append("" if len(tags) >= 12 else "12+ टॅग्ज टाका")
    s += 10 if d.count("#") >= 3 else 0
    why.append("" if d.count("#") >= 3 else "3-6 हॅशटॅग टाका")
    s += 10 if re.search(r"[A-Za-z]", t) and re.search(r"[\u0900-\u097F]", t) else 5
    why.append("" if re.search(r"[A-Za-z]", t) and re.search(r"[\u0900-\u097F]", t) else "शीर्षकात मराठी + इंग्रजी दोन्ही कीवर्ड")
    s += 5 if (v.get("thumbnail") or v.get("thumbnail_text")) else 0
    return {"score": min(100, s), "fix": [w for w in why if w]}


def summary_text(data):
    p, c = data.get("period", {}), data.get("channel", {})
    L = [f"चॅनेल: {c.get('title')}   सबस्क्राइबर: {c.get('subscribers'):,}   व्हिडिओ: {c.get('videos')}   एकूण views: {c.get('views_total'):,}",
         f"गेले {data['days']} दिवस ({data['fetched']}):  views {int(p.get('views', 0)):,} | watch {int(p.get('estimatedMinutesWatched', 0)):,} min | "
         f"सरासरी पाहिले {int(p.get('averageViewDuration', 0))} s ({p.get('averageViewPercentage', 0):.0f}%) | +{p.get('subscribersGained', 0)} / -{p.get('subscribersLost', 0)} subs | "
         f"likes {p.get('likes', 0)} | comments {p.get('comments', 0)} | shares {p.get('shares', 0)}", ""]
    if data.get("traffic"):
        L.append("Views कुठून आले: " + ", ".join(f"{r['insightTrafficSourceType']} {int(r['views'])}" for r in data["traffic"][:6]))
    if data.get("devices"):
        L.append("Device: " + ", ".join(f"{r['deviceType']} {int(r['views'])}" for r in data["devices"]))
    if data.get("geo"):
        L.append("देश: " + ", ".join(f"{r['country']} {int(r['views'])}" for r in data["geo"]))
    L += ["", f"{'views':>7} {'avg%':>5} {'CTR':>5} {'SEO':>4}  शीर्षक"]
    for r in data.get("top", []):
        ctr = f"{r['impressionsClickThroughRate']:.1f}%" if r.get("impressionsClickThroughRate") is not None else "-"
        L.append(f"{int(r.get('views', 0)):>7} {r.get('averageViewPercentage', 0):>4.0f}% {ctr:>5} {r.get('seo', {}).get('score', 0):>4}  {r.get('title', r['video'])[:70]}")
    return "\n".join(L)


def plan(cfg, data, log=print):
    """LLM: what to do this week, from the numbers -> _growth/plan.json."""
    slim = {"channel": data["channel"], "period": data["period"], "traffic": data["traffic"][:6], "devices": data.get("devices"),
            "daily": [(d["day"], int(d["views"])) for d in data["daily"]],
            "top": [{k: r.get(k) for k in ("title", "views", "averageViewPercentage", "averageViewDuration", "impressionsClickThroughRate", "likes", "comments", "published", "tags")} | {"seo": r.get("seo")} for r in data["top"][:15]],
            "recent": [{"title": r["title"], "published": r["published"], "views_total": r["views_total"], "seo": r["seo"]} for r in data.get("recent", [])[:15]]}
    user = f"""Channel analytics (last {data['days']} days):
{json.dumps(slim, ensure_ascii=False)}

Give a vidIQ-style action plan. Return JSON:
{{"health": "2 lines: what is going well / what is the main problem (use the numbers)",
 "today": ["5-7 concrete tasks for today, each 1 line, most important first"],
 "week": ["5 tasks for this week"],
 "fix_videos": [{{"title": "<existing video title>", "do": "what to change (title/thumbnail/description/pinned comment) and the exact new title"}} for 3-5 videos with high views but low CTR/avg% or low SEO],
 "next_videos": [{{"title": "exact title", "why": "data reason (e.g. series of top video / season / exam date)"}} for 6 videos to make next, std 1-8 Maharashtra board; mix explanation, स्वाध्याय, scholarship/NMMS, Shorts],
 "shorts": ["4 Shorts ideas (<60 s) cut from existing top videos or quick tricks"],
 "posting": {{"best_days": ["..."], "best_time": "HH:MM IST", "frequency": "...", "reason": "from the daily curve + school hours"}},
 "thumbnail_rules": ["3 rules for this channel's thumbnails (big Marathi text, face, std badge ...)"],
 "community": ["3 community-post / poll texts in Marathi for this week"],
 "kpis": {{"views_next_28d": int, "subs_next_28d": int, "avg_view_pct_target": int}}}}"""
    out, model = chat_json(cfg, SYSTEM, user, max_tokens=6000, log=log)
    out["made"] = dt.datetime.now().isoformat(timespec="minutes")
    out["model"] = model
    json.dump(out, open(os.path.join(gdir(cfg), "plan.json"), "w", encoding="utf-8"), ensure_ascii=False, indent=1)
    return out


def plan_text(p):
    L = ["=== चॅनेलची स्थिती ===", p.get("health", ""), "", "=== आज करा ==="] + [f"☐ {t}" for t in p.get("today", [])]
    L += ["", "=== या आठवड्यात ==="] + [f"☐ {t}" for t in p.get("week", [])]
    L += ["", "=== हे व्हिडिओ सुधारा ==="] + [f"- {f.get('title')}\n    → {f.get('do')}" for f in p.get("fix_videos", [])]
    L += ["", "=== पुढचे व्हिडिओ ==="] + [f"- {f.get('title')}\n    ({f.get('why')})" for f in p.get("next_videos", [])]
    L += ["", "=== Shorts ==="] + [f"- {t}" for t in p.get("shorts", [])]
    po = p.get("posting", {})
    L += ["", "=== कधी पोस्ट करायचे ===", f"दिवस: {', '.join(po.get('best_days', []))}   वेळ: {po.get('best_time')}   वारंवारता: {po.get('frequency')}", f"कारण: {po.get('reason')}"]
    L += ["", "=== थंबनेल नियम ==="] + [f"- {t}" for t in p.get("thumbnail_rules", [])]
    L += ["", "=== Community posts ==="] + [f"- {t}" for t in p.get("community", [])]
    k = p.get("kpis", {})
    L += ["", f"=== 28 दिवसांचे लक्ष्य ===  views {k.get('views_next_28d')}  |  subs +{k.get('subs_next_28d')}  |  avg view {k.get('avg_view_pct_target')}%", "", f"({p.get('made')}, {p.get('model')})"]
    return "\n".join(L)


def trends(cfg, data, topic="", log=print):
    """Keyword / topic finder: YouTube search (what already ranks) + LLM suggestions -> _growth/trends.json."""
    yt = youtube.service(cfg)
    queries = [q for q in [topic, "इयत्ता स्वाध्याय", "scholarship exam marathi", "NMMS", "balbharati explanation"] if q][:5]
    found = []
    for q in queries:
        try:
            res = yt.search().list(part="snippet", q=q, type="video", order="viewCount", regionCode="IN", relevanceLanguage="mr",
                                   publishedAfter=(dt.datetime.utcnow() - dt.timedelta(days=90)).isoformat("T") + "Z", maxResults=10).execute()
            ids = [i["id"]["videoId"] for i in res.get("items", [])]
            st = {v["id"]: v for v in yt.videos().list(part="statistics,snippet", id=",".join(ids)).execute().get("items", [])} if ids else {}
            for i in res.get("items", []):
                v = st.get(i["id"]["videoId"], {})
                found.append({"q": q, "title": i["snippet"]["title"], "channel": i["snippet"]["channelTitle"], "views": int(v.get("statistics", {}).get("viewCount", 0)),
                              "tags": v.get("snippet", {}).get("tags", [])[:15], "url": f"https://youtu.be/{i['id']['videoId']}"})
        except Exception as e:
            log(f"[growth] search '{q}': {str(e)[:120]}")
    month = dt.date.today().strftime("%B")
    user = f"""Month: {month}. Our channel's top videos: {[r.get('title') for r in data.get('top', [])[:10]] if data else []}.
Videos ranking now on YouTube (last 90 days) for our topics:
{json.dumps(found[:40], ensure_ascii=False)}
Teacher's focus topic: {topic or '(none)'}

Return JSON: {{"keywords": [{{"kw": "search phrase (Marathi or roman)", "demand": "high|medium|low", "competition": "high|medium|low", "use_in": "title|tags|description"}} for 20],
 "topics": [{{"title": "video title to make", "std": int, "subject": "...", "why": "season/exam/trend reason"}} for 10],
 "hashtags": ["12 hashtags that these ranking videos use + our own"],
 "calendar": [{{"week": 1-4, "videos": ["3 titles"]}}],
 "insight": "3 lines in Marathi: what the ranking videos do that we should copy"}}"""
    out, model = chat_json(cfg, SYSTEM, user, max_tokens=6000, log=log)
    out.update({"found": found, "made": dt.datetime.now().isoformat(timespec="minutes"), "model": model, "topic": topic})
    json.dump(out, open(os.path.join(gdir(cfg), "trends.json"), "w", encoding="utf-8"), ensure_ascii=False, indent=1)
    return out


def trends_text(t):
    L = ["=== निरीक्षण ===", t.get("insight", ""), "", "=== कीवर्ड (मागणी / स्पर्धा / कुठे वापरायचा) ==="]
    L += [f"- {k.get('kw')}   [{k.get('demand')}/{k.get('competition')}]  → {k.get('use_in')}" for k in t.get("keywords", [])]
    L += ["", "=== बनवायचे व्हिडिओ ==="] + [f"- इ.{x.get('std')} {x.get('subject')}: {x.get('title')}  ({x.get('why')})" for x in t.get("topics", [])]
    L += ["", "=== हॅशटॅग ===", " ".join(t.get("hashtags", [])), "", "=== 4 आठवड्यांचे कॅलेंडर ==="]
    L += [f"आठवडा {c.get('week')}: " + " | ".join(c.get("videos", [])) for c in t.get("calendar", [])]
    L += ["", "=== सध्या ranking व्हिडिओ ==="] + [f"{f['views']:>8}  {f['title'][:60]}  — {f['channel']}  {f['url']}" for f in sorted(t.get("found", []), key=lambda x: -x["views"])[:25]]
    return "\n".join(L)


def share_text(out_dir):
    """WhatsApp / Telegram share messages for a rendered video (needs youtube_seo.json + youtube_url in manifest)."""
    seo = json.load(open(os.path.join(out_dir, "youtube_seo.json"), encoding="utf-8"))
    man = json.load(open(os.path.join(out_dir, "manifest.json"), encoding="utf-8"))
    url = man.get("youtube_url") or "<video link>"
    first = seo["description"].split("\n\n")[1] if "\n\n" in seo["description"] else ""
    wa = f"🎓 *{seo['title']}*\n\n{first[:300]}\n\n▶ {url}\n\n{' '.join(seo['hashtags'][:4])}\nव्हिडिओ आवडल्यास पालक/विद्यार्थी गटात शेअर करा 🙏"
    tg = f"🎓 {seo['title']}\n{url}\n{' '.join(seo['hashtags'][:4])}"
    sms = f"{seo['title'][:60]} - {url}"
    txt = "=== WhatsApp ===\n" + wa + "\n\n=== Telegram ===\n" + tg + "\n\n=== SMS / status ===\n" + sms
    open(os.path.join(out_dir, "share_text.txt"), "w", encoding="utf-8").write(txt)
    return txt
