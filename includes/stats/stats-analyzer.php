<?php

/**
 * 統計分析器
 * 
 * 負責分析和彙總統計資料，提供趨勢和摘要資訊
 * 
 * @package MP_Ukagaka
 * @subpackage Stats
 * @since 2.6.0
 */

if (!defined('ABSPATH')) {
    exit();
}

/**
 * 獲取統計摘要
 * 
 * @return array 統計摘要
 */
function mpu_get_stats_summary()
{
    return mpu_build_stats_summary_from_dataset([]);
}

/**
 * 獲取過去 N 天的趨勢資料
 * 
 * @param int $days 天數（預設 7 天）
 * @return array 趨勢資料陣列
 */
function mpu_get_weekly_trend($days = 7)
{
    return mpu_build_trend_from_dataset([], $days);
}

/**
 * 獲取過去 30 天的趨勢資料
 * 
 * @return array 趨勢資料陣列
 */
function mpu_get_monthly_trend()
{
    return mpu_get_weekly_trend(30);
}

/**
 * 獲取熱門話題排行
 * 
 * @param int $days 統計天數（預設 7 天）
 * @param int $limit 返回數量（預設 10 個）
 * @return array 熱門話題陣列 [['topic' => '...', 'count' => N], ...]
 */
function mpu_get_top_topics($days = 7, $limit = 10)
{
    return mpu_build_top_topics_from_dataset([], $days, $limit);
}

/**
 * 獲取提供商使用分佈
 * 
 * @param int $days 統計天數（預設 7 天）
 * @return array 提供商分佈 ['gemini' => N, 'openai' => M, ...]
 */
function mpu_get_provider_distribution($days = 7)
{
    return mpu_build_provider_distribution_from_dataset([], $days);
}

/**
 * 獲取對話類型分佈
 * 
 * @param int $days 統計天數（預設 7 天）
 * @return array 對話類型分佈
 */
function mpu_get_conversation_distribution($days = 7)
{
    return mpu_build_conversation_distribution_from_dataset([], $days);
}

/**
 * 獲取儀表板所需的所有統計資料
 * 
 * @param int $days 統計天數（預設 7 天）
 * @return array 完整的儀表板資料
 */
function mpu_get_dashboard_data($days = 7)
{
    // 預先載入資料集以避免 N+1 查詢問題
    $load_days = max($days, 7);
    $dataset = [];
    $now = current_time('timestamp');
    for ($i = 0; $i < $load_days; $i++) {
        $timestamp = $now - ($i * DAY_IN_SECONDS);
        $date = date('Ymd', $timestamp);
        $dataset[$date] = [
            'stats' => mpu_get_daily_stats($date)
        ];
    }

    return [
        'summary' => mpu_build_stats_summary_from_dataset($dataset),
        'trend' => mpu_build_trend_from_dataset($dataset, $days),
        'top_topics' => mpu_build_top_topics_from_dataset($dataset, $days),
        'provider_distribution' => mpu_build_provider_distribution_from_dataset($dataset, $days),
        'conversation_distribution' => mpu_build_conversation_distribution_from_dataset($dataset, $days),
        'generated_at' => current_time('mysql')
    ];
}

// ==========================================
// 內部 Helper 集合 (Dataset Aggregators)
// 用於純資料陣列的聚合運算，避免重複資料庫 I/O
// ==========================================

function mpu_build_stats_summary_from_dataset($dataset)
{
    $today_date = date('Ymd', current_time('timestamp'));
    $today_stats = isset($dataset[$today_date]) ? $dataset[$today_date]['stats'] : mpu_get_daily_stats($today_date);
    $week_stats = mpu_build_trend_from_dataset($dataset, 7);
    
    // 計算今日 API 調用總數
    $today_api_calls = 0;
    $today_cached = 0;
    $today_errors = 0;
    
    foreach ($today_stats['api_calls'] as $provider => $counts) {
        $today_api_calls += ($counts['success'] ?? 0) + ($counts['error'] ?? 0) + ($counts['cached'] ?? 0);
        $today_cached += $counts['cached'] ?? 0;
        $today_errors += $counts['error'] ?? 0;
    }
    
    // 計算今日對話總數
    $today_conversations = array_sum($today_stats['conversations']);
    
    // 計算本週對話總數
    $week_conversations = 0;
    foreach ($week_stats as $day_data) {
        $week_conversations += array_sum($day_data['conversations'] ?? []);
    }
    
    // 計算平均回應時間
    $avg_response_time = 0;
    if (!empty($today_stats['response_times'])) {
        $avg_response_time = round(array_sum($today_stats['response_times']) / count($today_stats['response_times']), 2);
    }
    
    // 計算快取命中率
    $cache_hit_rate = 0;
    if ($today_api_calls > 0) {
        $cache_hit_rate = round(($today_cached / $today_api_calls) * 100, 1);
    }
    
    return [
        'today_api_calls' => $today_api_calls,
        'today_cached' => $today_cached,
        'today_errors' => $today_errors,
        'today_conversations' => $today_conversations,
        'week_conversations' => $week_conversations,
        'avg_response_time' => $avg_response_time,
        'cache_hit_rate' => $cache_hit_rate
    ];
}

function mpu_build_trend_from_dataset($dataset, $days)
{
    $trend = [];
    $now = current_time('timestamp');
    
    for ($i = $days - 1; $i >= 0; $i--) {
        $timestamp = $now - ($i * DAY_IN_SECONDS);
        $date = date('Ymd', $timestamp);
        $stats = isset($dataset[$date]) ? $dataset[$date]['stats'] : mpu_get_daily_stats($date);
        
        // 計算當日 API 調用
        $api_calls = 0;
        $cached = 0;
        foreach ($stats['api_calls'] as $provider => $counts) {
            $api_calls += ($counts['success'] ?? 0) + ($counts['error'] ?? 0);
            $cached += $counts['cached'] ?? 0;
        }
        
        $trend[] = [
            'date' => $date,
            'date_display' => date('m/d', $timestamp),
            'api_calls' => $api_calls,
            'cached' => $cached,
            'conversations' => $stats['conversations'],
            'total_conversations' => array_sum($stats['conversations'])
        ];
    }
    
    return $trend;
}

function mpu_build_top_topics_from_dataset($dataset, $days = 7, $limit = 10)
{
    $all_topics = [];
    $now = current_time('timestamp');
    
    for ($i = 0; $i < $days; $i++) {
        $timestamp = $now - ($i * DAY_IN_SECONDS);
        $date = date('Ymd', $timestamp);
        $stats = isset($dataset[$date]) ? $dataset[$date]['stats'] : mpu_get_daily_stats($date);
        
        foreach ($stats['topics'] as $topic => $count) {
            if (!isset($all_topics[$topic])) {
                $all_topics[$topic] = 0;
            }
            $all_topics[$topic] += $count;
        }
    }
    
    // 排序並取前 N 個
    arsort($all_topics);
    $top_topics = array_slice($all_topics, 0, $limit, true);
    
    // 格式化輸出
    $result = [];
    foreach ($top_topics as $topic => $count) {
        $result[] = [
            'topic' => $topic,
            'count' => $count
        ];
    }
    
    return $result;
}

function mpu_build_provider_distribution_from_dataset($dataset, $days = 7)
{
    $distribution = [];
    $now = current_time('timestamp');
    
    for ($i = 0; $i < $days; $i++) {
        $timestamp = $now - ($i * DAY_IN_SECONDS);
        $date = date('Ymd', $timestamp);
        $stats = isset($dataset[$date]) ? $dataset[$date]['stats'] : mpu_get_daily_stats($date);
        
        foreach ($stats['api_calls'] as $provider => $counts) {
            if (!isset($distribution[$provider])) {
                $distribution[$provider] = 0;
            }
            // 只計算實際的 API 調用（success + error），不包含 cached
            $distribution[$provider] += ($counts['success'] ?? 0) + ($counts['error'] ?? 0);
        }
    }
    
    return $distribution;
}

function mpu_build_conversation_distribution_from_dataset($dataset, $days = 7)
{
    $distribution = [
        'context' => 0,
        'interactive' => 0,
        'greeting' => 0,
        'touch' => 0,
        'auto_talk' => 0,
        'decoration' => 0
    ];
    $now = current_time('timestamp');
    
    for ($i = 0; $i < $days; $i++) {
        $timestamp = $now - ($i * DAY_IN_SECONDS);
        $date = date('Ymd', $timestamp);
        $stats = isset($dataset[$date]) ? $dataset[$date]['stats'] : mpu_get_daily_stats($date);
        
        foreach ($stats['conversations'] as $type => $count) {
            if (isset($distribution[$type])) {
                $distribution[$type] += $count;
            }
        }
    }
    
    return $distribution;
}
