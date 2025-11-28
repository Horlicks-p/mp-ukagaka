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
 * 輔助函數：調用 AI API (支持多提供商：Gemini, OpenAI, Claude)
 */
function mpu_call_ai_api($provider, $api_key, $system_prompt, $user_prompt, $language, $mpu_opt = null) {
    // 根據提供商調用對應的 API
    switch ($provider) {
        case "gemini":
            return mpu_call_gemini_api($api_key, $system_prompt, $user_prompt, $language);
        case "openai":
            $model = $mpu_opt["openai_model"] ?? "gpt-4o-mini";
            return mpu_call_openai_api($api_key, $model, $system_prompt, $user_prompt, $language);
        case "claude":
            $model = $mpu_opt["claude_model"] ?? "claude-sonnet-4-5-20250929";
            return mpu_call_claude_api($api_key, $model, $system_prompt, $user_prompt, $language);
        default:
            return new WP_Error("unsupported_provider", "不支援的 AI 提供商：{$provider}");
    }
}

/**
 * 調用 Gemini API (已更新為 2025 年 Gemini 2.5 標準)
 */
function mpu_call_gemini_api($api_key, $system_prompt, $user_prompt, $language) {
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
            "maxOutputTokens" => 100,
        ]
    ];
    
    // ★★★ 重大更新：切換至 Gemini 2.5 系列 (2025年主流模型) ★★★
    // 根據 Google 官方文檔，這些是目前的穩定版模型 ID
    $api_configs = [
        // 1. 首選：Gemini 2.5 Flash (速度快、成本低、最新穩定版)
        ["version" => "v1", "model" => "gemini-2.5-flash"],

        // 2. 次選：Gemini 2.5 Pro (更聰明，適合複雜推理)
        ["version" => "v1", "model" => "gemini-2.5-pro"],

        // 3. 備用：Gemini 2.5 Flash-Lite (超輕量版，如果 Flash 失敗時嘗試)
        ["version" => "v1", "model" => "gemini-2.5-flash-lite"],

        // 4. 相容性備援：Gemini 2.0 系列 (上一代穩定版)
        ["version" => "v1", "model" => "gemini-2.0-flash-001"],
        
        // 5. 最後手段：Gemini 1.5 (如果你的專案尚未遷移)
        ["version" => "v1beta", "model" => "gemini-1.5-flash"],
    ];
    
    $errors = []; // 收集所有錯誤以便調試
    
    foreach ($api_configs as $config) {
        $version = $config["version"];
        $model = $config["model"];
        
        // 構建 API URL (注意：新版通常使用 v1)
        $api_url = "https://generativelanguage.googleapis.com/{$version}/models/{$model}:generateContent?key=" . urlencode($api_key);
        
        // 發送請求
        $response = wp_remote_post($api_url, [
            "headers" => [
                "Content-Type" => "application/json",
            ],
            "body" => wp_json_encode($request_body),
            "timeout" => 30,
        ]);
        
        // 處理錯誤
        if (is_wp_error($response)) {
            $error_msg = "API 請求失敗（{$version}/{$model}）：" . $response->get_error_message();
            $errors[] = $error_msg;
            continue; // 嘗試下一個模型
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
                $errors[] = "API 回應格式空（{$version}/{$model}）";
                continue;
            }
        } else {
            // 解析錯誤訊息
            $error_data = json_decode($response_body, true);
            $error_message = isset($error_data["error"]["message"]) 
                ? $error_data["error"]["message"] 
                : "未知錯誤";
            
            $error_msg = "API 錯誤 {$response_code}（{$model}）：{$error_message}";
            $errors[] = $error_msg;
            
            // 如果是認證錯誤（401/403），立即返回，不需要嘗試其他模型
            if ($response_code === 401 || $response_code === 403) {
                return new WP_Error("api_auth_error", "API 認證失敗（HTTP {$response_code}）：{$error_message}。請檢查 API Key 是否正確。");
            }
            
            // 如果是 404 (模型不存在) 或 400 (參數錯誤)，嘗試下一個模型
            if ($response_code === 404 || $response_code === 400) {
                continue;
            }
            
            // 其他錯誤（如 500 伺服器錯誤），也嘗試下一個模型
            continue;
        }
    }
    
    // 所有模型都失敗了
    $all_errors = implode("; ", $errors);
    return new WP_Error("api_error", "所有模型配置都失敗。詳情：" . $all_errors);
}

/**
 * 調用 OpenAI API
 */
function mpu_call_openai_api($api_key, $model, $system_prompt, $user_prompt, $language) {
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
        return new WP_Error("api_request_failed", "OpenAI API 請求失敗：" . $response->get_error_message());
    }
    
    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);
    
    if ($response_code !== 200) {
        $error_data = json_decode($response_body, true);
        $error_message = isset($error_data["error"]["message"]) 
            ? $error_data["error"]["message"] 
            : "API 請求失敗 (HTTP {$response_code})";
        return new WP_Error("api_error", "OpenAI API 錯誤：{$error_message}");
    }
    
    // 解析回應
    $data = json_decode($response_body, true);
    
    if (empty($data["choices"][0]["message"]["content"])) {
        return new WP_Error("invalid_response", "OpenAI API 回應格式錯誤");
    }
    
    $generated_text = trim($data["choices"][0]["message"]["content"]);
    
    return $generated_text;
}

/**
 * 調用 Claude API (Anthropic)
 */
function mpu_call_claude_api($api_key, $model, $system_prompt, $user_prompt, $language) {
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
        return new WP_Error("api_request_failed", "Claude API 請求失敗：" . $response->get_error_message());
    }
    
    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);
    
    if ($response_code !== 200) {
        $error_data = json_decode($response_body, true);
        $error_message = isset($error_data["error"]["message"]) 
            ? $error_data["error"]["message"] 
            : "API 請求失敗 (HTTP {$response_code})";
        return new WP_Error("api_error", "Claude API 錯誤：{$error_message}");
    }
    
    // 解析回應
    $data = json_decode($response_body, true);
    
    if (empty($data["content"][0]["text"])) {
        return new WP_Error("invalid_response", "Claude API 回應格式錯誤");
    }
    
    $generated_text = trim($data["content"][0]["text"]);
    
    return $generated_text;
}

/**
 * 獲取語言指令（共用函數）
 */
function mpu_get_language_instruction($language) {
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
 * 輔助函數：檢查是否應該觸發 AI
 */
function mpu_should_trigger_ai() {
    $mpu_opt = mpu_get_option();
    
    if (empty($mpu_opt["ai_enabled"])) {
        return false;
    }
    
    $trigger_pages = $mpu_opt["ai_trigger_pages"] ?? "is_single";
    $conditions = array_map("trim", explode(",", $trigger_pages));
    
    foreach ($conditions as $condition) {
        $condition = trim($condition);
        if (empty($condition)) {
            continue;
        }
        
        // 檢查 WordPress 條件標籤
        if (function_exists($condition)) {
            if (call_user_func($condition)) {
                return true;
            }
        }
    }
    
    return false;
}

