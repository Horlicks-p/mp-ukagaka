<?php

/**
 * File and dialog directory helper functions.
 *
 * @package MP_Ukagaka
 * @subpackage Utility
 */

if (!defined('ABSPATH')) {
    exit();
}

// ========================================
// 安全性強化函數
// ========================================

/**
 * 檢查路徑是否位於允許的目錄內（防止目錄遍歷）
 *
 * @param string $path 檔案或目錄路徑
 * @param string $allowed_dir 允許的根目錄
 * @return bool
 */
function mpu_is_path_within_allowed_dir($path, $allowed_dir)
{
    $normalized_path = wp_normalize_path($path);
    $normalized_allowed_dir = trailingslashit(wp_normalize_path($allowed_dir));

    return strpos($normalized_path, $normalized_allowed_dir) === 0;
}

/**
 * 安全文件讀取
 * 使用 WordPress Filesystem API 或帶有安全檢查的原生函數
 * 
 * @param string $file_path 文件路徑
 * @return string|WP_Error 文件內容或錯誤
 */
function mpu_secure_file_read($file_path)
{
    // 1. 驗證文件路徑在允許的範圍內
    $allowed_dir = mpu_get_dialogs_dir();
    $real_path = realpath($file_path);
    $real_allowed_dir = realpath($allowed_dir);

    // 如果文件不存在，realpath 會返回 false
    if ($real_path === false) {
        return new WP_Error('file_not_found', __('指定されたファイルが見つかりません', 'mp-ukagaka'));
    }

    // 確保文件在允許的目錄內（防止目錄遍歷攻擊）
    if ($real_allowed_dir !== false && !mpu_is_path_within_allowed_dir($real_path, $real_allowed_dir)) {
        mpu_log_warning('安全警告：嘗試讀取不允許的路徑: ' . $file_path);
        return new WP_Error('path_not_allowed', __('このパスへのアクセスは許可されていません', 'mp-ukagaka'));
    }

    // 2. 檢查文件大小（限制 2MB）
    $file_size = filesize($real_path);
    if ($file_size === false || $file_size > 2 * 1024 * 1024) {
        return new WP_Error('file_too_large', __('ファイルが大きすぎて読み込めません', 'mp-ukagaka'));
    }

    // 3. 嘗試使用 WordPress Filesystem API
    global $wp_filesystem;

    if (empty($wp_filesystem)) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        WP_Filesystem();
    }

    if ($wp_filesystem && $wp_filesystem->exists($real_path)) {
        $content = $wp_filesystem->get_contents($real_path);
        if ($content !== false) {
            return $content;
        }
    }

    // 4. 備用：使用原生 file_get_contents（已通過安全檢查）
    $content = @file_get_contents($real_path);
    if ($content === false) {
        return new WP_Error('read_failed', __('ファイルを読み込めません', 'mp-ukagaka'));
    }

    return $content;
}

/**
 * 安全文件寫入
 * 使用 WordPress Filesystem API 或帶有安全檢查的原生函數
 * 
 * @param string $file_path 文件路徑
 * @param string $content 文件內容
 * @return bool|WP_Error 成功返回 true，失敗返回 WP_Error
 */
function mpu_secure_file_write($file_path, $content)
{
    // 1. 驗證文件路徑在允許的範圍內
    $allowed_dir = mpu_get_dialogs_dir();
    $file_dir = dirname($file_path);
    $real_allowed_dir = realpath($allowed_dir);

    // 確保目錄存在
    if (!file_exists($file_dir)) {
        if (!wp_mkdir_p($file_dir)) {
            return new WP_Error('mkdir_failed', __('ディレクトリを作成できません', 'mp-ukagaka'));
        }
    }

    $real_file_dir = realpath($file_dir);

    // 確保目標目錄在允許的範圍內
    if ($real_allowed_dir !== false && $real_file_dir !== false) {
        if (!mpu_is_path_within_allowed_dir($real_file_dir, $real_allowed_dir)) {
            mpu_log_warning('安全警告：嘗試寫入不允許的路徑: ' . $file_path);
            return new WP_Error('path_not_allowed', __('このパスへの書き込みは許可されていません', 'mp-ukagaka'));
        }
    }

    // 2. 驗證文件名
    $filename = basename($file_path);
    if (!preg_match('/^[a-zA-Z0-9_\-]+\.(json|txt)$/', $filename)) {
        return new WP_Error('invalid_filename', __('不正なファイル名です', 'mp-ukagaka'));
    }

    // 3. 嘗試使用 WordPress Filesystem API
    global $wp_filesystem;

    if (empty($wp_filesystem)) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        WP_Filesystem();
    }

    if ($wp_filesystem) {
        $result = $wp_filesystem->put_contents($file_path, $content, FS_CHMOD_FILE);
        if ($result !== false) {
            return true;
        }
    }

    // 4. 備用：使用原生 file_put_contents（已通過安全檢查）
    $result = @file_put_contents($file_path, $content);
    if ($result === false) {
        return new WP_Error('write_failed', __('ファイルに書き込めません', 'mp-ukagaka'));
    }

    return true;
}

/**
 * 獲取對話文件目錄路徑
 * 
 * @return string 目錄路徑
 */
function mpu_get_dialogs_dir()
{
    // 使用已定義的常量獲取主文件路徑
    $main_file = defined('MPU_MAIN_FILE') ? MPU_MAIN_FILE : dirname(dirname(dirname(__FILE__))) . '/mp-ukagaka.php';
    return plugin_dir_path($main_file) . 'dialogs';
}

/**
 * 確保對話目錄存在
 * 
 * @return bool 成功返回 true
 */
function mpu_ensure_dialogs_dir()
{
    $dialog_dir = mpu_get_dialogs_dir();
    if (!file_exists($dialog_dir)) {
        return wp_mkdir_p($dialog_dir);
    }
    return true;
}
