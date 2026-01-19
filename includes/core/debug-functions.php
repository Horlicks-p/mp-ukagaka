<?php
/**
 * 統一日誌系統
 * 
 * 提供統一的日誌記錄介面，支援 UTF-8 字符正確顯示。
 * 所有日誌功能應使用此模組中的函數，而非直接調用 error_log()。
 * 
 * @package MP_Ukagaka
 * @subpackage Core
 * @since 2.5.7
 */

if (!defined('ABSPATH')) {
    exit();
}

/**
 * 統一日誌函數
 * 
 * 支援 UTF-8 字符正確顯示，自動添加前綴。
 * 僅在 WP_DEBUG 啟用時記錄日誌。
 * 
 * @param mixed $message 日誌訊息（字串、陣列、物件皆可）
 * @param string $level 日誌級別：debug, info, warning, error
 * @return void
 */
function mpu_debug_log($message, $level = 'debug')
{
    // 僅在 DEBUG 模式下記錄
    if (!defined('WP_DEBUG') || !WP_DEBUG) {
        return;
    }

    // 支援陣列/物件輸出
    if (!is_string($message)) {
        $message = print_r($message, true);
    }

    // Windows 環境 UTF-8 修復
    // 確保多語言字符（中文、日文）正確顯示在日誌中
    if (function_exists('mb_convert_encoding') && PHP_OS_FAMILY === 'Windows') {
        // 嘗試修復可能的編碼問題
        $message = mb_convert_encoding($message, 'UTF-8', 'auto');
    }

    // 構建前綴
    $prefix = '[MP Ukagaka]';
    if ($level !== 'debug') {
        $prefix .= ' [' . strtoupper($level) . ']';
    }

    error_log($prefix . ' ' . $message);
}

/**
 * 記錄資訊級別日誌
 * 
 * @param mixed $message 日誌訊息
 * @return void
 */
function mpu_log_info($message)
{
    mpu_debug_log($message, 'info');
}

/**
 * 記錄警告級別日誌
 * 
 * @param mixed $message 日誌訊息
 * @return void
 */
function mpu_log_warning($message)
{
    mpu_debug_log($message, 'warning');
}

/**
 * 記錄錯誤級別日誌
 * 
 * @param mixed $message 日誌訊息
 * @return void
 */
function mpu_log_error($message)
{
    mpu_debug_log($message, 'error');
}

/**
 * 記錄 API 調用日誌（詳細版本）
 * 
 * 用於記錄 AI API 調用的詳細資訊，包括 System Prompt 和 User Prompt。
 * 有助於除錯 LLM 相關問題。
 * 
 * @param string $provider AI 提供商名稱
 * @param string $system_prompt System Prompt 內容
 * @param string $user_prompt User Prompt 內容
 * @param string $language 語言設定
 * @return void
 */
function mpu_log_api_call($provider, $system_prompt, $user_prompt, $language)
{
    if (!defined('WP_DEBUG') || !WP_DEBUG) {
        return;
    }

    mpu_debug_log('=== AI API 調用 ===');
    mpu_debug_log('提供商: ' . $provider);
    mpu_debug_log('語言: ' . $language);
    mpu_debug_log('--- System Prompt ---');
    mpu_debug_log($system_prompt);
    mpu_debug_log('--- User Prompt ---');
    mpu_debug_log($user_prompt);
    mpu_debug_log('=== End AI API 調用 ===');
}

/**
 * 記錄 System Prompt 統計資訊
 * 
 * 用於估算 Token 消耗，有助於優化 Prompt 設計。
 * 
 * @param string $system_prompt System Prompt 內容
 * @return void
 */
function mpu_log_prompt_stats($system_prompt)
{
    if (!defined('WP_DEBUG') || !WP_DEBUG) {
        return;
    }

    $char_count = mb_strlen($system_prompt, 'UTF-8');

    // 計算中文字符數量
    preg_match_all('/[\x{4e00}-\x{9fa5}]/u', $system_prompt, $chinese_chars);
    $chinese_count = count($chinese_chars[0]);

    // 計算英文字符數量
    $english_count = $char_count - $chinese_count;

    // 估算 Token 數（中文約 2 字符/Token，英文約 4 字符/Token）
    $estimated_tokens = ($chinese_count / 2) + ($english_count / 4);

    mpu_debug_log('=== System Prompt 統計 ===');
    mpu_debug_log('字符長度: ' . $char_count);
    mpu_debug_log('中文字符: ' . $chinese_count);
    mpu_debug_log('估算 Token 數: ' . (int)ceil($estimated_tokens));
    mpu_debug_log('=== End 統計 ===');
}
