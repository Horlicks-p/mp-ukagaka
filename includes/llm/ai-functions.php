<?php

/**
 * AI 功能：API 調用
 * 
 * @package MP_Ukagaka
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
    // ===== API 快取檢查 =====
    $cache_key = null;
    if (function_exists('mpu_is_api_cache_enabled') && mpu_is_api_cache_enabled()) {
        $cache_key = mpu_generate_cache_key($provider, $system_prompt, $user_prompt);
        $cached_response = mpu_get_cached_api_response($cache_key);
        if ($cached_response !== false) {
            return $cached_response;
        }
    }

    // 記錄發送給 AI 的提示詞
    // 檢查 WP_DEBUG 或 WP_DEBUG_LOG，如果都未啟用則強制記錄（用於調試）
    $wp_debug_enabled = defined('WP_DEBUG') && WP_DEBUG;
    $wp_debug_log_enabled = defined('WP_DEBUG_LOG') && WP_DEBUG_LOG;

    // 如果 WP_DEBUG 或 WP_DEBUG_LOG 啟用，則記錄調用資訊
    if ($wp_debug_enabled || $wp_debug_log_enabled) {
        if (function_exists('mpu_debug_log')) {
            mpu_debug_log('=== MP Ukagaka - AI API 調用 ===');
            mpu_debug_log('提供商: ' . $provider);
            mpu_debug_log('語言: ' . $language);
            mpu_debug_log('--- System Prompt ---');
            mpu_debug_log($system_prompt);
            mpu_debug_log('--- User Prompt ---');
            mpu_debug_log($user_prompt);
            mpu_debug_log('=== End AI API 調用 ===');
        } else {
            // 後備方案：使用標準 error_log
            error_log('=== MP Ukagaka - AI API 調用 ===');
            error_log('提供商: ' . $provider);
            error_log('語言: ' . $language);
            error_log('--- System Prompt ---');
            error_log($system_prompt);
            error_log('--- User Prompt ---');
            error_log($user_prompt);
            error_log('=== End AI API 調用 ===');
        }
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
    $full_prompt = $system_prompt . "\n\n" . $language_instruction . "\n\n" . $user_prompt;

    $request_body = [
        "contents" => [
            [
                "parts" => [
                    [
                        "text" => $full_prompt
                    ]
                ]
            ]
        ],
        "generationConfig" => [
            "temperature" => 0.7,
            "topK" => 40,
            "topP" => 0.95,
            "maxOutputTokens" => $max_tokens !== null ? intval($max_tokens) : 500,
        ]
    ];

    $api_url = "https://generativelanguage.googleapis.com/v1/models/{$model}:generateContent?key=" . urlencode($api_key);

    $response = wp_remote_post($api_url, [
        "headers" => [
            "Content-Type" => "application/json",
        ],
        "body" => wp_json_encode($request_body),
        "timeout" => 60,
    ]);

    if (is_wp_error($response)) {
        return new WP_Error("api_request_failed", sprintf(__('Gemini API 請求失敗：%s', 'mp-ukagaka'), $response->get_error_message()));
    }

    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);

    if ($response_code === 200) {
        $data = json_decode($response_body, true);

        if (!empty($data["candidates"][0]["content"]["parts"][0]["text"])) {
            $generated_text = trim($data["candidates"][0]["content"]["parts"][0]["text"]);
            return $generated_text;
        } else {
            return new WP_Error("empty_response", __('Gemini API 回應為空，請檢查模型是否正確', 'mp-ukagaka'));
        }
    } else {
        $error_data = json_decode($response_body, true);
        $error_message = isset($error_data["error"]["message"])
            ? $error_data["error"]["message"]
            : __('未知錯誤', 'mp-ukagaka');

        if ($response_code === 401 || $response_code === 403) {
            return new WP_Error("api_auth_error", sprintf(__('API 認證失敗（HTTP %s）：%s。請檢查 API Key 是否正確。', 'mp-ukagaka'), $response_code, $error_message));
        }

        if ($response_code === 404) {
            return new WP_Error("model_not_found", sprintf(__('Gemini 模型「%s」不存在。請在設定中選擇正確的模型。', 'mp-ukagaka'), $model));
        }

        return new WP_Error("api_error", sprintf(__('Gemini API 錯誤（HTTP %s）：%s', 'mp-ukagaka'), $response_code, $error_message));
    }
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

    // 構建請求體
    $request_body = [
        "model" => $model,
        "messages" => [
            [
                "role" => "system",
                "content" => $system_prompt . "\n\n" . $language_instruction
            ],
            [
                "role" => "user",
                "content" => $user_prompt
            ]
        ],
        "temperature" => 0.7,
        "max_tokens" => $max_tokens !== null ? intval($max_tokens) : 100,
    ];

    // 發送請求
    $response = wp_remote_post($api_url, [
        "headers" => [
            "Content-Type" => "application/json",
            "Authorization" => "Bearer " . $api_key,
        ],
        "body" => wp_json_encode($request_body),
        "timeout" => 30,
    ]);

    // 處理錯誤
    if (is_wp_error($response)) {
        return new WP_Error("api_request_failed", sprintf(__('OpenAI API 請求失敗：%s', 'mp-ukagaka'), $response->get_error_message()));
    }

    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);

    if ($response_code !== 200) {
        $error_data = json_decode($response_body, true);
        $error_message = isset($error_data["error"]["message"])
            ? $error_data["error"]["message"]
            : sprintf(__('API 請求失敗 (HTTP %s)', 'mp-ukagaka'), $response_code);
        return new WP_Error("api_error", sprintf(__('OpenAI API 錯誤：%s', 'mp-ukagaka'), $error_message));
    }

    // 解析回應
    $data = json_decode($response_body, true);

    if (empty($data["choices"][0]["message"]["content"])) {
        return new WP_Error("invalid_response", __('OpenAI API 回應格式錯誤', 'mp-ukagaka'));
    }

    $generated_text = trim($data["choices"][0]["message"]["content"]);

    return $generated_text;
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

    // 構建請求體
    $request_body = [
        "model" => $model,
        "max_tokens" => $max_tokens !== null ? intval($max_tokens) : 100,
        "system" => $full_system_prompt,
        "messages" => [
            [
                "role" => "user",
                "content" => $user_prompt
            ]
        ],
    ];

    // 發送請求
    $response = wp_remote_post($api_url, [
        "headers" => [
            "Content-Type" => "application/json",
            "x-api-key" => $api_key,
            "anthropic-version" => "2023-06-01",
        ],
        "body" => wp_json_encode($request_body),
        "timeout" => 30,
    ]);

    // 處理錯誤
    if (is_wp_error($response)) {
        return new WP_Error("api_request_failed", sprintf(__('Claude API 請求失敗：%s', 'mp-ukagaka'), $response->get_error_message()));
    }

    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);

    if ($response_code !== 200) {
        $error_data = json_decode($response_body, true);
        $error_message = isset($error_data["error"]["message"])
            ? $error_data["error"]["message"]
            : sprintf(__('API 請求失敗 (HTTP %s)', 'mp-ukagaka'), $response_code);
        return new WP_Error("api_error", sprintf(__('Claude API 錯誤：%s', 'mp-ukagaka'), $error_message));
    }

    // 解析回應
    $data = json_decode($response_body, true);

    if (empty($data["content"][0]["text"])) {
        return new WP_Error("invalid_response", __('Claude API 回應格式錯誤', 'mp-ukagaka'));
    }

    $generated_text = trim($data["content"][0]["text"]);

    return $generated_text;
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

    // 支援思考模式的模型 (Qwen3, DeepSeek, Frieren 等)
    $is_thinking_model = (strpos(strtolower($model), 'qwen3') !== false)
        || (strpos(strtolower($model), 'frieren') !== false)
        || (strpos(strtolower($model), 'deepseek') !== false);

    // 預設啟用思考模式（讓 AI 先思考再回答，提高回答品質）
    // 設定 ollama_disable_thinking = true 可關閉
    $enable_thinking = $is_thinking_model && !(isset($mpu_opt['ollama_disable_thinking']) && $mpu_opt['ollama_disable_thinking']);

    if (!function_exists('mpu_validate_ollama_endpoint')) {
        $endpoint = rtrim($endpoint, '/');
        if (!preg_match('/^https?:\/\/.+/', $endpoint)) {
            return new WP_Error("invalid_endpoint", __('Ollama 端點必須是有效的 HTTP 或 HTTPS URL', 'mp-ukagaka'));
        }
        $timeout = 30;
        $is_remote = !preg_match('/localhost|127\.0\.0\.1|::1/', $endpoint);
        if ($is_remote) {
            $timeout = 90;
        }
    } else {
        $validated_endpoint = mpu_validate_ollama_endpoint($endpoint);
        if (is_wp_error($validated_endpoint)) {
            return new WP_Error("invalid_endpoint", sprintf(__('Ollama 端點格式錯誤：%s', 'mp-ukagaka'), $validated_endpoint->get_error_message()));
        }
        $endpoint = $validated_endpoint;

        $timeout = mpu_get_ollama_timeout($endpoint, 'api_call');
        $is_remote = mpu_is_remote_endpoint($endpoint);
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
    // 如果關閉思考模式，在提示詞末尾添加 /no_think
    $final_user_prompt = $user_prompt;
    if (!$enable_thinking && $is_thinking_model) {
        $final_user_prompt = $user_prompt . ' /no_think';
    }

    $messages[] = [
        'role' => 'user',
        'content' => $final_user_prompt
    ];

    $request_body = [
        'model' => $model,
        'messages' => $messages,
        'stream' => false,
        'options' => [
            'temperature' => 0.7,
            'num_predict' => $max_tokens !== null ? intval($max_tokens) : 100
        ]
    ];

    // 設定思考參數
    if ($is_thinking_model) {
        $request_body['think'] = $enable_thinking;
    }

    // 發送請求（使用動態超時：本地 60 秒，遠程 90 秒）
    $response = wp_remote_post($api_url, [
        'headers' => [
            'Content-Type' => 'application/json',
        ],
        'body' => wp_json_encode($request_body),
        'timeout' => $timeout,  // 動態超時：本地 60 秒，遠程 90 秒（考慮 Cloudflare Tunnel 延遲）
    ]);

    // 處理錯誤
    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();

        // 根據連接類型提供不同的錯誤訊息
        if (strpos($error_message, 'Connection refused') !== false || strpos($error_message, 'couldn\'t connect') !== false) {
            if ($is_remote) {
                return new WP_Error(
                    "ollama_connection_failed",
                    sprintf(
                        __('無法連接到遠程 Ollama 服務。請確認：%1$s1. Cloudflare Tunnel 或遠程服務是否正在運行%1$s2. 端點 URL 是否正確（例如：https://your-domain.com）%1$s3. 網絡連接是否正常%1$s錯誤詳情：%2$s', 'mp-ukagaka'),
                        "\n",
                        $error_message
                    )
                );
            } else {
                return new WP_Error(
                    "ollama_connection_failed",
                    sprintf(__('無法連接到 Ollama 服務。請確認 Ollama 是否正在運行。%1$s錯誤詳情：%2$s', 'mp-ukagaka'), "\n", $error_message)
                );
            }
        }

        // 超時錯誤
        if (strpos($error_message, 'timeout') !== false || strpos($error_message, 'timed out') !== false) {
            if ($is_remote) {
                return new WP_Error(
                    "ollama_timeout",
                    sprintf(
                        __('連接 Ollama 服務超時（已等待 %1$s 秒）。%2$s遠程連接可能需要更長時間，請檢查網絡狀況或 Cloudflare Tunnel 狀態。%2$s錯誤詳情：%3$s', 'mp-ukagaka'),
                        $timeout,
                        "\n",
                        $error_message
                    )
                );
            } else {
                return new WP_Error(
                    "ollama_timeout",
                    sprintf(
                        __('連接 Ollama 服務超時（已等待 %1$s 秒）。%2$s請確認 Ollama 服務是否正常運行。%2$s錯誤詳情：%3$s', 'mp-ukagaka'),
                        $timeout,
                        "\n",
                        $error_message
                    )
                );
            }
        }

        return new WP_Error("api_request_failed", sprintf(__('Ollama API 請求失敗：%s', 'mp-ukagaka'), $error_message));
    }

    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);

    if ($response_code !== 200) {
        $error_data = json_decode($response_body, true);
        $error_message = isset($error_data["error"])
            ? $error_data["error"]
            : sprintf(__('API 請求失敗 (HTTP %s)', 'mp-ukagaka'), $response_code);

        // 提供更友好的錯誤提示
        if ($response_code === 404) {
            return new WP_Error("ollama_model_not_found", sprintf(__('Ollama 模型「%s」未找到。請確認模型名稱是否正確，或使用 <code>ollama list</code> 查看已下載的模型。', 'mp-ukagaka'), $model));
        }

        return new WP_Error("api_error", sprintf(__('Ollama API 錯誤：%s', 'mp-ukagaka'), $error_message));
    }

    // 解析回應
    $data = json_decode($response_body, true);

    // 驗證 JSON 解析是否成功
    if (json_last_error() !== JSON_ERROR_NONE) {
        $error_msg = json_last_error_msg();
        if (defined('WP_DEBUG') && WP_DEBUG) {
            if (function_exists('mpu_debug_log')) {
                mpu_debug_log('Ollama API JSON 解析失敗: ' . $error_msg);
                mpu_debug_log('Ollama API 原始響應: ' . mb_substr($response_body, 0, 500, 'UTF-8'));
            } else {
                error_log('Ollama API JSON 解析失敗: ' . $error_msg);
                error_log('Ollama API 原始響應: ' . mb_substr($response_body, 0, 500, 'UTF-8'));
            }
        }
        return new WP_Error("json_decode_error", sprintf(__('Ollama API 回應 JSON 解析失敗: %s', 'mp-ukagaka'), $error_msg));
    }

    // 驗證響應數據是否為數組
    if (!is_array($data)) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            if (function_exists('mpu_debug_log')) {
                mpu_debug_log('Ollama API 響應格式錯誤: 期望數組，得到 ' . gettype($data));
            } else {
                error_log('Ollama API 響應格式錯誤: 期望數組，得到 ' . gettype($data));
            }
        }
        return new WP_Error("invalid_response_type", __('Ollama API 回應格式錯誤：期望數組格式', 'mp-ukagaka'));
    }

    // 調試：記錄響應結構（僅在 WP_DEBUG 模式下）
    if (defined('WP_DEBUG') && WP_DEBUG) {
        if (function_exists('mpu_debug_log')) {
            mpu_debug_log('Ollama API Response: ' . print_r($data, true));
        } else {
            error_log('Ollama API Response: ' . print_r($data, true));
        }
    }

    // 改進的響應解析邏輯
    // Thinking models（如 Qwen, DeepSeek）會同時返回兩個字段：
    // - thinking: 模型的思考過程（內部推理）
    // - content: 實際的回應內容（這才是我們想要的）
    //
    // 支援的響應格式：
    // 1. {"message": {"role": "assistant", "content": "...", "thinking": "..."}}
    // 2. {"message": {"content": "..."}}
    // 3. {"content": "..."}
    // 4. {"response": "..."}
    // 5. {"message": "..."} (字串格式)

    $content = null;
    $thinking = null;

    // 優先檢查標準格式：data["message"]["content"]
    if (isset($data["message"]) && is_array($data["message"])) {
        $message = $data["message"];

        // 提取 content（實際回應）
        if (isset($message["content"])) {
            $content = is_string($message["content"]) ? $message["content"] : null;
        }

        // 提取 thinking（思考過程，僅用於調試或後備）
        if (isset($message["thinking"])) {
            $thinking = is_string($message["thinking"]) ? $message["thinking"] : null;
        }
    }

    // 如果標準格式沒有 content，嘗試其他格式
    if ($content === null) {
        // 格式 2: {"content": "..."}
        if (isset($data["content"]) && is_string($data["content"])) {
            $content = $data["content"];
        }
        // 格式 3: {"response": "..."}
        elseif (isset($data["response"]) && is_string($data["response"])) {
            $content = $data["response"];
        }
        // 格式 4: {"message": "..."} (字符串格式)
        elseif (isset($data["message"]) && is_string($data["message"])) {
            $content = $data["message"];
        }
    }

    // 調試輸出（僅在 WP_DEBUG 模式下）
    if (defined('WP_DEBUG') && WP_DEBUG) {
        if (function_exists('mpu_debug_log')) {
            mpu_debug_log('Ollama Extracted Content: ' . ($content !== null ? ('"' . mb_substr($content, 0, 100, 'UTF-8') . '"') : '(null)'));
            mpu_debug_log('Ollama Extracted Thinking: ' . ($thinking !== null ? ('"' . mb_substr($thinking, 0, 100, 'UTF-8') . '"') : '(null)'));
        } else {
            error_log('Ollama Extracted Content: ' . ($content !== null ? ('"' . mb_substr($content, 0, 100, 'UTF-8') . '"') : '(null)'));
            error_log('Ollama Extracted Thinking: ' . ($thinking !== null ? ('"' . mb_substr($thinking, 0, 100, 'UTF-8') . '"') : '(null)'));
        }
    }

    // 優先使用 content，只有在 content 完全不存在時才使用 thinking
    $final_response = null;

    if ($content !== null) {
        $trimmed_content = trim($content);

        // 先使用通用函數過濾思考標籤
        $trimmed_content = mpu_filter_thinking_content($trimmed_content);

        // 當思考模式關閉時，檢測可能洩漏的思考內容
        // 這些模式表示模型在「思考」而非「對話」
        $is_thinking_content = false;

        if (!$enable_thinking && !empty($trimmed_content)) {
            $thinking_patterns = [
                // 英文思考模式
                '/^Okay,?\s+(the\s+)?user/i',
                '/^The\s+user\s+(is\s+asking|mentioned|wants)/i',
                '/^Let\s+me\s+(recall|think|check|consider|remember)/i',
                '/^I\s+(need|should)\s+to\s+(respond|check|recall|remember)/i',
                '/^First,?\s+I\s+(need|should)/i',
                '/^(Based|According)\s+(on|to)\s+(the\s+)?(previous|system|user)/i',
                '/^The\s+(system|previous)\s+(info|prompt|message|conversation)/i',
                '/^I\s+recall\s+that/i',
                '/^I\s+remember\s+that/i',
                '/^(Looking|Checking)\s+(at|the)/i',
                '/^(So|Now),?\s+(I|the|let)/i',
                // 日文思考模式
                '/^(ユーザー|ユーザ)が/i',
                '/^まず[、,]?(私|僕)は/i',
                '/^(確認|チェック)し(ます|よう)/i',
                '/^(では|さて|それでは)[、,]/i',
                '/^(システム|前の)(情報|メッセージ)/i',
            ];

            foreach ($thinking_patterns as $pattern) {
                if (preg_match($pattern, $trimmed_content)) {
                    $is_thinking_content = true;
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        if (function_exists('mpu_debug_log')) {
                            mpu_debug_log('Ollama: Thinking content detected and filtered (pattern matched)');
                        } else {
                            error_log('Ollama: Thinking content detected and filtered (pattern matched)');
                        }
                    }
                    break;
                }
            }
        }

        // 如果不是思考內容，才使用它
        if (!$is_thinking_content && $trimmed_content !== '') {
            $final_response = $trimmed_content;
        } else if ($is_thinking_content) {
            // 思考內容被檢測到，記錄警告
            if (defined('WP_DEBUG') && WP_DEBUG) {
                if (function_exists('mpu_debug_log')) {
                    mpu_debug_log('Ollama Warning: Content appears to be thinking/reasoning, not dialogue');
                } else {
                    error_log('Ollama Warning: Content appears to be thinking/reasoning, not dialogue');
                }
            }
        }
    }

    // 只有在 content 完全不存在或為空時，才考慮使用 thinking
    // 當思考模式開啟時，如果 content 為空但 thinking 存在，使用 thinking 作為後備
    if ($final_response === null && $thinking !== null && $enable_thinking) {
        $trimmed_thinking = trim($thinking);
        if ($trimmed_thinking !== '') {
            // Content 不存在或為空，但 thinking 存在，作為後備使用
            if (defined('WP_DEBUG') && WP_DEBUG) {
                if (function_exists('mpu_debug_log')) {
                    mpu_debug_log('Ollama Warning: Using thinking as fallback because content is empty or missing');
                } else {
                    error_log('Ollama Warning: Using thinking as fallback because content is empty or missing');
                }
            }
            $final_response = mpu_filter_thinking_content($trimmed_thinking);
        }
    }

    // 如果仍然沒有有效回應，返回詳細錯誤
    if ($final_response === null || $final_response === '') {
        $response_keys = array_keys($data);
        $debug_info = '響應鍵: [' . implode(', ', $response_keys) . ']';

        if (isset($data["message"]) && is_array($data["message"])) {
            $message_keys = array_keys($data["message"]);
            $debug_info .= ', message 鍵: [' . implode(', ', $message_keys) . ']';
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            if (function_exists('mpu_debug_log')) {
                mpu_debug_log('Ollama API 響應解析失敗: ' . $debug_info);
            } else {
                error_log('Ollama API 響應解析失敗: ' . $debug_info);
            }
        }

        return new WP_Error(
            "invalid_response",
            sprintf(__('Ollama API 回應格式錯誤，無法提取有效內容。%s。請檢查模型響應格式。', 'mp-ukagaka'), $debug_info)
        );
    }

    return $final_response;
}

/**
 * 過濾 AI 回應中的思考內容標籤（<think>、<thinking>、<reflection>）
 * @param string $response AI 原始回應
 * @return string 過濾後的回應
 */
function mpu_filter_thinking_content($response)
{
    if (empty($response) || !is_string($response)) {
        return $response;
    }

    $original_response = $response;

    // 移除完整標籤
    $response = preg_replace('/<think>.*?<\/think>/is', '', $response);
    $response = preg_replace('/<thinking>.*?<\/thinking>/is', '', $response);
    $response = preg_replace('/<reflection>.*?<\/reflection>/is', '', $response);

    // 移除不完整標籤
    $response = preg_replace('/<think>.*$/is', '', $response);
    $response = preg_replace('/<thinking>.*$/is', '', $response);
    $response = preg_replace('/<reflection>.*$/is', '', $response);

    // 清理空白
    $response = preg_replace('/\n\s*\n\s*\n/s', "\n\n", $response);
    $response = trim($response);

    if (defined('WP_DEBUG') && WP_DEBUG && $response !== $original_response) {
        error_log('mpu_filter_thinking_content: filtered');
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
 * 
 * @return array 允許的條件標籤列表
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
                error_log("MP Ukagaka 安全警告：嘗試使用未授權的條件標籤: {$condition}");
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
                    error_log("MP Ukagaka 錯誤：條件標籤 {$condition} 調用失敗: " . $e->getMessage());
                }
            }
        }
    }

    return false;
}

// 注意：mpu_generate_llm_dialogue() 已移至 llm-functions.php
