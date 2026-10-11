"""Build the built-in character library from FLA packs. usage: python tools/build_library.py <fla_or_xfl> [...]"""
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
from engine import library_build  # noqa: E402

if __name__ == "__main__":
    library_build.build(sys.argv[1:], log=lambda s: print(s, flush=True))
