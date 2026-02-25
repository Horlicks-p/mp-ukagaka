<?php

/**
 * REST API Base Controller
 *
 * 所有 OO REST Controller 的抽象基礎類別。
 * 提供通用的 Rate Limiting、權限驗證、回傳格式輔助方法。
 *
 * @package MP_Ukagaka
 * @subpackage REST
 */

if (!defined('ABSPATH')) {
    exit();
}

abstract class MPU_REST_Base {

    /**
     * REST API namespace
     *
     * @var string
     */
    protected $namespace = 'mp-ukagaka/v1';

    /**
     * 子類別必須實作：向 WordPress 註冊該 Controller 的所有路由。
     * 此方法應掛載到 rest_api_init action。
     */
    abstract public function register_routes();

    /**
     * Rate Limiting 包裝。
     *
     * 直接委派給全域 mpu_rest_check_rate_limit()。
     * 超限時回傳 WP_REST_Response（429），允許時回傳 null。
     *
     * @param string $key    速率限制識別鍵（建議使用端點語意命名，如 'test_ollama_connection'）
     * @param int    $max    時間窗口內允許的最大請求次數
     * @param int    $window 時間窗口（秒）
     * @return WP_REST_Response|null  超限回傳 429 Response，允許回傳 null
     */
    protected function rate_limit($key, $max, $window) {
        if (function_exists('mpu_rest_check_rate_limit')) {
            return mpu_rest_check_rate_limit($key, $max, $window);
        }
        return null;
    }

    /**
     * Admin 權限驗證。
     *
     * 相容於舊 mpu_rest_admin_permission_check() 的行為：
     * - 未登入 → WP_Error('rest_not_logged_in', 401)
     * - 無 manage_options 能力 → WP_Error('rest_forbidden', 403)
     * - 通過 → true
     *
     * 供子類別在 permission_callback 中使用：
     *   'permission_callback' => [$this, 'check_admin']
     *
     * @param WP_REST_Request $request
     * @return true|WP_Error
     */
    public function check_admin(WP_REST_Request $request) {
        if (!is_user_logged_in()) {
            return new WP_Error(
                'rest_not_logged_in',
                __('請先登入', 'mp-ukagaka'),
                ['status' => 401]
            );
        }
        if (!current_user_can('manage_options')) {
            return new WP_Error(
                'rest_forbidden',
                __('權限不足', 'mp-ukagaka'),
                ['status' => 403]
            );
        }
        return true;
    }

    /**
     * 成功回傳包裝。
     *
     * @param mixed $data   回傳資料（陣列或純量）
     * @param int   $status HTTP 狀態碼，預設 200
     * @return WP_REST_Response
     */
    protected function ok($data, $status = 200) {
        return new WP_REST_Response($data, $status);
    }

    /**
     * 錯誤回傳包裝。
     *
     * @param string $code    WP_Error code（對前端可見，保持語意穩定）
     * @param string $message 人類可讀的錯誤訊息
     * @param int    $status  HTTP 狀態碼
     * @return WP_Error
     */
    protected function fail($code, $message, $status) {
        return new WP_Error($code, $message, ['status' => $status]);
    }
}
