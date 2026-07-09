<?php

/**
 * LLM 功能：Slimstat 整合
 * 
 * 處理 Slimstat 統計數據的獲取和整合
 * 
 * @package MP_Ukagaka
 * @subpackage LLM
 */

if (!defined('ABSPATH')) {
    exit();
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

	$sslverify = apply_filters( 'https_local_ssl_verify', apply_filters( 'https_ssl_verify', true ), $count_url );

    $count_response = wp_remote_get($count_url, [
        'timeout' => 5,
        'sslverify' => $sslverify,
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
        'sslverify' => $sslverify,
    ]);

    if (!is_wp_error($top_response) && wp_remote_retrieve_response_code($top_response) === 200) {
        $top_body = json_decode(wp_remote_retrieve_body($top_response), true);
        if (isset($top_body['data']) && is_array($top_body['data'])) {
            // 只取前 5 個
            $top_resources = array_slice($top_body['data'], 0, 5);

            // 格式化資源列表
            $post_id_cache = []; // 請求層級快取，避免同一 URL 重複查詢 DB
            foreach ($top_resources as $resource) {
                if (isset($resource['resource'])) {
                    $resource_url = esc_url($resource['resource']);

                    // 嘗試從 URL 獲取文章標題（使用請求層級快取）
                    if (!isset($post_id_cache[$resource_url])) {
                        $post_id_cache[$resource_url] = url_to_postid($resource_url);
                    }
                    $post_id = $post_id_cache[$resource_url];
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
            mpu_debug_log('Slimstat REST API 錯誤（count）: ' . $count_response->get_error_message());
        }
        if (is_wp_error($top_response)) {
            mpu_debug_log('Slimstat REST API 錯誤（top）: ' . $top_response->get_error_message());
        }
    }

    // 快取結果（10 分鐘）
    set_transient($cache_key, $stats, 10 * MINUTE_IN_SECONDS);

    return $stats;
}

/**
 * 檢查最近是否有 BOT 訪問（用於即時反應）
 * 
 * 直接查詢 Slimstat 資料庫，尋找過去幾秒內的 BOT 訪問記錄。
 * 
 * @param int $seconds 檢查過去多少秒內的記錄（預設 60 秒）
 * @return string|false 如果有 BOT 訪問，返回 BOT 名稱（browser），否則返回 false
 */
function mpu_check_recent_bot_visit($seconds = 60)
{
    // 檢查 Slimstat 是否啟用
    if (!class_exists('wp_slimstat')) {
        return false;
    }

    global $wpdb;
    $slimstat_table = $wpdb->prefix . 'slim_stats';

    // 檢查資料表是否存在
    $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $slimstat_table));
    if ($table_exists != $slimstat_table) {
        return false;
    }

    // 計算時間閾值
    $threshold = time() - $seconds;

    // 查詢最近的 BOT 訪問（browser_type = 1）
    // dt 是 Slimstat 的 timestamp 欄位
    $query = $wpdb->prepare(
        "SELECT browser FROM {$slimstat_table} 
         WHERE browser_type = 1 
         AND dt > %d 
         ORDER BY dt DESC 
         LIMIT 1",
        $threshold
    );

    $bot_name = $wpdb->get_var($query);

    if (!empty($bot_name)) {
        return sanitize_text_field($bot_name);
    }

    return false;
}

/**
 * AI 爬蟲 UA 對照表
 *
 * 借鑑 atlant-security AICrawlers 模組，純當查表用，不做封鎖。
 * 用於分辨 slimstat 偵測到的 bot 是哪一類 AI 訓練／推論爬蟲。
 *
 * @return array<int,array{id:string,name:string,pattern:string,company:string,purpose:string}>
 */
function mpu_get_ai_crawler_definitions()
{
    return [
        ['id' => 'gptbot',            'name' => 'GPTBot',             'pattern' => '/GPTBot/i',                'company' => 'OpenAI',       'purpose' => 'GPT 訓練'],
        ['id' => 'chatgpt_user',      'name' => 'ChatGPT-User',       'pattern' => '/ChatGPT-User/i',          'company' => 'OpenAI',       'purpose' => 'ChatGPT 即時瀏覽'],
        ['id' => 'oai_searchbot',     'name' => 'OAI-SearchBot',      'pattern' => '/OAI-SearchBot/i',         'company' => 'OpenAI',       'purpose' => 'ChatGPT 搜尋'],
        ['id' => 'google_extended',   'name' => 'Google-Extended',    'pattern' => '/Google-Extended/i',       'company' => 'Google',       'purpose' => 'Gemini 訓練'],
        ['id' => 'claudebot',         'name' => 'ClaudeBot',          'pattern' => '/ClaudeBot/i',             'company' => 'Anthropic',    'purpose' => 'Claude 訓練'],
        ['id' => 'claude_web',        'name' => 'Claude-Web',         'pattern' => '/Claude-Web/i',            'company' => 'Anthropic',    'purpose' => 'Claude 即時瀏覽'],
        ['id' => 'anthropic',         'name' => 'anthropic-ai',       'pattern' => '/anthropic-ai/i',          'company' => 'Anthropic',    'purpose' => 'AI 訓練'],
        ['id' => 'ccbot',             'name' => 'CCBot',              'pattern' => '/CCBot/i',                 'company' => 'Common Crawl', 'purpose' => '開放資料集（多被 AI 訓練使用）'],
        ['id' => 'bytespider',        'name' => 'Bytespider',         'pattern' => '/Bytespider/i',            'company' => 'ByteDance',    'purpose' => 'AI 訓練'],
        ['id' => 'perplexitybot',     'name' => 'PerplexityBot',      'pattern' => '/PerplexityBot/i',         'company' => 'Perplexity',   'purpose' => '搜尋強化'],
        ['id' => 'amazonbot',         'name' => 'Amazonbot',          'pattern' => '/Amazonbot/i',             'company' => 'Amazon',       'purpose' => 'Alexa / AI 訓練'],
        ['id' => 'meta_external',     'name' => 'Meta-ExternalAgent', 'pattern' => '/Meta-ExternalAgent/i',    'company' => 'Meta',         'purpose' => 'AI 訓練'],
        ['id' => 'applebot_extended', 'name' => 'Applebot-Extended',  'pattern' => '/Applebot-Extended/i',     'company' => 'Apple',        'purpose' => 'Apple Intelligence 訓練'],
        ['id' => 'cohere_ai',         'name' => 'cohere-ai',          'pattern' => '/cohere-ai/i',             'company' => 'Cohere',       'purpose' => 'AI 訓練'],
        ['id' => 'ai2bot',            'name' => 'AI2Bot',             'pattern' => '/AI2Bot/i',                'company' => 'Allen AI',     'purpose' => 'AI 研究'],
        ['id' => 'diffbot',           'name' => 'Diffbot',            'pattern' => '/Diffbot/i',               'company' => 'Diffbot',      'purpose' => '網頁資料抽取'],
        ['id' => 'youbot',            'name' => 'YouBot',             'pattern' => '/YouBot/i',                'company' => 'You.com',      'purpose' => 'AI 搜尋'],
        ['id' => 'imagesiftbot',      'name' => 'ImagesiftBot',       'pattern' => '/ImagesiftBot/i',          'company' => 'Imagesift',    'purpose' => 'AI 圖片索引'],
        ['id' => 'omgilibot',         'name' => 'Omgilibot',          'pattern' => '/Omgilibot/i',             'company' => 'Omgili',       'purpose' => '資料探勘'],
    ];
}

/**
 * 檢查最近 N 秒是否有 AI 爬蟲訪問
 *
 * 直接查 wp_slim_stats 過去 N 秒內 browser_type=1 的記錄，
 * 將 user_agent 與 browser 欄位與 AI 爬蟲 UA 對照表比對。
 *
 * @param int $seconds 檢查時窗（秒），預設 60
 * @return array|false 命中時回傳 [id,name,company,purpose]，否則 false
 */
function mpu_check_recent_ai_crawler($seconds = 60)
{
    if (!class_exists('wp_slimstat')) {
        return false;
    }

    global $wpdb;
    $table = $wpdb->prefix . 'slim_stats';
    $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
    if ($exists !== $table) {
        return false;
    }

    $threshold = time() - max(1, intval($seconds));

    // 取最近的 bot 記錄；user_agent 才能精準比對 AI 爬蟲簽名
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT browser, user_agent FROM {$table}
         WHERE browser_type = 1
         AND dt > %d
         ORDER BY dt DESC
         LIMIT 20",
        $threshold
    ), ARRAY_A);

    if (empty($rows)) {
        return false;
    }

    $definitions = mpu_get_ai_crawler_definitions();

    foreach ($rows as $row) {
        $haystack = trim(($row['user_agent'] ?? '') . ' ' . ($row['browser'] ?? ''));
        if ($haystack === '') {
            continue;
        }

        foreach ($definitions as $def) {
            if (preg_match($def['pattern'], $haystack)) {
                return [
                    'id'      => $def['id'],
                    'name'    => $def['name'],
                    'company' => $def['company'],
                    'purpose' => $def['purpose'],
                ];
            }
        }
    }

    return false;
}

/**
 * 取得最近一段時間內命中的所有 AI 爬蟲（含每個爬蟲的訪問次數）
 *
 * @param int $seconds 時窗（秒），預設 600
 * @param int $limit   最多檢查多少筆 bot 記錄，預設 200
 * @return array<int,array{id:string,name:string,company:string,purpose:string,count:int}>
 */
function mpu_get_recent_ai_crawlers($seconds = 600, $limit = 200)
{
    if (!class_exists('wp_slimstat')) {
        return [];
    }

    global $wpdb;
    $table = $wpdb->prefix . 'slim_stats';
    $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
    if ($exists !== $table) {
        return [];
    }

    $threshold = time() - max(1, intval($seconds));
    $limit     = max(1, min(1000, intval($limit)));

    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT browser, user_agent FROM {$table}
         WHERE browser_type = 1
         AND dt > %d
         ORDER BY dt DESC
         LIMIT %d",
        $threshold,
        $limit
    ), ARRAY_A);

    if (empty($rows)) {
        return [];
    }

    $definitions = mpu_get_ai_crawler_definitions();
    $hits        = [];

    foreach ($rows as $row) {
        $haystack = trim(($row['user_agent'] ?? '') . ' ' . ($row['browser'] ?? ''));
        if ($haystack === '') {
            continue;
        }

        foreach ($definitions as $def) {
            if (preg_match($def['pattern'], $haystack)) {
                $id = $def['id'];
                if (!isset($hits[$id])) {
                    $hits[$id] = [
                        'id'      => $id,
                        'name'    => $def['name'],
                        'company' => $def['company'],
                        'purpose' => $def['purpose'],
                        'count'   => 0,
                    ];
                }
                $hits[$id]['count']++;
                break;
            }
        }
    }

    $result = array_values($hits);
    usort($result, function ($a, $b) {
        return $b['count'] <=> $a['count'];
    });

    return $result;
}

/**
 * 取得最近一段時間的訪客脈動概要
 *
 * 全部資料來源為 wp_slim_stats，無額外寫入。
 *
 * @param int $minutes 時窗（分鐘），預設 60
 * @return array{total:int,unique_ips:int,unique_countries:int,countries:array,bot_count:int,human_count:int}
 */
function mpu_get_visitor_pulse($minutes = 60)
{
    $empty = [
        'total'            => 0,
        'unique_ips'       => 0,
        'unique_countries' => 0,
        'countries'        => [],
        'bot_count'        => 0,
        'human_count'      => 0,
    ];

    if (!class_exists('wp_slimstat')) {
        return $empty;
    }

    global $wpdb;
    $table = $wpdb->prefix . 'slim_stats';
    $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
    if ($exists !== $table) {
        return $empty;
    }

    $threshold = time() - max(1, intval($minutes)) * 60;

    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT
            COUNT(*) AS total,
            COUNT(DISTINCT ip) AS unique_ips,
            COUNT(DISTINCT country) AS unique_countries,
            SUM(CASE WHEN browser_type = 1 THEN 1 ELSE 0 END) AS bot_count
         FROM {$table}
         WHERE dt > %d",
        $threshold
    ), ARRAY_A);

    if (empty($row)) {
        return $empty;
    }

    $countries = $wpdb->get_col($wpdb->prepare(
        "SELECT DISTINCT country FROM {$table}
         WHERE dt > %d AND country IS NOT NULL AND country != ''
         ORDER BY country ASC",
        $threshold
    ));

    $total = intval($row['total']);
    $bot   = intval($row['bot_count']);

    return [
        'total'            => $total,
        'unique_ips'       => intval($row['unique_ips']),
        'unique_countries' => intval($row['unique_countries']),
        'countries'        => array_map('sanitize_text_field', $countries),
        'bot_count'        => $bot,
        'human_count'      => max(0, $total - $bot),
    ];
}

/**
 * 將一批國家代碼合併進 mpu_visitor_pulse_seen_countries 已播報清單
 *
 * 由 REST handler 在訊號成功播報（LLM 生成成功）後呼叫。
 * detect 函式本身不做 mutation，避免「冷卻中偵測命中卻被誤標為 seen」導致永遠不再播報。
 *
 * @param array $countries 要加入 seen 清單的國家代碼陣列
 */
function mpu_visitor_pulse_commit_seen_countries(array $countries)
{
    $countries = array_values(array_filter(array_map('strval', $countries)));
    if (empty($countries)) {
        return;
    }

    $seen = get_transient('mpu_visitor_pulse_seen_countries');
    if (!is_array($seen)) {
        $seen = [];
    }

    $merged = array_values(array_unique(array_merge($seen, $countries)));
    if (count($merged) > 50) {
        $merged = array_slice($merged, -50);
    }
    set_transient('mpu_visitor_pulse_seen_countries', $merged, DAY_IN_SECONDS);
}

/**
 * 偵測訪客脈動中「值得自發講」的訊號（純查詢，無 state mutation）
 *
 * 依優先序檢查三類訊號：
 *   1. foreign_visitor    過去 24 小時內首次出現的國家（對 mpu_visitor_pulse_seen_countries 比對但不寫入）
 *   2. traffic_spike      過去 1 小時人類訪客數 ≥ 上 1 小時的 1.5 倍且 ≥ 5
 *   3. late_night_visitor 深夜（00:00–04:59）有人類訪客
 *
 * foreign_visitor 命中時，回傳的陣列會帶 commit_on_success.seen_countries 欄位；
 * 呼叫端應在 LLM 成功生成台詞後再呼叫 mpu_visitor_pulse_commit_seen_countries() 寫入。
 *
 * @return array{type:string,data:array,commit_on_success?:array}|false
 */
function mpu_detect_visitor_pulse_event()
{
    if (!class_exists('wp_slimstat')) {
        return false;
    }

    global $wpdb;
    $table = $wpdb->prefix . 'slim_stats';
    $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
    if ($exists !== $table) {
        return false;
    }

    // 1) 罕見國家偵測（過去 60 分鐘）— 純查詢，不寫入 seen
    $pulse = mpu_get_visitor_pulse(60);
    if (!empty($pulse['countries'])) {
        $seen = get_transient('mpu_visitor_pulse_seen_countries');
        if (!is_array($seen)) {
            $seen = [];
        }

        $new_countries = array_values(array_diff($pulse['countries'], $seen));
        if (!empty($new_countries)) {
            $first = $new_countries[0];

            return [
                'type' => 'foreign_visitor',
                'data' => [
                    'country_code' => $first,
                    'country_name' => function_exists('mpu_country_code_to_name')
                        ? mpu_country_code_to_name($first)
                        : $first,
                ],
                'commit_on_success' => [
                    'seen_countries' => $new_countries,
                ],
            ];
        }
    }

    // 2) 流量峰：過去 1 小時 vs 上一個 1 小時（僅計人類）
    $now = time();
    $current_hour = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(DISTINCT ip) FROM {$table}
         WHERE dt > %d AND browser_type != 1",
        $now - 3600
    ));
    $previous_hour = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(DISTINCT ip) FROM {$table}
         WHERE dt > %d AND dt <= %d AND browser_type != 1",
        $now - 7200,
        $now - 3600
    ));

    if ($current_hour >= 5 && $previous_hour > 0 && $current_hour >= $previous_hour * 1.5) {
        return [
            'type' => 'traffic_spike',
            'data' => [
                'current'  => $current_hour,
                'previous' => $previous_hour,
                'ratio'    => round($current_hour / max(1, $previous_hour), 1),
            ],
        ];
    }

    // 3) 深夜訪客（伺服器時區 00:00–04:59）
    $hour = (int) current_time('G');
    if ($hour >= 0 && $hour < 5) {
        $night_human = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT ip) FROM {$table}
             WHERE dt > %d AND browser_type != 1",
            $now - 1800
        ));

        if ($night_human >= 1) {
            return [
                'type' => 'late_night_visitor',
                'data' => [
                    'count' => $night_human,
                    'hour'  => $hour,
                ],
            ];
        }
    }

    return false;
}
