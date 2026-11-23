"""Pack officially validated expression drawings into native switchable overlays.

Private expression projects use supported eye/mouth importer slots to validate
and crop additional drawings, preserving the main avatar's sleeping/angry slots.
"""
import json
from pathlib import Path
import subprocess
import shutil
import sys
import re
from PIL import Image

ROOT = Path(__file__).resolve().parent
STUDIO = ROOT / 'studio'
project_name = sys.argv[1] if len(sys.argv) > 1 else 'frieren-chest'
if not re.fullmatch(r'[A-Za-z0-9][A-Za-z0-9_-]*', project_name):
    raise ValueError('Invalid local project name')
PROJECT = STUDIO / 'projects' / project_name


def build():
    jobs = [('awkward', [('awkward-eyes', ['eyes_closed_0', 'eyes_closed_1']),
                         ('awkward-mouth', ['mouth_angry'])], {})]
    presets_path = PROJECT / 'expressions/bridge.json'
    presets = json.loads(presets_path.read_text(encoding='utf-8')) if presets_path.exists() else {}
    for key, face in presets.items():
        mapping = []
        if face.get('eyeOverlay'):
            mapping.append((face['eyeOverlay'], ['eyes_half_0', 'eyes_half_1']))
        if face.get('mouthOverlay'):
            mapping.append((face['mouthOverlay'], ['mouth_angry']))
        if mapping:
            if not re.fullmatch(r'[A-Za-z0-9_-]+', key):
                raise ValueError('Invalid expression project name')
            jobs.append((key, mapping, face.get('importMouthArea', {})))
    with Image.open(PROJECT / 'source.png') as source:
        size = source.size
    destination = PROJECT / 'built/overlays'
    destination.mkdir(parents=True, exist_ok=True)
    overlays = []
    for expression, mapping, mouth_area in jobs:
        work = PROJECT / 'expressions' / expression
        # Alignment, outside-mask validation, tint correction and feathering all
        # remain in the unmodified official importer, including wider closed smiles.
        for name in ['source.png', 'rig.json']:
            shutil.copyfile(PROJECT / name, work / name)
        if mouth_area:
            rig = json.loads((work / 'rig.json').read_text(encoding='utf-8'))
            rig['mouth']['area'].update(mouth_area)
            (work / 'rig.json').write_text(json.dumps(rig, indent=2), encoding='utf-8')
        subprocess.run([sys.executable, 'tools/variant-requests.py', str(work)], cwd=STUDIO, check=True)
        subprocess.run([sys.executable, 'tools/build-sprites.py', str(work)], cwd=STUDIO, check=True)
        sheet = json.loads((work / 'built/sprites/sprites.json').read_text(encoding='utf-8'))
        for name, layers in mapping:
            if not re.fullmatch(r'[A-Za-z0-9_-]+', name):
                raise ValueError('Invalid expression overlay name')
            canvas = Image.new('RGBA', size)
            for layer in layers:
                x, y, width, height = sheet['layers'][layer]
                with Image.open(work / f'built/sprites/{layer}.png') as image:
                    assert image.size == (width, height)
                    canvas.alpha_composite(image.convert('RGBA'), (x, y))
            canvas.save(destination / f'{name}.png')
            overlays.append(dict(name=name, file=f'{name}.png'))
    (destination / 'overlays.json').write_text(json.dumps(dict(version=1, overlays=overlays), indent=2), encoding='utf-8')
    print(f'Packed {len(overlays)} expression overlays from officially validated sprites.')


if __name__ == '__main__':
    build()
