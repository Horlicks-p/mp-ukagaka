"""Per-frame recipes: one entry per runtime frame (38 body states).

Every frame is converted from its own large reference by convert.py (the
pipeline that reproduces the master from book ref [1]); see the mapping
notes per sequence below. A recipe returns (image, converted-pixel mask);
the mask is None when the frame is the master itself.
"""
import pix
import convert
import layers
import eyes

_MASTER = None


def M():
    """The master with its outline recoloured without the white halo
    (convert.fix_outline) and its eye dots in their original teal (eyes.py)."""
    global _MASTER
    if _MASTER is None:
        _MASTER = eyes.fix(convert.fix_outline(pix.master())[0], "book", 1)[0]
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
# Each frame comes from its own large reference (idle / sleep / awareness
# sample large, 464x688). The head and cape move rigidly by the reference's
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

# content bases: frames that share one drawing reuse it (moved), so the
# unchanged parts cannot shimmer -- the blinks share idle [3]'s closed eyes,
# every sleep / waking frame shares the sleeping face of sleep [s0].
IDLE_CB = {6: ("idle", 3), 12: ("idle", 3)}


def _idle(n):
    if n in IDLE_CB:
        layers.frame(*IDLE_CB[n], REST, M())
    out, info = layers.frame("idle", n, REST, M(), IDLE_CB.get(n))
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
# large reference (shell/Frieren/book sample large/frieren[N].png) through
# the pipeline that reproduces the master from ref [1] (convert.py); pixels
# the original does not change keep the master's values so static parts do
# not shimmer between frames.

@frame("book-01")
def _():  # rest pose: the master is ref [1] itself
    return M(), None

for _n in range(2, 12):
    R["book-%02d" % _n] = (lambda n: lambda: convert.frame("book", n, ("book", 1), M()))(_n)
