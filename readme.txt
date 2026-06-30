=== MP-Ukagaka ===
Plugin Name: MP Ukagaka
Plugin URI: https://www.moelog.com/
Description: Create your own ukagakas. Supports reading dialogues from dialogs/*.txt or *.json. Added AI-powered context awareness, supporting multiple providers including Gemini, OpenAI, and Claude. API keys are stored encrypted and files are operated securely.
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 2.27.4
Author: Ariagle (patched by Horlicks [https://www.moelog.com])
Author URI: https://www.moelog.com/
Contributors: horlicks, ariagle
Reviser: Horlicks
Reviser URL: https://www.moelog.com/
Tags: ukagaka, ai, context awareness, llm, ollama, gemini, openai, claude

== Preface (Please Read) ==

This plugin is an extensively expanded version based on the original WordPress plugin "MP Ukagaka" released by Ariagle over 10 years ago.

**Important Notice**: Approximately 90% of this plugin's code was developed using AI-assisted development (Vibe Coding). Although it has undergone countless rounds of debugging and improvements, there may still be unknown bugs or imperfect code structures. Please understand this risk before use.

**Demo Site**: https://www.moelog.com/

= About Character Personality Creation =

While this plugin provides the "Create New Character Personality" feature (see docs-en/GHOST_CREATE_GUIDE.md), development efforts have primarily focused on the default character "Frieren". Therefore, this feature has not been fully tested. Your understanding is appreciated.

If you simply want to use the default character "Frieren", basic dialogues are built-in and ready to use out of the box. For richer, more interactive conversations, we recommend configuring an AI model API Key. Additionally, the character memory configuration file (ghost/Frieren/system_prompt.md, containing memories from anime Season 1) is also built-in. However, please remember to replace `{{admin_nickname}}` and `{{admin_name}}` with your preferred nicknames, and update the birthday to match your settings.

= AI Model Recommendations =

This plugin supports multiple AI providers including Gemini, OpenAI, Claude, and Ollama. Based on testing, **GPT-4o Mini** offers an excellent balance between dialogue generation quality and API costs, making it a highly recommended choice.

== Description ==

Create your own ukagakas and display one of them on your blog.
You can get more information about ukagaka at [Wikipedia](http://en.wikipedia.org/wiki/Ukagaka).

This plugin provides comprehensive features to help you create and customize your own ukagakas:

* **Classic Ukagaka Features**
  * Create multiple ukagaka characters
  * Customize character images (shell) and dialog messages
  * Support external dialog files (TXT or JSON format)
  * Auto-talk functionality with configurable intervals
  * Common messages that apply to all characters
  * Page exclusion rules
  * Multiple language support
  * Canvas animation support (single image & multi-frame animation)

* **AI Context Awareness**
  * Automatically analyzes page content and generates personalized responses
  * Supports multiple AI providers: Google Gemini, OpenAI GPT, Anthropic Claude, Ollama
  * SSE streaming chat support across OpenAI, Ollama, Gemini, and Claude
  * Configurable AI response probability
  * Customizable system prompts for character personality
  * Page-specific triggers (single posts, pages, home, etc.)
  * Customizable AI conversation text color
  * Configurable AI display duration to prevent conflicts with auto-talk
  * Multi-language AI responses (Traditional Chinese, Japanese, English)
  * First-time visitor greeting (with Slimstat integration support)

* **Modular Architecture**
  * Clean, modular code structure for better maintainability
  * Separated concerns: core functions, utilities, AI, ukagaka management, AJAX, frontend, admin
  * Easy to extend and customize
  * Improved code organization and readability

Visit the [Maintainer's Blog](https://www.moelog.com/) for more information.

== Installation ==

1. Unzip archive to the '/wp-content/plugins/' directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to 'Settings → MP Ukagaka', then you can:
   * Change general settings
   * Configure AI settings (Context Awareness)
   * Design your ukagakas
   * Create new ukagaka characters
   * Set up dialog messages

Visit the [Maintainer's Blog](https://www.moelog.com/) for more information.

== Frequently Asked Questions ==

= How do I enable AI Context Awareness? =

1. Go to 'Settings → MP Ukagaka → General Settings'
2. Find the "AI Setting (Context Awareness)" section
3. Check "Enable context awareness (requires AI API Key)"
4. Select an AI provider (Gemini, OpenAI, or Claude)
5. Enter your API key for the selected provider
6. Configure other AI settings (language, system prompt, probability, etc.)
7. Click "Save" to save your settings

= What AI providers are supported? =

* **Google Gemini**: Requires Gemini API Key (get it from Google AI Studio)
  * Supported models: gemini-2.5-flash, gemini-2.5-pro, gemini-2.5-flash-lite, gemini-2.0-flash-001, gemini-1.5-flash

* **OpenAI GPT**: Requires OpenAI API Key
  * Supported models: gpt-4.1-mini-2025-04-14 (recommended), gpt-4o-mini, gpt-4o

* **Anthropic Claude**: Requires Claude API Key
  * Supported model: claude-sonnet-4-5-20250929

= How does the AI probability setting work? =

The AI probability setting (1-100%) controls the chance that an AI conversation will trigger on matching pages. For example:
* 10% = AI conversation triggers 1 out of 10 times on average
* 100% = AI conversation always triggers (useful for testing)

This helps control API costs while still providing occasional AI responses.

= Why does my AI conversation get replaced by auto-talk? =

Use the "AI Dialog Display Time (seconds)" setting to prevent this. When an AI conversation is displayed, auto-talk is automatically paused for the specified duration. After the duration ends, auto-talk resumes. Recommended value: 5-10 seconds.

= Can I use external dialog files? =

Yes! The plugin supports loading dialog messages from external files:
* Format: TXT or JSON
* Location: `dialogs/` folder in the plugin directory
* File naming: Match your ukagaka's `dialog_filename` setting

= What special codes can I use in dialog files? =

You can use special codes to display dynamic content in your dialogs:

* `:recentpost[n]:` - Display a list of the n most recent posts (as clickable links)
* `:randompost[n]:` - Display a list of n random posts (as clickable links)
* `:commenters[n]:` - Display the n most recent commenters (as clickable links if they have websites)

Example:
```
Recent post：:recentpost[3]:
Random post：:randompost[5]:
Recent commenters：:commenters[5]:

```

Special codes are processed on the server side and converted to HTML links. Both formats `:recentpost[5]:` and `(:recentpost[5]:)` are supported.

Visit the [MOELOG.COM](https://www.moelog.com/) for more information.

== Architecture ==

This plugin uses a modular architecture for better maintainability:

**Main Plugin File** (`mp-ukagaka.php`)
* Plugin header and metadata
* Module loader and activation hooks

**PHP Modules** (`includes/`)
* `core-functions.php` - Settings management
* `utility-functions.php` - Utilities, security functions (file I/O, API key encryption)
* `personality-loader.php` - Personality system loader (JSON file loading and caching)
* `ai-functions.php` - AI API calls (Gemini, OpenAI, Claude, Ollama)
* `prompt-categories.php` - Prompt categories management (integrated with Personality system)
* `llm-functions.php` - LLM functionality (Ollama integration)
* `llm-context-builder.php` - LLM context building (System Prompt construction)
* `llm-slimstat.php` - Slimstat integration for visitor statistics
* `emoji-mapper.php` - Emoji mapping and emotion analysis
* `ukagaka-functions.php` - Character management
* `llm/providers/` - AI Provider Factory (Gemini, OpenAI, Claude, Ollama)
* `tool-loop-guard.php` - Protection against LLM infinite loops
* `rest/bootstrap.php` - REST Controller registration entry
* `rest/class-mpu-rest-base.php` - Base Class (OO Architecture)
* `rest/class-mpu-rest-chat.php` - LLM Chat Endpoints (SSE Streaming)
* `rest/class-mpu-rest-ghost.php` - Personality/Init Endpoints
* `rest/class-mpu-rest-dialog.php` - Dialog Management Endpoints
* `rest/class-mpu-rest-touch.php` - Touch Interaction Endpoints
* `rest/class-mpu-rest-test.php` - Connection Test Endpoints
* `frontend-functions.php` - Frontend HTML and assets
* `admin-functions.php` - Admin settings pages

**JavaScript Modules (v2.1.7+)**
* `js/ukagaka-base.js` - Base layer (config + utils + ajax)
* `js/ukagaka-core.js` - Core functionality (ui + dialogue + character switching)
* `js/ukagaka-features.js` - Feature modules (ai + external + events)
* `js/ukagaka-anime.js` - Canvas animation manager (single image & multi-frame animation)

* `js/ukagaka-textarearesizer.js` - Textarea resizer for admin

== Changelog ==

= 2026-06-30 =
* v2.27.4
* [FIX] Checksum mismatch during normal conversation: the integrity checksum only counts `type="chat"` assistant messages, but the history window was sliced from the last N *raw* entries before filtering, so interleaved `give`/`auto_talk`/`synthetic` messages could evict an older `chat` reply and make store/verify clip different messages. The slice now normalizes, filters to checksum messages, then takes the last N — so gifts and auto-talk no longer push chat replies out of the window. All store/verify paths share this helper; short histories are byte-identical to before.

= 2026-06-29 =
* v2.27.3
* [I18N] Built-in expression-tag instruction (`mpu_build_emotion_tag_instruction`) was Traditional Chinese while every other built-in prompt is Japanese; translated to Japanese with character-neutral examples. Behavior unchanged.
* [FIX] Gift requests no longer show a false "（…通信状況が良くないみたいだ…）": the synchronous `/touch/give` had a 30s front-end timeout equal to the back-end provider's 30s timeout, so a slow-but-successful generation tripped a front-end abort. Raised the gift timeout to 45s, set retries to 0 (non-idempotent POST), and split the error branch so structured back-end errors surface instead of all reading as connection failures.
* [FIX] Checksum mismatch on gift replies: a `give` assistant reply was mislabeled `chat` by some checksum-store paths (hand-written allowlists in `class-mpu-rest-dialog.php` and `akismet-integration.php` were missing `give`), so a normal conversation containing a gift could log a checksum mismatch. Consolidated all message-type allowlists onto the shared `MPU_Chat_History_Service` normalizer so store and verify match.

= 2026-06-22 =
* v2.27.2
* [FIX] Initialization race: on slow connections an LLM reply (startup, first-visit greeting, or page-context) could be written into the main dialog box before the character and box finished initializing, so text appeared abruptly or already half-typed once visibility was released. These auto-responses now defer rendering until a shared visual-ready signal fires (one-shot latch with a 12-second fallback that force-reveals visibility only, never overriding a visitor's hidden-dialog preference), while the LLM request itself still goes out early. Each flow re-checks its competing-flow guards after the wait so a later flow is not overwritten.
* [FIX] A greeting skipped by a competing flow no longer consumes the first-visit cookie, so a later page load can retry it; page-context defensively releases its own locks when it skips.
* [FIX] `loadImages()` left the main box hidden when the last animation frame finished via `onerror` (multi-image characters whose last-completing frame failed); visibility is now released from both completion branches.
* [FIX] Gift/feeding: `/touch/give` verifies the submitted chat history before writing its integrity checksum (audit-mode no-op today; gates tampered history under future block mode), and the gift reaction holds its input lock until the typewriter finishes so a reply cannot be interrupted by a second gift or auto-talk.

= 2026-06-19 =
* v2.27.1
* [I18N] Add the gift/feeding strings to the translation catalog — the four `/touch/give` error messages, the localized history anchor (`（%sを差し出した）`), the picker label, and the carousel previous/next labels. They were missing from the `.pot`/`.po`/`.mo` files, so non-Japanese sites showed Japanese and the backend-owned "localized anchor" fell back to Japanese. The carousel nav labels are now passed through `wp_localize_script` so they are translatable instead of hardcoded. Traditional Chinese and English translations added; `.mo` files recompiled.

= 2026-06-18 =
* v2.27.0
* [FEATURE] Gift / feeding system: a 🎁 picker by the chat input lets visitors hand the character gift or food items. Each item drives an LLM reaction — food is eaten with a taste comment, gifts are accepted with thanks, and `favorite` items get an extra-delighted reaction — rendered through the existing emotion-tag/APNG pipeline. Reactions are recorded in the session observation buffer (ghost-agnostic `item` type with per-item dedupe) and chat history, so later chat can refer back to what was given. New `POST /mp-ukagaka/v1/touch/give` on `MPU_REST_Touch` with an independent rate limit, a ghost-agnostic `items.json` catalog read by `personality-items.php` (image-filename whitelisting), and a backend-owned synthetic history anchor + checksum write for front/back history parity. Frieren ships with メルクーアプリン (food) and 魔導書 (gift).
* [UI] The gift picker is a single-item carousel (prev/next buttons, arrow keys, touch swipe, position counter) with image-first thumbnails and a text fallback; the picker button gains an explicit hover state.
* [FIX] Frontend interaction lock now routes gift/decoration/touch through the core `mpuSetMessageBlocking()` channel instead of `window.mpuMessageBlocking`, so a gift can no longer be sent while chat/context holds the lock (and vice versa).

= 2026-06-16 =
* v2.26.0
* [FEATURE] Daytime nap: characters can take an after-lunch nap (default window 12:30–13:30) in addition to nighttime sleep. It is probability-based (~2–3 times a week) with a variable 30–60 minute length, and is temporary like deep sleep (refreshing keeps the character asleep). `mpu_is_deep_sleep_time()` returns true during a nap, so reduced auto-talk frequency, dream lines, touch/wake reactions, and weight adjustments all apply automatically. Added `mpu_get_daily_nap_window()` and `mpu_is_nap_time()` in `llm-context-builder.php`.
* [FEATURE] Nap flavor: `sleep_mode.json` gains a `nap_dreams` pool and `wake_reaction_prompts.nap`; the frontend wake fallback (`ukagaka-chat-wake.js`) gains nap-specific lines. The temporary-wake message distinguishes "昼寝中" from "深い眠り中".
* [CONFIG] Per-character `nap` block under `sleep_settings` in `manifest.json` (off by default; partial configs deep-merge onto defaults). Frieren ships with nap enabled (12:30–13:30, p=0.4, 30–60 min).

= 2026-06-11 =
* v2.25.7
* [REFACTOR] A-2 frontend split: boot globals and bootstrap logic moved out of `frontend-functions.php` into the enqueue flow; Frieren runtime split into `frieren.js` / `frieren-animation.js` / `frieren-interactions.js` / `frieren-decorations.js`; `ukagaka-chat.js` split into seven focused modules (history, mode, format, SSE, send, events, wake) with a zero-byte compatibility entry. Production bundle output is byte-identical to the pre-split build.
* [PERF] Frieren personality scripts are now bundled into `ghost/Frieren/dist/frieren-bundle.min.js` (about 60 KB down to 28 KB, four HTTP requests down to one). `SCRIPT_DEBUG`, non-Frieren personalities, or a missing bundle file fall back to the original per-file manifest enqueue, so third-party ghosts are unaffected.
* [FIX] SSE graceful degradation: when the server lacks php-curl, chat falls back to the synchronous endpoint automatically instead of showing visitors a streaming error.
* [FIX] Initial system bubble wait extended from 6 to 12 seconds so slow first-visit asset loads no longer show the bubble before the character appears.
* [NOTE] Custom `extend.js_area` still runs after the MP Ukagaka bootstrap but is no longer a synchronous `<head>` inline script; code that depended on `<head>`-time execution should wait for DOM ready.
* [DOCS] English documentation under `docs-en/` is now the single canonical source; `docs/` and `docs-jp/` copies were removed.

= 2026-06-10 =
* v2.25.6
* [SECURITY] API Key Encryption (AES-256-GCM): New API keys are now stored with authenticated AES-256-GCM (`mpu_enc2:` prefix). Removed the weak obfuscation fallback and the insecure site-URL-derived key path; encryption now fails closed (refuses to store) when AUTH_KEY or OpenSSL/GCM is unavailable. Legacy `mpu_enc:` / `mpu_obf:` / plaintext values still decrypt and re-encrypt to GCM on the next settings save.
* [SECURITY] LLM Prompt Debug Logging: Full system/user prompt and conversation dumps are no longer emitted by `WP_DEBUG` alone. They now require an explicit opt-in via the `MPU_DEBUG_LLM` constant or the `mpu_debug_llm_prompts` filter, preventing prompt assets and conversation PII from leaking into debug logs.

= 2026-06-10 =
* v2.25.5
* [FIX] Touch API Session Token Guard: The `/touch/decoration` and `/touch/zone` endpoints now enforce the same session token validation as the Chat system, preventing unauthorized API calls.
* [FIX] API Cache Key Integrity: Fixed `mpu_generate_cache_key()` to include `model`, `language`, and `max_tokens` in the hash computation, ensuring that configuration changes do not falsely hit stale cache entries.
* [FIX] Deep Merge for Options: Fixed the shallow merge issue in `mpu_get_option()` for nested settings like `bot_blocker` and `extend`. New default values for nested arrays are now correctly preserved during updates.

= 2026-06-09 =
* v2.25.4
* [IMPROVE] Weather rain labels now account for Open-Meteo `precipitation_sum`: tomorrow forecast labels are upgraded when 24-hour rainfall indicates stronger continuous rain, avoiding heavy accumulation being described as drizzle.
* [FIX] Current weather keeps the live WMO code instead of being upgraded by the daily total; the context still includes the day’s accumulated rainfall so the character understands real rain intensity without overstating the current moment.
* [IMPROVE] Adjusted extreme-rain wording and rewrote one Frieren bot-detection template to remove a contradictory metaphor.
* [DOCS] Updated the Gift/Feeding implementation plan with code-verified follow-up notes.

= 2026-06-08 =
* v2.25.3
* [FIX] Emotion tag leak on security-event responses: the `check-spam-event` endpoint (Turnstile, Akismet spam, Moelog Bot Blocker, bot alert, AI crawler, visitor pulse) returned raw LLM text without normalization, so supported `[tag]` markers (e.g. `[smirk]`) leaked into the dialogue box after 2.25.2 removed frontend stripping. All six event branches now route through `mpu_finalize_spam_event_response()`, which normalizes the message, stores the checksum from the cleaned text, and returns `emoji` / `emotion_tags` / `primary_emotion_*` — keeping display/history/checksum aligned (§13.2). Added `SpamEventResponseTest` for the AI-crawler path.

= 2026-06-08 =
* v2.25.2
* [FIX] Emotion tag display leak (proper fix): moved the 2.25.1 stripping from the frontend `mpu_typewriter()` boundary back into the backend response normalizer and reverted the frontend strip, so display/history/checksum text stays aligned. The normalizer now also handles common variants — inner whitespace, trailing punctuation, and full-width brackets `【tag】` / `［tag］` (e.g. `[ thinking ]`, `【thinking】`, `[thinking…]`). Still gated by the supported list; unknown tags are preserved and Markdown links are protected.

= 2026-06-06 =
* v2.25.1
* [FIX] Emotion tag display leak: supported `[tag]` markers (e.g. `[thinking]`, `[laugh]`, `[sigh]`) are now stripped from visible text at the `mpu_typewriter()` boundary, so page-awareness, first-visit greeting, bot/event responses, and fallback dialogue no longer show raw tags. APNG expression selection is unaffected (it is driven by the separate emoji field, not the display text).
* [FIX] Frieren prompt nudge: removed literal tag examples from `ghost/Frieren/emoji-keywords.json` metadata so the model is no longer encouraged to copy `[thinking]` / `[laugh]` / `[sigh]` strings into replies; the tag syntax guidance stays in `expression_tag_policy`.

= 2026-05-31 =
* v2.25.0
* [NEW] Emotion tag response pipeline: AI responses can use inline `[tag]` expression markers. The response normalizer keeps display/history/checksum/TTS text aligned while extracting emotion tags into structured data.
* [NEW] Frieren expression prompt: Frieren now declares supported emoji tags in `manifest.json`, and prompts use inline `[tag]` style instead of trailing `[表情:xxx]` instructions while retaining backward-compatible parsing.
* [IMPROVE] SSE streaming parser: split tags and think blocks are handled across chunks, Markdown link false positives are avoided, and explicit streamed emotion tags are not overwritten by keyword guessing at completion.
* [IMPROVE] Think bubble placeholders: system placeholders such as `えっと` and `何を話せばいいかな` render in the character-side think bubble instead of the main dialogue box. Touch, decoration, and initial loading flows no longer flash empty dialogue boxes or leave stale placeholder state.
* [NOTE] Ollama reasoning (`message.thinking`) integration was tested and reverted because `num_predict` is shared by reasoning and final content. The think bubble UI remains dormant until provider reasoning can be budgeted safely.

= 2026-05-29 =
* v2.24.1
* [FIX] Observation decoration names: touched decoration slugs are now resolved to readable names before observation context is injected, so characters can mention the actual touched item.
* [TOOLING] Added PHPCS baseline workflow, wired lint:phpcs into verification, aligned PHPCS with the PHP 7.4 support floor, adopted repository line-ending policy, and refreshed the baseline after EOL normalization.
* [I18N] Added English translations for previously Japanese-fallback console/UI strings, refined Japanese log wording, aligned Frieren decoration fallback dialogue, fixed the missing "を" particle in the thinking placeholder path, and recompiled all .mo catalogs.
* [DOCS] Refreshed developer documentation and restored visitor-info debug log sections.

== Screenshots ==

Visit the [Maintainer's Blog](https://www.moelog.com/) for screenshots and more information.
