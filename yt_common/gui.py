"""Shared Tkinter GUI for the three YouTube projects (also the entry point of their PyInstaller .exe).

A project passes an `Adapter`:
  title            window title
  filters          [(label, key)] cascading combobox columns read from the unit rows (e.g. std -> subject -> title)
  rows()           list of dict rows (must contain the filter keys, 'unit_id', 'status')
  run(row, upload, log) -> meta dict with 'video' (+ 'youtube_url' / 'short_url')
  extras           optional [(label, key, default)] free inputs (e.g. how many Shorts) or
                   (label, key, default, [(shown, value), ...]) dropdown choices; the chosen value lands in row[key]
  buttons          optional [(label, fn(row, log, app))] project buttons (row = selected unit or None), run in a thread;
                   use app.after(0, ...) to open Tk windows
"""
import glob
import os
import queue
import threading
import tkinter as tk
from tkinter import font as tkfont, messagebox, ttk


def project_root():
    """Folder of the project (config.json, *.db, output): exe folder when frozen, else caller's dir via env."""
    import sys
    if getattr(sys, "frozen", False):
        return os.path.dirname(os.path.abspath(sys.executable))
    return os.path.abspath(os.environ.get("YT_PROJECT_ROOT", os.getcwd()))


class Adapter:
    def __init__(self, title, filters, rows, run, extras=None, upload_default=True, buttons=None):
        self.title, self.filters, self.rows, self.run, self.extras, self.upload_default = title, filters, rows, run, extras or [], upload_default
        self.buttons = buttons or []


class App(tk.Tk):
    def __init__(self, ad, out_dir):
        super().__init__()
        self.ad, self.out_dir = ad, out_dir
        self.title(ad.title)
        self.geometry("960x660")
        self._fonts()
        self.q = queue.Queue()
        self.all = ad.rows()
        self.boxes = {}
        self._build()
        self.after(200, self._poll)

    def _fonts(self):
        fams = set(tkfont.families())
        fam = next((f for f in ("Nirmala UI", "Mangal", "Noto Sans Devanagari", "Lohit Devanagari") if f in fams), None)
        if not fam:
            return
        for name in ("TkDefaultFont", "TkTextFont", "TkMenuFont", "TkHeadingFont", "TkFixedFont"):
            tkfont.nametofont(name).configure(family=fam, size=11)
        self.option_add("*TCombobox*Listbox.font", tkfont.nametofont("TkDefaultFont"))
        ttk.Style(self).configure(".", font=(fam, 11))

    def _build(self):
        f = ttk.Frame(self, padding=10)
        f.pack(fill="x")
        for i, (label, key) in enumerate(self.ad.filters):
            ttk.Label(f, text=label).grid(row=i, column=0, sticky="w", pady=4)
            cb = ttk.Combobox(f, width=90 if i == len(self.ad.filters) - 1 else 40, state="readonly")
            cb.grid(row=i, column=1, padx=6, sticky="w")
            cb.bind("<<ComboboxSelected>>", lambda e, i=i: self._cascade(i + 1))
            self.boxes[key] = cb
        r = len(self.ad.filters)
        self.extra_vars, self.extra_maps = {}, {}
        for ex in self.ad.extras:
            label, key, default = ex[:3]
            choices = ex[3] if len(ex) > 3 else None
            ttk.Label(f, text=label).grid(row=r, column=0, sticky="w", pady=4)
            if choices:
                shown = [s for s, _ in choices]
                self.extra_maps[key] = dict(choices)
                v = tk.StringVar(value=next((s for s, val in choices if val == default), shown[0]))
                ttk.Combobox(f, textvariable=v, values=shown, state="readonly", width=40).grid(row=r, column=1, sticky="w", padx=6)
            else:
                v = tk.StringVar(value=str(default))
                ttk.Entry(f, textvariable=v, width=12).grid(row=r, column=1, sticky="w", padx=6)
            self.extra_vars[key] = v
            r += 1
        self.v_upload = tk.BooleanVar(value=self.ad.upload_default)
        ttk.Checkbutton(f, text="YouTube upload", variable=self.v_upload).grid(row=r, column=1, sticky="w", padx=6)
        b = ttk.Frame(self, padding=(10, 0))
        b.pack(fill="x")
        self.btn = ttk.Button(b, text="▶ व्हिडिओ तयार करा", command=self._make)
        self.btn.pack(side="left")
        ttk.Button(b, text="Output फोल्डर", command=lambda: os.startfile(self.out_dir) if hasattr(os, "startfile") else None).pack(side="left", padx=8)
        ttk.Button(b, text="Google Flow (animation clip)", command=self._flow).pack(side="left", padx=8)
        for label, fn in self.ad.buttons:
            ttk.Button(b, text=label, command=lambda fn=fn: self._project_button(fn)).pack(side="left", padx=8)
        self.log = tk.Text(self, height=26, wrap="word")
        self.log.pack(fill="both", expand=True, padx=10, pady=10)
        self._cascade(0)

    def _filtered(self, upto):
        rows = self.all
        for label, key in self.ad.filters[:upto]:
            v = self.boxes[key].get()
            rows = [r for r in rows if str(r.get(key, "")) == v]
        return rows

    def _cascade(self, i):
        if i >= len(self.ad.filters):
            return
        key = self.ad.filters[i][1]
        rows = self._filtered(i)
        vals = []
        for r in rows:
            v = str(r.get(key, ""))
            if v not in vals:
                vals.append(v)
        if i == len(self.ad.filters) - 1:  # last column: show status
            done = {str(r.get(key, "")) for r in rows if r.get("status") == "done"}
            self.boxes[key]["values"] = [v + ("   ✔" if v in done else "") for v in vals]
        else:
            self.boxes[key]["values"] = vals
        self.boxes[key].set("")
        for label, k in self.ad.filters[i + 1:]:
            self.boxes[k]["values"] = []
            self.boxes[k].set("")

    def _selected(self):
        rows = self.all
        for label, key in self.ad.filters:
            v = self.boxes[key].get().replace("   ✔", "")
            if not v:
                return None
            rows = [r for r in rows if str(r.get(key, "")) == v]
        return rows[0] if rows else None

    def _row_with_extras(self, row):
        row = dict(row)
        for k, v in self.extra_vars.items():
            row[k] = self.extra_maps[k].get(v.get(), v.get()) if k in self.extra_maps else v.get()
        return row

    def _project_button(self, fn):
        row = self._selected() if self.ad.filters else {}
        row = self._row_with_extras(row) if row is not None else None

        def go():
            try:
                fn(row, self._log, self)
            except Exception:
                import traceback
                self._log("\n✖ " + traceback.format_exc())
        threading.Thread(target=go, daemon=True).start()

    def _log(self, s):
        self.q.put(str(s))

    def _poll(self):
        while not self.q.empty():
            self.log.insert("end", self.q.get() + "\n")
            self.log.see("end")
        self.after(200, self._poll)

    def _flow(self):
        """Open Google Flow with this video's Veo prompt (flow_prompt.txt of the newest output folder) on the clipboard."""
        from ytstudio import veo
        prompts = sorted(glob.glob(os.path.join(self.out_dir, "*", "flow_prompt.txt")), key=os.path.getmtime)
        text = open(prompts[-1], encoding="utf-8").read().split("\n\nSave the clip")[0] if prompts else ""
        veo.open_flow(text)
        self._log(f"[flow] prompt copied to clipboard{' from ' + os.path.basename(os.path.dirname(prompts[-1])) if prompts else ' (none yet - make a video first)'};"
                  " paste it in Flow, download the clip as intro.mp4 (bg.mp4 for motivation) into that folder, delete video.mp4/short.mp4 and remake")

    def _make(self):
        row = self._selected() if self.ad.filters else {}
        if row is None:
            messagebox.showwarning("", "सर्व पर्याय निवडा")
            return
        row = self._row_with_extras(row)
        self.btn.config(state="disabled")

        def go():
            try:
                meta = self.ad.run(row, self.v_upload.get(), self._log) or {}
                self._log(f"\n✔ {meta.get('video') or meta.get('short') or 'done'}\n{meta.get('youtube_url') or ''} {meta.get('short_url') or ''}")
            except Exception as e:
                import traceback
                self._log("\n✖ " + traceback.format_exc())
            finally:
                self.after(0, lambda: self.btn.config(state="normal"))

        threading.Thread(target=go, daemon=True).start()


def main(adapter, out_dir):
    App(adapter, out_dir).mainloop()
