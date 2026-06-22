# MP Ukagaka

A WordPress plugin for creating interactive ukagaka (伺か) characters with AI-powered features.

[![Plugin Version](https://img.shields.io/badge/version-2.27.2-blue.svg)](https://github.com)
[![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://www.php.net/)

🌍 **Other Languages**: [繁體中文](README_zh-TW.md) | [日本語](README_ja.md)

## 📢 Preface (Please Read)

This plugin is an extensively expanded version based on the original WordPress plugin "MP Ukagaka" released by Ariagle over 10 years ago.

> ⚠️ **Important Notice**: Approximately 90% of this plugin's code was developed using AI-assisted development (Vibe Coding). Although it has undergone countless rounds of debugging and improvements, there may still be unknown bugs or imperfect code structures. Please understand this risk before use.

📺 **Demo Site**: [https://www.moelog.com](https://www.moelog.com/)

### About Character Personality Creation

While this plugin provides the **Create New Character Personality** feature (see [GHOST_CREATE_GUIDE.md](docs-en/GHOST_CREATE_GUIDE.md)), development efforts have primarily focused on the default character "Frieren". Therefore, this feature has not been fully tested. Your understanding is appreciated.

If you simply want to use the default character "Frieren", basic dialogues are built-in and ready to use out of the box. For richer, more interactive conversations, we recommend configuring an AI model API Key. Additionally, the character memory configuration files (loading sequence: [personality.md](ghost/Frieren/personality.md), [instructions.md](ghost/Frieren/instructions.md), and then [system_prompt.md](ghost/Frieren/system_prompt.md), containing memories from anime Season 1) are also built-in. You can configure your **Admin full nickname**, **Admin short name**, and **Admin birthday** (MM-DD format, e.g., `10-18`) directly in **Settings → MP Ukagaka → General Settings**. The personality files use `{{admin_nickname}}`, `{{admin_name}}`, and `{{admin_birthday}}` as placeholders — these are automatically filled in from your backend settings at runtime, so you no longer need to manually edit the personality files or `calendar.json`. The character will also celebrate your birthday automatically based on this setting.

### AI Model Recommendations

This plugin supports multiple AI providers including Gemini, OpenAI, Claude, and Ollama. Based on testing, **GPT-4o Mini** offers an excellent balance between dialogue generation quality and API costs, making it a highly recommended choice.

## 📸 Screenshot

![MP Ukagaka Demo](screenshot.PNG)

_Frieren character displaying AI-generated dialogue based on article content_

> 💡 **More Screenshots**:
>
> - `screenshot2.PNG` - General Settings & LLM Settings pages
> - `screenshot3.PNG` - Interactive Chat Mode demo (v2.3.0 feature)

## ✨ Core Features

- **Multiple Characters**: Create and manage multiple ukagaka characters
- **AI Context Awareness**: Intelligent responses using Gemini, OpenAI, Claude, or Ollama
- **Interactive Chat Mode**: Real-time conversations with visitors, including SSE streaming responses
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

| Provider   | Cost     | Setup                                                                     |
| ---------- | -------- | ------------------------------------------------------------------------- |
| **Ollama** | Free     | Install locally or connect to remote server                               |
| **Gemini** | Paid API | Get key from [Google AI Studio](https://makersuite.google.com/app/apikey) |
| **OpenAI** | Paid API | Get key from [OpenAI Platform](https://platform.openai.com/api-keys)      |
| **Claude** | Paid API | Get key from [Anthropic Console](https://console.anthropic.com/)          |

## 📚 Documentation

For detailed information, please refer to:

- **[User Guide](docs-en/USER_GUIDE.md)** - Complete setup and configuration guide
- **[Developer Guide](docs-en/DEVELOPER_GUIDE.md)** - Architecture and development info
- **[API Reference](docs-en/API_REFERENCE.md)** - Function and hook reference
- **[Changelog](docs-en/CHANGELOG.md)** - Version history

## 🎉 What's New in v2.27.2

**Boot-time rendering race fixed**: On slow connections an LLM reply (startup, first-visit greeting, or page-context) could be written into the main dialog box before the character and box finished initializing, making text appear abruptly or already half-typed once visibility was released. These auto-responses now defer rendering until a shared visual-ready signal fires (a one-shot latch with a 12-second fallback that force-reveals visibility only — it never overrides a visitor's hidden-dialog preference), while the LLM request itself still goes out early. A greeting skipped by a competing flow no longer consumes the first-visit cookie, so a later load can retry it.

**Gift interaction hardening**: The gift/feeding endpoint now verifies submitted chat history before writing its integrity checksum, and the gift reaction keeps its input lock until the typewriter finishes, so a reply can no longer be interrupted by a second gift or auto-talk.

### v2.27.1 Highlights

**Gift / Feeding system** (v2.27.0): A new 🎁 picker by the chat input lets visitors hand the character gift or food items. Each item drives an LLM reaction — food is eaten with a taste comment, gifts are accepted with thanks, and `favorite` items get an extra-delighted reaction — rendered through the existing emotion-tag/APNG pipeline so expressions appear automatically. Reactions are recorded in the session observation buffer and chat history, so later conversation can refer back to what was given. It is built on a new `POST /mp-ukagaka/v1/touch/give` endpoint, a ghost-agnostic `ghost/<Character>/items.json` catalog, and a single-item carousel UI (image-first thumbnails with a text fallback). Frieren ships with two items: メルクーアプリン (food) and 魔導書 (gift).

**Gift string translations** (v2.27.1): The gift/feeding strings — the `/touch/give` error messages, the localized history anchor `（%sを差し出した）`, and the picker / carousel navigation labels — are now in the `.pot` / `.po` / `.mo` catalogs with Traditional Chinese and English translations, so non-Japanese sites no longer fall back to Japanese.

### v2.26.0 Highlights

**Daytime nap (after-lunch sleep)**: Characters can now take an after-lunch nap in addition to the existing nighttime sleep — probability-based (~2–3 times a week), with a variable length (30–60 minutes) inside a configurable window (default 12:30–13:30). It reuses the existing sleep machinery, so reduced auto-talk, dream lines, touch/wake reactions, and weight adjustments all apply automatically, with nap-specific dream and wake-reaction flavor. Off by default; Frieren ships with nap enabled.

### v2.25.7 Highlights

**Frontend modular split & performance**: The frontend runtime was reorganized without behavior changes — boot globals moved out of inline `<head>` scripts into the enqueue flow, the Frieren runtime split into animation/interactions/decorations modules, and `ukagaka-chat.js` split into seven focused modules with a byte-identical production bundle. Frieren's personality scripts are now bundled and minified into a single file (about 60 KB down to 28 KB, four HTTP requests down to one), with automatic per-file fallback for `SCRIPT_DEBUG` and third-party ghosts. Chat also degrades gracefully to the synchronous endpoint when the server lacks php-curl.

### v2.25.6 Highlights

**Security Hardening**: Upgraded API key encryption to authenticated AES-256-GCM (removing weak fallbacks), and gated full LLM prompt/conversation debug logging behind an explicit opt-in to prevent PII leaks.

### v2.25.5 Highlights

**Security & Stability Enhancements**: Fixed Touch API session token guard, API cache key integrity, and option deep merge issues.

### v2.25.4 Highlights

**Weather rain-label refinement**: Tomorrow's forecast rain label now uses Open-Meteo `precipitation_sum` to avoid understating heavy 24-hour rainfall as drizzle. Current weather still keeps the live WMO code, while the context adds the day’s accumulated rainfall so the character can talk about real rain intensity without overstating what is happening right now.

**Frieren reaction wording**: One bot-detection prompt was rewritten to remove a contradictory metaphor, making security-event dialogue more natural.

### v2.25.0 Highlights

**Emotion tags**: AI responses can now use inline `[tag]` expression markers. The new response normalizer keeps display/history/checksum/TTS text aligned while extracting emotion tags into structured data for REST and SSE responses.

**Frieren expression prompt**: Frieren now declares supported emoji tags in `manifest.json`, and the prompt uses the new inline tag style instead of the older trailing `[表情:xxx]` instruction. Backward-compatible parsing remains available.

**Streaming support**: SSE output now parses split emotion tags and think blocks across chunks, avoids Markdown link false positives, and preserves explicit streamed emotion choices instead of overwriting them with keyword guessing at completion.

**Think bubble placeholders**: System placeholders such as `えっと` and the initial `何を話せばいいかな` now render in the character-side think bubble instead of the main dialogue box. Touch, decoration, and initial loading flows were adjusted to avoid stale placeholder state and empty dialogue-box flashes.

**Note**: Ollama `message.thinking` integration was implemented and then reverted after testing. Ollama shares the `num_predict` budget between reasoning and final content, which caused empty or truncated replies and chat history/checksum issues. The think bubble itself ships and is actively used for the loading / placeholder UI (the initial `何を話せばいいかな`, touch / decoration thinking states). The separate LLM `<think>` inner-monologue channel that would feed character thoughts into that bubble has been shelved after testing — its code remains in the tree but no provider feeds it and no prompt asks for it, so it has no visible effect. We no longer maintain it, but the full pipeline (normalizer, SSE parser, bubble) ships intact: `DEVELOPER_GUIDE.md` → "Inner Monologue (`<think>`) Channel" documents the opt-in wiring points and pitfalls for developers experimenting with providers that can produce good short monologue.

### Previous Highlights

**Observation decoration names** (v2.24.1): Recent visitor activity resolves touched decoration slugs into readable names before prompt injection.

**Code quality workflow** (v2.24.1): Added the PHPCS baseline workflow and wired `lint:phpcs` into verification.

[View Full Changelog](docs-en/CHANGELOG.md)

## ❓ Common Questions

**Why isn't AI triggering?**

- Check API key is valid
- Verify page matches trigger conditions (e.g., `is_single`)
- Ensure probability is set (try 100% for testing)
- Check content length (\>300 characters required)

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

- **API Key Encryption**: AES-256-GCM encryption for all API keys
- **CSRF Protection**: WordPress nonce verification for all forms
- **XSS Prevention**: Input/output sanitization using WordPress core functions
- **Secure File Operations**: Path validation and WordPress Filesystem API

## 💬 Support

- Visit [MOELOG.COM](https://www.moelog.com/)
- Check [User Guide](docs-en/USER_GUIDE.md) and [FAQ](docs-en/USER_GUIDE.md#faq)
- Open an issue on GitHub

## 👥 Credits

- **Original Author**: Ariagle
- **Maintainer**: Horlicks ([MOELOG.COM](https://www.moelog.com/))
- **Inspired by**: Classic MP Ukagaka plugin / 伺か (Ukagaka)

## 📄 License

Based on the original MP Ukagaka plugin. Please refer to the original plugin's license terms.

---

**Made with ❤ for the WordPress community**
