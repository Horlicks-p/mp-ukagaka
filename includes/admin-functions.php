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
 * 管理画面の JS/CSS 読み込み
 */
function mpu_admin_enqueue_scripts($hook_suffix)
{
    // options.php ページでのみ読み込む
    if (strpos($hook_suffix, 'mp-ukagaka/options.php') === false) {
        return;
    }

    // 1. WordPress 付属の jQuery を読み込む
    wp_enqueue_script('jquery');

    // 2. resizer スクリプトを読み込む (jQuery 依存)
    // 使用已定義的常量獲取主文件路徑
    $main_file = defined('MPU_MAIN_FILE') ? MPU_MAIN_FILE : dirname(dirname(__FILE__)) . '/mp-ukagaka.php';
    wp_enqueue_script(
        'mpu-textarearesizer',
        plugins_url('jquery.textarearesizer.compressed.js', $main_file),
        array('jquery'),
        null,
        true
    );

    // 3. resizer を実行するインラインスクリプト
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
 * オプション保存処理 (admin_init)
 * 【★ 修正 2025-10-23】
 * 修正 admin_init 會攔截所有後台 POST 請求的 bug。
 * 增加 $_GET['page'] 和 $_POST['submit*'] 檢查，
 * 確保只在儲存本外掛的選項時才執行 Nonce 驗證。
 * (感謝用戶回報此問題！)
 */
function mpu_handle_options_save()
{
    // 1. 權限檢查 (Capability check)
    if (! current_user_can('manage_options')) {
        return;
    }

    // 2. 檢查是否為我們的外掛頁面 (Check if it's our plugin page)
    // (由用戶提供的關鍵修正)
    if (! isset($_GET['page']) || $_GET['page'] !== 'mp-ukagaka/options.php') {
        return;
    }

    // 3. 檢查是否為我們的表單提交 (Check if it's our form submission)
    // (由用戶提供的關鍵修正，並使用 1.6.1 的所有按鈕名稱)
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

    // 4. 驗證 Nonce (Verify Nonce) - 現在是安全的
    if (! isset($_POST['_wpnonce']) || ! wp_verify_nonce($_POST['_wpnonce'], 'mp_ukagaka_settings')) {
        add_settings_error('mpu_options', 'nonce_fail', __('安全性檢查失敗。', 'mp-ukagaka'));
        return;
    }

    // --- Nonce 驗證通過，開始處理儲存邏輯 ---

    // 現在的選項を取得
    $mpu_opt = mpu_get_option();
    $text = ''; // メッセージ用

    if (isset($_POST['submit1'])) {
        // 【処理】通用設定
        $mpu_opt['show_ukagaka'] = isset($_POST['show_ukagaka']);
        $mpu_opt['show_msg'] = isset($_POST['show_msg']);
        $mpu_opt['default_msg'] = isset($_POST['default_msg'][0]) ? intval($_POST['default_msg'][0]) : 0;
        $mpu_opt['next_msg'] = isset($_POST['next_msg'][0]) ? intval($_POST['next_msg'][0]) : 0;
        $mpu_opt['click_ukagaka'] = isset($_POST['click_ukagaka'][0]) ? intval($_POST['click_ukagaka'][0]) : 0;
        $mpu_opt['cur_ukagaka'] = isset($_POST['cur_ukagaka']) ? sanitize_text_field($_POST['cur_ukagaka']) : 'default_1';
        $mpu_opt['no_style'] = isset($_POST['no_style']);
        $mpu_opt['no_page'] = isset($_POST['no_page']) ? sanitize_textarea_field($_POST['no_page']) : '';
        // ★★★ 修改：系統已固定使用外部對話文件，無需從表單讀取此選項 ★★★
        $mpu_opt['use_external_file'] = true; // 固定為 true
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
        $mpu_opt['openai_api_key'] = $current_opt['openai_api_key'] ?? '';
        $mpu_opt['openai_model'] = $current_opt['openai_model'] ?? 'gpt-4o-mini';
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
        // 【処理】春菜の更改
        $ukagakas_raw = $_POST['ukagakas'] ?? [];
        $ukagakas_sanitized = [];

        foreach ($ukagakas_raw as $key => $value) {
            $key = sanitize_text_field($key);
            // 【★ 修正 2025-10-23】使用 sanitize_textarea_field 處理傳入的字串，而非 mpu_str2array 之後
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
        // 【処理】新春菜の創建
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

        // 鍵名 0 の処理
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
        // 【処理】擴展設定
        $extend = $_POST['extend'] ?? [];
        // js_area は特殊。そのまま保存する (管理者のため)
        // stripslashes をかけて保存 (元の mpu_input_filter と互換性)
        $mpu_opt['extend']['js_area'] = isset($extend['js_area']) ? stripslashes_deep($extend['js_area']) : '';
        $text = '<div class="updated"><p><strong>' . __('設定已儲存', 'mp-ukagaka') . '</strong></p></div>';
    } elseif (isset($_POST['submit5'])) {
        // 【処理】會話設定
        $mpu_opt['auto_msg'] = isset($_POST['auto_msg']) ? sanitize_textarea_field($_POST['auto_msg']) : '';
        $mpu_opt['common_msg'] = isset($_POST['common_msg']) ? sanitize_textarea_field($_POST['common_msg']) : '';
        $text = '<div class="updated"><p><strong>' . __('設定已儲存', 'mp-ukagaka') . '</strong></p></div>';
    } elseif (isset($_POST['submit_ai'])) {
        // 【処理】AI 設定
        $current_opt = mpu_get_option();

        // 保留通用設定（不在此處處理）
        $mpu_opt['show_ukagaka'] = $current_opt['show_ukagaka'] ?? true;
        $mpu_opt['show_msg'] = $current_opt['show_msg'] ?? true;
        $mpu_opt['default_msg'] = $current_opt['default_msg'] ?? 0;
        $mpu_opt['next_msg'] = $current_opt['next_msg'] ?? 0;
        $mpu_opt['click_ukagaka'] = $current_opt['click_ukagaka'] ?? 0;
        $mpu_opt['cur_ukagaka'] = $current_opt['cur_ukagaka'] ?? 'default_1';
        $mpu_opt['no_style'] = $current_opt['no_style'] ?? false;
        $mpu_opt['no_page'] = $current_opt['no_page'] ?? '';
        // ★★★ 修改：系統已固定使用外部對話文件 ★★★
        $mpu_opt['use_external_file'] = true; // 固定為 true
        $mpu_opt['external_file_format'] = $current_opt['external_file_format'] ?? 'txt';
        $mpu_opt['auto_talk'] = $current_opt['auto_talk'] ?? true;
        $mpu_opt['auto_talk_interval'] = $current_opt['auto_talk_interval'] ?? 8;
        $mpu_opt['typewriter_speed'] = $current_opt['typewriter_speed'] ?? 40;
        $mpu_opt['insert_html'] = $current_opt['insert_html'] ?? 0;
        $mpu_opt['ukagakas'] = $current_opt['ukagakas'] ?? [];
        $mpu_opt['extend'] = $current_opt['extend'] ?? [];
        $mpu_opt['auto_msg'] = $current_opt['auto_msg'] ?? '';
        $mpu_opt['common_msg'] = $current_opt['common_msg'] ?? '';

        // 處理 AI 設定
        $mpu_opt['ai_enabled'] = !empty($_POST['ai_enabled']);
        $mpu_opt['ai_provider'] = isset($_POST['ai_provider']) ? sanitize_text_field($_POST['ai_provider']) : 'gemini';

        // 【安全性強化】API Key 加密存儲
        $gemini_key = isset($_POST['ai_api_key']) ? sanitize_text_field($_POST['ai_api_key']) : '';
        $openai_key = isset($_POST['openai_api_key']) ? sanitize_text_field($_POST['openai_api_key']) : '';
        $claude_key = isset($_POST['claude_api_key']) ? sanitize_text_field($_POST['claude_api_key']) : '';

        // 只有當 Key 有變更時才重新加密（避免重複加密）
        // ★★★ 安全性改進：檢查是否為已加密的密鑰 ★★★
        if (!empty($gemini_key)) {
            if (mpu_is_api_key_encrypted($gemini_key)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('MP Ukagaka 安全警告：前端提交了已加密的 API Key，跳過處理');
                }
            } else {
                $mpu_opt['ai_api_key'] = mpu_encrypt_api_key($gemini_key);
            }
        }

        if (!empty($openai_key)) {
            if (mpu_is_api_key_encrypted($openai_key)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('MP Ukagaka 安全警告：前端提交了已加密的 OpenAI API Key，跳過處理');
                }
            } else {
                $mpu_opt['openai_api_key'] = mpu_encrypt_api_key($openai_key);
            }
        }

        if (!empty($claude_key)) {
            if (mpu_is_api_key_encrypted($claude_key)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('MP Ukagaka 安全警告：前端提交了已加密的 Claude API Key，跳過處理');
                }
            } else {
                $mpu_opt['claude_api_key'] = mpu_encrypt_api_key($claude_key);
            }
        }

        $mpu_opt['openai_model'] = isset($_POST['openai_model']) ? sanitize_text_field($_POST['openai_model']) : 'gpt-4o-mini';
        $mpu_opt['claude_model'] = isset($_POST['claude_model']) ? sanitize_text_field($_POST['claude_model']) : 'claude-sonnet-4-5-20250929';
        $mpu_opt['ai_language'] = isset($_POST['ai_language']) ? sanitize_text_field($_POST['ai_language']) : 'zh-TW';
        $mpu_opt['ai_system_prompt'] = isset($_POST['ai_system_prompt']) ? sanitize_textarea_field($_POST['ai_system_prompt']) : '你是一個傲嬌的桌面助手「春菜」。你會用簡短、帶點傲嬌的語氣評論文章內容。回應請保持在 40 字以內。';
        $mpu_opt['ai_probability'] = isset($_POST['ai_probability']) ? max(1, min(100, intval($_POST['ai_probability']))) : 10;
        $mpu_opt['ai_trigger_pages'] = isset($_POST['ai_trigger_pages']) ? sanitize_text_field($_POST['ai_trigger_pages']) : 'is_single';
        $mpu_opt['ai_text_color'] = isset($_POST['ai_text_color']) ? sanitize_hex_color($_POST['ai_text_color']) : '#000000';
        $mpu_opt['ai_display_duration'] = isset($_POST['ai_display_duration']) ? max(1, min(60, intval($_POST['ai_display_duration']))) : 8;
        $mpu_opt['ai_greet_first_visit'] = isset($_POST['ai_greet_first_visit']) && $_POST['ai_greet_first_visit'] ? true : false;
        $mpu_opt['ai_greet_prompt'] = isset($_POST['ai_greet_prompt']) ? sanitize_textarea_field($_POST['ai_greet_prompt']) : '你是一個友善的桌面助手「春菜」。當有訪客第一次來到網站時，你會根據訪客的來源（referrer）用親切的語氣打招呼。回應請保持在 50 字以內。';

        // 保存 Ollama 設定
        if (isset($_POST['ollama_endpoint'])) {
            $mpu_opt['ollama_endpoint'] = sanitize_text_field($_POST['ollama_endpoint']);
        }
        if (isset($_POST['ollama_model'])) {
            $mpu_opt['ollama_model'] = sanitize_text_field($_POST['ollama_model']);
        }
        // 保存「使用 LLM 取代內建對話」設定
        $mpu_opt['ollama_replace_dialogue'] = isset($_POST['ollama_replace_dialogue']) && $_POST['ollama_replace_dialogue'] ? true : false;

        // 保存「關閉思考模式」設定
        $mpu_opt['ollama_disable_thinking'] = isset($_POST['ollama_disable_thinking']) && $_POST['ollama_disable_thinking'] ? true : false;

        // 如果啟用「使用 LLM 取代內建對話」，自動關閉頁面感知功能
        if (!empty($mpu_opt['ollama_replace_dialogue']) && $mpu_opt['ai_provider'] === 'ollama') {
            $mpu_opt['ai_enabled'] = false;
        }

        $text = '<div class="updated"><p><strong>' . __('AI 設定已儲存', 'mp-ukagaka') . '</strong></p></div>';
    } elseif (isset($_POST['submit_reset'])) {
        // 【処理】重置設定
        if (isset($_POST['reset_mpu'])) {
            unset($mpu_opt);
            update_option('mp_ukagaka', []); // 空にする
            mpu_default_opt(); // デフォルトを再設定
            $text = '<div class="updated"><p><strong>' . __('設定已重置', 'mp-ukagaka') . '</strong></p></div>';
        } else {
            $text = '<div class="error"><p><strong>' . __('設定未被重置', 'mp-ukagaka') . '</strong></p></div>';
        }
    }

    // オプションをデータベースに保存
    update_option('mp_ukagaka', $mpu_opt);

    // メッセージを管理画面に表示（已包含 HTML 格式，直接輸出）
    if ($text) {
        // 使用 transients 將訊息傳遞給 options.php
        set_transient('mpu_admin_message', $text, 30);
    }
}
add_action('admin_init', 'mpu_handle_options_save');

/**
 * ダイアログファイル生成ヘルパー
 * 【安全性強化】使用 mpu_secure_file_write 替代 file_put_contents
 */
function mpu_generate_dialog_file($filename, $msg_array, $ext)
{
    if (empty($filename) || !is_array($msg_array)) {
        return false;
    }

    // ファイル名をサニタイズ
    $filename = sanitize_file_name($filename);
    $ext = ($ext === 'json') ? 'json' : 'txt';

    // 確保目錄存在
    if (!mpu_ensure_dialogs_dir()) {
        error_log('MP Ukagaka: 無法創建對話目錄');
        return false;
    }

    $file_path = mpu_get_dialogs_dir() . '/' . $filename . '.' . $ext;

    if ($ext == 'json') {
        $json_data = array(
            'messages' => $msg_array
        );
        $content = wp_json_encode($json_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    } else {
        $content = mpu_array2str($msg_array);
    }

    // 【安全性強化】使用安全文件寫入函數
    $result = mpu_secure_file_write($file_path, $content);

    if (is_wp_error($result)) {
        error_log('MP Ukagaka: 文件寫入失敗 - ' . $result->get_error_message());
        return false;
    }

    return true;
}


/**
 * オプションページの HTML を表示するコールバック
 */
function mpu_options_page_html()
{
    // 権限チェック
    if (! current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    // グローバル変数を読み込み (options.php が依存)
    global $mpu_opt;
    $mpu_opt = mpu_get_option();

    // admin_init で追加された通知メッセージを表示 (旧 $text の代わり)
    settings_errors('mpu_options');

    // options.php (HTML フレーム) を読み込む
    // 使用已定義的常量獲取主文件路徑
    $main_file = defined('MPU_MAIN_FILE') ? MPU_MAIN_FILE : dirname(dirname(__FILE__)) . '/mp-ukagaka.php';
    require_once(plugin_dir_path($main_file) . 'options.php');
}

/**
 * オプションページの登録
 */
function mpu_options()
{
    if (function_exists("add_options_page")) {
        add_options_page(
            __("MP Ukagaka 選項", "mp-ukagaka"),
            "MP-Ukagaka",
            "manage_options",
            "mp-ukagaka/options.php", // slug
            "mpu_options_page_html"   // 表示用コールバック関数
        );
    }
}
add_action("admin_menu", "mpu_options");
