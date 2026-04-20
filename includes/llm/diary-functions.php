<?php

/**
 * 日記功能模組
 * 
 * 自動日記功能（フリーレン手記）的核心邏輯
 * 包含日記生成、發佈和 WP Cron 排程
 * 
 * @package MP_Ukagaka
 * @subpackage Diary
 */

if (!defined('ABSPATH')) {
    exit();
}

/**
 * 獲取日記專用的 AI 供應商設定
 * 
 * 如果日記有設定專用的 API Key，使用日記的設定
 * 否則回退到 LLM 設定頁面的設定
 * 
 * @return array|false 設定陣列，未啟用或設定不完整時返回 false
 */
function mpu_diary_get_provider_settings()
{
    $mpu_opt = mpu_get_option();

    if (empty($mpu_opt['diary_enabled'])) {
        return false;
    }

    $provider = isset($mpu_opt['diary_provider']) ? $mpu_opt['diary_provider'] : 'gemini';

    $settings = [
        'provider' => $provider,
    ];

    switch ($provider) {
        case 'gemini':
            // 優先使用日記專用 API Key，否則使用 LLM 設定
            $api_key = !empty($mpu_opt['diary_gemini_api_key'])
                ? $mpu_opt['diary_gemini_api_key']
                : (!empty($mpu_opt['llm_gemini_api_key']) ? $mpu_opt['llm_gemini_api_key'] : ($mpu_opt['ai_api_key'] ?? ''));

            if (empty($api_key)) {
                return false;
            }

            $settings['api_key'] = mpu_decrypt_api_key($api_key);
            $settings['model'] = isset($mpu_opt['diary_gemini_model']) ? $mpu_opt['diary_gemini_model'] : 'gemini-2.5-flash';
            break;

        case 'openai':
            $api_key = !empty($mpu_opt['diary_openai_api_key'])
                ? $mpu_opt['diary_openai_api_key']
                : (!empty($mpu_opt['llm_openai_api_key']) ? $mpu_opt['llm_openai_api_key'] : ($mpu_opt['openai_api_key'] ?? ''));

            if (empty($api_key)) {
                return false;
            }

            $settings['api_key'] = mpu_decrypt_api_key($api_key);
            $settings['model'] = isset($mpu_opt['diary_openai_model']) ? $mpu_opt['diary_openai_model'] : 'gpt-4.1-mini-2025-04-14';
            break;

        case 'claude':
            $api_key = !empty($mpu_opt['diary_claude_api_key'])
                ? $mpu_opt['diary_claude_api_key']
                : (!empty($mpu_opt['llm_claude_api_key']) ? $mpu_opt['llm_claude_api_key'] : ($mpu_opt['claude_api_key'] ?? ''));

            if (empty($api_key)) {
                return false;
            }

            $settings['api_key'] = mpu_decrypt_api_key($api_key);
            $settings['model'] = isset($mpu_opt['diary_claude_model']) ? $mpu_opt['diary_claude_model'] : 'claude-sonnet-4-6';
            break;

        case 'ollama':
            $endpoint = !empty($mpu_opt['diary_ollama_endpoint'])
                ? $mpu_opt['diary_ollama_endpoint']
                : ($mpu_opt['ollama_endpoint'] ?? 'http://localhost:11434');

            $model = !empty($mpu_opt['diary_ollama_model'])
                ? $mpu_opt['diary_ollama_model']
                : ($mpu_opt['ollama_model'] ?? 'qwen3:8b');

            $settings['endpoint'] = $endpoint;
            $settings['model'] = $model;
            break;

        default:
            return false;
    }

    return $settings;
}

/**
 * 判斷今日是否應觸發日記
 * 
 * 基於設定的觸發機率（1~10%）進行隨機判斷
 * 每日最多觸發一次
 * 
 * @return bool 是否應觸發日記
 */
function mpu_should_trigger_diary()
{
    $mpu_opt = mpu_get_option();

    if (empty($mpu_opt['diary_enabled'])) {
        return false;
    }

    // 檢查今日是否已經發表過
    $last_diary_date = get_option('mpu_last_diary_date', '');
    $today = current_time('Y-m-d');

    if ($last_diary_date === $today) {
        return false; // 今日已發表，不再觸發
    }

    // 獲取觸發機率（1~10%）
    $trigger_rate = isset($mpu_opt['diary_trigger_rate']) ? intval($mpu_opt['diary_trigger_rate']) : 2;
    $trigger_rate = max(1, min(10, $trigger_rate));

    // 隨機判斷是否觸發
    $random = wp_rand(1, 100);

    return $random <= $trigger_rate;
}

/**
 * 載入日記設定檔案
 * 
 * @param string|null $personality_id 人格 ID，null 時使用當前人格
 * @return array|null 日記設定陣列，失敗時返回 null
 */
function mpu_load_diary_config($personality_id = null)
{
    static $cache = [];

    if ($personality_id === null && function_exists('mpu_get_current_personality_id')) {
        $personality_id = mpu_get_current_personality_id();
    }

    if (isset($cache[$personality_id])) {
        return $cache[$personality_id];
    }

    $personalities_dir = function_exists('mpu_get_personalities_dir') 
        ? mpu_get_personalities_dir() 
        : plugin_dir_path(dirname(dirname(__FILE__))) . 'ghost';

    $diary_path = $personalities_dir . '/' . $personality_id . '/diary.json';

    if (!file_exists($diary_path) || !is_readable($diary_path)) {
        $cache[$personality_id] = null;
        return null;
    }

    $content = file_get_contents($diary_path);
    if ($content === false) {
        $cache[$personality_id] = null;
        return null;
    }

    $data = json_decode($content, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        mpu_log_error('[Diary] JSON parse error in ' . $diary_path . ': ' . json_last_error_msg());
        $cache[$personality_id] = null;
        return null;
    }

    $cache[$personality_id] = $data;
    return $data;
}

/**
 * 獲取日記標題前綴（含尾部空格）
 * 
 * 優先順序：
 * 1. dynamics.json 的 diary_title_prefix
 * 2. 從 manifest.json 的角色名構建預設值
 * 3. 通用預設值 [手記]
 * 
 * @param string|null $personality_id 人格 ID，null 時使用當前人格
 * @param bool $with_space 是否在前綴後加空格（預設 true，用於 WordPress slug 生成）
 * @return string 日記標題前綴
 */
function mpu_get_diary_title_prefix($personality_id = null, $with_space = true)
{
    // 獲取 personality_id
    if ($personality_id === null && function_exists('mpu_get_current_personality_id')) {
        $personality_id = mpu_get_current_personality_id();
    }
    
    $prefix = '';
    
    // 1. 優先從 dynamics.json 讀取
    if (function_exists('mpu_load_personality_dynamic_prompts')) {
        $dynamic_prompts = mpu_load_personality_dynamic_prompts($personality_id);
        if (!empty($dynamic_prompts['diary_title_prefix'])) {
            $prefix = $dynamic_prompts['diary_title_prefix'];
        }
    }
    
    // 2. 如果沒有設定，從 manifest.json 的角色名構建
    if (empty($prefix) && function_exists('mpu_load_personality_manifest')) {
        $manifest = mpu_load_personality_manifest($personality_id);
        $character_name = $manifest['name'] ?? $manifest['name_en'] ?? $personality_id ?? '';
        if (!empty($character_name)) {
            $prefix = '[' . $character_name . '手記]';
        }
    }
    
    // 3. 最終後備
    if (empty($prefix)) {
        $prefix = '[手記]';
    }
    
    // 在前綴後加空格（讓 WordPress slug 生成時有分隔符）
    if ($with_space) {
        $prefix .= ' ';
    }
    
    return $prefix;
}

/**
 * 基於權重隨機選擇日記類別
 * 
 * @param array $categories 類別設定陣列
 * @return array|null [category_key, category_data] 或 null
 */
function mpu_select_diary_category_by_weight($categories)
{
    if (empty($categories) || !is_array($categories)) {
        return null;
    }

    // 計算總權重
    $total_weight = 0;
    foreach ($categories as $key => $category) {
        if (isset($category['weight']) && is_numeric($category['weight'])) {
            $total_weight += intval($category['weight']);
        }
    }

    if ($total_weight <= 0) {
        // 如果沒有權重設定，隨機選擇
        $keys = array_keys($categories);
        $random_key = $keys[array_rand($keys)];
        return [$random_key, $categories[$random_key]];
    }

    // 加權隨機選擇
    $random = wp_rand(1, $total_weight);
    $cumulative = 0;

    foreach ($categories as $key => $category) {
        $weight = isset($category['weight']) ? intval($category['weight']) : 0;
        $cumulative += $weight;

        if ($random <= $cumulative) {
            return [$key, $category];
        }
    }

    // 後備：返回第一個
    $first_key = array_key_first($categories);
    return [$first_key, $categories[$first_key]];
}

/**
 * 根據類別生成日記標題
 * 
 * @param string $category_key 類別鍵名
 * @param array $category_data 類別資料
 * @return string 生成的標題
 */
function mpu_generate_diary_title_by_category($category_key, $category_data)
{
    // 從類別資料中獲取標題主題
    $title_themes = isset($category_data['title_themes']) && is_array($category_data['title_themes'])
        ? $category_data['title_themes']
        : ['日記'];

    $theme = $title_themes[array_rand($title_themes)];

    // 使用統一的前綴獲取函數
    $prefix = mpu_get_diary_title_prefix();
    return "{$prefix}{$theme}";
}

/**
 * 生成日記專用提示詞（帶類別資訊）
 * 
 * @param string|null $personality_id 人格 ID，null 時使用當前人格
 * @return array ['prompt' => 系統提示詞, 'category' => 類別鍵名, 'category_data' => 類別資料]
 */
function mpu_get_diary_prompt($personality_id = null)
{
    // 獲取人格 ID
    if ($personality_id === null && function_exists('mpu_get_current_personality_id')) {
        $personality_id = mpu_get_current_personality_id();
    }

    // 獲取天氣上下文（如果啟用）
    $weather_context = '';
    if (function_exists('mpu_get_weather_context')) {
        $weather_context = mpu_get_weather_context();
    }

    // 獲取當前時間資訊
    $current_time = current_time('mysql');
    $date_info = wp_date('Y年n月j日（l）', strtotime($current_time));
    $month = intval(wp_date('n', strtotime($current_time)));

    // 季節判斷
    $season = '';
    if ($month >= 3 && $month <= 5) {
        $season = '春';
    } elseif ($month >= 6 && $month <= 8) {
        $season = '夏';
    } elseif ($month >= 9 && $month <= 11) {
        $season = '秋';
    } else {
        $season = '冬';
    }

    // 載入 diary.json 設定
    $diary_config = mpu_load_diary_config($personality_id);
    $selected_category = null;
    $category_key = null;
    $selected_prompt = '';

    if (!empty($diary_config['categories'])) {
        // 使用加權隨機選擇類別
        $result = mpu_select_diary_category_by_weight($diary_config['categories']);
        if ($result !== null) {
            list($category_key, $selected_category) = $result;

            // 從類別中隨機選擇提示詞
            if (!empty($selected_category['prompts']) && is_array($selected_category['prompts'])) {
                $selected_prompt = $selected_category['prompts'][array_rand($selected_category['prompts'])];
            }
        }
    }

    // 如果沒有從 diary.json 獲取到，嘗試從 dynamics.json 獲取
    if (empty($selected_prompt)) {
        if (function_exists('mpu_load_personality_dynamic_prompts')) {
            $dynamics = mpu_load_personality_dynamic_prompts($personality_id);
            if (!empty($dynamics['diary_prompts']) && is_array($dynamics['diary_prompts'])) {
                $selected_prompt = $dynamics['diary_prompts'][array_rand($dynamics['diary_prompts'])];
            }
        }
    }

    // 如果仍然沒有，使用通用預設
    if (empty($selected_prompt)) {
        $default_prompts = [
            "今日の天気や季節の変化について短い日記（約150〜250字）を書いてください。",
            "日常の何気ない出来事について日記（約150〜250字）を書いてください。",
        ];
        $selected_prompt = $default_prompts[array_rand($default_prompts)];
    }

    // 載入角色背景（僅 personality.md，避免 instructions.md 的 50 字限制影響日記長度）
    $base_prompt = '';
    $personality_path = mpu_get_personalities_dir() . '/' . $personality_id . '/personality.md';
    if (file_exists($personality_path) && is_readable($personality_path)) {
        $content = file_get_contents($personality_path);
        if ($content !== false) {
            $base_prompt = $content;
        }
    }
    // Fallback：舊式 system_prompt.md / manifest.json
    if (empty($base_prompt) && function_exists('mpu_load_personality_system_prompt')) {
        $loaded_prompt = mpu_load_personality_system_prompt($personality_id);
        if ($loaded_prompt !== false) {
            $base_prompt = $loaded_prompt;
        }
    }

    // 如果沒有載入成功，從 manifest.json 獲取角色名稱建構基本提示詞
    if (empty($base_prompt)) {
        $character_name = 'キャラクター';
        if (function_exists('mpu_load_personality_manifest')) {
            $manifest = mpu_load_personality_manifest($personality_id);
            $character_name = $manifest['name'] ?? $manifest['name_en'] ?? $personality_id ?? 'キャラクター';
        }
        $base_prompt = "あなたは「{$character_name}」というキャラクターです。\n";
        $base_prompt .= "キャラクターらしい口調と視点で話してください。";
    }

    // 構建完整的系統提示詞
    $system_prompt = $base_prompt . "\n\n";

    if (!empty($weather_context) && $category_key === 'daily_observation') {
        $system_prompt .= "現在の天気情報：" . $weather_context . "\n\n";
    }

    $system_prompt .= "今日は" . $date_info . "です。季節は" . $season . "です。\n\n";
    $system_prompt .= $selected_prompt;

    return [
        'prompt' => $system_prompt,
        'category' => $category_key,
        'category_data' => $selected_category
    ];
}

/**
 * 解析 JSON 格式的日記回應
 * 
 * @param string $response AI 回應
 * @return array ['title' => 標題, 'content' => 內容, 'success' => 是否成功]
 */
function mpu_parse_diary_json($response)
{
    // 清理可能的包裹符號（```json ... ```）
    $cleaned = preg_replace('/```json\s*|\s*```/u', '', $response);
    $cleaned = trim($cleaned);

    // 嘗試解析 JSON
    $data = json_decode($cleaned, true);

    if (json_last_error() === JSON_ERROR_NONE) {
        if (isset($data['title']) && isset($data['content'])) {
            return [
                'title' => trim($data['title']),
                'content' => trim($data['content']),
                'success' => true
            ];
        }
    }

    // Fallback：嘗試正則提取
    if (preg_match('/"title"\s*:\s*"([^"]+)"/u', $cleaned, $title_match) &&
        preg_match('/"content"\s*:\s*"([\s\S]+?)(?:"\s*}|"\s*,\s*")/u', $cleaned, $content_match)) {
        return [
            'title' => trim($title_match[1]),
            'content' => trim($content_match[1]),
            'success' => true
        ];
    }

    // 最後的 Fallback：使用原始回應作為內容
    if (defined('WP_DEBUG') && WP_DEBUG) {
        mpu_debug_log('[Diary] JSON 解析失敗，使用 fallback');
    }

    return [
        'title' => null, // 讓後續處理決定標題
        'content' => $response,
        'success' => false
    ];
}

/**
 * 使用 LLM 生成日記內容（含標題）
 * 
 * @return array|WP_Error 成功時返回 ['title' => 標題, 'content' => 內容, 'category' => 類別]，失敗時返回錯誤
 */
function mpu_generate_diary_content()
{
    $provider_settings = mpu_diary_get_provider_settings();

    if ($provider_settings === false) {
        return new WP_Error('diary_not_configured', __('日記機能が正しく設定されていません', 'mp-ukagaka'));
    }

    // 獲取提示詞和類別資訊
    $prompt_data = mpu_get_diary_prompt();
    $system_prompt = $prompt_data['prompt'];
    $category_key = $prompt_data['category'];
    $category_data = $prompt_data['category_data'];

    // 要求 AI 以 JSON 格式輸出標題和內容
    $user_prompt = "ブログに投稿する日記を書いてください。\n\n" .
                   "【出力形式】\n" .
                   "以下のJSON形式で出力してください：\n\n" .
                   "{\n" .
                   "  \"title\": \"5〜8文字の簡潔なタイトル\",\n" .
                   "  \"content\": \"150〜250字の本文\"\n" .
                   "}\n\n" .
                   "【重要】\n" .
                   "- JSONのみを出力してください\n" .
                   "- 説明文は不要です\n" .
                   "- titleは本文の内容に最も適したものを選んでください";

    // 使用統一的 AI API 調用函數
    if (!function_exists('mpu_call_ai_api')) {
        return new WP_Error('ai_function_missing', __('AI 機能モジュールが読み込まれていません', 'mp-ukagaka'));
    }

    // 構建選項陣列供 mpu_call_ai_api() 使用
    // mpu_call_ai_api() 讀取 llm_*_model 和 ollama_* key
    $api_opt = [
        'llm_gemini_model' => $provider_settings['model'] ?? 'gemini-2.5-flash',
        'llm_openai_model' => $provider_settings['model'] ?? 'gpt-4.1-mini-2025-04-14',
        'llm_claude_model' => $provider_settings['model'] ?? 'claude-sonnet-4-6',
        'ollama_endpoint'  => $provider_settings['endpoint'] ?? 'http://localhost:11434',
        'ollama_model'     => $provider_settings['model'] ?? 'qwen3:8b',
    ];

    $api_key = $provider_settings['api_key'] ?? '';

    // 使用 AI 設定頁面的語言設定
    $mpu_opt = mpu_get_option();
    $language = $mpu_opt['ai_language'] ?? 'ja';

    $result = mpu_call_ai_api(
        $provider_settings['provider'],
        $api_key,
        $system_prompt,
        $user_prompt,
        $language,
        $api_opt,
        2048
    );

    if (is_wp_error($result)) {
        return $result;
    }

    // 解析 JSON 回應
    $parsed = mpu_parse_diary_json($result);

    return [
        'title' => $parsed['title'],
        'content' => $parsed['content'],
        'category' => $category_key,
        'category_data' => $category_data,
        'parse_success' => $parsed['success']
    ];
}

/**
 * 發表日記文章到 WordPress
 * 
 * @param array $diary_data 日記資料 ['content' => 內容, 'category' => 類別鍵名, 'category_data' => 類別資料]
 * @return int|WP_Error 成功時返回文章 ID，失敗時返回錯誤
 */
function mpu_publish_diary_post($diary_data)
{
    $mpu_opt = mpu_get_option();

    // 獲取設定
    $category_id = isset($mpu_opt['diary_category']) ? intval($mpu_opt['diary_category']) : 0;
    $author_id = isset($mpu_opt['diary_author']) ? intval($mpu_opt['diary_author']) : get_current_user_id();
    $signature = isset($mpu_opt['diary_signature']) ? $mpu_opt['diary_signature'] : '';

    // 處理輸入資料
    $content = is_array($diary_data) ? ($diary_data['content'] ?? '') : $diary_data;
    $ai_title = is_array($diary_data) ? ($diary_data['title'] ?? null) : null;
    $diary_category_key = is_array($diary_data) ? ($diary_data['category'] ?? null) : null;
    $diary_category_data = is_array($diary_data) ? ($diary_data['category_data'] ?? null) : null;

    // 如果有簽名，附加到內容末尾
    if (!empty($signature)) {
        $content .= "\n\n" . $signature;
    }

    // 標題優先使用 AI 生成的標題
    if (!empty($ai_title)) {
        // 使用統一的前綴獲取函數
        $prefix = mpu_get_diary_title_prefix();
        $title = $prefix . $ai_title;
    } elseif ($diary_category_key !== null && $diary_category_data !== null) {
        // 後備：使用類別標題
        $title = mpu_generate_diary_title_by_category($diary_category_key, $diary_category_data);
    } else {
        // 最後後備：使用預設標題
        // 使用統一的前綴獲取函數
        $prefix = mpu_get_diary_title_prefix();
        $title = $prefix . '日記';
    }

    // 嘗試獲取分類圖片並插入到內容開頭
    if (!empty($diary_category_key)) {
        $image_url = mpu_get_random_diary_image($diary_category_key);
        if ($image_url !== false) {
            // 插入圖片 HTML
            $image_html = sprintf(
                '<p><img src="%s" alt="%s" class="mpu-diary-image" style="max-width: 100%%; height: auto; display: block; margin: 0 auto 15px auto;" /></p>',
                esc_url($image_url),
                esc_attr($title)
            );
            $content = $image_html . $content;
        }
    }

    // 準備文章資料
    $post_data = [
        'post_title' => $title,
        'post_content' => $content,
        'post_status' => 'publish',
        'post_author' => $author_id,
        'post_type' => 'post',
    ];

    // 如果有設定分類
    if ($category_id > 0) {
        $post_data['post_category'] = [$category_id];
    }

    // 插入文章
    $post_id = wp_insert_post($post_data, true);

    if (!is_wp_error($post_id)) {
        // 記錄發表日期
        update_option('mpu_last_diary_date', current_time('Y-m-d'));
        update_option('mpu_last_diary_post_id', $post_id);

        // 記錄到日誌
        if (function_exists('mpu_debug_log')) {
            mpu_debug_log('Diary: 發表成功 (ID: ' . $post_id . ')');
        }

        return $post_id;
    }

    return new WP_Error('insert_failed', '無法插入文章');
}

/**
 * 獲取隨機日記圖片
 * 
 * 根據日記分類，從對應的資料夾中隨機選擇一張圖片
 * 
 * @param string $category_key 日記分類鍵名
 * @param string|null $personality_id 人格 ID
 * @return string|false 圖片 URL (相對於網站根目錄)，如果找不到圖片則返回 false
 */
function mpu_get_random_diary_image($category_key, $personality_id = null)
{
    if (empty($category_key)) {
        return false;
    }

    if ($personality_id === null && function_exists('mpu_get_current_personality_id')) {
        $personality_id = mpu_get_current_personality_id();
    }

    // 圖片目錄路徑
    $base_dir = function_exists('mpu_get_personalities_dir') 
        ? mpu_get_personalities_dir() 
        : plugin_dir_path(dirname(dirname(__FILE__))) . 'ghost';
    
    $image_dir = $base_dir . '/' . $personality_id . '/diary_images/' . $category_key;

    if (!file_exists($image_dir) || !is_dir($image_dir)) {
        return false;
    }

    // 掃描圖片檔案
    $files = scandir($image_dir);
    $images = [];
    $valid_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }

        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (in_array($ext, $valid_extensions)) {
            $images[] = $file;
        }
    }

    if (empty($images)) {
        return false;
    }

    // 隨機選擇一張
    $selected_image = $images[array_rand($images)];

    // 建構 URL
    // 注意：這裡需要返回對於前端可訪問的 URL
    // 假設 ghost 目錄在 wp-content/plugins/mp-ukagaka/ghost/
    $plugin_dir_url = plugin_dir_url(dirname(dirname(dirname(__FILE__))) . '/mp-ukagaka.php');
    
    return $plugin_dir_url . 'ghost/' . $personality_id . '/diary_images/' . $category_key . '/' . $selected_image;
}

/**
 * WP Cron 處理函數：每日日記檢查
 */
function mpu_auto_diary_cron_handler()
{
    // 若前一次執行仍在進行中，跳過這次以避免重疊執行
    if (get_transient('mpu_cron_diary_running')) {
        return;
    }

    // 標記 cron 正在執行（300 秒 timeout，防止重疊）
    set_transient('mpu_cron_diary_running', true, 300);

    // 檢查是否應該觸發
    if (!mpu_should_trigger_diary()) {
        set_transient('mpu_cron_diary_last_run', [
            'status' => 'skipped',
            'time'   => time(),
        ], WEEK_IN_SECONDS);
        delete_transient('mpu_cron_diary_running');
        return;
    }

    // 生成日記內容
    $diary_data = mpu_generate_diary_content();

    if (is_wp_error($diary_data)) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            mpu_log_error('Diary: Failed to generate content - ' . $diary_data->get_error_message());
        }
        set_transient('mpu_cron_diary_last_run', [
            'status' => 'error',
            'time'   => time(),
            'error'  => $diary_data->get_error_message(),
        ], WEEK_IN_SECONDS);
        delete_transient('mpu_cron_diary_running');
        return;
    }

    // 發表日記
    $result = mpu_publish_diary_post($diary_data);

    if (is_wp_error($result)) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            mpu_log_error('Diary: Failed to publish - ' . $result->get_error_message());
        }
        set_transient('mpu_cron_diary_last_run', [
            'status' => 'error',
            'time'   => time(),
            'error'  => $result->get_error_message(),
        ], WEEK_IN_SECONDS);
    } else {
        set_transient('mpu_cron_diary_last_run', [
            'status'  => 'ok',
            'time'    => time(),
            'post_id' => $result,
        ], WEEK_IN_SECONDS);
    }

    delete_transient('mpu_cron_diary_running');
}
add_action('mpu_daily_diary_check', 'mpu_auto_diary_cron_handler');

/**
 * 排程日記 Cron 事件
 */
function mpu_schedule_diary_cron()
{
    if (!wp_next_scheduled('mpu_daily_diary_check')) {
        // 排程每日執行，設定在晚上 9 點（網站時區）
        $timestamp = strtotime('tomorrow 21:00:00', current_time('timestamp'));
        wp_schedule_event($timestamp, 'daily', 'mpu_daily_diary_check');
    }
}
add_action('init', 'mpu_schedule_diary_cron');

/**
 * AJAX 處理：手動測試生成日記
 */
function mpu_ajax_test_diary_generate()
{
    check_ajax_referer('mpu_test_diary', 'nonce');

    // 檢查權限
    if (!current_user_can('manage_options')) {
        wp_send_json_error(__('権限が不足しています', 'mp-ukagaka'));
        return;
    }

    // 檢查設定
    $provider_settings = mpu_diary_get_provider_settings();
    if ($provider_settings === false) {
        wp_send_json_error(__('日記機能が正しく設定されていません。先に AI プロバイダーを設定してください', 'mp-ukagaka'));
        return;
    }

    // 生成日記內容
    $diary_data = mpu_generate_diary_content();

    if (is_wp_error($diary_data)) {
        wp_send_json_error(__('生成に失敗しました：', 'mp-ukagaka') . $diary_data->get_error_message());
        return;
    }

    // 發表日記
    $post_id = mpu_publish_diary_post($diary_data);

    if (is_wp_error($post_id)) {
        wp_send_json_error(__('投稿に失敗しました：', 'mp-ukagaka') . $post_id->get_error_message());
        return;
    }

    $post_url = get_permalink($post_id);
    $category_info = !empty($diary_data['category']) ? ' (' . $diary_data['category'] . ')' : '';
    wp_send_json_success(sprintf(
        __('日記を投稿しました！<a href="%s" target="_blank">記事を見る</a>%s', 'mp-ukagaka'),
        esc_url($post_url),
        $category_info
    ));
}
add_action('wp_ajax_mpu_test_diary_generate', 'mpu_ajax_test_diary_generate');
