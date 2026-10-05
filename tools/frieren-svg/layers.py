"""Rigid layers of the master (head, torso) and phase-locked composition.

A head that sinks 0.72 px in the reference must move by a whole master pixel,
otherwise every frame samples its lines at a different phase and a 1.5 px eye
line renders 1 px in one frame and 2 px in the next. So per frame:
  1. measure the head / torso motion in the frame's own reference (motion.py)
     and round it to whole master pixels;
  2. move the master's head / torso layers by exactly that (unchanged hair,
     cape and face pixels stay identical between frames);
  3. where the reference content itself changes (eyes, mouth, hair tips),
     convert the reference translated by the rounding remainder, so those
     pixels are sampled at the same phase as the master.
"""
import numpy as np
from PIL import Image
from scipy import ndimage
import pix
import convert
import motion

YY, XX = np.mgrid[0:pix.H, 0:pix.W]


def head_mask(m):
    """Face, ears and hair including the long side strands over the cape."""
    r, g, b, a = [m[:, :, i].astype(int) for i in range(4)]
    mx = np.maximum(np.maximum(r, g), b)
    mn = np.minimum(np.minimum(r, g), b)
    top = (a > 0) & (YY <= 112)
    side = ((XX >= 58) & (XX <= 92)) | ((XX >= 113) & (XX <= 154))
    cool = (b >= r) & ((mx - mn) < 45) & (mx > 60) & (a > 0) & side & (YY <= 150)
    dark = (mx < 70) & (a > 0) & side & (YY <= 150)
    hair = cool | (dark & ndimage.binary_dilation(cool))
    lab, _ = ndimage.label(top | hair, structure=np.ones((3, 3)))
    keep = np.unique(lab[top])
    mask = np.isin(lab, keep[keep > 0])
    filled = ndimage.binary_closing(mask, structure=np.ones((3, 3))) & (a > 0) & (side | (YY <= 112))
    out = mask | (filled & ~mask & (YY > 112) & side & ndimage.binary_dilation(cool))
    out[141:, :73] = False
    return out


def torso_mask(m, head):
    """Cape and shoulders between the chin and the cape hem."""
    return (m[:, :, 3] > 0) & ~head & (YY >= 113) & (YY <= 152)


def shift(arr, dx, dy):
    out = np.zeros_like(arr)
    h, w = arr.shape[:2]
    ys0, ys1 = max(0, dy), min(h, h + dy)
    xs0, xs1 = max(0, dx), min(w, w + dx)
    out[ys0:ys1, xs0:xs1] = arr[ys0 - dy:ys1 - dy, xs0 - dx:xs1 - dx]
    return out


def translated_ref(kind, n, dx_m, dy_m):
    """The frame's reference moved by (dx_m, dy_m) master px (sub-pixel)."""
    im = Image.open(convert.ref_path(kind, n)).convert("RGBA")
    sx = im.width / pix.W
    sy = im.height / pix.H
    # premultiplied so transparent edges do not darken
    a = np.array(im).astype(float)
    a[:, :, :3] *= a[:, :, 3:] / 255
    pm = Image.fromarray(a.round().astype(np.uint8))
    t = pm.transform(im.size, Image.AFFINE, (1, 0, -dx_m * sx, 0, 1, -dy_m * sy), resample=Image.BICUBIC)
    b = np.array(t).astype(float)
    b[:, :, :3] = b[:, :, :3] / np.maximum(b[:, :, 3:] / 255, 1e-6)
    return Image.fromarray(np.clip(b, 0, 255).round().astype(np.uint8))


def convert_image(im):
    r = convert.resize(im)
    pal = convert.palette()
    d = ((r[..., None, :3] - pal[None, None]) ** 2).sum(-1)
    q = pal[d.argmin(-1)].astype(np.uint8)
    a = np.where(r[:, :, 3] >= 128, 255, 0).astype(np.uint8)
    q[a == 0] = 0
    return np.dstack([q, a]), r


def reference_image(im):
    return convert.resize(im).round().astype(np.uint8)


def edge_band(alpha, width=1):
    """Pixels within `width` of the silhouette outline (inside or outside)."""
    return ndimage.binary_dilation(alpha, iterations=width) & ~ndimage.binary_erosion(alpha, iterations=width)


def changed(aligned, base_ref, region, thr=28, min_blob=12, outline=None):
    """Content change between the motion-aligned reference and the base.
    Alpha flips on the silhouette outline (`outline` band) are resampling,
    not drawing: they are ignored so the outline stays the moved master's."""
    d = np.abs(aligned.astype(int) - base_ref.astype(int))[:, :, :3].max(-1)
    a0, a1 = base_ref[:, :, 3] >= 128, aligned[:, :, 3] >= 128
    flips = a0 != a1
    if outline is not None:
        flips &= ~outline
    m = (((d > thr) & (a0 | a1)) | flips) & region
    if outline is not None:
        # outline colours mix with the transparent ground and drift with the
        # sampling phase: they never start a change on their own
        m &= ~outline
    lab, k = ndimage.label(m, structure=np.ones((3, 3)))
    if k:
        sizes = ndimage.sum(m, lab, range(1, k + 1))
        m = np.isin(lab, 1 + np.nonzero(sizes >= min_blob)[0])
    m = ndimage.binary_closing(m, structure=np.ones((3, 3)))
    m = ndimage.binary_dilation(m, structure=np.ones((3, 3))) & region
    return ndimage.binary_fill_holes(m) & region


_CACHE = {}


def frame(kind, n, base, master, content_base=None):
    """Compose one idle / sleep / wake frame (see module doc).

    `base` is the reference of the master's rest pose (motion is measured
    against it). `content_base` is an already-composed frame (kind, n) whose
    drawing this frame shares (the sleeping face for every sleep / closed
    wake frame, the first blink for the other blinks): its layers are moved
    and only content that differs from that reference is converted. Without
    it the master is the content base."""
    head = head_mask(master)
    torso = torso_mask(master, head)
    ref_b = motion.load(*base)
    ref_a = motion.load(kind, n)
    dh = motion.to_master(ref_a.shape, motion.measure(ref_a, ref_b, motion.HEAD))
    dt = motion.to_master(ref_a.shape, motion.measure(ref_a, ref_b, motion.TORSO))
    nh = (int(round(dh[0])), int(round(dh[1])))
    nt = (int(round(dt[0])), int(round(dt[1])))
    if content_base:
        src, binfo = _CACHE[content_base]
        cb_kind, cb_n = content_base
        cdh, cdt, bnh, bnt = binfo["head"], binfo["torso"], binfo["nh"], binfo["nt"]
    else:
        src, cb_kind, cb_n = master, base[0], base[1]
        cdh = cdt = (0.0, 0.0)
        bnh = bnt = (0, 0)
    rh = (nh[0] - bnh[0], nh[1] - bnh[1])
    rt = (nt[0] - bnt[0], nt[1] - bnt[1])
    head_b = shift(head, *bnh)
    torso_b = shift(torso, *bnt) & ~head_b
    # the content base may have its own converted pixels just outside the
    # master's head outline (hair tips, ear tips); they belong to the head and
    # must move with it, otherwise they stay behind or get cut between frames
    edge = ndimage.binary_dilation(head_b, iterations=2) & (src[:, :, 3] > 0) & ~torso_b & ~head_b
    head_b = head_b | (edge & (YY < 160))
    cb_ref = Image.open(convert.ref_path(cb_kind, cb_n)).convert("RGBA")

    # 1-2. rigid moves of the content base's layers
    out = src.copy()
    moving = head_b | torso_b
    out[moving] = 0
    t_layer = np.where(torso_b[..., None], src, 0).astype(src.dtype)
    h_layer = np.where(head_b[..., None], src, 0).astype(src.dtype)
    t_mask = shift(torso_b, *rt)
    h_mask = shift(head_b, *rh)
    out[t_mask] = shift(t_layer, *rt)[t_mask]
    out[h_mask] = shift(h_layer, *rh)[h_mask]

    # 3. content changes vs the content base, phase-locked per layer
    conv_t, _ = convert_image(translated_ref(kind, n, nt[0] - dt[0], nt[1] - dt[1]))
    conv_h, _ = convert_image(translated_ref(kind, n, nh[0] - dh[0], nh[1] - dh[1]))
    # both references brought to the content base's whole-pixel position
    cb_t = reference_image(translated_ref(cb_kind, cb_n, bnt[0] - cdt[0], bnt[1] - cdt[1]))
    cb_h = reference_image(translated_ref(cb_kind, cb_n, bnh[0] - cdh[0], bnh[1] - cdh[1]))
    al_t = reference_image(translated_ref(kind, n, bnt[0] - dt[0], bnt[1] - dt[1]))
    al_h = reference_image(translated_ref(kind, n, bnh[0] - dh[0], bnh[1] - dh[1]))
    band_b = edge_band(src[:, :, 3] > 0, width=2)
    ch_t = shift(changed(al_t, cb_t, torso_b, outline=band_b), *rt)
    ch_h = shift(changed(al_h, cb_h, head_b, outline=band_b), *rh)
    vacated = moving & ~t_mask & ~h_mask
    # What a moved layer uncovers: within the cape's reach it is cape /
    # collar, taken from the reference. Beside the ears and hair it is
    # background -- a reference sample there is the ear or hair itself at
    # another phase and would leave a sliver -- unless the cell ends up
    # enclosed by the character (neck under the chin): such holes are filled
    # from the reference, else the content base, else the nearest colour.
    reveal = vacated & ndimage.binary_dilation(t_mask, iterations=1)
    out[vacated & ~reveal] = 0
    out[reveal] = conv_t[reveal]
    opaque = out[:, :, 3] > 0
    hole = ndimage.binary_fill_holes(opaque) & ~opaque
    if hole.any():
        for source in (conv_t, src):
            fill = hole & (source[:, :, 3] > 0)
            out[fill] = source[fill]
            hole &= ~fill
        if hole.any():
            _, (iy, ix) = ndimage.distance_transform_edt(~(out[:, :, 3] > 0), return_indices=True)
            out[hole] = out[iy[hole], ix[hole]]
    out[ch_t & ~h_mask] = conv_t[ch_t & ~h_mask]
    out[ch_h] = conv_h[ch_h]
    # 4. safety net: wherever the composite disagrees with this frame's own
    # reference clearly beyond palette error (mask / move artefacts), take the
    # phase-locked conversion of that layer instead
    # the reference brought to the same whole-pixel positions as the layers
    actual = reference_image(Image.open(convert.ref_path(kind, n)).convert("RGBA")).astype(int)
    act_t = reference_image(translated_ref(kind, n, nt[0] - dt[0], nt[1] - dt[1])).astype(int)
    act_h = reference_image(translated_ref(kind, n, nh[0] - dh[0], nh[1] - dh[1])).astype(int)
    actual[t_mask | vacated] = act_t[t_mask | vacated]
    actual[h_mask] = act_h[h_mask]
    err = np.abs(out.astype(int) - actual)[:, :, :3].max(-1)
    # the master itself differs from the half-size base reference at sharp
    # edges (it was made from the 2x book reference); only flag errors well
    # beyond that baseline, carried along with each layer's move
    base_ref = reference_image(Image.open(convert.ref_path(*base)).convert("RGBA"))
    e0 = np.abs(master.astype(int) - base_ref.astype(int))[:, :, :3].max(-1)
    a0 = (master[:, :, 3] > 0) != (base_ref[:, :, 3] >= 128)
    e0_m = e0.copy()
    e0_m[t_mask] = shift(np.where(torso_b, e0, 0), *rt)[t_mask]
    e0_m[h_mask] = shift(np.where(head_b, e0, 0), *rh)[h_mask]
    a0_m = a0.copy()
    a0_m[t_mask] = shift(torso_b & a0, *rt)[t_mask]
    a0_m[h_mask] = shift(head_b & a0, *rh)[h_mask]
    bad = (((err > 70) & (err > e0_m + 50) & (out[:, :, 3] > 0) & (actual[:, :, 3] >= 128))
           | (((out[:, :, 3] > 0) != (actual[:, :, 3] >= 128)) & ~a0_m))
    # artefacts can only arise where layers moved or content was replaced;
    # the outline band of the moved silhouette is left alone (resampling)
    affected = (h_mask ^ head_b) | (t_mask ^ torso_b) | vacated | ch_h | ch_t
    bad &= ndimage.binary_dilation(affected, iterations=2)
    bad &= ~edge_band(out[:, :, 3] > 0, width=2)
    lab, k = ndimage.label(bad, structure=np.ones((3, 3)))
    if k:
        sizes = ndimage.sum(bad, lab, range(1, k + 1))
        bad = np.isin(lab, 1 + np.nonzero(sizes >= 3)[0])
    bad &= ~convert.locked()
    fix_h = bad & h_mask
    fix_t = bad & ~h_mask
    out[fix_h] = conv_h[fix_h]
    out[fix_t] = conv_t[fix_t]
    lock = convert.locked()
    out[lock] = master[lock]
    converted = (vacated | (ch_t & ~h_mask) | ch_h | fix_h | fix_t) & ~lock
    info = {"converted": converted, "fixed": int(bad.sum()), "head": dh, "torso": dt, "nh": nh, "nt": nt,
            "changed_head": int(ch_h.sum()), "changed_torso": int(ch_t.sum()), "vacated": int(vacated.sum())}
    _CACHE[(kind, n)] = (out, info)
    return out, info
