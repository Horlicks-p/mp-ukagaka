"""Frieren's travel case, redrawn as pixel art after the original design
(flat olive-brown hard case that reads like wood, steel frame on every
edge with corner plates, dark brown arch handle) in the current decoration's pose, size (90x80)
and silhouette, so its placement and hit area stay as they were.
References: ghost/Frieren/decorations/suitcase original sample_.webp
(the anime design) and suitcase sample.png.

The geometry below is a projected box: left side face, front face and a
thin strip of the lid, all in decoration pixels."""
import os
import numpy as np
from PIL import Image, ImageDraw
import pix

W, H = 90, 80
C = {
    "out": "#2a1710",
    "m0": "#e6e7e4", "m1": "#b4b5b0", "m2": "#86877f", "m3": "#57574f",
    "f0": "#a27a50", "f1": "#936d46", "f2": "#84623e", "f3": "#735437", "f4": "#5e452d",
    "s0": "#62492e", "s1": "#574229", "s2": "#483725",
    "t0": "#9a7349", "t1": "#82613d",
    "h0": "#7a5d47", "h1": "#4d3c32", "h2": "#2e231d",
    "sh": "#3a3430",
}

# projected box corners
FTL, FTR, FBR, FBL = (15, 23), (87, 19), (87, 69), (15, 76)     # front face
STL, SBL = (4, 18), (4, 69)                                       # side face back edge
TBR = (76, 13)                                                    # lid back-right


def rgb(c):
    return [int(c[i:i + 2], 16) for i in (1, 3, 5)] + [255]


def poly(pts):
    img = Image.new("1", (W, H), 0)
    ImageDraw.Draw(img).polygon(pts, fill=1, outline=1)
    return np.array(img, bool)


def line(pts, width=1):
    img = Image.new("1", (W, H), 0)
    ImageDraw.Draw(img).line(pts, fill=1, width=width)
    return np.array(img, bool)


def put(im, mask, c):
    im[mask] = rgb(C[c])


def shade(im, mask, ramp, gx, gy, stops, band=0.06):
    """Flat colour bands across the mask along (gx, gy); `stops` are the
    band edges (0..1). Only a narrow strip at each edge is checker-dithered,
    like the reference's pixel shading."""
    ys, xs = np.nonzero(mask)
    t = xs * gx + ys * gy
    t = (t - t.min()) / max(t.max() - t.min(), 1)
    k = np.searchsorted(np.array(stops), t)
    checker = (xs + ys) % 2 == 0
    for i, edge in enumerate(stops):
        near = np.abs(t - edge) < band
        k = np.where(near & checker, i + (t < edge), k)
        k = np.where(near & ~checker, i + (t >= edge), k)
    k = np.clip(k, 0, len(ramp) - 1)
    for i, c in enumerate(ramp):
        sel = k == i
        im[ys[sel], xs[sel]] = rgb(C[c])


def edge_shade(im, mask, ramp, dark_edges, light_edges, dark_reach=12, light_reach=4):
    """Leather shading: flat base, darker toward `dark_edges` (pairs of
    points), a thin lighter strip under `light_edges`; checker dither on the
    band edges."""
    from scipy import ndimage
    ys, xs = np.nonzero(mask)

    def dist(edges):
        m = np.zeros((H, W), bool)
        for a, b in edges:
            m |= line([a, b])
        return ndimage.distance_transform_edt(~m)[ys, xs]
    dd = dist(dark_edges)
    dl = dist(light_edges)
    v = np.clip(1 - dd / dark_reach, 0, 1)            # 0 = base .. 1 = darkest
    k = 1 + np.floor(v * (len(ramp) - 1.001)).astype(int)
    frac = v * (len(ramp) - 1.001) - np.floor(v * (len(ramp) - 1.001))
    checker = (xs + ys) % 2 == 0
    k = np.where((frac > 0.6) & checker, k + 1, k)
    k = np.where(dl < light_reach, 0, k)
    k = np.where((dl >= light_reach) & (dl < light_reach + 1.5) & checker, 0, k)
    k = np.clip(k, 0, len(ramp))
    full = ["__light"] + list(ramp)
    for i in range(len(full)):
        sel = k == i
        c = full[i]
        im[ys[sel], xs[sel]] = rgb(C["f0"] if c == "__light" else C[c])


def planks(im, face, top_edge, bottom_edge, n, ramp, seam, seed, light_first=True):
    """Wooden planks on a projected face: `n` boards between the two edges
    (each edge a pair of points, left to right), a dark seam between boards,
    a lit 1 px top on each board and short grain streaks along the boards."""
    rng = np.random.RandomState(seed)
    ys, xs = np.nonzero(face)
    (ax0, ay0), (ax1, ay1) = top_edge
    (bx0, by0), (bx1, by1) = bottom_edge

    def edge_y(x, p0, p1):
        return p0[1] + (p1[1] - p0[1]) * (x - p0[0]) / max(p1[0] - p0[0], 1)
    yt = edge_y(xs, (ax0, ay0), (ax1, ay1))
    yb = edge_y(xs, (bx0, by0), (bx1, by1))
    v = (ys - yt) / np.maximum(yb - yt, 1) * n           # 0..n down the face
    board = np.clip(np.floor(v).astype(int), 0, n - 1)
    f = v - np.floor(v)
    hgt = (yb - yt) / n
    base = np.array([1, 2, 1, 2, 1, 2, 1])[board % 7]    # alternate board tones
    k = base.copy()
    k = np.where(f * hgt < 1.0, 0, k)                   # lit board top
    k = np.where(f * hgt > hgt - 1.2, 3, k)             # board bottom shade
    # grain: horizontal streaks, a few px long, darker
    grain = np.zeros(len(xs), bool)
    for b in range(n):
        for _ in range(9):
            gx = rng.randint(xs.min(), xs.max())
            gl = rng.randint(4, 12)
            gf = rng.uniform(0.3, 0.75)
            sel = (board == b) & (xs >= gx) & (xs < gx + gl) & (np.abs(f - gf) * hgt < 0.5)
            grain |= sel
    k = np.where(grain & (k > 0), np.minimum(k + 1, 3), k)
    for i, c in enumerate(ramp):
        sel = k == i
        im[ys[sel], xs[sel]] = rgb(C[c])
    seams = (f * hgt < 0.6) & (board > 0)
    im[ys[seams], xs[seams]] = rgb(C[seam])


def draw():
    im = np.zeros((H, W, 4), np.uint8)

    front = poly([FTL, FTR, FBR, FBL])
    side = poly([STL, FTL, FBL, SBL])
    lid = poly([STL, TBR, FTR, FTL])
    # flat case surface: one dominant tone, a soft lit area upper left and a
    # darker band inside the frame (the inset), checker-dithered transitions
    shade(im, side, ["s0", "s1", "s2"], 0.2, 1.0, [0.5, 0.88], band=0.02)
    shade(im, front, ["f0", "f1", "f2"], 0.75, 0.65, [0.16, 0.8], band=0.015)
    from scipy import ndimage
    inset = front & ~ndimage.binary_erosion(front, iterations=4)
    ys_, xs_ = np.nonzero(inset)
    for y, x in zip(ys_, xs_):
        if (x + y) % 2 == 0 or not ndimage.binary_erosion(front, iterations=2)[y, x]:
            im[y, x] = rgb(C["f3"])
    # light falls from the upper left: darken the right end and the bottom
    from scipy import ndimage
    ys, xs = np.nonzero(front)
    shadow_zone = (xs > FTR[0] - 9) | (ys > FBL[1] - 6 - (xs - FBL[0]) * (FBL[1] - FBR[1]) / (FBR[0] - FBL[0]))
    lut = {tuple(rgb(C[a])): rgb(C[b]) for a, b in (("f0", "f1"), ("f1", "f2"), ("f2", "f3"), ("f3", "f4"))}
    for y, x in zip(ys[shadow_zone], xs[shadow_zone]):
        if (x + y) % 2 == 0 or x > FTR[0] - 5:
            t = tuple(im[y, x])
            if t in lut:
                im[y, x] = lut[t]
    shade(im, lid, ["t0", "t1"], 1.0, 0.3, [0.6])

    # lid seam on the side face and across the lid (two halves of the case)

    # steel frame along every visible edge: dark outer line + two silvers
    def frame(a, b, tone):
        put(im, line([a, b], 5), "m3")
        put(im, line([a, b], 3), tone)
    frame(STL, TBR, "m1")
    frame(TBR, FTR, "m2")
    frame(STL, SBL, "m2")
    frame(SBL, FBL, "m3")
    frame(STL, FTL, "m1")
    frame(FTR, FBR, "m2")
    frame(FBL, FBR, "m2")
    frame(FTL, FBL, "m1")
    frame(FTL, FTR, "m1")
    put(im, line([(FTL[0] + 2, FTL[1] - 1), (FTR[0] - 3, FTR[1] - 1)], 2), "m0")   # lit top edge
    put(im, line([(FTL[0] - 1, FTL[1] + 3), (FBL[0] - 1, FBL[1] - 3)], 2), "m0")   # lit front-left edge
    # corner caps
    for (x, y) in (FTL, FTR, FBL, FBR, STL, SBL):
        put(im, poly([(x - 3, y - 3), (x + 2, y - 3), (x + 2, y + 2), (x - 3, y + 2)]), "m3")
        put(im, poly([(x - 2, y - 2), (x + 1, y - 2), (x + 1, y + 1), (x - 2, y + 1)]), "m1")
        put(im, poly([(x - 2, y - 2), (x - 1, y - 2), (x - 1, y - 1), (x - 2, y - 1)]), "m0")

    # two small flat latches on the top, either side of the handle (seen in
    # the anime key visual): a grey plate, lit top edge, dark slot, outline
    for cx, cy in ((26, 19), (71, 16)):
        put(im, poly([(cx - 4, cy - 2), (cx + 3, cy - 3), (cx + 3, cy + 2), (cx - 4, cy + 3)]), "out")
        put(im, poly([(cx - 3, cy - 2), (cx + 2, cy - 2), (cx + 2, cy + 1), (cx - 3, cy + 2)]), "m1")
        put(im, line([(cx - 3, cy - 2), (cx + 2, cy - 2)]), "m0")
        put(im, line([(cx - 2, cy), (cx + 1, cy)]), "m3")

    # dark brown arch handle
    put(im, line([(40, 19), (41, 10), (44, 6), (54, 5), (57, 8), (58, 17)], 6), "out")
    put(im, line([(40, 19), (41, 10), (44, 6), (54, 5), (57, 8), (58, 17)], 4), "h1")
    put(im, line([(41, 11), (44, 7), (53, 6)]), "h0")
    for x, y in ((40, 19), (58, 17)):          # handle mounts
        put(im, poly([(x - 3, y - 2), (x + 3, y - 2), (x + 3, y + 2), (x - 3, y + 2)]), "out")
        put(im, poly([(x - 1, y - 1), (x + 2, y - 1), (x + 2, y + 1), (x - 1, y + 1)]), "m2")

    # handle rim light so it reads on dark grounds
    put(im, line([(41, 10), (43, 6), (53, 5)], 2), "h0")
    put(im, line([(56, 7), (57, 9)]), "h0")

    # 1 px dark outline around the case (not the shadow)
    body = side | front | lid | line([(40, 19), (41, 10), (44, 6), (54, 5), (57, 8), (58, 17)], 6)
    from scipy import ndimage
    ring = ndimage.binary_dilation(body, iterations=2) & ~body
    im[ring] = rgb(C["out"])

    # lift the case 2 px (the handle has room above) so the ground shadow
    # fits under it inside the 90x80 box
    im = np.roll(im, -2, axis=0)
    im[-2:] = 0

    # ground shadow parallel to the case's bottom edges: the case's footprint
    # (side-bottom and front-bottom edges) pushed down/right by the light
    # from the upper left; a darker band hugging the case, a lighter rim
    def foot(dx, dy):
        sb, fb, fr = (SBL[0] + dx, SBL[1] - 2 + dy), (FBL[0] + dx, FBL[1] - 2 + dy), (FBR[0] + dx, FBR[1] - 2 + dy)
        return poly([(SBL[0], SBL[1] - 6), (FBR[0], FBR[1] - 6), fr, fb, sb])
    for (dx, dy), a in (((3, 5), 64), ((2, 3), 128)):
        m = foot(dx, dy) & (im[:, :, 3] == 0)
        im[m, :3] = rgb(C["sh"])[:3]
        im[m, 3] = a
    return im


if __name__ == "__main__":
    im = draw()
    pix.save("suitcase-new", im)
    cur = np.array(Image.open(os.path.join(pix.ROOT, "ghost", "Frieren", "decorations", "suitcase.png")).convert("RGBA"))
    pix.show(pix.row([pix.zoom(cur, None, 6, grid=False), pix.zoom(im, None, 6, grid=False)]), "suitcase_draft")
