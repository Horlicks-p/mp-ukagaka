"""Per-frame recipes: one entry per runtime frame (38 body states).

Every frame is converted from its own large reference by convert.py (the
pipeline that reproduces the master from book ref [1]); see the mapping
notes per sequence below. A recipe returns (image, converted-pixel mask);
the mask is None when the frame is the master itself.
"""
import numpy as np

import pix
import convert
import layers
import eyes

_MASTER = None


def _clean_chest_edge_noise(image):
    """Drop twelve resampling outliers around the shared treasure chest.

    The large references have smooth edges at these four spots.  Direct
    downsampling left small one- or two-cell protrusions in every SVG, so the
    shared master removes only those verified cells.
    """
    out = image.copy()
    cells = (
        # Left upper edge.
        (37, 177), (38, 177), (36, 178), (36, 179), (36, 182), (36, 189),
        # Right upper edge.
        (168, 164), (168, 165), (168, 166),
        # Right middle edge.
        (171, 180), (172, 180),
        # Left lower corner.
        (36, 233),
    )
    for x, y in cells:
        out[y, x] = 0
    return out


# (x0, y0, x1, y1) on the master grid.  These are the previously approved
# hair repairs.  Idle supplies their interior shading; the crown's outer
# contour is restored from the matching book reference immediately after it.
HAIR_TOUCHUPS = (
    (124, 20, 160, 62),   # right tuft top
    (50, 20, 85, 47),     # left tuft top
    (83, 20, 109, 47),    # crown, left of centre
    (53, 46, 74, 68),     # left tuft root
    (141, 79, 168, 116),  # right side hair, outer edge
    (52, 81, 70, 100),    # left side hair beside the ear
)


def _restore_book_crown(image):
    """Restore the four marked crown sections from the matching large ref.

    Using idle ref [1] here left uneven one-cell steps along the top of the
    book pose.  Copying the outer four-pixel contour from book ref [1]'s
    direct, palette-snapped conversion keeps both hair and transparent edge
    cells faithful to the source, while retaining the approved interior
    shading and leaving the ponytail roots below it untouched.
    """
    out = image.copy()
    donor = convert.convert(convert.ref_path("book", 1))
    y0, y1 = 20, 62
    for x in range(50, 166):
        current = np.flatnonzero(out[y0:y1, x, 3] > 0)
        reference = np.flatnonzero(donor[y0:y1, x, 3] > 0)
        if not len(current) and not len(reference):
            continue
        current_top = y0 + int(current[0]) if len(current) else y1
        reference_top = y0 + int(reference[0]) if len(reference) else y1
        edge_bottom = min(y1, max(current_top, reference_top) + 4)
        out[y0:edge_bottom, x] = donor[y0:edge_bottom, x]
    return out


def M():
    """The master with its outline cleaned of the white halo
    (convert.fix_outline), the hair of book ref [5], which draws the twin
    tails with a cleaner edge (convert.adopt_hair), and its eye dots in
    their original teal (eyes.py)."""
    global _MASTER
    if _MASTER is None:
        m = convert.fix_outline(pix.master())[0]
        m = convert.adopt_hair(m)[0]
        # The approved root/side spots take idle ref [1]'s cleaner outline;
        # that reference is the rest pose at the master's position.
        m = convert.adopt_hair(m, "idle", 1, dx=0, boxes=HAIR_TOUCHUPS)[0]
        m = _restore_book_crown(m)
        m = _clean_chest_edge_noise(m)
        _MASTER = eyes.fix(m, "book", 1)[0]
    return _MASTER.copy()


# frame -> (reference set, reference number): used by the per-frame eye fix
SOURCE = {}


R = {}


def frame(name):
    def deco(fn):
        R[name] = fn
        return fn
    return deco


# ------------------------------------------------------------------ idle / sleep / wake
# Each frame comes from its own large reference (refs/idle, refs/sleep,
# refs/awareness, 464x688). The head and cape move rigidly by the reference's
# measured motion rounded to whole master pixels, and only the content that
# really changes (eyes, hair tips, collar) is converted, phase-locked to that
# whole-pixel move (layers.py). idle ref [1] is the base (the master's rest
# pose at the same resolution).
#
# Frame <- reference mapping (verified by pose measurement):
#   idle-NN  <- idle [NN+1]   (refs 1=2 and 7..11 identical, like the APNG)
#   sleep-NN <- sleep [s0, s2, s4, s5, s6, s8, s9, s10, s11, s12][NN]
#               (one breathing cycle; s4=s9, s5=s8 keep the APNG palindrome)
#   wake-NN  <- awareness [wNN]

REST = ("idle", 1)
SLEEP_REF = [0, 2, 4, 5, 6, 8, 9, 10, 11, 12]

_SIDE_LOCK_REFERENCE = None


def _clean_side_locks(image, head_offset):
    """Use book-04's clean shoulder-length hair locks in selected frames.

    The large idle, sleep and awareness references show the same two pointed
    locks moving rigidly with the head.  Per-frame resampling leaves stray
    grey pixels through their lower halves where they overlap the white cape,
    so finish only those two narrow areas from the approved book-04 SVG.
    """
    global _SIDE_LOCK_REFERENCE
    if _SIDE_LOCK_REFERENCE is None:
        # built in memory, not read from book/frieren-book-04.svg: idle and
        # sleep are built before the book frames, so the file on disk could
        # still be the previous build's
        _SIDE_LOCK_REFERENCE = _book(4)[0]

    lock_mask = np.zeros((pix.H, pix.W), dtype=bool)
    lock_mask[121:149, 72:90] = True
    lock_mask[121:149, 120:140] = True
    dx, dy = head_offset
    donor = layers.shift(_SIDE_LOCK_REFERENCE, dx, dy)
    take = layers.shift(lock_mask, dx, dy)
    out = image.copy()
    out[take] = donor[take]
    return out

# content bases: frames that share one drawing reuse it (moved), so the
# unchanged parts cannot shimmer -- the blinks share idle [3]'s closed eyes,
# every sleep / waking frame shares the sleeping face of sleep [s0].
IDLE_CB = {6: ("idle", 3), 12: ("idle", 3)}


def _idle(n):
    if n in IDLE_CB:
        layers.frame(*IDLE_CB[n], REST, M())
    out, info = layers.frame("idle", n, REST, M(), IDLE_CB.get(n))
    out = _clean_side_locks(out, info["nh"])
    return out, info["converted"]


# waking: w1-w3 still have the sleeping face; from w4 the eyes open and the
# master (the awake rest pose) is the closer drawing, which also makes w5
# hand over to idle-00 without a jump
WAKE_CB = {1: ("sleep", 0), 2: ("sleep", 0), 3: ("sleep", 0), 4: None, 5: None}


def _sleepish(kind, n):
    if ("sleep", 0) not in layers._CACHE:
        layers.frame("sleep", 0, REST, M())
    if (kind, n) == ("sleep", 0):
        out, info = layers._CACHE[("sleep", 0)]
    else:
        cb = WAKE_CB[n] if kind == "wake" else ("sleep", 0)
        out, info = layers.frame(kind, n, REST, M(), cb)
    out = _clean_side_locks(out, info["nh"])
    return out, info["converted"]


for _n in range(12):
    SOURCE["idle-%02d" % _n] = ("idle", _n + 1)
for _n in range(10):
    SOURCE["sleep-%02d" % _n] = ("sleep", SLEEP_REF[_n])
for _n in range(1, 6):
    SOURCE["wake-%02d" % _n] = ("wake", _n)
for _n in range(1, 12):
    SOURCE["book-%02d" % _n] = ("book", _n)

for _n in range(12):
    R["idle-%02d" % _n] = (lambda n: lambda: _idle(n + 1))(_n)
for _n in range(10):
    R["sleep-%02d" % _n] = (lambda n: lambda: _sleepish("sleep", SLEEP_REF[n]))(_n)
for _n in range(1, 6):
    R["wake-%02d" % _n] = (lambda n: lambda: _sleepish("wake", n))(_n)


# ------------------------------------------------------------------ book
# frieren[1..11]: right hand turns a page. Each frame comes from its own
# large reference (refs/book/frieren[N].png) through
# the pipeline that reproduces the master from ref [1] (convert.py); pixels
# the original does not change keep the master's values so static parts do
# not shimmer between frames.

@frame("book-01")
def _():  # rest pose: the master is ref [1] itself
    return _clean_side_locks(M(), (0, 0)), None


def _book(n):
    out, converted = convert.frame("book", n, ("book", 1), M())
    if n <= 3:
        out = _clean_side_locks(out, (0, 0))
    return out, converted

for _n in range(2, 12):
    R["book-%02d" % _n] = (lambda n: lambda: _book(n))(_n)
