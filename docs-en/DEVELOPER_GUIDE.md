# MP Ukagaka Developer Guide

> 🛠️ Architecture Overview, Extension Development, and API Reference

---

## 📑 Table of Contents

1. [Architecture Overview](#architecture-overview)
2. [Module Descriptions](#module-descriptions)
3. [Data Structures](#data-structures)
4. [Hooks and Filters](#hooks-and-filters)
5. [REST Endpoints](#rest-endpoints)
6. [JavaScript API](#javascript-api)
7. [Extension Development](#extension-development)
8. [Security Considerations](#security-considerations)
9. [Development Standards](#development-standards)

---

## Architecture Overview

### Directory Structure

```text
mp-ukagaka/
├── mp-ukagaka.php          # Main plugin entry point
├── css/                    # Stylesheets
│   ├── mpu_style.css           # Frontend stylesheet
│   └── admin-style.css         # Admin stylesheet
├── includes/               # PHP Modules
│   ├── core/                   # Core modules
│   │   ├── debug-functions.php     # Logging system (must be loaded first)
│   │   ├── core-functions.php      # Core functions (settings management)
│   │   ├── utility-functions.php   # Utility functions
│   │   ├── ukagaka-functions.php   # Ukagaka management
│   │   └── frontend-functions.php  # Frontend functions
│   ├── rest/                   # REST API handling modules (OO Architecture)
│   │   ├── bootstrap.php           # REST Controller registration entry
│   │   ├── class-mpu-rest-base.php # Base class
│   │   ├── class-mpu-rest-chat.php # LLM chat endpoints
│   │   ├── class-mpu-rest-ghost.php# Core/Personality endpoints
│   │   ├── class-mpu-rest-dialog.php# Dialog management endpoints
│   │   ├── class-mpu-rest-touch.php# Touch interaction endpoints
│   │   └── class-mpu-rest-test.php # API test endpoints
│   ├── ajax/                   # AJAX handler modules
│   │   └── chat-api-handlers.php   # Chat API handlers (Multi-turn encapsulation)
│   ├── personality/            # Personality system modules
│   │   ├── personality-loader.php  # Personality system (JSON loader)
│   │   ├── personality-prompts.php # Personality prompts module
│   │   ├── personality-decorations.php # Decoration system
│   │   ├── personality-emoji.php   # Emoji system
│   │   └── emoji-mapper.php        # Emoji mapping and emotion analysis
│   ├── llm/                    # LLM/AI functions modules
│   │   ├── api-cache.php           # API cache system
│   │   ├── ai-functions.php        # AI functions (Cloud APIs: Gemini, OpenAI, Claude)
│   │   ├── llm-functions.php       # LLM functions (Ollama specifically)
│   │   ├── llm-context-builder.php # LLM context builder
│   │   ├── llm-slimstat.php        # LLM Slimstat integration
│   │   ├── prompt-categories.php   # Prompt category instruction management
│   │   ├── chat-integrity.php      # Chat history checksum validation
│   │   ├── request-state.php       # Request-level state management
│   │   ├── provider-helpers.php    # AI provider helper functions
│   │   ├── streaming-helpers.php   # SSE streaming helper functions
│   │   ├── provider-stream-http.php# cURL streaming HTTP client
│   │   ├── tool-loop-guard.php     # Tool call loop protection mechanism
│   │   ├── weather-functions.php   # Weather functions (Open-Meteo API)
│   │   ├── diary-functions.php     # AI diary functions
│   │   └── providers/              # AI provider factory modules
│   │       ├── bootstrap.php       # Loader
│   │       ├── interface-mpu-ai-provider.php # Interface
│   │       ├── class-mpu-ai-provider-base.php # Base class
│   │       ├── class-mpu-ai-provider-factory.php # Factory class
│   │       ├── class-mpu-ai-provider-gemini.php # Gemini provider
│   │       ├── class-mpu-ai-provider-openai.php # OpenAI provider
│   │       ├── class-mpu-ai-provider-claude.php # Claude provider
│   │       └── class-mpu-ai-provider-ollama.php # Ollama provider
│   ├── stats/                  # Statistics modules
│   │   ├── stats-collector.php     # Usage stats collection
│   │   └── stats-analyzer.php      # Stats analysis
│   ├── mcp-tools/              # Abilities/Tool call implementations
│   │   ├── manager.php             # Abilities manager
│   │   └── abilities/
│   │       ├── class-wp-bot-blocker-ability.php # Bot blocker ability
│   │       └── class-wp-postviews-ability.php   # Post views ability
│   ├── integrations/           # Integration modules
│   │   ├── abilities-integration.php   # Abilities API integration
│   │   ├── akismet-integration.php     # Akismet anti-spam integration
│   │   ├── bot-blocker-integration.php # Bot blocker integration
│   │   └── turnstile-integration.php   # Turnstile verification integration
│   └── admin-functions.php     # Admin functions
├── ghost/                  # Character personality configurations
│   ├── Frieren/
│   │   ├── shell/              # Character images
│   │   ├── decorations/        # Decoration images
│   │   ├── emojis/             # Character emoji images
│   │   ├── manifest.json       # Metadata and settings
│   │   ├── personality.md      # Core personality description
│   │   ├── instructions.md     # Behavior rules and instructions
│   │   ├── prompts.json        # Static dialog categories
│   │   ├── dynamics.json       # Dynamic templates (with variables)
│   │   ├── weights.json        # Category weights configuration
│   │   ├── sleep_mode.json     # Sleep mode configuration
│   │   ├── calendar.json       # Calendar/Holiday events
│   │   ├── touchzones.json     # Touch zones configuration
│   │   ├── decorations.json    # Decoration click prompts
│   │   ├── diary.json          # AI diary configuration
│   │   ├── emoji-keywords.json # Emoji keywords configuration
│   │   ├── frieren.js          # Character-specific JavaScript
│   │   └── frieren-emoji.js    # Frieren-specific emoji system
│   └── [Other Characters...]/
│       ├── shell/              # Character images
│       └── decorations/        # Decoration images (optional)
├── dialogs/                # Dialog files
├── images/                 # Common image resources
├── languages/              # Translation files
├── docs/                   # Documentation
├── options/                # Admin settings pages
│   ├── options.php             # Admin page framework
│   ├── options_general.php     # General settings page
│   ├── options_ukagakas.php    # Ukagaka management page
│   ├── options_create.php      # Create new ukagaka page
│   ├── options_extend.php      # Extension settings page
│   ├── options_dialog.php      # Dialog settings page
│   ├── options_page_ai.php     # AI features settings page
│   ├── options_page_llm.php    # LLM features settings page
│   ├── options_page_diary.php  # Diary features settings page
│   ├── options_page_bot_blocker.php # Bot blocker settings page
│   └── options_page_stats.php  # Stats settings page
├── js/                     # Frontend JavaScript modules
│   ├── dist/                   # Bundled output directory (Production)
│   │   ├── ukagaka-bundle.js       # Unminified bundle
│   │   ├── ukagaka-bundle.min.js   # Minified core bundle
│   │   └── ukagaka-textarearesizer.min.js  # Admin tool (minified)
│   ├── ukagaka-base.js         # Base layer (Config + Utils + AJAX)
│   ├── ukagaka-core.js         # Frontend core JS (Message display, Ukagaka switching, etc.)
│   ├── ukagaka-features.js     # Frontend features JS (Settings configuration, Event listeners)
│   ├── ukagaka-context.js      # Page-aware AI chat feature
│   ├── ukagaka-greeting.js     # First-time visitor greeting feature
│   ├── ukagaka-chat.js         # Chat frontend (Interactive dialogs)
│   ├── ukagaka-dialog.js       # External dialog loading and fallback
│   ├── ukagaka-anime.js        # Canvas animation manager (Image sequence playback)
│   ├── ukagaka-emoji.js        # Emoji configuration loader
│   └── ukagaka-textarearesizer.js  # Admin textarea resizer
└── readme.txt              # WordPress plugin directory readme
```

### Module Load Order

The plugin uses a conditional loading mechanism, loading modules based on the execution environment (frontend/admin):

```php
// Loading logic in mp-ukagaka.php

// Core modules: needed in both frontend and admin
$core_modules = [
    'core/debug-functions.php',     // 0. Logging system (must be loaded first)
    'core/core-functions.php',      // 1. Core functions (settings management)
    'core/utility-functions.php',   // 2. Utility functions
    'personality/personality-loader.php',  // 3. Personality system (JSON loader, must be before other personality modules)
    'personality/personality-prompts.php', // 4. Personality prompts module (Dynamic prompts, variable replacement)
    'personality/personality-decorations.php', // 5. Decoration system
    'personality/personality-emoji.php',   // 6. Emoji system
    'stats/stats-collector.php',   // 7. Stats collector (must be before ai-functions.php)
    'stats/stats-analyzer.php',    // 8. Stats analyzer
    'llm/api-cache.php',           // 9. API cache system
    'llm/provider-helpers.php',    // 10. Provider common helpers
    'llm/chat-integrity.php',      // 11. Chat history integrity checksum
    'llm/request-state.php',       // 12. Request-level state management
    'llm/tool-loop-guard.php',     // 13. Tool call loop protection mechanism
    'llm/streaming-helpers.php',   // 14. SSE streaming helper functions
    'llm/provider-stream-http.php', // 15. Provider streaming HTTP client
    'llm/providers/bootstrap.php', // 16. AI Providers factory pattern and classes
    'llm/ai-functions.php',        // 17. AI functions (Cloud APIs: Gemini, OpenAI, Claude)
    'llm/prompt-categories.php',   // 18. Prompt category instruction management
    'llm/llm-slimstat.php',        // 19. LLM Slimstat integration
    'llm/llm-context-builder.php', // 20. LLM context builder
    'llm/weather-functions.php',   // 21. Weather functions (Open-Meteo API)
    'llm/diary-functions.php',     // 22. AI diary functions
    'llm/llm-functions.php',       // 23. LLM functions (Local / Remote LLM)
    'personality/emoji-mapper.php', // 24. Emoji mapping and emotion analysis
    'core/ukagaka-functions.php',   // 25. Ukagaka management
    'rest/bootstrap.php',           // 26. REST OO Controller registration entry
    'ajax/chat-api-handlers.php',   // 27. Chat mode encapsulation / compatibility layer
    'integrations/akismet-integration.php', // 28. Akismet anti-spam integration
    'integrations/turnstile-integration.php', // 29. Turnstile verification integration
    'integrations/abilities-integration.php', // 30. Abilities API integration
    'integrations/bot-blocker-integration.php', // 31. Bot Blocker integration
];

// Frontend-only modules (loaded only in non-admin environments)
$frontend_modules = [
    'core/frontend-functions.php',  // Frontend functions
];

// Admin-only modules (loaded only in admin environments)
$admin_modules = [
    'admin-functions.php',     // Admin functions
];
```

**Loading Timing:**

- All core modules are loaded on the `plugins_loaded` action (priority 1)
- Frontend modules are loaded only when `!is_admin()`
- Admin modules are loaded only when `is_admin()`

### Constants Definition

| Constant        | Description     | Value                |
| --------------- | --------------- | -------------------- |
| `MPU_VERSION`   | Plugin version  | `"2.13.7-20260425"`  |
| `MPU_MAIN_FILE` | Main file path  | `__FILE__`           |

---

## Module Descriptions

### core-functions.php

Core functions module, responsible for settings management.

#### Main Functions

```php
/**
 * Gets default option values
 * @return array Default options array
 */
function mpu_default_opt()

/**
 * Gets plugin options (with cache)
 * @return array Options array
 */
function mpu_get_option()
```

**Note:** `mpu_count_total_msg()` is located in the `ukagaka-functions.php` module.

### utility-functions.php

Utility functions module, provides various helper features (string processing, filtering, file operations, encryption, etc.).

#### String/Array Conversion

```php
/**
 * Array to string (separated by double newlines)
 * @param array $arr Input array
 * @return string Output string
 */
function mpu_array2str($arr = [])

/**
 * String to array (separated by newlines, filters empty lines)
 * @param string $str Input string
 * @return array Output array
 */
function mpu_str2array($str = "")
```

#### Output Filtering

```php
/**
 * HTML output filter (uses esc_html)
 * @param string $str Input string
 * @return string Filtered string
 */
function mpu_output_filter($str)

/**
 * JavaScript output filter (uses esc_js)
 * @param string $str Input string
 * @return string Filtered string
 */
function mpu_js_filter($str)
```

#### Secure File Operations

```php
/**
 * Safely reads a file (uses WordPress Filesystem API)
 * @param string $file_path File path
 * @return string|WP_Error File content or error
 */
function mpu_secure_file_read($file_path)

/**
 * Safely writes to a file (uses WordPress Filesystem API)
 * @param string $file_path File path
 * @param string $content File content
 * @return bool|WP_Error Success or error
 */
function mpu_secure_file_write($file_path, $content)

/**
 * Gets dialogs directory path
 * @return string Directory path
 */
function mpu_get_dialogs_dir()

/**
 * Ensures dialogs directory exists
 * @return bool Success status
 */
function mpu_ensure_dialogs_dir()
```

#### API Key Encryption

```php
/**
 * Gets encryption key (based on WordPress AUTH_KEY)
 * @return string Encryption key
 */
function mpu_get_encryption_key()

/**
 * Encrypts API Key (AES-256-CBC)
 * @param string $api_key Raw API Key
 * @return string Encrypted string
 */
function mpu_encrypt_api_key($api_key)

/**
 * Decrypts API Key
 * @param string $encrypted_key Encrypted string
 * @return string|false Decrypted API Key or false
 */
function mpu_decrypt_api_key($encrypted_key)

/**
 * Checks if API Key is encrypted
 * @param string $api_key API Key string
 * @return bool Is encrypted
 */
function mpu_is_api_key_encrypted($api_key)
```

### personality-loader.php (v2.4.0)

Personality system loader module, providing a JSON-based character configuration system. Allows different characters to define their personality via JSON files without modifying PHP code.

#### personality-loader.php Main Functions

```php
/**
 * Gets ghost directory path (personalities directory)
 * @return string Absolute path
 */
function mpu_get_personalities_dir()

/**
 * Gets current personality ID
 * @return string Personality ID (folder name)
 */
function mpu_get_current_personality_id()

/**
 * Checks if a personality exists
 * @param string $personality_id Personality folder name
 * @return bool Exists
 */
function mpu_personality_exists($personality_id)

/**
 * Gets all available personalities
 * @param bool $include_placeholders Whether to include placeholder characters
 * @return array Associative array of Personality ID => manifest
 */
function mpu_get_available_personalities($include_placeholders = false)

/**
 * Loads personality manifest
 * @param string|null $personality_id Personality ID, null for current
 * @return array Manifest data
 */
function mpu_load_personality_manifest($personality_id = null)

/**
 * Loads personality prompts
 * @param string|null $personality_id Personality ID, null for current
 * @return array Prompts category array
 */
function mpu_load_personality_prompts($personality_id = null)

/**
 * Loads personality weights
 * @param string|null $personality_id Personality ID, null for current
 * @return array Weights configuration array
 */
function mpu_load_personality_weights($personality_id = null)

/**
 * Loads personality decorations configuration
 * @param string|null $personality_id Personality ID, null for current
 * @return array Decorations configuration array
 */
function mpu_load_personality_decorations($personality_id = null)

/**
 * Loads personality dynamic prompts
 * @param string|null $personality_id Personality ID, null for current
 * @return array Dynamic prompts configuration array
 */
function mpu_load_personality_dynamic_prompts($personality_id = null)

/**
 * Loads personality emoji keywords
 * @param string|null $personality_id Personality ID, null for current
 * @return array Emoji keywords configuration array
 */
function mpu_load_personality_emoji_keywords($personality_id = null)
```

#### Personality File Structure

Each personality folder should contain:

- **manifest.json** (Required): Metadata and settings
  - `id`: Personality ID
  - `name`, `name_en`, `name_zh`: Multi-language names
  - `version`: Version number
  - `settings`: Character settings (e.g., `max_response_length`, `speech_style`, `tone`)
  - `character_traits`: Character traits (e.g., `age`, `race`, `occupation`, `personality`)

- **prompts.json** (Optional): Static dialog categories
  - Keys are category names, values are arrays of prompts

- **dynamics.json** (Optional): Dynamic templates (with variable replacement)
  - Supports `{variable_name}` variable replacement
  - Includes categories like `time_aware_dynamic`, `tech_observation`, `bot_detection`

- **weights.json** (Optional): Category weights configuration
  - `base_weights`: Base weights
  - `time_adjustments`: Time-of-day adjustments

- **decorations.json** (Optional): Decoration click prompts
  - `items`: Array of decoration configurations, each containing:
    - `id`: Decoration ID
    - `image`: Image path (relative to `decorations/` folder)
    - `position`: Position settings (e.g., `{"bottom": "0px", "right": "0px"}`)
    - `size`: Size settings (e.g., `{"width": "100px", "height": "auto"}`)
    - `z_index`: Z-index (number)
    - `prompt`: Prompt used when clicked
    - `transform`: CSS transform (optional, e.g., `scale(1)`)

- **emoji-keywords.json** (Optional, v2.4.0): Emoji trigger keywords
  - `mappings`: Mapping of emoji types to keywords
  - Example format:
    ```json
    {
      "mappings": {
        "happy": {
          "keywords": ["開心", "happy"],
          "file": "happy.png",
          "weight": 10
        }
      }
    }
    ```

- **script** (Optional): Character-specific JavaScript file
  - e.g., `frieren.js`, automatically loaded by the frontend

#### Usage Example

```php
// Get prompts for the current personality
$prompts = mpu_load_personality_prompts();

// Get manifest for a specific personality
$manifest = mpu_load_personality_manifest('Frieren');

// Check if a personality exists
if (mpu_personality_exists('Frieren')) {
    // Frieren personality exists
}

// Get all available personalities
$personalities = mpu_get_available_personalities();
foreach ($personalities as $id => $manifest) {
    echo $manifest['name'];
}
```

### ai-functions.php

AI functions module, handles cloud AI API calls (Gemini, OpenAI, Claude) and Ollama integration.

#### ai-functions.php Main Functions

```php
/**
 * Calls AI API (Unified entry point)
 * @param string $provider Provider (gemini/openai/claude/ollama)
 * @param string $api_key API Key (Not needed for Ollama)
 * @param string $system_prompt System prompt (Character settings)
 * @param string $user_prompt User prompt
 * @param string $language Language code
 * @param array|null $mpu_opt Settings array (Optional)
 * @return string|WP_Error AI response or error
 */
function mpu_call_ai_api($provider, $api_key, $system_prompt, $user_prompt, $language, $mpu_opt = null)

/**
 * Gets language instruction
 * @param string $language Language code
 * @return string Language instruction
 */
function mpu_get_language_instruction($language)
```

#### Supported AI Providers

All providers are routed through `MPU_AI_Provider_Factory::create($provider_slug)->generate_text($args)`. Use `mpu_call_ai_api()` as the unified entry point.

| Provider | Slug | API Endpoint | Model Selection |
| -------- | ---- | ------------ | --------------- |
| Gemini | `gemini` | `generativelanguage.googleapis.com` | Supported (gemini-2.5-flash, gemini-2.5-pro, etc.) |
| OpenAI | `openai` | `api.openai.com` | Supported (gpt-4o-mini, gpt-4o, etc.) |
| Claude | `claude` | `api.anthropic.com` | Supported (claude-sonnet-4-6, etc.) |
| Ollama | `ollama` | Local or Remote Ollama Service | Supported (Any Ollama model) |

#### AI Stability & Security

To prevent the LLM from entering an infinite tool call loop, the system implements the following protection mechanisms:

1. **Turn Limit**: Defined by `MPU_MAX_TOOL_TURNS`, allowing a maximum of 5 tool call turns per request.
2. **Loop Guard**:
   - Target: Detects scenarios where the same tool is repeatedly called with identical parameters.
   - Threshold: Defined by `MPU_MAX_TOOL_REPEAT_SAME_CALL` (default is 2).
   - Behavior: Once a loop is detected, the system immediately returns a `tool_call_loop_detected` error and breaks the loop.
   - Implementation: `includes/llm/tool-loop-guard.php`.

### llm-functions.php (BETA)

> ⚠️ **Note**: This module is in **BETA**. APIs may change.

LLM functions module, specifically handles Ollama local LLM integration.

#### llm-functions.php Main Functions

```php
/**
 * Checks if an endpoint is a remote connection
 * @param string $endpoint Ollama endpoint URL
 * @return bool Is remote connection (true = remote, false = local)
 */
function mpu_is_remote_endpoint($endpoint)

/**
 * Gets appropriate timeout based on endpoint type and operation type
 * @param string $endpoint Ollama endpoint URL
 * @param string $operation_type Operation type: 'check', 'api_call', 'test'
 * @return int Timeout (seconds)
 */
function mpu_get_ollama_timeout($endpoint, $operation_type = 'api_call')

/**
 * Validates and normalizes Ollama endpoint URL
 * @param string $endpoint Raw endpoint URL
 * @return string|WP_Error Normalized URL or error
 */
function mpu_validate_ollama_endpoint($endpoint)

/**
 * Checks if Ollama service is available (quick check, cached)
 * @param string $endpoint Ollama endpoint
 * @param string $model Model name
 * @return bool Is service available
 */
function mpu_check_ollama_available($endpoint, $model)

/**
 * Generates random dialog using LLM (replaces built-in dialog)
 * @param string $ukagaka_name Character name
 * @return string|false Generated dialog content, false on failure
 */
function mpu_generate_llm_dialogue($ukagaka_name = 'default_1')

/**
 * Checks if LLM replace built-in dialog is enabled
 * @return bool
 */
function mpu_is_llm_replace_dialogue_enabled()
```

#### Timeout Settings

| Operation Type | Local Connection | Remote Connection |
| -------------- | ---------------- | ----------------- |
| Service Check (`check`) | 3s | 10s |
| API Call (`api_call`) | 60s | 90s |
| Connection Test (`test`) | 30s | 45s |

#### Usage Example

```php
// Check if service is available
$endpoint = 'https://your-domain.com';
$model = 'qwen3:8b';
if (mpu_check_ollama_available($endpoint, $model)) {
    // Service is available, can generate dialog
    $dialogue = mpu_generate_llm_dialogue('default_1');
    if ($dialogue !== false) {
        echo $dialogue;
    }
}

// Detect connection type
$is_remote = mpu_is_remote_endpoint($endpoint);
$timeout = mpu_get_ollama_timeout($endpoint, 'api_call');
```

### chat-api-handlers.php (Compatibility Layer)

Chat mode API handler, provides an AI call wrapper for the new REST Controllers for multi-turn conversations, isolating complex Provider options handling.

#### Main Functions

```php
/**
 * Calls AI API (Unified entry for multi-turn conversations, auto-dispatches to factory class)
 * @param string $provider Provider
 * @param string $api_key API Key
 * @param string $system_prompt System prompt
 * @param array $messages Conversation history
 * @param string $language Language
 * @param array $options Options (max_tokens, temperature, etc.)
 * @return string|WP_Error AI response
 */
function mpu_call_ai_api_with_messages($provider, $api_key, $system_prompt, $messages, $language, $options = [])
```

### REST API Modules (OO Architecture)

Introduced in v2.10.0, driven uniformly by `bootstrap.php`. All Controllers inherit from `MPU_REST_Base`.

#### Main Class Functions

- **MPU_REST_Chat**: Centralizes all AI chat-related endpoints.
  - `/chat/context` (Page-aware)
  - `/chat/greet` (First-time greeting)
  - `/chat/user` (Synchronous chat)
  - `/chat/user-stream` (SSE streaming chat)
- **MPU_REST_Ghost**: Handles personality lists and initial setup.
- **MPU_REST_Dialog**: Manages static and local dialog loading.
- **MPU_REST_Touch**: Handles touch zone interactions.
- **MPU_REST_Test**: Provides admin connection testing functionalities.

### diary-functions.php (v2.5.0)

AI diary function module, responsible for automatically generating and publishing character diaries.

#### diary-functions.php Main Functions

```php
/**
 * Gets the diary title prefix
 * @param string|null $personality_id Personality ID
 * @return string Prefix (e.g., "[Frieren's Journal] ")
 */
function mpu_get_diary_title_prefix($personality_id = null)

/**
 * Determines whether the diary should be triggered (based on probability and once-daily limit)
 * @return bool Should trigger
 */
function mpu_should_trigger_diary()

/**
 * Generates diary content
 * @return array|WP_Error Diary data or error
 */
function mpu_generate_diary_content()

/**
 * Publishes diary post
 * @param array $diary_data Diary data
 * @return int|WP_Error Post ID or error
 */
function mpu_publish_diary_post($diary_data)
```

### emoji-mapper.php (v2.4.0)

Emoji mapping and emotion analysis module, automatically selects the corresponding emoji based on the emotion of the dialog content.

#### emoji-mapper.php Main Functions

```php
/**
 * Analyzes the emotion of the dialog content and returns the corresponding emoji file name.
 * Prioritizes loading from the character's specific `emoji-keywords.json`.
 * Falls back to built-in general defaults if not found.
 *
 * @param string $text Dialog content
 * @param string|null $personality_id Personality ID (optional)
 * @return string|null Emoji file name (e.g., 'happy.png'), returns null if no match
 */
function mpu_analyze_emoji_from_text($text, $personality_id = null)
```

#### Supported Emoji Types

The system supports multiple emoji types, including:

- `happy`: Happy, glad
- `waku_waku`: Excited, anticipating
- `laugh`: Laughing
- `angry`: Angry
- `get_angry`: Furious
- `surprised` / `startled`: Surprised
- `stunned`: Shocked
- `discovery`: Discovered
- `scared_to_death`: Scared to death
- `heart`: Heart
- `kiss`: Kiss
- `sleepy`: Sleepy
- `awkward`: Awkward
- `proud`: Proud
- `suspect`: Suspicious
- etc...

#### Keyword Matching Mechanism

- Supports Traditional Chinese, Japanese, and English keywords
- Uses a weighted mechanism, prioritizing matches with higher weights
- Keyword matching is case-insensitive

#### Usage Example

```php
// Analyze dialog content and get emoji
$text = "Today is such a happy day!";
$emoji = mpu_analyze_emoji_from_text($text);
// Might return: 'happy.png'

// Use in AJAX response
wp_send_json([
    'msg' => $text,
    'emoji' => $emoji
]);
```

### ukagaka-functions.php

Ukagaka management module, handles character-related operations and dialog management.

#### ukagaka-functions.php Main Functions

```php
/**
 * Gets ukagaka data
 * @param string|false $num Ukagaka key (false for current ukagaka)
 * @return array|false Ukagaka data or false
 */
function mpu_get_ukagaka($num = false)

/**
 * Gets ukagaka image URL
 * @param string|false $num Ukagaka key (false for current ukagaka)
 * @param bool $echo Whether to output directly
 * @return string Image URL
 */
function mpu_get_shell($num = false, $echo = false)

/**
 * Gets a specific message
 * @param int $msgnum Message index
 * @param string|false $num Ukagaka key
 * @param bool $echo Whether to output directly
 * @return string Message content
 */
function mpu_get_msg($msgnum = 0, $num = false, $echo = false)

/**
 * Gets a random message
 * @param string|false $num Ukagaka key
 * @param bool $echo Whether to output directly
 * @return string Message content
 */
function mpu_get_random_msg($num = false, $echo = false)

/**
 * Gets the common message
 * @return string Common message content
 */
function mpu_common_msg()

/**
 * Gets the message array
 * @param string|false $num Ukagaka key
 * @return array Message array
 */
function mpu_get_msg_arr($num = false)

/**
 * Processes special codes in messages
 * @param array $msglist Message array
 * @return array Processed message array
 */
function mpu_msg_code($msglist = [])

/**
 * Calculates the total number of messages across all ukagakas
 * @return int Total message count
 */
function mpu_count_total_msg()

/**
 * Loads dialogs from an external file
 * @param string $filename_base File name (without extension)
 * @return array Dialog array
 */
function mpu_get_msg_from_file($filename_base)
```

### REST API Modules (OO Architecture)

Currently, the main endpoints are registered and handled by controllers under `includes/rest/`, with `rest/bootstrap.php` as the entry point.

#### bootstrap.php

Responsible for loading each REST controller and registering routes on the `rest_api_init` action.

#### class-mpu-rest-base.php

Shared base class for all controllers, centralizing the namespace, common helpers, and permission/rate-limiting logic.

#### class-mpu-rest-chat.php

Handles AI chat endpoints:

```php
/chat/context
/chat/greet
/chat/user
/chat/user-stream
```

#### class-mpu-rest-ghost.php

Handles character initialization and settings endpoints:

```php
/init
/settings
/change
/extend
/shell-info
/decoration-config
/emoji-config
```

#### class-mpu-rest-dialog.php

Handles dialog files and rotation dialog endpoints:

```php
/nextmsg
/dialog
/visitor-info
/decoration-prompts
/wake-ghost
```

#### class-mpu-rest-touch.php

Handles touch zones and decoration interaction endpoints:

```php
/touch/decoration
/touch/zone
```

#### class-mpu-rest-test.php

Handles admin testing and management endpoints:

```php
/test-connection/{provider}
/clear-cache
```

### chat-api-handlers.php

`includes/ajax/chat-api-handlers.php` currently serves as a multi-turn conversation wrapper and compatibility layer, helping to organize message-based provider calls, rather than being the primary old frontend AJAX entry point.

#### chat-api-handlers.php Main Functions

```php
/**
 * Calls AI API (Unified entry for multi-turn conversations)
 * @param string $system_prompt System prompt
 * @param array  $messages      Conversation history
 * @param array  $options       Provider / model / api_key options, etc.
 * @return string|WP_Error AI response or error
 */
function mpu_call_ai_api_with_messages($provider, $api_key, $system_prompt, $messages, $language, $options = [])
```

#### Conversation Message Format

```php
// Conversation history array format
$messages = [
    [
        'role' => 'user',      // 'user' or 'assistant'
        'content' => 'Hello'   // Message content
    ],
    [
        'role' => 'assistant',
        'content' => 'Hello! How can I help you?'
    ],
    // ... more messages
];
```

#### Dynamic Context Injection

The system decides whether to inject WordPress statistics based on the user's message content:

```php
// Keyword list (Traditional Chinese/Japanese/English)
$stats_keywords = [
    '文章', '記事', 'article', 'post',
    '留言', 'コメント', 'comment',
    '網站', 'サイト', 'site', 'website',
    'php', 'wordpress', '外掛', 'plugins', 'プラグイン',
    '主題', 'テーマ', 'theme'
];

// Only add statistics information when the user message contains these keywords
```

**Benefits**:

- Saves 70%+ of token consumption
- Reduces API costs
- Speeds up response times

#### Thinking Mode Support (Ollama)

Ollama provider automatically detects thinking models (Qwen3, DeepSeek, etc.) and sets `think: true` / `num_ctx: 8192` accordingly. Disable via the `ollama_disable_thinking` option.

#### Response Length Limit

All AI providers are uniformly restricted to **300 tokens**:

```php
// Ollama
$request_body['options']['num_predict'] = 300;

// OpenAI
'max_tokens' => 300,

// Gemini
'generationConfig' => ['maxOutputTokens' => 300],

// Claude
'max_tokens' => 300,
```

### frontend-functions.php

Frontend functions module, responsible for page display and resource loading.

#### frontend-functions.php Main Functions

```php
/**
 * Checks if it should be displayed on the current page
 * @return bool Should display
 */
function mpu_is_show_page()

/**
 * Output buffer callback (used to insert ukagaka HTML)
 * @param string $buffer Page content
 * @return string Processed content
 */
function mpu_ob_callback($buffer)

/**
 * Shutdown callback (ensures HTML is inserted)
 */
function mpu_shutdown_callback()

/**
 * Generates ukagaka HTML
 * @param string|false $num Ukagaka key
 * @return string HTML string
 */
function mpu_html($num = false)

/**
 * Outputs ukagaka HTML
 */
function mpu_echo_html()

/**
 * Enqueues frontend assets (CSS/JS)
 */
function mpu_enqueue_frontend_assets()

/**
 * Outputs settings in head (JavaScript variables)
 */
function mpu_head()
```

### admin-functions.php

Admin functions module, handles settings saving and admin interfaces.

#### admin-functions.php Main Functions

```php
/**
 * Enqueues admin assets (CSS/JS)
 * @param string $hook_suffix Current page hook
 */
function mpu_admin_enqueue_scripts($hook_suffix)

/**
 * Handles settings saving
 */
function mpu_handle_options_save()

/**
 * Generates dialog file (TXT or JSON format)
 * @param string $filename File name (without extension)
 * @param array $msg_array Message array
 * @param string $ext Extension (txt or json)
 * @return bool Is success
 */
function mpu_generate_dialog_file($filename, $msg_array, $ext)

/**
 * Admin menu page HTML
 */
function mpu_options_page_html()

/**
 * Registers admin menu
 */
function mpu_options()
```

---

## Data Structures

### Settings Structure ($mpu_opt)

```php
$mpu_opt = [
    // General Settings
    'cur_ukagaka' => 'default_1',      // Current ukagaka
    'show_ukagaka' => true,             // Show ukagaka
    'show_msg' => true,                 // Show dialog box
    'default_msg' => 0,                 // 0=Random, 1=First message
    'next_msg' => 0,                    // 0=Sequential, 1=Random
    'click_ukagaka' => 0,               // 0=Next message, 1=No action
    'insert_html' => 0,                 // HTML insert position
    'no_style' => false,                // Use custom styles
    'no_page' => '',                    // Exclude pages list

    // Auto Talk
    'auto_talk' => true,                // Enable auto talk
    'auto_talk_interval' => 8,          // Auto talk interval (seconds)
    'typewriter_speed' => 40,           // Typewriter speed (ms/character)

    // External Dialog Files
    'use_external_file' => true,        // Use external files (System forced to true)
    'external_file_format' => 'txt',     // File format (txt/json)

    // Conversation Settings
    'auto_msg' => '',                   // Fixed message
    'common_msg' => '',                 // Common dialog

    // AI Settings (Page-aware feature)
    'ai_enabled' => false,              // Enable AI
    'ai_provider' => 'gemini',          // AI Provider (gemini/openai/claude/ollama)
    'ai_api_key' => '',                 // Gemini API Key (encrypted)
    'gemini_model' => 'gemini-2.5-flash', // Gemini model
    'openai_api_key' => '',             // OpenAI API Key (encrypted)
    'openai_model' => 'gpt-4o-mini',    // OpenAI model
    'claude_api_key' => '',             // Claude API Key (encrypted)
    'claude_model' => 'claude-sonnet-4-5-20250929', // Claude model
    'ai_language' => 'zh-TW',           // AI response language
    'ai_system_prompt' => '',           // AI personality settings
    'ai_probability' => 10,             // AI trigger probability (0-100)
    'ai_trigger_pages' => 'is_single',  // Trigger page condition
    'ai_text_color' => '#ff6b6b',       // AI text color
    'ai_display_duration' => 8,         // AI display duration (seconds)
    'ai_greet_enabled' => false,        // First-time visitor greeting
    'ai_greet_prompt' => '',            // Greeting prompt

    // LLM Settings (BETA)
    'ollama_endpoint' => 'http://localhost:11434',  // Ollama endpoint
    'ollama_model' => 'qwen3:8b',                   // Ollama model
    'ollama_replace_dialogue' => false,              // Replace built-in dialogs with LLM
    'ollama_disable_thinking' => true,               // Disable thinking mode

    // Extensions
    'extend' => [
        'js_area' => '',                // Custom JavaScript
    ],

    // Ukagaka List
    'ukagakas' => [
        'default_1' => [
            'name' => 'Frieren',
            'shell' => 'images/shell/Frieren/',
            'msg' => ['I am Frieren. A mage who has lived for over a thousand years.'],
            'show' => true,
            'dialog_filename' => 'Frieren',
        ],
        // ... more ukagakas
    ],
];
```

### Ukagaka Structure

```php
$ukagaka = [
    'name' => 'Frieren',              // Name
    'shell' => 'https://...png',      // Image URL
    'msg' => [                        // Dialog array
        'Dialog 1',
        'Dialog 2',
    ],
    'show' => true,                   // Can be shown
    'dialog_filename' => 'frieren',   // Dialog file name
];
```

---

## Hooks and Filters

Since the REST refactoring in `v2.9.2`, all plugin-level `do_action()` hooks (`mpu_loaded`, `mpu_before_html`, `mpu_after_html`, `mpu_settings_saved`) and `apply_filters()` hooks (`mpu_options`, `mpu_messages`, `mpu_ai_response`, `mpu_ukagaka_html`) have been removed.

Currently, the 4 remaining functional filters relate to LLM prompt construction:

### mpu_llm_system_prompt

Used to modify the system prompt sent to the LLM, containing the personality card, WordPress context, and behavior rules in its complete structure.

```php
add_filter('mpu_llm_system_prompt', function($prompt, $ukagaka_name, $personality_id, $context) {
    return $prompt;
}, 10, 4);
```

### mpu_llm_user_prompt

Used to append additional context before the user prompt, such as security alerts, event information, or external system messages.

```php
add_filter('mpu_llm_user_prompt', function($prompt, $ukagaka_name, $personality_id) {
    $attack_info = get_transient('mpu_llar_attack_info');
    if ($attack_info) {
        return $prompt . "\n【Security Alert】\n" . $attack_info;
    }
    return $prompt;
}, 10, 3);
```

### mpu_prompt_categories

Used to adjust the category definitions for LLM auto-talk, such as greeting, casual, time aware, statistics observation, etc.

```php
add_filter('mpu_prompt_categories', function($categories, $wp_info, $visitor_info, $time_context) {
    return $categories;
}, 10, 4);
```

### mpu_category_weights

Used to adjust the weighted random weights of the dialog categories.

```php
add_filter('mpu_category_weights', function($weights, $time_context, $visitor_info, $context_vars) {
    if ($time_context === 'Late Night') {
        $weights['philosophical'] = 15;
    }
    return $weights;
}, 10, 4);
```

---

## REST Endpoints

Currently, frontend and most admin testing processes rely primarily on REST APIs. The base namespace is `mp-ukagaka/v1`, with the full prefix being `/wp-json/mp-ukagaka/v1/`. Frontend requests must include an `X-WP-Nonce` provided by `mpuRestNonce`.

### Character / Settings

| Endpoint | Method | Description |
| --- | --- | --- |
| `/init` | GET | Gets initialization data, including shell, decorations, emojis, touchzones, settings |
| `/settings` | GET | Gets the frontend settings object |
| `/change` | POST | Switches character, or returns the list of available characters if no parameters are passed |
| `/shell-info` | GET / POST | Gets appearance information for a specific character |
| `/decoration-config` | GET / POST | Gets decoration settings |
| `/emoji-config` | GET / POST | Gets emoji settings |
| `/extend` | GET / POST | Extension entry point, returns frontend clickable tags |

### Dialog

| Endpoint | Method | Description |
| --- | --- | --- |
| `/nextmsg` | POST | Gets the next message; uses AI generation if LLM replace mode is enabled |
| `/dialog` | GET / POST | Loads dialog files under `dialogs/` |
| `/visitor-info` | GET | Gets visitor source and Slimstat-related information |
| `/decoration-prompts` | GET / POST | Gets prompts for clicking on decorations |
| `/wake-ghost` | POST | Wakes up a sleeping character |

### AI Chat

| Endpoint | Method | Description |
| --- | --- | --- |
| `/chat/context` | POST | Page-aware AI chat |
| `/chat/greet` | POST | First-time visitor greeting |
| `/chat/user` | POST | Multi-turn interactive chat (non-streaming) |
| `/chat/user-stream` | POST | SSE streaming interactive chat |

### Touch Interaction

| Endpoint | Method | Description |
| --- | --- | --- |
| `/touch/decoration` | POST | AI reaction when clicking on a decoration |
| `/touch/zone` | POST | Interaction reaction when clicking on a character zone |

### Admin Testing

| Endpoint | Method | Description |
| --- | --- | --- |
| `/test-connection/{provider}` | POST | Unified provider connection testing |
| `/clear-cache` | POST | Clears LLM API cache |

### Retained AJAX Endpoints

Although the main architecture has moved to REST, a few internal integrations still use `admin-ajax.php`:

| Action | Handler | Description |
| --- | --- | --- |
| `wp_ajax_mpu_test_diary_generate` | `mpu_ajax_test_diary_generate` | Tests diary generation from admin |
| `wp_ajax_nopriv_slimtrack` / `wp_ajax_slimtrack` | `mpu_bb_intercept_slimstat` | Bot Blocker intercepts Slimstat |
| `wp_ajax_nopriv_mbb_js_flag` / `wp_ajax_mbb_js_flag` | `mpu_bb_js_flag_handler` | Bot Blocker JS flag detection |

---

## JavaScript API

### Global Variables

After frontend initialization, the following global variables are exposed:

```javascript
window.mpuRestUrl;         // REST Base URL, e.g., /wp-json/mp-ukagaka/v1/
window.mpuRestNonce;       // Nonce for REST
window.mpuL10n;            // Frontend translation strings
window.mpuSettings;        // settings returned by /init
window.mpuInitData;        // Full response from /init
window.mpuPersonalityId;   // Current personality ID
window.mpuMsgList;         // Dialog data
window.mpuChatHistory;     // Multi-turn chat history
window.mpuChatModeActive;  // Is interactive chat mode active
window.mpuCanvasManager;   // Canvas animation manager
window.mpuDecorationConfig;
window.mpuTouchZones;
window.mpuEmojiBaseUrl;
window.mpuSupportedEmojis;
window.mpuEmojiMappings;
```

The data for `window.mpuSettings` comes from the `settings` block returned by `/init`, formatted similarly to:

```javascript
window.mpuSettings = {
  auto_talk: true,
  auto_talk_interval: 8,
  typewriter_speed: 40,
  ai_enabled: true,
  ai_probability: 10,
  ai_trigger_pages: "is_single",
  ai_text_color: "#000000",
  ai_display_duration: 8,
  ai_greet_first_visit: true,
  ollama_replace_dialogue: false,
  enable_chat_mode: false,
  sleep_mode: {
    enabled: false,
    frequency_multiplier: 1.0
  }
};
```

### Core Functions

```javascript
function mpu_nextmsg(trigger)
function mpu_hidemsg()
function mpu_showmsg()
function mpu_hiderobot()
function mpu_showrobot()
function mpuChange(num)
```

### AI / Interaction Functions

```javascript
function mpu_chat_context()
function mpu_greet_first_visitor(settings)
function mpu_sendUserMessage()
function mpu_toggleChatMode(enable)
```

### Canvas Manager

```javascript
window.mpuCanvasManager = {
  init: function(shellInfo, name),
  playAnimation: function(),
  stopAnimation: function(),
  isAnimationMode: function()
};
```

---

## Extension Development

### Adding a New AI Provider

The current provider architecture is under `includes/llm/providers/`. It is recommended not to add procedural cases directly to `ai-functions.php`, but to integrate with the provider factory instead.

Basic Steps:

1. Add `class-mpu-ai-provider-*.php`
2. Implement the existing provider interface
3. Register it in `class-mpu-ai-provider-factory.php`
4. Add the corresponding fields in `provider-helpers.php` and the admin settings page if needed
5. Ensure `/test-connection/{provider}` can test your new provider

### Adding New Message Codes

Message special codes are still handled by `mpu_msg_code()`; to add a new `:newcode[n]:` type, you can insert corresponding replacement rules in that process.

```php
if (preg_match('/:newcode\[(\d+)\]:/', $msg, $matches)) {
    $param = intval($matches[1]);
    $replacement = my_custom_function($param);
    $msg = str_replace($matches[0], $replacement, $msg);
}
```

### Adding New REST Endpoints

Please prioritize using the controller architecture under `includes/rest/` instead of adding old AJAX actions.

```php
class MPU_REST_Custom extends MPU_REST_Base {
    public function register_routes() {
        register_rest_route($this->namespace, '/custom', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'handle_custom'],
                'permission_callback' => '__return_true',
            ],
        ]);
    }

    public function handle_custom(WP_REST_Request $request) {
        return rest_ensure_response([
            'success' => true,
            'data' => ['message' => 'ok'],
        ]);
    }
}
```

Then register the new controller in `includes/rest/bootstrap.php`.

### Customizing Dialog Category Weights

Dialog categories and weights are currently centralized in `includes/llm/prompt-categories.php`. They can be adjusted via the `mpu_prompt_categories` and `mpu_category_weights` filters; this is more stable than directly modifying `llm-functions.php`.

### Customizing Observation Dialog Samples

To modify the strategy of extracting samples from built-in dialog files, review the function that builds example dialogs in `includes/llm/llm-functions.php`, while keeping track of `dialog_filename` and personality / ukagaka mapping logic.

### Future Outlook: Universal Character Manager Support

**Current Status:**

In the current system, character-specific animations and interaction logic (such as Frieren's wake-up animation, page-turning animation, sleep mode, etc.) are implemented through a hardcoded `window.mpuFrierenManager`. This means:

- Only the Frieren personality has a dedicated character manager.
- Other characters cannot use similar exclusive animations and interaction features.
- All references to the character manager point directly to `mpuFrierenManager`.

**Direction for Improvement:**

A universal character manager system can be implemented in the future, supporting multiple characters, each with their exclusive animations and interaction logic:

1. **Dynamic Manager Lookup Mechanism**
   - Implement a `getCurrentCharacterManager()` method in `ukagaka-anime.js`.
   - Dynamically look up the corresponding manager based on the current character's `dialog_filename` or personality ID.
   - Use a naming convention: `window.mpu{PersonalityId}Manager` (e.g., `mpuFrierenManager`, `mpuSakuraManager`).

2. **Unified Interface Standard**
   - Define a standard character manager interface (method names and properties).
   - All character managers must implement: `initMode()`, `triggerSpeaking()`, `isCharacterMode`, etc.
   - Ensure backward compatibility (maintain support for `mpuFrierenManager`).

3. **Implementation Locations**
   - Major modifications: `js/ukagaka-anime.js` (approx. 20 references need modification).
   - Minor modifications: `js/ukagaka-chat.js` and `js/ukagaka-core.js` (a few references).
   - Estimated workload: ~2-3 hours (including testing).

4. **Trigger Timing**
   - Can be implemented together when a second character requires exclusive animations or interactions.
   - Alternatively, refactor when Frieren's exclusive features need to be abstracted.

**Technical Key Points:**

- Current character info must be retrieved from `dialog_filename` or personality ID.
- Backward compatibility must be maintained to ensure existing Frieren features work normally.
- You can refer to `mpuFrierenManager` in `ghost/Frieren/frieren.js` as an implementation example.

---

## Security Considerations

### API Key Security

- All API Keys are stored encrypted using AES-256-CBC
- Uses WordPress `AUTH_KEY` as the encryption key
- Hidden using `type="password"` in the admin display

### Input Validation

```php
// Always use WordPress functions for filtering
$input = sanitize_text_field($_POST['input']);
$html = wp_kses_post($_POST['html']);
$url = esc_url($_POST['url']);
```

### Output Escaping

```php
// HTML output
echo esc_html($text);

// Attribute output
echo esc_attr($value);

// URL output
echo esc_url($url);

// JavaScript output
echo wp_json_encode($data);
```

### Nonce Validation

```php
// Add nonce to form
wp_nonce_field('mp_ukagaka_settings');

// Verify nonce
if (!wp_verify_nonce($_POST['_wpnonce'], 'mp_ukagaka_settings')) {
    wp_die('Security check failed');
}
```

### File Operations

- Use `mpu_secure_file_read()` and `mpu_secure_file_write()`
- Verify file paths are within allowed directories
- Check file size limits

---

## Development Standards

### Code Style

- Follow [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/)
- Use 4 spaces for indentation
- Function names use `mpu_` prefix

### Commenting Standards

```php
/**
 * Short description of the function
 *
 * Detailed description (optional)
 *
 * @since 2.1.0
 * @param string $param1 Parameter description
 * @param int    $param2 Parameter description
 * @return string Return value description
 */
function mpu_example_function($param1, $param2 = 0) {
    // ...
}
```

### Internationalization

```php
// Translatable string
__('String', 'mp-ukagaka')

// Directly outputted translatable string
_e('String', 'mp-ukagaka')

// String with placeholders
sprintf(__('Welcome %s', 'mp-ukagaka'), $name)
```

### Testing

1. Test all features in the development environment
2. Use `WP_DEBUG` to check for errors
3. Test multiple AI providers
4. Test multi-language environments
5. Ensure there are no errors in the browser console

---

## SPA (Single Page Application) Integration

MP Ukagaka supports SPA navigation. When a theme uses AJAX to load page content instead of full page refreshes, the plugin needs to be notified to reinitialize.

### Event Triggering

The theme should trigger the `mpu:spaReady` event after SPA navigation is complete:

```javascript
// Trigger after SPA navigation is complete
document.dispatchEvent(
  new CustomEvent("mpu:spaReady", {
    detail: {
      url: window.location.href, // Optional: current URL
      title: document.title, // Optional: page title
    },
  }),
);
```

### Plugin Response

The plugin listens to this event and executes:

1. Stops and restarts the auto-talk timer
2. Retriggers page-aware AI (if enabled)
3. Updates page context information

### Integration Example (Theme)

```javascript
// SPA navigation example using History API
document.addEventListener("click", function (e) {
  const link = e.target.closest("a");
  if (!link || link.target === "_blank") return;

  e.preventDefault();

  // Execute AJAX loading...
  fetch(link.href)
    .then((response) => response.text())
    .then((html) => {
      // Update page content
      document.getElementById("content").innerHTML = html;
      history.pushState({}, "", link.href);

      // Notify MP Ukagaka
      document.dispatchEvent(new CustomEvent("mpu:spaReady"));
    });
});

// Handle browser back/forward
window.addEventListener("popstate", function () {
  // After loading corresponding page content...
  document.dispatchEvent(new CustomEvent("mpu:spaReady"));
});
```

### Notes

- The event should be triggered after DOM updates are complete
- The plugin will automatically handle maintaining dialog state
- Chat history is retained within the same session

---

## Related Resources

- [WordPress Plugin Handbook](https://developer.wordpress.org/plugins/)
- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/)
- [Gemini API Documentation](https://ai.google.dev/docs)
- [OpenAI API Documentation](https://platform.openai.com/docs)
- [Claude API Documentation](https://docs.anthropic.com/)

---

### Happy Coding! 🎉
