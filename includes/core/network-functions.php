<?php

/**
 * Network, API cache, rate limit, and session helper functions.
 *
 * @package MP_Ukagaka
 * @subpackage Utility
 */

if (!defined('ABSPATH')) {
    exit();
}

// ========================================
// 網路工具函數
// ========================================

/**
 * 獲取客戶端真實 IP 地址
 * 
 * 支援反向代理（Cloudflare、Nginx、HAProxy 等）環境下獲取真實 IP。
 * 優先順序：
 * 1. CF-Connecting-IP（Cloudflare 專用）
 * 2. X-Forwarded-For（通用反向代理 header）
 * 3. X-Real-IP（Nginx 常用）
 * 4. REMOTE_ADDR（直連客戶端）
 * 
 * @return string 客戶端 IP 地址
 */
function mpu_get_client_ip()
{
    $remote_addr = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : '';

    // 從 proxy header 提取 candidate IP
    $candidate = '';
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $candidate = sanitize_text_field($_SERVER['HTTP_CF_CONNECTING_IP']);
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip_list   = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $candidate = sanitize_text_field(trim($ip_list[0]));
    } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
        $candidate = sanitize_text_field($_SERVER['HTTP_X_REAL_IP']);
    }

    // Proxy header 只接受合法公開 IP（拒絕私有/保留位址，防止偽造 header 繞過速率限制）
    if ($candidate && filter_var($candidate, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        return $candidate;
    }

    // 直連或 proxy header 無效時回落到 REMOTE_ADDR（允許私有 IP，適用於內網環境）
    if ($remote_addr && filter_var($remote_addr, FILTER_VALIDATE_IP)) {
        return $remote_addr;
    }

    return '';
}

/**
 * 取得客戶端 IP（嚴格安全版本）
 *
 * 與 mpu_get_client_ip() 的差異：本函式**只在 REMOTE_ADDR 為私有/保留 IP**
 * （即連線來自本機反向代理 / CDN 出口）時才信任 CF-Connecting-IP / X-Forwarded-For。
 * 若 REMOTE_ADDR 是公開 IP（直連客戶端），任何 forwarded header 一律忽略，
 * 直接使用 REMOTE_ADDR——避免攻擊者偽造 forwarded header 來繞過安全判定。
 *
 * 使用情境（必須用 strict）：
 *   - Rate limit 計數鍵
 *   - IP 黑名單比對
 *   - 用 IP 反查資料庫的端點（如 /visitor-info 查 Slimstat）
 *
 * 使用情境（用原版 mpu_get_client_ip() 即可）：
 *   - 顯示給訪客自己看的資訊
 *   - 訪客 country 上下文 enrichment
 *   - 一般 log（追蹤目的而非安全判定）
 *
 * 取捨：CDN（如 Cloudflare）後面的網站，REMOTE_ADDR 通常是 CDN 出口 IP，
 * 屬於公開 IP，本函式會回 CDN IP 而非真實使用者 IP——這對 rate limit 等於
 * 「整個 CDN 出口共用一個配額」，可能誤殺合法使用者；但比起被 spoof 燒爆 API
 * 額度可接受。完整解法應建立可信 proxy/CDN IP 白名單。
 *
 * 邏輯沿用 includes/integrations/bot-blocker-integration.php 內的 mpu_bb_get_ip()。
 *
 * @since 2.13.x
 * @return string 客戶端 IP 地址（保證為合法 IP 字串，或在無 REMOTE_ADDR 時回 '0.0.0.0'）
 */
function mpu_get_client_ip_strict()
{
    $remote_addr = isset($_SERVER['REMOTE_ADDR'])
        ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']))
        : '0.0.0.0';

    // 安全策略：只有當 REMOTE_ADDR 是私有/保留 IP（即連線來自本機反向代理）時，
    // 才信任 proxy header。若 REMOTE_ADDR 是公開 IP，直接使用，不信任任何 proxy header。
    $remote_is_local_proxy = filter_var(
        $remote_addr,
        FILTER_VALIDATE_IP,
        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
    ) === false;

    if ($remote_is_local_proxy) {
        $candidate = '';
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $candidate = sanitize_text_field(wp_unslash($_SERVER['HTTP_CF_CONNECTING_IP']));
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips       = explode(',', sanitize_text_field(wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR'])));
            $candidate = trim($ips[0]);
        } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            $candidate = sanitize_text_field(wp_unslash($_SERVER['HTTP_X_REAL_IP']));
        }
        if ($candidate && filter_var($candidate, FILTER_VALIDATE_IP)) {
            return $candidate;
        }
    }

    return $remote_addr;
}

// ========================================
// 外部 API 請求函數
// ========================================

/**
 * 通用外部 API 請求函數（含快取）
 * 
 * 封裝 wp_remote_get 並提供統一的快取管理、錯誤處理和日誌記錄。
 * 
 * @param string $cache_key 快取鍵（建議格式：mpu_{service}_{identifier}）
 * @param string $url API 端點 URL
 * @param int $cache_duration 快取時間（秒），使用 MPU_CACHE_* 常數
 * @param array $options 額外選項：
 *   - 'headers' => array         額外 HTTP headers
 *   - 'timeout' => int           請求超時時間（預設 10 秒）
 *   - 'user_agent' => string     自訂 User-Agent
 *   - 'log_prefix' => string     日誌前綴（預設 'MP Ukagaka API'）
 *   - 'parse_json' => bool       是否解析 JSON（預設 true）
 * @return array|string|null API 回應資料（解析後的陣列或原始字串），失敗時返回 null
 */
function mpu_fetch_external_api($cache_key, $url, $cache_duration = MPU_CACHE_DEFAULT, $options = [])
{
    // 預設選項
    $defaults = [
        'headers' => [],
        'timeout' => 10,
        'user_agent' => 'WordPress/MP-Ukagaka-Plugin',
        'log_prefix' => 'MP Ukagaka API',
        'parse_json' => true,
    ];
    $options = array_merge($defaults, $options);

    // 檢查快取
    $cached = get_transient($cache_key);
    if ($cached !== false) {
        return $cached;
    }

    // 建構請求 headers
    $headers = array_merge([
        'Accept' => 'application/json',
        'User-Agent' => $options['user_agent'],
    ], $options['headers']);

    // 發送請求
    $response = wp_remote_get($url, [
        'timeout' => $options['timeout'],
        'headers' => $headers,
    ]);

    // 錯誤處理
    if (is_wp_error($response)) {
        mpu_log_error($options['log_prefix'] . ': Request failed - ' . $response->get_error_message());
        return null;
    }

    $status_code = wp_remote_retrieve_response_code($response);

    // 取得回應內容
    $body = wp_remote_retrieve_body($response);

    // 檢查狀態碼（在解析 JSON 前，避免不必要的解析）
    if ($status_code !== 200) {
        mpu_log_warning($options['log_prefix'] . ': API returned status ' . $status_code);

        // 如果是 JSON 錯誤響應，嘗試解析以便調用者檢查
        if ($options['parse_json'] && !empty($body)) {
            $decoded = json_decode($body, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded; // 返回錯誤響應以便調用者檢查
            }
        }

        return null;
    }

    // 解析 JSON（如果啟用）
    $result = $body;
    if ($options['parse_json']) {
        // 檢查空響應
        if (empty($body)) {
            mpu_log_warning($options['log_prefix'] . ': Empty response body');
            return null;
        }

        $decoded = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            mpu_log_error($options['log_prefix'] . ': JSON parse error - ' . json_last_error_msg());
            return null;
        }

        // 檢查解析結果是否為 null（空字串會被解析為 null）
        if ($decoded === null) {
            mpu_log_warning($options['log_prefix'] . ': JSON decoded to null (possibly empty string)');
            return null;
        }

        $result = $decoded;
    }

    // 寫入快取
    if ($cache_duration > 0) {
        set_transient($cache_key, $result, $cache_duration);
    }

    return $result;
}

/**
 * 清除指定快取
 * 
 * @param string $cache_key 快取鍵
 * @return bool 是否成功清除
 */
function mpu_clear_api_cache($cache_key)
{
    return delete_transient($cache_key);
}

// ========================================
// Rate Limiting 函數
// ========================================

/**
 * 速率限制檢查
 * 
 * 檢查指定動作的請求次數是否超過限制。
 * 使用 WordPress Transient API 儲存計數器。
 * 
 * @since 2.5.7
 * @param string $action 動作標識（如 'chat_context', 'user_chat'）
 * @param int $max_requests 時間週期內最大請求數
 * @param int $period 時間週期（秒）
 * @return array [
 *   'allowed' => bool,      // 是否允許此次請求
 *   'remaining' => int,     // 剩餘可用請求數
 *   'reset_in' => int,      // 重置倒計時（秒）
 *   'count' => int,         // 當前已使用次數
 * ]
 */
function mpu_check_rate_limit($action, $max_requests = 10, $period = 60)
{
    // 安全敏感：rate limit 必須用 strict 版，避免 spoofed forwarded header 繞過配額
    $ip = mpu_get_client_ip_strict();
    $transient_key = 'mpu_rl_' . sanitize_key($action) . '_' . md5($ip);
    
    $data = get_transient($transient_key);
    if ($data === false) {
        $data = ['count' => 0, 'first_request' => time()];
    }
    
    $elapsed = time() - $data['first_request'];
    
    // 如果超過週期，重置計數
    if ($elapsed >= $period) {
        $data = ['count' => 0, 'first_request' => time()];
        $elapsed = 0;
    }
    
    // 增加計數
    $data['count']++;
    
    // 更新 transient
    set_transient($transient_key, $data, $period);
    
    return [
        'allowed' => $data['count'] <= $max_requests,
        'remaining' => max(0, $max_requests - $data['count']),
        'reset_in' => max(0, $period - $elapsed),
        'count' => $data['count'],
    ];
}

/**
 * REST API 速率限制檢查（REST pipeline 相容版）
 *
 * 與 mpu_enforce_rate_limit() 不同，此函式會回傳 REST response 物件，
 * 由 REST callback 直接 return，讓 WP_REST_Server 走標準 response pipeline（含 CORS hooks 等）。
 * 超限時會回傳 429 的 WP_REST_Response（含 Retry-After header）。
 *
 * @since 2.8.4
 * @param string $action      動作標識
 * @param int    $max_requests 時間週期內最大請求數
 * @param int    $period       時間週期（秒）
 * @return WP_REST_Response|null 超限時回傳 429 REST 回應，允許時回傳 null
 */
function mpu_rest_check_rate_limit($action, $max_requests = 10, $period = 60)
{
    $result = mpu_check_rate_limit($action, $max_requests, $period);
    if (!$result['allowed']) {
        $response = new WP_REST_Response(
            [
                'code'    => 'rest_rate_limit_exceeded',
                'message' => sprintf(
                    __('リクエストが多すぎます。%d 秒後に再試行してください', 'mp-ukagaka'),
                    $result['reset_in']
                ),
                'data'    => ['status' => 429, 'retry_after' => $result['reset_in']],
            ],
            429
        );
        $response->header('Retry-After', (string) $result['reset_in']);
        return $response;
    }
    return null;
}

/**
 * REST API 自動更新 Nonce
 *
 * 當 wp_verify_nonce() 回傳 2（nonce 處於 12–24 小時老化期）時，
 * 在回應資料中附加 new_token，讓前端自動更新 mpuRestNonce，
 * 避免隔日長工作階段出現 403。
 *
 * @since 2.9.1
 */
add_filter('rest_post_dispatch', function ($result, $server, $request) {
    if (strpos($request->get_route(), '/mp-ukagaka/v1/') === false) {
        return $result;
    }
    $nonce = $request->get_header('X-WP-Nonce');
    if ($nonce && wp_verify_nonce($nonce, 'wp_rest') === 2) {
        $data = $result->get_data();
        if (is_array($data)) {
            $data['new_token'] = wp_create_nonce('wp_rest');
            $result->set_data($data);
        }
    }
    return $result;
}, 10, 3);

// ========================================
// Session Token（訪客請求驗證）
// ========================================

/**
 * 為當前頁面載入產生 IP-bound session token。
 * 存入 transient（TTL 2 小時），與 IP hash 綁定。
 * 用途：驗證 AI REST 端點請求確實來自頁面載入，而非直接 API 呼叫。
 */
function mpu_generate_session_token(): string
{
    $ip = mpu_get_client_ip_strict();
    try {
        $token = bin2hex(random_bytes(16));
    } catch (Exception $e) {
        $token = md5(uniqid('mpu_sess_', true) . wp_rand());
    }
    set_transient('mpu_sess_' . $token, [
        'ip_hash' => md5($ip . wp_salt('auth')),
        'created' => time(),
    ], 2 * HOUR_IN_SECONDS);
    return $token;
}

/**
 * 驗證 session token 是否合法且與當前 IP 匹配。
 *
 * @param string $token 32 字元十六進制 token
 */
function mpu_validate_session_token(string $token): bool
{
    if (empty($token) || !preg_match('/^[0-9a-f]{32}$/', $token)) {
        return false;
    }
    $data = get_transient('mpu_sess_' . $token);
    if ($data === false || !is_array($data)) {
        return false;
    }
    $ip = mpu_get_client_ip_strict();
    return isset($data['ip_hash']) && $data['ip_hash'] === md5($ip . wp_salt('auth'));
}
