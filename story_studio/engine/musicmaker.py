"""Built-in royalty-free background music, synthesised (soft pad + plucked arpeggio loops)."""
import math
import os
import wave

import numpy as np

SR = 44100
NOTE = {"C": 0, "D": 2, "E": 4, "F": 5, "G": 7, "A": 9, "B": 11}
PRESETS = {   # bpm, chords (root note, minor?), arpeggio pattern (scale steps of the chord), label
    "calm": (72, [("C", 0), ("A", 1), ("F", 0), ("G", 0)], [0, 1, 2, 1], "शांत (calm)"),
    "happy": (108, [("G", 0), ("D", 0), ("E", 1), ("C", 0)], [0, 2, 1, 2, 0, 1, 2, 3], "आनंदी (happy)"),
    "story": (86, [("A", 1), ("F", 0), ("C", 0), ("G", 0)], [0, 1, 2, 3, 2, 1], "गोष्ट (story)"),
    "school": (96, [("F", 0), ("C", 0), ("D", 1), ("A#", 0)], [0, 1, 2, 1, 3, 1], "शाळा (school)"),
}


def _f(name, octave):
    n = NOTE[name[0]] + (1 if "#" in name else 0)
    return 440.0 * 2 ** ((n - 9) / 12 + (octave - 4))


def make(preset, bars=8):
    bpm, chords, arp, _ = PRESETS[preset]
    beat = 60.0 / bpm
    bar = beat * 4
    total = bar * bars
    n = int(total * SR)
    out = np.zeros(n, dtype=np.float64)
    t_all = np.arange(n) / SR
    for b in range(bars):
        root, minor = chords[b % len(chords)]
        f0 = _f(root, 3)
        tri = [f0, f0 * 2 ** ((3 if minor else 4) / 12), f0 * 2 ** (7 / 12), f0 * 2]
        s, e = int(b * bar * SR), int((b + 1) * bar * SR)
        t = t_all[s:e] - b * bar
        env = np.minimum(1, t / 0.6) * np.minimum(1, (bar - t) / 0.5)
        for f in tri[:3]:
            out[s:e] += 0.05 * env * (np.sin(2 * math.pi * f * t) + 0.3 * np.sin(4 * math.pi * f * t))
        step = bar / len(arp) / (2 if len(arp) <= 4 else 1)
        k = 0
        while k * step < bar - 1e-6:
            f = tri[arp[k % len(arp)]] * 2
            i0 = s + int(k * step * SR)
            ln = min(int(1.2 * SR), n - i0)
            tt = np.arange(ln) / SR
            out[i0:i0 + ln] += 0.09 * np.exp(-tt * 3.2) * np.sin(2 * math.pi * f * tt) * (1 + 0.4 * np.sin(2 * math.pi * 2 * f * tt))
            k += 1
    # simple room: two feedback echoes
    for d, g in ((0.23, 0.25), (0.41, 0.15)):
        sh = int(d * SR)
        out[sh:] += out[:-sh] * g
    out /= max(1e-6, np.abs(out).max()) / 0.6
    return out.astype(np.float32)


def make_all(out_dir):
    os.makedirs(out_dir, exist_ok=True)
    for p in PRESETS:
        x = (make(p) * 32767).astype(np.int16)
        with wave.open(os.path.join(out_dir, p + ".wav"), "wb") as w:
            w.setnchannels(1)
            w.setsampwidth(2)
            w.setframerate(SR)
            w.writeframes(x.tobytes())
