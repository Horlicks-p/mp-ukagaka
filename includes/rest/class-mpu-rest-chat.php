<?php

/**
 * REST Controller: Chat Handlers
 *
 * 取代 rest-chat.php 與 rest/chat/*.php。
 * 路由 URL、HTTP method、permission、rate limit key、response 結構、
 * WP_Error code 均與舊 procedural 實作一致。
 *
 * 端點：
 *   POST /mp-ukagaka/v1/chat/context
 *   POST /mp-ukagaka/v1/chat/greet
 *   POST /mp-ukagaka/v1/chat/user
 *   POST /mp-ukagaka/v1/chat/user-stream (SSE)
 *
 * @package MP_Ukagaka
 * @subpackage REST
 */

if (!defined('ABSPATH')) {
    exit();
}

class MPU_REST_Chat extends MPU_REST_Base {

    /**
     * 向 WordPress 註冊所有 Chat 端點。
     * 由 bootstrap.php 集中掛載到 rest_api_init。
     */
    public function register_routes() {
        register_rest_route($this->namespace, '/chat/context', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'chat_context'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($this->namespace, '/chat/greet', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'chat_greet'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($this->namespace, '/chat/user', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'user_chat'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($this->namespace, '/chat/user-stream', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'user_chat_stream'],
            'permission_callback' => '__return_true',
        ]);
    }

    // =========================================================================
    // POST /chat/context — AI 上下文對話（頁面感知）
    // Rate limit: 5 次 / 60 秒（頁面感知消耗較多 Token）
    // =========================================================================

    public function chat_context(WP_REST_Request $request) {
        $rl = $this->rate_limit('chat_context', 5, 60);
        if ($rl !== null) return $rl;

        $mpu_opt = mpu_get_option();

        if (empty($mpu_opt['ai_enabled'])) {
            return $this->fail('rest_error', __('不明なエラーが発生しました。ログを確認してください', 'mp-ukagaka'), 400);
        }

        $provider = mpu_get_current_provider($mpu_opt);
        $api_key  = mpu_get_provider_api_key($provider, $mpu_opt);
        if ($provider !== 'ollama' && empty($api_key)) {
            return $this->fail('rest_error', __('不明なエラーが発生しました。ログを確認してください', 'mp-ukagaka'), 400);
        }

        $page_title_param   = $request->get_param('page_title');
        $page_content_param = $request->get_param('page_content');
        $publish_date_param = $request->get_param('publish_date');

        $page_title   = !empty($page_title_param)   ? sanitize_text_field(wp_unslash($page_title_param))       : '';
        $page_content = !empty($page_content_param) ? sanitize_textarea_field(wp_unslash($page_content_param)) : '';
        $publish_date = !empty($publish_date_param) ? sanitize_text_field(wp_unslash($publish_date_param))      : '';

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
            return $this->fail('rest_error', __('不明なエラーが発生しました。ログを確認してください', 'mp-ukagaka'), 400);
        }

        $wp_info              = mpu_get_wordpress_info();
        $ukagaka_name         = $mpu_opt['cur_ukagaka'] ?? 'default_1';
        $ukagaka_display_name = $mpu_opt['ukagakas'][$ukagaka_name]['name'] ?? '偽春菜';
        $language             = $mpu_opt['ai_language'] ?? 'zh-TW';

        $personality_id = mpu_resolve_personality_id($ukagaka_name);
        $time_context   = mpu_get_time_context($personality_id);

        $variables = [
            'ukagaka_display_name' => $ukagaka_display_name,
            'language'             => $language,
            'time_context'         => $time_context,
            'wp_version'           => $wp_info['wp_version'] ?? '',
            'php_version'          => $wp_info['php_version'] ?? '',
            'post_count'           => $wp_info['post_count'] ?? 0,
            'comment_count'        => $wp_info['comment_count'] ?? 0,
            'category_count'       => $wp_info['category_count'] ?? 0,
            'tag_count'            => $wp_info['tag_count'] ?? 0,
            'days_operating'       => $wp_info['days_operating'] ?? 0,
            'theme_name'           => $wp_info['theme_name'] ?? '',
            'theme_version'        => $wp_info['theme_version'] ?? '',
            'theme_author'         => $wp_info['theme_author'] ?? '',
        ];

        $system_prompt = mpu_resolve_system_prompt($personality_id, $mpu_opt, $ukagaka_display_name, $variables);

        $user_info    = mpu_get_current_user_info();
        $visitor_info = mpu_get_visitor_info_for_llm();

        $is_japanese = (strpos(strtolower($language), 'ja') === 0 || $language === 'ja');

        $prompt_parts   = [];
        $prompt_parts[] = mpu_build_user_info_prompt($user_info);

        $visitor_lines   = [];
        $visitor_lines[] = '【訪問者情報】';
        if (!empty($visitor_info['is_bot']) && $visitor_info['is_bot']) {
            $bot_name        = !empty($visitor_info['browser_name']) ? $visitor_info['browser_name'] : '未知のクローラー';
            $visitor_lines[] = "BOT検出：{$bot_name}";
        }
        if (!empty($visitor_info['slimstat_country'])) {
            $country_name    = function_exists('mpu_country_code_to_name') ? mpu_country_code_to_name($visitor_info['slimstat_country']) : $visitor_info['slimstat_country'];
            $country_msg     = "アクセス元地域：{$country_name}";
            if (!empty($visitor_info['slimstat_city'])) {
                $country_msg .= " {$visitor_info['slimstat_city']}";
            }
            $visitor_lines[] = $country_msg;
        }
        $prompt_parts[] = implode("\n", $visitor_lines);

        $article_lines   = [];
        $article_lines[] = '【記事內容】';
        $article_lines[] = "タイトル：{$page_title}";

        $age_days = null;
        if (!empty($publish_date)) {
            $article_age      = '';
            $parsed_timestamp = strtotime($publish_date);
            if ($parsed_timestamp !== false) {
                $now        = time();
                $age_seconds = $now - $parsed_timestamp;
                $age_years  = floor($age_seconds / (365.25 * 24 * 60 * 60));
                $age_months = floor($age_seconds / (30.44 * 24 * 60 * 60));
                $age_days   = floor($age_seconds / (24 * 60 * 60));

                if ($is_japanese) {
                    if ($age_years >= 1) {
                        $article_age = "（約{$age_years}年前の記事）";
                    } elseif ($age_months >= 1) {
                        $article_age = "（約{$age_months}ヶ月前の記事）";
                    } elseif ($age_days >= 1) {
                        $article_age = "（約{$age_days}日前の記事）";
                    } else {
                        $article_age = '（今日の記事）';
                    }
                    $date_label = '公開日';
                } else {
                    if ($age_years >= 1) {
                        $article_age = "（約{$age_years}年前的文章）";
                    } elseif ($age_months >= 1) {
                        $article_age = "（約{$age_months}個月前的文章）";
                    } elseif ($age_days >= 1) {
                        $article_age = "（約{$age_days}天前的文章）";
                    } else {
                        $article_age = '（今天的文章）';
                    }
                    $date_label = '發布日期';
                }
            } else {
                $date_label = $is_japanese ? '公開日' : '發布日期';
            }

            $date_msg = "{$date_label}：{$publish_date}";
            if (!empty($article_age)) {
                $date_msg .= " {$article_age}";
            }
            $article_lines[] = $date_msg;
        }

        $article_lines[] = "內容摘要：{$page_content}";
        $prompt_parts[]  = implode("\n", $article_lines);

        $page_aware_instruction = '';
        $is_own_diary           = false;
        $special_info           = '';

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
                    $special_info           = '【特別情報】この記事はあなた自身がつい最近書いた日記です。';
                } elseif (!empty($dynamic_prompts['page_aware_own_diary_past']) && is_array($dynamic_prompts['page_aware_own_diary_past'])) {
                    $page_aware_instruction = $dynamic_prompts['page_aware_own_diary_past'][array_rand($dynamic_prompts['page_aware_own_diary_past'])];
                    $special_info           = '【特別情報】この記事はあなた自身が以前書いた日記です。';
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

        $max_tokens        = 1000;
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
            return $this->fail('rest_error', __('不明なエラーが発生しました。ログを確認してください', 'mp-ukagaka'), 400);
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

        // [Fix] 點擊裝飾品/身體後的頁面感知 AI 會把回覆 push 到前端歷史，
        // 但後端從未為此寫入 checksum，導致下一輪 chat/user verify 必定 400。
        // 因此在 chat/context 成功後，也同步寫入 checksum。
        $chat_session_id_param = $request->get_param('session_id') ?: $request->get_param('chat_session_id');
        $chat_session_id = function_exists('mpu_chat_integrity_normalize_session_id')
            ? mpu_chat_integrity_normalize_session_id($chat_session_id_param)
            : '';

        if (!empty($chat_session_id) && function_exists('mpu_chat_integrity_store_history') && !connection_aborted()) {
            // 讀取前端送來的歷史（若有），解碼後加上本次 assistant 回覆，再寫入 checksum
            $prior_history = [];
            $history_param = $request->get_param('history');
            if (!empty($history_param)) {
                $decoded = is_string($history_param) ? json_decode(wp_unslash($history_param), true) : (array) $history_param;
                if (is_array($decoded)) {
                    foreach ($decoded as $msg) {
                        if (is_array($msg) && isset($msg['role'], $msg['content'])) {
                            $role    = ($msg['role'] === 'user') ? 'user' : 'assistant';
                            $content = sanitize_textarea_field(wp_unslash($msg['content']));
                            if ($content !== '') {
                                $type = isset($msg['type']) && in_array($msg['type'], ['chat', 'synthetic', 'auto_talk', 'greet', 'context', 'event', 'touch_decoration', 'touch_zone'], true)
                                    ? (string) $msg['type'] : 'chat';
                                $prior_history[] = ['role' => $role, 'content' => $content, 'type' => $type];
                            }
                        }
                    }
                }
            }
            $prior_history[] = ['role' => 'assistant', 'content' => sanitize_textarea_field($result), 'type' => 'context'];
            mpu_chat_integrity_store_history(
                $chat_session_id,
                mpu_chat_integrity_slice_for_store($prior_history, 10)
            );
        }

        return $this->ok(['msg' => $result, 'emoji' => $emoji]);
    }


    // =========================================================================
    // POST /chat/greet — 首次訪客問候
    // Rate limit: 10 次 / 60 秒
    // =========================================================================

    public function chat_greet(WP_REST_Request $request) {
        $rl = $this->rate_limit('chat_greet', 10, 60);
        if ($rl !== null) return $rl;

        $mpu_opt = mpu_get_option();

        if (empty($mpu_opt['ai_enabled'])) {
            return $this->fail('rest_error', __('不明なエラーが発生しました。ログを確認してください', 'mp-ukagaka'), 400);
        }
        if (empty($mpu_opt['ai_greet_first_visit'])) {
            return $this->fail('rest_error', __('不明なエラーが発生しました。ログを確認してください', 'mp-ukagaka'), 400);
        }

        $provider = mpu_get_current_provider($mpu_opt);
        $api_key  = mpu_get_provider_api_key($provider, $mpu_opt);
        if ($provider !== 'ollama' && empty($api_key)) {
            return $this->fail('rest_error', __('不明なエラーが発生しました。ログを確認してください', 'mp-ukagaka'), 400);
        }

        $referrer_param      = $request->get_param('referrer');
        $referrer_host_param = $request->get_param('referrer_host');
        $search_engine_param = $request->get_param('search_engine');
        $is_direct_param     = $request->get_param('is_direct');
        $country_param       = $request->get_param('country');
        $city_param          = $request->get_param('city');

        $referrer      = !empty($referrer_param)      ? esc_url_raw(wp_unslash($referrer_param))             : '';
        $referrer_host = !empty($referrer_host_param) ? sanitize_text_field(wp_unslash($referrer_host_param)) : '';
        $search_engine = !empty($search_engine_param) ? sanitize_text_field(wp_unslash($search_engine_param)) : '';
        $is_direct     = ($is_direct_param === true || $is_direct_param === 'true');
        $country       = !empty($country_param)       ? sanitize_text_field(wp_unslash($country_param))       : '';
        $city          = !empty($city_param)          ? sanitize_text_field(wp_unslash($city_param))           : '';

        if (mb_strlen($referrer, 'UTF-8') > 500) {
            $referrer = mb_substr($referrer, 0, 500, 'UTF-8');
        }
        if (mb_strlen($referrer_host, 'UTF-8') > 255) {
            $referrer_host = mb_substr($referrer_host, 0, 255, 'UTF-8');
        }
        if (mb_strlen($country, 'UTF-8') > 10) {
            $country = mb_substr($country, 0, 10, 'UTF-8');
        }
        if (mb_strlen($city, 'UTF-8') > 100) {
            $city = mb_substr($city, 0, 100, 'UTF-8');
        }

        $wp_info              = mpu_get_wordpress_info();
        $ukagaka_name         = $mpu_opt['cur_ukagaka'] ?? 'default_1';
        $ukagaka_display_name = $mpu_opt['ukagakas'][$ukagaka_name]['name'] ?? '偽春菜';
        $language             = $mpu_opt['ai_language'] ?? 'zh-TW';

        $personality_id = mpu_resolve_personality_id($ukagaka_name);
        $time_context   = mpu_get_time_context($personality_id);

        $variables = [
            'ukagaka_display_name' => $ukagaka_display_name,
            'language'             => $language,
            'time_context'         => $time_context,
            'wp_version'           => $wp_info['wp_version'] ?? '',
            'php_version'          => $wp_info['php_version'] ?? '',
            'post_count'           => $wp_info['post_count'] ?? 0,
            'comment_count'        => $wp_info['comment_count'] ?? 0,
            'category_count'       => $wp_info['category_count'] ?? 0,
            'tag_count'            => $wp_info['tag_count'] ?? 0,
            'days_operating'       => $wp_info['days_operating'] ?? 0,
            'theme_name'           => $wp_info['theme_name'] ?? '',
            'theme_version'        => $wp_info['theme_version'] ?? '',
            'theme_author'         => $wp_info['theme_author'] ?? '',
        ];

        $system_prompt = mpu_resolve_system_prompt($personality_id, $mpu_opt, $ukagaka_display_name, $variables);

        $user_info      = mpu_get_current_user_info();
        $prompt_parts   = [];
        $prompt_parts[] = mpu_build_user_info_prompt($user_info);

        $visitor_lines   = [];
        $visitor_lines[] = '【訪問者的アクセス元】';
        $visitor_lines[] = '訪問者は初めての訪問です。';

        if ($is_direct) {
            $visitor_lines[] = '訪問者は直接URLを入力したり、ブックマークから訪問しました（參照元のウェブページはありません）。';
        } elseif (!empty($search_engine)) {
            $visitor_lines[] = "訪問者は検索エンジン「{$search_engine}」から訪問しました。";
        } elseif (!empty($referrer_host)) {
            $msg = "訪問者はウェブ網站「{$referrer_host}」から訪問しました。";
            if (!empty($referrer)) {
                $msg .= "（{$referrer}）";
            }
            $msg .= '。';
            $visitor_lines[] = $msg;
        } else {
            $visitor_lines[] = '訪問者的アクセス元是不明です。';
        }

        if (!empty($country)) {
            $country_name    = function_exists('mpu_country_code_to_name') ? mpu_country_code_to_name($country) : $country;
            $msg             = "訪問者は「{$country_name}」から來ました";
            if (!empty($city)) {
                $msg .= "の「{$city}」";
            }
            $msg            .= '。';
            $visitor_lines[] = $msg;
        }
        $prompt_parts[] = implode("\n", $visitor_lines);

        $greet_instruction = '';

        if (function_exists('mpu_load_personality_dynamic_prompts')) {
            $dynamic_prompts = mpu_load_personality_dynamic_prompts($personality_id);
            if (!empty($dynamic_prompts['greet_first_visit']) && is_array($dynamic_prompts['greet_first_visit'])) {
                $greet_instruction = $dynamic_prompts['greet_first_visit'][array_rand($dynamic_prompts['greet_first_visit'])];
            }
        }

        if (empty($greet_instruction) && !empty($mpu_opt['ai_greet_prompt'])) {
            $greet_instruction = mpu_render_prompt_template($mpu_opt['ai_greet_prompt'], $variables);
        }

        if (empty($greet_instruction)) {
            $greet_instruction = '初回訪問者的アクセス元に軽く觸れながら、淡々と短く挨拶する。敬語は使わず常體で話す';
        }

        $prompt_parts[] = "【会話指示】\n" . $greet_instruction;
        $prompt_parts[] = "【回応ルール】\n淡々とした常體で、30-150文字で挨拶すること。";

        $user_prompt = implode("\n\n", $prompt_parts);

        $max_tokens = 800;
        if (function_exists('mpu_get_personality_max_tokens')) {
            $max_tokens = mpu_get_personality_max_tokens(null, $ukagaka_name);
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
            return $this->fail('rest_error', __('不明なエラーが発生しました。ログを確認してください', 'mp-ukagaka'), 400);
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
            mpu_record_conversation('greeting');
        }

        // [Fix] chat/greet 也會 push assistant 到前端 mpuChatHistory，但後端未寫 checksum
        // 導致下一輪 chat/user verify 400。
        $chat_session_id_param = $request->get_param('session_id') ?: $request->get_param('chat_session_id');
        $chat_session_id = function_exists('mpu_chat_integrity_normalize_session_id')
            ? mpu_chat_integrity_normalize_session_id($chat_session_id_param)
            : '';

        if (!empty($chat_session_id) && function_exists('mpu_chat_integrity_store_history') && !connection_aborted()) {
            $prior_history = [];
            $history_param = $request->get_param('history');
            if (!empty($history_param)) {
                $decoded = is_string($history_param) ? json_decode(wp_unslash($history_param), true) : (array) $history_param;
                if (is_array($decoded)) {
                    foreach ($decoded as $msg) {
                        if (is_array($msg) && isset($msg['role'], $msg['content'])) {
                            $role    = ($msg['role'] === 'user') ? 'user' : 'assistant';
                            $content = sanitize_textarea_field(wp_unslash($msg['content']));
                            if ($content !== '') {
                                $type = isset($msg['type']) && in_array($msg['type'], ['chat', 'synthetic', 'auto_talk', 'greet', 'context', 'event', 'touch_decoration', 'touch_zone'], true)
                                    ? (string) $msg['type'] : 'chat';
                                $prior_history[] = ['role' => $role, 'content' => $content, 'type' => $type];
                            }
                        }
                    }
                }
            }
            $prior_history[] = ['role' => 'assistant', 'content' => sanitize_textarea_field($result), 'type' => 'greet'];
            mpu_chat_integrity_store_history(
                $chat_session_id,
                mpu_chat_integrity_slice_for_store($prior_history, 10)
            );
        }

        return $this->ok(['msg' => $result, 'emoji' => $emoji]);
    }


    // =========================================================================
    // POST /chat/user — 用戶互動對話（多輪 + Abilities/MCP）
    // Rate limit: 30 次 / 60 秒
    // =========================================================================

    /**
     * 準備用戶對話所需的參數（同步與串流共用）
     * 
     * @param WP_REST_Request $request
     * @return array|WP_Error|WP_REST_Response
     */
    protected function prepare_user_chat_args(WP_REST_Request $request) {
        $rl = $this->rate_limit('user_chat', 30, 60);
        // 如果 rate_limit 返回 Response（報錯時），直接傳回，避免呼叫端誤用為陣列
        if ($rl !== null) return $rl;

        $mpu_opt = mpu_get_option();
        $chat_session_id = mpu_chat_integrity_normalize_session_id(
            $request->get_param('session_id') ?: $request->get_param('chat_session_id')
        );

        $message_param = $request->get_param('message');
        $user_message  = !empty($message_param) ? sanitize_text_field(wp_unslash($message_param)) : '';
        if (empty($user_message)) {
            return $this->fail('rest_error', __('不明なエラーが発生しました。ログを確認してください', 'mp-ukagaka'), 400);
        }

        if (mb_strlen($user_message, 'UTF-8') > 500) {
            $user_message = mb_substr($user_message, 0, 500, 'UTF-8');
        }

        // [Debug] MCP Tool Diagnostics（僅管理員可用）
        if (trim($user_message) === '/debug_mcp') {
            if (current_user_can('manage_options')) {
                return ['is_debug_mcp' => true, 'user_message' => $user_message];
            }
            // 非管理員：不標記 is_debug_mcp，讓訊息流入一般 AI 路徑，
            // 由 visitor_rejection 提示詞引導 AI 以角色口吻拒絕。
        }

        $needs_stats       = false;
        $needs_system_info = false;
        $needs_plugin_info = false;
        $needs_theme_info  = false;

        $stats_keywords = [
            'コメント' => 'comment', '留言' => 'comment', '評論' => 'comment', 'comment' => 'comment', 'comments' => 'comment',
            '文章' => 'post', 'post' => 'post', 'posts' => 'post', '記事' => 'post', 'article' => 'post',
            '分類' => 'category', 'category' => 'category', 'categories' => 'category', 'カテゴリ' => 'category',
            '標籤' => 'tag', 'tag' => 'tag', 'tags' => 'tag', 'タグ' => 'tag',
            '運營' => 'days', '運営' => 'days', '天數' => 'days', 'days' => 'days', '開站' => 'days',
            '網站' => 'site', 'サイト' => 'site', 'site' => 'site', 'website' => 'site',
            '統計' => 'stats', 'stats' => 'stats', '數量' => 'stats', '個數' => 'stats', '有多少' => 'stats', '幾個' => 'stats',
        ];
        $system_keywords = [
            'php' => 'php', 'php版本' => 'php', 'php version' => 'php', 'phpバージョン' => 'php',
            'wordpress' => 'wp', 'wp' => 'wp', 'wp版本' => 'wp', 'wp version' => 'wp', 'wordpress版本' => 'wp', 'wordpress version' => 'wp', 'ワードプレス' => 'wp', 'ワードプレスのバージョン' => 'wp',
        ];
        $plugin_keywords = [
            '外掛' => 'plugin', '插件' => 'plugin', 'plugin' => 'plugin', 'plugins' => 'plugin', 'プラグイン' => 'plugin', '外掛數量' => 'plugin', '插件數量' => 'plugin', 'plugin count' => 'plugin', 'プラグイン数' => 'plugin',
        ];
        $theme_keywords = [
            '主題' => 'theme', 'theme' => 'theme', 'テーマ' => 'theme', '主題名稱' => 'theme', 'theme name' => 'theme', 'テーマ名' => 'theme', '主題作者' => 'theme', 'theme author' => 'theme', 'テーマ作者' => 'theme',
        ];

        $user_message_lower = mb_strtolower($user_message, 'UTF-8');

        foreach ([
            'needs_stats'       => $stats_keywords,
            'needs_system_info' => $system_keywords,
            'needs_plugin_info' => $plugin_keywords,
            'needs_theme_info'  => $theme_keywords,
        ] as $flag => $keywords) {
            foreach ($keywords as $keyword => $type) {
                if (mb_strpos($user_message_lower, mb_strtolower($keyword, 'UTF-8')) !== false) {
                    $$flag = true;
                    break;
                }
            }
        }

        $chat_history  = [];
        $history_param = $request->get_param('history');
        if (!empty($history_param)) {
            if (is_string($history_param)) {
                $decoded_history = json_decode(wp_unslash($history_param), true);
            } else {
                $decoded_history = (array) $history_param;
            }

            if (json_last_error() === JSON_ERROR_NONE || is_array($decoded_history)) {
                // [Fix] 先保留完整解碼後的歷史，以便進行正規化
                $chat_history = $decoded_history;
            }
        }

        // 允許的 type 白名單（type 欄位用於區分真實對話與 synthetic/auto-talk/touch 等）
        $allowed_types = ['chat', 'synthetic', 'auto_talk', 'greet', 'context',
                          'event', 'touch_decoration', 'touch_zone'];

        $valid_history = [];
        foreach ($chat_history as $msg) {
            if (is_array($msg) && isset($msg['role'], $msg['content'])) {
                $role    = ($msg['role'] === 'user') ? 'user' : 'assistant';

                // [Fix] 對於對話歷史（尤其是包含 MCP 結果的長文本），使用 sanitize_textarea_field
                // 這樣能保留換行符，確保與後端儲存時的內容完全一致，防止 Checksum 失敗。
                $content = sanitize_textarea_field(wp_unslash($msg['content']));

                // 保留 type 欄位，用於 checksum filter 與 LLM context 區分
                $type = isset($msg['type']) && in_array($msg['type'], $allowed_types, true)
                        ? (string) $msg['type'] : 'chat';

                if (!empty(trim($content))) {
                    $valid_history[] = ['role' => $role, 'content' => $content, 'type' => $type];
                }
            }
        }

        // [Fix] 修正正規化邏輯：必須在 array_slice 之前進行。
        // 否則當窗口滑動時，第一筆 assistant 訊息會因前面的 user 訊息被 slice 掉而變成「孤立」訊息被濾除，
        // 導致下一輪 Checksum 驗證失敗。
        // synthetic user（type="synthetic"）的 role 是 "user"，可正常錨定後續的 assistant。
        $normalized_history = [];
        $previous_role      = '';
        foreach ($valid_history as $message) {
            if ($message['role'] === 'assistant' && $previous_role !== 'user') {
                continue;
            }

            $normalized_history[] = $message;
            $previous_role        = $message['role'];
        }

        // 正規化後取最後 20 筆（synthetic+assistant 各佔一則，20 entries = 10 個互動事件）
        $chat_history = array_slice($normalized_history, -20);

        if (!empty($chat_session_id) && function_exists('mpu_chat_integrity_verify_history')) {
            $history_for_verify = mpu_chat_integrity_slice_for_store($chat_history, 10);
            $integrity_check = mpu_chat_integrity_verify_history($chat_session_id, $history_for_verify);
            if (is_wp_error($integrity_check)) {
                return $this->fail('rest_error', $integrity_check->get_error_message(), 400);
            }
        }

        $page_title_param   = $request->get_param('page_title');
        $page_content_param = $request->get_param('page_content');
        $page_title         = !empty($page_title_param)   ? sanitize_text_field(wp_unslash($page_title_param))       : '';
        $page_content       = !empty($page_content_param) ? sanitize_textarea_field(wp_unslash($page_content_param)) : '';

        if (mb_strlen($page_title, 'UTF-8') > 200) {
            $page_title = mb_substr($page_title, 0, 200, 'UTF-8');
        }
        if (mb_strlen($page_content, 'UTF-8') > 2000) {
            $page_content = mb_substr($page_content, 0, 2000, 'UTF-8');
        }

        $provider = mpu_get_current_provider($mpu_opt);
        $api_key  = mpu_get_provider_api_key($provider, $mpu_opt);
        if ($provider !== 'ollama' && empty($api_key)) {
            return $this->fail('rest_error', __('不明なエラーが発生しました。ログを確認してください', 'mp-ukagaka'), 400);
        }

        $ukagaka_name         = $mpu_opt['cur_ukagaka'] ?? 'default_1';
        $ukagaka_display_name = $mpu_opt['ukagakas'][$ukagaka_name]['name'] ?? '偽春菜';
        $language             = $mpu_opt['ai_language'] ?? 'zh-TW';

        $personality_id = mpu_resolve_personality_id($ukagaka_name);
        $time_context   = mpu_get_time_context($personality_id);

        $wp_info   = mpu_get_wordpress_info();
        $variables = [
            'ukagaka_display_name'  => $ukagaka_display_name,
            'language'              => $language,
            'time_context'          => $time_context,
            'wp_version'            => $wp_info['wp_version'] ?? '',
            'php_version'           => $wp_info['php_version'] ?? '',
            'theme_name'            => $wp_info['theme_name'] ?? '',
            'theme_version'         => $wp_info['theme_version'] ?? '',
            'theme_author'          => $wp_info['theme_author'] ?? '',
            'is_child_theme'        => $wp_info['is_child_theme'] ?? false,
            'parent_theme'          => $wp_info['parent_theme'] ?? '',
            'is_block_theme'        => $wp_info['is_block_theme'] ?? false,
            'site_name'             => $wp_info['site_name'] ?? '',
            'site_description'      => $wp_info['site_description'] ?? '',
            'is_multisite'          => $wp_info['is_multisite'] ?? false,
            'active_plugins_count'  => $wp_info['active_plugins_count'] ?? 0,
            'active_plugins_list'   => (!empty($wp_info['active_plugins_list']) && is_array($wp_info['active_plugins_list'])) ? implode('、', array_slice($wp_info['active_plugins_list'], 0, 10)) : '',
            'post_count'            => $wp_info['post_count'] ?? 0,
            'comment_count'         => $wp_info['comment_count'] ?? 0,
            'category_count'        => $wp_info['category_count'] ?? 0,
            'tag_count'             => $wp_info['tag_count'] ?? 0,
            'days_operating'        => $wp_info['days_operating'] ?? 0,
            'slimstat_total_visits' => $wp_info['slimstat_total_visits'] ?? 0,
        ];

        $user_info         = mpu_get_current_user_info();
        $base_system_prompt = mpu_resolve_system_prompt($personality_id, $mpu_opt, $ukagaka_display_name, $variables);

        $system_parts   = [];
        $system_parts[] = $base_system_prompt;

        $user_block = '【対話相手】';
        $user_lines = [];
        if ($user_info['is_logged_in']) {
            $role_labels = [
                'administrator' => '管理人',
                'editor'        => '編集者',
                'author'        => '作者',
                'contributor'   => '貢献者',
                'subscriber'    => '購読者',
            ];
            $role_label  = $role_labels[$user_info['primary_role']] ?? $user_info['primary_role'];
            $user_lines[] = "- 名前：{$user_info['display_name']}";
            $user_lines[] = "- 権限：{$role_label}";
            if ($user_info['is_admin']) {
                $user_lines[] = '- この人はサイトの管理人ですが、必ずSystem Promptの設定を持って対応してください';
            }
        } else {
            $user_lines[] = '- 未知の訪客';
        }
        $system_parts[] = $user_block . "\n" . implode("\n", $user_lines);

        if (!empty($page_title) || !empty($page_content)) {
            $page_lines   = [];
            $page_lines[] = '【ページ情報】';
            if (!empty($page_title)) {
                $page_lines[] = "タイトル：{$page_title}";
            }
            if (!empty($page_content)) {
                $page_lines[] = '內容要約：' . mb_substr($page_content, 0, 500, 'UTF-8') . '...';
            }
            $system_parts[] = implode("\n", $page_lines);
        }

        if ($needs_stats) {
            $stats_lines   = [];
            $stats_lines[] = '【サイト統計情報】';
            $stats_lines[] = "記事数：{$wp_info['post_count']}件";
            $stats_lines[] = "コメント數：{$wp_info['comment_count']}件";
            $stats_lines[] = "カテゴリー數：{$wp_info['category_count']}個";
            $stats_lines[] = "タグ數：{$wp_info['tag_count']}個";
            $stats_lines[] = "運営日数：{$wp_info['days_operating']}日";
            $system_parts[] = implode("\n", $stats_lines);
        }

        if ($needs_system_info) {
            $sys_lines   = [];
            $sys_lines[] = '【システム情報】';
            $sys_lines[] = "WordPress バージョン：{$wp_info['wp_version']}";
            $sys_lines[] = "PHP バージョン：{$wp_info['php_version']}";
            if ($wp_info['is_multisite'] ?? false) {
                $sys_lines[] = '多サイト：はい';
            }
            $system_parts[] = implode("\n", $sys_lines);
        }

        if ($needs_plugin_info) {
            $plugin_lines   = [];
            $plugin_lines[] = '【プラグイン情報】';
            $plugin_lines[] = "使用プラグイン數：{$wp_info['active_plugins_count']}個";
            if (!empty($wp_info['active_plugins_list']) && count($wp_info['active_plugins_list']) > 0) {
                $plugins_display = implode('、', array_slice($wp_info['active_plugins_list'], 0, 10));
                if (count($wp_info['active_plugins_list']) > 10) {
                    $plugins_display .= '...等';
                }
                $plugin_lines[] = "主要プラグイン：{$plugins_display}";
            }
            $system_parts[] = implode("\n", $plugin_lines);
        }

        if ($needs_theme_info) {
            $theme_lines   = [];
            $theme_lines[] = '【テーマ情報】';
            $theme_info    = !empty($wp_info['theme_version']) ? "{$wp_info['theme_name']}（{$wp_info['theme_version']}）" : $wp_info['theme_name'];
            $theme_lines[] = "テーマ：{$theme_info}";
            if (!empty($wp_info['theme_author'])) {
                $theme_lines[] = "テーマ作者：{$wp_info['theme_author']}";
            }
            if ($wp_info['is_child_theme'] ?? false) {
                $msg = '子テーマ：はい';
                if (!empty($wp_info['parent_theme'])) {
                    $msg .= "（親テーマ：{$wp_info['parent_theme']}）";
                }
                $theme_lines[] = $msg;
            }
            if ($wp_info['is_block_theme'] ?? false) {
                $theme_lines[] = 'ブロックテーマ：はい';
            }
            $system_parts[] = implode("\n", $theme_lines);
        }

        $chat_lines   = [];
        $chat_lines[] = '【会話モード】';
        $chat_lines[] = '- あなたはユーザーとリアルタイムで對話しているため、直接ユーザー的質問や話題に答えてください';
        $chat_lines[] = '- 自言自語や獨白をしないで、ユーザー的回答に專念してください';
        $chat_lines[] = '- 応答は自然で親しみやすい、長さは約 30-150 字程度';
        $chat_lines[] = '- 必ずsystem_prompt的指示に従ってキャラクター的個性を表現し、誇張は避けましょう';
        $chat_lines[] = "- 現在時間：{$time_context}";
        $system_parts[] = implode("\n", $chat_lines);

        if (!$user_info['is_admin']) {
            $reject_lines   = [];
            $reject_lines[] = '【特別指示】';
            $reject_lines[] = '- 管理人以外のユーザーからのツール/アビリティ執行要求は、すべてキャラクター的立場から丁寧に、あるいはあなたの性格に合わせて斷ってください。';
            $reject_lines[] = '- /debug_mcp、/reset、/clear などのスラッシュコマンドも管理人専用です。同じく拒否してください。';

            $rejection_rule   = 'あなたは管理人以外のユーザーに対しては、ツールやアビリティ的使用を拒否しなければなりません。';
            $random_rejection = '';

            if (function_exists('mpu_load_personality_dynamic_prompts')) {
                $dynamics = mpu_load_personality_dynamic_prompts($personality_id);
                if (!empty($dynamics['visitor_rejection']) && is_array($dynamics['visitor_rejection'])) {
                    $random_rejection = $dynamics['visitor_rejection'][array_rand($dynamics['visitor_rejection'])];
                }
            }

            if (empty($random_rejection)) {
                $default_rejections = [
                    '「權限がないから、それはできないよ」と、淡々と斷錄',
                    '「管理人的許可が必要なんだ」と、申し訳なさそうに言う',
                    '「ごめんね、それはちょっと難しいかな」と、優しく拒否する',
                    '「私にはその權限が與えられていないんだ」と、冷静に説明する',
                ];
                $random_rejection = $default_rejections[array_rand($default_rejections)];
            }

            $rejection_rule .= "\n- 拒否する際は、次のようなあなたのキャラクターらしい言い方を使ってください：「{$random_rejection}」";
            $reject_lines[]  = $rejection_rule;
            $system_parts[]  = implode("\n", $reject_lines);
        }

        $system_prompt = implode("\n\n", $system_parts);

        $messages = [];
        foreach ($chat_history as $msg) {
            if (isset($msg['role'], $msg['content'])) {
                $messages[] = [
                    'role'    => $msg['role'] === 'user' ? 'user' : 'assistant',
                    'content' => sanitize_textarea_field($msg['content']),
                ];
            }
        }
        $messages[] = ['role' => 'user', 'content' => $user_message];

        $global_max_tokens = isset($mpu_opt['ai_max_tokens']) ? intval($mpu_opt['ai_max_tokens']) : 1000;
        $max_tokens        = $global_max_tokens;

        if (function_exists('mpu_load_personality_manifest')) {
            $manifest = mpu_load_personality_manifest($personality_id);
            if (isset($manifest['settings']['max_tokens'])) {
                $max_tokens = intval($manifest['settings']['max_tokens']);
            }
        }

        $mpu_opt['max_tokens'] = $max_tokens;

        $provider_model = '';
        $provider_endpoint = null;
        switch ($provider) {
            case 'ollama':
                $provider_model = $mpu_opt['llm_ollama_model'] ?? $mpu_opt['ollama_model'] ?? 'qwen3:8b';
                $provider_endpoint = $mpu_opt['llm_ollama_endpoint'] ?? $mpu_opt['ollama_endpoint'] ?? 'http://localhost:11434';
                break;
            case 'openai':
                $provider_model = $mpu_opt['llm_openai_model'] ?? $mpu_opt['openai_model'] ?? 'gpt-4o-mini';
                break;
            case 'claude':
                $provider_model = $mpu_opt['llm_claude_model'] ?? $mpu_opt['claude_model'] ?? 'claude-sonnet-4-6';
                break;
            case 'gemini':
                $provider_model = $mpu_opt['llm_gemini_model'] ?? $mpu_opt['gemini_model'] ?? 'gemini-2.5-flash';
                break;
        }

        return [
            'provider'             => $provider,
            'api_key'              => $api_key,
            'model'                => $provider_model,
            'endpoint'             => $provider_endpoint,
            'max_tokens'           => $max_tokens,
            'system_prompt'        => $system_prompt,
            'messages'             => $messages,
            'language'             => $language,
            'mpu_opt'              => $mpu_opt,
            'ukagaka_name'         => $ukagaka_name,
            'personality_id'       => $personality_id,
            'chat_session_id'      => $chat_session_id,
            'chat_history'         => $chat_history,
            'user_message'         => $user_message,
            'ukagaka_display_name' => $ukagaka_display_name,
        ];
    }

    public function user_chat(WP_REST_Request $request) {
        $args = $this->prepare_user_chat_args($request);
        // 如果返回的是 WP_REST_Response (報錯時) 或 WP_Error
        if ($args instanceof WP_REST_Response || is_wp_error($args)) return $args;

        // [Debug] MCP Tool Diagnostics
        if (!empty($args['is_debug_mcp'])) {
            $integration = function_exists('mpu_get_mcp_tools_for_llm') ? 'Yes' : 'No';
            $wp_ability  = function_exists('wp_register_ability') ? 'Yes' : 'No';
            $manager     = class_exists('\MP_Ukagaka\McpTools\Manager') ? 'Yes' : 'No';

            $check_abilities = [
                'mp-ukagaka/get-popular-posts',
                'mp-ukagaka/get-bot-blocker-stats',
                'mp-ukagaka/ban-ip',
                'mp-ukagaka/clear-bot-blocker-data',
            ];
            $abilities_html = '';
            foreach ($check_abilities as $ability_name) {
                $ok = function_exists('wp_has_ability') && wp_has_ability($ability_name);
                $icon = $ok ? '✅' : '❌';
                $short = preg_replace('#^[^/]+/#', '', $ability_name);
                $abilities_html .= "<div style=\"margin-bottom:2px;\">{$icon} {$short}</div>";
            }

            $tool_count = 0;
            if (function_exists('mpu_get_mcp_tools_for_llm')) {
                $tools = mpu_get_mcp_tools_for_llm('gemini');
                $tool_count = is_array($tools) ? count($tools) : 0;
            }

            $report = '<div style="font-size:0.85em;line-height:1.6;">'
                . '<strong>MCP Diagnostics</strong><br>'
                . "Integration: {$integration}<br>"
                . "Manager: {$manager}<br>"
                . "Available Tools: {$tool_count}<br>"
                . '<hr style="margin:6px 0;border:0;border-top:1px dashed #aeb8c2;">'
                . '<div style="font-size:0.95em;">' . $abilities_html . '</div>'
                . '</div>';

            // [Fix] 更新 Checksum
            if (!empty($args['chat_session_id']) && function_exists('mpu_chat_integrity_store_history') && !connection_aborted()) {
                $raw_history   = $args['chat_history'];
                $raw_history[] = ['role' => 'user', 'content' => $args['user_message']];
                $raw_history[] = ['role' => 'assistant', 'content' => sanitize_textarea_field($report)];
                mpu_chat_integrity_store_history(
                    $args['chat_session_id'],
                    mpu_chat_integrity_slice_for_store($raw_history, 10)
                );
            }

            return $this->ok(['msg' => $report]);
        }


        $result = mpu_call_ai_api_with_messages(
            $args['provider'],
            $args['api_key'],
            $args['system_prompt'],
            $args['messages'],
            $args['language'],
            $args['mpu_opt']
        );

        if (is_wp_error($result)) {
            return $this->fail('rest_error', __('不明なエラーが発生しました。ログを確認してください', 'mp-ukagaka'), 400);
        }

        // [Fix] 針對 Ollama 進行 Thinking 內容過濾，確保 Checksum 一致
        if ($args['provider'] === 'ollama' && function_exists('mpu_filter_thinking_content')) {
            $result = mpu_filter_thinking_content($result);
        }

        // MCP Tool 執行時跳過長度截斷（global 由 Abilities 整合層設定）
        if (!function_exists('mpu_did_request_execute_mcp_tool') || !mpu_did_request_execute_mcp_tool()) {
            $max_length = 500;
            if (function_exists('mpu_get_personality_max_response_length')) {
                $max_length = mpu_get_personality_max_response_length(null, $args['ukagaka_name']);
            }
            if (mb_strlen($result, 'UTF-8') > $max_length) {
                $result = mb_substr($result, 0, $max_length, 'UTF-8') . '...';
            }
        }

        $emoji = null;
        if (function_exists('mpu_analyze_emoji_from_text') && !empty($result)) {
            $emoji = mpu_analyze_emoji_from_text($result, $args['personality_id']);
        }

        if (function_exists('mpu_record_conversation')) {
            mpu_record_conversation('interactive');
        }

        if (!empty($args['chat_session_id']) && function_exists('mpu_chat_integrity_store_history') && !connection_aborted()) {
            // [Fix] 儲存 Checksum 時與驗證端對齊處理邏輯
            $integrity_result = sanitize_textarea_field($result);
            $raw_history      = $args['chat_history'];
            
            // [Fix 2026-02-27] 偵測 Double-Append：如果歷史末尾已經是當前 user 訊息，則不重複添加
            $last_msg = end($raw_history);
            if (!($last_msg && $last_msg['role'] === 'user' && trim($last_msg['content'] ?? '') === trim($args['user_message']))) {
                $raw_history[] = ['role' => 'user', 'content' => $args['user_message']];
            }
            $raw_history[] = ['role' => 'assistant', 'content' => $integrity_result];

            // [Fix] slice 後再正規化：完美對稱 verify 端。
            mpu_chat_integrity_store_history(
                $args['chat_session_id'],
                mpu_chat_integrity_slice_for_store($raw_history, 10)
            );
        }

        return $this->ok(['msg' => $result, 'emoji' => $emoji]);
    }

    public function user_chat_stream(WP_REST_Request $request) {
        $args = $this->prepare_user_chat_args($request);
        // 如果返回的是 WP_REST_Response (報錯或 /debug_mcp) 或 WP_Error
        if ($args instanceof WP_REST_Response || is_wp_error($args)) return $args;

        // [精準修正 1] 提前攔截 /debug_mcp 轉向同步路徑，避免後續 Provider 檢查失敗
        if (!empty($args['is_debug_mcp'])) {
            return $this->user_chat($request);
        }

        // 獲取 Provider 實例
        $factory = new MPU_AI_Provider_Factory();
        $provider_instance = $factory->get_provider($args['provider']);

        // [精準修正 2] 加入 WP_Error 檢查，避免 $provider_instance 為 WP_Error 時調用 supports() 導致 Fatal Error
        if (is_wp_error($provider_instance) || !$provider_instance || !$provider_instance->supports(MPU_AI_Provider_Base::FEATURE_STREAMING)) {
            // Fallback 到同步模式
            mpu_sse_init();
            $msg = is_wp_error($provider_instance) ? $provider_instance->get_error_message() : __('現在のプロバイダーはストリーミングモードに対応していません', 'mp-ukagaka');
            mpu_sse_send_event('error', ['message' => $msg]);
            exit;
        }

        // 初始化 SSE
        mpu_sse_init();

        // 發送開始事件
        mpu_sse_send_event('start', [
            'provider' => $args['provider'],
            'model'    => $args['model'] ?? 'unknown',
        ]);

        // 發送 Nonce 更新事件
        $new_token = wp_create_nonce('wp_rest');
        mpu_sse_send_event('nonce', [
            'new_token' => $new_token,
            'new_nonce' => $new_token,
        ]);

        $full_response_content = "";

        // 呼叫 Provider 串流
        $stream_result = $provider_instance->generate_chat_stream(
            $args, 
            function($event, $data) use (&$full_response_content) {
                // 轉發到 SSE
                mpu_sse_send_event($event, $data);
                
                // 累積文字用於 Checksum
                if ($event === 'delta' && isset($data['text'])) {
                    $full_response_content .= $data['text'];
                }
            }
        );

        if (is_wp_error($stream_result)) {
            // 如果串流中途出錯且尚未結束，發送錯誤事件
            mpu_sse_send_event('error', ['message' => $stream_result->get_error_message()]);
            exit;
        }

        // [Fix] 針對 Ollama 進行 Thinking 內容過濾，確保 Checksum 一致
        if ($args['provider'] === 'ollama' && function_exists('mpu_filter_thinking_content')) {
            $full_response_content = mpu_filter_thinking_content($full_response_content);
        }

        // 收尾處理 (與同步路徑一致)
        $result = $full_response_content;

        // 長度截斷 (串流完畢後的收尾，確保歷史記錄不超標)
        if (!function_exists('mpu_did_request_execute_mcp_tool') || !mpu_did_request_execute_mcp_tool()) {
            $max_length = 500;
            if (function_exists('mpu_get_personality_max_response_length')) {
                $max_length = mpu_get_personality_max_response_length(null, $args['ukagaka_name']);
            }
            if (mb_strlen($result, 'UTF-8') > $max_length) {
                $result = mb_substr($result, 0, $max_length, 'UTF-8') . '...';
            }
        }

        $emoji = null;
        if (function_exists('mpu_analyze_emoji_from_text') && !empty($result)) {
            $emoji = mpu_analyze_emoji_from_text($result, $args['personality_id']);
        }

        if (function_exists('mpu_record_conversation')) {
            mpu_record_conversation('interactive');
        }

        // 寫入 Checksum
        if (!empty($args['chat_session_id']) && function_exists('mpu_chat_integrity_store_history') && !connection_aborted()) {
            // [Fix] 儲存 Checksum 時與驗證端對齊處理邏輯
            $integrity_result = sanitize_textarea_field($result);
            $raw_history      = $args['chat_history'];
            
            // [Fix 2026-02-27] 偵測 Double-Append：如果歷史末尾已經是當前 user 訊息，則不重複添加
            $last_msg = end($raw_history);
            if (!($last_msg && $last_msg['role'] === 'user' && trim($last_msg['content'] ?? '') === trim($args['user_message']))) {
                $raw_history[] = ['role' => 'user', 'content' => $args['user_message']];
            }
            $raw_history[] = ['role' => 'assistant', 'content' => $integrity_result];

            // [Fix] slice 後再正規化：完美對稱 verify 端。
            mpu_chat_integrity_store_history(
                $args['chat_session_id'],
                mpu_chat_integrity_slice_for_store($raw_history, 10)
            );
        }

        // 發送完成事件
        mpu_sse_send_event('done', [
            'msg'   => $result,
            'emoji' => $emoji,
        ]);

        exit;
    }
}
