"""Közös segéd: az előnézet adatai (assets/js/data.js, panthera-data.js) Python-szótárként."""
import json
import os
import subprocess

ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))


def _load(js, var):
    code = (f"global.window={{}};require({json.dumps(os.path.join(ROOT, js))});"
            f"process.stdout.write(JSON.stringify(window.{var}))")
    return json.loads(subprocess.run(['node', '-e', code], capture_output=True, text=True, check=True).stdout)


def load_preview():
    return _load('assets/js/data.js', 'SLUG')


def load_panthera():
    return _load('assets/js/panthera-data.js', 'PANTHERA')
