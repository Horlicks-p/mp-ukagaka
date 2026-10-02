"""Per-frame conversion: each frame's own large reference -> master grid.

This is the same pipeline that reproduces the master from book ref [1]
(verified by repro2.py: silhouette 99.4% identical, 88% of colours equal or
within a few levels): the whole reference canvas is box-resampled to
208x328, colours snap to the shared master palette, alpha is binarised at
50%. No pixels are taken from the master or from any other frame."""
import os
import numpy as np
from PIL import Image
import pix

SETS = {
    "book": ("book", lambda n: "frieren[%d].png" % n),
    "idle": ("idle", lambda n: "frieren[%d].png" % n),
    "sleep": ("sleep", lambda n: "frieren[s%d].png" % n),
    "wake": ("awareness", lambda n: "frieren[w%d].png" % n),
}
# the per-frame large references (development only, not shipped)
REFS = os.path.join(pix.HERE, "refs")
_PAL = None


def palette():
    global _PAL
    if _PAL is None:
        _PAL = np.array([[int(c[i:i + 2], 16) for i in (1, 3, 5)] for c in pix.master_palette()], float)
    return _PAL


def ref_path(kind, n):
    folder, name = SETS[kind]
    return os.path.join(REFS, folder, name(n))


def resize(im):
    """Box-resample to the master grid without the white halo.

    The references were cut out of a white background: half-transparent
    edge pixels still carry that white mixed in, and fully transparent
    pixels store white RGB. Both would bleed into the outline and show as a
    light rim on dark backgrounds. So the white matte is removed first
    (C = (C - (1 - a) * 255) / a) and the resampling is premultiplied."""
    a = np.array(im.convert("RGBA")).astype(float)
    al = np.maximum(a[:, :, 3:] / 255, 1e-6)
    a[:, :, :3] = np.clip((a[:, :, :3] - (1 - al) * 255) / al, 0, 255)
    a[:, :, :3] *= a[:, :, 3:] / 255
    r = np.array(Image.fromarray(a.round().astype(np.uint8)).resize((pix.W, pix.H), Image.BOX)).astype(float)
    r[:, :, :3] = np.clip(r[:, :, :3] / np.maximum(r[:, :, 3:] / 255, 1e-6), 0, 255)
    r[r[:, :, 3] == 0, :3] = 0
    return r


def convert(path):
    im = Image.open(path).convert("RGBA")
    r = resize(im)
    pal = palette()
    d = ((r[..., None, :3] - pal[None, None]) ** 2).sum(-1)
    q = pal[d.argmin(-1)].astype(np.uint8)
    a = np.where(r[:, :, 3] >= 128, 255, 0).astype(np.uint8)
    q[a == 0] = 0
    return np.dstack([q, a])


def reference(kind, n):
    """Box-resampled reference at master size (unquantised), for comparison."""
    im = Image.open(ref_path(kind, n)).convert("RGBA")
    return resize(im).round().astype(np.uint8)


def change_mask(kind, n, base, thr=28, min_blob=12):
    """Where the frame's own reference really differs from `base`, a
    (kind, n) reference of the master's rest pose at the same resolution
    (book [1] for the book set, idle [1] for the half-size sets)."""
    from scipy import ndimage
    r0 = reference(*base).astype(int)
    rn = reference(kind, n).astype(int)
    d = np.abs(rn - r0)[:, :, :3].max(-1)
    a0, an = r0[:, :, 3] >= 128, rn[:, :, 3] >= 128
    m = ((d > thr) & (a0 | an)) | (a0 != an)
    lab, k = ndimage.label(m, structure=np.ones((3, 3)))
    if k:
        sizes = ndimage.sum(m, lab, range(1, k + 1))
        m = np.isin(lab, 1 + np.nonzero(sizes >= min_blob)[0])
    m = ndimage.binary_closing(m, structure=np.ones((3, 3)))
    m = ndimage.binary_dilation(m, structure=np.ones((3, 3)))
    # a moving part (e.g. the leaning head) is taken whole from this frame,
    # not as a mosaic with master pixels where its texture happens to match
    m = ndimage.binary_fill_holes(m)
    return m


def fix_outline(master, kind="book", n=1):
    """Clean the master's outermost outline ring against its own reference
    resampled correctly (matte removed, premultiplied): background remnants
    on the ring are dropped, the rest of the ring is recoloured."""
    from scipy import ndimage
    al = master[:, :, 3] > 0
    ring = al & ~ndimage.binary_erosion(al, structure=np.ones((3, 3)))
    r = resize(Image.open(ref_path(kind, n)).convert("RGBA"))
    # The master's silhouette is a little fuller than the reference: some
    # outline cells are less than half covered and hold only the light grey of
    # the white background the art was cut from. Recolouring them would leave
    # a dark bump (e.g. beside the left boot); they are dropped instead.
    c = master[:, :, :3].astype(int)
    matte = ((c @ np.array([3, 6, 1])) // 10 > 140) & ((c.max(-1) - c.min(-1)) < 45)
    remnant = ring & (r[:, :, 3] < 128) & matte
    master = master.copy()
    master[remnant] = 0
    al = al & ~remnant
    ring = ring & ~remnant
    # true (matte-free) colour of whatever covers each ring cell, even where
    # that coverage is under 50% (the master's silhouette is a little fuller)
    ok = ring & (r[:, :, 3] > 0)
    pal = palette()
    d = ((r[..., None, :3] - pal[None, None]) ** 2).sum(-1)
    q = pal[d.argmin(-1)].astype(np.uint8)
    out = master.copy()
    out[ok, :3] = q[ok]
    # a thinly covered cell can catch a highlight; an outline cell much
    # lighter than its darkest inner neighbour takes that neighbour's colour
    lum = out[:, :, :3].astype(int) @ np.array([3, 6, 1]) // 10
    inner = al & ~ring
    for y, x in zip(*np.nonzero(ring)):
        nb = [(y + dy, x + dx) for dy, dx in ((-1, 0), (1, 0), (0, -1), (0, 1), (-1, -1), (-1, 1), (1, -1), (1, 1))
              if 0 <= y + dy < out.shape[0] and 0 <= x + dx < out.shape[1] and al[y + dy, x + dx]]
        if not nb:
            continue
        yy, xx = min(nb, key=lambda p: lum[p])
        lighter_than_all = all(lum[y, x] > lum[p] + 60 for p in nb)
        if (r[y, x, 3] < 128 and lum[y, x] > lum[yy, xx] + 80) or (lighter_than_all and lum[y, x] > 170):
            out[y, x, :3] = out[yy, xx, :3]
    return out, int(ok.sum()) + int(remnant.sum())


def locked(shape=(328, 208)):
    """Legs, boots and the chair below the book never move in any sequence:
    always the master's pixels (no per-frame shimmer on the outlines). The
    strip right under the book stays open only where the right hand hangs
    over the page edge in book-02/03/11 (x 136-166)."""
    m = np.zeros(shape, bool)
    m[205:, :] = True
    m[196:205, :136] = True
    m[196:205, 167:] = True
    return m


def frame(kind, n, base, master):
    """Master where the original does not change; this frame's own converted
    reference wherever it does."""
    conv = convert(ref_path(kind, n))
    m = change_mask(kind, n, base) & ~locked()
    out = master.copy()
    out[m] = conv[m]
    return out, m


def adopt_hair(master, kind="book", n=5, dx=2, dy=0):
    """Take the hair (lavender-grey strands and their outline) from another
    frame of the same head: book ref [5] draws the twin tails with a cleaner
    edge and its head sits exactly 2 px left of the rest pose, so its
    conversion shifted back by (dx, dy) lines up with the master. Face, eyes,
    ears and the red hair ties stay the master's."""
    from scipy import ndimage
    import layers
    donor = convert(ref_path(kind, n))
    donor = layers.shift(donor, dx, dy)
    head = layers.head_mask(master)

    def hairish(im):
        c = im[:, :, :3].astype(int)
        r, b = c[:, :, 0], c[:, :, 2]
        sat = c.max(-1) - c.min(-1)
        return (im[:, :, 3] > 0) & (b >= r - 4) & (sat < 50)

    def hairish_any(a, b):
        return hairish(a) & hairish(b)
    # the head layer and one cell around it: the edge may move by a cell, but
    # the cape below the tails (moved differently in that frame) is not hair
    zone = ndimage.binary_dilation(head, iterations=1) & (layers.YY < 160)
    zone &= ~(layers.torso_mask(master, head) & ~hairish_any(master, donor))
    # the face (eyes, lashes, cheeks, mouth) stays the master's
    zone[78:120, 82:129] = False

    # hair in either image, plus background cells next to it (edge changes)
    hair = (hairish(master) | hairish(donor)) & zone
    bg = zone & ((master[:, :, 3] == 0) | (donor[:, :, 3] == 0))
    take = hair | (bg & ndimage.binary_dilation(hair, iterations=1))
    # never touch skin / ties / eyes: cells that are warm in either image
    warm = lambda im: (im[:, :, 3] > 0) & ~hairish(im) & (im[:, :, :3].astype(int).max(-1) > 70)
    take &= ~warm(master) | ~(donor[:, :, 3] > 0) | hairish(donor)
    take &= ~(warm(master) & warm(donor))
    out = master.copy()
    out[take] = donor[take]
    return out, int(np.any(out != master, axis=2).sum())
