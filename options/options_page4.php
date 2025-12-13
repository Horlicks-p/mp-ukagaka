<div>
    <!-- 標題：會話 -->
    <h3><?php _e('會話', 'mp-ukagaka'); ?></h3>

    <!-- 表單：用於提交會話設定的表單 -->
    <form method="post" name="setting" id="setting" action="<?php echo admin_url('options-general.php?page=' . $base_name . '&cur_page=4'); ?>">
        <?php wp_nonce_field('mp_ukagaka_settings'); ?>
        <!-- 分隔線 -->
        <div style="height:1px;background:#DFDFDF;width:400px;"></div>

        <!-- 子標題：固定資訊 -->
        <h4><label for="auto_msg"><?php _e('固定資訊：', 'mp-ukagaka'); ?></label></h4>
        <!-- 輸入欄：固定資訊內容 -->
        <p>
            <textarea cols="40" rows="3" id="auto_msg" name="auto_msg" class="resizable" style="line-height:130%;"><?php echo esc_textarea($mpu_opt['auto_msg']); ?></textarea><br />
            <?php _e('此資訊將顯示在每條會話的後面，不可使用 HTML 代碼。', 'mp-ukagaka'); ?>
        </p>

        <!-- 分隔線 -->
        <div style="height:1px;background:#DFDFDF;width:400px;"></div>

        <!-- 子標題：通用會話 -->
        <h4><label for="common_msg"><?php _e('通用會話：', 'mp-ukagaka'); ?></label></h4>
        <!-- 輸入欄：通用會話內容 -->
        <p>
            <textarea cols="40" rows="3" id="common_msg" name="common_msg" class="resizable" style="line-height:130%;"><?php echo esc_textarea($mpu_opt['common_msg']); ?></textarea><br />
            <?php _e('所有春菜共用的會話內容。', 'mp-ukagaka'); ?><br />
            <?php _e('一旦填寫此欄，通用會話將取代每個春菜的自訂會話。清空此欄則使用各春菜的預設自訂會話。', 'mp-ukagaka'); ?>
        </p>

        <!-- 提交按鈕：儲存設定 -->
        <p><input name="submit5" class="button" value="<?php _e(' 儲 存 ', 'mp-ukagaka'); ?>" type="submit" /></p>
    </form>
</div>