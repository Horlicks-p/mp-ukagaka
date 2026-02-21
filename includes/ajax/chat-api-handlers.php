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

    // 初始化 MCP 工具執行標記
    global $mpu_mcp_tool_executed;
    $mpu_mcp_tool_executed = false;

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
    $thinking_keywords = ['qwen3', 'frieren', 'deepseek'];
    $is_thinking_model = (bool) array_filter(
        $thinking_keywords,
        fn($kw) => stripos($model, $kw) !== false
    );

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

    // 獲取 MCP 工具
    $tools_config = [];
    if (function_exists('mpu_get_mcp_tools_for_llm')) {
        $mcp_tools = mpu_get_mcp_tools_for_llm('ollama');
        if (!empty($mcp_tools)) {
            $tools_config = $mcp_tools;
        }
    }

    $max_turns = 5;
    $current_turn = 0;
    $tool_executed = false;

    while ($current_turn < $max_turns) {
        
        $request_body = [
            'model' => $model,
            'messages' => $ollama_messages,
            'stream' => false,
            'options' => [
                'num_predict' => isset($options['max_tokens']) ? intval($options['max_tokens']) : 1000,
                'temperature' => 0.8
            ]
        ];

        if (!empty($tools_config)) {
            $request_body['tools'] = $tools_config;
        }

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

        $message = $data['message'];
        $ollama_messages[] = $message; // Add assistant response to history

        // Prepare variables for content and thinking extraction
        $content = null;
        $thinking = null;

        // Check for tool calls
        if (isset($message['tool_calls']) && !empty($message['tool_calls'])) {
            $tool_executed = true; // 標記為已執行工具
            global $mpu_mcp_tool_executed;
            $mpu_mcp_tool_executed = true; // 全域標記
            
            foreach ($message['tool_calls'] as $tool_call) {
                // ... (processing logic)
                $function_name = $tool_call['function']['name'];
                $arguments = $tool_call['function']['arguments']; // Ollama returns object directly
                
                $result = null;
                if (function_exists('mpu_execute_mcp_tool')) {
                    $result = mpu_execute_mcp_tool($function_name, $arguments);
                    if (is_wp_error($result)) {
                        $result = ["error" => $result->get_error_message()];
                    }
                } else {
                    $result = ["error" => "Tool execution function missing"];
                }

                // Add tool result to history
                $ollama_messages[] = [
                    'role' => 'tool',
                    'content' => json_encode($result)
                ];
            }
            $current_turn++;
            continue;
        }

        // Extract content and thinking
        if (isset($message["content"])) {
            $content = is_string($message["content"]) ? $message["content"] : null;
        }

        if (isset($message["thinking"])) {
            $thinking = is_string($message["thinking"]) ? $message["thinking"] : null;
        }

        // ... existing extraction logic kept for robustness ...
        if ($content === null) {
             if (isset($data["content"]) && is_string($data["content"])) {
                $content = $data["content"];
            } elseif (isset($data["response"]) && is_string($data["response"])) {
                $content = $data["response"];
            } elseif (isset($data["message"]) && is_string($data["message"])) {
                $content = $data["message"];
            }
        }
        
        // ... (logging and thinking logic) ...
        // Need to ensure existing thinking logic is preserved. 
        // The ReplaceFileContent tool requires existing content matchTargetContent.
        // I will match strictly to the block structure.

        // ... existing logging ...
        if (defined('WP_DEBUG') && WP_DEBUG) {
            if (function_exists('mpu_debug_log')) {
                mpu_debug_log('Ollama (Chat Mode) Extracted Content: ' . ($content !== null ? ('"' . mb_substr($content, 0, 100, 'UTF-8') . '"') : '(null)'));
            } else {
                 error_log('Ollama (Chat Mode) Extracted Content: ' . ($content !== null ? ('"' . mb_substr($content, 0, 100, 'UTF-8') . '"') : '(null)'));
            }
        }

        $final_response = null;

        if ($content !== null) {
            $trimmed_content = trim($content);
            $trimmed_content = mpu_filter_thinking_content($trimmed_content);
            
            // ... existing thinking filter logic ...
             $is_thinking_content = false;
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
                        break;
                    }
                }
            }

            if (!$is_thinking_content && !empty($trimmed_content)) {
                $final_response = $trimmed_content;
            }
        }

        // Output logic
        if ($final_response !== null && $final_response !== '') {
            return $final_response;
        }
        
        // Fallback for thinking-only response in thinking mode
        if ($final_response === null && $thinking !== null && $enable_thinking) {
            $trimmed_thinking = trim($thinking);
            if ($trimmed_thinking !== '') {
                return $trimmed_thinking;
            }
        }
        
        // If we loop and still don't have a response (e.g. tool call loop issue), we error out after max turns
    }

    return new WP_Error(
        'ollama_empty',
        sprintf(__('Ollama 未返回有效回應。請檢查模型響應格式或嘗試啟用思考模式。', 'mp-ukagaka'))
    );
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

    // 獲取 MCP 工具
    $tools_config = [];
    if (function_exists('mpu_get_mcp_tools_for_llm')) {
        $mcp_tools = mpu_get_mcp_tools_for_llm('openai');
        if (!empty($mcp_tools)) {
            $tools_config = $mcp_tools;
        }
    }

    $max_turns = 5;
    $current_turn = 0;
    $tool_executed = false;

    while ($current_turn < $max_turns) {
        
        $request_body = [
            'model' => $model,
            'messages' => $openai_messages,
            'max_tokens' => isset($options['max_tokens']) ? intval($options['max_tokens']) : 1000,
            'temperature' => 0.8
        ];

        if (!empty($tools_config)) {
            $request_body['tools'] = $tools_config;
            $request_body['tool_choice'] = 'auto';
        }

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
        $message = $data['choices'][0]['message'];

        // 將助手的回應加入歷史
        $openai_messages[] = $message;

        // 檢查是否有工具調用
        if (isset($message['tool_calls']) && !empty($message['tool_calls'])) {
            $tool_executed = true; // 標記為已執行工具（本地迴圈用）
            global $mpu_mcp_tool_executed;
            $mpu_mcp_tool_executed = true; // 全域標記（用於 user-chat-handler.php 跳過截斷）

            foreach ($message['tool_calls'] as $tool_call) {
                // ... (processing logic)
                $function_name = $tool_call['function']['name'];
                $arguments = json_decode($tool_call['function']['arguments'], true);
                $tool_call_id = $tool_call['id'];

                $result = null;
                if (function_exists('mpu_execute_mcp_tool')) {
                    $result = mpu_execute_mcp_tool($function_name, $arguments);
                    if (is_wp_error($result)) {
                        $result = ["error" => $result->get_error_message()];
                    }
                } else {
                    $result = ["error" => "Tool execution function missing"];
                }

                // 將工具結果加入歷史
                $openai_messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $tool_call_id,
                    'content' => json_encode($result)
                ];
            }
            $current_turn++;
            continue; // 繼續下一輪對話，讓 AI 處理結果
        }

        if (!empty($message['content'])) {
            return trim($message['content']);
        }
        
        return new WP_Error('openai_empty', __('OpenAI 未返回有效回應', 'mp-ukagaka'));
    }

    return new WP_Error('max_turns_exceeded', __('OpenAI 工具調用次數過多', 'mp-ukagaka'));
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

    // 獲取 MCP 工具
    $tools_config = [];
    if (function_exists('mpu_get_mcp_tools_for_llm')) {
        $mcp_tools = mpu_get_mcp_tools_for_llm('claude');
        if (!empty($mcp_tools)) {
            $tools_config = $mcp_tools;
        }
    }

    $max_turns = 5;
    $current_turn = 0;
    $tool_executed = false;

    while ($current_turn < $max_turns) {
        $request_body = [
            'model' => $model,
            'max_tokens' => isset($options['max_tokens']) ? intval($options['max_tokens']) : 1000,
            'system' => $system_prompt,
            'messages' => $claude_messages
        ];

        if (!empty($tools_config)) {
            $request_body['tools'] = $tools_config;
        }

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
        
        // 檢查 stop_reason
        $stop_reason = $data['stop_reason'] ?? null;
        $content = $data['content'] ?? [];

        // 將助手的回應加入歷史
        $claude_messages[] = [
            'role' => 'assistant',
            'content' => $content
        ];

        if ($stop_reason === 'tool_use') {
            $tool_executed = true; // 標記為已執行工具
            global $mpu_mcp_tool_executed;
            $mpu_mcp_tool_executed = true; // 全域標記
            
            $tool_results = [];
            foreach ($content as $block) {
                if ($block['type'] === 'tool_use') {
                    $tool_use_id = $block['id'];
                    $function_name = $block['name'];
                    $arguments = $block['input'];

                    $result = null;
                    $content_text = '';

                    if (function_exists('mpu_execute_mcp_tool')) {
                        $result = mpu_execute_mcp_tool($function_name, $arguments);
                    } else {
                        $result = new WP_Error('missing_tool', "Tool execution function missing");
                    }

                    if (is_wp_error($result)) {
                        $content_text = wp_json_encode(["error" => $result->get_error_message()]);
                    } elseif (is_string($result)) {
                        $content_text = $result;
                    } else {
                        $content_text = wp_json_encode($result);
                    }

                    $tool_results[] = [
                        'type' => 'tool_result',
                        'tool_use_id' => $tool_use_id,
                        'content' => $content_text
                    ];
                }
            }

            // 將工具結果加入歷史
            $claude_messages[] = [
                'role' => 'user',
                'content' => $tool_results
            ];

            $current_turn++;
            continue;
        }

        // 提取文本回應
        $text_response = '';
        foreach ($content as $block) {
            if ($block['type'] === 'text') {
                $text_response .= $block['text'];
            }
        }

        if (!empty($text_response)) {
             return trim($text_response);
        }
        
        return new WP_Error('claude_empty', __('Claude 未返回有效回應', 'mp-ukagaka'));
    }

    return new WP_Error('max_turns_exceeded', __('Claude 工具調用次數過多', 'mp-ukagaka'));
}

/**
 * Gemini API 呼叫（多輪對話）
 */
function mpu_call_gemini_with_messages($api_key, $system_prompt, $messages, $options = [])
{
    $model = $options['llm_gemini_model'] ?? 'gemini-2.0-flash-exp'; // Update default to a smarter model if possible, or keep 1.5-flash
    // Use v1beta for tools support
    $api_url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . urlencode($api_key);

    $contents = [];
    foreach ($messages as $msg) {
        $role = $msg['role'] === 'user' ? 'user' : 'model';
        $contents[] = [
            'role' => $role,
            'parts' => [['text' => $msg['content']]]
        ];
    }

    // 獲取 MCP 工具
    $tools_config = [];
    $mcp_tools = [];
    if (function_exists('mpu_get_mcp_tools_for_llm')) {
        $mcp_tools = mpu_get_mcp_tools_for_llm('gemini');
        if (!empty($mcp_tools)) {
            $tools_config = [
                "functionDeclarations" => $mcp_tools // User requested camelCase
            ];
        }
    }

    $max_turns = 5;
    $current_turn = 0;
    $tool_executed = false;

    while ($current_turn < $max_turns) {
        $request_body = [
            'systemInstruction' => [
                'parts' => [['text' => $system_prompt]]
            ],
            'contents' => $contents,
            'generationConfig' => [
                'maxOutputTokens' => isset($options['max_tokens']) ? intval($options['max_tokens']) : 1000,
                'temperature' => 0.8
            ]
        ];

        if (!empty($tools_config)) {
            $request_body['tools'] = [$tools_config];
        }

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

        // Fallback: 如果因為 tools 導致 400 錯誤，嘗試移除 tools 重試
        if ($response_code === 400 && !empty($tools_config)) {
             $error_data = json_decode($response_body, true);
             $error_msg = $error_data['error']['message'] ?? '';
             
             if (strpos($error_msg, 'tools') !== false || strpos($error_msg, 'Unknown name') !== false) {
                 unset($request_body['tools']);
                 $response = wp_remote_post($api_url, [
                    'headers' => ['Content-Type' => 'application/json'],
                    'body' => wp_json_encode($request_body),
                    'timeout' => 60,
                ]);
                $response_code = wp_remote_retrieve_response_code($response);
                $response_body = wp_remote_retrieve_body($response);
             }
        }

        if ($response_code !== 200) {
            $error_data = json_decode($response_body, true);
            $error_message = $error_data['error']['message'] ?? sprintf(__('HTTP %s 錯誤', 'mp-ukagaka'), $response_code);
            return new WP_Error('gemini_error', $error_message);
        }

        $data = json_decode($response_body, true);
        
        if (empty($data['candidates'][0]['content'])) {
             return new WP_Error('gemini_empty', __('Gemini 未返回有效回應', 'mp-ukagaka'));
        }

        $candidate_content = $data['candidates'][0]['content'];
        $parts = $candidate_content['parts'] ?? [];

        // 將模型的回答加入歷史
        $contents[] = $candidate_content;

        // 檢查是否有函數調用
        $function_calls = [];
        if (!empty($parts)) {
            foreach ($parts as $part) {
                if (isset($part["functionCall"])) {
                    $function_calls[] = $part["functionCall"];
                }
            }
        }

        if (!empty($function_calls)) {
            // 處理函數調用
            $function_response_parts = []; // Rename to match user snippet
            $tool_executed = true; 
            global $mpu_mcp_tool_executed;
            $mpu_mcp_tool_executed = true; 
            
            foreach ($function_calls as $call) {
                $function_name = $call["name"];
                $args = $call["args"] ?? [];
                
                // 執行工具
                $result = null;
                if (function_exists('mpu_execute_mcp_tool')) {
                    $result = mpu_execute_mcp_tool($function_name, $args);
                    
                    if (is_wp_error($result)) {
                        $result = ["error" => $result->get_error_message()];
                    } elseif (!is_array($result) && !is_object($result)) {
                        // Ensure result is an object/array for JSON serialization
                        $result = ["result" => (string) $result];
                    }
                } else {
                    $result = ["error" => "Tool execution function missing"];
                }

                $function_response_parts[] = [
                    "functionResponse" => [
                        "name" => $function_name,
                        "response" => $result // Direct result as response
                    ]
                ];
            }

            // 將函數結果加入歷史 - Role: user (as per user request)
            $contents[] = [
                "role" => "user",
                "parts" => $function_response_parts
            ];

            $current_turn++;
            continue;
        }

        // 如果沒有函數調用，返回文本
        if (isset($parts[0]['text'])) {
            return trim($parts[0]['text']);
        }
        
        return new WP_Error("unknown_response_format", __('Gemini API 回應格式無法識別', 'mp-ukagaka'));
    }

    return new WP_Error("max_turns_exceeded", __('Gemini API 工具調用次數過多', 'mp-ukagaka'));
}
