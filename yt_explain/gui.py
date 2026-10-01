#!/usr/bin/env python3
"""GUI / .exe entry for yt_explain: one big window.

Top: इयत्ता -> विषय -> घटक, teacher (शिक्षिका / शिक्षक / none), slide theme, mode, YouTube tick, ▶ / ➕ रांग.
Left: live preview of a sample slide with the chosen theme + teacher.
Tabs: लॉग | पाठ मजकूर (tick/edit chapter parts stored in explain.db) | स्क्रिप्ट (edit explain_script.json in place) |
      रांग (tick many chapters, render one after another).
Uses explain.db and auto.run_unit like auto.py."""
import glob
import io
import json
import os
import queue
import threading
import tkinter as tk
from tkinter import font as tkfont, messagebox, ttk

import auto
import avatar
import textparts
from explain import apply_options
from ytstudio.slides import Renderer

cfg = auto.load_cfg()
st = auto.open_state(cfg)

TEACHERS = [("शिक्षिका (teacher2)", "teacher2"), ("शिक्षक (teacher3)", "teacher3"), ("अवतार नाही (फक्त स्लाइड)", "none")]
THEMES = [("विषयानुसार (auto)", "auto"), ("गणित - निळा grid", "math"), ("विज्ञान - हिरवा lab", "science"),
          ("मराठी - warm paper", "marathi"), ("हिंदी", "hindi"), ("English - classic", "english"), ("इतिहास - parchment", "history"),
          ("भूगोल - map", "geography"), ("नागरिकशास्त्र", "civics"), ("परिसर अभ्यास", "evs"), ("संस्कृत", "sanskrit"),
          ("साधी (general)", "general"), ("channel रंग (brand)", "brand")]
MODES = [("व्हिडिओ (जतन केलेली स्क्रिप्ट असेल तर तीच)", "video"), ("फक्त स्क्रिप्ट (स्क्रिप्ट टॅबमध्ये बदला)", "script"),
         ("नवीन स्क्रिप्ट + व्हिडिओ", "new")]
PREVIEW_W = 560
FONT_SIZE = 13


def _avatar_default():
    av = cfg.get("avatar") or {}
    return (av.get("name") or "teacher2") if av.get("enabled", True) else "none"


def rows():
    out = []
    for r in st.db.execute("SELECT * FROM units ORDER BY std, subject, chapter_no, block"):
        r = dict(r)
        r["subj"] = f"{r['subject']} ({ {'en': 'English', 'hi': 'हिंदी'}.get(r.get('lang'), 'मराठी') })"
        m = json.loads(r.get("meta_json") or "{}")
        t = r["title"] if r["title"][:1].isdigit() else f"{m.get('tachan_seq', r['chapter_no'])}. {r['title']}"
        r["topic"] = t + (" (कविता)" if m.get("is_poem") else "")
        out.append(r)
    return out


def _unit(unit_id):
    return dict(st.db.execute("SELECT * FROM units WHERE unit_id=?", (unit_id,)).fetchone())


def _meta(u):
    return json.loads(u.get("meta_json") or "{}")


def run_one(unit_id, opts, upload, log):
    u = _unit(unit_id)
    try:
        meta = auto.run_unit(cfg, st, u, None if upload else False, log=log, opts=opts)
        if not meta.get("script_only"):
            st.mark(u["unit_id"], "done", meta=meta)
        return meta
    except Exception as e:
        st.mark(u["unit_id"], "failed", error=str(e)[:500])
        raise


def preview_png(u, opts, width=PREVIEW_W):
    """Sample bullet slide of this chapter in the chosen theme, with the chosen teacher composed on it (PNG bytes)."""
    c = apply_options(cfg, opts)
    meta = _meta(u)
    r = Renderer(c, {"std": u["std"], "subject": u["subject"], "lang": u["lang"]}, {"no": meta.get("tachan_seq"), "title": u["title"]})
    en = u["lang"] == "en"
    av = bool((c.get("avatar") or {}).get("enabled"))
    reserve = avatar.figure_height(c) + 60 if av else 0
    pts = ["Textbook definition, word for word", "Why it is so - one idea per step", "Daily-life example", "Activity for the child"] if en else \
          ["पाठ्यपुस्तकातील व्याख्या - शब्दशः", "असे का? - एका पायरीत एक कल्पना", "रोजच्या जीवनातील उदाहरण", "मुलांसाठी कृती"]
    im = r.points(u["title"], pts, badge="भाग 1" if not en else "Part 1", reserve_right=reserve)
    if av:
        c = dict(c, avatar=dict({"color": r.theme["primary"], "accent": r.theme["accent"]}, **c["avatar"]))
        im = avatar.compose(c, im, "point", 0.3, mouth=0.4)
    im = im.convert("RGB").resize((width, width * 9 // 16))
    buf = io.BytesIO()
    im.save(buf, "PNG")
    return buf.getvalue()


class PartsTab(ttk.Frame):
    """Tick the chapter parts the video should cover; edit heading/text; save to explain.db."""

    def __init__(self, master, app):
        super().__init__(master, padding=8)
        self.app, self.u, self.parts, self.vars, self.cur = app, None, [], [], None
        top = ttk.Frame(self)
        top.pack(fill="x")
        self.lbl = ttk.Label(top, text="घटक निवडा, मग 'भाग लोड करा' (पहिल्यांदा LLM मजकूर स्वच्छ करून भाग बनवते ~30 s)")
        self.lbl.pack(side="left")
        ttk.Button(top, text="📖 भाग लोड करा", command=self.load).pack(side="right")
        body = ttk.Frame(self)
        body.pack(fill="both", expand=True, pady=6)
        left = ttk.Frame(body)
        left.pack(side="left", fill="y")
        ttk.Label(left, text="व्हिडिओमध्ये घ्यायचे भाग (टिक करा):").pack(anchor="w")
        self.lb = tk.Listbox(left, width=40, exportselection=False, font=app.font)
        self.lb.pack(fill="y", expand=True)
        self.lb.bind("<<ListboxSelect>>", self._show)
        bb = ttk.Frame(left)
        bb.pack(fill="x", pady=6)
        ttk.Button(bb, text="टिक / अनटिक", command=self._toggle).pack(side="left")
        ttk.Button(bb, text="सर्व", command=lambda: self._set_all(True)).pack(side="left", padx=4)
        ttk.Button(bb, text="काहीही नाही", command=lambda: self._set_all(False)).pack(side="left")
        right = ttk.Frame(body)
        right.pack(side="left", fill="both", expand=True, padx=(10, 0))
        ttk.Label(right, text="शीर्षक:").pack(anchor="w")
        self.v_head = tk.StringVar()
        ttk.Entry(right, textvariable=self.v_head, font=app.font).pack(anchor="w", fill="x")
        ttk.Label(right, text="मजकूर (पाठ्यपुस्तकातील; OCR चूक असेल तर येथे सुधारा):").pack(anchor="w", pady=(6, 0))
        self.txt = tk.Text(right, wrap="word", font=app.font, undo=True)
        self.txt.pack(fill="both", expand=True)
        b2 = ttk.Frame(right)
        b2.pack(fill="x", pady=6)
        ttk.Button(b2, text="💾 जतन करा", command=self.save).pack(side="left")
        ttk.Button(b2, text="पुन्हा LLM ने भाग बनवा", command=self.rebuild).pack(side="left", padx=8)
        ttk.Label(b2, text="जतन केल्यावर पुढच्या व्हिडिओसाठी आपोआप नवीन स्क्रिप्ट बनते").pack(side="left", padx=8)

    def load(self, force=False):
        u = self.app.selected_unit()
        if not u:
            self.app.log("[text] घटक निवडा")
            return
        self.lbl.config(text=f"⏳ {u['title']} ...")

        def go():
            meta = _meta(u)
            if force:
                st.db.execute("DELETE FROM unit_text WHERE unit_id=?", (u["unit_id"],))
                st.commit()
            parts = textparts.parts_or_build(cfg, st, u, meta, auto.raw_text(u, meta), self.app.log)
            self.app.ui(lambda: self._fill(u, parts))
        threading.Thread(target=go, daemon=True).start()

    def _fill(self, u, parts):
        self.u, self.parts, self.cur = u, parts, None
        self.vars = [bool(p.get("selected", 1)) for p in parts]
        self.lbl.config(text=f"{u['title']} - {len(parts)} भाग" if parts else f"{u['title']} - पाठ्यपुस्तक मजकूर उपलब्ध नाही (स्क्रिप्ट अध्ययन निष्पत्तीवरून बनेल)")
        self._refresh_list()
        self.v_head.set("")
        self.txt.delete("1.0", "end")
        if parts:
            self.lb.selection_set(0)
            self._show()

    def _refresh_list(self):
        self.lb.delete(0, "end")
        for p, on in zip(self.parts, self.vars):
            self.lb.insert("end", f"{'☑' if on else '☐'}  {p['heading'][:38]}")

    def _store_current(self):
        if self.cur is not None and self.cur < len(self.parts):
            self.parts[self.cur]["heading"] = self.v_head.get().strip()
            self.parts[self.cur]["text"] = self.txt.get("1.0", "end").strip()

    def _show(self, *_):
        sel = self.lb.curselection()
        if not sel:
            return
        self._store_current()
        self.cur = sel[0]
        p = self.parts[self.cur]
        self.v_head.set(p["heading"])
        self.txt.delete("1.0", "end")
        self.txt.insert("1.0", p["text"])
        self._refresh_list()
        self.lb.selection_set(self.cur)

    def _toggle(self):
        sel = self.lb.curselection()
        if sel:
            self.vars[sel[0]] = not self.vars[sel[0]]
            self._refresh_list()
            self.lb.selection_set(sel[0])

    def _set_all(self, on):
        self.vars = [on] * len(self.vars)
        self._refresh_list()

    def save(self):
        if not self.u or not self.parts:
            return
        self._store_current()
        for p, on in zip(self.parts, self.vars):
            p["selected"] = 1 if on else 0
        textparts.save_parts(st, self.u["unit_id"], self.parts)
        self.app.log(f"[text] saved {sum(self.vars)}/{len(self.parts)} parts for {self.u['title']}")
        self.lbl.config(text=f"{self.u['title']} - {sum(self.vars)}/{len(self.parts)} भाग निवडले, जतन झाले ✔")

    def rebuild(self):
        if self.app.selected_unit() and messagebox.askyesno("", "सध्याचे भाग व बदल जाऊन LLM ने पुन्हा भाग बनवायचे?"):
            self.load(force=True)


class ScriptTab(ttk.Frame):
    """Edit explain_script.json of the selected chapter in place (say / point texts); saving re-voices on the next run."""

    def __init__(self, master, app):
        super().__init__(master, padding=8)
        self.app, self.path = app, None
        top = ttk.Frame(self)
        top.pack(fill="x")
        self.lbl = ttk.Label(top, text="घटक निवडा -> 'स्क्रिप्ट लोड करा' (नसेल तर 'फक्त स्क्रिप्ट' मोडने ▶ करा)")
        self.lbl.pack(side="left")
        ttk.Button(top, text="💾 जतन करा", command=self.save).pack(side="right")
        ttk.Button(top, text="📝 स्क्रिप्ट लोड करा", command=self.load).pack(side="right", padx=8)
        ttk.Label(self, text="'say' = बोललेला मजकूर, 'point' = स्लाइडवरचा मुद्दा. JSON रचना (कंस, अवतरण, स्वल्पविराम) तशीच ठेवा.").pack(anchor="w", pady=(4, 0))
        self.txt = tk.Text(self, wrap="word", font=app.font, undo=True)
        self.txt.pack(fill="both", expand=True, pady=6)

    def script_path(self, u):
        return os.path.join(auto.out_dir_of(cfg, u, _meta(u)), "explain_script.json")

    def load(self):
        u = self.app.selected_unit()
        if not u:
            self.app.log("[script] घटक निवडा")
            return
        p = self.script_path(u)
        if not os.path.exists(p):
            self.lbl.config(text=f"{u['title']} - स्क्रिप्ट अजून नाही; मोड 'फक्त स्क्रिप्ट' निवडून ▶ करा")
            return
        self.path = p
        self.txt.delete("1.0", "end")
        self.txt.insert("1.0", open(p, encoding="utf-8").read())
        self.lbl.config(text=f"{u['title']} - {p}")

    def save(self):
        if not self.path:
            return
        raw = self.txt.get("1.0", "end").strip()
        try:
            data = json.loads(raw)
        except json.JSONDecodeError as e:
            messagebox.showerror("JSON चूक", f"ओळ {e.lineno}, स्तंभ {e.colno}: {e.msg}")
            self.txt.mark_set("insert", f"{e.lineno}.{max(e.colno - 1, 0)}")
            self.txt.see("insert")
            self.txt.focus_set()
            return
        with open(self.path, "w", encoding="utf-8") as f:
            json.dump(data, f, ensure_ascii=False, indent=1)
        self.app.log(f"[script] saved {self.path} - 'व्हिडिओ' मोडने ▶ केल्यावर बदललेला मजकूर पुन्हा voice होईल")
        self.lbl.config(text=f"जतन झाले ✔  {self.path}")


class QueueTab(ttk.Frame):
    """Chapters queued with their own teacher/theme/mode; rendered one after another."""

    def __init__(self, master, app):
        super().__init__(master, padding=8)
        self.app, self.items, self.running = app, [], False
        ttk.Label(self, text="वर घटक + शिक्षक/थीम/मोड निवडून '➕ रांगेत टाका' करा; मग '▶ रांग सुरू करा' - एकामागून एक व्हिडिओ बनतात.").pack(anchor="w")
        self.lb = tk.Listbox(self, font=app.font, exportselection=False)
        self.lb.pack(fill="both", expand=True, pady=6)
        bb = ttk.Frame(self)
        bb.pack(fill="x")
        self.btn = ttk.Button(bb, text="▶ रांग सुरू करा", command=self.start)
        self.btn.pack(side="left")
        ttk.Button(bb, text="काढा", command=self.remove).pack(side="left", padx=8)
        ttk.Button(bb, text="रांग रिकामी करा", command=self.clear).pack(side="left")
        ttk.Button(bb, text="या विषयाचे न झालेले सर्व घटक टाका", command=self.add_subject).pack(side="left", padx=8)
        self.v_upload = tk.BooleanVar(value=cfg["youtube"]["enabled"])
        ttk.Checkbutton(bb, text="YouTube upload", variable=self.v_upload).pack(side="left", padx=8)

    def _label(self, it):
        names = {v: s for s, v in TEACHERS}
        themes = {v: s for s, v in THEMES}
        return f"{it['state']} इ.{it['std']} {it['subject']} - {it['title']}   [{names.get(it['opts']['avatar'], '')} | {themes.get(it['opts']['theme'], '')} | {it['opts']['mode']}]"

    def _refresh(self):
        self.lb.delete(0, "end")
        for it in self.items:
            self.lb.insert("end", self._label(it))

    def add(self, u, opts):
        if any(it["unit_id"] == u["unit_id"] and it["state"] == "⏳" for it in self.items):
            return
        self.items.append({"unit_id": u["unit_id"], "std": u["std"], "subject": u["subject"], "title": u["title"], "opts": dict(opts), "state": "⏳"})
        self._refresh()
        self.app.log(f"[queue] + {u['title']} ({len([i for i in self.items if i['state'] == '⏳'])} waiting)")

    def add_subject(self):
        u = self.app.selected_unit()
        if not u:
            self.app.log("[queue] प्रथम त्या विषयाचा कोणताही घटक निवडा")
            return
        opts = self.app.opts()
        for r in st.db.execute("SELECT * FROM units WHERE std=? AND subject=? AND status!='done' ORDER BY chapter_no, block", (u["std"], u["subject"])):
            self.add(dict(r), opts)

    def remove(self):
        sel = self.lb.curselection()
        if sel and self.items[sel[0]]["state"] != "▶":
            del self.items[sel[0]]
            self._refresh()

    def clear(self):
        self.items = [it for it in self.items if it["state"] == "▶"]
        self._refresh()

    def start(self):
        if self.running:
            return
        if not any(it["state"] == "⏳" for it in self.items):
            self.app.log("[queue] रांग रिकामी")
            return
        self.running = True
        self.btn.config(state="disabled")
        upload = self.v_upload.get()

        def go():
            try:
                while True:
                    it = next((i for i in self.items if i["state"] == "⏳"), None)
                    if not it:
                        break
                    it["state"] = "▶"
                    self.app.ui(self._refresh)
                    self.app.log(f"\n━━━ [queue] {it['title']} ━━━")
                    try:
                        meta = run_one(it["unit_id"], it["opts"], upload, self.app.log) or {}
                        it["state"] = "✔"
                        self.app.log(f"✔ {meta.get('video')}  {meta.get('youtube_url') or ''}")
                    except Exception:
                        import traceback
                        it["state"] = "✖"
                        self.app.log("✖ " + traceback.format_exc())
                    self.app.ui(self._refresh)
                self.app.log("[queue] रांग पूर्ण")
            finally:
                self.running = False
                self.app.ui(lambda: self.btn.config(state="normal"))
                self.app.ui(self.app.refresh_rows)
        threading.Thread(target=go, daemon=True).start()


class App(tk.Tk):
    def __init__(self):
        super().__init__()
        self.title("घटक स्पष्टीकरण व्हिडिओ — इयत्ता 1-8")
        self.geometry("1500x900")
        self.minsize(1200, 760)
        self.font = self._fonts()
        self.q = queue.Queue()
        self.all = rows()
        self.boxes, self.extra_vars, self.extra_maps = {}, {}, {}
        self._preview_job, self._preview_key = None, None
        self._build()
        self.after(200, self._poll)

    def _fonts(self):
        fams = set(tkfont.families())
        fam = next((f for f in ("Nirmala UI", "Mangal", "Noto Sans Devanagari", "Lohit Devanagari") if f in fams), "TkDefaultFont")
        for name in ("TkDefaultFont", "TkTextFont", "TkMenuFont", "TkHeadingFont", "TkFixedFont"):
            f = tkfont.nametofont(name)
            f.configure(size=FONT_SIZE, **({"family": fam} if fam != "TkDefaultFont" else {}))
        self.option_add("*TCombobox*Listbox.font", tkfont.nametofont("TkDefaultFont"))
        s = ttk.Style(self)
        s.configure(".", font=tkfont.nametofont("TkDefaultFont"))
        s.configure("TNotebook.Tab", padding=(14, 6))
        s.configure("Big.TButton", padding=(12, 8))
        return tkfont.nametofont("TkTextFont")

    def _build(self):
        f = ttk.Frame(self, padding=10)
        f.pack(fill="x")
        for i, (label, key) in enumerate([("इयत्ता", "std"), ("विषय", "subj"), ("घटक", "topic")]):
            ttk.Label(f, text=label).grid(row=i, column=0, sticky="w", pady=4)
            cb = ttk.Combobox(f, width=70 if i == 2 else 34, state="readonly")
            cb.grid(row=i, column=1, padx=6, sticky="w", columnspan=3)
            cb.bind("<<ComboboxSelected>>", lambda e, i=i: self._cascade(i + 1))
            self.boxes[key] = cb
        self.filters = [("इयत्ता", "std"), ("विषय", "subj"), ("घटक", "topic")]
        col = 4
        for label, key, default, choices in [("शिक्षक", "avatar", _avatar_default(), TEACHERS),
                                             ("स्लाइड थीम", "theme", cfg.get("theme") or "auto", THEMES), ("मोड", "mode", "video", MODES)]:
            r = ["avatar", "theme", "mode"].index(key)
            ttk.Label(f, text=label).grid(row=r, column=col, sticky="w", padx=(30, 4), pady=4)
            shown = [s for s, _ in choices]
            self.extra_maps[key] = dict(choices)
            v = tk.StringVar(value=next((s for s, val in choices if val == default), shown[0]))
            cb = ttk.Combobox(f, textvariable=v, values=shown, state="readonly", width=40)
            cb.grid(row=r, column=col + 1, sticky="w")
            cb.bind("<<ComboboxSelected>>", lambda e: self._schedule_preview())
            self.extra_vars[key] = v
        self.v_upload = tk.BooleanVar(value=cfg["youtube"]["enabled"])
        ttk.Checkbutton(f, text="YouTube upload", variable=self.v_upload).grid(row=3, column=col + 1, sticky="w", pady=4)
        b = ttk.Frame(self, padding=(10, 0))
        b.pack(fill="x")
        self.btn = ttk.Button(b, text="▶ व्हिडिओ तयार करा", style="Big.TButton", command=self._make)
        self.btn.pack(side="left")
        ttk.Button(b, text="➕ रांगेत टाका", style="Big.TButton", command=self._enqueue).pack(side="left", padx=8)
        ttk.Button(b, text="Output फोल्डर", command=lambda: os.startfile(cfg["out_dir"]) if hasattr(os, "startfile") else None).pack(side="left", padx=8)
        ttk.Button(b, text="Google Flow (animation clip)", command=self._flow).pack(side="left", padx=8)
        body = ttk.Frame(self, padding=10)
        body.pack(fill="both", expand=True)
        left = ttk.Frame(body)
        left.pack(side="left", fill="y", padx=(0, 10))
        ttk.Label(left, text="पूर्वावलोकन (निवडलेली थीम + शिक्षक):").pack(anchor="w")
        self.preview = tk.Label(left, width=PREVIEW_W, height=PREVIEW_W * 9 // 16, bg="#dde3ea", text="घटक निवडा", anchor="center")
        self.preview.pack()
        self.preview_note = ttk.Label(left, text="", wraplength=PREVIEW_W, justify="left")
        self.preview_note.pack(anchor="w", pady=(6, 0))
        self.nb = ttk.Notebook(body)
        self.nb.pack(side="left", fill="both", expand=True)
        logf = ttk.Frame(self.nb)
        self.logbox = tk.Text(logf, wrap="word", font=self.font)
        self.logbox.pack(fill="both", expand=True)
        self.parts_tab, self.script_tab, self.queue_tab = PartsTab(self.nb, self), ScriptTab(self.nb, self), QueueTab(self.nb, self)
        for w, name in [(logf, "लॉग"), (self.parts_tab, "📖 पाठ मजकूर / भाग"), (self.script_tab, "📝 स्क्रिप्ट"), (self.queue_tab, "📋 रांग")]:
            self.nb.add(w, text=name)
        self._cascade(0)

    # ---- selection -------------------------------------------------------------------------------
    def _filtered(self, upto):
        rs = self.all
        for label, key in self.filters[:upto]:
            v = self.boxes[key].get()
            rs = [r for r in rs if str(r.get(key, "")) == v]
        return rs

    def _cascade(self, i):
        if i >= len(self.filters):
            self._schedule_preview()
            return
        key = self.filters[i][1]
        rs = self._filtered(i)
        vals = []
        for r in rs:
            v = str(r.get(key, ""))
            if v not in vals:
                vals.append(v)
        if i == len(self.filters) - 1:
            done = {str(r.get(key, "")) for r in rs if r.get("status") == "done"}
            self.boxes[key]["values"] = [v + ("   ✔" if v in done else "") for v in vals]
        else:
            self.boxes[key]["values"] = vals
        self.boxes[key].set("")
        for label, k in self.filters[i + 1:]:
            self.boxes[k]["values"] = []
            self.boxes[k].set("")

    def _selected_row(self):
        rs = self.all
        for label, key in self.filters:
            v = self.boxes[key].get().replace("   ✔", "")
            if not v:
                return None
            rs = [r for r in rs if str(r.get(key, "")) == v]
        return rs[0] if rs else None

    def selected_unit(self):
        r = self._selected_row()
        return _unit(r["unit_id"]) if r else None

    def opts(self):
        return {k: self.extra_maps[k].get(v.get(), v.get()) for k, v in self.extra_vars.items()}

    def refresh_rows(self):
        keep = {k: b.get() for k, b in self.boxes.items()}
        self.all = rows()
        for i, (label, key) in enumerate(self.filters):
            self._cascade(i)
            self.boxes[key].set(keep[key])
        self._schedule_preview()

    # ---- preview ---------------------------------------------------------------------------------
    def _schedule_preview(self):
        if self._preview_job:
            self.after_cancel(self._preview_job)
        self._preview_job = self.after(250, self._render_preview)

    def _render_preview(self):
        self._preview_job = None
        u = self.selected_unit()
        if not u:
            return
        opts = self.opts()
        key = (u["unit_id"], opts["avatar"], opts["theme"])
        if key == self._preview_key:
            return
        self._preview_key = key
        self.preview_note.config(text="⏳ ...")

        def go():
            try:
                png = preview_png(u, opts)
            except Exception as e:
                msg = f"preview: {e}"
                self.ui(lambda: self.preview_note.config(text=msg))
                return
            if self._preview_key != key:
                return

            def show():
                self._photo = tk.PhotoImage(data=png)
                self.preview.config(image=self._photo, text="", width=self._photo.width(), height=self._photo.height())
                sp = self.script_tab.script_path(u)
                parts = textparts.get_parts(st, u["unit_id"])
                note = f"इ.{u['std']} {u['subject']} - {u['title']}\n"
                note += f"भाग: {sum(1 for p in parts if p['selected'])}/{len(parts)} निवडले" if parts else "भाग: अजून बनवले नाहीत ('पाठ मजकूर' टॅब)"
                note += "   |   स्क्रिप्ट: " + ("जतन आहे ✔" if os.path.exists(sp) else "नाही")
                self.preview_note.config(text=note)
            self.ui(show)
        threading.Thread(target=go, daemon=True).start()

    # ---- actions ---------------------------------------------------------------------------------
    def log(self, s):
        self.q.put(str(s))

    def ui(self, fn):
        """Run fn on the Tk thread (worker threads must not touch widgets directly)."""
        self.q.put(fn)

    def _poll(self):
        while not self.q.empty():
            item = self.q.get()
            if callable(item):
                item()
            else:
                self.logbox.insert("end", item + "\n")
                self.logbox.see("end")
        self.after(200, self._poll)

    def _flow(self):
        from ytstudio import veo
        prompts = sorted(glob.glob(os.path.join(cfg["out_dir"], "*", "flow_prompt.txt")), key=os.path.getmtime)
        text = open(prompts[-1], encoding="utf-8").read().split("\n\nSave the clip")[0] if prompts else ""
        veo.open_flow(text)
        self.log(f"[flow] prompt copied to clipboard{' from ' + os.path.basename(os.path.dirname(prompts[-1])) if prompts else ' (none yet - make a video first)'};"
                 " paste it in Flow, download the clip as intro.mp4 into that folder, delete video.mp4 and remake")

    def _enqueue(self):
        u = self.selected_unit()
        if not u:
            messagebox.showwarning("", "सर्व पर्याय निवडा")
            return
        self.queue_tab.add(u, self.opts())
        self.nb.select(self.queue_tab)

    def _make(self):
        u = self.selected_unit()
        if not u:
            messagebox.showwarning("", "सर्व पर्याय निवडा")
            return
        opts, upload = self.opts(), self.v_upload.get()
        self.btn.config(state="disabled")
        self.nb.select(0)

        def go():
            try:
                meta = run_one(u["unit_id"], opts, upload, self.log) or {}
                self.log(f"\n✔ {meta.get('video') or 'done'}\n{meta.get('youtube_url') or ''} {meta.get('short_url') or ''}")
                if meta.get("script_only"):
                    self.ui(lambda: (self.script_tab.load(), self.nb.select(self.script_tab)))
            except Exception:
                import traceback
                self.log("\n✖ " + traceback.format_exc())
            finally:
                self.ui(lambda: self.btn.config(state="normal"))
                self.ui(self.refresh_rows)
        threading.Thread(target=go, daemon=True).start()


if __name__ == "__main__":
    App().mainloop()
