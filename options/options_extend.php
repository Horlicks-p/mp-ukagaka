<div>
    <h3><?php _e('🔌 擴展', 'mp-ukagaka'); ?></h3>
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
                <?php if (!current_user_can('unfiltered_html')): ?>
                <div class="notice notice-warning inline" style="margin:8px 0;">
                    <p><?php _e('⚠️ 您的帳號不具備 <code>unfiltered_html</code> 權限，無法儲存自訂 JS。此欄位在多站點或受限角色環境下為唯讀。', 'mp-ukagaka'); ?></p>
                </div>
                <?php endif; ?>
                <textarea rows="8" cols="40" id="js_area" name="extend[js_area]" class="resizable"
                    style="line-height:130%; width: 100%; max-width: 700px; font-family: 'Courier New', Consolas, monospace;"
                    <?php echo !current_user_can('unfiltered_html') ? 'readonly aria-readonly="true"' : ''; ?>
                ><?php echo isset($mpu_opt['extend']['js_area']) ? htmlspecialchars($mpu_opt['extend']['js_area']) : ''; ?></textarea>
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