<?php

/**
 * REST API Endpoints: Touch & Decoration Handlers
 * 
 * @package MP_Ukagaka
 * @subpackage REST
 */

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Register Touch REST API routes.
 */
function mpu_register_rest_touch_routes() {
    $namespace = 'mp-ukagaka/v1';

    register_rest_route($namespace, '/touch/decoration', array(
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'mpu_rest_decoration_chat',
        'permission_callback' => '__return_true',
    ));

    register_rest_route($namespace, '/touch/zone', array(
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'mpu_rest_touch_zone_chat',
        'permission_callback' => '__return_true',
    ));
}
add_action('rest_api_init', 'mpu_register_rest_touch_routes');

/**
 * REST Callback: 裝飾物點擊對話
 */
function mpu_rest_decoration_chat( WP_REST_Request $request )
{
    $mpu_opt = mpu_get_option();

    if (empty($mpu_opt['ai_enabled'])) {
        return new WP_Error('rest_error', __('AI 功能未啟用', 'mp-ukagaka'), ['status' => 400]);
    }

    $rl = mpu_rest_check_rate_limit('decoration_chat', 20, 60);
    if ($rl !== null) return $rl;

    $decoration_type_param = $request->get_param('decoration_type');
    $decoration_type = !empty($decoration_type_param) ? sanitize_text_field(wp_unslash($decoration_type_param)) : '';

    if (empty($decoration_type)) {
        return new WP_Error('rest_error', __('未指定裝飾物類型', 'mp-ukagaka'), ['status' => 400]);
    }

    $personality_id = function_exists('mpu_get_current_personality_id')
        ? mpu_get_current_personality_id()
        : null;

    $user_prompt = mpu_get_decoration_prompt($decoration_type, $personality_id);

    if ($user_prompt === false) {
        return new WP_Error('rest_error', __('未知的裝飾物類型', 'mp-ukagaka'), ['status' => 400]);
    }

    $ukagaka_name = $mpu_opt['ukagakas'][$mpu_opt['cur_ukagaka']]['name'] ?? 'キャラクター';

    $user_prompt .= "\n\n【回応ルール】淡々とした常体で、30-150文字で{$ukagaka_name}として直接反応すること。第三者視点の描写は禁止。";

    $language = $mpu_opt['ai_language'] ?? 'ja';

    $system_prompt = mpu_resolve_system_prompt($personality_id, $mpu_opt, $ukagaka_name);

    $provider = mpu_get_current_provider($mpu_opt);
    $api_key  = mpu_get_provider_api_key($provider, $mpu_opt);
    if ($provider !== 'ollama' && empty($api_key)) {
        return new WP_Error('rest_error', ucfirst($provider) . ' API Key 未設定', ['status' => 400]);
    }

    $max_tokens = 800;
    if (function_exists('mpu_get_personality_max_tokens')) {
        $max_tokens = mpu_get_personality_max_tokens($personality_id);
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
        return new WP_Error('rest_error', $result->get_error_message(), ['status' => 400]);
    }

    $max_length = 500;
    if (function_exists('mpu_get_personality_max_response_length')) {
        $max_length = mpu_get_personality_max_response_length($personality_id);
    }
    if (mb_strlen($result, 'UTF-8') > $max_length) {
        $result = mb_substr($result, 0, $max_length, 'UTF-8') . '...';
    }

    $emoji = null;
    if (function_exists('mpu_analyze_emoji_from_text') && !empty($result)) {
        $emoji = mpu_analyze_emoji_from_text($result, $personality_id);
    }

    if (function_exists('mpu_record_conversation')) {
        mpu_record_conversation('decoration');
    }

    return new WP_REST_Response([
        'msg' => $result,
        'emoji' => $emoji
    ], 200);
}

/**
 * REST Callback: 角色觸摸區域對話
 */
function mpu_rest_touch_zone_chat( WP_REST_Request $request )
{
    $mpu_opt = mpu_get_option();

    if (empty($mpu_opt['ai_enabled'])) {
        return new WP_Error('rest_error', __('AI 功能未啟用', 'mp-ukagaka'), ['status' => 400]);
    }

    $rl = mpu_rest_check_rate_limit('touch_zone_chat', 20, 60);
    if ($rl !== null) return $rl;

    $touch_zone_param = $request->get_param('touch_zone');
    $touch_zone = !empty($touch_zone_param) ? sanitize_text_field(wp_unslash($touch_zone_param)) : '';

    if (empty($touch_zone)) {
        return new WP_Error('rest_error', __('未指定觸摸區域', 'mp-ukagaka'), ['status' => 400]);
    }

    $ukagaka_name = $mpu_opt['ukagakas'][$mpu_opt['cur_ukagaka']]['name'] ?? 'キャラクター';

    $personality_id = null;
    if (function_exists('mpu_get_personality_id_from_ukagaka_name')) {
        $personality_id = mpu_get_personality_id_from_ukagaka_name($mpu_opt['cur_ukagaka']);
    }
    if ($personality_id === null && function_exists('mpu_get_current_personality_id')) {
        $personality_id = mpu_get_current_personality_id();
    }

    $touchzones = [];
    if (function_exists('mpu_load_personality_touchzones')) {
        $touchzones = mpu_load_personality_touchzones($personality_id);
    }

    $zone_config = $touchzones['zones'][$touch_zone] ?? null;
    if (!$zone_config) {
        return new WP_Error('rest_error', __('未知的觸摸區域', 'mp-ukagaka'), ['status' => 400]);
    }

    $prompt_categories = $zone_config['reactions'] ?? ['touch_body'];

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
        $user_prompt = "触られた反応をする";
    } else {
        $user_prompt = $available_prompts[array_rand($available_prompts)];
    }

    $user_prompt .= "\n\n【回応ルール】淡々とした常体で、30-150文字で{$ukagaka_name}として直接反応すること。第三者視点の描写は禁止。";

    $language = $mpu_opt['ai_language'] ?? 'ja';

    $system_prompt = mpu_resolve_system_prompt($personality_id, $mpu_opt, $ukagaka_name);

    $provider = mpu_get_current_provider($mpu_opt);
    $api_key  = mpu_get_provider_api_key($provider, $mpu_opt);
    if ($provider !== 'ollama' && empty($api_key)) {
        return new WP_Error('rest_error', ucfirst($provider) . ' API Key 未設定', ['status' => 400]);
    }

    $max_tokens = 800;
    if (function_exists('mpu_get_personality_max_tokens')) {
        $max_tokens = mpu_get_personality_max_tokens($personality_id);
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
        return new WP_Error('rest_error', $result->get_error_message(), ['status' => 400]);
    }

    $max_length = 500;
    if (function_exists('mpu_get_personality_max_response_length')) {
        $max_length = mpu_get_personality_max_response_length($personality_id);
    }
    if (mb_strlen($result, 'UTF-8') > $max_length) {
        $result = mb_substr($result, 0, $max_length, 'UTF-8') . '...';
    }

    $emoji = null;
    if (function_exists('mpu_analyze_emoji_from_text') && !empty($result)) {
        $emoji = mpu_analyze_emoji_from_text($result, $personality_id);
    }

    if (function_exists('mpu_record_conversation')) {
        mpu_record_conversation('touch');
    }

    return new WP_REST_Response([
        'msg' => $result,
        'emoji' => $emoji,
        'zone' => $touch_zone
    ], 200);
}
