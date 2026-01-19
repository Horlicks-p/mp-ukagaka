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
    // 驗證 Nonce
    if (isset($_POST['mpu_nonce'])) {
        if (!wp_verify_nonce($_POST['mpu_nonce'], 'mpu_ajax_nonce')) {
            wp_send_json(["error" => __("安全性驗證失敗", "mp-ukagaka")]);
            return;
        }
    }

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

    // 檢測統計資訊關鍵字
    foreach ($stats_keywords as $keyword => $type) {
        if (mb_strpos($user_message_lower, mb_strtolower($keyword, 'UTF-8')) !== false) {
            $needs_stats = true;
            break;
        }
    }

    // 檢測系統資訊關鍵字
    foreach ($system_keywords as $keyword => $type) {
        if (mb_strpos($user_message_lower, mb_strtolower($keyword, 'UTF-8')) !== false) {
            $needs_system_info = true;
            break;
        }
    }

    // 檢測外掛資訊關鍵字
    foreach ($plugin_keywords as $keyword => $type) {
        if (mb_strpos($user_message_lower, mb_strtolower($keyword, 'UTF-8')) !== false) {
            $needs_plugin_info = true;
            break;
        }
    }

    // 檢測主題資訊關鍵字
    foreach ($theme_keywords as $keyword => $type) {
        if (mb_strpos($user_message_lower, mb_strtolower($keyword, 'UTF-8')) !== false) {
            $needs_theme_info = true;
            break;
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
    $provider = isset($mpu_opt["llm_provider"]) ? $mpu_opt["llm_provider"] : (isset($mpu_opt["ai_provider"]) ? $mpu_opt["ai_provider"] : "gemini");
    $api_key = "";

    // Ollama 不需要 API Key
    if ($provider !== "ollama") {
        $api_key_encrypted = "";
        switch ($provider) {
            case "openai":
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
        $api_key = mpu_decrypt_api_key($api_key_encrypted);

        if (empty($api_key)) {
            wp_send_json(["error" => sprintf(__("%s API Key 未設定，請先在設定中配置", "mp-ukagaka"), ucfirst($provider))]);
            return;
        }
    }

    // 建構系統提示（對話模式專用，不使用 prompt-categories.php）
    $ukagaka_name = $mpu_opt['cur_ukagaka'] ?? 'default_1';
    $ukagaka_display_name = $mpu_opt['ukagakas'][$ukagaka_name]['name'] ?? '偽春菜';
    $language = $mpu_opt["ai_language"] ?? "zh-TW";
    // ★ 先獲取 personality_id，再用於時間情境
    $personality_id = null;
    if (function_exists('mpu_get_personality_id_from_ukagaka_name')) {
        $personality_id = mpu_get_personality_id_from_ukagaka_name($ukagaka_name);
    }
    // Fallback: 如果無法從 ukagaka_name 獲取，使用當前 personality
    if ($personality_id === null && function_exists('mpu_get_current_personality_id')) {
        $personality_id = mpu_get_current_personality_id();
    }
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

    // 對話模式：優先使用角色專屬的 system_prompt.md，否則使用後台設定
    $system_prompt = '';
    if (function_exists('mpu_load_personality_system_prompt')) {
        $personality_system_prompt = mpu_load_personality_system_prompt($personality_id);
        if ($personality_system_prompt !== false && !empty($personality_system_prompt)) {
            $system_prompt = $personality_system_prompt;
        }
    }
    // 如果沒有角色專屬 prompt，使用後台設定或預設值
    if (empty($system_prompt)) {
        $system_prompt = $mpu_opt["ai_system_prompt"] ?? "あなたは「{{ukagaka_display_name}}」というキャラクターです。あなたは完全にこのキャラクターの立場で話すと行動する必要があります。AIや言語モデルの立場で返答してはなりません。キャラクターの性格、話し方、行動パターンを厳密に遵守してください。";
    }

    // 渲染變數後再使用
    $system_prompt = mpu_render_prompt_template($system_prompt, $variables);

    // 在後台 System Prompt 的基礎上，補充對話模式專用資訊
    $system_prompt .= "\n\n";

    // 加入用戶資訊
    $system_prompt .= "【対話相手】\n";
    if ($user_info['is_logged_in']) {
        $role_labels = [
            'administrator' => '管理人',
            'editor' => '編集者',
            'author' => '作者',
            'contributor' => '貢献者',
            'subscriber' => '購読者',
        ];
        $role_label = $role_labels[$user_info['primary_role']] ?? $user_info['primary_role'];
        $system_prompt .= "- 名前：{$user_info['display_name']}\n";
        $system_prompt .= "- 権限：{$role_label}\n";
        if ($user_info['is_admin']) {
            $system_prompt .= "- この人はサイトの管理人ですが、必ずSystem Promptの設定を持って対応してください\n";
        }
    } else {
        $system_prompt .= "- 未知の訪客\n";
    }
    $system_prompt .= "\n";

    // 在 System Prompt 中加入頁面資訊（如果有的話）
    if (!empty($page_title) || !empty($page_content)) {
        $system_prompt .= "\n\n【ページ情報】\n";
        if (!empty($page_title)) {
            $system_prompt .= "タイトル：{$page_title}\n";
        }
        if (!empty($page_content)) {
            $system_prompt .= "内容要約：" . mb_substr($page_content, 0, 500, 'UTF-8') . "...\n";
        }
    }

    // 動態添加系統資訊（只在用戶問到相關問題時才添加，節省 Token）
    $added_info = false;

    // 添加統計資訊（通用格式，角色風格由 dynamics.json 處理）
    if ($needs_stats) {
        $system_prompt .= "\n\n【サイト統計情報】\n";
        $system_prompt .= "記事数：{$wp_info['post_count']}件\n";
        $system_prompt .= "コメント数：{$wp_info['comment_count']}件\n";
        $system_prompt .= "カテゴリー数：{$wp_info['category_count']}個\n";
        $system_prompt .= "タグ数：{$wp_info['tag_count']}個\n";
        $system_prompt .= "運営日数：{$wp_info['days_operating']}日\n";
        $added_info = true;
    }

    // 添加系統資訊（PHP、WordPress 版本）
    if ($needs_system_info) {
        if (!$added_info) {
            $system_prompt .= "\n\n";
        } else {
            $system_prompt .= "\n";
        }
        $system_prompt .= "【システム情報】\n";
        $system_prompt .= "WordPress バージョン：{$wp_info['wp_version']}\n";
        $system_prompt .= "PHP バージョン：{$wp_info['php_version']}\n";
        if ($wp_info['is_multisite'] ?? false) {
            $system_prompt .= "多サイト：はい\n";
        }
        $added_info = true;
    }

    // 添加外掛資訊
    if ($needs_plugin_info) {
        if (!$added_info) {
            $system_prompt .= "\n\n";
        } else {
            $system_prompt .= "\n";
        }
        $system_prompt .= "【プラグイン情報】\n";
        $system_prompt .= "使用プラグイン数：{$wp_info['active_plugins_count']}個\n";
        if (!empty($wp_info['active_plugins_list']) && count($wp_info['active_plugins_list']) > 0) {
            $plugins_display = (!empty($wp_info['active_plugins_list']) && is_array($wp_info['active_plugins_list'])) ? implode('、', array_slice($wp_info['active_plugins_list'], 0, 10)) : '';
            if (!empty($wp_info['active_plugins_list']) && is_array($wp_info['active_plugins_list']) && count($wp_info['active_plugins_list']) > 10) {
                $plugins_display .= '...等';
            }
            $system_prompt .= "主要プラグイン：{$plugins_display}\n";
        }
        $added_info = true;
    }

    // 添加主題資訊
    if ($needs_theme_info) {
        if (!$added_info) {
            $system_prompt .= "\n\n";
        } else {
            $system_prompt .= "\n";
        }
        $system_prompt .= "【テーマ情報】\n";
        $theme_info = !empty($wp_info['theme_version']) ? "{$wp_info['theme_name']}（{$wp_info['theme_version']}）" : $wp_info['theme_name'];
        $system_prompt .= "テーマ：{$theme_info}\n";
        if (!empty($wp_info['theme_author'])) {
            $system_prompt .= "テーマ作者：{$wp_info['theme_author']}\n";
        }
        if ($wp_info['is_child_theme'] ?? false) {
            $system_prompt .= "子テーマ：はい";
            if (!empty($wp_info['parent_theme'])) {
                $system_prompt .= "（親テーマ：{$wp_info['parent_theme']}）";
            }
            $system_prompt .= "\n";
        }
        if ($wp_info['is_block_theme'] ?? false) {
            $system_prompt .= "ブロックテーマ：はい\n";
        }
        $added_info = true;
    }

    // 会話モード専用指示
    $system_prompt .= "\n【会話モード】\n";
    $system_prompt .= "- あなたはユーザーとリアルタイムで対話しているため、直接ユーザーの質問や話題に答えてください\n";
    $system_prompt .= "- 自言自語や独白をしないで、ユーザーの回答に専念してください\n";
    $system_prompt .= "- 応答は自然で親しみやすい、長さは約 30-150 字程度\n";
    $system_prompt .= "- 必ずsystem_promptの指示に従ってキャラクターの個性を表現し、誇張は避けましょう\n";
    $system_prompt .= "- 必ず {$language} 言語で回答してください\n";
    $system_prompt .= "- 現在時間：{$time_context}";

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
    $max_length = 500;
    if (function_exists('mpu_get_personality_max_response_length')) {
        $max_length = mpu_get_personality_max_response_length(null, $ukagaka_name);
    }
    if (mb_strlen($result, 'UTF-8') > $max_length) {
        $result = mb_substr($result, 0, $max_length, 'UTF-8') . '...';
    }

    // 分析對話內容的情緒，獲取對應的表情
    $emoji = null;
    if (function_exists('mpu_analyze_emoji_from_text') && !empty($result)) {
        // 獲取 personality_id 以載入角色專屬的表情關鍵字
        $personality_id = null;
        if (function_exists('mpu_get_personality_id_from_ukagaka_name')) {
            $personality_id = mpu_get_personality_id_from_ukagaka_name($ukagaka_name);
        }
        $emoji = mpu_analyze_emoji_from_text($result, $personality_id);
    }

    wp_send_json([
        "msg" => $result,
        "emoji" => $emoji  // 表情文件名，如 'happy.png' 或 null
    ]);
}
add_action('wp_ajax_mpu_user_chat', 'mpu_ajax_user_chat');
add_action('wp_ajax_nopriv_mpu_user_chat', 'mpu_ajax_user_chat');
