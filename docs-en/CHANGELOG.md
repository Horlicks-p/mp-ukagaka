# MP Ukagaka Version History

> 📋 Update records for all versions

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
  - `mpu_build_optimized_system_prompt()`: Build XML-structured System Prompt
  - `mpu_build_frieren_style_examples()`: Generate Frieren-style dialogue examples
  - `mpu_build_prompt_categories()`: Generate User Prompt instructions (corresponding to example categories)
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
