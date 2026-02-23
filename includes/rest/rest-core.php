<?php

/**
 * REST API Endpoints: Core Handlers
 * 
 * @package MP_Ukagaka
 * @subpackage REST
 */

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Register Core REST API routes.
 */
function mpu_register_rest_core_routes() {
    $namespace = 'mp-ukagaka/v1';

    register_rest_route($namespace, '/nextmsg', array(
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'mpu_rest_nextmsg',
        'permission_callback' => '__return_true',
    ));

    register_rest_route($namespace, '/extend', array(
        'methods'             => WP_REST_Server::READABLE . ',' . WP_REST_Server::CREATABLE,
        'callback'            => 'mpu_rest_extend',
        'permission_callback' => '__return_true',
    ));

    register_rest_route($namespace, '/change', array(
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'mpu_rest_change',
        'permission_callback' => '__return_true',
    ));

    register_rest_route($namespace, '/settings', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'mpu_rest_get_settings',
        'permission_callback' => '__return_true',
    ));

    register_rest_route($namespace, '/dialog', array(
        'methods'             => WP_REST_Server::READABLE . ',' . WP_REST_Server::CREATABLE,
        'callback'            => 'mpu_rest_load_dialog',
        'permission_callback' => '__return_true',
    ));

    register_rest_route($namespace, '/visitor-info', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'mpu_rest_get_visitor_info',
        'permission_callback' => '__return_true',
    ));

    register_rest_route($namespace, '/decoration-prompts', array(
        'methods'             => WP_REST_Server::READABLE . ',' . WP_REST_Server::CREATABLE,
        'callback'            => 'mpu_rest_get_decoration_prompts',
        'permission_callback' => '__return_true',
    ));

    register_rest_route($namespace, '/wake-ghost', array(
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'mpu_rest_wake_ghost',
        'permission_callback' => '__return_true',
    ));

    register_rest_route($namespace, '/shell-info', array(
        'methods'             => WP_REST_Server::READABLE . ',' . WP_REST_Server::CREATABLE,
        'callback'            => 'mpu_rest_get_shell_info',
        'permission_callback' => '__return_true',
    ));

    register_rest_route($namespace, '/decoration-config', array(
        'methods'             => WP_REST_Server::READABLE . ',' . WP_REST_Server::CREATABLE,
        'callback'            => 'mpu_rest_get_decoration_config',
        'permission_callback' => '__return_true',
    ));

    register_rest_route($namespace, '/emoji-config', array(
        'methods'             => WP_REST_Server::READABLE . ',' . WP_REST_Server::CREATABLE,
        'callback'            => 'mpu_rest_get_emoji_config',
        'permission_callback' => '__return_true',
    ));
}
add_action('rest_api_init', 'mpu_register_rest_core_routes');

/**
 * REST Callback: Get Next Message
 */
function mpu_rest_nextmsg( WP_REST_Request $request ) {
    $rl = mpu_rest_check_rate_limit('nextmsg', 20, 60);
    if ($rl !== null) return $rl;

    $mpu_opt = mpu_get_option();

    $cur_num = $request->get_param("cur_num");
    if (empty($cur_num)) {
        $cur_num = $mpu_opt["cur_ukagaka"] ?? "default_1";
    }

    $cur_msgnum = intval($request->get_param("cur_msgnum") ?: 0);
    $is_llm_enabled = mpu_is_llm_replace_dialogue_enabled();

    if ($is_llm_enabled) {
        $last_response = sanitize_text_field($request->get_param('last_response') ?: '');

        $response_history = [];
        $history_param = $request->get_param('response_history');
        if (!empty($history_param)) {
            // Note: In WP_REST_Request string formats unescaped automatically but JSON decoding string representation might still be needed.
            if (is_string($history_param)) {
                $decoded_history = json_decode(wp_unslash($history_param), true);
            } else {
                $decoded_history = (array)$history_param;
            }

            if (json_last_error() === JSON_ERROR_NONE || is_array($decoded_history)) {
                $response_history = array_slice($decoded_history, -10);
                $response_history = array_map('sanitize_text_field', $response_history);
            }
        }

        $last_visit_hours = intval($request->get_param('last_visit_hours') ?? -1);

        $llm_msg = mpu_generate_llm_dialogue($cur_num, $last_response, $response_history, $last_visit_hours);
        $use_fallback = ($llm_msg === 'MPU_USE_FALLBACK' || $llm_msg === 'MPU_OLLAMA_BUSY');

        if ($llm_msg !== false && $llm_msg !== 'MPU_OLLAMA_NOT_AVAILABLE' && !$use_fallback) {
            $msg = $llm_msg;
            $msgnum = 0;

            if (function_exists('mpu_record_conversation')) {
                mpu_record_conversation('auto_talk');
            }
        } elseif ($use_fallback || $llm_msg === false) {
            $msg_array = mpu_get_msg_arr($cur_num);
            $msgs = $msg_array["msg"] ?? [];
            $total = count($msgs);

            if ($total > 0) {
                $msgnum = wp_rand(0, $total - 1);
                $msg = $msgs[$msgnum];
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    $reason = $llm_msg === 'MPU_USE_FALLBACK' ? '重複檢測' : ($llm_msg === 'MPU_OLLAMA_BUSY' ? 'Ollama 忙碌' : '生成失敗');
                    if (function_exists('mpu_debug_log')) { mpu_debug_log('MP Ukagaka - ' . $reason . '，改用內建對話'); } else { error_log('MP Ukagaka - ' . $reason . '，改用內建對話'); }
                }
            } else {
                $msg = __("無對話內容", "mp-ukagaka");
                $msgnum = 0;
            }
        } else {
            $msg = __("本機 Ollama 程式未啟動，請檢查 Ollama 服務是否正在運行。", "mp-ukagaka");
            $msgnum = 0;
            if (defined('WP_DEBUG') && WP_DEBUG) {
                if (function_exists('mpu_debug_log')) { mpu_debug_log('MP Ukagaka - LLM 生成失敗，返回錯誤提示。llm_msg = ' . ($llm_msg === false ? 'false' : $llm_msg)); } else { error_log('MP Ukagaka - LLM 生成失敗，返回錯誤提示。llm_msg = ' . ($llm_msg === false ? 'false' : $llm_msg)); }
            }
        }
    } else {
        $msg_array = mpu_get_msg_arr($cur_num);
        $msgs = $msg_array["msg"] ?? [];
        $total = count($msgs);

        if (($mpu_opt["next_msg"] ?? 0) == 0) {
            $next = $cur_msgnum + 1;
            if (isset($msgs[$next])) {
                $msg = $msgs[$next];
                $msgnum = $next;
            } else {
                $msg = $msgs[0] ?? __("無對話內容", "mp-ukagaka");
                $msgnum = 0;
            }
        } else {
            if ($total > 0) {
                $msgnum = wp_rand(0, $total - 1);
                $msg = $msgs[$msgnum];
            } else {
                $msg = __("無對話內容", "mp-ukagaka");
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

    $emoji = null;
    if (function_exists('mpu_analyze_emoji_from_text') && !empty($msg)) {
        $personality_id = null;
        if (function_exists('mpu_get_personality_id_from_ukagaka_name')) {
            $personality_id = mpu_get_personality_id_from_ukagaka_name($cur_num);
        }
        $emoji = mpu_analyze_emoji_from_text($msg, $personality_id);
    }

    return new WP_REST_Response([
        "msg" => $msg,
        "msgnum" => $msgnum,
        "emoji" => $emoji
    ], 200);
}

/**
 * REST Callback: Extend
 */
function mpu_rest_extend( WP_REST_Request $request ) {
    $rl = mpu_rest_check_rate_limit('extend', 10, 60);
    if ($rl !== null) return $rl;

    return new WP_REST_Response(['label' => __("更換偽春菜", "mp-ukagaka")], 200);
}

/**
 * REST Callback: Change Ghost
 */
function mpu_rest_change( WP_REST_Request $request ) {
    $rl = mpu_rest_check_rate_limit('change', 10, 60);
    if ($rl !== null) return $rl;

    $mpu_opt = mpu_get_option();

    $mpu_num = $request->get_param("mpu_num");
    if (!isset($mpu_num)) {
        $items = [];
        if (!empty($mpu_opt["ukagakas"])) {
            foreach ($mpu_opt["ukagakas"] as $key => $value) {
                if (!empty($value["show"])) {
                    $items[] = [
                        'key'  => $key,
                        'name' => sanitize_text_field($value["name"]),
                    ];
                }
            }
        }
        return new WP_REST_Response([
            'heading'       => __("偽春菜們", "mp-ukagaka"),
            'items'         => $items,
            'empty_message' => empty($items) ? __("沒有可供選擇的偽春菜", "mp-ukagaka") : null,
        ], 200);
    }

    $mpu_num = sanitize_text_field($mpu_num);
    if (empty($mpu_opt["ukagakas"][$mpu_num])) {
        $mpu_num = "default_1";
    }

    $temp = [];
    $ext = $mpu_opt["external_file_format"] ?? "txt";
    $dialog_filename = $mpu_opt["ukagakas"][$mpu_num]["dialog_filename"] ?? $mpu_num;
    $temp["msglist"] = [
        "msgall" => 0,
        "auto_msg" => $mpu_opt["auto_msg"] ?? "",
        "msg" => [],
    ];
    $temp["shell"] = $mpu_opt["ukagakas"][$mpu_num]["shell"] ?? "";
    $temp["shell_info"] = mpu_get_shell_info($mpu_num);
    $temp["msg"] = ""; 
    $temp["name"] = $mpu_opt["ukagakas"][$mpu_num]["name"] ?? "";
    $temp["num"] = $mpu_num;
    $temp["dialog_filename"] = $dialog_filename;
    $temp["data_file"] = "dialogs/" . $dialog_filename . "." . $ext;

    $cookie_path   = defined('SITECOOKIEPATH') ? SITECOOKIEPATH : '/';
    $cookie_domain = defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : '';

    $cookie_name  = "mpu_ukagaka_" . COOKIEHASH;
    $cookie_value = rawurlencode($mpu_num);
    $expires      = gmdate('D, d M Y H:i:s \G\M\T', time() + DAY_IN_SECONDS);
    $cookie_str   = "{$cookie_name}={$cookie_value}; Path={$cookie_path}; Expires={$expires}; HttpOnly; SameSite=Lax";
    if ( is_ssl() ) {
        $cookie_str .= '; Secure';
    }
    if ( !empty($cookie_domain) ) {
        $cookie_str .= '; Domain=' . $cookie_domain;
    }

    $response = new WP_REST_Response($temp, 200);
    $response->header('Set-Cookie', $cookie_str);
    return $response;
}

/**
 * REST Callback: Get Settings
 */
function mpu_rest_get_settings( WP_REST_Request $request ) {
    $rl = mpu_rest_check_rate_limit('get_settings', 30, 60);
    if ($rl !== null) return $rl;

    $mpu_opt = mpu_get_option();

    $current_personality = function_exists('mpu_get_current_personality_id')
        ? mpu_get_current_personality_id()
        : null;

    $sleep_mode = function_exists('mpu_get_sleep_mode_settings')
        ? mpu_get_sleep_mode_settings($current_personality)
        : ['enabled' => false, 'frequency_multiplier' => 1.0];

    $settings = [
        "auto_talk" => !empty($mpu_opt["auto_talk"]),
        "auto_talk_interval" => intval($mpu_opt["auto_talk_interval"] ?? 8),
        "typewriter_speed" => intval($mpu_opt["typewriter_speed"] ?? 40),
        "ai_enabled" => !empty($mpu_opt["ai_enabled"]),
        "ai_probability" => intval($mpu_opt["ai_probability"] ?? 10),
        "ai_trigger_pages" => sanitize_text_field($mpu_opt["ai_trigger_pages"] ?? "is_single"),
        "ai_text_color" => sanitize_hex_color($mpu_opt["ai_text_color"] ?? "#000000"),
        "ai_display_duration" => intval($mpu_opt["ai_display_duration"] ?? 8),
        "ai_greet_first_visit" => !empty($mpu_opt["ai_greet_first_visit"]),
        "ollama_replace_dialogue" => mpu_is_llm_replace_dialogue_enabled(),
        "enable_chat_mode" => !empty($mpu_opt["enable_chat_mode"]),
        "sleep_mode" => $sleep_mode,
    ];

    return new WP_REST_Response($settings, 200);
}

/**
 * REST Callback: Load Dialog
 */
function mpu_rest_load_dialog( WP_REST_Request $request ) {
    $rl = mpu_rest_check_rate_limit('load_dialog', 30, 60);
    if ($rl !== null) return $rl;

    $mpu_opt = mpu_get_option();
    $file_param = $request->get_param('file');
    $file = !empty($file_param) ? basename(sanitize_text_field($file_param)) : "";

    if ($file === "" || !preg_match('/^[a-zA-Z0-9_\-]+\.(json|txt)$/', $file)) {
        return new WP_Error('rest_error', __('發生未知錯誤，請檢查日誌', 'mp-ukagaka'), ['status' => 400]);
    }

    $file_path = mpu_get_dialogs_dir() . "/" . $file;
    $content = mpu_secure_file_read($file_path);

    if (is_wp_error($content)) {
        return new WP_Error('rest_error', __('發生未知錯誤，請檢查日誌', 'mp-ukagaka'), ['status' => 400]);
    }

    $ext = pathinfo($file, PATHINFO_EXTENSION);
    if ($ext === "json") {
        $json = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('rest_error', __('發生未知錯誤，請檢查日誌', 'mp-ukagaka'), ['status' => 400]);
        }
        if (empty($json["messages"]) || !is_array($json["messages"]) || count($json["messages"]) === 0) {
            return new WP_Error('rest_error', __('發生未知錯誤，請檢查日誌', 'mp-ukagaka'), ['status' => 400]);
        }
        $msg_array = $json["messages"];
    } else {
        $msg_array = mpu_str2array($content);
        if (empty($msg_array) || !is_array($msg_array) || count($msg_array) === 0) {
            return new WP_Error('rest_error', __('發生未知錯誤，請檢查日誌', 'mp-ukagaka'), ['status' => 400]);
        }
    }

    $arr = [
        "msgall" => max(0, count($msg_array) - 1),
        "auto_msg" => $mpu_opt["auto_msg"] ?? "",
        "msg" => mpu_msg_code($msg_array),
        "next_msg" => intval($mpu_opt["next_msg"] ?? 0),
        "default_msg" => intval($mpu_opt["default_msg"] ?? 0),
    ];

    $auto_msg_array = mpu_msg_code([$arr["auto_msg"]]);
    $arr["auto_msg"] = implode(" ", $auto_msg_array);

    return new WP_REST_Response($arr, 200);
}

/**
 * REST Callback: Visitor Info
 */
function mpu_rest_get_visitor_info( WP_REST_Request $request ) {
    $rl = mpu_rest_check_rate_limit('get_visitor_info', 30, 60);
    if ($rl !== null) return $rl;

    $referrer = isset($_SERVER['HTTP_REFERER']) ? esc_url_raw($_SERVER['HTTP_REFERER']) : "";
    $ip = mpu_get_client_ip(); // 僅供 Slimstat 查詢，不回傳至前端

    $visitor_info = [
        "referrer"         => $referrer,
        "is_direct"        => empty($referrer),
        "slimstat_enabled" => false,
    ];

    if (class_exists('wp_slimstat')) {
        $visitor_info["slimstat_enabled"] = true;
        global $wpdb;
        $slimstat_table = $wpdb->prefix . 'slim_stats';
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $slimstat_table));

        if ($table_exists == $slimstat_table) {
            $query = $wpdb->prepare("SELECT referer, country, city FROM {$slimstat_table} WHERE ip = %s ORDER BY dt DESC LIMIT 1", $ip);
            $result = $wpdb->get_row($query, OBJECT);

            if (!empty($result)) {
                if (!empty($result->referer)) {
                    $visitor_info["referrer"] = esc_url_raw($result->referer);
                }
                if (!empty($result->country)) $visitor_info["slimstat_country"] = sanitize_text_field($result->country);
                if (!empty($result->city))    $visitor_info["slimstat_city"]    = sanitize_text_field($result->city);
            }
        }
    }

    if (!empty($visitor_info["referrer"])) {
        $parsed_url = parse_url($visitor_info["referrer"]);
        $visitor_info["referrer_host"] = isset($parsed_url['host']) ? $parsed_url['host'] : "";
        $visitor_info["referrer_path"] = isset($parsed_url['path']) ? $parsed_url['path'] : "";

        $search_engines = ['google', 'bing', 'yahoo', 'baidu', 'yandex', 'duckduckgo', 'naver'];
        $referrer_host_lower = strtolower($visitor_info["referrer_host"]);
        foreach ($search_engines as $engine) {
            if (strpos($referrer_host_lower, $engine) !== false) {
                $visitor_info["search_engine"] = $engine;
                break;
            }
        }
    }

    return new WP_REST_Response($visitor_info, 200);
}

/**
 * REST Callback: Decoration Prompts
 */
function mpu_rest_get_decoration_prompts( WP_REST_Request $request ) {
    $rl = mpu_rest_check_rate_limit('get_decoration_prompts', 20, 60);
    if ($rl !== null) return $rl;

    $personality_id = function_exists('mpu_get_current_personality_id') ? mpu_get_current_personality_id() : null;
    $decoration_types = mpu_get_available_decoration_types($personality_id);

    $decoration_type = sanitize_text_field($request->get_param('decoration_type') ?: '');
    $prompts = [];

    if ($decoration_type) {
        $prompt = mpu_get_decoration_prompt($decoration_type, $personality_id);
        if ($prompt !== false) {
            $prompts[$decoration_type] = $prompt;
        } else {
            return new WP_Error('rest_error', __('安全性驗證失敗', 'mp-ukagaka'), ['status' => 400]);
        }
    } else {
        foreach ($decoration_types as $type) {
            $prompt = mpu_get_decoration_prompt($type, $personality_id);
            if ($prompt !== false) {
                $prompts[$type] = $prompt;
            }
        }
    }

    return new WP_REST_Response(['prompts' => $prompts], 200);
}

/**
 * REST Callback: Wake Ghost
 */
function mpu_rest_wake_ghost( WP_REST_Request $request ) {
    $rl = mpu_rest_check_rate_limit('wake_ghost', 10, 60);
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
            __('缺少角色 ID 參數（personality_id / ukagaka_num）', 'mp-ukagaka'),
            ['status' => 400]
        );
    }

    if (function_exists('mpu_mark_ip_as_woken')) {
        $result = mpu_mark_ip_as_woken($personality_id);

        if (!$result) {
            $is_deep_sleep = false;
            if (function_exists('mpu_get_sleep_settings')) {
                $sleep_settings = mpu_get_sleep_settings($personality_id);
                $hour = (int) wp_date('G');
                $d_start = (int)$sleep_settings['deep_sleep_start'];
                $d_end = (int)$sleep_settings['deep_sleep_end'];
                
                if ($d_start < $d_end) {
                    $is_deep_sleep = ($hour >= $d_start && $hour < $d_end);
                } else {
                    $is_deep_sleep = ($hour >= $d_start || $hour < $d_end);
                }
            }

            if ($is_deep_sleep) {
                return new WP_REST_Response([
                    'success' => true,
                    'message' => __('角色已被暫時喚醒（深度睡眠期間，刷新頁面將恢復睡眠）', 'mp-ukagaka'),
                    'personality_id' => $personality_id,
                    'is_temporary' => true
                ], 200);
            }

            return new WP_Error(
                'rest_wake_ghost_unavailable',
                __('當前無法喚醒角色（可能未在賴床時間或未啟用賴床功能）', 'mp-ukagaka'),
                [
                    'status'         => 400,
                    'personality_id' => $personality_id,
                    'current_time'   => wp_date('H:i:s'),
                ]
            );
        }
    }

    return new WP_REST_Response([
        'success' => true,
        'message' => __('角色已被喚醒', 'mp-ukagaka'),
        'personality_id' => $personality_id,
    ], 200);
}

/**
 * REST Callback: Shell Info
 */
function mpu_rest_get_shell_info( WP_REST_Request $request ) {
    $rl = mpu_rest_check_rate_limit('get_shell_info', 30, 60);
    if ($rl !== null) return $rl;

    $mpu_opt = mpu_get_option();
    $ukagaka_num = sanitize_text_field($request->get_param('ukagaka_num') ?: '');
    if (empty($ukagaka_num)) {
        $ukagaka_num = $mpu_opt['cur_ukagaka'] ?? 'default_1';
    }

    if (empty($mpu_opt['ukagakas'][$ukagaka_num])) {
        $ukagaka_num = 'default_1';
    }

    $shell_info = mpu_get_shell_info($ukagaka_num);
    $ukagaka_name = $mpu_opt['ukagakas'][$ukagaka_num]['name'] ?? '';

    return new WP_REST_Response([
        'success' => true,
        'shell_info' => $shell_info,
        'ukagaka_name' => $ukagaka_name,
        'ukagaka_num' => $ukagaka_num,
    ], 200);
}

/**
 * REST Callback: Decoration Config
 */
function mpu_rest_get_decoration_config( WP_REST_Request $request ) {
    $rl = mpu_rest_check_rate_limit('get_decoration_config', 30, 60);
    if ($rl !== null) return $rl;

    $personality_id = function_exists('mpu_get_current_personality_id') ? mpu_get_current_personality_id() : 'Frieren';

    $decoration_config = [];
    if (function_exists('mpu_load_personality_decorations')) {
        $decorations_data = mpu_load_personality_decorations($personality_id);
        if (!empty($decorations_data['items'])) {
            $decoration_config = $decorations_data['items'];
        }
    }

    $main_file = defined('MPU_MAIN_FILE') ? MPU_MAIN_FILE : dirname(dirname(dirname(__FILE__))) . '/mp-ukagaka.php';
    $decorations_base_url = esc_url(plugins_url("ghost/{$personality_id}/decorations/", $main_file));

    $touchzones_config = [];
    if (function_exists('mpu_load_personality_touchzones')) {
        $touchzones_config = mpu_load_personality_touchzones($personality_id);
    }

    $mpu_opt = mpu_get_option();
    $show_decorations = isset($mpu_opt['ukagakas']['default_1']['show_decorations']) ? $mpu_opt['ukagakas']['default_1']['show_decorations'] : true;

    return new WP_REST_Response([
        'success' => true,
        'decorations_base_url' => $decorations_base_url,
        'decoration_config' => $decoration_config,
        'touchzones' => $touchzones_config,
        'show_decorations' => $show_decorations,
    ], 200);
}

/**
 * REST Callback: Emoji Config
 */
function mpu_rest_get_emoji_config( WP_REST_Request $request ) {
    $rl = mpu_rest_check_rate_limit('get_emoji_config', 30, 60);
    if ($rl !== null) return $rl;

    $personality_id = function_exists('mpu_get_current_personality_id') ? mpu_get_current_personality_id() : 'Frieren';

    $emoji_base_url = '';
    if (function_exists('mpu_get_personality_emoji_url')) {
        $emoji_base_url = mpu_get_personality_emoji_url($personality_id);
    }

    $emoji_config = [];
    if (function_exists('mpu_load_personality_emoji_config')) {
        $emoji_config = mpu_load_personality_emoji_config($personality_id);
    }

    $emoji_mappings = [];
    if (function_exists('mpu_load_personality_emoji_keywords')) {
        $emoji_mappings = mpu_load_personality_emoji_keywords($personality_id);
    }

    return new WP_REST_Response([
        'success' => true,
        'baseUrl' => $emoji_base_url,
        'supportedEmojis' => $emoji_config['supported'] ?? [],
        'mappings' => $emoji_mappings,
    ], 200);
}
