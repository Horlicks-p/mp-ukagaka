=== MP-Ukagaka ===
Plugin Name: MP Ukagaka
Plugin URI: https://www.moelog.com/
Description: Create your own ukagakas. Supports reading dialogues from dialogs/*.txt or *.json. Added AI-powered context awareness, supporting multiple providers including Gemini, OpenAI, and Claude. API keys are stored encrypted and files are operated securely.
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 2.23.2
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

While this plugin provides the "Create New Character Personality" feature (see docs/GHOST_CREATE_GUIDE.md), development efforts have primarily focused on the default character "Frieren". Therefore, this feature has not been fully tested. Your understanding is appreciated.

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

= 2026-05-24 =
* v2.23.2
* [FIX] Observation Buffer SPA navigation: page_view / stay_duration tracking now also starts after client-side (SPA) navigation into a single post, not only on initial single-post load. The post ID is re-detected from the DOM (body postid/page-id class, data-post-id, article[id^=post-]) with the PHP-injected mpuPageContext.postId taking priority; listing / archive / home pages are guarded against false starts.
* [FIX] Page-context auto-talk resume: removed the fragile !mpuAutoTalkTimer guard that could leave auto-talk permanently stalled in production after a page-awareness self-talk (API latency left a stale timer reference). startAutoTalk() already stops any existing timer first, so resume is now unconditional whenever auto-talk is enabled.

= 2026-05-24 =
* v2.23.1
* [IMPROVE] Language Setting Unification: Fixed ghost language setting conflicts. Prioritizes backend explicit setting > ghost manifest > final fallback Japanese (ja). Added a "Default" option to yield the choice to the ghost. Removed unused Simplified Chinese and Korean mappings.

= 2026-05-23 =
* v2.23.0
* [NEW] Observation Buffer: Added a session-scoped transient buffer for recent visitor activity, including page views, dwell time, touches, and wake events. The next user chat receives these observations as one-shot situational context; no persistent memory is written.

= 2026-05-22 =
* v2.22.1
* [NEW] Character Wake-up Complaint: When waking up from deep_sleep or oversleep, the character generates Japanese complaint lines via LLM, displayed with a typewriter effect on the frontend.
* [IMPROVE] Backend REST API & LLM Generation (class-mpu-rest-dialog.php): Added get_wake_sleep_phase() and generate_wake_reaction(). Pick reaction prompts randomly, filter think tags, handle API failure fallbacks, and lock Ollama paths.
* [IMPROVE] Frontend Chat Integration (ukagaka-chat.js): mpu_send_wake_up_request() returns Promise, uses window.mpuWakeRequestPromise to prevent double-clicks, and timeouts at 60s. Display complaints with typewriter effect and push to chat history.

= 2026-05-22 =
* v2.22.0
* [NEW] Ghost Runtime State Helper (#7 milestone): Added transient-based helper tracking frontend session runtime state (idle / thinking / speaking / chatting / sleeping / waking / tool_running / suspended / error) without clobbering options or session_start().
* [IMPROVE] Public API (runtime-state-functions.php): Whitelisted 9 states, transient key hashes session token via sha256. Added logged-in user fallback.
* [IMPROVE] REST Wiring: Integrated into /chat/user, /chat/user-stream, and wake_ghost. Ensures cleanup on exit, abort, and shutdown via register_shutdown_function().
* [TEST] Unit tests for RuntimeState: Added 8 test cases verifying state transition round-trips, security hashing, invalid tokens, and TTL clamping (60s to 900s).

= 2026-05-20 =
* v2.21.0
* [REFACTOR] JS global state encapsulation (v2.21.0 #8 milestone): frontend runtime state previously scattered across 19 file-level `let` declarations and 9 `window.*` globals is now collected into a structured `window.MPU_STATE` namespace, accessed via 31 setter/getter helper functions in js/ukagaka-base.js (plus `mpuState` const alias for 32 entries total). No algorithm / REST payload / UI behavior changes — pure structural refactor.
* [REFACTOR] 7 legacy globals fully migrated (no window.* left): __mpu_retry_count, __mpu_fallback_retry_count, mpuContextPending, mpuSettingsProcessed, mpuSettingsLoaded, mpuEnableChatMode, debugMode. window.mpuMsgList / window.mpuBaseAutoTalkInterval retained as compatibility bridges (helper dual-writes both names).
* [REFACTOR] Plan §2.3 「不搬清單」preserved: window.mpuChatHistory, window.mpuChatModeActive, window.mpuChatSessionId, window.mpuChatRequesting, mpuChatAbortController (chat shared state); mpuInfo / mpuSettings / mpuPreSettings / mpuRestUrl / mpuRestNonce / mpuL10n / mpuInitData / mpuInitParams / mpuPersonalityId (PHP localized contract); manager objects and event surfaces — all untouched.
* [BEHAVIOR] window.mpuDebugMode = true in browser console now takes effect immediately (previously was init-only-captured). New mpuIsDebugMode() helper reads both MPU_STATE.flags.debugMode and window.mpuDebugMode on every call.
* [VERIFY] npm run verify: lint + bundle + PHPUnit (27 tests / 59 assertions) all green. Manual smoke test required before release — PHPUnit does not cover JS runtime; verification path per plan §2.3 #6: auto-talk / chat / context / SSE / typewriter / wake_ghost / first-visit greeting / SPA navigation / sleep-mode interval.

= 2026-05-20 =
* v2.20.0
* [REFACTOR] utility-functions.php domain split (v2.20.0 #6 milestone): the ~1,143-line catch-all file is now five focused domain files — template-functions.php (string / output helpers), file-functions.php (secure file ops / dialogs dir), encryption-functions.php (API key encrypt/decrypt), wp-info-functions.php (WordPress environment / personality resolver), network-functions.php (HTTP / cache / rate limit / session token). utility-functions.php retains constants only (36 lines).
* [CLEANUP] Removed redundant function_exists() guards on mpu_chat_integrity_* and mpu_rest_check_rate_limit across chat-history-service, akismet-integration, chat-lock, rest-base, and rest-dialog — load order in mp-ukagaka.php already guarantees these functions exist before any caller. Behavior unchanged; reduces dead defensive code.
* [CLEANUP] Removed v2.19.2 dead code: hour-precision mpu_get_daily_deep_sleep_start() / mpu_get_daily_oversleep_end() (only wake_ghost fallback referenced them); wake_ghost() now calls _mod variants directly.
* [VERIFY] npm run verify: lint + bundle + PHPUnit (27 tests / 59 assertions) all green; no duplicate function definitions across core/.

= 2026-05-20 =
* v2.19.2
* [NEW] Sleep system minute precision: new _mod-suffix helpers (mpu_get_daily_deep_sleep_start_mod / mpu_get_daily_oversleep_end_mod) return minutes-of-day (0–1439). For [22, 23] deep_sleep_start, the daily roll picks any minute in 22:00–23:59 inclusive instead of only on the hour. Manifest schema unchanged (still authored in hours).
* [IMPROVE] mpu_is_deep_sleep_time(), mpu_is_ip_woken_today(), mpu_mark_ip_as_woken() all upgraded to minutes-of-day comparisons (cross-midnight handled via $start_mod > $end_mod OR-branch). frontend-functions.php caller upgraded to _mod to keep render boundary in sync with backend.
* [BREAKING] Cache keys take _mod suffix to force a miss against v2.19.1 hour-based values; no backward-compat read. oversleep_max_hour: 9 now means "wake by 09:59" (was "wake by 09:00 sharp") and IP-record window expanded by 59 minutes to match.
* [VERIFY] npm run verify: lint + bundle + PHPUnit (27 tests / 59 assertions) all green. Manual smoke test required for minute-precision behavior — PHPUnit does not mock set_transient / random_int / DateTimeImmutable.

= 2026-05-19 =
* v2.19.1
* [NEW] Frieren dynamic deep_sleep_start: manifest accepts [start, end] hour-range arrays; new mpu_get_daily_deep_sleep_start() rolls once per day (transient cached until midnight) to prevent refresh-induced "sudden sleep" glitches. Array safety: min/max range correction, count !== 2 warning + fallback, explicit 24 → 0 cross-day handling.
* [IMPROVE] Frieren oversleep_probability raised to 1.0 — wakes randomly between 07:00–09:00 every day (was 50% chance).
* [IMPROVE] Frieren deep_sleep_start = [22, 23] — random hour each day instead of fixed 23:00.
* [IMPROVE] mpu_is_deep_sleep_time() and wake_ghost() call sites now route through the new daily roll function (wake_ghost retains function_exists guard for load-order safety).
* [VERIFY] npm run verify: lint + bundle + PHPUnit (27 tests / 59 assertions) all green. New function behavior should be verified via manual smoke test (PHPUnit mocks do not cover transients / wp_date / random_int).

= 2026-05-19 =
* v2.19.0
* [REFACTOR] Core class type hints (v2.19 #5 milestone): added PHP 7.4-compatible type hints to MPU_Session_Event (2 methods), MPU_Input_Role (5 methods), MPU_REST_Base (rate_limit / ok / fail), and chat-integrity.php (13 functions). check_admin() and verify_history() intentionally untyped (true|WP_Error and true|null|WP_Error cannot be expressed in PHP 7.4 without union types). Behavior unchanged — pure type annotations.
* [DOCS] Pre-audit of #6 utility-functions decomposition: confirmed chat-integrity / provider-helpers / utility-functions load before rest/bootstrap, making several function_exists() guards in REST and chat history candidate-redundant. Defenses retained this release; cleanup deferred to the decomposition PR.
* [VERIFY] npm run verify: lint + bundle + PHPUnit (27 tests / 59 assertions) all green.

= 2026-05-18 =
* v2.18.0
* [NEW] PHPUnit Testing Infrastructure: introduced tests/ with a minimal WordPress mock bootstrap and six initial test suites (22 tests / 51 assertions) covering chat-integrity, encryption, input role, session event, template render, and chat lock. composer dev dependencies live under tools/php/.
* [NEW] verify pipeline: npm run verify now chains lint:php + build + test:php; build.js exits non-zero on minify failure so CI no longer silently passes.
* [NEW] Chat Lifecycle Lock: MPU_Chat_Lock uses add_option-based atomic check-and-set (not transient — transient is not atomic under parallel requests) with 60s TTL via mpu_chat_lock_ttl filter, token-validated release (hash_equals), expired-lock retry, and three action hooks (mpu_chat_lock_acquired / released / conflict). Wired only to /chat/user and /chat/user-stream; conflict returns HTTP 429 via the existing fail() envelope.
* [NEW] SSE-safe lock release: register_shutdown_function() + connection_aborted() check between SSE chunks so client disconnects mid-stream still release the lock; token validation prevents shutdown fallback from clobbering a later request's lock.
* [IMPROVE] REST chat dedup: extracted prepare_auto_chat_context() helper shared between chat_context() and chat_greet(). Response shape, checksum behavior, and prepare_user_chat_args() are unchanged.
* [IMPROVE] SSE state badge: visible .mpu-state-badge on #ukagaka_msgbox renders 6 localized states (thinking / streaming / tool / error / timeout / busy) backed by the existing data-mpu-stream-state attribute. handleStreamFailure now detects 429 lock conflicts and surfaces the busy state with a localized message.
* [IMPROVE] PHPUnit cacheResult=false avoids permission warnings; bootstrap filter/action mocks now actually execute callbacks (priority-sorted, accepted_args-aware) for reliable filter tests.

= 2026-05-15 =
* v2.17.0
* [NEW] Input Role Resolver: introduced MPU_Input_Role with admin/system/subscriber/visitor and a hardcoded ability whitelist for LLM tool boundaries.
* [NEW] Session Event Envelope: introduced MPU_Session_Event (kind + payload + eventId + ts); SSE remains backward-compatible with legacy event names.
* [NEW] Server-side Tool Gate: LLM tool exposure and tool execution are both gated by the resolved input role; all built-in abilities use the new role check with safe fallback.
* [NEW] Client-side SSE Watchdog: 45s timeout aborts the stream, rolls back the pending user message, releases the input box, and surfaces a timeout notice to prevent zombie "thinking..." states.
* [NEW] Chat Integrity Three-Tier Mode: chat_integrity_mode = audit (default) | warn | block, controllable via constant MPU_CHAT_INTEGRITY_MODE, plugin option, or the mpu_chat_integrity_mode / mpu_chat_integrity_should_block filters. Missing transients always pass through.
* [IMPROVE] REST chat now propagates the WP_Error status (e.g. 409 for integrity mismatch) instead of a hardcoded 400.
* [IMPROVE] data-mpu-stream-state attribute on #ukagaka_msgbox exposes thinking / tool / status / timeout / error states for CSS or debugging hooks.
* [SECURITY] Output escaping audit across admin settings pages; bot-blocker exempts security scanners and skips rate-limiting for logged-in requests to avoid false reputation flags.
* [FIX] Replaced an undefined MPU_PLUGIN_DIR reference with plugin_dir_path(MPU_MAIN_FILE).

= 2026-05-13 =
* v2.16.0
* [NEW] User Memory MVP: admins can type `/remember` in chat to extract stable facts from recent conversation and store them in usermeta.
* [NEW] Added AI Settings memory card for viewing and clearing saved admin memory.
* [IMPROVE] Inject saved memory into the system prompt as a non-instruction reference memo with server-side cleanup, deduplication, and throttling.
* [IMPROVE] Added `npm run verify`, REST smoke test checklists, runtime prompt/temperature refinements, and expanded Traditional Chinese/Japanese translations.

= 2026-05-08 =
* v2.15.0
* [SECURITY] Added IP-bound session token protection for public AI chat REST endpoints to reduce direct API abuse and quota drain.
* [SECURITY] Tightened raw frontend JavaScript customization to require `unfiltered_html`.
* [SECURITY] Hardened Personality ZIP overwrite with confirmation, backup/rollback, reserved ID protection, and stricter realpath boundary checks.
* [IMPROVE] Extracted chat history/checksum handling into a dedicated service and split the admin save handler into focused helpers.

= 2026-04-29 =
* v2.14.1
* [NEW] Gemini and Claude SSE Streaming: Added streaming support for Gemini streamGenerateContent and Claude Messages API content block events.
* [IMPROVE] Provider streaming stability: Added shared SSE parser, final event flushing, Claude tool-loop protection, and Gemini tool fallback to synchronous generation when MCP tools are available.
* [IMPROVE] Chat display: Streaming chat now uses a local typewriter queue that respects the admin typewriter speed setting and avoids duplicate finalization.

= 2026-04-27 =
* v2.13.8
* [NEW] Visitor Pulse & AI Crawler Signals: AI character can now react to late-night visitors, traffic spikes, and foreign countries. Added AI crawler detection (GPTBot, ClaudeBot, etc.).
* [IMPROVE] Personality Consistency: Added deep sleep window logic where new events trigger sleep_mode dream talks instead of waking up the character.
* [SECURITY] IP spoofing mitigation: Enhanced IP validation to prevent spoofing via proxy headers.

= 2026-04-25 =
* v2.13.7
* [FIX] Akismet 5.7 compatibility: AI dialogue stopped generating when Akismet 5.7 was active because its `akismet/get-stats` ability uses a JSON Schema union type (`type: ['object', 'null']`) that Gemini, OpenAI, and Claude all reject
* [FIX] Added mpu_normalize_schema_for_llm() in abilities-integration.php that recursively walks ability input schemas and converts union-type arrays to a single string before forwarding to any LLM provider
* [FIX] gotop button intercepted under SPA mode: added data-spa-ignore attribute to #toTop anchor and e.stopPropagation() to its click handler, preventing SPA frameworks from intercepting the back-to-top action

= 2026-03-18 =
* v2.13.1
* [SECURITY] Command access control: /debug_mcp, /reset, /clear now route non-admin users through AI pipeline with in-character visitor_rejection response
* [SECURITY] IP spoofing mitigation: mpu_bb_get_ip() conservative mode (proxy headers trusted only when REMOTE_ADDR is private); mpu_get_client_ip() validates proxy header IPs against public address range
* [FIX] fail() method now accepts optional 4th parameter, preserving provider error details (http_status, raw_body) in REST responses

= 2026-02-27 =
* v2.12.0
* [NEW] SSE Streaming: Implemented Real-time "Typewriter Effect" for AI responses
* [NEW] Streaming Endpoint: Added /chat/user-stream with security & rate limits
* [IMPROVE] Factory Pattern Phase 2: Completed provider logic encapsulation
* [IMPROVE] Ghost Management: Unified personality initialization via MPU_REST_Ghost
* [FIX] Connection Guard: Added ignore_user_abort to prevent ghost-talking processes

= 2026-02-26 =
* v2.10.0
* [NEW] AI Provider Factory: Object-oriented architecture for AI providers (Gemini, OpenAI, Claude, Ollama)
* [NEW] Tool Loop Guard: Protection against LLM infinite loops
* [IMPROVE] REST API Migration: Completely replaced admin-ajax.php with WordPress REST API
* [SECURITY] UTF-8 Safe JSON Encoding & precise nonce refreshing mechanism

= 2026-02-15 =
* v2.8.0
* [NEW] Abilities API (Tool Calling): Integrated WordPress Core Abilities API
  * Admin Only: Tool execution restricted to administrators
  * Visitor Optimization: Non-admin visitors save tokens by filtering tool definitions
  * Character Rejection: AI refuses unauthorized requests in character
* [SECURITY] Global Nonce Verification: Strengthened frontend AJAX security

= 2026-02-12 =
* v2.7.0
* [NEW] BOT Detection: Real-time bot monitoring and reaction system
  * Deep integration with personality system (bot_detection)
  * Triggers unique alert dialogues for search engines and malicious bots
  * Improved detection logic based on Slimstat data
* [IMPROVE] Auto-Talk Priority: Refined priority logic for spam and bot events
* [IMPROVE] LLM Prompt Expansion: Added dedicated spam and bot detection prompts

= 2026-02-01 =
* v2.6.0
* [NEW] Plugin Integrations: Akismet & Turnstile support
  * Triggers reaction dialogues when these plugins detect spam or blocks
  * Implemented cooldown mechanism (30 mins) to prevent flooding



== Screenshots ==

Visit the [Maintainer's Blog](https://www.moelog.com/) for screenshots and more information.
