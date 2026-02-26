# MP Ukagaka Version History

> 📋 Update records for all versions

---

---

## [2.12.0] - 2026-02-27

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
