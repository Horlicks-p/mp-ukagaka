<?php
/**
 * Low-level HTTP Streaming Client for AI Providers
 *
 * @package MP_Ukagaka
 * @subpackage LLM
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 發送串流 HTTP 請求到 AI Provider
 *
 * @param string $url 請求 URL
 * @param array $args 請求參數 (headers, body, method, etc.)
 * @param callable $on_chunk 當接收到資料區塊時的回呼函式 function($data): void
 * @return true|WP_Error
 */
function mpu_stream_api_request($url, $args, $on_chunk) {
    if (!function_exists('curl_init')) {
        return new WP_Error('curl_missing', 'cURL is not available on this server.');
    }

    $ch = curl_init();

    $headers = [];
    if (!empty($args['headers'])) {
        foreach ($args['headers'] as $key => $val) {
            $headers[] = "$key: $val";
        }
    }

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, false); // 我們直接在 WRITEFUNCTION 處理
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_AUTOREFERER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POST, (strtoupper($args['method'] ?? 'POST') === 'POST'));
    curl_setopt($ch, CURLOPT_POSTFIELDS, $args['body'] ?? '');
    curl_setopt($ch, CURLOPT_TIMEOUT, $args['timeout'] ?? 60);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, apply_filters('https_ssl_verify', true));

    // 核心：串流處理函式
    // 同時保留最後 4KB 的原始回應，遇到 4xx/5xx 時可用來把 provider 的錯誤訊息傳回上層。
    $error_tail = '';
    curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) use ($on_chunk, &$error_tail) {
        // 1. 執行 Provider 的解析回呼
        call_user_func($on_chunk, $data);

        // 2. 持續記錄 tail（截至 4KB），HTTP 錯誤時用
        $error_tail .= $data;
        if (strlen($error_tail) > 4096) {
            $error_tail = substr($error_tail, -4096);
        }

        // 3. 檢查連線狀態：如果用戶端（瀏覽器）已經斷開，我們應該終止上游請求
        // 注意：這依賴於 SSE handler 處調用了 ignore_user_abort(true)
        if (connection_aborted()) {
            mpu_debug_log("mpu_stream_api_request: Client disconnected, aborting cURL.");
            return 0; // 返回 0 會導致 cURL 終止並報錯 CURLE_WRITE_ERROR
        }

        return strlen($data);
    });

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        $error_msg = curl_error($ch);
        $error_code = curl_errno($ch);

        // 如果是因為我們主動返回 0 導致的斷開，不視為錯誤
        if ($error_code === CURLE_WRITE_ERROR && connection_aborted()) {
            return true;
        }

        return new WP_Error('curl_error', "cURL Error ($error_code): $error_msg");
    }

    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($http_code >= 400) {
        // 嘗試解析 provider 回傳的 JSON 錯誤訊息
        $detail = '';
        $trimmed_tail = trim($error_tail);
        if ($trimmed_tail !== '') {
            $parsed = json_decode($trimmed_tail, true);
            if (is_array($parsed) && isset($parsed['error'])) {
                $detail = is_string($parsed['error'])
                    ? $parsed['error']
                    : (isset($parsed['error']['message']) ? (string) $parsed['error']['message'] : wp_json_encode($parsed['error']));
            } else {
                // 非 JSON，直接擷取首 500 字
                $detail = mb_substr($trimmed_tail, 0, 500);
            }
        }

        $message = "AI Provider returned HTTP $http_code";
        if ($detail !== '') {
            $message .= ": $detail";
        }

        // 完整 dump：URL + request body + provider 回應，幫助 debug provider 級錯誤。
        // 用 error_log 不走 mpu_debug_log，避免被 WP_DEBUG 旗標擋掉。
        // 把 messages array 抽出來單獨 dump（每個 content 截短），這樣巨大的 system prompt
        // 不會把 tool_calls / tool result 擠出 dump 範圍。
        $body_str = isset($args['body']) ? (string) $args['body'] : '';
        $body_len = strlen($body_str);
        $messages_dump = '(unable to parse messages from body)';
        $body_parsed = json_decode($body_str, true);
        if (is_array($body_parsed) && isset($body_parsed['messages']) && is_array($body_parsed['messages'])) {
            $messages_for_log = [];
            foreach ($body_parsed['messages'] as $idx => $msg) {
                $entry = ['role' => $msg['role'] ?? '?'];
                if (isset($msg['name'])) {
                    $entry['name'] = $msg['name'];
                }
                if (isset($msg['content'])) {
                    if (is_string($msg['content'])) {
                        $content_str = $msg['content'];
                        $len = strlen($content_str);
                        $entry['content'] = $len > 400
                            ? mb_strcut($content_str, 0, 200) . " ... [" . $len . " bytes total] ... " . mb_strcut($content_str, max(0, $len - 200))
                            : $content_str;
                    } else {
                        $entry['content'] = $msg['content'];
                    }
                }
                if (isset($msg['tool_calls'])) {
                    // 完整保留 tool_calls 結構，不截斷 — 這是我們要看的重點
                    $entry['tool_calls'] = $msg['tool_calls'];
                }
                $messages_for_log[] = $entry;
            }
            $messages_dump = wp_json_encode($messages_for_log, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }
        error_log(sprintf(
            "[MP Ukagaka][provider-http] HTTP %d to %s (body=%d bytes)\n--- messages (content truncated, tool_calls verbatim) ---\n%s\n--- response tail ---\n%s",
            $http_code,
            $url,
            $body_len,
            $messages_dump,
            $trimmed_tail
        ));

        return new WP_Error('http_error', $message, ['status' => $http_code, 'body' => $trimmed_tail]);
    }

    return true;
}

/**
 * 解析 SSE 串流，每收到一個完整 event 就呼叫 $callback。
 *
 * 跨 chunk 邊界的行合併、多行 data: 拼接、空行 dispatch 均在此處理，
 * 讓各 Provider 的 generate_chat_stream() 不需各自維護 line parser。
 *
 * @param string   $url      API endpoint URL
 * @param array    $http_args mpu_build_http_args() 格式（headers, body, timeout）
 * @param callable $callback function(string $event_type, string $data_str): void
 * @return WP_Error|null  成功回傳 null，失敗回傳 WP_Error
 */
function mpu_stream_sse_events(string $url, array $http_args, callable $callback): ?WP_Error {
    $chunk_buffer  = '';
    $current_event = '';
    $data_lines    = [];

    $result = mpu_stream_api_request(
        $url,
        $http_args,
        function($chunk) use (&$chunk_buffer, &$current_event, &$data_lines, $callback) {
            $chunk_buffer .= $chunk;

            while (($pos = strpos($chunk_buffer, "\n")) !== false) {
                $line = substr($chunk_buffer, 0, $pos);
                $chunk_buffer = substr($chunk_buffer, $pos + 1);
                $line = rtrim($line, "\r");

                if ($line === '') {
                    // 空行：dispatch 一個完整 event
                    if (!empty($data_lines)) {
                        $data_str = implode("\n", $data_lines);
                        if ($data_str !== '[DONE]') {
                            call_user_func($callback, $current_event, $data_str);
                        }
                    }
                    $current_event = '';
                    $data_lines    = [];
                } elseif (strncmp($line, 'event:', 6) === 0) {
                    $current_event = trim(substr($line, 6));
                } elseif (strncmp($line, 'data:', 5) === 0) {
                    $data_lines[] = ltrim(substr($line, 5));
                }
                // id:, retry: 等欄位忽略
            }
        }
    );

    // 連線結束後 flush 最後未以空行結尾的 event（防止末尾缺空行時資料遺失）
    if (!empty($data_lines)) {
        $data_str = implode("\n", $data_lines);
        if ($data_str !== '[DONE]') {
            call_user_func($callback, $current_event, $data_str);
        }
    }

    if (is_wp_error($result)) {
        return $result;
    }
    return null;
}
