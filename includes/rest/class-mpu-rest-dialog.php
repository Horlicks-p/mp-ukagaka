<?php

/**
 * REST Controller: Dialog & Conversation Handlers
 *
 * 取代 rest-core.php 的對話/流程端點。
 * 路由 URL、HTTP method、permission、rate limit key、response 結構
 * 及 WP_Error code 均與舊 procedural 實作一致。
 *
 * 端點：
 *   POST     /mp-ukagaka/v1/nextmsg
 *   GET,POST /mp-ukagaka/v1/dialog
 *   GET      /mp-ukagaka/v1/visitor-info
 *   GET,POST /mp-ukagaka/v1/decoration-prompts
 *   POST     /mp-ukagaka/v1/wake-ghost
 *
 * 特殊 WP_Error codes（前端可能依賴）：
 *   rest_wake_ghost_missing_param  — /wake-ghost 缺少參數
 *   rest_wake_ghost_unavailable    — /wake-ghost 無法喚醒
 *   rest_error                     — 其餘通用錯誤
 *
 * @package MP_Ukagaka
 * @subpackage REST
 */

if (!defined('ABSPATH')) {
    exit();
}

class MPU_REST_Dialog extends MPU_REST_Base {

    /**
     * 向 WordPress 註冊所有 Dialog 端點。
     * 由 bootstrap.php 集中掛載到 rest_api_init。
     */
    public function register_routes() {
        register_rest_route($this->namespace, '/nextmsg', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'nextmsg'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($this->namespace, '/dialog', [
            'methods'             => WP_REST_Server::READABLE . ',' . WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'load_dialog'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($this->namespace, '/visitor-info', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_visitor_info'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($this->namespace, '/decoration-prompts', [
            'methods'             => WP_REST_Server::READABLE . ',' . WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'get_decoration_prompts'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($this->namespace, '/wake-ghost', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'wake_ghost'],
            'permission_callback' => '__return_true',
        ]);
    }

    // =========================================================================
    // POST /nextmsg — Rate limit: 20 次 / 60 秒
    // =========================================================================

    public function nextmsg(WP_REST_Request $request) {
        $rl = $this->rate_limit('nextmsg', 20, 60);
        if ($rl !== null) return $rl;

        $mpu_opt = mpu_get_option();

        $cur_num = $request->get_param('cur_num');
        if (empty($cur_num)) {
            $cur_num = $mpu_opt['cur_ukagaka'] ?? 'default_1';
        }

        $cur_msgnum     = intval($request->get_param('cur_msgnum') ?: 0);
        $is_llm_enabled = mpu_is_llm_replace_dialogue_enabled();

        if ($is_llm_enabled) {
            $last_response = sanitize_text_field($request->get_param('last_response') ?: '');

            $response_history = [];
            $history_param    = $request->get_param('response_history');
            if (!empty($history_param)) {
                if (is_string($history_param)) {
                    $decoded_history = json_decode(wp_unslash($history_param), true);
                } else {
                    $decoded_history = (array) $history_param;
                }

                if (json_last_error() === JSON_ERROR_NONE || is_array($decoded_history)) {
                    $response_history = array_slice($decoded_history, -10);
                    $response_history = array_map('sanitize_text_field', $response_history);
                }
            }

            $last_visit_hours = intval($request->get_param('last_visit_hours') ?? -1);

            $llm_msg      = mpu_generate_llm_dialogue($cur_num, $last_response, $response_history, $last_visit_hours);
            $use_fallback = ($llm_msg === 'MPU_USE_FALLBACK' || $llm_msg === 'MPU_OLLAMA_BUSY');

            if ($llm_msg !== false && $llm_msg !== 'MPU_OLLAMA_NOT_AVAILABLE' && !$use_fallback) {
                $msg    = $llm_msg;
                $msgnum = 0;

                if (function_exists('mpu_record_conversation')) {
                    mpu_record_conversation('auto_talk');
                }
            } elseif ($use_fallback || $llm_msg === false) {
                $msg_array = mpu_get_msg_arr($cur_num);
                $msgs      = $msg_array['msg'] ?? [];
                $total     = count($msgs);

                if ($total > 0) {
                    $msgnum = wp_rand(0, $total - 1);
                    $msg    = $msgs[$msgnum];
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        $reason = $llm_msg === 'MPU_USE_FALLBACK' ? '重複検知' : ($llm_msg === 'MPU_OLLAMA_BUSY' ? 'Ollama 混雑' : '生成失敗');
                        if (function_exists('mpu_debug_log')) {
                            mpu_debug_log('MP Ukagaka - ' . $reason . '、内蔵ダイアログを使用します');
                        } else {
                            error_log('MP Ukagaka - ' . $reason . '、内蔵ダイアログを使用します');
                        }
                    }
                } else {
                    $msg    = __('ダイアログ内容がありません', 'mp-ukagaka');
                    $msgnum = 0;
                }
            } else {
                $msg    = __('ローカルの Ollama が起動していません。Ollama サービスが起動しているか確認してください。', 'mp-ukagaka');
                $msgnum = 0;
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    if (function_exists('mpu_debug_log')) {
                        mpu_debug_log('MP Ukagaka - LLM 生成失敗、エラーメッセージを返します。llm_msg = ' . ($llm_msg === false ? 'false' : $llm_msg));
                    } else {
                        error_log('MP Ukagaka - LLM 生成失敗、エラーメッセージを返します。llm_msg = ' . ($llm_msg === false ? 'false' : $llm_msg));
                    }
                }
            }
        } else {
            $msg_array = mpu_get_msg_arr($cur_num);
            $msgs      = $msg_array['msg'] ?? [];
            $total     = count($msgs);

            if (($mpu_opt['next_msg'] ?? 0) == 0) {
                $next = $cur_msgnum + 1;
                if (isset($msgs[$next])) {
                    $msg    = $msgs[$next];
                    $msgnum = $next;
                } else {
                    $msg    = $msgs[0] ?? __('ダイアログ内容がありません', 'mp-ukagaka');
                    $msgnum = 0;
                }
            } else {
                if ($total > 0) {
                    $msgnum = wp_rand(0, $total - 1);
                    $msg    = $msgs[$msgnum];
                } else {
                    $msg    = __('ダイアログ内容がありません', 'mp-ukagaka');
                    $msgnum = 0;
                }
            }
        }

        $max_length = 500;
        if (function_exists('mpu_get_personality_max_response_length')) {
            $max_length = mpu_get_personality_max_response_length(null, $cur_num);
        }
        if (mb_strlen($msg, 'UTF-8') > $max_length) {
            $msg = mb_substr($msg, 0, $max_length, 'UTF-8') . '...';
        }

        $emoji         = null;
        $personality_id = null;
        if (function_exists('mpu_analyze_emoji_from_text') && !empty($msg)) {
            if (function_exists('mpu_get_personality_id_from_ukagaka_name')) {
                $personality_id = mpu_get_personality_id_from_ukagaka_name($cur_num);
            }
            $emoji = mpu_analyze_emoji_from_text($msg, $personality_id);
        }

        // [Fix] LLM 自發對話也會 push 到前端 mpuChatHistory，但後端未寫 checksum，
        // 導致下一輪 chat/user verify 400。僅在 LLM 成功回應時寫入。
        if ($is_llm_enabled && !$use_fallback && isset($msg) && $msg !== '' &&
            $msg !== __('ローカルの Ollama が起動していません。Ollama サービスが起動しているか確認してください。', 'mp-ukagaka')) {
            $chat_session_id_param = $request->get_param('session_id') ?: $request->get_param('chat_session_id');
            $chat_session_id = function_exists('mpu_chat_integrity_normalize_session_id')
                ? mpu_chat_integrity_normalize_session_id($chat_session_id_param)
                : '';

            if (!empty($chat_session_id) && function_exists('mpu_chat_integrity_store_history') && !connection_aborted()) {
                $prior_history = [];
                $history_param = $request->get_param('history');
                if (!empty($history_param)) {
                    $decoded = is_string($history_param) ? json_decode(wp_unslash($history_param), true) : (array) $history_param;
                    if (is_array($decoded)) {
                        foreach ($decoded as $entry) {
                            if (is_array($entry) && isset($entry['role'], $entry['content'])) {
                                $role    = ($entry['role'] === 'user') ? 'user' : 'assistant';
                                $content = sanitize_textarea_field(wp_unslash($entry['content']));
                                if ($content !== '') {
                                    $type = isset($entry['type']) && in_array($entry['type'], ['chat', 'synthetic', 'auto_talk', 'greet', 'context', 'event', 'touch_decoration', 'touch_zone'], true)
                                        ? (string) $entry['type'] : 'chat';
                                    $prior_history[] = ['role' => $role, 'content' => $content, 'type' => $type];
                                }
                            }
                        }
                    }
                }
                $prior_history[] = ['role' => 'assistant', 'content' => sanitize_textarea_field($msg), 'type' => 'auto_talk'];
                mpu_chat_integrity_store_history(
                    $chat_session_id,
                    mpu_chat_integrity_slice_for_store($prior_history, 10)
                );
            }
        }

        return $this->ok([
            'msg'    => $msg,
            'msgnum' => $msgnum,
            'emoji'  => $emoji,
        ]);
    }


    // =========================================================================
    // GET,POST /dialog — Rate limit: 30 次 / 60 秒
    // =========================================================================

    public function load_dialog(WP_REST_Request $request) {
        $rl = $this->rate_limit('load_dialog', 30, 60);
        if ($rl !== null) return $rl;

        $mpu_opt    = mpu_get_option();
        $file_param = $request->get_param('file');
        $file       = !empty($file_param) ? basename(sanitize_text_field($file_param)) : '';

        if ($file === '' || !preg_match('/^[a-zA-Z0-9_\-]+\.(json|txt)$/', $file)) {
            return $this->fail('rest_error', __('不明なエラーが発生しました。ログを確認してください', 'mp-ukagaka'), 400);
        }

        $file_path = mpu_get_dialogs_dir() . '/' . $file;
        $content   = mpu_secure_file_read($file_path);

        if (is_wp_error($content)) {
            return $this->fail('rest_error', __('不明なエラーが発生しました。ログを確認してください', 'mp-ukagaka'), 400);
        }

        $ext = pathinfo($file, PATHINFO_EXTENSION);
        if ($ext === 'json') {
            $json = json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->fail('rest_error', __('不明なエラーが発生しました。ログを確認してください', 'mp-ukagaka'), 400);
            }
            if (empty($json['messages']) || !is_array($json['messages']) || count($json['messages']) === 0) {
                return $this->fail('rest_error', __('不明なエラーが発生しました。ログを確認してください', 'mp-ukagaka'), 400);
            }
            $msg_array = $json['messages'];
        } else {
            $msg_array = mpu_str2array($content);
            if (empty($msg_array) || !is_array($msg_array) || count($msg_array) === 0) {
                return $this->fail('rest_error', __('不明なエラーが発生しました。ログを確認してください', 'mp-ukagaka'), 400);
            }
        }

        $arr = [
            'msgall'      => max(0, count($msg_array) - 1),
            'auto_msg'    => $mpu_opt['auto_msg'] ?? '',
            'msg'         => mpu_msg_code($msg_array),
            'next_msg'    => intval($mpu_opt['next_msg'] ?? 0),
            'default_msg' => intval($mpu_opt['default_msg'] ?? 0),
        ];

        $auto_msg_array  = mpu_msg_code([$arr['auto_msg']]);
        $arr['auto_msg'] = implode(' ', $auto_msg_array);

        return $this->ok($arr);
    }

    // =========================================================================
    // GET /visitor-info — Rate limit: 30 次 / 60 秒
    // =========================================================================

    public function get_visitor_info(WP_REST_Request $request) {
        $rl = $this->rate_limit('get_visitor_info', 30, 60);
        if ($rl !== null) return $rl;

        $referrer = isset($_SERVER['HTTP_REFERER']) ? esc_url_raw($_SERVER['HTTP_REFERER']) : '';
        $ip       = mpu_get_client_ip(); // 僅供 Slimstat 查詢，不回傳至前端

        $visitor_info = [
            'referrer'         => $referrer,
            'is_direct'        => empty($referrer),
            'slimstat_enabled' => false,
        ];

        if (class_exists('wp_slimstat')) {
            $visitor_info['slimstat_enabled'] = true;
            global $wpdb;
            $slimstat_table = $wpdb->prefix . 'slim_stats';
            $table_exists   = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $slimstat_table));

            if ($table_exists == $slimstat_table) {
                $query  = $wpdb->prepare("SELECT referer, country, city FROM {$slimstat_table} WHERE ip = %s ORDER BY dt DESC LIMIT 1", $ip);
                $result = $wpdb->get_row($query, OBJECT);

                if (!empty($result)) {
                    if (!empty($result->referer)) {
                        $visitor_info['referrer'] = esc_url_raw($result->referer);
                    }
                    if (!empty($result->country)) $visitor_info['slimstat_country'] = sanitize_text_field($result->country);
                    if (!empty($result->city))    $visitor_info['slimstat_city']    = sanitize_text_field($result->city);
                }
            }
        }

        if (!empty($visitor_info['referrer'])) {
            $parsed_url                      = parse_url($visitor_info['referrer']);
            $visitor_info['referrer_host']   = isset($parsed_url['host']) ? $parsed_url['host'] : '';
            $visitor_info['referrer_path']   = isset($parsed_url['path']) ? $parsed_url['path'] : '';

            $search_engines      = ['google', 'bing', 'yahoo', 'baidu', 'yandex', 'duckduckgo', 'naver'];
            $referrer_host_lower = strtolower($visitor_info['referrer_host']);
            foreach ($search_engines as $engine) {
                if (strpos($referrer_host_lower, $engine) !== false) {
                    $visitor_info['search_engine'] = $engine;
                    break;
                }
            }
        }

        return $this->ok($visitor_info);
    }

    // =========================================================================
    // GET,POST /decoration-prompts — Rate limit: 20 次 / 60 秒
    // WP_Error code: rest_error（安全性驗證失敗時使用）
    // =========================================================================

    public function get_decoration_prompts(WP_REST_Request $request) {
        $rl = $this->rate_limit('get_decoration_prompts', 20, 60);
        if ($rl !== null) return $rl;

        $personality_id  = function_exists('mpu_get_current_personality_id') ? mpu_get_current_personality_id() : null;
        $decoration_types = mpu_get_available_decoration_types($personality_id);

        $decoration_type = sanitize_text_field($request->get_param('decoration_type') ?: '');
        $prompts         = [];

        if ($decoration_type) {
            $prompt = mpu_get_decoration_prompt($decoration_type, $personality_id);
            if ($prompt !== false) {
                $prompts[$decoration_type] = $prompt;
            } else {
                return $this->fail('rest_error', __('セキュリティ検証に失敗しました', 'mp-ukagaka'), 400);
            }
        } else {
            foreach ($decoration_types as $type) {
                $prompt = mpu_get_decoration_prompt($type, $personality_id);
                if ($prompt !== false) {
                    $prompts[$type] = $prompt;
                }
            }
        }

        return $this->ok(['prompts' => $prompts]);
    }

    // =========================================================================
    // POST /wake-ghost — Rate limit: 10 次 / 60 秒
    // 特殊 WP_Error codes: rest_wake_ghost_missing_param, rest_wake_ghost_unavailable
    // =========================================================================

    public function wake_ghost(WP_REST_Request $request) {
        $rl = $this->rate_limit('wake_ghost', 10, 60);
        if ($rl !== null) return $rl;

        $personality_id = sanitize_text_field($request->get_param('personality_id') ?: '');

        if (empty($personality_id)) {
            $ukagaka_num = sanitize_text_field($request->get_param('ukagaka_num') ?: '');
            if (!empty($ukagaka_num) && function_exists('mpu_get_personality_id_from_ukagaka_name')) {
                $personality_id = mpu_get_personality_id_from_ukagaka_name($ukagaka_num);
            }
        }

        if (empty($personality_id)) {
            return new WP_Error(
                'rest_wake_ghost_missing_param',
                __('キャラクター ID パラメーター（personality_id / ukagaka_num）が不足しています', 'mp-ukagaka'),
                ['status' => 400]
            );
        }

        if (function_exists('mpu_mark_ip_as_woken')) {
            $result = mpu_mark_ip_as_woken($personality_id);

            if (!$result) {
                $is_deep_sleep = false;
                if (function_exists('mpu_get_sleep_settings')) {
                    $sleep_settings = mpu_get_sleep_settings($personality_id);
                    $hour           = (int) wp_date('G');
                    $d_start        = (int) $sleep_settings['deep_sleep_start'];
                    $d_end          = (int) $sleep_settings['deep_sleep_end'];

                    if ($d_start < $d_end) {
                        $is_deep_sleep = ($hour >= $d_start && $hour < $d_end);
                    } else {
                        $is_deep_sleep = ($hour >= $d_start || $hour < $d_end);
                    }
                }

                if ($is_deep_sleep) {
                    return $this->ok([
                        'success'        => true,
                        'message'        => __('キャラクターが一時的に起こされました（深い眠り中。ページを更新すると再び眠ります）', 'mp-ukagaka'),
                        'personality_id' => $personality_id,
                        'is_temporary'   => true,
                    ]);
                }

                return new WP_Error(
                    'rest_wake_ghost_unavailable',
                    __('現在キャラクターを起こすことができません（二度寝時間外か、機能が無効の可能性があります）', 'mp-ukagaka'),
                    [
                        'status'         => 400,
                        'personality_id' => $personality_id,
                        'current_time'   => wp_date('H:i:s'),
                    ]
                );
            }
        }

        return $this->ok([
            'success'        => true,
            'message'        => __('キャラクターが起こされました', 'mp-ukagaka'),
            'personality_id' => $personality_id,
        ]);
    }
}
