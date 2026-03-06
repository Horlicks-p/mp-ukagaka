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

/**
 * 將陣列轉換為字串
 */
function mpu_array2str($arr = [])
{
    if (empty($arr)) {
        return "";
    }

    // 使用 PHP_EOL 代替硬編碼的 \n\n 增強跨平台相容性
    return implode(PHP_EOL . PHP_EOL, $arr);
}

/**
 * 【★ 修正】將字串轉換為陣列 (使用更穩健的 explode)
 * 舊版使用 preg_split 容易在長字串時失敗
 */
function mpu_str2array($str = "")
{
    $arr = [];
    if (is_string($str) && !empty($str)) {
        $normalized_str = str_replace(["\r\n", "\r"], "\n", $str);
        $lines = explode("\n", $normalized_str);

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
    return esc_html($str);
}

/**
 * 輸出過濾器：JavaScript 輸出
 */
function mpu_js_filter($str)
{
    return esc_js($str);
}

/**
 * 輸入過濾器
 * この関数は stripslashes のためだけに残します。
 * 保存時のサニタイズは mpu_handle_options_save で行います。
 */
function mpu_input_filter($str)
{
    return stripslashes_deep($str);
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
        return new WP_Error('file_not_found', __('找不到指定的文件', 'mp-ukagaka'));
    }

    // 確保文件在允許的目錄內（防止目錄遍歷攻擊）
    if ($real_allowed_dir !== false && !mpu_is_path_within_allowed_dir($real_path, $real_allowed_dir)) {
        mpu_log_warning('安全警告：嘗試讀取不允許的路徑: ' . $file_path);
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
        if (!mpu_is_path_within_allowed_dir($real_file_dir, $real_allowed_dir)) {
            mpu_log_warning('安全警告：嘗試寫入不允許的路徑: ' . $file_path);
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
        mpu_log_error('API Key 解密失敗');
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

/**
 * 獲取指定 AI 提供商的解密後 API Key
 * 統一處理各提供商的 API Key 獲取邏輯，減少代碼重複
 * 
 * @param string $provider AI 提供商名稱（gemini, openai, claude, ollama）
 * @param array|null $mpu_opt 選項陣列，如果為 null 則自動獲取
 * @return string 解密後的 API Key，或空字串（Ollama 不需要 API Key）
 */
function mpu_get_provider_api_key($provider, $mpu_opt = null)
{
    if ($mpu_opt === null) {
        $mpu_opt = mpu_get_option();
    }

    // Ollama 不需要 API Key
    if ($provider === 'ollama') {
        return '';
    }

    $api_key_encrypted = '';

    switch ($provider) {
        case 'openai':
            // 向後兼容：優先使用新設定鍵，否則使用舊設定鍵
            $api_key_encrypted = !empty($mpu_opt['llm_openai_api_key'])
                ? $mpu_opt['llm_openai_api_key']
                : (!empty($mpu_opt['openai_api_key']) ? $mpu_opt['openai_api_key'] : '');
            break;
        case 'claude':
            $api_key_encrypted = !empty($mpu_opt['llm_claude_api_key'])
                ? $mpu_opt['llm_claude_api_key']
                : (!empty($mpu_opt['claude_api_key']) ? $mpu_opt['claude_api_key'] : '');
            break;
        case 'gemini':
        default:
            $api_key_encrypted = !empty($mpu_opt['llm_gemini_api_key'])
                ? $mpu_opt['llm_gemini_api_key']
                : (!empty($mpu_opt['ai_api_key']) ? $mpu_opt['ai_api_key'] : '');
            break;
    }

    // 解密並返回 API Key
    return mpu_decrypt_api_key($api_key_encrypted);
}

/**
 * 獲取當前啟用的 AI 提供商名稱
 * 
 * @param array|null $mpu_opt 選項陣列，如果為 null 則自動獲取
 * @return string AI 提供商名稱（gemini, openai, claude, ollama）
 */
function mpu_get_current_provider($mpu_opt = null)
{
    if ($mpu_opt === null) {
        $mpu_opt = mpu_get_option();
    }

    // 向後兼容：優先使用 llm_provider，否則使用 ai_provider
    return $mpu_opt['llm_provider'] ?? $mpu_opt['ai_provider'] ?? 'gemini';
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
    $info['spam_count'] = isset($comment_counts->spam) ? (int) $comment_counts->spam : 0;

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
    // 支援變數名包含字母、數字、下劃線和連字符（如 {{ukagaka-name}}）
    $result = preg_replace_callback(
        '/\{\{([\w\-]+)\}\}/',
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

            // 未定義的變數移除，避免 template 語法直接送進 LLM
            return '';
        },
        $template
    );

    return $result;
}

// ========================================
// 國碼轉換
// ========================================

/**
 * 將 ISO 3166-1 alpha-2 國碼轉換為日語國名
 *
 * 優先使用 PHP intl 擴展（Locale::getDisplayRegion），
 * 若不可用則使用內建的常見國碼對照表。
 *
 * @param string $code ISO 3166-1 alpha-2 國碼（如 "JP"、"TW"）
 * @param string $locale 顯示語言（預設 'ja'）
 * @return string 國名（如 "日本"、"台湾"），無法轉換時原樣返回國碼
 */
function mpu_country_code_to_name($code, $locale = 'ja')
{
    if (empty($code)) {
        return '';
    }
    $code = strtoupper(trim($code));

    // 優先使用 intl 擴展
    if (class_exists('Locale')) {
        $name = Locale::getDisplayRegion('-' . $code, $locale);
        if (!empty($name) && $name !== '-' . $code && $name !== $code) {
            return $name;
        }
    }

    // Fallback：常見國碼對照（日語）
    $map = [
        'JP' => '日本', 'TW' => '台湾', 'CN' => '中国', 'HK' => '香港',
        'KR' => '韓国', 'US' => 'アメリカ', 'GB' => 'イギリス', 'DE' => 'ドイツ',
        'FR' => 'フランス', 'CA' => 'カナダ', 'AU' => 'オーストラリア',
        'SG' => 'シンガポール', 'TH' => 'タイ', 'VN' => 'ベトナム',
        'PH' => 'フィリピン', 'MY' => 'マレーシア', 'ID' => 'インドネシア',
        'IN' => 'インド', 'RU' => 'ロシア', 'BR' => 'ブラジル',
        'IT' => 'イタリア', 'ES' => 'スペイン', 'NL' => 'オランダ',
        'SE' => 'スウェーデン', 'NZ' => 'ニュージーランド', 'MX' => 'メキシコ',
    ];

    return isset($map[$code]) ? $map[$code] : $code;
}

// ========================================
// 網路工具函數
// ========================================

/**
 * 獲取客戶端真實 IP 地址
 * 
 * 支援反向代理（Cloudflare、Nginx、HAProxy 等）環境下獲取真實 IP。
 * 優先順序：
 * 1. CF-Connecting-IP（Cloudflare 專用）
 * 2. X-Forwarded-For（通用反向代理 header）
 * 3. X-Real-IP（Nginx 常用）
 * 4. REMOTE_ADDR（直連客戶端）
 * 
 * @return string 客戶端 IP 地址
 */
function mpu_get_client_ip()
{
    $ip = '';

    // 1. Cloudflare 專用 header（最可靠）
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
    }
    // 2. X-Forwarded-For（通用反向代理）
    // 格式可能是：client, proxy1, proxy2
    elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip_list = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($ip_list[0]); // 取第一個（原始客戶端 IP）
    }
    // 3. X-Real-IP（Nginx 等）
    elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
        $ip = $_SERVER['HTTP_X_REAL_IP'];
    }
    // 4. 直連客戶端
    elseif (!empty($_SERVER['REMOTE_ADDR'])) {
        $ip = $_SERVER['REMOTE_ADDR'];
    }

    // 驗證 IP 格式有效性
    $ip = sanitize_text_field($ip);

    // 基本 IP 格式驗證（IPv4 或 IPv6）
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        // 如果無法驗證，返回空字串或 REMOTE_ADDR 作為後備
        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : '';
    }

    return $ip;
}

// ========================================
// 外部 API 請求函數
// ========================================

/**
 * 通用外部 API 請求函數（含快取）
 * 
 * 封裝 wp_remote_get 並提供統一的快取管理、錯誤處理和日誌記錄。
 * 
 * @param string $cache_key 快取鍵（建議格式：mpu_{service}_{identifier}）
 * @param string $url API 端點 URL
 * @param int $cache_duration 快取時間（秒），使用 MPU_CACHE_* 常數
 * @param array $options 額外選項：
 *   - 'headers' => array         額外 HTTP headers
 *   - 'timeout' => int           請求超時時間（預設 10 秒）
 *   - 'user_agent' => string     自訂 User-Agent
 *   - 'log_prefix' => string     日誌前綴（預設 'MP Ukagaka API'）
 *   - 'parse_json' => bool       是否解析 JSON（預設 true）
 * @return array|string|null API 回應資料（解析後的陣列或原始字串），失敗時返回 null
 */
function mpu_fetch_external_api($cache_key, $url, $cache_duration = MPU_CACHE_DEFAULT, $options = [])
{
    // 預設選項
    $defaults = [
        'headers' => [],
        'timeout' => 10,
        'user_agent' => 'WordPress/MP-Ukagaka-Plugin',
        'log_prefix' => 'MP Ukagaka API',
        'parse_json' => true,
    ];
    $options = array_merge($defaults, $options);

    // 檢查快取
    $cached = get_transient($cache_key);
    if ($cached !== false) {
        return $cached;
    }

    // 建構請求 headers
    $headers = array_merge([
        'Accept' => 'application/json',
        'User-Agent' => $options['user_agent'],
    ], $options['headers']);

    // 發送請求
    $response = wp_remote_get($url, [
        'timeout' => $options['timeout'],
        'headers' => $headers,
    ]);

    // 錯誤處理
    if (is_wp_error($response)) {
        mpu_log_error($options['log_prefix'] . ': Request failed - ' . $response->get_error_message());
        return null;
    }

    $status_code = wp_remote_retrieve_response_code($response);

    // 取得回應內容
    $body = wp_remote_retrieve_body($response);

    // 檢查狀態碼（在解析 JSON 前，避免不必要的解析）
    if ($status_code !== 200) {
        mpu_log_warning($options['log_prefix'] . ': API returned status ' . $status_code);

        // 如果是 JSON 錯誤響應，嘗試解析以便調用者檢查
        if ($options['parse_json'] && !empty($body)) {
            $decoded = json_decode($body, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded; // 返回錯誤響應以便調用者檢查
            }
        }

        return null;
    }

    // 解析 JSON（如果啟用）
    $result = $body;
    if ($options['parse_json']) {
        // 檢查空響應
        if (empty($body)) {
            mpu_log_warning($options['log_prefix'] . ': Empty response body');
            return null;
        }

        $decoded = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            mpu_log_error($options['log_prefix'] . ': JSON parse error - ' . json_last_error_msg());
            return null;
        }

        // 檢查解析結果是否為 null（空字串會被解析為 null）
        if ($decoded === null) {
            mpu_log_warning($options['log_prefix'] . ': JSON decoded to null (possibly empty string)');
            return null;
        }

        $result = $decoded;
    }

    // 寫入快取
    if ($cache_duration > 0) {
        set_transient($cache_key, $result, $cache_duration);
    }

    return $result;
}

/**
 * 清除指定快取
 * 
 * @param string $cache_key 快取鍵
 * @return bool 是否成功清除
 */
function mpu_clear_api_cache($cache_key)
{
    return delete_transient($cache_key);
}

// ========================================
// Rate Limiting 函數
// ========================================

/**
 * 速率限制檢查
 * 
 * 檢查指定動作的請求次數是否超過限制。
 * 使用 WordPress Transient API 儲存計數器。
 * 
 * @since 2.5.7
 * @param string $action 動作標識（如 'chat_context', 'user_chat'）
 * @param int $max_requests 時間週期內最大請求數
 * @param int $period 時間週期（秒）
 * @return array [
 *   'allowed' => bool,      // 是否允許此次請求
 *   'remaining' => int,     // 剩餘可用請求數
 *   'reset_in' => int,      // 重置倒計時（秒）
 *   'count' => int,         // 當前已使用次數
 * ]
 */
function mpu_check_rate_limit($action, $max_requests = 10, $period = 60)
{
    $ip = mpu_get_client_ip();
    $transient_key = 'mpu_rl_' . sanitize_key($action) . '_' . md5($ip);
    
    $data = get_transient($transient_key);
    if ($data === false) {
        $data = ['count' => 0, 'first_request' => time()];
    }
    
    $elapsed = time() - $data['first_request'];
    
    // 如果超過週期，重置計數
    if ($elapsed >= $period) {
        $data = ['count' => 0, 'first_request' => time()];
        $elapsed = 0;
    }
    
    // 增加計數
    $data['count']++;
    
    // 更新 transient
    set_transient($transient_key, $data, $period);
    
    return [
        'allowed' => $data['count'] <= $max_requests,
        'remaining' => max(0, $max_requests - $data['count']),
        'reset_in' => max(0, $period - $elapsed),
        'count' => $data['count'],
    ];
}

/**
 * 執行速率限制
 * 
 * 檢查請求是否超過限制，如果超過則直接返回 429 錯誤並終止。
 * 適用於 AJAX 處理器，簡化限制檢查流程。
 * 
 * @since 2.5.7
 * @param string $action 動作標識
 * @param int $max_requests 時間週期內最大請求數
 * @param int $period 時間週期（秒）
 * @return array Rate limit 結果（僅在允許時返回）
 */
function mpu_enforce_rate_limit($action, $max_requests = 10, $period = 60)
{
    $result = mpu_check_rate_limit($action, $max_requests, $period);
    
    if (!$result['allowed']) {
        // 記錄日誌（可選）
        if (function_exists('mpu_debug_log')) {
            mpu_debug_log(sprintf(
                'Rate limit exceeded: action=%s, ip=%s, count=%d, max=%d',
                $action,
                mpu_get_client_ip(),
                $result['count'],
                $max_requests
            ));
        }
        
        // 設置 HTTP 429 狀態碼和 Retry-After header
        status_header(429);
        header('Retry-After: ' . $result['reset_in']);
        
        wp_send_json_error([
            'code' => 'rate_limit_exceeded',
            'message' => sprintf(
                __('請求過於頻繁，請 %d 秒後再試', 'mp-ukagaka'),
                $result['reset_in']
            ),
            'retry_after' => $result['reset_in'],
        ], 429);
        
        // 不應該到達這裡，但以防萬一
        exit;
    }
    
    return $result;
}

/**
 * REST API 速率限制檢查（REST pipeline 相容版）
 *
 * 與 mpu_enforce_rate_limit() 不同，此函式會回傳 REST response 物件，
 * 由 REST callback 直接 return，讓 WP_REST_Server 走標準 response pipeline（含 CORS hooks 等）。
 * 超限時會回傳 429 的 WP_REST_Response（含 Retry-After header）。
 *
 * @since 2.8.4
 * @param string $action      動作標識
 * @param int    $max_requests 時間週期內最大請求數
 * @param int    $period       時間週期（秒）
 * @return WP_REST_Response|null 超限時回傳 429 REST 回應，允許時回傳 null
 */
function mpu_rest_check_rate_limit($action, $max_requests = 10, $period = 60)
{
    $result = mpu_check_rate_limit($action, $max_requests, $period);
    if (!$result['allowed']) {
        $response = new WP_REST_Response(
            [
                'code'    => 'rest_rate_limit_exceeded',
                'message' => sprintf(
                    __('請求過於頻繁，請 %d 秒後再試', 'mp-ukagaka'),
                    $result['reset_in']
                ),
                'data'    => ['status' => 429, 'retry_after' => $result['reset_in']],
            ],
            429
        );
        $response->header('Retry-After', (string) $result['reset_in']);
        return $response;
    }
    return null;
}

/**
 * REST API 自動更新 Nonce
 *
 * 當 wp_verify_nonce() 回傳 2（nonce 處於 12–24 小時老化期）時，
 * 在回應資料中附加 new_token，讓前端自動更新 mpuRestNonce，
 * 避免隔日長工作階段出現 403。
 *
 * @since 2.9.1
 */
add_filter('rest_post_dispatch', function ($result, $server, $request) {
    if (strpos($request->get_route(), '/mp-ukagaka/v1/') === false) {
        return $result;
    }
    $nonce = $request->get_header('X-WP-Nonce');
    if ($nonce && wp_verify_nonce($nonce, 'wp_rest') === 2) {
        $data = $result->get_data();
        if (is_array($data)) {
            $data['new_token'] = wp_create_nonce('wp_rest');
            $result->set_data($data);
        }
    }
    return $result;
}, 10, 3);

// ========================================
// AJAX 共用輔助函數
// ========================================

/**
 * 解析 Personality ID（含 fallback 邏輯）
 * 統一各 AJAX handler 中重複的 personality_id 取得流程：
 * 1. 優先從 ukagaka_name 查找
 * 2. fallback 至當前 personality
 *
 * @param string|null $ukagaka_name 角色 config key，null 則直接使用當前 personality
 * @return string|null Personality ID，無法解析時返回 null
 */
function mpu_resolve_personality_id($ukagaka_name = null): ?string
{
    if ($ukagaka_name !== null && function_exists('mpu_get_personality_id_from_ukagaka_name')) {
        $id = mpu_get_personality_id_from_ukagaka_name($ukagaka_name);
        if ($id !== null) {
            return $id;
        }
    }
    if (function_exists('mpu_get_current_personality_id')) {
        return mpu_get_current_personality_id();
    }
    return null;
}

/**
 * 驗證 AJAX Nonce（POST 請求專用）
 * 失敗時自動送出錯誤回應，呼叫方只需檢查回傳值即可
 *
 * @return bool 驗證成功返回 true，失敗返回 false（已送出錯誤回應）
 */
function mpu_verify_ajax_nonce(): bool
{
    $request_data = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' ? $_POST : $_GET;
    if (!isset($request_data['mpu_nonce']) || !wp_verify_nonce($request_data['mpu_nonce'], 'mpu_ajax_nonce')) {
        wp_send_json(['error' => __('安全性驗證失敗', 'mp-ukagaka')]);
        return false;
    }
    return true;
}

/**
 * 建構用戶資訊的 User Prompt 區塊
 * 格式化登入狀態、角色、管理員身份為 LLM 可讀的文字
 *
 * @param array $user_info mpu_get_current_user_info() 的返回值
 * @return string 格式化的用戶資訊提示詞（含尾部換行）
 */
function mpu_build_user_info_prompt(array $user_info): string
{
    $role_labels = [
        'administrator' => '管理人',
        'editor'        => '編集者',
        'author'        => '作者',
        'contributor'   => '貢献者',
        'subscriber'    => '購読者',
    ];

    $parts = [];
    $parts[] = "【現在のユーザー情報】";

    if ($user_info['is_logged_in']) {
        $role_label = $role_labels[$user_info['primary_role']] ?? $user_info['primary_role'];
        $user_lines = [];
        $user_lines[] = "ユーザーがログインしています：{$user_info['display_name']} ({$user_info['username']})";
        $user_lines[] = "役割：{$role_label}";
        if ($user_info['is_admin']) {
            $user_lines[] = "このユーザーはサイト管理人です。";
        }
        $parts[] = implode("\n", $user_lines);
    } else {
        $parts[] = "ユーザーがログインしていません（訪問者）。";
    }

    return implode("\n", $parts);
}
