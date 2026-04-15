# MP Ukagaka User Guide

> 🎭 Add cute Ukagaka (Desktop Mascots) to your WordPress site

---

## 📑 Table of Contents

1. [Introduction](#introduction)
2. [Installation &amp; Activation](#installation--activation)
3. [Quick Start](#quick-start)
4. [Basic Settings](#basic-settings)
5. [Ukagaka Management](#ukagaka-management)
6. [Dialogue Settings](#dialogue-settings)
7. [AI Feature Settings](#ai-feature-settings)
8. [LLM Feature Settings (BETA)](#llm-feature-settings-beta)
9. [Interactive Chat Mode (v2.3.0)](#interactive-chat-mode-v230)
10. [Thinking Mode (v2.3.0)](#thinking-mode-v230)
11. [Extensions](#extensions)
12. [FAQ](#faq)

---

## Introduction

MP Ukagaka is a WordPress plugin that lets you display interactive desktop mascot characters (Ukagaka/Nanika) on your website. Characters can display custom dialogue messages and support AI intelligent page awareness, automatically generating comments based on article content.

### Key Features

- 🎨 **Multi-Character Support**: Create and manage multiple Ukagaka characters.
- 💬 **Custom Dialogue**: Set exclusive dialogue content for each character.
- 🤖 **Universal LLM Interface**: Supports Ollama, Gemini, OpenAI, and Claude AI services.
- 🧠 **AI Page Awareness**: Automatically generates comments based on article content.
- 🌍 **Multi-Language**: Supports Traditional Chinese, Japanese, and English.
- 📁 **External Dialogue Files**: Supports TXT and JSON format dialogue files.
- ⚙️ **Highly Customizable**: Typing speed, display position, styles, and more are adjustable.
- 🎭 **Custom Prompt System**: Markdown/XML-formatted System Prompt for structured character settings.

---

## Installation & Activation

### System Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- Modern browser with JavaScript support

### Installation Steps

1. Download the plugin ZIP file.
2. Log in to WordPress Admin → **Plugins** → **Add New**.
3. Click "Upload Plugin" and select the downloaded ZIP file.
4. Click "Install Now".
5. After installation, click "Activate Plugin".

### Initial Setup

After activation, go to **Settings** → **MP Ukagaka** to configure.

---

## Quick Start

### 5-Minute Quick Setup

1. **Go to Settings Page**
   - WordPress Admin → Settings → MP Ukagaka

2. **Confirm Default Ukagaka**
   - In the "General Settings" page, ensure "Default Ukagaka" is selected.
   - Check "Default Show Ukagaka" and "Default Show Balloon".

3. **Save Settings**
   - Click the "Save" button.

4. **Check the Result**
   - Go to your website's front end; you should see the Ukagaka character in the bottom right corner of the page.

---

## Basic Settings

Go to **Settings** → **MP Ukagaka** → **General Settings**

### Display Settings

| Setting Item         | Description                                      |
| -------------------- | ------------------------------------------------ |
| Default Ukagaka      | Select the character to display by default.      |
| Default Show Ukagaka | Whether to show the character image by default.  |
| Default Show Balloon | Whether to show the dialogue balloon by default. |

### Dialogue Settings

| Setting Item    | Description                                                           |
| --------------- | --------------------------------------------------------------------- |
| Default Session | "Random Talk" or "First Talk".                                        |
| Session Order   | Whether the next dialogue upon clicking is "Sequential" or "Random".  |
| Click Ukagaka   | Action when clicking the character (Show next dialogue or No action). |

### Auto Dialogue

| Setting Item           | Description                                            |
| ---------------------- | ------------------------------------------------------ |
| Enable Auto Dialogue   | Whether to automatically rotate dialogue.              |
| Auto Dialogue Interval | Interval for automatic dialogue change (3-30 seconds). |
| Typing Effect Speed    | Dialogue typing animation speed (10-200 ms/char).      |

### External Dialogue Files

| Setting Item         | Description                                          |
| -------------------- | ---------------------------------------------------- |
| External File Format | Select TXT or JSON format.                           |
| Use External File    | Whether to read dialogue from the `dialogs/` folder. |

### Page Exclusion

In the "Don't show Ukagaka on these pages" text box, enter the URLs of pages where you don't want the Ukagaka to appear, one per line.

Supports wildcard matching: add `(*)` at the end of the URL to match all subpages.

**Example:**

```
/admin/
/wp-admin/(*)
/private-page/
```

### Style Settings

| Setting Item      | Description                                                                                 |
| ----------------- | ------------------------------------------------------------------------------------------- |
| Use Custom Style  | When enabled, the plugin will not load built-in CSS, allowing you to control the appearance |
| Custom Style Link | You can enter `<link>` tags to load your own custom CSS stylesheet                          |

> 💡 **Tip**: This option is for advanced users. When enabled, you need to write your own styles for the Ukagaka in your **theme's CSS** or load them via the "Custom Style Link" field. If you don't plan to customize the CSS, leave this unchecked to use the plugin's built-in styles.

**Custom Style Link Example:**

```html
<link
  rel="stylesheet"
  href="https://example.com/custom-ukagaka.css"
  type="text/css"
/>
```

---

## Ukagaka Management

### View Existing Ukagaka

Go to **Settings** → **MP Ukagaka** → **Ukagaka**

On this page, you can:

- View all created Ukagaka.
- Edit Ukagaka name, image, and dialogue.
- Delete non-default Ukagaka.
- Set visibility (via checkbox).

### Create New Ukagaka

> 📘 **Advanced Guide**: For creating character personalities with full LLM support, please refer to [Character Creation Guide (GHOST_CREATE_GUIDE.md)](GHOST_CREATE_GUIDE.md).

Go to **Settings** → **MP Ukagaka** → **Create New Ukagaka**

#### Required Fields

| Field     | Description                    | Example                           |
| --------- | ------------------------------ | --------------------------------- |
| Name      | Name of the Ukagaka            | `Frieren`                         |
| Image URL | Full URL of the Ukagaka image  | `https://example.com/ukagaka.png` |
| Dialogue  | Dialogue content, one per line | See example below                 |

#### Dialogue Content Example

```
Welcome to my website!
The weather is nice today~
Want to read the latest article?
Magic requires time to study slowly.
```

#### Optional Fields

| Field             | Description                                                                |
| ----------------- | -------------------------------------------------------------------------- |
| Dialogue Filename | Name of the external dialogue file (without extension).                    |
| Generate File     | Checking this will automatically generate the corresponding dialogue file. |

### External Dialogue File Format

#### TXT Format

File path: `wp-content/plugins/mp-ukagaka/dialogs/CharacterName.txt`

```
First dialogue

Second dialogue

Third dialogue
```

> ⚠️ Separate each dialogue entry with an **empty line**.

#### JSON Format

File path: `wp-content/plugins/mp-ukagaka/dialogs/CharacterName.json`

```json
{
  "messages": ["First dialogue", "Second dialogue", "Third dialogue"]
}
```

---

---

## Customizing Emoji System (v2.4.0)

MP Ukagaka v2.4.0 introduces a dynamic emoji system, allowing each character to have their own set of emojis.

### File Structure

To add emoji support for a character, create the following structure in the character's folder (`ghost/CharacterID/`):

- `emojis/` folder: Stores emoji images (Supports PNG, APNG, GIF)
- `CharacterID-emoji.js`: Emoji control script (Can copy `Frieren-emoji.js` and modify)
- `emoji-keywords.json`: Emoji trigger keyword settings (Optional)

### Keyword Settings (`emoji-keywords.json`)

This file defines which keywords trigger which emoji. If this file is not created, the system uses built-in generic keyword mappings.

**Format Example:**

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

- **keywords**: List of keywords triggering this emoji
- **file**: Image filename in `emojis/` folder
- **weight**: Weight (Higher priority when multiple emojis match)

---

## Dialogue Settings

Go to **Settings** → **MP Ukagaka** → **Dialogue**

### Fixed Information

This message will be **appended to the end of every dialogue**.

**Use Cases:**

- Display website announcements.
- Add signature or slogan.

**Example:**

```
—— Welcome to subscribe to our RSS
```

### General Dialogue

If filled, **all Ukagaka will use these dialogues**, replacing their custom dialogues.

Clear this field to revert to using each Ukagaka's default dialogue.

---

## AI Feature Settings (Page Awareness)

> 💡 **Important Note**: The AI Settings page is now dedicated to the "Page Awareness" feature. For LLM-related settings, please go to the **LLM Settings** page.

Go to **Settings** → **MP Ukagaka** → **AI Settings**

### What is Page Awareness?

Page Awareness allows Ukagaka to automatically generate AI comments related to article content on specific pages (such as single posts, pages). This feature requires first configuring in the **LLM Settings** page:

1. Select AI provider (Gemini, OpenAI, Claude, or Ollama)
2. Set API Key (except for Ollama)
3. Select model
   - **Gemini**: Gemini 2.5 Flash (Recommended, fast and cost-effective), Gemini 2.5 Pro (Smarter, for complex reasoning)
   - **OpenAI**: GPT-4.1 Mini (Recommended, fast and cost-effective), GPT-4o Mini (Fast and economical), GPT-4o (Smarter)
   - **Claude**: Claude Sonnet 4.5 (Recommended), Claude Haiku 4.5 (Fast), Claude Opus 4.5 (Advanced)

   > 🌍 **Multi-Language Support**: The model selection dropdown descriptions automatically display in the corresponding language (Traditional Chinese, English, Japanese) based on WordPress language settings. This helps users in different languages clearly understand each model's characteristics.

4. **Enable "Page Awareness Feature"**

### Basic Settings

#### 1. Language Settings

Select the language for AI responses:

- Traditional Chinese
- Japanese
- English

#### 2. Character Settings (System Prompt)

This is the core personality setting for the character, which will be sent to the LLM as part of the System Prompt. You can set the character's personality, speaking style, etc.

**Supported Formats:**

- **Plain Text Format** (Basic): Direct text description
- **Markdown Format** (Recommended): Use headings, lists, emphasis, etc. for structured formatting that helps models understand better
- **XML Tag Format** (Advanced): Use XML tags to mark structure for finer control

**Plain Text Example:**

```
You are the mage Frieren, speaking in a calm, slightly cold tone, showing more interest in magic-related topics. Keep responses under 50 words.
```

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

**XML Format Example:**

```xml
<role>Mage Frieren</role>
<personality>
  <trait>Calm, slightly cold tone</trait>
  <interest>Magic-related topics</interest>
</personality>
<rules>
  <response_length>Under 50 words</response_length>
  <tone>Casual (no honorifics)</tone>
</rules>
```

**Variable Support:**

You can use `{{variable_name}}` for dynamic replacement, for example:

- `{{ukagaka_display_name}}`: Character name
- `{{language}}`: Response language
- `{{time_context}}`: Time context (e.g., "Spring Morning")

**See the "Prompt System Architecture" section below for the complete variable list.**

> 💡 **Tip**:
>
> - This setting integrates with the System Prompt optimization system in the LLM Settings page
> - Modern LLMs (OpenAI, Claude, Gemini) can understand Markdown and XML formats directly
> - Using structured formats helps models better understand character settings; Markdown format is recommended
> - The input box uses monospace font for easier format structure viewing

#### 3. Page Awareness Probability (%)

Set the probability (1-100%) of triggering AI comments on matching pages.

**Recommended Values:**

- 10-30%: More natural, not too frequent
- 50%: Balanced trigger frequency
- 80-100%: Almost always triggers

#### 4. Trigger Pages

Set which page types trigger AI comments:

- `is_single`: Single posts
- `is_page`: Single pages
- `is_home`: Home page
- `is_front_page`: Static front page
- `is_archive`: All archive pages
- `is_category`: Category pages
- `is_tag`: Tag pages

**Example:**

```
is_single,is_page
```

> 💡 **Tip**: Separate multiple conditions with commas.

#### 5. AI Conversation Display Time (seconds)

Set how long AI-generated comments display before automatically disappearing.

**Recommended Values:**

- 5-10 seconds: Shorter display time, won't overly interrupt reading
- 10-15 seconds: Balanced display time
- 15-20 seconds: Longer display time, suitable for longer comments

#### 6. Enable First-Time Visitor Greeting

When enabled, first-time visitors to the website will receive a special greeting message.

#### 7. First-Time Visitor Greeting Prompt

Set the greeting message prompt for first-time visitors. This prompt combines with "Character Settings" to generate personalized greetings.

**Supported Formats:**

Same as "Character Settings", supports plain text, Markdown, and XML formats.

**Plain Text Example:**

```
Greet first-time visitors and briefly introduce this website.
```

**Markdown Format Example:**

```markdown
## First-Time Visitor Greeting Rules

- Greet concisely within 50 characters
- Use casual tone (no honorifics)
- Lightly mention visitor source or geographic info if available

### Conversation Examples

- "Nice to meet you. What brought you here?"
- "You came from Google, didn't you?"
```

> 💡 **Tip**: Supports `{{variable_name}}` variable replacement, same as System Prompt.

#### 8. Bot Detection

The system automatically detects crawler bots. When a bot visit is detected, it can trigger specific dialogue reactions, such as "Intruder detected" or "Friendly greeting".

![Bot Dialogue Example](../screenshot5.PNG)

_Bot Detection Dialogue Example: AI reacting specifically to bot visits_

### Page Awareness Workflow

1. Visitor accesses a page matching "Trigger Pages" conditions
2. System decides whether to trigger based on "Page Awareness Probability"
3. If triggered, the system will:
   - Read article content
   - Combine "Character Settings" and System Prompt from LLM Settings
   - Call selected AI service to generate comments
   - Display comments in Ukagaka dialogue box
   - Automatically disappear based on "AI Conversation Display Time" setting

### Relationship with LLM Features

- **AI Settings Page**: Controls "Page Awareness" feature behavior (when to trigger, how to display)
- **LLM Settings Page**: Controls AI service selection and settings (which AI to use, how to generate dialogue)

Used together, they can achieve:

- Use AI to comment on articles on specific pages (Page Awareness)
- Use LLM to generate random dialogue at other times (LLM replaces built-in dialogue)

---

## LLM Feature Settings

> 💡 **Major Update**: LLM functionality has been upgraded to a **Universal LLM Interface**, supporting multiple AI providers!

Go to **Settings** → **MP Ukagaka** → **LLM Settings**

### What is the LLM Feature?

The LLM (Large Language Model) feature allows you to use multiple AI services to generate dialogue, including:

- **Ollama** (Local/Remote): Completely free, no API Key required
- **Google Gemini**: Requires API Key
- **OpenAI**: Requires API Key
- **Claude (Anthropic)**: Requires API Key

All providers use a unified settings interface, and you can switch between different AI services at any time.

### Prerequisites

1. **Install Ollama**
   - Go to [Ollama Official Website](https://ollama.ai/) to download and install.
   - Start the Ollama service.
   - Download a model: Run `ollama pull qwen3:8b` (or your preferred model) in the terminal.

2. **Verify Ollama is Running**
   - Local: Visiting `http://localhost:11434` should show "Ollama is running".
   - Remote: Ensure your Cloudflare Tunnel or other tunnel service is running properly.

### Basic Settings

#### 1. Enable LLM

- Check "Enable LLM (Ollama)".
- The system will automatically switch the AI provider to Ollama.

#### 2. Configure Endpoint

**Local Connection:**

```
http://localhost:11434
```

**Remote Connection (Cloudflare Tunnel):**

```
https://your-domain.com
```

> 💡 The plugin automatically detects connection type (local/remote) and adjusts timeout settings.

#### 3. Set Model Name

Enter the name of the model you downloaded, for example:

- `qwen3:8b`
- `llama3.2`
- `qwen2.5:14b` (Recommended, best results)

> 💡 Use the `ollama list` command in PowerShell to view downloaded models.

#### 4. Test Connection

Click the "Test Ollama Connection" button to confirm the connection is working.

#### 5. Using Modelfile to Create Character-Specific Models (Advanced)

Ollama's **Modelfile** allows you to embed character settings directly into the model. This means you don't need to send the System Prompt with each conversation, **significantly reducing token consumption** and improving response consistency.

##### What is a Modelfile?

A Modelfile is Ollama's model configuration file, similar to Docker's Dockerfile. It can:

- Specify the base model
- Embed System Prompt (character settings)
- Adjust generation parameters (temperature, repeat penalty, etc.)
- Limit output length

##### Using the Example Modelfile

This plugin provides a Frieren character Modelfile example: `example/frieren_modelfile.example.txt`

**Step 1: Prepare the Modelfile**

```powershell
# Copy the example Modelfile to your working directory
Copy-Item wp-content\plugins\mp-ukagaka\example\frieren_modelfile.example.txt $HOME\frieren_modelfile
```

**Step 2: Modify the Base Model (Optional)**

Edit the Modelfile and change the `FROM` line (line 1) to a model you've downloaded:

```dockerfile
# Change to your downloaded model
FROM qwen3:8b
# Or other models:
# FROM gemma3:12b
# FROM llama3.2
# FROM qwen2.5:14b
```

**Step 3: Replace Admin Name Variables (Important)**

Edit the Modelfile and search for and replace the following variables:

- `{{admin_nickname}}`: Replace with the admin's full nickname
- `{{admin_name}}`: Replace with the admin's short name

> ⚠️ **Important**: If you don't replace these variables, the AI may directly say `{{admin_nickname}}` or `{{admin_name}}` in conversations instead of the actual admin name.

**Step 4: Create the Custom Model**

```powershell
# Create new model using Modelfile
ollama create frieren -f $HOME\frieren_modelfile

# On success, you'll see:
# success
```

**Step 5: Test the Model**

```powershell
# Test conversation
ollama run frieren "Hello"

# Should respond in Frieren's character
```

**Step 6: Use in Plugin**

In **LLM Settings** page, set the model name to `frieren` (or your custom model name).

##### Creating Your Own Character Modelfile

You can reference `example/frieren_modelfile.example.txt` to create your own character:

1. **Copy the example file**: `cp example/frieren_modelfile.example.txt my_character_modelfile`
2. **Modify the SYSTEM section**: Replace with your character settings
3. **Replace admin name variables**: Replace `{{admin_nickname}}` and `{{admin_name}}` with actual admin names
4. **Adjust parameters**: Adjust temperature, output length, etc. as needed
5. **Create the model**: `ollama create my_character -f my_character_modelfile`

##### Modelfile Structure

```dockerfile
# Base model (must be downloaded first)
FROM qwen3:8b

# System Prompt (character settings)
SYSTEM """
あなたは「フリーレン」。以下の人格・記憶・態度・話し方・制約を必ず守ること。
...
"""

# Parameter adjustments
PARAMETER num_predict 80       # Max output tokens
PARAMETER num_ctx 8192         # Context length
PARAMETER temperature 0.7      # Temperature (creativity)
PARAMETER top_p 0.9            # Top-p sampling
PARAMETER repeat_penalty 1.3   # Repeat penalty
PARAMETER repeat_last_n 64     # Repeat check window
```

##### Parameter Recommendations

| Parameter        | Description       | Recommended                         |
| ---------------- | ----------------- | ----------------------------------- |
| `num_predict`    | Max output tokens | 80 (auto-talk), 200 (chat mode)     |
| `num_ctx`        | Context length    | 8192 (ensures full System Prompt)   |
| `temperature`    | Creativity        | 0.7 (balance consistency & variety) |
| `top_p`          | Top-p sampling    | 0.9 (moderate variety)              |
| `repeat_penalty` | Repeat penalty    | 1.3 (reduce repetition)             |

> 📝 **Note**: The `num_predict` value depends on use case. Auto-talk mode needs shorter responses (80 tokens ≈ 40 Japanese chars); chat mode needs more space (200 tokens ≈ 100 chars). This differs from the `max_tokens: 600` setting in `manifest.json`, which applies to cloud APIs, not Ollama.

##### Modelfile vs Backend System Prompt

| Method               | Pros                                | Cons                            |
| -------------------- | ----------------------------------- | ------------------------------- |
| **Modelfile**        | No token cost, consistent responses | Need to rebuild model to change |
| **Backend Settings** | Easy to modify, flexible            | Costs tokens each time          |

> 💡 **Recommendation**: Use Modelfile if your character settings are stable. Use backend settings while still tuning the character.

##### Common Modelfile Commands

```powershell
# List created models
ollama list

# Delete custom model
ollama rm frieren

# Rebuild model (after modifying Modelfile)
ollama rm frieren; ollama create frieren -f $HOME\frieren_modelfile

# Show model info
ollama show frieren
```

### Advanced Settings

#### Use LLM to Replace Built-in Dialogue

After enabling this option:

- All Ukagaka dialogue will be generated by LLM in real-time.
- The default static dialogue list will no longer be used.

> 💡 **Tip**: This feature can be enabled simultaneously with "Page Awareness". When Page Awareness conditions are met, it will use AI to comment on the article; otherwise, it will use randomly generated dialogue.

**Use Cases:**

- Want completely dynamic dialogue content.
- Do not need default static dialogue.
- Wish for every dialogue to be unique.

#### Customize LLM Prompt System

> 💡 **Advanced Feature**: If you want to adjust the LLM generated dialogue style according to the character's personality, you can customize the prompt system.

**Default Prompt Style:**

Currently, the system default prompt is based on **Frieren style**, emphasizing:

- Calm, rational, with a sense of detachment
- Brief, direct, occasionally teasing
- Observer perspective, not overly enthusiastic
- Quiet, natural, unassuming dialogue

**System Architecture:**

The new prompt system adopts a **two-layer architecture** design:

1. **System Prompt (System Prompt)**: Defines character style, behavior rules, and dialogue examples
2. **User Prompt (User Prompt)**: Specific task instructions for each dialogue

This design makes character style more consistent while maintaining dialogue diversity.

**How to Customize Prompts:**

1. **Backend System Prompt Settings**

   System Prompt is now completely controlled by **backend settings**, with the code only performing `{{variable}}` variable replacement.
   - **Setting Location**: **Settings** → **MP Ukagaka** → **LLM Settings** → **Personality (System Prompt)**
   - **Format Support**: Supports **Plain Text**, **Markdown**, and **XML Tag** formats
   - **Variable Support**: You can use variables like `{{ukagaka_display_name}}`, `{{language}}`, `{{time_context}}` in System Prompt
   - **Design Philosophy**: Backend System Prompt is the single source of truth. All character styles, behavior rules, and dialogue examples should be defined here

   **Format Description:**
   - **Plain Text Format**: The simplest and most direct way, suitable for simple settings
   - **Markdown Format** (Recommended): Use headings, lists, emphasis, etc., making settings more structured and readable; models can also understand better
   - **XML Tag Format** (Advanced): Provides the finest control, suitable for complex character settings

   > 💡 **Tip**:
   >
   > - Modern LLMs (OpenAI GPT, Claude, Gemini) can directly understand Markdown and XML formats without additional processing
   > - Markdown format is recommended for a balance between readability and structure
   > - The input box uses monospace font for easier format structure viewing
   > - See `example/system-prompt-markdown-example.md` for a complete Markdown format example

   **Variable List:**
   - `{{ukagaka_display_name}}`: Character name
   - `{{language}}`: Response language (zh-TW, ja, en)
   - `{{time_context}}`: Time context (e.g., "Spring Morning")
   - `{{admin_nickname}}`: Admin's full nickname (must be manually replaced in example files)
   - `{{admin_name}}`: Admin's short name (must be manually replaced in example files)
   - `{{wp_version}}`: WordPress version
   - `{{php_version}}`: PHP version
   - `{{post_count}}`: Post count
   - `{{comment_count}}`: Comment count
   - `{{category_count}}`: Category count
   - `{{tag_count}}`: Tag count
   - `{{days_operating}}`: Days of operation
   - `{{theme_name}}`: Theme name
   - `{{theme_version}}`: Theme version
   - `{{theme_author}}`: Theme author

   > 💡 **Important**: System Prompt should contain complete character definition including personality, speaking style, behavior rules, etc. The code will no longer hardcode any XML structures, examples, or rules.

2. **User Prompt Structure**

   User Prompt is automatically constructed by the code and includes the following parts:

   ```
   【Current User Info】
   (If user is logged in, display username, role, etc.)

   【Visitor Info】
   (Display BOT detection, source region, etc.)

   【Site Statistics】
   (Display post count, comment count, WordPress version, etc.)

   【Time Context】
   Current time: {time context}

   【Dialogue Instruction】
   {Randomly selected instruction from prompt_categories}
   ```

   > 💡 **Design Philosophy**: User Prompt contains actual contextual information and specific task instructions, allowing the LLM to generate appropriate responses based on the current situation.

3. **Dialogue Category System (35 Categories)**

   The system has 35 built-in dialogue categories covering various character traits:

   **Core Personality Categories:**
   - `greeting`: Greeting category
   - `casual`: Casual chat category
   - `emotional_density`: Emotional density category (late understanding, sudden realization, etc.)
   - `self_awareness`: Self-awareness category

   **Time and Memory Categories:**
   - `time_aware`: Time-aware category
   - `memory`: Memory category
   - `party_memories`: Hero party memories category
   - `mentors_seniors`: Mentors and seniors category
   - `journey_adventure`: Journey and adventure category

   **Magic Professional Categories:**
   - `magic_research`: Magic research category
   - `magic_collection`: Magic collection category
   - `magic_metaphor`: Magic metaphor category (comparing technology to magic)
   - `demon_related`: Demon-related category

   **Human Observation Categories:**
   - `human_observation`: Human observation category
   - `admin_comment`: Admin comment category
   - `comparison`: Comparison category

   **Technical Statistics Categories:**
   - `tech_observation`: Technical observation category
   - `statistics`: Statistics observation category

   **Atmosphere and Situation Categories:**
   - `observation`: Observation and thinking category
   - `silence`: Silence category
   - `weather_nature`: Weather and nature category
   - `daily_life`: Daily life category
   - `current_action`: Current action category
   - `philosophical`: Philosophical thinking category

   **Emotional Expression Categories:**
   - `food_preference`: Food preference category
   - `unexpected`: Unexpected reaction category
   - `frieren_humor`: Frieren-style humor category
   - `curiosity`: Curiosity category
   - `lesson_learned`: Lessons learned category

   **Special Situation Categories:**
   - `bot_detection`: BOT detection category
   - `error_problem`: Error and problem category
   - `success_achievement`: Success and achievement category
   - `future_plans`: Future plans category
   - `seasonal_events`: Seasonal events category

   > ⭐ **Special Feature**: The `observation` (observation and thinking) category automatically reads up to 5 lines from the current character's built-in dialogue file, automatically filtering out empty strings and messages longer than 50 characters to ensure style consistency.

4. **Dynamic Weight System**

   The system uses dynamic weights to determine which type of dialogue to generate. Weights are automatically adjusted based on time, visitor status, etc.:

   ```php
   // Base weights (total approximately 200)
   $weights = [
       'casual' => 15,              // High-frequency core categories
       'observation' => 15,
       'magic_collection' => 12,
       'time_aware' => 10,
       'party_memories' => 10,      // Mid-frequency characteristic categories
       'human_observation' => 10,
       // ... more categories
   ];
   ```

   **Dynamic Adjustment Mechanism:**
   - **Time Period Adjustment**: Increase `philosophical`, `party_memories` weights during late night; increase `observation`, `magic_research` weights during morning
   - **Visitor Status Adjustment**: Increase `greeting`, `observation` weights for first-time visitors; increase `admin_comment`, `casual` weights for frequent visitors
   - **BOT Detection Adjustment**: Significantly increase `bot_detection` weight when BOT is detected

   **Customizing Weights:**
   - Can be modified in the `mpu_get_dynamic_category_weights()` function in `includes/llm-functions.php`
   - Can add more context variables in the `mpu_generate_llm_dialogue()` function (e.g., `is_first_visit`, `is_frequent_visitor`, `is_weekend`, etc.)

5. **WordPress Info Integration**

   The system automatically adds WordPress site info to User Prompt, including:

   **Basic System Info:**
   - WordPress version
   - Current theme name, version, author
   - PHP version
   - Site name and description
   - Active plugin count

   > 💡 **Tip**: Theme author info (`$theme_author`) is only available if the theme provides it; some themes may not include this.

   **Statistics (Gamified Terms):**
   - **Demon Encounters** (Post Count): `{$post_count}`
   - **Max Damage** (Comment Count): `{$comment_count}`
   - **Skills Learned** (Category Count): `{$category_count}`
   - **Items Used** (Tag Count): `{$tag_count}`
   - **Adventure Days** (Days Operating): `{$days_operating}`

   This information is automatically added to User Prompt, allowing the LLM to generate dialogues based on the actual site situation.

   > 💡 **Tip**: Statistics use a transient cache for 5 minutes to avoid frequent database queries affecting performance.

6. **Statistics Gamification Mapping**

   The system uses "demon battle" metaphors to describe site statistics. The mapping is as follows:

   | Site Statistics | Gamified Metaphor | Variable            |
   | --------------- | ----------------- | ------------------- |
   | Post Count      | Demon Encounters  | `{$post_count}`     |
   | Comment Count   | Max Damage        | `{$comment_count}`  |
   | Category Count  | Skills Learned    | `{$category_count}` |
   | Tag Count       | Items Used        | `{$tag_count}`      |
   | Days Operating  | Adventure Days    | `{$days_operating}` |
   | Plugin Count    | Magic Learned     | `{$plugins_count}`  |

   These metaphors are automatically integrated into User Prompt, making dialogues more consistent with the character's worldview.

7. **Customizing Dialogue Category Instructions**

   If you want to modify dialogue category instructions, you can edit the `mpu_build_prompt_categories()` function:

   ```php
   // In includes/llm-functions.php
   function mpu_build_prompt_categories(...) {
       $prompt_categories = [
           'greeting' => [
               "軽く挨拶する",
               "一言挨拶する",
               // ... add more instructions
           ],
           // ... more categories (35 total)
       ];

       return $prompt_categories;
   }
   ```

   **Instruction Design Points:**
   - ✅ **Concise and Clear**: Instructions should be concise and directly tell the LLM what type of dialogue to generate
   - ✅ **Task-Oriented**: Instructions should clearly tell the LLM "what type of dialogue to generate this time"
   - ✅ **Category-Appropriate**: Instructions should match the theme and style of the category

8. **Time Variable Usage**

   In time-aware categories, you can use the `{$time_context}` variable, which automatically replaces with:
   - `Morning` (5:00-11:59)
   - `Afternoon` (12:00-17:59)
   - `Evening` (18:00-21:59)
   - `Late Night` (22:00-4:59)

   > ⚠️ **Note**: Time logic uses Taiwan Time Zone (Asia/Taipei). It will display Taiwan time correctly even if the server is in another timezone.

9. **Anti-Repetition Mechanism**

   The system automatically tracks the last response generated by the LLM to prevent repeating the same content in auto-dialogue:
   - When LLM generates dialogue, the system records this response.
   - Next time auto-dialogue triggers, the last response is passed to LLM.
   - LLM will generate different content based on the prompt or remain silent.
   - Effectively avoids "nonsense loop" issues.

   > 💡 This mechanism is handled automatically in the backend, no extra setup needed.

10. **Idle Detection**

    The system automatically detects user activity status and pauses auto-dialogue when the user is idle:
    - **Idle Threshold**: 60 seconds (1 minute).
    - **Activity Detection**: Mouse movement, keyboard input, page scroll, clicks.
    - **Auto Resume**: Auto-dialogue resumes automatically when user becomes active.
    - **Resource Saving**: Avoids wasting GPU and network resources in background tabs or when user is away.

    > 💡 Idle threshold can be adjusted in `ukagaka-core.js` via the `mpuIdleThreshold` constant (default 60000ms).

11. **Notes After Modification**
    - Clear WordPress cache after modification (if applicable).
    - Recommend testing a few dialogues to ensure style meets expectations.
    - Adjust the number of prompts in different categories based on character personality.
    - More specific prompts lead to more consistent dialogue styles.

**Prompt Design Suggestions:**

- ✅ **Fit Character**: Design System Prompt according to the character's personality
- ✅ **Complete Definition**: System Prompt should contain complete character definition including personality, speaking style, behavior rules, etc.
- ✅ **Natural Expression**: Use natural language, avoid being too mechanical
- ✅ **Variable Usage**: Make good use of `{{variable}}` variables to make prompts more dynamic
- ✅ **Diversity**: Recommend 4-6 different instructions per dialogue category
- ✅ **Avoid Contradictions**: Ensure rules and style consistency in System Prompt
- 💡 **Length Recommendations**:
  - **Cloud AI Services** (Gemini, OpenAI, Claude): Recommend System Prompt within 500-1000 words to reduce token usage
  - **Local LLM** (Ollama): Can use longer prompts (1000+ words); detailed prompts usually provide better character consistency and personality definition

**System Architecture Advantages:**

The new system architecture brings the following advantages:

1. **Fully Controllable**: System Prompt is completely controlled by backend settings, no code modification needed
2. **Variable Replacement**: Code only performs safe `{{variable}}` replacement, won't pollute System Prompt
3. **Information Separation**: User info, visitor info, site statistics and other actual information are placed in User Prompt, keeping System Prompt pure
4. **Dynamic Weights**: Automatically adjust dialogue category weights based on time, visitor status, etc., making dialogues more contextually appropriate

This design makes character style more consistent while maintaining dialogue diversity and contextual adaptability.

#### Disable Thinking Mode (Beta Models like Qwen3)

After enabling this option:

- Disable thinking behavior for models like Qwen3.
- Improve response efficiency.
- Reduce response time.

**Recommendation:** Enable this option when using Qwen3 or similar models.

### Remote Connection Settings (Cloudflare Tunnel)

#### Using Cloudflare Tunnel

1. **Install Cloudflare Tunnel**

   ```bash
   # Windows
   cloudflared.exe service install <token>

   # Linux/Mac
   cloudflared service install <token>
   ```

2. **Verify Service Running**
   - Check Cloudflare Tunnel service status.
   - Confirm tunnel URL (e.g., `https://your-domain.com`).

3. **Configure in Plugin**
   - Endpoint: Enter Cloudflare Tunnel URL.
   - Test connection to confirm.

#### Other Tunnel Services

The plugin also supports other tunnel services:

- **ngrok**: `https://your-subdomain.ngrok.io`
- **Other HTTP/HTTPS tunnel services**

### Common Issues

#### LLM Connection Failed

1. **Local Connection Issues**
   - Confirm Ollama service is running.
   - Check if port is 11434.
   - Try visiting `http://localhost:11434` in browser.

2. **Remote Connection Issues**
   - Confirm Cloudflare Tunnel service is running.
   - Check if tunnel URL is correct.
   - Confirm network connection is normal.
   - Check firewall settings.

#### Slow Response Speed

1. **Local Connection**
   - Use a faster model (e.g., `qwen3:8b`).
   - Enable "Disable Thinking Mode".
   - Check local resource usage.

2. **Remote Connection**
   - Remote connections have extra latency (normal).
   - Consider using a faster network connection.
   - Check Cloudflare Tunnel latency.

#### Model Not Found

- Confirm model name is correct (use `ollama list` to view).
- Confirm model is downloaded (use `ollama pull <model>` to download).
- Check if model name case is correct.

### Notes

⚠️ **Beta Restrictions:**

- Functionality may be unstable.
- Connection issues may occur.
- Response time may be longer.
- Features may change in future versions.

💡 **Suggestions:**

- Try in a test environment first.
- Regularly backup settings.
- If issues occur, switch back to traditional AI features or static dialogue.

---

### Choose AI Provider

Three AI services are supported:

| Provider      | Features             | Get API Key                                                  |
| ------------- | -------------------- | ------------------------------------------------------------ |
| Google Gemini | Fast, high free tier | [Google AI Studio](https://makersuite.google.com/app/apikey) |
| OpenAI        | GPT series models    | [OpenAI Platform](https://platform.openai.com/api-keys)      |
| Claude        | Advanced reasoning   | [Anthropic Console](https://console.anthropic.com/)          |

### AI Setting Items

| Setting Item     | Description                                | Suggested Value     |
| ---------------- | ------------------------------------------ | ------------------- |
| API Key          | Key for the corresponding AI service       | —                   |
| Model            | AI Model Version                           | Select as needed    |
| Language         | Language for AI response                   | Traditional Chinese |
| Personality      | AI Personality Description (System Prompt) | See example below   |
| AI Response Rate | Probability of triggering AI (1-100%)      | 10-30%              |
| Trigger Pages    | Pages where AI triggers                    | `is_single`         |
| AI Text Color    | Text color of AI response                  | `#ff6b6b`           |
| AI Display Time  | How long AI response shows                 | 5-10 seconds        |

### Personality Setting Example

**Tsundere Character:**

```
You are a tsundere desktop assistant "Ukagaka". You comment on article content with a short, slightly tsundere tone. Keep response under 40 words.
```

**Mage Character:**

```
You are the mage Frieren, speak in a calm, slightly cold tone, showing more interest in magic-related topics. Keep response under 50 words.
```

**Japanese Character:**

```
あなたは可愛いデスクトップマスコットです。記事について短く（40字以内）、明るくコメントしてください。
```

### Trigger Page Description

Use WordPress conditional tags, multiple conditions separated by commas:

| Tag             | Description      |
| --------------- | ---------------- |
| `is_single`     | Single Post Page |
| `is_page`       | Static Page      |
| `is_home`       | Blog Home        |
| `is_front_page` | Site Home        |
| `is_category`   | Category Page    |
| `is_tag`        | Tag Page         |

**Example:** `is_single,is_page` triggers on both posts and pages.

### First Visitor Greeting

If enabled, displays a personalized welcome message to first-time visitors.

> 💡 Combine with Slimstat plugin for more visitor info (source, search keywords, etc.).

---

## Extensions

Go to **Settings** → **MP Ukagaka** → **Extensions**

### JS Area

You can add custom JavaScript code to add more interactive features for the Ukagaka.

**Example: Double click Ukagaka to jump to a specific page**

```javascript
document
  .getElementById("cur_ukagaka")
  .addEventListener("dblclick", function () {
    window.location.href = "/about/";
  });
```

### Special Codes

Use special codes in dialogue to display dynamic content:

| Code              | Description                 |
| ----------------- | --------------------------- |
| `:recentpost[5]:` | Show list of recent 5 posts |
| `:randompost[3]:` | Show 3 random posts         |
| `:commenters[5]:` | Show recent 5 commenters    |

**Dialogue Example:**

```
Recent posts: :recentpost[3]:
```

---

## FAQ

### Ukagaka Not Showing

1. Confirm "Default Show Ukagaka" is checked.
2. Check if current page is in the exclusion list.
3. Clear browser cache.
4. Check for JavaScript errors (Press F12 to view Console).

### AI Not Triggering

1. Confirm "Enable Page Awareness" is enabled.
2. Check if API Key is correct.
3. Confirm current page meets trigger conditions.
4. Temporarily set "AI Response Rate" to 100% to test.
5. Confirm article content is over 500 words.

### Dialogue Not Displaying Correctly

1. Check if dialogue file format is correct.
2. TXT Format: Separate each dialogue with an **empty line**.
3. JSON Format: Confirm valid JSON.

### AI Response Too Slow

1. Try switching to a faster model (e.g., `gemini-2.5-flash`).
2. **Cloud AI Services**: Shorten Personality (System Prompt) to reduce API processing time.
3. **Local LLM**: Prompt length has less impact on speed; prioritize adjusting model size or hardware.
4. Check internet connection.

### LLM Connection Failed

1. **Local Connection**
   - Confirm Ollama service is running.
   - Check if port is 11434.
   - Try visiting `http://localhost:11434` in browser.

2. **Remote Connection**
   - Confirm Cloudflare Tunnel service is running.
   - Check if tunnel URL is correct.
   - Confirm network connection is normal.

### LLM Response Slow

1. Use a faster model (e.g., `qwen3:8b`).
2. Enable "Disable Thinking Mode" option.
3. Remote connections have extra latency (normal).

### How to Control AI Costs

1. Lower "AI Response Rate" (Suggest 10-20%).
2. Limit "Trigger Pages" (Only trigger on `is_single`).
3. Use cheaper models.
4. **Or use LLM feature**: Completely free, no API Key (Beta).

---

## Interactive Chat Mode (v2.3.0)

> 🎉 **v2.3.0 Major Feature**: Let visitors directly engage in real-time multi-turn conversations with Ukagaka characters!

### What is Interactive Chat Mode?

Interactive Chat Mode transforms the frontend "Change Ukagaka" button into a real-time chat interface, allowing visitors to click and engage in actual multi-turn conversations with characters. This differs from the Page Awareness feature (automatic article comments), as Chat Mode is entirely triggered by the visitor.

### Main Features

- **Real-time Chat**: Visitors can input any message, and the character responds using AI
- **Multi-turn Conversations**: System maintains conversation history for coherent contextual responses
- **Context Awareness**: AI understands current page content and basic site information
- **Dynamic Context Injection**: Only adds WordPress statistics when relevant keywords are detected, significantly saving tokens
- **Scrollable History**: Long conversations auto-scroll, input box fixed at bottom for easy typing
- **Custom Scrollbar**: Beautiful scrollbar styling
- **Responsive Layout**: Automatically adapts to different screen sizes

### How to Enable

#### Prerequisites

Must first complete these settings in the **LLM Settings** page:

1. Select AI Provider (Gemini, OpenAI, Claude, or Ollama)
2. Set API Key (except for Ollama)
3. Choose appropriate model
4. Test connection to confirm it works

#### Activation Steps

1. Go to **Settings → MP Ukagaka → General Settings**
2. In the "💬 Dialogue Settings" section
3. Check "**Enable Interactive Chat**"
4. Click "Save" button
5. Visit the site frontend, the original "Change Ukagaka" button will become "💬 Chat" button

### How to Use

**Visitor Operations**:

1. Click the "💬 Chat" button in the bottom right corner
2. Chat box expands, displaying welcome message
3. Enter message in the bottom input box
4. Press Enter or click send icon
5. AI generates response based on input
6. Continue chatting, system remembers previous content

**Chat Characteristics**:

- Welcome message displays automatically based on language (Traditional Chinese/Japanese/English)
- Chat history retained on current page (cleared after refresh)
- Long conversations auto-scroll to ensure latest messages visible
- Input box fixed at bottom for continuous conversation

![Interactive Chat Mode Demo](../screenshot3.PNG)

_Interactive Chat Mode: Visitors can directly engage in multi-turn conversations with characters_

### Slash Commands

While in chat mode, special slash commands can be entered directly in the input box.

#### Available Commands

| Command | Available to | Description |
| ------------- | ------------ | --------------------------------- |
| `/help` | Everyone | Displays the list of available commands |
| `/reset` | Admin only | Clears the current chat history |
| `/clear` | Admin only | Same as `/reset` |
| `/debug_mcp` | Admin only | Displays MCP/Abilities diagnostic report |

> **Note**: Admin-only commands are determined by WordPress login status. If a non-logged-in user types these commands, the character will refuse in-character.

#### `/help`

Displays a quick reference of available commands. Accessible to all users regardless of login status.

#### `/reset` and `/clear`

Immediately clears the current page's chat history. Useful when the conversation has gone off track or you want to start fresh without reloading the page.

> **Admin only**: Must be logged in to WordPress as an administrator.

#### `/debug_mcp`

Displays a diagnostic panel showing the status of the Abilities/MCP tool system:

- Integration module load status
- Manager class status
- Number of available tools
- Status of each registered ability (e.g. `get-popular-posts`, `get-bot-blocker-stats`, `ban-ip`, `clear-bot-blocker-data`)

> **Admin only**: Must be logged in to WordPress as an administrator.

### Chat Mode vs Page Awareness

| Feature                | Interactive Chat Mode                     | Page Awareness Mode                |
| ---------------------- | ----------------------------------------- | ---------------------------------- |
| **Trigger Method**     | Visitor actively clicks "Chat" button     | Auto-trigger (probability-based)   |
| **Interactivity**      | Bidirectional multi-turn dialogue         | One-way comments                   |
| **Context**            | Maintains complete conversation history   | Only analyzes current page content |
| **Use Case**           | Visitor actively asks questions, consults | Auto-comments on article content   |
| **Response Frequency** | Responds to every input                   | Based on probability settings      |
| **Token Consumption**  | Accumulates per conversation turn         | Single comment                     |
| **Enable Location**    | General Settings → Dialogue Settings      | AI Settings → Page Awareness       |

### Technical Details

#### Dynamic Context Injection

System uses keyword detection to decide whether to add WordPress statistics:

**Supported Keywords (Traditional Chinese/Japanese/English)**:

- Posts related: 文章, 記事, article, post
- Comments related: 留言, コメント, comment
- Site related: 網站, サイト, site, website
- Technical related: php, wordpress, 外掛, plugins, プラグイン
- Theme related: 主題, テーマ, theme

**Benefits**:

- Most conversations don't include statistics, saving 70%+ tokens
- Only adds when visitors ask relevant questions
- Reduces API costs and response times

#### Context Window Settings

- **Thinking Mode Enabled**: 8192 tokens (Ollama default behavior)
- **Thinking Mode Disabled**: 4096 tokens

#### Response Length Limit

- Default limit is **800 tokens** (configurable via `settings.max_tokens` in `manifest.json`)
- Ensures concise responses, avoids excessive verbosity

### Common Questions

**Q: Is conversation history saved?**
A: Conversation history is only retained in current page memory, cleared after page refresh. Future versions may add persistence features.

**Q: Can I enable both Chat Mode and Page Awareness?**
A: Yes. They don't conflict. Page Awareness auto-triggers on specific pages; Chat Mode is opened by visitors.

**Q: Which AI providers support Chat Mode?**
A: All LLM providers: Ollama, Gemini, OpenAI, Claude.

**Q: How to control token consumption in conversations?**
A: Conversation history accumulates tokens, suggestions:

- Use cheaper models (gemini-2.5-flash, gpt-4o-mini)
- Use Ollama (runs locally, completely free)
- Limit conversation turns (recommend restarting after 10 turns)

**Q: Can chat box styling be customized?**
A: Currently chat box styling is fixed, future versions may open customization options.

---

## Thinking Mode (v2.3.0)

> 🧠 **v2.3.0 Important Change**: Thinking Mode is now **enabled by default** to improve AI response quality!

### What is Thinking Mode?

Thinking Mode lets supported AI models (like Qwen3, DeepSeek) perform internal reasoning before answering. The thinking process is completely separated from the final response, showing only the response content to visitors, ensuring output quality.

### Default Behavior Change (v2.3.0)

**Before (v2.2.0 and earlier)**:

- ❌ Thinking Mode default **disabled** (`think = false`)
- ❌ Configuration note: "Recommended to enable"
- Behavior: AI answers directly, may be less accurate, thinking content may mix into response

**Now (v2.3.0 onwards)**:

- ✅ Thinking Mode default **enabled** (`think = true`)
- ✅ Configuration note: "Enabled by default, optional disable"
- Behavior: AI thinks first then answers, thinking separated from response, only shows response

### Supported Models

Thinking Mode supports the following Ollama models:

- **Qwen3** series: `qwen3:8b`, `qwen3:14b`, etc.
- **DeepSeek** series: `deepseek`, `deepseek-r1`, etc.
- **Custom Modelfile models**: Like the example `frieren` model

> 💡 **Tip**: Cloud services like Gemini, OpenAI, Claude don't support Thinking Mode settings, as their thinking process is handled internally.

### Thinking Mode Advantages

#### 1. Improves Answer Accuracy

AI analyzes questions and thinks about strategy before generating answers, rather than responding directly. Especially suitable for:

- Questions requiring reasoning
- Complex multi-turn conversations
- Responses considering context

#### 2. Separates Thinking from Response

System automatically filters thinking process, ensuring visitors only see final response:

**Thinking Content Detection Mechanisms**:

- Detects `<think>...</think>` XML tags
- Detects `<thinking>...</thinking>` XML tags
- Detects thinking mode text patterns (like "I need", "User is", "Let me think")
- Detects mixed Chinese-English thinking content

#### 3. Advantages in Chat Mode

When enabling thinking in Interactive Chat Mode:

- Context window automatically expands to **8192 tokens**
- Better multi-turn conversation context understanding
- More coherent character personality maintenance

### Technical Details

#### Monologue Mode (Auto Dialogue)

```php
// includes/ai-functions.php
$think = $enable_thinking;  // Default true
```

- Thinking enabled: `think = true`
- Context window: Default value (depends on model)

#### Chat Mode (Interactive Chat)

```php
// includes/chat-api-handlers.php
if ($enable_thinking) {
    $request_body['think'] = true;
    $request_body['options']['num_ctx'] = 8192;  // Expand context window
} else {
    $request_body['think'] = false;
    $request_body['options']['num_ctx'] = 4096;
}
```

- Thinking enabled: context window expands to 8192 tokens
- Thinking disabled: context window is 4096 tokens

### How to Disable Thinking Mode

If you want faster responses (sacrificing some accuracy):

1. Go to **Settings → MP Ukagaka → LLM Settings**
2. In the **Ollama Settings** section
3. Check "**Disable Thinking Mode (Qwen3, DeepSeek, etc.)**"
4. Click "Save" button

**Effects**:

- ✅ Faster response speed (about 1-2 seconds faster)
- ⚠️ Answer accuracy may decrease
- ⚠️ Thinking content may mix into response (requires additional filtering)

### Thinking Mode vs Non-Thinking Mode

| Feature                 | Thinking Mode (Default)               | Non-Thinking Mode                      |
| ----------------------- | ------------------------------------- | -------------------------------------- |
| **Response Quality**    | ⭐⭐⭐⭐⭐ High accuracy              | ⭐⭐⭐ Faster but may be less accurate |
| **Response Speed**      | 🐌 Slightly slower (+1-2s)            | 🚀 Faster                              |
| **Context Window**      | 8192 tokens (Chat Mode)               | 4096 tokens (Chat Mode)                |
| **Thinking & Response** | ✅ Completely separated               | ⚠️ May mix together                    |
| **Use Cases**           | Complex reasoning, long conversations | Simple chat, quick responses           |

### Common Questions

**Q: How much response time does Thinking Mode add?**
A: Usually adds 1-2 seconds, depending on:

- Model size (8B faster than 14B)
- Question complexity (simple questions think faster)
- Hardware configuration (GPU performance)

**Q: Do cloud AI (Gemini/OpenAI/Claude) have Thinking Mode?**
A: These services have built-in optimization, don't need or support manual thinking mode control.

**Q: Will visitors see the thinking content?**
A: No. System automatically filters thinking content, only shows final response.

**Q: How to determine if Thinking Mode is active?**
A: When testing in backend, open browser developer tools (F12) → Network → check Ollama API request, if `"think": true` then enabled.

**Q: Can I enable/disable thinking for different scenarios?**
A: Currently global setting, cannot adjust for specific pages or scenarios. Recommend keeping default enabled unless you absolutely need faster response speed.

---

---

## Automated Diary Settings (Diary Settings)

> 📓 **V2.5.0 New Feature**: Let your character automatically write diaries!

Go to **Settings** → **MP Ukagaka** → **Diary Settings**

### What is the Automated Diary Feature?

This is a feature that allows the Ukagaka character to "live automatically". When enabled, the character will automatically write and publish diary posts with a very low probability (set by you). These diaries will include:

- Today's date and season
- Current weather conditions (if weather feature is enabled)
- Mood and thoughts fitting the character's personality
- Random daily events

### Basic Settings

#### 1. Enable Automated Diary

Check this option to turn on the feature. When enabled, the system will check once a day whether to trigger diary generation.

#### 2. Post Category

Select which category to publish diary posts to.

> 💡 **Recommendation**: Create a dedicated category in WordPress Posts first, e.g., "Frieren's Notes" (slug: `frieren-notes`), and then select it here.

#### 3. Post Author

Select which WordPress user to publish the diary as. It is usually recommended to create a dedicated "Character Account" or use the administrator account directly.

#### 4. Trigger Probability

Set the daily probability of triggering a diary (1% - 10%).

- **2%**: About 0~1 posts per month (Rare)
- **5%**: About 1~2 posts per month (Occasional)
- **10%**: About 3 posts per month (Frequent)

> ⚠️ To avoid flooding with diaries, it is recommended to keep it around 2-5%.

#### 5. Post Signature

Set the signature text to be appended to the end of each diary post. Leave blank to not append.
Example: `*This entry was written by Frieren, the mage who has lived for over a thousand years.`

### Diary AI Provider

The diary feature uses **independent AI settings**, which means you can:

- Use Ollama (Free) for chat functions
- Use Gemini 2.5 Flash (High quality and cheap) for diary functions

This ensures response speed for daily conversations while guaranteeing literary quality for diary content.

Supported providers are the same as LLM settings:

- **Gemini**: Recommended `Gemini 2.5 Flash`
- **OpenAI**: Recommended `GPT-4.1 Mini`
- **Claude**: Recommended `Claude Sonnet 4.5`
- **Ollama**: Supports custom models

### Diary Topic Configuration (diary.json)

What the diary "writes about" is controlled by `diary.json` in the character folder. This file defines the diary's **topic categories** and **prompts**.

**File Location:** `ghost/CharacterID/diary.json`

#### Category Weighting System

Each category has a `weight` value. The higher the weight, the more likely that category will be selected.

**Default Character (Frieren) Category Examples:**

| Category            | Weight | Description                             |
| ------------------- | ------ | --------------------------------------- |
| `daily_observation` | 20     | Daily observations, street scenes       |
| `magic_research`    | 18     | Magic research, experiment notes        |
| `memories`          | 16     | Past journey memories                   |
| `companions`        | 15     | Interactions with companions            |
| `time_awareness`    | 12     | Sense of time, millennial perspective   |
| `emotional_growth`  | 10     | Emotional growth, self-discovery        |
| `first_class_exam`  | 8      | First-class mage exam                   |
| `demon_encounters`  | 5      | Demon encounter records                 |
| `mimic_adventures`  | 4      | Treasure chests and dungeon exploration |
| `food_thoughts`     | 3      | Food thoughts                           |
| `sleep_laziness`    | 3      | Oversleeping diary                      |
| `purchases_regrets` | 2      | Impulse buying regrets                  |
| `philosophical`     | 2      | Philosophical thoughts                  |

#### Customizing Diary Topics

If you want to customize diary topics, edit `diary.json`:

```json
{
  "categories": {
    "my_category": {
      "weight": 10,
      "title_themes": ["Title Keyword 1", "Title Keyword 2"],
      "prompts": [
        "Prompt 1 (about 150-250 characters)",
        "Prompt 2 (about 150-250 characters)"
      ]
    }
  }
}
```

- **weight**: Weight value, relative selection probability
- **title_themes**: Keywords used to generate diary titles
- **prompts**: AI randomly selects one to generate diary content

### Diary Title Prefix

Diary titles automatically have a prefix added (e.g., `[フリーレン手記]`). This prefix is defined in the `diary_title_prefix` field of `dynamics.json`.

This prefix has two purposes:

1. **Visual Identification**: Readers can immediately see this is a character's diary
2. **Page Awareness**: When the character sees their own diary on the website, they have a special reaction (defined in `page_aware_own_diary`)

### Test Feature

There is a "🧪 Test" section at the bottom of the settings page. Click the **"Generate Diary Now (Test)"** button, and the system will immediately attempt to generate and publish a diary post.

This can be used to confirm:

1. AI connection is normal
2. Post category and author settings are correct
3. Diary content style is as expected

---

## Technical Support

If you have issues, please:

1. Consult this User Guide.
2. Check the [FAQ](#faq) section.
3. Visit [萌えログ.COM](https://www.moelog.com/).

---

**Made with ❤ for WordPress**
