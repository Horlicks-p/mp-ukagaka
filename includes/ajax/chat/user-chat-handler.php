<?php
/**
 * AJAX Handler: 用戶互動對話
 * 處理用戶輸入的訊息，使用 LLM 生成回應
 * 
 * @package MP_Ukagaka
 * @subpackage AJAX/Chat
 * @since 2.3.0
 */

if (!defined('ABSPATH')) {
    exit();
}

/**
 * AJAX 處理器：用戶互動對話
 * 處理用戶輸入的訊息，使用 LLM 生成回應
 * 
 * @since 2.3.0
 */
function mpu_ajax_user_chat()
{
    // 驗證 Nonce（強制）
    if (!mpu_verify_ajax_nonce()) return;

    // 速率限制（防止濫用）- 30次/分鐘
    mpu_enforce_rate_limit('user_chat', 30, 60);

    $mpu_opt = mpu_get_option();

    // 獲取用戶訊息
    $user_message = isset($_POST['message']) ? sanitize_text_field(wp_unslash($_POST['message'])) : '';
    if (empty($user_message)) {
        wp_send_json(["error" => __("訊息不能為空", "mp-ukagaka")]);
        return;
    }

    // 限制訊息長度
    if (mb_strlen($user_message, 'UTF-8') > 500) {
        $user_message = mb_substr($user_message, 0, 500, 'UTF-8');
    }

    // [Debug] MCP Tool Diagnostics
    if (trim($user_message) === '/debug_mcp') {
        if (!current_user_can('manage_options')) {
            wp_send_json(["msg" => "權限不足：僅管理員可用此指令。"]);
            return;
        }

        $report = "=== MCP Diagnostics ===\n";
        
        // Check integration file
        $report .= "Integration active: " . (function_exists('mpu_get_mcp_tools_for_llm') ? 'Yes' : 'No') . "\n";
        
        // Check Core Abilities API
        $report .= "wp_register_ability function: " . (function_exists('wp_register_ability') ? 'Yes' : 'No') . "\n";

        // Check Ability Registration
        $check_abilities = [
            'mp-ukagaka/get-popular-posts',
            'mp-ukagaka/get-bot-blocker-stats',
            'mp-ukagaka/ban-ip',
            'mp-ukagaka/clear-bot-blocker-data',
        ];
        foreach ($check_abilities as $ability_name) {
            $is_registered = function_exists('wp_has_ability') && wp_has_ability($ability_name);
            $report .= "Ability '{$ability_name}' registered: " . ($is_registered ? 'Yes' : 'No') . "\n";
        }

        // Check Manager class
        $report .= "McpTools Manager class: " . (class_exists('\MP_Ukagaka\McpTools\Manager') ? 'Yes' : 'No') . "\n";

        // Check Ability Class (New Location)
        $report .= "Wp_PostViews_Ability class: " . (class_exists('\MP_Ukagaka\McpTools\Abilities\Wp_PostViews_Ability') ? 'Yes' : 'No') . "\n";

        // Get Tools
        if (function_exists('mpu_get_mcp_tools_for_llm')) {
            $tools = mpu_get_mcp_tools_for_llm('gemini');
            $report .= "Tool count: " . count($tools) . "\n";
            $report .= "Tools found:\n";
            foreach ($tools as $t) {
                $name = $t['name'] ?? $t['function']['name'] ?? 'unknown';
                $report .= "- " . $name . "\n";
            }
        } else {
             $report .= "Cannot fetch tools (function missing).\n";
        }

        // Check WP-PostViews function
        $report .= "WP-PostViews function (get_most_viewed): " . (function_exists('get_most_viewed') ? 'Exists' : 'Missing') . "\n";

        wp_send_json(["msg" => $report]);
        return;
    }

    // 檢測用戶問題是否需要系統資訊（動態添加，節省 Token）
    $needs_stats = false;
    $needs_system_info = false;
    $needs_plugin_info = false;
    $needs_theme_info = false;

    $stats_keywords = [
        // 評論/留言相關
        'コメント' => 'comment',
        '留言' => 'comment',
        '評論' => 'comment',
        'comment' => 'comment',
        'comments' => 'comment',
        // 文章相關
        '文章' => 'post',
        'post' => 'post',
        'posts' => 'post',
        '記事' => 'post',
        'article' => 'post',
        // 分類相關
        '分類' => 'category',
        'category' => 'category',
        'categories' => 'category',
        'カテゴリ' => 'category',
        // 標籤相關
        '標籤' => 'tag',
        'tag' => 'tag',
        'tags' => 'tag',
        'タグ' => 'tag',
        // 運營天數相關
        '運營' => 'days',
        '運営' => 'days',
        '天數' => 'days',
        'days' => 'days',
        '開站' => 'days',
        // 網站相關
        '網站' => 'site',
        'サイト' => 'site',
        'site' => 'site',
        'website' => 'site',
        // 統計相關（通用）
        '統計' => 'stats',
        'stats' => 'stats',
        '數量' => 'stats',
        '個數' => 'stats',
        '有多少' => 'stats',
        '幾個' => 'stats',
    ];

    $system_keywords = [
        // PHP 版本相關
        'php' => 'php',
        'php版本' => 'php',
        'php version' => 'php',
        'phpバージョン' => 'php',
        // WordPress 版本相關
        'wordpress' => 'wp',
        'wp' => 'wp',
        'wp版本' => 'wp',
        'wp version' => 'wp',
        'wordpress版本' => 'wp',
        'wordpress version' => 'wp',
        'ワードプレス' => 'wp',
        'ワードプレスのバージョン' => 'wp',
    ];

    $plugin_keywords = [
        // 外掛相關
        '外掛' => 'plugin',
        '插件' => 'plugin',
        'plugin' => 'plugin',
        'plugins' => 'plugin',
        'プラグイン' => 'plugin',
        '外掛數量' => 'plugin',
        '插件數量' => 'plugin',
        'plugin count' => 'plugin',
        'プラグイン数' => 'plugin',
    ];

    $theme_keywords = [
        // 主題相關
        '主題' => 'theme',
        'theme' => 'theme',
        'テーマ' => 'theme',
        '主題名稱' => 'theme',
        'theme name' => 'theme',
        'テーマ名' => 'theme',
        '主題作者' => 'theme',
        'theme author' => 'theme',
        'テーマ作者' => 'theme',
    ];

    $user_message_lower = mb_strtolower($user_message, 'UTF-8');

    // 合併關鍵字偵測：單次外迴圈處理所有類別，避免重複程式碼
    foreach ([
        'needs_stats'       => $stats_keywords,
        'needs_system_info' => $system_keywords,
        'needs_plugin_info' => $plugin_keywords,
        'needs_theme_info'  => $theme_keywords,
    ] as $flag => $keywords) {
        foreach ($keywords as $keyword => $type) {
            if (mb_strpos($user_message_lower, mb_strtolower($keyword, 'UTF-8')) !== false) {
                $$flag = true;
                break;
            }
        }
    }

    // 獲取對話歷史
    $chat_history = [];
    if (isset($_POST['history'])) {
        $history_json = wp_unslash($_POST['history']);
        $decoded_history = json_decode($history_json, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_history)) {
            // 只保留最近 10 條對話
            $chat_history = array_slice($decoded_history, -10);
        }
    }

    // 驗證對話歷史結構（防止 PHP 錯誤和安全問題）
    $valid_history = [];
    foreach ($chat_history as $msg) {
        // 嚴格檢查是否為陣列且包含必要欄位
        if (is_array($msg) && isset($msg['role'], $msg['content'])) {
            // 確保 role 只有 user 或 assistant（防止注入 system）
            $role = ($msg['role'] === 'user') ? 'user' : 'assistant';
            $content = sanitize_text_field($msg['content']);

            // 內容不可過長（防止 Token 爆炸）
            if (mb_strlen($content, 'UTF-8') > 500) {
                $content = mb_substr($content, 0, 500, 'UTF-8') . '...';
            }

            // 跳過空內容
            if (!empty(trim($content))) {
                $valid_history[] = ['role' => $role, 'content' => $content];
            }
        }
    }
    $chat_history = $valid_history;

    // 獲取頁面內容（可選，用於上下文）
    $page_title = isset($_POST['page_title']) ? sanitize_text_field(wp_unslash($_POST['page_title'])) : '';
    $page_content = isset($_POST['page_content']) ? sanitize_textarea_field(wp_unslash($_POST['page_content'])) : '';

    // 限制長度
    if (mb_strlen($page_title, 'UTF-8') > 200) {
        $page_title = mb_substr($page_title, 0, 200, 'UTF-8');
    }
    if (mb_strlen($page_content, 'UTF-8') > 2000) {
        $page_content = mb_substr($page_content, 0, 2000, 'UTF-8');
    }

    // 獲取 AI 提供商和 API Key
    $provider = mpu_get_current_provider($mpu_opt);
    $api_key  = mpu_get_provider_api_key($provider, $mpu_opt);
    if ($provider !== 'ollama' && empty($api_key)) {
        wp_send_json(["error" => sprintf(__("%s API Key 未設定，請先在設定中配置", "mp-ukagaka"), ucfirst($provider))]);
        return;
    }

    // 建構系統提示（對話模式專用，不使用 prompt-categories.php）
    $ukagaka_name = $mpu_opt['cur_ukagaka'] ?? 'default_1';
    $ukagaka_display_name = $mpu_opt['ukagakas'][$ukagaka_name]['name'] ?? '偽春菜';
    $language = $mpu_opt["ai_language"] ?? "zh-TW";
    // 獲取 personality_id（含 fallback 邏輯）
    $personality_id = mpu_resolve_personality_id($ukagaka_name);
    $time_context = mpu_get_time_context($personality_id);

    // 準備變數陣列（參考 mpu_ajax_chat_context 的實作）
    $wp_info = mpu_get_wordpress_info();
    $variables = [
        'ukagaka_display_name' => $ukagaka_display_name,
        'language' => $language,
        'time_context' => $time_context,
        // WordPress 系統資訊
        'wp_version' => $wp_info['wp_version'] ?? '',
        'php_version' => $wp_info['php_version'] ?? '',
        // 主題資訊
        'theme_name' => $wp_info['theme_name'] ?? '',
        'theme_version' => $wp_info['theme_version'] ?? '',
        'theme_author' => $wp_info['theme_author'] ?? '',
        'is_child_theme' => $wp_info['is_child_theme'] ?? false,
        'parent_theme' => $wp_info['parent_theme'] ?? '',
        'is_block_theme' => $wp_info['is_block_theme'] ?? false,
        // 網站資訊
        'site_name' => $wp_info['site_name'] ?? '',
        'site_description' => $wp_info['site_description'] ?? '',
        'is_multisite' => $wp_info['is_multisite'] ?? false,
        // 外掛資訊
        'active_plugins_count' => $wp_info['active_plugins_count'] ?? 0,
        'active_plugins_list' => (!empty($wp_info['active_plugins_list']) && is_array($wp_info['active_plugins_list'])) ? implode('、', array_slice($wp_info['active_plugins_list'], 0, 10)) : '',
        // 統計資訊
        'post_count' => $wp_info['post_count'] ?? 0,
        'comment_count' => $wp_info['comment_count'] ?? 0,
        'category_count' => $wp_info['category_count'] ?? 0,
        'tag_count' => $wp_info['tag_count'] ?? 0,
        'days_operating' => $wp_info['days_operating'] ?? 0,
        // Slimstat 統計（如果可用）
        'slimstat_total_visits' => $wp_info['slimstat_total_visits'] ?? 0,
    ];

    // 獲取用戶資訊
    $user_info = mpu_get_current_user_info();

    // 統一系統提示詞解析（支援模組化檔案 → 舊版檔案 → 後台設定 → 預設值）
    $base_system_prompt = mpu_resolve_system_prompt($personality_id, $mpu_opt, $ukagaka_display_name, $variables);

    // --- [組合對話模式專用 System Prompt 分段] ---
    $system_parts = [];
    $system_parts[] = $base_system_prompt;

    // 加入用戶資訊
    $user_block = "【対話相手】";
    $user_lines = [];
    if ($user_info['is_logged_in']) {
        $role_labels = [
            'administrator' => '管理人',
            'editor' => '編集者',
            'author' => '作者',
            'contributor' => '貢献者',
            'subscriber' => '購読者',
        ];
        $role_label = $role_labels[$user_info['primary_role']] ?? $user_info['primary_role'];
        $user_lines[] = "- 名前：{$user_info['display_name']}";
        $user_lines[] = "- 権限：{$role_label}";
        if ($user_info['is_admin']) {
            $user_lines[] = "- この人はサイトの管理人ですが、必ずSystem Promptの設定を持って対応してください";
        }
    } else {
        $user_lines[] = "- 未知の訪客";
    }
    $system_parts[] = $user_block . "\n" . implode("\n", $user_lines);

    // 在 System Prompt 中加入頁面資訊（如果有的話）
    if (!empty($page_title) || !empty($page_content)) {
        $page_lines = [];
        $page_lines[] = "【ページ情報】";
        if (!empty($page_title)) {
            $page_lines[] = "タイトル：{$page_title}";
        }
        if (!empty($page_content)) {
            $page_lines[] = "内容要約：" . mb_substr($page_content, 0, 500, 'UTF-8') . "...";
        }
        $system_parts[] = implode("\n", $page_lines);
    }

    // 動態添加系統資訊（只在用戶問到相關問題時才添加，節省 Token）
    // 添加統計資訊（通用格式，角色風格由 dynamics.json 處理）
    if ($needs_stats) {
        $stats_lines = [];
        $stats_lines[] = "【サイト統計情報】";
        $stats_lines[] = "記事数：{$wp_info['post_count']}件";
        $stats_lines[] = "コメント数：{$wp_info['comment_count']}件";
        $stats_lines[] = "カテゴリー数：{$wp_info['category_count']}個";
        $stats_lines[] = "タグ数：{$wp_info['tag_count']}個";
        $stats_lines[] = "運営日数：{$wp_info['days_operating']}日";
        $system_parts[] = implode("\n", $stats_lines);
    }

    // 添加系統資訊（PHP、WordPress 版本）
    if ($needs_system_info) {
        $sys_lines = [];
        $sys_lines[] = "【システム情報】";
        $sys_lines[] = "WordPress バージョン：{$wp_info['wp_version']}";
        $sys_lines[] = "PHP バージョン：{$wp_info['php_version']}";
        if ($wp_info['is_multisite'] ?? false) {
            $sys_lines[] = "多サイト：はい";
        }
        $system_parts[] = implode("\n", $sys_lines);
    }

    // 添加外掛資訊
    if ($needs_plugin_info) {
        $plugin_lines = [];
        $plugin_lines[] = "【プラグイン情報】";
        $plugin_lines[] = "使用プラグイン数：{$wp_info['active_plugins_count']}個";
        if (!empty($wp_info['active_plugins_list']) && count($wp_info['active_plugins_list']) > 0) {
            $plugins_display = (!empty($wp_info['active_plugins_list']) && is_array($wp_info['active_plugins_list'])) ? implode('、', array_slice($wp_info['active_plugins_list'], 0, 10)) : '';
            if (!empty($wp_info['active_plugins_list']) && is_array($wp_info['active_plugins_list']) && count($wp_info['active_plugins_list']) > 10) {
                $plugins_display .= '...等';
            }
            $plugin_lines[] = "主要プラグイン：{$plugins_display}";
        }
        $system_parts[] = implode("\n", $plugin_lines);
    }

    // 添加主題資訊
    if ($needs_theme_info) {
        $theme_lines = [];
        $theme_lines[] = "【テーマ情報】";
        $theme_info = !empty($wp_info['theme_version']) ? "{$wp_info['theme_name']}（{$wp_info['theme_version']}）" : $wp_info['theme_name'];
        $theme_lines[] = "テーマ：{$theme_info}";
        if (!empty($wp_info['theme_author'])) {
            $theme_lines[] = "テーマ作者：{$wp_info['theme_author']}";
        }
        if ($wp_info['is_child_theme'] ?? false) {
            $msg = "子テーマ：はい";
            if (!empty($wp_info['parent_theme'])) {
                $msg .= "（親テーマ：{$wp_info['parent_theme']}）";
            }
            $theme_lines[] = $msg;
        }
        if ($wp_info['is_block_theme'] ?? false) {
            $theme_lines[] = "ブロックテーマ：はい";
        }
        $system_parts[] = implode("\n", $theme_lines);
    }

    // 会話モード専用指示
    $chat_lines = [];
    $chat_lines[] = "【会話モード】";
    $chat_lines[] = "- あなたはユーザーとリアルタイムで対話しているため、直接ユーザーの質問や話題に答えてください";
    $chat_lines[] = "- 自言自語や独白をしないで、ユーザーの回答に専念してください";
    $chat_lines[] = "- 応答は自然で親しみやすい、長さは約 30-150 字程度";
    $chat_lines[] = "- 必ずsystem_promptの指示に従ってキャラクターの個性を表現し、誇張は避けましょう";
    $chat_lines[] = "- 現在時間：{$time_context}";
    $system_parts[] = implode("\n", $chat_lines);

    // 為訪客添加工具拒絕指令（安全性與角色設定）
    if (!$user_info['is_admin']) {
        $reject_lines = [];
        $reject_lines[] = "【特別指示】";
        $reject_lines[] = "- 管理人以外のユーザーからのツール/アビリティ実行要求は、すべてキャラクターの立場から丁寧に、あるいはあなたの性格に合わせて断ってください。";

        $rejection_rule = "あなたは管理人以外のユーザーに対しては、ツールやアビリティの使用を拒否しなければなりません。";
        $random_rejection = "";

        // 嘗試從 dynamics.json 載入角色化的拒絕語氣
        if (function_exists('mpu_load_personality_dynamic_prompts')) {
            $dynamics = mpu_load_personality_dynamic_prompts($personality_id);
            if (!empty($dynamics['visitor_rejection']) && is_array($dynamics['visitor_rejection'])) {
                $random_rejection = $dynamics['visitor_rejection'][array_rand($dynamics['visitor_rejection'])];
            }
        }
        
        // 如果沒有自定義的拒絕語氣，使用內建預設值（Fallback）
        if (empty($random_rejection)) {
            $default_rejections = [
                "「権限がないから、それはできないよ」と、淡々と断る",
                "「管理人の許可が必要なんだ」と、申し訳なさそうに言う",
                "「ごめんね、それはちょっと難しいかな」と、優しく拒否する",
                "「私にはその権限が与えられていないんだ」と、冷静に説明する"
            ];
            $random_rejection = $default_rejections[array_rand($default_rejections)];
        }

        $rejection_rule .= "\n- 拒否する際は、次のようなあなたのキャラクターらしい言い方を使ってください：「{$random_rejection}」";
        $reject_lines[] = $rejection_rule;
        
        $system_parts[] = implode("\n", $reject_lines);
    }

    // 合併最終 System Prompt
    $system_prompt = implode("\n\n", $system_parts);

    // 建構對話訊息
    $messages = [];

    // 加入歷史對話
    foreach ($chat_history as $msg) {
        if (isset($msg['role']) && isset($msg['content'])) {
            $role = $msg['role'] === 'user' ? 'user' : 'assistant';
            $messages[] = [
                'role' => $role,
                'content' => sanitize_text_field($msg['content'])
            ];
        }
    }

    // 加入當前用戶訊息
    $messages[] = [
        'role' => 'user',
        'content' => $user_message
    ];

    // 呼叫 AI API
    // 獲取最大 Token 數（優先順序：Manifest > 全域設定 > 預設 1000）
    $global_max_tokens = isset($mpu_opt['ai_max_tokens']) ? intval($mpu_opt['ai_max_tokens']) : 1000;
    $max_tokens = $global_max_tokens;

    if (function_exists('mpu_load_personality_manifest')) {
        $manifest = mpu_load_personality_manifest($personality_id);
        if (isset($manifest['settings']['max_tokens'])) {
            $max_tokens = intval($manifest['settings']['max_tokens']);
        }
    }

    // 將 max_tokens 加入 options
    $mpu_opt['max_tokens'] = $max_tokens;

    $result = mpu_call_ai_api_with_messages(
        $provider,
        $api_key,
        $system_prompt,
        $messages,
        $language,
        $mpu_opt
    );

    if (is_wp_error($result)) {
        wp_send_json(["error" => $result->get_error_message()]);
        return;
    }

    // Rate limiting 現在由 mpu_enforce_rate_limit 自動處理

    // 限制回應長度（從 manifest.json 的 settings.max_response_length 讀取，預設 500）
    // 如果執行了 MCP 工具，則跳過限制
    global $mpu_mcp_tool_executed;

    if (empty($mpu_mcp_tool_executed)) {
        $max_length = 500;
        if (function_exists('mpu_get_personality_max_response_length')) {
            $max_length = mpu_get_personality_max_response_length(null, $ukagaka_name);
        }
        if (mb_strlen($result, 'UTF-8') > $max_length) {
            $result = mb_substr($result, 0, $max_length, 'UTF-8') . '...';
        }
    }

    // 分析對話內容的情緒，獲取對應的表情
    $emoji = null;
    if (function_exists('mpu_analyze_emoji_from_text') && !empty($result)) {
        $emoji = mpu_analyze_emoji_from_text($result, $personality_id);
    }

    // ===== 統計：記錄互動對話 =====
    if (function_exists('mpu_record_conversation')) {
        mpu_record_conversation('interactive');
    }

    wp_send_json([
        "msg" => $result,
        "emoji" => $emoji  // 表情文件名，如 'happy.png' 或 null
    ]);
}
add_action('wp_ajax_mpu_user_chat', 'mpu_ajax_user_chat');
add_action('wp_ajax_nopriv_mpu_user_chat', 'mpu_ajax_user_chat');
