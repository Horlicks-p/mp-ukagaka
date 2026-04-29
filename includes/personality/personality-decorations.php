<?php

/**
 * Personality Decorations: Decoration system functions
 * 
 * This module handles decoration loading and management
 * for personality-based decorative elements.
 * 
 * @package MP_Ukagaka
 * @subpackage Personality
 * @since 2.5.0
 */

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Load personality decorations configuration
 * 
 * @param string|null $personality_id Personality ID, or null for current
 * @return array Decorations configuration, or empty array if not found
 */
function mpu_load_personality_decorations($personality_id = null)
{
    if ($personality_id === null) {
        $personality_id = mpu_get_current_personality_id();
    } else {
        $personality_id = mpu_sanitize_personality_id($personality_id);
        if (empty($personality_id)) {
            return [];
        }
    }

    $path = mpu_get_personalities_dir() . '/' . $personality_id . '/decorations.json';
    $decorations = mpu_load_json_file($path);

    return $decorations ?? [];
}

/**
 * Get decoration prompt for a specific type
 * 
 * Loads from personality JSON if available, falls back to hardcoded.
 * 
 * @param string $decoration_type Decoration type (suitcase, evil_horns, staff, books)
 * @param string|null $personality_id Personality ID, or null for current
 * @return string|false Prompt string, or false if not found
 */
function mpu_get_personality_decoration_prompt($decoration_type, $personality_id = null)
{
    $decorations = mpu_load_personality_decorations($personality_id);

    if (empty($decorations['items']) || !is_array($decorations['items'])) {
        return false;
    }

    foreach ($decorations['items'] as $item) {
        if (isset($item['type']) && $item['type'] === $decoration_type) {
            return $item['prompt'] ?? false;
        }
    }

    return false;
}

/**
 * Get available decoration types from personality
 * 
 * @param string|null $personality_id Personality ID, or null for current
 * @return array Array of decoration type strings
 */
function mpu_get_personality_decoration_types($personality_id = null)
{
    $decorations = mpu_load_personality_decorations($personality_id);

    if (empty($decorations['items']) || !is_array($decorations['items'])) {
        return [];
    }

    $types = [];
    foreach ($decorations['items'] as $item) {
        if (isset($item['type'])) {
            $types[] = $item['type'];
        }
    }

    return $types;
}
