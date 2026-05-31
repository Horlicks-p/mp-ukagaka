<?php
/**
 * Ollama AI Provider.
 *
 * Implements the Ollama (local LLM) engine for single and multi-turn generation.
 *
 * @package MPU
 * @since 2.10.0
 */

if (!defined("ABSPATH")) {
    exit();
}

/**
 * Class MPU_AI_Provider_Ollama
 */
class MPU_AI_Provider_Ollama extends MPU_AI_Provider_Base {
    /**
     * Get slug.
     *
     * @return string
     */
    public function get_slug() {
        return 'ollama';
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
            self::FEATURE_LOCAL_ENDPOINT,
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
        $endpoint      = $args['endpoint'] ?? 'http://localhost:11434';
        $model         = $args['model'] ?? 'qwen3:8b';
        $messages      = $args['messages'] ?? [];
        $system_prompt = $args['system_prompt'] ?? '';
        $language      = $args['language'] ?? 'zh-TW';
        $max_tokens    = $args['max_tokens'] ?? 1000;
        $temperature   = $args['temperature'] ?? 0.8;

        $thinking_keywords = ['qwen3', 'frieren', 'deepseek'];
        $is_thinking_model = (bool) array_filter(
            $thinking_keywords,
            fn($kw) => stripos($model, $kw) !== false
        );

        $mpu_opt = mpu_get_option();
        $enable_thinking = $is_thinking_model && !(isset($mpu_opt['ollama_disable_thinking']) && $mpu_opt['ollama_disable_thinking']);

        $endpoint = rtrim($endpoint, '/');
        $api_url = $endpoint . '/api/chat';

        $language_instruction = mpu_get_language_instruction($language);
        $full_system_prompt = $system_prompt . "\n\n" . $language_instruction;

        $ollama_messages = [
            ['role' => 'system', 'content' => $full_system_prompt]
        ];

        foreach ($messages as $index => $msg) {
            $content = $msg['content'];
            if ($msg['role'] === 'user' && !$enable_thinking && $is_thinking_model && $index === count($messages) - 1) {
                $content = $content . ' /no_think';
            }
            $ollama_messages[] = ['role' => $msg['role'], 'content' => $content];
        }

        $tools_config = [];
        if (function_exists('mpu_get_mcp_tools_for_llm')) {
            $mcp_tools = mpu_get_mcp_tools_for_llm('ollama');
            if (!empty($mcp_tools)) {
                $tools_config = $mcp_tools;
            }
        }

        $max_turns = MPU_MAX_TOOL_TURNS;
        $current_turn = 0;
        $loop_state = [];

        while ($current_turn < $max_turns) {
            $request_body = [
                'model'    => $model,
                'messages' => $ollama_messages,
                'stream'   => true,
                'options'  => [
                    'num_predict' => intval($max_tokens),
                    'temperature' => $temperature
                ]
            ];

            if (!empty($tools_config)) {
                $request_body['tools'] = $tools_config;
            }

            if ($is_thinking_model) {
                $request_body['think'] = $enable_thinking;
            }

            $timeout = mpu_get_ollama_timeout($endpoint, 'chat');
            $current_tool_calls = [];
            $full_response_content = "";
            $chunk_buffer = "";

			$thinking_open = false;

            $result = mpu_stream_api_request(
                $api_url,
                mpu_build_http_args(mpu_get_provider_headers('ollama'), $request_body, $timeout),
				function( $chunk ) use ( &$chunk_buffer, &$current_tool_calls, &$full_response_content, &$thinking_open, $emit ) {
                    $chunk_buffer .= $chunk;
                    while (($pos = strpos($chunk_buffer, "\n")) !== false) {
                        $line = trim(substr($chunk_buffer, 0, $pos));
                        $chunk_buffer = substr($chunk_buffer, $pos + 1);
                        if (empty($line)) continue;

                        $data = json_decode($line, true);
                        if (!$data) continue;

						if ( isset( $data['message']['thinking'] ) && is_string( $data['message']['thinking'] ) && '' !== $data['message']['thinking'] ) {
							if ( ! $thinking_open ) {
								$thinking_open = true;
								call_user_func( $emit, 'delta', array( 'text' => '<think>' ) );
							}
							call_user_func( $emit, 'delta', array( 'text' => $data['message']['thinking'] ) );
						}

                        if (isset($data['message']['content'])) {
                            $text = $data['message']['content'];
							if ( $thinking_open && '' !== $text ) {
								$thinking_open = false;
								call_user_func( $emit, 'delta', array( 'text' => '</think>' ) );
							}
                            $full_response_content .= $text;
                            call_user_func($emit, 'delta', ['text' => $text]);
                        }

                        if (isset($data['message']['tool_calls'])) {
                            foreach ($data['message']['tool_calls'] as $tc) {
                                $current_tool_calls[] = $tc;
                            }
                        }

                        if (isset($data['done']) && $data['done'] === true) break;
                    }
                }
            );

            if (is_wp_error($result)) {
                return $result;
            }

			if ( $thinking_open ) {
				$thinking_open = false;
				call_user_func( $emit, 'delta', array( 'text' => '</think>' ) );
			}

            // [Fix] 串流結束後，對累積的完整內容進行過濾，確保與同步模式一致
            if (!empty($full_response_content)) {
                $full_response_content = mpu_filter_thinking_content($full_response_content);
            }

            // Ollama 串流模式下，工具呼叫通常隨 done:true 一起完成
            if (!empty($current_tool_calls)) {
                $assistant_msg = [
                    'role' => 'assistant',
                    'content' => $full_response_content ?: null,
                    'tool_calls' => $current_tool_calls
                ];
                $ollama_messages[] = mpu_normalize_ollama_assistant_message($assistant_msg);

                if (function_exists('mpu_mark_request_mcp_tool_executed')) {
                    mpu_mark_request_mcp_tool_executed();
                }

                foreach ($current_tool_calls as $tool_call) {
                    $function_name = $tool_call['function']['name'];
                    $tool_args = $tool_call['function']['arguments'];
                    
                    call_user_func($emit, 'status', [
                        'type'           => 'executing_tool',
                        'tool'           => $function_name,
                        'executing_tool' => $function_name, // 保留相容性
                    ]);

                    $guard_result = mpu_tool_loop_guard_check($loop_state, $this->get_slug(), $current_turn, $function_name, $tool_args);
                    if (is_wp_error($guard_result)) {
                        call_user_func($emit, 'error', ['message' => $guard_result->get_error_message()]);
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

                    $ollama_messages[] = mpu_build_ollama_tool_message($function_name, $tool_result);
                    call_user_func($emit, 'tool_result', ['tool' => $function_name, 'success' => !isset($tool_result['error'])]);
                }
                $current_turn++;
                $full_response_content = "";
                continue;
            }

            return; // 正常結束
        }

        $err = $this->error('max_turns_exceeded', __('Ollama のツール呼び出し回数が多すぎます', 'mp-ukagaka'));
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
     *   endpoint:      string|null,
     * } $args
     * @return string|WP_Error
     */
    public function generate_text(array $args) {
        $endpoint      = $args['endpoint'] ?? 'http://localhost:11434';
        $model         = $args['model'] ?? 'qwen3:8b';
        $system_prompt = $args['system_prompt'] ?? '';
        $user_prompt   = $args['user_prompt'] ?? '';
        $language      = $args['language'] ?? 'zh-TW';
        $max_tokens    = $args['max_tokens'] ?? null;
        $temperature   = $args['temperature'] ?? 0.7;

        $mpu_opt = mpu_get_option();

        // Thinking model keywords
        $thinking_keywords = ['qwen3', 'frieren', 'deepseek', 'r1'];
        $is_thinking_model = (bool) array_filter(
            $thinking_keywords,
            fn($kw) => stripos($model, $kw) !== false
        );

        // Default to enable thinking
        $enable_thinking = $is_thinking_model && !(isset($mpu_opt['ollama_disable_thinking']) && $mpu_opt['ollama_disable_thinking']);

        // Validate and get endpoint
        if (!function_exists('mpu_validate_ollama_endpoint')) {
            $endpoint = rtrim($endpoint, '/');
            $is_remote = !preg_match('/localhost|127\.0\.0\.1|::1/', $endpoint);
            $timeout = $is_remote ? 90 : 30;
        } else {
            $validated_endpoint = mpu_validate_ollama_endpoint($endpoint);
            if (is_wp_error($validated_endpoint)) {
                return $this->error("invalid_endpoint", sprintf(__('Ollama エンドポイントの形式が正しくありません：%s', 'mp-ukagaka'), $validated_endpoint->get_error_message()));
            }
            $endpoint = $validated_endpoint;
            $timeout = mpu_get_ollama_timeout($endpoint, 'api_call');
            $is_remote = mpu_is_remote_endpoint($endpoint);
        }
        
        // Increase timeout for tool calls
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

        // Add user prompt
        $final_user_prompt = $user_prompt;
        if (!$enable_thinking && $is_thinking_model) {
            $final_user_prompt = $user_prompt . ' /no_think';
        }

        $messages[] = [
            'role' => 'user',
            'content' => $final_user_prompt
        ];

        // Get MCP tools
        $tools = [];
        if (function_exists('mpu_get_mcp_tools_for_llm')) {
            $tools = mpu_get_mcp_tools_for_llm('ollama');
        }

        $max_turns = MPU_MAX_TOOL_TURNS;
        $current_turn = 0;
        $loop_state = [];

        while ($current_turn < $max_turns) {
            $request_body = [
                'model' => $model,
                'messages' => $messages,
                'stream' => false,
                'options' => [
                    'temperature' => $temperature,
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
                return $this->handle_ollama_api_error($response, $is_remote, $timeout);
            }

            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);

            if ($response_code !== 200) {
                return $this->handle_api_error($response);
            }

            $data = mpu_json_decode_assoc($response_body);

            if (empty($data["message"])) {
                return $this->error("invalid_response", __('Ollama API レスポンス形式が正しくありません', 'mp-ukagaka'));
            }

            $message = $data["message"];

            // Add assistant response to history
            $messages[] = mpu_normalize_ollama_assistant_message($message);

            // Tool call check
            if (isset($message['tool_calls']) && !empty($message['tool_calls'])) {
                foreach ($message['tool_calls'] as $tool_call) {
                    $function_name = $tool_call['function']['name'];
                    $tool_args = $tool_call['function']['arguments'];
                    if (is_string($tool_args)) {
                        $tool_args = json_decode($tool_args, true);
                    }

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

                    $messages[] = mpu_build_ollama_tool_message($function_name, $tool_result);
                }
                
                $current_turn++;
                continue;
            }

            if (isset($message['content'])) {
				$content = is_string( $message['content'] ) ? $message['content'] : '';

				$thinking = isset( $message['thinking'] ) && is_string( $message['thinking'] ) ? $message['thinking'] : '';
				return $this->compose_thinking_response( $thinking, $content, $enable_thinking );
            }

            return $this->error("empty_content", __('Ollama API レスポンスの内容が空です', 'mp-ukagaka'));
        }

        return $this->error("max_turns_exceeded", __('Ollama API のツール呼び出し回数が多すぎます', 'mp-ukagaka'));
    }

    /**
     * Specialized Ollama error handler for single-turn calls.
     * 
     * @param WP_Error $response
     * @param bool $is_remote
     * @param int $timeout
     * @return WP_Error
     */
    protected function handle_ollama_api_error($response, $is_remote, $timeout) {
        $error_message = $response->get_error_message();

        if (strpos($error_message, 'timeout') !== false || strpos($error_message, 'timed out') !== false) {
            $msg = $is_remote
                ? sprintf(__('Ollama 接続タイムアウト（%s 秒待機済み）。リモート接続は時間がかかる場合があります。Cloudflare Tunnel やネットワーク状態を確認してください。', 'mp-ukagaka'), $timeout)
                : sprintf(__('Ollama 接続タイムアウト（%s 秒待機済み）。Ollama サービスが正常に動作しているか確認してください。', 'mp-ukagaka'), $timeout);
            return $this->error("api_request_failed", $msg);
        }

        if (strpos($error_message, 'Connection refused') !== false || strpos($error_message, 'couldn\'t connect') !== false) {
            $msg = $is_remote
                ? sprintf(__('リモート Ollama サービスに接続できません。Cloudflare Tunnel が動作しているか、エンドポイント URL が正しいか確認してください。エラー：%s', 'mp-ukagaka'), $error_message)
                : sprintf(__('Ollama サービスに接続できません。Ollama が動作しているか確認してください。エラー：%s', 'mp-ukagaka'), $error_message);
            return $this->error("api_request_failed", $msg);
        }

        return $this->error("api_request_failed", sprintf(__('Ollama API リクエストに失敗しました：%s', 'mp-ukagaka'), $error_message));
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
     *   endpoint:      string|null,
     * } $args
     * @return string|WP_Error
     */
    public function generate_chat(array $args) {
        $endpoint      = $args['endpoint'] ?? 'http://localhost:11434';
        $model         = $args['model'] ?? 'qwen3:8b';
        $messages      = $args['messages'] ?? [];
        $system_prompt = $args['system_prompt'] ?? '';
        $language      = $args['language'] ?? 'zh-TW';
        $max_tokens    = $args['max_tokens'] ?? 1000;
        $temperature   = $args['temperature'] ?? 0.8;

        // Thinking model keywords
        $thinking_keywords = ['qwen3', 'frieren', 'deepseek'];
        $is_thinking_model = (bool) array_filter(
            $thinking_keywords,
            fn($kw) => stripos($model, $kw) !== false
        );

        $mpu_opt = mpu_get_option();

        // Default to enable thinking
        $enable_thinking = $is_thinking_model && !(isset($mpu_opt['ollama_disable_thinking']) && $mpu_opt['ollama_disable_thinking']);

        $endpoint = rtrim($endpoint, '/');
        $api_url = $endpoint . '/api/chat';

        $language_instruction = mpu_get_language_instruction($language);
        $full_system_prompt = $system_prompt . "\n\n" . $language_instruction;

        $ollama_messages = [
            ['role' => 'system', 'content' => $full_system_prompt]
        ];

        foreach ($messages as $index => $msg) {
            $content = $msg['content'];

            // Add /no_think instruction if thinking is disabled
            if ($msg['role'] === 'user' && !$enable_thinking && $is_thinking_model && $index === count($messages) - 1) {
                $content = $content . ' /no_think';
            }

            $ollama_messages[] = [
                'role'    => $msg['role'],
                'content' => $content
            ];
        }

        // Get MCP tools
        $tools_config = [];
        if (function_exists('mpu_get_mcp_tools_for_llm')) {
            $mcp_tools = mpu_get_mcp_tools_for_llm('ollama');
            if (!empty($mcp_tools)) {
                $tools_config = $mcp_tools;
            }
        }

        $max_turns = MPU_MAX_TOOL_TURNS;
        $current_turn = 0;
        $loop_state = [];

        while ($current_turn < $max_turns) {
            $request_body = [
                'model'    => $model,
                'messages' => $ollama_messages,
                'stream'   => false,
                'options'  => [
                    'num_predict' => intval($max_tokens),
                    'temperature' => $temperature
                ]
            ];

            if (!empty($tools_config)) {
                $request_body['tools'] = $tools_config;
            }

            // Set thinking parameters
            if ($is_thinking_model) {
                $request_body['think'] = $enable_thinking;
                if ($enable_thinking) {
                    $request_body['options']['num_ctx'] = 8192;
                } else {
                    $request_body['options']['num_ctx'] = 4096;
                }
            }

            $timeout = mpu_get_ollama_timeout($endpoint, 'chat');

            $response = wp_remote_post($api_url,
                mpu_build_http_args(mpu_get_provider_headers('ollama'), $request_body, $timeout));

            if (is_wp_error($response)) {
                $is_remote = mpu_is_remote_endpoint($endpoint);
                return $this->handle_ollama_api_error($response, $is_remote, $timeout);
            }

            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);

            if ($response_code !== 200) {
                return $this->handle_api_error($response);
            }

            $data = mpu_json_decode_assoc($response_body);
            $message = $data['message'];
            $ollama_messages[] = mpu_normalize_ollama_assistant_message($message);

            $content = null;
            $thinking = null;

            // Check for tool calls
            if (isset($message['tool_calls']) && !empty($message['tool_calls'])) {
                if (function_exists('mpu_mark_request_mcp_tool_executed')) {
                    mpu_mark_request_mcp_tool_executed();
                }
                
                foreach ($message['tool_calls'] as $tool_call) {
                    $function_name = $tool_call['function']['name'];
                    $tool_args = $tool_call['function']['arguments'];
                    
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

                    $ollama_messages[] = mpu_build_ollama_tool_message($function_name, $tool_result);
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
                mpu_debug_log('Ollama (Chat Mode) Extracted Content: ' . ($content !== null ? ('"' . mb_substr($content, 0, 100, 'UTF-8') . '"') : '(null)'));
            }

            $final_response = null;

            if ($content !== null) {
                $trimmed_content = trim($content);
                $trimmed_content = mpu_filter_thinking_content($trimmed_content);
                
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

            if ($final_response !== null && $final_response !== '') {
				return $this->compose_thinking_response( $thinking, $final_response, $enable_thinking );
            }
            
            if ($final_response === null && $thinking !== null && $enable_thinking) {
                $trimmed_thinking = trim($thinking);
                if ($trimmed_thinking !== '') {
                    return $trimmed_thinking;
                }
            }
            
            break;
        }

        return $this->error(
            'ollama_empty',
            sprintf(__('Ollama から有効なレスポンスが返されませんでした。モデルのレスポンス形式を確認するか、思考モードの有効化をお試しください。', 'mp-ukagaka'))
        );
    }

	/**
	 * Compose structured Ollama thinking with visible content for the normalizer/parser.
	 *
	 * @param string|null $thinking        Ollama message.thinking.
	 * @param string      $content         Visible assistant content.
	 * @param bool        $enable_thinking Whether Ollama thinking is enabled for this request.
	 * @return string
	 */
	private function compose_thinking_response( $thinking, $content, $enable_thinking ) {
		$content = is_string( $content ) ? $content : '';
		if ( ! $enable_thinking || ! is_string( $thinking ) || '' === trim( $thinking ) ) {
			return mpu_filter_thinking_content( $content );
		}

		return '<think>' . trim( $thinking ) . '</think>' . mpu_filter_thinking_content( $content );
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
        $endpoint = $args['endpoint'] ?? 'http://localhost:11434';
        $model    = $args['model'] ?? 'qwen3:8b';

        $endpoint = sanitize_text_field($endpoint);
        $model    = sanitize_text_field($model);

        if (!function_exists('mpu_validate_ollama_endpoint')) {
            $endpoint = rtrim($endpoint, '/');
            if (!preg_match('/^https?:\/\/.+/', $endpoint)) {
                return $this->error('rest_error', __('エンドポイントの形式が無効です。完全な http:// または https:// URL を入力してください', 'mp-ukagaka'), 400);
            }
            $timeout   = 30;
            $is_remote = !preg_match('/localhost|127\.0\.0\.1|::1/', $endpoint);
            if ($is_remote) {
                $timeout = 45;
            }
        } else {
            $validated_endpoint = mpu_validate_ollama_endpoint($endpoint);
            if (is_wp_error($validated_endpoint)) {
                return $this->error('rest_error', $validated_endpoint->get_error_message(), 400);
            }
            $endpoint  = $validated_endpoint;
            $timeout   = mpu_get_ollama_timeout($endpoint, 'test');
            $is_remote = mpu_is_remote_endpoint($endpoint);
        }

        $api_url      = rtrim($endpoint, '/') . '/api/chat';
        $request_body = [
            'model'    => $model,
            'messages' => [['role' => 'user', 'content' => 'Hi']],
            'stream'   => false,
            'options'  => ['num_predict' => 50, 'temperature' => 0.7],
        ];

        $response = wp_remote_post($api_url, [
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => wp_json_encode($request_body),
            'timeout' => $timeout,
        ]);

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();

            if (strpos($error_message, 'timeout') !== false || strpos($error_message, 'timed out') !== false) {
                $msg = $is_remote
                    ? sprintf(__('接続タイムアウト（%s 秒待機済み）。リモート接続は時間がかかる場合があります。Cloudflare Tunnel やネットワーク状態を確認してください。', 'mp-ukagaka'), $timeout)
                    : sprintf(__('接続タイムアウト（%s 秒待機済み）。Ollama サービスが正常に動作しているか確認してください。', 'mp-ukagaka'), $timeout);
                return $this->error('rest_error', $msg, 400);
            }

            if (strpos($error_message, 'Connection refused') !== false || strpos($error_message, 'couldn\'t connect') !== false) {
                $msg = $is_remote
                    ? sprintf(__('リモート Ollama サービスに接続できません。Cloudflare Tunnel が動作しているか、エンドポイント URL が正しいか確認してください。エラー：%s', 'mp-ukagaka'), $error_message)
                    : sprintf(__('Ollama サービスに接続できません。Ollama が動作しているか確認してください。エラー：%s', 'mp-ukagaka'), $error_message);
                return $this->error('rest_error', $msg, 400);
            }

            $connection_type_text = $is_remote ? __('リモート', 'mp-ukagaka') : __('ローカル', 'mp-ukagaka');
            return $this->error('rest_error', sprintf(__('%s接続に失敗しました：%s', 'mp-ukagaka'), $connection_type_text, $error_message), 400);
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($response_code === 200) {
            $data        = json_decode($response_body, true);
            $content     = null;
            $has_content = false;

            if (!empty($data['message']['content'])) {
                $content     = $data['message']['content'];
                $has_content = true;
            } elseif (!empty($data['content'])) {
                $content     = $data['content'];
                $has_content = true;
            } elseif (isset($data['message']) && is_string($data['message'])) {
                $content     = $data['message'];
                $has_content = true;
            } elseif (!empty($data['response'])) {
                $content     = $data['response'];
                $has_content = true;
            } elseif (!empty($data['message']['thinking'])) {
                $content     = $data['message']['thinking'];
                $has_content = false;
            }

            if (defined('WP_DEBUG') && WP_DEBUG && function_exists('mpu_debug_log')) {
                mpu_debug_log('Ollama Test Response: ' . print_r($data, true));
            }

            if (!empty($content)) {
                if ($has_content) {
                    $cache_key = 'mpu_ollama_available_' . md5($endpoint . $model);
                    delete_transient($cache_key);
                    set_transient($cache_key, 1, 5 * MINUTE_IN_SECONDS);

                    $preview              = mb_substr($content, 0, 50);
                    $connection_type_text = $is_remote ? __('リモート', 'mp-ukagaka') : __('ローカル', 'mp-ukagaka');
                    return new WP_REST_Response(['msg' => sprintf(
                        __('接続成功（%s接続）。モデルが正常に応答しています（プレビュー：%s...）', 'mp-ukagaka'),
                        $connection_type_text,
                        $preview
                    )], 200);
                } else {
                    $preview = mb_substr($content, 0, 50);
                    return new WP_REST_Response(['msg' => sprintf(
                        __('接続成功。ただしモデルは思考過程のみを返しました（プレビュー：%s...）。実際の使用時にはコンテンツが生成されるはずです。', 'mp-ukagaka'),
                        $preview
                    )], 200);
                }
            } else {
                $response_keys = is_array($data) ? array_keys($data) : [];
                $debug_info    = 'レスポンスキー: [' . implode(', ', $response_keys) . ']';
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    $debug_info .= ' | 完全なレスポンス: ' . mb_substr($response_body, 0, 200);
                }
                return $this->error('rest_error', sprintf(__('接続は成功しましたが、レスポンス形式が異常でモデル出力を解析できません。%s', 'mp-ukagaka'), $debug_info), 400, $response_body);
            }
        } else {
            $error_data    = json_decode($response_body, true);
            $error_message = isset($error_data['error'])
                ? $error_data['error']
                : sprintf(__('HTTP %s：Ollama が動作しているかモデルがダウンロード済みか確認してください', 'mp-ukagaka'), $response_code);
            return $this->error('rest_error', $error_message, 400, $response_body);
        }
    }
}
