<?php

/**
 * Transient-backed ghost runtime state helpers.
 *
 * Reserved backend foundation for future runtime UI/observation readers. The
 * current production path writes short-lived state during REST chat/dialog
 * flows, but does not expose a front-end read endpoint yet.
 *
 * @package MP_Ukagaka
 * @subpackage Runtime_State
 */

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Return the whitelist of supported ghost runtime states.
 *
 * @return string[]
 */
function mpu_runtime_state_allowed_states(): array
{
    return [
        'idle',
        'thinking',
        'speaking',
        'chatting',
        'sleeping',
        'waking',
        'tool_running',
        'suspended',
        'error',
    ];
}

/**
 * Resolve the transient key for the current runtime-state scope.
 *
 * Anonymous visitors must provide a valid front-end session token. Logged-in
 * users can fall back to their user id, which avoids PHP sessions and IP-based
 * visitor tracking.
 */
function mpu_runtime_state_scope_key(?string $session_token = null): ?string
{
    $session_token = trim((string) $session_token);

    if ($session_token !== '') {
        if (!function_exists('mpu_validate_session_token') || !mpu_validate_session_token($session_token)) {
            return null;
        }

        return 'mpu_runtime_state_' . hash('sha256', $session_token);
    }

    if (function_exists('is_user_logged_in') && is_user_logged_in() && function_exists('get_current_user_id')) {
        $user_id = (int) get_current_user_id();
        if ($user_id > 0) {
            return 'mpu_runtime_state_user_' . $user_id;
        }
    }

    return null;
}

/**
 * Runtime state transient TTL in seconds.
 */
function mpu_runtime_state_ttl(): int
{
    $ttl = (int) apply_filters('mpu_runtime_state_ttl', 5 * MINUTE_IN_SECONDS);
    return max(60, min(900, $ttl));
}

/**
 * Set the runtime state for a session/user scope.
 */
function mpu_set_runtime_state(string $state, ?string $session_token = null): bool
{
    if (!in_array($state, mpu_runtime_state_allowed_states(), true)) {
        return false;
    }

    $key = mpu_runtime_state_scope_key($session_token);
    if ($key === null) {
        return false;
    }

    return (bool) set_transient($key, [
        'state' => $state,
        'ts'    => time(),
    ], mpu_runtime_state_ttl());
}

/**
 * Get the runtime state for a session/user scope.
 *
 * @return array{state:string,ts:int}|null
 */
function mpu_get_runtime_state(?string $session_token = null): ?array
{
    $key = mpu_runtime_state_scope_key($session_token);
    if ($key === null) {
        return null;
    }

    $value = get_transient($key);
    if (!is_array($value) || !isset($value['state'], $value['ts'])) {
        return null;
    }

    if (!in_array($value['state'], mpu_runtime_state_allowed_states(), true)) {
        return null;
    }

    return [
        'state' => (string) $value['state'],
        'ts'    => (int) $value['ts'],
    ];
}

/**
 * Clear the runtime state for a session/user scope.
 */
function mpu_clear_runtime_state(?string $session_token = null): void
{
    $key = mpu_runtime_state_scope_key($session_token);
    if ($key !== null) {
        delete_transient($key);
    }
}
