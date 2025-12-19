<?php
// 定義插件的基本路徑和頁面
// 使用固定的 slug，而不是 plugin_basename(__FILE__)，因為檔案已移動到子資料夾
// WordPress 註冊的頁面 slug 是 'mp-ukagaka/options.php'
$base_name = 'mp-ukagaka/options.php';
$base_page = 'options-general.php?page=' . $base_name;
$text = '';

// 從 transient 獲取 admin-functions.php 處理的訊息（避免重複處理）
$admin_message = get_transient('mpu_admin_message');
if ($admin_message !== false) {
    $text = $admin_message;
    delete_transient('mpu_admin_message');
    // 如果已經有訊息，跳過後續的表單處理（避免重複）
    $skip_form_processing = true;
} else {
    $skip_form_processing = false;
}

// 獲取當前頁面編號，預設為 0
$cur_page = $_GET['cur_page'] ?? 0;
if (!is_numeric($cur_page) || ($cur_page < 0 || $cur_page > 6) || $cur_page == '') {
    $cur_page = 0;
}

// 處理刪除春菜的請求（不受 skip_form_processing 影響）
if (!$skip_form_processing && isset($_GET['del']) && $_GET['del'] != '') {
    $del = $_GET['del'];
    if ($del == str_replace('default', '', $del)) { // 檢查是否為預設春菜
        if (isset($mpu_opt['ukagakas'][$del])) {
            $name = $mpu_opt['ukagakas'][$del]['name'];
            unset($mpu_opt['ukagakas'][$del]); // 刪除指定的春菜
            update_option('mp_ukagaka', $mpu_opt); // 更新選項
            $message = (($name == '') ? __('春菜', 'mp-ukagaka') : $name) . __('已離你而去…', 'mp-ukagaka');
            $text = '<div class="updated"><p><strong>' . $message . '</strong></p></div>';
        } else {
            $text = '<div class="error"><p><strong>' . __('不存在此春菜喲', 'mp-ukagaka') . '</strong></p></div>';
        }
    } else {
        $text = '<div class="error"><p><strong>' . __('不允許趕走預設春菜喲', 'mp-ukagaka') . '</strong></p></div>';
    }
} elseif (!$skip_form_processing && isset($_POST['submit_llm'])) {
    // 驗證 Nonce
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'mp_ukagaka_settings')) {
        $text .= '<div class="error"><p>' . __('安全性檢查失敗。', 'mp-ukagaka') . '</p></div>';
    } else {
        // 處理 LLM 設定
        $mpu_opt = mpu_get_option();

        // 保存頁面感知開關
        $mpu_opt['ai_enabled'] = isset($_POST['ai_enabled']) && $_POST['ai_enabled'] ? true : false;

        // 保存提供商選擇（統一使用 llm_provider，同時保持 ai_provider 向後兼容）
        if (isset($_POST['llm_provider'])) {
            $provider = sanitize_text_field($_POST['llm_provider']);
            $mpu_opt['llm_provider'] = $provider;
            $mpu_opt['ai_provider'] = $provider; // 向後兼容
        }

        // 處理各提供商的 API Key（加密存儲）
        $gemini_key = isset($_POST['llm_gemini_api_key']) ? sanitize_text_field($_POST['llm_gemini_api_key']) : '';
        $openai_key = isset($_POST['llm_openai_api_key']) ? sanitize_text_field($_POST['llm_openai_api_key']) : '';
        $claude_key = isset($_POST['llm_claude_api_key']) ? sanitize_text_field($_POST['llm_claude_api_key']) : '';

        if (!empty($gemini_key) && !mpu_is_api_key_encrypted($gemini_key)) {
            $mpu_opt['llm_gemini_api_key'] = mpu_encrypt_api_key($gemini_key);
            // 向後兼容：同時保存到舊的設定鍵
            $mpu_opt['ai_api_key'] = $mpu_opt['llm_gemini_api_key'];
        }

        if (!empty($openai_key) && !mpu_is_api_key_encrypted($openai_key)) {
            $mpu_opt['llm_openai_api_key'] = mpu_encrypt_api_key($openai_key);
            // 向後兼容
            $mpu_opt['openai_api_key'] = $mpu_opt['llm_openai_api_key'];
        }

        if (!empty($claude_key) && !mpu_is_api_key_encrypted($claude_key)) {
            $mpu_opt['llm_claude_api_key'] = mpu_encrypt_api_key($claude_key);
            // 向後兼容
            $mpu_opt['claude_api_key'] = $mpu_opt['llm_claude_api_key'];
        }

        // 保存各提供商的模型選擇
        if (isset($_POST['llm_gemini_model'])) {
            $mpu_opt['llm_gemini_model'] = sanitize_text_field($_POST['llm_gemini_model']);
            $mpu_opt['gemini_model'] = $mpu_opt['llm_gemini_model']; // 向後兼容
        }
        if (isset($_POST['llm_openai_model'])) {
            $mpu_opt['llm_openai_model'] = sanitize_text_field($_POST['llm_openai_model']);
            $mpu_opt['openai_model'] = $mpu_opt['llm_openai_model']; // 向後兼容
        }
        if (isset($_POST['llm_claude_model'])) {
            $mpu_opt['llm_claude_model'] = sanitize_text_field($_POST['llm_claude_model']);
            $mpu_opt['claude_model'] = $mpu_opt['llm_claude_model']; // 向後兼容
        }

        // 保存 Ollama 設定
        if (isset($_POST['ollama_endpoint'])) {
            $mpu_opt['ollama_endpoint'] = sanitize_text_field($_POST['ollama_endpoint']);
        }
        if (isset($_POST['ollama_model'])) {
            $mpu_opt['ollama_model'] = sanitize_text_field($_POST['ollama_model']);
        }
        if (isset($_POST['ollama_disable_thinking'])) {
            $mpu_opt['ollama_disable_thinking'] = $_POST['ollama_disable_thinking'] ? true : false;
        }

        // 保存「使用 LLM 取代內建對話」設定（支援所有提供商）
        $mpu_opt['llm_replace_dialogue'] = isset($_POST['llm_replace_dialogue']) && $_POST['llm_replace_dialogue'] ? true : false;
        // 向後兼容：如果使用 Ollama 且啟用了取代對話，也設置 ollama_replace_dialogue
        if ($mpu_opt['llm_replace_dialogue'] && isset($mpu_opt['llm_provider']) && $mpu_opt['llm_provider'] === 'ollama') {
            $mpu_opt['ollama_replace_dialogue'] = true;
        }

        update_option('mp_ukagaka', $mpu_opt);
        $text = '<div class="updated"><p><strong>' . __('LLM 設定已儲存', 'mp-ukagaka') . '</strong></p></div>';
    }
} elseif (!$skip_form_processing && isset($_POST['submit_ai'])) {
    // 驗證 Nonce
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'mp_ukagaka_settings')) {
        $text = '<div class="error"><p><strong>' . __('安全性檢查失敗。', 'mp-ukagaka') . '</strong></p></div>';
    } else {
        // 只處理 AI 設定（頁面感知相關的設定），保留其他所有設定
        $mpu_opt = mpu_get_option(); // 獲取現有設定

        // 只更新頁面感知相關的設定（不處理提供商、API Key、模型選擇）
        $mpu_opt['ai_language'] = isset($_POST['ai_language']) ? sanitize_text_field($_POST['ai_language']) : 'zh-TW';
        $mpu_opt['ai_system_prompt'] = isset($_POST['ai_system_prompt']) ? sanitize_textarea_field($_POST['ai_system_prompt']) : '你是一個傲嬌的桌面助手「春菜」。你會用簡短、帶點傲嬌的語氣評論文章內容。回應請保持在 40 字以內。';
        $mpu_opt['ai_probability'] = isset($_POST['ai_probability']) ? max(1, min(100, intval($_POST['ai_probability']))) : 10;
        $mpu_opt['ai_trigger_pages'] = isset($_POST['ai_trigger_pages']) ? sanitize_text_field($_POST['ai_trigger_pages']) : 'is_single';
        $mpu_opt['ai_text_color'] = isset($_POST['ai_text_color']) ? sanitize_hex_color($_POST['ai_text_color']) : '#000000';
        $mpu_opt['ai_display_duration'] = isset($_POST['ai_display_duration']) ? max(1, min(60, intval($_POST['ai_display_duration']))) : 8;
        $mpu_opt['ai_greet_first_visit'] = isset($_POST['ai_greet_first_visit']) && $_POST['ai_greet_first_visit'] ? true : false;
        $mpu_opt['ai_greet_prompt'] = isset($_POST['ai_greet_prompt']) ? sanitize_textarea_field($_POST['ai_greet_prompt']) : '你是一個友善的桌面助手「春菜」。當有訪客第一次來到網站時，你會根據訪客的來源（referrer）用親切的語氣打招呼。回應請保持在 50 字以內。';

        update_option('mp_ukagaka', $mpu_opt);
        $text = '<div class="updated"><p><strong>' . __('AI 設定已儲存', 'mp-ukagaka') . '</strong></p></div>';
    }
} elseif (!$skip_form_processing && isset($_POST['submit2'])) {
    // 處理春菜的更改
    $ukagakas = $_POST['ukagakas'];
    foreach ($ukagakas as $key => $value) {
        $ukagakas[$key]['msg'] = mpu_str2array($ukagakas[$key]['msg']);
        $ukagakas[$key]['name'] = mpu_input_filter($ukagakas[$key]['name']);
        $ukagakas[$key]['shell'] = mpu_input_filter($ukagakas[$key]['shell']);
        $ukagakas[$key]['show'] = isset($ukagakas[$key]['show']) && $ukagakas[$key]['show'] ? true : false;

        // 檢查是否需要生成對話檔案
        if (isset($_POST['generate_dialog_file'][$key]) && $_POST['generate_dialog_file'][$key] == 'true') {
            // 獲取對話檔案名稱
            $dialog_filename = isset($ukagakas[$key]['dialog_filename']) ? sanitize_file_name($ukagakas[$key]['dialog_filename']) : sanitize_file_name($key);

            // 獲取檔案格式
            $ext = isset($mpu_opt['external_file_format']) ? $mpu_opt['external_file_format'] : 'txt';

            // 【安全性強化】使用安全文件生成函數
            mpu_generate_dialog_file($dialog_filename, $ukagakas[$key]['msg'], $ext);
        }
    }
    $mpu_opt['ukagakas'] = $ukagakas;
    update_option('mp_ukagaka', $mpu_opt);
    $message = __('春菜們已經煥然一新啦', 'mp-ukagaka');
    if (isset($_POST['generate_dialog_file'])) {
        $message .= __('，對話檔案已生成', 'mp-ukagaka');
    }
    $text = '<div class="updated"><p><strong>' . $message . '</strong></p></div>';
} elseif (!$skip_form_processing && isset($_POST['submit3'])) {
    // 處理新春菜的創建
    $ukagaka = $_POST['ukagaka'];
    $ukagaka['msg'] = mpu_str2array($ukagaka['msg']);
    $ukagaka['name'] = mpu_input_filter($ukagaka['name']);
    $ukagaka['shell'] = mpu_input_filter($ukagaka['shell']);
    $ukagaka['show'] = isset($ukagaka['show']) && $ukagaka['show'] ? true : false;

    // 處理對話檔案
    if (isset($_POST['generate_dialog_file_new']) && $_POST['generate_dialog_file_new'] == 'true' && !empty($ukagaka['dialog_filename'])) {
        // 獲取檔案格式
        $ext = isset($mpu_opt['external_file_format']) ? $mpu_opt['external_file_format'] : 'txt';

        // 【安全性強化】使用安全文件生成函數
        $dialog_filename = sanitize_file_name($ukagaka['dialog_filename']);
        mpu_generate_dialog_file($dialog_filename, $ukagaka['msg'], $ext);
    }

    $mpu_opt['ukagakas'][] = $ukagaka;

    // 處理鍵名為 0 的情況
    if (isset($mpu_opt['ukagakas'][0]) && is_array($mpu_opt['ukagakas'][0])) {
        $mpu_opt['ukagakas'][] = $mpu_opt['ukagakas'][0];
        unset($mpu_opt['ukagakas'][0]);
    }
    update_option('mp_ukagaka', $mpu_opt);
    $message = __('春菜創建成功～', 'mp-ukagaka');
    if (isset($_POST['generate_dialog_file_new']) && $_POST['generate_dialog_file_new'] == 'true') {
        $message .= __('，對話檔案已生成', 'mp-ukagaka');
    }
    $text = '<div class="updated"><p><strong>' . $message . '</strong></p></div>';
} elseif (!$skip_form_processing && isset($_POST['submit4'])) {
    // 處理擴展設定的提交
    $extend = $_POST['extend'];
    $extend['js_area'] = mpu_input_filter($extend['js_area']);
    $mpu_opt['extend'] = $extend;
    update_option('mp_ukagaka', $mpu_opt);
    $text = '<div class="updated"><p><strong>' . __('設定已儲存', 'mp-ukagaka') . '</strong></p></div>';
} elseif (!$skip_form_processing && isset($_POST['submit5'])) {
    // 處理會話設定的提交
    $auto_msg = $_POST['auto_msg'];
    $common_msg = $_POST['common_msg'];
    $mpu_opt['auto_msg'] = mpu_input_filter($auto_msg);
    $mpu_opt['common_msg'] = mpu_input_filter($common_msg);
    update_option('mp_ukagaka', $mpu_opt);
    $text = '<div class="updated"><p><strong>' . __('設定已儲存', 'mp-ukagaka') . '</strong></p></div>';
} elseif (!$skip_form_processing && isset($_POST['submit_reset'])) {
    // 處理重置設定的提交
    if ($_POST['reset_mpu']) {
        unset($mpu_opt);
        update_option('mp_ukagaka', $mpu_opt);
        mpu_default_opt(); // 重置為預設選項
        $text = '<div class="updated"><p><strong>' . __('設定已重置', 'mp-ukagaka') . '</strong></p></div>';
    } else {
        $text = '<div class="error"><p><strong>' . __('設定未被重置', 'mp-ukagaka') . '</strong></p></div>';
    }
}
?>

<!-- 引入 TextAreaResizer 插件 -->
<!-- 注意：jQuery 已通過 wp_enqueue_script('jquery') 載入，無需重複引入 -->
<script type="text/javascript" src="<?php echo plugins_url('js/ukagaka-textarearesizer.js', defined('MPU_MAIN_FILE') ? MPU_MAIN_FILE : __FILE__); ?>"></script>
<script type="text/javascript">
    // 當頁面載入完成時，啟用增強的 TextAreaResizer 功能
    jQuery(document).ready(function() {
        jQuery('textarea.resizable:not(.processed)').TextAreaResizer();
        jQuery('iframe.resizable:not(.processed)').TextAreaResizer();

        // 額外設置讓所有文字區域可以水平和垂直調整
        jQuery('textarea').css('resize', 'both');
    });
</script>

<!-- 引入 Claude 風格後台樣式 -->
<link rel="stylesheet" href="<?php echo plugins_url('admin-style.css', defined('MPU_MAIN_FILE') ? MPU_MAIN_FILE : __FILE__); ?>" type="text/css" />

<!-- 自訂樣式：調整文字區域的外觀（保留必要的內聯樣式） -->
<style type="text/css">
    /* 增加文字區域大小以便於輸入HTML */
    /* 統一 textarea 寬度，與 System Prompt 保持一致 */
    textarea[name$="[msg]"],
    textarea#common_msg,
    textarea#auto_msg,
    textarea#ai_system_prompt,
    textarea#ai_greet_prompt {
        width: 1000px !important;
        min-height: 200px;
        resize: both !important;
    }

    .resizable-textarea textarea {
        display: block;
        margin-bottom: 0pt;
        height: 20%;
        width: 100%;
        min-width: 300px;
    }

    /* 動漫風格：Grippie 調整大小底框 */
    div.grippie {
        background: #E8F4F8 url(<?php echo plugins_url('images/grippie.png', defined('MPU_MAIN_FILE') ? MPU_MAIN_FILE : __FILE__); ?>) no-repeat scroll center 2px;
        border: 1px solid #B8E6E6;
        border-top: none;
        border-radius: 0 0 6px 6px;
        cursor: s-resize;
        height: 12px;
        margin-top: -1px;
    }

    /* 兩欄布局：主內容區和側邊欄 */
    .mp-ukagaka-main-layout {
        display: flex;
        gap: 20px;
        align-items: flex-start;
        width: 100%;
        box-sizing: border-box;
    }

    .mp-ukagaka-section {
        flex: 0 0 55%;
        /* 縮小45%，即55%寬度 */
        max-width: 55%;
        box-sizing: border-box;
    }

    .mp-ukagaka-sidebar {
        flex: 0 0 300px;
        /* 固定寬度300px */
        width: 300px;
        max-width: 300px;
        position: sticky;
        top: 32px;
        /* WordPress admin bar height */
        box-sizing: border-box;
    }

    /* 動漫風格：主背景漸變 */
    body.wp-admin .wrap {
        background: linear-gradient(135deg, #F0F8FF 0%, #F5FDFF 100%);
        min-height: 100vh;
        padding: 20px;
        margin: 0 -20px 0 -20px;
    }

    /* 快速連結卡片樣式 - 動漫風格 */
    .mpu-quick-link-card {
        background: #E8F4F8;
        border: 1px solid #B8E6E6;
        border-radius: 10px;
        padding: 16px 20px;
        margin-bottom: 16px;
        box-shadow: 0 2px 8px rgba(168, 216, 234, 0.15);
    }

    .mpu-quick-link-card h4 {
        color: #4A9EBD;
        font-size: 14px;
        font-weight: 600;
        margin: 0 0 12px 0;
        padding-bottom: 8px;
        border-bottom: 1px solid #A8D8EA;
    }

    .mpu-quick-link-card ul {
        list-style: none;
        margin: 0;
        padding: 0;
    }

    .mpu-quick-link-card li {
        margin-bottom: 8px;
    }

    .mpu-quick-link-card li:last-child {
        margin-bottom: 0;
    }

    .mpu-quick-link-card a {
        color: #3A9BC1;
        text-decoration: none;
        transition: color 0.2s;
    }

    .mpu-quick-link-card a:hover {
        color: #5FB3A1;
        text-decoration: underline;
    }

    .mpu-provider-links p {
        margin: 0 0 10px 0;
        line-height: 1.6;
        font-size: 13px;
    }

    .mpu-provider-links p:last-child {
        margin-bottom: 0;
    }

    .mpu-provider-links strong {
        color: #2C3E50;
        font-weight: 600;
    }

    .mpu-provider-links p {
        color: #2C3E50;
    }

    /* 動漫風格：按鈕樣式 */
    .mpu-settings-card .button,
    .wrap .button {
        background: linear-gradient(135deg, #A8D8EA 0%, #B8E6E6 100%);
        border: 2px solid #B8E6E6;
        border-radius: 6px;
        color: #2C3E50;
        font-weight: 500;
        transition: all 0.2s;
        box-shadow: 0 2px 4px rgba(168, 216, 234, 0.15);
    }

    .mpu-settings-card .button:hover,
    .wrap .button:hover {
        background: linear-gradient(135deg, #4A9EBD 0%, #5FB3A1 100%);
        color: white;
        border-color: #4A9EBD;
        box-shadow: 0 2px 8px rgba(74, 158, 189, 0.3);
        transform: translateY(-1px);
    }

    .mpu-settings-card .button:active,
    .wrap .button:active {
        background: linear-gradient(135deg, #3A8CAD 0%, #4FA391 100%);
        transform: translateY(0);
    }

    /* 動漫風格：輸入框樣式 */
    .mpu-settings-card input[type="text"],
    .mpu-settings-card input[type="password"],
    .mpu-settings-card input[type="number"],
    .mpu-settings-card input[type="url"],
    .mpu-settings-card select,
    .mpu-settings-card textarea {
        border: 1px solid #A8D8EA;
        border-radius: 6px;
        background: #F0F9FF;
        color: #2C3E50;
        transition: all 0.2s;
    }

    /* 動漫風格：textarea 滾動條樣式 */
    .mpu-settings-card textarea::-webkit-scrollbar {
        width: 12px;
    }

    .mpu-settings-card textarea::-webkit-scrollbar-track {
        background: #E8F4F8;
        border-radius: 6px;
        border: 1px solid #B8E6E6;
    }

    .mpu-settings-card textarea::-webkit-scrollbar-thumb {
        background: linear-gradient(135deg, #A8D8EA 0%, #B8E6E6 100%);
        border-radius: 6px;
        border: 2px solid #E8F4F8;
    }

    .mpu-settings-card textarea::-webkit-scrollbar-thumb:hover {
        background: linear-gradient(135deg, #4A9EBD 0%, #5FB3A1 100%);
    }

    /* Firefox 滾動條樣式 */
    .mpu-settings-card textarea {
        scrollbar-width: thin;
        scrollbar-color: #A8D8EA #E8F4F8;
    }

    .mpu-settings-card input[type="text"]:focus,
    .mpu-settings-card input[type="password"]:focus,
    .mpu-settings-card input[type="number"]:focus,
    .mpu-settings-card input[type="url"]:focus,
    .mpu-settings-card select:focus,
    .mpu-settings-card textarea:focus {
        border-color: #4A9EBD;
        background: #FFFFFF;
        box-shadow: 0 0 0 3px rgba(74, 158, 189, 0.1);
        outline: none;
    }

    @media (max-width: 1200px) {
        .mp-ukagaka-main-layout {
            flex-direction: column;
        }

        .mp-ukagaka-section {
            flex: 1;
            max-width: 100%;
        }

        .mp-ukagaka-sidebar {
            flex: 1;
            min-width: 100%;
            position: static;
        }
    }
</style>


<!-- 主要內容區塊 -->
<div class="wrap mp-ukagaka-wrap">
    <h2><?php _e('MP Ukagaka 選項', 'mp-ukagaka'); ?></h2>

    <!-- 顯示操作結果訊息 -->
    <?php if (!empty($text)) echo $text; ?>

    <!-- 改進的導覽列：頁面切換連結 -->
    <div class="mp-ukagaka-tabs">
        <a class="<?php echo $cur_page == 0 ? 'active' : ''; ?>" href="<?php echo admin_url('options-general.php?page=' . $base_name . '&cur_page=0'); ?>"><?php _e('通用設定', 'mp-ukagaka'); ?></a>
        <a class="<?php echo $cur_page == 5 ? 'active' : ''; ?>" href="<?php echo admin_url('options-general.php?page=' . $base_name . '&cur_page=5'); ?>"><?php _e('AI 設定', 'mp-ukagaka'); ?></a>
        <a class="<?php echo $cur_page == 6 ? 'active' : ''; ?>" href="<?php echo admin_url('options-general.php?page=' . $base_name . '&cur_page=6'); ?>"><?php _e('LLM 設定', 'mp-ukagaka'); ?></a>
        <a class="<?php echo $cur_page == 4 ? 'active' : ''; ?>" href="<?php echo admin_url('options-general.php?page=' . $base_name . '&cur_page=4'); ?>"><?php _e('會話', 'mp-ukagaka'); ?></a>
        <a class="<?php echo $cur_page == 1 ? 'active' : ''; ?>" href="<?php echo admin_url('options-general.php?page=' . $base_name . '&cur_page=1'); ?>"><?php _e('春菜們', 'mp-ukagaka'); ?></a>
        <a class="<?php echo $cur_page == 2 ? 'active' : ''; ?>" href="<?php echo admin_url('options-general.php?page=' . $base_name . '&cur_page=2'); ?>"><?php _e('創建新春菜', 'mp-ukagaka'); ?></a>
        <a class="<?php echo $cur_page == 3 ? 'active' : ''; ?>" href="<?php echo admin_url('options-general.php?page=' . $base_name . '&cur_page=3'); ?>"><?php _e('擴展', 'mp-ukagaka'); ?></a>
    </div>

    <div class="mp-ukagaka-main-layout">
        <div class="mp-ukagaka-section">
            <!-- 根據當前頁面載入對應內容 -->
            <?php
            $page_files = array(
                0 => 'options_page0.php',
                1 => 'options_page1.php',
                2 => 'options_page2.php',
                3 => 'options_page3.php',
                4 => 'options_page4.php',
                5 => 'options_page_ai.php',
                6 => 'options_page_llm.php'
            );

            if (isset($page_files[$cur_page])) {
                require_once($page_files[$cur_page]);
            }
            ?>
        </div>

        <!-- 右側快速連結欄 -->
        <div class="mp-ukagaka-sidebar">
            <!-- AI Provider 相關網站 -->
            <div class="mpu-quick-link-card">
                <h4>🤖 AI Provider</h4>
                <div class="mpu-provider-links">
                    <p><strong>OpenAI:</strong> <a href="https://platform.openai.com/api-keys" target="_blank">API Keys</a> / <a href="https://platform.openai.com/docs" target="_blank">Docs</a></p>
                    <p><strong>Google Gemini:</strong> <a href="https://makersuite.google.com/app/apikey" target="_blank">AI Studio</a> / <a href="https://ai.google.dev/docs" target="_blank">Docs</a></p>
                    <p><strong>Anthropic (Claude):</strong> <a href="https://console.anthropic.com/" target="_blank">API Keys</a> / <a href="https://docs.anthropic.com/claude/docs" target="_blank">Docs</a></p>
                    <p><strong>Ollama:</strong> <a href="https://ollama.com/search" target="_blank">Models</a> / <a href="https://docs.ollama.com/" target="_blank">Docs</a></p>
                </div>
            </div>

            <!-- 文檔連結 -->
            <div class="mpu-quick-link-card">
                <h4>📚 Documentation</h4>
                <ul>
                    <li><a href="https://github.com/Horlicks-p/mp-ukagaka/blob/main/docs/README.md" target="_blank">README</a></li>
                    <li><a href="https://github.com/Horlicks-p/mp-ukagaka/blob/main/docs/USER_GUIDE.md" target="_blank">User Guide</a></li>
                    <li><a href="https://github.com/Horlicks-p/mp-ukagaka/blob/main/docs/DEVELOPER_GUIDE.md" target="_blank">Developer Guide</a></li>
                    <li><a href="https://github.com/Horlicks-p/mp-ukagaka/blob/main/docs/API_REFERENCE.md" target="_blank">API Reference</a></li>
                </ul>
            </div>

            <!-- 相關連結 -->
            <div class="mpu-quick-link-card">
                <h4>🔗 Links</h4>
                <ul>
                    <li><a href="https://www.moelog.com/" target="_blank">萌えログ.COM</a></li>
                    <li><a href="https://ja.wikipedia.org/wiki/伺か" target="_blank">伺か (Wikipedia)</a></li>
                    <li><a href="https://github.com/Horlicks-p/mp-ukagaka/" target="_blank">GitHub Repository</a></li>
                </ul>
            </div>
        </div>
    </div>
</div><!-- 結束 wrap -->