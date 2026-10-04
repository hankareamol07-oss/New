"""Tkinter GUI: instruction box -> Plan -> (edit) -> Run; live log, cost, stop button, stats, open folders."""
import json, os, subprocess, sys, threading, queue
import tkinter as tk
from tkinter import ttk, scrolledtext, messagebox
from .agent import Agent, TOOLS
from .settings import SettingsTab

FONT = ("Nirmala UI", 12) if sys.platform.startswith("win") else ("Noto Sans Devanagari", 12)
EXAMPLES = [
    "input फोल्डरमधील सर्व PDF जोडा, OCR करा, धडे शोधा, भाग व चित्रे काढा, प्रत्येक धड्याचा स्वाध्याय + प्रत्येक विषयावर 12-15 MCQ (Bloom) बनवा आणि exam_paper साठी export करा",
    "Add std 7 Science (English medium) PDF 7_science.pdf, OCR pages 1-40, detect chapters and build parts only",
    "For book 2 make question bank with 10 MCQs per topic without picture questions, then export",
    "प्रत्येक धड्याची टिपणे (notes) बनवा आणि Word फाईल द्या; MCQ चा Excel (प्रश्न, 4 पर्याय, उत्तर, स्पष्टीकरण) बनवा",
    "For book 1 make detailed notes, then export Excel quiz workbook and Word notes",
    "input मधील scholarship_paper.pdf हा प्रश्नपत्रिका PDF जोडा (std 5), paper म्हणून वाचा – उतारा, प्रश्न, चित्र पर्याय – आणि Excel द्या",
    "Read nmms_2024.pdf as a competitive question paper (std 8): passages, questions, image options, answer key; export Excel",
    "stats",
]


class App(tk.Tk):
    def __init__(self, workdir):
        super().__init__()
        self.title("PDF → Textbook Database Agent")
        self.geometry("1150x780")
        self.workdir = workdir
        self.q = queue.Queue()
        self.ag = None
        self.steps = []
        top = ttk.Frame(self, padding=8)
        top.pack(fill="x")
        ttk.Label(top, text="सूचना / Instruction:", font=FONT).pack(anchor="w")
        self.instr = scrolledtext.ScrolledText(top, height=4, font=FONT, wrap="word")
        self.instr.pack(fill="x")
        self.instr.insert("1.0", EXAMPLES[0])
        row = ttk.Frame(top)
        row.pack(fill="x", pady=4)
        ttk.Button(row, text="📋 Plan", command=self.do_plan).pack(side="left")
        self.run_btn = ttk.Button(row, text="▶ Run plan", command=self.do_run, state="disabled")
        self.run_btn.pack(side="left", padx=4)
        ttk.Button(row, text="⏹ Stop", command=self.do_stop).pack(side="left")
        ttk.Button(row, text="📊 Stats", command=lambda: self.bg(lambda: self.ag.t_stats())).pack(side="left", padx=4)
        ttk.Button(row, text="📁 input", command=lambda: self.open(os.path.join(workdir, "input"))).pack(side="left")
        ttk.Button(row, text="📁 export", command=lambda: self.open(os.path.join(workdir, "export"))).pack(side="left", padx=4)
        ttk.Button(row, text="⚙ config.json", command=lambda: self.open(os.path.join(workdir, "config.json"))).pack(side="left")
        ex = ttk.Combobox(row, values=EXAMPLES, width=60, state="readonly")
        ex.pack(side="right")
        ex.bind("<<ComboboxSelected>>", lambda e: (self.instr.delete("1.0", "end"), self.instr.insert("1.0", ex.get())))
        self.cost = ttk.Label(row, text="cost: $0.00", font=FONT)
        self.cost.pack(side="right", padx=10)
        nb = ttk.Notebook(self)
        nb.pack(fill="both", expand=True, padx=8, pady=4)
        work = ttk.Frame(nb)
        nb.add(work, text="  ▶ Work  ")
        nb.add(SettingsTab(nb, workdir, FONT, lambda: self.bg(self.init_agent)), text="  ⚙ Settings / API keys  ")
        pan = ttk.Panedwindow(work, orient="vertical")
        pan.pack(fill="both", expand=True)
        f1 = ttk.Labelframe(pan, text="Plan (editable JSON)")
        self.plan = scrolledtext.ScrolledText(f1, height=10, font=("Consolas", 11), wrap="word")
        self.plan.pack(fill="both", expand=True)
        pan.add(f1, weight=1)
        f2 = ttk.Labelframe(pan, text="Log")
        self.logbox = scrolledtext.ScrolledText(f2, font=("Consolas", 10), wrap="word", state="disabled")
        self.logbox.pack(fill="both", expand=True)
        pan.add(f2, weight=2)
        self.after(200, self.pump)
        self.bg(self.init_agent)

    def init_agent(self):
        try:
            self.ag = Agent(self.workdir, self.log)
            self.log(f"workdir: {self.workdir}\nTools: " + ", ".join(TOOLS) + "\nPut PDFs in input\\ , write an instruction, click Plan.")
        except Exception as e:  # noqa: BLE001
            self.log(f"config error: {e}\nOpen the Settings tab, enter your API keys and click Save.")

    def log(self, *a):
        self.q.put(" ".join(str(x) for x in a))

    def pump(self):
        try:
            while True:
                s = self.q.get_nowait()
                self.logbox.configure(state="normal")
                self.logbox.insert("end", s + "\n")
                self.logbox.see("end")
                self.logbox.configure(state="disabled")
        except queue.Empty:
            pass
        if self.ag:
            self.cost.configure(text=f"cost: ${self.ag.client.cost.usd:.2f} / cap ${self.ag.client.cost.cap}")
        self.after(300, self.pump)

    def bg(self, fn):
        def w():
            try:
                fn()
            except Exception as e:  # noqa: BLE001
                self.log(f"ERROR: {e}")
        threading.Thread(target=w, daemon=True).start()

    def do_plan(self):
        instr = self.instr.get("1.0", "end").strip()
        if not instr or not self.ag:
            return
        if instr.lower() == "stats":
            return self.bg(self.ag.t_stats)

        def w():
            self.log("planning ...")
            steps, notes = self.ag.plan(instr)
            self.steps = steps
            self.plan.delete("1.0", "end")
            self.plan.insert("1.0", json.dumps(steps, ensure_ascii=False, indent=1))
            self.log(f"plan: {len(steps)} steps" + (f"\nnotes: {notes}" if notes else "") + "\nCheck/edit the plan, then click Run.")
            self.run_btn.configure(state="normal")
        self.bg(w)

    def do_run(self):
        try:
            steps = json.loads(self.plan.get("1.0", "end"))
        except ValueError as e:
            return messagebox.showerror("Plan", f"Plan is not valid JSON: {e}")
        self.ag.stop.clear()
        self.run_btn.configure(state="disabled")
        self.bg(lambda: (self.ag.run(steps), self.run_btn.configure(state="normal")))

    def do_stop(self):
        if self.ag:
            self.ag.stop.set()
            self.log("stopping after the current step ...")

    def open(self, p):
        os.makedirs(p, exist_ok=True) if not p.endswith(".json") else None
        if sys.platform.startswith("win"):
            os.startfile(p)  # noqa: S606
        else:
            subprocess.Popen(["xdg-open", p])


def main(workdir):
    App(workdir).mainloop()
