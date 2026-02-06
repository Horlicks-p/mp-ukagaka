<?php

/**
 * API 連線測試 AJAX 處理器
 * 
 * @package MP_Ukagaka
 * @subpackage AJAX
 */

if (!defined('ABSPATH')) {
    exit();
}

function mpu_ajax_test_ollama_connection()
{
    check_ajax_referer('mpu_test_connection', 'nonce');

    // Rate Limiting
    if (function_exists('mpu_enforce_rate_limit')) {
        mpu_enforce_rate_limit('test_ollama_connection', 10, 60);
    }

    $endpoint = sanitize_text_field($_POST['endpoint'] ?? 'http://localhost:11434');
    $model = sanitize_text_field($_POST['model'] ?? 'qwen3:8b');

    if (!function_exists('mpu_validate_ollama_endpoint')) {
        $endpoint = rtrim($endpoint, '/');
        if (!preg_match('/^https?:\/\/.+/', $endpoint)) {
            wp_send_json_error(__('端點 URL 格式錯誤：必須是有效的 HTTP 或 HTTPS URL', 'mp-ukagaka'));
            return;
        }
        $timeout = 30;
        $is_remote = !preg_match('/localhost|127\.0\.0\.1|::1/', $endpoint);
        if ($is_remote) {
            $timeout = 45;
        }
    } else {
        $validated_endpoint = mpu_validate_ollama_endpoint($endpoint);
        if (is_wp_error($validated_endpoint)) {
            wp_send_json_error(sprintf(__('端點 URL 格式錯誤：%s', 'mp-ukagaka'), $validated_endpoint->get_error_message()));
            return;
        }
        $endpoint = $validated_endpoint;

        $timeout = mpu_get_ollama_timeout($endpoint, 'test');
        $is_remote = mpu_is_remote_endpoint($endpoint);
    }

    $api_url = rtrim($endpoint, '/') . '/api/chat';
    $request_body = [
        'model' => $model,
        'messages' => [
            ['role' => 'user', 'content' => 'Hi']
        ],
        'stream' => false,
        'options' => [
            'num_predict' => 50,
            'temperature' => 0.7
        ]
    ];

    $response = wp_remote_post($api_url, [
        'headers' => ['Content-Type' => 'application/json'],
        'body' => wp_json_encode($request_body),
        'timeout' => $timeout,
    ]);

    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        $connection_type = $is_remote ? '遠程' : '本地';

        if (strpos($error_message, 'timeout') !== false || strpos($error_message, 'timed out') !== false) {
            if ($is_remote) {
                wp_send_json_error(sprintf(__('連接超時（已等待 %s 秒）。遠程連接可能需要更長時間，請檢查 Cloudflare Tunnel 或網絡狀況。', 'mp-ukagaka'), $timeout));
            } else {
                wp_send_json_error(sprintf(__('連接超時（已等待 %s 秒）。請確認 Ollama 服務是否正常運行。', 'mp-ukagaka'), $timeout));
            }
            return;
        }

        if (strpos($error_message, 'Connection refused') !== false || strpos($error_message, 'couldn\'t connect') !== false) {
            if ($is_remote) {
                wp_send_json_error(sprintf(__('無法連接到遠程 Ollama 服務。請確認 Cloudflare Tunnel 是否正在運行，端點 URL 是否正確。錯誤：%s', 'mp-ukagaka'), $error_message));
            } else {
                wp_send_json_error(sprintf(__('無法連接到 Ollama 服務。請確認 Ollama 是否正在運行。錯誤：%s', 'mp-ukagaka'), $error_message));
            }
            return;
        }

        $connection_type_text = $is_remote ? __('遠程', 'mp-ukagaka') : __('本地', 'mp-ukagaka');
        wp_send_json_error(sprintf(__('連接失敗（%s連接）：%s', 'mp-ukagaka'), $connection_type_text, $error_message));
        return;
    }

    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);

    if ($response_code === 200) {
        $data = json_decode($response_body, true);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            if (function_exists('mpu_debug_log')) {
                mpu_debug_log('Ollama Test Response: ' . print_r($data, true));
            } else {
                error_log('Ollama Test Response: ' . print_r($data, true));
            }
        }

        $content = null;
        $has_content = false;

        if (!empty($data['message']['content'])) {
            $content = $data['message']['content'];
            $has_content = true;
        } elseif (!empty($data['content'])) {
            $content = $data['content'];
            $has_content = true;
        } elseif (isset($data['message']) && is_string($data['message'])) {
            $content = $data['message'];
            $has_content = true;
        } elseif (!empty($data['response'])) {
            $content = $data['response'];
            $has_content = true;
        } elseif (!empty($data['message']['thinking'])) {
            $content = $data['message']['thinking'];
            $has_content = false;
        }

        if (!empty($content)) {
            if ($has_content) {
                $cache_key = 'mpu_ollama_available_' . md5($endpoint . $model);
                delete_transient($cache_key);
                set_transient($cache_key, 1, 5 * MINUTE_IN_SECONDS);

                $preview = mb_substr($content, 0, 50);
                $connection_type_text = $is_remote ? __('遠程', 'mp-ukagaka') : __('本地', 'mp-ukagaka');
                wp_send_json_success(sprintf(__('連接成功（%s連接），模型響應正常（預覽：%s...）', 'mp-ukagaka'), $connection_type_text, $preview));
            } else {
                $preview = mb_substr($content, 0, 50);
                wp_send_json_success(sprintf(__('連接成功，但模型只返回思考過程（預覽：%s...）。實際使用時應會生成內容。', 'mp-ukagaka'), $preview));
            }
        } else {
            $response_keys = is_array($data) ? array_keys($data) : [];
            $response_preview = mb_substr($response_body, 0, 200);

            $debug_info = '響應鍵: [' . implode(', ', $response_keys) . ']';
            if (defined('WP_DEBUG') && WP_DEBUG) {
                $debug_info .= ' | 完整響應: ' . $response_preview;
            }

            wp_send_json_error('模型未返回有效響應。' . $debug_info . ' 請檢查模型是否正確載入或嘗試使用其他模型。');
        }
    } else {
        $error_body = wp_remote_retrieve_body($response);
        $error_data = json_decode($error_body, true);
        $error_message = isset($error_data['error']) ? $error_data['error'] : "HTTP {$response_code}：請檢查 Ollama 是否運行且模型已下載";
        wp_send_json_error($error_message);
    }
}
add_action('wp_ajax_mpu_test_ollama_connection', 'mpu_ajax_test_ollama_connection');

function mpu_ajax_test_gemini_connection()
{
    check_ajax_referer('mpu_test_connection', 'nonce');

    // Rate Limiting
    if (function_exists('mpu_enforce_rate_limit')) {
        mpu_enforce_rate_limit('test_gemini_connection', 10, 60);
    }

    $api_key = sanitize_text_field($_POST['api_key'] ?? '');
    $model = sanitize_text_field($_POST['model'] ?? 'gemini-2.5-flash');

    if (empty($api_key)) {
        $mpu_opt = mpu_get_option();
        $api_key_encrypted = $mpu_opt['llm_gemini_api_key'] ?? $mpu_opt['ai_api_key'] ?? '';
        if (!empty($api_key_encrypted)) {
            $api_key = mpu_decrypt_api_key($api_key_encrypted);
        }
    }

    if (empty($api_key)) {
        wp_send_json_error(__('請輸入 Gemini API Key', 'mp-ukagaka'));
        return;
    }

    $api_url = "https://generativelanguage.googleapis.com/v1/models/{$model}:generateContent?key=" . urlencode($api_key);
    $request_body = [
        "contents" => [
            [
                "parts" => [
                    [
                        "text" => "Hi"
                    ]
                ]
            ]
        ],
        "generationConfig" => [
            "maxOutputTokens" => 50,
        ]
    ];

    $response = wp_remote_post($api_url, [
        "headers" => [
            "Content-Type" => "application/json",
        ],
        "body" => wp_json_encode($request_body),
        "timeout" => 30,
    ]);

    if (is_wp_error($response)) {
        wp_send_json_error(sprintf(__('連接失敗：%s', 'mp-ukagaka'), $response->get_error_message()));
        return;
    }

    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);

    if ($response_code === 200) {
        $data = json_decode($response_body, true);
        if (!empty($data["candidates"][0]["content"]["parts"][0]["text"])) {
            $content = trim($data["candidates"][0]["content"]["parts"][0]["text"]);
            $preview = mb_substr($content, 0, 50);
            wp_send_json_success(sprintf(__('連接成功，模型響應正常（預覽：%s...）', 'mp-ukagaka'), $preview));
        } else {
            wp_send_json_error(__('API 回應為空，請檢查模型是否正確', 'mp-ukagaka'));
        }
    } else {
        $error_data = json_decode($response_body, true);
        $error_message = isset($error_data["error"]["message"])
            ? $error_data["error"]["message"]
            : sprintf(__('HTTP %s 錯誤', 'mp-ukagaka'), $response_code);

        if ($response_code === 401 || $response_code === 403) {
            wp_send_json_error(sprintf(__('API 認證失敗：%s。請檢查 API Key 是否正確。', 'mp-ukagaka'), $error_message));
        } elseif ($response_code === 404) {
            wp_send_json_error(sprintf(__('模型「%s」不存在。請在設定中選擇正確的模型。', 'mp-ukagaka'), $model));
        } else {
            wp_send_json_error(sprintf(__('API 錯誤（HTTP %s）：%s', 'mp-ukagaka'), $response_code, $error_message));
        }
    }
}
add_action('wp_ajax_mpu_test_gemini_connection', 'mpu_ajax_test_gemini_connection');

function mpu_ajax_test_openai_connection()
{
    check_ajax_referer('mpu_test_connection', 'nonce');

    // Rate Limiting
    if (function_exists('mpu_enforce_rate_limit')) {
        mpu_enforce_rate_limit('test_openai_connection', 10, 60);
    }

    $api_key = sanitize_text_field($_POST['api_key'] ?? '');
    $model = sanitize_text_field($_POST['model'] ?? 'gpt-4o-mini');

    if (empty($api_key)) {
        $mpu_opt = mpu_get_option();
        $api_key_encrypted = $mpu_opt['llm_openai_api_key'] ?? $mpu_opt['openai_api_key'] ?? '';
        if (!empty($api_key_encrypted)) {
            $api_key = mpu_decrypt_api_key($api_key_encrypted);
        }
    }

    if (empty($api_key)) {
        wp_send_json_error(__('請輸入 OpenAI API Key', 'mp-ukagaka'));
        return;
    }

    $api_url = "https://api.openai.com/v1/chat/completions";
    $request_body = [
        "model" => $model,
        "messages" => [
            [
                "role" => "user",
                "content" => "Hi"
            ]
        ],
        "max_tokens" => 50,
    ];

    $response = wp_remote_post($api_url, [
        "headers" => [
            "Content-Type" => "application/json",
            "Authorization" => "Bearer " . $api_key,
        ],
        "body" => wp_json_encode($request_body),
        "timeout" => 30,
    ]);

    if (is_wp_error($response)) {
        wp_send_json_error(sprintf(__('連接失敗：%s', 'mp-ukagaka'), $response->get_error_message()));
        return;
    }

    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);

    if ($response_code === 200) {
        $data = json_decode($response_body, true);
        if (!empty($data["choices"][0]["message"]["content"])) {
            $content = trim($data["choices"][0]["message"]["content"]);
            $preview = mb_substr($content, 0, 50);
            wp_send_json_success(sprintf(__('連接成功，模型響應正常（預覽：%s...）', 'mp-ukagaka'), $preview));
        } else {
            wp_send_json_error(__('API 回應格式錯誤', 'mp-ukagaka'));
        }
    } else {
        $error_data = json_decode($response_body, true);
        $error_message = isset($error_data["error"]["message"])
            ? $error_data["error"]["message"]
            : sprintf(__('HTTP %s 錯誤', 'mp-ukagaka'), $response_code);

        if ($response_code === 401 || $response_code === 403) {
            wp_send_json_error(sprintf(__('API 認證失敗：%s。請檢查 API Key 是否正確。', 'mp-ukagaka'), $error_message));
        } else {
            wp_send_json_error(sprintf(__('API 錯誤（HTTP %s）：%s', 'mp-ukagaka'), $response_code, $error_message));
        }
    }
}
add_action('wp_ajax_mpu_test_openai_connection', 'mpu_ajax_test_openai_connection');

function mpu_ajax_test_claude_connection()
{
    check_ajax_referer('mpu_test_connection', 'nonce');

    // Rate Limiting
    if (function_exists('mpu_enforce_rate_limit')) {
        mpu_enforce_rate_limit('test_claude_connection', 10, 60);
    }

    $api_key = sanitize_text_field($_POST['api_key'] ?? '');
    $model = sanitize_text_field($_POST['model'] ?? 'claude-sonnet-4-5-20250929');

    if (empty($api_key)) {
        $mpu_opt = mpu_get_option();
        $api_key_encrypted = $mpu_opt['llm_claude_api_key'] ?? $mpu_opt['claude_api_key'] ?? '';
        if (!empty($api_key_encrypted)) {
            $api_key = mpu_decrypt_api_key($api_key_encrypted);
        }
    }

    if (empty($api_key)) {
        wp_send_json_error(__('請輸入 Claude API Key', 'mp-ukagaka'));
        return;
    }

    $api_url = "https://api.anthropic.com/v1/messages";
    $request_body = [
        "model" => $model,
        "max_tokens" => 50,
        "messages" => [
            [
                "role" => "user",
                "content" => "Hi"
            ]
        ],
    ];

    $response = wp_remote_post($api_url, [
        "headers" => [
            "Content-Type" => "application/json",
            "x-api-key" => $api_key,
            "anthropic-version" => "2023-06-01",
        ],
        "body" => wp_json_encode($request_body),
        "timeout" => 30,
    ]);

    if (is_wp_error($response)) {
        wp_send_json_error(sprintf(__('連接失敗：%s', 'mp-ukagaka'), $response->get_error_message()));
        return;
    }

    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);

    if ($response_code === 200) {
        $data = json_decode($response_body, true);
        if (!empty($data["content"][0]["text"])) {
            $content = trim($data["content"][0]["text"]);
            $preview = mb_substr($content, 0, 50);
            wp_send_json_success(sprintf(__('連接成功，模型響應正常（預覽：%s...）', 'mp-ukagaka'), $preview));
        } else {
            wp_send_json_error(__('API 回應格式錯誤', 'mp-ukagaka'));
        }
    } else {
        $error_data = json_decode($response_body, true);
        $error_message = isset($error_data["error"]["message"])
            ? $error_data["error"]["message"]
            : sprintf(__('HTTP %s 錯誤', 'mp-ukagaka'), $response_code);

        if ($response_code === 401 || $response_code === 403) {
            wp_send_json_error(sprintf(__('API 認證失敗：%s。請檢查 API Key 是否正確。', 'mp-ukagaka'), $error_message));
        } else {
            wp_send_json_error(sprintf(__('API 錯誤（HTTP %s）：%s', 'mp-ukagaka'), $response_code, $error_message));
        }
    }
}
add_action('wp_ajax_mpu_test_claude_connection', 'mpu_ajax_test_claude_connection');

/**
 * 測試 Open-Meteo 天氣 API 連線
 */
function mpu_ajax_test_weather_api()
{
    check_ajax_referer('mpu_test_weather', 'nonce');

    // Rate Limiting
    if (function_exists('mpu_enforce_rate_limit')) {
        mpu_enforce_rate_limit('test_weather_api', 10, 60);
    }

    $latitude = isset($_POST['latitude']) ? floatval($_POST['latitude']) : 25.0330;
    $longitude = isset($_POST['longitude']) ? floatval($_POST['longitude']) : 121.5654;

    // 驗證座標範圍
    if ($latitude < -90 || $latitude > 90) {
        wp_send_json_error(__('緯度必須在 -90 到 90 之間', 'mp-ukagaka'));
        return;
    }
    if ($longitude < -180 || $longitude > 180) {
        wp_send_json_error(__('經度必須在 -180 到 180 之間', 'mp-ukagaka'));
        return;
    }

    // 清除快取以獲取最新資料
    if (function_exists('mpu_clear_weather_cache')) {
        mpu_clear_weather_cache($latitude, $longitude);
    }

    // 獲取天氣資料
    if (!function_exists('mpu_get_weather_forecast')) {
        wp_send_json_error(__('天氣功能模組未載入', 'mp-ukagaka'));
        return;
    }

    $weather = mpu_get_weather_forecast($latitude, $longitude);

    if ($weather === null) {
        wp_send_json_error(__('無法獲取天氣資料，請檢查網路連接或座標是否正確', 'mp-ukagaka'));
        return;
    }

    // 格式化成功訊息
    $current_weather = $weather['current']['weather_text'] ?? '不明';
    $current_temp = $weather['current']['temperature'] ?? '-';
    $today_precip_prob = $weather['today']['precipitation_probability'] ?? null;
    $tomorrow_weather = $weather['tomorrow']['weather_text'] ?? '不明';
    $tomorrow_max = $weather['tomorrow']['temp_max'] ?? '-';
    $tomorrow_min = $weather['tomorrow']['temp_min'] ?? '-';
    $tomorrow_precip_prob = $weather['tomorrow']['precipitation_probability'] ?? null;

    // 格式化今天降水機率
    $today_precip_text = '';
    if ($today_precip_prob !== null && $today_precip_prob > 0) {
        $today_code = $weather['today']['weather_code'] ?? 0;
        $precip_type = ($today_code >= 71 && $today_code <= 77) || ($today_code >= 85 && $today_code <= 86) ? '降雪' : '降水';
        $today_precip_text = sprintf(' %s%d%%', $precip_type, $today_precip_prob);
    }

    // 格式化明天降水機率
    $tomorrow_precip_text = '';
    if ($tomorrow_precip_prob !== null && $tomorrow_precip_prob > 0) {
        $tomorrow_code = $weather['tomorrow']['weather_code'] ?? 0;
        $precip_type = ($tomorrow_code >= 71 && $tomorrow_code <= 77) || ($tomorrow_code >= 85 && $tomorrow_code <= 86) ? '降雪' : '降水';
        $tomorrow_precip_text = sprintf(' %s%d%%', $precip_type, $tomorrow_precip_prob);
    }

    $message = sprintf(
        __('連接成功！現在：%s %.0f°C%s / 明日：%s %.0f~%.0f°C%s', 'mp-ukagaka'),
        $current_weather,
        $current_temp,
        $today_precip_text,
        $tomorrow_weather,
        $tomorrow_min,
        $tomorrow_max,
        $tomorrow_precip_text
    );

    wp_send_json_success($message);
}
add_action('wp_ajax_mpu_test_weather_api', 'mpu_ajax_test_weather_api');

/**
 * 清除 API 快取 AJAX 處理器
 */
function mpu_ajax_clear_api_cache()
{
    check_ajax_referer('mpu_clear_cache', 'nonce');
    
    // Rate Limiting
    if (function_exists('mpu_enforce_rate_limit')) {
        mpu_enforce_rate_limit('clear_api_cache', 10, 60);
    }
    
    // 檢查權限
    if (!current_user_can('manage_options')) {
        wp_send_json_error(__('權限不足', 'mp-ukagaka'));
        return;
    }
    
    // 清除快取
    if (function_exists('mpu_clear_all_api_cache')) {
        $cleared_count = mpu_clear_all_api_cache();
        wp_send_json_success(sprintf(__('已清除 %d 筆快取', 'mp-ukagaka'), $cleared_count));
    } else {
        wp_send_json_error(__('快取模組未載入', 'mp-ukagaka'));
    }
}
add_action('wp_ajax_mpu_clear_api_cache', 'mpu_ajax_clear_api_cache');
