<?php
/**
 * AJAX Handler: 首次訪客問候
 * 對新訪客生成 AI 打招呼訊息
 * 
 * @package MP_Ukagaka
 * @subpackage AJAX/Chat
 */

if (!defined('ABSPATH')) {
    exit();
}

/**
 * AJAX 處理器：首次訪客問候
 */
function mpu_ajax_chat_greet()
{

    if (isset($_POST['mpu_nonce'])) {
        if (!wp_verify_nonce($_POST['mpu_nonce'], 'mpu_ajax_nonce')) {
            wp_send_json(["error" => "安全性驗證失敗"]);
            return;
        }
    }

    // 速率限制（防止濫用）- 10次/分鐘
    mpu_enforce_rate_limit('chat_greet', 10, 60);

    $mpu_opt = mpu_get_option();

    if (empty($mpu_opt["ai_enabled"])) {
        wp_send_json(["error" => "AI 功能未啟用"]);
        return;
    }

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
    $referrer = isset($_POST["referrer"]) ? esc_url_raw(wp_unslash($_POST["referrer"])) : "";
    $referrer_host = isset($_POST["referrer_host"]) ? sanitize_text_field(wp_unslash($_POST["referrer_host"])) : "";
    $search_engine = isset($_POST["search_engine"]) ? sanitize_text_field(wp_unslash($_POST["search_engine"])) : "";
    $is_direct = isset($_POST["is_direct"]) && $_POST["is_direct"] === "true";
    $country = isset($_POST["country"]) ? sanitize_text_field(wp_unslash($_POST["country"])) : "";
    $city = isset($_POST["city"]) ? sanitize_text_field(wp_unslash($_POST["city"])) : "";

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

    $wp_info = mpu_get_wordpress_info();
    $ukagaka_name = $mpu_opt['cur_ukagaka'] ?? 'default_1';
    $ukagaka_display_name = $mpu_opt['ukagakas'][$ukagaka_name]['name'] ?? '偽春菜';
    $language = $mpu_opt["ai_language"] ?? "zh-TW";

    // 獲取時間情境（傳入 personality_id 以讀取該角色的專屬日曆）
    $personality_id = function_exists('mpu_get_personality_id_from_ukagaka_name')
        ? mpu_get_personality_id_from_ukagaka_name($ukagaka_name)
        : null;
    $time_context = mpu_get_time_context($personality_id);

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

    // 獲取打招呼提示詞（如果未設定，使用針對芙莉蓮的預設值）
    $default_greet_prompt = "あなたは「{{ukagaka_display_name}}」。初回訪問者に対して挨拶する際は、以下のルールに従うこと。\n\n### 基本的な挨拶ルール\n\n1. **会話長度**：必ず**30文字**以内で収まること。\n2. **口調**：敬語（です・ます）は厳禁。「〜だよ」「〜だね」などの常体のみ使用。\n3. **淡々とした態度**：過度に熱心にならず、冷静に挨拶すること。\n\n### 訪問者の情報に基づく対応\n\n#### 直接訪問（referrer なし）\n\n- 直接訪問の場合：「初めまして。こちらに来たんだね。」と淡々と。\n\n#### 検索エンジン経由\n\n- Google 経由：「Google から来たのか。何か探しているのかな。」\n- その他の検索エンジン：同様に淡々と対応。\n\n#### 外部サイト経由\n\n- 特定のサイトから来た場合：「[サイト名]から来たんだね。」と一言。\n- 興味を示す必要はないが、気づいたことは口にする。\n\n#### 地理的情報（Slimstat が有効な場合）\n\n- 国・都市情報がある場合：「[国名/都市名]から来たんだね。」と軽く言及。\n- 地理的な話題は避け、単なる観察として述べる。\n\n### 会話例\n\n- 「初めまして。何か用事があったのかな。」\n- 「Google から来たんだね。何か探しているのかな。」\n- 「[サイト名]から来たのか。興味深いね。」\n- 「[国名]から来たんだね。」\n- 「初めて来たのか。まあ、ゆっくりしていくといいよ。」\n\n### 重要な注意事項\n\n- **からかわない**：初回訪問者なので、まだからかう段階ではない。\n- **魔法や冒険の話題は出さない**：初回は基本的な挨拶のみ。\n- **簡潔に**：長々と説明しない。一言で終わること。";

    $system_prompt = $mpu_opt["ai_greet_prompt"] ?? $default_greet_prompt;
    $system_prompt = mpu_render_prompt_template($system_prompt, $variables);

    $user_info = mpu_get_current_user_info();

    $user_prompt = "【現在のユーザー情報】\n";
    if ($user_info['is_logged_in']) {
        $role_labels = [
            'administrator' => '管理人',
            'editor' => '編集者',
            'author' => '作者',
            'contributor' => '貢献者',
            'subscriber' => '購読者',
        ];
        $role_label = isset($role_labels[$user_info['primary_role']])
            ? $role_labels[$user_info['primary_role']]
            : $user_info['primary_role'];

        $user_prompt .= "ユーザーがログインしています：{$user_info['display_name']} ({$user_info['username']})\n";
        $user_prompt .= "役割：{$role_label}\n";
        if ($user_info['is_admin']) {
            $user_prompt .= "このユーザーはサイト管理人です。\n";
        }
    } else {
        $user_prompt .= "ユーザーがログインしていません（訪問者）。\n";
    }

    $user_prompt .= "\n【訪問者のアクセス元】\n";
    $user_prompt .= "訪問者は初めての訪問です。";

    if ($is_direct) {
        $user_prompt .= "訪問者は直接URLを入力したり、ブックマークから訪問しました（参照元のウェブページはありません）。";
    } else if (!empty($search_engine)) {
        $user_prompt .= "訪問者は検索エンジン「{$search_engine}」から訪問しました。";
    } else if (!empty($referrer_host)) {
        $user_prompt .= "訪問者はウェブサイト「{$referrer_host}」から訪問しました。";
        if (!empty($referrer)) {
            $user_prompt .= "（{$referrer}）";
        }
        $user_prompt .= "。";
    } else {
        $user_prompt .= "訪問者のアクセス元は不明です。";
    }

    if (!empty($country)) {
        $user_prompt .= "訪問者は「{$country}」から来ました";
        if (!empty($city)) {
            $user_prompt .= "の「{$city}」";
        }
        $user_prompt .= "。";
    }

    $user_prompt .= "\n\n【回応ルール】淡々とした常体で、30-150文字で挨拶すること。";

    // 從 manifest.json 的 settings.max_tokens 讀取（預設 800）
    $max_tokens = 800;
    if (function_exists('mpu_get_personality_max_tokens')) {
        $max_tokens = mpu_get_personality_max_tokens(null, $ukagaka_name);
    }
    $result = mpu_call_ai_api(
        $provider,
        $api_key,
        $system_prompt,
        $user_prompt,
        $language,
        $mpu_opt,
        $max_tokens
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
add_action('wp_ajax_mpu_chat_greet', 'mpu_ajax_chat_greet');
add_action('wp_ajax_nopriv_mpu_chat_greet', 'mpu_ajax_chat_greet');
