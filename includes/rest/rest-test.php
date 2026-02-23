<?php

/**
 * REST API Endpoints: Test Connections and Admin Tools
 * 
 * @package MP_Ukagaka
 * @subpackage REST
 */

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Register Test REST API routes.
 */
function mpu_register_rest_test_routes() {
    $namespace = 'mp-ukagaka/v1';

    register_rest_route($namespace, '/test-connection/(?P<provider>[\w-]+)', array(
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'mpu_rest_test_connection',
        'permission_callback' => 'mpu_rest_admin_permission_check',
        'args'                => array(
            'provider' => array(
                'required' => true,
                'type'     => 'string',
            ),
        ),
    ));

    register_rest_route($namespace, '/clear-cache', array(
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'mpu_rest_clear_api_cache',
        'permission_callback' => 'mpu_rest_admin_permission_check',
    ));
}
add_action('rest_api_init', 'mpu_register_rest_test_routes');

/**
 * Permission check for admin-only REST endpoints
 */
function mpu_rest_admin_permission_check(WP_REST_Request $request) {
    if (!is_user_logged_in()) {
        return new WP_Error('rest_not_logged_in', __('請先登入', 'mp-ukagaka'), array('status' => 401));
    }
    if (!current_user_can('manage_options')) {
        return new WP_Error('rest_forbidden', __('權限不足', 'mp-ukagaka'), array('status' => 403));
    }
    return true;
}

/**
 * REST Callback: 測試連接
 */
function mpu_rest_test_connection(WP_REST_Request $request) {
    $provider = $request->get_param('provider');
    
    // Rate Limiting
    if (function_exists('mpu_rest_check_rate_limit')) {
        $rl = mpu_rest_check_rate_limit('test_' . $provider . '_connection', 10, 60);
        if ($rl !== null) return $rl;
    }

    switch ($provider) {
        case 'ollama':
            return mpu_rest_test_ollama($request);
        case 'gemini':
            return mpu_rest_test_gemini($request);
        case 'openai':
            return mpu_rest_test_openai($request);
        case 'claude':
            return mpu_rest_test_claude($request);
        case 'weather':
            return mpu_rest_test_weather($request);
        default:
            return new WP_Error('rest_error', sprintf(__('不支援的提供商：%s', 'mp-ukagaka'), $provider), ['status' => 400]);
    }
}

function mpu_rest_test_ollama($request) {
    $endpoint = $request->get_param('endpoint') ? sanitize_text_field($request->get_param('endpoint')) : 'http://localhost:11434';
    $model = $request->get_param('model') ? sanitize_text_field($request->get_param('model')) : 'qwen3:8b';

    if (!function_exists('mpu_validate_ollama_endpoint')) {
        $endpoint = rtrim($endpoint, '/');
        if (!preg_match('/^https?:\/\/.+/', $endpoint)) {
            return new WP_Error('rest_error', __('端點格式無效，請輸入完整的 http:// 或 https:// URL', 'mp-ukagaka'), ['status' => 400]);
        }
        $timeout = 30;
        $is_remote = !preg_match('/localhost|127\.0\.0\.1|::1/', $endpoint);
        if ($is_remote) {
            $timeout = 45;
        }
    } else {
        $validated_endpoint = mpu_validate_ollama_endpoint($endpoint);
        if (is_wp_error($validated_endpoint)) {
            return new WP_Error('rest_error', $validated_endpoint->get_error_message(), ['status' => 400]);
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

        if (strpos($error_message, 'timeout') !== false || strpos($error_message, 'timed out') !== false) {
            $msg = $is_remote ?
                sprintf(__('連接超時（已等待 %s 秒）。遠程連接可能需要更長時間，請檢查 Cloudflare Tunnel 或網絡狀況。', 'mp-ukagaka'), $timeout) :
                sprintf(__('連接超時（已等待 %s 秒）。請確認 Ollama 服務是否正常運行。', 'mp-ukagaka'), $timeout);
            return new WP_Error('rest_error', $msg, ['status' => 400]);
        }

        if (strpos($error_message, 'Connection refused') !== false || strpos($error_message, 'couldn\'t connect') !== false) {
            $msg = $is_remote ?
                sprintf(__('無法連接到遠程 Ollama 服務。請確認 Cloudflare Tunnel 是否正在運行，端點 URL 是否正確。錯誤：%s', 'mp-ukagaka'), $error_message) :
                sprintf(__('無法連接到 Ollama 服務。請確認 Ollama 是否正在運行。錯誤：%s', 'mp-ukagaka'), $error_message);
            return new WP_Error('rest_error', $msg, ['status' => 400]);
        }

        $connection_type_text = $is_remote ? __('遠程', 'mp-ukagaka') : __('本地', 'mp-ukagaka');
        return new WP_Error('rest_error', sprintf(__('%s連接失敗：%s', 'mp-ukagaka'), $connection_type_text, $error_message), ['status' => 400]);
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
                return new WP_REST_Response(['msg' => sprintf(__('連接成功（%s連接），模型響應正常（預覽：%s...）', 'mp-ukagaka'), $connection_type_text, $preview)], 200);
            } else {
                $preview = mb_substr($content, 0, 50);
                return new WP_REST_Response(['msg' => sprintf(__('連接成功，但模型只返回思考過程（預覽：%s...）。實際使用時應會生成內容。', 'mp-ukagaka'), $preview)], 200);
            }
        } else {
            $response_keys = is_array($data) ? array_keys($data) : [];
            $debug_info = '響應鍵: [' . implode(', ', $response_keys) . ']';
            if (defined('WP_DEBUG') && WP_DEBUG) {
                $debug_info .= ' | 完整響應: ' . mb_substr($response_body, 0, 200);
            }
            return new WP_Error('rest_error', sprintf(__('連接成功但回應格式異常，無法解析模型輸出。%s', 'mp-ukagaka'), $debug_info), ['status' => 400]);
        }
    } else {
        $error_body = wp_remote_retrieve_body($response);
        $error_data = json_decode($error_body, true);
        $error_message = isset($error_data['error']) ? $error_data['error'] : sprintf(__('HTTP %s：請檢查 Ollama 是否運行且模型已下載', 'mp-ukagaka'), $response_code);
        return new WP_Error('rest_error', $error_message, ['status' => 400]);
    }
}

function mpu_rest_test_gemini($request) {
    $api_key = $request->get_param('api_key') ? sanitize_text_field($request->get_param('api_key')) : '';
    $model = $request->get_param('model') ? sanitize_text_field($request->get_param('model')) : 'gemini-2.5-flash';

    if (empty($api_key)) {
        $mpu_opt = mpu_get_option();
        $api_key_encrypted = $mpu_opt['llm_gemini_api_key'] ?? $mpu_opt['ai_api_key'] ?? '';
        if (!empty($api_key_encrypted)) {
            $api_key = mpu_decrypt_api_key($api_key_encrypted);
        }
    }

    if (empty($api_key)) {
        return new WP_Error('rest_error', __('Gemini API Key 未設定', 'mp-ukagaka'), ['status' => 400]);
    }

    $api_url = "https://generativelanguage.googleapis.com/v1/models/{$model}:generateContent?key=" . urlencode($api_key);
    $request_body = [
        "contents" => [
            ["parts" => [["text" => "Hi"]]]
        ],
        "generationConfig" => ["maxOutputTokens" => 50]
    ];

    $response = wp_remote_post($api_url, [
        "headers" => ["Content-Type" => "application/json"],
        "body" => wp_json_encode($request_body),
        "timeout" => 30,
    ]);

    if (is_wp_error($response)) {
        return new WP_Error('rest_error', sprintf(__('連接失敗：%s', 'mp-ukagaka'), $response->get_error_message()), ['status' => 400]);
    }

    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);

    if ($response_code === 200) {
        $data = json_decode($response_body, true);
        if (!empty($data["candidates"][0]["content"]["parts"][0]["text"])) {
            $content = trim($data["candidates"][0]["content"]["parts"][0]["text"]);
            $preview = mb_substr($content, 0, 50);
            return new WP_REST_Response(['msg' => sprintf(__('連接成功，模型響應正常（預覽：%s...）', 'mp-ukagaka'), $preview)], 200);
        } else {
            return new WP_Error('rest_error', __('連接成功但回應格式異常，無法解析模型輸出', 'mp-ukagaka'), ['status' => 400]);
        }
    } else {
        $error_data = json_decode($response_body, true);
        $error_message = isset($error_data["error"]["message"]) ? $error_data["error"]["message"] : sprintf(__('HTTP %s 錯誤', 'mp-ukagaka'), $response_code);

        if ($response_code === 401 || $response_code === 403) {
            return new WP_Error('rest_error', sprintf(__('API Key 無效或權限不足：%s', 'mp-ukagaka'), $error_message), ['status' => 400]);
        } elseif ($response_code === 404) {
            return new WP_Error('rest_error', sprintf(__('模型不存在（%s）：%s', 'mp-ukagaka'), $model, $error_message), ['status' => 400]);
        } else {
            return new WP_Error('rest_error', $error_message, ['status' => 400]);
        }
    }
}

function mpu_rest_test_openai($request) {
    $api_key = $request->get_param('api_key') ? sanitize_text_field($request->get_param('api_key')) : '';
    $model = $request->get_param('model') ? sanitize_text_field($request->get_param('model')) : 'gpt-4o-mini';

    if (empty($api_key)) {
        $mpu_opt = mpu_get_option();
        $api_key_encrypted = $mpu_opt['llm_openai_api_key'] ?? $mpu_opt['openai_api_key'] ?? '';
        if (!empty($api_key_encrypted)) {
            $api_key = mpu_decrypt_api_key($api_key_encrypted);
        }
    }

    if (empty($api_key)) {
        return new WP_Error('rest_error', __('OpenAI API Key 未設定', 'mp-ukagaka'), ['status' => 400]);
    }

    $api_url = "https://api.openai.com/v1/chat/completions";
    $request_body = [
        "model" => $model,
        "messages" => [["role" => "user", "content" => "Hi"]],
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
        return new WP_Error('rest_error', sprintf(__('連接失敗：%s', 'mp-ukagaka'), $response->get_error_message()), ['status' => 400]);
    }

    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);

    if ($response_code === 200) {
        $data = json_decode($response_body, true);
        if (!empty($data["choices"][0]["message"]["content"])) {
            $content = trim($data["choices"][0]["message"]["content"]);
            $preview = mb_substr($content, 0, 50);
            return new WP_REST_Response(['msg' => sprintf(__('連接成功，模型響應正常（預覽：%s...）', 'mp-ukagaka'), $preview)], 200);
        } else {
            return new WP_Error('rest_error', __('連接成功但回應格式異常，無法解析模型輸出', 'mp-ukagaka'), ['status' => 400]);
        }
    } else {
        $error_data = json_decode($response_body, true);
        $error_message = isset($error_data["error"]["message"]) ? $error_data["error"]["message"] : sprintf(__('HTTP %s 錯誤', 'mp-ukagaka'), $response_code);

        if ($response_code === 401 || $response_code === 403) {
            return new WP_Error('rest_error', sprintf(__('API Key 無效或權限不足：%s', 'mp-ukagaka'), $error_message), ['status' => 400]);
        } else {
            return new WP_Error('rest_error', $error_message, ['status' => 400]);
        }
    }
}

function mpu_rest_test_claude($request) {
    $api_key = $request->get_param('api_key') ? sanitize_text_field($request->get_param('api_key')) : '';
    $model = $request->get_param('model') ? sanitize_text_field($request->get_param('model')) : 'claude-sonnet-4-5-20250929';

    if (empty($api_key)) {
        $mpu_opt = mpu_get_option();
        $api_key_encrypted = $mpu_opt['llm_claude_api_key'] ?? $mpu_opt['claude_api_key'] ?? '';
        if (!empty($api_key_encrypted)) {
            $api_key = mpu_decrypt_api_key($api_key_encrypted);
        }
    }

    if (empty($api_key)) {
        return new WP_Error('rest_error', __('Claude API Key 未設定', 'mp-ukagaka'), ['status' => 400]);
    }

    $api_url = "https://api.anthropic.com/v1/messages";
    $request_body = [
        "model" => $model,
        "max_tokens" => 50,
        "messages" => [
            ["role" => "user", "content" => "Hi"]
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
        return new WP_Error('rest_error', sprintf(__('連接失敗：%s', 'mp-ukagaka'), $response->get_error_message()), ['status' => 400]);
    }

    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);

    if ($response_code === 200) {
        $data = json_decode($response_body, true);
        if (!empty($data["content"][0]["text"])) {
            $content = trim($data["content"][0]["text"]);
            $preview = mb_substr($content, 0, 50);
            return new WP_REST_Response(['msg' => sprintf(__('連接成功，模型響應正常（預覽：%s...）', 'mp-ukagaka'), $preview)], 200);
        } else {
            return new WP_Error('rest_error', __('連接成功但回應格式異常，無法解析模型輸出', 'mp-ukagaka'), ['status' => 400]);
        }
    } else {
        $error_data = json_decode($response_body, true);
        $error_message = isset($error_data["error"]["message"]) ? $error_data["error"]["message"] : sprintf(__('HTTP %s 錯誤', 'mp-ukagaka'), $response_code);

        if ($response_code === 401 || $response_code === 403) {
            return new WP_Error('rest_error', sprintf(__('API Key 無效或權限不足：%s', 'mp-ukagaka'), $error_message), ['status' => 400]);
        } else {
            return new WP_Error('rest_error', $error_message, ['status' => 400]);
        }
    }
}

function mpu_rest_test_weather($request) {
    if (function_exists('mpu_rest_check_rate_limit')) {
        $rl = mpu_rest_check_rate_limit('test_weather_api', 10, 60);
        if ($rl !== null) return $rl;
    }

    $latitude_param = $request->get_param('latitude');
    $longitude_param = $request->get_param('longitude');

    $latitude = isset($latitude_param) && $latitude_param !== '' ? floatval($latitude_param) : 25.0330;
    $longitude = isset($longitude_param) && $longitude_param !== '' ? floatval($longitude_param) : 121.5654;

    if ($latitude < -90 || $latitude > 90) {
        return new WP_Error('rest_error', __('緯度參數無效（須介於 -90 至 90 之間）', 'mp-ukagaka'), ['status' => 400]);
    }
    if ($longitude < -180 || $longitude > 180) {
        return new WP_Error('rest_error', __('經度參數無效（須介於 -180 至 180 之間）', 'mp-ukagaka'), ['status' => 400]);
    }

    if (function_exists('mpu_clear_weather_cache')) {
        mpu_clear_weather_cache($latitude, $longitude);
    }

    if (!function_exists('mpu_get_weather_forecast')) {
        return new WP_Error('rest_error', __('天氣功能模組不可用', 'mp-ukagaka'), ['status' => 400]);
    }

    $weather = mpu_get_weather_forecast($latitude, $longitude);

    if ($weather === null) {
        return new WP_Error('rest_error', __('無法獲取天氣數據，請確認網絡連線及 Open-Meteo API 是否正常', 'mp-ukagaka'), ['status' => 400]);
    }

    $current_weather = $weather['current']['weather_text'] ?? '不明';
    $current_temp = $weather['current']['temperature'] ?? '-';
    $today_precip_prob = $weather['today']['precipitation_probability'] ?? null;
    $tomorrow_weather = $weather['tomorrow']['weather_text'] ?? '不明';
    $tomorrow_max = $weather['tomorrow']['temp_max'] ?? '-';
    $tomorrow_min = $weather['tomorrow']['temp_min'] ?? '-';
    $tomorrow_precip_prob = $weather['tomorrow']['precipitation_probability'] ?? null;

    $today_precip_text = '';
    if ($today_precip_prob !== null && $today_precip_prob > 0) {
        $today_code = $weather['today']['weather_code'] ?? 0;
        $precip_type = ($today_code >= 71 && $today_code <= 77) || ($today_code >= 85 && $today_code <= 86) ? '降雪' : '降水';
        $today_precip_text = sprintf(' %s%d%%', $precip_type, $today_precip_prob);
    }

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

    return new WP_REST_Response(['msg' => $message], 200);
}

/**
 * REST Callback: 清除 API 快取
 */
function mpu_rest_clear_api_cache(WP_REST_Request $request) {
    if (function_exists('mpu_rest_check_rate_limit')) {
        $rl = mpu_rest_check_rate_limit('clear_api_cache', 10, 60);
        if ($rl !== null) return $rl;
    }
    
    if (function_exists('mpu_clear_all_api_cache')) {
        $cleared_count = mpu_clear_all_api_cache();
        return new WP_REST_Response(['msg' => sprintf(__('已清除 %d 筆快取', 'mp-ukagaka'), $cleared_count)], 200);
    } else {
        return new WP_Error('rest_error', __('快取清除功能不可用', 'mp-ukagaka'), ['status' => 400]);
    }
}
