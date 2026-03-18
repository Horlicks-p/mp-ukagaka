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

/**
 * 將 checksum mismatch 的詳細資訊寫入 mp-ukagaka/logs/checksum-mismatch.log。
 * 不影響外掛運作（non-blocking），僅供日後排查。
 *
 * @param string $session_id
 * @param string $expected   上一輪 store 寫入的 checksum
 * @param string $actual     本輪 verify 計算的 checksum
 * @param array  $verify_history 本輪 verify 端傳入的歷史
 */
function mpu_chat_integrity_dump_mismatch($session_id, $expected, $actual, array $verify_history, array $verify_meta = [])
{
    // __FILE__ = includes/llm/chat-integrity.php → 需要往上 2 層到外掛根目錄
    $log_dir = dirname(dirname(dirname(__FILE__))) . '/logs';
    if (!is_dir($log_dir)) {
        wp_mkdir_p($log_dir);
        // 加入 .htaccess 保護
        $htaccess = $log_dir . '/.htaccess';
        if (!file_exists($htaccess)) {
            @file_put_contents($htaccess, "Deny from all\n");
        }
    }

    $log_file = $log_dir . '/checksum-mismatch.log';

    // 取出上一輪 store 時保存的 JSON snapshot（若有）
    $snapshot_key    = mpu_chat_integrity_snapshot_key($session_id);
    $snapshot_meta_key = mpu_chat_integrity_snapshot_meta_key($session_id);
    $stored_snapshot = get_transient($snapshot_key);
    $stored_json     = ($stored_snapshot !== false && is_string($stored_snapshot)) ? $stored_snapshot : '(no snapshot)';
    $stored_snapshot_meta = get_transient($snapshot_meta_key);
    $stored_meta_text = '(no meta)';
    if (is_array($stored_snapshot_meta)) {
        $meta_source = isset($stored_snapshot_meta['source']) ? (string) $stored_snapshot_meta['source'] : '(unknown)';
        $meta_func   = isset($stored_snapshot_meta['function']) ? (string) $stored_snapshot_meta['function'] : '(unknown)';
        $meta_time   = isset($stored_snapshot_meta['stored_at']) ? (string) $stored_snapshot_meta['stored_at'] : '(unknown)';
        $stored_meta_text = sprintf('source=%s function=%s stored_at=%s', $meta_source, $meta_func, $meta_time);
    }

    // 計算本輪 verify 端的 filtered JSON
    $verify_filtered = mpu_chat_integrity_filter_messages($verify_history);
    $verify_json = mpu_chat_integrity_json_encode($verify_filtered, true);

    // 也輸出「先 normalize + slice」後的 verify 視角，方便對齊 store 窗口
    $verify_windowed = mpu_chat_integrity_slice_for_store($verify_history, 10);
    $verify_windowed_filtered = mpu_chat_integrity_filter_messages($verify_windowed);
    $verify_windowed_json = mpu_chat_integrity_json_encode($verify_windowed_filtered, true);

    $verify_raw_json = mpu_chat_integrity_json_encode($verify_history, false);
    $verify_raw_checksum = md5($verify_raw_json);
    $verify_windowed_checksum = mpu_chat_integrity_compute_checksum($verify_windowed);

    // 解析 store snapshot 供差異比對
    $stored_filtered = json_decode((string) $stored_json, true);
    if (!is_array($stored_filtered)) {
        $stored_filtered = [];
    }
    $stored_snapshot_checksum = mpu_chat_integrity_checksum_for_filtered($stored_filtered);
    $diff_index_raw = mpu_chat_integrity_first_diff_index($stored_filtered, $verify_filtered);
    $diff_index_windowed = mpu_chat_integrity_first_diff_index($stored_filtered, $verify_windowed_filtered);

    $verify_source = isset($verify_meta['source']) ? (string) $verify_meta['source'] : '(unknown)';
    $verify_function = isset($verify_meta['function']) ? (string) $verify_meta['function'] : '(unknown)';
    $verify_role_counts = mpu_chat_integrity_role_counts($verify_history);
    $verify_role_counts_text = mpu_chat_integrity_json_encode($verify_role_counts, false);

    $timestamp = current_time('Y-m-d H:i:s');
    $separator = str_repeat('=', 72);

    $entry  = "\n{$separator}\n";
    $entry .= "[{$timestamp}] CHECKSUM MISMATCH\n";
    $entry .= "session_id      : {$session_id}\n";
    $entry .= "expected (store): {$expected}\n";
    $entry .= "actual (verify) : {$actual}\n";
    $entry .= "verify source    : {$verify_source} ({$verify_function})\n";
    $entry .= "verify history count: " . count($verify_history) . "\n";
    $entry .= "verify role counts : {$verify_role_counts_text}\n";
    $entry .= "last store meta  : {$stored_meta_text}\n";
    $entry .= "store snapshot cs : {$stored_snapshot_checksum}\n";
    $entry .= "verify raw cs     : {$verify_raw_checksum}\n";
    $entry .= "verify window cs  : {$verify_windowed_checksum}\n";
    $entry .= "first diff (raw)  : {$diff_index_raw}\n";
    $entry .= "first diff (win)  : {$diff_index_windowed}\n";
    $entry .= "\n--- STORE 端 filtered JSON (上一輪寫入時) ---\n";
    $entry .= $stored_json . "\n";
    $entry .= "\n--- VERIFY 端 filtered JSON (本輪驗證時) ---\n";
    $entry .= $verify_json . "\n";
    $entry .= "\n--- VERIFY 端 filtered JSON (normalize+slice 後) ---\n";
    $entry .= $verify_windowed_json . "\n";

    // 附加原始 verify history 的 role 與 content 前 80 字元，方便快速比對
    $entry .= "\n--- VERIFY 端 raw history (role + content preview) ---\n";
    foreach ($verify_history as $i => $msg) {
        $role    = isset($msg['role']) ? $msg['role'] : '?';
        $content = isset($msg['content']) ? mb_substr((string)$msg['content'], 0, 80, 'UTF-8') : '(empty)';
        $content = str_replace(["\r", "\n"], ['\\r', '\\n'], $content);
        $entry  .= sprintf("  [%d] %-10s %s\n", $i, $role, $content);
    }

    $entry .= "{$separator}\n";

    // 限制 log 檔案大小：超過 512KB 時截斷保留後半
    $max_size = 512 * 1024;
    if (file_exists($log_file) && filesize($log_file) > $max_size) {
        $existing = @file_get_contents($log_file);
        if (is_string($existing)) {
            $existing = substr($existing, (int)(strlen($existing) / 2));
            // 從下一個 separator 開始，避免截斷在中間
            $cut_pos = strpos($existing, str_repeat('=', 72));
            if ($cut_pos !== false) {
                $existing = substr($existing, $cut_pos);
            }
            @file_put_contents($log_file, "[...truncated...]\n" . $existing);
        }
    }

    @file_put_contents($log_file, $entry, FILE_APPEND | LOCK_EX);
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

function mpu_chat_integrity_snapshot_key($session_id)
{
    return 'mpu_chat_cs_snapshot_' . $session_id;
}

function mpu_chat_integrity_snapshot_meta_key($session_id)
{
    return 'mpu_chat_cs_snapshot_meta_' . $session_id;
}

/**
 * 取得最後一次 checksum store 的呼叫來源，方便 mismatch 排查。
 *
 * @return array
 */
function mpu_chat_integrity_detect_store_source()
{
    $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 12);
    $self  = wp_normalize_path(__FILE__);

    foreach ($trace as $frame) {
        $file = isset($frame['file']) ? wp_normalize_path((string) $frame['file']) : '';
        if ($file === '' || $file === $self) {
            continue;
        }

        $func = isset($frame['function']) ? (string) $frame['function'] : '(unknown)';
        $line = isset($frame['line']) ? (int) $frame['line'] : 0;
        return [
            'source'   => basename($file) . ':' . $line,
            'function' => $func,
            'file'     => $file,
            'line'     => $line,
        ];
    }

    return [
        'source'   => '(unknown)',
        'function' => '(unknown)',
        'file'     => '',
        'line'     => 0,
    ];
}

/**
 * 取得 verify 觸發來源（REST/AJAX/其他路徑）。
 *
 * @return array
 */
function mpu_chat_integrity_detect_verify_source()
{
    $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 12);
    $self  = wp_normalize_path(__FILE__);

    foreach ($trace as $frame) {
        $file = isset($frame['file']) ? wp_normalize_path((string) $frame['file']) : '';
        if ($file === '' || $file === $self) {
            continue;
        }

        $func = isset($frame['function']) ? (string) $frame['function'] : '(unknown)';
        $line = isset($frame['line']) ? (int) $frame['line'] : 0;
        return [
            'source'   => basename($file) . ':' . $line,
            'function' => $func,
            'file'     => $file,
            'line'     => $line,
        ];
    }

    return [
        'source'   => '(unknown)',
        'function' => '(unknown)',
        'file'     => '',
        'line'     => 0,
    ];
}

function mpu_chat_integrity_json_encode($data, $pretty = false)
{
    if (function_exists('mpu_json_encode_safe')) {
        $json = mpu_json_encode_safe($data);
    } else {
        $flags = JSON_UNESCAPED_UNICODE;
        if ($pretty) {
            $flags |= JSON_PRETTY_PRINT;
        }
        $json = wp_json_encode($data, $flags);
    }
    return is_string($json) ? $json : '[]';
}

function mpu_chat_integrity_checksum_for_filtered(array $filtered_messages)
{
    $json = mpu_chat_integrity_json_encode($filtered_messages, false);
    return md5($json);
}

function mpu_chat_integrity_first_diff_index(array $a, array $b)
{
    $max = max(count($a), count($b));
    for ($i = 0; $i < $max; $i++) {
        $left  = array_key_exists($i, $a) ? $a[$i] : null;
        $right = array_key_exists($i, $b) ? $b[$i] : null;
        if ($left !== $right) {
            return (string) $i;
        }
    }
    return 'none';
}

function mpu_chat_integrity_role_counts(array $messages)
{
    $counts = [];
    foreach ($messages as $msg) {
        if (!is_array($msg)) {
            $role = '__non_array__';
        } else {
            $role = isset($msg['role']) ? (string) $msg['role'] : '__missing_role__';
        }
        if (!isset($counts[$role])) {
            $counts[$role] = 0;
        }
        $counts[$role]++;
    }
    ksort($counts);
    return $counts;
}

function mpu_chat_integrity_filter_messages(array $messages)
{
    $filtered = [];
    foreach ($messages as $message) {
        if (!is_array($message)) continue;
        $role = isset($message['role']) ? (string) $message['role'] : '';
        // 跳過所有 user（含 synthetic 錨點）與空 role
        if ($role === '' || $role === 'user') continue;
        // 只計入真實對話輪次（type="chat"）的 assistant，合成/auto-talk/touch 等不納入 checksum
        $type = isset($message['type']) ? (string) $message['type'] : 'chat';
        if ($type !== 'chat') continue;
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
        $verify_meta = mpu_chat_integrity_detect_verify_source();
        // [Warn-only] Checksum mismatch：只記錄 log，不中斷請求
        mpu_chat_integrity_debug_log(sprintf(
            '[WARN] Checksum mismatch (non-blocking). session_id=%s expected=%s actual=%s history_count=%d source=%s',
            $session_id, $expected, $actual, count($history), $verify_meta['source']
        ));

        // [Diagnostic] 寫入詳細 mismatch dump 到 mp-ukagaka/logs/checksum-mismatch.log
        mpu_chat_integrity_dump_mismatch($session_id, $expected, $actual, $history, $verify_meta);

        return null; // null = 跳過驗證，請求繼續
    }

    return true;
}

function mpu_chat_integrity_store_history($session_id, array $history)
{
    $session_id = mpu_chat_integrity_normalize_session_id($session_id);
    if ($session_id === '') return false;
    $checksum = mpu_chat_integrity_compute_checksum($history);

    // [Diagnostic] 保存 store 端的 filtered JSON snapshot，供下次 mismatch 時 dump 比對
    $filtered = mpu_chat_integrity_filter_messages($history);
    if (function_exists('mpu_json_encode_safe')) {
        $json = mpu_json_encode_safe($filtered);
    } else {
        $json = wp_json_encode($filtered, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
    if (is_string($json)) {
        set_transient(mpu_chat_integrity_snapshot_key($session_id), $json, HOUR_IN_SECONDS);
    }

    // [Diagnostic] 保存最後一次 store 的來源與時間，供 mismatch log 使用
    $store_source = mpu_chat_integrity_detect_store_source();
    $store_source['stored_at'] = current_time('Y-m-d H:i:s');
    set_transient(mpu_chat_integrity_snapshot_meta_key($session_id), $store_source, HOUR_IN_SECONDS);

    return set_transient(mpu_chat_integrity_transient_key($session_id), $checksum, HOUR_IN_SECONDS);
}

/**
 * 對歷史進行正規化與滑動窗口裁切。
 *
 * [Fix 2026-02-27] 順序改為「先正規化（移除孤立 assistant）→ 再 slice」，
 * 與 verify 端（prepare_user_chat_args line 647-659）的處理順序完全對稱。
 * 舊版為 slice→normalize，在長對話窗口滑動時會產生不對稱。
 *
 * @param array $history 完整歷史
 * @param int   $limit   保留筆數上限
 * @return array
 */
function mpu_chat_integrity_slice_for_store(array $history, $limit = 10)
{
    // Step 1: 先正規化 — 移除開頭的孤立 assistant（與 verify 端對稱）
    $normalized = [];
    $prev_role  = '';
    foreach ($history as $msg) {
        $role = isset($msg['role']) ? (string)$msg['role'] : '';
        if ($role === 'assistant' && $prev_role !== 'user') continue;
        $normalized[] = $msg;
        $prev_role    = $role;
    }

    // Step 2: 再 slice — 取最後 N 筆
    return array_slice($normalized, -$limit);
}
