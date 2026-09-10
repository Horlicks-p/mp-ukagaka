# Releasing

`tools/node/bump-version.js` owns both halves of a release: it rewrites the version
markers, and it refuses to pass until the release notes exist. `npm --prefix tools/node
run verify` runs the check, so an incomplete release fails the pipeline.

1. **Bump the markers**
   ```bash
   npm --prefix tools/node run version:set <version> [YYYY-MM-DD]
   ```
   Rewrites the plugin header, `MPU_VERSION`, `readme.txt` Stable tag, root README badge,
   `docs-en/README.md` version + date, and the `CLAUDE.md` / `API_REFERENCE.md` markers,
   then prints the release notes still owed.

2. **Write those notes by hand** — the script never generates prose, and deliberately
   never fills in the version for them (a heading claiming a release the text below does
   not describe would pass the check while being wrong):
   - `docs-en/CHANGELOG.md` — `## [X.Y.Z] - YYYY-MM-DD` section. Canonical, most detailed.
   - `readme.txt` — `= YYYY-MM-DD =` / `* vX.Y.Z` block under `== Changelog ==`.
   - `README.md` — retitle `## 🎉 What's New in vX.Y.Z` **and** add a
     `**Title** (vX.Y.Z): …` paragraph. Keep the three most recent releases in detail;
     fold the fourth into the "Earlier releases" line.

3. **Verify** — `npm --prefix tools/node run verify` must be green.

4. **Ship** — commit as `chore(release): X.Y.Z`, then tag `vX.Y.Z` and push the tag.
   The `release.yml` workflow builds and attaches `mp-ukagaka.zip`; never attach it manually.

`README_ja.md` and `README_zh-TW.md` are intentionally version-free — both state that
`docs-en/` is the single source of truth, and neither is synced per release.
