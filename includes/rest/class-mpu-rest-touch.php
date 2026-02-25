<?php

/**
 * REST Controller: Touch & Decoration Handlers
 *
 * 取代 rest-touch.php。路由 URL、HTTP method、permission、
 * rate limit key、response 結構與 WP_Error code 均與舊 procedural 實作一致。
 *
 * 端點：
 *   POST /mp-ukagaka/v1/touch/decoration
 *   POST /mp-ukagaka/v1/touch/zone
 *
 * @package MP_Ukagaka
 * @subpackage REST
 */

if (!defined('ABSPATH')) {
    exit();
}

class MPU_REST_Touch extends MPU_REST_Base {

    /**
     * 向 WordPress 註冊所有 Touch 端點。
     * 由 bootstrap.php 集中掛載到 rest_api_init。
     */
    public function register_routes() {
        register_rest_route($this->namespace, '/touch/decoration', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'decoration_chat'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($this->namespace, '/touch/zone', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'touch_zone_chat'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * POST /touch/decoration — 裝飾物點擊對話
     * Rate limit: 20 次 / 60 秒（與舊實作相同）
     */
    public function decoration_chat(WP_REST_Request $request) {
        $mpu_opt = mpu_get_option();

        if (empty($mpu_opt['ai_enabled'])) {
            return $this->fail('rest_error', __('AI 功能未啟用', 'mp-ukagaka'), 400);
        }

        $rl = $this->rate_limit('decoration_chat', 20, 60);
        if ($rl !== null) return $rl;

        $decoration_type_param = $request->get_param('decoration_type');
        $decoration_type = !empty($decoration_type_param)
            ? sanitize_text_field(wp_unslash($decoration_type_param))
            : '';

        if (empty($decoration_type)) {
            return $this->fail('rest_error', __('未指定裝飾物類型', 'mp-ukagaka'), 400);
        }

        $personality_id = function_exists('mpu_get_current_personality_id')
            ? mpu_get_current_personality_id()
            : null;

        $user_prompt = mpu_get_decoration_prompt($decoration_type, $personality_id);

        if ($user_prompt === false) {
            return $this->fail('rest_error', __('未知的裝飾物類型', 'mp-ukagaka'), 400);
        }

        $ukagaka_name = $mpu_opt['ukagakas'][$mpu_opt['cur_ukagaka']]['name'] ?? 'キャラクター';

        $user_prompt .= "\n\n【回応ルール】淡々とした常体で、30-150文字で{$ukagaka_name}として直接反応すること。第三者視点の描写は禁止。";

        $language = $mpu_opt['ai_language'] ?? 'ja';

        $system_prompt = mpu_resolve_system_prompt($personality_id, $mpu_opt, $ukagaka_name);

        $provider = mpu_get_current_provider($mpu_opt);
        $api_key  = mpu_get_provider_api_key($provider, $mpu_opt);
        if ($provider !== 'ollama' && empty($api_key)) {
            return $this->fail('rest_error', ucfirst($provider) . ' API Key 未設定', 400);
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
            return $this->fail('rest_error', $result->get_error_message(), 400);
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

        return $this->ok([
            'msg'   => $result,
            'emoji' => $emoji,
        ]);
    }

    /**
     * POST /touch/zone — 角色觸摸區域對話
     * Rate limit: 20 次 / 60 秒（與舊實作相同）
     */
    public function touch_zone_chat(WP_REST_Request $request) {
        $mpu_opt = mpu_get_option();

        if (empty($mpu_opt['ai_enabled'])) {
            return $this->fail('rest_error', __('AI 功能未啟用', 'mp-ukagaka'), 400);
        }

        $rl = $this->rate_limit('touch_zone_chat', 20, 60);
        if ($rl !== null) return $rl;

        $touch_zone_param = $request->get_param('touch_zone');
        $touch_zone = !empty($touch_zone_param)
            ? sanitize_text_field(wp_unslash($touch_zone_param))
            : '';

        if (empty($touch_zone)) {
            return $this->fail('rest_error', __('未指定觸摸區域', 'mp-ukagaka'), 400);
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
            return $this->fail('rest_error', __('未知的觸摸區域', 'mp-ukagaka'), 400);
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

        $user_prompt = empty($available_prompts)
            ? '触られた反応をする'
            : $available_prompts[array_rand($available_prompts)];

        $user_prompt .= "\n\n【回応ルール】淡々とした常体で、30-150文字で{$ukagaka_name}として直接反応すること。第三者視点の描写は禁止。";

        $language = $mpu_opt['ai_language'] ?? 'ja';

        $system_prompt = mpu_resolve_system_prompt($personality_id, $mpu_opt, $ukagaka_name);

        $provider = mpu_get_current_provider($mpu_opt);
        $api_key  = mpu_get_provider_api_key($provider, $mpu_opt);
        if ($provider !== 'ollama' && empty($api_key)) {
            return $this->fail('rest_error', ucfirst($provider) . ' API Key 未設定', 400);
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
            return $this->fail('rest_error', $result->get_error_message(), 400);
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

        return $this->ok([
            'msg'   => $result,
            'emoji' => $emoji,
            'zone'  => $touch_zone,
        ]);
    }
}
