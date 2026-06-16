<?php

/**
 * Personality Prompts: Dynamic prompts and variable substitution
 * 
 * This module handles prompt loading, dynamic prompt templates,
 * and variable substitution for personality-based dialogues.
 * 
 * @package MP_Ukagaka
 * @subpackage Personality
 * @since 2.5.0
 */

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Load personality prompts
 * 
 * @param string|null $personality_id Personality ID, or null for current
 * @return array Prompts array (category => prompts[]), or empty array if not found
 */
function mpu_load_personality_prompts($personality_id = null)
{
    if ($personality_id === null) {
        $personality_id = mpu_get_current_personality_id();
    } else {
        $personality_id = mpu_sanitize_personality_id($personality_id);
        if (empty($personality_id)) {
            return [];
        }
    }

    $path = mpu_get_personalities_dir() . '/' . $personality_id . '/prompts.json';
    $prompts = mpu_load_json_file($path);

    if ($prompts === null) {
        return [];
    }

    // Filter out metadata keys (starting with _)
    $filtered = [];
    foreach ($prompts as $key => $value) {
        if (strpos($key, '_') !== 0 && is_array($value)) {
            $filtered[$key] = $value;
        }
    }

    return $filtered;
}

/**
 * Load personality dynamic prompts
 * 
 * These are prompt templates that use variable placeholders like {time_context}.
 * 
 * @param string|null $personality_id Personality ID, or null for current
 * @return array Dynamic prompts configuration, or empty array if not found
 */
function mpu_load_personality_dynamic_prompts($personality_id = null)
{
    if ($personality_id === null) {
        $personality_id = mpu_get_current_personality_id();
    } else {
        $personality_id = mpu_sanitize_personality_id($personality_id);
        if (empty($personality_id)) {
            return [];
        }
    }

    $path = mpu_get_personalities_dir() . '/' . $personality_id . '/dynamics.json';
    $dynamic = mpu_load_json_file($path);

    return $dynamic ?? [];
}

/**
 * Load personality sleep mode configuration
 * 
 * @param string|null $personality_id Personality ID, or null for current
 * @return array Sleep mode config, or empty array if not found
 */
function mpu_load_personality_sleep_mode($personality_id = null)
{
    if ($personality_id === null) {
        $personality_id = mpu_get_current_personality_id();
    } else {
        $personality_id = mpu_sanitize_personality_id($personality_id);
        if (empty($personality_id)) {
            return [];
        }
    }

    $path = mpu_get_personalities_dir() . '/' . $personality_id . '/sleep_mode.json';
    $sleep_mode = mpu_load_json_file($path);

    return $sleep_mode ?? [];
}

/**
 * 在睡眠時段從角色 sleep_mode.json 抽一條夢話（不呼叫 LLM）
 *
 * 使用情境：事件推送類訊號（auto_talk 推送）想在睡眠時段給「夢話版」回應，
 * 而非用 LLM 生成清醒對話，避免人格設定矛盾且節省 API 成本。
 *
 * 行為：
 *   - 若角色不在 deep_sleep_time 或 sleep_mode 未啟用，回 false（呼叫端應走原本 LLM 路徑）
 *   - 命中時，從指定子類別 + basic 池合併隨機抽一條，附加 <!-- mpu-sleep --> 標記
 *
 * @param string|null $personality_id 角色 ID（null 取當前）
 * @param string      $category       sleep_mode.json 內主類別鍵（如 bot_dreams、visitor_dreams）
 * @param bool        $merge_basic    是否混入 basic 池增加多樣性（預設 true）
 * @return string|false
 */
function mpu_pick_sleep_dream_line($personality_id, $category, $merge_basic = true)
{
    if (!function_exists('mpu_is_deep_sleep_time') || !mpu_is_deep_sleep_time($personality_id)) {
        return false;
    }

    $sleep_config = mpu_load_personality_sleep_mode($personality_id);
    if (empty($sleep_config['enabled'])) {
        return false;
    }

	// 午休時段（白天小睡）優先使用 nap_dreams 池：晚睡的夢話與 visitor/bot 等.
	// 子類別偏夜晚語氣（例：「こんな夜更けに」），不適合白天，故直接以 nap 池取代.
	if (
		function_exists( 'mpu_is_nap_time' ) && mpu_is_nap_time( $personality_id )
		&& ! empty( $sleep_config['nap_dreams'] ) && is_array( $sleep_config['nap_dreams'] )
	) {
		$nap_pool = $sleep_config['nap_dreams'];
		return $nap_pool[ array_rand( $nap_pool ) ] . '<!-- mpu-sleep -->';
	}

    $pool = [];
    if (!empty($sleep_config[$category]) && is_array($sleep_config[$category])) {
        $pool = array_merge($pool, $sleep_config[$category]);
    }
    if ($merge_basic && !empty($sleep_config['basic']) && is_array($sleep_config['basic'])) {
        $pool = array_merge($pool, $sleep_config['basic']);
    }

    if (empty($pool)) {
        return false;
    }

    $line = $pool[array_rand($pool)];
    return $line . '<!-- mpu-sleep -->';
}

/**
 * Pick a wake reaction prompt from sleep_mode.json.
 *
 * This returns an instruction for LLM generation, not a finished dialogue line.
 *
 * Expected schema:
 * {
 *   "wake_reaction_prompts": {
 *     "deep_sleep": ["..."],
 *     "oversleep": ["..."]
 *   }
 * }
 *
 * @param string|null $personality_id Character ID.
 * @param string      $phase          Sleep phase: deep_sleep, oversleep, or nap.
 * @return string|false
 */
function mpu_pick_wake_reaction_prompt($personality_id, $phase)
{
    $phase = sanitize_key($phase);
	if ( ! in_array( $phase, array( 'deep_sleep', 'oversleep', 'nap' ), true ) ) {
        return false;
    }

    $sleep_config = mpu_load_personality_sleep_mode($personality_id);
    if (empty($sleep_config['enabled'])) {
        return false;
    }

    $prompts = $sleep_config['wake_reaction_prompts'][$phase] ?? [];
    if (empty($prompts) || !is_array($prompts)) {
        return false;
    }

    $prompt = $prompts[array_rand($prompts)];
    return is_string($prompt) && trim($prompt) !== '' ? trim($prompt) : false;
}

/**
 * Load personality calendar configuration
 * 
 * Calendar includes holidays, custom anniversaries, and dynamic holidays.
 * 
 * @param string|null $personality_id Personality ID, or null for current
 * @return array Calendar config, or empty array if not found
 */
function mpu_load_personality_calendar($personality_id = null)
{
    if ($personality_id === null) {
        $personality_id = mpu_get_current_personality_id();
    } else {
        $personality_id = mpu_sanitize_personality_id($personality_id);
        if (empty($personality_id)) {
            return [];
        }
    }

    $path = mpu_get_personalities_dir() . '/' . $personality_id . '/calendar.json';
    $calendar = mpu_load_json_file($path);

    if (function_exists('mpu_apply_admin_profile_calendar') && is_array($calendar)) {
        $calendar = mpu_apply_admin_profile_calendar($calendar);
    }

    return $calendar ?? [];
}

/**
 * Load touch zones configuration from personality
 * 
 * @param string|null $personality_id Personality ID, or null for current
 * @return array Touch zones configuration
 */
function mpu_load_personality_touchzones($personality_id = null)
{
    if ($personality_id === null) {
        $personality_id = mpu_get_current_personality_id();
    } else {
        $personality_id = mpu_sanitize_personality_id($personality_id);
        if (empty($personality_id)) {
            return [];
        }
    }

    $path = mpu_get_personalities_dir() . '/' . $personality_id . '/touchzones.json';
    $touchzones = mpu_load_json_file($path);

    return $touchzones ?? [];
}

/**
 * Replace variable placeholders in prompts
 * 
 * Replaces {variable_name} patterns with actual values.
 * 
 * @param string|array $prompts Single prompt string or array of prompts
 * @param array $variables Associative array of variable_name => value
 * @return string|array Prompts with variables replaced
 */
function mpu_replace_prompt_variables($prompts, $variables)
{
    if (is_string($prompts)) {
        return mpu_replace_single_prompt_variables($prompts, $variables);
    }

    if (!is_array($prompts)) {
        return $prompts;
    }

    $result = [];
    foreach ($prompts as $key => $value) {
        if (is_string($value)) {
            $result[$key] = mpu_replace_single_prompt_variables($value, $variables);
        } elseif (is_array($value)) {
            $result[$key] = mpu_replace_prompt_variables($value, $variables);
        } else {
            $result[$key] = $value;
        }
    }

    return $result;
}

/**
 * Replace variables in a single prompt string
 * 
 * @param string $prompt Single prompt string
 * @param array $variables Associative array of variable_name => value
 * @return string Prompt with variables replaced
 */
function mpu_replace_single_prompt_variables($prompt, $variables)
{
    if (empty($variables) || !is_string($prompt)) {
        return $prompt;
    }

    foreach ($variables as $key => $value) {
        if (!is_scalar($value)) {
            if (is_array($value)) {
                $value = implode('、', array_slice($value, 0, 5));
            } else {
                continue;
            }
        }
        $prompt = str_replace('{' . $key . '}', (string)$value, $prompt);
    }

    // 清除未被替換的殘留 {placeholder}，避免 template 語法直接送進 LLM
    $prompt = preg_replace('/\{[a-z_]+\}/', '', $prompt);

    return $prompt;
}

/**
 * Get statistics mappings from dynamic prompts
 * 
 * @param string|null $personality_id Personality ID, or null for current
 * @return array Statistics mappings array
 */
function mpu_get_personality_statistics_mappings($personality_id = null)
{
    $dynamic = mpu_load_personality_dynamic_prompts($personality_id);
    return $dynamic['statistics_mappings'] ?? [];
}

/**
 * Apply dynamic prompts to categories based on context
 * 
 * @param array &$prompt_categories Categories array (by reference)
 * @param array $variables Variable values for template substitution
 * @param string|null $personality_id Personality ID, or null for current
 */
function mpu_apply_dynamic_prompts(&$prompt_categories, $variables, $personality_id = null)
{
    $dynamic = mpu_load_personality_dynamic_prompts($personality_id);

    if (empty($dynamic)) {
        return;
    }

    // Time aware dynamic
    if (!empty($dynamic['time_aware_dynamic']) && is_array($dynamic['time_aware_dynamic'])) {
        foreach ($dynamic['time_aware_dynamic'] as $template) {
            $prompt_categories['time_aware'][] = mpu_replace_single_prompt_variables($template, $variables);
        }
    }

    // Daily life morning (only if time context contains morning)
    $time_context = $variables['time_context'] ?? '';
    if (
        !empty($dynamic['daily_life_morning']) && is_array($dynamic['daily_life_morning']) &&
        (strpos($time_context, '朝') !== false || strpos($time_context, '清晨') !== false)
    ) {
        foreach ($dynamic['daily_life_morning'] as $template) {
            $prompt_categories['daily_life'][] = mpu_replace_single_prompt_variables($template, $variables);
        }
    }

    // Tech observation
    if (!empty($dynamic['tech_observation']) && is_array($dynamic['tech_observation'])) {
        $prompt_categories['tech_observation'] = [];
        foreach ($dynamic['tech_observation'] as $template) {
            $prompt_categories['tech_observation'][] = mpu_replace_single_prompt_variables($template, $variables);
        }
    }

    // Tech observation author
    if (!empty($dynamic['tech_observation_author']) && !empty($variables['theme_author'])) {
        $prompt_categories['tech_observation'][] = mpu_replace_single_prompt_variables(
            $dynamic['tech_observation_author'],
            $variables
        );
    }

    // Plugin prompts
    $plugins_count = $variables['plugins_count'] ?? 0;
    if (!empty($dynamic['plugin_prompts']) && $plugins_count > 0) {
        $pp = $dynamic['plugin_prompts'];

        if (!empty($pp['magic_collection'])) {
            $prompt_categories['magic_collection'][] = mpu_replace_single_prompt_variables($pp['magic_collection'], $variables);
        }
        if (!empty($pp['magic_metaphor'])) {
            $prompt_categories['magic_metaphor'][] = mpu_replace_single_prompt_variables($pp['magic_metaphor'], $variables);
        }
        if (!empty($pp['magic_research'])) {
            $prompt_categories['magic_research'][] = mpu_replace_single_prompt_variables($pp['magic_research'], $variables);
        }

        $plugins_list = $variables['plugins_list'] ?? [];
        if (!empty($plugins_list) && is_array($plugins_list)) {
            $sample_plugins = array_slice($plugins_list, 0, 5);
            $variables['plugins_names_text'] = implode('、', $sample_plugins);

            if (!empty($pp['magic_research_names'])) {
                $prompt_categories['magic_research'][] = mpu_replace_single_prompt_variables($pp['magic_research_names'], $variables);
            }
            if (!empty($pp['magic_collection_names'])) {
                $prompt_categories['magic_collection'][] = mpu_replace_single_prompt_variables($pp['magic_collection_names'], $variables);
            }
        }
    }

    // Bot detection
    $is_bot = $variables['is_bot'] ?? false;
    if (!empty($dynamic['bot_detection']) && is_array($dynamic['bot_detection']) && $is_bot) {
        foreach ($dynamic['bot_detection'] as $template) {
            $prompt_categories['bot_detection'][] = mpu_replace_single_prompt_variables($template, $variables);
        }

        if (!empty($dynamic['bot_demon_related'])) {
            $prompt_categories['demon_related'][] = mpu_replace_single_prompt_variables(
                $dynamic['bot_demon_related'],
                $variables
            );
        }
    }

    // Dungeon prompts
    $pages_viewed = $variables['pages_viewed'] ?? 0;
    if (!empty($dynamic['dungeon_prompts']) && $pages_viewed > 3) {
        $dp = $dynamic['dungeon_prompts'];
        if (!empty($dp['dungeon_expert'])) {
            $prompt_categories['dungeon_expert'][] = $dp['dungeon_expert'];
        }
        if (!empty($dp['mimic_obsession'])) {
            $prompt_categories['mimic_obsession'][] = $dp['mimic_obsession'];
        }
    }

    // Weather prompts（根據天氣情境選擇對應的提示詞）
    $time_context = $variables['time_context'] ?? '';

    // 從 time_context 中解析天氣資訊
    // 格式範例：【天気：晴れ 18°C / 明日：曇り 15~20°C】
    // 合併兩次 preg_match：同時捕捉天氣描述與當前溫度，減少對同一字串的重複匹配
    $weather_match = [];
    if (preg_match('/【天気：(.+?)(\d+)°C/', $time_context, $weather_match)) {
        $current_weather = trim($weather_match[1] ?? '');

        // 晴天系列（快晴、晴れ）
        if (
            !empty($dynamic['weather_sunny']) && is_array($dynamic['weather_sunny']) &&
            (strpos($current_weather, '晴') !== false || strpos($current_weather, '快晴') !== false)
        ) {
            foreach ($dynamic['weather_sunny'] as $template) {
                $prompt_categories['weather'][] = mpu_replace_single_prompt_variables($template, $variables);
            }
        }

        // 陰天系列（曇り、薄曇り）
        if (!empty($dynamic['weather_cloudy']) && is_array($dynamic['weather_cloudy']) && strpos($current_weather, '曇') !== false) {
            foreach ($dynamic['weather_cloudy'] as $template) {
                $prompt_categories['weather'][] = mpu_replace_single_prompt_variables($template, $variables);
            }
        }

        // 雨天系列（雨、小雨、大雨、にわか雨、豪雨）
        if (!empty($dynamic['weather_rainy']) && is_array($dynamic['weather_rainy']) && strpos($current_weather, '雨') !== false) {
            foreach ($dynamic['weather_rainy'] as $template) {
                $prompt_categories['weather'][] = mpu_replace_single_prompt_variables($template, $variables);
            }
        }

        // 雪天系列（雪、小雪、大雪、にわか雪、霰）
        if (
            !empty($dynamic['weather_snowy']) && is_array($dynamic['weather_snowy']) &&
            (strpos($current_weather, '雪') !== false || strpos($current_weather, '霰') !== false)
        ) {
            foreach ($dynamic['weather_snowy'] as $template) {
                $prompt_categories['weather'][] = mpu_replace_single_prompt_variables($template, $variables);
            }
        }

        // 雷雨
        if (!empty($dynamic['weather_stormy']) && is_array($dynamic['weather_stormy']) && strpos($current_weather, '雷') !== false) {
            foreach ($dynamic['weather_stormy'] as $template) {
                $prompt_categories['weather'][] = mpu_replace_single_prompt_variables($template, $variables);
            }
        }

        // 霧系列
        if (!empty($dynamic['weather_foggy']) && is_array($dynamic['weather_foggy']) && strpos($current_weather, '霧') !== false) {
            foreach ($dynamic['weather_foggy'] as $template) {
                $prompt_categories['weather'][] = mpu_replace_single_prompt_variables($template, $variables);
            }
        }
    }

    // 溫度相關提示詞（重用上方合併匹配的第 2 個捕捉群組，不再對同一字串執行第二次 preg_match）
    if (!empty($weather_match[2])) {
        $current_temp = intval($weather_match[2]);

        // 高溫（>= 28°C）— 與 personality.md「温度感覚の基準」一致
        if (!empty($dynamic['weather_hot']) && is_array($dynamic['weather_hot']) && $current_temp >= 28) {
            foreach ($dynamic['weather_hot'] as $template) {
                $prompt_categories['weather'][] = mpu_replace_single_prompt_variables($template, $variables);
            }
        }

        // 低溫（<= 15°C）— 與 personality.md「温度感覚の基準」及 frontend-functions.php cold_complaints 一致
        if (!empty($dynamic['weather_cold']) && is_array($dynamic['weather_cold']) && $current_temp <= 15) {
            foreach ($dynamic['weather_cold'] as $template) {
                $prompt_categories['weather'][] = mpu_replace_single_prompt_variables($template, $variables);
            }
        }
    }

    // Weather Report 類別（天氣預報魔法）
    // 只要有天氣資訊就可以觸發這個類別
    if (!empty($dynamic['weather_report']) && strpos($time_context, '【天気：') !== false) {
        // 解析明日天氣
        $tomorrow_match = [];
        $tomorrow_weather = '不明';
        $umbrella_advice = '分からない';
        $precipitation_probability = 0;
        $precipitation_text = '';

        if (preg_match('/明日：(.+?)[\s\d~°C]+/', $time_context, $tomorrow_match)) {
            $tomorrow_weather = trim($tomorrow_match[1]);
        }

        // 解析明日降水機率（格式：降水30% 或 降雪50%）
        $precip_match = [];
        if (preg_match('/明日：.+?°C\s*(降[水雪])(\d+)%/', $time_context, $precip_match)) {
            $precip_type = $precip_match[1] ?? '降水'; // 降水 或 降雪
            $precipitation_probability = intval($precip_match[2]);
            $precipitation_text = $precip_type . $precipitation_probability . '%';
        }

        // 根據明日天氣和降水機率給出傘的建議（優先級：天氣代碼 > 降水率）
        if (strpos($tomorrow_weather, '雨') !== false) {
            $umbrella_advice = '持っていった方がいい';
        } elseif ($precipitation_probability >= 50) {
            $umbrella_advice = '持っていった方がいい';
        } elseif ($precipitation_probability >= 30) {
            $umbrella_advice = '念のため持っていくといいかも';
        } elseif ($precipitation_probability < 20) {
            $umbrella_advice = 'いらないと思う';
        } else {
            $umbrella_advice = strpos($tomorrow_weather, '曇') !== false ? '念のため持っていくといいかも' : 'いらないと思う';
        }

        // 添加天氣預報專用變數
        $weather_vars = array_merge($variables, [
            'tomorrow_weather' => $tomorrow_weather,
            'umbrella_advice' => $umbrella_advice,
            'precipitation_probability' => $precipitation_probability,
            'precipitation_text' => $precipitation_text,
        ]);

        foreach ($dynamic['weather_report'] as $template) {
            $prompt_categories['weather_report'][] = mpu_replace_single_prompt_variables($template, $weather_vars);
        }
    }
}

/**
 * Apply statistics prompts from personality JSON
 * 
 * @param array &$categories Categories array (by reference)
 * @param array $wp_info WordPress info with statistics
 * @param string|null $personality_id Personality ID, or null for current
 */
function mpu_apply_personality_statistics(&$categories, $wp_info, $personality_id = null)
{
    $mappings = mpu_get_personality_statistics_mappings($personality_id);

    if (empty($mappings)) {
        return;
    }

    foreach ($mappings as $mapping) {
        $key = $mapping['key'] ?? '';
        $category = $mapping['category'] ?? '';
        $templates = $mapping['templates'] ?? [];
        $fallback = $mapping['fallback'] ?? '';

        if (empty($key) || empty($category)) {
            continue;
        }

        $value = $wp_info[$key] ?? 0;
        $variables = ['value' => $value];

        if ($value > 0) {
            foreach ($templates as $template) {
                $categories[$category][] = mpu_replace_single_prompt_variables($template, $variables);
            }

            if (!empty($mapping['extra'])) {
                $extra = $mapping['extra'];
                $extra_category = $extra['category'] ?? '';
                $extra_template = $extra['template'] ?? '';
                if (!empty($extra_category) && !empty($extra_template)) {
                    $categories[$extra_category][] = mpu_replace_single_prompt_variables($extra_template, $variables);
                }
            }
        } elseif (!empty($fallback)) {
            $categories[$category][] = $fallback;
        }
    }
}
