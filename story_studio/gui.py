#!/usr/bin/env python3
"""Story Studio - make animated story videos (Marathi / Hindi / English) from Adobe Animate characters.

Workflow like Mango Animation Maker: scenes on the left, preview in the middle, cast / scene / lines on the right,
one button to make the video. Tabs: गोष्ट (editor) | AI गोष्ट | पात्रे (library + FLA import) | रिग | पार्श्वभूमी व संगीत |
रांग (queue) | सेटिंग्ज."""
import copy
import glob
import json
import os
import queue
import re
import shutil
import subprocess
import sys
import threading
import time
import tkinter as tk
from tkinter import filedialog, messagebox, ttk

from PIL import Image, ImageTk

from engine import (autorig, bg_import, bgmaker, config, library, library_build, musicmaker, paths, render, rig, story_ai,
                    tts, xfl)

cfg = config.load()
LANGS = [("मराठी", "mr"), ("हिंदी", "hi"), ("English", "en")]
POSITIONS = ["far-left", "left", "center", "right", "far-right"]
SIDES = ["", "left", "right"]
CAMERAS = ["slow-zoom", "pan", "static"]
TRANSITIONS = ["fade", "cut"]
ACTION_LABEL = {"talk": "बोलणे", "explain": "समजावणे", "point": "बोट दाखवणे", "wave": "हात हलवणे", "greet": "नमस्कार",
                "think": "विचार", "idle": "शांत उभे"}
PREVIEW = (640, 360)
UI_FONT = ("Nirmala UI", 10) if sys.platform == "win32" else ("Noto Sans Devanagari", 10)


def open_file(p):
    try:
        if sys.platform == "win32":
            os.startfile(p)
        else:
            subprocess.Popen(["xdg-open", p])
    except OSError:
        pass


def char_info():
    out = []
    for n in library.characters():
        try:
            m = library.load_manifest(n)
        except (OSError, ValueError):
            continue
        out.append((n, m.get("label", n), m.get("gender", "female")))
    return out


def backgrounds():
    names = []
    for f in sorted(os.listdir(paths.BGS)) if os.path.isdir(paths.BGS) else []:
        if f.lower().endswith((".png", ".jpg", ".jpeg", ".webp")):
            names.append(os.path.splitext(f)[0] if os.path.splitext(f)[0] in bgmaker.PRESETS else f)
    return names or list(bgmaker.PRESETS)


def music_list():
    files = [f for f in sorted(os.listdir(paths.MUSIC)) if f.lower().endswith((".mp3", ".wav", ".m4a", ".ogg"))] \
        if os.path.isdir(paths.MUSIC) else []
    return ["none"] + [os.path.splitext(f)[0] if os.path.splitext(f)[0] in musicmaker.PRESETS else f for f in files]


def new_story():
    return {"title": "नवीन गोष्ट", "lang": cfg.get("lang", "mr"), "music": cfg.get("music", "calm"), "cast": {},
            "narrator": {}, "scenes": [{"background": "village", "camera": "slow-zoom", "transition": "fade",
                                        "characters": [], "lines": []}]}


def parse_script(text, story):
    """'नाव: संवाद' per line -> lines of the current scene; unknown names become cast members."""
    cast = story.setdefault("cast", {})
    by_name = {c.get("name", k): k for k, c in cast.items()}
    used = {c.get("character") for c in cast.values()}
    free = [c for c in char_info() if c[0] not in used]
    lines = []
    for raw in text.splitlines():
        raw = raw.strip()
        if not raw:
            continue
        m = re.match(r"^([^:：]{1,30})[:：]\s*(.+)$", raw)
        if not m or m.group(1).strip().lower() in ("narrator", "निवेदक", "कथाकार", "सूत्रधार", "वाचक"):
            lines.append({"speaker": "narrator", "text": m.group(2).strip() if m else raw})
            continue
        nm, tx = m.group(1).strip(), m.group(2).strip()
        if nm not in by_name:
            cid = library.safe_name(nm).lower() or f"c{len(cast) + 1}"
            if cid in cast or not re.match(r"^\w+$", cid, re.ASCII):
                cid = f"c{len(cast) + 1}"
            ch = free.pop(0) if free else (char_info() or [("sita", "", "female")])[0]
            cast[cid] = {"name": nm, "character": ch[0], "gender": ch[2]}
            by_name[nm] = cid
        lines.append({"speaker": by_name[nm], "text": tx, "action": "talk"})
    return lines


class CharPicker(tk.Toplevel):
    """Grid of character thumbnails; double-click / button returns the id."""

    def __init__(self, master, title="पात्र निवडा"):
        super().__init__(master)
        self.title(title)
        self.geometry("980x640")
        self.result = None
        top = ttk.Frame(self, padding=6)
        top.pack(fill="x")
        ttk.Label(top, text="दाखवा:").pack(side="left")
        self.flt = tk.StringVar(value="all")
        for t, v in (("सर्व", "all"), ("स्त्री", "female"), ("पुरुष", "male")):
            ttk.Radiobutton(top, text=t, value=v, variable=self.flt, command=self.fill).pack(side="left", padx=4)
        ttk.Button(top, text="✔ निवडा", command=self.ok).pack(side="right")
        self.cv = tk.Canvas(self, bg="white")
        sb = ttk.Scrollbar(self, orient="vertical", command=self.cv.yview)
        self.cv.configure(yscrollcommand=sb.set)
        sb.pack(side="right", fill="y")
        self.cv.pack(fill="both", expand=True)
        self.cv.bind_all("<MouseWheel>", lambda e: self.cv.yview_scroll(-1 * (e.delta // 120), "units"))
        self.imgs, self.sel = [], tk.StringVar()
        self.fill()
        self.transient(master)
        self.grab_set()

    def fill(self):
        self.cv.delete("all")
        self.imgs.clear()
        x = y = 0
        for cid, lab, g in char_info():
            if self.flt.get() != "all" and g != self.flt.get():
                continue
            im = library.thumb(cid)
            if im is None:
                continue
            im = im.copy()
            im.thumbnail((110, 170))
            ph = ImageTk.PhotoImage(im)
            self.imgs.append(ph)
            f = tk.Frame(self.cv, bg="white", bd=2, relief="groove")
            b = tk.Radiobutton(f, image=ph, variable=self.sel, value=cid, indicatoron=False, bg="white",
                               selectcolor="#ffe08a", width=120, height=175)
            b.pack()
            b.bind("<Double-Button-1>", lambda e, c=cid: (self.sel.set(c), self.ok()))
            tk.Label(f, text=lab, bg="white", font=UI_FONT, wraplength=120).pack()
            self.cv.create_window(x * 135 + 5, y * 225 + 5, anchor="nw", window=f)
            x += 1
            if x == 7:
                x, y = 0, y + 1
        self.cv.configure(scrollregion=(0, 0, 7 * 135, (y + 1) * 225 + 10))

    def ok(self):
        self.result = self.sel.get() or None
        self.destroy()


class App:
    def __init__(self, root):
        self.root = root
        root.title("Story Studio - गोष्ट व्हिडिओ")
        root.geometry("1400x900")
        style = ttk.Style()
        if "vista" in style.theme_names():
            style.theme_use("vista")
        style.configure(".", font=UI_FONT)
        style.configure("Big.TButton", font=(UI_FONT[0], 12, "bold"), padding=6)
        self.story = new_story()
        self.path = None
        self.si = 0
        self.rigs = {}
        self.ui = queue.Queue()
        self.jobs = []
        self.cancel_flag = threading.Event()
        self.worker = None
        self._preview_job = None
        self._build_top()
        nb = ttk.Notebook(root)
        nb.pack(fill="both", expand=True)
        self.nb = nb
        for name, cls in (("गोष्ट", StoryTab), ("AI गोष्ट", AITab), ("पात्रे", CharsTab), ("रिग", RigTab),
                          ("पार्श्वभूमी व संगीत", MediaTab), ("⏳ रांग व लॉग", QueueTab), ("सेटिंग्ज", SettingsTab)):
            tab = cls(nb, self)
            nb.add(tab, text=name)
            setattr(self, cls.__name__.lower(), tab)
        self.load_story_into_ui()
        root.after(100, self._pump)

    # ---- top bar -------------------------------------------------------------------------------
    def _build_top(self):
        f = ttk.Frame(self.root, padding=6)
        f.pack(fill="x")
        ttk.Button(f, text="नवीन", command=self.new).pack(side="left")
        ttk.Button(f, text="उघडा", command=self.open).pack(side="left", padx=2)
        ttk.Button(f, text="जतन", command=self.save).pack(side="left")
        ttk.Label(f, text="  शीर्षक:").pack(side="left")
        self.v_title = tk.StringVar()
        ttk.Entry(f, textvariable=self.v_title, width=28).pack(side="left")
        ttk.Label(f, text="  भाषा:").pack(side="left")
        self.v_lang = tk.StringVar()
        cb = ttk.Combobox(f, textvariable=self.v_lang, values=[a for a, _ in LANGS], width=9, state="readonly")
        cb.pack(side="left")
        cb.bind("<<ComboboxSelected>>", lambda e: self.storytab.refresh_cast())
        ttk.Label(f, text="  संगीत:").pack(side="left")
        self.v_music = tk.StringVar()
        self.cb_music = ttk.Combobox(f, textvariable=self.v_music, values=music_list(), width=14)
        self.cb_music.pack(side="left")
        self.v_shorts = tk.BooleanVar(value=bool(cfg.get("shorts")))
        ttk.Checkbutton(f, text="Shorts (उभा 9:16)", variable=self.v_shorts).pack(side="left", padx=6)
        ttk.Button(f, text="➕ रांगेत", command=lambda: self.enqueue(start=False)).pack(side="right")
        ttk.Button(f, text="▶ व्हिडिओ बनवा", style="Big.TButton", command=lambda: self.enqueue(start=True)).pack(side="right", padx=4)

    def lang(self):
        return dict(LANGS).get(self.v_lang.get(), "mr")

    def sync_top(self):
        self.story["title"] = self.v_title.get().strip()
        self.story["lang"] = self.lang()
        self.story["music"] = self.v_music.get() or "none"

    def load_story_into_ui(self):
        self.v_title.set(self.story.get("title", ""))
        self.v_lang.set({v: k for k, v in LANGS}.get(self.story.get("lang", "mr"), "मराठी"))
        self.v_music.set(self.story.get("music") or "none")
        self.si = 0
        self.storytab.reload()

    def new(self):
        self.story, self.path = new_story(), None
        self.load_story_into_ui()

    def open(self):
        p = filedialog.askopenfilename(initialdir=paths.STORIES, filetypes=[("Story", "*.json")])
        if p:
            self.story, self.path = render.load(p), p
            self.load_story_into_ui()

    def save(self, ask=True):
        self.sync_top()
        if not self.path or ask:
            os.makedirs(paths.STORIES, exist_ok=True)
            p = filedialog.asksaveasfilename(initialdir=paths.STORIES, defaultextension=".json",
                                             initialfile=library.safe_name(self.story.get("title") or "story") + ".json")
            if not p:
                return None
            self.path = p
        with open(self.path, "w", encoding="utf8") as f:
            json.dump(self.story, f, ensure_ascii=False, indent=1)
        self.log(f"जतन: {self.path}")
        return self.path

    # ---- queue / worker ------------------------------------------------------------------------
    def enqueue(self, start=True):
        self.sync_top()
        if not any(sc.get("lines") for sc in self.story["scenes"]):
            messagebox.showwarning("Story Studio", "आधी संवाद (ओळी) लिहा.")
            return
        st = copy.deepcopy(self.story)
        shorts = self.v_shorts.get()
        name = library.safe_name(st.get("title") or "story") + ("_shorts" if shorts else "") + time.strftime("_%H%M%S")
        out = os.path.join(paths.OUTPUT, name + ".mp4")
        self.jobs.append({"story": st, "shorts": shorts, "out": out, "status": "रांगेत", "pct": 0})
        self.queuetab.refresh()
        if start:
            self.nb.select(self.queuetab)
            self.start_queue()

    def start_queue(self):
        if self.worker and self.worker.is_alive():
            return
        self.cancel_flag.clear()
        self.worker = threading.Thread(target=self._work, daemon=True)
        self.worker.start()

    def _work(self):
        while True:
            job = next((j for j in self.jobs if j["status"] == "रांगेत"), None)
            if job is None or self.cancel_flag.is_set():
                return
            job["status"] = "बनत आहे"
            self.ui.put(("jobs", None))
            try:
                render.render(job["story"], job["out"], shorts=job["shorts"], log=lambda s: self.ui.put(("log", s)),
                              progress=lambda i, n, j=job: j.update(pct=int(100 * i / max(1, n))),
                              cancel=self.cancel_flag.is_set)
                job["status"] = "✔ तयार"
            except render.Cancelled:
                job["status"] = "थांबवले"
            except Exception as e:
                job["status"] = "✖ चूक"
                self.ui.put(("log", f"चूक: {type(e).__name__}: {e}"))
            self.ui.put(("jobs", None))

    def log(self, s):
        self.ui.put(("log", s))

    def _pump(self):
        try:
            while True:
                kind, val = self.ui.get_nowait()
                if kind == "log":
                    self.queuetab.add_log(val)
                elif kind == "jobs":
                    self.queuetab.refresh()
                elif kind == "call":
                    val()
        except queue.Empty:
            pass
        if any(j["status"] == "बनत आहे" for j in self.jobs):
            self.queuetab.refresh(progress_only=True)
        self.root.after(200, self._pump)

    def bg(self, fn, done=None):
        """Run fn() in a thread; done(result) back on the UI thread; errors go to the log + a message box."""
        def run():
            try:
                r = fn()
                if done:
                    self.ui.put(("call", lambda: done(r)))
            except Exception as e:
                msg = f"{type(e).__name__}: {e}"
                self.ui.put(("log", "चूक: " + msg))
                self.ui.put(("call", lambda: messagebox.showerror("Story Studio", msg[:600])))
        threading.Thread(target=run, daemon=True).start()


# ================================================================================================
class StoryTab(ttk.Frame):
    def __init__(self, master, app):
        super().__init__(master, padding=6)
        self.app = app
        pw = ttk.PanedWindow(self, orient="horizontal")
        pw.pack(fill="both", expand=True)
        # scenes
        left = ttk.LabelFrame(pw, text="दृश्ये (Scenes)", padding=4)
        pw.add(left, weight=0)
        self.lb = tk.Listbox(left, width=24, font=UI_FONT, exportselection=False)
        self.lb.pack(fill="both", expand=True)
        self.lb.bind("<<ListboxSelect>>", lambda e: self.pick_scene())
        bf = ttk.Frame(left)
        bf.pack(fill="x")
        for t, fn in (("➕", self.add_scene), ("⧉", self.copy_scene), ("", self.del_scene), ("⬆", lambda: self.move_scene(-1)),
                      ("⬇", lambda: self.move_scene(1))):
            ttk.Button(bf, text=t, width=3, command=fn).pack(side="left")
        # centre: preview + lines
        mid = ttk.Frame(pw)
        pw.add(mid, weight=3)
        self.pv = ttk.Label(mid, text="पूर्वदृश्य", anchor="center")
        self.pv.pack(pady=2)
        lf = ttk.LabelFrame(mid, text="संवाद / ओळी (Timeline)", padding=4)
        lf.pack(fill="both", expand=True)
        self.tv = ttk.Treeview(lf, columns=("who", "act", "text"), show="headings", height=8)
        for c, t, w in (("who", "कोण", 110), ("act", "कृती", 100), ("text", "मजकूर", 520)):
            self.tv.heading(c, text=t)
            self.tv.column(c, width=w, stretch=(c == "text"))
        self.tv.pack(fill="both", expand=True)
        self.tv.bind("<<TreeviewSelect>>", lambda e: self.pick_line())
        ed = ttk.Frame(lf)
        ed.pack(fill="x", pady=3)
        self.v_who, self.v_act = tk.StringVar(), tk.StringVar(value="talk")
        self.cb_who = ttk.Combobox(ed, textvariable=self.v_who, width=14, state="readonly")
        self.cb_who.pack(side="left")
        ttk.Combobox(ed, textvariable=self.v_act, values=[f"{k} {v}" for k, v in ACTION_LABEL.items()], width=16,
                     state="readonly").pack(side="left", padx=3)
        self.txt = tk.Text(ed, height=2, width=60, font=UI_FONT, wrap="word")
        self.txt.pack(side="left", fill="x", expand=True)
        bf = ttk.Frame(lf)
        bf.pack(fill="x")
        for t, fn in (("➕ नवीन ओळ", self.add_line), ("✔ बदला", self.upd_line), ("", self.del_line), ("⬆", lambda: self.move_line(-1)),
                      ("⬇", lambda: self.move_line(1)), ("ऐका", self.listen), ("स्क्रिप्ट पेस्ट", self.paste_script)):
            ttk.Button(bf, text=t, command=fn).pack(side="left", padx=1)
        # right: scene settings + cast + scene characters
        right = ttk.Frame(pw)
        pw.add(right, weight=1)
        sf = ttk.LabelFrame(right, text="दृश्य सेटिंग", padding=4)
        sf.pack(fill="x")
        self.v_bg, self.v_cam, self.v_tr = tk.StringVar(), tk.StringVar(), tk.StringVar()
        for r, (lab, var, vals) in enumerate((("पार्श्वभूमी", self.v_bg, backgrounds()), ("कॅमेरा", self.v_cam, CAMERAS),
                                               ("संक्रमण", self.v_tr, TRANSITIONS))):
            ttk.Label(sf, text=lab).grid(row=r, column=0, sticky="w")
            cb = ttk.Combobox(sf, textvariable=var, values=vals, width=22, state="readonly")
            cb.grid(row=r, column=1, sticky="we", pady=1)
            cb.bind("<<ComboboxSelected>>", lambda e: self.upd_scene())
            if var is self.v_bg:
                self.cb_bg = cb
        cf = ttk.LabelFrame(right, text="पात्रे (Cast) व आवाज", padding=4)
        cf.pack(fill="x", pady=4)
        self.ct = ttk.Treeview(cf, columns=("name", "char", "voice"), show="headings", height=5)
        for c, t, w in (("name", "नाव", 90), ("char", "पात्र", 110), ("voice", "आवाज", 130)):
            self.ct.heading(c, text=t)
            self.ct.column(c, width=w)
        self.ct.pack(fill="x")
        self.ct.bind("<<TreeviewSelect>>", lambda e: self.pick_cast())
        g = ttk.Frame(cf)
        g.pack(fill="x")
        self.v_cname, self.v_voice, self.v_vstyle = tk.StringVar(), tk.StringVar(), tk.StringVar(value="auto")
        ttk.Label(g, text="नाव").grid(row=0, column=0)
        ttk.Entry(g, textvariable=self.v_cname, width=16).grid(row=0, column=1, sticky="we")
        ttk.Label(g, text="आवाज").grid(row=1, column=0)
        self.cb_voice = ttk.Combobox(g, textvariable=self.v_voice, width=24, state="readonly")
        self.cb_voice.grid(row=1, column=1, sticky="we")
        ttk.Label(g, text="ढंग").grid(row=2, column=0)
        ttk.Combobox(g, textvariable=self.v_vstyle, values=["auto"] + list(tts.STYLES), width=12, state="readonly").grid(row=2, column=1, sticky="w")
        bf = ttk.Frame(cf)
        bf.pack(fill="x")
        ttk.Button(bf, text="➕ पात्र जोडा", command=self.add_cast).pack(side="left")
        ttk.Button(bf, text="✔", width=3, command=self.upd_cast).pack(side="left")
        ttk.Button(bf, text="पात्र बदला", command=self.swap_cast).pack(side="left")
        ttk.Button(bf, text="", width=3, command=self.del_cast).pack(side="left")
        af = ttk.LabelFrame(right, text="या दृश्यातील पात्रे (स्टेज)", padding=4)
        af.pack(fill="x")
        self.at = ttk.Treeview(af, columns=("id", "pos", "enter", "exit", "scale"), show="headings", height=4)
        for c, t, w in (("id", "पात्र", 80), ("pos", "जागा", 70), ("enter", "येणे", 50), ("exit", "जाणे", 50), ("scale", "आकार", 45)):
            self.at.heading(c, text=t)
            self.at.column(c, width=w)
        self.at.pack(fill="x")
        self.at.bind("<<TreeviewSelect>>", lambda e: self.pick_actor())
        g = ttk.Frame(af)
        g.pack(fill="x")
        self.v_aid, self.v_pos, self.v_in, self.v_out = tk.StringVar(), tk.StringVar(value="left"), tk.StringVar(), tk.StringVar()
        self.v_scale, self.v_face = tk.DoubleVar(value=1.0), tk.StringVar()
        self.cb_aid = ttk.Combobox(g, textvariable=self.v_aid, width=10, state="readonly")
        self.cb_aid.grid(row=0, column=0, columnspan=2, sticky="we")
        ttk.Label(g, text="जागा").grid(row=1, column=0)
        ttk.Combobox(g, textvariable=self.v_pos, values=POSITIONS, width=9, state="readonly").grid(row=1, column=1)
        ttk.Label(g, text="चालत येणे").grid(row=2, column=0)
        ttk.Combobox(g, textvariable=self.v_in, values=SIDES, width=9, state="readonly").grid(row=2, column=1)
        ttk.Label(g, text="चालत जाणे").grid(row=3, column=0)
        ttk.Combobox(g, textvariable=self.v_out, values=SIDES, width=9, state="readonly").grid(row=3, column=1)
        ttk.Label(g, text="आकार").grid(row=4, column=0)
        ttk.Spinbox(g, from_=0.5, to=1.6, increment=0.1, textvariable=self.v_scale, width=6).grid(row=4, column=1, sticky="w")
        ttk.Label(g, text="तोंड").grid(row=5, column=0)
        ttk.Combobox(g, textvariable=self.v_face, values=["", "left", "right"], width=9, state="readonly").grid(row=5, column=1)
        bf = ttk.Frame(af)
        bf.pack(fill="x")
        ttk.Button(bf, text="➕ स्टेजवर", command=self.add_actor).pack(side="left")
        ttk.Button(bf, text="✔ बदला", command=self.upd_actor).pack(side="left")
        ttk.Button(bf, text="", width=3, command=self.del_actor).pack(side="left")

    # helpers
    def scene(self):
        sc = self.app.story["scenes"]
        self.app.si = max(0, min(self.app.si, len(sc) - 1))
        return sc[self.app.si]

    def reload(self):
        self.cb_bg.configure(values=backgrounds())
        self.lb.delete(0, "end")
        for i, sc in enumerate(self.app.story["scenes"]):
            first = (sc.get("lines") or [{}])[0].get("text", "")[:18]
            self.lb.insert("end", f"{i + 1}. {sc.get('background', '')}  {first}")
        self.lb.selection_clear(0, "end")
        self.lb.selection_set(self.app.si)
        sc = self.scene()
        self.v_bg.set(sc.get("background", "village"))
        self.v_cam.set(sc.get("camera", "slow-zoom"))
        self.v_tr.set(sc.get("transition", "fade"))
        self.refresh_cast()
        self.refresh_lines()
        self.refresh_actors()
        self.preview()

    def refresh_cast(self):
        cast = self.app.story.setdefault("cast", {})
        lang = self.app.lang()
        voices = tts.auto_cast(lang, cast, self.app.story.get("narrator"))
        labels = {v: lab for v, lab in tts.VOICES.get(lang, [])}
        self.ct.delete(*self.ct.get_children())
        for cid, c in cast.items():
            v, st = voices.get(cid, ("", ""))
            tag = "" if c.get("voice") else " (auto)"
            self.ct.insert("", "end", iid=cid, values=(c.get("name", cid), c.get("character", ""), f"{labels.get(v, v)} {st}{tag}"))
        self.cb_voice.configure(values=["auto"] + [f"{lab} | {v}" for v, lab in tts.VOICES.get(lang, [])])
        self.cb_aid.configure(values=list(cast))
        self.cb_who.configure(values=["narrator"] + [a["id"] for a in self.scene().get("characters", [])])

    def refresh_lines(self):
        self.tv.delete(*self.tv.get_children())
        cast = self.app.story.get("cast", {})
        for i, ln in enumerate(self.scene().get("lines", [])):
            who = ln.get("speaker", "narrator")
            nm = "निवेदक" if who == "narrator" else cast.get(who, {}).get("name", who)
            self.tv.insert("", "end", iid=str(i), values=(nm, ACTION_LABEL.get(ln.get("action", "talk"), ""), ln.get("text", "")))

    def refresh_actors(self):
        self.at.delete(*self.at.get_children())
        for i, a in enumerate(self.scene().get("characters", [])):
            self.at.insert("", "end", iid=str(i), values=(a["id"], a.get("position", "center"), a.get("enter") or "",
                                                         a.get("exit") or "", a.get("scale", 1.0)))
        self.cb_who.configure(values=["narrator"] + [a["id"] for a in self.scene().get("characters", [])])

    def preview(self, line=None):
        if line is None:
            sel = self.tv.selection()
            line = int(sel[0]) if sel else 0
        st = copy.deepcopy(self.app.story)
        si = self.app.si
        shorts = self.app.v_shorts.get()
        W, H = ((540, 960) if shorts else (960, 540))
        token = object()
        self._pv_token = token

        def work():
            return render.preview(st, si, W, H, line, self.app.rigs)

        def done(im):
            if self._pv_token is not token:
                return
            im.thumbnail((PREVIEW[0], PREVIEW[1] if not shorts else 420))
            self._ph = ImageTk.PhotoImage(im)
            self.pv.configure(image=self._ph, text="")
        self.app.bg(work, done)

    # scenes
    def pick_scene(self):
        s = self.lb.curselection()
        if s and s[0] != self.app.si:
            self.app.si = s[0]
            self.reload()

    def add_scene(self):
        sc = self.scene()
        self.app.story["scenes"].insert(self.app.si + 1, {"background": sc.get("background", "village"), "camera": "slow-zoom",
                                                           "transition": "fade", "lines": [],
                                                           "characters": [dict(a, enter=None, exit=None) for a in sc.get("characters", [])]})
        self.app.si += 1
        self.reload()

    def copy_scene(self):
        self.app.story["scenes"].insert(self.app.si + 1, copy.deepcopy(self.scene()))
        self.app.si += 1
        self.reload()

    def del_scene(self):
        if len(self.app.story["scenes"]) > 1 and messagebox.askyesno("Story Studio", "हे दृश्य काढायचे?"):
            del self.app.story["scenes"][self.app.si]
            self.reload()

    def move_scene(self, d):
        sc, i = self.app.story["scenes"], self.app.si
        if 0 <= i + d < len(sc):
            sc[i], sc[i + d] = sc[i + d], sc[i]
            self.app.si = i + d
            self.reload()

    def upd_scene(self):
        sc = self.scene()
        sc.update(background=self.v_bg.get(), camera=self.v_cam.get(), transition=self.v_tr.get())
        self.reload()

    # lines
    def _line_from_editor(self):
        return {"speaker": self.v_who.get() or "narrator", "text": self.txt.get("1.0", "end").strip(),
                "action": (self.v_act.get() or "talk").split()[0]}

    def pick_line(self):
        sel = self.tv.selection()
        if not sel:
            return
        ln = self.scene()["lines"][int(sel[0])]
        self.v_who.set(ln.get("speaker", "narrator"))
        a = ln.get("action", "talk")
        self.v_act.set(f"{a} {ACTION_LABEL.get(a, '')}")
        self.txt.delete("1.0", "end")
        self.txt.insert("1.0", ln.get("text", ""))
        self.preview(int(sel[0]))

    def add_line(self):
        ln = self._line_from_editor()
        if not ln["text"]:
            return
        lines = self.scene().setdefault("lines", [])
        sel = self.tv.selection()
        i = int(sel[0]) + 1 if sel else len(lines)
        lines.insert(i, ln)
        self.txt.delete("1.0", "end")
        self.refresh_lines()
        self.tv.selection_set(str(i))

    def upd_line(self):
        sel = self.tv.selection()
        if sel:
            self.scene()["lines"][int(sel[0])] = self._line_from_editor()
            self.refresh_lines()
            self.tv.selection_set(sel[0])

    def del_line(self):
        sel = self.tv.selection()
        if sel:
            del self.scene()["lines"][int(sel[0])]
            self.refresh_lines()

    def move_line(self, d):
        sel = self.tv.selection()
        lines = self.scene()["lines"]
        if sel and 0 <= int(sel[0]) + d < len(lines):
            i = int(sel[0])
            lines[i], lines[i + d] = lines[i + d], lines[i]
            self.refresh_lines()
            self.tv.selection_set(str(i + d))

    def listen(self):
        ln = self._line_from_editor()
        if not ln["text"]:
            return
        voices = tts.auto_cast(self.app.lang(), self.app.story.get("cast", {}), self.app.story.get("narrator"))
        v, st = voices.get(ln["speaker"]) or voices["narrator"]
        self.app.bg(lambda: tts.speak(ln["text"], v, st, self.app.log), open_file)

    def paste_script(self):
        w = tk.Toplevel(self)
        w.title("स्क्रिप्ट पेस्ट करा")
        ttk.Label(w, text="प्रत्येक ओळ:  नाव: संवाद      (नाव नसेल तर निवेदक)\nउदा.  रमेश: काकू, ही तुमची पर्स.", padding=6).pack(anchor="w")
        t = tk.Text(w, width=90, height=20, font=UI_FONT)
        t.pack(fill="both", expand=True, padx=6)

        def ok():
            lines = parse_script(t.get("1.0", "end"), self.app.story)
            sc = self.scene()
            sc.setdefault("lines", []).extend(lines)
            have = {a["id"] for a in sc.setdefault("characters", [])}
            free = [p for p in ("left", "right", "center", "far-left", "far-right")]
            for a in sc["characters"]:
                if a.get("position") in free:
                    free.remove(a["position"])
            for ln in lines:
                if ln["speaker"] != "narrator" and ln["speaker"] not in have and free:
                    have.add(ln["speaker"])
                    sc["characters"].append({"id": ln["speaker"], "position": free.pop(0)})
            w.destroy()
            self.reload()
        ttk.Button(w, text="✔ जोडा", command=ok).pack(pady=6)

    # cast
    def pick_cast(self):
        sel = self.ct.selection()
        if not sel:
            return
        c = self.app.story["cast"][sel[0]]
        self.v_cname.set(c.get("name", sel[0]))
        labels = {v: lab for v, lab in tts.VOICES.get(self.app.lang(), [])}
        self.v_voice.set(f"{labels.get(c['voice'], c['voice'])} | {c['voice']}" if c.get("voice") else "auto")
        self.v_vstyle.set(c.get("style", "auto"))
        self.v_aid.set(sel[0])

    def add_cast(self):
        p = CharPicker(self)
        self.wait_window(p)
        if not p.result:
            return
        m = library.load_manifest(p.result)
        cast = self.app.story.setdefault("cast", {})
        cid = p.result
        while cid in cast:
            cid += "2"
        nm = re.sub(r"\s*\(.*\)$", "", m.get("label", cid))
        cast[cid] = {"name": nm, "character": p.result, "gender": m.get("gender", "female")}
        sc = self.scene()
        used = {a.get("position") for a in sc.setdefault("characters", [])}
        pos = next((q for q in ("left", "right", "center", "far-left", "far-right") if q not in used), "center")
        sc["characters"].append({"id": cid, "position": pos})
        self.reload()

    def upd_cast(self):
        sel = self.ct.selection()
        if not sel:
            return
        c = self.app.story["cast"][sel[0]]
        c["name"] = self.v_cname.get().strip() or sel[0]
        v = self.v_voice.get()
        if v and v != "auto":
            c["voice"] = v.split("|")[-1].strip()
        else:
            c.pop("voice", None)
        if self.v_vstyle.get() and self.v_vstyle.get() != "auto":
            c["style"] = self.v_vstyle.get()
        else:
            c.pop("style", None)
        self.reload()

    def swap_cast(self):
        sel = self.ct.selection()
        if not sel:
            return
        p = CharPicker(self, "नवीन रूप निवडा")
        self.wait_window(p)
        if p.result:
            c = self.app.story["cast"][sel[0]]
            c["character"] = p.result
            c["gender"] = library.load_manifest(p.result).get("gender", c.get("gender"))
            self.reload()

    def del_cast(self):
        sel = self.ct.selection()
        if sel and messagebox.askyesno("Story Studio", "हे पात्र काढायचे? (त्याच्या ओळी निवेदकाकडे जातील)"):
            cid = sel[0]
            del self.app.story["cast"][cid]
            for sc in self.app.story["scenes"]:
                sc["characters"] = [a for a in sc.get("characters", []) if a["id"] != cid]
                for ln in sc.get("lines", []):
                    if ln.get("speaker") == cid:
                        ln["speaker"] = "narrator"
            self.reload()

    # scene actors
    def pick_actor(self):
        sel = self.at.selection()
        if not sel:
            return
        a = self.scene()["characters"][int(sel[0])]
        self.v_aid.set(a["id"])
        self.v_pos.set(a.get("position", "center"))
        self.v_in.set(a.get("enter") or "")
        self.v_out.set(a.get("exit") or "")
        self.v_scale.set(a.get("scale", 1.0))
        self.v_face.set(a.get("face") or "")

    def _actor_from_editor(self):
        try:
            sc = float(self.v_scale.get())
        except (tk.TclError, ValueError):
            sc = 1.0
        a = {"id": self.v_aid.get(), "position": self.v_pos.get() or "center", "enter": self.v_in.get() or None,
             "exit": self.v_out.get() or None, "scale": round(sc, 2)}
        if self.v_face.get():
            a["face"] = self.v_face.get()
        return a

    def add_actor(self):
        a = self._actor_from_editor()
        if a["id"] and a["id"] not in [x["id"] for x in self.scene().setdefault("characters", [])]:
            self.scene()["characters"].append(a)
            self.reload()

    def upd_actor(self):
        sel = self.at.selection()
        if sel and self.v_aid.get():
            self.scene()["characters"][int(sel[0])] = self._actor_from_editor()
            self.reload()

    def del_actor(self):
        sel = self.at.selection()
        if sel:
            del self.scene()["characters"][int(sel[0])]
            self.reload()


# ================================================================================================
class AITab(ttk.Frame):
    def __init__(self, master, app):
        super().__init__(master, padding=10)
        self.app = app
        g = ttk.Frame(self)
        g.pack(fill="x")
        self.v_theme, self.v_moral = tk.StringVar(), tk.StringVar()
        self.v_scenes, self.v_lines = tk.IntVar(value=2), tk.IntVar(value=8)
        ttk.Label(g, text="विषय / शीर्षक:").grid(row=0, column=0, sticky="w")
        ttk.Entry(g, textvariable=self.v_theme, width=70).grid(row=0, column=1, sticky="we")
        ttk.Label(g, text="तात्पर्य (ऐच्छिक):").grid(row=1, column=0, sticky="w")
        ttk.Entry(g, textvariable=self.v_moral, width=70).grid(row=1, column=1, sticky="we")
        ttk.Label(g, text="दृश्ये / ओळी:").grid(row=2, column=0, sticky="w")
        f = ttk.Frame(g)
        f.grid(row=2, column=1, sticky="w")
        ttk.Spinbox(f, from_=1, to=6, textvariable=self.v_scenes, width=4).pack(side="left")
        ttk.Spinbox(f, from_=4, to=30, textvariable=self.v_lines, width=4).pack(side="left", padx=6)
        ttk.Label(self, text="पाठ्यपुस्तकातील धडा (ऐच्छिक) - इथे पेस्ट करा:").pack(anchor="w", pady=(8, 0))
        self.txt = tk.Text(self, height=14, font=UI_FONT, wrap="word")
        self.txt.pack(fill="both", expand=True)
        self.btn = ttk.Button(self, text="✨ AI ने गोष्ट लिहा", style="Big.TButton", command=self.go)
        self.btn.pack(pady=8)
        ttk.Label(self, text="AI साठी सेटिंग्जमध्ये Gemini / NVIDIA / Groq key किंवा GCP key file लागते. गोष्ट 'गोष्ट' टॅबमध्ये उघडते, तिथे बदला.").pack()

    def go(self):
        theme = self.v_theme.get().strip()
        if not theme and not self.txt.get("1.0", "end").strip():
            messagebox.showwarning("Story Studio", "विषय लिहा.")
            return
        self.btn.configure(state="disabled", text="लिहित आहे ...")
        lang = self.app.lang()
        args = dict(theme=theme, lang=lang, moral=self.v_moral.get(), text=self.txt.get("1.0", "end"),
                    scenes=int(self.v_scenes.get()), lines=int(self.v_lines.get()), characters=char_info(),
                    backgrounds=backgrounds(), log=self.app.log)

        def done(st):
            self.btn.configure(state="normal", text="✨ AI ने गोष्ट लिहा")
            st["music"] = self.app.v_music.get() or "calm"
            self.app.story, self.app.path = st, None
            self.app.load_story_into_ui()
            self.app.nb.select(self.app.storytab)

        def work():
            try:
                return story_ai.generate(config.load(), **args)
            finally:
                self.app.ui.put(("call", lambda: self.btn.configure(state="normal", text="✨ AI ने गोष्ट लिहा")))
        self.app.bg(work, done)


# ================================================================================================
class CharsTab(ttk.Frame):
    def __init__(self, master, app):
        super().__init__(master, padding=6)
        self.app = app
        top = ttk.Frame(self)
        top.pack(fill="x")
        ttk.Button(top, text="FLA आयात", command=self.import_files).pack(side="left")
        ttk.Button(top, text="FLA फोल्डर स्कॅन", command=self.import_folder).pack(side="left", padx=4)
        ttk.Button(top, text="निवडलेले काढा", command=self.delete).pack(side="left")
        ttk.Button(top, text="रिग उघडा", command=self.open_rig).pack(side="left", padx=4)
        self.status = ttk.Label(top, text="")
        self.status.pack(side="left", padx=10)
        self.pb = ttk.Progressbar(top, length=200)
        self.pb.pack(side="right")
        self.cv = tk.Canvas(self, bg="white")
        sb = ttk.Scrollbar(self, orient="vertical", command=self.cv.yview)
        self.cv.configure(yscrollcommand=sb.set)
        sb.pack(side="right", fill="y")
        self.cv.pack(fill="both", expand=True)
        self.sel = tk.StringVar()
        self.imgs = []
        self.fill()

    def fill(self):
        self.cv.delete("all")
        self.imgs.clear()
        info = char_info()
        self.status.configure(text=f"{len(info)} पात्रे")
        for i, (cid, lab, g) in enumerate(info):
            im = library.thumb(cid)
            if im is None:
                continue
            im = im.copy()
            im.thumbnail((110, 170))
            ph = ImageTk.PhotoImage(im)
            self.imgs.append(ph)
            f = tk.Frame(self.cv, bg="white", bd=2, relief="groove")
            tk.Radiobutton(f, image=ph, variable=self.sel, value=cid, indicatoron=False, bg="white", selectcolor="#ffe08a",
                           width=120, height=175).pack()
            tk.Label(f, text=f"{lab}\n{'स्त्री' if g == 'female' else 'पुरुष'}", bg="white", font=UI_FONT, wraplength=120).pack()
            self.cv.create_window((i % 9) * 135 + 5, (i // 9) * 240 + 5, anchor="nw", window=f)
        self.cv.configure(scrollregion=(0, 0, 9 * 135, (len(info) // 9 + 1) * 240 + 10))

    def _run(self, srcs):
        self.pb.configure(value=0)

        def work():
            return library_build.build(srcs, log=self.app.log,
                                       progress=lambda a, n: self.app.ui.put(("call", lambda: self.pb.configure(value=100 * a / n))))

        def done(added):
            self.pb.configure(value=100)
            self.fill()
            messagebox.showinfo("Story Studio", f"{len(added)} नवीन पात्रे जोडली.\nचूक दिसल्यास 'रिग' टॅबमध्ये दुरुस्त करा.")
        self.app.log("FLA आयात सुरू ... (लॉग टॅब पहा)")
        self.app.bg(work, done)

    def import_files(self):
        fs = filedialog.askopenfilenames(filetypes=[("Adobe Animate", "*.fla *.xfl")])
        if fs:
            self._run(list(fs))

    def import_folder(self):
        d = filedialog.askdirectory()
        if d:
            fs = sorted(glob.glob(os.path.join(d, "**", "*.fla"), recursive=True))
            if not fs:
                messagebox.showwarning("Story Studio", "या फोल्डरमध्ये .fla फाइल नाही.")
                return
            self._run(fs)

    def delete(self):
        c = self.sel.get()
        if c and messagebox.askyesno("Story Studio", f"'{c}' पात्र लायब्ररीतून काढायचे?"):
            shutil.rmtree(os.path.join(paths.CHARS, c), ignore_errors=True)
            self.fill()

    def open_rig(self):
        if self.sel.get():
            self.app.rigtab.v_char.set(self.sel.get())
            self.app.rigtab.load()
        self.app.nb.select(self.app.rigtab)


# ================================================================================================
class RigTab(ttk.Frame):
    """Correct the auto-rig: which layer is the head / arms / legs, mouth & eye symbols with frame numbers,
    joints by clicking on the picture. Live preview of talk / blink / gestures / walk."""
    JOINTS = [("गळा (neck)", ("neck",)), ("पुढचा खांदा", ("arm_front", "shoulder")), ("पुढचे कोपर", ("arm_front", "elbow")),
              ("मागचा खांदा", ("arm_back", "shoulder")), ("मागचे कोपर", ("arm_back", "elbow"))]

    def __init__(self, master, app):
        super().__init__(master, padding=6)
        self.app = app
        self.m = None
        self.r = None
        top = ttk.Frame(self)
        top.pack(fill="x")
        ttk.Label(top, text="पात्र:").pack(side="left")
        self.v_char = tk.StringVar()
        self.cb = ttk.Combobox(top, textvariable=self.v_char, values=library.characters(), width=24, state="readonly")
        self.cb.pack(side="left")
        self.cb.bind("<<ComboboxSelected>>", lambda e: self.load())
        ttk.Button(top, text="Auto-rig पुन्हा", command=self.autorig).pack(side="left", padx=4)
        ttk.Button(top, text="जतन", command=self.save).pack(side="left")
        ttk.Label(top, text="   पूर्वदृश्य:").pack(side="left")
        self.v_anim = tk.StringVar(value="talk")
        for a in ("idle", "talk", "point", "wave", "think", "walk"):
            ttk.Radiobutton(top, text=a, value=a, variable=self.v_anim).pack(side="left")
        body = ttk.Frame(self)
        body.pack(fill="both", expand=True, pady=6)
        self.pic = tk.Canvas(body, width=420, height=620, bg="#f4f4f4")
        self.pic.pack(side="left")
        self.pic.bind("<Button-1>", self.click)
        form = ttk.Frame(body, padding=8)
        form.pack(side="left", fill="both", expand=True)
        self.vars = {}
        rows = [("label", "नाव (लेबल)"), ("gender", "लिंग"), ("faces", "चेहरा कोणीकडे"), ("head", "डोके layer"),
                ("mouth_sym", "तोंड symbol"), ("mouth_frames", "तोंड frames: बंद,मध्यम,उघडे"), ("eye_sym", "डोळे symbol"),
                ("eye_frames", "डोळे frames: उघडे; मिटलेले (उदा 0; 10,11,12)"), ("af_upper", "पुढचा दंड layer"),
                ("af_fore", "पुढचा हात (कोपरापुढे) layer"), ("ab_upper", "मागचा दंड layer"), ("ab_fore", "मागचा हात layer"),
                ("legs", "पाय layers (स्वल्पविराम)"), ("leg_swing", "चालताना पाय हलणे (अंश)")]
        for i, (k, lab) in enumerate(rows):
            ttk.Label(form, text=lab).grid(row=i, column=0, sticky="w", pady=1)
            v = tk.StringVar()
            self.vars[k] = v
            if k == "gender":
                w = ttk.Combobox(form, textvariable=v, values=["female", "male"], state="readonly", width=40)
            elif k == "faces":
                w = ttk.Combobox(form, textvariable=v, values=["left", "right"], state="readonly", width=40)
            elif k in ("label", "mouth_frames", "eye_frames", "legs", "leg_swing"):
                w = ttk.Entry(form, textvariable=v, width=43)
            else:
                w = ttk.Combobox(form, textvariable=v, width=40)
                setattr(self, "cb_" + k, w)
            w.grid(row=i, column=1, sticky="we", pady=1)
        ttk.Label(form, text="सांधे: खालील निवडा, मग चित्रावर क्लिक करा").grid(row=len(rows), column=0, columnspan=2, sticky="w", pady=(10, 0))
        self.v_joint = tk.IntVar(value=0)
        jf = ttk.Frame(form)
        jf.grid(row=len(rows) + 1, column=0, columnspan=2, sticky="w")
        for i, (lab, _) in enumerate(self.JOINTS):
            ttk.Radiobutton(jf, text=lab, value=i, variable=self.v_joint).pack(side="left")
        ttk.Button(form, text="↻ लागू करा (पूर्वदृश्य)", command=self.apply).grid(row=len(rows) + 2, column=1, sticky="e", pady=8)
        self.t0 = time.time()
        self.after(150, self.tick)

    def load(self):
        n = self.v_char.get()
        if not n:
            return
        self.m = library.load_manifest(n)
        self.r = rig.Rig(os.path.join(paths.CHARS, n), self.m)
        self.app.rigs.pop(n, None)
        layers = [L.get("name") for L in self.r.doc.layers(self.r.doc.layer_scope)]
        syms = sorted(os.path.splitext(os.path.relpath(f, os.path.join(paths.CHARS, n, "LIBRARY")))[0].replace(os.sep, "/")
                      for f in glob.glob(os.path.join(paths.CHARS, n, "LIBRARY", "**", "*.xml"), recursive=True))
        for k in ("head", "af_upper", "af_fore", "ab_upper", "ab_fore"):
            getattr(self, "cb_" + k).configure(values=[""] + layers)
        mouthy = [s for s in syms if autorig.RX_MOUTH.search(s)] + [s for s in syms if not autorig.RX_MOUTH.search(s)]
        eyey = [s for s in syms if autorig.RX_EYE.search(s)] + [s for s in syms if not autorig.RX_EYE.search(s)]
        self.cb_mouth_sym.configure(values=mouthy)
        self.cb_eye_sym.configure(values=eyey)
        self.to_form()

    def to_form(self):
        m, r = self.m, self.m["rig"]
        v = self.vars
        v["label"].set(m.get("label", ""))
        v["gender"].set(m.get("gender", "female"))
        v["faces"].set(r.get("faces", "left"))
        v["head"].set(r.get("head") or "")
        mo = r.get("mouth") or {}
        if mo:
            s, f = next(iter(mo.items()))
            v["mouth_sym"].set(s)
            v["mouth_frames"].set(f"{f.get('closed', 0)},{f.get('mid', 0)},{f.get('open', 0)}")
        ey = r.get("eyes") or {}
        if ey:
            s, f = next(iter(ey.items()))
            v["eye_sym"].set(s)
            v["eye_frames"].set(f"{f.get('open', 0)}; {','.join(str(b) for b in f.get('blink', []))}")
        for side, a in (("af", "arm_front"), ("ab", "arm_back")):
            v[side + "_upper"].set((r.get(a) or {}).get("upper", ""))
            v[side + "_fore"].set((r.get(a) or {}).get("fore", ""))
        v["legs"].set(", ".join(r.get("legs") or []))
        v["leg_swing"].set(str(r.get("leg_swing", 11.0)))

    def from_form(self):
        m, r = self.m, self.m["rig"]
        v = {k: x.get().strip() for k, x in self.vars.items()}
        m["label"], m["gender"] = v["label"] or m.get("name"), v["gender"] or "female"
        r["faces"], r["head"] = v["faces"] or "left", v["head"] or None
        try:
            c, mi, o = [int(x) for x in v["mouth_frames"].split(",")[:3]]
            old = r.get("mouth") or {}
            r["mouth"] = {v["mouth_sym"]: {"closed": c, "mid": mi, "open": o}}
            for s in old:              # extra mouth symbols (other poses) keep their frames
                if s != v["mouth_sym"]:
                    r["mouth"][s] = old[s]
        except ValueError:
            pass
        try:
            op, bl = (v["eye_frames"].split(";") + [""])[:2]
            old = r.get("eyes") or {}
            r["eyes"] = {v["eye_sym"]: {"open": int(op), "blink": [int(b) for b in bl.split(",") if b.strip()]}}
            for s in old:
                if s != v["eye_sym"]:
                    r["eyes"][s] = old[s]
        except ValueError:
            pass
        for side, a in (("af", "arm_front"), ("ab", "arm_back")):
            if v[side + "_upper"]:
                arm = r.get(a) or {}
                arm.update(upper=v[side + "_upper"], fore=v[side + "_fore"] or None)
                arm.setdefault("shoulder", r.get("neck") or [0, 0])
                arm.setdefault("elbow", arm["shoulder"])
                r[a] = arm
            else:
                r[a] = None
        r["legs"] = [x.strip() for x in v["legs"].split(",") if x.strip()]
        try:
            r["leg_swing"] = float(v["leg_swing"])
        except ValueError:
            pass

    def apply(self):
        if not self.m:
            return
        self.from_form()
        self.r = rig.Rig(os.path.join(paths.CHARS, self.v_char.get()), self.m)

    def autorig(self):
        n = self.v_char.get()
        if not n:
            return

        def work():
            d = xfl.Doc(os.path.join(paths.CHARS, n))
            return autorig.guess(d, self.m["rig"]["root"], int(self.m["rig"].get("frame", 0)))

        def done(g):
            g["leg_swing"] = self.m["rig"].get("leg_swing", 11.0)
            self.m["rig"] = g
            self.to_form()
            self.apply()
        self.app.bg(work, done)

    def save(self):
        if not self.m:
            return
        self.apply()
        library.save_manifest(self.v_char.get(), self.m)
        self.app.rigs.pop(self.v_char.get(), None)
        thumb = os.path.join(paths.CHARS, self.v_char.get(), "thumb.png")
        if os.path.exists(thumb):
            os.remove(thumb)
        library.thumb(self.v_char.get())
        self.app.charstab.fill()
        messagebox.showinfo("Story Studio", "रिग जतन झाली.")

    def _scale(self):
        b = self.r.bounds
        return min(400 / (b[2] - b[0]), 600 / (b[3] - b[1]))

    def click(self, e):
        if not self.r:
            return
        b, s = self.r.bounds, self._scale()
        x, y = b[0] + (e.x - 10) / s, b[1] + (e.y - 10) / s
        key = self.JOINTS[self.v_joint.get()][1]
        r = self.m["rig"]
        if key == ("neck",):
            r["neck"] = [round(x, 2), round(y, 2)]
        elif r.get(key[0]):
            r[key[0]][key[1]] = [round(x, 2), round(y, 2)]
        self.r = rig.Rig(os.path.join(paths.CHARS, self.v_char.get()), self.m)

    def tick(self):
        try:
            if self.r and str(self.app.nb.select()) == str(self):
                t = time.time() - self.t0
                a = self.v_anim.get()
                s = self._scale()
                mouth = (0.5 + 0.5 * abs(((t * 4.2) % 2) - 1)) if a in ("talk", "point", "wave", "think") else 0.0
                img, _ = self.r.frame(t, mouth=mouth, blink=(t % 3.0) < 0.15, gesture=a if a not in ("walk",) else "idle",
                                      gk=0.5 - 0.5 * __import__("math").cos(min(1.0, (t % 4.0) / 0.8) * 3.1416),
                                      walk=(t * 6.0) if a == "walk" else None, scale=s)
                self._ph = ImageTk.PhotoImage(img)
                self.pic.delete("all")
                self.pic.create_image(10, 10, anchor="nw", image=self._ph)
                r, b = self.m["rig"], self.r.bounds
                pts = [r.get("neck")] + [(r.get(k) or {}).get(j) for k in ("arm_front", "arm_back") for j in ("shoulder", "elbow")]
                for i, p in enumerate(pts):
                    if p:
                        px, py = 10 + (p[0] - b[0]) * s, 10 + (p[1] - b[1]) * s
                        col = "#ff3030" if i == self.v_joint.get() else "#2060ff"
                        self.pic.create_oval(px - 5, py - 5, px + 5, py + 5, outline=col, width=2)
        except Exception as e:     # bad manual values: show, don't crash the loop
            self.pic.delete("all")
            self.pic.create_text(200, 300, text=f"{type(e).__name__}: {e}"[:200], width=380)
        self.after(90, self.tick)


# ================================================================================================
class MediaTab(ttk.Frame):
    def __init__(self, master, app):
        super().__init__(master, padding=6)
        self.app = app
        bf = ttk.LabelFrame(self, text="पार्श्वभूमी (Backgrounds)", padding=4)
        bf.pack(fill="both", expand=True)
        t = ttk.Frame(bf)
        t.pack(fill="x")
        ttk.Button(t, text="➕ चित्रे जोडा", command=self.add_bg).pack(side="left")
        ttk.Button(t, text="फोल्डर जोडा", command=self.add_bg_folder).pack(side="left", padx=4)
        ttk.Button(t, text="FLA मधून पार्श्वभूमी", command=self.add_bg_fla).pack(side="left", padx=4)
        ttk.Button(t, text="काढा", command=self.del_bg).pack(side="left")
        self.cv = tk.Canvas(bf, bg="white", height=360)
        sb = ttk.Scrollbar(bf, orient="vertical", command=self.cv.yview)
        self.cv.configure(yscrollcommand=sb.set)
        sb.pack(side="right", fill="y")
        self.cv.pack(fill="both", expand=True)
        mf = ttk.LabelFrame(self, text="पार्श्वसंगीत (Music) - बोलताना आपोआप हळू होते", padding=4)
        mf.pack(fill="x", pady=4)
        self.ml = tk.Listbox(mf, height=6, font=UI_FONT)
        self.ml.pack(side="left", fill="x", expand=True)
        b = ttk.Frame(mf)
        b.pack(side="left", padx=4)
        ttk.Button(b, text="➕ संगीत जोडा", command=self.add_music).pack(fill="x")
        ttk.Button(b, text="▶ ऐका", command=self.play_music).pack(fill="x")
        ttk.Button(b, text="काढा", command=self.del_music).pack(fill="x")
        self.sel = tk.StringVar()
        self.imgs = []
        self.fill()

    def fill(self):
        os.makedirs(paths.BGS, exist_ok=True)
        if not any(f.endswith(".png") for f in os.listdir(paths.BGS)):
            bgmaker.make_all(paths.BGS)
        if not os.path.isdir(paths.MUSIC) or not os.listdir(paths.MUSIC):
            musicmaker.make_all(paths.MUSIC)
        self.cv.delete("all")
        self.imgs.clear()
        files = [f for f in sorted(os.listdir(paths.BGS)) if f.lower().endswith((".png", ".jpg", ".jpeg", ".webp"))]
        for i, f in enumerate(files):
            try:
                im = Image.open(os.path.join(paths.BGS, f))
                im.thumbnail((220, 124))
            except OSError:
                continue
            ph = ImageTk.PhotoImage(im)
            self.imgs.append(ph)
            fr = tk.Frame(self.cv, bg="white")
            tk.Radiobutton(fr, image=ph, variable=self.sel, value=f, indicatoron=False, selectcolor="#ffe08a").pack()
            tk.Label(fr, text=os.path.splitext(f)[0][:28], bg="white", font=UI_FONT).pack()
            self.cv.create_window((i % 5) * 235 + 5, (i // 5) * 160 + 5, anchor="nw", window=fr)
        self.cv.configure(scrollregion=(0, 0, 5 * 235, (len(files) // 5 + 1) * 160))
        self.ml.delete(0, "end")
        for m in music_list()[1:]:
            self.ml.insert("end", m)
        self.app.cb_music.configure(values=music_list())
        if hasattr(self.app, "storytab"):
            self.app.storytab.cb_bg.configure(values=backgrounds())

    def _copy(self, files, dst):
        os.makedirs(dst, exist_ok=True)
        for f in files:
            shutil.copy2(f, os.path.join(dst, os.path.basename(f)))
        self.fill()

    def add_bg(self):
        fs = filedialog.askopenfilenames(filetypes=[("Images", "*.png *.jpg *.jpeg *.webp")])
        if fs:
            self._copy(fs, paths.BGS)

    def add_bg_folder(self):
        d = filedialog.askdirectory()
        if d:
            fs = [f for f in glob.glob(os.path.join(d, "**", "*"), recursive=True) if f.lower().endswith((".png", ".jpg", ".jpeg", ".webp"))]
            self._copy(fs, paths.BGS)
            messagebox.showinfo("Story Studio", f"{len(fs)} चित्रे जोडली.")

    def add_bg_fla(self):
        fs = filedialog.askopenfilenames(filetypes=[("Adobe Animate", "*.fla")])
        if not fs:
            return
        self.app.log("FLA पार्श्वभूमी आयात सुरू ...")

        def work():
            return sum(len(bg_import.from_fla(f, log=self.app.log)) for f in fs)

        def done(n):
            self.fill()
            messagebox.showinfo("Story Studio", f"{n} पार्श्वभूमी जोडल्या.")
        self.app.bg(work, done)

    def del_bg(self):
        if self.sel.get() and messagebox.askyesno("Story Studio", "ही पार्श्वभूमी काढायची?"):
            os.remove(os.path.join(paths.BGS, self.sel.get()))
            self.fill()

    def add_music(self):
        fs = filedialog.askopenfilenames(filetypes=[("Audio", "*.mp3 *.wav *.m4a *.ogg")])
        if fs:
            self._copy(fs, paths.MUSIC)

    def _music_file(self):
        s = self.ml.curselection()
        return render.music_path(self.ml.get(s[0])) if s else None

    def play_music(self):
        f = self._music_file()
        if f:
            open_file(f)

    def del_music(self):
        f = self._music_file()
        if f and messagebox.askyesno("Story Studio", "हे संगीत काढायचे?"):
            os.remove(f)
            self.fill()


# ================================================================================================
class QueueTab(ttk.Frame):
    def __init__(self, master, app):
        super().__init__(master, padding=6)
        self.app = app
        t = ttk.Frame(self)
        t.pack(fill="x")
        ttk.Button(t, text="▶ रांग सुरू", command=app.start_queue).pack(side="left")
        ttk.Button(t, text="⏹ थांबवा", command=app.cancel_flag.set).pack(side="left", padx=4)
        ttk.Button(t, text="यादीतून काढा", command=self.remove).pack(side="left")
        ttk.Button(t, text="▶ व्हिडिओ पहा", command=self.play).pack(side="left", padx=4)
        ttk.Button(t, text="output फोल्डर", command=lambda: (os.makedirs(paths.OUTPUT, exist_ok=True), open_file(paths.OUTPUT))).pack(side="left")
        self.tv = ttk.Treeview(self, columns=("title", "fmt", "status", "pct", "file"), show="headings", height=8)
        for c, tx, w in (("title", "गोष्ट", 260), ("fmt", "प्रकार", 80), ("status", "स्थिती", 100), ("pct", "%", 60), ("file", "फाइल", 520)):
            self.tv.heading(c, text=tx)
            self.tv.column(c, width=w)
        self.tv.pack(fill="x", pady=4)
        self.logt = tk.Text(self, height=20, font=UI_FONT)
        self.logt.pack(fill="both", expand=True)

    def refresh(self, progress_only=False):
        if progress_only:
            for i, j in enumerate(self.app.jobs):
                if self.tv.exists(str(i)):
                    self.tv.set(str(i), "pct", j["pct"])
            return
        self.tv.delete(*self.tv.get_children())
        for i, j in enumerate(self.app.jobs):
            self.tv.insert("", "end", iid=str(i), values=(j["story"].get("title", ""), "Shorts" if j["shorts"] else "16:9",
                                                         j["status"], j["pct"], j["out"]))

    def add_log(self, s):
        self.logt.insert("end", s + "\n")
        self.logt.see("end")

    def _sel(self):
        s = self.tv.selection()
        return self.app.jobs[int(s[0])] if s else None

    def remove(self):
        j = self._sel()
        if j and j["status"] != "बनत आहे":
            self.app.jobs.remove(j)
            self.refresh()

    def play(self):
        j = self._sel()
        if j and os.path.exists(j["out"]):
            open_file(j["out"])


# ================================================================================================
class SettingsTab(ttk.Frame):
    FIELDS = [("gemini_api_key", "Gemini API key"), ("nvidia_api_key", "NVIDIA API key"), ("groq_api_key", "Groq API key"),
              ("sarvam_api_key", "Sarvam API key"), ("gemini_model", "Gemini model"), ("nvidia_model", "NVIDIA model"),
              ("groq_model", "Groq model"), ("cost_cap_inr", "खर्च मर्यादा (₹ / महिना)"), ("music_volume", "संगीत आवाज (0.05-0.4)")]

    def __init__(self, master, app):
        super().__init__(master, padding=10)
        self.app = app
        self.vars = {}
        ttk.Label(self, text="आवाज (TTS): मोफत Microsoft Edge आवाज वापरले जातात - key लागत नाही. AI गोष्टीसाठी खालीलपैकी एक key टाका.").grid(
            row=0, column=0, columnspan=3, sticky="w", pady=(0, 8))
        for i, (k, lab) in enumerate(self.FIELDS, 1):
            ttk.Label(self, text=lab).grid(row=i, column=0, sticky="w", pady=2)
            v = tk.StringVar(value=str(cfg.get(k, "")))
            self.vars[k] = v
            ttk.Entry(self, textvariable=v, width=60, show="•" if k.endswith("_key") else "").grid(row=i, column=1, sticky="we")
        r = len(self.FIELDS) + 1
        vx = cfg.get("vertex") or {}
        self.v_proj, self.v_loc = tk.StringVar(value=vx.get("project", "")), tk.StringVar(value=vx.get("location", "us-central1"))
        self.v_kf = tk.StringVar(value=vx.get("key_file", ""))
        ttk.Label(self, text="GCP project (Vertex)").grid(row=r, column=0, sticky="w")
        ttk.Entry(self, textvariable=self.v_proj, width=60).grid(row=r, column=1, sticky="we")
        ttk.Label(self, text="GCP location").grid(row=r + 1, column=0, sticky="w")
        ttk.Entry(self, textvariable=self.v_loc, width=60).grid(row=r + 1, column=1, sticky="we")
        ttk.Label(self, text="GCP service-account key").grid(row=r + 2, column=0, sticky="w")
        ttk.Entry(self, textvariable=self.v_kf, width=60).grid(row=r + 2, column=1, sticky="we")
        ttk.Button(self, text="निवडा ...", command=self.pick_key).grid(row=r + 2, column=2)
        self.v_order = tk.StringVar(value=", ".join(cfg.get("llm_order", [])))
        ttk.Label(self, text="AI क्रम").grid(row=r + 3, column=0, sticky="w")
        ttk.Entry(self, textvariable=self.v_order, width=60).grid(row=r + 3, column=1, sticky="we")
        ttk.Button(self, text="सेटिंग्ज जतन", style="Big.TButton", command=self.save).grid(row=r + 4, column=1, sticky="w", pady=12)

    def pick_key(self):
        f = filedialog.askopenfilename(filetypes=[("JSON key", "*.json")])
        if f:
            dst = os.path.join(paths.HERE, "gcp_key.json")
            if os.path.abspath(f) != os.path.abspath(dst):
                shutil.copyfile(f, dst)
            self.v_kf.set("gcp_key.json")
            try:
                with open(dst, encoding="utf8") as fh:
                    self.v_proj.set(self.v_proj.get() or json.load(fh).get("project_id", ""))
            except (OSError, ValueError):
                pass

    def save(self):
        for k, v in self.vars.items():
            val = v.get().strip()
            if k in ("cost_cap_inr", "music_volume"):
                try:
                    val = float(val)
                except ValueError:
                    continue
            cfg[k] = val
        cfg["vertex"] = {"project": self.v_proj.get().strip(), "location": self.v_loc.get().strip() or "us-central1",
                         "key_file": self.v_kf.get().strip()}
        cfg["llm_order"] = [x.strip() for x in self.v_order.get().split(",") if x.strip()]
        cfg["lang"] = self.app.lang()
        config.save(cfg)
        messagebox.showinfo("Story Studio", "सेटिंग्ज जतन झाल्या.")


def main():
    for d in (paths.CHARS, paths.BGS, paths.MUSIC, paths.STORIES, paths.OUTPUT, paths.CACHE):
        os.makedirs(d, exist_ok=True)
    root = tk.Tk()
    App(root)
    root.mainloop()


if __name__ == "__main__":
    main()
