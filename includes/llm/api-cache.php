<?php
/**
 * API 快取系統
 * 使用 WordPress Transient API 快取 AI API 回應
 * 
 * @package MP_Ukagaka
 * @subpackage LLM
 * @since 2.5.7
 */

if (!defined('ABSPATH')) {
    exit();
}

/**
 * 檢查 API 快取是否啟用
 * 
 * @return bool
 */
function mpu_is_api_cache_enabled()
{
    $mpu_opt = mpu_get_option();
    return !empty($mpu_opt['api_cache_enabled']);
}

/**
 * 取得快取 TTL（秒）
 * 
 * @return int 預設 3600 秒（1 小時）
 */
function mpu_get_api_cache_ttl()
{
    $mpu_opt = mpu_get_option();
    $ttl = isset($mpu_opt['api_cache_ttl']) ? intval($mpu_opt['api_cache_ttl']) : 3600;
    // 限制範圍：最小 5 分鐘，最大 24 小時
    return max(300, min(86400, $ttl));
}

/**
 * 生成快取鍵
 * 
 * @param string $provider 提供商
 * @param string $system_prompt 系統提示詞
 * @param string $user_prompt 用戶提示詞
 * @return string
 */
function mpu_generate_cache_key($provider, $system_prompt, $user_prompt)
{
    // 只使用 prompt 內容生成 hash，不包含 API key
    $content = $provider . '|' . $system_prompt . '|' . $user_prompt;
    return 'mpu_api_' . md5($content);
}

/**
 * 從快取取得 API 回應
 * 
 * @param string $cache_key 快取鍵
 * @return string|false 快取的回應或 false
 */
function mpu_get_cached_api_response($cache_key)
{
    if (!mpu_is_api_cache_enabled()) {
        return false;
    }
    
    $cached = get_transient($cache_key);
    
    if ($cached !== false) {
        // 記錄快取命中（可選，用於除錯）
        if (defined('MPU_DEBUG') && MPU_DEBUG) {
            error_log("MP Ukagaka: API 快取命中 - {$cache_key}");
        }
    }
    
    return $cached;
}

/**
 * 將 API 回應存入快取
 * 
 * @param string $cache_key 快取鍵
 * @param string $response API 回應
 * @return bool
 */
function mpu_set_cached_api_response($cache_key, $response)
{
    if (!mpu_is_api_cache_enabled()) {
        return false;
    }
    
    // 不快取錯誤回應或空回應
    if (empty($response) || is_wp_error($response)) {
        return false;
    }
    
    $ttl = mpu_get_api_cache_ttl();
    return set_transient($cache_key, $response, $ttl);
}

/**
 * 清除所有 LLM API 快取
 * 
 * @return int 清除的快取數量
 */
function mpu_clear_all_api_cache()
{
    global $wpdb;
    
    // 刪除所有以 mpu_api_ 開頭的 transients
    $count = $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
            '_transient_mpu_api_%',
            '_transient_timeout_mpu_api_%'
        )
    );
    
    return intval($count / 2); // 每個 transient 有 2 筆記錄
}

/**
 * 取得 API 快取統計
 * 
 * @return array
 */
function mpu_get_api_cache_stats()
{
    global $wpdb;
    
    $count = $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '_transient_mpu_api_%'"
    );
    
    return [
        'count' => intval($count),
        'ttl' => mpu_get_api_cache_ttl(),
        'enabled' => mpu_is_api_cache_enabled()
    ];
}
