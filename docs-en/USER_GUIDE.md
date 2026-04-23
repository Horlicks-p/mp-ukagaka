# MP Ukagaka User Guide

> 🎭 Add cute Ukagaka (Desktop Mascots) to your WordPress site

---

## 📑 Table of Contents

### Part 1: Basic Settings

1. [Introduction](#introduction)
2. [Installation & Activation](#installation--activation)
3. [Quick Start](#quick-start)
4. [Basic Settings](#basic-settings)
5. [Ukagaka Management](#ukagaka-management)
6. [Custom Emoji System](#custom-emoji-system)

### Part 2: AI Features

7. [LLM Settings (AI Dialogue Engine)](#llm-settings-ai-dialogue-engine)
8. [Page Awareness Feature](#page-awareness-feature)
9. [Interactive Chat Mode](#interactive-chat-mode)
10. [Thinking Mode](#thinking-mode)
11. [Weather Awareness Feature](#weather-awareness-feature)
12. [Automated Diary Feature](#automated-diary-feature)

### Part 3: Static Dialogue Features

13. [External Dialogue Files](#external-dialogue-files)
14. [Dialogue Settings](#dialogue-settings)
15. [Special Codes](#special-codes)
16. [Extensions](#extensions)

17. [FAQ](#faq)

---

## Introduction

MP Ukagaka is a WordPress plugin that lets you display interactive desktop mascot characters (Ukagaka/Nanika) on your website. Characters can display custom dialogue messages and support AI page awareness, automatically generating comments based on article content.

### Key Features

- 🎨 **Multi-Character Support**: Create and manage multiple Ukagaka characters
- 💬 **Custom Dialogue**: Set exclusive dialogue content for each character
- 🤖 **Universal LLM Interface**: Supports Ollama, Gemini, OpenAI, and Claude AI services
- 🧠 **AI Page Awareness**: Automatically generates comments based on article content
- 🌍 **Multi-Language**: Supports Traditional Chinese, Japanese, and English
- 📁 **External Dialogue Files**: Supports TXT and JSON format dialogue files
- ⚙️ **Highly Customizable**: Typing speed, display position, styles, and more are adjustable
- 🎭 **Custom Prompt System**: Markdown-formatted System Prompt for structured character settings

---

# Part 1: Basic Settings

> Settings in this section apply regardless of whether AI features are enabled.

---

## Installation & Activation

### System Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- Modern browser with JavaScript support

### Installation Steps

1. Download the plugin ZIP file
2. Log in to WordPress Admin → **Plugins** → **Add New**
3. Click "Upload Plugin" and select the downloaded ZIP file
4. Click "Install Now"
5. After installation, click "Activate Plugin"

After activation, go to **Settings** → **MP Ukagaka** to configure.

---

## Quick Start

### 5-Minute Setup

1. **Go to Settings**: WordPress Admin → Settings → MP Ukagaka
2. **Confirm Default Ukagaka**: In "General Settings", ensure a "Default Ukagaka" is selected, then check "Default Show Ukagaka" and "Default Show Balloon"
3. **Save Settings**: Click the "Save" button
4. **Check the Result**: Go to your site's frontend — you should see the Ukagaka character in the bottom right corner

---

## Basic Settings

Go to **Settings** → **MP Ukagaka** → **General Settings**

### Display Settings

| Setting | Description |
| --- | --- |
| Default Ukagaka | Select the character to display by default |
| Default Show Ukagaka | Whether to show the character image by default |
| Default Show Balloon | Whether to show the dialogue balloon by default |

### Dialogue Settings

| Setting | Description |
| --- | --- |
| Default Session | "Random Talk" or "First Talk" |
| Session Order | Whether the next dialogue on click is "Sequential" or "Random" |
| Click Ukagaka | Action when clicking the character (Show next dialogue or No action) |

### Auto Dialogue

| Setting | Description |
| --- | --- |
| Enable Auto Dialogue | Whether to automatically rotate dialogue |
| Auto Dialogue Interval | Interval for automatic dialogue change (3-30 seconds) |
| Typing Effect Speed | Dialogue typing animation speed (10-200 ms/char) |

### Page Exclusion

In the "Don't show Ukagaka on these pages" text box, enter the URLs of pages where you don't want the Ukagaka to appear, one per line.

Supports wildcard matching: add `(*)` at the end of the URL to match all subpages.

```
/admin/
/wp-admin/(*)
/private-page/
```

### Style Settings

| Setting | Description |
| --- | --- |
| Use Custom Style | When enabled, the plugin will not load built-in CSS — you control the appearance |
| Custom Style Link | Enter `<link>` tags to load your own custom CSS stylesheet |

> 💡 This option is for advanced users. When enabled, you need to write your own styles in your **theme's CSS** or via the "Custom Style Link" field. If you don't plan to customize CSS, leave this unchecked to use the plugin's built-in styles.

```html
<link rel="stylesheet" href="https://example.com/custom-ukagaka.css" type="text/css" />
```

---

## Ukagaka Management

### View Existing Ukagaka

Go to **Settings** → **MP Ukagaka** → **Ukagaka**

On this page you can view all created Ukagaka, edit their name/image/dialogue, delete non-default Ukagaka, and set visibility.

### Install New Ukagaka (ZIP Upload)

MP Ukagaka supports installing or updating characters by uploading a ZIP file.

#### Preparing the ZIP File

The ZIP file must contain the following directly in its root directory:

- `manifest.json`: Character config file (required)
- `shell/`: Character image folder (required)
- Other files (e.g., `prompts.json`, `decorations.json`)

> ⚠️ Ensure these files and folders are in the ZIP root, not inside another subfolder.

#### Upload Steps

1. Go to **Settings** → **MP Ukagaka** → **Ukagaka**
2. Click the "**Upload ZIP**" button at the top of the page
3. Select your prepared ZIP file
4. Wait for the upload to complete — the system will automatically validate and install
5. After installation, the new character will appear in the list

### Create New Ukagaka

> 📘 **Advanced Guide**: For creating character personalities with full LLM support, refer to the [Character Creation Guide](GHOST_CREATE_GUIDE.md).

Go to **Settings** → **MP Ukagaka** → **Create New Ukagaka**

#### Required Fields

| Field | Description | Example |
| --- | --- | --- |
| Name | Name of the Ukagaka | `Frieren` |
| Image URL | Full URL of the Ukagaka image | `https://example.com/ukagaka.png` |
| Dialogue | Dialogue content, one per line | See example below |

```
Welcome to my website!
The weather is nice today~
Want to read the latest article?
Magic requires time to study slowly.
```

#### Optional Fields

| Field | Description |
| --- | --- |
| Dialogue Filename | Name of the external dialogue file (without extension) |
| Generate File | Checking this will automatically generate the corresponding dialogue file |

---

## Custom Emoji System

> 🎭 **v2.4.0 Feature**: Give each character their own set of emojis.

The emoji system automatically displays emoji images next to the character based on keywords detected in the dialogue text. **Both static dialogue and AI-generated dialogue support the emoji feature.**

### File Structure

Create the following structure in the character's folder (`ghost/CharacterID/`):

- `emojis/`: Stores emoji images (supports PNG, APNG, GIF)
- `CharacterID-emoji.js`: Emoji control script (copy `Frieren-emoji.js` and modify)
- `emoji-keywords.json`: Emoji trigger keyword settings (optional — uses built-in mappings if absent)

### Keyword Settings (`emoji-keywords.json`)

```json
{
  "_meta": {
    "description": "Character specific emoji keywords",
    "version": "1.0"
  },
  "mappings": {
    "happy": {
      "keywords": ["happy", "glad", "lol"],
      "file": "happy.png",
      "weight": 10
    },
    "angry": {
      "keywords": ["angry", "mad", "furious"],
      "file": "angry.png",
      "weight": 10
    }
  }
}
```

| Field | Description |
| --- | --- |
| **keywords** | List of keywords that trigger this emoji |
| **file** | Image filename in the `emojis/` folder |
| **weight** | Priority when multiple emojis match (higher = preferred) |

---

# Part 2: AI Features

> Settings in this section require an AI provider (or local Ollama service) to be configured.

---

## LLM Settings (AI Dialogue Engine)

Go to **Settings** → **MP Ukagaka** → **LLM Settings**

The LLM (Large Language Model) feature allows Ukagaka to generate dialogue using AI, supporting four providers:

| Provider | Cost | API Key Required |
| --- | --- | --- |
| **Ollama** (Local/Remote) | Completely free | No |
| **Google Gemini** | Usage-based | Yes ([Google AI Studio](https://makersuite.google.com/app/apikey)) |
| **OpenAI** | Usage-based | Yes ([OpenAI Platform](https://platform.openai.com/api-keys)) |
| **Claude (Anthropic)** | Usage-based | Yes ([Anthropic Console](https://console.anthropic.com/)) |

![LLM Settings Page](../screenshot4.PNG)

### Cloud AI (Gemini / OpenAI / Claude)

1. Select your provider in the **LLM Settings** page
2. Enter your API Key in the **API Key** field (automatically encrypted at rest)
3. Select a model from the dropdown:
   - **Gemini**: Gemini 2.5 Flash (Recommended — fast and cost-effective), Gemini 2.5 Pro
   - **OpenAI**: GPT-4.1 Mini (Recommended — fast and cost-effective), GPT-4o Mini, GPT-4o
   - **Claude**: Claude Sonnet 4.6 (Recommended), Claude Haiku 4.5 (Fast), Claude Opus 4.7 (Advanced)
4. Click "Test Connection" to confirm the API Key is valid

> 💡 **Security**: API Keys are stored using WordPress encryption and never saved as plaintext in the database.

> 🌍 **Multi-Language Support**: Model dropdown descriptions automatically display in your WordPress language (Traditional Chinese, English, Japanese).

### Ollama (Local/Remote)

Ollama is a free local AI — no API Key required.

**Prerequisites:**

1. Download and install from the [Ollama website](https://ollama.ai/)
2. Start the Ollama service
3. Download a model, e.g. run in terminal: `ollama pull qwen3:8b`

**Configure Endpoint:**

- **Local**: `http://localhost:11434`
- **Remote (Cloudflare Tunnel)**: `https://your-domain.com`

> 💡 The plugin automatically detects the connection type (local/remote) and adjusts timeout settings.

**Set Model Name:**

Enter the name of your downloaded model, for example:

- `qwen3:8b` (Recommended — good balance of speed and quality)
- `qwen2.5:14b` (Higher quality)
- `llama3.2`

> 💡 Use `ollama list` in the terminal to view downloaded models.

Click "Test Ollama Connection" to confirm the connection works.

#### Using Modelfile to Create Character-Specific Models (Advanced)

Ollama's Modelfile lets you embed character settings directly into the model, eliminating the need to send a System Prompt every request — significantly reducing token consumption and improving response consistency.

**Using the Example Modelfile:**

This plugin provides a Frieren character Modelfile example: `example/frieren_modelfile.example.txt`

```powershell
# Step 1: Copy the example Modelfile
Copy-Item wp-content\plugins\mp-ukagaka\example\frieren_modelfile.example.txt $HOME\frieren_modelfile
```

```powershell
# Step 2 (optional): Change the FROM line to your downloaded model
# FROM qwen3:8b
```

```powershell
# Step 3: Important! Edit the Modelfile and replace these variables:
# {{admin_nickname}} → admin's full nickname
# {{admin_name}}     → admin's short name
# If not replaced, the AI may literally say {{admin_nickname}} in conversation
```

```powershell
# Step 4: Create the model
ollama create frieren -f $HOME\frieren_modelfile

# Step 5: Test
ollama run frieren "Hello"
```

In **LLM Settings**, set the model name to `frieren` (or your custom model name).

| Method | Pros | Cons |
| --- | --- | --- |
| **Modelfile** | No token cost, consistent responses | Need to rebuild model to change |
| **Backend Settings** | Easy to modify, flexible | Costs tokens each request |

> 💡 Use Modelfile when your character settings are stable. Use backend settings while still tuning the character.

**Common Modelfile Commands:**

```powershell
ollama list                                                           # List created models
ollama rm frieren                                                     # Delete custom model
ollama rm frieren; ollama create frieren -f $HOME\frieren_modelfile  # Rebuild model
ollama show frieren                                                   # Show model info
```

### Advanced Settings

#### Use LLM to Replace Built-in Dialogue

When enabled, all Ukagaka dialogue will be generated by LLM in real-time — the static dialogue list will no longer be used.

> 💡 This feature can be enabled simultaneously with "Page Awareness". When Page Awareness conditions are met, AI comments on the article; otherwise, randomly generated LLM dialogue is used.

#### Personality (System Prompt)

This is the core character definition — it sets the character's personality, speaking style, and behavioral rules.

**Supported Formats:**

- **Plain Text** (Basic): Direct text description
- **Markdown** (Recommended): Use headings, lists, emphasis — structured and clear
- **XML Tags** (Advanced): Finer control for complex character setups

**Markdown Format Example:**

```markdown
## Role

You are the mage Frieren.

## Personality

- Speaking in a calm, slightly cold tone
- Showing more interest in magic-related topics
- Time perception differs from humans

## Dialogue Rules

- Keep responses under 50 words
- Use casual tone (no honorifics)
```

**Supported Variables (use `{{variable_name}}` for dynamic replacement in System Prompt):**

| Variable | Description |
| --- | --- |
| `{{ukagaka_display_name}}` | Character name |
| `{{language}}` | Response language (zh-TW, ja, en) |
| `{{time_context}}` | Time context (Morning, Afternoon, Evening, Late Night) |
| `{{admin_nickname}}` | Admin's full nickname |
| `{{admin_name}}` | Admin's short name |
| `{{wp_version}}` | WordPress version |
| `{{php_version}}` | PHP version |
| `{{post_count}}` | Post count |
| `{{comment_count}}` | Comment count |
| `{{days_operating}}` | Days of operation |
| `{{theme_name}}` | Theme name |
| `{{theme_version}}` | Theme version |
| `{{theme_author}}` | Theme author |

> 💡 **Length Recommendations**: Cloud AI (Gemini, OpenAI, Claude) — keep System Prompt under 500-1000 words to reduce token usage. Local LLM (Ollama) — longer prompts (1000+ words) are fine and often improve character consistency.

#### Disable Thinking Mode (Qwen3, DeepSeek, etc.)

When enabled, disables the thinking behavior of models like Qwen3 and DeepSeek, improving response efficiency and reducing response time. Recommended when using these model types.

### Ollama Remote Connection (Cloudflare Tunnel)

```bash
# Windows
cloudflared.exe service install <token>

# Linux/Mac
cloudflared service install <token>
```

After confirming the tunnel URL, enter `https://your-domain.com` in the endpoint field. Other tunnel services (ngrok, etc.) are also supported.

---

## Page Awareness Feature

Go to **Settings** → **MP Ukagaka** → **AI Settings**

Page Awareness lets Ukagaka automatically generate AI comments related to article content on specific pages. **You must first configure an AI provider in LLM Settings.**

### Settings

#### Language Settings

Select the language for AI responses: Traditional Chinese / Japanese / English

#### Character Settings (System Prompt)

The character personality used for page awareness responses. Supports the same formats and variables as [Personality (System Prompt)](#personality-system-prompt).

#### Page Awareness Probability (%)

Set the probability of triggering AI comments on matching pages (1-100%).

| Value | Effect |
| --- | --- |
| 10-30% | More natural, not too frequent |
| 50% | Balanced trigger frequency |
| 80-100% | Almost always triggers |

#### Trigger Pages

Set which page types trigger AI comments. Separate multiple conditions with commas:

| Tag | Description |
| --- | --- |
| `is_single` | Single Post Page |
| `is_page` | Static Page |
| `is_home` | Blog Home |
| `is_front_page` | Site Home |
| `is_archive` | All Archive Pages |
| `is_category` | Category Page |
| `is_tag` | Tag Page |

Example: `is_single,is_page`

#### AI Conversation Display Time (seconds)

How long AI-generated comments are shown before automatically disappearing.

| Value | Use Case |
| --- | --- |
| 5-10 seconds | Shorter comments, less disruptive |
| 10-15 seconds | Balanced |
| 15-20 seconds | Longer comments |

#### Enable First-Time Visitor Greeting

When enabled, first-time visitors receive a special greeting message.

#### First-Time Visitor Greeting Prompt

Set the greeting prompt for first-time visitors. This prompt combines with the Character Settings to generate a personalized greeting. Supports the same `{{variable_name}}` substitution as System Prompt.

Example:

```
Greet first-time visitors and briefly introduce this website.
```

#### Bot Detection

The system automatically detects crawler bots. When a bot visit is detected, it can trigger specific dialogue reactions (e.g., "Intruder detected").

![Bot Detection Example](../screenshot5.PNG)

### How Page Awareness Works

1. Visitor accesses a page matching "Trigger Pages" conditions
2. System decides whether to trigger based on "Page Awareness Probability"
3. If triggered: reads article content → combines with Character Settings → calls AI service → displays comment in dialogue balloon → disappears after "AI Conversation Display Time"

### Relationship with LLM Settings

- **LLM Settings**: Controls which AI service to use (provider, API Key, model)
- **AI Settings (Page Awareness)**: Controls when to trigger and how to display

Both can be enabled simultaneously: Page Awareness comments on articles on specific pages; LLM generates random dialogue at other times.

---

## Interactive Chat Mode

> 🎉 **v2.3.0 Feature**: Let visitors engage in real-time multi-turn conversations with Ukagaka!

### What is Interactive Chat Mode?

Interactive Chat Mode transforms the "Change Ukagaka" button into a real-time chat interface. Visitors can click to engage in actual multi-turn conversations with the character. Unlike Page Awareness (automatic article comments), Chat Mode is entirely visitor-initiated.

**An AI provider must be configured in LLM Settings before enabling.**

### How to Enable

1. Go to **Settings → MP Ukagaka → General Settings**
2. In the "💬 Dialogue Settings" section, check "**Enable Interactive Chat**"
3. Click "Save"
4. The "Change Ukagaka" button on the frontend will become a "💬 Chat" button

### How to Use

1. Click the "💬 Chat" button in the bottom right corner
2. Chat box expands, displaying a welcome message
3. Enter a message in the bottom input box, press Enter or click send
4. AI generates a response based on the input
5. Continue chatting — the system remembers previous content

Conversation history is retained on the current page only (cleared after refresh).

![Interactive Chat Mode Demo](../screenshot3.PNG)

### Slash Commands

| Command | Available To | Description |
| --- | --- | --- |
| `/help` | Everyone | Displays the list of available commands |
| `/reset` | Admin only | Clears the current chat history |
| `/clear` | Admin only | Same as `/reset` |
| `/debug_mcp` | Admin only | Displays MCP/Abilities diagnostic report |

> Admin-only commands are determined by WordPress login status. If a non-logged-in user types these commands, the character will refuse in-character.

### Chat Mode vs Page Awareness

| Feature | Interactive Chat Mode | Page Awareness Mode |
| --- | --- | --- |
| **Trigger Method** | Visitor actively clicks "Chat" | Auto-trigger (probability-based) |
| **Interactivity** | Two-way multi-turn dialogue | One-way comments |
| **Context** | Full conversation history | Analyzes current page only |
| **Token Consumption** | Accumulates per turn | Single comment |
| **Enable Location** | General Settings → Dialogue Settings | AI Settings → Page Awareness |

---

## Thinking Mode

> 🧠 **v2.3.0 Feature**: Let Ollama models think before answering to improve response quality.

### What is Thinking Mode?

Thinking Mode lets supported AI models (like Qwen3, DeepSeek) perform internal reasoning before answering. The thinking process is completely hidden from visitors — only the final response is shown.

> 💡 Cloud services like Gemini, OpenAI, and Claude don't need this setting — their thinking process is handled internally.

### Default Behavior (from v2.3.0)

Thinking Mode is **enabled by default** (`think = true`). AI thinks first then answers, ensuring output quality.

**Supported Ollama Models:**

- **Qwen3** series: `qwen3:8b`, `qwen3:14b`, etc.
- **DeepSeek** series: `deepseek`, `deepseek-r1`, etc.

### How to Disable

If you need faster responses (at the cost of some accuracy):

1. Go to **Settings → MP Ukagaka → LLM Settings**
2. In the **Ollama Settings** section, check "**Disable Thinking Mode (Qwen3, DeepSeek, etc.)**"
3. Click "Save"

| Feature | Thinking Mode (Default) | Non-Thinking Mode |
| --- | --- | --- |
| **Response Quality** | High accuracy | Faster but may be less accurate |
| **Response Speed** | Slightly slower (+1-2s) | Faster |
| **Context Window (Chat Mode)** | 8192 tokens | 4096 tokens |
| **Thinking & Response** | Completely separated | May mix together |

---

## Weather Awareness Feature

> 🌤️ **v2.5.0 Feature**: Let your Ukagaka be aware of the weather!

Uses the [Open-Meteo](https://open-meteo.com/) free API — **no API Key required**. When enabled, the character is aware of local weather conditions and may mention them in dialogue or AI-generated comments (requires a System Prompt that uses weather variables).

### Setup Steps

1. In **LLM Settings**, check "**Enable Weather Awareness**"
2. Enter your location's latitude and longitude (default: Taipei — 25.0330, 121.5654)
   - Right-click on [Google Maps](https://www.google.com/maps) to copy coordinates
3. Click "Test Weather API" to confirm weather data can be retrieved

### Features

- Current temperature and weather conditions (sunny, rainy, cloudy, etc.)
- Tomorrow's weather forecast
- Precipitation probability for today and tomorrow
- Automatic caching to avoid excessive requests

---

## Automated Diary Feature

> 📓 **v2.5.0 Feature**: Let your character automatically write diary posts!

Go to **Settings** → **MP Ukagaka** → **Diary Settings**

### What is the Automated Diary Feature?

When enabled, the character will automatically write and publish diary posts at a very low probability (set by you). These diaries include:

- Today's date and season
- Current weather conditions (if weather feature is enabled)
- Mood and thoughts fitting the character's personality
- Random daily events

### Basic Settings

| Setting | Description |
| --- | --- |
| Enable Automated Diary | System checks once per day whether to trigger |
| Post Category | Select which category to publish diary posts to |
| Post Author | Select which WordPress user to publish as |
| Trigger Probability | Daily trigger probability (1%-10%) |
| Post Signature | Text appended to the end of each diary (leave blank to skip) |

**Trigger Probability Reference:**

| Probability | Approx. Posts per Month |
| --- | --- |
| 2% | 0-1 (Rare) |
| 5% | 1-2 (Occasional) |
| 10% | 3 (Frequent) |

> ⚠️ To avoid flooding with diaries, keeping it at 2-5% is recommended.

> 💡 It's recommended to create a dedicated WordPress post category first (e.g., "Frieren's Notes"), then select it here.

### Diary AI Provider

The diary feature uses **independent AI settings**, meaning you can use a different provider than the general dialogue. For example: Ollama (free) for chat, Gemini 2.5 Flash (high quality, low cost) for diaries.

Supports the same providers as LLM Settings: Gemini, OpenAI, Claude, Ollama.

### Diary Topic Configuration (diary.json)

What the diary "writes about" is controlled by `diary.json` in the character folder, which defines topic categories, prompts, and selection weights.

**File Location:** `ghost/CharacterID/diary.json`

For detailed format documentation, see the [Character Creation Guide](GHOST_CREATE_GUIDE.md).

### Test Feature

Click "**Generate Diary Now (Test)**" at the bottom of the settings page to immediately generate and publish a test diary — useful for confirming AI connectivity, post settings, and content style.

---

# Part 3: Static Dialogue Features

> This section explains the dialogue features available when not using an AI model.

---

## External Dialogue Files

Ukagaka can load dialogue from external files, supporting TXT and JSON formats.

**Enable:** In **General Settings**, check "Use External Dialogue File" and select the format (TXT or JSON).

### TXT Format

**File Location:** `wp-content/plugins/mp-ukagaka/dialogs/CharacterName.txt`

```
First dialogue

Second dialogue

Third dialogue
```

> ⚠️ Separate each dialogue entry with an **empty line**.

### JSON Format

**File Location:** `wp-content/plugins/mp-ukagaka/dialogs/CharacterName.json`

```json
{
  "messages": ["First dialogue", "Second dialogue", "Third dialogue"]
}
```

---

## Dialogue Settings

Go to **Settings** → **MP Ukagaka** → **Dialogue**

### Fixed Information

This message will be **appended to the end of every dialogue**. Useful for displaying site announcements or adding a signature.

```
—— Welcome to subscribe to our RSS
```

### General Dialogue

If filled, **all Ukagaka will use these dialogues**, replacing their custom dialogues. Clear this field to revert to each character's individual dialogue.

---

## Special Codes

Use special codes in dialogue text to display dynamic content:

| Code | Description |
| --- | --- |
| `:recentpost[N]:` | Show list of N recent posts |
| `:randompost[N]:` | Show N random posts |
| `:commenters[N]:` | Show N recent commenters |

**Example:**

```
Recent posts: :recentpost[3]:
```

---

## Extensions

Go to **Settings** → **MP Ukagaka** → **Extensions**

### JS Area

Add custom JavaScript code to add more interactive features for the Ukagaka.

**Example: Double-click Ukagaka to navigate to a specific page**

```javascript
document.getElementById('cur_ukagaka').addEventListener('dblclick', function() {
  window.location.href = '/about/';
});
```

---

# FAQ

### Ukagaka Not Showing

1. Confirm "Default Show Ukagaka" is checked
2. Check if the current page is in the exclusion list
3. Clear browser cache
4. Press F12 to check the Console for JavaScript errors

### AI Not Triggering

1. Confirm "Enable Page Awareness" is enabled
2. Check that the API Key is correct
3. Confirm the current page meets trigger conditions
4. Temporarily set "Page Awareness Probability" to 100% to test
5. Confirm article content is over 500 words

### Dialogue Not Displaying Correctly

1. Check that the dialogue file format is correct
2. TXT Format: Separate each dialogue with an **empty line**
3. JSON Format: Confirm it is valid JSON

### AI Response Too Slow

1. Switch to a faster model (e.g., `gemini-2.5-flash`, `qwen3:8b`)
2. **Cloud AI**: Shorten the System Prompt to reduce API processing time
3. **Local LLM (Ollama)**: Prompt length has less impact on speed — consider adjusting model size or hardware
4. Check your internet connection

### LLM Connection Failed

**Ollama:**

1. Confirm the Ollama service is running
2. Confirm the port is 11434 — try visiting `http://localhost:11434` in your browser
3. Remote connection: confirm Cloudflare Tunnel is running and the tunnel URL is correct

**Gemini / OpenAI / Claude:**

1. Confirm the API Key is correct and not expired
2. Confirm the API Key has sufficient balance
3. Confirm your network connection is working — check firewall settings

### How to Control AI Costs

1. Lower "Page Awareness Probability" (suggest 10-20%)
2. Limit "Trigger Pages" (only trigger on `is_single`)
3. Use cheaper models (e.g., `gemini-2.5-flash`, `gpt-4o-mini`)
4. Use Ollama (runs locally, completely free)

---

## Technical Support

If you have issues, please:

1. Consult this User Guide
2. Visit [萌えログ.COM](https://www.moelog.com/)

---

**Made with ❤ for WordPress**
