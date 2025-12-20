<?php

/**
 * LLM 功能：本機 LLM (Ollama) 對話生成
 * 
 * @package MP_Ukagaka
 * @subpackage LLM
 */

if (!defined('ABSPATH')) {
    exit();
}

/**
 * 檢測 Ollama 端點是否為遠程連接
 * 
 * @param string $endpoint Ollama 端點 URL
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
 * 根據端點類型和操作類型獲取適當的超時時間
 * 
 * @param string $endpoint Ollama 端點 URL
 * @param string $operation_type 操作類型：'check'（服務檢查）、'api_call'（API 調用）、'test'（測試連接）
 * @return int 超時時間（秒）
 */
function mpu_get_ollama_timeout($endpoint, $operation_type = 'api_call')
{
    $is_remote = mpu_is_remote_endpoint($endpoint);

    // 根據操作類型和連接類型返回超時時間
    switch ($operation_type) {
        case 'check':
            // 服務可用性檢查
            return $is_remote ? 10 : 3;

        case 'api_call':
            // API 調用（生成對話）
            return $is_remote ? 90 : 60;

        case 'test':
            // 測試連接
            return $is_remote ? 45 : 30;

        default:
            // 默認使用 API 調用的超時時間
            return $is_remote ? 90 : 60;
    }
}

/**
 * 驗證和標準化 Ollama 端點 URL
 * 
 * @param string $endpoint 原始端點 URL
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
 * 
 * @param string $endpoint Ollama 端點
 * @param string $model 模型名稱
 * @return bool 服務是否可用
 */
function mpu_check_ollama_available($endpoint, $model)
{
    // 驗證端點 URL
    $validated_endpoint = mpu_validate_ollama_endpoint($endpoint);
    if (is_wp_error($validated_endpoint)) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('MP Ukagaka - Ollama 端點驗證失敗: ' . $validated_endpoint->get_error_message());
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

    // 構建測試 API URL（嘗試多個端點以確保兼容性）
    // 優先使用 /api/version（最輕量），如果失敗則嘗試 /api/tags
    $api_urls = [
        rtrim($endpoint, '/') . '/api/version',
        rtrim($endpoint, '/') . '/api/tags',
    ];

    $is_available = false;
    $last_error = null;

    foreach ($api_urls as $api_url) {
        // 發送輕量級請求檢查服務是否可用（使用動態超時）
        $response = wp_remote_get($api_url, [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'timeout' => $timeout,  // 動態超時：本地 3 秒，遠程 10 秒
        ]);

        if (!is_wp_error($response)) {
            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code === 200) {
                // 服務可用（Ollama 服務正在運行）
                $is_available = true;
                break; // 找到可用的端點，退出循環
            }
        } else {
            // 記錄最後一個錯誤
            $last_error = $response;
        }
    }

    // 如果所有端點都失敗，檢查最後一個錯誤
    if (!$is_available && $last_error !== null) {
        $error_message = $last_error->get_error_message();
        // 連接錯誤表示服務不可用（這已經是 false，但我們記錄錯誤信息）
        // 這裡不需要額外設置，因為 $is_available 已經是 false
    }

    // 緩存結果（5 分鐘）
    set_transient($cache_key, $is_available ? 1 : 0, 5 * MINUTE_IN_SECONDS);

    if (defined('WP_DEBUG') && WP_DEBUG) {
        $connection_type = $is_remote ? '遠程' : '本地';
        error_log("MP Ukagaka - Ollama 服務檢查: " . ($is_available ? '可用' : '不可用') . " ({$connection_type}連接, 端點: {$endpoint}, 模型: {$model}, 超時: {$timeout}秒)");
        if (!$is_available && $last_error !== null) {
            error_log('MP Ukagaka - Ollama 連接錯誤: ' . $last_error->get_error_message());
        }
    }

    return $is_available;
}

/**
 * 根據月份獲取季節
 * 
 * @param int $month 月份（1-12）
 * @return string 季節名稱（春/夏/秋/冬）
 */
function mpu_get_season($month)
{
    // 台灣季節劃分：
    // 春：3-5月
    // 夏：6-8月
    // 秋：9-11月
    // 冬：12-2月
    if ($month >= 3 && $month <= 5) {
        return '春';
    } elseif ($month >= 6 && $month <= 8) {
        return '夏';
    } elseif ($month >= 9 && $month <= 11) {
        return '秋';
    } else {
        return '冬';
    }
}

/**
 * 獲取時間情境（季節 + 時間段）
 * 
 * @return string 時間情境字串，如「春の朝」
 */
function mpu_get_time_context()
{
    // 根據時間獲取情境提示（使用台灣時區）
    $original_timezone = date_default_timezone_get();
    date_default_timezone_set('Asia/Taipei'); // 設置為台灣時區
    $hour = (int) date('G');
    $month = (int) date('n'); // 獲取月份（1-12）
    $season = mpu_get_season($month); // 獲取季節
    date_default_timezone_set($original_timezone); // 恢復原始時區

    // 判定一天中的時間段
    $time_period = '';
    if ($hour >= 5 && $hour < 12) {
        $time_period = '朝';
    } elseif ($hour >= 12 && $hour < 18) {
        $time_period = '昼';
    } elseif ($hour >= 18 && $hour < 22) {
        $time_period = '夜';
    } else {
        $time_period = '深夜';
    }

    // 結合季節和時間段
    return "{$season}の{$time_period}";
}

/**
 * 獲取訪客資訊（包括 BOT 資訊）供 LLM 使用
 * 此函數類似於 mpu_ajax_get_visitor_info()，但返回陣列而非 JSON
 * 
 * @return array 訪客資訊陣列，包含 is_bot, browser_name, browser_type, slimstat_country, slimstat_city 等
 */
function mpu_get_visitor_info_for_llm()
{
    global $wpdb;

    // 從 $_SERVER 獲取基本資訊
    $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : "";

    // 準備返回的資訊
    $visitor_info = [
        "is_bot" => false,
        "browser_type" => 0,
        "browser_name" => "",
        "slimstat_enabled" => false,
    ];

    // 使用 Slimstat 獲取更詳細的訪客資訊
    if (class_exists('wp_slimstat')) {
        $visitor_info["slimstat_enabled"] = true;

        // 直接查詢 Slimstat 資料庫
        $slimstat_table = $wpdb->prefix . 'slim_stats';

        // 使用 prepare 防止 SQL 注入（安全性）
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $slimstat_table));
        if ($table_exists == $slimstat_table) {
            // 查詢當前 IP 最近的完整記錄（包含 BOT 資訊）
            $query = $wpdb->prepare(
                "SELECT country, city, browser, browser_type FROM {$slimstat_table} WHERE ip = %s ORDER BY dt DESC LIMIT 1",
                $ip
            );
            $result = $wpdb->get_row($query, OBJECT);

            if (!empty($result)) {
                // 獲取 country
                if (!empty($result->country)) {
                    $visitor_info["slimstat_country"] = sanitize_text_field($result->country);
                }

                // 獲取 city（可選）
                if (!empty($result->city)) {
                    $visitor_info["slimstat_city"] = sanitize_text_field($result->city);
                }

                // ★★★ 獲取 BOT 資訊 ★★★
                // browser_type: 0 = 一般瀏覽器, 1 = crawler/bot, 2 = mobile
                if (isset($result->browser_type)) {
                    $visitor_info["is_bot"] = (intval($result->browser_type) === 1);
                    $visitor_info["browser_type"] = intval($result->browser_type);
                }

                // 獲取瀏覽器名稱（BOT 名稱）
                if (!empty($result->browser)) {
                    $visitor_info["browser_name"] = sanitize_text_field($result->browser);
                }
            } else {
                // 如果資料庫中沒有記錄，嘗試從當前請求檢測 BOT
                // 使用 Slimstat 的 Browscap 服務來檢測
                if (class_exists('\SlimStat\Services\Browscap')) {
                    // SlimStat\Services\Browscap is provided by the SlimStat plugin (external dependency)
                    /** @phpstan-var class-string<\SlimStat\Services\Browscap> $browscap_class */
                    $browscap_class = '\SlimStat\Services\Browscap';
                    $browser = $browscap_class::get_browser();
                    if (!empty($browser)) {
                        $visitor_info["is_bot"] = (isset($browser['browser_type']) && intval($browser['browser_type']) === 1);
                        $visitor_info["browser_type"] = isset($browser['browser_type']) ? intval($browser['browser_type']) : 0;
                        if (!empty($browser['browser'])) {
                            $visitor_info["browser_name"] = sanitize_text_field($browser['browser']);
                        }
                    }
                }
            }
        }
    }

    return $visitor_info;
}

/**
 * 獲取訪客狀態文字（BOT 或地理位置）
 * 
 * @param array $visitor_info 訪客資訊
 * @return string 訪客狀態描述
 */
function mpu_get_visitor_status_text($visitor_info)
{
    // BOT 檢測優先
    if (!empty($visitor_info['is_bot']) && $visitor_info['is_bot'] === true) {
        $bot_name = !empty($visitor_info['browser_name'])
            ? $visitor_info['browser_name']
            : '未知機器人';
        return "🤖 BOT: {$bot_name}";
    }

    // 地理位置資訊
    if (!empty($visitor_info['slimstat_country'])) {
        $location = $visitor_info['slimstat_country'];
        if (!empty($visitor_info['slimstat_city'])) {
            $location .= " / {$visitor_info['slimstat_city']}";
        }
        return "來自 {$location}";
    }

    return '';
}

/**
 * 壓縮上下文資訊為緊湊格式（節省 Token）
 * 
 * @param array $wp_info WordPress 資訊
 * @param array $user_info 用戶資訊
 * @param array $visitor_info 訪客資訊
 * @return string 壓縮後的上下文資訊
 */
function mpu_compress_context_info($wp_info, $user_info, $visitor_info)
{
    $context_lines = [];

    // 1. 網站核心資訊（單行）
    $site_info = sprintf(
        "WP %s | Theme: %s v%s | PHP %s",
        $wp_info['wp_version'],
        $wp_info['theme_name'],
        $wp_info['theme_version'],
        $wp_info['php_version']
    );
    $context_lines[] = "<site>{$site_info}</site>";

    // 2. 統計資訊（單行，使用簡寫）
    $stats_info = sprintf(
        "文章:%d 留言:%d 分類:%d 標籤:%d 運營:%d天",
        $wp_info['post_count'],
        $wp_info['comment_count'],
        $wp_info['category_count'],
        $wp_info['tag_count'],
        $wp_info['days_operating']
    );
    $context_lines[] = "<stats>{$stats_info}</stats>";

    // 3. 外掛資訊（只取前 5 個，避免過長）
    if (!empty($wp_info['active_plugins_list'])) {
        $plugins_count = $wp_info['active_plugins_count'];
        $top_plugins = array_slice($wp_info['active_plugins_list'], 0, 5);
        $plugins_text = implode('、', $top_plugins);

        if ($plugins_count > 5) {
            $plugins_info = "主要プラグイン: {$plugins_text}...等 (総計{$plugins_count}個)";
        } else {
            $plugins_info = "プラグイン: {$plugins_text} (総計{$plugins_count}個)";
        }
        $context_lines[] = "<plugins>{$plugins_info}</plugins>";
    }

    // 4. 用戶狀態（單行）
    if ($user_info['is_logged_in']) {
        $role_labels = [
            'administrator' => '管理員',
            'editor' => '編集',
            'author' => '作者',
            'contributor' => '貢献者',
            'subscriber' => '購読者',
        ];
        $role = $role_labels[$user_info['primary_role']] ?? $user_info['primary_role'];
        $user_status = sprintf(
            "%s (%s)",
            $user_info['display_name'],
            $role
        );
    } else {
        $user_status = "訪問者（未ログイン）";
    }
    $context_lines[] = "<user>{$user_status}</user>";

    // 5. 訪客資訊（BOT 檢測或地理位置）
    $visitor_status = mpu_get_visitor_status_text($visitor_info);
    if (!empty($visitor_status)) {
        $context_lines[] = "<visitor>{$visitor_status}</visitor>";
    }

    return implode("\n", $context_lines);
}

/**
 * 加權隨機選擇函數
 * 
 * 根據權重陣列，從類別陣列中隨機選擇一個類別
 * 權重越高，被選中的機率越大
 * 
 * @param array $categories 類別陣列（key => value）
 * @param array $weights 權重陣列（key => weight）
 * @return string 選中的類別 key
 */
function mpu_weighted_random_select($categories, $weights)
{
    // 計算總權重
    $total_weight = 0;
    $weighted_keys = [];

    foreach ($categories as $key => $value) {
        // 如果該類別有設定權重，使用設定的權重；否則使用預設權重 5
        $weight = isset($weights[$key]) ? $weights[$key] : 5;
        $total_weight += $weight;
        $weighted_keys[$key] = $weight;
    }

    // 如果總權重為 0，使用均勻隨機
    if ($total_weight <= 0) {
        return array_rand($categories);
    }

    // 生成 0 到總權重之間的隨機數
    $random = wp_rand(1, $total_weight);

    // 根據權重區間選擇類別
    $current_weight = 0;
    foreach ($weighted_keys as $key => $weight) {
        $current_weight += $weight;
        if ($random <= $current_weight) {
            return $key;
        }
    }

    // 如果沒有選中（理論上不應該發生），返回第一個類別
    return array_key_first($categories);
}


/**
 * 建構優化後的 System Prompt（XML 結構化版本）
 * 
 * @param array $mpu_opt 外掛設定
 * @param array $wp_info WordPress 資訊
 * @param array $user_info 用戶資訊
 * @param array $visitor_info 訪客資訊
 * @param string $ukagaka_name 春菜名稱
 * @param string $time_context 時間情境（早上/下午/晚上/深夜）
 * @param string $language 語言設定
 * @return string 優化後的 system prompt
 */
function mpu_build_optimized_system_prompt(
    $mpu_opt,
    $wp_info,
    $user_info,
    $visitor_info,
    $ukagaka_name,
    $time_context,
    $language
) {
    // 1. 獲取角色名稱
    $ukagaka_display_name = $mpu_opt['ukagakas'][$ukagaka_name]['name'] ?? '春菜';

    // 2. 獲取基礎人格設定（來自後台設定）
    $system_prompt = $mpu_opt['ai_system_prompt'] ??
        "你是偽春菜「{$ukagaka_display_name}」。";

    // 3. 準備變數陣列
    $variables = [
        'ukagaka_display_name' => $ukagaka_display_name,
        'language' => $language,
        'time_context' => $time_context,
        'wp_version' => $wp_info['wp_version'] ?? '',
        'php_version' => $wp_info['php_version'] ?? '',
        'post_count' => $wp_info['post_count'] ?? 0,
        'comment_count' => $wp_info['comment_count'] ?? 0,
        'category_count' => $wp_info['category_count'] ?? 0,
        'tag_count' => $wp_info['tag_count'] ?? 0,
        'days_operating' => $wp_info['days_operating'] ?? 0,
        'theme_name' => $wp_info['theme_name'] ?? '',
        'theme_version' => $wp_info['theme_version'] ?? '',
        'theme_author' => $wp_info['theme_author'] ?? '',
    ];

    // 4. 使用模板渲染函數進行變數替換
    $system_prompt = mpu_render_prompt_template($system_prompt, $variables);

    return $system_prompt;
}

/**
 * 使用 LLM 生成隨機對話（取代內建對話）
 * 此函數用於當啟用「使用 LLM 取代內建對話」時，生成不依賴頁面內容的隨機對話
 * 
 * @param string $ukagaka_name 春菜名稱
 * @param string $last_response 上一次 AI 的回應（用於避免重複對話）
 * @param array $response_history 回應歷史陣列（最近幾次回應，用於更嚴格的重複檢測）
 * @return string|false 生成的對話內容，失敗時返回 false
 */
function mpu_generate_llm_dialogue($ukagaka_name = 'default_1', $last_response = '', $response_history = [])
{
    $mpu_opt = mpu_get_option();

    // 檢查是否啟用了「使用 LLM 取代內建對話」（支援所有提供商）
    $llm_replace = isset($mpu_opt['llm_replace_dialogue']) ? $mpu_opt['llm_replace_dialogue'] : (isset($mpu_opt['ollama_replace_dialogue']) && $mpu_opt['ollama_replace_dialogue']);

    if (empty($llm_replace)) {
        return false;
    }

    // 獲取提供商（向後兼容：優先使用 llm_provider，否則使用 ai_provider）
    $provider = isset($mpu_opt['llm_provider']) ? $mpu_opt['llm_provider'] : (isset($mpu_opt['ai_provider']) ? $mpu_opt['ai_provider'] : 'gemini');

    $language = $mpu_opt['ai_language'] ?? 'zh-TW';

    // 如果是 Ollama，檢查服務是否可用
    if ($provider === 'ollama') {
        $endpoint = $mpu_opt['ollama_endpoint'] ?? 'http://localhost:11434';
        $model = $mpu_opt['ollama_model'] ?? 'qwen3:8b';

        if (!mpu_check_ollama_available($endpoint, $model)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('MP Ukagaka - Ollama 服務不可用，返回錯誤提示');
                error_log('MP Ukagaka - 端點: ' . $endpoint . ', 模型: ' . $model);
            }
            return 'MPU_OLLAMA_NOT_AVAILABLE';
        }
    }

    // 獲取春菜名稱
    $ukagaka_name_display = $mpu_opt['ukagakas'][$ukagaka_name]['name'] ?? '春菜';

    // 獲取 WordPress 網站資訊
    $wp_info = mpu_get_wordpress_info();

    // 獲取當前用戶資訊
    $user_info = mpu_get_current_user_info();

    // ★★★ 獲取訪客資訊（包括 BOT 資訊）★★★
    $visitor_info = mpu_get_visitor_info_for_llm();

    // 獲取時間情境
    $time_context = mpu_get_time_context();

    // ★★★ 使用優化後的 System Prompt 建構函數 ★★★
    $system_prompt = mpu_build_optimized_system_prompt(
        $mpu_opt,
        $wp_info,
        $user_info,
        $visitor_info,
        $ukagaka_name,
        $time_context,
        $language
    );

    // Debug 模式：輸出 System Prompt 供檢查
    mpu_debug_system_prompt($system_prompt);

    // ★★★ 使用 Prompt Categories 函數生成類別指令 ★★★
    // 這些指令會與實際資訊一起組成 User Prompt，引導 LLM 生成對應類型的對話
    $prompt_categories = mpu_build_prompt_categories(
        $wp_info,
        $visitor_info,
        $time_context,
        $wp_info['theme_name'],
        $wp_info['theme_version'],
        $wp_info['theme_author'] ?? ''
    );

    // 獲取動態權重配置（根據時間、訪客狀態等調整）
    // 獲取上下文變數（可選，用於更精細的權重調整）
    $context_vars = [];
    // 可以在這裡添加更多上下文變數的檢測邏輯
    // 例如：$context_vars['is_first_visit'] = ...;
    // 例如：$context_vars['is_frequent_visitor'] = ...;
    // 例如：$context_vars['is_weekend'] = ...;

    $category_weights = mpu_get_dynamic_category_weights(
        $time_context,
        $visitor_info,
        $context_vars
    );

    // 使用加權隨機選擇一個類別
    $selected_category = mpu_weighted_random_select($prompt_categories, $category_weights);
    // 從選中的類別中隨機選擇一個提示詞
    $category_instruction = $prompt_categories[$selected_category][array_rand($prompt_categories[$selected_category])];

    // 構建 User Prompt：包含實際資訊 + 類別指令
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
        $user_prompt .= "国：{$visitor_info['slimstat_country']}";
        if (!empty($visitor_info['slimstat_city'])) {
            $user_prompt .= " {$visitor_info['slimstat_city']}";
        }
        $user_prompt .= "\n";
    }

    $user_prompt .= "\n【サイト統計】（冒険の記録として）\n";
    $user_prompt .= "記事数（魔族討伐数）：{$wp_info['post_count']}\n";
    $user_prompt .= "コメント数（戦闘回数）：{$wp_info['comment_count']}\n";
    $user_prompt .= "カテゴリ数（習得スキル総数）：{$wp_info['category_count']}\n";
    $user_prompt .= "タグ数（アイテム使用回数）：{$wp_info['tag_count']}\n";
    $user_prompt .= "運営日数（冒険経過日数）：{$wp_info['days_operating']}\n";
    if (!empty($wp_info['theme_name'])) {
        $user_prompt .= "テーマ：{$wp_info['theme_name']} v{$wp_info['theme_version']}\n";
    }
    $user_prompt .= "WordPressのバージョン：{$wp_info['wp_version']}\n";
    $user_prompt .= "PHPのバージョン：{$wp_info['php_version']}\n";

    $user_prompt .= "\n【時間感覚】\n";
    $user_prompt .= "今は：{$time_context}\n";

    $user_prompt .= "\n【会話指示】\n";
    $user_prompt .= $category_instruction;

    // 如果提供了上一次回應，加入避免重複的指令（防止廢話迴圈）
    if (!empty($last_response)) {
        $last_response_escaped = esc_attr($last_response);
        // 使用日語指令，符合角色風格
        $user_prompt .= "\n\n注意：さっき「{$last_response_escaped}」と言ったため、新しいことがなければ、違う短い一言を言うか、何も言わないで（何も出力しない）。同じことを繰り返さないこと。";
    }

    // 根據提供商調用對應的 API
    $api_key = '';
    if ($provider !== 'ollama') {
        // 獲取 API Key（向後兼容）
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

    // 調用對應的 API
    if ($provider === 'ollama') {
        $endpoint = $mpu_opt['ollama_endpoint'] ?? 'http://localhost:11434';
        $model = $mpu_opt['ollama_model'] ?? 'qwen3:8b';
        $result = mpu_call_ollama_api($endpoint, $model, $system_prompt, $user_prompt, $language);
    } else {
        $result = mpu_call_ai_api($provider, $api_key, $system_prompt, $user_prompt, $language, $mpu_opt);
    }

    if (is_wp_error($result)) {
        // 如果 LLM 調用失敗，返回 false，讓系統使用後備對話
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('LLM Dialogue Generation Failed: ' . $result->get_error_message());
        }
        // 如果調用失敗，清除緩存（僅 Ollama）
        if ($provider === 'ollama') {
            $endpoint = $mpu_opt['ollama_endpoint'] ?? 'http://localhost:11434';
            $model = $mpu_opt['ollama_model'] ?? 'qwen3:8b';
            $cache_key = 'mpu_ollama_available_' . md5($endpoint . $model);
            delete_transient($cache_key);
        }
        return false;
    }

    // ★★★ 後端相似度檢查（防止廢話迴圈）★★★
    if (!empty($result) && (!empty($last_response) || !empty($response_history))) {
        $similarity_threshold = 0.7; // 相似度閾值（70%），超過此值視為重複

        // 檢查與上一次回應的相似度
        if (!empty($last_response)) {
            $similarity = mpu_calculate_text_similarity($result, $last_response);
            if ($similarity >= $similarity_threshold) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("MP Ukagaka - 檢測到重複回應（相似度: " . round($similarity * 100, 1) . "%），拒絕返回");
                }
                // 相似度太高，返回 false 讓系統使用後備對話
                return false;
            }
        }

        // 檢查與歷史回應的相似度
        if (!empty($response_history) && is_array($response_history)) {
            foreach ($response_history as $hist_response) {
                $similarity = mpu_calculate_text_similarity($result, $hist_response);
                if ($similarity >= $similarity_threshold) {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log("MP Ukagaka - 檢測到與歷史回應重複（相似度: " . round($similarity * 100, 1) . "%），拒絕返回");
                    }
                    // 相似度太高，返回 false 讓系統使用後備對話
                    return false;
                }
            }
        }
    }

    return $result;
}

/**
 * 計算兩個文字的相似度（使用簡單的字符級別相似度算法）
 * 
 * @param string $text1 第一個文字
 * @param string $text2 第二個文字
 * @return float 相似度（0.0 到 1.0，1.0 表示完全相同）
 */
function mpu_calculate_text_similarity($text1, $text2)
{
    if (empty($text1) || empty($text2)) {
        return 0.0;
    }

    // 標準化文字（移除空白、標點，轉為小寫）
    $normalize = function ($text) {
        // 移除標點符號和空白
        $text = preg_replace('/[^\p{L}\p{N}]/u', '', $text);
        // 轉為小寫
        $text = mb_strtolower($text, 'UTF-8');
        return $text;
    };

    $norm1 = $normalize($text1);
    $norm2 = $normalize($text2);

    if (empty($norm1) || empty($norm2)) {
        return 0.0;
    }

    // 如果完全相同，返回 1.0
    if ($norm1 === $norm2) {
        return 1.0;
    }

    // 使用最長公共子序列（LCS）算法計算相似度
    $len1 = mb_strlen($norm1, 'UTF-8');
    $len2 = mb_strlen($norm2, 'UTF-8');

    // 如果長度差異太大，直接返回較低相似度
    $length_ratio = min($len1, $len2) / max($len1, $len2);
    if ($length_ratio < 0.5) {
        return 0.0; // 長度差異太大，視為完全不同
    }

    // 計算最長公共子序列長度
    $lcs_length = mpu_lcs_length($norm1, $norm2);

    // 相似度 = LCS 長度 / 平均長度
    $avg_length = ($len1 + $len2) / 2;
    $similarity = $lcs_length / $avg_length;

    return min(1.0, $similarity);
}

/**
 * 計算兩個字串的最長公共子序列（LCS）長度
 * 
 * @param string $str1 第一個字串
 * @param string $str2 第二個字串
 * @return int LCS 長度
 */
function mpu_lcs_length($str1, $str2)
{
    $len1 = mb_strlen($str1, 'UTF-8');
    $len2 = mb_strlen($str2, 'UTF-8');

    // 使用動態規劃計算 LCS
    $dp = [];
    for ($i = 0; $i <= $len1; $i++) {
        $dp[$i] = [];
        for ($j = 0; $j <= $len2; $j++) {
            if ($i === 0 || $j === 0) {
                $dp[$i][$j] = 0;
            } else {
                $char1 = mb_substr($str1, $i - 1, 1, 'UTF-8');
                $char2 = mb_substr($str2, $j - 1, 1, 'UTF-8');
                if ($char1 === $char2) {
                    $dp[$i][$j] = $dp[$i - 1][$j - 1] + 1;
                } else {
                    $dp[$i][$j] = max($dp[$i - 1][$j], $dp[$i][$j - 1]);
                }
            }
        }
    }

    return $dp[$len1][$len2];
}

/**
 * 檢查是否啟用了 LLM 取代內建對話
 * 
 * 注意：此功能獨立於「頁面感知 AI」(ai_enabled)
 * LLM 取代對話只需要：
 * 1. ollama_replace_dialogue 為 true
 * 2. ai_provider 為 'ollama'
 * 
 * @return bool
 */
function mpu_is_llm_replace_dialogue_enabled()
{
    $mpu_opt = mpu_get_option();

    // 檢查是否啟用 LLM 取代內建對話（支援所有提供商）
    $llm_replace = isset($mpu_opt['llm_replace_dialogue']) ? $mpu_opt['llm_replace_dialogue'] : (isset($mpu_opt['ollama_replace_dialogue']) && $mpu_opt['ollama_replace_dialogue']);

    if (empty($llm_replace)) {
        return false;
    }

    // 獲取提供商（向後兼容）
    $provider = isset($mpu_opt['llm_provider']) ? $mpu_opt['llm_provider'] : (isset($mpu_opt['ai_provider']) ? $mpu_opt['ai_provider'] : 'gemini');

    // 檢查提供商是否有有效的設定
    if ($provider === 'ollama') {
        // Ollama 不需要 API Key，只需要檢查端點和模型
        return true;
    } else {
        // 雲端提供商需要 API Key
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
 * 
 * @return array|false 設定陣列，未啟用時返回 false
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
 * 
 * @param string $system_prompt System prompt 內容
 */
function mpu_debug_system_prompt($system_prompt)
{
    if (defined('WP_DEBUG') && WP_DEBUG) {
        // 粗略估算 Token 數（中文約 2-3 字符 = 1 token，英文約 4 字符 = 1 token）
        $char_count = mb_strlen($system_prompt, 'UTF-8');

        // 計算中文字數
        preg_match_all('/[\x{4e00}-\x{9fa5}]/u', $system_prompt, $chinese_chars);
        $chinese_count = count($chinese_chars[0]);

        // 計算英文字數
        $english_count = $char_count - $chinese_count;

        // 估算 token 數
        $estimated_tokens = ($chinese_count / 2) + ($english_count / 4);

        error_log('=== MP Ukagaka - System Prompt Debug ===');
        error_log('估算 Token 數: ' . (int)ceil($estimated_tokens));
        error_log('字符長度: ' . $char_count);
        error_log('--- Prompt 內容 ---');
        error_log($system_prompt);
        error_log('=== End Debug ===');
    }
}
