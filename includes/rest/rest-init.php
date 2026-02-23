<?php

/**
 * REST API Endpoints
 * 
 * @package MP_Ukagaka
 * @subpackage REST
 */

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Register REST API routes for MP Ukagaka.
 */
function mpu_register_rest_routes() {
    register_rest_route('mp-ukagaka/v1', '/init', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'mpu_rest_init',
        'permission_callback' => '__return_true', // Publicly accessible, same as wp_ajax_nopriv
    ));
}
add_action('rest_api_init', 'mpu_register_rest_routes');

/**
 * REST Callback: Init Endpoint
 * 
 * Replaces the old mpu_ajax_init admin-ajax action.
 * Merges shell_info, decoration_config, emoji_config, and settings.
 * 
 * @param WP_REST_Request $request Full data about the request.
 * @return WP_Error|WP_REST_Response
 */
function mpu_rest_init( WP_REST_Request $request ) {
    // Note: Nonce validation is handled by Cookie Authentication implicitly by WordPress REST API
    // if the user is logged in. However, for a public-facing plugin, we can just rely on standard HTTP requests.
    // If rate limiting is required per IP or user, we can add it here.
    
    // 速率限制 - 30次/分鐘
    $rl = mpu_rest_check_rate_limit('init', 30, 60);
    if ($rl !== null) return $rl;

    $mpu_opt = mpu_get_option();

    // 獲取 ukagaka_num 參數 (from query string)
    $ukagaka_num = $request->get_param('ukagaka_num');
    if (empty($ukagaka_num)) {
        $ukagaka_num = $mpu_opt['cur_ukagaka'] ?? 'default_1';
    }

    // 驗證 ukagaka_num 是否有效
    if (empty($mpu_opt['ukagakas'][$ukagaka_num])) {
        $ukagaka_num = 'default_1';
    }

    // 獲取當前人格 ID
    $personality_id = function_exists('mpu_get_current_personality_id')
        ? mpu_get_current_personality_id()
        : 'Frieren';

    $main_file = defined('MPU_MAIN_FILE') ? MPU_MAIN_FILE : dirname(dirname(dirname(__FILE__))) . '/mp-ukagaka.php';

    // ===== 1. Shell Info =====
    $shell_info = mpu_get_shell_info($ukagaka_num);
    $ukagaka_name = $mpu_opt['ukagakas'][$ukagaka_num]['name'] ?? '';

    // ===== 2. Decoration Config =====
    $decoration_config = [];
    if (function_exists('mpu_load_personality_decorations')) {
        $decorations_data = mpu_load_personality_decorations($personality_id);
        if (!empty($decorations_data['items'])) {
            $decoration_config = $decorations_data['items'];
        }
    }
    $decorations_base_url = esc_url(plugins_url("ghost/{$personality_id}/decorations/", $main_file));
    $touchzones_config = function_exists('mpu_load_personality_touchzones')
        ? mpu_load_personality_touchzones($personality_id)
        : [];
    $show_decorations = isset($mpu_opt['ukagakas']['default_1']['show_decorations'])
        ? $mpu_opt['ukagakas']['default_1']['show_decorations']
        : true;

    // ===== 3. Emoji Config =====
    $emoji_base_url = function_exists('mpu_get_personality_emoji_url')
        ? mpu_get_personality_emoji_url($personality_id)
        : '';
    $emoji_config = function_exists('mpu_load_personality_emoji_config')
        ? mpu_load_personality_emoji_config($personality_id)
        : [];
    $emoji_mappings = function_exists('mpu_load_personality_emoji_keywords')
        ? mpu_load_personality_emoji_keywords($personality_id)
        : [];

    // ===== 4. Settings =====
    $sleep_mode = function_exists('mpu_get_sleep_mode_settings')
        ? mpu_get_sleep_mode_settings($personality_id)
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

    $response_data = [
        'success'              => true,
        'shell_info'           => $shell_info,
        'ukagaka_name'         => $ukagaka_name,
        'ukagaka_num'          => $ukagaka_num,
        'personality_id'       => $personality_id,
        'decorations_base_url' => $decorations_base_url,
        'decoration_config'    => $decoration_config,
        'touchzones'           => $touchzones_config,
        'show_decorations'     => $show_decorations,
        'emoji_base_url'       => $emoji_base_url,
        'supported_emojis'     => $emoji_config['supported'] ?? [],
        'emoji_mappings'       => $emoji_mappings,
        'settings'             => $settings,
    ];

    return new WP_REST_Response($response_data, 200);
}
