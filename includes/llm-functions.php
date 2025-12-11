<?php

/**
 * LLM 功能：本機 LLM (Ollama) 對話生成
 * 
 * @package MP_Ukagaka
 * @subpackage LLM
 */

if (!defined('ABSPATH')) {
    exit();
}

/**
 * 檢測 Ollama 端點是否為遠程連接
 * 
 * @param string $endpoint Ollama 端點 URL
 * @return bool 是否為遠程連接（true = 遠程，false = 本地）
 */
function mpu_is_remote_endpoint($endpoint)
{
    if (empty($endpoint)) {
        return false;
    }

    // 標準化 URL（移除尾部斜線，轉為小寫）
    $normalized = strtolower(rtrim($endpoint, '/'));

    // 檢查是否為本地連接
    $local_patterns = [
        'localhost',
        '127.0.0.1',
        '::1',
        '0.0.0.0',
    ];

    foreach ($local_patterns as $pattern) {
        if (strpos($normalized, $pattern) !== false) {
            return false; // 本地連接
        }
    }

    // 如果包含 http:// 或 https:// 且不是本地模式，則為遠程連接
    if (preg_match('/^https?:\/\//', $normalized)) {
        return true; // 遠程連接
    }

    // 默認視為本地連接（向後兼容）
    return false;
}

/**
 * 根據端點類型和操作類型獲取適當的超時時間
 * 
 * @param string $endpoint Ollama 端點 URL
 * @param string $operation_type 操作類型：'check'（服務檢查）、'api_call'（API 調用）、'test'（測試連接）
 * @return int 超時時間（秒）
 */
function mpu_get_ollama_timeout($endpoint, $operation_type = 'api_call')
{
    $is_remote = mpu_is_remote_endpoint($endpoint);

    // 根據操作類型和連接類型返回超時時間
    switch ($operation_type) {
        case 'check':
            // 服務可用性檢查
            return $is_remote ? 10 : 3;

        case 'api_call':
            // API 調用（生成對話）
            return $is_remote ? 90 : 60;

        case 'test':
            // 測試連接
            return $is_remote ? 45 : 30;

        default:
            // 默認使用 API 調用的超時時間
            return $is_remote ? 90 : 60;
    }
}

/**
 * 驗證和標準化 Ollama 端點 URL
 * 
 * @param string $endpoint 原始端點 URL
 * @return string|WP_Error 標準化後的 URL 或錯誤
 */
function mpu_validate_ollama_endpoint($endpoint)
{
    if (empty($endpoint)) {
        return new WP_Error('empty_endpoint', 'Ollama 端點不能為空');
    }

    // 移除尾部斜線
    $endpoint = rtrim($endpoint, '/');

    // 驗證 URL 格式
    if (!preg_match('/^https?:\/\/.+/', $endpoint)) {
        return new WP_Error('invalid_url_format', 'Ollama 端點必須是有效的 HTTP 或 HTTPS URL');
    }

    // 驗證 URL 是否可解析
    $parsed = wp_parse_url($endpoint);
    if ($parsed === false || empty($parsed['scheme']) || empty($parsed['host'])) {
        return new WP_Error('invalid_url', '無法解析 Ollama 端點 URL');
    }

    // 確保 scheme 是 http 或 https
    if (!in_array($parsed['scheme'], ['http', 'https'], true)) {
        return new WP_Error('invalid_scheme', 'Ollama 端點必須使用 HTTP 或 HTTPS 協議');
    }

    return $endpoint;
}

/**
 * 檢查 Ollama 服務是否可用（快速檢查，使用緩存）
 * 
 * @param string $endpoint Ollama 端點
 * @param string $model 模型名稱
 * @return bool 服務是否可用
 */
function mpu_check_ollama_available($endpoint, $model)
{
    // ★★★ 改進：驗證端點 URL ★★★
    $validated_endpoint = mpu_validate_ollama_endpoint($endpoint);
    if (is_wp_error($validated_endpoint)) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('MP Ukagaka - Ollama 端點驗證失敗: ' . $validated_endpoint->get_error_message());
        }
        return false;
    }
    $endpoint = $validated_endpoint;

    // 使用 transient 緩存檢查結果，避免頻繁檢查（5 分鐘緩存）
    $cache_key = 'mpu_ollama_available_' . md5($endpoint . $model);
    $cached_result = get_transient($cache_key);

    if ($cached_result !== false) {
        return (bool) $cached_result;
    }

    // ★★★ 改進：根據端點類型使用動態超時時間 ★★★
    $timeout = mpu_get_ollama_timeout($endpoint, 'check');
    $is_remote = mpu_is_remote_endpoint($endpoint);

    // 構建測試 API URL（嘗試多個端點以確保兼容性）
    // 優先使用 /api/version（最輕量），如果失敗則嘗試 /api/tags
    $api_urls = [
        rtrim($endpoint, '/') . '/api/version',
        rtrim($endpoint, '/') . '/api/tags',
    ];

    $is_available = false;
    $last_error = null;

    foreach ($api_urls as $api_url) {
        // 發送輕量級請求檢查服務是否可用（使用動態超時）
        $response = wp_remote_get($api_url, [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'timeout' => $timeout,  // 動態超時：本地 3 秒，遠程 10 秒
        ]);

        if (!is_wp_error($response)) {
            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code === 200) {
                // 服務可用（Ollama 服務正在運行）
                $is_available = true;
                break; // 找到可用的端點，退出循環
            }
        } else {
            // 記錄最後一個錯誤
            $last_error = $response;
        }
    }

    // 如果所有端點都失敗，檢查最後一個錯誤
    if (!$is_available && $last_error !== null) {
        $error_message = $last_error->get_error_message();
        // 連接錯誤表示服務不可用（這已經是 false，但我們記錄錯誤信息）
        // 這裡不需要額外設置，因為 $is_available 已經是 false
    }

    // 緩存結果（5 分鐘）
    set_transient($cache_key, $is_available ? 1 : 0, 5 * MINUTE_IN_SECONDS);

    if (defined('WP_DEBUG') && WP_DEBUG) {
        $connection_type = $is_remote ? '遠程' : '本地';
        error_log("MP Ukagaka - Ollama 服務檢查: " . ($is_available ? '可用' : '不可用') . " ({$connection_type}連接, 端點: {$endpoint}, 模型: {$model}, 超時: {$timeout}秒)");
        if (!$is_available && $last_error !== null) {
            error_log('MP Ukagaka - Ollama 連接錯誤: ' . $last_error->get_error_message());
        }
    }

    return $is_available;
}

/**
 * 使用 LLM 生成隨機對話（取代內建對話）
 * 此函數用於當啟用「使用 LLM 取代內建對話」時，生成不依賴頁面內容的隨機對話
 * 
 * @param string $ukagaka_name 春菜名稱
 * @return string|false 生成的對話內容，失敗時返回 false
 */
function mpu_generate_llm_dialogue($ukagaka_name = 'default_1')
{
    $mpu_opt = mpu_get_option();

    // 檢查是否啟用了「使用 LLM 取代內建對話」
    if (empty($mpu_opt['ollama_replace_dialogue']) || $mpu_opt['ai_provider'] !== 'ollama') {
        return false;
    }

    // 獲取 Ollama 設定
    $endpoint = $mpu_opt['ollama_endpoint'] ?? 'http://localhost:11434';
    $model = $mpu_opt['ollama_model'] ?? 'qwen3:8b';
    $language = $mpu_opt['ai_language'] ?? 'zh-TW';

    // ★★★ 修改：在調用前先檢查 Ollama 服務是否可用 ★★★
    if (!mpu_check_ollama_available($endpoint, $model)) {
        // ★★★ 服務不可用，返回特定錯誤標記，不再回退到內建台詞 ★★★
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('MP Ukagaka - Ollama 服務不可用，返回錯誤提示');
            error_log('MP Ukagaka - 端點: ' . $endpoint . ', 模型: ' . $model);
            error_log('MP Ukagaka - ollama_replace_dialogue: ' . ($mpu_opt['ollama_replace_dialogue'] ? 'true' : 'false'));
            error_log('MP Ukagaka - ai_provider: ' . ($mpu_opt['ai_provider'] ?? 'not set'));
        }
        return 'MPU_OLLAMA_NOT_AVAILABLE';
    }

    // 獲取春菜名稱和人格設定
    $ukagaka_name_display = $mpu_opt['ukagakas'][$ukagaka_name]['name'] ?? '春菜';
    $system_prompt = $mpu_opt['ai_system_prompt'] ?? "你是一個傲嬌的桌面助手「{$ukagaka_name_display}」。你會用簡短、帶點傲嬌的語氣說話。回應請保持在 40 字以內。";

    // 生成隨機對話提示詞（不依賴頁面內容）
    $random_prompts = [
        "說一句隨機的話",
        "隨便說點什麼",
        "說一句話",
        "打個招呼",
        "說點有趣的話",
        "隨便聊聊",
        "說一句日常對話",
    ];
    $user_prompt = $random_prompts[array_rand($random_prompts)];

    // 調用 Ollama API
    $result = mpu_call_ollama_api($endpoint, $model, $system_prompt, $user_prompt, $language);

    if (is_wp_error($result)) {
        // 如果 LLM 調用失敗，返回 false，讓系統使用後備對話
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('LLM Dialogue Generation Failed: ' . $result->get_error_message());
        }
        // 如果調用失敗，清除緩存，讓下次可以重新檢查
        $cache_key = 'mpu_ollama_available_' . md5($endpoint . $model);
        delete_transient($cache_key);
        return false;
    }

    return $result;
}

/**
 * 檢查是否啟用了 LLM 取代內建對話
 * 
 * 注意：此功能獨立於「頁面感知 AI」(ai_enabled)
 * LLM 取代對話只需要：
 * 1. ollama_replace_dialogue 為 true
 * 2. ai_provider 為 'ollama'
 * 
 * @return bool
 */
function mpu_is_llm_replace_dialogue_enabled()
{
    $mpu_opt = mpu_get_option();
    $is_enabled = !empty($mpu_opt['ollama_replace_dialogue']) && $mpu_opt['ai_provider'] === 'ollama';
    
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('MP Ukagaka - mpu_is_llm_replace_dialogue_enabled:');
        error_log('  - ollama_replace_dialogue = ' . (isset($mpu_opt['ollama_replace_dialogue']) && $mpu_opt['ollama_replace_dialogue'] ? 'true' : 'false'));
        error_log('  - ai_provider = ' . ($mpu_opt['ai_provider'] ?? 'not set'));
        error_log('  - 結果 = ' . ($is_enabled ? 'true' : 'false'));
    }
    
    return $is_enabled;
}

/**
 * 獲取 Ollama 設定
 * 
 * @return array|false 設定陣列，未啟用時返回 false
 */
function mpu_get_ollama_settings()
{
    $mpu_opt = mpu_get_option();

    if ($mpu_opt['ai_provider'] !== 'ollama') {
        return false;
    }

    return [
        'endpoint' => $mpu_opt['ollama_endpoint'] ?? 'http://localhost:11434',
        'model' => $mpu_opt['ollama_model'] ?? 'qwen3:8b',
        'replace_dialogue' => !empty($mpu_opt['ollama_replace_dialogue']),
    ];
}
