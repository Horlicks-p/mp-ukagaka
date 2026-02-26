<?php
/**
 * OpenAI Provider.
 *
 * Implements the OpenAI API for single and multi-turn generation.
 *
 * @package MPU
 * @since 2.10.0
 */

if (!defined("ABSPATH")) {
    exit();
}

/**
 * Class MPU_AI_Provider_OpenAI
 */
class MPU_AI_Provider_OpenAI extends MPU_AI_Provider_Base {
    /**
     * Get slug.
     *
     * @return string
     */
    public function get_slug() {
        return 'openai';
    }

    /**
     * Supports specific feature.
     *
     * @param string $feature
     * @return bool
     */
    public function supports($feature) {
        return in_array($feature, [
            self::FEATURE_TOOLS,
            self::FEATURE_CHAT,
            self::FEATURE_STREAMING,
        ]);
    }

    /**
     * SSE Streaming chat generation.
     *
     * @param array $args
     * @param callable $emit function(string $event, array|string $data): void
     * @param array $context Additional request context
     * @return void|WP_Error
     */
    public function generate_chat_stream(array $args, $emit, array $context = []) {
        $api_key       = $args['api_key'] ?? '';
        $model         = $args['model'] ?? 'gpt-4o-mini';
        $messages      = $args['messages'] ?? [];
        $system_prompt = $args['system_prompt'] ?? '';
        $max_tokens    = $args['max_tokens'] ?? 1000;
        $temperature   = $args['temperature'] ?? 0.8;

        $api_url = 'https://api.openai.com/v1/chat/completions';

        $openai_messages = [
            ['role' => 'system', 'content' => $system_prompt]
        ];

        foreach ($messages as $msg) {
            $openai_messages[] = [
                'role'    => $msg['role'],
                'content' => $msg['content']
            ];
        }

        // Get MCP tools
        $tools_config = [];
        if (function_exists('mpu_get_mcp_tools_for_llm')) {
            $mcp_tools = mpu_get_mcp_tools_for_llm('openai');
            if (!empty($mcp_tools)) {
                $tools_config = $mcp_tools;
            }
        }

        $max_turns = MPU_MAX_TOOL_TURNS;
        $current_turn = 0;
        $loop_state = [];
        $full_response_content = "";

        while ($current_turn < $max_turns) {
            $request_body = [
                'model'       => $model,
                'messages'    => $openai_messages,
                'max_tokens'  => intval($max_tokens),
                'temperature' => $temperature,
                'stream'      => true
            ];

            if (!empty($tools_config)) {
                $request_body['tools'] = $tools_config;
                $request_body['tool_choice'] = 'auto';
            }

            $tool_calls_buffer = [];
            $current_finish_reason = null;
            $chunk_buffer = "";

            $result = mpu_stream_api_request(
                $api_url,
                mpu_build_http_args(mpu_get_provider_headers('openai', $api_key), $request_body),
                function($chunk) use (&$chunk_buffer, &$tool_calls_buffer, &$current_finish_reason, &$full_response_content, $emit) {
                    $chunk_buffer .= $chunk;
                    
                    while (($pos = strpos($chunk_buffer, "\n")) !== false) {
                        $line = trim(substr($chunk_buffer, 0, $pos));
                        $chunk_buffer = substr($chunk_buffer, $pos + 1);

                        if (empty($line) || strpos($line, 'data: ') !== 0) continue;
                        
                        $data_str = substr($line, 6);
                        if ($data_str === '[DONE]') break;

                        $data = json_decode($data_str, true);
                        if (empty($data['choices'][0])) continue;

                        $choice = $data['choices'][0];
                        $delta = $choice['delta'] ?? [];

                        // 處理文字內容
                        if (isset($delta['content']) && $delta['content'] !== null) {
                            $text = $delta['content'];
                            $full_response_content .= $text;
                            call_user_func($emit, 'delta', ['text' => $text]);
                        }

                        // 處理工具呼叫 (Streaming mode)
                        if (isset($delta['tool_calls'])) {
                            foreach ($delta['tool_calls'] as $tc_delta) {
                                $idx = $tc_delta['index'];
                                if (!isset($tool_calls_buffer[$idx])) {
                                    $tool_calls_buffer[$idx] = [
                                        'id' => '',
                                        'type' => 'function',
                                        'function' => ['name' => '', 'arguments' => '']
                                    ];
                                }

                                if (isset($tc_delta['id'])) {
                                    $tool_calls_buffer[$idx]['id'] .= $tc_delta['id'];
                                }
                                if (isset($tc_delta['function']['name'])) {
                                    $tool_calls_buffer[$idx]['function']['name'] .= $tc_delta['function']['name'];
                                }
                                if (isset($tc_delta['function']['arguments'])) {
                                    $tool_calls_buffer[$idx]['function']['arguments'] .= $tc_delta['function']['arguments'];
                                }
                            }
                        }

                        if (isset($choice['finish_reason'])) {
                            $current_finish_reason = $choice['finish_reason'];
                        }
                    }
                }
            );

            if (is_wp_error($result)) {
                return $result;
            }

            // 檢查是否需要執行工具
            if ($current_finish_reason === 'tool_calls' && !empty($tool_calls_buffer)) {
                // 將工具呼叫加入對話歷史
                $assistant_msg = [
                    'role' => 'assistant',
                    'content' => $full_response_content ?: null,
                    'tool_calls' => array_values($tool_calls_buffer)
                ];
                $openai_messages[] = $assistant_msg;

                if (function_exists('mpu_mark_request_mcp_tool_executed')) {
                    mpu_mark_request_mcp_tool_executed();
                }

                foreach ($tool_calls_buffer as $tool_call) {
                    $function_name = $tool_call['function']['name'];
                    $tool_args = json_decode($tool_call['function']['arguments'], true);
                    $tool_call_id = $tool_call['id'];

                    call_user_func($emit, 'status', [
                        'type'           => 'executing_tool',
                        'tool'           => $function_name,
                        'executing_tool' => $function_name, // 保留相容性
                    ]);

                    // Loop Guard check
                    $guard_result = mpu_tool_loop_guard_check($loop_state, $this->get_slug(), $current_turn, $function_name, $tool_args);
                    if (is_wp_error($guard_result)) {
                        return $guard_result;
                    }

                    $tool_result = null;
                    if (function_exists('mpu_execute_mcp_tool')) {
                        $tool_result = mpu_execute_mcp_tool($function_name, $tool_args);
                        if (is_wp_error($tool_result)) {
                            $tool_result = ["error" => $tool_result->get_error_message()];
                        }
                    } else {
                        $tool_result = ["error" => "Tool execution function missing"];
                    }

                    $openai_messages[] = mpu_build_openai_tool_message($tool_call_id, $function_name, $tool_result);
                    
                    call_user_func($emit, 'tool_result', [
                        'tool' => $function_name,
                        'success' => !isset($tool_result['error'])
                    ]);
                }

                $current_turn++;
                $full_response_content = ""; // 重置文字緩存，準備下一輪串流
                continue;
            }

            // 正常結束
            return;
        }

        $err = $this->error('max_turns_exceeded', __('OpenAI 工具調用次數過多', 'mp-ukagaka'));
        return $err;
    }


    /**
     * Single-turn text generation.
     *
     * @param array{
     *   api_key:       string,
     *   model:         string,
     *   system_prompt: string,
     *   user_prompt:   string,
     *   language:      string,
     *   max_tokens:    int|null,
     *   temperature:   float|null,
     * } $args
     * @return string|WP_Error
     */
    public function generate_text(array $args) {
        $api_key       = $args['api_key'] ?? '';
        $model         = $args['model'] ?? 'gpt-4o-mini';
        $system_prompt = $args['system_prompt'] ?? '';
        $user_prompt   = $args['user_prompt'] ?? '';
        $language      = $args['language'] ?? 'zh-TW';
        $max_tokens    = $args['max_tokens'] ?? null;
        $temperature   = $args['temperature'] ?? 0.7;

        $language_instruction = mpu_get_language_instruction($language);

        // OpenAI API endpoint
        $api_url = "https://api.openai.com/v1/chat/completions";

        // Initial messages
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

        // Get MCP tools
        $tools = [];
        if (function_exists('mpu_get_mcp_tools_for_llm')) {
            $tools = mpu_get_mcp_tools_for_llm('openai');
        }

        $max_turns = MPU_MAX_TOOL_TURNS;
        $current_turn = 0;
        $loop_state = [];

        while ($current_turn < $max_turns) {
            $request_body = [
                "model" => $model,
                "messages" => $messages,
                "temperature" => $temperature,
                "max_tokens" => $max_tokens !== null ? intval($max_tokens) : 100,
            ];

            if (!empty($tools)) {
                $request_body['tools'] = $tools;
            }

            $response = wp_remote_post($api_url,
                mpu_build_http_args(mpu_get_provider_headers('openai', $api_key), $request_body));

            if (is_wp_error($response)) {
                return $this->error("api_request_failed", sprintf(__('OpenAI API 請求失敗：%s', 'mp-ukagaka'), $response->get_error_message()));
            }

            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);

            if ($response_code !== 200) {
                return $this->handle_api_error($response);
            }

            $data = mpu_json_decode_assoc($response_body);
            
            if (empty($data["choices"][0]["message"])) {
                return $this->error("invalid_response", __('OpenAI API 回應格式錯誤', 'mp-ukagaka'));
            }

            $message = $data["choices"][0]["message"];

            // Tool call check
            if (isset($message['tool_calls']) && !empty($message['tool_calls'])) {
                $messages[] = $message;

                foreach ($message['tool_calls'] as $tool_call) {
                    $function_name = $tool_call['function']['name'];
                    $arguments_json = $tool_call['function']['arguments'];
                    $tool_args = json_decode($arguments_json, true);
                    
                    // Loop Guard check before execution
                    $guard_result = mpu_tool_loop_guard_check($loop_state, $this->get_slug(), $current_turn, $function_name, $tool_args);
                    if (is_wp_error($guard_result)) {
                        return $guard_result;
                    }

                    $tool_result = null;
                    if (function_exists('mpu_execute_mcp_tool')) {
                        $tool_result = mpu_execute_mcp_tool($function_name, $tool_args);
                    } else {
                        $tool_result = new WP_Error('function_missing', 'Tool execution function missing.');
                    }

                    $messages[] = mpu_build_openai_tool_message($tool_call['id'], $function_name, $tool_result);
                }

                $current_turn++;
                continue;
            }

            if (isset($message['content'])) {
                return trim($message['content']);
            }

            return $this->error("empty_content", __('OpenAI API 回應內容為空', 'mp-ukagaka'));
        }

        return $this->error("max_turns_exceeded", __('OpenAI API 工具調用次數過多', 'mp-ukagaka'));
    }

    /**
     * Multi-turn chat generation.
     *
     * @param array{
     *   api_key:       string,
     *   model:         string,
     *   messages:      array,
     *   language:      string,
     *   max_tokens:    int|null,
     *   temperature:   float|null,
     *   system_prompt: string|null,
     * } $args
     * @return string|WP_Error
     */
    public function generate_chat(array $args) {
        $api_key       = $args['api_key'] ?? '';
        $model         = $args['model'] ?? 'gpt-4o-mini';
        $messages      = $args['messages'] ?? [];
        $system_prompt = $args['system_prompt'] ?? '';
        $max_tokens    = $args['max_tokens'] ?? 1000;
        $temperature   = $args['temperature'] ?? 0.8;

        $api_url = 'https://api.openai.com/v1/chat/completions';

        $openai_messages = [
            ['role' => 'system', 'content' => $system_prompt]
        ];

        foreach ($messages as $msg) {
            $openai_messages[] = [
                'role'    => $msg['role'],
                'content' => $msg['content']
            ];
        }

        // Get MCP tools
        $tools_config = [];
        if (function_exists('mpu_get_mcp_tools_for_llm')) {
            $mcp_tools = mpu_get_mcp_tools_for_llm('openai');
            if (!empty($mcp_tools)) {
                $tools_config = $mcp_tools;
            }
        }

        $max_turns = MPU_MAX_TOOL_TURNS;
        $current_turn = 0;
        $loop_state = [];

        while ($current_turn < $max_turns) {
            $request_body = [
                'model'       => $model,
                'messages'    => $openai_messages,
                'max_tokens'  => intval($max_tokens),
                'temperature' => $temperature
            ];

            if (!empty($tools_config)) {
                $request_body['tools'] = $tools_config;
                $request_body['tool_choice'] = 'auto';
            }

            $response = wp_remote_post($api_url,
                mpu_build_http_args(mpu_get_provider_headers('openai', $api_key), $request_body));

            if (is_wp_error($response)) {
                return $this->error("api_request_failed", sprintf(__('OpenAI API 請求失敗：%s', 'mp-ukagaka'), $response->get_error_message()));
            }

            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);

            if ($response_code !== 200) {
                return $this->handle_api_error($response);
            }

            $data = mpu_json_decode_assoc($response_body);
            $message = $data['choices'][0]['message'];

            // Add assistant response to history
            $openai_messages[] = $message;

            // Tool call check
            if (isset($message['tool_calls']) && !empty($message['tool_calls'])) {
                if (function_exists('mpu_mark_request_mcp_tool_executed')) {
                    mpu_mark_request_mcp_tool_executed();
                }

                foreach ($message['tool_calls'] as $tool_call) {
                    $function_name = $tool_call['function']['name'];
                    $tool_args = json_decode($tool_call['function']['arguments'], true);
                    $tool_call_id = $tool_call['id'];

                    // Loop Guard check before execution
                    $guard_result = mpu_tool_loop_guard_check($loop_state, $this->get_slug(), $current_turn, $function_name, $tool_args);
                    if (is_wp_error($guard_result)) {
                        return $guard_result;
                    }

                    $tool_result = null;
                    if (function_exists('mpu_execute_mcp_tool')) {
                        $tool_result = mpu_execute_mcp_tool($function_name, $tool_args);
                        if (is_wp_error($tool_result)) {
                            $tool_result = ["error" => $tool_result->get_error_message()];
                        }
                    } else {
                        $tool_result = ["error" => "Tool execution function missing"];
                    }

                    $openai_messages[] = mpu_build_openai_tool_message($tool_call_id, $function_name, $tool_result);
                }
                $current_turn++;
                continue;
            }

            if (!empty($message['content'])) {
                return trim($message['content']);
            }
            
            return $this->error('openai_empty', __('OpenAI 未返回有效回應', 'mp-ukagaka'));
        }

        return $this->error('max_turns_exceeded', __('OpenAI 工具調用次數過多', 'mp-ukagaka'));
    }

    /**
     * Backend connection test.
     *
     * @param array{
     *   api_key:       string,
     *   model:         string,
     *   endpoint:      string|null,
     * } $args
     * @return WP_REST_Response|WP_Error
     */
    public function test_connection(array $args) {
        $api_key = $args['api_key'] ?? '';
        $model   = $args['model'] ?? 'gpt-4o-mini';

        if (empty($api_key)) {
            $mpu_opt           = mpu_get_option();
            $api_key_encrypted = $mpu_opt['llm_openai_api_key'] ?? $mpu_opt['openai_api_key'] ?? '';
            if (!empty($api_key_encrypted)) {
                $api_key = mpu_decrypt_api_key($api_key_encrypted);
            }
        }

        if (empty($api_key)) {
            return $this->error('rest_error', __('OpenAI API Key 未設定', 'mp-ukagaka'), 400);
        }

        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ],
            'body'    => wp_json_encode([
                'model'      => $model,
                'messages'   => [['role' => 'user', 'content' => 'Hi']],
                'max_tokens' => 50,
            ]),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return $this->error('rest_error', sprintf(__('連接失敗：%s', 'mp-ukagaka'), $response->get_error_message()), 400);
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($response_code === 200) {
            $data = json_decode($response_body, true);
            if (!empty($data['choices'][0]['message']['content'])) {
                $preview = mb_substr(trim($data['choices'][0]['message']['content']), 0, 50);
                return new WP_REST_Response(['msg' => sprintf(__('連接成功，模型響應正常（預覽：%s...）', 'mp-ukagaka'), $preview)], 200);
            } else {
                return $this->error('rest_error', __('連接成功但回應格式異常，無法解析模型輸出', 'mp-ukagaka'), 400);
            }
        } else {
            $error_data    = json_decode($response_body, true);
            $error_message = isset($error_data['error']['message'])
                ? $error_data['error']['message']
                : sprintf(__('HTTP %s 錯誤', 'mp-ukagaka'), $response_code);

            if ($response_code === 401 || $response_code === 403) {
                return $this->error('rest_error', sprintf(__('API Key 無效或權限不足：%s', 'mp-ukagaka'), $error_message), 400, $response_body);
            } else {
                return $this->error('rest_error', $error_message, 400, $response_body);
            }
        }
    }
}
