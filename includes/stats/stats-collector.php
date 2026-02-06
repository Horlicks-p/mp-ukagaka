<?php

/**
 * 統計收集器
 * 
 * 負責收集和儲存 API 調用、對話、話題等統計資料
 * 
 * @package MP_Ukagaka
 * @subpackage Stats
 * @since 2.6.0
 */

if (!defined('ABSPATH')) {
    exit();
}

/**
 * 獲取統計資料的 option key
 * 
 * @param string $date 日期，格式 YYYYMMDD（預設今天）
 * @return string Option key
 */
function mpu_get_stats_key($date = null)
{
    if ($date === null) {
        $date = current_time('Ymd');
    }
    return 'mpu_stats_' . $date;
}

/**
 * 獲取指定日期的統計資料
 * 
 * @param string $date 日期，格式 YYYYMMDD（預設今天）
 * @return array 統計資料陣列
 */
function mpu_get_daily_stats($date = null)
{
    $key = mpu_get_stats_key($date);
    $stats = get_option($key, null);
    
    if ($stats === null) {
        // 初始化空統計資料
        $stats = [
            'api_calls' => [],
            'conversations' => [
                'context' => 0,
                'interactive' => 0,
                'greeting' => 0,
                'touch' => 0,
                'auto_talk' => 0,
                'decoration' => 0
            ],
            'topics' => [],
            'response_times' => [],
            'date' => $date ?? current_time('Ymd')
        ];
    }
    
    return $stats;
}

/**
 * 儲存統計資料
 * 
 * @param array $stats 統計資料陣列
 * @param string $date 日期，格式 YYYYMMDD（預設今天）
 * @return bool 是否成功
 */
function mpu_save_daily_stats($stats, $date = null)
{
    $key = mpu_get_stats_key($date);
    
    // 檢查選項是否已存在
    if (false === get_option($key)) {
        // 新選項：使用 add_option 並設定 autoload = 'no'
        // 這樣可以避免統計資料被自動載入，減少記憶體使用
        return add_option($key, $stats, '', 'no');
    } else {
        // 更新現有選項（不會改變 autoload 屬性）
        return update_option($key, $stats, false);
    }
}

/**
 * 記錄 API 調用
 * 
 * @param string $provider 提供商名稱（gemini, openai, claude, ollama）
 * @param string $status 狀態（success, error, cached）
 * @param float $response_time 回應時間（毫秒），快取命中時為 0
 * @return bool 是否成功記錄
 */
function mpu_record_api_call($provider, $status, $response_time = 0)
{
    $stats = mpu_get_daily_stats();
    
    // 初始化提供商統計
    if (!isset($stats['api_calls'][$provider])) {
        $stats['api_calls'][$provider] = [
            'success' => 0,
            'error' => 0,
            'cached' => 0
        ];
    }
    
    // 增加計數
    if (isset($stats['api_calls'][$provider][$status])) {
        $stats['api_calls'][$provider][$status]++;
    }
    
    // 記錄回應時間（僅成功的非快取請求）
    if ($status === 'success' && $response_time > 0) {
        $stats['response_times'][] = round($response_time, 2);
        
        // 限制陣列大小，避免資料過大（保留最近 100 筆）
        if (count($stats['response_times']) > 100) {
            $stats['response_times'] = array_slice($stats['response_times'], -100);
        }
    }
    
    return mpu_save_daily_stats($stats);
}

/**
 * 記錄對話事件
 * 
 * @param string $type 對話類型（context, interactive, greeting, touch）
 * @return bool 是否成功記錄
 */
function mpu_record_conversation($type)
{
    $valid_types = ['context', 'interactive', 'greeting', 'touch', 'auto_talk', 'decoration'];
    if (!in_array($type, $valid_types)) {
        return false;
    }
    
    $stats = mpu_get_daily_stats();
    
    if (!isset($stats['conversations'][$type])) {
        $stats['conversations'][$type] = 0;
    }
    
    $stats['conversations'][$type]++;
    
    return mpu_save_daily_stats($stats);
}

/**
 * 記錄話題關鍵字
 * 
 * @param string $topic 話題關鍵字
 * @return bool 是否成功記錄
 */
function mpu_record_topic($topic)
{
    if (empty($topic)) {
        return false;
    }
    
    // 清理話題名稱（保留 unicode 字符）
    $topic = trim($topic);
    $topic = wp_strip_all_tags($topic);  // 移除 HTML 標籤但保留文字
    $topic = mb_substr($topic, 0, 50, 'UTF-8'); // 限制長度（指定 UTF-8 編碼）
    
    $stats = mpu_get_daily_stats();
    
    if (!isset($stats['topics'][$topic])) {
        $stats['topics'][$topic] = 0;
    }
    
    $stats['topics'][$topic]++;
    
    // 限制話題數量，避免資料過大（保留前 50 個熱門話題）
    if (count($stats['topics']) > 50) {
        arsort($stats['topics']);
        $stats['topics'] = array_slice($stats['topics'], 0, 50, true);
    }
    
    return mpu_save_daily_stats($stats);
}

/**
 * 從文章標題中提取話題
 * 
 * @param string $title 文章標題
 * @return void
 */
function mpu_extract_and_record_topic($title)
{
    if (empty($title)) {
        return;
    }
    
    $original_title = trim($title);
    
    // 移除常見的前綴括號（支援各種括號格式）
    // 全形方括號【】
    $title = preg_replace('/^【[^】]+】\s*/u', '', $title);
    // 半形方括號 []
    $title = preg_replace('/^\[[^\]]+\]\s*/u', '', $title);
    // 全形方括號 ［］ (日文)
    $title = preg_replace('/^［[^］]+］\s*/u', '', $title);
    // 日文引號「」
    $title = preg_replace('/^「[^」]+」\s*/u', '', $title);
    // 圓括號 ()（）
    $title = preg_replace('/^\([^)]+\)\s*/u', '', $title);
    $title = preg_replace('/^（[^）]+）\s*/u', '', $title);
    
    // 移除網站名稱後綴
    $title = preg_replace('/\s*[\|–—:：]\s*[^|\|–—:：]+$/u', '', $title);
    
    // 清理多餘空白
    $title = trim($title);
    
    // 如果清理後為空，使用原始標題
    if (empty($title)) {
        $title = $original_title;
    }
    
    // 限制標題長度（50 字）
    if (mb_strlen($title, 'UTF-8') > 50) {
        $title = mb_substr($title, 0, 50, 'UTF-8');
    }
    
    // 最終檢查：確保不是空字串
    if (!empty($title)) {
        mpu_record_topic($title);
    }
}

/**
 * 清理指定日期之前的舊統計資料
 * 
 * @param int $days_to_keep 保留天數（預設 30 天）
 * @return int 清除的記錄數量
 */
function mpu_cleanup_old_stats($days_to_keep = 30)
{
    global $wpdb;
    
    $cutoff_date = date('Ymd', strtotime("-{$days_to_keep} days"));
    
    // 查找並刪除舊的統計 options
    $deleted = $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE %s 
             AND option_name < %s",
            'mpu_stats_%',
            'mpu_stats_' . $cutoff_date
        )
    );
    
    return intval($deleted);
}

/**
 * 清除所有統計資料
 * 
 * @return int 清除的記錄數量
 */
function mpu_clear_all_stats()
{
    global $wpdb;
    
    $deleted = $wpdb->query(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'mpu_stats_%'"
    );
    
    return intval($deleted);
}

/**
 * 檢查是否啟用統計功能
 * 
 * @return bool
 */
function mpu_is_stats_enabled()
{
    $mpu_opt = mpu_get_option();
    // 預設啟用統計功能
    return !isset($mpu_opt['stats_enabled']) || $mpu_opt['stats_enabled'];
}
