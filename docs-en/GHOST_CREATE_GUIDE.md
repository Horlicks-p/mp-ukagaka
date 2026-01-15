# Character Personality Creation Guide

> 🎭 How to create a new character personality for MP Ukagaka

---

## 📑 Table of Contents

1. [Overview](#overview)
2. [Required Files](#required-files)
3. [Folder Structure](#folder-structure)
4. [manifest.json Format](#manifestjson-format)
5. [system_prompt.md Usage](#system_promptmd-usage)
6. [prompts.json Format (LLM Mode)](#promptsjson-format-llm-mode)
7. [weights.json Format (LLM Mode)](#weightsjson-format-llm-mode)
8. [decorations.json Format (Optional)](#decorationsjson-format-optional)
9. [Shell Images](#shell-images)
10. [JavaScript Scripts (Optional)](#javascript-scripts-optional)
11. [Upload &amp; Usage](#upload--usage)
12. [Full Example](#full-example)

---

## Overview

In MP Ukagaka, each character personality is stored in the `ghost/` directory within an independent folder named after the Personality ID. A complete personality includes the following:

- **Required Files**: `manifest.json`, `shell/` folder (containing character images)
- **LLM Mode Files** (when using AI): `prompts.json`, `weights.json`, `system_prompt.md` (or the `system_prompt` field in `manifest.json`)
- **Optional Files**: `decorations.json`, `decorations/` folder, JavaScript scripts, `dynamics.json`

---

## Required Files

To create a new personality, **you need at least the following files**:

1. **`manifest.json`** - Personality metadata and settings
2. **`shell/{PersonalityID}/{PersonalityID}.png`** - The character's main image (at least one)

### Minimal Example

```
ghost/
└── MyCharacter/
    ├── manifest.json
    └── shell/
        └── MyCharacter/
            └── MyCharacter.png
```

---

## Folder Structure

The complete personality folder structure is as follows:

```
ghost/
└── {PersonalityID}/        # Personality folder (e.g., Frieren, Sakura_Laurel)
    ├── manifest.json       # Required: Metadata and settings
    ├── system_prompt.md    # Recommended: System Prompt (Markdown format)
    │
    ├── shell/              # Required: Character image folder
    │   └── {PersonalityID}/ # Image subfolder (usually same as Personality ID)
    │       ├── {PersonalityID}.png          # Main image (Required)
    │       ├── {PersonalityID}[0].png       # Animation frame (Optional)
    │       ├── {PersonalityID}[1].png       # Animation frame (Optional)
    │       └── ...
    │
    ├── decorations/        # Optional: Decoration image folder
    │   ├── item1.png
    │   └── item2.png
    │
    ├── prompts.json        # LLM Mode: Dialogue category prompts
    ├── weights.json        # LLM Mode: Category weight configuration
    ├── dynamics.json       # LLM Mode: Dynamic templates (Optional)
    ├── decorations.json    # Optional: Decoration configuration
    └── {PersonalityID}.js  # Optional: JavaScript animation script
```

---

## manifest.json Format

`manifest.json` is the core configuration file for a personality, defining basic information and settings.

### Required Fields

- `id`: Unique identifier for the personality (Alphanumeric, underscores, hyphens; CamelCase starting with an uppercase letter is recommended)
- `name`: Character display name (Default language)
- `shell_folder`: Name of the shell image folder (Usually same as `id`)

### Full Field Description

```json
{
  "id": "MyCharacter",                    // Required: Personality ID (Unique Identifier)
  "name": "Character Name",               // Required: Character Display Name
  "name_en": "Character Name",            // Optional: English Name
  "name_zh": "Character Name",            // Optional: Chinese Name
  "version": "1.0.0",                     // Optional: Version Number
  "author": "Author Name",                // Optional: Author Information
  "description": "Character Description", // Optional: Character Introduction
  "description_en": "Character description", // Optional: English Description
  "language": "en",                       // Optional: Primary Language (ja/zh-TW/en)
  "shell_folder": "MyCharacter",          // Required: Shell Image Folder Name
  "decorations_folder": "decorations",    // Optional: Decoration Folder Name (Default "decorations")
  "script": "mycharacter.js",             // Optional: JavaScript Script Filename
  
  "settings": {                           // Optional: Behavior Settings
    "max_response_length": 500,           // Response Length Limit (Characters, default 500)
    "max_tokens": 800,                     // Token Limit for API Calls (default 800)
    "speech_style": "Casual",             // Speech Style (Metadata, currently unused)
    "tone": "Calm",                       // Tone (Metadata, currently unused)
    "emoji_style": "minimal"              // Emoji Style (Metadata, currently unused)
  },
  
  "character_traits": {                   // Optional: Character Traits (Metadata, currently unused)
    "age": "18",
    "race": "Human",
    "occupation": "Student",
    "personality": ["Cheerful", "Lively"],
    "aliases": ["Nickname1", "Nickname2"]
  },
  
  "system_prompt": "You are...",          // Optional: System Prompt (String or Array)
                                           // Note: Using system_prompt.md file is recommended
}
```

### Example

```json
{
  "id": "Frieren",
  "name": "Frieren",
  "name_en": "Frieren",
  "name_zh": "芙莉蓮",
  "version": "1.0.0",
  "author": "Horlicks-JP",
  "description": "An elven mage who has lived for over a thousand years. Speaks in a calm tone and collects magic as a hobby.",
  "language": "en",
  "shell_folder": "Frieren",
  "decorations_folder": "decorations",
  "script": "frieren.js",
  "settings": {
    "max_response_length": 500,
    "max_tokens": 800,
    "speech_style": "Casual",
    "tone": "Calm",
    "emoji_style": "minimal"
  }
}
```

### settings Field Description

The `settings` object contains behavior settings for the character, and the response length limiting mechanism has been unified into a three-layer protection system:

#### Response Length Settings

- **`max_response_length`** (Default: 500)

  - Backend truncation limit (character count)
  - When AI responses exceed this length, the system will automatically truncate and add `...`
  - Applied to all dialogue types (page awareness, first visit greeting, interactive chat, touch zones, decoration clicks, spontaneous dialogue)
- **`max_tokens`** (Default: 800)

  - Token limit for API calls
  - Controls the maximum number of tokens the AI model can generate
  - Approximately equals 600-800 characters (depending on language and content)
  - Used by all AI dialogue types

#### Three-Layer Protection Mechanism

The system implements a unified three-layer response length limiting mechanism:

1. **Prompt Suggestion**: 30-250 characters (soft guidance)

   - Suggests to AI in System Prompt and User Prompt to stay within 30-250 characters
2. **API max_tokens**: 800 (configurable via `max_tokens`)

   - Limits the maximum tokens the AI model can generate
   - Read from `manifest.json`'s `settings.max_tokens`, default 800
3. **Backend Truncation**: 500 characters (configurable via `max_response_length`)

   - Final safety layer
   - Read from `manifest.json`'s `settings.max_response_length`, default 500

### JSON Format Rules

1. **Encoding**: Must use UTF-8 encoding.
2. **Syntax**:
   - Strings must be enclosed in double quotes `"`.
   - No comma after the last property.
   - No comma after the last element in an array or object.
3. **Comments**: Standard JSON does not support comments, but you can use `_comment` field for notes.
4. **Validation**: Use online JSON validation tools to check syntax.

**Correct Example:**

```json
{
  "id": "MyCharacter",
  "name": "Character Name",
  "settings": {
    "max_response_length": 500,
    "max_tokens": 800
  }
}
```

**Incorrect Example:**

```json
{
  "id": "MyCharacter",
  "name": "Character Name",  // ❌ JSON does not support comments
  "settings": {
    "max_response_length": 129,  // ❌ No comma after the last property
  }
}
```

---

## system_prompt.md Usage

`system_prompt.md` is a Markdown file used to define the character's System Prompt, which has the highest priority.

### Priority Order

1. **`system_prompt.md`** (Highest Priority) ⭐
2. `system_prompt` field in `manifest.json`
3. Backend Global Settings (Fallback)

### File Location

```
ghost/{PersonalityID}/system_prompt.md
```

### Format Requirements

- **Encoding**: UTF-8
- **Format**: Plain Markdown text file
- **Content**: The character's complete System Prompt. Markdown format can be used to enhance readability.

### Markdown Format Recommendations

Using Markdown makes the System Prompt more structured and readable:

```markdown
# Character Definition

You are "Character Name". You must follow the rules below.

## Dialogue Protocol

1. **Response Length**: Must be within 40 words.
2. **First Person**: Always use "I".
3. **Tone**: Casual only.

## Background Settings

- Character Background Description
- Personality Traits
- Speaking Style

## Behavioral Rules

- Rule 1
- Rule 2
```

### Variable Support

System Prompt supports the following variable substitutions:

- `{{ukagaka_display_name}}`: Character Name
- `{{language}}`: Response Language (zh-TW, ja, en)
- `{{time_context}}`: Time Context (e.g., "Jan 2 (Thu) - Winter Morning")
- `{{wp_version}}`: WordPress Version
- `{{php_version}}`: PHP Version
- `{{theme_name}}`: Theme Name
- `{{theme_version}}`: Theme Version
- `{{theme_author}}`: Theme Author
- `{{post_count}}`: Post Count
- `{{comment_count}}`: Comment Count
- `{{category_count}}`: Category Count
- `{{tag_count}}`: Tag Count
- `{{days_operating}}`: Days the website has been operating

**Example:**

```markdown
You are a character named "{{ukagaka_display_name}}".

The current time is {{time_context}}.
```

### Full Example

Refer to `example/system-prompt-markdown-example.md` for a complete Markdown format example.

---

## prompts.json Format (LLM Mode)

`prompts.json` defines categories of prompts used when the LLM generates dialogue. Each category contains multiple prompt templates, which the system randomly selects based on weights.

### File Structure

```json
{
  "_comment": "Character Name - Prompt Categories",
  "_format_version": "1.0",
  "_variable_placeholders": [
    "{time_context}", "{visitor_country}", "{bot_name}"
  ],
  
  "category_name": [
    "Prompt Template 1",
    "Prompt Template 2",
    "Prompt Template 3"
  ]
}
```

### Category Naming Recommendations

- `greeting`: Greetings
- `casual`: Casual Chat
- `observation`: Observations
- `memory`: Memories
- `time_aware`: Time Awareness
- `magic_collection`: Magic Collection (or character-specific interests)
- `self_awareness`: Self Awareness
- `emotional_density`: Emotional Density
- etc...

### Variable Placeholders

You can use variable placeholders in prompts, which the system will automatically substitute:

- `{time_context}`: Time Context
- `{wp_version}`: WordPress Version
- `{theme_name}`: Theme Name
- `{visitor_country}`: Visitor Country
- `{bot_name}`: BOT Name (if detected)
- etc...

### Example

```json
{
  "_comment": "MyCharacter - Prompt Categories",
  "_format_version": "1.0",
  
  "greeting": [
    "Acknowledge the return visit lightly with a calm attitude",
    "Show slight surprise with an 'Eh' at the visit after a long time"
  ],
  
  "casual": [
    "State a calm impression about something noticed",
    "Mutter something suddenly remembered that has no particular meaning"
  ],
  
  "time_aware": [
    "State the realization that time is too short for humans",
    "Treat a period of 'just 10 years' as a very short time"
  ]
}
```

---

## weights.json Format (LLM Mode)

`weights.json` defines the weights for each dialogue category. The higher the weight, the greater the probability that the category will be selected.

### File Structure

```json
{
  "_comment": "Character Name - Category Weights Configuration",
  "_format_version": "1.0",
  
  "base_weights": {
    "category_name": 10,
    "another_category": 15
  },
  
  "time_adjustments": {
    "Morning": {
      "category_name": 20
    },
    "Night": {
      "category_name": 5
    }
  }
}
```

### base_weights

Base weights used across all time periods. Recommended range: 1-20.

- Higher value = Higher probability of selection
- Recommended for frequent categories: 10-15
- Recommended for rare categories: 1-5

### time_adjustments

Adjust weights based on time periods, merged with `base_weights`.

**Supported Time Periods (Keys correspond to internal logic, use these keys):**

- `深夜` (Late Night): 23:00-04:59
- `睡眠時間帯` (Sleep Time): 00:00-05:59
- `朝` (Morning): 05:00-11:59
- `昼` (Afternoon): 12:00-17:59
- `夜` (Evening): 18:00-22:59

### Example

```json
{
  "_comment": "MyCharacter - Category Weights",
  "_format_version": "1.0",
  
  "base_weights": {
    "casual": 15,
    "observation": 15,
    "greeting": 6,
    "memory": 8,
    "time_aware": 10
  },
  
  "time_adjustments": {
    "深夜": {
      "memory": 15,
      "time_aware": 15,
      "casual": 5
    },
    "朝": {
      "greeting": 20,
      "casual": 15
    }
  }
}
```

---

## decorations.json Format (Optional)

`decorations.json` defines the character's decorations (clickable interactive elements).

### File Structure

```json
{
  "_comment": "Character Name - Decoration Click Prompts",
  "_format_version": "1.0",
  
  "decorations_base_folder": "decorations",
  
  "items": [
    {
      "type": "item_type",
      "image": "item.png",
      "position": {
        "top": "82%",
        "right": "-62px",
        "left": "auto"
      },
      "size": {
        "width": "90px",
        "height": "auto"
      },
      "transform": "translateY(-50%)",
      "z_index": 10,
      "prompt": "Prompt for LLM when user clicks this decoration (within 50 words)"
    }
  ]
}
```

### Field Description

- `type`: Decoration Type (Unique Identifier)
- `image`: Image Filename (Stored in `decorations/` folder)
- `position`: CSS Positioning (`top`, `left`, `right`)
- `size`: Image Size (`width`, `height`)
- `transform`: CSS transform (Optional)
- `z_index`: Layer Order
- `prompt`: LLM prompt on click

### Example

```json
{
  "_comment": "MyCharacter - Decorations",
  "_format_version": "1.0",
  
  "decorations_base_folder": "decorations",
  
  "items": [
    {
      "type": "suitcase",
      "image": "suitcase.png",
      "position": {
        "top": "82%",
        "right": "-62px",
        "left": "auto"
      },
      "size": {
        "width": "90px",
        "height": "auto"
      },
      "transform": "translateY(-50%)",
      "z_index": 10,
      "prompt": "User clicked the suitcase. Please talk about this suitcase (within 50 words)."
    }
  ]
}
```

---

## Shell Images

Shell images are the visual representation of the character, stored in the `shell/{PersonalityID}/` folder.

### Required Files

- **`{PersonalityID}.png`**: Main Image (Required)

### Optional Files (Animation)

- `{PersonalityID}[0].png`, `{PersonalityID}[1].png`, ...: Animation Frames
- `{PersonalityID}[s].png`: Special State Image
- `{PersonalityID}[w1].png`, `{PersonalityID}[w2].png`, ...: Wake-up Animation Frames

### Naming Convention

1. **Main Image**: `{PersonalityID}.png` (e.g., `Frieren.png`)
2. **Animation Frames**: `{PersonalityID}[Number].png` (e.g., `Frieren[0].png`, `Frieren[1].png`)
3. **Special State**: `{PersonalityID}[Letter].png` (e.g., `Frieren[s].png`)

### Image Format

- **Format**: PNG (Recommended) or JPG
- **Dimensions**: Recommended 200-400px width, height customizable
- **Background**: Transparent background recommended (PNG)

### File Structure Example

```
shell/
└── Frieren/
    ├── Frieren.png        # Main Image (Required)
    ├── Frieren[0].png     # Animation Frame 0
    ├── Frieren[1].png     # Animation Frame 1
    ├── Frieren[2].png     # Animation Frame 2
    ├── Frieren[s].png     # Special State
    ├── Frieren[w1].png    # Wake-up Animation 1
    ├── Frieren[w2].png    # Wake-up Animation 2
    └── ...
```

---

## JavaScript Scripts (Optional)

If you need custom animation or interaction behavior, you can create a JavaScript script.

### File Location

```
ghost/{PersonalityID}/{PersonalityID}.js
```

### Specifying in manifest.json

```json
{
  "id": "MyCharacter",
  "script": "mycharacter.js"
}
```

### Basic Structure

The JavaScript script needs to register with `window.mpuFrierenManager` (or a similar character manager). Refer to `ghost/Frieren/frieren.js` for a complete example.

---

## Upload & Usage

### Method 1: ZIP Upload (Recommended)

1. Package all personality files into a ZIP file.
2. Log in to WordPress Admin → **Settings** → **MP Ukagaka** → **Create New Ukagaka**.
3. Select the ZIP file and upload.
4. The system will automatically unzip and verify.
5. After confirming the preview information is correct, click "Confirm and Create".

### Method 2: Manual Upload

1. Via FTP or File Manager, upload the personality folder to `wp-content/plugins/mp-ukagaka/ghost/`.
2. Log in to WordPress Admin → **Settings** → **MP Ukagaka** → **Ukagakas**.
3. Manually add the new character configuration.

### ZIP File Structure Requirements

The ZIP file, when unzipped, should directly contain `manifest.json` and the `shell/` folder:

```
MyCharacter.zip
└── (Unzipped)
    ├── manifest.json
    ├── system_prompt.md
    ├── shell/
    │   └── MyCharacter/
    │       └── MyCharacter.png
    ├── prompts.json
    └── weights.json
```

**Note**: The ZIP file **should not** contain a top-level folder name (e.g., `MyCharacter/manifest.json`); it should be the files themselves.

---

## Full Example

Here is a minimal character example:

### 1. Folder Structure

```
ghost/
└── SimpleCharacter/
    ├── manifest.json
    └── shell/
        └── SimpleCharacter/
            └── SimpleCharacter.png
```

### 2. manifest.json

```json
{
  "id": "SimpleCharacter",
  "name": "Simple Character",
  "shell_folder": "SimpleCharacter"
}
```

### 3. system_prompt.md (Optional, Recommended)

```markdown
# Character Definition

You are "Simple Character". Please interact with visitors in a concise and friendly tone.

## Dialogue Rules

- Keep responses within 50 words
- Use casual tone (no honorifics)
- Use "I" for first person
```

### 4. prompts.json (LLM Mode, Optional)

```json
{
  "_comment": "SimpleCharacter - Prompt Categories",
  "_format_version": "1.0",
  
  "greeting": [
    "Greet with a calm attitude",
    "Call out lightly to the visitor"
  ],
  
  "casual": [
    "State an opinion on something noticed",
    "Mutter something suddenly remembered"
  ]
}
```

### 5. weights.json (LLM Mode, Optional)

```json
{
  "_comment": "SimpleCharacter - Category Weights",
  "_format_version": "1.0",
  
  "base_weights": {
    "greeting": 10,
    "casual": 15
  }
}
```

---

## Summary

Basic steps to create a new personality:

1. ✅ Create `manifest.json` (Required)
2. ✅ Prepare `shell/{PersonalityID}/{PersonalityID}.png` (Required)
3. ⭐ Create `system_prompt.md` (Recommended, for defining character behavior)
4. 📝 Create `prompts.json` and `weights.json` (For LLM Mode)
5. 🎨 Add `decorations.json` and decoration images (Optional)
6. 📦 Package into ZIP and upload

**Reference Example**: Check the `ghost/Frieren/` folder to understand the full personality structure.

---

**Last Updated**: 2026-01-15
