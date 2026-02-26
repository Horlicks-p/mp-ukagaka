<?php
/**
 * Request-scoped runtime state helpers.
 *
 * Centralizes per-request mutable state so REST/AJAX entrypoints can reset it
 * before handling a new request. This prevents stale flags from leaking into
 * subsequent logic (and provides a single extension point for future SSE state).
 *
 * @package MP_Ukagaka
 * @subpackage LLM
 */

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Initialize or reset request-scoped state.
 *
 * @param string $context Reset source label for debugging.
 * @param bool   $force   Whether to force reset when state already exists.
 * @return array
 */
function mpu_request_state_bootstrap($context = 'manual', $force = false)
{
    global $mpu_request_state;
    global $mpu_mcp_tool_executed;

    if (!$force && is_array($mpu_request_state)) {
        return $mpu_request_state;
    }

    $mpu_request_state = [
        'meta' => [
            'context'   => (string) $context,
            'reset_at'  => microtime(true),
            'reset_cnt' => isset($mpu_request_state['meta']['reset_cnt']) ? intval($mpu_request_state['meta']['reset_cnt']) + 1 : 1,
        ],
        'flags' => [
            'mcp_tool_executed' => false,
        ],
        'streaming' => [
            'buffer' => '',
        ],
        'function_call' => [
            'last_result' => null,
        ],
    ];

    // Legacy compatibility for code paths still reading this global directly.
    $mpu_mcp_tool_executed = false;

    return $mpu_request_state;
}

/**
 * Force reset request-scoped state.
 *
 * @param string $context Reset source label for debugging.
 * @return array
 */
function mpu_reset_request_state($context = 'manual')
{
    return mpu_request_state_bootstrap($context, true);
}

/**
 * Ensure request-scoped state exists without forcing reset.
 *
 * @param string $context Init source label for debugging.
 * @return array
 */
function mpu_ensure_request_state($context = 'manual')
{
    return mpu_request_state_bootstrap($context, false);
}

/**
 * Mark that at least one MCP tool executed in this request.
 *
 * @return void
 */
function mpu_mark_request_mcp_tool_executed()
{
    global $mpu_request_state;
    global $mpu_mcp_tool_executed;

    mpu_ensure_request_state('mark_mcp_tool');

    $mpu_request_state['flags']['mcp_tool_executed'] = true;
    $mpu_mcp_tool_executed = true;
}

/**
 * Whether an MCP tool has executed in this request.
 *
 * @return bool
 */
function mpu_did_request_execute_mcp_tool()
{
    global $mpu_request_state;
    global $mpu_mcp_tool_executed;

    if (is_array($mpu_request_state) && isset($mpu_request_state['flags']['mcp_tool_executed'])) {
        return !empty($mpu_request_state['flags']['mcp_tool_executed']);
    }

    return !empty($mpu_mcp_tool_executed);
}

/**
 * Reset request state before MP Ukagaka REST callbacks run.
 *
 * @param mixed           $result  Response to replace the requested version with.
 * @param WP_REST_Server  $server  Server instance.
 * @param WP_REST_Request $request Request object.
 * @return mixed
 */
function mpu_rest_request_state_reset($result, $server, $request)
{
    if (!($request instanceof WP_REST_Request)) {
        return $result;
    }

    $route = (string) $request->get_route();
    if (strpos($route, '/mp-ukagaka/v1/') !== 0) {
        return $result;
    }

    mpu_reset_request_state('rest:' . $route);

    return $result;
}

add_filter('rest_pre_dispatch', 'mpu_rest_request_state_reset', 5, 3);

