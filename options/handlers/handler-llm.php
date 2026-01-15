<?php
/**
 * Handler: LLM 設定 + AI 頁面感知設定
 * 
 * @param array $mpu_opt 當前設定
 * @return string 處理結果訊息
 */
function mpu_handle_llm(&$mpu_opt) {
    $text = '';

    // 處理 LLM 設定 (submit_llm)
    if (isset($_POST['submit_llm'])) {
        // 驗證 Nonce
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'mp_ukagaka_settings')) {
            return '<div class="error"><p>' . __('安全性檢查失敗。', 'mp-ukagaka') . '</p></div>';
        }

        // 處理 LLM 設定
        $mpu_opt = mpu_get_option();

        // 保存頁面感知開關
        $mpu_opt['ai_enabled'] = isset($_POST['ai_enabled']) && $_POST['ai_enabled'] ? true : false;

        // 保存提供商選擇（統一使用 llm_provider，同時保持 ai_provider 向後兼容）
        if (isset($_POST['llm_provider'])) {
            $provider = sanitize_text_field($_POST['llm_provider']);
            $mpu_opt['llm_provider'] = $provider;
            $mpu_opt['ai_provider'] = $provider; // 向後兼容
        }

        // 處理各提供商的 API Key（加密存儲）
        $gemini_key = isset($_POST['llm_gemini_api_key']) ? sanitize_text_field($_POST['llm_gemini_api_key']) : '';
        $openai_key = isset($_POST['llm_openai_api_key']) ? sanitize_text_field($_POST['llm_openai_api_key']) : '';
        $claude_key = isset($_POST['llm_claude_api_key']) ? sanitize_text_field($_POST['llm_claude_api_key']) : '';

        if (!empty($gemini_key) && !mpu_is_api_key_encrypted($gemini_key)) {
            $mpu_opt['llm_gemini_api_key'] = mpu_encrypt_api_key($gemini_key);
            // 向後兼容：同時保存到舊的設定鍵
            $mpu_opt['ai_api_key'] = $mpu_opt['llm_gemini_api_key'];
        }

        if (!empty($openai_key) && !mpu_is_api_key_encrypted($openai_key)) {
            $mpu_opt['llm_openai_api_key'] = mpu_encrypt_api_key($openai_key);
            // 向後兼容
            $mpu_opt['openai_api_key'] = $mpu_opt['llm_openai_api_key'];
        }

        if (!empty($claude_key) && !mpu_is_api_key_encrypted($claude_key)) {
            $mpu_opt['llm_claude_api_key'] = mpu_encrypt_api_key($claude_key);
            // 向後兼容
            $mpu_opt['claude_api_key'] = $mpu_opt['llm_claude_api_key'];
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
            $mpu_opt['ollama_endpoint'] = sanitize_text_field($_POST['ollama_endpoint']);
        }
        if (isset($_POST['ollama_model'])) {
            $mpu_opt['ollama_model'] = sanitize_text_field($_POST['ollama_model']);
        }
        // 保存「關閉思考模式」設定（checkbox 未勾選時不會發送，所以要明確設為 false）
        $mpu_opt['ollama_disable_thinking'] = isset($_POST['ollama_disable_thinking']) && $_POST['ollama_disable_thinking'] ? true : false;

        // 保存「使用 LLM 取代內建對話」設定（支援所有提供商）
        $mpu_opt['llm_replace_dialogue'] = isset($_POST['llm_replace_dialogue']) && $_POST['llm_replace_dialogue'] ? true : false;
        // 向後兼容：如果使用 Ollama 且啟用了取代對話，也設置 ollama_replace_dialogue
        if ($mpu_opt['llm_replace_dialogue'] && isset($mpu_opt['llm_provider']) && $mpu_opt['llm_provider'] === 'ollama') {
            $mpu_opt['ollama_replace_dialogue'] = true;
        }

        // 保存「啟用互動對話功能」設定
        $mpu_opt['enable_chat_mode'] = isset($_POST['enable_chat_mode']) && $_POST['enable_chat_mode'] ? true : false;

        // 保存天氣設定
        $mpu_opt['weather_enabled'] = isset($_POST['weather_enabled']) && $_POST['weather_enabled'] ? true : false;
        $mpu_opt['weather_latitude'] = isset($_POST['weather_latitude']) ? floatval($_POST['weather_latitude']) : 25.0330;
        $mpu_opt['weather_longitude'] = isset($_POST['weather_longitude']) ? floatval($_POST['weather_longitude']) : 121.5654;

        // 保存 API 快取設定
        $mpu_opt['api_cache_enabled'] = isset($_POST['api_cache_enabled']) && $_POST['api_cache_enabled'] ? true : false;
        $mpu_opt['api_cache_ttl'] = isset($_POST['api_cache_ttl']) ? intval($_POST['api_cache_ttl']) : 3600;

        update_option('mp_ukagaka', $mpu_opt);
        return '<div class="updated"><p><strong>' . __('LLM 設定已儲存', 'mp-ukagaka') . '</strong></p></div>';
    }

    // 處理 AI 設定 (submit_ai)
    if (isset($_POST['submit_ai'])) {
        // 驗證 Nonce
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'mp_ukagaka_settings')) {
            return '<div class="error"><p><strong>' . __('安全性檢查失敗。', 'mp-ukagaka') . '</strong></p></div>';
        }

        // 只處理 AI 設定（頁面感知相關的設定），保留其他所有設定
        $mpu_opt = mpu_get_option(); // 獲取現有設定

        // 只更新頁面感知相關的設定（不處理提供商、API Key、模型選擇）
        $mpu_opt['ai_language'] = isset($_POST['ai_language']) ? sanitize_text_field($_POST['ai_language']) : 'zh-TW';
        $mpu_opt['ai_system_prompt'] = isset($_POST['ai_system_prompt']) ? sanitize_textarea_field($_POST['ai_system_prompt']) : 'あなたは「{{ukagaka_display_name}}」というキャラクターです。必ずこのキャラクターとして振る舞い、一人称は「私」を使用してください。回答は日本語で、50文字以内の短い一言で返してください。自分が AI や Qwen だと言わないでください。';
        $mpu_opt['ai_probability'] = isset($_POST['ai_probability']) ? max(1, min(100, intval($_POST['ai_probability']))) : 10;
        $mpu_opt['ai_trigger_pages'] = isset($_POST['ai_trigger_pages']) ? sanitize_text_field($_POST['ai_trigger_pages']) : 'is_single';
        $mpu_opt['ai_text_color'] = isset($_POST['ai_text_color']) ? sanitize_hex_color($_POST['ai_text_color']) : '#000000';
        $mpu_opt['ai_display_duration'] = isset($_POST['ai_display_duration']) ? max(1, min(60, intval($_POST['ai_display_duration']))) : 8;
        $mpu_opt['ai_greet_first_visit'] = isset($_POST['ai_greet_first_visit']) && $_POST['ai_greet_first_visit'] ? true : false;
        $mpu_opt['ai_greet_prompt'] = isset($_POST['ai_greet_prompt']) ? sanitize_textarea_field($_POST['ai_greet_prompt']) : 'あなたは「{{ukagaka_display_name}}」というキャラクターです。訪問者が初めてサイトに来た時、キャラクターらしく簡単に挨拶してください。50文字以内で返してください。';

        update_option('mp_ukagaka', $mpu_opt);
        return '<div class="updated"><p><strong>' . __('AI 設定已儲存', 'mp-ukagaka') . '</strong></p></div>';
    }

    return $text;
}
