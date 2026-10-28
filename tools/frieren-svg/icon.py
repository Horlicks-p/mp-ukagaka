"""Plugin icon for the update screens (assets/icon-*.png).

Plugin Update Checker reads assets/icon-128x128.png and icon-256x256.png from
the installed copy of the plugin, the same names WordPress.org uses. The icon is
Frieren's head from the first idle frame, cropped 128x128 at 1:1 so every pixel
stays square, on an off-white background.

usage: python icon.py
"""
import os

from PIL import Image

import pix

FRAME = os.path.join(pix.SHELL, "idle", "frieren-idle-00.svg")
CROP = (42, 20, 170, 148)  # x0, y0, x1, y1 on the 208x328 master grid
BACKGROUND = (0xf5, 0xf5, 0xf0, 255)  # chosen by the site owner
OUT = os.path.join(pix.ROOT, "assets")


def icon():
    x0, y0, x1, y1 = CROP
    head = Image.fromarray(pix.svg_to_rgba(FRAME)[y0:y1, x0:x1])
    base = Image.new("RGBA", head.size, BACKGROUND)
    base.alpha_composite(head)
    return base.convert("RGB")


if __name__ == "__main__":
    os.makedirs(OUT, exist_ok=True)
    im = icon()
    for size in (128, 256):
        path = os.path.join(OUT, "icon-%dx%d.png" % (size, size))
        im.resize((size, size), Image.NEAREST).save(path, optimize=True)
        print("wrote", path)
