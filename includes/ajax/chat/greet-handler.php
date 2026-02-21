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

    // 驗證 Nonce（強制）
    if (!mpu_verify_ajax_nonce()) return;

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

    // 獲取提供商和 API Key
    $provider = mpu_get_current_provider($mpu_opt);
    $api_key  = mpu_get_provider_api_key($provider, $mpu_opt);
    if ($provider !== 'ollama' && empty($api_key)) {
        wp_send_json(["error" => ucfirst($provider) . " API Key 未設定"]);
        return;
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
    $personality_id = mpu_resolve_personality_id($ukagaka_name);
    $time_context   = mpu_get_time_context($personality_id);

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

    // 統一系統提示詞解析（吃到 personality 檔案：instructions.md + personality.md 等）
    $system_prompt = mpu_resolve_system_prompt($personality_id, $mpu_opt, $ukagaka_display_name, $variables);

    $user_info = mpu_get_current_user_info();

    $user_prompt = mpu_build_user_info_prompt($user_info);

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
        $country_name = function_exists('mpu_country_code_to_name') ? mpu_country_code_to_name($country) : $country;
        $user_prompt .= "訪問者は「{$country_name}」から来ました";
        if (!empty($city)) {
            $user_prompt .= "の「{$city}」";
        }
        $user_prompt .= "。";
    }

    // ===== 初回訪問挨拶專用：會話指示選擇 =====
    // 優先順序：dynamics.json greet_first_visit → 後台 ai_greet_prompt → hardcoded 預設
    $greet_instruction = '';

    if (function_exists('mpu_load_personality_dynamic_prompts')) {
        $dynamic_prompts = mpu_load_personality_dynamic_prompts($personality_id);
        if (!empty($dynamic_prompts['greet_first_visit']) && is_array($dynamic_prompts['greet_first_visit'])) {
            $greet_instruction = $dynamic_prompts['greet_first_visit'][array_rand($dynamic_prompts['greet_first_visit'])];
        }
    }

    // Fallback：後台設定的打招呼提示詞
    if (empty($greet_instruction) && !empty($mpu_opt['ai_greet_prompt'])) {
        $greet_instruction = mpu_render_prompt_template($mpu_opt['ai_greet_prompt'], $variables);
    }

    // Fallback：hardcoded 預設
    if (empty($greet_instruction)) {
        $greet_instruction = '初回訪問者のアクセス元に軽く触れながら、淡々と短く挨拶する。敬語は使わず常体で話す';
    }

    $user_prompt .= "\n\n【会話指示】\n" . $greet_instruction;
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
        $emoji = mpu_analyze_emoji_from_text($result, $personality_id);
    }

    // ===== 統計：記錄問候語對話 =====
    if (function_exists('mpu_record_conversation')) {
        mpu_record_conversation('greeting');
    }

    wp_send_json([
        "msg" => $result,
        "emoji" => $emoji  // 表情文件名，如 'happy.png' 或 null
    ]);
}
add_action('wp_ajax_mpu_chat_greet', 'mpu_ajax_chat_greet');
add_action('wp_ajax_nopriv_mpu_chat_greet', 'mpu_ajax_chat_greet');
