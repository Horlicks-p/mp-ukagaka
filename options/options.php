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

$page_labels = array(
    0 => __('通用設定', 'mp-ukagaka'),
    1 => __('偽春菜們', 'mp-ukagaka'),
    2 => __('創建新偽春菜', 'mp-ukagaka'),
    3 => __('擴展', 'mp-ukagaka'),
    4 => __('會話', 'mp-ukagaka'),
    5 => __('AI 設定', 'mp-ukagaka'),
    6 => __('LLM 設定', 'mp-ukagaka'),
    7 => __('日記設定', 'mp-ukagaka'),
    8 => __('統計', 'mp-ukagaka')
);
$current_page_label = isset($page_labels[$cur_page]) ? $page_labels[$cur_page] : $page_labels[0];

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

<!-- 引入後台樣式 -->
<link rel="stylesheet" href="<?php echo plugins_url('css/admin-style.css', defined('MPU_MAIN_FILE') ? MPU_MAIN_FILE : __FILE__); ?>" type="text/css" />

<!-- 自訂樣式：Frieren 主題佈局 -->
<style type="text/css">
    .mpu-frieren-admin.wrap {
        margin: 0 0 0 -2px !important;
        padding: 22px 24px 28px 24px !important;
        background: #f0f0f1;
        min-height: calc(100vh - 40px);
        box-sizing: border-box;
    }

    .mpu-frieren-hero {
        margin: 0 0 14px 0;
        padding: 18px 22px 20px 22px;
        width: 70%;
        max-width: 70%;
        border: 1px solid var(--mpu-border);
        border-radius: 6px;
        background: var(--mpu-bg-section);
        box-shadow: none;
    }

    .mpu-frieren-hero__tag {
        display: inline-block;
        padding: 4px 10px;
        margin-bottom: 10px;
        border-radius: 999px;
        background: linear-gradient(120deg, var(--mpu-accent), #9B8EC4);
        color: #fff;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    .mpu-frieren-hero h2 {
        margin: 0 0 6px 0 !important;
        padding: 0 !important;
        width: auto !important;
        max-width: none !important;
        font-size: 30px !important;
        line-height: 1.15;
        color: var(--mpu-text-heading) !important;
        text-shadow: none !important;
    }

    .mpu-frieren-hero p {
        margin: 0;
        color: #6E5BA0;
        font-size: 14px;
        line-height: 1.7;
        max-width: 860px;
    }

    .mpu-frieren-admin .updated,
    .mpu-frieren-admin .error,
    .mpu-frieren-admin .notice {
        border-radius: 6px;
        border-left-width: 4px;
        margin: 12px 0 18px 0 !important;
        box-shadow: none;
    }

    .mpu-frieren-admin .mp-ukagaka-tabs a {
        border-radius: 2px 2px 0 0;
    }

    .mpu-frieren-admin .mp-ukagaka-tabs {
        width: 70% !important;
        max-width: 70% !important;
        margin-bottom: 0;
        border-bottom: 0 !important;
    }

    .mpu-frieren-admin .mp-ukagaka-main-layout {
        display: flex;
        gap: 18px;
        align-items: flex-start;
        width: 70%;
        max-width: 70%;
        margin-top: 0;
    }

    .mpu-frieren-admin .mp-ukagaka-section {
        flex: 1 1 auto;
        width: auto !important;
        max-width: none !important;
        min-width: 0;
        margin: 0 !important;
        padding: 26px 30px !important;
        border-radius: 0 0 6px 6px;
        border: 1px solid var(--mpu-border);
        border-top: 0;
        background: var(--mpu-bg-section);
        box-shadow: none;
    }

    .mpu-frieren-admin .mp-ukagaka-sidebar {
        flex: 0 0 300px;
        width: 300px;
        position: sticky;
        top: 42px;
    }

    .mpu-frieren-admin .mpu-quick-link-card {
        background: var(--mpu-bg-section);
        border: 1px solid var(--mpu-border);
        border-radius: 6px;
        padding: 14px 16px;
        margin-bottom: 14px;
        box-shadow: none;
    }

    .mpu-frieren-admin .mpu-quick-link-card h4 {
        margin: 0 0 10px 0;
        padding: 0 0 8px 0;
        border-bottom: 1px solid var(--mpu-border-light);
        color: var(--mpu-text-heading);
        font-size: 13px;
        font-weight: 700;
        letter-spacing: 0.01em;
    }

    .mpu-frieren-admin .mpu-quick-link-card p,
    .mpu-frieren-admin .mpu-quick-link-card li {
        margin: 0 0 8px 0;
        color: var(--mpu-text-primary);
        line-height: 1.55;
        font-size: 12px;
    }

    .mpu-frieren-admin .mpu-quick-link-card li:last-child,
    .mpu-frieren-admin .mpu-quick-link-card p:last-child {
        margin-bottom: 0;
    }

    .mpu-frieren-admin .mpu-quick-link-card a {
        color: #6E5BA0;
        text-decoration: none;
        border-bottom: 1px solid rgba(110, 91, 160, 0.35);
        transition: color 0.2s ease, border-color 0.2s ease;
    }

    .mpu-frieren-admin .mpu-quick-link-card a:hover {
        color: var(--mpu-accent-dark);
        border-color: rgba(94, 77, 139, 0.7);
    }

    .mpu-frieren-admin .mpu-context-panel {
        font-size: 12px;
        color: var(--mpu-text-primary);
        line-height: 1.6;
    }

    .mpu-frieren-admin .mpu-context-panel strong {
        color: var(--mpu-text-heading);
        font-weight: 700;
    }

    /* 增加文字區域大小以便於輸入 HTML */
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
        margin-bottom: 0;
        height: 20%;
        width: 100%;
        min-width: 300px;
    }

    div.grippie {
        background: var(--mpu-accent-muted) url(<?php echo plugins_url('images/grippie.png', defined('MPU_MAIN_FILE') ? MPU_MAIN_FILE : __FILE__); ?>) no-repeat scroll center 2px;
        border: 1px solid var(--mpu-border);
        border-top: none;
        border-radius: 0 0 6px 6px;
        cursor: s-resize;
        height: 12px;
        margin-top: -1px;
    }

    @media (max-width: 1280px) {
        .mpu-frieren-admin .mp-ukagaka-main-layout {
            flex-direction: column;
            gap: 14px;
            width: 100%;
            max-width: 100%;
        }

        .mpu-frieren-admin .mp-ukagaka-sidebar {
            position: static;
            flex: 1;
            width: 100%;
            max-width: 100%;
        }
    }

    @media (max-width: 782px) {
        .mpu-frieren-admin.wrap {
            margin-left: 0 !important;
            padding: 14px !important;
        }

        .mpu-frieren-hero {
            padding: 14px 14px 15px 14px;
            border-radius: 6px;
            width: 100%;
            max-width: 100%;
        }

        .mpu-frieren-hero h2 {
            font-size: 25px !important;
        }

        .mpu-frieren-admin .mp-ukagaka-tabs {
            margin: 0 !important;
            width: 100% !important;
            max-width: 100% !important;
        }

        .mpu-frieren-admin .mp-ukagaka-section {
            padding: 18px 14px !important;
            margin: 0 !important;
        }
    }
</style>

<!-- 主要內容區塊 -->
<div class="wrap mp-ukagaka-wrap mpu-frieren-admin">
    <div class="mpu-frieren-hero">
        <span class="mpu-frieren-hero__tag">Ghost Console</span>
        <h2><?php _e('MP Ukagaka 選項', 'mp-ukagaka'); ?></h2>
        <p><?php _e('在 WordPress 網站上顯示互動式偽春菜角色，支援多家 AI 供應商的 LLM 對話、頁面感知、自動日記等功能。', 'mp-ukagaka'); ?></p>
    </div>

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
            <div class="mpu-quick-link-card mpu-context-panel">
                <h4><?php _e('控制台狀態', 'mp-ukagaka'); ?></h4>
                <p><strong><?php _e('目前頁面：', 'mp-ukagaka'); ?></strong><?php echo esc_html($current_page_label); ?></p>
                <p><strong><?php _e('模式：', 'mp-ukagaka'); ?></strong><?php _e('後台設定', 'mp-ukagaka'); ?></p>
                <p><strong><?php _e('建議：', 'mp-ukagaka'); ?></strong><?php _e('先完成 LLM 與 AI 設定，再調整會話與日記細節。', 'mp-ukagaka'); ?></p>
            </div>

            <!-- AI Provider 相關網站 -->
            <div class="mpu-quick-link-card">
                <h4><?php _e('AI Provider', 'mp-ukagaka'); ?></h4>
                <div class="mpu-provider-links">
                    <p><strong>OpenAI:</strong> <a href="https://platform.openai.com/api-keys" target="_blank">API Keys</a> / <a href="https://platform.openai.com/docs" target="_blank">Docs</a></p>
                    <p><strong>Google Gemini:</strong> <a href="https://makersuite.google.com/app/apikey" target="_blank">AI Studio</a> / <a href="https://ai.google.dev/docs" target="_blank">Docs</a></p>
                    <p><strong>Anthropic (Claude):</strong> <a href="https://console.anthropic.com/" target="_blank">API Keys</a> / <a href="https://docs.anthropic.com/claude/docs" target="_blank">Docs</a></p>
                    <p><strong>Ollama:</strong> <a href="https://ollama.com/search" target="_blank">Models</a> / <a href="https://docs.ollama.com/" target="_blank">Docs</a></p>
                </div>
            </div>

            <!-- 文檔連結 -->
            <div class="mpu-quick-link-card">
                <h4><?php _e('Documentation', 'mp-ukagaka'); ?></h4>
                <ul>
                    <li><a href="https://github.com/Horlicks-p/mp-ukagaka/blob/main/docs/README.md" target="_blank">README</a></li>
                    <li><a href="https://github.com/Horlicks-p/mp-ukagaka/blob/main/docs/USER_GUIDE.md" target="_blank">User Guide</a></li>
                    <li><a href="https://github.com/Horlicks-p/mp-ukagaka/blob/main/docs/DEVELOPER_GUIDE.md" target="_blank">Developer Guide</a></li>
                    <li><a href="https://github.com/Horlicks-p/mp-ukagaka/blob/main/docs/API_REFERENCE.md" target="_blank">API Reference</a></li>
                </ul>
            </div>

            <!-- 相關連結 -->
            <div class="mpu-quick-link-card">
                <h4><?php _e('Links', 'mp-ukagaka'); ?></h4>
                <ul>
                    <li><a href="https://www.moelog.com/" target="_blank">萌えログ.COM</a></li>
                    <li><a href="https://ja.wikipedia.org/wiki/伺か" target="_blank">伺か (Wikipedia)</a></li>
                    <li><a href="https://github.com/Horlicks-p/mp-ukagaka/" target="_blank">GitHub Repository</a></li>
                </ul>
            </div>
        </div>
    </div>
</div><!-- 結束 wrap -->
