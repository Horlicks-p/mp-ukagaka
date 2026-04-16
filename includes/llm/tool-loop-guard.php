<?php
/**
 * Tool Call Loop Guard Helper.
 *
 * Provides functions to detect and prevent infinite loops in LLM tool calls.
 *
 * @package MPU
 * @since 2.10.0
 */

if (!defined("ABSPATH")) {
    exit();
}

/**
 * Normalize tool arguments for consistent comparison.
 *
 * Handles different formats (JSON, Object, Array) and ensures key ordering.
 *
 * @param mixed $args Tool arguments.
 * @return array Normalized arguments.
 */
function mpu_normalize_tool_args($args) {
    if (is_string($args)) {
        $decoded = json_decode($args, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $args = $decoded;
        } else {
            // Not JSON, treat as raw string in an array
            return ['_raw' => $args];
        }
    }

    if (is_object($args)) {
        $args = json_decode(json_encode($args), true);
    }

    if (!is_array($args)) {
        return ['_val' => (string) $args];
    }

    // Recursive sorting and string conversion for stability
    return mpu_recursive_normalize_array($args);
}

/**
 * Helper: Recursively normalize array keys and values.
 *
 * @param array $array
 * @return array
 */
function mpu_recursive_normalize_array($array) {
    ksort($array);
    foreach ($array as $key => $value) {
        if (is_array($value)) {
            $array[$key] = mpu_recursive_normalize_array($value);
        } elseif (is_numeric($value)) {
            // Convert numbers to string to avoid 10 vs "10" mismatch
            $array[$key] = (string) $value;
        } elseif (is_bool($value)) {
            $array[$key] = $value ? 'true' : 'false';
        } elseif (is_null($value)) {
            $array[$key] = 'null';
        }
    }
    return $array;
}

/**
 * Build a stable signature for a tool call.
 *
 * @param string $tool_name
 * @param mixed $args
 * @return string
 */
function mpu_build_tool_call_signature($tool_name, $args) {
    $normalized = mpu_normalize_tool_args($args);
    $args_json = mpu_json_encode_safe($normalized);
    return $tool_name . ':' . md5($args_json);
}

/**
 * Check if a tool call constitutes a loop and update the state.
 *
 * @param array $state Reference to the loop detection state.
 * @param string $provider Current provider slug.
 * @param int $turn Current turn number.
 * @param string $tool_name Tool being called.
 * @param mixed $args Tool arguments.
 * @return true|WP_Error True if safe to proceed, WP_Error if loop detected.
 */
function mpu_tool_loop_guard_check(&$state, $provider, $turn, $tool_name, $args) {
    $signature = mpu_build_tool_call_signature($tool_name, $args);
    $threshold = defined('MPU_MAX_TOOL_REPEAT_SAME_CALL') ? MPU_MAX_TOOL_REPEAT_SAME_CALL : 2;

    if (!isset($state['last_signature'])) {
        $state['last_signature'] = null;
        $state['consecutive_count'] = 0;
    }

    if ($state['last_signature'] === $signature) {
        $state['consecutive_count']++;
    } else {
        $state['last_signature'] = $signature;
        $state['consecutive_count'] = 1;
    }

    if ($state['consecutive_count'] >= $threshold) {
        $error_msg = sprintf(
            __('ツール呼び出しのループを検出しました：ツール "%s" が %d 回連続して繰り返し呼び出されています。無限ループを防ぐため中止します。', 'mp-ukagaka'),
            $tool_name,
            $state['consecutive_count']
        );

        // Logging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            mpu_debug_log(sprintf(
                '[LoopGuard] Detected loop! Provider: %s, Turn: %d, Tool: %s, Signature: %s, Count: %d',
                $provider,
                $turn,
                $tool_name,
                $signature,
                $state['consecutive_count']
            ));
        }

        return new WP_Error('tool_call_loop_detected', $error_msg, [
            'tool_name'    => $tool_name,
            'repeat_count' => $state['consecutive_count'],
            'turn'         => $turn,
            'provider'     => $provider,
            'signature'    => $signature,
        ]);
    }

    // Normal logging for tracing
    if (defined('WP_DEBUG') && WP_DEBUG) {
        mpu_debug_log(sprintf(
            '[LoopGuard] Trace: Provider: %s, Turn: %d, Tool: %s, Signature: %s, Count: %d',
            $provider,
            $turn,
            $tool_name,
            $signature,
            $state['consecutive_count']
        ));
    }

    return true;
}
