<?php

/**
 * 工具函數：字串處理、過濾器等
 * 
 * @package MP_Ukagaka
 * @subpackage Utility
 */

if (!defined('ABSPATH')) {
    exit();
}

/**
 * 將陣列轉換為字串
 */
function mpu_array2str($arr = [])
{
    $str = "";
    if (!empty($arr)) {
        $n = 0;
        $len = count($arr);
        foreach ($arr as $value) {
            // 使用 PHP_EOL 代替硬編碼的 \n\n 增強跨平台相容性
            $str .= $n++ == $len - 1 ? $value : $value . PHP_EOL . PHP_EOL;
        }
    }
    return $str;
}

/**
 * 【★ 修正】將字串轉換為陣列 (使用更穩健的 explode)
 * 舊版使用 preg_split 容易在長字串時失敗
 */
function mpu_str2array($str = "")
{
    $arr = [];
    if (is_string($str) && !empty($str)) {
        // 1. 為了安全起見，先移除 stripslashes，因為 sanitize_textarea_field 已經處理過
        // $str = stripslashes($str); 

        // 2. 將所有換行符 (CRLF, CR) 統一標準化為 LF (\n)
        $normalized_str = str_replace(["\r\n", "\r"], "\n", $str);

        // 3. 使用 explode (比 preg_split 更快更安全)
        $lines = explode("\n", $normalized_str);

        // 4. 迴圈並過濾空行
        foreach ($lines as $value) {
            $trimmed_value = trim($value);
            if ($trimmed_value !== "") {
                $arr[] = $trimmed_value;
            }
        }
    }
    return $arr;
}

/**
 * 輸出過濾器：HTML 輸出
 */
function mpu_output_filter($str)
{
    // HTML を許可せず、純粋なテキストとして出力
    return esc_html($str);
}

/**
 * 輸出過濾器：JavaScript 輸出
 */
function mpu_js_filter($str)
{
    // 使用 esc_js 處理，更安全
    return esc_js($str);
}

/**
 * 輸入過濾器
 * この関数は stripslashes のためだけに残します。
 * 保存時のサニタイズは mpu_handle_options_save で行います。
 */
function mpu_input_filter($str)
{
    // WordPress は自動で stripslashes を行うため、通常この操作は不要
    // しかし、古いロジックがこれを前提にしている可能性があるため、残しておく
    return stripslashes_deep($str);
}

/**
 * HTML 解碼
 */
function mpu_html_decode($str)
{
    $table = [
        "&amp;" => "&",
        "&quot;" => '"',
        "quot;" => '"',
        "&#039;" => "'",
        "&lt;" => "<",
        "&gt;" => ">",
    ];
    return strtr($str, $table);
}

/**
 * 瀏覽器檢測
 */
function mpu_is_browser($target = "")
{
    if (empty($_SERVER["HTTP_USER_AGENT"])) {
        return false;
    }
    $ua = strtolower($_SERVER["HTTP_USER_AGENT"]);
    return strpos($ua, strtolower($target)) !== false;
}

// ========================================
// 安全性強化函數
// ========================================

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
        return new WP_Error('file_not_found', __('找不到指定的文件', 'mp-ukagaka'));
    }

    // 確保文件在允許的目錄內（防止目錄遍歷攻擊）
    if ($real_allowed_dir !== false && strpos($real_path, $real_allowed_dir) !== 0) {
        error_log('MP Ukagaka 安全警告：嘗試讀取不允許的路徑: ' . $file_path);
        return new WP_Error('path_not_allowed', __('不允許讀取該路徑', 'mp-ukagaka'));
    }

    // 2. 檢查文件大小（限制 2MB）
    $file_size = filesize($real_path);
    if ($file_size === false || $file_size > 2 * 1024 * 1024) {
        return new WP_Error('file_too_large', __('文件過大，無法讀取', 'mp-ukagaka'));
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
        return new WP_Error('read_failed', __('無法讀取文件', 'mp-ukagaka'));
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
            return new WP_Error('mkdir_failed', __('無法創建目錄', 'mp-ukagaka'));
        }
    }

    $real_file_dir = realpath($file_dir);

    // 確保目標目錄在允許的範圍內
    if ($real_allowed_dir !== false && $real_file_dir !== false) {
        if (strpos($real_file_dir, $real_allowed_dir) !== 0) {
            error_log('MP Ukagaka 安全警告：嘗試寫入不允許的路徑: ' . $file_path);
            return new WP_Error('path_not_allowed', __('不允許寫入該路徑', 'mp-ukagaka'));
        }
    }

    // 2. 驗證文件名
    $filename = basename($file_path);
    if (!preg_match('/^[a-zA-Z0-9_\-]+\.(json|txt)$/', $filename)) {
        return new WP_Error('invalid_filename', __('不合法的文件名', 'mp-ukagaka'));
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
        return new WP_Error('write_failed', __('無法寫入文件', 'mp-ukagaka'));
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
    $main_file = defined('MPU_MAIN_FILE') ? MPU_MAIN_FILE : dirname(dirname(__FILE__)) . '/mp-ukagaka.php';
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

// ========================================
// API Key 加密/解密函數
// ========================================

/**
 * 獲取加密密鑰
 * 使用 WordPress 的 AUTH_KEY 作為基礎，確保每個站點都有唯一的密鑰
 * 
 * @return string 加密密鑰
 */
function mpu_get_encryption_key()
{
    // 使用 WordPress 的 AUTH_KEY 和一個固定的鹽值
    $base_key = defined('AUTH_KEY') ? AUTH_KEY : 'mpu-default-key-' . get_site_url();
    return hash('sha256', $base_key . 'mpu_api_key_encryption', true);
}

/**
 * 加密 API Key
 * 
 * @param string $api_key 明文 API Key
 * @return string 加密後的 API Key（base64 編碼）
 */
function mpu_encrypt_api_key($api_key)
{
    if (empty($api_key)) {
        return '';
    }

    // 如果已經加密過（以 mpu_enc: 開頭），直接返回
    if (strpos($api_key, 'mpu_enc:') === 0) {
        return $api_key;
    }

    $key = mpu_get_encryption_key();

    // 檢查 OpenSSL 是否可用
    if (function_exists('openssl_encrypt')) {
        $method = 'AES-256-CBC';
        $iv_length = openssl_cipher_iv_length($method);
        $iv = openssl_random_pseudo_bytes($iv_length);

        $encrypted = openssl_encrypt($api_key, $method, $key, OPENSSL_RAW_DATA, $iv);

        if ($encrypted !== false) {
            // 將 IV 和加密數據一起編碼
            return 'mpu_enc:' . base64_encode($iv . $encrypted);
        }
    }

    // OpenSSL 不可用時，使用簡單的混淆（不是真正的加密，但比明文好）
    $obfuscated = base64_encode(strrev($api_key) . '|' . substr(md5($api_key), 0, 8));
    return 'mpu_obf:' . $obfuscated;
}

/**
 * 解密 API Key
 * 
 * @param string $encrypted_key 加密的 API Key
 * @return string 明文 API Key
 */
function mpu_decrypt_api_key($encrypted_key)
{
    if (empty($encrypted_key)) {
        return '';
    }

    // 如果是 OpenSSL 加密的
    if (strpos($encrypted_key, 'mpu_enc:') === 0) {
        $key = mpu_get_encryption_key();
        $data = base64_decode(substr($encrypted_key, 8));

        if ($data !== false && function_exists('openssl_decrypt')) {
            $method = 'AES-256-CBC';
            $iv_length = openssl_cipher_iv_length($method);
            $iv = substr($data, 0, $iv_length);
            $encrypted = substr($data, $iv_length);

            $decrypted = openssl_decrypt($encrypted, $method, $key, OPENSSL_RAW_DATA, $iv);

            if ($decrypted !== false) {
                return $decrypted;
            }
        }

        // 解密失敗，返回空
        error_log('MP Ukagaka: API Key 解密失敗');
        return '';
    }

    // 如果是混淆的
    if (strpos($encrypted_key, 'mpu_obf:') === 0) {
        $data = base64_decode(substr($encrypted_key, 8));
        if ($data !== false) {
            $parts = explode('|', $data);
            if (count($parts) >= 1) {
                return strrev($parts[0]);
            }
        }
        return '';
    }

    // 如果既沒有加密前綴也沒有混淆前綴，則是明文（向後兼容）
    return $encrypted_key;
}

/**
 * 檢查 API Key 是否已加密
 * 
 * @param string $api_key API Key
 * @return bool 是否已加密
 */
function mpu_is_api_key_encrypted($api_key)
{
    return strpos($api_key, 'mpu_enc:') === 0 || strpos($api_key, 'mpu_obf:') === 0;
}

// ========================================
// WordPress 資訊收集函數
// ========================================

/**
 * 獲取 WordPress 網站資訊（包含基本資訊和統計資訊）
 * 使用 transient 緩存，減少資料庫查詢
 * 
 * @return array WordPress 網站資訊陣列
 */
function mpu_get_wordpress_info()
{
    // 使用 transient 緩存，5 分鐘過期（統計資訊不會頻繁變動）
    $cache_key = 'mpu_wordpress_info';
    $cached_info = get_transient($cache_key);

    if ($cached_info !== false) {
        return $cached_info;
    }

    global $wpdb;

    $info = [];

    // ========================================
    // 基本系統資訊
    // ========================================

    // WordPress 版本
    $info['wp_version'] = get_bloginfo('version');

    // 主題資訊
    $theme = wp_get_theme();
    $info['theme_name'] = $theme->get('Name');
    $info['theme_version'] = $theme->get('Version');
    $info['theme_author'] = $theme->get('Author');
    $info['is_child_theme'] = is_child_theme();
    if ($info['is_child_theme']) {
        $info['parent_theme'] = get_template();
    }
    $info['is_block_theme'] = function_exists('wp_is_block_theme') ? wp_is_block_theme() : false;

    // 網站資訊
    $info['site_name'] = get_bloginfo('name');
    $info['site_description'] = get_bloginfo('description');

    // PHP 版本
    $info['php_version'] = phpversion();

    // 啟用外掛資訊
    $active_plugins = get_option('active_plugins', []);
    $info['active_plugins_count'] = count($active_plugins);

    // 獲取啟用外掛的名稱列表
    $info['active_plugins_list'] = [];
    if (!empty($active_plugins)) {
        // 確保 get_plugins() 函數可用（需要載入 admin 檔案）
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        if (function_exists('get_plugins')) {
            // 獲取所有外掛的詳細資訊
            $all_plugins = get_plugins();
            foreach ($active_plugins as $plugin_file) {
                if (isset($all_plugins[$plugin_file])) {
                    $plugin_data = $all_plugins[$plugin_file];
                    // 只儲存外掛名稱（Name）
                    $info['active_plugins_list'][] = $plugin_data['Name'];
                }
            }
            // 按字母順序排序，方便閱讀
            sort($info['active_plugins_list']);
        }
    }

    // 是否為多站點
    $info['is_multisite'] = is_multisite();

    // ========================================
    // 統計資訊（遊戲化用語）
    // ========================================

    // 攻擊回數（文章篇數）
    $post_counts = wp_count_posts('post');
    $info['post_count'] = isset($post_counts->publish) ? (int) $post_counts->publish : 0;

    // 最大傷害（留言數量）
    $comment_counts = wp_count_comments();
    $info['comment_count'] = isset($comment_counts->approved) ? (int) $comment_counts->approved : 0;

    // 習得スキル總數（分類數量）
    $category_count = wp_count_terms([
        'taxonomy' => 'category',
        'hide_empty' => false,
    ]);
    if (is_wp_error($category_count)) {
        // 如果 wp_count_terms 失敗，使用備用方法
        $categories = get_categories(['hide_empty' => false]);
        $info['category_count'] = count($categories);
    } else {
        $info['category_count'] = (int) $category_count;
    }

    // アイテム使用回數（TAG數量）
    $tag_count = wp_count_terms([
        'taxonomy' => 'post_tag',
        'hide_empty' => false,
    ]);
    if (is_wp_error($tag_count)) {
        // 如果 wp_count_terms 失敗，使用備用方法
        $tags = get_tags(['hide_empty' => false]);
        $info['tag_count'] = count($tags);
    } else {
        $info['tag_count'] = (int) $tag_count;
    }

    // 冒險日數（運營日數）
    // 方法1：查詢最早文章的發布日期（使用直接查詢，因為沒有用戶輸入）
    $first_post = $wpdb->get_row(
        "SELECT post_date FROM {$wpdb->posts} 
        WHERE post_status != 'auto-draft' 
        AND post_type = 'post' 
        ORDER BY post_date ASC 
        LIMIT 1"
    );

    if ($first_post && !empty($first_post->post_date)) {
        $first_post_date = strtotime($first_post->post_date);
        $now = time();
        $info['days_operating'] = (int) floor(($now - $first_post_date) / DAY_IN_SECONDS);
    } else {
        // 如果沒有文章，使用 WordPress 安裝日期（如果可用）
        $install_date = get_option('first_install_date');
        if ($install_date) {
            $install_timestamp = strtotime($install_date);
            $now = time();
            $info['days_operating'] = (int) floor(($now - $install_timestamp) / DAY_IN_SECONDS);
        } else {
            // 最後的備用方案：使用現在日期（設為 0 表示未知）
            $info['days_operating'] = 0;
        }
    }

    // ========================================
    // Slimstat 統計數據（如果可用）
    // ========================================
    if (function_exists('mpu_fetch_slimstat_stats')) {
        $slimstat_stats = mpu_fetch_slimstat_stats();
        $info['slimstat_total_visits'] = $slimstat_stats['total_visits'] ?? 0;
        $info['slimstat_top_resources'] = $slimstat_stats['top_resources'] ?? [];
    } else {
        $info['slimstat_total_visits'] = 0;
        $info['slimstat_top_resources'] = [];
    }

    // 緩存結果（5 分鐘）
    set_transient($cache_key, $info, 5 * MINUTE_IN_SECONDS);

    return $info;
}

/**
 * 獲取當前 WordPress 用戶資訊（不緩存，因為每個用戶不同）
 * 
 * @return array 當前用戶資訊陣列
 */
function mpu_get_current_user_info()
{
    $user_info = [];

    // 檢查用戶是否已登入
    $is_logged_in = is_user_logged_in();
    $user_info['is_logged_in'] = $is_logged_in;

    if ($is_logged_in) {
        // 獲取當前用戶對象
        $current_user = wp_get_current_user();

        // 用戶基本資訊
        $user_info['user_id'] = $current_user->ID;
        $user_info['username'] = $current_user->user_login;
        $user_info['display_name'] = $current_user->display_name;
        $user_info['email'] = $current_user->user_email;

        // 用戶角色
        $user_roles = $current_user->roles;
        $user_info['roles'] = $user_roles;
        $user_info['primary_role'] = !empty($user_roles) ? $user_roles[0] : '';

        // 是否是管理員
        $user_info['is_admin'] = current_user_can('manage_options');
        $user_info['is_editor'] = current_user_can('edit_posts');
        $user_info['is_author'] = current_user_can('publish_posts');
    } else {
        // 未登入用戶
        $user_info['user_id'] = 0;
        $user_info['username'] = '';
        $user_info['display_name'] = '';
        $user_info['email'] = '';
        $user_info['roles'] = [];
        $user_info['primary_role'] = '';
        $user_info['is_admin'] = false;
        $user_info['is_editor'] = false;
        $user_info['is_author'] = false;
    }

    return $user_info;
}

/**
 * 渲染提示詞模板，替換 {{變數名}} 為實際值
 * 
 * @param string $template 模板字串，包含 {{變數名}} 佔位符
 * @param array $variables 變數陣列，鍵為變數名（不含 {{}}），值為要替換的內容
 * @return string 替換後的字串
 */
function mpu_render_prompt_template($template, $variables = [])
{
    if (empty($template) || !is_string($template)) {
        return $template;
    }

    if (empty($variables) || !is_array($variables)) {
        return $template;
    }

    // 使用 preg_replace_callback 進行安全替換
    // 只替換純量值（字串、數字），確保安全
    $result = preg_replace_callback(
        '/\{\{(\w+)\}\}/',
        function ($matches) use ($variables) {
            $var_name = $matches[1];

            // 只替換存在的變數
            if (isset($variables[$var_name])) {
                $value = $variables[$var_name];

                // 只處理純量值（字串、數字、布林值）
                if (is_scalar($value)) {
                    // 轉換為字串
                    return (string) $value;
                }
            }

            // 未定義的變數保持原樣（不替換）
            return $matches[0];
        },
        $template
    );

    return $result;
}
