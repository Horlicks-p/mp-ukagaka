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
    <h3><?php _e('創建新春菜', 'mp-ukagaka'); ?></h3>
    <p style="color: #5A7A8C; margin-bottom: 20px;">
        <small><?php _e('創建一個新的春菜角色。圖片請填寫完整的 URL，不要忘記以 http:// 或 https:// 開頭。', 'mp-ukagaka'); ?></small>
    </p>
    <form method="post" name="create" id="create" action="<?php echo admin_url('options-general.php?page=' . $base_name . '&cur_page=2'); ?>">
        <?php wp_nonce_field('mp_ukagaka_settings'); ?>
        
        <div class="mpu-settings-card">
            <h4><?php _e('➕ 新增春菜', 'mp-ukagaka'); ?></h4>
            
            <div class="mpu-field-group">
                <label><input type="checkbox" name="ukagaka[show]" value="true" /><?php _e('可顯示', 'mp-ukagaka'); ?></label>
            </div>

            <div class="mpu-field-group">
                <label><?php _e('名稱：', 'mp-ukagaka'); ?></label>
                <input type="text" name="ukagaka[name]" value="" style="width: 100%; max-width: 400px;" />
            </div>

            <div class="mpu-field-group">
                <label><?php _e('圖片：', 'mp-ukagaka'); ?></label>
                <input type="text" name="ukagaka[shell]" value="http://" style="width: 100%; max-width: 500px;" />
                <small><?php _e('請填寫完整的 URL，不要忘記以 http:// 或 https:// 開頭', 'mp-ukagaka'); ?></small>
            </div>

            <div class="mpu-field-group">
                <label><?php _e('吐槽：', 'mp-ukagaka'); ?></label>
                <textarea name="ukagaka[msg]" rows="5" cols="60" class="resizable" style="line-height:130%; width: 100%; max-width: 850px;"></textarea>
                <small><?php _e('每行一條吐槽，不可使用 HTML 代碼。', 'mp-ukagaka'); ?></small>
            </div>

            <div class="mpu-field-group">
                <label><?php _e('對話檔案名稱：', 'mp-ukagaka'); ?></label>
                <input type="text" name="ukagaka[dialog_filename]" value="" style="width: 100%; max-width: 300px;" />
                <small><?php _e('此名稱將用於外部對話檔案，例如：asuna.txt 或 asuna.json', 'mp-ukagaka'); ?></small>
            </div>

            <div class="mpu-field-group">
                <label><input type="checkbox" name="generate_dialog_file_new" value="true" /><?php _e('生成對話檔案', 'mp-ukagaka'); ?></label>
                <small><?php _e('勾選此項將使用上方吐槽內容生成對應的對話檔案', 'mp-ukagaka'); ?></small>
            </div>
        </div>

        <p><input name="submit3" class="button" value="<?php _e(' 創 建 ', 'mp-ukagaka'); ?>" type="submit" /></p>
    </form>
</div>
