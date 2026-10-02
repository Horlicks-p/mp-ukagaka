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

4. **Ship** — commit as `chore(release): X.Y.Z` on `main` and push it, then create and push an
   annotated tag:
   ```bash
   git tag -a vX.Y.Z -m "vX.Y.Z" && git push origin vX.Y.Z
   ```

5. **Publish the GitHub Release** — pushing the tag alone does nothing; `release.yml` runs on
   `release: published`:
   ```bash
   gh release create vX.Y.Z --title "vX.Y.Z — Short Title" --notes-file <notes.md>
   ```
   Use the version's `docs-en/CHANGELOG.md` section as the notes. The workflow builds
   `mp-ukagaka.zip` with `git archive` from the tag and attaches it within a minute or so;
   never attach it manually. The in-plugin updater (`includes/updater/github-updater.php`)
   downloads that asset, so a release without it cannot be installed by sites.
   If the workflow failed, re-run it from the Actions tab (it also accepts a manual
   `workflow_dispatch` with the tag).

`README_ja.md` and `README_zh-TW.md` are intentionally version-free — both state that
`docs-en/` is the single source of truth, and neither is synced per release.
