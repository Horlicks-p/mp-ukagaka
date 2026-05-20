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
/**
 * REST Callback: Check Spam Event
 */
/**
 * [Fix] 寫入 check-spam-event 的 checksum
 * 確保前端 push 和後端驗證保持一致，不讓 chat/user verify 回 400。
 */
function mpu_store_spam_event_checksum( WP_REST_Request $request, $message )
{
    if (empty($message) || connection_aborted()) {
        return;
    }
    $cs_sid_param = $request->get_param('session_id') ?: $request->get_param('chat_session_id');
    $cs_sid = mpu_chat_integrity_normalize_session_id($cs_sid_param);

    if (empty($cs_sid)) return;

    $prior = [];
    $hp    = $request->get_param('history');
    if (!empty($hp)) {
        $decoded = is_string($hp) ? json_decode(wp_unslash($hp), true) : (array)$hp;
        if (is_array($decoded)) {
            foreach ($decoded as $hm) {
                if (is_array($hm) && isset($hm['role'], $hm['content'])) {
                    $r = ($hm['role'] === 'user') ? 'user' : 'assistant';
                    $c = sanitize_textarea_field(wp_unslash($hm['content']));
                    if ($c !== '') {
                        $t = isset($hm['type']) && in_array($hm['type'], ['chat', 'synthetic', 'auto_talk', 'greet', 'context', 'event', 'touch_decoration', 'touch_zone'], true)
                            ? (string) $hm['type'] : 'chat';
                        $prior[] = ['role' => $r, 'content' => $c, 'type' => $t];
                    }
                }
            }
        }
    }
    $prior[] = ['role' => 'assistant', 'content' => sanitize_textarea_field($message), 'type' => 'event'];
    mpu_chat_integrity_store_history($cs_sid, mpu_chat_integrity_slice_for_store($prior, 10));
}

function mpu_rest_check_spam_event( WP_REST_Request $request )
{
    // 速率限制 - 20次/分鐘（跟隨 auto_talk 頻率）
    $rl = mpu_rest_check_rate_limit('check_spam_event', 20, 60);
    if ($rl !== null) return $rl;

    $is_llm_enabled = function_exists('mpu_is_llm_replace_dialogue_enabled')
        && mpu_is_llm_replace_dialogue_enabled();

    if (!$is_llm_enabled) {
        return new WP_REST_Response([
            'has_event' => false
        ], 200);
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
            mpu_store_spam_event_checksum($request, $message);
            return new WP_REST_Response([
                'has_event' => true,
                'msg' => $message,
                'action' => 'turnstile_block',
                'block_count' => $count,
            ], 200);
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
            mpu_store_spam_event_checksum($request, $message);
            return new WP_REST_Response([
                'has_event' => true,
                'msg' => $message,
                'action' => 'spam_alert',
                'spam_count' => $count,
            ], 200);
        }
        // LLM 生成失敗 → 不阻擋，繼續往下檢查 Bot
        if (function_exists('mpu_debug_log')) {
            mpu_debug_log('Akismet Integration: LLM 生成失敗，跳過 spam 反應');
        }
    }

    // Moelog Bot Blocker 檢查
    $mbb_event = get_transient('moelog_bot_blocker_event');

    if ($mbb_event !== false && get_transient('mpu_mbb_reaction_cooldown') === false) {
        delete_transient('moelog_bot_blocker_event');
        // 冷卻時間 30 分鐘，避免一直講
        set_transient('mpu_mbb_reaction_cooldown', true, 30 * MINUTE_IN_SECONDS);

        $count = intval($mbb_event['count']);
        $message = function_exists('mpu_generate_mbb_reaction_llm') 
            ? mpu_generate_mbb_reaction_llm($count) 
            : false;

        if ($message !== false) {
            if (function_exists('mpu_record_conversation')) {
                mpu_record_conversation('auto_talk');
            }
            if (function_exists('mpu_debug_log')) {
                mpu_debug_log('Moelog Bot Blocker: 防禦魔法反應觸發，攔截數量: ' . $count . '（冷卻 30 分鐘）');
            }
            mpu_store_spam_event_checksum($request, $message);
            return new WP_REST_Response([
                'has_event' => true,
                'msg' => $message,
                'action' => 'bot_blocker_alert',
                'block_count' => $count,
            ], 200);
        }
        if (function_exists('mpu_debug_log')) {
            mpu_debug_log('Moelog Bot Blocker: LLM 生成失敗，跳過反應');
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
            mpu_store_spam_event_checksum($request, $message);
            return new WP_REST_Response([
                'has_event' => true,
                'msg' => $message,
                'action' => 'bot_alert',
                'bot_name' => $bot_name,
            ], 200);
        }
    }

    // AI 爬蟲檢查（獨立冷卻 30 分鐘）
    $ai_crawler = function_exists('mpu_check_recent_ai_crawler') ? mpu_check_recent_ai_crawler(60) : false;

    if ($ai_crawler !== false && get_transient('mpu_ai_crawler_cooldown') === false) {
        set_transient('mpu_ai_crawler_cooldown', true, 30 * MINUTE_IN_SECONDS);

        $message = function_exists('mpu_generate_ai_crawler_reaction_llm')
            ? mpu_generate_ai_crawler_reaction_llm($ai_crawler)
            : false;

        if ($message !== false) {
            if (function_exists('mpu_record_conversation')) {
                mpu_record_conversation('auto_talk');
            }
            if (function_exists('mpu_debug_log')) {
                mpu_debug_log('AI Crawler: 偵測到 ' . $ai_crawler['name'] . ' (' . $ai_crawler['company'] . ')，觸發反應（冷卻 30 分鐘）');
            }
            mpu_store_spam_event_checksum($request, $message);
            return new WP_REST_Response([
                'has_event' => true,
                'msg' => $message,
                'action' => 'ai_crawler_alert',
                'crawler' => $ai_crawler['name'],
                'company' => $ai_crawler['company'],
            ], 200);
        }
        if (function_exists('mpu_debug_log')) {
            mpu_debug_log('AI Crawler: LLM 生成失敗，跳過反應');
        }
    }

    // 訪客脈動檢查（獨立冷卻 60 分鐘，避免太囉嗦）
    // 先檢查冷卻才呼叫 detect，避免冷卻期間的純查詢消耗 DB
    if (get_transient('mpu_visitor_pulse_cooldown') === false) {
        $pulse_event = function_exists('mpu_detect_visitor_pulse_event') ? mpu_detect_visitor_pulse_event() : false;

        if ($pulse_event !== false) {
            set_transient('mpu_visitor_pulse_cooldown', true, 60 * MINUTE_IN_SECONDS);

            $message = function_exists('mpu_generate_visitor_pulse_reaction_llm')
                ? mpu_generate_visitor_pulse_reaction_llm($pulse_event)
                : false;

            if ($message !== false) {
                // LLM 成功才把新國家寫入 seen 清單，避免「偵測命中但失敗 → 永遠不再播報」
                if (
                    isset($pulse_event['commit_on_success']['seen_countries'])
                    && is_array($pulse_event['commit_on_success']['seen_countries'])
                    && function_exists('mpu_visitor_pulse_commit_seen_countries')
                ) {
                    mpu_visitor_pulse_commit_seen_countries($pulse_event['commit_on_success']['seen_countries']);
                }

                if (function_exists('mpu_record_conversation')) {
                    mpu_record_conversation('auto_talk');
                }
                if (function_exists('mpu_debug_log')) {
                    mpu_debug_log('Visitor Pulse: 觸發訊號 ' . $pulse_event['type'] . '（冷卻 60 分鐘）');
                }
                mpu_store_spam_event_checksum($request, $message);
                return new WP_REST_Response([
                    'has_event' => true,
                    'msg' => $message,
                    'action' => 'visitor_pulse_alert',
                    'pulse_type' => $pulse_event['type'],
                ], 200);
            }
            if (function_exists('mpu_debug_log')) {
                mpu_debug_log('Visitor Pulse: LLM 生成失敗，跳過反應');
            }
        }
    }

    return new WP_REST_Response([
        'has_event' => false
    ], 200);
}

function mpu_register_akismet_rest_routes() {
    register_rest_route('mp-ukagaka/v1', '/check-spam-event', array(
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'mpu_rest_check_spam_event',
        'permission_callback' => '__return_true',
    ));
}
add_action('rest_api_init', 'mpu_register_akismet_rest_routes');

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
            mpu_debug_log('Akismet Integration: prompts.json 中找不到 akismet_spam_reactions，使用通用 fallback');
        }
        $category_instruction = mpu_get_spam_prompt_fallback($count);
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
    $user_prompt .= "【制約】50字以内・淡々とした口調で";

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
    $index = wp_rand(0, count($spam_prompts) - 1);
    return $spam_prompts[$index];
}

/**
 * Akismet spam 反應的通用 fallback 提示詞。
 *
 * 只有在目前人格沒有 prompts.json / akismet_spam_reactions 時使用。
 * 需保持人格中立，避免寫入特定角色世界觀。
 *
 * @param int $count spam 件數
 * @return string
 */
function mpu_get_spam_prompt_fallback($count = 1)
{
    $fallbacks = [
        "スパムを{$count}件検知し、処理済みであることを短く報告する",
        "迷惑なコメントを{$count}件ブロックしたことを冷静に伝える",
        "不要な投稿を{$count}件取り除いたことを淡々と述べる",
        "Akismet が{$count}件のスパムを検出したことを簡潔に知らせる",
    ];

    return $fallbacks[array_rand($fallbacks)];
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

    $user_prompt = "【状況】\nサイトに Bot（{$bot_name}）がアクセスしている。\n\n";
    $user_prompt .= "【指示】\n{$instruction}\n";
    $user_prompt .= "【制約】50字以内で淡々と。自動アクセスを検知した事実として冷静に述べること";

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
 * 使用 LLM 生成 Moelog Bot Blocker 反應台詞
 * 
 * 使用 dynamics.json 中的 moelog_bot_blocker_report 模板。
 * 
 * @param int $count 攔截數量
 * @return string|false 生成的台詞，失敗時回傳 false
 */
function mpu_generate_mbb_reaction_llm($count)
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

    $mbb_templates = $dynamics['moelog_bot_blocker_report'] ?? [];
    if (!empty($mbb_templates) && is_array($mbb_templates)) {
        $template = $mbb_templates[array_rand($mbb_templates)];
        $vars = ['count' => $count];
        $instruction = mpu_replace_single_prompt_variables($template, $vars);
    } else {
        // fallback - 通用的描述
        $instruction = "Bot Blocker が怪しいアクセスを{$count}件遮断したことを報告する";
    }

    // 從 prompts.json 讀取 moelog_bot_blocker_reactions 提示詞
    $category_instruction = '';
    if (function_exists('mpu_load_personality_prompts')) {
        $all_prompts = mpu_load_personality_prompts($personality_id);
        if (!empty($all_prompts['moelog_bot_blocker_reactions'])) {
            $mbb_prompts = $all_prompts['moelog_bot_blocker_reactions'];
            $category_instruction = "\n【参考セリフ】\n「" . $mbb_prompts[array_rand($mbb_prompts)] . "」";
        }
    }

    $user_prompt = "【状況】\nBot Blocker が起動し、{$count}件の怪しいアクセスを遮断した。\n\n";
    $user_prompt .= "【指示】\n{$instruction}\n";
    if ($category_instruction !== '') {
        $user_prompt .= $category_instruction;
    }
    $user_prompt .= "\n【制約】50字以内で淡々と。怪しいアクセスを遮断した事実として冷静に述べること";

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
            mpu_debug_log('Moelog Bot Blocker: LLM API Error - ' . $result->get_error_message());
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

/**
 * 使用 LLM 生成 AI 爬蟲反應台詞
 *
 * 使用 dynamics.json 中的 ai_crawler_report 模板，
 * 並從 prompts.json 的 ai_crawler_reactions 取參考台詞。
 *
 * @param array $crawler 命中爬蟲定義 ['id','name','company','purpose']
 * @return string|false
 */
function mpu_generate_ai_crawler_reaction_llm($crawler)
{
    if (empty($crawler) || empty($crawler['name'])) {
        return false;
    }

    $mpu_opt  = mpu_get_option();
    $cur_num = $mpu_opt['cur_ukagaka'] ?? 'default_1';
    $personality_id = null;
    if (function_exists('mpu_get_personality_id_from_ukagaka_name')) {
        $personality_id = mpu_get_personality_id_from_ukagaka_name($cur_num);
    }

    // 睡眠時段：AI crawler 在 Frieren 設定中是低風險的「同族」気配，不喚醒也不抽 bot_dreams。
    if (function_exists('mpu_is_deep_sleep_time') && mpu_is_deep_sleep_time($personality_id)) {
        if (function_exists('mpu_debug_log')) {
            mpu_debug_log('AI Crawler: 睡眠時段，低優先度事件をスキップ');
        }
        return false;
    }

    $provider = isset($mpu_opt['llm_provider']) ? $mpu_opt['llm_provider'] : (isset($mpu_opt['ai_provider']) ? $mpu_opt['ai_provider'] : 'gemini');
    $language = $mpu_opt['ai_language'] ?? 'zh-TW';

    $wp_info      = function_exists('mpu_get_wordpress_info') ? mpu_get_wordpress_info() : [];
    $user_info    = function_exists('mpu_get_current_user_info') ? mpu_get_current_user_info() : [];
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

    $dynamics = function_exists('mpu_load_personality_dynamic_prompts')
        ? mpu_load_personality_dynamic_prompts($personality_id)
        : [];

    $vars = [
        'crawler_name' => $crawler['name'],
        'company'      => $crawler['company'] ?? '',
        'purpose'      => $crawler['purpose'] ?? '',
    ];

    $templates = $dynamics['ai_crawler_report'] ?? [];
    if (!empty($templates) && is_array($templates)) {
        $template    = $templates[array_rand($templates)];
        $instruction = mpu_replace_single_prompt_variables($template, $vars);
    } else {
        $instruction = "{$crawler['name']}（{$crawler['company']} のクローラー）が訪れたことを淡々と報告する";
    }

    $category_instruction = '';
    if (function_exists('mpu_load_personality_prompts')) {
        $all_prompts = mpu_load_personality_prompts($personality_id);
        if (!empty($all_prompts['ai_crawler_reactions'])) {
            $reactions = $all_prompts['ai_crawler_reactions'];
            $category_instruction = "\n【参考セリフ】\n「" . $reactions[array_rand($reactions)] . "」";
        }
    }

    $user_prompt  = "【状況】\nクローラー（{$crawler['name']} / {$crawler['company']}、目的：{$crawler['purpose']}）が訪れている。\n\n";
    $user_prompt .= "【指示】\n{$instruction}\n";
    if ($category_instruction !== '') {
        $user_prompt .= $category_instruction;
    }
    $user_prompt .= "\n【制約】50字以内で淡々と。サイトへの自動巡回を検知した事実として述べること";

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

    if (function_exists('mpu_call_ai_api')) {
        $result = mpu_call_ai_api($provider, $api_key, $system_prompt, $user_prompt, $language, $mpu_opt, 200);
    } else {
        return false;
    }

    if (is_wp_error($result)) {
        if (function_exists('mpu_debug_log')) {
            mpu_debug_log('AI Crawler: LLM API Error - ' . $result->get_error_message());
        }
        return false;
    }

    if (!empty($result) && is_string($result) && function_exists('mpu_filter_thinking_content')) {
        $result = mpu_filter_thinking_content($result);
    }

    if (empty($result)) {
        return false;
    }

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
 * 使用 LLM 生成訪客脈動反應台詞
 *
 * 使用 dynamics.json 中的 visitor_pulse_report（按 type 子鍵）模板，
 * 並從 prompts.json 的 visitor_pulse_reactions 取參考台詞。
 *
 * @param array $event ['type' => string, 'data' => array]
 * @return string|false
 */
function mpu_generate_visitor_pulse_reaction_llm($event)
{
    if (empty($event['type'])) {
        return false;
    }

    $mpu_opt  = mpu_get_option();
    $cur_num = $mpu_opt['cur_ukagaka'] ?? 'default_1';
    $personality_id = null;
    if (function_exists('mpu_get_personality_id_from_ukagaka_name')) {
        $personality_id = mpu_get_personality_id_from_ukagaka_name($cur_num);
    }

    // 睡眠時段：抽 visitor_dreams 夢話池，不呼叫 LLM
    // late_night_visitor 必然在 0–4 觸發，與 23–07 的睡眠時段重疊，故必走此路徑
    if (function_exists('mpu_pick_sleep_dream_line')) {
        $dream = mpu_pick_sleep_dream_line($personality_id, 'visitor_dreams');
        if ($dream !== false) {
            if (function_exists('mpu_debug_log')) {
                mpu_debug_log('Visitor Pulse: 睡眠時段，抽 visitor_dreams 夢話替代 LLM（type=' . $event['type'] . '）');
            }
            return $dream;
        }
    }

    $provider = isset($mpu_opt['llm_provider']) ? $mpu_opt['llm_provider'] : (isset($mpu_opt['ai_provider']) ? $mpu_opt['ai_provider'] : 'gemini');
    $language = $mpu_opt['ai_language'] ?? 'zh-TW';

    $wp_info      = function_exists('mpu_get_wordpress_info') ? mpu_get_wordpress_info() : [];
    $user_info    = function_exists('mpu_get_current_user_info') ? mpu_get_current_user_info() : [];
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

    $dynamics = function_exists('mpu_load_personality_dynamic_prompts')
        ? mpu_load_personality_dynamic_prompts($personality_id)
        : [];

    $type = (string) $event['type'];
    $data = is_array($event['data'] ?? null) ? $event['data'] : [];

    // visitor_pulse_report 在 dynamics.json 是按 type 分子鍵
    $type_templates = [];
    $pulse_block    = $dynamics['visitor_pulse_report'] ?? [];
    if (is_array($pulse_block)) {
        if (isset($pulse_block[$type]) && is_array($pulse_block[$type])) {
            $type_templates = $pulse_block[$type];
        } elseif (!empty($pulse_block) && array_keys($pulse_block) === range(0, count($pulse_block) - 1)) {
            $type_templates = $pulse_block;
        }
    }

    $vars      = [];
    $situation = '';
    switch ($type) {
        case 'foreign_visitor':
            $vars = [
                'country_name' => $data['country_name'] ?? '',
                'country_code' => $data['country_code'] ?? '',
            ];
            $situation = "見慣れない国（{$vars['country_name']}）からの訪問者が現れた。";
            break;
        case 'traffic_spike':
            $vars = [
                'current'  => $data['current']  ?? 0,
                'previous' => $data['previous'] ?? 0,
                'ratio'    => $data['ratio']    ?? 1,
            ];
            $situation = "過去 1 時間で訪問者が前の 1 時間の {$vars['ratio']} 倍（{$vars['previous']} → {$vars['current']} 人）に急増している。";
            break;
        case 'late_night_visitor':
            $vars = [
                'count' => $data['count'] ?? 0,
                'hour'  => $data['hour']  ?? 0,
            ];
            $situation = "深夜（{$vars['hour']} 時台）にもかかわらず {$vars['count']} 人の訪問者がいる。";
            break;
        default:
            return false;
    }

    if (!empty($type_templates)) {
        $template    = $type_templates[array_rand($type_templates)];
        $instruction = mpu_replace_single_prompt_variables($template, $vars);
    } else {
        $fallbacks = [
            'foreign_visitor'    => "{$vars['country_name']}からの訪問者がいることを淡々と述べる",
            'traffic_spike'      => "訪問者が急に増えたことを淡々と述べる",
            'late_night_visitor' => "深夜なのに訪問者がいることに、人間は眠らないものだと感想を述べる",
        ];
        $instruction = $fallbacks[$type];
    }

    $category_instruction = '';
    if (function_exists('mpu_load_personality_prompts')) {
        $all_prompts = mpu_load_personality_prompts($personality_id);
        if (!empty($all_prompts['visitor_pulse_reactions'])) {
            $reactions = $all_prompts['visitor_pulse_reactions'];
            $category_instruction = "\n【参考セリフ】\n「" . $reactions[array_rand($reactions)] . "」";
        }
    }

    $user_prompt  = "【状況】\n{$situation}\n\n";
    $user_prompt .= "【指示】\n{$instruction}\n";
    if ($category_instruction !== '') {
        $user_prompt .= $category_instruction;
    }
    $user_prompt .= "\n【制約】50字以内で淡々と。訪問者の動きを観察した事実として述べること";

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

    if (function_exists('mpu_call_ai_api')) {
        $result = mpu_call_ai_api($provider, $api_key, $system_prompt, $user_prompt, $language, $mpu_opt, 200);
    } else {
        return false;
    }

    if (is_wp_error($result)) {
        if (function_exists('mpu_debug_log')) {
            mpu_debug_log('Visitor Pulse: LLM API Error - ' . $result->get_error_message());
        }
        return false;
    }

    if (!empty($result) && is_string($result) && function_exists('mpu_filter_thinking_content')) {
        $result = mpu_filter_thinking_content($result);
    }

    if (empty($result)) {
        return false;
    }

    $max_length = 150;
    if (function_exists('mpu_get_personality_max_response_length')) {
        $max_length = mpu_get_personality_max_response_length($personality_id, $cur_num);
    }
    if (mb_strlen($result, 'UTF-8') > $max_length) {
        $result = mb_substr($result, 0, $max_length, 'UTF-8') . '...';
    }

    return $result;
}
