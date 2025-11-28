<?php
/*
Plugin Name: MP Ukagaka
Plugin URI: https://www.moelog.com/
Description: Create your own ukagakas. 支援從 dialogs/*.txt 或 *.json 讀取對話。新增 AI 頁面感知功能（Gemini、OpenAI、Claude）。API Key 加密存儲、安全文件操作、可配置打字速度。
Version: 2.1.0
Author: Ariagle (patched by Horlicks [https://www.moelog.com])
Author URI: https://www.moelog.com/
*/

if (!defined("ABSPATH")) {
    exit();
}

// 定義常量
define("MPU_VERSION", "2.1.0");
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
    },
    5
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
 */
function mpu_load_modules() {
    $plugin_dir = plugin_dir_path(__FILE__);
    $includes_dir = $plugin_dir . 'includes';
    
    // 按依賴順序載入模組
    $modules = [
        'core-functions.php',      // 核心功能（設定管理）
        'utility-functions.php',   // 工具函數
        'ai-functions.php',        // AI 功能
        'ukagaka-functions.php',   // 春菜管理
        'ajax-handlers.php',       // AJAX 處理器
        'frontend-functions.php',  // 前端功能
        'admin-functions.php',     // 後台功能
    ];
    
    foreach ($modules as $module) {
        $file_path = $includes_dir . '/' . $module;
        if (file_exists($file_path)) {
            require_once $file_path;
        } else {
            // 如果模組文件不存在，記錄錯誤但不要阻止插件運行
            error_log("MP Ukagaka: 模組文件不存在: {$file_path}");
        }
    }
}

// 載入模組（在 WordPress 完全載入後）
add_action('plugins_loaded', 'mpu_load_modules', 1);

/**
 * 向下相容：保留 $mpu_opt 全域變數
 * 需要在核心功能載入後才能使用
 */
add_action('plugins_loaded', function() {
    global $mpu_opt;
    if (function_exists('mpu_get_option')) {
    $mpu_opt = mpu_get_option();
    }
}, 10);
