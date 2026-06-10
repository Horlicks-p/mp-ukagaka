=== MP-Ukagaka ===
Plugin Name: MP Ukagaka
Plugin URI: https://www.moelog.com/
Description: Create your own ukagakas. Supports reading dialogues from dialogs/*.txt or *.json. Added AI-powered context awareness, supporting multiple providers including Gemini, OpenAI, and Claude. API keys are stored encrypted and files are operated securely.
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 2.25.5
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
