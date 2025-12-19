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
</style>

<div>
    <h3><?php _e('會話', 'mp-ukagaka'); ?></h3>
    <p style="color: #5A7A8C; margin-bottom: 20px;">
        <small><?php _e('設定所有春菜共用的固定資訊和通用會話內容。', 'mp-ukagaka'); ?></small>
    </p>
    <form method="post" name="setting" id="setting" action="<?php echo admin_url('options-general.php?page=' . $base_name . '&cur_page=4'); ?>">
        <?php wp_nonce_field('mp_ukagaka_settings'); ?>
        
        <div class="mpu-settings-card">
            <h4><?php _e('📌 固定資訊', 'mp-ukagaka'); ?></h4>
            <div class="mpu-field-group">
                <label for="auto_msg"><?php _e('固定資訊：', 'mp-ukagaka'); ?></label>
                <textarea cols="40" rows="3" id="auto_msg" name="auto_msg" class="resizable" style="line-height:130%; width: 100%; max-width: 850px;"><?php echo esc_textarea($mpu_opt['auto_msg']); ?></textarea>
                <small><?php _e('此資訊將顯示在每條會話的後面，不可使用 HTML 代碼。', 'mp-ukagaka'); ?></small>
            </div>
        </div>

        <div class="mpu-settings-card">
            <h4><?php _e('💬 通用會話', 'mp-ukagaka'); ?></h4>
            <div class="mpu-field-group">
                <label for="common_msg"><?php _e('通用會話：', 'mp-ukagaka'); ?></label>
                <textarea cols="40" rows="3" id="common_msg" name="common_msg" class="resizable" style="line-height:130%; width: 100%; max-width: 850px;"><?php echo esc_textarea($mpu_opt['common_msg']); ?></textarea>
                <small><?php _e('所有春菜共用的會話內容。一旦填寫此欄，通用會話將取代每個春菜的自訂會話。清空此欄則使用各春菜的預設自訂會話。', 'mp-ukagaka'); ?></small>
            </div>
        </div>

        <p><input name="submit5" class="button" value="<?php _e(' 儲 存 ', 'mp-ukagaka'); ?>" type="submit" /></p>
    </form>
</div>
