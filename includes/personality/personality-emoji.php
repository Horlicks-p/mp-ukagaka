<?php

/**
 * Personality Emoji: Emoji system functions
 * 
 * This module handles emoji detection, loading, and URL generation
 * for personality-based emoji expressions.
 * 
 * @package MP_Ukagaka
 * @subpackage Personality
 * @since 2.5.0
 */

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Check if personality has emoji support
 * 
 * Detection order:
 * 1. Check manifest.json for 'emoji' configuration
 * 2. Auto-detect emojis folder existence
 * 3. Auto-detect *-emoji.js script existence
 * 
 * @param string|null $personality_id Personality ID, or null for current
 * @return bool True if personality has emoji support
 */
function mpu_personality_has_emoji($personality_id = null)
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

    // Method 1: Check manifest for emoji config
    $manifest = mpu_load_personality_manifest($personality_id);
    if (!empty($manifest['emoji'])) {
        return true;
    }

    // Method 2: Auto-detect emojis folder
    $emojis_dir = $dir . '/emojis';
    if (is_dir($emojis_dir)) {
        $script = mpu_get_personality_emoji_script_path($personality_id);
        return $script !== null;
    }

    return false;
}

/**
 * Get personality emoji script file path (absolute)
 * 
 * Detection order:
 * 1. Check manifest.json for 'emoji.script'
 * 2. Auto-detect *-emoji.js in personality folder
 * 
 * @param string|null $personality_id Personality ID, or null for current
 * @return string|null Absolute file path, or null if not found
 */
function mpu_get_personality_emoji_script_path($personality_id = null)
{
    if ($personality_id === null) {
        $personality_id = mpu_get_current_personality_id();
    } else {
        $personality_id = mpu_sanitize_personality_id($personality_id);
        if (empty($personality_id)) {
            return null;
        }
    }

    $dir = mpu_get_personalities_dir() . '/' . $personality_id;

    // Method 1: Check manifest for emoji script
    $manifest = mpu_load_personality_manifest($personality_id);
    if (!empty($manifest['emoji']['script'])) {
        $script_path = $dir . '/' . $manifest['emoji']['script'];
        if (file_exists($script_path)) {
            return $script_path;
        }
    }

    // Method 2: Auto-detect *-emoji.js
    $pattern = $dir . '/*-emoji.js';
    $files = glob($pattern);
    if (!empty($files)) {
        return $files[0];
    }

    return null;
}

/**
 * Get personality emoji script URL
 * 
 * @param string|null $personality_id Personality ID, or null for current
 * @return string|null Script URL, or null if not found
 */
function mpu_get_personality_emoji_script_url($personality_id = null)
{
    $script_path = mpu_get_personality_emoji_script_path($personality_id);
    if ($script_path === null) {
        return null;
    }

    $plugin_dir = plugin_dir_path(MPU_MAIN_FILE);
    $plugin_url = plugin_dir_url(MPU_MAIN_FILE);

    $relative_path = str_replace($plugin_dir, '', $script_path);
    $relative_path = str_replace('\\', '/', $relative_path);

    return $plugin_url . $relative_path;
}

/**
 * Get personality emoji images URL
 * 
 * Detection order:
 * 1. Check manifest.json for 'emoji.folder'
 * 2. Default to 'emojis/' folder
 * 
 * @param string|null $personality_id Personality ID, or null for current
 * @return string|null Emoji folder URL (with trailing slash), or null if not found
 */
function mpu_get_personality_emoji_url($personality_id = null)
{
    if ($personality_id === null) {
        $personality_id = mpu_get_current_personality_id();
    } else {
        $personality_id = mpu_sanitize_personality_id($personality_id);
        if (empty($personality_id)) {
            return null;
        }
    }

    $dir = mpu_get_personalities_dir() . '/' . $personality_id;

    $manifest = mpu_load_personality_manifest($personality_id);
    $folder = $manifest['emoji']['folder'] ?? 'emojis';

    $folder_path = $dir . '/' . $folder;
    if (!is_dir($folder_path)) {
        return null;
    }

    $plugin_url = plugin_dir_url(MPU_MAIN_FILE);
    return $plugin_url . 'ghost/' . $personality_id . '/' . $folder . '/';
}

/**
 * Load personality emoji configuration
 * 
 * Returns the full emoji config from manifest, or auto-detected values.
 * 
 * @param string|null $personality_id Personality ID, or null for current
 * @return array Emoji configuration array with 'script', 'folder', 'supported' keys
 */
function mpu_load_personality_emoji_config($personality_id = null)
{
    if ($personality_id === null) {
        $personality_id = mpu_get_current_personality_id();
    } else {
        $personality_id = mpu_sanitize_personality_id($personality_id);
        if (empty($personality_id)) {
            return [];
        }
    }

    $manifest = mpu_load_personality_manifest($personality_id);

    if (!empty($manifest['emoji'])) {
        return $manifest['emoji'];
    }

    // Auto-detect configuration
    $config = [];

    $script_path = mpu_get_personality_emoji_script_path($personality_id);
    if ($script_path) {
        $config['script'] = basename($script_path);
    }

    $dir = mpu_get_personalities_dir() . '/' . $personality_id;
    if (is_dir($dir . '/emojis')) {
        $config['folder'] = 'emojis';

        $emoji_files = [];
        $extensions = ['png', 'apng', 'gif'];
        foreach ($extensions as $ext) {
            $pattern = $dir . '/emojis/*.' . $ext;
            $files = glob($pattern);
            if ($files !== false) {
                $emoji_files = array_merge($emoji_files, $files);
            }
        }
        if (!empty($emoji_files)) {
            $config['supported'] = array_map(function ($file) {
                return pathinfo($file, PATHINFO_FILENAME);
            }, $emoji_files);
        }
    }

    return $config;
}

/**
 * Load personality emoji keywords configuration
 * 
 * Loads emoji keyword mappings from emoji-keywords.json for the specified personality.
 * 
 * @param string|null $personality_id Personality ID, or null for current
 * @return array Emoji mappings array
 */
function mpu_load_personality_emoji_keywords($personality_id = null)
{
    if ($personality_id === null) {
        $personality_id = mpu_get_current_personality_id();
    } else {
        $personality_id = mpu_sanitize_personality_id($personality_id);
        if (empty($personality_id)) {
            return [];
        }
    }

    $path = mpu_get_personalities_dir() . '/' . $personality_id . '/emoji-keywords.json';
    $data = mpu_load_json_file($path);

    if ($data === null || empty($data['mappings'])) {
        return [];
    }

    return $data['mappings'];
}
