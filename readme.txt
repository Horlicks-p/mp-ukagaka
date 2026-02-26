=== MP-Ukagaka ===
Plugin Name: MP Ukagaka
Plugin URI: https://www.moelog.com/
Description: Create your own ukagakas. Supports reading dialogues from dialogs/*.txt or *.json. Added AI-powered context awareness, supporting multiple providers including Gemini, OpenAI, and Claude. API keys are stored encrypted and files are operated securely.
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 2.12.0
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
  * Supports multiple AI providers: Google Gemini, OpenAI GPT, Anthropic Claude
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
