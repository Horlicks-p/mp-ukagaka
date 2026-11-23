# ADR-000 — Product identity and current experiment boundary

Date: 2026-11-23. Source: the v0.3.5 charter, D0 and §2.5.
This ADR does not authorize a release or implement migration.

**Status: reference only.** On 2026-11-17 the owner froze the 3.0 route. 2.x keeps its
normal release cadence, including new features, and has no scheduled end of life. The
current work is an experiment, not a resumed roadmap. The contracts below are the
pre-freeze decisions, kept so a future unfreeze starts from them. None of them is in
effect now. Before relying on any of them, the owner has to reconfirm it.

## Pre-freeze contracts (not in effect)

- A future 3.0 is the successor to MP Ukagaka, retaining plugin slug, main-file identity,
  `mp_ukagaka` option tree, `mpu_*`/`MPU_*` prefixes, REST namespace and update source.
  Parallel activation of 2.x and 3.0 on one WordPress site is not supported.
- The upgrade route is overwrite/update, not uninstall/reinstall. `uninstall.php` deletes
  the option holding settings and encrypted API keys. Back up settings before an upgrade.
- Preview work must not make existing settings irreversibly incompatible with 2.x. If a
  later migration needs schema changes, take a pre-upgrade snapshot and test restoration
  of both code and data, including after 3.0 has written its new schema.
- A future 3.0 uses WordPress 6.5+ script modules. Runtime code will be bundled locally
  at a verified version; production must not depend on Studio's Node server or floating CDN.
- Before 3.0 GA, Stable retains its normal release channel. If 3.0 ever reaches GA, it takes
  that channel and 2.x then reaches EOL; no such date or plan exists while the route is frozen. Any later exceptional 2.x security ZIP is distributed manually,
  with a versioned filename, checksum and a notice; it must not displace `releases/latest`.
- WP-version gating in the already-installed 2.x updater is a GA blocker. It is not fixed
  by this experiment. The charter documents the PUC 5.6 limitation and candidate veto;
  neither that implementation nor a PUC upgrade was made here.
- Public `.mavatar` files remain downloadable artwork. Omitting `source.png` does not
  make the built full-size textures private. A complete static poster accompanies playback.

## Current authorized slice

The owner requested a test using `plan/mavatar-plan/frieren-chest-fullbody.png` and approved
OpenAI-generated eye/mouth variants. The implementation is a local Studio project, an
independent HTML preview and a renderer bridge. It performs no WordPress option writes,
release workflow edits, production deployments or branch merges.

The Studio checkout/private assets are ignored; all `experiments/` files are export-ignored.
No Miko sample artwork was used as Frieren or included in the exported archive.

Before any preview release, inspect the actual `release.yml`/version tool behavior and
exercise an isolated dry run. Prerelease naming alone must not be assumed to bypass a
workflow triggered by `release: published`. There is no release authorization in this ADR.

## Required future upgrade/revert checks

1. Back up code, database and settings without exposing plaintext API keys.
2. Overwrite 2.x with the preview on a disposable site; verify old settings can be read.
3. Exercise any preview writes, then restore the 2.x code and pre-upgrade settings snapshot.
4. Verify dialogue, provider connectivity and encrypted-key readability on the restored site.
5. For the version gate, exercise notices, automatic update, manual update and ZIP overwrite
   on WP 6.4, with both gated and ungated old 2.x installations; document the legacy risk.

Those tests are pending: none was substituted with the independent browser PoC.

## Appendix — code baseline and proposed renderer boundary

Observed local `origin/main` on 2026-11-23:
`0a5cfd0fdd33b71dee0217714e4dc84dc2fc2676`, v2.41.0. This was a local remote-tracking
observation, not a new promise about the current live default branch.
The experiment branch is `docs/mavatar-plan` at `3726d43` plus uncommitted local work.

In this branch, `includes/core/frontend-functions.php` initializes `mpuCanvasManager` from
`/init` data. `js/ukagaka-anime.js` expects a 2D `#cur_ukagaka` canvas and delegates Frieren
animation to its ghost manager; it also returns early when already in Frieren mode.
It cannot safely be pointed at a `.mavatar` as if it were another image URL.

The new local `MeshBridge` tests a narrow future boundary: load/ready, emotion, speaking,
sleep, visibility and destroy. It uses a new element per load generation, discards stale
loads and resolves cancellation explicitly. The bridge has no dependency on WordPress,
LLM providers or SSE. Selecting its integration points into actual dialogue, touches,
decorations and character changes is still a separate bounded implementation task.

See [PoC Results](MAvatar-PoC-Results.md) for evidence and limitations.
