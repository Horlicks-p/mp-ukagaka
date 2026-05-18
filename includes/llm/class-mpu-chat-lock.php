<?php
/**
 * Atomic chat lifecycle lock.
 *
 * Uses add_option() as an atomic check-and-set primitive. Transients are not
 * used because get_transient() + set_transient() is not atomic under parallel
 * requests.
 */

if (!defined('ABSPATH')) {
    exit();
}

class MPU_Chat_Lock {
    const OPTION_PREFIX = 'mpu_chat_lock_';
    const DEFAULT_TTL = 60;

    public static function acquire($session_id, array $context = []) {
        $session_id = self::normalize_session_id($session_id);
        if ($session_id === '') {
            return null;
        }

        $key = self::option_key($session_id);
        $now = time();
        $ttl = self::ttl();
        $token = self::generate_token();
        $payload = [
            'token'       => $token,
            'session_id'  => $session_id,
            'acquired_at' => $now,
            'expires_at'  => $now + $ttl,
            'context'     => self::sanitize_context($context),
        ];

        if (add_option($key, $payload, '', 'no')) {
            do_action('mpu_chat_lock_acquired', $session_id, $payload);
            return $payload;
        }

        $existing = get_option($key, null);
        if (self::is_expired_payload($existing, $now)) {
            delete_option($key);
            if (add_option($key, $payload, '', 'no')) {
                do_action('mpu_chat_lock_acquired', $session_id, $payload);
                return $payload;
            }
        }

        do_action('mpu_chat_lock_conflict', $session_id, is_array($existing) ? $existing : null, $context);

        return new WP_Error(
            'mpu_chat_lock_busy',
            __('前の返答がまだ処理中です。少し待ってからもう一度試してください。', 'mp-ukagaka'),
            ['status' => 429]
        );
    }

    public static function release($session_id, $token) {
        $session_id = self::normalize_session_id($session_id);
        $token = is_scalar($token) ? (string) $token : '';
        if ($session_id === '' || $token === '') {
            return false;
        }

        $key = self::option_key($session_id);
        $existing = get_option($key, null);
        if (!is_array($existing) || !isset($existing['token']) || !hash_equals((string) $existing['token'], $token)) {
            return false;
        }

        $deleted = delete_option($key);
        if ($deleted) {
            do_action('mpu_chat_lock_released', $session_id, $existing);
        }

        return (bool) $deleted;
    }

    public static function is_locked($session_id) {
        $session_id = self::normalize_session_id($session_id);
        if ($session_id === '') {
            return false;
        }

        $key = self::option_key($session_id);
        $existing = get_option($key, null);
        if (!is_array($existing)) {
            return false;
        }

        if (self::is_expired_payload($existing, time())) {
            delete_option($key);
            return false;
        }

        return true;
    }

    public static function option_key($session_id) {
        return self::OPTION_PREFIX . self::normalize_session_id($session_id);
    }

    public static function ttl() {
        $ttl = (int) apply_filters('mpu_chat_lock_ttl', self::DEFAULT_TTL);
        return max(10, min(300, $ttl));
    }

    private static function normalize_session_id($session_id) {
        if (function_exists('mpu_chat_integrity_normalize_session_id')) {
            return mpu_chat_integrity_normalize_session_id($session_id);
        }

        if (!is_scalar($session_id)) {
            return '';
        }

        $normalized = sanitize_key((string) $session_id);
        return substr($normalized, 0, 64);
    }

    private static function generate_token() {
        if (function_exists('wp_generate_uuid4')) {
            return wp_generate_uuid4();
        }

        return bin2hex(random_bytes(16));
    }

    private static function sanitize_context(array $context) {
        $clean = [];
        foreach ($context as $key => $value) {
            if (!is_scalar($value)) {
                continue;
            }
            $clean[sanitize_key((string) $key)] = sanitize_text_field((string) $value);
        }
        return $clean;
    }

    private static function is_expired_payload($payload, $now) {
        if (!is_array($payload) || !isset($payload['expires_at'])) {
            return true;
        }

        return (int) $payload['expires_at'] <= (int) $now;
    }
}
