<?php
/**
 * REST Handler: 用戶互動對話
 * 處理用戶輸入的訊息，使用 LLM 生成回應
 * 
 * @package MP_Ukagaka
 * @subpackage REST/Chat
 */

if (!defined('ABSPATH')) {
    exit();
}

/**
 * REST 處理器：用戶互動對話
 */
function mpu_rest_user_chat( WP_REST_Request $request )
{
    // 速率限制（防止濫用）- 30次/分鐘
    $rl = mpu_rest_check_rate_limit('user_chat', 30, 60);
    if ($rl !== null) return $rl;

    $mpu_opt = mpu_get_option();

    // 獲取用戶訊息
    $message_param = $request->get_param('message');
    $user_message = !empty($message_param) ? sanitize_text_field(wp_unslash($message_param)) : '';
    if (empty($user_message)) {
        return new WP_Error('rest_error', __('發生未知錯誤，請檢查日誌', 'mp-ukagaka'), ['status' => 400]);
    }

    // 限制訊息長度
    if (mb_strlen($user_message, 'UTF-8') > 500) {
        $user_message = mb_substr($user_message, 0, 500, 'UTF-8');
    }

    // [Debug] MCP Tool Diagnostics
    if (trim($user_message) === '/debug_mcp') {
        if (!current_user_can('manage_options')) {
            return new WP_REST_Response(["msg" => "權限不足：僅管理員可用此指令。"], 200);
        }

        $report = "=== MCP Diagnostics ===\n";
        $report .= "Integration active: " . (function_exists('mpu_get_mcp_tools_for_llm') ? 'Yes' : 'No') . "\n";
        $report .= "wp_register_ability function: " . (function_exists('wp_register_ability') ? 'Yes' : 'No') . "\n";

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

        $report .= "McpTools Manager class: " . (class_exists('\MP_Ukagaka\McpTools\Manager') ? 'Yes' : 'No') . "\n";
        $report .= "Wp_PostViews_Ability class: " . (class_exists('\MP_Ukagaka\McpTools\Abilities\Wp_PostViews_Ability') ? 'Yes' : 'No') . "\n";

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

        $report .= "WP-PostViews function (get_most_viewed): " . (function_exists('get_most_viewed') ? 'Exists' : 'Missing') . "\n";

        return new WP_REST_Response(["msg" => $report], 200);
    }

    $needs_stats = false;
    $needs_system_info = false;
    $needs_plugin_info = false;
    $needs_theme_info = false;

    $stats_keywords = [
        'コメント' => 'comment', '留言' => 'comment', '評論' => 'comment', 'comment' => 'comment', 'comments' => 'comment',
        '文章' => 'post', 'post' => 'post', 'posts' => 'post', '記事' => 'post', 'article' => 'post',
        '分類' => 'category', 'category' => 'category', 'categories' => 'category', 'カテゴリ' => 'category',
        '標籤' => 'tag', 'tag' => 'tag', 'tags' => 'tag', 'タグ' => 'tag',
        '運營' => 'days', '運営' => 'days', '天數' => 'days', 'days' => 'days', '開站' => 'days',
        '網站' => 'site', 'サイト' => 'site', 'site' => 'site', 'website' => 'site',
        '統計' => 'stats', 'stats' => 'stats', '數量' => 'stats', '個數' => 'stats', '有多少' => 'stats', '幾個' => 'stats',
    ];

    $system_keywords = [
        'php' => 'php', 'php版本' => 'php', 'php version' => 'php', 'phpバージョン' => 'php',
        'wordpress' => 'wp', 'wp' => 'wp', 'wp版本' => 'wp', 'wp version' => 'wp', 'wordpress版本' => 'wp', 'wordpress version' => 'wp', 'ワードプレス' => 'wp', 'ワードプレスのバージョン' => 'wp',
    ];

    $plugin_keywords = [
        '外掛' => 'plugin', '插件' => 'plugin', 'plugin' => 'plugin', 'plugins' => 'plugin', 'プラグイン' => 'plugin', '外掛數量' => 'plugin', '插件數量' => 'plugin', 'plugin count' => 'plugin', 'プラグイン数' => 'plugin',
    ];

    $theme_keywords = [
        '主題' => 'theme', 'theme' => 'theme', 'テーマ' => 'theme', '主題名稱' => 'theme', 'theme name' => 'theme', 'テーマ名' => 'theme', '主題作者' => 'theme', 'theme author' => 'theme', 'テーマ作者' => 'theme',
    ];

    $user_message_lower = mb_strtolower($user_message, 'UTF-8');

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

    $chat_history = [];
    $history_param = $request->get_param('history');
    if (!empty($history_param)) {
        if (is_string($history_param)) {
            $decoded_history = json_decode(wp_unslash($history_param), true);
        } else {
            $decoded_history = (array)$history_param;
        }
        
        if (json_last_error() === JSON_ERROR_NONE || is_array($decoded_history)) {
            $chat_history = array_slice($decoded_history, -10);
        }
    }

    $valid_history = [];
    foreach ($chat_history as $msg) {
        if (is_array($msg) && isset($msg['role'], $msg['content'])) {
            $role = ($msg['role'] === 'user') ? 'user' : 'assistant';
            $content = sanitize_text_field($msg['content']);

            if (mb_strlen($content, 'UTF-8') > 500) {
                $content = mb_substr($content, 0, 500, 'UTF-8') . '...';
            }

            if (!empty(trim($content))) {
                $valid_history[] = ['role' => $role, 'content' => $content];
            }
        }
    }
    $chat_history = $valid_history;

    $page_title_param = $request->get_param('page_title');
    $page_content_param = $request->get_param('page_content');
    $page_title = !empty($page_title_param) ? sanitize_text_field(wp_unslash($page_title_param)) : '';
    $page_content = !empty($page_content_param) ? sanitize_textarea_field(wp_unslash($page_content_param)) : '';

    if (mb_strlen($page_title, 'UTF-8') > 200) {
        $page_title = mb_substr($page_title, 0, 200, 'UTF-8');
    }
    if (mb_strlen($page_content, 'UTF-8') > 2000) {
        $page_content = mb_substr($page_content, 0, 2000, 'UTF-8');
    }

    $provider = mpu_get_current_provider($mpu_opt);
    $api_key  = mpu_get_provider_api_key($provider, $mpu_opt);
    if ($provider !== 'ollama' && empty($api_key)) {
        return new WP_Error('rest_error', __('發生未知錯誤，請檢查日誌', 'mp-ukagaka'), ['status' => 400]);
    }

    $ukagaka_name = $mpu_opt['cur_ukagaka'] ?? 'default_1';
    $ukagaka_display_name = $mpu_opt['ukagakas'][$ukagaka_name]['name'] ?? '偽春菜';
    $language = $mpu_opt["ai_language"] ?? "zh-TW";
    
    $personality_id = mpu_resolve_personality_id($ukagaka_name);
    $time_context = mpu_get_time_context($personality_id);

    $wp_info = mpu_get_wordpress_info();
    $variables = [
        'ukagaka_display_name' => $ukagaka_display_name,
        'language' => $language,
        'time_context' => $time_context,
        'wp_version' => $wp_info['wp_version'] ?? '',
        'php_version' => $wp_info['php_version'] ?? '',
        'theme_name' => $wp_info['theme_name'] ?? '',
        'theme_version' => $wp_info['theme_version'] ?? '',
        'theme_author' => $wp_info['theme_author'] ?? '',
        'is_child_theme' => $wp_info['is_child_theme'] ?? false,
        'parent_theme' => $wp_info['parent_theme'] ?? '',
        'is_block_theme' => $wp_info['is_block_theme'] ?? false,
        'site_name' => $wp_info['site_name'] ?? '',
        'site_description' => $wp_info['site_description'] ?? '',
        'is_multisite' => $wp_info['is_multisite'] ?? false,
        'active_plugins_count' => $wp_info['active_plugins_count'] ?? 0,
        'active_plugins_list' => (!empty($wp_info['active_plugins_list']) && is_array($wp_info['active_plugins_list'])) ? implode('、', array_slice($wp_info['active_plugins_list'], 0, 10)) : '',
        'post_count' => $wp_info['post_count'] ?? 0,
        'comment_count' => $wp_info['comment_count'] ?? 0,
        'category_count' => $wp_info['category_count'] ?? 0,
        'tag_count' => $wp_info['tag_count'] ?? 0,
        'days_operating' => $wp_info['days_operating'] ?? 0,
        'slimstat_total_visits' => $wp_info['slimstat_total_visits'] ?? 0,
    ];

    $user_info = mpu_get_current_user_info();
    $base_system_prompt = mpu_resolve_system_prompt($personality_id, $mpu_opt, $ukagaka_display_name, $variables);

    $system_parts = [];
    $system_parts[] = $base_system_prompt;

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

    if ($needs_plugin_info) {
        $plugin_lines = [];
        $plugin_lines[] = "【プラグイン情報】";
        $plugin_lines[] = "使用プラグイン数：{$wp_info['active_plugins_count']}個";
        if (!empty($wp_info['active_plugins_list']) && count($wp_info['active_plugins_list']) > 0) {
            $plugins_display = implode('、', array_slice($wp_info['active_plugins_list'], 0, 10));
            if (count($wp_info['active_plugins_list']) > 10) {
                $plugins_display .= '...等';
            }
            $plugin_lines[] = "主要プラグイン：{$plugins_display}";
        }
        $system_parts[] = implode("\n", $plugin_lines);
    }

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

    $chat_lines = [];
    $chat_lines[] = "【会話モード】";
    $chat_lines[] = "- あなたはユーザーとリアルタイムで対話しているため、直接ユーザーの質問や話題に答えてください";
    $chat_lines[] = "- 自言自語や独白をしないで、ユーザーの回答に専念してください";
    $chat_lines[] = "- 応答は自然で親しみやすい、長さは約 30-150 字程度";
    $chat_lines[] = "- 必ずsystem_promptの指示に従ってキャラクターの個性を表現し、誇張は避けましょう";
    $chat_lines[] = "- 現在時間：{$time_context}";
    $system_parts[] = implode("\n", $chat_lines);

    if (!$user_info['is_admin']) {
        $reject_lines = [];
        $reject_lines[] = "【特別指示】";
        $reject_lines[] = "- 管理人以外のユーザーからのツール/アビリティ実行要求は、すべてキャラクターの立場から丁寧に、あるいはあなたの性格に合わせて断ってください。";

        $rejection_rule = "あなたは管理人以外のユーザーに対しては、ツールやアビリティの使用を拒否しなければなりません。";
        $random_rejection = "";

        if (function_exists('mpu_load_personality_dynamic_prompts')) {
            $dynamics = mpu_load_personality_dynamic_prompts($personality_id);
            if (!empty($dynamics['visitor_rejection']) && is_array($dynamics['visitor_rejection'])) {
                $random_rejection = $dynamics['visitor_rejection'][array_rand($dynamics['visitor_rejection'])];
            }
        }
        
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

    $system_prompt = implode("\n\n", $system_parts);

    $messages = [];

    foreach ($chat_history as $msg) {
        if (isset($msg['role']) && isset($msg['content'])) {
            $role = $msg['role'] === 'user' ? 'user' : 'assistant';
            $messages[] = [
                'role' => $role,
                'content' => sanitize_text_field($msg['content'])
            ];
        }
    }

    $messages[] = [
        'role' => 'user',
        'content' => $user_message
    ];

    $global_max_tokens = isset($mpu_opt['ai_max_tokens']) ? intval($mpu_opt['ai_max_tokens']) : 1000;
    $max_tokens = $global_max_tokens;

    if (function_exists('mpu_load_personality_manifest')) {
        $manifest = mpu_load_personality_manifest($personality_id);
        if (isset($manifest['settings']['max_tokens'])) {
            $max_tokens = intval($manifest['settings']['max_tokens']);
        }
    }

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
        return new WP_Error('rest_error', __('發生未知錯誤，請檢查日誌', 'mp-ukagaka'), ['status' => 400]);
    }

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

    $emoji = null;
    if (function_exists('mpu_analyze_emoji_from_text') && !empty($result)) {
        $emoji = mpu_analyze_emoji_from_text($result, $personality_id);
    }

    if (function_exists('mpu_record_conversation')) {
        mpu_record_conversation('interactive');
    }

    return new WP_REST_Response([
        "msg" => $result,
        "emoji" => $emoji
    ], 200);
}
