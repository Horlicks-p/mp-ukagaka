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


