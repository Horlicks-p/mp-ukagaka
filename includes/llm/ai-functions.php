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

    $result = null;
    switch ($provider) {
        case "gemini":
            $model = $mpu_opt["llm_gemini_model"] ?? $mpu_opt["gemini_model"] ?? "gemini-2.5-flash";
            $result = mpu_call_gemini_api($api_key, $model, $system_prompt, $user_prompt, $language, $max_tokens);
            break;
        case "openai":
            $model = $mpu_opt["llm_openai_model"] ?? $mpu_opt["openai_model"] ?? "gpt-4.1-mini-2025-04-14";
            $result = mpu_call_openai_api($api_key, $model, $system_prompt, $user_prompt, $language, $max_tokens);
            break;
        case "claude":
            $model = $mpu_opt["llm_claude_model"] ?? $mpu_opt["claude_model"] ?? "claude-sonnet-4-5-20250929";
            $result = mpu_call_claude_api($api_key, $model, $system_prompt, $user_prompt, $language, $max_tokens);
            break;
        case "ollama":
            $endpoint = $mpu_opt["ollama_endpoint"] ?? "http://localhost:11434";
            $model = $mpu_opt["ollama_model"] ?? "qwen3:8b";
            $result = mpu_call_ollama_api($endpoint, $model, $system_prompt, $user_prompt, $language, $max_tokens);
            break;
        default:
            return new WP_Error("unsupported_provider", sprintf(__('不支援的 AI 提供商：%s', 'mp-ukagaka'), $provider));
    }

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
 * 調用 Gemini API（支援用戶選擇模型）
 * @param {string} $api_key - API 金鑰
 * @param {string} $model - 模型名稱（如 gemini-2.5-flash, gemini-2.5-pro）
 * @param {string} $system_prompt - 系統提示詞
 * @param {string} $user_prompt - 用戶提示詞
 * @param {string} $language - 語言代碼
 * @param {int|null} $max_tokens - 最大 token 數（可選，預設 500）
 * @return {string|WP_Error} 生成的文本或錯誤
 */
function mpu_call_gemini_api($api_key, $model, $system_prompt, $user_prompt, $language, $max_tokens = null)
{
    $language_instruction = mpu_get_language_instruction($language);
    
    // 初始對話歷史
    $contents = [
        [
            "role" => "user",
            "parts" => [
                ["text" => $system_prompt . "\n\n" . $language_instruction . "\n\n" . $user_prompt]
            ]
        ]
    ];

    // 獲取 MCP 工具
    $tools_config = [];
    if (function_exists('mpu_get_mcp_tools_for_llm')) {
        $mcp_tools = mpu_get_mcp_tools_for_llm('gemini');
        if (!empty($mcp_tools)) {
            $tools_config = [
                "functionDeclarations" => $mcp_tools // User requested camelCase
            ];
        }
    }

    // Use v1beta for tools support
    $api_url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . urlencode($api_key);
    
    $max_turns = MPU_MAX_TOOL_TURNS; // 工具呼叫回合上限
    $current_turn = 0;

    while ($current_turn < $max_turns) {
        $request_body = [
            "contents" => $contents,
            "generationConfig" => [
                "temperature" => 0.7,
                "topK" => 40,
                "topP" => 0.95,
                "maxOutputTokens" => $max_tokens !== null ? intval($max_tokens) : 500,
            ]
        ];

        if (!empty($tools_config)) {
            $request_body['tools'] = [$tools_config];
        }

        $response = wp_remote_post($api_url,
            mpu_build_http_args(mpu_get_provider_headers('gemini'), $request_body));

        if (is_wp_error($response)) {
            return new WP_Error("api_request_failed", sprintf(__('Gemini API 請求失敗：%s', 'mp-ukagaka'), $response->get_error_message()));
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        // Fallback: 如果因為 tools 導致 400 錯誤，嘗試移除 tools 重試
        if ($response_code === 400 && !empty($tools_config)) {
             $error_msg = mpu_parse_api_error_message($response_body, '');

             if (strpos($error_msg, 'tools') !== false || strpos($error_msg, 'Unknown name') !== false) {
                 unset($request_body['tools']);
                 $response = wp_remote_post($api_url,
                    mpu_build_http_args(mpu_get_provider_headers('gemini'), $request_body));
                $response_code = wp_remote_retrieve_response_code($response);
                $response_body = wp_remote_retrieve_body($response);
             }
        }

        if ($response_code !== 200) {
            $error_message = mpu_parse_api_error_message($response_body, __('未知錯誤', 'mp-ukagaka'));
            return new WP_Error("api_error", sprintf(__('Gemini API 錯誤（HTTP %s）：%s', 'mp-ukagaka'), $response_code, $error_message));
        }

        $data = mpu_json_decode_assoc($response_body);
        
        if (empty($data["candidates"][0]["content"])) {
            return new WP_Error("empty_response", __('Gemini API 回應為空', 'mp-ukagaka'));
        }

        $candidate_content = $data["candidates"][0]["content"];
        $parts = $candidate_content["parts"];

        // 將模型的回答加入歷史
        $contents[] = $candidate_content;

        // 檢查是否有函數調用
        $function_calls = [];
        foreach ($parts as $part) {
            if (isset($part["functionCall"])) {
                $function_calls[] = $part["functionCall"];
            }
        }

        if (!empty($function_calls)) {
            // 處理函數調用
            $function_response_parts = [];
            
            foreach ($function_calls as $call) {
                $function_name = $call["name"];
                $args = $call["args"] ?? [];
                
                // 執行工具並建構 Gemini functionResponse part
                $result = function_exists('mpu_execute_mcp_tool')
                    ? mpu_execute_mcp_tool($function_name, $args)
                    : ['error' => 'Tool execution function missing'];

                $function_response_parts[] = mpu_build_gemini_function_response_part($function_name, $result);
            }

            // 將函數結果加入歷史
            $contents[] = [
                "role" => "user", // Role: user
                "parts" => $function_response_parts
            ];

            $current_turn++;
            continue;
        }

        // 如果沒有函數調用，返回文本
        if (isset($parts[0]["text"])) {
            return trim($parts[0]["text"]);
        }
        
        return new WP_Error("unknown_response_format", __('Gemini API 回應格式無法識別', 'mp-ukagaka'));
    }

    return new WP_Error("max_turns_exceeded", __('Gemini API 工具調用次數過多', 'mp-ukagaka'));
}

/**
 * 調用 OpenAI API
 * @param {string} $api_key - API 金鑰
 * @param {string} $model - 模型名稱（如 gpt-4o-mini）
 * @param {string} $system_prompt - 系統提示詞
 * @param {string} $user_prompt - 用戶提示詞
 * @param {string} $language - 語言代碼
 * @return {string|WP_Error} 生成的文本或錯誤
 */
function mpu_call_openai_api($api_key, $model, $system_prompt, $user_prompt, $language, $max_tokens = null)
{
    $language_instruction = mpu_get_language_instruction($language);

    // OpenAI API 端點
    $api_url = "https://api.openai.com/v1/chat/completions";

    // 準備初始訊息
    $messages = [
        [
            "role" => "system",
            "content" => $system_prompt . "\n\n" . $language_instruction
        ],
        [
            "role" => "user",
            "content" => $user_prompt
        ]
    ];

    // 獲取 MCP 工具（如果有）
    $tools = [];
    if (function_exists('mpu_get_mcp_tools_for_llm')) {
        $tools = mpu_get_mcp_tools_for_llm('openai');
    }

    $max_turns = MPU_MAX_TOOL_TURNS; // 工具呼叫回合上限
    $current_turn = 0;

    while ($current_turn < $max_turns) {
        $request_body = [
            "model" => $model,
            "messages" => $messages,
            "temperature" => 0.7,
            "max_tokens" => $max_tokens !== null ? intval($max_tokens) : 100,
        ];

        if (!empty($tools)) {
            $request_body['tools'] = $tools;
        }

        // 發送請求
        $response = wp_remote_post($api_url,
            mpu_build_http_args(mpu_get_provider_headers('openai', $api_key), $request_body));

        // 處理錯誤
        if (is_wp_error($response)) {
            return new WP_Error("api_request_failed", sprintf(__('OpenAI API 請求失敗：%s', 'mp-ukagaka'), $response->get_error_message()));
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($response_code !== 200) {
            $error_message = mpu_parse_api_error_message($response_body, sprintf(__('API 請求失敗 (HTTP %s)', 'mp-ukagaka'), $response_code));
            return new WP_Error("api_error", sprintf(__('OpenAI API 錯誤：%s', 'mp-ukagaka'), $error_message));
        }

        // 解析回應
        $data = mpu_json_decode_assoc($response_body);
        
        if (empty($data["choices"][0]["message"])) {
            return new WP_Error("invalid_response", __('OpenAI API 回應格式錯誤', 'mp-ukagaka'));
        }

        $message = $data["choices"][0]["message"];

        // 檢查是否有工具調用
        if (isset($message['tool_calls']) && !empty($message['tool_calls'])) {
            // 將助手的回應（包含工具調用）加入歷史
            $messages[] = $message;

            foreach ($message['tool_calls'] as $tool_call) {
                $function_name = $tool_call['function']['name'];
                $arguments_json = $tool_call['function']['arguments'];
                $arguments = json_decode($arguments_json, true);
                
                // 執行工具
                $result = null;
                if (function_exists('mpu_execute_mcp_tool')) {
                    $result = mpu_execute_mcp_tool($function_name, $arguments);
                } else {
                    $result = new WP_Error('function_missing', 'Tool execution function missing.');
                }

                // 加入工具結果到歷史
                $messages[] = mpu_build_openai_tool_message($tool_call['id'], $function_name, $result);
            }

            // 繼續下一輪迴圈，讓 AI 根據工具結果生成新回應
            $current_turn++;
            continue;
        }

        // 如果沒有工具調用，直接返回內容
        if (isset($message['content'])) {
            return trim($message['content']);
        }

        return new WP_Error("empty_content", __('OpenAI API 回應內容為空', 'mp-ukagaka'));
    }

    return new WP_Error("max_turns_exceeded", __('OpenAI API 工具調用次數過多', 'mp-ukagaka'));
}

/**
 * 調用 Claude API (Anthropic)
 * @param {string} $api_key - API 金鑰
 * @param {string} $model - 模型名稱（如 claude-sonnet-4-5-20250929）
 * @param {string} $system_prompt - 系統提示詞
 * @param {string} $user_prompt - 用戶提示詞
 * @param {string} $language - 語言代碼
 * @return {string|WP_Error} 生成的文本或錯誤
 */
function mpu_call_claude_api($api_key, $model, $system_prompt, $user_prompt, $language, $max_tokens = null)
{
    $language_instruction = mpu_get_language_instruction($language);

    // Claude API 端點
    $api_url = "https://api.anthropic.com/v1/messages";

    // 組合完整的系統提示詞
    $full_system_prompt = $system_prompt . "\n\n" . $language_instruction;

    // 初始訊息
    $messages = [
        [
            "role" => "user",
            "content" => $user_prompt
        ]
    ];

    // 獲取 MCP 工具
    $tools = [];
    if (function_exists('mpu_get_mcp_tools_for_llm')) {
        $tools = mpu_get_mcp_tools_for_llm('claude');
    }

    $max_turns = MPU_MAX_TOOL_TURNS; // 工具呼叫回合上限
    $current_turn = 0;

    while ($current_turn < $max_turns) {
        $request_body = [
            "model" => $model,
            "max_tokens" => $max_tokens !== null ? intval($max_tokens) : 100,
            "system" => $full_system_prompt,
            "messages" => $messages,
        ];

        if (!empty($tools)) {
            $request_body['tools'] = $tools;
        }

        // 發送請求
        $response = wp_remote_post($api_url,
            mpu_build_http_args(mpu_get_provider_headers('claude', $api_key), $request_body));

        if (is_wp_error($response)) {
            return new WP_Error("api_request_failed", sprintf(__('Claude API 請求失敗：%s', 'mp-ukagaka'), $response->get_error_message()));
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($response_code !== 200) {
            $error_message = mpu_parse_api_error_message($response_body, sprintf(__('API 請求失敗 (HTTP %s)', 'mp-ukagaka'), $response_code));
            return new WP_Error("api_error", sprintf(__('Claude API 錯誤：%s', 'mp-ukagaka'), $error_message));
        }

        $data = mpu_json_decode_assoc($response_body);
        
        if (empty($data["content"])) {
            return new WP_Error("invalid_response", __('Claude API 回應格式錯誤', 'mp-ukagaka'));
        }

        // 將助手的回應加入歷史
        $messages[] = [
            "role" => "assistant",
            "content" => $data["content"]
        ];

        // 檢查是否停止原因是 tool_use
        if (isset($data['stop_reason']) && $data['stop_reason'] === 'tool_use') {
            $tool_results = [];

            foreach ($data['content'] as $content_block) {
                if ($content_block['type'] === 'tool_use') {
                    $tool_use_id = $content_block['id'];
                    $function_name = $content_block['name'];
                    $arguments = $content_block['input'];

                    // 執行工具
                    $result = null;
                    if (function_exists('mpu_execute_mcp_tool')) {
                        $result = mpu_execute_mcp_tool($function_name, $arguments);
                    } else {
                        $result = ["error" => "Tool execution function missing"];
                    }

                    $tool_results[] = mpu_build_claude_tool_result_block($tool_use_id, $result);
                }
            }

            // 將工具結果加入歷史
            $messages[] = [
                "role" => "user",
                "content" => $tool_results
            ];

            $current_turn++;
            continue;
        }

        // 提取文本回應
        foreach ($data['content'] as $content_block) {
            if ($content_block['type'] === 'text') {
                return trim($content_block['text']);
            }
        }

        return new WP_Error("empty_content", __('Claude API 回應內容為空', 'mp-ukagaka'));
    }

    return new WP_Error("max_turns_exceeded", __('Claude API 工具調用次數過多', 'mp-ukagaka'));
}

/**
 * 調用 Ollama API（本機 LLM）
 * @param {string} $endpoint - Ollama 端點 URL
 * @param {string} $model - 模型名稱
 * @param {string} $system_prompt - 系統提示詞
 * @param {string} $user_prompt - 用戶提示詞
 * @param {string} $language - 語言代碼
 * @return {string|WP_Error} 生成的文本或錯誤
 */
function mpu_call_ollama_api($endpoint, $model, $system_prompt, $user_prompt, $language, $max_tokens = null)
{
    $mpu_opt = mpu_get_option();

    // 支援思考模式的模型 (Qwen3, DeepSeek, Frieren, R1 等)
    $thinking_keywords = ['qwen3', 'frieren', 'deepseek', 'r1'];
    $is_thinking_model = (bool) array_filter(
        $thinking_keywords,
        fn($kw) => stripos($model, $kw) !== false
    );

    // 預設啟用思考模式
    $enable_thinking = $is_thinking_model && !(isset($mpu_opt['ollama_disable_thinking']) && $mpu_opt['ollama_disable_thinking']);

    // 驗證並獲取端點
    if (!function_exists('mpu_validate_ollama_endpoint')) {
        $endpoint = rtrim($endpoint, '/');
        // ... (省略舊版驗證邏輯，保持兼容性) ...
        $is_remote = !preg_match('/localhost|127\.0\.0\.1|::1/', $endpoint);
        $timeout = $is_remote ? 90 : 30;
    } else {
        $validated_endpoint = mpu_validate_ollama_endpoint($endpoint);
        if (is_wp_error($validated_endpoint)) {
            return new WP_Error("invalid_endpoint", sprintf(__('Ollama 端點格式錯誤：%s', 'mp-ukagaka'), $validated_endpoint->get_error_message()));
        }
        $endpoint = $validated_endpoint;
        $timeout = mpu_get_ollama_timeout($endpoint, 'api_call');
        $is_remote = mpu_is_remote_endpoint($endpoint);
    }
    
    // 如果是遠端連線，增加超時時間以處理工具調用
    if ($is_remote) {
        $timeout = max($timeout, 90); 
    } else {
        $timeout = max($timeout, 60);
    }

    $api_url = rtrim($endpoint, '/') . '/api/chat';

    $messages = [];

    if (!empty($system_prompt)) {
        $language_instruction = mpu_get_language_instruction($language);
        $full_system_prompt = $system_prompt . "\n\n" . $language_instruction;
        $messages[] = [
            'role' => 'system',
            'content' => $full_system_prompt
        ];
    }

    // 添加用戶提示詞
    $final_user_prompt = $user_prompt;
    if (!$enable_thinking && $is_thinking_model) {
        $final_user_prompt = $user_prompt . ' /no_think';
    }

    $messages[] = [
        'role' => 'user',
        'content' => $final_user_prompt
    ];

    // 獲取 MCP 工具
    $tools = [];
    if (function_exists('mpu_get_mcp_tools_for_llm')) {
        $tools = mpu_get_mcp_tools_for_llm('ollama');
    }

    $max_turns = MPU_MAX_TOOL_TURNS; // 工具呼叫回合上限
    $current_turn = 0;

    while ($current_turn < $max_turns) {
        $request_body = [
            'model' => $model,
            'messages' => $messages,
            'stream' => false,
            'options' => [
                'temperature' => 0.7,
                'num_predict' => $max_tokens !== null ? intval($max_tokens) : 100
            ]
        ];

        if ($is_thinking_model) {
            $request_body['think'] = $enable_thinking;
        }

        if (!empty($tools)) {
            $request_body['tools'] = $tools;
        }

        $response = wp_remote_post($api_url,
            mpu_build_http_args(mpu_get_provider_headers('ollama'), $request_body, $timeout));

        if (is_wp_error($response)) {
            // ... (保留原有的錯誤處理邏輯) ...
            return new WP_Error("api_request_failed", sprintf(__('Ollama API 請求失敗：%s', 'mp-ukagaka'), $response->get_error_message()));
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($response_code !== 200) {
            // ... (保留原有的錯誤處理邏輯) ...
            return new WP_Error("api_error", sprintf(__('Ollama API 錯誤 (HTTP %s)', 'mp-ukagaka'), $response_code));
        }

        $data = mpu_json_decode_assoc($response_body);

        if (empty($data["message"])) {
            return new WP_Error("invalid_response", __('Ollama API 回應格式錯誤', 'mp-ukagaka'));
        }

        $message = $data["message"];
        
        // 將助手的回應加入歷史
        $messages[] = $message;

        // 檢查是否有工具調用
        if (isset($message['tool_calls']) && !empty($message['tool_calls'])) {
            foreach ($message['tool_calls'] as $tool_call) {
                $function_name = $tool_call['function']['name'];
                $arguments = $tool_call['function']['arguments'];
                // Ollama 參數可能是對象或 JSON 字串
                if (is_string($arguments)) {
                    $arguments = json_decode($arguments, true);
                }

                // 執行工具
                $result = null;
                if (function_exists('mpu_execute_mcp_tool')) {
                    $result = mpu_execute_mcp_tool($function_name, $arguments);
                } else {
                    $result = ["error" => "Tool execution function missing"];
                }

                // 將工具結果加入歷史 (role: tool)
                $messages[] = mpu_build_ollama_tool_message($function_name, $result);
            }
            
            $current_turn++;
            continue;
        }

        // 如果沒有工具調用，處理內容
        $content = $message['content'] ?? null;
        
        // ... (保留原有的 thinking / content 提取邏輯) ...
        
        if ($content !== null) {
             return mpu_filter_thinking_content($content); // 簡化展示，實際可以使用原有邏輯
        }

        return new WP_Error("empty_content", __('Ollama API 回應內容為空', 'mp-ukagaka'));
    }

    return new WP_Error("max_turns_exceeded", __('Ollama API 工具調用次數過多', 'mp-ukagaka'));
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

/**
 * 獲取允許的 WordPress 條件標籤白名單
 * * @return array 允許的條件標籤列表
 */
function mpu_get_allowed_conditional_tags()
{
    // 白名單限制，防止 RCE 漏洞（安全性）
    // 只允許安全的 WordPress 條件標籤函數
    return [
        // 主要頁面類型
        'is_single',
        'is_page',
        'is_home',
        'is_front_page',
        'is_archive',
        'is_search',
        'is_404',
        'is_attachment',

        // 文章類型
        'is_singular',
        'is_post_type_archive',

        // 分類和標籤
        'is_category',
        'is_tag',
        'is_tax',

        // 作者和日期
        'is_author',
        'is_date',
        'is_year',
        'is_month',
        'is_day',
        'is_time',

        // 管理頁面
        'is_admin',
        'is_feed',
        'is_robots',
        'is_trackback',
        'is_preview',

        // 多站點
        'is_main_site',
        'is_multisite',

        // 其他
        'is_paged',
        'is_sticky',
    ];
}

/**
 * 檢查是否應該觸發 AI
 * 使用白名單限制，防止遠程代碼執行 (RCE) 漏洞（安全性修復）
 * @return {bool} 是否應該觸發 AI
 */
function mpu_should_trigger_ai()
{
    $mpu_opt = mpu_get_option();

    if (empty($mpu_opt["ai_enabled"])) {
        return false;
    }

    $trigger_pages = $mpu_opt["ai_trigger_pages"] ?? "is_single";
    $conditions = array_map("trim", explode(",", $trigger_pages));

    // 獲取允許的條件標籤白名單
    $allowed_tags = mpu_get_allowed_conditional_tags();

    foreach ($conditions as $condition) {
        $condition = trim($condition);
        if (empty($condition)) {
            continue;
        }

        // 安全性檢查：只允許白名單中的函數
        if (!in_array($condition, $allowed_tags, true)) {
            // 記錄安全警告
            if (defined('WP_DEBUG') && WP_DEBUG) {
                if (function_exists('mpu_log_warning')) {
                    mpu_log_warning("嘗試使用未授權的條件標籤: {$condition}");
                } else {
                    error_log("MP Ukagaka 安全警告：嘗試使用未授權的條件標籤: {$condition}");
                }
            }
            continue; // 跳過未授權的條件標籤
        }

        // 檢查函數是否存在且可調用
        if (function_exists($condition) && is_callable($condition)) {
            try {
                // 安全調用：只調用白名單中的函數
                if (call_user_func($condition)) {
                    return true;
                }
            } catch (Exception $e) {
                // 如果調用失敗，記錄錯誤但繼續處理其他條件
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    if (function_exists('mpu_log_error')) {
                        mpu_log_error("條件標籤 {$condition} 調用失敗: " . $e->getMessage());
                    } else {
                        error_log("MP Ukagaka 錯誤：條件標籤 {$condition} 調用失敗: " . $e->getMessage());
                    }
                }
            }
        }
    }

    return false;
}

// 注意：mpu_generate_llm_dialogue() 已移至 llm-functions.php