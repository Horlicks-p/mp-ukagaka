<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!isset($mpu_opt) || !is_array($mpu_opt)) {
    $mpu_opt = function_exists('mpu_get_option') ? mpu_get_option() : [];
}

if (empty($mpu_opt['ukagakas']) || !is_array($mpu_opt['ukagakas'])) {
    $mpu_opt['ukagakas'] = [];
}
?>
<div>
    <h3><?php _e('👻 偽春菜們', 'mp-ukagaka'); ?></h3>
    <p style="color: #8A7FA0; margin-bottom: 20px;">
        <small><?php _e('圖片欄中，請填寫完整的 URL，不要忘記以 http:// 開頭。吐槽欄中，每行代表一條吐槽。不可使用 HTML 代碼。', 'mp-ukagaka'); ?></small>
    </p>
    <form method="post" name="ukagakas" id="ukagakas" action="<?php echo admin_url('options-general.php?page=' . $base_name . '&cur_page=1'); ?>">
        <?php foreach ($mpu_opt['ukagakas'] as $key => $value) { ?>
            <?php wp_nonce_field('mp_ukagaka_settings'); ?>

            <!-- 偽春菜單個設定區塊 -->
            <div class="mpu-settings-card">
                <div class="mpu-ukagaka-header">
                    <h4>#<?php echo esc_attr($key); ?> - <?php echo mpu_output_filter($value['name']); ?></h4>
                    <?php if ($key == str_replace('default', '', $key)) { ?>
                        <a href="<?php echo admin_url('options-general.php?page=' . $base_name . '&cur_page=1&del=' . esc_attr($key)); ?>" class="mpu-delete-link">[<?php _e('刪除', 'mp-ukagaka'); ?>]</a>
                    <?php } ?>
                </div>

                <div class="mpu-field-group" style="display: flex; align-items: center; gap: 24px; flex-wrap: wrap;">
                    <label><input type="checkbox" name="ukagakas[<?php echo esc_attr($key); ?>][show]" value="true" <?php if ($value['show']) {
                                                                                                                echo ' checked="checked"';
                                                                                                            } ?> /><?php _e('可顯示', 'mp-ukagaka'); ?></label>
                    <?php if ($key === 'default_1') { ?>
                        <label style="display: flex; align-items: center; gap: 5px;">
                            <input type="checkbox" name="ukagakas[<?php echo esc_attr($key); ?>][show_decorations]" value="true" <?php if (isset($value['show_decorations']) && $value['show_decorations']) {
                                                                                                                            echo ' checked="checked"';
                                                                                                                        } ?> /><?php _e('アクセサリー表示', 'mp-ukagaka'); ?>
                            <small style="color: #8A7FA0;"><?php _e('（スーツケース、巨大な頭蓋骨、魔法の杖と魔法の本）', 'mp-ukagaka'); ?></small>
                        </label>
                    <?php } ?>
                </div>

                <div class="mpu-field-group">
                    <label><?php _e('名稱：', 'mp-ukagaka'); ?></label>
                    <input type="text" name="ukagakas[<?php echo esc_attr($key); ?>][name]" value="<?php echo mpu_output_filter($value['name']); ?>" style="width: 100%; max-width: 400px;" />
                </div>

                <div class="mpu-field-group">
                    <label><?php _e('圖片：', 'mp-ukagaka'); ?></label>
                    <input type="text" name="ukagakas[<?php echo esc_attr($key); ?>][shell]" value="<?php echo mpu_output_filter($value['shell']); ?>" style="width: 100%; max-width: 500px;" />
                    <small><?php _e('請填寫完整的 URL，不要忘記以 http:// 或 https:// 開頭', 'mp-ukagaka'); ?></small>
                </div>

                <div class="mpu-field-group">
                    <label><?php _e('吐槽：', 'mp-ukagaka'); ?></label>
                    <textarea name="ukagakas[<?php echo esc_attr($key); ?>][msg]" rows="3" cols="60" class="resizable" style="line-height:130%; width: 100%; max-width: 850px;"><?php echo esc_textarea(mpu_array2str($value['msg'])); ?></textarea>
                    <small><?php _e('每行代表一條吐槽。不可使用 HTML 代碼。', 'mp-ukagaka'); ?></small>
                </div>

                <div class="mpu-field-group">
                    <label><?php _e('對話檔案名稱：', 'mp-ukagaka'); ?></label>
                    <input type="text" name="ukagakas[<?php echo esc_attr($key); ?>][dialog_filename]" value="<?php echo isset($value['dialog_filename']) ? mpu_output_filter($value['dialog_filename']) : $key; ?>" style="width: 100%; max-width: 300px;" />
                    <small><?php _e('此名稱將用於外部對話檔案，例如：asuna.txt 或 asuna.json', 'mp-ukagaka'); ?></small>
                </div>

                <div class="mpu-field-group">
                    <label><input type="checkbox" name="generate_dialog_file[<?php echo esc_attr($key); ?>]" value="true" /><?php _e('生成對話檔案', 'mp-ukagaka'); ?></label>
                    <small><?php _e('勾選此項將使用上方吐槽內容生成對應的對話檔案', 'mp-ukagaka'); ?></small>
                </div>
            </div>
        <?php } ?>

        <p><input name="submit2" class="button" value="<?php _e(' 儲 存 ', 'mp-ukagaka'); ?>" type="submit" /></p>
    </form>
</div>
