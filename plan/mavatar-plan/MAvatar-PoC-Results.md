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

Seven accepted variants: `eyes_closed`, `eyes_half`, `eyes_smile`, `mouth_a`,
`mouth_a_half`, `mouth_i`, `mouth_o`; these produce ten cropped sprites.
Optional sad/surprised eye drawings and angry/sad mouth drawings were not generated.
Those emotions use the upstream numeric expression system and available sprites.

All generated full images are 1024 × 1536. The official importer resized them proportionally,
validated their alignment, then imported only the allowed mask regions with documented
skin-tint correction. The original base drawing was not replaced with a generated picture.
The first `mouth_a_half` was rejected: outside-mask opaque mean **16.50/255**, limit **16**.
One retry passed at **8.47/255**. The rejected image was retained; no thresholds were raised.
Eight imagegen calls total; no SD route comparison was performed.

Local prompts, inputs and output provenance are in:
`experiments/mavatar/studio/projects/frieren-chest/work/imagegen-provenance.json`, with
official prompts/masks under the project's `variant-requests/` folder.

## Verification and artifacts

`check-demo.mjs` passed all **14 checks** on the final archive and updated bridge:

- source PNG absent from the archive; real model loads with only local requests;
- source-space anchored-point deformation and 350 px browser-rendered ROI comparisons;
- Chinese simulated streaming and Japanese kana cancellation;
- held picker expressions with observable face parameters, surviving speech completion;
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

Final model: **1,770,138 bytes**. SHA-256:
`268557ae45c415e3bb2f08f2ec2b095a9f46318acfbef66b7af5487fa3489960`.
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
maps happy→smile, angry→angry, sad→sad, surprised→surprise, relaxed→halfLidded and
neutral→neutral, clearing the underlying talking emotion to avoid applying the face twice.
Sleep temporarily clears the overlay and restores it on wake. Tests assert smiling eyes,
surprised mouth opening, opposite sad/angry brow parameters and persistence after speech.
This changes control behavior, not the asset set: angry/sad dedicated artwork is still absent,
and those two expressions remain less distinct at 350 px. No additional image generation
was performed for this follow-up.

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
