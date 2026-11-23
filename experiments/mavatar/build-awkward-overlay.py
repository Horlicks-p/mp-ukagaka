"""Pack officially validated eye/mouth textures into native, independently switchable overlays.

The private expression project uses eyes_closed/mouth_angry as importer slots only;
it does not replace the main avatar's sleeping or angry sprites.
"""
import json
from pathlib import Path
import subprocess
import shutil
import sys
from PIL import Image

ROOT = Path(__file__).resolve().parent
STUDIO = ROOT / 'studio'
PROJECT = STUDIO / 'projects/frieren-chest'
WORK = PROJECT / 'expressions/awkward'


def build():
    # Alignment, outside-mask validation, tint correction and feathering belong to upstream.
    for name in ['source.png', 'rig.json']:
        shutil.copyfile(PROJECT / name, WORK / name)
    subprocess.run([sys.executable, 'tools/variant-requests.py', str(WORK)], cwd=STUDIO, check=True)
    subprocess.run([sys.executable, 'tools/build-sprites.py', str(WORK)], cwd=STUDIO, check=True)
    sheet = json.loads((WORK / 'built/sprites/sprites.json').read_text(encoding='utf-8'))
    with Image.open(PROJECT / 'source.png') as source:
        size = source.size
    destination = PROJECT / 'built/overlays'
    destination.mkdir(parents=True, exist_ok=True)
    overlays = []
    for name, layers in [('awkward-eyes', ['eyes_closed_0', 'eyes_closed_1']),
                         ('awkward-mouth', ['mouth_angry'])]:
        canvas = Image.new('RGBA', size)
        for layer in layers:
            x, y, width, height = sheet['layers'][layer]
            with Image.open(WORK / f'built/sprites/{layer}.png') as image:
                assert image.size == (width, height)
                canvas.alpha_composite(image.convert('RGBA'), (x, y))
        canvas.save(destination / f'{name}.png')
        overlays.append(dict(name=name, file=f'{name}.png'))
    (destination / 'overlays.json').write_text(json.dumps(dict(version=1, overlays=overlays), indent=2), encoding='utf-8')
    print('Packed awkward eyes/mouth overlays from official validated sprites.')


if __name__ == '__main__':
    build()
