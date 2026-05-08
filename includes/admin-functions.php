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


// =========================================================================
// Admin Settings Save Helpers — 各設定頁面儲存邏輯
// 每個 helper 修改 $mpu_opt（by reference）並回傳成功提示訊息 HTML。
// =========================================================================

function mpu_save_general_settings(array &$mpu_opt): string {
    $mpu_opt['show_ukagaka']  = isset($_POST['show_ukagaka']);
    $mpu_opt['show_msg']      = isset($_POST['show_msg']);
    $mpu_opt['default_msg']   = isset($_POST['default_msg'][0]) ? intval($_POST['default_msg'][0]) : 0;
    $mpu_opt['next_msg']      = isset($_POST['next_msg'][0]) ? intval($_POST['next_msg'][0]) : 0;
    $mpu_opt['click_ukagaka'] = isset($_POST['click_ukagaka'][0]) ? intval($_POST['click_ukagaka'][0]) : 0;
    $cur = isset($_POST['cur_ukagaka']) ? mpu_sanitize_personality_id(sanitize_text_field($_POST['cur_ukagaka'])) : '';
    $mpu_opt['cur_ukagaka']   = !empty($cur) ? $cur : 'default_1';
    $mpu_opt['no_style']      = isset($_POST['no_style']);
    $mpu_opt['custom_style_link'] = isset($_POST['custom_style_link']) ? wp_kses($_POST['custom_style_link'], [
        'link' => ['rel' => true, 'href' => true, 'type' => true, 'media' => true, 'id' => true],
    ]) : '';
    $mpu_opt['no_page']              = isset($_POST['no_page']) ? sanitize_textarea_field($_POST['no_page']) : '';
    $mpu_opt['use_external_file']    = true;
    $mpu_opt['external_file_format'] = (isset($_POST['external_file_format'][0]) && in_array($_POST['external_file_format'][0], ['txt', 'json'], true))
        ? $_POST['external_file_format'][0] : 'txt';
    $mpu_opt['auto_talk']          = isset($_POST['auto_talk']);
    $mpu_opt['auto_talk_interval'] = isset($_POST['auto_talk_interval']) ? max(3, min(30, intval($_POST['auto_talk_interval']))) : 8;
    $mpu_opt['typewriter_speed']   = isset($_POST['typewriter_speed']) ? max(10, min(200, intval($_POST['typewriter_speed']))) : 40;
    $mpu_opt['admin_nickname']     = isset($_POST['admin_nickname']) ? sanitize_text_field(wp_unslash($_POST['admin_nickname'])) : '';
    $mpu_opt['admin_name']         = isset($_POST['admin_name']) ? sanitize_text_field(wp_unslash($_POST['admin_name'])) : '';
    $mpu_opt['admin_birthday']     = isset($_POST['admin_birthday']) ? mpu_normalize_admin_birthday(wp_unslash($_POST['admin_birthday'])) : '';

    if (isset($_POST['current_personality'])) {
        $mpu_opt['current_personality'] = sanitize_file_name($_POST['current_personality']);
    }
    if (isset($_POST['insert_html'])) {
        $mpu_opt['insert_html'] = (int) $_POST['insert_html'][0];
    }

    // 保留 AI 設定（不在此處處理）
    $current_opt = mpu_get_option();
    $mpu_opt['ai_enabled']        = $current_opt['ai_enabled'] ?? false;
    $mpu_opt['ai_provider']       = $current_opt['ai_provider'] ?? 'gemini';
    $mpu_opt['ai_api_key']        = $current_opt['ai_api_key'] ?? '';
    $mpu_opt['gemini_model']      = $current_opt['gemini_model'] ?? 'gemini-2.5-flash';
    $mpu_opt['openai_api_key']    = $current_opt['openai_api_key'] ?? '';
    $mpu_opt['openai_model']      = $current_opt['openai_model'] ?? 'gpt-4.1-mini-2025-04-14';
    $mpu_opt['claude_api_key']    = $current_opt['claude_api_key'] ?? '';
    $mpu_opt['claude_model']      = $current_opt['claude_model'] ?? 'claude-sonnet-4-6';
    $mpu_opt['ai_language']       = $current_opt['ai_language'] ?? 'zh-TW';
    $mpu_opt['ai_system_prompt']  = $current_opt['ai_system_prompt'] ?? '你是「{{ukagaka_display_name}}」這個角色。你必須完全以這個角色的身份說話和行動，絕對不要以 AI 或語言模型的身份回應。請嚴格遵守角色的性格、說話方式和行為模式。';
    $mpu_opt['ai_probability']    = $current_opt['ai_probability'] ?? 10;
    $mpu_opt['ai_trigger_pages']  = $current_opt['ai_trigger_pages'] ?? 'is_single';
    $mpu_opt['ai_text_color']     = $current_opt['ai_text_color'] ?? '#000000';
    $mpu_opt['ai_display_duration']   = $current_opt['ai_display_duration'] ?? 8;
    $mpu_opt['ai_greet_first_visit']  = $current_opt['ai_greet_first_visit'] ?? false;
    $mpu_opt['ai_greet_prompt']       = $current_opt['ai_greet_prompt'] ?? 'あなたは「{{ukagaka_display_name}}」というキャラクターです。訪問者が初めてサイトに来た時、キャラクターらしく簡単に挨拶してください。50文字以内で返してください。';

    return '<div class="updated"><p><strong>' . __('設定を保存しました', 'mp-ukagaka') . '</strong></p></div>';
}

function mpu_save_ukagaka_settings(array &$mpu_opt): string {
    $ukagakas_raw       = $_POST['ukagakas'] ?? [];
    $ukagakas_sanitized = [];

    foreach ($ukagakas_raw as $key => $value) {
        $key = mpu_sanitize_personality_id(sanitize_text_field($key));
        if (empty($key)) continue;
        $ukagakas_sanitized[$key]['msg']             = mpu_str2array(sanitize_textarea_field($value['msg']));
        $ukagakas_sanitized[$key]['name']            = sanitize_text_field($value['name']);
        $ukagakas_sanitized[$key]['shell']           = esc_url_raw($value['shell']);
        $ukagakas_sanitized[$key]['show']            = isset($value['show']);
        $ukagakas_sanitized[$key]['dialog_filename'] = isset($value['dialog_filename']) ? sanitize_file_name($value['dialog_filename']) : $key;

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

    $message = __('キャラクター設定を更新しました', 'mp-ukagaka');
    if (isset($_POST['generate_dialog_file'])) {
        $message .= __('。ダイアログファイルを生成しました', 'mp-ukagaka');
    }
    return '<div class="updated"><p><strong>' . $message . '</strong></p></div>';
}

function mpu_save_ai_settings(array &$mpu_opt): string {
    $current_opt = mpu_get_option();

    // 保留通用設定（不在 AI 設定頁面處理）
    foreach (['show_ukagaka', 'show_msg', 'default_msg', 'next_msg', 'click_ukagaka',
              'cur_ukagaka', 'no_style', 'no_page', 'external_file_format', 'auto_talk',
              'auto_talk_interval', 'typewriter_speed', 'insert_html', 'ukagakas', 'extend',
              'auto_msg', 'common_msg'] as $key) {
        $mpu_opt[$key] = $current_opt[$key] ?? null;
    }
    $mpu_opt['use_external_file'] = true;

    $mpu_opt['ai_language']       = isset($_POST['ai_language']) ? sanitize_text_field($_POST['ai_language']) : 'zh-TW';
    $mpu_opt['ai_system_prompt']  = isset($_POST['ai_system_prompt']) ? wp_kses_post(wp_unslash($_POST['ai_system_prompt'])) : 'あなたは「{{ukagaka_display_name}}」というキャラクターです。必ずこのキャラクターとして振る舞い、一人称は「私」を使用してください。回答は日本語で、50文字以内の短い一言で返してください。自分が AI や Qwen だと言わないでください。';
    $mpu_opt['ai_probability']    = isset($_POST['ai_probability']) ? max(1, min(100, intval($_POST['ai_probability']))) : 10;
    $mpu_opt['ai_max_tokens']     = isset($_POST['ai_max_tokens']) ? max(100, min(8192, intval($_POST['ai_max_tokens']))) : 1000;
    $mpu_opt['ai_trigger_pages']  = isset($_POST['ai_trigger_pages']) ? sanitize_text_field($_POST['ai_trigger_pages']) : 'is_single';
    $mpu_opt['ai_text_color']     = isset($_POST['ai_text_color']) ? sanitize_hex_color($_POST['ai_text_color']) : '#000000';
    $mpu_opt['ai_display_duration']  = isset($_POST['ai_display_duration']) ? max(1, min(60, intval($_POST['ai_display_duration']))) : 8;
    $mpu_opt['ai_greet_first_visit'] = !empty($_POST['ai_greet_first_visit']);
    $mpu_opt['ai_greet_prompt']      = isset($_POST['ai_greet_prompt']) ? wp_kses_post(wp_unslash($_POST['ai_greet_prompt'])) : 'あなたは「{{ukagaka_display_name}}」というキャラクターです。訪問者が初めてサイトに来た時、キャラクターらしく簡単に挨拶してください。50文字以内で返してください。';

    return '<div class="updated"><p><strong>' . __('AI 設定を保存しました', 'mp-ukagaka') . '</strong></p></div>';
}

function mpu_save_llm_settings(array &$mpu_opt): string {
    $mpu_opt['ai_enabled'] = !empty($_POST['ai_enabled']);

    if (isset($_POST['llm_provider'])) {
        $allowed = ['gemini', 'openai', 'claude', 'ollama'];
        $provider = in_array($_POST['llm_provider'], $allowed, true) ? $_POST['llm_provider'] : 'gemini';
        $mpu_opt['llm_provider'] = $provider;
        $mpu_opt['ai_provider']  = $provider;
    }

    $gemini_key = isset($_POST['llm_gemini_api_key']) ? sanitize_text_field($_POST['llm_gemini_api_key']) : '';
    $openai_key = isset($_POST['llm_openai_api_key']) ? sanitize_text_field($_POST['llm_openai_api_key']) : '';
    $claude_key = isset($_POST['llm_claude_api_key']) ? sanitize_text_field($_POST['llm_claude_api_key']) : '';

    if (!empty($gemini_key) && !mpu_is_api_key_encrypted($gemini_key)) {
        $mpu_opt['llm_gemini_api_key'] = mpu_encrypt_api_key($gemini_key);
        $mpu_opt['ai_api_key']         = $mpu_opt['llm_gemini_api_key'];
    }
    if (!empty($openai_key) && !mpu_is_api_key_encrypted($openai_key)) {
        $mpu_opt['llm_openai_api_key'] = mpu_encrypt_api_key($openai_key);
        $mpu_opt['openai_api_key']     = $mpu_opt['llm_openai_api_key'];
    }
    if (!empty($claude_key) && !mpu_is_api_key_encrypted($claude_key)) {
        $mpu_opt['llm_claude_api_key'] = mpu_encrypt_api_key($claude_key);
        $mpu_opt['claude_api_key']     = $mpu_opt['llm_claude_api_key'];
    }

    if (isset($_POST['llm_gemini_model'])) {
        $mpu_opt['llm_gemini_model'] = sanitize_text_field($_POST['llm_gemini_model']);
        $mpu_opt['gemini_model']     = $mpu_opt['llm_gemini_model'];
    }
    if (isset($_POST['llm_openai_model'])) {
        $mpu_opt['llm_openai_model'] = sanitize_text_field($_POST['llm_openai_model']);
        $mpu_opt['openai_model']     = $mpu_opt['llm_openai_model'];
    }
    if (isset($_POST['llm_claude_model'])) {
        $mpu_opt['llm_claude_model'] = sanitize_text_field($_POST['llm_claude_model']);
        $mpu_opt['claude_model']     = $mpu_opt['llm_claude_model'];
    }

    if (isset($_POST['ollama_endpoint'])) {
        $validated = mpu_validate_ollama_endpoint(esc_url_raw(wp_unslash($_POST['ollama_endpoint'])));
        $mpu_opt['ollama_endpoint'] = is_wp_error($validated) ? 'http://localhost:11434' : $validated;
    }
    if (isset($_POST['ollama_model'])) {
        $mpu_opt['ollama_model'] = sanitize_text_field($_POST['ollama_model']);
    }
    $mpu_opt['ollama_disable_thinking'] = !empty($_POST['ollama_disable_thinking']);

    $mpu_opt['llm_replace_dialogue'] = !empty($_POST['llm_replace_dialogue']);
    if ($mpu_opt['llm_replace_dialogue'] && isset($mpu_opt['llm_provider']) && $mpu_opt['llm_provider'] === 'ollama') {
        $mpu_opt['ollama_replace_dialogue'] = true;
    }

    $mpu_opt['enable_chat_mode'] = !empty($_POST['enable_chat_mode']);

    $mpu_opt['weather_enabled']   = !empty($_POST['weather_enabled']);
    $mpu_opt['weather_latitude']  = isset($_POST['weather_latitude']) ? max(-90.0, min(90.0, floatval($_POST['weather_latitude']))) : 25.0330;
    $mpu_opt['weather_longitude'] = isset($_POST['weather_longitude']) ? max(-180.0, min(180.0, floatval($_POST['weather_longitude']))) : 121.5654;

    $mpu_opt['api_cache_enabled'] = !empty($_POST['api_cache_enabled']);
    $mpu_opt['api_cache_ttl']     = isset($_POST['api_cache_ttl']) ? max(60, min(604800, intval($_POST['api_cache_ttl']))) : 3600;

    return '<div class="updated"><p><strong>' . __('LLM 設定を保存しました', 'mp-ukagaka') . '</strong></p></div>';
}

function mpu_save_diary_settings(array &$mpu_opt): string {
    $mpu_opt['diary_enabled']      = !empty($_POST['diary_enabled']);
    $mpu_opt['diary_category']     = isset($_POST['diary_category']) ? intval($_POST['diary_category']) : 0;
    $mpu_opt['diary_author']       = isset($_POST['diary_author']) ? intval($_POST['diary_author']) : get_current_user_id();
    $mpu_opt['diary_trigger_rate'] = isset($_POST['diary_trigger_rate']) ? max(1, min(10, intval($_POST['diary_trigger_rate']))) : 2;
    $mpu_opt['diary_signature']    = isset($_POST['diary_signature']) ? sanitize_text_field($_POST['diary_signature']) : '';

    if (isset($_POST['diary_provider'])) {
        $allowed = ['gemini', 'openai', 'claude', 'ollama'];
        $mpu_opt['diary_provider'] = in_array($_POST['diary_provider'], $allowed, true) ? $_POST['diary_provider'] : 'gemini';
    }

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

    if (isset($_POST['diary_gemini_model'])) $mpu_opt['diary_gemini_model'] = sanitize_text_field($_POST['diary_gemini_model']);
    if (isset($_POST['diary_openai_model'])) $mpu_opt['diary_openai_model'] = sanitize_text_field($_POST['diary_openai_model']);
    if (isset($_POST['diary_claude_model'])) $mpu_opt['diary_claude_model'] = sanitize_text_field($_POST['diary_claude_model']);

    if (isset($_POST['diary_ollama_endpoint'])) {
        $raw = trim(esc_url_raw(wp_unslash($_POST['diary_ollama_endpoint'])));
        if (empty($raw)) {
            $mpu_opt['diary_ollama_endpoint'] = '';
        } else {
            $validated = mpu_validate_ollama_endpoint($raw);
            $mpu_opt['diary_ollama_endpoint'] = is_wp_error($validated) ? '' : $validated;
        }
    }
    if (isset($_POST['diary_ollama_model'])) {
        $mpu_opt['diary_ollama_model'] = sanitize_text_field($_POST['diary_ollama_model']);
    }

    return '<div class="updated"><p><strong>' . __('日記設定を保存しました', 'mp-ukagaka') . '</strong></p></div>';
}

function mpu_save_bot_blocker_settings(array &$mpu_opt): string {
    $current_opt = mpu_get_option();
    $bb = isset($current_opt['bot_blocker']) && is_array($current_opt['bot_blocker'])
        ? $current_opt['bot_blocker'] : [];

    $bb['enabled']     = !empty($_POST['bot_blocker_enabled']);
    $bb['auto_ban_ip'] = !empty($_POST['bot_blocker_auto_ban_ip']);

    if (isset($_POST['bot_blocker_banned_fingerprints'])) {
        $lines = explode("\n", sanitize_textarea_field($_POST['bot_blocker_banned_fingerprints']));
        $bb['banned_fingerprints'] = array_values(array_filter(
            array_map('trim', $lines),
            function($h) { return preg_match('/^[a-f0-9]{32}$/i', $h); }
        ));
    }
    if (isset($_POST['bot_blocker_suspicious_resolutions'])) {
        $lines = explode("\n", sanitize_textarea_field($_POST['bot_blocker_suspicious_resolutions']));
        $bb['suspicious_resolutions'] = array_values(array_filter(
            array_map('trim', $lines),
            function($r) { return preg_match('/^\d+x\d+$/i', $r); }
        ));
    }

    $bb['block_status']         = isset($_POST['bot_blocker_block_status']) ? max(400, min(599, intval($_POST['bot_blocker_block_status']))) : 403;
    $bb['hot_transient_ttl']    = isset($_POST['bot_blocker_hot_transient_ttl']) ? max(60, min(86400, intval($_POST['bot_blocker_hot_transient_ttl']))) : 600;
    $bb['rate_limit_threshold'] = isset($_POST['bot_blocker_rate_limit_threshold']) ? max(0, min(1000, intval($_POST['bot_blocker_rate_limit_threshold']))) : 40;
    $bb['max_log_rows']         = isset($_POST['bot_blocker_max_log_rows']) ? max(100, min(10000, intval($_POST['bot_blocker_max_log_rows']))) : 1000;

    $mpu_opt['bot_blocker'] = $bb;

    return '<div class="updated"><p><strong>' . __('Bot 防護設定を保存しました', 'mp-ukagaka') . '</strong></p></div>';
}

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
                set_transient('mpu_admin_message', '<div class="updated"><p><strong>' . sprintf(__('%d 件の統計データを削除しました', 'mp-ukagaka'), $cleared) . '</strong></p></div>', 30);
            }
            wp_safe_redirect(admin_url('options-general.php?page=mp-ukagaka/options.php&cur_page=8'));
            exit;
        }
    }

    // 檢查是否為本外掛的表單提交
    $is_our_submit = isset($_POST['submit1'])     // 通用設定
        || isset($_POST['submit2'])          // 偽春菜們
        || isset($_POST['submit3'])          // 創建偽春菜
        || isset($_POST['submit4'])          // 擴展
        || isset($_POST['submit5'])          // 會話
        || isset($_POST['submit_ai'])        // AI 設定
        || isset($_POST['submit_llm'])       // LLM 設定
        || isset($_POST['submit_diary'])     // 日記設定
        || isset($_POST['submit_reset'])     // 重置設定
        || isset($_POST['submit_upload_zip']) // ZIP 上傳
        || isset($_POST['submit_confirm_ghost_overwrite']) // ZIP 覆蓋確認
        || isset($_POST['submit_bot_blocker']); // Bot 防護

    if (! $is_our_submit) {
        return;
    }

    // 驗證 Nonce
    if (! isset($_POST['_wpnonce']) || ! wp_verify_nonce($_POST['_wpnonce'], 'mp_ukagaka_settings')) {
        add_settings_error('mpu_options', 'nonce_fail', __('セキュリティチェックに失敗しました。', 'mp-ukagaka'));
        return;
    }

    // Nonce 驗證通過，開始處理儲存邏輯

    // 取得當前選項
    $mpu_opt = mpu_get_option();
    $text = ''; // 用於顯示訊息

    if (isset($_POST['submit1'])) {
        $text = mpu_save_general_settings($mpu_opt);
    } elseif (isset($_POST['submit2'])) {
        $text = mpu_save_ukagaka_settings($mpu_opt);
    } elseif (isset($_POST['submit_upload_zip'])) {
        // 處理 ZIP 文件上傳
        if (!function_exists('mpu_handle_ghost_zip_upload')) {
            add_settings_error('mpu_options', 'function_missing', __('ZIP アップロード処理関数が存在しません。', 'mp-ukagaka'));
            return;
        }

        $upload_result = mpu_handle_ghost_zip_upload();

        if (is_wp_error($upload_result)) {
            // 特殊錯誤碼：需要覆蓋確認，轉至確認頁面而非顯示錯誤
            if ($upload_result->get_error_code() === 'ghost_exists_pending_confirm') {
                $redirect_url = admin_url('options-general.php?page=mp-ukagaka/options.php&cur_page=2&overwrite_pending=1');
                wp_safe_redirect($redirect_url);
                exit;
            }
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

    } elseif (isset($_POST['submit_confirm_ghost_overwrite'])) {
        // 管理員確認後，將暫存目錄原子搬移至正式目錄
        $pending_key = 'mpu_ghost_overwrite_' . get_current_user_id();
        $pending = get_transient($pending_key);

        if (!$pending || empty($pending['temp_dir']) || empty($pending['ghost_id'])) {
            add_settings_error('mpu_options', 'overwrite_expired', __('確認データが期限切れです。再度アップロードしてください。', 'mp-ukagaka'));
            return;
        }

        $temp_dir     = $pending['temp_dir'];
        $ghost_id     = $pending['ghost_id'];
        $manifest_data = $pending['manifest'];
        $ghost_dir    = mpu_get_personalities_dir();
        $target_dir   = $ghost_dir . '/' . sanitize_file_name($ghost_id);

        if (!is_dir($temp_dir)) {
            delete_transient($pending_key);
            add_settings_error('mpu_options', 'temp_missing', __('一時ディレクトリが見つかりません。再度アップロードしてください。', 'mp-ukagaka'));
            return;
        }

        // 原子替換：先把舊目錄 rename 成 backup，再把 temp rename 到正式位置
        // 若 rename(temp→target) 失敗，可從 backup 還原，避免留下空目錄
        $backup_dir = null;
        if (file_exists($target_dir)) {
            $backup_dir = $target_dir . '_bak_' . time();
            if (!rename($target_dir, $backup_dir)) {
                mpu_recursive_rmdir($temp_dir);
                delete_transient($pending_key);
                add_settings_error('mpu_options', 'backup_failed', __('既存 Personality のバックアップに失敗しました。', 'mp-ukagaka'));
                return;
            }
        }

        if (!rename($temp_dir, $target_dir)) {
            // 還原 backup（rollback）
            if ($backup_dir && is_dir($backup_dir)) {
                rename($backup_dir, $target_dir);
            }
            mpu_recursive_rmdir($temp_dir);
            delete_transient($pending_key);
            add_settings_error('mpu_options', 'move_failed', __('Personality ディレクトリの移動に失敗しました。', 'mp-ukagaka'));
            return;
        }

        // 成功：清除 backup
        if ($backup_dir && is_dir($backup_dir)) {
            mpu_recursive_rmdir($backup_dir);
        }

        delete_transient($pending_key);

        // 建立預覽資料並轉至預覽頁面
        $main_file = defined('MPU_MAIN_FILE') ? MPU_MAIN_FILE : dirname(dirname(__FILE__)) . '/mp-ukagaka.php';
        $shell_url = plugins_url("ghost/{$ghost_id}/shell/", $main_file);
        $preview_data = [
            'id'          => $ghost_id,
            'name'        => !empty($manifest_data['name_zh']) ? $manifest_data['name_zh'] : (!empty($manifest_data['name']) ? $manifest_data['name'] : $ghost_id),
            'name_en'     => $manifest_data['name_en'] ?? '',
            'shell_url'   => $shell_url,
            'version'     => $manifest_data['version'] ?? '',
            'author'      => $manifest_data['author'] ?? '',
            'description' => !empty($manifest_data['description_zh']) ? $manifest_data['description_zh'] : ($manifest_data['description'] ?? ''),
        ];
        $transient_key = 'mpu_ghost_zip_preview_' . get_current_user_id();
        set_transient($transient_key, $preview_data, HOUR_IN_SECONDS);
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
                add_settings_error('mpu_options', 'preview_mismatch', __('プレビューデータが一致しません。再度アップロードしてください。', 'mp-ukagaka'));
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

            $message = __('キャラクターの作成に成功しました', 'mp-ukagaka');
            $message .= ' ' . __('「キャラクター一覧」ページでダイアログファイルなどの設定を行ってください。', 'mp-ukagaka');
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

        $message = __('キャラクターの作成に成功しました', 'mp-ukagaka');
        if (isset($_POST['generate_dialog_file_new']) && $_POST['generate_dialog_file_new'] == 'true') {
            $message .= __('。ダイアログファイルを生成しました', 'mp-ukagaka');
        }
        $text = '<div class="updated"><p><strong>' . $message . '</strong></p></div>';
        }
    } elseif (isset($_POST['submit4'])) {
        // 處理擴展設定
        $extend = $_POST['extend'] ?? [];
        // js_area 輸出 raw JavaScript，需要 unfiltered_html 權限才能儲存；
        // 無權限時保留既有內容，避免切換站點模式造成資料遺失。
        if (current_user_can('unfiltered_html')) {
            $mpu_opt['extend']['js_area'] = isset($extend['js_area']) ? stripslashes_deep($extend['js_area']) : '';
        }
        $text = '<div class="updated"><p><strong>' . __('設定を保存しました', 'mp-ukagaka') . '</strong></p></div>';
    } elseif (isset($_POST['submit5'])) {
        // 處理會話設定
        $mpu_opt['auto_msg'] = isset($_POST['auto_msg']) ? sanitize_textarea_field($_POST['auto_msg']) : '';
        $mpu_opt['common_msg'] = isset($_POST['common_msg']) ? sanitize_textarea_field($_POST['common_msg']) : '';
        $text = '<div class="updated"><p><strong>' . __('設定を保存しました', 'mp-ukagaka') . '</strong></p></div>';
    } elseif (isset($_POST['submit_ai'])) {
        $text = mpu_save_ai_settings($mpu_opt);
    } elseif (isset($_POST['submit_llm'])) {
        $text = mpu_save_llm_settings($mpu_opt);
    } elseif (isset($_POST['submit_diary'])) {
        $text = mpu_save_diary_settings($mpu_opt);
    } elseif (isset($_POST['submit_reset'])) {
        // 處理重置設定
        if (isset($_POST['reset_mpu'])) {
            unset($mpu_opt);
            update_option('mp_ukagaka', []); // 清空選項
            mpu_default_opt(); // 重新設定預設值
            $text = '<div class="updated"><p><strong>' . __('設定をリセットしました', 'mp-ukagaka') . '</strong></p></div>';
        } else {
            $text = '<div class="error"><p><strong>' . __('設定はリセットされませんでした', 'mp-ukagaka') . '</strong></p></div>';
        }
    } elseif (isset($_POST['submit_bot_blocker'])) {
        $text = mpu_save_bot_blocker_settings($mpu_opt);
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
        if ($cur_page < 0 || $cur_page > 9) {
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
            __("MP Ukagaka オプション", "mp-ukagaka"),
            "MP-Ukagaka",
            "manage_options",
            "mp-ukagaka/options.php", // slug
            "mpu_options_page_html"   // 顯示用回調函數
        );
    }
}
add_action("admin_menu", "mpu_options");


/**
 * 回傳不允許透過 ZIP 上傳覆蓋的保留 Personality ID 清單。
 */
function mpu_get_reserved_ghost_ids(): array
{
    return ['Frieren', 'default_1'];
}

/**
 * 從 ZIP 文件中讀取 manifest.json
 *
 * @param string $zip_path ZIP 文件路徑
 * @return array|WP_Error manifest.json 數據或錯誤
 */
function mpu_get_ghost_manifest_from_zip($zip_path)
{
    if (!class_exists('ZipArchive')) {
        return new WP_Error('zip_not_supported', __('PHP ZipArchive クラスが利用できません。サーバー管理者にお問い合わせください。', 'mp-ukagaka'));
    }

    $zip = new ZipArchive();
    $result = $zip->open($zip_path);

    if ($result !== true) {
        return new WP_Error('zip_open_failed', __('ZIP ファイルを開けませんでした。', 'mp-ukagaka'));
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
        return new WP_Error('manifest_not_found', __('ZIP ファイル内に manifest.json が見つかりません。', 'mp-ukagaka'));
    }

    $manifest_data = json_decode($manifest_content, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return new WP_Error('invalid_json', __('manifest.json の形式が無効です：', 'mp-ukagaka') . json_last_error_msg());
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
        return new WP_Error('zip_not_supported', __('PHP ZipArchive クラスが利用できません。サーバー管理者にお問い合わせください。', 'mp-ukagaka'));
    }

    // 讀取 manifest.json
    $manifest_data = mpu_get_ghost_manifest_from_zip($zip_path);
    if (is_wp_error($manifest_data)) {
        return $manifest_data;
    }

    // 驗證 manifest.json 必須包含 id 字段
    if (empty($manifest_data['id'])) {
        return new WP_Error('missing_id', __('manifest.json に必須の id フィールドがありません。', 'mp-ukagaka'));
    }

    $zip = new ZipArchive();
    $result = $zip->open($zip_path);

    if ($result !== true) {
        return new WP_Error('zip_open_failed', __('ZIP ファイルを開けませんでした。', 'mp-ukagaka'));
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
        return new WP_Error('too_many_files', __('ZIP ファイルに含まれるファイル数が多すぎます（1,000 個超）。ZIP の内容を確認してください。', 'mp-ukagaka'));
    }

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $filename = $zip->getNameIndex($i);
        $basename = basename($filename);

        // 1. 檢查路徑遍歷 (Zip Slip) 和絕對路徑
        // 檢查相對路徑遍歷
        if (strpos($filename, '../') !== false || strpos($filename, '..\\') !== false) {
            $zip->close();
            return new WP_Error('invalid_path', __('ZIP ファイルに不正なパス (..) が含まれています。セキュリティ上の理由で拒否されました。', 'mp-ukagaka'));
        }
        // 檢查絕對路徑（Unix 系統以 / 開頭，Windows 系統以驅動器字母開頭如 C:）
        if (substr($filename, 0, 1) === '/' || preg_match('/^[a-zA-Z]:[\\\\\/]/', $filename)) {
            $zip->close();
            return new WP_Error('invalid_path', __('ZIP ファイルに絶対パスが含まれています。セキュリティ上の理由で拒否されました。', 'mp-ukagaka'));
        }
        // 檢查空字節注入（雖然 ZIP 通常不會有，但為安全起見）
        if (strpos($filename, "\0") !== false) {
            $zip->close();
            return new WP_Error('invalid_path', __('ZIP ファイルに不正な文字（ヌルバイト）が含まれています。セキュリティ上の理由で拒否されました。', 'mp-ukagaka'));
        }

        // 跳過目錄項目
        if (substr($filename, -1) === '/') {
            continue;
        }

        // 2. 檢查副檔名白名單
        $extension = strtolower(pathinfo($basename, PATHINFO_EXTENSION));
        if (!in_array($extension, $allowed_extensions)) {
            $zip->close();
            return new WP_Error('invalid_file_type', sprintf(__('ZIP ファイルに許可されていないファイル形式 (.%s) が含まれています。画像・音声・TXT・JSON のみ許可されています。', 'mp-ukagaka'), $extension));
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
        return new WP_Error('shell_not_found', __('ZIP ファイル内に shell/ フォルダが見つかりません。', 'mp-ukagaka'));
    }

    if (empty($shell_files)) {
        return new WP_Error('no_shell_images', __('shell/ フォルダに画像ファイル（.png, .jpg, .jpeg, .gif, .webp）が見つかりません。', 'mp-ukagaka'));
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
        return new WP_Error('zip_not_supported', __('PHP ZipArchive クラスが利用できません。サーバー管理者にお問い合わせください。', 'mp-ukagaka'));
    }

    // 確保目標目錄存在
    if (!file_exists($target_dir)) {
        if (!wp_mkdir_p($target_dir)) {
            return new WP_Error('mkdir_failed', __('対象ディレクトリを作成できませんでした：', 'mp-ukagaka') . $target_dir);
        }
    }

    // 驗證目標目錄路徑安全性（必須在 ghost 目錄下）
    $real_target = realpath($target_dir);
    $ghost_dir = mpu_get_personalities_dir();
    $real_ghost = realpath($ghost_dir);

    if ($real_ghost === false || $real_target === false) {
        return new WP_Error('invalid_path', __('無効なディレクトリパスです。', 'mp-ukagaka'));
    }

    if (strpos($real_target, $real_ghost) !== 0) {
        return new WP_Error('path_not_allowed', __('対象ディレクトリが許可された範囲外です。', 'mp-ukagaka'));
    }

    $zip = new ZipArchive();
    $result = $zip->open($zip_path);

    if ($result !== true) {
        return new WP_Error('zip_open_failed', __('ZIP ファイルを開けませんでした。', 'mp-ukagaka'));
    }

    // 解壓文件（ZipArchive::extractTo 會自動處理路徑規範化，但我們已在驗證階段檢查過路徑安全）
    $extract_result = $zip->extractTo($target_dir);
    $zip->close();

    if (!$extract_result) {
        return new WP_Error('extract_failed', __('ファイルの解凍に失敗しました。', 'mp-ukagaka'));
    }

    // 驗證解壓後的文件結構
    if (!file_exists($target_dir . '/manifest.json')) {
        return new WP_Error('manifest_missing_after_extract', __('解凍後に manifest.json が見つかりません。', 'mp-ukagaka'));
    }

    $shell_path = $target_dir . '/shell';
    if (!file_exists($shell_path) && !is_dir($shell_path)) {
        return new WP_Error('shell_missing_after_extract', __('解凍後に shell/ ディレクトリが見つかりません。', 'mp-ukagaka'));
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
                return new WP_Error('upload_size_exceeded', __('アップロードされたファイルのサイズが大きすぎます。', 'mp-ukagaka'));
            case UPLOAD_ERR_PARTIAL:
                return new WP_Error('upload_partial', __('ファイルのアップロードが不完全です。', 'mp-ukagaka'));
            case UPLOAD_ERR_NO_FILE:
                return new WP_Error('no_file', __('アップロードする ZIP ファイルを選択してください。', 'mp-ukagaka'));
            default:
                return new WP_Error('upload_error', __('ファイルのアップロードに失敗しました。', 'mp-ukagaka'));
        }
    }

    $file = $_FILES['ghost_zip_file'];

    // 驗證文件類型
    $file_type = wp_check_filetype($file['name']);
    if ($file_type['ext'] !== 'zip') {
        return new WP_Error('invalid_file_type', __('ZIP 形式のファイルのみアップロードできます。', 'mp-ukagaka'));
    }

    // 驗證文件大小（50MB 限制）
    $max_size = 50 * 1024 * 1024; // 50MB
    if ($file['size'] > $max_size) {
        return new WP_Error('file_too_large', __('ファイルサイズは 50MB 以下である必要があります。', 'mp-ukagaka'));
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

    // 拒絕覆蓋保留 ID（內建 Personality），比對大小寫不敏感
    if (in_array(strtolower($ghost_id), array_map('strtolower', mpu_get_reserved_ghost_ids()), true)) {
        @unlink($zip_path);
        return new WP_Error('reserved_ghost_id', sprintf(
            __('"%s" は組み込み Personality のため上書きできません。', 'mp-ukagaka'),
            esc_html($ghost_id)
        ));
    }

    // 解壓到暫存目錄（避免失敗時殘留半套目錄）
    $temp_dir = $ghost_dir . '/_tmp_' . sanitize_file_name($ghost_id) . '_' . uniqid('', true);
    $extract_result = mpu_extract_ghost_zip($zip_path, $temp_dir);
    @unlink($zip_path);

    if (is_wp_error($extract_result)) {
        mpu_recursive_rmdir($temp_dir);
        return $extract_result;
    }

    // 解壓後二次路徑掃描（確保所有檔案均在暫存目錄內）
    $real_temp = realpath($temp_dir);
    if ($real_temp) {
        $iter = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($temp_dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($iter as $f) {
            $real_f = realpath($f->getPathname());
            if ($real_f !== false && strpos($real_f, $real_temp . DIRECTORY_SEPARATOR) !== 0) {
                mpu_recursive_rmdir($temp_dir);
                return new WP_Error('extract_path_violation', __('解凍後のファイルが許可された範囲外に存在します。', 'mp-ukagaka'));
            }
        }
    }

    // 既有目錄：儲存暫存路徑，等待管理員二次確認後再覆蓋
    if (file_exists($target_dir) && is_dir($target_dir)) {
        set_transient('mpu_ghost_overwrite_' . get_current_user_id(), [
            'temp_dir' => $temp_dir,
            'ghost_id' => $ghost_id,
            'manifest' => $manifest_data,
        ], 30 * MINUTE_IN_SECONDS);
        return new WP_Error('ghost_exists_pending_confirm', $ghost_id);
    }

    // 原子式搬移：暫存目錄 → 正式目錄
    if (!rename($temp_dir, $target_dir)) {
        mpu_recursive_rmdir($temp_dir);
        return new WP_Error('move_failed', __('Personality ディレクトリの移動に失敗しました。', 'mp-ukagaka'));
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
