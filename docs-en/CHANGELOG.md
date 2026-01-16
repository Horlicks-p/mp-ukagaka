# MP Ukagaka Version History

> 📋 Update records for all versions

---

## [2.5.6] - 2026-01-15

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

---

## [1.9.x] - Historical Versions

### 1.9.5

- Fixed compatibility issues with some themes.
- Improved dialogue display effect.

### 1.9.4

- Added auto-dialogue feature.
- Added dialogue interval setting.

### 1.9.3

- Added external dialogue file support (TXT format).
- Added multi-Ukagaka switching feature.

### 1.9.2

- Added page exclusion feature.
- Improved mobile display.

### 1.9.1

- Added fixed message feature.
- Added common session feature.

### 1.9.0

- Added click behavior setting.
- Added session order setting.

---

## [1.8.x] - Historical Versions

### 1.8.5

- Added jQuery compatibility fixes.
- Improved WordPress 5.x compatibility.

### 1.8.0

- Added multi-language support.
- Added Traditional Chinese and Japanese translations.

---

## [1.7.x] - Historical Versions

### 1.7.0

- Added Ukagaka management interface.
- Added create new Ukagaka feature.

---

## [1.6.x] - Historical Versions

### 1.6.0

- Added extensions page.
- Added custom JavaScript feature.

---

## [1.5.x] - Historical Versions

### 1.5.0

- Initial public release.
- Basic Ukagaka display feature.
- Basic dialogue feature.

---

## Upgrade Guide

### Upgrading from 1.x to 2.x

1. **Backup Settings**

   - Recommended to backup `mpu_opt` option in `wp_options` first.
2. **Upgrade Plugin**

   - Upload new version to overwrite old version.
   - Or update via WordPress Admin.
3. **Check Settings**

   - Settings are automatically preserved after upgrade.
   - Recommended to check all settings pages to confirm.
4. **Clear Cache**

   - Clear browser cache.
   - Clear WordPress cache plugin cache.

### Upgrading from 2.0.x to 2.1.x

1. **API Key Auto-Encryption**

   - Existing plaintext API Keys will be automatically encrypted on first save.
   - No manual action required.
2. **Check File Permissions**

   - Ensure `dialogs/` folder is writable.
   - WordPress Filesystem API requires appropriate permissions.

### Upgrading from 2.1.x to 2.2.0

1. **Automatic Settings Migration**

   - All existing settings will be automatically preserved and migrated.
   - AI provider settings will be automatically migrated to the LLM Settings page.
   - No manual action required.
2. **Check LLM Settings**

   - Go to **Settings** → **MP Ukagaka** → **LLM Settings**.
   - Confirm AI provider selection is correct.
   - Confirm API Key is correctly set (automatically encrypted).
   - Test connection to confirm it works.
3. **Check AI Settings**

   - Go to **Settings** → **MP Ukagaka** → **AI Settings**.
   - Confirm "Page Awareness Probability" and "Trigger Pages" settings are correct.
   - Confirm "Character Settings (System Prompt)" content is correct.
4. **Clear Cache**

   - Clear browser cache.
   - Clear WordPress cache plugin cache (if applicable).
5. **Experience New UI**

   - All settings pages have been updated with new card-based design.
   - Main settings page now includes sidebar quick links.

---

## Known Issues

### 2.1.0

- Some older PHP versions (< 7.4) may not support encryption features.
- Recommended to upgrade to PHP 7.4 or higher.

### 2.0.0

- AI features require stable internet connection.
- Some firewalls might block AI API requests.

---

## Reporting Issues

If you find issues, please provide:

1. WordPress Version
2. PHP Version
3. Plugin Version
4. Error Message (if any)
5. Browser Console Errors (Press F12 to view)

---

## Contributors

- **Original Author**: Ariagle *(Original site discontinued)*
- **Maintainer**: Horlicks ([MoeLog](https://www.moelog.com/))

---

**Thanks for all user support and feedback!** ❤️
