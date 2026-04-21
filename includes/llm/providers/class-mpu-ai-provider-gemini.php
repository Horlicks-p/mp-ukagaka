<?php
/**
 * Gemini AI Provider.
 *
 * Implements the Gemini AI engine for single and multi-turn generation.
 *
 * @package MPU
 * @since 2.10.0
 */

if (!defined("ABSPATH")) {
    exit();
}

/**
 * Class MPU_AI_Provider_Gemini
 */
class MPU_AI_Provider_Gemini extends MPU_AI_Provider_Base {
    /**
     * Get slug.
     *
     * @return string
     */
    public function get_slug() {
        return 'gemini';
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
        $model         = $args['model'] ?? 'gemini-2.5-flash';
        $system_prompt = $args['system_prompt'] ?? '';
        $user_prompt   = $args['user_prompt'] ?? '';
        $language      = $args['language'] ?? 'zh-TW';
        $max_tokens    = $args['max_tokens'] ?? null;
        $temperature   = $args['temperature'] ?? 0.7;

        $language_instruction = mpu_get_language_instruction($language);
        
        // Initial conversation history
        $contents = [
            [
                "role" => "user",
                "parts" => [
                    ["text" => $system_prompt . "\n\n" . $language_instruction . "\n\n" . $user_prompt]
                ]
            ]
        ];

        // Get MCP tools
        $tools_config = [];
        if (function_exists('mpu_get_mcp_tools_for_llm')) {
            $mcp_tools = mpu_get_mcp_tools_for_llm('gemini');
            if (!empty($mcp_tools)) {
                $tools_config = [
                    "functionDeclarations" => $mcp_tools
                ];
            }
        }

        // Use v1beta for tools support
        $api_url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . urlencode($api_key);
        
        $max_turns = MPU_MAX_TOOL_TURNS;
        $current_turn = 0;
        $loop_state = [];

        while ($current_turn < $max_turns) {
            $request_body = [
                "contents" => $contents,
                "generationConfig" => [
                    "temperature" => $temperature,
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
                return $this->error("api_request_failed", sprintf(__('Gemini API リクエストに失敗しました：%s', 'mp-ukagaka'), $response->get_error_message()));
            }

            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);

            // Fallback: If 400 error due to tools, try removing tools and retry
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
                return $this->handle_api_error($response);
            }

            $data = mpu_json_decode_assoc($response_body);
            
            if (empty($data["candidates"][0]["content"])) {
                return $this->error("empty_response", __('Gemini API レスポンスが空です', 'mp-ukagaka'));
            }

            $candidate_content = $data["candidates"][0]["content"];
            $parts = $candidate_content["parts"];

            // Add model response to history
            $contents[] = $candidate_content;

            // Check for function calls
            $function_calls = [];
            foreach ($parts as $part) {
                if (isset($part["functionCall"])) {
                    $function_calls[] = $part["functionCall"];
                }
            }

            if (!empty($function_calls)) {
                $function_response_parts = [];
                
                foreach ($function_calls as $call) {
                    $function_name = $call["name"];
                    $call_args = $call["args"] ?? [];
                    
                    // Loop Guard check before execution
                    $guard_result = mpu_tool_loop_guard_check($loop_state, $this->get_slug(), $current_turn, $function_name, $call_args);
                    if (is_wp_error($guard_result)) {
                        return $guard_result;
                    }

                    $tool_result = function_exists('mpu_execute_mcp_tool')
                        ? mpu_execute_mcp_tool($function_name, $call_args)
                        : ['error' => 'Tool execution function missing'];

                    $function_response_parts[] = mpu_build_gemini_function_response_part($function_name, $tool_result);
                }

                $contents[] = [
                    "role" => "user",
                    "parts" => $function_response_parts
                ];

                $current_turn++;
                continue;
            }

            $text = null;
            foreach ($parts as $part) {
                if (isset($part['text']) && empty($part['thought'])) {
                    $text = $part['text'];
                    break;
                }
            }
            if ($text !== null) {
                return trim($text);
            }

            return $this->error("unknown_response_format", __('Gemini API レスポンス形式を識別できません', 'mp-ukagaka'));
        }

        return $this->error("max_turns_exceeded", __('Gemini API のツール呼び出し回数が多すぎます', 'mp-ukagaka'));
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
     * } $args
     * @return string|WP_Error
     */
    public function generate_chat(array $args) {
        $api_key       = $args['api_key'] ?? '';
        $model         = $args['model'] ?? 'gemini-2.5-flash';
        $messages      = $args['messages'] ?? [];
        $system_prompt = $args['system_prompt'] ?? '';
        $max_tokens    = $args['max_tokens'] ?? 1000;
        $temperature   = $args['temperature'] ?? 0.8;

        // Use v1beta for tools support
        $api_url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . urlencode($api_key);

        $contents = [];
        foreach ($messages as $msg) {
            $role = $msg['role'] === 'user' ? 'user' : 'model';
            $contents[] = [
                'role'  => $role,
                'parts' => [['text' => $msg['content']]]
            ];
        }

        // Get MCP tools
        $tools_config = [];
        if (function_exists('mpu_get_mcp_tools_for_llm')) {
            $mcp_tools = mpu_get_mcp_tools_for_llm('gemini');
            if (!empty($mcp_tools)) {
                $tools_config = [
                    "functionDeclarations" => $mcp_tools
                ];
            }
        }

        $max_turns = MPU_MAX_TOOL_TURNS;
        $current_turn = 0;
        $loop_state = [];

        while ($current_turn < $max_turns) {
            $request_body = [
                'systemInstruction' => [
                    'parts' => [['text' => $system_prompt]]
                ],
                'contents' => $contents,
                'generationConfig' => [
                    'maxOutputTokens' => intval($max_tokens),
                    'temperature'     => $temperature
                ]
            ];

            if (!empty($tools_config)) {
                $request_body['tools'] = [$tools_config];
            }

            $response = wp_remote_post($api_url,
                mpu_build_http_args(mpu_get_provider_headers('gemini'), $request_body));

            if (is_wp_error($response)) {
                return $this->error("api_request_failed", sprintf(__('Gemini API リクエストに失敗しました：%s', 'mp-ukagaka'), $response->get_error_message()));
            }

            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);

            // Fallback: If 400 error due to tools, try removing tools and retry
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
                return $this->handle_api_error($response);
            }

            $data = mpu_json_decode_assoc($response_body);
            
            if (empty($data['candidates'][0]['content'])) {
                 return $this->error('gemini_empty', __('Gemini から有効なレスポンスが返されませんでした', 'mp-ukagaka'));
            }

            $candidate_content = $data['candidates'][0]['content'];
            $parts = $candidate_content['parts'] ?? [];

            // Add model response to history
            $contents[] = $candidate_content;

            // Check for function calls
            $function_calls = [];
            if (!empty($parts)) {
                foreach ($parts as $part) {
                    if (isset($part["functionCall"])) {
                        $function_calls[] = $part["functionCall"];
                    }
                }
            }

            if (!empty($function_calls)) {
                $function_response_parts = [];
                if (function_exists('mpu_mark_request_mcp_tool_executed')) {
                    mpu_mark_request_mcp_tool_executed();
                }
                
                foreach ($function_calls as $call) {
                    $function_name = $call["name"];
                    $call_args = $call["args"] ?? [];
                    
                    // Loop Guard check before execution
                    $guard_result = mpu_tool_loop_guard_check($loop_state, $this->get_slug(), $current_turn, $function_name, $call_args);
                    if (is_wp_error($guard_result)) {
                        return $guard_result;
                    }

                    $tool_result = function_exists('mpu_execute_mcp_tool')
                        ? mpu_execute_mcp_tool($function_name, $call_args)
                        : ['error' => 'Tool execution function missing'];

                    $function_response_parts[] = mpu_build_gemini_function_response_part($function_name, $tool_result);
                }

                $contents[] = [
                    "role"  => "user",
                    "parts" => $function_response_parts
                ];

                $current_turn++;
                continue;
            }

            $text = null;
            foreach ($parts as $part) {
                if (isset($part['text']) && empty($part['thought'])) {
                    $text = $part['text'];
                    break;
                }
            }
            if ($text !== null) {
                return trim($text);
            }

            return $this->error("unknown_response_format", __('Gemini API レスポンス形式を識別できません', 'mp-ukagaka'));
        }

        return $this->error("max_turns_exceeded", __('Gemini API のツール呼び出し回数が多すぎます', 'mp-ukagaka'));
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
        $model   = $args['model'] ?? 'gemini-2.5-flash';

        if (empty($api_key)) {
            $mpu_opt           = mpu_get_option();
            $api_key_encrypted = $mpu_opt['llm_gemini_api_key'] ?? $mpu_opt['ai_api_key'] ?? '';
            if (!empty($api_key_encrypted)) {
                $api_key = mpu_decrypt_api_key($api_key_encrypted);
            }
        }

        if (empty($api_key)) {
            return $this->error('rest_error', __('Gemini API キーが設定されていません', 'mp-ukagaka'), 400);
        }

        $api_url      = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . urlencode($api_key);
        $request_body = [
            'contents'         => [['parts' => [['text' => 'Hi']]]],
            'generationConfig' => ['maxOutputTokens' => 200],
        ];

        $response = wp_remote_post($api_url, [
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => wp_json_encode($request_body),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return $this->error('rest_error', sprintf(__('接続に失敗しました：%s', 'mp-ukagaka'), $response->get_error_message()), 400);
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($response_code === 200) {
            $data  = json_decode($response_body, true);
            $parts = $data['candidates'][0]['content']['parts'] ?? [];
            $text  = null;
            foreach ($parts as $part) {
                if (isset($part['text']) && empty($part['thought'])) {
                    $text = $part['text'];
                    break;
                }
            }
            if ($text !== null) {
                $preview = mb_substr(trim($text), 0, 50);
                return new WP_REST_Response(['msg' => sprintf(__('接続成功。モデルが正常に応答しています（プレビュー：%s...）', 'mp-ukagaka'), $preview)], 200);
            } else {
                return $this->error('rest_error', __('接続は成功しましたが、レスポンス形式が異常でモデル出力を解析できません', 'mp-ukagaka'), 400);
            }
        } else {
            $error_data    = json_decode($response_body, true);
            $error_message = isset($error_data['error']['message'])
                ? $error_data['error']['message']
                : sprintf(__('HTTP %s エラー', 'mp-ukagaka'), $response_code);

            if ($response_code === 401 || $response_code === 403) {
                return $this->error('rest_error', sprintf(__('API キーが無効か、権限が不足しています：%s', 'mp-ukagaka'), $error_message), 400, $response_body);
            } elseif ($response_code === 404) {
                return $this->error('rest_error', sprintf(__('モデルが存在しません（%s）：%s', 'mp-ukagaka'), $model, $error_message), 400, $response_body);
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
        $err = $this->error('unsupported', __('Gemini は現在ストリーミングモードに対応していません', 'mp-ukagaka'));
        call_user_func($emit, 'error', ['message' => $err->get_error_message()]);
        return $err;
    }
}
