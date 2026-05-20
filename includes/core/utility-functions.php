<?php

/**
 * Core utility constants.
 *
 * @package MP_Ukagaka
 * @subpackage Utility
 */

if (!defined('ABSPATH')) {
    exit();
}

// ========================================
// LLM 工具呼叫回合上限
// ========================================
if (!defined('MPU_MAX_TOOL_TURNS')) {
    define('MPU_MAX_TOOL_TURNS', 5); // 每次 AI 呼叫最多允許的工具呼叫回合數（tool-call turns limit）
}

if (!defined('MPU_MAX_TOOL_REPEAT_SAME_CALL')) {
    define('MPU_MAX_TOOL_REPEAT_SAME_CALL', 2); // 同一工具與參數連續重複呼叫的門檻（觸發 loop 中止）
}

// ========================================
// API 快取時間常數
// ========================================
if (!defined('MPU_CACHE_WEATHER')) {
    define('MPU_CACHE_WEATHER', 30 * MINUTE_IN_SECONDS);   // 天氣：30 分鐘
}
if (!defined('MPU_CACHE_EXCHANGE')) {
    define('MPU_CACHE_EXCHANGE', DAY_IN_SECONDS);          // 匯率：24 小時
}
if (!defined('MPU_CACHE_DEFAULT')) {
    define('MPU_CACHE_DEFAULT', HOUR_IN_SECONDS);          // 預設：1 小時
}
