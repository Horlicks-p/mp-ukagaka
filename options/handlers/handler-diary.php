<?php
/**
 * Handler: 日記設定
 * 
 * @param array $mpu_opt 當前設定
 * @return string 處理結果訊息
 */
function mpu_handle_diary(&$mpu_opt) {
    $text = '';

    // 處理日記設定 (submit_diary)
    if (isset($_POST['submit_diary'])) {
        // 驗證 Nonce
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'mp_ukagaka_settings')) {
            return '<div class="error"><p>' . __('安全性檢查失敗。', 'mp-ukagaka') . '</p></div>';
        }

        // 處理日記設定
        $mpu_opt = mpu_get_option();

        // 保存基本設定
        $mpu_opt['diary_enabled'] = isset($_POST['diary_enabled']) && $_POST['diary_enabled'] ? true : false;
        $mpu_opt['diary_category'] = isset($_POST['diary_category']) ? intval($_POST['diary_category']) : 0;
        $mpu_opt['diary_author'] = isset($_POST['diary_author']) ? intval($_POST['diary_author']) : get_current_user_id();
        $mpu_opt['diary_trigger_rate'] = isset($_POST['diary_trigger_rate']) ? max(1, min(10, intval($_POST['diary_trigger_rate']))) : 2;
        $mpu_opt['diary_signature'] = isset($_POST['diary_signature']) ? sanitize_text_field($_POST['diary_signature']) : '';

        // 保存 AI 供應商設定
        if (isset($_POST['diary_provider'])) {
            $mpu_opt['diary_provider'] = sanitize_text_field($_POST['diary_provider']);
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
            $mpu_opt['diary_ollama_endpoint'] = sanitize_text_field($_POST['diary_ollama_endpoint']);
        }
        if (isset($_POST['diary_ollama_model'])) {
            $mpu_opt['diary_ollama_model'] = sanitize_text_field($_POST['diary_ollama_model']);
        }

        update_option('mp_ukagaka', $mpu_opt);
        return '<div class="updated"><p><strong>' . __('日記設定已儲存', 'mp-ukagaka') . '</strong></p></div>';
    }

    return $text;
}
