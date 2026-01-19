# MP Ukagaka

A WordPress plugin for creating interactive ukagaka (伺か) characters with AI-powered features.

[![Plugin Version](https://img.shields.io/badge/version-2.5.6-blue.svg)](https://github.com)
[![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://www.php.net/)

🌍 **Other Languages**: [繁體中文](README_zh-TW.md) | [日本語](README_ja.md)

## 🎉 Special Announcement

To celebrate **"Sousou no Frieren" Season 2** premiere on **January 16, 2026**, the default character is now **Frieren (フリーレン)**.

## 📸 Screenshot

![MP Ukagaka Demo](screenshot.PNG)

_Frieren character displaying AI-generated dialogue based on article content_

> 💡 **More Screenshots**:
> - `screenshot2.PNG` - General Settings & LLM Settings pages
> - `screenshot3.PNG` - Interactive Chat Mode demo (v2.3.0 feature)

## ✨ Core Features

- **Multiple Characters**: Create and manage multiple ukagaka characters
- **AI Context Awareness**: Intelligent responses using Gemini, OpenAI, Claude, or Ollama
- **Interactive Chat Mode**: Real-time conversations with visitors
- **External Dialog Files**: Support for TXT and JSON format dialogues
- **Canvas Animation**: Single image or multi-frame animation support
- **Multi-Language**: English, Traditional Chinese, and Japanese
- **Security First**: API key encryption, CSRF protection, XSS prevention

## 🚀 Quick Start

### Installation

1. Download or clone this repository to `wp-content/plugins/`
2. Activate the plugin in WordPress Admin → Plugins
3. Go to **Settings → MP Ukagaka**

### Basic Setup

1. **General Settings**: Choose default character and configure display settings
2. **Create Character**: Add character with image URL and dialogues
3. **Dialog Files**: Dialogues are automatically saved to `dialogs/` folder

### Enable AI Features (Optional)

**LLM Settings**:
- Choose provider: Ollama (free), Gemini, OpenAI, or Claude
- Enter API key (automatically encrypted) or configure Ollama endpoint
- Enable "Replace Built-in Dialogues"

**AI Settings**:
- Enable "Page Awareness"
- Set trigger probability (10-30% recommended for cost control)
- Customize character personality in System Prompt

**Chat Mode**:
- Enable "Interactive Chat Mode" in General Settings
- "Change Ukagaka" button becomes a chat interface

## 🤖 AI Providers

| Provider | Cost | Setup |
|----------|------|-------|
| **Ollama** | Free | Install locally or connect to remote server |
| **Gemini** | Paid API | Get key from [Google AI Studio](https://makersuite.google.com/app/apikey) |
| **OpenAI** | Paid API | Get key from [OpenAI Platform](https://platform.openai.com/api-keys) |
| **Claude** | Paid API | Get key from [Anthropic Console](https://console.anthropic.com/) |

## 📚 Documentation

For detailed information, please refer to:

- **[User Guide](docs-en/USER_GUIDE.md)** - Complete setup and configuration guide
- **[Developer Guide](docs-en/DEVELOPER_GUIDE.md)** - Architecture and development info
- **[API Reference](docs-en/API_REFERENCE.md)** - Function and hook reference
- **[Changelog](docs-en/CHANGELOG.md)** - Version history

## 🎉 What's New in v2.5.6

**Frontend JS Optimization**: Bundled and minified frontend JavaScript for production.
  - 87.5% reduction in HTTP requests (8 files → 1 bundle)
  - 64.5% reduction in file size (Terser minification)
  - Development mode supported via `SCRIPT_DEBUG`

**API Cache System**: Reduce API costs with intelligent response caching.
  - Uses WordPress Transient API
  - Configurable TTL (30min - 24hrs)
  - Admin UI with cache statistics and clear function

**Auto Diary Feature**: AI-generated diary posts based on browsing data.
  - Custom title generation with personality integration
  - Configurable publish settings and signature

**Code Refactoring**: Modularized AJAX chat handlers for better maintainability.

[View Full Changelog](docs-en/CHANGELOG.md)

## ❓ Common Questions

**Why isn't AI triggering?**
- Check API key is valid
- Verify page matches trigger conditions (e.g., `is_single`)
- Ensure probability is set (try 100% for testing)
- Check content length (\>500 characters required)

**How to control API costs?**
- Set probability to 10-20%
- Use cheaper models (gemini-2.5-flash, gpt-4o-mini)
- Limit trigger pages to `is_single`

**LLM connection failed?**
- For Ollama: Ensure service is running on port 11434
- For remote: Check Cloudflare Tunnel or network connection
- Test connection using the test button in settings

[More FAQ in User Guide](docs-en/USER_GUIDE.md#faq)

## 🔒 Security Features

- **API Key Encryption**: AES-256-CBC encryption for all API keys
- **CSRF Protection**: WordPress nonce verification for all forms
- **XSS Prevention**: Input/output sanitization using WordPress core functions
- **Secure File Operations**: Path validation and WordPress Filesystem API

## 💬 Support

- Visit [MOELOG.COM](https://www.moelog.com/)
- Check [User Guide](docs-en/USER_GUIDE.md) and [Troubleshooting](docs-en/USER_GUIDE.md#troubleshooting)
- Open an issue on GitHub

## 👥 Credits

- **Original Author**: Ariagle
- **Maintainer**: Horlicks ([MOELOG.COM](https://www.moelog.com/))
- **Inspired by**: Classic MP Ukagaka plugin / 伺か (Ukagaka)

## 📄 License

Based on the original MP Ukagaka plugin. Please refer to the original plugin's license terms.

---

**Made with ❤ for the WordPress community**
