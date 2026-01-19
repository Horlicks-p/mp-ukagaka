<?php

/**
 * LLM 功能：Slimstat 整合
 * 
 * 處理 Slimstat 統計數據的獲取和整合
 * 
 * @package MP_Ukagaka
 * @subpackage LLM
 */

if (!defined('ABSPATH')) {
    exit();
}

/**
 * 從 Slimstat 設定中獲取第一個可用的 REST API token
 * 
 * @return string|false 返回第一個可用的 token，如果沒有則返回 false
 */
function mpu_get_slimstat_rest_token()
{
    // 檢查 Slimstat 是否啟用
    if (!class_exists('wp_slimstat')) {
        return false;
    }

    // 獲取 Slimstat 設定
    $slimstat_options = get_option('slimstat_options', []);

    if (empty($slimstat_options['rest_api_tokens'])) {
        return false;
    }

    // 將 token 字串轉換為陣列（Slimstat 使用逗號分隔）
    $tokens = array_filter(array_map('trim', explode(',', $slimstat_options['rest_api_tokens'])));

    // 返回第一個可用的 token
    return !empty($tokens) ? reset($tokens) : false;
}

/**
 * 調用 Slimstat REST API 獲取統計數據
 * 
 * @return array 統計數據陣列，包含：
 *   - total_visits: 總訪問數（排除機器人）
 *   - top_resources: 最熱門文章列表（前 5 個）
 */
function mpu_fetch_slimstat_stats()
{
    // 檢查快取（10 分鐘）
    $cache_key = 'mpu_slimstat_stats';
    $cached_stats = get_transient($cache_key);

    if ($cached_stats !== false) {
        return $cached_stats;
    }

    // 初始化返回數據
    $stats = [
        'total_visits' => 0,
        'top_resources' => [],
    ];

    // 獲取 REST API token
    $token = mpu_get_slimstat_rest_token();

    if (empty($token)) {
        // 如果沒有 token，返回空數據並快取 5 分鐘（避免重複檢查）
        set_transient($cache_key, $stats, 5 * MINUTE_IN_SECONDS);
        return $stats;
    }

    // 構建 REST API 端點
    $rest_url = rest_url('slimstat/v1/get');

    // 1. 獲取總訪問數（排除機器人）
    $count_url = add_query_arg([
        'token' => $token,
        'function' => 'count',
        'dimension' => '*',
    ], $rest_url);

    $count_response = wp_remote_get($count_url, [
        'timeout' => 5,
        'sslverify' => false,
    ]);

    if (!is_wp_error($count_response) && wp_remote_retrieve_response_code($count_response) === 200) {
        $count_body = json_decode(wp_remote_retrieve_body($count_response), true);
        if (isset($count_body['data']) && is_numeric($count_body['data'])) {
            $stats['total_visits'] = intval($count_body['data']);
        }
    }

    // 2. 獲取最熱門文章（resource 維度，前 5 個）
    $top_url = add_query_arg([
        'token' => $token,
        'function' => 'top',
        'dimension' => 'resource',
    ], $rest_url);

    $top_response = wp_remote_get($top_url, [
        'timeout' => 5,
        'sslverify' => false,
    ]);

    if (!is_wp_error($top_response) && wp_remote_retrieve_response_code($top_response) === 200) {
        $top_body = json_decode(wp_remote_retrieve_body($top_response), true);
        if (isset($top_body['data']) && is_array($top_body['data'])) {
            // 只取前 5 個
            $top_resources = array_slice($top_body['data'], 0, 5);

            // 格式化資源列表
            foreach ($top_resources as $resource) {
                if (isset($resource['resource'])) {
                    $resource_url = esc_url($resource['resource']);

                    // 嘗試從 URL 獲取文章標題
                    $post_id = url_to_postid($resource_url);
                    $title = '';
                    if ($post_id) {
                        $post = get_post($post_id);
                        if ($post) {
                            $title = get_the_title($post_id);
                        }
                    }

                    $stats['top_resources'][] = [
                        'url' => $resource_url,
                        'title' => $title,
                        'hits' => isset($resource['counthits']) ? intval($resource['counthits']) : 0,
                    ];
                }
            }
        }
    }

    // 記錄調試資訊（僅在 WP_DEBUG 模式下）
    if (defined('WP_DEBUG') && WP_DEBUG) {
        if (is_wp_error($count_response)) {
            mpu_debug_log('Slimstat REST API 錯誤（count）: ' . $count_response->get_error_message());
        }
        if (is_wp_error($top_response)) {
            mpu_debug_log('Slimstat REST API 錯誤（top）: ' . $top_response->get_error_message());
        }
    }

    // 快取結果（10 分鐘）
    set_transient($cache_key, $stats, 10 * MINUTE_IN_SECONDS);

    return $stats;
}

