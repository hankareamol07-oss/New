#!/usr/bin/env python3
"""GUI / .exe entry: इयत्ता -> विषय -> घटक -> explanation video + Short (+ YouTube). Uses explain.db like auto.py.
Per-video choices: teacher (शिक्षिका / शिक्षक / none), slide theme, mode (video / script only / new script), and the
"पाठ मजकूर" button to tick / edit which parts of the chapter the video covers (stored in explain.db)."""
import json
import threading
import tkinter as tk
from tkinter import messagebox, ttk

import auto
import textparts
from yt_common.gui import Adapter, main

cfg = auto.load_cfg()
st = auto.open_state(cfg)

TEACHERS = [("शिक्षिका (teacher2)", "teacher2"), ("शिक्षक (teacher3)", "teacher3"), ("अवतार नाही (फक्त स्लाइड)", "none")]
THEMES = [("विषयानुसार (auto)", "auto"), ("गणित - निळा grid", "math"), ("विज्ञान - हिरवा lab", "science"),
          ("मराठी - warm paper", "marathi"), ("हिंदी", "hindi"), ("English - classic", "english"), ("इतिहास - parchment", "history"),
          ("भूगोल - map", "geography"), ("नागरिकशास्त्र", "civics"), ("परिसर अभ्यास", "evs"), ("संस्कृत", "sanskrit"),
          ("साधी (general)", "general"), ("channel रंग (brand)", "brand")]
MODES = [("व्हिडिओ (जतन केलेली स्क्रिप्ट असेल तर तीच)", "video"), ("फक्त स्क्रिप्ट - Notepad मध्ये बदलण्यासाठी", "script"),
         ("नवीन स्क्रिप्ट + व्हिडिओ", "new")]


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


def _unit(row):
    return dict(st.db.execute("SELECT * FROM units WHERE unit_id=?", (row["unit_id"],)).fetchone())


def run(row, upload, log):
    u = _unit(row)
    opts = {"avatar": row.get("avatar"), "theme": row.get("theme"), "mode": row.get("mode")}
    try:
        meta = auto.run_unit(cfg, st, u, None if upload else False, log=log, opts=opts)
        if not meta.get("script_only"):
            st.mark(u["unit_id"], "done", meta=meta)
        return meta
    except Exception as e:
        st.mark(u["unit_id"], "failed", error=str(e)[:500])
        raise


class PartsWindow(tk.Toplevel):
    """Tick the chapter parts the video should cover; edit heading/text; save to explain.db."""

    def __init__(self, app, u, parts, log):
        super().__init__(app)
        self.u, self.parts, self.log = u, parts, log
        self.title(f"पाठ मजकूर - {u['title']}")
        self.geometry("1100x700")
        left = ttk.Frame(self, padding=8)
        left.pack(side="left", fill="y")
        ttk.Label(left, text="व्हिडिओमध्ये घ्यायचे भाग (टिक करा):").pack(anchor="w")
        self.vars, self.lb = [], tk.Listbox(left, width=42, height=30, exportselection=False)
        self.lb.pack(fill="y", expand=True)
        self.lb.bind("<<ListboxSelect>>", self._show)
        for p in parts:
            self.vars.append(bool(p.get("selected", 1)))
        self._refresh_list()
        bb = ttk.Frame(left)
        bb.pack(fill="x", pady=6)
        ttk.Button(bb, text="टिक / अनटिक", command=self._toggle).pack(side="left")
        ttk.Button(bb, text="सर्व", command=lambda: self._set_all(True)).pack(side="left", padx=4)
        ttk.Button(bb, text="काहीही नाही", command=lambda: self._set_all(False)).pack(side="left")
        right = ttk.Frame(self, padding=8)
        right.pack(side="left", fill="both", expand=True)
        ttk.Label(right, text="शीर्षक:").pack(anchor="w")
        self.v_head = tk.StringVar()
        ttk.Entry(right, textvariable=self.v_head, width=80).pack(anchor="w", fill="x")
        ttk.Label(right, text="मजकूर (पाठ्यपुस्तकातील; बदल करू शकता):").pack(anchor="w", pady=(6, 0))
        self.txt = tk.Text(right, wrap="word", height=28)
        self.txt.pack(fill="both", expand=True)
        b2 = ttk.Frame(right)
        b2.pack(fill="x", pady=6)
        ttk.Button(b2, text="💾 जतन करा", command=self._save).pack(side="left")
        ttk.Button(b2, text="पुन्हा LLM ने भाग बनवा", command=self._rebuild).pack(side="left", padx=8)
        ttk.Label(b2, text="जतन केल्यावर 'नवीन स्क्रिप्ट + व्हिडिओ' मोडने व्हिडिओ बनवा").pack(side="left", padx=8)
        self.cur = None
        if parts:
            self.lb.selection_set(0)
            self._show()

    def _refresh_list(self):
        self.lb.delete(0, "end")
        for p, on in zip(self.parts, self.vars):
            self.lb.insert("end", f"{'☑' if on else '☐'}  {p['heading'][:38]}")

    def _store_current(self):
        if self.cur is not None:
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

    def _save(self):
        self._store_current()
        for p, on in zip(self.parts, self.vars):
            p["selected"] = 1 if on else 0
        textparts.save_parts(st, self.u["unit_id"], self.parts)
        self.log(f"[text] saved {sum(self.vars)}/{len(self.parts)} parts for {self.u['title']}")
        messagebox.showinfo("", f"{sum(self.vars)}/{len(self.parts)} भाग निवडले - जतन झाले")

    def _rebuild(self):
        if not messagebox.askyesno("", "सध्याचे भाग व बदल जाऊन LLM ने पुन्हा भाग बनवायचे?"):
            return
        st.db.execute("DELETE FROM unit_text WHERE unit_id=?", (self.u["unit_id"],))
        st.commit()
        app = self.master
        self.destroy()
        threading.Thread(target=open_parts, args=({"unit_id": self.u["unit_id"]}, self.log, app), daemon=True).start()


def open_parts(row, log, app):
    """Button: build (LLM, first time) and show the chapter's parts for the selected topic."""
    if not row:
        log("[text] घटक निवडा")
        return
    u = _unit(row)
    meta = json.loads(u.get("meta_json") or "{}")
    parts = textparts.parts_or_build(cfg, st, u, meta, auto.raw_text(u, meta), log)
    if not parts:
        log("[text] या घटकाचा पाठ्यपुस्तक मजकूर उपलब्ध नाही (स्क्रिप्ट अध्ययन निष्पत्तीवरून बनेल)")
        return
    log(f"[text] {len(parts)} parts")
    app.after(0, lambda: PartsWindow(app, u, parts, log))


if __name__ == "__main__":
    main(Adapter("घटक स्पष्टीकरण व्हिडिओ — इयत्ता 1-8", [("इयत्ता", "std"), ("विषय", "subj"), ("घटक", "topic")],
                 rows, run, upload_default=cfg["youtube"]["enabled"],
                 extras=[("शिक्षक", "avatar", _avatar_default(), TEACHERS), ("स्लाइड थीम", "theme", cfg.get("theme") or "auto", THEMES),
                         ("मोड", "mode", "video", MODES)],
                 buttons=[("📖 पाठ मजकूर / भाग निवडा", open_parts)]), cfg["out_dir"])
