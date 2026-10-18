"""Folder layout. When frozen (PyInstaller exe) everything is resolved next to the exe."""
import os
import sys

HERE = os.path.dirname(sys.executable) if getattr(sys, "frozen", False) else os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
ASSETS = os.path.join(HERE, "assets")
FONTS = os.path.join(ASSETS, "fonts")
LIBRARY = os.path.join(HERE, "library")
CHARS = os.path.join(LIBRARY, "characters")
BGS = os.path.join(LIBRARY, "backgrounds")
MUSIC = os.path.join(LIBRARY, "music")
IMPORTS = os.path.join(LIBRARY, "_fla")          # extracted XFL folders of imported .fla files
STORIES = os.path.join(HERE, "stories")
OUTPUT = os.path.join(HERE, "output")
CACHE = os.path.join(HERE, "cache")
CONFIG = os.path.join(HERE, "config.json")

if sys.platform == "win32":                       # Pillow raqm (Devanagari shaping) needs fribidi
    _dll = os.path.join(ASSETS, "dll")
    if os.path.isdir(_dll):
        os.environ["PATH"] = _dll + os.pathsep + os.environ.get("PATH", "")
        try:
            os.add_dll_directory(_dll)
        except (AttributeError, OSError):
            pass
