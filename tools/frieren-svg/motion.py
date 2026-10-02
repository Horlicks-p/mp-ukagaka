"""Sub-pixel motion of a reference region relative to the base reference.

Measured at the reference's own resolution (integer search + parabolic
refinement), returned in reference pixels and in master pixels."""
import numpy as np
from PIL import Image
import pix
import convert

# regions as fractions of the canvas (x0, y0, x1, y1); same framing in all sets
HEAD = (0.20, 0.08, 0.80, 0.30)      # hair crown, forehead, ears, eyes
TORSO = (0.15, 0.40, 0.85, 0.47)     # cape shoulders (below the side hair tips)


def load(kind, n):
    return np.array(Image.open(convert.ref_path(kind, n)).convert("RGBA")).astype(float)


def _err(a, b, box, dx, dy):
    x0, y0, x1, y1 = box
    A = a[y0 + dy:y1 + dy, x0 + dx:x1 + dx]
    B = b[y0:y1, x0:x1]
    return np.abs(A[:, :, :3] * A[:, :, 3:] / 255 - B[:, :, :3] * B[:, :, 3:] / 255).mean()


def measure(a, b, frac, r=14, fix_dx=False):
    h, w = b.shape[:2]
    box = (int(frac[0] * w), int(frac[1] * h), int(frac[2] * w), int(frac[3] * h))
    best = None
    for dy in range(-r, r + 1):
        for dx in ([0] if fix_dx else range(-r // 2, r // 2 + 1)):
            e = _err(a, b, box, dx, dy)
            if best is None or e < best[0]:
                best = (e, dx, dy)
    _, dx, dy = best

    def para(f, v):
        em, e0, ep = f(v - 1), f(v), f(v + 1)
        den = em - 2 * e0 + ep
        return v + (0.5 * (em - ep) / den if den > 1e-9 else 0.0)
    fy = para(lambda v: _err(a, b, box, dx, v), dy)
    fx = dx if fix_dx else para(lambda v: _err(a, b, box, v, dy), dx)
    return fx, fy


def to_master(ref_shape, d):
    h, w = ref_shape[:2]
    return d[0] * pix.W / w, d[1] * pix.H / h


if __name__ == "__main__":
    import sys
    base = load("idle", 1)
    sets = [("idle", range(1, 13)), ("sleep", range(13)), ("wake", range(1, 6))]
    for kind, ns in sets:
        for n in ns:
            a = load(kind, n)
            hd = to_master(a.shape, measure(a, base, HEAD))
            td = to_master(a.shape, measure(a, base, TORSO))
            print("%-5s %2d  head dx %+.2f dy %+.2f   torso dx %+.2f dy %+.2f" % (kind, n, hd[0], hd[1], td[0], td[1]))
