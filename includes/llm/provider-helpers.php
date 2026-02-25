<?php

/**
 * LLM Provider 共用 Helper
 *
 * Pure helper 函數：無流程控制、無副作用。
 * 統一 JSON encode flags、tool result 格式化，為未來 Provider Factory 或
 * 單輪/多輪路徑合併預鋪基礎。
 *
 * @package MP_Ukagaka
 * @subpackage LLM
 */

if (!defined('ABSPATH')) {
    exit();
}

// ========================================
// JSON Encoding
// ========================================

/**
 * UTF-8 安全的 JSON 編碼（統一旗標）
 *
 * 統一使用 JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE：
 * - JSON_UNESCAPED_UNICODE      : 保留中文/日文字元，不轉義為 \uXXXX（縮短 payload）
 * - JSON_INVALID_UTF8_SUBSTITUTE: 無效 UTF-8 序列以 U+FFFD 替換，防止 json_encode 靜默回傳 false
 *
 * 取代散落各 provider 的 wp_json_encode($data) 與 json_encode($data) 呼叫，
 * 確保所有 LLM API 請求及 tool result 序列化一致。
 *
 * @param  mixed        $data 要編碼的資料
 * @return string|false JSON 字串；編碼失敗時回傳 false
 */
function mpu_json_encode_safe($data)
{
    $encoded = wp_json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($encoded === false) {
        // 最後防線：不可序列化的資料（例如 resource、循環引用）回傳錯誤物件，
        // 確保呼叫端永遠拿到字串，不會因為 false 造成 API 請求靜默失敗。
        if (function_exists('mpu_debug_log')) {
            mpu_debug_log('mpu_json_encode_safe: json_encode 失敗，type=' . gettype($data));
        } elseif (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('MP Ukagaka: mpu_json_encode_safe json_encode 失敗，type=' . gettype($data));
        }
        return '{"error":"json_encode_failed"}';
    }
    return $encoded;
}

// ========================================
// Tool Result 格式化
// ========================================

/**
 * 將工具執行結果轉為 Gemini functionResponse 所需的陣列格式
 *
 * Gemini API 的 functionResponse.response 欄位需要 array 或 object，不能是純字串。
 *
 * @param  mixed $result mpu_execute_mcp_tool() 的回傳值
 * @return array 可直接用於 functionResponse.response 的陣列
 */
function mpu_tool_result_to_object($result)
{
    if (is_wp_error($result)) {
        return ['error' => $result->get_error_message()];
    }
    if (is_array($result) || is_object($result)) {
        return (array) $result;
    }
    // 純量包裝為 {"result": "..."}
    return ['result' => (string) $result];
}

/**
 * 將工具執行結果轉為 OpenAI / Claude / Ollama tool message 所需的字串格式
 *
 * 這三個 provider 的 tool/function role 訊息 content 欄位需要字串。
 * WP_Error 序列化為 JSON object，保持結構一致（LLM 較易解析 {"error":"..."} 而非純文字）。
 *
 * @param  mixed  $result mpu_execute_mcp_tool() 的回傳值
 * @return string 可直接作為 message.content 的字串
 */
function mpu_tool_result_to_string($result)
{
    if (is_wp_error($result)) {
        return mpu_json_encode_safe(['error' => $result->get_error_message()]);
    }
    if (is_string($result)) {
        return $result;
    }
    return mpu_json_encode_safe($result);
}

// ========================================
// HTTP Request 建構
// ========================================

/**
 * 取得 LLM Provider 的 HTTP 請求標頭
 *
 * Gemini 的 API key 放在 URL query string，不在標頭；
 * Ollama 本機服務不需要認證。
 *
 * @param  string $provider 'gemini' | 'openai' | 'claude' | 'ollama'
 * @param  string $api_key  API 金鑰（gemini / ollama 不需要）
 * @return array  HTTP headers 陣列
 */
function mpu_get_provider_headers($provider, $api_key = '')
{
    $base = ['Content-Type' => 'application/json'];
    switch ($provider) {
        case 'openai':
            return array_merge($base, ['Authorization' => 'Bearer ' . $api_key]);
        case 'claude':
            return array_merge($base, [
                'x-api-key'         => $api_key,
                'anthropic-version' => '2023-06-01',
            ]);
        case 'gemini':
        case 'ollama':
        default:
            return $base; // Gemini key 在 URL；Ollama 無需 auth
    }
}

/**
 * 建構 wp_remote_post() 所需的 $args 陣列
 *
 * body 自動經由 mpu_json_encode_safe() 序列化，確保 UTF-8 安全。
 *
 * @param  array  $headers   HTTP headers（可由 mpu_get_provider_headers() 產生）
 * @param  mixed  $body_data 請求 body 資料（PHP array / object）
 * @param  int    $timeout   超時秒數（預設 60）
 * @return array  wp_remote_post() 的 $args
 */
function mpu_build_http_args($headers, $body_data, $timeout = 60)
{
    return [
        'headers' => $headers,
        'body'    => mpu_json_encode_safe($body_data),
        'timeout' => intval($timeout),
    ];
}

// ========================================
// Tool Message 建構（per-provider）
// ========================================

/**
 * 建構 OpenAI tool message（role: tool）
 *
 * @param  string $tool_call_id tool_call['id']
 * @param  string $function_name 函數名稱
 * @param  mixed  $result mpu_execute_mcp_tool() 的回傳值
 * @return array  OpenAI messages[] 項目
 */
function mpu_build_openai_tool_message($tool_call_id, $function_name, $result)
{
    return [
        'role'         => 'tool',
        'tool_call_id' => $tool_call_id,
        'name'         => $function_name,
        'content'      => mpu_tool_result_to_string($result),
    ];
}

/**
 * 建構 Ollama tool message（role: tool）
 *
 * @param  string $function_name 函數名稱
 * @param  mixed  $result mpu_execute_mcp_tool() 的回傳值
 * @return array  Ollama messages[] 項目
 */
function mpu_build_ollama_tool_message($function_name, $result)
{
    return [
        'role'    => 'tool',
        'name'    => $function_name,
        'content' => mpu_tool_result_to_string($result),
    ];
}

/**
 * 建構 Claude tool_result content block
 *
 * @param  string $tool_use_id content_block['id']
 * @param  mixed  $result mpu_execute_mcp_tool() 的回傳值
 * @return array  Claude tool_results[] 項目
 */
function mpu_build_claude_tool_result_block($tool_use_id, $result)
{
    return [
        'type'        => 'tool_result',
        'tool_use_id' => $tool_use_id,
        'content'     => mpu_tool_result_to_string($result),
    ];
}

/**
 * 建構 Gemini functionResponse part
 *
 * @param  string $function_name 函數名稱
 * @param  mixed  $result mpu_execute_mcp_tool() 的回傳值
 * @return array  Gemini parts[] 項目
 */
function mpu_build_gemini_function_response_part($function_name, $result)
{
    return [
        'functionResponse' => [
            'name'     => $function_name,
            'response' => mpu_tool_result_to_object($result),
        ],
    ];
}

// ========================================
// API 錯誤解析
// ========================================

/**
 * 從 API 錯誤回應 body 中提取 error.message 字串
 *
 * Gemini / OpenAI / Claude 三家的錯誤回應均使用
 * {"error": {"message": "..."}} 結構，統一在此解析。
 *
 * @param  string $response_body wp_remote_retrieve_body() 回傳的原始 body
 * @param  string $fallback      無法解析時回傳的預設訊息
 * @return string error.message 字串，或 $fallback
 */
function mpu_parse_api_error_message($response_body, $fallback = '')
{
    $data = mpu_json_decode_assoc($response_body);
    if (isset($data['error']['message'])) {
        return $data['error']['message'];
    }
    return $fallback;
}

// ========================================
// JSON 解碼
// ========================================

/**
 * JSON 解碼為關聯陣列（非 strict，純重構替換）
 *
 * 取代各 provider 的 json_decode($body, true) 散落呼叫，
 * 統一入口方便日後添加錯誤日誌或 strict 模式。
 *
 * 注意：故意不呼叫 json_last_error() 以避免行為改變（呼叫端目前
 * 以 empty() / isset() 自行判斷解碼結果是否有效）。
 * 若需要嚴格版本（decode 失敗時回傳 WP_Error），
 * 請另行新增 mpu_json_decode_assoc_strict()，勿修改此函數。
 *
 * @param  string $body JSON 字串
 * @return array|null   解碼結果，失敗時回傳 null
 */
function mpu_json_decode_assoc($body)
{
    return json_decode($body, true);
}
