<?php

/**
 * Minimal harness for calling MPU_REST_Chat::prepare_user_chat_args() under PHPUnit.
 *
 * Load only from a test running in a separate process: it pulls in the real
 * core-functions.php and personality modules, which would redeclare functions
 * that other test files stub at file scope.
 *
 * Everything the chat contract depends on (history service, chat lock, integrity,
 * input role, observation buffer, personality and prompt resolution) is the real
 * production code. Only the WordPress runtime surface below is stubbed.
 */

if (!class_exists('WP_REST_Request')) {
    class WP_REST_Request {
        private $params;
        private $headers;
        private $route;

        public function __construct(array $params = [], array $headers = [], string $route = '/mp-ukagaka/v1/chat/user') {
            $this->params  = $params;
            $this->headers = array_change_key_case($headers, CASE_LOWER);
            $this->route   = $route;
        }

        public function get_param($key) {
            return $this->params[$key] ?? null;
        }

        public function get_header($key) {
            return $this->headers[strtolower((string) $key)] ?? null;
        }

        public function get_route() {
            return $this->route;
        }
    }
}

if (!class_exists('WP_REST_Response')) {
    class WP_REST_Response {
        public $data;
        public $status;
        public $headers = [];

        public function __construct($data = null, $status = 200) {
            $this->data   = $data;
            $this->status = $status;
        }

        public function get_data() {
            return $this->data;
        }

        public function get_status() {
            return $this->status;
        }

        public function header($key, $value) {
            $this->headers[$key] = $value;
        }
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($value) {
        return $value instanceof WP_Error;
    }
}

if (!function_exists('plugins_url')) {
    function plugins_url($path = '', $plugin = '') {
        return 'https://example.test/wp-content/plugins/mp-ukagaka/' . ltrim((string) $path, '/');
    }
}

if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file) {
        return rtrim(dirname((string) $file), '/\\') . DIRECTORY_SEPARATOR;
    }
}

if (!function_exists('wp_date')) {
    function wp_date($format, $timestamp = null, $timezone = null) {
        return gmdate($format, $timestamp ?? time());
    }
}

if (!function_exists('wp_timezone_string')) {
    function wp_timezone_string() {
        return 'UTC';
    }
}

if (!function_exists('wp_timezone')) {
    function wp_timezone() {
        return new DateTimeZone('UTC');
    }
}

if (!function_exists('wp_rand')) {
    function wp_rand($min = 0, $max = 0) {
        return random_int((int) $min, max((int) $min, (int) $max));
    }
}

if (!function_exists('wp_get_current_user')) {
    function wp_get_current_user() {
        return (object) [
            'ID'           => (int) $GLOBALS['_mpu_test_current_user_id'],
            'user_login'   => 'tester',
            'display_name' => 'Tester',
            'user_email'   => 'tester@example.test',
            'roles'        => ['subscriber'],
        ];
    }
}

// debug-functions.php is deliberately not loaded: its mpu_log_error() collides with the
// bootstrap stub. The only effect is that the guarded mpu_debug_log() calls are skipped.
require_once MPU_TESTS_ROOT . '/includes/core/core-functions.php';
require_once MPU_TESTS_ROOT . '/includes/llm/request-state.php';
require_once MPU_TESTS_ROOT . '/includes/personality/personality-loader.php';
require_once MPU_TESTS_ROOT . '/includes/personality/personality-prompts.php';
require_once MPU_TESTS_ROOT . '/includes/llm/llm-context-builder.php';
require_once MPU_TESTS_ROOT . '/includes/rest/class-mpu-rest-base.php';
require_once MPU_TESTS_ROOT . '/includes/rest/class-mpu-rest-chat.php';
require_once __DIR__ . '/IntegrityLogGuard.php';

/**
 * Exposes the protected method under test without changing production visibility.
 */
final class MPU_Test_REST_Chat extends MPU_REST_Chat {
    public function prepare(WP_REST_Request $request) {
        return $this->prepare_user_chat_args($request);
    }
}
