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

    // 處理統計資料清除請求（獨立處理，不需要表單提交）
    if (isset($_POST['mpu_action']) && $_POST['mpu_action'] === 'clear_stats') {
        if (isset($_POST['mpu_clear_stats_nonce']) && wp_verify_nonce($_POST['mpu_clear_stats_nonce'], 'mpu_clear_stats')) {
            if (function_exists('mpu_clear_all_stats')) {
                $cleared = mpu_clear_all_stats();
                set_transient('mpu_admin_message', '<div class="updated"><p><strong>' . sprintf(__('已清除 %d 筆統計資料', 'mp-ukagaka'), $cleared) . '</strong></p></div>', 30);
            }
            wp_safe_redirect(admin_url('options-general.php?page=mp-ukagaka/options.php&cur_page=8'));
            exit;
        }
    }

    // 檢查是否為本外掛的表單提交
    $is_our_submit = isset($_POST['submit1'])     // 通用設定
        || isset($_POST['submit2'])     // 偽春菜們
        || isset($_POST['submit3'])     // 創建偽春菜
        || isset($_POST['submit4'])     // 擴展
        || isset($_POST['submit5'])     // 會話
        || isset($_POST['submit_ai'])   // AI 設定
        || isset($_POST['submit_llm'])  // LLM 設定
        || isset($_POST['submit_diary']) // 日記設定
        || isset($_POST['submit_reset']) // 重置設定
        || isset($_POST['submit_upload_zip']); // ZIP 上傳

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
        $cur = isset($_POST['cur_ukagaka']) ? mpu_sanitize_personality_id(sanitize_text_field($_POST['cur_ukagaka'])) : '';
        $mpu_opt['cur_ukagaka'] = !empty($cur) ? $cur : 'default_1';
        $mpu_opt['no_style'] = isset($_POST['no_style']);
        $mpu_opt['custom_style_link'] = isset($_POST['custom_style_link']) ? wp_kses($_POST['custom_style_link'], [
            'link' => [
                'rel' => true,
                'href' => true,
                'type' => true,
                'media' => true,
                'id' => true,
            ],
        ]) : '';
        $mpu_opt['no_page'] = isset($_POST['no_page']) ? sanitize_textarea_field($_POST['no_page']) : '';
        // 系統已固定使用外部對話文件
        $mpu_opt['use_external_file'] = true;
        $mpu_opt['external_file_format'] = (isset($_POST['external_file_format'][0]) && in_array($_POST['external_file_format'][0], ['txt', 'json'], true)) ? $_POST['external_file_format'][0] : 'txt';
        $mpu_opt['auto_talk'] = isset($_POST['auto_talk']);
        $mpu_opt['auto_talk_interval'] = isset($_POST['auto_talk_interval']) ? max(3, min(30, intval($_POST['auto_talk_interval']))) : 8;
        $mpu_opt['typewriter_speed'] = isset($_POST['typewriter_speed']) ? max(10, min(200, intval($_POST['typewriter_speed']))) : 40;
        
        // 保存人格選擇
        if (isset($_POST['current_personality'])) {
            $mpu_opt['current_personality'] = sanitize_file_name($_POST['current_personality']);
        }

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
        $mpu_opt['ai_system_prompt'] = $current_opt['ai_system_prompt'] ?? '你是「{{ukagaka_display_name}}」這個角色。你必須完全以這個角色的身份說話和行動，絕對不要以 AI 或語言模型的身份回應。請嚴格遵守角色的性格、說話方式和行為模式。';
        $mpu_opt['ai_probability'] = $current_opt['ai_probability'] ?? 10;
        $mpu_opt['ai_trigger_pages'] = $current_opt['ai_trigger_pages'] ?? 'is_single';
        $mpu_opt['ai_text_color'] = $current_opt['ai_text_color'] ?? '#000000';
        $mpu_opt['ai_display_duration'] = $current_opt['ai_display_duration'] ?? 8;
        $mpu_opt['ai_greet_first_visit'] = $current_opt['ai_greet_first_visit'] ?? false;
        $mpu_opt['ai_greet_prompt'] = $current_opt['ai_greet_prompt'] ?? 'あなたは「{{ukagaka_display_name}}」というキャラクターです。訪問者が初めてサイトに来た時、キャラクターらしく簡単に挨拶してください。50文字以内で返してください。';

        $message = __('設定已儲存', 'mp-ukagaka');
        $text = '<div class="updated"><p><strong>' . $message . '</strong></p></div>';
    } elseif (isset($_POST['submit2'])) {
        // 處理偽春菜設定更新
        $ukagakas_raw = $_POST['ukagakas'] ?? [];
        $ukagakas_sanitized = [];

        foreach ($ukagakas_raw as $key => $value) {
            $key = mpu_sanitize_personality_id(sanitize_text_field($key));
            if (empty($key)) {
                continue;
            }
            // 使用 sanitize_textarea_field 處理傳入的字串，再轉換為陣列
            $ukagakas_sanitized[$key]['msg'] = mpu_str2array(sanitize_textarea_field($value['msg']));
            $ukagakas_sanitized[$key]['name'] = sanitize_text_field($value['name']);
            $ukagakas_sanitized[$key]['shell'] = esc_url_raw($value['shell']);
            $ukagakas_sanitized[$key]['show'] = isset($value['show']);
            $ukagakas_sanitized[$key]['dialog_filename'] = isset($value['dialog_filename']) ? sanitize_file_name($value['dialog_filename']) : $key;

            // 處理芙莉蓮專屬的裝飾配件設定
            if ($key === 'default_1') {
                $ukagakas_sanitized[$key]['show_decorations'] = isset($value['show_decorations']);
            }

            if (isset($_POST['generate_dialog_file'][$key]) && $_POST['generate_dialog_file'][$key] == 'true') {
                mpu_generate_dialog_file(
                    $ukagakas_sanitized[$key]['dialog_filename'],
                    $ukagakas_sanitized[$key]['msg'],
                    $mpu_opt['external_file_format'] ?? 'txt'
                );
            }
        }
        $mpu_opt['ukagakas'] = $ukagakas_sanitized;

        $message = __('偽春菜們已經更新', 'mp-ukagaka');
        if (isset($_POST['generate_dialog_file'])) {
            $message .= __('，對話檔案已生成', 'mp-ukagaka');
        }
        $text = '<div class="updated"><p><strong>' . $message . '</strong></p></div>';
    } elseif (isset($_POST['submit_upload_zip'])) {
        // 處理 ZIP 文件上傳
        if (!function_exists('mpu_handle_ghost_zip_upload')) {
            add_settings_error('mpu_options', 'function_missing', __('ZIP 上傳處理函數不存在。', 'mp-ukagaka'));
            return;
        }

        $upload_result = mpu_handle_ghost_zip_upload();

        if (is_wp_error($upload_result)) {
            add_settings_error('mpu_options', 'zip_upload_error', $upload_result->get_error_message());
            return;
        }

        // 將預覽數據存儲到 transient
        $transient_key = 'mpu_ghost_zip_preview_' . get_current_user_id();
        set_transient($transient_key, $upload_result, HOUR_IN_SECONDS); // 1 小時有效期

        // 重定向到預覽頁面
        $redirect_url = admin_url('options-general.php?page=mp-ukagaka/options.php&cur_page=2&preview=1');
        wp_safe_redirect($redirect_url);
        exit;
    } elseif (isset($_POST['submit3'])) {
        // 處理新偽春菜創建
        $transient_key = 'mpu_ghost_zip_preview_' . get_current_user_id();
        $preview_data = get_transient($transient_key);
        $is_zip_upload = ($preview_data !== false);

        if ($is_zip_upload && isset($_POST['ghost_preview_id'])) {
            // 從 ZIP 預覽數據創建偽春菜
            $ghost_id = sanitize_file_name($_POST['ghost_preview_id']);

            // 驗證 preview_id 與 transient 中的數據一致
            if ($preview_data['id'] !== $ghost_id) {
                add_settings_error('mpu_options', 'preview_mismatch', __('預覽數據不匹配，請重新上傳。', 'mp-ukagaka'));
                delete_transient($transient_key);
                return;
            }

            $ukagaka = array();
            $ukagaka['name'] = sanitize_text_field($preview_data['name']);
            $ukagaka['shell'] = esc_url_raw($preview_data['shell_url']);
            $ukagaka['show'] = true; // 默認顯示
            $ukagaka['dialog_filename'] = sanitize_file_name($ghost_id);
            $ukagaka['msg'] = array(); // 留空，後續在「偽春菜們」頁面配置

            // 檢查是否有 decorations 配置
            if (function_exists('mpu_load_personality_manifest')) {
                $manifest = mpu_load_personality_manifest($ghost_id);
                if (!empty($manifest['decorations_folder'])) {
                    $ukagaka['show_decorations'] = true;
                }
            }

            // 清除 transient
            delete_transient($transient_key);

            $mpu_opt['ukagakas'][] = $ukagaka;

            // 處理鍵名為 0 的情況
            if (isset($mpu_opt['ukagakas'][0]) && is_array($mpu_opt['ukagakas'][0])) {
                $mpu_opt['ukagakas'][] = $mpu_opt['ukagakas'][0];
                unset($mpu_opt['ukagakas'][0]);
            }

            update_option('mp_ukagaka', $mpu_opt);

            $message = __('偽春菜創建成功', 'mp-ukagaka');
            $message .= ' ' . __('請前往「偽春菜們」頁面配置對話文件等設定。', 'mp-ukagaka');
            set_transient('mpu_admin_message', '<div class="updated"><p><strong>' . $message . '</strong></p></div>', 30);

            // 重定向到「偽春菜們」頁面
            $redirect_url = admin_url('options-general.php?page=mp-ukagaka/options.php&cur_page=1');
            wp_safe_redirect($redirect_url);
            exit;
        } else {
            // 傳統手動創建方式
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

        $message = __('偽春菜創建成功', 'mp-ukagaka');
        if (isset($_POST['generate_dialog_file_new']) && $_POST['generate_dialog_file_new'] == 'true') {
            $message .= __('，對話檔案已生成', 'mp-ukagaka');
        }
        $text = '<div class="updated"><p><strong>' . $message . '</strong></p></div>';
        }
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
        $mpu_opt['ai_system_prompt'] = isset($_POST['ai_system_prompt']) ? wp_kses_post(wp_unslash($_POST['ai_system_prompt'])) : 'あなたは「{{ukagaka_display_name}}」というキャラクターです。必ずこのキャラクターとして振る舞い、一人称は「私」を使用してください。回答は日本語で、50文字以内の短い一言で返してください。自分が AI や Qwen だと言わないでください。';
        $mpu_opt['ai_probability'] = isset($_POST['ai_probability']) ? max(1, min(100, intval($_POST['ai_probability']))) : 10;
        $mpu_opt['ai_max_tokens'] = isset($_POST['ai_max_tokens']) ? max(100, min(8192, intval($_POST['ai_max_tokens']))) : 1000;
        $mpu_opt['ai_trigger_pages'] = isset($_POST['ai_trigger_pages']) ? sanitize_text_field($_POST['ai_trigger_pages']) : 'is_single';
        $mpu_opt['ai_text_color'] = isset($_POST['ai_text_color']) ? sanitize_hex_color($_POST['ai_text_color']) : '#000000';
        $mpu_opt['ai_display_duration'] = isset($_POST['ai_display_duration']) ? max(1, min(60, intval($_POST['ai_display_duration']))) : 8;
        $mpu_opt['ai_greet_first_visit'] = isset($_POST['ai_greet_first_visit']) && $_POST['ai_greet_first_visit'] ? true : false;
        $mpu_opt['ai_greet_prompt'] = isset($_POST['ai_greet_prompt']) ? wp_kses_post(wp_unslash($_POST['ai_greet_prompt'])) : 'あなたは「{{ukagaka_display_name}}」というキャラクターです。訪問者が初めてサイトに来た時、キャラクターらしく簡単に挨拶してください。50文字以内で返してください。';

        // 注意：提供商選擇、API Key、模型選擇已移至 LLM 設定頁面
        // 「LLM 取代內建對話」和「頁面感知 AI (ai_enabled)」是兩個獨立的功能
        // 用戶可以同時啟用或單獨啟用任一功能

        $text = '<div class="updated"><p><strong>' . __('AI 設定已儲存', 'mp-ukagaka') . '</strong></p></div>';
    } elseif (isset($_POST['submit_llm'])) {
        // 處理 LLM 設定
        $current_opt = mpu_get_option();

        // 保存頁面感知開關
        $mpu_opt['ai_enabled'] = isset($_POST['ai_enabled']) && $_POST['ai_enabled'] ? true : false;

        // 保存提供商選擇（統一使用 llm_provider，同時保持 ai_provider 向後兼容）
        if (isset($_POST['llm_provider'])) {
            $allowed_providers = ['gemini', 'openai', 'claude', 'ollama'];
            $provider = in_array($_POST['llm_provider'], $allowed_providers, true) ? $_POST['llm_provider'] : 'gemini';
            $mpu_opt['llm_provider'] = $provider;
            $mpu_opt['ai_provider'] = $provider; // 向後兼容
        }

        // 處理各提供商的 API Key（加密存儲）
        $gemini_key = isset($_POST['llm_gemini_api_key']) ? sanitize_text_field($_POST['llm_gemini_api_key']) : '';
        $openai_key = isset($_POST['llm_openai_api_key']) ? sanitize_text_field($_POST['llm_openai_api_key']) : '';
        $claude_key = isset($_POST['llm_claude_api_key']) ? sanitize_text_field($_POST['llm_claude_api_key']) : '';

        if (!empty($gemini_key) && !mpu_is_api_key_encrypted($gemini_key)) {
            $mpu_opt['llm_gemini_api_key'] = mpu_encrypt_api_key($gemini_key);
            $mpu_opt['ai_api_key'] = $mpu_opt['llm_gemini_api_key']; // 向後兼容
        }
        if (!empty($openai_key) && !mpu_is_api_key_encrypted($openai_key)) {
            $mpu_opt['llm_openai_api_key'] = mpu_encrypt_api_key($openai_key);
            $mpu_opt['openai_api_key'] = $mpu_opt['llm_openai_api_key']; // 向後兼容
        }
        if (!empty($claude_key) && !mpu_is_api_key_encrypted($claude_key)) {
            $mpu_opt['llm_claude_api_key'] = mpu_encrypt_api_key($claude_key);
            $mpu_opt['claude_api_key'] = $mpu_opt['llm_claude_api_key']; // 向後兼容
        }

        // 保存各提供商的模型選擇
        if (isset($_POST['llm_gemini_model'])) {
            $mpu_opt['llm_gemini_model'] = sanitize_text_field($_POST['llm_gemini_model']);
            $mpu_opt['gemini_model'] = $mpu_opt['llm_gemini_model']; // 向後兼容
        }
        if (isset($_POST['llm_openai_model'])) {
            $mpu_opt['llm_openai_model'] = sanitize_text_field($_POST['llm_openai_model']);
            $mpu_opt['openai_model'] = $mpu_opt['llm_openai_model']; // 向後兼容
        }
        if (isset($_POST['llm_claude_model'])) {
            $mpu_opt['llm_claude_model'] = sanitize_text_field($_POST['llm_claude_model']);
            $mpu_opt['claude_model'] = $mpu_opt['llm_claude_model']; // 向後兼容
        }

        // 保存 Ollama 設定
        if (isset($_POST['ollama_endpoint'])) {
            $validated = mpu_validate_ollama_endpoint(esc_url_raw(wp_unslash($_POST['ollama_endpoint'])));
            $mpu_opt['ollama_endpoint'] = is_wp_error($validated) ? 'http://localhost:11434' : $validated;
        }
        if (isset($_POST['ollama_model'])) {
            $mpu_opt['ollama_model'] = sanitize_text_field($_POST['ollama_model']);
        }
        $mpu_opt['ollama_disable_thinking'] = isset($_POST['ollama_disable_thinking']) && $_POST['ollama_disable_thinking'] ? true : false;

        // 保存「使用 LLM 取代內建對話」設定
        $mpu_opt['llm_replace_dialogue'] = isset($_POST['llm_replace_dialogue']) && $_POST['llm_replace_dialogue'] ? true : false;
        if ($mpu_opt['llm_replace_dialogue'] && isset($mpu_opt['llm_provider']) && $mpu_opt['llm_provider'] === 'ollama') {
            $mpu_opt['ollama_replace_dialogue'] = true;
        }

        // 保存「啟用互動對話功能」設定
        $mpu_opt['enable_chat_mode'] = isset($_POST['enable_chat_mode']) && $_POST['enable_chat_mode'] ? true : false;

        // 保存天氣設定
        $mpu_opt['weather_enabled'] = isset($_POST['weather_enabled']) && $_POST['weather_enabled'] ? true : false;
        $mpu_opt['weather_latitude'] = isset($_POST['weather_latitude']) ? max(-90.0, min(90.0, floatval($_POST['weather_latitude']))) : 25.0330;
        $mpu_opt['weather_longitude'] = isset($_POST['weather_longitude']) ? max(-180.0, min(180.0, floatval($_POST['weather_longitude']))) : 121.5654;

        // 保存 API 快取設定
        $mpu_opt['api_cache_enabled'] = isset($_POST['api_cache_enabled']) && $_POST['api_cache_enabled'] ? true : false;
        $mpu_opt['api_cache_ttl'] = isset($_POST['api_cache_ttl']) ? max(60, min(604800, intval($_POST['api_cache_ttl']))) : 3600;

        $text = '<div class="updated"><p><strong>' . __('LLM 設定已儲存', 'mp-ukagaka') . '</strong></p></div>';
    } elseif (isset($_POST['submit_diary'])) {
        // 處理日記設定
        $current_opt = mpu_get_option();

        // 保存基本設定
        $mpu_opt['diary_enabled'] = isset($_POST['diary_enabled']) && $_POST['diary_enabled'] ? true : false;
        $mpu_opt['diary_category'] = isset($_POST['diary_category']) ? intval($_POST['diary_category']) : 0;
        $mpu_opt['diary_author'] = isset($_POST['diary_author']) ? intval($_POST['diary_author']) : get_current_user_id();
        $mpu_opt['diary_trigger_rate'] = isset($_POST['diary_trigger_rate']) ? max(1, min(10, intval($_POST['diary_trigger_rate']))) : 2;
        $mpu_opt['diary_signature'] = isset($_POST['diary_signature']) ? sanitize_text_field($_POST['diary_signature']) : '';

        // 保存 AI 供應商設定
        if (isset($_POST['diary_provider'])) {
            $allowed_providers = ['gemini', 'openai', 'claude', 'ollama'];
            $mpu_opt['diary_provider'] = in_array($_POST['diary_provider'], $allowed_providers, true) ? $_POST['diary_provider'] : 'gemini';
        }

        // 處理各提供商的 API Key（加密存儲）
        $diary_gemini_key = isset($_POST['diary_gemini_api_key']) ? sanitize_text_field($_POST['diary_gemini_api_key']) : '';
        $diary_openai_key = isset($_POST['diary_openai_api_key']) ? sanitize_text_field($_POST['diary_openai_api_key']) : '';
        $diary_claude_key = isset($_POST['diary_claude_api_key']) ? sanitize_text_field($_POST['diary_claude_api_key']) : '';

        if (!empty($diary_gemini_key) && !mpu_is_api_key_encrypted($diary_gemini_key)) {
            $mpu_opt['diary_gemini_api_key'] = mpu_encrypt_api_key($diary_gemini_key);
        }
        if (!empty($diary_openai_key) && !mpu_is_api_key_encrypted($diary_openai_key)) {
            $mpu_opt['diary_openai_api_key'] = mpu_encrypt_api_key($diary_openai_key);
        }
        if (!empty($diary_claude_key) && !mpu_is_api_key_encrypted($diary_claude_key)) {
            $mpu_opt['diary_claude_api_key'] = mpu_encrypt_api_key($diary_claude_key);
        }

        // 保存模型選擇
        if (isset($_POST['diary_gemini_model'])) {
            $mpu_opt['diary_gemini_model'] = sanitize_text_field($_POST['diary_gemini_model']);
        }
        if (isset($_POST['diary_openai_model'])) {
            $mpu_opt['diary_openai_model'] = sanitize_text_field($_POST['diary_openai_model']);
        }
        if (isset($_POST['diary_claude_model'])) {
            $mpu_opt['diary_claude_model'] = sanitize_text_field($_POST['diary_claude_model']);
        }

        // 保存 Ollama 設定
        if (isset($_POST['diary_ollama_endpoint'])) {
            $raw_endpoint = trim(esc_url_raw(wp_unslash($_POST['diary_ollama_endpoint'])));
            if (empty($raw_endpoint)) {
                // 空字串保留，表示沿用 LLM 設定頁的主端點
                $mpu_opt['diary_ollama_endpoint'] = '';
            } else {
                $validated = mpu_validate_ollama_endpoint($raw_endpoint);
                $mpu_opt['diary_ollama_endpoint'] = is_wp_error($validated) ? '' : $validated;
            }
        }
        if (isset($_POST['diary_ollama_model'])) {
            $mpu_opt['diary_ollama_model'] = sanitize_text_field($_POST['diary_ollama_model']);
        }

        $text = '<div class="updated"><p><strong>' . __('日記設定已儲存', 'mp-ukagaka') . '</strong></p></div>';
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
        if ($cur_page < 0 || $cur_page > 8) {
            $cur_page = 0;
        }

        // 構建重定向 URL
        $redirect_url = admin_url('options-general.php?page=mp-ukagaka/options.php&cur_page=' . $cur_page . '&settings-updated=true');

        // 執行重定向
        wp_safe_redirect($redirect_url);
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
        if (function_exists('mpu_log_error')) {
            mpu_log_error('無法創建對話目錄', 'dialog_gen');
        } else {
             error_log('MP Ukagaka: 無法創建對話目錄');
        }
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
        if (function_exists('mpu_log_error')) {
            mpu_log_error('文件寫入失敗 - ' . $result->get_error_message(), 'dialog_gen');
        } else {
            error_log('MP Ukagaka: 文件寫入失敗 - ' . $result->get_error_message());
        }
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


/**
 * 從 ZIP 文件中讀取 manifest.json
 * 
 * @param string $zip_path ZIP 文件路徑
 * @return array|WP_Error manifest.json 數據或錯誤
 */
function mpu_get_ghost_manifest_from_zip($zip_path)
{
    if (!class_exists('ZipArchive')) {
        return new WP_Error('zip_not_supported', __('PHP ZipArchive 類別不可用，請聯繫服務器管理員。', 'mp-ukagaka'));
    }

    $zip = new ZipArchive();
    $result = $zip->open($zip_path);

    if ($result !== true) {
        return new WP_Error('zip_open_failed', __('無法打開 ZIP 文件。', 'mp-ukagaka'));
    }

    // 查找 manifest.json
    $manifest_content = false;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $filename = $zip->getNameIndex($i);
        if ($filename === 'manifest.json' || basename($filename) === 'manifest.json') {
            $manifest_content = $zip->getFromIndex($i);
            break;
        }
    }

    $zip->close();

    if ($manifest_content === false) {
        return new WP_Error('manifest_not_found', __('ZIP 文件中找不到 manifest.json。', 'mp-ukagaka'));
    }

    $manifest_data = json_decode($manifest_content, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return new WP_Error('invalid_json', __('manifest.json 格式無效：', 'mp-ukagaka') . json_last_error_msg());
    }

    return $manifest_data;
}

/**
 * 驗證 ZIP 文件結構
 * 
 * @param string $zip_path ZIP 文件路徑
 * @return array|WP_Error 驗證結果（包含 manifest 數據和 shell 信息）或錯誤
 */
function mpu_validate_ghost_zip($zip_path)
{
    if (!class_exists('ZipArchive')) {
        return new WP_Error('zip_not_supported', __('PHP ZipArchive 類別不可用，請聯繫服務器管理員。', 'mp-ukagaka'));
    }

    // 讀取 manifest.json
    $manifest_data = mpu_get_ghost_manifest_from_zip($zip_path);
    if (is_wp_error($manifest_data)) {
        return $manifest_data;
    }

    // 驗證 manifest.json 必須包含 id 字段
    if (empty($manifest_data['id'])) {
        return new WP_Error('missing_id', __('manifest.json 中缺少必需的 id 字段。', 'mp-ukagaka'));
    }

    $zip = new ZipArchive();
    $result = $zip->open($zip_path);

    if ($result !== true) {
        return new WP_Error('zip_open_failed', __('無法打開 ZIP 文件。', 'mp-ukagaka'));
    }

    // 安全檢查：遍歷 ZIP 內的所有文件
    $has_shell = false;
    $shell_files = [];
    // 允許的副檔名白名單 (嚴格限制)
    $allowed_extensions = ['json', 'txt', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'mp3', 'wav', 'ogg', 'ico'];
    // 允許的 shell 圖片副檔名
    $shell_img_extensions = ['png', 'jpg', 'jpeg', 'gif', 'webp'];

    // 防護：拒絕超大 ZIP（避免記憶體耗盡）
    if ($zip->numFiles > 1000) {
        $zip->close();
        return new WP_Error('too_many_files', __('ZIP 文件包含過多檔案（超過 1,000 個），請檢查 ZIP 內容。', 'mp-ukagaka'));
    }

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $filename = $zip->getNameIndex($i);
        $basename = basename($filename);

        // 1. 檢查路徑遍歷 (Zip Slip) 和絕對路徑
        // 檢查相對路徑遍歷
        if (strpos($filename, '../') !== false || strpos($filename, '..\\') !== false) {
            $zip->close();
            return new WP_Error('invalid_path', __('ZIP 文件包含非法路徑 (..)，因安全原因被拒絕。', 'mp-ukagaka'));
        }
        // 檢查絕對路徑（Unix 系統以 / 開頭，Windows 系統以驅動器字母開頭如 C:）
        if (substr($filename, 0, 1) === '/' || preg_match('/^[a-zA-Z]:[\\\\\/]/', $filename)) {
            $zip->close();
            return new WP_Error('invalid_path', __('ZIP 文件包含絕對路徑，因安全原因被拒絕。', 'mp-ukagaka'));
        }
        // 檢查空字節注入（雖然 ZIP 通常不會有，但為安全起見）
        if (strpos($filename, "\0") !== false) {
            $zip->close();
            return new WP_Error('invalid_path', __('ZIP 文件包含非法字元（空字節），因安全原因被拒絕。', 'mp-ukagaka'));
        }

        // 跳過目錄項目
        if (substr($filename, -1) === '/') {
            continue;
        }

        // 2. 檢查副檔名白名單
        $extension = strtolower(pathinfo($basename, PATHINFO_EXTENSION));
        if (!in_array($extension, $allowed_extensions)) {
            $zip->close();
            return new WP_Error('invalid_file_type', sprintf(__('ZIP 文件包含不允許的檔案類型 (.%s)，僅允許圖片、音訊、TXT 和 JSON。', 'mp-ukagaka'), $extension));
        }

        // 檢查是否在 shell/ 目錄下的圖片
        if (strpos($filename, 'shell/') === 0 || strpos($filename, 'shell\\') === 0) {
            if (in_array($extension, $shell_img_extensions)) {
                $has_shell = true;
                $shell_files[] = $basename;
            }
        }
    }

    $zip->close();

    if (!$has_shell) {
        return new WP_Error('shell_not_found', __('ZIP 文件中找不到 shell/ 文件夾。', 'mp-ukagaka'));
    }

    if (empty($shell_files)) {
        return new WP_Error('no_shell_images', __('shell/ 文件夾中沒有找到圖片文件（.png, .jpg, .jpeg, .gif, .webp）。', 'mp-ukagaka'));
    }

    return [
        'manifest' => $manifest_data,
        'shell_files' => $shell_files,
    ];
}

/**
 * 解壓 ZIP 文件到目標目錄
 * 
 * @param string $zip_path ZIP 文件路徑
 * @param string $target_dir 目標目錄
 * @return bool|WP_Error 成功返回 true，失敗返回錯誤
 */
function mpu_extract_ghost_zip($zip_path, $target_dir)
{
    if (!class_exists('ZipArchive')) {
        return new WP_Error('zip_not_supported', __('PHP ZipArchive 類別不可用，請聯繫服務器管理員。', 'mp-ukagaka'));
    }

    // 確保目標目錄存在
    if (!file_exists($target_dir)) {
        if (!wp_mkdir_p($target_dir)) {
            return new WP_Error('mkdir_failed', __('無法創建目標目錄：', 'mp-ukagaka') . $target_dir);
        }
    }

    // 驗證目標目錄路徑安全性（必須在 ghost 目錄下）
    $real_target = realpath($target_dir);
    $ghost_dir = mpu_get_personalities_dir();
    $real_ghost = realpath($ghost_dir);

    if ($real_ghost === false || $real_target === false) {
        return new WP_Error('invalid_path', __('無效的目錄路徑。', 'mp-ukagaka'));
    }

    if (strpos($real_target, $real_ghost) !== 0) {
        return new WP_Error('path_not_allowed', __('目標目錄不在允許範圍內。', 'mp-ukagaka'));
    }

    $zip = new ZipArchive();
    $result = $zip->open($zip_path);

    if ($result !== true) {
        return new WP_Error('zip_open_failed', __('無法打開 ZIP 文件。', 'mp-ukagaka'));
    }

    // 解壓文件（ZipArchive::extractTo 會自動處理路徑規範化，但我們已在驗證階段檢查過路徑安全）
    $extract_result = $zip->extractTo($target_dir);
    $zip->close();

    if (!$extract_result) {
        return new WP_Error('extract_failed', __('解壓文件失敗。', 'mp-ukagaka'));
    }

    // 驗證解壓後的文件結構
    if (!file_exists($target_dir . '/manifest.json')) {
        return new WP_Error('manifest_missing_after_extract', __('解壓後找不到 manifest.json。', 'mp-ukagaka'));
    }

    $shell_path = $target_dir . '/shell';
    if (!file_exists($shell_path) && !is_dir($shell_path)) {
        return new WP_Error('shell_missing_after_extract', __('解壓後找不到 shell/ 目錄。', 'mp-ukagaka'));
    }

    return true;
}

/**
 * 遞歸刪除目錄及其所有內容
 * 作為 WP_Filesystem 的備用方法使用
 *
 * @param string $dir 要刪除的目錄路徑
 * @return bool 成功返回 true，目錄不存在返回 false
 */
function mpu_recursive_rmdir($dir)
{
    if (!is_dir($dir)) {
        return false;
    }
    $files = array_diff(scandir($dir), array('.', '..'));
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        is_dir($path) ? mpu_recursive_rmdir($path) : unlink($path);
    }
    return rmdir($dir);
}

/**
 * 處理 Ghost ZIP 文件上傳
 *
 * @return array|WP_Error 成功返回預覽數據數組，失敗返回錯誤
 */
function mpu_handle_ghost_zip_upload()
{
    // 檢查文件上傳
    if (empty($_FILES['ghost_zip_file']) || $_FILES['ghost_zip_file']['error'] !== UPLOAD_ERR_OK) {
        $error_code = $_FILES['ghost_zip_file']['error'] ?? UPLOAD_ERR_NO_FILE;
        switch ($error_code) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return new WP_Error('upload_size_exceeded', __('上傳的文件太大。', 'mp-ukagaka'));
            case UPLOAD_ERR_PARTIAL:
                return new WP_Error('upload_partial', __('文件上傳不完整。', 'mp-ukagaka'));
            case UPLOAD_ERR_NO_FILE:
                return new WP_Error('no_file', __('請選擇要上傳的 ZIP 文件。', 'mp-ukagaka'));
            default:
                return new WP_Error('upload_error', __('文件上傳失敗。', 'mp-ukagaka'));
        }
    }

    $file = $_FILES['ghost_zip_file'];

    // 驗證文件類型
    $file_type = wp_check_filetype($file['name']);
    if ($file_type['ext'] !== 'zip') {
        return new WP_Error('invalid_file_type', __('只能上傳 ZIP 格式的文件。', 'mp-ukagaka'));
    }

    // 驗證文件大小（50MB 限制）
    $max_size = 50 * 1024 * 1024; // 50MB
    if ($file['size'] > $max_size) {
        return new WP_Error('file_too_large', __('文件大小不能超過 50MB。', 'mp-ukagaka'));
    }

    // 使用 WordPress 文件上傳處理函數
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    $uploaded_file = wp_handle_upload($file, array('test_form' => false));

    if (isset($uploaded_file['error'])) {
        return new WP_Error('upload_handle_error', $uploaded_file['error']);
    }

    $zip_path = $uploaded_file['file'];

    // 驗證 ZIP 文件結構
    $validation_result = mpu_validate_ghost_zip($zip_path);
    if (is_wp_error($validation_result)) {
        @unlink($zip_path); // 清理上傳的文件
        return $validation_result;
    }

    $manifest_data = $validation_result['manifest'];
    $ghost_id = $manifest_data['id'];

    // 確定目標目錄
    $ghost_dir = mpu_get_personalities_dir();
    $target_dir = $ghost_dir . '/' . sanitize_file_name($ghost_id);

    // 如果目錄已存在，覆蓋（先刪除現有目錄）
    if (file_exists($target_dir) && is_dir($target_dir)) {
        // 使用 WP_Filesystem 或遞歸刪除
        global $wp_filesystem;
        if (empty($wp_filesystem)) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            WP_Filesystem();
        }

        if ($wp_filesystem && $wp_filesystem->is_dir($target_dir)) {
            $wp_filesystem->rmdir($target_dir, true);
        } else {
            // 備用方法：使用 PHP 遞歸刪除
            mpu_recursive_rmdir($target_dir);
        }
    }

    // 解壓 ZIP 文件
    $extract_result = mpu_extract_ghost_zip($zip_path, $target_dir);

    // 清理臨時上傳的 ZIP 文件
    @unlink($zip_path);

    if (is_wp_error($extract_result)) {
        return $extract_result;
    }

    // 生成 shell URL
    $main_file = defined('MPU_MAIN_FILE') ? MPU_MAIN_FILE : dirname(dirname(__FILE__)) . '/mp-ukagaka.php';
    $shell_url = plugins_url("ghost/{$ghost_id}/shell/", $main_file);

    // 準備預覽數據
    $preview_data = array(
        'id' => $ghost_id,
        'name' => !empty($manifest_data['name_zh']) ? $manifest_data['name_zh'] : (!empty($manifest_data['name']) ? $manifest_data['name'] : $ghost_id),
        'name_en' => !empty($manifest_data['name_en']) ? $manifest_data['name_en'] : '',
        'shell_url' => $shell_url,
        'version' => !empty($manifest_data['version']) ? $manifest_data['version'] : '',
        'author' => !empty($manifest_data['author']) ? $manifest_data['author'] : '',
        'description' => !empty($manifest_data['description_zh']) ? $manifest_data['description_zh'] : (!empty($manifest_data['description']) ? $manifest_data['description'] : ''),
    );

    return $preview_data;
}
