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

    .mpu-settings-card .radio-group {
        display: flex;
        gap: 20px;
        flex-wrap: wrap;
    }

    .mpu-settings-card .radio-group label {
        font-weight: normal;
    }
</style>

<div>
    <h3><?php _e('通用設定', 'mp-ukagaka'); ?></h3>
    <p style="color: #5A7A8C; margin-bottom: 20px;">
        <small><?php _e('此頁面用於設定春菜的基本顯示和行為選項。', 'mp-ukagaka'); ?></small>
    </p>
    <form method="post" name="setting" id="setting" action="<?php echo admin_url('options-general.php?page=' . $base_name . '&cur_page=0'); ?>">
        <?php wp_nonce_field('mp_ukagaka_settings'); ?>

        <!-- 基本顯示設定 -->
        <div class="mpu-settings-card">
            <h4><?php _e('👤 基本顯示設定', 'mp-ukagaka'); ?></h4>
            <div class="mpu-field-group">
                <label for="cur_ukagaka"><?php _e('預設春菜：', 'mp-ukagaka'); ?></label>
                <select id="cur_ukagaka" name="cur_ukagaka" style="width: 100%; max-width: 300px;">
                    <?php foreach ($mpu_opt['ukagakas'] as $key => $value) { ?>
                        <option value="<?php echo $key; ?>" <?php if ($key == $mpu_opt['cur_ukagaka']) {
                                                                    echo ' selected="selected"';
                                                                } ?>><?php echo mpu_output_filter($value['name']); ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="mpu-field-group">
                <label><input id="show_ukagaka" name="show_ukagaka" type="checkbox" value="true" <?php if ($mpu_opt['show_ukagaka']) {
                                                                                                        echo ' checked="checked"';
                                                                                                    } ?> /><?php _e('預設顯示春菜', 'mp-ukagaka'); ?></label>
            </div>
            <div class="mpu-field-group">
                <label><input id="show_msg" name="show_msg" type="checkbox" value="true" <?php if ($mpu_opt['show_msg']) {
                                                                                                echo ' checked="checked"';
                                                                                            } ?> /><?php _e('預設顯示對話框', 'mp-ukagaka'); ?></label>
            </div>
        </div>

        <!-- 對話行為設定 -->
        <div class="mpu-settings-card">
            <h4><?php _e('💬 對話行為設定', 'mp-ukagaka'); ?></h4>
            <div class="mpu-field-group">
                <label><?php _e('預設會話：', 'mp-ukagaka'); ?></label>
                <div class="radio-group">
                    <label><input name="default_msg[]" type="radio" value="0" <?php if ($mpu_opt['default_msg'] == 0) {
                                                                                    echo ' checked="checked"';
                                                                                } ?> /><?php _e('隨機吐槽', 'mp-ukagaka'); ?></label>
                    <label><input name="default_msg[]" type="radio" value="1" <?php if ($mpu_opt['default_msg'] == 1) {
                                                                                    echo ' checked="checked"';
                                                                                } ?> /><?php _e('第一條吐槽', 'mp-ukagaka'); ?></label>
                </div>
            </div>
            <div class="mpu-field-group">
                <label><?php _e('會話順序：', 'mp-ukagaka'); ?></label>
                <div class="radio-group">
                    <label><input name="next_msg[]" type="radio" value="0" <?php if ($mpu_opt['next_msg'] == 0) {
                                                                                echo ' checked="checked"';
                                                                            } ?> /><?php _e('順序吐槽', 'mp-ukagaka'); ?></label>
                    <label><input name="next_msg[]" type="radio" value="1" <?php if ($mpu_opt['next_msg'] == 1) {
                                                                                echo ' checked="checked"';
                                                                            } ?> /><?php _e('隨機吐槽', 'mp-ukagaka'); ?></label>
                </div>
            </div>
            <div class="mpu-field-group">
                <label><?php _e('點擊春菜：', 'mp-ukagaka'); ?></label>
                <div class="radio-group">
                    <label><input name="click_ukagaka[]" type="radio" value="0" <?php if ($mpu_opt['click_ukagaka'] == 0) {
                                                                                    echo ' checked="checked"';
                                                                                } ?> /><?php _e('下一條吐槽', 'mp-ukagaka'); ?></label>
                    <label><input name="click_ukagaka[]" type="radio" value="1" <?php if ($mpu_opt['click_ukagaka'] == 1) {
                                                                                    echo ' checked="checked"';
                                                                                } ?> /><?php _e('無操作', 'mp-ukagaka'); ?></label>
                </div>
            </div>
        </div>

        <!-- 自動對話設定 -->
        <div class="mpu-settings-card">
            <h4><?php _e('⏰ 自動對話設定', 'mp-ukagaka'); ?></h4>
            <div class="mpu-field-group">
                <label><input name="auto_talk" type="checkbox" value="true" <?php if (isset($mpu_opt['auto_talk']) && $mpu_opt['auto_talk']) {
                                                                                echo ' checked="checked"';
                                                                            } ?> /><?php _e('啟用自動對話功能', 'mp-ukagaka'); ?></label>
            </div>
            <div class="mpu-field-group">
                <label><?php _e('自動對話間隔時間（秒）：', 'mp-ukagaka'); ?>
                    <input type="number" name="auto_talk_interval" value="<?php echo isset($mpu_opt['auto_talk_interval']) ? intval($mpu_opt['auto_talk_interval']) : 8; ?>" min="3" max="30" style="width: 80px;" />
                </label>
            </div>
            <div class="mpu-field-group">
                <label><?php _e('打字效果速度（毫秒/字）：', 'mp-ukagaka'); ?>
                    <input type="number" name="typewriter_speed" value="<?php echo isset($mpu_opt['typewriter_speed']) ? intval($mpu_opt['typewriter_speed']) : 40; ?>" min="10" max="200" style="width: 80px;" />
                </label>
                <small><?php _e('數值越小打字越快，建議範圍 20-80，預設 40', 'mp-ukagaka'); ?></small>
            </div>
        </div>

        <!-- 對話文件設定 -->
        <div class="mpu-settings-card">
            <h4><?php _e('📄 對話文件設定', 'mp-ukagaka'); ?></h4>
            <div class="mpu-field-group">
                <label><?php _e('外部對話文件格式：', 'mp-ukagaka'); ?></label>
                <div class="radio-group">
                    <label><input name="external_file_format[]" type="radio" value="txt" <?php if (!isset($mpu_opt['external_file_format']) || $mpu_opt['external_file_format'] == 'txt') {
                                                                                            echo ' checked="checked"';
                                                                                        } ?> /><?php _e('純文字格式 (TXT)', 'mp-ukagaka'); ?></label>
                    <label><input name="external_file_format[]" type="radio" value="json" <?php if (isset($mpu_opt['external_file_format']) && $mpu_opt['external_file_format'] == 'json') {
                                                                                                echo ' checked="checked"';
                                                                                            } ?> /><?php _e('JSON 格式', 'mp-ukagaka'); ?></label>
                </div>
                <small><?php _e('注意：系統已固定使用外部對話文件，對話將從 dialogs/ 資料夾讀取。', 'mp-ukagaka'); ?></small>
            </div>
        </div>

        <!-- 樣式設定 -->
        <div class="mpu-settings-card">
            <h4><?php _e('🎨 樣式設定', 'mp-ukagaka'); ?></h4>
            <div class="mpu-field-group">
                <label><input type="checkbox" id="no_style" name="no_style" value="true" <?php if ($mpu_opt['no_style']) {
                                                                                                echo ' checked="checked"';
                                                                                            } ?> /><?php _e('使用自訂樣式', 'mp-ukagaka'); ?></label>
            </div>
        </div>

        <!-- 頁面排除設定 -->
        <div class="mpu-settings-card">
            <h4><?php _e('🚫 頁面排除設定', 'mp-ukagaka'); ?></h4>
            <div class="mpu-field-group">
                <label for="no_page"><?php _e('不在以下頁面顯示春菜', 'mp-ukagaka'); ?></label>
                <textarea cols="40" rows="3" id="no_page" name="no_page" class="resizable" style="line-height:130%; width: 100%; max-width: 500px;"><?php echo $mpu_opt['no_page']; ?></textarea>
                <small><?php _e('輸入不顯示春菜的頁面 URL，每行一條。在地址尾部加入 (*) 可進行模糊匹配。', 'mp-ukagaka'); ?></small>
            </div>
        </div>

        <!-- 可選高級設定：HTML 生成位置（僅在特定模式下顯示） -->
        <?php if (isset($_GET['mpu_mode'])) { ?>
            <div class="mpu-settings-card">
                <h4><?php _e('⚙️ HTML 生成位置', 'mp-ukagaka'); ?></h4>
                <div class="mpu-field-group">
                    <small style="color: #d63638;"><?php _e('一般情況下請勿更改', 'mp-ukagaka'); ?></small>
                    <div class="radio-group" style="flex-direction: column; gap: 10px; margin-top: 10px;">
                        <label><input type="radio" name="insert_html[]" value="0" <?php if ($mpu_opt['insert_html'] == 0) {
                                                                                        echo ' checked="checked"';
                                                                                    } ?> /><?php _e('在</body>前', 'mp-ukagaka'); ?></label>
                        <small style="margin-left: 20px;"><?php _e('當春菜無法顯示（主題尾部無 wp_footer() 函數）或 wp_footer() 位置導致樣式異常時，請勾選此項', 'mp-ukagaka'); ?></small>
                        <label><input type="radio" name="insert_html[]" value="1" <?php if ($mpu_opt['insert_html'] == 1) {
                                                                                        echo ' checked="checked"';
                                                                                    } ?> /><?php _e('在 wp_footer() 處', 'mp-ukagaka'); ?></label>
                        <small style="margin-left: 20px;"><?php _e('這是 WordPress 常用方式，但若主題尾部無 wp_footer() 函數，春菜將無法顯示', 'mp-ukagaka'); ?></small>
                    </div>
                </div>
            </div>
        <?php } ?>

        <p><input name="submit1" class="button" value="<?php _e(' 儲 存 ', 'mp-ukagaka'); ?>" type="submit" /></p>
    </form>

    <!-- 插件資訊 -->
    <div class="mpu-settings-card">
        <h4><?php _e('ℹ️ 插件資訊', 'mp-ukagaka'); ?></h4>
        <div class="mpu-field-group">
            <p><?php _e('版本：', 'mp-ukagaka'); ?> <strong><?php echo MPU_VERSION; ?></strong></p>
            <p><?php _e('春菜數量：', 'mp-ukagaka'); ?> <strong><?php echo count($mpu_opt['ukagakas']); ?></strong></p>
            <p><?php _e('平均會話數：', 'mp-ukagaka'); ?> <strong><?php echo round(mpu_count_total_msg() / count($mpu_opt['ukagakas']), 1); ?></strong></p>
            <p><?php _e('維護者網站：', 'mp-ukagaka'); ?> <a href="https://www.moelog.com/" title="MoeLog"><?php _e('前往維護者網站', 'mp-ukagaka'); ?></a></p>
        </div>
    </div>

    <!-- 重置設定 -->
    <form method="post" name="setting" id="setting" action="<?php echo admin_url('options-general.php?page=' . $base_name . '&cur_page=0'); ?>">
        <div class="mpu-settings-card" style="border-color: #E57373; background: #FFEBEE;">
            <h4 style="color: #C62828;"><?php _e('⚠️ 重置設定', 'mp-ukagaka'); ?></h4>
            <div class="mpu-field-group">
                <label><input id="reset_mpu" name="reset_mpu" type="checkbox" value="true" /><?php _e('確認重置', 'mp-ukagaka'); ?></label>
                <small style="color: #C62828;"><?php _e('重置設定將還原插件的所有配置為預設值，所有設定及春菜將被刪除。此操作無法撤銷，請謹慎操作。', 'mp-ukagaka'); ?></small>
            </div>
            <p><input name="submit_reset" class="button" value="<?php _e(' 重 置 ', 'mp-ukagaka'); ?>" type="submit" style="background: linear-gradient(135deg, #E57373 0%, #EF5350 100%); border-color: #E57373; color: white; box-shadow: 0 2px 4px rgba(229, 115, 115, 0.2);" /></p>
        </div>
    </form>
</div>
