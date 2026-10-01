#!/usr/bin/env python3
"""Tiny desktop GUI (Tkinter, no extra install): pick std / book / chapter -> Make video (-> Descript -> YouTube)."""
import os
import queue
import threading
import tkinter as tk
from tkinter import filedialog, font as tkfont, messagebox, simpledialog, ttk

from ytstudio import config, pipeline
from ytstudio.data import Bank


class App(tk.Tk):
    def __init__(self):
        super().__init__()
        self.title("YT Studio — इयत्ता 1–8 व्हिडिओ")
        self.geometry("900x640")
        self._fonts()
        self.cfg = config.load()
        self.bank = Bank(self.cfg["data_dir"])
        self.q = queue.Queue()
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
        ttk.Label(f, text="इयत्ता").grid(row=0, column=0, sticky="w")
        self.std = ttk.Combobox(f, values=self.bank.standards(), width=6, state="readonly")
        self.std.grid(row=0, column=1, padx=5)
        self.std.bind("<<ComboboxSelected>>", self._books)
        ttk.Label(f, text="पुस्तक").grid(row=0, column=2, sticky="w")
        self.book = ttk.Combobox(f, width=48, state="readonly")
        self.book.grid(row=0, column=3, padx=5)
        self.book.bind("<<ComboboxSelected>>", self._chapters)
        ttk.Label(f, text="पाठ").grid(row=1, column=0, sticky="w", pady=6)
        self.chapter = ttk.Combobox(f, width=70, state="readonly")
        self.chapter.grid(row=1, column=1, columnspan=3, padx=5, sticky="w")
        ttk.Label(f, text="प्रश्न स्रोत").grid(row=2, column=0, sticky="w")
        self.source = ttk.Combobox(f, values=["both", "book", "typed"], width=8, state="readonly")
        self.source.set("both")
        self.source.grid(row=2, column=1, padx=5, sticky="w")
        self.v_descript = tk.BooleanVar(value=self.cfg["descript"]["enabled"])
        self.v_upload = tk.BooleanVar(value=self.cfg["youtube"]["enabled"])
        ttk.Checkbutton(f, text="Descript polish (credits)", variable=self.v_descript).grid(row=2, column=2, sticky="w")
        ttk.Checkbutton(f, text="YouTube upload", variable=self.v_upload).grid(row=2, column=3, sticky="w")
        b = ttk.Frame(self, padding=(10, 0))
        b.pack(fill="x")
        self.btn = ttk.Button(b, text="▶ व्हिडिओ तयार करा", command=self._make)
        self.btn.pack(side="left")
        ttk.Button(b, text="PDF/TXT पाठ (नवीन अभ्यासक्रम)…", command=self._make_file).pack(side="left", padx=8)
        ttk.Button(b, text="Output फोल्डर", command=lambda: os.startfile(self.cfg["out_dir"]) if hasattr(os, "startfile") else None).pack(side="left")
        self.log = tk.Text(self, height=26, wrap="word")
        self.log.pack(fill="both", expand=True, padx=10, pady=10)

    def _books(self, *_):
        std = int(self.std.get())
        self._bl = self.bank.book_list(std)
        self.book["values"] = [f"{b['book_id']} | {b['subject']} ({b['lang']}) — {b['title']}" for b in self._bl]
        self.book.set("")
        self.chapter.set("")

    def _chapters(self, *_):
        bid = int(self.book.get().split("|")[0])
        self._ch = self.bank.chapters(bid)
        self.chapter["values"] = [f"{c['no']} | {c['title']} ({len(self.bank.chapter_questions(bid, c['no']))} q)" for c in self._ch]

    def _log(self, s):
        self.q.put(str(s))

    def _poll(self):
        while not self.q.empty():
            self.log.insert("end", self.q.get() + "\n")
            self.log.see("end")
        self.after(200, self._poll)

    def _bg(self, fn):
        self.btn.config(state="disabled")

        def go():
            try:
                meta = fn()
                self._log(f"\n✔ {meta['video']}\n{meta.get('youtube_url', '')}")
            except Exception as e:
                import traceback
                self._log("\n✖ " + traceback.format_exc())
            finally:
                self.q.put("")
                self.after(0, lambda: self.btn.config(state="normal"))

        threading.Thread(target=go, daemon=True).start()

    def _make(self):
        if not self.chapter.get():
            messagebox.showwarning("", "पाठ निवडा")
            return
        bid = int(self.book.get().split("|")[0])
        cno = int(self.chapter.get().split("|")[0])
        self._bg(lambda: pipeline.run_bank(self.cfg, bid, cno, self.source.get(), log=self._log,
                                           upload=self.v_upload.get(), use_descript=self.v_descript.get()))

    def _make_file(self):
        path = filedialog.askopenfilename(filetypes=[("PDF / text", "*.pdf *.txt")])
        if not path:
            return
        std = int(self.std.get() or 6)
        subj = simpledialog.askstring("विषय", "Subject (Marathi/Hindi/English/Maths/Science/EVS/History/Geography/Civics):") or "Science"
        lang = simpledialog.askstring("भाषा", "Language code mr / hi / en:") or "mr"
        title = simpledialog.askstring("पाठ", "Chapter title:") or os.path.basename(path)
        no = simpledialog.askinteger("पाठ क्र.", "Chapter number:", initialvalue=1, minvalue=0) or 0
        self._bg(lambda: pipeline.run_file(self.cfg, path, std, subj, lang, title, no=no, log=self._log,
                                           upload=self.v_upload.get(), use_descript=self.v_descript.get()))


if __name__ == "__main__":
    App().mainloop()
