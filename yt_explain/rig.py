"""Puppet rig for the supplied female teacher sheet (teacher_f): body from the pointing pose, a separate forearm layer
that swings about the elbow, and a swappable head (mouth shapes from the viseme row, blink from the eyes row) that nods
and tilts.  Everything is drawn from the user's own sheet; nothing is redrawn."""
import math
import os

from PIL import Image, ImageDraw

K = 3                                   # working upscale of the 1x sheet frames
RIG = {
    "teacher_f": dict(
        body="045.png", ghost_x=14, head_cut=(0, 0, 70, 64), bindi=(59, 23),
        arm_poly=[(70, 46), (97, 46), (97, 86), (80, 86), (70, 74)], elbow=(76, 80),
        heads={"closed": "016.png", "small": "006.png", "mid": "005.png", "open": "000.png", "blink": "013.png"},
        head_scale=0.62, neck=(50, 66),
    ),
}


def _bindi(im):
    """Centre of the red bindi dot (the most reliable landmark shared by every head frame)."""
    px = im.convert("RGBA").load()
    xs, ys = [], []
    for y in range(im.height // 2):
        for x in range(im.width):
            r, g, b, a = px[x, y]
            if a > 200 and r > 170 and g < 90 and b < 90:
                xs.append(x)
                ys.append(y)
    if not xs:
        return None
    return sum(xs) / len(xs), sum(ys) / len(ys)


_RC = {}


def _rot(im, deg, pivot):
    deg = round(deg * 2) / 2
    key = (id(im), deg, pivot)
    if key not in _RC:
        if len(_RC) > 600:
            _RC.clear()
        _RC[key] = _rot_raw(im, deg, pivot)
    return _RC[key]


def _rot_raw(im, deg, pivot):
    """Rotate about `pivot` (in image coords) keeping the canvas; returns (image, offset) so pivot stays put."""
    big = Image.new("RGBA", (im.width * 2, im.height * 2), (0, 0, 0, 0))
    ox, oy = im.width // 2, im.height // 2
    big.alpha_composite(im, (ox, oy))
    r = big.rotate(deg, resample=Image.BICUBIC, center=(ox + pivot[0], oy + pivot[1]))
    return r, (-ox, -oy)


class Rig:
    POSES = {"point": 0.0, "wave": -18.0, "cheer": -8.0, "think": -40.0, "talk": -30.0, "sway": -46.0, "read": -46.0}

    def __init__(self, name, h, flip=True):
        spec = RIG[name]
        d = os.path.join(os.path.dirname(__file__), "assets", "avatar", name)
        src = Image.open(os.path.join(d, spec["body"])).convert("RGBA")
        self.flip, self.h = flip, h
        w0, h0 = src.size
        body = src.resize((w0 * K, h0 * K), Image.LANCZOS)
        # forearm layer (cut), then erase it + the neighbour's stray pointer + the small head from the body
        mask = Image.new("L", body.size, 0)
        ImageDraw.Draw(mask).polygon([(x * K, y * K) for x, y in spec["arm_poly"]], fill=255)
        arm = Image.new("RGBA", body.size, (0, 0, 0, 0))
        arm.paste(body, (0, 0), mask)
        self.arm = arm
        self.elbow = (spec["elbow"][0] * K, spec["elbow"][1] * K)
        er = ImageDraw.Draw(body)
        er.polygon([(x * K, y * K) for x, y in spec["arm_poly"]], fill=(0, 0, 0, 0))
        er.rectangle([0, 0, spec["ghost_x"] * K, 110 * K], fill=(0, 0, 0, 0))
        x0, y0, x1, y1 = spec["head_cut"]
        er.rectangle([x0 * K, y0 * K, x1 * K, y1 * K], fill=(0, 0, 0, 0))
        self.body = body
        self.neck = (spec["neck"][0] * K, spec["neck"][1] * K)
        self.heads = {}
        bx, by = spec["bindi"][0] * K, spec["bindi"][1] * K
        for key, f in spec["heads"].items():
            im = Image.open(os.path.join(d, f)).convert("RGBA")
            s = spec["head_scale"] * K
            im = im.resize((int(im.width * s), int(im.height * s)), Image.LANCZOS)
            b = _bindi(im) or (im.width / 2, im.height / 3)
            # soften the cut bottom edge (neck) so it melts into the body
            a = im.getchannel("A")
            fade = Image.new("L", im.size, 255)
            fd = ImageDraw.Draw(fade)
            for i in range(12):
                fd.line([0, im.height - 1 - i, im.width, im.height - 1 - i], fill=int(255 * i / 12))
            im.putalpha(Image.composite(a, Image.new("L", im.size, 0), fade))
            self.heads[key] = (im, (int(bx - b[0]), int(by - b[1])))
        self.size = body.size

    def _head(self, mouth, blink):
        if blink:
            return self.heads["blink"]
        if mouth < 0.06:
            return self.heads["closed"]
        if mouth < 0.14:
            return self.heads["small"]
        if mouth < 0.26:
            return self.heads["mid"]
        return self.heads["open"]

    def frame(self, pose, t, mouth=0.0, blink=False, secs=None):
        """One rendered figure (RGBA, height self.h) at clip time t."""
        W, H = self.size
        can = Image.new("RGBA", (W + 40 * K, H + 24 * K), (0, 0, 0, 0))
        ox, oy = 20 * K, 20 * K
        breathe = 1.0 + 0.006 * math.sin(t * 2 * math.pi / 3.4)
        body = self.body.resize((W, int(round(H * breathe / 2) * 2)), Image.BILINEAR) if abs(breathe - 1) > 0.001 else self.body
        can.alpha_composite(body, (ox, oy + H - body.height))
        # forearm: eases from rest to the pose angle over the first 0.6 s and back during the last 0.5 s, with a
        # gentle beat while talking
        target = self.POSES.get(pose, -30.0)
        k = min(1.0, t / 0.6)
        if secs is not None:
            k = min(k, max(0.0, (secs - t) / 0.5))
        k = 0.5 - 0.5 * math.cos(math.pi * k)
        ang = -46.0 + (target + 46.0) * k + (3.0 * math.sin(t * 2 * math.pi * 1.3) * mouth * 4 if mouth > 0.05 else 0.0)
        arm, off = _rot(self.arm, -ang, self.elbow)
        can.alpha_composite(arm, (ox + off[0], oy + off[1] + H - body.height))
        head, pos = self._head(mouth, blink)
        nod = 2.5 * math.sin(t * 2 * math.pi * 0.45) + (1.5 * math.sin(t * 2 * math.pi * 2.1) if mouth > 0.08 else 0.0)
        hbig = Image.new("RGBA", (W, H), (0, 0, 0, 0))
        hbig.alpha_composite(head, pos)
        hrot, hoff = _rot(hbig, nod, self.neck)
        bob = int(1.5 * K * math.sin(t * 2 * math.pi / 3.4))
        can.alpha_composite(hrot, (ox + hoff[0], oy + hoff[1] + bob + H - body.height))
        hh = int(self.h * can.height / H)           # body itself is self.h tall; the canvas adds head-room
        out = can.resize((int(can.width * hh / can.height), hh), Image.LANCZOS)
        if self.flip:
            out = out.transpose(Image.FLIP_LEFT_RIGHT)
        return out
