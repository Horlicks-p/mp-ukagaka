# MP Ukagaka

A WordPress plugin for creating and displaying interactive ukagaka (伺か) characters on your blog, with AI-powered context awareness features.

[![Plugin Version](https://img.shields.io/badge/version-2.1.5-blue.svg)](https://github.com)
[![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://www.php.net/)

🌍 **Other Languages**: [繁體中文](README_zh-TW.md) | [日本語](README_ja.md)

## 📸 Screenshot

![MP Ukagaka Demo](screenshot.PNG)

_Frieren character displaying AI-generated dialogue based on article content_

## 📖 Description

MP Ukagaka allows you to create custom interactive desktop mascot characters for your WordPress site. Based on the classic MP Ukagaka plugin, this version has been completely modernized with enhanced security, performance improvements, modular architecture, and cutting-edge AI features.

### Classic Features

- **Multiple Characters**: Create and manage multiple ukagaka characters
- **Custom Dialogues**: Design custom dialog messages for each character
- **External Dialog Files**: Support for loading dialogues from TXT or JSON files
- **Auto-Talk**: Automatic message rotation with configurable intervals
- **Common Messages**: Apply messages to all characters simultaneously
- **Page Exclusion**: Control where ukagakas appear on your site
- **Multi-Language**: Supports English, Traditional Chinese, and Japanese

### 🚀 AI Context Awareness (NEW in v2.0.0)

The plugin now includes intelligent AI-powered features that analyze page content and generate personalized responses:

- **Multi-Provider AI Support**:
  - **Google Gemini**: Fast and efficient AI responses (gemini-2.5-flash, gemini-2.5-pro)
  - **OpenAI GPT**: Powerful language models (GPT-4o, GPT-4o-mini, GPT-3.5-turbo)
  - **Anthropic Claude**: Advanced reasoning capabilities (Claude Sonnet 4.5)
- **Smart Context Analysis**: Automatically extracts and analyzes page title and content
- **Configurable Triggers**: Set which pages trigger AI conversations (single posts, pages, home, etc.)
- **Probability Control**: Adjust AI response frequency (1-100%) to manage API costs
- **Customizable Personality**: Design your character's personality through system prompts
- **Visual Customization**: Customize AI conversation text color
- **Display Duration Control**: Prevent AI conversations from being overwritten by auto-talk
- **Multi-Language AI**: Generate responses in Traditional Chinese, Japanese, or English
- **First-Time Visitor Greeting**: Welcome new visitors with personalized AI greetings (requires Slimstat plugin)

### 🧪 Local LLM Support (BETA - Testing Phase)

> ⚠️ **Note**: LLM functionality is currently in **BETA testing phase**. Features may change and stability is not guaranteed.

The plugin now supports local LLM integration via Ollama, allowing you to generate dialogues without API costs:

- **Ollama Integration**: Connect to local or remote Ollama instances
- **Remote Connection Support**: Works with Cloudflare Tunnel, ngrok, or other tunneling services
- **Replace Built-in Dialogues**: Option to replace static dialogues with AI-generated content
- **No API Keys Required**: Completely free to use with your own LLM setup
- **Smart Connection Detection**: Automatically adjusts timeout settings for local vs remote connections
- **Model Support**: Compatible with various Ollama models (Qwen3, Llama, Mistral, etc.)
- **Thinking Mode Control**: Option to disable thinking mode for Qwen3 and similar models

**Setup Requirements:**

- Install and run [Ollama](https://ollama.ai/) locally or on a remote server
- Download desired models (e.g., `ollama pull qwen3:8b`)
- Configure endpoint URL in plugin settings (local: `http://localhost:11434` or remote: `https://your-domain.com`)
- For detailed setup instructions, please refer to [USER_GUIDE.md](/docs/USER_GUIDE.md)

**Current Limitations:**

- Feature is in beta testing phase
- May experience connection issues with remote setups
- Response times may vary based on model and connection type

## 🏗️ Architecture

### Modular Structure

The plugin has been refactored into a clean, modular architecture for better maintainability:

**Main Plugin File** (`mp-ukagaka.php`)

- Plugin header and metadata
- Constant definitions
- Module loader
- Activation hooks

**Core Modules** (`includes/`)

- **`core-functions.php`**: Core functionality (default options, settings management)
- **`utility-functions.php`**: Utility functions (array/string conversion, filtering, sanitization)
- **`ai-functions.php`**: AI functionality (API calls for Gemini, OpenAI, Claude, Ollama)
- **`llm-functions.php`**: Local LLM functionality (Ollama integration, connection management)
- **`ukagaka-functions.php`**: Ukagaka management (CRUD operations, message processing)
- **`ajax-handlers.php`**: AJAX handlers (next message, change character, load dialog, AI chat, visitor info)
- **`frontend-functions.php`**: Frontend functionality (HTML generation, asset loading, page display logic)
- **`admin-functions.php`**: Admin functionality (settings save, admin page, dialog file generation)

### Module Loading Order

Modules are loaded in dependency order:

1. Core functions (settings management)
2. Utility functions (helper functions)
3. AI functions (AI API calls)
4. Ukagaka functions (character management)
5. AJAX handlers (request processing)
6. Frontend functions (display logic)
7. Admin functions (admin panel)

## 🎯 Use Cases

- **Personal Blogs**: Add interactive characters that engage with your content
- **Creative Websites**: Enhance user experience with AI-powered conversations
- **Gaming Blogs**: Create character mascots that comment on game reviews
- **News Sites**: Generate contextual commentary on articles
- **Educational Sites**: Provide interactive learning companions

## 📦 Installation

1. **Download the plugin**

   - Download from this repository
   - Or clone this repository into your WordPress plugins directory

2. **Install the plugin**

   ```bash
   # Navigate to your WordPress plugins directory
   cd /path/to/wordpress/wp-content/plugins/

   # Unzip or clone the plugin
   unzip mp-ukagaka.zip
   ```

3. **Activate the plugin**

   - Go to WordPress Admin → Plugins
   - Find "MP Ukagaka" and click "Activate"

4. **Configure settings**
   - Go to **Settings → MP Ukagaka**
   - Configure general settings and create your first ukagaka character
   - (Optional) Enable AI features in the "AI 設定 (Context Awareness)" section

## ⚙️ Configuration

### Basic Setup

1. **General Settings**

   - Choose default ukagaka character
   - Enable/disable display
   - Configure auto-talk interval
   - Set page exclusion rules

2. **Create Characters**

   - Go to "春菜們" (Characters) tab
   - Add new character with custom image and dialogues
   - Configure character-specific settings

3. **Dialog Setup**
   - **Important**: All dialogues must be stored as external files (TXT or JSON format)
   - Place dialog files in the `dialogs/` folder
   - Dialog files are automatically generated when saving character settings
   - You can also manually create/edit dialog files in the `dialogs/` folder

### AI Context Awareness Setup

1. **Enable AI Features**
   - Navigate to Settings → MP Ukagaka → General Settings
   - Find the "AI 設定 (Context Awareness)" section
   - Check "啟用 AI 頁面感知"

### Local LLM (Ollama) Setup (BETA)

> ⚠️ **Warning**: This feature is in **BETA testing phase**. Use at your own risk.

1. **Install Ollama**

   - Download and install [Ollama](https://ollama.ai/) on your local machine or server
   - Start the Ollama service
   - Download a model: `ollama pull qwen3:8b` (or your preferred model)

2. **Configure Plugin Settings**

   - Navigate to Settings → MP Ukagaka → LLM 設定
   - Check "啟用 LLM (Ollama)"
   - Enter Ollama endpoint:
     - **Local**: `http://localhost:11434` (default)
     - **Remote**: `https://your-domain.com` (Cloudflare Tunnel, ngrok, etc.)
   - Enter model name (e.g., `qwen3:8b`, `llama3.2`, `mistral`)

3. **Optional Settings**

   - **Replace Built-in Dialogues**: Enable to use LLM-generated dialogues instead of static ones
   - **Disable Thinking Mode**: Recommended for Qwen3 models to improve response speed
   - **Test Connection**: Use the "測試 Ollama 連接" button to verify setup

4. **Remote Connection (Cloudflare Tunnel)**

   - Set up Cloudflare Tunnel pointing to `http://localhost:11434`
   - Use the tunnel URL as your endpoint (e.g., `https://your-domain.com`)
   - Plugin automatically detects remote connections and adjusts timeout settings

5. **Choose AI Provider**

   **Google Gemini** (Recommended for beginners):

   - Get your API key from [Google AI Studio](https://makersuite.google.com/app/apikey)
   - Select "Gemini" as provider
   - Enter your API key
   - Default model: gemini-2.5-flash

   **OpenAI GPT**:

   - Get your API key from [OpenAI Platform](https://platform.openai.com/api-keys)
   - Select "OpenAI" as provider
   - Enter your API key
   - Choose model: gpt-4o-mini (recommended), gpt-4o, or gpt-3.5-turbo

   **Anthropic Claude**:

   - Get your API key from [Anthropic Console](https://console.anthropic.com/)
   - Select "Claude" as provider
   - Enter your API key
   - Model: claude-sonnet-4-5-20250929

6. **Configure AI Settings**

   - **Language**: Choose response language (zh-TW, ja, en)
   - **System Prompt**: Define your character's personality (e.g., "你是一個傲嬌的桌面助手「春菜」。你會用簡短、帶點傲嬌的語氣評論文章內容。回應請保持在 40 字以內。")
   - **Probability**: Set AI trigger rate (1-100%, recommended: 10-30% for cost control)
   - **Trigger Pages**: Specify which pages trigger AI (e.g., "is_single" for single posts only)
   - **Text Color**: Customize AI conversation text color
   - **Display Duration**: Set how long AI messages display before auto-talk resumes (recommended: 5-10 seconds)

7. **First-Time Visitor Greeting** (Optional)

   - Enable "首次訪客打招呼" (First-time visitor greeting)
   - Configure greeting prompt
   - Requires Slimstat plugin for enhanced visitor tracking

8. **Save Settings**
   - Click "儲存" (Save) button
   - Test on a single post page to verify AI responses

## 🔧 Advanced Features

### External Dialog Files

> ⚠️ **Important**: As of version 2.1.3, the system **exclusively uses external dialog files**. All dialogues must be stored as external files in the `dialogs/` folder. Internal dialog storage has been removed.

You can load dialogues from external files (TXT or JSON format):

**TXT Format** (`dialogs/character_name.txt`):

```
對話1

對話2

對話3
```

**JSON Format** (`dialogs/character_name.json`):

```json
{
  "messages": ["對話1", "對話2", "對話3"]
}
```

### Page Triggers

Use WordPress conditional tags for AI triggers:

- `is_single` - Single post pages
- `is_page` - Static pages
- `is_home` - Home page
- `is_front_page` - Front page
- Multiple conditions: `is_single,is_page`

### System Prompt Examples

**Friendly Character**:

```
你是一個友善的桌面助手。你會用親切的語氣簡單評論文章內容，回應請保持在 30 字以內。
```

**Professional Character**:

```
You are a professional blog assistant. Provide brief, insightful commentary on the article content. Keep responses under 50 words.
```

**Playful Character**:

```
あなたは遊び心のあるデスクトップマスコットです。記事の内容を面白く、短く（40字以内）コメントしてください。
```

## 🔒 Security Features

- **CSRF Protection**: All form submissions use WordPress nonce verification
- **XSS Prevention**: Input sanitization using WordPress core functions
- **API Key Encryption**: API keys are encrypted using AES-256-CBC before storage
- **Secure File Operations**: All file I/O uses WordPress Filesystem API with path validation
- **Directory Traversal Prevention**: File paths are validated to prevent unauthorized access
- **Input Validation**: All user inputs are sanitized and validated
- **Modular Security**: Each module implements its own security checks

## 📝 File Structure

```
mp-ukagaka/
├── includes/                      # PHP Modular components
│   ├── core-functions.php        # Core functionality (settings, options)
│   ├── utility-functions.php     # Utility functions (string/array, filtering, security)
│   ├── ai-functions.php          # AI functionality (Gemini, OpenAI, Claude API calls)
│   ├── llm-functions.php         # LLM functionality (Ollama integration)
│   ├── ukagaka-functions.php     # Ukagaka management (CRUD, message processing)
│   ├── ajax-handlers.php         # AJAX handlers (all AJAX endpoints)
│   ├── frontend-functions.php    # Frontend functionality (HTML, assets, display logic)
│   └── admin-functions.php       # Admin functionality (settings save, admin pages)
├── options/                       # Admin option pages
│   ├── options.php               # Admin page framework
│   ├── options_page0.php         # General settings
│   ├── options_page1.php         # Character management
│   ├── options_page2.php         # Create new character
│   ├── options_page3.php         # Extensions
│   ├── options_page4.php         # Dialog management
│   ├── options_page_ai.php      # AI settings (Context Awareness)
│   └── options_page_llm.php      # LLM settings (Ollama) - BETA
├── dialogs/                      # Dialog files (TXT/JSON)
├── images/                       # Character images
│   └── shell/                    # Character shell images
├── languages/                    # Translation files
├── mp-ukagaka.php               # Main plugin file (module loader)
├── ukagaka-core.js              # Frontend JavaScript (core functions)
├── ukagaka-features.js          # Frontend JavaScript (features & AI)
├── ukagaka_cookie.js            # Cookie handling utilities
├── mpu_style.css                # Stylesheet
├── readme.txt                   # WordPress.org readme
└── README.md                    # This file
```

### Module Responsibilities

**`core-functions.php`**

- Default options definition
- Option retrieval and caching
- Plugin initialization

**`utility-functions.php`**

- Array/string conversion (`mpu_array2str`, `mpu_str2array`)
- Output filtering (`mpu_output_filter`, `mpu_js_filter`, `mpu_input_filter`)
- HTML encoding/decoding
- Secure file operations (`mpu_secure_file_read`, `mpu_secure_file_write`)
- API key encryption/decryption (`mpu_encrypt_api_key`, `mpu_decrypt_api_key`)

**`ai-functions.php`**

- AI API dispatcher (`mpu_call_ai_api`)
- Provider-specific API calls (Gemini, OpenAI, Claude, Ollama)
- Language instruction generation
- AI trigger condition checking

**`llm-functions.php`**

- Ollama connection management (`mpu_check_ollama_available`)
- LLM dialogue generation (`mpu_generate_llm_dialogue`)
- Remote endpoint detection (`mpu_is_remote_endpoint`)
- Dynamic timeout management (`mpu_get_ollama_timeout`)
- Endpoint URL validation (`mpu_validate_ollama_endpoint`)

**`ukagaka-functions.php`**

- Character CRUD operations
- Message array retrieval and processing
- Message code processing (e.g., `:recentpost[5]:`)
- Dialog file loading

**`ajax-handlers.php`**

- Next message handler
- Character change handler
- Settings retrieval handler
- Dialog loading handler
- AI context chat handler
- Visitor info retrieval handler (Slimstat integration)
- AI greeting handler

**`frontend-functions.php`**

- Page display condition checking
- HTML generation
- Frontend asset enqueuing
- Head script injection
- Output buffering callbacks

**`admin-functions.php`**

- Settings save handler
- Admin asset enqueuing
- Options page HTML callback
- Dialog file generation
- Admin menu registration

## 👨‍💻 Development

### Adding New Features

The modular architecture makes it easy to add new features:

1. **Core Functionality**: Add to `includes/core-functions.php`
2. **Utility Functions**: Add helpers to `includes/utility-functions.php`
3. **AJAX Endpoints**: Add handlers to `includes/ajax-handlers.php`
4. **Admin Features**: Add to `includes/admin-functions.php`
5. **Frontend Features**: Add to `includes/frontend-functions.php`

### Code Style

- Follow WordPress Coding Standards
- Use proper sanitization for all inputs
- Escape all outputs
- Add docblocks for all functions
- Use meaningful variable names

### Testing

1. Test on a staging site first
2. Verify all AJAX endpoints work correctly
3. Test with multiple AI providers
4. Verify backward compatibility
5. Check browser console for errors

## 🌍 Language Support

The plugin admin interface supports:

- English
- 繁體中文 (Traditional Chinese)
- 日本語 (Japanese)

## ❓ FAQ

### How do I control API costs?

- Set **Probability** to a low value (10-20%)
- Use faster/cheaper models (e.g., gemini-2.5-flash, gpt-4o-mini)
- Limit trigger pages (e.g., only `is_single`)
- Set minimum content length (default: 500 characters)

### Why isn't AI triggering on my pages?

1. Check that AI is enabled in settings
2. Verify API key is correct
3. Ensure page matches trigger conditions (e.g., `is_single`)
4. Check content length meets minimum (500 characters)
5. Verify probability setting (try 100% for testing)
6. Check browser console for JavaScript errors

### Can I use multiple AI providers?

Currently, you can only use one provider at a time. You can switch providers in the settings, but each character will use the selected provider.

### How do I customize the AI response style?

Edit the **System Prompt** field. This defines your character's personality and response style. Keep prompts concise (under 200 characters recommended).

### What if AI conversations get replaced by auto-talk?

Increase the **Display Duration** setting (in seconds). This controls how long AI messages stay visible before auto-talk resumes. Recommended: 5-10 seconds.

### How does first-time visitor greeting work?

The plugin uses cookies to detect first-time visitors. If Slimstat plugin is installed, it will use Slimstat's API to get enhanced visitor information (referrer, search terms, location). The greeting appears only once per visitor.

## 🐛 Troubleshooting

### AI not triggering

- Check browser console for errors
- Verify API key validity
- Test with probability set to 100%
- Ensure page content is sufficient (>500 characters)
- Check that page matches trigger conditions

### Character not displaying

- Check "顯示春菜" setting is enabled
- Verify character image path is correct
- Clear browser cache
- Check page exclusion rules
- Verify JavaScript is loading correctly

### Dialogues not loading

- Verify dialog file format (TXT or JSON)
- Check file naming matches character setting
- Ensure file is in `dialogs/` folder
- Check file permissions
- Verify file size (max 2MB)

### Module loading errors

- Check that all module files exist in `includes/` directory
- Verify file permissions are correct
- Check WordPress error logs for specific errors
- Ensure PHP version is 7.4 or higher

## 📜 Changelog

### Version 2.1.5 (2025-12-13)

**Structure Changes:**

- 📁 **REFACTOR**: Reorganized admin option pages into dedicated `options/` folder
  - All option page files (options.php, options_page*.php) now centralized in `options/` directory
  - Improved code organization and maintainability
  - Better separation of concerns between includes and options

**Enhancements:**

- ✨ **LLM**: Improved random dialogue prompt system with categorized prompts (greeting, casual, observation, contextual, interactive)
- ✨ **LLM**: Added time-aware contextual prompts (morning, afternoon, evening, late night)
- 🌍 **i18n**: Complete translation file audit and updates
- 🌍 **i18n**: Added missing translations for all error messages and success messages
- 🌍 **i18n**: All API error messages now properly internationalized
- 🔧 **i18n**: Updated translation compilation script for better .po to .mo conversion

**Improvements:**

- 🔧 **LLM**: Enhanced prompt diversity from 7 to 20+ prompts across 5 categories
- 🔧 **LLM**: More natural and contextual prompt expressions
- 🔧 **i18n**: All hardcoded strings in llm-functions.php, ai-functions.php, and ajax-handlers.php now use translation functions

### Version 2.1.4 (2025-12-11)

**Improvements:**

- ⚡ **AI**: Increased Gemini `maxOutputTokens` from 100 to 500 to prevent response truncation during context awareness processing (allows for longer, more complete responses).
- 💰 **AI**: Kept OpenAI and Claude at 100 tokens to maintain cost control.

**Bug Fixes:**

- 🐛 **UI**: Fixed "AI Dialog Text Color" input display issue in admin settings (color picker was collapsed to a line due to CSS padding conflict).

### Version 2.1.3 (2025-12-10)

**Major Changes:**

- 🔄 **BREAKING**: System now exclusively uses external dialog files (TXT/JSON format)
  - Internal dialog storage has been removed
  - All dialogues must be stored in `dialogs/` folder as external files
  - Dialog files are automatically generated when saving character settings
- 🎨 **NEW**: Complete admin UI redesign with Claude-style interface
  - Modern, clean design with warm color palette
  - Improved tab navigation and content layout
  - Better message alignment and formatting
  - Responsive design for mobile devices

**Enhancements:**

- 🔧 **IMPROVED**: Better message display consistency across all admin pages
- 🔧 **IMPROVED**: Fixed duplicate message display issue
- 🔧 **IMPROVED**: Optimized admin interface width (75% with left alignment)
- 🔧 **IMPROVED**: Removed shadows for cleaner appearance
- 🔧 **IMPROVED**: WordPress default background color restored

**Bug Fixes:**

- 🐛 **FIXED**: Message box alignment issues
- 🐛 **FIXED**: Duplicate save messages on multiple admin pages

### Version 2.1.2 (2025-12-08)

**New Features:**

- ✨ **NEW (BETA)**: Local LLM support via Ollama integration
- ✨ **NEW**: Cloudflare Tunnel and remote connection support
- ✨ **NEW**: Dynamic timeout management for local vs remote connections
- ✨ **NEW**: Automatic service availability checking

**Enhancements:**

- 🔧 **IMPROVED**: Better error messages for remote connections
- 🔧 **IMPROVED**: URL validation for Ollama endpoints
- 🔧 **IMPROVED**: Connection type detection (local/remote)

**Bug Fixes:**

- 🐛 **FIXED**: LLM enable checkbox state persistence issue

### Version 2.1.1 (2025-11-28)

**Bug Fixes:**

- 🐛 **FIXED**: CSS stability improvements for theme compatibility (Twenty Ten, etc.)
- 🐛 **FIXED**: Navigation button hover effects now work correctly across all themes
- 🐛 **FIXED**: Removed focus outline on dialog buttons (OK/Cancel)

**Enhancements:**

- 🔧 **IMPROVED**: Use Flexbox layout for better button alignment
- 🔧 **IMPROVED**: Added `!important` rules to prevent theme CSS overrides
- 🔧 **IMPROVED**: Complete CSS reset for dock elements

### Version 2.1.0 (2025-11-26)

**New Features:**

- ✨ **NEW**: Configurable typewriter speed (10-200ms per character)
- 🔒 **SECURITY**: API keys now encrypted using AES-256-CBC
- 🔒 **SECURITY**: Secure file operations using WordPress Filesystem API
- 🔒 **SECURITY**: Directory traversal prevention for file operations

**Enhancements:**

- 🔧 **IMPROVED**: Added visual indicator for configured API keys
- 🔧 **IMPROVED**: Better error messages for file operations
- 🔧 **IMPROVED**: Backward compatibility for existing plaintext API keys

### Version 2.0.0 (2025-11-22)

**Architecture Improvements:**

- ✨ **REFACTORED**: Complete modular architecture (7 modules)
- ✨ **REFACTORED**: Main plugin file reduced to ~85 lines

**New Features:**

- ✨ **NEW**: AI Context Awareness with multi-provider support (Gemini, OpenAI, Claude)
- ✨ **NEW**: First-time visitor greeting (Slimstat integration)
- ✨ **NEW**: Configurable AI text color and display duration

**Enhancements:**

- 🔧 **ENHANCED**: JSON dialog file support
- 🔧 **IMPROVED**: Better error handling and logging

## 👥 Credits

- **Original Author**: Ariagle _(原站點已停止運營)_
- **Maintainer**: Horlicks (https://www.moelog.com/)
- **Inspired by**: The classic MP Ukagaka plugin / 伺か (Ukagaka)

## 📄 License

This plugin is based on the original MP Ukagaka plugin. Please refer to the original plugin's license terms.

## 🔗 Links

- [Maintainer's Blog](https://www.moelog.com/) - 維護者部落格
- [Ukagaka on Wikipedia](http://en.wikipedia.org/wiki/Ukagaka)
- [Google AI Studio](https://makersuite.google.com/app/apikey) (Gemini API Keys)
- [OpenAI Platform](https://platform.openai.com/api-keys) (OpenAI API Keys)
- [Anthropic Console](https://console.anthropic.com/) (Claude API Keys)

## 💬 Support

For issues, questions, or feature requests:

- Visit the [Maintainer's Blog](https://www.moelog.com/)
- Check the FAQ section in WordPress admin
- Review troubleshooting section above
- Open an issue on GitHub

---

**Made with ❤ for the WordPress community**
