"""Frieren SVG shell - pixel workbench (development only).

Master-grid (208x328) RGBA frames, SVG read/write in the master's format,
and preview helpers. Working PNGs live in tools/frieren-svg/work/ (ignored);
only the emitted SVGs ship at runtime.
"""
import os
import re

import numpy as np
from PIL import Image, ImageDraw

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", ".."))
SHELL = os.path.join(ROOT, "ghost", "Frieren", "shell", "Frieren")
HERE = os.path.dirname(os.path.abspath(__file__))
WORK = os.path.join(HERE, "work")
PREVIEW = os.path.join(HERE, "preview")
MASTER_SVG = os.path.join(HERE, "master.svg")  # master grid source (not shipped)
W, H = 208, 328

FILTER = """<defs>
<filter id="character-drop-shadow" x="-10%" y="-10%" width="120%" height="130%" color-interpolation-filters="sRGB">
<feGaussianBlur in="SourceAlpha" stdDeviation="0.7" result="shadow-blur"/>
<feOffset in="shadow-blur" dx="0" dy="5" result="shadow-offset"/>
<feFlood flood-color="#000000" flood-opacity="0.24" result="shadow-color"/>
<feComposite in="shadow-color" in2="shadow-offset" operator="in" result="shadow"/>
<feMerge>
<feMergeNode in="shadow"/>
<feMergeNode in="SourceGraphic"/>
</feMerge>
</filter>
</defs>"""


# ---------------------------------------------------------------- SVG I/O

def svg_to_rgba(path):
    s = open(path, encoding="utf8").read()
    vb = re.search(r'viewBox="0 0 (\d+) (\d+)"', s)
    im = np.zeros((int(vb[2]), int(vb[1]), 4), np.uint8)
    for m in re.finditer(r'<path fill="(#[0-9a-fA-F]{6})" d="([^"]+)"', s):
        rgb = [int(m[1][i:i + 2], 16) for i in (1, 3, 5)]
        for r in re.finditer(r"M(\d+) (\d+)h(\d+)v(\d+)h-\d+z", m[2]):
            x, y, w, h = map(int, r.groups())
            im[y:y + h, x:x + w] = rgb + [255]
    return im


def master():
    return svg_to_rgba(MASTER_SVG)


def master_palette():
    """Colours in master file order (stable path order for diffs)."""
    s = open(MASTER_SVG, encoding="utf8").read()
    return [m.lower() for m in re.findall(r'<path fill="(#[0-9a-fA-F]{6})"', s)]


def hexc(px):
    return "#%02x%02x%02x" % tuple(int(v) for v in px[:3])


def rects_for(mask):
    """Greedy rectangles: horizontal runs merged downward while identical."""
    mask = mask.copy()
    out = []
    h, w = mask.shape
    for y in range(h):
        x = 0
        while x < w:
            if not mask[y, x]:
                x += 1
                continue
            x1 = x
            while x1 < w and mask[y, x1]:
                x1 += 1
            y1 = y + 1
            while y1 < h and mask[y1, x:x1].all() and \
                    (x == 0 or not mask[y1, x - 1]) and (x1 == w or not mask[y1, x1]):
                y1 += 1
            mask[y:y1, x:x1] = False
            out.append((x, y, x1 - x, y1 - y))
            x = x1
    return out


def rgba_to_svg(im, title):
    assert im.shape == (H, W, 4)
    a = im[:, :, 3]
    assert set(np.unique(a)) <= {0, 255}, "partial alpha is not allowed"
    order = master_palette()
    colors = {hexc(im[y, x]) for y, x in zip(*np.nonzero(a))}
    extra = sorted(colors - set(order))
    lines = [
        '<?xml version="1.0" encoding="UTF-8"?>',
        # preserveAspectRatio="none": the runtime may draw the frame wider than
        # 208:328 (layout.frame, build.STRETCH_X); the default "meet" would keep
        # the aspect ratio and only pad the sides
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d" width="%d" height="%d" preserveAspectRatio="none" shape-rendering="crispEdges">' % (W, H, W, H),
        "<title>%s</title>" % title,
        "<desc>Pixel art on the shared Frieren master grid, converted from this frame's own reference. Foreground uses grouped SVG paths; no raster image is embedded. A subtle shared drop shadow sits five pixels below the character.</desc>",
        FILTER,
        '<g id="character-art" filter="url(#character-drop-shadow)">',
    ]
    rgb = im[:, :, :3].astype(np.int32)
    key = (rgb[:, :, 0] << 16) | (rgb[:, :, 1] << 8) | rgb[:, :, 2]
    for c in [c for c in order if c in colors] + extra:
        v = int(c[1:], 16)
        mask = (key == v) & (a == 255)
        d = "".join("M%d %dh%dv%dh-%dz" % (x, y, w, h, w) for x, y, w, h in rects_for(mask))
        lines.append('<path fill="%s" d="%s"/>' % (c, d))
    lines += ["</g>", "</svg>", ""]
    return "\n".join(lines)


# ---------------------------------------------------------------- frames

def work_path(name):
    return os.path.join(WORK, name + ".png")


def load(name):
    return np.array(Image.open(work_path(name)).convert("RGBA"))


def save(name, im):
    os.makedirs(WORK, exist_ok=True)
    Image.fromarray(im).save(work_path(name))


# ---------------------------------------------------------------- preview

def zoom(im, box=None, scale=8, grid=True, bg=(196, 214, 196), step=10):
    x0, y0, x1, y1 = box or (0, 0, im.shape[1], im.shape[0])
    sub = Image.fromarray(im[y0:y1, x0:x1])
    w, h = sub.size
    base = Image.new("RGBA", (w, h), bg + (255,))
    base.alpha_composite(sub)
    big = base.resize((w * scale, h * scale), Image.NEAREST)
    pad = 28
    canvas = Image.new("RGBA", (big.width + pad, big.height + pad), (255, 255, 255, 255))
    canvas.paste(big, (pad, pad))
    d = ImageDraw.Draw(canvas)
    if grid and scale >= 6:
        for x in range(w + 1):
            gx = pad + x * scale
            major = (x0 + x) % step == 0
            d.line([(gx, pad), (gx, pad + big.height)], fill=(0, 0, 0, 110 if major else 28))
            if major:
                d.text((gx + 1, 2), str(x0 + x), fill=(200, 0, 0))
        for y in range(h + 1):
            gy = pad + y * scale
            major = (y0 + y) % step == 0
            d.line([(pad, gy), (pad + big.width, gy)], fill=(0, 0, 0, 110 if major else 28))
            if major:
                d.text((1, gy + 1), str(y0 + y), fill=(200, 0, 0))
    return canvas


def row(images, gap=12, bg=(255, 255, 255)):
    w = sum(i.width for i in images) + gap * (len(images) - 1)
    h = max(i.height for i in images)
    c = Image.new("RGBA", (w, h), bg + (255,))
    x = 0
    for i in images:
        c.paste(i, (x, 0))
        x += i.width + gap
    return c


def show(img, name):
    """Save a preview under a fresh name (viewers may cache by path)."""
    import time
    os.makedirs(PREVIEW, exist_ok=True)
    p = os.path.join(PREVIEW, "%s_%d.png" % (name, int(time.time() * 1000) % 10**8))
    img.save(p)
    print("preview:", p)
    return p
