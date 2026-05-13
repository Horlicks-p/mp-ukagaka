<?php

/**
 * REST Controller Bootstrap
 *
 * OO REST Controller 的集中註冊入口。
 * 所有啟用的 Controller 皆在此管理，避免 add_action('rest_api_init', ...) 分散各檔。
 *
 * 遷移 SOP：
 *  1. 建立對應 Controller 類別（class-mpu-rest-*.php），確認路由行為與舊 procedural 完全一致。
 *  2. 從 mp-ukagaka.php $core_modules 移除對應的舊 procedural 檔案（單軌切換，不重複註冊）。
 *  3. 將 Controller 類別名稱加入下方 $controllers 陣列。
 *  4. 驗證後再決定是否實體刪除舊檔。
 *
 * @package MP_Ukagaka
 * @subpackage REST
 */

if (!defined('ABSPATH')) {
    exit();
}

// 載入基礎類別（必須最先）
require_once __DIR__ . '/class-mpu-rest-base.php';

// Chat 輔助服務（history 解析、checksum 寫入/驗證）
require_once dirname(__DIR__) . '/chat/class-mpu-chat-history-service.php';

/**
 * 已啟用的 OO REST Controller 清單。
 *
 * Phase 1：陣列為空，機制就位但不接管任何端點。
 * 後續各 Phase：停用對應舊 procedural 檔案後，在此加入 Controller 類別名稱。
 *
 * 格式：
 *   Controller 類別名稱（字串）=> 對應的類別檔案路徑（相對於 __DIR__）
 *
 * 範例（Phase 2 啟用 Test Controller 時取消注釋）：
 *   'MPU_REST_Test' => 'class-mpu-rest-test.php',
 */
$mpu_oo_rest_controllers = [
    // Phase 2 — Test Controller（停用 rest-test.php 後啟用）
    'MPU_REST_Test' => 'class-mpu-rest-test.php',

    // Phase 3 — Touch Controller（停用 rest-touch.php 後啟用）
    'MPU_REST_Touch' => 'class-mpu-rest-touch.php',

    // Phase 4 — Chat Controller（停用 rest-chat.php + chat/*.php 後啟用）
    'MPU_REST_Chat' => 'class-mpu-rest-chat.php',

    // Phase 5 — Ghost Controller（停用 rest-init.php + rest-core.php 後啟用，與 Dialog 同步切換）
    'MPU_REST_Ghost' => 'class-mpu-rest-ghost.php',

    // Phase 5 — Dialog Controller（停用 rest-core.php 後啟用，與 Ghost 同步切換）
    'MPU_REST_Dialog' => 'class-mpu-rest-dialog.php',

    // User Memory — 管理員記憶萃取端點
    'MPU_REST_Memory' => 'class-mpu-rest-memory.php',
];

// 集中掛載：逐一載入 Controller 並將 register_routes() 掛到 rest_api_init
foreach ($mpu_oo_rest_controllers as $class => $file) {
    $file_path = __DIR__ . '/' . $file;
    if (!file_exists($file_path)) {
        error_log("MP Ukagaka: REST Controller 檔案不存在: {$file_path}");
        continue;
    }
    require_once $file_path;
    if (!class_exists($class)) {
        error_log("MP Ukagaka: REST Controller 類別不存在: {$class}");
        continue;
    }
    // 防禦性護欄：確保只實例化繼承自 MPU_REST_Base 的類別
    if (!is_subclass_of($class, 'MPU_REST_Base')) {
        error_log("MP Ukagaka: REST Controller 類別未繼承 MPU_REST_Base，略過: {$class}");
        continue;
    }
    $controller = new $class();
    add_action('rest_api_init', [$controller, 'register_routes']);
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log("MP Ukagaka: REST Controller 已掛載: {$class}");
    }
}
