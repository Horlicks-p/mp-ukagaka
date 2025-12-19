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

    .mpu-ukagaka-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
    }

    .mpu-ukagaka-header h4 {
        margin: 0;
        padding-bottom: 0;
        border-bottom: none;
    }

    .mpu-ukagaka-header .mpu-delete-link {
        color: #d63638;
        text-decoration: none;
        font-size: 13px;
    }

    .mpu-ukagaka-header .mpu-delete-link:hover {
        text-decoration: underline;
    }
</style>

<div>
    <h3><?php _e('春菜們', 'mp-ukagaka'); ?></h3>
    <p style="color: #5A7A8C; margin-bottom: 20px;">
        <small><?php _e('圖片欄中，請填寫完整的 URL，不要忘記以 http:// 開頭。吐槽欄中，每行代表一條吐槽。不可使用 HTML 代碼。', 'mp-ukagaka'); ?></small>
    </p>
    <form method="post" name="ukagakas" id="ukagakas" action="<?php echo admin_url('options-general.php?page=' . $base_name . '&cur_page=1'); ?>">
        <?php foreach ($mpu_opt['ukagakas'] as $key => $value) { ?>
            <?php wp_nonce_field('mp_ukagaka_settings'); ?>
            
            <!-- 春菜單個設定區塊 -->
            <div class="mpu-settings-card">
                <div class="mpu-ukagaka-header">
                    <h4>#<?php echo $key; ?> - <?php echo mpu_output_filter($value['name']); ?></h4>
                    <?php if ($key == str_replace('default', '', $key)) { ?>
                        <a href="<?php echo admin_url('options-general.php?page=' . $base_name . '&cur_page=1&del=' . esc_attr($key)); ?>" class="mpu-delete-link">[<?php _e('刪除', 'mp-ukagaka'); ?>]</a>
                    <?php } ?>
                </div>

                <div class="mpu-field-group">
                    <label><input type="checkbox" name="ukagakas[<?php echo $key; ?>][show]" value="true" <?php if ($value['show']) {
                                                                                                                echo ' checked="checked"';
                                                                                                            } ?> /><?php _e('可顯示', 'mp-ukagaka'); ?></label>
                </div>

                <div class="mpu-field-group">
                    <label><?php _e('名稱：', 'mp-ukagaka'); ?></label>
                    <input type="text" name="ukagakas[<?php echo $key; ?>][name]" value="<?php echo mpu_output_filter($value['name']); ?>" style="width: 100%; max-width: 400px;" />
                </div>

                <div class="mpu-field-group">
                    <label><?php _e('圖片：', 'mp-ukagaka'); ?></label>
                    <input type="text" name="ukagakas[<?php echo $key; ?>][shell]" value="<?php echo mpu_output_filter($value['shell']); ?>" style="width: 100%; max-width: 500px;" />
                    <small><?php _e('請填寫完整的 URL，不要忘記以 http:// 或 https:// 開頭', 'mp-ukagaka'); ?></small>
                </div>

                <div class="mpu-field-group">
                    <label><?php _e('吐槽：', 'mp-ukagaka'); ?></label>
                    <textarea name="ukagakas[<?php echo $key; ?>][msg]" rows="3" cols="60" class="resizable" style="line-height:130%; width: 100%; max-width: 850px;"><?php echo esc_textarea(mpu_array2str($value['msg'])); ?></textarea>
                    <small><?php _e('每行代表一條吐槽。不可使用 HTML 代碼。', 'mp-ukagaka'); ?></small>
                </div>

                <div class="mpu-field-group">
                    <label><?php _e('對話檔案名稱：', 'mp-ukagaka'); ?></label>
                    <input type="text" name="ukagakas[<?php echo $key; ?>][dialog_filename]" value="<?php echo isset($value['dialog_filename']) ? mpu_output_filter($value['dialog_filename']) : $key; ?>" style="width: 100%; max-width: 300px;" />
                    <small><?php _e('此名稱將用於外部對話檔案，例如：asuna.txt 或 asuna.json', 'mp-ukagaka'); ?></small>
                </div>

                <div class="mpu-field-group">
                    <label><input type="checkbox" name="generate_dialog_file[<?php echo $key; ?>]" value="true" /><?php _e('生成對話檔案', 'mp-ukagaka'); ?></label>
                    <small><?php _e('勾選此項將使用上方吐槽內容生成對應的對話檔案', 'mp-ukagaka'); ?></small>
                </div>
            </div>
        <?php } ?>

        <p><input name="submit2" class="button" value="<?php _e(' 儲 存 ', 'mp-ukagaka'); ?>" type="submit" /></p>
    </form>
</div>
