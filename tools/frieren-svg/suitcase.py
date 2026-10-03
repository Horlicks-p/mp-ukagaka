"""Frieren's travel case for the decoration layer (100x90, development only).

Converted from refs/suitcase_sample.png (the case
design the user approved): the sample is cropped to the case, resampled to
the old decoration's footprint and split by colour into outline, steel
frame / latches, handle and the case body. Outline, steel and handle keep
the sample's own colours; the body is recoloured with the olive-brown of
the previous suitcase.svg, each body pixel taking the body colour closest
in lightness. A soft ground shadow parallel to the bottom edges is added
as before (alpha steps 64 / 128).

The canvas is 100x90 (was 90x80; enlarged so the small latches read).
decorations.json shows it at 100 px wide with its centre where the 90x80
case's was, and the pixel hit test (alpha > 10) follows the drawing."""
import os
import numpy as np
from PIL import Image
from scipy import ndimage
import pix

W, H = 100, 90
SAMPLE = os.path.join(pix.HERE, "refs", "suitcase_sample.png")

# footprint of the case in the canvas: the full width less a 1 px margin,
# with room below for the ground shadow
BOX_LEFT, BOX_RIGHT, BOX_BOTTOM = 1, W - 1, H - 7

# body colours of the previous suitcase.svg (front, side, lid)
BODY = ["#a27a50", "#9a7349", "#936d46", "#84623e", "#82613d", "#735437",
        "#62492e", "#5e452d", "#574229", "#483725"]
SHADOW = "#3a3430"
SHADOW_FROM_ROW = 15
BODY_TONES = 6
OUTLINE = "#2a1c14"       # warm dark brown, between the sample's near-black and the staff / books outlines
STEEL_DARK = "#575a66"

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


STEEL = {"hi": "#d0d3db", "light": "#b3b9c5", "mid": "#989dab", "low": "#6b738c"}
# where the handle sits in the canvas (its brown is not lid)
HANDLE_CANVAS = (39, 0, 65, 19)
# side face seam: the clean upper-half row and the columns / rows it fills
SEAM_ROW, SEAM_END, SEAM_X0, SEAM_X1 = 40, 74, 7, 15
# side face bottom edge: bottom outline row at x 2 and its slope per column
SIDE_BOTTOM_Y0, SIDE_BOTTOM_SLOPE = 73, 0.7
SIDE_PANEL_ROW = 66   # a clean row of the side panel above the ragged bottom
# front bottom edge: columns redrawn, and how far above the edge the front
# panel colour is sampled (above the old dark staircase)
FRONT_BOTTOM_X0, FRONT_BOTTOM_X1, FRONT_FACE_ABOVE = 24, 93, 9
# front-left corner: the vertical corner rail's columns (outline + steel)
# and a clean row of it above the ragged bottom
CORNER_X0, CORNER_X1, CORNER_ROW = 15, 21, 70
# steel corner protectors at the two left bottom corners, like the one the
# sample shows at the right: (x0, x1, y0, y1) of each, outline included
CORNER_CAPS = ((1, 5, 69, 75), (15, 21, 76, 83))


def steel_rails(im):
    """The sample shows these as brown / dark, but the case design (refs/
    suitcase sample.png, the original look) has a steel rail on every edge:
    the lid's back edge, the side face's back edge and the side face's
    middle seam. Paint 2 px steel just inside the outline there; latches,
    handle and the existing front / bottom frame are left as they are."""
    a = im[:, :, 3] == 255
    hx0, hy0, hx1, hy1 = HANDLE_CANVAS

    def is_steel(px):
        r, g, b = (int(v) for v in px[:3])
        return b > r and (max(r, g, b) - min(r, g, b)) < 45 and r > 70

    def paint(y, x, colour):
        if a[y, x] and not is_steel(im[y, x]) and not (hx0 <= x < hx1 and hy0 <= y < hy1):
            im[y, x, :3] = rgb(STEEL[colour])

    # lid back edge: the first two cells under the top outline
    for x in range(6, W - 4):
        ys = np.nonzero(a[:, x])[0]
        if len(ys):
            y0 = ys[0]
            paint(y0 + 1, x, "light")
            paint(y0 + 2, x, "low")
    # side face back edge: the first two cells inside the left outline
    for y in range(20, 72):
        xs = np.nonzero(a[y, :20])[0]
        if len(xs):
            x0 = xs[0]
            paint(y, x0 + 1, "mid")
            paint(y, x0 + 2, "low")
    # side face middle seam: brighten the thin grey line into a rail
    for y in range(22, 78):
        for x in range(6, 12):
            c = pix.hexc(im[y, x])
            if c in ("#575a66", "#565867"):
                im[y, x, :3] = rgb(STEEL["mid"])
            elif c == "#6b738c":
                im[y, x, :3] = rgb(STEEL["light"])
    # the side face is a flat vertical panel, but the resampled sample
    # shifts the seam one cell right below row 48; continue the upper
    # half's columns (outline, grey, light, outline, brown) straight down
    seam = im[SEAM_ROW, SEAM_X0:SEAM_X1].copy()
    for y in range(SEAM_ROW + 1, SEAM_END):
        im[y, SEAM_X0:SEAM_X1] = seam

    # side face bottom edge: a steel rail like the front's bottom frame. The
    # resampled outline there is ragged, so the edge is drawn on the side
    # face's perspective line, from the back corner (x 2) down to the front
    # corner (x 14): outline, light, mid, outline from the top, nothing below
    for x in range(2, 15):
        yo = round(SIDE_BOTTOM_Y0 + (x - 2) * SIDE_BOTTOM_SLOPE)
        # the panel above (back rail, brown, seam) runs straight down to it
        im[SIDE_PANEL_ROW + 1:yo - 3, x] = im[SIDE_PANEL_ROW, x]
        im[yo - 3, x] = list(rgb(OUTLINE)) + [255]
        im[yo - 2, x] = list(rgb(STEEL["light"])) + [255]
        im[yo - 1, x] = list(rgb(STEEL["mid"])) + [255]
        im[yo, x] = list(rgb(OUTLINE)) + [255]
        im[yo + 1:, x] = 0
        # where the seam meets the rail it joins it: no outline across it
        if x in (SEAM_X0 + 1, SEAM_X0 + 2):
            im[yo - 3, x] = im[SIDE_PANEL_ROW, x]

    # front face bottom edge (where the case stands): the converted sample
    # leaves a ragged dark staircase there; redraw it like the side's edge,
    # on the straight line fitted to the current bottom, with the front
    # panel's own colour continued down to it
    xs = np.arange(FRONT_BOTTOM_X0, FRONT_BOTTOM_X1)
    yb = np.array([np.nonzero(im[:, x, 3] == 255)[0][-1] for x in xs])
    slope, icpt = np.polyfit(xs, yb, 1)
    for x in xs:
        yo = int(round(icpt + slope * x))
        face = im[yo - FRONT_FACE_ABOVE, x].copy()
        im[yo - FRONT_FACE_ABOVE + 1:yo - 3, x] = face
        im[yo - 3, x] = list(rgb(OUTLINE)) + [255]
        im[yo - 2, x] = list(rgb(STEEL["light"])) + [255]
        im[yo - 1, x] = list(rgb(STEEL["mid"])) + [255]
        im[yo, x] = list(rgb(OUTLINE)) + [255]
        im[yo + 1:, x] = 0

    # front-left bottom corner: the two bottom rails meet under the vertical
    # corner rail, whose steel runs straight down to the bottom outline; the
    # few columns between it and the front rail get the front rail's layers
    # (the corner's bottom is flat at the front rail's level there, not a
    # spike where the two lines would cross)
    floor_y = int(round(icpt + slope * CORNER_X1))
    for x in range(CORNER_X0, FRONT_BOTTOM_X0):
        yo = min(int(round(SIDE_BOTTOM_Y0 + (x - 2) * SIDE_BOTTOM_SLOPE)),
                 int(round(icpt + slope * x)), floor_y)
        im[CORNER_ROW + 1:yo, x] = im[CORNER_ROW, x]
        if x >= CORNER_X1:
            im[yo - 3, x] = list(rgb(OUTLINE)) + [255]
            im[yo - 2, x] = list(rgb(STEEL["light"])) + [255]
            im[yo - 1, x] = list(rgb(STEEL["mid"])) + [255]
        im[yo, x] = list(rgb(OUTLINE)) + [255]
        im[yo + 1:, x] = 0

    # corner protectors: a steel plate over each left bottom corner, drawn
    # like the right one (outline, mid steel, the outer column dark, the
    # bottom row low), nothing below it
    for x0, x1, y0, y1 in CORNER_CAPS:
        im[y0:y1 + 1, x0:x1 + 1] = list(rgb(OUTLINE)) + [255]
        im[y0 + 1:y1, x0 + 1:x1] = list(rgb(STEEL["mid"])) + [255]
        im[y0 + 1:y1, x0 + 1] = list(rgb(STEEL_DARK)) + [255]
        im[y1 - 1, x0 + 1:x1] = list(rgb(STEEL["low"])) + [255]
        im[y1 + 1:, x0:x1 + 1] = 0


# the lid, drawn rather than converted: the sample's pixel blocks are about
# 2 canvas cells and fall between them, so its slanted top edges come out
# as broken staircases. Each edge is a straight line (y or x per column)
LID_BACK = (3, 16.5, -0.1717)    # back edge: x0, y at x0, slope
LID_FRONT = (33, 24, -0.196)     # front edge (top of its rail)
LID_LEFT = (2, 17, 0.62)         # left edge, over the side face
LID_RIGHT = (84, 3, 0.54)        # right edge
LID_BR_X, LID_FL_X = 84, 15      # where the back / front edges turn
LID_TOP = "#a27a50"
FACE = "#936d46"
FACE_SHADE = "#483725"
HANDLE = {"hi": "#844f26", "mid": "#6e462b", "dark": "#512e20"}
# clean rows below the redrawn part: side face, front-left rail, right rail
SIDE_CLEAN_ROW, FRONT_LEFT_CLEAN_ROW, RIGHT_CLEAN_ROW = 30, 33, 18
# top corner protectors (x0, x1, y0, y1, outer side)
TOP_CAPS = ((1, 5, 15, 21, "left"), (15, 21, 24, 31, "left"), (93, 98, 8, 15, "right"))
HANDLE_X = (42, 60)              # handle span; posts are 5 wide
HANDLE_RISE = 13                 # grip top above the post feet
LATCHES = ((21, 12), (71, 12))   # (x0, width) of the two clasps


def line(x, spec):
    x0, y0, k = spec
    return int(np.floor(y0 + k * (x - x0) + 0.5))


def refine_top(im):
    """Redraw the upper part (lid, its four edge rails, the top corners,
    handle and clasps) on straight perspective lines; the converted body
    below is kept and its clean rows continue up to the new edges."""
    def put(y, x, c):
        if 0 <= y < H:
            im[y, x] = list(rgb(c)) + [255]

    side = im[SIDE_CLEAN_ROW].copy()
    front_left = im[FRONT_LEFT_CLEAN_ROW].copy()
    right = im[RIGHT_CLEAN_ROW].copy()
    for x in range(1, W):
        front = line(x, LID_FRONT)
        # clear exactly what is redrawn below
        if x < LID_FL_X:
            im[:SIDE_CLEAN_ROW, x] = 0
        elif x <= CORNER_X1:
            im[:FRONT_LEFT_CLEAN_ROW, x] = 0
        elif x >= 93:
            im[:RIGHT_CLEAN_ROW, x] = 0
        else:
            im[:front + 15, x] = 0
        if x <= 1:
            continue
        top = line(x, LID_BACK) if x <= LID_BR_X else line(x, LID_RIGHT)
        put(top, x, OUTLINE)
        put(top + 1, x, STEEL["light"])
        put(top + 2, x, STEEL["low"] if x <= LID_BR_X else STEEL["mid"])
        if x < LID_FL_X:
            e = line(x, LID_LEFT)
            for y in range(top + 3, e):
                put(y, x, LID_TOP)
            for y, c in zip(range(e, e + 4), (OUTLINE, STEEL["hi"], STEEL["mid"], OUTLINE)):
                put(y, x, c)
            # the side face (and its seam, which joins the rail) runs up to it
            if x in (SEAM_X0 + 1, SEAM_X0 + 2):
                im[e + 3, x] = side[x]
            im[e + 4:SIDE_CLEAN_ROW, x] = side[x]
            continue
        for y in range(top + 3, front):
            put(y, x, LID_TOP)
        for y, c in zip(range(front, front + 5),
                        (OUTLINE, STEEL["hi"], STEEL["light"], STEEL["mid"], OUTLINE)):
            put(y, x, c)
        if x <= CORNER_X1:
            im[front + 5:FRONT_LEFT_CLEAN_ROW, x] = front_left[x]
        elif x >= 93:
            im[front + 5:RIGHT_CLEAN_ROW, x] = right[x]
        else:
            put(front + 5, x, FACE_SHADE)
            for y in range(front + 6, front + 15):
                put(y, x, FACE)
    im[:, 0] = np.where(np.arange(H)[:, None] < SIDE_CLEAN_ROW, 0, im[:, 0])

    # clasps: a bevelled steel plate standing on the front rail with a slot
    for x0, w in LATCHES:
        for x in range(x0, x0 + w):
            f = line(x, LID_FRONT)
            if x in (x0, x0 + w - 1):
                for y in range(f - 5, f):
                    put(y, x, OUTLINE)
                continue
            for y, c in zip(range(f - 5, f), (OUTLINE, STEEL["hi"], STEEL["light"],
                                               STEEL["mid"], STEEL["low"])):
                put(y, x, c)
            if abs(x - (x0 + w / 2 - 0.5)) < 1:
                put(f - 3, x, OUTLINE)
                put(f - 2, x, OUTLINE)

    # handle: two posts on steel feet and the grip across them
    hx0, hx1 = HANDLE_X
    posts = (range(hx0, hx0 + 5), range(hx1 - 4, hx1 + 1))
    for x in range(hx0, hx1 + 1):
        base = line(x, LID_FRONT) - 5
        g = base - HANDLE_RISE
        if x in (hx0, hx1):
            for y in range(g, base - 2):
                put(y, x, OUTLINE)
            continue
        for y, c in zip(range(g, g + 4), (OUTLINE, HANDLE["hi"], HANDLE["dark"], OUTLINE)):
            put(y, x, c)
        post = next((p for p in posts if x in p), None)
        if post is None:
            if pix.hexc(im[g + 4, x]) == LID_TOP:
                put(g + 4, x, FACE_SHADE)     # the grip's shadow on the lid
            continue
        c = HANDLE["mid"] if x - post[0] == 1 else (OUTLINE if x - post[0] == 4 else HANDLE["dark"])
        for y in range(g + 4, base - 2):
            put(y, x, c)
    for p in posts:
        for x in range(p[0] - 1, p[-1] + 2):
            base = line(x, LID_FRONT) - 5
            end = x in (p[0] - 1, p[-1] + 1)
            put(base - 2, x, OUTLINE if end else STEEL["light"])
            put(base - 1, x, OUTLINE if end else STEEL["mid"])
            put(base, x, OUTLINE)

    for x0, x1, y0, y1, outer in TOP_CAPS:
        im[y0:y1 + 1, x0:x1 + 1] = list(rgb(OUTLINE)) + [255]
        im[y0 + 1:y1, x0 + 1:x1] = list(rgb(STEEL["mid"])) + [255]
        im[y0 + 1, x0 + 1:x1] = list(rgb(STEEL["light"])) + [255]
        dark_x = x0 + 1 if outer == "left" else x1 - 1
        im[y0 + 2:y1, dark_x] = list(rgb(STEEL_DARK)) + [255]


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

    # the sample's near-black, cool outline and the navy shade of the steel
    # read much harder than the other decorations' warm dark outlines:
    # soften them toward the case's own dark browns / greys
    k = out[:, :, :3].astype(int)
    kl = lum(k)
    ksat = k.max(-1) - k.min(-1)
    outline = alpha & ~body & (kl < 30)
    out[outline, :3] = rgb(OUTLINE)
    steel_dark = alpha & ~body & ~outline & (kl < 70) & (ksat < 45) & (k[:, :, 2] > k[:, :, 0])
    out[steel_dark, :3] = rgb(STEEL_DARK)

    # lone pixels left by resampling
    nb = ndimage.convolve(alpha.astype(int), np.ones((3, 3), int), mode="constant") - alpha
    out[alpha & (nb == 0)] = 0

    im = np.zeros((H, W, 4), np.uint8)
    top = BOX_BOTTOM + 1 - bh
    im[top:top + bh, BOX_LEFT:BOX_LEFT + bw] = out
    steel_rails(im)
    refine_top(im)

    # ground shadow parallel to the bottom edges, below/right of the case
    case = im[:, :, 3] > 0
    for (dx, dy), a_ in (((3, 5), 64), ((2, 3), 128)):
        moved = np.zeros_like(case)
        moved[dy:, dx:] = case[:H - dy, :W - dx]
        m = moved & ~case & (im[:, :, 3] == 0)
        m[:SHADOW_FROM_ROW] = False       # not inside the handle's arch
        im[m, :3] = rgb(SHADOW)
        im[m, 3] = a_
    return im


if __name__ == "__main__":
    im = draw()
    pix.show(pix.zoom(im, None, 7, grid=False), "suitcase_from_sample")
