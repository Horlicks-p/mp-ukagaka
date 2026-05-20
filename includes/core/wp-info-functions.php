<?php

/**
 * WordPress, user, and country info helper functions.
 *
 * @package MP_Ukagaka
 * @subpackage Utility
 */

if (!defined('ABSPATH')) {
    exit();
}

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
