<?php

/**
 * 對話模式 API 處理器
 * 處理多輪對話的 LLM API 調用
 * 
 * @package MP_Ukagaka
 * @subpackage Chat API
 */

if (!defined('ABSPATH')) {
    exit();
}

/**
 * AI API 呼叫（支援多輪對話）
 * 
 * @param string $provider AI 提供商
 * @param string $api_key API Key
 * @param string $system_prompt 系統提示
 * @param array $messages 對話訊息陣列
 * @param string $language 語言
 * @param array $options 選項
 * @return string|WP_Error AI 回應或錯誤
 */
function mpu_call_ai_api_with_messages($provider, $api_key, $system_prompt, $messages, $language, $options = [])
{
    if (function_exists('mpu_resolve_language_code')) {
        $personality_id = function_exists('mpu_get_current_personality_id') ? mpu_get_current_personality_id() : null;
        $language = mpu_resolve_language_code($personality_id, $language);
    } elseif (empty($language)) {
        $language = 'zh-TW';
    }

    // Full prompt dumps require explicit opt-in because they may contain PII.
    if (function_exists('mpu_is_llm_prompt_debug_enabled') && mpu_is_llm_prompt_debug_enabled() && function_exists('mpu_debug_log')) {
        $message_log_limit = (int) apply_filters('mpu_debug_llm_prompt_message_limit', 12);
        if ($message_log_limit < 1) {
            $message_log_limit = 12;
        }
        $logged_messages = array_slice($messages, -$message_log_limit, null, true);

        mpu_debug_log('=== MP Ukagaka - AI API 調用（多輪對話） ===');
        mpu_debug_log('提供商: ' . $provider);
        mpu_debug_log('語言: ' . $language);
        mpu_debug_log('--- System Prompt ---');
        mpu_debug_log($system_prompt);
        mpu_debug_log('--- Messages (' . count($messages) . ' 條、latest ' . count($logged_messages) . ' logged) ---');
        foreach ($logged_messages as $index => $msg) {
            $role = isset($msg['role']) ? $msg['role'] : 'unknown';
            $content = isset($msg['content']) ? $msg['content'] : '';
            mpu_debug_log("  [{$index}] {$role}: " . mb_substr($content, 0, 200, 'UTF-8') . (mb_strlen($content, 'UTF-8') > 200 ? '...' : ''));
        }
        mpu_debug_log('=== End AI API 調用（多輪對話） ===');
    }

    // 初始化 MCP 工具執行標記
    if (function_exists('mpu_ensure_request_state')) {
        mpu_ensure_request_state('legacy_chat_api_wrapper');
    }

    $provider_slug = strtolower(trim((string) $provider));

    $factory_result = MPU_AI_Provider_Factory::get_provider($provider_slug);
    if (is_wp_error($factory_result)) {
        return $factory_result;
    }

    /** @var MPU_AI_Provider_Interface $ai_provider */
    $ai_provider = $factory_result;

    // 準備參數
    $args = [
        'api_key'       => $api_key,
        'system_prompt' => $system_prompt,
        'messages'      => $messages,
        'language'      => $language,
        'max_tokens'    => $options['max_tokens'] ?? 1000,
        'temperature'   => $options['temperature'] ?? 0.8,
    ];

    // 提供商特定參數提取
    switch ($provider_slug) {
        case 'ollama':
            $args['endpoint'] = $options['llm_ollama_endpoint'] ?? $options['ollama_endpoint'] ?? 'http://localhost:11434';
            $args['model'] = $options['llm_ollama_model'] ?? $options['ollama_model'] ?? 'qwen3:8b';
            break;
        case 'openai':
            $args['model'] = $options['llm_openai_model'] ?? 'gpt-4o-mini';
            break;
        case 'claude':
            $args['model'] = $options['llm_claude_model'] ?? 'claude-sonnet-4-6';
            break;
        case 'gemini':
            $args['model'] = $options['llm_gemini_model'] ?? 'gemini-2.5-flash';
            break;
    }

    return $ai_provider->generate_chat($args);
}
