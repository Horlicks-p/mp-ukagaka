=== MP-Ukagaka ===
Plugin Name: MP Ukagaka
Plugin URI: https://www.moelog.com/
Description: Create your own ukagakas. 支援從 dialogs/*.txt 或 *.json 讀取對話。新增 AI 頁面感知功能（Context Awareness），支援 Gemini、OpenAI、Claude 多提供商。API Key 加密存儲、安全文件操作。
Version: 2.1.5
Author: Ariagle (patched by Horlicks [https://www.moelog.com])
Author URI: https://www.moelog.com/
Reviser: Horlicks
Reviser URL: https://www.moelog.com/

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

* **AI Context Awareness (NEW in v1.7.0)**
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
2. Find the "AI 設定 (Context Awareness)" section
3. Check "啟用 AI 頁面感知"
4. Select an AI provider (Gemini, OpenAI, or Claude)
5. Enter your API key for the selected provider
6. Configure other AI settings (language, system prompt, probability, etc.)
7. Click "儲存" to save your settings

= What AI providers are supported? =

* **Google Gemini**: Requires Gemini API Key (get it from Google AI Studio)
  * Supported models: gemini-2.5-flash, gemini-2.5-pro, gemini-2.5-flash-lite, gemini-2.0-flash-001, gemini-1.5-flash

* **OpenAI GPT**: Requires OpenAI API Key
  * Supported models: gpt-4o-mini, gpt-4o, gpt-3.5-turbo

* **Anthropic Claude**: Requires Claude API Key
  * Supported model: claude-sonnet-4-5-20250929

= How does the AI probability setting work? =

The AI probability setting (1-100%) controls the chance that an AI conversation will trigger on matching pages. For example:
* 10% = AI conversation triggers 1 out of 10 times on average
* 100% = AI conversation always triggers (useful for testing)

This helps control API costs while still providing occasional AI responses.

= Why does my AI conversation get replaced by auto-talk? =

Use the "AI 對話顯示時間（秒）" setting to prevent this. When an AI conversation is displayed, auto-talk is automatically paused for the specified duration. After the duration ends, auto-talk resumes. Recommended value: 5-10 seconds.

= Can I use external dialog files? =

Yes! The plugin supports loading dialog messages from external files:
* Format: TXT or JSON
* Location: `dialogs/` folder in the plugin directory
* File naming: Match your ukagaka's `dialog_filename` setting

Visit the [Maintainer's Blog](https://www.moelog.com/) for more information.

== Architecture ==

This plugin uses a modular architecture for better maintainability:

**Main Plugin File** (`mp-ukagaka.php`)
* Plugin header and metadata
* Module loader and activation hooks

**PHP Modules** (`includes/`)
* `core-functions.php` - Settings management
* `utility-functions.php` - Utilities, security functions (file I/O, API key encryption)
* `ai-functions.php` - AI API calls (Gemini, OpenAI, Claude)
* `ukagaka-functions.php` - Character management
* `ajax-handlers.php` - AJAX endpoints
* `frontend-functions.php` - Frontend HTML and assets
* `admin-functions.php` - Admin settings pages

**JavaScript Modules**
* `ukagaka-core.js` - Core frontend functions (typewriter, storage, UI)
* `ukagaka-features.js` - Features (AI chat, auto-talk, visitor greeting)
* `ukagaka_cookie.js` - Cookie utilities

== Changelog ==

= 2025-12-13 =
* v2.1.5
* [REFACTOR] Reorganized admin option pages into dedicated options/ folder
* [ENHANCED] Improved LLM random dialogue prompt system with categorized prompts
* [ENHANCED] Added time-aware contextual prompts (morning, afternoon, evening, late night)
* [ENHANCED] Complete translation file audit and updates
* [ENHANCED] Added missing translations for all error messages and success messages
* [IMPROVED] Enhanced prompt diversity from 7 to 20+ prompts across 5 categories
* [IMPROVED] All API error messages now properly internationalized

= 2025-12-11 =
* v2.1.4
* [IMPROVED] Increased Gemini maxOutputTokens to 500 for better context awareness responses
* [FIXED] Fixed "AI Dialog Text Color" input display issue in admin settings

= 2025-12-10 =
* v2.1.3
* [CHANGE] System now exclusively uses external dialog files (TXT/JSON format)
* [NEW] Complete admin UI redesign with Claude-style interface
* [IMPROVED] Better message display consistency and layout

= 2025-11-26 =
* v2.1.0
* [NEW] Configurable typewriter speed (10-200ms per character) in settings
* [SECURITY] API keys now encrypted using AES-256-CBC before storage
* [SECURITY] Secure file operations using WordPress Filesystem API
* [SECURITY] Directory traversal prevention for all file operations
* [IMPROVED] Visual indicator showing "✓ 已設定" for configured API keys
* [IMPROVED] Better error messages for file operation failures
* [IMPROVED] Backward compatibility for existing plaintext API keys

= 2025-11-22 =
* v2.0.0
* [REFACTOR] Complete modular architecture - split into 7 components
* [REFACTOR] Main plugin file reduced from 2020 lines to ~85 lines
* [NEW] AI Context Awareness with multi-provider support (Gemini, OpenAI, Claude)
* [NEW] Configurable AI response probability, text color, display duration
* [NEW] First-time visitor greeting (with Slimstat integration)
* [NEW] JSON dialog file support
* [IMPROVED] Better error handling and logging

= 2025-10-23 =
* v1.6.1
* Complete modernization: Fetch API, WordPress AJAX API
* Security enhancements: CSRF protection, XSS prevention
* Bug fixes: Cookie handling, AJAX errors for non-logged-in users

== Screenshots ==

Visit the [Maintainer's Blog](https://www.moelog.com/) for screenshots and more information.
