<?php
/**
 * REST Handler: AI 上下文對話（頁面感知）
 * 根據頁面內容生成 AI 回應
 * 
 * @package MP_Ukagaka
 * @subpackage REST/Chat
 */

if (!defined('ABSPATH')) {
    exit();
}

/**
 * REST 處理器：AI 上下文對話
 */
function mpu_rest_chat_context( WP_REST_Request $request )
{
    // 速率限制（防止濫用）- 5次/分鐘（頁面感知消耗較多 Token）
    $rl = mpu_rest_check_rate_limit('chat_context', 5, 60);
    if ($rl !== null) return $rl;

    $mpu_opt = mpu_get_option();

    // 驗證 AI 是否啟用
    if (empty($mpu_opt["ai_enabled"])) {
        return new WP_Error('rest_error', __('發生未知錯誤，請檢查日誌', 'mp-ukagaka'), ['status' => 400]);
    }

    // 獲取提供商和 API Key
    $provider = mpu_get_current_provider($mpu_opt);
    $api_key  = mpu_get_provider_api_key($provider, $mpu_opt);
    if ($provider !== 'ollama' && empty($api_key)) {
        return new WP_Error('rest_error', __('發生未知錯誤，請檢查日誌', 'mp-ukagaka'), ['status' => 400]);
    }

    // 獲取頁面內容
    $page_title_param = $request->get_param("page_title");
    $page_content_param = $request->get_param("page_content");
    $publish_date_param = $request->get_param("publish_date");

    $page_title = !empty($page_title_param) ? sanitize_text_field(wp_unslash($page_title_param)) : "";
    $page_content = !empty($page_content_param) ? sanitize_textarea_field(wp_unslash($page_content_param)) : "";
    $publish_date = !empty($publish_date_param) ? sanitize_text_field(wp_unslash($publish_date_param)) : "";

    // 限制內容長度，防止過大請求
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
        return new WP_Error('rest_error', __('發生未知錯誤，請檢查日誌', 'mp-ukagaka'), ['status' => 400]);
    }

    $wp_info = mpu_get_wordpress_info();
    $ukagaka_name = $mpu_opt['cur_ukagaka'] ?? 'default_1';
    $ukagaka_display_name = $mpu_opt['ukagakas'][$ukagaka_name]['name'] ?? '偽春菜';
    $language = $mpu_opt["ai_language"] ?? "zh-TW";

    // 獲取時間情境
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

    $system_prompt = mpu_resolve_system_prompt($personality_id, $mpu_opt, $ukagaka_display_name, $variables);

    $user_info = mpu_get_current_user_info();
    $visitor_info = mpu_get_visitor_info_for_llm();

    $is_japanese = (strpos(strtolower($language), 'ja') === 0 || $language === 'ja');

    $prompt_parts = [];

    $prompt_parts[] = mpu_build_user_info_prompt($user_info);

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

    $article_lines = [];
    $article_lines[] = "【記事內容】";
    $article_lines[] = "タイトル：{$page_title}";

    if (!empty($publish_date)) {
        $article_age = '';
        $parsed_timestamp = strtotime($publish_date);
        if ($parsed_timestamp !== false) {
            $now = time();
            $age_seconds = $now - $parsed_timestamp;
            $age_years = floor($age_seconds / (365.25 * 24 * 60 * 60));
            $age_months = floor($age_seconds / (30.44 * 24 * 60 * 60));
            $age_days = floor($age_seconds / (24 * 60 * 60));

            if ($is_japanese) {
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

    $page_aware_instruction = '';
    $is_own_diary = false;
    $special_info = '';

    if (function_exists('mpu_load_personality_dynamic_prompts')) {
        $dynamic_prompts = mpu_load_personality_dynamic_prompts($personality_id);

        if (!empty($dynamic_prompts['diary_title_prefix']) && !empty($page_title)) {
            $diary_prefix = $dynamic_prompts['diary_title_prefix'];
            if (mb_strpos($page_title, $diary_prefix) !== false) {
                $is_own_diary = true;
            }
        }

        if ($is_own_diary) {
            $is_recent = (isset($age_days) && $age_days <= 30);
            
            if ($is_recent && !empty($dynamic_prompts['page_aware_own_diary_recent']) && is_array($dynamic_prompts['page_aware_own_diary_recent'])) {
                $page_aware_instruction = $dynamic_prompts['page_aware_own_diary_recent'][array_rand($dynamic_prompts['page_aware_own_diary_recent'])];
                $special_info = "【特別情報】この記事はあなた自身がつい最近書いた日記です。";
            } elseif (!empty($dynamic_prompts['page_aware_own_diary_past']) && is_array($dynamic_prompts['page_aware_own_diary_past'])) {
                $page_aware_instruction = $dynamic_prompts['page_aware_own_diary_past'][array_rand($dynamic_prompts['page_aware_own_diary_past'])];
                $special_info = "【特別情報】この記事はあなた自身が以前書いた日記です。";
            }
        }
        
        if (empty($page_aware_instruction)) {
            if (wp_rand(1, 100) <= 20 && !empty($dynamic_prompts['page_aware_tsukkomi']) && is_array($dynamic_prompts['page_aware_tsukkomi'])) {
                $page_aware_instruction = $dynamic_prompts['page_aware_tsukkomi'][array_rand($dynamic_prompts['page_aware_tsukkomi'])];
            } elseif (!empty($dynamic_prompts['page_aware']) && is_array($dynamic_prompts['page_aware'])) {
                $page_aware_instruction = $dynamic_prompts['page_aware'][array_rand($dynamic_prompts['page_aware'])];
            }
        }
    }

    if (!empty($special_info)) {
        $prompt_parts[] = $special_info;
    }

    if (!empty($page_aware_instruction)) {
        $prompt_parts[] = "【会話指示】\n" . $page_aware_instruction;
    }

    $user_prompt = implode("\n\n", $prompt_parts);

    $max_tokens = 1000;
    $global_max_tokens = isset($mpu_opt['ai_max_tokens']) ? intval($mpu_opt['ai_max_tokens']) : 1000;
    
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
        return new WP_Error('rest_error', __('發生未知錯誤，請檢查日誌', 'mp-ukagaka'), ['status' => 400]);
    }

    $max_length = 500;
    if (function_exists('mpu_get_personality_max_response_length')) {
        $max_length = mpu_get_personality_max_response_length(null, $ukagaka_name);
    }
    if (mb_strlen($result, 'UTF-8') > $max_length) {
        $result = mb_substr($result, 0, $max_length, 'UTF-8') . '...';
    }

    $emoji = null;
    if (function_exists('mpu_analyze_emoji_from_text') && !empty($result)) {
        $emoji = mpu_analyze_emoji_from_text($result, $personality_id);
    }

    if (function_exists('mpu_record_conversation')) {
        mpu_record_conversation('context');
    }
    if (function_exists('mpu_extract_and_record_topic') && !empty($page_title)) {
        mpu_extract_and_record_topic($page_title);
    }

    return new WP_REST_Response([
        "msg" => $result,
        "emoji" => $emoji
    ], 200);
}
