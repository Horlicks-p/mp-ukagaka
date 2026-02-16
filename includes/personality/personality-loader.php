<?php

/**
 * Personality Loader: JSON-based personality system (Core)
 * 
 * This module provides data-driven personality loading functionality,
 * allowing different character personalities to be defined via JSON files.
 * 
 * Related modules (split from this file):
 * - personality-prompts.php: Dynamic prompts and variable substitution
 * - personality-decorations.php: Decoration system
 * - personality-emoji.php: Emoji system
 * 
 * Folder structure:
 * /ghost/
 *   ├── Frieren/
 *   │   ├── manifest.json        - Metadata & config
 *   │   ├── prompts.json         - Static dialogue categories
 *   │   ├── dynamics.json        - Dynamic templates with variables
 *   │   ├── weights.json         - Category weights
 *   │   └── decorations.json     - Decoration click prompts
 *   └── [other personalities...]
 * 
 * @package MP_Ukagaka
 * @subpackage Personality
 * @since 2.4.0
 */

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Get the personalities directory path
 * 
 * @return string Absolute path to personalities directory
 */
function mpu_get_personalities_dir()
{
    $main_file = defined('MPU_MAIN_FILE') ? MPU_MAIN_FILE : dirname(dirname(dirname(__FILE__))) . '/mp-ukagaka.php';
    return plugin_dir_path($main_file) . 'ghost';
}

/**
 * Sanitize personality ID to prevent path traversal attacks
 * 
 * @param string $personality_id Personality ID
 * @return string Sanitized personality ID
 */
function mpu_sanitize_personality_id($personality_id)
{
    if (empty($personality_id) || !is_string($personality_id)) {
        return '';
    }
    return preg_replace('/[^a-zA-Z0-9_-]/', '', $personality_id);
}

/**
 * Get personality ID from ukagaka_name (via dialog_filename)
 * 
 * @param string $ukagaka_name Ukagaka configuration key (e.g., 'default_1')
 * @return string|null Personality ID, or null if not found
 */
function mpu_get_personality_id_from_ukagaka_name($ukagaka_name)
{
    if (empty($ukagaka_name)) {
        return null;
    }

    $mpu_opt = mpu_get_option();
    $ukagaka_data = $mpu_opt['ukagakas'][$ukagaka_name] ?? [];
    $dialog_filename = $ukagaka_data['dialog_filename'] ?? '';

    if (empty($dialog_filename)) {
        return null;
    }

    $personality_id = mpu_sanitize_personality_id($dialog_filename);

    if (!empty($personality_id) && mpu_personality_exists($personality_id)) {
        return $personality_id;
    }

    return null;
}

/**
 * Get the current personality ID
 * 
 * Priority:
 * 1. WordPress option 'mpu_current_personality'
 * 2. Current ukagaka's name (for backward compatibility)
 * 3. Default to 'Frieren'
 * 
 * @return string Personality ID (folder name)
 */
function mpu_get_current_personality_id()
{
    static $personality_id = null;

    if ($personality_id !== null) {
        return $personality_id;
    }

    $mpu_opt = mpu_get_option();

    if (!empty($mpu_opt['current_personality'])) {
        $personality_id = sanitize_file_name($mpu_opt['current_personality']);
        if (mpu_personality_exists($personality_id)) {
            return $personality_id;
        }
    }

    $current_ukagaka = $mpu_opt['cur_ukagaka'] ?? 'default_1';
    $ukagaka_data = $mpu_opt['ukagakas'][$current_ukagaka] ?? [];
    $ukagaka_name = $ukagaka_data['name'] ?? '';

    if (!empty($ukagaka_name)) {
        $personalities = mpu_get_available_personalities();
        foreach ($personalities as $id => $manifest) {
            if (
                $ukagaka_name === ($manifest['name'] ?? '') ||
                $ukagaka_name === ($manifest['name_en'] ?? '') ||
                $ukagaka_name === ($manifest['name_zh'] ?? '')
            ) {
                $personality_id = $id;
                return $personality_id;
            }
        }
    }

    $personality_id = 'Frieren';
    return $personality_id;
}

/**
 * Check if a personality exists
 * 
 * @param string $personality_id Personality folder name
 * @return bool True if personality folder and manifest.json exist
 */
function mpu_personality_exists($personality_id)
{
    $personality_id = mpu_sanitize_personality_id($personality_id);
    if (empty($personality_id)) {
        return false;
    }

    $dir = mpu_get_personalities_dir() . '/' . $personality_id;
    return is_dir($dir) && file_exists($dir . '/manifest.json');
}

/**
 * Get all available personalities
 * 
 * @param bool $include_placeholders Whether to include placeholder personalities
 * @return array Associative array of personality_id => manifest
 */
function mpu_get_available_personalities($include_placeholders = false)
{
    static $cache = null;
    static $cache_with_placeholders = null;

    if (!$include_placeholders && $cache !== null) {
        return $cache;
    }
    if ($include_placeholders && $cache_with_placeholders !== null) {
        return $cache_with_placeholders;
    }

    $personalities = [];
    $dir = mpu_get_personalities_dir();

    if (!is_dir($dir)) {
        return $personalities;
    }

    $folders = scandir($dir);
    foreach ($folders as $folder) {
        if ($folder === '.' || $folder === '..') {
            continue;
        }

        $manifest_path = $dir . '/' . $folder . '/manifest.json';
        if (!file_exists($manifest_path)) {
            continue;
        }

        $manifest = mpu_load_json_file($manifest_path);
        if ($manifest === null) {
            continue;
        }

        if (!$include_placeholders && !empty($manifest['_status']) && $manifest['_status'] === 'placeholder') {
            continue;
        }

        $personalities[$folder] = $manifest;
    }

    if ($include_placeholders) {
        $cache_with_placeholders = $personalities;
    } else {
        $cache = $personalities;
    }

    return $personalities;
}

/**
 * Load a JSON file with error handling and caching
 * 
 * @param string $file_path Absolute path to JSON file
 * @return array|null Parsed JSON array, or null on error
 */
function mpu_load_json_file($file_path)
{
    static $cache = [];

    if (isset($cache[$file_path])) {
        return $cache[$file_path];
    }

    if (!file_exists($file_path) || !is_readable($file_path)) {
        $cache[$file_path] = null;
        return null;
    }

    $file_size = filesize($file_path);
    if ($file_size > 1024 * 1024) {
        mpu_log_error('JSON file too large: ' . $file_path);
        $cache[$file_path] = null;
        return null;
    }

    $content = file_get_contents($file_path);
    if ($content === false) {
        $cache[$file_path] = null;
        return null;
    }

    $data = json_decode($content, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        mpu_log_error('JSON parse error in ' . $file_path . ': ' . json_last_error_msg());
        $cache[$file_path] = null;
        return null;
    }

    $cache[$file_path] = $data;
    return $data;
}

/**
 * Load personality manifest
 * 
 * @param string|null $personality_id Personality ID, or null for current
 * @return array Manifest data, or empty array if not found
 */
function mpu_load_personality_manifest($personality_id = null)
{
    if ($personality_id === null) {
        $personality_id = mpu_get_current_personality_id();
    } else {
        $personality_id = mpu_sanitize_personality_id($personality_id);
        if (empty($personality_id)) {
            return [];
        }
    }

    $path = mpu_get_personalities_dir() . '/' . $personality_id . '/manifest.json';
    $manifest = mpu_load_json_file($path);

    return $manifest ?? [];
}

/**
 * Load personality system prompt
 * 
 * @param string|null $personality_id Personality ID, or null for current
 * @return string|false System prompt string, or false if not found
 */
function mpu_load_personality_system_prompt($personality_id = null)
{
    if ($personality_id === null) {
        $personality_id = mpu_get_current_personality_id();
    } else {
        $personality_id = mpu_sanitize_personality_id($personality_id);
        if (empty($personality_id)) {
            return false;
        }
    }

    $dir = mpu_get_personalities_dir() . '/' . $personality_id;

    $md_path = $dir . '/system_prompt.md';
    if (file_exists($md_path) && is_readable($md_path)) {
        $content = file_get_contents($md_path);
        if ($content !== false) {
            return $content;
        }
    }

    $manifest = mpu_load_personality_manifest($personality_id);
    if (!empty($manifest['system_prompt'])) {
        if (is_array($manifest['system_prompt'])) {
            return implode("\n", $manifest['system_prompt']);
        }
        return $manifest['system_prompt'];
    }

    return false;
}

/**
 * Load modular personality prompt (instructions.md + personality.md)
 *
 * Priority:
 * 1. Modular files: instructions.md + personality.md (either or both)
 * 2. Legacy: system_prompt.md → manifest.json (via mpu_load_personality_system_prompt)
 *
 * @param string|null $personality_id Personality ID, or null for current
 * @return string|false Combined prompt string, or false if no modular files found
 */
function mpu_load_personality_full_prompt($personality_id = null)
{
    if ($personality_id === null) {
        $personality_id = mpu_get_current_personality_id();
    } else {
        $personality_id = mpu_sanitize_personality_id($personality_id);
        if (empty($personality_id)) {
            return false;
        }
    }

    $dir = mpu_get_personalities_dir() . '/' . $personality_id;
    $instructions_path = $dir . '/instructions.md';
    $personality_path = $dir . '/personality.md';

    $instructions = '';
    $personality = '';

    if (file_exists($instructions_path) && is_readable($instructions_path)) {
        $content = file_get_contents($instructions_path);
        if ($content !== false) {
            $instructions = $content;
        }
    }

    if (file_exists($personality_path) && is_readable($personality_path)) {
        $content = file_get_contents($personality_path);
        if ($content !== false) {
            $personality = $content;
        }
    }

    // At least one modular file must exist
    if (empty($instructions) && empty($personality)) {
        return false;
    }

    // Instructions first (LLM sees protocol before personality)
    $parts = [];
    if (!empty($instructions)) {
        $parts[] = $instructions;
    }
    if (!empty($personality)) {
        $parts[] = $personality;
    }

    return implode("\n\n", $parts);
}

/**
 * Resolve system prompt with unified fallback chain
 *
 * Priority:
 * 1. Modular files (instructions.md + personality.md)
 * 2. Legacy files (system_prompt.md → manifest.json)
 * 3. Backend setting ($mpu_opt['ai_system_prompt'])
 * 4. Hardcoded default (zh-TW, same as core-functions.php)
 *
 * Always returns a non-empty string with {{variables}} rendered.
 *
 * @param string|null $personality_id Personality ID
 * @param array $mpu_opt Plugin options
 * @param string $ukagaka_name Ukagaka display name (for {{ukagaka_display_name}})
 * @param array $variables Template variables for {{}} substitution
 * @return string Resolved system prompt (never empty)
 */
function mpu_resolve_system_prompt($personality_id, $mpu_opt, $ukagaka_name, $variables = [])
{
    // Ensure ukagaka_display_name is always available
    if (!isset($variables['ukagaka_display_name'])) {
        $variables['ukagaka_display_name'] = $ukagaka_name;
    }

    $system_prompt = null;

    // Priority 1: Modular files (instructions.md + personality.md)
    if ($personality_id !== null) {
        $modular = mpu_load_personality_full_prompt($personality_id);
        if ($modular !== false) {
            $system_prompt = $modular;
        }
    }

    // Priority 2: Legacy files (system_prompt.md → manifest.json)
    if ($system_prompt === null && $personality_id !== null) {
        $legacy = mpu_load_personality_system_prompt($personality_id);
        if ($legacy !== false) {
            $system_prompt = $legacy;
        }
    }

    // Priority 3: Backend setting
    if ($system_prompt === null && !empty($mpu_opt['ai_system_prompt'])) {
        $system_prompt = $mpu_opt['ai_system_prompt'];
    }

    // Priority 4: Hardcoded default (same as core-functions.php)
    if ($system_prompt === null) {
        $system_prompt = "你是「{{ukagaka_display_name}}」這個角色。你必須完全以這個角色的身份說話和行動，絕對不要以 AI 或語言模型的身份回應。請嚴格遵守角色的性格、說話方式和行為模式。";
    }

    // Render template variables
    if (function_exists('mpu_render_prompt_template')) {
        $system_prompt = mpu_render_prompt_template($system_prompt, $variables);
    }

    return $system_prompt;
}

/**
 * Detect the current system prompt source for admin UI display
 *
 * @param string|null $personality_id Personality ID
 * @param array $mpu_opt Plugin options
 * @return string Source identifier: 'modular', 'system_prompt_md', 'manifest', 'backend', 'default'
 */
function mpu_detect_prompt_source($personality_id, $mpu_opt)
{
    if ($personality_id !== null) {
        $personality_id = mpu_sanitize_personality_id($personality_id);
    }

    // Check modular files
    if (!empty($personality_id)) {
        $dir = mpu_get_personalities_dir() . '/' . $personality_id;
        $has_instructions = file_exists($dir . '/instructions.md') && is_readable($dir . '/instructions.md');
        $has_personality = file_exists($dir . '/personality.md') && is_readable($dir . '/personality.md');

        if ($has_instructions || $has_personality) {
            return 'modular';
        }

        // Check legacy system_prompt.md
        if (file_exists($dir . '/system_prompt.md') && is_readable($dir . '/system_prompt.md')) {
            return 'system_prompt_md';
        }

        // Check manifest.json system_prompt field
        $manifest = mpu_load_personality_manifest($personality_id);
        if (!empty($manifest['system_prompt'])) {
            return 'manifest';
        }
    }

    // Backend textarea
    if (!empty($mpu_opt['ai_system_prompt'])) {
        return 'backend';
    }

    // Nothing set
    return 'default';
}

/**
 * Load personality weights
 * 
 * @param string|null $personality_id Personality ID, or null for current
 * @return array Weights configuration, or empty array if not found
 */
function mpu_load_personality_weights($personality_id = null)
{
    if ($personality_id === null) {
        $personality_id = mpu_get_current_personality_id();
    } else {
        $personality_id = mpu_sanitize_personality_id($personality_id);
        if (empty($personality_id)) {
            return [];
        }
    }

    $path = mpu_get_personalities_dir() . '/' . $personality_id . '/weights.json';
    $weights = mpu_load_json_file($path);

    return $weights ?? [];
}

/**
 * Get holiday adjustments from personality weights
 * 
 * @param string|null $personality_id Personality ID, or null for current
 * @return array Holiday adjustments array
 */
function mpu_get_personality_holiday_adjustments($personality_id = null)
{
    $weights_config = mpu_load_personality_weights($personality_id);
    return $weights_config['holiday_adjustments'] ?? [];
}

/**
 * Get base category weights from personality
 * 
 * @param string|null $personality_id Personality ID, or null for current
 * @return array Base weights array (category => weight)
 */
function mpu_get_personality_base_weights($personality_id = null)
{
    $weights_config = mpu_load_personality_weights($personality_id);
    return $weights_config['base_weights'] ?? [];
}

/**
 * Get time period adjustments from personality
 * 
 * @param string|null $personality_id Personality ID, or null for current
 * @return array Time adjustments array
 */
function mpu_get_personality_time_adjustments($personality_id = null)
{
    $weights_config = mpu_load_personality_weights($personality_id);
    return $weights_config['time_adjustments'] ?? [];
}

/**
 * Get seasonal adjustments from personality weights
 * 
 * @param string|null $personality_id Personality ID, or null for current
 * @return array Seasonal adjustments array
 */
function mpu_get_personality_seasonal_adjustments($personality_id = null)
{
    $weights_config = mpu_load_personality_weights($personality_id);
    return $weights_config['seasonal_adjustments'] ?? [];
}

/**
 * Get weather adjustments from personality weights
 * 
 * @param string|null $personality_id Personality ID, or null for current
 * @return array Weather adjustments array
 */
function mpu_get_personality_weather_adjustments($personality_id = null)
{
    $weights_config = mpu_load_personality_weights($personality_id);
    return $weights_config['weather_adjustments'] ?? [];
}

/**
 * Check if current personality is Frieren
 * 
 * @return bool True if current personality is Frieren
 */
function mpu_is_frieren_personality()
{
    $personality_id = mpu_get_current_personality_id();
    return strtolower($personality_id) === 'frieren';
}

/**
 * Get personality setting
 * 
 * @param string $key Setting key
 * @param mixed $default Default value if not found
 * @param string|null $personality_id Personality ID, or null for current
 * @return mixed Setting value
 */
function mpu_get_personality_setting($key, $default = null, $personality_id = null)
{
    $manifest = mpu_load_personality_manifest($personality_id);

    if (isset($manifest['settings'][$key])) {
        return $manifest['settings'][$key];
    }

    return $default;
}

/**
 * Get personality character trait
 * 
 * @param string $key Trait key
 * @param mixed $default Default value if not found
 * @param string|null $personality_id Personality ID, or null for current
 * @return mixed Trait value
 */
function mpu_get_personality_trait($key, $default = null, $personality_id = null)
{
    $manifest = mpu_load_personality_manifest($personality_id);

    if (isset($manifest['character_traits'][$key])) {
        return $manifest['character_traits'][$key];
    }

    return $default;
}

/**
 * Get personality max response length
 * 
 * @param string|null $personality_id Personality ID, or null for current
 * @param string|null $ukagaka_name Ukagaka name
 * @return int Max response length in characters
 */
function mpu_get_personality_max_response_length($personality_id = null, $ukagaka_name = null)
{
    // 嘗試從 ukagaka_name 獲取 personality_id
    if ($personality_id === null && $ukagaka_name !== null) {
        if (function_exists('mpu_get_personality_id_from_ukagaka_name')) {
            $personality_id = mpu_get_personality_id_from_ukagaka_name($ukagaka_name);
        }
    }

    // 如果仍然是 null，使用當前 personality（傳 null 給 mpu_get_personality_setting 會自動獲取當前 personality）
    $max_length = mpu_get_personality_setting('max_response_length', 500, $personality_id);
    $max_length = absint($max_length);

    if ($max_length < 20) {
        $max_length = 20;
    }

    return $max_length;
}

/**
 * Get personality max tokens for API calls
 * 
 * @param string|null $personality_id Personality ID, or null for current
 * @param string|null $ukagaka_name Ukagaka name
 * @return int Max tokens for API calls
 */
function mpu_get_personality_max_tokens($personality_id = null, $ukagaka_name = null)
{
    // 嘗試從 ukagaka_name 獲取 personality_id
    if ($personality_id === null && $ukagaka_name !== null) {
        if (function_exists('mpu_get_personality_id_from_ukagaka_name')) {
            $personality_id = mpu_get_personality_id_from_ukagaka_name($ukagaka_name);
        }
    }

    // 從 manifest.json 的 settings.max_tokens 讀取，預設 800
    $max_tokens = mpu_get_personality_setting('max_tokens', 800, $personality_id);
    $max_tokens = absint($max_tokens);

    // 最小值保護
    if ($max_tokens < 50) {
        $max_tokens = 50;
    }

    return $max_tokens;
}

