"""Evidence for convert.py: rebuild the master from book ref [1] with several
resampling filters and report how closely each reproduces it (silhouette and
palette-colour agreement). BOX is the pipeline used for every frame."""
import os, numpy as np
from PIL import Image
import pix
import convert
m = pix.load("master").astype(int)
pal = np.array([[int(c[i:i + 2], 16) for i in (1, 3, 5)] for c in pix.master_palette()], float)
im = Image.open(convert.ref_path("book", 1)).convert("RGBA")
def quant(rgb):
    d = ((rgb[..., None, :] - pal[None, None, :, :]) ** 2).sum(-1)
    return pal[d.argmin(-1)]
for fname, f in [("nearest", Image.NEAREST), ("box", Image.BOX), ("bilinear", Image.BILINEAR), ("hamming", Image.HAMMING), ("bicubic", Image.BICUBIC), ("lanczos", Image.LANCZOS)]:
    for premul in (False, True):
        src = im
        if premul:
            a = np.array(im).astype(float); a[:, :, :3] *= a[:, :, 3:4] / 255
            src = Image.fromarray(a.astype(np.uint8))
        r = np.array(src.resize((208, 328), f)).astype(float)
        if premul:
            r[:, :, :3] = r[:, :, :3] / np.maximum(r[:, :, 3:4] / 255, 1e-6)
        for thr in (128,):
            alpha = r[:, :, 3] >= thr
            q = quant(np.clip(r[:, :, :3], 0, 255))
            both = alpha & (m[:, :, 3] > 0)
            ex = ((np.abs(q - m[:, :, :3]).sum(-1) == 0) & both).sum() / both.sum()
            nr = ((np.abs(q - m[:, :, :3]).sum(-1) < 40) & both).sum() / both.sum()
            print("%-9s premul=%d alpha %.4f exact %.3f near %.3f" % (fname, premul, (alpha == (m[:, :, 3] > 0)).mean(), ex, nr))
