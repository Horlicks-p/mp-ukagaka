<?php

/**
 * Akismet 垃圾留言連動
 * 
 * 當 Akismet 攔截垃圾留言時，記錄事件到 WordPress transient，
 * 前端在下次自動對話（auto_talk）時觸發芙莉蓮的得意反應。
 * LLM 模式使用 prompts.json 的 akismet_spam_reactions 提示詞，
 * 透過 LLM API 生成自然的反應台詞。
 * 
 * @package MP_Ukagaka
 * @subpackage Integrations
 * @since 2.7.0
 */

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Akismet 垃圾留言攔截 Hook
 * 
 * 當 Akismet 判定留言為垃圾時觸發，記錄事件到 transient。
 * 使用 transient 而非自訂資料表，輕量且自動過期。
 */
add_action('akismet_spam_caught', 'mpu_on_akismet_spam_caught');

function mpu_on_akismet_spam_caught()
{
    // 取得現有的垃圾留言事件（如果有累積的話）
    $event = get_transient('mpu_akismet_spam_event');

    if ($event === false) {
        // 新事件
        $event = [
            'count' => 1,
            'last_time' => current_time('timestamp'),
        ];
    } else {
        // 累積事件
        $event['count'] = intval($event['count']) + 1;
        $event['last_time'] = current_time('timestamp');
    }

    // 儲存事件，10 分鐘後自動過期
    set_transient('mpu_akismet_spam_event', $event, 10 * MINUTE_IN_SECONDS);

    if (function_exists('mpu_debug_log')) {
        mpu_debug_log('Akismet Integration: 垃圾留言已攔截，累計 ' . $event['count'] . ' 則');
    }
}

/**
 * AJAX 處理器：檢查是否有待處理的垃圾留言事件
 * 前端在 auto_talk 計時器觸發時會呼叫此端點。
 * 事件在被消費後會被清除（一次性反應）。
 */
function mpu_ajax_check_spam_event()
{
    // 速率限制 - 20次/分鐘（跟隨 auto_talk 頻率）
    if (function_exists('mpu_enforce_rate_limit')) {
        mpu_enforce_rate_limit('check_spam_event', 20, 60);
    }

    $is_llm_enabled = function_exists('mpu_is_llm_replace_dialogue_enabled')
        && mpu_is_llm_replace_dialogue_enabled();

    if (!$is_llm_enabled) {
        wp_send_json([
            'has_event' => false
        ]);
        return;
    }

    // Turnstile 結界檢查（最優先）
    $turnstile_event = get_transient('mpu_turnstile_block_event');

    if ($turnstile_event !== false && get_transient('mpu_turnstile_reaction_cooldown') === false) {
        delete_transient('mpu_turnstile_block_event');
        set_transient('mpu_turnstile_reaction_cooldown', true, 30 * MINUTE_IN_SECONDS);

        $count = intval($turnstile_event['count']);
        $message = function_exists('mpu_generate_turnstile_reaction_llm')
            ? mpu_generate_turnstile_reaction_llm($count)
            : false;

        if ($message !== false) {
            if (function_exists('mpu_record_conversation')) {
                mpu_record_conversation('auto_talk');
            }
            if (function_exists('mpu_debug_log')) {
                mpu_debug_log('Turnstile Integration: 結界防禦反應觸發，撞擊數量: ' . $count . '（冷卻 30 分鐘）');
            }
            wp_send_json([
                'has_event' => true,
                'msg' => $message,
                'action' => 'turnstile_block',
                'block_count' => $count,
            ]);
            return;
        }
        // LLM 生成失敗 → 不阻擋，繼續往下檢查
        if (function_exists('mpu_debug_log')) {
            mpu_debug_log('Turnstile Integration: LLM 生成失敗，跳過結界反應');
        }
    }

    // Spam 檢查（優先）
    $event = get_transient('mpu_akismet_spam_event');

    if ($event !== false && get_transient('mpu_spam_reaction_cooldown') === false) {
        delete_transient('mpu_akismet_spam_event');
        set_transient('mpu_spam_reaction_cooldown', true, 30 * MINUTE_IN_SECONDS);

        $count = intval($event['count']);
        $message = mpu_generate_spam_reaction_llm($count);

        if ($message !== false) {
            if (function_exists('mpu_record_conversation')) {
                mpu_record_conversation('auto_talk');
            }
            if (function_exists('mpu_debug_log')) {
                mpu_debug_log('Akismet Integration: 垃圾留言反應觸發，攔截數量: ' . $count . '（冷卻 30 分鐘）');
            }
            wp_send_json([
                'has_event' => true,
                'msg' => $message,
                'action' => 'spam_alert',
                'spam_count' => $count,
            ]);
            return;
        }
        // LLM 生成失敗 → 不阻擋，繼續往下檢查 Bot
        if (function_exists('mpu_debug_log')) {
            mpu_debug_log('Akismet Integration: LLM 生成失敗，跳過 spam 反應');
        }
    }

    // Bot 檢查（獨立冷卻）
    $bot_name = function_exists('mpu_check_recent_bot_visit') ? mpu_check_recent_bot_visit(60) : false;

    if ($bot_name !== false && get_transient('mpu_bot_alert_cooldown') === false) {
        set_transient('mpu_bot_alert_cooldown', true, 30 * MINUTE_IN_SECONDS);

        $message = mpu_generate_bot_alert_llm($bot_name);

        if ($message !== false) {
            if (function_exists('mpu_record_conversation')) {
                mpu_record_conversation('auto_talk');
            }
            if (function_exists('mpu_debug_log')) {
                mpu_debug_log('Bot Alert: 偵測到 Bot (' . $bot_name . ')，觸發警報反應');
            }
            wp_send_json([
                'has_event' => true,
                'msg' => $message,
                'action' => 'bot_alert',
                'bot_name' => $bot_name,
            ]);
            return;
        }
    }


    wp_send_json([
        'has_event' => false
    ]);
}
add_action('wp_ajax_mpu_check_spam_event', 'mpu_ajax_check_spam_event');
add_action('wp_ajax_nopriv_mpu_check_spam_event', 'mpu_ajax_check_spam_event');

/**
 * 使用 LLM 生成垃圾留言反應台詞
 * 
 * @param int $count 累積的垃圾留言數量
 * @return string|false 生成的台詞，失敗時回傳 false
 */
function mpu_generate_spam_reaction_llm($count = 1)
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

    // 從 prompts.json 讀取 akismet_spam_reactions 提示詞
    $category_instruction = mpu_get_spam_prompt_from_json($personality_id);
    if ($category_instruction === false) {
        if (function_exists('mpu_debug_log')) {
            mpu_debug_log('Akismet Integration: prompts.json 中找不到 akismet_spam_reactions');
        }
        return false;
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

    // 從 akismet_spam_report 隨機選取一條指令模板
    $spam_templates = $dynamics['akismet_spam_report'] ?? [];
    if (!empty($spam_templates) && is_array($spam_templates)) {
        $template = $spam_templates[array_rand($spam_templates)];
        $vars = ['count' => $count];
        $instruction = mpu_replace_single_prompt_variables($template, $vars);
    } else {
        // fallback（dynamics.json 缺少 akismet_spam_report 時）
        $instruction = "スパムを{$count}件ブロックしたことを短く報告する";
    }

    // 組合：dynamics.json 的指令 + prompts.json 的参考台詞
    $user_prompt = "【指示】\n{$instruction}\n";
    $user_prompt .= "\n【参考セリフ】\n「{$category_instruction}」";

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

    // 取得 max_tokens（精簡回應，不需要太長）
    $max_tokens = 200;

    // 呼叫 LLM API
    if (function_exists('mpu_call_ai_api')) {
        $result = mpu_call_ai_api($provider, $api_key, $system_prompt, $user_prompt, $language, $mpu_opt, $max_tokens);
    } else {
        if (function_exists('mpu_debug_log')) {
            mpu_debug_log('Akismet Integration: mpu_call_ai_api 函式不存在');
        }
        return false;
    }

    if (is_wp_error($result)) {
        if (function_exists('mpu_debug_log')) {
            mpu_debug_log('Akismet Integration: LLM API 錯誤 - ' . $result->get_error_message());
        }
        return false;
    }

    // 過濾思考標籤（DeepSeek-R1 等推理模型）
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

/**
 * 從 prompts.json 讀取 akismet_spam_reactions 提示詞
 * 
 * @param string|null $personality_id 人格 ID
 * @return string|false 隨機選取的提示詞，找不到時回傳 false
 */
function mpu_get_spam_prompt_from_json($personality_id = null)
{
    // 使用既有的 personality prompts 載入機制
    if (!function_exists('mpu_load_personality_prompts')) {
        return false;
    }

    $all_prompts = mpu_load_personality_prompts($personality_id);

    if (empty($all_prompts['akismet_spam_reactions'])) {
        return false;
    }

    $spam_prompts = $all_prompts['akismet_spam_reactions'];

    // 隨機選取一條提示詞
    $index = mt_rand(0, count($spam_prompts) - 1);
    return $spam_prompts[$index];
}

/**
 * 使用 LLM 生成 Bot 警報反應台詞
 * 
 * 使用 dynamics.json 中的 bot_detection 模板。
 * 
 * @param string $bot_name Bot 名稱
 * @return string|false 生成的台詞，失敗時回傳 false
 */
function mpu_generate_bot_alert_llm($bot_name)
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

    // 建構 System Prompt（複用既有架構）
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

    $bot_templates = $dynamics['bot_detection'] ?? [];
    if (!empty($bot_templates) && is_array($bot_templates)) {
        $template = $bot_templates[array_rand($bot_templates)];
        $vars = ['bot_name' => $bot_name];
        $instruction = mpu_replace_single_prompt_variables($template, $vars);
    } else {
        // fallback - 通用的描述
        $instruction = "{$bot_name}というボットがアクセスしていることを報告する";
    }

    $user_prompt = "【状況】\nサイトに Bot（{$bot_name}）がアクセスしています。\n\n";
    $user_prompt .= "【指示】\n{$instruction}\n";
    $user_prompt .= "- 管理者（ユーザー）に即座に報告してください。\n";
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
            mpu_debug_log('Bot Alert: LLM API Error - ' . $result->get_error_message());
        }
        return false;
    }

    // 過濾思考標籤
    if (!empty($result) && is_string($result) && function_exists('mpu_filter_thinking_content')) {
        $result = mpu_filter_thinking_content($result);
    }

    return $result;
}