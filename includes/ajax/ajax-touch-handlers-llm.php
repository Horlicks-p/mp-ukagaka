<?php

/**
 * LLM 相關 AJAX 處理器：觸摸相關
 * 
 * @package MP_Ukagaka
 * @subpackage AJAX
 */

if (!defined('ABSPATH')) {
    exit();
}

/**
 * AJAX 處理器：裝飾物點擊對話
 * 當用戶點擊裝飾物時，使用 LLM 生成相關對話
 */
function mpu_ajax_decoration_chat()
{
    // 驗證 Nonce（強制）
    if (!isset($_POST['mpu_nonce']) || !wp_verify_nonce($_POST['mpu_nonce'], 'mpu_ajax_nonce')) {
        wp_send_json(['error' => __('安全性驗證失敗', 'mp-ukagaka')]);
        return;
    }

    $mpu_opt = mpu_get_option();

    // 驗證 AI 是否啟用
    if (empty($mpu_opt['ai_enabled'])) {
        wp_send_json(['error' => __('AI 功能未啟用', 'mp-ukagaka')]);
        return;
    }

    // 速率限制（防止濫用）- 20次/分鐘
    mpu_enforce_rate_limit('decoration_chat', 20, 60);

    // 獲取裝飾物類型
    $decoration_type = isset($_POST['decoration_type'])
        ? sanitize_text_field($_POST['decoration_type'])
        : '';

    if (empty($decoration_type)) {
        wp_send_json(['error' => __('未指定裝飾物類型', 'mp-ukagaka')]);
        return;
    }

    // 獲取 personality_id
    $personality_id = function_exists('mpu_get_current_personality_id')
        ? mpu_get_current_personality_id()
        : null;

    // 從 prompt-categories.php 獲取裝飾品提示詞
    $user_prompt = mpu_get_decoration_prompt($decoration_type, $personality_id);

    if ($user_prompt === false) {
        wp_send_json(['error' => __('未知的裝飾物類型', 'mp-ukagaka')]);
        return;
    }

    // 獲取角色名稱
    $ukagaka_name = $mpu_opt['ukagakas'][$mpu_opt['cur_ukagaka']]['name'] ?? 'キャラクター';

    // 在 user_prompt 中添加字數限制（確保回應簡潔）
    $user_prompt .= "\n\n【回応ルール】淡々とした常体で、30-150文字で{$ukagaka_name}として直接反応すること。第三者視点の描写は禁止。";

    $language = $mpu_opt['ai_language'] ?? 'ja';

    // 統一系統提示詞解析（修正：原先使用不存在的函數名 mpu_get_personality_system_prompt）
    $system_prompt = mpu_resolve_system_prompt($personality_id, $mpu_opt, $ukagaka_name);

    // 獲取提供商和 API Key
    $provider = isset($mpu_opt['llm_provider']) ? $mpu_opt['llm_provider'] : (isset($mpu_opt['ai_provider']) ? $mpu_opt['ai_provider'] : 'gemini');
    $api_key_encrypted = "";
    $api_key = "";

    // Ollama 不需要 API Key
    if ($provider !== "ollama") {
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

        // 解密 API Key
        $api_key = mpu_decrypt_api_key($api_key_encrypted);

        if (empty($api_key)) {
            wp_send_json(['error' => ucfirst($provider) . ' API Key 未設定']);
            return;
        }
    }

    // 從 manifest.json 的 settings.max_tokens 讀取（預設 800）
    $max_tokens = 800;
    if (function_exists('mpu_get_personality_max_tokens')) {
        $max_tokens = mpu_get_personality_max_tokens($personality_id);
    }

    // 調用 AI API
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
        wp_send_json(['error' => $result->get_error_message()]);
        return;
    }

    // 更新速率限制計數
    $current_count = ($rate_limit !== false) ? intval($rate_limit) : 0;
    set_transient($transient_key, $current_count + 1, 60); // 60 秒內限制

    // 限制回應長度（從 manifest.json 的 settings.max_response_length 讀取，預設 500）
    $max_length = 500;
    if (function_exists('mpu_get_personality_max_response_length')) {
        $max_length = mpu_get_personality_max_response_length($personality_id);
    }
    if (mb_strlen($result, 'UTF-8') > $max_length) {
        $result = mb_substr($result, 0, $max_length, 'UTF-8') . '...';
    }

    // 分析對話內容的情緒，獲取對應的表情
    $emoji = null;
    if (function_exists('mpu_analyze_emoji_from_text') && !empty($result)) {
        $emoji = mpu_analyze_emoji_from_text($result, $personality_id);
    }

    // ===== 統計：記錄裝飾品反應 =====
    if (function_exists('mpu_record_conversation')) {
        mpu_record_conversation('decoration');
    }

    wp_send_json([
        'msg' => $result,
        'emoji' => $emoji  // 表情文件名，如 'happy.png' 或 null
    ]);
}
add_action('wp_ajax_mpu_decoration_chat', 'mpu_ajax_decoration_chat');
add_action('wp_ajax_nopriv_mpu_decoration_chat', 'mpu_ajax_decoration_chat');

/**
 * AJAX 處理器：角色觸摸區域對話
 * 當用戶點擊角色不同部位時，使用 LLM 生成相關對話
 * 
 * @since 2.4.0
 */
function mpu_ajax_touch_zone_chat()
{
    // 驗證 Nonce（強制）
    if (!isset($_POST['mpu_nonce']) || !wp_verify_nonce($_POST['mpu_nonce'], 'mpu_ajax_nonce')) {
        wp_send_json(['error' => __('安全性驗證失敗', 'mp-ukagaka')]);
        return;
    }

    $mpu_opt = mpu_get_option();

    // 驗證 AI 是否啟用
    if (empty($mpu_opt['ai_enabled'])) {
        wp_send_json(['error' => __('AI 功能未啟用', 'mp-ukagaka')]);
        return;
    }

    // 速率限制（防止濫用）- 20次/分鐘
    mpu_enforce_rate_limit('touch_zone_chat', 20, 60);

    // 獲取觸摸區域
    $touch_zone = isset($_POST['touch_zone'])
        ? sanitize_text_field($_POST['touch_zone'])
        : '';

    if (empty($touch_zone)) {
        wp_send_json(['error' => __('未指定觸摸區域', 'mp-ukagaka')]);
        return;
    }

    // 獲取角色名稱（修正：原先在此之後才定義 $ukagaka_name，導致 undefined variable）
    $ukagaka_name = $mpu_opt['ukagakas'][$mpu_opt['cur_ukagaka']]['name'] ?? 'キャラクター';

    // 獲取 personality_id（修正：使用 cur_ukagaka config key 而非顯示名稱）
    $personality_id = null;
    if (function_exists('mpu_get_personality_id_from_ukagaka_name')) {
        $personality_id = mpu_get_personality_id_from_ukagaka_name($mpu_opt['cur_ukagaka']);
    }
    // Fallback: 如果無法從 ukagaka_name 獲取，使用當前 personality
    if ($personality_id === null && function_exists('mpu_get_current_personality_id')) {
        $personality_id = mpu_get_current_personality_id();
    }

    // 從 touchzones.json 獲取區域配置
    $touchzones = [];
    if (function_exists('mpu_load_personality_touchzones')) {
        $touchzones = mpu_load_personality_touchzones($personality_id);
    }

    $zone_config = $touchzones['zones'][$touch_zone] ?? null;
    if (!$zone_config) {
        wp_send_json(['error' => __('未知的觸摸區域', 'mp-ukagaka')]);
        return;
    }

    // 獲取該區域對應的 prompt 類別
    $prompt_categories = $zone_config['reactions'] ?? ['touch_body'];

    // 從 prompts.json 隨機選取一條 prompt
    $prompts_data = [];
    if (function_exists('mpu_load_personality_prompts')) {
        $prompts_data = mpu_load_personality_prompts($personality_id);
    }

    $available_prompts = [];
    foreach ($prompt_categories as $category) {
        if (!empty($prompts_data[$category]) && is_array($prompts_data[$category])) {
            $available_prompts = array_merge($available_prompts, $prompts_data[$category]);
        }
    }

    if (empty($available_prompts)) {
        // 使用預設 prompt
        $user_prompt = "触られた反応をする";
    } else {
        $user_prompt = $available_prompts[array_rand($available_prompts)];
    }


    // 在 user_prompt 中添加字數限制（確保回應簡潔）
    $user_prompt .= "\n\n【回応ルール】淡々とした常体で、30-150文字で{$ukagaka_name}として直接反応すること。第三者視点の描写は禁止。";

    $language = $mpu_opt['ai_language'] ?? 'ja';

    // 統一系統提示詞解析（修正：原先使用不存在的函數名 + $ukagaka_name 未定義）
    $system_prompt = mpu_resolve_system_prompt($personality_id, $mpu_opt, $ukagaka_name);

    // 獲取提供商和 API Key
    $provider = isset($mpu_opt['llm_provider']) ? $mpu_opt['llm_provider'] : (isset($mpu_opt['ai_provider']) ? $mpu_opt['ai_provider'] : 'gemini');
    $api_key_encrypted = "";
    $api_key = "";

    // Ollama 不需要 API Key
    if ($provider !== "ollama") {
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

        // 解密 API Key
        $api_key = mpu_decrypt_api_key($api_key_encrypted);

        if (empty($api_key)) {
            wp_send_json(['error' => ucfirst($provider) . ' API Key 未設定']);
            return;
        }
    }

    // 從 manifest.json 的 settings.max_tokens 讀取（預設 800）
    $max_tokens = 800;
    if (function_exists('mpu_get_personality_max_tokens')) {
        $max_tokens = mpu_get_personality_max_tokens($personality_id);
    }

    // 調用 AI API
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
        wp_send_json(['error' => $result->get_error_message()]);
        return;
    }

    // 更新速率限制計數
    $current_count = ($rate_limit !== false) ? intval($rate_limit) : 0;
    set_transient($transient_key, $current_count + 1, 60); // 60 秒內限制

    // 限制回應長度（從 manifest.json 的 settings.max_response_length 讀取，預設 500）
    $max_length = 500;
    if (function_exists('mpu_get_personality_max_response_length')) {
        $max_length = mpu_get_personality_max_response_length($personality_id);
    }
    if (mb_strlen($result, 'UTF-8') > $max_length) {
        $result = mb_substr($result, 0, $max_length, 'UTF-8') . '...';
    }

    // 分析對話內容的情緒，獲取對應的表情（$personality_id 已在上方解析）
    $emoji = null;
    if (function_exists('mpu_analyze_emoji_from_text') && !empty($result)) {
        $emoji = mpu_analyze_emoji_from_text($result, $personality_id);
    }

    // ===== 統計：記錄觸摸反應 =====
    if (function_exists('mpu_record_conversation')) {
        mpu_record_conversation('touch');
    }

    wp_send_json([
        'msg' => $result,
        'emoji' => $emoji,
        'zone' => $touch_zone  // 回傳區域資訊供前端調試
    ]);
}
add_action('wp_ajax_mpu_touch_zone_chat', 'mpu_ajax_touch_zone_chat');
add_action('wp_ajax_nopriv_mpu_touch_zone_chat', 'mpu_ajax_touch_zone_chat');

