<div>
    <!-- 標題：創建新春菜 -->
    <h3><?php _e('創建新春菜', 'mp-ukagaka'); ?></h3>

    <!-- 表單：用於提交新春菜的創建 -->
    <form method="post" name="create" id="create" action="<?php echo admin_url('options-general.php?page=' . $base_name . '&cur_page=2'); ?>">
        <?php wp_nonce_field('mp_ukagaka_settings'); ?>
        <!-- 選項：是否可顯示 -->
        <p><label><input type="checkbox" name="ukagaka[show]" value="true" /><?php _e('可顯示', 'mp-ukagaka'); ?></label></p>

        <!-- 輸入欄：春菜名稱 -->
        <p><label><?php _e('名稱：', 'mp-ukagaka'); ?><br /><input type="text" name="ukagaka[name]" value="" size="45" /></label></p>

        <!-- 輸入欄：春菜圖片 URL，預設值為 http:// -->
        <p><label><?php _e('圖片：', 'mp-ukagaka'); ?><br /><input type="text" name="ukagaka[shell]" value="http://" size="45" /></label></p>

        <!-- 輸入欄：春菜吐槽內容 -->
        <p>
            <label><?php _e('吐槽：', 'mp-ukagaka'); ?><br /><textarea name="ukagaka[msg]" rows="5" cols="60" class="resizable" style="line-height:130%;"></textarea></label><br />
            <?php _e('每行一條吐槽，不可使用 HTML 代碼。', 'mp-ukagaka'); ?>
        </p>

        <!-- 輸入欄：對話檔案名稱 -->
        <p><label><?php _e('對話檔案名稱：', 'mp-ukagaka'); ?><br /><input type="text" name="ukagaka[dialog_filename]" value="" size="45" /></label><br />
            <?php _e('此名稱將用於外部對話檔案，例如：asuna.txt 或 asuna.json', 'mp-ukagaka'); ?></p>

        <!-- 新增：生成對話檔案的勾選框 -->
        <p><label><input type="checkbox" name="generate_dialog_file_new" value="true" /><?php _e('生成對話檔案', 'mp-ukagaka'); ?></label><br />
            <?php _e('勾選此項將使用上方吐槽內容生成對應的對話檔案', 'mp-ukagaka'); ?></p>

        <!-- 提交按鈕：創建新春菜 -->
        <p><input name="submit3" class="button" value="<?php _e(' 創 建 ', 'mp-ukagaka'); ?>" type="submit" /></p>
    </form>
</div>