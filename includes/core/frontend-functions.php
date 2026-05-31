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
/**
 * 取得初始訊息字串。
 *
 * @param string|null $ukagaka_name 角色名稱.
 * @param bool        $is_system    [out] by-ref：回傳訊息是否為 system placeholder
 *     （思考中／何を話せばいいかな 等）。睡眠對話為 false（應播放角色動畫），
 *     placeholder 為 true（應跳過動畫）。供前端 §16.3-A 標記式判定使用.
 * @return string
 */
function mpu_get_initial_message($ukagaka_name = null, &$is_system = null)
{
    // 預設視為 system placeholder（正常時段兩個 return 分支皆為 placeholder）.
    $is_system = true;
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
                    $current_mod = (int) wp_date('G') * 60 + (int) wp_date('i');
                    $sleep_settings = function_exists('mpu_get_sleep_settings') 
                        ? mpu_get_sleep_settings($personality_id) 
                        : [];
                    $deep_sleep_end_mod = function_exists('mpu_sleep_hour_to_boundary_mod')
                        ? mpu_sleep_hour_to_boundary_mod($sleep_settings['deep_sleep_end'] ?? 6)
                        : max(0, min(1439, (int) ($sleep_settings['deep_sleep_end'] ?? 6) * 60));
                    $oversleep_end_mod = function_exists('mpu_get_daily_oversleep_end_mod') 
                        ? mpu_get_daily_oversleep_end_mod($personality_id) 
                        : $deep_sleep_end_mod;
                    // 在賴床時間範圍內（deep_sleep_end <= current_mod < oversleep_end_mod）
                    if ($current_mod >= $deep_sleep_end_mod && $current_mod < $oversleep_end_mod) {
                        $sleeping_messages = array_merge($sleeping_messages, $sleep_config['lazy_protests']);
                    }
                }

                // 如果有睡眠消息，隨機選擇一條
                if (!empty($sleeping_messages)) {
                    $selected_message = $sleeping_messages[array_rand($sleeping_messages)];
                    // 睡眠對話是角色台詞而非 placeholder：應播放角色動畫.
                    $is_system = false;
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
    $initial_message = mpu_get_initial_message($ukagaka_num, $initial_msg_is_system);

    $html .=
        '
<div id="mp_ukagaka">
    <div id="ukagaka_shell">
        <div id="ukagaka">
            <div id="ukagaka_think" class="mpu-think-bubble" hidden></div>
            <div id="ukagaka_msgbox"' . ( $initial_msg_is_system ? ' style="display:none;"' : '' ) . '>
                <div class="ukagaka-msgbox-top"></div>
                <div id="ukagaka_msg" data-initial-msg="' .
        esc_attr($initial_message) .
        '"' . ( $initial_msg_is_system ? ' data-initial-msg-system="1"' : '' ) . '></div>
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

    $log_i18n = function_exists('mpu_console_log_i18n_builder')
        ? mpu_console_log_i18n_builder()
        : null;

    if ($log_i18n) {
        /* translators: console log. Character drawing canvas DOM element is missing. */
        $log_i18n->always('animeCanvasElementMissing', __('Canvas 要素が存在しません', 'mp-ukagaka'));
        /* translators: console log. CanvasRenderingContext2D could not be acquired. */
        $log_i18n->always('animeCanvasContextUnavailable', __('Canvas コンテキストを取得できません', 'mp-ukagaka'));
        /* translators: console log. Frieren-specific manager is missing, so generic mode is used. */
        $log_i18n->always('animeFrierenManagerMissing', __('フリーレンマネージャーが読み込まれていないため、汎用モードを使用します', 'mp-ukagaka'));
        /* translators: console log. %s is the image URL that failed to load. */
        $log_i18n->always('animeImageLoadFailed', __('画像の読み込みに失敗しました：%s', 'mp-ukagaka'));
        /* translators: console log. %s is the frame image URL that failed to load. */
        $log_i18n->always('animeFrameImageLoadFailed', __('フレーム画像の読み込みに失敗しました：%s', 'mp-ukagaka'));
        /* translators: console log. %s is the server-provided decoration config error, or an unknown-error fallback. */
        $log_i18n->always('frierenDecorationConfigLoadFailed', __('装飾設定を読み込めませんでした：%s', 'mp-ukagaka'), ['scope' => 'frieren']);
        /* translators: console log. Used when decoration config loading fails without a server-provided error message. */
        $log_i18n->always('frierenDecorationConfigLoadFailedUnknown', __('装飾設定を読み込めませんでした：不明なエラー', 'mp-ukagaka'), ['scope' => 'frieren']);
        /* translators: console log. %s is the jQuery AJAX error value for loading decoration config. */
        $log_i18n->always('frierenDecorationConfigAjaxFailed', __('AJAX による装飾設定の読み込みに失敗しました：%s', 'mp-ukagaka'), ['scope' => 'frieren']);
        /* translators: console log. Required front-end runtime dependencies for decoration config loading are unavailable. */
        $log_i18n->always('frierenDecorationConfigRuntimeUnavailable', __('jQuery または mpuurl が利用できないため、装飾設定を読み込めません', 'mp-ukagaka'), ['scope' => 'frieren']);
        /* translators: console log. The decoration_config structure is not in the expected format. */
        $log_i18n->always('frierenDecorationConfigInvalid', __('装飾設定が無効です', 'mp-ukagaka'), ['scope' => 'frieren']);
        /* translators: console log. shellInfo contains the character image URL and display settings. */
        $log_i18n->always('frierenShellInfoInvalid', __('フリーレンモード：shellInfo が無効です', 'mp-ukagaka'), ['scope' => 'frieren']);
        /* translators: console log. Canvas manager is missing before Frieren images are loaded. */
        $log_i18n->always('frierenImageCanvasManagerMissing', __('画像読み込み前に Canvas マネージャーが初期化されていません', 'mp-ukagaka'), ['scope' => 'frieren']);
        /* translators: console log. %s is the Frieren image URL that failed to load. */
        $log_i18n->always('frierenImageLoadFailed', __('フリーレン画像の読み込みに失敗しました：%s', 'mp-ukagaka'), ['scope' => 'frieren']);
        /* translators: console log. Canvas manager is missing before drawing Frieren. */
        $log_i18n->always('frierenDrawCanvasManagerMissing', __('描画前に Canvas マネージャーが初期化されていません', 'mp-ukagaka'), ['scope' => 'frieren']);
        /* translators: console log. %s is the decoration type for which pixel-hit Canvas creation failed. */
        $log_i18n->always('frierenPixelCanvasCreateFailed', __('ピクセル判定用 Canvas を作成できません：%s', 'mp-ukagaka'), ['scope' => 'frieren']);
        /* translators: console log. %1$s is the decoration type, %2$s is the exception message. */
        $log_i18n->always('frierenPixelDataUnavailable', __('ピクセルデータを取得できません（クロスオリジンの可能性があります）：タイプ=%1$s、メッセージ=%2$s', 'mp-ukagaka'), ['scope' => 'frieren']);
        /* translators: console log. The request error object is logged as an additional console argument. */
        $log_i18n->always('frierenTouchZoneDialogRequestFailed', __('タッチ領域の会話リクエストに失敗しました', 'mp-ukagaka'), ['scope' => 'frieren']);
        /* translators: console log. The decoration dialog request error object is logged as an additional console argument. */
        $log_i18n->always('frierenDecorationDialogRequestFailed', __('装飾品会話リクエストに失敗しました', 'mp-ukagaka'), ['scope' => 'frieren']);
        /* translators: console log. jQuery.cookie initialization failed, so cookie-dependent features may not work. */
        $log_i18n->always('jqueryCookieInitFailed', __('jQuery.cookie を初期化できません。一部の機能が正常に動作しない可能性があります', 'mp-ukagaka'));
        /* translators: debug console log. Auto-talk startup exits because auto-talk is disabled. */
        $log_i18n->debug('autoTalkDisabledExit', __('startAutoTalk: mpuAutoTalk が false のため終了します', 'mp-ukagaka'));
        /* translators: debug console log. Auto-talk is not started while chat mode is active. */
        $log_i18n->debug('autoTalkSkippedDuringChatMode', __('startAutoTalk: 会話モード中のため自動会話を開始しません', 'mp-ukagaka'));
        /* translators: debug console log. Auto-talk is skipped during decoration or touch dialog. */
        $log_i18n->debug('autoTalkSkippedDuringInteractionDialog', __('startAutoTalk: 装飾品またはタッチ会話中のため自動会話を開始しません', 'mp-ukagaka'));
        /* translators: debug console log. Auto-talk is disabled while the character is asleep and not awakened. */
        $log_i18n->debug('autoTalkSkippedUnawokenSleepMode', __('🌙 睡眠モードでまだ目を覚ましていないため、自動会話を開始せず OK ボタンのみ受け付けます', 'mp-ukagaka'));
        /* translators: debug console log. %1$s is the adjusted interval in ms, %2$s is the original interval in ms. */
        $log_i18n->debug('autoTalkSleepModeIntervalAdjusted', __('🌙 睡眠モードが有効です（00:00〜06:00）。間隔を %1$s ms に調整しました（元: %2$s ms）', 'mp-ukagaka'));
        /* translators: debug console log. %1$s is the timer interval in ms, %2$s is the auto-talk enabled flag. */
        $log_i18n->debug('autoTalkTimerSet', __('startAutoTalk: タイマーを設定しました。間隔=%1$s ms、mpuAutoTalk=%2$s', 'mp-ukagaka'));
        /* translators: debug console log. %1$s is the auto-talk flag, %2$s is the LLM replacement flag. */
        $log_i18n->debug('autoTalkTimerTriggered', __('自動会話タイマーが作動しました。mpuAutoTalk=%1$s、mpuOllamaReplaceDialogue=%2$s', 'mp-ukagaka'));
        /* translators: debug console log. %s is the idle duration in seconds. */
        $log_i18n->debug('autoTalkSkippedUserIdle', __('ユーザーが無操作状態です（%s 秒）。今回の自動会話をスキップします', 'mp-ukagaka'));
        /* translators: debug console log. %1$s is previous state, %2$s is new state, %3$s is the new interval in ms. */
        $log_i18n->debug('autoTalkSleepModeStateChanged', __('睡眠モード状態が変化しました（%1$s → %2$s）。自動会話を再起動します（新しい間隔: %3$s ms）', 'mp-ukagaka'));
        /* translators: debug console log. A scheduled auto-talk tick is skipped while asleep and not awakened. */
        $log_i18n->debug('autoTalkTickSkippedUnawokenSleepMode', __('🌙 睡眠モードでまだ目を覚ましていないため、今回の自動会話をスキップし OK ボタンのみ受け付けます', 'mp-ukagaka'));
        /* translators: debug console log. %s is the detected bot name. */
        $log_i18n->debug('autoTalkBotAlertDetected', __('🛡️ Bot Alert: Bot の侵入を検出しました。Bot 名: %s', 'mp-ukagaka'));
        /* translators: debug console log. %s is the Turnstile block count. */
        $log_i18n->debug('autoTalkTurnstileDefenseDetected', __('🛡️ Turnstile 結界防御: 結界衝突イベントを検出しました。ブロック回数: %s', 'mp-ukagaka'));
        /* translators: debug console log. %s is the bot blocker block count. */
        $log_i18n->debug('autoTalkBotBlockerDetected', __('🛡️ Moelog Bot Blocker: 防御魔法のブロックイベントを検出しました。ブロック数: %s', 'mp-ukagaka'));
        /* translators: debug console log. %1$s is the crawler name, %2$s is the company name. */
        $log_i18n->debug('autoTalkAiCrawlerDetected', __('🤖 AI Crawler: AI クローラーの訪問を検出しました。crawler=%1$s、company=%2$s', 'mp-ukagaka'));
        /* translators: debug console log. %s is the visitor pulse type. */
        $log_i18n->debug('autoTalkVisitorPulseDetected', __('🌍 Visitor Pulse: 訪問者パルス信号を検出しました。pulse_type=%s', 'mp-ukagaka'));
        /* translators: debug console log. %s is the spam block count. */
        $log_i18n->debug('autoTalkAkismetSpamDetected', __('🛡️ Akismet スパム連携: スパムコメントイベントを検出しました。ブロック数: %s', 'mp-ukagaka'));
        /* translators: debug console log. %s is the unclassified action name. */
        $log_i18n->debug('autoTalkUnclassifiedSecurityEvent', __('🛡️ Auto-talk イベント（未分類 action）: %s', 'mp-ukagaka'));
        /* translators: debug console log. No security event was detected. */
        $log_i18n->debug('securityCheckNoEvent', __('🛡️ Turnstile/Akismet/BotBlocker/Bot Check: イベントはありません', 'mp-ukagaka'));
        /* translators: debug console log. %s is the caught error value. */
        $log_i18n->debug('securityCheckFailed', __('Security Check: セキュリティチェックに失敗しました: %s', 'mp-ukagaka'));
        /* translators: debug console log. The Ollama request queue has no pending item. */
        $log_i18n->debug('ollamaQueueEmpty', __('mpu_processOllamaQueue: キューは空です', 'mp-ukagaka'));
        /* translators: debug console log. %1$s is the trigger, %2$s is the remaining queue length. */
        $log_i18n->debug('ollamaQueueProcessingRequest', __('mpu_processOllamaQueue: キュー内のリクエストを処理します。trigger=%1$s、残りキュー長=%2$s', 'mp-ukagaka'));
        /* translators: debug console log. Values describe the next-message trigger and mode flags. */
        $log_i18n->debug('nextMessageCalled', __('mpu_nextmsg が呼び出されました。trigger=%1$s、isAuto=%2$s、isStartup=%3$s、isManual=%4$s、mpuOllamaReplaceDialogue=%5$s', 'mp-ukagaka'));
        /* translators: debug console log. Next message is skipped because message blocking is active. */
        $log_i18n->debug('nextMessageSkippedMessageBlocking', __('mpu_nextmsg: メッセージ表示がブロックされています（mpuMessageBlocking=true）。スキップします', 'mp-ukagaka'));
        /* translators: debug console log. Auto-talk next message is skipped during chat mode. */
        $log_i18n->debug('nextMessageSkippedChatMode', __('mpu_nextmsg: 会話モード中のため自動会話をスキップします', 'mp-ukagaka'));
        /* translators: debug console log. Auto-talk next message is skipped during decoration or touch dialog. */
        $log_i18n->debug('nextMessageSkippedInteractionDialog', __('mpu_nextmsg: 装飾品またはタッチ会話中のため自動会話をスキップします', 'mp-ukagaka'));
        /* translators: debug console log. Next message exits because auto-talk is disabled. */
        $log_i18n->debug('nextMessageAutoTalkDisabledExit', __('mpu_nextmsg: 自動会話が無効のため終了します', 'mp-ukagaka'));
        /* translators: debug console log. %s is the trigger that was skipped. */
        $log_i18n->debug('nextMessageSkippedUnawokenSleepMode', __('🌙 睡眠モードでまだ目を覚ましていないため、%s トリガーの会話をスキップし OK ボタンのみ受け付けます', 'mp-ukagaka'));
        /* translators: debug console log. Startup or auto next message is skipped while page-aware AI is active. */
        $log_i18n->debug('nextMessageSkippedPageAwareInProgress', __('mpu_nextmsg: ページ感知 AI が進行中のため、自動/起動会話をスキップします', 'mp-ukagaka'));
        /* translators: debug console log. Startup is skipped because page-aware AI is scheduled. */
        $log_i18n->debug('nextMessageSkippedScheduledPageAwareStartup', __('mpu_nextmsg: ページ感知が予約済みのため、BOT 会話の上書きを避けるため startup をスキップします', 'mp-ukagaka'));
        /* translators: debug console log. Startup or auto next message is skipped while greeting is active. */
        $log_i18n->debug('nextMessageSkippedGreetingInProgress', __('mpu_nextmsg: 初回訪問者への挨拶中のため、自動/起動会話をスキップします', 'mp-ukagaka'));
        /* translators: debug console log. Auto-triggered request is skipped because Ollama is busy. */
        $log_i18n->debug('nextMessageAutoSkippedOllamaBusy', __('mpu_nextmsg: Ollama がリクエストを処理中のため、自動トリガーのリクエストをスキップします', 'mp-ukagaka'));
        /* translators: debug console log. Request is queued because Ollama is busy. */
        $log_i18n->debug('nextMessageQueuedOllamaBusy', __('mpu_nextmsg: Ollama がリクエストを処理中のため、このリクエストをキューに追加します', 'mp-ukagaka'));
        /* translators: debug console log. Request is skipped because the queue is full. */
        $log_i18n->debug('nextMessageSkippedQueueFull', __('mpu_nextmsg: キューが満杯のため、このリクエストをスキップします', 'mp-ukagaka'));
        /* translators: debug console log. Next message will be generated by the LLM. */
        $log_i18n->debug('nextMessageUsingLlm', __('mpu_nextmsg: LLM を使用して会話を生成します', 'mp-ukagaka'));
        /* translators: debug console log. %s is the REST endpoint URL. */
        $log_i18n->debug('nextMessageSendingLlmPost', __('mpu_nextmsg: LLM POST リクエストを送信します: %s', 'mp-ukagaka'));
        /* translators: debug console log. %s is the raw LLM response object. */
        $log_i18n->debug('nextMessageLlmResponse', __('mpu_nextmsg: LLM 応答 = %s', 'mp-ukagaka'));
        /* translators: debug console log. LLM response display is skipped while page-aware AI is active. */
        $log_i18n->debug('nextMessageLlmResponseSkippedPageAwareInProgress', __('mpu_nextmsg: ページ感知 AI が進行中のため、LLM 応答の表示をスキップします', 'mp-ukagaka'));
        /* translators: debug console log. %s is the current chat history length. */
        $log_i18n->debug('nextMessageSpontaneousAddedToHistory', __('mpu_nextmsg: 自発会話を会話履歴に追加しました。現在の履歴長: %s', 'mp-ukagaka'));
        /* translators: debug console log. Chat history was saved. */
        $log_i18n->debug('nextMessageHistorySaved', __('mpu_nextmsg: 会話履歴を保存しました', 'mp-ukagaka'));
        /* translators: debug console log. Chat history cannot be saved because the save function is unavailable. */
        $log_i18n->debug('nextMessageSaveHistoryMissing', __('mpu_nextmsg: mpu_saveChatHistory 関数が存在しないため、会話履歴を保存できません', 'mp-ukagaka'));
        /* translators: debug console log. Chat history is unavailable or not an array. */
        $log_i18n->debug('nextMessageHistoryUnavailable', __('mpu_nextmsg: window.mpuChatHistory が未初期化、または配列ではないため、会話履歴に追加できません', 'mp-ukagaka'));
        /* translators: debug console log. Auto-talk timer waits for typewriter completion after LLM response. */
        $log_i18n->debug('nextMessageLlmCompleteWaitingForTypewriter', __('mpu_nextmsg: LLM 応答が完了しました。タイピング完了後に自動会話タイマーを開始します', 'mp-ukagaka'));
        /* translators: debug console log. Typewriter completed and auto-talk timer starts. */
        $log_i18n->debug('nextMessageTypewriterCompleteStartingAutoTalk', __('mpu_nextmsg: タイピングが完了しました。自動会話タイマーを開始します', 'mp-ukagaka'));
        /* translators: debug console log. LLM response object has no msg field. */
        $log_i18n->debug('nextMessageLlmResponseMissingMessage', __('mpu_nextmsg: LLM 応答に msg がありません', 'mp-ukagaka'));
        /* translators: debug console log. Fallback dialog is used because LLM response has no msg. */
        $log_i18n->debug('nextMessageLlmMissingMessageUsingFallback', __('mpu_nextmsg: LLM 応答に msg がないため、フォールバック会話を使用します', 'mp-ukagaka'));
        /* translators: debug console log. Timer waits for typewriter completion after fallback dialog. */
        $log_i18n->debug('nextMessageFallbackCompleteWaitingForTypewriter', __('mpu_nextmsg: フォールバックが完了しました。タイピング完了後にタイマーを開始します', 'mp-ukagaka'));
        /* translators: debug console log. Timer waits for typewriter completion after an error. */
        $log_i18n->debug('nextMessageErrorWaitingForTypewriter', __('mpu_nextmsg: エラーが発生しました。タイピング完了後にタイマーを開始します', 'mp-ukagaka'));
        /* translators: debug console log. LLM error handling is skipped while page-aware AI is active. */
        $log_i18n->debug('nextMessageLlmErrorSkippedPageAwareInProgress', __('mpu_nextmsg: ページ感知 AI が進行中のため、LLM エラー処理をスキップします', 'mp-ukagaka'));
        /* translators: debug console log. Next message waits for dialog data to load. */
        $log_i18n->debug('nextMessageWaitingForDialogLoad', __('mpu_nextmsg: 会話がまだ読み込まれていません。読み込み完了を待機します...', 'mp-ukagaka'));
        /* translators: debug console log. Dialog loading timed out after three retries. */
        $log_i18n->debug('nextMessageDialogLoadTimeout', __('mpu_nextmsg: 会話読み込みがタイムアウトしました。3 回再試行済みです', 'mp-ukagaka'));
        /* translators: debug console log. %1$s describes the dialog store, %2$s describes the message array. */
        $log_i18n->debug('nextMessageCannotDisplayDialog', __('mpu_nextmsg: 会話を表示できません - store=%1$s、msgArray=%2$s', 'mp-ukagaka'));
        /* translators: debug console log. Traditional dialog waits for typewriter completion before timer restart. */
        $log_i18n->debug('nextMessageTraditionalDialogWaitingForTypewriter', __('mpu_nextmsg: 通常会話です。タイピング完了後にタイマーを開始します', 'mp-ukagaka'));
        /* translators: debug console log. Fallback display is skipped while page-aware AI is active. */
        $log_i18n->debug('nextMessageFallbackSkippedPageAwareInProgress', __('mpu_nextmsg_fallback: ページ感知 AI が進行中のため、表示をスキップします', 'mp-ukagaka'));
        /* translators: debug console log. Fallback waits for dialog data to load. */
        $log_i18n->debug('nextMessageFallbackWaitingForDialogLoad', __('mpu_nextmsg_fallback: 会話がまだ読み込まれていません。読み込み完了を待機します...', 'mp-ukagaka'));
        /* translators: debug console log. Fallback dialog loading timed out after two retries. */
        $log_i18n->debug('nextMessageFallbackDialogLoadTimeout', __('mpu_nextmsg_fallback: 会話読み込みがタイムアウトしました。2 回再試行済みです', 'mp-ukagaka'));
        /* translators: debug console log. %1$s describes the dialog store, %2$s describes the message array. */
        $log_i18n->debug('nextMessageFallbackCannotDisplayDialog', __('mpu_nextmsg_fallback: フォールバック会話を表示できません - store=%1$s、msgArray=%2$s', 'mp-ukagaka'));
        /* translators: debug console log. Canvas manager is unexpectedly missing after Ajax success. */
        $log_i18n->debug('changeCanvasManagerMissingAfterAjax', __('mpuChange: Ajax 成功後に Canvas マネージャーが存在しないことが判明しました。これは想定外です', 'mp-ukagaka'));
        /* translators: debug console log. Anime initialization is skipped because Frieren mode is already active. */
        $log_i18n->debug('animeSkipFrierenReinit', __('⏭️ すでにフリーレンモードのため、再初期化をスキップします', 'mp-ukagaka'));
        /* translators: debug console log. Typewriter animation was interrupted. */
        $log_i18n->debug('typewriterInterrupted', __('タイピング効果を中断しました', 'mp-ukagaka'));
        /* translators: debug console log. The typewriter completion callback is not a function. */
        $log_i18n->debug('typewriterWaitCallbackInvalid', __('mpu_waitForTypewriterComplete: callback が関数ではありません', 'mp-ukagaka'));
        /* translators: debug console log. Typewriter wait timed out and callback will be forced. */
        $log_i18n->debug('typewriterWaitTimeoutForcingCallback', __('mpu_waitForTypewriterComplete: 待機がタイムアウトしたため、callback を強制実行します', 'mp-ukagaka'));
        /* translators: debug console log. jQuery is missing before jQuery.cookie initialization. */
        $log_i18n->debug('jqueryMissingForCookieInit', __('jQuery がまだ読み込まれていないため、jQuery.cookie を初期化できません', 'mp-ukagaka'));
        /* translators: debug console log. jQuery is missing before idle detection initialization. */
        $log_i18n->debug('jqueryMissingForIdleDetection', __('jQuery がまだ読み込まれていないため、無操作検出を初期化できません', 'mp-ukagaka'));
        /* translators: debug console log. %s is the idle threshold in seconds. */
        $log_i18n->debug('idleDetectionInitialized', __('無操作検出を初期化しました。しきい値：%s 秒', 'mp-ukagaka'));
        /* translators: debug console log. Last visit timestamp was updated. */
        $log_i18n->debug('lastVisitTimeUpdated', __('最終訪問時刻を更新しました', 'mp-ukagaka'));
        /* translators: debug console log. %s is the caught error value. */
        $log_i18n->debug('lastVisitTimeUpdateFailed', __('最終訪問時刻を更新できませんでした：%s', 'mp-ukagaka'));
        /* translators: debug console log. %s is the caught error value. */
        $log_i18n->debug('lastVisitTimeReadFailed', __('最終訪問時刻を取得できませんでした：%s', 'mp-ukagaka'));
        /* translators: debug console log. %s is the request ID. This text appears in two cancel paths. */
        $log_i18n->debug('requestCancelled', __('リクエストをキャンセルしました：%s', 'mp-ukagaka'));
        /* translators: debug console log. %s is the request ID skipped by dedupe. */
        $log_i18n->debug('requestDeduped', __('リクエストを重複排除してスキップします：%s', 'mp-ukagaka'));
        /* translators: debug console log. %s is the timed-out request ID. */
        $log_i18n->debug('requestTimedOut', __('リクエストがタイムアウトしました：%s', 'mp-ukagaka'));
        /* translators: debug console log. %1$s is current attempt, %2$s is max retries, %3$s is request ID. */
        $log_i18n->debug('requestRetrying', __('リクエストを再試行します（%1$s/%2$s）：%3$s', 'mp-ukagaka'));
        /* translators: debug console log. REST nonce was refreshed from a response. */
        $log_i18n->debug('restNonceAutoUpdated', __('REST Nonce を自動更新しました', 'mp-ukagaka'));
        /* translators: debug console log. %s is the network error message. */
        $log_i18n->debug('networkErrorWillRetry', __('ネットワークエラーのため再試行します：%s', 'mp-ukagaka'));
        /* translators: debug console log. jQuery is missing before context-menu initialization. */
        $log_i18n->debug('jqueryMissingForContextMenu', __('jQuery がまだ読み込まれていないため、右クリックメニューを初期化できません', 'mp-ukagaka'));
        /* translators: debug console log. Context menu opens the character switch menu. */
        $log_i18n->debug('contextMenuTriggered', __('右クリックメニューが作動しました：キャラクター切り替えメニューを表示します', 'mp-ukagaka'));
        /* translators: debug console log. Character change function is unavailable. */
        $log_i18n->debug('changeFunctionMissing', __('mpuChange 関数が定義されていません', 'mp-ukagaka'));
        /* translators: debug console log. Context menu initialization completed. */
        $log_i18n->debug('contextMenuInitialized', __('右クリックメニューを初期化しました', 'mp-ukagaka'));
        /* translators: debug console log. Interactive chat mode was entered. */
        $log_i18n->debug('chatModeEntered', __('インタラクティブ会話モードに入りました', 'mp-ukagaka'));
        /* translators: debug console log. Interactive chat mode was exited. */
        $log_i18n->debug('chatModeExited', __('インタラクティブ会話モードを終了しました', 'mp-ukagaka'));
        /* translators: debug console log. User attempted to send while a response is pending. */
        $log_i18n->debug('chatWaitingForResponse', __('応答待ちです。しばらくお待ちください', 'mp-ukagaka'));
        /* translators: debug console log. Chat history was cleared. */
        $log_i18n->debug('chatHistoryCleared', __('会話履歴をクリアしました', 'mp-ukagaka'));
        /* translators: debug console log. %s is the user message text. */
        $log_i18n->debug('chatSendingUserMessage', __('ユーザーメッセージを送信します：%s', 'mp-ukagaka'));
        /* translators: debug console log. AI response is discarded because chat mode closed. */
        $log_i18n->debug('chatModeClosedDiscardAiResponse', __('会話モードが閉じているため、今回の AI 応答を破棄します', 'mp-ukagaka'));
        /* translators: debug console log. Error message is discarded because chat mode closed. */
        $log_i18n->debug('chatModeClosedDiscardError', __('会話モードが閉じているため、エラーメッセージを破棄します', 'mp-ukagaka'));
        /* translators: debug console log. %s is the current chat history length. */
        $log_i18n->debug('chatHistoryLoadedOnPageLoad', __('ページ読み込み：会話履歴を読み込みました。現在の記録数：%s', 'mp-ukagaka'));
        /* translators: debug console log. Button click is ignored while a decoration dialog is active. This text appears in two button handlers. */
        $log_i18n->debug('chatButtonIgnoredDecorationDialogActive', __('装飾品会話中のため、ボタンクリックを無視します', 'mp-ukagaka'));
        /* translators: debug console log. Button click is ignored while message blocking is active. This text appears in two button handlers. */
        $log_i18n->debug('chatButtonIgnoredMessageBlocking', __('メッセージがブロックされているため、ボタンクリックを無視します', 'mp-ukagaka'));
        /* translators: debug console log. Chat mode exit was requested from a control. */
        $log_i18n->debug('chatModeExitRequested', __('会話モードを終了します', 'mp-ukagaka'));
        /* translators: debug console log. Interactive chat mode initialization completed. */
        $log_i18n->debug('chatModeInitialized', __('インタラクティブ会話モードを初期化しました', 'mp-ukagaka'));
        /* translators: debug console log. Wake-up request is being prepared. */
        $log_i18n->debug('wakeRequestPreparing', __('🌅 キャラクターを起こします。リクエスト送信を準備しています...', 'mp-ukagaka'));
        /* translators: debug console log. Wake request is cancelled because identity data is missing. */
        $log_i18n->debug('wakeRequestCancelledMissingIdentity', __('目覚めリクエストをキャンセルしました：personality_id/ukagaka_num が不足しているため、人格状態の混乱を避けます', 'mp-ukagaka'));
        /* translators: debug console log. %s is the wake request response object. */
        $log_i18n->debug('wakeRequestSucceeded', __('目覚めに成功しました：%s', 'mp-ukagaka'));
        /* translators: debug console log. Wake-up was temporary during deep sleep. */
        $log_i18n->debug('wakeRequestTemporaryDuringDeepSleep', __('これは深い睡眠中の一時的な目覚めです', 'mp-ukagaka'));
        /* translators: debug console log. %s is the failed wake response object. */
        $log_i18n->debug('wakeRequestResponseFailed', __('目覚めリクエストの応答が失敗しました：%s', 'mp-ukagaka'));
        /* translators: debug console log. %s is the caught wake request error. */
        $log_i18n->debug('wakeRequestFailedNonBlocking', __('目覚めリクエストに失敗しましたが、通常動作には影響しません：%s', 'mp-ukagaka'));
        /* translators: debug console log. Page trigger configuration is empty. */
        $log_i18n->debug('contextTriggerPagesEmpty', __('mpu_check_page_trigger: triggerPages が空のため false を返します', 'mp-ukagaka'));
        /* translators: debug console log. %s values are the trigger conditions and current path. */
        $log_i18n->debug('contextTriggerConditionsCheck', __('mpu_check_page_trigger: チェック条件 = %s、path = %s', 'mp-ukagaka'));
        /* translators: debug console log. Additional object data describes the is_single exclusion check. */
        $log_i18n->debug('contextTriggerIsSingleExcludingHomeArchive', __('mpu_check_page_trigger: is_single チェック - ホームページ/アーカイブページを除外します', 'mp-ukagaka'));
        /* translators: debug console log. Additional object data describes the is_single trigger check. */
        $log_i18n->debug('contextTriggerIsSingleCheck', __('mpu_check_page_trigger: is_single チェック', 'mp-ukagaka'));
        /* translators: debug console log. Additional object data describes the is_page trigger check. */
        $log_i18n->debug('contextTriggerIsPageCheck', __('mpu_check_page_trigger: is_page チェック', 'mp-ukagaka'));
        /* translators: debug console log. Additional object data describes the home/front-page trigger check. */
        $log_i18n->debug('contextTriggerIsHomeFrontPageCheck', __('mpu_check_page_trigger: is_home/is_front_page チェック', 'mp-ukagaka'));
        /* translators: debug console log. Additional object data describes the archive trigger check. */
        $log_i18n->debug('contextTriggerIsArchiveCheck', __('mpu_check_page_trigger: is_archive チェック', 'mp-ukagaka'));
        /* translators: debug console log. Additional object data describes the category trigger check. */
        $log_i18n->debug('contextTriggerIsCategoryCheck', __('mpu_check_page_trigger: is_category チェック', 'mp-ukagaka'));
        /* translators: debug console log. Additional object data describes the tag trigger check. */
        $log_i18n->debug('contextTriggerIsTagCheck', __('mpu_check_page_trigger: is_tag チェック', 'mp-ukagaka'));
        /* translators: debug console log. Page-aware AI is skipped while chat mode is active. */
        $log_i18n->debug('contextChatSkippedChatMode', __('mpu_chat_context: 会話モード中のためページ感知 AI をスキップします', 'mp-ukagaka'));
        /* translators: debug console log. A new page-aware trigger is skipped while one is already running. */
        $log_i18n->debug('contextChatSkippedPageAwareInProgress', __('mpu_chat_context: ページ感知処理中のため新しいトリガーをスキップします', 'mp-ukagaka'));
        /* translators: debug console log. Page-aware AI is skipped during sleep mode. */
        $log_i18n->debug('contextChatSkippedSleepMode', __('🌙 睡眠モード（00:00-06:00）：ページ感知 AI をスキップし、キャラクターを休ませます', 'mp-ukagaka'));
        /* translators: debug console log. Additional object data describes the page context check. */
        $log_i18n->debug('contextChatPageContextCheck', __('mpu_chat_context: ページコンテキストチェック', 'mp-ukagaka'));
        /* translators: debug console log. Page-aware chat is skipped because both title and content are missing. */
        $log_i18n->debug('contextChatSkippedEmptyTitleContent', __('mpu_chat_context: タイトルと本文がないためスキップします', 'mp-ukagaka'));
        /* translators: debug console log. Page-aware chat is skipped during the first-visitor greeting. */
        $log_i18n->debug('contextChatSkippedFirstVisitorGreeting', __('mpu_chat_context: 初回訪問者への挨拶中のためスキップします', 'mp-ukagaka'));
        /* translators: debug console log. %s is the current content length. */
        $log_i18n->debug('contextChatSkippedShortContent', __('mpu_chat_context: 内容が 300 文字未満です（現在：%s）。スキップします', 'mp-ukagaka'));
        /* translators: debug console log. Page-aware chat was added to history and saved. */
        $log_i18n->debug('contextChatSavedToHistory', __('mpu_chat_context: 会話を履歴に追加して保存しました', 'mp-ukagaka'));
        /* translators: debug console warning. %s is the failed AI response object. */
        $log_i18n->debug('contextChatAiFailedUseDefault', __('AI 会話に失敗したため、既定の会話システムを使用します：%s', 'mp-ukagaka'));
        /* translators: debug console log label. The visitor information object is passed as a separate console argument. */
        $log_i18n->debug('contextVisitorInfo', __('訪問者情報：', 'mp-ukagaka'));
        /* translators: debug console log. jQuery ready handler has run. */
        $log_i18n->debug('featuresJqueryReady', __('jQuery ready を実行しました', 'mp-ukagaka'));
        /* translators: debug console log. jQuery.cookie initialization succeeded. */
        $log_i18n->debug('featuresJqueryCookieInitialized', __('jQuery.cookie を正常に初期化しました', 'mp-ukagaka'));
        /* translators: debug console log. Built-in dialogue remains loaded as a fallback when LLM replacement is enabled. */
        $log_i18n->debug('featuresLlmReplaceDialogueFallbackLoaded', __('LLM 置換会話が有効ですが、フォールバックとして内蔵会話も読み込みます', 'mp-ukagaka'));
        /* translators: debug console log. Duplicate settings processing is skipped. */
        $log_i18n->debug('featuresProcessSettingsAlreadyHandled', __('processSettings: 設定は処理済みのため、重複呼び出しをスキップします', 'mp-ukagaka'));
        /* translators: debug console warning. %s is the invalid settings response object. */
        $log_i18n->debug('featuresGetSettingsInvalidResponse', __('mpu_get_settings: 無効な応答です：%s', 'mp-ukagaka'));
        /* translators: debug console log. %s is the serialized settings response. */
        $log_i18n->debug('featuresGetSettingsReceived', __('mpu_get_settings: 設定を受信しました = %s', 'mp-ukagaka'));
        /* translators: debug console log. %s is the auto-talk enabled flag. */
        $log_i18n->debug('featuresGetSettingsAutoTalkSet', __('mpu_get_settings: mpuAutoTalk を設定しました = %s', 'mp-ukagaka'));
        /* translators: debug console log. %1$s is the adjusted interval in ms, %2$s is the original interval in ms. */
        $log_i18n->debug('featuresSleepModeIntervalAdjusted', __('🌙 睡眠モードが有効です（00:00~06:00）。間隔を %1$s ms に調整しました（元：%2$s ms）', 'mp-ukagaka'));
        /* translators: debug console log. %s is the auto-talk interval in ms. */
        $log_i18n->debug('featuresGetSettingsAutoTalkIntervalSet', __('mpu_get_settings: mpuAutoTalkInterval を設定しました = %s ms', 'mp-ukagaka'));
        /* translators: debug console log. %1$s and %2$s are enabled/disabled labels. */
        $log_i18n->debug('featuresLlmReplaceDialogueSettings', __('LLM 置換会話設定：%1$s、インタラクティブ会話モード：%2$s', 'mp-ukagaka'));
        /* translators: debug console log. Initial LLM dialogue trigger is skipped during sleep mode. */
        $log_i18n->debug('featuresSleepModeSkipInitialLlmTrigger', __('🌙 睡眠モード：初回 LLM 会話トリガーをスキップし、睡眠メッセージの表示を維持します', 'mp-ukagaka'));
        /* translators: debug console log. %s is the approximate number of seconds until auto-talk triggers. */
        $log_i18n->debug('featuresSleepMessageRetainedUntilAutoTalk', __('🌙 睡眠メッセージは自動会話タイマーが作動するまで表示を維持します（約 %s 秒後）', 'mp-ukagaka'));
        /* translators: debug console log. LLM dialogue trigger waits for the initial message to finish. */
        $log_i18n->debug('featuresLlmReplaceDialogueDelayInitialTrigger', __('LLM 置換会話が有効です。初期メッセージの完了後に LLM 会話をトリガーします', 'mp-ukagaka'));
        /* translators: debug console log. %1$s is the auto-talk flag, %2$s is whether auto-talk should be delayed. */
        $log_i18n->debug('featuresAutoTalkTogglePreparing', __('mpu_get_settings: startAutoTalk/stopAutoTalk の呼び出しを準備します。mpuAutoTalk=%1$s、shouldDelayAutoTalk=%2$s', 'mp-ukagaka'));
        /* translators: debug console log. %s is the configured page-aware trigger condition. */
        $log_i18n->debug('featuresPageAwareAiEnabled', __('ページ感知 AI が有効です。トリガーページ条件 = %s', 'mp-ukagaka'));
        /* translators: debug console log. %s is the page-aware trigger result. */
        $log_i18n->debug('featuresPageAwareTriggerCheckResult', __('ページ感知チェック結果：shouldTrigger = %s', 'mp-ukagaka'));
        /* translators: debug console log. %1$s is probability percent, %2$s is the random roll, %3$s is the trigger result. */
        $log_i18n->debug('featuresPageAwareProbabilityCheck', __('ページ感知の確率チェック：設定確率=%1$s%%、ロール=%2$s、トリガー=%3$s', 'mp-ukagaka'));
        /* translators: debug console log. Page-aware AI trigger has been scheduled. */
        $log_i18n->debug('featuresPageAwareAiTriggerScheduled', __('ページ感知 AI は 3 秒後にトリガーされます', 'mp-ukagaka'));
        /* translators: debug console log. Page-aware AI is skipped after failing probability check. */
        $log_i18n->debug('featuresPageAwareAiProbabilitySkipped', __('ページ感知 AI は確率チェックを通過しなかったため、トリガーしません', 'mp-ukagaka'));
        /* translators: debug console log. Page-aware AI is skipped after failing page type check. */
        $log_i18n->debug('featuresPageAwareAiPageTypeSkipped', __('ページ感知 AI はページタイプチェックを通過しなかったため、トリガーしません', 'mp-ukagaka'));
        /* translators: debug console log. %s is the ai_enabled flag. */
        $log_i18n->debug('featuresPageAwareAiDisabled', __('ページ感知 AI は有効ではありません（ai_enabled = %s）', 'mp-ukagaka'));
        /* translators: debug console log. Preloaded settings data is being used. */
        $log_i18n->debug('featuresUsingPreloadedSettings', __('プリロード済み設定データを使用します', 'mp-ukagaka'));
        /* translators: debug console log. Settings are obtained from the mpuInitComplete event. */
        $log_i18n->debug('featuresSettingsFromInitComplete', __('mpuInitComplete イベントから設定を取得します', 'mp-ukagaka'));
        /* translators: debug console log. Fallback settings request is sent separately. */
        $log_i18n->debug('featuresFallbackGetSettingsAjax', __('Fallback: 独立した mpu_get_settings AJAX を送信します', 'mp-ukagaka'));
        /* translators: debug console log. Additional argument is the SPA navigation URL. */
        $log_i18n->debug('featuresSpaNavigationContentChanged', __('🔄 SPA ナビゲーション：ページ内容が変更されました', 'mp-ukagaka'));
        /* translators: debug console log. %1$s is probability, %2$s is the random roll, %3$s is the trigger result. */
        $log_i18n->debug('featuresSpaPageAwareProbabilityCheck', __('🎲 SPA ページ感知チェック：確率=%1$s、ロール=%2$s、トリガー=%3$s', 'mp-ukagaka'));
        /* translators: debug console log. Feature script loading has completed. */
        $log_i18n->debug('featuresScriptLoaded', __('スクリプトの読み込みが完了しました', 'mp-ukagaka'));
        /* translators: debug console warning. %s is the caught emoji config load error. */
        $log_i18n->debug('frierenEmojiConfigLoadFailed', __('mpuEmojiManager: 表情設定を読み込めませんでした：%s', 'mp-ukagaka'), ['scope' => 'frieren']);
        /* translators: debug console log. Emoji base path is not configured. */
        $log_i18n->debug('frierenEmojiBasePathMissing', __('mpuEmojiManager: 表情のベースパスが設定されていません', 'mp-ukagaka'), ['scope' => 'frieren']);
        /* translators: debug console log. The ukagaka image container is missing. */
        $log_i18n->debug('frierenEmojiContainerMissing', __('mpuEmojiManager: #ukagaka_img コンテナが見つかりません', 'mp-ukagaka'), ['scope' => 'frieren']);
        /* translators: debug console warning. %s is the failed emoji image URL. */
        $log_i18n->debug('frierenEmojiImageLoadFailed', __('mpuEmojiManager: 表情画像の読み込みに失敗しました：%s', 'mp-ukagaka'), ['scope' => 'frieren']);
        /* translators: debug console log. %s is the emoji name being shown. */
        $log_i18n->debug('frierenEmojiShown', __('mpuEmojiManager: 表情を表示します：%s', 'mp-ukagaka'), ['scope' => 'frieren']);
        /* translators: debug console log. Emoji display is removed. */
        $log_i18n->debug('frierenEmojiRemoved', __('mpuEmojiManager: 表情を削除します', 'mp-ukagaka'), ['scope' => 'frieren']);
        /* translators: debug console log. Shows which Frieren base image was selected. */
        $log_i18n->debug('frierenSleepIdleImageSelected', __('🌙 睡眠画像 frieren[s].png を表示します / ☀️ ゴースト画像 frieren[0].png を表示します', 'mp-ukagaka'), ['scope' => 'frieren']);
        /* translators: debug console log. Decoration loading is skipped because it already completed. */
        $log_i18n->debug('frierenDecorationsAlreadyLoaded', __('装飾品は読み込み済みのため、重複読み込みをスキップします', 'mp-ukagaka'), ['scope' => 'frieren']);
        /* translators: debug console log. %s is the number of decorations loaded from JSON config. */
        $log_i18n->debug('frierenDecorationConfigLoaded', __('JSON 設定から %s 個の装飾品を読み込みました', 'mp-ukagaka'), ['scope' => 'frieren']);
        /* translators: debug console log. %s is the decoration type whose transparent area was clicked. */
        $log_i18n->debug('frierenDecorationTransparentClickIgnored', __('透明領域がクリックされたため無視します：%s', 'mp-ukagaka'), ['scope' => 'frieren']);
        /* translators: debug console log. %s is the decoration type hit by pixel detection. */
        $log_i18n->debug('frierenDecorationPixelHitClicked', __('装飾品がクリックされました（ピクセルヒット）：%s', 'mp-ukagaka'), ['scope' => 'frieren']);
        /* translators: debug console log. %1$s is the decoration type, %2$s is the canvas size. */
        $log_i18n->debug('frierenPixelDetectionCanvasCreated', __('ピクセル検出 Canvas を作成しました：%1$s、%2$s', 'mp-ukagaka'), ['scope' => 'frieren']);
        /* translators: debug console log. Values are decoration type, pixel coordinates, alpha, and hit threshold. */
        $log_i18n->debug('frierenPixelDetectionSample', __('ピクセル検出：%1$s、x=%2$s、y=%3$s、alpha=%4$s、threshold=%5$s', 'mp-ukagaka'), ['scope' => 'frieren']);
        /* translators: debug console log. Frieren has awakened; forceWakeUp may be appended as a separate suffix. */
        $log_i18n->debug('frierenAwakened', __('☀️ フリーレンが目を覚ましました！', 'mp-ukagaka'), ['scope' => 'frieren']);
        /* translators: debug console log. Wake animation frames are being played. */
        $log_i18n->debug('frierenWakeAnimationPlaying', __('👀 目覚めアニメーション frieren[w1-w5].png を再生します', 'mp-ukagaka'), ['scope' => 'frieren']);
        /* translators: debug console log. %s is whether the book-flip animation should be skipped. */
        $log_i18n->debug('frierenWakeAnimationStarted', __('🌅 目覚めアニメーションを開始します。skipBookFlip = %s', 'mp-ukagaka'), ['scope' => 'frieren']);
        /* translators: debug console log. Book-flip animation plays after wake. */
        $log_i18n->debug('frierenPostWakeBookFlipPlaying', __('📖 目覚め後にページめくりアニメーションを再生します', 'mp-ukagaka'), ['scope' => 'frieren']);
        /* translators: debug console log. Book-flip animation is skipped after wake. */
        $log_i18n->debug('frierenPostWakeBookFlipSkipped', __('📖 目覚め後のページめくりアニメーションをスキップします', 'mp-ukagaka'), ['scope' => 'frieren']);
        /* translators: debug console log. Book-flip animation is skipped during sleep mode. */
        $log_i18n->debug('frierenSleepModeBookFlipSkipped', __('🌙 睡眠モード：ページめくりアニメーションをスキップします', 'mp-ukagaka'), ['scope' => 'frieren']);
        /* translators: debug console log. Book-flip animation is skipped for manual wake dialogue. */
        $log_i18n->debug('frierenManualWakeDialogueBookFlipSkipped', __('📖 手動の目覚め会話：ページめくりアニメーションをスキップします', 'mp-ukagaka'), ['scope' => 'frieren']);
        /* translators: debug console log. %s is the decoration type passed to the click handler. */
        $log_i18n->debug('frierenDecorationClickHandled', __('handleDecorationClick が呼び出されました。装飾品タイプ：%s', 'mp-ukagaka'), ['scope' => 'frieren']);
        /* translators: debug console log. Decoration click is ignored while a decoration dialog is active. */
        $log_i18n->debug('frierenDecorationClickIgnoredDialogActive', __('装飾品会話中のため、今回のクリックを無視します', 'mp-ukagaka'), ['scope' => 'frieren']);
        /* translators: debug console log. Decoration dialog is skipped because AI is disabled. */
        $log_i18n->debug('frierenDecorationDialogSkippedAiDisabled', __('AI 機能が有効ではないため、装飾品会話をスキップします', 'mp-ukagaka'), ['scope' => 'frieren']);
        /* translators: debug console log. Additional argument is the cancelled decoration click request ID. */
        $log_i18n->debug('frierenDecorationClickRequestCancelled', __('装飾品クリック：リクエストをキャンセルしました', 'mp-ukagaka'), ['scope' => 'frieren']);
        /* translators: debug console log. Decoration dialog completed and state was restored. */
        $log_i18n->debug('frierenDecorationDialogCompletedStateRestored', __('装飾品会話が完了し、状態を復元しました', 'mp-ukagaka'), ['scope' => 'frieren']);
        /* translators: debug console log. %1$s is the zone name, %2$s is the relative Y coordinate. */
        $log_i18n->debug('frierenTouchZoneDetected', __('タッチ領域検出：%1$s、relativeY=%2$s', 'mp-ukagaka'), ['scope' => 'frieren']);
        /* translators: debug console log. %s is the handled touch zone name. */
        $log_i18n->debug('frierenTouchZoneHandled', __('handleTouchZone が呼び出されました。領域：%s', 'mp-ukagaka'), ['scope' => 'frieren']);
        /* translators: debug console log. %s is the touch zone currently in cooldown. */
        $log_i18n->debug('frierenTouchZoneClickIgnoredCooldown', __('領域がクールダウン中のため、クリックを無視します：%s', 'mp-ukagaka'), ['scope' => 'frieren']);
        /* translators: debug console log. %s is the touch zone entering cooldown after reaching its click limit. */
        $log_i18n->debug('frierenTouchZoneClickLimitReached', __('領域のクリック上限に達したため、クールダウンに入ります：%s', 'mp-ukagaka'), ['scope' => 'frieren']);
        /* translators: debug console log. Additional argument is the cancelled touch zone request ID. */
        $log_i18n->debug('frierenTouchZoneRequestCancelled', __('タッチ領域クリック：リクエストをキャンセルしました', 'mp-ukagaka'), ['scope' => 'frieren']);
        /* translators: debug console log. Character touch event handlers have been bound. */
        $log_i18n->debug('frierenTouchEventsBound', __('キャラクターのタッチイベントを設定しました', 'mp-ukagaka'), ['scope' => 'frieren']);
        /* translators: debug console log. %1$s is the touch zone name, %2$s is the cooldown duration in seconds. */
        $log_i18n->debug('frierenTouchZoneCooldownStarted', __('領域がクールダウンに入りました：%1$s、クールダウン時間：%2$s 秒', 'mp-ukagaka'), ['scope' => 'frieren']);
        /* translators: debug console log. Loading message display is skipped because the sleep message is active. */
        $log_i18n->debug('dialogLoadingMessageSkippedSleepMessage', __('🌙 睡眠モード：睡眠メッセージを検出したため、読み込みメッセージの表示をスキップします', 'mp-ukagaka'));
        /* translators: debug console warning. External dialogue file has no usable content. */
        $log_i18n->debug('dialogExternalFileEmpty', __('loadExternalDialog: 会話ファイルが空です', 'mp-ukagaka'));
        /* translators: debug console log. Dialogue file is empty while LLM replacement mode is active. */
        $log_i18n->debug('dialogExternalFileEmptyLlmFallback', __('loadExternalDialog: LLM 置換会話モードのため会話ファイルが空です。LLM 生成に依存します', 'mp-ukagaka'));
        /* translators: debug console log. Fallback dialogue data loaded but first line display is suppressed. */
        $log_i18n->debug('dialogFallbackLoadedFirstLineSuppressed', __('loadExternalDialog: LLM 置換会話モードでフォールバック会話データを読み込みましたが、最初の一文は表示しません', 'mp-ukagaka'));
        /* translators: debug console log. Duplicate display of the first dialogue line was blocked. */
        $log_i18n->debug('dialogFirstLineDuplicateBlocked', __('loadExternalDialog: 最初の会話文を重複表示しようとしたため阻止しました', 'mp-ukagaka'));
        /* translators: debug console log. First built-in dialogue is skipped while asleep and not awakened. */
        $log_i18n->debug('dialogFirstLineSkippedUnawokenSleepMode', __('🌙 睡眠モードでまだ目を覚ましていないため、最初の内蔵会話をスキップし、睡眠メッセージを維持します', 'mp-ukagaka'));
        /* translators: debug console warning. %s is the backend error message. */
        $log_i18n->debug('dialogBackendErrorUseEmptyFallback', __('loadExternalDialog: バックエンドがエラーを返したため、空の mpuMsgList をフォールバックとして設定します - %s', 'mp-ukagaka'));
        /* translators: debug console warning. Dialogue loading failed and an empty message list is used as fallback. */
        $log_i18n->debug('dialogLoadFailedUseEmptyFallback', __('loadExternalDialog: 読み込みに失敗したため、空の mpuMsgList をフォールバックとして設定します', 'mp-ukagaka'));
        /* translators: debug console log. Emoji base path is not configured. */
        $log_i18n->debug('emojiBasePathMissing', __('mpuEmojiManager: 表情のベースパスが設定されていません', 'mp-ukagaka'));
        /* translators: debug console log. The ukagaka image container is missing. */
        $log_i18n->debug('emojiContainerMissing', __('mpuEmojiManager: #ukagaka_img コンテナが見つかりません', 'mp-ukagaka'));
        /* translators: debug console warning. %s is the failed emoji image URL. */
        $log_i18n->debug('emojiImageLoadFailed', __('mpuEmojiManager: 表情画像の読み込みに失敗しました：%s', 'mp-ukagaka'));
        /* translators: debug console log. %s is the emoji name being shown. */
        $log_i18n->debug('emojiShown', __('mpuEmojiManager: 表情を表示します：%s', 'mp-ukagaka'));
        /* translators: debug console log. Emoji display is removed. */
        $log_i18n->debug('emojiRemoved', __('mpuEmojiManager: 表情を削除します', 'mp-ukagaka'));
        /* translators: debug console log. First-visitor greeting is skipped during sleep mode. */
        $log_i18n->debug('greetingSkippedSleepMode', __('🌙 睡眠モード：初回訪問者への挨拶をスキップし、キャラクターを休ませます', 'mp-ukagaka'));
        /* translators: debug console log label. The visitor information object is passed as a separate console argument for first-visitor greeting. */
        $log_i18n->debug('greetingVisitorInfo', __('訪問者情報：', 'mp-ukagaka'));
        /* translators: debug console log. First-visitor greeting was added to history and saved. */
        $log_i18n->debug('greetingSavedToHistory', __('mpu_greet_first_visitor: 挨拶を履歴に追加して保存しました', 'mp-ukagaka'));
        /* translators: debug console warning. Chat history cannot be saved because the save function is missing. */
        $log_i18n->debug('greetingSaveHistoryFunctionMissing', __('mpu_greet_first_visitor: mpu_saveChatHistory 関数が存在しないため、会話履歴を保存できません', 'mp-ukagaka'));
        /* translators: debug console warning. Greeting cannot be added because chat history is unavailable. */
        $log_i18n->debug('greetingChatHistoryUnavailable', __('mpu_greet_first_visitor: window.mpuChatHistory が初期化されていないか配列ではないため、会話履歴に追加できません', 'mp-ukagaka'));
        /* translators: debug console warning. %s is the failed first-visitor greeting response object. */
        $log_i18n->debug('greetingFirstVisitorFailed', __('初回訪問者への挨拶に失敗しました：%s', 'mp-ukagaka'));
        /* translators: debug console log. Browser reload cleared chat history and the chat session ID. */
        $log_i18n->debug('pageReloadClearedChatSession', __('🔄 ページの再読み込みを検出したため、会話履歴とセッション ID をクリアしました', 'mp-ukagaka'));
    }

    $l10n = [
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
        // Stream state badge labels（v2.18 #4：runtime 驗收用，data-mpu-stream-state 對應）
        'streamStates' => [
            'thinking'  => __('考え中…', 'mp-ukagaka'),
            'streaming' => __('応答中…', 'mp-ukagaka'),
            'tool'      => __('調べてる…', 'mp-ukagaka'),
            'status'    => __('応答中…', 'mp-ukagaka'),
            'error'     => __('エラー', 'mp-ukagaka'),
            'timeout'   => __('タイムアウト', 'mp-ukagaka'),
            'busy'      => __('混雑中…', 'mp-ukagaka'),
        ],
        'logs' => $log_i18n ? $log_i18n->logs() : [],
    ];

    if (mpu_is_frontend_debug_mode()) {
        $l10n['logsDebug'] = $log_i18n ? $log_i18n->logs_debug() : [];
    }

    wp_localize_script($l10n_handle, 'mpuL10n', $l10n);
}
add_action('wp_enqueue_scripts', 'mpu_enqueue_frontend_assets');

/**
 * Determine whether front-end debug behavior should be enabled.
 *
 * Keep this as the single source of truth for browser debug logging and future
 * debug-only localized payloads. WP_DEBUG alone is intentionally not enough,
 * because anonymous visitors should not receive verbose diagnostic output.
 */
function mpu_is_frontend_debug_mode()
{
    $enabled = defined('WP_DEBUG') && WP_DEBUG && current_user_can('manage_options');

    return (bool) apply_filters('mpu_frontend_debug_mode', $enabled);
}

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
    echo 'window.mpuDebugMode = ' . wp_json_encode(mpu_is_frontend_debug_mode()) . ";\n";
    // Token 不再嵌入 HTML（避免 full-page cache 把第一訪客 token 送給他人）
    // JS 會在首次 API 呼叫前透過 /session-token 端點懶取得
    echo "var mpuSessionToken = null;\n";
    $mpu_page_context = [
        'postId' => is_singular() ? (int) get_queried_object_id() : 0,
    ];
    echo 'var mpuPageContext = ' . wp_json_encode($mpu_page_context) . ";\n";

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
    echo "    is_admin: " . (current_user_can('manage_options') ? 'true' : 'false') . ",\n";
    echo "    rest_nonce: '" . (current_user_can('manage_options') ? esc_js(wp_create_nonce('wp_rest')) : '') . "'\n";
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
