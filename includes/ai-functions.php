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
 * @return {string|WP_Error} 生成的文本或錯誤
 */
function mpu_call_ai_api($provider, $api_key, $system_prompt, $user_prompt, $language, $mpu_opt = null)
{
    // 根據提供商調用對應的 API
    switch ($provider) {
        case "gemini":
            $model = $mpu_opt["gemini_model"] ?? "gemini-2.5-flash";
            return mpu_call_gemini_api($api_key, $model, $system_prompt, $user_prompt, $language);
        case "openai":
            $model = $mpu_opt["openai_model"] ?? "gpt-4o-mini";
            return mpu_call_openai_api($api_key, $model, $system_prompt, $user_prompt, $language);
        case "claude":
            $model = $mpu_opt["claude_model"] ?? "claude-sonnet-4-5-20250929";
            return mpu_call_claude_api($api_key, $model, $system_prompt, $user_prompt, $language);
        case "ollama":
            $endpoint = $mpu_opt["ollama_endpoint"] ?? "http://localhost:11434";
            $model = $mpu_opt["ollama_model"] ?? "qwen3:8b";
            return mpu_call_ollama_api($endpoint, $model, $system_prompt, $user_prompt, $language);
        default:
            return new WP_Error("unsupported_provider", sprintf(__('不支援的 AI 提供商：%s', 'mp-ukagaka'), $provider));
    }
}

/**
 * 調用 Gemini API（支援用戶選擇模型）
 * @param {string} $api_key - API 金鑰
 * @param {string} $model - 模型名稱（如 gemini-2.5-flash, gemini-2.5-pro）
 * @param {string} $system_prompt - 系統提示詞
 * @param {string} $user_prompt - 用戶提示詞
 * @param {string} $language - 語言代碼
 * @return {string|WP_Error} 生成的文本或錯誤
 */
function mpu_call_gemini_api($api_key, $model, $system_prompt, $user_prompt, $language)
{
    // 構建語言指令
    $language_instruction = mpu_get_language_instruction($language);

    // 組合完整的提示詞
    $full_prompt = $system_prompt . "\n\n" . $language_instruction . "\n\n" . $user_prompt;

    // 構建請求體
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
            "maxOutputTokens" => 500,
        ]
    ];

    // 構建 API URL（使用用戶選擇的模型）
    $api_url = "https://generativelanguage.googleapis.com/v1/models/{$model}:generateContent?key=" . urlencode($api_key);

    // 發送請求
    $response = wp_remote_post($api_url, [
        "headers" => [
            "Content-Type" => "application/json",
        ],
        "body" => wp_json_encode($request_body),
        "timeout" => 60, // Gemini 可能需要較長時間
    ]);

    // 處理錯誤
    if (is_wp_error($response)) {
        return new WP_Error("api_request_failed", sprintf(__('Gemini API 請求失敗：%s', 'mp-ukagaka'), $response->get_error_message()));
    }

    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);

    if ($response_code === 200) {
        // 解析回應
        $data = json_decode($response_body, true);

        if (!empty($data["candidates"][0]["content"]["parts"][0]["text"])) {
            $generated_text = trim($data["candidates"][0]["content"]["parts"][0]["text"]);
            return $generated_text;
        } else {
            return new WP_Error("empty_response", __('Gemini API 回應為空，請檢查模型是否正確', 'mp-ukagaka'));
        }
    } else {
        // 解析錯誤訊息
        $error_data = json_decode($response_body, true);
        $error_message = isset($error_data["error"]["message"])
            ? $error_data["error"]["message"]
            : __('未知錯誤', 'mp-ukagaka');

        // 如果是認證錯誤（401/403）
        if ($response_code === 401 || $response_code === 403) {
            return new WP_Error("api_auth_error", sprintf(__('API 認證失敗（HTTP %s）：%s。請檢查 API Key 是否正確。', 'mp-ukagaka'), $response_code, $error_message));
        }

        // 如果是 404（模型不存在）
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
function mpu_call_openai_api($api_key, $model, $system_prompt, $user_prompt, $language)
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
        "max_tokens" => 100,
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
function mpu_call_claude_api($api_key, $model, $system_prompt, $user_prompt, $language)
{
    $language_instruction = mpu_get_language_instruction($language);

    // Claude API 端點
    $api_url = "https://api.anthropic.com/v1/messages";

    // 組合完整的系統提示詞
    $full_system_prompt = $system_prompt . "\n\n" . $language_instruction;

    // 構建請求體
    $request_body = [
        "model" => $model,
        "max_tokens" => 100,
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
function mpu_call_ollama_api($endpoint, $model, $system_prompt, $user_prompt, $language)
{
    $mpu_opt = mpu_get_option();

    // 檢查是否為 Qwen3 模型且需要關閉思考模式
    $is_qwen3 = (strpos(strtolower($model), 'qwen3') !== false) || (strpos(strtolower($model), 'frieren') !== false);
    // 如果設定了關閉思考模式，或者未設定但使用 Qwen3（預設關閉）
    $disable_thinking = $is_qwen3 && (isset($mpu_opt['ollama_disable_thinking']) ? $mpu_opt['ollama_disable_thinking'] : true);

    // 驗證端點 URL
    // 檢查輔助函數是否存在（確保 llm-functions.php 已載入）
    if (!function_exists('mpu_validate_ollama_endpoint')) {
        // 如果函數不存在，使用基本驗證
        $endpoint = rtrim($endpoint, '/');
        if (!preg_match('/^https?:\/\/.+/', $endpoint)) {
            return new WP_Error("invalid_endpoint", __('Ollama 端點必須是有效的 HTTP 或 HTTPS URL', 'mp-ukagaka'));
        }
        $timeout = 60; // 默認超時
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

        // 根據端點類型使用動態超時時間
        $timeout = mpu_get_ollama_timeout($endpoint, 'api_call');
        $is_remote = mpu_is_remote_endpoint($endpoint);
    }

    // 構建 API URL
    $api_url = rtrim($endpoint, '/') . '/api/chat';

    // 構建請求體
    $messages = [];

    // 添加系統提示詞
    if (!empty($system_prompt)) {
        $language_instruction = mpu_get_language_instruction($language);
        $full_system_prompt = $system_prompt . "\n\n" . $language_instruction;
        $messages[] = [
            'role' => 'system',
            'content' => $full_system_prompt
        ];
    }

    // 添加用戶提示詞
    // 如果是 Qwen3 且需要關閉思考模式，在提示詞末尾添加 /no_think
    $final_user_prompt = $user_prompt;
    if ($disable_thinking && $is_qwen3) {
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
            'num_predict' => 200
        ]
    ];

    // 如果是 Qwen3 且需要關閉思考模式，添加 think 參數
    if ($disable_thinking && $is_qwen3) {
        $request_body['think'] = false;
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
            error_log('Ollama API JSON 解析失敗: ' . $error_msg);
            error_log('Ollama API 原始響應: ' . substr($response_body, 0, 500));
        }
        return new WP_Error("json_decode_error", sprintf(__('Ollama API 回應 JSON 解析失敗: %s', 'mp-ukagaka'), $error_msg));
    }

    // 驗證響應數據是否為數組
    if (!is_array($data)) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Ollama API 響應格式錯誤: 期望數組，得到 ' . gettype($data));
        }
        return new WP_Error("invalid_response_type", __('Ollama API 回應格式錯誤：期望數組格式', 'mp-ukagaka'));
    }

    // 調試：記錄響應結構（僅在 WP_DEBUG 模式下）
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Ollama API Response: ' . print_r($data, true));
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
        error_log('Ollama Extracted Content: ' . ($content !== null ? ('"' . substr($content, 0, 100) . '"') : '(null)'));
        error_log('Ollama Extracted Thinking: ' . ($thinking !== null ? ('"' . substr($thinking, 0, 100) . '"') : '(null)'));
    }

    // 優先使用 content，只有在 content 完全不存在時才使用 thinking
    $final_response = null;

    if ($content !== null) {
        $trimmed_content = trim($content);
        if ($trimmed_content !== '') {
            // Content 存在且不為空，使用它
            $final_response = $trimmed_content;
        } else {
            // Content 存在但為空字符串，記錄警告
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Ollama Warning: Content exists but is empty string');
            }
        }
    }

    // 只有在 content 完全不存在或為空時，才考慮使用 thinking
    if ($final_response === null && $thinking !== null) {
        $trimmed_thinking = trim($thinking);
        if ($trimmed_thinking !== '') {
            // Content 不存在或為空，但 thinking 存在，作為後備使用
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Ollama Warning: Using thinking as fallback because content is empty or missing');
            }
            $final_response = $trimmed_thinking;
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
            error_log('Ollama API 響應解析失敗: ' . $debug_info);
        }

        return new WP_Error(
            "invalid_response",
            sprintf(__('Ollama API 回應格式錯誤，無法提取有效內容。%s。請檢查模型響應格式。', 'mp-ukagaka'), $debug_info)
        );
    }

    return $final_response;
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
