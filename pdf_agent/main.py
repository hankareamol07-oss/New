#!/usr/bin/env python3
"""pdf_agent – run with no arguments for the GUI, or:  main.py "instruction"  [--yes]  (CLI, plan printed first)."""
import os, sys
HERE = os.path.dirname(os.path.abspath(sys.executable if getattr(sys, "frozen", False) else __file__))
sys.path.insert(0, HERE)
from pdfagent.agent import Agent  # noqa: E402


def cli(instr, yes):
    ag = Agent(HERE)
    steps, notes = ag.plan(instr)
    print("PLAN:")
    for i, s in enumerate(steps, 1):
        print(f" {i}. {s['tool']} {s.get('args', {})}  — {s.get('why', '')}")
    if notes:
        print("notes:", notes)
    if not yes and input("Run? [y/N] ").strip().lower() != "y":
        return
    ag.run(steps)


if __name__ == "__main__":
    args = [a for a in sys.argv[1:] if not a.startswith("--")]
    if args:
        cli(" ".join(args), "--yes" in sys.argv)
    else:
        from pdfagent.gui import main
        main(HERE)
