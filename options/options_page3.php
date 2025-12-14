<div>
    <!-- 標題：擴展 -->
    <h3><?php _e('擴展', 'mp-ukagaka'); ?></h3>

    <!-- 警告說明：提醒用戶操作需謹慎 -->
    <p><?php _e('如果您不懂如何操作或編寫代碼，請勿更改此頁。', 'mp-ukagaka'); ?></p>

    <!-- 表單：用於提交擴展設定的表單 -->
    <form method="post" name="setting" id="setting" action="<?php echo admin_url('options-general.php?page=' . $base_name . '&cur_page=3'); ?>">
        <?php wp_nonce_field('mp_ukagaka_settings'); ?>
        <!-- 分隔線 -->
        <div style="height:1px;background:#DFDFDF;width:400px;"></div>

        <!-- 子標題：JS 區 -->
        <h4><?php _e('JS 區', 'mp-ukagaka'); ?></h4>

        <!-- 說明：JS 區的功能和使用方式 -->
        <p>
            <?php _e('可在此填寫 JavaScript 代碼，為春菜自訂更多的回應事件。', 'mp-ukagaka'); ?>
            <br />
            <?php _e('無需使用 &lt;script&gt; 標籤，代碼將寫入到 &lt;head&gt; 部分。', 'mp-ukagaka'); ?>
        </p>

        <!-- 輸入欄：JS 代碼區域 -->
        <p>
            <textarea rows="8" cols="40" id="js_area" name="extend[js_area]" class="resizable" style="line-height:130%;"><?php echo isset($mpu_opt['extend']['js_area']) ? htmlspecialchars($mpu_opt['extend']['js_area']) : ''; ?></textarea>
        </p>

        <!-- 提交按鈕：儲存設定 -->
        <p><input name="submit4" class="button" value="<?php _e(' 儲 存 ', 'mp-ukagaka'); ?>" type="submit" /></p>

        <!-- 分隔線 -->
        <div style="height:1px;background:#DFDFDF;width:400px;"></div>

        <!-- 子標題：代碼擴展 -->
        <h4><?php _e('代碼擴展', 'mp-ukagaka'); ?></h4>

        <!-- 說明：代碼擴展的功能和參考連結 -->
        <p>
            <?php _e('您可以在春菜的資訊框中使用特殊代碼來顯示特定的資訊，例如日誌列表。', 'mp-ukagaka'); ?>
            <br />
            <?php _e('更多擴展代碼資訊請參閱：', 'mp-ukagaka'); ?>
            <a href="https://github.com/Horlicks-p/mp-ukagaka/tree/main/docs" target="_blank" title="MP Ukagaka 文檔中心">MP Ukagaka 文檔中心</a>
        </p>
    </form>
</div>