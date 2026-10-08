# Built-in Dialogue Emotion Tag Plan

> Status: Proposed — not implemented
> Date: 2026-10-08
> Scope: Built-in dialogue loaded from `dialogs/*.json` and `dialogs/*.txt`
> Review amendment 1: incorporates the post-plan review of dynamic-code expansion, shared dialogue files, fade timing, and stale emoji cleanup.
> Review amendment 2: incorporates the code-verified review of the `/nextmsg` server fallback, the active-personality mismatch between `/dialog` and the on-screen character, pending emoji loads, `mpu_msg_code()` de-duplication, and test wiring.
> Review amendment 3: incorporates the code-verified review of `cur_num → personality` resolution through `dialog_filename`, the seven random built-in display sites outside `ukagaka-core.js`, the PNG-only filename contract, in-flight `loadEmojiConfig()` requests, and the `mpu_get_msg_arr()` error-return contract.

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
- Every path that displays a built-in line — including the server-side fallback inside `/nextmsg` when an LLM is enabled — applies the same rules.

## 2. Design decision

This is a global dialogue-engine capability with personality-scoped validation.

- Parsing is global and shared by every personality.
- A tag is valid only when it is in the active personality's **resolved** emoji configuration, `mpu_load_personality_emoji_config()['supported']`. That is `manifest.json` `emoji.supported` when the manifest declares an `emoji` block, otherwise the filenames auto-detected in the personality's `emojis/` folder. This is the same source the LLM path and `/emoji-config` already use; the dialogue feature must not introduce a stricter or looser rule.
- Emoji files are loaded only from the active personality's configured emoji folder.
- The **active personality** is the character currently on screen for this visitor (the `cur_num` the browser is showing), not merely the admin default. See §4.1.
- One personality must never resolve a tag to another personality's assets.
- Personalities without emoji support continue to behave exactly as they do now.

Do not hard-code Frieren or a fixed list of emotion names in the dialogue loader.

## 3. Current behavior and reusable components

### 3.1 Two backend producers of built-in lines

Built-in dialogue reaches the browser through two backend functions that both read the same dialog files:

1. `MPU_REST_Dialog::load_dialog()` (`/dialog`) — used by `loadExternalDialog()` to fill the frontend dialog store.
   - JSON reads `messages` directly; TXT passes each non-empty line through `mpu_str2array()`.
   - Both pass through `mpu_msg_code()` and are returned as `msg: string[]`.
2. `mpu_get_msg_arr()` (`includes/core/ukagaka-functions.php`) — used by `MPU_REST_Dialog::nextmsg()` (`/nextmsg`):
   - in the LLM branch, as the **server-side fallback** when generation fails, is rejected as a repeat (`MPU_USE_FALLBACK`), or Ollama is busy;
   - in the non-LLM branch.

   It reads the file through `mpu_get_msg_from_file()` and also calls `mpu_msg_code()`.

Today `nextmsg()` then routes whichever line it picked, built-in or LLM, through `mpu_normalize_ai_response_for_rest()`. For a built-in line this means:

- an active-personality-supported tag is stripped and returned as `emoji`;
- a tag known only to another installed personality stays visible;
- an untagged line goes through keyword emoji guessing (`mpu_analyze_emoji_from_text()`);
- parsing happens after `mpu_msg_code()` expansion, so post titles, commenter names and generated links are scanned.

All four conflict with the rules in §5. This plan therefore covers both producers.

### 3.2 `mpu_msg_code()` de-duplication

`mpu_msg_code()` ends with `return array_unique($templist);`. This has two consequences:

- Duplicate lines across the whole file are collapsed.
- The returned array can have **non-sequential keys**. When it does, `json_encode` emits an object instead of an array. The frontend's `Array.isArray(store.msg)` check then fails and the "訊息列表格式錯誤" error appears. `/nextmsg` can also pick a random index that falls into a gap.

### 3.3 Reusable primitives

The existing LLM pipeline already provides the required personality-aware primitives:

- `mpu_normalize_get_supported_tags( $personality_id )` obtains a personality's resolved supported tags.
- `mpu_normalize_extract_emotions( $text, $supported, $strip_unknown )` validates and removes inline tags.
- `mpu_load_personality_emoji_config()` reads the manifest emoji block or auto-detects it.
- `mpu_get_personality_id_from_ukagaka_name( $cur_num )` maps an ukagaka key to its personality by treating the ukagaka's `dialog_filename` as the personality folder name; it returns `null` when that name is not an installed personality. `mpu_resolve_personality_id( $cur_num )` (`wp-info-functions.php`) wraps it with a fallback to `mpu_get_current_personality_id()`. See §4.1 for why the dialogue feature needs a stricter variant.
- `mpu_get_available_personalities()` enumerates installed personalities (statically cached).
- `/emoji-config` returns the base URL, supported tags, and mappings (positions and scales).
- `window.mpuEmojiManager.showEmoji()` / `cleanup()` render and clear the image (both the common and Frieren managers implement both).

The implementation should reuse these facilities instead of adding a second regular expression or a second tag registry.

## 4. Backward-compatible REST contract

### 4.1 Request: identify the on-screen character

`mpu_get_current_personality_id()` resolves from the global option (`current_personality`, then `cur_ukagaka`). The visitor's on-screen character, however, can differ: `mpu_html()` honours the `mpu_ukagaka_<COOKIEHASH>` cookie, and `mpuChange()` switches characters at runtime. Today `loadExternalDialog()` sends only `file`, and `/emoji-config` takes no parameter. After a character switch, both endpoints can therefore resolve tags and base URLs for the admin default instead.

Rules:

- `loadExternalDialog()` sends `cur_num`: the key `mpuChange()` just switched to, or the current `#ukagaka_num` / `window.mpuInfo.num`.
- `/emoji-config` accepts the same optional `cur_num`; the frontend `loadEmojiConfig()` sends it.
- The backend accepts `cur_num` only when it is a key of `$mpu_opt['ukagakas']`. It never accepts a personality folder name from the client.
- `/dialog`, `/emoji-config`, and both built-in branches of `/nextmsg` (via `mpu_get_msg_arr()`) resolve the personality through **one** resolver with exactly these cases:

  | `cur_num` | `dialog_filename` names an installed personality | Emoji personality |
  |---|---|---|
  | missing or not a key of `ukagakas` | — | `mpu_get_current_personality_id()` (today's behavior; keeps older clients working) |
  | valid | yes | that personality |
  | valid | no | **none**: every installed-union tag is stripped, `msg_emojis` is all `null`, `/emoji-config` reports no `baseUrl` |

- Implement the resolver by extending `mpu_resolve_personality_id()` with an opt-in strict mode (for example a second `$fallback_to_current = true` parameter) instead of adding a new function. Existing callers keep the lenient default.

#### Why the third row is "none", not the admin default

`mpu_get_personality_id_from_ukagaka_name()` derives the personality from the user-editable `dialog_filename` (`options/options_ukagakas.php`). A character whose dialogue file is, say, `shared_dialog` or `default_1` is not a personality. The lenient fallback would hand it the admin default's emoji (for example Frieren's) and draw them over a shell that is not Frieren. That violates §2 ("never resolve a tag to another personality's assets"). Showing no expression is the safe failure.

#### Convention this plan relies on

The plugin already identifies a character's personality by `dialog_filename == ghost/<Personality>/` folder. The LLM system prompt, sleep mode, Akismet and Turnstile integrations all use the same mapping. This plan follows that convention rather than redefining it:

- Two characters that share one dialogue file share one personality, everywhere, not only for emoji.
- "Reusing another personality's dialogue" in §5 therefore means a *copied* file under a different name, which resolves to row 3 (or to a different personality whose folder matches the new name).

A persisted per-character `personality_id` setting, decoupling dialogue file from personality, would fix all of these consumers at once. It needs an admin field, a migration, and changes to every caller listed above, so it is out of scope (§16) and should be its own task.

`/change` does not need the resolver: it returns only shell, name, and an empty `msglist`, and the frontend then reloads dialogue and emoji config with the new `cur_num`.

### 4.2 Response: aligned `msg_emojis`

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
- Both arrays are always JSON arrays (sequential keys from `array_values()`), never objects.
- Existing response fields (`msgall`, `auto_msg`, `next_msg`, `default_msg`) remain unchanged.
- Older clients that ignore `msg_emojis` continue to work.
- `msg_emojis` must have the same length as the final expanded `msg` array, not the legacy `msgall` value. `msgall` is currently calculated from the pre-expansion source count; this task must document but not silently change that existing behavior.
- `mpu_get_msg_arr()` returns the same `msg` / `msg_emojis` pair, built by the same helper. For the same file and personality, `/dialog` and `/nextmsg` therefore agree on every index. The frontend's `#ukagaka_msgnum` is shared between them.
- The contract holds on **every** return path, not just success. `mpu_get_msg_arr()`'s recursion-limit return and its "file not found" substitution return a one-element `msg` with `msg_emojis: [null]`. `mpu_get_msg_from_file()` keeps its existing plain `string[]` contract and never returns `msg_emojis`. Its error strings (invalid name, missing, too large, unreadable) gain the aligned `msg_emojis: [null]` only when the built-in builder or `mpu_get_msg_arr()` wraps them. Likewise every `/dialog` response that carries `msg` carries an aligned `msg_emojis`. Error text never goes through tag parsing.

### 4.3 Expansion order and de-duplication

Parse author-written emotion tags before expanding dynamic dialogue codes. Process each source entry independently:

1. Extract its tag metadata first.
2. Call `mpu_msg_code( [ $clean_text ] )`.
3. Append every expanded result together with the source entry's selected emoji.

This avoids scanning post titles, commenter names, generated links, or HTML attributes for emotion syntax.

Because per-entry expansion bypasses `mpu_msg_code()`'s whole-list `array_unique()`, the helper restores the existing de-duplication explicitly:

- After all entries are expanded, de-duplicate on the **final visible text**, first occurrence wins.
- A dropped duplicate also drops its `msg_emojis` slot; the survivor keeps the emoji of its first occurrence.
- Example: `こんにちは[laugh]` followed by `こんにちは[sad]` yields one `こんにちは` with `laugh.png`.
- Finish with `array_values()` on both arrays.

This keeps today's "duplicates collapse" behavior and fixes the sparse-key bug in §3.2.

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

The inline pattern requires a letter as the first character, so dynamic codes such as `:recentpost[5]:` are never mistaken for tags. Lock this in with a test.

### Validation

- Tag matching is case-insensitive but resolves to the canonical spelling in the resolved `supported` list.
- Build a union of resolved `supported` tags across installed personalities. Enumerate them with `mpu_get_available_personalities()` without placeholders, so placeholder entries do not widen the union.
- A tag in that installed-tag union is treated as authoring metadata and removed from visible dialogue even when the active personality does not support it.
- Only tags supported by the active personality may activate an image. A recognized tag belonging only to another installed personality is stripped but produces a `null` emoji for the active personality.
- Tags not declared by any installed personality remain visible text. This prevents arbitrary bracketed text such as `[note]` from being silently deleted.
- Markdown-like constructs and links must retain the existing normalizer protections.
- Recommend at most one supported emotion tag per dialogue entry.
- If multiple globally recognized tags occur, strip all of them and display the first one supported by the active personality, matching the existing primary-emotion convention as closely as possible.
- Do not run keyword-based automatic emotion guessing for built-in dialogue, on any path, including the `/nextmsg` server fallback. Static authors must opt in explicitly with a tag.

The installed-tag union exists so reused dialogue displays safely. For example, a character whose dialogue file was copied from Frieren's (under another name, see §4.1) must not show a literal `[laugh]` merely because it has no `laugh` asset, or no personality at all. Cache the union for the request rather than repeatedly reading every manifest for every line.

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

Recommended manifest configuration:

```json
{
  "emoji": {
    "folder": "emojis",
    "supported": ["laugh", "sad"]
  }
}
```

Without an `emoji` block, the supported list is auto-detected from the `.png` / `.apng` / `.gif` filenames in `emojis/`. That works, but an explicit list is recommended so a stray file cannot silently become a tag.

**Phase 1 file format: PNG only.** A tag `<tag>` always resolves to `<tag>.png`. This matches the existing LLM contract: `response-normalizer.php` also hard-codes `$tag . '.png'`. Animated PNG content is fine, but the file name must end in `.png`.

Because auto-detect also accepts `.apng` and `.gif`, a folder containing only `laugh.gif` makes `laugh` a supported tag whose `laugh.png` does not exist. The request then takes the "missing emoji file" path in §10: logged, image removed, dialogue still visible. Document this in the authoring guide.

A `tag → actual filename` resolver would let `.apng` / `.gif` work. It must be shared with the LLM emotion contract, though, so it is out of scope (§16).

Optional personality-specific emoji scripts remain supported. The common built-in-dialog feature must call the active `window.mpuEmojiManager` rather than assuming Frieren's renderer.

Frieren currently does not define `smile` or provide `smile.png`. Its available tags include `laugh`, `smirk`, `good`, and `smug_nod`. Do not add a `smile` alias in this task. Alias support can be designed separately if authors need vocabulary that differs from filenames.

## 7. Backend implementation

Primary files:

```text
includes/rest/class-mpu-rest-dialog.php   (/dialog, /nextmsg)
includes/rest/class-mpu-rest-ghost.php    (/emoji-config)
includes/core/ukagaka-functions.php       (mpu_get_msg_arr)
```

### 7.1 Shared helpers

Add two small helpers and use them from every producer. Do not grow the REST controller methods.

- **Installed-tag registry**: returns the request-cached union described in §5. It is built from `mpu_get_available_personalities()` plus `mpu_normalize_get_supported_tags()` per personality.
- **Built-in message builder**: takes the unexpanded source messages, an explicit personality ID, and an explicit tag registry. It returns `[ 'msg' => string[], 'msg_emojis' => (string|null)[] ]`. Steps:
  1. For each source entry, call `mpu_normalize_extract_emotions( $text, $installed_union, false )`.
  2. Select the first extracted tag that is also in the active personality's supported list; use `<tag>.png` (PNG only, §6) or `null`. When the resolver returned no personality (§4.1 row 3), the active supported list is empty, so every entry gets `null`.
  3. Call `mpu_msg_code( [ $clean_text ] )` and append each expanded string with the selected emoji.
  4. Apply the first-wins de-duplication and `array_values()` from §4.3.

Explicit parameters keep tests and character switching independent of ambient global state.

Place the helpers in a module that loads after `response-normalizer.php` and `personality-emoji.php`. Confirm this against the `mpu_load_modules()` arrays before choosing the file, and make sure the helpers are available in REST requests (not frontend-only modules).

### 7.2 Producers

- `load_dialog()`:
  1. Read and validate JSON or TXT exactly as today.
  2. Resolve the personality from the validated `cur_num` (§4.1).
  3. Call the builder.
  4. Return `msg` and `msg_emojis`. Keep the pre-expansion `msgall` calculation.
- `mpu_get_msg_arr( $num )`:
  - Call the builder with the personality the strict resolver returns for `$num` (§4.1).
  - Return `msg_emojis` alongside `msg`. `msg_emojis` is an additive key; the only callers are the two `nextmsg()` branches.
- `nextmsg()` built-in branches (the LLM-branch server fallback and the non-LLM branch):
  - Take `msg` and `msg_emojis[$msgnum]` from `mpu_get_msg_arr()`.
  - **Do not** pass the line through `mpu_normalize_ai_response_for_rest()`, so neither keyword guessing nor post-expansion parsing happens.
  - Still apply the existing display-length limit.
  - Return the same response fields as today:
    - `emoji` is the selected file or `null`;
    - `emotion_tags` / `emotion_files` / `primary_emotion_*` are consistent with it;
    - `think` is `''`.
  - The LLM-success branch keeps using the AI normalizer unchanged.
- `get_emoji_config()`: resolve the personality from the validated `cur_num` (§4.1).

Do not route static dialogue through `mpu_normalize_ai_response()`: built-in dialogue does not contain `<think>` output, does not need keyword inference, and should not inherit unrelated AI-response behavior. Reuse the lower-level emotion extraction function only.

## 8. Frontend implementation

Primary files:

```text
js/ukagaka-dialog.js
js/ukagaka-core.js
js/ukagaka-chat-mode.js          (chat-exit line)
js/ukagaka-greeting.js           (greeting fallbacks)
js/ukagaka-context.js            (page-context fallbacks)
js/ukagaka-emoji.js
ghost/Frieren/frieren-emoji.js   (generation check only)
```

### 8.1 Shared helpers

There are two layers. Every built-in line goes through the second, which uses the first.

**(a) Emoji display helper**, also used by LLM responses that carry `res.emoji`. It should:

- Accept an emoji filename or `null`.
- Use the active `window.mpuEmojiManager`.
- On every dialogue transition, first invalidate any pending display (see §8.2), then call the manager's `cleanup()`. An expression from the previous line therefore cannot remain beside, or appear later beside, an untagged next line. (`hideEmoji()` takes the image element; `cleanup()` removes `currentEmoji` and exists on both managers.)
- After cleanup, return when the new value is empty; otherwise call `showEmoji()`.
- Not duplicate emoji-configuration waiting. Configuration readiness belongs to the active manager's `showEmoji()` contract.
- Fail silently for personalities without an emoji renderer or usable base URL; dialogue text must still display.
- Never delay or cancel the dialogue solely because an emoji image/configuration failed.

The existing inline "load config then `showEmoji()`" blocks in `ukagaka-core.js` (the LLM branch of `mpu_nextmsg()` and the page-context path) become calls to this helper.

**(b) Built-in message helper**, `mpuDisplayBuiltInMessage(store, index, options)`, defined in `js/ukagaka-core.js`. `ukagaka-dialog.js`, `ukagaka-greeting.js`, `ukagaka-context.js`, and `ukagaka-chat-mode.js` all declare `mpu-core` as a script dependency, and `ukagaka-core.js` also precedes them in the `tools/node/build.js` bundle order. Helper (a) lives in the same file.

This is the single place that turns a dialog-store index into a displayed line:

- reads `store.msg[index]` and `store.msg_emojis[index]` (missing array or slot → `null`);
- appends `store.auto_msg`;
- calls helper (a), then `mpu_typewriter()` with the unescaped text, passing through typewriter options the caller needs;
- returns the displayed text so the caller can write history.

It does **not** pick the index, fade the message box, wait for animations, or write history. Those stay with the caller, because their timing and history formats differ (for example chat-exit pushes a synthetic `（独り言）` user turn). A tiny `mpuRandomBuiltInIndex(store)` may replace the duplicated `Math.floor(Math.random() * msgArr.length)` expressions.

Callers must invoke helper (b) at their real text-swap point (§8.3). The helper never decides timing on its own.

### 8.2 Lazy loading and stale-load protection (required)

- **Common manager lazy-load is required, not conditional.** `js/ukagaka-emoji.js` `showEmoji()` currently returns immediately when `mpuEmojiConfig.baseUrl` is empty; it does not load configuration. Make it follow the Frieren manager's contract: call `loadEmojiConfig()` and retry once it resolves.
- **Generation token.** `cleanup()` alone cannot stop a load that is already in flight. Frieren's manager does `loadEmojiConfig().then(() => this.showEmoji(name))`; if the next line arrives first, the old expression appears after the cleanup. The managers therefore keep a monotonically increasing display generation:
  - Every dialogue transition (including a `null` emoji) and every `cleanup()` increments it.
  - Every async continuation (config load, image `onload`) captures the generation at start and renders only if it is still current.
  - The continuation also re-checks the active personality and the config's personality/base URL before rendering.
- **Character switch.** `mpuChange()` marks the cached `window.mpuEmojiConfig` stale (clear it, or bump a config generation) and cleans up the current emoji. The next `showEmoji()` then loads the new character's configuration with the new `cur_num`. The cached config records which `cur_num` it was loaded for.
- **In-flight config requests.** Today `loadEmojiConfig()` caches only a *completed* config. Every call made while the first request is pending, including the automatic one on `DOMContentLoaded`, starts another fetch. Any of those fetches can resolve last and overwrite `window.mpuEmojiConfig`. Change `loadEmojiConfig()` so that:
  - it keeps the pending Promise together with the `cur_num` and config generation it was started for;
  - a call for the same `cur_num` and generation returns that Promise instead of fetching again;
  - when a response arrives, it is written to `window.mpuEmojiConfig` only if its `cur_num` and generation are still current. A stale response resolves for its own caller but never overwrites the newer character's config. The display-generation check in `showEmoji()` alone is not enough, because a stale write poisons every later call.
  - a rejected request clears the pending slot so a later call can retry.

### 8.3 Display paths

Every frontend site that displays a line from the dialog store goes through helper (b). The inventory below was taken with `grep` for `store.msg[`, `msgArr[`, and `Math.random() * msgArr` in `js/*.js`. Re-run that search before implementing and before merge; a new site added later must also use helper (b).

| # | Site | Index | Notes |
|---|---|---|---|
| 1 | `ukagaka-dialog.js` `loadExternalDialog()` | first line | first message |
| 2 | `ukagaka-core.js` `mpu_nextmsg()` non-LLM branch | `msgNum` | OK and auto-talk; inside `showBuiltInMessage` |
| 3 | `ukagaka-core.js` `mpu_nextmsg_fallback()` | `msgNum` | client fallback after an LLM failure |
| 4 | `ukagaka-chat-mode.js` chat-exit | random | caller keeps its history push |
| 5–7 | `ukagaka-greeting.js` (three sites: rate-limit timeout and the failure fallbacks) | random | after `showMainDialog()` |
| 8–10 | `ukagaka-context.js` (three sites: rate-limit timeout and the failure fallbacks) | random | after `showMainDialog()` |

Codex's review listed three of sites 4–10; the actual count is seven.

Server-returned lines do not come from the store and use helper (a) directly:

- The LLM branch of `mpu_nextmsg()` when `/nextmsg` returns a server-side built-in fallback uses `res.emoji`. This display block is shared with LLM-success responses, so those responses also go through helper (a). The visible effect for LLM responses is that the previous expression is now cleared at the text swap; that is intended.

The helper must run at the actual text-swap point, not merely when the next-message request begins:

- **Normal non-LLM OK path**: call it inside `showBuiltInMessage`. This runs after `jQuery("#ukagaka_msgbox").promise()` resolves the 600 ms fade-out, immediately before or with the new typewriter call.
- Do not show the new expression while the old dialogue box is still fading out.
- **Wake animation**: when it delays text display, call the helper inside the animation's completion callback, in both the built-in and the LLM branches.
- **`mpu_nextmsg_fallback()`**: it has no fade or callback of its own. It types the line immediately and only then triggers the animation, so call the helper immediately before its `mpu_typewriter()` call.
- **First message**: call the helper at the point where `loadExternalDialog()` types it.
- **Sites 4–10**: replace the existing `mpu_typewriter(mpu_unescapeHTML(msgArr[i] + auto), …)` call in place. Do not move it earlier or later relative to `showMainDialog()`, message-block release, or auto-talk restart.

### 8.4 History

`window.mpuChatHistory` receives `msg + auto_msg`. Because `msg` from `/dialog`, and `res.msg` from `/nextmsg`, are already clean, the history contains no tags without extra frontend work. Treat this as something to verify, not to implement. This includes the chat-exit line (site 4), which writes the text returned by helper (b).

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

Known limitation, not addressed here: a personality-specific manager script loaded for the initial character stays installed after `mpuChange()` switches to another character. The generation and personality checks in §8.2 stop wrong-asset rendering, but positioning still follows the loaded manager's code. Swapping manager scripts at runtime is a separate task.

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
- Untagged next line: clear the previous expression at the text swap, even if its normal display timeout has not elapsed.
- Tagged line whose config is still loading, followed by another line: the earlier expression never appears (generation check).
- Empty dialogue after removing a valid tag: allow the existing empty-message safeguards to handle it; add a test so it cannot crash the typewriter.
- Character switched while configuration/image loading is pending: do not attach the previous personality's emoji to the new character (generation + personality check).
- Invalid or unknown `cur_num`: fall back to the current personality resolution; never error.
- Valid `cur_num` whose `dialog_filename` is not an installed personality: no expression, tags from the installed union still hidden (§4.1 row 3).
- Tag auto-detected from a `.gif` / `.apng` file only: `<tag>.png` is missing, so the missing-file rule applies (§6).
- Two `loadEmojiConfig()` requests for different characters resolving out of order: the older response never overwrites the newer config.

## 11. Tests

### PHP unit tests

Add focused coverage for the builder and producers:

- JSON message with a supported tag returns clean `msg` plus aligned `msg_emojis`.
- TXT line with a supported tag produces the same result as JSON.
- Message without a tag returns `null` emoji.
- Tag supported only by another installed personality is removed but returns `null` emoji.
- Tag unknown to every installed personality remains visible and returns `null` emoji.
- Tag matching is case-insensitive.
- Multiple messages preserve exact index alignment.
- A dynamic-code expansion that produces N messages produces N copies of the source emoji metadata.
- `:recentpost[5]:` and similar codes are not treated as tags.
- Post titles, commenter names, generated links, and HTML attributes containing `[tag]` text are not reparsed or modified after expansion.
- Two personalities with different supported lists cannot resolve each other's tags.
- Multiple globally recognized tags use the first active-personality-supported image and remove all recognized tags.
- Duplicate lines collapse first-wins; "same text, different tag" keeps the first tag's emoji.
- `msg` and `msg_emojis` are sequential lists even when duplicates were removed (JSON encodes as arrays).
- `msg_emojis` length matches the final `msg` length even when legacy `msgall` reflects the pre-expansion source count.
- Existing `/dialog` fields and string-array contract remain unchanged.
- `/dialog` and `mpu_get_msg_arr()` return identical `msg` / `msg_emojis` for the same file and personality.
- `/dialog` and `/emoji-config` resolve the personality from a valid `cur_num`, and ignore an invalid one.
- Resolver table (§4.1), one test per row:
  - missing/invalid `cur_num` → current personality;
  - valid `cur_num` whose `dialog_filename` is a personality → that personality;
  - valid `cur_num` whose `dialog_filename` is not a personality (for example `shared_dialog`) → no emoji, installed-union tags still stripped, and never the admin default's files.
- The lenient default of `mpu_resolve_personality_id()` is unchanged for its existing callers.
- Every `mpu_get_msg_arr()` error/empty return (recursion limit, missing file, invalid name, oversize, unreadable) has `msg_emojis` aligned with `msg`.
- A tag auto-detected only from `laugh.gif` yields `laugh.png` (PNG-only contract, §6).
- `/nextmsg` server fallback (LLM enabled, generation fails):
  - a tagged line returns clean `msg` and the matching `emoji`;
  - an untagged line returns `emoji: null` (no keyword guessing);
  - a tag known only to another personality is hidden.
- `/nextmsg` non-LLM branch follows the same rules.

Extend existing response-normalizer tests only when testing the shared extraction primitive. Put `/dialog` and `/nextmsg` contract tests near the REST dialog tests rather than treating static dialogue as an AI-response test.

### JavaScript smoke tests

Add `tools/node/test-dialog-emotion-smoke.js` covering the shared display helper and managers:

- Calls `showEmoji("laugh.png")` once for the selected message.
- Clears the previous expression before displaying a tagged or untagged next message.
- For `null`, clears the previous expression and does not call `showEmoji()`.
- Delegates lazy configuration loading to the active emoji manager rather than starting a duplicate load in the dialogue helper.
- The common manager lazy-loads configuration when `baseUrl` is empty.
- Tagged line → config pending → untagged line → config resolves: the old emoji never appears.
- Config pending → character switch → config resolves: the old personality's image never appears.
- Dialogue still displays when configuration loading rejects.
- First message, normal `mpu_nextmsg()`, client fallback, and `/nextmsg` server fallback select the emoji at the same message index.
- `mpuDisplayBuiltInMessage()` reads `msg[i]` and `msg_emojis[i]` together, tolerates a missing `msg_emojis` (older server), and returns the displayed text.
- Chat-exit, the three greeting fallbacks, and the three page-context fallbacks each display the tagged line's emoji and clean text (stub the random index).
- A source scan fails if any line in `js/*.js` (excluding `js/dist/`) indexes the dialog store directly (`store.msg[`, `dialogStore.msg[`, `msgArr[`), except for exactly one allowed occurrence, the `store.msg[index]` read inside the body of `mpuDisplayBuiltInMessage()`. Do not exempt all of `ukagaka-core.js`, which would let other direct reads in that file slip through. This means a new display site cannot bypass helper (b).
- Concurrent `loadEmojiConfig()` calls for the same `cur_num` issue one fetch.
- `loadEmojiConfig()` for character A, then for B, with A resolving last: `window.mpuEmojiConfig` stays B's.
- A rejected `loadEmojiConfig()` can be retried.
- Wake-animation callback does not display the emoji early.
- The normal OK path does not clear/show the next expression until the 600 ms message-box fade-out has completed.
- Clean text, not the source tag, is written to history.

### Playground E2E

Extend `tools/e2e/interaction-e2e.js` with a fresh Playground site with no AI settings:

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
11. Switch characters with `mpuChange()` and confirm the next tagged line requests the new character's emoji URL, not the admin default's.
12. Give a character a `dialog_filename` that is not a personality folder, filled with Frieren-tagged lines: the tags are hidden and no emoji URL is requested.
13. Exit chat mode with a tagged dialogue store and confirm the chat-exit line shows its expression.

## 12. Documentation

Update canonical documentation under `docs-en/`:

- Built-in dialogue authoring for JSON and TXT.
- One non-empty TXT line equals one dialogue entry.
- Tags are personality-scoped and come from the personality's resolved emoji list: the manifest `emoji.supported`, or the auto-detected `emojis/` filenames when the manifest has no `emoji` block. Recommend the explicit manifest list.
- Tags known to an installed personality are hidden as metadata, but only the active personality may display the matching image.
- Tags unknown to every installed personality remain visible.
- Recommended one-tag-per-entry rule.
- Duplicate lines collapse; the first occurrence's tag wins.
- Example manifest, directory layout, JSON, and TXT.
- Emoji images must be `.png` files (animated PNG allowed); `.gif` / `.apng` are not displayed by tags in this version.
- A character shows expressions only when its dialogue file name matches a personality folder under `ghost/`.
- List Frieren's actual supported tags or link to its manifest rather than documenting `[smile]` as available.

In `docs-en/API_REFERENCE.md`:

- add the optional `msg_emojis` field to `/dialog`;
- add the optional `cur_num` parameter to `/dialog` and `/emoji-config`;
- note that `/nextmsg` built-in lines no longer get keyword-guessed `emoji`.

Add a CHANGELOG entry for the behavior changes visible to users:

- the `/nextmsg` fallback no longer guesses emoji from keywords;
- the previous expression is cleared at the text swap;
- the dialog list is always returned as an array.

## 13. Build and verification

If JavaScript source changes, rebuild and commit both distribution bundles:

```powershell
npm --prefix tools/node run build
```

Wire the new smoke test into the tooling. In `tools/node/package.json`:

```json
"test:dialog-emotion": "cd ../.. && node tools/node/test-dialog-emotion-smoke.js"
```

Add `npm run test:dialog-emotion` to the `verify` chain next to the other smoke tests.

Minimum validation:

```powershell
php tools/php/vendor/bin/phpunit --configuration tests/phpunit.xml.dist
npm --prefix tools/node run test:dialog-emotion
npm --prefix tools/node run test:visual-ready
npm --prefix tools/node run test:frieren-shell
npm --prefix tools/node run test:interaction
npm --prefix tools/node run build
git diff --check
```

`test:interaction` (Playground E2E) is not part of `verify`; run it explicitly before merge.

Prefer the full repository verification before merge:

```powershell
npm --prefix tools/node run verify
```

Manual checks:

- Fresh install with no AI provider/API key.
- JSON tagged dialogue.
- TXT tagged dialogue.
- LLM enabled with a failing/unreachable provider: the server fallback line shows its tag's expression, and untagged fallback lines show none.
- Frieren expression position and scale unchanged.
- Generic personality expression uses only its own configuration and files.
- Switching characters with the dock menu uses the new character's tags and emoji URL.
- OK fade-out/fade-in timing remains correct.
- New expressions do not appear before the old dialogue finishes fading out.
- An untagged next line clears the previous expression immediately at the text swap.
- Auto-talk timer remains a single chain.

## 14. Suggested implementation order

1. Add backend tests for the resolver, the builder, the producers (including error returns), and the `/nextmsg` fallback.
2. Add the strict mode to `mpu_resolve_personality_id()`.
3. Add the installed-tag registry and the built-in message builder (with first-wins de-duplication).
4. Accept and validate `cur_num` in `/dialog` and `/emoji-config`; route `/dialog`, `/emoji-config`, and `mpu_get_msg_arr()` through the strict resolver.
5. Extend `/dialog` and `mpu_get_msg_arr()` with aligned `msg_emojis` on every return path while preserving `msg: string[]`.
6. Switch the `/nextmsg` built-in branches off the AI normalizer.
7. Add common-manager lazy loading, the in-flight `loadEmojiConfig()` Promise, and the generation token to both managers.
8. Add helpers (a) and (b); invalidate emoji config in `mpuChange()`.
9. Wire sites 1–10 and the LLM-branch display path; re-run the `grep` inventory.
10. Add JavaScript smoke coverage and wire `test:dialog-emotion` into `verify`.
11. Add JSON/TXT Playground E2E coverage.
12. Verify one generic personality in addition to Frieren.
13. Update canonical documentation and the changelog.
14. Rebuild bundles and run full verification.

## 15. Acceptance criteria

- [ ] Non-LLM JSON dialogue can explicitly trigger a supported personality emoji.
- [ ] Non-LLM TXT dialogue can explicitly trigger the same emoji using the same syntax.
- [ ] Supported tags are removed from visible dialogue and stored history.
- [ ] Tags known to an installed personality are removed from shared dialogue even when the active personality cannot render them.
- [ ] Tags unknown to every installed personality are not silently removed.
- [ ] First line, OK/manual next, auto-talk, client fallback, chat-exit, greeting fallbacks, page-context fallbacks, and `/nextmsg` server fallback all behave consistently through `mpuDisplayBuiltInMessage()`.
- [ ] A character whose dialogue file is not a personality never shows another personality's expression.
- [ ] `msg_emojis` is aligned with `msg` on every `/dialog` and `mpu_get_msg_arr()` return path, including errors.
- [ ] Out-of-order `loadEmojiConfig()` responses never overwrite the current character's config.
- [ ] Built-in lines never receive keyword-guessed expressions on any path.
- [ ] New expressions are triggered only after the preceding fade-out completes.
- [ ] An untagged next line clears any still-visible expression from the previous line.
- [ ] A pending emoji load from an earlier line or an earlier character never renders.
- [ ] Tags and asset paths follow the on-screen character, including after `mpuChange()`.
- [ ] Tags and asset paths are isolated to the active personality.
- [ ] A personality without emoji support has no behavior change.
- [ ] Frieren retains its current expression placement and timing.
- [ ] At least one non-Frieren fixture personality passes the same flow.
- [ ] Existing `/dialog` consumers remain compatible; `msg` is always a JSON array.
- [ ] `test:dialog-emotion` is part of `verify`; production bundles are rebuilt and verification passes.

## 16. Out of scope

- Automatic keyword-based emotion selection for built-in dialogue.
- Emotion aliases such as `smile -> laugh.png`.
- Adding or redrawing emoji artwork.
- Changing expression duration through dialogue syntax.
- Multiple simultaneously displayed emoji images.
- `<think>` blocks in static dialogue files.
- Changing the legacy `msgall` semantics.
- Tags in sleep-mode messages (`mpu_get_initial_message()` / personality sleep config) and in LLM output, which already has its own normalizer path.
- Swapping personality-specific emoji manager scripts at runtime after a character switch.
- A persisted per-character `personality_id` setting that decouples the dialogue file from the personality (§4.1). It affects the LLM prompt, sleep mode, and the Akismet/Turnstile integrations too, so it needs its own plan and migration.
- A `tag → actual filename` resolver for `.apng` / `.gif` emoji. It must be shared with the LLM emotion contract in `response-normalizer.php` (§6).
- A broad rewrite of Frieren animation, touch, decoration, or shell rendering.
