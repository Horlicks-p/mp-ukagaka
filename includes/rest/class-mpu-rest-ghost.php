<?php

/**
 * REST Controller: Ghost Appearance & Settings
 *
 * 取代 rest-init.php（/init）與 rest-core.php 的外觀/設定端點。
 * 路由 URL、HTTP method、permission、rate limit key、response 結構
 * 及 WP_Error code 均與舊 procedural 實作一致。
 *
 * 端點：
 *   GET  /mp-ukagaka/v1/init
 *   GET  /mp-ukagaka/v1/settings
 *   POST /mp-ukagaka/v1/change
 *   GET,POST /mp-ukagaka/v1/extend
 *   GET,POST /mp-ukagaka/v1/shell-info
 *   GET,POST /mp-ukagaka/v1/decoration-config
 *   GET,POST /mp-ukagaka/v1/emoji-config
 *
 * @package MP_Ukagaka
 * @subpackage REST
 */

if (!defined('ABSPATH')) {
    exit();
}

class MPU_REST_Ghost extends MPU_REST_Base {

    /**
     * 向 WordPress 註冊所有 Ghost 端點。
     * 由 bootstrap.php 集中掛載到 rest_api_init。
     */
    public function register_routes() {
        register_rest_route($this->namespace, '/init', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'init'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($this->namespace, '/settings', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_settings'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($this->namespace, '/change', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'change'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($this->namespace, '/extend', [
            'methods'             => WP_REST_Server::READABLE . ',' . WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'extend'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($this->namespace, '/shell-info', [
            'methods'             => WP_REST_Server::READABLE . ',' . WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'get_shell_info'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($this->namespace, '/decoration-config', [
            'methods'             => WP_REST_Server::READABLE . ',' . WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'get_decoration_config'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($this->namespace, '/emoji-config', [
            'methods'             => WP_REST_Server::READABLE . ',' . WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'get_emoji_config'],
            'permission_callback' => '__return_true',
        ]);
    }

    // =========================================================================
    // GET /init — 統一初始化（取代 rest-init.php mpu_rest_init）
    // Rate limit: 30 次 / 60 秒
    // =========================================================================

    public function init(WP_REST_Request $request) {
        $rl = $this->rate_limit('init', 30, 60);
        if ($rl !== null) return $rl;

        $mpu_opt = mpu_get_option();

        $ukagaka_num = $request->get_param('ukagaka_num');
        if (empty($ukagaka_num)) {
            $ukagaka_num = $mpu_opt['cur_ukagaka'] ?? 'default_1';
        }

        if (empty($mpu_opt['ukagakas'][$ukagaka_num])) {
            $ukagaka_num = 'default_1';
        }

        $personality_id = function_exists('mpu_get_current_personality_id')
            ? mpu_get_current_personality_id()
            : 'Frieren';

        $main_file = defined('MPU_MAIN_FILE') ? MPU_MAIN_FILE : dirname(dirname(dirname(__FILE__))) . '/mp-ukagaka.php';

        // Shell Info
        $shell_info   = mpu_get_shell_info($ukagaka_num);
        $ukagaka_name = $mpu_opt['ukagakas'][$ukagaka_num]['name'] ?? '';

        // Decoration Config
        $decoration_config = [];
        if (function_exists('mpu_load_personality_decorations')) {
            $decorations_data = mpu_load_personality_decorations($personality_id);
            if (!empty($decorations_data['items'])) {
                $decoration_config = $decorations_data['items'];
            }
        }
        $decorations_base_url = esc_url(plugins_url("ghost/{$personality_id}/decorations/", $main_file));
        $touchzones_config    = function_exists('mpu_load_personality_touchzones')
            ? mpu_load_personality_touchzones($personality_id)
            : [];
        $show_decorations = isset($mpu_opt['ukagakas']['default_1']['show_decorations'])
            ? $mpu_opt['ukagakas']['default_1']['show_decorations']
            : true;

        // Emoji Config
        $emoji_base_url = function_exists('mpu_get_personality_emoji_url')
            ? mpu_get_personality_emoji_url($personality_id)
            : '';
        $emoji_config = function_exists('mpu_load_personality_emoji_config')
            ? mpu_load_personality_emoji_config($personality_id)
            : [];
        $emoji_mappings = function_exists('mpu_load_personality_emoji_keywords')
            ? mpu_load_personality_emoji_keywords($personality_id)
            : [];

        // Settings
        $sleep_mode = function_exists('mpu_get_sleep_mode_settings')
            ? mpu_get_sleep_mode_settings($personality_id)
            : ['enabled' => false, 'frequency_multiplier' => 1.0];

        $settings = [
            'auto_talk'              => !empty($mpu_opt['auto_talk']),
            'auto_talk_interval'     => intval($mpu_opt['auto_talk_interval'] ?? 8),
            'typewriter_speed'       => intval($mpu_opt['typewriter_speed'] ?? 40),
            'ai_enabled'             => !empty($mpu_opt['ai_enabled']),
            'ai_probability'         => intval($mpu_opt['ai_probability'] ?? 10),
            'ai_trigger_pages'       => sanitize_text_field($mpu_opt['ai_trigger_pages'] ?? 'is_single'),
            'ai_text_color'          => sanitize_hex_color($mpu_opt['ai_text_color'] ?? '#000000'),
            'ai_display_duration'    => intval($mpu_opt['ai_display_duration'] ?? 8),
            'ai_greet_first_visit'   => !empty($mpu_opt['ai_greet_first_visit']),
            'ollama_replace_dialogue' => mpu_is_llm_replace_dialogue_enabled(),
            'enable_chat_mode'       => !empty($mpu_opt['enable_chat_mode']),
            'sleep_mode'             => $sleep_mode,
        ];

        return $this->ok([
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
        ]);
    }

    // =========================================================================
    // GET /settings — Rate limit: 30 次 / 60 秒
    // =========================================================================

    public function get_settings(WP_REST_Request $request) {
        $rl = $this->rate_limit('get_settings', 30, 60);
        if ($rl !== null) return $rl;

        $mpu_opt = mpu_get_option();

        $current_personality = function_exists('mpu_get_current_personality_id')
            ? mpu_get_current_personality_id()
            : null;

        $sleep_mode = function_exists('mpu_get_sleep_mode_settings')
            ? mpu_get_sleep_mode_settings($current_personality)
            : ['enabled' => false, 'frequency_multiplier' => 1.0];

        return $this->ok([
            'auto_talk'              => !empty($mpu_opt['auto_talk']),
            'auto_talk_interval'     => intval($mpu_opt['auto_talk_interval'] ?? 8),
            'typewriter_speed'       => intval($mpu_opt['typewriter_speed'] ?? 40),
            'ai_enabled'             => !empty($mpu_opt['ai_enabled']),
            'ai_probability'         => intval($mpu_opt['ai_probability'] ?? 10),
            'ai_trigger_pages'       => sanitize_text_field($mpu_opt['ai_trigger_pages'] ?? 'is_single'),
            'ai_text_color'          => sanitize_hex_color($mpu_opt['ai_text_color'] ?? '#000000'),
            'ai_display_duration'    => intval($mpu_opt['ai_display_duration'] ?? 8),
            'ai_greet_first_visit'   => !empty($mpu_opt['ai_greet_first_visit']),
            'ollama_replace_dialogue' => mpu_is_llm_replace_dialogue_enabled(),
            'enable_chat_mode'       => !empty($mpu_opt['enable_chat_mode']),
            'sleep_mode'             => $sleep_mode,
        ]);
    }

    // =========================================================================
    // POST /change — Rate limit: 10 次 / 60 秒
    // 注意：回應包含 Set-Cookie header（切換角色需寫入 cookie）
    // =========================================================================

    public function change(WP_REST_Request $request) {
        $rl = $this->rate_limit('change', 10, 60);
        if ($rl !== null) return $rl;

        $mpu_opt = mpu_get_option();

        $mpu_num = $request->get_param('mpu_num');
        if (!isset($mpu_num)) {
            $items = [];
            if (!empty($mpu_opt['ukagakas'])) {
                foreach ($mpu_opt['ukagakas'] as $key => $value) {
                    if (!empty($value['show'])) {
                        $items[] = [
                            'key'  => $key,
                            'name' => sanitize_text_field($value['name']),
                        ];
                    }
                }
            }
            return $this->ok([
                'heading'       => __('偽春菜們', 'mp-ukagaka'),
                'items'         => $items,
                'empty_message' => empty($items) ? __('沒有可供選擇的偽春菜', 'mp-ukagaka') : null,
            ]);
        }

        $mpu_num = sanitize_text_field($mpu_num);
        if (empty($mpu_opt['ukagakas'][$mpu_num])) {
            $mpu_num = 'default_1';
        }

        $ext             = $mpu_opt['external_file_format'] ?? 'txt';
        $dialog_filename = $mpu_opt['ukagakas'][$mpu_num]['dialog_filename'] ?? $mpu_num;

        $temp = [
            'msglist' => [
                'msgall'   => 0,
                'auto_msg' => $mpu_opt['auto_msg'] ?? '',
                'msg'      => [],
            ],
            'shell'           => $mpu_opt['ukagakas'][$mpu_num]['shell'] ?? '',
            'shell_info'      => mpu_get_shell_info($mpu_num),
            'msg'             => '',
            'name'            => $mpu_opt['ukagakas'][$mpu_num]['name'] ?? '',
            'num'             => $mpu_num,
            'dialog_filename' => $dialog_filename,
            'data_file'       => 'dialogs/' . $dialog_filename . '.' . $ext,
        ];

        $cookie_path   = defined('SITECOOKIEPATH') ? SITECOOKIEPATH : '/';
        $cookie_domain = defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : '';

        $cookie_name  = 'mpu_ukagaka_' . COOKIEHASH;
        $cookie_value = rawurlencode($mpu_num);
        $expires      = gmdate('D, d M Y H:i:s \G\M\T', time() + DAY_IN_SECONDS);
        $cookie_str   = "{$cookie_name}={$cookie_value}; Path={$cookie_path}; Expires={$expires}; HttpOnly; SameSite=Lax";
        if (is_ssl()) {
            $cookie_str .= '; Secure';
        }
        if (!empty($cookie_domain)) {
            $cookie_str .= '; Domain=' . $cookie_domain;
        }

        $response = new WP_REST_Response($temp, 200);
        $response->header('Set-Cookie', $cookie_str);
        return $response;
    }

    // =========================================================================
    // GET,POST /extend — Rate limit: 10 次 / 60 秒
    // =========================================================================

    public function extend(WP_REST_Request $request) {
        $rl = $this->rate_limit('extend', 10, 60);
        if ($rl !== null) return $rl;

        return $this->ok(['label' => __('更換偽春菜', 'mp-ukagaka')]);
    }

    // =========================================================================
    // GET,POST /shell-info — Rate limit: 30 次 / 60 秒
    // =========================================================================

    public function get_shell_info(WP_REST_Request $request) {
        $rl = $this->rate_limit('get_shell_info', 30, 60);
        if ($rl !== null) return $rl;

        $mpu_opt     = mpu_get_option();
        $ukagaka_num = sanitize_text_field($request->get_param('ukagaka_num') ?: '');
        if (empty($ukagaka_num)) {
            $ukagaka_num = $mpu_opt['cur_ukagaka'] ?? 'default_1';
        }
        if (empty($mpu_opt['ukagakas'][$ukagaka_num])) {
            $ukagaka_num = 'default_1';
        }

        $shell_info   = mpu_get_shell_info($ukagaka_num);
        $ukagaka_name = $mpu_opt['ukagakas'][$ukagaka_num]['name'] ?? '';

        return $this->ok([
            'success'      => true,
            'shell_info'   => $shell_info,
            'ukagaka_name' => $ukagaka_name,
            'ukagaka_num'  => $ukagaka_num,
        ]);
    }

    // =========================================================================
    // GET,POST /decoration-config — Rate limit: 30 次 / 60 秒
    // =========================================================================

    public function get_decoration_config(WP_REST_Request $request) {
        $rl = $this->rate_limit('get_decoration_config', 30, 60);
        if ($rl !== null) return $rl;

        $personality_id = function_exists('mpu_get_current_personality_id')
            ? mpu_get_current_personality_id()
            : 'Frieren';

        $decoration_config = [];
        if (function_exists('mpu_load_personality_decorations')) {
            $decorations_data = mpu_load_personality_decorations($personality_id);
            if (!empty($decorations_data['items'])) {
                $decoration_config = $decorations_data['items'];
            }
        }

        $main_file            = defined('MPU_MAIN_FILE') ? MPU_MAIN_FILE : dirname(dirname(dirname(__FILE__))) . '/mp-ukagaka.php';
        $decorations_base_url = esc_url(plugins_url("ghost/{$personality_id}/decorations/", $main_file));

        $touchzones_config = [];
        if (function_exists('mpu_load_personality_touchzones')) {
            $touchzones_config = mpu_load_personality_touchzones($personality_id);
        }

        $mpu_opt          = mpu_get_option();
        $show_decorations = isset($mpu_opt['ukagakas']['default_1']['show_decorations'])
            ? $mpu_opt['ukagakas']['default_1']['show_decorations']
            : true;

        return $this->ok([
            'success'              => true,
            'decorations_base_url' => $decorations_base_url,
            'decoration_config'    => $decoration_config,
            'touchzones'           => $touchzones_config,
            'show_decorations'     => $show_decorations,
        ]);
    }

    // =========================================================================
    // GET,POST /emoji-config — Rate limit: 30 次 / 60 秒
    // =========================================================================

    public function get_emoji_config(WP_REST_Request $request) {
        $rl = $this->rate_limit('get_emoji_config', 30, 60);
        if ($rl !== null) return $rl;

        $personality_id = function_exists('mpu_get_current_personality_id')
            ? mpu_get_current_personality_id()
            : 'Frieren';

        $emoji_base_url = function_exists('mpu_get_personality_emoji_url')
            ? mpu_get_personality_emoji_url($personality_id)
            : '';
        $emoji_config = function_exists('mpu_load_personality_emoji_config')
            ? mpu_load_personality_emoji_config($personality_id)
            : [];
        $emoji_mappings = function_exists('mpu_load_personality_emoji_keywords')
            ? mpu_load_personality_emoji_keywords($personality_id)
            : [];

        return $this->ok([
            'success'         => true,
            'baseUrl'         => $emoji_base_url,
            'supportedEmojis' => $emoji_config['supported'] ?? [],
            'mappings'        => $emoji_mappings,
        ]);
    }
}
