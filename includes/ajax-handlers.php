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
    $mpu_opt = mpu_get_option();

    // 支援 POST 和 GET 兩種方式（優先使用 POST，向後兼容 GET）
    $request_data = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;

    $cur_num = isset($request_data["cur_num"])
        ? sanitize_text_field($request_data["cur_num"])
        : $mpu_opt["cur_ukagaka"];
    $cur_msgnum = isset($request_data["cur_msgnum"])
        ? intval($request_data["cur_msgnum"])
        : 0;

    // 檢查是否啟用了「使用 LLM 取代內建對話」
    $is_llm_enabled = mpu_is_llm_replace_dialogue_enabled();

    // 調試日誌已移除，避免 debug.log 中出現過多訊息
    // 如需調試，可臨時取消以下註釋：
    // if (defined('WP_DEBUG') && WP_DEBUG) {
    //     error_log('MP Ukagaka - mpu_ajax_nextmsg: is_llm_enabled = ' . ($is_llm_enabled ? 'true' : 'false'));
    //     error_log('MP Ukagaka - mpu_ajax_nextmsg: ai_provider = ' . ($mpu_opt['ai_provider'] ?? 'not set'));
    //     error_log('MP Ukagaka - mpu_ajax_nextmsg: ollama_replace_dialogue = ' . (isset($mpu_opt['ollama_replace_dialogue']) && $mpu_opt['ollama_replace_dialogue'] ? 'true' : 'false'));
    // }

    if ($is_llm_enabled) {
        // 獲取上一次回應（從 POST/GET 參數中獲取，用於避免重複對話）
        // 注意：sanitize_text_field 內部已處理 wp_unslash，但為了明確性，這裡直接使用
        $last_response = isset($request_data['last_response'])
            ? sanitize_text_field($request_data['last_response'])
            : '';

        // ★★★ 獲取回應歷史（用於更嚴格的重複檢測）★★★
        $response_history = [];
        if (isset($request_data['response_history'])) {
            // 1. 先使用 wp_unslash 去除 WordPress 自動添加的反斜線
            // WordPress 會對所有 $_POST、$_GET 數據自動進行 addslashes 處理
            // 這會導致 JSON 字串中的引號 " 變成 \"，導致 json_decode 失敗
            $history_json = wp_unslash($request_data['response_history']);

            // 2. 解碼原始 JSON
            $decoded_history = json_decode($history_json, true);

            if (is_array($decoded_history)) {
                // 3. 限制歷史記錄數量
                $response_history = array_slice($decoded_history, -5);

                // 4. 對解碼後的內容進行消毒 (Sanitization)
                $response_history = array_map('sanitize_text_field', $response_history);
            }
        }

        // 使用 LLM 生成對話
        $llm_msg = mpu_generate_llm_dialogue($cur_num, $last_response, $response_history);

        // 檢查是否需要使用內建對話（重複檢測或 Ollama 忙碌）
        $use_fallback = ($llm_msg === 'MPU_USE_FALLBACK' || $llm_msg === 'MPU_OLLAMA_BUSY');

        if ($llm_msg !== false && $llm_msg !== 'MPU_OLLAMA_NOT_AVAILABLE' && !$use_fallback) {
            // LLM 生成成功，使用生成的對話
            $msg = $llm_msg;
            $msgnum = 0; // LLM 生成的對話不需要 msgnum
        } elseif ($use_fallback || $llm_msg === false) {
            // ★★★ 重複檢測觸發或 Ollama 忙碌或生成失敗，使用內建對話 ★★★
            $msg_array = mpu_get_msg_arr($cur_num);
            $msgs = $msg_array["msg"] ?? [];
            $total = count($msgs);

            if ($total > 0) {
                // 隨機選擇一條內建對話
                $msg = $msgs[mt_rand(0, $total - 1)];
                $msgnum = array_search($msg, $msgs, true);
                if ($msgnum === false) {
                    $msgnum = 0;
                }
                
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    $reason = $llm_msg === 'MPU_USE_FALLBACK' ? '重複檢測' : ($llm_msg === 'MPU_OLLAMA_BUSY' ? 'Ollama 忙碌' : '生成失敗');
                    error_log('MP Ukagaka - ' . $reason . '，改用內建對話');
                }
            } else {
                $msg = __("無對話內容", "mp-ukagaka");
                $msgnum = 0;
            }
        } else {
            // 當 Ollama 未啟動時，顯示錯誤提示
            $msg = __("本機 Ollama 程式未啟動，請檢查 Ollama 服務是否正在運行。", "mp-ukagaka");
            $msgnum = 0;

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('MP Ukagaka - LLM 生成失敗，返回錯誤提示。llm_msg = ' . ($llm_msg === false ? 'false' : $llm_msg));
            }
        }
    } else {
        // 正常模式：從外部文件讀取對話
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
            $msg = $total > 0 ? $msgs[mt_rand(0, $total - 1)] : __("無對話內容", "mp-ukagaka");
            $msgnum = array_search($msg, $msgs, true);
            if ($msgnum === false) {
                $msgnum = 0;
            }
        }
    }

    wp_send_json(["msg" => $msg, "msgnum" => $msgnum]);
}
add_action('wp_ajax_mpu_nextmsg', 'mpu_ajax_nextmsg');
add_action('wp_ajax_nopriv_mpu_nextmsg', 'mpu_ajax_nextmsg');


/**
 * AJAX 處理器：擴展功能選單
 */
function mpu_ajax_extend()
{
    echo '<a onclick="mpuChange(\'\')" href="javascript:void(0);">' .
        __("更換春菜", "mp-ukagaka") .
        "</a>";
    wp_die();
}
add_action('wp_ajax_mpu_extend', 'mpu_ajax_extend');
add_action('wp_ajax_nopriv_mpu_extend', 'mpu_ajax_extend');

/**
 * AJAX 處理器：切換春菜人物或顯示選單
 */
function mpu_ajax_change()
{
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
    $temp["shell"] = $mpu_opt["ukagakas"][$mpu_num]["shell"];
    // 新增 shell_info 欄位
    $temp["shell_info"] = mpu_get_shell_info($mpu_num);
    $temp["msg"] = ""; // 前端會透過 loadExternalDialog 載入實際對話
    $temp["name"] = $mpu_opt["ukagakas"][$mpu_num]["name"];
    $temp["num"] = $mpu_num;
    $temp["dialog_filename"] = $dialog_filename;
    $temp["data_file"] = "dialogs/" . $dialog_filename . "." . $ext;

    // 使用 SITECOOKIEPATH（通常是 '/'）來設定 Cookie
    // 而不是 COOKIEPATH（可能是 /wp-admin/）
    // 這樣前台頁面才能讀取到 Cookie
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
    $mpu_opt = mpu_get_option();
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
 * 根據頁面內容生成 AI 回應
 */
function mpu_ajax_chat_context()
{
    // 驗證 Nonce（如果提供）
    // 注意：Nonce 驗證是可選的，主要依賴速率限制來防止濫用
    if (isset($_POST['mpu_nonce'])) {
        if (!wp_verify_nonce($_POST['mpu_nonce'], 'mpu_ajax_nonce')) {
            wp_send_json(["error" => "安全性驗證失敗"]);
            return;
        }
    }

    // 速率限制（防止濫用）
    $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : '';
    $transient_key = 'mpu_ai_rate_limit_' . md5($ip);
    $rate_limit = get_transient($transient_key);

    if ($rate_limit !== false && $rate_limit >= 10) {
        wp_send_json(["error" => "請求過於頻繁，請稍後再試"]);
        return;
    }

    $mpu_opt = mpu_get_option();

    // 驗證 AI 是否啟用
    if (empty($mpu_opt["ai_enabled"])) {
        wp_send_json(["error" => "AI 功能未啟用"]);
        return;
    }

    // 獲取提供商（向後兼容：優先使用 llm_provider，否則使用 ai_provider）
    $provider = isset($mpu_opt["llm_provider"]) ? $mpu_opt["llm_provider"] : (isset($mpu_opt["ai_provider"]) ? $mpu_opt["ai_provider"] : "gemini");
    $api_key_encrypted = "";
    $api_key = "";

    // Ollama 不需要 API Key
    if ($provider !== "ollama") {
        switch ($provider) {
            case "openai":
                // 向後兼容：優先使用新設定鍵，否則使用舊設定鍵
                $api_key_encrypted = $mpu_opt["llm_openai_api_key"] ?? $mpu_opt["openai_api_key"] ?? "";
                break;
            case "claude":
                $api_key_encrypted = $mpu_opt["llm_claude_api_key"] ?? $mpu_opt["claude_api_key"] ?? "";
                break;
            case "gemini":
            default:
                $api_key_encrypted = $mpu_opt["llm_gemini_api_key"] ?? $mpu_opt["ai_api_key"] ?? "";
                break;
        }

        // 解密 API Key（安全性強化）
        $api_key = mpu_decrypt_api_key($api_key_encrypted);

        if (empty($api_key)) {
            wp_send_json(["error" => ucfirst($provider) . " API Key 未設定"]);
            return;
        }
    }

    // 獲取頁面內容
    $page_title = isset($_POST["page_title"]) ? sanitize_text_field($_POST["page_title"]) : "";
    $page_content = isset($_POST["page_content"]) ? sanitize_textarea_field($_POST["page_content"]) : "";

    // 限制內容長度，防止過大請求（使用多位元組函數避免 UTF-8 亂碼）
    if (mb_strlen($page_title, 'UTF-8') > 500) {
        $page_title = mb_substr($page_title, 0, 500, 'UTF-8');
    }
    if (mb_strlen($page_content, 'UTF-8') > 5000) {
        $page_content = mb_substr($page_content, 0, 5000, 'UTF-8');
    }

    if (empty($page_title) && empty($page_content)) {
        wp_send_json(["error" => "頁面內容為空"]);
        return;
    }

    // System Prompt：只讀取後台設定並做變數替換
    $wp_info = mpu_get_wordpress_info();
    $ukagaka_name = $mpu_opt['cur_ukagaka'] ?? 'default_1';
    $ukagaka_display_name = $mpu_opt['ukagakas'][$ukagaka_name]['name'] ?? '春菜';
    $language = $mpu_opt["ai_language"] ?? "zh-TW";

    // 獲取時間情境
    $time_context = mpu_get_time_context();

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

    $system_prompt = $mpu_opt["ai_system_prompt"] ?? "你是一個傲嬌的桌面助手「春菜」。你會用簡短、帶點傲嬌的語氣評論文章內容。回應請保持在 40 字以內。";
    $system_prompt = mpu_render_prompt_template($system_prompt, $variables);

    // User Prompt：包含用戶資訊、訪客資訊、文章內容
    $user_info = mpu_get_current_user_info();
    $visitor_info = mpu_get_visitor_info_for_llm();

    $user_prompt = "【當前用戶資訊】\n";
    if ($user_info['is_logged_in']) {
        $role_labels = [
            'administrator' => '管理員',
            'editor' => '編輯',
            'author' => '作者',
            'contributor' => '投稿者',
            'subscriber' => '訂閱者',
        ];
        $role_label = isset($role_labels[$user_info['primary_role']])
            ? $role_labels[$user_info['primary_role']]
            : $user_info['primary_role'];

        $user_prompt .= "用戶已登入：{$user_info['display_name']} ({$user_info['username']})\n";
        $user_prompt .= "角色：{$role_label}\n";
        if ($user_info['is_admin']) {
            $user_prompt .= "此用戶是網站管理員。\n";
        }
    } else {
        $user_prompt .= "用戶未登入（訪客）。\n";
    }

    $user_prompt .= "\n【訪客資訊】\n";
    if (!empty($visitor_info['is_bot']) && $visitor_info['is_bot']) {
        $bot_name = $visitor_info['browser_name'] ?? '未知のクローラー';
        $user_prompt .= "檢測到 BOT：{$bot_name}\n";
    }
    if (!empty($visitor_info['slimstat_country'])) {
        $user_prompt .= "來源地區：{$visitor_info['slimstat_country']}";
        if (!empty($visitor_info['slimstat_city'])) {
            $user_prompt .= " {$visitor_info['slimstat_city']}";
        }
        $user_prompt .= "\n";
    }

    $user_prompt .= "\n【文章內容】\n";
    $user_prompt .= "標題：{$page_title}\n\n";
    $user_prompt .= "內容摘要：{$page_content}";

    // 調用 AI API
    $result = mpu_call_ai_api(
        $provider,
        $api_key,
        $system_prompt,
        $user_prompt,
        $language,
        $mpu_opt // 傳遞完整設定以便獲取模型名稱
    );

    if (is_wp_error($result)) {
        wp_send_json(["error" => $result->get_error_message()]);
        return;
    }

    // 更新速率限制計數器
    $current_count = ($rate_limit !== false) ? intval($rate_limit) : 0;
    set_transient($transient_key, $current_count + 1, 60); // 60 秒內最多 10 次

    wp_send_json(["msg" => $result]);
}
add_action('wp_ajax_mpu_chat_context', 'mpu_ajax_chat_context');
add_action('wp_ajax_nopriv_mpu_chat_context', 'mpu_ajax_chat_context');

/**
 * AJAX 處理器：獲取訪客資訊
 * 使用 Slimstat API 獲取更詳細的訪客資訊
 */
function mpu_ajax_get_visitor_info()
{
    global $wpdb;

    // 從 $_SERVER 獲取基本資訊
    $referrer = isset($_SERVER['HTTP_REFERER']) ? esc_url_raw($_SERVER['HTTP_REFERER']) : "";
    $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : "";
    $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : "";

    // 準備返回的資訊
    $visitor_info = [
        "referrer" => $referrer,
        "user_agent" => $user_agent,
        "ip" => $ip,
        "is_direct" => empty($referrer),
        "slimstat_enabled" => false,
    ];

    // 使用 Slimstat 獲取更詳細的訪客資訊
    if (class_exists('wp_slimstat')) {
        $visitor_info["slimstat_enabled"] = true;

        // 直接查詢 Slimstat 資料庫
        global $wpdb;
        $slimstat_table = $wpdb->prefix . 'slim_stats';

        // 使用 prepare 防止 SQL 注入（安全性）
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $slimstat_table));
        if ($table_exists == $slimstat_table) {
            // 查詢當前 IP 最近的完整記錄（包含 BOT 資訊）
            $query = $wpdb->prepare(
                "SELECT referer, country, city, browser, browser_type FROM {$slimstat_table} WHERE ip = %s ORDER BY dt DESC LIMIT 1",
                $ip
            );
            $result = $wpdb->get_row($query, OBJECT);

            if (!empty($result)) {
                // 優先使用 Slimstat 的 referer（更準確）
                if (!empty($result->referer)) {
                    $visitor_info["slimstat_referer"] = esc_url_raw($result->referer);
                    $visitor_info["referrer"] = $visitor_info["slimstat_referer"];
                }

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
                } else {
                    $visitor_info["is_bot"] = false;
                    $visitor_info["browser_type"] = 0;
                }

                // 獲取瀏覽器名稱（BOT 名稱）
                if (!empty($result->browser)) {
                    $visitor_info["browser_name"] = sanitize_text_field($result->browser);
                }
            } else {
                // 如果資料庫中沒有記錄，嘗試從當前請求檢測 BOT
                // 使用 Slimstat 的 Browscap 服務來檢測
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

    // 解析 referrer 獲取來源資訊
    if (!empty($visitor_info["referrer"])) {
        $parsed_url = parse_url($visitor_info["referrer"]);
        $visitor_info["referrer_host"] = isset($parsed_url['host']) ? $parsed_url['host'] : "";
        $visitor_info["referrer_path"] = isset($parsed_url['path']) ? $parsed_url['path'] : "";

        // 判斷是否為搜尋引擎
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

/**
 * AJAX 處理器：首次訪客 AI 打招呼
 * 根據訪客資訊生成個性化問候語
 */
function mpu_ajax_chat_greet()
{
    // 驗證 Nonce（如果提供）
    // 注意：Nonce 驗證是可選的，主要依賴速率限制來防止濫用
    if (isset($_POST['mpu_nonce'])) {
        if (!wp_verify_nonce($_POST['mpu_nonce'], 'mpu_ajax_nonce')) {
            wp_send_json(["error" => "安全性驗證失敗"]);
            return;
        }
    }

    // 速率限制（防止濫用）
    $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : '';
    $transient_key = 'mpu_ai_greet_rate_limit_' . md5($ip);
    $rate_limit = get_transient($transient_key);

    if ($rate_limit !== false && $rate_limit >= 5) {
        wp_send_json(["error" => "請求過於頻繁，請稍後再試"]);
        return;
    }

    $mpu_opt = mpu_get_option();

    // 驗證 AI 是否啟用
    if (empty($mpu_opt["ai_enabled"])) {
        wp_send_json(["error" => "AI 功能未啟用"]);
        return;
    }

    // 驗證首次訪客打招呼是否啟用
    if (empty($mpu_opt["ai_greet_first_visit"])) {
        wp_send_json(["error" => "首次訪客打招呼功能未啟用"]);
        return;
    }

    // 獲取提供商（向後兼容：優先使用 llm_provider，否則使用 ai_provider）
    $provider = isset($mpu_opt["llm_provider"]) ? $mpu_opt["llm_provider"] : (isset($mpu_opt["ai_provider"]) ? $mpu_opt["ai_provider"] : "gemini");
    $api_key_encrypted = "";
    $api_key = "";

    // Ollama 不需要 API Key
    if ($provider !== "ollama") {
        switch ($provider) {
            case "openai":
                // 向後兼容：優先使用新設定鍵，否則使用舊設定鍵
                $api_key_encrypted = $mpu_opt["llm_openai_api_key"] ?? $mpu_opt["openai_api_key"] ?? "";
                break;
            case "claude":
                $api_key_encrypted = $mpu_opt["llm_claude_api_key"] ?? $mpu_opt["claude_api_key"] ?? "";
                break;
            case "gemini":
            default:
                $api_key_encrypted = $mpu_opt["llm_gemini_api_key"] ?? $mpu_opt["ai_api_key"] ?? "";
                break;
        }

        // 解密 API Key（安全性強化）
        $api_key = mpu_decrypt_api_key($api_key_encrypted);

        if (empty($api_key)) {
            wp_send_json(["error" => ucfirst($provider) . " API Key 未設定"]);
            return;
        }
    }

    // 獲取訪客資訊
    $referrer = isset($_POST["referrer"]) ? esc_url_raw($_POST["referrer"]) : "";
    $referrer_host = isset($_POST["referrer_host"]) ? sanitize_text_field($_POST["referrer_host"]) : "";
    $search_engine = isset($_POST["search_engine"]) ? sanitize_text_field($_POST["search_engine"]) : "";
    $is_direct = isset($_POST["is_direct"]) && $_POST["is_direct"] === "true";
    $country = isset($_POST["country"]) ? sanitize_text_field($_POST["country"]) : "";
    $city = isset($_POST["city"]) ? sanitize_text_field($_POST["city"]) : "";

    // 驗證輸入長度（安全性，使用多位元組函數避免 UTF-8 亂碼）
    if (mb_strlen($referrer, 'UTF-8') > 500) {
        $referrer = mb_substr($referrer, 0, 500, 'UTF-8');
    }
    if (mb_strlen($referrer_host, 'UTF-8') > 255) {
        $referrer_host = mb_substr($referrer_host, 0, 255, 'UTF-8');
    }
    if (mb_strlen($country, 'UTF-8') > 10) {
        $country = mb_substr($country, 0, 10, 'UTF-8');
    }
    if (mb_strlen($city, 'UTF-8') > 100) {
        $city = mb_substr($city, 0, 100, 'UTF-8');
    }

    // System Prompt：只讀取後台設定並做變數替換
    $wp_info = mpu_get_wordpress_info();
    $ukagaka_name = $mpu_opt['cur_ukagaka'] ?? 'default_1';
    $ukagaka_display_name = $mpu_opt['ukagakas'][$ukagaka_name]['name'] ?? '春菜';
    $language = $mpu_opt["ai_language"] ?? "zh-TW";

    // 獲取時間情境
    $time_context = mpu_get_time_context();

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

    $system_prompt = $mpu_opt["ai_greet_prompt"] ?? "你是一個友善的桌面助手「春菜」。當有訪客第一次來到網站時，你會根據訪客的來源（referrer）用親切的語氣打招呼。回應請保持在 50 字以內。";
    $system_prompt = mpu_render_prompt_template($system_prompt, $variables);

    // User Prompt：包含用戶資訊、訪客來源資訊
    $user_info = mpu_get_current_user_info();

    $user_prompt = "【當前用戶資訊】\n";
    if ($user_info['is_logged_in']) {
        $role_labels = [
            'administrator' => '管理員',
            'editor' => '編輯',
            'author' => '作者',
            'contributor' => '投稿者',
            'subscriber' => '訂閱者',
        ];
        $role_label = isset($role_labels[$user_info['primary_role']])
            ? $role_labels[$user_info['primary_role']]
            : $user_info['primary_role'];

        $user_prompt .= "用戶已登入：{$user_info['display_name']} ({$user_info['username']})\n";
        $user_prompt .= "角色：{$role_label}\n";
        if ($user_info['is_admin']) {
            $user_prompt .= "此用戶是網站管理員。\n";
        }
    } else {
        $user_prompt .= "用戶未登入（訪客）。\n";
    }

    $user_prompt .= "\n【訪客來源資訊】\n";
    $user_prompt .= "有訪客第一次來到網站。";

    if ($is_direct) {
        $user_prompt .= "訪客是直接輸入網址或從書籤訪問的（沒有來源網頁）。";
    } else if (!empty($search_engine)) {
        $user_prompt .= "訪客來自搜尋引擎「{$search_engine}」。";
    } else if (!empty($referrer_host)) {
        $user_prompt .= "訪客來自網站「{$referrer_host}」";
        if (!empty($referrer)) {
            $user_prompt .= "（{$referrer}）";
        }
        $user_prompt .= "。";
    } else {
        $user_prompt .= "訪客來源資訊不明。";
    }

    // 添加地理位置資訊（如果有的話）
    if (!empty($country)) {
        $user_prompt .= "訪客來自「{$country}」";
        if (!empty($city)) {
            $user_prompt .= "的「{$city}」";
        }
        $user_prompt .= "。";
    }

    $user_prompt .= "\n\n請用親切友善的語氣打招呼。";

    // 調試模式：記錄傳遞給 AI 的資訊
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log("MP Ukagaka - AI 打招呼提示詞:");
        error_log("  - Referrer: " . ($referrer ?: "無"));
        error_log("  - Referrer Host: " . ($referrer_host ?: "無"));
        error_log("  - Search Engine: " . ($search_engine ?: "無"));
        error_log("  - Is Direct: " . ($is_direct ? "是" : "否"));
        error_log("  - Country: " . ($country ?: "無"));
        error_log("  - City: " . ($city ?: "無"));
        error_log("  - User Prompt: " . $user_prompt);
    }

    // 調用 AI API
    $result = mpu_call_ai_api(
        $provider,
        $api_key,
        $system_prompt,
        $user_prompt,
        $language,
        $mpu_opt
    );

    if (is_wp_error($result)) {
        wp_send_json(["error" => $result->get_error_message()]);
        return;
    }

    // 更新速率限制計數器
    $current_count = ($rate_limit !== false) ? intval($rate_limit) : 0;
    set_transient($transient_key, $current_count + 1, 300); // 5 分鐘內最多 5 次

    wp_send_json(["msg" => $result]);
}
add_action('wp_ajax_mpu_chat_greet', 'mpu_ajax_chat_greet');
add_action('wp_ajax_nopriv_mpu_chat_greet', 'mpu_ajax_chat_greet');

/**
 * AJAX 處理器：測試 Ollama 連接
 * 驗證 Ollama 端點和模型是否可用
 */
function mpu_ajax_test_ollama_connection()
{
    check_ajax_referer('mpu_test_connection', 'nonce');

    $endpoint = sanitize_text_field($_POST['endpoint'] ?? 'http://localhost:11434');
    $model = sanitize_text_field($_POST['model'] ?? 'qwen3:8b');

    // 驗證端點 URL
    // 檢查輔助函數是否存在（確保 llm-functions.php 已載入）
    if (!function_exists('mpu_validate_ollama_endpoint')) {
        // 如果函數不存在，使用基本驗證
        $endpoint = rtrim($endpoint, '/');
        if (!preg_match('/^https?:\/\/.+/', $endpoint)) {
            wp_send_json_error(__('端點 URL 格式錯誤：必須是有效的 HTTP 或 HTTPS URL', 'mp-ukagaka'));
            return;
        }
        $timeout = 30; // 默認超時
        $is_remote = !preg_match('/localhost|127\.0\.0\.1|::1/', $endpoint);
        if ($is_remote) {
            $timeout = 45;
        }
    } else {
        $validated_endpoint = mpu_validate_ollama_endpoint($endpoint);
        if (is_wp_error($validated_endpoint)) {
            wp_send_json_error(sprintf(__('端點 URL 格式錯誤：%s', 'mp-ukagaka'), $validated_endpoint->get_error_message()));
            return;
        }
        $endpoint = $validated_endpoint;

        // 根據端點類型使用動態超時時間
        $timeout = mpu_get_ollama_timeout($endpoint, 'test');
        $is_remote = mpu_is_remote_endpoint($endpoint);
    }

    // 構建測試請求
    $api_url = rtrim($endpoint, '/') . '/api/chat';
    $request_body = [
        'model' => $model,
        'messages' => [
            ['role' => 'user', 'content' => 'Hi']
        ],
        'stream' => false,
        'options' => [
            'num_predict' => 50,  // 增加 token 數量，確保生成實際內容
            'temperature' => 0.7
        ]
    ];

    $response = wp_remote_post($api_url, [
        'headers' => ['Content-Type' => 'application/json'],
        'body' => wp_json_encode($request_body),
        'timeout' => $timeout,  // 動態超時：本地 30 秒，遠程 45 秒
    ]);

    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        $connection_type = $is_remote ? '遠程' : '本地';

        // 根據連接類型提供更詳細的錯誤訊息
        if (strpos($error_message, 'timeout') !== false || strpos($error_message, 'timed out') !== false) {
            if ($is_remote) {
                wp_send_json_error(sprintf(__('連接超時（已等待 %s 秒）。遠程連接可能需要更長時間，請檢查 Cloudflare Tunnel 或網絡狀況。', 'mp-ukagaka'), $timeout));
            } else {
                wp_send_json_error(sprintf(__('連接超時（已等待 %s 秒）。請確認 Ollama 服務是否正常運行。', 'mp-ukagaka'), $timeout));
            }
            return;
        }

        if (strpos($error_message, 'Connection refused') !== false || strpos($error_message, 'couldn\'t connect') !== false) {
            if ($is_remote) {
                wp_send_json_error(sprintf(__('無法連接到遠程 Ollama 服務。請確認 Cloudflare Tunnel 是否正在運行，端點 URL 是否正確。錯誤：%s', 'mp-ukagaka'), $error_message));
            } else {
                wp_send_json_error(sprintf(__('無法連接到 Ollama 服務。請確認 Ollama 是否正在運行。錯誤：%s', 'mp-ukagaka'), $error_message));
            }
            return;
        }

        $connection_type_text = $is_remote ? __('遠程', 'mp-ukagaka') : __('本地', 'mp-ukagaka');
        wp_send_json_error(sprintf(__('連接失敗（%s連接）：%s', 'mp-ukagaka'), $connection_type_text, $error_message));
        return;
    }

    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);

    if ($response_code === 200) {
        $data = json_decode($response_body, true);

        // 調試：記錄響應結構（僅在 WP_DEBUG 模式下）
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Ollama Test Response: ' . print_r($data, true));
        }

        // 檢查多種可能的響應格式
        $content = null;
        $has_content = false;

        if (!empty($data['message']['content'])) {
            // 優先使用 content（實際回應）
            $content = $data['message']['content'];
            $has_content = true;
        } elseif (!empty($data['content'])) {
            $content = $data['content'];
            $has_content = true;
        } elseif (isset($data['message']) && is_string($data['message'])) {
            $content = $data['message'];
            $has_content = true;
        } elseif (!empty($data['response'])) {
            $content = $data['response'];
            $has_content = true;
        } elseif (!empty($data['message']['thinking'])) {
            // 只有在沒有 content 時才使用 thinking（僅用於測試）
            $content = $data['message']['thinking'];
            $has_content = false; // 標記這不是實際內容
        }

        if (!empty($content)) {
            if ($has_content) {
                // 清除之前的失敗緩存，確保下次檢查時使用最新狀態
                $cache_key = 'mpu_ollama_available_' . md5($endpoint . $model);
                delete_transient($cache_key);
                // 設置成功緩存（5 分鐘）
                set_transient($cache_key, 1, 5 * MINUTE_IN_SECONDS);

                // 成功：返回簡短的確認訊息
                $preview = mb_substr($content, 0, 50);
                $connection_type_text = $is_remote ? __('遠程', 'mp-ukagaka') : __('本地', 'mp-ukagaka');
                wp_send_json_success(sprintf(__('連接成功（%s連接），模型響應正常（預覽：%s...）', 'mp-ukagaka'), $connection_type_text, $preview));
            } else {
                // 只有 thinking 沒有 content，提示用戶可能需要調整參數
                $preview = mb_substr($content, 0, 50);
                wp_send_json_success(sprintf(__('連接成功，但模型只返回思考過程（預覽：%s...）。實際使用時應會生成內容。', 'mp-ukagaka'), $preview));
            }
        } else {
            // 提供更詳細的錯誤信息和實際響應內容（用於調試）
            $response_keys = is_array($data) ? array_keys($data) : [];
            $response_preview = mb_substr($response_body, 0, 200);

            $debug_info = '響應鍵: [' . implode(', ', $response_keys) . ']';
            if (defined('WP_DEBUG') && WP_DEBUG) {
                $debug_info .= ' | 完整響應: ' . $response_preview;
            }

            wp_send_json_error('模型未返回有效響應。' . $debug_info . ' 請檢查模型是否正確載入或嘗試使用其他模型。');
        }
    } else {
        $error_body = wp_remote_retrieve_body($response);
        $error_data = json_decode($error_body, true);
        $error_message = isset($error_data['error']) ? $error_data['error'] : "HTTP {$response_code}：請檢查 Ollama 是否運行且模型已下載";
        wp_send_json_error($error_message);
    }
}
add_action('wp_ajax_mpu_test_ollama_connection', 'mpu_ajax_test_ollama_connection');

/**
 * AJAX 處理器：測試 Gemini 連接
 * 驗證 API Key 和模型是否可用
 */
function mpu_ajax_test_gemini_connection()
{
    check_ajax_referer('mpu_test_connection', 'nonce');

    $api_key = sanitize_text_field($_POST['api_key'] ?? '');
    $model = sanitize_text_field($_POST['model'] ?? 'gemini-2.5-flash');

    // 如果前端沒有提供 API Key，嘗試從已保存的設定中讀取
    if (empty($api_key)) {
        $mpu_opt = mpu_get_option();
        $api_key_encrypted = $mpu_opt['llm_gemini_api_key'] ?? $mpu_opt['ai_api_key'] ?? '';
        if (!empty($api_key_encrypted)) {
            $api_key = mpu_decrypt_api_key($api_key_encrypted);
        }
    }

    if (empty($api_key)) {
        wp_send_json_error(__('請輸入 Gemini API Key', 'mp-ukagaka'));
        return;
    }

    // 構建測試請求
    $api_url = "https://generativelanguage.googleapis.com/v1/models/{$model}:generateContent?key=" . urlencode($api_key);
    $request_body = [
        "contents" => [
            [
                "parts" => [
                    [
                        "text" => "Hi"
                    ]
                ]
            ]
        ],
        "generationConfig" => [
            "maxOutputTokens" => 50,
        ]
    ];

    $response = wp_remote_post($api_url, [
        "headers" => [
            "Content-Type" => "application/json",
        ],
        "body" => wp_json_encode($request_body),
        "timeout" => 30,
    ]);

    if (is_wp_error($response)) {
        wp_send_json_error(sprintf(__('連接失敗：%s', 'mp-ukagaka'), $response->get_error_message()));
        return;
    }

    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);

    if ($response_code === 200) {
        $data = json_decode($response_body, true);
        if (!empty($data["candidates"][0]["content"]["parts"][0]["text"])) {
            $content = trim($data["candidates"][0]["content"]["parts"][0]["text"]);
            $preview = mb_substr($content, 0, 50);
            wp_send_json_success(sprintf(__('連接成功，模型響應正常（預覽：%s...）', 'mp-ukagaka'), $preview));
        } else {
            wp_send_json_error(__('API 回應為空，請檢查模型是否正確', 'mp-ukagaka'));
        }
    } else {
        $error_data = json_decode($response_body, true);
        $error_message = isset($error_data["error"]["message"])
            ? $error_data["error"]["message"]
            : sprintf(__('HTTP %s 錯誤', 'mp-ukagaka'), $response_code);

        if ($response_code === 401 || $response_code === 403) {
            wp_send_json_error(sprintf(__('API 認證失敗：%s。請檢查 API Key 是否正確。', 'mp-ukagaka'), $error_message));
        } elseif ($response_code === 404) {
            wp_send_json_error(sprintf(__('模型「%s」不存在。請在設定中選擇正確的模型。', 'mp-ukagaka'), $model));
        } else {
            wp_send_json_error(sprintf(__('API 錯誤（HTTP %s）：%s', 'mp-ukagaka'), $response_code, $error_message));
        }
    }
}
add_action('wp_ajax_mpu_test_gemini_connection', 'mpu_ajax_test_gemini_connection');

/**
 * AJAX 處理器：測試 OpenAI 連接
 * 驗證 API Key 和模型是否可用
 */
function mpu_ajax_test_openai_connection()
{
    check_ajax_referer('mpu_test_connection', 'nonce');

    $api_key = sanitize_text_field($_POST['api_key'] ?? '');
    $model = sanitize_text_field($_POST['model'] ?? 'gpt-4o-mini');

    // 如果前端沒有提供 API Key，嘗試從已保存的設定中讀取
    if (empty($api_key)) {
        $mpu_opt = mpu_get_option();
        $api_key_encrypted = $mpu_opt['llm_openai_api_key'] ?? $mpu_opt['openai_api_key'] ?? '';
        if (!empty($api_key_encrypted)) {
            $api_key = mpu_decrypt_api_key($api_key_encrypted);
        }
    }

    if (empty($api_key)) {
        wp_send_json_error(__('請輸入 OpenAI API Key', 'mp-ukagaka'));
        return;
    }

    // 構建測試請求
    $api_url = "https://api.openai.com/v1/chat/completions";
    $request_body = [
        "model" => $model,
        "messages" => [
            [
                "role" => "user",
                "content" => "Hi"
            ]
        ],
        "max_tokens" => 50,
    ];

    $response = wp_remote_post($api_url, [
        "headers" => [
            "Content-Type" => "application/json",
            "Authorization" => "Bearer " . $api_key,
        ],
        "body" => wp_json_encode($request_body),
        "timeout" => 30,
    ]);

    if (is_wp_error($response)) {
        wp_send_json_error(sprintf(__('連接失敗：%s', 'mp-ukagaka'), $response->get_error_message()));
        return;
    }

    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);

    if ($response_code === 200) {
        $data = json_decode($response_body, true);
        if (!empty($data["choices"][0]["message"]["content"])) {
            $content = trim($data["choices"][0]["message"]["content"]);
            $preview = mb_substr($content, 0, 50);
            wp_send_json_success(sprintf(__('連接成功，模型響應正常（預覽：%s...）', 'mp-ukagaka'), $preview));
        } else {
            wp_send_json_error(__('API 回應格式錯誤', 'mp-ukagaka'));
        }
    } else {
        $error_data = json_decode($response_body, true);
        $error_message = isset($error_data["error"]["message"])
            ? $error_data["error"]["message"]
            : sprintf(__('HTTP %s 錯誤', 'mp-ukagaka'), $response_code);

        if ($response_code === 401 || $response_code === 403) {
            wp_send_json_error(sprintf(__('API 認證失敗：%s。請檢查 API Key 是否正確。', 'mp-ukagaka'), $error_message));
        } else {
            wp_send_json_error(sprintf(__('API 錯誤（HTTP %s）：%s', 'mp-ukagaka'), $response_code, $error_message));
        }
    }
}
add_action('wp_ajax_mpu_test_openai_connection', 'mpu_ajax_test_openai_connection');

/**
 * AJAX 處理器：測試 Claude 連接
 * 驗證 API Key 和模型是否可用
 */
function mpu_ajax_test_claude_connection()
{
    check_ajax_referer('mpu_test_connection', 'nonce');

    $api_key = sanitize_text_field($_POST['api_key'] ?? '');
    $model = sanitize_text_field($_POST['model'] ?? 'claude-sonnet-4-5-20250929');

    // 如果前端沒有提供 API Key，嘗試從已保存的設定中讀取
    if (empty($api_key)) {
        $mpu_opt = mpu_get_option();
        $api_key_encrypted = $mpu_opt['llm_claude_api_key'] ?? $mpu_opt['claude_api_key'] ?? '';
        if (!empty($api_key_encrypted)) {
            $api_key = mpu_decrypt_api_key($api_key_encrypted);
        }
    }

    if (empty($api_key)) {
        wp_send_json_error(__('請輸入 Claude API Key', 'mp-ukagaka'));
        return;
    }

    // 構建測試請求
    $api_url = "https://api.anthropic.com/v1/messages";
    $request_body = [
        "model" => $model,
        "max_tokens" => 50,
        "messages" => [
            [
                "role" => "user",
                "content" => "Hi"
            ]
        ],
    ];

    $response = wp_remote_post($api_url, [
        "headers" => [
            "Content-Type" => "application/json",
            "x-api-key" => $api_key,
            "anthropic-version" => "2023-06-01",
        ],
        "body" => wp_json_encode($request_body),
        "timeout" => 30,
    ]);

    if (is_wp_error($response)) {
        wp_send_json_error(sprintf(__('連接失敗：%s', 'mp-ukagaka'), $response->get_error_message()));
        return;
    }

    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);

    if ($response_code === 200) {
        $data = json_decode($response_body, true);
        if (!empty($data["content"][0]["text"])) {
            $content = trim($data["content"][0]["text"]);
            $preview = mb_substr($content, 0, 50);
            wp_send_json_success(sprintf(__('連接成功，模型響應正常（預覽：%s...）', 'mp-ukagaka'), $preview));
        } else {
            wp_send_json_error(__('API 回應格式錯誤', 'mp-ukagaka'));
        }
    } else {
        $error_data = json_decode($response_body, true);
        $error_message = isset($error_data["error"]["message"])
            ? $error_data["error"]["message"]
            : sprintf(__('HTTP %s 錯誤', 'mp-ukagaka'), $response_code);

        if ($response_code === 401 || $response_code === 403) {
            wp_send_json_error(sprintf(__('API 認證失敗：%s。請檢查 API Key 是否正確。', 'mp-ukagaka'), $error_message));
        } else {
            wp_send_json_error(sprintf(__('API 錯誤（HTTP %s）：%s', 'mp-ukagaka'), $response_code, $error_message));
        }
    }
}
add_action('wp_ajax_mpu_test_claude_connection', 'mpu_ajax_test_claude_connection');
