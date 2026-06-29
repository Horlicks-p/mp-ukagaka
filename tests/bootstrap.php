<?php
/**
 * Minimal WordPress test bootstrap for low-dependency unit tests.
 */

define('MPU_TESTS_ROOT', dirname(__DIR__));

if (!defined('ABSPATH')) {
    define('ABSPATH', MPU_TESTS_ROOT . '/');
}
if (!defined('AUTH_KEY')) {
    define('AUTH_KEY', 'mpu-test-auth-key');
}
if (!defined('HOUR_IN_SECONDS')) {
    define('HOUR_IN_SECONDS', 3600);
}
if (!defined('MINUTE_IN_SECONDS')) {
    define('MINUTE_IN_SECONDS', 60);
}
if (!defined('DAY_IN_SECONDS')) {
    define('DAY_IN_SECONDS', 86400);
}

$autoload = MPU_TESTS_ROOT . '/tools/php/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

$GLOBALS['_mpu_test_filters'] = [];
$GLOBALS['_mpu_test_actions'] = [];
$GLOBALS['_mpu_test_action_log'] = [];
$GLOBALS['_mpu_test_transients'] = [];
$GLOBALS['_mpu_test_transient_expirations'] = [];
$GLOBALS['_mpu_test_options'] = [];
$GLOBALS['_mpu_test_current_user_can'] = false;
$GLOBALS['_mpu_test_is_user_logged_in'] = false;
$GLOBALS['_mpu_test_current_user_id'] = 0;

if (!class_exists('WP_Error')) {
    class WP_Error {
        private $code;
        private $message;
        private $data;

        public function __construct($code = '', $message = '', $data = null) {
            $this->code = $code;
            $this->message = $message;
            $this->data = $data;
        }

        public function get_error_code() {
            return $this->code;
        }

        public function get_error_message() {
            return $this->message;
        }

        public function get_error_data() {
            return $this->data;
        }
    }
}

function __($text, $domain = 'default') {
    return $text;
}

function apply_filters($hook_name, $value, ...$args) {
    if (empty($GLOBALS['_mpu_test_filters'][$hook_name])) {
        return $value;
    }

    usort($GLOBALS['_mpu_test_filters'][$hook_name], static function ($a, $b) {
        return ($a['priority'] ?? 10) <=> ($b['priority'] ?? 10);
    });

    foreach ($GLOBALS['_mpu_test_filters'][$hook_name] as $filter) {
        $accepted_args = max(1, (int) ($filter['accepted_args'] ?? 1));
        $callback_args = array_slice(array_merge([$value], $args), 0, $accepted_args);
        $value = call_user_func_array($filter['callback'], $callback_args);
    }

    return $value;
}

function do_action($hook_name, ...$args) {
    $GLOBALS['_mpu_test_action_log'][] = [$hook_name, $args];

    if (empty($GLOBALS['_mpu_test_actions'][$hook_name])) {
        return;
    }

    usort($GLOBALS['_mpu_test_actions'][$hook_name], static function ($a, $b) {
        return ($a['priority'] ?? 10) <=> ($b['priority'] ?? 10);
    });

    foreach ($GLOBALS['_mpu_test_actions'][$hook_name] as $action) {
        $accepted_args = (int) ($action['accepted_args'] ?? 1);
        call_user_func_array($action['callback'], array_slice($args, 0, $accepted_args));
    }
}

function add_filter($hook_name, $callback, $priority = 10, $accepted_args = 1) {
    $GLOBALS['_mpu_test_filters'][$hook_name][] = compact('callback', 'priority', 'accepted_args');
    return true;
}

function add_action($hook_name, $callback, $priority = 10, $accepted_args = 1) {
    $GLOBALS['_mpu_test_actions'][$hook_name][] = compact('callback', 'priority', 'accepted_args');
    return true;
}

function sanitize_key($key) {
    $key = strtolower((string) $key);
    return preg_replace('/[^a-z0-9_\-]/', '', $key);
}

function sanitize_text_field($value) {
    return trim(strip_tags((string) $value));
}

function sanitize_textarea_field($value) {
    return trim(strip_tags((string) $value));
}

function wp_strip_all_tags($value) {
    return strip_tags((string) $value);
}

function wp_unslash($value) {
    return is_string($value) ? stripslashes($value) : $value;
}

function wp_json_encode($data, $options = 0, $depth = 512) {
    return json_encode($data, $options, $depth);
}

function wp_normalize_path($path) {
    return str_replace('\\', '/', (string) $path);
}

function wp_mkdir_p($target) {
    return is_dir($target) || mkdir($target, 0777, true);
}

function get_site_url() {
    return 'https://example.test';
}

function current_user_can($capability) {
    return (bool) $GLOBALS['_mpu_test_current_user_can'];
}

function is_user_logged_in() {
    return (bool) $GLOBALS['_mpu_test_is_user_logged_in'];
}

function get_current_user_id() {
    return (int) $GLOBALS['_mpu_test_current_user_id'];
}

function wp_generate_uuid4() {
    return '00000000-0000-4000-8000-000000000000';
}

function wp_salt($scheme = 'auth') {
    return 'mpu-test-salt-' . $scheme;
}

function gmdate_test_safe($format) {
    return gmdate($format);
}

function get_transient($key) {
    return array_key_exists($key, $GLOBALS['_mpu_test_transients'])
        ? $GLOBALS['_mpu_test_transients'][$key]
        : false;
}

function get_option($key, $default = false) {
    return array_key_exists($key, $GLOBALS['_mpu_test_options'])
        ? $GLOBALS['_mpu_test_options'][$key]
        : $default;
}

function add_option($key, $value = '', $deprecated = '', $autoload = 'yes') {
    if (array_key_exists($key, $GLOBALS['_mpu_test_options'])) {
        return false;
    }
    $GLOBALS['_mpu_test_options'][$key] = $value;
    return true;
}

function update_option($key, $value, $autoload = null) {
    $GLOBALS['_mpu_test_options'][$key] = $value;
    return true;
}

function delete_option($key) {
    if (!array_key_exists($key, $GLOBALS['_mpu_test_options'])) {
        return false;
    }
    unset($GLOBALS['_mpu_test_options'][$key]);
    return true;
}

function set_transient($key, $value, $expiration = 0) {
    $GLOBALS['_mpu_test_transients'][$key] = $value;
    $GLOBALS['_mpu_test_transient_expirations'][$key] = $expiration;
    return true;
}

function delete_transient($key) {
    unset($GLOBALS['_mpu_test_transients'][$key]);
    unset($GLOBALS['_mpu_test_transient_expirations'][$key]);
    return true;
}

function get_post($post_id) {
    return $GLOBALS['_mpu_test_posts'][(int) $post_id] ?? null;
}

function get_the_title($post = 0) {
    if (is_object($post) && isset($post->post_title)) {
        return $post->post_title;
    }
    $post_id = (int) $post;
    return isset($GLOBALS['_mpu_test_posts'][$post_id])
        ? $GLOBALS['_mpu_test_posts'][$post_id]->post_title
        : '';
}

function current_time($format) {
    return gmdate($format);
}

function mpu_log_error($message) {
    $GLOBALS['_mpu_test_last_error_log'] = $message;
}

require_once MPU_TESTS_ROOT . '/includes/llm/chat-integrity.php';
// Shared history normalizer：dialog/chat/akismet の checksum store が依存する。
// chat-integrity.php（mpu_chat_integrity_* 関数）の後に読み込むこと。
require_once MPU_TESTS_ROOT . '/includes/chat/class-mpu-chat-history-service.php';
require_once MPU_TESTS_ROOT . '/includes/llm/class-mpu-chat-lock.php';
require_once MPU_TESTS_ROOT . '/includes/core/class-mpu-input-role.php';
require_once MPU_TESTS_ROOT . '/includes/llm/class-mpu-session-event.php';
require_once MPU_TESTS_ROOT . '/includes/core/utility-functions.php';
require_once MPU_TESTS_ROOT . '/includes/core/template-functions.php';
require_once MPU_TESTS_ROOT . '/includes/core/file-functions.php';
require_once MPU_TESTS_ROOT . '/includes/core/encryption-functions.php';
require_once MPU_TESTS_ROOT . '/includes/core/wp-info-functions.php';
require_once MPU_TESTS_ROOT . '/includes/core/network-functions.php';
require_once MPU_TESTS_ROOT . '/includes/core/runtime-state-functions.php';
require_once MPU_TESTS_ROOT . '/includes/core/class-mpu-observation-buffer.php';
require_once MPU_TESTS_ROOT . '/includes/llm/provider-helpers.php';
