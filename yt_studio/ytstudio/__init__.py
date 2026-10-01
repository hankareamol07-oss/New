"""On Windows, make the bundled fribidi DLL visible so Pillow's raqm layout (Devanagari shaping) works."""
import os
import sys

if sys.platform == "win32":
    from .config import ROOT

    _dll = os.path.join(ROOT, "assets", "dll")
    if os.path.isdir(_dll):
        os.environ["PATH"] = _dll + os.pathsep + os.environ.get("PATH", "")
        os.add_dll_directory(_dll)
