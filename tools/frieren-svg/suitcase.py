"""Build Frieren's suitcase decoration from the user-approved large sample.

The previous redraw measured a second, simplified reference and rebuilt the
case from straight procedural rails. That lost the sample's characteristic
perspective, handle, clasps and stepped corner protectors. This version uses
``ghost/Frieren/decorations/suitcase sample.png`` itself as the drawing source,
fits its opaque silhouette into the existing 100x90 decoration canvas, then
maps the wood, steel and handle back to the current SVG's compact palette.

The emitted asset is still native pixel-art SVG (grouped rect paths, no raster
image embedded). The canvas, CSS size and placement stay unchanged, and the
runtime's alpha hit test follows the new silhouette exactly.
"""
import os

import numpy as np
from PIL import Image
from scipy import ndimage

import pix


W, H = 100, 90
REFERENCE = os.path.join(
    pix.ROOT, "ghost", "Frieren", "decorations", "suitcase sample.png"
)

# Keep one cell of breathing room on the left/right and enough room below for
# the existing soft ground shadow. The reference crop is 1.254:1, therefore
# 98 pixels wide becomes 78 pixels high without distortion.
CASE_LEFT, CASE_TOP, CASE_WIDTH, CASE_HEIGHT = 1, 2, 98, 78

# Existing suitcase.svg palette. Wood is ordered light -> dark; hardware and
# handle retain their established cool-grey / charcoal colours.
WOOD = (
    "#a27a50", "#9a7349", "#936d46", "#84623e", "#82613d",
    "#735437", "#62492e", "#5e452d", "#574229", "#483725",
)
STEEL = ("#d0d3db", "#b3b9c5", "#989dab", "#6b738c", "#575a66")
HANDLE = ("#6b738c", "#575a66", "#2a1c14")
OUTLINE = "#2a1c14"
SHADOW = "#3a3430"


def rgb(colour):
    return np.array([int(colour[i:i + 2], 16) for i in (1, 3, 5)], np.uint8)


def luminance(colours):
    colours = np.asarray(colours, float)
    return colours[..., 0] * 0.30 + colours[..., 1] * 0.59 + colours[..., 2] * 0.11


def _sample_crop():
    """Return the visible sample tightly cropped, as straight-alpha RGBA."""
    source = np.array(Image.open(REFERENCE).convert("RGBA"))
    visible = source[:, :, 3] > 10
    labels, count = ndimage.label(visible, structure=np.ones((3, 3)))
    if count:
        sizes = ndimage.sum(visible, labels, range(1, count + 1))
        visible = labels == int(np.argmax(sizes)) + 1
    ys, xs = np.nonzero(visible)
    return source[ys.min():ys.max() + 1, xs.min():xs.max() + 1]


def _resample_sample():
    """Premultiplied box resize: sharp sample pixels without a dark halo."""
    source = _sample_crop().astype(float)
    alpha = source[:, :, 3:] / 255
    source[:, :, :3] *= alpha
    resized = np.array(
        Image.fromarray(source.round().astype(np.uint8)).resize(
            (CASE_WIDTH, CASE_HEIGHT), Image.Resampling.BOX
        )
    ).astype(float)
    resized[:, :, :3] = np.clip(
        resized[:, :, :3] / np.maximum(resized[:, :, 3:] / 255, 1e-6), 0, 255
    )
    return resized


def _map_ramp(values, mask, palette, low=None, high=None):
    """Map source luminance into an ordered light-to-dark target palette."""
    result = np.zeros(values.shape + (3,), np.uint8)
    if not mask.any():
        return result
    if low is None:
        low = float(np.percentile(values[mask], 4))
    if high is None:
        high = float(np.percentile(values[mask], 96))
    level = np.clip((values - low) / max(high - low, 1), 0, 1)
    indices = np.rint((1 - level) * (len(palette) - 1)).astype(int)
    colours = np.array([rgb(colour) for colour in palette])
    result[mask] = colours[indices[mask]]
    return result


def _colourise(sample):
    """Separate wood, steel/outline and handle, preserving sample geometry."""
    colours = sample[:, :, :3]
    alpha = sample[:, :, 3]
    visible = alpha >= 72

    # Keep only the connected suitcase. This drops isolated antialias crumbs
    # while preserving the lid behind the handle opening.
    labels, count = ndimage.label(visible, structure=np.ones((3, 3)))
    if count:
        sizes = ndimage.sum(visible, labels, range(1, count + 1))
        visible = labels == int(np.argmax(sizes)) + 1

    red, green, blue = colours[:, :, 0], colours[:, :, 1], colours[:, :, 2]
    light = luminance(colours)
    saturation = colours.max(-1) - colours.min(-1)

    # Brown/orange pixels are the leather/wood shell. This includes its dark
    # grain but excludes the neutral steel and blue-grey handle.
    wood = (
        visible
        & (red > green + 7)
        & (green > blue + 5)
        & (red > blue + 20)
        & (saturation > 22)
    )
    outline = visible & ~wood & (light < 46)

    yy, xx = np.mgrid[:CASE_HEIGHT, :CASE_WIDTH]
    # The handle occupies this upper-centre zone in the approved sample. Its
    # mounts are included so their charcoal shading does not become bright
    # steel; the silver clasps sit outside or below this mask.
    handle_zone = (
        visible & ~wood & (xx >= 31) & (xx <= 64) & (yy <= 27)
        & (light < 145)
    )
    steel = visible & ~wood & ~outline & ~handle_zone

    out = np.zeros((CASE_HEIGHT, CASE_WIDTH, 4), np.uint8)
    out[wood, :3] = _map_ramp(light, wood, WOOD)[wood]
    out[steel, :3] = _map_ramp(light, steel, STEEL, low=58, high=224)[steel]
    out[handle_zone, :3] = _map_ramp(
        light, handle_zone, HANDLE, low=42, high=132
    )[handle_zone]
    out[outline, :3] = rgb(OUTLINE)
    out[visible, 3] = 255
    return out


def _add_shadow(image):
    """Add the existing two-step shadow only beneath the case footprint."""
    case = image[:, :, 3] > 0
    for (dx, dy), opacity in (((3, 5), 64), ((2, 3), 128)):
        moved = np.zeros_like(case)
        moved[dy:, dx:] = case[:H - dy, :W - dx]
        mask = moved & ~case & (image[:, :, 3] == 0)
        mask[:61] = False
        image[mask, :3] = rgb(SHADOW)
        image[mask, 3] = opacity
    return image


def draw():
    """Return the redrawn 100x90 RGBA decoration."""
    case = _colourise(_resample_sample())
    image = np.zeros((H, W, 4), np.uint8)
    image[
        CASE_TOP:CASE_TOP + CASE_HEIGHT,
        CASE_LEFT:CASE_LEFT + CASE_WIDTH,
    ] = case
    return _add_shadow(image)


if __name__ == "__main__":
    pix.show(pix.zoom(draw(), None, 7, grid=False), "suitcase-redraw")
