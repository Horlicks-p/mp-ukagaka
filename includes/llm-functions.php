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
 * 檢查 Ollama 是否正在處理請求
 * 
 * @param string $endpoint Ollama 端點
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
 * 
 * @param string $endpoint Ollama 端點
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
 * 
 * @param string $endpoint Ollama 端點
 * @param string $model 模型名稱
 */
function mpu_release_ollama_lock($endpoint, $model)
{
    $lock_key = 'mpu_ollama_lock_' . md5($endpoint . $model);
    delete_transient($lock_key);
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
        // 注意：這裡使用直接查詢而非 REST API，因為：
        // 1. 需要根據當前訪客的 IP 精確查詢個人資訊
        // 2. REST API 的 recent 功能無法精確按 IP 過濾（filters 參數格式複雜）
        // 3. 直接查詢更快、更精確，且是內部使用
        // 統計數據（總訪問數、最熱門文章）則使用 REST API（見 mpu_fetch_slimstat_stats()）
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

                if (isset($result->browser_type)) {
                    $visitor_info["is_bot"] = (intval($result->browser_type) === 1);
                    $visitor_info["browser_type"] = intval($result->browser_type);
                }

                if (!empty($result->browser)) {
                    $visitor_info["browser_name"] = sanitize_text_field($result->browser);
                }
            } else {
                if (class_exists('\SlimStat\Services\Browscap')) {
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
 * 從 Slimstat 設定中獲取第一個可用的 REST API token
 * 
 * @return string|false 返回第一個可用的 token，如果沒有則返回 false
 */
function mpu_get_slimstat_rest_token()
{
    // 檢查 Slimstat 是否啟用
    if (!class_exists('wp_slimstat')) {
        return false;
    }

    // 獲取 Slimstat 設定
    $slimstat_options = get_option('slimstat_options', []);

    if (empty($slimstat_options['rest_api_tokens'])) {
        return false;
    }

    // 將 token 字串轉換為陣列（Slimstat 使用逗號分隔）
    $tokens = array_filter(array_map('trim', explode(',', $slimstat_options['rest_api_tokens'])));

    // 返回第一個可用的 token
    return !empty($tokens) ? reset($tokens) : false;
}

/**
 * 調用 Slimstat REST API 獲取統計數據
 * 
 * @return array 統計數據陣列，包含：
 *   - total_visits: 總訪問數（排除機器人）
 *   - top_resources: 最熱門文章列表（前 5 個）
 */
function mpu_fetch_slimstat_stats()
{
    // 檢查快取（10 分鐘）
    $cache_key = 'mpu_slimstat_stats';
    $cached_stats = get_transient($cache_key);

    if ($cached_stats !== false) {
        return $cached_stats;
    }

    // 初始化返回數據
    $stats = [
        'total_visits' => 0,
        'top_resources' => [],
    ];

    // 獲取 REST API token
    $token = mpu_get_slimstat_rest_token();

    if (empty($token)) {
        // 如果沒有 token，返回空數據並快取 5 分鐘（避免重複檢查）
        set_transient($cache_key, $stats, 5 * MINUTE_IN_SECONDS);
        return $stats;
    }

    // 構建 REST API 端點
    $rest_url = rest_url('slimstat/v1/get');

    // 1. 獲取總訪問數（排除機器人）
    $count_url = add_query_arg([
        'token' => $token,
        'function' => 'count',
        'dimension' => '*',
    ], $rest_url);

    $count_response = wp_remote_get($count_url, [
        'timeout' => 5,
        'sslverify' => false,
    ]);

    if (!is_wp_error($count_response) && wp_remote_retrieve_response_code($count_response) === 200) {
        $count_body = json_decode(wp_remote_retrieve_body($count_response), true);
        if (isset($count_body['data']) && is_numeric($count_body['data'])) {
            $stats['total_visits'] = intval($count_body['data']);
        }
    }

    // 2. 獲取最熱門文章（resource 維度，前 5 個）
    $top_url = add_query_arg([
        'token' => $token,
        'function' => 'top',
        'dimension' => 'resource',
    ], $rest_url);

    $top_response = wp_remote_get($top_url, [
        'timeout' => 5,
        'sslverify' => false,
    ]);

    if (!is_wp_error($top_response) && wp_remote_retrieve_response_code($top_response) === 200) {
        $top_body = json_decode(wp_remote_retrieve_body($top_response), true);
        if (isset($top_body['data']) && is_array($top_body['data'])) {
            // 只取前 5 個
            $top_resources = array_slice($top_body['data'], 0, 5);

            // 格式化資源列表
            foreach ($top_resources as $resource) {
                if (isset($resource['resource'])) {
                    $resource_url = esc_url($resource['resource']);

                    // 嘗試從 URL 獲取文章標題
                    $post_id = url_to_postid($resource_url);
                    $title = '';
                    if ($post_id) {
                        $post = get_post($post_id);
                        if ($post) {
                            $title = get_the_title($post_id);
                        }
                    }

                    $stats['top_resources'][] = [
                        'url' => $resource_url,
                        'title' => $title,
                        'hits' => isset($resource['counthits']) ? intval($resource['counthits']) : 0,
                    ];
                }
            }
        }
    }

    // 記錄調試資訊（僅在 WP_DEBUG 模式下）
    if (defined('WP_DEBUG') && WP_DEBUG) {
        if (is_wp_error($count_response)) {
            error_log('MP Ukagaka - Slimstat REST API 錯誤（count）: ' . $count_response->get_error_message());
        }
        if (is_wp_error($top_response)) {
            error_log('MP Ukagaka - Slimstat REST API 錯誤（top）: ' . $top_response->get_error_message());
        }
    }

    // 快取結果（10 分鐘）
    set_transient($cache_key, $stats, 10 * MINUTE_IN_SECONDS);

    return $stats;
}

/**
 * 獲取隨機文章供 LLM 推薦使用
 * 
 * @param int $count 要獲取的文章數量（1-3篇）
 * @return array 文章陣列，每個元素包含 'title' 和 'url'
 */
function mpu_get_random_posts_for_llm($count = 2)
{
    // 限制數量範圍
    $count = max(1, min(3, intval($count)));

    // 查詢隨機的已發布文章
    $posts = get_posts([
        "numberposts" => $count,
        "orderby" => "rand",
        "post_status" => "publish",
        "suppress_filters" => true,
    ]);

    $articles = [];

    foreach ($posts as $post) {
        $title = get_the_title($post->ID);
        $permalink = get_permalink($post->ID);

        $articles[] = [
            'title' => $title,
            'url' => $permalink,
        ];
    }

    return $articles;
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

    // 4. 添加 Slimstat 統計數據變數
    $slimstat_total_visits = $wp_info['slimstat_total_visits'] ?? 0;
    $variables['slimstat_total_visits'] = $slimstat_total_visits;

    // 格式化最熱門文章列表為易讀的文字
    $top_resources = $wp_info['slimstat_top_resources'] ?? [];
    $top_resources_text = '';
    if (!empty($top_resources)) {
        $resource_list = [];
        foreach ($top_resources as $resource) {
            $url = $resource['url'] ?? '';
            $title = $resource['title'] ?? '';
            if (!empty($url)) {
                if (!empty($title)) {
                    $resource_list[] = "{$title} ({$url})";
                } else {
                    $resource_list[] = $url;
                }
            }
        }
        if (!empty($resource_list)) {
            $top_resources_text = implode('、', $resource_list);
        }
    }
    $variables['slimstat_top_resources'] = $top_resources_text;

    // 5. 使用模板渲染函數進行變數替換
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
                    error_log('MP Ukagaka - Ollama 服務不可用，返回錯誤提示');
                    error_log('MP Ukagaka - 端點: ' . $endpoint . ', 模型: ' . $model);
                }
                return 'MPU_OLLAMA_NOT_AVAILABLE';
            }
        }
    }

    $ukagaka_name_display = $mpu_opt['ukagakas'][$ukagaka_name]['name'] ?? '春菜';
    $wp_info = mpu_get_wordpress_info();
    $user_info = mpu_get_current_user_info();
    $visitor_info = mpu_get_visitor_info_for_llm();
    $time_context = mpu_get_time_context();

    $system_prompt = mpu_build_optimized_system_prompt(
        $mpu_opt,
        $wp_info,
        $user_info,
        $visitor_info,
        $ukagaka_name,
        $time_context,
        $language
    );

    mpu_debug_system_prompt($system_prompt);

    $prompt_categories = mpu_build_prompt_categories(
        $wp_info,
        $visitor_info,
        $time_context,
        $wp_info['theme_name'],
        $wp_info['theme_version'],
        $wp_info['theme_author'] ?? ''
    );

    $context_vars = [];

    $category_weights = mpu_get_dynamic_category_weights(
        $time_context,
        $visitor_info,
        $context_vars
    );

    $selected_category = mpu_weighted_random_select($prompt_categories, $category_weights);
    $category_instruction = $prompt_categories[$selected_category][array_rand($prompt_categories[$selected_category])];

    $articles_info = '';
    if ($selected_category === 'article_recommendation') {
        $article_count = mt_rand(1, 3);
        $articles = mpu_get_random_posts_for_llm($article_count);

        if (!empty($articles)) {
            $articles_info = "\n【記事情報】\n";
            $article_num = 1;
            foreach ($articles as $article) {
                $articles_info .= "記事{$article_num}：{$article['title']} - {$article['url']}\n";
                $article_num++;
            }
            $articles_info .= "\n注意：記事を紹介する際は、HTML形式の<a>タグを使用してリンクを生成してください（例：<a href=\"記事のURL\">記事のタイトル</a>）。";
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

    if (!empty($articles_info)) {
        $user_prompt .= $articles_info;
    }

    $user_prompt .= "\n【会話指示】\n";
    $user_prompt .= $category_instruction;

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
                error_log('MP Ukagaka - Ollama 正在處理其他請求，此請求將被跳過');
            }
            return 'MPU_OLLAMA_BUSY';
        }

        mpu_set_ollama_busy($endpoint, $model, 90);

        $result = mpu_call_ollama_api($endpoint, $model, $system_prompt, $user_prompt, $language);

        mpu_release_ollama_lock($endpoint, $model);
    } else {
        $result = mpu_call_ai_api($provider, $api_key, $system_prompt, $user_prompt, $language, $mpu_opt);
    }

    if (is_wp_error($result)) {
        // 如果 LLM 調用失敗，返回 false，讓系統使用後備對話
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('LLM Dialogue Generation Failed: ' . $result->get_error_message());
        }
        // ★★★ 改善：移除失敗時清除緩存的邏輯 ★★★
        // 原本這裡會清除緩存，但這會導致惡性循環：
        // 超時 → 清除快取 → 重新驗證 → 再超時 → 再清除
        // 現在保留緩存，讓系統在緩存過期後自然重試
        return false;
    }

    // ★★★ 過濾推理模型的思考過程標籤（DeepSeek-R1 等）★★★
    if (!empty($result) && is_string($result)) {
        // 移除 <think>...</think> 標籤（DeepSeek-R1 等推理模型使用）
        $result = preg_replace('/<think>.*?<\/think>/s', '', $result);
        // 移除 <think>...</redacted_reasoning> 標籤（部分模型可能使用）
        $result = preg_replace('/<think>.*?<\/redacted_reasoning>/s', '', $result);
        // 清理可能殘留的空白
        $result = trim($result);

        // 如果過濾後結果為空，返回 false
        if (empty($result)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('MP Ukagaka - LLM 回應僅包含思考過程，無實際內容');
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
                    error_log("MP Ukagaka - 檢測到重複回應（相似度: " . round($similarity * 100, 1) . "%），改用內建對話");
                }
                return 'MPU_USE_FALLBACK';
            }
        }

        if (!empty($response_history) && is_array($response_history)) {
            foreach ($response_history as $hist_response) {
                $similarity = mpu_calculate_text_similarity($result, $hist_response);
                if ($similarity >= $similarity_threshold) {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log("MP Ukagaka - 檢測到與歷史回應重複（相似度: " . round($similarity * 100, 1) . "%），改用內建對話");
                    }
                    return 'MPU_USE_FALLBACK';
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
