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
 * AJAX ハンドラ: mpu_nextmsg
 */
function mpu_ajax_nextmsg() {
    $mpu_opt = mpu_get_option();
    $cur_num = isset($_GET["cur_num"])
        ? sanitize_text_field($_GET["cur_num"])
        : $mpu_opt["cur_ukagaka"];
    $cur_msgnum = isset($_GET["cur_msgnum"])
        ? intval($_GET["cur_msgnum"])
        : 0;
    $msg = mpu_get_next_msg($cur_num, $cur_msgnum);
    $msgnum = mpu_get_msg_key($cur_num, $msg);

    wp_send_json(["msg" => $msg, "msgnum" => $msgnum]);
}
add_action('wp_ajax_mpu_nextmsg', 'mpu_ajax_nextmsg');
add_action('wp_ajax_nopriv_mpu_nextmsg', 'mpu_ajax_nextmsg');


/**
 * AJAX ハンドラ: mpu_extend
 */
function mpu_ajax_extend() {
    echo '<a onclick="mpuChange(\'\')" href="javascript:void(0);">' .
        __("更換春菜", "mp-ukagaka") .
        "</a>";
    wp_die();
}
add_action('wp_ajax_mpu_extend', 'mpu_ajax_extend');
add_action('wp_ajax_nopriv_mpu_extend', 'mpu_ajax_extend');

/**
 * AJAX ハンドラ: mpu_change
 */
function mpu_ajax_change() {
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
    $temp["msglist"] = mpu_get_msg_arr($mpu_num);
    $temp["shell"] = $mpu_opt["ukagakas"][$mpu_num]["shell"];
    $temp["msg"] = $temp["msglist"]["msg"][0];
    $temp["name"] = $mpu_opt["ukagakas"][$mpu_num]["name"];
    $temp["num"] = $mpu_num;
    $temp["dialog_filename"] =
        $mpu_opt["ukagakas"][$mpu_num]["dialog_filename"] ?? $mpu_num;

    // 【★ 修正 1/2】
    // 使用 SITECOOKIEPATH (通常是 '/') 來設定 Cookie，
    // 而不是 COOKIEPATH (可能是 /wp-admin/)，
    // 這樣前台頁面 (mpu_html) 才能讀取到。
    $cookie_path = defined('SITECOOKIEPATH') ? SITECOOKIEPATH : '/';
    $cookie_domain = defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : '';

    setcookie(
        "mpu_ukagaka_" . COOKIEHASH,
        $mpu_num,
        time() + DAY_IN_SECONDS,
        $cookie_path,     // ★ 修正點
        $cookie_domain,   // ★ 修正點
        is_ssl(),
        true
    );

    wp_send_json($temp);
}
add_action('wp_ajax_mpu_change', 'mpu_ajax_change');
add_action('wp_ajax_nopriv_mpu_change', 'mpu_ajax_change');


/**
 * AJAX ハンドラ: mpu_get_settings
 */
function mpu_ajax_get_settings() {
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
        // 注意：頁面條件檢查改為在前端進行，因為 AJAX 請求中 WordPress 條件標籤可能無法正確工作
    ];
    wp_send_json($settings);
}
add_action('wp_ajax_mpu_get_settings', 'mpu_ajax_get_settings');
add_action('wp_ajax_nopriv_mpu_get_settings', 'mpu_ajax_get_settings');

/**
 * AJAX ハンドラ: mpu_load_dialog
 * 【安全性強化】使用 mpu_secure_file_read 替代 file_get_contents
 */
function mpu_ajax_load_dialog() {
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

    // 【安全性強化】使用安全文件讀取函數
    $content = mpu_secure_file_read($file_path);
    
    if (is_wp_error($content)) {
        wp_send_json(["error" => $content->get_error_message()]);
    }

    $ext = pathinfo($file, PATHINFO_EXTENSION);

    if ($ext === "json") {
        $json = json_decode($content, true);
        if (
            json_last_error() !== JSON_ERROR_NONE ||
            empty($json["messages"])
        ) {
            wp_send_json([
                "error" => "JSON 檔案格式錯誤：" . json_last_error_msg(),
            ]);
        }
        $msg_array = $json["messages"];
    } else {
        $msg_array = mpu_str2array($content);
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
 * AJAX ハンドラ: mpu_chat_context (AI 上下文對話)
 */
function mpu_ajax_chat_context() {
    // ★★★ 安全性：驗證 Nonce（如果提供）★★★
    // 注意：Nonce 驗證是可選的，主要依賴速率限制來防止濫用
    if (isset($_POST['mpu_nonce'])) {
        if (!wp_verify_nonce($_POST['mpu_nonce'], 'mpu_ajax_nonce')) {
            wp_send_json(["error" => "安全性驗證失敗"]);
            return;
        }
    }
    
    // ★★★ 安全性：速率限制（防止濫用）★★★
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
    
    // 獲取提供商和對應的 API Key
    $provider = $mpu_opt["ai_provider"] ?? "gemini";
    $api_key_encrypted = "";
    
    switch ($provider) {
        case "openai":
            $api_key_encrypted = $mpu_opt["openai_api_key"] ?? "";
            break;
        case "claude":
            $api_key_encrypted = $mpu_opt["claude_api_key"] ?? "";
            break;
        case "gemini":
        default:
            $api_key_encrypted = $mpu_opt["ai_api_key"] ?? "";
            break;
    }
    
    // 【安全性強化】解密 API Key
    $api_key = mpu_decrypt_api_key($api_key_encrypted);
    
    if (empty($api_key)) {
        wp_send_json(["error" => ucfirst($provider) . " API Key 未設定"]);
        return;
    }
    
    // 獲取頁面內容
    $page_title = isset($_POST["page_title"]) ? sanitize_text_field($_POST["page_title"]) : "";
    $page_content = isset($_POST["page_content"]) ? sanitize_textarea_field($_POST["page_content"]) : "";
    
    // ★★★ 安全性：限制內容長度，防止過大請求 ★★★
    if (strlen($page_title) > 500) {
        $page_title = substr($page_title, 0, 500);
    }
    if (strlen($page_content) > 5000) {
        $page_content = substr($page_content, 0, 5000);
    }
    
    if (empty($page_title) && empty($page_content)) {
        wp_send_json(["error" => "頁面內容為空"]);
        return;
    }
    
    // 構建提示詞
    $system_prompt = $mpu_opt["ai_system_prompt"] ?? "你是一個傲嬌的桌面助手「春菜」。你會用簡短、帶點傲嬌的語氣評論文章內容。回應請保持在 40 字以內。";
    $language = $mpu_opt["ai_language"] ?? "zh-TW";
    
    $user_prompt = "文章標題：{$page_title}\n\n文章內容摘要：{$page_content}";
    
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
    
    // ★★★ 安全性：更新速率限制計數器 ★★★
    $current_count = ($rate_limit !== false) ? intval($rate_limit) : 0;
    set_transient($transient_key, $current_count + 1, 60); // 60 秒內最多 10 次
    
    wp_send_json(["msg" => $result]);
}
add_action('wp_ajax_mpu_chat_context', 'mpu_ajax_chat_context');
add_action('wp_ajax_nopriv_mpu_chat_context', 'mpu_ajax_chat_context');

/**
 * AJAX ハンドラ: mpu_get_visitor_info (獲取訪客資訊，使用 Slimstat API)
 */
function mpu_ajax_get_visitor_info() {
    global $wpdb;
    
    // 獲取基本資訊（從 $_SERVER）
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
    
    // ★★★ 使用 Slimstat 獲取更詳細的訪客資訊 ★★★
    if (class_exists('wp_slimstat')) {
        $visitor_info["slimstat_enabled"] = true;
        
        // 直接查詢 Slimstat 資料庫
        global $wpdb;
        $slimstat_table = $wpdb->prefix . 'slim_stats';
        
        // ★★★ 安全性：使用 prepare 防止 SQL 注入 ★★★
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $slimstat_table));
        if ($table_exists == $slimstat_table) {
            // 查詢當前 IP 最近的完整記錄
            $query = $wpdb->prepare(
                "SELECT referer, country, city FROM {$slimstat_table} WHERE ip = %s ORDER BY dt DESC LIMIT 1",
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
 * AJAX ハンドラ: mpu_chat_greet (首次訪客 AI 打招呼)
 */
function mpu_ajax_chat_greet() {
    // ★★★ 安全性：驗證 Nonce（如果提供）★★★
    // 注意：Nonce 驗證是可選的，主要依賴速率限制來防止濫用
    if (isset($_POST['mpu_nonce'])) {
        if (!wp_verify_nonce($_POST['mpu_nonce'], 'mpu_ajax_nonce')) {
            wp_send_json(["error" => "安全性驗證失敗"]);
            return;
        }
    }
    
    // ★★★ 安全性：速率限制（防止濫用）★★★
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
    
    // 獲取提供商和對應的 API Key
    $provider = $mpu_opt["ai_provider"] ?? "gemini";
    $api_key_encrypted = "";
    
    switch ($provider) {
        case "openai":
            $api_key_encrypted = $mpu_opt["openai_api_key"] ?? "";
            break;
        case "claude":
            $api_key_encrypted = $mpu_opt["claude_api_key"] ?? "";
            break;
        case "gemini":
        default:
            $api_key_encrypted = $mpu_opt["ai_api_key"] ?? "";
            break;
    }
    
    // 【安全性強化】解密 API Key
    $api_key = mpu_decrypt_api_key($api_key_encrypted);
    
    if (empty($api_key)) {
        wp_send_json(["error" => ucfirst($provider) . " API Key 未設定"]);
        return;
    }
    
    // 獲取訪客資訊
    $referrer = isset($_POST["referrer"]) ? esc_url_raw($_POST["referrer"]) : "";
    $referrer_host = isset($_POST["referrer_host"]) ? sanitize_text_field($_POST["referrer_host"]) : "";
    $search_engine = isset($_POST["search_engine"]) ? sanitize_text_field($_POST["search_engine"]) : "";
    $is_direct = isset($_POST["is_direct"]) && $_POST["is_direct"] === "true";
    $country = isset($_POST["country"]) ? sanitize_text_field($_POST["country"]) : "";
    $city = isset($_POST["city"]) ? sanitize_text_field($_POST["city"]) : "";
    
    // ★★★ 安全性：驗證輸入長度 ★★★
    if (strlen($referrer) > 500) {
        $referrer = substr($referrer, 0, 500);
    }
    if (strlen($referrer_host) > 255) {
        $referrer_host = substr($referrer_host, 0, 255);
    }
    if (strlen($country) > 10) {
        $country = substr($country, 0, 10);
    }
    if (strlen($city) > 100) {
        $city = substr($city, 0, 100);
    }
    
    // 構建提示詞
    $system_prompt = $mpu_opt["ai_greet_prompt"] ?? "你是一個友善的桌面助手「春菜」。當有訪客第一次來到網站時，你會根據訪客的來源（referrer）用親切的語氣打招呼。回應請保持在 50 字以內。";
    $language = $mpu_opt["ai_language"] ?? "zh-TW";
    
    // 構建用戶提示詞
    $user_prompt = "有訪客第一次來到網站。";
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
    
    $user_prompt .= "請用親切友善的語氣打招呼。";
    
    // ★★★ 調試模式：記錄傳遞給 AI 的資訊 ★★★
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
    
    // ★★★ 安全性：更新速率限制計數器 ★★★
    $current_count = ($rate_limit !== false) ? intval($rate_limit) : 0;
    set_transient($transient_key, $current_count + 1, 300); // 5 分鐘內最多 5 次
    
    wp_send_json(["msg" => $result]);
}
add_action('wp_ajax_mpu_chat_greet', 'mpu_ajax_chat_greet');
add_action('wp_ajax_nopriv_mpu_chat_greet', 'mpu_ajax_chat_greet');

