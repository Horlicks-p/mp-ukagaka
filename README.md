# MP Ukagaka

A WordPress plugin for creating and displaying interactive ukagaka (伺か) characters on your blog, with AI-powered context awareness features.

[![Plugin Version](https://img.shields.io/badge/version-2.2.0-blue.svg)](https://github.com)
[![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://www.php.net/)

🌍 **Other Languages**: [繁體中文](README_zh-TW.md) | [日本語](README_ja.md)

## 🎉 Special Announcement

To celebrate the premiere of **"Sousou no Frieren" (葬送のフリーレン) Season 2** on **January 16, 2026**, the default character has been changed from **Hatsune Miku** to **Frieren (フリーレン)**.

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
- **Canvas Animation**: Support for single static images and multi-frame animations
  - Automatic folder detection for animation sequences
  - Animation plays only when character is speaking
  - Frame rate: 100ms per frame
  - Supported formats: PNG, JPG, JPEG, GIF, WebP
- **Canvas Animation**: Support for single static images and multi-frame animations

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

### 🤖 Universal LLM Interface (v2.2.0)

> 💡 **Major Update**: LLM functionality has been upgraded to a **Universal LLM Interface**, supporting multiple AI services!

The plugin now supports a unified interface for multiple AI services, allowing you to generate dialogues with:

- **Ollama** (Local/Remote): Completely free, no API Key required
  - Connect to local or remote Ollama instances
  - Remote connection support via Cloudflare Tunnel, ngrok, or other tunneling services
  - Smart connection detection with automatic timeout adjustment
  - Model support: Qwen3, Llama, Mistral, etc.
  - Thinking mode control for Qwen3 and similar models

- **Google Gemini**: Requires API Key
  - Supported models: Gemini 2.5 Flash (recommended), Gemini 1.5 Pro, etc.

- **OpenAI**: Requires API Key
  - Supported models: GPT-4.1 Mini (recommended), GPT-4o, etc.

- **Claude (Anthropic)**: Requires API Key
  - Supported models: Claude Sonnet 4.5, Claude Haiku 4.5, Claude Opus 4.5

**Key Features:**

- **Unified Settings Interface**: All providers use the same settings page
- **API Key Encryption**: All API keys are automatically encrypted for security
- **Connection Testing**: Test buttons for all AI providers
- **Replace Built-in Dialogues**: Option to replace static dialogues with AI-generated content (supports all providers)
- **Optimized System Prompt**: XML-structured system prompt with 70+ Frieren-style dialogue examples
- **WordPress Information Integration**: LLM dialogues can include site information (WordPress version, theme info, statistics)
- **Anti-Repetition Mechanism**: Prevents repetitive idle chatter by tracking previous responses
- **Idle Detection**: Automatically pauses auto-talk when users are idle (60 seconds), saving GPU and network resources

**Setup Requirements:**

- Install and run [Ollama](https://ollama.ai/) locally or on a remote server
- Download desired models (e.g., `ollama pull qwen3:8b`)
- Configure endpoint URL in plugin settings (local: `http://localhost:11434` or remote: `https://your-domain.com`)
- For detailed setup instructions, please refer to [USER_GUIDE.md](/docs-en/USER_GUIDE.md)

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
   - Find the "AI Setting (Context Awareness)" section
   - Check "Enable context awareness (requires AI API Key)"

### Universal LLM Setup

1. **Choose AI Provider**

   Navigate to **Settings → MP Ukagaka → LLM Settings** and select one of the following AI providers:

   **Ollama** (Free, no API Key required):
   - Install and run [Ollama](https://ollama.ai/) locally or on a remote server
   - Download a model: `ollama pull qwen3:8b` (or your preferred model)
   - Enter endpoint: `http://localhost:11434` (local) or `https://your-domain.com` (remote)
   - Enter model name (e.g., `qwen3:8b`, `llama3.2`, `mistral`)
   - Test connection using the "Test Ollama Connection" button

   **Google Gemini** (Recommended for beginners):
   - Get your API key from [Google AI Studio](https://makersuite.google.com/app/apikey)
   - Enter your API key (automatically encrypted)
   - Select model: Gemini 2.5 Flash (recommended), Gemini 1.5 Pro, etc.
   - Test connection using the "Test Connection" button

   **OpenAI**:
   - Get your API key from [OpenAI Platform](https://platform.openai.com/api-keys)
   - Enter your API key (automatically encrypted)
   - Select model: GPT-4.1 Mini (recommended), GPT-4o, etc.
   - Test connection using the "Test Connection" button

   **Claude (Anthropic)**:
   - Get your API key from [Anthropic Console](https://console.anthropic.com/)
   - Enter your API key (automatically encrypted)
   - Select model: Claude Sonnet 4.5 (recommended), Claude Haiku 4.5, Claude Opus 4.5
   - Test connection using the "Test Connection" button

2. **Configure LLM Settings**

   - **Replace Built-in Dialogues**: Enable to use LLM-generated dialogues instead of static ones (supports all providers)
   - **Enable Page Awareness**: Control whether page awareness function is enabled
   - **Disable Thinking Mode**: Recommended for Qwen3, DeepSeek models (Ollama only)

3. **Configure AI Settings (Page Awareness)**

   Navigate to **Settings → MP Ukagaka → AI Settings** to configure page awareness:

   - **Language**: Choose response language (zh-TW, ja, en)
   - **Character Setting (System Prompt)**: Define your character's personality
     - This setting integrates with the optimized System Prompt system
     - For cloud AI services: System Prompt is automatically optimized to reduce token usage
     - For local LLM: Can use longer prompts for better character consistency
   - **Page Awareness Probability**: Set AI trigger rate (1-100%, recommended: 10-30% for cost control)
   - **Trigger Pages**: Specify which pages trigger AI (e.g., "is_single" for single posts only)
   - **Display Duration**: Set how long AI messages display (recommended: 5-10 seconds)
   - **First-Time Visitor Greeting**: Enable and configure greeting prompt (optional)

4. **Save Settings**
   - Click "Save" button
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

### Special Codes

You can use special codes in your dialog files to display dynamic content:

| Code | Description |
|------|-------------|
| `:recentpost[n]:` | Display a list of the n most recent posts (as clickable links) |
| `:randompost[n]:` | Display a list of n random posts (as clickable links) |
| `:commenters[n]:` | Display the n most recent commenters (as clickable links if they have websites) |

**Usage Examples:**

```
Recent post：:recentpost[3]:

Random post：:randompost[5]:

Recent commenters：:commenters[5]:
```

> 📌 **Note**: Special codes are processed on the server side and converted to HTML links before being sent to the frontend. These codes work in both TXT and JSON format dialog files. The old format `(:recentpost[5]:)` with parentheses is also supported for backward compatibility.

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
├── js/                           # JavaScript files (v2.1.7+)
│   ├── ukagaka-base.js          # Base layer (config + utils + ajax)
│   ├── ukagaka-core.js          # Core functionality (ui + dialogue + character switching)
│   ├── ukagaka-features.js      # Feature modules (ai + external + events)
│   ├── ukagaka-anime.js         # Canvas animation manager
│   ├── ukagaka-cookie.js        # Cookie handling utilities
│   └── ukagaka-textarearesizer.js # Textarea resizer for admin
├── languages/                    # Translation files
├── mp-ukagaka.php               # Main plugin file (module loader)
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

Edit the **System Prompt** field. This defines your character's personality and response style.

**For cloud AI services** (Gemini, OpenAI, Claude): Keep prompts concise (under 200-300 characters recommended) to manage API costs and response speed.

**For local LLM** (Ollama): You can use longer, more detailed prompts (even 1000+ characters) as there are no API costs. Longer prompts often result in better character consistency and personality definition.

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

### 🎨 Canvas Animation (NEW in v2.1.6)

- **Single Image Support**: Backward compatible with existing single image settings
- **Multi-Frame Animation**: Automatic folder detection and frame animation playback
- **Smart Animation Control**: Animation only plays when character is speaking
- **Auto Image Loading**: Automatically loads all images from folder and plays in sequence
- **Frame Rate**: Fixed 100ms per frame for smooth animation
- **Supported Formats**: PNG, JPG, JPEG, GIF, WebP

For detailed information, see [Canvas Customization Guide](docs-en/CANVAS_CUSTOMIZATION.md).

## 📜 Changelog

### Version 2.2.0 (2025-12-19)

**🚀 Major Update: Universal LLM Interface**

- **Multi-AI Provider Support**: Unified interface supporting four AI services
  - **Ollama**: Local/remote free LLM (no API Key required)
  - **Google Gemini**: Supports Gemini 2.5 Flash (recommended), Gemini 1.5 Pro, etc.
  - **OpenAI**: Supports GPT-4.1 Mini (recommended), GPT-4o, etc.
  - **Claude (Anthropic)**: Supports Claude Sonnet 4.5, Claude Haiku 4.5, Claude Opus 4.5
  - All providers use unified settings interface, can switch anytime

- **API Key Encryption**: All API keys automatically encrypted for security
- **Connection Testing**: Test buttons for all AI providers

**🧠 System Prompt Optimization System**

- **XML Structured Design**: Uses XML tags to organize System Prompt, improving LLM comprehension
  - `<character>`: Character name and core settings
  - `<knowledge_base>`: Compressed WordPress information
  - `<behavior_rules>`: Behavior rules (must_do, should_do, must_not_do)
  - `<response_style_examples>`: 70+ dialogue examples
  - `<current_context>`: Current context information

- **Context Compression**: Automatically compresses WordPress, user, visitor information to reduce token usage
- **Frieren Style Examples System**: Built-in 70+ actual dialogue examples covering 12 categories
- **Dual-Layer Architecture**: System Prompt defines style, User Prompt provides task instructions

**🎨 UI/UX Complete Upgrade**

- **Unified Card Design**: All settings pages use consistent card-based layout
- **Two-Column Layout**: Main content + sidebar design (main content 55%, sidebar 300px)
- **Custom Scrollbar Styles**: Beautiful scrollbars for long text areas

**🔧 Feature Improvements**

- **Page Awareness Integration**: Moved "Page Awareness" setting to LLM Settings page
- **AI Settings Page Simplification**: Focused on "Page Awareness" functionality
- **Statistics Metaphor Optimization**: Restored and optimized gamified statistics metaphors

**📝 Code Optimization**

- **New Functions**: mpu_build_optimized_system_prompt, mpu_build_frieren_style_examples, mpu_build_prompt_categories, mpu_compress_context_info, mpu_get_visitor_status_text, mpu_calculate_text_similarity, mpu_debug_system_prompt
- **Function Refactoring**: mpu_generate_llm_dialogue uses new optimized System Prompt system
- **Backward Compatibility**: Maintains support for old settings, automatic migration of setting keys

**🐛 Bug Fixes**

- Fixed statistics metaphor mapping
- Optimized textarea width settings (unified to 850px)
- Fixed main menu bottom line alignment
- Fixed scrollbar style issues

**🎉 Special Update (2025-12-19)**

- Changed default character from Hatsune Miku to Frieren (フリーレン) to celebrate "Sousou no Frieren" Season 2 premiere on January 16, 2026

---

### Version 2.1.7 (2025-12-15)

**Performance Optimizations:**

- 🚀 **JavaScript File Structure Refactoring**: Merged 10 JS files into 4, reducing HTTP requests
  - `ukagaka-base.js`: Merged config + utils + ajax (base layer)
  - `ukagaka-core.js`: Merged ui + dialogue + core (core functionality)
  - `ukagaka-features.js`: Merged ai + external + events (feature modules)
  - `ukagaka-anime.js`: Kept separate (animation module)
  - All files unified with `ukagaka-` prefix naming
- 🚀 **Optimized mousemove Logging**: Removed frequently triggered log records to avoid console flooding

**Feature Improvements:**

- 🔧 **LLM Request Optimization**: Changed to POST method for data transmission, avoiding URL length limits
  - Use `FormData` to pass all parameters
  - Backend supports both POST and GET methods (backward compatible)
  - Use `wp_unslash()` to correctly handle WordPress JSON data
- 🔧 **Prevent LLM Request Double-Click**: Added `cancelPrevious: true` option
  - Automatically cancels previous unfinished requests when users rapidly click "next"
  - Avoids multiple parallel requests overwriting typewriter effects

**Error Handling Optimization:**

- 🐛 **Canvas Animation Error Handling**: Check Canvas Manager at the start of `mpuChange` function
  - Early check for `window.mpuCanvasManager` existence
  - Avoid discovering errors after Ajax success
- 🐛 **LLM Error Visual Feedback**: Display error messages in debug mode
  - Display format: `[LLM Error: error message]`
  - Automatically switch to fallback dialogue after 2 seconds

**Other Improvements:**

- 📝 Unified file naming convention: All JavaScript files use `ukagaka-` prefix
  - `jquery.textarearesizer.compressed.js` → `ukagaka-textarearesizer.js`

### Version 2.1.6 (2025-12-14)

**New Features:**

- ✨ **Canvas Animation**: Support for multi-frame character animations
  - Automatic folder detection for animation sequences
  - Animation plays only when character is speaking (saves resources)
  - Backward compatible with single static images
  - Frame rate: 180ms per frame
  - Supported formats: PNG, JPG, JPEG, GIF, WebP
  - See [Canvas Customization Guide](docs-en/CANVAS_CUSTOMIZATION.md) for details
  - Visit the author's website at [www.moelog.com](https://www.moelog.com/) to see how it works in action
- ✨ **LLM**: WordPress information integration - LLM can now access and comment on site information
  - WordPress version, theme name/version/author, PHP version, site name
  - Site statistics: post count, comment count, category count, tag count, days of operation
  - Cached information for performance (5-minute cache)
  - Customizable statistics prompts with RPG-style terminology support (see [USER_GUIDE.md](docs/USER_GUIDE.md) for details)
- ✨ **LLM**: Anti-repetition mechanism - prevents "廢話迴圈" (repetitive idle chatter) by tracking previous responses
- ✨ **Performance**: Idle detection - automatically pauses auto-talk when users are idle (60 seconds)
  - Saves GPU resources when users leave the page
  - Tracks user activity (mouse movement, keyboard, scroll, clicks)
  - Resumes automatically when user returns

**Enhancements:**

- 🔧 **LLM**: Added new prompt categories: `wordpress_info` and `statistics` for WordPress-related dialogues
- 🔧 **LLM**: Enhanced system prompt with WordPress context information
- 🔧 **Performance**: Reduced unnecessary LLM requests during user inactivity

## 👥 Credits

- **Original Author**: Ariagle _(原站點已停止運營)_
- **Maintainer**: Horlicks (<https://www.moelog.com/>)
- **Inspired by**: The classic MP Ukagaka plugin / 伺か (Ukagaka)

## 📄 License

This plugin is based on the original MP Ukagaka plugin. Please refer to the original plugin's license terms.

## 🔗 Links

- [MOELOG.COM](https://www.moelog.com/) - MOELOG.COM
- [Ukagaka on Wikipedia](http://en.wikipedia.org/wiki/Ukagaka)
- [Google AI Studio](https://makersuite.google.com/app/apikey) (Gemini API Keys)
- [OpenAI Platform](https://platform.openai.com/api-keys) (OpenAI API Keys)
- [Anthropic Console](https://console.anthropic.com/) (Claude API Keys)

## 💬 Support

For issues, questions, or feature requests:

- Visit the [MOELOG.OM](https://www.moelog.com/)
- Check the FAQ section in WordPress admin
- Review troubleshooting section above
- Open an issue on GitHub

---

**Made with ❤ for the WordPress community**
