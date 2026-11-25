# Frieren chest mesh study — 2026-11-23

Status: **local character and bridge PoC completed; WordPress integration not implemented**.
The owner supplied `frieren-chest-fullbody.png` and authorized this local experiment,
including sending that source and the generated masks to OpenAI for image variants.
No commit, release, deployment, option migration or production renderer replacement occurred.

## Open the result

The local preview listens at <http://127.0.0.1:9488/>. To start it again from the repository root:

```powershell
& ./experiments/mavatar/start-preview.ps1
```

Controls: emotions, simulated Chinese text streaming, cancel, Japanese kana mouth preview,
sleep/wake, hide/show, reload, deliberate missing-model fallback and model download.
There is no synthesized voice or live LLM request. The server binds only to loopback,
serves an explicit file allowlist, and never serves the Studio directory or source PNG.

## Source and toolchain

- Source: `plan/mavatar-plan/frieren-chest-fullbody.png`, RGBA, 1034 × 1521.
- Working master: 1034 × 1551, transparent padding of 15 px above and below.
  Original pixels were copied unchanged; rig coordinates use the padded image.
- Studio: `shinshin86/mesh-avatar-studio`, commit `fb823c05c9963d241fec42e772959be562c927eb`.
- Runtime 0.9.0; Element 0.4.6; Node 24.22.1; uv 0.13.9.
- Python used for initial preparation: a local CPython 3.14.3 (set `MAVATAR_PYTHON` to choose one).
  Studio doctor also found regular Python 3.11.18. uv dependencies and Chromium are local.
- Browser: Playwright Chromium 153.0.8010.12.
- Version and SHA-256 records: `experiments/mavatar/upstream.json`.
- npm adjusted upstream's stale workspace entries in the installed lockfile. The actual
  lockfile is retained in the ignored checkout and its hash is recorded. Runtime source
  algorithms and sprite-import thresholds were not modified.

The Studio checkout and all generated character assets are ignored by the parent repository.
`experiments/` has `export-ignore`; existing `plan/` exclusion protects the supplied source.

## Rig and image work

The upstream coordinate calibration passed: median **0.48 px**, maximum **1.69 px**.
The numbered calibration image was inspected before writing answers; the key was only
read by the official scoring command. Full, head, eye (5 px), mouth (5 px), accessory
and body grids plus rig overlays were inspected.

Unpadded source survey: face approximately (550, 240), crown y≈10, chin (542, 322),
neck (540, 335), shoulders approximately (340, 415)/(747, 425). Long twin tails and
two red earrings are present. The hands rest on the chest, well below the head.
This is an upper-body deformation on a complete composition, not a full-body articulated rig.

The rig uses `breathBand`, `rollBand`, `hip.band`, and head fade bands. Body effects fade
out at padded y=690, before the hands and chest. No `legs`, `arms`, `cloth` or `hand`
occlusion rig was created. Hair strands and earrings have restrained motion. The hands,
legs and treasure chest have no independently animated joints.

Official workflow used:

1. `new-project.py`, coordinate grids, draft validation and overlays.
2. `build-layers.py`, then inspection of hair mask and both eyeball layers.
3. `render-poses`: 21 poses/physics snapshots and 64 images including detail crops.
4. `variant-requests.py`; built-in `image_gen.imagegen` with source + official masks.
5. `build-sprites.py` without relaxed validation, followed by another complete pose review.
6. `pack-avatar --no-source`, then browser integration checks.

Initial seven accepted variants: `eyes_closed`, `eyes_half`, `eyes_smile`, `mouth_a`,
`mouth_a_half`, `mouth_i`, `mouth_o`; these produce ten cropped sprites.
The later emotion pass generated `eyes_sad`, `mouth_sad` and `mouth_angry`. After visual
review, the owner found the sad mouth too exaggerated for Frieren and chose to omit it.
The current model uses nine variants / thirteen cropped sprites: sad eyes and brows
retain the original quiet closed mouth, while angry keeps its firm closed-mouth sprite.
`eyes_surprise` is still absent. Angry uses the existing half-lidded eyes, following the
upstream runtime's supported sprite contract.

All generated full images are 1024 × 1536. The official importer resized them proportionally,
validated their alignment, then imported only the allowed mask regions with documented
skin-tint correction. The original base drawing was not replaced with a generated picture.
The first `mouth_a_half` was rejected: outside-mask opaque mean **16.50/255**, limit **16**.
One retry passed at **8.47/255**. The rejected image was retained; no thresholds were raised.
Eight imagegen calls in the initial pass; no SD route comparison was performed.

The emotion pass used four further built-in imagegen calls: sad eyes, sad mouth, angry
mouth and one sad-eye retry. The first sad-eye image was rejected for an outside-mask
12 px edge-band mean of 21.00/255 and 13.4% of pixels above 48; limits are 20 and 12%.
The retry passed at 14.08/255 and 7.4%. Both mouths passed on their first attempts.
The rejected eye image is retained as `variants/rejected/eyes_sad-first.png`.

Local prompts, inputs and output provenance are in:
`experiments/mavatar/studio/projects/frieren-chest/work/imagegen-provenance.json`, with
official prompts/masks under the project's `variant-requests/` folder.
The later emotion prompts and image outputs are recorded in
`work/imagegen-emotion-provenance.json` in that same local project.

## Verification and artifacts

`check-demo.mjs` passed all **19 checks** on the current archive and updated bridge:

- source PNG absent from the archive; real model loads with only local requests;
- sad-eye and angry-mouth sprites present, with `mouth_sad` absent from the manifest/archive;
- source-space anchored-point deformation and 350 px browser-rendered ROI comparisons;
- Chinese simulated streaming and Japanese kana cancellation;
- held picker expressions with observable face parameters, surviving speech completion;
- relaxed mostly-open timing and slow close/reopen sequence, sleep closure and restoration on wake;
- native awkward overlay assets, reload/wake restoration, speech mouth handoff and clearing on emotion change;
- sleep/wake, hide/show, overlapping loads, destruction during loading;
- missing-model fallback and recovery, no-WebGL2 fallback, reduced-motion loading;
- 390 px responsive layout; no browser script errors or external resource requests.

For maximum breath, combined left lean/turn, and combined right lean/turn, all four rendered
ROIs (left hand, right hand, chest, legs) had **maximum RGBA channel delta 0**, at canvas
234 × 350. Dense source-space sampling under those poses measured maximum displacement:

| ROI | Maximum source-pixel displacement |
| --- | ---: |
| Left hand | 6.47e-11 |
| Right hand | 3.76e-10 |
| Chest | 2.48e-7 |
| Legs | 1.71e-12 |

These are specific pose comparisons, not a proof over every possible future motion or device.
The fresh final archive loaded in approximately 265 ms in this local test; this is one local
observation, not a production performance budget or a mobile hardware measurement.

Private local artifact paths, relative to `experiments/mavatar/studio/projects/frieren-chest/`:

| Artifact | Path |
| --- | --- |
| Runtime model | `frieren-runtime.mavatar` |
| Complete neutral fallback | `poster.png` |
| Editable draft/final rig | `rig.draft.json`, `rig.json` |
| Pose contact sheet/details | `review/` |
| Desktop/mobile/sleep screenshots | `review-demo/` |
| Emotion comparison with face crops | `review-demo/emotions.png` |
| Browser recording | `review-demo/video/` |
| Full machine-readable results | `review-demo/results.json` |
| Failed first half-open mouth | `variants/rejected/mouth_a_half-first.png` |
| Accepted but intentionally unused sad mouth | `variants/unused/mouth_sad.png` |

Initial model: **1,770,138 bytes**. SHA-256:
`268557ae45c415e3bb2f08f2ec2b095a9f46318acfbef66b7af5487fa3489960`.
Current model with awkward overlays (sad mouth omitted): **1,835,707 bytes**. SHA-256:
`e53759d2950a1f33ff1d6621a3d5e89741c9906d011df552d53afbd9777edcf4`.
Standalone element bundle: approximately 179 kB, served locally.

## Repeat the checks or edit the rig

From the repository root:

```powershell
. ./experiments/mavatar/env.ps1
node ./experiments/mavatar/check-demo.mjs

# Rebuild after intentional rig/variant edits; validates before replacing the runtime archive:
& ./experiments/mavatar/rebuild.ps1

# Open the upstream editor, then select projects/frieren-chest:
Set-Location ./experiments/mavatar/studio
npm run dev
```

`prepare-frieren.py` reproduces the initial traced draft and padded source. Do not rerun it
after manual rig edits unless intentionally resetting to this initial trace.
On Windows, Python's temporary staging files inherited owner-only ACLs. Project inheritance
was restored for generated layers and `rig.json`; `rebuild.ps1` repeats that adjustment.

## Limits and next integration step

The visual PoC is suitable for the owner to try. It is not a release candidate or an approval
of a WordPress-wide renderer migration. No old book-flip, touch-zone, gift, decoration or
character-switch workflow has been migrated or tested in WordPress.

After owner feedback that emotions were subtle, the preview picker was changed from
`setEmotion()` talking faces to held `setExpression()` expressions. The former uses restrained
open-eye happiness and a surprised talking face without a resting mouth opening; it can also
expire after speech despite the picker still showing the selected emotion. The bridge now
maps happy→smile, angry→angry, sad→sad, surprised→surprise and neutral→neutral,
clearing the underlying talking emotion to avoid applying the face twice. Relaxed and
awkward use the bridge controls described below over the neutral expression.
Sleep temporarily clears the overlay and restores it on wake. Tests assert smiling eyes,
surprised mouth opening, opposite sad/angry brow parameters and persistence after speech.
That first control-only follow-up was followed by the dedicated sad-eye and sad/angry-mouth
drawings described above. The owner subsequently chose to use only the sad eyes, keeping
the original mouth for a restrained Frieren expression. `mouth_sad.png` was moved to
`variants/unused/`; rebuilding through the official importer removes it from the sprite
manifest, and the official packer omits it. Its source artwork is retained for comparison.
The browser check rejects an archive that reintroduces that sprite and verifies that
the sad resting expression keeps its mouth closed. Normal speech still uses vowel sprites.

Relaxed originally inherited `halfLidded`, followed by an intermediate brow correction.
The owner then chose a mostly open, softer gaze with slow blinks instead of sustained
half-open eyes. It now uses a small bridge-controlled eye cycle: 8.6 seconds per cycle,
closing over 0.28 s, holding closed for 0.32 s, and reopening over 0.5 s. The resting eye
parameter is 0.96; half-open sprites only appear briefly during transitions. The clock
uses actual avatar simulation steps, so it pauses when the element stops animating and
does not catch up after being hidden. Sleep explicitly closes both eyes and restores
the selected face on wake. Angry retains its existing 0.72 eye setting and mouth sprite.
Sampling a full cycle gives 90% open, approximately 2.9% half-open and 7.1% closed.

## Awkward / evasive expression

The owner supplied `studio/samples/images.jpg` as an expression reference: flat, thick
black closed-eye bars and a small closed omega-shaped mouth. The reference itself and
its background are not included in the avatar. The preview adds an `awkward` selection
labelled 尷尬／搪塞. This is a local bridge state, not a new upstream emotion enum.

Two built-in imagegen drawings were prepared, with one eye retry. The first eyes were
rejected for outside-mask ring mean 23.82/255 and 15.8% above 48. The retry passed at
14.09/255 and 8.1%; the mouth passed on its first attempt. Prompts, outputs and validation
are in the local project's `work/imagegen-awkward-provenance.json`.

The isolated work project `expressions/awkward/` uses the official `eyes_closed` and
`mouth_angry` importer slots only to validate and crop these drawings. The main model's
sleeping/smiling eyes and angry mouth are not replaced. `build-awkward-overlay.py` calls
the unmodified official mask generator and sprite importer, then only positions the
validated crops into two native transparent overlays, `awkward-eyes` and `awkward-mouth`.
It implements no second alignment checker, inpainting operation or sprite importer.

At rest both overlays show. During speech the mouth overlay hides so normal vowel sprites
can animate while the flat awkward eyes remain. Stopping speech restores the omega mouth;
sleep hides both overlays, waking/reloading restores them, and selecting another emotion
clears them. This is an intentionally stylized held eye drawing; it does not interpolate
the black bars into an open-eye drawing. The preview's expression comparison includes it.

At 350 px, facial detail is small; full-open mouth is deliberately clearer than neutral,
and half-closed eyes have a stronger crease than the source. The source also contains
colored cutout noise near earrings/silhouette. A final art pass remains useful. The generated
hair mask includes some pale collar/ear-edge pixels; pose review showed no obvious tearing,
but refinement should include those areas. No image cleanup was silently applied.

The next bounded slice is to mount the tested `MeshBridge` in the existing role container,
route actual emotion and text-display lifecycle into it, and validate in local WordPress.
Existing REST/SSE/providers remain the source of dialogue; `startSpeaking/stopSpeaking`
describe text-display activity and are not phoneme-accurate lip sync. Readiness must be
per load generation, and missing WebGL2 must use a complete poster, never `built/base.png`.

References: [upstream Agent Guide](https://github.com/shinshin86/mesh-avatar-studio/blob/fb823c05c9963d241fec42e772959be562c927eb/docs/agent-guide.md),
[Element API](https://github.com/shinshin86/mesh-avatar-studio/blob/fb823c05c9963d241fec42e772959be562c927eb/packages/element/README.md).

## Follow-up — owner-cut book illustration (2026-11-23)

The owner supplied `frieren-chest/expressions/awkward/source-new.png` and requested the
same avatar workflow. This creates a separate private project, `projects/frieren-book`;
the previous chest project and its artwork remain available.

- Original: RGBA, **1088 × 1600**, with the owner's own transparent cutout and an open book.
  Only 16 transparent pixels were added above and below, producing **1088 × 1632**.
  A direct RGBA array comparison confirmed every original pixel is unchanged.
  No generated full image replaces the source or base drawing.
- The rig was traced again using full, head, hair, body, accessory and 5 px eye/mouth grids,
  inspected overlays, official layer building and pose rendering. Calibration: median
  **0.42 px**, maximum **0.81 px**, PASS. No legs, arms, cloth or chin-hand rig.
- Nine accepted main variants produce thirteen sprites; sad retains the original closed
  mouth. Two additional drawings supply native awkward eye/mouth overlays, with the same
  speech handoff as the chest study. `eyes_surprise` is omitted as in that study.
- **14 built-in imagegen calls**, eleven accepted drawings. Two initial requests were
  rejected visually after a prompt extraction bug yielded `[object Object]`; the resulting
  open purple eyes were never imported. That bug was corrected before retrying.
  The first sad-eye drawing failed the official 12 px edge-band mean test (**20.13 > 20**);
  retry passed (**18.31**, 11.1% above 48). Thresholds were unchanged. Rejected drawings
  remain in `variants/rejected/`; prompts, inputs and provenance are in `variant-requests/`
  and `work/imagegen-provenance.json`.
- The first rig allowed **0.05693 source px** of book displacement under extreme poses.
  Narrowing chest/shoulder influence fixed it. Final dense sampling: book **1.60e-6 px**;
  both hands, chest and legs **0 px**. At canvas **234 × 350**, all five ROIs had maximum
  RGBA channel delta **0** for maximum breath and combined left/right lean/turn.
- Final `check-demo.mjs frieren-book`: **19 checks passed**, including expressions,
  speaking/cancellation, kana, relaxed/sleep/wake, awkward overlay handoff, load races,
  destruction, 404/no-WebGL2 fallback and 390 px layout, without external requests or
  page errors. Initial loading was **398 ms** in one local run, not a mobile benchmark.
- The final official pose pass produced **64 review images**, plus a face contact sheet
  for inspection. Desktop/mobile/sleep screenshots, emotion comparison, recording and
  machine-readable results are in this project's `review-demo/`.

Runtime and Element remain pinned to **0.9.0 / 0.4.6**, upstream commit
`fb823c05c9963d241fec42e772959be562c927eb`. This workstation used Node **24.11.1**,
Python **3.12.10** (Pillow 11.3.0, NumPy 2.2.6, OpenCV 4.12.0), uv **0.9.13** and
Chromium **153.0.8010.12**. `npm ci --ignore-scripts --no-audit --no-fund` repaired an
incomplete transferred Playwright installation; official doctor then passed. During QA,
an alpha-image display initially appeared to omit the body; inspecting the actual PNGs
against a solid background showed the body was complete. A separate software-WebGL
pose pass also completed; the final pass and browser checks used normal Chromium.

Initial private deliverable: `experiments/mavatar/studio/projects/frieren-book/frieren-book.mavatar`
(**1,468,491 bytes**, `--no-source`), also copied to `frieren-runtime.mavatar` for the demo.
SHA-256: `35169177f66a88e8cc9a908b9acbc84ae9e898ff2b2b75f0eaa82acf7bc0c379`.
The archive passed the official avatar validator. Source, variants and working rigs remain
ignored; the archive still contains downloadable runtime textures.

From the plugin root, preview and repeat the browser checks:

```powershell
node ./experiments/mavatar/server.mjs 9489 frieren-book
# Open http://127.0.0.1:9489/

$env:PLAYWRIGHT_BROWSERS_PATH = (Resolve-Path ./experiments/mavatar/artifacts/browsers).Path
node ./experiments/mavatar/check-demo.mjs frieren-book
```

Open Studio with `npm run dev`, then choose `projects/frieren-book`. The private
`work/prepare.py` reproduces the initial traced draft, source padding and poster; rerunning
it resets manual draft edits. For later rig edits, use the official layer/sprite/pose/pack
commands and `python ../build-awkward-overlay.py frieren-book` from Studio. The rebuild
helper now accepts `-Project frieren-book` and restores the accessory underpainting
after the official layer build, before rendering and packing (see the repair below).

Remaining visual checks for the owner: half-lidded and angry eyes have a stronger crease;
angry uses a stylized closed frown; the earrings swing in the physics stress frames.
Hair masks include faint collar/ear-edge pixels, so those transitions merit an art pass.
At 350 px the face remains small. The book and hands are fixed, without page-turning or
independent finger movement. This follow-up adds no WordPress integration or release.

### Book study repair — hair behind swinging earrings (2026-11-23)

The owner identified visible hair notches when earrings swing. The official builder
inpaints the removed accessories, then applies an extra distance-based alpha fade inside
the removal mask. That fade erased portions of the hair silhouette. Restoring its opacity
alone was insufficient: the locally inpainted black hair contour remained blurred.

Three built-in imagegen requests explored an earring-free hair underpainting (two full
compositions and one head crop). All three raw paintings failed the face-sprite importer's
outside-mask edge check; none was imported as a full-image variant. After inspecting the
local composites, the first painting's small hair regions were chosen for their continuous
contours. `restore-accessory-underlay.py` discards its pixels outside a **4,224 px** protection
mask and blends the two patches in premultiplied RGBA with a 4 px feather. The actual
assembled base is then checked against the pre-repair base using the unchanged official
`mask_only` thresholds: opaque mean **0**, edge-band mean **0**, edge-band share **0%**.
The face importer's returned alpha/tint adjustment is deliberately unused: this patch
restores a hair silhouette and the genuine transparent space beside it. This is a separate
underpainting assembly step, not a new eye/mouth sprite or a replacement source image.

**4,129 base pixels changed; zero pixels outside the protection mask changed.** The source
and original owner-cut image retain identical pixels. Comparing old/new archives confirms
that only `built/base.png` and its `built/layers.json` cache identity changed; the rig,
earring layers, physics, eye/mouth sprites and awkward overlays are byte-identical.
The helper detects an already-applied patch and leaves it unchanged on a repeat run.

The updated archive passed the official validator and all **19 browser checks**, including
the fixed book/hand/chest/leg ROI comparisons. A fresh official pass produced **64 pose
review images**. Six additional earring physics snapshots using the same input pose sequence
were compared before/after on blue-grey and white backgrounds; the new contours stay
continuous as the earrings move. Private comparison images are
`accessory-underlay/earrings-before.png` and `accessory-underlay/earrings-fixed.png`.
The masks, full prompts, attempted paintings, native-only comparison and complete provenance
are retained in `accessory-underlay/`; generated full compositions never replace the source.

Archive after this repair: **1,469,429 bytes**, available as `frieren-book.mavatar`,
`frieren-book-hairfix.mavatar` and the preview's `frieren-runtime.mavatar` in that project.
SHA-256: `75f471ab0720d29b61effcf96fbd8a15ac49e448d3ecef1b375e0bbfbcf42cdd`.
The prior archive is retained in `work/accessory-underlay/before/`.

To retain the repair after later rig edits, run the parameterized rebuild helper from the
plugin root: `./experiments/mavatar/rebuild.ps1 -Project frieren-book`. When rebuilding
manually from Studio, run `python ../restore-accessory-underlay.py projects/frieren-book`
after `build-layers.py` and before pose review/packing. No upstream source was modified.

### Reference expressions — restrained anger, good mood and smug (2026-11-23)

The owner supplied `samples/frieren-angry.PNG`, `samples/frieren-good-mood.PNG` and
`samples/frieren-smug.png` as expression references. They are read-only reference artwork,
not replacement faces or assets included in the avatar. The book preview now offers
**nine expressions**:

- **Angry / displeased**: original open eyes, slight brow tension and a tiny closed frown.
  The pointed inverted-V mouth was replaced. The book's bridge preset uses `browAngle=0.12`
  and `browY=-0.06`, replacing the strong default pose (`0.85` / `-0.55`, eyes `0.72`).
- **Good mood** (`good_mood`): open eyes and a fine closed smile, distinct from the existing
  happy expression with closed smiling eyes.
- **Smug** (`smug`): dedicated half-lidded green eyes and a wider, slightly asymmetric closed
  smile, following the owner's specific reference. Its eye overlay yields to native closed
  blinks; its mouth overlay yields to speech and restores after speech finishes or is cancelled.

Five built-in imagegen calls produced four final drawings and one unused narrower mouth
retry. All final drawings passed the unchanged official sprite importer. The first smug
smile was clipped by the normal mouth slot; its isolated expression rig now uses a wider
`mouth.area` (`rx=43`, `ry=21`). After comparing the wider original drawing and the narrower
retry, the original was reimported with that correctly sized mask. Both corners now fit.
This private import geometry does not change the main rig or normal talking mouth region.
The raw first attempt, unused retry, final prompts and provenance remain in the project.

`build-awkward-overlay.py` now assembles **five native overlays** from isolated official
imports: the existing two awkward overlays, `good-mood-mouth`, `smug-eyes`, `smug-mouth`.
The main model still has thirteen sprites, with only its angry-mouth drawing replaced.
`expressions/bridge.json` stores the application presets. The local server exposes that
curated file at `/face-presets.json` (empty presets for projects without one); the demo
adds only supported menu choices. General runtime/Element versions and enums are unchanged.
New good-mood/smug control and restrained anger are bridge behavior; an unrelated generic
player using only the upstream `setExpression('angry')` still uses its upstream numeric pose.

The bridge now coordinates kana playback with speaking state as well, so new closed-mouth
overlays yield to kana and restore on natural completion, cancellation, sleep and reload.
Blink restoration is checked against simulation state, including a native blink already
in progress when waking, rather than requiring open eyes at a fixed timestamp.

**23 browser checks passed**, including nine-face comparison, restrained angry parameters,
open-eye good mood, smug blink handoff, mock/kana speech, natural kana completion and
cancellation, sleep/wake/visibility/reload and overlay clearing. Existing fixed book/hand/
chest/leg comparisons still show maximum RGBA delta **0** under the three tested poses.
Source pixels, the main rig and the repaired hair base remain unchanged. The base SHA-256
is still `340a728790e3451098824aa6781ae93422a9fc0d3bf8ab81bee1162e390d5a11`.

Model after the reference-expression pass, preserved as the named snapshot
`frieren-book-reference-faces.mavatar`: **1,521,713 bytes**.
SHA-256: `92a34111d25b71d4a522715644f45d115c109ae3dca48470a09b03dbe4505b73`.
The official archive validator passed. Preview remains <http://127.0.0.1:9489/>; refresh
to load the updated bridge and menu. The nine-expression image is `review-demo/emotions.png`.
Full prompts, reference paths and generation details are in `expressions/reference-provenance.json`.
The pre-change avatar and angry drawing remain in `work/reference-emotions-before/`.
The existing rebuild helper includes all expression projects automatically.

### Smug mouth refinement (2026-11-24)

The owner approved the other expressions and supplied `samples/frieren-smug2.webp`
to correct the tilted, rough smug mouth. Only that mouth drawing was replaced: its
corners are now nearly level, with a fine, smooth, shallow closed smile. The approved
smug eyes, other expressions, source, rig and repaired hair remain unchanged.

Three built-in imagegen edits were inspected. Two full-body attempts corrected the
tilt but retained a heavier line after resizing; neither is the final deliverable.
The selected edit used a 256 px face crop, generated at higher resolution and resized
through the official sprite reader. The raw crop failed the unchanged outside-mask
opaque-mean check (**17.63 > 16**) because protected face details were also redrawn.
Those protected pixels were discarded before assembly. Only the mouth mask, further
restricted to exclude the nose, was inserted into the original crop. The actual
assembled edit passed official `mask_only` checks with opaque mean **0**, edge-band
mean **0** and edge-band share **0%**; its returned alignment/tint result was used.
The full-size variant also has **zero changed pixels outside the mouth mask**.
No importer thresholds were relaxed.

Official overlay rebuilding and avatar validation passed. Comparing the previous
reference-expression archive against this archive confirms that the sole changed
entry is `built/overlays/smug-mouth.png`; every other entry is byte-identical.
All **23 browser checks passed** against this exact archive, including nine expressions,
blink/speech/kana handoff, sleep/wake/reload, fixed body/book ROIs and the mobile layout.
The running preview's downloaded avatar was verified against the final SHA-256.

Model after the first mouth refinement, retained as `frieren-book-smug2-fine.mavatar`:
**1,520,364 bytes**, SHA-256
`801b135cae9970a8b75b3b516d9111c02f72a84e569a86aac2e170b158a7aa02`.
Refresh <http://127.0.0.1:9489/> and choose Smug. The mouth comparison, crop assembly
helper, rejected attempts and previous archive are retained in
`work/smug-mouth-refinement-20261124/`. Complete prompts, input roles and validation
provenance are in `expressions/smug-mouth-refinement-20261124.json`.

### Mouth visibility at 350 px and displeased mouth (2026-11-24)

The owner found the previous fine smug mouth invisible at the actual 350 px avatar
height, preferred the older mouth's weight, and supplied `samples/frieren-smug3.webp`.
They also identified that the angry mouth still resembled the neutral mouth. Two
built-in imagegen crop edits now provide a darker, smooth, nearly level smug smile
and a continuous, rounded, slightly downturned displeased mouth following
`samples/frieren-angry.PNG`. The restrained open eyes and brow parameters are unchanged.

Only mouth pixels were assembled from the generated crops into the original source;
generated eye, nose and other protected pixels were discarded. Both actual assembled
edits passed the unchanged official importer with opaque/edge-band mean **0** and
edge-band share **0%**; returned tint/alignment adjustments were used. No pixels outside
the protected mouth masks changed. Official sprite/overlay building and archive
validation passed. The only changed archive entries versus the preceding fine-mouth
model are `built/sprites/mouth_angry.png`, its `built/sprites/sprites.json` cache identity,
and `built/overlays/smug-mouth.png`. All other entries are byte-identical.

All **23 browser checks passed** on the final archive. The actual **234 x 350** WebGL
render was inspected against the neutral, original smug and too-fine smug versions;
`work/mouth-readability-20261124/native-mouth-comparison.png` retains original-size
avatars and enlarged nearest-neighbour mouth pixels. In canvas mouth ROI
`[113,77,129,83]`, angry differs from neutral in **10 pixels** above 8/255 (maximum
channel difference **39**); new smug differs from the too-fine version in **10 pixels**
(maximum **77**). These are rendering observations, not a substitute for owner art review.

Current model: `projects/frieren-book/frieren-book.mavatar` / `frieren-runtime.mavatar`,
with snapshot `frieren-book-readable-mouths.mavatar`: **1,520,563 bytes**, SHA-256
`a7912905c3f3708a5abb49a8d41b2f4412025022c159fcc7c63fee240eb38c60`.
The running preview at <http://127.0.0.1:9489/> serves this exact archive. Full prompts
and validation are in `expressions/mouth-readability-20261124.json`; generated crops,
reproducible assembly helper and previous variants/archive are retained in
`work/mouth-readability-20261124/`.

### Japanese-only speech preview and restrained i mouth (2026-11-24)

The holding-book model's `mouth_i` resembled an oversized toothy grin. One built-in
imagegen edit now supplies a small, thin opening for Japanese **い**, checked in the
actual **234 x 350** WebGL render beside all five vowels. The protected original
face crop and mouth mask were used as inputs; generated pixels outside the mouth
were discarded. The unchanged official `read_variant` / `mask_only` importer
returned the alignment and tint correction used by the assembled variant. Outside-mask
changed pixels are **0**; nose, source image, rig, repaired hair, body and all approved
expression overlays are preserved. The archive comparison has exactly two changed
entries: `built/sprites/mouth_i.png` and the sprite manifest's cache identity.

The previous mock stream revealed characters every 85 ms while an unrelated sine
volume signal selected random vowels. `japanese-speech.mjs` now prepares a Japanese
kana timeline using the pinned upstream parser's vowel/onset tables. The bridge's
animation clock advances both revealed text and native `holdMouth` targets; there
is no separate text timer or random-volume mouth driver. Default pace is **0.19 s
per mora**. Small kana are displayed with their preceding kana as one beat, including
拗音 and combinations such as ティ / ファ; ー extends the vowel, っ / ん and punctuation
close the mouth, and m/b/p receive a brief initial closure. Native mouth smoothing
still applies. This is kana-driven visual timing without audio, a reading dictionary,
pitch/accent analysis or a real LLM/SSE connection. Kanji, Chinese and Latin input are
rejected with a request for kana readings instead of silently disappearing.

The shared demo now has a Japanese sample, a five-vowel sequence and individual
あ / い / う / え / お buttons. Hidden, sleeping, cancelled and replaced speech does
not continue revealing text. Good-mood/smug/awkward mouth overlays still yield during
speech and restore afterward. The chest model receives the shared timing/UI changes;
its original mouth drawings and archive are unchanged.

`node experiments/mavatar/check-japanese-speech.mjs` passed vowel/text mapping,
compound/foreign kana, long vowels, closures, punctuation, half-width normalization
and invalid-input checks. **25 book checks and 21 chest checks passed**, including
the five actual rendered vowel forms against revealed text, pause closure, natural
completion, cancellation, kanji rejection and hide/sleep cancellation. Existing
expression, fixed book/hand/chest/leg ROI, fallback and mobile checks also passed.

Current holding-book runtime / `frieren-book.mavatar` and named snapshot
`frieren-book-japanese-speech.mavatar`: **1,518,911 bytes**, SHA-256
`95a535e9abc930b0da3e6f096ab2982abeac2d4972c1516bbc75b4a0cfeca76c`.
Official sprite building and archive validation passed. Refresh
<http://127.0.0.1:9489/> for the book model or <http://127.0.0.1:9488/> for the chest.
The final variant is `projects/frieren-book/variants/mouth_i.png`. Raw generated
crop, complete prompt/input roles (`generation.json`), reproducible assembly and
before/after vowel sheets are retained in the private
`projects/frieren-book/work/japanese-speech-20261124/` directory. Assets remain in
the ignored Studio tree; the tracked repository contains only the bridge, UI,
checks and this documentation.

### Smaller Japanese a opening (2026-11-24)

The owner requested a smaller **あ**, approximately the size of the current **お**.
The bridge now uses native lip-sync openness **0** for a-vowel speech, selecting
the existing `mouth_a_half` drawing (steady native opening about **0.495**, below
the full-a threshold of **0.6**). Other vowels and pauses restore openness **0.3**;
stop/cancellation also resets it. The full **あ** drawing previously reached about
**0.738**; **お** remains about **0.615**. These are native opening values, not image
area ratios. The actual 234 x 350 comparison shows a restrained small mouth close
in visual size to お, while retaining a different shape.

No new image generation or asset replacement was needed. The book/chest archives,
approved i mouth and expression drawings are unchanged. The local comparison is
`projects/frieren-book/work/mouth-a-size-20261124/openness-comparison.png`.
Browser verification checks that a remains below the full-a threshold, opens less
than o, and does not reduce i/u/e/o opening after the vowel transition.
All **25 book checks and 21 chest checks passed** with this smaller-a setting.

### Mora articulation and text/mouth synchronization (2026-11-25)

On long lines the native smoothing blended consecutive moras into one held opening,
and revealed text could run ahead of the drawing. `japanese-speech.mjs` now gives each
timeline unit two closure windows:

- **`lipClose`**: m/b/p onsets close for `min(0.03 s, 16%)` of the unit.
- **`tailDip`**: before a mora with a consonant onset, or before a repeated vowel
  (おお), the unit's last `min(0.045 s, 24%)` closes. Vowel glides such as あい / やすもう
  stay open; ん / っ / punctuation already close and receive no extra dip.

The bridge enforces these at any frame rate. When a long frame would skip a dip, or
land after a bilabial window, it draws one closed frame (finishing the closure with
`holdMouth('n')` at the same simulation instant) before the next mora opens. Text is
published only after rendering: a new vowel's kana appears on the first frame whose
native `mouthOpen` reaches **0.18** (the open sprite's half-opacity point), so text and
drawing never disagree. Speaking while **surprised** temporarily drops the preset's
held-open mouth parameters, keeping the eyes and raised brows; the full surprised face
returns after speech ends or is cancelled. Speech uses `closeSpeed` **1** and restores
**0.7** afterwards.

Each forced closure frame rewinds the line clock to the next unit's start. Without
compensation that overshoot accumulated: a 20-mora おま line at 0.1 s per mora took
**2.5 s instead of 2.0 s** at 20 fps. The rewound time is now kept as debt and repaid by
running the line clock up to **25%** faster on later frames, so the closures stay
visible and the line still ends on schedule (measured **2.00 s** for おか and **2.05 s**
for おま at 20 fps). This matters for the later audio/TTS alignment.

`check-japanese-speech.mjs` adds articulation-dip assertions. Browser checks add
closed-then-open boundaries for fast/low-fps, repeated-vowel, narrow-vowel, bilabial,
glide and surprised cases, plus the 20 fps line-duration check. **27 book checks and
23 chest checks passed.** No assets or archives changed.
