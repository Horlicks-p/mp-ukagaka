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
 * 判斷是否顯示春菜
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
    if (in_array($GLOBALS["pagenow"], ["wp-login.php", "wp-register.php"])) {
        return false;
    }

    $url = isset($_SERVER["HTTP_HOST"], $_SERVER["REQUEST_URI"])
        ? "http" .
        (is_ssl() ? "s" : "") .
        "://" .
        $_SERVER["HTTP_HOST"] .
        $_SERVER["REQUEST_URI"]
        : "";

    // 修正：如果網址為空，直接返回 true (通常不會發生，但以防萬一)
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

    // 檢查 ukagaka 是否為空，避免錯誤
    if (empty($ukagaka)) {
        return "";
    }

    // 使用已定義的常量獲取主文件路徑
    $main_file = defined('MPU_MAIN_FILE') ? MPU_MAIN_FILE : dirname(dirname(__FILE__)) . '/mp-ukagaka.php';
    $ok_png = esc_url(plugins_url("images/ok_hover.png", $main_file));
    $cancel_png = esc_url(plugins_url("images/cancel_hover.png", $main_file));

    $html = "";

    // 一律使用外部文件模式，不再從內部對話讀取
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
        esc_attr(function_exists('mpu_is_llm_replace_dialogue_enabled') && mpu_is_llm_replace_dialogue_enabled()
            ? __("（えっと…何話せばいいかな…）", "mp-ukagaka")
            : __("（思考中…）", "mp-ukagaka")) .
        '"></div>
                <div id="ukagaka_msgnum" style="display:none;">0</div>
                <div id="ukagaka_msglist" style="display:none;" data-file="' .
        esc_attr($data_file) .
        '" data-load-external="true"></div>
                <div class="ukagaka-msgbox-border">
                    <a onclick="mpu_nextmsg(\'\')" href="javascript:void(0);" alt="Next">
                        <img style="margin-top:14px;margin-left:65px" src="' .
        $ok_png .
        '" width="28" height="28" />
                    </a>
                    <a onclick="mpu_hidemsg(\'\')" href="javascript:void(0);" alt="Cancel">
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
                <li class="gotop"><a id="toTop" href="javascript:void(0);" title="転移">' .
        __("回到頂部 ▼", "mp-ukagaka") .
        '</a></li>
                <li class="hide"><a id="remove" href="javascript:void(0);" title="ログアウト？">' .
        __("隱藏春菜 ▼", "mp-ukagaka") .
        '</a></li>
                <li class="change"><a onclick="mpuChange(\'\')" href="javascript:void(0);" title="モードチェンジ">' .
        __("更換春菜", "mp-ukagaka") .
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
        $main_file = defined('MPU_MAIN_FILE') ? MPU_MAIN_FILE : dirname(dirname(__FILE__)) . '/mp-ukagaka.php';
        wp_enqueue_style(
            'mpu-style',
            plugins_url('mpu_style.css', $main_file),
            array(),
            MPU_VERSION
        );
    }

    // 載入 JavaScript
    // 使用已定義的常量獲取主文件路徑
    $main_file = defined('MPU_MAIN_FILE') ? MPU_MAIN_FILE : dirname(dirname(__FILE__)) . '/mp-ukagaka.php';
    // 先載入核心文件
    wp_enqueue_script(
        'mpu-ukagaka-core',
        plugins_url('ukagaka-core.js', $main_file),
        array('jquery'), // 依賴 jQuery
        MPU_VERSION,
        true // 在頁尾載入
    );
    // 再載入動畫模組（依賴核心文件）
    wp_enqueue_script(
        'mpu-ukagaka-anime',
        plugins_url('ukagaka-anime.js', $main_file),
        array('jquery', 'mpu-ukagaka-core'), // 依賴 jQuery 和核心文件
        MPU_VERSION,
        true // 在頁尾載入
    );
    // 最後載入功能模組（依賴核心文件和動畫模組）
    wp_enqueue_script(
        'mpu-ukagaka-features',
        plugins_url('ukagaka-features.js', $main_file),
        array('jquery', 'mpu-ukagaka-core', 'mpu-ukagaka-anime'), // 依賴 jQuery、核心文件和動畫模組
        MPU_VERSION,
        true // 在頁尾載入
    );
}
add_action('wp_enqueue_scripts', 'mpu_enqueue_frontend_assets');


/**
 * 在 <head> 標籤中的處理
 * 移除 JS/CSS 檔案的直接輸出，僅保留 JS 變數和內聯邏輯
 */
function mpu_head()
{
    if (!mpu_is_show_page()) {
        return;
    }

    $mpu_opt = mpu_get_option();

    // CSS 和 JS 檔案的 <link> <script> 輸出已移除（由 mpu_enqueue_frontend_assets 處理）

    // 定義 JS 全域變數（ukagaka.js 依賴這些變數）
    $robot_show = mpu_js_filter(__("顯示春菜 ▲", "mp-ukagaka"));
    $robot_hide = mpu_js_filter(__("隱藏春菜 ▼", "mp-ukagaka"));
    $msg_show = mpu_js_filter(__("顯示會話 ▲", "mp-ukagaka"));
    $msg_hide = mpu_js_filter(__("隱藏會話 ▼", "mp-ukagaka"));

    echo "<script type=\"text/javascript\">\n";
    echo "var mpuurl = '" . esc_url(admin_url('admin-ajax.php')) . "';\n";
    echo "var mpuNonce = '" . wp_create_nonce('mpu_ajax_nonce') . "';\n";
    echo "var mpuInfo = {
        robot: ['{$robot_show}', '{$robot_hide}'],
        msg: ['{$msg_show}', '{$msg_hide}']
    };\n";

    // 預先輸出 LLM 設定，供前端判斷是否跳過內建對話載入
    $ollama_replace = function_exists('mpu_is_llm_replace_dialogue_enabled')
        ? mpu_is_llm_replace_dialogue_enabled()
        : false;
    echo "var mpuPreSettings = {\n";
    echo "    ollama_replace: " . ($ollama_replace ? 'true' : 'false') . "\n";
    echo "};\n";

    // 獲取當前春菜的 shell_info 用於初始化 Canvas
    $ukagaka_num = $mpu_opt["cur_ukagaka"] ?? "default_1";
    $shell_info = mpu_get_shell_info($ukagaka_num);
    $ukagaka_name = $mpu_opt["ukagakas"][$ukagaka_num]["name"] ?? "";

    // mpu_getCookie 在 footer 載入，需在 DOM ready 後執行
    echo '
    jQuery(document).ready(function($) {
        // 初始化 Canvas（延遲執行，確保 Canvas 元素已存在）
        if (typeof window.mpuCanvasManager !== "undefined" && $("#cur_ukagaka").length > 0) {
            var shellInfo = ' . wp_json_encode($shell_info) . ';
            window.mpuCanvasManager.init(shellInfo, ' . wp_json_encode($ukagaka_name) . ');
        }
        
        var showRobot = mpu_getCookie("mpuRobot");
        var showMsg   = mpu_getCookie("mpuMsg");
        if (showRobot==null) {';
    if (empty($mpu_opt["show_ukagaka"])) {
        // 預設隱藏
        echo '
            $("#show_ukagaka").html(mpuInfo.robot[0]); 
            $("#ukagaka").fadeOut(400);';
    }
    echo '
        } else if (showRobot=="hidden") { // Cookie 記住隱藏狀態
            $("#show_ukagaka").html(mpuInfo.robot[0]); 
            $("#ukagaka").fadeOut(400);
        }
        if (showMsg==null) {';
    if (empty($mpu_opt["show_msg"])) {
        // 預設隱藏
        echo '
            $("#show_msg").html(mpuInfo.msg[0]); 
            $("#ukagaka_msgbox").fadeOut(400);';
    }
    echo '
        } else if (showMsg=="hidden") { // Cookie 記住隱藏狀態
            $("#show_msg").html(mpuInfo.msg[0]); 
            $("#ukagaka_msgbox").fadeOut(400);
        }
    });';

    // 擴展 JavaScript
    if (!empty($mpu_opt["extend"]["js_area"])) {
        echo stripslashes($mpu_opt["extend"]["js_area"]) . "\n";
    }

    echo "</script>\n";
}
add_action("wp_head", "mpu_head");
