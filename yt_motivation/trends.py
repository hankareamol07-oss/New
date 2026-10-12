"""Find what Marathi motivation Shorts are trending right now (research only - we never copy a script).

Order of sources:
  1. yt-dlp search (no API key / quota): `ytsearch` for each query, sorted by view count and recency.
  2. YouTube Data API search.list (order=viewCount, publishedAfter) with the same OAuth token used for upload.
Results go to motivation.db table `trends` (video_id, title, channel, views, published, query, fetched_at).
Only titles / channel names / view counts are stored - that is all the script writer gets as inspiration.
"""
import datetime as dt
import re

QUERIES = [
    "मराठी motivation shorts", "मराठी प्रेरणादायी विचार", "marathi motivational status", "मराठी सुविचार",
    "marathi motivation video", "प्रेरणादायी मराठी", "मराठी यश विचार", "marathi motivation reels",
]

_TOPIC_STOP = {"shorts", "short", "status", "video", "reels", "motivation", "motivational", "marathi", "मराठी", "#shorts", "|", "-"}


def _yt_dlp(query, n, log):
    try:
        import yt_dlp
    except ImportError:
        log("[trends] yt-dlp not installed (pip install yt-dlp) - skipping")
        return []
    opts = {"quiet": True, "no_warnings": True, "extract_flat": True, "skip_download": True}
    out = []
    try:
        with yt_dlp.YoutubeDL(opts) as y:
            info = y.extract_info(f"ytsearch{n}:{query}", download=False)
        for e in info.get("entries") or []:
            if not e or not e.get("id"):
                continue
            out.append({
                "video_id": e["id"], "title": e.get("title") or "", "channel": e.get("channel") or e.get("uploader") or "",
                "views": int(e.get("view_count") or 0), "duration": int(e.get("duration") or 0),
                "published": "", "query": query,
            })
    except Exception as ex:
        log(f"[trends] yt-dlp '{query}': {ex}")
    return out


def _api(cfg, query, n, days, log):
    try:
        from ytstudio import youtube
        yt = youtube.service(cfg)
        after = (dt.datetime.utcnow() - dt.timedelta(days=days)).strftime("%Y-%m-%dT%H:%M:%SZ")
        r = yt.search().list(part="snippet", q=query, type="video", videoDuration="short", order="viewCount",
                             publishedAfter=after, relevanceLanguage="mr", regionCode="IN", maxResults=min(n, 50)).execute()
        ids = [i["id"]["videoId"] for i in r.get("items", [])]
        stats = {}
        if ids:
            v = yt.videos().list(part="statistics", id=",".join(ids)).execute()
            stats = {i["id"]: int(i["statistics"].get("viewCount", 0)) for i in v.get("items", [])}
        return [{"video_id": i["id"]["videoId"], "title": i["snippet"]["title"], "channel": i["snippet"]["channelTitle"],
                 "views": stats.get(i["id"]["videoId"], 0), "duration": 0, "published": i["snippet"]["publishedAt"], "query": query}
                for i in r.get("items", [])]
    except Exception as ex:
        log(f"[trends] YouTube API '{query}': {ex}")
        return []


def fetch(cfg, db, log=print):
    t = cfg.get("trends", {})
    per_q, days = int(t.get("per_query", 15)), int(t.get("days", 30))
    rows = []
    for q in t.get("queries") or QUERIES:
        got = _yt_dlp(q, per_q, log)
        if not got and t.get("use_api", True):
            got = _api(cfg, q, per_q, days, log)
        rows += got
    now = dt.datetime.now().isoformat(timespec="seconds")
    seen = set()
    for r in rows:
        if r["video_id"] in seen or (r["duration"] and r["duration"] > 90):
            continue
        seen.add(r["video_id"])
        db.execute("INSERT OR REPLACE INTO trends(video_id,title,channel,views,duration,published,query,fetched_at) VALUES(?,?,?,?,?,?,?,?)",
                   (r["video_id"], r["title"], r["channel"], r["views"], r["duration"], r["published"], r["query"], now))
    db.commit()
    log(f"[trends] {len(seen)} trending videos stored")
    return len(seen)


def top(db, n=40):
    return [dict(r) for r in db.execute("SELECT * FROM trends ORDER BY views DESC LIMIT ?", (n,))]


def keywords(rows, n=30):
    """Most frequent Devanagari/latin words in trending titles (theme hints for the LLM)."""
    freq = {}
    for r in rows:
        for w in re.findall(r"[\u0900-\u097F]+|[A-Za-z]{3,}", r["title"]):
            lw = w.lower()
            if lw in _TOPIC_STOP or len(lw) < 3:
                continue
            freq[lw] = freq.get(lw, 0) + 1
    return [w for w, _ in sorted(freq.items(), key=lambda x: -x[1])[:n]]
