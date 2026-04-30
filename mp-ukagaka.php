<?php
/*
Plugin Name: MP Ukagaka
Plugin URI: https://www.moelog.com/
Description: Create your own ukagakas. 支援從 dialogs/*.txt 或 *.json 讀取對話。新增 AI 頁面感知功能（Gemini、OpenAI、Claude）。本機 LLM 支援（Ollama，測試階段）。API Key 加密存儲、安全文件操作、可配置打字速度。Claude 風格後台管理介面。JSON 人格系統。
Version: 2.14.1
Author: Ariagle (patched by Horlicks [https://www.moelog.com])
Author URI: https://www.moelog.com/
*/

if (!defined("ABSPATH")) {
    exit();
}

// 定義常量
define("MPU_VERSION", "2.14.1");
define("MPU_MAIN_FILE", __FILE__);

/**
 * 語系載入
 */
add_action(
    "init",
    function () {
        load_plugin_textdomain(
            "mp-ukagaka",
            false,
            dirname(plugin_basename(__FILE__)) . "/languages"
        );
    }
);

/**
 * 啟用時建立目錄
 */
register_activation_hook(__FILE__, function () {
    $dialog_dir = plugin_dir_path(__FILE__) . "dialogs";
    if (!file_exists($dialog_dir)) {
        wp_mkdir_p($dialog_dir);
    }
    // 排程統計清理事件
    if (!wp_next_scheduled('mpu_daily_stats_cleanup')) {
        wp_schedule_event(time(), 'daily', 'mpu_daily_stats_cleanup');
    }
});

/**
 * 停用時清除排程
 */
register_deactivation_hook(__FILE__, function () {
    wp_clear_scheduled_hook('mpu_daily_stats_cleanup');
});

/**
 * 載入所有模組文件
 * 根據上下文（前端/後台）條件載入模組以優化效能
 */
function mpu_load_modules()
{
    $plugin_dir = plugin_dir_path(__FILE__);
    $includes_dir = $plugin_dir . 'includes';

    // 核心模組：前端和後台都需要
    $core_modules = [
        'core/debug-functions.php',     // 日誌系統（必須最先載入）
        'core/core-functions.php',      // 核心功能（設定管理）
        'core/utility-functions.php',   // 工具函數
        'personality/personality-loader.php',  // 人格系統（JSON 載入器，需在其他 personality 模組之前載入）
        'personality/personality-prompts.php', // 人格提示詞模組（動態提示詞、變數替換）
        'personality/personality-decorations.php', // 裝飾物系統
        'personality/personality-emoji.php',   // 表情系統
        'stats/stats-collector.php',   // 統計收集器（需在 ai-functions.php 之前載入）
        'stats/stats-analyzer.php',    // 統計分析器
        'llm/api-cache.php',           // API 快取系統（需在 ai-functions.php 之前載入）
        'llm/provider-helpers.php',    // Provider 共用 Helper（JSON encode、tool result 格式化）
        'llm/chat-integrity.php',      // 對話歷史完整性 checksum（防前端篡改）
        'llm/request-state.php',       // 請求期間狀態重置/追蹤（MCP tool flag、未來 SSE buffer）
        'llm/tool-loop-guard.php',     // 工具呼叫迴圈防護機制
        'llm/streaming-helpers.php',   // SSE 串流 Helper（ob 清理、事件發送）
        'llm/provider-stream-http.php', // 低階 cURL 串流 HTTP Client
        'llm/providers/bootstrap.php', // AI Providers 工廠模式與類別（需在 ai-functions.php 之前載入）
        'llm/ai-functions.php',        // AI 功能（雲端 API：Gemini, OpenAI, Claude）
        'llm/prompt-categories.php',   // Prompt 類別指令管理（需在 llm-functions.php 之前載入）
        'llm/llm-slimstat.php',       // LLM Slimstat 整合（需在 llm-context-builder.php 之前載入）
        'llm/llm-context-builder.php', // LLM 上下文建構（需在 llm-functions.php 之前載入）
        'llm/weather-functions.php',   // 天氣功能（Open-Meteo API）
        'llm/diary-functions.php',     // 自動日記功能（フリーレン手記）
        'llm/llm-functions.php',       // LLM 功能（本機 LLM：Ollama）
        'personality/emoji-mapper.php',        // 表情映射與情緒分析（需在 AJAX 處理器之前載入）
        'core/ukagaka-functions.php',   // 偽春菜管理
        'rest/bootstrap.php',           // REST OO Controller 註冊入口（含 Base Class 載入）
        'ajax/chat-api-handlers.php',   // 對話模式 API 處理器（多輪對話）
        'integrations/akismet-integration.php',    // Akismet 垃圾留言連動
        'integrations/turnstile-integration.php',  // Turnstile 垃圾留言連動
        'integrations/abilities-integration.php',  // Abilities API Integration (formerly MCP)
        'integrations/bot-blocker-integration.php', // Bot 防護（整合自 Moelog Bot Blocker）
    ];

    // 前端專用模組
    $frontend_modules = [
        'core/frontend-functions.php',  // 前端功能
    ];

    // 後台專用模組
    $admin_modules = [
        'admin-functions.php',     // 後台功能
    ];

    // 載入核心模組
    foreach ($core_modules as $module) {
        $file_path = $includes_dir . '/' . $module;
        if (file_exists($file_path)) {
            require_once $file_path;
        } else {
            error_log("MP Ukagaka: 模組文件不存在: {$file_path}");
        }
    }

    // 載入前端模組（非後台環境）
    if (!is_admin()) {
        foreach ($frontend_modules as $module) {
            $file_path = $includes_dir . '/' . $module;
            if (file_exists($file_path)) {
                require_once $file_path;
            } else {
                error_log("MP Ukagaka: 模組文件不存在: {$file_path}");
            }
        }
    }

    // 載入後台模組（僅在後台環境）
    if (is_admin()) {
        foreach ($admin_modules as $module) {
            $file_path = $includes_dir . '/' . $module;
            if (file_exists($file_path)) {
                require_once $file_path;
            } else {
                error_log("MP Ukagaka: 模組文件不存在: {$file_path}");
            }
        }
    }
}

// 載入模組（在 WordPress 完全載入後）
add_action('plugins_loaded', 'mpu_load_modules', 1);

/**
 * 向下相容：保留 $mpu_opt 全域變數
 * 需要在核心功能載入後才能使用
 */
add_action('plugins_loaded', function () {
    global $mpu_opt;
    if (function_exists('mpu_get_option')) {
        $mpu_opt = mpu_get_option();
    }
}, 10);
