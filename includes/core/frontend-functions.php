<?php

/**
 * 前端功能：HTML 生成、資源載入
 * 
 * @package MP_Ukagaka
 * @subpackage Frontend
 */

if (!defined('ABSPATH')) {
    exit();
}

/**
 * 獲取初始對話訊息
 * 
 * 支援從 personality 系統載入睡眠對話（如果該人格啟用了睡眠模式）
 * 
 * @param string|null $ukagaka_name 偽春菜名稱，用於確定當前人格
 * @return string 初始對話訊息
 */
function mpu_get_initial_message($ukagaka_name = null)
{
    // 睡眠時間帶：嘗試從 personality 系統載入睡眠對話
    // 獲取當前人格 ID（提前解析，供後續使用）
    $personality_id = null;
    if ($ukagaka_name !== null && function_exists('mpu_get_personality_id_from_ukagaka_name')) {
        $personality_id = mpu_get_personality_id_from_ukagaka_name($ukagaka_name);
    } else {
        $personality_id = function_exists('mpu_get_current_personality_id')
            ? mpu_get_current_personality_id()
            : null;
    }

    // ★ 傳入 personality_id 以正確判斷該角色的睡眠狀態
    if (function_exists('mpu_is_deep_sleep_time') && mpu_is_deep_sleep_time($personality_id)) {

        // 嘗試從 personality 系統載入睡眠模式配置
        if ($personality_id !== null && function_exists('mpu_load_personality_sleep_mode')) {
            $sleep_config = mpu_load_personality_sleep_mode($personality_id);

            // 檢查是否啟用睡眠模式
            if (!empty($sleep_config['enabled'])) {
                $sleeping_messages = [];

                // 合併所有類別的睡眠消息
                if (!empty($sleep_config['basic'])) {
                    $sleeping_messages = array_merge($sleeping_messages, $sleep_config['basic']);
                }
                if (!empty($sleep_config['food_dreams'])) {
                    $sleeping_messages = array_merge($sleeping_messages, $sleep_config['food_dreams']);
                }
                if (!empty($sleep_config['mimic_dreams'])) {
                    $sleeping_messages = array_merge($sleeping_messages, $sleep_config['mimic_dreams']);
                }
                if (!empty($sleep_config['magic_dreams'])) {
                    $sleeping_messages = array_merge($sleeping_messages, $sleep_config['magic_dreams']);
                }
                if (!empty($sleep_config['journey_dreams'])) {
                    $sleeping_messages = array_merge($sleeping_messages, $sleep_config['journey_dreams']);
                }

                // 如果檢測到 BOT，添加 BOT 相關夢話
                $visitor_info = function_exists('mpu_get_visitor_info_for_llm') ? mpu_get_visitor_info_for_llm() : [];
                $is_bot_detected = !empty($visitor_info['is_bot']) && $visitor_info['is_bot'] === true;

                // 檢查過去 30 分鐘內是否有 BOT 訪問（即使當前訪問者不是 BOT）
                if (!$is_bot_detected && function_exists('mpu_check_recent_bot_visit')) {
                    $recent_bot = mpu_check_recent_bot_visit(1800); // 30 分鐘
                    if ($recent_bot !== false) {
                        $is_bot_detected = true;
                    }
                }

                if ($is_bot_detected && !empty($sleep_config['bot_dreams'])) {
                    // 將 BOT 相關夢話加入選項池（增加被選中的機率）
                    $sleeping_messages = array_merge($sleeping_messages, $sleep_config['bot_dreams'], $sleep_config['bot_dreams']);
                }

                // 條件觸發：低溫時加入 cold_complaints（氣溫 ≤ 15°C）
                if (!empty($sleep_config['cold_complaints'])) {
                    $weather = function_exists('mpu_get_weather_forecast') 
                        ? mpu_get_weather_forecast() 
                        : null;
                    $current_temp = $weather['current']['temperature'] ?? null;
                    if ($current_temp !== null && $current_temp <= 15) {
                        $sleeping_messages = array_merge($sleeping_messages, $sleep_config['cold_complaints']);
                    }
                }

                // 條件觸發：賴床時加入 lazy_protests
                if (!empty($sleep_config['lazy_protests'])) {
                    $hour = (int) wp_date('G');
                    $sleep_settings = function_exists('mpu_get_sleep_settings') 
                        ? mpu_get_sleep_settings($personality_id) 
                        : [];
                    $deep_sleep_end = (int) ($sleep_settings['deep_sleep_end'] ?? 6);
                    $oversleep_end = function_exists('mpu_get_daily_oversleep_end') 
                        ? mpu_get_daily_oversleep_end($personality_id) 
                        : $deep_sleep_end;
                    // 在賴床時間範圍內（deep_sleep_end <= hour < oversleep_end）
                    if ($hour >= $deep_sleep_end && $hour < $oversleep_end) {
                        $sleeping_messages = array_merge($sleeping_messages, $sleep_config['lazy_protests']);
                    }
                }

                // 如果有睡眠消息，隨機選擇一條
                if (!empty($sleeping_messages)) {
                    $selected_message = $sleeping_messages[array_rand($sleeping_messages)];
                    return $selected_message . '<!-- mpu-sleep -->';
                }
            }
        }
    }

    // 正常時段：根據 LLM 模式顯示不同訊息
    if (function_exists('mpu_is_llm_replace_dialogue_enabled') && mpu_is_llm_replace_dialogue_enabled()) {
        return __("（えっと…何を話せばいいかな…）", "mp-ukagaka");
    }

    return __("（思考中…）", "mp-ukagaka");
}

/**
 * 決定是否顯示與如何插入
 */
add_action("wp", function () {
    $opt = mpu_get_option();
    if (mpu_is_show_page()) {
        mpu_common_msg();
        if (!empty($opt["insert_html"]) && intval($opt["insert_html"]) === 1) {
            add_action("wp_footer", "mpu_echo_html");
        } else {
            // 使用輸出緩衝來在 </body> 前插入 HTML
            ob_start("mpu_ob_callback");
            register_shutdown_function("mpu_shutdown_callback");
        }
    }
});

/**
 * 判斷是否顯示偽春菜
 */
function mpu_is_show_page()
{
    $mpu_opt = mpu_get_option();

    // 增加對 AJAX 的檢查，避免在 AJAX 請求中載入
    if (
        is_admin() ||
        is_feed() ||
        (defined("DOING_AJAX") && DOING_AJAX) ||
        wp_is_mobile()
    ) {
        return false;
    }

    // 檢查登入/註冊頁面
    $pagenow = $GLOBALS["pagenow"] ?? "";
    if (in_array($pagenow, ["wp-login.php", "wp-register.php"], true)) {
        return false;
    }

    $url = isset($_SERVER["HTTP_HOST"], $_SERVER["REQUEST_URI"])
        ? "http" .
        (is_ssl() ? "s" : "") .
        "://" .
        $_SERVER["HTTP_HOST"] .
        $_SERVER["REQUEST_URI"]
        : "";

    if (empty($url)) {
        return true;
    }

    $arr = mpu_str2array($mpu_opt["no_page"] ?? "");

    foreach ($arr as $value) {
        if (substr($value, -3) === "(*)") {
            $needle = substr($value, 0, -3);
            if ($needle !== "" && strpos($url, $needle) !== false) {
                return false;
            }
        } elseif ($value === $url) {
            return false;
        }
    }

    return true;
}

function mpu_ob_callback($buffer)
{
    $html = mpu_html();
    // 僅替換第一次出現的 </body> 標籤
    return preg_replace("/<\/body>/i", $html . "\n</body>", $buffer, 1);
}

function mpu_shutdown_callback()
{
    if (function_exists("ob_get_level") && ob_get_level() > 0) {
        @ob_end_flush();
    }
}

/**
 * 生成 HTML
 */
function mpu_html($num = false)
{
    $mpu_opt = mpu_get_option();

    if ($num === false && isset($_COOKIE["mpu_ukagaka_" . COOKIEHASH])) {
        $cookie_num = sanitize_text_field(
            $_COOKIE["mpu_ukagaka_" . COOKIEHASH]
        );
        if (!empty($mpu_opt["ukagakas"][$cookie_num])) {
            $num = $cookie_num;
        }
    }
    $ukagaka_num =
        $num === false ? $mpu_opt["cur_ukagaka"] ?? "default_1" : $num;
    $ukagaka = mpu_get_ukagaka($ukagaka_num);
    $ukagaka_num = $ukagaka_num ?? "default_1"; // 確保有值

    if (empty($ukagaka)) {
        return "";
    }

    $main_file = defined('MPU_MAIN_FILE') ? MPU_MAIN_FILE : dirname(dirname(dirname(__FILE__))) . '/mp-ukagaka.php';
    $ok_png = esc_url(plugins_url("images/ok_hover.png", $main_file));
    $cancel_png = esc_url(plugins_url("images/cancel_hover.png", $main_file));

    $html = "";

    $ext = $mpu_opt["external_file_format"] ?? "txt";
    $dialog_filename = $ukagaka["dialog_filename"] ?? $ukagaka_num;
    $data_file = "dialogs/" . $dialog_filename . "." . $ext;

    $html .=
        '
<div id="mp_ukagaka">
    <div id="ukagaka_shell">
        <div id="ukagaka">
            <div id="ukagaka_msgbox">
                <div class="ukagaka-msgbox-top"></div>
                <div id="ukagaka_msg" data-initial-msg="' .
        esc_attr(mpu_get_initial_message($ukagaka_num)) .
        '"></div>
                <div id="ukagaka_chat_input" style="display:none;">
                    <input type="text" id="mpu_user_input" placeholder="' . esc_attr__("メッセージを入力...", "mp-ukagaka") . '" maxlength="500" />
                </div>
                <div id="ukagaka_msgnum" style="display:none;">0</div>
                <div id="ukagaka_msglist" style="display:none;" data-file="' .
        esc_attr($data_file) .
        '" data-load-external="true"></div>
                <div class="ukagaka-msgbox-border">
                    <a id="mpu_ok_btn" href="javascript:void(0);" alt="Next">
                        <img style="margin-top:14px;margin-left:65px" src="' .
        $ok_png .
        '" width="28" height="28" />
                    </a>
                    <a id="mpu_cancel_btn" href="javascript:void(0);" alt="Cancel">
                        <img style="float:right;margin-top:14px;margin-right:65px" src="' .
        $cancel_png .
        '" width="28" height="28" />
                    </a>
                </div>
            </div>
            <div id="ukagaka_img"><canvas id="cur_ukagaka" data-title="' .
        esc_attr(mpu_output_filter($ukagaka["name"])) .
        '" data-alt="' .
        esc_attr(mpu_output_filter($ukagaka["name"])) .
        '" data-shell="' .
        esc_attr(mpu_get_shell($ukagaka_num, false)) .
        '"></canvas></div>
            <div id="ukagaka_num" style="display:none;">' .
        $ukagaka_num .
        '</div>
        </div>
        <div class="mpu-clear"></div>
        <div id="ukagaka-dock">
            <ul>
                <li class="gotop"><a id="toTop" href="#" title="転移" data-spa-ignore>' .
        __("トップへ戻る ▼", "mp-ukagaka") .
        '</a></li>
                <li class="hide"><a id="remove" href="#" title="ログアウト？">' .
        __("キャラを隠す ▼", "mp-ukagaka") .
        '</a></li>
                <li class="change"><a id="mpu_chat_toggle" href="#" title="チャット">' .
        __("チャット", "mp-ukagaka") .
        '</a></li>
            </ul>
        </div>
    </div>
</div>';

    // 移除舊的 JS 設置邏輯，改在 mpu_head 統一處理
    $html .= "\n";

    return $html;
}

function mpu_echo_html()
{
    echo mpu_html();
}

/**
 * 前端資源載入
 */
function mpu_enqueue_frontend_assets()
{
    if (! mpu_is_show_page()) {
        return;
    }

    $mpu_opt = mpu_get_option();

    // 載入 CSS
    if (empty($mpu_opt["no_style"])) {
        // 使用已定義的常量獲取主文件路徑
        $main_file = defined('MPU_MAIN_FILE') ? MPU_MAIN_FILE : dirname(dirname(dirname(__FILE__))) . '/mp-ukagaka.php';
        wp_enqueue_style(
            'mpu-style',
            plugins_url('css/mpu_style.css', $main_file),
            array(),
            MPU_VERSION
        );
    } else {
        // 使用自訂樣式：如果有設定自訂樣式連結，在 wp_head 輸出
        if (!empty($mpu_opt['custom_style_link'])) {
            add_action('wp_head', function () use ($mpu_opt) {
                // 直接輸出已由 wp_kses 過濾的 link 標籤
                echo $mpu_opt['custom_style_link'] . "\n";
            }, 5);
        }
    }

    // 載入 JavaScript
    // 使用已定義的常量獲取主文件路徑
    $main_file = defined('MPU_MAIN_FILE') ? MPU_MAIN_FILE : dirname(dirname(dirname(__FILE__))) . '/mp-ukagaka.php';

    // 判斷是否使用 bundled 版本（生產模式）
    // SCRIPT_DEBUG = true 時使用獨立檔案（開發模式）
    $use_bundle = !(defined('SCRIPT_DEBUG') && SCRIPT_DEBUG);
    $bundle_file = plugin_dir_path($main_file) . 'js/dist/ukagaka-bundle.min.js';
    
    // 如果 bundle 不存在，強制使用獨立檔案
    if ($use_bundle && !file_exists($bundle_file)) {
        $use_bundle = false;
    }

    if ($use_bundle) {
        // === 生產模式：載入合併壓縮後的 bundle ===
        wp_enqueue_script(
            'mpu-bundle',
            plugins_url('js/dist/ukagaka-bundle.min.js', $main_file),
            array('jquery'),
            MPU_VERSION,
            true
        );
        
        // Bundle 已包含：base, core, anime, emoji, chat, features, cookie
        // 設定 alias 供後續依賴使用
        $base_handle = 'mpu-bundle';
        $core_handle = 'mpu-bundle';
        $anime_handle = 'mpu-bundle';
        $chat_handle = 'mpu-bundle';
    } else {
        // === 開發模式：載入獨立檔案（便於調試）===
        
        // 1. 基礎層（配置、工具、AJAX）
        wp_enqueue_script(
            'mpu-base',
            plugins_url('js/ukagaka-base.js', $main_file),
            array('jquery'),
            MPU_VERSION,
            true
        );

        wp_enqueue_script(
            'mpu-core',
            plugins_url('js/ukagaka-core.js', $main_file),
            array('mpu-base'),
            MPU_VERSION,
            true
        );

        wp_enqueue_script(
            'mpu-anime',
            plugins_url('js/ukagaka-anime.js', $main_file),
            array('jquery', 'mpu-core'),
            MPU_VERSION,
            true
        );

        wp_enqueue_script(
            'mpu-context',
            plugins_url('js/ukagaka-context.js', $main_file),
            array('mpu-core'),
            MPU_VERSION,
            true
        );

        wp_enqueue_script(
            'mpu-dialog',
            plugins_url('js/ukagaka-dialog.js', $main_file),
            array('mpu-core'),
            MPU_VERSION,
            true
        );

        wp_enqueue_script(
            'mpu-greeting',
            plugins_url('js/ukagaka-greeting.js', $main_file),
            array('mpu-core', 'mpu-chat'),
            MPU_VERSION,
            true
        );

        wp_enqueue_script(
            'mpu-chat',
            plugins_url('js/ukagaka-chat.js', $main_file),
            array('mpu-core', 'mpu-anime'),
            MPU_VERSION,
            true
        );

        // 載入表情配置載入器
        wp_enqueue_script(
            'mpu-emoji-config',
            plugins_url('js/ukagaka-emoji.js', $main_file),
            array('mpu-core'),
            MPU_VERSION,
            true
        );

        wp_enqueue_script(
            'mpu-features',
            plugins_url('js/ukagaka-features.js', $main_file),
            array('mpu-core', 'mpu-anime', 'mpu-chat', 'mpu-context', 'mpu-dialog', 'mpu-greeting'),
            MPU_VERSION,
            true
        );
        
        $base_handle = 'mpu-base';
        $core_handle = 'mpu-core';
        $anime_handle = 'mpu-anime';
        $chat_handle = 'mpu-chat';
    }

    // 動態載入人格專屬腳本（從 manifest.json 讀取）
    // 支援 "script" (字串) 或 "scripts" (陣列) 兩種格式
    $personality_script_handle = 'mpu-personality';
    if (function_exists('mpu_load_personality_manifest')) {
        $manifest = mpu_load_personality_manifest();
        $personality_id = function_exists('mpu_get_current_personality_id')
            ? mpu_get_current_personality_id()
            : 'Frieren';

        // 統一處理：將 script 或 scripts 轉換為陣列
        $scripts_to_load = [];
        if (!empty($manifest['scripts']) && is_array($manifest['scripts'])) {
            // 新格式：scripts 陣列
            $scripts_to_load = $manifest['scripts'];
        } elseif (!empty($manifest['script'])) {
            // 舊格式：單一 script 字串（向後相容）
            $scripts_to_load = [$manifest['script']];
        }

        // 載入所有腳本（排除 *-emoji.js，因為有獨立載入機制）
        $script_index = 0;
        foreach ($scripts_to_load as $script_file) {
            // 跳過 emoji 腳本（由獨立機制處理）
            if (preg_match('/-emoji\.js$/i', $script_file)) {
                continue;
            }

            $handle = $script_index === 0 ? $personality_script_handle : "mpu-personality-{$script_index}";
            $script_url = plugins_url('ghost/' . $personality_id . '/' . $script_file, $main_file);
            wp_enqueue_script(
                $handle,
                $script_url,
                array($anime_handle),
                MPU_VERSION,
                true
            );
            $script_index++;
        }
    }

    // 動態載入角色專屬表情腳本（支援任何有 emojis/ 資料夾的角色）
    $personality_id = function_exists('mpu_get_current_personality_id')
        ? mpu_get_current_personality_id()
        : 'Frieren';

    // 使用新的動態表情檢測系統
    if (function_exists('mpu_personality_has_emoji') && mpu_personality_has_emoji($personality_id)) {
        $emoji_script_url = mpu_get_personality_emoji_script_url($personality_id);
        $emoji_base_url = mpu_get_personality_emoji_url($personality_id);
        $emoji_config = mpu_load_personality_emoji_config($personality_id);

        if ($emoji_script_url && $emoji_base_url) {
            $emoji_deps = $use_bundle 
                ? array('mpu-bundle', $personality_script_handle)
                : array('mpu-core', 'mpu-anime', 'mpu-emoji-config', $personality_script_handle);
            
            wp_enqueue_script(
                'mpu-emoji',
                $emoji_script_url,
                $emoji_deps,
                MPU_VERSION,
                true
            );

            // 表情配置改為 AJAX 延遲載入（避免在網頁原始碼中暴露關鍵字和圖片路徑）
            // 前端會透過 mpu_get_emoji_config AJAX 動態獲取表情配置
        }
    }

    // 傳遞可翻譯字串給 JavaScript（國際化支援）
    // 獲取日記標題前綴（從 dynamics.json）
    $diary_title_prefix = '';
    if (function_exists('mpu_load_personality_dynamic_prompts')) {
        $personality_id = function_exists('mpu_get_current_personality_id')
            ? mpu_get_current_personality_id()
            : null;
        $dynamic_prompts = mpu_load_personality_dynamic_prompts($personality_id);
        $diary_title_prefix = $dynamic_prompts['diary_title_prefix'] ?? '';
    }

    // 根據模式選擇正確的 script handle
    // 修正：掛載到更基礎的 handle (mpu-core)，確保 mpu-chat 等依賴它的腳本能讀取到 mpuL10n
    $l10n_handle = $use_bundle ? 'mpu-bundle' : 'mpu-core';
    
    wp_localize_script($l10n_handle, 'mpuL10n', [
        // AI 相關訊息
        'loadingArticle' => __('（…ああ、記事か。どれどれ…）', 'mp-ukagaka'),
        'loadingOwnDiary' => __('（…あ、これ私の…）', 'mp-ukagaka'),
        'diaryTitlePrefix' => $diary_title_prefix,
        'unknownVisitor' => __('（…あ、知らない人間だ…）', 'mp-ukagaka'),
        'apiMagicInsufficient' => __('…ちょっと待って。API魔力が足りない', 'mp-ukagaka'),
        'thinkingMessage' => __('（えっと…何を話せばいいかな…）', 'mp-ukagaka'),
        // 錯誤訊息
        'dialogLoadFailed' => __('ダイアログファイルの読み込みに失敗しました。後でもう一度お試しください。', 'mp-ukagaka'),
        'dialogEmpty' => __('ダイアログファイルが空です。内容を確認してください', 'mp-ukagaka'),
        'dialogNotLoaded' => __('ダイアログがまだ読み込まれていません。お待ちください...', 'mp-ukagaka'),
        'llmError' => __('LLM 接続に失敗しました', 'mp-ukagaka'),
        'processingError' => __('ダイアログデータの処理中にエラーが発生しました。後でもう一度お試しください。', 'mp-ukagaka'),
        'loadingFailed' => __('読み込みに失敗しました。後でもう一度お試しください。', 'mp-ukagaka'),
        'errorOccurred' => __('エラーが発生しました。後でもう一度お試しください。', 'mp-ukagaka'),
        'duplicateRequest' => __('重複したリクエストが存在します。後でもう一度お試しください。', 'mp-ukagaka'),
        'requestFailed' => __('リクエストに失敗しました', 'mp-ukagaka'),
        'securityVerificationFailed' => __('セキュリティ検証に失敗しました', 'mp-ukagaka'),
        'animationLoadFailed' => __('アニメーションモジュールの読み込みに失敗しました。ページを更新してください。', 'mp-ukagaka'),
        'connectionError' => __('（…通信状況が良くないみたいだ…）', 'mp-ukagaka'),
        // 互動對話模式
        'chatExit' => __('……', 'mp-ukagaka'),
        'chatWelcome' => __('何か話したいことはありますか？', 'mp-ukagaka'),
        'chatPlaceholder' => __('メッセージを入力...', 'mp-ukagaka'),
        'chatThinking' => __('…うーん、そうだね…', 'mp-ukagaka'),
        'executingTool' => __('（…%sを実行中…）', 'mp-ukagaka'),
    ]);
}
add_action('wp_enqueue_scripts', 'mpu_enqueue_frontend_assets');

function mpu_head()
{
    if (!mpu_is_show_page()) {
        return;
    }

    $mpu_opt = mpu_get_option();

    $robot_show = mpu_js_filter(__("キャラを表示 ▲", "mp-ukagaka"));
    $robot_hide = mpu_js_filter(__("キャラを隠す ▼", "mp-ukagaka"));
    $msg_show = mpu_js_filter(__("会話を表示 ▲", "mp-ukagaka"));
    $msg_hide = mpu_js_filter(__("会話を隠す ▼", "mp-ukagaka"));

    echo "<script type=\"text/javascript\">\n";
    echo "var mpuRestUrl = '" . esc_url_raw(rest_url('mp-ukagaka/v1/')) . "';\n";
    echo "var mpuRestNonce = '" . wp_create_nonce('wp_rest') . "';\n";
    // Token 不再嵌入 HTML（避免 full-page cache 把第一訪客 token 送給他人）
    // JS 會在首次 API 呼叫前透過 /session-token 端點懶取得
    echo "var mpuSessionToken = null;\n";

    // ★ 先獲取當前 personality_id，再用於睡眠判定
    $current_personality = function_exists('mpu_get_current_personality_id')
        ? mpu_get_current_personality_id()
        : 'Frieren';

    // 獲取伺服器端的深夜睡眠時間判定（統一時間來源，避免客戶端/伺服器時區差異）
    // ★ 傳入當前 personality_id 以正確判斷該角色的睡眠狀態
    $is_deep_sleep_time = function_exists('mpu_is_deep_sleep_time') ? mpu_is_deep_sleep_time($current_personality) : false;

    echo "var mpuInfo = {
        robot: ['{$robot_show}', '{$robot_hide}'],
        msg: ['{$msg_show}', '{$msg_hide}'],
        isDeepSleepTime: " . ($is_deep_sleep_time ? 'true' : 'false') . "
    };\n";

    $ollama_replace = function_exists('mpu_is_llm_replace_dialogue_enabled')
        ? mpu_is_llm_replace_dialogue_enabled()
        : false;
    $typewriter_speed = isset($mpu_opt['typewriter_speed']) ? intval($mpu_opt['typewriter_speed']) : 40;
    $ai_enabled = isset($mpu_opt['ai_enabled']) && $mpu_opt['ai_enabled'];
    $streaming_enabled = false;
    if (class_exists('MPU_AI_Provider_Factory') && function_exists('mpu_get_current_provider')) {
        $current_provider = mpu_get_current_provider($mpu_opt);
        $provider_instance = MPU_AI_Provider_Factory::get_provider($current_provider);
        if (!is_wp_error($provider_instance) && is_object($provider_instance) && method_exists($provider_instance, 'supports')) {
            $streaming_enabled = $provider_instance->supports(MPU_AI_Provider_Base::FEATURE_STREAMING);
        }
    }
    echo "var mpuPreSettings = {\n";
    echo "    ollama_replace: " . ($ollama_replace ? 'true' : 'false') . ",\n";
    echo "    typewriter_speed: " . $typewriter_speed . ",\n";
    echo "    streaming_enabled: " . ($streaming_enabled ? 'true' : 'false') . ",\n";
    echo "    is_admin: " . (current_user_can('manage_options') ? 'true' : 'false') . "\n";
    echo "};\n";
    echo "var mpuAiEnabled = " . ($ai_enabled ? 'true' : 'false') . ";\n";

    // 裝飾配置改為 AJAX 延遲載入（避免在網頁原始碼中暴露圖片路徑）
    // 前端會透過 mpu_get_decoration_config AJAX 動態獲取裝飾配置
    echo "var mpuDecorationConfigPending = true;\n";

    // 從 cookie 讀取 ukagaka_num（與 mpu_html() 保持一致）
    $ukagaka_num = $mpu_opt["cur_ukagaka"] ?? "default_1";
    if (isset($_COOKIE["mpu_ukagaka_" . COOKIEHASH])) {
        $cookie_num = sanitize_text_field($_COOKIE["mpu_ukagaka_" . COOKIEHASH]);
        if (!empty($mpu_opt["ukagakas"][$cookie_num])) {
            $ukagaka_num = $cookie_num;
        }
    }

    // 輸出 ukagaka_num 供前端 AJAX 載入使用（不直接輸出 shellInfo，改用 AJAX 延遲載入）
    echo "var mpuInitParams = { ukagaka_num: " . wp_json_encode($ukagaka_num) . " };\n";

    echo '
    jQuery(document).ready(function($) {
        if (typeof window.mpuCanvasManager !== "undefined" && $("#cur_ukagaka").length > 0) {
            $.ajax({
                url: mpuRestUrl + "init",
                type: "GET",
                beforeSend: function(xhr) {
                    xhr.setRequestHeader("X-WP-Nonce", mpuRestNonce);
                },
                data: {
                    ukagaka_num: mpuInitParams.ukagaka_num
                },
                dataType: "json",
                success: function(response) {
                    if (response.success) {
                        if (response.shell_info) {
                            window.mpuCanvasManager.init(
                                response.shell_info,
                                response.ukagaka_name,
                                response.ukagaka_num
                            );
                        }
                        window.mpuInitData = response;
                        window.mpuPersonalityId = response.personality_id || null;
                        window.mpuDecorationsBaseUrl = response.decorations_base_url;
                        window.mpuDecorationConfig = response.decoration_config;
                        window.mpuTouchZones = response.touchzones;
                        window.mpuShowDecorations = response.show_decorations;
                        window.mpuEmojiBaseUrl = response.emoji_base_url;
                        window.mpuSupportedEmojis = response.supported_emojis;
                        window.mpuEmojiMappings = response.emoji_mappings;
                        window.mpuSettings = response.settings;
                        $(document).trigger("mpuInitComplete", [response]);
                    } else {
                        console.error("[MP Ukagaka] Init failed:", response.error || "Unknown error");
                    }
                },
                error: function(xhr, status, error) {
                    console.error("[MP Ukagaka] AJAX init failed:", error);
                }
            });
        }
        
        var showRobot = mpu_getCookie("mpuRobot");
        var showMsg   = mpu_getCookie("mpuMsg");
        if (showRobot==null) {';
    if (empty($mpu_opt["show_ukagaka"])) {
        echo '
            $("#show_ukagaka").html(mpuInfo.robot[0]); 
            $("#ukagaka").fadeOut(400);';
    }
    echo '
        } else if (showRobot=="hidden") {
            $("#show_ukagaka").html(mpuInfo.robot[0]); 
            $("#ukagaka").fadeOut(400);
        }
        if (showMsg==null) {';
    if (empty($mpu_opt["show_msg"])) {
        echo '
            $("#show_msg").html(mpuInfo.msg[0]); 
            $("#ukagaka_msgbox").fadeOut(400);';
    }
    echo '
        } else if (showMsg=="hidden") {
            $("#show_msg").html(mpuInfo.msg[0]); 
            $("#ukagaka_msgbox").fadeOut(400);
        }
    });';

    if (!empty($mpu_opt["extend"]["js_area"])) {
        echo stripslashes($mpu_opt["extend"]["js_area"]) . "\n";
    }

    echo "</script>\n";
}
add_action("wp_head", "mpu_head");
