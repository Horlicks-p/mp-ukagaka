<?php
/**
 * AJAX Handler: AI 上下文對話（頁面感知）
 * 根據頁面內容生成 AI 回應
 * 
 * @package MP_Ukagaka
 * @subpackage AJAX/Chat
 */

if (!defined('ABSPATH')) {
    exit();
}

/**
 * AJAX 處理器：AI 上下文對話
 * 根據頁面內容生成 AI 回應
 */
function mpu_ajax_chat_context()
{
    // 驗證 Nonce（強制）
    if (!isset($_POST['mpu_nonce']) || !wp_verify_nonce($_POST['mpu_nonce'], 'mpu_ajax_nonce')) {
        wp_send_json(["error" => __("安全性驗證失敗", "mp-ukagaka")]);
        return;
    }

    // 速率限制（防止濫用）- 5次/分鐘（頁面感知消耗較多 Token）
    mpu_enforce_rate_limit('chat_context', 5, 60);

    $mpu_opt = mpu_get_option();

    // 驗證 AI 是否啟用
    if (empty($mpu_opt["ai_enabled"])) {
        wp_send_json(["error" => "AI 功能未啟用"]);
        return;
    }

    // 獲取提供商（向後兼容：優先使用 llm_provider，否則使用 ai_provider）
    $provider = isset($mpu_opt["llm_provider"]) ? $mpu_opt["llm_provider"] : (isset($mpu_opt["ai_provider"]) ? $mpu_opt["ai_provider"] : "gemini");
    $api_key_encrypted = "";
    $api_key = "";

    // Ollama 不需要 API Key
    if ($provider !== "ollama") {
        switch ($provider) {
            case "openai":
                // 向後兼容：優先使用新設定鍵，否則使用舊設定鍵
                $api_key_encrypted = $mpu_opt["llm_openai_api_key"] ?? $mpu_opt["openai_api_key"] ?? "";
                break;
            case "claude":
                $api_key_encrypted = $mpu_opt["llm_claude_api_key"] ?? $mpu_opt["claude_api_key"] ?? "";
                break;
            case "gemini":
            default:
                $api_key_encrypted = $mpu_opt["llm_gemini_api_key"] ?? $mpu_opt["ai_api_key"] ?? "";
                break;
        }

        // 解密 API Key（安全性強化）
        $api_key = mpu_decrypt_api_key($api_key_encrypted);

        if (empty($api_key)) {
            wp_send_json(["error" => ucfirst($provider) . " API Key 未設定"]);
            return;
        }
    }

    // 獲取頁面內容
    $page_title = isset($_POST["page_title"]) ? sanitize_text_field(wp_unslash($_POST["page_title"])) : "";
    $page_content = isset($_POST["page_content"]) ? sanitize_textarea_field(wp_unslash($_POST["page_content"])) : "";
    $publish_date = isset($_POST["publish_date"]) ? sanitize_text_field(wp_unslash($_POST["publish_date"])) : "";

    // 限制內容長度，防止過大請求（使用多位元組函數避免 UTF-8 亂碼）
    if (mb_strlen($page_title, 'UTF-8') > 500) {
        $page_title = mb_substr($page_title, 0, 500, 'UTF-8');
    }
    if (mb_strlen($page_content, 'UTF-8') > 5000) {
        $page_content = mb_substr($page_content, 0, 5000, 'UTF-8');
    }
    if (mb_strlen($publish_date, 'UTF-8') > 100) {
        $publish_date = mb_substr($publish_date, 0, 100, 'UTF-8');
    }

    if (empty($page_title) && empty($page_content)) {
        wp_send_json(["error" => "頁面內容為空"]);
        return;
    }

    $wp_info = mpu_get_wordpress_info();
    $ukagaka_name = $mpu_opt['cur_ukagaka'] ?? 'default_1';
    $ukagaka_display_name = $mpu_opt['ukagakas'][$ukagaka_name]['name'] ?? '偽春菜';
    $language = $mpu_opt["ai_language"] ?? "zh-TW";

    // 獲取時間情境（傳入 personality_id 以讀取該角色的專屬日曆）
    $personality_id = function_exists('mpu_get_personality_id_from_ukagaka_name')
        ? mpu_get_personality_id_from_ukagaka_name($ukagaka_name)
        : null;
    $time_context = mpu_get_time_context($personality_id);

    $variables = [
        'ukagaka_display_name' => $ukagaka_display_name,
        'language' => $language,
        'time_context' => $time_context,
        'wp_version' => $wp_info['wp_version'] ?? '',
        'php_version' => $wp_info['php_version'] ?? '',
        'post_count' => $wp_info['post_count'] ?? 0,
        'comment_count' => $wp_info['comment_count'] ?? 0,
        'category_count' => $wp_info['category_count'] ?? 0,
        'tag_count' => $wp_info['tag_count'] ?? 0,
        'days_operating' => $wp_info['days_operating'] ?? 0,
        'theme_name' => $wp_info['theme_name'] ?? '',
        'theme_version' => $wp_info['theme_version'] ?? '',
        'theme_author' => $wp_info['theme_author'] ?? '',
    ];

    $system_prompt = $mpu_opt["ai_system_prompt"] ?? "あなたは「{{ukagaka_display_name}}」というキャラクターです。必ずこのキャラクターとして振る舞い、一人称は「私」を使用してください。回答は日本語で、50文字以内の短い一言で返してください。自分が AI や Qwen だと言わないでください。";
    $system_prompt = mpu_render_prompt_template($system_prompt, $variables);

    $user_info = mpu_get_current_user_info();
    $visitor_info = mpu_get_visitor_info_for_llm();

    // 判斷是否為日語（用於決定文案語言）
    $is_japanese = (strpos(strtolower($language), 'ja') === 0 || $language === 'ja');

    $user_prompt = "【現在のユーザー情報】\n";
    if ($user_info['is_logged_in']) {
        $role_labels = [
            'administrator' => '管理人',
            'editor' => '編集者',
            'author' => '作者',
            'contributor' => '貢献者',
            'subscriber' => '購読者',
        ];
        $role_label = isset($role_labels[$user_info['primary_role']])
            ? $role_labels[$user_info['primary_role']]
            : $user_info['primary_role'];

        $user_prompt .= "ユーザーがログインしています：{$user_info['display_name']} ({$user_info['username']})\n";
        $user_prompt .= "役割：{$role_label}\n";
        if ($user_info['is_admin']) {
            $user_prompt .= "このユーザーはサイト管理人です。\n";
        }
    } else {
        $user_prompt .= "ユーザーがログインしていません（訪問者）。\n";
    }

    $user_prompt .= "\n【訪問者情報】\n";
    if (!empty($visitor_info['is_bot']) && $visitor_info['is_bot']) {
        $bot_name = $visitor_info['browser_name'] ?? '';
        if (empty($bot_name)) {
            $bot_name = '未知のクローラー';
        }
        $user_prompt .= "BOT検出：{$bot_name}\n";
    }
    if (!empty($visitor_info['slimstat_country'])) {
        $user_prompt .= "アクセス元地域：{$visitor_info['slimstat_country']}";
        if (!empty($visitor_info['slimstat_city'])) {
            $user_prompt .= " {$visitor_info['slimstat_city']}";
        }
        $user_prompt .= "\n";
    }

    $user_prompt .= "\n【記事内容】\n";
    $user_prompt .= "タイトル：{$page_title}\n";

    // 添加發布日期資訊（如果有的話）
    if (!empty($publish_date)) {
        // 嘗試解析日期並計算文章年齡
        $article_age = '';
        $parsed_timestamp = strtotime($publish_date);
        if ($parsed_timestamp !== false) {
            $now = time();
            $age_seconds = $now - $parsed_timestamp;
            $age_years = floor($age_seconds / (365.25 * 24 * 60 * 60));
            $age_months = floor($age_seconds / (30.44 * 24 * 60 * 60));
            $age_days = floor($age_seconds / (24 * 60 * 60));

            if ($is_japanese) {
                // 日語文案
                if ($age_years >= 1) {
                    $article_age = "（約{$age_years}年前の記事）";
                } elseif ($age_months >= 1) {
                    $article_age = "（約{$age_months}ヶ月前の記事）";
                } elseif ($age_days >= 1) {
                    $article_age = "（約{$age_days}日前の記事）";
                } else {
                    $article_age = "（今日の記事）";
                }
                $date_label = "公開日";
            } else {
                // 中文文案（預設）
                if ($age_years >= 1) {
                    $article_age = "（約{$age_years}年前的文章）";
                } elseif ($age_months >= 1) {
                    $article_age = "（約{$age_months}個月前的文章）";
                } elseif ($age_days >= 1) {
                    $article_age = "（約{$age_days}天前的文章）";
                } else {
                    $article_age = "（今天的文章）";
                }
                $date_label = "發布日期";
            }
        } else {
            // 如果日期解析失敗，只顯示日期標籤，不顯示年齡
            $date_label = $is_japanese ? "公開日" : "發布日期";
        }
        $user_prompt .= "{$date_label}：{$publish_date}";
        if (!empty($article_age)) {
            $user_prompt .= " {$article_age}";
        }
        $user_prompt .= "\n";
    }

    $user_prompt .= "\n內容摘要：{$page_content}";

    // ===== 頁面感知專用：會話指示選擇 =====
    // 獲取 personality_id（如果還沒有獲取）
    if (empty($personality_id)) {
        if (function_exists('mpu_get_personality_id_from_ukagaka_name')) {
            $personality_id = mpu_get_personality_id_from_ukagaka_name($ukagaka_name);
        }

        // 如果無法從 ukagaka_name 獲取，嘗試使用當前 personality_id
        if (empty($personality_id) && function_exists('mpu_get_current_personality_id')) {
            $personality_id = mpu_get_current_personality_id();
        }
    }

    // ===== 頁面感知專用：會話指示選擇 =====
    // 從 dynamics.json 載入角色專屬的 page_aware 提示詞
    // 提示詞會引導 AI 如何評論文章內容（聚焦於管理人的文章本身）
    $page_aware_instruction = '';
    $is_own_diary = false;

    if (function_exists('mpu_load_personality_dynamic_prompts')) {
        // mpu_load_personality_dynamic_prompts 會在 personality_id 為 null 時自動使用當前 personality_id
        $dynamic_prompts = mpu_load_personality_dynamic_prompts($personality_id);

        // 檢測是否為角色自己寫的日記（通過標題前綴匹配）
        if (!empty($dynamic_prompts['diary_title_prefix']) && !empty($page_title)) {
            $diary_prefix = $dynamic_prompts['diary_title_prefix'];
            if (mb_strpos($page_title, $diary_prefix) !== false) {
                $is_own_diary = true;
            }
        }

        // 選擇會話指示的優先順序：
        // 0. 如果是自己的日記 → 判斷是近期(30天內)還是過去
        if ($is_own_diary) {
            // $age_days 在前面已經計算過 (需確保 publish_date 存在)
            $is_recent = (isset($age_days) && $age_days <= 30);
            
            if ($is_recent && !empty($dynamic_prompts['page_aware_own_diary_recent']) && is_array($dynamic_prompts['page_aware_own_diary_recent'])) {
                $page_aware_instruction = $dynamic_prompts['page_aware_own_diary_recent'][array_rand($dynamic_prompts['page_aware_own_diary_recent'])];
                // 添加提示讓 AI 知道這是最近的日記
                $user_prompt .= "\n\n【特別情報】この記事はあなた自身がつい最近書いた日記です。";
            } elseif (!empty($dynamic_prompts['page_aware_own_diary_past']) && is_array($dynamic_prompts['page_aware_own_diary_past'])) {
                $page_aware_instruction = $dynamic_prompts['page_aware_own_diary_past'][array_rand($dynamic_prompts['page_aware_own_diary_past'])];
                // 添加提示讓 AI 知道這是過去的日記
                $user_prompt .= "\n\n【特別情報】この記事はあなた自身が以前書いた日記です。";
            }
        }
        
        // 1. 如果沒有設定日記特殊的指示（或不是日記），則進入一般流程
        if (empty($page_aware_instruction)) {
            if (wp_rand(1, 100) <= 20 && !empty($dynamic_prompts['page_aware_tsukkomi']) && is_array($dynamic_prompts['page_aware_tsukkomi'])) {
                $page_aware_instruction = $dynamic_prompts['page_aware_tsukkomi'][array_rand($dynamic_prompts['page_aware_tsukkomi'])];
            } elseif (!empty($dynamic_prompts['page_aware']) && is_array($dynamic_prompts['page_aware'])) {
                $page_aware_instruction = $dynamic_prompts['page_aware'][array_rand($dynamic_prompts['page_aware'])];
            }
        }
    }

    // 添加會話指示到 User Prompt
    if (!empty($page_aware_instruction)) {
        $user_prompt .= "\n\n【会話指示】\n" . $page_aware_instruction;
    }

    // 頁面感知 AI 需要更長的回應來完整表達對文章的看法
    // 獲取最大 Token 數（優先順序：Manifest > 全域設定 > 預設 1000）
    $max_tokens = 1000;
    $global_max_tokens = isset($mpu_opt['ai_max_tokens']) ? intval($mpu_opt['ai_max_tokens']) : 1000;
    
    // 1. 嘗試從 Manifest 獲取
    if (function_exists('mpu_load_personality_manifest')) {
         // 先取得 ID
         $pid = null;
         if (function_exists('mpu_get_personality_id_from_ukagaka_name')) {
             $pid = mpu_get_personality_id_from_ukagaka_name($ukagaka_name);
         }
         $manifest = mpu_load_personality_manifest($pid);
         if (isset($manifest['settings']['max_tokens'])) {
             $max_tokens = intval($manifest['settings']['max_tokens']);
         } else {
             $max_tokens = $global_max_tokens;
         }
    } else {
        $max_tokens = $global_max_tokens;
    }
    $result = mpu_call_ai_api(
        $provider,
        $api_key,
        $system_prompt,
        $user_prompt,
        $language,
        $mpu_opt,
        $max_tokens
    );

    if (is_wp_error($result)) {
        wp_send_json(["error" => $result->get_error_message()]);
        return;
    }

    // Rate limiting 現在由 mpu_enforce_rate_limit 自動處理

    // 限制回應長度（從 manifest.json 的 settings.max_response_length 讀取，預設 500）
    $max_length = 500;
    if (function_exists('mpu_get_personality_max_response_length')) {
        $max_length = mpu_get_personality_max_response_length(null, $ukagaka_name);
    }
    if (mb_strlen($result, 'UTF-8') > $max_length) {
        $result = mb_substr($result, 0, $max_length, 'UTF-8') . '...';
    }

    // 分析對話內容的情緒，獲取對應的表情
    $emoji = null;
    if (function_exists('mpu_analyze_emoji_from_text') && !empty($result)) {
        // 獲取 personality_id 以載入角色專屬的表情關鍵字
        $personality_id = null;
        if (function_exists('mpu_get_personality_id_from_ukagaka_name')) {
            $personality_id = mpu_get_personality_id_from_ukagaka_name($ukagaka_name);
        }
        $emoji = mpu_analyze_emoji_from_text($result, $personality_id);
    }

    // ===== 統計：記錄上下文對話和話題 =====
    if (function_exists('mpu_record_conversation')) {
        mpu_record_conversation('context');
    }
    if (function_exists('mpu_extract_and_record_topic') && !empty($page_title)) {
        mpu_extract_and_record_topic($page_title);
    }

    wp_send_json([
        "msg" => $result,
        "emoji" => $emoji  // 表情文件名，如 'happy.png' 或 null
    ]);
}
add_action('wp_ajax_mpu_chat_context', 'mpu_ajax_chat_context');
add_action('wp_ajax_nopriv_mpu_chat_context', 'mpu_ajax_chat_context');
