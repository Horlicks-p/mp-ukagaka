"""Per-frame seam check: changed region with its border marked, plain."""
import sys, numpy as np
import pix, convert
kind, base = sys.argv[1], (sys.argv[2].split(':')[0], int(sys.argv[2].split(':')[1]))
names = sys.argv[3:]
m = pix.load("master")
for nm in names:
    n = int(nm)
    f = pix.load("%s-%02d" % (kind, n))
    mk = convert.change_mask(kind, n, base)
    ys, xs = np.nonzero(mk)
    B = (max(xs.min() - 4, 0), max(ys.min() - 4, 0), min(xs.max() + 5, 208), min(ys.max() + 5, 328))
    e = mk & ~(np.roll(mk, 1, 0) & np.roll(mk, -1, 0) & np.roll(mk, 1, 1) & np.roll(mk, -1, 1))
    v = f.copy(); v[e] = [0, 255, 255, 255]
    s = max(2, min(6, 900 // (B[2] - B[0])))
    pix.show(pix.row([pix.zoom(f, B, s, grid=False), pix.zoom(v, B, s, grid=False)]), "seam_%s%02d" % (kind, n))
