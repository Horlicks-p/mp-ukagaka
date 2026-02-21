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
    if (!mpu_verify_ajax_nonce()) return;

    // 速率限制（防止濫用）- 5次/分鐘（頁面感知消耗較多 Token）
    mpu_enforce_rate_limit('chat_context', 5, 60);

    $mpu_opt = mpu_get_option();

    // 驗證 AI 是否啟用
    if (empty($mpu_opt["ai_enabled"])) {
        wp_send_json(["error" => "AI 功能未啟用"]);
        return;
    }

    // 獲取提供商和 API Key
    $provider = mpu_get_current_provider($mpu_opt);
    $api_key  = mpu_get_provider_api_key($provider, $mpu_opt);
    if ($provider !== 'ollama' && empty($api_key)) {
        wp_send_json(["error" => ucfirst($provider) . " API Key 未設定"]);
        return;
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
    $personality_id = mpu_resolve_personality_id($ukagaka_name);
    $time_context   = mpu_get_time_context($personality_id);

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

    // 統一系統提示詞解析（修正：原先忽略了 personality 檔案，現在支援模組化 → 舊版 → 後台 → 預設值）
    $system_prompt = mpu_resolve_system_prompt($personality_id, $mpu_opt, $ukagaka_display_name, $variables);

    $user_info = mpu_get_current_user_info();
    $visitor_info = mpu_get_visitor_info_for_llm();

    // 判斷是否為日語（用於決定文案語言）
    $is_japanese = (strpos(strtolower($language), 'ja') === 0 || $language === 'ja');

    // --- [組合 User Prompt] ---
    $prompt_parts = [];

    // 1. 用戶資訊
    $prompt_parts[] = mpu_build_user_info_prompt($user_info);

    // 2. 訪問者情報
    $visitor_lines = [];
    $visitor_lines[] = "【訪問者情報】";
    if (!empty($visitor_info['is_bot']) && $visitor_info['is_bot']) {
        $bot_name = !empty($visitor_info['browser_name']) ? $visitor_info['browser_name'] : '未知のクローラー';
        $visitor_lines[] = "BOT検出：{$bot_name}";
    }
    if (!empty($visitor_info['slimstat_country'])) {
        $country_name = function_exists('mpu_country_code_to_name') ? mpu_country_code_to_name($visitor_info['slimstat_country']) : $visitor_info['slimstat_country'];
        $country_msg = "アクセス元地域：{$country_name}";
        if (!empty($visitor_info['slimstat_city'])) {
            $country_msg .= " {$visitor_info['slimstat_city']}";
        }
        $visitor_lines[] = $country_msg;
    }
    $prompt_parts[] = implode("\n", $visitor_lines);

    // 3. 記事內容
    $article_lines = [];
    $article_lines[] = "【記事內容】";
    $article_lines[] = "タイトル：{$page_title}";

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
        
        $date_msg = "{$date_label}：{$publish_date}";
        if (!empty($article_age)) {
            $date_msg .= " {$article_age}";
        }
        $article_lines[] = $date_msg;
    }

    $article_lines[] = "內容摘要：{$page_content}";
    $prompt_parts[] = implode("\n", $article_lines);

    // ===== 頁面感知專用：會話指示選擇 =====
    // 從 dynamics.json 載入角色專屬的 page_aware 提示詞
    // 提示詞會引導 AI 如何評論文章內容（聚焦於管理人的文章本身）
    $page_aware_instruction = '';
    $is_own_diary = false;
    $special_info = '';

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
                $special_info = "【特別情報】この記事はあなた自身がつい最近書いた日記です。";
            } elseif (!empty($dynamic_prompts['page_aware_own_diary_past']) && is_array($dynamic_prompts['page_aware_own_diary_past'])) {
                $page_aware_instruction = $dynamic_prompts['page_aware_own_diary_past'][array_rand($dynamic_prompts['page_aware_own_diary_past'])];
                // 添加提示讓 AI 知道這是過去的日記
                $special_info = "【特別情報】この記事はあなた自身が以前書いた日記です。";
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

    // 4. 特別情報（如果是日記）
    if (!empty($special_info)) {
        $prompt_parts[] = $special_info;
    }

    // 5. 會話指示
    if (!empty($page_aware_instruction)) {
        $prompt_parts[] = "【会話指示】\n" . $page_aware_instruction;
    }

    // 合併最終 User Prompt
    $user_prompt = implode("\n\n", $prompt_parts);

    // 頁面感知 AI 需要更長的回應來完整表達對文章的看法
    // 獲取最大 Token 數（優先順序：Manifest > 全域設定 > 預設 1000）
    $max_tokens = 1000;
    $global_max_tokens = isset($mpu_opt['ai_max_tokens']) ? intval($mpu_opt['ai_max_tokens']) : 1000;
    
    // 1. 嘗試從 Manifest 獲取
    if (function_exists('mpu_load_personality_manifest')) {
         $manifest = mpu_load_personality_manifest($personality_id);
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
