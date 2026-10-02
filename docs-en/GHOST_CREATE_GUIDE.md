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

1. **`manifest.json`** - Personality metadata and settings (`id` is mandatory)
2. **`shell/`** - At least one character image (`.png`, `.jpg`, `.jpeg`, `.gif` or `.webp`) placed **directly** in this folder

### Minimal Example

```
ghost/
└── MyCharacter/
    ├── manifest.json
    └── shell/
        └── mycharacter.png
```

> ⚠️ Do not put the images in a subfolder such as `shell/MyCharacter/`. A ZIP-installed personality's image path is always `ghost/{PersonalityID}/shell/`, and only that folder's own files are read — images one level deeper are never found.

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
    ├── shell/              # Required: Character images, directly in this folder
    │   ├── mycharacter1.png        # Played in natural filename order
    │   ├── mycharacter2.png
    │   └── ...
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

- `id`: The unique identifier for the personality (alphanumeric, underscores, hyphens; PascalCase is recommended). ZIP installation is rejected without it, and it becomes the folder name under `ghost/`.
- `name`: Character display name (default language). On ZIP installation the new ukagaka's display name is taken from `name_zh` when present, otherwise `name`, otherwise `id`.

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
  "shell_folder": "MyCharacter",          // Informational only; the plugin does not read it
  "decorations_folder": "decorations",    // Optional: Decoration folder name (Default "decorations")
  "script": "mycharacter.js",             // Optional: Old format, single JavaScript script
  "scripts": ["mycharacter.js"],          // Optional: New format, supports multiple scripts
  
  "settings": {                           // Optional: Behavior settings
    "max_response_length": 500,           // Response length limit (characters, default 500)
    "max_tokens": 800,                     // Token limit during API calls (fallback depends on the path, see below)
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

  "emoji": {                              // Optional: Emoji system
    "script": "mycharacter-emoji.js",
    "folder": "emojis",
    "supported": ["notice", "thinking", "laugh"]
  },

  "features": {                           // Optional: Per-context inner-monologue switches
    "inner_monologue_contexts": { "chat": true, "touch": true, "page_aware": true,
                                  "decoration": false, "initial": false, "diary": false }
  },

  "thinking_placeholder": {               // Optional: Text shown while waiting for the AI, per context
    "default": "...", "chat": "...", "touch": "...", "decoration": "...", "initial": "..."
  },

  "sleep_settings": {                     // Optional: Overrides the built-in sleep schedule
    "deep_sleep_start": 0,                //   hour (or [earliest, latest] hours)        default 0
    "deep_sleep_end": 6,                  //   hour                                      default 6
    "oversleep_enabled": true,            //                                             default true
    "oversleep_max_hour": 8,              //                                             default 8
    "oversleep_probability": 0.5,         //                                             default 0.5
    "nap": {                              //   Daytime nap, off by default
      "enabled": false,
      "window_start": 750,                //   minutes of day (750 = 12:30)
      "window_end": 810,                  //   minutes of day (810 = 13:30)
      "probability": 0.4,
      "min_minutes": 30,
      "max_minutes": 60
    }
  }
}
```

`features.inner_monologue_contexts` overrides the per-context defaults described in the Developer Guide's inner monologue section. `sleep_settings` is merged over the defaults shown, so only the keys you change are needed; Frieren sleeps from 22–23 h to 7 h, may oversleep until 9 h, and naps 12:30–13:30 with probability 0.4.

### Example

```json
{
  "id": "Frieren",
  "name": "フリーレン",
  "name_en": "Frieren",
  "name_zh": "芙莉蓮",
  "version": "2.5.1",
  "author": "和製ホーリックス",
  "description_en": "An elven mage who has lived for over a thousand years. Speaks in a calm, matter-of-fact tone and collects magic as a hobby.",
  "language": "ja",
  "shell_folder": "Frieren",
  "decorations_folder": "decorations",
  "scripts": ["frieren.js", "frieren-animation.js", "frieren-interactions.js", "frieren-decorations.js", "frieren-emoji.js"],
  "settings": {
    "max_response_length": 150,
    "max_tokens": 600,
    "speech_style": "常体",
    "tone": "淡々とした口調",
    "emoji_style": "minimal"
  }
}
```

An excerpt of `ghost/Frieren/manifest.json`; the real file also carries `character_traits`, `emoji`, `features` and more.

### settings Field Description

The `settings` object contains the behavioral settings of the character, where the word count limitation mechanism has been unified into three layers of protection:

#### Character Limit Settings

- **`max_response_length`** (Default: 500)

  - Backend truncation limit (character count).
  - When the AI response exceeds this length, the system will automatically truncate it and append `...`.
  - This limit applies to all dialogue types (page-aware, first-time visitor, interactive dialogue, touch zones, decoration clicks, spontaneous dialogues).
- **`max_tokens`**

  - Token limit for API calls (the model's output budget).
  - When set, it applies to every AI dialogue type of this personality.
  - When omitted, interactive chat and page-aware comments fall back to the admin **Max Output Tokens** setting (`ai_max_tokens`, default 1000); auto talk, the first-visit greeting and touch / decoration / gift reactions fall back to 800.
  - The wake-up reaction always uses a fixed 120.

#### Three Layers of Length Control

1. **Prompt guidance**: the built-in prompts ask for replies of about 30–150 characters (soft guidance only).
2. **API `max_tokens`**: caps what the model can generate (see above).
3. **Backend truncation**: `max_response_length` characters (default 500, minimum 20). Longer replies are cut and `...` is appended. Frieren sets 150.

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

## items.json Format Guide (Optional)

`items.json` defines the gift and food items a visitor can hand to the character through the give/feed interaction. Each item is validated and sanitized on load; malformed entries are silently dropped.

### File Structure

```json
{
  "version": "1.0",
  "items_base_folder": "items",
  "items": [
    {
      "id": "merkur_pudding",
      "kind": "food",
      "name": "メルクーアプリン",
      "image": "merkur_pudding.png",
      "favorite": true,
      "prompt": "相手がメルクーアプリンを差し出した。メルクーアプリンはフリーレンの大好物である。見たところ{variant}。",
      "variants": [
        "赤い木の実がいくつも添えられている",
        "果実のソースがたっぷりかかっている",
        "手作りらしい素朴な形をしている"
      ],
      "reactions": ["give_food", "give_favorite"]
    },
    {
      "id": "grimoire",
      "kind": "gift",
      "name": "魔導書",
      "image": "grimoire.png",
      "favorite": true,
      "prompt": "相手が魔導書を差し出した。数頁を繰れば{variant}らしいと見て取れる品である。相手が中身を承知の上で選んだとは限らない。",
      "variants": [
        "大魔法使いフランメの魔法手記の写本",
        "古代文字で書かれ、解読に時間のかかりそうな古い魔導書",
        "既に習得済みの属性魔法ばかり載っていた魔導書"
      ],
      "reactions": ["give_gift", "give_favorite"]
    }
  ]
}
```

### Field Description

| Field | Required | Description |
|---|---|---|
| `id` | Yes | Unique item identifier. Must match `^[a-z_][a-z0-9_]*$`. |
| `kind` | Yes | Item type. Must be `food` or `gift`. |
| `name` | Yes | Display name (also used for the chat-history anchor). |
| `image` | Yes | Image file name in the `items/` folder. Must match `^[a-z0-9_-]+\.(png\|webp)$`. |
| `favorite` | No | `true` marks the item as a favorite. When no `reactions` pool resolves, favorites still get an extra-happy fallback line. |
| `prompt` | Yes | Base LLM prompt describing the item being offered. May contain a `{variant}` placeholder (see below). |
| `reactions` | No | Category names from `prompts.json`. One line is drawn at random from the merged pool each time to vary the staging angle. |
| `variants` | No | List of concrete descriptions (see below). |

### Variants (`{variant}`)

`variants` lets a single item produce varied, content-aware reactions instead of one fixed line. When an item defines a non-empty `variants` list, the server picks one entry at random on each give and substitutes it into the `{variant}` placeholder in `prompt`. This tells the character *what specifically* was given — for example, which grimoire — so the reply differs from turn to turn.

- Each variant is sanitized with `sanitize_text_field`; non-string, blank, and duplicate entries are dropped.
- If `prompt` contains `{variant}` but no variants resolve (e.g. the list is empty), the unresolved placeholder is stripped before the prompt reaches the LLM — it is never passed through as literal template text.
- The chat-history anchor always uses the item `name`, not the chosen variant, so the picked variant only surfaces inside the character's own reply.
- Write variants as concrete nouns/phrases that fit the sentence around `{variant}`; keep them consistent in tone with the item's `prompt`.
- A visible variant must agree with the picker artwork. Do not describe packaging, quantity, garnish, temperature, or another visual property that the image contradicts or cannot establish.
- Do not use a variant to invent who made, bought, found, or otherwise obtained the item. The visitor's message and conversation own that information. An item may look handmade (`手作りらしい`), but it must not be declared to have been made by the visitor unless the visitor said so.
- Describe item facts and clues rather than ordering the model to mention every detail. The reaction builder lets the model omit details that do not fit the conversation and improvise unresolved item details without inventing the visitor's motive, source, or knowledge.

---

## Shell Image Files

Shell images are the visual representation of the character. For a ZIP-installed personality they live **directly** in `ghost/{PersonalityID}/shell/`.

### How They Are Used

- The plugin reads every `.png`, `.jpg`, `.jpeg`, `.gif` and `.webp` file in the folder (subfolders are ignored) and sorts the names naturally (`char2.png` before `char10.png`).
- One image: it is shown as a still.
- Several images: they are played in that order as an animation by the generic canvas manager.
- File names carry no other meaning to the core plugin. There is no required "main image" name.

Names such as `frieren[0].png`, `frieren[s].png` (sleeping) and `frieren[w1].png` (waking up) are a convention of Frieren's own script (`ghost/Frieren/frieren-animation.js`), which picks frames by name for its idle, page-turn, sleep and wake animations. A new personality only needs that kind of naming if it ships a script that looks for it.

### Image Formats

- **Format**: PNG (Recommended), JPG, GIF or WebP
- **Size**: Recommended 200-400px width, height is custom
- **Background**: Transparent background is recommended (PNG)

### Example File Structure

```
shell/
├── mycharacter1.png
├── mycharacter2.png
└── mycharacter3.png
```

> The bundled Frieren is configured by the plugin's defaults to use `ghost/Frieren/shell/Frieren/`. That subfolder works only because her ukagaka entry points at it directly; a ZIP install always points at `shell/` itself.

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
    │   └── mycharacter.png
    ├── prompts.json
    └── weights.json
```

**Note**: The ZIP file must **not** contain the top-level folder name (e.g., `MyCharacter/manifest.json`); it should directly contain the files. The personality is installed to `ghost/{id}/`; uploading a ZIP whose `id` already exists asks for confirmation before overwriting, and the built-in IDs `Frieren` and `default_1` cannot be overwritten.

---

## Complete Example

Here is a minimal personality example:

### 1. Folder Structure

```
ghost/
└── SimpleCharacter/
    ├── manifest.json
    └── shell/
        └── simplecharacter.png
```

### 2. manifest.json

```json
{
  "id": "SimpleCharacter",
  "name": "Simple Character"
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
2. ✅ Put at least one image directly in `shell/` (Required)
3. ⭐ Create `instructions.md` and `personality.md` (Recommended, used to define character behavior)
4. 📝 Create `prompts.json` and `weights.json` (Used in LLM mode)
5. 🎨 Add `decorations.json` and decoration images (Optional)
6. 📦 Package into a ZIP and upload

**Reference Example**: View the `ghost/Frieren/` folder to understand the complete personality structure; when creating a new personality, please prioritize matching the modular prompt structure.

---

**Last Updated**: 2026-10-02
