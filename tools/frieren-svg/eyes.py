"""Eye colour fix: the dots under the irises are teal-green in the original
(Frieren's green eyes, suggested with a few pixels in this RO style). The
master palette has no teal, so conversion snapped them to the nearest greys.

Per frame, inside the eye window only: a pixel becomes teal when the frame's
own reference is teal-green there (within 1 px for the whole-pixel rounding
of head moves) and the frame shows one of those greys. Lightness is kept by
mapping each grey to the teal of the same rank."""
import numpy as np
from scipy import ndimage
import pix
import convert

GREY_TO_TEAL = {
    "#686d7a": "#5f8a7f",
    "#8f848f": "#719a8d",
    "#97929d": "#7ca295",
    "#a998a1": "#8aab9b",
    "#b1a7b5": "#9ab8a8",
}
# the two iris boxes on the master (left, right); head moves of up to 6 px
# down are covered by extending each box downward
IRISES = [(89, 92, 98, 100), (112, 92, 122, 100)]


def teal_in_reference(kind, n):
    r = convert.reference(kind, n).astype(float)
    R, G, B, A = r[:, :, 0], r[:, :, 1], r[:, :, 2], r[:, :, 3]
    mx, mn = np.maximum(np.maximum(R, G), B), np.minimum(np.minimum(R, G), B)
    sat = (mx - mn) / np.maximum(mx, 1)
    # green-to-cyan hue (pink skin and the mauve greys fall outside), allowing
    # the faint tint of a half-open eye
    greenish = (G >= R) & (G >= B - 12)
    return (A >= 128) & greenish & (sat > 0.06)


def fix(im, kind, n, max_dy=6, only=None):
    """`only`: restrict to pixels converted for this frame (pixels carried
    over from the master already have the master's teal)."""
    win = np.zeros(im.shape[:2], bool)
    for x0, y0, x1, y1 in IRISES:
        win[y0:y1 + max_dy, x0:x1] = True
    teal = ndimage.binary_dilation(teal_in_reference(kind, n) & win, iterations=1)
    if only is not None:
        teal &= only
    out = im.copy()
    count = 0
    for grey, tl in GREY_TO_TEAL.items():
        g = np.array([int(grey[i:i + 2], 16) for i in (1, 3, 5)])
        sel = teal & np.all(im[:, :, :3] == g, axis=2) & (im[:, :, 3] > 0)
        out[sel] = [int(tl[i:i + 2], 16) for i in (1, 3, 5)] + [255]
        count += int(sel.sum())
    return out, count
