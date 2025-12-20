# MP Ukagaka API Reference

> 📚 Complete reference for functions, Hooks, and AJAX endpoints

---

## 📑 Table of Contents

1. [PHP Functions](#php-functions)
2. [WordPress Hooks](#wordpress-hooks)
3. [AJAX Endpoints](#ajax-endpoints)
4. [JavaScript Functions](#javascript-functions)
5. [Special Codes](#special-codes)

---

## PHP Functions

### Core Functions (core-functions.php)

#### mpu_default_opt()

Get default settings.

```php
/**
 * @return array Default settings array
 */
function mpu_default_opt(): array
```

**Example:**

```php
$defaults = mpu_default_opt();
echo $defaults['auto_talk_interval']; // 8
```

---

#### mpu_get_option()

Get plugin options (with cache).

```php
/**
 * @return array Options array
 */
function mpu_get_option(): array
```

**Example:**

```php
$mpu_opt = mpu_get_option();
if ($mpu_opt['ai_enabled']) {
    // AI Enabled
}
```

---

#### mpu_count_total_msg()

Count total messages of all Ukagakas.

```php
/**
 * @return int Total messages count
 */
function mpu_count_total_msg(): int
```

---

### Utility Functions (utility-functions.php)

#### mpu_array2str()

Convert array to string (separated by newlines).

```php
/**
 * @param array $arr Input array
 * @return string Output string
 */
function mpu_array2str(array $arr): string
```

**Example:**

```php
$messages = ['Dialog 1', 'Dialog 2', 'Dialog 3'];
$str = mpu_array2str($messages);
// Result:
// Dialog 1
//
// Dialog 2
//
// Dialog 3
```

---

#### mpu_str2array()

Convert string to array (separated by empty lines).

```php
/**
 * @param string $str Input string
 * @return array Output array
 */
function mpu_str2array(string $str): array
```

**Example:**

```php
$str = "Dialog 1\n\nDialog 2\n\nDialog 3";
$messages = mpu_str2array($str);
// Result: ['Dialog 1', 'Dialog 2', 'Dialog 3']
```

---

#### mpu_output_filter()

HTML output filter.

```php
/**
 * @param string $str Input string
 * @return string Filtered string
 */
function mpu_output_filter(string $str): string
```

---

#### mpu_js_filter()

JavaScript output filter (escapes quotes and special characters).

```php
/**
 * @param string $str Input string
 * @return string Filtered string
 */
function mpu_js_filter(string $str): string
```

---

#### mpu_input_filter()

Input filter (Processing before saving).

```php
/**
 * @param string $str Input string
 * @return string Filtered string
 */
function mpu_input_filter(string $str): string
```

---

#### mpu_secure_file_read()

Secure file read.

```php
/**
 * @param string $file_path File path
 * @param int $max_size Max file size (default 2MB)
 * @return string|WP_Error File content or error
 */
function mpu_secure_file_read(string $file_path, int $max_size = 2097152)
```

**Example:**

```php
$content = mpu_secure_file_read('/path/to/file.txt');
if (is_wp_error($content)) {
    echo $content->get_error_message();
} else {
    echo $content;
}
```

**Possible Errors:**

| Error Code | Description |
|---------|------|
| `file_not_found` | File not found |
| `path_not_allowed` | Path not allowed to read |
| `file_too_large` | File too large to read |
| `read_failed` | Failed to read file |

---

#### mpu_secure_file_write()

Secure file write.

```php
/**
 * @param string $file_path File path
 * @param string $content File content
 * @return bool|WP_Error Success or error
 */
function mpu_secure_file_write(string $file_path, string $content)
```

**Possible Errors:**

| Error Code | Description |
|---------|------|
| `mkdir_failed` | Failed to create directory |
| `path_not_allowed` | Path not allowed to write |
| `invalid_filename` | Invalid filename |
| `write_failed` | Failed to write file |

---

#### mpu_encrypt_api_key()

Encrypt API Key using AES-256-CBC.

```php
/**
 * @param string $api_key Original API Key
 * @return string Encrypted string
 */
function mpu_encrypt_api_key(string $api_key): string
```

---

#### mpu_decrypt_api_key()

Decrypt API Key.

```php
/**
 * @param string $encrypted Encrypted string
 * @return string Decrypted API Key
 */
function mpu_decrypt_api_key(string $encrypted): string
```

---

### AI Functions (ai-functions.php)

#### mpu_call_ai_api()

Call AI API (Automatically select provider). Supports Gemini, OpenAI, Claude.

```php
/**
 * @param string $provider AI provider ('gemini', 'openai', 'claude')
 * @param string $api_key API Key
 * @param string $system_prompt System prompt (Personality settings)
 * @param string $user_prompt User prompt
 * @param string $language Language setting ('zh-TW', 'ja', 'en')
 * @param array $mpu_opt Plugin options (used to get model name)
 * @return string|WP_Error AI response or error
 */
function mpu_call_ai_api(
    string $provider,
    string $api_key,
    string $system_prompt,
    string $user_prompt,
    string $language = 'zh-TW',
    array $mpu_opt = []
)
```

**Example:**

```php
$response = mpu_call_ai_api(
    'gemini',
    $api_key,
    'You are a friendly assistant, keep responses short.',
    'What is this article about?',
    'zh-TW',
    $mpu_opt
);
if (!is_wp_error($response)) {
    echo $response;
}
```

---

#### mpu_should_trigger_ai()

Check if AI should be triggered.

```php
/**
 * @return bool Should trigger
 */
function mpu_should_trigger_ai(): bool
```

Check conditions:

- Is AI enabled
- Is API Key set
- Does current page match trigger conditions
- Probability check

---

#### mpu_get_language_instruction()

Get language instruction string.

```php
/**
 * @param string $language Language code (zh-TW, ja, en)
 * @return string Language instruction
 */
function mpu_get_language_instruction(string $language): string
```

**Return Values:**

| Language Code | Return Value |
|---------|--------|
| `zh-TW` | `請用繁體中文回覆。` |
| `ja` | `日本語で返答してください。` |
| `en` | `Please reply in English.` |

---

#### mpu_call_gemini_api()

Call Google Gemini API.

```php
/**
 * @param string $prompt User prompt
 * @param string $system_prompt System prompt
 * @return string|null AI response or null
 */
function mpu_call_gemini_api(string $prompt, string $system_prompt): ?string
```

---

#### mpu_call_openai_api()

Call OpenAI API.

```php
/**
 * @param string $prompt User prompt
 * @param string $system_prompt System prompt
 * @return string|null AI response or null
 */
function mpu_call_openai_api(string $prompt, string $system_prompt): ?string
```

---

#### mpu_call_claude_api()

Call Anthropic Claude API.

```php
/**
 * @param string $prompt User prompt
 * @param string $system_prompt System prompt
 * @return string|null AI response or null
 */
function mpu_call_claude_api(string $prompt, string $system_prompt): ?string
```

---

### LLM Functions (llm-functions.php)

> 💡 **v2.2.0 Update**: LLM functionality has been upgraded to a **Universal LLM Interface**, supporting four major AI services: Ollama, Gemini, OpenAI, and Claude.

#### mpu_is_remote_endpoint()

Detect if endpoint is a remote connection.

```php
/**
 * @param string $endpoint Ollama endpoint URL
 * @return bool Is remote connection (true = Remote, false = Local)
 */
function mpu_is_remote_endpoint(string $endpoint): bool
```

**Example:**

```php
$is_remote = mpu_is_remote_endpoint('https://your-domain.com'); // true
$is_local = mpu_is_remote_endpoint('http://localhost:11434');  // false
```

---

#### mpu_get_ollama_timeout()

Get appropriate timeout based on endpoint type and operation type.

```php
/**
 * @param string $endpoint Ollama endpoint URL
 * @param string $operation_type Operation type: 'check', 'api_call', 'test'
 * @return int Timeout (seconds)
 */
function mpu_get_ollama_timeout(string $endpoint, string $operation_type = 'api_call'): int
```

**Example:**

```php
$timeout = mpu_get_ollama_timeout('https://your-domain.com', 'api_call'); // 90
$timeout = mpu_get_ollama_timeout('http://localhost:11434', 'check');      // 3
```

---

#### mpu_validate_ollama_endpoint()

Validate and normalize Ollama endpoint URL.

```php
/**
 * @param string $endpoint Raw endpoint URL
 * @return string|WP_Error Normalized URL or error
 */
function mpu_validate_ollama_endpoint(string $endpoint)
```

**Example:**

```php
$validated = mpu_validate_ollama_endpoint('https://your-domain.com');
if (is_wp_error($validated)) {
    echo $validated->get_error_message();
} else {
    echo $validated; // 'https://your-domain.com'
}
```

---

#### mpu_check_ollama_available()

Check if Ollama service is available (Fast check, using cache).

```php
/**
 * @param string $endpoint Ollama endpoint
 * @param string $model Model name
 * @return bool Is service available
 */
function mpu_check_ollama_available(string $endpoint, string $model): bool
```

**Example:**

```php
if (mpu_check_ollama_available('https://your-domain.com', 'qwen3:8b')) {
    // Service available
}
```

---

#### mpu_generate_llm_dialogue()

Generate random dialogue using LLM (Replace built-in dialogue). Supports all AI providers (Ollama, Gemini, OpenAI, Claude).

```php
/**
 * @param string $ukagaka_name Ukagaka name
 * @param string $last_response Last AI response (for avoiding repetitive dialogue)
 * @param array $response_history Response history array (recent responses for stricter repetition detection)
 * @return string|false Generated dialogue content, or false on failure
 */
function mpu_generate_llm_dialogue(string $ukagaka_name = 'default_1', string $last_response = '', array $response_history = [])
```

**Example:**

```php
$dialogue = mpu_generate_llm_dialogue('frieren');
if ($dialogue !== false) {
    echo $dialogue;
}

// With repetition detection
$dialogue = mpu_generate_llm_dialogue('frieren', 'Last response', ['Response 1', 'Response 2']);
```

**Key Features:**

- Automatically uses optimized XML-structured System Prompt
- Supports anti-repetition mechanism (similarity detection)
- Automatically integrates WordPress info, user info, visitor info
- Supports 70+ Frieren-style dialogue examples

---

#### mpu_is_llm_replace_dialogue_enabled()

Check if LLM replace built-in dialogue is enabled.

```php
/**
 * @return bool
 */
function mpu_is_llm_replace_dialogue_enabled(): bool
```

---

#### mpu_get_ollama_settings()

Get Ollama settings.

```php
/**
 * @return array|false Settings array, or false if not enabled
 */
function mpu_get_ollama_settings()
```

**Return Value:**

```php
[
    'endpoint' => 'http://localhost:11434',
    'model' => 'qwen3:8b',
    'replace_dialogue' => true,
]
```

---

#### mpu_get_visitor_info_for_llm()

Get visitor information (for LLM dialogue generation). Integrates Slimstat data, including BOT detection and geolocation information.

```php
/**
 * @return array Visitor information array
 */
function mpu_get_visitor_info_for_llm(): array
```

**Return Value:**

```php
[
    'is_bot' => false,                    // Is BOT
    'browser_type' => 0,                  // Browser type (0=normal, 1=BOT, 2=mobile)
    'browser_name' => 'Chrome',            // Browser name (BOT name)
    'slimstat_enabled' => true,            // Is Slimstat enabled
    'slimstat_country' => 'TW',            // Country code
    'slimstat_city' => 'Taipei',           // City name
]
```

---

#### mpu_get_visitor_status_text()

Get visitor status text (BOT or geolocation).

```php
/**
 * @param array $visitor_info Visitor information
 * @return string Visitor status description
 */
function mpu_get_visitor_status_text(array $visitor_info): string
```

**Example:**

```php
$visitor_info = mpu_get_visitor_info_for_llm();
$status = mpu_get_visitor_status_text($visitor_info);
// May return: '🤖 BOT: Googlebot' or 'From TW / Taipei'
```

---

#### mpu_compress_context_info()

Compress WordPress, user, and visitor information into compact XML format (for System Prompt).

```php
/**
 * @param array $wp_info WordPress information
 * @param array $user_info User information
 * @param array $visitor_info Visitor information
 * @return string Compressed XML format string
 */
function mpu_compress_context_info(array $wp_info, array $user_info, array $visitor_info): string
```

---

#### mpu_build_frieren_style_examples()

Build Frieren-style dialogue examples (70+ examples covering 12 categories).

```php
/**
 * @param array $wp_info WordPress information
 * @param array $visitor_info Visitor information
 * @param string $time_context Time context (morning/afternoon/evening/late night)
 * @param string $theme_name Theme name
 * @param string $theme_version Theme version
 * @param string $theme_author Theme author
 * @return string Formatted example text
 */
function mpu_build_frieren_style_examples(
    array $wp_info,
    array $visitor_info,
    string $time_context,
    string $theme_name,
    string $theme_version,
    string $theme_author
): string
```

**Example Categories:**

- Greeting, Casual, Time-aware, Observation
- Magic research, Tech observation, Statistics, Memory
- Admin comments, Unexpected reactions, BOT detection, Silence

**Special Features:**

- **Observation category** automatically reads up to 5 lines from the current character's built-in dialogue file
  - Automatically filters out empty strings and messages longer than 50 characters
  - Randomly selects qualifying dialogues to add to examples
  - Makes AI-generated dialogues closer to the character's actual style

---

#### mpu_build_prompt_categories()

Build User Prompt category instructions (corresponding to example categories).

```php
/**
 * @param array $wp_info WordPress information
 * @param array $visitor_info Visitor information
 * @param string $time_context Time context
 * @param string $theme_name Theme name
 * @param string $theme_version Theme version
 * @param string $theme_author Theme author
 * @return array Category instruction array
 */
function mpu_build_prompt_categories(
    array $wp_info,
    array $visitor_info,
    string $time_context,
    string $theme_name,
    string $theme_version,
    string $theme_author
): array
```

**Return Value:**

```php
[
    'greeting' => ['Refer to greeting examples and lightly greet', ...],
    'casual' => ['Refer to casual examples and say something plain', ...],
    'time_aware' => ['Refer to time-aware examples and express {$time_context} time sense', ...],
    // ... more categories
]
```

---

#### mpu_weighted_random_select()

Randomly select a category from a category array based on a weight array (weighted random selection).

```php
/**
 * @param array $categories Category array (key => value)
 * @param array $weights Weight array (key => weight), higher values have higher probability of being selected
 * @return string Selected category key
 */
function mpu_weighted_random_select(array $categories, array $weights): string
```

**Usage Example:**

```php
$categories = [
    'greeting' => ['Greeting 1', 'Greeting 2'],
    'casual' => ['Casual 1', 'Casual 2'],
    'tech_observation' => ['Tech 1', 'Tech 2'],
];

$weights = [
    'greeting' => 10,
    'casual' => 10,
    'tech_observation' => 3,  // Lower weight for tech observation category
];

$selected = mpu_weighted_random_select($categories, $weights);
// May return: 'greeting', 'casual', or 'tech_observation'
// tech_observation has approximately 30% probability compared to other categories
```

**Notes:**

- If a category is not set in the weight array, the default weight is 5
- If total weight is 0, uniform random selection (`array_rand()`) will be used
- Higher weight values have higher probability of being selected

---

#### mpu_build_optimized_system_prompt()

Build optimized System Prompt (XML-structured version).

```php
/**
 * @param array $mpu_opt Plugin settings
 * @param array $wp_info WordPress information
 * @param array $user_info User information
 * @param array $visitor_info Visitor information
 * @param string $ukagaka_name Ukagaka name
 * @param string $time_context Time context (morning/afternoon/evening/late night)
 * @param string $language Language setting
 * @return string Optimized system prompt
 */
function mpu_build_optimized_system_prompt(
    array $mpu_opt,
    array $wp_info,
    array $user_info,
    array $visitor_info,
    string $ukagaka_name,
    string $time_context,
    string $language
): string
```

**Returned XML Structure:**

```xml
<character>
Name: {Character name}
Core settings: {System Prompt from backend}
Style features: ...
</character>
<knowledge_base>
{Compressed context information}
</knowledge_base>
<behavior_rules>
  <must_do>...</must_do>
  <should_do>...</should_do>
  <must_not_do>...</must_not_do>
</behavior_rules>
<response_style_examples>
{70+ dialogue examples}
</response_style_examples>
<current_context>
Time: {Time context}
Language: {Language setting}
</current_context>
```

---

#### mpu_calculate_text_similarity()

Calculate similarity between two texts (for preventing repetitive dialogue).

```php
/**
 * @param string $text1 First text
 * @param string $text2 Second text
 * @return float Similarity (0.0-1.0)
 */
function mpu_calculate_text_similarity(string $text1, string $text2): float
```

**Example:**

```php
$similarity = mpu_calculate_text_similarity('Hello again.', 'Hello again.');
// Returns: 1.0 (identical)

$similarity = mpu_calculate_text_similarity('Hello again.', 'Long time no see.');
// Returns: 0.0 (completely different)
```

---

#### mpu_debug_system_prompt()

Debug mode: Output System Prompt to WordPress debug log.

```php
/**
 * @param string $system_prompt System prompt to debug
 * @param string $context Debug context description
 * @return void
 */
function mpu_debug_system_prompt(string $system_prompt, string $context = ''): void
```

**Example:**

```php
$system_prompt = mpu_build_optimized_system_prompt(...);
mpu_debug_system_prompt($system_prompt, 'LLM Dialogue Generation');
// Outputs to wp-content/debug.log if WP_DEBUG is enabled
```

---

### Ukagaka Functions (ukagaka-functions.php)

#### mpu_get_ukagakas()

Get Ukagaka list HTML.

```php
/**
 * @return string HTML string
 */
function mpu_get_ukagakas(): string
```

---

#### mpu_get_shell()

Get Ukagaka shell image URL.

```php
/**
 * @param string $key Ukagaka key
 * @param bool $for_js Whether for JavaScript (default true)
 * @return string Image URL
 */
function mpu_get_shell(string $key, bool $for_js = true): string
```

---

#### mpu_get_msg_array()

Get message array.

```php
/**
 * @param array $ukagaka Ukagaka data
 * @return array Message array
 */
function mpu_get_msg_array(array $ukagaka): array
```

---

#### mpu_process_msg_codes()

Process special codes in messages.

```php
/**
 * @param string $msg Original message
 * @return string Processed message
 */
function mpu_process_msg_codes(string $msg): string
```

---

#### mpu_load_dialog_file()

Load dialogue file.

```php
/**
 * @param string $filename Filename (without extension)
 * @param string $format File format (txt/json)
 * @return array Dialogue array
 */
function mpu_load_dialog_file(string $filename, string $format): array
```

**Example:**

```php
$messages = mpu_load_dialog_file('frieren', 'json');
```

---

### Frontend Functions (frontend-functions.php)

#### mpu_is_hide()

Check if Ukagaka should be hidden.

```php
/**
 * @return bool Should hide
 */
function mpu_is_hide(): bool
```

---

#### mpu_generate_html()

Generate Ukagaka HTML and output.

```php
/**
 * @return void
 */
function mpu_generate_html(): void
```

---

### Admin Functions (admin-functions.php)

#### mpu_generate_dialog_file()

Generate dialogue file.

```php
/**
 * @param string $key Ukagaka key
 * @param array $ukagaka Ukagaka data
 * @return bool Success or not
 */
function mpu_generate_dialog_file(string $key, array $ukagaka): bool
```

---

## WordPress Hooks

### Actions

#### mpu_loaded

Triggered after plugin modules are loaded.

```php
add_action('mpu_loaded', function() {
    // Plugin loaded
});
```

---

#### mpu_before_html

Triggered before Ukagaka HTML generation.

```php
add_action('mpu_before_html', function() {
    // Output content before Ukagaka HTML
});
```

---

#### mpu_after_html

Triggered after Ukagaka HTML generation.

```php
add_action('mpu_after_html', function() {
    // Output content after Ukagaka HTML
});
```

---

#### mpu_settings_saved

Triggered after settings are saved.

```php
add_action('mpu_settings_saved', function($mpu_opt) {
    // Settings saved, $mpu_opt contains new settings
}, 10, 1);
```

---

### Filters

#### mpu_options

Filter settings.

```php
add_filter('mpu_options', function($mpu_opt) {
    // Modify settings
    $mpu_opt['auto_talk_interval'] = 10;
    return $mpu_opt;
});
```

---

#### mpu_messages

Filter message array.

```php
add_filter('mpu_messages', function($messages, $ukagaka_key) {
    // Add extra messages for specific Ukagaka
    if ($ukagaka_key === 'frieren') {
        $messages[] = 'Magic requires time to study.';
    }
    return $messages;
}, 10, 2);
```

---

#### mpu_ai_response

Filter AI response.

```php
add_filter('mpu_ai_response', function($response, $prompt) {
    // Modify AI response
    return $response . ' ✨';
}, 10, 2);
```

---

#### mpu_ukagaka_html

Filter Ukagaka HTML.

```php
add_filter('mpu_ukagaka_html', function($html) {
    // Modify HTML
    return $html;
});
```

---

## AJAX Endpoints

### mpu_nextmsg

Get next message.

**Action:** `mpu_nextmsg`

**Request Parameters:**

| Parameter | Type | Description |
|-----|------|------|
| `ukagaka` | string | Ukagaka key |
| `current` | int | Current message index |
| `mode` | string | `next` or `random` |

**Success Response:**

```json
{
    "success": true,
    "data": {
        "msg": "Dialogue content",
        "index": 1
    }
}
```

---

### mpu_change

Switch Ukagaka.

**Action:** `mpu_change`

**Request Parameters:**

| Parameter | Type | Description |
|-----|------|------|
| `ukagaka` | string | Target Ukagaka key |

**Success Response:**

```json
{
    "success": true,
    "data": {
        "name": "Frieren",
        "shell": "https://.../frieren.png",
        "messages": ["Dialog 1", "Dialog 2"]
    }
}
```

---

### mpu_get_settings

Get frontend settings.

**Action:** `mpu_get_settings`

**Success Response:**

```json
{
    "success": true,
    "data": {
        "autoTalk": true,
        "autoTalkInterval": 8000,
        "typewriterSpeed": 40,
        "clickBehavior": 0
    }
}
```

---

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

---

### mpu_load_dialog

Load external dialogue file.

**Action:** `mpu_load_dialog`

**Request Parameters:**

| Parameter | Type | Description |
|-----|------|------|
| `filename` | string | Filename |
| `format` | string | `txt` or `json` |

**Success Response:**

```json
{
    "success": true,
    "data": {
        "messages": ["Dialog 1", "Dialog 2", "Dialog 3"]
    }
}
```

---

### mpu_ai_context_chat

AI page awareness chat.

**Action:** `mpu_ai_context_chat`

**Request Parameters:**

| Parameter | Type | Description |
|-----|------|------|
| `title` | string | Post Title |
| `content` | string | Post Content |
| `nonce` | string | Security nonce |

**Success Response:**

```json
{
    "success": true,
    "data": {
        "message": "AI Generated Comment"
    }
}
```

---

### mpu_get_visitor_info

Get visitor info (Requires Slimstat).

**Action:** `mpu_get_visitor_info`

**Request Parameters:**

| Parameter | Type | Description |
|-----|------|------|
| `nonce` | string | Security nonce |

**Success Response:**

```json
{
    "success": true,
    "data": {
        "country": "TW",
        "city": "Taipei",
        "referer": "https://google.com",
        "searchterms": "Search terms",
        "browser": "Chrome",
        "platform": "Windows"
    }
}
```

---

### mpu_ai_greet

AI first visitor greeting.

**Action:** `mpu_ai_greet`

**Request Parameters:**

| Parameter | Type | Description |
|-----|------|------|
| `visitor_info` | object | Visitor Info |
| `nonce` | string | Security nonce |

**Success Response:**

```json
{
    "success": true,
    "data": {
        "message": "Welcome friend from Taiwan!"
    }
}
```

---

## JavaScript Functions

### Core Functions

#### mpu_nextmsg(mode)

Show next message.

```javascript
/**
 * @param {string} mode - 'next' sequential / 'random' random / '' use setting
 */
mpu_nextmsg('next');
```

---

#### mpu_hidemsg()

Hide balloon.

```javascript
mpu_hidemsg();
```

---

#### mpu_showmsg()

Show balloon.

```javascript
mpu_showmsg();
```

---

#### mpu_hideukagaka()

Hide Ukagaka.

```javascript
mpu_hideukagaka();
```

---

#### mpu_showukagaka()

Show Ukagaka.

```javascript
mpu_showukagaka();
```

---

#### mpuChange()

Open Ukagaka switch menu.

```javascript
mpuChange();
```

---

#### mpu_showMessage(message, options)

Show specific message (with typewriter effect).

```javascript
/**
 * @param {string} message - Message content
 * @param {object} options - Options
 * @param {string} options.color - Text color
 * @param {boolean} options.typewriter - Whether to use typewriter effect
 */
mpu_showMessage('Welcome!', {
    color: '#ff6b6b',
    typewriter: true
});
```

---

### AI Functions

#### mpu_triggerAIContext()

Trigger AI page awareness.

```javascript
mpu_triggerAIContext();
```

---

#### mpu_triggerAIGreeting()

Trigger AI first visitor greeting.

```javascript
mpu_triggerAIGreeting();
```

---

#### mpu_pauseAutoTalk(duration)

Pause auto talk.

```javascript
/**
 * @param {number} duration - Pause duration (ms)
 */
mpu_pauseAutoTalk(10000); // Pause for 10 seconds
```

---

### Global Settings Object

```javascript
window.mpuSettings = {
    ajaxUrl: '/wp-admin/admin-ajax.php',
    nonce: 'xxx',
    autoTalk: true,
    autoTalkInterval: 8000,      // ms
    typewriterSpeed: 40,          // ms/char
    clickBehavior: 0,             // 0=Next, 1=No Action
    nextMode: 0,                  // 0=Sequential, 1=Random
    aiEnabled: true,
    aiTextColor: '#ff6b6b',
    aiDisplayDuration: 8000,      // ms
    aiGreetEnabled: true,
    useExternalFile: false,
    externalFileFormat: 'txt'
};
```

---

## Special Codes

You can use the following special codes in dialogue content:

### :recentpost[n]

Show list of recent n posts.

```
Recent Posts: :recentpost[5]:
```

---

### :randompost[n]

Show list of random n posts.

```
Recommended: :randompost[3]:
```

---

### :commenters[n]

Show recent n commenters.

```
Thanks for commenting: :commenters[5]:
```

---

### :date:

Show today's date.

```
Today is :date:
```

---

### :time:

Show current time.

```
Current time is :time:
```

---

### :sitename:

Show site name.

```
Welcome to :sitename:!
```

---

**📌 Note:** Special codes are processed on the server side, converted to actual content before being sent to the frontend.

---

**Documentation Version: 2.1.0**
