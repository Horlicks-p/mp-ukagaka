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

```
mp-ukagaka/
├── mp-ukagaka.php          # Main entry point
├── includes/               # PHP Modules
│   ├── core-functions.php      # Core functions
│   ├── utility-functions.php   # Utility functions
│   ├── ai-functions.php        # AI functions (Cloud API + Ollama)
│   ├── prompt-categories.php   # Prompt category instruction management
│   ├── llm-functions.php       # LLM functions (Ollama specific) - BETA
│   ├── ukagaka-functions.php   # Ukagaka management
│   ├── ajax-handlers.php       # AJAX handlers
│   ├── frontend-functions.php  # Frontend functions
│   └── admin-functions.php     # Admin functions
├── dialogs/                # Dialogue files
├── images/                 # Image resources
│   └── shell/                  # Character images
├── languages/              # Language files
├── docs/                   # Documentation
├── options/                # Admin settings pages
│   ├── options.php             # Admin page framework
│   ├── options_page0.php       # Basic settings page
│   ├── options_page1.php       # Ukagaka management page
│   ├── options_page2.php       # Dialogue settings page
│   ├── options_page3.php       # Display settings page
│   ├── options_page4.php       # Advanced settings page
│   ├── options_page_ai.php     # AI settings page
│   └── options_page_llm.php    # LLM settings page (BETA)
├── js/                     # Frontend JavaScript Modules
│   ├── ukagaka-base.js         # Base layer (Config + Utils + AJAX)
│   ├── ukagaka-core.js         # Frontend core JS (Message display, switching, etc.)
│   ├── ukagaka-features.js     # Frontend features JS (AI page awareness, greeting, etc.)
│   ├── ukagaka-anime.js        # Canvas Animation Manager (Image Sequence Playback)
│   ├── ukagaka-cookie.js       # Cookie utility (Visitor tracking)
│   └── ukagaka-textarearesizer.js  # Admin textarea resizer
├── mpu_style.css           # Frontend stylesheet
├── admin-style.css         # Admin stylesheet
└── readme.txt              # WordPress plugin directory readme
```

### Module Loading Order

The plugin uses conditional loading mechanisms to load modules based on the execution environment (Frontend/Admin):

```php
// Loading logic in mp-ukagaka.php

// Core modules: Required by both frontend and admin
$core_modules = [
    'core-functions.php',      // 1. Core functions (Settings)
    'utility-functions.php',   // 2. Utility functions
    'ai-functions.php',        // 3. AI functions (Cloud API: Gemini, OpenAI, Claude)
    'prompt-categories.php',   // 4. Prompt category management (Load before llm-functions.php)
    'llm-functions.php',       // 5. LLM functions (Local LLM: Ollama)
    'ukagaka-functions.php',   // 6. Ukagaka management
    'ajax-handlers.php',       // 7. AJAX handlers (Used by both)
];

// Frontend modules (Loaded only in non-admin environment)
$frontend_modules = [
    'frontend-functions.php',  // Frontend functions
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

| Constant | Description | Value |
|-----|------|-----|
| `MPU_VERSION` | Plugin Version | `"2.2.0"` |
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

/**
 * HTML decode
 * @param string $str Input string
 * @return string Decoded string
 */
function mpu_html_decode($str): string
```

#### Browser Detection

```php
/**
 * Detect browser type
 * @param string $target Target browser (e.g., 'ie', 'chrome')
 * @return bool Is target browser
 */
function mpu_is_browser($target = ""): bool
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

#### Main Functions

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
 * Check if AI should be triggered
 * @return bool Should trigger
 */
function mpu_should_trigger_ai(): bool

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
```

#### Supported AI Providers

| Provider | Function | API Endpoint | Model Selection |
|-------|------|---------|---------|
| Gemini | `mpu_call_gemini_api()` | `generativelanguage.googleapis.com` | Supported (gemini-2.5-flash, gemini-2.5-pro, etc.) |
| OpenAI | `mpu_call_openai_api()` | `api.openai.com` | Supported (gpt-4o-mini, gpt-4o, etc.) |
| Claude | `mpu_call_claude_api()` | `api.anthropic.com` | Supported (claude-sonnet-4-5-20250929, etc.) |
| Ollama | `mpu_call_ollama_api()` | Local or Remote Ollama Service | Supported (Any Ollama model) |

### llm-functions.php (BETA)

> ⚠️ **Note**: This module is in **BETA**. API may change.

LLM functions module, specifically handling Ollama local LLM integration.

#### Main Functions

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

| Operation Type | Local Connection | Remote Connection |
|---------|---------|---------|
| Service Check (`check`) | 3s | 10s |
| API Call (`api_call`) | 60s | 90s |
| Test Connection (`test`) | 30s | 45s |

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

### ukagaka-functions.php

Ukagaka management module, handling character operations and dialogue management.

#### Main Functions

```php
/**
 * Get Ukagaka list HTML
 * @return string HTML string
 */
function mpu_ukagaka_list(): string

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
 * Get default message
 * @param string|false $num Ukagaka key
 * @param bool $echo Whether to echo directly
 * @return string Message content
 */
function mpu_get_default_msg($num = false, $echo = false): string

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
 * Get next message
 * @param string|false $num Ukagaka key
 * @param int $msgnum Current message index
 * @return array Array containing message and index
 */
function mpu_get_next_msg($num = false, $msgnum = 0): array

/**
 * Process special codes in message
 * @param array $msglist Message array
 * @return array Processed message array
 */
function mpu_msg_code($msglist = []): array

/**
 * Get message key
 * @param string|false $num Ukagaka key
 * @param string $msg Message content
 * @return int|false Message index or false
 */
function mpu_get_msg_key($num = false, $msg = "")

/**
 * Count Ukagaka messages
 * @param string|false $num Ukagaka key
 * @return int Count
 */
function mpu_count_msg($num = false): int

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

#### Main Functions

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

#### Main Functions

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

#### Main Functions

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
    'openai_model' => 'gpt-4o-mini',    // OpenAI Model
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
    ajaxUrl: '/wp-admin/admin-ajax.php',
    nonce: 'xxx',
    autoTalk: true,
    autoTalkInterval: 8000,
    typewriterSpeed: 40,
    aiEnabled: true,
    aiTextColor: '#ff6b6b',
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

### AI Feature Functions (ukagaka-features.js)

```javascript
/**
 * Trigger AI page context
 */
function mpu_triggerAIContext()

/**
 * Trigger AI first visitor greeting
 */
function mpu_triggerAIGreeting()

/**
 * Pause auto talk
 * @param {number} duration - Pause duration (ms)
 */
function mpu_pauseAutoTalk(duration)

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
```

```

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

2. Add case in `mpu_call_ai_api()`:

```php
case 'newprovider':
    return mpu_call_newprovider_api($prompt, $system_prompt);
```

3. Add corresponding options in the admin settings page.

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

## Related Resources

- [WordPress Plugin Handbook](https://developer.wordpress.org/plugins/)
- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/)
- [Gemini API Docs](https://ai.google.dev/docs)
- [OpenAI API Docs](https://platform.openai.com/docs)
- [Claude API Docs](https://docs.anthropic.com/)

---

**Happy Coding! 🎉**
