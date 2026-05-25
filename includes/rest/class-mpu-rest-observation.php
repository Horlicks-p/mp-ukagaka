<?php

/**
 * REST Controller: Observation Buffer.
 *
 * @package MP_Ukagaka
 * @subpackage REST
 */

if (!defined('ABSPATH')) {
    exit();
}

class MPU_REST_Observation extends MPU_REST_Base {

    public function register_routes() {
        register_rest_route($this->namespace, '/observation/push', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'push'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function push(WP_REST_Request $request) {
        $auth = $this->require_valid_session_token($request);
        if ($auth !== null) {
            return $auth;
        }

        $rl = $this->rate_limit('observation_push', 20, 60);
        if ($rl !== null) {
            return $rl;
        }

        $type = sanitize_key((string) $request->get_param('type'));
        if (!in_array($type, ['page_view', 'stay_duration'], true)) {
            return $this->fail('invalid_type', __('この type は許可されていません。', 'mp-ukagaka'), 400);
        }

        $content = $request->get_param('content');
        if (!is_string($content) && !is_numeric($content)) {
            return $this->fail('invalid_content', __('不正な observation content です。', 'mp-ukagaka'), 400);
        }

        $token = $this->request_session_token($request);
        $ok = class_exists('MPU_Observation_Buffer')
            ? MPU_Observation_Buffer::push($type, (string) $content, $token)
            : false;

        return $this->ok(['ok' => $ok]);
    }

    private function require_valid_session_token(WP_REST_Request $request) {
        $token = $this->request_session_token($request);
        if ($token !== '' && function_exists('mpu_validate_session_token') && mpu_validate_session_token($token)) {
            return null;
        }

        return $this->fail(
            'missing_session_token',
            __('有効なセッショントークンが必要です。', 'mp-ukagaka'),
            403
        );
    }

    private function request_session_token(WP_REST_Request $request): string {
        return trim((string) ($request->get_header('X-MPU-Session-Token') ?: $request->get_param('session_token')));
    }
}
