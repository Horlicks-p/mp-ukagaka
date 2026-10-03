"""Frieren's travel case for the decoration layer (100x90, development only).

Drawn after refs/suitcase_reference.png (the case the user picked as the
look to aim for: steel rails on every edge, protector plates on the
corners, a black arched handle and two silver clasps on a wood lid).

The reference is a smooth, dithered picture; scaled down to the canvas its
rails and outlines break up, so only the shading of the front face is
taken from it (resampled, blurred and mapped onto the olive-brown of the
previous suitcase.svg). Everything else -- outline, the rails along
straight perspective lines measured on the reference, corner plates,
side face, lid with its grain, handle and clasps -- is drawn cell by cell.
A soft ground shadow parallel to the bottom edges is added as before
(alpha steps 64 / 128).

decorations.json shows it 100 px wide; the pixel hit test (alpha > 10)
follows the drawing."""
import os
import numpy as np
from PIL import Image
from scipy import ndimage
import pix

W, H = 100, 90
REFERENCE = os.path.join(pix.HERE, "refs", "suitcase_reference.png")
REFERENCE_GROUND = (200, 210, 200)

# footprint of the case in the canvas: the full width less a 1 px margin,
# with room below for the ground shadow
BOX_LEFT, BOX_WIDTH, BOX_BOTTOM = 1, 99, H - 7

# body colours of the previous suitcase.svg, light to dark
BODY = ["#a27a50", "#9a7349", "#936d46", "#84623e", "#82613d", "#735437",
        "#62492e", "#5e452d", "#574229", "#483725"]
FACE = "#936d46"           # the front face's main tone (the reference's median maps here)
FACE_BLUR = 2.0            # smooths the reference's dither into bands
LID = "#84623e"
LID_GRAIN = "#62492e"
SIDE = "#483725"
SIDE_FRONT = "#574229"     # the side panel next to the front corner
SHADOW = "#3a3430"
SHADOW_FROM_ROW = 15       # the ground shadow stays out of the handle's arch
OUTLINE = "#2a1c14"
STEEL = {"hi": "#d0d3db", "light": "#b3b9c5", "mid": "#989dab", "low": "#6b738c",
         "dark": "#575a66"}

# straight edges (x0, y at x0, slope), measured on the reference:
LID_BACK = (2, 18, -0.171)        # top outline of the lid's back edge
LID_RIGHT = (84, 4, 0.5)          # top outline of its right edge
LID_LEFT = (2, 18, 0.4)           # top outline of the rail over the side face
FRONT_TOP = (30, 29, -0.2)        # bottom outline of the front top rail
BACK_RIGHT_X = 84
SIDE_X1, FRONT_LEFT_X1, RIGHT_X0 = 17, 23, 95   # side face / front-left rail / right rail
# the bottom lines are fitted to the reference's silhouette over these columns
SIDE_FIT, FRONT_FIT = (3, 16), (26, 91)

# column patterns of the vertical rails and the side face, left to right
SIDE_COLUMNS = ["O", "light", "mid", "O", SIDE, SIDE, "O", "light", "mid", "O",
                SIDE, SIDE, SIDE, SIDE, SIDE_FRONT, SIDE_FRONT]        # x 2..17
FRONT_LEFT_COLUMNS = ["O", "mid", "light", "hi", "mid", "O"]            # x 18..23
RIGHT_COLUMNS = ["O", "light", "mid", "O"]                              # x 95..98

# corner protector plates (x0, x1, y0, y1, outer side, lit top)
CAPS = ((1, 5, 15, 22, "left", True), (16, 23, 23, 32, "left", True),
        (93, 98, 10, 17, "right", True), (1, 5, 67, 74, "left", False),
        (17, 23, 76, 83, "left", False), (93, 98, 58, 65, "right", False))

HANDLE_X = (42, 58)               # outer columns of the arch
HANDLE_BASE = 9                   # post feet: rows above the front top rail
HANDLE_RISE = 9                   # grip top above the feet
HANDLE_THICK = 4                  # posts and grip, cells
# clasp plates: left column; each stands this many rows above the front rail
CLASPS = ((24, 6), (70, 6))
CLASP = [".OOOOOOOOO.",
         "OHHLLLLLLmO",
         "OLmOOOOOmlO",
         "OLmOdddOmlO",
         "OmmmmmmmmlO",
         ".OOOOOOOOO."]


def rgb(c):
    return np.array([int(c[i:i + 2], 16) for i in (1, 3, 5)])


def lum(c):
    c = np.asarray(c, float)
    return c[..., 0] * 0.3 + c[..., 1] * 0.59 + c[..., 2] * 0.11


def line(x, spec):
    x0, y0, k = spec
    return int(np.floor(y0 + k * (x - x0) + 0.5))


def resample(a, size):
    """Box resample with premultiplied alpha (no light halo)."""
    p = a.copy()
    p[:, :, :3] *= p[:, :, 3:] / 255
    r = np.array(Image.fromarray(p.round().astype(np.uint8)).resize(size, Image.BOX)).astype(float)
    r[:, :, :3] = np.clip(r[:, :, :3] / np.maximum(r[:, :, 3:] / 255, 1e-6), 0, 255)
    return r


def load_reference():
    """The reference cut from its flat ground and scaled into the footprint:
    (colour, opaque mask) on the canvas."""
    a = np.array(Image.open(REFERENCE).convert("RGBA")).astype(float)
    fg = np.abs(a[:, :, :3] - REFERENCE_GROUND).max(-1) > 18
    lab, _ = ndimage.label(fg)
    fg = ndimage.binary_fill_holes(lab == np.argmax(np.bincount(lab.ravel())[1:]) + 1)
    a[:, :, 3] = fg * 255
    ys, xs = np.nonzero(fg)
    a = a[ys.min():ys.max() + 1, xs.min():xs.max() + 1]
    bh = round(a.shape[0] * BOX_WIDTH / a.shape[1])
    r = resample(a, (BOX_WIDTH, bh))
    c = np.zeros((H, W, 3))
    m = np.zeros((H, W), bool)
    top = BOX_BOTTOM + 1 - bh
    c[top:top + bh, BOX_LEFT:BOX_LEFT + BOX_WIDTH] = r[:, :, :3]
    m[top:top + bh, BOX_LEFT:BOX_LEFT + BOX_WIDTH] = r[:, :, 3] >= 128
    return c, m


def face_tones(c, m):
    """The reference's front face shading in the old body colours."""
    L = lum(c)
    sat = c.max(-1) - c.min(-1)
    body = m & (c[:, :, 0] > c[:, :, 2] + 20) & (sat > 28)
    bm = body.astype(float)
    Ls = ndimage.gaussian_filter(L * bm, FACE_BLUR) / np.maximum(ndimage.gaussian_filter(bm, FACE_BLUR), 1e-6)
    pal = np.array([rgb(x) for x in BODY])
    target = Ls * lum(rgb(FACE)) / np.median(Ls[body])
    tone = pal[np.abs(target[:, :, None] - lum(pal)[None, None, :]).argmin(-1)]
    # cells the reference shows as rail / outline take the nearest face tone
    _, (iy, ix) = ndimage.distance_transform_edt(~body, return_indices=True)
    return tone[iy, ix]


def fit_bottom(m, cols):
    xs = np.arange(*cols)
    ys = np.array([np.nonzero(m[:, x])[0][-1] for x in xs])
    k, b = np.polyfit(xs, ys, 1)
    return (0, b, k)


def draw():
    c, m = load_reference()
    face = face_tones(c, m)
    side_bottom = fit_bottom(m, SIDE_FIT)
    front_bottom = fit_bottom(m, FRONT_FIT)

    im = np.zeros((H, W, 4), np.uint8)

    def put(y, x, colour):
        if 0 <= y < H and 0 <= x < W:
            im[y, x] = list(rgb(STEEL.get(colour, OUTLINE if colour == "O" else colour))) + [255]

    def stack(y, x, colours):
        for i, colour in enumerate(colours):
            put(y + i, x, colour)

    for x in range(2, 99):
        top = line(x, LID_BACK) if x <= BACK_RIGHT_X else line(x, LID_RIGHT)
        stack(top, x, ["O", "light", "low" if x <= BACK_RIGHT_X else "mid"])
        if x <= SIDE_X1:
            # lid above the side face, the rail over it, the side face and
            # its bottom rail
            e = line(x, LID_LEFT)
            lid_end = e
            col = SIDE_COLUMNS[x - 2]
            stack(e, x, ["O", "hi", "mid"])
            # the side's own rails run up into the top rail
            put(e + 3, x, col if col in ("light", "mid") else "O")
            yb = line(x, side_bottom)
            for y in range(e + 4, yb - 3):
                put(y, x, col)
            stack(yb - 3, x, ["O" if col not in ("light", "mid") else col, "light", "mid", "O"])
        else:
            ob = line(x, FRONT_TOP)
            lid_end = ob - 4
            stack(ob - 4, x, ["O", "hi", "light", "mid", "O"])
            yb = min(line(x, front_bottom), line(SIDE_X1 + 1, side_bottom) + 1) \
                if x <= FRONT_LEFT_X1 else line(x, front_bottom)
            if x <= FRONT_LEFT_X1:
                col = FRONT_LEFT_COLUMNS[x - SIDE_X1 - 1]
                for y in range(ob + 1, yb):
                    put(y, x, col)
            elif x >= RIGHT_X0:
                col = RIGHT_COLUMNS[x - RIGHT_X0]
                yb = line(RIGHT_X0 - 1, front_bottom)
                for y in range(ob + 1, yb):
                    put(y, x, col)
            else:
                for y in range(ob + 1, yb - 3):
                    im[y, x, :3] = face[y, x]
                    im[y, x, 3] = 255
                stack(yb - 3, x, ["O", "light", "mid"])
            put(yb, x, "O")
        # lid: wood with two broken grain lines along its length
        for y in range(top + 3, lid_end):
            u = (y - top - 3) / max(lid_end - top - 3, 1)
            grain = any(abs(u - g) < 0.09 for g in (0.3, 0.68)) and (x // 5 + (u > 0.5)) % 3 != 2
            put(y, x, LID_GRAIN if grain else LID)

    for x0, x1, y0, y1, outer, lit in CAPS:
        im[y0:y1 + 1, x0:x1 + 1] = list(rgb(OUTLINE)) + [255]
        im[y0 + 1:y1, x0 + 1:x1] = list(rgb(STEEL["mid"])) + [255]
        dark_x = x0 + 1 if outer == "left" else x1 - 1
        im[y0 + 1:y1, dark_x] = list(rgb(STEEL["dark"])) + [255]
        if lit:
            im[y0 + 1, x0 + 1:x1] = list(rgb(STEEL["light"])) + [255]
        else:
            im[y1 - 1, x0 + 1:x1] = list(rgb(STEEL["low"])) + [255]
        # rounded outer corners
        for cx, cy in ((x0, y0), (x1, y0), (x0, y1), (x1, y1)):
            dx, dy = (-1 if cx == x0 else 1), (-1 if cy == y0 else 1)
            if im[cy + dy, cx, 3] == 0 and im[cy, cx + dx, 3] == 0:
                im[cy, cx] = 0

    # clasps: rounded silver plates with a slot, lying on the lid
    for x0, above in CLASPS:
        for i in range(len(CLASP[0])):
            y0 = line(x0 + i, FRONT_TOP) - 4 - above
            for j, row in enumerate(CLASP):
                if row[i] != ".":
                    put(y0 + j, x0 + i, {"H": "hi", "L": "light", "m": "mid", "l": "low",
                                         "d": "dark", "O": "O"}[row[i]])

    # handle: a thick black arch with a grey sheen one cell inside its
    # outer edge, rounded outer corners, standing on the lid
    hx0, hx1 = HANDLE_X
    w = hx1 - hx0
    for x in range(hx0, hx1 + 1):
        i = min(x - hx0, hx1 - x)          # distance from the arch's outer side
        base = line(x, FRONT_TOP) - 4 - HANDLE_BASE
        g = base - HANDLE_RISE
        if i == 0:
            colours = ["O"] * (base - g)
            g += 1
        elif i == 1:
            colours = ["O"] + ["dark"] * (base - g - 1) + ["O"]
        elif i < HANDLE_THICK:
            colours = ["O", "dark"] + ["O"] * (base - g - 1)
        else:
            colours = ["O", "dark", "O", "O"]
            below = g + len(colours)
            if im[below, x, 3] and pix.hexc(im[below, x]) in (LID, LID_GRAIN):
                put(below, x, LID_GRAIN)       # the grip's shadow on the lid
        stack(g, x, colours)

    # ground shadow parallel to the bottom edges, below/right of the case
    case = im[:, :, 3] > 0
    for (dx, dy), a_ in (((3, 5), 64), ((2, 3), 128)):
        moved = np.zeros_like(case)
        moved[dy:, dx:] = case[:H - dy, :W - dx]
        sm = moved & ~case & (im[:, :, 3] == 0)
        sm[:SHADOW_FROM_ROW] = False
        im[sm, :3] = rgb(SHADOW)
        im[sm, 3] = a_
    return im


if __name__ == "__main__":
    im = draw()
    pix.show(pix.zoom(im, None, 7, grid=False), "suitcase")
