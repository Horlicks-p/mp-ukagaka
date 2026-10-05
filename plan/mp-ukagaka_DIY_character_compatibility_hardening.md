# mp-ukagaka Generic / DIY Character Compatibility Hardening

Repository:

```text
Horlicks-p/mp-ukagaka
```

Target:

```text
main
```

Purpose:

This document describes two compatibility-hardening changes recommended after the Frieren SVG migration.

The goal is **not** to change the new Frieren SVG renderer.  
The goal is to make sure the SVG migration does not accidentally change the behavior of ordinary user-created / DIY characters.

---

# 1. Scope

Please review and modify only the generic character compatibility behavior described below.

Primary files expected to be relevant:

```text
includes/core/ukagaka-functions.php
js/ukagaka-anime.js
tests/Unit/ShellImageListTest.php
```

Possible related generated bundles / tests:

```text
js/dist/
ghost/Frieren/dist/
tools/node/
```

Do not redesign or remove the existing Frieren SVG system.

Do not revert:

- `assets.json`
- Frieren SVG sequences
- load generation protection
- first-frame-first rendering
- decoration hit testing
- stale async protection
- Dist Parity workflow

---

# 2. Issue A — Generic shell basename de-duplication is too broad

## Current behavior

`mpu_list_shell_images()` currently allows:

```php
array( 'svg', 'png', 'jpg', 'jpeg', 'gif', 'webp' )
```

and groups files by basename.

The current logic effectively means that files such as:

```text
shell1.png
shell1.webp
shell1.gif
```

are treated as the same logical frame, and only one survives.

The original reason for introducing basename preference was reasonable:

```text
foo.png
foo.svg
```

should prefer:

```text
foo.svg
```

during an SVG migration.

However, the implementation now de-duplicates **all formats**, not only SVG-vs-raster duplicates.

---

# 3. Why this can break DIY characters

Before the SVG migration, a generic shell directory could contain:

```text
shell1.png
shell1.webp
shell2.png
```

and all three files would be returned by the directory scan and naturally sorted.

A user may intentionally use two files with the same basename but different extensions as separate animation frames.

Example:

```text
frame1.png
frame1.webp
frame2.png
```

Old behavior:

```text
frame1.png
frame1.webp
frame2.png
```

Current behavior may become:

```text
frame1.png
frame2.png
```

or another single preferred format for `frame1`.

This is an unintended generic-shell compatibility change.

---

# 4. Desired behavior for shell scanning

Preserve the old behavior for raster files.

Only apply replacement semantics when an SVG with the same basename exists.

## Required examples

### Case 1 — SVG replaces same-basename raster

Input:

```text
a1.png
a1.svg
a2.png
```

Expected:

```text
a1.svg
a2.png
```

### Case 2 — raster formats remain separate frames

Input:

```text
a1.png
a1.webp
a2.png
```

Expected:

```text
a1.png
a1.webp
a2.png
```

Do **not** de-duplicate `a1.png` and `a1.webp`.

### Case 3 — SVG replaces all raster versions with same basename

Input:

```text
a1.png
a1.webp
a1.jpg
a1.svg
a2.png
```

Expected:

```text
a1.svg
a2.png
```

If an SVG exists for that basename, the SVG is the canonical replacement for all raster files sharing that basename.

### Case 4 — natural ordering must still work

Input:

```text
frame10.png
frame2.png
frame1.webp
```

Expected:

```text
frame1.webp
frame2.png
frame10.png
```

### Case 5 — subdirectories still ignored

Existing behavior should remain unchanged.

---

# 5. Suggested implementation approach

One possible approach:

1. Scan all valid image files.
2. Group only for the purpose of detecting whether a matching `.svg` exists.
3. If basename has an SVG:
   - include the SVG
   - exclude raster files of the same basename
4. If basename has no SVG:
   - include all raster files individually
5. Apply natural sort to the final filename list.

Pseudo-code:

```php
$svg_by_base = array();
$raster_files = array();

foreach ( $entries as $entry ) {
    $ext = strtolower( pathinfo( $entry, PATHINFO_EXTENSION ) );

    if ( 'svg' === $ext ) {
        $svg_by_base[ pathinfo( $entry, PATHINFO_FILENAME ) ] = $entry;
        continue;
    }

    if ( in_array( $ext, array( 'png', 'jpg', 'jpeg', 'gif', 'webp' ), true ) ) {
        $raster_files[] = $entry;
    }
}

$result = array_values( $svg_by_base );

foreach ( $raster_files as $entry ) {
    $base = pathinfo( $entry, PATHINFO_FILENAME );

    if ( ! isset( $svg_by_base[ $base ] ) ) {
        $result[] = $entry;
    }
}

natsort( $result );
return array_values( $result );
```

This is only illustrative.  
Use the cleanest implementation that matches the project style.

---

# 6. Required tests for Issue A

Please extend:

```text
tests/Unit/ShellImageListTest.php
```

Add regression tests for at least:

```text
same basename raster files are all preserved
SVG replaces raster files of same basename
natural sort still works
subfolders are ignored
```

Suggested test names:

```php
test_same_basename_raster_formats_are_preserved()
test_svg_replaces_same_basename_rasters()
```

Existing SVG preference test may need to be adjusted.

---

# 7. Issue B — Frieren renderer detection is based partly on display name

## Current behavior

`js/ukagaka-anime.js` currently contains logic similar to:

```js
isFrieren: function(num, name) {
    const checkNum = num !== undefined ? num : this.currentCharacterNum;
    const checkName = name !== undefined ? name : this.currentCharacterName;

    if (checkNum === 'default_1') {
        return true;
    }

    if (checkName && (
        checkName.indexOf('フリーレン') !== -1 ||
        checkName.indexOf('Frieren') !== -1 ||
        checkName.indexOf('frieren') !== -1
    )) {
        return true;
    }

    return false;
}
```

This predates the SVG migration, but it is now riskier because the Frieren renderer is much more specialized.

---

# 8. Why display-name detection is risky now

A DIY user may create an ordinary generic character named:

```text
Frieren Alter
Frieren Test
My Frieren
フリーレン風キャラ
```

If the character is a normal PNG / GIF / WebP shell and its display name contains `Frieren`, the generic character can accidentally enter the special Frieren runtime.

The Frieren runtime now expects things such as:

```text
shell/Frieren/assets.json
idle/
sleep/
book/
wake/
```

A normal DIY character does not have these resources.

Therefore renderer selection should not depend on arbitrary display text.

---

# 9. Desired behavior for Frieren detection

At minimum:

```js
checkNum === "default_1"
```

should continue identifying the built-in Frieren character.

A user-created character should **not** enter the Frieren renderer merely because its display name contains:

```text
Frieren
frieren
フリーレン
```

---

# 10. Preferred long-term design

A more explicit renderer selection would be cleaner.

Examples:

```json
{
  "renderer": "frieren-svg"
}
```

or:

```json
{
  "features": {
    "custom_renderer": "frieren"
  }
}
```

Then renderer selection becomes explicit rather than inferred from display name.

However:

## Important

For this task, prefer the **smallest safe compatibility patch**.

Do not introduce a large manifest architecture change unless it is clearly necessary.

A minimal acceptable change is:

```js
isFrieren: function(num, name) {
    const checkNum = num !== undefined ? num : this.currentCharacterNum;
    return checkNum === 'default_1';
}
```

provided this does not break another supported built-in path.

Please inspect the current repository before choosing the exact implementation.

---

# 11. Required tests for Issue B

Add regression coverage proving that:

```text
default_1
```

still uses the Frieren renderer.

And that:

```text
custom character ID + display name "Frieren Test"
```

uses the generic renderer.

Also verify:

```text
custom character ID + display name "フリーレン風キャラ"
```

does not activate the Frieren renderer.

If there is already a suitable JS smoke-test harness, add the tests there rather than creating unnecessary new infrastructure.

---

# 12. Generic character behavior that must remain unchanged

Please explicitly preserve the existing behavior for non-Frieren DIY characters:

## Single image shell

Supported formats should continue to include:

```text
PNG
JPG
JPEG
GIF
WebP
```

Server-placed trusted SVG should continue to work if already supported.

Single image should continue through:

```text
loadSingleImage()
```

## Multi-image shell

Multiple generic shell images should continue through:

```text
loadImages()
```

and:

```text
playAnimation()
```

Natural filename ordering must remain intact.

## Generic animation timing

Do not change the current generic frame timing behavior as part of this task.

## Character switching

Keep the new:

```text
loadGeneration
```

protection.

It is a positive compatibility improvement and should not be removed.

## Frieren cleanup

Do not weaken:

```text
cleanupFrierenElements()
```

or stale callback protection.

---

# 13. SVG upload security behavior must remain unchanged

Current policy is intentional:

```text
trusted server-placed SVG shell
    → supported

user ZIP-uploaded SVG
    → rejected
```

Do not loosen the ZIP upload SVG restriction.

The reason is that unsanitized SVG served directly by the site may carry stored-XSS risk.

This compatibility task is about generic runtime behavior, **not** about permitting SVG uploads.

---

# 14. Verification checklist

After changes, please confirm:

- [ ] ordinary PNG-only DIY character still works
- [ ] ordinary WebP-only DIY character still works
- [ ] ordinary GIF shell still works
- [ ] multi-frame raster shell still keeps natural order
- [ ] same-basename raster files remain separate frames
- [ ] same-basename SVG replaces raster variants
- [ ] SVG-only trusted server shell still works
- [ ] ZIP upload SVG restriction remains unchanged
- [ ] `default_1` still enters Frieren mode
- [ ] custom character named `Frieren Test` stays generic
- [ ] custom character named `フリーレン風キャラ` stays generic
- [ ] rapid character switching still passes stale-load tests
- [ ] existing Frieren shell smoke tests remain green
- [ ] Dist Parity remains green
- [ ] generated bundles are rebuilt and committed if source JS changes

---

# 15. Recommended validation commands

Use the repository's existing verification flow.

At minimum:

```bash
npm --prefix tools/node run test:frieren-shell
npm --prefix tools/node run build
```

and PHP unit tests covering shell image listing.

Prefer running the full project verification if practical:

```bash
npm --prefix tools/node run verify
```

After rebuilding, ensure no stale dist output remains.

---

# 16. Expected outcome

After this patch:

```text
Frieren
    → continues using the specialized SVG manifest renderer

ordinary DIY characters
    → continue using the generic renderer exactly as before

generic raster shells
    → retain backward-compatible frame enumeration

trusted SVG shells
    → remain supported

uploaded SVG ZIP shells
    → remain rejected for security
```

The patch should reduce accidental coupling between the Frieren-specific renderer and the generic user-created character system without changing the successful SVG migration itself.
