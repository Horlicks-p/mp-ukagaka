<?php

/**
 * 後台功能：設定保存、管理頁面
 * 
 * @package MP_Ukagaka
 * @subpackage Admin
 */

if (!defined('ABSPATH')) {
    exit();
}

/**
 * 載入後台管理頁面的 JS/CSS 資源
 * @param {string} $hook_suffix - 當前頁面的 hook 名稱
 */
function mpu_admin_enqueue_scripts($hook_suffix)
{
    // 只在 options.php 頁面載入
    if (strpos($hook_suffix, 'mp-ukagaka/options.php') === false) {
        return;
    }

    // 載入 WordPress 內建的 jQuery
    wp_enqueue_script('jquery');

    // 載入文字區域調整大小腳本（依賴 jQuery）
    // 使用已定義的常量獲取主文件路徑
    $main_file = defined('MPU_MAIN_FILE') ? MPU_MAIN_FILE : dirname(dirname(__FILE__)) . '/mp-ukagaka.php';
    wp_enqueue_script(
        'mpu-textarearesizer',
        plugins_url('js/ukagaka-textarearesizer.js', $main_file),
        array('jquery'),
        null,
        true
    );

    // 執行文字區域調整大小的內聯腳本
    wp_add_inline_script('mpu-textarearesizer', "
        jQuery(document).ready(function($) {
            $('textarea.resizable:not(.processed)').TextAreaResizer();
            $('iframe.resizable:not(.processed)').TextAreaResizer();
            $('textarea').css('resize', 'both');
        });
    ");
}
add_action('admin_enqueue_scripts', 'mpu_admin_enqueue_scripts');


/**
 * 處理選項保存（在 admin_init 中執行）
 * 增加頁面和表單檢查，確保只在儲存本外掛的選項時才執行驗證
 * @return void
 */
function mpu_handle_options_save()
{
    // 權限檢查
    if (! current_user_can('manage_options')) {
        return;
    }

    // 檢查是否為本外掛的設定頁面
    if (! isset($_GET['page']) || $_GET['page'] !== 'mp-ukagaka/options.php') {
        return;
    }

    // 檢查是否為本外掛的表單提交
    $is_our_submit = isset($_POST['submit1'])     // 通用設定
        || isset($_POST['submit2'])     // 春菜們
        || isset($_POST['submit3'])     // 創建新春菜
        || isset($_POST['submit4'])     // 擴展
        || isset($_POST['submit5'])     // 會話
        || isset($_POST['submit_ai'])   // AI 設定
        || isset($_POST['submit_llm'])  // LLM 設定
        || isset($_POST['submit_reset']); // 重置設定

    if (! $is_our_submit) {
        return;
    }

    // 驗證 Nonce
    if (! isset($_POST['_wpnonce']) || ! wp_verify_nonce($_POST['_wpnonce'], 'mp_ukagaka_settings')) {
        add_settings_error('mpu_options', 'nonce_fail', __('安全性檢查失敗。', 'mp-ukagaka'));
        return;
    }

    // Nonce 驗證通過，開始處理儲存邏輯

    // 取得當前選項
    $mpu_opt = mpu_get_option();
    $text = ''; // 用於顯示訊息

    if (isset($_POST['submit1'])) {
        // 處理通用設定
        $mpu_opt['show_ukagaka'] = isset($_POST['show_ukagaka']);
        $mpu_opt['show_msg'] = isset($_POST['show_msg']);
        $mpu_opt['default_msg'] = isset($_POST['default_msg'][0]) ? intval($_POST['default_msg'][0]) : 0;
        $mpu_opt['next_msg'] = isset($_POST['next_msg'][0]) ? intval($_POST['next_msg'][0]) : 0;
        $mpu_opt['click_ukagaka'] = isset($_POST['click_ukagaka'][0]) ? intval($_POST['click_ukagaka'][0]) : 0;
        $mpu_opt['cur_ukagaka'] = isset($_POST['cur_ukagaka']) ? sanitize_text_field($_POST['cur_ukagaka']) : 'default_1';
        $mpu_opt['no_style'] = isset($_POST['no_style']);
        $mpu_opt['no_page'] = isset($_POST['no_page']) ? sanitize_textarea_field($_POST['no_page']) : '';
        // 系統已固定使用外部對話文件
        $mpu_opt['use_external_file'] = true;
        $mpu_opt['external_file_format'] = isset($_POST['external_file_format'][0]) ? sanitize_text_field($_POST['external_file_format'][0]) : 'txt';
        $mpu_opt['auto_talk'] = isset($_POST['auto_talk']);
        $mpu_opt['auto_talk_interval'] = isset($_POST['auto_talk_interval']) ? max(3, min(30, intval($_POST['auto_talk_interval']))) : 8;
        $mpu_opt['typewriter_speed'] = isset($_POST['typewriter_speed']) ? max(10, min(200, intval($_POST['typewriter_speed']))) : 40;

        if (isset($_POST['insert_html'])) {
            $mpu_opt['insert_html'] = (int)$_POST['insert_html'][0];
        }

        // 保留 AI 設定（不在此處處理）
        $current_opt = mpu_get_option();
        $mpu_opt['ai_enabled'] = $current_opt['ai_enabled'] ?? false;
        $mpu_opt['ai_provider'] = $current_opt['ai_provider'] ?? 'gemini';
        $mpu_opt['ai_api_key'] = $current_opt['ai_api_key'] ?? '';
        $mpu_opt['gemini_model'] = $current_opt['gemini_model'] ?? 'gemini-2.5-flash';
        $mpu_opt['openai_api_key'] = $current_opt['openai_api_key'] ?? '';
        $mpu_opt['openai_model'] = $current_opt['openai_model'] ?? 'gpt-4.1-mini-2025-04-14';
        $mpu_opt['claude_api_key'] = $current_opt['claude_api_key'] ?? '';
        $mpu_opt['claude_model'] = $current_opt['claude_model'] ?? 'claude-sonnet-4-5-20250929';
        $mpu_opt['ai_language'] = $current_opt['ai_language'] ?? 'zh-TW';
        $mpu_opt['ai_system_prompt'] = $current_opt['ai_system_prompt'] ?? '你是一個傲嬌的桌面助手「春菜」。你會用簡短、帶點傲嬌的語氣評論文章內容。回應請保持在 40 字以內。';
        $mpu_opt['ai_probability'] = $current_opt['ai_probability'] ?? 10;
        $mpu_opt['ai_trigger_pages'] = $current_opt['ai_trigger_pages'] ?? 'is_single';
        $mpu_opt['ai_text_color'] = $current_opt['ai_text_color'] ?? '#000000';
        $mpu_opt['ai_display_duration'] = $current_opt['ai_display_duration'] ?? 8;
        $mpu_opt['ai_greet_first_visit'] = $current_opt['ai_greet_first_visit'] ?? false;
        $mpu_opt['ai_greet_prompt'] = $current_opt['ai_greet_prompt'] ?? '你是一個友善的桌面助手「春菜」。當有訪客第一次來到網站時，你會根據訪客的來源（referrer）用親切的語氣打招呼。回應請保持在 50 字以內。';

        $message = __('設定已儲存', 'mp-ukagaka');
        $text = '<div class="updated"><p><strong>' . $message . '</strong></p></div>';
    } elseif (isset($_POST['submit2'])) {
        // 處理春菜設定更新
        $ukagakas_raw = $_POST['ukagakas'] ?? [];
        $ukagakas_sanitized = [];

        foreach ($ukagakas_raw as $key => $value) {
            $key = sanitize_text_field($key);
            // 使用 sanitize_textarea_field 處理傳入的字串，再轉換為陣列
            $ukagakas_sanitized[$key]['msg'] = mpu_str2array(sanitize_textarea_field($value['msg']));
            $ukagakas_sanitized[$key]['name'] = sanitize_text_field($value['name']);
            $ukagakas_sanitized[$key]['shell'] = esc_url_raw($value['shell']);
            $ukagakas_sanitized[$key]['show'] = isset($value['show']);
            $ukagakas_sanitized[$key]['dialog_filename'] = isset($value['dialog_filename']) ? sanitize_file_name($value['dialog_filename']) : $key;

            if (isset($_POST['generate_dialog_file'][$key]) && $_POST['generate_dialog_file'][$key] == 'true') {
                mpu_generate_dialog_file(
                    $ukagakas_sanitized[$key]['dialog_filename'],
                    $ukagakas_sanitized[$key]['msg'],
                    $mpu_opt['external_file_format'] ?? 'txt'
                );
            }
        }
        $mpu_opt['ukagakas'] = $ukagakas_sanitized;

        $message = __('春菜們已經煥然一新啦', 'mp-ukagaka');
        if (isset($_POST['generate_dialog_file'])) {
            $message .= __('，對話檔案已生成', 'mp-ukagaka');
        }
        $text = '<div class="updated"><p><strong>' . $message . '</strong></p></div>';
    } elseif (isset($_POST['submit3'])) {
        // 處理新春菜創建
        $ukagaka_raw = $_POST['ukagaka'] ?? [];
        $ukagaka = [];

        $ukagaka['msg'] = isset($ukagaka_raw['msg']) ? mpu_str2array(sanitize_textarea_field($ukagaka_raw['msg'])) : [];
        $ukagaka['name'] = isset($ukagaka_raw['name']) ? sanitize_text_field($ukagaka_raw['name']) : '';
        $ukagaka['shell'] = isset($ukagaka_raw['shell']) ? esc_url_raw($ukagaka_raw['shell']) : '';
        $ukagaka['show'] = isset($ukagaka_raw['show']);
        $ukagaka['dialog_filename'] = isset($ukagaka_raw['dialog_filename']) ? sanitize_file_name($ukagaka_raw['dialog_filename']) : '';

        if (isset($_POST['generate_dialog_file_new']) && $_POST['generate_dialog_file_new'] == 'true' && !empty($ukagaka['dialog_filename'])) {
            mpu_generate_dialog_file(
                $ukagaka['dialog_filename'],
                $ukagaka['msg'],
                $mpu_opt['external_file_format'] ?? 'txt'
            );
        }

        $mpu_opt['ukagakas'][] = $ukagaka;

        // 處理鍵名為 0 的情況
        if (isset($mpu_opt['ukagakas'][0]) && is_array($mpu_opt['ukagakas'][0])) {
            $mpu_opt['ukagakas'][] = $mpu_opt['ukagakas'][0];
            unset($mpu_opt['ukagakas'][0]);
        }

        $message = __('春菜創建成功～', 'mp-ukagaka');
        if (isset($_POST['generate_dialog_file_new']) && $_POST['generate_dialog_file_new'] == 'true') {
            $message .= __('，對話檔案已生成', 'mp-ukagaka');
        }
        $text = '<div class="updated"><p><strong>' . $message . '</strong></p></div>';
    } elseif (isset($_POST['submit4'])) {
        // 處理擴展設定
        $extend = $_POST['extend'] ?? [];
        // js_area 為特殊欄位，直接保存（供管理員使用）
        // 使用 stripslashes 處理，與原 mpu_input_filter 保持兼容
        $mpu_opt['extend']['js_area'] = isset($extend['js_area']) ? stripslashes_deep($extend['js_area']) : '';
        $text = '<div class="updated"><p><strong>' . __('設定已儲存', 'mp-ukagaka') . '</strong></p></div>';
    } elseif (isset($_POST['submit5'])) {
        // 處理會話設定
        $mpu_opt['auto_msg'] = isset($_POST['auto_msg']) ? sanitize_textarea_field($_POST['auto_msg']) : '';
        $mpu_opt['common_msg'] = isset($_POST['common_msg']) ? sanitize_textarea_field($_POST['common_msg']) : '';
        $text = '<div class="updated"><p><strong>' . __('設定已儲存', 'mp-ukagaka') . '</strong></p></div>';
    } elseif (isset($_POST['submit_ai'])) {
        // 處理 AI 設定
        $current_opt = mpu_get_option();

        // 保留通用設定（不在 AI 設定頁面處理）
        $mpu_opt['show_ukagaka'] = $current_opt['show_ukagaka'] ?? true;
        $mpu_opt['show_msg'] = $current_opt['show_msg'] ?? true;
        $mpu_opt['default_msg'] = $current_opt['default_msg'] ?? 0;
        $mpu_opt['next_msg'] = $current_opt['next_msg'] ?? 0;
        $mpu_opt['click_ukagaka'] = $current_opt['click_ukagaka'] ?? 0;
        $mpu_opt['cur_ukagaka'] = $current_opt['cur_ukagaka'] ?? 'default_1';
        $mpu_opt['no_style'] = $current_opt['no_style'] ?? false;
        $mpu_opt['no_page'] = $current_opt['no_page'] ?? '';
        // 系統已固定使用外部對話文件
        $mpu_opt['use_external_file'] = true;
        $mpu_opt['external_file_format'] = $current_opt['external_file_format'] ?? 'txt';
        $mpu_opt['auto_talk'] = $current_opt['auto_talk'] ?? true;
        $mpu_opt['auto_talk_interval'] = $current_opt['auto_talk_interval'] ?? 8;
        $mpu_opt['typewriter_speed'] = $current_opt['typewriter_speed'] ?? 40;
        $mpu_opt['insert_html'] = $current_opt['insert_html'] ?? 0;
        $mpu_opt['ukagakas'] = $current_opt['ukagakas'] ?? [];
        $mpu_opt['extend'] = $current_opt['extend'] ?? [];
        $mpu_opt['auto_msg'] = $current_opt['auto_msg'] ?? '';
        $mpu_opt['common_msg'] = $current_opt['common_msg'] ?? '';

        // 處理 AI 設定（僅頁面感知相關的設定，不處理提供商、API Key、模型選擇）
        $mpu_opt['ai_language'] = isset($_POST['ai_language']) ? sanitize_text_field($_POST['ai_language']) : 'zh-TW';
        $mpu_opt['ai_system_prompt'] = isset($_POST['ai_system_prompt']) ? sanitize_textarea_field($_POST['ai_system_prompt']) : '你是一個傲嬌的桌面助手「春菜」。你會用簡短、帶點傲嬌的語氣評論文章內容。回應請保持在 40 字以內。';
        $mpu_opt['ai_probability'] = isset($_POST['ai_probability']) ? max(1, min(100, intval($_POST['ai_probability']))) : 10;
        $mpu_opt['ai_trigger_pages'] = isset($_POST['ai_trigger_pages']) ? sanitize_text_field($_POST['ai_trigger_pages']) : 'is_single';
        $mpu_opt['ai_text_color'] = isset($_POST['ai_text_color']) ? sanitize_hex_color($_POST['ai_text_color']) : '#000000';
        $mpu_opt['ai_display_duration'] = isset($_POST['ai_display_duration']) ? max(1, min(60, intval($_POST['ai_display_duration']))) : 8;
        $mpu_opt['ai_greet_first_visit'] = isset($_POST['ai_greet_first_visit']) && $_POST['ai_greet_first_visit'] ? true : false;
        $mpu_opt['ai_greet_prompt'] = isset($_POST['ai_greet_prompt']) ? sanitize_textarea_field($_POST['ai_greet_prompt']) : '你是一個友善的桌面助手「春菜」。當有訪客第一次來到網站時，你會根據訪客的來源（referrer）用親切的語氣打招呼。回應請保持在 50 字以內。';

        // 注意：提供商選擇、API Key、模型選擇已移至 LLM 設定頁面
        // 「LLM 取代內建對話」和「頁面感知 AI (ai_enabled)」是兩個獨立的功能
        // 用戶可以同時啟用或單獨啟用任一功能

        $text = '<div class="updated"><p><strong>' . __('AI 設定已儲存', 'mp-ukagaka') . '</strong></p></div>';
    } elseif (isset($_POST['submit_reset'])) {
        // 處理重置設定
        if (isset($_POST['reset_mpu'])) {
            unset($mpu_opt);
            update_option('mp_ukagaka', []); // 清空選項
            mpu_default_opt(); // 重新設定預設值
            $text = '<div class="updated"><p><strong>' . __('設定已重置', 'mp-ukagaka') . '</strong></p></div>';
        } else {
            $text = '<div class="error"><p><strong>' . __('設定未被重置', 'mp-ukagaka') . '</strong></p></div>';
        }
    }

    // 將選項保存到資料庫
    update_option('mp_ukagaka', $mpu_opt);

    // 在管理畫面顯示訊息（已包含 HTML 格式）
    if ($text) {
        // 使用 transients 將訊息傳遞給 options.php
        set_transient('mpu_admin_message', $text, 30);

        // 保存後重定向，確保頁面顯示最新值
        // 獲取當前頁面編號，用於重定向
        $cur_page = isset($_GET['cur_page']) ? intval($_GET['cur_page']) : 0;
        if ($cur_page < 0 || $cur_page > 6) {
            $cur_page = 0;
        }

        // 構建重定向 URL
        $redirect_url = admin_url('options-general.php?page=mp-ukagaka/options.php&cur_page=' . $cur_page . '&settings-updated=true');

        // 執行重定向
        wp_redirect($redirect_url);
        exit;
    }
}
add_action('admin_init', 'mpu_handle_options_save');

/**
 * 生成對話檔案
 * 使用安全文件寫入函數（安全性強化）
 * @param {string} $filename - 檔案名稱（不含副檔名）
 * @param {array} $msg_array - 對話訊息陣列
 * @param {string} $ext - 檔案副檔名（'txt' 或 'json'）
 * @return {bool} 是否成功生成
 */
function mpu_generate_dialog_file($filename, $msg_array, $ext)
{
    if (empty($filename) || !is_array($msg_array)) {
        return false;
    }

    // 清理檔案名稱
    $filename = sanitize_file_name($filename);
    $ext = ($ext === 'json') ? 'json' : 'txt';

    // 確保對話目錄存在
    if (!mpu_ensure_dialogs_dir()) {
        error_log('MP Ukagaka: 無法創建對話目錄');
        return false;
    }

    $file_path = mpu_get_dialogs_dir() . '/' . $filename . '.' . $ext;

    // 根據副檔名生成內容
    if ($ext == 'json') {
        $json_data = array(
            'messages' => $msg_array
        );
        $content = wp_json_encode($json_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    } else {
        $content = mpu_array2str($msg_array);
    }

    // 使用安全文件寫入函數（安全性強化）
    $result = mpu_secure_file_write($file_path, $content);

    if (is_wp_error($result)) {
        error_log('MP Ukagaka: 文件寫入失敗 - ' . $result->get_error_message());
        return false;
    }

    return true;
}


/**
 * 顯示選項頁面的 HTML（回調函數）
 */
function mpu_options_page_html()
{
    // 權限檢查
    if (! current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    // 載入全域變數（options.php 依賴此變數）
    global $mpu_opt;
    $mpu_opt = mpu_get_option();

    // 顯示 admin_init 中新增的通知訊息
    settings_errors('mpu_options');

    // 載入 options.php（HTML 框架）
    // 使用已定義的常量獲取主文件路徑
    $main_file = defined('MPU_MAIN_FILE') ? MPU_MAIN_FILE : dirname(dirname(__FILE__)) . '/mp-ukagaka.php';
    require_once(plugin_dir_path($main_file) . 'options/options.php');
}

/**
 * 註冊選項頁面
 */
function mpu_options()
{
    if (function_exists("add_options_page")) {
        add_options_page(
            __("MP Ukagaka 選項", "mp-ukagaka"),
            "MP-Ukagaka",
            "manage_options",
            "mp-ukagaka/options.php", // slug
            "mpu_options_page_html"   // 顯示用回調函數
        );
    }
}
add_action("admin_menu", "mpu_options");
