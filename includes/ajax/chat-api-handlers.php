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
    // 記錄發送給 AI 的提示詞
    // 檢查 WP_DEBUG 或 WP_DEBUG_LOG，如果都未啟用則強制記錄（用於調試）
    $wp_debug_enabled = defined('WP_DEBUG') && WP_DEBUG;
    $wp_debug_log_enabled = defined('WP_DEBUG_LOG') && WP_DEBUG_LOG;

    // 如果 WP_DEBUG 或 WP_DEBUG_LOG 啟用，則記錄調用資訊
    if ($wp_debug_enabled || $wp_debug_log_enabled) {
        if (function_exists('mpu_debug_log')) {
            mpu_debug_log('=== MP Ukagaka - AI API 調用（多輪對話） ===');
            mpu_debug_log('提供商: ' . $provider);
            mpu_debug_log('語言: ' . $language);
            mpu_debug_log('--- System Prompt ---');
            mpu_debug_log($system_prompt);
            mpu_debug_log('--- Messages (' . count($messages) . ' 條) ---');
            foreach ($messages as $index => $msg) {
                $role = isset($msg['role']) ? $msg['role'] : 'unknown';
                $content = isset($msg['content']) ? $msg['content'] : '';
                mpu_debug_log("  [{$index}] {$role}: " . mb_substr($content, 0, 200, 'UTF-8') . (mb_strlen($content, 'UTF-8') > 200 ? '...' : ''));
            }
            mpu_debug_log('=== End AI API 調用（多輪對話） ===');
        } else {
            // 後備方案：使用標準 error_log
            error_log('=== MP Ukagaka - AI API 調用（多輪對話） ===');
            error_log('提供商: ' . $provider);
            error_log('語言: ' . $language);
            error_log('--- System Prompt ---');
            error_log($system_prompt);
            error_log('--- Messages (' . count($messages) . ' 條) ---');
            foreach ($messages as $index => $msg) {
                $role = isset($msg['role']) ? $msg['role'] : 'unknown';
                $content = isset($msg['content']) ? $msg['content'] : '';
                error_log("  [{$index}] {$role}: " . mb_substr($content, 0, 200, 'UTF-8') . (mb_strlen($content, 'UTF-8') > 200 ? '...' : ''));
            }
            error_log('=== End AI API 調用（多輪對話） ===');
        }
    }

    $timeout = 60;
    $options['language'] = $language;

    switch ($provider) {
        case 'ollama':
            return mpu_call_ollama_with_messages($system_prompt, $messages, $options);

        case 'openai':
            return mpu_call_openai_with_messages($api_key, $system_prompt, $messages, $options);

        case 'claude':
            return mpu_call_claude_with_messages($api_key, $system_prompt, $messages, $options);

        case 'gemini':
        default:
            return mpu_call_gemini_with_messages($api_key, $system_prompt, $messages, $options);
    }
}

/**
 * Ollama API 呼叫（多輪對話）
 */
function mpu_call_ollama_with_messages($system_prompt, $messages, $options = [])
{
    $endpoint = $options['llm_ollama_endpoint'] ?? $options['ollama_endpoint'] ?? 'http://localhost:11434';
    $model = $options['llm_ollama_model'] ?? $options['ollama_model'] ?? 'qwen3:8b';
    $language = $options['language'] ?? 'ja';

    // 支援思考模式的模型
    $is_thinking_model = (strpos(strtolower($model), 'qwen3') !== false)
        || (strpos(strtolower($model), 'frieren') !== false)
        || (strpos(strtolower($model), 'deepseek') !== false);

    // 預設啟用思考模式
    $enable_thinking = $is_thinking_model && !(isset($options['ollama_disable_thinking']) && $options['ollama_disable_thinking']);

    $endpoint = rtrim($endpoint, '/');
    $api_url = $endpoint . '/api/chat';

    $language_instruction = mpu_get_language_instruction($language);
    $full_system_prompt = $system_prompt . "\n\n" . $language_instruction;

    $ollama_messages = [
        ['role' => 'system', 'content' => $full_system_prompt]
    ];

    foreach ($messages as $index => $msg) {
        $content = $msg['content'];

        // 如果關閉思考模式，添加 /no_think 指令
        if ($msg['role'] === 'user' && !$enable_thinking && $is_thinking_model && $index === count($messages) - 1) {
            $content = $content . ' /no_think';
        }

        $ollama_messages[] = [
            'role' => $msg['role'],
            'content' => $content
        ];
    }

    $request_body = [
        'model' => $model,
        'messages' => $ollama_messages,
        'stream' => false,
        'options' => [
            'num_predict' => 300,
            'temperature' => 0.8
        ]
    ];

    // 設定思考參數
    if ($is_thinking_model) {
        $request_body['think'] = $enable_thinking;
        if ($enable_thinking) {
            // 思考模式需要更大的 context window
            $request_body['options']['num_ctx'] = 8192;
        } else {
            $request_body['options']['num_ctx'] = 4096;
        }
    }

    $timeout = mpu_get_ollama_timeout($endpoint, 'chat');

    $response = wp_remote_post($api_url, [
        'headers' => ['Content-Type' => 'application/json'],
        'body' => wp_json_encode($request_body),
        'timeout' => $timeout,
    ]);

    if (is_wp_error($response)) {
        return $response;
    }

    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);

    if ($response_code !== 200) {
        return new WP_Error('ollama_error', sprintf(__('Ollama API 錯誤（HTTP %s）', 'mp-ukagaka'), $response_code));
    }

    $data = json_decode($response_body, true);

    $content = null;
    $thinking = null;

    if (isset($data["message"]) && is_array($data["message"])) {
        $message = $data["message"];

        if (isset($message["content"])) {
            $content = is_string($message["content"]) ? $message["content"] : null;
        }

        if (isset($message["thinking"])) {
            $thinking = is_string($message["thinking"]) ? $message["thinking"] : null;
        }
    }

    if ($content === null) {
        if (isset($data["content"]) && is_string($data["content"])) {
            $content = $data["content"];
        } elseif (isset($data["response"]) && is_string($data["response"])) {
            $content = $data["response"];
        } elseif (isset($data["message"]) && is_string($data["message"])) {
            $content = $data["message"];
        }
    }

    if (defined('WP_DEBUG') && WP_DEBUG) {
        if (function_exists('mpu_debug_log')) {
            mpu_debug_log('Ollama (Chat Mode) Extracted Content: ' . ($content !== null ? ('"' . mb_substr($content, 0, 100, 'UTF-8') . '"') : '(null)'));
            mpu_debug_log('Ollama (Chat Mode) Extracted Thinking: ' . ($thinking !== null ? ('"' . mb_substr($thinking, 0, 100, 'UTF-8') . '"') : '(null)'));
        } else {
            error_log('Ollama (Chat Mode) Extracted Content: ' . ($content !== null ? ('"' . mb_substr($content, 0, 100, 'UTF-8') . '"') : '(null)'));
            error_log('Ollama (Chat Mode) Extracted Thinking: ' . ($thinking !== null ? ('"' . mb_substr($thinking, 0, 100, 'UTF-8') . '"') : '(null)'));
        }
    }

    $final_response = null;

    if ($content !== null) {
        $trimmed_content = trim($content);
        $trimmed_content = mpu_filter_thinking_content($trimmed_content);

        $is_thinking_content = false;

        // 當思考模式啟用時，不需要過濾思考內容（因為 API 已經分離 thinking 和 content）
        // 只有在關閉思考模式時，才需要過濾可能洩漏的思考內容
        if (!$enable_thinking && !empty($trimmed_content)) {
            $thinking_patterns = [
                '/^Okay,?\s+(the\s+)?user/i',
                '/^The\s+user\s+(is\s+asking|mentioned|wants)/i',
                '/^Let\s+me\s+(recall|think|check|consider|remember)/i',
                '/^I\s+(need|should)\s+to\s+(respond|check|recall|remember)/i',
                '/^First,?\s+I\s+(need|should)/i',
                '/^(Based|According)\s+(on|to)\s+(the\s+)?(previous|system|user)/i',
                '/^The\s+(system|previous)\s+(info|prompt|message|conversation)/i',
                '/^I\s+recall\s+that/i',
                '/^I\s+remember\s+that/i',
                '/^(ユーザー|ユーザ)が/i',
                '/^まず[、,]?(私|僕)は/i',
                '/^(確認|チェック)し(ます|よう)/i',
            ];

            foreach ($thinking_patterns as $pattern) {
                if (preg_match($pattern, $trimmed_content)) {
                    $is_thinking_content = true;
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        if (function_exists('mpu_debug_log')) {
                            mpu_debug_log('Ollama (Chat): Thinking detected');
                        } else {
                            error_log('Ollama (Chat): Thinking detected');
                        }
                    }
                    break;
                }
            }
        }

        if (!$is_thinking_content && !empty($trimmed_content)) {
            $final_response = $trimmed_content;
        }
    }

    // 只有在思考模式開啟且 content 為空時，才使用 thinking 作為後備
    if ($final_response === null && $thinking !== null && $enable_thinking) {
        $trimmed_thinking = trim($thinking);
        if ($trimmed_thinking !== '') {
            $final_response = $trimmed_thinking;
        }
    }

    if ($final_response === null || $final_response === '') {
        $response_keys = array_keys($data);
        $debug_info = '響應鍵: [' . implode(', ', $response_keys) . ']';

        if (isset($data["message"]) && is_array($data["message"])) {
            $message_keys = array_keys($data["message"]);
            $debug_info .= ', message 鍵: [' . implode(', ', $message_keys) . ']';
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            if (function_exists('mpu_debug_log')) {
                mpu_debug_log('Ollama (Chat Mode) API 響應解析失敗: ' . $debug_info);
            } else {
                error_log('Ollama (Chat Mode) API 響應解析失敗: ' . $debug_info);
            }
        }

        return new WP_Error(
            'ollama_empty',
            sprintf(__('Ollama 未返回有效回應。%s。請檢查模型響應格式或嘗試啟用思考模式。', 'mp-ukagaka'), $debug_info)
        );
    }

    return $final_response;
}

/**
 * OpenAI API 呼叫（多輪對話）
 */
function mpu_call_openai_with_messages($api_key, $system_prompt, $messages, $options = [])
{
    $model = $options['llm_openai_model'] ?? 'gpt-4o-mini';
    $api_url = 'https://api.openai.com/v1/chat/completions';

    $openai_messages = [
        ['role' => 'system', 'content' => $system_prompt]
    ];

    foreach ($messages as $msg) {
        $openai_messages[] = [
            'role' => $msg['role'],
            'content' => $msg['content']
        ];
    }

    $request_body = [
        'model' => $model,
        'messages' => $openai_messages,
        'max_tokens' => 300,
        'temperature' => 0.8
    ];

    $response = wp_remote_post($api_url, [
        'headers' => [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $api_key
        ],
        'body' => wp_json_encode($request_body),
        'timeout' => 60,
    ]);

    if (is_wp_error($response)) {
        return $response;
    }

    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);

    if ($response_code !== 200) {
        $error_data = json_decode($response_body, true);
        $error_message = $error_data['error']['message'] ?? sprintf(__('HTTP %s 錯誤', 'mp-ukagaka'), $response_code);
        return new WP_Error('openai_error', $error_message);
    }

    $data = json_decode($response_body, true);

    if (!empty($data['choices'][0]['message']['content'])) {
        return trim($data['choices'][0]['message']['content']);
    }

    return new WP_Error('openai_empty', __('OpenAI 未返回有效回應', 'mp-ukagaka'));
}

/**
 * Claude API 呼叫（多輪對話）
 */
function mpu_call_claude_with_messages($api_key, $system_prompt, $messages, $options = [])
{
    $model = $options['llm_claude_model'] ?? 'claude-sonnet-4-5-20250929';
    $api_url = 'https://api.anthropic.com/v1/messages';

    $claude_messages = [];
    foreach ($messages as $msg) {
        $claude_messages[] = [
            'role' => $msg['role'],
            'content' => $msg['content']
        ];
    }

    $request_body = [
        'model' => $model,
        'max_tokens' => 300,
        'system' => $system_prompt,
        'messages' => $claude_messages
    ];

    $response = wp_remote_post($api_url, [
        'headers' => [
            'Content-Type' => 'application/json',
            'x-api-key' => $api_key,
            'anthropic-version' => '2023-06-01'
        ],
        'body' => wp_json_encode($request_body),
        'timeout' => 60,
    ]);

    if (is_wp_error($response)) {
        return $response;
    }

    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);

    if ($response_code !== 200) {
        $error_data = json_decode($response_body, true);
        $error_message = $error_data['error']['message'] ?? sprintf(__('HTTP %s 錯誤', 'mp-ukagaka'), $response_code);
        return new WP_Error('claude_error', $error_message);
    }

    $data = json_decode($response_body, true);

    if (!empty($data['content'][0]['text'])) {
        return trim($data['content'][0]['text']);
    }

    return new WP_Error('claude_empty', __('Claude 未返回有效回應', 'mp-ukagaka'));
}

/**
 * Gemini API 呼叫（多輪對話）
 */
function mpu_call_gemini_with_messages($api_key, $system_prompt, $messages, $options = [])
{
    $model = $options['llm_gemini_model'] ?? 'gemini-2.5-flash';
    $api_url = "https://generativelanguage.googleapis.com/v1/models/{$model}:generateContent?key=" . urlencode($api_key);

    $contents = [];

    foreach ($messages as $msg) {
        $role = $msg['role'] === 'user' ? 'user' : 'model';
        $contents[] = [
            'role' => $role,
            'parts' => [['text' => $msg['content']]]
        ];
    }

    $request_body = [
        'systemInstruction' => [
            'parts' => [['text' => $system_prompt]]
        ],
        'contents' => $contents,
        'generationConfig' => [
            'maxOutputTokens' => 300,
            'temperature' => 0.8
        ]
    ];

    $response = wp_remote_post($api_url, [
        'headers' => ['Content-Type' => 'application/json'],
        'body' => wp_json_encode($request_body),
        'timeout' => 60,
    ]);

    if (is_wp_error($response)) {
        return $response;
    }

    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);

    if ($response_code !== 200) {
        $error_data = json_decode($response_body, true);
        $error_message = $error_data['error']['message'] ?? sprintf(__('HTTP %s 錯誤', 'mp-ukagaka'), $response_code);
        return new WP_Error('gemini_error', $error_message);
    }

    $data = json_decode($response_body, true);

    if (!empty($data['candidates'][0]['content']['parts'][0]['text'])) {
        return trim($data['candidates'][0]['content']['parts'][0]['text']);
    }

    return new WP_Error('gemini_empty', __('Gemini 未返回有效回應', 'mp-ukagaka'));
}
