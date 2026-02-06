<style>
    /* 動漫風格：統一設定頁面樣式 */
    .mpu-settings-card {
        background: #E8F4F8;
        border: 1px solid #B8E6E6;
        border-radius: 10px;
        padding: 20px 24px;
        margin: 20px 0;
        box-shadow: 0 2px 8px rgba(168, 216, 234, 0.15);
    }

    .mpu-settings-card h4 {
        color: #4A9EBD;
        font-size: 15px;
        font-weight: 600;
        margin: 0 0 16px 0;
        padding-bottom: 10px;
        border-bottom: 1px solid #A8D8EA;
    }

    .mpu-settings-card .mpu-field-group {
        margin-bottom: 16px;
    }

    .mpu-settings-card .mpu-field-group:last-child {
        margin-bottom: 0;
    }

    .mpu-settings-card label {
        font-weight: 500;
    }

    .mpu-settings-card small {
        color: #5A7A8C;
        display: block;
        margin-top: 4px;
    }

    .mpu-settings-card label {
        color: #2C3E50;
    }

    .mpu-settings-card a {
        color: #3A9BC1;
        text-decoration: none;
        transition: color 0.2s;
    }

    .mpu-settings-card a:hover {
        color: #5FB3A1;
        text-decoration: underline;
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

    .mpu-warning-box {
        background: #FFF8E1;
        border: 1px solid #FFD54F;
        border-radius: 6px;
        padding: 12px 16px;
        margin: 20px 0;
        color: #5A7A8C;
        box-shadow: 0 2px 4px rgba(255, 213, 79, 0.15);
    }
</style>

<div>
    <h3><?php _e('擴展', 'mp-ukagaka'); ?></h3>
    <div class="mpu-warning-box">
        <strong><?php _e('⚠️ 警告：', 'mp-ukagaka'); ?></strong> <?php _e('如果您不懂如何操作或編寫代碼，請勿更改此頁。', 'mp-ukagaka'); ?>
    </div>
    <form method="post" name="setting" id="setting" action="<?php echo admin_url('options-general.php?page=' . $base_name . '&cur_page=3'); ?>">
        <?php wp_nonce_field('mp_ukagaka_settings'); ?>

        <div class="mpu-settings-card">
            <h4><?php _e('📜 JS 區', 'mp-ukagaka'); ?></h4>
            <div class="mpu-field-group">
                <p><?php _e('可在此填寫 JavaScript 代碼，為偽春菜自訂更多的回應事件。', 'mp-ukagaka'); ?></p>
                <p><small><?php _e('無需使用 &lt;script&gt; 標籤，代碼將寫入到 &lt;head&gt; 部分。', 'mp-ukagaka'); ?></small></p>
                <textarea rows="8" cols="40" id="js_area" name="extend[js_area]" class="resizable" style="line-height:130%; width: 100%; max-width: 700px; font-family: 'Courier New', Consolas, monospace;"><?php echo isset($mpu_opt['extend']['js_area']) ? htmlspecialchars($mpu_opt['extend']['js_area']) : ''; ?></textarea>
            </div>
        </div>

        <div class="mpu-settings-card">
            <h4><?php _e('🔧 代碼擴展', 'mp-ukagaka'); ?></h4>
            <div class="mpu-field-group">
                <p><?php _e('您可以在偽春菜的資訊框中使用特殊代碼來顯示特定的資訊，例如日誌列表。', 'mp-ukagaka'); ?></p>
                <p><?php _e('更多擴展代碼資訊請參閱：', 'mp-ukagaka'); ?>
                    <a href="https://github.com/Horlicks-p/mp-ukagaka/tree/main/docs" target="_blank" title="<?php _e('MP Ukagaka 文檔中心', 'mp-ukagaka'); ?>"><?php _e('MP Ukagaka 文檔中心', 'mp-ukagaka'); ?></a>
                </p>
            </div>
        </div>

        <p><input name="submit4" class="button" value="<?php _e(' 儲 存 ', 'mp-ukagaka'); ?>" type="submit" /></p>
    </form>
</div>