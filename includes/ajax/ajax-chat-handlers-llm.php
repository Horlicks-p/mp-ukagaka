<?php
/**
 * LLM 相關 AJAX 處理器：對話相關
 * 
 * 此檔案為載入器，實際處理函數已拆分至 chat/ 子目錄
 * 
 * @package MP_Ukagaka
 * @subpackage AJAX
 */

if (!defined('ABSPATH')) {
    exit();
}

// 載入對話相關處理器
require_once __DIR__ . '/chat/context-handler.php';   // 頁面感知 AI 上下文對話
require_once __DIR__ . '/chat/greet-handler.php';     // 首次訪客問候
require_once __DIR__ . '/chat/user-chat-handler.php'; // 用戶互動對話
