"""Build a professional illustrated teacher avatar sprite (one-time step; the result is shipped in assets/avatar/).

    python avatar_make.py female            # generate with NVIDIA FLUX (nvidia_api_key from config.json)
    python avatar_make.py male
    python avatar_make.py myteacher --image my_teacher.png   # use your own picture (plain white background)

Steps: image (FLUX.1-dev via NVIDIA NIM, free credits) -> white background removed -> face landmarks
(MediaPipe face_landmarker, CPU) -> assets/avatar/<name>.png + <name>.json (mouth/eye boxes, skin & lip colour).
avatar.py only needs the png+json at render time (no MediaPipe / API on the rendering PC).
"""
import argparse
import base64
import json
import os
import sys

import numpy as np
import requests
from PIL import Image

HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, os.path.join(HERE, "yt_studio"))
sys.path.insert(0, os.path.join(HERE, "..", "yt_studio"))
ASSETS = os.path.join(HERE, "assets", "avatar")
MODEL = "face_landmarker.task"
MODEL_URL = "https://storage.googleapis.com/mediapipe-models/face_landmarker/face_landmarker/float16/1/face_landmarker.task"

STYLE = ("professional flat vector illustration, clean modern corporate style, crisp sharp lines, soft cel shading, "
         "plain solid white background, no text, no shadow, waist-up portrait facing camera, "
         "gentle closed-mouth smile, right hand raised palm up as if explaining, ")
PROMPTS = {
    "female": STYLE + "friendly Indian woman school teacher age 30, elegant teal saree with yellow blouse, neat hair bun, "
                      "small red bindi, gold earrings",
    "male": STYLE + "friendly Indian man school teacher age 35, light blue formal shirt, navy waistcoat and tie, "
                    "black glasses, neat short hair",
}


def generate(cfg, prompt, seed=21):
    k = cfg["nvidia_api_key"]
    k = k[0] if isinstance(k, list) else k
    r = requests.post("https://ai.api.nvidia.com/v1/genai/black-forest-labs/flux.1-dev",
                      headers={"Authorization": f"Bearer {k}", "Accept": "application/json"},
                      json={"prompt": prompt, "mode": "base", "cfg_scale": 3.5, "width": 1024, "height": 1024,
                            "seed": seed, "steps": 50}, timeout=300)
    r.raise_for_status()
    return base64.b64decode(r.json()["artifacts"][0]["base64"])


def cutout(im):
    """Make the white background transparent (only white connected to the image border)."""
    from scipy import ndimage
    a = np.asarray(im.convert("RGB")).astype(int)
    white = (a.min(axis=2) >= 222) & ((a.max(axis=2) - a.min(axis=2)) <= 24)
    lab, n = ndimage.label(white)
    border = set(np.unique(np.concatenate([lab[0], lab[-1], lab[:, 0], lab[:, -1]]))) - {0}
    bg = np.isin(lab, list(border))
    alpha = np.where(bg, 0, 255).astype(np.uint8)
    # soften the edge one pixel so the cut is not jagged
    soft = ndimage.uniform_filter(alpha.astype(float), 3)
    alpha = np.where(bg, np.minimum(alpha, soft), alpha).astype(np.uint8)
    out = np.dstack([a.astype(np.uint8), alpha])
    return Image.fromarray(out, "RGBA")


def landmarks(im):
    import mediapipe as mp
    from mediapipe.tasks import python as mpp
    from mediapipe.tasks.python import vision
    path = os.path.join(ASSETS, MODEL)
    if not os.path.exists(path):
        os.makedirs(ASSETS, exist_ok=True)
        open(path, "wb").write(requests.get(MODEL_URL, timeout=120).content)
    opt = vision.FaceLandmarkerOptions(base_options=mpp.BaseOptions(model_asset_path=path), num_faces=1,
                                       running_mode=vision.RunningMode.IMAGE, min_face_detection_confidence=0.2)
    det = vision.FaceLandmarker.create_from_options(opt)
    rgb = np.asarray(im.convert("RGB"))
    r = det.detect(mp.Image(image_format=mp.ImageFormat.SRGB, data=rgb))
    if not r.face_landmarks:
        raise SystemExit("no face found in the image")
    lm = r.face_landmarks[0]
    W, H = im.size
    P = lambda i: (lm[i].x * W, lm[i].y * H)
    return P


def measure(im):
    P = landmarks(im)
    px = im.convert("RGB").load()
    col = lambda p: px[int(p[0]), int(p[1])]
    l, r, top, bot = P(61), P(291), P(0), P(17)
    chin = P(152)
    mw, my = r[0] - l[0], (l[1] + r[1]) / 2
    samples = [col((l[0] - mw * 0.25, my)), col((r[0] + mw * 0.25, my)), col(((bot[0] + chin[0]) / 2, (bot[1] + chin[1]) / 2))]
    skin = tuple(sum(c[i] for c in samples) // 3 for i in range(3))
    lip = col(P(0))
    lower = col(P(17))
    if sum(lower) < sum(lip):
        lip = lower
    eyes = []
    for a, b, up, dn in ((33, 133, 159, 145), (362, 263, 386, 374)):
        xs = [P(a)[0], P(b)[0]]
        ys = [P(up)[1], P(dn)[1]]
        eyes.append([min(xs), min(ys), max(xs), max(ys)])
    return {"mouth": [l[0], top[1], r[0], bot[1]], "mouth_mid": [(l[1] + r[1]) / 2],
            "eyes": eyes, "skin": list(skin), "lip": list(lip), "chin": list(chin),
            "face_top": list(P(10)), "nose": list(P(1))}


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("name")
    ap.add_argument("--image", help="use this picture instead of generating one")
    ap.add_argument("--prompt", help="custom FLUX prompt (appended to the house style)")
    ap.add_argument("--seed", type=int, default=21)
    a = ap.parse_args()
    os.makedirs(ASSETS, exist_ok=True)
    if a.image:
        im = Image.open(a.image)
    else:
        from ytstudio.config import load
        cfg = load(os.path.join(HERE, "config.json"))
        prompt = PROMPTS.get(a.name) or (STYLE + (a.prompt or f"friendly Indian school teacher, {a.name}"))
        raw = os.path.join(ASSETS, f"{a.name}_raw.png")
        open(raw, "wb").write(generate(cfg, prompt, a.seed))
        im = Image.open(raw)
        print("generated", raw)
    im = cutout(im)
    meta = measure(im)
    meta["size"] = list(im.size)
    im.save(os.path.join(ASSETS, f"{a.name}.png"))
    json.dump(meta, open(os.path.join(ASSETS, f"{a.name}.json"), "w"), indent=1)
    print("saved", os.path.join(ASSETS, a.name + ".png"), json.dumps(meta))


if __name__ == "__main__":
    main()
