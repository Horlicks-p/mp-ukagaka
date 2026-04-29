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
    curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) use ($on_chunk) {
        // 1. 執行 Provider 的解析回呼
        call_user_func($on_chunk, $data);

        // 2. 檢查連線狀態：如果用戶端（瀏覽器）已經斷開，我們應該終止上游請求
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
        curl_close($ch);
        
        // 如果是因為我們主動返回 0 導致的斷開，不視為錯誤
        if ($error_code === CURLE_WRITE_ERROR && connection_aborted()) {
            return true;
        }

        return new WP_Error('curl_error', "cURL Error ($error_code): $error_msg");
    }

    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code >= 400) {
        return new WP_Error('http_error', "AI Provider returned HTTP $http_code");
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
