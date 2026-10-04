"""Settings tab: every API key / model / provider order / cost cap edited in the GUI and saved to config.json (never bundled in the exe)."""
import json, os
import tkinter as tk
from tkinter import ttk, filedialog, messagebox
from .llm import PROVIDERS

ROWS = [  # (config key, label, secret?)
    ("vertex.project", "GCP project ID (Vertex AI)", False),
    ("vertex.location", "Vertex location (global / us-central1)", False),
    ("vertex.key_file", "GCP service-account key file (.json)", False),
    ("vertex.api_key", "Vertex API key (instead of key file)", True),
    ("gemini_api_keys", "Gemini API keys (comma separated, AI Studio)", True),
    ("gemini_model", "Gemini model", False),
    ("anthropic_api_key", "Anthropic Claude API key", True), ("anthropic_model", "Claude model", False),
    ("openai_api_key", "OpenAI API key", True), ("openai_model", "OpenAI model", False),
    ("openrouter_api_key", "OpenRouter API key (Kimi/Qwen/DeepSeek/…)", True), ("openrouter_model", "OpenRouter model", False),
    ("mistral_api_key", "Mistral API key", True), ("mistral_model", "Mistral model", False),
    ("together_api_key", "Together API key", True), ("together_model", "Together model", False),
    ("groq_api_key", "Groq API key", True), ("groq_model", "Groq model", False),
    ("nvidia_api_key", "NVIDIA NIM API key", True), ("nvidia_model", "NVIDIA model", False),
    ("sarvam_api_key", "Sarvam API key", True), ("sarvam_model", "Sarvam model", False),
    ("custom_url", "Custom OpenAI-compatible URL (…/v1/chat/completions)", False),
    ("custom_api_key", "Custom API key", True), ("custom_model", "Custom model", False), ("custom_vision", "Custom model can read images (true/false)", False),
    ("llm_order", "Provider order for text (comma separated)", False),
    ("cost_cap_usd", "Cost cap (USD) – run stops above this", False),
    ("workers", "Parallel requests", False), ("ocr_batch", "Pages per OCR request", False), ("db", "Database file", False),
]


def _get(cfg, key):
    if "." in key:
        a, b = key.split(".")
        return (cfg.get(a) or {}).get(b, "")
    v = cfg.get(key, "")
    return ", ".join(v) if isinstance(v, list) else ("" if v is None else v)


def _set(cfg, key, val):
    val = val.strip()
    if "." in key:
        a, b = key.split(".")
        cfg.setdefault(a, {})
        if val:
            cfg[a][b] = val
        else:
            cfg[a].pop(b, None)
        return
    if key in ("gemini_api_keys", "llm_order"):
        cfg[key] = [x.strip() for x in val.split(",") if x.strip()]
    elif key in ("cost_cap_usd",):
        cfg[key] = float(val or 100)
    elif key in ("workers", "ocr_batch"):
        cfg[key] = int(val or 4)
    elif key == "custom_vision":
        cfg[key] = val.lower() in ("true", "1", "yes", "होय")
    else:
        cfg[key] = val


class SettingsTab(ttk.Frame):
    def __init__(self, master, workdir, font, on_saved):
        super().__init__(master, padding=8)
        self.path = os.path.join(workdir, "config.json")
        self.example = os.path.join(workdir, "config.example.json")
        self.workdir, self.on_saved, self.vars = workdir, on_saved, {}
        canvas = tk.Canvas(self, highlightthickness=0)
        sb = ttk.Scrollbar(self, orient="vertical", command=canvas.yview)
        inner = ttk.Frame(canvas)
        inner.bind("<Configure>", lambda e: canvas.configure(scrollregion=canvas.bbox("all")))
        canvas.create_window((0, 0), window=inner, anchor="nw")
        canvas.configure(yscrollcommand=sb.set)
        canvas.pack(side="left", fill="both", expand=True)
        sb.pack(side="right", fill="y")
        ttk.Label(inner, text="API keys stay in config.json on this PC only. Fill what you have; empty = not used.  Providers: " + ", ".join(PROVIDERS),
                  font=font, wraplength=900).grid(row=0, column=0, columnspan=3, sticky="w", pady=(0, 8))
        for i, (key, label, secret) in enumerate(ROWS, 1):
            ttk.Label(inner, text=label, font=font).grid(row=i, column=0, sticky="w", padx=(0, 8), pady=2)
            v = tk.StringVar()
            e = ttk.Entry(inner, textvariable=v, width=70, show="•" if secret else "")
            e.grid(row=i, column=1, sticky="we", pady=2)
            self.vars[key] = v
            if secret:
                ttk.Button(inner, text="👁", width=3, command=lambda e=e: e.configure(show="" if e.cget("show") else "•")).grid(row=i, column=2)
            if key == "vertex.key_file":
                ttk.Button(inner, text="…", width=3, command=lambda v=v: v.set(self._pick())).grid(row=i, column=2)
        r = len(ROWS) + 1
        bar = ttk.Frame(inner)
        bar.grid(row=r, column=0, columnspan=3, sticky="w", pady=10)
        ttk.Button(bar, text="💾 Save settings", command=self.save).pack(side="left")
        ttk.Button(bar, text="↺ Reload", command=self.load).pack(side="left", padx=6)
        ttk.Button(bar, text="🔌 Test providers", command=self.test).pack(side="left")
        self.status = ttk.Label(inner, text="", font=font, wraplength=900)
        self.status.grid(row=r + 1, column=0, columnspan=3, sticky="w")
        self.load()

    def _pick(self):
        p = filedialog.askopenfilename(title="GCP service-account key", filetypes=[("JSON", "*.json")])
        if p and os.path.dirname(p) == self.workdir:
            p = os.path.basename(p)
        return p or self.vars["vertex.key_file"].get()

    def cfg(self):
        src = self.path if os.path.exists(self.path) else self.example
        try:
            return json.load(open(src, encoding="utf-8"))
        except Exception:  # noqa: BLE001
            return {}

    def load(self):
        cfg = self.cfg()
        for k, v in self.vars.items():
            v.set(str(_get(cfg, k)))
        self.status.configure(text=f"loaded {os.path.basename(self.path if os.path.exists(self.path) else self.example)}")

    def save(self):
        cfg = self.cfg()
        try:
            for k, v in self.vars.items():
                _set(cfg, k, v.get())
        except ValueError as e:
            return messagebox.showerror("Settings", f"Bad value: {e}")
        json.dump(cfg, open(self.path, "w", encoding="utf-8"), ensure_ascii=False, indent=1)
        self.status.configure(text="saved config.json – agent reloaded")
        self.on_saved()

    def test(self):
        from .llm import Client
        import threading

        def w():
            c = Client(self.cfg(), self.workdir)
            out = []
            for p in PROVIDERS:
                if not c.has(p):
                    continue
                try:
                    js, m = c.call(p, "Answer in JSON.", 'Return {"ok": true}', max_tokens=50)
                    out.append(f"{p}: OK ({m})")
                except Exception as e:  # noqa: BLE001
                    out.append(f"{p}: FAIL {str(e)[:100]}")
            self.status.configure(text="\n".join(out) or "no provider configured")
        self.status.configure(text="testing …")
        threading.Thread(target=w, daemon=True).start()
