# MP Ukagaka API Reference

> 📚 Complete Function, Hooks, and REST Endpoints Reference (v2.13.7)

---

## 📑 Table of Contents

1. [PHP Functions](#php-functions)
2. [WordPress Hooks](#wordpress-hooks)
3. [REST Endpoints](#rest-endpoints)
4. [JavaScript Functions](#javascript-functions)
5. [Special Codes](#special-codes)

---

## PHP Functions

### Core Functions (core-functions.php)

#### mpu_default_opt()

Gets the default options.

```php
/**
 * @return array Default options array
 */
function mpu_default_opt()
```

**Example:**

```php
$defaults = mpu_default_opt();
echo $defaults['auto_talk_interval']; // 8
```

---

#### mpu_get_option()

Gets plugin options (cached).

```php
/**
 * @return array Options array
 */
function mpu_get_option()
```

**Example:**

```php
$mpu_opt = mpu_get_option();
if ($mpu_opt['ai_enabled']) {
    // AI is enabled
}
```

---

#### mpu_count_total_msg()

Calculates the total number of dialogue messages across all Ukagakas.

```php
/**
 * @return int Total message count
 */
function mpu_count_total_msg()
```

---

### Utility Functions (utility-functions.php)

#### mpu_array2str()

Converts an array to a string (separated by line breaks).

```php
/**
 * @param array $arr Input array
 * @return string Output string
 */
function mpu_array2str($arr = [])
```

**Example:**

```php
$messages = ['Dialogue 1', 'Dialogue 2', 'Dialogue 3'];
$str = mpu_array2str($messages);
// Result:
// Dialogue 1
//
// Dialogue 2
//
// Dialogue 3
```

---

#### mpu_str2array()

Converts a string to an array (separated by empty lines).

```php
/**
 * @param string $str Input string
 * @return array Output array
 */
function mpu_str2array($str = "")
```

**Example:**

```php
$str = "Dialogue 1\n\nDialogue 2\n\nDialogue 3";
$messages = mpu_str2array($str);
// Result: ['Dialogue 1', 'Dialogue 2', 'Dialogue 3']
```

---

#### mpu_output_filter()

HTML output filter.

```php
/**
 * @param string $str Input string
 * @return string Filtered string
 */
function mpu_output_filter($str)
```

---

#### mpu_js_filter()

JavaScript output filter (escapes quotes and special characters).

```php
/**
 * @param string $str Input string
 * @return string Filtered string
 */
function mpu_js_filter($str)
```

---

#### mpu_secure_file_read()

Safely reads a file.

```php
/**
 * @param string $file_path File path (must be within the dialogs/ directory)
 * @return string|WP_Error File content or error
 */
function mpu_secure_file_read($file_path)
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

| Error Code         | Description                    |
| ------------------ | ------------------------------ |
| `file_not_found`   | File not found                 |
| `path_not_allowed` | Path not allowed to be read    |
| `file_too_large`   | File is too large to read      |
| `read_failed`      | Failed to read file            |

---

#### mpu_secure_file_write()

Safely writes to a file.

```php
/**
 * @param string $file_path File path
 * @param string $content File content
 * @return bool|WP_Error Success or error
 */
function mpu_secure_file_write($file_path, $content)
```

**Possible Errors:**

| Error Code         | Description                     |
| ------------------ | ------------------------------- |
| `mkdir_failed`     | Failed to create directory      |
| `path_not_allowed` | Path not allowed to be written  |
| `invalid_filename` | Invalid filename                |
| `write_failed`     | Failed to write to file         |

---

#### mpu_encrypt_api_key()

Encrypts the API Key using AES-256-CBC.

```php
/**
 * @param string $api_key Raw API Key
 * @return string Encrypted string
 */
function mpu_encrypt_api_key($api_key)
```

---

#### mpu_decrypt_api_key()

Decrypts the API Key.

```php
/**
 * @param string $encrypted Encrypted string
 * @return string Decrypted API Key
 */
function mpu_decrypt_api_key($encrypted_key)
```

---

#### mpu_get_client_ip()

Gets the client's real IP address (supports reverse proxies).

```php
/**
 * @return string Client IP address
 */
function mpu_get_client_ip()
```

---

#### mpu_fetch_external_api()

General external API request function (with caching).

```php
/**
 * @param string $cache_key Cache key
 * @param string $url API endpoint URL
 * @param int $cache_duration Cache duration (seconds)
 * @param array $options Additional options
 * @return array|string|null API response data
 */
function mpu_fetch_external_api($cache_key, $url, $cache_duration = MPU_CACHE_DEFAULT, $options = [])
```

---

#### mpu_render_prompt_template()

Renders a prompt template, replacing `{{variable_name}}` with actual values.

```php
/**
 * @param string $template Template string
 * @param array $variables Variable array
 * @return string Replaced string
 */
function mpu_render_prompt_template($template, $variables = [])
```

---

#### mpu_get_current_user_info()

Gets the current WordPress user's information.

```php
/**
 * @return array Current user information array
 */
function mpu_get_current_user_info()
```

---

#### mpu_get_wordpress_info()

Gets WordPress site information (including basic info and statistics).

```php
/**
 * @return array WordPress site information array
 */
function mpu_get_wordpress_info()
```

---

#### mpu_get_provider_api_key()

Gets the decrypted API Key for the specified AI provider.

```php
/**
 * @param string $provider AI provider name (gemini, openai, claude, ollama)
 * @param array|null $mpu_opt Options array
 * @return string Decrypted API Key
 */
function mpu_get_provider_api_key($provider, $mpu_opt = null)
```

---

#### mpu_get_current_provider()

Gets the currently enabled AI provider name.

```php
/**
 * @param array|null $mpu_opt Options array
 * @return string AI provider name
 */
function mpu_get_current_provider($mpu_opt = null)
```

---

### AI Functions (ai-functions.php)

#### mpu_call_ai_api()

Calls an AI API (automatically selects the provider). Supports Gemini, OpenAI, Claude.

```php
/**
 * @param string $provider AI provider ('gemini', 'openai', 'claude', 'ollama')
 * @param string $api_key API Key
 * @param string $system_prompt System prompt (character setting)
 * @param string $user_prompt User prompt
 * @param string $language Language setting ('zh-TW', 'ja', 'en')
 * @param array|null $mpu_opt Plugin options (for getting model name)
 * @param int|null $max_tokens Max tokens (optional)
 * @return string|WP_Error AI response or error
 */
function mpu_call_ai_api(
    $provider,
    $api_key,
    $system_prompt,
    $user_prompt,
    $language = 'zh-TW',
    $mpu_opt = null,
    $max_tokens = null
)
```

**Example:**

```php
$response = mpu_call_ai_api(
    'gemini',
    $api_key,
    'You are a friendly assistant. Keep responses short.',
    'What is this article about?',
    'en',
    $mpu_opt
);
if (!is_wp_error($response)) {
    echo $response;
}
```

---

#### mpu_get_language_instruction()

Gets the language instruction string.

```php
/**
 * @param string $language Language code (zh-TW, ja, en)
 * @return string Language instruction
 */
function mpu_get_language_instruction($language)
```

**Return Values:**

| Language Code | Return Value |
| -------- | ---------------------------- |
| `zh-TW`  | `請用繁體中文回覆。` |
| `ja`     | `日本語で返答してください。` |
| `en`     | `Please reply in English.` |

---

### API Cache Functions (api-cache.php)

> 💡 **New in v2.5.6**: API caching system. Uses WordPress Transient API to cache AI API responses, reducing duplicate requests and costs.

#### mpu_is_api_cache_enabled()

Checks if API caching is enabled.

```php
/**
 * @return bool
 */
function mpu_is_api_cache_enabled()
```

---

#### mpu_get_api_cache_ttl()

Gets the cache TTL (seconds).

```php
/**
 * @return int Default 3600 seconds (1 hour), range 300-86400 seconds
 */
function mpu_get_api_cache_ttl()
```

---

#### mpu_generate_cache_key()

Generates a cache key.

```php
/**
 * @param string $provider Provider
 * @param string $system_prompt System prompt
 * @param string $user_prompt User prompt
 * @return string Cache key
 */
function mpu_generate_cache_key($provider, $system_prompt, $user_prompt)
```

---

#### mpu_get_cached_api_response()

Gets an API response from cache.

```php
/**
 * @param string $cache_key Cache key
 * @return string|false Cached response or false
 */
function mpu_get_cached_api_response($cache_key)
```

---

#### mpu_set_cached_api_response()

Stores an API response into the cache.

```php
/**
 * @param string $cache_key Cache key
 * @param string $response API response
 * @return bool
 */
function mpu_set_cached_api_response($cache_key, $response)
```

---

#### mpu_clear_all_api_cache()

Clears all LLM API caches.

```php
/**
 * @return int Number of cleared caches
 */
function mpu_clear_all_api_cache()
```

---

#### mpu_get_api_cache_stats()

Gets API cache statistics.

```php
/**
 * @return array ['count' => int, 'ttl' => int, 'enabled' => bool]
 */
function mpu_get_api_cache_stats()
```

---

### LLM Functions (llm-functions.php)

> 💡 **Updated in 2.2.0**: LLM functionality upgraded to a **Universal LLM Interface**, supporting Ollama, Gemini, OpenAI, and Claude.

#### mpu_is_remote_endpoint()

Checks if an endpoint is a remote connection.

```php
/**
 * @param string $endpoint Ollama endpoint URL
 * @return bool Is remote connection (true = remote, false = local)
 */
function mpu_is_remote_endpoint($endpoint)
```

**Example:**

```php
$is_remote = mpu_is_remote_endpoint('https://your-domain.com'); // true
$is_local = mpu_is_remote_endpoint('http://localhost:11434');  // false
```

---

#### mpu_get_ollama_timeout()

Gets the appropriate timeout based on endpoint type and operation.

```php
/**
 * @param string $endpoint Ollama endpoint URL
 * @param string $operation_type Operation type: 'check', 'api_call', 'test'
 * @return int Timeout (seconds)
 */
function mpu_get_ollama_timeout($endpoint, $operation_type = 'api_call')
```

**Example:**

```php
$timeout = mpu_get_ollama_timeout('https://your-domain.com', 'api_call'); // 90
$timeout = mpu_get_ollama_timeout('http://localhost:11434', 'check');      // 3
```

---

#### mpu_validate_ollama_endpoint()

Validates and normalizes an Ollama endpoint URL.

```php
/**
 * @param string $endpoint Raw endpoint URL
 * @return string|WP_Error Normalized URL or error
 */
function mpu_validate_ollama_endpoint($endpoint)
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

Checks if the Ollama service is available (quick check, cached).

```php
/**
 * @param string $endpoint Ollama endpoint
 * @param string $model Model name
 * @return bool Service availability
 */
function mpu_check_ollama_available($endpoint, $model)
```

**Example:**

```php
if (mpu_check_ollama_available('https://your-domain.com', 'qwen3:8b')) {
    // Service available
}
```

---

#### mpu_generate_llm_dialogue()

Generates random dialogue using LLMs (replaces built-in dialogue). Supports all AI providers (Ollama, Gemini, OpenAI, Claude).

```php
/**
 * @param string $ukagaka_name Character name
 * @param string $last_response Previous AI response (to avoid repetition)
 * @param array $response_history Response history array (for stricter repetition detection)
 * @param int $last_visit_hours Hours since last visit (-1 default means no data)
 * @return string|false Generated dialogue content, false on failure
 */
function mpu_generate_llm_dialogue($ukagaka_name = 'default_1', $last_response = '', $response_history = [], $last_visit_hours = -1)
```

**Example:**

```php
$dialogue = mpu_generate_llm_dialogue('frieren');
if ($dialogue !== false) {
    echo $dialogue;
}

// With repetition detection
$dialogue = mpu_generate_llm_dialogue('frieren', 'Last response', ['Response1', 'Response2']);
```

**Features:**

- Uses optimized XML-structured System Prompt
- Supports anti-repetition mechanism (similarity detection)
- Automatically integrates WordPress info, user info, and visitor info
- Supports 70+ Frieren-style dialogue examples

**Available Filter Hooks (v2.5.7):**

| Filter | Description | Parameters |
| ----------------------- | -------------------------- | --------------------------------------------------------- |
| `mpu_llm_system_prompt` | Modifies System Prompt | `$prompt`, `$ukagaka_name`, `$personality_id`, `$context` |
| `mpu_llm_user_prompt` | Injects extra context before chat instructions | `$prompt`, `$ukagaka_name`, `$personality_id` |

**Usage Example (Security Alert Integration):**

```php
add_filter('mpu_llm_user_prompt', function($prompt, $ukagaka_name, $personality_id) {
    $attack_info = get_transient('mpu_llar_attack_info');
    if ($attack_info) {
        return $prompt . "\n【Security Alert】\n" . $attack_info;
    }
    return $prompt;
}, 10, 3);
```

---

#### mpu_is_llm_replace_dialogue_enabled()

Checks if LLM replacement of built-in dialogue is enabled.

```php
/**
 * @return bool
 */
function mpu_is_llm_replace_dialogue_enabled()
```

---

#### mpu_get_visitor_info_for_llm()

Gets visitor info (for LLM dialogue generation). Integrates Slimstat data, including BOT detection and geolocation.

```php
/**
 * @return array Visitor info array
 */
function mpu_get_visitor_info_for_llm()
```

**Return Value:**

```php
[
    'is_bot' => false,                    // Is BOT
    'browser_type' => 0,                  // Browser type (0=normal, 1=BOT, 2=mobile)
    'browser_name' => 'Chrome',           // Browser name (BOT name)
    'slimstat_enabled' => true,           // Is Slimstat enabled
    'slimstat_country' => 'US',           // Country code
    'slimstat_city' => 'New York',        // City name
]
```

---

#### mpu_get_visitor_status_text()

Gets visitor status text (BOT or geolocation).

```php
/**
 * @param array $visitor_info Visitor info
 * @return string Visitor status description
 */
function mpu_get_visitor_status_text($visitor_info)
```

**Example:**

```php
$visitor_info = mpu_get_visitor_info_for_llm();
$status = mpu_get_visitor_status_text($visitor_info);
// Could return: '🤖 BOT: Googlebot' or 'From US / New York'
```

---

#### mpu_weighted_random_select()

Randomly selects a category from a category array based on weights.

```php
/**
 * @param array $categories Category array (key => value)
 * @param array $weights Weights array (key => weight), higher value = higher chance
 * @return string Selected category key
 */
function mpu_weighted_random_select($categories, $weights)
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
    'tech_observation' => 3,  // Lower weight for tech observation
];

$selected = mpu_weighted_random_select($categories, $weights);
// Could return: 'greeting', 'casual' or 'tech_observation'
// tech_observation selection chance is approx 30% of others
```

**Notes:**

- If a category is not set in weights, default is 5
- If total weight is 0, uses uniform random selection (`array_rand()`)
- Higher weight values increase selection chance

---

#### mpu_build_optimized_system_prompt()

Builds an optimized System Prompt (XML structured version).

```php
/**
 * @param array $mpu_opt Plugin options
 * @param array $wp_info WordPress info
 * @param array $user_info User info
 * @param array $visitor_info Visitor info
 * @param string $ukagaka_name Character name
 * @param string $time_context Time context (morning/afternoon/evening/late night)
 * @param string $language Language setting
 * @return string Optimized system prompt
 */
function mpu_build_optimized_system_prompt(
    $mpu_opt,
    $wp_info,
    $user_info,
    $visitor_info,
    $ukagaka_name,
    $time_context,
    $language
)
```

**Returned XML Structure:**

```xml
<character>
Name: {Character Name}
Core Setting: {System Prompt from Admin}
Style Traits: ...
</character>
<knowledge_base>
{Compressed Context Info}
</knowledge_base>
<behavior_rules>
  <must_do>...</must_do>
  <should_do>...</should_do>
  <must_not_do>...</must_not_do>
</behavior_rules>
<response_style_examples>
{70+ Dialogue Examples}
</response_style_examples>
<current_context>
Time: {Time Context}
Language: {Language Setting}
</current_context>
```

---

#### mpu_calculate_text_similarity()

Calculates similarity between two texts (for repetition prevention).

```php
/**
 * @param string $text1 First text
 * @param string $text2 Second text
 * @param bool $text1_normalized Is $text1 already normalized
 * @return float Similarity (0.0-1.0)
 */
function mpu_calculate_text_similarity($text1, $text2, $text1_normalized = false)
```

**Example:**

```php
$similarity = mpu_calculate_text_similarity('Hello there.', 'Hello there.');
// Returns: 1.0 (exact match)

$similarity = mpu_calculate_text_similarity('Hello there.', 'How are you?');
// Returns: 0.0 (completely different)
```

---

### Prompt Category Management (prompt-categories.php)

> 💡 **New in v2.2.0**: Prompt category instruction management module, used for category instructions and dynamic weight configuration during LLM dialogue generation.

#### mpu_get_static_prompt_categories()

Gets static category instructions (uses cache to avoid rebuilds).

```php
/**
 * @param string|null $personality_id Personality ID (optional, defaults to current)
 * @return array Static category instructions array
 */
function mpu_get_static_prompt_categories($personality_id = null)
```

---

#### mpu_add_statistics_prompts()

Adds dynamic statistics category instructions. Generates dialogue instructions based on WordPress statistics.

```php
/**
 * @param array &$categories Category array (passed by reference)
 * @param array $wp_info WordPress info
 * @param string|null $personality_id Personality ID (optional, defaults to current)
 * @return void
 */
function mpu_add_statistics_prompts(&$categories, $wp_info, $personality_id = null)
```

---

#### mpu_build_prompt_categories()

Builds category instructions for User Prompt. This function generates dialogue instructions for different categories, used for the "Replace Built-in Dialogue with LLM" feature.

```php
/**
 * @param array $wp_info WordPress info
 * @param array $visitor_info Visitor info
 * @param string $time_context Time context
 * @param string $theme_name Theme name
 * @param string $theme_version Theme version
 * @param string $theme_author Theme author
 * @return array Category instructions array
 */
function mpu_build_prompt_categories(
    $wp_info,
    $visitor_info,
    $time_context,
    $theme_name,
    $theme_version,
    $theme_author
)
```

**Return Structure:**

```php
[
    'greeting' => ['Casually greet based on greeting examples', ...],
    'casual' => ['Say daily words flatly based on casual examples', ...],
    'time_aware' => ['Express time perception based on time_aware examples', ...],
    // ... 35 categories
]
```

---

#### mpu_get_dynamic_category_weights()

Gets dynamic category weight configuration. Adjusts category weights based on time context and visitor info.

```php
/**
 * @param string $time_context Time context
 * @param array $visitor_info Visitor info
 * @param array $context_vars Context variables (optional)
 * @return array Weights array
 */
function mpu_get_dynamic_category_weights(
    $time_context,
    $visitor_info,
    $context_vars = [],
    $personality_id = null
)
```

**Special Adjustments:**

- Late night: `silence`, `philosophical`, `party_memories` weights increased
- Morning: `daily_life` weight increased (character is weak in mornings)
- BOT Visitors: `bot_detection` category weight significantly increased

---

#### mpu_get_decoration_prompt()

Gets the prompt for decoration click dialogue. Returns corresponding User Prompt instruction when a user clicks a decoration.

```php
/**
 * @param string $decoration_type Decoration type (suitcase, evil_horns, staff, books)
 * @param string|null $personality_id Personality ID (optional, defaults to current)
 * @return string|false Prompt, false if not found
 */
function mpu_get_decoration_prompt($decoration_type, $personality_id = null)
```

**Supported Decoration Types:**

| Type | Description |
| ------------ | -------------------- |
| `suitcase` | Suitcase (Magic Collection Box) |
| `evil_horns` | Evil Horns |
| `staff` | Magic Staff |
| `books` | Grimoire |

---

### Ukagaka Functions (ukagaka-functions.php)

#### mpu_get_shell()

Gets the shell image URL of the specified character.

```php
/**
 * @param string|false $num Character key; false to use current character
 * @param bool $echo Whether to output directly (default false, returns string)
 * @return string Image URL
 */
function mpu_get_shell($num = false, $echo = false)
```

---

#### mpu_get_msg_arr()

Gets the message array structure of the specified character (includes `msgall`, `auto_msg`, `msg`, etc. keys).

```php
/**
 * @param string $num Character key (e.g. 'default_1', 'frieren')
 * @return array Message array
 */
function mpu_get_msg_arr($num)
```

---

#### mpu_msg_code()

Processes special codes in the message array (e.g. `:recentpost[n]:`, `:commenters[n]:`), replacing them with actual HTML.

```php
/**
 * @param array $msglist Message array
 * @return array Processed message array
 */
function mpu_msg_code($msglist = [])
```

---

#### mpu_get_msg_from_file()

Loads dialogue files under the `dialogs/` directory (auto-detects `.txt` / `.json` formats).

```php
/**
 * @param string $filename_base File name (without extension)
 * @return array Dialogue array
 */
function mpu_get_msg_from_file($filename_base)
```

**Example:**

```php
$messages = mpu_get_msg_from_file('frieren');
```

---

### Frontend Functions (frontend-functions.php)

#### mpu_html()

Generates and outputs the Ukagaka HTML.

```php
/**
 * @param string|false $num Character key; false to use current character
 * @return void
 */
function mpu_html($num = false)
```

---

### Admin Functions (admin-functions.php)

#### mpu_generate_dialog_file()

Writes the message array out to a dialogue file (`.txt` or `.json`).

```php
/**
 * @param string $filename File name (without extension)
 * @param array $msg_array Message array
 * @param string $ext Extension ('txt' or 'json')
 * @return bool Success status
 */
function mpu_generate_dialog_file($filename, $msg_array, $ext)
```

---

## WordPress Hooks

> 📌 Since v2.9.2 REST refactoring, all plugin-level `do_action()` hooks (`mpu_loaded`, `mpu_before_html`, `mpu_after_html`, `mpu_settings_saved`) and `apply_filters()` hooks (`mpu_options`, `mpu_messages`, `mpu_ai_response`, `mpu_ukagaka_html`) have been removed. Only 4 filters related to LLM prompt construction remain.

### Filters

#### mpu_llm_system_prompt

Filters the LLM System Prompt (including personality card, WordPress context, behavior rules, etc. in complete XML structure).

```php
add_filter('mpu_llm_system_prompt', function($prompt, $ukagaka_name, $personality_id, $context) {
    return $prompt;
}, 10, 4);
```

---

#### mpu_llm_user_prompt

Filters the User Prompt (injects extra context before chat instructions, e.g. security alerts, event messages).

```php
add_filter('mpu_llm_user_prompt', function($prompt, $ukagaka_name, $personality_id) {
    $attack_info = get_transient('mpu_llar_attack_info');
    if ($attack_info) {
        return $prompt . "\n【Security Alert】\n" . $attack_info;
    }
    return $prompt;
}, 10, 3);
```

---

#### mpu_prompt_categories

Filters the category definitions for LLM auto-talk (Greeting, Casual, Time Aware, Statistics Observation, etc. 35+ categories).

```php
add_filter('mpu_prompt_categories', function($categories, $wp_info, $visitor_info, $time_context) {
    return $categories;
}, 10, 4);
```

---

#### mpu_category_weights

Filters the weighted random weights of dialogue categories. Higher value means more easily selected; default is 5.

```php
add_filter('mpu_category_weights', function($weights, $time_context, $visitor_info, $context_vars) {
    // Increase philosophical thoughts probability during late night
    if ($time_context === '深夜') {
        $weights['philosophical'] = 15;
    }
    return $weights;
}, 10, 4);
```

---

## REST Endpoints

> 💡 **Since v2.9.2**: The plugin's endpoint architecture fully migrated from AJAX to REST API, with unified rate limiting and error handling. Frontend calls require an `X-WP-Nonce` (provided via `mpuRestNonce` through `wp_localize_script`).

### Basic Info

- **Namespace**: `/wp-json/mp-ukagaka/v1`
- **Permissions**: Most endpoints are public (`__return_true`), only testing/cache management endpoints are admin-only.
- **Rate Limit**: Counted independently per endpoint, returns HTTP 429 when exceeded.
- **Response Format**: Except for `/chat/user-stream` (SSE), all are JSON; structured as `{ success, data, ... }` or `WP_Error`.

### Character / Settings

| Endpoint | Method | Permission | Parameters (All Optional) | Rate Limit | Description |
| --- | --- | --- | --- | --- | --- |
| `/init` | GET | Public | `ukagaka_num` | 30/60s | Unified initialization endpoint to get shell, decorations, emojis, touchzones, and settings at once |
| `/settings` | GET | Public | — | 30/60s | Gets frontend settings object (`auto_talk`, `typewriter_speed`, `ai_*`, etc.) |
| `/change` | POST | Public | `mpu_num` | 10/60s | If empty, returns list of available characters; if provided, switches character (and Set-Cookie) |
| `/shell-info` | GET / POST | Public | `ukagaka_num` | 30/60s | Gets appearance info for specified character |
| `/decoration-config` | GET / POST | Public | — | 30/60s | Gets decoration base URL, settings, touchzones, visibility flags |
| `/emoji-config` | GET / POST | Public | — | 30/60s | Gets emoji base URL, support list, and keyword mapping |
| `/extend` | GET / POST | Public | — | 10/60s | Character extension tag locations (Reserved endpoint) |

### Dialogue

| Endpoint | Method | Permission | Parameters | Rate Limit | Description |
| --- | --- | --- | --- | --- | --- |
| `/nextmsg` | POST | Public | `cur_num`, `cur_msgnum`, `last_response`, `response_history`, `last_visit_hours`, `session_id`, `history` | 20/60s | Auto-talk rotation: Calls AI generation if LLM replace mode is on, else draws from built-in dialogues |
| `/dialog` | GET / POST | Public | `file` (required) | 30/60s | Reads dialogue file under `dialogs/`; returns `{msgall, auto_msg, msg, next_msg, default_msg}` |
| `/visitor-info` | GET | Public | — | 30/60s | Returns visitor info such as referrer, search engine, Slimstat country/city, etc. |
| `/decoration-prompts` | GET / POST | Public | `decoration_type` | 20/60s | Gets prompts for decoration click dialogue |
| `/wake-ghost` | POST | Public | `personality_id` or `ukagaka_num` (at least one) | 10/60s | Temporarily wakes up a sleeping character; WP_Error codes: `rest_wake_ghost_missing_param`, `rest_wake_ghost_unavailable` |

### AI Chat

| Endpoint | Method | Permission | Parameters | Rate Limit | Description |
| --- | --- | --- | --- | --- | --- |
| `/chat/context` | POST | Public | `page_title`, `page_content`, `publish_date`, `session_id`, `history` | 5/60s | Page aware dialogue, triggers AI comments based on current article content; max 500 chars |
| `/chat/greet` | POST | Public | `referrer`, `referrer_host`, `search_engine`, `is_direct`, `country`, `city`, `session_id`, `history` | 10/60s | First-time visitor greeting, customized by source country/search engine |
| `/chat/user` | POST | Public | `message` (required), `history`, `page_title`, `page_content`, `session_id` | 30/60s | Multi-turn interactive chat (non-streaming), supports MCP Tool/Abilities calls; returns `{msg, emoji}` |
| `/chat/user-stream` | POST | Public | Same as `/chat/user` | 30/60s | SSE streaming version, outputs token-by-token if supported by Provider |

### Touch Interactions

| Endpoint | Method | Permission | Parameters | Rate Limit | Description |
| --- | --- | --- | --- | --- | --- |
| `/touch/decoration` | POST | Public | `decoration_type` (required) | 20/60s | AI reaction when clicking decorations; returns `{msg, emoji}` |
| `/touch/zone` | POST | Public | `touch_zone` (required) | 20/60s | Petting reaction when clicking character body zones; returns `{msg, emoji, zone}` |

### Admin Testing & Management (Admin Only)

| Endpoint | Method | Permission | Parameters | Rate Limit | Description |
| --- | --- | --- | --- | --- | --- |
| `/test-connection/{provider}` | POST | Admin | `provider` (Path param: gemini/openai/claude/ollama/weather), `api_key`, `model`, `endpoint`; weather additionally takes `latitude`, `longitude` | 10/60s | Unified Provider connection test endpoint |
| `/clear-cache` | POST | Admin | — | 10/60s | Clears LLM API response caches |

---

### `/chat/user-stream` SSE Event Format

Starting from v2.12.x, interactive chat supports Server-Sent Events. The stream sends events in a fixed sequence using the `text/event-stream` format:

| Event | Fired When | Data Content |
| --- | --- | --- |
| `start` | Stream starts | `{"provider": "gemini", "model": "gemini-2.5-flash"}` |
| `nonce` | Immediately after `start` | `{"new_token": "<nonce>", "new_nonce": "<nonce>"}` — Provides a new nonce for the next request |
| `delta` | When AI generates tokens (multiple triggers) | `{"text": "Yes"}` — Single token/chunk |
| `done` | Stream ends | `{"msg": "Full message", "emoji": "smile"}` — Final result after length truncation and emoji analysis |
| `error` | Provider doesn't support streaming, or error occurs | `{"message": "<error_message>"}` |

**Raw Stream Example**:

```
event: start
data: {"provider":"gemini","model":"gemini-2.5-flash"}

event: nonce
data: {"new_token":"a1b2c3","new_nonce":"a1b2c3"}

event: delta
data: {"text":"今日"}

event: delta
data: {"text":"はいい天気"}

event: delta
data: {"text":"ですね。"}

event: done
data: {"msg":"今日はいい天気ですね。","emoji":"happy"}
```

---

### Retained AJAX Endpoints

A few non-chat actions are still executed via `admin-ajax.php`, mainly for internal integrations:

| Action | Handler | Description |
| --- | --- | --- |
| `wp_ajax_mpu_test_diary_generate` | `mpu_ajax_test_diary_generate` | Manually triggers diary generation test from admin (Admin only) |
| `wp_ajax_nopriv_slimtrack` / `wp_ajax_slimtrack` | `mpu_bb_intercept_slimstat` | Bot Blocker intercepts Slimstat tracking (priority 0) |
| `wp_ajax_nopriv_mbb_js_flag` / `wp_ajax_mbb_js_flag` | `mpu_bb_js_flag_handler` | Bot Blocker JS execution flag detection |

> 📌 AJAX actions from older versions (v2.9.x and below) such as `mpu_nextmsg`, `mpu_change`, `mpu_chat_*`, `mpu_test_*_connection`, `mpu_load_dialog`, `mpu_get_visitor_info`, `mpu_wake_ghost`, `mpu_init`, `mpu_get_settings`, `mpu_clear_api_cache`, `mpu_check_spam_event` have all been removed. Please use the corresponding REST endpoints from the table above instead.

## JavaScript Functions

### Core Functions

#### mpu_nextmsg(trigger)

Displays the next message.

```javascript
/**
 * @param {string} trigger - 'next' sequential / 'random' random / '' use setting value
 */
mpu_nextmsg("next");
```

---

#### mpu_hidemsg()

Hides the dialogue box.

```javascript
mpu_hidemsg();
```

---

#### mpu_showmsg()

Shows the dialogue box.

```javascript
mpu_showmsg();
```

---

#### mpu_hiderobot()

Hides the character.

```javascript
mpu_hiderobot();
```

---

#### mpu_showrobot()

Shows the character.

```javascript
mpu_showrobot();
```

---

#### mpuChange(num)

Opens the character switch menu, or switches directly to the specified character if param provided.

```javascript
/**
 * @param {string} [num] - Target character key; omits to open menu
 */
mpuChange();            // Opens menu
mpuChange("default_2"); // Switches directly
```

---

### Global Variables

Upon frontend loading, data passed via `wp_localize_script` and returned by the `/init` endpoint are written to the following `window` globals:

| Variable | Source | Description |
| --- | --- | --- |
| `window.mpuRestUrl` | `wp_localize_script` | REST base URL (e.g. `/wp-json/mp-ukagaka/v1/`) |
| `window.mpuRestNonce` | `wp_localize_script` | `X-WP-Nonce` used for REST requests |
| `window.mpuL10n` | `wp_localize_script` | Translated string set for frontend display |
| `window.mpuSettings` | `/init` return | Character behavior settings object (see below) |
| `window.mpuInitData` | `/init` return | Complete original init response object |
| `window.mpuPersonalityId` | `/init` return | Current personality ID |
| `window.mpuCanvasManager` | `ukagaka-anime.js` | Canvas animation manager |
| `window.mpuChatHistory` | `ukagaka-chat.js` | Multi-turn chat history array (max 40 items) |
| `window.mpuChatModeActive` | `ukagaka-chat.js` | Interactive chat mode flag |
| `window.mpuDecorationsBaseUrl` / `mpuDecorationConfig` / `mpuTouchZones` / `mpuShowDecorations` | `/init` return | Decoration and touchzone related info |
| `window.mpuEmojiBaseUrl` / `mpuSupportedEmojis` / `mpuEmojiMappings` | `/init` return | Emoji system related data |

#### window.mpuSettings

Populated by the `settings` block returned from `/wp-json/mp-ukagaka/v1/init`:

```javascript
window.mpuSettings = {
  auto_talk: true,
  auto_talk_interval: 8,            // seconds
  typewriter_speed: 40,             // ms/character
  ai_enabled: true,
  ai_probability: 10,               // 0-100, AI trigger probability
  ai_trigger_pages: "is_single",    // Page type condition
  ai_text_color: "#000000",
  ai_display_duration: 8,           // seconds
  ai_greet_first_visit: true,
  ollama_replace_dialogue: false,   // Replace built-in dialogue with LLM
  enable_chat_mode: false,          // Enable interactive chat mode
  sleep_mode: {
    enabled: false,
    frequency_multiplier: 1.0
  }
};
```

---

## Special Codes

The following special codes can be used in dialogue content, which are processed server-side by `mpu_msg_code()` before sending to frontend. Supports two formats: `:code[n]:` or `(:code[n]:)` (inside parentheses).

### :recentpost[n]: / :recentposts[n]:

Displays a list of the recent n posts. The singular form lists them line by line, while the plural form (`recentposts`) concatenates them with `<br>`.

```
Recent posts: :recentpost[5]:
```

---

### :randompost[n]: / :randomposts[n]:

Displays a list of n random posts. The singular form lists them line by line, while the plural form concatenates them with `<br>`.

```
Recommended reading: :randompost[3]:
```

---

### :commenters[n]:

Displays the recent n unique commenters (separated by commas).

```
Thanks for commenting: :commenters[5]:
```

---

**📌 Note:** The above are the only codes actually supported by `mpu_msg_code()`. The `:date:`, `:time:`, and `:sitename:` codes mentioned in older docs **are currently not implemented**; if you need such variable replacements, please use `mpu_render_prompt_template()` to process `{{variable}}` placeholders instead.

---

**Document Version: 2.13.7**
