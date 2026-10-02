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
│   │   ├── utility-functions.php   # Utility functions / constants
│   │   ├── template-functions.php  # Template loading and string processing
│   │   ├── file-functions.php      # Secure file operations and directory helpers
│   │   ├── encryption-functions.php# API key encryption / decryption helpers
│   │   ├── wp-info-functions.php   # WordPress info extractor
│   │   ├── network-functions.php   # Network request helpers
│   │   ├── runtime-state-functions.php # Runtime-scoped state helpers
│   │   ├── class-mpu-input-role.php # LLM / tool input role resolver
│   │   ├── class-mpu-observation-buffer.php # Session-scoped visitor activity buffer
│   │   ├── class-mpu-log-i18n-builder.php # Frontend console log i18n payload builder
│   │   ├── ukagaka-functions.php   # Ukagaka management
│   │   └── frontend-functions.php  # Frontend functions
│   ├── chat/                   # Chat flow services
│   │   └── class-mpu-chat-history-service.php # Chat history service (verify/store/slice)
│   ├── rest/                   # REST API handling modules (OO Architecture)
│   │   ├── bootstrap.php           # REST Controller registration entry
│   │   ├── class-mpu-rest-base.php # Base class
│   │   ├── class-mpu-rest-chat.php # LLM chat endpoints
│   │   ├── class-mpu-rest-ghost.php# Core/Personality endpoints
│   │   ├── class-mpu-rest-dialog.php# Dialog management endpoints
│   │   ├── class-mpu-rest-touch.php# Touch interaction endpoints
│   │   ├── class-mpu-rest-test.php # API test endpoints
│   │   ├── class-mpu-rest-observation.php # Observation push endpoint
│   │   └── class-mpu-rest-memory.php # User memory endpoints
│   ├── ajax/                   # AJAX handler modules
│   │   └── chat-api-handlers.php   # Chat API handlers (Multi-turn encapsulation)
│   ├── personality/            # Personality system modules
│   │   ├── personality-loader.php  # Personality system (JSON loader)
│   │   ├── personality-prompts.php # Personality prompts module
│   │   ├── personality-decorations.php # Decoration system
│   │   ├── personality-items.php   # Gift / feeding item catalog
│   │   ├── personality-emoji.php   # Emoji system
│   │   └── emoji-mapper.php        # Emoji mapping and emotion analysis
│   ├── llm/                    # LLM/AI functions modules
│   │   ├── api-cache.php           # API cache system
│   │   ├── ai-functions.php        # AI functions (Cloud APIs: Gemini, OpenAI, Claude)
│   │   ├── response-normalizer.php # AI response normalization (emotion tags / think blocks)
│   │   ├── class-mpu-stream-output-parser.php # SSE stream parser
│   │   ├── llm-functions.php       # LLM functions (Ollama specifically)
│   │   ├── llm-context-builder.php # LLM context builder
│   │   ├── llm-slimstat.php        # LLM Slimstat integration
│   │   ├── prompt-categories.php   # Prompt category instruction management
│   │   ├── chat-integrity.php      # Chat history checksum validation
│   │   ├── class-mpu-chat-lock.php # Chat lifecycle lock (concurrent LLM request guard)
│   │   ├── request-state.php       # Request-level state management
│   │   ├── class-mpu-session-event.php # Transport-neutral session event envelope
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
│   │       ├── class-wp-postviews-ability.php   # Post views ability
│   │       ├── class-ai-crawler-ability.php     # AI crawler signal ability
│   │       └── class-visitor-pulse-ability.php  # Visitor pulse statistics ability
│   ├── integrations/           # Integration modules
│   │   ├── abilities-integration.php   # Abilities API integration
│   │   ├── akismet-integration.php     # Akismet anti-spam integration
│   │   ├── bot-blocker-integration.php # Bot blocker integration
│   │   └── turnstile-integration.php   # Turnstile verification integration
│   ├── updater/                # Auto-update module
│   │   └── github-updater.php      # GitHub-based plugin update checker
│   └── admin-functions.php     # Admin functions
├── ghost/                  # Character personality configurations
│   ├── Frieren/
│   │   ├── shell/              # Character images
│   │   ├── decorations/        # Decoration images
│   │   ├── emojis/             # Character emoji images
│   │   ├── items/              # Gift / feeding item images
│   │   ├── dist/               # Bundled character runtime
│   │   │   ├── frieren-bundle.js
│   │   │   └── frieren-bundle.min.js
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
│   │   ├── items.json          # Gift / feeding item catalog
│   │   ├── diary.json          # AI diary configuration
│   │   ├── emoji-keywords.json # Emoji keywords configuration
│   │   ├── frieren.js          # Character runtime entry
│   │   ├── frieren-animation.js    # Character animation runtime
│   │   ├── frieren-interactions.js # Touch / gift interaction runtime
│   │   ├── frieren-decorations.js  # Decoration click runtime
│   │   └── frieren-emoji.js    # Frieren-specific emoji system
│   └── [Other Characters...]/
│       ├── shell/              # Character images
│       └── decorations/        # Decoration images (optional)
├── dialogs/                # Dialog files
├── images/                 # Common image resources
├── languages/              # Translation files
├── docs-en/                # Canonical English documentation
├── plan/                   # Development planning notes
├── tests/                  # PHPUnit test suite
├── tools/                  # Node/PHP build, lint, and test tooling
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
│   ├── ukagaka-chat-mode.js    # Chat UI open/close and mode state
│   ├── ukagaka-chat-history.js # Frontend chat history/session state
│   ├── ukagaka-chat-send.js    # Chat send pipeline
│   ├── ukagaka-chat-sse.js     # SSE streaming client
│   ├── ukagaka-chat-format.js  # Chat text formatting helpers
│   ├── ukagaka-chat-events.js  # Chat event bindings
│   ├── ukagaka-chat-wake.js    # Sleep/wake chat integration
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
    'core/utility-functions.php',   // 2. Utility functions / constants
    'core/template-functions.php',  // 3. Template loading and string processing
    'core/file-functions.php',      // 4. Secure file operations and directory helpers
    'core/encryption-functions.php',// 5. API key encryption / decryption helpers
    'core/wp-info-functions.php',   // 6. WordPress info extractor
    'core/network-functions.php',   // 7. Network request helpers
    'core/runtime-state-functions.php', // 8. Runtime-scoped state helpers
    'core/class-mpu-input-role.php', // 9. LLM / tool input role resolver
    'core/class-mpu-observation-buffer.php', // 10. Session-scoped visitor activity buffer
    'core/class-mpu-log-i18n-builder.php', // 11. Frontend console log i18n payload builder
    'personality/personality-loader.php',  // 12. Personality system (JSON loader, must be before other personality modules)
    'personality/personality-prompts.php', // 13. Personality prompts module (Dynamic prompts, variable replacement)
    'personality/personality-decorations.php', // 14. Decoration system
    'personality/personality-items.php', // 15. Gift / feeding item catalog
    'personality/personality-emoji.php',   // 16. Emoji system
    'stats/stats-collector.php',   // 17. Stats collector (must be before ai-functions.php)
    'stats/stats-analyzer.php',    // 18. Stats analyzer
    'llm/api-cache.php',           // 19. API cache system
    'llm/provider-helpers.php',    // 20. Provider common helpers (JSON encoding / tool result formatting)
    'llm/chat-integrity.php',      // 21. Chat history integrity checksum (prevents frontend tampering)
    'llm/class-mpu-chat-lock.php', // 22. Chat lifecycle lock (concurrent LLM request guard)
    'llm/request-state.php',       // 23. Request-level state management
    'llm/class-mpu-session-event.php', // 24. Transport-neutral session event envelope
    'llm/tool-loop-guard.php',     // 25. Tool call loop protection mechanism
    'llm/streaming-helpers.php',   // 26. SSE streaming helper functions
    'llm/provider-stream-http.php', // 27. cURL streaming HTTP client
    'llm/providers/bootstrap.php', // 28. AI provider factory + classes
    'llm/ai-functions.php',        // 29. AI functions (Cloud APIs: Gemini, OpenAI, Claude)
    'llm/response-normalizer.php', // 30. AI response normalization contract
    'llm/class-mpu-stream-output-parser.php', // 31. SSE output parser
    'llm/prompt-categories.php',   // 32. Prompt category instruction management
    'llm/llm-slimstat.php',        // 33. LLM Slimstat integration
    'llm/llm-context-builder.php', // 34. LLM context builder
    'llm/weather-functions.php',   // 35. Weather functions (Open-Meteo API)
    'llm/diary-functions.php',     // 36. AI diary functions (Frieren's journal)
    'llm/llm-functions.php',       // 37. LLM functions (Local LLM: Ollama)
    'personality/emoji-mapper.php', // 38. Emoji mapping and emotion analysis
    'core/ukagaka-functions.php',   // 39. Ukagaka management
    'rest/bootstrap.php',           // 40. REST OO Controller registration entry
    'ajax/chat-api-handlers.php',   // 41. Chat mode API handlers (Multi-turn)
    'integrations/akismet-integration.php', // 42. Akismet anti-spam integration
    'integrations/turnstile-integration.php', // 43. Turnstile verification integration
    'integrations/abilities-integration.php', // 44. Abilities API integration
    'integrations/bot-blocker-integration.php', // 45. Bot Blocker integration
];

// Frontend-only modules (loaded only in non-admin environments)
$frontend_modules = [
    'core/frontend-functions.php',  // Frontend functions
];

// Admin-only modules (loaded only in admin environments)
$admin_modules = [
    'admin-functions.php',          // Admin functions
    'updater/github-updater.php',   // GitHub auto-update (Plugin Update Checker)
];
```

**Loading Timing:**

- All core modules are loaded on the `plugins_loaded` action (priority 1)
- Frontend modules are loaded only when `!is_admin()`
- Admin modules are loaded only when `is_admin()`

### Constants Definition

| Constant | Defined in | Description | Value |
| -------- | ---------- | ----------- | ----- |
| `MPU_VERSION` | `mp-ukagaka.php` | Plugin version (set by `tools/node/bump-version.js`) | `'X.Y.Z'` |
| `MPU_MAIN_FILE` | `mp-ukagaka.php` | Main plugin file path | `__FILE__` |
| `MPU_MAX_TOOL_TURNS` | `core/utility-functions.php` | Max tool-call turns per AI request | `5` |
| `MPU_MAX_TOOL_REPEAT_SAME_CALL` | `core/utility-functions.php` | Identical tool calls before the loop guard stops | `2` |
| `MPU_CACHE_DEFAULT` | `core/utility-functions.php` | Default API cache TTL | `HOUR_IN_SECONDS` |
| `MPU_CACHE_WEATHER` | `core/utility-functions.php` | Weather cache TTL | `30 * MINUTE_IN_SECONDS` |
| `MPU_CACHE_EXCHANGE` | `core/utility-functions.php` | Exchange-rate cache TTL | `DAY_IN_SECONDS` |
| `MPU_ITEM_MESSAGE_MAX_LENGTH` | `personality/personality-items.php` | Max length of a message sent with a gift | `500` |

Constants you can define in `wp-config.php`: `MPU_DEBUG_LLM` (log full LLM prompts, see `mpu_debug_llm_prompts`) and `MPU_REST_BOOTSTRAP_DEBUG` (log controller registration when `WP_DEBUG` is on). The utility constants above are wrapped in `if (!defined(...))`, so they can be overridden there too.

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
 * Encrypts API Key (AES-256-GCM / mpu_enc2)
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
 * @param int|null $max_tokens Max output tokens (Optional; each provider's default when null)
 * @return string|WP_Error AI response or error
 */
function mpu_call_ai_api($provider, $api_key, $system_prompt, $user_prompt, $language, $mpu_opt = null, $max_tokens = null)

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
| Gemini | `gemini` | `generativelanguage.googleapis.com` | Supported (default `gemini-2.5-flash`) |
| OpenAI | `openai` | `api.openai.com` | Supported (default `gpt-4.1-mini-2025-04-14`) |
| Claude | `claude` | `api.anthropic.com` | Supported (default `claude-sonnet-4-6`) |
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
 * @param string $last_response Previous AI reply (used to avoid repeating it)
 * @param array $response_history Recent replies (stricter repeat detection)
 * @param int $last_visit_hours Hours since the visitor's last visit (-1 = first visit)
 * @return string|false Generated dialog content, false on failure
 */
function mpu_generate_llm_dialogue($ukagaka_name = 'default_1', $last_response = '', $response_history = [], $last_visit_hours = -1)

/**
 * Checks if LLM replace built-in dialog is enabled
 * @return bool
 */
function mpu_is_llm_replace_dialogue_enabled()
```

#### Timeout Settings

| Operation Type | Local Connection | Remote Connection |
| -------------- | ---------------- | ----------------- |
| Service Check (`check`) | 15s | 15s |
| API Call (`api_call`, also the default) | 90s | 120s |
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

### diary-functions.php (v2.5.0)

AI diary function module, responsible for automatically generating and publishing character diaries.

#### diary-functions.php Main Functions

```php
/**
 * Gets the diary title prefix
 * (dynamics.json `diary_title_prefix` → name from manifest.json → generic default)
 * @param string|null $personality_id Personality ID (null = current personality)
 * @param bool $with_space Append a trailing space (default true, used for slug generation)
 * @return string Prefix (e.g., "[Frieren's Journal] ")
 */
function mpu_get_diary_title_prefix($personality_id = null, $with_space = true)

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

Introduced in v2.9.2. Endpoints are registered by controllers under `includes/rest/`, with `rest/bootstrap.php` as the entry point. All controllers extend `MPU_REST_Base`; the namespace is `mp-ukagaka/v1`.

| File | Class | Routes |
| ---- | ----- | ------ |
| `class-mpu-rest-base.php` | `MPU_REST_Base` | — (namespace, permission, rate-limit, session-token and response helpers) |
| `class-mpu-rest-chat.php` | `MPU_REST_Chat` | `/chat/context`, `/chat/greet`, `/chat/user`, `/chat/user-stream`, `/session-token` |
| `class-mpu-rest-ghost.php` | `MPU_REST_Ghost` | `/init`, `/settings`, `/change`, `/extend`, `/shell-info`, `/decoration-config`, `/emoji-config` |
| `class-mpu-rest-dialog.php` | `MPU_REST_Dialog` | `/nextmsg`, `/dialog`, `/visitor-info`, `/decoration-prompts`, `/wake-ghost` |
| `class-mpu-rest-touch.php` | `MPU_REST_Touch` | `/touch/decoration`, `/touch/zone`, `/touch/give` |
| `class-mpu-rest-memory.php` | `MPU_REST_Memory` | `/memory/extract` |
| `class-mpu-rest-observation.php` | `MPU_REST_Observation` | `/observation/push` |
| `class-mpu-rest-test.php` | `MPU_REST_Test` | `/test-connection/{provider}`, `/clear-cache` |

One route predates this structure and is still registered procedurally, outside this table: `/check-spam-event` in `includes/integrations/akismet-integration.php` (`mpu_register_akismet_rest_routes()`). Keep it in mind when changing the REST layer; new endpoints must go through a controller.

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

The Ollama provider treats a model as a thinking model when its name contains `qwen3`, `deepseek` or `frieren`. For those models it sends `think: true` with `num_ctx: 8192`, or `think: false` with `num_ctx: 4096` when the `ollama_disable_thinking` option is checked. Other models get neither field.

#### Response Length Limit

Most paths read `settings.max_tokens` from the personality's `manifest.json` first. When it is absent, the fallback depends on the path (the wake-up reaction from `/wake-ghost` is the exception: it always uses a fixed `120`):

| Path | Fallback |
| ---- | -------- |
| Interactive chat (`/chat/user`, `/chat/user-stream`) and page-aware (`/chat/context`) | The **Max Output Tokens** setting, `ai_max_tokens` (default `1000`, clamped to 100–8192 on save) |
| LLM auto talk, first-visit greeting, touch / decoration / gift reactions | `800`, via `mpu_get_personality_max_tokens()` (minimum `50`) |

The value is passed to the provider as `max_tokens` and mapped to its own field (`num_predict` for Ollama, `maxOutputTokens` for Gemini, `max_tokens` for OpenAI and Claude). Connection tests use small fixed budgets of their own. The displayed reply is then cut to `settings.max_response_length` characters (default `500`, minimum `20`) by `mpu_get_personality_max_response_length()`.

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

All settings live in the single `mp_ukagaka` option. Defaults come from `mpu_default_opt()` (`includes/core/core-functions.php`); the admin save handlers in `includes/admin-functions.php` clamp and sanitize each value. Keys without a default are absent until their settings page is saved for the first time.

```php
$mpu_opt = [
    // General
    'cur_ukagaka' => 'default_1',       // Current ukagaka key
    'current_personality' => '',       // Personality folder under ghost/ (set from the General page)
    'show_ukagaka' => true,             // Show the character
    'show_msg' => true,                 // Show the message box
    'default_msg' => 0,                 // 0 = random, 1 = first message
    'next_msg' => 0,                    // 0 = sequential, 1 = random
    'click_ukagaka' => 0,               // 0 = next message, 1 = no action
    'insert_html' => 0,                 // HTML insert position
    'no_style' => false,                // Skip the bundled stylesheet
    'custom_style_link' => '',          // <link> tag for a custom stylesheet
    'no_page' => '',                    // Excluded pages, one per line
    'admin_nickname' => '',             // {{admin_nickname}}
    'admin_name' => '',                 // {{admin_name}}
    'admin_birthday' => '',             // Used by calendar events

    // Auto talk
    'auto_talk' => true,
    'auto_talk_interval' => 8,          // Seconds, 3–30
    'typewriter_speed' => 40,           // ms per character, 10–200

    // Dialog files
    'use_external_file' => true,        // Always forced to true
    'external_file_format' => 'txt',    // txt | json
    'auto_msg' => '',                   // Fixed message
    'common_msg' => '',                 // Common dialog shared by all ukagakas

    // AI settings page (page-aware comments, greeting)
    'ai_language' => '',                // '' = follow the personality
    'ai_system_prompt' => '...',        // Fallback system prompt
    'ai_probability' => 10,             // 1–100
    'ai_max_tokens' => 1000,            // 100–8192; manifest settings.max_tokens overrides
    'ai_trigger_pages' => 'is_single',  // Comma-separated conditional tags
    'ai_text_color' => '#000000',
    'ai_display_duration' => 8,         // Seconds, 1–60
    'ai_greet_first_visit' => false,    // First-time visitor greeting
    'ai_greet_prompt' => '...',
    'chat_integrity_mode' => 'audit',   // audit | warn | block (no UI; see mpu_chat_integrity_mode filter)

    // LLM settings page (provider shared by all AI features except the diary)
    'ai_enabled' => false,
    'llm_provider' => 'gemini',         // gemini | openai | claude | ollama
    'llm_gemini_api_key' => '',         // Encrypted
    'llm_gemini_model' => 'gemini-2.5-flash',
    'llm_openai_api_key' => '',         // Encrypted
    'llm_openai_model' => 'gpt-4.1-mini-2025-04-14',
    'llm_claude_api_key' => '',         // Encrypted
    'llm_claude_model' => 'claude-sonnet-4-6',
    'ollama_endpoint' => 'http://localhost:11434', // Read-time fallback when unset
    'ollama_model' => 'qwen3:8b',       // Read-time fallback when unset
    'ollama_disable_thinking' => false, // true = send think:false to thinking models
    'llm_replace_dialogue' => false,    // Replace built-in dialog with LLM output
    'enable_chat_mode' => false,        // Interactive chat mode
    'weather_enabled' => false,
    'weather_latitude' => 25.0330,
    'weather_longitude' => 121.5654,
    'api_cache_enabled' => false,
    'api_cache_ttl' => 3600,            // Seconds, 60–604800

    // Diary page (separate provider and keys)
    'diary_enabled' => false,
    'diary_category' => 0,
    'diary_author' => 0,                // Defaults to the saving user
    'diary_trigger_rate' => 2,          // 1–10
    'diary_signature' => '',
    'diary_provider' => 'gemini',       // gemini | openai | claude | ollama
    'diary_gemini_api_key' => '', 'diary_gemini_model' => '',
    'diary_openai_api_key' => '', 'diary_openai_model' => '',
    'diary_claude_api_key' => '', 'diary_claude_model' => '',
    'diary_ollama_endpoint' => '', 'diary_ollama_model' => '',

    // Bot blocker page
    'bot_blocker' => [
        'enabled' => false,
        'banned_fingerprints' => [...],
        'suspicious_resolutions' => [...],
        'max_log_rows' => 1000,
        'auto_ban_ip' => true,
        'block_status' => 403,
        'hot_transient_ttl' => 600,
        'rate_limit_threshold' => 40,
    ],

    // Extensions
    'extend' => [
        'js_area' => '',                // Custom JavaScript (requires unfiltered_html to save)
    ],

    // Ukagaka list
    'ukagakas' => [
        'default_1' => [
            'name' => 'フリーレン',
            'shell' => '<plugin URL>/ghost/Frieren/shell/Frieren/', // Image URL or folder URL
            'msg' => ['...'],
            'dialog_filename' => 'Frieren',
            'show' => true,
            'show_decorations' => true,
        ],
        // ... more ukagakas
    ],
];
```

**Legacy keys.** Versions before the `llm_*` split stored `ai_provider`, `ai_api_key`, `gemini_model`, `openai_api_key`, `openai_model`, `claude_api_key`, `claude_model` and `ollama_replace_dialogue`. `mpu_normalize_llm_option_keys()` copies them into the `llm_*` keys when those are empty and removes them on the next save. A few read paths still fall back to `ai_provider`, so do not reuse those names for anything else.

### Ukagaka Structure

```php
$ukagaka = [
    'name' => 'Frieren',              // Display name
    'shell' => 'https://.../shell/',  // Single image URL, or a folder URL whose images are played as frames
    'msg' => [                        // Dialog array
        'Dialog 1',
        'Dialog 2',
    ],
    'show' => true,                   // Can be shown
    'dialog_filename' => 'Frieren',   // Dialog file name under dialogs/ (without extension)
    'show_decorations' => true,       // Show personality decorations
];
```

---

## Hooks and Filters

Since the REST refactoring in `v2.9.2`, the legacy plugin-level hooks (`mpu_loaded`, `mpu_before_html`, `mpu_after_html`, `mpu_settings_saved`, `mpu_options`, `mpu_messages`, `mpu_ai_response`, `mpu_ukagaka_html`) no longer exist. The hooks below are the current set; signatures and examples are in [API Reference → WordPress Hooks](API_REFERENCE.md#wordpress-hooks).

| Hook | Type | Purpose |
| ---- | ---- | ------- |
| `mpu_llm_system_prompt` | filter | System prompt of LLM auto talk (`/nextmsg` → `mpu_generate_llm_dialogue()`) only |
| `mpu_llm_user_prompt` | filter | User prompt of the same path |
| `mpu_prompt_categories` | filter | Auto-talk category definitions |
| `mpu_category_weights` | filter | Auto-talk category weights |
| `mpu_mcp_tools_for_llm` | filter | Tool (ability) definitions offered to the LLM |
| `mpu_chat_integrity_mode` | filter | Checksum enforcement: `audit` / `warn` / `block` |
| `mpu_chat_lock_ttl` | filter | Chat lifecycle lock TTL |
| `mpu_runtime_state_ttl` | filter | Runtime-state transient TTL |
| `mpu_observation_buffer_ttl` | filter | Observation buffer TTL |
| `mpu_observation_post_visibility` | filter | Whether a post title may enter prompt context |
| `mpu_frontend_debug_mode` | filter | Frontend debug logging |
| `mpu_debug_llm_prompts` | filter | Full prompt logging |
| `mpu_debug_llm_prompt_message_limit` | filter | Messages included in prompt logs |
| `mpu_chat_integrity_mismatch` | action | Checksum mismatch detected |
| `mpu_chat_lock_acquired` / `_conflict` / `_released` | action | Chat lock lifecycle |

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
| `/session-token` | GET | Issues the IP-bound session token anonymous visitors send as `X-MPU-Session-Token` (empty for logged-in users) |
| `/memory/extract` | POST | Admin only: extracts owner memory from recent chat history (`/remember`) |
| `/observation/push` | POST | Buffers visitor activity observations for the session |

### Touch Interaction

| Endpoint | Method | Description |
| --- | --- | --- |
| `/touch/decoration` | POST | AI reaction when clicking on a decoration |
| `/touch/zone` | POST | Interaction reaction when clicking on a character zone |
| `/touch/give` | POST | Gift / feeding reaction for an item from `items.json` |

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
function mpu_hidemsg(speed = 400)
function mpu_showmsg(speed = 400)
function mpu_hiderobot(speed = 400)
function mpu_showrobot(speed = 400)
function mpuChange(num)
```

### Message Blocking

Several flows need to stop auto talk and the OK button from replacing what is on screen: leaving chat, page-aware comments, rate-limit cooldowns, touch / decoration dialogs and gift reactions. Since v2.33.2 each flow holds the block under its own owner name, so one flow can no longer release a block another flow still needs.

```javascript
mpuAcquireMessageBlock(owner);   // add an owner; re-acquiring the same owner is a no-op
mpuReleaseMessageBlock(owner);   // remove only that owner
mpuHasMessageBlock(owner);       // is this owner holding the block?
mpuMessageBlocking;              // true while any owner remains (read-only for callers)
MPU_STATE.llm.messageBlockOwners // snapshot array of the current owners
mpuSetMessageBlocking(bool);     // compatibility setter for extensions, uses the "legacy" owner
```

Built-in owners: `chat-exit`, `page-context`, `rate-limit-next-message`, `rate-limit-greeting`, `frieren-interaction`, `frieren-gift`. Extensions should acquire under a name of their own rather than calling `mpuSetMessageBlocking()`. `mpuIsInteractionDialogActive()` reports whether a touch / decoration dialog is open, read from the ghost manager that owns that state.

### Chat History Ordering

`window.mpuChatGeneration` increments on every chat open / close. A request remembers the generation it was sent in; a reply that arrives after chat was toggled is not shown but is still stored. `mpu_insertChatReply(userEntry, assistantEntry)` places such a reply directly after the user turn it answers, and `mpu_removeChatHistoryEntry(userEntry)` rolls back a failed turn by reference (falling back to a content match after history is reloaded from storage). Auto-talk replies that arrive while chat is open are recorded through `mpu_recordLlmAutoTalk()` as `auto_talk` entries without being displayed.

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
  init: function(shellInfo, name, num),
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
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'handle_custom'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function handle_custom(WP_REST_Request $request) {
        // Per-IP rate limit: 10 requests / 60 s
        $rl = $this->rate_limit('custom', 10, 60);
        if ($rl !== null) return $rl;

        // Anonymous visitors must send X-MPU-Session-Token for AI-backed routes
        $denied = $this->require_session_token($request);
        if ($denied !== null) return $denied;

        return $this->ok(['msg' => 'ok']);
        // Errors: return $this->fail('code', __('Message', 'mp-ukagaka'), 400);
    }
}
```

Then add the class to `$mpu_oo_rest_controllers` in `includes/rest/bootstrap.php` (`'MPU_REST_Custom' => 'class-mpu-rest-custom.php'`). The bootstrap only instantiates classes that extend `MPU_REST_Base`, and hooks `register_routes()` to `rest_api_init`. Admin-only routes use `'permission_callback' => [$this, 'check_admin']`.

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
   - Minor modifications: the split chat modules (`js/ukagaka-chat-*.js`) and `js/ukagaka-core.js` (a few references).
   - Estimated workload: ~2-3 hours (including testing).

4. **Trigger Timing**
   - Can be implemented together when a second character requires exclusive animations or interactions.
   - Alternatively, refactor when Frieren's exclusive features need to be abstracted.

**Technical Key Points:**

- Current character info must be retrieved from `dialog_filename` or personality ID.
- Backward compatibility must be maintained to ensure existing Frieren features work normally.
- You can refer to `mpuFrierenManager` in `ghost/Frieren/frieren.js` as an implementation example.

### Inner Monologue (`<think>`) Channel — Abandoned by Default, Opt-In for Developers

> **Status:** The LLM `<think>` inner-monologue channel (rendering character "inner thoughts" into the think bubble) is **abandoned and unmaintained by the project** as of v2.25.0. The original maintainer could not get a usable result on a local Ollama setup, but *the entire pipeline ships intact* and is inert only because nothing currently feeds it. If your provider/model can produce good short inner monologue, you can opt in by feeding `<think>` text into the existing pipeline. This is documented as a developer extension, not a supported feature.

**What already works (no changes needed):**

1. `mpu_normalize_ai_response()` (`includes/llm/response-normalizer.php`) extracts the **leading** `<think>...</think>` block from any AI response into a `think` field, and keeps it out of `display_text` / `history_text` / `checksum_text` / `tts_text`. (Mid-message `<think>` is stripped to a warning log, never rendered.)
2. The SSE state machine `MPU_Stream_Output_Parser` (`includes/llm/class-mpu-stream-output-parser.php`) detects `<think>` across chunk boundaries and emits normalized events: `status {type:"thinking_start"|"thinking_end"}`, `think_delta {text}` (progressive), and `think {text}` (final).
3. The frontend chat modules (`js/ukagaka-chat-sse.js`, `js/ukagaka-chat-send.js`, and related `js/ukagaka-chat-*.js` files) already consume all of those events — and the non-streaming `res.think` field — and render them via `mpuShowThinkBubble(text, { source: "llm", context })` into `#ukagaka_think`. Streaming, progressive, and non-streaming paths are all wired.

**The one missing link:** a provider must actually emit `<think>...</think>` *in its text output*. Cloud reasoning models (Claude/OpenAI/Gemini) put reasoning in **separate API fields that never enter this pipeline**, so emitting `<think>` has to be requested explicitly. Two ways:

**Option A — Prompt instruction (cross-provider, lowest code).** Add an instruction to the active character prompt (`instructions.md`, backend System Prompt, or `manifest.json` prompt source) that asks the model to wrap a short optional inner monologue in `<think>`. For example:

```markdown
## Inner Monologue
Optionally begin your reply with one short <think>...</think> block
(a private thought or stage direction, max ~30 chars). It is shown in a
separate bubble, never spoken, and must appear before any other text.
Open and close the tag as a pair, or omit it entirely.
```

That is enough for the normalizer, SSE parser, and bubble to do the rest on contexts where the gate is enabled. Be aware that this is a prompt-level opt-in, not a runtime per-context hook; if the same prompt is reused by disabled contexts, they may still spend tokens producing `<think>` that gets stripped.

Do **not** use `mpu_llm_system_prompt` as the modern REST wiring point. In current core it only applies to LLM auto talk (`/nextmsg` → `mpu_generate_llm_dialogue()`), not the REST chat / touch / page-aware paths that call `mpu_resolve_system_prompt()`. Its 4th argument is also an information array (`wp_info`, `user_info`, `visitor_info`, `time_context`, `language`), not a request context string. If you need runtime context-aware injection, add a dedicated filter around the `mpu_resolve_system_prompt()` call path or perform the mapping inside your provider integration.

**Option B — Map a provider's native reasoning field into `<think>`.** For Ollama / reasoning models that expose a separate `thinking` field, wrap it as `<think>{thinking}</think>` and prepend it to the content inside the provider class (this is what the reverted commit `a0e257f` did — see the pitfalls before reusing it).

**Gates that must allow it (already present):**

| Gate | Location | Default |
|---|---|---|
| `enable_inner_monologue` option | read by `mpu_is_inner_monologue_enabled()` | `true` (global on) |
| Per-context policy | `mpu_is_inner_monologue_enabled_for_context($context, $personality_id)` | `chat` / `touch` / `page_aware` = on; `decoration` / `initial` / `diary` = off; unknown = off |
| Per-personality override | `manifest.json` → `features.inner_monologue_contexts[$context]` | overrides the per-context default |

If the gate is off for a context, the SSE parser strips `<think>` and does **not** emit think events, and the normalizer returns an empty `think` — so injecting the prompt alone is not enough; the context must be enabled too.

**Known pitfalls (why it was abandoned — fix these before relying on it):**

- **Shared token budget.** Ollama's `num_predict` (and any single-output-budget model) is shared between reasoning and the final reply. A long `<think>` can eat the budget, truncating or emptying the actual answer, which then desyncs chat history and trips checksum mismatches (`logs/checksum-mismatch.log`). Budget reasoning and reply **separately** before enabling on such models.
- **No overflow guard on the bubble.** `.mpu-think-bubble` has no `max-height` / `overflow`; a long reasoning dump overflows the UI. Add `max-height` + `overflow: auto` (an earlier overflow fix was discarded with the reverted channel).
- **Quality.** Reasoning-model "thinking" tends to be long, mechanical, and off-character. Keep the instruction tight (length cap, single block) and test per model.

---

## Security Considerations

### API Key Security

- All API Keys are stored encrypted using AES-256-GCM (`mpu_enc2:`).
- WordPress `AUTH_KEY` is required as the encryption key; encryption is refused instead of falling back to a public site-derived key.
- Legacy `mpu_enc:` (AES-256-CBC) and `mpu_obf:` values remain readable for existing settings.
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

Frontend console logs also use i18n. Production-visible logs must be registered on the PHP side with `mpu_console_log_i18n_builder()->always()` into `mpuL10n.logs`, while JS call sites use `mpuLogger.errorL/errorF` or `mpuLogger.warnAlways/warnAlwaysF`. Do not convert an always-output `console.warn` into debug-gated `warnL`. During migration, console fallbacks are gradually changing to Japanese source strings, with displayed output translated by the active WordPress locale.

### Testing

Install the Node tooling once with `npm --prefix tools/node ci`; PHP tooling lives in `tools/php/` (`composer install` there provides PHPUnit and PHPCS).

| Command | What it checks |
| ------- | -------------- |
| `npm --prefix tools/node run verify` | The release gate: version markers, `php -l`, PHPCS against its baseline, stylelint, the Node smoke tests (`tools/node/test-*-smoke.js`), a bundle rebuild, and PHPUnit. Must be green before a release. |
| `tools/php/vendor/bin/phpunit -c tests/phpunit.xml.dist` | PHPUnit suite in `tests/Unit/` on its own. |
| `npm --prefix tools/node run test:interaction` | End-to-end interaction suite (`tools/e2e/`). Boots a disposable WordPress Playground with this checkout mounted, points the plugin at a scripted fake Ollama, and drives the real bundle in Edge. Covers handoffs between auto talk, chat, page-aware, touch and gifts. Not part of `verify`; pass `-- --only=<name>` to run one scenario or `-- --headed` to watch. |
| `npm --prefix tools/node run visual:baseline` / `visual:compare` | Pixel comparison of the frontend CSS against a captured baseline. |
| `npm --prefix tools/node run build` | Rebuilds `js/dist/` and the Frieren bundle after JS changes. |

Beyond these, check the browser console with debug mode on (`WP_DEBUG` as an administrator, see `mpu_frontend_debug_mode`), `logs/checksum-mismatch.log` for chat integrity issues, and the admin **Test Connection** buttons for provider connectivity. `docs-en/REST_SMOKE_TEST.md` has a `curl` checklist for a live site.

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
