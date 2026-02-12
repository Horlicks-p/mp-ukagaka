<?php

/**
 * Turnstile 防禦結界連動
 * 
 * 
 * 偵測試圖繞過 Cloudflare Turnstile CAPTCHA 直接提交留言的腳本攻擊。
 * 當 POST 請求中缺少有效的 Turnstile Token 時，記錄事件到 WordPress transient，
 * 前端在下次自動對話（auto_talk）時觸發芙莉蓮的「結界防禦」嘲諷反應。
 * 

 * @package MP_Ukagaka
 * @subpackage Integrations
 * @since 2.7.0
 */

if (!defined('ABSPATH')) {
    exit();
}

/**
 * 檢查 Turnstile Token 是否存在
 * 
 * 在 WordPress 處理留言的核心流程中，搶在其他外掛之前檢查。
 * 如果 Token 為空，代表對方試圖繞過結界（腳本攻擊）。
 * 
 * 注意：此函數不會阻止留言提交（那是 Turnstile 外掛的工作），
 * 只是記錄「結界被撞擊」的事件供偽春菜反應使用。
 * 
 * @param array $commentdata 留言資料
 * @return array 原封不動的留言資料
 */
function mpu_check_turnstile_failure($commentdata)
{
    // 管理員登入中不需要檢查
    if (is_user_logged_in() && current_user_can('moderate_comments')) {
        return $commentdata;
    }

    // 確認 Turnstile 外掛已啟用
    if (!function_exists('cfturnstile_settings_redirect')) {
        // Turnstile 外掛未啟用，跳過
        return $commentdata;
    }

    // 檢查 POST 請求中的 Turnstile Token
    // 欄位名稱 'cf-turnstile-response' 是 Simple Cloudflare Turnstile 外掛的標準名稱
    $token = isset($_POST['cf-turnstile-response']) ? sanitize_text_field(wp_unslash($_POST['cf-turnstile-response'])) : '';

    // Token 為空 = 對方試圖繞過結界
    if (empty($token)) {
        mpu_record_turnstile_event();

        if (function_exists('mpu_debug_log')) {
            mpu_debug_log('Turnstile Integration: 結界防禦觸發 - 缺少 Turnstile Token');
        }
    }

    return $commentdata;
}
add_filter('preprocess_comment', 'mpu_check_turnstile_failure', 1);

/**
 * 記錄 Turnstile 結界撞擊事件
 * 
 * 使用 WordPress transient 儲存事件，10 分鐘後自動過期。
 * 邏輯同 Akismet Integration：寫入 Transient → 前端 Auto Talk 時讀取。
 */
function mpu_record_turnstile_event()
{
    $event = get_transient('mpu_turnstile_block_event');

    if ($event === false) {
        $event = [
            'count' => 1,
            'last_time' => current_time('timestamp'),
        ];
    } else {
        $event['count'] = intval($event['count']) + 1;
        $event['last_time'] = current_time('timestamp');
    }

    set_transient('mpu_turnstile_block_event', $event, 10 * MINUTE_IN_SECONDS);
}

/**
 * 使用 LLM 生成 Turnstile 結界防禦反應台詞
 * 
 * 
 * 使用 dynamics.json 中的 turnstile_block 模板。
 * 
 * @param int $count 累積的撞擊次數
 * @return string|false 生成的台詞，失敗時回傳 false
 */
function mpu_generate_turnstile_reaction_llm($count = 1)
{
    $mpu_opt = mpu_get_option();

    // 取得 LLM 設定
    $provider = isset($mpu_opt['llm_provider']) ? $mpu_opt['llm_provider'] : (isset($mpu_opt['ai_provider']) ? $mpu_opt['ai_provider'] : 'gemini');
    $language = $mpu_opt['ai_language'] ?? 'zh-TW';

    // 取得 personality_id
    $cur_num = $mpu_opt['cur_ukagaka'] ?? 'default_1';
    $personality_id = null;
    if (function_exists('mpu_get_personality_id_from_ukagaka_name')) {
        $personality_id = mpu_get_personality_id_from_ukagaka_name($cur_num);
    }

    // 建構 System Prompt
    $wp_info = function_exists('mpu_get_wordpress_info') ? mpu_get_wordpress_info() : [];
    $user_info = function_exists('mpu_get_current_user_info') ? mpu_get_current_user_info() : [];
    $visitor_info = function_exists('mpu_get_visitor_info_for_llm') ? mpu_get_visitor_info_for_llm() : [];
    $time_context = function_exists('mpu_get_time_context') ? mpu_get_time_context($personality_id) : '';

    $system_prompt = '';
    if (function_exists('mpu_build_optimized_system_prompt')) {
        $system_prompt = mpu_build_optimized_system_prompt(
            $mpu_opt,
            $wp_info,
            $user_info,
            $visitor_info,
            $cur_num,
            $time_context,
            $language,
            $personality_id
        );
    }

    // 建構 User Prompt
    $dynamics = function_exists('mpu_load_personality_dynamic_prompts')
        ? mpu_load_personality_dynamic_prompts($personality_id)
        : [];

    $templates = $dynamics['turnstile_block'] ?? [];
    if (!empty($templates) && is_array($templates)) {
        $template = $templates[array_rand($templates)];
        $vars = ['count' => $count];
        $instruction = mpu_replace_single_prompt_variables($template, $vars);
    } else {
        // fallback
        $instruction = "防御結界（Turnstile）を突破できなかった攻撃者を嘲笑する";
    }

    $user_prompt = "【状況】\nサイトの防御結界（Cloudflare Turnstile）を突破できずに弾かれた攻撃が{$count}件ありました。\n\n";
    $user_prompt .= "【指示】\n{$instruction}\n";
    $user_prompt .= "- 結界を突破できない程度の存在を見下すように。\n";
    $user_prompt .= "- 短い一言（20〜50文字程度）でお願いします。";

    // 取得 API Key
    $api_key = '';
    if ($provider !== 'ollama') {
        switch ($provider) {
            case 'gemini':
                $api_key = !empty($mpu_opt['llm_gemini_api_key']) ? mpu_decrypt_api_key($mpu_opt['llm_gemini_api_key']) : (!empty($mpu_opt['ai_api_key']) ? mpu_decrypt_api_key($mpu_opt['ai_api_key']) : '');
                break;
            case 'openai':
                $api_key = !empty($mpu_opt['llm_openai_api_key']) ? mpu_decrypt_api_key($mpu_opt['llm_openai_api_key']) : (!empty($mpu_opt['openai_api_key']) ? mpu_decrypt_api_key($mpu_opt['openai_api_key']) : '');
                break;
            case 'claude':
                $api_key = !empty($mpu_opt['llm_claude_api_key']) ? mpu_decrypt_api_key($mpu_opt['llm_claude_api_key']) : (!empty($mpu_opt['claude_api_key']) ? mpu_decrypt_api_key($mpu_opt['claude_api_key']) : '');
                break;
        }
    }

    // 呼叫 LLM API
    if (function_exists('mpu_call_ai_api')) {
        $result = mpu_call_ai_api($provider, $api_key, $system_prompt, $user_prompt, $language, $mpu_opt, 200);
    } else {
        return false;
    }

    if (is_wp_error($result)) {
        if (function_exists('mpu_debug_log')) {
            mpu_debug_log('Turnstile Integration: LLM API Error - ' . $result->get_error_message());
        }
        return false;
    }

    // 過濾思考標籤
    if (!empty($result) && is_string($result) && function_exists('mpu_filter_thinking_content')) {
        $result = mpu_filter_thinking_content($result);
    }

    if (empty($result)) {
        return false;
    }

    // 限制回應長度
    $max_length = 150;
    if (function_exists('mpu_get_personality_max_response_length')) {
        $max_length = mpu_get_personality_max_response_length($personality_id, $cur_num);
    }
    if (mb_strlen($result, 'UTF-8') > $max_length) {
        $result = mb_substr($result, 0, $max_length, 'UTF-8') . '...';
    }

    return $result;
}
