<?php

/**
 * AJAX 處理器
 * 
 * @package MP_Ukagaka
 * @subpackage AJAX
 */

if (!defined('ABSPATH')) {
    exit();
}

/**
 * AJAX 處理器：獲取下一句對話
 * 支援 LLM 生成對話或從外部文件讀取
 */
function mpu_ajax_nextmsg()
{
    // 驗證 Nonce（強制）
    $request_data = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
    if (!isset($request_data['mpu_nonce']) || !wp_verify_nonce($request_data['mpu_nonce'], 'mpu_ajax_nonce')) {
        wp_send_json(['error' => __('安全性驗證失敗', 'mp-ukagaka')]);
        return;
    }

    // 速率限制（防止濫用）- 20次/分鐘
    mpu_enforce_rate_limit('nextmsg', 20, 60);

    $mpu_opt = mpu_get_option();

    $cur_num = isset($request_data["cur_num"])
        ? sanitize_text_field($request_data["cur_num"])
        : ($mpu_opt["cur_ukagaka"] ?? "default_1");
    $cur_msgnum = isset($request_data["cur_msgnum"])
        ? intval($request_data["cur_msgnum"])
        : 0;

    $is_llm_enabled = mpu_is_llm_replace_dialogue_enabled();

    if ($is_llm_enabled) {
        $last_response = isset($request_data['last_response'])
            ? sanitize_text_field($request_data['last_response'])
            : '';

        $response_history = [];
        if (isset($request_data['response_history'])) {
            $history_json = wp_unslash($request_data['response_history']);
            $decoded_history = json_decode($history_json, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_history)) {
                $response_history = array_slice($decoded_history, -10);
                $response_history = array_map('sanitize_text_field', $response_history);
            }
        }

        // 獲取上次訪問時間（-1 = 首次訪問，>= 0 = 距離上次訪問的小時數）
        $last_visit_hours = isset($request_data['last_visit_hours'])
            ? intval($request_data['last_visit_hours'])
            : -1;

        $llm_msg = mpu_generate_llm_dialogue($cur_num, $last_response, $response_history, $last_visit_hours);

        $use_fallback = ($llm_msg === 'MPU_USE_FALLBACK' || $llm_msg === 'MPU_OLLAMA_BUSY');

        if ($llm_msg !== false && $llm_msg !== 'MPU_OLLAMA_NOT_AVAILABLE' && !$use_fallback) {
            $msg = $llm_msg;
            $msgnum = 0;

            // ===== 統計：記錄自言自語對話（LLM 生成） =====
            if (function_exists('mpu_record_conversation')) {
                mpu_record_conversation('auto_talk');
            }
        } elseif ($use_fallback || $llm_msg === false) {
            $msg_array = mpu_get_msg_arr($cur_num);
            $msgs = $msg_array["msg"] ?? [];
            $total = count($msgs);

            if ($total > 0) {
                $msgnum = mt_rand(0, $total - 1);
                $msg = $msgs[$msgnum];

                if (defined('WP_DEBUG') && WP_DEBUG) {
                    $reason = $llm_msg === 'MPU_USE_FALLBACK' ? '重複檢測' : ($llm_msg === 'MPU_OLLAMA_BUSY' ? 'Ollama 忙碌' : '生成失敗');
                    if (function_exists('mpu_debug_log')) {
                        mpu_debug_log('MP Ukagaka - ' . $reason . '，改用內建對話');
                    } else {
                        error_log('MP Ukagaka - ' . $reason . '，改用內建對話');
                    }
                }
            } else {
                $msg = __("無對話內容", "mp-ukagaka");
                $msgnum = 0;
            }
        } else {
            $msg = __("本機 Ollama 程式未啟動，請檢查 Ollama 服務是否正在運行。", "mp-ukagaka");
            $msgnum = 0;

            if (defined('WP_DEBUG') && WP_DEBUG) {
                if (function_exists('mpu_debug_log')) {
                    mpu_debug_log('MP Ukagaka - LLM 生成失敗，返回錯誤提示。llm_msg = ' . ($llm_msg === false ? 'false' : $llm_msg));
                } else {
                    error_log('MP Ukagaka - LLM 生成失敗，返回錯誤提示。llm_msg = ' . ($llm_msg === false ? 'false' : $llm_msg));
                }
            }
        }
    } else {
        $msg_array = mpu_get_msg_arr($cur_num);
        $msgs = $msg_array["msg"] ?? [];
        $total = count($msgs);

        if (($mpu_opt["next_msg"] ?? 0) == 0) {
            $next = $cur_msgnum + 1;
            if (isset($msgs[$next])) {
                $msg = $msgs[$next];
                $msgnum = $next;
            } else {
                $msg = $msgs[0] ?? __("無對話內容", "mp-ukagaka");
                $msgnum = 0;
            }
        } else {
            if ($total > 0) {
                $msgnum = mt_rand(0, $total - 1);
                $msg = $msgs[$msgnum];
            } else {
                $msg = __("無對話內容", "mp-ukagaka");
                $msgnum = 0;
            }
        }
    }

    // 限制回應長度（從 manifest.json 的 settings.max_response_length 讀取，預設 500）
    $max_length = 500;
    if (function_exists('mpu_get_personality_max_response_length')) {
        $max_length = mpu_get_personality_max_response_length(null, $cur_num);
    }
    if (mb_strlen($msg, 'UTF-8') > $max_length) {
        $msg = mb_substr($msg, 0, $max_length, 'UTF-8') . '...';
    }

    // 分析對話內容的情緒，獲取對應的表情
    $emoji = null;
    if (function_exists('mpu_analyze_emoji_from_text') && !empty($msg)) {
        // 獲取 personality_id 以載入角色專屬的表情關鍵字
        $personality_id = null;
        if (function_exists('mpu_get_personality_id_from_ukagaka_name')) {
            $personality_id = mpu_get_personality_id_from_ukagaka_name($cur_num);
        }
        $emoji = mpu_analyze_emoji_from_text($msg, $personality_id);
    }

    wp_send_json([
        "msg" => $msg,
        "msgnum" => $msgnum,
        "emoji" => $emoji  // 表情文件名，如 'happy.png' 或 null
    ]);
}
add_action('wp_ajax_mpu_nextmsg', 'mpu_ajax_nextmsg');
add_action('wp_ajax_nopriv_mpu_nextmsg', 'mpu_ajax_nextmsg');


/**
 * AJAX 處理器：擴展功能選單
 */
function mpu_ajax_extend()
{
    // 驗證 Nonce（強制）
    $request_data = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
    if (!isset($request_data['mpu_nonce']) || !wp_verify_nonce($request_data['mpu_nonce'], 'mpu_ajax_nonce')) {
        wp_send_json(['error' => __('安全性驗證失敗', 'mp-ukagaka')]);
        return;
    }

    // 速率限制 - 10次/分鐘
    mpu_enforce_rate_limit('extend', 10, 60);

    echo '<a onclick="mpuChange(\'\')" href="javascript:void(0);">' .
        __("更換偽春菜", "mp-ukagaka") .
        "</a>";
    wp_die();
}
add_action('wp_ajax_mpu_extend', 'mpu_ajax_extend');
add_action('wp_ajax_nopriv_mpu_extend', 'mpu_ajax_extend');

/**
 * AJAX 處理器：切換偽春菜人物或顯示選單
 */
function mpu_ajax_change()
{
    // 驗證 Nonce（強制）
    $request_data = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
    if (!isset($request_data['mpu_nonce']) || !wp_verify_nonce($request_data['mpu_nonce'], 'mpu_ajax_nonce')) {
        wp_send_json(['error' => __('安全性驗證失敗', 'mp-ukagaka')]);
        return;
    }

    // 速率限制 - 10次/分鐘
    mpu_enforce_rate_limit('change', 10, 60);

    $mpu_opt = mpu_get_option();

    if (!isset($_GET["mpu_num"])) {
        echo mpu_ukagaka_list();
        wp_die();
    }

    $mpu_num = sanitize_text_field($_GET["mpu_num"]);
    if (empty($mpu_opt["ukagakas"][$mpu_num])) {
        $mpu_num = "default_1";
    }

    $temp = [];
    // 一律從外部文件讀取，不再使用內部對話
    $ext = $mpu_opt["external_file_format"] ?? "txt";
    $dialog_filename = $mpu_opt["ukagakas"][$mpu_num]["dialog_filename"] ?? $mpu_num;
    $temp["msglist"] = [
        "msgall" => 0,
        "auto_msg" => $mpu_opt["auto_msg"] ?? "",
        "msg" => [],
    ];
    $temp["shell"] = $mpu_opt["ukagakas"][$mpu_num]["shell"] ?? "";
    // 新增 shell_info 欄位
    $temp["shell_info"] = mpu_get_shell_info($mpu_num);
    $temp["msg"] = ""; // 前端會透過 loadExternalDialog 載入實際對話
    $temp["name"] = $mpu_opt["ukagakas"][$mpu_num]["name"] ?? "";
    $temp["num"] = $mpu_num;
    $temp["dialog_filename"] = $dialog_filename;
    $temp["data_file"] = "dialogs/" . $dialog_filename . "." . $ext;

    $cookie_path = defined('SITECOOKIEPATH') ? SITECOOKIEPATH : '/';
    $cookie_domain = defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : '';

    setcookie(
        "mpu_ukagaka_" . COOKIEHASH,
        $mpu_num,
        time() + DAY_IN_SECONDS,
        $cookie_path,
        $cookie_domain,
        is_ssl(),
        true
    );

    wp_send_json($temp);
}
add_action('wp_ajax_mpu_change', 'mpu_ajax_change');
add_action('wp_ajax_nopriv_mpu_change', 'mpu_ajax_change');


/**
 * AJAX 處理器：獲取前端設定
 */
function mpu_ajax_get_settings()
{
    // 速率限制 - 30次/分鐘
    mpu_enforce_rate_limit('get_settings', 30, 60);

    $mpu_opt = mpu_get_option();

    // 獲取當前人格 ID
    $current_personality = function_exists('mpu_get_current_personality_id')
        ? mpu_get_current_personality_id()
        : null;

    // 獲取睡眠模式設定（00:00~06:00 時減少發話頻率）
    // ★ 傳入 personality_id 以正確判斷該角色的睡眠狀態
    $sleep_mode = function_exists('mpu_get_sleep_mode_settings')
        ? mpu_get_sleep_mode_settings($current_personality)
        : ['enabled' => false, 'frequency_multiplier' => 1.0];

    $settings = [
        "auto_talk" => !empty($mpu_opt["auto_talk"]),
        "auto_talk_interval" => intval($mpu_opt["auto_talk_interval"] ?? 8),
        "typewriter_speed" => intval($mpu_opt["typewriter_speed"] ?? 40),
        "ai_enabled" => !empty($mpu_opt["ai_enabled"]),
        "ai_probability" => intval($mpu_opt["ai_probability"] ?? 10),
        "ai_trigger_pages" => sanitize_text_field($mpu_opt["ai_trigger_pages"] ?? "is_single"),
        "ai_text_color" => sanitize_hex_color($mpu_opt["ai_text_color"] ?? "#000000"),
        "ai_display_duration" => intval($mpu_opt["ai_display_duration"] ?? 8),
        "ai_greet_first_visit" => !empty($mpu_opt["ai_greet_first_visit"]),
        "ollama_replace_dialogue" => mpu_is_llm_replace_dialogue_enabled(),
        "enable_chat_mode" => !empty($mpu_opt["enable_chat_mode"]),
        "sleep_mode" => $sleep_mode,  // 睡眠模式設定
        // 注意：頁面條件檢查改為在前端進行，因為 AJAX 請求中 WordPress 條件標籤可能無法正確工作
    ];
    wp_send_json($settings);
}
add_action('wp_ajax_mpu_get_settings', 'mpu_ajax_get_settings');
add_action('wp_ajax_nopriv_mpu_get_settings', 'mpu_ajax_get_settings');

/**
 * AJAX 處理器：載入外部對話檔案
 * 使用安全文件讀取函數（安全性強化）
 */
function mpu_ajax_load_dialog()
{
    // 驗證 Nonce（強制）
    $request_data = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
    if (!isset($request_data['mpu_nonce']) || !wp_verify_nonce($request_data['mpu_nonce'], 'mpu_ajax_nonce')) {
        wp_send_json(['error' => __('安全性驗證失敗', 'mp-ukagaka')]);
        return;
    }

    // 速率限制 - 30次/分鐘
    mpu_enforce_rate_limit('load_dialog', 30, 60);

    $mpu_opt = mpu_get_option();
    $file = isset($_GET["file"])
        ? basename(sanitize_text_field($_GET["file"]))
        : "";

    if (
        $file === "" ||
        !preg_match('/^[a-zA-Z0-9_\-]+\.(json|txt)$/', $file)
    ) {
        wp_send_json(["error" => "未指定或不合法的檔名"]);
    }

    $file_path = mpu_get_dialogs_dir() . "/" . $file;

    // 使用安全文件讀取函數（安全性強化）
    $content = mpu_secure_file_read($file_path);

    if (is_wp_error($content)) {
        wp_send_json(["error" => $content->get_error_message()]);
    }

    $ext = pathinfo($file, PATHINFO_EXTENSION);

    if ($ext === "json") {
        $json = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json([
                "error" => "JSON 檔案格式錯誤：" . json_last_error_msg(),
            ]);
        }
        if (empty($json["messages"]) || !is_array($json["messages"]) || count($json["messages"]) === 0) {
            wp_send_json([
                "error" => "對話文件為空，請檢查對話文件內容",
            ]);
        }
        $msg_array = $json["messages"];
    } else {
        $msg_array = mpu_str2array($content);
        // 檢查 TXT 文件是否為空
        if (empty($msg_array) || !is_array($msg_array) || count($msg_array) === 0) {
            wp_send_json([
                "error" => "對話文件為空，請檢查對話文件內容",
            ]);
        }
    }

    $arr = [
        "msgall" => max(0, count($msg_array) - 1),
        "auto_msg" => $mpu_opt["auto_msg"] ?? "",
        "msg" => mpu_msg_code($msg_array),
        "next_msg" => intval($mpu_opt["next_msg"] ?? 0),
        "default_msg" => intval($mpu_opt["default_msg"] ?? 0),
    ];

    // 確保 auto_msg 也是經過代碼處理的
    $auto_msg_array = mpu_msg_code([$arr["auto_msg"]]);
    $arr["auto_msg"] = implode(" ", $auto_msg_array);

    wp_send_json($arr);
}
add_action('wp_ajax_mpu_load_dialog', 'mpu_ajax_load_dialog');
add_action('wp_ajax_nopriv_mpu_load_dialog', 'mpu_ajax_load_dialog');

/**
 * AJAX 處理器：AI 上下文對話
 * 已分拆至 ajax-chat-handlers-llm.php（對話相關）和 ajax-touch-handlers-llm.php（觸摸相關）
 */

function mpu_ajax_get_visitor_info()
{
    // 速率限制 - 30次/分鐘
    mpu_enforce_rate_limit('get_visitor_info', 30, 60);

    global $wpdb;

    $referrer = isset($_SERVER['HTTP_REFERER']) ? esc_url_raw($_SERVER['HTTP_REFERER']) : "";
    $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : "";
    $ip = mpu_get_client_ip();

    $visitor_info = [
        "referrer" => $referrer,
        "user_agent" => $user_agent,
        "ip" => $ip,
        "is_direct" => empty($referrer),
        "slimstat_enabled" => false,
    ];

    if (class_exists('wp_slimstat')) {
        $visitor_info["slimstat_enabled"] = true;

        global $wpdb;
        $slimstat_table = $wpdb->prefix . 'slim_stats';

        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $slimstat_table));
        if ($table_exists == $slimstat_table) {
            $query = $wpdb->prepare(
                "SELECT referer, country, city, browser, browser_type FROM {$slimstat_table} WHERE ip = %s ORDER BY dt DESC LIMIT 1",
                $ip
            );
            $result = $wpdb->get_row($query, OBJECT);

            if (!empty($result)) {
                if (!empty($result->referer)) {
                    $visitor_info["slimstat_referer"] = esc_url_raw($result->referer);
                    $visitor_info["referrer"] = $visitor_info["slimstat_referer"];
                }

                if (!empty($result->country)) {
                    $visitor_info["slimstat_country"] = sanitize_text_field($result->country);
                }

                if (!empty($result->city)) {
                    $visitor_info["slimstat_city"] = sanitize_text_field($result->city);
                }

                if (isset($result->browser_type)) {
                    $visitor_info["is_bot"] = (intval($result->browser_type) === 1);
                    $visitor_info["browser_type"] = intval($result->browser_type);
                } else {
                    $visitor_info["is_bot"] = false;
                    $visitor_info["browser_type"] = 0;
                }

                // 獲取瀏覽器名稱（BOT 名稱）
                if (!empty($result->browser)) {
                    $visitor_info["browser_name"] = sanitize_text_field($result->browser);
                }
            } else {
                if (class_exists('\SlimStat\Services\Browscap')) {
                    $browser = \SlimStat\Services\Browscap::get_browser();
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

    if (!empty($visitor_info["referrer"])) {
        $parsed_url = parse_url($visitor_info["referrer"]);
        $visitor_info["referrer_host"] = isset($parsed_url['host']) ? $parsed_url['host'] : "";
        $visitor_info["referrer_path"] = isset($parsed_url['path']) ? $parsed_url['path'] : "";

        $search_engines = ['google', 'bing', 'yahoo', 'baidu', 'yandex', 'duckduckgo', 'naver'];
        $referrer_host_lower = strtolower($visitor_info["referrer_host"]);
        foreach ($search_engines as $engine) {
            if (strpos($referrer_host_lower, $engine) !== false) {
                $visitor_info["search_engine"] = $engine;
                break;
            }
        }
    }

    wp_send_json($visitor_info);
}
add_action('wp_ajax_mpu_get_visitor_info', 'mpu_ajax_get_visitor_info');
add_action('wp_ajax_nopriv_mpu_get_visitor_info', 'mpu_ajax_get_visitor_info');

function mpu_ajax_get_decoration_prompts()
{
    // 驗證 Nonce（強制）
    $nonce = isset($_POST['mpu_nonce']) ? $_POST['mpu_nonce'] : (isset($_GET['mpu_nonce']) ? $_GET['mpu_nonce'] : '');
    if (empty($nonce) || !wp_verify_nonce($nonce, 'mpu_ajax_nonce')) {
        wp_send_json(['error' => __('安全性驗證失敗', 'mp-ukagaka')]);
        return;
    }

    // 速率限制 - 20次/分鐘
    mpu_enforce_rate_limit('get_decoration_prompts', 20, 60);

    // 獲取當前人格 ID
    $personality_id = function_exists('mpu_get_current_personality_id')
        ? mpu_get_current_personality_id()
        : null;

    // 獲取所有可用的裝飾物類型
    $decoration_types = mpu_get_available_decoration_types($personality_id);

    // 檢查是否請求特定裝飾物的提示詞
    $request_data = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
    $decoration_type = isset($request_data['decoration_type'])
        ? sanitize_text_field($request_data['decoration_type'])
        : null;

    $prompts = [];

    // 獲取當前人格 ID
    $personality_id = function_exists('mpu_get_current_personality_id')
        ? mpu_get_current_personality_id()
        : null;

    if ($decoration_type) {
        // 返回特定裝飾物的提示詞
        $prompt = mpu_get_decoration_prompt($decoration_type, $personality_id);
        if ($prompt !== false) {
            $prompts[$decoration_type] = $prompt;
        } else {
            wp_send_json(['error' => __('未知的裝飾物類型', 'mp-ukagaka')]);
            return;
        }
    } else {
        // 返回所有裝飾物的提示詞
        foreach ($decoration_types as $type) {
            $prompt = mpu_get_decoration_prompt($type, $personality_id);
            if ($prompt !== false) {
                $prompts[$type] = $prompt;
            }
        }
    }

    wp_send_json(['prompts' => $prompts]);
}
add_action('wp_ajax_mpu_get_decoration_prompts', 'mpu_ajax_get_decoration_prompts');
add_action('wp_ajax_nopriv_mpu_get_decoration_prompts', 'mpu_ajax_get_decoration_prompts');

/**
 * AJAX 處理器：喚醒角色（賴床功能）
 * 
 * 當使用者在賴床時間按下「OK」按鈕時調用，
 * 記錄該 IP 為「已喚醒」，當天不再顯示睡眠狀態
 * 
 * ★ 必須傳入 personality_id 參數以實現人格隔離
 */
function mpu_ajax_wake_ghost()
{
    // 驗證 Nonce（強制）
    $request_data = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
    if (!isset($request_data['mpu_nonce']) || !wp_verify_nonce($request_data['mpu_nonce'], 'mpu_ajax_nonce')) {
        wp_send_json([
            'success' => false,
            'error' => __('安全性驗證失敗', 'mp-ukagaka')
        ]);
        return;
    }

    // 速率限制 - 10次/分鐘
    mpu_enforce_rate_limit('wake_ghost', 10, 60);

    // ★ 從請求中獲取 personality_id（必須）
    $personality_id = isset($request_data['personality_id'])
        ? sanitize_text_field($request_data['personality_id'])
        : null;

    // ★ 驗證 personality_id 是否有效
    if (empty($personality_id)) {
        wp_send_json([
            'success' => false,
            'error' => __('缺少角色 ID 參數', 'mp-ukagaka')
        ]);
        return;
    }

    // ★ 記錄 IP 為已喚醒（傳入 personality_id）
    if (function_exists('mpu_mark_ip_as_woken')) {
        $result = mpu_mark_ip_as_woken($personality_id);

        if (!$result) {
            // mpu_mark_ip_as_woken 返回 false 的情況：
            // 1. oversleep_enabled = false（該角色未啟用賴床）
            // 2. 不在賴床時間範圍內
            wp_send_json([
                'success' => false,
                'error' => __('當前無法喚醒角色（可能未在賴床時間或未啟用賴床功能）', 'mp-ukagaka'),
                'debug_info' => [
                    'personality_id' => $personality_id,
                    'current_time' => wp_date('H:i:s'),
                ]
            ]);
            return;
        }
    }

    wp_send_json([
        'success' => true,
        'message' => __('角色已被喚醒', 'mp-ukagaka'),
        'personality_id' => $personality_id,
    ]);
}
add_action('wp_ajax_mpu_wake_ghost', 'mpu_ajax_wake_ghost');
add_action('wp_ajax_nopriv_mpu_wake_ghost', 'mpu_ajax_wake_ghost');

/**
 * AJAX 處理器：獲取 Shell 資訊（延遲載入）
 * 
 * 用於避免在網頁原始碼中直接暴露圖片路徑
 * 前端透過 AJAX 動態獲取 shell info 後再初始化 Canvas
 */
function mpu_ajax_get_shell_info()
{
    // 驗證 Nonce（強制）
    $request_data = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
    if (!isset($request_data['mpu_nonce']) || !wp_verify_nonce($request_data['mpu_nonce'], 'mpu_ajax_nonce')) {
        wp_send_json(['error' => __('安全性驗證失敗', 'mp-ukagaka')]);
        return;
    }

    // 速率限制 - 30次/分鐘
    mpu_enforce_rate_limit('get_shell_info', 30, 60);

    // 獲取 ukagaka_num 參數
    $mpu_opt = mpu_get_option();
    $ukagaka_num = isset($request_data['ukagaka_num'])
        ? sanitize_text_field($request_data['ukagaka_num'])
        : ($mpu_opt['cur_ukagaka'] ?? 'default_1');

    // 驗證 ukagaka_num 是否有效
    if (empty($mpu_opt['ukagakas'][$ukagaka_num])) {
        $ukagaka_num = 'default_1';
    }

    // 獲取 shell info
    $shell_info = mpu_get_shell_info($ukagaka_num);
    $ukagaka_name = $mpu_opt['ukagakas'][$ukagaka_num]['name'] ?? '';

    wp_send_json([
        'success' => true,
        'shell_info' => $shell_info,
        'ukagaka_name' => $ukagaka_name,
        'ukagaka_num' => $ukagaka_num,
    ]);
}
add_action('wp_ajax_mpu_get_shell_info', 'mpu_ajax_get_shell_info');
add_action('wp_ajax_nopriv_mpu_get_shell_info', 'mpu_ajax_get_shell_info');

/**
 * AJAX 處理器：獲取裝飾配置（延遲載入）
 * 
 * 用於避免在網頁原始碼中直接暴露裝飾圖片路徑
 * 前端透過 AJAX 動態獲取裝飾配置後再載入裝飾物
 */
function mpu_ajax_get_decoration_config()
{
    // 驗證 Nonce（強制）
    $request_data = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
    if (!isset($request_data['mpu_nonce']) || !wp_verify_nonce($request_data['mpu_nonce'], 'mpu_ajax_nonce')) {
        wp_send_json(['error' => __('安全性驗證失敗', 'mp-ukagaka')]);
        return;
    }

    // 速率限制 - 30次/分鐘
    mpu_enforce_rate_limit('get_decoration_config', 30, 60);

    // 獲取當前人格 ID
    $personality_id = function_exists('mpu_get_current_personality_id')
        ? mpu_get_current_personality_id()
        : 'Frieren';

    // 獲取裝飾配置
    $decoration_config = [];
    if (function_exists('mpu_load_personality_decorations')) {
        $decorations_data = mpu_load_personality_decorations($personality_id);
        if (!empty($decorations_data['items'])) {
            $decoration_config = $decorations_data['items'];
        }
    }

    // 獲取裝飾基礎 URL
    $main_file = defined('MPU_MAIN_FILE') ? MPU_MAIN_FILE : dirname(dirname(dirname(__FILE__))) . '/mp-ukagaka.php';
    $decorations_base_url = esc_url(plugins_url("ghost/{$personality_id}/decorations/", $main_file));

    // 獲取觸摸區域配置
    $touchzones_config = [];
    if (function_exists('mpu_load_personality_touchzones')) {
        $touchzones_config = mpu_load_personality_touchzones($personality_id);
    }

    // 獲取顯示設定
    $mpu_opt = mpu_get_option();
    $show_decorations = isset($mpu_opt['ukagakas']['default_1']['show_decorations'])
        ? $mpu_opt['ukagakas']['default_1']['show_decorations']
        : true;

    wp_send_json([
        'success' => true,
        'decorations_base_url' => $decorations_base_url,
        'decoration_config' => $decoration_config,
        'touchzones' => $touchzones_config,
        'show_decorations' => $show_decorations,
    ]);
}
add_action('wp_ajax_mpu_get_decoration_config', 'mpu_ajax_get_decoration_config');
add_action('wp_ajax_nopriv_mpu_get_decoration_config', 'mpu_ajax_get_decoration_config');

/**
 * AJAX 處理器：獲取表情配置（延遲載入）
 * 
 * 用於避免在網頁原始碼中直接暴露表情圖片路徑和關鍵字
 * 前端透過 AJAX 動態獲取表情配置後再進行表情匹配
 */
function mpu_ajax_get_emoji_config()
{
    // 驗證 Nonce（強制）
    $request_data = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
    if (!isset($request_data['mpu_nonce']) || !wp_verify_nonce($request_data['mpu_nonce'], 'mpu_ajax_nonce')) {
        wp_send_json(['error' => __('安全性驗證失敗', 'mp-ukagaka')]);
        return;
    }

    // 速率限制 - 30次/分鐘
    mpu_enforce_rate_limit('get_emoji_config', 30, 60);

    // 獲取當前人格 ID
    $personality_id = function_exists('mpu_get_current_personality_id')
        ? mpu_get_current_personality_id()
        : 'Frieren';

    // 獲取表情基礎 URL
    $emoji_base_url = '';
    if (function_exists('mpu_get_personality_emoji_url')) {
        $emoji_base_url = mpu_get_personality_emoji_url($personality_id);
    }

    // 獲取表情配置
    $emoji_config = [];
    if (function_exists('mpu_load_personality_emoji_config')) {
        $emoji_config = mpu_load_personality_emoji_config($personality_id);
    }

    // 獲取表情關鍵字映射
    $emoji_mappings = [];
    if (function_exists('mpu_load_personality_emoji_keywords')) {
        $emoji_mappings = mpu_load_personality_emoji_keywords($personality_id);
    }

    wp_send_json([
        'success' => true,
        'baseUrl' => $emoji_base_url,
        'supportedEmojis' => $emoji_config['supported'] ?? [],
        'mappings' => $emoji_mappings,
    ]);
}
add_action('wp_ajax_mpu_get_emoji_config', 'mpu_ajax_get_emoji_config');
add_action('wp_ajax_nopriv_mpu_get_emoji_config', 'mpu_ajax_get_emoji_config');

/**
 * AJAX 處理器：統一初始化（性能優化）
 * 
 * 合併 shell_info + decoration_config + emoji_config + settings
 * 減少頁面載入時的 AJAX 請求數（4 → 1）
 */
function mpu_ajax_init()
{
    // 驗證 Nonce（強制）
    $request_data = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
    if (!isset($request_data['mpu_nonce']) || !wp_verify_nonce($request_data['mpu_nonce'], 'mpu_ajax_nonce')) {
        wp_send_json(['error' => __('安全性驗證失敗', 'mp-ukagaka')]);
        return;
    }

    // 速率限制 - 30次/分鐘
    mpu_enforce_rate_limit('init', 30, 60);

    $mpu_opt = mpu_get_option();

    // 獲取 ukagaka_num 參數
    $ukagaka_num = isset($request_data['ukagaka_num'])
        ? sanitize_text_field($request_data['ukagaka_num'])
        : ($mpu_opt['cur_ukagaka'] ?? 'default_1');

    // 驗證 ukagaka_num 是否有效
    if (empty($mpu_opt['ukagakas'][$ukagaka_num])) {
        $ukagaka_num = 'default_1';
    }

    // 獲取當前人格 ID
    $personality_id = function_exists('mpu_get_current_personality_id')
        ? mpu_get_current_personality_id()
        : 'Frieren';

    $main_file = defined('MPU_MAIN_FILE') ? MPU_MAIN_FILE : dirname(dirname(dirname(__FILE__))) . '/mp-ukagaka.php';

    // ===== 1. Shell Info =====
    $shell_info = mpu_get_shell_info($ukagaka_num);
    $ukagaka_name = $mpu_opt['ukagakas'][$ukagaka_num]['name'] ?? '';

    // ===== 2. Decoration Config =====
    $decoration_config = [];
    if (function_exists('mpu_load_personality_decorations')) {
        $decorations_data = mpu_load_personality_decorations($personality_id);
        if (!empty($decorations_data['items'])) {
            $decoration_config = $decorations_data['items'];
        }
    }
    $decorations_base_url = esc_url(plugins_url("ghost/{$personality_id}/decorations/", $main_file));
    $touchzones_config = function_exists('mpu_load_personality_touchzones')
        ? mpu_load_personality_touchzones($personality_id)
        : [];
    $show_decorations = isset($mpu_opt['ukagakas']['default_1']['show_decorations'])
        ? $mpu_opt['ukagakas']['default_1']['show_decorations']
        : true;

    // ===== 3. Emoji Config =====
    $emoji_base_url = function_exists('mpu_get_personality_emoji_url')
        ? mpu_get_personality_emoji_url($personality_id)
        : '';
    $emoji_config = function_exists('mpu_load_personality_emoji_config')
        ? mpu_load_personality_emoji_config($personality_id)
        : [];
    $emoji_mappings = function_exists('mpu_load_personality_emoji_keywords')
        ? mpu_load_personality_emoji_keywords($personality_id)
        : [];

    // ===== 4. Settings =====
    $current_personality = function_exists('mpu_get_current_personality_id')
        ? mpu_get_current_personality_id()
        : null;
    $sleep_mode = function_exists('mpu_get_sleep_mode_settings')
        ? mpu_get_sleep_mode_settings($current_personality)
        : ['enabled' => false, 'frequency_multiplier' => 1.0];

    $settings = [
        "auto_talk" => !empty($mpu_opt["auto_talk"]),
        "auto_talk_interval" => intval($mpu_opt["auto_talk_interval"] ?? 8),
        "typewriter_speed" => intval($mpu_opt["typewriter_speed"] ?? 40),
        "ai_enabled" => !empty($mpu_opt["ai_enabled"]),
        "ai_probability" => intval($mpu_opt["ai_probability"] ?? 10),
        "ai_trigger_pages" => sanitize_text_field($mpu_opt["ai_trigger_pages"] ?? "is_single"),
        "ai_text_color" => sanitize_hex_color($mpu_opt["ai_text_color"] ?? "#000000"),
        "ai_display_duration" => intval($mpu_opt["ai_display_duration"] ?? 8),
        "ai_greet_first_visit" => !empty($mpu_opt["ai_greet_first_visit"]),
        "ollama_replace_dialogue" => mpu_is_llm_replace_dialogue_enabled(),
        "enable_chat_mode" => !empty($mpu_opt["enable_chat_mode"]),
        "sleep_mode" => $sleep_mode,
    ];

    // ===== 合併回應 =====
    wp_send_json([
        'success' => true,
        // Shell
        'shell_info' => $shell_info,
        'ukagaka_name' => $ukagaka_name,
        'ukagaka_num' => $ukagaka_num,
        // Decorations
        'decorations_base_url' => $decorations_base_url,
        'decoration_config' => $decoration_config,
        'touchzones' => $touchzones_config,
        'show_decorations' => $show_decorations,
        // Emojis (保持與 mpu_ajax_get_emoji_config 一致的命名)
        'baseUrl' => $emoji_base_url,
        'supportedEmojis' => $emoji_config['supported'] ?? [],
        'mappings' => $emoji_mappings,
        // Settings
        'settings' => $settings,
    ]);
}
add_action('wp_ajax_mpu_init', 'mpu_ajax_init');
add_action('wp_ajax_nopriv_mpu_init', 'mpu_ajax_init');
