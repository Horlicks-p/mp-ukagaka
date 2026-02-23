<?php
/**
 * REST Handler: 首次訪客問候
 * 對新訪客生成 AI 打招呼訊息
 * 
 * @package MP_Ukagaka
 * @subpackage REST/Chat
 */

if (!defined('ABSPATH')) {
    exit();
}

/**
 * REST 處理器：首次訪客問候
 */
function mpu_rest_chat_greet( WP_REST_Request $request )
{
    // 速率限制（防止濫用）- 10次/分鐘
    $rl = mpu_rest_check_rate_limit('chat_greet', 10, 60);
    if ($rl !== null) return $rl;

    $mpu_opt = mpu_get_option();

    if (empty($mpu_opt["ai_enabled"])) {
        return new WP_Error('rest_error', __('發生未知錯誤，請檢查日誌', 'mp-ukagaka'), ['status' => 400]);
    }

    if (empty($mpu_opt["ai_greet_first_visit"])) {
        return new WP_Error('rest_error', __('發生未知錯誤，請檢查日誌', 'mp-ukagaka'), ['status' => 400]);
    }

    // 獲取提供商和 API Key
    $provider = mpu_get_current_provider($mpu_opt);
    $api_key  = mpu_get_provider_api_key($provider, $mpu_opt);
    if ($provider !== 'ollama' && empty($api_key)) {
        return new WP_Error('rest_error', __('發生未知錯誤，請檢查日誌', 'mp-ukagaka'), ['status' => 400]);
    }

    // 獲取訪客資訊
    $referrer_param = $request->get_param("referrer");
    $referrer_host_param = $request->get_param("referrer_host");
    $search_engine_param = $request->get_param("search_engine");
    $is_direct_param = $request->get_param("is_direct");
    $country_param = $request->get_param("country");
    $city_param = $request->get_param("city");

    $referrer = !empty($referrer_param) ? esc_url_raw(wp_unslash($referrer_param)) : "";
    $referrer_host = !empty($referrer_host_param) ? sanitize_text_field(wp_unslash($referrer_host_param)) : "";
    $search_engine = !empty($search_engine_param) ? sanitize_text_field(wp_unslash($search_engine_param)) : "";
    $is_direct = ($is_direct_param === true || $is_direct_param === "true");
    $country = !empty($country_param) ? sanitize_text_field(wp_unslash($country_param)) : "";
    $city = !empty($city_param) ? sanitize_text_field(wp_unslash($city_param)) : "";

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

    // 獲取時間情境
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

    $system_prompt = mpu_resolve_system_prompt($personality_id, $mpu_opt, $ukagaka_display_name, $variables);

    $user_info = mpu_get_current_user_info();

    $prompt_parts = [];
    $prompt_parts[] = mpu_build_user_info_prompt($user_info);

    $visitor_lines = [];
    $visitor_lines[] = "【訪問者のアクセス元】";
    $visitor_lines[] = "訪問者は初めての訪問です。";

    if ($is_direct) {
        $visitor_lines[] = "訪問者は直接URLを入力したり、ブックマークから訪問しました（参照元のウェブページはありません）。";
    } else if (!empty($search_engine)) {
        $visitor_lines[] = "訪問者は検索エンジン「{$search_engine}」から訪問しました。";
    } else if (!empty($referrer_host)) {
        $msg = "訪問者はウェブ網站「{$referrer_host}」から訪問しました。";
        if (!empty($referrer)) {
            $msg .= "（{$referrer}）";
        }
        $msg .= "。";
        $visitor_lines[] = $msg;
    } else {
        $visitor_lines[] = "訪問者のアクセス元は不明です。";
    }

    if (!empty($country)) {
        $country_name = function_exists('mpu_country_code_to_name') ? mpu_country_code_to_name($country) : $country;
        $msg = "訪問者は「{$country_name}」から來ました";
        if (!empty($city)) {
            $msg .= "の「{$city}」";
        }
        $msg .= "。";
        $visitor_lines[] = $msg;
    }
    $prompt_parts[] = implode("\n", $visitor_lines);

    $greet_instruction = '';

    if (function_exists('mpu_load_personality_dynamic_prompts')) {
        $dynamic_prompts = mpu_load_personality_dynamic_prompts($personality_id);
        if (!empty($dynamic_prompts['greet_first_visit']) && is_array($dynamic_prompts['greet_first_visit'])) {
            $greet_instruction = $dynamic_prompts['greet_first_visit'][array_rand($dynamic_prompts['greet_first_visit'])];
        }
    }

    if (empty($greet_instruction) && !empty($mpu_opt['ai_greet_prompt'])) {
        $greet_instruction = mpu_render_prompt_template($mpu_opt['ai_greet_prompt'], $variables);
    }

    if (empty($greet_instruction)) {
        $greet_instruction = '初回訪問者のアクセス元に軽く触れながら、淡々と短く挨拶する。敬語は使わず常体で話す';
    }

    $prompt_parts[] = "【会話指示】\n" . $greet_instruction;
    $prompt_parts[] = "【回応ルール】\n淡々とした常体で、30-150文字で挨拶すること。";

    $user_prompt = implode("\n\n", $prompt_parts);

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
        return new WP_Error('rest_error', __('發生未知錯誤，請檢查日誌', 'mp-ukagaka'), ['status' => 400]);
    }

    // 限制回應長度
    $max_length = 500;
    if (function_exists('mpu_get_personality_max_response_length')) {
        $max_length = mpu_get_personality_max_response_length(null, $ukagaka_name);
    }
    if (mb_strlen($result, 'UTF-8') > $max_length) {
        $result = mb_substr($result, 0, $max_length, 'UTF-8') . '...';
    }

    $emoji = null;
    if (function_exists('mpu_analyze_emoji_from_text') && !empty($result)) {
        $emoji = mpu_analyze_emoji_from_text($result, $personality_id);
    }

    if (function_exists('mpu_record_conversation')) {
        mpu_record_conversation('greeting');
    }

    return new WP_REST_Response([
        "msg" => $result,
        "emoji" => $emoji
    ], 200);
}
