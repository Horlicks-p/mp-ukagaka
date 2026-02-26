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
