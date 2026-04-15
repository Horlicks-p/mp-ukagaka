# MP Ukagaka Developer Guide

> 🛠️ Architecture overview, extension development, and API reference

---

## 📑 Table of Contents

1. [Architecture Overview](#architecture-overview)
2. [Module Description](#module-description)
3. [Data Structure](#data-structure)
4. [Hooks and Filters](#hooks-and-filters)
5. [AJAX Endpoints](#ajax-endpoints)
6. [JavaScript API](#javascript-api)
7. [Extension Development](#extension-development)
8. [Security Considerations](#security-considerations)
9. [Development Standards](#development-standards)

---

## Architecture Overview

### Directory Structure

```text
mp-ukagaka/
├── mp-ukagaka.php          # Main entry point
├── css/                    # Stylesheets
│   ├── mpu_style.css           # Frontend stylesheet
│   └── admin-style.css         # Admin stylesheet
├── includes/               # PHP Modules
│   ├── core/                   # Core function modules
│   │   ├── debug-functions.php     # Logging system (must load first)
│   │   ├── core-functions.php      # Core functions (Settings)
│   │   ├── utility-functions.php   # Utility functions
│   │   ├── ukagaka-functions.php   # Ukagaka management
│   │   └── frontend-functions.php  # Frontend functions
│   ├── rest/                   # REST API handler modules (OO Architecture)
│   │   ├── bootstrap.php           # REST Controller registration entry
│   │   ├── class-mpu-rest-base.php # Base class
│   │   ├── class-mpu-rest-chat.php # LLM Chat endpoints
│   │   ├── class-mpu-rest-ghost.php# Core/Personality endpoints
│   │   ├── class-mpu-rest-dialog.php# Dialogue management endpoints
│   │   ├── class-mpu-rest-touch.php# Touch interaction endpoints
│   │   └── class-mpu-rest-test.php # API test endpoints
│   ├── ajax/                   # AJAX handler modules
│   │   └── chat-api-handlers.php   # Chat mode API handler (Multi-turn chat wrapper)
│   ├── personality/            # Personality system modules
│   │   ├── personality-loader.php  # Personality system (JSON loader)
│   │   ├── personality-prompts.php # Personality prompt module
│   │   ├── personality-decorations.php # Decoration system
│   │   ├── personality-emoji.php   # Emoji system
│   │   └── emoji-mapper.php        # Emoji mapping and emotion analysis
│   ├── llm/                    # LLM/AI function modules
│   │   ├── api-cache.php           # API cache system
│   │   ├── ai-functions.php        # AI functions (Cloud API: Gemini, OpenAI, Claude)
│   │   ├── llm-functions.php       # LLM functions (Ollama specific)
│   │   ├── llm-context-builder.php # LLM context builder
│   │   ├── llm-slimstat.php        # LLM Slimstat integration
│   │   ├── prompt-categories.php   # Prompt category management
│   │   ├── chat-integrity.php      # Chat history checksum validation
│   │   ├── request-state.php       # Per-request state management
│   │   ├── provider-helpers.php    # AI provider helper functions
│   │   ├── streaming-helpers.php   # SSE streaming helpers
│   │   ├── provider-stream-http.php# cURL streaming HTTP client
│   │   ├── tool-loop-guard.php     # Tool call loop protection
│   │   ├── weather-functions.php   # Weather functions (Open-Meteo API)
│   │   ├── diary-functions.php     # AI Diary functions
│   │   └── providers/              # AI provider factory module
│   │       ├── bootstrap.php       # Loader
│   │       ├── interface-mpu-ai-provider.php # Interface
│   │       ├── class-mpu-ai-provider-base.php # Base class
│   │       ├── class-mpu-ai-provider-factory.php # Factory class
│   │       ├── class-mpu-ai-provider-gemini.php # Gemini provider
│   │       ├── class-mpu-ai-provider-openai.php # OpenAI provider
│   │       ├── class-mpu-ai-provider-claude.php # Claude provider
│   │       └── class-mpu-ai-provider-ollama.php # Ollama provider
│   ├── stats/                  # Statistics modules
│   │   ├── stats-collector.php     # Usage statistics collection
│   │   └── stats-analyzer.php      # Statistics analysis
│   ├── mcp-tools/              # Abilities/tool-call implementations
│   │   ├── manager.php             # Abilities manager
│   │   └── abilities/
│   │       ├── class-wp-bot-blocker-ability.php # Bot blocker ability
│   │       └── class-wp-postviews-ability.php   # Post views ability
│   ├── integrations/           # Integration modules
│   │   ├── abilities-integration.php   # Abilities API integration
│   │   ├── akismet-integration.php     # Akismet spam protection
│   │   ├── bot-blocker-integration.php # Bot blocker integration
│   │   └── turnstile-integration.php   # Turnstile CAPTCHA integration
│   └── admin-functions.php     # Admin functions
├── ghost/                  # Character personality configuration
│   ├── Frieren/
│   │   ├── shell/              # Character images
│   │   ├── decorations/        # Decoration images
│   │   ├── emojis/             # Character emoji images
│   │   ├── manifest.json       # Metadata and settings
│   │   ├── personality.md      # Core personality description
│   │   ├── instructions.md     # Behavioural instructions
│   │   ├── prompts.json        # Static dialogue categories
│   │   ├── dynamics.json       # Dynamic templates (with variables)
│   │   ├── weights.json        # Category weight configuration
│   │   ├── sleep_mode.json     # Sleep mode configuration
│   │   ├── calendar.json       # Calendar/holiday events
│   │   ├── touchzones.json     # Touch zone configuration
│   │   ├── decorations.json    # Decoration click prompts
│   │   ├── diary.json          # AI diary configuration
│   │   ├── emoji-keywords.json # Emoji keyword configuration
│   │   ├── frieren.js          # Character specific JavaScript
│   │   └── frieren-emoji.js    # Frieren emoji system
│   └── [other characters...]/
├── dialogs/                # Dialogue files
├── images/                 # Image resources
├── languages/              # Language files
├── docs/                   # Documentation
├── options/                # Admin settings pages
│   ├── options.php             # Admin page framework
│   ├── options_general.php     # General settings page
│   ├── options_ukagakas.php    # Ukagaka management page
│   ├── options_create.php      # Create new ukagaka page
│   ├── options_extend.php      # Extension settings page
│   ├── options_dialog.php      # Dialogue settings page
│   ├── options_page_ai.php     # AI settings page
│   ├── options_page_llm.php    # LLM settings page
│   ├── options_page_diary.php  # Diary settings page
│   ├── options_page_bot_blocker.php # Bot blocker settings page
│   └── options_page_stats.php  # Statistics settings page
├── js/                     # Frontend JavaScript modules
│   ├── dist/                   # Build output directory (Production)
│   │   ├── ukagaka-bundle.js       # Unminified bundle
│   │   ├── ukagaka-bundle.min.js   # Merged and minified core bundle
│   │   └── ukagaka-textarearesizer.min.js  # Admin tool (minified)
│   ├── ukagaka-base.js         # Base layer (Config + Utils + AJAX)
│   ├── ukagaka-core.js         # Frontend core JS (Message display, switching, etc.)
│   ├── ukagaka-features.js     # Frontend features JS (Settings configuration, event listening)
│   ├── ukagaka-context.js      # Page-aware AI dialog functionality
│   ├── ukagaka-greeting.js     # First visitor greeting functionality
│   ├── ukagaka-chat.js         # Chat functionality frontend (Interactive chat mode)
│   ├── ukagaka-dialog.js       # External dialog loading and fallback processing
│   ├── ukagaka-anime.js        # Canvas Animation Manager (Image Sequence Playback)
│   ├── ukagaka-emoji.js        # Emoji config loader
│   └── ukagaka-textarearesizer.js  # Admin textarea resizer
└── readme.txt              # WordPress plugin directory readme
```

### Module Loading Order

The plugin uses conditional loading mechanisms to load modules based on the execution environment (Frontend/Admin):

```php
// Loading logic in mp-ukagaka.php

// Core modules: Required by both frontend and admin
$core_modules = [
    'core/debug-functions.php',     // 0. Logging system (Must be loaded first)
    'core/core-functions.php',      // 1. Core functions (Settings)
    'core/utility-functions.php',   // 2. Utility functions
    'personality/personality-loader.php',  // 3. Personality system (JSON loader)
    'personality/personality-prompts.php', // 4. Personality prompt module
    'personality/personality-decorations.php', // 5. Decoration system
    'personality/personality-emoji.php',   // 6. Emoji system
    'stats/stats-collector.php',   // 7. Statistics collector (Load before ai-functions.php)
    'stats/stats-analyzer.php',    // 8. Statistics analyzer
    'llm/api-cache.php',           // 9. API cache system (v2.5.6, load before ai-functions.php)
    'llm/provider-helpers.php',    // 10. Provider common helpers (v2.10.0)
    'llm/tool-loop-guard.php',     // 11. Tool call loop protection mechanism (v2.10.0)
    'llm/providers/bootstrap.php', // 12. AI Providers factory pattern & classes (v2.10.0)
    'llm/ai-functions.php',        // 13. AI functions (Cloud API: Gemini, OpenAI, Claude)
    'llm/prompt-categories.php',   // 14. Prompt category management (Load before llm-functions.php)
    'llm/llm-slimstat.php',        // 15. LLM Slimstat integration (Load before llm-context-builder.php)
    'llm/llm-context-builder.php', // 16. LLM context builder (Load before llm-functions.php)
    'llm/weather-functions.php',   // 17. Weather functions (Open-Meteo API)
    'llm/diary-functions.php',     // 18. AI Diary functions (v2.5.0)
    'llm/llm-functions.php',       // 19. LLM functions (Local LLM: Ollama)
    'personality/emoji-mapper.php',        // 17. Emoji mapping (Load before AJAX handlers)
    'core/ukagaka-functions.php',   // 18. Ukagaka management
    'rest/bootstrap.php',           // 19. REST OO Controller registration entry
    'ajax/chat-api-handlers.php',   // 20. Chat mode API handler (Compatibility layer)
    'integrations/akismet-integration.php', // 24. Akismet spam protection integration
    'integrations/turnstile-integration.php', // 25. Turnstile integration
];

// Frontend modules (Loaded only in non-admin environment)
$frontend_modules = [
    'core/frontend-functions.php',  // Frontend functions
];

// Admin modules (Loaded only in admin environment)
$admin_modules = [
    'admin-functions.php',     // Admin functions
];
```

**Loading Timing:**

- All core modules are loaded on `plugins_loaded` action (priority 1).
- Frontend modules are loaded only when `!is_admin()`.
- Admin modules are loaded only when `is_admin()`.

### Constant Definitions

| Constant        | Description    | Value      |
| --------------- | -------------- | ---------- |
| `MPU_VERSION`   | Plugin Version | `"2.5.6"`  |
| `MPU_MAIN_FILE` | Main File Path | `__FILE__` |

---

## Module Description

### core-functions.php

Core function module, responsible for settings management.

#### Main Functions

```php
/**
 * Get default settings
 * @return array Default settings array
 */
function mpu_default_opt(): array

/**
 * Get plugin options (cached)
 * @return array Options array
 */
function mpu_get_option(): array
```

**Note:** `mpu_count_total_msg()` is located in `ukagaka-functions.php`.

### utility-functions.php

Utility function module, providing various helper functions (String processing, filtering, file operations, encryption, etc.).

#### String/Array Conversion

```php
/**
 * Array to string (Separated by double newlines)
 * @param array $arr Input array
 * @return string Output string
 */
function mpu_array2str($arr = []): string

/**
 * String to array (Separated by newlines, filtering empty lines)
 * @param string $str Input string
 * @return array Output array
 */
function mpu_str2array($str = ""): array
```

#### Output Filtering

```php
/**
 * HTML output filter (using esc_html)
 * @param string $str Input string
 * @return string Filtered string
 */
function mpu_output_filter($str): string

/**
 * JavaScript output filter (using esc_js)
 * @param string $str Input string
 * @return string Filtered string
 */
function mpu_js_filter($str): string

/**
 * Input filter (stripslashes)
 * @param string $str Input string
 * @return string Filtered string
 */
function mpu_input_filter($str): string

```

#### Secure File Operations

```php
/**
 * Secure file read (Using WordPress Filesystem API)
 * @param string $file_path File path
 * @return string|WP_Error File content or error
 */
function mpu_secure_file_read($file_path)

/**
 * Secure file write (Using WordPress Filesystem API)
 * @param string $file_path File path
 * @param string $content File content
 * @return bool|WP_Error Success or error
 */
function mpu_secure_file_write($file_path, $content)

/**
 * Get dialogues directory path
 * @return string Directory path
 */
function mpu_get_dialogs_dir(): string

/**
 * Ensure dialogues directory exists
 * @return bool Success or not
 */
function mpu_ensure_dialogs_dir(): bool
```

#### API Key Encryption

```php
/**
 * Get encryption key (Based on WordPress AUTH_KEY)
 * @return string Encryption key
 */
function mpu_get_encryption_key(): string

/**
 * Encrypt API Key (AES-256-CBC)
 * @param string $api_key Original API Key
 * @return string Encrypted string
 */
function mpu_encrypt_api_key($api_key): string

/**
 * Decrypt API Key
 * @param string $encrypted_key Encrypted string
 * @return string|false Decrypted API Key or false
 */
function mpu_decrypt_api_key($encrypted_key)

/**
 * Check if API Key is encrypted
 * @param string $api_key API Key string
 * @return bool Is encrypted
 */
function mpu_is_api_key_encrypted($api_key): bool
```

### ai-functions.php

AI functions module, handling Cloud AI API calls (Gemini, OpenAI, Claude) and Ollama integration.

#### ai-functions.php Main Functions

```php
/**
 * Call AI API (Unified entry)
 * @param string $provider Provider (gemini/openai/claude/ollama)
 * @param string $api_key API Key (Ollama doesn't need it)
 * @param string $system_prompt System prompt (Personality settings)
 * @param string $user_prompt User prompt
 * @param string $language Language code
 * @param array|null $mpu_opt Options array (Optional)
 * @return string|WP_Error AI response or error
 */
function mpu_call_ai_api($provider, $api_key, $system_prompt, $user_prompt, $language, $mpu_opt = null)

/**
 * Call Gemini API
 * @param string $api_key API Key
 * @param string $model Model name (e.g., gemini-2.5-flash)
 * @param string $system_prompt System prompt
 * @param string $user_prompt User prompt
 * @param string $language Language code
 * @return string|WP_Error Generated text or error
 */
function mpu_call_gemini_api($api_key, $model, $system_prompt, $user_prompt, $language)

/**
 * Call OpenAI API
 * @param string $api_key API Key
 * @param string $model Model name (e.g., gpt-4o-mini)
 * @param string $system_prompt System prompt
 * @param string $user_prompt User prompt
 * @param string $language Language code
 * @return string|WP_Error Generated text or error
 */
function mpu_call_openai_api($api_key, $model, $system_prompt, $user_prompt, $language)

/**
 * Call Claude API
 * @param string $api_key API Key
 * @param string $model Model name (e.g., claude-sonnet-4-5-20250929)
 * @param string $system_prompt System prompt
 * @param string $user_prompt User prompt
 * @param string $language Language code
 * @return string|WP_Error Generated text or error
 */
function mpu_call_claude_api($api_key, $model, $system_prompt, $user_prompt, $language)

/**
 * Call Ollama API (Local or Remote)
 * @param string $endpoint Ollama endpoint URL
 * @param string $model Model name (e.g., qwen3:8b)
 * @param string $system_prompt System prompt
 * @param string $user_prompt User prompt
 * @param string $language Language code
 * @return string|WP_Error Generated text or error
 */
function mpu_call_ollama_api($endpoint, $model, $system_prompt, $user_prompt, $language)


/**
 * Get language instruction
 * @param string $language Language code
 * @return string Language instruction
 */
function mpu_get_language_instruction(string $language): string

/**
 * Get allowed conditional tags list
 * @return array Conditional tags array
 */
function mpu_get_allowed_conditional_tags(): array

/**
 * Load personality dynamic prompts
 * @param string|null $personality_id Personality ID, null for current
 * @return array Dynamic prompt configuration array
 */
function mpu_load_personality_dynamic_prompts($personality_id = null): array

/**
 * Load personality emoji keywords
 * @param string|null $personality_id Personality ID, null for current
 * @return array Emoji keywords configuration array
 */
function mpu_load_personality_emoji_keywords($personality_id = null): array
```

#### Personality File Structure

Each personality folder should contain:

- **manifest.json** (Required): Metadata and settings
  - `id`: Personality ID
  - `name`, `name_en`, `name_zh`: Multilingual names
  - `version`: Version number
  - `settings`: Character settings (e.g. `max_response_length`, `speech_style`, `tone`)
  - `character_traits`: Character traits (e.g. `age`, `race`, `occupation`, `personality`)

- **prompts.json** (Optional): Static dialogue categories
  - Key is category name, value is array of prompts

- **dynamics.json** (Optional): Dynamic templates (with variable substitution)
  - Supports `{variable_name}` variable substitution
  - Contains `time_aware_dynamic`, `tech_observation`, `bot_detection` etc. categories

- **weights.json** (Optional): Category weight configuration
  - `base_weights`: Base weights
  - `time_adjustments`: Time based adjustments

- **decorations.json** (Optional): Decoration click prompts
  - `items`: Decoration items array, each containing:
    - `id`: Decoration ID
    - `image`: Image path (relative to `decorations/` folder)
    - `position`: Position settings (e.g. `{"bottom": "0px", "right": "0px"}`)
    - `size`: Size settings (e.g. `{"width": "100px", "height": "auto"}`)
    - `z_index`: Z-index (number)
    - `prompt`: Prompt when clicked
    - `transform`: CSS transform (optional, e.g. `scale(1)`)

- **emoji-keywords.json** (Optional, v2.4.0): Emoji trigger keywords
  - `mappings`: Mapping of emoji types to keywords
  - Format example:

    ```json
    {
      "mappings": {
        "happy": {
          "keywords": ["happy", "glad"],
          "file": "happy.png",
          "weight": 10
        }
      }
    }
    ```

    - **diary.json** (Optional, v2.5.0): AI Diary settings
      - `categories`: Diary category configuration
      - Format example:

        ```json
        {
          "categories": {
            "daily": {
              "weight": 10,
              "title_themes": ["Daily Life"],
              "prompts": ["Write a diary about daily life"]
            }
          }
        }
        ```

- **script** (Optional): Character specific JavaScript file
  - e.g. `frieren.js`, automatically loaded by frontend

#### Supported AI Providers

| Provider | Function                | API Endpoint                        | Model Selection                                    |
| -------- | ----------------------- | ----------------------------------- | -------------------------------------------------- |
| Gemini   | `mpu_call_gemini_api()` | `generativelanguage.googleapis.com` | Supported (gemini-2.5-flash, gemini-2.5-pro, etc.) |
| OpenAI   | `mpu_call_openai_api()` | `api.openai.com`                    | Supported (gpt-4o-mini, gpt-4o, etc.)              |
| Claude   | `mpu_call_claude_api()` | `api.anthropic.com`                 | Supported (claude-sonnet-4-5-20250929, etc.)       |
| Ollama   | `mpu_call_ollama_api()` | Local or Remote Ollama Service      | Supported (Any Ollama model)                       |

### llm-functions.php (BETA)

> ⚠️ **Note**: This module is in **BETA**. API may change.

LLM functions module, specifically handling Ollama local LLM integration.

#### llm-functions.php Main Functions

```php
/**
 * Detect if endpoint is a remote connection
 * @param string $endpoint Ollama endpoint URL
 * @return bool Is remote connection (true = Remote, false = Local)
 */
function mpu_is_remote_endpoint(string $endpoint): bool

/**
 * Get appropriate timeout based on endpoint type and operation type
 * @param string $endpoint Ollama endpoint URL
 * @param string $operation_type Operation type: 'check', 'api_call', 'test'
 * @return int Timeout (seconds)
 */
function mpu_get_ollama_timeout(string $endpoint, string $operation_type = 'api_call'): int

/**
 * Validate and normalize Ollama endpoint URL
 * @param string $endpoint Raw endpoint URL
 * @return string|WP_Error Normalized URL or error
 */
function mpu_validate_ollama_endpoint(string $endpoint)

/**
 * Check if Ollama service is available (Fast check, cached)
 * @param string $endpoint Ollama endpoint
 * @param string $model Model name
 * @return bool Is available
 */
function mpu_check_ollama_available(string $endpoint, string $model): bool

/**
 * Generate random dialogue using LLM (Replacing built-in dialogue)
 * @param string $ukagaka_name Ukagaka name
 * @return string|false Generated dialogue, or false on failure
 */
function mpu_generate_llm_dialogue(string $ukagaka_name = 'default_1')

/**
 * Check if LLM replacing built-in dialogue is enabled
 * @return bool
 */
function mpu_is_llm_replace_dialogue_enabled(): bool

/**
 * Get Ollama settings
 * @return array|false Settings array, or false if not enabled
 */
function mpu_get_ollama_settings()
```

#### Timeout Settings

| Operation Type           | Local Connection | Remote Connection |
| ------------------------ | ---------------- | ----------------- |
| Service Check (`check`)  | 3s               | 10s               |
| API Call (`api_call`)    | 60s              | 90s               |
| Test Connection (`test`) | 30s              | 45s               |

#### Usage Example

```php
// Check if service is available
$endpoint = 'https://your-domain.com';
$model = 'qwen3:8b';
if (mpu_check_ollama_available($endpoint, $model)) {
    // Service available, generate dialogue
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

Chat mode API handler, providing a multi-turn conversation AI call wrapper for the new REST Controllers, isolating complex provider option handling.

#### Main Functions

```php
/**
 * AI API Call (Supports multi-turn chat, automatically dispatches via Factory)
 * @param string $provider Provider
 * @param string $api_key API Key
 * @param string $system_prompt System prompt
 * @param array $messages Conversation history
 * @param string $language Language
 * @param array $options Options (max_tokens, temperature, etc.)
 * @return string|WP_Error AI response
 */
function mpu_call_ai_api_with_messages($provider, $api_key, $system_prompt, $messages, $language, $options = [])

/**
 * Provider-specific wrappers
 */
function mpu_call_ollama_with_messages($system_prompt, $messages, $options = [])
function mpu_call_openai_with_messages($api_key, $system_prompt, $messages, $options = [])
function mpu_call_claude_with_messages($api_key, $system_prompt, $messages, $options = [])
function mpu_call_gemini_with_messages($api_key, $system_prompt, $messages, $options = [])
```

### REST API Module (OO Architecture)

Introduced starting v2.12.0, driven uniformly by `bootstrap.php`. All Controllers inherit from `MPU_REST_Base`.

#### Main Class Responsibilities

- **MPU_REST_Chat**: Centralizes all AI chat-related endpoints.
  - `/chat/context` (Page-aware)
  - `/chat/greet` (First greeting)
  - `/chat/user` (Synchronous chat)
  - `/chat/user-stream` (SSE streaming chat)
- **MPU_REST_Ghost**: Handles personality lists and initial configuration.
- **MPU_REST_Dialog**: Manages static and local dialogue reading.
- **MPU_REST_Touch**: Handles touch zone interactions.
- **MPU_REST_Test**: Provides backend connection testing functionality.

### diary-functions.php (v2.5.0)

AI Diary functions module, responsible for auto-generating and publishing character diaries.

#### diary-functions.php Main Functions

```php
/**
 * Get diary title prefix
 * @param string|null $personality_id Personality ID
 * @return string Prefix (e.g., "[Frieren's Diary] ")
 */
function mpu_get_diary_title_prefix($personality_id = null): string

/**
 * Check if diary should be triggered (Based on probability and daily limit)
 * @return bool Should trigger
 */
function mpu_should_trigger_diary(): bool

/**
 * Generate diary content
 * @return array|WP_Error Diary data or error
 */
function mpu_generate_diary_content()

/**
 * Publish diary post
 * @param array $diary_data Diary data
 * @return int|WP_Error Post ID or error
 */
function mpu_publish_diary_post($diary_data)
```

### ukagaka-functions.php

Ukagaka management module, handling character operations and dialogue management.

#### ukagaka-functions.php Main Functions

```php

/**
 * Get Ukagaka data
 * @param string|false $num Ukagaka key (false for current)
 * @return array|false Ukagaka data or false
 */
function mpu_get_ukagaka($num = false)

/**
 * Get Ukagaka shell image URL
 * @param string|false $num Ukagaka key (false for current)
 * @param bool $echo Whether to echo directly
 * @return string Image URL
 */
function mpu_get_shell($num = false, $echo = false): string

/**
 * Get specific message
 * @param int $msgnum Message index
 * @param string|false $num Ukagaka key
 * @param bool $echo Whether to echo directly
 * @return string Message content
 */
function mpu_get_msg($msgnum = 0, $num = false, $echo = false): string

/**
 * Get random message
 * @param string|false $num Ukagaka key
 * @param bool $echo Whether to echo directly
 * @return string Message content
 */
function mpu_get_random_msg($num = false, $echo = false): string


/**
 * Get common message
 * @return string Common message content
 */
function mpu_common_msg(): string

/**
 * Get message array
 * @param string|false $num Ukagaka key
 * @return array Message array
 */
function mpu_get_msg_arr($num = false): array


/**
 * Process special codes in message
 * @param array $msglist Message array
 * @return array Processed message array
 */
function mpu_msg_code($msglist = []): array



/**
 * Count total messages of all Ukagakas
 * @return int Total count
 */
function mpu_count_total_msg(): int

/**
 * Load dialogues from external file
 * @param string $filename_base Filename (without extension)
 * @return array Dialogue array
 */
function mpu_get_msg_from_file($filename_base): array
```

### ajax-handlers.php

AJAX handlers module, handling all AJAX requests.

#### ajax-handlers.php Main Functions

```php
/**
 * Handle next message request
 */
function mpu_ajax_nextmsg()

/**
 * Handle extension function request
 */
function mpu_ajax_extend()

/**
 * Handle switch Ukagaka request
 */
function mpu_ajax_change()

/**
 * Handle get settings request
 */
function mpu_ajax_get_settings()

/**
 * Handle load dialogue file request
 */
function mpu_ajax_load_dialog()

/**
 * Handle AI page context chat request
 */
function mpu_ajax_chat_context()

/**
 * Handle get visitor info request (Requires Slimstat)
 */
function mpu_ajax_get_visitor_info()

/**
 * Handle AI first greeting request
 */
function mpu_ajax_chat_greet()

/**
 * Handle test Ollama connection request (BETA)
 */
function mpu_ajax_test_ollama_connection()
```

> See [AJAX Endpoints](#ajax-endpoints) section for details.

### frontend-functions.php

Frontend functions module, responsible for page display and resource loading.

#### frontend-functions.php Main Functions

```php
/**
 * Check if should show on current page
 * @return bool Should show
 */
function mpu_is_show_page(): bool

/**
 * Output buffering callback (For inserting Ukagaka HTML)
 * @param string $buffer Page content
 * @return string Processed content
 */
function mpu_ob_callback($buffer): string

/**
 * Shutdown callback (Ensure HTML insertion)
 */
function mpu_shutdown_callback(): void

/**
 * Generate Ukagaka HTML
 * @param string|false $num Ukagaka key
 * @return string HTML string
 */
function mpu_html($num = false): string

/**
 * Echo Ukagaka HTML
 */
function mpu_echo_html(): void

/**
 * Enqueue frontend assets (CSS/JS)
 */
function mpu_enqueue_frontend_assets(): void

/**
 * Output settings in head (JavaScript variables)
 */
function mpu_head(): void
```

### admin-functions.php

Admin functions module, handling settings saving and admin interface.

#### admin-functions.php Main Functions

```php
/**
 * Enqueue admin assets (CSS/JS)
 * @param string $hook_suffix Current page hook
 */
function mpu_admin_enqueue_scripts($hook_suffix): void

/**
 * Handle options save
 */
function mpu_handle_options_save(): void

/**
 * Generate dialogue file (TXT or JSON format)
 * @param string $filename Filename (without extension)
 * @param array $msg_array Message array
 * @param string $ext Extension (txt or json)
 * @return bool Success
 */
function mpu_generate_dialog_file($filename, $msg_array, $ext): bool

/**
 * Admin menu page HTML
 */
function mpu_options_page_html(): void

/**
 * Register admin menu
 */
function mpu_options(): void
```

---

## Data Structure

### Settings Structure ($mpu_opt)

```php
$mpu_opt = [
    // Basic Settings
    'cur_ukagaka' => 'default_1',      // Current Ukagaka
    'show_ukagaka' => true,             // Show Ukagaka
    'show_msg' => true,                 // Show balloon
    'default_msg' => 0,                 // 0=Random, 1=First
    'next_msg' => 0,                    // 0=Sequential, 1=Random
    'click_ukagaka' => 0,               // 0=Next, 1=No Action
    'insert_html' => 0,                 // HTML insert position
    'no_style' => false,                // No custom style
    'no_page' => '',                    // Exclude pages

    // Auto Talk
    'auto_talk' => true,                // Enable auto talk
    'auto_talk_interval' => 8,          // Interval (seconds)
    'typewriter_speed' => 40,           // Typing speed (ms/char)

    // External Dialogue Files
    'use_external_file' => true,        // Use external file (Fixed to true)
    'external_file_format' => 'txt',     // File format (txt/json)

    // Session Settings
    'auto_msg' => '',                   // Fixed message
    'common_msg' => '',                 // Common dialogue

    // AI Settings (Page Awareness)
    'ai_enabled' => false,              // Enable AI
    'ai_provider' => 'gemini',          // AI Provider (gemini/openai/claude/ollama)
    'ai_api_key' => '',                 // Gemini API Key (Encrypted)
    'gemini_model' => 'gemini-2.5-flash', // Gemini Model
    'openai_api_key' => '',             // OpenAI API Key (Encrypted)
    'openai_model' => 'gpt-4.1-mini-2025-04-14',    // OpenAI Model
    'claude_api_key' => '',             // Claude API Key (Encrypted)
    'claude_model' => 'claude-sonnet-4-5-20250929', // Claude Model
    'ai_language' => 'zh-TW',           // AI Response Language
    'ai_system_prompt' => '',           // AI Personality
    'ai_probability' => 10,             // Trigger Probability (0-100)
    'ai_trigger_pages' => 'is_single',  // Trigger Page Conditions
    'ai_text_color' => '#ff6b6b',       // AI Text Color
    'ai_display_duration' => 8,         // Display Duration (seconds)
    'ai_greet_enabled' => false,        // First Visitor Greeting
    'ai_greet_prompt' => '',            // Greeting Prompt

    // LLM Settings (BETA)
    'ollama_endpoint' => 'http://localhost:11434',  // Ollama Endpoint
    'ollama_model' => 'qwen3:8b',                   // Ollama Model
    'ollama_replace_dialogue' => false,              // Use LLM Replace Dialogue
    'ollama_disable_thinking' => true,               // Disable Thinking Mode

    // Extensions
    'extend' => [
        'js_area' => '',                // Custom JavaScript
    ],

    // Ukagaka List
    'ukagakas' => [
        'default_1' => [
            'name' => 'フリーレン',
            'shell' => 'images/shell/Frieren/',
            'msg' => ['フリレーンだ。千年以上生きた魔法使いだ。'],
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
    'name' => 'Frieren',               // Name
    'shell' => 'https://...png',      // Image URL
    'msg' => [                        // Dialogue Array
        'Dialogue 1',
        'Dialogue 2',
    ],
    'show' => true,                   // Visible
    'dialog_filename' => 'frieren',   // Dialogue Filename
];
```

---

## Hooks and Filters

### Actions

```php
// After plugin loaded
do_action('mpu_loaded');

// Before Ukagaka HTML generation
do_action('mpu_before_html');

// After Ukagaka HTML generation
do_action('mpu_after_html');

// After settings saved
do_action('mpu_settings_saved', $mpu_opt);
```

### Filters

```php
// Filter options
$mpu_opt = apply_filters('mpu_options', $mpu_opt);

// Filter message array
$messages = apply_filters('mpu_messages', $messages, $ukagaka_key);

// Filter AI response
$response = apply_filters('mpu_ai_response', $response, $prompt);

// Filter Ukagaka HTML
$html = apply_filters('mpu_ukagaka_html', $html);
```

---

## AJAX Endpoints

All AJAX requests use `admin-ajax.php`.

### mpu_nextmsg

Get next message.

**Request:**

```javascript
{
    action: 'mpu_nextmsg',
    ukagaka: 'default_1',    // Ukagaka key
    current: 0,               // Current message index
    mode: 'next'              // next or random
}
```

**Response:**

```javascript
{
    success: true,
    data: {
        msg: 'Dialogue content',
        index: 1
    }
}
```

### mpu_change

Switch Ukagaka.

**Request:**

```javascript
{
    action: 'mpu_change',
    ukagaka: 'frieren'
}
```

**Response:**

```javascript
{
    success: true,
    data: {
        name: 'Frieren',
        shell: 'https://.../frieren.png',
        messages: ['Dialog 1', 'Dialog 2']
    }
}
```

### mpu_test_ollama_connection (BETA)

> ⚠️ **Note**: This endpoint is in **BETA**.

Test Ollama connection.

**Request:**

```javascript
{
    action: 'mpu_test_ollama_connection',
    endpoint: 'https://your-domain.com',  // Ollama endpoint
    model: 'qwen3:8b',                     // Model name
    nonce: '...'                           // WordPress nonce
}
```

**Response (Success):**

```javascript
{
    success: true,
    data: 'Connection successful (Remote), model response normal (Preview: Hello...)'
}
```

**Response (Failure):**

```javascript
{
    success: false,
    data: 'Connection failed: Unable to connect to remote Ollama service...'
}
```

### mpu_load_dialog

Load external dialogue file.

**Request:**

```javascript
{
    action: 'mpu_load_dialog',
    filename: 'frieren',
    format: 'json'
}
```

**Response:**

```javascript
{
    success: true,
    data: {
        messages: ['Dialog 1', 'Dialog 2', 'Dialog 3']
    }
}
```

### mpu_ai_context_chat

AI page awareness chat.

**Request:**

```javascript
{
    action: 'mpu_ai_context_chat',
    title: 'Post Title',
    content: 'Post Content Summary...',
    nonce: 'xxx'
}
```

**Response:**

```javascript
{
    success: true,
    data: {
        message: 'AI generated comment'
    }
}
```

### mpu_get_visitor_info

Get visitor info (Requires Slimstat).

**Request:**

```javascript
{
    action: 'mpu_get_visitor_info',
    nonce: 'xxx'
}
```

**Response:**

```javascript
{
    success: true,
    data: {
        country: 'TW',
        referer: 'https://google.com',
        searchterms: 'Search Keywords'
    }
}
```

### mpu_ai_greet

AI first visitor greeting.

**Request:**

```javascript
{
    action: 'mpu_ai_greet',
    visitor_info: { country: 'TW', ... },
    nonce: 'xxx'
}
```

**Response:**

```javascript
{
    success: true,
    data: {
        message: 'Welcome friend from Taiwan!'
    }
}
```

---

## JavaScript API

### Global Object

```javascript
// Settings object
window.mpuSettings = {
  ajaxUrl: "/wp-admin/admin-ajax.php",
  nonce: "xxx",
  autoTalk: true,
  autoTalkInterval: 8000,
  typewriterSpeed: 40,
  aiEnabled: true,
  aiTextColor: "#ff6b6b",
  aiDisplayDuration: 8000,
  // ...
};
```

### Core Functions (ukagaka-core.js)

```javascript
/**
 * Show next message
 * @param {string} mode - 'next' or 'random'
 */
function mpu_nextmsg(mode)

/**
 * Hide balloon
 */
function mpu_hidemsg()

/**
 * Show balloon
 */
function mpu_showmsg()

/**
 * Hide Ukagaka
 */
function mpu_hideukagaka()

/**
 * Show Ukagaka
 */
function mpu_showukagaka()

/**
 * Switch Ukagaka
 */
function mpuChange()

/**
 * Show specific message (with typewriter effect)
 * @param {string} message - Message content
 * @param {object} options - Options
 */
function mpu_showMessage(message, options)
```

### AI Feature Functions

#### ukagaka-context.js

```javascript
/**
 * AI Context Chat: Generate AI response based on current page content
 */
function mpu_chat_context()
```

#### ukagaka-greeting.js

````javascript
/**
 * First Visitor Greeting: Generate personalized greeting based on visitor info
 * @param {Object} settings - Settings object
 * @returns {Promise}
 */
function mpu_greet_first_visitor(settings)

### Canvas Animation Functions (ukagaka-anime.js)

```javascript
/**
 * Global Canvas Manager Object
 */
window.mpuCanvasManager = {
    /**
     * Initialize Canvas
     * @param {object} shellInfo - Image or folder info
     * @param {string} name - Ukagaka name
     */
    init: function(shellInfo, name),

    /**
     * Start animation
     */
    playAnimation: function(),

    /**
     * Stop animation
     */
    stopAnimation: function(),

    /**
     * Check if in animation mode
     * @return {boolean}
     */
    isAnimationMode: function()
};
````

---

## Extension Development

### Adding a New AI Provider

1. Add new function in `ai-functions.php`:

```php
function mpu_call_newprovider_api($prompt, $system_prompt) {
    $mpu_opt = mpu_get_option();
    $api_key = mpu_decrypt_api_key($mpu_opt['newprovider_api_key']);

    // API call logic...

    return $response;
}
```

2\. Add case in `mpu_call_ai_api()`:

```php
case 'newprovider':
    return mpu_call_newprovider_api($prompt, $system_prompt);
```

3\. Add corresponding options in the admin settings page.

### Adding New Message Codes

Add in `mpu_process_msg_codes()` of `ukagaka-functions.php`:

```php
// Handle :newcode[param]: format
if (preg_match('/:newcode\[(\d+)\]:/', $msg, $matches)) {
    $param = intval($matches[1]);
    $replacement = my_custom_function($param);
    $msg = str_replace($matches[0], $replacement, $msg);
}
```

### Adding New AJAX Endpoints

In `ajax-handlers.php`:

```php
add_action('wp_ajax_mpu_custom_action', 'mpu_handle_custom_action');
add_action('wp_ajax_nopriv_mpu_custom_action', 'mpu_handle_custom_action');

function mpu_handle_custom_action() {
    // Verify nonce
    check_ajax_referer('mpu_nonce', 'nonce');

    // Logic...

    wp_send_json_success(['data' => $result]);
}
```

### Customizing Dialogue Category Weights

The system uses weighted random selection to determine which type of dialogue to generate. You can modify the weights in the `mpu_generate_llm_dialogue()` function in `includes/llm-functions.php`:

```php
// Category weight settings (higher values have higher probability of being selected)
// Total weight: 100
$category_weights = [
    'greeting' => 8,           // Greeting
    'casual' => 10,            // Casual chat
    'time_aware' => 8,         // Time-aware
    'observation' => 10,       // Observation/Thinking
    'magic_research' => 8,     // Magic research
    'tech_observation' => 6,   // Tech observation (lower weight)
    'statistics' => 8,         // Statistics
    'memory' => 10,            // Memory
    'admin_comment' => 8,     // Admin comments
    'unexpected' => 10,        // Unexpected reactions
    'silence' => 8,            // Silence
    'bot_detection' => 6,     // BOT detection
];
```

**Weight Adjustment Recommendations:**

- It's recommended to keep total weight at 100 for easier probability calculation
- Lowering a category's weight reduces its appearance frequency
- Increasing a category's weight increases its appearance frequency

### Customizing Observation Category Built-in Dialogue Reading

The observation category automatically reads dialogues from the current character's built-in dialogue file. You can modify this functionality in the `mpu_build_frieren_style_examples()` function in `includes/llm-functions.php`:

```php
// Read dialogues from built-in dialogue file (up to 5 lines)
$mpu_opt = mpu_get_option();
$current_ukagaka = $mpu_opt['cur_ukagaka'] ?? 'default_1';
if (isset($mpu_opt['ukagakas'][$current_ukagaka])) {
    $ukagaka = $mpu_opt['ukagakas'][$current_ukagaka];
    $dialog_filename = $ukagaka['dialog_filename'] ?? $current_ukagaka;

    // Read dialogue file
    if (function_exists('mpu_get_msg_from_file')) {
        $dialog_messages = mpu_get_msg_from_file($dialog_filename);
        // ... processing logic
    }
}
```

**Adjustable Parameters:**

- Maximum read count: Currently 5 lines, can modify the number in `min(5, $count)`
- Character length limit: Currently 50 characters, can modify in `mb_strlen($msg) <= 50`
- Filter conditions: Can add more filter conditions to screen suitable dialogues

---

## Security Considerations

### API Key Security

- All API Keys are stored using AES-256-CBC encryption.
- Uses WordPress `AUTH_KEY` as encryption key.
- Displayed as `type="password"` in Admin UI.

### Input Validation

```php
// Always use WordPress sanitization functions
$input = sanitize_text_field($_POST['input']);
$html = wp_kses_post($_POST['html']);
$url = esc_url($_POST['url']);
```

### Output Escaping

```php
// HTML Output
echo esc_html($text);

// Attribute Output
echo esc_attr($value);

// URL Output
echo esc_url($url);

// JavaScript Output
echo wp_json_encode($data);
```

### Nonce Verification

```php
// Add nonce field
wp_nonce_field('mp_ukagaka_settings');

// Verify nonce
if (!wp_verify_nonce($_POST['_wpnonce'], 'mp_ukagaka_settings')) {
    wp_die('Security check failed');
}
```

### File Operations

- Use `mpu_secure_file_read()` and `mpu_secure_file_write()`.
- Validate file paths within allowed directories.
- Check file size limits.

---

## Development Standards

### Code Style

- Follow [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/).
- Use 4 spaces for indentation.
- Use `mpu_` prefix for function names.

### Documentation Standards

```php
/**
 * Short description of function
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

// Echo translatable string
_e('String', 'mp-ukagaka')

// String with placeholder
sprintf(__('Welcome %s', 'mp-ukagaka'), $name)
```

### Testing

1. Test all functions in development environment.
2. Use `WP_DEBUG` to check for errors.
3. Test multiple AI providers.
4. Test multi-language environments.
5. Check browser console for errors.

---

## SPA (Single Page Application) Integration

MP Ukagaka supports SPA navigation. When your theme uses AJAX to load page content instead of full page refresh, you need to notify the plugin to reinitialize.

### Event Trigger

Your theme should dispatch the `mpu:spaReady` event after SPA navigation completes:

```javascript
// Dispatch after SPA navigation completes
document.dispatchEvent(
  new CustomEvent("mpu:spaReady", {
    detail: {
      url: window.location.href, // Optional: Current URL
      title: document.title, // Optional: Page title
    },
  }),
);
```

### Plugin Response

The plugin listens for this event and will:

1. Stop and restart the auto-talk timer
2. Re-trigger page-aware AI (if enabled)
3. Update page context information

### Theme Integration Example

```javascript
// SPA navigation example using History API
document.addEventListener("click", function (e) {
  const link = e.target.closest("a");
  if (!link || link.target === "_blank") return;

  e.preventDefault();

  // Perform AJAX loading...
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
  // After loading page content...
  document.dispatchEvent(new CustomEvent("mpu:spaReady"));
});
```

### Notes

- Dispatch the event after DOM updates are complete
- The plugin automatically maintains dialogue state
- Chat history is preserved within the same session

---

## Related Resources

- [WordPress Plugin Handbook](https://developer.wordpress.org/plugins/)
- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/)
- [Gemini API Docs](https://ai.google.dev/docs)
- [OpenAI API Docs](https://platform.openai.com/docs)
- [Claude API Docs](https://docs.anthropic.com/)

---

### Happy Coding! 🎉
