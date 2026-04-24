# Ghost Creation Guide

> 🎭 How to create a new character personality for MP Ukagaka

---

## 📑 Table of Contents

1. [Overview](#overview)
2. [Required Files](#required-files)
3. [Folder Structure](#folder-structure)
4. [manifest.json Format Guide](#manifestjson-format-guide)
5. [Personality Prompt Structure](#personality-prompt-structure)
6. [prompts.json Format Guide (LLM Mode)](#promptsjson-format-guide-llm-mode)
7. [weights.json Format Guide (LLM Mode)](#weightsjson-format-guide-llm-mode)
8. [decorations.json Format Guide (Optional)](#decorationsjson-format-guide-optional)
9. [Shell Image Files](#shell-image-files)
10. [JavaScript Scripts (Optional)](#javascript-scripts-optional)
11. [Upload and Use](#upload-and-use)
12. [Complete Example](#complete-example)

---

## Overview

In MP Ukagaka, each character personality is stored under the `ghost/` folder in an independent folder named after its personality ID. A complete personality usually contains the following:

- **Required Files**: `manifest.json`, `shell/` folder (contains character images).
- **Core Files for LLM Mode** (When using AI): `prompts.json`, `weights.json`, and personality prompt files.
- **Currently Recommended Personality Prompt Structure**: `instructions.md` + `personality.md`.
- **Legacy Compatibility Approach**: `system_prompt.md` or the `system_prompt` field in `manifest.json`.
- **Optional Files**: `dynamics.json`, `decorations.json`, `decorations/`, `touchzones.json`, `sleep_mode.json`, `calendar.json`, `emoji-keywords.json`, `diary.json`, JavaScript scripts.

---

## Required Files

To create a new personality, **the following files are required at a minimum**:

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
└── {PersonalityID}/              # Personality folder (e.g., Frieren, Sakura_Laurel)
    ├── manifest.json       # Required: Metadata and settings
    ├── instructions.md     # Recommended: Behavioral rules / Dialogue protocols
    ├── personality.md      # Recommended: Personality background / Character description
    ├── system_prompt.md    # Legacy compat: Legacy prompt fallback
    │
    ├── shell/              # Required: Character image folder
    │   └── {PersonalityID}/       # Image subfolder (usually the same name as the Personality ID)
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
    ├── touchzones.json     # Optional: Touch zone configuration
    ├── sleep_mode.json     # Optional: Sleep mode configuration
    ├── calendar.json       # Optional: Holiday / Anniversary configuration
    ├── diary.json          # Optional: AI Diary configuration
    ├── emoji-keywords.json # Optional: Emoji keyword configuration
    └── {PersonalityID}.js         # Optional: JavaScript animation script
```

---

## manifest.json Format Guide

`manifest.json` is the core configuration file for the personality, defining its basic information and settings.

### Required Fields

- `id`: The unique identifier for the personality (alphanumeric, underscores, hyphens; PascalCase is recommended).
- `name`: Character display name (default language).
- `shell_folder`: Name of the shell image folder (usually the same as `id`).

### Complete Field Descriptions

```json
{
  "id": "MyCharacter",                    // Required: Personality ID (Unique identifier)
  "name": "Character Name",               // Required: Character display name
  "name_en": "Character Name",            // Optional: English name
  "name_zh": "Character Name",            // Optional: Chinese name
  "version": "1.0.0",                     // Optional: Version number
  "author": "Author Name",                // Optional: Author information
  "description": "Character description", // Optional: Character introduction
  "description_en": "Character description",  // Optional: English description
  "language": "ja",                       // Optional: Primary language (ja/zh-TW/en)
  "shell_folder": "MyCharacter",          // Required: Shell image folder name
  "decorations_folder": "decorations",    // Optional: Decoration folder name (Default "decorations")
  "script": "mycharacter.js",             // Optional: Old format, single JavaScript script
  "scripts": ["mycharacter.js"],          // Optional: New format, supports multiple scripts
  
  "settings": {                           // Optional: Behavior settings
    "max_response_length": 500,           // Response length limit (characters, default 500)
    "max_tokens": 800,                     // Token limit during API call (default 800)
    "speech_style": "常体",                // Speech style (metadata, currently not actively used)
    "tone": "淡々とした",                  // Tone (metadata, currently not actively used)
    "emoji_style": "minimal"              // Emoji style (metadata, currently not actively used)
  },
  
  "character_traits": {                   // Optional: Character traits (metadata, currently not actively used)
    "age": "18",
    "race": "Human",
    "occupation": "Student",
    "personality": ["Cheerful", "Lively"],
    "aliases": ["Nickname1", "Nickname2"]
  },
  
  "system_prompt": "You are...",          // Optional: Old format prompt fallback (string or array)
                                           // Recommended to use instructions.md + personality.md instead
}
```

### Example

```json
{
  "id": "Frieren",
  "name": "フリーレン",
  "name_en": "Frieren",
  "name_zh": "芙莉蓮",
  "version": "1.0.0",
  "author": "和製ホーリックス",
  "description": "An elven mage who has lived for over a thousand years. She speaks in a flat tone and enjoys collecting magic.",
  "language": "ja",
  "shell_folder": "Frieren",
  "decorations_folder": "decorations",
  "script": "frieren.js",
  "settings": {
    "max_response_length": 500,
    "max_tokens": 800,
    "speech_style": "常体",
    "tone": "淡々とした",
    "emoji_style": "minimal"
  }
}
```

### settings Field Description

The `settings` object contains the behavioral settings of the character, where the word count limitation mechanism has been unified into three layers of protection:

#### Character Limit Settings

- **`max_response_length`** (Default: 500)

  - Backend truncation limit (character count).
  - When the AI response exceeds this length, the system will automatically truncate it and append `...`.
  - This limit applies to all dialogue types (page-aware, first-time visitor, interactive dialogue, touch zones, decoration clicks, spontaneous dialogues).
- **`max_tokens`** (Default: 800)

  - Token limit for API calls.
  - Controls the maximum number of tokens generated by the AI model's response.
  - Roughly equals 600-800 characters (depending on language and content).
  - Used for all AI dialogue types.

#### Three-Layer Protection Mechanism

The system implements a unified three-layer character limit mechanism:

1. **Prompt Recommendation**: 30-150 words (soft guidance).

   - Instructs the AI in the System Prompt and User Prompt to keep the response within the 30-250 word range.
2. **API max_tokens**: 800 (Configurable via `max_tokens`).

   - Limits the maximum tokens generated by the AI model.
   - Read from `settings.max_tokens` in `manifest.json`, default is 800.
3. **Backend Truncation**: 150 words (Configurable via `max_response_length`).

   - The final safety net.
   - Read from `settings.max_response_length` in `manifest.json`, default is 500.

### JSON Formatting Rules

1. **File Encoding**: Must use UTF-8 encoding.
2. **Syntax**:
   - Use double quotes `"` to wrap strings.
   - Do **not** place a comma after the last property.
   - Do **not** place a comma after the last element of an array or object.
3. **Comments**: The JSON standard does not support comments, but you can use the `_comment` field for explanation.
4. **Validation**: You can use online JSON validation tools to check the syntax.

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

## Personality Prompt Structure

It is currently recommended to use the **modular prompt** structure to define the character:

- `instructions.md`: Behavioral rules, tone, formatting restrictions, dialogue protocols.
- `personality.md`: Background setting, worldview, preferences, supplementary personality details.

The system will read `instructions.md` first, followed by `personality.md`. This is currently the **highest priority**.

### Priority Order

1. **`instructions.md` + `personality.md`** (Currently Recommended) ⭐
2. `system_prompt.md` (Legacy fallback)
3. `system_prompt` field in `manifest.json` (Legacy fallback)
4. Global settings in the admin panel (Fallback)

### File Locations

```text
ghost/{PersonalityID}/instructions.md
ghost/{PersonalityID}/personality.md
```

### Format Requirements

- **Encoding**: UTF-8
- **Format**: Plain Markdown text files
- **Content Suggestions**:
  - `instructions.md` focuses on rules and output constraints.
  - `personality.md` focuses on personality and background.

### Suggested Approaches

`instructions.md`

```markdown
# Dialogue Protocol

- Keep responses concise.
- Use casual speech.
- Use "私" for the first person.
- Avoid breaking character.
```

`personality.md`

```markdown
# Character Setting

You are "Character Name".

- Quiet personality.
- Distinct preferences for specific topics.
- Slower pace of speaking.
```

### Legacy Compatibility

If you want to maintain the old personality format, you can still use either of the following methods:

- `ghost/{PersonalityID}/system_prompt.md`
- `system_prompt` in `manifest.json`

However, when creating new personalities, it is recommended to directly use `instructions.md + personality.md`.

### Variable Support

Personality prompts support the following variable replacements:

- `{{ukagaka_display_name}}`: Character name
- `{{language}}`: Response language (zh-TW, ja, en)
- `{{time_context}}`: Time context (e.g., "1月2日（木曜日）・冬の朝")
- `{{wp_version}}`: WordPress version
- `{{php_version}}`: PHP version
- `{{theme_name}}`: Theme name
- `{{theme_version}}`: Theme version
- `{{theme_author}}`: Theme author
- `{{post_count}}`: Number of posts
- `{{comment_count}}`: Number of comments
- `{{category_count}}`: Number of categories
- `{{tag_count}}`: Number of tags
- `{{days_operating}}`: Days the site has been operating

**Example:**

```markdown
You are the character "{{ukagaka_display_name}}".

The current time is {{time_context}}.
```

### Complete Example

Refer to `example/system-prompt-markdown-example.md` to understand the Markdown prompt format. If you want to align with the current architecture, it is recommended to split the content into `instructions.md` and `personality.md`.

---

## prompts.json Format Guide (LLM Mode)

`prompts.json` defines the prompt categories used when the LLM generates spontaneous dialogue. Each category contains multiple prompt templates, and the system selects one randomly based on weights.

### File Structure

```json
{
  "_comment": "Character Name - Prompt Categories",
  "_format_version": "1.0",
  "_variable_placeholders": [
    "{time_context}", "{visitor_country}", "{bot_name}"
  ],
  
  "category_name": [
    "Prompt template 1",
    "Prompt template 2",
    "Prompt template 3"
  ]
}
```

### Suggested Category Naming

- `greeting`: Greetings
- `casual`: Casual chat
- `observation`: Observations
- `memory`: Memories
- `time_aware`: Time-awareness
- `magic_collection`: Magic collection (or corresponding character interests)
- `self_awareness`: Self-awareness
- `emotional_density`: Emotional density
- etc...

### Variable Placeholders

You can use variable placeholders in the prompts, and the system will automatically replace them:

- `{time_context}`: Time context
- `{wp_version}`: WordPress version
- `{theme_name}`: Theme name
- `{visitor_country}`: Visitor's country
- `{bot_name}`: BOT name (if detected)
- etc...

### Example

```json
{
  "_comment": "MyCharacter - Prompt Categories",
  "_format_version": "1.0",
  
  "greeting": [
    "Acknowledge the revisit lightly with a flat attitude.",
    "Show slight surprise with an 'Eh?' at the first visit in a while."
  ],
  
  "casual": [
    "Give a flat impression about something that catches your eye.",
    "Mutter something suddenly remembered, with no particular meaning."
  ],
  
  "time_aware": [
    "Express that time for humans feels too short.",
    "Treat the period of 'just 10 years' as a very brief moment."
  ]
}
```

---

## weights.json Format Guide (LLM Mode)

`weights.json` defines the weights for each dialogue category. The higher the weight, the greater the chance that category is selected.

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
    "朝": {
      "category_name": 20
    },
    "夜": {
      "category_name": 5
    }
  }
}
```

### base_weights

Base weights used across all time periods. Recommended value range: **1-20**.

- Higher value = higher chance of being selected.
- Recommended to set frequently used categories to 10-15.
- Rarely used categories to 1-5.

### time_adjustments

Adjusts weights based on the time period. Will be merged with `base_weights`.

**Supported time periods:**

- `深夜` (Late Night): 23:00-04:59
- `睡眠時間帯` (Sleep Time): 00:00-05:59
- `朝` (Morning): 05:00-11:59
- `昼` (Noon/Afternoon): 12:00-17:59
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

## decorations.json Format Guide (Optional)

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
      "prompt": "LLM prompt when the user clicks this decoration (within 50 characters)"
    }
  ]
}
```

### Field Description

- `type`: Decoration type (unique identifier).
- `image`: Image file name (stored in the `decorations/` folder).
- `position`: CSS positioning (`top`, `left`, `right`).
- `size`: Image size (`width`, `height`).
- `transform`: CSS transform (optional).
- `z_index`: Layer order.
- `prompt`: LLM prompt upon clicking.

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
      "prompt": "The user clicked the suitcase. Please talk about this suitcase (within 50 characters)."
    }
  ]
}
```

---

## Shell Image Files

Shell images are the visual representation of the character, stored in the `shell/{PersonalityID}/` folder.

### Required Files

- **`{PersonalityID}.png`**: Main image (Required)

### Optional Files (Animations)

- `{PersonalityID}[0].png`, `{PersonalityID}[1].png`, ...: Animation frames
- `{PersonalityID}[s].png`: Special state image
- `{PersonalityID}[w1].png`, `{PersonalityID}[w2].png`, ...: Wake-up animation frames

### Naming Conventions

1. **Main image**: `{PersonalityID}.png` (e.g., `Frieren.png`)
2. **Animation frames**: `{PersonalityID}[Number].png` (e.g., `Frieren[0].png`, `Frieren[1].png`)
3. **Special states**: `{PersonalityID}[Letter].png` (e.g., `Frieren[s].png`)

### Image Formats

- **Format**: PNG (Recommended) or JPG
- **Size**: Recommended 200-400px width, height is custom
- **Background**: Transparent background is recommended (PNG)

### Example File Structure

```
shell/
└── Frieren/
    ├── Frieren.png        # Main image (Required)
    ├── Frieren[0].png     # Animation frame 0
    ├── Frieren[1].png     # Animation frame 1
    ├── Frieren[2].png     # Animation frame 2
    ├── Frieren[s].png     # Special state
    ├── Frieren[w1].png    # Wake-up animation 1
    ├── Frieren[w2].png    # Wake-up animation 2
    └── ...
```

---

## JavaScript Scripts (Optional)

If you need custom animations or interactive behaviors, you can create JavaScript scripts.

### File Locations

```text
ghost/{PersonalityID}/*.js
```

### Specify in manifest.json

```json
{
  "id": "MyCharacter",
  "script": "mycharacter.js"
}
```

Or use the newer multi-script format:

```json
{
  "id": "MyCharacter",
  "scripts": ["mycharacter.js", "mycharacter-extra.js"]
}
```

### Basic Structure

A personality can contain one or more frontend scripts. General interaction scripts can be loaded via `script` or `scripts`; emoji scripts matching the `*-emoji.js` naming convention are independently detected and loaded by the emoji system. See `ghost/Frieren/frieren.js` and `ghost/Frieren/frieren-emoji.js` for full examples.

---

## Upload and Use

### Method 1: ZIP Upload (Recommended)

1. Package all personality files into a ZIP file.
2. Log in to the WordPress admin panel → **Settings** → **MP Ukagaka** → **Create New Ukagaka**.
3. Select the ZIP file and upload it.
4. The system will automatically extract and verify it.
5. After confirming the preview information is correct, click "Confirm and Create".

### Method 2: Manual Upload

1. Use FTP or a file manager to upload the personality folder to `wp-content/plugins/mp-ukagaka/ghost/`.
2. Log in to the WordPress admin panel → **Settings** → **MP Ukagaka** → **Ukagakas**.
3. Manually add the new character settings.

### ZIP File Structure Requirements

After extracting the ZIP file, it should directly contain the `manifest.json` and the `shell/` folder:

```
MyCharacter.zip
└── (After extraction)
    ├── manifest.json
    ├── instructions.md
    ├── personality.md
    ├── shell/
    │   └── MyCharacter/
    │       └── MyCharacter.png
    ├── prompts.json
    └── weights.json
```

**Note**: The ZIP file must **not** contain the top-level folder name (e.g., `MyCharacter/manifest.json`); it should directly contain the files.

---

## Complete Example

Here is a minimal personality example:

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

### 3. instructions.md / personality.md (Optional, Recommended)

```markdown
# Dialogue Protocol

- Keep responses within 50 characters.
- Use casual speech (no honorifics).
- Use "私" for the first person.
```

```markdown
# Character Definition

You are "Simple Character". Please interact with the visitor in a concise and friendly tone.

- Quiet personality.
- Likes to observe surroundings.
```

### 4. prompts.json (LLM Mode, Optional)

```json
{
  "_comment": "SimpleCharacter - Prompt Categories",
  "_format_version": "1.0",
  
  "greeting": [
    "Greet lightly with a flat attitude",
    "Call out to the visitor briefly"
  ],
  
  "casual": [
    "Give an impression of something that caught your eye",
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
3. ⭐ Create `instructions.md` and `personality.md` (Recommended, used to define character behavior)
4. 📝 Create `prompts.json` and `weights.json` (Used in LLM mode)
5. 🎨 Add `decorations.json` and decoration images (Optional)
6. 📦 Package into a ZIP and upload

**Reference Example**: View the `ghost/Frieren/` folder to understand the complete personality structure; when creating a new personality, please prioritize matching the modular prompt structure.

---

**Last Updated**: 2026-01-15
