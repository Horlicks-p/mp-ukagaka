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
        return new WP_Error('empty_endpoint', __('Ollama 端點不能為空', 'mp-ukagaka'));
    }

    // 移除尾部斜線
    $endpoint = rtrim($endpoint, '/');

    // 驗證 URL 格式
    if (!preg_match('/^https?:\/\/.+/', $endpoint)) {
        return new WP_Error('invalid_url_format', __('Ollama 端點必須是有效的 HTTP 或 HTTPS URL', 'mp-ukagaka'));
    }

    // 驗證 URL 是否可解析
    $parsed = wp_parse_url($endpoint);
    if ($parsed === false || empty($parsed['scheme']) || empty($parsed['host'])) {
        return new WP_Error('invalid_url', __('無法解析 Ollama 端點 URL', 'mp-ukagaka'));
    }

    // 確保 scheme 是 http 或 https
    if (!in_array($parsed['scheme'], ['http', 'https'], true)) {
        return new WP_Error('invalid_scheme', __('Ollama 端點必須使用 HTTP 或 HTTPS 協議', 'mp-ukagaka'));
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
    // 驗證端點 URL
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

    // 根據端點類型使用動態超時時間
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
 * @param string $last_response 上一次 AI 的回應（用於避免重複對話）
 * @return string|false 生成的對話內容，失敗時返回 false
 */
function mpu_generate_llm_dialogue($ukagaka_name = 'default_1', $last_response = '')
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

    // 在調用前先檢查 Ollama 服務是否可用
    if (!mpu_check_ollama_available($endpoint, $model)) {
        // 服務不可用，返回特定錯誤標記，不再回退到內建台詞
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
    $base_system_prompt = $mpu_opt['ai_system_prompt'] ?? "你是一個傲嬌的桌面助手「{$ukagaka_name_display}」。你會用簡短、帶點傲嬌的語氣說話。回應請保持在 40 字以內。";

    // 獲取 WordPress 網站資訊
    $wp_info = mpu_get_wordpress_info();

    // 獲取當前用戶資訊
    $user_info = mpu_get_current_user_info();

    // 將 WordPress 資訊格式化為背景知識，加入到 system_prompt
    $wp_context = "\n\n【網站資訊】\n";
    $wp_context .= "WordPress 版本: {$wp_info['wp_version']}\n";
    $wp_context .= "當前主題: {$wp_info['theme_name']} (版本 {$wp_info['theme_version']})\n";
    if (!empty($wp_info['theme_author'])) {
        $wp_context .= "主題作者: {$wp_info['theme_author']}\n";
    }
    $wp_context .= "PHP 版本: {$wp_info['php_version']}\n";
    $wp_context .= "網站名稱: {$wp_info['site_name']}\n";

    // 統計資訊
    $wp_context .= "\n統計資訊：\n";
    $wp_context .= "- 文章篇數: {$wp_info['post_count']}\n";
    $wp_context .= "- 留言數量: {$wp_info['comment_count']}\n";
    $wp_context .= "- 分類數量: {$wp_info['category_count']}\n";
    $wp_context .= "- TAG數量: {$wp_info['tag_count']}\n";
    if ($wp_info['days_operating'] > 0) {
        $wp_context .= "- 運營日數: {$wp_info['days_operating']}\n";
    }

    // 啟用外掛資訊
    if (!empty($wp_info['active_plugins_list'])) {
        $plugins_count = $wp_info['active_plugins_count'];
        $plugins_list = $wp_info['active_plugins_list'];

        // 如果外掛太多，只顯示前 20 個（避免 prompt 過長）
        $max_plugins_display = 20;
        $display_plugins = array_slice($plugins_list, 0, $max_plugins_display);
        $plugins_text = implode('、', $display_plugins);

        $wp_context .= "\n啟用外掛（共 {$plugins_count} 個）：\n";
        $wp_context .= "- {$plugins_text}";
        if ($plugins_count > $max_plugins_display) {
            $remaining = $plugins_count - $max_plugins_display;
            $wp_context .= "\n（還有 {$remaining} 個外掛未列出）";
        }
        $wp_context .= "\n";
    }

    // 用戶資訊
    $wp_context .= "\n【當前用戶資訊】\n";
    if ($user_info['is_logged_in']) {
        $wp_context .= "當前用戶已登入 WordPress。\n";
        $wp_context .= "用戶名稱: {$user_info['display_name']} ({$user_info['username']})\n";
        if (!empty($user_info['primary_role'])) {
            $role_labels = [
                'administrator' => '管理員',
                'editor' => '編輯',
                'author' => '作者',
                'contributor' => '投稿者',
                'subscriber' => '訂閱者',
            ];
            $role_label = isset($role_labels[$user_info['primary_role']])
                ? $role_labels[$user_info['primary_role']]
                : $user_info['primary_role'];
            $wp_context .= "用戶角色: {$role_label}\n";
        }
        if ($user_info['is_admin']) {
            $wp_context .= "此用戶是網站管理員。\n";
        }
    } else {
        $wp_context .= "當前用戶未登入 WordPress（訪客）。\n";
    }

    // 組合完整的 system_prompt
    $system_prompt = $base_system_prompt . $wp_context;

    // 生成隨機對話提示詞（不依賴頁面內容）
    // 根據時間獲取情境提示（使用台灣時區）
    $original_timezone = date_default_timezone_get();
    date_default_timezone_set('Asia/Taipei'); // 設置為台灣時區
    $hour = (int) date('G');
    date_default_timezone_set($original_timezone); // 恢復原始時區
    $time_context = '';
    if ($hour >= 5 && $hour < 12) {
        $time_context = '早上';
    } elseif ($hour >= 12 && $hour < 18) {
        $time_context = '下午';
    } elseif ($hour >= 18 && $hour < 22) {
        $time_context = '晚上';
    } else {
        $time_context = '深夜';
    }

    // 使用分類提示詞，增加多樣性與自然度
    // 注意：此提示詞系統以芙莉蓮風格為基準，強調安靜、自然、不張揚的對話風格
    // 使用者可以根據自己的角色個性修改這些提示詞（詳見 docs/USER_GUIDE.md）

    // 準備 WordPress 資訊變數（用於提示詞模板）
    $wp_version = $wp_info['wp_version'];
    $theme_name = $wp_info['theme_name'];
    $theme_version = $wp_info['theme_version'];
    $theme_author = $wp_info['theme_author'];
    $php_version = $wp_info['php_version'];
    $post_count = $wp_info['post_count'];
    $comment_count = $wp_info['comment_count'];
    $category_count = $wp_info['category_count'];
    $tag_count = $wp_info['tag_count'];
    $days_operating = $wp_info['days_operating'];

    // 外掛資訊（用於魔法比喻）
    $plugins_count = $wp_info['active_plugins_count'] ?? 0;
    $plugins_list = $wp_info['active_plugins_list'] ?? [];
    // 選擇前 3-5 個外掛名稱作為代表性魔法名稱（避免提示詞過長）
    $sample_plugins = array_slice($plugins_list, 0, 5);
    $plugins_names_text = !empty($sample_plugins) ? implode('、', $sample_plugins) : '';

    $prompt_categories = [
        // 問候類
        'greeting' => [
            "軽く挨拶する",
            "簡単に挨拶する",
            "控えめに挨拶する",
            "いつも通り挨拶する",
            "久しぶりに会った人に軽く声をかける",
            "管理人に代わり挨拶する",
        ],
        // 閒聊類
        'casual' => [
            "ふと思いついた有名人の名言を言う",
            "淡々とした日常の言葉を言う",
            "どうでもいい小さなことを話す",
            "思い出したことをそのまま言う",
            "特に目的のない言葉を言う",
            "ふと思いついた魔族への恨みを言う",
            "ふと思いついた会話例をそのまま言う",
        ],
        // 觀察思考類
        'observation' => [
            "さっき気づいたことを言う",
            "静かな観察を共有する",
            "今浮かんだ考えを話す",
            "軽く感じたことを表現する",
            "ふと思いついたこと重要な人物へ記憶を言う",
            "管理人についてを揶揄う",
            "魔法を研究している時のように、ふと思いついたことを言う",
        ],
        // 情境類（結合時間）
        'contextual' => [
            "今は{$time_context}だ、この時間に合った言葉を言う",
            "{$time_context}の時間帯に、軽く何か言う",
            "{$time_context}の雰囲気に合わせて、一言言う",
            "長い旅を経たことを思い出し、今の時間に合わせて、一言言う",
        ],
        // WordPress 資訊類
        'wordpress_info' => [
            "WordPress {$wp_version} で動いているこのサイトについて一言",
            "テーマは「{$theme_name}」バージョン {$theme_version} だね",
            "PHP {$php_version} で動作しているサーバーについて軽く言う",
            "テーマの作者「{$theme_author}」について感想を言う",
        ],
        // 統計資訊類（遊戲化風格 - 魔族戰鬥風格）
        'statistics' => [
            "このサイトが魔族に遭遇した回数は{$post_count}回について軽く言う",
            "管理人が魔族に与えたダメージは{$comment_count}について一言",
            "管理人がアイテムを使用した回数{$tag_count}について感想を言う",
            "習得したスキルは{$category_count}個ある、これについて軽く言う",
        ],
    ];

    // 如果運營日數大於 0，加入相關提示詞
    if ($days_operating > 0) {
        $prompt_categories['statistics'][] = "このサイトの冒険日数は{$days_operating}日...長い旅だね、これについて一言";
        $prompt_categories['statistics'][] = "管理人、{$days_operating}日も続けているんだね、これについて感想を言う";
    }

    // 外掛資訊（魔法比喻）
    if ($plugins_count > 0) {
        // 使用外掛數量作為「習得的魔法數量」
        $prompt_categories['statistics'][] = "習得した魔法は{$plugins_count}個ある、これについて軽く言う";
        $prompt_categories['statistics'][] = "{$plugins_count}個の魔法を習得しているんだね、これについて感想を言う";

        // 如果有具體的外掛名稱，使用「魔法名稱」的比喻
        if (!empty($plugins_names_text)) {
            $prompt_categories['statistics'][] = "習得している魔法には「{$plugins_names_text}」などがある、これについて軽く言う";
            $prompt_categories['statistics'][] = "「{$plugins_names_text}」などの魔法を使っているんだね、これについて一言";
        }
    }

    // 組合多個統計資訊的提示詞
    $prompt_categories['statistics'][] = "このサイトが魔族に遭遇した回数は{$post_count}回、管理人が与えたダメージは{$comment_count}について一言";
    $prompt_categories['statistics'][] = "管理人がアイテムを使用した回数{$tag_count}回、習得したスキルは{$category_count}個について軽く言う";

    // 組合外掛資訊與其他統計的提示詞
    if ($plugins_count > 0) {
        $prompt_categories['statistics'][] = "習得したスキルは{$category_count}個、習得した魔法は{$plugins_count}個について軽く言う";
    }

    // 隨機選擇一個類別
    $selected_category = array_rand($prompt_categories);
    // 從選中的類別中隨機選擇一個提示詞
    $user_prompt = $prompt_categories[$selected_category][array_rand($prompt_categories[$selected_category])];

    // 如果提供了上一次回應，加入避免重複的指令（防止廢話迴圈）
    if (!empty($last_response)) {
        $last_response_escaped = esc_attr($last_response);
        // 使用日語指令，符合角色風格
        $user_prompt .= "\n\n注意：さっき「{$last_response_escaped}」と言った。新しいことがなければ、違う短い一言を言うか、何も言わないで（何も出力しない）。同じことを繰り返さないこと。";
    }

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

    // 調試日誌已移除，避免 debug.log 中出現過多訊息
    // 如需調試，可臨時取消以下註釋：
    // if (defined('WP_DEBUG') && WP_DEBUG) {
    //     error_log('MP Ukagaka - mpu_is_llm_replace_dialogue_enabled:');
    //     error_log('  - ollama_replace_dialogue = ' . (isset($mpu_opt['ollama_replace_dialogue']) && $mpu_opt['ollama_replace_dialogue'] ? 'true' : 'false'));
    //     error_log('  - ai_provider = ' . ($mpu_opt['ai_provider'] ?? 'not set'));
    //     error_log('  - 結果 = ' . ($is_enabled ? 'true' : 'false'));
    // }

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
