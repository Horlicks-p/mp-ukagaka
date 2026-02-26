<?php

/**
 * REST Controller: Test Connections & Admin Tools
 *
 * 取代 rest-test.php。路由 URL、HTTP method、permission、args、
 * status code、WP_Error code 與舊 procedural 實作完全一致。
 *
 * 端點：
 *   POST /mp-ukagaka/v1/test-connection/{provider}
 *   POST /mp-ukagaka/v1/clear-cache
 *
 * @package MP_Ukagaka
 * @subpackage REST
 */

if (!defined('ABSPATH')) {
    exit();
}

class MPU_REST_Test extends MPU_REST_Base {

    /**
     * 向 WordPress 註冊所有 Test 端點。
     * 由 bootstrap.php 集中掛載到 rest_api_init。
     */
    public function register_routes() {
        register_rest_route($this->namespace, '/test-connection/(?P<provider>[\w-]+)', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'test_connection'],
            'permission_callback' => [$this, 'check_admin'],
            'args'                => [
                'provider' => [
                    'required' => true,
                    'type'     => 'string',
                ],
            ],
        ]);

        register_rest_route($this->namespace, '/clear-cache', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'clear_api_cache'],
            'permission_callback' => [$this, 'check_admin'],
        ]);
    }

    /**
     * POST /test-connection/{provider}
     * Rate limit: 10 次 / 60 秒（與舊實作相同）
     */
    public function test_connection(WP_REST_Request $request) {
        $provider_slug = $request->get_param('provider');

        $rl = $this->rate_limit('test_' . $provider_slug . '_connection', 10, 60);
        if ($rl !== null) return $rl;

        // Weather is a special case (not an AI provider)
        if ($provider_slug === 'weather') {
            return $this->test_weather($request);
        }

        $factory_result = MPU_AI_Provider_Factory::get_provider($provider_slug);
        if (is_wp_error($factory_result)) {
            return $this->fail(
                'rest_error',
                $factory_result->get_error_message(),
                400
            );
        }

        /** @var MPU_AI_Provider_Interface $provider */
        $provider = $factory_result;

        $args = [
            'api_key'  => $request->get_param('api_key'),
            'model'    => $request->get_param('model'),
            'endpoint' => $request->get_param('endpoint'),
        ];

        $result = $provider->test_connection($args);

        if (is_wp_error($result)) {
            return $this->fail(
                $result->get_error_code(),
                $result->get_error_message(),
                400,
                $result->get_error_data()
            );
        }

        return $result;
    }

    /**
     * POST /clear-cache
     * Rate limit: 10 次 / 60 秒（與舊實作相同）
     */
    public function clear_api_cache(WP_REST_Request $request) {
        $rl = $this->rate_limit('clear_api_cache', 10, 60);
        if ($rl !== null) return $rl;

        if (function_exists('mpu_clear_all_api_cache')) {
            $cleared_count = mpu_clear_all_api_cache();
            return $this->ok(['msg' => sprintf(__('已清除 %d 筆快取', 'mp-ukagaka'), $cleared_count)]);
        } else {
            return $this->fail('rest_error', __('快取清除功能不可用', 'mp-ukagaka'), 400);
        }
    }

    // -------------------------------------------------------------------------
    // 私有輔助：天氣測試邏輯（非 AI Provider）
    // -------------------------------------------------------------------------

    private function test_weather(WP_REST_Request $request) {
        $rl = $this->rate_limit('test_weather_api', 10, 60);
        if ($rl !== null) return $rl;

        $latitude_param  = $request->get_param('latitude');
        $longitude_param = $request->get_param('longitude');

        $latitude  = isset($latitude_param) && $latitude_param !== '' ? floatval($latitude_param) : 25.0330;
        $longitude = isset($longitude_param) && $longitude_param !== '' ? floatval($longitude_param) : 121.5654;

        if ($latitude < -90 || $latitude > 90) {
            return $this->fail('rest_error', __('緯度參數無效（須介於 -90 至 90 之間）', 'mp-ukagaka'), 400);
        }
        if ($longitude < -180 || $longitude > 180) {
            return $this->fail('rest_error', __('經度參數無效（須介於 -180 至 180 之間）', 'mp-ukagaka'), 400);
        }

        if (function_exists('mpu_clear_weather_cache')) {
            mpu_clear_weather_cache($latitude, $longitude);
        }

        if (!function_exists('mpu_get_weather_forecast')) {
            return $this->fail('rest_error', __('天氣功能模組不可用', 'mp-ukagaka'), 400);
        }

        $weather = mpu_get_weather_forecast($latitude, $longitude);
        if ($weather === null) {
            return $this->fail('rest_error', __('無法獲取天氣數據，請確認網絡連線及 Open-Meteo API 是否正常', 'mp-ukagaka'), 400);
        }

        $current_weather      = $weather['current']['weather_text'] ?? '不明';
        $current_temp         = $weather['current']['temperature'] ?? '-';
        $today_precip_prob    = $weather['today']['precipitation_probability'] ?? null;
        $tomorrow_weather     = $weather['tomorrow']['weather_text'] ?? '不明';
        $tomorrow_max         = $weather['tomorrow']['temp_max'] ?? '-';
        $tomorrow_min         = $weather['tomorrow']['temp_min'] ?? '-';
        $tomorrow_precip_prob = $weather['tomorrow']['precipitation_probability'] ?? null;

        $today_precip_text = '';
        if ($today_precip_prob !== null && $today_precip_prob > 0) {
            $today_code        = $weather['today']['weather_code'] ?? 0;
            $precip_type       = ($today_code >= 71 && $today_code <= 77) || ($today_code >= 85 && $today_code <= 86) ? '降雪' : '降水';
            $today_precip_text = sprintf(' %s%d%%', $precip_type, $today_precip_prob);
        }

        $tomorrow_precip_text = '';
        if ($tomorrow_precip_prob !== null && $tomorrow_precip_prob > 0) {
            $tomorrow_code        = $weather['tomorrow']['weather_code'] ?? 0;
            $precip_type          = ($tomorrow_code >= 71 && $tomorrow_code <= 77) || ($tomorrow_code >= 85 && $tomorrow_code <= 86) ? '降雪' : '降水';
            $tomorrow_precip_text = sprintf(' %s%d%%', $precip_type, $tomorrow_precip_prob);
        }

        $message = sprintf(
            __('連接成功！現在：%s %.0f°C%s / 明日：%s %.0f~%.0f°C%s', 'mp-ukagaka'),
            $current_weather,
            $current_temp,
            $today_precip_text,
            $tomorrow_weather,
            $tomorrow_min,
            $tomorrow_max,
            $tomorrow_precip_text
        );

        return $this->ok(['msg' => $message]);
    }
}
