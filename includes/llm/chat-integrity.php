<?php
/**
 * Chat history integrity checksum helpers.
 */

if (!defined('ABSPATH')) {
    exit();
}

function mpu_chat_integrity_debug_log($message)
{
    $wp_debug_enabled     = defined('WP_DEBUG') && WP_DEBUG;
    $wp_debug_log_enabled = defined('WP_DEBUG_LOG') && WP_DEBUG_LOG;
    if (!$wp_debug_enabled && !$wp_debug_log_enabled) {
        return;
    }
    $line = '[MPU Chat Integrity] ' . (string) $message;
    if (function_exists('mpu_debug_log')) {
        mpu_debug_log($line);
        return;
    }
    error_log($line);
}

function mpu_chat_integrity_normalize_session_id($session_id)
{
    if (!is_scalar($session_id)) return '';
    $normalized = sanitize_key((string) $session_id);
    if ($normalized === '') return '';
    return substr($normalized, 0, 64);
}

function mpu_chat_integrity_transient_key($session_id)
{
    return 'mpu_chat_checksum_' . $session_id;
}

function mpu_chat_integrity_filter_messages(array $messages)
{
    $filtered = [];
    foreach ($messages as $message) {
        if (!is_array($message)) continue;
        $role = isset($message['role']) ? (string) $message['role'] : '';
        if ($role === '' || $role === 'user') continue;
        if (!isset($message['content'])) continue;
        $content = $message['content'];
        if (is_scalar($content)) {
            $content = (string) $content;
        } else {
            $content = wp_json_encode($content);
        }
        $filtered[] = [
            'role'    => $role,
            'content' => (string) $content,
        ];
    }
    return $filtered;
}

function mpu_chat_integrity_compute_checksum(array $messages)
{
    $filtered = mpu_chat_integrity_filter_messages($messages);
    if (function_exists('mpu_json_encode_safe')) {
        $json = mpu_json_encode_safe($filtered);
    } else {
        $json = wp_json_encode($filtered);
    }
    if (!is_string($json)) $json = '[]';
    return md5($json);
}

function mpu_chat_integrity_verify_history($session_id, array $history)
{
    $session_id = mpu_chat_integrity_normalize_session_id($session_id);
    if ($session_id === '') return null;
    $key      = mpu_chat_integrity_transient_key($session_id);
    $expected = get_transient($key);
    if ($expected === false || !is_string($expected) || $expected === '') return null;
    $actual = mpu_chat_integrity_compute_checksum($history);
    if (!hash_equals($expected, $actual)) {
        mpu_chat_integrity_debug_log(sprintf(
            'Checksum mismatch. session_id=%s expected=%s actual=%s history_count=%d',
            $session_id, $expected, $actual, count($history)
        ));
        return new WP_Error('chat_history_checksum_mismatch', __('對話歷史驗證失敗，請重新整理頁面後再試一次。', 'mp-ukagaka'), [
            'status'     => 400,
            'session_id' => $session_id,
        ]);
    }
    return true;
}

function mpu_chat_integrity_store_history($session_id, array $history)
{
    $session_id = mpu_chat_integrity_normalize_session_id($session_id);
    if ($session_id === '') return false;
    $checksum = mpu_chat_integrity_compute_checksum($history);
    return set_transient(mpu_chat_integrity_transient_key($session_id), $checksum, HOUR_IN_SECONDS);
}

function mpu_chat_integrity_slice_for_store(array $history, $limit = 10)
{
    $sliced = array_slice($history, -$limit);
    $normalized = [];
    $prev_role  = '';
    foreach ($sliced as $msg) {
        $role = isset($msg['role']) ? (string)$msg['role'] : '';
        if ($role === 'assistant' && $prev_role !== 'user') continue;
        $normalized[] = $msg;
        $prev_role    = $role;
    }
    return $normalized;
}
