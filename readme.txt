=== MP-Ukagaka ===
Plugin Name: MP Ukagaka
Plugin URI: https://www.moelog.com/
Description: Create your own ukagakas. Supports reading dialogues from dialogs/*.txt or *.json. Added AI-powered context awareness, supporting multiple providers including Gemini, OpenAI, and Claude. API keys are stored encrypted and files are operated securely.
Version: 2.2.0
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 2.2.0
Author: Ariagle (patched by Horlicks [https://www.moelog.com])
Author URI: https://www.moelog.com/
Reviser: Horlicks
Reviser URL: https://www.moelog.com/

== Special Announcement ==

🎉 To celebrate the premiere of "Sousou no Frieren" Season 2 on January 16, 2026, the default character has been changed from Hatsune Miku to Frieren (フリーレン).

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
  * Supported models: gpt-4o-mini, gpt-4o, gpt-3.5-turbo

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
* `ai-functions.php` - AI API calls (Gemini, OpenAI, Claude, Ollama)
* `prompt-categories.php` - Prompt categories management
* `llm-functions.php` - LLM functionality (Ollama integration)
* `ukagaka-functions.php` - Character management
* `ajax-handlers.php` - AJAX endpoints
* `frontend-functions.php` - Frontend HTML and assets
* `admin-functions.php` - Admin settings pages

**JavaScript Modules (v2.1.7+)**
* `js/ukagaka-base.js` - Base layer (config + utils + ajax)
* `js/ukagaka-core.js` - Core functionality (ui + dialogue + character switching)
* `js/ukagaka-features.js` - Feature modules (ai + external + events)
* `js/ukagaka-anime.js` - Canvas animation manager (single image & multi-frame animation)
* `js/ukagaka-cookie.js` - Cookie utilities
* `js/ukagaka-textarearesizer.js` - Textarea resizer for admin

== Changelog ==

= 2025-12-19 =
* v2.2.0
* [MISC] Changed default character from Hatsune Miku to Frieren (フリーレン) to celebrate "Sousou no Frieren" Season 2 premiere on January 16, 2026
* [MAJOR] Universal LLM Interface: Unified interface supporting four major AI services (Ollama, Gemini, OpenAI, Claude)
  * All providers use a unified settings interface, switchable at any time
  * API Keys automatically encrypted for secure storage
  * Added connection test buttons for all AI providers
* [MAJOR] System Prompt Optimization: XML-structured design to improve LLM comprehension efficiency
  * XML tag organization: character, knowledge_base, behavior_rules, response_style_examples, current_context
  * Context compression mechanism: automatically compresses WordPress, user, and visitor information to reduce token usage
  * Frieren-style example system: built-in 70+ actual dialogue examples covering 12 categories
  * Dual-layer architecture: System Prompt defines style, User Prompt provides task instructions
* [MAJOR] Complete UI/UX Upgrade: Unified card-based design with anime-style color scheme
  * All settings pages use consistent card-based layout
  * Inspired by Frieren website design with soft gradient backgrounds
  * Two-column layout: main content + sidebar design (main content 55%, sidebar 300px)
  * Custom scrollbar styles: added beautiful scrollbars for long text areas
* [MAJOR] Page Awareness Feature Integration: Moved "Page Awareness" settings to LLM settings page
  * Unified management of all LLM-related settings
  * Integrated with "Use LLM to replace built-in dialogue" feature
* [IMPROVE] AI Settings Page Simplification: Focus on "Page Awareness" functionality
  * Retained: Language settings, Character settings, Page awareness probability, Trigger pages, AI conversation display time, First-time visitor greeting
  * Removed: AI provider selection, API Key settings, Model selection (moved to LLM settings page)
* [IMPROVE] Statistics Metaphor Optimization: Restored and optimized gamified statistics metaphors
  * Demon encounters = Post count, Maximum damage = Comment count, Skills learned = Category count, Items used = Tag count, Adventure days = Days since launch
* [NEW] New functions: mpu_build_optimized_system_prompt, mpu_build_frieren_style_examples, mpu_build_prompt_categories, mpu_compress_context_info, mpu_get_visitor_status_text, mpu_calculate_text_similarity, mpu_debug_system_prompt
* [IMPROVE] Function refactoring: mpu_generate_llm_dialogue now uses the new optimized System Prompt system
* [IMPROVE] Backward compatibility: Maintains support for old settings, automatically migrates setting keys
* [FIX] Fixed statistics metaphor mappings, text area width settings, main menu bottom line alignment issues, scrollbar style issues

= 2025-12-15 =
* v2.1.7
* [PERF] JavaScript file structure refactoring: merged 10 files into 4, reducing HTTP requests
* [PERF] Optimized mousemove logging to avoid console flooding
* [IMPROVE] LLM requests changed to POST method, avoiding URL length limits
* [IMPROVE] Added cancelPrevious option to prevent LLM request double-click
* [FIX] Canvas animation error handling: check Canvas Manager before Ajax request
* [FIX] LLM error visual feedback in debug mode
* [MISC] Unified file naming convention with ukagaka- prefix

= 2025-12-14 =
* v2.1.6
* [NEW] Canvas animation support for multi-frame character animations
  * Automatic folder detection for animation sequences
  * Animation plays only when character is speaking (saves resources)
  * Backward compatible with single static images
  * Frame rate: 180ms per frame
  * Supported formats: PNG, JPG, JPEG, GIF, WebP
  * See docs/CANVAS_CUSTOMIZATION.md for detailed documentation
  * Visit the author's website at www.moelog.com to see how it works in action
* [NEW] WordPress information integration for LLM dialogues
  * LLM can now access WordPress version, theme info, PHP version, site statistics
  * New prompt categories: wordpress_info and statistics
  * Customizable statistics prompts with RPG-style terminology support
* [NEW] Anti-repetition mechanism to prevent repetitive idle chatter
  * Tracks previous LLM responses to avoid saying the same thing repeatedly
  * Generates unique idle comments or stays silent when no new content
* [NEW] Idle detection for auto-talk
  * Automatically pauses auto-talk when users are idle (60 seconds)
  * Tracks user activity (mouse, keyboard, scroll, clicks)
  * Saves GPU and network resources when users leave the page
* [IMPROVED] Enhanced LLM dialogue system with WordPress context awareness
* [IMPROVED] Better resource management and performance optimization



== Screenshots ==

Visit the [Maintainer's Blog](https://www.moelog.com/) for screenshots and more information.
