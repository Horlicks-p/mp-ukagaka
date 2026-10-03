"""Frieren's travel case for the decoration layer (90x80, development only).

Converted from refs/suitcase_sample.png (the case
design the user approved): the sample is cropped to the case, resampled to
the old decoration's footprint and split by colour into outline, steel
frame / latches, handle and the case body. Outline, steel and handle keep
the sample's own colours; the body is recoloured with the olive-brown of
the previous suitcase.svg, each body pixel taking the body colour closest
in lightness. A soft ground shadow parallel to the bottom edges is added
as before (alpha steps 64 / 128).

The canvas stays 90x80 so decorations.json position and size, and the
pixel hit test (alpha > 10), keep their meaning."""
import os
import numpy as np
from PIL import Image
from scipy import ndimage
import pix

W, H = 90, 80
SAMPLE = os.path.join(pix.HERE, "refs", "suitcase_sample.png")

# footprint of the case in the 90x80 canvas (the previous case spanned
# x 1..89, y 0..76; leave room below for the ground shadow)
BOX_LEFT, BOX_RIGHT, BOX_BOTTOM = 1, 89, 74

# body colours of the previous suitcase.svg (front, side, lid)
BODY = ["#a27a50", "#9a7349", "#936d46", "#84623e", "#82613d", "#735437",
        "#62492e", "#5e452d", "#574229", "#483725"]
SHADOW = "#3a3430"
BODY_TONES = 6

# the handle in sample pixels (so its dark brown is not taken for body)
HANDLE_BOX = (700, 150, 905, 275)


def rgb(c):
    return np.array([int(c[i:i + 2], 16) for i in (1, 3, 5)])


def lum(c):
    c = np.asarray(c, float)
    return c[..., 0] * 0.3 + c[..., 1] * 0.59 + c[..., 2] * 0.11


def load_sample():
    """Crop of the case (and its handle mask) from the sample."""
    a = np.array(Image.open(SAMPLE).convert("RGBA")).astype(float)
    ys, xs = np.nonzero(a[:, :, 3] > 200)
    x0, y0, x1, y1 = xs.min(), ys.min(), xs.max() + 1, ys.max() + 1
    handle = np.zeros(a.shape[:2], bool)
    hx0, hy0, hx1, hy1 = HANDLE_BOX
    handle[hy0:hy1, hx0:hx1] = True
    return a[y0:y1, x0:x1], handle[y0:y1, x0:x1]


def resample(a, size):
    """Box resample with premultiplied alpha (no light halo)."""
    p = a.copy()
    p[:, :, :3] *= p[:, :, 3:] / 255
    r = np.array(Image.fromarray(p.round().astype(np.uint8)).resize(size, Image.BOX)).astype(float)
    r[:, :, :3] = np.clip(r[:, :, :3] / np.maximum(r[:, :, 3:] / 255, 1e-6), 0, 255)
    return r


def draw():
    a, handle_src = load_sample()
    h_src, w_src = a.shape[:2]
    bw = BOX_RIGHT - BOX_LEFT + 1
    bh = round(h_src * bw / w_src)
    r = resample(a, (bw, bh))
    hmask = np.array(Image.fromarray(handle_src.astype(np.uint8) * 255).resize((bw, bh), Image.BOX)) > 127

    c = r[:, :, :3]
    alpha = r[:, :, 3] >= 128
    L = lum(c)
    sat = c.max(-1) - c.min(-1)
    warm = (c[:, :, 0] > c[:, :, 2] + 25) & (sat > 30)
    # inside the handle box only the dark handle itself is handle; the lid
    # seen through the arch is body
    hmask &= L < 95
    body = alpha & warm & ~hmask & (L > 38)

    out = np.zeros((bh, bw, 4), np.uint8)
    # outline, steel, latches and handle keep the sample's colours,
    # reduced to a small palette of their own
    keep = alpha & ~body
    if keep.any():
        strip = Image.fromarray(c[keep].reshape(1, -1, 3).round().astype(np.uint8))
        q = np.array(strip.quantize(colors=12, method=Image.Quantize.FASTOCTREE,
                                    dither=Image.Dither.NONE).convert("RGB")).reshape(-1, 3)
        out[keep, :3] = q
    # body: the sample's body tones are first reduced to a few flat colours
    # (its smooth gradients would otherwise turn into speckle), then each tone
    # takes the old case colour at the same relative lightness
    pal = np.array([rgb(x) for x in BODY])
    pl = lum(pal)
    strip = Image.fromarray(c[body].reshape(1, -1, 3).round().astype(np.uint8))
    tones = np.array(strip.quantize(colors=BODY_TONES, method=Image.Quantize.MEDIANCUT,
                                    dither=Image.Dither.NONE).convert("RGB")).reshape(-1, 3).astype(float)
    tl = lum(tones)
    uniq = np.unique(tl)
    lo, hi = uniq.min(), uniq.max()
    t = np.clip((tl - lo) / max(hi - lo, 1), 0, 1)
    target = pl.min() + t * (pl.max() - pl.min())
    out[body, :3] = pal[np.abs(target[:, None] - pl[None, :]).argmin(1)]
    out[alpha, 3] = 255

    # lone pixels left by resampling
    nb = ndimage.convolve(alpha.astype(int), np.ones((3, 3), int), mode="constant") - alpha
    out[alpha & (nb == 0)] = 0

    im = np.zeros((H, W, 4), np.uint8)
    top = BOX_BOTTOM + 1 - bh
    im[top:top + bh, BOX_LEFT:BOX_LEFT + bw] = out

    # ground shadow parallel to the bottom edges, below/right of the case
    case = im[:, :, 3] > 0
    for (dx, dy), a_ in (((3, 5), 64), ((2, 3), 128)):
        moved = np.zeros_like(case)
        moved[dy:, dx:] = case[:H - dy, :W - dx]
        m = moved & ~case & (im[:, :, 3] == 0)
        im[m, :3] = rgb(SHADOW)
        im[m, 3] = a_
    return im


if __name__ == "__main__":
    im = draw()
    pix.show(pix.zoom(im, None, 7, grid=False), "suitcase_from_sample")
