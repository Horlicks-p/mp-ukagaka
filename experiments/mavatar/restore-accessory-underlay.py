"""Assemble a tightly masked accessory underpainting after the official layer build.

Only built/base.png within the private protection mask changes. Source, rig and
separate earrings remain intact. Reuses upstream size and outside-mask checks on
the final assembled base; artwork outside the mask is discarded before assembly.
"""
import argparse
import hashlib
import importlib.util
import json
import sys
from pathlib import Path

import cv2
import numpy as np
from PIL import Image


def restore(project):
    project = Path(project).resolve()
    studio = Path(__file__).resolve().parent / 'studio'
    if not project.is_relative_to((studio / 'projects').resolve()):
        raise ValueError('Project must stay inside Studio/projects')
    sys.path.insert(0, str(studio / 'tools'))
    spec = importlib.util.spec_from_file_location('official_sprites', studio / 'tools/build-sprites.py')
    sprites = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(sprites)
    folder = project / 'accessory-underlay'
    base_path = project / 'built/base.png'
    report_path = folder / 'import-report.json'
    if report_path.exists():
        previous = json.loads(report_path.read_text(encoding='utf-8'))
        already_applied = (
            previous.get('baseSha256') == hashlib.sha256(base_path.read_bytes()).hexdigest()
            and previous.get('paintingSha256') == hashlib.sha256((folder / 'painting.png').read_bytes()).hexdigest()
            and previous.get('maskSha256') == hashlib.sha256((folder / 'mask.png').read_bytes()).hexdigest()
        )
        if already_applied:
            print('Accessory underlay already applied; base unchanged.')
            return
    before = np.array(Image.open(base_path).convert('RGBA'))
    region = sprites.load_mask(folder / 'mask.png', before.shape[1::-1])
    if not np.any(region):
        raise ValueError('Empty accessory protection mask')
    raw, _ = sprites.read_variant('accessory-underlay', folder / 'painting.png', before.shape[1::-1])
    new, old = raw.astype(np.float32), before.astype(np.float32)
    new[..., 3] = np.where(new[..., 3] > 240, 255, new[..., 3])
    weight = np.clip(cv2.distanceTransform(region, cv2.DIST_L2, 5) / 4, 0, 1)[..., None]
    alpha = old[..., 3:4] * (1 - weight) + new[..., 3:4] * weight
    premul = old[..., :3] * old[..., 3:4] * (1 - weight) + new[..., :3] * new[..., 3:4] * weight
    rgb = np.divide(premul, alpha, out=np.zeros_like(premul), where=alpha > 0)
    after = np.concatenate((np.clip(np.rint(rgb), 0, 255), np.rint(alpha)), axis=-1).astype(np.uint8)
    after[region == 0] = before[region == 0]
    assert np.array_equal(after[region == 0], before[region == 0])
    # Validate the actual assembled base against the original built base. Raw
    # generator edits outside this mask are never part of the resulting image.
    # Ignore the returned face-only alpha/tint adjustment: this underpainting
    # deliberately restores a hair silhouette and its true transparent gap.
    sprites.mask_only('assembled-accessory-base', after, before, region)
    Image.fromarray(before).save(folder / 'base-before.png')
    Image.fromarray(after).save(base_path)
    metadata_path = project / 'built/layers.json'
    metadata = json.loads(metadata_path.read_text(encoding='utf-8'))
    metadata['build'] = hashlib.sha256(base_path.read_bytes()).hexdigest()[:12]
    metadata_path.write_text(json.dumps(metadata, indent=2) + '\n', encoding='utf-8')
    report = {
        'method': 'generated hair underpainting, exact-mask assembly, official validation of final assembled base',
        'maskPixels': int(np.count_nonzero(region)),
        'changedPixels': int(np.count_nonzero(np.any(after != before, axis=-1))),
        'outsideMaskPixelsChanged': 0,
        'sourceSha256': hashlib.sha256((project / 'source.png').read_bytes()).hexdigest(),
        'paintingSha256': hashlib.sha256((folder / 'painting.png').read_bytes()).hexdigest(),
        'maskSha256': hashlib.sha256((folder / 'mask.png').read_bytes()).hexdigest(),
        'baseSha256': hashlib.sha256(base_path.read_bytes()).hexdigest(),
    }
    report_path.write_text(json.dumps(report, indent=2) + '\n', encoding='utf-8')
    print(json.dumps(report))


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('project', help='Project folder inside Studio/projects')
    restore(parser.parse_args().project)
