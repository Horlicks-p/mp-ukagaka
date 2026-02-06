<?php

/**
 * Prompt Categories：User Prompt 類別指令管理
 * 
 * 此模組負責管理 LLM 對話生成時使用的類別指令和動態權重配置。
 * 將類別指令集中管理，方便維護和擴展。
 * 
 * 權重和提示詞現在從人格 JSON 檔案載入：
 * - prompts.json: 靜態對話類別
 * - dynamics.json: 動態模板（含變數）
 * - weights.json: 類別權重和時段調整
 * - decorations.json: 裝飾品點擊提示詞
 * 
 * @package MP_Ukagaka
 * @subpackage LLM
 */

if (!defined('ABSPATH')) {
    exit();
}

/**
 * 獲取靜態類別指令（使用快取避免重複建構）
 * 
 * 從 JSON 人格檔案載入對話類別。
 * 若 JSON 載入失敗，返回空陣列並記錄錯誤。
 * 
 * @return array 靜態類別指令陣列
 */
function mpu_get_static_prompt_categories($personality_id = null)
{
    // 如果指定了 personality_id，不使用靜態快取（因為不同 personality 需要不同的提示詞）
    if ($personality_id !== null) {
        // 從 JSON 載入（personality-loader.php）
        if (function_exists('mpu_load_personality_prompts')) {
            $json_prompts = mpu_load_personality_prompts($personality_id);

            if (!empty($json_prompts)) {
                return $json_prompts;
            }
        }

        // JSON 載入失敗：記錄錯誤並返回空陣列
        if (function_exists('mpu_debug_log')) {
            mpu_debug_log('[MP Ukagaka] 無法從 JSON 載入人格提示詞 (personality_id: ' . $personality_id . ')，請檢查 ghost/ 資料夾');
        } else {
            mpu_log_error('無法從 JSON 載入人格提示詞 (personality_id: ' . $personality_id . ')，請檢查 ghost/ 資料夾');
        }
        return [];
    }

    // 沒有指定 personality_id 時使用靜態快取（向後兼容）
    static $static_categories = null;

    if ($static_categories !== null) {
        return $static_categories;
    }

    // 從 JSON 載入（personality-loader.php）
    if (function_exists('mpu_load_personality_prompts')) {
        $json_prompts = mpu_load_personality_prompts();

        if (!empty($json_prompts)) {
            $static_categories = $json_prompts;
            return $static_categories;
        }
    }

    // JSON 載入失敗：記錄錯誤並返回空陣列
    if (function_exists('mpu_debug_log')) {
        mpu_debug_log('[MP Ukagaka] 無法從 JSON 載入人格提示詞，請檢查 ghost/ 資料夾');
    } else {
        mpu_log_error('無法從 JSON 載入人格提示詞，請檢查 ghost/ 資料夾');
    }
    $static_categories = [];
    return $static_categories;
}

/**
 * 添加動態統計類別指令
 * 
 * 從人格 JSON 載入統計映射配置，並根據 WordPress 資訊動態生成提示詞。
 * 
 * @param array $categories 類別陣列（引用傳遞）
 * @param array $wp_info WordPress 資訊
 */
function mpu_add_statistics_prompts(&$categories, $wp_info, $personality_id = null)
{
    // 優先從 JSON 載入
    if (function_exists('mpu_apply_personality_statistics')) {
        mpu_apply_personality_statistics($categories, $wp_info, $personality_id);
        return;
    }

    // 如果 personality-loader 不可用，則不添加任何統計提示
    mpu_log_error('mpu_apply_personality_statistics function not available');
}

/**
 * 建構 User Prompt 的類別指令
 * 
 * 此函數生成不同類別的對話指令，用於「使用 LLM 取代內建對話」功能。
 * 這些指令會與實際的用戶/訪客/網站資訊一起組成 User Prompt，提供上下文並引導 LLM 生成對應類型的對話。
 * 
 * 動態提示詞從人格 JSON 檔案載入（dynamics.json）。
 * 
 * @param array $wp_info WordPress 資訊
 * @param array $visitor_info 訪客資訊
 * @param string $time_context 時間情境
 * @param string $theme_name 主題名稱
 * @param string $theme_version 主題版本
 * @param string $theme_author 主題作者
 * @return array 類別指令陣列
 */
function mpu_build_prompt_categories(
    $wp_info,
    $visitor_info,
    $time_context,
    $theme_name,
    $theme_version,
    $theme_author,
    $personality_id = null
) {
    // 從快取獲取靜態類別
    $prompt_categories = mpu_get_static_prompt_categories($personality_id);

    // 使用 JSON 動態提示詞（如果可用）
    if (function_exists('mpu_apply_dynamic_prompts')) {
        // 構建變數陣列
        $variables = [
            'wp_version' => $wp_info['wp_version'] ?? '',
            'php_version' => $wp_info['php_version'] ?? '',
            'plugins_count' => $wp_info['active_plugins_count'] ?? 0,
            'plugins_list' => $wp_info['active_plugins_list'] ?? [],
            'theme_name' => $theme_name,
            'theme_version' => $theme_version,
            'theme_author' => $theme_author,
            'time_context' => $time_context,
            'is_bot' => !empty($visitor_info['is_bot']) && $visitor_info['is_bot'] === true,
            'bot_name' => $visitor_info['browser_name'] ?? '未知のクローラー',
            'pages_viewed' => $visitor_info['pages_viewed'] ?? 0,
        ];

        // 應用動態提示詞（傳遞 personality_id）
        mpu_apply_dynamic_prompts($prompt_categories, $variables, $personality_id);
    }

    // 添加動態統計指令（傳遞 personality_id）
    mpu_add_statistics_prompts($prompt_categories, $wp_info, $personality_id);

    /**
     * Filter: mpu_prompt_categories
     * 允許其他開發者擴展或修改類別指令
     * 
     * @param array $prompt_categories 類別指令陣列
     * @param array $wp_info WordPress 資訊
     * @param array $visitor_info 訪客資訊
     * @param string $time_context 時間情境
     */
    return apply_filters('mpu_prompt_categories', $prompt_categories, $wp_info, $visitor_info, $time_context);
}

/**
 * 獲取動態類別權重配置
 * 
 * 從人格 JSON 檔案（weights.json）載入權重配置，
 * 並根據時間情境、訪客資訊和上下文變數動態調整。
 * 
 * @param string $time_context 時間情境（如「春の朝」）
 * @param array $visitor_info 訪客資訊
 * @param array $context_vars 上下文變數（可選）
 * @param string|null $personality_id Personality ID（可選）
 * @return array 權重陣列
 */
function mpu_get_dynamic_category_weights($time_context, $visitor_info, $context_vars = [], $personality_id = null)
{
    // 從 JSON 載入權重配置
    $weights = [];
    $time_adjustments = [];

    if (function_exists('mpu_get_personality_base_weights')) {
        $weights = mpu_get_personality_base_weights($personality_id);
    }
    if (function_exists('mpu_get_personality_time_adjustments')) {
        $time_adjustments = mpu_get_personality_time_adjustments($personality_id);
    }

    // 如果無法載入 JSON 權重，返回空陣列
    if (empty($weights)) {
        if (function_exists('mpu_debug_log')) {
            mpu_debug_log('[MP Ukagaka] 無法從 JSON 載入權重配置');
        } else {
            mpu_log_error('無法從 JSON 載入權重配置');
        }
        return [];
    }

    // 提取時間段（從 time_context 中提取，如「春の朝」→「朝」）
    // 注意：time_context 格式可能是「1月2日（木曜日）・冬の朝」或「1月2日（木曜日）・冬の朝【節日名】」
    // 需要提取「朝」而不是「朝【節日名】」
    $time_period = '';
    if (preg_match('/の([^【]+)/', $time_context, $matches)) {
        $time_period = trim($matches[1]);
    } elseif (preg_match('/の(.+)$/', $time_context, $matches)) {
        // 後備方案：如果沒有【節日名】，使用原來的正則
        $time_period = trim($matches[1]);
    }

    // 使用映射表進行時段調整
    if (!empty($time_adjustments[$time_period])) {
        $weights = array_merge($weights, $time_adjustments[$time_period]);
    }

    // 睡眠時間帶特殊處理（00:00~06:00）：用睡眠時間帶權重覆蓋
    // ★ 傳入 personality_id 以正確判斷該角色的睡眠狀態
    if (function_exists('mpu_is_deep_sleep_time') && mpu_is_deep_sleep_time($personality_id)) {
        if (isset($time_adjustments['睡眠時間帯'])) {
            $weights = array_merge($weights, $time_adjustments['睡眠時間帯']);
        }
    }

    // 訪客狀態調整
    if (!empty($context_vars)) {
        // 首次訪問：好奇但淡々とした態度
        if (!empty($context_vars['is_first_visit'])) {
            $weights['greeting'] = 15;
            $weights['observation'] = 18;
            $weights['curiosity'] = 10;
            $weights['human_observation'] = 12;
        }

        // 常客：親しみを込めてからかう
        if (!empty($context_vars['is_frequent_visitor'])) {
            $weights['admin_comment'] = 15;
            $weights['casual'] = 18;
            $weights['human_observation'] = 12;
            $weights['frieren_humor'] = 10;
        }

        // 週末：リラックスした雰囲気
        if (!empty($context_vars['is_weekend'])) {
            $weights['casual'] = 18;
            $weights['frieren_humor'] = 10;
            $weights['daily_life'] = 8;
            $weights['food_preference'] = 10;
            $weights['mimic_obsession'] = 8;
        }

        // 長時間滯在：ダンジョン探索的な雰囲気
        if (!empty($context_vars['long_session'])) {
            $weights['dungeon_expert'] = 12;
            $weights['mimic_obsession'] = 10;
            $weights['journey_adventure'] = 10;
        }

        // 長時間未訪問（超過 24 小時）：「久しぶり」系問候
        if (!empty($context_vars['is_long_absence'])) {
            $weights['greeting_long_absence'] = 25;
            $weights['greeting'] = 3; // 降低普通問候權重
            $weights['curiosity'] = 10;
            $weights['human_observation'] = 12;
        }

        // 近期有訪問（24 小時內）：常客問候
        if (!empty($context_vars['is_recent_visit'])) {
            $weights['greeting_regular'] = 15;
            $weights['greeting_long_absence'] = 0;
            $weights['casual'] = 18;
            $weights['admin_comment'] = 10;
        }
    }

    // BOT 檢測調整：魔族に例える（★ 高優先度：幾乎確定會觸發 BOT 對話）
    if (!empty($visitor_info['is_bot']) && $visitor_info['is_bot'] === true) {
        // 先把所有權重壓低，確保 BOT 對話優先
        foreach ($weights as $key => $value) {
            $weights[$key] = max(1, intval($value * 0.1)); // 降到原本的 10%
        }
        // 設定 BOT 相關類別的高權重
        $weights['bot_detection'] = 80;  // 主要優先
        $weights['demon_related'] = 15;  // 次要
        $weights['observation'] = 5;     // 最低
    }

    // 季節調整（從 JSON 載入 seasonal_adjustments）
    if (function_exists('mpu_get_personality_seasonal_adjustments')) {
        $seasonal_adjustments = mpu_get_personality_seasonal_adjustments($personality_id);
        
        if (!empty($seasonal_adjustments)) {
            // 檢測季節（春、夏、秋、冬）
            foreach (['春', '夏', '秋', '冬'] as $season) {
                if (strpos($time_context, $season) !== false && !empty($seasonal_adjustments[$season])) {
                    $weights = array_merge($weights, $seasonal_adjustments[$season]);
                    break;
                }
            }
        }
    }

    // 天氣調整（從 JSON 載入 weather_adjustments）
    if (function_exists('mpu_get_personality_weather_adjustments')) {
        $weather_adjustments = mpu_get_personality_weather_adjustments($personality_id);
        
        if (!empty($weather_adjustments)) {
            // 從 time_context 中解析天氣資訊
            // 格式：【天気：晴れ 18°C / 明日：曇り 15~20°C】
            if (preg_match('/【天気：(.+?)[\s\d°C]+/', $time_context, $weather_match)) {
                $current_weather = $weather_match[1] ?? '';
                
                // 匹配天氣條件
                foreach ($weather_adjustments as $weather_key => $adjustment) {
                    if ($weather_key === '_comment') continue;
                    
                    // 檢查天氣關鍵字是否在當前天氣中
                    if (strpos($current_weather, $weather_key) !== false) {
                        $weights = array_merge($weights, $adjustment);
                    }
                }
            }
        }
    }

    // 節日調整：檢測當天是否為節日，使用 calendar.json 配置
    if (function_exists('mpu_get_holiday_config')) {
        // 從 time_context 中提取月份和日期
        // 格式：「1月2日（木曜日）・冬の朝【節日名】」
        if (preg_match('/(\d+)月(\d+)日/', $time_context, $date_matches)) {
            $month = (int) $date_matches[1];
            $day = (int) $date_matches[2];
            $holiday_config = mpu_get_holiday_config($month, $day, $personality_id);

            if ($holiday_config !== null) {
                $holiday_name = $holiday_config['name'] ?? '';
                $holiday_prompts = $holiday_config['prompts'] ?? ['holiday'];

                // 使用節日專屬的 prompt 類別（從 calendar.json）
                foreach ($holiday_prompts as $prompt_category) {
                    $weights[$prompt_category] = 30; // 最高優先級
                }

                // 同時保持通用 holiday 類別的權重
                $weights['holiday'] = 25;
                $weights['seasonal_events'] = 15;

                // 從 weights.json 的 holiday_adjustments 載入額外調整
                if (function_exists('mpu_get_personality_holiday_adjustments')) {
                    $holiday_adjustments = mpu_get_personality_holiday_adjustments($personality_id);

                    // 1. 應用 default 調整
                    if (!empty($holiday_adjustments['default'])) {
                        $weights = array_merge($weights, $holiday_adjustments['default']);
                    }

                    // 2. 應用特定節日調整
                    if (!empty($holiday_adjustments[$holiday_name])) {
                        $weights = array_merge($weights, $holiday_adjustments[$holiday_name]);
                    }
                }
            }
        }
    }

    /**
     * Filter: mpu_category_weights
     * 允許其他開發者調整類別權重
     * 
     * @param array $weights 權重陣列
     * @param string $time_context 時間情境
     * @param array $visitor_info 訪客資訊
     * @param array $context_vars 上下文變數
     */
    return apply_filters('mpu_category_weights', $weights, $time_context, $visitor_info, $context_vars);
}

/**
 * 獲取裝飾品點擊對話的提示詞
 * 
 * 從人格 JSON 檔案（decorations.json）載入裝飾品提示詞。
 * 
 * @param string $decoration_type 裝飾物類型
 * @return string|false 提示詞，若未找到則返回 false
 */
function mpu_get_decoration_prompt($decoration_type, $personality_id = null)
{
    // 從 JSON 載入
    if (function_exists('mpu_get_personality_decoration_prompt')) {
        $json_prompt = mpu_get_personality_decoration_prompt($decoration_type, $personality_id);
        if ($json_prompt !== false && !empty($json_prompt)) {
            return $json_prompt;
        }
    }

    // 如果沒有 JSON 資料，返回 false
    if (function_exists('mpu_debug_log')) {
        mpu_debug_log('[MP Ukagaka] Decoration prompt not found for type: ' . $decoration_type);
    } else {
        mpu_log_warning('Decoration prompt not found for type: ' . $decoration_type);
    }
    return false;
}

/**
 * 獲取所有可用的裝飾品類型
 * 
 * 從人格 JSON 檔案（decorations.json）載入可用的裝飾品類型。
 * 
 * @return array 裝飾品類型陣列
 */
function mpu_get_available_decoration_types($personality_id = null)
{
    // 從 JSON 載入
    if (function_exists('mpu_get_personality_decoration_types')) {
        $types = mpu_get_personality_decoration_types($personality_id);
        if (!empty($types)) {
            return $types;
        }
    }

    // 如果沒有 JSON 資料，返回空陣列
    return [];
}
