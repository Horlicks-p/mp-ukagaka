<?php
// 定義插件的基本路徑和頁面
$base_name = plugin_basename(__FILE__);
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

        // ★★★ 修正：正確處理啟用/取消 LLM 的情況 ★★★
        if (isset($_POST['llm_enabled']) && $_POST['llm_enabled']) {
            // 啟用 LLM，設置提供商為 ollama
            $mpu_opt['ai_provider'] = 'ollama';
        } else {
            // 取消勾選 LLM，如果當前提供商是 ollama，則切換回默認提供商
            if (isset($mpu_opt['ai_provider']) && $mpu_opt['ai_provider'] === 'ollama') {
                $mpu_opt['ai_provider'] = 'gemini'; // 切換回默認提供商
            }
        }

        // 保存 Ollama 設定
        if (isset($_POST['ollama_endpoint'])) {
            $mpu_opt['ollama_endpoint'] = sanitize_text_field($_POST['ollama_endpoint']);
        }
        if (isset($_POST['ollama_model'])) {
            $mpu_opt['ollama_model'] = sanitize_text_field($_POST['ollama_model']);
        }
        // 保存「使用 LLM 取代內建對話」設定
        $mpu_opt['ollama_replace_dialogue'] = isset($_POST['ollama_replace_dialogue']) && $_POST['ollama_replace_dialogue'] ? true : false;

        // 保存「關閉思考模式」設定
        $mpu_opt['ollama_disable_thinking'] = isset($_POST['ollama_disable_thinking']) && $_POST['ollama_disable_thinking'] ? true : false;

        // ★★★ 移除：不再強制關閉頁面感知 ★★★
        // Ollama 現在也支援頁面感知功能，與雲端 AI 相同

        update_option('mp_ukagaka', $mpu_opt);
        $text = '<div class="updated"><p><strong>' . __('LLM 設定已儲存', 'mp-ukagaka') . '</strong></p></div>';
    }
} elseif (!$skip_form_processing && isset($_POST['submit_ai'])) {
    // 驗證 Nonce
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'mp_ukagaka_settings')) {
        $text = '<div class="error"><p><strong>' . __('安全性檢查失敗。', 'mp-ukagaka') . '</strong></p></div>';
    } else {
        // 只處理 AI 設定，保留其他所有設定
        $mpu_opt = mpu_get_option(); // 獲取現有設定

        // 只更新 AI 相關設定
        $mpu_opt['ai_enabled'] = isset($_POST['ai_enabled']) && $_POST['ai_enabled'] ? true : false;
        $mpu_opt['ai_provider'] = isset($_POST['ai_provider']) ? sanitize_text_field($_POST['ai_provider']) : 'gemini';

        // 【安全性強化】API Key 加密存儲
        // ★★★ 改進：確保所有 API Key 都經過正確的加密處理 ★★★
        $gemini_key = isset($_POST['ai_api_key']) ? sanitize_text_field($_POST['ai_api_key']) : '';
        $openai_key = isset($_POST['openai_api_key']) ? sanitize_text_field($_POST['openai_api_key']) : '';
        $claude_key = isset($_POST['claude_api_key']) ? sanitize_text_field($_POST['claude_api_key']) : '';

        // 處理 Gemini API Key
        if (!empty($gemini_key)) {
            // ★★★ 安全性檢查：如果提交的是已加密的密鑰，可能是異常情況，記錄警告 ★★★
            if (mpu_is_api_key_encrypted($gemini_key)) {
                // 前端不應該提交已加密的密鑰，這可能是安全問題
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('MP Ukagaka 安全警告：前端提交了已加密的 API Key，跳過處理');
                }
                // 跳過處理，保留現有值
            } else {
                // 正常情況：提交的是明文，進行加密
                $mpu_opt['ai_api_key'] = mpu_encrypt_api_key($gemini_key);
            }
        }
        // 注意：如果為空，不更新現有值（保留已加密的密鑰）

        // 處理 OpenAI API Key
        if (!empty($openai_key)) {
            if (mpu_is_api_key_encrypted($openai_key)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('MP Ukagaka 安全警告：前端提交了已加密的 OpenAI API Key，跳過處理');
                }
            } else {
                $mpu_opt['openai_api_key'] = mpu_encrypt_api_key($openai_key);
            }
        }

        // 處理 Claude API Key
        if (!empty($claude_key)) {
            if (mpu_is_api_key_encrypted($claude_key)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('MP Ukagaka 安全警告：前端提交了已加密的 Claude API Key，跳過處理');
                }
            } else {
                $mpu_opt['claude_api_key'] = mpu_encrypt_api_key($claude_key);
            }
        }

        $mpu_opt['openai_model'] = isset($_POST['openai_model']) ? sanitize_text_field($_POST['openai_model']) : 'gpt-4o-mini';
        $mpu_opt['claude_model'] = isset($_POST['claude_model']) ? sanitize_text_field($_POST['claude_model']) : 'claude-sonnet-4-5-20250929';

        // 保存 Ollama 設定
        if (isset($_POST['ollama_endpoint'])) {
            $mpu_opt['ollama_endpoint'] = sanitize_text_field($_POST['ollama_endpoint']);
        }
        if (isset($_POST['ollama_model'])) {
            $mpu_opt['ollama_model'] = sanitize_text_field($_POST['ollama_model']);
        }
        // 保存「使用 LLM 取代內建對話」設定（僅在表單有此欄位時才更新）
        // 注意：這些設定主要在 LLM 設定頁面，AI 設定頁面沒有這些欄位
        // 如果未提交，保留現有值，避免覆蓋 LLM 設定頁面的設定
        if (isset($_POST['ollama_replace_dialogue'])) {
            $mpu_opt['ollama_replace_dialogue'] = $_POST['ollama_replace_dialogue'] ? true : false;
        }

        // 保存「關閉思考模式」設定（僅在表單有此欄位時才更新）
        if (isset($_POST['ollama_disable_thinking'])) {
            $mpu_opt['ollama_disable_thinking'] = $_POST['ollama_disable_thinking'] ? true : false;
        }

        // ★★★ 移除：不再強制關閉頁面感知 ★★★
        // Ollama 現在也支援頁面感知功能，與雲端 AI 相同

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
<script type="text/javascript" src="<?php echo plugins_url('jquery.textarearesizer.compressed.js', defined('MPU_MAIN_FILE') ? MPU_MAIN_FILE : __FILE__); ?>"></script>
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
    textarea[name$="[msg]"],
    textarea#common_msg,
    textarea#auto_msg {
        width: 500px !important;
        min-height: 150px;
        resize: both !important;
    }

    .resizable-textarea textarea {
        display: block;
        margin-bottom: 0pt;
        height: 20%;
        width: 100%;
        min-width: 300px;
    }

    /* Grippie 背景圖使用 PHP 生成的 URL */
    div.grippie {
        background: #EEECDE url(<?php echo plugins_url('images/grippie.png', defined('MPU_MAIN_FILE') ? MPU_MAIN_FILE : __FILE__); ?>) no-repeat scroll center 2px;
    }
</style>


<!-- 主要內容區塊 -->
<div class="wrap mp-ukagaka-wrap">
    <h2><?php _e('MP Ukagaka 選項', 'mp-ukagaka'); ?></h2>

    <!-- 顯示操作結果訊息 -->
    <?php if (!empty($text)) echo $text; ?>

    <!-- 改進的導覽列：頁面切換連結 -->
    <div class="mp-ukagaka-tabs">
        <a class="<?php echo $cur_page == 0 ? 'active' : ''; ?>" href="<?php echo admin_url('options-general.php?page=' . plugin_basename(__FILE__) . '&cur_page=0'); ?>"><?php _e('通用設定', 'mp-ukagaka'); ?></a>
        <a class="<?php echo $cur_page == 5 ? 'active' : ''; ?>" href="<?php echo admin_url('options-general.php?page=' . plugin_basename(__FILE__) . '&cur_page=5'); ?>"><?php _e('AI 設定', 'mp-ukagaka'); ?></a>
        <a class="<?php echo $cur_page == 6 ? 'active' : ''; ?>" href="<?php echo admin_url('options-general.php?page=' . plugin_basename(__FILE__) . '&cur_page=6'); ?>"><?php _e('LLM 設定', 'mp-ukagaka'); ?></a>
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
            5 => 'options_page_ai.php',
            6 => 'options_page_llm.php'
        );

        if (isset($page_files[$cur_page])) {
            require_once($page_files[$cur_page]);
        }
        ?>
    </div>
</div><!-- 結束 wrap -->