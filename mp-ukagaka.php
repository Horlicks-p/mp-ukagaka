<?php
/*
Plugin Name: MP Ukagaka
Plugin URI: https://www.moelog.com/
Description: Create your own ukagakas. 支援從 dialogs/*.txt 或 *.json 讀取對話。新增 AI 頁面感知功能（Gemini、OpenAI、Claude）。本機 LLM 支援（Ollama，測試階段）。API Key 加密存儲、安全文件操作、可配置打字速度。Claude 風格後台管理介面。
Version: 2.1.5
Author: Ariagle (patched by Horlicks [https://www.moelog.com])
Author URI: https://www.moelog.com/
*/

if (!defined("ABSPATH")) {
    exit();
}

// 定義常量
define("MPU_VERSION", "2.1.5");
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
        'core-functions.php',      // 核心功能（設定管理）
        'utility-functions.php',   // 工具函數
        'ai-functions.php',        // AI 功能（雲端 API：Gemini, OpenAI, Claude）
        'llm-functions.php',       // LLM 功能（本機 LLM：Ollama）
        'ukagaka-functions.php',   // 春菜管理
        'ajax-handlers.php',       // AJAX 處理器（前端和後台都可能使用）
    ];

    // 前端專用模組
    $frontend_modules = [
        'frontend-functions.php',  // 前端功能
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
