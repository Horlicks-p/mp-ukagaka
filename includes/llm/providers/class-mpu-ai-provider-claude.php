<?php
/**
 * Claude AI Provider.
 *
 * Implements the Claude (Anthropic) AI engine for single and multi-turn generation.
 *
 * @package MPU
 * @since 2.10.0
 */

if (!defined("ABSPATH")) {
    exit();
}

/**
 * Class MPU_AI_Provider_Claude
 */
class MPU_AI_Provider_Claude extends MPU_AI_Provider_Base {
    /**
     * Get slug.
     *
     * @return string
     */
    public function get_slug() {
        return 'claude';
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
        ]);
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
        $model         = $args['model'] ?? 'claude-sonnet-4-5-20250929';
        $system_prompt = $args['system_prompt'] ?? '';
        $user_prompt   = $args['user_prompt'] ?? '';
        $language      = $args['language'] ?? 'zh-TW';
        $max_tokens    = $args['max_tokens'] ?? null;
        $temperature   = $args['temperature'] ?? 0.7;

        $language_instruction = mpu_get_language_instruction($language);

        // Claude API endpoint
        $api_url = "https://api.anthropic.com/v1/messages";

        // Full system prompt
        $full_system_prompt = $system_prompt . "\n\n" . $language_instruction;

        // Initial messages
        $messages = [
            [
                "role" => "user",
                "content" => $user_prompt
            ]
        ];

        // Get MCP tools
        $tools = [];
        if (function_exists('mpu_get_mcp_tools_for_llm')) {
            $tools = mpu_get_mcp_tools_for_llm('claude');
        }

        $max_turns = MPU_MAX_TOOL_TURNS;
        $current_turn = 0;
        $loop_state = [];

        while ($current_turn < $max_turns) {
            $request_body = [
                "model"      => $model,
                "max_tokens" => $max_tokens !== null ? intval($max_tokens) : 100,
                "system"     => $full_system_prompt,
                "messages"   => $messages,
            ];

            if (!empty($tools)) {
                $request_body['tools'] = $tools;
            }

            $response = wp_remote_post($api_url,
                mpu_build_http_args(mpu_get_provider_headers('claude', $api_key), $request_body));

            if (is_wp_error($response)) {
                return $this->error("api_request_failed", sprintf(__('Claude API 請求失敗：%s', 'mp-ukagaka'), $response->get_error_message()));
            }

            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);

            if ($response_code !== 200) {
                return $this->handle_api_error($response);
            }

            $data = mpu_json_decode_assoc($response_body);
            
            if (empty($data["content"])) {
                return $this->error("invalid_response", __('Claude API 回應格式錯誤', 'mp-ukagaka'));
            }

            // Add assistant response to history
            $messages[] = [
                "role" => "assistant",
                "content" => $data["content"]
            ];

            // Check if stop_reason is tool_use
            if (isset($data['stop_reason']) && $data['stop_reason'] === 'tool_use') {
                $tool_results = [];

                foreach ($data['content'] as $content_block) {
                    if ($content_block['type'] === 'tool_use') {
                        $tool_use_id = $content_block['id'];
                        $function_name = $content_block['name'];
                        $tool_args = $content_block['input'];

                        // Loop Guard check before execution
                        $guard_result = mpu_tool_loop_guard_check($loop_state, $this->get_slug(), $current_turn, $function_name, $tool_args);
                        if (is_wp_error($guard_result)) {
                            return $guard_result;
                        }

                        $tool_result = null;
                        if (function_exists('mpu_execute_mcp_tool')) {
                            $tool_result = mpu_execute_mcp_tool($function_name, $tool_args);
                        } else {
                            $tool_result = ["error" => "Tool execution function missing"];
                        }

                        $tool_results[] = mpu_build_claude_tool_result_block($tool_use_id, $tool_result);
                    }
                }

                $messages[] = [
                    "role" => "user",
                    "content" => $tool_results
                ];

                $current_turn++;
                continue;
            }

            // Extract text response
            foreach ($data['content'] as $content_block) {
                if ($content_block['type'] === 'text') {
                    return trim($content_block['text']);
                }
            }

            return $this->error("empty_content", __('Claude API 回應內容為空', 'mp-ukagaka'));
        }

        return $this->error("max_turns_exceeded", __('Claude API 工具調用次數過多', 'mp-ukagaka'));
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
        $model         = $args['model'] ?? 'claude-sonnet-4-5-20250929';
        $messages      = $args['messages'] ?? [];
        $system_prompt = $args['system_prompt'] ?? '';
        $max_tokens    = $args['max_tokens'] ?? 1000;
        $temperature   = $args['temperature'] ?? 0.8;

        $api_url = 'https://api.anthropic.com/v1/messages';

        $claude_messages = [];
        foreach ($messages as $msg) {
            $claude_messages[] = [
                'role'    => $msg['role'],
                'content' => $msg['content']
            ];
        }

        // Get MCP tools
        $tools_config = [];
        if (function_exists('mpu_get_mcp_tools_for_llm')) {
            $mcp_tools = mpu_get_mcp_tools_for_llm('claude');
            if (!empty($mcp_tools)) {
                $tools_config = $mcp_tools;
            }
        }

        $max_turns = MPU_MAX_TOOL_TURNS;
        $current_turn = 0;
        $loop_state = [];

        while ($current_turn < $max_turns) {
            $request_body = [
                'model'      => $model,
                'max_tokens' => intval($max_tokens),
                'system'     => $system_prompt,
                'messages'   => $claude_messages,
                'temperature'=> $temperature
            ];

            if (!empty($tools_config)) {
                $request_body['tools'] = $tools_config;
            }

            $response = wp_remote_post($api_url,
                mpu_build_http_args(mpu_get_provider_headers('claude', $api_key), $request_body));

            if (is_wp_error($response)) {
                return $this->error("api_request_failed", sprintf(__('Claude API 請求失敗：%s', 'mp-ukagaka'), $response->get_error_message()));
            }

            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);

            if ($response_code !== 200) {
                return $this->handle_api_error($response);
            }

            $data = mpu_json_decode_assoc($response_body);
            
            // Check stop_reason
            $stop_reason = $data['stop_reason'] ?? null;
            $content = $data['content'] ?? [];

            // Add assistant response to history
            $claude_messages[] = [
                'role'    => 'assistant',
                'content' => $content
            ];

            if ($stop_reason === 'tool_use') {
                if (function_exists('mpu_mark_request_mcp_tool_executed')) {
                    mpu_mark_request_mcp_tool_executed();
                }
                
                $tool_results = [];
                foreach ($content as $block) {
                    if ($block['type'] === 'tool_use') {
                        $tool_use_id = $block['id'];
                        $function_name = $block['name'];
                        $tool_args = $block['input'];

                        // Loop Guard check before execution
                        $guard_result = mpu_tool_loop_guard_check($loop_state, $this->get_slug(), $current_turn, $function_name, $tool_args);
                        if (is_wp_error($guard_result)) {
                            return $guard_result;
                        }

                        $tool_result = null;
                        if (function_exists('mpu_execute_mcp_tool')) {
                            $tool_result = mpu_execute_mcp_tool($function_name, $tool_args);
                        } else {
                            $tool_result = new WP_Error('missing_tool', 'Tool execution function missing');
                        }

                        $tool_results[] = mpu_build_claude_tool_result_block($tool_use_id, $tool_result);
                    }
                }

                $claude_messages[] = [
                    'role'    => 'user',
                    'content' => $tool_results
                ];

                $current_turn++;
                continue;
            }

            // Extract text response
            $text_response = '';
            foreach ($content as $block) {
                if ($block['type'] === 'text') {
                    $text_response .= $block['text'];
                }
            }

            if (!empty($text_response)) {
                 return trim($text_response);
            }
            
            return $this->error('claude_empty', __('Claude 未返回有效回應', 'mp-ukagaka'));
        }

        return $this->error('max_turns_exceeded', __('Claude 工具調用次數過多', 'mp-ukagaka'));
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
        $model   = $args['model'] ?? 'claude-sonnet-4-5-20250929';

        if (empty($api_key)) {
            $mpu_opt           = mpu_get_option();
            $api_key_encrypted = $mpu_opt['llm_claude_api_key'] ?? $mpu_opt['claude_api_key'] ?? '';
            if (!empty($api_key_encrypted)) {
                $api_key = mpu_decrypt_api_key($api_key_encrypted);
            }
        }

        if (empty($api_key)) {
            return $this->error('rest_error', __('Claude API Key 未設定', 'mp-ukagaka'), 400);
        }

        $response = wp_remote_post('https://api.anthropic.com/v1/messages', [
            'headers' => [
                'Content-Type'      => 'application/json',
                'x-api-key'         => $api_key,
                'anthropic-version' => '2023-06-01',
            ],
            'body'    => wp_json_encode([
                'model'      => $model,
                'max_tokens' => 50,
                'messages'   => [['role' => 'user', 'content' => 'Hi']],
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
            if (!empty($data['content'][0]['text'])) {
                $preview = mb_substr(trim($data['content'][0]['text']), 0, 50);
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

    /**
     * SSE Streaming chat generation.
     *
     * @param array $args
     * @param callable $emit function(string $event, array|string $data): void
     * @param array $context Additional request context
     * @return void|WP_Error
     */
    public function generate_chat_stream(array $args, $emit, array $context = []) {
        $err = $this->error('unsupported', __('Claude 目前不支援串流模式', 'mp-ukagaka'));
        call_user_func($emit, 'error', ['message' => $err->get_error_message()]);
        return $err;
    }
}
