"""Build working PNGs, runtime SVGs, assets.json and QA sheets.

usage: python build.py [name-prefix ...]   e.g. build.py sleep wake-04
"""
import json, os, sys
import numpy as np
from PIL import Image, ImageDraw
import pix
import recipes
import eyes

SEQ = {
    "idle": ([("idle-%02d" % i) for i in range(12)], True,
             [4800, 50, 120, 50, 4800, 120, 50, 50, 50, 50, 4800, 120]),
    "sleep": ([("sleep-%02d" % i) for i in range(10)], True,
              [300, 300, 600, 300, 600, 300, 300, 300, 300, 300]),
    # book flip: mpuCanvasManager.frameInterval default (ukagaka-anime.js)
    "book_flip": ([("book-%02d" % i) for i in range(1, 12)], False, [150] * 11),
    # wake: frameInterval in playFrierenWakeAnimation (frieren-animation.js)
    "wake": ([("wake-%02d" % i) for i in range(1, 6)], False, [80] * 5),
}
DIR = {"idle": "idle", "sleep": "sleep", "book": "book", "wake": "wake"}


def svg_path(name):
    kind = name.split("-")[0]
    return os.path.join(pix.SHELL, DIR[kind], "frieren-%s.svg" % name)


def check(name, im):
    a = im[:, :, 3]
    assert set(np.unique(a)) <= {0, 255}, name + ": partial alpha"
    cols = len(np.unique(im[a > 0][:, :3], axis=0))
    return cols


def build(names):
    for n in names:
        if n not in recipes.R:
            print("skip (no recipe yet)", n); continue
        im, conv = recipes.R[n]()
        if conv is not None:
            im, _ = eyes.fix(im, *recipes.SOURCE[n], only=conv)
        cols = check(n, im)
        pix.save(n, im)
        p = svg_path(n)
        os.makedirs(os.path.dirname(p), exist_ok=True)
        s = pix.rgba_to_svg(im, "Frieren %s" % n)
        open(p, "w", encoding="utf8", newline="\n").write(s)
        print("%-9s colors=%3d paths=%3d bytes=%6d" % (n, cols, s.count("<path"), len(s)))


def qa(seq):
    names, loop, _ = SEQ[seq]
    have = [n for n in names if os.path.exists(pix.work_path(n))]
    tiles = []
    for i, n in enumerate(have):
        cur = pix.load(n)
        prev = pix.load(have[i - 1]) if i else cur
        d = np.any(cur != prev, axis=2)
        onion = cur.copy()
        onion[d] = [255, 0, 255, 255]
        t = pix.row([pix.zoom(cur, (24, 24, 184, 324), 2, grid=False),
                     pix.zoom(onion, (24, 24, 184, 324), 2, grid=False)], gap=2)
        ImageDraw.Draw(t).text((30, 4), "%s  diff-prev=%d" % (n, d.sum()), fill=(0, 0, 0))
        tiles.append(t)
    rows = [pix.row(tiles[i:i + 4]) for i in range(0, len(tiles), 4)]
    w = max(r.width for r in rows); h = sum(r.height + 8 for r in rows)
    c = Image.new("RGBA", (w, h), (255, 255, 255, 255)); y = 0
    for r in rows:
        c.paste(r, (0, y)); y += r.height + 8
    return pix.show(c, "qa_" + seq)


# The shell box the rest of the runtime is written against (decorations.json
# positions, touch-zone ratios, emoji offsets): the former 134x249 PNG, whose
# character filled rows 1..248 and the full width.
LEGACY_BOX = (134, 249)
LEGACY_CHAR_TOP, LEGACY_CHAR_HEIGHT = 1, 248


def layout():
    """Where the 208x328 SVG frame is drawn relative to that box: scaled so
    the character matches the old character's height and centred on it. The
    frame overhangs the box (margins, drop shadow); the box stays the layout."""
    m = pix.master()
    ys, xs = np.nonzero(m[:, :, 3] > 0)
    top, bottom = int(ys.min()), int(ys.max()) + 1
    left, right = int(xs.min()), int(xs.max()) + 1
    s = LEGACY_CHAR_HEIGHT / (bottom - top)
    bw, bh = LEGACY_BOX
    return {
        "box": [bw, bh],
        "frame": [round((bw - (right - left) * s) / 2 - left * s, 2),
                  round(LEGACY_CHAR_TOP - top * s, 2),
                  round(pix.W * s, 2), round(pix.H * s, 2)],
    }


def manifest():
    out = {"format_version": 1, "view_box": [0, 0, pix.W, pix.H],
           "layout": layout(), "sequences": {}}
    for seq, (names, loop, durs) in SEQ.items():
        frames = []
        for i, n in enumerate(names):
            f = {"src": os.path.relpath(svg_path(n), pix.SHELL).replace("\\", "/")}
            f["duration_ms"] = durs[i]
            frames.append(f)
        out["sequences"][seq] = {"loop": loop, "frames": frames}
    return out



if __name__ == "__main__":
    pre = sys.argv[1:] or [""]
    allnames = [n for s in SEQ.values() for n in s[0]]
    build([n for n in allnames if any(n.startswith(p) for p in pre)])
    path = os.path.join(pix.SHELL, "assets.json")
    with open(path, "w", encoding="utf8", newline="\n") as fh:
        fh.write(json.dumps(manifest(), indent=2) + "\n")
    print("wrote", path)
