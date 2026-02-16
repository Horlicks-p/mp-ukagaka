<?php

/**
 * LLM 功能：本機 LLM (Ollama) 對話生成
 * * @package MP_Ukagaka
 * @subpackage LLM
 */

if (!defined('ABSPATH')) {
    exit();
}

/**
 * 檢測 Ollama 端點是否為遠程連接
 * * @param string $endpoint Ollama 端點 URL
 * @return bool 是否為遠程連接（true = 遠程，false = 本地）
 */
function mpu_is_remote_endpoint($endpoint)
{
    if (empty($endpoint)) {
        return false;
    }

    // 標準化 URL（移除尾部斜線，轉為小寫）
    $normalized = strtolower(rtrim($endpoint, '/'));

    // 檢查是否為本地連接
    $local_patterns = [
        'localhost',
        '127.0.0.1',
        '::1',
        '0.0.0.0',
    ];

    foreach ($local_patterns as $pattern) {
        if (strpos($normalized, $pattern) !== false) {
            return false; // 本地連接
        }
    }

    // 如果包含 http:// 或 https:// 且不是本地模式，則為遠程連接
    if (preg_match('/^https?:\/\//', $normalized)) {
        return true; // 遠程連接
    }

    // 默認視為本地連接（向後兼容）
    return false;
}

/**
 * 檢查 Ollama 是否正在處理請求
 * * @param string $endpoint Ollama 端點
 * @param string $model 模型名稱
 * @return bool 是否正在忙碌
 */
function mpu_is_ollama_busy($endpoint, $model)
{
    $lock_key = 'mpu_ollama_lock_' . md5($endpoint . $model);
    return get_transient($lock_key) !== false;
}

/**
 * 設定 Ollama 忙碌狀態
 * * @param string $endpoint Ollama 端點
 * @param string $model 模型名稱
 * @param int $duration 鎖定持續時間（秒），默認 90 秒
 */
function mpu_set_ollama_busy($endpoint, $model, $duration = 90)
{
    $lock_key = 'mpu_ollama_lock_' . md5($endpoint . $model);
    set_transient($lock_key, time(), $duration);
}

/**
 * 釋放 Ollama 鎖定
 * * @param string $endpoint Ollama 端點
 * @param string $model 模型名稱
 */
function mpu_release_ollama_lock($endpoint, $model)
{
    $lock_key = 'mpu_ollama_lock_' . md5($endpoint . $model);
    delete_transient($lock_key);
}

/**
 * 根據端點類型和操作類型獲取適當的超時時間
 * * @param string $endpoint Ollama 端點 URL
 * @param string $operation_type 操作類型：'check'（服務檢查）、'api_call'（API 調用）、'test'（測試連接）
 * @return int 超時時間（秒）
 */
function mpu_get_ollama_timeout($endpoint, $operation_type = 'api_call')
{
    $is_remote = mpu_is_remote_endpoint($endpoint);

    switch ($operation_type) {
        case 'check':
            return $is_remote ? 15 : 15;

        case 'api_call':
            return $is_remote ? 120 : 90;

        case 'test':
            return $is_remote ? 45 : 30;

        default:
            return $is_remote ? 120 : 90;
    }
}

/**
 * 驗證和標準化 Ollama 端點 URL
 * * @param string $endpoint 原始端點 URL
 * @return string|WP_Error 標準化後的 URL 或錯誤
 */
function mpu_validate_ollama_endpoint($endpoint)
{
    if (empty($endpoint)) {
        return new WP_Error('empty_endpoint', __('Ollama 端點不能為空', 'mp-ukagaka'));
    }

    // 移除尾部斜線
    $endpoint = rtrim($endpoint, '/');

    // 驗證 URL 格式
    if (!preg_match('/^https?:\/\/.+/', $endpoint)) {
        return new WP_Error('invalid_url_format', __('Ollama 端點必須是有效的 HTTP 或 HTTPS URL', 'mp-ukagaka'));
    }

    // 驗證 URL 是否可解析
    $parsed = wp_parse_url($endpoint);
    if ($parsed === false || empty($parsed['scheme']) || empty($parsed['host'])) {
        return new WP_Error('invalid_url', __('無法解析 Ollama 端點 URL', 'mp-ukagaka'));
    }

    // 確保 scheme 是 http 或 https
    if (!in_array($parsed['scheme'], ['http', 'https'], true)) {
        return new WP_Error('invalid_scheme', __('Ollama 端點必須使用 HTTP 或 HTTPS 協議', 'mp-ukagaka'));
    }

    return $endpoint;
}

/**
 * 檢查 Ollama 服務是否可用（快速檢查，使用緩存）
 * * @param string $endpoint Ollama 端點
 * @param string $model 模型名稱
 * @return bool 服務是否可用
 */
function mpu_check_ollama_available($endpoint, $model)
{
    // 驗證端點 URL
    $validated_endpoint = mpu_validate_ollama_endpoint($endpoint);
    if (is_wp_error($validated_endpoint)) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            mpu_debug_log('MP Ukagaka - Ollama 端點驗證失敗: ' . $validated_endpoint->get_error_message());
        }
        return false;
    }
    $endpoint = $validated_endpoint;

    // 使用 transient 緩存檢查結果，避免頻繁檢查（5 分鐘緩存）
    $cache_key = 'mpu_ollama_available_' . md5($endpoint . $model);
    $cached_result = get_transient($cache_key);

    if ($cached_result !== false) {
        return (bool) $cached_result;
    }

    // 根據端點類型使用動態超時時間
    $timeout = mpu_get_ollama_timeout($endpoint, 'check');
    $is_remote = mpu_is_remote_endpoint($endpoint);

    $api_urls = [
        rtrim($endpoint, '/') . '/api/version',
        rtrim($endpoint, '/') . '/api/tags',
    ];

    $is_available = false;
    $last_error = null;

    foreach ($api_urls as $api_url) {
        $response = wp_remote_get($api_url, [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'timeout' => $timeout,
        ]);

        if (!is_wp_error($response)) {
            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code === 200) {
                $is_available = true;
                break;
            }
        } else {
            $last_error = $response;
        }
    }

    if ($is_available) {
        set_transient($cache_key, 1, 10 * MINUTE_IN_SECONDS);
    } else {
        set_transient($cache_key, 0, 60);
    }

    if (defined('WP_DEBUG') && WP_DEBUG) {
        $connection_type = $is_remote ? '遠程' : '本地';
        mpu_debug_log("MP Ukagaka - Ollama 服務檢查: " . ($is_available ? '可用' : '不可用') . " ({$connection_type}連接, 端點: {$endpoint}, 模型: {$model}, 超時: {$timeout}秒)");
        if (!$is_available && $last_error !== null) {
            mpu_debug_log('MP Ukagaka - Ollama 連接錯誤: ' . $last_error->get_error_message());
        }
    }

    return $is_available;
}

/**
 * 使用 LLM 生成隨機對話（取代內建對話）
 * 此函數用於當啟用「使用 LLM 取代內建對話」時，生成不依賴頁面內容的隨機對話
 * * @param string $ukagaka_name 偽春菜名稱
 * @param string $last_response 上一次 AI 的回應（用於避免重複對話）
 * @param array $response_history 回應歷史陣列（最近幾次回應，用於更嚴格的重複檢測）
 * @param int $last_visit_hours 距離上次訪問的小時數（-1 = 首次訪問）
 * @return string|false 生成的對話內容，失敗時返回 false
 */
function mpu_generate_llm_dialogue($ukagaka_name = 'default_1', $last_response = '', $response_history = [], $last_visit_hours = -1)
{
    $mpu_opt = mpu_get_option();

    $llm_replace = isset($mpu_opt['llm_replace_dialogue']) ? $mpu_opt['llm_replace_dialogue'] : (isset($mpu_opt['ollama_replace_dialogue']) && $mpu_opt['ollama_replace_dialogue']);

    if (empty($llm_replace)) {
        return false;
    }

    $provider = isset($mpu_opt['llm_provider']) ? $mpu_opt['llm_provider'] : (isset($mpu_opt['ai_provider']) ? $mpu_opt['ai_provider'] : 'gemini');

    $language = $mpu_opt['ai_language'] ?? 'zh-TW';

    if ($provider === 'ollama') {
        $endpoint = $mpu_opt['ollama_endpoint'] ?? 'http://localhost:11434';
        $model = $mpu_opt['ollama_model'] ?? 'qwen3:8b';

        $cache_key = 'mpu_ollama_available_' . md5($endpoint . $model);
        $cached_result = get_transient($cache_key);

        if ($cached_result === false || $cached_result === 0) {
            if (!mpu_check_ollama_available($endpoint, $model)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    mpu_debug_log('MP Ukagaka - Ollama 服務不可用，返回錯誤提示');
                    mpu_debug_log('MP Ukagaka - 端點: ' . $endpoint . ', 模型: ' . $model);
                }
                return 'MPU_OLLAMA_NOT_AVAILABLE';
            }
        }
    }

    $ukagaka_name_display = $mpu_opt['ukagakas'][$ukagaka_name]['name'] ?? '偽春菜';
    $wp_info = mpu_get_wordpress_info();
    $user_info = mpu_get_current_user_info();
    $visitor_info = mpu_get_visitor_info_for_llm();

    // 1. 先統一解析 personality_id（避免重複解析）
    $personality_id = null;
    if (function_exists('mpu_get_personality_id_from_ukagaka_name')) {
        $personality_id = mpu_get_personality_id_from_ukagaka_name($ukagaka_name);
    }

    // ★ 傳入 personality_id 以讀取該角色的專屬日曆（節日、紀念日等）
    $time_context = mpu_get_time_context($personality_id);

    // 2. 傳遞 ID 給 System Prompt 建構函數（統一使用解析好的 ID）
    $system_prompt = mpu_build_optimized_system_prompt(
        $mpu_opt,
        $wp_info,
        $user_info,
        $visitor_info,
        $ukagaka_name,
        $time_context,
        $language,
        $personality_id // 傳遞已解析的 ID，避免函數內部重複解析
    );

    /**
     * Filter: mpu_llm_system_prompt
     * 允許第三方程式碼修改 LLM System Prompt
     * 
     * @since 2.5.7
     * @param string $system_prompt 原始 System Prompt
     * @param string $ukagaka_name 角色名稱 key
     * @param string|null $personality_id 人格 ID
     * @param array $context 上下文資訊（wp_info, user_info, visitor_info, time_context）
     */
    $system_prompt = apply_filters('mpu_llm_system_prompt', $system_prompt, $ukagaka_name, $personality_id, [
        'wp_info' => $wp_info,
        'user_info' => $user_info,
        'visitor_info' => $visitor_info,
        'time_context' => $time_context,
        'language' => $language,
    ]);

    // System Prompt 會在 mpu_call_ai_api() 中記錄，這裡不需要重複記錄
    // mpu_debug_system_prompt($system_prompt);

    // 3. 繼續傳遞給 Categories（使用已解析的 ID）
    $prompt_categories = mpu_build_prompt_categories(
        $wp_info,
        $visitor_info,
        $time_context,
        $wp_info['theme_name'],
        $wp_info['theme_version'],
        $wp_info['theme_author'] ?? '',
        $personality_id
    );

    // 建構上下文變數（用於權重調整）
    $context_vars = [];
    
    // 訪問時間相關上下文
    if ($last_visit_hours >= 0) {
        $context_vars['last_visit_hours'] = $last_visit_hours;
        
        // 根據訪問間隔設定上下文標記
        // 改為 7 天 (168 小時)
        if ($last_visit_hours > 168) {
            $context_vars['is_long_absence'] = true; // 超過 7 天未訪問
        } else {
            $context_vars['is_recent_visit'] = true; // 7 天內有訪問
        }
    }

    $category_weights = mpu_get_dynamic_category_weights(
        $time_context,
        $visitor_info,
        $context_vars,
        $personality_id
    );

    $selected_category = mpu_weighted_random_select($prompt_categories, $category_weights);
    $category_instruction = $prompt_categories[$selected_category][array_rand($prompt_categories[$selected_category])];

    // --- [變數替換邏輯開始] ---
    // 準備變數陣列
    $prompt_variables = [
        'year' => date('Y'),
        'date' => $time_context, // 預設使用完整的時間情境
    ];

    // 如果是用戶友好的日期格式（例如：1月4日）
    if (preg_match('/(\d+)月(\d+)日/', $time_context, $date_matches)) {
        $prompt_variables['date'] = "{$date_matches[1]}月{$date_matches[2]}日";
    }

    // 獲取節日/紀念日相關變數 (years_passed) 和 LLM 上下文
    $today_holiday_info = ''; // LLM 上下文資訊
    if (function_exists('mpu_get_holiday_config')) {
        // 從 time_context 中提取月份和日期
        if (preg_match('/(\d+)月(\d+)日/', $time_context, $date_matches)) {
            $month = (int) $date_matches[1];
            $day = (int) $date_matches[2];
            $holiday_config = mpu_get_holiday_config($month, $day, $personality_id);

            if (!empty($holiday_config)) {
                // 1. 變數替換用（既有處理）
                if (!empty($holiday_config['years_passed'])) {
                    $prompt_variables['years_passed'] = $holiday_config['years_passed'];
                }

                // 2. LLM 上下文注入用（★新規追加）
                $holiday_name = $holiday_config['name'] ?? '';
                $holiday_desc = $holiday_config['description'] ?? '';

                if (!empty($holiday_name)) {
                    $today_holiday_info .= "今日は「{$holiday_name}」です。";
                    if (!empty($holiday_desc)) {
                        $today_holiday_info .= "({$holiday_desc})";
                    }
                    if (!empty($holiday_config['years_passed'])) {
                        $today_holiday_info .= " {$holiday_config['years_passed']}周年です。";
                    }
                }
            }
        }
    }

    // 執行替換
    if (function_exists('mpu_replace_single_prompt_variables')) {
        $category_instruction = mpu_replace_single_prompt_variables($category_instruction, $prompt_variables);
    }
    // --- [變數替換邏輯結束] ---

    $articles_info = '';
    if ($selected_category === 'article_recommendation') {
        $article_count = wp_rand(1, 3);
        $articles = mpu_get_random_posts_for_llm($article_count);

        if (!empty($articles)) {
            $articles_info = "\n【記事情報】\n";
            $article_num = 1;
            foreach ($articles as $article) {
                // get_permalink() 已經返回正確編碼的 URL，但為了節省 token 和可讀性，我們進行解碼
                $article_url = urldecode($article['url']);
                $articles_info .= "記事{$article_num}：{$article['title']} - {$article_url}\n";
                $article_num++;
            }
            // 明確指示 AI：URL 已經解碼，保持原樣
            $example_url = !empty($articles[0]['url']) ? urldecode($articles[0]['url']) : '';
            $articles_info .= "\n【重要】記事を紹介する際は、HTML形式の<a>タグを使用してリンクを生成してください。\n";
            $articles_info .= "URLはデコード済みです。日本語が含まれていても、そのまま使用してください（再エンコード不要）。\n";
            $articles_info .= "例：<a href=\"{$example_url}\">記事のタイトル</a>\n";
        }
    }

    $user_prompt = "【當前用戶資訊】\n";
    if ($user_info['is_logged_in']) {
        $role_labels = [
            'administrator' => '管理人',
            'editor' => '編集者',
            'author' => '投稿者',
            'contributor' => '貢献者',
            'subscriber' => '購読者',
        ];
        $role_label = isset($role_labels[$user_info['primary_role']])
            ? $role_labels[$user_info['primary_role']]
            : $user_info['primary_role'];

        $user_prompt .= "ユーザーがログインしています：{$user_info['display_name']} ({$user_info['username']})\n";
        $user_prompt .= "役割：{$role_label}\n";
        if ($user_info['is_admin']) {
            $user_prompt .= "このユーザーはサイトの管理人です。\n";
        }
    } else {
        $user_prompt .= "ユーザーがログインしていません（訪客）。\n";
    }

    $user_prompt .= "\n【訪客情報】\n";
    if (!empty($visitor_info['is_bot']) && $visitor_info['is_bot']) {
        $bot_name = $visitor_info['browser_name'] ?? '未知のクローラー';
        $user_prompt .= "BOT を検出しました：{$bot_name}\n";
    }
    if (!empty($visitor_info['slimstat_country'])) {
        $country_name = function_exists('mpu_country_code_to_name') ? mpu_country_code_to_name($visitor_info['slimstat_country']) : $visitor_info['slimstat_country'];
        $user_prompt .= "国：{$country_name}";
        if (!empty($visitor_info['slimstat_city'])) {
            $user_prompt .= " {$visitor_info['slimstat_city']}";
        }
        $user_prompt .= "\n";
    }

    $user_prompt .= "\n【サイト統計】\n";
    $user_prompt .= "記事数：{$wp_info['post_count']}\n";
    $user_prompt .= "コメント数：{$wp_info['comment_count']}\n";
    $user_prompt .= "カテゴリ数：{$wp_info['category_count']}\n";
    $user_prompt .= "タグ数：{$wp_info['tag_count']}\n";
    $user_prompt .= "スパム数：{$wp_info['spam_count']}\n";
    $user_prompt .= "運営日数：{$wp_info['days_operating']}\n";
    if (!empty($wp_info['theme_name'])) {
        $user_prompt .= "テーマ：{$wp_info['theme_name']} v{$wp_info['theme_version']}\n";
    }
    $user_prompt .= "WordPressのバージョン：{$wp_info['wp_version']}\n";
    $user_prompt .= "PHPのバージョン：{$wp_info['php_version']}\n";

    $user_prompt .= "\n【時間感覚】\n";
    $user_prompt .= "今は：{$time_context}\n";

    // ★ 追加：紀念日情報をプロンプトに含める
    if (!empty($today_holiday_info)) {
        $user_prompt .= "特記事項：{$today_holiday_info}\n";
    }

    if (!empty($articles_info)) {
        $user_prompt .= $articles_info;
    }

    /**
     * Filter: mpu_llm_user_prompt
     * 允許第三方程式碼在會話指示之前注入額外上下文
     * 
     * 使用範例（安全警報）：
     * add_filter('mpu_llm_user_prompt', function($prompt, $ukagaka_name, $personality_id) {
     *     $attack_info = get_transient('mpu_llar_attack_info');
     *     if ($attack_info) {
     *         return $prompt . "\n【安全警報】\n" . $attack_info;
     *     }
     *     return $prompt;
     * }, 10, 3);
     * 
     * @since 2.5.7
     * @param string $user_prompt 原始 User Prompt（包含用戶、訪客、統計資訊）
     * @param string $ukagaka_name 角色名稱 key
     * @param string|null $personality_id 人格 ID
     */
    $user_prompt = apply_filters('mpu_llm_user_prompt', $user_prompt, $ukagaka_name, $personality_id);

    $user_prompt .= "\n【会話指示】\n";
    $user_prompt .= $category_instruction;
    
    // 統一字數建議：30-150 字
    $user_prompt .= "\n\n【回応ルール】淡々とした常体で、30-150文字で応答すること。";

    // 加入回應歷史提示，幫助 LLM 避免重複
    if (!empty($response_history) && is_array($response_history) && count($response_history) > 0) {
        $user_prompt .= "\n\n【最近の発言履歴（避けるべき内容）】\n";
        $user_prompt .= "以下の内容と似たことは言わないでください：\n";

        // 只顯示最近 5 條，避免 prompt 過長
        $recent_for_prompt = array_slice($response_history, -5);
        foreach ($recent_for_prompt as $idx => $hist_msg) {
            $hist_escaped = esc_attr(mb_substr($hist_msg, 0, 40, 'UTF-8'));
            $user_prompt .= "- 「{$hist_escaped}...」\n";
        }
        $user_prompt .= "新しい話題や違う視点で話してください。";
    }

    if (!empty($last_response)) {
        $last_response_escaped = esc_attr($last_response);
        $user_prompt .= "\n\n注意：さっき「{$last_response_escaped}」と言ったため、新しいことがなければ、違う短い一言を言うか、何も言わないで（何も出力しない）。同じことを繰り返さないこと。";
    }

    $api_key = '';
    if ($provider !== 'ollama') {
        switch ($provider) {
            case 'gemini':
                $api_key = !empty($mpu_opt['llm_gemini_api_key']) ? mpu_decrypt_api_key($mpu_opt['llm_gemini_api_key']) : (!empty($mpu_opt['ai_api_key']) ? mpu_decrypt_api_key($mpu_opt['ai_api_key']) : '');
                break;
            case 'openai':
                $api_key = !empty($mpu_opt['llm_openai_api_key']) ? mpu_decrypt_api_key($mpu_opt['llm_openai_api_key']) : (!empty($mpu_opt['openai_api_key']) ? mpu_decrypt_api_key($mpu_opt['openai_api_key']) : '');
                break;
            case 'claude':
                $api_key = !empty($mpu_opt['llm_claude_api_key']) ? mpu_decrypt_api_key($mpu_opt['llm_claude_api_key']) : (!empty($mpu_opt['claude_api_key']) ? mpu_decrypt_api_key($mpu_opt['claude_api_key']) : '');
                break;
        }
    }

    if ($provider === 'ollama') {
        $endpoint = $mpu_opt['ollama_endpoint'] ?? 'http://localhost:11434';
        $model = $mpu_opt['ollama_model'] ?? 'qwen3:8b';

        if (mpu_is_ollama_busy($endpoint, $model)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                mpu_debug_log('MP Ukagaka - Ollama 正在處理其他請求，此請求將被跳過');
            }
            return 'MPU_OLLAMA_BUSY';
        }

        mpu_set_ollama_busy($endpoint, $model, 90);

        // 從 manifest.json 的 settings.max_tokens 讀取（預設 800）
        $max_tokens = 800;
        if (function_exists('mpu_get_personality_max_tokens')) {
            $max_tokens = mpu_get_personality_max_tokens($personality_id, $ukagaka_name);
        }

        // 使用 mpu_call_ai_api() 而不是直接調用 mpu_call_ollama_api()
        // 這樣可以統一記錄提示詞日誌
        $result = mpu_call_ai_api($provider, '', $system_prompt, $user_prompt, $language, $mpu_opt, $max_tokens);

        mpu_release_ollama_lock($endpoint, $model);
    } else {
        // 從 manifest.json 的 settings.max_tokens 讀取（預設 800）
        $max_tokens = 800;
        if (function_exists('mpu_get_personality_max_tokens')) {
            $max_tokens = mpu_get_personality_max_tokens($personality_id, $ukagaka_name);
        }
        
        $result = mpu_call_ai_api($provider, $api_key, $system_prompt, $user_prompt, $language, $mpu_opt, $max_tokens);
    }

    if (is_wp_error($result)) {
        // 如果 LLM 調用失敗，返回 false，讓系統使用後備對話
        if (defined('WP_DEBUG') && WP_DEBUG) {
            mpu_log_error('LLM Dialogue Generation Failed: ' . $result->get_error_message());
        }

        return false;
    }

    // ★★★ 過濾推理模型的思考過程標籤（DeepSeek-R1 等）★★★
    // 使用專用函數過濾思考內容，支援完整標籤與截斷標籤
    if (!empty($result) && is_string($result)) {
        if (function_exists('mpu_filter_thinking_content')) {
            $result = mpu_filter_thinking_content($result);
        } else {
            // 後備方案
            $result = preg_replace('/<think>.*?<\/think>/s', '', $result);
            $result = preg_replace('/<think>.*?<\/redacted_reasoning>/s', '', $result);
            $result = trim($result);
        }

        if (empty($result)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                mpu_debug_log('MP Ukagaka - LLM 回應僅包含思考過程，無實際內容');
            }
            }
            return false;
        }
    }

    if (!empty($result) && (!empty($last_response) || !empty($response_history))) {
        $similarity_threshold = 0.7;

        if (!empty($last_response)) {
            $similarity = mpu_calculate_text_similarity($result, $last_response);
            if ($similarity >= $similarity_threshold) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    mpu_debug_log("MP Ukagaka - 檢測到重複回應（相似度: " . round($similarity * 100, 1) . "%），改用內建對話");
                }
                return 'MPU_USE_FALLBACK';
            }
        }

        if (!empty($response_history) && is_array($response_history)) {
            foreach ($response_history as $hist_response) {
                $similarity = mpu_calculate_text_similarity($result, $hist_response);
                if ($similarity >= $similarity_threshold) {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        mpu_debug_log("MP Ukagaka - 檢測到與歷史回應重複（相似度: " . round($similarity * 100, 1) . "%），改用內建對話");
                    }
                    return 'MPU_USE_FALLBACK';
                }
            }
        }
    }

    return $result;
}

/**
 * 計算兩個文字的相似度
 * 使用 PHP 內建的 similar_text() 函數（C 語言實作，效能優於純 PHP 算法）
 * * @param string $text1 第一個文字
 * @param string $text2 第二個文字
 * @return float 相似度（0.0 到 1.0，1.0 表示完全相同）
 */
function mpu_calculate_text_similarity($text1, $text2)
{
    if (empty($text1) || empty($text2)) {
        return 0.0;
    }

    // 正規化：移除標點符號和空白，轉為小寫
    $normalize = function ($text) {
        $text = preg_replace('/[^\p{L}\p{N}]/u', '', $text);
        $text = mb_strtolower($text, 'UTF-8');
        return $text;
    };

    $norm1 = $normalize($text1);
    $norm2 = $normalize($text2);

    if (empty($norm1) || empty($norm2)) {
        return 0.0;
    }

    // 完全相同
    if ($norm1 === $norm2) {
        return 1.0;
    }

    // 長度差異過大時快速返回（優化）
    $len1 = mb_strlen($norm1, 'UTF-8');
    $len2 = mb_strlen($norm2, 'UTF-8');
    $length_ratio = min($len1, $len2) / max($len1, $len2);
    if ($length_ratio < 0.5) {
        return 0.0;
    }

    // 使用 PHP 內建的 similar_text()（C 語言層級實作，速度快 10-100 倍）
    // 直接返回相似度百分比，語意清晰且不用自己維護算法
    similar_text($norm1, $norm2, $percent);
    
    return $percent / 100.0;
}

/**
 * 檢查是否啟用了 LLM 取代內建對話
 * @return bool
 */
function mpu_is_llm_replace_dialogue_enabled()
{
    $mpu_opt = mpu_get_option();

    $llm_replace = isset($mpu_opt['llm_replace_dialogue']) ? $mpu_opt['llm_replace_dialogue'] : (isset($mpu_opt['ollama_replace_dialogue']) && $mpu_opt['ollama_replace_dialogue']);

    if (empty($llm_replace)) {
        return false;
    }

    $provider = isset($mpu_opt['llm_provider']) ? $mpu_opt['llm_provider'] : (isset($mpu_opt['ai_provider']) ? $mpu_opt['ai_provider'] : 'gemini');

    if ($provider === 'ollama') {
        return true;
    } else {
        switch ($provider) {
            case 'gemini':
                return !empty($mpu_opt['llm_gemini_api_key']) || !empty($mpu_opt['ai_api_key']);
            case 'openai':
                return !empty($mpu_opt['llm_openai_api_key']) || !empty($mpu_opt['openai_api_key']);
            case 'claude':
                return !empty($mpu_opt['llm_claude_api_key']) || !empty($mpu_opt['claude_api_key']);
            default:
                return false;
        }
    }
}

/**
 * 獲取 Ollama 設定
 * * @return array|false 設定陣列，未啟用時返回 false
 */
function mpu_get_ollama_settings()
{
    $mpu_opt = mpu_get_option();

    if ($mpu_opt['ai_provider'] !== 'ollama') {
        return false;
    }

    return [
        'endpoint' => $mpu_opt['ollama_endpoint'] ?? 'http://localhost:11434',
        'model' => $mpu_opt['ollama_model'] ?? 'qwen3:8b',
        'replace_dialogue' => !empty($mpu_opt['ollama_replace_dialogue']),
    ];
}

/**
 * Debug 工具：輸出 System Prompt 供檢查
 * 使用方式：在 WordPress Debug 模式下會自動記錄到日誌
 * * @param string $system_prompt System prompt 內容
 */
function mpu_debug_system_prompt($system_prompt)
{
    if (defined('WP_DEBUG') && WP_DEBUG) {
        $char_count = mb_strlen($system_prompt, 'UTF-8');

        preg_match_all('/[\x{4e00}-\x{9fa5}]/u', $system_prompt, $chinese_chars);
        $chinese_count = count($chinese_chars[0]);

        $english_count = $char_count - $chinese_count;

        $estimated_tokens = ($chinese_count / 2) + ($english_count / 4);

        // 使用 mpu_debug_log 確保 UTF-8 文字正確顯示
        mpu_debug_log('=== MP Ukagaka - System Prompt Debug ===');
        mpu_debug_log('估算 Token 數: ' . (int)ceil($estimated_tokens));
        mpu_debug_log('字符長度: ' . $char_count);
        mpu_debug_log('--- Prompt 內容 ---');
        mpu_debug_log($system_prompt);
        mpu_debug_log('=== End Debug ===');
    }
}