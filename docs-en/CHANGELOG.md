# MP Ukagaka Version History

> 📋 Update records for all versions

---

## [2.27.1] - 2026-06-19

### 🌐 i18n fix

- **Gift strings added to the catalog**: The gift/feeding feature shipped with new `__()` strings that were never added to the translation files — the four `/touch/give` error messages, the localized history anchor `（%sを差し出した）`, the picker label, and the carousel previous/next labels. Non-Japanese sites therefore showed Japanese, and the backend-owned "localized anchor" fell back to Japanese. All are now in `mp-ukagaka.pot` and the `.po` / `.mo` catalogs with Traditional Chinese and English translations, and the carousel navigation labels are passed through `wp_localize_script` so they are translatable instead of hardcoded.

---

## [2.27.0] - 2026-06-18

### 🎁 Gift / Feeding System

- **Give items to the character**: A new 🎁 picker by the chat input lets visitors hand the character gift or food items. Each item drives an LLM reaction — food is eaten with a taste comment, gifts are accepted with thanks, and `favorite` items get an extra-delighted reaction — rendered through the existing emotion-tag/APNG pipeline so expressions appear automatically.
- **Memory integration**: Reactions are recorded in the session observation buffer (new ghost-agnostic `item` type with `food:` / `gift:` kinds and per-item dedupe) and in chat history, so later conversation can refer back to what was given. The backend owns a localized synthetic history anchor and writes the checksum (`store_after_auto(..., 'give')`) to keep front/back history in parity.
- **Architecture**: New `POST /mp-ukagaka/v1/touch/give` on `MPU_REST_Touch` (independent `give_item` rate limit), a ghost-agnostic `ghost/<Character>/items.json` catalog read by a new `personality-items.php` loader (with image-filename whitelisting), and a shared `run_reaction()` helper extracted from the decoration/touch paths. The `give` type was registered across the chat-history allowlists, inner-monologue defaults, and conversation stats.
- **UI**: The picker is a single-item carousel (prev/next buttons, arrow keys, touch swipe, a position counter) with image-first thumbnails and a text fallback. The interaction lock now routes through the core `mpuSetMessageBlocking()` channel so a gift cannot be sent while chat/context holds the lock. Frieren ships with two items: メルクーアプリン (food) and 魔導書 (gift).

---

## [2.26.0] - 2026-06-16

### 😴 Daytime Nap (after-lunch sleep)

- **New nap window**: Characters can now take an after-lunch nap in addition to the existing nighttime sleep. The nap does not happen every day (probability-based, ~2–3 times a week) and its length varies (30–60 minutes) within a configurable window (default 12:30–13:30). Like nighttime deep sleep, a nap is temporary: refreshing the page keeps the character asleep, and the woken-IP record is not used.
- **Reuses the existing sleep machinery**: `mpu_is_deep_sleep_time()` now returns `true` during a nap, so every downstream behavior (reduced auto-talk frequency, dream lines, touch/wake reactions, weight adjustments, the `isDeepSleepTime` frontend flag) applies automatically. Two helpers were added in `llm-context-builder.php`: `mpu_get_daily_nap_window()` (rolls the day's nap once and caches it in a transient until midnight) and `mpu_is_nap_time()`.
- **Nap-specific flavor**: `sleep_mode.json` gains a `nap_dreams` pool and `wake_reaction_prompts.nap`. During a nap, dream lines are drawn from `nap_dreams` (daytime, post-meal tone) instead of the night pools, and the wake reaction uses the `nap` phase. The frontend wake fallback (`ukagaka-chat-wake.js`) also gains nap-specific lines for when the LLM reaction is empty or fails.
- **Per-character config**: Add a `nap` block under `sleep_settings` in a character's `manifest.json` (`enabled`, `window_start` / `window_end` in minutes-of-day, `probability`, `min_minutes`, `max_minutes`). Nap is **off by default**; partial nap configs now deep-merge onto the defaults, so a manifest only needs `nap.enabled: true` to inherit the rest. Frieren ships with nap enabled (12:30–13:30, p=0.4, 30–60 min).
- **Wake UX**: The temporary-wake message distinguishes a nap ("昼寝中…") from deep sleep ("深い眠り中…").

---

## [2.25.7] - 2026-06-11

### A-2 Frontend Split & Performance

- **Frontend boot script split**: Moved the pure-data boot globals and bootstrap logic out of `frontend-functions.php` into the enqueue flow, and split the Frieren-specific runtime into `frieren.js`, `frieren-animation.js`, `frieren-interactions.js`, and `frieren-decorations.js`.
- **Chat module split**: `ukagaka-chat.js` (1,301 lines) is now split into seven focused modules — history/state, mode controls, formatting, SSE client, send orchestration, event bindings, and wake helpers. `ukagaka-chat.js` remains as a zero-byte compatibility entry so the `mpu-chat` handle keeps working. The production bundle output is byte-identical to the pre-split build.
- **Frieren script bundle**: The build now produces `ghost/Frieren/dist/frieren-bundle.min.js` from the manifest script list (about 60 KB down to 28 KB, four HTTP requests down to one). When `SCRIPT_DEBUG` is enabled, the personality is not Frieren, or the bundle file is missing, loading falls back to the original per-file manifest enqueue, so third-party ghosts are unaffected.
- **SSE graceful degradation**: When the server lacks the php-curl extension, chat now falls back to the synchronous endpoint automatically instead of surfacing a streaming error to visitors.
- **Initial system bubble timing**: Extended the character-visibility wait for the initial system bubble from 6 to 12 seconds, so slow first-visit asset loads no longer show the bubble before the character appears.
- **Compatibility note**: Custom `extend.js_area` still runs after the MP Ukagaka bootstrap, but it is no longer a synchronous `<head>` inline script. Custom code that depended on `<head>`-time execution should wait for DOM ready or the MP Ukagaka init-complete event.
- **Docs consolidation**: English documentation under `docs-en/` is now the single canonical source; the Chinese and Japanese copies (`docs/`, `docs-jp/`) were removed.
- **Validation**: Chat SSE streaming, touch/decoration interactions, and first-visit flows were manually smoke-tested in a real browser, in both production bundle mode and `SCRIPT_DEBUG` per-file mode.

---

## [2.25.6] - 2026-06-10

### 🛡️ Security Hardening (A-tier fixes)

- **API Key Encryption upgraded to AES-256-GCM**: New API keys are stored with authenticated AES-256-GCM (`mpu_enc2:` prefix). Removed the reversible obfuscation fallback and the insecure site-URL-derived key path; encryption now fails closed (refuses to store) when AUTH_KEY or OpenSSL/GCM is unavailable. Legacy `mpu_enc:` / `mpu_obf:` / plaintext values still decrypt and re-encrypt to GCM on the next settings save.
- **LLM Prompt Debug Logging Guard**: Full system/user prompt and conversation dumps are no longer emitted by `WP_DEBUG` alone. They now require an explicit opt-in via the `MPU_DEBUG_LLM` constant or the `mpu_debug_llm_prompts` filter, preventing prompt assets and conversation PII from leaking into debug logs.

---

## [2.25.5] - 2026-06-10

### 🛡️ Security & Stability Enhancements (S-tier fixes)

- **Touch API Session Token Guard**: The `/touch/decoration` and `/touch/zone` endpoints now enforce the same session token validation as the Chat system, preventing unauthorized API calls.
- **API Cache Key Integrity**: Fixed `mpu_generate_cache_key()` to include `model`, `language`, and `max_tokens` in the hash computation, ensuring that configuration changes do not falsely hit stale cache entries.
- **Deep Merge for Options**: Fixed the shallow merge issue in `mpu_get_option()` for nested settings like `bot_blocker` and `extend`. New default values for nested arrays are now correctly preserved during updates.

---

## [2.25.4] - 2026-06-09

### Weather rain labels now account for 24-hour precipitation totals

- Added rain-label refinement for Open-Meteo tomorrow forecasts: `precipitation_sum` now upgrades continuous-rain WMO labels when 24-hour rainfall is materially higher than the base code suggests, so heavy daily accumulation is no longer described as drizzle.
- Current weather keeps the live WMO code instead of being upgraded by the daily total. The context still appends the day’s accumulated rainfall, giving the character enough signal about true rain intensity without falsely saying the current moment is a downpour.
- Adjusted extreme-rain labels to more natural Japanese wording and rewrote one Frieren bot-detection template to remove a contradictory metaphor.
- Updated the Gift/Feeding implementation plan with code-verified follow-up notes for the normalizer context file, first-interaction history handling, and observation dedupe wording.

---

## [2.25.3] - 2026-06-08

### `check-spam-event` responses now pass through the backend normalizer

- The `check-spam-event` REST endpoint (Turnstile, Akismet spam, Moelog Bot Blocker, bot alert, AI crawler, visitor pulse) returned raw LLM messages without normalization, so supported `[tag]` markers (e.g. `[smirk]`) leaked into the dialogue box once 2.25.2 removed the frontend strip. This event path was outside the four REST paths covered by 2.25.2.
- Added `mpu_finalize_spam_event_response()`; all six event branches now route through it. It normalizes the message (stripping/extracting emotion tags), stores the checksum from the **normalized** text (previously stored the raw text, which would diverge from the cleaned frontend history), and returns structured `emoji` / `emotion_tags` / `primary_emotion_*` like the other REST paths — keeping `display_text` / `history_text` / `checksum_text` aligned per §13.2.
- Added `SpamEventResponseTest` covering the AI-crawler event path (`[smirk]` stripped, `smirk.png` emoji, checksum computed from cleaned text).

---

## [2.25.2] - 2026-06-08

### Emotion tag display hardening moved to the backend normalizer

- Moved the 2.25.1 emotion-tag stripping from the frontend `mpu_typewriter()` boundary back into the backend response normalizer and reverted the frontend strip, so `display_text` / `history_text` / `checksum_text` stay aligned per §13.2 (frontend-only stripping touched display but not history, causing the three to drift).
- The backend normalizer now also handles common spelling variants: inner whitespace, trailing punctuation, and full-width brackets `【tag】` / `［tag］` (e.g. `[ thinking ]`, `【thinking】`, `［sigh。］`, `[thinking…]`). It remains gated by the supported list, unknown tags are preserved by default, and Markdown links stay protected by the existing `(?!\()` guard.
- Added `ResponseNormalizerTest` cases covering full-width / whitespace / trailing-punctuation stripping and Markdown-link protection (including links with inner whitespace).

---

## [2.25.1] - 2026-06-06

### Emotion tag display hardening (frontend)

- Frontend now strips supported `[tag]` emotion markers from visible text at the `mpu_typewriter()` boundary. Page-awareness, first-visit greeting, bot / event responses, and fallback dialogue no longer leak raw markers such as `[thinking]` into the dialogue box. APNG expression selection is unaffected because it is driven by the separate `res.emoji` field, not by parsing the display text. The strip merges the canonical tag list with `window.mpuSupportedEmojis` / `mpuEmojiConfig.mappings`, only removes recognized tags, and uses a `(?!\()` lookahead to avoid eating Markdown links.
- Removed literal tag examples from `ghost/Frieren/emoji-keywords.json` metadata so the model is no longer nudged to copy `[thinking]` / `[laugh]` / `[sigh]` strings verbatim into replies. The tag syntax guidance remains in `expression_tag_policy`.
- Updated `EmotionTagPromptTest` to assert the metadata examples contain no visible tags while the policy still documents the syntax.

---

## [2.25.0] - 2026-05-31

### Emotion tag response pipeline

- Added the Response Normalizer contract for AI responses. Display text, history text, checksum text, and TTS text now share the same cleaned output, while leading `<think>` / `<thinking>` blocks and emotion tags are parsed into structured fields.
- Added inline `[tag]` emotion prompt instructions for supported character expressions. Frieren now declares the supported emoji tag list in `manifest.json`, replacing the older trailing `[表情:xxx]` prompt style while keeping backward-compatible parsing.
- Connected non-streaming REST routes and SSE streaming output to the normalized emotion pipeline. Explicit streamed emotion tags now take priority and are no longer overwritten by keyword-based emoji guessing at stream finalization.
- Added stream parser coverage for split tags, split think blocks, Markdown link false positives, context-disabled paths, and malformed / unclosed think blocks.

### Think bubble and system placeholders

- Added the shared `#ukagaka_think` bubble used by both system placeholders and future LLM think output. The legacy loading placeholders such as `えっと` and the initial `何を話せばいいかな` now render outside the main dialogue box.
- Refined Frieren touch / decoration / initial loading behavior so placeholder bubbles do not enter chat history, do not leave stale placeholder attributes, and do not flash an empty main dialogue box before the first real line.
- Tuned the think bubble appearance and placement: right-anchored growth, adjusted tail direction / position, dashed border, and opacity aligned with the main dialogue visual style.

### Notes

- Ollama `message.thinking` integration was implemented and then reverted after testing. Ollama shares the `num_predict` budget between reasoning and final content, which caused empty or truncated replies and chat history/checksum issues. The think bubble itself ships and is actively used for the loading / placeholder UI (the initial `何を話せばいいかな`, touch / decoration thinking states). The separate LLM `<think>` inner-monologue channel that would feed character thoughts into that bubble has been shelved after testing — its code remains in the tree but no provider feeds it and no prompt asks for it, so it has no visible effect. We no longer maintain it, but the full pipeline (normalizer, SSE parser, bubble) ships intact: `DEVELOPER_GUIDE.md` → "Inner Monologue (`<think>`) Channel" documents the opt-in wiring points and pitfalls for developers experimenting with providers that can produce good short monologue.
- Added as-built implementation notes to `plan/Emotion_Tag_And_Think_Block_Plan.md` for the final commit mapping, deviations, verification baseline, and open follow-up decisions.

---

## [2.24.1] - 2026-05-29

### 🐛 Bug Fixes

#### Decoration name resolution in observation context

- When a visitor touched a decoration and later asked what they did, the character only said "touched a decoration" instead of naming the actual item (e.g. the staff, the suitcase). The observation buffer stored only the decoration `type` slug, and the prompt builder echoed the raw slug.
- Added a `name` field to each entry in `ghost/Frieren/decorations.json` (e.g. 魔法杖, 魔導書, スーツケース).
- `MPU_Observation_Buffer` now resolves the decoration type → display name at drain time via `mpu_load_personality_decorations()` (name-first lookup). Resolution happens at drain because the push-side normalize regex only allows `[a-z0-9_]+`, so localized names cannot be stored in the buffer. Fallback is ghost-agnostic (`str_replace _ → space`); no per-character type map is baked into the generic class.

### 🛠️ Code quality tooling

- Added PHPCS baseline (`tools/php/phpcs-baseline.json`) snapshotting all WordPress Coding Standards violations at branch-out time, so new code is held to current standards while legacy violations remain queryable.
- Wired `lint:phpcs` into `npm run verify` for pre-commit checks.
- Aligned PHPCS `testVersion` with the declared PHP 7.4 minimum to avoid spurious diagnostics on language features unrelated to the actual support floor.

### 📐 Repository hygiene

- Adopted `.gitattributes` line-ending policy: LF for all source files, with BOM preserved for tracked `.po` / `.pot` catalogs.
- Normalized repository-wide line endings.
- Refreshed PHPCS baseline after EOL normalization (1336 → 230 violation entries; the remainder is genuine technical debt rather than EOL-induced false positives).

### 🌐 Translation refinements

- Added English (en_US) translations for console logs and UI strings that were previously falling back to Japanese source.
- Refined Japanese console log wording around idle / timer / ghost lifecycle states.
- Fixed Japanese wording typo in the v2.22.1 changelog entry.
- Aligned Frieren decoration dialogue fallback wording with the PHP canonical source.
- **Fixed missing 「を」 particle** in `（…えっと…何を話せばいいかな…）` placeholder: `zh_TW.po` msgstr was a typo of `（…えっと…何話せばいいかな…）`, and the JavaScript `systemMessages` blacklist in `js/ukagaka-base.js` had been copied from the typo'd Chinese-locale rendering. The mismatch silently broke the character-animation-skip logic on every non-zh_TW locale (animation kept playing during placeholder display, where it should have been suppressed).
- Recompiled all `.mo` files.

### 📚 Documentation

- Refreshed `DEVELOPER_GUIDE` directory tree and module load order across en / jp / zh-TW.
- Restored expandable visitor-info debug log section in `API_REFERENCE` and `DEBUG_SLIMSTAT` docs.
- Added reality-checked plan status snapshot (2026-05-27).

---

## [2.24.0] - 2026-05-26

### 🌐 Frontend console log i18n — complete

- All frontend console log strings have been migrated from hard-coded Traditional Chinese to Japanese source strings, displayed through WordPress locale translations. No hard-coded CJK console logs remain in `js/` or `ghost/`.
- The migration covers every console log: the production-visible logs (`js/ukagaka-anime.js`, `ghost/Frieren/frieren.js`, and the core-bundle finishers in `js/ukagaka-features.js` / `js/ukagaka-base.js`) plus the 195 debug-gated logs across the core bundle, Frieren, dialog, greeting, and emoji modules.
- Logs now go through `mpuLogger` helpers (`logL` / `logF` / `warnL` / `warnF`, plus the always-output `errorL` / `errorF` / `warnAlways` / `warnAlwaysF`), each keyed by a stable semantic key. Strings are delivered in two buckets: `mpuL10n.logs` (always injected) and `mpuL10n.logsDebug` (injected only in front-end debug mode).
- Output timing and gating are unchanged: error/warn output behaves as before, and debug-only logs still appear only when `WP_DEBUG` is enabled for an administrator.

### 🗂️ Translation catalogs

- Regenerated `mp-ukagaka.pot` and merged the `ja`, `zh_TW`, and `en_US` catalogs. Japanese and Traditional Chinese translations are complete for all 206 unique log message IDs (212 registrations, six of which share identical Japanese source text). English (`en_US`) is merged and compiled but not yet translated, so English-locale sites fall back to the Japanese source string for these logs.

## [2.23.2] - 2026-05-24

### 🐛 Bug Fixes

#### Observation Buffer — SPA navigation tracking

- Observation tracking (`page_view` / `stay_duration`) now also starts after client-side (SPA) navigation into a single post, not only when a single post is the initial page load.
- The post ID is re-detected from the DOM (`postid-` / `page-id-` body class, `data-post-id`, `article[id^="post-"]`), with the PHP-injected `mpuPageContext.postId` taking priority when present.
- Listing, archive, and home pages are guarded against false starts, and the stale post ID is cleared on each SPA navigation before re-detection.

#### Page-context self-talk — auto-talk resume

- Removed the fragile `!mpuAutoTalkTimer` guard in the page-awareness self-talk display callback. Under production API latency a stale timer reference could leave auto-talk permanently stalled (not reproducible locally).
- `startAutoTalk()` already stops any existing timer first, so resume is now unconditional whenever auto-talk is enabled — no double timer, original cadence preserved.

---

## [2.23.1] - 2026-05-24

### 🌐 Language Setting Unification and Fallback Fix

- Fixed ghost language setting conflicts. The language resolution priority is now: Backend explicit setting > Ghost's `manifest.json` > Final fallback Japanese (`ja`).
- Added a "Default" option to the language dropdowns in General Settings and AI Settings, allowing admins to yield the language choice to the ghost's `manifest.json`.
- Removed unused Simplified Chinese and Korean mappings from the language list.

---

## [2.23.0] - 2026-05-23

### 🧠 Observation Buffer

- Observation data is stored in WordPress transients. The scope key is derived from a hashed session token, so the raw token is never stored in the key.
- Each session keeps at most 5 observations, with each entry capped at 200 bytes. The default TTL is 1 hour and can be adjusted via the `mpu_observation_buffer_ttl` filter, clamped between 5 minutes and 2 hours.
- The buffer is drained when `/chat/user` builds the system prompt, so the same observation batch is injected at most once.

#### 🔌 REST API and Frontend Tracking

- Added `POST /mp-ukagaka/v1/observation/push`, currently accepting the frontend observation types `page_view` and `stay_duration`.
- The endpoint requires a valid session token and applies a `20 requests / 60 seconds` rate limit.
- Added frontend helpers `mpuObservationPush()` and `mpuInitObservationTracking()` to record post page views and dwell-time checkpoints at 10 / 30 / 60 / 180 / 600 seconds.
- If a stale session token returns 403, the frontend refreshes the token and retries once to reduce missed observations on long-lived or SPA pages.

#### 👻 Interaction Event Integration

- Touching the character or decorations now accumulates `touch` observations, deduping by part and merging repeated touches into a count.
- Wake events, wake-from-sleep events, and page-context triggers are pushed as lifecycle observations so the next chat can naturally reference what just happened.
- `/chat/user` formats observations as a "recent visitor activity" block and explicitly treats them as context, not instructions, so the LLM may reference them naturally without being forced by them.

#### 🛡️ Safety and Boundaries

- Observation content is checked against an allowlist, validated by type-specific formats, stripped of HTML, and length-capped. Private or password-protected posts are represented as `[non-public]` instead of exposing titles.
- The buffer is short-lived transient context only. It does not write User Memory and does not create a persistent visitor profile.
- `bot_signal` is reserved for a later phase 2 and is currently rejected.

---

## [2.22.1] - 2026-05-22

### 👻 Ghost Sleep-Wake Grumble Mechanism (v2.22.1)

When a visitor wakes the character via `/wake-ghost` during `deep_sleep` or `oversleep`, the backend now uses the LLM to generate a short, in-character Japanese grumble. The frontend typewriters this grumble to the user instead of showing the default welcome message.

### ⚙️ Backend REST API & LLM Generation (`includes/rest/class-mpu-rest-dialog.php`)

- Adds `get_wake_sleep_phase()` to classify the active sleep phase as `deep_sleep` or `oversleep` before marking the IP as woken.
- Adds `generate_wake_reaction()` to dynamically pick the corresponding prompt, call `mpu_call_ai_api` with `max_tokens=120`, and filter `<think>` tags and HTML tags. Returns an empty string `''` on failure for a safe fallback while logging details.
- Returns `sleep_phase` and `wake_reaction` fields in the wake-ghost API response.
- Protects the Ollama path with a `busy-lock` to prevent concurrent local LLM execution.

### 📐 Personality Config & Prompts (`ghost/Frieren/sleep_mode.json` & `includes/personality/personality-prompts.php`)

- Adds `wake_reaction_prompts` in `sleep_mode.json`, defining three prompt variants each for `deep_sleep` and `oversleep`.
- Adds `mpu_pick_wake_reaction_prompt()` in `personality-prompts.php` to securely filter and randomly extract the prompt.

### 🔌 Frontend Integration (`js/ukagaka-chat.js`)

- Updates `mpu_send_wake_up_request()` to return a Promise and guards against duplicate concurrent clicks via `window.mpuWakeRequestPromise`, extending the timeout to 60s for LLM processing.
- Adds `mpu_display_wake_reaction()` to typewriter the grumble and push a `{ type: 'wake_reaction' }` entry into `window.mpuChatHistory` to preserve conversational context.
- Integrates `mpu_toggleChatMode()` and the OK-button handler to await the wake animation before firing the request, safely falling back to the default welcome message if no grumble is generated.

### 📦 Files Changed & Build

- Updates the main plugin entry `mp-ukagaka.php` and its version constant `MPU_VERSION` to `2.22.1`.
- Rebuilds frontend assets (`js/dist/ukagaka-bundle.js` and `js/dist/ukagaka-bundle.min.js`).

---

## [2.22.0] - 2026-05-22

### 👻 Ghost Runtime State Helper (v2.22.0 #7 milestone)

A new transient-backed helper records a single short-lived ghost runtime state per session, providing the backend foundation for future runtime UI / observation features. Lands per `plan/Engineering_Quality_Improvement_Plan.md` §v2.22.0 #7 hard limits — no LLM prompt injection, no Observation Buffer, no user/visitor memory writes.

### 📐 Public API (`includes/core/runtime-state-functions.php`)

State whitelist (9): `idle` / `thinking` / `speaking` / `chatting` / `sleeping` / `waking` / `tool_running` / `suspended` / `error`. Payload shape: `['state' => string, 'ts' => int]`.

| Function | Purpose |
|---|---|
| `mpu_runtime_state_allowed_states(): array` | Return the whitelist |
| `mpu_runtime_state_scope_key(?$session_token): ?string` | Resolve a transient key; raw token is `sha256`-hashed, never stored verbatim |
| `mpu_set_runtime_state(string $state, ?$session_token): bool` | Write state; invalid state or unresolvable scope → `false` |
| `mpu_get_runtime_state(?$session_token): ?array` | Read state; returns `null` if missing, malformed, or state no longer in whitelist |
| `mpu_clear_runtime_state(?$session_token): void` | Delete transient |
| `mpu_runtime_state_ttl(): int` | TTL in seconds, default 300, filterable via `mpu_runtime_state_ttl`, clamped to `[60, 900]` |

### 🔌 REST Wiring (`MPU_REST_Base` + chat/dialog controllers)

- **`/chat/user`** — `thinking` before LLM call → `speaking` on success → `idle` in `finally`; error branch → `error` then `idle`. `register_shutdown_function` writes `idle` as a fallback if the request dies before `finally`.
- **`/chat/user-stream`** — `thinking` at SSE start; the stream callback flips to `tool_running` on `status` events with `type=executing_tool`, and to `speaking` on `delta` events. `exit_if_stream_aborted()` writes `idle` before `exit` so client-side aborts release state. `done` → `idle`; error mid-stream → `error` then `idle`. Same shutdown fallback.
- **`wake_ghost`** — Sets `waking` immediately after token resolution; a `try/finally` block guarantees the eventual `idle` regardless of which early `WP_Error` branch is taken.

A protected `MPU_REST_Base::runtime_session_token(WP_REST_Request)` resolves and validates the `X-MPU-Session-Token` header / `session_token` parameter via `mpu_validate_session_token()` before any state write — there is no second source of truth.

### 🛡️ Plan §v2.22.0 #7 Hard Limits — Compliance Check

| Limit | Status |
|---|---|
| No `session_start()` / `session_id()` | ✅ 0 hits (word-boundary regrep) |
| No IP / referrer / fingerprint as scope | ✅ Token-only (+ optional logged-in `user_id` fallback, see below) |
| No `mpu_opt` / `update_option(...runtime...)` writes | ✅ 0 hits |
| No REST response shape changes | ✅ Wiring is write-only; no payload field added |
| TTL clamped, default 5 min | ✅ `[60, 900]` clamp |
| State whitelist enforced | ✅ Unknown values → `false` on write, `null` on read |
| Clear on error/abort/done | ✅ `finally` + SSE abort + shutdown fallback |

### 🟡 One Documented Deviation from Plan

`mpu_runtime_state_scope_key()` adds a **logged-in user fallback**: when the caller passes no session token (or one that fails validation) and `is_user_logged_in()` is true, the helper falls back to a `mpu_runtime_state_user_{user_id}` transient key. The plan permits only token-based scoping, but the user-id branch:

- Does not use IP / referrer / fingerprint (plan §scope rule 4 stays satisfied)
- Uses a distinct key prefix from token-hashed keys (no collision)
- Enables admin-only flows like `wp-admin` direct access that never go through the front-end session-token bootstrap

If this fallback proves unnecessary in practice, it can be removed without changing the public signature.

### 🧪 Tests (`tests/Unit/RuntimeStateTest.php`)

Eight cases covering: valid write/read round-trip; invalid state rejected; invalid token does not create a scope key; anonymous request without token has no scope; scope key hashes the token without leaking the raw value; `clear` deletes the transient; TTL filter clamps both upper (`999999 → 900`) and lower (`1 → 60`) bounds; logged-in user fallback works without a session token. `tests/bootstrap.php` gains `wp_salt()` and `get_current_user_id()` mocks to support the new fixtures.

### ✅ Verification

- `npm --prefix tools/node run lint:php`: all PHP files clean.
- `npm --prefix tools/node run test:php`: **35 tests / 76 assertions, all green** (baseline before v2.22.0 was 27/59; this milestone adds +8 tests / +17 assertions).
- Red-line greps (`session_start(` / `session_id(` word-boundary, `update_option(...runtime` / `mpu_opt['runtime_state']`, `mpu_get_session_key`): all 0 hits in source.

### 📦 Commit Layout

Single feature commit `feat(v2.22.0): ghost runtime state helper (#7)` + this CHANGELOG commit. No build artifact changes (PHP-only milestone).

### 📋 Milestone Notes

Closes v2.22.0 in the freeze table. Next up: **#10 Observation Buffer MVP** remains in design phase (`plan/Observation_Buffer_Design.md`); implementation gated on User Memory v2.

---

## [2.21.1] - 2026-05-22

### 🐛 Sleep wake-up flow fix for Frieren

When clicking the "Open Chat Window" button to wake a sleeping Frieren, the intended behavior was "open eyes + open chat window". The actual behavior was "open eyes + book-flip animation" with the chat window never opening.

### 🔍 Root Cause Analysis

Two coexisting problem chains:

**Wake-up race condition**

- In the wake-up branch, `mpu_toggleChatMode` first calls `mpu_send_wake_up_request()` (backend AJAX), then `$msgbox.fadeOut(1000, ...)`, and finally invokes `triggerCharacterAnimation` inside the callback.
- During the 1-second `fadeOut` delay, the backend wake response can flip `isSleepMessage()` (clearing `data-initial-msg` or resetting `mpuInfo.isTemporaryWakeUp`).
- Result: `wakeUp()` returns false → wake-up animation branch is skipped → control falls into the book-flip path inside `triggerFrierenSpeaking`, and `onWakeUpComplete` is never called — the chat window stays closed forever.

**Stale flag in the OK-button path**

- After wake-up via the OK button, `mpuSkipNextManualBookFlip = true` is set, expecting `mpu_nextmsg` to consume it on the next manual interaction.
- But in chat mode `handleOkAction` calls `mpu_sendUserMessage()` (SSE stream) and never touches `mpu_nextmsg`, so the flag is never consumed.
- The next manual interaction incorrectly skips the book-flip animation.

### 🛠️ Fixes

| Commit | Content |
|---|---|
| `2433729` | (1) Introduces `window.mpuForceWakeUpNextTime` flag, set before `fadeOut` to lock in "must wake this time" so backend responses cannot override it; (2) Adds a `skipBookFlip` early-return path in `triggerFrierenSpeaking` so a stale `sleepModeAwoken=true` no longer triggers a phantom book-flip, while still calling `onWakeUpComplete` to open the chat window; (3) Reroutes the OK-button path to await the wake-up animation before calling `handleOkAction`, eliminating concurrent-animation conflicts when LLM dialogue is triggered. |
| `6ceb36f` | (1) `wakeUp()` now unconditionally clears `mpuForceWakeUpNextTime` at entry, preventing flag leaks when `sleepModeAwoken` is already true; (2) `mpuSkipNextManualBookFlip` gets a `Date.now()` token + 8-second timeout fallback; (3) The consumer clears the token to `null` on normal consumption, so a fired timeout becomes a no-op — closing the "old timeout clears the new flag" regression window. |

### 📦 Files Changed

- `ghost/Frieren/frieren.js` — `wakeUp()` / `triggerFrierenSpeaking()`
- `js/ukagaka-chat.js` — `mpu_toggleChatMode` / OK-button handler
- `js/ukagaka-core.js` — two manual-animation trigger points in `mpu_nextmsg`
- `js/dist/ukagaka-bundle.js` / `ukagaka-bundle.min.js` — rebuilt via `tools/node/build.js`

### ✅ Verification

Live-check the three scenarios; console output should match (log strings are hard-coded in Traditional Chinese and do not localize with WordPress locale — a known UX gap, tracked for future improvement):

- Click "Open Chat Window" while Frieren is sleeping: `☀️ 芙莉蓮被喚醒了！(forceWakeUp)` + `📖 喚醒後跳過翻書動畫` + chat window opens
- Click OK button while Frieren is sleeping: LLM reply is generated only after the wake-up animation finishes, with no book-flip
- Interact normally while Frieren is already awake: book-flip plays as expected, not accidentally skipped

---

## [2.21.0] - 2026-05-20

### 🏗️ JS Global State Encapsulation (v2.21.0 #8 milestone)

Frontend runtime state previously scattered across 19 file-level `let` declarations and 9 `window.*` globals is now collected into a structured `window.MPU_STATE` namespace, accessed via 31 setter/getter helper functions (plus `mpuState` const alias for 32 entries total). No algorithm changes, no REST payload changes, no UI behavior changes — pure structural refactor of how mutable state is owned and accessed.

**Migration tiers**:

| Tier | Variables | Strategy |
|---|---|---|
| Fully migrated (no `window.*` left) | `__mpu_retry_count` / `__mpu_fallback_retry_count` / `mpuContextPending` / `mpuSettingsProcessed` / `mpuSettingsLoaded` / `mpuEnableChatMode` / `debugMode` | Reads/writes go to MPU_STATE only |
| Compat bridge retained | `mpuMsgList` / `mpuBaseAutoTalkInterval` | Helper dual-writes `window.*` + MPU_STATE; getter reads `window.*` canonical |
| Dual-write helper | 17 primitives (autoTalk / typewriter / AI / LLM / dialog / greet state) | Helper updates both old `let` and MPU_STATE; readers can use either |
| Same-object reference | `mpuLLMResponseHistory` / `mpuOllamaRequestQueue` / `__mpuStorage` | Array/object shared — mutations auto-sync |

**Plan §2.3 「不搬清單」preserved** (untouched by design): `window.mpuChatHistory`, `window.mpuChatModeActive`, `window.mpuChatSessionId`, `window.mpuChatRequesting`, `mpuChatAbortController` (chat shared state); `mpuInfo`, `mpuSettings`, `mpuPreSettings`, `mpuRestUrl`, `mpuRestNonce`, `mpuL10n`, `mpuInitData`, `mpuInitParams`, `mpuPersonalityId` (PHP localized contract); `mpuCanvasManager`, `mpuEmojiManager`, `mpuFrierenManager`, `mpuEmojiConfig` (manager objects); `MPU_EVENTS`, `mpuSpaEvents` (event surface).

### ✨ Behavior Change (Intentional)

- **`window.mpuDebugMode = true` in browser console now takes effect immediately.** Previously, `let debugMode` captured the value once at script load and never re-read the window flag. New `mpuIsDebugMode()` helper checks both `MPU_STATE.flags.debugMode` and `window.mpuDebugMode` on every call, so toggling at runtime via console now toggles logging immediately.

### 📐 Helper Inventory (`js/ukagaka-base.js`)

31 helper functions (+1 `mpuState` const alias = 32 entries total):

- **State access**: `mpuGetState`, `mpuState` (const alias)
- **Debug**: `mpuIsDebugMode`
- **AutoTalk**: `mpuSetAutoTalkTimer`, `mpuSetAutoTalkEnabled`, `mpuSetAutoTalkInterval`, `mpuSetBaseAutoTalkInterval`, `mpuGetBaseAutoTalkInterval`
- **Typewriter**: `mpuSetTypewriterTimer`
- **LLM/AI**: `mpuSetAiTextColor`, `mpuSetAiDisplayDuration`, `mpuSetAiDisplayTimer`, `mpuSetAiContextInProgress`, `mpuSetMessageBlocking`, `mpuSetOllamaReplaceDialogue`, `mpuSetLastLLMResponse`, `mpuResetLLMResponseHistory`, `mpuSetOllamaRequesting`, `mpuSetLastUserActionTime`
- **Dialog**: `mpuSetDialogStore`, `mpuGetDialogStore`, `mpuSetDialogNextMode`, `mpuSetDialogDefaultMsg`
- **Flags**: `mpuSetGreetInProgress`, `mpuSetContextPending`, `mpuIsContextPending`, `mpuSetSettingsProcessed`, `mpuIsSettingsProcessed`, `mpuSetSettingsLoaded`, `mpuIsSettingsLoaded`, `mpuSetEnableChatMode`, `mpuIsChatModeEnabled`

### 🔁 Deviation from Plan §2.3 #6 (Better than Literal)

Plan suggested "local short aliases + segmented replacement" to avoid scope errors during refactor. Implementation used **setter helper functions** instead — single point of dual-write logic, grep-friendly, immune to scope errors. Same intent (avoid search-replace bugs), cleaner execution.

### 📦 Commit Layout

Two commits land this milestone:

1. `refactor(js): encapsulate runtime state into window.MPU_STATE` — all 7 source file changes
2. `chore(build): rebuild dist bundle for v2.21.0 #8 MPU_STATE migration` — `tools/node/build.js` output only

Plan §2.3 originally specified 7 step commits (Inventory / Namespace / Base+core / Dialog+context+greeting / Features / Chat boundary / Bundle). The 6 source steps were authored without intermediate commits and collapsed to a single source commit at landing time. Trade-off: bisect can isolate "v2.21.0 vs pre-v2.21.0" but not within the milestone. Mitigated by clean file boundaries per step (base/core/dialog/context/greeting/features/chat each owns its scope).

### ✅ Verification

- `npm run verify`: lint + bundle + PHPUnit (27 tests / 59 assertions) all green on both commits.
- Source grep for fully-migrated globals returns 0 hits.
- `window.mpuMsgList` / `window.mpuBaseAutoTalkInterval` source references only inside base.js helper internals (compat bridge writes + defensive fallback).
- `git diff --check` clean.

### ⚠️ Known Limitations

- **Manual smoke test required for release.** PHPUnit covers backend only; JS runtime regressions are not detected. Plan §2.3 #6 path: auto-talk / chat / context / SSE / typewriter / wake_ghost / first-visit greeting / SPA navigation / sleep-mode interval.
- **`mpuGetDialogStore()` defensive fallback is effectively dead code under normal operation.** `typeof window.mpuMsgList !== "undefined"` is always true after base.js init (`typeof null === "object"`), so `MPU_STATE.dialog.msgList` fallback never executes. Retained as a defensive net against external scripts setting `window.mpuMsgList = undefined`; inline comment explains intent.

### 📋 Milestone Notes

Closes v2.21.0 in the freeze table. Next up: **v2.22.0 = #7 runtime_state helper** (Avatar §4) or **#9 CSS theme / i18n hot swap** (Avatar §7 + §8), depending on implementation complexity.

---

## [2.20.0] - 2026-05-20

### 🔧 utility-functions.php Domain Split (v2.20.0 #6 milestone)

Pure file moves — no logic changes. The ~1,143-line catch-all is now five domain files; original `utility-functions.php` retains constants only (36 lines).

| New file | Lines | Functions |
|---|---|---|
| `core/template-functions.php` | 142 | `array2str` / `str2array` / `output_filter` / `js_filter` / `render_prompt_template` / `build_user_info_prompt` |
| `core/file-functions.php` | 174 | `is_path_within_allowed_dir` / `secure_file_read` / `secure_file_write` / `get_dialogs_dir` / `ensure_dialogs_dir` |
| `core/encryption-functions.php` | 190 | `get_encryption_key` / `encrypt_api_key` / `decrypt_api_key` / `is_api_key_encrypted` / `get_provider_api_key` / `get_current_provider` |
| `core/wp-info-functions.php` | 284 | `get_wordpress_info` / `get_current_user_info` / `country_code_to_name` / `resolve_personality_id` |
| `core/network-functions.php` | 389 | `get_client_ip` / `get_client_ip_strict` / `fetch_external_api` / `clear_api_cache` / `check_rate_limit` / `rest_check_rate_limit` / `generate_session_token` / `validate_session_token` |

- **Load order in `mp-ukagaka.php`**: `debug → core → utility (constants) → template → file → encryption → wp-info → network → ...` — encryption loads before wp-info (provider key resolver depends on encryption), network loads last as the heaviest consumer of other utilities.
- **tests/bootstrap.php**: same load order replicated for PHPUnit.
- **Stray-function placement** (per plan #5 hand-write decision): `array2str` / `str2array` / `output_filter` / `js_filter` → `template`; `fetch_external_api` / `clear_api_cache` → `network`; `resolve_personality_id` → `wp-info`.

### 🧹 Redundant Defense Cleanup

Load order in `mp-ukagaka.php` guarantees these helpers exist before any caller; the guards were noise:

- `chat/class-mpu-chat-history-service.php`: 3 `function_exists` guards on `mpu_chat_integrity_normalize_session_id` / `verify_history` / `store_history` removed.
- `integrations/akismet-integration.php`: 4 guards on `mpu_chat_integrity_*` and `mpu_rest_check_rate_limit` removed.
- `llm/class-mpu-chat-lock.php`: `normalize_session_id()` inline fallback removed — chat-integrity version is byte-equivalent (`is_scalar` → `sanitize_key` → `substr 64`).
- `rest/class-mpu-rest-base.php`: `rate_limit()` guard removed.
- `rest/class-mpu-rest-dialog.php`: 2 guards on `mpu_chat_integrity_*` removed.

### 🪦 Dead Sleep Helper Removal (v2.19.2 follow-up)

v2.19.2 marked the hour-precision sleep helpers as dead code (only `wake_ghost` fallback referenced them). Removed in this release:

- `llm/llm-context-builder.php`: `mpu_get_daily_deep_sleep_start()` and `mpu_get_daily_oversleep_end()` deleted. `_mod` variants remain.
- `rest/class-mpu-rest-dialog.php`: `wake_ghost()` 3-layer `function_exists` fallback collapsed to direct `_mod` calls.

### ✅ Verification

- `npm run verify`: lint + bundle + PHPUnit (27 tests / 59 assertions) all green.
- `grep -h "^function mpu_" includes/core/*.php | sort | uniq -d` empty — no duplicate definitions.
- Code-path grep for old hour helpers returns only doc / changelog / plan history references.

### 📋 Milestone Notes

This release closes v2.20.0 in the freeze table (which had already shifted forward one slot after v2.19.2 was patched out of the original v2.20.0 plan). Next up: **v2.21.0 = #8 JS global state encapsulation** — larger surface, manual smoke test required, not bundled with backend work.

---

## [2.19.2] - 2026-05-20

### ✨ Sleep System Minute Precision

Extends v2.19.1 dynamic hour-roll to minute precision. Pure backend cache-key migration — manifest schema unchanged (still authored in hours).

- **New `_mod` suffix functions**: `mpu_get_daily_deep_sleep_start_mod()` / `mpu_get_daily_oversleep_end_mod()` return minutes-of-day (0–1439). For `[22, 23]` deep_sleep_start, the daily roll now picks any minute in `random_int(22*60, 23*60+59) = random_int(1320, 1439)` — i.e. anywhere between 22:00 and 23:59 inclusive — rather than only "22 or 23 on the hour".
- **All comparisons upgraded to minutes-of-day**: `mpu_is_deep_sleep_time()` cross-midnight handling rewritten in minute units (`$start_mod > $end_mod` OR-branch); `mpu_is_ip_woken_today()` / `mpu_mark_ip_as_woken()` window boundaries also moved to minute units.
- **Cache keys take `_mod` suffix**: forces a cache miss against old hour values stored under the v2.19.1 key prefix. No backward-compat read of the old keys.
- **Frontend caller upgraded**: `includes/core/frontend-functions.php` previously called the hour-based helpers; switched to `_mod` to keep the rendered chat-block boundary in sync with backend logic.
- **`wake_ghost()` updated**: now reads `_mod` helpers with the same `function_exists` fallback safety net.
- **Semantic shift on `oversleep_max_hour`**: a value of `9` now means "wake by 09:59" (previously "wake by 09:00 sharp"), consistent with minute-precision intent. IP-record window expanded by 59 minutes to match.

### ⚠️ Known Limitations

- **Old hour-based helpers are now dead code**: `mpu_get_daily_deep_sleep_start()` / `mpu_get_daily_oversleep_end()` only remain as a `wake_ghost` fallback reference. Cleanup deferred to **v2.20.0** (`#6` utility-functions split), since file moves + dead-code removal share the same precondition.
- **Sleep settings input clamping deferred**: `_mod` functions only handle positive cross-day overflow via `% 1440`. Negative integers in manifest would break minutes-of-day comparisons (PHP `%` preserves dividend sign). Not triggered today because manifest is hand-written by character authors — a full input-validation pass is scheduled with the future admin UI PR, not as an isolated clamp.

### ✅ Verification

- `npm run verify`: lint + bundle + PHPUnit (27 tests / 59 assertions) all green.
- **PHPUnit does not cover the new `_mod` helpers**: same `set_transient` / `random_int` / `DateTimeImmutable` mock gap as v2.19.1. Manual smoke test path: cross-midnight switch, same-day cache hit, oversleep + IP record (now minute-precision), malformed settings, old cache transition (`_mod` suffix forces miss).

### 📦 Versioning Note

The two commits landing this feature (`b54d96c`, `239a5d1`) carry `feat(v2.20.0):` / `docs(plan): v2.20.0` in their messages — they were authored when the milestone freeze table still listed sleep-minute-precision as v2.20.0. Released as **v2.19.2** patch because the behavior is a refinement of v2.19.1 (manifest schema unchanged, IP machinery unchanged, only internal time-unit upgraded). Subsequent milestones in the freeze table shift forward one version (utility-functions split → v2.20.0, JS global state encapsulation → v2.21.0, etc.).

---

## [2.19.1] - 2026-05-19

### ✨ Frieren Dynamic Sleep Time (Daily Roll)

- **`deep_sleep_start` accepts `[start, end]` hour-range arrays**: previously a fixed integer (e.g. `23` meant sleep starts at 23:00 every day), now `[22, 23]` rolls once per day. All page loads within the same day get the same value (transient cached until midnight), preventing the "Frieren suddenly sleeps / suddenly wakes on refresh" glitch.
- **`oversleep_probability` raised to 1.0**: Frieren manifest changes from 50% to 100% oversleep chance; combined with `oversleep_max_hour: 9`, Frieren wakes randomly between 07:00–09:00. Manual `/wake-ghost` records IP for 3 hours, keeping her awake on refresh.
- **New function `mpu_get_daily_deep_sleep_start()`**: mirrors the existing `mpu_get_daily_oversleep_end()`. Cache key `mpu_deep_sleep_start_{date}_{pid}`, expires aligned to next midnight via `DateTimeImmutable('tomorrow', wp_timezone())` + `max(60, ...)` clamp.
- **Array safety**: `is_array` branch uses `random_int(min, max)` so reversed ranges auto-correct; `count !== 2` logs `mpu_log_warning` and falls back to the first element; non-array settings keep the integer read.
- **Explicit `24 → 0` cross-day handling**: if a manifest sets `24`, `if ($v === 24) $v = 0` converts to next-day 00:00 (Frieren uses `[22, 23]` to avoid the ambiguity, but the function retains the guard).
- **Call site swaps**: `mpu_is_deep_sleep_time()` (`includes/llm/llm-context-builder.php`) and `wake_ghost()` (`includes/rest/class-mpu-rest-dialog.php`) previously read `$sleep_settings['deep_sleep_start']` directly — now call the new function. `wake_ghost` retains a `function_exists` guard as a load-order safety net.

### ⚠️ Known Limitations

- **Sleep start still aligned to the hour**: this release only randomizes "which hour"; minute-level randomization (22:43 sleep / 07:35 wake) is scheduled for **v2.19.2** together with wake-time minute precision and cache key migration.

### ✅ Verification

- `npm run verify`: lint + bundle + PHPUnit (27 tests / 59 assertions) all green.
- **PHPUnit does not cover the new function**: `mpu_get_daily_deep_sleep_start()` depends on `set_transient` / `get_transient` / `wp_date` / `random_int` / `DateTimeImmutable`, none of which are mocked in the current `tests/bootstrap.php`. "Tests pass" only proves lint OK, syntax OK, and no regression in existing test paths. Verify behavior manually (cross-day switch, same-day cache hit, oversleep + IP, malformed settings).

---

## [2.19.0] - 2026-05-19

### 🏷️ Core Class Type Hints (v2.19 #5 milestone)

- **`MPU_Session_Event`**: `build(string $kind, array $payload = []): array` and `kind_for_legacy_event(string $event): string` get PHP 7.4-compatible type hints.
- **`MPU_Input_Role`**: 5 static methods (`resolve` / `can_use_ability` / `current_can_use_ability` / `normalize_ability_name` / `is_known_role`) gain `string` / `bool` type hints. Internal `(string)` casts retained as a defense layer.
- **`MPU_REST_Base`**: `rate_limit` returns `?WP_REST_Response`, `ok` returns `WP_REST_Response`, `fail` returns `WP_Error`; `$data` deliberately remains mixed. **`check_admin()` intentionally untyped**: actual contract is `true|WP_Error`, which PHP 7.4 cannot express (no union types).
- **`chat-integrity.php`**: 13 functions receive `string` / `array` / `bool` / `void` / `WP_Error` type hints. **`verify_history()` intentionally untyped**: actual contract is `true|null|WP_Error`. `_store_history(): bool` correctly aligns its two return paths (`return false` and `set_transient()` returning bool).

### 📋 #6 utility-functions Decomposition Pre-Audit

- Confirmed `chat-integrity.php` / `provider-helpers.php` / `utility-functions.php` load before `rest/bootstrap.php`, making `function_exists('mpu_chat_integrity_*')` and `function_exists('mpu_rest_check_rate_limit')` calls in REST and chat history candidate-redundant defenses under load-order guarantees.
- **No defenses removed in this release**. Redundancy cleanup is deferred to the #6 utility-functions decomposition PR — file moves and `function_exists` removal are two sides of the same precondition and should land in one reviewable unit.

### ✅ Verification

- `npm run verify`: PHP lint + bundle build + PHPUnit (27 tests / 59 assertions) all green.
- **Behavior unchanged**: pure type hint additions; no logic changes, file moves, or interface alterations.

---

## [2.18.0] - 2026-05-18

### 🧪 Testing Infrastructure (PHPUnit + verify pipeline)

- **Added PHPUnit unit testing**: introduced `tests/` with a minimal WordPress mock bootstrap (`tests/bootstrap.php`) that lets pure-function tests run without booting WordPress. Six initial suites cover `chat-integrity` (filter / checksum / slice), encryption round-trip, input role resolution, session event envelope, template rendering, and the new chat lock — 22 tests / 51 assertions in total.
- **Composer-based tooling**: dev dependencies (`phpunit/phpunit ^9.6`, `brain/monkey ^2.6`) live in `tools/php/composer.json` with vendor isolated under `tools/php/vendor/`. The plugin itself ships no runtime composer dependency.
- **`npm run verify` pipeline**: `tools/node/package.json` now chains `lint:php` → `build` → `test:php` so every change can be validated locally. The build script now exits non-zero on minify failure so CI no longer silently passes.
- **PHPUnit `cacheResult="false"`**: prevents `.phpunit.result.cache` write attempts from triggering permission warnings in restrictive sandboxes.
- **Filter/action mocks actually execute callbacks** in `tests/bootstrap.php` (priority-sorted, `accepted_args`-aware) — future tests for filters like `mpu_chat_integrity_mode` will be reliable.

### 🔒 Chat Lifecycle Lock (concurrent LLM protection)

- **New `MPU_Chat_Lock` class** (`includes/llm/class-mpu-chat-lock.php`): atomic check-and-set primitive using `add_option($key, $payload, '', 'no')`. Transients are deliberately not used because `get_transient()` + `set_transient()` is not atomic under parallel requests and would itself have the race the lock is meant to prevent.
- **Expired-lock retry flow**: if `add_option()` fails and the existing lock has expired, `delete_option()` is called and one retry attempts a fresh acquire. Stale locks from crashed PHP workers self-heal within one request cycle.
- **Token-validated release**: `release($session_id, $token)` uses `hash_equals()` to compare tokens, so a request can never accidentally release a lock held by another request. Defense-in-depth against double-finally bugs.
- **60-second TTL via `mpu_chat_lock_ttl` filter**, clamped to `[10, 300]` seconds.
- **Three action hooks**: `mpu_chat_lock_acquired`, `mpu_chat_lock_released`, `mpu_chat_lock_conflict` — for metrics collection, audit logging, or future approval-hub integration.
- **Wired only into `/chat/user` and `/chat/user-stream`**: `/chat/greet`, `/chat/context`, and `/debug_mcp` are intentionally not locked (they fall under existing per-route rate limits).
- **Conflict returns HTTP 429** using the existing `$this->fail()` envelope, so the frontend error path needs no changes.
- **SSE-safe release**: `register_shutdown_function()` is registered immediately after lock acquire, and the stream loop calls `connection_aborted()` between chunks via `exit_if_stream_aborted()` so a client disconnect mid-stream still releases the lock. Token validation prevents the shutdown fallback from clobbering a lock acquired by a subsequent request.
- **Lock context records** `route`, `input_role`, and an `ip_hash` (first 12 chars of `sha256(client_ip)`) — useful for triage without storing raw IP.

### 🧹 REST Chat Handler Dedup

- **New `prepare_auto_chat_context()` helper** in `MPU_REST_Chat`: centralizes the boilerplate previously duplicated between `chat_context()` and `chat_greet()` — `ai_enabled` / `ai_greet_first_visit` precheck, provider + API key, `wp_info`, ukagaka identity, language, personality, time context, the 13-key variable map, and the resolved system prompt.
- **`require_first_visit_greeting` option flag** differentiates `chat_greet`'s extra check from `chat_context` without splitting the helper into two functions.
- **Response shape unchanged** for both endpoints. Checksum store paths (`store_after_auto` with kind `'context'` / `'greet'`) intentionally untouched. `prepare_user_chat_args()`, chat lock, and SSE handling are not affected.

### 🏷️ SSE Stream State Badge (runtime verification UI)

- **Visible `.mpu-state-badge` element** rendered in the top-right corner of `#ukagaka_msgbox`, backed by the existing `data-mpu-stream-state` attribute introduced in 2.17.0 — no new state machine, just a visible surface for the existing data.
- **Six visible states**: `thinking`, `streaming`, `tool`, `error`, `timeout`, `busy`. `status` is mapped to the same label as `streaming` to avoid empty badges.
- **`busy` state on chat lock conflict**: `handleStreamFailure()` detects `error.code === "mpu_chat_lock_busy"` or `error.data.status === 429` from the SSE JSON fallback path and surfaces the localized busy message instead of a generic error.
- **`streaming` state on first delta**: `onDelta` now calls `setStreamState("streaming")` instead of clearing the attribute, so the badge stays visible during text streaming.
- **i18n via existing `mpuL10n` mechanism**: new `mpuL10n.streamStates` map exposes Japanese-first labels (`考え中…` / `応答中…` / `調べてる…` / `エラー` / `タイムアウト` / `混雑中…`), translatable through the existing `.po` / `.mo` workflow.

### 🐛 Bug Fixes

- **Ollama Empty Tool Calls Array Parsing Fix**: Fixed an issue where Ollama's tool calls without arguments (e.g., `get-bot-blocker-stats`) caused PHP's `json_decode` and `json_encode` to convert an empty object `{}` into an empty array `[]`, leading to the Ollama engine throwing a `"Value looks like object, but can't find closing '}' symbol"` error. This is now fixed via `mpu_normalize_ollama_assistant_message()` which forces empty arrays to `stdClass` (`{}`), supported by 5 new PHPUnit test cases covering all edge cases.
- **Enhanced Provider HTTP Error Logging**: When encountering HTTP >= 400 errors, the system now attempts to extract the Provider JSON's `error` field to provide cleaner error messages, and uses `error_log` to export complete debug information including the tool_calls structure and a 4KB response tail.
- **UI State Badge Position Adjustment**: Tweaked the CSS for `.mpu-state-badge` to better fit inside the top-right corner of the message box, preventing clipping.

### ✅ Verification

- `npm run verify`: PHP lint + bundle build + PHPUnit (22 tests, 51 assertions) all green.
- `js/dist/ukagaka-bundle.js` and `.min.js` rebuilt (169.7 KB → 79.0 KB after minify).
- `git diff --check` confirmed no whitespace errors.

---

## [2.17.0] - 2026-05-15

### 🛡️ Input Role Resolver + Server-side Tool Gate

- **New `MPU_Input_Role` class** (`includes/core/class-mpu-input-role.php`): separates LLM input identity (`admin` / `system` / `subscriber` / `visitor`) from raw WordPress capabilities. `resolve()` derives the role from request context plus current WP state; `can_use_ability()` checks a hardcoded whitelist for prompt-injection resistance.
- **Request-scoped input context** (`request-state.php`): added `mpu_set_request_input_context()` / `mpu_get_request_input_context()` so the chat endpoint, tool exposure, and tool execution all see the same context.
- **Two-stage tool gate** (`abilities-integration.php`): `mpu_get_mcp_tools_for_llm()` filters the tool list by role before sending to the LLM, and `mpu_execute_mcp_tool()` re-checks the role at execution time to catch any out-of-band tool calls. Built-in ability classes (`visitor-pulse`, `ai-crawler`, `wp-postviews`, `wp-bot-blocker` ×3) now use `MPU_Input_Role::current_can_use_ability()` with a safe `current_user_can('manage_options')` fallback.

### 📡 Session Event Envelope (SSE)

- **New `MPU_Session_Event` class** (`includes/llm/class-mpu-session-event.php`): defines event kinds (`stream.delta / status / done / error`, `tool.request / result`, `nonce.refresh`) and wraps payloads with `eventId + ts + kind + payload`.
- **Backward-compatible SSE**: `streaming-helpers.php` automatically converts legacy event names through `kind_for_legacy_event()` and wraps payloads with the new envelope — existing endpoints work unchanged.
- **Frontend dispatcher** (`js/ukagaka-chat.js`): `window.MPU_EVENTS` constants and `mpuNormalizeSseEvent()` detect new envelopes vs legacy events; the switch handles both forms in parallel.

### ⏱️ Client-side SSE Watchdog (zombie state prevention)

- **45-second timeout** with `AbortController.abort()`: if no SSE event arrives for 45 seconds, the stream is aborted, the pending user message is rolled back from `mpuChatHistory`, the input box is re-enabled, and a localized timeout message is displayed via `mpuShowBalloon()`.
- **Unified `onEvent` hook**: every legacy and new-envelope event resets the watchdog, eliminating bug-prone per-handler reset duplication.
- **Typewriter-safe**: `stream.done` and `stream.error` clear the watchdog at the `onEvent` level, so when the backend finishes streaming but the frontend typewriter still has text to render, the watchdog never fires accidentally.
- **`data-mpu-stream-state` attribute** on `#ukagaka_msgbox`: exposes `thinking / tool / status / timeout / error` states for CSS theming or debugging.
- **`tool.request` handler**: the frontend now shows an "executing tool…" state when backend sends `tool.request` events (used in future PRs).

### 🔐 Chat Integrity Three-Tier Mode

- **New `chat_integrity_mode` option** (default: `audit`): `audit` logs mismatches without interruption (existing behavior), `warn` adds a WARN decision and fires the `mpu_chat_integrity_mismatch` action hook, `block` returns `WP_Error` (HTTP 409) when an existing checksum mismatches.
- **Three control points**: constant `MPU_CHAT_INTEGRITY_MODE` > option `chat_integrity_mode` > filter `mpu_chat_integrity_mode`. Missing checksums always pass through (`block` only fires on real mismatches, not first requests or session resets).
- **`mpu_chat_integrity_should_block` filter**: allows per-session bypass / grayscale rollout for `block` mode.
- **`mpu_chat_integrity_mismatch` action hook** fires in all three modes with `{ session_id, expected, actual, mode, decision, source, history_count }` payload — usable for metrics collection or webhook integration even in `audit` mode.
- **REST chat propagates the WP_Error status**: `class-mpu-rest-chat.php` now reads `error_data['status']` (409) instead of hardcoding 400.
- **Surgical change**: the store / normalize / slice pipeline is untouched — only the verify decision layer was modified.

### 🛠️ Hardening Patches (absorbed from 2.16.1–2.16.4)

- **WordPress output escaping audit** across admin settings pages.
- **Bot-blocker scanner exemption**: full scanner exemption and rate-limit skip for logged-in requests to prevent false reputation flags; extracted `mpu_bb_is_logged_in_request()` helper for shared use.
- **`MPU_PLUGIN_DIR` fix**: replaced an undefined constant reference with `plugin_dir_path(MPU_MAIN_FILE)`.

### ✅ Verification

- PHP lint passed on all touched files (`chat-integrity.php`, `chat-history-service.php`, REST chat, `core-functions.php`, abilities).
- JS syntax check passed (`ukagaka-chat.js`, `dist/ukagaka-bundle.js`).
- Bundle rebuilt (`js/dist/ukagaka-bundle.js` + `.min.js`).
- `git diff --check` confirmed no whitespace errors.

---

## [2.16.0] - 2026-05-13

### 🧠 User Memory MVP

- **Added `/remember` admin command**: admins can type `/remember` in the frontend chat box to extract stable admin facts from the latest 20 chat messages and store them in `mpu_user_memory` usermeta.
- **Added Memory REST Controller**: added `MPU_REST_Memory` and `POST /memory/extract`, protected by `manage_options`, WordPress REST nonce verification, a 60-second transient throttle, and defensive cleanup.
- **System prompt memory injection**: `mpu_resolve_system_prompt()` now injects saved memory as "管理人についての記憶（参考メモ）" and explicitly marks it as reference information, not instructions, reducing prompt injection risk.
- **AI Settings memory management**: added a memory card at the bottom of the AI tab to show saved facts, last update time, and a nonce-protected clear action for the current admin user.

### ✅ Release Quality and Runtime Info

- **Added release verification tooling**: `npm run lint:php` now scans the main plugin file, and `npm run verify` runs PHP lint followed by the JS build.
- **Added REST smoke test checklist**: added `docs/REST_SMOKE_TEST.md`, `docs-en/REST_SMOKE_TEST.md`, and `docs-jp/REST_SMOKE_TEST.md`, covering baseline endpoints, session token flow, token enforcement, chat round-trip, admin guards, and SSE headers.
- **GitHub auto-update support**: bundled Plugin Update Checker v5.6 (`vendor/plugin-update-checker/`) and added `includes/updater/github-updater.php`. The plugin now detects new GitHub Releases and shows an update notification in the WordPress admin dashboard. Upload `mp-ukagaka.zip` as a release asset on each tag to enable one-click updating.
- **Runtime Info refinements**: adjusted weather temperature thresholds and added Frieren emotion trigger rules in `instructions.md` to improve character nuance without encouraging overreaction.
- **i18n debt cleanup**: expanded Traditional Chinese and Japanese translations via `mp-ukagaka-zh_TW.po/.mo` and `mp-ukagaka-ja.po/.mo`.

---

## [2.15.0] - 2026-05-08

### 🔒 Security and Abuse Hardening

- **Session token guard for public AI REST endpoints**: Added an anonymous visitor IP-bound session token. `/chat/context`, `/chat/greet`, `/chat/user`, and `/chat/user-stream` now require a valid token, reducing the risk of direct external API calls consuming AI quota.
- **Lazy-fetched session token**: The frontend no longer embeds the token directly in HTML. It fetches one from `/session-token` before the first protected request, with `no-store` cache headers to avoid leaking the first visitor's token through full-page caching.
- **Unified frontend token injection**: `mpuFetch` and `mpuFetchSSE` now automatically send `X-MPU-Session-Token`, with helper existence guards so normal REST and SSE flows behave consistently.
- **Raw JavaScript extension permission tightened**: Saving and rendering `extend[js_area]` now requires `unfiltered_html`, avoiding raw frontend JavaScript output in environments where `manage_options` alone should not grant that ability.
- **Personality ZIP overwrite hardening**: ZIP uploads are extracted to a temporary directory first, then protected by a confirmation step, backup→rename→rollback flow, case-insensitive reserved ID checks, and `realpath()` + `DIRECTORY_SEPARATOR` boundary checks to reduce Zip Slip, symlink escape, and accidental overwrite risks.

### 🧱 Structure and Maintainability

- **Chat history/checksum logic extracted**: Added `MPU_Chat_History_Service` to centralize session id handling, history parsing, checksum verify/store, and integrity updates after normal chat and streaming responses.
- **Admin save handler split**: `mpu_handle_options_save()` now delegates general settings, character settings, AI, LLM, diary, and Bot Blocker saves to dedicated helper functions, reducing the maintenance cost of one large branching handler.
- **Backward-compatible REST behavior retained**: REST routes, error codes, SSE event names, response shapes, and checksum behavior were preserved. This release focuses on incremental hardening rather than broad refactoring.

### ✅ Verification and Documentation

- **JS bundle rebuilt**: Updated `js/dist/ukagaka-bundle.js` and `js/dist/ukagaka-bundle.min.js`.
- **Syntax and build verification**: PHP lint and Node build checks passed.
- **Hardening plan added**: Added `plan/Code_Quality_Hardening_Plan.md` documenting the security assessment, phased improvement order, implementation summary, and post-review fixes.

---

## [2.14.1] - 2026-04-30

### ✨ Admin Profile: Backend Management

- **New admin fields**: Added "Admin full nickname", "Admin short name", and "Admin birthday" fields to the **General Settings** page (`options_general.php`). Admin nickname, short name, and birthday can now be configured directly from the WordPress admin panel — no more manual editing of `personality.md` or `calendar.json`.
- **Automatic System Prompt injection**: Added `mpu_get_admin_profile_prompt_block()` to `core-functions.php`. During System Prompt rendering, `{{admin_nickname}}`, `{{admin_name}}`, and `{{admin_birthday}}` are automatically replaced with the backend values, and an override instruction block is appended.
- **Dynamic calendar.json override**: Added logic in `personality-prompts.php` to automatically remove the `"MM-DD"` placeholder from `calendar.json` and replace it with the actual birthday when configured in the backend — no file editing required.
- **Birthday format validation**: Added `mpu_normalize_admin_birthday()` to `admin-functions.php`, enforcing `MM-DD` format validation and normalization on save.

### 🎌 Holiday Periods Support

- **New holiday_periods mechanism**: `calendar.json` now supports date-range holiday definitions. The system automatically determines whether the current date falls within a specified period and triggers the corresponding holiday reactions.
- **Built-in holiday periods**:
  - **Golden Week** (4/29–5/5): Character displays dedicated holiday reactions during Golden Week
  - **New Year** (1/1–1/7): Character displays dedicated holiday reactions during the New Year period
- **Prompt and Weight support**: Added corresponding holiday period prompt categories and weight configurations to `prompts.json` and `weights.json`.

### 📖 Documentation Updates

- **Abilities section added to USER_GUIDE**: Added an "Abilities" subsection to the Interactive Chat Mode chapter across all three language versions (ZH-TW, EN, JP). Documents the 6 built-in abilities (popular posts query, IP ban, Bot Blocker stats/clear, AI crawler detection, visitor pulse) with usage examples and required plugin list.
- **README updates**: Updated admin profile setup instructions in all three language versions to reflect the new backend configuration approach.
- **New screenshot**: `screenshot7.PNG` — Abilities feature demo (character reporting Bot Blocker statistics in-character).

---

## [2.14.0] - 2026-04-29

### LLM Streaming Expansion: Gemini / Claude

- **Gemini streaming added**: the `gemini` provider now implements `generate_chat_stream()` using the `streamGenerateContent?alt=sse` endpoint. Plain text responses are forwarded to the frontend as SSE `delta` events.
- **Claude streaming added**: the `claude` provider now implements `generate_chat_stream()` for the Anthropic Messages API, handling `content_block_start`, `content_block_delta`, and `content_block_stop`, including buffered `input_json_delta` tool arguments.
- **Tool-call safety retained**: when MCP tools are available for Claude, streamed text is buffered until the turn is known to be tool-free. If a tool call occurs, pre-tool text is not emitted, keeping frontend display, backend checksum, and final tool output aligned.
- **Gemini tools fallback**: Gemini streaming currently supports plain-text streaming first. When MCP tools are available, it falls back to synchronous `generate_chat()` and emits the final result as a single `delta`, preventing tool support from silently disappearing in streaming mode.

### Streaming Stability and Frontend Display

- **Shared SSE parser**: added `mpu_stream_sse_events()` to handle cross-chunk line merging, multi-line `data:` payloads, blank-line dispatch, and final event flushing when a stream does not end with a blank line.
- **Claude tool-loop protection**: Claude streaming now returns `max_turns_exceeded` when `MPU_MAX_TOOL_TURNS` is exhausted instead of ending as an empty successful response.
- **Claude tool argument guard**: failed JSON decoding for streamed Claude tool input is logged for debugging and passed as empty arguments so the tool can return an error result instead of running with malformed input.
- **Frontend streaming typewriter queue**: chat-mode streaming display now uses a local timer and pending queue, respects the admin `typewriter_speed` setting, and guards against duplicate finalization.
- **Bundle rebuilt**: updated `js/dist/ukagaka-bundle.js` and `js/dist/ukagaka-bundle.min.js`.

---

## [2.13.9] - 2026-04-29

### 🧹 Dead Code Removal (Phase 1 & 2)

Removed all orphan functions confirmed to have zero runtime callers across PHP and JS source. No behaviour change.

**PHP removed (21 functions across 8 files):**

- `utility-functions.php`: `mpu_enforce_rate_limit`, `mpu_verify_ajax_nonce`, `mpu_input_filter`
- `ai-functions.php`: `mpu_call_gemini_api`, `mpu_call_openai_api`, `mpu_call_claude_api`, `mpu_call_ollama_api`, `mpu_get_allowed_conditional_tags`
- `chat-api-handlers.php`: `mpu_call_ollama_with_messages`, `mpu_call_openai_with_messages`, `mpu_call_claude_with_messages`, `mpu_call_gemini_with_messages` (unified entry `mpu_call_ai_api_with_messages` retained)
- `llm-context-builder.php`: `mpu_compress_context_info`, `mpu_get_context_label` (depended on non-existent `mpu_load_personality_dynamics`)
- `llm-functions.php`: `mpu_get_ollama_settings`, `mpu_debug_system_prompt`
- `personality-prompts.php`: `mpu_get_dynamic_prompt_templates`
- `personality-decorations.php`: `mpu_get_personality_all_decorations`
- `personality-loader.php`: `mpu_is_frieren_personality`, `mpu_get_personality_trait`
- `weather-functions.php`: `mpu_get_weather_info`

**JS removed (4 functions across 3 source files):**

- `ukagaka-base.js`: `mpu_init_visit_tracking`
- `ukagaka-core.js`: `mpu_hideMsgText`, `mpuMoe`
- `ukagaka-chat.js`: `mpu_escapeHTML`

**Docs updated:** API_REFERENCE and DEVELOPER_GUIDE across all three language sets (EN / TW / JP) have been updated to reflect removals.

---

## [2.13.8] - 2026-04-27

### ✨ New: Visitor Pulse and AI Crawler Signals

- **AI crawler detection added**: `llm-slimstat.php` now includes an AI crawler signature table and helper functions to detect recent GPTBot, ClaudeBot, Google-Extended, PerplexityBot, and other AI / LLM crawlers from Slimstat bot records.
- **Visitor Pulse signals added**: three new Slimstat-backed site signals were introduced:
  - `foreign_visitor`: a country appearing for the first time in the recent 60-minute window
  - `traffic_spike`: a meaningful increase in human visitors compared with the previous hour
  - `late_night_visitor`: human visitors still present during late-night hours
- **New auto-talk event branches**: `/check-spam-event` now handles `ai_crawler_alert` and `visitor_pulse_alert`, allowing Frieren to react to these site signals with spontaneous dialogue.
- **New Abilities tools**: added two read-only tools, `mp-ukagaka/get-recent-ai-crawlers` and `mp-ukagaka/get-visitor-pulse`, so the LLM can actively query recent crawler activity and visitor pulse summaries.

### 💤 Personality Consistency: Sleep Mode for Event Pushes

- **Sleep-time dream fallback**: added the shared helper `mpu_pick_sleep_dream_line()`. When the character is inside the deep sleep window (default `00:00–06:00`, with optional oversleep extension), new event reactions no longer call the LLM and instead use dream lines from `sleep_mode.json`.
- **New `visitor_dreams` pool**: `ghost/Frieren/sleep_mode.json` now includes visitor-pulse dream lines, while AI crawler reactions are skipped during sleep mode as low-priority events.
- **Sleep-mode conflict resolved**: fixed the issue where late-night visitor events could generate fully awake analytical dialogue while Frieren was supposed to be asleep.

### 🔒 Security and Stability Fixes

- **Foreign-visitor dedupe timing fixed**: `mpu_detect_visitor_pulse_event()` is now query-only and no longer writes seen countries during detection. Seen countries are committed only after a message is successfully generated via `mpu_visitor_pulse_commit_seen_countries()`, preventing new countries from being silently consumed by cooldown.
- **Abilities permissions tightened**: the two new abilities now use `current_user_can('manage_options')` for `permission_callback`, preventing unauthorized access to visitor pulse and crawler metadata through the Core Abilities API REST endpoints.
- **IP spoofing protection hardened**: added `mpu_get_client_ip_strict()` and switched rate limiting plus `/visitor-info` Slimstat lookups to strict IP resolution, preventing forged `X-Forwarded-For` / `CF-Connecting-IP` headers from bypassing LLM endpoint quotas or probing visitor data for arbitrary IPs.
- **Dead weight config removed**: `weights.json` keeps the effective `ai_crawler_reactions` / `visitor_pulse_reactions` category weights and the `is_bot` adjustment, while removing the non-functional `is_foreign_visitor` / `is_late_night` context blocks.
- **Frontend event routing completed**: `ukagaka-core.js` now logs `ai_crawler_alert`, `visitor_pulse_alert`, and `spam_alert` explicitly instead of misclassifying new actions as Akismet spam.

---

## [2.13.7] - 2026-04-25

### 🐛 Bug Fix: Akismet 5.7 Compatibility — AI Dialogue Broken

- **Root Cause**: Akismet 5.7 introduced the `akismet/get-stats` ability (WordPress Abilities API) with an input schema using a JSON Schema union type: `type: ['object', 'null']`. When mp-ukagaka collects all registered abilities via `wp_get_abilities()` and forwards them as tool definitions to the LLM provider, this union-type array caused the entire API call to be rejected — Gemini, OpenAI, and Claude all require `type` to be a plain string (e.g. `"object"`), not an array.
- **Symptom**: With Akismet active, AI dialogue stopped generating entirely and the character fell back to built-in static dialogue. Disabling Akismet restored AI dialogue immediately.
- **Fix**: Added `mpu_normalize_schema_for_llm()` in `abilities-integration.php`. This function recursively walks any ability's input schema and converts union `type` arrays (e.g. `['object', 'null']`) to a single string (the first non-null type), before the schema is sent to any LLM provider.

### 🐛 Bug Fix: gotop Button Intercepted Under SPA Mode

- **Symptom**: When the WordPress theme has SPA (Single Page Application) mode enabled, clicks on the "back to top" (gotop) button in the ukagaka dock were intercepted by the SPA router, preventing the button from working.
- **Fix**: Added the `data-spa-ignore` attribute to the `#toTop` anchor in `frontend-functions.php`, and added `e.stopPropagation()` to the click handler in `ukagaka-features.js` to prevent SPA frameworks from intercepting the anchor click.

---

## [2.13.6] - 2026-04-24

### 📖 Docs: Developer Documentation Catch-Up & Cross-Language Sync

- **Completed the missing developer doc updates from yesterday**: Fully reviewed and updated `API_REFERENCE.md`, `DEVELOPER_GUIDE.md`, `CANVAS_CUSTOMIZATION.md`, `DEBUG_SLIMSTAT.md`, `ABILITIES_API.md`, and `GHOST_CREATE_GUIDE.md` so they now match the current codebase structure.
- **Aligned REST / Abilities / Personality architecture docs**:
  - Replaced outdated AJAX, old hooks, legacy globals, and obsolete module descriptions with the current REST controller architecture.
  - Clarified the distinction between the public Abilities API concept and the internal MCP-era naming that still remains in parts of the implementation.
  - Updated the ghost creation guide to use `instructions.md + personality.md` as the primary prompt structure, while keeping `system_prompt.md` / `manifest.json.system_prompt` documented as legacy fallback behavior.
- **Documented the currently supported personality / frontend structure**:
  - Added coverage for `touchzones.json`, `sleep_mode.json`, `calendar.json`, `diary.json`, `emoji-keywords.json`, `scripts`, and related current-era files.
  - Refreshed the canvas decoration system, Slimstat/visitor-info debugging flow, init payload notes, and frontend script-loading documentation.
- **Three-language sync complete**: Traditional Chinese, English, and Japanese developer docs and changelogs are now aligned.

---

## [2.13.5] - 2026-04-23

### 📖 Docs: USER_GUIDE Full Restructure

- **Three-Part Architecture**: Reorganized the user guide around use-cases rather than settings tabs:
  1. **Basic Settings** (applies with or without AI): Installation, Ukagaka management, auto-dialogue, custom emoji system, etc.
  2. **AI Features** (requires an AI provider): LLM settings, Page Awareness, Interactive Chat Mode, Thinking Mode, Weather Awareness, Automated Diary
  3. **Static Dialogue Features** (without AI): External dialogue files, dialogue settings, special codes, extensions
- **Emoji System Clarification**: Clarified that the emoji system works for both static and AI-generated dialogue; moved to Part 1 (previously placed incorrectly)
- **Cross-Language Content Sync**:
  - English (`docs-en/`): Added Weather Awareness and ZIP Upload sections (previously missing)
  - Japanese (`docs-jp/`): Added ZIP Upload section (previously missing)
- **Removed Developer-Only Content**: Removed the 35 dialogue categories listing, PHP dynamic weight code, Thinking Mode PHP snippets, and other developer-specific technical details from the user-facing guide (these belong in DEVELOPER_GUIDE)

---

## [2.13.4] - 2026-04-21

### 🐛 Bug Fix

- **Browser Autofill on API Key Fields**: Changed `autocomplete="off"` to `autocomplete="new-password"` on all three API key password inputs (Gemini, OpenAI, Claude) in the LLM Settings page. Some browsers ignored `autocomplete="off"` and injected saved passwords into the fields, causing them to show `.......` instead of the placeholder text.
- **Browser Username Autofill on Custom Model Inputs**: Added `autocomplete="off"` to the custom model text inputs for all three providers. The browser was treating the text input adjacent to a password field as a username field and auto-filling `admin`.
- **Gemini 2.5 Pro: Thinking Parts Not Handled**: Gemini 2.5 Pro returns internal thinking blocks (`"thought": true`) in the `parts` array before the actual response text. The parser was only checking `parts[0].text` and failed when the first part was a thought block. Fixed by iterating all parts and skipping those with `thought: true` in `generate_text`, `generate_chat`, and `test_connection`.
- **Gemini 2.5 Pro: MAX_TOKENS in Test Connection**: The test connection request used `maxOutputTokens: 50`, which was exhausted entirely by Gemini 2.5 Pro's internal thinking (47 thinking tokens), leaving no room for actual output and returning an empty `content.parts`. Increased to `200`.
- **Removed Gemini 2.5 Pro Preset**: Removed `gemini-2.5-pro` from the preset model list. Due to its mandatory thinking overhead, it is unreliable with the current token budget. Users who need it can still enter it via the custom model input.

---

## [2.13.3] - 2026-04-20

### ✨ Enhancement: Custom Model Selection & Claude Version Update

- **Custom Model Input**: Added free-text "Custom Model…" option to the model selector on both the **LLM Settings** page and the **Diary AI Settings** page for Gemini, OpenAI, and Claude providers. Selecting "Custom Model…" reveals a text input where any valid provider model ID can be entered directly, without being limited to the preset list. The selected value is stored identically to a preset selection — no backend changes required.
- **Claude Model Versions Updated**: Refreshed the Claude preset model list to the latest available versions:
  - Sonnet 4.5 → **Sonnet 4.6** (`claude-sonnet-4-6`)
  - Opus 4.5 → **Opus 4.7** (`claude-opus-4-7`)
  - Haiku 4.5 remains (`claude-haiku-4-5-20251001` — no 4.6 Haiku available yet)

---

## [2.13.2] - 2026-04-16

### 🌐 Localization: System Messages Unified to Japanese

- **Full i18n Overhaul**: Unified all hard-coded Chinese (zh-TW) system messages to Japanese across 18 PHP files (~200+ strings), ensuring a consistent language experience in all user-facing output.
  - `includes/core/`: File operation, security validation, and rate-limiting error messages (`utility-functions.php`); frontend UI labels and `mpuL10n` localization strings (`frontend-functions.php`); dialogue file error messages (`ukagaka-functions.php`).
  - `includes/rest/`: All REST API error and status messages (`class-mpu-rest-base.php`, `class-mpu-rest-chat.php`, `class-mpu-rest-dialog.php`, `class-mpu-rest-ghost.php`, `class-mpu-rest-test.php`, `class-mpu-rest-touch.php`).
  - `includes/llm/`: All LLM provider error messages (`class-mpu-ai-provider-gemini.php`, `class-mpu-ai-provider-openai.php`, `class-mpu-ai-provider-claude.php`, `class-mpu-ai-provider-ollama.php`, `class-mpu-ai-provider-base.php`); diary, Ollama validation, and tool-loop messages (`diary-functions.php`, `llm-functions.php`, `tool-loop-guard.php`).
  - `includes/admin-functions.php`: All admin panel notices and ZIP upload/validation messages.
- **Extended `mpuL10n`**: Added new localization keys to the frontend JavaScript object (`loadingFailed`, `errorOccurred`, `duplicateRequest`, `requestFailed`, `securityVerificationFailed`, `animationLoadFailed`, `connectionError`, `chatExit`) so JS modules no longer rely on hard-coded fallback strings.
- All strings remain wrapped in `__()` for future `.po`/`.mo` translation pack support.

### 🐛 Bug Fix

- **`mpuL10n` Key Mismatch**: `js/ukagaka-dialog.js` was referencing `mpuL10n.loadFailed` (undefined) instead of the correct key `mpuL10n.loadingFailed`, silently falling back to the built-in string. Fixed.
- **Missing `chatExit` Key**: `js/ukagaka-chat.js` referenced `mpuL10n.chatExit` which was never registered in `wp_localize_script`. The key is now added to `frontend-functions.php`.

---

## [2.13.1] - 2026-03-18

### 🔒 Security Hardening

- **Command Access Control**: Admin-only commands (`/debug_mcp`, `/reset`, `/clear`) no longer return a system error message for non-logged-in users. Instead, the `visitor_rejection` prompts from `dynamics.json` guide the character to refuse in-character, consistent with MCP tool rejection behavior.
  - Backend: Non-admin `/debug_mcp` requests now fall through to the standard AI pipeline; the non-admin system prompt includes a note to reject slash commands.
  - Frontend: `mpuPreSettings` gains an `is_admin` flag; `/reset` and `/clear` are guarded by admin check and fall through to the AI path for non-admin users.
- **IP Spoofing Mitigation**: `mpu_get_client_ip()` (rate limiting) and `mpu_bb_get_ip()` (Bot Blocker auto-ban) now validate proxy header values (CF-Connecting-IP / X-Forwarded-For) against public address rules (`FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE`), preventing attackers from forging private/reserved IPs to bypass rate limits or trigger false auto-bans on legitimate targets.

### 🐛 Bug Fix

- **`fail()` Lost Error Details**: The `fail()` method in `class-mpu-rest-base.php` now accepts an optional 4th parameter `$data`. Provider-returned debug fields such as `http_status` and `raw_body` are no longer silently dropped and are now accessible in the REST response body for admin debugging.

---

## [2.13.0] - 2026-03-06

### 🧹 Dead Code Cleanup

- **Removed Unused Functions & Files**: Removed deprecated code that hasn't been used and has no frontend/backend calls to ensure plugin lightweightness and future maintainability.
  - Removed multiple obsolete files in `includes/rest/chat/` and `includes/ajax/chat/` directories which have been replaced by the latest OO architecture.
  - Removed outdated functions such as `mpu_html_decode`, `mpu_is_browser`, `mpu_should_trigger_ai`, `mpu_ukagaka_list`, `mpu_get_default_msg`, `mpu_get_next_msg`, `mpu_get_msg_key`, `mpu_count_msg`, and simultaneously removed their references from the Developer Guides.

---

## [2.12.5] - 2026-02-28

### 🚀 Major Update: Unified History & SSE Stability Hardening

- **Unified History Memory**: Frieren can now remember all interactions (auto-talk, page context, touch responses, etc.). By implementing "Synthetic User Anchors," non-chat interactions are now correctly preserved in the conversation context, resolving identity/memory gaps.
- **SSE Streaming Stability**: Fixed multiple vulnerabilities leading to persistent 400 Checksum errors.
  - **Observational Mode**: Transitioned Checksum from a blocking mechanism to an auditing mode to improve long-conversation reliability.
  - **Diagnostic Logs**: Added `logs/checksum-mismatch.log` to automatically record data differences between frontend/backend for easier troubleshooting.
  - **Symmetry Fixes**: Corrected the slice/normalize order between storage and verification paths to ensure consistency.
- **Frontend Architecture Refactoring**:
  - **Global State Migration**: Moved core states like `mpuChatHistory` and `mpuChatModeActive` to the `window` object for better cross-module stability.
  - **Capacity Doubled**: Increased chat history limit from 20 to 40 entries to accommodate all interaction events.
  - **Lifecycle Management**: Implemented reload detection (F5) to reset memory while preserving it during internal SPA navigation.
- **UX Enhancements**:
  - Refined `/reset` command response to maintain character consistency.
  - Improved `mpuFetchSSE` error handling with proper fallback for JSON error responses.

---

### 🚀 Major Update: SSE Streaming & Typewriter Effect

- **Server-Sent Events (SSE) Streaming**: Fully implemented the `text/event-stream` protocol for real-time output. AI responses now appear with a smooth "typewriter effect," significantly improving the user experience for long replies and thinking-heavy models like Ollama.
- **Dedicated Streaming Endpoint**: Introduced a new REST route `/chat/user-stream`, providing consistent security, rate limiting, and chat integrity checks as the existing synchronous path.
- **Standardized SSE Protocol**: Designed a custom event model (`delta`, `status`, `nonce`, `done`, `error`) allowing the frontend to precisely track token increments, tool execution status, and secure token refreshes.
- **Provider Streaming Support**:
  - **OpenAI**: Full support for streaming, including complex multi-chunk `tool_calls` assembly.
  - **Ollama**: Native support for JSON Lines streaming relay with integrated thinking tag filtering.
- **Connection Stability & Protection**: Integrated `ignore_user_abort` and cURL streaming callbacks to proactively terminate upstream API requests when a user disconnects or closes the chat, preventing "ghost responses" and saving resources.

### 🏗️ Advanced Architecture Upgrades

- **AI Provider Factory Pattern**: Completed Stage 2 refactoring, unifying all provider logic under the `MPU_AI_Provider_Factory` architecture, removing redundant branches for better extensibility.
- **Tool Call Loop Detection**: Officially implemented the loop signature detection system, automatically intercepting infinite "same tool + same args" cycles to protect API usage costs.

---

## [2.10.0] - 2026-02-26

### 🚀 Major Update: AI Provider Factory & Stability Enhancements

- **AI Provider Factory Pattern**: Comprehensively refactored the provider routing logic within `ai-functions.php` and `chat-api-handlers.php` into an object-oriented Factory Pattern. Introduced the `includes/llm/providers/` architecture and `MPU_AI_Provider_Factory`, streamlining future integrations for new models like DeepSeek.
- **Tool Call Loop Detection**: Implemented a robust loop guard mechanism (`tool-loop-guard.php`) for multi-turn chats. Utilizing argument JSON hash signatures, the system now instantly halts execution and returns `tool_call_loop_detected` if an LLM requests the exact same tool with identical parameters consecutively, drastically mitigating wasted API token costs.
- **API Compatibility Wrappers**: To maintain backward compatibility, 8 legacy API entry points (e.g., `mpu_call_ai_api`) have been converted into "thin wrappers" that safely route requests to the new underlying factory architecture.
- **Stability Optimizations**: Fixed parameter bugs in `handle_api_error`; added slug normalization mechanisms for defensive provider routing; and updated default Gemini model handling.

---

## [2.9.2] - 2026-02-25

### 🚀 Major Update: REST OO Routing & Refactoring

- **Object-Oriented Routing Structure**: Comprehensively refactored all 19 REST API routes into an object-oriented architecture. Introduced the `MPU_REST_Base` class and split domains into exclusive controllers (`Chat`, `Dialog`, `Ghost`, `Test`, `Touch`), centrally registered via `bootstrap.php`, dramatically improving routing maintainability.
- **Provider Helpers Extraction**: Extracted shared LLM provider logic (e.g., safe JSON encoding, tool result formatting) into `provider-helpers.php`, eliminating code duplication and laying a solid foundation for the upcoming Factory Pattern.
- **Tool Call Loop Hardening**: Enhanced tool execution protections in `includes/llm/ai-functions.php` and multi-turn chats by introducing a constant maximum loop limit (`MPU_MAX_TOOL_TURNS`), preventing the model from entering infinite local tool call loops.
- **Dead Code Cleanup & Tech Debt Reduction**: Completely removed deprecated procedural REST files (`rest-init.php`, `rest-core.php`, etc.) and legacy AJAX handlers, fully embracing the elegant and modernized routing structure.

---

## [2.9.1] - 2026-02-24

### 🛡️ Security & Stability Improvements

- **Automatic REST API Nonce Refresh**: Automatically refreshes aging nonces (12-24 hours) via `rest_post_dispatch`, preventing 403 Forbidden errors during prolonged browser sessions.
- **Type-Aware Field Sanitization**: Optimized backend settings sanitization by properly distinguishing between plain text, HTML, and booleans, preventing over-sanitization of HTML-allowed fields like system prompts.
- **UTF-8 Safe JSON Encoding**: Added `JSON_INVALID_UTF8_SUBSTITUTE` to API requests to prevent silent failures caused by malformed UTF-8 characters.
- **Unmatched Placeholder Cleanup**: Automatically cleans up unreplaced `{{variable}}` template tags before dispatching prompts to the LLM to prevent raw syntax from confusing the model.

### ✨ New Features & Improvements

- **Cron Health Status Tracking**: Introduced transient-based tracking for cron executions, providing a visual status panel in the diary settings page for admins to easily monitor the success or exact failure reasons of the auto-diary publishing feature.

---

## [2.9.0] - 2026-02-23

### 🚀 Major Update: Complete REST API Migration

- **Backend Architecture Refactoring**: Comprehensively migrated all AJAX endpoints from the legacy `admin-ajax.php` to the modern **WordPress REST API** (`wp-json/mp-ukagaka/v1/`).
- **Modular Routing**: Split the single monolithic AJAX handler into multiple modular REST controllers (e.g., `rest-init.php`, `rest-core.php`, `rest-chat.php`, `rest-touch.php`), significantly improving code maintainability.
- **Security Upgrades**:
  - Transitioned to native `X-WP-Nonce` headers combined with `permission_callback` for authenticated requests.
  - Strictly bound `GET` (read-only) and `POST` (state-modifying) REST methods to prevent unauthorized state changes.
- **Standardized Error Handling**: Fully adopted `WP_Error` combined with native HTTP status codes (400, 401, 403, 429) for more precise error parsing on the frontend.
- **Rate Limit Optimization**: Added a REST-specific rate limiting mechanism (`mpu_rest_check_rate_limit`) that returns standard HTTP 429 alongside valid `Retry-After` headers when limits are exceeded.
- **Cookie Handling Fixes**: Fixed unstable `setcookie()` calls within the REST context by routing them securely via `WP_REST_Response::header`.
- **Frontend Refactoring**: All JavaScript requests (via `mpuFetch`) have been decoupled from `admin-ajax.php`, unified to interact exclusively with the REST API, featuring refined logic for network retries (e.g., exclusively for 5xx errors).

## [2.8.3] - 2026-02-21

### 🚀 Performance Optimization & Refactoring

- **PHP Backend Performance & Structural Optimization**:
  - **O(1) Map Lookup Optimization**: Implemented a static reverse lookup map (`static $name_map`) in `personality-loader.php`, downgrading the array traversal O(n) complexity to O(1) `isset()` lookups.
  - **Request-Level Cache**: Implemented a `$post_id_cache` array in `llm-slimstat.php` to prevent repetitive, expensive `url_to_postid()` queries for the same URL.
  - **Static Resource Cache Merger**: Optimized caching in `prompt-categories.php` by using `$personality_id ?? '__default__'` so all Ukagaka personalities can benefit from static memory caching across requests.
  - **String Processing Improvements**: Extracted `mpu_normalize_for_similarity()`, executing normalization before loops to avoid repeating `preg_replace`; merged multiple Regex (`preg_match`) weather operations on the same string in `personality-prompts.php` into a single, efficient match.
  - **Loop Consolidation**: Merged 4 separate `foreach` iterations in `user-chat-handler.php` into a single loop using dynamic variables (`$$flag`), significantly reducing boilerplate code.

- **JS Frontend Optimization**:
  - **O(n²) → O(n)**: Refactored array mutative loops utilizing `splice` in `ukagaka-context.js` and `ukagaka-greeting.js` to use `filter()` and a counter mechanism. This drastically reduces CPU overhead when processing massive chat history arrays.
  - **jQuery Selector Caching**: Cached `const $msgnum` in `ukagaka-core.js`'s `mpu_nextmsg` processes to reduce repeated DOM interactions and repaints.

### 🛡️ Security & Stability

- **ZIP Bomb Mitigation**: Implemented a strict file limit check (maximum `1,000` files) in `admin-functions.php` to instantly deny uploads of archives containing excessively large numbers of files, preventing memory exhaustion (DoS).
- **Secure Randomizer**: Entirely replaced insecure `mt_rand()` instances globally with the WordPress-standard, cryptographically safer `wp_rand()`.
- **Fatal Error Prevention (`mpu_recursive_rmdir`)**: Extracted directory removal functions to global scope to eliminate nested inclusion conflicts which were causing sporadic Fatal Errors.

### 🔧 Code Cleanup & Reusability

- **Heavy Code Deduplication**: Abstracted scattered, redundant logic into unified global utility functions, cutting hundreds of lines of code:
  - PHP: `mpu_verify_ajax_nonce()`, `mpu_get_current_provider()`, `mpu_get_provider_api_key()`, `mpu_build_user_info_prompt()`.
  - JS: `mpu_isDeepSleepTime()`, `mpu_selectNextMessage()`, unified `_isDebug()` conditionals.
- **Ollama Thinking Model Recognition**: Upgraded from tedious `strpos(strtolower())` chains to a clean, maintainable `array_filter` & `stripos` mechanism.
- **Directory Scanning Simplification**: Replaced bulky `scandir()` loops with a concise `glob()` technique in `personality-loader.php`, bypassing the need for mundane `.` / `..` filtering and `file_exists` evaluations.

## [2.8.2] - 2026-02-16

- New: Added `mpu_country_code_to_name` utility function to convert ISO 3166-1 country codes to full country names (prioritizing PHP intl extension).
- Improvement: Converted visitor country codes to full names (e.g., "JP" -> "Japan") in LLM contexts (Greeting, Chat Context, Prompt Variables) to improve AI response naturalness.

## [2.8.1] - 2026-02-16

### 📝 System Prompt Loading Refactor

- **Unified Loading Logic**: Refactored the System Prompt loading mechanism across all AJAX handlers (`mpu_ajax_chat_context`, `mpu_ajax_user_chat`, `mpu_ajax_touch_zone_chat`, `mpu_ajax_decoration_chat`) to ensure consistency.
- **Modular Personality File Support**:
  - Added support for splitting `system_prompt.md` into `personality.md` (Character Background) and `instructions.md` (Behavioral Guidelines).
  - Provides more flexible character configuration management.
- **UI Source Indicator**:
  - Added a source indicator to the System Prompt section in the admin AI settings page.
  - Clearly displays whether the current source is Modular Files, Legacy File, Manifest Settings, or Backend Textarea.

### 🐛 Bug Fixes

- **Touch Reaction System Prompt Fix**: Resolved a function name typo in `ajax-touch-handlers-llm.php`, ensuring character-specific System Prompts are correctly loaded during touch and decoration interactions.

## [2.8.0] - 2026-02-15

### 🚀 Major Update: Abilities API (Tool Calling)

- **Core Integration**: Integrated WordPress Core Abilities API, empowering AI characters to perform backend operations.
  - Currently implemented `get_popular_posts` tool, allowing AI to query popular articles on the site.
  - **Permission Control**: Strictly restricted tool execution to users with administrator privileges (`manage_options`).
  - **Visitor Optimization**: For non-admin visitors, the system automatically filters out tool definitions, saving tokens and improving security.

- **Character Rejection Responses**:
  - When visitors attempt to request privileged operations (e.g., querying data), the AI will not error out but instead refuse in character.
  - Added `visitor_rejection` behavioral guidelines in `dynamics.json` to ensure diverse and personality-consistent refusals (e.g., Frieren might say "It's a secret between me and the master").

### 🔒 Security Enhancements

- **Global Nonce Verification**: Strengthened security for all frontend AJAX requests.
  - Fixed missing `mpu_nonce` in `mpu_nextmsg`, `mpu_change`, `mpu_get_settings`, `mpu_extend`, `mpu_load_dialog` requests.
  - Ensures all backend interactions undergo strict Nonce verification to prevent CSRF attacks.

- **Token Saving & Optimization**:
  - For visitors who cannot use tools, the system avoids sending large tool descriptions to the LLM, significantly reducing token consumption.
  - Prevents invalid interaction loops where the LLM attempts to call a tool only to be intercepted by the backend.

## [2.7.0] - 2026-02-12

### 🛡️ Plugin Integrations

- **Akismet + Turnstile Integration**: Integrates with Akismet and Turnstile plugins.
  - When these plugins detect spam or block scripts, the ukagaka will trigger corresponding reaction dialogues.
  - **Cooldown Mechanism**: Implemented independent reaction cooldowns (30 minutes) to prevent chatter flooding.

### 🤖 BOT Detection

- **Real-time Bot Monitoring**: Detects visits from search engine crawlers or malicious bots.
  - Deep integration with the personality system (`bot_detection`), triggering unique alert dialogues.
  - Improved detection logic based on Slimstat data.

### 🔧 Optimizations & Adjustments

- **Auto-Talk Priority Optimization**: Refined the priority logic for handling spam and bot events during auto-talk.
- **LLM Prompt Expansion**: Added dedicated prompts for spam and bot detection in `dynamics.json` and `prompts.json`.

---

### 🚀 Performance Optimization

- **Frontend JS Bundling & Minification**: 7 JS files merged into a single bundle
  - HTTP requests reduced by 87.5% (8→1)
  - File size reduced by 64.5% (160KB→60KB)
  - Uses Terser for minification
  - Supports `SCRIPT_DEBUG` for development mode switching
  - Added `npm run build` command

### ✨ New Features

- **API Cache System**: Reduces duplicate API requests and costs
  - Implemented using WordPress Transient API
  - Configurable TTL (30 minutes to 24 hours)
  - Admin settings UI (LLM Settings page)
  - Cache statistics display and one-click clear function
- **Auto Diary Feature**: AI can automatically write diary-style articles
  - Generates content based on recent browsing data
  - Supports custom title prefix and publish settings
  - Integrates with personality system diary prompts

### 🔧 Code Refactoring

- **AJAX Chat Handler Modularization**: Split `ajax-chat-handlers-llm.php`
  - `context-handler.php`: Page-aware dialogue
  - `greet-handler.php`: First-visit greeting
  - `user-chat-handler.php`: Interactive chat
  - Original file reduced from 1036 lines to 18-line loader
- **Form Handler Architecture Unification**: Consolidated handlers into `admin-functions.php`
  - Integrated LLM and Diary settings logic into `mpu_handle_options_save()`
  - Single entry point, follows WordPress best practices

### 📝 Documentation Updates

- Updated `DEVELOPER_GUIDE.md` directory structure
- Added API cache system documentation

---

## [2.5.2] - 2026-01-11

### ✨ New Features & Improvements

- **Weather Awareness**: Characters can now perceive weather conditions using Open-Meteo API
  - Uses free Open-Meteo API, no API Key required
  - Weather settings can be configured in the admin panel
  - Supports dialogue adjustments based on weather conditions

- **Sleep Mode**: Added sleep functionality
  - Characters can enter sleep mode during specified hours
  - Sleep time can be configured via `sleep_settings` in `manifest.json`
  - Supports deep sleep time and oversleep settings

- **Enhanced Frieren Personality**:
  - Improved `system_prompt.md` for the default Frieren character
  - Expanded `prompts.json` for more diverse dialogue
  - Enhanced dialogue diversity and role-playing quality

- **Touch Interaction**:
  - Added touch zones functionality
  - Characters can respond to touches on different body parts (head, face, chest, legs, etc.)
  - Configured via `touchzones.json`
  - Each zone can define independent reaction dialogues

- **Expanded Emoji System**:
  - Added more emoji types
  - Enables richer character expressions

- **New Decorations**:
  - Added two new decorations for Frieren: "Dark Dragon Horn" (暗黒竜の角) and "Clothes-Dissolving Potion" (服だけ溶かす薬)
  - Clicking decorations triggers related dialogues

- **Code Refactoring**:
  - Improved code structure and maintainability
  - Optimized module organization

---

## [2.4.0] - 2026-01-03

### 🚀 Major Update: JSON Personality System (v2.4.0)

- **Personality System**: JSON-based character configuration system
  - New `ghost/` directory (similar to Ukagaka ghost folder), each character can have independent config files
  - Supports `manifest.json` (metadata), `prompts.json` (static dialogue categories), `dynamics.json` (dynamic templates), `weights.json` (category weights), `decorations.json` (decoration config), `emoji-keywords.json` (emoji keywords)
  - Each character can include dedicated JavaScript files (e.g. `frieren.js`)
  - Experience similar to traditional Ukagaka SHIORI architecture, defining character personalities without modifying PHP code

- **New Modules**:
  - `personality-loader.php`: Personality system loader with JSON file reading and caching mechanism
  - `emoji-mapper.php`: Emoji mapping and sentiment analysis module, automatically selects emojis based on dialogue content

- **Frontend Extensions**:
  - `ukagaka-chat.js`: Chat functionality frontend implementation
  - `ghost/Frieren/frieren-emoji.js`: Frieren-specific emoji system frontend (RO style, loaded only for Frieren personality)

- **Architecture Improvements**:
  - `prompt-categories.php` fully integrated with Personality System
  - Dynamic prompts, weight configurations, and statistic mappings can all be loaded from JSON files
  - Backward compatibility: Automatically falls back to legacy behavior if Personality System is unavailable

- **Module Loading Order Optimization**:
  - `personality-loader.php` loaded before `prompt-categories.php` (Required)
  - `emoji-mapper.php` loaded before AJAX handlers (Required)

---

## [2.3.1] - 2025-12-30

### 🔧 Improvements & Fixes

- **Terminology Update**: Changed all "春菜" to "偽春菜" (Ukagaka)
  - Updated UI text, comments, and messages in all PHP files
  - Updated Chinese documentation (USER_GUIDE.md, DEVELOPER_GUIDE.md, README.md)
  - Ensures consistency with Japanese "伺か" terminology

- **Interactive Chat Mode Improvements**:
  - Auto-talk now resumes with a 5-second delay after exiting chat mode
  - Prevents the character from immediately talking after a conversation ends

- **Decoration Click Animation**:
  - Added fade-out/fade-in animation when clicking decorations
  - Matches the visual effect of clicking the OK button for next dialogue
  - Improves overall UX fluidity

- **Late Night Mode**:
  - Added dedicated dialogue context for late night hours (02:00~06:00)
  - AI adjusts dialogue style and content during late night hours

---

## [2.3.0] - 2025-12-27

### 🚀 Major Update: Interactive Chat Mode

- **Interactive Chat Mode**: Transforms the "Change Ukagaka" button into a real-time chat interface
  - Visitors can now chat directly with the character
  - Maintains conversation history for contextual responses
  - Scrollable conversation area with automatic scrolling for long chats
  - Input box fixed at the bottom, messages scroll above

- **Dynamic Context Injection**: Smart token optimization
  - WordPress statistics (post count, comment count, PHP version, plugin count, etc.) are only added to System Prompt when relevant keywords are detected in user queries
  - Significantly reduces token usage in most conversations
  - Supports Traditional Chinese, Japanese, and English keywords
  - Example keywords: article, コメント, comment, php, wordpress, plugin, plugins, theme, etc.

- **Thinking Mode (Enabled by Default)**: Improves AI response quality
  - **Default Behavior Changed**: Supported models (Qwen3, DeepSeek) now have thinking mode enabled by default
  - **Monologue Mode**: In `ai-functions.php`, set `think = true`, AI thinks before responding
  - **Conversation Mode**: In `chat-api-handlers.php`, thinking is also enabled, context window expanded to 8192 tokens
  - **Separation Mechanism**: Thinking process completely separated from response, only response shown to users
  - **Quality Improvement**: More precise answers, especially in conversation mode
  - **Behavior Difference**:
    - **Before**: think = false, direct answer, faster but potentially less accurate, thinking may mix into response
    - **Now**: think = true, think then answer, more accurate, thinking separated from response, only shows response
  - **Supported Models**: Qwen3 (e.g. qwen3:8b), DeepSeek (e.g. deepseek), and other custom models
  - **Configurable**: Can be disabled via "Disable Thinking Mode" option in backend LLM settings

- **Character Personality Consistency**: Improved role-playing
  - Fixed System Prompt variable rendering (`{{ukagaka_display_name}}`)
  - Default System Prompt now explicitly emphasizes role-playing: "You must speak and act completely as this character, never respond as an AI or language model"
  - Chat mode uses the same backend System Prompt as monologue mode, ensuring consistency

- **Code Refactoring**: Better organization
  - Split `ajax-handlers.php` into `ajax-handlers.php` and `chat-api-handlers.php`
  - Moved multi-turn conversation API functions (`mpu_call_ai_api_with_messages`, `mpu_call_ollama_with_messages`, etc.) to dedicated module
  - Improved code maintainability and organization
  - Removed excessive code comments to keep codebase clean

- **Response Length Control**: Optimized AI responses
  - Increased AI response token limit from 200 to 300 tokens
  - Applied to all AI providers (Ollama, Gemini, OpenAI, Claude)

- **UI Improvements**:
  - Chat input box placeholder redesigned: "Click here to chat with {{name}}..."
  - Chat bubbles use different colors to distinguish user and assistant
  - Added loading animation (three bouncing dots)
  - Improved scrollbar appearance

### 📁 File Structure Updates

- **New File**: `includes/chat-api-handlers.php`
  - Dedicated module for handling multi-turn conversations (Interactive Chat Mode)
  - Contains `mpu_ajax_chat()` and all `*_with_messages()` functions

- **Modified Files**:
  - `includes/ajax-handlers.php`: Simplified, removed chat-related functions
  - `options/options_page_llm.php`: Added "Enable Interactive Chat Mode" checkbox option
  - `js/ukagaka-base.js`: Added `mpuChat()` function to handle user chat interactions
  - `mpu_style.css`: Added chat mode styles (message box scrolling, input box, chat bubbles, loading animation)

### 🧠 Technical Details

#### Dynamic Context Injection

Keywords automatically detected based on user input:

- **Statistics Keywords**: article, post, comment, コメント, category, tag, days, 運営, stats, etc.
- **System Keywords**: php, wordpress, wp version, etc.
- **Plugin Keywords**: plugin, プラグイン, 外掛, etc.
- **Theme Keywords**: theme, テーマ, 主題, author, etc.

**Benefits**:

- Save 70%+ token consumption in typical conversations
- Reduce API costs
- Faster response times

#### Thinking Mode

For supported models (Qwen3, DeepSeek), the system automatically:

1. Sets `think = true` in API requests
2. Expands context window to 8192 tokens (normal mode: 4096)
3. Separates thinking process from response content
4. Only displays the response part to users

**Detection Mechanism**:

- Checks if model name contains `qwen3`, `deepseek`, or `frieren`
- Can be disabled via backend `ollama_disable_thinking` option
- When disabled, adds `/no_think` instruction to user prompt

### 🐛 Bug Fixes

- Fixed System Prompt variable rendering issues
- Fixed chat history missing in multi-turn conversations
- Improved thinking content filtering logic (supports multiple detection methods)
- Unified time context format across monologue and chat modes

### 📚 Documentation Updates

- Updated `docs/USER_GUIDE.md`: Added Interactive Chat Mode and Thinking Mode chapters
- Updated `docs/DEVELOPER_GUIDE.md`: Added `chat-api-handlers.php` module documentation
- Updated `docs/CANVAS_CUSTOMIZATION.md`: Added Frieren exclusive decorations system
- Updated all three README files: Added screenshot references

---

## [2.2.0] - 2025-12-19

### 🚀 Major Update: Universal LLM Interface

- **Multi-AI Provider Support**: Unified interface supporting four major AI services
  - **Ollama**: Local/remote free LLM (no API Key required)
  - **Google Gemini**: Supports Gemini 2.5 Flash (recommended), Gemini 1.5 Pro, etc.
  - **OpenAI**: Supports GPT-4.1 Mini (recommended), GPT-4o, etc.
  - **Claude (Anthropic)**: Supports Claude Sonnet 4.5, Claude Haiku 4.5, Claude Opus 4.5
  - All providers use a unified settings interface, switchable at any time

- **API Key Encrypted Storage**: All API Keys automatically encrypted for secure storage
- **Connection Testing**: Added connection test buttons for all AI providers

### 🧠 System Prompt Optimization

- **XML-Structured Design**: Uses XML tags to organize System Prompt, improving LLM comprehension efficiency
  - `<character>`: Character name and core settings
  - `<knowledge_base>`: Compressed WordPress information
  - `<behavior_rules>`: Behavior rules (must_do, should_do, must_not_do)
  - `<response_style_examples>`: 70+ dialogue examples
  - `<current_context>`: Current context information

- **Context Compression Mechanism**: Automatically compresses WordPress, user, and visitor information to reduce token usage
- **Frieren-Style Example System**: Built-in 70+ actual dialogue examples covering 12 categories
  - Greeting, Casual, Time-aware, Observation
  - Magic research, Tech observation, Statistics, Memory
  - Admin comments, Unexpected reactions, BOT detection, Silence

- **Dual-Layer Architecture**:
  - **System Prompt**: Defines character style, behavior rules, and dialogue examples
  - **User Prompt**: Specific task instructions for each dialogue (corresponding to example categories)

### 🎨 Complete UI/UX Upgrade

- **Unified Card Design**: All settings pages use consistent card-based layout
- **Two-Column Layout**: Main settings page uses main content + sidebar design
  - Main content width: 55%
  - Sidebar width: 300px (fixed)
  - Sidebar includes: AI Provider links, Documentation links, General links

- **Custom Scrollbar Styles**: Added beautiful scrollbars for long text areas (System Prompt, etc.)

### 🔧 Feature Improvements

- **Page Awareness Feature Integration**: Moved "Page Awareness" settings to LLM Settings page
  - Unified management of all LLM-related settings
  - Integrated with "Use LLM to replace built-in dialogue" feature

- **AI Settings Page Simplification**: Focus on "Page Awareness" functionality
  - Retained: Language settings, Character settings, Page awareness probability, Trigger pages, AI conversation display time, First-time visitor greeting
  - Removed: AI provider selection, API Key settings, Model selection (moved to LLM Settings page)

- **Statistics Metaphor Optimization**: Restored and optimized gamified statistics metaphors
  - Demon encounters = Post count (`post_count`)
  - Max damage = Comment count (`comment_count`)
  - Skills learned = Category count (`category_count`)
  - Items used = Tag count (`tag_count`)
  - Adventure days = Days operating (`days_operating`)

### 📝 Code Optimization

- **New Functions**:
  - `mpu_build_optimized_system_prompt()`: Build System Prompt (supports variable replacement)
  - `mpu_build_prompt_categories()`: Generate User Prompt instruction categories
  - `mpu_compress_context_info()`: Compress context information
  - `mpu_get_visitor_status_text()`: Get visitor status text
  - `mpu_calculate_text_similarity()`: Calculate text similarity for anti-repetition
  - `mpu_debug_system_prompt()`: Debug System Prompt output

- **Function Refactoring**:
  - `mpu_generate_llm_dialogue()`: Uses new optimized System Prompt system
  - Removed old verbose System Prompt construction logic

- **Backward Compatibility**: Maintains support for old settings, automatically migrates setting keys

### 🐛 Bug Fixes

- Fixed statistics metaphor mappings
- Optimized text area width settings (unified to 850px)
- Fixed main menu bottom line alignment issues
- Fixed scrollbar style issues

### 📚 Documentation Updates

- Updated `USER_GUIDE.md`: Complete explanation of Universal LLM Interface and System Prompt optimization
- Updated `API_REFERENCE.md`: Added all new LLM functions documentation
- Updated `CHANGELOG.md`: Recorded all v2.2.0 updates

### 🎉 Special Update (2025-12-19)

- Changed default character from Hatsune Miku to Frieren (フリーレン) to celebrate "Sousou no Frieren" Season 2 premiere on January 16, 2026
- New installations will see Frieren as the default character
- Existing installations with the default character name still set to "初音" will automatically be updated to Frieren

---

## [2.1.7] - 2025-12-15

### 🚀 Performance Optimization

- **JavaScript File Structure Refactoring**: Merged 10 JS files into 4, reducing HTTP requests
  - `ukagaka-base.js`: Merged config + utils + ajax (base layer)
  - `ukagaka-core.js`: Merged ui + dialogue + core (core functionality)
  - `ukagaka-features.js`: Merged ai + external + events (feature modules)
  - `ukagaka-anime.js`: Kept separate (animation module)
  - All files unified with `ukagaka-` prefix naming

- **Optimize mousemove Logging**: Removed frequently triggered log records to avoid console flooding
  - Commented out log output in `mousemove` events
  - Improved debugging experience in debug mode

### 🔧 Feature Improvements

- **LLM Request Optimization**: Changed to POST method for data transmission, avoiding URL length limits
  - Use `FormData` to pass all parameters (`cur_num`, `cur_msgnum`, `last_response`, `response_history`)
  - Backend supports both POST and GET methods (backward compatible)
  - Use `wp_unslash()` to correctly handle WordPress JSON data

- **Prevent LLM Request Double-Click**: Added `cancelPrevious: true` option
  - When users rapidly click "next" multiple times, automatically cancel previous unfinished requests
  - Avoid multiple parallel requests overwriting typewriter effects

### 🐛 Error Handling Optimization

- **Canvas Animation Error Handling**: Check Canvas Manager at the start of `mpuChange` function
  - Early check for `window.mpuCanvasManager` existence
  - Avoid discovering errors after Ajax success, providing more consistent experience

- **LLM Error Visual Feedback**: Display error messages in debug mode
  - Display format: `[LLM Error: error message]`
  - Automatically switch to fallback dialogue after 2 seconds
  - In non-debug mode, directly use fallback dialogue without affecting regular users

### 📝 Other Improvements

- Unified file naming convention: All JavaScript files use `ukagaka-` prefix
  - `jquery.textarearesizer.compressed.js` → `ukagaka-textarearesizer.js`

---

## [2.1.6] - 2025-12-13

### ✨ New Features

- **WordPress Info Integration**: LLM spontaneous dialogue can now retrieve and comment on site info.
  - Integrates WordPress version, theme info (name, version, author), PHP version, site name.
  - Statistics: Post count, comment count, category count, tag count, operation days.
  - Uses transient cache mechanism (5 minutes) to improve performance.
  - Added `wordpress_info` and `statistics` prompt categories.

- **RPG Style Statistics**: Statistics use gamified terms.
  - Demon Encounters (Post Count)
  - Max Damage (Comment Count)
  - Skills Learned (Category Count)
  - Items Used (Tag Count)
  - Adventure Days (Operation Days)

- **Anti-Repetition Mechanism**: Avoids "nonsense loop" issues.
  - Tracks the last response generated by LLM.
  - Adds instructions to avoid repetition in prompts.
  - Automatically generates different casual content or remains silent.

- **Idle Detection**: Automatically pauses auto-dialogue to save resources.
  - Detects user activity (mouse, keyboard, scroll, click).
  - 60-second idle threshold (adjustable).
  - Automatically resumes when user returns.
  - Effectively saves GPU and network resources.

### 🔧 Improvements

- **LLM System Prompt Enhancement**: Adds WordPress site info as background knowledge.
- **Prompt Diversity**: Added prompts related to WordPress and statistics.
- **Performance Optimization**: Reduced unnecessary LLM requests.
- **Resource Management**: Better GPU and network resource usage control.

### 📝 Technical Details

- Added `mpu_get_wordpress_info()` function (in `includes/utility-functions.php`).
- Modified `mpu_generate_llm_dialogue()` function to integrate WordPress info.
- Added idle detection logic to frontend JavaScript (`ukagaka-core.js`).
- AJAX handler supports `last_response` parameter.

---

## [2.1.0] - 2025-11-26

### ✨ New Features

- **Configurable Typing Speed**: Added typing effect speed setting (10-200 ms/char).
- **API Key Encrypted Storage**: All API Keys encrypted using AES-256-CBC.
- **Secure File Operations**: All file read/write uses WordPress Filesystem API.
- **Directory Traversal Protection**: Validates all file paths to prevent unauthorized access.

### 🔧 Improvements

- **Status Indicator**: Configured API Keys show a green checkmark indicator.
- **Error Messages**: Improved error messages for file operations.
- **Backward Compatibility**: Supports automatic encryption of existing plaintext API Keys.

### 🔒 Security

- All API Keys encrypted using AES-256-CBC.
- File operations use WordPress Filesystem API.
- Added path validation to prevent directory traversal attacks.

---

## [2.0.0] - 2025-11-22

### 🏗️ Architecture Improvements

- **Modular Refactoring**: Split single file into 7 independent modules.
- **Main Program Slimmed**: `mp-ukagaka.php` reduced to about 85 lines.
- **Dependency Loading**: Modules loaded in order of dependency.

### ✨ New Features

- **AI Page Awareness**: Automatically generates AI comments based on article content.
- **Multi-AI Provider Support**:
  - Google Gemini (gemini-2.5-flash, gemini-2.5-pro)
  - OpenAI GPT (GPT-4o, GPT-4o-mini, GPT-3.5-turbo)
  - Anthropic Claude (Claude Sonnet 4.5)
- **First Visitor Greeting**: Displays personalized welcome message for new visitors.
- **Slimstat Integration**: Retrieves visitor source, region, etc.
- **AI Text Color**: Customizable AI response text color.
- **AI Display Duration**: Control AI message display time.

### 🔧 Improvements

- **JSON Dialogue File Support**: Added JSON format support in addition to TXT.
- **Error Handling**: More detailed error logs.
- **Performance Optimization**: Settings reading uses cache mechanism.

### 📁 Module Structure

```
includes/
├── core-functions.php      # Core functions
├── utility-functions.php   # Utility functions
├── ai-functions.php        # AI functions
├── ukagaka-functions.php   # Ukagaka management
├── ajax-handlers.php       # AJAX handlers
├── frontend-functions.php  # Frontend functions
└── admin-functions.php     # Admin functions
```

## Contributors

- **Original Author**: Ariagle _(Original site discontinued)_
- **Maintainer**: Horlicks ([MoeLog](https://www.moelog.com/))

---

**Thanks for all user support and feedback!** ❤️
