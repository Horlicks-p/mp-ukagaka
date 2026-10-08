# Built-in Dialogue Emotion Tag Plan

> Status: Proposed — not implemented
> Date: 2026-10-08
> Scope: Built-in dialogue loaded from `dialogs/*.json` and `dialogs/*.txt`
> Review amendment: incorporates the post-plan review of dynamic-code expansion, shared dialogue files, fade timing, and stale emoji cleanup.

## 1. Goal

Allow non-LLM dialogue authors to trigger a personality's existing emoji assets with the same inline emotion-tag vocabulary already used by LLM responses.

Examples:

```json
{
  "messages": [
    "フリレーンだ。千年以上生きた魔法使いだ。[laugh]",
    "人間の寿命は短いんだ。[sad]"
  ]
}
```

```text
フリレーンだ。千年以上生きた魔法使いだ。[laugh]
人間の寿命は短いんだ。[sad]
```

Expected behavior:

- The visible dialogue does not include `[laugh]` or `[sad]`.
- The matching personality-scoped image, such as `laugh.png`, is displayed through the existing emoji manager.
- The feature works without enabling an AI provider or supplying an API key.
- JSON and TXT use the same syntax and runtime behavior.

## 2. Design decision

This is a global dialogue-engine capability with personality-scoped validation.

- Parsing is global and shared by every personality.
- A tag is valid only when it is listed in the current personality's `manifest.json` under `emoji.supported`.
- Emoji files are loaded only from the current personality's configured emoji folder.
- One personality must never resolve a tag to another personality's assets.
- Personalities without emoji support continue to behave exactly as they do now.

Do not hard-code Frieren or a fixed list of emotion names in the dialogue loader.

## 3. Current behavior and reusable components

Both external formats already converge in `MPU_REST_Dialog::load_dialog()`:

- JSON reads `messages` directly.
- TXT passes each non-empty line through `mpu_str2array()`.
- Both then pass through `mpu_msg_code()` and are returned as `msg: string[]`.

The existing LLM pipeline already provides the required personality-aware primitives:

- `mpu_normalize_get_supported_tags()` obtains the current personality's supported tags.
- `mpu_normalize_extract_emotions()` validates and removes supported inline tags.
- `mpu_load_personality_emoji_config()` reads `manifest.json` emoji configuration.
- `/emoji-config` returns the current personality's base URL, supported tags, mappings, positions, and scales.
- `window.mpuEmojiManager.showEmoji()` renders the selected image.

The implementation should reuse these facilities instead of adding a second regular expression or a second tag registry.

## 4. Backward-compatible REST contract

Keep the existing `msg` field as an array of strings. Do not change it to an array of objects because existing frontend and third-party consumers may assume `string[]`.

Add a parallel, index-aligned field:

```json
{
  "msg": [
    "フリレーンだ。千年以上生きた魔法使いだ。",
    "人間の寿命は短いんだ。"
  ],
  "msg_emojis": [
    "laugh.png",
    "sad.png"
  ]
}
```

Rules:

- `msg_emojis[n]` belongs to `msg[n]`.
- Use `null` when that message has no supported tag.
- Preserve the array length and indexes exactly.
- Existing response fields (`msgall`, `auto_msg`, `next_msg`, `default_msg`) remain unchanged.
- Older clients that ignore `msg_emojis` continue to work.
- `msg_emojis` must have the same length as the final expanded `msg` array, not the legacy `msgall` value. `msgall` is currently calculated from the pre-expansion source count; this task must document but not silently change that existing behavior.

Parse author-written emotion tags before expanding dynamic dialogue codes. Process each source entry independently: extract its tag metadata first, call `mpu_msg_code( [ $clean_text ] )`, append every expanded result to the final `msg` array, and repeat the source entry's selected emoji for the same number of expanded results. This preserves index alignment without scanning post titles, commenter names, generated links, or HTML attributes for emotion syntax.

## 5. Tag rules

### Supported syntax

Use the same syntax accepted by the existing response normalizer:

```text
Dialogue text.[laugh]
Dialogue text.【laugh】
Dialogue text.［laugh］
Dialogue text.[emoji: laugh]
```

The documented and recommended authoring form is:

```text
Dialogue text.[laugh]
```

### Validation

- Tag matching is case-insensitive but resolves to the canonical spelling in `emoji.supported`.
- Build a union of `emoji.supported` tags declared by installed personalities. A tag in that installed-tag union is treated as authoring metadata and removed from visible dialogue even when the active personality does not support it.
- Only tags supported by the active personality may activate an image. A recognized tag belonging only to another installed personality is stripped but produces a `null` emoji for the active personality.
- Tags not declared by any installed personality remain visible text. This prevents arbitrary bracketed text such as `[note]` from being silently deleted.
- Markdown-like constructs and links must retain the existing normalizer protections.
- Recommend at most one supported emotion tag per dialogue entry.
- If multiple globally recognized tags occur, strip all of them and display the first one supported by the active personality, matching the existing primary-emotion convention as closely as possible.
- Do not run keyword-based automatic emotion guessing for built-in dialogue. Static authors must opt in explicitly with a tag.

The installed-tag union is specifically for safe display of shared dialogue files. For example, a generic personality that reuses Frieren's dialogue must not show a literal `[laugh]` merely because it has no `laugh` asset. Cache the union for the request rather than repeatedly reading every manifest for every line.

### `auto_msg`

Phase 1 supports tags in individual JSON messages or TXT lines only. Do not interpret emotion tags in the global `auto_msg` suffix because one suffix is appended to every line and would make every dialogue trigger the same expression.

## 6. Personality requirements

A personality that wants to use explicit built-in dialogue expressions should provide:

```text
ghost/<Personality>/
├─ manifest.json
├─ emojis/
│  ├─ laugh.png
│  └─ sad.png
└─ emoji-keywords.json       # optional for positioning, scale, and keyword fallback
```

Minimum manifest configuration:

```json
{
  "emoji": {
    "folder": "emojis",
    "supported": ["laugh", "sad"]
  }
}
```

Optional personality-specific emoji scripts remain supported. The common built-in-dialog feature must call the active `window.mpuEmojiManager` rather than assuming Frieren's renderer.

Frieren currently does not define `smile` or provide `smile.png`. Its available tags include `laugh`, `smirk`, `good`, and `smug_nod`. Do not add a `smile` alias in this task. Alias support can be designed separately if authors need vocabulary that differs from filenames.

## 7. Backend implementation

Primary file:

```text
includes/rest/class-mpu-rest-dialog.php
```

Suggested flow inside `load_dialog()`:

1. Read and validate JSON or TXT exactly as today.
2. Resolve the current personality ID and its supported tags through `mpu_normalize_get_supported_tags()`.
3. Build the request-cached union of tags declared by all installed personalities.
4. For each unexpanded source message, call `mpu_normalize_extract_emotions()` with the installed-tag union and `strip_unknown_tags = false`.
5. From the extracted tags, select the first tag also supported by the active personality; use its filename or `null`.
6. Call `mpu_msg_code( [ $clean_text ] )` for that one cleaned source message.
7. Append each expanded string to the final `msg` array and append the selected emoji to `msg_emojis` once per expanded string.
8. Preserve the existing pre-expansion `msgall` calculation unless it is addressed in a separate compatibility change.
9. Return the backward-compatible response.

Prefer extracting the transformation into small reusable helpers rather than growing the REST controller method. The message helper must accept an explicit personality ID and tag registry so tests and future character switching do not depend on ambient global state. The installed-tag registry helper should enumerate validated personality manifests (start from `mpu_get_available_personalities()`) and cache the union for the current request.

Do not route static dialogue through `mpu_normalize_ai_response()`: built-in dialogue does not contain `<think>` output, does not need keyword inference, and should not inherit unrelated AI-response behavior. Reuse the lower-level emotion extraction function only.

## 8. Frontend implementation

Primary files:

```text
js/ukagaka-dialog.js
js/ukagaka-core.js
js/ukagaka-emoji.js
```

Add one shared frontend helper for displaying a dialogue-associated emoji. It should:

- Accept an emoji filename or `null`.
- Use the active `window.mpuEmojiManager`.
- On every dialogue transition, call the manager's `cleanup()` first so an expression from the previous line cannot remain beside an untagged next line. (`hideEmoji()` takes the image element; `cleanup()` removes `currentEmoji` and exists on both the common and Frieren managers.)
- After cleanup, return when the new value is empty; otherwise call `showEmoji()`.
- Do not duplicate emoji-configuration waiting in this caller. Configuration readiness belongs to the active manager's `showEmoji()` contract. Frieren's manager already lazy-loads through `loadEmojiConfig()`; make the common manager follow the same contract if generic-personality testing shows it does not yet do so.
- Fail silently for personalities without an emoji renderer or usable base URL; dialogue text must still display.
- Never delay or cancel the dialogue solely because an emoji image/configuration failed.

Call the helper from all built-in dialogue display paths:

1. `loadExternalDialog()` when showing the first JSON/TXT line.
2. The non-LLM branch of `mpu_nextmsg()` for OK and automatic rotation.
3. `mpu_nextmsg_fallback()` when an LLM failure falls back to a built-in line.

The helper must run at the actual text-swap point, not merely when the next-message request begins:

- In the normal non-LLM OK path, call it inside `showBuiltInMessage`, after `jQuery("#ukagaka_msgbox").promise()` resolves the 600 ms fade-out and immediately before or with the new typewriter call.
- Do not show the new expression while the old dialogue box is still fading out.
- When a wake animation delays text display, call it inside the wake-animation completion callback.
- The first-message and fallback paths must follow the same rule at their own final display callbacks.

Store cleaned visible text, not tag-bearing source text, in `window.mpuChatHistory` for built-in auto-talk and fallback history entries.

## 9. Generic-character compatibility

The configuration and endpoint layers are already personality-aware, but the current renderer retains Frieren-era names and defaults:

- `.frieren-emoji` CSS class
- comments and log keys referring to Frieren
- fallback head-position assumptions tuned for Frieren
- `ghost/Frieren/frieren-emoji.js` overriding the common manager

For the first implementation:

- Do not require a Frieren-specific manager.
- Continue allowing personality-specific scripts to replace or extend `window.mpuEmojiManager`.
- Use `emoji-keywords.json` mapping values (`position`, `scale`) whenever supplied.
- Verify the generic shell path using `#cur_ukagaka`, not only Frieren's `#frieren_idle_apng`.
- Keep current Frieren visuals unchanged.

If testing shows the generic manager cannot position another personality correctly, make the minimum generic refactor:

- Rename/add the element class as `mpu-character-emoji` while temporarily retaining `.frieren-emoji` as a compatibility alias.
- Move fallback position defaults into common configuration or document them as defaults.
- Keep Frieren-specific position values in `emoji-keywords.json`.

Do not combine this task with a broad rewrite of the animation or personality renderer systems.

## 10. Failure behavior

- Missing emoji file: log through the existing logger and remove the failed image; keep the dialogue visible.
- Missing `/emoji-config`: keep the dialogue visible without an expression.
- Tag known to another installed personality but unsupported by the active personality: strip the tag and show no image.
- Tag unknown to every installed personality: preserve it as ordinary text.
- Personality with no emoji configuration: no-op, with no additional REST failure.
- Untagged next message: clear the previous expression at the text-swap point, even if its normal display timeout has not elapsed.
- Empty dialogue after removing a valid tag: allow the existing empty-message safeguards to handle it; add a test so it cannot crash the typewriter.
- Character switched while configuration/image loading is pending: do not attach the previous personality's emoji to the new character. Capture the personality/config generation or verify the active base URL before rendering.

## 11. Tests

### PHP unit tests

Add focused coverage for the dialogue-message transformation:

- JSON message with a supported tag returns clean `msg` plus aligned `msg_emojis`.
- TXT line with a supported tag produces the same result as JSON.
- Message without a tag returns `null` emoji.
- Tag supported only by another installed personality is removed but returns `null` emoji.
- Tag unknown to every installed personality remains visible and returns `null` emoji.
- Tag matching is case-insensitive.
- Multiple messages preserve exact index alignment.
- A dynamic-code expansion that produces N messages produces N copies of the source emoji metadata.
- Post titles, commenter names, generated links, and HTML attributes containing `[tag]` text are not reparsed or modified after expansion.
- Two personalities with different supported lists cannot resolve each other's tags.
- Multiple globally recognized tags use the first active-personality-supported image and remove all recognized tags.
- Existing `/dialog` fields and string-array contract remain unchanged.
- `msg_emojis` length matches the final `msg` length even when legacy `msgall` reflects the pre-expansion source count.

Extend existing response-normalizer tests only when testing the shared extraction primitive. Put `/dialog` contract tests near the REST dialog tests rather than treating static dialogue as an AI-response test.

### JavaScript smoke tests

Add a small test harness covering the shared display helper:

- Calls `showEmoji("laugh.png")` once for the selected message.
- Clears the previous expression before displaying a tagged or untagged next message.
- For `null`, clears the previous expression and does not call `showEmoji()`.
- Delegates lazy configuration loading to the active emoji manager rather than starting a duplicate load in the dialogue helper.
- Dialogue still displays when configuration loading rejects.
- First message, normal `mpu_nextmsg()`, and fallback select the emoji at the same message index.
- Wake-animation callback does not display the emoji early.
- The normal OK path does not clear/show the next expression until the 600 ms message-box fade-out has completed.
- Clean text, not the source tag, is written to history.

### Playground E2E

Use a fresh Playground site with no AI settings:

1. Select Frieren.
2. Load a fixture TXT dialogue containing `[laugh]`.
3. Assert the dialogue text does not contain `[laugh]`.
4. Assert the expression image URL ends in `/ghost/Frieren/emojis/laugh.png`.
5. Click OK and verify the next expression is not cleared or shown until the old message box reaches opacity 0, then appears with the new line during fade-in.
6. Repeat with the equivalent JSON fixture.
7. Test a generic fixture personality with its own emoji folder and supported tags.
8. Follow a tagged line with an untagged line before the three-second emoji timeout and confirm the old expression is removed at the text swap.
9. Reuse Frieren's tagged dialogue with a personality that lacks the tag; confirm the tag is hidden and no cross-personality image is requested.
10. Confirm a tag unknown to all installed personalities remains visible ordinary text.

## 12. Documentation

Update canonical documentation under `docs-en/`:

- Built-in dialogue authoring for JSON and TXT.
- One non-empty TXT line equals one dialogue entry.
- Tags are personality-scoped and must appear in `emoji.supported`.
- Tags known to an installed personality are hidden as metadata, but only the active personality may display the matching image.
- Tags unknown to every installed personality remain visible.
- Recommended one-tag-per-entry rule.
- Example manifest, directory layout, JSON, and TXT.
- List Frieren's actual supported tags or link to its manifest rather than documenting `[smile]` as available.

If the REST response is documented, add the optional `msg_emojis` field to `docs-en/API_REFERENCE.md`.

## 13. Build and verification

If JavaScript source changes, rebuild and commit both distribution bundles:

```powershell
npm --prefix tools/node run build
```

Minimum validation:

```powershell
php tools/php/vendor/bin/phpunit --configuration tests/phpunit.xml.dist
npm --prefix tools/node run test:visual-ready
npm --prefix tools/node run test:frieren-shell
npm --prefix tools/node run build
git diff --check
```

Prefer the full repository verification before merge:

```powershell
npm --prefix tools/node run verify
```

Manual checks:

- Fresh install with no AI provider/API key.
- JSON tagged dialogue.
- TXT tagged dialogue.
- Frieren expression position and scale unchanged.
- Generic personality expression uses only its own configuration and files.
- OK fade-out/fade-in timing remains correct.
- New expressions do not appear before the old dialogue finishes fading out.
- An untagged next line clears the previous expression immediately at the text swap.
- Auto-talk timer remains a single chain.

## 14. Suggested implementation order

1. Add backend transformation tests.
2. Add the personality-aware dialogue transformation helper.
3. Extend `/dialog` with aligned `msg_emojis` while preserving `msg: string[]`.
4. Add the shared frontend emoji-display helper.
5. Wire first-message, next-message, and fallback paths.
6. Add JavaScript smoke coverage.
7. Add JSON/TXT Playground E2E coverage.
8. Verify one generic personality in addition to Frieren.
9. Update canonical documentation.
10. Rebuild bundles and run full verification.

## 15. Acceptance criteria

- [ ] Non-LLM JSON dialogue can explicitly trigger a supported personality emoji.
- [ ] Non-LLM TXT dialogue can explicitly trigger the same emoji using the same syntax.
- [ ] Supported tags are removed from visible dialogue and stored history.
- [ ] Tags known to an installed personality are removed from shared dialogue even when the active personality cannot render them.
- [ ] Tags unknown to every installed personality are not silently removed.
- [ ] First line, OK/manual next, auto-talk, and LLM fallback all behave consistently.
- [ ] New expressions are triggered only after the preceding fade-out completes.
- [ ] An untagged next line clears any still-visible expression from the previous line.
- [ ] Tags and asset paths are isolated to the active personality.
- [ ] A personality without emoji support has no behavior change.
- [ ] Frieren retains its current expression placement and timing.
- [ ] At least one non-Frieren fixture personality passes the same flow.
- [ ] Existing `/dialog` consumers remain compatible.
- [ ] Production bundles are rebuilt and verification passes.

## 16. Out of scope

- Automatic keyword-based emotion selection for built-in dialogue.
- Emotion aliases such as `smile -> laugh.png`.
- Adding or redrawing emoji artwork.
- Changing expression duration through dialogue syntax.
- Multiple simultaneously displayed emoji images.
- `<think>` blocks in static dialogue files.
- A broad rewrite of Frieren animation, touch, decoration, or shell rendering.
