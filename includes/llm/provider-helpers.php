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

// phpcs:disable Generic.WhiteSpace.DisallowSpaceIndent, Generic.Functions.OpeningFunctionBraceKernighanRitchie, Squiz.Functions.FunctionDeclarationArgumentSpacing, PEAR.Functions.FunctionCallSignature, WordPress.WhiteSpace.ControlStructureSpacing, WordPress.WhiteSpace.CastStructureSpacing, Universal.Arrays.DisallowShortArraySyntax, NormalizedArrays.Arrays.ArrayBraceSpacing -- 沿用本檔既有的 4 空格舊式風格.
/**
 * 建構 OpenAI Chat Completions 的 token / 採樣 / 推理參數片段
 *
 * 新世代模型（gpt-6-luna、gpt-5.6-luna）改用 max_completion_tokens，並明確送出
 * reasoning_effort: none —— Chat Completions 只在 none 時支援 function calling，
 * 而 MCP 工具會掛在請求上。其餘模型維持舊格式（max_tokens），
 * 舊模型不接受 reasoning_effort，不可外漏。
 *
 * @param  string     $model       模型 ID.
 * @param  int        $max_tokens  最大輸出 token 數.
 * @param  float|null $temperature 採樣溫度；null 表示不送.
 * @return array      要合併進 request body 的參數
 */
function mpu_build_openai_generation_params($model, $max_tokens, $temperature = null)
{
    // 支援 reasoning_effort: none 的新世代模型（前綴比對，含日期快照）.
    // gpt-5.6-luna 不列在設定頁選單，供使用者以自訂模型填入.
    $none_effort_models = ['gpt-6-luna', 'gpt-5.6-luna'];

    $is_new_generation = false;
    foreach ($none_effort_models as $prefix) {
        if (0 === strpos((string) $model, $prefix)) {
            $is_new_generation = true;
            break;
        }
    }

    if ($is_new_generation) {
        $params = [
            'max_completion_tokens' => intval($max_tokens),
            'reasoning_effort'      => 'none',
        ];
    } else {
        $params = ['max_tokens' => intval($max_tokens)];
    }

    if (null !== $temperature) {
        $params['temperature'] = $temperature;
    }

    return $params;
}
/**
 * 建構 Gemini generateContent 的 generationConfig
 *
 * Gemini 3 之後（3.x、4.x）的模型忽略自訂 temperature / topK / topP 並回 deprecation
 * warning（官方預告之後改回 400），因此一律不送；thinking 改用 thinkingConfig.thinkingLevel。
 * 3.8 Flash 預設 medium，會讓短回覆與測試連線逾時，又不接受 minimal，所以固定送 low；
 * Flash-Lite 預設就是 minimal，不另外指定。
 * 2.5 與無法辨識版本的模型維持各路徑原本的採樣參數，也不送 thinkingLevel（2.5 不接受）。
 *
 * @param  string $model      模型 ID.
 * @param  int    $max_tokens 最大輸出 token 數（含 thinking token）.
 * @param  array  $sampling   舊世代模型沿用的採樣參數（temperature、topK、topP）.
 * @return array  generationConfig
 */
function mpu_build_gemini_generation_config($model, $max_tokens, array $sampling = [])
{
    $config = ['maxOutputTokens' => intval($max_tokens)];
    $model  = strtolower(trim((string) $model));

    if (1 !== preg_match('/^gemini-(\d+)(?:[.-]|$)/', $model, $m) || intval($m[1]) < 3) {
        return array_merge($sampling, $config);
    }

    if (false === strpos($model, '-lite')) {
        $config['thinkingConfig'] = ['thinkingLevel' => 'low'];
    }

    return $config;
}

/**
 * 建構 Claude Messages API 的 token / 採樣 / effort 參數片段
 *
 * 採樣溫度只送給明確接受它的舊模型（精確 ID 白名單）：在 anthropic-version 2023-06-01 下，
 * Opus 4.7 以後與 5.x 收到 temperature 會回 400，Sonnet 5.5 / Haiku 5.5 收到非預設值也會 400。
 * 未知或更新的模型一律不送，避免猜錯能力。
 *
 * Claude 5 以後預設開啟 adaptive thinking，thinking token 也計入 max_tokens：
 * 角色對話送 effort low（簡單請求多半跳過思考），並把 max_tokens 墊到至少 1024，
 * 避免額度在思考階段用完、回應只剩 thinking block。
 *
 * @param  string     $model       模型 ID.
 * @param  int        $max_tokens  最大輸出 token 數.
 * @param  float|null $temperature 採樣溫度；null 表示不送.
 * @return array      要合併進 request body 的參數
 */
function mpu_build_claude_generation_params($model, $max_tokens, $temperature = null)
{
    $temperature_models = [
        'claude-sonnet-4-6',
        'claude-opus-4-6',
        'claude-haiku-4-5',
        'claude-haiku-4-5-20251001',
        'claude-sonnet-4-5',
        'claude-sonnet-4-5-20250929',
        'claude-opus-4-5',
        'claude-opus-4-5-20251101',
    ];

    $model  = strtolower(trim((string) $model));
    $params = ['max_tokens' => intval($max_tokens)];

    if (1 === preg_match('/^claude-[a-z]+-(\d+)(?:-|$)/', $model, $m) && intval($m[1]) >= 5) {
        $params['max_tokens']    = max(1024, $params['max_tokens']);
        $params['output_config'] = ['effort' => 'low'];
    }

    if (null !== $temperature && in_array($model, $temperature_models, true)) {
        $params['temperature'] = $temperature;
    }

    return $params;
}
// phpcs:enable Generic.WhiteSpace.DisallowSpaceIndent, Generic.Functions.OpeningFunctionBraceKernighanRitchie, Squiz.Functions.FunctionDeclarationArgumentSpacing, PEAR.Functions.FunctionCallSignature, WordPress.WhiteSpace.ControlStructureSpacing, WordPress.WhiteSpace.CastStructureSpacing, Universal.Arrays.DisallowShortArraySyntax, NormalizedArrays.Arrays.ArrayBraceSpacing

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
 * 修正 Ollama assistant message 中 tool_calls.function.arguments 的 PHP 空陣列問題。
 *
 * Ollama API 期望 arguments 是 JSON 物件 `{}`，但 PHP 的 json_decode($json, true) 會把
 * 空物件 `{}` 解析成空陣列 `[]`。直接 json_encode 回去就變成陣列字面值 `[]`，
 * Ollama 的 chat template 引擎會吐：
 *   "Value looks like object, but can't find closing '}' symbol"
 *
 * 對策：發現空陣列 arguments 就轉成 stdClass，序列化後就是 `{}`。
 * 非空 PHP assoc array json_encode 本來就會輸出 object，不必處理。
 *
 * @param  array $message Ollama assistant message（可能含 tool_calls）
 * @return array          已正規化的 message
 */
function mpu_normalize_ollama_assistant_message(array $message): array
{
    if (!isset($message['tool_calls']) || !is_array($message['tool_calls'])) {
        return $message;
    }
    foreach ($message['tool_calls'] as &$tc) {
        if (!isset($tc['function']['arguments'])) {
            continue;
        }
        $args = $tc['function']['arguments'];
        if (is_array($args) && empty($args)) {
            $tc['function']['arguments'] = new \stdClass();
        }
    }
    unset($tc);
    return $message;
}

// phpcs:disable Generic.WhiteSpace.DisallowSpaceIndent, Generic.Functions.OpeningFunctionBraceKernighanRitchie, Squiz.Functions.FunctionDeclarationArgumentSpacing, WordPress.WhiteSpace.ControlStructureSpacing, Universal.Arrays.DisallowShortArraySyntax, Generic.WhiteSpace.ArbitraryParenthesesSpacing, PEAR.Functions.FunctionCallSignature, WordPress.WhiteSpace.OperatorSpacing -- 沿用 mpu_normalize_ollama_assistant_message() 的舊式風格.
/**
 * 修正 Claude assistant content 中 tool_use.input 的 PHP 空陣列問題
 *
 * 與 mpu_normalize_ollama_assistant_message() 同一個問題：無參數呼叫的 input `{}`
 * 經 json_decode($json, true) 變成 `[]`，原樣送回下一輪時 Claude 回 400
 * （tool_use.input 必須是物件）。只在送回 API 前使用，執行工具仍用陣列參數。
 *
 * @param  array $content assistant 回合的 content blocks.
 * @return array          已正規化的 content blocks
 */
function mpu_normalize_claude_assistant_content(array $content): array
{
    foreach ($content as &$block) {
        if (($block['type'] ?? '') === 'tool_use' && (!isset($block['input']) || (is_array($block['input']) && empty($block['input'])))) {
            $block['input'] = new \stdClass();
        }
    }
    unset($block);
    return $content;
}

/**
 * 修正 Gemini model content 中 functionCall.args 的 PHP 空陣列問題
 *
 * 無參數呼叫的 args `{}` 解碼後變成 `[]`，原樣送回時 Gemini 會拒絕（args 必須是物件）.
 *
 * @param  array $content candidates[0].content（含 parts）.
 * @return array          已正規化的 content
 */
function mpu_normalize_gemini_model_content(array $content): array
{
    if (!isset($content['parts']) || !is_array($content['parts'])) {
        return $content;
    }
    foreach ($content['parts'] as &$part) {
        if (isset($part['functionCall']['args']) && is_array($part['functionCall']['args']) && empty($part['functionCall']['args'])) {
            $part['functionCall']['args'] = new \stdClass();
        }
    }
    unset($part);
    return $content;
}
// phpcs:enable Generic.WhiteSpace.DisallowSpaceIndent, Generic.Functions.OpeningFunctionBraceKernighanRitchie, Squiz.Functions.FunctionDeclarationArgumentSpacing, WordPress.WhiteSpace.ControlStructureSpacing, Universal.Arrays.DisallowShortArraySyntax, Generic.WhiteSpace.ArbitraryParenthesesSpacing, PEAR.Functions.FunctionCallSignature, WordPress.WhiteSpace.OperatorSpacing

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

// phpcs:disable Generic.WhiteSpace.DisallowSpaceIndent, Generic.Functions.OpeningFunctionBraceKernighanRitchie, Squiz.Functions.FunctionDeclarationArgumentSpacing, PEAR.Functions.FunctionCallSignature, WordPress.WhiteSpace.ControlStructureSpacing, WordPress.WhiteSpace.CastStructureSpacing, Universal.Arrays.DisallowShortArraySyntax, NormalizedArrays.Arrays.ArrayBraceSpacing -- 沿用本檔既有的 4 空格舊式風格.
/**
 * 建構 Gemini functionResponse part
 *
 * Gemini 3 的每個 functionCall 都帶 id，回應必須原樣帶回同一個 id；
 * 2.5 不回傳 id，此時不送空的 id 欄位.
 *
 * @param  string      $function_name 函數名稱
 * @param  mixed       $result mpu_execute_mcp_tool() 的回傳值
 * @param  string|null $call_id functionCall.id；沒有時為 null.
 * @return array  Gemini parts[] 項目
 */
function mpu_build_gemini_function_response_part($function_name, $result, $call_id = null)
{
    $response = [
        'name'     => $function_name,
        'response' => mpu_tool_result_to_object($result),
    ];

    if (is_string($call_id) && '' !== $call_id) {
        $response['id'] = $call_id;
    }

    return ['functionResponse' => $response];
}
// phpcs:enable Generic.WhiteSpace.DisallowSpaceIndent, Generic.Functions.OpeningFunctionBraceKernighanRitchie, Squiz.Functions.FunctionDeclarationArgumentSpacing, PEAR.Functions.FunctionCallSignature, WordPress.WhiteSpace.ControlStructureSpacing, WordPress.WhiteSpace.CastStructureSpacing, Universal.Arrays.DisallowShortArraySyntax, NormalizedArrays.Arrays.ArrayBraceSpacing

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
