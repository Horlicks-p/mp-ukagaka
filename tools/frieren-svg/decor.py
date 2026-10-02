"""Frieren decorations -> self-contained static SVG (development only).

The decoration PNGs are already at their display size (decorations.json
size.width == PNG width), so each SVG keeps the PNG's pixel grid as its
viewBox and intrinsic size: position, size and the pixel hit test stay as
they are. Colours are reduced per decoration (octree) and identical
colours are merged into one path of rects, like the body frames."""
import os
import numpy as np
from PIL import Image
import pix

DECOR = os.path.join(pix.ROOT, "ghost", "Frieren", "decorations")
NAMES = ["books", "staff", "suitcase", "evil_horns", "dark_dragon_horn", "potion"]


def load(name):
    return Image.open(os.path.join(DECOR, name + ".png")).convert("RGBA")


# shared alpha steps (plan 7.2 rule 5): soft shadows, glows and the glass
# bottle cap live in half-transparent pixels and must survive
ALPHA_STEPS = np.array([0, 26, 64, 128, 191, 255])
# frieren-decorations.js treats alpha > 10 as clickable; every such pixel
# must land on a non-zero step so the SVG hit area equals the PNG's
HIT_THRESHOLD = 10


def alpha_step(al):
    step = ALPHA_STEPS[np.abs(al[..., None] - ALPHA_STEPS[None, None]).argmin(-1)]
    step = np.where((al > HIT_THRESHOLD) & (step == 0), ALPHA_STEPS[1], step)
    return np.where(al <= HIT_THRESHOLD, 0, step)


def quantize(im, colors=64, semi_colors=16):
    """Opaque pixels -> `colors` colours (fast octree: keeps small accents
    such as the red bookmarks that median cut merges away); half-transparent pixels
    -> nearest shared alpha step and `semi_colors` colours of their own."""
    a = np.array(im)
    al = a[:, :, 3].astype(int)
    step = alpha_step(al)
    res = np.zeros_like(a)
    for sel, n in ((step == 255, colors), ((step > 0) & (step < 255), semi_colors)):
        if not sel.any():
            continue
        px = a[sel][:, :3]
        strip = Image.fromarray(px.reshape(1, -1, 3).astype(np.uint8))
        q = np.array(strip.quantize(colors=n, method=Image.Quantize.FASTOCTREE,
                                    dither=Image.Dither.NONE).convert("RGB")).reshape(-1, 3)
        res[sel, :3] = q
        res[sel, 3] = step[sel]
    return res


def to_svg(im, title):
    h, w = im.shape[:2]
    a = im[:, :, 3]
    rgb = im[:, :, :3].astype(np.int32)
    key = (rgb[:, :, 0] << 16) | (rgb[:, :, 1] << 8) | rgb[:, :, 2]
    lines = [
        '<?xml version="1.0" encoding="UTF-8"?>',
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d" width="%d" height="%d" shape-rendering="crispEdges">' % (w, h, w, h),
        "<title>%s</title>" % title,
        "<desc>Frieren decoration as pixel art: grouped SVG paths, no raster image embedded.</desc>",
    ]
    for st in ALPHA_STEPS[::-1][:-1]:
        layer = a == st
        if not layer.any():
            continue
        op = "" if st == 255 else ' fill-opacity="%s"' % format(st / 255, ".2f").rstrip("0")
        vals, counts = np.unique(key[layer], return_counts=True)
        for v in vals[np.argsort(-counts)]:
            mask = (key == v) & layer
            d = "".join("M%d %dh%dv%dh-%dz" % (x, y, ww, hh, ww) for x, y, ww, hh in pix.rects_for(mask))
            lines.append('<path fill="#%06x"%s d="%s"/>' % (int(v), op, d))
    lines += ["</svg>", ""]
    return "\n".join(lines)


# decorations redrawn as pixel art instead of converted (already exact
# palette and alpha steps): name -> function returning the RGBA array
REDRAWN = {}


def build():
    import suitcase
    REDRAWN["suitcase"] = suitcase.draw
    for name in NAMES:
        im = load(name)
        q = REDRAWN[name]() if name in REDRAWN else quantize(im)
        s = to_svg(q, "Frieren decoration: %s" % name)
        path = os.path.join(DECOR, name + ".svg")
        with open(path, "w", encoding="utf8", newline="\n") as fh:
            fh.write(s)
        cols = len(np.unique(q[q[:, :, 3] > 0][:, :3], axis=0))
        print("%-17s %dx%d  colors=%3d paths=%3d bytes=%6d" % (name, im.width, im.height, cols, s.count("<path"), len(s)))


if __name__ == "__main__":
    build()
