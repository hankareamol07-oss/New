"""Generic vector puppet: any layered Animate character + a rig manifest (see autorig.guess) -> per-frame images.
Mouth from audio loudness, blinks, breathing, head nod, two-joint arm gestures, leg swing walk cycle."""
import json
import math
import os

import skia
from PIL import Image

from . import xfl

GESTURES = {            # (upper arm deg, forearm deg) at full strength; positive = raise toward the facing side
    "idle": (0, 0), "talk": (8, 38), "point": (62, 28), "wave": (40, 120), "think": (14, 135),
    "explain": (18, 70), "greet": (30, 95),
}


def _ease(k):
    k = max(0.0, min(1.0, k))
    return 0.5 - 0.5 * math.cos(math.pi * k)


class Rig:
    def __init__(self, char_dir, manifest=None):
        self.dir = char_dir
        m = manifest or json.load(open(os.path.join(char_dir, "manifest.json"), encoding="utf8"))
        self.m = m
        self.rig = m.get("rig") or m
        self.doc = xfl.Doc(char_dir)
        self.root = self.rig["root"]
        self.doc.layer_scope = self.doc.timeline(self.doc.symbol(self.root))
        self.f0 = int(self.rig.get("frame", 0))
        x0, y0, x1, y1 = self.rig["bounds"]
        h = y1 - y0
        self.bounds = (x0 - h * 0.25, y0 - h * 0.04, x1 + h * 0.25, y1 + h * 0.01)   # room for raised arms
        self.height = h
        self.feet_y = y1
        self.cx = (x0 + x1) / 2
        self.sgn = 1.0 if self.rig.get("faces", "left") == "left" else -1.0
        legs = self.rig.get("legs") or []
        hips = self.rig.get("hips") or {}
        self.leg_of = {}                                    # foot layer -> leg layer it hangs from
        for f in self.rig.get("feet") or []:
            if legs:
                self.leg_of[f] = min(legs, key=lambda L: abs(hips.get(L, [0, 0])[0] - self._layer_cx(f)))

    def _layer_cx(self, layer):
        try:
            _, b = xfl.render(self.doc, self.root, self.f0, scale=0.25, only_layers={layer})
            return (b[0] + b[2]) / 2 if b else 0.0
        except Exception:
            return 0.0

    # ------------------------------------------------------------------
    def _arm(self, spec, up, fore, xf, B):
        if not spec or not spec.get("upper"):
            return
        sh = spec["shoulder"]
        R_up = skia.Matrix.RotateDeg(up * self.sgn, skia.Point(*sh))
        R_up.postConcat(B)
        xf[spec["upper"]] = R_up
        if spec.get("fore"):
            el = spec.get("elbow") or sh
            R_f = skia.Matrix.RotateDeg((up + fore) * self.sgn, skia.Point(*sh))
            p = skia.Matrix.RotateDeg(up * self.sgn, skia.Point(*sh)).mapXY(*el)
            # forearm: rotate about the elbow after the upper arm moved it -> joint stays connected
            R_f = skia.Matrix.RotateDeg(fore * self.sgn, skia.Point(p.x(), p.y()))
            R_f.preConcat(skia.Matrix.RotateDeg(up * self.sgn, skia.Point(*sh)))
            R_f.postConcat(B)
            xf[spec["fore"]] = R_f

    def frame(self, t, mouth=0.0, blink=False, gesture="idle", gk=0.0, walk=None, scale=1.0, flip=False, look=0.0):
        """t secs; mouth 0..1; gesture strength gk 0..1; walk = cycle phase in radians or None.
        Returns (PIL RGBA, (anchor_x, anchor_y)) where anchor = feet centre in image px."""
        d, r = self.doc, self.rig
        xf = {}
        breathe = 1.0 + 0.005 * math.sin(t * 2 * math.pi / 3.4)
        bob = 0.0
        if walk is not None:
            bob = -abs(math.sin(walk)) * self.height * 0.012
        B = skia.Matrix.Scale(1.0, breathe)
        B.postTranslate(0, self.feet_y * (1 - breathe))
        # head: slow calm nod + small speech accents
        talk = min(1.0, mouth * 3) if mouth > 0.05 else 0.0
        nod = 0.7 * math.sin(t * 2 * math.pi / 3.7) + 1.0 * math.sin(t * 2 * math.pi * 1.7) * talk + look
        if r.get("head") and r.get("neck"):
            H = skia.Matrix.RotateDeg(nod * self.sgn, skia.Point(*r["neck"]))
            H.postConcat(B)
            xf[r["head"]] = H
        # arms
        gu, gf = GESTURES.get(gesture, (0, 0))
        k = _ease(gk)
        beat = 2.5 * math.sin(t * 2 * math.pi * 1.3) * talk if k > 0.4 else 0.0
        swing = 0.0
        if walk is not None:
            swing = 9.0 * math.sin(walk)
        self._arm(r.get("arm_front"), gu * k + swing, gf * k + beat + (6 if walk is not None else 0), xf, B)
        self._arm(r.get("arm_back"), -swing * 0.8, 4 if walk is not None else 0, xf, B)
        # legs / feet
        hips = r.get("hips") or {}
        legs = r.get("legs") or []
        if walk is not None and legs:
            for i, L in enumerate(legs):
                a = float(r.get("leg_swing", 11.0)) * math.sin(walk + (math.pi if i % 2 else 0.0))
                hp = hips.get(L, [self.cx, self.feet_y * 0.5])
                xf[L] = skia.Matrix.RotateDeg(a, skia.Point(*hp))
            for f, L in self.leg_of.items():
                xf[f] = xf[L]
        for ln in r.get("breath", []):
            xf.setdefault(ln, B)
        d.layer_xform = xf
        # torso & the rest breathe through layer_xform of every other layer
        for L in d.layers(d.layer_scope):
            n = L.get("name")
            if n not in xf and n not in legs and n not in self.leg_of and n not in (r.get("shadow") or []):
                xf[n] = B
        # face
        fo = {}
        for sym, mf in (r.get("mouth") or {}).items():
            fo[sym] = mf["open"] if mouth > 0.42 else (mf["mid"] if mouth > 0.14 else mf["closed"])
        for sym, ef in (r.get("eyes") or {}).items():
            bl = ef.get("blink") or []
            fo[sym] = bl[len(bl) // 2] if (blink and bl) else ef.get("open", 0)
        d.frame_override = fo
        if bob:
            for n in list(xf):
                if n not in (r.get("shadow") or []):
                    xf[n] = skia.Matrix.Concat(skia.Matrix.Translate(0, bob), xf[n])
        img, _ = xfl.render(d, self.root, self.f0, scale=scale, bounds=self.bounds)
        pil = xfl.to_pil(img)
        ax = (self.cx - self.bounds[0]) * scale
        ay = (self.feet_y - self.bounds[1]) * scale
        if flip:
            pil = pil.transpose(Image.FLIP_LEFT_RIGHT)
            ax = pil.width - ax
        return pil, (ax, ay)
