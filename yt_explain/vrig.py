"""Vector puppet rig: the teacher is a real layered Animate character (from the user's .fla), rendered with skia
per video frame — idle body animation from the file, mouth from the audio envelope, blinks, head nod, arm pointing."""
import math
import os

import skia
from PIL import Image

import xfl

ASSETS = os.path.join(os.path.dirname(__file__), "assets", "avatar")
NS = xfl.NS

RIGS = {
    "teacher_v": dict(
        root="Duplicate Items Folder/Symbol 1 copy 17",
        head="Layer_3",
        upper_arm="Layer_9", forearm="Layer_8",          # arm on the slide side (image left)
        far_hand="Layer_2",
        lips="Duplicate Items Folder/father lips copy",   # frames 0-15 closed variants, 16-19 open
        eyes="Duplicate Items Folder/girl eye copy",      # frames 10-13 blink
        lips_closed=0, lips_open=16,
        blink_frames=(10, 11, 12, 13),
        idle_len=50,
        breath=("Layer_2", "Layer_4", "Layer_5", "Layer_7", "Layer_12", "Layer_13"),   # far hand, pallu, far arm, torso, back pallu, hair
    ),
}


def _pivot(doc, root, layer, frame=0):
    """Pivot (transformation point) of the instance on a layer at `frame` (tween-interpolated), in root space."""
    tl = doc.timeline(doc.symbol(root))
    for L in doc.layers(tl):
        if L.get("name") == layer:
            df, nxt = doc.frame_at(L, frame)
            if df is None:
                return 0.0, 0.0
            inst = df.find(".//" + NS + "DOMSymbolInstance")
            nxt_el = nxt.find(".//" + NS + "DOMSymbolInstance") if (nxt is not None and df.get("tweenType") == "motion") else None
            idx, dur = int(df.get("index", 0)), int(df.get("duration", 1) or 1)
            t = (frame - idx) / dur if dur > 1 else 0.0
            m = doc._interp_matrix(inst, nxt_el, t) if nxt_el is not None else xfl._mat(inst.find(NS + "matrix"))
            tp = inst.find(NS + "transformationPoint/" + NS + "Point")
            x = float(tp.get("x", 0) or 0) if tp is not None else 0.0
            y = float(tp.get("y", 0) or 0) if tp is not None else 0.0
            p = m.mapXY(x, y)
            return p.x(), p.y()
    return 0.0, 0.0


class VRig:
    POSES = {"point": 1.0, "wave": 0.75, "cheer": 0.9, "think": 0.35, "talk": 0.5, "sway": 0.0, "read": 0.0}

    def __init__(self, name, h, flip=None):
        spec = RIGS[name]
        self.spec = spec
        self.h = h
        self.doc = xfl.Doc(os.path.join(ASSETS, name))
        self.root = spec["root"]
        import json
        meta = json.load(open(os.path.join(ASSETS, name, "manifest.json"), encoding="utf8"))
        self.flip = bool(meta.get("flip", False) if flip is None else flip)
        self.shoulder = _pivot(self.doc, self.root, spec["upper_arm"])
        self.elbow = _pivot(self.doc, self.root, spec["forearm"])
        self.neck = _pivot(self.doc, self.root, spec["head"])
        img, b = xfl.render(self.doc, self.root, 0, scale=1.0, pad=10)
        x0, y0, x1, y1 = b
        self.bounds = (x0 - 70, y0 - 30, x1 + 30, y1 + 6)   # room for the raised arm
        self.scale = h / (self.bounds[3] - self.bounds[1])

    # ---------------------------------------------------------------
    GESTURE_HOLD = 2.6      # seconds the hand stays up at the start of a slide
    GESTURE_EVERY = 14.0    # a short gesture again every N seconds on long slides

    def _arm_angles(self, pose, t, mouth, secs):
        target = self.POSES.get(pose, 0.5)
        if target <= 0.0:
            return 0.0, 0.0
        # hand comes up (0.7 s), holds, then rests; repeats briefly on long slides. Rest of the time: arm down.
        cyc = t if t < self.GESTURE_HOLD + 1.5 else ((t - self.GESTURE_HOLD - 1.5) % self.GESTURE_EVERY) - (self.GESTURE_EVERY - self.GESTURE_HOLD - 1.5)
        if cyc < 0:
            return 0.0, 0.0
        k = min(1.0, cyc / 0.7, max(0.0, (self.GESTURE_HOLD + 1.2 - cyc) / 0.8))
        if secs is not None:
            k = min(k, max(0.0, (secs - t) / 0.6))
        k = max(0.0, 0.5 - 0.5 * math.cos(math.pi * k)) * target
        beat = (1.5 * math.sin(t * 2 * math.pi * 1.4) * min(1.0, mouth * 3)) if (mouth > 0.04 and k > 0.5) else 0.0
        up = 22.0 * k + beat * 0.3          # upper arm swings forward a little (shoulder cap stays on the blouse)
        fore = 96.0 * k + beat              # forearm bends up so the hand points at the slide
        return up, fore

    def frame(self, pose, t, mouth=0.0, blink=False, secs=None):
        d = self.doc
        spec = self.spec
        fi = 0                                        # calm: no walking/swaying idle loop from the file
        up, fore = self._arm_angles(pose, t, mouth, secs)
        nod = 0.6 * math.sin(t * 2 * math.pi / 3.1) + (0.8 * math.sin(t * 2 * math.pi * 2.1) * min(1.0, mouth * 3) if mouth > 0.04 else 0.0)
        tilt = 0.0
        shoulder = _pivot(d, self.root, spec["upper_arm"], fi)
        elbow = _pivot(d, self.root, spec["forearm"], fi)
        neck = _pivot(d, self.root, spec["head"], fi)
        R_up = skia.Matrix.RotateDeg(up, skia.Point(*shoulder))
        p = R_up.mapXY(*elbow)
        R_fore = skia.Matrix.RotateDeg(fore, skia.Point(p.x(), p.y()))
        R_fore.preConcat(R_up)
        d.layer_xform = {
            spec["upper_arm"]: R_up,
            spec["forearm"]: R_fore,
            spec["head"]: skia.Matrix.RotateDeg(nod + tilt, skia.Point(*neck)),
        }
        d.layer_frame = {}
        breathe = 1.0 + 0.004 * math.sin(t * 2 * math.pi / 3.6)   # gentle breathing of the whole figure about the feet
        B = skia.Matrix.Scale(1.0, breathe)
        B.postTranslate(0, self.bounds[3] * (1 - breathe))
        for key in ("head", "upper_arm", "forearm"):
            m = d.layer_xform[spec[key]]
            m.postConcat(B)
        for ln in spec.get("breath", ()):
            d.layer_xform[ln] = B
        if blink:
            d.frame_override = {spec["eyes"]: spec["blink_frames"][1]}
        else:
            d.frame_override = {spec["eyes"]: 0}
        d.frame_override[spec["lips"]] = spec["lips_open"] if mouth > 0.12 else spec["lips_closed"]
        self._mouth_scale = min(1.0, max(0.35, mouth * 2.2)) if mouth > 0.12 else 1.0
        img, _ = xfl.render(d, self.root, fi, scale=self.scale, bounds=self.bounds)
        pil = xfl.to_pil(img)
        if self.flip:
            pil = pil.transpose(Image.FLIP_LEFT_RIGHT)
        return pil
