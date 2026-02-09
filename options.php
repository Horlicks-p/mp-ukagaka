<?php
// 定義插件的基本路徑和頁面
// 使用固定的 slug，而不是 plugin_basename(__FILE__)，因為檔案已移動到子資料夾
// WordPress 註冊的頁面 slug 是 'mp-ukagaka/options.php'
$base_name = 'mp-ukagaka/options.php';
$base_page = 'options-general.php?page=' . $base_name;
$text = '';

// 從 transient 獲取 admin-functions.php 處理的訊息
$admin_message = get_transient('mpu_admin_message');
if ($admin_message !== false) {
    $text = $admin_message;
    delete_transient('mpu_admin_message');
}

// 獲取當前頁面編號，預設為 0
$cur_page = $_GET['cur_page'] ?? 0;
if (!is_numeric($cur_page) || ($cur_page < 0 || $cur_page > 8) || $cur_page == '') {
    $cur_page = 0;
}

// 注意：表單處理已統一由 admin-functions.php 的 mpu_handle_options_save() 函數處理
// 在 admin_init hook 中執行，確保在頁面渲染前完成處理
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
<link rel="stylesheet" href="<?php echo plugins_url('css/admin-style.css', defined('MPU_MAIN_FILE') ? MPU_MAIN_FILE : __FILE__); ?>" type="text/css" />

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
        /* 防止在放大時溢出 */
        max-width: calc(100vw - 200px);
    }

    .mp-ukagaka-section {
        /* 使用彈性寬度，自動填滿剩餘空間 */
        flex: 1 1 auto;
        min-width: 0;  /* 防止 flex 子元素溢出 */
        box-sizing: border-box;
    }

    .mp-ukagaka-sidebar {
        /* 彈性收縮的側邊欄 */
        flex: 0 0 280px;
        width: 280px;
        min-width: 200px;
        max-width: 300px;
        position: sticky;
        top: 32px;
        /* WordPress admin bar height */
        box-sizing: border-box;
    }

    /* WordPress 側邊欄收合時有更多空間 */
    .folded .mp-ukagaka-main-layout {
        max-width: calc(100vw - 80px);
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
            max-width: 100%;
        }

        .mp-ukagaka-section {
            flex: 1;
            max-width: 100%;
            width: 100%;
        }

        .mp-ukagaka-sidebar {
            flex: 1;
            width: 100%;
            min-width: 100%;
            max-width: 100%;
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
        <a class="<?php echo $cur_page == 7 ? 'active' : ''; ?>" href="<?php echo admin_url('options-general.php?page=' . $base_name . '&cur_page=7'); ?>"><?php _e('日記設定', 'mp-ukagaka'); ?></a>
        <a class="<?php echo $cur_page == 4 ? 'active' : ''; ?>" href="<?php echo admin_url('options-general.php?page=' . $base_name . '&cur_page=4'); ?>"><?php _e('會話', 'mp-ukagaka'); ?></a>
        <a class="<?php echo $cur_page == 1 ? 'active' : ''; ?>" href="<?php echo admin_url('options-general.php?page=' . $base_name . '&cur_page=1'); ?>"><?php _e('偽春菜們', 'mp-ukagaka'); ?></a>
        <a class="<?php echo $cur_page == 2 ? 'active' : ''; ?>" href="<?php echo admin_url('options-general.php?page=' . $base_name . '&cur_page=2'); ?>"><?php _e('創建新偽春菜', 'mp-ukagaka'); ?></a>
        <a class="<?php echo $cur_page == 3 ? 'active' : ''; ?>" href="<?php echo admin_url('options-general.php?page=' . $base_name . '&cur_page=3'); ?>"><?php _e('擴展', 'mp-ukagaka'); ?></a>
        <a class="<?php echo $cur_page == 8 ? 'active' : ''; ?>" href="<?php echo admin_url('options-general.php?page=' . $base_name . '&cur_page=8'); ?>"><?php _e('統計', 'mp-ukagaka'); ?></a>
    </div>

    <div class="mp-ukagaka-main-layout">
        <div class="mp-ukagaka-section">
            <!-- 根據當前頁面載入對應內容 -->
            <?php
            $page_files = array(
                0 => 'options_general.php',
                1 => 'options_ukagakas.php',
                2 => 'options_create.php',
                3 => 'options_extend.php',
                4 => 'options_dialog.php',
                5 => 'options_page_ai.php',
                6 => 'options_page_llm.php',
                7 => 'options_page_diary.php',
                8 => 'options_page_stats.php'
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