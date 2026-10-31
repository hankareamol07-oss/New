"""Descript API (https://docs.descriptapi.com): direct upload -> Underlord agent edit -> publish -> download MP4."""
import os
import time

import requests

BASE = "https://descriptapi.com/v1"

DEFAULT_PROMPT = (
    "This is an educational slideshow video for school students (Maharashtra Board, std {std}, {subject}, {lang}). "
    "Do the following and nothing else: 1) apply Studio Sound to the narration, 2) remove awkward silences longer "
    "than 2.5 seconds but keep about 1.2 s pause between question and answer, 3) add a subtle zoom-in on each slide, "
    "4) {captions} 5) do not change or reorder any slide text. Keep the composition name unchanged."
)
CAPTIONS_ON = ("add captions in the spoken language: dark text on a semi-transparent white box, positioned in the lower third "
               "ABOVE the yellow footer bar (never over it),")
CAPTIONS_OFF = "do not add captions,"


DESCRIPT_LANG = {"mr": "hi", "hi": "hi", "en": "en"}  # Descript has no Marathi ASR; Hindi is the closest Devanagari model


class Descript:
    def __init__(self, token, log=print):
        self.h = {"Authorization": f"Bearer {token}", "Content-Type": "application/json"}
        self.log = log

    def _req(self, method, path, **kw):
        r = requests.request(method, BASE + path, headers=self.h, timeout=120, **kw)
        if r.status_code >= 400:
            raise RuntimeError(f"Descript {method} {path} -> {r.status_code}: {r.text[:300]}")
        return r.json() if r.text else {}

    def wait(self, job_id, every=10, timeout=3600):
        t0 = time.time()
        while time.time() - t0 < timeout:
            j = self._req("GET", f"/jobs/{job_id}")
            if j.get("job_state") == "stopped":
                res = j.get("result", {})
                if res.get("status") not in (None, "success"):
                    raise RuntimeError(f"Descript job failed: {res}")
                return j
            time.sleep(every)
        raise TimeoutError(job_id)

    def upload_video(self, mp4, project_name, lang):
        size = os.path.getsize(mp4)
        body = {
            "project_name": project_name,
            "add_media": {"video.mp4": {"content_type": "video/mp4", "file_size": size, "language": DESCRIPT_LANG.get(lang, "en")}},
            "add_compositions": [{"name": "Main", "clips": [{"media": "video.mp4"}]}],
        }
        j = self._req("POST", "/jobs/import/project_media", json=body)
        up = (j.get("upload_urls") or {}).get("video.mp4", {}).get("upload_url")
        if not up:
            raise RuntimeError(f"no upload_url in import response: {j}")
        self.log("[descript] uploading ...")
        with open(mp4, "rb") as f:
            r = requests.put(up, data=f, headers={"Content-Type": "application/octet-stream"}, timeout=3600)
        r.raise_for_status()
        done = self.wait(j["job_id"])
        comps = done.get("result", {}).get("created_compositions", [])
        return j["project_id"], (comps[0]["id"] if comps else None), j.get("project_url")

    def agent(self, project_id, composition_id, prompt, model="auto"):
        body = {"project_id": project_id, "prompt": prompt, "model": model}
        if composition_id:
            body["composition_id"] = composition_id
        j = self._req("POST", "/jobs/agent", json=body)
        self.log("[descript] Underlord editing ...")
        done = self.wait(j["job_id"], every=15)
        return done.get("result", {}).get("agent_response", "")

    def publish(self, project_id, composition_id, out_path, resolution="1080p"):
        body = {"project_id": project_id, "media_type": "Video", "resolution": resolution, "access_level": "unlisted"}
        if composition_id:
            body["composition_id"] = composition_id
        j = self._req("POST", "/jobs/publish", json=body)
        self.log("[descript] rendering/publishing ...")
        done = self.wait(j["job_id"], every=15)
        res = done.get("result", {})
        url = res.get("download_url")
        if not url:
            raise RuntimeError(f"publish returned no download_url: {res}")
        with requests.get(url, stream=True, timeout=3600) as r:
            r.raise_for_status()
            with open(out_path, "wb") as f:
                for chunk in r.iter_content(1 << 20):
                    f.write(chunk)
        return res.get("share_url"), out_path


def polish(cfg, mp4, book, chapter, out_dir, log=print):
    d = Descript(cfg["keys"]["descript"], log)
    name = f"Std {book['std']} {book['subject']} - {chapter['title']}"[:80]
    pid, cid, purl = d.upload_video(mp4, name, book["lang"])
    log(f"[descript] project {purl}")
    captions = cfg["descript"].get("captions", "auto")
    want_caps = captions is True or (captions == "auto" and book["lang"] != "mr")
    prompt = cfg["descript"].get("prompt") or DEFAULT_PROMPT.format(
        std=book["std"], subject=book["subject"], lang=book["lang"], captions=CAPTIONS_ON if want_caps else CAPTIONS_OFF)
    summary = d.agent(pid, cid, prompt, cfg["descript"].get("agent_model", "auto"))
    log(f"[descript] agent: {str(summary)[:200]}")
    share, path = d.publish(pid, cid, os.path.join(out_dir, "video_descript.mp4"), cfg["descript"].get("resolution", "1080p"))
    return {"project_url": purl, "share_url": share, "file": path, "agent_response": summary}
