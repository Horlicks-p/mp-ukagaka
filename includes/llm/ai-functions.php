<?php

/**
 * AI 功能：API 調用
 * * @package MP_Ukagaka
 * @subpackage AI
 */

if (!defined('ABSPATH')) {
    exit();
}

/**
 * 調用 AI API（支援多提供商：Gemini, OpenAI, Claude, Ollama）
 * @param {string} $provider - AI 提供商名稱
 * @param {string} $api_key - API 金鑰（Ollama 不需要）
 * @param {string} $system_prompt - 系統提示詞
 * @param {string} $user_prompt - 用戶提示詞
 * @param {string} $language - 語言代碼
 * @param {array|null} $mpu_opt - 選項陣列（包含模型名稱等）
 * @param {int|null} $max_tokens - 最大 token 數（可選，預設使用各 API 的默認值）
 * @return {string|WP_Error} 生成的文本或錯誤
 */
function mpu_call_ai_api($provider, $api_key, $system_prompt, $user_prompt, $language, $mpu_opt = null, $max_tokens = null)
{
    if (function_exists('mpu_ensure_request_state')) {
        mpu_ensure_request_state('llm_single_turn');
    }

    // ===== 統計：記錄開始時間 =====
    $start_time = microtime(true);

    // ===== API 快取檢查 =====
    $cache_key = null;
    if (function_exists('mpu_is_api_cache_enabled') && mpu_is_api_cache_enabled()) {
        $cache_key = mpu_generate_cache_key($provider, $system_prompt, $user_prompt);
        $cached_response = mpu_get_cached_api_response($cache_key);
        if ($cached_response !== false) {
            // ===== 統計：記錄快取命中 =====
            if (function_exists('mpu_record_api_call')) {
                mpu_record_api_call($provider, 'cached', 0);
            }
            return $cached_response;
        }
    }

    // 記錄發送給 AI 的提示詞
    // 檢查 WP_DEBUG 或 WP_DEBUG_LOG，如果都未啟用則強制記錄（用於調試）
    $wp_debug_enabled = defined('WP_DEBUG') && WP_DEBUG;
    $wp_debug_log_enabled = defined('WP_DEBUG_LOG') && WP_DEBUG_LOG;

    // 如果 WP_DEBUG 或 WP_DEBUG_LOG 啟用，則記錄調用資訊
    if ($wp_debug_enabled || $wp_debug_log_enabled) {
        mpu_debug_log('=== MP Ukagaka - AI API 調用 ===');
        mpu_debug_log('提供商: ' . $provider);
        mpu_debug_log('語言: ' . $language);
        mpu_debug_log('--- System Prompt ---');
        mpu_debug_log($system_prompt);
        mpu_debug_log('--- User Prompt ---');
        mpu_debug_log($user_prompt);
        mpu_debug_log('=== End AI API 調用 ===');
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
        'user_prompt'   => $user_prompt,
        'language'      => $language,
        'max_tokens'    => $max_tokens,
    ];

    // 提供商特定參數提取
    switch ($provider_slug) {
        case "gemini":
            $args['model'] = $mpu_opt["llm_gemini_model"] ?? $mpu_opt["gemini_model"] ?? "gemini-2.5-flash";
            break;
        case "openai":
            $args['model'] = $mpu_opt["llm_openai_model"] ?? $mpu_opt["openai_model"] ?? "gpt-4.1-mini-2025-04-14";
            break;
        case "claude":
            $args['model'] = $mpu_opt["llm_claude_model"] ?? $mpu_opt["claude_model"] ?? "claude-sonnet-4-6";
            break;
        case "ollama":
            $args['endpoint'] = $mpu_opt["ollama_endpoint"] ?? "http://localhost:11434";
            $args['model'] = $mpu_opt["ollama_model"] ?? "qwen3:8b";
            break;
    }

    $result = $ai_provider->generate_text($args);

    // ===== 統計：記錄 API 調用結果 =====
    if (function_exists('mpu_record_api_call')) {
        $elapsed_time = (microtime(true) - $start_time) * 1000; // 毫秒
        $status = is_wp_error($result) ? 'error' : 'success';
        mpu_record_api_call($provider, $status, $elapsed_time);
    }

    // ===== 快取成功的 API 回應 =====
    if ($cache_key !== null && !is_wp_error($result) && !empty($result)) {
        mpu_set_cached_api_response($cache_key, $result);
    }

    return $result;
}

/**
 * 過濾 AI 回應中的思考內容標籤
 * 支援多種推理模型的標籤變體：
 * - <think>, <thinking>: DeepSeek-R1, Qwen3
 * - <reflection>: 某些模型的反思過程
 * - <chain_of_thought>: 思維鏈標籤
 * - <reasoning>: 推理過程標籤
 * - <inner_monologue>: 內心獨白標籤
 * - <redacted_reasoning>: DeepSeek API 的已編輯推理
 * * @param string $response AI 原始回應
 * @return string 過濾後的回應
 */
function mpu_filter_thinking_content($response)
{
    if (empty($response) || !is_string($response)) {
        return $response;
    }

    $original_response = $response;

    // 完整標籤過濾（有開始和結束標籤）
    $complete_tags = [
        'think',
        'thinking',
        'reflection',
        'chain_of_thought',
        'reasoning',
        'inner_monologue',
        'redacted_reasoning',
    ];
    
    foreach ($complete_tags as $tag) {
        $response = preg_replace('/<' . $tag . '>.*?<\/' . $tag . '>/is', '', $response);
    }

    // 不完整標籤過濾（只有開始標籤，內容延伸到字串結尾）
    // 這處理模型輸出被截斷的情況
    $incomplete_tags = [
        'think',
        'thinking',
        'reflection',
        'chain_of_thought',
        'reasoning',
        'inner_monologue',
    ];
    
    foreach ($incomplete_tags as $tag) {
        $response = preg_replace('/<' . $tag . '>.*$/is', '', $response);
    }

    // 過濾 LLM 特殊 token（常見於 Mistral、Llama、ChatML 格式模型）
    // 使用正則表達式匹配 <|xxx|> 格式的 token
    $response = preg_replace('/\<\|[a-z_]+\|\>/i', '', $response);

    // 清理多餘空白
    $response = preg_replace('/\n\s*\n\s*\n/s', "\n\n", $response);
    $response = trim($response);

    // 調試記錄
    if (defined('WP_DEBUG') && WP_DEBUG && $response !== $original_response) {
            mpu_debug_log('mpu_filter_thinking_content: filtered thinking tags');
    }

    return $response;
}

/**
 * 獲取語言指令（共用函數）
 * @param {string} $language - 語言代碼（zh-TW, ja, en）
 * @return {string} 語言指令字串
 */
function mpu_get_language_instruction($language)
{
    switch ($language) {
        case "zh-TW":
            return "請用繁體中文回應。";
        case "ja":
            return "日本語で応答してください。";
        case "en":
            return "Please respond in English.";
        default:
            return "請用繁體中文回應。";
    }
}

// 注意：mpu_generate_llm_dialogue() 已移至 llm-functions.php
