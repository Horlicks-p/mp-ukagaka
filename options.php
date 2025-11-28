<?php
// 定義插件的基本路徑和頁面
$base_name = plugin_basename('mp-ukagaka/options.php');
$base_page = 'options-general.php?page=' . $base_name;
$text = '';

// 獲取當前頁面編號，預設為 0
$cur_page = $_GET['cur_page'] ?? 0;
if (!is_numeric($cur_page) || ($cur_page < 0 || $cur_page > 5) || $cur_page == '') {
    $cur_page = 0;
}

// 處理刪除春菜的請求
if (isset($_GET['del']) && $_GET['del'] != '') {
    $del = $_GET['del'];
    if ($del == str_replace('default', '', $del)) { // 檢查是否為預設春菜
        if (isset($mpu_opt['ukagakas'][$del])) {
            $name = $mpu_opt['ukagakas'][$del]['name'];
            unset($mpu_opt['ukagakas'][$del]); // 刪除指定的春菜
            update_option('mp_ukagaka', $mpu_opt); // 更新選項
            $text .= (($name == '') ? __('春菜', 'mp-ukagaka') : $name) . __('已離你而去…', 'mp-ukagaka');
        } else {
            $text .= __('不存在此春菜喲', 'mp-ukagaka');
        }
    } else {
        $text .= __('不允許趕走預設春菜喲', 'mp-ukagaka');
    }
} elseif (isset($_POST['submit_ai'])) {
    // 驗證 Nonce
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'mp_ukagaka_settings')) {
        $text .= '<div class="error"><p>' . __('安全性檢查失敗。', 'mp-ukagaka') . '</p></div>';
    } else {
        // 只處理 AI 設定，保留其他所有設定
        $mpu_opt = mpu_get_option(); // 獲取現有設定
        
        // 只更新 AI 相關設定
        $mpu_opt['ai_enabled'] = isset($_POST['ai_enabled']) && $_POST['ai_enabled'] ? true : false;
        $mpu_opt['ai_provider'] = isset($_POST['ai_provider']) ? sanitize_text_field($_POST['ai_provider']) : 'gemini';
        
        // 【安全性強化】API Key 加密存儲
        $gemini_key = isset($_POST['ai_api_key']) ? sanitize_text_field($_POST['ai_api_key']) : '';
        $openai_key = isset($_POST['openai_api_key']) ? sanitize_text_field($_POST['openai_api_key']) : '';
        $claude_key = isset($_POST['claude_api_key']) ? sanitize_text_field($_POST['claude_api_key']) : '';
        
        $mpu_opt['ai_api_key'] = !empty($gemini_key) ? mpu_encrypt_api_key($gemini_key) : '';
        $mpu_opt['openai_api_key'] = !empty($openai_key) ? mpu_encrypt_api_key($openai_key) : '';
        $mpu_opt['claude_api_key'] = !empty($claude_key) ? mpu_encrypt_api_key($claude_key) : '';
        
        $mpu_opt['openai_model'] = isset($_POST['openai_model']) ? sanitize_text_field($_POST['openai_model']) : 'gpt-4o-mini';
        $mpu_opt['claude_model'] = isset($_POST['claude_model']) ? sanitize_text_field($_POST['claude_model']) : 'claude-sonnet-4-5-20250929';
        $mpu_opt['ai_language'] = isset($_POST['ai_language']) ? sanitize_text_field($_POST['ai_language']) : 'zh-TW';
        $mpu_opt['ai_system_prompt'] = isset($_POST['ai_system_prompt']) ? sanitize_textarea_field($_POST['ai_system_prompt']) : '你是一個傲嬌的桌面助手「春菜」。你會用簡短、帶點傲嬌的語氣評論文章內容。回應請保持在 40 字以內。';
        $mpu_opt['ai_probability'] = isset($_POST['ai_probability']) ? max(1, min(100, intval($_POST['ai_probability']))) : 10;
        $mpu_opt['ai_trigger_pages'] = isset($_POST['ai_trigger_pages']) ? sanitize_text_field($_POST['ai_trigger_pages']) : 'is_single';
        $mpu_opt['ai_text_color'] = isset($_POST['ai_text_color']) ? sanitize_hex_color($_POST['ai_text_color']) : '#000000';
        $mpu_opt['ai_display_duration'] = isset($_POST['ai_display_duration']) ? max(1, min(60, intval($_POST['ai_display_duration']))) : 8;
        $mpu_opt['ai_greet_first_visit'] = isset($_POST['ai_greet_first_visit']) && $_POST['ai_greet_first_visit'] ? true : false;
        $mpu_opt['ai_greet_prompt'] = isset($_POST['ai_greet_prompt']) ? sanitize_textarea_field($_POST['ai_greet_prompt']) : '你是一個友善的桌面助手「春菜」。當有訪客第一次來到網站時，你會根據訪客的來源（referrer）用親切的語氣打招呼。回應請保持在 50 字以內。';

        update_option('mp_ukagaka', $mpu_opt);
    }
} elseif (isset($_POST['submit2'])) {
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
    $text .= __('春菜們已經煥然一新啦', 'mp-ukagaka');
    if (isset($_POST['generate_dialog_file'])) {
        $text .= __('，對話檔案已生成', 'mp-ukagaka');
    }
} elseif (isset($_POST['submit3'])) {
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
    $text .= __('春菜創建成功～', 'mp-ukagaka');
    if (isset($_POST['generate_dialog_file_new']) && $_POST['generate_dialog_file_new'] == 'true') {
        $text .= __('，對話檔案已生成', 'mp-ukagaka');
    }
} elseif (isset($_POST['submit4'])) {
    // 處理擴展設定的提交
    $extend = $_POST['extend'];
    $extend['js_area'] = mpu_input_filter($extend['js_area']);
    $mpu_opt['extend'] = $extend;
    update_option('mp_ukagaka', $mpu_opt);
    $text .= __('設定已儲存', 'mp-ukagaka');
} elseif (isset($_POST['submit5'])) {
    // 處理會話設定的提交
    $auto_msg = $_POST['auto_msg'];
    $common_msg = $_POST['common_msg'];
    $mpu_opt['auto_msg'] = mpu_input_filter($auto_msg);
    $mpu_opt['common_msg'] = mpu_input_filter($common_msg);
    update_option('mp_ukagaka', $mpu_opt);
    $text .= __('設定已儲存', 'mp-ukagaka');
} elseif (isset($_POST['submit_reset'])) {
    // 處理重置設定的提交
    if ($_POST['reset_mpu']) {
        unset($mpu_opt);
        update_option('mp_ukagaka', $mpu_opt);
        mpu_default_opt(); // 重置為預設選項
        $text .= __('設定已重置', 'mp-ukagaka');
    } else {
        $text .= __('設定未被重置', 'mp-ukagaka');
    }
}
?>

<!-- 引入 jQuery 和 TextAreaResizer 插件 -->
<script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jquery/1.4/jquery.min.js"></script>
<script type="text/javascript" src="<?php echo plugins_url('mp-ukagaka/jquery.textarearesizer.compressed.js'); ?>"></script>
<script type="text/javascript">
    // 當頁面載入完成時，啟用增強的 TextAreaResizer 功能
    jQuery(document).ready(function() {
        jQuery('textarea.resizable:not(.processed)').TextAreaResizer();
        jQuery('iframe.resizable:not(.processed)').TextAreaResizer();
        
        // 額外設置讓所有文字區域可以水平和垂直調整
        jQuery('textarea').css('resize', 'both');
    });
</script>

<!-- 自訂樣式：調整文字區域的外觀 -->
<style type="text/css">
    div.grippie {
        background: #EEEEEE url(<?php echo plugins_url('mp-ukagaka/images/grippie.png'); ?>) no-repeat scroll center 2px;
        border-color: #DDDDDD;
        border-style: solid;
        border-width: 0pt 1px 1px;
        cursor: s-resize;
        height: 9px;
        overflow: hidden;
    }
    .resizable-textarea textarea {
        display: block;
        margin-bottom: 0pt;
        height: 20%;
        width: 100%; /* 確保文字區域使用全寬 */
        min-width: 300px; /* 設置最小寬度 */
    }
    
    /* 增加文字區域大小以便於輸入HTML */
    textarea[name$="[msg]"], textarea#common_msg, textarea#auto_msg {
        width: 500px !important; /* 增加寬度 */
        min-height: 150px; /* 增加預設高度 */
        resize: both !important; /* 允許同時水平和垂直調整大小 */
    }
    
    /* 添加更現代的後台樣式 */
    .mp-ukagaka-wrap h2 {
        margin-bottom: 20px;
    }
    
    .mp-ukagaka-tabs {
        margin-bottom: 20px;
        border-bottom: 1px solid #ccc;
    }
    
    .mp-ukagaka-tabs a {
        display: inline-block;
        padding: 8px 12px;
        text-decoration: none;
        margin-right: 5px;
        border: 1px solid transparent;
        border-bottom: none;
    }
    
    .mp-ukagaka-tabs a.active {
        border-color: #ccc;
        background: #fff;
        border-bottom: 1px solid #fff;
        margin-bottom: -1px;
    }
    
    .mp-ukagaka-section {
        background: #fff;
        padding: 20px;
        border: 1px solid #ccc;
        border-top: none;
    }
    
    .form-table td {
        padding: 15px 10px;
    }
    
    .button-primary {
        margin-top: 15px !important;
    }
    
    .mp-divider {
        height: 1px;
        background: #DFDFDF;
        margin: 20px 0;
        width: 100%;
    }
</style>


<!-- 主要內容區塊 -->
<div class="wrap mp-ukagaka-wrap">
    <?php screen_icon(); ?>
    <h2><?php _e('MP Ukagaka 選項', 'mp-ukagaka'); ?></h2>

    <!-- 顯示操作結果訊息 -->
    <?php if (!empty($text)) echo $text; ?>

    <!-- 改進的導覽列：頁面切換連結 -->
    <div class="mp-ukagaka-tabs">
        <a class="<?php echo $cur_page == 0 ? 'active' : ''; ?>" href="<?php echo admin_url('options-general.php?page=' . plugin_basename(__FILE__) . '&cur_page=0'); ?>"><?php _e('通用設定', 'mp-ukagaka'); ?></a>
        <a class="<?php echo $cur_page == 5 ? 'active' : ''; ?>" href="<?php echo admin_url('options-general.php?page=' . plugin_basename(__FILE__) . '&cur_page=5'); ?>"><?php _e('AI 設定', 'mp-ukagaka'); ?></a>
        <a class="<?php echo $cur_page == 4 ? 'active' : ''; ?>" href="<?php echo admin_url('options-general.php?page=' . plugin_basename(__FILE__) . '&cur_page=4'); ?>"><?php _e('會話', 'mp-ukagaka'); ?></a>
        <a class="<?php echo $cur_page == 1 ? 'active' : ''; ?>" href="<?php echo admin_url('options-general.php?page=' . plugin_basename(__FILE__) . '&cur_page=1'); ?>"><?php _e('春菜們', 'mp-ukagaka'); ?></a>
        <a class="<?php echo $cur_page == 2 ? 'active' : ''; ?>" href="<?php echo admin_url('options-general.php?page=' . plugin_basename(__FILE__) . '&cur_page=2'); ?>"><?php _e('創建新春菜', 'mp-ukagaka'); ?></a>
        <a class="<?php echo $cur_page == 3 ? 'active' : ''; ?>" href="<?php echo admin_url('options-general.php?page=' . plugin_basename(__FILE__) . '&cur_page=3'); ?>"><?php _e('擴展', 'mp-ukagaka'); ?></a>
    </div>

    <div class="mp-ukagaka-section">
        <!-- 根據當前頁面載入對應內容 -->
        <?php 
        $page_files = array(
            0 => 'options_page0.php',
            1 => 'options_page1.php',
            2 => 'options_page2.php',
            3 => 'options_page3.php',
            4 => 'options_page4.php',
            5 => 'options_page_ai.php'
        );
        
        if (isset($page_files[$cur_page])) {
            require_once($page_files[$cur_page]);
        }
        ?>
    </div>
</div><!-- 結束 wrap -->