<div>
    <h3><?php _e('💬 會話', 'mp-ukagaka'); ?></h3>
    <p style="color: #8A7FA0; margin-bottom: 20px;">
        <small><?php _e('設定所有偽春菜共用的固定資訊和通用會話內容。', 'mp-ukagaka'); ?></small>
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
                <small><?php _e('所有偽春菜共用的會話內容。一旦填寫此欄，通用會話將取代每個偽春菜的自訂會話。清空此欄則使用各偽春菜的預設自訂會話。', 'mp-ukagaka'); ?></small>
            </div>
        </div>

        <p><input name="submit5" class="button" value="<?php _e(' 儲 存 ', 'mp-ukagaka'); ?>" type="submit" /></p>
    </form>
</div>