=== MP-Ukagaka ===
Plugin Name: MP Ukagaka
Plugin URI: https://www.moelog.com/
Description: Create your own ukagakas. Supports reading dialogues from dialogs/*.txt or *.json. Added AI-powered context awareness, supporting multiple providers including Gemini, OpenAI, and Claude. API keys are stored encrypted and files are operated securely.
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 2.6.0
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
* `ajax-handlers.php` - AJAX endpoints
* `ajax-handlers-llm.php` - LLM-related AJAX handlers
* `chat-api-handlers.php` - Chat mode API handlers (multi-turn conversations)
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

= 2026-01-15 =
* v2.5.6
* [IMPROVE] Frontend JS Optimization: Bundled and minified frontend JavaScript
  * 87.5% reduction in HTTP requests (8 files → 1 bundle)
  * 64.5% reduction in file size using Terser minification
  * Development mode supported via `SCRIPT_DEBUG` constant
  * Use `npm run build` to rebuild the bundle
* [NEW] API Cache System: Intelligent response caching to reduce API costs
  * Uses WordPress Transient API for reliable caching
  * Configurable TTL options (30min, 1hr, 2hr, 6hr, 24hr)
  * Admin UI with cache statistics and manual clear function
  * Cache key based on provider + system prompt + user prompt hash
* [NEW] Auto Diary Feature: AI-generated diary posts based on browsing data
  * Automatic title generation with personality integration
  * Configurable publish settings, author, and signature
  * Independent AI provider settings for cost optimization
* [IMPROVE] Code Refactoring: Modularized AJAX chat handlers
  * Split ajax-chat-handlers-llm.php into context, greet, and user-chat handlers
  * Improved code organization and maintainability
* [DOCS] Updated DEVELOPER_GUIDE with new JS directory structure

= 2026-01-11 =
* v2.5.2
* [NEW] Weather Awareness: Characters can now perceive weather conditions using Open-Meteo API
  * Uses free Open-Meteo API, no API Key required
  * Weather settings can be configured in the admin panel
  * Supports dialogue adjustments based on weather conditions
* [NEW] Sleep Mode: Added sleep functionality
  * Characters can enter sleep mode during specified hours
  * Sleep time can be configured via `sleep_settings` in `manifest.json`
  * Supports deep sleep time and oversleep settings
* [IMPROVE] Enhanced Frieren Personality: Improved `system_prompt.md` and `prompts.json` for the default Frieren character
  * Expanded prompts.json for more diverse dialogue
  * Enhanced dialogue diversity and role-playing quality
* [NEW] Touch Interaction: Added touch zones functionality
  * Characters can respond to touches on different body parts (head, face, chest, legs, etc.)
  * Configured via `touchzones.json`
  * Each zone can define independent reaction dialogues
* [NEW] Expanded Emoji System: Added more emoji types for richer character expressions
* [NEW] New Decorations: Added two new decorations for Frieren
  * "Dark Dragon Horn" (暗黒竜の角)
  * "Clothes-Dissolving Potion" (服だけ溶かす薬)
  * Clicking decorations triggers related dialogues
* [IMPROVE] Code Refactoring: Improved code structure and maintainability, optimized module organization

= 2026-01-03 =
* v2.4.0
* [MAJOR] JSON Personality System: Complete character configuration system based on JSON files
  * Added `ghost/` folder structure (similar to traditional Ukagaka's ghost folder)
  * Each character can have independent configuration files (manifest.json, prompts.json, weights.json, decorations.json, etc.)
  * Characters can include custom JavaScript files (e.g., frieren.js)
  * Similar to traditional Ukagaka's SHIORI DLL architecture - define character personalities without modifying PHP code
* [NEW] Personality Loader Module: New `personality-loader.php` module for loading JSON files and caching mechanisms
* [NEW] Emoji Mapper Module: New `emoji-mapper.php` module for emotion analysis and emoji mapping based on dialogue content
* [NEW] Dynamic Emoji System: Character-specific emoji support with custom keywords and scripts
* [NEW] ZIP Upload Feature: Upload new characters as ZIP files for easy installation
* [NEW] Personality Creation Guide: New documentation (GHOST_CREATE_GUIDE.md) explaining how to create new personalities
* [IMPROVE] Architecture improvements: prompt-categories.php fully integrated with Personality system
* [IMPROVE] Dynamic prompts, weights, and statistics mappings can now be loaded from JSON files
* [IMPROVE] Backward compatibility: Automatically falls back to old behavior if Personality system is unavailable
* [IMPROVE] Module loading order optimization: personality-loader.php loads before prompt-categories.php (required)
* [IMPROVE] Code cleanup: Removed unnecessary comments from PHP files in includes/ directory

= 2025-12-27 =
* v2.3.0
* [MAJOR] Interactive Chat Mode: Transformed "Change Ukagaka" button into a real-time chat interface
  * Visitors can now directly chat with your character
  * Maintains conversation history for contextual responses
  * Scrollable chat area with automatic scrolling for long conversations
  * Input field stays fixed at bottom while messages scroll above
* [MAJOR] Dynamic Context Injection: Smart token optimization
  * WordPress statistics are only added to System Prompt when relevant keywords are detected
  * Significantly reduces token usage for most conversations
  * Supports keywords in Traditional Chinese, Japanese, and English
* [IMPROVE] Thinking Mode (Default Enabled): Enhanced AI response quality
  * Default behavior: Thinking mode is now enabled by default for supported models (Qwen3, DeepSeek)
  * Monologue mode: AI thinks before responding, improving accuracy
  * Chat mode: Thinking enabled with expanded context window (8192 tokens) for better conversation quality
  * Separation: Thinking process and response are separated - only the response is shown to users
  * Quality improvement: More accurate responses, especially in chat mode
  * Configurable: Can be disabled via "Disable Thinking Mode" option in LLM settings
* [IMPROVE] Character Personality Consistency: Improved role-playing
  * Fixed System Prompt variable rendering ({{ukagaka_display_name}})
  * Default System Prompt now explicitly emphasizes role-playing
  * Chat mode uses the same backend System Prompt as monologue mode
* [IMPROVE] Code Refactoring: Better organization
  * Split ajax-handlers.php into ajax-handlers.php and chat-api-handlers.php
  * Moved multi-turn conversation API functions to dedicated module
* [IMPROVE] Response Length Control: Optimized AI responses
  * Increased AI response token limit from 200 to 300 tokens
  * Applied to all AI providers (Ollama, Gemini, OpenAI, Claude)
* [FIX] Fixed ollama_disable_thinking key mismatch between monologue and chat mode
* [FIX] Fixed page awareness AI conflict with chat mode
* [FIX] Fixed welcome message translation in chat mode
* [MISC] Removed excessive code comments for cleaner codebase

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



== Screenshots ==

Visit the [Maintainer's Blog](https://www.moelog.com/) for screenshots and more information.
